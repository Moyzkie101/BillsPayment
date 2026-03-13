<?php
// Connect to the database
include '../../../config/config.php';
require '../../../vendor/autoload.php';

// Start the session
session_start();


if (isset($_SESSION['user_type'])) {
    $current_user_email = '';
    if ($_SESSION['user_type'] === 'admin' && isset($_SESSION['admin_email'])) {
        $current_user_email = $_SESSION['admin_email'];
    } elseif ($_SESSION['user_type'] === 'user' && isset($_SESSION['user_email'])) {
        $current_user_email = $_SESSION['user_email'];
    } else {
        // Redirect to login page if user_type is invalid
        header("Location: ../../../index.php");
        session_abort();
        session_destroy();
        exit();
    }
} else {
    // Redirect to login page if user_type is not set
    header("Location: ../../../index.php");
    session_abort();
    session_destroy();
    exit();
}

// get display dropdown menu for partners
if (isset($_POST['action']) && $_POST['action'] === 'get_partner_list') {
    try {
        $partnersQuery = "SELECT partner_name FROM masterdata.partner_masterfile WHERE status = 'ACTIVE' ORDER BY partner_name";
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

// get display dropdown menu for banks based on settlement type dropdown selection
if (isset($_POST['action']) && $_POST['action'] === 'get_bank_list') {
    try {
        $bankQuery = 'SELECT bank_name FROM mldb.bank_table GROUP BY bank_name ORDER BY bank_name';
        $stmt = $conn->prepare($bankQuery);

        $stmt->execute();
        $bankResult = $stmt->get_result();

        $bank = array();
        if ($bankResult && $bankResult->num_rows > 0) {
            while ($row = $bankResult->fetch_assoc()) {
                $bank[] = $row;
            }
        }

        echo json_encode([
            'status' => 'success',
            'data' => $bank
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

// get display dropdown menu for settlement types based on Bank Name dropdown selection
if (isset($_POST['action']) && $_POST['action'] === 'get_settlement_type_list') {
    try {
        $bankName = $_POST['bank_name'] ?? '';

        if ($bankName === '') {
            echo json_encode([
                'status' => 'success',
                'data' => []
            ]);
            exit();
        }

        // Populate settlement types from mldb.bank_table
        $settlementTypeQuery = "SELECT DISTINCT
                                    bt.settled_online_check
                                FROM
                                    mldb.bank_table bt
                                WHERE
                                    bt.used_unused = 'used'
                                    AND bt.settled_online_check IS NOT NULL
                                    AND TRIM(bt.settled_online_check) <> ''";

        if ($bankName !== 'ALL') {
            $settlementTypeQuery .= " AND UPPER(TRIM(bt.bank_name)) = UPPER(TRIM(?))";
        }

        $settlementTypeQuery .= ' ORDER BY bt.settled_online_check';
        $stmt = $conn->prepare($settlementTypeQuery);

        if (!$stmt) {
            throw new Exception('Prepare failed: ' . $conn->error);
        }

        if ($bankName !== 'ALL') {
            $stmt->bind_param('s', $bankName);
        }

        $stmt->execute();
        $settlementTypeResult = $stmt->get_result();

        $settlementTypes = array();
        if ($settlementTypeResult && $settlementTypeResult->num_rows > 0) {
            while ($row = $settlementTypeResult->fetch_assoc()) {
                $settlementTypes[] = $row;
            }
        }

        $stmt->close();

        echo json_encode([
            'status' => 'success',
            'data' => $settlementTypes
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

// get next CAD number based on bank abbreviation + selected date (YYYY-MM)
if (isset($_POST['action']) && $_POST['action'] === 'get_next_cad_no') {
    try {
        $bankName = trim($_POST['bank_name'] ?? '');
        $transactionDateRaw = trim($_POST['transaction_date'] ?? '');

        if ($bankName === '' || $transactionDateRaw === '') {
            echo json_encode(['status' => 'error', 'message' => 'Missing bank or transaction date']);
            exit();
        }

        $abbrQuery = "SELECT bank_abbreviation FROM mldb.bank_table WHERE UPPER(TRIM(bank_name)) = UPPER(TRIM(?)) LIMIT 1";
        $abbrStmt = $conn->prepare($abbrQuery);
        if (!$abbrStmt) {
            throw new Exception('Prepare failed: ' . $conn->error);
        }
        $abbrStmt->bind_param('s', $bankName);
        $abbrStmt->execute();
        $abbrResult = $abbrStmt->get_result();
        $abbrRow = $abbrResult ? $abbrResult->fetch_assoc() : null;
        $abbrStmt->close();

        $abbr = trim($abbrRow['bank_abbreviation'] ?? '');
        if ($abbr === '') {
            throw new Exception('Bank abbreviation not found for selected bank');
        }

        $ts = strtotime($transactionDateRaw);
        if ($ts === false && preg_match('/^\d{4}-\d{2}$/', $transactionDateRaw)) {
            $ts = strtotime($transactionDateRaw . '-01');
        }
        if ($ts === false) {
            throw new Exception('Invalid transaction date');
        }

        $yearMonth = date('Y-m', $ts);
        $dayPart = date('d', $ts);
        $cadNo = $abbr . '-' . $yearMonth . '-000' . $dayPart;

        echo json_encode([
            'status' => 'success',
            'cad_no' => $cadNo,
            'year_month' => $yearMonth,
            'day' => $dayPart
        ]);
        exit();
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit();
    }
}

// update settlement/unsettlement details for selected partner transactions (context menu)
if (isset($_POST['action']) && $_POST['action'] === 'update_settle_status') {
    try {
        $statusRaw = $_POST['status'] ?? '';
        $status = strtoupper(trim($statusRaw));

        $partnerId = $_POST['partner_id'] ?? '';
        $partnerIdKpx = $_POST['partner_id_kpx'] ?? '';
        $partnerIds = $_POST['partner_ids'] ?? ($_POST['partner_ids[]'] ?? []);
        $partnerIdKpxs = $_POST['partner_id_kpxs'] ?? ($_POST['partner_id_kpxs[]'] ?? []);
        $startDate = $_POST['startDate'] ?? '';
        $endDate = $_POST['endDate'] ?? '';

        $settlementDate = $_POST['settlement_date'] ?? '';
        $cadNo = trim($_POST['cad_no'] ?? '');
        $rfpNo = trim($_POST['rfp_no'] ?? '');

        $hasSingle = ($partnerId !== '' || $partnerIdKpx !== '');
        $hasArray = (!empty($partnerIds) || !empty($partnerIdKpxs));
        if (($status !== 'SETTLE' && $status !== 'UNSETTLE') || (!$hasSingle && !$hasArray)) {
            echo json_encode(['status' => 'error', 'message' => 'Missing parameters']);
            exit();
        }

        if ($status === 'SETTLE') {
            if ($settlementDate === '' || $cadNo === '' || $rfpNo === '') {
                echo json_encode(['status' => 'error', 'message' => 'Settlement Date, CAD No, and RFP No are required']);
                exit();
            }
        }

        $clauses = [];
        $whereTypes = '';
        $whereParams = [];

        if (!empty($partnerIds) && is_array($partnerIds)) {
            $clean = array_values(array_filter($partnerIds, function ($v) { return trim((string)$v) !== ''; }));
            if (!empty($clean)) {
                $placeholders = implode(',', array_fill(0, count($clean), '?'));
                $clauses[] = "NULLIF(TRIM(partner_id),'') IN ($placeholders)";
                $whereTypes .= str_repeat('s', count($clean));
                foreach ($clean as $value) {
                    $whereParams[] = $value;
                }
            }
        }

        if (!empty($partnerIdKpxs) && is_array($partnerIdKpxs)) {
            $clean = array_values(array_filter($partnerIdKpxs, function ($v) { return trim((string)$v) !== ''; }));
            if (!empty($clean)) {
                $placeholders = implode(',', array_fill(0, count($clean), '?'));
                $clauses[] = "NULLIF(TRIM(partner_id_kpx),'') IN ($placeholders)";
                $whereTypes .= str_repeat('s', count($clean));
                foreach ($clean as $value) {
                    $whereParams[] = $value;
                }
            }
        }

        if (empty($clauses) && ($partnerId !== '' || $partnerIdKpx !== '')) {
            $clauses[] = "NULLIF(TRIM(partner_id),'') = ?";
            $whereTypes .= 's';
            $whereParams[] = $partnerId;

            $clauses[] = "NULLIF(TRIM(partner_id_kpx),'') = ?";
            $whereTypes .= 's';
            $whereParams[] = $partnerIdKpx;
        }

        if (empty($clauses)) {
            throw new Exception('No partner identifiers provided');
        }

        if ($status === 'SETTLE') {
            $sql = "UPDATE mldb.billspayment_transaction
                    SET settlement_date = ?, cad_no = ?, rfp_no = ?, settle_unsettle = 'Settle'
                    WHERE (" . implode(' OR ', $clauses) . ")";
            $types = 'sss' . $whereTypes;
            $params = [$settlementDate, $cadNo, $rfpNo];
        } else {
            $sql = "UPDATE mldb.billspayment_transaction
                    SET settlement_date = NULL, cad_no = NULL, rfp_no = NULL, settle_unsettle = 'Unsettle'
                    WHERE (" . implode(' OR ', $clauses) . ")";
            $types = $whereTypes;
            $params = [];
        }

        $params = array_merge($params, $whereParams);

        if ($startDate !== '' && $endDate !== '') {
            $sql .= " AND DATE(datetime) BETWEEN ? AND ?";
            $types .= 'ss';
            $params[] = $startDate;
            $params[] = $endDate;
        }

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception('Prepare failed: ' . $conn->error);
        }

        if ($types !== '') {
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        echo json_encode(['status' => 'success', 'updated' => $affected]);
        exit();
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit();
    }
}

// get data table results based on filter dropdown selection
if (isset($_POST['action']) && $_POST['action'] === 'generate_report') {
    $partner = $_POST['partner'] ?? '';
    $settlementType = $_POST['settlementType'] ?? '';
    $bankName = $_POST['bankName'] ?? '';
    $filterType = $_POST['filterType'] ?? '';
    $startDate = $_POST['startDate'] ?? '';
    $endDate = $_POST['endDate'] ?? '';

    if (empty($filterType) || empty($startDate)) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Missing required filters.',
            'data' => []
        ]);
        exit();
    }

    $transactionDateCondition = '';
    $settlementDateCondition = '';
    $dateTypesPerCte = '';
    $dateParamsPerCte = [];

    if ($filterType === 'daily') {
        $transactionDateCondition = 'DATE(mbt.datetime) = ?';
        $settlementDateCondition = 'DATE(msabt.posting_date) = ?';
        $dateTypesPerCte = 's';
        $dateParamsPerCte = [$startDate];
    } elseif ($filterType === 'date-range') {
        $rangeEnd = $endDate !== '' ? $endDate : $startDate;
        $transactionDateCondition = 'DATE(mbt.datetime) BETWEEN ? AND ?';
        $settlementDateCondition = 'DATE(msabt.posting_date) BETWEEN ? AND ?';
        $dateTypesPerCte = 'ss';
        $dateParamsPerCte = [$startDate, $rangeEnd];
    } elseif ($filterType === 'monthly') {
        $startMonth = $startDate . '-01';
        $endMonth = date('Y-m-t', strtotime($startDate . '-01'));
        $transactionDateCondition = 'DATE(mbt.datetime) BETWEEN ? AND ?';
        $settlementDateCondition = 'DATE(msabt.posting_date) BETWEEN ? AND ?';
        $dateTypesPerCte = 'ss';
        $dateParamsPerCte = [$startMonth, $endMonth];
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid time frame selected.',
            'data' => []
        ]);
        exit();
    }

    $partnerListConditions = [];
    $partnerListTypes = '';
    $partnerListParams = [];
    $partnerBankJoin = '';

    // If a specific settlement type selected, filter by it.
    // If 'ALL' (or empty), derive the active settlement types from mldb.bank_table where used_unused = 'used'
    if (!empty($settlementType) && strtoupper($settlementType) !== 'ALL') {
        $partnerListConditions[] = 'mpm.settled_online_check = ?';
        $partnerListTypes .= 's';
        $partnerListParams[] = $settlementType;
    } else {
        // fetch available settlement types marked as 'used' in bank_table
        try {
            $typesQuery = "SELECT DISTINCT settled_online_check FROM mldb.bank_table WHERE used_unused = 'used' AND settled_online_check IS NOT NULL AND TRIM(settled_online_check) <> ''";
            $typesStmt = null;
            if (!empty($bankName) && strtoupper($bankName) !== 'ALL') {
                $typesQuery .= " AND UPPER(TRIM(bank_name)) = UPPER(TRIM(?))";
                $typesStmt = $conn->prepare($typesQuery);
                if ($typesStmt) {
                    $typesStmt->bind_param('s', $bankName);
                }
            } else {
                $typesStmt = $conn->prepare($typesQuery);
            }

            $availableTypes = [];
            if ($typesStmt) {
                $typesStmt->execute();
                $typesResult = $typesStmt->get_result();
                if ($typesResult && $typesResult->num_rows > 0) {
                    while ($r = $typesResult->fetch_assoc()) {
                        $availableTypes[] = $r['settled_online_check'];
                    }
                }
                $typesStmt->close();
            }

            if (!empty($availableTypes)) {
                // add IN clause for the available settlement types
                $placeholders = implode(',', array_fill(0, count($availableTypes), '?'));
                $partnerListConditions[] = 'mpm.settled_online_check IN (' . $placeholders . ')';
                $partnerListTypes .= str_repeat('s', count($availableTypes));
                foreach ($availableTypes as $t) {
                    $partnerListParams[] = $t;
                }
            }
        } catch (Exception $e) {
            // if something goes wrong, fall back to no settlement-type filter
        }
    }

    if (!empty($bankName) && strtoupper($bankName) !== 'ALL') {
        // join partner_bank and match directly to ensure partner_name_list only contains affiliated partners
        $partnerBankJoin = " LEFT JOIN mldb.partner_bank pb ON pb.partner_id = mpm.partner_id ";
        $partnerListConditions[] = '(
            UPPER(TRIM(mpm.bank)) = UPPER(TRIM(?))
            OR UPPER(TRIM(pb.bank)) = UPPER(TRIM(?))
        )';
        $partnerListTypes .= 'ss';
        $partnerListParams[] = $bankName;
        $partnerListParams[] = $bankName;
    }

    if (!empty($partner) && strtoupper($partner) !== 'ALL') {
        $partnerListConditions[] = 'UPPER(TRIM(mpm.partner_name)) = UPPER(TRIM(?))';
        $partnerListTypes .= 's';
        $partnerListParams[] = $partner;
    }

    $partnerListWhereClause = '';
    if (!empty($partnerListConditions)) {
        $partnerListWhereClause = ' AND ' . implode(' AND ', $partnerListConditions);
    }

    // The query uses date placeholders in four places: summary_vol, adjustment_vol, transaction_data, and principal_adjustment_data
    $types = $partnerListTypes . str_repeat($dateTypesPerCte, 4);
    $params = array_merge($partnerListParams, $dateParamsPerCte, $dateParamsPerCte, $dateParamsPerCte, $dateParamsPerCte);

    // Ensure date conditions use the correct table alias inside the summary/adjustment CTEs
    $dateConditionForBT = str_replace('mbt.', 'bt.', $transactionDateCondition);

    $dataQuery = "WITH partner_name_list AS (
                    SELECT
                        mpm.partner_id,
                        mpm.partner_id_kpx,
                        mpm.partner_name,
                        mpm.partner_accName,
                        mpm.bank_accNumber,
                        mpm.bank AS bank_name,
                        mpm.settled_online_check,
                        mpm.charge_to,
                        mpm.charge_sched
                    FROM masterdata.partner_masterfile mpm
                    $partnerBankJoin
                    WHERE mpm.status = 'ACTIVE'
                    $partnerListWhereClause
                ),
                summary_vol AS (
                    SELECT
                        CASE
                            WHEN bt.partner_id IS NOT NULL THEN bt.partner_id
                            WHEN bt.partner_id_kpx IS NOT NULL THEN bt.partner_id_kpx
                            ELSE CONCAT('temp_', CASE WHEN bt.sub_billers_name IN ('MYLORA CORPORATION', 'JUNANS MARKETING') THEN bt.sub_billers_name ELSE bt.partner_name END)
                        END COLLATE utf8mb4_general_ci AS partner_key,
                        CASE
                            WHEN bt.sub_billers_name IN ('MYLORA CORPORATION', 'JUNANS MARKETING') THEN bt.sub_billers_name
                            ELSE bt.partner_name
                        END AS partner_name,
                        MAX(bt.sub_billers_name) AS sub_billers_name,
                        COUNT(*) AS vol1,
                        SUM(bt.amount_paid) AS principal1,
                        SUM(bt.charge_to_partner + bt.charge_to_customer) AS charge1
                    FROM mldb.billspayment_transaction AS bt
                    WHERE $dateConditionForBT
                        AND bt.status IS NULL
                        AND NOT bt.branch_id IN ('1','2','4937','4938','4962','4987','4993','4944')
                    GROUP BY
                        CASE
                            WHEN bt.partner_id IS NOT NULL THEN bt.partner_id
                            WHEN bt.partner_id_kpx IS NOT NULL THEN bt.partner_id_kpx
                            ELSE CONCAT('temp_', CASE WHEN bt.sub_billers_name IN ('MYLORA CORPORATION', 'JUNANS MARKETING') THEN bt.sub_billers_name ELSE bt.partner_name END)
                        END COLLATE utf8mb4_general_ci,
                        CASE
                            WHEN bt.sub_billers_name IN ('MYLORA CORPORATION', 'JUNANS MARKETING') THEN bt.sub_billers_name
                            ELSE bt.partner_name
                        END
                ),
                adjustment_vol AS (
                    SELECT
                        CASE
                            WHEN bt.partner_id IS NOT NULL THEN bt.partner_id
                            WHEN bt.partner_id_kpx IS NOT NULL THEN bt.partner_id_kpx
                            ELSE CONCAT('temp_', CASE WHEN bt.sub_billers_name IN ('MYLORA CORPORATION', 'JUNANS MARKETING') THEN bt.sub_billers_name ELSE bt.partner_name END)
                        END COLLATE utf8mb4_general_ci AS partner_key,
                        CASE
                            WHEN bt.sub_billers_name IN ('MYLORA CORPORATION', 'JUNANS MARKETING') THEN bt.sub_billers_name
                            ELSE bt.partner_name
                        END AS partner_name,
                        MAX(bt.sub_billers_name) AS sub_billers_name,
                        COUNT(*) AS vol2,
                        SUM(bt.amount_paid) AS principal2,
                        SUM(bt.charge_to_partner + bt.charge_to_customer) AS charge2
                    FROM mldb.billspayment_transaction AS bt
                    WHERE $dateConditionForBT
                        AND bt.status = '*'
                        AND NOT bt.branch_id IN ('1','2','4937','4938','4962','4987','4993','4944')
                    GROUP BY
                        CASE
                            WHEN bt.partner_id IS NOT NULL THEN bt.partner_id
                            WHEN bt.partner_id_kpx IS NOT NULL THEN bt.partner_id_kpx
                            ELSE CONCAT('temp_', CASE WHEN bt.sub_billers_name IN ('MYLORA CORPORATION', 'JUNANS MARKETING') THEN bt.sub_billers_name ELSE bt.partner_name END)
                        END COLLATE utf8mb4_general_ci,
                        CASE
                            WHEN bt.sub_billers_name IN ('MYLORA CORPORATION', 'JUNANS MARKETING') THEN bt.sub_billers_name
                            ELSE bt.partner_name
                        END
                ),
                transaction_data AS (
                    SELECT
                        CASE
                            WHEN UPPER(TRIM(COALESCE(mbt.sub_billers_name, ''))) IN ('MYLORA CORPORATION', 'JUNANS MARKETING')
                                THEN mbt.sub_billers_name
                            WHEN UPPER(TRIM(COALESCE(mbt.partner_name, ''))) = 'SECURITY BANK'
                                AND (mbt.sub_billers_name IS NULL OR TRIM(mbt.sub_billers_name) = '')
                                THEN mbt.partner_name
                            ELSE mbt.partner_name
                        END AS owner_name,
                        SUM(CASE WHEN mbt.settle_unsettle IS NULL OR TRIM(mbt.settle_unsettle) = '' OR UPPER(TRIM(mbt.settle_unsettle)) = 'UNSETTLE' THEN 1 ELSE 0 END) AS unsettled_count,
                        COUNT(*) AS volume_count,
                        SUM(mbt.amount_paid) AS principal_amount_paid,
                        SUM(mbt.charge_to_customer + mbt.charge_to_partner) AS charges
                    FROM mldb.billspayment_transaction mbt
                    LEFT JOIN mldb.settle_adjustment_branch_transaction msabt
                        ON (
                            NULLIF(TRIM(mbt.partner_id), '') COLLATE utf8mb4_0900_ai_ci = NULLIF(TRIM(msabt.partner_id), '') COLLATE utf8mb4_0900_ai_ci
                            OR
                            NULLIF(TRIM(mbt.partner_id_kpx), '') COLLATE utf8mb4_0900_ai_ci = NULLIF(TRIM(msabt.partner_id_kpx), '') COLLATE utf8mb4_0900_ai_ci
                        )
                        AND DATE(mbt.settlement_date) = DATE(msabt.posting_date)
                        AND NULLIF(TRIM(mbt.reference_no), '') COLLATE utf8mb4_0900_ai_ci = NULLIF(TRIM(msabt.reference_no), '') COLLATE utf8mb4_0900_ai_ci
                    WHERE mbt.branch_id NOT IN ('1','2','4937','4938','4962','4987','4993','4944')
                        AND msabt.reason_note IS NULL
                        AND $transactionDateCondition 
                    GROUP BY owner_name
                ),
                principal_adjustment_data AS (
                    SELECT
                        CASE
                            WHEN UPPER(TRIM(COALESCE(msabt.partner_name, ''))) IN ('MYLORA CORPORATION', 'JUNANS MARKETING')
                                THEN msabt.partner_name
                            WHEN UPPER(TRIM(COALESCE(msabt.partner_name, ''))) = 'SECURITY BANK'
                                THEN msabt.partner_name
                            ELSE msabt.partner_name
                        END AS owner_name,
                        SUM(
                            CASE
                                WHEN LOWER(TRIM(msabt.reason_note)) = 'late-posting'
                                    THEN COALESCE(msabt.prev_amount_paid, 0)
                                WHEN LOWER(TRIM(msabt.reason_note)) = 'wrong-amount'
                                    THEN COALESCE(msabt.edited_amount_paid, 0)
                                ELSE 0
                            END
                        ) AS principal_adjustment,
                        SUM(
                            CASE
                                WHEN LOWER(TRIM(msabt.reason_note)) = 'late-posting'
                                    THEN COALESCE(msabt.prev_charge_to_customer, 0) + COALESCE(msabt.prev_charge_to_partner, 0)
                                WHEN LOWER(TRIM(msabt.reason_note)) = 'wrong-amount'
                                    THEN COALESCE(msabt.edited_charge_to_customer, 0) + COALESCE(msabt.edited_charge_to_partner, 0)
                                ELSE 0
                            END
                        ) AS charges
                    FROM mldb.settle_adjustment_branch_transaction msabt
                    WHERE $settlementDateCondition
                    GROUP BY owner_name
                )
                SELECT
                    pml.partner_id,
                    pml.partner_id_kpx,
                    pml.partner_name,
                    COALESCE(NULLIF(TRIM(td.owner_name), ''), pml.partner_name) AS partner_name_raw,
                    pml.partner_accName,
                    pml.bank_accNumber,
                    pml.bank_name,
                    pml.settled_online_check,
                    pml.charge_to,
                    pml.charge_sched,
                    SUM(COALESCE(td.unsettled_count, 0)) AS has_unsettled,
                    SUM(COALESCE(td.volume_count, 0)) AS volume_count,
                    SUM(COALESCE(td.principal_amount_paid, 0)) AS principal_amount_paid,
                    SUM(COALESCE(td.charges, 0)) AS charges,
                    (COALESCE(SUM(pad.principal_adjustment), 0) - COALESCE(SUM(pad.charges), 0)) AS principal_adjustment,
                    SUM(COALESCE(sv.vol1, 0)) AS summary_vol,
                    SUM(COALESCE(sv.principal1, 0)) AS summary_principal,
                    SUM(COALESCE(sv.charge1, 0)) AS summary_charges,
                    SUM(COALESCE(av.vol2, 0)) AS adjustment_vol,
                    SUM(COALESCE(ABS(av.principal2), 0)) AS adjustment_principal,
                    SUM(COALESCE(ABS(av.charge2), 0)) AS adjustment_charges,
                    (SUM(COALESCE(sv.vol1, 0)) - SUM(COALESCE(av.vol2, 0))) AS net_vol,
                    (SUM(COALESCE(sv.vol1, 0)) - SUM(COALESCE(av.vol2, 0))) AS net_total_transaction,
                    (SUM(COALESCE(sv.principal1, 0)) - SUM(COALESCE(ABS(av.principal2), 0))) AS net_principal,
                    (SUM(COALESCE(sv.charge1, 0)) - SUM(COALESCE(ABS(av.charge2), 0))) AS net_charges,
                    CASE
                        WHEN (COALESCE(SUM(pad.principal_adjustment), 0) - COALESCE(SUM(pad.charges), 0)) = 0
                            THEN SUM(COALESCE(td.principal_amount_paid, 0))
                        ELSE COALESCE((SUM(COALESCE(td.principal_amount_paid, 0)) - SUM(COALESCE(td.charges, 0))) + (COALESCE(SUM(pad.principal_adjustment), 0) - COALESCE(SUM(pad.charges), 0)), 0)
                    END AS amount_for_settlement
                FROM partner_name_list pml
                LEFT JOIN transaction_data td
                    ON NULLIF(TRIM(td.owner_name), '') COLLATE utf8mb4_0900_ai_ci = NULLIF(TRIM(pml.partner_name), '') COLLATE utf8mb4_0900_ai_ci
                LEFT JOIN principal_adjustment_data pad
                    ON NULLIF(TRIM(pad.owner_name), '') COLLATE utf8mb4_0900_ai_ci = NULLIF(TRIM(pml.partner_name), '') COLLATE utf8mb4_0900_ai_ci
                LEFT JOIN summary_vol sv
                    ON NULLIF(TRIM(sv.partner_name), '') COLLATE utf8mb4_0900_ai_ci = NULLIF(TRIM(pml.partner_name), '') COLLATE utf8mb4_0900_ai_ci
                LEFT JOIN adjustment_vol av
                    ON NULLIF(TRIM(av.partner_name), '') COLLATE utf8mb4_0900_ai_ci = NULLIF(TRIM(pml.partner_name), '') COLLATE utf8mb4_0900_ai_ci
                GROUP BY
                    pml.partner_id,
                    pml.partner_id_kpx,
                    pml.partner_name,
                    pml.partner_accName,
                    pml.bank_accNumber,
                    pml.bank_name,
                    pml.settled_online_check,
                    pml.charge_to,
                    pml.charge_sched,
                    COALESCE(NULLIF(TRIM(td.owner_name), ''), pml.partner_name)
                ORDER BY pml.partner_name";

    try {
        $stmt = $conn->prepare($dataQuery);
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
            'data' => $rows
        ]);
    } catch (Exception $e) {
        error_log('Settlement per bank generate_report error: ' . $e->getMessage());
        echo json_encode([
            'status' => 'error',
            'message' => 'Database error: ' . $e->getMessage(),
            'data' => []
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
    <title>Settlement Per Bank | <?php if($_SESSION['user_type'] === 'admin' || $_SESSION['user_type'] === 'user') echo ucfirst($_SESSION['user_type']); else echo "Guest";?></title>
    <!-- custom CSS file link  -->
    <link rel="stylesheet" href="../../../assets/css/templates/style.css?v=<?php echo time(); ?>">
    <script src="https://kit.fontawesome.com/30b908cc5a.js" crossorigin="anonymous"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="../../../assets/js/sweetalert2.all.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>

    <link rel="icon" href="../../../images/MLW logo.png" type="image/png">
    <style>
        #tableContainer {
            max-height: 745px;
            overflow-y: auto;
            overflow-x: auto;
            position: relative;
        }

        #transactionReportTable thead.sticky-top th {
            position: sticky;
            top: 0;
            z-index: 4;
            background-color: #f8f9fa;
        }

        #transactionReportTable tfoot.sticky-bottom th {
            position: sticky;
            bottom: 0;
            z-index: 4;
            background-color: #212529;
            color: #fff;
        }

        #loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.25);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 2000;
        }
    </style>
</head>
<body>
    <div class="main-container">
        <?php include '../../../templates/header_ui.php'; ?>
        <!-- Show and Hide Side Nav Menu -->
        <?php include '../../../templates/sidebar.php'; ?>
        <div id="loading-overlay">
            <div class="spinner-border text-danger" role="status" aria-hidden="true"></div>
        </div>
        <div class="bp-section-header" role="region" aria-label="Page title">
            <div class="bp-section-title">
                <i class="fa-solid fa-layer-group" aria-hidden="true"></i>
                <div>
                    <h2>Settlement Per Bank</h2>
                    <!-- <p class="bp-section-sub">Sample Description</p> -->
                </div>
            </div>
        </div>
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-18">
                    <div class="card">
                        <div class="card-header">
                            <div class="row g-2 align-items-end">
                                <!-- Partner List -->
                                <div class="col-md-2 col-sm-6">
                                    <label class="form-label">Partners Name:</label>
                                    <select id="partnerlistDropdown" class="form-select select2" aria-label="Select Partner" name="partnerlist" data-placeholder="Search or select a Partner..." required>
                                        <option value="">Select Partner</option>
                                        <option value="ALL">ALL</option>
                                        <!-- options will be populated by JS -->
                                    </select>
                                </div>

                                <!-- Bank Name -->
                                <div class="col-md-2 col-sm-6">
                                    <label class="form-label">Bank Name:</label>
                                    <select class="form-select" name="bankName" required>
                                        <option value="">Select Bank</option>
                                        <!-- <option value="ALL">ALL</option> -->
                                        <!-- options will be populated by JS -->
                                    </select>
                                </div>

                                <!-- Settlement Type -->
                                <div class="col-md-2 col-sm-6">
                                    <label class="form-label">Settlement Type:</label>
                                    <select class="form-select" name="settlementType" required>
                                        <option value="">Select Settlement Type</option>
                                        <option value="ALL">ALL</option>
                                        <!-- options will be populated by JS -->
                                    </select>
                                </div>

                                <!-- Charge By -->
                                <!-- <div class="col-md-2 col-sm-6">
                                    <label class="form-label">Charge By:</label>
                                    <select class="form-select" name="chargeBy" required>
                                        <option value="">Select Charge By</option>
                                        <option value="ALL">ALL</option>
                                        <option value="CUSTOMER">CUSTOMER</option>
                                        <option value="PARTNER">PARTNER</option>
                                        <option value="BOTH">BOTH</option>
                                    </select>
                                </div> -->

                                <!-- Time Frame -->
                                <div class="col-md-2 col-sm-6">
                                    <label class="form-label">Time Frame:</label>
                                    <select class="form-select" name="filterType" required>
                                        <option value="">Select Time Frame</option>
                                        <option value="daily">Daily</option>
                                        <option value="monthly">Monthly</option>
                                        <option value="date-range">Date Range</option>
                                        <!-- <option value="monthly-range">Monthly Range</option>
                                        <option value="yearly">Per Year</option>
                                        <option value="yearly-range">Yearly Range</option> -->
                                    </select>
                                </div>

                                <!-- Date Range based on selected Time Frame -->
                                <div class="col-md-2" style="display: none;">
                                    <label class="form-label mb-0 transaction-date-label">Transaction Date</label>
                                    <br>
                                    <label class="form-label start-date-label">Start Date:</label>
                                    <input type="date" class="form-control" name="startDate" required>
                                </div>
                                <div class="col-md-2" style="display: none;">
                                    <label class="form-label end-date-label">End Date:</label>
                                    <input type="date" class="form-control" name="endDate" required>
                                </div>

                                <!-- Settlement Date -->
                                <!-- <div class="col-md-2">
                                    <label class="form-label">Settlement Date:</label>
                                    <input type="date" class="form-control" name="settlementDate" required>
                                </div> -->

                                <!-- Action Button -->
                                <div class="col-md-1 col-sm-6">
                                    <div class="col-md-3 d-flex align-items-end">
                                        <button type="button" class="btn btn-danger" id="generateReport">Generate</button>
                                        <button type="button" class="btn btn-secondary ms-2" id="bulkActionBtn" style="display:none;">Bulk Action</button>
                                    </div>
                                    
                                </div>

                                <!-- Export + Debug Buttons (inline) -->
                                <!-- <div class="col-md-1 col-sm-6">
                                    <div class="d-flex align-items-end" style="gap:8px; white-space:nowrap;">
                                        <button class="btn btn-danger" id="exportButton" type="button" style="display:none;">Export to</button>
                                        <button class="btn btn-warning" id="debugButton" type="button" style="display:none;">Debug Report</button>
                                    </div>
                                </div> -->
                            </div>
                        </div>
                        <div class="container-fluid">
                            <div class="day-shortcut-container mt-2" id="dayFilterContainer" style="display: none;">
                                <div class="day-buttons-label">Filter by Day:</div>
                                <div class="day-buttons-wrapper" id="dayButtonsWrapper">
                                    <button class="day-button day-button-all day-button-active" id="allDaysButton">All</button>
                                </div>
                            </div>
                            <div class="day-shortcut-container mt-2"  id="monthFilterContainer" style="display: none;">
                                <div class="day-buttons-label">Filter by Month:</div>
                                <div class="day-buttons-wrapper" id="dayButtonsWrapper">
                                    <button class="day-button day-button-all day-button-active" id="allDaysButton">All</button>
                                </div>
                            </div>
                            <div class="day-shortcut-container mt-2"  id="yearFilterContainer" style="display: none;">
                                <div class="day-buttons-label">Filter by Year:</div>
                                <div class="day-buttons-wrapper" id="dayButtonsWrapper">
                                    <button class="day-button day-button-all day-button-active" id="allDaysButton">All</button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="card-body">
                            <div class="table-responsive" id="tableContainer" style="overflow-y: auto;">
                                <table id="transactionReportTable" class="table table-bordered table-hover table-striped">
                                    <thead class="table-light sticky-top">
                                        <tr>
                                            <th rowspan="2" class='text-truncate text-center align-middle'><input id="selectAllRows" type="checkbox" aria-label="Select all" /></th>
                                            <th rowspan="2" class='text-truncate text-center align-middle'>No.</th>
                                            <th rowspan="2" class='text-truncate text-center align-middle'>Partner Name</th>
                                            <th rowspan="2" class='text-truncate text-center align-middle'>Account Name</th>
                                            <th rowspan="2" class='text-truncate text-center align-middle'>Account Number</th>
                                            <th colspan="3" class='text-truncate text-center align-middle'>Net Total Transaction</th>
                                            <th class='text-truncate text-center align-middle'>Principal Adjustment</th>
                                            <th rowspan="2" class='text-truncate text-center align-middle'>Amount for Settlement</th>
                                            <th rowspan="2" class='text-truncate text-center align-middle'>Status</th>
                                        </tr>
                                        <tr>
                                            <!-- Column header for Net -->
                                            <th class='text-center'>Vol.</th>
                                            <th class='text-center'>Principal</th>
                                            <th class='text-center'>Charge</th>
                                            <th class='text-center'>Add / Less</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <!-- Data will be populated via JavaScript -->
                                        <tr>
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
                                    <tfoot class="sticky-bottom table-dark">
                                        <tr>
                                            <th colspan="5" class="text-end">Total : </th>
                                            <th class="text-center" id="totalnetvolume">0</th>
                                            <th class="text-end" id="totalnetprincipal">0.00</th>
                                            <th class="text-end" id="totalnetcharge">0.00</th>
                                            <th class="text-end" id="totalprincipaladjustment">0.00</th>
                                            <th class="text-end" id="totalamountforsettlement">0.00</th>
                                            <th class="text-center"></th>
                                            <!-- <th class="text-end">0.00</th>
                                            <th class="text-end">0.00</th>
                                            <th class="text-end">0.00</th>
                                            <th class="text-end">0.00</th>
                                            <th class="text-end">0.00</th>
                                            <th class="text-end">0.00</th>
                                            <th class="text-end">0.00</th>
                                            <th class="text-end">0.00</th> -->
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
<?php include '../../../templates/footer.php'; ?>

<!-- PARTNER DROPDOWN SELECTION HANDLER -->
<script>
    $(document).ready(function() {
        const $partner = $('#partnerlistDropdown');
        const $settlementType = $('select[name="settlementType"]');
        const $bankName = $('select[name="bankName"]');
        const $filterType = $('select[name="filterType"]');
        const $startDate = $('input[name="startDate"]');
        const $endDate = $('input[name="endDate"]');
        const $startWrap = $startDate.closest('.col-md-2');
        const $endWrap = $endDate.closest('.col-md-2');
        const $generateReport = $('#generateReport');

        $partner.select2({
            placeholder: 'Search or select a Partner...',
            allowClear: true
        });

        function loadPartners() {
            $.ajax({
                url: '',
                type: 'POST',
                data: { action: 'get_partner_list' },
                success: function(response) {
                    try {
                        const result = JSON.parse(response);
                        if (result.status === 'success') {
                            $partner.find('option:not([value=""],[value="ALL"])').remove();
                            (result.data || []).forEach(partner => {
                                $partner.append(new Option(partner.partner_name, partner.partner_name));
                            });
                            $partner.trigger('change.select2');
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Partner List Error',
                                text: result.message || 'Unable to load partner list.'
                            });
                        }
                    } catch (e) {
                        console.error('Error loading partners:', e, response);
                        Swal.fire({
                            icon: 'error',
                            title: 'Invalid Response',
                            text: 'Failed to process partner list response.'
                        });
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Partner list request error:', { xhr: xhr, status: status, error: error });
                    Swal.fire({
                        icon: 'error',
                        title: 'Connection Error',
                        text: 'Failed to load partner list. Please try again.'
                    });
                }
            });
        }

        const timeFrameRefs = {
            $filterType,
            $partner,
            $settlementType,
            $bankName,
            $startDate,
            $endDate,
            $startWrap,
            $endWrap,
            $generateReport
        };

        loadPartners();
        window.SettlementPerBankBank.init({
            $bankName,
            onBankSelectionChanged: function() {
                window.SettlementPerBankTimeFrame.toggleGenerateButton(timeFrameRefs);
            }
        });
        window.SettlementPerBankSettlementType.init({
            $bankName,
            $settlementType,
            onSettlementTypeSelectionChanged: function() {
                window.SettlementPerBankTimeFrame.toggleGenerateButton(timeFrameRefs);
            }
        });
        window.SettlementPerBankTimeFrame.configureInputsForFilterType('', timeFrameRefs);
        window.SettlementPerBankTimeFrame.toggleGenerateButton(timeFrameRefs);
        window.SettlementPerBankResults.init(timeFrameRefs);

        $filterType.on('change', function() {
            window.SettlementPerBankTimeFrame.configureInputsForFilterType($(this).val(), timeFrameRefs);
            window.SettlementPerBankTimeFrame.toggleGenerateButton(timeFrameRefs);
        });

        $partner.on('change', function() {
            window.SettlementPerBankTimeFrame.toggleGenerateButton(timeFrameRefs);
        });

        $startDate.add($endDate).on('change', function() {
            window.SettlementPerBankTimeFrame.toggleGenerateButton(timeFrameRefs);
        });
    });

