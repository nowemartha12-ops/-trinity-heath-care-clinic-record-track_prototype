<?php
require_once __DIR__ . '/includes/auth.php';
require_role(['dispensary']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'dispense') {
        $rxId = (int)$_POST['prescription_id'];
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("SELECT pr.*, d.quantity_in_stock, d.expiry_date FROM prescription pr
                               JOIN drug d ON d.drug_id = pr.drug_id
                               WHERE pr.prescription_id = ? AND pr.status = 'Pending' FOR UPDATE");
        $stmt->execute([$rxId]);
        $rx = $stmt->fetch();

        if (!$rx) {
            $pdo->rollBack();
            flash('This prescription was already dispensed.');
        } elseif (visit_money($pdo, $rx['visit_id'])['balance'] > 0) {
            $pdo->rollBack();
            flash('The patient has not paid yet. Send the patient to Finance first.');
        } elseif (!empty($rx['expiry_date']) && $rx['expiry_date'] < date('Y-m-d')) {
            $pdo->rollBack();
            flash('This medicine has expired. Do not dispense it.');
        } elseif ($rx['quantity_in_stock'] < $rx['quantity']) {
            $pdo->rollBack();
            flash('Not enough stock for this medicine.');
        } else {
            $pdo->prepare("UPDATE drug SET quantity_in_stock = quantity_in_stock - ? WHERE drug_id = ?")
                ->execute([$rx['quantity'], $rx['drug_id']]);
            $pdo->prepare("UPDATE prescription SET status = 'Dispensed', dispensed_date = NOW() WHERE prescription_id = ?")
                ->execute([$rxId]);
            $pdo->commit();
            log_stock($pdo, $rx['drug_id'], 'Dispensed', -$rx['quantity']);
            log_action($pdo, "Dispensed prescription #$rxId");
            flash('Medicine dispensed and stock updated.');
        }

    } elseif ($action === 'add_stock') {
        $qty = (int)$_POST['quantity'];
        $drugId = (int)$_POST['drug_id'];
        if ($qty > 0) {
            $pdo->prepare("UPDATE drug SET quantity_in_stock = quantity_in_stock + ? WHERE drug_id = ?")
                ->execute([$qty, $drugId]);
            if (!empty($_POST['expiry_date'])) {
                $pdo->prepare("UPDATE drug SET expiry_date = ? WHERE drug_id = ?")->execute([$_POST['expiry_date'], $drugId]);
            }
            log_stock($pdo, $drugId, 'Purchase', $qty);
            log_action($pdo, "Added $qty to stock of drug #$drugId");
            flash('Stock added.');
        }

    } elseif ($action === 'new_drug') {
        $name = trim($_POST['drug_name'] ?? '');
        if ($name !== '') {
            $qty = max(0, (int)$_POST['quantity']);
            $pdo->prepare("INSERT INTO drug (supplier_id, drug_name, quantity_in_stock, reorder_level, unit_price, expiry_date) VALUES (?,?,?,?,?,?)")
                ->execute([$_POST['supplier_id'] ?: null, $name, $qty, max(0, (int)$_POST['reorder_level']),
                           max(0, (int)$_POST['unit_price']), $_POST['expiry_date'] ?: null]);
            $newId = $pdo->lastInsertId();
            if ($qty > 0) {
                log_stock($pdo, $newId, 'Purchase', $qty);
            }
            log_action($pdo, "Added new drug: $name");
            flash('New medicine added.');
        }
    }
    header('Location: pharmacy.php');
    exit;
}

