<?php

declare(strict_types=1);

namespace App\Controllers;

final class ReportController extends BaseController
{
    public function index(): void
    {
        $this->requirePermission('reports');

        $scope = $this->scopeCondition('r');

        $metrics = $this->calculateMetrics($scope['sql'], $scope['params']);
        $communityCompare = $this->communityCompare();
        $trend = $this->alertTrend($scope['sql'], $scope['params']);
        $nationalTemplate = $this->nationalTemplate($metrics);
        $interventionEffect = $this->interventionEffect($scope['sql'], $scope['params']);
        $doctorPerformance = $this->doctorPerformance();

        $export = (string) query('export', '');
        if ($export === 'excel') {
            $this->exportCsv($metrics, $communityCompare, $nationalTemplate, $interventionEffect, $doctorPerformance);
            return;
        }
        if ($export === 'pdf') {
            $this->renderPdfLike($metrics, $communityCompare, $nationalTemplate, $interventionEffect, $doctorPerformance);
            return;
        }

        $this->render('reports/index', [
            'title' => '公卫考核统计与报表',
            'metrics' => $metrics,
            'communityCompare' => $communityCompare,
            'trend' => $trend,
            'nationalTemplate' => $nationalTemplate,
            'interventionEffect' => $interventionEffect,
            'doctorPerformance' => $doctorPerformance,
        ]);
    }

    private function calculateMetrics(string $scopeSql, array $scopeParams): array
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM residents r WHERE r.archived_at IS NULL' . $scopeSql);
        $stmt->execute($scopeParams);
        $totalResidents = max((int) $stmt->fetchColumn(), 1);

