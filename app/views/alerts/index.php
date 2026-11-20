<?php /** FR-06 - alert notification centre. */ ?>

<div class="row g-3 mb-3">
  <div class="col-md-4">
    <div class="kpi d-flex justify-content-between align-items-start">
      <div><div class="k-label">Out of Stock</div>
        <div class="k-value text-danger"><?= num(count($out)) ?></div>
        <div class="k-foot">Cannot be sold until replenished</div></div>
      <div class="k-icon i-red"><i class="bi bi-x-octagon"></i></div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="kpi d-flex justify-content-between align-items-start">
      <div><div class="k-label">Low Stock</div>
        <div class="k-value text-warning"><?= num(count($low)) ?></div>
        <div class="k-foot">At or below the reorder level</div></div>
      <div class="k-icon i-amber"><i class="bi bi-exclamation-triangle"></i></div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="kpi d-flex justify-content-between align-items-start">
      <div><div class="k-label">Fast Movers to Watch</div>
        <div class="k-value text-primary"><?= num(count($watch)) ?></div>
        <div class="k-foot">Selling faster than current cover</div></div>
      <div class="k-icon i-blue"><i class="bi bi-lightning-charge"></i></div>
    </div>
  </div>
</div>

<?php
/** Render one alert table. */
$renderTable = function (array $rows, string $tone, string $emptyMsg) {
  if (!$rows) {
    echo '<div class="p-4 text-center text-muted"><i class="bi bi-check2-circle fs-4 d-block mb-2 text-success"></i>'
       . e($emptyMsg) . '</div>';
    return;
  } ?>
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead><tr><th>SKU</th><th>Item</th><th>Category</th>
        <th class="text-center">On hand</th><th class="text-center">Reorder at</th>
        <th class="text-center">Sold (30d)</th><th class="text-end no-print">Action</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $a): ?>
        <tr>
          <td class="mono"><?= e($a['sku']) ?></td>
          <td>
            <a class="fw-semibold text-dark" href="<?= url('inventory.view', ['id' => $a['id']]) ?>">
              <?= e($a['name']) ?></a>
            <div class="text-muted-sm"><?= e($a['brand'] ?? '') ?></div>
          </td>
          <td class="text-muted-sm"><?= e($a['category_name'] ?? '—') ?></td>
          <td class="text-center"><span class="badge badge-soft-<?= $tone ?> fs-6"><?= num($a['quantity']) ?></span></td>
          <td class="text-center text-muted"><?= num($a['reorder_level']) ?></td>
          <td class="text-center fw-semibold"><?= num($a['sold_30'] ?? 0) ?></td>
          <td class="text-end no-print">
            <a class="btn btn-sm btn-outline-primary" href="<?= url('inventory.view', ['id' => $a['id']]) ?>">
              <i class="bi bi-box-arrow-in-down"></i> Restock</a>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php }; ?>

<div class="card mb-3">
  <div class="card-header bg-danger bg-opacity-10 text-danger">
    <i class="bi bi-x-octagon"></i> Out of Stock &mdash; immediate reorder required
  </div>
  <?php $renderTable($out, 'danger', 'No items are out of stock right now.'); ?>
</div>

<div class="card mb-3">
  <div class="card-header bg-warning bg-opacity-10 text-warning-emphasis">
    <i class="bi bi-exclamation-triangle"></i> Low Stock &mdash; at or below reorder level
  </div>
  <?php $renderTable($low, 'warning', 'No items have fallen to their reorder level.'); ?>
</div>

<div class="card">
  <div class="card-header">
    <i class="bi bi-lightning-charge text-primary"></i> Fast Movers &mdash; selling faster than the stock on hand
    <div class="text-muted-sm fw-normal mt-1">
      These items are still above the reorder level, but the last 30 days of sales exceed the
      current quantity on hand, so they are likely to run out before the next delivery.
    </div>
  </div>
  <?php $renderTable($watch, 'primary', 'No fast-moving items need attention.'); ?>
</div>
