<?php

declare(strict_types=1);

namespace App\Controllers;

final class SystemController extends BaseController
{
    public function users(): void
    {
        $this->ensureAdmin();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf();
            $action = (string) post('action');

            if ($action === 'create_user') {
                $stmt = $this->db->prepare(
                    'INSERT INTO users (username, password_hash, role, real_name, community_id, is_active)
                    VALUES (:username, :password_hash, :role, :real_name, :community_id, 1)'
                );
                $stmt->execute([
                    'username' => trim((string) post('username')),
                    'password_hash' => password_hash((string) post('password'), PASSWORD_DEFAULT),
                    'role' => (string) post('role'),
                    'real_name' => trim((string) post('real_name')),
                    'community_id' => post('community_id') !== '' ? (int) post('community_id') : null,
                ]);

                $this->logger->log($this->user()['id'], '新增系统用户', 'users', (int) $this->db->lastInsertId());
                flash('用户已创建');
            }

            if ($action === 'toggle_user') {
                $id = (int) post('id');
                if ($id > 0) {
                    $stmt = $this->db->prepare(
                        'UPDATE users
                        SET is_active = CASE WHEN is_active = 1 THEN 0 ELSE 1 END
                        WHERE id = :id'
                    );
                    $stmt->execute(['id' => $id]);

                    $this->logger->log($this->user()['id'], '变更用户状态', 'users', $id);
                    flash('用户状态已更新');
                }
            }

            redirect('system_users');
        }

        $users = $this->db->query(
            'SELECT u.*, c.name AS community_name
            FROM users u
            LEFT JOIN communities c ON c.id = u.community_id
            ORDER BY u.id DESC'
        )->fetchAll();

        $communities = $this->db->query('SELECT * FROM communities ORDER BY id DESC')->fetchAll();

