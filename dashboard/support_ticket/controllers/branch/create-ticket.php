<?php
include_once __DIR__ . '/../../includes/bootstrap.php';

st_require_login('../../../../login_form.php');
$redirectBack = '../../create-ticket.php';

st_require_permission_page(['Support Ticket Create'], '../../../home.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    st_redirect_with_flash('create_ticket', 'danger', 'Invalid request method.', $redirectBack);
}

$userId = st_user_id_or_null();
if ($userId === null) {
    st_redirect_with_flash('create_ticket', 'danger', 'Unable to resolve current user ID.', $redirectBack);
}

$referenceNumber = trim((string) ($_POST['reference_number'] ?? ''));
$source = strtoupper(trim((string) ($_POST['source'] ?? 'KPX')));
$ticketTypeId = (int) ($_POST['ticket_type_id'] ?? 0);
$reason = trim((string) ($_POST['reason'] ?? ''));
$typeOfRequest = st_upper($_POST['type_of_request'] ?? '');
$transferDatetime = trim((string) ($_POST['transfer_datetime'] ?? ''));
$refNo = trim((string) ($_POST['ref_no'] ?? ''));
$subbillerExtId = trim((string) ($_POST['subbiller_ext_id'] ?? ''));
$accountNo = trim((string) ($_POST['account_no'] ?? ''));
$accountName = trim((string) ($_POST['account_name'] ?? ''));
$paymentBranchId = trim((string) ($_POST['payment_branch_id'] ?? ''));
$paymentBranchName = trim((string) ($_POST['payment_branch_name'] ?? ''));
$amount = st_to_decimal($_POST['amount'] ?? null);

$correctBillerId = trim((string) ($_POST['correct_biller_id'] ?? ''));
$correctBillerName = trim((string) ($_POST['correct_biller_name'] ?? ''));
$wrongAmount = st_to_decimal($_POST['wrong_amount'] ?? null);
$correctAmount = st_to_decimal($_POST['correct_amount'] ?? null);

if ($wrongAmount === null) {
    $wrongAmount = st_to_decimal($_POST['wrong_amount_cancel'] ?? null);
}
if ($correctAmount === null) {
    $correctAmount = st_to_decimal($_POST['correct_amount_cancel'] ?? null);
}

$allowedSources = ['KPX', 'KP7'];
if (!in_array($source, $allowedSources, true)) {
    st_redirect_with_flash('create_ticket', 'danger', 'Invalid source selected.', $redirectBack);
}

if (
    $referenceNumber === '' ||
    $reason === '' ||
    $typeOfRequest === '' ||
    $transferDatetime === '' ||
    $subbillerExtId === '' ||
    $accountNo === '' ||
    $accountName === '' ||
    $paymentBranchId === '' ||
    $paymentBranchName === '' ||
    $amount === null
) {
    st_redirect_with_flash('create_ticket', 'danger', 'Please complete all required fields before submitting.', $redirectBack);
}

if ($typeOfRequest === 'WRONG BILLER') {
    if ($correctBillerId === '' || $correctBillerName === '') {
        st_redirect_with_flash('create_ticket', 'danger', 'Correct biller details are required for WRONG BILLER request.', $redirectBack);
    }
}

if ($typeOfRequest === 'OVERSTATED AMOUNT' || $typeOfRequest === 'CANCELLED TRANSACTION') {
    if ($wrongAmount === null || $correctAmount === null) {
        st_redirect_with_flash('create_ticket', 'danger', 'Wrong and correct amounts are required for this request type.', $redirectBack);
    }
}

$subbiller = st_get_subbiller_by_ext_id($conn, $subbillerExtId);
if (!$subbiller) {
    st_redirect_with_flash('create_ticket', 'danger', 'Selected subbiller is invalid or not found in mldb.subbiller.', $redirectBack);
}

$billerName = trim((string) ($subbiller['subbiller_name'] ?? ''));
$partnerExtId = trim((string) ($subbiller['partner_ext_id'] ?? ''));
if ($billerName === '') {
    st_redirect_with_flash('create_ticket', 'danger', 'Selected subbiller has no canonical name.', $redirectBack);
}

$conn->autocommit(false);

