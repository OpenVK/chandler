<?php declare(strict_types=1);
namespace Chandler\Database;
use Chandler\Database\DatabaseConnection;
use Nette\Database\Table\ActiveRow;
use Chandler\Database\Log;
use Chandler\Database\CurrentUser;

class Logs
{
    private $context;
    private $logs;

    function __construct()
    {
        $this->context = DatabaseConnection::i()->getContext();
        $this->logs  = $this->context->table("ChandlerLogs");
    }

    private function toLog(?ActiveRow $ar): ?Log
    {
        return is_null($ar) ? NULL : new Log($ar);
    }

    function get(int $id): ?Log
    {
        return $this->toLog($this->logs->get($id));
    }

    function create(string $user, string $table, string $model, int $type, $object, $changes, ?string $ip = NULL, ?string $useragent = NULL): void
    {
        if ($model !== "Chandler\Database\Log") {
            $fobject = (is_array($object) ? $object : $object->unwrap());
            $nobject = [];
            $_changes = [];

            if ($type === 1) {
                foreach ($changes as $field => $value) {
                    $nobject[$field] = $fobject[$field];
                }

                foreach (array_diff_assoc($nobject, $changes) as $field => $value) {
                    if (str_starts_with($field, "rate_limit")) continue;
                    if ($field === "online") continue;
                    $_changes[$field] = xdiff_string_diff((string)$nobject[$field], (string)$changes[$field]);
                }

                if (count($_changes) === 0) return;
            } else if ($type === 0) { // if new
                $nobject = $fobject;
                foreach ($fobject as $field => $value) {
                    $_changes[$field] = xdiff_string_diff("", (string)$value);
                }
            } else if ($type === 2 || $type === 3) { // if deleting or restoring
                $_changes["deleted"] = (int)($type === 2);
            }

            $log = new Log;
            $log->setUser($user);
            $log->setType($type);
            $log->setObject_Table($table);
            $log->setObject_Model($model);
            $log->setObject_Id(is_array($object) ? $object["id"] : $object->getId());
            $log->setXdiff_Old(json_encode($nobject));
            $log->setXdiff_New(json_encode($_changes));
            $log->setTs(time());
            $log->setIp(CurrentUser::i()->getIP());
            $log->setUserAgent(CurrentUser::i()->getUserAgent());
            $log->save();
        }
    }

    function search($filter): \Traversable
    {
        foreach ($this->logs->where($filter)->order("id DESC") as $log)
            yield new Log($log);
    }

    function getTypes(): array
    {
        $types = [];
        foreach ($this->context->query("SELECT DISTINCT(`object_model`) AS `object_model` FROM `ChandlerLogs`")->fetchAll() as $type)
            $types[] = str_replace(CHANDLER_ROOT_CONF["preferences"]["logs"]["entitiesNamespace"], "", $type->object_model);

        return $types;
    }
}
