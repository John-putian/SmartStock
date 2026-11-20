<?php /** FR-07 - inventory valuation and audit report. */ ?>

<ul class="nav nav-pills mb-3 no-print">
  <li class="nav-item"><a class="nav-link" href="<?= url('reports.sales') ?>">
    <i class="bi bi-cash-stack"></i> Sales &amp; Revenue</a></li>
  <li class="nav-item"><a class="nav-link active" href="<?= url('reports.inventory') ?>">
    <i class="bi bi-boxes"></i> Inventory Valuation</a></li>
  <li class="nav-item"><a class="nav-link" href="<?= url('reports.movement') ?>">
    <i class="bi bi-arrow-left-right"></i> Stock Movement</a></li>
  <li class="nav-item ms-auto"><a class="nav-link" href="<?= url('inventory.export') ?>">
    <i class="bi bi-file-earmark-excel"></i> Export Excel</a></li>
  <li class="nav-item"><a class="nav-link" href="javascript:window.print()">
    <i class="bi bi-printer"></i> Print</a></li>
</ul>

<div class="print-only mb-3">
  <h3>A&amp;A Surplus &mdash; Inventory Valuation Report</h3>
  <div class="text-muted">Smart Stock &middot; Inventory, Sales &amp; Decision Support System</div>
  <div>Generated <?= date('F j, Y g:i A') ?> by <?= e(Auth::user()['full_name']) ?></div><hr>
</div>

<div class="row g-3 mb-3">
  <div class="col-6 col-lg-3">
    <div class="kpi"><div class="k-label">Item Types</div>
      <div class="k-value"><?= num($counts['total'] ?? 0) ?></div>
      <div class="k-foot">Active in the catalogue</div></div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="kpi"><div class="k-label">Units On Hand</div>
      <div class="k-value"><?= num($counts['units'] ?? 0) ?></div>
      <div class="k-foot">Total physical stock</div></div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="kpi"><div class="k-label">Stock Value (cost)</div>
      <div class="k-value"><?= peso($counts['stock_value'] ?? 0) ?></div>
      <div class="k-foot">Capital tied up in inventory</div></div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="kpi"><div class="k-label">Potential Retail Value</div>
      <div class="k-value text-success"><?= peso($counts['retail_value'] ?? 0) ?></div>
      <div class="k-foot">Gross margin
        <?= peso((float) ($counts['retail_value'] ?? 0) - (float) ($counts['stock_value'] ?? 0)) ?></div></div>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-lg-5">
    <div class="card h-100">
      <div class="card-header"><i class="bi bi-diagram-3"></i> Valuation by Category</div>
      <div class="table-responsive">
        <table class="table table-sm mb-0">
          <thead><tr><th>Category</th><th class="text-center">Items</th>
            <th class="text-center">Units</th><th class="text-end">Cost value</th>
            <th class="text-end">Retail value</th></tr></thead>
          <tbody>
          <?php foreach ($byCat as $c): ?>
            <tr><td class="fw-semibold"><?= e($c['category']) ?></td>
              <td class="text-center"><?= num($c['items']) ?></td>
              <td class="text-center"><?= num($c['units']) ?></td>
              <td class="text-end"><?= peso($c['cost_value']) ?></td>
              <td class="text-end fw-semibold"><?= peso($c['retail_value']) ?></td></tr>
          <?php endforeach; ?>
          </tbody>
          <tfoot class="table-light fw-bold">
            <tr><td>TOTAL</td>
              <td class="text-center"><?= num(array_sum(array_column($byCat, 'items'))) ?></td>
              <td class="text-center"><?= num(array_sum(array_column($byCat, 'units'))) ?></td>
              <td class="text-end"><?= peso(array_sum(array_column($byCat, 'cost_value'))) ?></td>
              <td class="text-end"><?= peso(array_sum(array_column($byCat, 'retail_value'))) ?></td></tr>
          </tfoot>
        </table>
      </div>
    </div>
  </div>
  <div class="col-lg-7">
    <div class="card h-100">
      <div class="card-header"><i class="bi bi-bar-chart"></i> Stock Value Distribution</div>
      <div class="card-body"><div class="chart-box"><canvas id="valChart"></canvas></div></div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header"><i class="bi bi-list-check"></i> Full Inventory Listing
    <span class="text-muted-sm">&middot; <?= num(count($items)) ?> item(s)</span></div>
  <div class="table-responsive">
    <table class="table table-hover table-sm mb-0">
      <thead><tr><th>SKU</th><th>Item</th><th>Category</th><th>Condition</th>
        <th class="text-center">Qty</th><th class="text-end">Cost</th><th class="text-end">Price</th>
        <th class="text-end">Stock value</th><th class="text-center">Sold</th><th>Status</th></tr></thead>
      <tbody>
      <?php foreach ($items as $i):
            [$label, $tone] = stock_status((int) $i['quantity'], (int) $i['reorder_level']); ?>
        <tr>
          <td class="mono"><?= e($i['sku']) ?></td>
          <td><?= e($i['name']) ?></td>
          <td class="text-muted-sm"><?= e($i['category_name'] ?? '—') ?></td>
          <td><span class="badge badge-soft-<?= condition_class($i['condition_grade']) ?>"><?= e($i['condition_grade']) ?></span></td>
          <td class="text-center fw-semibold"><?= num($i['quantity']) ?></td>
          <td class="text-end text-muted"><?= peso($i['cost_price']) ?></td>
          <td class="text-end"><?= peso($i['selling_price']) ?></td>
          <td class="text-end fw-semibold"><?= peso((float) $i['quantity'] * (float) $i['cost_price']) ?></td>
          <td class="text-center text-muted"><?= num($i['sold_units']) ?></td>
          <td><span class="badge badge-soft-<?= $tone ?>"><?= e($label) ?></span></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php
$cLabels = js_json(array_column($byCat, 'category'));
$cCost   = js_json(array_map(fn($r) => round((float) $r['cost_value'], 2), $byCat));
$cRetail = js_json(array_map(fn($r) => round((float) $r['retail_value'], 2), $byCat));
$inlineScript = <<<JS
new Chart(document.getElementById('valChart'), {
  type: 'bar',
  data: { labels: {$cLabels}, datasets: [
    { label: 'Cost value',   data: {$cCost},   backgroundColor: '#c41114', borderRadius: 3 },
    { label: 'Retail value', data: {$cRetail}, backgroundColor: '#1f1d1c', borderRadius: 3 } ] },
  options: { responsive: true, maintainAspectRatio: false, indexAxis: 'y',
    plugins: { tooltip: { callbacks: { label: function (c) {
      return c.dataset.label + ': ' + AA.peso(c.parsed.x); } } } },
    scales: { x: { beginAtZero: true, ticks: { callback: function (v) { return AA.peso(v); } } } } }
});
JS;
?>
