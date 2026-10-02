<?php

declare(strict_types=1);

namespace App\Controllers;

final class FollowupController extends BaseController
{
    public function index(): void
    {
        $this->requirePermission('followups');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf();
            $action = (string) post('action');

            if ($action === 'create_followup') {
                $this->createFollowup();
                redirect('followups');
            }

            if ($action === 'complete_followup') {
                $this->completeFollowup();
                redirect('followups');
            }
        }

        $scope = $this->scopeCondition('r');

        $residentSql = 'SELECT r.id, r.name
            FROM residents r
            JOIN families f ON f.id = r.family_id
            JOIN buildings b ON b.id = f.building_id
            JOIN grids g ON g.id = b.grid_id
            WHERE r.archived_at IS NULL' . $scope['sql'] . '
            ORDER BY r.id DESC LIMIT 300';
        $stmt = $this->db->prepare($residentSql);
        $stmt->execute($scope['params']);
        $residents = $stmt->fetchAll();

        $listSql = 'SELECT f.*, r.name AS resident_name, u.real_name AS creator_name
            FROM followups f
            JOIN residents r ON r.id = f.resident_id
            LEFT JOIN users u ON u.id = f.created_by
            WHERE 1=1' . $scope['sql'] . '
            ORDER BY (f.status = "pending") DESC, f.due_date ASC LIMIT 300';
        $stmt = $this->db->prepare($listSql);
        $stmt->execute($scope['params']);
        $followups = $stmt->fetchAll();

        $this->render('followups/index', [
            'title' => '随访管理',
            'residents' => $residents,
            'followups' => $followups,
        ]);
    }

    private function createFollowup(): void
    {
        $residentId = (int) post('resident_id');
        $dueDate = (string) post('due_date');

        if ($residentId <= 0 || $dueDate === '') {
            flash('请填写完整随访计划', 'error');
            return;
        }

        if (!$this->canAccessResident($residentId)) {
            flash('无权限操作该居民', 'error');
            return;
        }

        $user = $this->user();
        $stmt = $this->db->prepare(
            'INSERT INTO followups (resident_id, due_date, status, created_by)
            VALUES (:resident_id, :due_date, "pending", :created_by)'
        );
        $stmt->execute([
            'resident_id' => $residentId,
            'due_date' => $dueDate,
            'created_by' => $user['id'],
        ]);

        $followupId = (int) $this->db->lastInsertId();
        $this->createTaskAndNotify(
            $residentId,
            '随访到期提醒',
            $dueDate,
            $this->findAssigneeByResident($residentId),
            '随访计划到期'
        );

        $this->logger->log($user['id'], '新建随访计划', 'followups', $followupId);
        flash('随访计划已生成');
    }

    private function completeFollowup(): void
    {
        $followupId = (int) post('followup_id');
        if ($followupId <= 0) {
            return;
        }

        $scope = $this->scopeCondition('r');
        $checkSql = 'SELECT f.id, f.resident_id
            FROM followups f
            JOIN residents r ON r.id = f.resident_id
            WHERE f.id = :id' . $scope['sql'];
        $params = array_merge(['id' => $followupId], $scope['params']);

        $stmt = $this->db->prepare($checkSql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        if (!$row) {
            return;
        }

        $visitDate = (string) post('visit_date');
        $summary = trim((string) post('result_summary'));
        $nextDueDate = (string) post('next_due_date');
        $abnormal = (int) (post('abnormal', 0) ? 1 : 0);

        $stmt = $this->db->prepare(
            'UPDATE followups
            SET visit_date = :visit_date,
                result_summary = :result_summary,
                next_due_date = :next_due_date,
                status = "done"
            WHERE id = :id'
        );
        $stmt->execute([
            'id' => $followupId,
            'visit_date' => $visitDate,
            'result_summary' => $summary,
            'next_due_date' => $nextDueDate !== '' ? $nextDueDate : null,
        ]);

        $this->db->prepare(
            'UPDATE tasks
            SET status = "done"
            WHERE resident_id = :resident_id
              AND task_type IN ("随访到期提醒", "随访提醒")
              AND status = "pending"'
        )->execute(['resident_id' => $row['resident_id']]);

        if ($nextDueDate !== '') {
            $stmt = $this->db->prepare(
                'INSERT INTO followups (resident_id, due_date, status, created_by)
                VALUES (:resident_id, :due_date, "pending", :created_by)'
            );
            $user = $this->user();
            $stmt->execute([
                'resident_id' => $row['resident_id'],
                'due_date' => $nextDueDate,
                'created_by' => $user['id'],
            ]);
        }

        if ($abnormal === 1) {
            $user = $this->user();
            $stmt = $this->db->prepare(
                'INSERT INTO referrals (resident_id, followup_id, reason, status, apply_time, created_by)
                VALUES (:resident_id, :followup_id, :reason, "applied", :apply_time, :created_by)'
            );
            $stmt->execute([
                'resident_id' => $row['resident_id'],
                'followup_id' => $followupId,
                'reason' => $summary !== '' ? $summary : '随访指标异常，触发转诊',
                'apply_time' => date('Y-m-d H:i:s'),
                'created_by' => $user['id'],
            ]);

            $this->createTaskAndNotify(
                (int) $row['resident_id'],
                '转诊审批',
                date('Y-m-d', strtotime('+3 days')),
                $this->findAssigneeByResident((int) $row['resident_id']),
                '异常随访触发转诊'
            );
        }

        $user = $this->user();
        $this->logger->log($user['id'], '完成随访记录', 'followups', $followupId);
        flash('随访记录已完成');
    }

    private function canAccessResident(int $residentId): bool
    {
        $user = $this->user();
        if (($user['role'] ?? '') === 'admin') {
            return true;
        }

        $stmt = $this->db->prepare(
            'SELECT g.community_id
            FROM residents r
            JOIN families f ON f.id = r.family_id
            JOIN buildings b ON b.id = f.building_id
            JOIN grids g ON g.id = b.grid_id
            WHERE r.id = :id'
        );
        $stmt->execute(['id' => $residentId]);
        $communityId = (int) $stmt->fetchColumn();

        if ($communityId === 0) {
            return false;
        }

        return !$user['community_id'] || (int) $user['community_id'] === $communityId;
    }

    private function findAssigneeByResident(int $residentId): ?int
    {
        $stmt = $this->db->prepare(
            'SELECT g.responsible_user_id
            FROM residents r
            JOIN families f ON f.id = r.family_id
            JOIN buildings b ON b.id = f.building_id
            JOIN grids g ON g.id = b.grid_id
            WHERE r.id = :id'
        );
        $stmt->execute(['id' => $residentId]);
        $id = $stmt->fetchColumn();

        return $id !== false ? (int) $id : null;
    }
}
