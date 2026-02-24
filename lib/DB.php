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

    /** @var bool 是否强制刷新缓存 */
    private bool $cacheForce = false;

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
        $clone->cacheTime  = 0;
        $clone->cacheForce = false;
        return $clone;
    }

    /**
     * 启用查询缓存
     *
     * @param int  $ttl   缓存秒数，0 等同于不缓存
     * @param bool $force true = 强制刷新：忽略旧缓存，重新查库并覆盖
     *
     * 用法：->cache(300)        // 有缓存就用，没有则查库后缓存
     *       ->cache(300, true)  // 强制刷新，常用于写操作后主动更新热点数据
     */
    public function cache(int $ttl = 3600, bool $force = false): self
    {
        $this->cacheTime  = $ttl;
        $this->cacheForce = $force;
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
            // force=false 时先读缓存，命中直接返回
            if (!$this->cacheForce) {
                $hit = $cache->get($key);
                if ($hit !== null) return $hit;
            }
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($this->params);
            $data = $stmt->fetchAll();
            $cache->set($key, $data, $this->cacheTime);  // 写入（覆盖）缓存
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
            if (!$this->cacheForce) {
                $hit = $cache->get($key);
                if ($hit !== null) return $hit;
            }
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
    // 关联关系辅助（hasMany / belongsTo）
    // -------------------------------------------------------------------------

    /**
     * 一对多：获取子表记录（返回可链式的 DB 实例）
     *
     * @param string $relTable  关联表名
     * @param string $fk        外键字段名（在关联表中）
     * @param mixed  $pkValue   当前记录的主键值
     *
     * 用法：
     *   // 获取 user_id=5 的所有文章，可继续链式过滤
     *   $posts = $this->db->hasMany('posts', 'user_id', 5)
     *       ->order('id DESC')->limit(10)->fetchAll();
     */
    public function hasMany(string $relTable, string $fk, $pkValue): self
    {
        return $this->table($relTable)->where("{$fk}=?", [$pkValue]);
    }

    /**
     * 多对一：获取父表记录（返回可链式的 DB 实例，通常 ->fetch()）
     *
     * @param string $relTable  父表名
     * @param string $pk        父表主键字段名
     * @param mixed  $fkValue   当前记录的外键值
     *
     * 用法：
     *   // 获取 post['user_id'] 对应的用户
     *   $user = $this->db->belongsTo('users', 'id', $post['user_id'])->fetch();
     */
    public function belongsTo(string $relTable, string $pk, $fkValue): self
    {
        return $this->table($relTable)->where("{$pk}=?", [$fkValue]);
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
