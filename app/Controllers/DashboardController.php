<?php

declare(strict_types=1);

namespace App\Controllers;

final class DashboardController extends BaseController
{
    public function index(): void
    {
        $this->requirePermission('dashboard');

        $scope = $this->scopeCondition('r');
        $today = date('Y-m-d');
        $weekEnd = date('Y-m-d', strtotime('+7 days'));

        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM residents r WHERE 1=1' . $scope['sql']
        );
        $stmt->execute($scope['params']);
        $totalResidents = (int) $stmt->fetchColumn();

        $familySql = 'SELECT COUNT(*) FROM families f
            JOIN buildings b ON b.id = f.building_id
            JOIN grids g ON g.id = b.grid_id
            WHERE 1=1';
        $familyParams = [];
        $user = $this->user();
        if ($user && $user['role'] !== 'admin' && $user['community_id']) {
            $familySql .= ' AND g.community_id = :community_id';
            $familyParams['community_id'] = (int) $user['community_id'];
        }
        $stmt = $this->db->prepare($familySql);
        $stmt->execute($familyParams);
        $totalFamilies = (int) $stmt->fetchColumn();

        $riskSql = 'SELECT ra.line_color, COUNT(*) AS cnt
            FROM risk_assessments ra
            JOIN (
                SELECT resident_id, MAX(id) AS max_id
                FROM risk_assessments
                GROUP BY resident_id
            ) latest ON latest.max_id = ra.id
            JOIN residents r ON r.id = ra.resident_id
            WHERE 1=1' . $scope['sql'] . '
            GROUP BY ra.line_color';
        $stmt = $this->db->prepare($riskSql);
        $stmt->execute($scope['params']);
        $riskDist = ['green' => 0, 'yellow' => 0, 'red' => 0];
        foreach ($stmt->fetchAll() as $row) {
            $riskDist[$row['line_color']] = (int) $row['cnt'];
        }

        $alertSql = 'SELECT COUNT(*)
            FROM alerts a
            JOIN residents r ON r.id = a.resident_id
            WHERE a.status = "pending"' . $scope['sql'];
        $stmt = $this->db->prepare($alertSql);
        $stmt->execute($scope['params']);
        $pendingAlerts = (int) $stmt->fetchColumn();

        $followSql = 'SELECT COUNT(*)
            FROM followups f
            JOIN residents r ON r.id = f.resident_id
            WHERE f.status <> "done" AND f.due_date < :today' . $scope['sql'];
        $stmt = $this->db->prepare($followSql);
        $stmt->execute(array_merge($scope['params'], ['today' => $today]));
        $overdueFollowups = (int) $stmt->fetchColumn();

        $todayAlertSql = 'SELECT COUNT(*)
            FROM alerts a
            JOIN residents r ON r.id = a.resident_id
            WHERE DATE(a.created_at) = :today' . $scope['sql'];
        $stmt = $this->db->prepare($todayAlertSql);
        $stmt->execute(array_merge($scope['params'], ['today' => $today]));
        $todayAlerts = (int) $stmt->fetchColumn();

        $taskSql = 'SELECT t.*, r.name AS resident_name, u.real_name AS assignee_name
            FROM tasks t
            JOIN residents r ON r.id = t.resident_id
            LEFT JOIN users u ON u.id = t.assignee_id
            WHERE t.status = "pending" AND t.due_date <= :week_end' . $scope['sql'] . '
            ORDER BY t.due_date ASC
            LIMIT 20';
        $stmt = $this->db->prepare($taskSql);
        $stmt->execute(array_merge($scope['params'], ['week_end' => $weekEnd]));
        $tasks = $stmt->fetchAll();

        $this->render('dashboard/index', [
            'title' => '工作台',
            'totalResidents' => $totalResidents,
            'totalFamilies' => $totalFamilies,
            'riskDist' => $riskDist,
            'pendingAlerts' => $pendingAlerts,
            'overdueFollowups' => $overdueFollowups,
            'todayAlerts' => $todayAlerts,
            'tasks' => $tasks,
        ]);
    }
}
