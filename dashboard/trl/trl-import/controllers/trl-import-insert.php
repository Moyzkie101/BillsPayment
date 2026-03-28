<?php
include '../../../../config/config.php';
session_start();
include '../../../../templates/middleware.php';

$id = resolve_user_identifier();
if (empty($id)) {
    header('Location: ../../../../login_form.php');
    exit;
}

if (!function_exists('has_any_permission') || !has_any_permission(['TRL Import', 'Bills Payment'])) {
    header('Location: ../../../home.php');
    exit;
}

$rows = $_SESSION['trl_import_rows'] ?? [];
if (empty($rows)) {
    $_SESSION['trl_import_flash'] = [
        'type' => 'error',
        'message' => 'No TRL rows found in session. Please upload files again.'
    ];
    header('Location: ../trl-import-preview.php');
    exit;
}

$inserted = 0;
$failed = 0;

$sql = "INSERT INTO mldb.trl (
    transfer_datetime,
    ref_no,
    wrong_biller_id,
    biller_name,
    account_no,
    name,
    payment_branch_id,
    amount,
    type_of_request,
    correct_biller_id,
    correct_biller_name,
    reason
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    $_SESSION['trl_import_flash'] = [
        'type' => 'error',
        'message' => 'Failed to prepare insert statement: ' . $conn->error
    ];
    header('Location: ../trl-import-preview.php');
    exit;
}

$conn->autocommit(false);

try {
    foreach ($rows as $row) {
        $transferDatetime = !empty($row['transfer_datetime']) ? $row['transfer_datetime'] : null;
        $refNo = trim((string) ($row['ref_no'] ?? ''));
        $wrongBillerId = trim((string) ($row['wrong_biller_id'] ?? ''));
        $billerName = trim((string) ($row['biller_name'] ?? ''));
        $accountNo = trim((string) ($row['account_no'] ?? ''));
        $name = trim((string) ($row['name'] ?? ''));
        $paymentBranchId = trim((string) ($row['payment_branch_id'] ?? ''));
        $amount = (float) ($row['amount'] ?? 0);
        $typeOfRequest = trim((string) ($row['type_of_request'] ?? ''));
        $correctBillerId = trim((string) ($row['correct_biller_id'] ?? ''));
        $correctBillerName = trim((string) ($row['correct_biller_name'] ?? ''));
        $reason = trim((string) ($row['reason'] ?? ''));

        $stmt->bind_param(
            'sssssssdssss',
            $transferDatetime,
            $refNo,
            $wrongBillerId,
            $billerName,
            $accountNo,
            $name,
            $paymentBranchId,
            $amount,
            $typeOfRequest,
            $correctBillerId,
            $correctBillerName,
            $reason
        );

        if ($stmt->execute()) {
            $inserted++;
        } else {
            $failed++;
        }
    }

    if ($failed > 0) {
        $conn->rollback();
        $_SESSION['trl_import_flash'] = [
            'type' => 'error',
            'message' => "Insert rolled back. Inserted: {$inserted}, Failed: {$failed}."
        ];
    } else {
        $conn->commit();
        $_SESSION['trl_import_flash'] = [
            'type' => 'success',
            'message' => "TRL import complete. Inserted {$inserted} row(s) into mldb.trl."
        ];
        unset($_SESSION['trl_import_rows'], $_SESSION['trl_import_summary'], $_SESSION['trl_import_duplicate_result']);
    }
} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['trl_import_flash'] = [
        'type' => 'error',
        'message' => 'Insert failed: ' . $e->getMessage()
    ];
}

$stmt->close();
$conn->autocommit(true);

header('Location: ../trl-import-preview.php');
exit;
