<?php

declare(strict_types=1);

namespace Elementary\Session\Drivers;

use Elementary\Database\QueryBuilder;
use Elementary\Database\Connection;
use stdClass;

class DatabaseSessionDriver implements SessionDriverInterface
{
    private QueryBuilder $query;
    private string $table;

    public function __construct(Connection $connection, string $table)
    {
        $this->query = new QueryBuilder($connection->getInstance());
        $this->table = $table;
    }

    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string|false
    {
        $session = $this->query->setModel(stdClass::class)->table($this->table)->where('id', '=', $id)->first();

        if ($session && isset($session->payload)) {
            return base64_decode($session->payload);
        }

        return '';
    }

    public function write(string $id, string $data): bool
    {
        $payload = base64_encode($data);
        $last_activity = time();

        $this->query->table($this->table)->where('id', '=', $id)->delete();
        $this->query->table($this->table)->insert([
            'id' => $id,
            'payload' => $payload,
            'last_activity' => $last_activity,
        ]);

        return true;
    }

    public function destroy(string $id): bool
    {
        $this->query->table($this->table)->where('id', '=', $id)->delete();
        return true;
    }

    public function gc(int $max_lifetime): int|false
    {
        return $this->query->table($this->table)
            ->where('last_activity', '<=', time() - $max_lifetime)
            ->delete();
    }
}
