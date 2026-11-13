<?php
// Moves old patient records from Excel into the system.
// In Excel: File > Save As > CSV. Columns in this order: full_name, gender, date_of_birth, phone, address
require_once __DIR__ . '/includes/auth.php';
require_role(['clinician']);

$report = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv']) && $_FILES['csv']['error'] === UPLOAD_ERR_OK) {
    $file = fopen($_FILES['csv']['tmp_name'], 'r');
    $stmt = $pdo->prepare("INSERT INTO patient (full_name, gender, date_of_birth, phone, address) VALUES (?,?,?,?,?)");
    $added = 0;
    $skipped = 0;
    $first = true;
    while (($row = fgetcsv($file)) !== false) {
        if ($first) {            // skip the heading row
            $first = false;
            continue;
        }
        $name = trim($row[0] ?? '');
        if ($name === '') {
            $skipped++;
            continue;
        }
        $gender = (strtolower(trim($row[1] ?? '')) === 'male') ? 'Male' : 'Female';
        $dob = trim($row[2] ?? '');
        $dob = ($dob !== '' && strtotime($dob)) ? date('Y-m-d', strtotime($dob)) : null;
        $stmt->execute([$name, $gender, $dob, trim($row[3] ?? ''), trim($row[4] ?? '')]);
        $added++;
    }
    fclose($file);
    log_action($pdo, "Imported $added patients from CSV");
    $report = "$added patients added, $skipped rows skipped.";
}

$title = 'Import patients';
include __DIR__ . '/includes/header.php';
?>
<h1>Import patients from Excel</h1>
<?php if ($report): ?><div class="msg"><?= e($report) ?></div><?php endif; ?>
<form method="post" enctype="multipart/form-data" class="box">
  <p>Save your Excel sheet as <b>CSV</b>. The first row must be the headings. Columns in this order:<br>
     <code>full_name, gender, date_of_birth, phone, address</code></p>
  <input type="file" name="csv" accept=".csv" required>
  <p><button type="submit">Import</button> <a class="btn" href="patients.php">Back</a></p>
</form>
<?php include __DIR__ . '/includes/footer.php'; ?>