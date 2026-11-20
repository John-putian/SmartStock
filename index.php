<?php
/**
 * =====================================================================
 *  WEB-BASED INVENTORY, SALES, AND DECISION SUPPORT SYSTEM
 *  A&A Household Appliances Trading - Panabo City, Davao del Norte
 *
 *  IT 307 - Web Systems and Technologies 2
 *  Proponents: Carl Ulrick Duque | Rossen Dale Dizon | John Ray Puti-an
 * =====================================================================
 *  Front controller. Every request enters here and is dispatched to a
 *  controller in /app/controllers, which then renders a view.
 */

require_once __DIR__ . '/config/config.php';

$page = (string) ($_GET['page'] ?? (Auth::check() ? 'dashboard' : 'login'));

/**
 * Access levels, from the widest to the narrowest:
 *
 *   guest   nobody is signed in
 *   any     signed in or not
 *   user    any signed-in account, cashier included
 *   till    the cashier alone - the Point of Sale is their station
 *   staff   an account allowed to change inventory records - today that is
 *           every signed-in account, because the floor lists what it unpacks.
 *           Kept as its own level so tightening it later is one line.
 *   manage  manager or administrator              (Insights, Administration)
 *   admin   administrator only                    (user accounts)
 *
 * A cashier who types ?page=analytics by hand is turned away here, not merely
 * left without a link in the sidebar.
 */
$routes = [
    // page              => [controller file,          function,        access]
    'login'              => ['AuthController',      'login',            'guest'],
    'logout'             => ['AuthController',      'logout',           'any'],
    'dashboard'          => ['DashboardController', 'index',            'user'],

    'inventory'          => ['InventoryController', 'index',            'user'],
    'inventory.view'     => ['InventoryController', 'show',             'user'],
    'inventory.create'   => ['InventoryController', 'create',           'staff'],
    'inventory.edit'     => ['InventoryController', 'edit',             'staff'],
    'inventory.archive'  => ['InventoryController', 'archive',          'manage'],
    'inventory.stockin'  => ['InventoryController', 'stockIn',          'staff'],
    'inventory.export'   => ['InventoryController', 'export',           'user'],
    'inventory.qr'       => ['InventoryController', 'qr',               'user'],
    'inventory.units'    => ['InventoryController', 'unitLabels',       'user'],
    'inventory.unit'     => ['InventoryController', 'updateUnit',       'staff'],
    'inventory.lookup'   => ['InventoryController', 'lookup',           'user'],
    'inventory.describe' => ['InventoryController', 'describe',         'staff'],
    'inventory.price'    => ['InventoryController', 'suggestPrice',     'staff'],

    'approvals'          => ['ApprovalController',  'index',            'user'],
    'approvals.decide'   => ['ApprovalController',  'decide',           'manage'],
    'approvals.discount' => ['ApprovalController',  'decideDiscount',   'manager'],

    'notifications'      => ['NotificationController', 'index',         'user'],
    'notifications.open' => ['NotificationController', 'open',          'user'],
    'notifications.read' => ['NotificationController', 'readAll',       'user'],

    'categories'         => ['CategoryController',  'index',            'manage'],
    'categories.save'    => ['CategoryController',  'save',             'manage'],
    'categories.delete'  => ['CategoryController',  'delete',           'manage'],

    // FR-04 - the till belongs to the cashier. A receipt stays readable by
    // the office, because the sales report links straight to one.
    'pos'                => ['PosController',       'index',            'till'],
    'pos.add'            => ['PosController',       'add',              'till'],
    'pos.scan'           => ['PosController',       'addUnit',          'till'],
    'pos.update'         => ['PosController',       'updateQty',        'till'],
    'pos.remove'         => ['PosController',       'remove',           'till'],
    'pos.clear'          => ['PosController',       'clear',            'till'],
    'pos.checkout'       => ['PosController',       'checkout',         'till'],
    'pos.discount'       => ['PosController',       'requestDiscount',  'till'],
    'pos.discount.cancel'=> ['PosController',       'cancelDiscount',   'till'],
    'pos.receipt'        => ['PosController',       'receipt',          'user'],

    'alerts'             => ['AlertController',     'index',            'user'],

    // Insights. The cashier and the work staff watch stock come in and go out;
    // reading the takings is the office's job.
    'reports'            => ['ReportController',    'index',            'manage'],
    'reports.sales'      => ['ReportController',    'sales',            'manage'],
    'reports.category'   => ['ReportController',    'category',         'manage'],
    'reports.inventory'  => ['ReportController',    'inventory',        'manage'],
    'reports.movement'   => ['ReportController',    'movement',         'manage'],
    'reports.print'      => ['ReportController',    'printable',        'manage'],
    'reports.export'     => ['ReportController',    'export',           'manage'],

    'analytics'          => ['AnalyticsController', 'index',            'manage'],

    'mail'               => ['MailController',      'index',            'admin'],
    'mail.preview'       => ['MailController',      'preview',          'admin'],
    'mail.test'          => ['MailController',      'test',             'admin'],
    'mail.address'       => ['MailController',      'saveAddress',      'admin'],

    'users'              => ['UserController',      'index',            'admin'],
    'users.save'         => ['UserController',      'save',             'admin'],
    'users.delete'       => ['UserController',      'delete',           'admin'],

    'audit'              => ['AuditController',     'index',            'manage'],

    'help'               => ['HelpController',      'index',            'user'],
    'help.connect'       => ['HelpController',      'connect',          'user'],
];

if (!isset($routes[$page])) {
    http_response_code(404);
    $page = Auth::check() ? 'dashboard' : 'login';
}

[$controller, $action, $access] = $routes[$page];
$GLOBALS['current_page'] = $page;

// The Help screen builds its permission table from this very list, so what it
// shows people is what the router actually enforces - the two cannot drift.
$GLOBALS['routes'] = $routes;

switch ($access) {
    case 'admin':
        Auth::requireAdmin();
        break;
    case 'manager':
        Auth::requireLogin();
        if (!Auth::canApproveDiscount()) Auth::deny('Only the administrator or a Store Manager may decide discount requests.');
        break;
    case 'manage':
        Auth::requireManage();
        break;
    case 'till':
        Auth::requireTill();
        break;
    case 'staff':
        Auth::requireStaff();
        break;
    case 'user':
        Auth::requireLogin();
        break;
    case 'guest':
        if (Auth::check()) redirect('dashboard');
        break;
}

$mutations = ['logout', 'inventory.describe', 'inventory.price', 'inventory.archive', 'inventory.stockin', 'inventory.unit',
    'approvals.decide', 'approvals.discount', 'notifications.read', 'categories.save',
    'categories.delete', 'pos.add', 'pos.scan', 'pos.update', 'pos.remove', 'pos.clear',
    'pos.checkout', 'pos.discount', 'pos.discount.cancel', 'mail.test', 'mail.address',
    'users.save', 'users.delete'];
if (in_array($page, $mutations, true)) verify_csrf();

/**
 * A XAMPP install has no scheduler, so the end-of-day takings digest is
 * produced lazily: the first signed-in page load of a new day writes
 * yesterday's summary if it is missing. The call guards itself internally and
 * costs one indexed lookup once the digest exists.
 */
if (Auth::check()) {
    Notification::dailyIncomeDigest();
}

require_once APP_PATH . '/controllers/' . $controller . '.php';
call_user_func([$controller, $action]);
