<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>QR Label &middot; <?= e($p['sku']) ?></title>
<link rel="stylesheet" href="<?= base_url('assets/vendor/bootstrap/bootstrap.min.css') ?>">
<link rel="stylesheet" href="<?= asset_url('assets/css/style.css') ?>">
<style>
  body { background:#f4f1ec; padding:30px }
  .label-card {
    width: 300px; margin: 0 auto 16px; background:#fff; border:1px solid #e4ded5; border-top:3px solid #c41114;
    border-radius:8px; padding:16px; text-align:center;
  }
  .label-card .store { font-size:10px; font-weight:800; letter-spacing:.1em; color:#c41114 }
  .label-card .nm { font-size:13px; font-weight:600; margin:8px 0 2px; line-height:1.3 }
  .label-card .sku { font-family:Consolas,monospace; font-size:12px; color:#5c554e }
  .label-card .price { font-size:19px; font-weight:800; color:#c41114; margin-top:6px }
  #qr img, #qr canvas { display:block; margin:0 auto }
  /* One small label was printing in the middle of a blank A4 sheet. The page
     is sized to the label itself, so a sheet of sticker stock or a label
     printer gets the card and nothing else. For plain A4, use:
         @page { size: A4 portrait; margin: 12mm; }                          */
  @media print {
    @page { size: 80mm 110mm; margin: 4mm; }
    body { background:#fff; padding:0 }
    .no-print { display:none !important }
    .label-card {
      width:auto; max-width:none; margin:0; border:0; border-radius:0;
      break-inside: avoid;
    }
  }
</style>
</head>
<body>

<div class="label-card">
  <img src="<?= base_url('assets/img/logo-sm.png') ?>" alt="A&amp;A Surplus" style="width:54px;height:54px;margin-bottom:4px">
  <div class="store">A&amp;A SURPLUS &middot; SMART STOCK</div>
  <div style="font-size:10px;color:#7a726a">Panabo City, Davao del Norte</div>
  <div id="qr" style="margin:12px 0"></div>
  <div class="sku"><?= e($p['sku']) ?></div>
  <div class="nm"><?= e($p['name']) ?></div>
  <div style="font-size:11px;color:#7a726a"><?= e($p['condition_grade']) ?><?= $p['brand'] ? ' &middot; ' . e($p['brand']) : '' ?></div>
  <div class="price">&#8369;<?= number_format((float) $p['selling_price'], 2) ?></div>
</div>

<div class="text-center no-print">
  <button class="btn btn-primary btn-sm" onclick="window.print()">Print label</button>
  <button class="btn btn-outline-secondary btn-sm" onclick="window.close()">Close</button>
</div>

<script src="<?= base_url('assets/vendor/qrcode.js') ?>"></script>
<script>
  // FR-03 : the label encodes the SKU so it can be scanned at the POS terminal.
  var qr = qrcode(0, 'M');
  qr.addData(<?= js_json($p['sku']) ?>);
  qr.make();
  document.getElementById('qr').innerHTML = qr.createSvgTag({ cellSize: 4, margin: 2, scalable: false });
</script>
</body>
</html>
