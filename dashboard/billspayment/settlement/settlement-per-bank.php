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

        $settlementTypeQuery = 'SELECT
                                    settled_online_check
                                FROM
                                    mldb.bank_table
                                WHERE
                                    used_unused = ?';

        if ($bankName !== 'ALL') {
            $settlementTypeQuery .= ' AND bank_name = ?';
        }

        $settlementTypeQuery .= ' GROUP BY settled_online_check ORDER BY settled_online_check';
        $stmt = $conn->prepare($settlementTypeQuery);

        if (!$stmt) {
            throw new Exception('Prepare failed: ' . $conn->error);
        }

        $usedStatus = 'used';

        if ($bankName !== 'ALL') {
            $stmt->bind_param('ss', $usedStatus, $bankName);
        } else {
            $stmt->bind_param('s', $usedStatus);
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

    $dateCondition = '';
    $dateTypesPerCte = '';
    $dateParamsPerCte = [];

    if ($filterType === 'daily') {
        $dateCondition = "(DATE(bt.datetime) = ? OR DATE(bt.cancellation_date) = ?)";
        $dateTypesPerCte = 'ss';
        $dateParamsPerCte = [$startDate, $startDate];
    } elseif ($filterType === 'date-range') {
        $rangeEnd = $endDate !== '' ? $endDate : $startDate;
        $dateCondition = "(DATE(bt.datetime) BETWEEN ? AND ? OR DATE(bt.cancellation_date) BETWEEN ? AND ?)";
        $dateTypesPerCte = 'ssss';
        $dateParamsPerCte = [$startDate, $rangeEnd, $startDate, $rangeEnd];
    } elseif ($filterType === 'monthly') {
        $startMonth = $startDate . '-01';
        $endMonth = date('Y-m-t', strtotime($startDate . '-01'));
        $dateCondition = "(DATE(bt.datetime) BETWEEN ? AND ? OR DATE(bt.cancellation_date) BETWEEN ? AND ?)";
        $dateTypesPerCte = 'ssss';
        $dateParamsPerCte = [$startMonth, $endMonth, $startMonth, $endMonth];
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid time frame selected.',
            'data' => []
        ]);
        exit();
    }

    $cteFilterConditions = [];
    $cteFilterTypes = '';
    $cteFilterParams = [];

    if (!empty($settlementType) && strtoupper($settlementType) !== 'ALL') {
        $cteFilterConditions[] = "EXISTS (
            SELECT 1
                        FROM masterdata.partner_masterfile pm
            WHERE (pm.partner_id = bt.partner_id OR pm.partner_id_kpx = bt.partner_id_kpx OR pm.partner_name = bt.partner_name)
              AND pm.settled_online_check = ?
        )";
        $cteFilterTypes .= 's';
        $cteFilterParams[] = $settlementType;
    }

    if (!empty($bankName) && strtoupper($bankName) !== 'ALL') {
        $cteFilterConditions[] = "EXISTS (
            SELECT 1
                        FROM masterdata.partner_masterfile pm
            INNER JOIN mldb.partner_bank pb ON pb.partner_id = pm.partner_id
            WHERE (pm.partner_id = bt.partner_id OR pm.partner_id_kpx = bt.partner_id_kpx OR pm.partner_name = bt.partner_name)
              AND pb.bank = ?
        )";
        $cteFilterTypes .= 's';
        $cteFilterParams[] = $bankName;
    }

    $allPartnersFilterConditions = [];
    $allPartnersFilterTypes = '';
    $allPartnersFilterParams = [];

    if (!empty($settlementType) && strtoupper($settlementType) !== 'ALL') {
        $allPartnersFilterConditions[] = "mpm.settled_online_check = ?";
        $allPartnersFilterTypes .= 's';
        $allPartnersFilterParams[] = $settlementType;
    }

    if (!empty($bankName) && strtoupper($bankName) !== 'ALL') {
        $allPartnersFilterConditions[] = "EXISTS (
            SELECT 1
            FROM mldb.partner_bank pb
            WHERE pb.partner_id = mpm.partner_id
              AND pb.bank = ?
        )";
        $allPartnersFilterTypes .= 's';
        $allPartnersFilterParams[] = $bankName;
    }

    $cteFilterClause = '';
    if (!empty($cteFilterConditions)) {
        $cteFilterClause = ' AND ' . implode(' AND ', $cteFilterConditions);
    }

    $perCteTypes = $dateTypesPerCte . $cteFilterTypes;
    $perCteParams = array_merge($dateParamsPerCte, $cteFilterParams);

    $allPartnersWhereClause = '';
    if (!empty($allPartnersFilterConditions)) {
        $allPartnersWhereClause = ' AND ' . implode(' AND ', $allPartnersFilterConditions);
    }

    $types = $perCteTypes . $perCteTypes . $allPartnersFilterTypes;
    $params = array_merge($perCteParams, $perCteParams, $allPartnersFilterParams);

    $mainWhereClause = '';
    if (!empty($partner) && strtoupper($partner) !== 'ALL') {
        $mainWhereClause = ' AND ap.partner_name = ?';
        $types .= 's';
        $params[] = $partner;
    }

    $dataQuery = "WITH summary_vol AS (
                        SELECT
                            CASE
                                WHEN bt.partner_id IS NOT NULL THEN bt.partner_id
                                WHEN bt.partner_id_kpx IS NOT NULL THEN bt.partner_id_kpx
                                ELSE CONCAT('temp_', bt.partner_name)
                            END COLLATE utf8mb4_general_ci AS partner_key,
                            bt.partner_name,
                            COUNT(*) AS vol1,
                            SUM(bt.amount_paid) AS principal1,
                            SUM(bt.charge_to_partner + bt.charge_to_customer) AS charge1
                        FROM mldb.billspayment_transaction AS bt
                        WHERE $dateCondition
                            AND bt.status IS NULL
                            AND bt.branch_id NOT IN ('1', '2', '4937', '4938', '4962', '4987', '4993', '4944')
                            $cteFilterClause
                        GROUP BY
                            CASE
                                WHEN bt.partner_id IS NOT NULL THEN bt.partner_id
                                WHEN bt.partner_id_kpx IS NOT NULL THEN bt.partner_id_kpx
                                ELSE CONCAT('temp_', bt.partner_name)
                            END COLLATE utf8mb4_general_ci,
                            bt.partner_name
                ),
                adjustment_vol AS (
                    SELECT
                        CASE
                            WHEN bt.partner_id IS NOT NULL THEN bt.partner_id
                            WHEN bt.partner_id_kpx IS NOT NULL THEN bt.partner_id_kpx
                            ELSE CONCAT('temp_', bt.partner_name)
                        END COLLATE utf8mb4_general_ci AS partner_key,
                        bt.partner_name,
                        COUNT(*) AS vol2,
                        SUM(bt.amount_paid) AS principal2,
                        SUM(bt.charge_to_partner + bt.charge_to_customer) AS charge2
                    FROM mldb.billspayment_transaction AS bt
                    WHERE $dateCondition
                        AND bt.status = '*'
                        AND bt.branch_id NOT IN ('1', '2', '4937', '4938', '4962', '4987', '4993', '4944')
                        $cteFilterClause
                    GROUP BY
                        CASE
                            WHEN bt.partner_id IS NOT NULL THEN bt.partner_id
                            WHEN bt.partner_id_kpx IS NOT NULL THEN bt.partner_id_kpx
                            ELSE CONCAT('temp_', bt.partner_name)
                        END COLLATE utf8mb4_general_ci,
                        bt.partner_name
                ),
                all_partners AS (
                    SELECT
                        COALESCE(mpm.partner_id, mpm.partner_id_kpx, CONCAT('temp_', mpm.partner_name)) AS partner_key,
                        mpm.partner_name,
                        mpm.partner_accName,
                        mpm.bank_accNumber,
                        mpm.bank,
                        mpm.settled_online_check,
                        mpm.charge_to,
                        mpm.charge_sched
                    FROM masterdata.partner_masterfile AS mpm
                    WHERE mpm.status = 'ACTIVE'
                    $allPartnersWhereClause

                    UNION

                    SELECT
                        partner_key,
                        partner_name,
                        NULL AS partner_accName,
                        NULL AS bank_accNumber,
                        NULL AS bank,
                        NULL AS settled_online_check,
                        NULL AS charge_to,
                        NULL AS charge_sched
                    FROM summary_vol

                    UNION

                    SELECT
                        partner_key,
                        partner_name,
                        NULL AS partner_accName,
                        NULL AS bank_accNumber,
                        NULL AS bank,
                        NULL AS settled_online_check,
                        NULL AS charge_to,
                        NULL AS charge_sched
                    FROM adjustment_vol
                )
                SELECT
                    ap.partner_name,
                    MAX(ap.partner_accName) AS partner_accName,
                    MAX(ap.bank_accNumber) AS bank_accNumber,
                    MAX(mpm.bank) AS bank,
                    MAX(mpm.settled_online_check) AS settled_online_check,
                    MAX(mpm.charge_to) AS charge_to,
                    MAX(mpm.charge_sched) AS charge_sched,
                    (SUM(COALESCE(sv.vol1, 0)) - SUM(COALESCE(av.vol2, 0))) AS net_vol,
                    (SUM(COALESCE(sv.principal1, 0)) - SUM(COALESCE(ABS(av.principal2), 0))) AS net_principal,
                    (SUM(COALESCE(sv.charge1, 0)) - SUM(COALESCE(ABS(av.charge2), 0))) AS net_charges
                FROM all_partners AS ap
                LEFT JOIN summary_vol AS sv ON (ap.partner_key = sv.partner_key OR ap.partner_name = sv.partner_name)
                LEFT JOIN adjustment_vol AS av ON (ap.partner_key = av.partner_key OR ap.partner_name = av.partner_name)
                LEFT JOIN masterdata.partner_masterfile AS mpm ON (ap.partner_name = mpm.partner_name)
                WHERE (mpm.status = 'ACTIVE' OR mpm.status IS NULL)
                $mainWhereClause
                GROUP BY ap.partner_name
                HAVING ap.partner_name IS NOT NULL
                ORDER BY ap.partner_name";

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
                                            <th rowspan="2" class='text-truncate text-center align-middle'>No.</th>
                                            <th rowspan="2" class='text-truncate text-center align-middle'>Partner Name</th>
                                            <th rowspan="2" class='text-truncate text-center align-middle'>Account Name</th>
                                            <th rowspan="2" class='text-truncate text-center align-middle'>Account Number</th>
                                            <th colspan="3" class='text-truncate text-center align-middle'>Net Total Transaction</th>
                                            <th class='text-truncate text-center align-middle'>Principal Adjustment</th>
                                            <th rowspan="2" class='text-truncate text-center align-middle'>Amount for Settlement</th>
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
                                        </tr>
                                    </tbody>
                                    <tfoot class="sticky-bottom table-dark">
                                        <tr>
                                            <th colspan="4" class="text-end">Total : </th>
                                            <th class="text-center" id="totalnetvolume">0</th>
                                            <th class="text-end" id="totalnetprincipal">0.00</th>
                                            <th class="text-end" id="totalnetcharge">0.00</th>
                                            <th class="text-end">0.00</th>
                                            <th class="text-end">0.00</th>
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
            tbody.append('<tr><td colspan="9" class="text-center">No data found for the selected criteria</td></tr>');

            $('#totalnetvolume').text('0');
            $('#totalnetprincipal').text('0.00');
            $('#totalnetcharge').text('0.00');
        },

        populateReportTable: function(data, refs) {
            const tbody = $('#transactionReportTable tbody');
            tbody.empty();

            let totalNetVolume = 0;
            let totalNetPrincipal = 0;
            let totalNetCharge = 0;

            const rows = Array.isArray(data) ? data : [];

            const appendDataRows = function(sectionRows, startIndex) {
                sectionRows.forEach((row, index) => {
                    const netVol = parseInt(row.net_vol || 0, 10);
                    const netPrincipal = parseFloat(row.net_principal || 0);
                    const netCharge = parseFloat(row.net_charges || 0);

                    totalNetVolume += netVol;
                    totalNetPrincipal += netPrincipal;
                    totalNetCharge += netCharge;

                    const partnerMeta = [
                        row.bank ? `Bank: ${row.bank}` : '',
                        row.settled_online_check ? `Settlement: ${row.settled_online_check}` : '',
                        row.charge_to ? `Charge To: ${row.charge_to}` : '',
                        row.charge_sched ? `Charge Sched: ${row.charge_sched}` : ''
                    ].filter(Boolean).join(' | ');

                    const tr = `
                        <tr>
                            <td class="text-center">${startIndex + index + 1}</td>
                            <td>
                                ${row.partner_name || ''}
                                ${partnerMeta ? `<div class="small text-muted">${partnerMeta}</div>` : ''}
                            </td>
                            <td>${row.partner_accName || ''}</td>
                            <td class="text-truncate">${row.bank_accNumber || ''}</td>
                            <td class="text-end">${netVol.toLocaleString()}</td>
                            <td class="text-end">${netPrincipal.toLocaleString('en-US', { minimumFractionDigits: 2 })}</td>
                            <td class="text-end">${netCharge.toLocaleString('en-US', { minimumFractionDigits: 2 })}</td>
                            <td class="text-end">0.00</td>
                            <td class="text-end">0.00</td>
                        </tr>
                    `;
                    tbody.append(tr);
                });
            };

            if (rows.length === 0) {
                tbody.append('<tr><td colspan="9" class="text-center">No data found for the selected criteria</td></tr>');
            } else {
                appendDataRows(rows, 0);
            }

            $('#totalnetvolume').text(totalNetVolume.toLocaleString());
            $('#totalnetprincipal').text(totalNetPrincipal.toLocaleString('en-US', { minimumFractionDigits: 2 }));
            $('#totalnetcharge').text(totalNetCharge.toLocaleString('en-US', { minimumFractionDigits: 2 }));
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