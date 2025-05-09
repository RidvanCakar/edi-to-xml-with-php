<?php

namespace Ridvan\EdiToXml;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class LoggerFactory
{
    public static function create(string $name, string $logFile): Logger
    {
        $logger = new Logger($name);
        $logger->pushHandler(new StreamHandler('php://stdout', Logger::DEBUG));
        $logger->pushHandler(new StreamHandler($logFile, Logger::DEBUG));
        return $logger;
    }
}
