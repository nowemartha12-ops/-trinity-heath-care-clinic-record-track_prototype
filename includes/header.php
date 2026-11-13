<?php
$page = basename($_SERVER['SCRIPT_NAME']);
function nav_link($file, $label, $page) {
    $class = ($page === $file) ? 'active' : '';
    echo '<a class="' . $class . '" href="' . $file . '">' . $label . '</a>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ?? 'Trinity Clinic MIS') ?></title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<?php if (!empty($_SESSION['staff_id'])): ?>
<div class="layout">
<aside class="sidebar">
  <div class="logo">Trinity Health Care Clinic Record Track</div>
  <div class="who"><?= e($_SESSION['name']) ?><span class="role"><?= e($_SESSION['role']) ?></span></div>

  <div class="grp">Main</div>
  <?php nav_link('index.php', 'Dashboard', $page); ?>

  <?php if (can(['clinician', 'lab', 'finance'])): ?>
  <div class="grp">Clinic</div>
  <?php if (can(['clinician'])): nav_link('patients.php', 'Patients', $page); nav_link('followups.php', 'Appointments / Follow-ups', $page); endif; ?>
  <?php if (can(['lab'])): nav_link('lab.php', 'Laboratory and Scans', $page); endif; ?>
  <?php if (can(['finance'])): nav_link('payments.php', 'Billing and Payments', $page); endif; ?>
  <?php endif; ?>

  <?php if (can(['dispensary'])): ?>
  <div class="grp">Pharmacy</div>
  <?php nav_link('pharmacy.php', 'Medicines and Dispensing', $page); nav_link('stock_movements.php', 'Stock Movements', $page); nav_link('suppliers.php', 'Suppliers', $page); ?>
  <?php endif; ?>

  <?php if (can(['clinician'])): ?>
  <div class="grp">Reports</div>
  <?php nav_link('reports.php', 'Reports', $page); ?>
  <?php endif; ?>

  <?php if (can([])): ?>
  <div class="grp">Admin</div>
  <?php nav_link('users.php', 'Users and Activity Log', $page); ?>
  <?php endif; ?>

  <a class="logout" href="logout.php">Log out</a>
</aside>
<div class="content">
<?php endif; ?>
<main>
<?php $m = flash(); if ($m): ?><div class="msg"><?= e($m) ?></div><?php endif; ?>