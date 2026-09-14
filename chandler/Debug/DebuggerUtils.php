<?php

declare(strict_types=1);

namespace Chandler\Debug;

use Tracy\Debugger;
use Tracy\Logger;

class DebuggerUtils
{
    private static ?string $lastErrorCode = null;

    /**
     * Extracts or calculates the Tracy deduplication hash for an exception.
     * Also saves it as the last recorded error code.
     *
     * @param \Throwable|null $e
     * @return string|null 10-character hash (e.g. "a1b2c3d4e5") or null if not available
     */
    public static function getErrorCode(?\Throwable $e = null): ?string
    {
        if ($e === null) {
            return self::getLastErrorCode();
        }

        $code = null;

        if (class_exists(Debugger::class)) {
            $logger = Debugger::getLogger();
            if ($logger instanceof Logger || (is_object($logger) && method_exists($logger, "getExceptionFile"))) {
                try {
                    $file = $logger->getExceptionFile($e);
                    if (preg_match('/--([a-f0-9]+)\.html$/i', $file, $matches)) {
                        $code = $matches[1];
                    }
                } catch (\Throwable) {
                    // Fallback to manual computation below
                }
            }
        }

        if ($code === null) {
            $data = [];
            $curr = $e;
            while ($curr !== null) {
                $data[] = [
                    get_class($curr),
                    $curr->getMessage(),
                    $curr->getCode(),
                    $curr->getFile(),
                    $curr->getLine(),
                    array_map(function (array $item): array {
                        unset($item["args"]);
                        return $item;
                    }, $curr->getTrace()),
                ];
                $curr = $curr->getPrevious();
            }
            $code = substr(hash("xxh128", serialize($data)), 0, 10);
        }

        self::setLastErrorCode($code);

        return $code;
    }

    /**
     * Sets the last recorded error code / Tracy hash.
     *
     * @param string|null $code
     * @return void
     */
    public static function setLastErrorCode(?string $code): void
    {
        self::$lastErrorCode = $code;
        $GLOBALS["tracyErrorCode"] = $code;
        $GLOBALS["errorCode"] = $code;
    }

    /**
     * Gets the last recorded error code / Tracy hash.
     *
     * @return string|null
     */
    public static function getLastErrorCode(): ?string
    {
        if (self::$lastErrorCode !== null && self::$lastErrorCode !== "") {
            return self::$lastErrorCode;
        }

        if (!empty($GLOBALS["tracyErrorCode"])) {
            return $GLOBALS["tracyErrorCode"];
        }

        if (!empty($GLOBALS["errorCode"])) {
            return $GLOBALS["errorCode"];
        }

        $logDir = null;
        if (class_exists(Debugger::class) && Debugger::$logDirectory && is_dir(Debugger::$logDirectory)) {
            $logDir = Debugger::$logDirectory;
        } elseif (defined("CHANDLER_ROOT") && is_dir(constant("CHANDLER_ROOT") . "/logs")) {
            $logDir = constant("CHANDLER_ROOT") . "/logs";
        }

        if ($logDir !== null) {
            $logFile = $logDir . "/exception.log";
            if (file_exists($logFile) && is_readable($logFile)) {
                $f = @fopen($logFile, "r");
                if ($f) {
                    $size = filesize($logFile);
                    if ($size > 0) {
                        $seek = max(0, $size - 4096);
                        fseek($f, $seek);
                        $chunk = fread($f, 4096);
                        if (is_string($chunk)) {
                            $lines = explode("\n", trim($chunk));
                            $lastLine = end($lines);
                            if ($lastLine && preg_match('/@@\s+([^\s]+\.html)/', $lastLine, $m)) {
                                $html = $m[1];
                                if (preg_match('/--([a-f0-9]+)\.html$/i', $html, $hashMatch)) {
                                    self::setLastErrorCode($hashMatch[1]);
                                    fclose($f);
                                    return $hashMatch[1];
                                }
                                self::setLastErrorCode($html);
                                fclose($f);
                                return $html;
                            }
                        }
                    }
                    fclose($f);
                }
            }
        }

        return null;
    }
}
