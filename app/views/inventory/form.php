<?php
/** One continuous photo-first form; server-side checks remain authoritative. */
$errorIds = ['name'=>'productName', 'category_id'=>'productCategory', 'selling_price'=>'sellPrice',
    'cost_price'=>'costPrice', 'quantity'=>'productQuantity', 'sku'=>'productSku', 'photo'=>'itemPhoto', 'brand'=>'productBrand', 'serial_no'=>'productSerial', 'reorder_level'=>'reorderLevel'];
$invalid = static fn(string $name): string => !empty($errors[$name]) ? ' is-invalid' : '';
$feedback = static function(string $name) use ($errors): string {
    return '<div id="field-' . e($name) . '-error" data-field-error="' . e($name)
        . '" class="invalid-feedback' . (!empty($errors[$name]) ? ' d-block' : '') . '">'
        . e($errors[$name] ?? '') . '</div>';
};
$attrs = static fn(string $name): string => 'aria-describedby="field-' . e($name) . '-error"'
    . (!empty($errors[$name]) ? ' aria-invalid="true"' : '');
?>
<link rel="stylesheet" href="<?= asset_url('assets/css/product-form.css') ?>">
<div class="product-form">
  <a href="<?= url('inventory') ?>" class="btn btn-outline-secondary mb-3"><i class="bi bi-arrow-left"></i> Back to inventory</a>
  <p class="text-muted mb-3">Start with a photo or enter the details yourself. Review everything before saving.</p>
  <nav class="product-steps mb-3" aria-label="Product entry steps">
    <a href="#productPhoto"><span>1</span> Photo</a><a href="#productDetails"><span>2</span> Details</a>
    <a href="#productPricing"><span>3</span> Price &amp; stock</a><a href="#productSave"><span>4</span> Save &amp; QR</a>
  </nav>
  <div id="formErrors" class="alert alert-danger <?= $errors ? '' : 'd-none' ?>" role="alert" tabindex="-1">
    <strong>Please correct the highlighted fields.</strong>
    <ul id="formErrorList" class="mb-0 mt-1">
      <?php foreach ($errors as $field => $error): ?><li><?php if (isset($errorIds[$field])): ?>
        <a href="#<?= e($errorIds[$field]) ?>"><?= e($error) ?></a>
      <?php else: ?><?= e($error) ?><?php endif; ?></li><?php endforeach; ?>
    </ul>
  </div>
  <form id="inventoryForm" method="post" enctype="multipart/form-data" novalidate
        action="<?= $isEdit ? url('inventory.edit', ['id' => $item['id']]) : url('inventory.create') ?>">
    <?= csrf_field() ?>
      <div class="card mb-3" id="productPhoto">
        <div class="card-header"><i class="bi bi-camera"></i> 1. Product photo <span class="text-muted fw-normal small">Optional</span></div>
        <div class="card-body">
          <?php $img = product_image($item['image_path'] ?? null); ?>
          <div class="item-hero mb-3" style="width:100%;height:190px">
            <?php if ($img): ?><img src="<?= e($img) ?>" alt="">
            <?php else: ?><i class="bi bi-camera"></i><?php endif; ?>
          </div>
          <div class="photo-upload-controls"><div class="d-grid gap-2 mb-2">
            <button type="button" id="takeProductPhoto" class="btn btn-primary py-2" disabled>
              <i class="bi bi-camera"></i> Take photo</button>
            <button type="button" id="chooseProductPhoto" class="btn btn-outline-secondary py-2" disabled>
              <i class="bi bi-images"></i> Choose from gallery</button>
          </div>
          <label for="itemPhoto" class="form-label small">Product photo</label>
          <input id="itemPhoto" type="file" name="photo" class="form-control form-control-sm<?= $invalid('photo') ?>" <?= $attrs('photo') ?>
                 accept="image/*">
          <div class="form-text">
            Photograph the actual piece.
            An iPhone photo or a large camera file is shrunk to a JPG on the phone
            before it is sent, so you do not have to convert anything yourself.
            Camera unavailable? Choose a photo from your gallery.
          </div>
          <div class="form-text">
            <i class="bi bi-info-circle"></i>
            This is a <strong>picture of the product</strong> for its record &mdash; it is not a QR
            scan. QR labels are printed after saving and are read at the till by a barcode scanner.
          </div>
          <div id="photoFileName" class="small text-muted mt-2" aria-live="polite">No new photo selected.</div>
          <?php if ($errors && isset($_FILES['photo']) && ($_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE): ?>
            <p class="small text-warning mt-2">Choose your photo again after correcting the fields. Browsers cannot restore an uploaded file.</p>
          <?php endif; ?>
          <?= $feedback('photo') ?>
          <?php $photoReady = PhotoAssistant::ready(); ?>
          <button type="button" id="suggestPhoto" class="btn btn-outline-primary w-100 mt-3"
                  data-enabled="<?= $photoReady ? '1' : '0' ?>" data-url="<?= e(url('inventory.describe')) ?>" disabled>
            <i class="bi bi-stars"></i> Suggest details from photo
          </button>
          <p class="form-text mb-0">Suggestions stay editable.
            <?= $photoReady ? 'Clicking sends this photo to Google Gemini for suggestions.' : 'Photo suggestions are unavailable. Enter the details below.' ?></p>
          <div id="photoStatus" class="small mt-2" role="status" aria-live="polite"></div>
          </div><!-- photo upload controls -->
          <div id="photoDraft" class="border rounded p-3 mt-3 d-none">
            <div class="fw-semibold mb-2">Review photo suggestions</div>
            <p class="small text-muted">Check the details against the actual item. Tick the fields you want to use; existing entries are left unticked.</p>
            <div class="form-check mb-2"><input type="checkbox" class="form-check-input" id="usePhotoName">
              <label class="form-check-label" for="usePhotoName">Use suggested name</label></div>
            <p id="photoName" class="small text-break"></p>
            <div class="form-check mb-2"><input type="checkbox" class="form-check-input" id="usePhotoCategory">
              <label class="form-check-label" for="usePhotoCategory">Use suggested category</label></div>
            <p id="photoCategory" class="small text-break"></p>
            <div class="form-check mb-2"><input type="checkbox" class="form-check-input" id="usePhotoDescription">
              <label class="form-check-label" for="usePhotoDescription">Use suggested description</label></div>
            <p id="photoDescription" class="small text-break" style="white-space:pre-wrap"></p>
            <div class="form-check mb-2"><input type="checkbox" class="form-check-input" id="usePhotoPrice">
              <label class="form-check-label" for="usePhotoPrice">Use suggested initial price</label></div>
            <p id="photoPrice" class="small fw-semibold"></p>
            <p id="photoPriceBasis" class="small text-muted"></p>
            <button type="button" id="applyPhoto" class="btn btn-primary btn-sm">Use selected details</button>
            <button type="button" id="dismissPhoto" class="btn btn-outline-secondary btn-sm">Dismiss</button>
            <div class="form-text">You can edit these fields before saving the item.</div>
          </div>
          <?php if ($img): ?>
            <div class="form-check mt-2">
              <input class="form-check-input" type="checkbox" name="remove_photo" value="1" id="rmPhoto">
              <label class="form-check-label small" for="rmPhoto">Remove the current photo</label>
            </div>
          <?php endif; ?>
        </div>
      </div>


    <section class="card mb-3" id="productDetails" aria-labelledby="detailsHeading">
      <div class="card-header" id="detailsHeading">2. Review product details</div>
      <div class="card-body"><div class="row g-3">
        <div class="col-md-8">
          <label for="productName" class="form-label fw-semibold">Item name <span class="text-danger">*</span></label>
          <input id="productName" name="name" maxlength="150" class="form-control<?= $invalid('name') ?>" <?= $attrs('name') ?> required value="<?= e($item['name']) ?>" placeholder="e.g. Two-door refrigerator">
          <?= $feedback('name') ?>
        </div>
        <div class="col-md-4">
          <label for="productCategory" class="form-label fw-semibold">Category <span class="text-danger">*</span></label>
          <select id="productCategory" name="category_id" class="form-select<?= $invalid('category_id') ?>" <?= $attrs('category_id') ?> required>
            <option value="">Select category…</option>
            <?php foreach ($categories as $c): ?><option value="<?= (int) $c['id'] ?>" <?= (int) ($item['category_id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
          </select><?= $feedback('category_id') ?>
        </div>
        <div class="col-md-4">
          <label for="productCondition" class="form-label fw-semibold">Condition</label>
          <select id="productCondition" name="condition_grade" class="form-select">
            <?php foreach ($conditions as $c): ?><option value="<?= e($c) ?>" <?= ($item['condition_grade'] ?? '') === $c ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?>
          </select><div class="form-text">Inspect the actual piece. Class A = near mint; For Parts = defective.</div>
        </div>
        <div class="col-md-8">
          <label for="productDescription" class="form-label fw-semibold">Description / remarks</label>
          <textarea id="productDescription" name="description" class="form-control" rows="3" placeholder="Visible features and any faults you have checked…"><?= e($item['description'] ?? '') ?></textarea>
        </div>
      </div></div>
    </section>
    <section class="card mb-3" id="productPricing" aria-labelledby="pricingHeading">
      <div class="card-header" id="pricingHeading">3. Price and quantity</div>
      <div class="card-body"><div class="row g-3">
        <div class="col-md-4">
          <label for="costPrice" class="form-label fw-semibold">Acquisition cost</label>
          <div class="input-group"><span class="input-group-text">₱</span>
            <input id="costPrice" name="cost_price" type="number" inputmode="decimal" step="0.01" min="0" class="form-control<?= $invalid('cost_price') ?>" <?= $attrs('cost_price') ?> value="<?= e($item['cost_price']) ?>" placeholder="0.00">
          </div><?= $feedback('cost_price') ?>
        </div>
        <div class="col-md-4">
          <label for="sellPrice" class="form-label fw-semibold">Selling price <span class="text-danger">*</span></label>
          <div class="input-group"><span class="input-group-text">₱</span>
            <input name="selling_price" id="sellPrice" type="number" inputmode="decimal" step="0.01" min="0.01" class="form-control<?= $invalid('selling_price') ?>" <?= $attrs('selling_price') ?> value="<?= e($item['selling_price']) ?>" required placeholder="0.00" data-listed="<?= e($isEdit ? ($listedPrice ?? (float) $item['selling_price']) : 0) ?>">
          </div><?= $feedback('selling_price') ?>
        </div>
        <div class="col-md-4">
          <label for="productQuantity" class="form-label fw-semibold">Quantity on hand <span class="text-danger">*</span></label>
          <input id="productQuantity" name="quantity" type="number" inputmode="numeric" min="0" step="1" required class="form-control<?= $invalid('quantity') ?>" <?= $attrs('quantity') ?> value="<?= e($item['quantity']) ?>">
          <?= $feedback('quantity') ?>
          <div class="form-text"><?= $isEdit ? 'A changed quantity records a stock adjustment.' : 'Each piece receives its own QR code when saved.' ?></div>
        </div>
      </div>
          <button type="button" id="suggestPrice" class="btn btn-outline-primary mt-2"
                  data-url="<?= e(url('inventory.price')) ?>">Suggest initial price</button>
          <?php $priceConfig = require BASE_PATH . '/config/photo-ai.php'; ?>
          <div class="form-text">Uses similar sales in this system.
            <?= !empty($priceConfig['demo_pricing']) ? 'Without matches, the demo suggests ' . peso($priceConfig['demo_price']) . ' as a placeholder.' : 'Without matches, enter the price manually.' ?>
            Review and edit before saving.</div>
          <div id="priceSuggestionStatus" class="small mt-2" role="status" aria-live="polite"></div>
          <div id="priceSuggestionPanel" class="border rounded p-2 mt-2 d-none">
            <div id="priceSuggestionAmount" class="fw-semibold"></div>
            <p id="priceSuggestionBasis" class="small mb-2"></p>
            <button type="button" id="useSuggestedPrice" class="btn btn-primary btn-sm">Use suggested price</button>
            <button type="button" id="dismissSuggestedPrice" class="btn btn-outline-secondary btn-sm">Dismiss</button>
          </div>

          <?php if ($isEdit && !Auth::canApprove()): ?>
            <?php $waiting = $pendingPrice ?? null; ?>
            <div class="form-text mt-2">
              <i class="bi bi-shield-lock"></i>
              <?php if (Auth::canSetPrice()): ?>
                You may raise this price yourself. Lowering it is sent to the
                administrator or manager for approval, and the item keeps its
                current price until they agree.
              <?php else: ?>
                This item is already listed, so changing its price &mdash; up or
                down &mdash; is sent to the administrator or manager for approval.
                It keeps its current price until they agree.
              <?php endif; ?>
            </div>

            <?php if ($waiting): ?>
              <div class="alert alert-warning py-2 px-3 mt-2 mb-0 small">
                A reduction to <strong><?= peso($waiting['new_price']) ?></strong> is already
                waiting for a decision, asked
                <?= date('M j, g:i A', strtotime($waiting['created_at'])) ?>.
              </div>
            <?php endif; ?>

            <div class="mt-2 d-none" id="priceReasonBox">
              <label class="form-label fw-semibold mb-1">Why are you changing it?</label>
              <textarea name="price_reason" class="form-control form-control-sm" rows="2"
                        maxlength="250"
                        placeholder="e.g. Chipped veneer on the left side, customer asked for a lower price."></textarea>
              <div class="form-text">The approver reads this before deciding.</div>
            </div>
          <?php endif; ?>
      </div>
    </section>
    <details class="card mb-3 product-more" id="moreDetails" <?= array_intersect_key($errors, array_flip(['sku','brand','serial_no','reorder_level'])) ? 'open' : '' ?>>
      <summary class="card-header">More details <span class="small fw-normal text-muted">Brand, SKU, serial number and reorder level</span></summary>
      <div class="card-body"><div class="row g-3">
        <div class="col-md-6"><label for="productBrand" class="form-label fw-semibold">Brand</label>
          <input id="productBrand" name="brand" maxlength="80" class="form-control<?= $invalid('brand') ?>" <?= $attrs('brand') ?> value="<?= e($item['brand'] ?? '') ?>" placeholder="Optional"><?= $feedback('brand') ?></div>
        <div class="col-md-6"><label for="productSku" class="form-label fw-semibold">SKU</label>
          <input id="productSku" name="sku" maxlength="40" class="form-control mono<?= $invalid('sku') ?>" <?= $attrs('sku') ?> value="<?= e($item['sku']) ?>" <?= $isEdit ? 'readonly' : '' ?> placeholder="Generated automatically">
          <?= $feedback('sku') ?><div class="form-text">Leave blank to generate a code when saving.</div></div>
        <div class="col-md-6"><label for="productSerial" class="form-label fw-semibold">Serial number</label>
          <input id="productSerial" name="serial_no" maxlength="60" class="form-control mono<?= $invalid('serial_no') ?>" <?= $attrs('serial_no') ?> value="<?= e($item['serial_no'] ?? '') ?>" placeholder="Optional, for appliances"><?= $feedback('serial_no') ?></div>
        <div class="col-md-6"><label for="reorderLevel" class="form-label fw-semibold">Reorder level</label>
          <input id="reorderLevel" name="reorder_level" type="number" inputmode="numeric" min="0" step="1" class="form-control<?= $invalid('reorder_level') ?>" <?= $attrs('reorder_level') ?> value="<?= e($item['reorder_level']) ?>"><?= $feedback('reorder_level') ?>
          <div class="form-text">Low-stock alert at or below this quantity.</div></div>
      </div></div>
    </details>
    <section id="productSave" class="product-next mb-3">
      <strong>4. Save, then print QR labels</strong>
      <p class="mb-0 mt-1 small">After saving, open <strong>Print labels for stock</strong> on the product details screen. Attach one label to each physical piece.</p>
    </section>
  </form>
  <div class="inventory-savebar">
    <span class="small text-muted">Review details, price and quantity before saving.</span>
    <button id="saveProduct" type="submit" form="inventoryForm" class="btn btn-primary fw-semibold"><i class="bi bi-save"></i> <?= $isEdit ? 'Save changes' : 'Save product' ?></button>
  </div>
  <?php if ($isEdit && Auth::canManage()): ?>
    <form id="archiveForm" method="post" action="<?= url('inventory.archive', ['id' => $item['id']]) ?>" class="mt-3">
      <?= csrf_field() ?><button type="submit" class="btn btn-outline-danger" onclick="return AA.confirmLink(this,'Archive this item? It will no longer appear in the catalogue or POS.')"><i class="bi bi-archive"></i> Archive item</button>
    </form>
  <?php endif; ?>
</div>
<script src="<?= asset_url('assets/js/inventory-form.js') ?>" defer></script>
<?php if ($isEdit && !Auth::canApprove()):
// Work staff may raise a price freely, so only a drop needs explaining. A
// cashier may not move a listed price at all, so any change does.
$anyChange = Auth::canSetPrice() ? 'false' : 'true';
$inlineScript = <<<JS
// Reveal the "why" box the moment the figure moves in a direction this account
// cannot apply on its own, so the reason is written while it is fresh.
(function () {
  var ANY_CHANGE = {$anyChange};
  var price = document.getElementById('sellPrice');
  var box   = document.getElementById('priceReasonBox');
  if (!price || !box) return;

  var listed = parseFloat(price.dataset.listed || '0') || 0;
  function sync() {
    var now = parseFloat(price.value);
    if (isNaN(now)) { box.classList.add('d-none'); return; }
    var needs = ANY_CHANGE
        ? Math.abs(now - listed) >= 0.005
        : now < listed - 0.005;
    box.classList.toggle('d-none', !needs);
  }
  price.addEventListener('input', sync);
  sync();
})();
JS; endif; ?>

<script src="<?= asset_url('assets/js/photo-assistant.js') ?>" defer></script>
<script src="<?= asset_url('assets/js/price-assistant.js') ?>" defer></script>