$pending = $pdo->query("SELECT pr.prescription_id, pr.visit_id, pr.dosage, pr.route, pr.quantity,
                               d.drug_name, d.quantity_in_stock, p.full_name
                        FROM prescription pr
                        JOIN drug d ON d.drug_id = pr.drug_id
                        JOIN visit v ON v.visit_id = pr.visit_id
                        JOIN patient p ON p.patient_id = v.patient_id
                        WHERE pr.status = 'Pending' ORDER BY pr.prescription_id")->fetchAll();

$drugs = $pdo->query("SELECT d.*, s.supplier_name FROM drug d
                      LEFT JOIN supplier s ON s.supplier_id = d.supplier_id
                      ORDER BY d.drug_name")->fetchAll();
$suppliers = $pdo->query("SELECT * FROM supplier ORDER BY supplier_name")->fetchAll();

$title = 'Medicines and Dispensing';
include __DIR__ . '/includes/header.php';
?>
<h1>Medicines and Dispensing</h1>

<h2>Prescriptions waiting</h2>
<div class="panel">
<table>
  <tr><th>Patient</th><th>Medicine</th><th>Dosage</th><th>Route</th><th>Qty</th><th>In stock</th><th>Payment</th><th></th></tr>
  <?php foreach ($pending as $r):
      $paid = visit_money($pdo, $r['visit_id'])['balance'] <= 0; ?>
    <tr>
      <td><?= e($r['full_name']) ?></td>
      <td><?= e($r['drug_name']) ?></td>
      <td><?= e($r['dosage']) ?></td>
      <td><?= e($r['route']) ?></td>
      <td><?= e($r['quantity']) ?></td>
      <td><?= e($r['quantity_in_stock']) ?></td>
      <td><?= $paid ? badge('Paid', 'green') : badge('Not paid', 'red') ?></td>
      <td>
        <?php if ($paid): ?>
        <form method="post">
          <input type="hidden" name="action" value="dispense">
          <input type="hidden" name="prescription_id" value="<?= e($r['prescription_id']) ?>">
          <button type="submit">Dispense</button>
        </form>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$pending): ?><tr><td colspan="8">No prescriptions waiting.</td></tr><?php endif; ?>
</table>
</div>

<h2>Medicines in stock</h2>
<div class="panel">
<table>
  <tr><th>Medicine</th><th>In stock</th><th>Reorder level</th><th>Price</th><th>Expiry date</th><th>Supplier</th><th>Status</th></tr>
  <?php foreach ($drugs as $d): $st = drug_status($d); ?>
    <tr>
      <td><?= e($d['drug_name']) ?></td>
      <td><?= e($d['quantity_in_stock']) ?></td>
      <td><?= e($d['reorder_level']) ?></td>
      <td><?= money($d['unit_price']) ?></td>
      <td><?= e($d['expiry_date']) ?></td>
      <td><?= e($d['supplier_name']) ?></td>
      <td><?= badge($st[0], $st[1]) ?></td>
    </tr>
  <?php endforeach; ?>
</table>
</div>

<div class="grid2">
  <form method="post" class="box">
    <b>Add stock received</b>
    <input type="hidden" name="action" value="add_stock">
    <label>Medicine</label>
    <select name="drug_id">
      <?php foreach ($drugs as $d): ?><option value="<?= e($d['drug_id']) ?>"><?= e($d['drug_name']) ?></option><?php endforeach; ?>
    </select>
    <label>Quantity received</label>
    <input type="number" name="quantity" min="1" required>
    <label>New expiry date (optional)</label>
    <input type="date" name="expiry_date">
    <p><button type="submit">Add stock</button></p>
  </form>

  <form method="post" class="box">
    <b>Add a new medicine</b>
    <input type="hidden" name="action" value="new_drug">
    <label>Medicine name</label>
    <input type="text" name="drug_name" required>
    <div class="grid2">
      <div><label>Quantity</label><input type="number" name="quantity" value="0" min="0"></div>
      <div><label>Reorder level</label><input type="number" name="reorder_level" value="10" min="0"></div>
      <div><label>Unit price (UGX)</label><input type="number" name="unit_price" min="0" required></div>
      <div><label>Expiry date</label><input type="date" name="expiry_date"></div>
    </div>
    <label>Supplier</label>
    <select name="supplier_id">
      <option value="">None</option>
      <?php foreach ($suppliers as $s): ?><option value="<?= e($s['supplier_id']) ?>"><?= e($s['supplier_name']) ?></option><?php endforeach; ?>
    </select>
    <p><button type="submit">Add medicine</button></p>
  </form>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>