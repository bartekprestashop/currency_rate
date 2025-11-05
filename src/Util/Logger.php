<?php

namespace CurrencyRate\Util;

if (!defined('_PS_VERSION_')) {
    exit;
}

class Logger
{
    /**
     * Add a log entry if logging is enabled.
     *
     * @param string $message
     * @param int $level PrestaShop log level (1=info, 2=warning, 3=error)
     */
    public static function add(string $message, int $level = 1): void
    {
        // Respect module configuration toggle
        if (!class_exists('Configuration') || (int) \Configuration::get('CURRENCY_RATE_ADD_LOGS') !== 1) {
            return;
        }

        $msg = '[CURRENCY_RATE] ' . $message;

        if (class_exists('PrestaShopLogger')) {
            \PrestaShopLogger::addLog($msg, $level);
            return;
        }

        // Fallback to PHP error_log if PS logger is unavailable
        @error_log($msg);
    }

    public static function info(string $message): void
    {
        self::add($message, 1);
    }

    public static function warn(string $message): void
    {
        self::add($message, 2);
    }

    public static function error(string $message): void
    {
        self::add($message, 3);
    }
}
