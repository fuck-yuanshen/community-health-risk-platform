<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

final class Auth
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function attempt(string $username, string $password): bool
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE username = :username AND is_active = 1 LIMIT 1');
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            return false;
        }

        $_SESSION['user'] = [
            'id' => (int) $user['id'],
            'username' => $user['username'],
            'real_name' => $user['real_name'],
            'role' => $user['role'],
            'community_id' => $user['community_id'] ? (int) $user['community_id'] : null,
        ];

        return true;
    }

    public function logout(): void
    {
        unset($_SESSION['user']);
    }

    public function user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    public function check(): bool
    {
        return isset($_SESSION['user']);
    }

    public function can(array $config, string $module): bool
    {
        $user = $this->user();
        if (!$user) {
            return false;
        }

        $role = $user['role'] ?? '';
        $allowed = $config['permissions'][$role] ?? [];

        return in_array('*', $allowed, true) || in_array($module, $allowed, true);
    }
}
