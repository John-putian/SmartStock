<?php
/**
 * FR-10 - Sales by Category.
 *
 * The report the manager brings to the monthly meeting with the shipment
 * owner. Containers cannot be ordered item by item, so everything here is
 * expressed in the only unit the owner can actually act on: the category.
 */
$targetPage = 'reports.category';
$showGroup  = false;
$exportWhat = 'category';
require APP_PATH . '/views/reports/_filter.php';

// ---- pivot the day/category rows into a matrix -----------------------
$matrix   = [];   // [day][category] => ['units'=>, 'revenue'=>]
$dayTotal = [];   // [day] => revenue
foreach ($daily as $d) {
    $matrix[$d['day']][$d['category']] = [
        'units'   => (int) $d['units'],
        'revenue' => (float) $d['revenue'],
    ];
    $dayTotal[$d['day']] = ($dayTotal[$d['day']] ?? 0) + (float) $d['revenue'];
}
krsort($matrix);   // newest day first

// Only categories that actually sold get a column, best sellers first.
$columns = [];
foreach ($rows as $r) {
    if ((float) $r['revenue'] > 0) {
        $columns[] = $r['category'];
    }
}

$restock = array_values(array_filter($rows, fn($r) => $r['advice_tone'] === 'more'));
$drop    = array_values(array_filter($rows, fn($r) => $r['advice_tone'] === 'less'));
$best    = $rows ? array_reduce($rows, fn($c, $r) =>
              ($c === null || (float) $r['revenue'] > (float) $c['revenue']) ? $r : $c) : null;

$tone = [
    'more'   => ['badge-soft-danger',    'arrow-up-circle',   'Ask for more'],
    'keep'   => ['badge-soft-success',   'check-circle',      'Keep receiving'],
    'steady' => ['badge-soft-secondary', 'dash-circle',       'Steady'],
    'less'   => ['badge-soft-warning',   'arrow-down-circle', 'Ask for less'],
];
?>

<ul class="nav nav-pills mb-3 no-print">
  <li class="nav-item"><a class="nav-link" href="<?= url('reports.sales', ['preset' => $p['preset']]) ?>">
    <i class="bi bi-cash-stack"></i> Sales &amp; Revenue</a></li>
  <li class="nav-item"><a class="nav-link active" href="<?= url('reports.category', ['preset' => $p['preset']]) ?>">
    <i class="bi bi-tags-fill"></i> Sales by Category</a></li>
  <li class="nav-item"><a class="nav-link" href="<?= url('reports.inventory') ?>">
    <i class="bi bi-boxes"></i> Inventory Valuation</a></li>
  <li class="nav-item"><a class="nav-link" href="<?= url('reports.movement', ['preset' => $p['preset']]) ?>">
    <i class="bi bi-arrow-left-right"></i> Stock Movement</a></li>
</ul>

<div class="card mb-3">
  <div class="card-body d-flex gap-3 py-3">
    <i class="bi bi-people text-danger fs-4"></i>
    <div class="text-muted-sm">
      <strong class="text-dark">Why this report exists.</strong>
      A container is loaded with whatever the supplier has that month, so the shop can never
      ask for a named item &mdash; only for more or less of a <em>category</em>. This page shows what
      each category actually earned, day by day, and turns that into the request list to bring
      to the monthly meeting with the shipment owner.
    </div>
  </div>
</div>

<!-- ---------------------------------------------------------------- KPIs -->
<div class="row g-3 mb-3">
  <div class="col-6 col-lg-3">
    <div class="kpi"><div class="k-label">Revenue in Period</div>
      <div class="k-value"><?= peso($revenue) ?></div>
      <div class="k-foot"><?= peso($revenue / max(1, $days)) ?> per day across <?= num($days) ?> day(s)</div></div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="kpi"><div class="k-label">Pieces Sold</div>
      <div class="k-value"><?= num($units) ?></div>
      <div class="k-foot"><?= num(count($columns)) ?> of <?= num(count($rows)) ?> categories sold something</div></div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="kpi"><div class="k-label">Best Category</div>
      <div class="k-value" style="font-size:1.35rem"><?= $best ? e($best['category']) : '—' ?></div>
      <div class="k-foot"><?= $best ? peso($best['revenue']) . ' &middot; ' . number_format($best['share'], 1) . '% of takings' : 'No sales yet' ?></div></div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="kpi"><div class="k-label">Categories to Restock</div>
      <div class="k-value <?= $restock ? 'text-danger' : 'text-success' ?>"><?= num(count($restock)) ?></div>
      <div class="k-foot"><?= $restock ? 'Sold out or running low' : 'Nothing urgent this period' ?></div></div>
  </div>
</div>

