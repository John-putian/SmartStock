<?php
/**
 * Smart Stock - MariaDB & 3NF Normalization Explorer
 * Advanced Database Systems demonstration page.
 *
 * Read-only. Shows the tables built by database/ads/normalization_demo.sql
 * in the separate `aa_normalization_demo` database, stage by stage
 * (UNF -> 1NF -> 2NF -> 3NF), plus two live proofs. It never writes, and it
 * does not touch the `aa_inventory` data the shop runs on.
 *
 * Opens only from this computer (localhost), so it cannot be reached by
 * other devices on the shop Wi-Fi.
 *
 *   http://localhost/SmartStock/normalization-explorer.php
 */

$remote = $_SERVER['REMOTE_ADDR'] ?? '';
if (!in_array($remote, ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    exit('The Normalization Explorer opens only on this computer (localhost).');
}

$cfg = require __DIR__ . '/config/database.php';
$DEMO_DB = 'aa_normalization_demo';

function h($v): string { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }

try {
    $pdo = new PDO(
        "mysql:host={$cfg['host']};port={$cfg['port']};dbname={$DEMO_DB};charset=utf8mb4",
        $cfg['username'], $cfg['password'] ?? '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
} catch (Throwable $e) {
    http_response_code(500);
    exit('Run database/ads/normalization_demo.sql first - the aa_normalization_demo database was not found.');
}

/* ---- What each view shows, in the order it is presented ---- */
$VIEWS = [
    'unf_sales_register' => ['UNF', 'Unnormalized Form',
        'One row per receipt, as written in a paper sales register. The items are a <b>repeating group</b> packed into multi-valued columns, cashier details repeat on every receipt, and there is <b>no primary key</b>.'],
    'nf1_sales_lines'    => ['1NF', 'First Normal Form',
        'Every column holds <b>one atomic value</b> and there are no repeating groups: each receipt is split into one row per item. Primary key: the composite <b>(invoice_no, sku)</b>. Cashier and product details still repeat on every line.'],
    'nf2_sales'          => ['2NF', 'Second Normal Form - receipt header',
        'Removed the <b>partial dependency</b> invoice_no &rarr; sale_date, cashier, customer, payment, total. These depend on the receipt only, so they live once per receipt.'],
    'nf2_products'       => ['2NF', 'Second Normal Form - products',
        'Removed the <b>partial dependency</b> sku &rarr; product_name, category. These depend on the product only.'],
    'nf2_sale_items'     => ['2NF', 'Second Normal Form - sale lines',
        'Only <b>quantity</b> and <b>unit_price</b> depend on the whole key (invoice_no, sku). unit_price stays on the line: it is the price <i>this</i> sale was charged.'],
    'users'              => ['3NF', 'Third Normal Form - users',
        'Removed the <b>transitive dependency</b> invoice_no &rarr; cashier_username &rarr; cashier_name, role. Each cashier is stored once.'],
    'categories'         => ['3NF', 'Third Normal Form - categories',
        'Removed the <b>transitive dependency</b> sku &rarr; category_name &rarr; category_code. Each category is stored once.'],
    'products'           => ['3NF', 'Third Normal Form - products',
        'Each product stored once, linked to its category by the foreign key <b>category_id &rarr; categories.id</b>.'],
    'sales'              => ['3NF', 'Third Normal Form - sales',
        'Receipt header with the foreign key <b>user_id &rarr; users.id</b> instead of copied cashier fields.'],
    'sale_items'         => ['3NF', 'Third Normal Form - sale items',
        'Each line points to its receipt (<b>sale_id &rarr; sales.id</b>) and its product (<b>product_id &rarr; products.id</b>).'],
    'proof_lossless'     => ['PROOF', 'Lossless decomposition',
        'All 3NF tables joined back together and compared with the 1NF table. Both counts must be <b>0</b>: no row lost, none invented.'],
    'proof_live_fk'      => ['PROOF', 'Matches the live Smart Stock database',
        'The same foreign keys, read from <b>information_schema</b> of the real <b>aa_inventory</b> database the shop runs on.'],
];

$t = $_GET['t'] ?? 'unf_sales_register';
if (!isset($VIEWS[$t])) { $t = 'unf_sales_register'; }

/* ---- Keys of every demo table, for the PK / FK badges ---- */
$keys = [];
foreach ($pdo->query(
    "SELECT TABLE_NAME, COLUMN_NAME, CONSTRAINT_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
       FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = " . $pdo->quote($DEMO_DB)) as $k) {
    $tag = $k['CONSTRAINT_NAME'] === 'PRIMARY' ? 'PK'
         : ($k['REFERENCED_TABLE_NAME'] ? 'FK &rarr; ' . h($k['REFERENCED_TABLE_NAME']) . '.' . h($k['REFERENCED_COLUMN_NAME']) : 'UNIQUE');
    $keys[$k['TABLE_NAME']][$k['COLUMN_NAME']][] = $tag;
}

$counts = [];
foreach ($VIEWS as $name => $v) {
    if ($v[0] !== 'PROOF') {
        $counts[$name] = (int) $pdo->query("SELECT COUNT(*) FROM `$name`")->fetchColumn();
    }
}

/* ---- The rows to show ---- */
$title = $DEMO_DB . '.' . $t;
$sqlShown = '';
if ($t === 'proof_lossless') {
    $sqlShown = "WITH rebuilt AS (SELECT ... FROM sale_items JOIN sales JOIN users JOIN products JOIN categories)\n"
              . "SELECT (nf1_sales_lines EXCEPT rebuilt) AS rows_missing, (rebuilt EXCEPT nf1_sales_lines) AS rows_invented";
    $rows = $pdo->query("
        WITH rebuilt AS (
          SELECT s.invoice_no, p.sku, s.sale_date, u.username AS cashier_username,
                 u.full_name AS cashier_name, u.role AS cashier_role, s.customer_name,
                 s.payment_method, p.name AS product_name, c.name AS category_name,
                 c.code AS category_code, i.quantity, i.unit_price, s.total
          FROM sale_items i
          JOIN sales s ON s.id = i.sale_id JOIN users u ON u.id = s.user_id
          JOIN products p ON p.id = i.product_id JOIN categories c ON c.id = p.category_id)
        SELECT (SELECT COUNT(*) FROM nf1_sales_lines) AS nf1_rows,
               (SELECT COUNT(*) FROM rebuilt) AS rebuilt_rows,
               (SELECT COUNT(*) FROM (SELECT * FROM nf1_sales_lines EXCEPT SELECT * FROM rebuilt) a) AS rows_missing_after_join,
               (SELECT COUNT(*) FROM (SELECT * FROM rebuilt EXCEPT SELECT * FROM nf1_sales_lines) b) AS rows_invented_by_join")->fetchAll();
} elseif ($t === 'proof_live_fk') {
    $title = 'aa_inventory (live) - foreign keys';
    $sqlShown = "SELECT ... FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = 'aa_inventory'";
    $rows = $pdo->query("
        SELECT TABLE_NAME AS child_table, COLUMN_NAME AS foreign_key,
               REFERENCED_TABLE_NAME AS parent_table, REFERENCED_COLUMN_NAME AS parent_key,
               CONSTRAINT_NAME AS constraint_name
        FROM information_schema.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = 'aa_inventory'
          AND TABLE_NAME IN ('products','sales','sale_items')
          AND REFERENCED_TABLE_NAME IN ('categories','users','products','sales')
          AND COLUMN_NAME <> 'discount_approved_by'
        ORDER BY child_table, foreign_key")->fetchAll();
} else {
    $order = [
        'unf_sales_register' => 'invoice_no DESC', 'nf1_sales_lines' => 'invoice_no DESC, sku',
        'nf2_sales' => 'invoice_no DESC', 'nf2_products' => 'sku', 'nf2_sale_items' => 'invoice_no DESC, sku',
    ][$t] ?? 'id';
    $sqlShown = "SELECT * FROM `$t` ORDER BY $order;";
    $rows = $pdo->query("SELECT * FROM `$t` ORDER BY $order")->fetchAll();
}
$cols = $rows ? array_keys($rows[0]) : [];
[$stage, $stageName, $rule] = $VIEWS[$t];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Smart Stock - MariaDB &amp; 3NF Normalization Explorer</title>
<style>
  :root { --bg:#0b0f14; --panel:#121820; --panel2:#18202b; --line:#253041; --text:#e6edf3;
          --mute:#8b98a8; --red:#e5484d; --red2:#c41114; --ok:#3fb950; --amber:#e3b341; --blue:#58a6ff; }
  * { box-sizing:border-box; }
  body { margin:0; background:var(--bg); color:var(--text); font:14px/1.45 "Segoe UI", Inter, system-ui, sans-serif; }
  header { display:flex; align-items:center; gap:14px; padding:14px 22px; border-bottom:1px solid var(--line); background:#0e131a; }
  .logo { width:34px; height:34px; border-radius:50%; background:var(--red2); display:grid; place-items:center; font-weight:800; font-size:13px; }
  header h1 { font-size:19px; margin:0; font-weight:700; }
  header h1 span { color:var(--mute); font-weight:500; }
  .live { margin-left:auto; font-size:12px; color:var(--ok); border:1px solid #23863633; background:#2386361a; padding:5px 10px; border-radius:20px; }
  .cards { display:grid; grid-template-columns:repeat(3,1fr); gap:14px; padding:14px 22px; }
  .card { background:var(--panel); border:1px solid var(--line); border-radius:10px; padding:12px 14px; }
  .card h3 { margin:0 0 6px; font-size:13px; color:var(--blue); }
  .card p { margin:0; font-size:12px; color:var(--mute); }
  .wrap { display:grid; grid-template-columns:270px 1fr; gap:14px; padding:0 22px 22px; }
  nav { background:var(--panel); border:1px solid var(--line); border-radius:10px; padding:10px; align-self:start; }
  nav .grp { font-size:11px; letter-spacing:.08em; color:var(--mute); margin:10px 6px 6px; text-transform:uppercase; }
  nav a { display:flex; justify-content:space-between; align-items:center; gap:8px; padding:7px 10px; margin:2px 0;
          border-radius:7px; color:var(--text); text-decoration:none; font-family:Consolas, "DejaVu Sans Mono", monospace; font-size:12.5px; }
  nav a:hover { background:var(--panel2); }
  nav a.on { background:#c4111426; outline:1px solid #e5484d66; }
  nav a .n { color:var(--mute); font-size:11px; }
  main { background:var(--panel); border:1px solid var(--line); border-radius:10px; padding:16px 18px; min-width:0; }
  .stage { display:inline-block; font-weight:800; font-size:12px; padding:3px 9px; border-radius:6px; background:var(--red2); margin-right:8px; }
  .stage.PROOF { background:#238636; }
  main h2 { margin:0; font-size:17px; display:flex; align-items:center; }
  .tname { font-family:Consolas, "DejaVu Sans Mono", monospace; color:var(--amber); font-size:13px; margin:8px 0 10px; }
  .rule { background:var(--panel2); border-left:3px solid var(--red); padding:9px 12px; border-radius:6px; color:#c9d4df; margin-bottom:10px; }
  pre.sql { background:#0b0f14; border:1px solid var(--line); color:#7ee787; padding:8px 12px; border-radius:6px; font-size:12px; margin:0 0 12px; white-space:pre-wrap; }
  .meta { color:var(--mute); font-size:12px; margin-bottom:8px; }
  .grid { overflow:auto; border:1px solid var(--line); border-radius:8px; }
  table { border-collapse:collapse; width:100%; font-family:Consolas, "DejaVu Sans Mono", monospace; font-size:12.5px; }
  th, td { padding:7px 10px; border-bottom:1px solid var(--line); text-align:left; white-space:nowrap; vertical-align:top; }
  th { background:#0e131a; position:sticky; top:0; color:#c9d4df; }
  th .k { display:block; font-size:10px; font-weight:600; color:var(--amber); margin-top:2px; }
  tr:nth-child(even) td { background:#0f141b; }
  td.null { color:#5c6773; font-style:italic; }
  td.multi { color:#ff9b9b; white-space:normal; min-width:240px; }
  td.big { font-size:22px; font-weight:800; color:var(--ok); }
</style>
</head>
<body>
<header>
  <div class="logo">A&amp;A</div>
  <h1>Smart Stock &mdash; MariaDB &amp; 3NF Normalization Explorer <span>&middot; A&amp;A Surplus</span></h1>
  <div class="live">&#9679; MariaDB 10.4 &middot; database: <?= h($DEMO_DB) ?> &middot; built from live aa_inventory sales</div>
</header>

<section class="cards">
  <div class="card"><h3>1NF &mdash; Atomic values, no repeating groups</h3>
    <p>A receipt with three items becomes three rows. Key: (invoice_no, sku).</p></div>
  <div class="card"><h3>2NF &mdash; No partial dependencies</h3>
    <p>Receipt facts depend on invoice_no only; product facts on sku only &rarr; split into sales, products, sale_items.</p></div>
  <div class="card"><h3>3NF &mdash; No transitive dependencies</h3>
    <p>Cashier name depends on username; category code on category &rarr; users and categories tables, linked by foreign keys.</p></div>
</section>

<div class="wrap">
  <nav>
<?php $last = null; foreach ($VIEWS as $name => $v):
        if ($v[0] !== $last): $last = $v[0]; ?>
    <div class="grp"><?= h($v[0] === 'PROOF' ? 'Proofs' : $v[0] . ' - ' . explode(' - ', $v[1])[0]) ?></div>
<?php   endif; ?>
    <a href="?t=<?= h($name) ?>" class="<?= $name === $t ? 'on' : '' ?>" data-t="<?= h($name) ?>">
      <span><?= h($name) ?></span><span class="n"><?= isset($counts[$name]) ? $counts[$name] . ' rows' : '' ?></span></a>
<?php endforeach; ?>
  </nav>

  <main>
    <h2><span class="stage <?= h($stage) ?>"><?= h($stage) ?></span><?= h($stageName) ?></h2>
    <div class="tname">Table: <?= h($title) ?></div>
    <div class="rule"><?= $rule ?></div>
    <pre class="sql"><?= h($sqlShown) ?></pre>
    <div class="meta"><?= count($rows) ?> row(s)</div>
    <div class="grid">
      <table>
        <thead><tr>
<?php foreach ($cols as $c): ?>
          <th><?= h($c) ?><?php foreach ($keys[$t][$c] ?? [] as $tag): ?><span class="k">&#128273; <?= $tag ?></span><?php endforeach; ?></th>
<?php endforeach; ?>
        </tr></thead>
        <tbody>
<?php foreach ($rows as $r): ?>
          <tr>
<?php   foreach ($r as $c => $val):
          $cls = $val === null ? 'null' : '';
          if ($t === 'unf_sales_register' && str_contains((string) $val, ' | ')) { $cls = 'multi'; }
          if ($t === 'proof_lossless' && str_starts_with($c, 'rows_')) { $cls = 'big'; } ?>
            <td class="<?= $cls ?>"><?= $val === null ? 'NULL' : h($val) ?></td>
<?php   endforeach; ?>
          </tr>
<?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </main>
</div>
</body>
</html>
