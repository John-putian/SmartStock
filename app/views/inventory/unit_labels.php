<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Unit Labels &middot; <?= e($p['sku']) ?></title>
<link rel="icon" href="<?= base_url('assets/img/favicon.png') ?>" type="image/png">
<link rel="stylesheet" href="<?= base_url('assets/vendor/bootstrap/bootstrap.min.css') ?>">
<link rel="stylesheet" href="<?= base_url('assets/vendor/icons/bootstrap-icons.min.css') ?>">
<style>
  body { background:#f4f1ec; font-family:"Segoe UI",system-ui,sans-serif; padding:26px 20px }
  .sheet { display:grid; grid-template-columns:repeat(3, 1fr); gap:10px; max-width:960px; margin:auto }
  .lbl {
    background:#fff; border:1px solid #d8d0c6; border-top:3px solid #c41114;
    border-radius:6px; padding:12px 10px; text-align:center; break-inside:avoid;
  }
  .lbl .store { font-size:9px; font-weight:800; letter-spacing:.1em; color:#c41114 }
  .lbl .qr { margin:8px 0 6px }
  .lbl .qr svg { width:104px; height:104px }
  .lbl .code { font-family:Consolas,monospace; font-size:12px; font-weight:700; letter-spacing:.02em }
  .lbl .nm { font-size:11px; font-weight:600; margin:4px 0 2px; line-height:1.25 }
  .lbl .meta { font-size:10px; color:#7a726a }
  .lbl .price { font-size:16px; font-weight:800; color:#c41114; margin-top:4px }
  .lbl.sold { opacity:.45 }
  .lbl .sold-tag {
    display:inline-block; font-size:9px; font-weight:700; letter-spacing:.06em;
    background:#ece7e0; color:#5c554e; border-radius:3px; padding:1px 6px; margin-top:4px;
  }
  .hdr { max-width:960px; margin:0 auto 16px }
  @media print {
    body { background:#fff; padding:0 }
    .no-print { display:none }
    .sheet { grid-template-columns:repeat(3, 1fr); gap:6px; max-width:none }
    .lbl { border-color:#999 }
    @page { margin:10mm }
  }
</style>
</head>
<body>

<div class="hdr d-flex align-items-center gap-3 no-print">
  <img src="<?= base_url('assets/img/logo-sm.png') ?>" alt="" style="width:52px;height:52px">
  <div class="flex-grow-1">
    <div class="fw-bold"><?= e($p['name']) ?></div>
    <div class="text-muted" style="font-size:.85rem">
      <?= num(count($units)) ?> label(s) &middot; one per physical piece &middot;
      item <?= e($p['sku']) ?>
    </div>
  </div>
  <div class="d-flex gap-2">
    <a class="btn btn-sm btn-outline-secondary"
       href="<?= url('inventory.units', ['id' => $p['id']]) ?>">All pieces</a>
    <a class="btn btn-sm btn-outline-secondary"
       href="<?= url('inventory.units', ['id' => $p['id'], 'only' => 'stock']) ?>">In stock only</a>
    <button class="btn btn-sm btn-primary" onclick="window.print()">
      <i class="bi bi-printer"></i> Print</button>
    <button class="btn btn-sm btn-outline-secondary" onclick="window.close()">Close</button>
  </div>
</div>

<?php if (!$units): ?>
  <div class="text-center text-muted py-5">
    No pieces are registered for this item yet. Receive stock first &mdash;
    each piece received gets its own barcode.
  </div>
<?php endif; ?>

<div class="sheet">
  <?php foreach ($units as $i => $u): ?>
    <div class="lbl <?= $u['status'] !== 'in_stock' ? 'sold' : '' ?>">
      <div class="store">A&amp;A SURPLUS &middot; SMART STOCK</div>
      <div class="qr" id="qr<?= $i ?>"></div>
      <div class="code"><?= e($u['unit_code']) ?></div>
      <div class="nm"><?= e($p['name']) ?></div>
      <div class="meta">
        <?= e($u['condition_grade'] ?: $p['condition_grade']) ?>
        <?= $p['brand'] ? ' &middot; ' . e($p['brand']) : '' ?>
      </div>
      <?php if ($u['notes']): ?>
        <div class="meta" style="font-style:italic"><?= e($u['notes']) ?></div>
      <?php endif; ?>
      <div class="price">&#8369;<?= number_format((float) $p['selling_price'], 2) ?></div>
      <?php if ($u['status'] !== 'in_stock'): ?>
        <div class="sold-tag"><?= strtoupper(e($u['status'] === 'sold' ? 'SOLD ' . ($u['invoice_no'] ?? '') : $u['status'])) ?></div>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>

<script src="<?= base_url('assets/vendor/qrcode.js') ?>"></script>
<script>
  // FR-03 : each label encodes ONE piece's own barcode, so scanning it at the
  // till adds that exact unit to the sale.
  var codes = <?= js_json(array_column($units, 'unit_code')) ?>;
  codes.forEach(function (code, i) {
    var qr = qrcode(0, 'M');
    qr.addData(code);
    qr.make();
    var el = document.getElementById('qr' + i);
    if (el) el.innerHTML = qr.createSvgTag({ cellSize: 3, margin: 1, scalable: true });
  });
</script>
</body>
</html>
