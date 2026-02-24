<?php
namespace Lib;

/**
 * Validator — 表单验证器
 *
 * 用法：
 *   $v = new \Lib\Validator($_POST, [
 *       'name'  => 'required|max_len:50',
 *       'email' => 'required|email',
 *       'age'   => 'required|integer|min:1|max:150',
 *   ]);
 *
 *   if ($v->fails()) {
 *       echo $v->firstError();   // 第一条错误
 *       print_r($v->errors());   // 所有错误
 *   }
 */
class Validator
{
    /** @var array 原始数据 */
    private array $data;

    /** @var array [字段 => [错误消息, ...]] */
    private array $errors = [];

    /** @var \Lib\DB|null 用于 unique 规则 */
    private ?DB $db;

    /** @var array 自定义字段标签（用于错误提示） */
    private array $labels;

    /**
     * @param array       $data   待验证数据（通常是 $_POST）
     * @param array       $rules  [字段 => 'rule1|rule2:param|...']
     * @param array       $labels [字段 => '显示名称']（可选）
     * @param \Lib\DB|null $db    传入 DB 实例以支持 unique 规则
     */
    public function __construct(array $data, array $rules, array $labels = [], ?DB $db = null)
    {
        $this->data   = $data;
        $this->labels = $labels;
        $this->db     = $db;
        $this->validate($rules);
    }

    // -------------------------------------------------------------------------
    // 公开结果方法
    // -------------------------------------------------------------------------

    /** 是否有验证错误 */
    public function fails(): bool
    {
        return !empty($this->errors);
    }

    /** 是否全部通过 */
    public function passes(): bool
    {
        return empty($this->errors);
    }

    /**
     * 获取所有错误（[字段 => [错误1, 错误2, ...]]）
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * 获取指定字段的第一条错误，不存在则返回 null
     */
    public function error(string $field): ?string
    {
        return $this->errors[$field][0] ?? null;
    }

    /**
     * 获取全局第一条错误消息
     */
    public function firstError(): ?string
    {
        foreach ($this->errors as $msgs) {
            return $msgs[0] ?? null;
        }
        return null;
    }

    /**
     * 所有错误合并为一个数组（扁平化）
     */
    public function allErrors(): array
    {
        $flat = [];
        foreach ($this->errors as $msgs) {
            foreach ($msgs as $msg) {
                $flat[] = $msg;
            }
        }
        return $flat;
    }

    // -------------------------------------------------------------------------
    // 内部验证逻辑
    // -------------------------------------------------------------------------

    private function validate(array $rules): void
    {
        foreach ($rules as $field => $ruleStr) {
            $value = $this->data[$field] ?? null;
            $label = $this->labels[$field] ?? $field;

            foreach (explode('|', $ruleStr) as $ruleExpr) {
                [$rule, $param] = array_pad(explode(':', $ruleExpr, 2), 2, null);
                $rule = trim($rule);

                $error = $this->applyRule($rule, $field, $value, $param, $label);
                if ($error !== null) {
                    $this->errors[$field][] = $error;
                    // required 失败后跳过此字段的后续规则
                    if ($rule === 'required') {
                        break;
                    }
                }
            }
        }
    }

    private function applyRule(string $rule, string $field, $value, ?string $param, string $label): ?string
    {
        // 非 required 时，空值跳过其他规则
        if ($rule !== 'required' && ($value === null || $value === '')) {
            return null;
        }

        switch ($rule) {
            case 'required':
                if ($value === null || $value === '' || (is_array($value) && empty($value))) {
                    return "{$label} 不能为空";
                }
                break;

            case 'email':
                if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    return "{$label} 邮箱格式不正确";
                }
                break;

            case 'integer':
                if (!ctype_digit(ltrim((string)$value, '-')) || (string)(int)$value !== (string)$value) {
                    return "{$label} 必须是整数";
                }
                break;

            case 'numeric':
                if (!is_numeric($value)) {
                    return "{$label} 必须是数字";
                }
                break;

            case 'min':
                if (is_numeric($value) && (float)$value < (float)$param) {
                    return "{$label} 不能小于 {$param}";
                }
                break;

            case 'max':
                if (is_numeric($value) && (float)$value > (float)$param) {
                    return "{$label} 不能大于 {$param}";
                }
                break;

            case 'min_len':
                if (mb_strlen((string)$value) < (int)$param) {
                    return "{$label} 长度不能少于 {$param} 个字符";
                }
                break;

            case 'max_len':
                if (mb_strlen((string)$value) > (int)$param) {
                    return "{$label} 长度不能超过 {$param} 个字符";
                }
                break;

            case 'in':
                $allowed = explode(',', $param ?? '');
                if (!in_array($value, $allowed, true)) {
                    return "{$label} 值不合法（允许：{$param}）";
                }
                break;

            case 'regex':
                if (!preg_match($param, (string)$value)) {
                    return "{$label} 格式不正确";
                }
                break;

            case 'url':
                if (!filter_var($value, FILTER_VALIDATE_URL)) {
                    return "{$label} URL 格式不正确";
                }
                break;

            case 'confirmed':
                // 校验 {field}_confirmation 与 {field} 相同
                $confirm = $this->data["{$field}_confirmation"] ?? null;
                if ($value !== $confirm) {
                    return "{$label} 两次输入不一致";
                }
                break;

            case 'unique':
                // unique:table,column
                [$table, $col] = array_pad(explode(',', $param ?? '', 2), 2, $field);
                if ($this->db && $this->db->table($table)->where("{$col}=?", [$value])->count() > 0) {
                    return "{$label} 已被占用";
                }
                break;

            default:
                // 未知规则，忽略
                break;
        }

        return null;
    }
}
