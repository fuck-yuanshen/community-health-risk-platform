<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Crypto;
use App\Core\Logger;
use PDO;

final class FamilyController extends BaseController
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
        $this->requirePermission('families');
        $user = $this->user();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf();
            $action = (string) post('action');

            if ($action === 'create_family') {
                $buildingId = (int) post('building_id');
                $address = trim((string) post('address'));
                $headName = trim((string) post('head_name'));
                $headPhone = trim((string) post('head_phone'));

                if ($buildingId > 0 && $address !== '' && $headName !== '') {
                    $checkSql = 'SELECT g.community_id
                        FROM buildings b
                        JOIN grids g ON g.id = b.grid_id
                        WHERE b.id = :building_id';
                    $stmt = $this->db->prepare($checkSql);
                    $stmt->execute(['building_id' => $buildingId]);
                    $communityId = (int) $stmt->fetchColumn();

                    if ($communityId === 0) {
                        flash('楼栋不存在', 'error');
                        redirect('families');
                    }
                    if ($user['role'] !== 'admin' && $user['community_id'] && (int) $user['community_id'] !== $communityId) {
                        flash('不可跨社区操作', 'error');
                        redirect('families');
                    }

                    $stmt = $this->db->prepare(
                        'INSERT INTO families (building_id, address, head_name, head_phone_enc, archive_status)
                         VALUES (:building_id, :address, :head_name, :head_phone_enc, "active")'
                    );
                    $stmt->execute([
                        'building_id' => $buildingId,
                        'address' => $address,
                        'head_name' => $headName,
                        'head_phone_enc' => $this->crypto->encrypt($headPhone),
                    ]);
                    $id = (int) $this->db->lastInsertId();
                    $this->logger->log($user['id'], '新增家庭档案', 'families', $id);
                    flash('家庭档案创建成功');
                }
            }

            if ($action === 'archive_family') {
                $familyId = (int) post('family_id');
                if ($familyId > 0) {
                    $scopeSql = 'SELECT g.community_id
                        FROM families f
                        JOIN buildings b ON b.id = f.building_id
                        JOIN grids g ON g.id = b.grid_id
                        WHERE f.id = :id';
                    $stmt = $this->db->prepare($scopeSql);
                    $stmt->execute(['id' => $familyId]);
                    $communityId = (int) $stmt->fetchColumn();

                    if ($communityId > 0 && ($user['role'] === 'admin' || !$user['community_id'] || (int) $user['community_id'] === $communityId)) {
                        $stmt = $this->db->prepare('UPDATE families SET archive_status = "archived" WHERE id = :id');
                        $stmt->execute(['id' => $familyId]);
                        $this->logger->log($user['id'], '归档家庭档案', 'families', $familyId);
                        flash('已归档');
                    }
                }
            }

            redirect('families');
        }

        $buildingSql = 'SELECT b.id, b.name, g.name AS grid_name, c.name AS community_name
            FROM buildings b
            JOIN grids g ON g.id = b.grid_id
            JOIN communities c ON c.id = g.community_id
            WHERE 1=1';
        $buildingParams = [];
        if ($user['role'] !== 'admin' && $user['community_id']) {
            $buildingSql .= ' AND g.community_id = :community_id';
            $buildingParams['community_id'] = (int) $user['community_id'];
        }
        $buildingSql .= ' ORDER BY b.id DESC';
        $stmt = $this->db->prepare($buildingSql);
        $stmt->execute($buildingParams);
        $buildings = $stmt->fetchAll();

        $familySql = 'SELECT f.*, b.name AS building_name, g.name AS grid_name, c.name AS community_name,
                COUNT(DISTINCT r.id) AS member_count,
                SUM(CASE WHEN ra.line_color = "red" THEN 1 ELSE 0 END) AS red_members
            FROM families f
            JOIN buildings b ON b.id = f.building_id
            JOIN grids g ON g.id = b.grid_id
            JOIN communities c ON c.id = g.community_id
            LEFT JOIN residents r ON r.family_id = f.id
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
        $familyParams = [];
        if ($user['role'] !== 'admin' && $user['community_id']) {
            $familySql .= ' AND g.community_id = :community_id';
            $familyParams['community_id'] = (int) $user['community_id'];
        }
        $familySql .= ' GROUP BY f.id ORDER BY f.id DESC';
        $stmt = $this->db->prepare($familySql);
        $stmt->execute($familyParams);
        $families = $stmt->fetchAll();

        foreach ($families as &$family) {
            $family['head_phone'] = $this->crypto->decrypt($family['head_phone_enc'] ?? '');
        }
        unset($family);

        $this->render('families/index', [
            'title' => '家庭健康档案',
            'buildings' => $buildings,
            'families' => $families,
        ]);
    }
}
