<?php
namespace Lib;

/**
 * DB — PDO 数据库封装
 * 提供简洁的链式查询接口和直接 SQL 执行
 *
 * 支持数据库：MySQL / MariaDB / PostgreSQL / SQLite
 */
class DB
{
    private \PDO $pdo;
    /** @var string 数据库驱动名 mysql|pgsql|sqlite */
    private string $driver = 'mysql';
    private string $table  = '';
    private string $where  = '';
    private array  $params = [];
    private string $order  = '';
    private string $limit  = '';
    private string $fields = '*';

    /** @var int 默认最大查询行数（防止意外全表扫描） */
    public const MAX_ROWS = 10000;

    /** @var int 慢查询阈值（毫秒），超过则自动记录日志 */
    public const SLOW_THRESHOLD = 100;

    /** @var array DB 单例缓存 */
    private static array $instances = [];

    /** @var int 超过此数量且 fields=* 时，自动排除 TEXT/BLOB 等大字段 */
    public const HEAVY_LIMIT = 50;

    /** @var array 视为“重型”的字段类型（小写包含匹配） */
    private const HEAVY_TYPES = ['text', 'blob', 'clob', 'json', 'binary', 'bytea', 'mediumtext', 'longtext', 'mediumblob', 'longblob', 'tinytext', 'tinyblob'];

    /** @var bool 是否排除大字段 */
    private bool $light = false;

    /** @var int 缓存时间（秒），0 表示不缓存 */
    private int $cacheTime = 0;

    /** @var bool 是否强制刷新缓存 */
    private bool $cacheForce = false;

    /** @var array|null 缓存驱动配置 */
    private ?array $cacheConfig = null;

    /** @var bool 是否自动维护时间戳（created_at / updated_at，存储为 bigint Unix 时间戳） */
    private bool $timestamps = false;

    /**
     * @var bool 是否启用软删除
     *
     * 软删除通过 updated_at 符号判断：
     *   - updated_at > 0  → 正常记录
     *   - updated_at < 0  → 已删除记录（ABS 值仍为真实更新时间）
     *
     * 不需要额外的 deleted_at 字段。
     */
    private bool $softDeletes = false;

    /** @var bool 是否包含已软删除记录 */
    private bool $withTrashed = false;

    /** @var bool 是否只查已软删除记录 */
    private bool $onlyTrashed = false;

    public function __construct(array $config)
    {
        $this->pdo = new \PDO(
            $config['dsn'],
            $config['user'] ?? null,
            $config['password'] ?? null,
            $config['options'] ?? []
        );
        $this->pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
        $this->cacheConfig = $config['cache'] ?? null;

        // 从 DSN 自动检测驱动
        if (preg_match('/^(mysql|pgsql|sqlite)/i', $config['dsn'], $m)) {
            $this->driver = strtolower($m[1]);
        }
    }

    /**
     * 获取 DB 单例（复用连接，避免重复 new PDO）
     *
     * 用法：$db = DB::instance($config);
     */
    public static function instance(array $config): self
    {
        $key = md5($config['dsn'] . ($config['user'] ?? ''));
        if (!isset(self::$instances[$key])) {
            self::$instances[$key] = new self($config);
        }
        return self::$instances[$key];
    }

