<?php

declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');

$basePath = dirname(__DIR__);
$lockPath = $basePath . '/storage/install.lock';
$tokenRequired = (string) getenv('INIT_WEB_TOKEN');
$token = (string) ($_GET['token'] ?? '');
$force = (string) ($_GET['force'] ?? '') === '1';

function h(string $text): string
{
    return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function render(string $title, string $body, int $code = 200): never
{
    http_response_code($code);
    echo '<!doctype html><html lang="zh-CN"><head><meta charset="UTF-8"><title>' . h($title) . '</title>';
    echo '<style>body{font-family:Arial,Microsoft YaHei,sans-serif;background:#f7f9fc;padding:20px;color:#1f2937}'
        . '.card{max-width:900px;margin:0 auto;background:#fff;border-radius:12px;padding:18px;box-shadow:0 8px 24px rgba(0,0,0,.08)}'
        . 'code,pre{background:#f3f4f6;border-radius:8px;padding:10px;display:block;white-space:pre-wrap;word-break:break-word}'
        . '.ok{color:#166534}.err{color:#991b1b}.hint{color:#6b7280}</style></head><body><div class="card">';
    echo '<h2>' . h($title) . '</h2>';
    echo $body;
    echo '</div></body></html>';
    exit;
}

if ($tokenRequired !== '' && !hash_equals($tokenRequired, $token)) {
    render(
        '初始化已保护',
        '<p class="err">缺少或错误的 token。</p>'
        . '<p class="hint">请使用：<code>/init_db_web.php?token=你的token</code></p>',
        403
    );
}

if (is_file($lockPath) && !$force) {
    $lockInfo = (string) @file_get_contents($lockPath);
    render(
        '数据库已初始化',
        '<p class="ok">检测到锁文件，已阻止重复初始化。</p>'
        . '<p class="hint">如需强制重跑：<code>/init_db_web.php?force=1'
        . ($token !== '' ? '&token=' . urlencode($token) : '')
        . '</code></p>'
        . '<p class="hint">锁文件内容：</p><pre>' . h($lockInfo) . '</pre>'
    );
}

$logs = [];

try {
    if (!is_dir($basePath . '/storage')) {
        mkdir($basePath . '/storage', 0777, true);
    }

    ob_start();
    require $basePath . '/scripts/init_db.php';
    $logs[] = "init_db.php output:\n" . trim((string) ob_get_clean());

    ob_start();
    require $basePath . '/scripts/migrate_partial_items.php';
    $logs[] = "migrate_partial_items.php output:\n" . trim((string) ob_get_clean());

    $meta = [
        'time' => date('Y-m-d H:i:s'),
        'ip' => (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'),
        'ua' => (string) ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown'),
    ];
    @file_put_contents($lockPath, json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

    render(
        '初始化完成',
        '<p class="ok">数据库初始化和迁移已执行完成。</p>'
        . '<p>账号已初始化，密码来自 HEALTH_* 环境变量。</p>'
        . '<p>执行日志：</p><pre>' . h(implode("\n\n", $logs)) . '</pre>'
        . '<p class="hint">建议初始化后删除本文件：<code>public/init_db_web.php</code></p>'
    );
} catch (Throwable $e) {
    $logText = $logs ? implode("\n\n", $logs) : '(no logs)';
    render(
        '初始化失败',
        '<p class="err">' . h($e->getMessage()) . '</p>'
        . '<p>已执行日志：</p><pre>' . h($logText) . '</pre>',
        500
    );
}
