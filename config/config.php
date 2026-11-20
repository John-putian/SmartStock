<?php
/**
 * Application bootstrap.
 * Web-Based Inventory, Sales, and Decision Support System
 * A&A Household Appliances Trading
 */

declare(strict_types=1);

define('APP_NAME',    'Smart Stock — Inventory, Sales & Decision Support System');
define('APP_SHORT',   'Smart Stock');
define('APP_CLIENT',  'A&A Surplus — Household Appliances Trading, Panabo City');
define('APP_VERSION', '1.0');
define('BASE_PATH',   dirname(__DIR__));
define('APP_PATH',    BASE_PATH . '/app');

/**
 * Demonstration mode.
 *
 * When on, the sign-in page offers one button per role instead of asking you
 * to type a seeded password. It is a classroom and presentation convenience:
 * the buttons name the demo accounts and sign them in, so it must NOT be on
 * when the system is handed to the client.
 *
 * It is on while the file DEMO-MODE-ON exists in the project root, or while
 * the SMARTSTOCK_DEMO environment variable is 1. To turn it off, delete
 * DEMO-MODE-ON. Nothing else changes: the panel stops being drawn and the
 * ordinary username and password form is all that is left. No account, role
 * or password is altered either way.
 */
define('DEMO_MODE', getenv('SMARTSTOCK_DEMO') === '1'
                    || is_file(BASE_PATH . '/DEMO-MODE-ON'));

date_default_timezone_set('Asia/Manila');

// Show errors while developing on XAMPP. Set to 0 for production.
ini_set('display_errors', getenv('SMARTSTOCK_DEBUG') === '1' ? '1' : '0');
error_reporting(E_ALL);

session_name('AAISDSS');
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true, 'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_start();
}

/** Base URL of the application, e.g. /aa-inventory */
function base_url(string $path = ''): string
{
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    return $dir . '/' . ltrim($path, '/');
}

/** Build a link to a page handled by the front controller. */
function url(string $page, array $params = []): string
{
    $params = array_merge(['page' => $page], $params);
    return base_url('index.php') . '?' . http_build_query($params);
}

require_once APP_PATH . '/core/Database.php';
require_once APP_PATH . '/core/Helpers.php';
require_once APP_PATH . '/core/Xlsx.php';
require_once APP_PATH . '/core/Auth.php';
require_once APP_PATH . '/core/Audit.php';
require_once APP_PATH . '/core/Mailer.php';
require_once APP_PATH . '/core/PhotoAssistant.php';

foreach (glob(APP_PATH . '/models/*.php') as $model) {
    require_once $model;
}
