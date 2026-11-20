<?php /** Dashboard - high-level KPI summary and shortcuts. */ ?>

<div class="row g-3 mb-3">
  <div class="col-6 col-xl-3">
    <div class="kpi d-flex justify-content-between align-items-start">
      <div>
        <div class="k-label">Sales Today</div>
        <div class="k-value"><?= peso($todayStats['revenue'] ?? 0) ?></div>
        <div class="k-foot"><?= num($todayStats['txn'] ?? 0) ?> transaction(s) recorded</div>
      </div>
      <div class="k-icon i-green"><i class="bi bi-cash-stack"></i></div>
    </div>
  </div>

  <div class="col-6 col-xl-3">
    <div class="kpi d-flex justify-content-between align-items-start">
      <div>
        <div class="k-label">Revenue This Month</div>
        <div class="k-value"><?= peso($monthStats['revenue'] ?? 0) ?></div>
        <div class="k-foot">
          <?php if ($growth === null): ?>
            No comparison data
          <?php else: ?>
            <span class="text-<?= $growth >= 0 ? 'success' : 'danger' ?>">
              <i class="bi bi-arrow-<?= $growth >= 0 ? 'up' : 'down' ?>-right"></i>
              <?= number_format(abs($growth), 1) ?>%</span> vs. last month
          <?php endif; ?>
        </div>
      </div>
      <div class="k-icon i-blue"><i class="bi bi-graph-up"></i></div>
    </div>
  </div>

  <div class="col-6 col-xl-3">
    <div class="kpi d-flex justify-content-between align-items-start">
      <div>
        <div class="k-label">Inventory On Hand</div>
        <div class="k-value"><?= num($counts['units'] ?? 0) ?></div>
        <div class="k-foot"><?= num($counts['total'] ?? 0) ?> item types &middot;
          <?= peso($counts['stock_value'] ?? 0) ?> at cost</div>
      </div>
      <div class="k-icon i-purple"><i class="bi bi-box-seam"></i></div>
    </div>
  </div>

  <div class="col-6 col-xl-3">
    <div class="kpi d-flex justify-content-between align-items-start">
      <div>
        <div class="k-label">Stock Alerts</div>
        <div class="k-value text-<?= $alertTotal ? 'danger' : 'success' ?>"><?= num($alertTotal) ?></div>
        <div class="k-foot"><?= num($counts['out_stock'] ?? 0) ?> out of stock &middot;
          <?= num($counts['low_stock'] ?? 0) ?> low</div>
      </div>
      <div class="k-icon i-<?= $alertTotal ? 'red' : 'green' ?>"><i class="bi bi-bell"></i></div>
    </div>
  </div>
</div>

