<?php
/**
 * The database installer has already been run and is now DISABLED.
 *
 * This file is kept only as a harmless placeholder. It can no longer create,
 * drop or overwrite any database. You can safely delete it.
 *
 * If you ever need to reinstall the database from scratch (for example on a
 * groupmate's laptop), use the copy of install.php inside
 * aa-inventory-system.zip, or import database/schema.sql and database/seed.sql
 * through phpMyAdmin.
 */
http_response_code(410);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Installer disabled &middot; A&amp;A ISDSS</title>
<link rel="stylesheet" href="assets/vendor/bootstrap/bootstrap.min.css">
<link rel="stylesheet" href="assets/vendor/icons/bootstrap-icons.min.css">
<style>body{background:#f1f4f8;font-family:"Segoe UI",system-ui,sans-serif;padding:60px 16px}</style>
</head>
<body>
<div style="max-width:560px;margin:auto" class="card border-0 shadow-sm">
  <div class="card-body p-4 text-center">
    <i class="bi bi-shield-check text-success" style="font-size:2.4rem"></i>
    <h1 class="h5 fw-bold mt-3">The installer has been disabled</h1>
    <p class="text-muted">
      The <code>aa_inventory</code> database has already been installed, so this page
      no longer does anything. You can safely delete <code>install.php</code>
      from the project folder.
    </p>
    <a href="index.php" class="btn btn-primary">
      <i class="bi bi-box-arrow-in-right"></i> Open the system</a>
  </div>
</div>
</body>
</html>
