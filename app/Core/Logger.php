<?php

declare(strict_types=1);

namespace App\Core;

final class Logger
{
    public function __construct(private readonly \PDO $db)
    {
    }

    public function log(?int $userId, string $action, string $targetType = '', ?int $targetId = null): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO audit_logs (user_id, action, target_type, target_id, ip) VALUES (:user_id, :action, :target_type, :target_id, :ip)'
        );
        $stmt->execute([
            'user_id' => $userId,
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
        ]);
    }
}