    /**
     * 引用标识符（表名、列名）
     * MySQL/MariaDB 用反引号 `name`，PostgreSQL/SQLite 用双引号 "name"
     */
    private function qi(string $name): string
    {
        if ($this->driver === 'mysql') {
            return '`' . $name . '`';
        }
        return '"' . $name . '"';
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
     * 排除大字段（TEXT/BLOB/JSON 等）
     *
     * 用法：$this->db->table('posts')->light()->fetchAll();
     * 等价于手动 fields('id, title, status, ...')，但不需要一个个写。
     */
    public function light(): self
    {
        $this->light = true;
        return $this;
    }

    /**
     * 开启自动时间戳（存储为 bigint Unix 时间戳）
     *
     * 开启后：
     *   - insert() 自动填充 updated_at（必须）
     *   - insert() 自动填充 created_at（仅当表有该字段时）
     *   - update() 自动写入 updated_at
     *
     * 建表要求：
     *   id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY  -- 必须
     *   updated_at BIGINT NOT NULL DEFAULT 0                           -- 必须
     *   created_at BIGINT NOT NULL DEFAULT 0                           -- 可选
     *
     * 用法：$this->db->table('posts')->timestamps()->insert([...]);
     */
    public function timestamps(bool $auto = true): self
    {
        $clone = clone $this;
        $clone->timestamps = $auto;
        return $clone;
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
     * WHERE IN 条件
     * 用法：->whereIn('id', [1, 2, 3])
     *       ->where('status=?', ['active'])->whereIn('id', [1,2,3])
     */
    public function whereIn(string $column, array $values): self
    {
        if (empty($values)) {
            // 空数组：永远不匹配
            $cond = '1=0';
        } else {
            $col   = $this->qi($column);
            $marks = implode(',', array_fill(0, count($values), '?'));
            $cond  = "{$col} IN ({$marks})";
        }
        if ($this->where) {
            $this->where  .= " AND {$cond}";
            $this->params  = array_merge($this->params, $values);
        } else {
            $this->where  = $cond;
            $this->params = $values;
        }
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
     * 游标分页（高性能，适合深页 / 无限滚动 / API）
     *
     * 基于主键索引定位，无论第几页性能都一样。
     * 返回结构：['data' => [...], 'next_cursor' => 123, 'has_more' => true]
     *
     * @param int    $perPage 每页条数
     * @param int    $cursor  游标值（上一页最后一条的 ID，0 表示从头开始）
     * @param string $column  游标列名（默认 id）
     * @param string $dir     排序方向 DESC 或 ASC
     *
     * 用法：
     *   // 第一页
     *   $result = $this->db->table('posts')->where('status=?',['published'])
     *       ->cursorPaginate(10);
     *
     *   // 下一页（传入上一页返回的 next_cursor）
     *   $result = $this->db->table('posts')->where('status=?',['published'])
     *       ->cursorPaginate(10, $lastCursor);
     *
     * @return array{data: array, next_cursor: int|null, has_more: bool}
     */
    public function cursorPaginate(int $perPage = 10, int $cursor = 0, string $column = 'id', string $dir = 'DESC'): array
    {
        $isDesc = strtoupper($dir) === 'DESC';
        $op     = $isDesc ? '<' : '>';
        $col    = $this->qi($column);

        // 在已有 WHERE 基础上追加游标条件
        if ($cursor > 0) {
            $cursorWhere = "{$col} {$op} ?";
            if ($this->where) {
                $this->where = "({$this->where}) AND {$cursorWhere}";
            } else {
                $this->where = $cursorWhere;
            }
            $this->params[] = $cursor;
        }

        $this->order = "{$col} {$dir}";
        // 多取一条用于判断是否有下一页
        $this->limit = (string)($perPage + 1);

        $sql  = $this->buildSelect();
        $stmt = $this->pdo->prepare($sql);
        $this->runStmt($stmt, $this->params, $sql);
        $rows = $stmt->fetchAll();

        $hasMore = count($rows) > $perPage;
        if ($hasMore) {
            array_pop($rows);  // 去掉多取的那条
        }

        $nextCursor = $hasMore && !empty($rows) ? end($rows)[$column] : null;

        return [
            'data'        => $rows,
            'next_cursor' => $nextCursor,
            'has_more'    => $hasMore,
        ];
    }

    /**
     * 获取多条记录
     *
     * @param int $limit 可选，直接指定 LIMIT（省去单独调 ->limit()）
     *
     * 用法：
     *   ->fetch()      等同旧 fetchAll()
     *   ->fetch(10)    等同 ->limit(10)->fetchAll()
     */
    public function fetch(int $limit = 0): array
    {
        if ($limit > 0) {
            $this->limit = (string)$limit;
        }

        // 未设置 LIMIT 时，自动加默认上限防止全表扫描
        if ($this->limit === '') {
            $this->limit = (string)self::MAX_ROWS;
        }

        // fields=* 时：手动 light() 或 LIMIT > HEAVY_LIMIT 自动排除大字段
        if ($this->fields === '*') {
            $limitNum = (int)$this->limit;
            if ($this->light || $limitNum > self::HEAVY_LIMIT) {
                $light = $this->getLightFields();
                if ($light) {
                    $this->fields = $light;
                }
            }
        }

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
            $this->runStmt($stmt, $this->params, $sql);
            $data = $stmt->fetchAll();
            $cache->set($key, $data, $this->cacheTime);  // 写入（覆盖）缓存
            return $data;
        }

        $stmt = $this->pdo->prepare($sql);
        $this->runStmt($stmt, $this->params, $sql);
        return $stmt->fetchAll();
    }

    /** @deprecated 旧方法名，保留兼容，内部转发到 fetch() */
    public function fetchAll(): array
    {
        return $this->fetch();
    }

    /**
     * 获取单条记录（LIMIT 1）
     */
    public function fetchOne()
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
            $this->runStmt($stmt, $this->params, $sql);
            $data = $stmt->fetch();
            if ($data !== false) {
                $cache->set($key, $data, $this->cacheTime);
            }
            return $data;
        }

        $stmt = $this->pdo->prepare($sql);
        $this->runStmt($stmt, $this->params, $sql);
        return $stmt->fetch();
    }

