<?php
require_once __DIR__ . '/includes/auth.php';
require_role(['dispensary']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['supplier_name'] ?? '');
    if ($name !== '') {
        $pdo->prepare("INSERT INTO supplier (supplier_name, phone, address) VALUES (?,?,?)")
            ->execute([$name, trim($_POST['phone'] ?? ''), trim($_POST['address'] ?? '')]);
        log_action($pdo, "Added supplier: $name");
        flash('Supplier added.');
    } else {
        flash('Please enter the supplier name.');
    }
    header('Location: suppliers.php');
    exit;
}

$suppliers = $pdo->query("SELECT s.*, COUNT(d.drug_id) AS drugs
                          FROM supplier s LEFT JOIN drug d ON d.supplier_id = s.supplier_id
                          GROUP BY s.supplier_id ORDER BY s.supplier_name")->fetchAll();

$title = 'Suppliers';
include __DIR__ . '/includes/header.php';
?>
<h1>Suppliers</h1>
<div class="panel">
<table>
  <tr><th>Supplier</th><th>Phone</th><th>Address</th><th>Medicines supplied</th></tr>
  <?php foreach ($suppliers as $s): ?>
    <tr><td><?= e($s['supplier_name']) ?></td><td><?= e($s['phone']) ?></td><td><?= e($s['address']) ?></td><td><?= e($s['drugs']) ?></td></tr>
  <?php endforeach; ?>
  <?php if (!$suppliers): ?><tr><td colspan="4">No suppliers yet.</td></tr><?php endif; ?>
</table>
</div>

<h2>Add a supplier</h2>
<form method="post" class="box">
  <div class="grid2">
    <div><label>Supplier name</label><input type="text" name="supplier_name" required></div>
    <div><label>Phone</label><input type="text" name="phone"></div>
  </div>
  <label>Address</label>
  <input type="text" name="address">
  <p><button type="submit">Add supplier</button></p>
</form>
<?php include __DIR__ . '/includes/footer.php'; ?>