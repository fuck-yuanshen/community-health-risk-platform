<?php

declare(strict_types=1);

namespace App\Controllers;

final class ScreenController extends BaseController
{
    public function index(): void
    {
        $this->requirePermission('screen');

        $scope = $this->scopeCondition('r');
        $today = date('Y-m-d');

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
        $risk = ['green' => 0, 'yellow' => 0, 'red' => 0];
        foreach ($stmt->fetchAll() as $row) {
            $risk[$row['line_color']] = (int) $row['cnt'];
        }

        $todaySql = 'SELECT COUNT(*) FROM alerts a
            JOIN residents r ON r.id = a.resident_id
            WHERE DATE(a.created_at) = :today' . $scope['sql'];
        $stmt = $this->db->prepare($todaySql);
        $stmt->execute(array_merge($scope['params'], ['today' => $today]));
        $todayAlerts = (int) $stmt->fetchColumn();

        $highRiskSql = 'SELECT COUNT(*)
            FROM risk_assessments ra
            JOIN (
                SELECT resident_id, MAX(id) AS max_id
                FROM risk_assessments
                GROUP BY resident_id
            ) latest ON latest.max_id = ra.id
            JOIN residents r ON r.id = ra.resident_id
            WHERE ra.line_color = "red"' . $scope['sql'];
        $stmt = $this->db->prepare($highRiskSql);
        $stmt->execute($scope['params']);
        $highRisk = (int) $stmt->fetchColumn();

        $followupSql = 'SELECT
                SUM(CASE WHEN f.status = "done" THEN 1 ELSE 0 END) AS done_cnt,
                COUNT(*) AS total_cnt
            FROM followups f
            JOIN residents r ON r.id = f.resident_id
            WHERE 1=1' . $scope['sql'];
        $stmt = $this->db->prepare($followupSql);
        $stmt->execute($scope['params']);
        $follow = $stmt->fetch() ?: ['done_cnt' => 0, 'total_cnt' => 0];
        $followupRate = ((int) $follow['total_cnt']) > 0 ? round((int) $follow['done_cnt'] / (int) $follow['total_cnt'] * 100, 2) : 0;

        $interventionSql = 'SELECT COUNT(DISTINCT i.resident_id)
            FROM interventions i
            JOIN residents r ON r.id = i.resident_id
            WHERE 1=1' . $scope['sql'];
        $stmt = $this->db->prepare($interventionSql);
        $stmt->execute($scope['params']);
        $coveredResidents = (int) $stmt->fetchColumn();

        $totalSql = 'SELECT COUNT(*) FROM residents r WHERE r.archived_at IS NULL' . $scope['sql'];
        $stmt = $this->db->prepare($totalSql);
        $stmt->execute($scope['params']);
        $totalResidents = max((int) $stmt->fetchColumn(), 1);

        $coverageRate = round($coveredResidents / $totalResidents * 100, 2);

        $pendingAlertSql = 'SELECT COUNT(*)
            FROM alerts a
            JOIN residents r ON r.id = a.resident_id
            WHERE a.status = "pending"' . $scope['sql'];
        $stmt = $this->db->prepare($pendingAlertSql);
        $stmt->execute($scope['params']);
        $pendingAlerts = (int) $stmt->fetchColumn();

        $overdueSql = 'SELECT COUNT(*)
            FROM followups f
            JOIN residents r ON r.id = f.resident_id
            WHERE f.status <> "done"
              AND f.due_date < :today' . $scope['sql'];
        $stmt = $this->db->prepare($overdueSql);
        $stmt->execute(array_merge($scope['params'], ['today' => $today]));
        $overdueFollowups = (int) $stmt->fetchColumn();

        $issues = $this->identifyIssues($risk, $todayAlerts, $followupRate, $coverageRate, $pendingAlerts, $overdueFollowups);

        $mapSql = 'SELECT c.name AS community_name, g.name AS grid_name, b.name AS building_name,
                f.address AS family_address, r.name AS resident_name,
                COALESCE(ra.line_color, "green") AS line_color
            FROM residents r
            JOIN families f ON f.id = r.family_id
            JOIN buildings b ON b.id = f.building_id
            JOIN grids g ON g.id = b.grid_id
            JOIN communities c ON c.id = g.community_id
            LEFT JOIN (
                SELECT ra1.*
                FROM risk_assessments ra1
                JOIN (
                    SELECT resident_id, MAX(id) AS max_id
                    FROM risk_assessments
                    GROUP BY resident_id
                ) latest ON latest.max_id = ra1.id
            ) ra ON ra.resident_id = r.id
            WHERE 1=1' . $scope['sql'] . '
            ORDER BY c.id, g.id, b.id, f.id
            LIMIT 300';
        $stmt = $this->db->prepare($mapSql);
        $stmt->execute($scope['params']);
        $mapItems = $stmt->fetchAll();

        $this->render('screen/index', [
            'title' => '决策分析大屏',
            'risk' => $risk,
            'todayAlerts' => $todayAlerts,
            'highRisk' => $highRisk,
            'followupRate' => $followupRate,
            'coverageRate' => $coverageRate,
            'issues' => $issues,
            'pendingAlerts' => $pendingAlerts,
            'overdueFollowups' => $overdueFollowups,
            'mapItems' => $mapItems,
        ]);
    }

    private function identifyIssues(
        array $risk,
        int $todayAlerts,
        float $followupRate,
        float $coverageRate,
        int $pendingAlerts,
        int $overdueFollowups
    ): array {
        $issues = [];
        $total = max(1, (int) ($risk['green'] + $risk['yellow'] + $risk['red']));
        $redRatio = round($risk['red'] / $total * 100, 2);

        if ($redRatio >= 20) {
            $issues[] = '红线人群占比 ' . $redRatio . '%，建议强化重点随访和干预。';
        }
        if ($todayAlerts >= 5) {
            $issues[] = '今日新增预警 ' . $todayAlerts . ' 条，建议启动应急处置。';
        }
        if ($pendingAlerts >= 8) {
            $issues[] = '待处理预警积压 ' . $pendingAlerts . ' 条，存在处置滞后风险。';
        }
        if ($followupRate < 70) {
            $issues[] = '随访完成率仅 ' . $followupRate . '%，建议优化随访排班。';
        }
        if ($overdueFollowups > 0) {
            $issues[] = '逾期随访 ' . $overdueFollowups . ' 条，建议优先清理。';
        }
        if ($coverageRate < 75) {
            $issues[] = '干预覆盖率仅 ' . $coverageRate . '%，建议扩大纳管范围。';
        }

        if (!$issues) {
            $issues[] = '当前未识别到突出风险问题，可维持现有管理节奏。';
        }

        return $issues;
    }
}
