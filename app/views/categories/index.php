<div class="row g-3">
  <div class="col-lg-8">
    <div class="card">
      <div class="card-header"><i class="bi bi-tags"></i> Product Categories</div>
      <div class="card-body py-2 border-bottom text-muted-sm">
        Surplus arrives at random, so a category stays empty until a container happens to bring
        something for it. Empty categories are kept here and stay selectable when you add an item,
        but they are hidden from the Point of Sale and Inventory filters so cashiers never see a
        dead choice.
      </div>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead><tr><th>Category</th><th>Description</th>
            <th class="text-center">Items</th><th class="text-center">Units</th>
            <th class="text-end no-print">Actions</th></tr></thead>
          <tbody>
          <?php foreach ($categories as $c): ?>
            <tr>
              <td class="fw-semibold"><?= e($c['name']) ?>
                <?php if ((int) $c['item_count'] === 0): ?>
                  <span class="badge badge-soft-secondary ms-1"
                        title="Kept, but hidden from the POS and Inventory filters until a container brings stock for it">
                    <i class="bi bi-eye-slash"></i> hidden while empty</span>
                <?php endif; ?>
                <?php if (!empty($c['code'])): ?>
                  <div class="mono text-muted-sm">barcode prefix AA-<?= e($c['code']) ?>-</div>
                <?php endif; ?>
              </td>
              <td class="text-muted-sm"><?= e($c['description'] ?: '—') ?></td>
              <td class="text-center"><span class="badge badge-soft-primary"><?= num($c['item_count']) ?></span></td>
              <td class="text-center"><?= num($c['unit_count']) ?></td>
              <td class="text-end no-print">
                <button class="btn btn-sm btn-outline-secondary"
                        onclick='editCat(<?= js_json($c, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                  <i class="bi bi-pencil"></i></button>
                <form method="post" action="<?= url('categories.delete', ['id' => $c['id']]) ?>" class="d-inline">
<?= csrf_field() ?>
<button type="submit" class="btn btn-sm btn-outline-danger"
                   
                   onclick="return AA.confirmLink(this,'Delete this category? Only empty categories can be removed.')">
                  <i class="bi bi-trash"></i></button></form>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="card">
      <div class="card-header" id="formTitle"><i class="bi bi-plus-circle"></i> Add Category</div>
      <form class="card-body" method="post" action="<?= url('categories.save') ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="id" id="catId" value="">
        <div class="mb-3">
          <label class="form-label fw-semibold">Category name</label>
          <input name="name" id="catName" class="form-control" required placeholder="e.g. Kitchen Appliances">
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold">Description</label>
          <textarea name="description" id="catDesc" class="form-control" rows="3"
                    placeholder="Short description of what belongs in this category"></textarea>
        </div>
        <button class="btn btn-primary w-100"><i class="bi bi-save"></i> Save Category</button>
        <button type="button" class="btn btn-link w-100 mt-1 text-muted" onclick="resetCat()">Clear form</button>
      </form>
    </div>
  </div>
</div>

<?php $inlineScript = <<<'JS'
function editCat(c) {
  document.getElementById('catId').value = c.id;
  document.getElementById('catName').value = c.name;
  document.getElementById('catDesc').value = c.description || '';
  document.getElementById('formTitle').innerHTML = '<i class="bi bi-pencil"></i> Edit Category';
  window.scrollTo({ top: 0, behavior: 'smooth' });
}
function resetCat() {
  document.getElementById('catId').value = '';
  document.getElementById('catName').value = '';
  document.getElementById('catDesc').value = '';
  document.getElementById('formTitle').innerHTML = '<i class="bi bi-plus-circle"></i> Add Category';
}
JS; ?>
