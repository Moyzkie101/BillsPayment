<?php
// Connect to the database
include '../../../config/config.php';
require '../../../vendor/autoload.php';

// Start the session
session_start();

$settlement_view = $_POST['settlement_view'] ?? 'filter';
$partner_options = [];


if (isset($_SESSION['user_type'])) {
    $current_user_email = '';
    if ($_SESSION['user_type'] === 'admin' && isset($_SESSION['admin_email'])) {
        $current_user_email = $_SESSION['admin_email'];
    } elseif ($_SESSION['user_type'] === 'user' && isset($_SESSION['user_email'])) {
        $current_user_email = $_SESSION['user_email'];
    }
}

try {
    $partnersQuery = "SELECT DISTINCT partner_name FROM masterdata.partner_masterfile WHERE status = 'ACTIVE' AND partner_name IS NOT NULL AND partner_name <> '' ORDER BY partner_name";
    $partnersResult = $conn->query($partnersQuery);

    if ($partnersResult && $partnersResult->num_rows > 0) {
        while ($row = $partnersResult->fetch_assoc()) {
            $partner_options[] = $row['partner_name'];
        }
    }
} catch (Exception $e) {
    error_log('Failed to load partner options in adjustment-transaction.php: ' . $e->getMessage());
}

// get display dropdown menu for partners
if (isset($_POST['action']) && $_POST['action'] === 'get_partner_list') {
    header('Content-Type: application/json');
    try {
        $partnersQuery = "SELECT DISTINCT partner_name FROM masterdata.partner_masterfile WHERE status = 'ACTIVE' AND partner_name IS NOT NULL AND partner_name <> '' ORDER BY partner_name";
        $partnersResult = $conn->query($partnersQuery);
        
        $partners = array();
        if ($partnersResult && $partnersResult->num_rows > 0) {
            while ($row = $partnersResult->fetch_assoc()) {
                $partners[] = $row;
            }
        }
        
        echo json_encode([
            'status' => 'success',
            'data' => $partners
        ]);
        exit();
    } catch (Exception $e) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Database error: ' . $e->getMessage()
        ]);
        exit();
    }
}

if (isset($_POST['action']) && $_POST['action'] === 'generate_report') {
    header('Content-Type: application/json');

    $settlementView = $_POST['settlementView'] ?? 'filter';
    $partner = $_POST['partner'] ?? '';
    $filterType = $_POST['filterType'] ?? '';
    $startDate = $_POST['startDate'] ?? '';
    $endDate = $_POST['endDate'] ?? '';
    $referenceNumber = trim($_POST['referenceNumber'] ?? '');

    $params = [];
    $types = '';
    $dateCondition = '';

    if ($settlementView === 'reference') {
        if ($referenceNumber === '') {
            echo json_encode([
                'status' => 'error',
                'message' => 'Reference Number is required.',
                'data' => []
            ]);
            exit();
        }

        $query = "SELECT
                                bt.reference_no AS reference_number,
                                bt.datetime AS transaction_datetime,
                                bt.payor AS payor_name,
                                bt.address AS payor_address,
                                bt.account_no AS account_number,
                                bt.account_name,
                                bt.amount_paid AS principal,
                                bt.charge_to_customer,
                                bt.charge_to_partner,
                                bt.contact_no AS contact_number,
                                bt.other_details,
                                bt.outlet AS branch_outlet,
                                bt.operator AS branch_operator
                  FROM mldb.billspayment_transaction AS bt
                  WHERE (bt.status IS NULL OR bt.status <> '*')
                    AND (bt.post_transaction IS NULL OR bt.post_transaction <> 'posted')
                    AND bt.settle_unsettle IS NULL
                    AND bt.reference_no LIKE ?
                  ORDER BY bt.datetime ASC, bt.reference_no ASC";

        $likeReference = '%' . $referenceNumber . '%';

        try {
            $stmt = $conn->prepare($query);
            if (!$stmt) {
                throw new Exception('Prepare failed: ' . $conn->error);
            }

            $stmt->bind_param('s', $likeReference);
            $stmt->execute();
            $result = $stmt->get_result();
            $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
            $stmt->close();

            echo json_encode([
                'status' => 'success',
                'message' => 'Report generated successfully.',
                'totalRows' => count($rows),
                'data' => $rows
            ]);
        } catch (Exception $e) {
            error_log('Settle transaction generate_report(reference) error: ' . $e->getMessage());
            echo json_encode([
                'status' => 'error',
                'message' => 'Database error: ' . $e->getMessage(),
                'data' => []
            ]);
        }

        exit();
    }

    if ($filterType === '' || $startDate === '') {
        echo json_encode([
            'status' => 'error',
            'message' => 'Missing required filters.',
            'data' => []
        ]);
        exit();
    }

    $resolvedEndDate = $endDate;
    if ($filterType === 'daily') {
        $resolvedEndDate = $startDate;
    } elseif ($filterType === 'date-range') {
        $resolvedEndDate = $endDate !== '' ? $endDate : $startDate;
    } elseif ($filterType === 'monthly') {
        $startDate = $startDate . '-01';
        $resolvedEndDate = date('Y-m-t', strtotime($startDate));
    }

    if ($filterType === 'daily') {
        $dateCondition = 'DATE(bt.datetime) = ?';
        $params[] = $startDate;
        $types .= 's';
    } else {
        $dateCondition = 'DATE(bt.datetime) BETWEEN ? AND ?';
        $params[] = $startDate;
        $params[] = $resolvedEndDate;
        $types .= 'ss';
    }

    $partnerIds = [];
    if ($partner !== '' && $partner !== 'All') {
        $partnerConvertSQL = "SELECT DISTINCT partner_id, partner_id_kpx FROM masterdata.partner_masterfile WHERE partner_name = ? AND status = 'ACTIVE'";
        $partnerStmt = $conn->prepare($partnerConvertSQL);

        if ($partnerStmt) {
            $partnerStmt->bind_param('s', $partner);
            $partnerStmt->execute();
            $partnerResult = $partnerStmt->get_result();

            while ($row = $partnerResult->fetch_assoc()) {
                if (!empty($row['partner_id'])) {
                    $partnerIds[] = trim((string)$row['partner_id']);
                }
                if (!empty($row['partner_id_kpx'])) {
                    $partnerIds[] = trim((string)$row['partner_id_kpx']);
                }
            }

            $partnerStmt->close();
        }

        $partnerIds = array_values(array_unique(array_filter($partnerIds)));
    }

        $query = "SELECT
                                bt.reference_no AS reference_number,
                                bt.datetime AS transaction_datetime,
                                bt.payor AS payor_name,
                                bt.address AS payor_address,
                                bt.account_no AS account_number,
                                bt.account_name,
                                bt.amount_paid AS principal,
                                bt.charge_to_customer,
                                bt.charge_to_partner,
                                bt.contact_no AS contact_number,
                                bt.other_details,
                                bt.outlet AS branch_outlet,
                                bt.operator AS branch_operator
              FROM mldb.billspayment_transaction AS bt
              WHERE $dateCondition
                                AND (bt.status IS NULL OR bt.status <> '*')
                AND (bt.post_transaction IS NULL OR bt.post_transaction <> 'posted')
                AND bt.settle_unsettle IS NULL";

    if ($partner !== '' && $partner !== 'All') {
        if (!empty($partnerIds)) {
            $partnerIdPlaceholders = implode(',', array_fill(0, count($partnerIds), '?'));
            $query .= " AND (bt.partner_id IN ($partnerIdPlaceholders) OR bt.partner_id_kpx IN ($partnerIdPlaceholders))";
            $params = array_merge($params, $partnerIds, $partnerIds);
            $types .= str_repeat('s', count($partnerIds) * 2);
        } else {
            $query .= " AND bt.partner_name = ?";
            $params[] = $partner;
            $types .= 's';
        }
    }

    $query .= ' ORDER BY bt.datetime ASC, bt.reference_no ASC';

    try {
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception('Prepare failed: ' . $conn->error);
        }

        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();
        $result = $stmt->get_result();
        $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();

        echo json_encode([
            'status' => 'success',
            'message' => 'Report generated successfully.',
            'totalRows' => count($rows),
            'data' => $rows
        ]);
    } catch (Exception $e) {
        error_log('Settle transaction generate_report error: ' . $e->getMessage());
        echo json_encode([
            'status' => 'error',
            'message' => 'Database error: ' . $e->getMessage(),
            'data' => []
        ]);
    }

    exit();
}

if (isset($_POST['action']) && $_POST['action'] === 'submit_changes') {
    header('Content-Type: application/json');

    $rawChanges = $_POST['changes'] ?? '[]';
    $decodedChanges = json_decode($rawChanges, true);

    if (!is_array($decodedChanges)) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid submitted changes payload.',
            'data' => [],
            'rowColorClass' => 'bg-danger-subtle'
        ]);
        exit();
    }

    $normalizedChanges = [];
    foreach ($decodedChanges as $item) {
        if (!is_array($item)) {
            continue;
        }

        $referenceKey = trim((string)($item['reference_key'] ?? ''));
        $referenceNumber = trim((string)($item['reference_number'] ?? ''));
        $transactionDatetime = trim((string)($item['transaction_datetime'] ?? ''));
        $reasonNote = trim((string)($item['reason_note'] ?? ''));

        if ($referenceKey === '' || $referenceNumber === '') {
            continue;
        }

        $normalizedChanges[] = [
            'reference_key' => $referenceKey,
            'reference_number' => $referenceNumber,
            'transaction_datetime' => $transactionDatetime,
            'reason_note' => $reasonNote,
            'payor' => isset($item['payor']) ? trim((string)$item['payor']) : '',
            'address' => isset($item['address']) ? trim((string)$item['address']) : '',
            'account_no' => isset($item['account_no']) ? trim((string)$item['account_no']) : '',
            'account_name' => isset($item['account_name']) ? trim((string)$item['account_name']) : '',
            'principal' => isset($item['principal']) ? trim((string)$item['principal']) : '',
            'charge_to_customer' => isset($item['charge_to_customer']) ? trim((string)$item['charge_to_customer']) : '',
            'charge_to_partner' => isset($item['charge_to_partner']) ? trim((string)$item['charge_to_partner']) : '',
            'contact_no' => isset($item['contact_no']) ? trim((string)$item['contact_no']) : '',
            'other_details' => isset($item['other_details']) ? trim((string)$item['other_details']) : '',
            'outlet' => isset($item['outlet']) ? trim((string)$item['outlet']) : '',
            'operator' => isset($item['operator']) ? trim((string)$item['operator']) : ''
        ];
    }

    echo json_encode([
        'status' => 'success',
        'message' => 'Changes captured successfully.',
        'data' => $normalizedChanges,
        'rowColorClass' => 'bg-danger-subtle'
    ]);
    exit();
}

