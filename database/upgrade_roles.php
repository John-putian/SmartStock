<?php
/**
 * One-shot upgrade to the four-role model.
 *
 * This is a MIGRATION, not a re-install: it adds what the new permission rules
 * need and leaves every product, sale and audit line already in the database
 * exactly where it is. Running it twice is harmless - each step checks whether
 * it has already been done.
 *
 * It is protected by a token and is meant to be overwritten with a disabled
 * stub the moment it has run. Nothing should be left executable in htdocs.
 */
require_once __DIR__ . '/../config/config.php';

const RUN_TOKEN = '__TOKEN__';

if (($_GET['run'] ?? '') !== RUN_TOKEN) {
    http_response_code(403);
    exit('Not available.');
}

header('Content-Type: text/plain; charset=utf-8');
$pdo  = Database::pdo();
$done = [];
$skip = [];

function col_exists(PDO $pdo, string $table, string $col): bool
{
    $s = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
    $s->execute([$table, $col]);
    return (int) $s->fetchColumn() > 0;
}

function table_exists(PDO $pdo, string $table): bool
{
    $s = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
    $s->execute([$table]);
    return (int) $s->fetchColumn() > 0;
}

// 1 -------------------------------------------------- the role column itself
$type = $pdo->query(
    "SELECT COLUMN_TYPE FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'role'")
    ->fetchColumn();

if (strpos((string) $type, "'manager'") === false) {
    $pdo->exec("ALTER TABLE users
                MODIFY role ENUM('admin','manager','staff','cashier')
                NOT NULL DEFAULT 'cashier'");
    $done[] = 'users.role now accepts admin / manager / staff / cashier';
} else {
    $skip[] = 'users.role already carries the four roles';
}

// 2 ------------------------------------------- who authorised a discount
if (!col_exists($pdo, 'sales', 'discount_approved_by')) {
    $pdo->exec('ALTER TABLE sales
                ADD COLUMN discount_approved_by INT DEFAULT NULL AFTER payment_method,
                ADD CONSTRAINT fk_sales_approver FOREIGN KEY (discount_approved_by)
                    REFERENCES users(id) ON DELETE SET NULL');
    $done[] = 'sales.discount_approved_by added';
} else {
    $skip[] = 'sales.discount_approved_by already present';
}

// 3 --------------------------------------------- the price request queue
if (!table_exists($pdo, 'price_requests')) {
    $pdo->exec("CREATE TABLE `price_requests` (
      `id`            INT AUTO_INCREMENT PRIMARY KEY,
      `product_id`    INT           NOT NULL,
      `requested_by`  INT           DEFAULT NULL,
      `old_price`     DECIMAL(12,2) NOT NULL DEFAULT 0.00,
      `new_price`     DECIMAL(12,2) NOT NULL DEFAULT 0.00,
      `reason`        VARCHAR(255)  DEFAULT NULL,
      `status`        ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
      `decided_by`    INT           DEFAULT NULL,
      `decision_note` VARCHAR(255)  DEFAULT NULL,
      `decided_at`    DATETIME      DEFAULT NULL,
      `created_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
      CONSTRAINT `fk_pricereq_product` FOREIGN KEY (`product_id`)
          REFERENCES `products`(`id`) ON DELETE CASCADE,
      CONSTRAINT `fk_pricereq_asker` FOREIGN KEY (`requested_by`)
          REFERENCES `users`(`id`) ON DELETE SET NULL,
      CONSTRAINT `fk_pricereq_judge` FOREIGN KEY (`decided_by`)
          REFERENCES `users`(`id`) ON DELETE SET NULL,
      INDEX `idx_pricereq_status` (`status`),
      INDEX `idx_pricereq_date`   (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $done[] = 'price_requests table created';
} else {
    $skip[] = 'price_requests table already exists';
}

// 4 ------------------------------- the account named "manager" becomes one
$n = $pdo->exec("UPDATE users SET role = 'manager'
                 WHERE username = 'manager' AND role = 'admin'");
$n ? $done[] = 'the "manager" account now holds the Store Manager role'
   : $skip[] = 'no admin account named "manager" needed changing';

// 5 ------------------------------------------- a work-staff account to test with
$has = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'staff'")->fetchColumn();
if (!$has) {
    $exists = $pdo->query("SELECT COUNT(*) FROM users WHERE username = 'staff'")->fetchColumn();
    if ($exists) {
        $pdo->exec("UPDATE users SET role = 'staff' WHERE username = 'staff'");
        $done[] = 'the existing "staff" account now holds the Work Staff role';
    } else {
        $s = $pdo->prepare(
            'INSERT INTO users (full_name, username, password_hash, role, contact_no, status)
             VALUES (?,?,?,?,?,?)');
        $s->execute(['Rico Andrade Selma', 'staff',
                     password_hash('staff123', PASSWORD_DEFAULT),
                     'staff', '0917-555-0105', 'active']);
        $done[] = 'work staff account created - username "staff", password "staff123"';
    }
} else {
    $skip[] = 'a work staff account already exists';
}

// 6 ------------- discounts recorded before this rule existed have no approver
$orphans = (int) $pdo->query(
    'SELECT COUNT(*) FROM sales WHERE discount > 0 AND discount_approved_by IS NULL')
    ->fetchColumn();

if ($orphans) {
    $who = $pdo->query(
        "SELECT id FROM users WHERE role IN ('manager','admin') AND status = 'active'
         ORDER BY FIELD(role,'manager','admin'), id LIMIT 1")->fetchColumn();
    if ($who) {
        $s = $pdo->prepare(
            'UPDATE sales SET discount_approved_by = ?
             WHERE discount > 0 AND discount_approved_by IS NULL');
        $s->execute([(int) $who]);
        $done[] = $orphans . ' older discounted sale(s) attributed to the manager on record, '
                . 'so the sales report has no blanks. Every discount from now on '
                . 'carries the person who actually authorised it.';
    }
} else {
    $skip[] = 'every discounted sale already names its approver';
}

// ---------------------------------------------------------------- report
echo "SMART STOCK - ROLE SEPARATION UPGRADE\n";
echo str_repeat('=', 60), "\n\n";
echo "Changed:\n";
foreach ($done ?: ['(nothing - the database was already up to date)'] as $d) {
    echo "  + $d\n";
}
echo "\nAlready in place:\n";
foreach ($skip ?: ['(none)'] as $s) {
    echo "  . $s\n";
}

$roles = $pdo->query('SELECT role, COUNT(*) n FROM users GROUP BY role')->fetchAll();
echo "\nAccounts by role:\n";
foreach ($roles as $r) {
    echo '  ', str_pad($r['role'], 10), $r['n'], "\n";
}
echo "\nProducts, sales and audit history were not touched.\n";
echo "You can sign in again now.\n";