</script>

<!-- CONTEXT MENU FOR TRANSACTION ROWS -->
<style>
    .bp-context-menu {
        position: absolute;
        z-index: 3000;
        background: #fff;
        border: 1px solid rgba(0,0,0,0.15);
        box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        min-width: 180px;
        padding: 6px 0;
        display: none;
    }
    .bp-context-menu .item {
        padding: 8px 12px;
        cursor: pointer;
    }
    .bp-context-menu .item.disabled {
        color: #6c757d;
        cursor: default;
    }
    .bp-context-menu .item:hover:not(.disabled) {
        background: #f8f9fa;
    }
    /* show pointer when hovering over selectable data rows */
    #transactionReportTable tbody tr[data-partner-id],
    #transactionReportTable tbody tr[data-partner-id-kpx] {
        cursor: pointer;
    }
    .row-disabled {
        opacity: 0.6;
    }
    .row-disabled .row-select {
        cursor: not-allowed;
    }
</style>

<div id="bpContextMenu" class="bp-context-menu"></div>

<script>
    (function() {
        const $menu = $('#bpContextMenu');

        function hideMenu() {
            $menu.hide().empty();
        }

        function getTargetRows($row) {
            const $selectedRows = $('#transactionReportTable tbody .row-select:checked').closest('tr');
            if ($selectedRows.length > 0) {
                return $selectedRows;
            }
            return $row;
        }

        function collectIdentifiers($rows) {
            const partnerIds = [];
            const partnerIdKpxs = [];
            $rows.each(function() {
                const $r = $(this);
                const pid = String($r.data('partner-id') || '').trim();
                const pkpx = String($r.data('partner-id-kpx') || '').trim();
                if (pid !== '' && !partnerIds.includes(pid)) {
                    partnerIds.push(pid);
                }
                if (pkpx !== '' && !partnerIdKpxs.includes(pkpx)) {
                    partnerIdKpxs.push(pkpx);
                }
            });
            return { partnerIds, partnerIdKpxs };
        }

        // selection mode: null | 'settle' | 'unsettle'
        let selectionMode = null;

        function getRowMode($row) {
            return (Number($row.data('has-unsettled') || 0) > 0) ? 'unsettle' : 'settle';
        }

        function setSelectionMode(mode) {
            selectionMode = mode;
            updateSelectableRows();
        }

        function updateSelectableRows() {
            $('#transactionReportTable tbody tr').each(function() {
                const $tr = $(this);
                // only affect data rows
                if (!$tr.data('partner-id') && !$tr.data('partner-id-kpx')) return;
                const rowMode = getRowMode($tr);
                const $chk = $tr.find('.row-select');
                if (!selectionMode) {
                    $tr.removeClass('row-disabled');
                    $chk.prop('disabled', false);
                } else if (rowMode !== selectionMode) {
                    $tr.addClass('row-disabled');
                    $chk.prop('disabled', true).prop('checked', false);
                } else {
                    $tr.removeClass('row-disabled');
                    $chk.prop('disabled', false);
                }
            });
            // after updating rows, recalc bulk button
            updateBulkActionButton();
        }

        function updateSelectionModeFromSelected() {
            const $selected = $('#transactionReportTable tbody .row-select:checked').closest('tr');
            if ($selected.length === 0) {
                selectionMode = null;
                updateSelectableRows();
                return;
            }
            const mode = getRowMode($selected.first());
            selectionMode = mode;
            updateSelectableRows();
        }

        function getBankNameForRows($rows) {
            const fromFilter = $('select[name="bankName"]').val() || '';
            if (fromFilter) {
                return fromFilter;
            }

            const uniqueBanks = [];
            $rows.each(function() {
                const bank = String($(this).data('bank-name') || '').trim();
                if (bank && !uniqueBanks.includes(bank)) {
                    uniqueBanks.push(bank);
                }
            });

            if (uniqueBanks.length === 1) {
                return uniqueBanks[0];
            }

            return uniqueBanks.length > 1 ? 'Multiple Banks' : '';
        }

        function formatRfpPrefixFromDate(dateValue) {
            if (!dateValue) {
                return '';
            }
            const date = new Date(dateValue);
            if (Number.isNaN(date.getTime())) {
                return '';
            }
            const month = String(date.getMonth() + 1).padStart(2, '0');
            return `${date.getFullYear()}-${month}-`;
        }

        function loadCadNo(bankName, transactionDate) {
            return $.ajax({
                url: '',
                type: 'POST',
                data: {
                    action: 'get_next_cad_no',
                    bank_name: bankName,
                    transaction_date: transactionDate
                }
            });
        }

        async function openSettleModal($rows) {
            const multiple = $rows.length > 1;
            const partnerName = String($rows.first().data('partner-name') || '');
            const bankName = getBankNameForRows($rows);

            if (!bankName || bankName === 'Multiple Banks') {
                Swal.fire({
                    icon: 'warning',
                    title: 'Bank Name Required',
                    text: 'Please filter or select rows under a single bank before settling.'
                });
                return;
            }

            const partnerHtml = multiple ? '' : `
                <div class="text-start mb-2">
                    <label class="form-label mb-1"><b>Partner Name</b></label>
                    <div class="form-control">${partnerName || ''}</div>
                </div>`;

            await Swal.fire({
                title: 'Settle Transactions',
                width: 650,
                html: `
                    ${partnerHtml}
                    <div class="text-start mb-2">
                        <label class="form-label mb-1"><b>Bank Name</b></label>
                        <div class="form-control" id="settleBankName">${bankName}</div>
                    </div>
                    <div class="text-start mb-2">
                        <label class="form-label mb-1"><b>Settlement Date</b></label>
                        <input type="date" id="settleDate" class="form-control" />
                    </div>
                    <div class="text-start mb-2">
                        <label class="form-label mb-1"><b>CAD No</b></label>
                        <input type="text" id="cadNo" class="form-control" readonly />
                    </div>
                    <div class="text-start mb-0">
                        <label class="form-label mb-1"><b>RFP No</b></label>
                        <div class="input-group">
                            <span class="input-group-text" id="rfpPrefix">YYYY-MM-</span>
                            <input type="text" id="rfpSuffix" class="form-control" placeholder="Manual suffix" />
                        </div>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: 'Settle',
                didOpen: () => {
                    const confirmBtn = Swal.getConfirmButton();
                    const dateInput = document.getElementById('settleDate');
                    const cadInput = document.getElementById('cadNo');
                    const prefixEl = document.getElementById('rfpPrefix');
                    const suffixInput = document.getElementById('rfpSuffix');
                    const filterType = $('select[name="filterType"]').val() || '';
                    const startDateValue = $('input[name="startDate"]').val() || '';
                    const endDateValue = $('input[name="endDate"]').val() || '';
                    const transactionDate = (filterType === 'date-range' && endDateValue)
                        ? endDateValue
                        : (startDateValue || endDateValue);

                    const toggleConfirm = () => {
                        const prefix = prefixEl.textContent || '';
                        const canSubmit = !!(dateInput.value && cadInput.value && prefix !== 'YYYY-MM-' && suffixInput.value.trim() !== '');
                        confirmBtn.disabled = !canSubmit;
                    };

                    confirmBtn.disabled = true;

                    dateInput.addEventListener('change', async function() {
                        const prefix = formatRfpPrefixFromDate(dateInput.value);
                        prefixEl.textContent = prefix || 'YYYY-MM-';
                        toggleConfirm();
                    });

                    suffixInput.addEventListener('input', toggleConfirm);

                    // CAD No is based on selected Transaction Date filter, not Settlement Date.
                    (async function initializeCadNo() {
                        if (!transactionDate) {
                            cadInput.value = '';
                            Swal.showValidationMessage('Transaction Date filter is required before settling.');
                            return;
                        }
                        try {
                            const response = await loadCadNo(bankName, transactionDate);
                            const result = typeof response === 'object' ? response : JSON.parse(response);
                            if (result.status === 'success') {
                                cadInput.value = result.cad_no || '';
                            } else {
                                cadInput.value = '';
                                Swal.showValidationMessage(result.message || 'Failed to generate CAD No');
                            }
                        } catch (err) {
                            cadInput.value = '';
                            Swal.showValidationMessage('Failed to generate CAD No');
                        }
                        toggleConfirm();
                    })();
                },
                preConfirm: () => {
                    const settlementDate = document.getElementById('settleDate').value;
                    const cadNo = document.getElementById('cadNo').value;
                    const rfpPrefix = document.getElementById('rfpPrefix').textContent || '';
                    const rfpSuffix = document.getElementById('rfpSuffix').value.trim();

                    if (!settlementDate || !cadNo || rfpPrefix === 'YYYY-MM-' || !rfpSuffix) {
                        Swal.showValidationMessage('Settlement Date, CAD No, and RFP No are required');
                        return false;
                    }

                    return {
                        settlementDate,
                        cadNo,
                        rfpNo: `${rfpPrefix}${rfpSuffix}`
                    };
                }
            }).then((result) => {
                if (!result.isConfirmed || !result.value) {
                    return;
                }
                const ids = collectIdentifiers($rows);
                performSettleAction(ids.partnerIds, ids.partnerIdKpxs, 'Settle', $rows, {
                    settlementDate: result.value.settlementDate,
                    cadNo: result.value.cadNo,
                    rfpNo: result.value.rfpNo
                });
            });
        }

        function showMenuForRow($row, event) {
            hideMenu();
            const $targetRows = getTargetRows($row);
            const multiple = $targetRows.length > 1;
            const partnerName = String($row.data('partner-name') || '');
            const unsettledCount = $targetRows.filter(function() {
                return Number($(this).data('has-unsettled') || 0) > 0;
            }).length;

            // For multi-select, require same status so only one toggle action appears.
            if (multiple && unsettledCount > 0 && unsettledCount < $targetRows.length) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Mixed Status Selection',
                    text: 'Please select rows with the same status before using context menu.'
                });
                return;
            }

            const toggleAction = unsettledCount > 0 ? 'Settle' : 'Unsettle';

            const items = [];
            if (!multiple) {
                items.push({ label: partnerName || 'Partner', cls: 'disabled' });
            }
            items.push({ label: toggleAction, cls: '', action: toggleAction });

            items.forEach((it) => {
                const $it = $('<div class="item"></div>').text(it.label).addClass(it.cls);
                if (!it.cls) {
                    $it.on('click', function() {
                        hideMenu();

                        if (it.action === 'Settle') {
                            openSettleModal($targetRows);
                            return;
                        }

                        const confirmText = multiple
                            ? `Are you sure you want to mark ${$targetRows.length} partners as Unsettle?`
                            : `Are you sure you want to mark ${partnerName} as Unsettle?`;

                        Swal.fire({
                            title: 'Unsettle',
                            text: confirmText,
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonText: 'Yes'
                        }).then((res) => {
                            if (!res.isConfirmed) {
                                return;
                            }

                            const ids = collectIdentifiers($targetRows);
                            performSettleAction(ids.partnerIds, ids.partnerIdKpxs, 'Unsettle', $targetRows);
                        });
                    });
                }
                $menu.append($it);
            });

            $menu.css({ left: event.pageX + 'px', top: event.pageY + 'px' }).show();
        }

        function performSettleAction(partnerIds, partnerIdKpxs, action, $rowOrRows, settlePayload = null) {
            const startDate = $('input[name="startDate"]').val() || '';
            const endDate = $('input[name="endDate"]').val() || '';
            const data = {
                action: 'update_settle_status',
                status: action,
                startDate: startDate,
                endDate: endDate
            };

            if (action === 'Settle' && settlePayload) {
                data.settlement_date = settlePayload.settlementDate;
                data.cad_no = settlePayload.cadNo;
                data.rfp_no = settlePayload.rfpNo;
            }

            // attach arrays
            for (let i = 0; i < partnerIds.length; i++) {
                data['partner_ids[]'] = data['partner_ids[]'] || [];
                data['partner_ids[]'].push(partnerIds[i]);
            }
            for (let i = 0; i < partnerIdKpxs.length; i++) {
                data['partner_id_kpxs[]'] = data['partner_id_kpxs[]'] || [];
                data['partner_id_kpxs[]'].push(partnerIdKpxs[i]);
            }

            const showOverlay = (action === 'Settle' || action === 'Unsettle');

            $.ajax({
                url: '',
                type: 'POST',
                data: data,
                beforeSend: function() {
                    if (showOverlay) {
                        $('#loading-overlay').css('display', 'flex');
                    }
                },
                complete: function() {
                    if (showOverlay) {
                        $('#loading-overlay').hide();
                    }
                },
                success: function(response) {
                    try {
                        const result = typeof response === 'object' ? response : JSON.parse(response);
                        if (result.status === 'success') {
                            // toggle classes on affected rows
                            if ($rowOrRows && $rowOrRows.length) {
                                const statusIconForSettle = '<i class="fa-solid fa-check text-success" title="Settled"></i>';
                                const statusIconForUnsettle = '<i class="fa-solid fa-xmark text-danger" title="Unsettled"></i>';
                                const newIcon = (action === 'Settle') ? statusIconForSettle : statusIconForUnsettle;

                                $rowOrRows.each(function() {
                                    const $r = $(this);
                                    if (action === 'Settle') {
                                        $r.removeClass('table-danger').addClass('table-success');
                                        $r.data('has-unsettled', 0);
                                        $r.attr('data-has-unsettled', 0);
                                    } else {
                                        $r.removeClass('table-success').addClass('table-danger');
                                        $r.data('has-unsettled', 1);
                                        $r.attr('data-has-unsettled', 1);
                                    }

                                    // update status icon cell (last td)
                                    $r.find('td').last().html(newIcon);

                                    // uncheck rows after action
                                    $r.find('.row-select').prop('checked', false).trigger('change');
                                });
                                $('#selectAllRows').prop('checked', false).trigger('change');
                                // refresh bulk button state
                                updateBulkActionButton();
                            }
                            Swal.fire({ icon: 'success', title: 'Updated', text: `${result.updated || 0} rows updated.` });
                        } else {
                            Swal.fire({ icon: 'error', title: 'Error', text: result.message || 'Failed to update transactions.' });
                        }
                    } catch (e) {
                        Swal.fire({ icon: 'error', title: 'Error', text: 'Invalid server response.' });
                    }
                },
                error: function() {
                    Swal.fire({ icon: 'error', title: 'Error', text: 'Request failed.' });
                }
            });
        }

        // attach contextmenu listener to table rows via delegation
        $(document).on('contextmenu', '#transactionReportTable tbody tr', function(e) {
            const $tr = $(this);
            // ignore header/footer rows without data (they may be section rows)
            if (!$tr.data('partner-id') && !$tr.data('partner-id-kpx')) {
                return;
            }
            e.preventDefault();
            showMenuForRow($tr, e);
        });

        // select-all checkbox handler (enforces selectionMode)
        $(document).on('change', '#selectAllRows', function() {
            const isChecked = $(this).is(':checked');
            const $allCheckboxes = $('#transactionReportTable tbody .row-select');
            if (!isChecked) {
                // uncheck all and reset mode
                $allCheckboxes.prop('checked', false).trigger('change');
                selectionMode = null;
                updateSelectableRows();
                return;
            }

            // if selectionMode not set, ask user which type to select
            if (!selectionMode) {
                Swal.fire({
                    title: 'Select rows to target',
                    text: 'Choose which status to select for bulk operation:',
                    showDenyButton: true,
                    showCancelButton: true,
                    confirmButtonText: 'Select All Settled',
                    denyButtonText: 'Select All Unsettled'
                }).then((res) => {
                    if (res.isConfirmed) {
                        setSelectionMode('settle');
                        $allCheckboxes.filter(function() { return Number($(this).closest('tr').data('has-unsettled') || 0) === 0; }).prop('checked', true).trigger('change');
                    } else if (res.isDenied) {
                        setSelectionMode('unsettle');
                        $allCheckboxes.filter(function() { return Number($(this).closest('tr').data('has-unsettled') || 0) > 0; }).prop('checked', true).trigger('change');
                    } else {
                        // cancelled
                        $('#selectAllRows').prop('checked', false);
                    }
                });
                return;
            }

            // if selectionMode already set, only toggle matching rows
            if (selectionMode === 'settle') {
                $allCheckboxes.filter(function() { return Number($(this).closest('tr').data('has-unsettled') || 0) === 0; }).prop('checked', true).trigger('change');
            } else if (selectionMode === 'unsettle') {
                $allCheckboxes.filter(function() { return Number($(this).closest('tr').data('has-unsettled') || 0) > 0; }).prop('checked', true).trigger('change');
            }
        });

        // row checkbox toggles header
        $(document).on('change', '#transactionReportTable tbody', function(e) {
            const total = $('#transactionReportTable tbody .row-select').length;
            const checked = $('#transactionReportTable tbody .row-select:checked').length;
            $('#selectAllRows').prop('checked', total > 0 && total === checked);
        });

        // enforce selectionMode when individual row checkbox changes
        $(document).on('change', '#transactionReportTable tbody .row-select', function(e) {
            const $chk = $(this);
            const $tr = $chk.closest('tr');
            const rowMode = getRowMode($tr);

            if ($chk.is(':checked')) {
                // trying to select
                if (!selectionMode) {
                    setSelectionMode(rowMode);
                } else if (selectionMode !== rowMode) {
                    // prevent selecting mixed status
                    $chk.prop('checked', false);
                    Swal.fire({ icon: 'warning', title: 'Mixed Status Selection', text: 'You cannot select rows of different statuses.' , timer:1500, showConfirmButton:false});
                    return;
                }
            } else {
                // unchecked: if no selected remain, reset mode
                const anyChecked = $('#transactionReportTable tbody .row-select:checked').length > 0;
                if (!anyChecked) {
                    selectionMode = null;
                    updateSelectableRows();
                }
            }

            // ensure UI updated
            updateBulkActionButton();
        });

        // clicking a row toggles its selection (except when clicking inputs, links, buttons or right-clicking)
        $(document).on('click', '#transactionReportTable tbody tr', function(e) {
            if (e.which === 3) return; // ignore right-click
            // ignore clicks that originated from interactive elements
            if ($(e.target).closest('input, a, button, label, .item, #bpContextMenu, .row-select').length) return;
            const $chk = $(this).find('.row-select');
            if ($chk.length && !$chk.is(':disabled')) {
                $chk.prop('checked', !$chk.prop('checked')).trigger('change');
            }
        });

        // bulk action button manager
        function updateBulkActionButton() {
            const $btn = $('#bulkActionBtn');
            const $selectedRows = $('#transactionReportTable tbody .row-select:checked').closest('tr');
            if ($selectedRows.length === 0) {
                $btn.hide();
                return;
            }

            const unsettledCount = $selectedRows.filter(function() {
                return Number($(this).data('has-unsettled') || 0) > 0;
            }).length;

            if (unsettledCount > 0 && unsettledCount < $selectedRows.length) {
                $btn.show().prop('disabled', true).removeClass('btn-success btn-danger').addClass('btn-secondary').text('Mixed Selection');
                return;
            }

            if (unsettledCount > 0) {
                $btn.show().prop('disabled', false).removeClass('btn-danger btn-secondary').addClass('btn-success').text('Settle');
                return;
            }

            $btn.show().prop('disabled', false).removeClass('btn-success btn-secondary').addClass('btn-danger').text('Unsettle');
        }

        // when a row checkbox changes or select-all toggles, update the bulk button
        $(document).on('change', '#transactionReportTable tbody .row-select', updateBulkActionButton);
        $(document).on('change', '#selectAllRows', updateBulkActionButton);

        // bulk button click behavior; mirrors context menu actions
        $(document).on('click', '#bulkActionBtn', function() {
            const $btn = $(this);
            if ($btn.is(':disabled')) {
                Swal.fire({ icon: 'warning', title: 'Invalid Selection', text: 'Please select rows with the same status.' });
                return;
            }

            const action = $btn.text().trim();
            const $rows = $('#transactionReportTable tbody .row-select:checked').closest('tr');
            if ($rows.length === 0) {
                Swal.fire({ icon: 'warning', title: 'No Rows Selected', text: 'Please select at least one row.' });
                return;
            }

            if (action === 'Settle') {
                openSettleModal($rows);
                return;
            }

            if (action === 'Unsettle') {
                const confirmText = $rows.length > 1 ? `Are you sure you want to mark ${$rows.length} partners as Unsettle?` : `Are you sure you want to mark this partner as Unsettle?`;
                Swal.fire({ title: 'Unsettle', text: confirmText, icon: 'warning', showCancelButton: true, confirmButtonText: 'Yes' }).then((res) => {
                    if (!res.isConfirmed) return;
                    const ids = collectIdentifiers($rows);
                    performSettleAction(ids.partnerIds, ids.partnerIdKpxs, 'Unsettle', $rows);
                });
                return;
            }
        });

        // hide menu on click elsewhere or escape
        $(document).on('click', function(e) {
            if (!$(e.target).closest('#bpContextMenu').length) hideMenu();
        });
        $(document).on('keydown', function(e) { if (e.key === 'Escape') hideMenu(); });
    })();
</script>

<!-- BANK NAME DROPDOWN SELECTION HANDLER -->
<script>
    window.SettlementPerBankBank = {
        init: function(refs) {
            const self = this;
            const $bankName = refs.$bankName;

            self.resetBankOptions($bankName);
            self.loadBankOptions(refs);

            $bankName.on('change', function() {
                if (typeof refs.onBankSelectionChanged === 'function') {
                    refs.onBankSelectionChanged();
                }
            });
        },

        resetBankOptions: function($bankName) {
            $bankName.find('option:not([value=""],[value="ALL"])').remove();
            $bankName.val('');
        },

        loadBankOptions: function(refs) {
            const self = this;
            const $bankName = refs.$bankName;

            self.resetBankOptions($bankName);

            $.ajax({
                url: '',
                type: 'POST',
                data: {
                    action: 'get_bank_list'
                },
                success: function(response) {
                    try {
                        const result = JSON.parse(response);
                        if (result.status === 'success') {
                            (result.data || []).forEach(bank => {
                                $bankName.append(new Option(bank.bank_name, bank.bank_name));
                            });

                            $bankName.val('');
                            if (typeof refs.onBankSelectionChanged === 'function') {
                                refs.onBankSelectionChanged();
                            }
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Bank List Error',
                                text: result.message || 'Unable to load bank list.'
                            });
                        }
                    } catch (e) {
                        console.error('Error loading bank list:', e, response);
                        Swal.fire({
                            icon: 'error',
                            title: 'Invalid Response',
                            text: 'Failed to process bank list response.'
                        });
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Bank list request error:', { xhr: xhr, status: status, error: error });
                    Swal.fire({
                        icon: 'error',
                        title: 'Connection Error',
                        text: 'Failed to load bank list. Please try again.'
                    });
                }
            });
        }
    };

</script>

<!-- SETTLEMENT TYPE DROPDOWN SELECTION HANDLER -->
<script>
    window.SettlementPerBankSettlementType = {
        init: function(refs) {
            const self = this;
            const $bankName = refs.$bankName;
            const $settlementType = refs.$settlementType;

            self.resetSettlementTypeOptions($settlementType);

            $bankName.on('change', function() {
                const selectedBankName = $(this).val();
                self.loadSettlementTypeOptions(selectedBankName, refs);
            });

            $settlementType.on('change', function() {
                if (typeof refs.onSettlementTypeSelectionChanged === 'function') {
                    refs.onSettlementTypeSelectionChanged();
                }
            });
        },

        resetSettlementTypeOptions: function($settlementType) {
            $settlementType.find('option:not([value=""],[value="ALL"])').remove();
            $settlementType.val('');
        },

        loadSettlementTypeOptions: function(bankName, refs) {
            const self = this;
            const $settlementType = refs.$settlementType;

            self.resetSettlementTypeOptions($settlementType);

            if (!bankName) {
                if (typeof refs.onSettlementTypeSelectionChanged === 'function') {
                    refs.onSettlementTypeSelectionChanged();
                }
                return;
            }

            $.ajax({
                url: '',
                type: 'POST',
                data: {
                    action: 'get_settlement_type_list',
                    bank_name: bankName
                },
                success: function(response) {
                    try {
                        const result = JSON.parse(response);
                        if (result.status === 'success') {
                            (result.data || []).forEach(item => {
                                $settlementType.append(new Option(item.settled_online_check, item.settled_online_check));
                            });

                            if (typeof refs.onSettlementTypeSelectionChanged === 'function') {
                                refs.onSettlementTypeSelectionChanged();
                            }
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Settlement Type Error',
                                text: result.message || 'Unable to load settlement types.'
                            });
                        }
                    } catch (e) {
                        console.error('Error loading settlement types:', e, response);
                        Swal.fire({
                            icon: 'error',
                            title: 'Invalid Response',
                            text: 'Failed to process settlement type response.'
                        });
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Settlement type request error:', { xhr: xhr, status: status, error: error });
                    Swal.fire({
                        icon: 'error',
                        title: 'Connection Error',
                        text: 'Failed to load settlement types. Please try again.'
                    });
                }
            });
        }
    };

</script>

<!-- TIME FRAME SELECTION HANDLER -->
<script>
    window.SettlementPerBankTimeFrame = {
        configureInputsForFilterType: function(filterType, refs) {
            const $startDate = refs.$startDate;
            const $endDate = refs.$endDate;
            const $startWrap = refs.$startWrap;
            const $endWrap = refs.$endWrap;

            const $transactionDateLabel = $startWrap.find('.transaction-date-label');
            const $startLabel = $startWrap.find('.start-date-label');
            const $endLabel = $endWrap.find('.end-date-label');

            $startDate.val('');
            $endDate.val('');
            $startDate.attr({ min: null, max: null, placeholder: '' });
            $endDate.attr({ min: null, max: null, placeholder: '' });

            if (filterType === 'date-range') {
                $startDate.attr('type', 'date');
                $endDate.attr('type', 'date');
                $transactionDateLabel.text('Transaction Date').show();
                $startLabel.text('Start Date:');
                $endLabel.text('End Date:');
                $startWrap.show();
                $endWrap.show();
                return;
            }

            if (filterType === 'daily') {
                $startDate.attr('type', 'date');
                $transactionDateLabel.text('Transaction Date').show();
                $startLabel.text('Select Date:');
                $startWrap.show();
                $endWrap.hide();
                return;
            }

            if (filterType === 'monthly' || filterType === 'monthly-range') {
                $startDate.attr('type', 'month');
                $endDate.attr('type', 'month');
                $transactionDateLabel.text('Transaction Date').show();
                $startLabel.text(filterType === 'monthly' ? 'Select Month:' : 'Start Month:');
                $endLabel.text('End Month:');
                $startWrap.show();
                $endWrap.toggle(filterType === 'monthly-range');
                return;
            }

            if (filterType === 'yearly' || filterType === 'yearly-range') {
                $startDate.attr('type', 'number');
                $endDate.attr('type', 'number');
                $startDate.attr({ min: '2020', max: '2035', placeholder: 'YYYY' });
                $endDate.attr({ min: '2020', max: '2035', placeholder: 'YYYY' });
                $transactionDateLabel.text('Transaction Date').show();
                $startLabel.text(filterType === 'yearly' ? 'Select Year:' : 'Start Year:');
                $endLabel.text('End Year:');
                $startWrap.show();
                $endWrap.toggle(filterType === 'yearly-range');
                return;
            }

            $startWrap.hide();
            $endWrap.hide();
        },

        toggleGenerateButton: function(refs) {
            const filterType = refs.$filterType.val();
            const partner = refs.$partner.val();
            const settlementType = refs.$settlementType ? refs.$settlementType.val() : '';
            const bankName = refs.$bankName ? refs.$bankName.val() : '';
            const startDate = refs.$startDate.val();
            const endDate = refs.$endDate.val();
            const $generateReport = refs.$generateReport;

            let datesValid = false;
            if (filterType === 'daily' || filterType === 'monthly' || filterType === 'yearly') {
                datesValid = startDate !== '';
            } else if (filterType === 'date-range' || filterType === 'monthly-range' || filterType === 'yearly-range') {
                datesValid = startDate !== '' && endDate !== '';
            }

            // const enable = !!(filterType && partner && settlementType && bankName && datesValid);
            // if (enable) {
            //     $generateReport.prop('disabled', false).removeClass('btn-secondary').addClass('btn-danger');
            // } else {
            //     $generateReport.prop('disabled', true).removeClass('btn-danger').addClass('btn-secondary');
            // }
        }
    };
</script>

<!-- DATA TABLE RESULTS BASED ON FILTER DROPDOWN SELECTION HANDLER -->
<script>
    window.SettlementPerBankResults = {
        init: function(refs) {
            const self = this;

            self.clearReportTable();
            $('#loading-overlay').hide();

            refs.$generateReport.on('click', function() {
                self.handleGenerate(refs);
            });
        },

        clearReportTable: function() {
            const tbody = $('#transactionReportTable tbody');
            tbody.empty();
            tbody.append('<tr><td colspan="11" class="text-start"><b>CHARGE BY CUSTOMER</b></td></tr><tr><td colspan="11" class="text-center text-muted">No data yet</td></tr><tr><td colspan="11" class="text-start"><b>CHARGE BY PARTNER DAILY</b></td></tr><tr><td colspan="11" class="text-center text-muted">No data yet</td></tr><tr><td colspan="11" class="text-start"><b>CHARGE BY PARTNER WEEKLY</b></td></tr><tr><td colspan="11" class="text-center text-muted">No data yet</td></tr><tr><td colspan="11" class="text-start"><b>CHARGE BY PARTNER MONTHLY</b></td></tr><tr><td colspan="11" class="text-center text-muted">No data yet</td></tr>');

            $('#totalnetvolume').text('0');
            $('#totalnetprincipal').text('0.00');
            $('#totalnetcharge').text('0.00');
            $('#totalprincipaladjustment').text('0.00');
            $('#totalamountforsettlement').text('0.00');
        },

        populateReportTable: function(data, refs) {
            const tbody = $('#transactionReportTable tbody');
            tbody.empty();

            let totalNetVolume = 0;
            let totalNetPrincipal = 0;
            let totalNetCharge = 0;
            let totalPrincipalAdjustment = 0;
            let totalAmountForSettlement = 0;
            let runningIndex = 0;

            const rows = Array.isArray(data) ? data : [];

            const toNumber = function(value) {
                const numericValue = parseFloat(value);
                return Number.isFinite(numericValue) ? numericValue : 0;
            };

            const isEffectivelyZero = function(value) {
                return Math.abs(value) < 0.0000001;
            };

            const normalizeValue = function(value) {
                return String(value || '').trim().toUpperCase();
            };

            const applyRowIntoTarget = function(target, source) {
                target.volume_count = toNumber(target.volume_count) + toNumber(source.volume_count);
                target.principal_amount_paid = toNumber(target.principal_amount_paid) + toNumber(source.principal_amount_paid);
                target.charges = toNumber(target.charges) + toNumber(source.charges);
                target.principal_adjustment = toNumber(target.principal_adjustment) + toNumber(source.principal_adjustment);
                target.amount_for_settlement = toNumber(target.amount_for_settlement) + toNumber(source.amount_for_settlement);
                // aggregate summary/adjustment fields if present
                target.summary_vol = toNumber(target.summary_vol) + toNumber(source.summary_vol);
                target.summary_principal = toNumber(target.summary_principal) + toNumber(source.summary_principal);
                target.summary_charges = toNumber(target.summary_charges) + toNumber(source.summary_charges);
                target.adjustment_vol = toNumber(target.adjustment_vol) + toNumber(source.adjustment_vol);
                target.adjustment_principal = toNumber(target.adjustment_principal) + toNumber(source.adjustment_principal);
                target.adjustment_charges = toNumber(target.adjustment_charges) + toNumber(source.adjustment_charges);
                // keep server-provided net if present, otherwise will compute later
                target.net_vol = toNumber(target.net_vol) || 0;
                target.net_principal = toNumber(target.net_principal) || 0;
                target.net_charges = toNumber(target.net_charges) || 0;
                target.partner_name_raw = target.partner_name_raw || source.partner_name_raw || '';
                target.partner_name = target.partner_name || source.partner_name || '';
                target.partner_id = target.partner_id || source.partner_id || '';
                target.partner_id_kpx = target.partner_id_kpx || source.partner_id_kpx || '';
                target.partner_accName = target.partner_accName || source.partner_accName || '';
                target.bank_accNumber = target.bank_accNumber || source.bank_accNumber || '';
                target.bank_name = target.bank_name || source.bank_name || source.bank || '';
                target.settled_online_check = target.settled_online_check || source.settled_online_check || '';
                target.charge_to = target.charge_to || source.charge_to || '';
                target.charge_sched = target.charge_sched || source.charge_sched || '';
            };

            const mergedRowsMap = new Map();
            rows.forEach(function(row) {
                const displayPartnerName = (row.partner_name_raw || row.partner_name || '');
                const partnerKey = [
                    normalizeValue(displayPartnerName),
                    normalizeValue(row.partner_id),
                    normalizeValue(row.partner_id_kpx)
                ].join('|');

                if (!mergedRowsMap.has(partnerKey)) {
                    mergedRowsMap.set(partnerKey, {
                        ...row,
                        partner_name_raw: displayPartnerName,
                        volume_count: toNumber(row.volume_count),
                        principal_amount_paid: toNumber(row.principal_amount_paid),
                        charges: toNumber(row.charges),
                        principal_adjustment: toNumber(row.principal_adjustment),
                        amount_for_settlement: toNumber(row.amount_for_settlement),
                        bank_name: row.bank_name || row.bank || ''
                    });
                    return;
                }

                const existing = mergedRowsMap.get(partnerKey);
                applyRowIntoTarget(existing, row);
            });

            const mergedRows = Array.from(mergedRowsMap.values());

            const visibleRows = mergedRows.filter(function(row) {
                const volumeCount = toNumber(row.volume_count);
                const principalAmountPaid = toNumber(row.principal_amount_paid);
                const charges = toNumber(row.charges);

                return !(isEffectivelyZero(volumeCount) && isEffectivelyZero(principalAmountPaid) && isEffectivelyZero(charges));
            });

            const getEffectiveChargeTo = function(row) {
                const chargeTo = normalizeValue(row.charge_to);
                const chargeSched = normalizeValue(row.charge_sched);

                if (chargeTo) {
                    return chargeTo;
                }

                if (chargeSched === 'PER TRANSACTION') {
                    return 'CUSTOMER';
                }

                return '';
            };

            const sections = [
                {
                    title: 'CHARGE BY CUSTOMER',
                    matcher: function(row) {
                        return getEffectiveChargeTo(row) === 'CUSTOMER';
                    }
                },
                {
                    title: 'CHARGE BY PARTNER DAILY',
                    matcher: function(row) {
                        return getEffectiveChargeTo(row) === 'PARTNER' && normalizeValue(row.charge_sched) === 'DAILY';
                    }
                },
                {
                    title: 'CHARGE BY PARTNER WEEKLY',
                    matcher: function(row) {
                        return getEffectiveChargeTo(row) === 'PARTNER' && normalizeValue(row.charge_sched) === 'WEEKLY';
                    }
                },
                {
                    title: 'CHARGE BY PARTNER MONTHLY',
                    matcher: function(row) {
                        return getEffectiveChargeTo(row) === 'PARTNER' && normalizeValue(row.charge_sched) === 'MONTHLY';
                    }
                }
            ];

            const displayedRows = new Set();

            const appendDataRows = function(sectionRows) {
                sectionRows.forEach((row) => {
                    const volumeCount = toNumber(row.volume_count);
                    const principalAmountPaid = toNumber(row.principal_amount_paid);
                    const charges = toNumber(row.charges);
                    const principalAdjustment = toNumber(row.principal_adjustment);
                    const amountForSettlement = toNumber(row.amount_for_settlement);
                    // compute net values: prefer server-provided net fields, else compute from summary - adjustment
                    const summaryVol = toNumber(row.summary_vol);
                    const adjustmentVol = toNumber(row.adjustment_vol);
                    const summaryPrincipal = toNumber(row.summary_principal);
                    const adjustmentPrincipal = toNumber(row.adjustment_principal);
                    const summaryCharge = toNumber(row.summary_charges);
                    const adjustmentCharge = toNumber(row.adjustment_charges);

                    const netVol = toNumber(row.net_vol) || (summaryVol - adjustmentVol);
                    const netPrincipal = toNumber(row.net_principal) || (summaryPrincipal - adjustmentPrincipal);
                    const netCharge = toNumber(row.net_charges) || (summaryCharge - adjustmentCharge);

                    displayedRows.add(row);

                    // Totals for Net Total Transaction should use net values
                    totalNetVolume += netVol;
                    totalNetPrincipal += netPrincipal;
                    totalNetCharge += netCharge;
                    totalPrincipalAdjustment += principalAdjustment;
                    totalAmountForSettlement += amountForSettlement;
                    runningIndex += 1;

                    const isUnsettled = toNumber(row.has_unsettled) > 0;
                    const trClass = isUnsettled ? 'table-danger' : 'table-success';
                    const statusIcon = isUnsettled ? '<i class="fa-solid fa-xmark text-danger" title="Unsettled"></i>' : '<i class="fa-solid fa-check text-success" title="Settled"></i>';
                    const partnerNameRaw = row.partner_name_raw || row.partner_name || '';
                    const tr = `
                        <tr class="${trClass}" data-partner-id="${row.partner_id || ''}" data-partner-id-kpx="${row.partner_id_kpx || ''}" data-partner-name="${(partnerNameRaw||'').replace(/"/g,'&quot;')}" data-bank-name="${(row.bank_name||'').replace(/"/g,'&quot;')}" data-has-unsettled="${row.has_unsettled || 0}">
                            <td class="text-center"><input class="form-check-input row-select" type="checkbox" aria-label="Select row"></td>
                            <td class="text-center">${runningIndex}</td>
                            <td>
                                ${partnerNameRaw}
                            </td>
                            <td>${row.partner_accName || ''}</td>
                            <td class="text-truncate">${row.bank_accNumber || ''}</td>
                            <td class="text-end">${parseInt(netVol || 0).toLocaleString()}</td>
                            <td class="text-end">${parseFloat(netPrincipal || 0).toLocaleString('en-US', { minimumFractionDigits: 2 })}</td>
                            <td class="text-end">${parseFloat(netCharge || 0).toLocaleString('en-US', { minimumFractionDigits: 2 })}</td>
                            <td class="text-end">${principalAdjustment.toLocaleString('en-US', { minimumFractionDigits: 2 })}</td>
                            <td class="text-end">${amountForSettlement.toLocaleString('en-US', { minimumFractionDigits: 2 })}</td>
                            <td class="text-center">${statusIcon}</td>
                        </tr>
                    `;
                    tbody.append(tr);
                });
            };

            if (visibleRows.length === 0) {
                tbody.append('<tr><td colspan="11" class="text-center">No data found for the selected criteria</td></tr>');
            } else {
                sections.forEach(section => {
                    const sectionRows = visibleRows.filter(section.matcher);

                    tbody.append(`<tr><td colspan="11" class="text-start"><b>${section.title}</b></td></tr>`);

                        if (sectionRows.length === 0) {
                        tbody.append('<tr><td colspan="11" class="text-center text-muted">No data found under this section</td></tr>');
                        return;
                    }

                    appendDataRows(sectionRows);
                });

                const uncategorizedRows = visibleRows.filter(function(row) {
                    return !displayedRows.has(row);
                });

                if (uncategorizedRows.length > 0) {
                    tbody.append('<tr><td colspan="11" class="text-start"><b>UNMAPPED CHARGE CATEGORY</b></td></tr>');
                    appendDataRows(uncategorizedRows);
                }
            }

            $('#totalnetvolume').text(totalNetVolume.toLocaleString());
            $('#totalnetprincipal').text(totalNetPrincipal.toLocaleString('en-US', { minimumFractionDigits: 2 }));
            $('#totalnetcharge').text(totalNetCharge.toLocaleString('en-US', { minimumFractionDigits: 2 }));
            $('#totalprincipaladjustment').text(totalPrincipalAdjustment.toLocaleString('en-US', { minimumFractionDigits: 2 }));
            $('#totalamountforsettlement').text(totalAmountForSettlement.toLocaleString('en-US', { minimumFractionDigits: 2 }));
        },

        getEffectiveDates: function(refs) {
            const filterType = refs.$filterType.val();
            const startDate = refs.$startDate.val();
            let endDate = refs.$endDate.val();

            if (filterType === 'daily' || filterType === 'monthly') {
                endDate = startDate;
            }

            return {
                startDate: startDate,
                endDate: endDate || startDate
            };
        },

        handleGenerate: function(refs) {
            const filterType = refs.$filterType.val();
            const partner = refs.$partner.val();
            const settlementType = refs.$settlementType.val();
            const bankName = refs.$bankName.val();
            const startDate = refs.$startDate.val();
            const endDate = refs.$endDate.val();

            // if (!partner || !settlementType || !bankName || !filterType || !startDate) {
            //     Swal.fire({
            //         icon: 'warning',
            //         title: 'Missing Information',
            //         text: 'Please complete all required filters.'
            //     });
            //     return;
            // }

            if (filterType === 'date-range' && !endDate) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Missing End Date',
                    text: 'Please provide End Date for Date Range.'
                });
                return;
            }

            const dates = this.getEffectiveDates(refs);
            this.requestReport(refs, dates.startDate, dates.endDate);
        },

        requestReport: function(refs, startDate, endDate) {
            const self = this;
            $('#loading-overlay').css('display', 'flex');

            $.ajax({
                url: '',
                type: 'POST',
                data: {
                    action: 'generate_report',
                    partner: refs.$partner.val(),
                    settlementType: refs.$settlementType.val(),
                    bankName: refs.$bankName.val(),
                    filterType: refs.$filterType.val(),
                    startDate: startDate,
                    endDate: endDate
                },
                complete: function() {
                    $('#loading-overlay').hide();
                },
                success: function(response) {
                    try {
                        const result = JSON.parse(response);
                        if (result.status === 'success') {
                            self.populateReportTable(result.data || [], refs);
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: result.message || 'Unable to generate report.'
                            });
                        }
                    } catch (e) {
                        console.error('Settlement report response parse error:', e, response);
                        Swal.fire({
                            icon: 'error',
                            title: 'Invalid Response',
                            text: 'Failed to process report response.'
                        });
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Settlement report request error:', { xhr: xhr, status: status, error: error });
                    Swal.fire({
                        icon: 'error',
                        title: 'Connection Error',
                        text: 'Failed to generate report. Please try again.'
                    });
                }
            });
        }
    };

</script>
</html>