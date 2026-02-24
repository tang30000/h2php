<?php
namespace Lib;

/**
 * DB — PDO 数据库封装
 * 提供简洁的链式查询接口和直接 SQL 执行
 */
class DB
{
    private \PDO $pdo;
    private string $table  = '';
    private string $where  = '';
    private array  $params = [];
    private string $order  = '';
    private string $limit  = '';
    private string $fields = '*';

    /** @var int 缓存时间（秒），0 表示不缓存 */
    private int $cacheTime = 0;

    /** @var array|null 缓存驱动配置 */
    private ?array $cacheConfig = null;

    public function __construct(array $config)
    {
        $this->pdo = new \PDO(
            $config['dsn'],
            $config['user'],
            $config['password'],
            $config['options'] ?? []
        );
        $this->cacheConfig = $config['cache'] ?? null;
    }

    // -------------------------------------------------------------------------
    // 链式查询接口
    // -------------------------------------------------------------------------

    /**
     * 指定表名，返回新的 DB 实例（支持链式且不污染当前状态）
     */
    public function table(string $table): self
    {
        $clone = clone $this;
        $clone->table     = $table;
        $clone->where     = '';
        $clone->params    = [];
        $clone->order     = '';
        $clone->limit     = '';
        $clone->fields    = '*';
        $clone->cacheTime = 0;
        return $clone;
    }

    /**
     * 启用查询缓存
     *
     * @param int $ttl 缓存秒数，0 等同于不缓存
     * 用法：->cache(300)->fetchAll()  → 结果缓存 300 秒
     */
    public function cache(int $ttl = 3600): self
    {
        $this->cacheTime = $ttl;
        return $this;
    }

    /**
     * 指定查询字段
     * 用法：->fields('id, name, email')
     */
    public function fields(string $fields): self
    {
        $this->fields = $fields;
        return $this;
    }

    /**
     * WHERE 条件（支持占位符）
     * 用法：->where('id = ? AND status = ?', [1, 1])
     */
    public function where(string $condition, array $params = []): self
    {
        $this->where  = $condition;
        $this->params = $params;
        return $this;
    }

    /**
     * ORDER BY
     * 用法：->order('created_at DESC')
     */
    public function order(string $order): self
    {
        $this->order = $order;
        return $this;
    }

    /**
     * LIMIT / OFFSET
     * 用法：->limit(10) 或 ->limit(10, 20)（取10条，从第20条开始）
     */
    public function limit(int $limit, int $offset = 0): self
    {
        $this->limit = $offset > 0 ? "$limit OFFSET $offset" : "$limit";
        return $this;
    }

    /**
     * 获取多条记录
     */
    public function fetchAll(): array
    {
        $sql = $this->buildSelect();

        if ($this->cacheTime > 0 && $this->cacheConfig) {
            $key   = md5($sql . serialize($this->params));
            $cache = Cache::instance($this->cacheConfig);
            $hit   = $cache->get($key);
            if ($hit !== null) return $hit;
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($this->params);
            $data = $stmt->fetchAll();
            $cache->set($key, $data, $this->cacheTime);
            return $data;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($this->params);
        return $stmt->fetchAll();
    }

    /**
     * 获取单条记录
     */
    public function fetch()
    {
        $this->limit = '1';
        $sql = $this->buildSelect();

        if ($this->cacheTime > 0 && $this->cacheConfig) {
            $key   = md5($sql . serialize($this->params));
            $cache = Cache::instance($this->cacheConfig);
            $hit   = $cache->get($key);
            if ($hit !== null) return $hit;
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($this->params);
            $data = $stmt->fetch();
            if ($data !== false) {
                $cache->set($key, $data, $this->cacheTime);
            }
            return $data;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($this->params);
        return $stmt->fetch();
    }

    /**
     * 获取单个字段值
     * 用法：->table('config')->where('key=?', ['site_name'])->value()
     */
    public function value()
    {
        $row = $this->fetch();
        return $row ? reset($row) : null;
    }

    /**
     * 统计行数
     */
    public function count(): int
    {
        $sql  = "SELECT COUNT(*) FROM `{$this->table}`"
              . ($this->where ? " WHERE {$this->where}" : '');
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($this->params);
        return (int)$stmt->fetchColumn();
    }

    /**
     * 插入一条记录，返回自增 ID
     */
    public function insert(array $data)
    {
        $cols   = implode('`, `', array_keys($data));
        $marks  = implode(', ', array_fill(0, count($data), '?'));
        $sql    = "INSERT INTO `{$this->table}` (`{$cols}`) VALUES ({$marks})";
        $stmt   = $this->pdo->prepare($sql);
        $stmt->execute(array_values($data));
        return $this->pdo->lastInsertId();
    }

    /**
     * 更新记录，返回受影响行数
     */
    public function update(array $data): int
    {
        $sets   = implode(', ', array_map(fn($k) => "`{$k}` = ?", array_keys($data)));
        $vals   = array_merge(array_values($data), $this->params);
        $sql    = "UPDATE `{$this->table}` SET {$sets}"
                . ($this->where ? " WHERE {$this->where}" : '');
        $stmt   = $this->pdo->prepare($sql);
        $stmt->execute($vals);
        return $stmt->rowCount();
    }

    /**
     * 删除记录，返回受影响行数
     */
    public function delete(): int
    {
        $sql  = "DELETE FROM `{$this->table}`"
              . ($this->where ? " WHERE {$this->where}" : '');
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($this->params);
        return $stmt->rowCount();
    }

    // -------------------------------------------------------------------------
    // 直接 SQL 执行
    // -------------------------------------------------------------------------

    /**
     * 执行原生查询，返回结果集
     */
    public function query(string $sql, array $params = []): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * 执行原生语句（INSERT/UPDATE/DELETE），返回受影响行数
     */
    public function exec(string $sql, array $params = []): int
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    /**
     * 获取原始 PDO 对象（用于事务等高级操作）
     */
    public function pdo(): \PDO
    {
        return $this->pdo;
    }

    // -------------------------------------------------------------------------
    // 内部辅助
    // -------------------------------------------------------------------------

    private function buildSelect(): string
    {
        $sql = "SELECT {$this->fields} FROM `{$this->table}`";
        if ($this->where) $sql .= " WHERE {$this->where}";
        if ($this->order) $sql .= " ORDER BY {$this->order}";
        if ($this->limit) $sql .= " LIMIT {$this->limit}";
        return $sql;
    }
}
