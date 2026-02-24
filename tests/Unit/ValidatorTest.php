<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase as BaseTestCase;
use Lib\Validator;

/**
 * ValidatorTest — 表单验证器单元测试
 *
 * 运行：php h2 test
 *   或：./vendor/bin/phpunit tests/Unit/ValidatorTest.php
 */
class ValidatorTest extends BaseTestCase
{
    // ── required ──────────────────────────────────────────────────────────────

    public function testRequiredFailsOnEmpty(): void
    {
        $v = new Validator(['name' => ''], ['name' => 'required']);
        $this->assertTrue($v->fails());
        $this->assertNotNull($v->error('name'));
    }

    public function testRequiredPassesOnValue(): void
    {
        $v = new Validator(['name' => 'Tom'], ['name' => 'required']);
        $this->assertTrue($v->passes());
    }

    // ── email ─────────────────────────────────────────────────────────────────

    public function testEmailFailsOnInvalidFormat(): void
    {
        $v = new Validator(['email' => 'not-an-email'], ['email' => 'email']);
        $this->assertTrue($v->fails());
    }

    public function testEmailPassesOnValidAddress(): void
    {
        $v = new Validator(['email' => 'user@example.com'], ['email' => 'email']);
        $this->assertTrue($v->passes());
    }

    // ── integer ───────────────────────────────────────────────────────────────

    public function testIntegerFailsOnFloat(): void
    {
        $v = new Validator(['age' => '18.5'], ['age' => 'integer']);
        $this->assertTrue($v->fails());
    }

    public function testIntegerPassesOnInt(): void
    {
        $v = new Validator(['age' => '25'], ['age' => 'integer']);
        $this->assertTrue($v->passes());
    }

    // ── min / max ─────────────────────────────────────────────────────────────

    public function testMinFailsBelowThreshold(): void
    {
        $v = new Validator(['age' => '0'], ['age' => 'integer|min:1']);
        $this->assertTrue($v->fails());
    }

    public function testMaxFailsAboveThreshold(): void
    {
        $v = new Validator(['age' => '200'], ['age' => 'integer|max:150']);
        $this->assertTrue($v->fails());
    }

    // ── min_len / max_len ─────────────────────────────────────────────────────

    public function testMinLenFailsOnShortString(): void
    {
        $v = new Validator(['pw' => 'abc'], ['pw' => 'min_len:6']);
        $this->assertTrue($v->fails());
    }

    public function testMaxLenFailsOnLongString(): void
    {
        $v = new Validator(['name' => str_repeat('a', 51)], ['name' => 'max_len:50']);
        $this->assertTrue($v->fails());
    }

    // ── in ────────────────────────────────────────────────────────────────────

    public function testInFailsOnInvalidValue(): void
    {
        $v = new Validator(['role' => 'superadmin'], ['role' => 'in:admin,user,guest']);
        $this->assertTrue($v->fails());
    }

    public function testInPassesOnValidValue(): void
    {
        $v = new Validator(['role' => 'admin'], ['role' => 'in:admin,user,guest']);
        $this->assertTrue($v->passes());
    }

    // ── confirmed ─────────────────────────────────────────────────────────────

    public function testConfirmedFailsOnMismatch(): void
    {
        $v = new Validator(
            ['password' => 'secret123', 'password_confirmation' => 'wrong'],
            ['password' => 'confirmed']
        );
        $this->assertTrue($v->fails());
    }

    public function testConfirmedPassesOnMatch(): void
    {
        $v = new Validator(
            ['password' => 'secret123', 'password_confirmation' => 'secret123'],
            ['password' => 'confirmed']
        );
        $this->assertTrue($v->passes());
    }

    // ── 多字段 + 自定义标签 ───────────────────────────────────────────────────

    public function testMultipleFieldsCollectsAllErrors(): void
    {
        $v = new Validator(
            ['email' => '', 'age' => 'abc'],
            ['email' => 'required|email', 'age' => 'required|integer'],
            ['email' => '邮箱', 'age' => '年龄']
        );

        $this->assertTrue($v->fails());
        $this->assertCount(2, $v->errors());
        $this->assertStringContainsString('邮箱', $v->firstError());
    }

    // ── 空值跳过非 required 规则 ──────────────────────────────────────────────

    public function testEmptyValueSkipsOtherRulesWhenNotRequired(): void
    {
        // email 字段为空但没有 required，其他规则应跳过
        $v = new Validator(['email' => ''], ['email' => 'email']);
        $this->assertTrue($v->passes());
    }
}
