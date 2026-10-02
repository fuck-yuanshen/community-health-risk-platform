<?php

declare(strict_types=1);

namespace App\Controllers;

final class AlertController extends BaseController
{
    public function index(): void
    {
        $this->requirePermission('alerts');
        $scope = $this->scopeCondition('r');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf();
            $action = (string) post('action');

            if ($action === 'handle_alert') {
                $alertId = (int) post('alert_id');
                $this->handleAlert($alertId);
                redirect('alerts');
            }

            if ($action === 'one_click_alert_scan') {
                $this->scanAndGenerateAlerts();
                redirect('alerts');
            }
        }

        $line = trim((string) query('line', ''));

        $sql = 'SELECT a.*, r.name AS resident_name, ra.score, ra.management_level
            FROM alerts a
            JOIN residents r ON r.id = a.resident_id
            LEFT JOIN risk_assessments ra ON ra.id = a.risk_assessment_id
            WHERE 1=1' . $scope['sql'];

        $params = $scope['params'];
        if (in_array($line, ['yellow', 'red'], true)) {
            $sql .= ' AND a.alert_line = :line';
            $params['line'] = $line;
        }

        $sql .= ' ORDER BY a.status = "pending" DESC, a.id DESC LIMIT 300';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $alerts = $stmt->fetchAll();

        $taskSql = 'SELECT t.*, r.name AS resident_name, u.real_name AS assignee_name
            FROM tasks t
            JOIN residents r ON r.id = t.resident_id
            LEFT JOIN users u ON u.id = t.assignee_id
            WHERE t.status = "pending"' . $scope['sql'] . '
            ORDER BY t.due_date ASC LIMIT 200';
        $stmt = $this->db->prepare($taskSql);
        $stmt->execute($scope['params']);
        $tasks = $stmt->fetchAll();

        $this->render('alerts/index', [
            'title' => '动态预警与提醒',
            'alerts' => $alerts,
            'tasks' => $tasks,
            'line' => $line,
        ]);
    }

    private function handleAlert(int $alertId): void
    {
        if ($alertId <= 0) {
            return;
        }

        $scope = $this->scopeCondition('r');
        $sql = 'SELECT a.id FROM alerts a
            JOIN residents r ON r.id = a.resident_id
            WHERE a.id = :id' . $scope['sql'] . ' LIMIT 1';
        $params = array_merge(['id' => $alertId], $scope['params']);
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $id = $stmt->fetchColumn();

        if (!$id) {
            return;
        }

        $user = $this->user();
        $stmt = $this->db->prepare(
            'UPDATE alerts SET status = "handled", handled_by = :handled_by, handled_at = :handled_at WHERE id = :id'
        );
        $stmt->execute([
            'id' => $alertId,
            'handled_by' => $user['id'],
            'handled_at' => date('Y-m-d H:i:s'),
        ]);

        $this->logger->log($user['id'], '处理预警', 'alerts', $alertId);
        flash('预警已处理');
    }

    private function scanAndGenerateAlerts(): void
    {
        $scope = $this->scopeCondition('r');
        $sql = 'SELECT ra.id AS risk_id, ra.resident_id, ra.line_color, ra.score
            FROM risk_assessments ra
            JOIN (
                SELECT resident_id, MAX(id) AS max_id
                FROM risk_assessments
                GROUP BY resident_id
            ) latest ON latest.max_id = ra.id
            JOIN residents r ON r.id = ra.resident_id
            LEFT JOIN alerts a ON a.resident_id = ra.resident_id AND a.status = "pending"
            WHERE ra.line_color IN ("yellow", "red")
              AND a.id IS NULL' . $scope['sql'] . '
            LIMIT 300';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($scope['params']);
        $rows = $stmt->fetchAll();

        $count = 0;
        foreach ($rows as $row) {
            $title = $row['line_color'] === 'red' ? '红线风险预警' : '黄线风险预警';
            $description = '一键预警扫描触发，风险分值 ' . $row['score'] . '。';

            $insert = $this->db->prepare(
                'INSERT INTO alerts (resident_id, risk_assessment_id, alert_line, title, description, status)
                VALUES (:resident_id, :risk_assessment_id, :alert_line, :title, :description, "pending")'
            );
            $insert->execute([
                'resident_id' => $row['resident_id'],
                'risk_assessment_id' => $row['risk_id'],
                'alert_line' => $row['line_color'],
                'title' => $title,
                'description' => $description,
            ]);

            $dueDate = date('Y-m-d', strtotime($row['line_color'] === 'red' ? '+14 days' : '+30 days'));
            $assigneeId = $this->findAssigneeByResident((int) $row['resident_id']);
            $this->createTaskAndNotify(
                (int) $row['resident_id'],
                '一键预警随访',
                $dueDate,
                $assigneeId,
                '由一键预警扫描自动生成'
            );
            $this->pushNotification($assigneeId, (int) $row['resident_id'], 'alert', $title, $description);
            $_SESSION['_auto_alert_popup'][] = '居民ID ' . $row['resident_id'] . '：' . $title . '（分值 ' . $row['score'] . '）';
            $count++;
        }

        $user = $this->user();
        $this->logger->log($user['id'], '执行一键预警扫描', 'alerts');
        flash('一键预警扫描完成，本次新增预警 ' . $count . ' 条');
    }

    private function findAssigneeByResident(int $residentId): ?int
    {
        $stmt = $this->db->prepare('SELECT g.responsible_user_id
            FROM residents r
            JOIN families f ON f.id = r.family_id
            JOIN buildings b ON b.id = f.building_id
            JOIN grids g ON g.id = b.grid_id
            WHERE r.id = :id');
        $stmt->execute(['id' => $residentId]);
        $id = $stmt->fetchColumn();

        return $id !== false ? (int) $id : null;
    }
}