if (isset($_POST['action']) && $_POST['action'] === 'save_changes') {
    header('Content-Type: application/json');

    $rawChanges = $_POST['changes'] ?? '[]';
    $decodedChanges = json_decode($rawChanges, true);

    if (!is_array($decodedChanges) || count($decodedChanges) === 0) {
        echo json_encode([
            'status' => 'error',
            'message' => 'No submitted changes to save.',
            'insertedRows' => 0
        ]);
        exit();
    }

    $modifiedBy = trim((string)($current_user_email ?? ''));
    if ($modifiedBy === '') {
        $modifiedBy = 'system';
    }

    $normalizeAmount = function ($value) {
        return str_replace(',', '', trim((string)$value));
    };

    $insertSql = "INSERT INTO mldb.settle_adjustment_branch_transaction (
                    datetime, reference_no,
                    prev_payor, edited_payor,
                    prev_address, edited_address,
                    prev_account_no, edited_account_no,
                    prev_account_name, edited_account_name,
                    prev_amount_paid, edited_amount_paid,
                    prev_charge_to_customer, edited_charge_to_customer,
                    prev_charge_to_partner, edited_charge_to_partner,
                    prev_contact_no, edited_contact_no,
                    prev_other_details, edited_other_details,
                    prev_outlet, edited_outlet,
                    prev_operator, edited_operator,
                    partner_name, partner_id, partner_id_kpx,
                    reason_note, modified_by, modified_date
                )
                SELECT
                    bt.datetime, bt.reference_no,
                    bt.payor, ?,
                    bt.address, ?,
                    bt.account_no, ?,
                    bt.account_name, ?,
                    bt.amount_paid, ?,
                    bt.charge_to_customer, ?,
                    bt.charge_to_partner, ?,
                    bt.contact_no, ?,
                    bt.other_details, ?,
                    bt.outlet, ?,
                    bt.operator, ?,
                    bt.partner_name, bt.partner_id, bt.partner_id_kpx,
                    ?, ?, NOW()
                FROM mldb.billspayment_transaction bt
                WHERE bt.reference_no = ?
                  AND bt.datetime = ?
                  AND NOT EXISTS (
                    SELECT 1
                    FROM mldb.settle_adjustment_branch_transaction st
                    WHERE st.reference_no = bt.reference_no
                      AND st.datetime = bt.datetime
                  )
                LIMIT 1";

        $insertLatePostingSql = "INSERT INTO mldb.settle_adjustment_branch_transaction (
                                        datetime, reference_no,
                                        prev_payor,
                                        prev_address,
                                        prev_account_no,
                                        prev_account_name,
                                        prev_amount_paid,
                                        prev_charge_to_customer,
                                        prev_charge_to_partner,
                                        prev_contact_no,
                                        prev_other_details,
                                        prev_outlet,
                                        prev_operator,
                                        partner_name, partner_id, partner_id_kpx,
                                        reason_note, modified_by, modified_date
                                )
                                SELECT
                                        bt.datetime, bt.reference_no,
                                        bt.payor,
                                        bt.address,
                                        bt.account_no,
                                        bt.account_name,
                                        bt.amount_paid,
                                        bt.charge_to_customer,
                                        bt.charge_to_partner,
                                        bt.contact_no,
                                        bt.other_details,
                                        bt.outlet,
                                        bt.operator,
                                        bt.partner_name, bt.partner_id, bt.partner_id_kpx,
                                        ?, ?, NOW()
                                FROM mldb.billspayment_transaction bt
                                WHERE bt.reference_no = ?
                                    AND bt.datetime = ?
                                    AND NOT EXISTS (
                                        SELECT 1
                                        FROM mldb.settle_adjustment_branch_transaction st
                                        WHERE st.reference_no = bt.reference_no
                                            AND st.datetime = bt.datetime
                                    )
                                LIMIT 1";

        $insertWrongAmountSql = "INSERT INTO mldb.settle_adjustment_branch_transaction (
                                        datetime, reference_no,
                                        prev_payor,
                                        prev_address,
                                        prev_account_no,
                                        prev_account_name,
                                        prev_amount_paid, edited_amount_paid,
                                        prev_charge_to_customer, edited_charge_to_customer,
                                        prev_charge_to_partner, edited_charge_to_partner,
                                        prev_contact_no,
                                        prev_other_details,
                                        prev_outlet,
                                        prev_operator,
                                        partner_name, partner_id, partner_id_kpx,
                                        reason_note, modified_by, modified_date
                                )
                                SELECT
                                        bt.datetime, bt.reference_no,
                                        bt.payor,
                                        bt.address,
                                        bt.account_no,
                                        bt.account_name,
                                        bt.amount_paid, ?,
                                        bt.charge_to_customer, ?,
                                        bt.charge_to_partner, ?,
                                        bt.contact_no,
                                        bt.other_details,
                                        bt.outlet,
                                        bt.operator,
                                        bt.partner_name, bt.partner_id, bt.partner_id_kpx,
                                        ?, ?, NOW()
                                FROM mldb.billspayment_transaction bt
                                WHERE bt.reference_no = ?
                                    AND bt.datetime = ?
                                    AND NOT EXISTS (
                                        SELECT 1
                                        FROM mldb.settle_adjustment_branch_transaction st
                                        WHERE st.reference_no = bt.reference_no
                                            AND st.datetime = bt.datetime
                                    )
                                LIMIT 1";

        $latePostingPartnerLookupSql = "SELECT bt.partner_id, bt.partner_id_kpx
                                                                        FROM mldb.billspayment_transaction bt
                                                                        WHERE bt.reference_no = ?
                                                                            AND bt.datetime = ?
                                                                        LIMIT 1";

        $updateLatePostingSql = "UPDATE mldb.billspayment_transaction
                                                                SET settle_unsettle = 'settle'
                                                            WHERE post_transaction = 'unposted'
                                                                AND datetime = ?
                                                                AND reference_no = ?
                                                                AND (partner_id = ? OR partner_id_kpx = ?)
                                                            LIMIT 1";

        $updateWrongAmountSql = "UPDATE mldb.billspayment_transaction
                                                                SET settle_unsettle = 'settle',
                                                                        amount_paid = ?,
                                                                        charge_to_customer = ?,
                                                                        charge_to_partner = ?
                                                            WHERE post_transaction = 'unposted'
                                                                AND datetime = ?
                                                                AND reference_no = ?
                                                                AND (partner_id = ? OR partner_id_kpx = ?)
                                                            LIMIT 1";

        $updateSql = "UPDATE mldb.billspayment_transaction
                                    SET
                                        payor = ?,
                                        address = ?,
                                        account_no = ?,
                                        account_name = ?,
                                        amount_paid = ?,
                                        charge_to_customer = ?,
                                        charge_to_partner = ?,
                                        contact_no = ?,
                                        other_details = ?,
                                        outlet = ?,
                                        operator = ?,
                                        settle_unsettle = 'settle'
                                    WHERE reference_no = ?
                                        AND datetime = ?
                                    LIMIT 1";

    try {
                $stmt = $conn->prepare($insertSql);
                if (!$stmt) {
            throw new Exception('Prepare failed: ' . $conn->error);
        }

                $updateStmt = $conn->prepare($updateSql);
                if (!$updateStmt) {
                        throw new Exception('Prepare failed (update): ' . $conn->error);
                }

                $lateInsertStmt = $conn->prepare($insertLatePostingSql);
                if (!$lateInsertStmt) {
                    throw new Exception('Prepare failed (late insert): ' . $conn->error);
                }

                $wrongAmountInsertStmt = $conn->prepare($insertWrongAmountSql);
                if (!$wrongAmountInsertStmt) {
                    throw new Exception('Prepare failed (wrong amount insert): ' . $conn->error);
                }

                $latePartnerLookupStmt = $conn->prepare($latePostingPartnerLookupSql);
                if (!$latePartnerLookupStmt) {
                    throw new Exception('Prepare failed (late partner lookup): ' . $conn->error);
                }

                $lateUpdateStmt = $conn->prepare($updateLatePostingSql);
                if (!$lateUpdateStmt) {
                    throw new Exception('Prepare failed (late update): ' . $conn->error);
                }

                $wrongAmountUpdateStmt = $conn->prepare($updateWrongAmountSql);
                if (!$wrongAmountUpdateStmt) {
                    throw new Exception('Prepare failed (wrong amount update): ' . $conn->error);
                }

        $insertedRows = 0;
        $skippedRows = 0;
                $updatedRows = 0;

        foreach ($decodedChanges as $item) {
            if (!is_array($item)) {
                continue;
            }

            $referenceNumber = trim((string)($item['reference_number'] ?? ''));
            $transactionDatetime = trim((string)($item['transaction_datetime'] ?? ''));
            if ($referenceNumber === '' || $transactionDatetime === '') {
                continue;
            }

            $editedPayor = trim((string)($item['payor'] ?? ''));
            $editedAddress = trim((string)($item['address'] ?? ''));
            $editedAccountNo = trim((string)($item['account_no'] ?? ''));
            $editedAccountName = trim((string)($item['account_name'] ?? ''));
            $editedAmountPaid = $normalizeAmount($item['principal'] ?? '');
            $editedChargeCustomer = $normalizeAmount($item['charge_to_customer'] ?? '');
            $editedChargePartner = $normalizeAmount($item['charge_to_partner'] ?? '');
            $editedContactNo = trim((string)($item['contact_no'] ?? ''));
            $editedOtherDetails = trim((string)($item['other_details'] ?? ''));
            $editedOutlet = trim((string)($item['outlet'] ?? ''));
            $editedOperator = trim((string)($item['operator'] ?? ''));
            $reasonNote = trim((string)($item['reason_note'] ?? ''));

            if ($reasonNote === 'late-posting') {
                $lateInsertStmt->bind_param(
                    str_repeat('s', 4),
                    $reasonNote,
                    $modifiedBy,
                    $referenceNumber,
                    $transactionDatetime
                );

                $lateInsertStmt->execute();
                if ($lateInsertStmt->affected_rows > 0) {
                    $insertedRows++;
                } else {
                    $skippedRows++;
                }

                $partnerId = '';
                $partnerIdKpx = '';

                $latePartnerLookupStmt->bind_param(
                    str_repeat('s', 2),
                    $referenceNumber,
                    $transactionDatetime
                );
                $latePartnerLookupStmt->execute();
                $latePartnerLookupResult = $latePartnerLookupStmt->get_result();
                if ($latePartnerLookupResult && $latePartnerLookupResult->num_rows > 0) {
                    $latePartnerRow = $latePartnerLookupResult->fetch_assoc();
                    $partnerId = trim((string)($latePartnerRow['partner_id'] ?? ''));
                    $partnerIdKpx = trim((string)($latePartnerRow['partner_id_kpx'] ?? ''));
                }

                if ($partnerId !== '' || $partnerIdKpx !== '') {
                    $lateUpdateStmt->bind_param(
                        str_repeat('s', 4),
                        $transactionDatetime,
                        $referenceNumber,
                        $partnerId,
                        $partnerIdKpx
                    );
                    $lateUpdateStmt->execute();
                    if ($lateUpdateStmt->affected_rows > 0) {
                        $updatedRows++;
                    }
                }

                continue;
            }

            if ($reasonNote === 'wrong-amount') {
                $wrongAmountInsertStmt->bind_param(
                    str_repeat('s', 7),
                    $editedAmountPaid,
                    $editedChargeCustomer,
                    $editedChargePartner,
                    $reasonNote,
                    $modifiedBy,
                    $referenceNumber,
                    $transactionDatetime
                );

                $wrongAmountInsertStmt->execute();
                if ($wrongAmountInsertStmt->affected_rows > 0) {
                    $insertedRows++;
                } else {
                    $skippedRows++;
                }

                $partnerId = '';
                $partnerIdKpx = '';

                $latePartnerLookupStmt->bind_param(
                    str_repeat('s', 2),
                    $referenceNumber,
                    $transactionDatetime
                );
                $latePartnerLookupStmt->execute();
                $latePartnerLookupResult = $latePartnerLookupStmt->get_result();
                if ($latePartnerLookupResult && $latePartnerLookupResult->num_rows > 0) {
                    $latePartnerRow = $latePartnerLookupResult->fetch_assoc();
                    $partnerId = trim((string)($latePartnerRow['partner_id'] ?? ''));
                    $partnerIdKpx = trim((string)($latePartnerRow['partner_id_kpx'] ?? ''));
                }

                if ($partnerId !== '' || $partnerIdKpx !== '') {
                    $wrongAmountUpdateStmt->bind_param(
                        str_repeat('s', 7),
                        $editedAmountPaid,
                        $editedChargeCustomer,
                        $editedChargePartner,
                        $transactionDatetime,
                        $referenceNumber,
                        $partnerId,
                        $partnerIdKpx
                    );
                    $wrongAmountUpdateStmt->execute();
                    if ($wrongAmountUpdateStmt->affected_rows > 0) {
                        $updatedRows++;
                    }
                }

                continue;
            }

            $stmt->bind_param(
                str_repeat('s', 15),
                $editedPayor,
                $editedAddress,
                $editedAccountNo,
                $editedAccountName,
                $editedAmountPaid,
                $editedChargeCustomer,
                $editedChargePartner,
                $editedContactNo,
                $editedOtherDetails,
                $editedOutlet,
                $editedOperator,
                $reasonNote,
                $modifiedBy,
                $referenceNumber,
                $transactionDatetime
            );

            $stmt->execute();
            if ($stmt->affected_rows > 0) {
                $insertedRows++;
            } else {
                $skippedRows++;
            }

            $updateStmt->bind_param(
                str_repeat('s', 13),
                $editedPayor,
                $editedAddress,
                $editedAccountNo,
                $editedAccountName,
                $editedAmountPaid,
                $editedChargeCustomer,
                $editedChargePartner,
                $editedContactNo,
                $editedOtherDetails,
                $editedOutlet,
                $editedOperator,
                $referenceNumber,
                $transactionDatetime
            );
            $updateStmt->execute();
            if ($updateStmt->affected_rows > 0) {
                $updatedRows++;
            }
        }

        $stmt->close();
        $updateStmt->close();
        $lateInsertStmt->close();
        $wrongAmountInsertStmt->close();
        $latePartnerLookupStmt->close();
        $lateUpdateStmt->close();
        $wrongAmountUpdateStmt->close();

        echo json_encode([
            'status' => 'success',
            'message' => 'Inserted Successfully',
            'insertedRows' => $insertedRows,
            'skippedRows' => $skippedRows,
            'updatedRows' => $updatedRows
        ]);
    } catch (Exception $e) {
        error_log('Settle transaction save_changes error: ' . $e->getMessage());
        echo json_encode([
            'status' => 'error',
            'message' => 'Database error: ' . $e->getMessage(),
            'insertedRows' => 0
        ]);
    }

    exit();
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settle Transaction | <?php if($_SESSION['user_type'] === 'admin' || $_SESSION['user_type'] === 'user') echo ucfirst($_SESSION['user_type']); else echo "Guest";?></title>
    <!-- custom CSS file link  -->
    <link rel="stylesheet" href="../../../assets/css/templates/style.css?v=<?php echo time(); ?>">
    <script src="https://kit.fontawesome.com/30b908cc5a.js" crossorigin="anonymous"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="../../../assets/js/sweetalert2.all.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>

    <link rel="icon" href="../../../images/MLW logo.png" type="image/png">

    <style>
        .settle-filter-card {
            background: #ffffff;
            border: 1px solid #e9ecef;
            border-radius: 14px;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.08);
            padding: 1rem 1.25rem;
            margin: 1rem 0 0;
        }

        .settle-report-card {
            border: 1px solid #e9ecef;
            border-radius: 14px;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.08);
            margin-top: 1rem;
            overflow: hidden;
        }

        .settle-report-head {
            background: #ffffff;
            padding: 1rem 1.25rem;
            border-bottom: 1px solid #e9ecef;
        }

        .settle-report-body {
            background: #ffffff;
            padding: 1rem 1.25rem;
        }

        .settle-filter-title {
            font-size: 0.9rem;
            font-weight: 600;
            color: #495057;
            margin-bottom: 0.75rem;
        }

        .settle-filter-actions {
            margin-top: 1rem;
        }

        .compact-field .form-label {
            font-size: 0.82rem;
            margin-bottom: 0.3rem;
        }

        .compact-field .form-select,
        .compact-field .form-control {
            height: 38px;
            padding-top: 0.35rem;
            padding-bottom: 0.35rem;
            font-size: 0.9rem;
        }

        .compact-apply-btn {
            height: 38px;
            padding-top: 0.35rem;
            padding-bottom: 0.35rem;
            font-size: 0.9rem;
            white-space: nowrap;
        }

        .table-checkbox-cell {
            width: 44px;
            min-width: 44px;
            text-align: center;
        }

        .table-search-wrap {
            max-width: 320px;
            margin-left: auto;
        }

        #tableContainer {
            max-height: 745px;
            overflow-y: auto;
            overflow-x: auto;
            border: 1px solid #e9ecef;
            border-radius: 8px;
        }

        #transactionReportTable {
            margin-bottom: 0;
        }

        #transactionReportTable thead th {
            position: sticky;
            top: 0;
            z-index: 5;
            background-color: #f8f9fa;
        }

        #transactionReportTable tbody tr.bg-danger-subtle > td,
        #transactionReportTable tbody tr.bs-danger-bg-subtle > td {
            background-color: var(--bs-danger-bg-subtle) !important;
        }

        #tableContainer::-webkit-scrollbar {
            width: 10px;
            height: 10px;
        }

        #tableContainer::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }

        #tableContainer::-webkit-scrollbar-thumb {
            background: #c7c7c7;
            border-radius: 10px;
        }

        #tableContainer::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }

        .day-shortcut-container {
            padding: 10px 8px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            background-color: #f8f9fa;
            border: 1px solid #e0e0e0;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        }

        .day-buttons-label {
            font-weight: 600;
            margin-right: 12px;
            color: #666;
            white-space: nowrap;
            padding-left: 6px;
            font-size: 0.85rem;
        }

        .day-buttons-wrapper {
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
            max-width: 100%;
            overflow-x: auto;
            padding: 4px;
            align-items: center;
        }

        .day-button {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            border: 1px solid #dc3545;
            background-color: #ffffff;
            color: #dc3545;
            font-weight: 600;
            font-size: 0.8rem;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        }

        .day-button:hover {
            background-color: rgba(220,53,69,0.85);
            color: #ffffff;
            transform: scale(1.05);
            box-shadow: 0 2px 4px rgba(220,53,69,0.3);
            z-index: 2;
        }

        .day-button-active {
            background-color: #dc3545;
            color: #ffffff;
            transform: scale(1.08);
            box-shadow: 0 2px 5px rgba(220,53,69,0.4);
            position: relative;
            z-index: 3;
        }

        .day-button-active::after {
            content: "";
            position: absolute;
            bottom: -3px;
            left: 50%;
            transform: translateX(-50%);
            width: 6px;
            height: 6px;
            background-color: #ff8a04;
            border-radius: 50%;
        }

        .day-button.day-button-all {
            width: auto;
            min-width: 58px;
            padding: 0 14px;
            border-radius: 20px;
            background-color: #6c757d;
            color: #ffffff;
            border-color: #6c757d;
        }

        .day-button.day-button-all:hover {
            background-color: #5a6268;
            border-color: #545b62;
            color: #ffffff;
        }

        .day-button.day-number-button {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            font-size: 0.78rem;
        }

        .month-separator {
            width: 100%;
            text-align: center;
            font-size: 10px;
            color: #666;
            margin: 6px 0 3px;
            font-weight: 700;
            border-bottom: 1px solid #ddd;
            padding-bottom: 2px;
        }

        .settle-mode-group {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .settle-mode-option {
            flex: 0 1 auto;
            margin: 0;
            min-width: 190px;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            background: #fff;
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 10px;
            cursor: pointer;
            transition: all 120ms ease;
            box-shadow: 0 1px 2px rgba(0,0,0,0.03);
        }

        .settle-mode-option:hover {
            background: #ffffff;
            border-color: #f1c1c1;
        }

        .settle-mode-icon {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: #f8f9fa;
            color: #6c757d;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            flex-shrink: 0;
        }

        .settle-mode-text {
            display: flex;
            flex-direction: column;
            line-height: 1.15;
        }

        .settle-mode-main {
            font-size: 13px;
            font-weight: 700;
            color: #212529;
            margin: 0;
        }

        .settle-mode-sub {
            font-size: 0.85rem;
            color: #6c757d;
            margin: 0.1rem 0 0;
        }

        .btn-check:checked + .settle-mode-option {
            border: 1px solid #dc3545;
            background: #ffffff;
            box-shadow: 0 8px 24px rgba(220,53,69,0.06);
        }

        .btn-check:checked + .settle-mode-option .settle-mode-main,
        .btn-check:checked + .settle-mode-option .settle-mode-sub {
            color: #212529;
        }

        .btn-check:checked + .settle-mode-option .settle-mode-icon {
            color: #dc3545;
            background: #fff5f5;
        }

        .btn-check:focus + .settle-mode-option {
            box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.2);
        }

        @media (max-width: 991.98px) {
            .settle-filter-actions {
                margin-top: 0;
            }
        }

        @media (max-width: 576px) {
            .settle-mode-option {
                min-width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="main-container">
        <?php include '../../../templates/header_ui.php'; ?>
        <!-- Show and Hide Side Nav Menu -->
        <?php include '../../../templates/sidebar.php'; ?>
        <div id="loading-overlay">
            <div class="loading-spinner"></div>
        </div>
        <div class="bp-section-header" role="region" aria-label="Page title">
            <div class="bp-section-title">
                <i class="fa-solid fa-layer-group" aria-hidden="true"></i>
                <div>
                    <h2>Adjustment Entry</h2>
                    <!-- <p class="bp-section-sub">Sample Description</p> -->
                </div>
            </div>
        </div>

        <div class="container-fluid px-0">
            <div class="card settle-report-card">
                <div class="card-header settle-report-head">
                    <div class="col-12">
                        <p class="settle-filter-title mb-2">Search By:</p>
                        <div class="mb-3 d-flex align-items-center justify-content-between" style="gap:12px;">
                            <div class="settle-mode-group" role="group" aria-label="Settlement view options">
                                <input type="radio" class="btn-check" name="settlement_view" id="settlement-filter" value="filter" autocomplete="off" <?php echo $settlement_view === 'filter' ? 'checked' : ''; ?>>
                                <label class="settle-mode-option" for="settlement-filter">
                                    <!-- <span class="settle-mode-icon"><i class="fas fa-upload"></i></span> -->
                                    <span class="settle-mode-text">
                                        <span class="settle-mode-main">Partner &amp; Transaction Date</span>
                                    </span>
                                </label>

                                <input type="radio" class="btn-check" name="settlement_view" id="settlement-reference" value="reference" autocomplete="off" <?php echo $settlement_view === 'reference' ? 'checked' : ''; ?>>
                                <label class="settle-mode-option" for="settlement-reference">
                                    <!-- <span class="settle-mode-icon"><i class="fas fa-file-alt"></i></span> -->
                                    <span class="settle-mode-text">
                                        <span class="settle-mode-main">Reference Number</span>
                                    </span>
                                </label>
                                <input type="radio" class="btn-check" name="settlement_view" id="settlement-unposting" value="unposting" autocomplete="off" <?php echo $settlement_view === 'unposting' ? 'checked' : ''; ?>>
                                <label class="settle-mode-option" for="settlement-unposting">
                                    <!-- <span class="settle-mode-icon"><i class="fas fa-file-alt"></i></span> -->
                                    <span class="settle-mode-text">
                                        <span class="settle-mode-main">Unposting</span>
                                    </span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="row g-2 align-items-end">
                        <!-- Partner List -->
                        <div class="col-lg-3 col-md-6 col-sm-12 compact-field">
                            <label class="form-label">Partners Name:</label>
                            <select id="partnerlistDropdown" class="form-select select2" aria-label="Select Partner" name="partnerlist" data-placeholder="Search or select a Partner..." required>
                                <option value="">Select Partner</option>
                                <?php foreach ($partner_options as $partner_name): ?>
                                    <option value="<?php echo htmlspecialchars($partner_name); ?>"><?php echo htmlspecialchars($partner_name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Time Frame -->
                        <div class="col-lg-3 col-md-6 col-sm-12 compact-field">
                            <label class="form-label">Time Frame:</label>
                            <select id="filterType" class="form-select" name="filterType" required>
                                <option value="">Select Time Frame</option>
                                <option value="daily">Per Day</option>
                                <option value="date-range">Date Range</option>
                                <option value="monthly">Per Month</option>
                            </select>
                        </div>

                        <!-- Date Range based on selected Time Frame -->
                        <div id="startDateWrap" class="col-lg-2 col-md-6 col-sm-12 compact-field" style="display: none;">
                            <label class="form-label">Start Date:</label>
                            <input id="startDate" type="date" class="form-control" name="startDate" required>
                        </div>
                        <div id="endDateWrap" class="col-lg-2 col-md-6 col-sm-12 compact-field" style="display: none;">
                            <label class="form-label">End Date:</label>
                            <input id="endDate" type="date" class="form-control" name="endDate" required>
                        </div>

                        <div id="referenceNumberWrap" class="col-lg-8 col-md-12 col-sm-12 compact-field" style="display: none;">
                            <label class="form-label">Reference Number:</label>
                            <input id="referenceNumberInput" type="text" class="form-control" placeholder="Enter reference number">
                        </div>

                        <div class="col-lg-2 col-md-12 col-sm-12 settle-filter-actions">
                            <button id="generateReport" type="button" class="btn btn-secondary w-100 compact-apply-btn" disabled>Generate</button>
                        </div>
                    </div>

                    <div class="day-shortcut-container mt-2" id="dayFilterContainer" style="display: none;">
                        <div class="day-buttons-label">Filter by Day:</div>
                        <div class="day-buttons-wrapper" id="dayButtonsWrapper">
                            <button type="button" class="day-button day-button-all day-button-active" id="allDaysButton">All</button>
                        </div>
                    </div>
                    <div id="reportStatus" class="small text-muted mt-2"></div>
                </div>
                <div class="card-body settle-report-body">
                    <div class="d-flex justify-content-end align-items-center mb-2 gap-2">
                        <div class="d-flex gap-2">
                            <button id="settle-edit" type="button" class="btn btn-secondary" disabled>Reason</button>
                            <button id="settle-save" type="button" class="btn btn-secondary" disabled>Save</button>
                        </div>
                        <div class="input-group table-search-wrap">
                            <span class="input-group-text bg-white">
                                <i class="fas fa-search text-muted"></i>
                            </span>
                            <input
                                type="text"
                                id="tableSearchInput"
                                class="form-control"
                                placeholder="Search records..."
                                aria-label="Search records"
                            >
                        </div>
                    </div>
                    <div class="table-responsive" id="tableContainer">
                        <table id="transactionReportTable" class="table table-bordered table-hover table-striped">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th class='text-truncate text-center align-middle table-checkbox-cell'>
                                        <div class="form-check d-flex justify-content-center m-0">
                                            <input class="form-check-input" type="checkbox" id="selectAllRows" aria-label="Select all rows">
                                        </div>
                                    </th>
                                    <th class='text-truncate text-center align-middle'>Reference Number</th>
                                    <th class='text-truncate text-center align-middle'>Payor Name</th>
                                    <th class='text-truncate text-center align-middle'>Payor Address</th>
                                    <th class='text-truncate text-center align-middle'>Account Number</th>
                                    <th class='text-truncate text-center align-middle'>Account Name</th>
                                    <th class='text-truncate text-center align-middle'>Principal</th>
                                    <th class='text-truncate text-center align-middle'>Charge to Customer</th>
                                    <th class='text-truncate text-center align-middle'>Charge to Partner</th>
                                    <th class='text-truncate text-center align-middle'>Contact Number</th>
                                    <th class='text-truncate text-center align-middle'>Other Details</th>
                                    <th class='text-truncate text-center align-middle'>Branch Outlet</th>
                                    <th class='text-truncate text-center align-middle'>Branch Operator</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Data will be populated via JavaScript -->
                                <tr>
                                    <td class="table-checkbox-cell">
                                        <div class="form-check d-flex justify-content-center m-0">
                                            <input class="form-check-input row-select" type="checkbox" aria-label="Select row">
                                        </div>
                                    </td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="settlementEditModal" tabindex="-1" aria-labelledby="settlementEditModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="settlementEditModalLabel">Reason</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="settlementModalFormHost"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-danger" id="settlementSubmitChanges">Submit Changes</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        $(function () {
            const partnerDropdown = $('#partnerlistDropdown');
            const $filterType = $('#filterType');
            const $startDate = $('#startDate');
            const $endDate = $('#endDate');
            const $startWrap = $('#startDateWrap');
            const $endWrap = $('#endDateWrap');
            const $generateBtn = $('#generateReport');
            const $dayFilterContainer = $('#dayFilterContainer');
            const $dayButtonsWrapper = $('#dayButtonsWrapper');
            const $reportStatus = $('#reportStatus');
            const $tableSearchInput = $('#tableSearchInput');
            const $settleEditBtn = $('#settle-edit');
            const $settleSaveBtn = $('#settle-save');
            const $settlementViewRadios = $('input[name="settlement_view"]');
            const $partnerFieldWrap = partnerDropdown.closest('.col-lg-3');
            const $filterTypeFieldWrap = $filterType.closest('.col-lg-3');
            const $referenceNumberWrap = $('#referenceNumberWrap');
            const $referenceNumberInput = $('#referenceNumberInput');
            const settlementEditModalEl = document.getElementById('settlementEditModal');
            const settlementEditModal = settlementEditModalEl ? new bootstrap.Modal(settlementEditModalEl) : null;
            let currentRangeStart = '';
            let currentRangeEnd = '';

            partnerDropdown.select2({
                placeholder: partnerDropdown.data('placeholder') || 'Search or select a Partner...',
                allowClear: true,
                width: '100%'
            });

            function configureInputsForFilterType(filterType) {
                const startLabel = $startWrap.find('label');
                const endLabel = $endWrap.find('label');

                $startDate.val('');
                $endDate.val('');

                $startDate.attr({ min: null, max: null, placeholder: '' });
                $endDate.attr({ min: null, max: null, placeholder: '' });

                if (filterType === 'date-range') {
                    $startDate.attr('type', 'date');
                    $endDate.attr('type', 'date');
                    startLabel.text('Start Date:');
                    endLabel.text('End Date:');
                    $startWrap.show();
                    $endWrap.show();
                    return;
                }

                if (filterType === 'daily') {
                    $startDate.attr('type', 'date');
                    startLabel.text('Select Date:');
                    $startWrap.show();
                    $endWrap.hide();
                    return;
                }

                if (filterType === 'monthly' || filterType === 'monthly-range') {
                    $startDate.attr('type', 'month');
                    $endDate.attr('type', 'month');
                    startLabel.text(filterType === 'monthly' ? 'Select Month:' : 'Start Month:');
                    endLabel.text('End Month:');
                    $startWrap.show();
                    $endWrap.toggle(filterType === 'monthly-range');
                    return;
                }

                if (filterType === 'yearly' || filterType === 'yearly-range') {
                    $startDate.attr('type', 'number');
                    $endDate.attr('type', 'number');
                    $startDate.attr({ min: '2020', max: '2035', placeholder: 'YYYY' });
                    $endDate.attr({ min: '2020', max: '2035', placeholder: 'YYYY' });
                    startLabel.text(filterType === 'yearly' ? 'Select Year:' : 'Start Year:');
                    endLabel.text('End Year:');
                    $startWrap.show();
                    $endWrap.toggle(filterType === 'yearly-range');
                    return;
                }

                $startWrap.hide();
                $endWrap.hide();
            }

            function toggleGenerateButton() {
                const selectedView = $('input[name="settlement_view"]:checked').val() || 'filter';

                if (selectedView === 'reference') {
                    const hasReference = $.trim($referenceNumberInput.val()) !== '';
                    if (hasReference) {
                        $generateBtn.prop('disabled', false).removeClass('btn-secondary').addClass('btn-danger');
                    } else {
                        $generateBtn.prop('disabled', true).removeClass('btn-danger').addClass('btn-secondary');
                    }
                    return;
                }

                const filterType = $filterType.val();
                const partner = partnerDropdown.val();
                const startDate = $startDate.val();
                const endDate = $endDate.val();

                let datesValid = false;
                if (filterType === 'daily' || filterType === 'monthly' || filterType === 'yearly') {
                    datesValid = startDate !== '';
                } else if (filterType === 'date-range' || filterType === 'monthly-range' || filterType === 'yearly-range') {
                    datesValid = startDate !== '' && endDate !== '';
                }

                const enable = !!(filterType && partner && datesValid);
                if (enable) {
                    $generateBtn.prop('disabled', false).removeClass('btn-secondary').addClass('btn-danger');
                } else {
                    $generateBtn.prop('disabled', true).removeClass('btn-danger').addClass('btn-secondary');
                }
            }

            function toggleSettlementViewFields() {
                const selectedView = $('input[name="settlement_view"]:checked').val() || 'filter';

                if (selectedView === 'reference') {
                    $partnerFieldWrap.hide();
                    $filterTypeFieldWrap.hide();
                    $startWrap.hide();
                    $endWrap.hide();
                    $referenceNumberWrap.show();
                    $dayFilterContainer.hide();
                } else {
                    $partnerFieldWrap.show();
                    $filterTypeFieldWrap.show();
                    $referenceNumberWrap.hide();
                    configureInputsForFilterType($filterType.val());
                }

                toggleGenerateButton();
            }

            function formatDate(date) {
                const year = date.getFullYear();
                const month = String(date.getMonth() + 1).padStart(2, '0');
                const day = String(date.getDate()).padStart(2, '0');
                return `${year}-${month}-${day}`;
            }

            function renderDayButtons(startDate, endDate) {
                $dayButtonsWrapper.find('.day-button').not('#allDaysButton').remove();
                $dayButtonsWrapper.find('.month-separator').remove();
                $dayButtonsWrapper.find('div.day-break').remove();

                const start = new Date(startDate);
                const end = new Date(endDate);
                const dateButtons = [];

                for (let d = new Date(start); d <= end; d.setDate(d.getDate() + 1)) {
                    dateButtons.push({
                        date: formatDate(d),
                        day: d.getDate(),
                        fullDate: new Date(d)
                    });
                }

                const monthGroups = {};
                dateButtons.forEach(function (item) {
                    const monthYear = item.fullDate.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
                    if (!monthGroups[monthYear]) {
                        monthGroups[monthYear] = [];
                    }
                    monthGroups[monthYear].push(item);
                });

                const groupKeys = Object.keys(monthGroups);
                groupKeys.forEach(function (monthYear, index) {
                    if (groupKeys.length > 1) {
                        $dayButtonsWrapper.append(`<div class="month-separator">${monthYear}</div>`);
                    }

                    monthGroups[monthYear].forEach(function (item) {
                        const button = $(`<button type="button" class="day-button day-number-button" data-date="${item.date}" title="${item.date}">${item.day}</button>`);
                        $dayButtonsWrapper.append(button);
                    });

                    if (groupKeys.length > 1 && index < groupKeys.length - 1) {
                        $dayButtonsWrapper.append('<div class="day-break" style="width:100%;height:5px;"></div>');
                    }
                });

                $dayFilterContainer.show();
            }

            function setDayActive($button) {
                $dayButtonsWrapper.find('.day-button').removeClass('day-button-active');
                $button.addClass('day-button-active');
            }

            function requestReport(startDate, endDate) {
                const selectedView = $('input[name="settlement_view"]:checked').val() || 'filter';
                $.ajax({
                    url: window.location.pathname,
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'generate_report',
                        settlementView: selectedView,
                        partner: partnerDropdown.val(),
                        filterType: $filterType.val(),
                        startDate: startDate,
                        endDate: endDate,
                        referenceNumber: $referenceNumberInput.val()
                    },
                    success: function(result) {
                        if (!result || result.status !== 'success') {
                            Swal.fire({
                                icon: 'error',
                                title: 'Generate Failed',
                                text: (result && result.message) ? result.message : 'Unable to generate report.'
                            });
                            clearReportTable();
                            return;
                        }

                        populateReportTable(result.data || []);
                        $reportStatus.text(`Found ${result.totalRows || 0} record(s).`);
                    },
                    error: function(xhr, status, error) {
                        console.error('generate_report failed:', status, error);
                        clearReportTable();
                        Swal.fire({
                            icon: 'error',
                            title: 'Connection Error',
                            text: 'Failed to generate report. Please try again.'
                        });
                    }
                });
            }

            function formatAmount(value) {
                const numeric = parseFloat(value || 0);
                return numeric.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }

            function escapeHtml(value) {
                return String(value ?? '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            }

            function clearReportTable() {
                const $tbody = $('#transactionReportTable tbody');
                $tbody.html(`
                    <tr>
                        <td colspan="13" class="text-center text-muted">No data found for selected criteria.</td>
                    </tr>
                `);
                $('#selectAllRows').prop('checked', false);
                updateEditButtonState();
                updateSaveButtonState();
            }

            function populateReportTable(data) {
                const $tbody = $('#transactionReportTable tbody');
                $tbody.empty();

                if (!Array.isArray(data) || data.length === 0) {
                    clearReportTable();
                    return;
                }

                data.forEach(function (row) {
                    const transactionDatetime = escapeHtml(row.transaction_datetime || '');
                    const referenceKey = escapeHtml(`${row.reference_number || ''}|${row.transaction_datetime || ''}`);
                    const html = `
                        <tr data-datetime="${transactionDatetime}" data-reference-key="${referenceKey}">
                            <td class="table-checkbox-cell">
                                <div class="form-check d-flex justify-content-center m-0">
                                    <input class="form-check-input row-select" type="checkbox" aria-label="Select row">
                                </div>
                            </td>
                            <td class="text-truncate">${escapeHtml(row.reference_number)}</td>
                            <td class="text-truncate">${escapeHtml(row.payor_name)}</td>
                            <td class="text-truncate">${escapeHtml(row.payor_address)}</td>
                            <td class="text-truncate">${escapeHtml(row.account_number)}</td>
                            <td class="text-truncate">${escapeHtml(row.account_name)}</td>
                            <td class="text-end">${formatAmount(row.principal)}</td>
                            <td class="text-end">${formatAmount(row.charge_to_customer)}</td>
                            <td class="text-end">${formatAmount(row.charge_to_partner)}</td>
                            <td class="text-truncate">${escapeHtml(row.contact_number)}</td>
                            <td class="text-truncate">${escapeHtml(row.other_details)}</td>
                            <td class="text-truncate">${escapeHtml(row.branch_outlet)}</td>
                            <td class="text-truncate">${escapeHtml(row.branch_operator)}</td>
                        </tr>
                    `;
                    $tbody.append(html);
                });

                $('#selectAllRows').prop('checked', false);
                updateEditButtonState();
                updateSaveButtonState();
            }

            function updateEditButtonState() {
                const checkedCount = $('#transactionReportTable tbody .row-select:checked').length;
                const hasSelection = checkedCount > 0;

                if (hasSelection) {
                    $settleEditBtn.prop('disabled', false).removeClass('btn-secondary').addClass('btn-danger');
                } else {
                    $settleEditBtn.prop('disabled', true).removeClass('btn-danger').addClass('btn-secondary');
                }
            }

            function updateSaveButtonState() {
                const highlightedCount = $('#transactionReportTable tbody tr.bg-danger-subtle, #transactionReportTable tbody tr.bs-danger-bg-subtle, #transactionReportTable tbody tr.table-danger').length;
                const hasHighlightedRows = highlightedCount > 0;

                if (hasHighlightedRows) {
                    $settleSaveBtn.prop('disabled', false).removeClass('btn-secondary').addClass('btn-success');
                } else {
                    $settleSaveBtn.prop('disabled', true).removeClass('btn-success').addClass('btn-secondary');
                }
            }

            window.updateSettlementSaveButtonState = updateSaveButtonState;
            window.updateSettlementEditButtonState = updateEditButtonState;

            function getPendingSettlementChangesForSave() {
                if (typeof window.getSettlementChangesForSave === 'function') {
                    return window.getSettlementChangesForSave();
                }
                return [];
            }

            function filterTableRows(searchText) {
                const keyword = (searchText || '').toLowerCase().trim();
                const $rows = $('#transactionReportTable tbody tr');

                if (!keyword) {
                    $rows.show();
                    return;
                }

                $rows.each(function () {
                    const rowText = $(this).text().toLowerCase();
                    $(this).toggle(rowText.includes(keyword));
                });
            }

            $filterType.on('change', function () {
                configureInputsForFilterType($(this).val());
                $dayFilterContainer.hide();
                $dayButtonsWrapper.find('.day-button').not('#allDaysButton').remove();
                setDayActive($('#allDaysButton'));
                $reportStatus.text('');
                clearReportTable();
                toggleGenerateButton();
            });

            partnerDropdown.on('change', function () {
                $reportStatus.text('');
                clearReportTable();
                toggleGenerateButton();
            });

            $startDate.add($endDate).on('input change', function () {
                $reportStatus.text('');
                clearReportTable();
                toggleGenerateButton();
            });

            $referenceNumberInput.on('input change', function () {
                $reportStatus.text('');
                clearReportTable();
                toggleGenerateButton();
            });

            $settlementViewRadios.on('change', function () {
                $reportStatus.text('');
                clearReportTable();
                toggleSettlementViewFields();
            });

            $generateBtn.on('click', function () {
                const selectedView = $('input[name="settlement_view"]:checked').val() || 'filter';
                if (selectedView === 'reference') {
                    const referenceNumber = $.trim($referenceNumberInput.val());
                    if (!referenceNumber) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Missing Reference Number',
                            text: 'Please enter a reference number.'
                        });
                        return;
                    }

                    $dayFilterContainer.hide();
                    $dayButtonsWrapper.find('.day-button').not('#allDaysButton').remove();
                    setDayActive($('#allDaysButton'));
                    requestReport('', '');
                    return;
                }

                const filterType = $filterType.val();
                const partner = partnerDropdown.val();
                const startDate = $startDate.val();
                let endDate = $endDate.val();

                if (!filterType || !partner) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Missing Information',
                        text: 'Please select Partner and Time Frame.'
                    });
                    return;
                }

                if (!startDate) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Missing Date',
                        text: 'Please provide Start Date.'
                    });
                    return;
                }

                if ((filterType === 'date-range' || filterType === 'monthly-range' || filterType === 'yearly-range') && !endDate) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Missing End Date',
                        text: 'Please provide End Date.'
                    });
                    return;
                }

                if (filterType === 'daily' || filterType === 'monthly' || filterType === 'yearly') {
                    endDate = startDate;
                }

                currentRangeStart = startDate;
                currentRangeEnd = endDate;

                if (filterType === 'date-range') {
                    renderDayButtons(startDate, endDate);
                    setDayActive($('#allDaysButton'));
                } else {
                    $dayFilterContainer.hide();
                    $dayButtonsWrapper.find('.day-button').not('#allDaysButton').remove();
                    setDayActive($('#allDaysButton'));
                }

                requestReport(startDate, endDate);
            });

            $dayButtonsWrapper.on('click', '.day-button', function () {
                const $btn = $(this);
                setDayActive($btn);

                if ($btn.attr('id') === 'allDaysButton') {
                    if (currentRangeStart && currentRangeEnd) {
                        requestReport(currentRangeStart, currentRangeEnd);
                    }
                    return;
                }

                const selectedDate = $btn.data('date');
                if (selectedDate) {
                    requestReport(selectedDate, selectedDate);
                }
            });

            $('#selectAllRows').on('change', function () {
                const isChecked = $(this).is(':checked');
                $('#transactionReportTable tbody .row-select:not(:disabled)').prop('checked', isChecked);
                updateEditButtonState();
            });

            $('#transactionReportTable').on('change', '.row-select', function () {
                const total = $('#transactionReportTable tbody .row-select').length;
                const checked = $('#transactionReportTable tbody .row-select:checked').length;
                $('#selectAllRows').prop('checked', total > 0 && total === checked);
                updateEditButtonState();
            });

            $settleEditBtn.on('click', function () {
                if ($(this).prop('disabled')) {
                    return;
                }

                const selectedRows = window.collectSelectedRowsByReference();
                if (!Array.isArray(selectedRows) || selectedRows.length === 0) {
                    return;
                }

                window.populateSettlementEditModal(selectedRows);

                if (settlementEditModal) {
                    settlementEditModal.show();
                }
            });

            $tableSearchInput.on('input', function () {
                filterTableRows($(this).val());
            });

            $settleSaveBtn.on('click', function () {
                if ($(this).prop('disabled')) {
                    return;
                }

                const pendingChanges = getPendingSettlementChangesForSave();
                if (!Array.isArray(pendingChanges) || pendingChanges.length === 0) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'No Pending Changes',
                        text: 'Please submit changes first before saving.'
                    });
                    return;
                }

                Swal.fire({
                    icon: 'question',
                    title: 'Confirm Save',
                    text: 'Do you want to save these submitted changes?',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, Proceed',
                    cancelButtonText: 'Cancel'
                }).then(function (confirmResult) {
                    if (!confirmResult.isConfirmed) {
                        return;
                    }

                    $.ajax({
                        url: window.location.pathname,
                        type: 'POST',
                        dataType: 'json',
                        data: {
                            action: 'save_changes',
                            changes: JSON.stringify(pendingChanges)
                        },
                        success: function (result) {
                            if (!result || result.status !== 'success') {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Save Failed',
                                    text: (result && result.message) ? result.message : 'Unable to save changes.'
                                });
                                return;
                            }

                            Swal.fire({
                                icon: 'success',
                                title: 'Inserted Successfully',
                                text: `Saved ${result.insertedRows || 0} record(s).`
                            });
                        },
                        error: function () {
                            Swal.fire({
                                icon: 'error',
                                title: 'Connection Error',
                                text: 'Failed to save changes. Please try again.'
                            });
                        }
                    });
                });
            });

            loadPartners();
            configureInputsForFilterType($filterType.val());
            toggleSettlementViewFields();
            toggleGenerateButton();
            setDayActive($('#allDaysButton'));
            clearReportTable();
            updateEditButtonState();
            updateSaveButtonState();
        });

        function loadPartners() {
            $.ajax({
                url: window.location.pathname,
                type: 'POST',
                dataType: 'json',
                data: { action: 'get_partner_list' },
                success: function(result) {
                    if (!result || result.status !== 'success' || !Array.isArray(result.data)) {
                        return;
                    }

                    const select = $('#partnerlistDropdown');
                    const existingValues = new Set();

                    select.find('option').each(function () {
                        existingValues.add($(this).val());
                    });

                    result.data.forEach(function (partner) {
                        if (!partner || !partner.partner_name) {
                            return;
                        }

                        if (!existingValues.has(partner.partner_name)) {
                            select.append(new Option(partner.partner_name, partner.partner_name));
                            existingValues.add(partner.partner_name);
                        }
                    });

                    select.trigger('change.select2');
                },
                error: function(xhr, status, error) {
                    console.error('Failed to load partner list:', status, error);
                }
            });
        }
    </script>

    <script>
        function escapeModalValue(value) {
            return $('<div>').text(value || '').html();
        }

        function renderSettlementModalFormHtml(selectedRows) {
            if (!Array.isArray(selectedRows) || selectedRows.length === 0) {
                $('#settlementModalFormHost').html('<div class="text-muted">No selected transaction.</div>');
                return;
            }

            const html = selectedRows.map(function (row, index) {
                const reference = escapeModalValue(row.referenceNumber);
                const referenceKey = escapeModalValue(row.referenceKey || buildSettlementRowKey(row.referenceNumber, row.transactionDatetime));
                const reason = row.reasonNote || '';

                return `
                    <div class="border rounded p-3 mb-3 bg-light-subtle settlement-edit-item" data-reference="${referenceKey}">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="mb-0">Reference #${reference}</h6>
                            <span class="badge text-bg-secondary">Item ${index + 1}</span>
                        </div>

                        <div class="row g-2 mb-2">
                            <div class="col-md-6">
                                <label class="form-label mb-1">Reference Number</label>
                                <input type="text" class="form-control" value="${reference}" readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label mb-1">Reason Note</label>
                                <select class="form-select edit-reason-note" data-reference="${referenceKey}">
                                    <option value="" ${reason === '' ? 'selected' : ''}>Select a Reason</option>
                                    <option value="late-posting" ${reason === 'late-posting' ? 'selected' : ''}>Late Posting</option>
                                    <option value="wrong-biller" ${reason === 'wrong-biller' ? 'selected' : ''}>Wrong Biller</option>
                                    <option value="no-payment" ${reason === 'no-payment' ? 'selected' : ''}>No Payment</option>
                                    <option value="wrong-account" ${reason === 'wrong-account' ? 'selected' : ''}>Wrong Account</option>
                                    <option value="wrong-amount" ${reason === 'wrong-amount' ? 'selected' : ''}>Wrong Amount</option>
                                </select>
                            </div>
                        </div>

                        <div class="row g-3 settlement-dual-cards-row">
                            <div class="col-lg-12 settlement-prev-col">
                                <div class="border rounded p-3 h-100 bg-white settlement-prev-field-panel">
                                    <h6 class="mb-3 text-center">Previous</h6>
                                    <div class="mb-2 prev-field-row" data-field="payor"><label class="form-label mb-1">Payor Name</label><input type="text" class="form-control" value="${escapeModalValue(row.payor)}" readonly></div>
                                    <div class="mb-2 prev-field-row" data-field="address"><label class="form-label mb-1">Payor Address</label><input type="text" class="form-control" value="${escapeModalValue(row.address)}" readonly></div>
                                    <div class="mb-2 prev-field-row" data-field="account_no"><label class="form-label mb-1">Account Number</label><input type="text" class="form-control" value="${escapeModalValue(row.accountNo)}" readonly></div>
                                    <div class="mb-2 prev-field-row" data-field="account_name"><label class="form-label mb-1">Account Name</label><input type="text" class="form-control" value="${escapeModalValue(row.accountName)}" readonly></div>
                                    <div class="mb-2 prev-field-row" data-field="principal"><label class="form-label mb-1">Principal</label><input type="text" class="form-control" value="${escapeModalValue(row.amountPaid)}" readonly></div>
                                    <div class="mb-2 prev-field-row" data-field="charge_to_customer"><label class="form-label mb-1">Charge to Customer</label><input type="text" class="form-control" value="${escapeModalValue(row.chargeToCustomer)}" readonly></div>
                                    <div class="mb-2 prev-field-row" data-field="charge_to_partner"><label class="form-label mb-1">Charge to Partner</label><input type="text" class="form-control" value="${escapeModalValue(row.chargeToPartner)}" readonly></div>
                                    <div class="mb-2 prev-field-row" data-field="contact_no"><label class="form-label mb-1">Contact Number</label><input type="text" class="form-control" value="${escapeModalValue(row.contactNo)}" readonly></div>
                                    <div class="mb-2 prev-field-row" data-field="other_details"><label class="form-label mb-1">Other Details</label><input type="text" class="form-control" value="${escapeModalValue(row.otherDetails)}" readonly></div>
                                    <div class="mb-2 prev-field-row" data-field="outlet"><label class="form-label mb-1">Branch Outlet</label><input type="text" class="form-control" value="${escapeModalValue(row.mlOutlet)}" readonly></div>
                                    <div class="mb-0 prev-field-row" data-field="operator"><label class="form-label mb-1">Branch Operator</label><input type="text" class="form-control" value="${escapeModalValue(row.operator)}" readonly></div>
                                </div>
                            </div>

                            <div class="col-lg-6 settlement-edit-col">
                                <div class="border rounded p-3 h-100 bg-white settlement-edit-field-panel">
                                    <h6 class="mb-3 text-center">Edit Field</h6>
                                    <div class="mb-2 edit-field-row" data-field="payor"><label class="form-label mb-1">Payor Name</label><input type="text" class="form-control edit-payor" data-reference="${referenceKey}" value="${escapeModalValue(row.payor)}" disabled></div>
                                    <div class="mb-2 edit-field-row" data-field="address"><label class="form-label mb-1">Payor Address</label><input type="text" class="form-control edit-address" data-reference="${referenceKey}" value="${escapeModalValue(row.address)}" disabled></div>
                                    <div class="mb-2 edit-field-row" data-field="account_no"><label class="form-label mb-1">Account Number</label><input type="text" class="form-control edit-account-no" data-reference="${referenceKey}" value="${escapeModalValue(row.accountNo)}" disabled></div>
                                    <div class="mb-2 edit-field-row" data-field="account_name"><label class="form-label mb-1">Account Name</label><input type="text" class="form-control edit-account-name" data-reference="${referenceKey}" value="${escapeModalValue(row.accountName)}" disabled></div>
                                    <div class="mb-2 edit-field-row" data-field="principal"><label class="form-label mb-1">Principal</label><input type="text" class="form-control edit-amount-paid" data-reference="${referenceKey}" value="${escapeModalValue(row.amountPaid)}" disabled></div>
                                    <div class="mb-2 edit-field-row" data-field="charge_to_customer"><label class="form-label mb-1">Charge to Customer</label><input type="text" class="form-control edit-charge-customer" data-reference="${referenceKey}" value="${escapeModalValue(row.chargeToCustomer)}" disabled></div>
                                    <div class="mb-2 edit-field-row" data-field="charge_to_partner"><label class="form-label mb-1">Charge to Partner</label><input type="text" class="form-control edit-charge-partner" data-reference="${referenceKey}" value="${escapeModalValue(row.chargeToPartner)}" disabled></div>
                                    <div class="mb-2 edit-field-row" data-field="contact_no"><label class="form-label mb-1">Contact Number</label><input type="text" class="form-control edit-contact-no" data-reference="${referenceKey}" value="${escapeModalValue(row.contactNo)}" disabled></div>
                                    <div class="mb-2 edit-field-row" data-field="other_details"><label class="form-label mb-1">Other Details</label><input type="text" class="form-control edit-other-details" data-reference="${referenceKey}" value="${escapeModalValue(row.otherDetails)}" disabled></div>
                                    <div class="mb-2 edit-field-row" data-field="outlet"><label class="form-label mb-1">Branch Outlet</label><input type="text" class="form-control edit-ml-outlet" data-reference="${referenceKey}" value="${escapeModalValue(row.mlOutlet)}" disabled></div>
                                    <div class="mb-0 edit-field-row" data-field="operator"><label class="form-label mb-1">Branch Operator</label><input type="text" class="form-control edit-operator" data-reference="${referenceKey}" value="${escapeModalValue(row.operator)}" disabled></div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            }).join('');

            $('#settlementModalFormHost').html(html);

            $('#settlementModalFormHost .settlement-edit-item').each(function () {
                const $container = $(this);
                const reason = $container.find('.edit-reason-note').val() || '';
                toggleWrongAmountFields($container, reason);
            });
        }

        renderSettlementModalFormHtml([]);

        function buildSettlementRowKey(referenceNumber, transactionDatetime) {
            const ref = String(referenceNumber || '').trim();
            const datetime = String(transactionDatetime || '').trim();
            return `${ref}|${datetime}`;
        }

        function toggleWrongAmountFields($container, reasonValue) {
            const reason = String(reasonValue || '').trim();
            const hasReason = reason !== '';
            const isLatePosting = reason === 'late-posting';

            const reasonFieldMap = {
                'wrong-biller': ['payor', 'address', 'other_details'],
                'wrong-account': ['account_no', 'account_name'],
                'wrong-amount': ['principal', 'charge_to_customer', 'charge_to_partner'],
                'no-payment': ['principal', 'charge_to_customer', 'charge_to_partner']
            };

            const visibleFields = reasonFieldMap[reason] || [];
            const visibleSet = new Set(visibleFields);

            const $prevCol = $container.find('.settlement-prev-col');
            const $editCol = $container.find('.settlement-edit-col');
            const $editPanel = $container.find('.settlement-edit-field-panel');
            const $prevRows = $container.find('.prev-field-row');
            const $editRows = $container.find('.edit-field-row');
            const $editInputs = $editRows.find('input');

            $editInputs.prop('readonly', false).prop('disabled', true).removeClass('bg-light');

            if (!hasReason || isLatePosting) {
                $prevCol.removeClass('col-lg-6').addClass('col-lg-12');
                $editCol.hide();
                $editPanel.hide();
                $prevRows.show();
                $editRows.show();
                return;
            }

            $prevCol.removeClass('col-lg-12').addClass('col-lg-6');
            $editCol.show();
            $editPanel.show();

            $prevRows.show();
            $editRows.hide();

            if (visibleSet.size > 0) {
                $prevRows.filter(function () {
                    const key = String($(this).data('field') || '');
                    return visibleSet.has(key);
                }).show();

                $editRows.filter(function () {
                    const key = String($(this).data('field') || '');
                    return visibleSet.has(key);
                }).show();
            }

            $editRows.each(function () {
                const key = String($(this).data('field') || '');
                const $input = $(this).find('input');
                if (!visibleSet.has(key)) {
                    return;
                }

                if (reason === 'wrong-amount') {
                    $input.prop('disabled', false).prop('readonly', false).addClass('bg-light');
                } else {
                    $input.prop('disabled', false).prop('readonly', true).addClass('bg-light');
                }
            });
        }

        function splitSettlementRowKey(referenceKey) {
            const key = String(referenceKey || '');
            const splitIndex = key.indexOf('|');

            if (splitIndex < 0) {
                return {
                    referenceNumber: key,
                    transactionDatetime: ''
                };
            }

            return {
                referenceNumber: key.substring(0, splitIndex),
                transactionDatetime: key.substring(splitIndex + 1)
            };
        }

        function collectSettlementModalChanges() {
            const changes = [];

            $('#settlementModalFormHost .settlement-edit-item').each(function () {
                const $container = $(this);
                const referenceKey = String($container.attr('data-reference') || '').trim();
                const splitKey = splitSettlementRowKey(referenceKey);
                const reasonNote = String($container.find('.edit-reason-note').val() || '').trim();

                changes.push({
                    reference_key: referenceKey,
                    reference_number: splitKey.referenceNumber,
                    transaction_datetime: splitKey.transactionDatetime,
                    reason_note: reasonNote,
                    payor: String($container.find('.edit-payor').val() || '').trim(),
                    address: String($container.find('.edit-address').val() || '').trim(),
                    account_no: String($container.find('.edit-account-no').val() || '').trim(),
                    account_name: String($container.find('.edit-account-name').val() || '').trim(),
                    principal: String($container.find('.edit-amount-paid').val() || '').trim(),
                    charge_to_customer: String($container.find('.edit-charge-customer').val() || '').trim(),
                    charge_to_partner: String($container.find('.edit-charge-partner').val() || '').trim(),
                    contact_no: String($container.find('.edit-contact-no').val() || '').trim(),
                    other_details: String($container.find('.edit-other-details').val() || '').trim(),
                    outlet: String($container.find('.edit-ml-outlet').val() || '').trim(),
                    operator: String($container.find('.edit-operator').val() || '').trim()
                });
            });

            return changes;
        }

        function formatSubmittedAmount(value) {
            const text = String(value ?? '').replace(/,/g, '').trim();
            if (text === '') {
                return '';
            }

            const numeric = parseFloat(text);
            if (Number.isNaN(numeric)) {
                return String(value ?? '').trim();
            }

            return numeric.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        function applySubmittedDataToTable(submittedRows) {
            if (!Array.isArray(submittedRows) || submittedRows.length === 0) {
                return;
            }

            submittedRows.forEach(function (item) {
                const referenceKey = String(item.reference_key || '').trim();
                if (!referenceKey) {
                    return;
                }

                const $row = $(`#transactionReportTable tbody tr[data-reference-key="${referenceKey.replace(/"/g, '\\"')}"]`);
                if (!$row.length) {
                    return;
                }

                const $cells = $row.find('td');
                $cells.eq(2).text(String(item.payor || '').trim());
                $cells.eq(3).text(String(item.address || '').trim());
                $cells.eq(4).text(String(item.account_no || '').trim());
                $cells.eq(5).text(String(item.account_name || '').trim());
                $cells.eq(6).text(formatSubmittedAmount(item.principal));
                $cells.eq(7).text(formatSubmittedAmount(item.charge_to_customer));
                $cells.eq(8).text(formatSubmittedAmount(item.charge_to_partner));
                $cells.eq(9).text(String(item.contact_no || '').trim());
                $cells.eq(10).text(String(item.other_details || '').trim());
                $cells.eq(11).text(String(item.outlet || '').trim());
                $cells.eq(12).text(String(item.operator || '').trim());
            });
        }

        function cacheSubmittedChanges(submittedRows) {
            if (!Array.isArray(submittedRows)) {
                return;
            }

            const store = window.settlementSubmittedChangesByKey || {};
            submittedRows.forEach(function (item) {
                const referenceKey = String(item.reference_key || '').trim();
                if (!referenceKey) {
                    return;
                }
                store[referenceKey] = item;
            });

            window.settlementSubmittedChangesByKey = store;
        }

        function getSettlementChangesForSave() {
            const store = window.settlementSubmittedChangesByKey || {};
            return Object.values(store);
        }

        window.getSettlementChangesForSave = getSettlementChangesForSave;

        function applySubmittedRowHighlight(referenceKeys, rowColorClass) {
            if (!Array.isArray(referenceKeys) || referenceKeys.length === 0) {
                return;
            }

            const keySet = new Set(referenceKeys.filter(Boolean));
            const requestedClass = String(rowColorClass || '').trim();
            const highlightClass = requestedClass || 'bg-danger-subtle';

            $('#transactionReportTable tbody tr').each(function () {
                const $row = $(this);
                const referenceKey = String($row.attr('data-reference-key') || '').trim();
                if (keySet.has(referenceKey)) {
                    $row.removeClass('bg-danger-subtle bs-danger-bg-subtle table-danger');
                    $row.children('td').removeClass('bg-danger-subtle bs-danger-bg-subtle table-danger');
                    $row.addClass(highlightClass);
                    $row.children('td').addClass(highlightClass);

                    const $checkbox = $row.find('.row-select');
                    $checkbox.prop('checked', false).prop('disabled', false);
                }
            });

            const totalEnabled = $('#transactionReportTable tbody .row-select').length;
            const checkedEnabled = $('#transactionReportTable tbody .row-select:checked').length;
            $('#selectAllRows').prop('checked', totalEnabled > 0 && checkedEnabled === totalEnabled);

            if (typeof window.updateSettlementSaveButtonState === 'function') {
                window.updateSettlementSaveButtonState();
            }

            if (typeof window.updateSettlementEditButtonState === 'function') {
                window.updateSettlementEditButtonState();
            }
        }

        function extractSettlementRowData($row) {
            const $cells = $row.find('td');
            const transactionDatetime = String($row.attr('data-datetime') || '').trim();
            const referenceNumber = $cells.eq(1).text().trim();
            return {
                referenceNumber,
                transactionDatetime,
                referenceKey: buildSettlementRowKey(referenceNumber, transactionDatetime),
                payor: $cells.eq(2).text().trim(),
                address: $cells.eq(3).text().trim(),
                accountNo: $cells.eq(4).text().trim(),
                accountName: $cells.eq(5).text().trim(),
                amountPaid: $cells.eq(6).text().trim(),
                chargeToCustomer: $cells.eq(7).text().trim(),
                chargeToPartner: $cells.eq(8).text().trim(),
                contactNo: $cells.eq(9).text().trim(),
                otherDetails: $cells.eq(10).text().trim(),
                mlOutlet: $cells.eq(11).text().trim(),
                operator: $cells.eq(12).text().trim(),
                reasonNote: ''
            };
        }

        function collectSelectedRowsByReference() {
            const rowsByReference = {};

            $('#transactionReportTable tbody .row-select:checked').each(function () {
                const rowData = extractSettlementRowData($(this).closest('tr'));
                if (rowData.referenceKey) {
                    rowsByReference[rowData.referenceKey] = rowData;
                }
            });

            window.selectedSettlementRowsByReference = rowsByReference;
            return Object.values(rowsByReference);
        }

        function populateSettlementEditModal(selectedRows) {
            if (!Array.isArray(selectedRows) || selectedRows.length === 0) {
                return;
            }

            renderSettlementModalFormHtml(selectedRows);
        }

        window.collectSelectedRowsByReference = collectSelectedRowsByReference;
        window.populateSettlementEditModal = populateSettlementEditModal;

        $(document).on('change', '.edit-reason-note', function () {
            const $container = $(this).closest('.settlement-edit-item');
            toggleWrongAmountFields($container, $(this).val());
        });

        $('#settlementSubmitChanges').on('click', function () {
            const changes = collectSettlementModalChanges();

            if (!changes.length) {
                Swal.fire({
                    icon: 'warning',
                    title: 'No Changes Found',
                    text: 'Please select transaction rows and edit details first.'
                });
                return;
            }

            $.ajax({
                url: window.location.pathname,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'submit_changes',
                    changes: JSON.stringify(changes)
                },
                success: function (result) {
                    if (!result || result.status !== 'success') {
                        Swal.fire({
                            icon: 'error',
                            title: 'Submit Failed',
                            text: (result && result.message) ? result.message : 'Unable to submit changes.'
                        });
                        return;
                    }

                    const submitted = Array.isArray(result.data) ? result.data : [];
                    const submittedKeys = submitted.map(function (item) {
                        return item.reference_key;
                    });

                    cacheSubmittedChanges(submitted);
                    applySubmittedDataToTable(submitted);
                    applySubmittedRowHighlight(submittedKeys, result.rowColorClass || 'bg-danger-subtle');

                    $('#transactionReportTable tbody .row-select').prop('checked', false).prop('disabled', false);
                    $('#selectAllRows').prop('checked', false);

                    if (typeof window.updateSettlementEditButtonState === 'function') {
                        window.updateSettlementEditButtonState();
                    }

                    const modalEl = document.getElementById('settlementEditModal');
                    const modalInstance = modalEl ? bootstrap.Modal.getInstance(modalEl) : null;
                    if (modalInstance) {
                        modalInstance.hide();
                    }

                    Swal.fire({
                        icon: 'success',
                        title: 'Changes Submitted',
                        text: 'Selected rows are marked with danger subtle background.'
                    });
                },
                error: function () {
                    Swal.fire({
                        icon: 'error',
                        title: 'Connection Error',
                        text: 'Failed to submit changes. Please try again.'
                    });
                }
            });
        });
    </script>
</body>
<?php include '../../../templates/footer.php'; ?>
</html>