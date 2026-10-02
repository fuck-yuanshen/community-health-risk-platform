<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Logger;
use App\Services\RiskService;
use PDO;

final class MeasurementController extends BaseController
{
    public function __construct(
        PDO $db,
        Auth $auth,
        Logger $logger,
        array $config,
        private readonly RiskService $riskService
    ) {
        parent::__construct($db, $auth, $logger, $config);
    }

    public function index(): void
    {
        $this->requirePermission('measurements');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf();
            $action = (string) post('action');

            if ($action === 'add_measurement') {
                $this->addMeasurement([
                    'resident_id' => (int) post('resident_id'),
                    'check_date' => (string) post('check_date'),
                    'fasting_glucose' => (float) post('fasting_glucose', 0),
                    'ogtt_2h' => (float) post('ogtt_2h', 0),
                    'hba1c' => (float) post('hba1c', 0),
                    'sbp' => (int) post('sbp', 0),
                    'dbp' => (int) post('dbp', 0),
                    'bmi' => (float) post('bmi', 0),
                    'triglyceride' => (float) post('triglyceride', 0),
                    'ldl' => (float) post('ldl', 0),
                    'family_history' => (int) (post('family_history', 0) ? 1 : 0),
                    'smoker' => (int) (post('smoker', 0) ? 1 : 0),
                    'drinker' => (int) (post('drinker', 0) ? 1 : 0),
                    'inactivity' => (int) (post('inactivity', 0) ? 1 : 0),
                    'source' => trim((string) post('source', '门诊录入')),
                ]);

                redirect('measurements');
            }

            if ($action === 'batch_import') {
                $content = trim((string) post('batch_text'));
                $lines = preg_split('/\r\n|\r|\n/', $content) ?: [];
                $count = 0;

                foreach ($lines as $line) {
                    $parts = array_map('trim', explode(',', $line));
                    if (count($parts) < 15) {
                        continue;
                    }

                    $this->addMeasurement([
                        'resident_id' => (int) $parts[0],
                        'check_date' => $parts[1],
                        'fasting_glucose' => (float) $parts[2],
                        'ogtt_2h' => (float) $parts[3],
                        'hba1c' => (float) $parts[4],
                        'sbp' => (int) $parts[5],
                        'dbp' => (int) $parts[6],
                        'bmi' => (float) $parts[7],
                        'triglyceride' => (float) $parts[8],
                        'ldl' => (float) $parts[9],
                        'family_history' => (int) $parts[10],
                        'smoker' => (int) $parts[11],
                        'drinker' => (int) $parts[12],
                        'inactivity' => (int) $parts[13],
                        'source' => $parts[14] ?: '批量导入',
                    ]);
                    $count++;
                }

                flash('体检批量导入完成，成功 ' . $count . ' 条');
                redirect('measurements');
            }
        }

        $scope = $this->scopeCondition('r');

        $stmt = $this->db->prepare(
            'SELECT r.id, r.name, CONCAT(c.name, " / ", g.name, " / ", f.address) AS location
            FROM residents r
            JOIN families f ON f.id = r.family_id
            JOIN buildings b ON b.id = f.building_id
            JOIN grids g ON g.id = b.grid_id
            JOIN communities c ON c.id = g.community_id
            WHERE r.archived_at IS NULL' . $scope['sql'] . '
            ORDER BY r.id DESC LIMIT 300'
        );
        $stmt->execute($scope['params']);
        $residents = $stmt->fetchAll();

        $sql = 'SELECT m.*, r.name AS resident_name, ra.score, ra.line_color, ra.management_level
            FROM measurements m
            JOIN residents r ON r.id = m.resident_id
            LEFT JOIN risk_assessments ra ON ra.measurement_id = m.id
            WHERE 1=1' . $scope['sql'] . '
            ORDER BY m.id DESC LIMIT 200';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($scope['params']);
        $measurements = $stmt->fetchAll();

