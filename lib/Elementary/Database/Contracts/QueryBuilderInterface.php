<?php

declare(strict_types=1);

namespace Elementary\Database\Contracts;

/**
 * Query Builder Interface
 * 
 * Defines the contract for all query builders across different database drivers.
 * Provides a unified API for building and executing database queries regardless
 * of the underlying database system.
 */
interface QueryBuilderInterface
{
    /**
     * Set the table/collection for the query
     */
    public function table(string $table): self;

    /**
     * Set the model class for result hydration
     */
    public function setModel(string $modelClass): self;

    /**
     * Add SELECT columns
     */
    public function select(array $columns = ['*']): self;

    /**
     * Add WHERE clause
     */
    public function where(string $column, string $operator, $value): self;

    /**
     * Add WHERE IN clause
     */
    public function whereIn(string $column, array $values): self;

    /**
     * Add WHERE NOT IN clause
     */
    public function whereNotIn(string $column, array $values): self;

    /**
     * Add WHERE NULL clause
     */
    public function whereNull(string $column): self;

    /**
     * Add WHERE NOT NULL clause
     */
    public function whereNotNull(string $column): self;

    /**
     * Add ORDER BY clause
     */
    public function orderBy(string $column, string $direction = 'ASC'): self;

    /**
     * Add LIMIT clause
     */
    public function limit(int $number): self;

    /**
     * Add OFFSET clause
     */
    public function offset(int $number): self;

    /**
     * Set the relationships to be eager loaded.
     */
    public function with(string|array $relations): self;

    /**
     * Execute query and get all results
     */
    public function get(): array;

    /**
     * Execute query and get first result
     */
    public function first();

    /**
     * Find record by primary key
     */
    public function find($id);

    /**
     * Insert data
     */
    public function insert(array $data): bool;

    /**
     * Update records
     */
    public function update(array $data): int;

    /**
     * Delete records
     */
    public function delete(): int;

    /**
     * Get count of records
     */
    public function count(): int;

    /**
     * Check if any records exist
     */
    public function exists(): bool;

    /**
     * Paginate results
     */
    public function paginate(int $page = 1, int $perPage = 15): array;

    /**
     * Get distinct values
     */
    public function distinct(): self;

    /**
     * Debug method to see generated query and bindings
     */
    public function toDebugSql(): array;
}