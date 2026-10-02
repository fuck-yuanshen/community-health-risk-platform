<?php

declare(strict_types=1);

namespace App\Controllers;

final class GridController extends BaseController
{
    public function index(): void
    {
        $this->requirePermission('grids');
        $user = $this->user();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf();
            $action = (string) post('action');

            if ($action === 'create_community' && $user['role'] === 'admin') {
                $name = trim((string) post('name'));
                if ($name !== '') {
                    $stmt = $this->db->prepare('INSERT INTO communities (name) VALUES (:name)');
                    $stmt->execute(['name' => $name]);
                    $this->logger->log($user['id'], '新增社区', 'communities', (int) $this->db->lastInsertId());
                    flash('社区已创建');
                }
            }

            if ($action === 'create_grid') {
                $name = trim((string) post('name'));
                $communityId = (int) post('community_id');
                $responsibleId = post('responsible_user_id') !== '' ? (int) post('responsible_user_id') : null;

                if ($name !== '' && $communityId > 0) {
                    if ($user['role'] !== 'admin' && (int) $user['community_id'] !== $communityId) {
                        flash('不可跨社区创建网格', 'error');
                        redirect('grids');
                    }

                    $stmt = $this->db->prepare(
                        'INSERT INTO grids (community_id, name, responsible_user_id) VALUES (:community_id, :name, :responsible_user_id)'
                    );
                    $stmt->execute([
                        'community_id' => $communityId,
                        'name' => $name,
                        'responsible_user_id' => $responsibleId,
                    ]);
                    $this->logger->log($user['id'], '新增网格', 'grids', (int) $this->db->lastInsertId());
                    flash('网格已创建');
                }
            }

            if ($action === 'create_building') {
                $name = trim((string) post('name'));
                $gridId = (int) post('grid_id');

                if ($name !== '' && $gridId > 0) {
                    $checkSql = 'SELECT g.community_id FROM grids g WHERE g.id = :grid_id';
                    $stmt = $this->db->prepare($checkSql);
                    $stmt->execute(['grid_id' => $gridId]);
                    $communityId = (int) $stmt->fetchColumn();

                    if ($communityId === 0) {
                        flash('网格不存在', 'error');
                        redirect('grids');
                    }
                    if ($user['role'] !== 'admin' && (int) $user['community_id'] !== $communityId) {
                        flash('不可跨社区创建楼栋', 'error');
                        redirect('grids');
                    }

                    $stmt = $this->db->prepare('INSERT INTO buildings (grid_id, name) VALUES (:grid_id, :name)');
                    $stmt->execute(['grid_id' => $gridId, 'name' => $name]);
                    $this->logger->log($user['id'], '新增楼栋', 'buildings', (int) $this->db->lastInsertId());
                    flash('楼栋已创建');
                }
            }

            redirect('grids');
        }

        $communitySql = 'SELECT * FROM communities';
        $params = [];
        if ($user['role'] !== 'admin' && $user['community_id']) {
            $communitySql .= ' WHERE id = :id';
            $params['id'] = (int) $user['community_id'];
        }
        $communitySql .= ' ORDER BY id DESC';

        $stmt = $this->db->prepare($communitySql);
        $stmt->execute($params);
        $communities = $stmt->fetchAll();

        $gridSql = 'SELECT g.*, c.name AS community_name, u.real_name AS responsible_name
            FROM grids g
            JOIN communities c ON c.id = g.community_id
            LEFT JOIN users u ON u.id = g.responsible_user_id
            WHERE 1=1';
        $gridParams = [];
        if ($user['role'] !== 'admin' && $user['community_id']) {
            $gridSql .= ' AND g.community_id = :community_id';
            $gridParams['community_id'] = (int) $user['community_id'];
        }
        $gridSql .= ' ORDER BY g.id DESC';
        $stmt = $this->db->prepare($gridSql);
        $stmt->execute($gridParams);
        $grids = $stmt->fetchAll();

        $buildingSql = 'SELECT b.*, g.name AS grid_name, c.name AS community_name
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

        $usersSql = 'SELECT id, real_name FROM users WHERE is_active = 1 ORDER BY real_name';
        $stmt = $this->db->query($usersSql);
        $users = $stmt->fetchAll();

        $this->render('grids/index', [
            'title' => '社区网格管理',
            'communities' => $communities,
            'grids' => $grids,
            'buildings' => $buildings,
            'users' => $users,
        ]);
    }
}
