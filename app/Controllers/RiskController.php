<?php

declare(strict_types=1);

namespace App\Controllers;

final class RiskController extends BaseController
{
    public function index(): void
    {
        $this->requirePermission('risks');

        $scope = $this->scopeCondition('r');
        $residentId = (int) query('resident_id', 0);

        $sql = 'SELECT ra.*, r.name AS resident_name, m.check_date
            FROM risk_assessments ra
            JOIN residents r ON r.id = ra.resident_id
            LEFT JOIN measurements m ON m.id = ra.measurement_id
            WHERE 1=1' . $scope['sql'];

        $params = $scope['params'];
        if ($residentId > 0) {
            $sql .= ' AND ra.resident_id = :resident_id';
            $params['resident_id'] = $residentId;
        }

        $sql .= ' ORDER BY ra.id DESC LIMIT 300';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $assessments = $stmt->fetchAll();

        $residentStmt = $this->db->prepare(
            'SELECT r.id, r.name
            FROM residents r
            JOIN families f ON f.id = r.family_id
            JOIN buildings b ON b.id = f.building_id
            JOIN grids g ON g.id = b.grid_id
            WHERE r.archived_at IS NULL' . $scope['sql'] . '
            ORDER BY r.id DESC LIMIT 300'
        );
        $residentStmt->execute($scope['params']);
        $residents = $residentStmt->fetchAll();

        $trendLabels = [];
        $trendScores = [];
        if ($residentId > 0) {
            $stmt = $this->db->prepare(
                'SELECT ra.score, ra.line_color, m.check_date
                FROM risk_assessments ra
                LEFT JOIN measurements m ON m.id = ra.measurement_id
                JOIN residents r ON r.id = ra.resident_id
                WHERE ra.resident_id = :resident_id' . $scope['sql'] . '
                ORDER BY ra.id ASC LIMIT 20'
            );
            $params2 = array_merge(['resident_id' => $residentId], $scope['params']);
            $stmt->execute($params2);
            foreach ($stmt->fetchAll() as $item) {
                $trendLabels[] = $item['check_date'] ? date('m-d', strtotime($item['check_date'])) : '-';
                $trendScores[] = (int) $item['score'];
            }
        }

        $this->render('risks/index', [
            'title' => '风险分线分级评估',
            'assessments' => $assessments,
            'residents' => $residents,
            'residentId' => $residentId,
            'trendLabels' => $trendLabels,
            'trendScores' => $trendScores,
        ]);
    }
}
