<?php


namespace Micropoly;


use Monolog\Handler\ErrorLogHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

class Log
{
    public static function logger(): LoggerInterface
    {
        static $logger = null;
        if ($logger === null)
            $logger = self::initLogger();
        return $logger;
    }

    private static function initLogger(): Logger
    {
        $logger = new Logger("logger");
        $logger->pushHandler(new ErrorLogHandler());
        return $logger;
    }
}