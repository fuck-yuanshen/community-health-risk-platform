<?php

declare(strict_types=1);

namespace App\Controllers;

final class NotificationController extends BaseController
{
    public function markAllRead(): void
    {
        $this->requireLogin();
        $user = $this->user();

        try {
            $stmt = $this->db->prepare('UPDATE notifications SET status = "read" WHERE user_id = :user_id');
            $stmt->execute(['user_id' => $user['id']]);
        } catch (\Throwable) {
            // 兼容旧库未迁移场景
        }

        flash('消息已全部标记为已读');
        $back = $_SERVER['HTTP_REFERER'] ?? '/index.php?r=dashboard';
        header('Location: ' . $back);
        exit;
    }
}
