<?php
namespace Lib;

/**
 * StaticFile — PHP 内置服务器静态文件直通
 *
 * 当使用 php -S 开发服务器时，所有请求都经过 index.php。
 * 静态资源（css/js/图片/字体等）应直接返回，不走路由。
 *
 * 用法（在 index.php 顶部调用）：
 *   require __DIR__ . '/lib/StaticFile.php';
 *   \Lib\StaticFile::serve();
 */
class StaticFile
{
    private static array $mimeTypes = [
        'css'   => 'text/css',
        'js'    => 'application/javascript',
        'json'  => 'application/json',
        'png'   => 'image/png',
        'jpg'   => 'image/jpeg',
        'jpeg'  => 'image/jpeg',
        'gif'   => 'image/gif',
        'svg'   => 'image/svg+xml',
        'webp'  => 'image/webp',
        'ico'   => 'image/x-icon',
        'woff'  => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf'   => 'font/ttf',
        'eot'   => 'application/vnd.ms-fontobject',
        'mp4'   => 'video/mp4',
        'pdf'   => 'application/pdf',
        'zip'   => 'application/zip',
        'xml'   => 'application/xml',
        'txt'   => 'text/plain',
        'map'   => 'application/json',
    ];

    /**
     * 检测并直接输出静态文件，命中则 exit
     *
     * @param string|null $docRoot 文档根目录，默认为 index.php 所在目录
     */
    public static function serve(?string $docRoot = null): void
    {
        $uri  = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $root = $docRoot ?? dirname($_SERVER['SCRIPT_FILENAME']);
        $file = $root . $uri;

        if ($uri === '/' || !is_file($file)) {
            return;
        }

        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (!isset(self::$mimeTypes[$ext])) {
            return;
        }

        header('Content-Type: ' . self::$mimeTypes[$ext]);
        header('Content-Length: ' . filesize($file));
        readfile($file);
        exit;
    }
}
