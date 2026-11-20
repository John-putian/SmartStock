<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Business Report &middot; <?= e($p['from']) ?> to <?= e($p['to']) ?></title>
<link rel="stylesheet" href="<?= base_url('assets/vendor/bootstrap/bootstrap.min.css') ?>">
<link rel="stylesheet" href="<?= asset_url('assets/css/style.css') ?>">
<style>
  body { background:#fff; padding:28px 34px; font-size:12.5px }
  h1 { font-size:19px; margin:0 } h2 { font-size:14px; margin:22px 0 8px; border-bottom:2px solid #c41114; padding-bottom:4px }
  .hdr { border-bottom:3px solid #c41114; padding-bottom:10px; margin-bottom:14px }
  table { width:100% } .table th { background:#faf8f5 !important; -webkit-print-color-adjust:exact }
  .sumbox { border:1px solid #e4ded5; border-left:3px solid #c41114; border-radius:6px; padding:10px 12px }
  .sumbox .l { font-size:10px; text-transform:uppercase; letter-spacing:.06em; color:#7a726a; font-weight:700 }
  .sumbox .v { font-size:17px; font-weight:700 }
  /* ------------------------------------------------------------------ print
     This is the sheet that gets handed to the proprietor, so it has to survive
     being several pages long: every table repeats its column headings, no row
     is cut in half, no section heading is left stranded at the foot of a page
     without its table, and the signature block stays in one piece.

     Chrome cannot put "Page 1 of 4" there from CSS - that is the browser's own
     print header, switched on with "Headers and footers" in the print dialog.
     What it CAN do is repeat a fixed footer on every sheet, which is what the
     band at the bottom is.                                                  */
  @media print {
    @page { size: A4 portrait; margin: 14mm 14mm 20mm; }

    .no-print { display: none !important; }
    body { padding: 0; }

    thead { display: table-header-group; }
    tfoot { display: table-footer-group; }
    tr, .sumbox, .sign-block { break-inside: avoid; page-break-inside: avoid; }
    h1, h2 { break-after: avoid; page-break-after: avoid; }

    .print-foot { display: block !important; }
  }

  /* Only meaningful on paper; on screen the page already says all of this. */
  .print-foot {
    display: none;
    position: fixed; bottom: 0; left: 0; right: 0;
    text-align: center; font-size: 9px; color: #7a726a;
    border-top: 1px solid #e4ded5; padding-top: 3px;
  }
</style>
</head>
<body>

<div class="hdr d-flex justify-content-between align-items-start">
  <div class="d-flex gap-3 align-items-start">
    <img src="<?= base_url('assets/img/logo-sm.png') ?>" alt="A&amp;A Surplus" style="width:74px;height:74px">
    <div>
    <h1>A&amp;A SURPLUS &middot; HOUSEHOLD APPLIANCES TRADING</h1>
    <div class="text-muted"><?= e(setting('store_address', 'Panabo City, Davao del Norte')) ?>
      &middot; <?= e(setting('store_contact', '')) ?></div>
    <div class="mt-2 fw-bold">CONSOLIDATED BUSINESS REPORT</div>
    <div>Reporting period: <?= date('F j, Y', strtotime($p['from'])) ?>
      to <?= date('F j, Y', strtotime($p['to'])) ?></div>
    </div>
  </div>
  <div class="text-end small">
    <div>Generated: <?= date('F j, Y g:i A') ?></div>
    <div>Prepared by: <?= e(Auth::user()['full_name']) ?></div>
    <div>Role: <?= e(role_label(Auth::role())) ?></div>
  </div>
</div>

<h2>1. Sales Summary</h2>
<div class="row g-2 mb-2">
  <div class="col-3"><div class="sumbox"><div class="l">Total Revenue</div>
    <div class="v"><?= peso($summary['revenue'] ?? 0) ?></div></div></div>
  <div class="col-3"><div class="sumbox"><div class="l">Transactions</div>
    <div class="v"><?= num($summary['txn'] ?? 0) ?></div></div></div>
  <div class="col-3"><div class="sumbox"><div class="l">Average Sale</div>
    <div class="v"><?= peso($summary['avg_sale'] ?? 0) ?></div></div></div>
  <div class="col-3"><div class="sumbox"><div class="l">Discounts</div>
    <div class="v"><?= peso($summary['discounts'] ?? 0) ?></div></div></div>
</div>

<h2>2. Revenue Breakdown by Period</h2>
<table class="table table-sm table-bordered">
  <thead><tr><th>Period</th><th class="text-center">Transactions</th>
    <th class="text-end">Discounts</th><th class="text-end">Revenue</th></tr></thead>
  <tbody>
  <?php foreach ($trend as $t): ?>
    <tr><td><?= e($t['bucket']) ?></td>
      <td class="text-center"><?= num($t['txn']) ?></td>
      <td class="text-end"><?= peso($t['discounts']) ?></td>
      <td class="text-end fw-semibold"><?= peso($t['revenue']) ?></td></tr>
  <?php endforeach; ?>
  </tbody>
  <tfoot><tr class="fw-bold"><td>TOTAL</td>
    <td class="text-center"><?= num(array_sum(array_column($trend, 'txn'))) ?></td>
    <td class="text-end"><?= peso(array_sum(array_column($trend, 'discounts'))) ?></td>
    <td class="text-end"><?= peso(array_sum(array_column($trend, 'revenue'))) ?></td></tr></tfoot>
</table>

<h2>3. Sales by Category</h2>
<table class="table table-sm table-bordered">
  <thead><tr><th>Category</th><th class="text-center">Units Sold</th><th class="text-end">Revenue</th>
    <th class="text-end">Share</th></tr></thead>
  <tbody>
  <?php $totalRev = max(0.01, (float) array_sum(array_column($byCat, 'revenue')));
        foreach ($byCat as $c): ?>
    <tr><td><?= e($c['category']) ?></td>
      <td class="text-center"><?= num($c['units']) ?></td>
      <td class="text-end"><?= peso($c['revenue']) ?></td>
      <td class="text-end"><?= number_format((float) $c['revenue'] / $totalRev * 100, 1) ?>%</td></tr>
  <?php endforeach; ?>
  </tbody>
</table>

<h2>4. Top Selling Items</h2>
<table class="table table-sm table-bordered">
  <thead><tr><th>#</th><th>SKU</th><th>Item</th><th class="text-center">Units</th><th class="text-end">Revenue</th></tr></thead>
  <tbody>
  <?php foreach ($top as $i => $t): ?>
    <tr><td><?= $i + 1 ?></td><td><?= e($t['sku']) ?></td><td><?= e($t['product_name']) ?></td>
      <td class="text-center"><?= num($t['units']) ?></td>
      <td class="text-end"><?= peso($t['revenue']) ?></td></tr>
  <?php endforeach; ?>
  </tbody>
</table>

<h2>5. Cashier Performance and Payment Methods</h2>
<div class="row g-3">
  <div class="col-6">
    <table class="table table-sm table-bordered">
      <thead><tr><th>Cashier</th><th class="text-center">Txn</th><th class="text-end">Revenue</th></tr></thead>
      <tbody><?php foreach ($byCash as $c): ?>
        <tr><td><?= e($c['cashier']) ?></td><td class="text-center"><?= num($c['txn']) ?></td>
          <td class="text-end"><?= peso($c['revenue']) ?></td></tr>
      <?php endforeach; ?></tbody>
    </table>
  </div>
  <div class="col-6">
    <table class="table table-sm table-bordered">
      <thead><tr><th>Payment Method</th><th class="text-center">Txn</th><th class="text-end">Revenue</th></tr></thead>
      <tbody><?php foreach ($byPay as $m): ?>
        <tr><td><?= e($m['payment_method']) ?></td><td class="text-center"><?= num($m['txn']) ?></td>
          <td class="text-end"><?= peso($m['revenue']) ?></td></tr>
      <?php endforeach; ?></tbody>
    </table>
  </div>
</div>

<h2>6. Inventory Position as of <?= date('F j, Y') ?></h2>
<div class="row g-2 mb-2">
  <div class="col-3"><div class="sumbox"><div class="l">Item Types</div>
    <div class="v"><?= num($counts['total'] ?? 0) ?></div></div></div>
  <div class="col-3"><div class="sumbox"><div class="l">Units On Hand</div>
    <div class="v"><?= num($counts['units'] ?? 0) ?></div></div></div>
  <div class="col-3"><div class="sumbox"><div class="l">Stock Value (cost)</div>
    <div class="v"><?= peso($counts['stock_value'] ?? 0) ?></div></div></div>
  <div class="col-3"><div class="sumbox"><div class="l">Retail Value</div>
    <div class="v"><?= peso($counts['retail_value'] ?? 0) ?></div></div></div>
</div>

<h2>7. Reorder / Low-Stock Advisory</h2>
<?php if (!$alerts): ?>
  <p>All active items are currently above their configured reorder levels.</p>
<?php else: ?>
<table class="table table-sm table-bordered">
  <thead><tr><th>SKU</th><th>Item</th><th>Category</th><th class="text-center">On hand</th>
    <th class="text-center">Reorder level</th><th class="text-center">Sold (30d)</th><th>Status</th></tr></thead>
  <tbody>
  <?php foreach ($alerts as $a): ?>
    <tr><td><?= e($a['sku']) ?></td><td><?= e($a['name']) ?></td>
      <td><?= e($a['category_name'] ?? '—') ?></td>
      <td class="text-center"><?= num($a['quantity']) ?></td>
      <td class="text-center"><?= num($a['reorder_level']) ?></td>
      <td class="text-center"><?= num($a['sold_30']) ?></td>
      <td><?= (int) $a['quantity'] <= 0 ? 'OUT OF STOCK' : 'LOW STOCK' ?></td></tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>

<div class="sign-block" style="margin-top:40px;display:flex;gap:60px">
  <div style="flex:1">
    <div style="border-top:1px solid #000;padding-top:4px">Prepared by &mdash; <?= e(Auth::user()['full_name']) ?></div>
  </div>
  <div style="flex:1">
    <div style="border-top:1px solid #000;padding-top:4px">Noted by &mdash; Management / Proprietor</div>
  </div>
</div>

<div class="text-center text-muted mt-4" style="font-size:10px">
  Generated by Smart Stock &mdash; the Web-Based Inventory, Sales and Decision Support System of
  A&amp;A Surplus &middot; IT 307 &ndash; Web Systems and Technologies 2
</div>

<div class="text-center mt-4 no-print">
  <button class="btn btn-primary" onclick="window.print()">Print / Save as PDF</button>
  <button class="btn btn-outline-secondary" onclick="window.close()">Close</button>
</div>
<div class="print-foot">
  A&amp;A SURPLUS &middot; Consolidated Business Report &middot;
  <?= date('F j, Y', strtotime($p['from'])) ?> to <?= date('F j, Y', strtotime($p['to'])) ?>
  &middot; generated <?= date('F j, Y g:i A') ?>
  &middot; prepared by <?= e(Auth::user()['full_name']) ?>
</div>

</body>
</html>