    /**
     * 获取单个字段值
     * 用法：->table('config')->where('key=?', ['site_name'])->value()
     */
    public function value()
    {
        $row = $this->fetchOne();
        return $row ? reset($row) : null;
    }

    /**
     * 输出最终 SQL 和参数（不执行查询）
     *
     * 用法：
     *   $sql = $this->db->table('posts')->where('status=?', ['published'])
     *       ->order('id DESC')->limit(10)->toSql();
     *   // 返回: ['sql' => 'SELECT * FROM `posts` WHERE ...', 'params' => ['published']]
     *
     * @return array{sql: string, params: array}
     */
    public function toSql(): array
    {
        if ($this->limit === '') {
            $this->limit = (string)self::MAX_ROWS;
        }
        return [
            'sql'    => $this->buildSelect(),
            'params' => $this->params,
        ];
    }

    /**
     * 打印最终 SQL 并终止（调试用）
     *
     * 用法：$this->db->table('users')->where('age>?', [18])->dd();
     */
    public function dd(): void
    {
        $info = $this->toSql();
        header('Content-Type: text/plain; charset=utf-8');
        echo $info['sql'] . "\n\n";
        echo "Params: " . json_encode($info['params'], JSON_UNESCAPED_UNICODE) . "\n";
        exit;
    }

    /**
     * 统计行数
     */
    public function count(): int
    {
        $t    = $this->qi($this->table);
        $sql  = "SELECT COUNT(*) FROM {$t}"
              . ($this->where ? " WHERE {$this->where}" : '');
        $stmt = $this->pdo->prepare($sql);
        $this->runStmt($stmt, $this->params, $sql);
        return (int)$stmt->fetchColumn();
    }

    /**
     * 插入记录（自动识别单条/批量）
     *
     * 单条插入：insert(['name' => 'foo', 'age' => 20])  → 返回自增 ID
     * 批量插入：insert([['name'=>'a'], ['name'=>'b']])  → 返回插入行数
     */
    public function insert(array $data)
    {
        // 检测是否为二维数组（批量插入）
        if (isset($data[0]) && is_array($data[0])) {
            return $this->insertBatch($data);
        }

        if ($this->timestamps) {
            $now = time();
            $data['updated_at'] = $data['updated_at'] ?? $now;
            if (!isset($data['created_at']) && $this->hasColumn('created_at')) {
                $data['created_at'] = $now;
            }
        }
        $cols   = implode(', ', array_map(fn($k) => $this->qi($k), array_keys($data)));
        $marks  = implode(', ', array_fill(0, count($data), '?'));
        $t      = $this->qi($this->table);
        $sql    = "INSERT INTO {$t} ({$cols}) VALUES ({$marks})";
        if ($this->driver === 'pgsql') {
            $sql .= ' RETURNING id';
        }
        $stmt   = $this->pdo->prepare($sql);
        $this->runStmt($stmt, array_values($data), $sql);
        if ($this->driver === 'pgsql') {
            return $stmt->fetchColumn();
        }
        return $this->pdo->lastInsertId();
    }

