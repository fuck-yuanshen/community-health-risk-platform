<?php

declare(strict_types=1);

use App\Controllers\AlertController;
use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\FamilyController;
use App\Controllers\FollowupController;
use App\Controllers\GridController;
use App\Controllers\InterventionController;
use App\Controllers\MeasurementController;
use App\Controllers\NotificationController;
use App\Controllers\ReferralController;
use App\Controllers\ReportController;
use App\Controllers\ResidentController;
use App\Controllers\RiskController;
use App\Controllers\ScreenController;
use App\Controllers\SystemController;
use App\Core\Auth;
use App\Core\Crypto;
use App\Core\Database;
use App\Core\Logger;
use App\Services\RiskService;

session_start();

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

date_default_timezone_set($config['timezone']);

try {
    $db = Database::connection($config);
    $auth = new Auth($db);
    $logger = new Logger($db);
    $crypto = new Crypto($config['security']['app_key']);
    $riskService = new RiskService();

    $route = (string) query('r', 'dashboard');

    $authController = new AuthController($db, $auth, $logger, $config);

    if (!$auth->check() && !in_array($route, ['login'], true)) {
        redirect('login');
    }

    switch ($route) {
        case 'login':
            $authController->login();
            break;

        case 'logout':
            $authController->logout();
            break;

        case 'dashboard':
            (new DashboardController($db, $auth, $logger, $config))->index();
            break;

        case 'grids':
            (new GridController($db, $auth, $logger, $config))->index();
            break;

        case 'families':
            (new FamilyController($db, $auth, $logger, $config, $crypto))->index();
            break;

        case 'residents':
            (new ResidentController($db, $auth, $logger, $config, $crypto))->index();
            break;

        case 'measurements':
            (new MeasurementController($db, $auth, $logger, $config, $riskService))->index();
            break;

        case 'risks':
            (new RiskController($db, $auth, $logger, $config))->index();
            break;

        case 'alerts':
            (new AlertController($db, $auth, $logger, $config))->index();
            break;

        case 'interventions':
            (new InterventionController($db, $auth, $logger, $config))->index();
            break;

        case 'followups':
            (new FollowupController($db, $auth, $logger, $config))->index();
            break;

        case 'referrals':
            (new ReferralController($db, $auth, $logger, $config))->index();
            break;

        case 'reports':
            (new ReportController($db, $auth, $logger, $config))->index();
            break;

        case 'screen':
            (new ScreenController($db, $auth, $logger, $config))->index();
            break;

        case 'system_users':
            (new SystemController($db, $auth, $logger, $config))->users();
            break;

        case 'system_settings':
            (new SystemController($db, $auth, $logger, $config))->settings();
            break;

        case 'system_logs':
            (new SystemController($db, $auth, $logger, $config))->logs();
            break;

        case 'system_backup':
            (new SystemController($db, $auth, $logger, $config))->backup();
            break;

        case 'notifications_read':
            (new NotificationController($db, $auth, $logger, $config))->markAllRead();
            break;

        default:
            http_response_code(404);
            echo '页面不存在';
            break;
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo '<h2>系统错误</h2>';
    echo '<pre>' . e($e->getMessage()) . '</pre>';
}
