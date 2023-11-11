<?php declare(strict_types=1);
namespace Chandler\Signaling;
use Chandler\Patterns\TSimpleSingleton;

/**
 * Signal manager (singleton).
 * Signals are events, that are meant to be recieved by end user.
 * 
 * @author kurotsun <celestine@vriska.ru>
 */
class SignalManager
{
    /**
     * @var int Latest event timestamp.
     */
    private $since;
    /**
     * @var \PDO PDO Connection to events SQLite DB.
     */
    private $connection;
    
    /**
     * @internal
     */
    private function __construct()
    {
        $this->since = time();
        $this->connection = new \PDO( 
            'sqlite:' . CHANDLER_ROOT . '/tmp/events.bin', 
            null, 
            null, 
            [\PDO::ATTR_PERSISTENT => true]
        );
        $this->connection->query("CREATE TABLE IF NOT EXISTS pool(id INTEGER PRIMARY KEY AUTOINCREMENT, since INTEGER, for INTEGER, event TEXT);");
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
        $since     = $this->since - 1;
        $statement = $this->connection->query("SELECT * FROM pool WHERE `for` = $for AND `since` > $since ORDER BY since DESC");
        $event     = $statement->fetch(\PDO::FETCH_LAZY);
        if(!$event) return null;
        
        $this->since = time();
        return [$event->id, unserialize(hex2bin($event->event))];
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
    function listen(\Closure $callback, int $for, int $time = 25): void
    {
        $this->since = time() - 1;
        for($i = 0; $i < $time; $i++) {
            sleep(1);
            
            $event = $this->eventFor($for);
            if(!$event) continue;
            
            list($id, $evt) = $event;
            $id = crc32((string)$id);
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
    function tipFor(int $for): int
    {
        $statement = $this->connection->query("SELECT since FROM pool WHERE `for` = $for ORDER BY since DESC");
        $result    = $statement->fetch(\PDO::FETCH_LAZY);
        if(!$result) return 1;
        
        return $result->since;
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
    function getHistoryFor(int $for, ?int $tip = NULL, int $limit = 1000): array
    {
        $res   = [];
        $tip   = $tip ?? $this->tipFor($for);
        $query = $this->connection->query("SELECT * FROM pool WHERE `for` = $for AND `since` > $tip ORDER BY since DESC LIMIT $limit");
        foreach($query as $event)
            $res[] = unserialize(hex2bin($event["event"]));
        
        return $res;
    }
    
    /**
     * Triggers event for user and sends signal to DB and listeners.
     * 
     * @api
     * @param object $event Event
     * @param int $for User ID
     * @return bool Success state
     */
    function triggerEvent(object $event, int $for): bool
    {
        $event = bin2hex(serialize($event));
        $since = time();
        
        $this->connection->query("INSERT INTO pool VALUES (NULL, $since, $for, '$event')");
        return true;
    }
    
    use TSimpleSingleton;
}
