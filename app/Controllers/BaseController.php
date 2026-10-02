<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Logger;
use App\Core\View;
use PDO;

abstract class BaseController
{
    public function __construct(
        protected readonly PDO $db,
        protected readonly Auth $auth,
        protected readonly Logger $logger,
        protected readonly array $config
    ) {
    }

    protected function user(): ?array
    {
        return $this->auth->user();
    }

    protected function requireLogin(): void
    {
        if (!$this->auth->check()) {
            redirect('login');
        }
    }

    protected function requirePermission(string $module): void
    {
        $this->requireLogin();
        if (!$this->auth->can($this->config, $module)) {
            http_response_code(403);
            echo '无权限访问';
            exit;
        }
    }

    protected function scopeCondition(string $alias = 'r'): array
    {
        $user = $this->user();
        if (!$user || $user['role'] === 'admin' || !$user['community_id']) {
            return ['sql' => '', 'params' => []];
        }

        return [
            'sql' => " AND {$alias}.community_id = :community_id",
            'params' => ['community_id' => (int) $user['community_id']],
        ];
    }

    protected function render(string $view, array $data = []): void
    {
        $notifications = [];
        $notificationUnread = 0;
        $autoAlertPopup = $_SESSION['_auto_alert_popup'] ?? [];
        unset($_SESSION['_auto_alert_popup']);

        $user = $this->user();
        if ($user) {
            try {
                $stmt = $this->db->prepare(
                    'SELECT n.*, r.name AS resident_name
                    FROM notifications n
                    LEFT JOIN residents r ON r.id = n.resident_id
                    WHERE n.user_id = :user_id
                    ORDER BY n.status = "unread" DESC, n.id DESC
                    LIMIT 20'
                );
                $stmt->execute(['user_id' => $user['id']]);
                $notifications = $stmt->fetchAll();

                $stmt = $this->db->prepare(
                    'SELECT COUNT(*) FROM notifications WHERE user_id = :user_id AND status = "unread"'
                );
                $stmt->execute(['user_id' => $user['id']]);
                $notificationUnread = (int) $stmt->fetchColumn();
            } catch (\Throwable) {
                $notifications = [];
                $notificationUnread = 0;
            }
        }

        View::render($view, array_merge($data, [
            'authUser' => $user,
            'appName' => $this->config['app_name'],
            'csrfToken' => Csrf::token(),
            'flash' => flash(),
            'notifications' => $notifications,
            'notificationUnread' => $notificationUnread,
            'autoAlertPopup' => $autoAlertPopup,
        ]));
    }

    protected function verifyCsrf(): void
    {
        if (!Csrf::validate(post('_csrf'))) {
            http_response_code(419);
            echo 'CSRF 校验失败';
            exit;
        }
    }

    protected function setting(string $name, mixed $default = null): mixed
    {
        $stmt = $this->db->prepare('SELECT value FROM settings WHERE name = :name LIMIT 1');
        $stmt->execute(['name' => $name]);
        $v = $stmt->fetchColumn();
        return $v !== false ? $v : $default;
    }

    protected function upsertSetting(string $name, string $value): void
    {
        $driver = (string) $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $stmt = $this->db->prepare(
                'INSERT INTO settings (name, value, updated_at)
                VALUES (:name, :value, CURRENT_TIMESTAMP)
                ON CONFLICT(name) DO UPDATE SET
                    value = excluded.value,
                    updated_at = CURRENT_TIMESTAMP'
            );
        } else {
            $stmt = $this->db->prepare(
                'INSERT INTO settings (name, value) VALUES (:name, :value)
                ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = CURRENT_TIMESTAMP'
            );
        }
        $stmt->execute(['name' => $name, 'value' => $value]);
    }

    protected function pushNotification(?int $userId, ?int $residentId, string $type, string $title, string $content): void
    {
        if (!$userId) {
            return;
        }

        try {
            $stmt = $this->db->prepare(
                'INSERT INTO notifications (user_id, resident_id, type, title, content, status)
                VALUES (:user_id, :resident_id, :type, :title, :content, "unread")'
            );
            $stmt->execute([
                'user_id' => $userId,
                'resident_id' => $residentId,
                'type' => $type,
                'title' => $title,
                'content' => substr($content, 0, 250),
            ]);
        } catch (\Throwable) {
            // 兼容未迁移旧库时的降级运行
        }
    }

    protected function createTaskAndNotify(
        int $residentId,
        string $taskType,
        string $dueDate,
        ?int $assigneeId,
        string $note = ''
    ): void {
        $stmt = $this->db->prepare(
            'INSERT INTO tasks (resident_id, task_type, due_date, status, assignee_id, note)
            VALUES (:resident_id, :task_type, :due_date, "pending", :assignee_id, :note)'
        );
        $stmt->execute([
            'resident_id' => $residentId,
            'task_type' => $taskType,
            'due_date' => $dueDate,
            'assignee_id' => $assigneeId,
            'note' => $note,
        ]);

        $this->pushNotification(
            $assigneeId,
            $residentId,
            'task',
            $taskType,
            $taskType . '，到期：' . $dueDate . ($note !== '' ? '；' . $note : '')
        );
    }
}
