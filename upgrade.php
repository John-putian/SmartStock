<?php
/**
 * The FR-11 notifications upgrade has already been run and is now DISABLED.
 *
 * This file is a harmless placeholder that replaces upgrade_notifications.php
 * once the upgrade is finished. It cannot create, alter or write anything.
 * You can safely delete it.
 */
http_response_code(410);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Upgrade disabled &middot; Smart Stock</title>
<link rel="stylesheet" href="assets/vendor/bootstrap/bootstrap.min.css">
<link rel="stylesheet" href="assets/vendor/icons/bootstrap-icons.min.css">
<style>body{background:#f1f4f8;font-family:"Segoe UI",system-ui,sans-serif;padding:60px 16px}</style>
</head>
<body>
<div style="max-width:560px;margin:auto" class="card border-0 shadow-sm">
  <div class="card-body p-4 text-center">
    <i class="bi bi-shield-check text-success" style="font-size:2.4rem"></i>
    <h1 class="h5 fw-bold mt-3">The notifications upgrade has been disabled</h1>
    <p class="text-muted">
      The <code>notifications</code> table is already installed, so this page no
      longer does anything. You can safely delete it from the project folder.
    </p>
    <a href="index.php" class="btn btn-primary">
      <i class="bi bi-box-arrow-in-right"></i> Open Smart Stock</a>
  </div>
</div>
</body>
</html>
