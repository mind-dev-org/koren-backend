<?php

namespace Vine\Database;

class QueryBuilder
{
    private string $table;
    private string $tableAlias = '';
    private string $primaryKey = 'id';
    private array $selects = ['*'];
    private array $wheres = [];
    private array $bindings = [];
    private array $joins = [];
    private ?string $orderByCol = null;
    private string $orderDir = 'ASC';
    private ?int $limitVal = null;
    private ?int $offsetVal = null;
    private Connection $db;

    public function __construct(string $table, Connection $db, string $alias = '', string $primaryKey = 'id')
    {
        $this->table      = $table;
        $this->tableAlias = $alias;
        $this->primaryKey = $primaryKey;
        $this->db         = $db;
    }

    public function select(array $columns): static
    {
        $this->selects = $columns;
        return $this;
    }

    public function join(string $table, string $on): static
    {
        $this->joins[] = "JOIN $table ON $on";
        return $this;
    }

    public function leftJoin(string $table, string $on): static
    {
        $this->joins[] = "LEFT JOIN $table ON $on";
        return $this;
    }

    public function where(string $column, string $operator, mixed $value): static
    {
        $placeholder = ':w' . count($this->bindings);
        $this->wheres[] = "$column $operator $placeholder";
        $this->bindings[$placeholder] = $value;
        return $this;
    }

    public function whereNull(string $column): static
    {
        $this->wheres[] = "$column IS NULL";
        return $this;
    }

    public function whereNotNull(string $column): static
    {
        $this->wheres[] = "$column IS NOT NULL";
        return $this;
    }

    public function whereIn(string $column, array $values): static
    {
        $placeholders = [];
        foreach ($values as $i => $val) {
            $key = ':win' . count($this->bindings) . $i;
            $placeholders[] = $key;
            $this->bindings[$key] = $val;
        }
        $this->wheres[] = "$column IN (" . implode(', ', $placeholders) . ")";
        return $this;
    }

    public function when(mixed $condition, callable $callback): static
    {
        if ($condition) {
            $callback($this);
        }
        return $this;
    }

    public function orderBy(string $column, string $direction = 'ASC'): static
    {
        $this->orderByCol = $column;
        $this->orderDir = strtoupper($direction);
        return $this;
    }

    public function limit(int $limit): static
    {
        $this->limitVal = $limit;
        return $this;
    }

    public function offset(int $offset): static
    {
        $this->offsetVal = $offset;
        return $this;
    }

    public function get(): array
    {
        return $this->db->select($this->buildSql(), $this->bindings);
    }

    public function first(): array|null
    {
        $this->limit(1);
        $result = $this->db->selectOne($this->buildSql(), $this->bindings);
        return $result ?: null;
    }

    public function count(): int
    {
        $savedSelects  = $this->selects;
        $savedOrder    = $this->orderByCol;
        $savedLimit    = $this->limitVal;
        $savedOffset   = $this->offsetVal;

        $this->selects    = ['COUNT(*) as aggregate'];
        $this->orderByCol = null;
        $this->limitVal   = null;
        $this->offsetVal  = null;

        $sql = $this->buildSql();

        $this->selects    = $savedSelects;
        $this->orderByCol = $savedOrder;
        $this->limitVal   = $savedLimit;
        $this->offsetVal  = $savedOffset;

        $row = $this->db->selectOne($sql, $this->bindings);
        return (int) ($row['aggregate'] ?? 0);
    }

    public function paginate(int $page, int $perPage): array
    {
        $total = $this->count();
        $this->limit($perPage)->offset(($page - 1) * $perPage);
        $data = $this->get();

        return [
            'data' => $data,
            'meta' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => (int) ceil($total / $perPage),
            ],
        ];
    }

    private function buildSql(): string
    {
        $sql = 'SELECT ' . implode(', ', $this->selects);
        $from = $this->tableAlias ? "{$this->table} {$this->tableAlias}" : $this->table;
        $sql .= ' FROM ' . $from;

        if (!empty($this->joins)) {
            $sql .= ' ' . implode(' ', $this->joins);
        }

        if (!empty($this->wheres)) {
            $sql .= ' WHERE ' . implode(' AND ', $this->wheres);
        }

        if ($this->orderByCol) {
            $sql .= " ORDER BY {$this->orderByCol} {$this->orderDir}";
        }

        if ($this->limitVal !== null) {
            $sql .= " LIMIT {$this->limitVal}";
        }

        if ($this->offsetVal !== null) {
            $sql .= " OFFSET {$this->offsetVal}";
        }

        return $sql;
    }

    public function insert(array $data): string
    {
        $columns = implode(', ', array_keys($data));
        $placeholders = [];
        $bindings = [];
        foreach ($data as $col => $val) {
            $key = ':' . $col;
            $placeholders[] = $key;
            $bindings[$key] = $val;
        }
        $sql = "INSERT INTO {$this->table} ($columns) VALUES (" . implode(', ', $placeholders) . ") RETURNING {$this->primaryKey}";
        return $this->db->insert($sql, $bindings);
    }

    public function update(array $data): bool
    {
        $sets = [];
        $bindings = [];
        foreach ($data as $col => $val) {
            $key = ':set_' . $col;
            $sets[] = "$col = $key";
            $bindings[$key] = $val;
        }
        $sql = "UPDATE {$this->table} SET " . implode(', ', $sets);
        if (!empty($this->wheres)) {
            $sql .= ' WHERE ' . implode(' AND ', $this->wheres);
        }
        return $this->db->statement($sql, array_merge($bindings, $this->bindings));
    }

    public function delete(): bool
    {
        $sql = "DELETE FROM {$this->table}";
        if (!empty($this->wheres)) {
            $sql .= ' WHERE ' . implode(' AND ', $this->wheres);
        }
        return $this->db->statement($sql, $this->bindings);
    }
}
