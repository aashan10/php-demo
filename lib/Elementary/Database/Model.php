<?php

declare(strict_types=1);

namespace Elementary\Database;

use Elementary\Database\Contracts\QueryBuilderInterface;
use Elementary\Database\Relations\BelongsTo;
use Elementary\Database\Relations\HasMany;
use Elementary\Database\Relations\HasOne;

/**
 * Enhanced Model base class with clean Active Record pattern
 * 
 * Provides elegant Active Record functionality without container dependencies.
 * Maintains full backward compatibility with existing model usage patterns.
 */
abstract class Model
{
    protected static string $connection = 'default';
    protected static string $table = '';
    protected static string $primaryKey = 'id';
    protected static array $fillable = [];
    protected static array $guarded = ['id'];
    
    protected array $attributes = [];
    protected array $original = [];
    protected bool $exists = false;
    protected array $relations = [];

    public function __construct(array $attributes = [])
    {
        $this->fill($attributes);
        $this->syncOriginal();
    }

    // ===========================================
    // Static Query Methods (Public API)
    // ===========================================

    /**
     * Create new query builder for this model
     */
    public static function query(): QueryBuilderInterface
    {
        $instance = new static;
        return DatabaseManager::getInstance()
            ->connection(static::$connection)
            ->getQueryBuilder()
            ->table($instance->getTable())
            ->setModel(static::class);
    }

    /**
     * Find model by primary key
     */
    public static function find($id): ?static
    {
        return static::query()->find($id);
    }

    /**
     * Create new model instance and save to database
     */
    public static function create(array $attributes): static
    {
        $model = new static($attributes);
        $model->save();
        return $model;
    }

    /**
     * Get all models
     */
    public static function all(): array
    {
        return static::query()->get();
    }

    /**
     * Find first model matching conditions
     */
    public static function where(string $column, string $operator, $value): QueryBuilderInterface
    {
        return static::query()->where($column, $operator, $value);
    }

    public static function with(string|array $relations): QueryBuilderInterface
    {
        return static::query()->with($relations);
    }

    /**
     * Get count of records
     */
    public static function count(): int
    {
        $result = static::query()->select(['COUNT(*) as count'])->first();
        return (int) ($result->count ?? 0);
    }

    // ===========================================
    // Instance Methods (Public API)
    // ===========================================

    /**
     * Save the model (insert or update)
     */
    public function save(): bool
    {
        if ($this->exists) {
            return $this->performUpdate();
        } else {
            return $this->performInsert();
        }
    }

    /**
     * Update model with new attributes
     */
    public function update(array $attributes): bool
    {
        $this->fill($attributes);
        return $this->save();
    }

    /**
     * Delete the model
     */
    public function delete(): bool
    {
        if (!$this->exists) {
            return false;
        }

        $result = static::query()
            ->where($this->getKeyName(), '=', $this->getKey())
            ->delete();

        if ($result > 0) {
            $this->exists = false;
            return true;
        }

        return false;
    }

    /**
     * Refresh model from database
     */
    public function refresh(): static
    {
        if (!$this->exists) {
            throw new \RuntimeException('Cannot refresh model that does not exist in database');
        }

        $fresh = static::find($this->getKey());
        if (!$fresh) {
            throw new \RuntimeException('Model no longer exists in database');
        }

        $this->attributes = $fresh->attributes;
        $this->syncOriginal();

        return $this;
    }


    /**
     * Check if model has unsaved changes
     */
    public function isDirty(?string $attribute = null): bool
    {
        if ($attribute) {
            return isset($this->attributes[$attribute]) && 
                   (!isset($this->original[$attribute]) || $this->original[$attribute] !== $this->attributes[$attribute]);
        }

        return !empty($this->getDirtyAttributes());
    }

    /**
     * Get the dirty (changed) attributes
     */
    public function getDirty(): array
    {
        return $this->getDirtyAttributes();
    }

    /**
     * Replicate the model into a new, non-existing instance
     */
    public function replicate(array $except = []): static
    {
        $attributes = $this->attributes;
        
        // Remove primary key and any specified exceptions
        unset($attributes[$this->getKeyName()]);
        foreach ($except as $key) {
            unset($attributes[$key]);
        }
        
        return new static($attributes);
    }

