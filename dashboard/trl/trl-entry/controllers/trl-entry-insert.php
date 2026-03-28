<?php
include '../../../../config/config.php';
session_start();
include '../../../../templates/middleware.php';

header('Content-Type: application/json');

$id = resolve_user_identifier();
if (empty($id)) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

if (!function_exists('has_any_permission') || !has_any_permission(['TRL Entry', 'Bills Payment'])) {
    echo json_encode(['success' => false, 'message' => 'You do not have permission to perform this action.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

function trl_required($key) {
    return trim((string) ($_POST[$key] ?? ''));
}

$payload = [
    'transfer_datetime' => trl_required('transfer_datetime'),
    'ref_no' => trl_required('ref_no'),
    'wrong_biller_id' => trl_required('wrong_biller_id'),
    'biller_name' => trl_required('biller_name'),
    'account_no' => trl_required('account_no'),
    'name' => trl_required('name'),
    'payment_branch_id' => trl_required('payment_branch_id'),
    'payment_branch_name' => trl_required('payment_branch_name'),
    'payment_branch' => trl_required('payment_branch'),
    'amount' => trl_required('amount'),
    'type_of_request' => trl_required('type_of_request'),
    'correct_biller_id' => trl_required('correct_biller_id'),
    'correct_biller_name' => trl_required('correct_biller_name'),
    'reason' => trl_required('reason')
];

$requiredKeys = [
    'transfer_datetime', 'ref_no', 'wrong_biller_id', 'biller_name', 'account_no', 'name',
    'payment_branch_id', 'payment_branch_name', 'amount', 'type_of_request', 'reason'
];

// If the request type requires correction details, make those fields required
if (strcasecmp($payload['type_of_request'], 'WRONG BILLER') === 0) {
    $requiredKeys[] = 'correct_biller_id';
    $requiredKeys[] = 'correct_biller_name';
}

$missing = [];
foreach ($requiredKeys as $k) {
    if ($payload[$k] === '') {
        $missing[] = $k;
    }
}

if (!empty($missing)) {
    echo json_encode([
        'success' => false,
        'message' => 'Please complete all required fields before submitting.'
    ]);
    exit;
}

$amount = is_numeric($payload['amount']) ? (float) $payload['amount'] : (float) str_replace(',', '', $payload['amount']);

// Prefer payment_branch (requested target), fallback to payment_branch_name only if needed.
$branchColumn = 'payment_branch';
$colCheck = mysqli_query($conn, "SHOW COLUMNS FROM mldb.trl LIKE 'payment_branch'");
if (!$colCheck || mysqli_num_rows($colCheck) === 0) {
    $branchColumn = 'payment_branch_name';
}

$paymentBranchValue = $payload['payment_branch_name'] !== '' ? $payload['payment_branch_name'] : $payload['payment_branch'];

$sql = "INSERT INTO mldb.trl (
    transfer_datetime,
    ref_no,
    wrong_biller_id,
    biller_name,
    account_no,
    name,
    payment_branch_id,
    {$branchColumn},
    amount,
    type_of_request,
    reason
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode([
        'success' => false,
        'message' => 'Unable to prepare insert statement.'
    ]);
    exit;
}

$stmt->bind_param(
    'ssssssssdss',
    $payload['transfer_datetime'],
    $payload['ref_no'],
    $payload['wrong_biller_id'],
    $payload['biller_name'],
    $payload['account_no'],
    $payload['name'],
    $payload['payment_branch_id'],
    $paymentBranchValue,
    $amount,
    $payload['type_of_request'],
    $payload['reason']
);

$conn->autocommit(false);

try {
    if (!$stmt->execute()) {
        throw new Exception('Failed to insert TRL record.');
    }

    $trlNo = (int) $conn->insert_id;
    if ($trlNo <= 0) {
        throw new Exception('Invalid TRL number generated.');
    }

    if (strcasecmp($payload['type_of_request'], 'WRONG BILLER') === 0) {
        $wrongSql = "INSERT INTO mldb.trl_wrongbiller (trl_no, correct_biller_id, correct_biller_name) VALUES (?, ?, ?)";
        $wrongStmt = $conn->prepare($wrongSql);
        if (!$wrongStmt) {
            throw new Exception('Unable to prepare wrong biller insert statement.');
        }

        $wrongStmt->bind_param(
            'iss',
            $trlNo,
            $payload['correct_biller_id'],
            $payload['correct_biller_name']
        );

        if (!$wrongStmt->execute()) {
            $wrongStmt->close();
            throw new Exception('Failed to insert wrong biller correction details.');
        }

        $wrongStmt->close();
    }

    $conn->commit();
    $conn->autocommit(true);

    echo json_encode([
        'success' => true,
        'message' => 'Transaction Request Log has been submitted successfully!'
    ]);
    exit;
} catch (Exception $e) {
    $conn->rollback();
    $conn->autocommit(true);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    exit;
}
