<?php

namespace Vine\Database;

abstract class Model
{
    protected static string $table = '';
    protected static string $alias = '';
    protected static string $primaryKey = 'id';
    protected static array $schema = [];
    protected static array $fillable = [];
    protected static array $hidden = [];

    protected static function db(): Connection
    {
        return Connection::getInstance();
    }

    public static function query(): QueryBuilder
    {
        return new QueryBuilder(static::$table, static::db(), static::$alias, static::$primaryKey);
    }

    public static function find(int $id): ?array
    {
        return static::query()->where(static::$primaryKey, '=', $id)->first();
    }

    public static function findOrFail(int $id): array
    {
        $record = static::find($id);
        if (!$record) {
            throw new \RuntimeException('Record not found');
        }
        return $record;
    }

    public static function all(): array
    {
        return static::query()->get();
    }

    public static function create(array $data): array
    {
        $allowed = array_filter($data, fn($k) => in_array($k, static::$fillable), ARRAY_FILTER_USE_KEY);
        $id = static::query()->insert($allowed);
        return static::find((int) $id);
    }

    public static function update(int $id, array $data): bool
    {
        $allowed = array_filter($data, fn($k) => in_array($k, static::$fillable), ARRAY_FILTER_USE_KEY);
        return static::query()->where(static::$primaryKey, '=', $id)->update($allowed);
    }

    public static function delete(int $id): bool
    {
        return static::query()->where(static::$primaryKey, '=', $id)->delete();
    }

    public static function hide(array $record): array
    {
        foreach (static::$hidden as $field) {
            unset($record[$field]);
        }
        return $record;
    }

    public static function migrate(): void
    {
        if (empty(static::$schema)) {
            return;
        }

        $table = static::$table;
        $db = static::db();

        $checkSql = "SELECT to_regclass('public.$table') as exists";
        $row = $db->selectOne($checkSql);

        if ($row['exists'] !== null) {
            return;
        }

        $columns = ['id SERIAL PRIMARY KEY'];
        foreach (static::$schema as $column => $definition) {
            $columns[] = "$column $definition";
        }
        $columns[] = 'created_at TIMESTAMPTZ DEFAULT NOW()';

        $sql = "CREATE TABLE $table (\n    " . implode(",\n    ", $columns) . "\n)";
        $db->statement($sql);

        echo "  [migrate] Created table: $table\n";
    }

    public static function getTable(): string
    {
        return static::$table;
    }
}