    /**
     * 批量插入（内部方法，由 insert() 自动调用）
     * 一条 SQL 插入所有行，性能比循环 insert 快 10-100 倍
     *
     * @return int 插入行数
     */
    private function insertBatch(array $rows): int
    {
        if (empty($rows)) return 0;

        $now  = time();
        $hasCreatedAt = $this->timestamps && $this->hasColumn('created_at');
        $keys = array_keys($rows[0]);

        // 自动补充时间戳字段名
        if ($this->timestamps) {
            if (!in_array('updated_at', $keys)) $keys[] = 'updated_at';
            if ($hasCreatedAt && !in_array('created_at', $keys)) $keys[] = 'created_at';
        }

        $cols    = implode(', ', array_map(fn($k) => $this->qi($k), $keys));
        $single  = '(' . implode(',', array_fill(0, count($keys), '?')) . ')';
        $marks   = implode(', ', array_fill(0, count($rows), $single));
        $t       = $this->qi($this->table);
        $sql     = "INSERT INTO {$t} ({$cols}) VALUES {$marks}";

        $vals = [];
        foreach ($rows as $row) {
            if ($this->timestamps) {
                $row['updated_at'] = $row['updated_at'] ?? $now;
                if ($hasCreatedAt) {
                    $row['created_at'] = $row['created_at'] ?? $now;
                }
            }
            foreach ($keys as $k) {
                $vals[] = $row[$k] ?? null;
            }
        }

        $stmt = $this->pdo->prepare($sql);
        $this->runStmt($stmt, $vals, $sql);
        return $stmt->rowCount();
    }

    /**
     * 更新记录，返回受影响行数
     */
    public function update(array $data): int
    {
        if ($this->timestamps) {
            $data['updated_at'] = $data['updated_at'] ?? time();
        }
        $sets   = implode(', ', array_map(fn($k) => $this->qi($k) . ' = ?', array_keys($data)));
        $vals   = array_merge(array_values($data), $this->params);
        $t      = $this->qi($this->table);
        $sql    = "UPDATE {$t} SET {$sets}"
                . ($this->where ? " WHERE {$this->where}" : '');
        $stmt   = $this->pdo->prepare($sql);
        $this->runStmt($stmt, $vals, $sql);
        return $stmt->rowCount();
    }

    /**
     * 删除记录，返回受影响行数
     */
    public function delete(): int
    {
        $t    = $this->qi($this->table);
        $sql  = "DELETE FROM {$t}"
              . ($this->where ? " WHERE {$this->where}" : '');
        $stmt = $this->pdo->prepare($sql);
        $this->runStmt($stmt, $this->params, $sql);
        return $stmt->rowCount();
    }

    // -------------------------------------------------------------------------
    // 软删除（基于 updated_at 符号）
    //
    // 原理：updated_at > 0 正常，updated_at < 0 已删除
    // 优势：不需要额外的 deleted_at 字段，任何有 updated_at 的表都可以软删除
    // -------------------------------------------------------------------------

    /**
     * 启用软删除模式
     *
     * 启用后：
     *   - 查询自动过滤 updated_at < 0 的记录
     *   - 可用 withTrashed()  查询全部（含已删）
     *   - 可用 onlyTrashed() 只查已删除的
     *   - 可用 softDelete()  将 updated_at 取负
     *   - 可用 restore()     恢复（取绝对值）
     *
     * 要求表有 updated_at BIGINT 字段即可，无需 deleted_at。
     *
     * 用法：$this->db->table('posts')->softDeletes()->where('id=?',[$id])->softDelete();
     */
    public function softDeletes(): self
    {
        $clone = clone $this;
        $clone->softDeletes = true;
        return $clone;
    }

    /** 查询时包含已软删除的记录 */
    public function withTrashed(): self
    {
        $clone = clone $this;
        $clone->withTrashed = true;
        return $clone;
    }

    /** 只查询已软删除的记录 */
    public function onlyTrashed(): self
    {
        $clone = clone $this;
        $clone->onlyTrashed = true;
        return $clone;
    }