        $this->render('system/users', [
            'title' => '系统用户与权限',
            'users' => $users,
            'communities' => $communities,
        ]);
    }

    public function settings(): void
    {
        $this->ensureAdmin();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf();

            $lineYellow = (string) post('line_yellow', '40');
            $lineRed = (string) post('line_red', '70');
            $control = (string) post('control_fpg_target', '7.0');

            $this->upsertSetting('line_yellow', $lineYellow);
            $this->upsertSetting('line_red', $lineRed);
            $this->upsertSetting('control_fpg_target', $control);

            $this->logger->log($this->user()['id'], '更新系统阈值配置', 'settings');
            flash('系统配置已更新');
            redirect('system_settings');
        }

        $settings = [
            'line_yellow' => $this->setting('line_yellow', (string) $this->config['risk_defaults']['line_yellow']),
            'line_red' => $this->setting('line_red', (string) $this->config['risk_defaults']['line_red']),
            'control_fpg_target' => $this->setting('control_fpg_target', (string) $this->config['risk_defaults']['control_fpg_target']),
        ];

        $this->render('system/settings', [
            'title' => '系统配置与安全',
            'settings' => $settings,
        ]);
    }

    public function logs(): void
    {
        $this->ensureAdmin();

        $logs = $this->db->query(
            'SELECT l.*, u.real_name
            FROM audit_logs l
            LEFT JOIN users u ON u.id = l.user_id
            ORDER BY l.id DESC LIMIT 500'
        )->fetchAll();

        $backups = $this->db->query('SELECT * FROM data_backups ORDER BY id DESC LIMIT 50')->fetchAll();

        $this->render('system/logs', [
            'title' => '操作日志与备份',
            'logs' => $logs,
            'backups' => $backups,
        ]);
    }

    public function backup(): void
    {
        $this->ensureAdmin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('system_logs');
        }

        $this->verifyCsrf();

        $backupDir = $this->config['base_path'] . '/storage/backups';
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0777, true);
        }

        $fileName = 'backup_' . date('Ymd_His') . '.sql';
        $fullPath = $backupDir . '/' . $fileName;

        $sqlDump = $this->generateSqlDump();
        file_put_contents($fullPath, $sqlDump);

        $stmt = $this->db->prepare('INSERT INTO data_backups (file_path, created_by) VALUES (:file_path, :created_by)');
        $stmt->execute([
            'file_path' => 'storage/backups/' . $fileName,
            'created_by' => $this->user()['id'],
        ]);

        $this->logger->log($this->user()['id'], '创建数据备份', 'data_backups', (int) $this->db->lastInsertId());
        flash('数据备份完成: ' . $fileName);
        redirect('system_logs');
    }

    private function generateSqlDump(): string
    {
        $driver = (string) $this->db->getAttribute(\PDO::ATTR_DRIVER_NAME);
        return $driver === 'sqlite' ? $this->generateSqliteDump() : $this->generateMysqlDump();
    }

    private function generateMysqlDump(): string
    {
        $sqlDump = "-- 社区健康管理系统备份\n-- " . date('Y-m-d H:i:s') . "\n\nSET FOREIGN_KEY_CHECKS=0;\n\n";

        $tables = $this->db->query('SHOW TABLES')->fetchAll(\PDO::FETCH_NUM);
        foreach ($tables as $tableRow) {
            $table = (string) $tableRow[0];
            $create = $this->db->query('SHOW CREATE TABLE `' . $table . '`')->fetch(\PDO::FETCH_ASSOC);
            $createSql = $create['Create Table'] ?? '';

            $sqlDump .= 'DROP TABLE IF EXISTS `' . $table . '`;' . "\n";
            $sqlDump .= $createSql . ';' . "\n\n";
            $sqlDump .= $this->generateInsertDumpForTable($table, '`') . "\n";
        }

        $sqlDump .= "SET FOREIGN_KEY_CHECKS=1;\n";
        return $sqlDump;
    }

    private function generateSqliteDump(): string
    {
        $sqlDump = "-- 社区健康管理系统备份 (SQLite)\n-- " . date('Y-m-d H:i:s') . "\n\nPRAGMA foreign_keys=OFF;\nBEGIN TRANSACTION;\n\n";

        $tables = $this->db->query(
            "SELECT name, sql FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name"
        )->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($tables as $tableInfo) {
            $table = (string) ($tableInfo['name'] ?? '');
            $createSql = (string) ($tableInfo['sql'] ?? '');
            if ($table === '' || $createSql === '') {
                continue;
            }

            $escaped = str_replace('"', '""', $table);
            $sqlDump .= 'DROP TABLE IF EXISTS "' . $escaped . '";' . "\n";
            $sqlDump .= $createSql . ';' . "\n\n";
            $sqlDump .= $this->generateInsertDumpForTable($table, '"') . "\n";
        }

        $sqlDump .= "COMMIT;\nPRAGMA foreign_keys=ON;\n";
        return $sqlDump;
    }

    private function generateInsertDumpForTable(string $table, string $quote): string
    {
        $quoteIdent = static function (string $name) use ($quote): string {
            if ($quote === '"') {
                return '"' . str_replace('"', '""', $name) . '"';
            }
            return '`' . str_replace('`', '``', $name) . '`';
        };

        $rows = $this->db->query('SELECT * FROM ' . $quoteIdent($table))->fetchAll(\PDO::FETCH_ASSOC);
        if (!$rows) {
            return '';
        }

        $sql = '';
        foreach ($rows as $row) {
            $columns = array_map($quoteIdent, array_keys($row));
            $values = [];
            foreach ($row as $value) {
                $values[] = $value === null ? 'NULL' : $this->db->quote((string) $value);
            }
            $sql .= 'INSERT INTO ' . $quoteIdent($table) . ' (' . implode(',', $columns) . ') VALUES (' . implode(',', $values) . ');' . "\n";
        }

        return $sql;
    }

    private function ensureAdmin(): void
    {
        $this->requireLogin();
        $user = $this->user();
        if (($user['role'] ?? '') !== 'admin') {
            http_response_code(403);
            echo '仅管理员可访问';
            exit;
        }
    }
}