<div class="row g-3">
  <div class="col-xl-8">
    <div class="card mb-3">
      <div class="card-header d-flex align-items-center">
        <i class="bi bi-activity me-2"></i> Daily Sales &ndash; Last 30 Days
        <a class="ms-auto small" href="<?= url('reports.sales') ?>">View full report <i class="bi bi-chevron-right"></i></a>
      </div>
      <div class="card-body"><div class="chart-box"><canvas id="trendChart"></canvas></div></div>
    </div>

    <div class="card">
      <div class="card-header d-flex align-items-center">
        <i class="bi bi-receipt me-2"></i> Recent Transactions
        <a class="ms-auto small" href="<?= url('reports.sales') ?>">All sales <i class="bi bi-chevron-right"></i></a>
      </div>
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead><tr><th>Invoice</th><th>Date</th><th>Cashier</th>
            <th class="text-center">Items</th><th class="text-end">Total</th><th></th></tr></thead>
          <tbody>
          <?php if (!$recent): ?>
            <tr><td colspan="6" class="text-center text-muted py-4">No recent transactions.</td></tr>
          <?php endif; ?>
          <?php foreach ($recent as $s): ?>
            <tr>
              <td class="mono"><?= e($s['invoice_no']) ?></td>
              <td class="text-muted-sm"><?= date('M j, g:i A', strtotime($s['sale_date'])) ?></td>
              <td class="text-muted-sm"><?= e($s['cashier'] ?? '—') ?></td>
              <td class="text-center"><?= num($s['units']) ?></td>
              <td class="text-end fw-semibold"><?= peso($s['total']) ?></td>
              <td class="text-end"><a class="btn btn-sm btn-outline-secondary py-0"
                 href="<?= url('pos.receipt', ['id' => $s['id']]) ?>"><i class="bi bi-receipt"></i></a></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="col-xl-4">
    <div class="card mb-3">
      <div class="card-header d-flex align-items-center">
        <i class="bi bi-exclamation-triangle text-warning me-2"></i> Low-Stock Alerts
        <a class="ms-auto small" href="<?= url('alerts') ?>">Alert centre <i class="bi bi-chevron-right"></i></a>
      </div>
      <div class="list-group list-group-flush">
        <?php if (!$alerts): ?>
          <div class="p-4 text-center text-muted">
            <i class="bi bi-check2-circle fs-3 d-block mb-2 text-success"></i>
            All items are above their reorder level.</div>
        <?php endif; ?>
        <?php foreach ($alerts as $a):
              $out = (int) $a['quantity'] < 1; ?>
          <a class="list-group-item list-group-item-action d-flex align-items-center gap-2"
             href="<?= url('inventory.view', ['id' => $a['id']]) ?>">
            <div class="flex-grow-1">
              <div class="fw-semibold" style="font-size:.85rem"><?= e($a['name']) ?></div>
              <div class="text-muted-sm"><?= e($a['category_name'] ?? '') ?> &middot;
                sold <?= num($a['sold_30']) ?> in last 30 days</div>
            </div>
            <span class="badge badge-soft-<?= $out ? 'danger' : 'warning' ?>">
              <?= $out ? 'Out of stock' : num($a['quantity']) . ' left' ?></span>
          </a>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="card mb-3">
      <div class="card-header"><i class="bi bi-pie-chart me-2"></i> Revenue by Category (this month)</div>
      <div class="card-body"><div class="chart-box sm"><canvas id="catChart"></canvas></div></div>
    </div>

    <div class="card">
      <div class="card-header"><i class="bi bi-trophy me-2"></i> Best Sellers This Month</div>
      <div class="list-group list-group-flush">
        <?php if (!$topProducts): ?>
          <div class="p-4 text-center text-muted">No sales recorded this month yet.</div>
        <?php endif; ?>
        <?php foreach ($topProducts as $i => $t): ?>
          <div class="list-group-item d-flex align-items-center gap-2">
            <span class="badge bg-light text-dark"><?= $i + 1 ?></span>
            <div class="flex-grow-1">
              <div class="fw-semibold" style="font-size:.84rem"><?= e($t['product_name']) ?></div>
              <div class="text-muted-sm"><?= num($t['units']) ?> unit(s) sold</div>
            </div>
            <span class="fw-semibold" style="font-size:.85rem"><?= peso($t['revenue']) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<?php
$tLabels = js_json(array_map(fn($r) => date('M j', strtotime($r['bucket'])), $trend));
$tValues = js_json(array_map(fn($r) => round((float) $r['revenue'], 2), $trend));
$cLabels = js_json(array_column($categories, 'category'));
$cValues = js_json(array_map(fn($r) => round((float) $r['revenue'], 2), $categories));

$inlineScript = <<<JS
new Chart(document.getElementById('trendChart'), {
  type: 'line',
  data: { labels: {$tLabels}, datasets: [{
      label: 'Daily revenue', data: {$tValues},
      borderColor: '#c41114', backgroundColor: 'rgba(196,17,20,.10)',
      fill: true, tension: .35, pointRadius: 2, borderWidth: 2 }] },
  options: { responsive: true, maintainAspectRatio: false,
    plugins: { legend: { display: false },
      tooltip: { callbacks: { label: function (c) { return AA.peso(c.parsed.y); } } } },
    scales: { y: { beginAtZero: true, ticks: { callback: function (v) { return AA.peso(v); } } } } }
});

new Chart(document.getElementById('catChart'), {
  type: 'doughnut',
  data: { labels: {$cLabels},
    datasets: [{ data: {$cValues}, backgroundColor: AA.palette, borderWidth: 2, borderColor: '#fff' }] },
  options: { responsive: true, maintainAspectRatio: false, cutout: '58%',
    plugins: { legend: { position: 'right', labels: { boxWidth: 8, font: { size: 11 } } },
      tooltip: { callbacks: { label: function (c) { return c.label + ': ' + AA.peso(c.parsed); } } } } }
});
JS;
?>