try {
    $schema = st_schema();
    $ticketNumber = st_generate_ticket_number($conn);

    // Validate ticket_type_id exists; if not, set to 0 so NULL is inserted
    if ($ticketTypeId > 0) {
        $checkSql = "SELECT id FROM {$schema}.ticket_types WHERE id = ? LIMIT 1";
        $checkStmt = $conn->prepare($checkSql);
        if ($checkStmt) {
            $checkStmt->bind_param('i', $ticketTypeId);
            if ($checkStmt->execute()) {
                $res = $checkStmt->get_result();
                if (!$res || $res->num_rows === 0) {
                    $ticketTypeId = 0;
                }
            } else {
                $ticketTypeId = 0;
            }
            $checkStmt->close();
        } else {
            $ticketTypeId = 0;
        }
    }

    $ticketSql = "INSERT INTO {$schema}.tickets (
                    ticket_number,
                    reference_number,
                    source,
                    partner_ext_id,
                    created_by,
                    created_by_role,
                    current_handler_role,
                    status,
                    allow_branch_reply
                  ) VALUES (?, ?, ?, ?, ?, 'BRANCH', 'VPO', 'open', 1)";
    $ticketStmt = $conn->prepare($ticketSql);
    if (!$ticketStmt) {
        throw new Exception('Unable to prepare ticket insert statement.');
    }

    $ticketStmt->bind_param('ssssi', $ticketNumber, $referenceNumber, $source, $partnerExtId, $userId);
    if (!$ticketStmt->execute()) {
        $ticketStmt->close();
        throw new Exception('Unable to create ticket.');
    }
    $ticketId = (int) $conn->insert_id;
    $ticketStmt->close();

    if ($ticketId <= 0) {
        throw new Exception('Ticket row was not created correctly.');
    }

    $metaJson = json_encode([
        'source' => $source,
        'created_via' => 'branch_form',
    ], JSON_UNESCAPED_SLASHES);

    $infoSql = "INSERT INTO {$schema}.ticket_info (
                    ticket_number,
                    ticket_type_id,
                    reason,
                    transfer_datetime,
                    ref_no,
                    wrong_biller_id,
                    biller_name,
                    account_no,
                    account_name,
                    payment_branch_id,
                    payment_branch_name,
                    amount,
                    type_of_request,
                    meta
                ) VALUES (?, NULLIF(?, 0), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $infoStmt = $conn->prepare($infoSql);
    if (!$infoStmt) {
        throw new Exception('Unable to prepare ticket info insert statement.');
    }

    $infoStmt->bind_param(
        'sisssssssssdss',
        $ticketNumber,
        $ticketTypeId,
        $reason,
        $transferDatetime,
        $refNo,
        $subbillerExtId,
        $billerName,
        $accountNo,
        $accountName,
        $paymentBranchId,
        $paymentBranchName,
        $amount,
        $typeOfRequest,
        $metaJson
    );

    if (!$infoStmt->execute()) {
        $infoStmt->close();
        throw new Exception('Unable to insert ticket details.');
    }
    $infoStmt->close();

    if ($typeOfRequest === 'WRONG BILLER') {
        $wbSql = "INSERT INTO {$schema}.ticket_info_wrongbiller (ticket_number, correct_biller_id, correct_biller_name) VALUES (?, ?, ?)";
        $wbStmt = $conn->prepare($wbSql);
        if (!$wbStmt) {
            throw new Exception('Unable to prepare wrong biller insert.');
        }

        $wbStmt->bind_param('sss', $ticketNumber, $correctBillerId, $correctBillerName);
        if (!$wbStmt->execute()) {
            $wbStmt->close();
            throw new Exception('Unable to save wrong biller details.');
        }
        $wbStmt->close();
    }

    if ($typeOfRequest === 'OVERSTATED AMOUNT') {
        $difference = $wrongAmount - $correctAmount;
        $oaSql = "INSERT INTO {$schema}.ticket_info_overstatedamount (ticket_number, wrong_amount, correct_amount, difference) VALUES (?, ?, ?, ?)";
        $oaStmt = $conn->prepare($oaSql);
        if (!$oaStmt) {
            throw new Exception('Unable to prepare overstated amount insert.');
        }

        $oaStmt->bind_param('sddd', $ticketNumber, $wrongAmount, $correctAmount, $difference);
        if (!$oaStmt->execute()) {
            $oaStmt->close();
            throw new Exception('Unable to save overstated amount details.');
        }
        $oaStmt->close();
    }

    if ($typeOfRequest === 'CANCELLED TRANSACTION') {
        $ctSql = "INSERT INTO {$schema}.ticket_info_cancelledtransaction (ticket_number, wrong_amount, correct_amount) VALUES (?, ?, ?)";
        $ctStmt = $conn->prepare($ctSql);
        if (!$ctStmt) {
            throw new Exception('Unable to prepare cancelled transaction insert.');
        }

        $ctStmt->bind_param('sdd', $ticketNumber, $wrongAmount, $correctAmount);
        if (!$ctStmt->execute()) {
            $ctStmt->close();
            throw new Exception('Unable to save cancelled transaction details.');
        }
        $ctStmt->close();
    }

    $firstTrailId = st_insert_trail($conn, $ticketId, 'message', $userId, 'BRANCH', 'VPO', $reason, null);

    $attachments = st_uploads_to_array('attachments');
    foreach ($attachments as $file) {
        st_insert_attachment($conn, $ticketId, $firstTrailId, $userId, $file);
    }

    $conn->commit();
    $conn->autocommit(true);

    st_flash_set('create_ticket', 'success', 'Ticket ' . $ticketNumber . ' created successfully.');
    header('Location: ' . $redirectBack);
    exit;
} catch (Exception $e) {
    $conn->rollback();
    $conn->autocommit(true);
    st_redirect_with_flash('create_ticket', 'danger', $e->getMessage(), $redirectBack);
}
