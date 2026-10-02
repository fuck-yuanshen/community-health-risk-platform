<?php

declare(strict_types=1);

namespace App\Controllers;

final class InterventionController extends BaseController
{
    public function index(): void
    {
        $this->requirePermission('interventions');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf();
            $action = (string) post('action');

            if ($action === 'update_intervention') {
                $this->updateIntervention((int) post('id'));
                redirect('interventions');
            }
        }

        $scope = $this->scopeCondition('r');
        $status = trim((string) query('status', ''));

        $sql = 'SELECT i.*, r.name AS resident_name, ra.line_color, ra.management_level, u.real_name AS executor_name
            FROM interventions i
            JOIN residents r ON r.id = i.resident_id
            LEFT JOIN risk_assessments ra ON ra.id = i.risk_assessment_id
            LEFT JOIN users u ON u.id = i.executor_id
            WHERE 1=1' . $scope['sql'];

        $params = $scope['params'];
        if (in_array($status, ['todo', 'in_progress', 'done'], true)) {
            $sql .= ' AND i.status = :status';
            $params['status'] = $status;
        }

        $sql .= ' ORDER BY i.status <> "done" DESC, i.id DESC LIMIT 300';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $items = $stmt->fetchAll();

        $this->render('interventions/index', [
            'title' => '分层分类干预管理',
            'items' => $items,
            'status' => $status,
        ]);
    }

    private function updateIntervention(int $id): void
    {
        if ($id <= 0) {
            return;
        }

        $scope = $this->scopeCondition('r');
        $sql = 'SELECT i.id FROM interventions i
            JOIN residents r ON r.id = i.resident_id
            WHERE i.id = :id' . $scope['sql'];
        $params = array_merge(['id' => $id], $scope['params']);

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        if (!$stmt->fetchColumn()) {
            return;
        }

        $status = (string) post('status', 'todo');
        if (!in_array($status, ['todo', 'in_progress', 'done'], true)) {
            $status = 'todo';
        }

        $feedback = trim((string) post('feedback'));
        $attachmentPath = $this->handleAttachmentUpload();

        if ($attachmentPath !== null) {
            $stmt = $this->db->prepare(
                'UPDATE interventions
                SET status = :status, feedback = :feedback, attachment_path = :attachment_path
                WHERE id = :id'
            );
            $stmt->execute([
                'id' => $id,
                'status' => $status,
                'feedback' => $feedback,
                'attachment_path' => $attachmentPath,
            ]);
        } else {
            $stmt = $this->db->prepare('UPDATE interventions SET status = :status, feedback = :feedback WHERE id = :id');
            $stmt->execute([
                'id' => $id,
                'status' => $status,
                'feedback' => $feedback,
            ]);
        }

        $user = $this->user();
        $this->logger->log($user['id'], '更新干预执行记录', 'interventions', $id);
        flash('干预记录已更新');
    }

    private function handleAttachmentUpload(): ?string
    {
        if (!isset($_FILES['attachment']) || !is_array($_FILES['attachment'])) {
            return null;
        }

        $file = $_FILES['attachment'];
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            flash('附件上传失败，请重试', 'error');
            return null;
        }

        if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
            flash('附件不能超过 5MB', 'error');
            return null;
        }

        $ext = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx', 'txt'];
        if (!in_array($ext, $allowed, true)) {
            flash('附件格式不支持，仅支持 jpg/jpeg/png/pdf/doc/docx/txt', 'error');
            return null;
        }

        $dir = $this->config['base_path'] . '/public/uploads/interventions';
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $newName = 'intervention_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $dest = $dir . '/' . $newName;

        if (!move_uploaded_file((string) $file['tmp_name'], $dest)) {
            flash('附件保存失败', 'error');
            return null;
        }

        return '/uploads/interventions/' . $newName;
    }
}
