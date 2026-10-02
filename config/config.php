<?php

declare(strict_types=1);

$basePath = realpath(__DIR__ . '/..');
if ($basePath === false) {
    $basePath = dirname(__DIR__);
}

return [
    'app_name' => "\u{793E}\u{533A}\u{5065}\u{5EB7}\u{7BA1}\u{7406}\u{5E73}\u{53F0}",
    'timezone' => 'Asia/Shanghai',
    'base_path' => $basePath,
    'db' => [
        'driver' => getenv('DB_DRIVER') ?: 'sqlite',
        'path' => getenv('DB_PATH') ?: ($basePath . '/community_health.sqlite'),
        'host' => getenv('DB_HOST') ?: '127.0.0.1',
        'port' => getenv('DB_PORT') ?: '3306',
        'dbname' => getenv('DB_NAME') ?: 'community_health_platform',
        'user' => getenv('DB_USER') ?: 'root',
        'pass' => getenv('DB_PASS') ?: 'admin',
        'charset' => 'utf8mb4',
    ],
    'security' => [
        'app_key' => getenv('APP_KEY') ?: 'please-change-this-key-32chars-minimum',
    ],
    'permissions' => [
        'admin' => ['*'],
        'doctor' => [
            'dashboard', 'grids', 'families', 'residents', 'measurements', 'risks', 'alerts',
            'interventions', 'followups', 'referrals', 'reports', 'screen',
        ],
        'public_health' => [
            'dashboard', 'residents', 'measurements', 'risks', 'alerts',
            'followups', 'reports', 'screen',
        ],
        'nurse' => [
            'dashboard', 'residents', 'measurements', 'alerts', 'followups',
        ],
    ],
    'risk_defaults' => [
        'line_yellow' => 40,
        'line_red' => 70,
        'control_fpg_target' => 7.0,
    ],
];