<!-- ------------------------------------------------- the request list -->
<div class="card mb-3">
  <div class="card-header d-flex align-items-center">
    <i class="bi bi-clipboard-check me-2"></i> What to Ask For in the Next Container
    <span class="ms-auto text-muted-sm">Most urgent first &middot; bring this list to the meeting</span>
  </div>
  <div class="card-body py-2 border-bottom text-muted-sm">
    <strong>Ask for</strong> is how many pieces of that category would cover the next 30 days at the
    rate it is currently selling, less whatever is still on the floor. It is a starting figure for
    the conversation, not a promise &mdash; the container brings what it brings.
  </div>
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead><tr>
        <th style="width:190px">Category</th>
        <th style="width:150px">Request</th>
        <th class="text-center" style="width:120px">Ask for</th>
        <th>Why</th>
        <th class="text-center" style="width:80px">Sold</th>
        <th class="text-center" style="width:80px">Left</th>
        <th class="text-end" style="width:130px">Revenue</th>
      </tr></thead>
      <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="7" class="text-center text-muted py-4">No categories have been set up yet.</td></tr>
      <?php endif; ?>
      <?php foreach ($rows as $r): ?>
        <?php [$cls, $icon, $label] = $tone[$r['advice_tone']]; ?>
        <tr>
          <td class="fw-semibold"><?= e($r['category']) ?>
            <?php if (!empty($r['code'])): ?>
              <div class="mono text-muted-sm">AA-<?= e($r['code']) ?>-</div>
            <?php endif; ?>
          </td>
          <td><span class="badge <?= $cls ?>"><i class="bi bi-<?= $icon ?>"></i> <?= e($r['advice']) ?></span></td>
          <td class="text-center">
            <?php if ((int) $r['suggest'] > 0): ?>
              <span class="fw-semibold fs-5"><?= num($r['suggest']) ?></span>
              <div class="text-muted-sm">piece(s)</div>
            <?php else: ?><span class="text-muted">&mdash;</span><?php endif; ?>
          </td>
          <td class="text-muted-sm"><?= e($r['advice_why']) ?></td>
          <td class="text-center"><?= num($r['units']) ?></td>
          <td class="text-center <?= (int) $r['pieces_left'] === 0 ? 'text-danger fw-semibold' : '' ?>">
            <?= num($r['pieces_left']) ?></td>
          <td class="text-end fw-semibold"><?= peso($r['revenue']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- --------------------------------------------------- daily breakdown -->
<div class="card mb-3">
  <div class="card-header"><i class="bi bi-bar-chart-steps"></i>
    Daily Takings by Category</div>
  <div class="card-body"><div class="chart-box"><canvas id="catDayChart"></canvas></div></div>
</div>

<div class="card mb-3">
  <div class="card-header d-flex flex-wrap align-items-center gap-2">
    <span><i class="bi bi-calendar3"></i> Day-by-Day Detail</span>
    <span class="text-muted-sm">&middot; peso takings, with pieces sold underneath</span>
    <a class="ms-auto small no-print"
       href="<?= url('reports.export', ['preset' => $p['preset'], 'from' => $p['from'],
                                        'to' => $p['to'], 'what' => 'category']) ?>">
      <i class="bi bi-file-earmark-excel"></i> Export this table</a>
  </div>
  <div class="table-responsive" style="max-height:520px;overflow:auto">
    <table class="table table-hover table-sm mb-0 align-middle">
      <thead class="position-sticky top-0">
        <tr>
          <th style="min-width:130px">Date</th>
          <?php foreach ($columns as $c): ?>
            <th class="text-end" style="min-width:110px"><?= e($c) ?></th>
          <?php endforeach; ?>
          <th class="text-end" style="min-width:120px">Day total</th>
        </tr>
      </thead>
      <tbody>
      <?php if (!$matrix): ?>
        <tr><td colspan="<?= count($columns) + 2 ?>" class="text-center text-muted py-5">
          No sales were recorded in this period.</td></tr>
      <?php endif; ?>
      <?php foreach ($matrix as $day => $cells): ?>
        <tr>
          <td class="text-nowrap">
            <span class="fw-semibold"><?= date('M j', strtotime($day)) ?></span>
            <span class="text-muted-sm"><?= date('D', strtotime($day)) ?></span>
          </td>
          <?php foreach ($columns as $c): ?>
            <?php $cell = $cells[$c] ?? null; ?>
            <td class="text-end <?= $cell ? '' : 'text-muted' ?>">
              <?php if ($cell): ?>
                <div class="fw-semibold"><?= peso($cell['revenue']) ?></div>
                <div class="text-muted-sm"><?= num($cell['units']) ?> pc</div>
              <?php else: ?>—<?php endif; ?>
            </td>
          <?php endforeach; ?>
          <td class="text-end fw-semibold"><?= peso($dayTotal[$day] ?? 0) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
      <?php if ($matrix): ?>
      <tfoot class="table-group-divider">
        <tr class="fw-semibold">
          <td>Period total</td>
          <?php foreach ($columns as $c): ?>
            <?php
              $colRevenue = 0.0; $colUnits = 0;
              foreach ($rows as $r) {
                  if ($r['category'] === $c) { $colRevenue = (float) $r['revenue']; $colUnits = (int) $r['units']; }
              }
            ?>
            <td class="text-end">
              <div><?= peso($colRevenue) ?></div>
              <div class="text-muted-sm fw-normal"><?= num($colUnits) ?> pc</div>
            </td>
          <?php endforeach; ?>
          <td class="text-end"><?= peso($revenue) ?></td>
        </tr>
      </tfoot>
      <?php endif; ?>
    </table>
  </div>
