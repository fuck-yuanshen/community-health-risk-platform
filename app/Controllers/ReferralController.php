<?php

declare(strict_types=1);

namespace App\Controllers;

final class ReferralController extends BaseController
{
    public function index(): void
    {
        $this->requirePermission('referrals');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf();
            $this->updateReferral();
            redirect('referrals');
        }

        $scope = $this->scopeCondition('r');

        $sql = 'SELECT rf.*, r.name AS resident_name, f.visit_date
            FROM referrals rf
            JOIN residents r ON r.id = rf.resident_id
            LEFT JOIN followups f ON f.id = rf.followup_id
            WHERE 1=1' . $scope['sql'] . '
            ORDER BY rf.id DESC LIMIT 300';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($scope['params']);
        $items = $stmt->fetchAll();

        $this->render('referrals/index', [
            'title' => '双向转诊闭环',
            'items' => $items,
        ]);
    }

    private function updateReferral(): void
    {
        $id = (int) post('id');
        $status = (string) post('status');
        $note = trim((string) post('result_note'));

        if ($id <= 0 || !in_array($status, ['applied', 'approved', 'completed', 'rejected'], true)) {
            return;
        }

        $scope = $this->scopeCondition('r');
        $checkSql = 'SELECT rf.id, rf.resident_id
            FROM referrals rf
            JOIN residents r ON r.id = rf.resident_id
            WHERE rf.id = :id' . $scope['sql'];
        $params = array_merge(['id' => $id], $scope['params']);
        $stmt = $this->db->prepare($checkSql);
        $stmt->execute($params);
        $item = $stmt->fetch();

        if (!$item) {
            return;
        }

        $reviewTime = in_array($status, ['approved', 'completed', 'rejected'], true) ? date('Y-m-d H:i:s') : null;
        $stmt = $this->db->prepare('UPDATE referrals SET status = :status, result_note = :result_note, review_time = :review_time WHERE id = :id');
        $stmt->execute([
            'id' => $id,
            'status' => $status,
            'result_note' => $note,
            'review_time' => $reviewTime,
        ]);

        if ($status === 'completed') {
            $dueDate = date('Y-m-d', strtotime('+7 days'));
            $assigneeId = $this->findAssigneeByResident((int) $item['resident_id']);

            $stmt = $this->db->prepare(
                'INSERT INTO followups (resident_id, due_date, status, created_by)
                VALUES (:resident_id, :due_date, "pending", :created_by)'
            );
            $user = $this->user();
            $stmt->execute([
                'resident_id' => $item['resident_id'],
                'due_date' => $dueDate,
                'created_by' => $user['id'] ?? null,
            ]);

            $this->createTaskAndNotify(
                (int) $item['resident_id'],
                '再评估提醒',
                $dueDate,
                $assigneeId,
                '转诊完成后自动进入再评估'
            );
        }

        $user = $this->user();
        $this->logger->log($user['id'], '更新转诊状态', 'referrals', $id);
        flash('转诊状态已更新');
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
