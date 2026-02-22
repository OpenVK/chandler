<?php

declare(strict_types=1);

namespace Chandler\Signaling;

use Chandler\Patterns\TSimpleSingleton;
use Predis\Client as RedisClient;

/**
 * Signal manager (singleton).
 * Signals are events, that are meant to be recieved by end user.
 *
 * @author kurotsun <celestine@vriska.ru>
 * @author Vladimir Barinov <veselcraft@icloud.com>
 */
class SignalManager
{
    use TSimpleSingleton;
    
    /**
     * @var int Latest event timestamp.
     */
    private $since;
    
    /**
     * @var \PDO PDO Connection to events SQLite DB.
     */
    private $connection;
    
    /**
     * @var RedisClient|null Redis client for events.
     */
    private $redisClient;
    
    /**
     * @var bool Whether to use Redis for operations.
     */
    private $useRedis = false;

    /**
     * @internal
     */
    private function __construct()
    {
        $this->since = time();
        
        // Check if Redis is configured
        if (!empty(CHANDLER_ROOT_CONF["redisUrl"])) {
            try {
                $this->redisClient = new RedisClient(CHANDLER_ROOT_CONF["redisUrl"]);
                $this->useRedis = true;
                // Test connection
                $this->redisClient->ping();
            } catch (\Exception $e) {
                error_log("Redis connection failed, falling back to SQLite: " . $e->getMessage());
                $this->useRedis = false;
                $this->redisClient = null;
            }
        }
        
        // Initialize SQLite connection (fallback)
        if (!$this->useRedis) {
            $this->connection = new \PDO(
                'sqlite:' . CHANDLER_ROOT . '/tmp/events.bin',
                null,
                null,
                [\PDO::ATTR_PERSISTENT => true]
            );
            $this->connection->query("CREATE TABLE IF NOT EXISTS pool(id INTEGER PRIMARY KEY AUTOINCREMENT, since INTEGER, for INTEGER, event TEXT);");
        }
    }

    /**
     * Waits for event for user with ID = $for.
     * This function is blocking.
     *
     * @internal
     * @param int $for User ID
     * @return array|null Array of events if there are any, null otherwise
     */
    private function eventFor(int $for): ?array
    {
        if ($this->useRedis && $this->redisClient) {
            // Use Redis
            $since = $this->since - 1;
            $key = "events:$for";
            $events = $this->redisClient->zrevrangebyscore($key, '+inf', $since, ['LIMIT' => [0, 1]]);
            
            if (empty($events)) {
                return null;
            }
            
            $eventData = json_decode($events[0], true);
            $this->since = time();
            return [$eventData['id'], unserialize(hex2bin($eventData['event']))];
        } else {
            // Use SQLite
            $since     = $this->since - 1;
            $statement = $this->connection->query("SELECT * FROM pool WHERE `for` = $for AND `since` > $since ORDER BY since DESC");
            $event     = $statement->fetch(\PDO::FETCH_LAZY);
            if (!$event) {
                return null;
            }

            $this->since = time();
            return [$event->id, unserialize(hex2bin($event->event))];
        }
    }

    /**
     * Set ups listener.
     * This function blocks the thread and calls $callback each time
     * a signal is recieved for user with ID = $for
     *
     * @api
     * @param \Closure $callback Callback
     * @param int $for User ID
     * @uses \Chandler\Signaling\SignalManager::eventFor
     * @return void
     */
    public function listen(\Closure $callback, int $for, int $time = 25): void
    {
        try {
            $redisClient = new RedisClient(CHANDLER_ROOT_CONF["redisUrl"], ['read_write_timeout' => $time]);

            // We will catch the old message first
            $oldEvent = $this->eventFor($for);
            
            if ($oldEvent) {
                list($id, $evt) = $oldEvent;
                $id = crc32((string)$id);
                $callback($evt, $id);
            }

            // And then we will subscribe to user's channel
            $subscriber = $redisClient->pubSubLoop();
            $subscriber->subscribe('im'.$for);

            foreach($subscriber as $event) {
                if ($event->kind == 'message' && $event->channel == 'im'.$for) {
                    list($id, $evt) = json_decode($event->payload);
                    $id = crc32((string)$id);
                    $evt = unserialize(hex2bin($evt));
                    $callback($evt, $id);
                }
            }

            // On timeout we're returning nothing
            exit("[]");
        } 
        catch (Exception $e) 
        {
            error_log("Couldn't connect to Redis server, fallback to old sqlite method. Exception Message: ".$e->getMessage());
        }

        $this->since = time() - 1;
        for ($i = 0; $i < ($time / 5); $i++) {
            sleep(1);

            $event = $this->eventFor($for);
            if (!$event) {
                continue;
            }

            [$id, $evt] = $event;
            $id = crc32((string) $id);
            $callback($evt, $id);
        }

        exit("[]");
    }

