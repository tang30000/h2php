<?php
namespace Lib;

/**
 * Upload — 文件上传辅助
 *
 * 用法（控制器中）：
 *   $file = $this->upload('avatar', 'static/uploads/avatars');
 *
 *   if ($file->fails()) {
 *       $this->flash('error', $file->error());
 *       $this->redirect('/user/profile');
 *   }
 *
 *   $path = $file->path();  // 存储的相对路径，可存入数据库
 *
 * 链式配置：
 *   $file = $this->upload('photo', 'static/uploads')
 *       ->maxSize(5 * 1024 * 1024)     // 最大 5 MB
 *       ->allowTypes(['jpg', 'png', 'webp'])
 *       ->rename('uuid');              // uuid | timestamp | original
 */
class Upload
{
    private string $field;
    private string $destDir;
    private int    $maxBytes    = 5 * 1024 * 1024;   // 5 MB
    private array  $allowExts   = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'zip'];
    private string $renameMode  = 'uuid';

    private ?string $storedPath = null;
    private ?string $errorMsg   = null;
    private bool    $saved      = false;

    /** 危险扩展名黑名单（不可覆盖，即使 allowTypes 放行也拒绝） */
    private const DANGER_EXTS = [
        'php', 'php3', 'php5', 'phtml', 'phar',
        'sh', 'bash', 'bat', 'cmd', 'exe', 'com',
        'cgi', 'pl', 'py', 'rb', 'jsp', 'asp', 'aspx',
        'htaccess', 'htpasswd',
    ];

    /** 禁止上传到这些目录（防止代码注入） */
    private const FORBIDDEN_DIRS = ['app', 'lib', 'config', 'views', 'vendor'];

    public function __construct(string $field, string $destDir)
    {
        $this->field   = $field;
        $this->destDir = rtrim($destDir, '/');
    }

    // ── 链式配置 ─────────────────────────────────────────────────

    /** 最大文件大小（字节） */
    public function maxSize(int $bytes): self { $this->maxBytes  = $bytes; return $this; }

    /** 允许的扩展名列表（小写，不含点） */
    public function allowTypes(array $exts): self { $this->allowExts = array_map('strtolower', $exts); return $this; }

    /** 重命名策略：'uuid'（默认）| 'timestamp' | 'original' */
    public function rename(string $mode): self { $this->renameMode = $mode; return $this; }

    // ── 执行保存 ─────────────────────────────────────────────────

    /**
     * 验证并保存文件
     * @return $this
     */
    public function save(): self
    {
        if ($this->saved) return $this;
        $this->saved = true;

        // 安全检查：禁止路径穿越
        if (strpos($this->destDir, '..') !== false) {
            $this->errorMsg = '上传目录不允许包含 ..（路径穿越）';
            return $this;
        }

        // 安全检查：禁止上传到代码目录
        $dirParts = explode('/', str_replace('\\', '/', $this->destDir));
        $firstDir = strtolower($dirParts[0] ?? '');
        if (in_array($firstDir, self::FORBIDDEN_DIRS, true)) {
            $this->errorMsg = "禁止上传到 {$firstDir}/ 目录（安全限制）";
            return $this;
        }

        $files = $_FILES[$this->field] ?? null;

        if (!$files || empty($files['tmp_name']) || $files['error'] !== UPLOAD_ERR_OK) {
            $this->errorMsg = $this->uploadErrorMessage($files['error'] ?? UPLOAD_ERR_NO_FILE);
            return $this;
        }

        // 大小校验
        if ($files['size'] > $this->maxBytes) {
            $this->errorMsg = sprintf('文件大小超过限制（最大 %s）', $this->formatBytes($this->maxBytes));
            return $this;
        }

        // 扩展名校验（不依赖 MIME，简单实用）
        $ext = strtolower(pathinfo($files['name'], PATHINFO_EXTENSION));

        // 危险扩展名硬拦截（不可覆盖）
        if (in_array($ext, self::DANGER_EXTS, true)) {
            $this->errorMsg = sprintf('禁止上传可执行文件类型：.%s', $ext);
            return $this;
        }

        if ($this->allowExts && !in_array($ext, $this->allowExts, true)) {
            $this->errorMsg = sprintf('不支持的文件类型：.%s（允许：%s）', $ext, implode(', ', $this->allowExts));
            return $this;
        }

        // 生成目标路径
        $dir = defined('ROOT') ? ROOT . '/' . $this->destDir : $this->destDir;
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $filename = $this->generateName($files['name'], $ext);
        $dest     = $dir . '/' . $filename;

        if (!move_uploaded_file($files['tmp_name'], $dest)) {
            $this->errorMsg = '文件保存失败，请检查目录权限';
            return $this;
        }

        $this->storedPath = $this->destDir . '/' . $filename;
        return $this;
    }

    // ── 结果读取 ─────────────────────────────────────────────────

    /** 是否上传失败 */
    public function fails(): bool
    {
        $this->save();
        return $this->errorMsg !== null;
    }

    /** 错误信息 */
    public function error(): string
    {
        return $this->errorMsg ?? '';
    }

    /** 存储路径（相对 ROOT，可直接存入数据库） */
    public function path(): ?string
    {
        return $this->storedPath;
    }

    /** URL（相对路径，可直接用于 <img src=> 等） */
    public function url(): ?string
    {
        return $this->storedPath ? '/' . $this->storedPath : null;
    }

    // ── 私有辅助 ─────────────────────────────────────────────────

    private function generateName(string $original, string $ext): string
    {
        if ($this->renameMode === 'timestamp') {
            return time() . '_' . random_int(1000, 9999) . '.' . $ext;
        }
        if ($this->renameMode === 'original') {
            return preg_replace('/[^a-zA-Z0-9_\-.]/', '_', pathinfo($original, PATHINFO_FILENAME)) . '.' . $ext;
        }
        // default: uuid
        return sprintf('%s.%s', bin2hex(random_bytes(16)), $ext);
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1048576) return round($bytes / 1048576, 1) . ' MB';
        if ($bytes >= 1024)    return round($bytes / 1024, 1) . ' KB';
        return $bytes . ' B';
    }

    private function uploadErrorMessage(int $code): string
    {
        switch ($code) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return '文件超过服务器允许的上传大小';
            case UPLOAD_ERR_PARTIAL:
                return '文件只上传了一部分';
            case UPLOAD_ERR_NO_FILE:
                return '没有选择文件';
            case UPLOAD_ERR_NO_TMP_DIR:
                return '临时目录不存在';
            case UPLOAD_ERR_CANT_WRITE:
                return '磁盘写入失败';
            default:
                return '上传失败';
        }
    }
}
