<?php declare(strict_types=1);

namespace Chandler\Database;

class CurrentUser
{
    private static $instance = null;
    private $ip;
    private $useragent;

    public function __construct(?string $ip = NULL, ?string $useragent = NULL)
    {
        if ($ip)
            $this->ip = $ip;

        if ($useragent)
            $this->useragent = $useragent;
    }

    public static function get($ip, $useragent)
    {
        if (self::$instance === null) self::$instance = new self($ip, $useragent);
        return self::$instance;
    }

    public function getIP(): string
    {
        return $this->ip;
    }

    public function getUserAgent(): string
    {
        return $this->useragent;
    }

    public static function i()
    {
        return self::$instance;
    }
}
