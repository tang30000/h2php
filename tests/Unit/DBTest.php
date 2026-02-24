<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Lib\DB;

/**
 * DBTest — DB 链式查询单元测试（使用 SQLite 内存库，无需 MySQL）
 *
 * 运行：./vendor/bin/phpunit tests/Unit/DBTest.php
 */
class DBTest extends TestCase
{
    private DB $db;

    protected function setUp(): void
    {
        // 使用 SQLite 内存库，跑完即销毁，无需 MySQL
        $this->db = new DB([
            'dsn'      => 'sqlite::memory:',
            'user'     => '',
            'password' => '',
            'options'  => [
                \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            ],
        ]);

        $this->db->pdo()->exec("CREATE TABLE users (
            id    INTEGER PRIMARY KEY AUTOINCREMENT,
            name  TEXT NOT NULL,
            email TEXT NOT NULL,
            age   INTEGER
        )");
    }

    // ── insert / fetch ────────────────────────────────────────────────────────

    public function testInsertAndFetch(): void
    {
        $id = $this->db->table('users')->insert(['name' => 'Tom', 'email' => 'tom@test.com', 'age' => 20]);
        $this->assertGreaterThan(0, $id);

        $row = $this->db->table('users')->where('id=?', [$id])->fetch();
        $this->assertEquals('Tom', $row['name']);
        $this->assertEquals('tom@test.com', $row['email']);
    }

    // ── fetchAll ──────────────────────────────────────────────────────────────

    public function testFetchAll(): void
    {
        $this->db->table('users')->insert(['name' => 'A', 'email' => 'a@test.com', 'age' => 10]);
        $this->db->table('users')->insert(['name' => 'B', 'email' => 'b@test.com', 'age' => 20]);

        $rows = $this->db->table('users')->fetchAll();
        $this->assertCount(2, $rows);
    }

    // ── count ─────────────────────────────────────────────────────────────────

    public function testCount(): void
    {
        $this->db->table('users')->insert(['name' => 'X', 'email' => 'x@test.com', 'age' => 1]);
        $this->db->table('users')->insert(['name' => 'Y', 'email' => 'y@test.com', 'age' => 2]);

        $count = $this->db->table('users')->count();
        $this->assertEquals(2, $count);
    }

    // ── update ────────────────────────────────────────────────────────────────

    public function testUpdate(): void
    {
        $id = $this->db->table('users')->insert(['name' => 'Old', 'email' => 'old@test.com', 'age' => 0]);
        $affected = $this->db->table('users')->where('id=?', [$id])->update(['name' => 'New']);
        $this->assertEquals(1, $affected);

        $row = $this->db->table('users')->where('id=?', [$id])->fetch();
        $this->assertEquals('New', $row['name']);
    }

    // ── delete ────────────────────────────────────────────────────────────────

    public function testDelete(): void
    {
        $id = $this->db->table('users')->insert(['name' => 'Del', 'email' => 'del@test.com', 'age' => 0]);
        $this->db->table('users')->where('id=?', [$id])->delete();

        $row = $this->db->table('users')->where('id=?', [$id])->fetch();
        $this->assertFalse($row);
    }

    // ── where + order + limit ─────────────────────────────────────────────────

    public function testWhereOrderLimit(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->db->table('users')->insert(['name' => "U{$i}", 'email' => "u{$i}@test.com", 'age' => $i * 10]);
        }

        $rows = $this->db->table('users')
            ->where('age >= ?', [20])
            ->order('age DESC')
            ->limit(2)
            ->fetchAll();

        $this->assertCount(2, $rows);
        $this->assertEquals(50, $rows[0]['age']); // 最大年龄在前
    }

    // ── hasMany / belongsTo ───────────────────────────────────────────────────

    public function testHasManyAndBelongsTo(): void
    {
        $this->db->pdo()->exec("CREATE TABLE posts (
            id      INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            title   TEXT
        )");

        $uid = $this->db->table('users')->insert(['name' => 'Author', 'email' => 'a@b.com', 'age' => 30]);
        $this->db->table('posts')->insert(['user_id' => $uid, 'title' => 'Post 1']);
        $this->db->table('posts')->insert(['user_id' => $uid, 'title' => 'Post 2']);

        $posts = $this->db->hasMany('posts', 'user_id', $uid)->fetchAll();
        $this->assertCount(2, $posts);

        $user = $this->db->belongsTo('users', 'id', $uid)->fetch();
        $this->assertEquals('Author', $user['name']);
    }

    // ── value() ──────────────────────────────────────────────────────────────

    public function testValue(): void
    {
        $this->db->table('users')->insert(['name' => 'Val', 'email' => 'val@test.com', 'age' => 99]);
        $name = $this->db->table('users')->fields('name')->where('email=?', ['val@test.com'])->value();
        $this->assertEquals('Val', $name);
    }
}
