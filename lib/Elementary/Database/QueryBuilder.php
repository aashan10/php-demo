<?php

declare(strict_types=1);

namespace Elementary\Database;

use PDO;

class QueryBuilder
{
    protected PDO $pdo;
    protected string $table;
    protected ?string $modelClass = null;

    protected array $columns = ['*'];
    protected array $wheres = [];
    protected array $bindings = [];
    protected ?string $orderBy = null;
    protected ?int $limit = null;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function table(string $table): self
    {
        $this->table = $table;
        return $this;
    }

    public function setModel(string $modelClass): self
    {
        $this->modelClass = $modelClass;
        return $this;
    }

    public function where(string $column, string $operator, $value): self
    {
        $this->wheres[] = "`{$column}` {$operator} ?";
        $this->bindings[] = $value;
        return $this;
    }

    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $this->orderBy = "ORDER BY `{$column}` {$direction}";
        return $this;
    }

    public function limit(int $number): self
    {
        $this->limit = $number;
        return $this;
    }

    public function get(): array
    {
        $stmt = $this->execute($this->toSql());

        if ($this->modelClass) {
            return $stmt->fetchAll(PDO::FETCH_CLASS, $this->modelClass);
        }

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function first(): ?object
    {
        $this->limit(1);
        $results = $this->get();
        return $results[0] ?? null;
    }

    public function find(int $id): ?object
    {
        return $this->where('id', '=', $id)->first();
    }

    private function toSql(): string
    {
        $sql = "SELECT " . implode(', ', $this->columns) . " FROM `{$this->table}`";

        if (!empty($this->wheres)) {
            $sql .= " WHERE " . implode(' AND ', $this->wheres);
        }

        if ($this->orderBy) {
            $sql .= " " . $this->orderBy;
        }

        if ($this->limit) {
            $sql .= " LIMIT " . $this->limit;
        }

        return $sql;
    }

    private function execute(string $sql): \PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($this->bindings);
        return $stmt;
    }
}
