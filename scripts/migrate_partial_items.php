<?php

declare(strict_types=1);

$config = require __DIR__ . '/../config/config.php';
$db = $config['db'];
$driver = strtolower((string) ($db['driver'] ?? 'mysql'));

if ($driver === 'sqlite') {
    $dbPath = (string) ($db['path'] ?? (dirname(__DIR__) . '/community_health.sqlite'));
    $dbDir = dirname($dbPath);
    if (!is_dir($dbDir)) {
        mkdir($dbDir, 0777, true);
    }

    $pdo = new PDO('sqlite:' . $dbPath, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON');

    $columns = $pdo->query('PRAGMA table_info(interventions)')->fetchAll();
    $hasAttachment = false;
    foreach ($columns as $column) {
        if (($column['name'] ?? '') === 'attachment_path') {
            $hasAttachment = true;
            break;
        }
    }
    if (!$hasAttachment) {
        $pdo->exec('ALTER TABLE interventions ADD COLUMN attachment_path TEXT NULL');
    }

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS notifications (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            resident_id INTEGER NULL,
            type TEXT NOT NULL,
            title TEXT NOT NULL,
            content TEXT NOT NULL,
            status TEXT NOT NULL DEFAULT "unread",
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (resident_id) REFERENCES residents(id) ON DELETE SET NULL
        )'
    );
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_notification_user_status ON notifications(user_id, status, created_at)');

    echo "迁移完成：SQLite 通知表与附件字段已就绪。\n";
    exit;
}

$dsn = sprintf(
    'mysql:host=%s;port=%s;dbname=%s;charset=%s',
    $db['host'],
    $db['port'],
    $db['dbname'],
    $db['charset']
);

$pdo = new PDO($dsn, $db['user'], $db['pass'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$checkStmt = $pdo->prepare(
    'SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = :db
      AND TABLE_NAME = "interventions"
      AND COLUMN_NAME = "attachment_path"'
);
$checkStmt->execute(['db' => $db['dbname']]);
if ((int) $checkStmt->fetchColumn() === 0) {
    $pdo->exec('ALTER TABLE interventions ADD COLUMN attachment_path VARCHAR(255) NULL AFTER feedback');
}

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS notifications (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNSIGNED NOT NULL,
        resident_id INT UNSIGNED NULL,
        type VARCHAR(50) NOT NULL,
        title VARCHAR(120) NOT NULL,
        content VARCHAR(255) NOT NULL,
        status ENUM("unread", "read") NOT NULL DEFAULT "unread",
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_notification_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        CONSTRAINT fk_notification_resident FOREIGN KEY (resident_id) REFERENCES residents(id) ON DELETE SET NULL,
        INDEX idx_notification_user_status (user_id, status, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
);

echo "迁移完成：已补充 notifications 表与 interventions.attachment_path 字段。\n";
