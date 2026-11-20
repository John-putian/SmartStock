<?php /** FR-02 / FR-03 / FR-06 - inventory catalogue with search and stock status. */ ?>

<div class="row g-3 mb-3">
  <div class="col-6 col-lg-3">
    <div class="kpi d-flex justify-content-between align-items-start">
      <div><div class="k-label">Active Items</div>
        <div class="k-value"><?= num($counts['total'] ?? 0) ?></div>
        <div class="k-foot"><?= num($counts['units'] ?? 0) ?> units on hand</div></div>
      <div class="k-icon i-blue"><i class="bi bi-box-seam"></i></div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="kpi d-flex justify-content-between align-items-start">
      <div><div class="k-label">In Stock</div>
        <div class="k-value text-success"><?= num($counts['in_stock'] ?? 0) ?></div>
        <div class="k-foot">Above reorder level</div></div>
      <div class="k-icon i-green"><i class="bi bi-check2-circle"></i></div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="kpi d-flex justify-content-between align-items-start">
      <div><div class="k-label">Low Stock</div>
        <div class="k-value text-warning"><?= num($counts['low_stock'] ?? 0) ?></div>
        <div class="k-foot">At or below reorder level</div></div>
      <div class="k-icon i-amber"><i class="bi bi-exclamation-triangle"></i></div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="kpi d-flex justify-content-between align-items-start">
      <div><div class="k-label">Out of Stock</div>
        <div class="k-value text-danger"><?= num($counts['out_stock'] ?? 0) ?></div>
        <div class="k-foot">Needs replenishment</div></div>
      <div class="k-icon i-red"><i class="bi bi-x-octagon"></i></div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header d-flex flex-wrap gap-2 align-items-center">
    <span><i class="bi bi-list-ul"></i> Inventory Catalogue</span>
    <span class="text-muted-sm">&middot; <?= num(count($items)) ?> item(s) shown</span>
    <div class="ms-auto d-flex gap-2 no-print">
      <a class="btn btn-sm btn-outline-success"
         href="<?= url('inventory.export', $filters) ?>"><i class="bi bi-file-earmark-excel"></i> Export Excel</a>
      <?php if (Auth::canEditItems()): ?>
        <a class="btn btn-sm btn-primary" href="<?= url('inventory.create') ?>">
          <i class="bi bi-plus-lg"></i> Add Item</a>
      <?php endif; ?>
    </div>
  </div>

  <div class="card-body border-bottom no-print">
    <form method="get" class="row g-2">
      <input type="hidden" name="page" value="inventory">
      <div class="col-12 col-lg-4">
        <div class="input-group input-group-sm">
          <span class="input-group-text"><i class="bi bi-search"></i></span>
          <input name="q" value="<?= e($filters['q']) ?>" class="form-control"
                 placeholder="Search by item name, SKU, brand or serial number" data-live-search>
        </div>
      </div>
      <div class="col-6 col-lg-2">
        <select name="category" class="form-select form-select-sm" data-autosubmit>
          <option value="">All categories</option>
          <?php foreach ($categories as $c): ?>
            <option value="<?= $c['id'] ?>" <?= (string) $filters['category'] === (string) $c['id'] ? 'selected' : '' ?>>
              <?= e($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-lg-2">
        <select name="condition" class="form-select form-select-sm" data-autosubmit>
          <option value="">Any condition</option>
          <?php foreach ($conditions as $c): ?>
            <option value="<?= e($c) ?>" <?= $filters['condition'] === $c ? 'selected' : '' ?>><?= e($c) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-lg-2">
        <select name="stock" class="form-select form-select-sm" data-autosubmit>
          <option value="">Any stock status</option>
          <option value="in"  <?= $filters['stock'] === 'in'  ? 'selected' : '' ?>>In Stock</option>
          <option value="low" <?= $filters['stock'] === 'low' ? 'selected' : '' ?>>Low Stock</option>
          <option value="out" <?= $filters['stock'] === 'out' ? 'selected' : '' ?>>Out of Stock</option>
        </select>
      </div>
      <div class="col-6 col-lg-2">
        <select name="sort" class="form-select form-select-sm" data-autosubmit>
          <option value="name"  <?= $filters['sort'] === 'name'  ? 'selected' : '' ?>>Sort: Name</option>
          <option value="stock" <?= $filters['sort'] === 'stock' ? 'selected' : '' ?>>Sort: Lowest stock</option>
          <option value="price" <?= $filters['sort'] === 'price' ? 'selected' : '' ?>>Sort: Highest price</option>
          <option value="new"   <?= $filters['sort'] === 'new'   ? 'selected' : '' ?>>Sort: Newest</option>
        </select>
      </div>
    </form>
  </div>

  <div class="table-responsive">
    <table class="table table-hover align-middle">
      <thead>
        <tr>
          <th style="width:56px"></th><th>Barcode</th><th>Item</th><th>Category</th><th>Condition</th>
          <th class="text-end">Cost</th><th class="text-end">Price</th>
          <th class="text-center">Qty</th><th class="text-center">Reorder</th>
          <th>Status</th><th class="text-end no-print">Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php if (!$items): ?>
        <tr><td colspan="11" class="text-center text-muted py-5">
          <i class="bi bi-inbox fs-3 d-block mb-2"></i>No items match the current filters.</td></tr>
      <?php endif; ?>

      <?php foreach ($items as $i):
            [$label, $tone] = stock_status((int) $i['quantity'], (int) $i['reorder_level']); ?>
        <tr>
          <td>
            <?php $img = product_image($i['image_path'] ?? null, true); ?>
            <a href="<?= url('inventory.view', ['id' => $i['id']]) ?>" class="inv-thumb">
              <?php if ($img): ?><img src="<?= e($img) ?>" alt="" loading="lazy">
              <?php else: ?><i class="bi bi-<?= category_icon($i['category_name'] ?? '') ?>"></i><?php endif; ?>
            </a>
          </td>
          <td>
            <div class="mono fw-semibold"><?= e($i['sku']) ?></div>
            <?php if ((int) $i['quantity'] > 0): ?>
              <div class="text-muted-sm mono" style="font-size:.7rem">
                <?= e($i['sku']) ?>-01<?= (int) $i['quantity'] > 1
                    ? '…' . sprintf('%02d', (int) $i['quantity']) : '' ?>
              </div>
            <?php endif; ?>
          </td>
          <td>
            <a href="<?= url('inventory.view', ['id' => $i['id']]) ?>" class="fw-semibold text-dark">
              <?= e($i['name']) ?></a>
            <?php if ($i['brand']): ?>
              <div class="text-muted-sm"><?= e($i['brand']) ?><?= $i['serial_no'] ? ' &middot; SN ' . e($i['serial_no']) : '' ?></div>
            <?php endif; ?>
          </td>
          <td class="text-muted-sm"><?= e($i['category_name'] ?? '—') ?></td>
          <td><span class="badge badge-soft-<?= condition_class($i['condition_grade']) ?>">
              <?= e($i['condition_grade']) ?></span></td>
          <td class="text-end text-muted"><?= peso($i['cost_price']) ?></td>
          <td class="text-end fw-semibold"><?= peso($i['selling_price']) ?></td>
          <td class="text-center fw-bold"><?= num($i['quantity']) ?></td>
          <td class="text-center text-muted"><?= num($i['reorder_level']) ?></td>
          <td><span class="badge badge-soft-<?= $tone ?>"><?= e($label) ?></span></td>
          <td class="text-end no-print">
            <div class="btn-group btn-group-sm">
              <a class="btn btn-outline-secondary" title="View"
                 href="<?= url('inventory.view', ['id' => $i['id']]) ?>"><i class="bi bi-eye"></i></a>
              <a class="btn btn-outline-secondary" title="Print a barcode label for each piece"
                 target="_blank" href="<?= url('inventory.units', ['id' => $i['id'], 'only' => 'stock']) ?>">
                <i class="bi bi-upc-scan"></i></a>
              <?php if (Auth::canEditItems()): ?>
                <a class="btn btn-outline-secondary" title="Edit"
                   href="<?= url('inventory.edit', ['id' => $i['id']]) ?>"><i class="bi bi-pencil"></i></a>
              <?php endif; ?>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