        $this->render('measurements/index', [
            'title' => '多维风险数据采集',
            'residents' => $residents,
            'measurements' => $measurements,
        ]);
    }

    private function addMeasurement(array $data): void
    {
        $user = $this->user();
        if ($data['resident_id'] <= 0 || !$this->canAccessResident($data['resident_id'], $user)) {
            return;
        }

        $stmt = $this->db->prepare(
            'INSERT INTO measurements (
                resident_id, check_date, fasting_glucose, ogtt_2h, hba1c, sbp, dbp, bmi,
                triglyceride, ldl, family_history, smoker, drinker, inactivity, source
            ) VALUES (
                :resident_id, :check_date, :fasting_glucose, :ogtt_2h, :hba1c, :sbp, :dbp, :bmi,
                :triglyceride, :ldl, :family_history, :smoker, :drinker, :inactivity, :source
            )'
        );
        $stmt->execute($data);
        $measurementId = (int) $this->db->lastInsertId();

        $settings = $this->loadRiskSettings();

        $prev = $this->db->prepare('SELECT score, line_color, management_level FROM risk_assessments WHERE resident_id = :resident_id ORDER BY id DESC LIMIT 1');
        $prev->execute(['resident_id' => $data['resident_id']]);
        $lastAssessment = $prev->fetch();

        $assessment = $this->riskService->evaluate($data, $settings);

        $trendTag = '';
        if ($lastAssessment) {
            $delta = (int) $assessment['score'] - (int) $lastAssessment['score'];
            if ($delta >= 10) {
                $trendTag = '风险恶化，自动升级关注';
            } elseif ($delta <= -10) {
                $trendTag = '风险改善，自动降级建议';
            }
        }
        if ($trendTag !== '') {
            $assessment['reason'] = trim($assessment['reason'] . '；' . $trendTag, '；');
        }

        $stmt = $this->db->prepare(
            'INSERT INTO risk_assessments (resident_id, measurement_id, score, line_color, management_level, prediabetes, reason)
            VALUES (:resident_id, :measurement_id, :score, :line_color, :management_level, :prediabetes, :reason)'
        );
        $stmt->execute([
            'resident_id' => $data['resident_id'],
            'measurement_id' => $measurementId,
            'score' => $assessment['score'],
            'line_color' => $assessment['line_color'],
            'management_level' => $assessment['management_level'],
            'prediabetes' => $assessment['prediabetes'],
            'reason' => $assessment['reason'],
        ]);

        $riskAssessmentId = (int) $this->db->lastInsertId();
        $this->logger->log($user['id'], '新增体检并完成风险评估', 'measurements', $measurementId);
        $assigneeId = $this->findAssigneeByResident($data['resident_id']);

        if (in_array($assessment['line_color'], ['yellow', 'red'], true)) {
            $this->createAlertAndTask(
                $data['resident_id'],
                $riskAssessmentId,
                $assessment['line_color'],
                $assessment['score'],
                $assigneeId
            );
        }

        $this->generateInterventions($data['resident_id'], $riskAssessmentId, $assessment['line_color'], $assessment['management_level']);
        $this->scheduleStandardFollowupPlan($data['resident_id'], $assessment['line_color'], $assigneeId);

        if ($assessment['line_color'] === 'red') {
            $this->scheduleContinuousMonitoring($data['resident_id'], $assigneeId);
        }
    }

    private function createAlertAndTask(
        int $residentId,
        int $riskAssessmentId,
        string $line,
        int $score,
        ?int $assigneeId
    ): void
    {
        $title = $line === 'red' ? '红线风险预警' : '黄线风险预警';
        $desc = '风险分值 ' . $score . '，请及时处理并安排随访。';

        $stmt = $this->db->prepare(
            'INSERT INTO alerts (resident_id, risk_assessment_id, alert_line, title, description, status)
            VALUES (:resident_id, :risk_assessment_id, :alert_line, :title, :description, "pending")'
        );
        $stmt->execute([
            'resident_id' => $residentId,
            'risk_assessment_id' => $riskAssessmentId,
            'alert_line' => $line,
            'title' => $title,
            'description' => $desc,
        ]);

        $dueDays = $line === 'red' ? 14 : 30;
        $dueDate = date('Y-m-d', strtotime('+' . $dueDays . ' days'));
        $note = $line === 'red' ? '高危人群重点随访' : '黄线预警复查提醒';
        $this->createTaskAndNotify($residentId, '随访提醒', $dueDate, $assigneeId, $note);
        $this->pushNotification($assigneeId, $residentId, 'alert', $title, $desc);
        $_SESSION['_auto_alert_popup'][] = '居民ID ' . $residentId . '：' . $title . '（分值 ' . $score . '）';
    }

    private function generateInterventions(int $residentId, int $riskAssessmentId, string $line, string $level): void
    {
        $plans = $this->riskService->interventionPlan($line, $level);
        $assigneeId = $this->findAssigneeByResident($residentId);

        $stmt = $this->db->prepare(
            'INSERT INTO interventions (resident_id, risk_assessment_id, category, plan_content, status, executor_id)
            VALUES (:resident_id, :risk_assessment_id, :category, :plan_content, "todo", :executor_id)'
        );

        foreach ($plans as $category => $content) {
            $stmt->execute([
                'resident_id' => $residentId,
                'risk_assessment_id' => $riskAssessmentId,
                'category' => $category,
                'plan_content' => $content,
                'executor_id' => $assigneeId,
            ]);
        }

        $this->pushNotification(
            $assigneeId,
            $residentId,
            'intervention',
            '自动生成干预计划',
            '系统已根据风险等级自动下发干预任务，请及时执行。'
        );
    }

    private function scheduleStandardFollowupPlan(int $residentId, string $line, ?int $assigneeId): void
    {
        $days = match ($line) {
            'red' => 14,
            'yellow' => 30,
            default => 90,
        };

        $dueDate = date('Y-m-d', strtotime('+' . $days . ' days'));
        $existsStmt = $this->db->prepare(
            'SELECT COUNT(*) FROM followups
            WHERE resident_id = :resident_id
              AND status = "pending"
              AND due_date = :due_date'
        );
        $existsStmt->execute([
            'resident_id' => $residentId,
            'due_date' => $dueDate,
        ]);
        if ((int) $existsStmt->fetchColumn() > 0) {
            return;
        }

        $stmt = $this->db->prepare(
            'INSERT INTO followups (resident_id, due_date, status, created_by)
            VALUES (:resident_id, :due_date, "pending", :created_by)'
        );
        $user = $this->user();
        $stmt->execute([
            'resident_id' => $residentId,
            'due_date' => $dueDate,
            'created_by' => $user['id'] ?? null,
        ]);

        $taskType = $line === 'green' ? '体检提醒' : '随访到期提醒';
        $this->createTaskAndNotify($residentId, $taskType, $dueDate, $assigneeId, '按公卫随访标准自动生成计划');
    }

    private function scheduleContinuousMonitoring(int $residentId, ?int $assigneeId): void
    {
        foreach ([14, 28, 42] as $days) {
            $dueDate = date('Y-m-d', strtotime('+' . $days . ' days'));
            $existsStmt = $this->db->prepare(
                'SELECT COUNT(*) FROM followups
                WHERE resident_id = :resident_id
                  AND status = "pending"
                  AND due_date = :due_date'
            );
            $existsStmt->execute([
                'resident_id' => $residentId,
                'due_date' => $dueDate,
            ]);

            if ((int) $existsStmt->fetchColumn() > 0) {
                continue;
            }

            $stmt = $this->db->prepare(
                'INSERT INTO followups (resident_id, due_date, status, created_by)
                VALUES (:resident_id, :due_date, "pending", :created_by)'
            );
            $user = $this->user();
            $stmt->execute([
                'resident_id' => $residentId,
                'due_date' => $dueDate,
                'created_by' => $user['id'] ?? null,
            ]);

            $this->createTaskAndNotify($residentId, '高危连续监测', $dueDate, $assigneeId, '红线人群专项强化管理');
        }
    }

    private function findAssigneeByResident(int $residentId): ?int
    {
        $stmt = $this->db->prepare(
            'SELECT g.responsible_user_id
            FROM residents r
            JOIN families f ON f.id = r.family_id
            JOIN buildings b ON b.id = f.building_id
            JOIN grids g ON g.id = b.grid_id
            WHERE r.id = :resident_id'
        );
        $stmt->execute(['resident_id' => $residentId]);

        $id = $stmt->fetchColumn();
        return $id !== false ? (int) $id : null;
    }

    private function loadRiskSettings(): array
    {
        $stmt = $this->db->query('SELECT name, value FROM settings WHERE name IN ("line_yellow", "line_red")');
        $items = $stmt->fetchAll();

        $settings = $this->config['risk_defaults'];
        foreach ($items as $item) {
            $settings[$item['name']] = is_numeric($item['value']) ? (float) $item['value'] : $item['value'];
        }

        return $settings;
    }

    private function canAccessResident(int $residentId, array $user): bool
    {
        if ($user['role'] === 'admin') {
            return true;
        }

        $stmt = $this->db->prepare('SELECT g.community_id FROM residents r
            JOIN families f ON f.id = r.family_id
            JOIN buildings b ON b.id = f.building_id
            JOIN grids g ON g.id = b.grid_id
            WHERE r.id = :id');
        $stmt->execute(['id' => $residentId]);
        $communityId = (int) $stmt->fetchColumn();

        if ($communityId === 0) {
            return false;
        }

        return !$user['community_id'] || (int) $user['community_id'] === $communityId;
    }
}
