<?php
// Return JSON list of all partners
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/config.php';

// ensure $conn exists
if (!isset($conn) || !$conn) {
    echo json_encode(['success' => false, 'error' => 'Database connection not available']);
    exit;
}

// Always get all partners, no fileType filtering
$sql = "SELECT partner_name FROM masterdata.partner_masterfile ORDER BY partner_name ASC";

$result = $conn->query($sql);
if ($result === false) {
    echo json_encode(['success' => false, 'error' => $conn->error]);
    exit;
}

$out = [];
while ($row = $result->fetch_assoc()) {
    $out[] = [
        'partner_name' => $row['partner_name']
    ];
}

echo json_encode(['success' => true, 'data' => $out]);
exit;
?>