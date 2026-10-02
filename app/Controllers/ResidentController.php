<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Crypto;
use App\Core\Logger;
use PDO;

final class ResidentController extends BaseController
{
    public function __construct(
        PDO $db,
        Auth $auth,
        Logger $logger,
        array $config,
        private readonly Crypto $crypto
    ) {
        parent::__construct($db, $auth, $logger, $config);
    }

    public function index(): void
    {
        $this->requirePermission('residents');
        $user = $this->user();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf();
            $action = (string) post('action');

            if ($action === 'create_resident') {
                $this->createResident();
                redirect('residents');
            }

            if ($action === 'update_resident') {
                $this->updateResident();
                redirect('residents');
            }

            if ($action === 'archive_resident') {
                $residentId = (int) post('resident_id');
                if ($residentId > 0 && $this->canAccessResident($residentId, $user)) {
                    $stmt = $this->db->prepare('UPDATE residents SET archived_at = :archived_at WHERE id = :id');
                    $stmt->execute([
                        'id' => $residentId,
                        'archived_at' => date('Y-m-d H:i:s'),
                    ]);
                    $this->logger->log($user['id'], '归档居民档案', 'residents', $residentId);
                    flash('居民档案已归档');
                }
                redirect('residents');
            }

            if ($action === 'import_residents') {
                $familyId = (int) post('family_id');
                $content = trim((string) post('batch_text'));
                $inserted = 0;

                if ($familyId > 0 && $content !== '') {
                    if (!$this->canAccessFamily($familyId, $user)) {
                        flash('不可跨社区导入', 'error');
                        redirect('residents');
                    }

                    $lines = preg_split('/\r\n|\r|\n/', $content) ?: [];
                    foreach ($lines as $line) {
                        $parts = array_map('trim', explode(',', $line));
                        if (count($parts) < 6) {
                            continue;
                        }
                        $stmt = $this->db->prepare(
                            'INSERT INTO residents (family_id, name, gender, birth_date, id_card_enc, phone_enc, address, chronic_history)
                            VALUES (:family_id, :name, :gender, :birth_date, :id_card_enc, :phone_enc, :address, :chronic_history)'
                        );
                        $stmt->execute([
                            'family_id' => $familyId,
                            'name' => $parts[0],
                            'gender' => $parts[1],
                            'birth_date' => $parts[2],
                            'id_card_enc' => $this->crypto->encrypt($parts[3]),
                            'phone_enc' => $this->crypto->encrypt($parts[4]),
                            'address' => $parts[5],
                            'chronic_history' => $parts[6] ?? '',
                        ]);
                        $inserted++;
                    }
                }

                $this->logger->log($user['id'], '批量导入居民', 'residents');
                flash('批量导入完成，成功 ' . $inserted . ' 条');
                redirect('residents');
            }
        }

        $keyword = trim((string) query('keyword', ''));
        $editId = (int) query('edit', 0);

        $familySql = 'SELECT f.id, CONCAT(c.name, " / ", g.name, " / ", b.name, " / ", f.address) AS label
            FROM families f
            JOIN buildings b ON b.id = f.building_id
            JOIN grids g ON g.id = b.grid_id
            JOIN communities c ON c.id = g.community_id
            WHERE f.archive_status = "active"';
        $familyParams = [];
        if ($user['role'] !== 'admin' && $user['community_id']) {
            $familySql .= ' AND g.community_id = :community_id';
            $familyParams['community_id'] = (int) $user['community_id'];
        }
        $familySql .= ' ORDER BY f.id DESC';
        $stmt = $this->db->prepare($familySql);
        $stmt->execute($familyParams);
        $families = $stmt->fetchAll();

        $residentSql = 'SELECT r.*, f.address AS family_address, b.name AS building_name, g.name AS grid_name, c.name AS community_name,
                ra.score AS latest_score, ra.line_color AS latest_line, ra.management_level AS latest_level
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
            WHERE 1=1';
        $residentParams = [];
        if ($user['role'] !== 'admin' && $user['community_id']) {
            $residentSql .= ' AND g.community_id = :community_id';
            $residentParams['community_id'] = (int) $user['community_id'];
        }
        if ($keyword !== '') {
            $residentSql .= ' AND (r.name LIKE :kw OR r.address LIKE :kw)';
            $residentParams['kw'] = '%' . $keyword . '%';
        }
        $residentSql .= ' ORDER BY r.id DESC LIMIT 200';

