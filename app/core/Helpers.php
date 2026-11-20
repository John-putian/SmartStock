<?php
/** Shared view / request helper functions. */

/**
 * URL for a CSS/JS asset with a cache-busting version stamp, so a browser
 * can never serve a stale stylesheet after the file has been updated.
 */
function asset_url(string $path): string
{
    $file = BASE_PATH . '/' . ltrim($path, '/');
    $v    = is_file($file) ? filemtime($file) : APP_VERSION;
    return base_url($path) . '?v=' . $v;
}

/** Escape output. */
function e($v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

/** Encode data safely inside inline scripts and quoted event handlers. */
function js_json($value, int $flags = 0): string
{
    return json_encode($value, $flags | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE);
}

/** Format a peso amount. */
function peso($v, bool $symbol = true): string
{
    $n = number_format((float) $v, 2);
    return $symbol ? '&#8369;' . $n : $n;
}

function num($v): string
{
    return number_format((float) $v);
}

/** Read a GET/POST value. */
function input(string $key, $default = null)
{
    $value = $_POST[$key] ?? $_GET[$key] ?? $default;
    return is_array($value) && !is_array($default) ? $default : $value;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

/** Redirect to an application page then stop. */
function redirect(string $page, array $params = []): void
{
    header('Location: ' . url($page, $params));
    exit;
}

/** Flash messages (survive one redirect). */
function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function take_flash(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

/** CSRF token helpers. */
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(24));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . csrf_token() . '">';
}

function verify_csrf(): void
{
    if (!is_post() || empty($_SESSION['csrf']) || !is_string($_POST['_token'] ?? null)
        || !hash_equals($_SESSION['csrf'], $_POST['_token'])) {
        http_response_code(419);
        exit('Session expired. Please go back and try again.');
    }
}

/** Stock status label for a product row. */
function stock_status(int $qty, int $reorder): array
{
    if ($qty <= 0)         return ['Out of Stock', 'danger'];
    if ($qty <= $reorder)  return ['Low Stock',    'warning'];
    return ['In Stock', 'success'];
}

/** Human wording for a user role. */
function role_label(string $role): string
{
    return [
        'admin'   => 'Administrator',
        'manager' => 'Store Manager',
        'staff'   => 'Work Staff',
        'cashier' => 'Cashier',
    ][$role] ?? 'Cashier';
}

/** One line describing what a role is for, used on the accounts screen. */
function role_summary(string $role): string
{
    return [
        'admin'   => 'System administration and user accounts. Discount approval belongs only to managers.',
        'manager' => 'Everything except user accounts. Authorises price changes and discounts.',
        'staff'   => 'Receives stock, adds items and prices them, and may raise a price. Lowering one needs approval.',
        'cashier' => 'Sells at the till and adds items. Changing a listed price needs approval. No access to Insights.',
    ][$role] ?? '';
}

/** Badge colour for a role. */
function role_class(string $role): string
{
    return [
        'admin'   => 'primary',
        'manager' => 'info',
        'staff'   => 'warning',
        'cashier' => 'secondary',
    ][$role] ?? 'secondary';
}

function condition_class(string $c): string
{
    return [
        'Class A'   => 'success',
        'Class B'   => 'info',
        'Class C'   => 'warning',
        'For Parts' => 'secondary',
    ][$c] ?? 'secondary';
}

/**
 * Resolve a product image to a browser URL, or null when the item has none.
 * Second-hand goods are one-of-a-kind, so the picture stored against an item
 * is a photograph of the actual unit on the floor.
 */
function product_image(?string $path, bool $thumb = false): ?string
{
    $path = trim((string) $path);
    if ($path === '' || str_contains($path, '..')) {
        return null;
    }
    if ($thumb) {
        $t = preg_replace('/\.(jpe?g|png|webp)$/i', '-thumb.$1', $path);
        if ($t && is_file(BASE_PATH . '/assets/img/' . $t)) {
            $path = $t;
        }
    }
    return is_file(BASE_PATH . '/assets/img/' . $path)
        ? base_url('assets/img/' . $path)
        : null;
}

/** Bootstrap icon that stands in for a category when an item has no photo. */
function category_icon(?string $category): string
{
    $c = strtolower((string) $category);
    foreach ([
        'furniture'   => 'lamp',
        'kitchenware' => 'cup-hot',
        'dining'      => 'cup-hot',
        'decor'       => 'flower1',
        'musical'     => 'music-note-beamed',
        'sports'      => 'trophy',
        'major'       => 'plug',
        'kitchen app' => 'plug',
        'electronics' => 'tv',
        'accessories' => 'fan',
    ] as $needle => $icon) {
        if (str_contains($c, $needle)) return $icon;
    }
    return 'box-seam';
}

/** Read a value from the settings table (cached per request). */
function setting(string $key, string $default = ''): string
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (Database::all('SELECT setting_key, setting_value FROM settings') as $r) {
            $cache[$r['setting_key']] = $r['setting_value'];
        }
    }
    return $cache[$key] ?? $default;
}

/** Render a view file inside the main layout. */
function view(string $template, array $data = [], ?string $title = null): void
{
    extract($data, EXTR_SKIP);
    $pageTitle = $title ?? APP_SHORT;
    $viewFile  = APP_PATH . '/views/' . $template . '.php';
    require APP_PATH . '/views/layouts/header.php';
    require $viewFile;
    require APP_PATH . '/views/layouts/footer.php';
}

/** Render a view with no navigation chrome (login, receipts, print views). */
function view_bare(string $template, array $data = [], ?string $title = null): void
{
    extract($data, EXTR_SKIP);
    $pageTitle = $title ?? APP_SHORT;
    require APP_PATH . '/views/' . $template . '.php';
}

/** Send an array of rows to the browser as a CSV download. */
/**
 * Send a report as a spreadsheet.
 *
 * Excel is the default, because a CSV cannot carry column widths or number
 * formats: opened in Excel it showed every date as ######## and every peso
 * figure as a bare number. CSV is still available with &format=csv for anyone
 * who wants the raw rows.
 *
 * $spec is an Xlsx::send() spec - see app/core/Xlsx.php.
 */
function export_table(string $basename, array $spec): void
{
    $rows = $spec['rows'] ?? [];

    if ((string) input('format', 'xlsx') === 'csv') {
        $labels = [];
        foreach ($spec['columns'] ?? [] as $c) { $labels[] = (string) ($c['label'] ?? ''); }
        export_csv($basename . '.csv', $labels, $rows);
    }

    Xlsx::send($basename . '.xlsx', $spec);
}

function export_csv(string $filename, array $headers, array $rows): void
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    // Excel on Windows ignores the charset in the HTTP header and reads a CSV
    // as the system codepage, which turns a peso sign into "â‚±" and a name
    // like Muñoz into "MuÃ±oz". The byte-order mark is what tells it UTF-8.
    echo "\xEF\xBB\xBF";

    $out = fopen('php://output', 'w');

    // PHP 8.4 deprecates calling fputcsv() without the escape argument, and the
    // notice would be written straight into the download. Pass it explicitly.
    fputcsv($out, $headers, ',', '"', '');
    foreach ($rows as $r) {
        $cells = array_map(function ($value) {
            return is_string($value) && preg_match('/^[=+@\\-\\t\\r\\n]/', $value) ? "'" . $value : $value;
        }, array_values($r));
        fputcsv($out, $cells, ',', '"', '');
    }
    fclose($out);
    exit;
}
