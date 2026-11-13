<?php
require_once __DIR__ . '/includes/auth.php';
require_role(['dispensary']);

$rows = $pdo->query("SELECT m.created_at, m.movement_type, m.quantity, d.drug_name, s.full_name
                     FROM stock_movement m
                     JOIN drug d ON d.drug_id = m.drug_id
                     LEFT JOIN staff s ON s.staff_id = m.staff_id
                     ORDER BY m.movement_id DESC LIMIT 100")->fetchAll();

$title = 'Stock Movements';
include __DIR__ . '/includes/header.php';
?>
<h1>Stock Movements</h1>
<p class="small">Every purchase and every medicine dispensed is recorded here (last 100).</p>
<div class="panel">
<table>
  <tr><th>Date and time</th><th>Medicine</th><th>Type</th><th>Quantity</th><th>Done by</th></tr>
  <?php foreach ($rows as $r): $in = $r['quantity'] > 0; ?>
    <tr>
      <td><?= e(date('d M Y H:i', strtotime($r['created_at']))) ?></td>
      <td><?= e($r['drug_name']) ?></td>
      <td><?= badge($r['movement_type'], $in ? 'green' : 'amber') ?></td>
      <td><?= $in ? '+' : '' ?><?= e($r['quantity']) ?></td>
      <td><?= e($r['full_name'] ?? 'System') ?></td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="5">No stock movements yet.</td></tr><?php endif; ?>
</table>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>