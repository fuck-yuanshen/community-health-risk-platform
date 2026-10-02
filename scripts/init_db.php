<?php

declare(strict_types=1);

use App\Core\Crypto;
use App\Services\RiskService;

require __DIR__ . '/../app/Core/helpers.php';

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $file = __DIR__ . '/../app/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

$config = require __DIR__ . '/../config/config.php';
$db = $config['db'];
$driver = strtolower((string) ($db['driver'] ?? 'mysql'));

if ($driver === 'sqlite') {
    $dbPath = (string) ($db['path'] ?? (dirname(__DIR__) . '/community_health.sqlite'));
    $dbDir = dirname($dbPath);
    if (!is_dir($dbDir)) {
        mkdir($dbDir, 0777, true);
    }

    $pdo = new PDO('sqlite:' . $dbPath, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON');
    $schemaFile = __DIR__ . '/../database/schema_sqlite.sql';
} else {
    $dsnNoDb = sprintf('mysql:host=%s;port=%s;charset=%s', $db['host'], $db['port'], $db['charset']);
    $pdo = new PDO($dsnNoDb, $db['user'], $db['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . $db['dbname'] . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $pdo->exec('USE `' . $db['dbname'] . '`');
    $schemaFile = __DIR__ . '/../database/schema.sql';
}

$schema = file_get_contents($schemaFile);
if ($schema === false) {
    throw new RuntimeException('读取数据库结构文件失败: ' . $schemaFile);
}

$statements = array_filter(array_map('trim', explode(';', $schema)));
foreach ($statements as $statement) {
    if ($statement !== '') {
        $pdo->exec($statement);
    }
}

echo "数据库结构初始化完成\n";

$crypto = new Crypto($config['security']['app_key']);
$riskService = new RiskService();

$settings = [
    'line_yellow' => (string) $config['risk_defaults']['line_yellow'],
    'line_red' => (string) $config['risk_defaults']['line_red'],
    'control_fpg_target' => (string) $config['risk_defaults']['control_fpg_target'],
];

$upsertSettingSql = $driver === 'sqlite'
    ? 'INSERT INTO settings (name, value, updated_at)
        VALUES (:name, :value, CURRENT_TIMESTAMP)
        ON CONFLICT(name) DO UPDATE SET
            value = excluded.value,
            updated_at = CURRENT_TIMESTAMP'
    : 'INSERT INTO settings (name, value)
        VALUES (:name, :value)
        ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = CURRENT_TIMESTAMP';
$stmt = $pdo->prepare($upsertSettingSql);
foreach ($settings as $name => $value) {
    $stmt->execute(['name' => $name, 'value' => $value]);
}

function findOrCreateCommunity(PDO $pdo, string $name): int
{
    $stmt = $pdo->prepare('SELECT id FROM communities WHERE name = :name LIMIT 1');
    $stmt->execute(['name' => $name]);
    $id = $stmt->fetchColumn();
    if ($id) {
        return (int) $id;
    }

    $stmt = $pdo->prepare('INSERT INTO communities (name) VALUES (:name)');
    $stmt->execute(['name' => $name]);
    return (int) $pdo->lastInsertId();
}

function upsertUser(PDO $pdo, string $username, string $password, string $role, string $realName, ?int $communityId): int
{
    $stmt = $pdo->prepare('SELECT id FROM users WHERE username = :username LIMIT 1');
    $stmt->execute(['username' => $username]);
    $exists = $stmt->fetchColumn();

    if ($exists) {
        $stmt = $pdo->prepare(
            'UPDATE users
            SET password_hash = :password_hash, role = :role, real_name = :real_name, community_id = :community_id, is_active = 1
            WHERE id = :id'
        );
        $stmt->execute([
            'id' => $exists,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'role' => $role,
            'real_name' => $realName,
            'community_id' => $communityId,
        ]);
        return (int) $exists;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO users (username, password_hash, role, real_name, community_id, is_active)
        VALUES (:username, :password_hash, :role, :real_name, :community_id, 1)'
    );
    $stmt->execute([
        'username' => $username,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'role' => $role,
        'real_name' => $realName,
        'community_id' => $communityId,
    ]);

    return (int) $pdo->lastInsertId();
}

function findOrCreateGrid(PDO $pdo, int $communityId, string $name, ?int $responsibleUserId): int
{
    $stmt = $pdo->prepare('SELECT id FROM grids WHERE community_id = :community_id AND name = :name LIMIT 1');
    $stmt->execute(['community_id' => $communityId, 'name' => $name]);
    $id = $stmt->fetchColumn();
    if ($id) {
        $stmt = $pdo->prepare('UPDATE grids SET responsible_user_id = :responsible_user_id WHERE id = :id');
        $stmt->execute(['responsible_user_id' => $responsibleUserId, 'id' => $id]);
        return (int) $id;
    }

    $stmt = $pdo->prepare('INSERT INTO grids (community_id, name, responsible_user_id) VALUES (:community_id, :name, :responsible_user_id)');
    $stmt->execute([
        'community_id' => $communityId,
        'name' => $name,
        'responsible_user_id' => $responsibleUserId,
    ]);

    return (int) $pdo->lastInsertId();
}

function findOrCreateBuilding(PDO $pdo, int $gridId, string $name): int
{
    $stmt = $pdo->prepare('SELECT id FROM buildings WHERE grid_id = :grid_id AND name = :name LIMIT 1');
    $stmt->execute(['grid_id' => $gridId, 'name' => $name]);
    $id = $stmt->fetchColumn();
    if ($id) {
        return (int) $id;
    }

    $stmt = $pdo->prepare('INSERT INTO buildings (grid_id, name) VALUES (:grid_id, :name)');
    $stmt->execute(['grid_id' => $gridId, 'name' => $name]);
    return (int) $pdo->lastInsertId();
}

$dongfengId = findOrCreateCommunity($pdo, '东风社区');
$xiangyangId = findOrCreateCommunity($pdo, '向阳社区');

$adminPassword = getenv('HEALTH_ADMIN_PASSWORD') ?: 'change-me-before-use';
$doctorPassword = getenv('HEALTH_DOCTOR_PASSWORD') ?: 'change-me-before-use';
$staffPassword = getenv('HEALTH_STAFF_PASSWORD') ?: 'change-me-before-use';
$adminId = upsertUser($pdo, 'admin', $adminPassword, 'admin', '系统管理员', null);
$doctorId = upsertUser($pdo, 'doctor01', $doctorPassword, 'doctor', '陈明德', $dongfengId);
$publicHealthId = upsertUser($pdo, 'public01', $staffPassword, 'public_health', '周雨桐', $dongfengId);
$nurseId = upsertUser($pdo, 'nurse01', $staffPassword, 'nurse', '刘雅菲', $dongfengId);

$gridA = findOrCreateGrid($pdo, $dongfengId, '第一网格', $doctorId);
$gridB = findOrCreateGrid($pdo, $dongfengId, '第二网格', $publicHealthId);
$gridC = findOrCreateGrid($pdo, $xiangyangId, '滨河网格', null);

$building1 = findOrCreateBuilding($pdo, $gridA, '5号楼');
$building2 = findOrCreateBuilding($pdo, $gridA, '6号楼');
$building3 = findOrCreateBuilding($pdo, $gridB, '2号楼');
$building4 = findOrCreateBuilding($pdo, $gridC, '1号楼');

$residentCount = (int) $pdo->query('SELECT COUNT(*) FROM residents')->fetchColumn();

if ($residentCount === 0) {
    $families = [
        [$building1, '5号楼2单元301', '张建国', '13910001111'],
        [$building1, '5号楼1单元502', '李桂花', '13910002222'],
        [$building2, '6号楼1单元201', '王海英', '13910003333'],
        [$building3, '2号楼3单元601', '赵志强', '13910004444'],
        [$building4, '1号楼2单元102', '孙美华', '13910005555'],
    ];

    $familyIds = [];
    $stmtFamily = $pdo->prepare(
        'INSERT INTO families (building_id, address, head_name, head_phone_enc, archive_status)
        VALUES (:building_id, :address, :head_name, :head_phone_enc, "active")'
    );
    foreach ($families as [$buildingId, $address, $headName, $phone]) {
        $stmtFamily->execute([
            'building_id' => $buildingId,
            'address' => $address,
            'head_name' => $headName,
            'head_phone_enc' => $crypto->encrypt($phone),
        ]);
        $familyIds[] = (int) $pdo->lastInsertId();
    }

    $residents = [
        [$familyIds[0], '张建国', '男', '1968-03-12', '320102196803121235', '13910001111', '东风路8号', '高血压'],
        [$familyIds[0], '张雨欣', '女', '1996-11-09', '320102199611093422', '13910001112', '东风路8号', '无'],
        [$familyIds[1], '李桂花', '女', '1971-06-08', '320103197106085612', '13910002222', '振兴街2号', '糖尿病家族史'],
        [$familyIds[2], '王海英', '女', '1980-02-22', '320104198002227091', '13910003333', '朝阳路5号', '肥胖'],
        [$familyIds[3], '赵志强', '男', '1965-08-16', '320105196508166834', '13910004444', '园区路2号', '高血脂'],
        [$familyIds[4], '孙美华', '女', '1977-05-03', '320106197705034531', '13910005555', '滨河西路1号', '无'],
    ];

    $residentIds = [];
    $stmtResident = $pdo->prepare(
        'INSERT INTO residents (family_id, name, gender, birth_date, id_card_enc, phone_enc, address, chronic_history)
        VALUES (:family_id, :name, :gender, :birth_date, :id_card_enc, :phone_enc, :address, :chronic_history)'
    );
    foreach ($residents as $r) {
        $stmtResident->execute([
            'family_id' => $r[0],
            'name' => $r[1],
            'gender' => $r[2],
            'birth_date' => $r[3],
            'id_card_enc' => $crypto->encrypt($r[4]),
            'phone_enc' => $crypto->encrypt($r[5]),
            'address' => $r[6],
            'chronic_history' => $r[7],
        ]);
        $residentIds[] = (int) $pdo->lastInsertId();
    }

    $measurements = [
        [$residentIds[0], '2026-02-01', 8.6, 12.2, 7.1, 162, 96, 29.8, 2.8, 3.8, 1, 1, 1, 1, '社区筛查'],
        [$residentIds[1], '2026-02-01', 5.2, 6.8, 5.3, 118, 76, 21.4, 1.2, 2.1, 0, 0, 0, 0, '社区筛查'],
        [$residentIds[2], '2026-02-03', 6.5, 8.9, 6.0, 142, 89, 26.8, 1.9, 3.0, 1, 0, 0, 1, '体检导入'],
        [$residentIds[3], '2026-02-04', 7.8, 11.6, 6.8, 158, 94, 30.1, 3.1, 4.2, 1, 1, 1, 1, '门诊录入'],
    ];

    $insertMeasure = $pdo->prepare(
        'INSERT INTO measurements (
            resident_id, check_date, fasting_glucose, ogtt_2h, hba1c, sbp, dbp, bmi,
            triglyceride, ldl, family_history, smoker, drinker, inactivity, source
        ) VALUES (
            :resident_id, :check_date, :fasting_glucose, :ogtt_2h, :hba1c, :sbp, :dbp, :bmi,
            :triglyceride, :ldl, :family_history, :smoker, :drinker, :inactivity, :source
        )'
    );

    $insertRisk = $pdo->prepare(
        'INSERT INTO risk_assessments (resident_id, measurement_id, score, line_color, management_level, prediabetes, reason)
        VALUES (:resident_id, :measurement_id, :score, :line_color, :management_level, :prediabetes, :reason)'
    );

    $insertAlert = $pdo->prepare(
        'INSERT INTO alerts (resident_id, risk_assessment_id, alert_line, title, description, status)
        VALUES (:resident_id, :risk_assessment_id, :alert_line, :title, :description, :status)'
    );

    $insertIntervention = $pdo->prepare(
        'INSERT INTO interventions (resident_id, risk_assessment_id, category, plan_content, status, executor_id)
        VALUES (:resident_id, :risk_assessment_id, :category, :plan_content, :status, :executor_id)'
    );

    $insertTask = $pdo->prepare(
        'INSERT INTO tasks (resident_id, task_type, due_date, status, assignee_id, note)
        VALUES (:resident_id, :task_type, :due_date, :status, :assignee_id, :note)'
    );

    foreach ($measurements as $m) {
        $data = [
            'resident_id' => $m[0],
            'check_date' => $m[1],
            'fasting_glucose' => $m[2],
            'ogtt_2h' => $m[3],
            'hba1c' => $m[4],
            'sbp' => $m[5],
            'dbp' => $m[6],
            'bmi' => $m[7],
            'triglyceride' => $m[8],
            'ldl' => $m[9],
            'family_history' => $m[10],
            'smoker' => $m[11],
            'drinker' => $m[12],
            'inactivity' => $m[13],
            'source' => $m[14],
        ];

        $insertMeasure->execute($data);
        $measurementId = (int) $pdo->lastInsertId();
        $result = $riskService->evaluate($data, $settings);

        $insertRisk->execute([
            'resident_id' => $data['resident_id'],
            'measurement_id' => $measurementId,
            'score' => $result['score'],
            'line_color' => $result['line_color'],
            'management_level' => $result['management_level'],
            'prediabetes' => $result['prediabetes'],
            'reason' => $result['reason'],
        ]);

        $riskId = (int) $pdo->lastInsertId();
        $plans = $riskService->interventionPlan($result['line_color'], $result['management_level']);
        foreach ($plans as $category => $content) {
            $insertIntervention->execute([
                'resident_id' => $data['resident_id'],
                'risk_assessment_id' => $riskId,
                'category' => $category,
                'plan_content' => $content,
                'status' => 'todo',
                'executor_id' => $doctorId,
            ]);
        }

        if (in_array($result['line_color'], ['yellow', 'red'], true)) {
            $insertAlert->execute([
                'resident_id' => $data['resident_id'],
                'risk_assessment_id' => $riskId,
                'alert_line' => $result['line_color'],
                'title' => $result['line_color'] === 'red' ? '红线风险预警' : '黄线风险预警',
                'description' => '风险分值' . $result['score'] . '，请及时跟进处理。',
                'status' => 'pending',
            ]);

            $insertTask->execute([
                'resident_id' => $data['resident_id'],
                'task_type' => '随访提醒',
                'due_date' => $result['line_color'] === 'red' ? date('Y-m-d', strtotime('+14 days')) : date('Y-m-d', strtotime('+30 days')),
                'status' => 'pending',
                'assignee_id' => $doctorId,
                'note' => $result['line_color'] === 'red' ? '高危重点随访' : '黄线复查随访',
            ]);
        }
    }

    $insertFollowup = $pdo->prepare(
        'INSERT INTO followups (resident_id, due_date, visit_date, result_summary, next_due_date, status, created_by)
        VALUES (:resident_id, :due_date, :visit_date, :result_summary, :next_due_date, :status, :created_by)'
    );
    $insertFollowup->execute([
        'resident_id' => $residentIds[0],
        'due_date' => date('Y-m-d', strtotime('-10 days')),
        'visit_date' => date('Y-m-d', strtotime('-8 days')),
        'result_summary' => '空腹血糖仍偏高，建议药物调整。',
        'next_due_date' => date('Y-m-d', strtotime('+20 days')),
        'status' => 'done',
        'created_by' => $doctorId,
    ]);

    $followupId = (int) $pdo->lastInsertId();

    $insertReferral = $pdo->prepare(
        'INSERT INTO referrals (resident_id, followup_id, reason, status, apply_time, review_time, result_note, created_by)
        VALUES (:resident_id, :followup_id, :reason, :status, :apply_time, :review_time, :result_note, :created_by)'
    );
    $insertReferral->execute([
        'resident_id' => $residentIds[0],
        'followup_id' => $followupId,
        'reason' => '连续两次空腹血糖过高，建议专科评估。',
        'status' => 'approved',
        'apply_time' => date('Y-m-d H:i:s', strtotime('-7 days')),
        'review_time' => date('Y-m-d H:i:s', strtotime('-6 days')),
        'result_note' => '已安排上级医院门诊。',
        'created_by' => $publicHealthId,
    ]);

    echo "已写入示例业务数据\n";
}

$logStmt = $pdo->prepare(
    'INSERT INTO audit_logs (user_id, action, target_type, target_id, ip)
    VALUES (:user_id, :action, :target_type, :target_id, :ip)'
);
$logStmt->execute([
    'user_id' => $adminId,
    'action' => '初始化数据库与示例数据',
    'target_type' => 'system',
    'target_id' => null,
    'ip' => '127.0.0.1',
]);

echo "初始化完成。\n";
echo "账号已初始化；密码来自 HEALTH_* 环境变量。\n";
