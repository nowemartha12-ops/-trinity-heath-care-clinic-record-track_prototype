<?php
require_once __DIR__ . '/includes/auth.php';
require_role(['clinician']);

// Register a new patient
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['full_name'] ?? '');
    if ($name === '') {
        flash('Please enter the patient name.');
    } else {
        $stmt = $pdo->prepare("INSERT INTO patient (full_name, gender, date_of_birth, phone, address) VALUES (?,?,?,?,?)");
        $stmt->execute([
            $name,
            $_POST['gender'] === 'Male' ? 'Male' : 'Female',
            $_POST['date_of_birth'] ?: null,
            trim($_POST['phone'] ?? ''),
            trim($_POST['address'] ?? ''),
        ]);
        $newId = $pdo->lastInsertId();
        log_action($pdo, "Registered patient #$newId");
        flash("Patient registered. Patient number: $newId");
        header("Location: patient_view.php?id=$newId");
        exit;
    }
}

// Search
$q = trim($_GET['q'] ?? '');
if ($q !== '') {
    $stmt = $pdo->prepare("SELECT * FROM patient WHERE full_name LIKE ? OR phone LIKE ? OR patient_id = ? ORDER BY full_name LIMIT 50");
    $stmt->execute(["%$q%", "%$q%", (int)$q]);
    $patients = $stmt->fetchAll();
} else {
    $patients = $pdo->query("SELECT * FROM patient ORDER BY patient_id DESC LIMIT 20")->fetchAll();
}

$title = 'Patients';
include __DIR__ . '/includes/header.php';
?>
<h1>Patients</h1>

<form method="get" class="box inline">
  <input type="text" name="q" value="<?= e($q) ?>" placeholder="Search by name, phone or patient number" style="width:60%">
  <button type="submit">Search</button>
  <a class="btn" href="patients.php">Show latest</a>
</form>

<table>
  <tr><th>No.</th><th>Name</th><th>Gender</th><th>Phone</th><th>Address</th><th></th></tr>
  <?php foreach ($patients as $p): ?>
    <tr>
      <td><?= e($p['patient_id']) ?></td>
      <td><?= e($p['full_name']) ?></td>
      <td><?= e($p['gender']) ?></td>
      <td><?= e($p['phone']) ?></td>
      <td><?= e($p['address']) ?></td>
      <td><a href="patient_view.php?id=<?= e($p['patient_id']) ?>">Open record</a></td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$patients): ?><tr><td colspan="6">No patient found.</td></tr><?php endif; ?>
</table>

<h2>Register a new patient</h2>
<form method="post" class="box">
  <div class="grid2">
    <div><label>Full name</label><input type="text" name="full_name" required></div>
    <div><label>Gender</label><select name="gender"><option>Female</option><option>Male</option></select></div>
    <div><label>Date of birth</label><input type="date" name="date_of_birth"></div>
    <div><label>Phone</label><input type="text" name="phone"></div>
  </div>
  <label>Address</label>
  <input type="text" name="address">
  <p><button type="submit">Register patient</button>
     <a class="btn" href="import_patients.php">Import from Excel (CSV)</a></p>
</form>
<?php include __DIR__ . '/includes/footer.php'; ?>