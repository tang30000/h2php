<?php
namespace Lib;

/**
 * Cache — 多驱动缓存封装
 *
 * 支持驱动：memcache | memcached | redis | file
 *
 * config/config.php 中配置：
 *   'cache' => [
 *       'driver'  => 'redis',       // memcache | memcached | redis | file
 *       'host'    => '127.0.0.1',
 *       'port'    => 6379,          // Redis 默认 6379，Memcache 默认 11211
 *       'prefix'  => 'h2_',        // key 前缀，防止多项目冲突
 *       'dir'     => '',            // file 驱动缓存目录（默认 ROOT/cache）
 *   ],
 */
class Cache
{
    private static ?self $instance = null;

    private string $driver;
    private string $prefix;
    private ?string $dir;

    /** @var \Memcache|\Memcached|\Redis|null */
    private $conn;

    private function __construct(array $config)
    {
        $this->driver = $config['driver']  ?? 'file';
        $this->prefix = $config['prefix']  ?? 'h2_';
        $this->dir    = $config['dir']     ?? null;

        $host = $config['host'] ?? '127.0.0.1';
        $port = (int)($config['port'] ?? 11211);

        switch ($this->driver) {
            case 'memcache':
                $this->conn = new \Memcache();
                $this->conn->connect($host, $port);
                break;

            case 'memcached':
                $this->conn = new \Memcached();
                $this->conn->addServer($host, $port);
                break;

            case 'redis':
                $this->conn = new \Redis();
                $this->conn->pconnect($host, $config['port'] ?? 6379);
                if (!empty($config['password'])) {
                    $this->conn->auth($config['password']);
                }
                break;

            case 'file':
            default:
                $this->driver = 'file';
                $this->dir    = rtrim($this->dir ?? (defined('ROOT') ? ROOT . '/cache' : sys_get_temp_dir()), '/\\');
                if (!is_dir($this->dir)) {
                    mkdir($this->dir, 0755, true);
                }
                break;
        }
    }

    /** 获取单例（由 DB 内部调用） */
    public static function instance(array $config): self
    {
        if (self::$instance === null) {
            self::$instance = new self($config);
        }
        return self::$instance;
    }

    // -------------------------------------------------------------------------
    // 公开接口
    // -------------------------------------------------------------------------

    /**
     * 读取缓存，未命中返回 null
     */
    public function get(string $key)
    {
        $key = $this->prefix . $key;

        switch ($this->driver) {
            case 'memcache':
                $val = $this->conn->get($key);
                return $val === false ? null : $val;

            case 'memcached':
                $val = $this->conn->get($key);
                return $this->conn->getResultCode() === \Memcached::RES_NOTFOUND ? null : $val;

            case 'redis':
                $val = $this->conn->get($key);
                return $val === false ? null : unserialize($val);

            case 'file':
                return $this->fileGet($key);
        }
        return null;
    }

    /**
     * 写入缓存
     *
     * @param string $key
     * @param mixed  $value
     * @param int    $ttl   秒数，0 = 永不过期
     */
    public function set(string $key, $value, int $ttl = 3600): bool
    {
        $key = $this->prefix . $key;

        switch ($this->driver) {
            case 'memcache':
                return $this->conn->set($key, $value, 0, $ttl);

            case 'memcached':
                return $this->conn->set($key, $value, $ttl);

            case 'redis':
                $val = serialize($value);
                if ($ttl > 0) {
                    return $this->conn->setex($key, $ttl, $val);
                }
                return $this->conn->set($key, $val);

            case 'file':
                return $this->fileSet($key, $value, $ttl);
        }
        return false;
    }

    /**
     * 删除指定 key
     */
    public function delete(string $key): bool
    {
        $key = $this->prefix . $key;
        switch ($this->driver) {
            case 'memcache':  return $this->conn->delete($key);
            case 'memcached': return $this->conn->delete($key);
            case 'redis':     return (bool)$this->conn->del($key);
            case 'file':      return $this->fileDelete($key);
        }
        return false;
    }

    /**
     * 清空所有缓存（谨慎使用）
     */
    public function flush(): void
    {
        switch ($this->driver) {
            case 'memcache':  $this->conn->flush(); break;
            case 'memcached': $this->conn->flush(); break;
            case 'redis':     $this->conn->flushAll(); break;
            case 'file':      $this->fileFlush(); break;
        }
    }

    // -------------------------------------------------------------------------
    // File 驱动实现
    // -------------------------------------------------------------------------

    private function filePath(string $key): string
    {
        return $this->dir . '/' . md5($key) . '.cache';
    }

    private function fileGet(string $key)
    {
        $path = $this->filePath($key);
        if (!is_file($path)) return null;
        $data = unserialize(file_get_contents($path));
        if ($data['expires'] > 0 && $data['expires'] < time()) {
            unlink($path);
            return null;
        }
        return $data['value'];
    }

    private function fileSet(string $key, $value, int $ttl): bool
    {
        $data = [
            'expires' => $ttl > 0 ? time() + $ttl : 0,
            'value'   => $value,
        ];
        return file_put_contents($this->filePath($key), serialize($data), LOCK_EX) !== false;
    }

    private function fileDelete(string $key): bool
    {
        $path = $this->filePath($key);
        return is_file($path) ? unlink($path) : true;
    }

    private function fileFlush(): void
    {
        foreach (glob($this->dir . '/*.cache') as $f) {
            unlink($f);
        }
    }
}