    // ===========================================
    // Relationship Methods
    // ===========================================

    /**
     * Define a one-to-many relationship.
     */
    protected function hasMany(string $related, ?string $foreignKey = null, ?string $localKey = null): HasMany
    {
        $foreignKey = $foreignKey ?: $this->getForeignKey();
        $localKey = $localKey ?: $this->getKeyName();

        return new HasMany(new $related, $this, $foreignKey, $localKey);
    }

    protected function hasOne(string $related, ?string $foreignKey = null, ?string $localKey = null): HasOne
    {
        $foreignKey = $foreignKey ?: $this->getForeignKey();
        $localKey = $localKey ?: $this->getKeyName();

        return new HasOne(new $related, $this, $foreignKey, $localKey);
    }

    protected function belongsTo(string $related, ?string $foreignKey = null, ?string $ownerKey = null): BelongsTo
    {
        $foreignKey = $foreignKey ?: $this->getForeignKey();
        $ownerKey = $ownerKey ?: (new $related)->getKeyName();

        return new BelongsTo(new $related, $this, $foreignKey, $ownerKey);
    }

    // ===========================================
    // Configuration & Metadata Methods
    // ===========================================

    /**
     * Get table name
     */
    public function getTable(): string
    {
        return static::$table ?: $this->getDefaultTableName();
    }

    /**
     * Get primary key name
     */
    public function getKeyName(): string
    {
        return static::$primaryKey;
    }

    /**
     * Get primary key value
     */
    public function getKey()
    {
        return $this->getAttribute($this->getKeyName());
    }

