
<?php
/**
 * @author Amin Mahmoudi (MasterkinG)
 * @copyright    Copyright (c) 2019 - 2024, MasterkinG32. (https://masterking32.com)
 * @link    https://masterking32.com
 **/

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\DriverManager;
use PDO; // for PDO::MYSQL_ATTR_* constants

class database
{
    public static $auth;
    public static $chars;

    /**
     * Build PDO MySQL TLS driver options in a safe, opt-in way.
     * - Returns an empty array if TLS is disabled or CA path missing.
     * - Adds client cert/key only if defined (mutual TLS).
     * - Adds optional cipher/capath if present.
     */
    private static function buildTlsDriverOptions(): array
    {
        $useTls = (bool) (get_config('db_auth_use_tls') ?? false);
        $ca     = get_config('db_auth_ssl_ca') ?? null;

        if (!$useTls || empty($ca)) {
            // TLS not requested, or CA not provided → no TLS options.
            return [];
        }

        $cert   = get_config('db_auth_ssl_cert')   ?? null;
        $key    = get_config('db_auth_ssl_key')    ?? null;
        $cipher = get_config('db_auth_ssl_cipher') ?? null;
        $capath = get_config('db_auth_ssl_capath') ?? null;

        // Build the options array only with values that are set (array_filter removes nulls).
        return array_filter([
            PDO::MYSQL_ATTR_SSL_CA     => $ca,
            // Mutual TLS (optional):
            PDO::MYSQL_ATTR_SSL_CERT   => $cert,
            PDO::MYSQL_ATTR_SSL_KEY    => $key,
            // Optional hardening:
            PDO::MYSQL_ATTR_SSL_CIPHER => $cipher,
            PDO::MYSQL_ATTR_SSL_CAPATH => $capath,
        ]);
    }

    public static function db_connect()
    {
        // --- Auth/Realmd connection ---
        self::$auth = DriverManager::getConnection([
            'dbname'   => get_config('db_auth_dbname'),
            'user'     => get_config('db_auth_user'),
            'password' => get_config('db_auth_pass'),
            'host'     => get_config('db_auth_host'),
            'port'     => get_config('db_auth_port'),
            'driver'   => 'pdo_mysql',
            'charset'  => 'utf8',
            // TLS options (may be empty if feature is disabled or CA missing)
            'driverOptions' => self::buildTlsDriverOptions(),
        ], new Configuration());

        // --- Per-realm character/world connections ---
        $realmlists = get_config("realmlists");
        if (is_iterable($realmlists) || is_object($realmlists)) {
            foreach ($realmlists as $realm) {
                if (
                    !empty($realm["realmid"]) &&
                    !empty($realm["db_host"]) &&
                    !empty($realm["db_port"]) &&
                    !empty($realm["db_user"]) &&
                    !empty($realm["db_pass"]) &&
                    !empty($realm["db_name"])
                ) {
                    self::$chars[$realm["realmid"]] = DriverManager::getConnection([
                        'dbname'   => $realm["db_name"],
                        'user'     => $realm["db_user"],
                        'password' => $realm["db_pass"],
                        'host'     => $realm["db_host"],
                        'port'     => $realm["db_port"],
                        'driver'   => 'pdo_mysql',
                        'charset'  => 'utf8',
                        // Reuse same TLS config; if you prefer realm-specific TLS, you can
                        // add fields like $realm["ssl_ca"] and build a realm-aware helper.
                        'driverOptions' => self::buildTlsDriverOptions(),
                    ], new Configuration());
                } else {
                    die("Missing char database required field.");
                }
            }
               } else {
            die("Invalid 'realmlists' configuration.");
        }
    }
}