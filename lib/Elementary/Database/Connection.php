<?php

declare(strict_types=1);

namespace Elementary\Database;

use Elementary\Config\ConfigBag;
use PDO;
use PDOException;

/**
 * Manages the database connection.
 */
final class Connection
{
    private static ?PDO $instance = null;
    private ConfigBag $config;

    public function __construct(ConfigBag $config)
    {
        $this->config = $config;
    }

    /**
     * Gets the single instance of the PDO connection.
     *
     * @return PDO The PDO instance.
     */
    public function getInstance(): PDO
    {
        if (self::$instance === null) {
            $host = $this->config->get('database.host');
            $db   = $this->config->get('database.database');
            $user = $this->config->get('database.username');
            $pass = $this->config->get('database.password');
            $charset = $this->config->get('database.charset', 'utf8mb4');

            $dsn = "mysql:host={$host};dbname={$db};charset={$charset}";
            
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            try {
                self::$instance = new PDO($dsn, $user, $pass, $options);
            } catch (PDOException $e) {
                throw new PDOException($e->getMessage(), (int)$e->getCode());
            }
        }

        return self::$instance;
    }
}