</div>

<!-- ------------------------------------------------ category scorecard -->
<div class="card">
  <div class="card-header"><i class="bi bi-list-columns-reverse"></i> Category Scorecard</div>
  <div class="table-responsive">
    <table class="table table-hover mb-0 align-middle">
      <thead><tr>
        <th>Category</th>
        <th class="text-center">Pieces sold</th>
        <th class="text-end">Revenue</th>
        <th class="text-end">Share</th>
        <th class="text-end">Average price</th>
        <th class="text-center">How much sold</th>
        <th class="text-center">Pieces left</th>
        <th class="text-center">Stock lasts</th>
      </tr></thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td class="fw-semibold"><?= e($r['category']) ?></td>
          <td class="text-center"><?= num($r['units']) ?></td>
          <td class="text-end fw-semibold"><?= peso($r['revenue']) ?></td>
          <td class="text-end"><?= number_format($r['share'], 1) ?>%</td>
          <td class="text-end text-muted-sm"><?= (int) $r['units'] > 0 ? peso($r['avg_price']) : '—' ?></td>
          <td class="text-center" style="min-width:150px">
            <?php if ((int) $r['units'] + (int) $r['pieces_left'] > 0): ?>
              <div class="progress" style="height:6px">
                <div class="progress-bar bg-danger" style="width:<?= min(100, $r['sell_rate']) ?>%"></div>
              </div>
              <span class="text-muted-sm"><?= round($r['sell_rate']) ?>% of what we had</span>
            <?php else: ?><span class="text-muted">—</span><?php endif; ?>
          </td>
          <td class="text-center <?= (int) $r['pieces_left'] === 0 ? 'text-danger fw-semibold' : '' ?>">
            <?= num($r['pieces_left']) ?></td>
          <td class="text-center text-muted-sm">
            <?php if ($r['days_cover'] === null): ?>
              <?= (int) $r['pieces_left'] > 0 ? 'Not selling' : '—' ?>
            <?php elseif ($r['days_cover'] < 1): ?>
              <span class="text-danger fw-semibold">Sold out</span>
            <?php else: ?>
              about <?= num(round($r['days_cover'])) ?> day(s)
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="card-body py-2 border-top text-muted-sm">
    <strong>How much sold</strong> compares the pieces sold in this period against everything that
    was available &mdash; sold plus still on the floor. <strong>Stock lasts</strong> divides what is left
    by the daily selling rate, so it answers &ldquo;if nothing new arrives, how long before this
    category is empty?&rdquo;
  </div>
</div>

<?php
// ---- stacked daily chart ---------------------------------------------
$chartDays = array_keys($matrix);
sort($chartDays);
$labels = js_json(array_map(fn($d) => date('M j', strtotime($d)), $chartDays));

$datasets = [];
foreach ($columns as $i => $c) {
    $series = [];
    foreach ($chartDays as $d) {
        $series[] = round((float) ($matrix[$d][$c]['revenue'] ?? 0), 2);
    }
    $datasets[] = ['label' => $c, 'data' => $series];
}
$datasetsJson = js_json($datasets);

$inlineScript = <<<JS
(function () {
  var sets = {$datasetsJson};
  if (!sets.length) { return; }

  new Chart(document.getElementById('catDayChart'), {
    type: 'bar',
    data: {
      labels: {$labels},
      datasets: sets.map(function (s, i) {
        return {
          label: s.label,
          data: s.data,
          backgroundColor: AA.palette[i % AA.palette.length],
          borderRadius: 2,
          borderWidth: 0
        };
      })
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      interaction: { mode: 'index', intersect: false },
      plugins: {
        legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 11 } } },
        tooltip: {
          callbacks: {
            label: function (c) { return c.dataset.label + ': ' + AA.peso(c.parsed.y); },
            footer: function (items) {
              var t = items.reduce(function (sum, i) { return sum + i.parsed.y; }, 0);
              return 'Day total: ' + AA.peso(t);
            }
          }
        }
      },
      scales: {
        x: { stacked: true, ticks: { maxRotation: 45, autoSkip: true, maxTicksLimit: 20 } },
        y: { stacked: true, beginAtZero: true,
             ticks: { callback: function (v) { return AA.peso(v); } },
             title: { display: true, text: 'Takings' } }
      }
    }
  });
})();
JS;
?>