    public function getForeignKey(): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', class_basename($this))) . '_id';
    }

    /**
     * Get all model attributes
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * Get original attributes (before changes)
     */
    public function getOriginal(): array
    {
        return $this->original;
    }

    /**
     * Check if model exists in database
     */
    public function exists(): bool
    {
        return $this->exists;
    }

    /**
     * Set the exists flag (used by query builders when hydrating models)
     */
    public function setExists(bool $exists): void
    {
        $this->exists = $exists;
    }

    // ===========================================
    // Attribute Management
    // ===========================================

    /**
     * Fill model with attributes (respects fillable/guarded rules)
     */
    public function fill(array $attributes): static
    {
        foreach ($attributes as $key => $value) {
            if ($this->isFillable($key)) {
                $this->setAttribute($key, $value);
            }
        }
        return $this;
    }

    /**
     * Hydrate model with attributes from database (bypasses fillable/guarded rules)
     * Used by query builders when loading models from database
     */
    public function hydrate(array $attributes): static
    {
        foreach ($attributes as $key => $value) {
            $this->setAttribute($key, $value);
        }
        return $this;
    }

    /**
     * Set attribute value
     */
    public function setAttribute(string $key, $value): void
    {
        $this->attributes[$key] = $value;
    }

    public function setRelation(string $relation, mixed $value): void
    {
        $this->relations[$relation] = $value;
    }

    /**
     * Get attribute value
     */
    public function getAttribute(string $key)
    {
        return $this->attributes[$key] ?? null;
    }

    /**
     * Check if attribute exists
     */
    public function hasAttribute(string $key): bool
    {
        return array_key_exists($key, $this->attributes);
    }

    /**
     * Remove an attribute
     */
    public function unsetAttribute(string $key): void
    {
        unset($this->attributes[$key]);
    }

    // ===========================================
    // Array/JSON Conversion
    // ===========================================

    /**
     * Convert model to array
     */
    public function toArray(): array
    {
        return array_merge($this->attributes, $this->relationsToArray());
    }

    protected function relationsToArray(): array
    {
        $relations = [];
        foreach ($this->relations as $key => $relation) {
            if (is_array($relation)) {
                $relations[$key] = array_map(fn($item) => $item instanceof Model ? $item->toArray() : $item, $relation);
            } else {
                $relations[$key] = $relation instanceof Model ? $relation->toArray() : $relation;
            }
        }
        return $relations;
    }

    /**
     * Convert model to JSON
     */
    public function toJson(int $options = 0): string
    {
        return json_encode($this->toArray(), $options);
    }

    /**
     * Convert model to JSON when used as string
     */
    public function __toString(): string
    {
        return $this->toJson();
    }

    // ===========================================
    // Magic Methods
    // ===========================================

    /**
     * Magic getter for attributes
     */
    public function __get(string $key)
    {
        // 1. Check for existing attribute
        if (array_key_exists($key, $this->attributes)) {
            return $this->getAttribute($key);
        }

        // 2. Check for already loaded relation
        if (array_key_exists($key, $this->relations)) {
            return $this->relations[$key];
        }

        // 3. Check if a relationship method exists
        if (method_exists($this, $key)) {
            $relation = $this->{$key}();

            if ($relation instanceof \Elementary\Database\Relations\Relation) {
                $results = $relation->get(); // Get results from the relation object

                // Cache the loaded relationship to prevent re-querying
                $this->setRelation($key, $results);

                return $results;
            }
        }

        return null;
    }

    /**
     * Magic setter for attributes
     */
    public function __set(string $key, $value): void
    {
        $this->setAttribute($key, $value);
    }

    /**
     * Magic isset for attributes
     */
    public function __isset(string $key): bool
    {
        return $this->hasAttribute($key);
    }

    /**
     * Magic unset for attributes
     */
    public function __unset(string $key): void
    {
        $this->unsetAttribute($key);
    }

    // ===========================================
    // Internal Helper Methods
    // ===========================================

    /**
     * Check if attribute is fillable
     */
    protected function isFillable(string $key): bool
    {
        if (in_array($key, static::$guarded)) {
            return false;
        }

        if (empty(static::$fillable)) {
            return true;
        }

        return in_array($key, static::$fillable);
    }

    /**
     * Perform database insert
     */
    protected function performInsert(): bool
    {
        $attributes = $this->getAttributesForInsert();
        
        $result = DatabaseManager::getInstance()
            ->newQuery()
            ->table($this->getTable())
            ->insert($attributes);

        if ($result) {
            // Get the inserted ID if using auto-increment
            $pooledConn = DatabaseManager::getInstance()->getConnection()->getPooledConnection();
            try {
                $lastInsertId = $pooledConn->lastInsertId();
                
                if ($lastInsertId && !$this->getKey()) {
                    $this->setAttribute($this->getKeyName(), $lastInsertId);
                }
            } finally {
                $pooledConn->release();
            }

            $this->exists = true;
            $this->syncOriginal();
            return true;
        }

        return false;
    }

    /**
     * Perform database update
     */
    protected function performUpdate(): bool
    {
        $dirty = $this->getDirtyAttributes();
        
        if (empty($dirty)) {
            return true; // No changes to save
        }

        $result = static::query()
            ->where($this->getKeyName(), '=', $this->getKey())
            ->update($dirty);

        if ($result > 0) {
            $this->syncOriginal();
            return true;
        }

        return false;
    }

    /**
     * Get attributes for insert (removes null primary key)
     */
    protected function getAttributesForInsert(): array
    {
        $attributes = $this->attributes;
        
        // Remove primary key if it's null (for auto-increment)
        if (is_null($this->getKey())) {
            unset($attributes[$this->getKeyName()]);
        }

        return $attributes;
    }

    /**
     * Get dirty (changed) attributes
     */
    protected function getDirtyAttributes(): array
    {
        $dirty = [];
        
        foreach ($this->attributes as $key => $value) {
            if (!isset($this->original[$key]) || $this->original[$key] !== $value) {
                $dirty[$key] = $value;
            }
        }

        return $dirty;
    }

    /**
     * Sync original attributes with current
     */
    protected function syncOriginal(): void
    {
        $this->original = $this->attributes;
    }

    /**
     * Get default table name from class name
     */
    protected function getDefaultTableName(): string
    {
        $className = class_basename(static::class);
        
        // Simple pluralization (you could make this more sophisticated)
        if (str_ends_with($className, 'y')) {
            return strtolower(substr($className, 0, -1)) . 'ies';
        } elseif (str_ends_with($className, 's')) {
            return strtolower($className) . 'es';
        } else {
            return strtolower($className) . 's';
        }
    }
}

/**
 * Helper function to get class basename
 */
if (!function_exists('class_basename')) {
    function class_basename(string $class): string
    {
        $class = is_object($class) ? get_class($class) : $class;
        return basename(str_replace('\\', '/', $class));
    }
}