        $stmt = $this->db->prepare('SELECT COUNT(DISTINCT m.resident_id)
            FROM measurements m
            JOIN residents r ON r.id = m.resident_id
            WHERE 1=1' . $scopeSql);
        $stmt->execute($scopeParams);
        $screened = (int) $stmt->fetchColumn();

        $stmt = $this->db->prepare('SELECT COUNT(DISTINCT i.resident_id)
            FROM interventions i
            JOIN residents r ON r.id = i.resident_id
            WHERE 1=1' . $scopeSql);
        $stmt->execute($scopeParams);
        $managed = (int) $stmt->fetchColumn();

        $stmt = $this->db->prepare('SELECT COUNT(DISTINCT f.resident_id)
            FROM followups f
            JOIN residents r ON r.id = f.resident_id
            WHERE f.status = "done"' . $scopeSql);
        $stmt->execute($scopeParams);
        $followed = (int) $stmt->fetchColumn();

        $controlTarget = (float) $this->setting('control_fpg_target', $this->config['risk_defaults']['control_fpg_target']);

        $controlSql = 'SELECT COUNT(*)
            FROM (
                SELECT m.resident_id, m.fasting_glucose
                FROM measurements m
                JOIN (
                    SELECT resident_id, MAX(id) AS max_id
                    FROM measurements
                    GROUP BY resident_id
                ) latest ON latest.max_id = m.id
                JOIN residents r ON r.id = m.resident_id
                WHERE m.fasting_glucose > 0' . $scopeSql . '
            ) x
            WHERE x.fasting_glucose < :target';
        $stmt = $this->db->prepare($controlSql);
        $stmt->execute(array_merge($scopeParams, ['target' => $controlTarget]));
        $controlled = (int) $stmt->fetchColumn();

        $measuredSql = 'SELECT COUNT(*) FROM (
                SELECT m.resident_id
                FROM measurements m
                JOIN (
                    SELECT resident_id, MAX(id) AS max_id
                    FROM measurements
                    GROUP BY resident_id
                ) latest ON latest.max_id = m.id
                JOIN residents r ON r.id = m.resident_id
                WHERE m.fasting_glucose > 0' . $scopeSql . '
            ) y';
        $stmt = $this->db->prepare($measuredSql);
        $stmt->execute($scopeParams);
        $measuredTotal = max((int) $stmt->fetchColumn(), 1);

        $riskSql = 'SELECT ra.line_color, COUNT(*) AS cnt
            FROM risk_assessments ra
            JOIN (
                SELECT resident_id, MAX(id) AS max_id
                FROM risk_assessments
                GROUP BY resident_id
            ) latest ON latest.max_id = ra.id
            JOIN residents r ON r.id = ra.resident_id
            WHERE 1=1' . $scopeSql . '
            GROUP BY ra.line_color';
        $stmt = $this->db->prepare($riskSql);
        $stmt->execute($scopeParams);
        $riskDist = ['green' => 0, 'yellow' => 0, 'red' => 0];
        foreach ($stmt->fetchAll() as $row) {
            $riskDist[$row['line_color']] = (int) $row['cnt'];
        }

        $stmt = $this->db->prepare('SELECT COUNT(*) FROM alerts a JOIN residents r ON r.id = a.resident_id WHERE 1=1' . $scopeSql);
        $stmt->execute($scopeParams);
        $totalAlerts = max((int) $stmt->fetchColumn(), 1);

        $stmt = $this->db->prepare('SELECT COUNT(*) FROM alerts a JOIN residents r ON r.id = a.resident_id WHERE a.status = "handled"' . $scopeSql);
        $stmt->execute($scopeParams);
        $handledAlerts = (int) $stmt->fetchColumn();

        return [
            'screening_rate' => round($screened / $totalResidents * 100, 2),
            'management_rate' => round($managed / $totalResidents * 100, 2),
            'followup_rate' => round($followed / $totalResidents * 100, 2),
            'control_rate' => round($controlled / $measuredTotal * 100, 2),
            'alert_handle_rate' => round($handledAlerts / $totalAlerts * 100, 2),
            'total_residents' => $totalResidents,
            'screened_count' => $screened,
            'managed_count' => $managed,
            'followed_count' => $followed,
            'controlled_count' => $controlled,
            'measured_total' => $measuredTotal,
            'handled_alert_count' => $handledAlerts,
            'total_alert_count' => $totalAlerts,
            'risk_dist' => $riskDist,
        ];
    }

    private function communityCompare(): array
    {
        $user = $this->user();

        $sql = 'SELECT c.name,
                COUNT(DISTINCT r.id) AS resident_count,
                SUM(CASE WHEN latest.line_color = "red" THEN 1 ELSE 0 END) AS red_count
            FROM communities c
            LEFT JOIN grids g ON g.community_id = c.id
            LEFT JOIN buildings b ON b.grid_id = g.id
            LEFT JOIN families f ON f.building_id = b.id
            LEFT JOIN residents r ON r.family_id = f.id
            LEFT JOIN (
                SELECT ra1.*
                FROM risk_assessments ra1
                JOIN (
                    SELECT resident_id, MAX(id) AS max_id
                    FROM risk_assessments
                    GROUP BY resident_id
                ) t ON t.max_id = ra1.id
            ) latest ON latest.resident_id = r.id
            WHERE 1=1';
        $params = [];

        if ($user['role'] !== 'admin' && $user['community_id']) {
            $sql .= ' AND c.id = :community_id';
            $params['community_id'] = (int) $user['community_id'];
        }

        $sql .= ' GROUP BY c.id ORDER BY resident_count DESC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    private function alertTrend(string $scopeSql, array $scopeParams): array
    {
        $startDate = date('Y-m-d', strtotime('-14 days'));
        $sql = 'SELECT DATE(a.created_at) AS d, COUNT(*) AS cnt
            FROM alerts a
            JOIN residents r ON r.id = a.resident_id
            WHERE DATE(a.created_at) >= :start_date' . $scopeSql . '
            GROUP BY DATE(a.created_at)
            ORDER BY d ASC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_merge($scopeParams, ['start_date' => $startDate]));

        $rows = $stmt->fetchAll();
        $labels = [];
        $data = [];
        foreach ($rows as $row) {
            $labels[] = date('m-d', strtotime($row['d']));
            $data[] = (int) $row['cnt'];
        }

        return ['labels' => $labels, 'data' => $data];
    }

    private function nationalTemplate(array $metrics): array
    {
        return [
            ['指标', '数值', '说明'],
            ['在管糖尿病居民数', (string) $metrics['total_residents'], '管理对象总人数'],
            ['年度筛查人数', (string) $metrics['screened_count'], '已完成筛查人次（按居民去重）'],
            ['规范管理人数', (string) $metrics['managed_count'], '纳入干预管理人数'],
            ['年度随访完成人数', (string) $metrics['followed_count'], '至少一次完成随访'],
            ['血糖控制达标人数', (string) $metrics['controlled_count'], '最近一次空腹血糖达标'],
            ['预警处理完成数', (string) $metrics['handled_alert_count'], '预警处置闭环数量'],
        ];
    }

    private function interventionEffect(string $scopeSql, array $scopeParams): array
    {
        $sql = 'SELECT
                COUNT(*) AS tracked_count,
                SUM(CASE WHEN r_last.score < r_first.score THEN 1 ELSE 0 END) AS improved_count,
                SUM(CASE WHEN r_last.score > r_first.score THEN 1 ELSE 0 END) AS worsened_count
            FROM (
                SELECT resident_id, MIN(id) AS first_id, MAX(id) AS last_id, COUNT(*) AS cnt
                FROM risk_assessments
                GROUP BY resident_id
                HAVING cnt >= 2
            ) t
            JOIN risk_assessments r_first ON r_first.id = t.first_id
            JOIN risk_assessments r_last ON r_last.id = t.last_id
            JOIN residents r ON r.id = t.resident_id
            WHERE 1=1' . $scopeSql;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($scopeParams);
        $row = $stmt->fetch() ?: [];

        $tracked = (int) ($row['tracked_count'] ?? 0);
        $improved = (int) ($row['improved_count'] ?? 0);
        $worsened = (int) ($row['worsened_count'] ?? 0);
        $stable = max(0, $tracked - $improved - $worsened);

        return [
            'tracked_count' => $tracked,
            'improved_count' => $improved,
            'worsened_count' => $worsened,
            'stable_count' => $stable,
            'improvement_rate' => $tracked > 0 ? round($improved / $tracked * 100, 2) : 0,
        ];
    }

    private function doctorPerformance(): array
    {
        $user = $this->user();
        $sql = 'SELECT u.id, u.real_name, u.role,
                (SELECT COUNT(*) FROM tasks t WHERE t.assignee_id = u.id) AS assigned_tasks,
                (SELECT COUNT(*) FROM tasks t WHERE t.assignee_id = u.id AND t.status = "done") AS done_tasks,
                (SELECT COUNT(*) FROM interventions i WHERE i.executor_id = u.id) AS intervention_tasks,
                (SELECT COUNT(*) FROM interventions i WHERE i.executor_id = u.id AND i.status = "done") AS intervention_done,
                (SELECT COUNT(*) FROM followups f WHERE f.created_by = u.id) AS followups_created,
                (SELECT COUNT(*) FROM alerts a WHERE a.handled_by = u.id) AS handled_alerts
            FROM users u
            WHERE u.is_active = 1
              AND u.role IN ("doctor", "public_health", "nurse")';
        $params = [];

        if (($user['role'] ?? '') !== 'admin' && !empty($user['community_id'])) {
            $sql .= ' AND u.community_id = :community_id';
            $params['community_id'] = (int) $user['community_id'];
        }

        $sql .= ' ORDER BY u.role, u.real_name';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    private function exportCsv(
        array $metrics,
        array $communityCompare,
        array $nationalTemplate,
        array $interventionEffect,
        array $doctorPerformance
    ): void
    {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="公卫考核报表_' . date('Ymd_His') . '.csv"');

        echo "\xEF\xBB\xBF";
        $out = fopen('php://output', 'wb');
        fputcsv($out, ['指标', '数值']);
        fputcsv($out, ['糖尿病筛查率', $metrics['screening_rate'] . '%']);
        fputcsv($out, ['管理率', $metrics['management_rate'] . '%']);
        fputcsv($out, ['随访率', $metrics['followup_rate'] . '%']);
        fputcsv($out, ['控制率', $metrics['control_rate'] . '%']);
        fputcsv($out, ['预警处理率', $metrics['alert_handle_rate'] . '%']);
        fputcsv($out, []);
        fputcsv($out, ['风险分布', '人数']);
        fputcsv($out, ['绿线', $metrics['risk_dist']['green']]);
        fputcsv($out, ['黄线', $metrics['risk_dist']['yellow']]);
        fputcsv($out, ['红线', $metrics['risk_dist']['red']]);
        fputcsv($out, []);
        fputcsv($out, ['社区', '居民数', '红线人数']);
        foreach ($communityCompare as $row) {
            fputcsv($out, [$row['name'], $row['resident_count'], $row['red_count']]);
        }

        fputcsv($out, []);
        fputcsv($out, ['国家基本公卫糖尿病管理标准报表（简版）']);
        foreach ($nationalTemplate as $row) {
            fputcsv($out, $row);
        }

        fputcsv($out, []);
        fputcsv($out, ['干预成效分析', '数值']);
        fputcsv($out, ['追踪人数', $interventionEffect['tracked_count']]);
        fputcsv($out, ['改善人数', $interventionEffect['improved_count']]);
        fputcsv($out, ['恶化人数', $interventionEffect['worsened_count']]);
        fputcsv($out, ['持平人数', $interventionEffect['stable_count']]);
        fputcsv($out, ['改善率', $interventionEffect['improvement_rate'] . '%']);

        fputcsv($out, []);
        fputcsv($out, ['家庭医生/团队考核支撑']);
        fputcsv($out, ['姓名', '角色', '任务总数', '任务完成数', '干预总数', '干预完成数', '创建随访数', '处理预警数']);
        foreach ($doctorPerformance as $row) {
            fputcsv($out, [
                $row['real_name'],
                $row['role'],
                $row['assigned_tasks'],
                $row['done_tasks'],
                $row['intervention_tasks'],
                $row['intervention_done'],
                $row['followups_created'],
                $row['handled_alerts'],
            ]);
        }
        fclose($out);
        exit;
    }

    private function renderPdfLike(
        array $metrics,
        array $communityCompare,
        array $nationalTemplate,
        array $interventionEffect,
        array $doctorPerformance
    ): void
    {
        header('Content-Type: text/html; charset=UTF-8');
        echo '<!doctype html><html><head><meta charset="utf-8"><title>公卫考核报表</title><style>body{font-family:SimSun;padding:24px}table{border-collapse:collapse;width:100%;margin:12px 0}th,td{border:1px solid #333;padding:8px;text-align:left}</style></head><body>';
        echo '<h1>公卫考核报表</h1>';
        echo '<p>生成时间：' . date('Y-m-d H:i:s') . '</p>';
        echo '<table><tr><th>指标</th><th>数值</th></tr>';
        echo '<tr><td>糖尿病筛查率</td><td>' . $metrics['screening_rate'] . '%</td></tr>';
        echo '<tr><td>管理率</td><td>' . $metrics['management_rate'] . '%</td></tr>';
        echo '<tr><td>随访率</td><td>' . $metrics['followup_rate'] . '%</td></tr>';
        echo '<tr><td>控制率</td><td>' . $metrics['control_rate'] . '%</td></tr>';
        echo '<tr><td>预警处理率</td><td>' . $metrics['alert_handle_rate'] . '%</td></tr>';
        echo '</table>';

        echo '<h2>社区对比</h2><table><tr><th>社区</th><th>居民数</th><th>红线人数</th></tr>';
        foreach ($communityCompare as $row) {
            echo '<tr><td>' . e($row['name']) . '</td><td>' . e($row['resident_count']) . '</td><td>' . e($row['red_count']) . '</td></tr>';
        }
        echo '</table>';

        echo '<h2>国家基本公卫糖尿病管理标准报表（简版）</h2>';
        echo '<table><tr><th>指标</th><th>数值</th><th>说明</th></tr>';
        foreach ($nationalTemplate as $index => $row) {
            if ($index === 0) {
                continue;
            }
            echo '<tr><td>' . e($row[0]) . '</td><td>' . e($row[1]) . '</td><td>' . e($row[2]) . '</td></tr>';
        }
        echo '</table>';

        echo '<h2>干预成效分析</h2><table><tr><th>指标</th><th>数值</th></tr>';
        echo '<tr><td>追踪人数</td><td>' . e($interventionEffect['tracked_count']) . '</td></tr>';
        echo '<tr><td>改善人数</td><td>' . e($interventionEffect['improved_count']) . '</td></tr>';
        echo '<tr><td>恶化人数</td><td>' . e($interventionEffect['worsened_count']) . '</td></tr>';
        echo '<tr><td>持平人数</td><td>' . e($interventionEffect['stable_count']) . '</td></tr>';
        echo '<tr><td>改善率</td><td>' . e($interventionEffect['improvement_rate']) . '%</td></tr>';
        echo '</table>';

        echo '<h2>家庭医生/团队考核支撑</h2><table><tr><th>姓名</th><th>角色</th><th>任务总数</th><th>任务完成数</th><th>干预总数</th><th>干预完成数</th><th>创建随访数</th><th>处理预警数</th></tr>';
        foreach ($doctorPerformance as $row) {
            echo '<tr><td>' . e($row['real_name']) . '</td><td>' . e($row['role']) . '</td><td>' . e($row['assigned_tasks']) . '</td><td>' . e($row['done_tasks']) . '</td><td>' . e($row['intervention_tasks']) . '</td><td>' . e($row['intervention_done']) . '</td><td>' . e($row['followups_created']) . '</td><td>' . e($row['handled_alerts']) . '</td></tr>';
        }
        echo '</table>';

        echo '<script>window.print()</script></body></html>';
        exit;
    }
}