    /**
     * 软删除：将 updated_at 取负数
     * UPDATE table SET updated_at = -ABS(updated_at) WHERE ...
     */
    public function softDelete(): int
    {
        $ua   = $this->qi('updated_at');
        $t    = $this->qi($this->table);
        $sql  = "UPDATE {$t} SET {$ua} = -ABS({$ua})"
              . ($this->where ? " WHERE {$this->where}" : '');
        $stmt = $this->pdo->prepare($sql);
        $this->runStmt($stmt, $this->params, $sql);
        return $stmt->rowCount();
    }

    /**
     * 恢复已软删除的记录：将 updated_at 取绝对值
     * UPDATE table SET updated_at = ABS(updated_at) WHERE ...
     */
    public function restore(): int
    {
        $ua   = $this->qi('updated_at');
        $t    = $this->qi($this->table);
        $sql  = "UPDATE {$t} SET {$ua} = ABS({$ua})"
              . ($this->where ? " WHERE {$this->where}" : '');
        $stmt = $this->pdo->prepare($sql);
        $this->runStmt($stmt, $this->params, $sql);
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
        $this->runStmt($stmt, $params, $sql);
        return $stmt->fetchAll();
    }

    /**
     * 执行原生语句（INSERT/UPDATE/DELETE），返回受影响行数
     */
    public function exec(string $sql, array $params = []): int
    {
        $stmt = $this->pdo->prepare($sql);
        $this->runStmt($stmt, $params, $sql);
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
    // 事务
    // -------------------------------------------------------------------------

    /** 开始事务 */
    public function beginTransaction(): bool
    {
        return $this->pdo->beginTransaction();
    }

    /** 提交事务 */
    public function commit(): bool
    {
        return $this->pdo->commit();
    }

    /** 回滚事务 */
    public function rollback(): bool
    {
        return $this->pdo->rollBack();
    }

    /**
     * 事务闭包（自动 commit/rollback）
     *
     * 用法：
     *   \$this->db->transaction(function(\$db) {
     *       \$db->table('orders')->insert([...]);
     *       \$db->table('stock')->where('id=?',[1])->update(['qty' => 99]);
     *   });
     *
     * 闭包内抛异常自动回滚并重新抛出。
     *
     * @param callable $callback 接收 DB 实例参数
     * @return mixed 闭包返回值
     */
    public function transaction(callable $callback)
    {
        $this->pdo->beginTransaction();
        try {
            $result = $callback($this);
            $this->pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
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
     *   $user = $this->db->belongsTo('users', 'id', $post['user_id'])->fetchOne();
     */
    public function belongsTo(string $relTable, string $pk, $fkValue): self
    {
        return $this->table($relTable)->where("{$pk}=?", [$fkValue]);
    }

    /**
     * 多对多：通过中间表获取关联记录（返回可链式的 DB 实例）
     *
     * @param string $relTable   关联表名（目标表）
     * @param string $pivotTable 中间表名
     * @param string $localFk    中间表中指向当前记录的外键
     * @param string $relFk      中间表中指向关联记录的外键
     * @param mixed  $pkValue    当前记录的主键值
     *
     * 用法：
     *   // 获取文章的所有标签（通过 post_tag 中间表）
     *   $tags = $this->db->belongsToMany('tags', 'post_tag', 'post_id', 'tag_id', $postId)
     *       ->order('tags.name')->fetchAll();
     */
    public function belongsToMany(
        string $relTable,
        string $pivotTable,
        string $localFk,
        string $relFk,
        $pkValue
    ): self {
        $clone        = clone $this;
        $clone->table = $relTable;
        $pt = $this->qi($pivotTable);
        $rt = $this->qi($relTable);
        $lf = $this->qi($localFk);
        $rf = $this->qi($relFk);
        $clone->where  = "{$pt}.{$lf}=?";
        $clone->params = [$pkValue];
        $clone->order  = '';
        $clone->limit  = '';
        // 将 INNER JOIN 嵌入 fields 作为自定义前缀（buildSelect 会原样包含）
        $clone->fields = "{$rt}.* FROM {$pt} INNER JOIN {$rt} ON {$pt}.{$rf}={$rt}." . $this->qi('id');
        // 标记 table 为空使 buildSelect 跳过 FROM 部分
        $clone->__btm = true;
        return $clone;
    }

    // -------------------------------------------------------------------------
    // 内部辅助
    // -------------------------------------------------------------------------

    /**
     * 执行语句并记录慢查询
     * 超过 SLOW_THRESHOLD (100ms) 自动写入日志
     */
    private function runStmt(\PDOStatement $stmt, array $params, string $sql): void
    {
        $start = microtime(true);
        $stmt->execute($params);
        $ms = round((microtime(true) - $start) * 1000, 1);

        if ($ms >= self::SLOW_THRESHOLD && class_exists('\Lib\Logger')) {
            Logger::write('warning', "[SLOW SQL] {$ms}ms | {$sql}", [
                'params' => $params,
                'time_ms' => $ms,
            ]);
        }
    }

    private function buildSelect(): string
    {
        if (!empty($this->__btm)) {
            $sql = "SELECT {$this->fields}";
            if ($this->where) $sql .= " WHERE {$this->where}";
            if ($this->order) $sql .= " ORDER BY {$this->order}";
            if ($this->limit) $sql .= " LIMIT {$this->limit}";
            return $sql;
        }

        $t   = $this->qi($this->table);
        $sql = "SELECT {$this->fields} FROM {$t}";

        // 构建 WHERE（含软删除过滤：基于 updated_at 符号）
        $where = $this->where;
        if ($this->softDeletes && !$this->withTrashed) {
            $ua = $this->qi('updated_at');
            $sd = $this->onlyTrashed
                ? "{$ua} < 0"
                : "{$ua} > 0";
            $where = $where ? "({$where}) AND {$sd}" : $sd;
        }

        if ($where) $sql .= " WHERE {$where}";
        if ($this->order) $sql .= " ORDER BY {$this->order}";
        if ($this->limit) $sql .= " LIMIT {$this->limit}";
        return $sql;
    }

    /**
     * 检测当前表是否有指定列（带缓存）
     */
    private function hasColumn(string $column): bool
    {
        $schema = $this->getSchema();
        return isset($schema[$column]);
    }

    /**
     * 获取表结构：['列名' => '类型', ...]（带缓存）
     */
    private function getSchema(): array
    {
        $key = $this->table;
        if (!isset(self::$schemaCache[$key])) {
            $schema = [];
            if ($this->driver === 'sqlite') {
                $rows = $this->pdo->query("PRAGMA table_info(" . $this->qi($this->table) . ")")->fetchAll();
                foreach ($rows as $r) $schema[$r['name']] = strtolower($r['type']);
            } elseif ($this->driver === 'pgsql') {
                $rows = $this->pdo->query(
                    "SELECT column_name, data_type FROM information_schema.columns WHERE table_name='" . $this->table . "'"
                )->fetchAll();
                foreach ($rows as $r) $schema[$r['column_name']] = strtolower($r['data_type']);
            } else {
                $rows = $this->pdo->query("SHOW COLUMNS FROM " . $this->qi($this->table))->fetchAll();
                foreach ($rows as $r) $schema[$r['Field']] = strtolower($r['Type']);
            }
            self::$schemaCache[$key] = $schema;
        }
        return self::$schemaCache[$key];
    }

    /**
     * 获取排除大字段后的字段列表
     * 返回如 "`id`, `title`, `status`, `updated_at`"，或空字符串（全部都是大字段时）
     */
    private function getLightFields(): string
    {
        $schema = $this->getSchema();
        $light = [];
        foreach ($schema as $name => $type) {
            $isHeavy = false;
            foreach (self::HEAVY_TYPES as $ht) {
                if (strpos($type, $ht) !== false) {
                    $isHeavy = true;
                    break;
                }
            }
            if (!$isHeavy) {
                $light[] = $this->qi($name);
            }
        }
        return implode(', ', $light);
    }

    /** @var array 表结构缓存 ['表名' => ['列名' => '类型']] */
    private static array $schemaCache = [];
}
