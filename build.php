<?php
/**
 * H2PHP Single File Build Script
 * Usage: php build.php
 * Output: dist/h2php-core.php
 */

$root    = __DIR__;
$libDir  = $root . '/lib';
$outDir  = $root . '/dist';
$outFile = $outDir . '/h2php-core.php';

$order = [
    'Env.php','Logger.php','StaticFile.php','Str.php','Request.php',
    'Response.php','Cookie.php','Auth.php','Encryption.php','RateLimiter.php',
    'Cache.php','DB.php','Validator.php','Upload.php','Mail.php',
    'Event.php','Queue.php','Scheduler.php','Http.php','Redis.php',
    'Pagination.php','Router.php','Core.php','Bootstrap.php',
];

if (!is_dir($outDir)) { mkdir($outDir, 0755, true); }

foreach (glob($libDir . '/*.php') as $f) {
    $bn = basename($f);
    if (!in_array($bn, $order)) { $order[] = $bn; }
}

$header  = "<?php\n";
$header .= "/**\n * H2PHP Framework - Single File Distribution\n";
$header .= " * Version: 1.1.0\n * Built: " . date('Y-m-d H:i:s') . "\n";
$header .= " * Source: https://github.com/tang30000/h2php\n * License: MIT\n";
$header .= " * Usage: require __DIR__ . '/h2php-core.php';\n */\n\n";

$body = '';
$count = 0;
$totalLines = 0;

foreach ($order as $file) {
    $path = $libDir . '/' . $file;
    if (!file_exists($path)) { echo "  [SKIP] $file\n"; continue; }

    $code = file_get_contents($path);
    $lines = substr_count($code, "\n") + 1;
    $totalLines += $lines;
    $count++;

    $code = preg_replace('/^<\?php\s*/i', '', $code);
    $code = preg_replace('/\?>\s*$/', '', $code);

    $name = str_replace('.php', '', $file);
    $sep = str_repeat('-', 60);
    $body .= "// $sep\n// $name ($lines lines)\n// $sep\n\n";
    $body .= trim($code) . "\n\n";

    echo "  [OK] $file ($lines lines)\n";
}

file_put_contents($outFile, $header . $body);
$size = round(filesize($outFile) / 1024, 1);

echo "\n  Build complete! $count components, $totalLines lines\n";
echo "  Output: dist/h2php-core.php ($size KB)\n";