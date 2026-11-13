<?php
require_once __DIR__ . '/includes/auth.php';
require_role(['clinician']);

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT v.*, p.full_name, s.service_name, s.fee
                       FROM visit v
                       JOIN patient p ON p.patient_id = v.patient_id
                       JOIN service s ON s.service_id = v.service_id
                       WHERE v.visit_id = ?");
$stmt->execute([$id]);
$visit = $stmt->fetch();
if (!$visit) {
    die('Visit not found. <a href="patients.php">Back</a>');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_notes') {
        $stmt = $pdo->prepare("UPDATE visit SET symptoms = ?, diagnosis = ?, next_visit_date = ? WHERE visit_id = ?");
        $stmt->execute([trim($_POST['symptoms']), trim($_POST['diagnosis']), $_POST['next_visit_date'] ?: null, $id]);
        log_action($pdo, "Updated consultation notes for visit #$id");
        flash('Consultation notes saved.');

    } elseif ($action === 'add_lab') {
        $test = trim($_POST['test_name'] ?? '');
        if ($test !== '') {
            $stmt = $pdo->prepare("INSERT INTO lab_request (visit_id, requested_by, test_name) VALUES (?,?,?)");
            $stmt->execute([$id, $_SESSION['staff_id'], $test]);
            log_action($pdo, "Requested test '$test' for visit #$id");
            flash('Test request sent to the laboratory.');
        }

    } elseif ($action === 'add_rx') {
        $route = in_array($_POST['route'], ['Oral', 'Injection', 'Other']) ? $_POST['route'] : 'Oral';
        $qty = max(1, (int)$_POST['quantity']);
        $stmt = $pdo->prepare("INSERT INTO prescription (visit_id, drug_id, dosage, route, quantity) VALUES (?,?,?,?,?)");
        $stmt->execute([$id, (int)$_POST['drug_id'], trim($_POST['dosage']), $route, $qty]);
        log_action($pdo, "Prescribed medicine for visit #$id");
        flash('Medicine prescribed.');
    }
    header("Location: visit.php?id=$id");
    exit;
}

$drugs = $pdo->query("SELECT * FROM drug ORDER BY drug_name")->fetchAll();

$stmt = $pdo->prepare("SELECT * FROM lab_request WHERE visit_id = ? ORDER BY request_id");
$stmt->execute([$id]);
$labs = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT pr.*, d.drug_name FROM prescription pr JOIN drug d ON d.drug_id = pr.drug_id
                       WHERE pr.visit_id = ? ORDER BY pr.prescription_id");
$stmt->execute([$id]);
$rxs = $stmt->fetchAll();

$money = visit_money($pdo, $id);

$title = 'Visit #' . $id;
include __DIR__ . '/includes/header.php';
?>
<h1>Visit #<?= e($id) ?>: <?= e($visit['full_name']) ?></h1>
<p>Service: <b><?= e($visit['service_name']) ?></b> | Date: <?= e(date('d M Y H:i', strtotime($visit['visit_date']))) ?>
   | <a href="patient_view.php?id=<?= e($visit['patient_id']) ?>">Patient record</a></p>

<h2>Consultation</h2>
<form method="post" class="box">
  <input type="hidden" name="action" value="save_notes">
  <label>Symptoms</label>
  <textarea name="symptoms" rows="2"><?= e($visit['symptoms']) ?></textarea>
  <label>Diagnosis</label>
  <textarea name="diagnosis" rows="2"><?= e($visit['diagnosis']) ?></textarea>
  <label>Next visit date (follow-up)</label>
  <input type="date" name="next_visit_date" value="<?= e($visit['next_visit_date']) ?>" style="width:200px">
  <p><button type="submit">Save notes</button></p>
</form>

<div class="grid2">
  <div>
    <h2>Lab / scan requests</h2>
    <form method="post" class="box inline">
      <input type="hidden" name="action" value="add_lab">
      <input type="text" name="test_name" placeholder="e.g. Malaria test, Abdominal scan" required style="width:70%">
      <button type="submit">Request</button>
    </form>
    <table>
      <tr><th>Test</th><th>Status</th><th>Result</th></tr>
      <?php foreach ($labs as $l): ?>
        <tr><td><?= e($l['test_name']) ?></td><td><?= e($l['status']) ?></td><td><?= e($l['result']) ?></td></tr>
      <?php endforeach; ?>
      <?php if (!$labs): ?><tr><td colspan="3">None.</td></tr><?php endif; ?>
    </table>
  </div>

  <div>
    <h2>Prescription</h2>
    <form method="post" class="box">
      <input type="hidden" name="action" value="add_rx">
      <label>Medicine</label>
      <select name="drug_id">
        <?php foreach ($drugs as $d): ?>
          <option value="<?= e($d['drug_id']) ?>"><?= e($d['drug_name']) ?> (stock <?= e($d['quantity_in_stock']) ?>)</option>
        <?php endforeach; ?>
      </select>
      <label>Dosage</label>
      <input type="text" name="dosage" placeholder="e.g. 1 tablet 3 times a day" required>
      <div class="grid2">
        <div><label>Route</label><select name="route"><option>Oral</option><option>Injection</option><option>Other</option></select></div>
        <div><label>Quantity</label><input type="number" name="quantity" value="1" min="1"></div>
      </div>
      <p><button type="submit">Prescribe</button></p>
    </form>
    <table>
      <tr><th>Medicine</th><th>Dosage</th><th>Route</th><th>Qty</th><th>Status</th></tr>
      <?php foreach ($rxs as $r): ?>
        <tr><td><?= e($r['drug_name']) ?></td><td><?= e($r['dosage']) ?></td><td><?= e($r['route']) ?></td><td><?= e($r['quantity']) ?></td><td><?= e($r['status']) ?></td></tr>
      <?php endforeach; ?>
      <?php if (!$rxs): ?><tr><td colspan="5">None.</td></tr><?php endif; ?>
    </table>
  </div>
</div>

<h2>Bill</h2>
<div class="box">
  Bill: <b><?= money($money['bill']) ?></b> | Paid: <b><?= money($money['paid']) ?></b> |
  Balance: <b class="<?= $money['balance'] > 0 ? 'warn' : '' ?>"><?= money($money['balance']) ?></b>
  <span class="small">(service fee plus medicines prescribed)</span>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>