    /**
     * Gets creation time of user's last event.
     * If there is no events returns 1.
     *
     * @api
     * @param int $for User ID
     * @return int
     */
    public function tipFor(int $for): int
    {
        if ($this->useRedis && $this->redisClient) {
            // Use Redis
            $key = "events:$for";
            $events = $this->redisClient->zrevrange($key, 0, 0, ['WITHSCORES' => true]);
            
            if (empty($events)) {
                return 1;
            }
            
            // Return the score (timestamp) of the latest event
            return (int) reset($events);
        } else {
            // Use SQLite
            $statement = $this->connection->query("SELECT since FROM pool WHERE `for` = $for ORDER BY since DESC");
            $result    = $statement->fetch(\PDO::FETCH_LAZY);
            if (!$result) {
                return 1;
            }

            return $result->since;
        }
    }

    /**
     * Gets history of long pool events.
     * If there is no events returns empty array.
     *
     * @api
     * @param int $for User ID
     * @param int|null $tip last sync time
     * @return array
     */
    public function getHistoryFor(int $for, ?int $tip = null, int $limit = 1000): array
    {
        if ($this->useRedis && $this->redisClient) {
            // Use Redis
            $res = [];
            $tip ??= $this->tipFor($for);
            $key = "events:$for";
            
            // Get events with scores greater than tip
            $events = $this->redisClient->zrevrangebyscore($key, '+inf', $tip, ['LIMIT' => [0, $limit]]);
            
            foreach ($events as $eventJson) {
                $eventData = json_decode($eventJson, true);
                $res[] = unserialize(hex2bin($eventData['event']));
            }
            
            return $res;
        } else {
            // Use SQLite
            $res   = [];
            $tip ??= $this->tipFor($for);
            $query = $this->connection->query("SELECT * FROM pool WHERE `for` = $for AND `since` > $tip ORDER BY since DESC LIMIT $limit");
            foreach ($query as $event) {
                $res[] = unserialize(hex2bin($event["event"]));
            }

            return $res;
        }
    }

    /**
     * Triggers event for user and sends signal to DB and listeners.
     *
     * @api
     * @param object $event Event
     * @param int $for User ID
     * @return bool Success state
     */
    public function triggerEvent(object $event, int $for): bool
    {
        $event = bin2hex(serialize($event));
        $since = time();
        $id = null;
        
        if ($this->useRedis && $this->redisClient) {
            // Use Redis
            $key = "events:$for";
            $id = time() . rand(1000, 9999); // Simple ID generation
            $eventData = [
                'id' => $id,
                'event' => $event,
                'since' => $since
            ];
            $this->redisClient->zadd($key, $since, json_encode($eventData));
        } else {
            // Use SQLite
            $this->connection->query("INSERT INTO pool VALUES (NULL, $since, $for, '$event')");
            $id = $this->connection->lastInsertId();
        }

        // Try to publish to Redis for real-time notifications (existing behavior)
        if ($id !== null) {
            try {
                $redisClient = new RedisClient(CHANDLER_ROOT_CONF["redisUrl"]);
                $redisClient->publish('im'.$for, json_encode([$id, $event]));
            } catch (Exception $e) {
                error_log("Couldn't connect to Redis server and push the event. Exception Message: ".$e->getMessage());
            }
        }
        
        return true;
    }
}
