<?php

declare(strict_types=1);

namespace Elementary\Database;

use PDO;

class QueryBuilder
{
    protected PDO $pdo;
    protected string $table;
    protected ?string $modelClass = null;
    protected string $primaryKey = 'id';

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

    public function setPrimaryKey(string $key): self
    {
        $this->primaryKey = $key;
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

    public function limit(?int $number): self
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
        return $this->where($this->primaryKey, '=', $id)->first();
    }

    public function insert(array $data): bool
    {
        $columns = array_keys($data);
        $placeholders = array_fill(0, count($columns), '?');

        $sql = sprintf(
            'INSERT INTO `%s` (`%s`) VALUES (%s)',
            $this->table,
            implode('`, `', $columns),
            implode(', ', $placeholders)
        );

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute(array_values($data));
    }

    public function update(array $data): int
    {
        if (empty($this->wheres)) {
            throw new \RuntimeException('Attempting to update without a WHERE clause.');
        }

        $columns = array_keys($data);
        $setPlaceholders = array_map(fn($col) => "`{$col}` = ?", $columns);

        $sql = sprintf(
            'UPDATE `%s` SET %s WHERE %s',
            $this->table,
            implode(', ', $setPlaceholders),
            implode(' AND ', $this->wheres)
        );

        $bindings = array_merge(array_values($data), $this->bindings);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($bindings);

        return $stmt->rowCount();
    }

    public function delete(): int
    {
        if (empty($this->wheres)) {
            throw new \RuntimeException('Attempting to delete without a WHERE clause.');
        }

        $sql = sprintf(
            'DELETE FROM `%s` WHERE %s',
            $this->table,
            implode(' AND ', $this->wheres)
        );

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($this->bindings);

        return $stmt->rowCount();
    }

    public function toSql(): string
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