        $stmt = $this->db->prepare($residentSql);
        $stmt->execute($residentParams);
        $residents = $stmt->fetchAll();

        foreach ($residents as &$resident) {
            $resident['id_card'] = $this->crypto->decrypt($resident['id_card_enc'] ?? '');
            $resident['phone'] = $this->crypto->decrypt($resident['phone_enc'] ?? '');
        }
        unset($resident);

        $editResident = null;
        if ($editId > 0 && $this->canAccessResident($editId, $user)) {
            $stmt = $this->db->prepare('SELECT * FROM residents WHERE id = :id');
            $stmt->execute(['id' => $editId]);
            $editResident = $stmt->fetch();
            if ($editResident) {
                $editResident['id_card'] = $this->crypto->decrypt($editResident['id_card_enc'] ?? '');
                $editResident['phone'] = $this->crypto->decrypt($editResident['phone_enc'] ?? '');
            }
        }

        $this->render('residents/index', [
            'title' => '居民健康档案',
            'families' => $families,
            'residents' => $residents,
            'keyword' => $keyword,
            'editResident' => $editResident,
        ]);
    }

    private function createResident(): void
    {
        $user = $this->user();
        $familyId = (int) post('family_id');

        if ($familyId <= 0 || !$this->canAccessFamily($familyId, $user)) {
            flash('家庭不合法或无权限', 'error');
            return;
        }

        $stmt = $this->db->prepare(
            'INSERT INTO residents (family_id, name, gender, birth_date, id_card_enc, phone_enc, address, chronic_history)
            VALUES (:family_id, :name, :gender, :birth_date, :id_card_enc, :phone_enc, :address, :chronic_history)'
        );
        $stmt->execute([
            'family_id' => $familyId,
            'name' => trim((string) post('name')),
            'gender' => trim((string) post('gender')),
            'birth_date' => post('birth_date'),
            'id_card_enc' => $this->crypto->encrypt(trim((string) post('id_card'))),
            'phone_enc' => $this->crypto->encrypt(trim((string) post('phone'))),
            'address' => trim((string) post('address')),
            'chronic_history' => trim((string) post('chronic_history')),
        ]);

        $id = (int) $this->db->lastInsertId();
        $this->logger->log($user['id'], '新增居民档案', 'residents', $id);
        flash('居民档案创建成功');
    }

    private function updateResident(): void
    {
        $user = $this->user();
        $residentId = (int) post('resident_id');
        if ($residentId <= 0 || !$this->canAccessResident($residentId, $user)) {
            flash('居民不存在或无权限', 'error');
            return;
        }

        $stmt = $this->db->prepare(
            'UPDATE residents SET
                name = :name,
                gender = :gender,
                birth_date = :birth_date,
                id_card_enc = :id_card_enc,
                phone_enc = :phone_enc,
                address = :address,
                chronic_history = :chronic_history
            WHERE id = :id'
        );

        $stmt->execute([
            'id' => $residentId,
            'name' => trim((string) post('name')),
            'gender' => trim((string) post('gender')),
            'birth_date' => post('birth_date'),
            'id_card_enc' => $this->crypto->encrypt(trim((string) post('id_card'))),
            'phone_enc' => $this->crypto->encrypt(trim((string) post('phone'))),
            'address' => trim((string) post('address')),
            'chronic_history' => trim((string) post('chronic_history')),
        ]);

        $this->logger->log($user['id'], '编辑居民档案', 'residents', $residentId);
        flash('居民档案已更新');
    }

    private function canAccessFamily(int $familyId, array $user): bool
    {
        $stmt = $this->db->prepare('SELECT g.community_id FROM families f
            JOIN buildings b ON b.id = f.building_id
            JOIN grids g ON g.id = b.grid_id
            WHERE f.id = :id');
        $stmt->execute(['id' => $familyId]);
        $communityId = (int) $stmt->fetchColumn();

        if ($communityId === 0) {
            return false;
        }

        return $user['role'] === 'admin' || !$user['community_id'] || (int) $user['community_id'] === $communityId;
    }

    private function canAccessResident(int $residentId, array $user): bool
    {
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

        return $user['role'] === 'admin' || !$user['community_id'] || (int) $user['community_id'] === $communityId;
    }
}
