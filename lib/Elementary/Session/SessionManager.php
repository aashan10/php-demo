<?php

declare(strict_types=1);

namespace Elementary\Session;

use Elementary\Config\ConfigBag;
use Elementary\Database\DatabaseManager;
use Elementary\Session\Drivers\DatabaseSessionDriver;
use Elementary\Session\Drivers\FileSessionDriver;
use Elementary\Session\Drivers\SessionDriverInterface;

class SessionManager
{
    private ConfigBag $config;
    private DatabaseManager $db;
    private ?SessionDriverInterface $driver = null;

    public function __construct(ConfigBag $config, DatabaseManager $db)
    {
        $this->config = $config;
        $this->db = $db;
    }

    public function getDriver(): SessionDriverInterface
    {
        if ($this->driver) {
            return $this->driver;
        }

        $driverName = $this->config->get('session.driver', 'file');

        $this->driver = match ($driverName) {
            'file' => $this->createFileDriver(),
            'database' => $this->createDatabaseDriver(),
            default => throw new \InvalidArgumentException("Unsupported session driver [{$driverName}]"),
        };
        
        return $this->driver;
    }

    private function createFileDriver(): SessionDriverInterface
    {
        $path = $this->config->get('session.files', BASE_PATH . '/cache/sessions');
        return new FileSessionDriver($path);
    }

    private function createDatabaseDriver(): SessionDriverInterface
    {
        $table = $this->config->get('session.table', 'sessions');
        return new DatabaseSessionDriver($this->db, $table);
    }
}
