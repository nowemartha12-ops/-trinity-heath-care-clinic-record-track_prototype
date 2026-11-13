<?php
// Small helper functions used on many pages

// Make text safe to show on a page
function e($text) {
    return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
}

function money($n) {
    return number_format((float)$n, 0) . ' UGX';
}

// Save who did what (activity log)
function log_action($pdo, $text) {
    $staff_id = $_SESSION['staff_id'] ?? null;
    $stmt = $pdo->prepare("INSERT INTO audit_log (staff_id, action) VALUES (?, ?)");
    $stmt->execute([$staff_id, $text]);
}

// Bill = service fee + cost of drugs prescribed. Balance = bill - payments made.
function visit_money($pdo, $visit_id) {
    $sql = "SELECT s.fee + COALESCE((SELECT SUM(pr.quantity * d.unit_price)
                                     FROM prescription pr
                                     JOIN drug d ON d.drug_id = pr.drug_id
                                     WHERE pr.visit_id = v.visit_id), 0) AS bill,
                   COALESCE((SELECT SUM(amount) FROM payment WHERE visit_id = v.visit_id), 0) AS paid
            FROM visit v
            JOIN service s ON s.service_id = v.service_id
            WHERE v.visit_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$visit_id]);
    $row = $stmt->fetch();
    if (!$row) {
        return ['bill' => 0, 'paid' => 0, 'balance' => 0];
    }
    $row['balance'] = $row['bill'] - $row['paid'];
    return $row;
}

// A simple bar for the dashboard and reports
function bar($value, $max) {
    $width = $max > 0 ? round($value / $max * 100) : 0;
    return '<div class="bar"><div style="width:' . $width . '%"></div></div>';
}

// Record every change in drug stock (purchase, dispensed, adjustment)
function log_stock($pdo, $drug_id, $type, $qty) {
    $staff_id = $_SESSION['staff_id'] ?? null;
    $stmt = $pdo->prepare("INSERT INTO stock_movement (drug_id, staff_id, movement_type, quantity) VALUES (?, ?, ?, ?)");
    $stmt->execute([$drug_id, $staff_id, $type, $qty]);
}

// Stock status of a drug: returns [label, colour]
function drug_status($d) {
    $today = date('Y-m-d');
    if (!empty($d['expiry_date']) && $d['expiry_date'] < $today) {
        return ['Expired', 'red'];
    }
    if ($d['quantity_in_stock'] <= 0) {
        return ['Out', 'red'];
    }
    if (!empty($d['expiry_date'])) {
        $days = (int)((strtotime($d['expiry_date']) - strtotime($today)) / 86400);
        if ($days <= 30) {
            return [$days . 'd', 'amber'];
        }
    }
    if ($d['quantity_in_stock'] <= $d['reorder_level']) {
        return ['Low', 'amber'];
    }
    return ['OK', 'green'];
}

function badge($label, $colour) {
    return '<span class="badge ' . e($colour) . '">' . e($label) . '</span>';
}