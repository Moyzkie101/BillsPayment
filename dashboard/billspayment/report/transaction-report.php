<?php
// Connect to the database
include '../../../config/config.php';
require '../../../vendor/autoload.php';

// Start the session
session_start();
@include_once __DIR__ . '/../../../templates/middleware.php';
$id = resolve_user_identifier();
if (empty($id)) { header('Location: ../../../login_form.php'); exit; }
if (!function_exists('has_any_permission') || !has_any_permission(['Transaction Report','Bills Payment'])) { header('Location: ../../home.php'); exit; }


// prefer explicit session values for current user email; avoid role-based gating
$current_user_email = $_SESSION['admin_email'] ?? $_SESSION['user_email'] ?? '';

// zone - Modified to be populated via AJAX based on mainzone selection
$zone_result = null; // Will be populated via AJAX

// Add AJAX handler for fetching transaction data
if (isset($_POST['action']) && $_POST['action'] === 'get_transaction_data') {
    // Clear any previous output and set headers
    if (ob_get_level()) {
        ob_clean();
    }
    header('Content-Type: application/json');
    
    $partner = isset($_POST['partner']) ? $_POST['partner'] : '';
    $start_date = isset($_POST['start_date']) ? $_POST['start_date'] : '';
    $end_date = isset($_POST['end_date']) ? $_POST['end_date'] : '';
    $post_transaction = isset($_POST['post_transaction']) ? $_POST['post_transaction'] : '';
    $status = isset($_POST['status']) ? $_POST['status'] : '';
    $source_file = isset($_POST['source_file']) ? $_POST['source_file'] : '';
    $mainzone = isset($_POST['mainzone']) ? $_POST['mainzone'] : '';
    $zone = isset($_POST['zone']) ? $_POST['zone'] : '';
    $region = isset($_POST['region']) ? $_POST['region'] : '';
    $branch = isset($_POST['branch']) ? $_POST['branch'] : '';
    $search = isset($_POST['search']) ? $_POST['search'] : '';
    $page = isset($_POST['page']) ? (int)$_POST['page'] : 1;
    $rows_per_page = isset($_POST['rows_per_page']) ? (int)$_POST['rows_per_page'] : 15;
    
    // Initialize arrays and variables
    $whereConditions = [];
    $params = [];
    $types = '';

    // Always exclude these branch/status rows from report results
    $whereConditions[] = "NOT (branch_id IN ('1', '2', '4937', '4938', '4962', '4987', '4993', '4944') AND status IS NULL)";
    
    // Build WHERE conditions
    if (!empty($search)) {
        $whereConditions[] = "(reference_no LIKE ?)";
        $searchParam = "%$search%";
        $params[] = $searchParam;
        $types .= 's';
    }

    if (!empty($partner) && $partner !== 'All') {
        if($partner === 'SECURITY BANK') {
            $whereConditions[] = "(partner_name = ? AND sub_billers_id IS NULL)";
        }elseif($partner === 'MYLORA CORPORATION' || $partner === 'JUNANS MARKETING'){
            $whereConditions[] = "sub_billers_name = ?";
        }else{
            $whereConditions[] = "partner_name = ?";
        }
        $params[] = $partner;
        $types .= 's';
    }

    if (!empty($start_date)) {
        $whereConditions[] = "(DATE(datetime) >= ? OR DATE(cancellation_date) >= ? OR DATE(report_date) >= ?)";
        $params[] = $start_date;
        $params[] = $start_date;
        $params[] = $start_date;
        $types .= 'sss';
    }

    if (!empty($end_date)) {
        $whereConditions[] = "(DATE(datetime) <= ? OR DATE(cancellation_date) <= ? OR DATE(report_date) <= ?)";
        $params[] = $end_date;
        $params[] = $end_date;
        $params[] = $end_date;
        $types .= 'sss';
    }

    if (!empty($post_transaction) && $post_transaction !== 'All') {
        $whereConditions[] = "post_transaction = ?";
        $params[] = $post_transaction;
        $types .= 's';
    }

    if (!empty($status) && $status !== 'All') {
        if ($status === 'active') {
            // Handle cases for Active status (NULL or empty values in database)
            $whereConditions[] = "status IS NULL";
        } else {
            // Handle other specific statuses
            $whereConditions[] = "status = '*'";
        }
    }

    if (!empty($source_file) && $source_file !== 'All') {
        $whereConditions[] = "source_file = ?";
        $params[] = $source_file;
        $types .= 's';
    }

    //for mainzone and zone filtering
    if($mainzone ==='VISMIN'){
        if (!empty($zone) && $zone !== 'All') {
            $whereConditions[] = "zone_code = ?";
            $params[] = $zone;
            $types .= 's';
        }else{
            $whereConditions[] = "zone_code IN ('VIS', 'MIN')";
        }
    }elseif($mainzone ==='LNCR'){
        if (!empty($zone) && $zone !== 'All') {
            $whereConditions[] = "zone_code = ?";
            $params[] = $zone;
            $types .= 's';
        }else{
            $whereConditions[] = "zone_code IN ('LZN', 'NCR')";
        }
    }

    if (!empty($region) && $region !== 'All') {
        $whereConditions[] = "region_code = ?";
        $params[] = $region;
        $types .= 's';
    }

    if (!empty($branch) && $branch !== 'All') {
        $whereConditions[] = "branch_id = ?";
        $params[] = $branch;
        $types .= 's';
    }

    // Build WHERE clause
    $whereClause = '';
    if (!empty($whereConditions)) {
        $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
    }

    // Check database connection
    if (!$conn) {
        echo json_encode(['success' => false, 'error' => 'Database connection failed']);
        exit;
    }

    // Helper: bind parameters with proper reference handling.
    // mysqli_stmt::bind_param requires arguments by reference; the spread
    // operator (...) unpacks by value, which causes bind_param to fail
    // silently on PHP < 8.1.  call_user_func_array preserves references.
    $bindParams = function ($stmt, $types, &$params) {
        if (empty($types)) {
            return true;
        }
        $refs = [$types];
        foreach ($params as $key => $val) {
            $refs[] = &$params[$key];
        }
        return call_user_func_array([$stmt, 'bind_param'], $refs);
    };

    // Execute a prepared query and return the result set (or null on failure)
    $execPrepared = function ($conn, $query, $types, &$params) use ($bindParams) {
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            return null;
        }
        if (!$bindParams($stmt, $types, $params)) {
            $stmt->close();
            return null;
        }
        if (!$stmt->execute()) {
            $stmt->close();
            return null;
        }
        $result = $stmt->get_result();
        $stmt->close();
        return $result ?: null;
    };

    // Count total records
    $totalRecords = 0;
    $countQuery = "SELECT COUNT(*) as total FROM mldb.billspayment_transaction $whereClause";

    if (!empty($params)) {
        $countResult = $execPrepared($conn, $countQuery, $types, $params);
        if ($countResult) {
            $totalRecords = $countResult->fetch_assoc()['total'];
        }
    } else {
        $countResult = $conn->query($countQuery);
        if ($countResult) {
            $totalRecords = $countResult->fetch_assoc()['total'];
        }
    }

    $summaryResults = [
        'summary' => ['volume' => 0, 'principal' => 0, 'chargePartner' => 0, 'chargeCustomer' => 0, 'totalCharge' => 0],
        'adjustment' => ['volume' => 0, 'principal' => 0, 'chargePartner' => 0, 'chargeCustomer' => 0, 'totalCharge' => 0],
        'net' => ['volume' => 0, 'principal' => 0, 'chargePartner' => 0, 'chargeCustomer' => 0, 'totalCharge' => 0, 'settlementAmount' => 0]
    ];

    $summaryQuery = "SELECT
                        COALESCE(SUM(CASE WHEN status IS NULL OR status = '' THEN 1 ELSE 0 END), 0) as summary_volume,
                        COALESCE(SUM(CASE WHEN status IS NULL OR status = '' THEN ABS(amount_paid) ELSE 0 END), 0) as summary_principal,
                        COALESCE(SUM(CASE WHEN status IS NULL OR status = '' THEN ABS(charge_to_partner) ELSE 0 END), 0) as summary_charge_partner,
                        COALESCE(SUM(CASE WHEN status IS NULL OR status = '' THEN ABS(charge_to_customer) ELSE 0 END), 0) as summary_charge_customer,
                        COALESCE(SUM(CASE WHEN status = '*' OR status = 'cancelled' THEN 1 ELSE 0 END), 0) as adjustment_volume,
                        COALESCE(SUM(CASE WHEN status = '*' OR status = 'cancelled' THEN ABS(amount_paid) ELSE 0 END), 0) as adjustment_principal,
                        COALESCE(SUM(CASE WHEN status = '*' OR status = 'cancelled' THEN ABS(charge_to_partner) ELSE 0 END), 0) as adjustment_charge_partner,
                        COALESCE(SUM(CASE WHEN status = '*' OR status = 'cancelled' THEN ABS(charge_to_customer) ELSE 0 END), 0) as adjustment_charge_customer
                    FROM mldb.billspayment_transaction $whereClause";

    if (!empty($params)) {
        $summaryResult = $execPrepared($conn, $summaryQuery, $types, $params);
    } else {
        $summaryResult = $conn->query($summaryQuery);
    }

    if ($summaryResult) {
        $summaryRow = $summaryResult->fetch_assoc();
        $summaryResults['summary']['volume'] = (int)$summaryRow['summary_volume'];
        $summaryResults['summary']['principal'] = (float)$summaryRow['summary_principal'];
        $summaryResults['summary']['chargePartner'] = (float)$summaryRow['summary_charge_partner'];
        $summaryResults['summary']['chargeCustomer'] = (float)$summaryRow['summary_charge_customer'];
        $summaryResults['summary']['totalCharge'] = $summaryResults['summary']['chargePartner'] + $summaryResults['summary']['chargeCustomer'];

        $summaryResults['adjustment']['volume'] = (int)$summaryRow['adjustment_volume'];
        $summaryResults['adjustment']['principal'] = (float)$summaryRow['adjustment_principal'];
        $summaryResults['adjustment']['chargePartner'] = (float)$summaryRow['adjustment_charge_partner'];
        $summaryResults['adjustment']['chargeCustomer'] = (float)$summaryRow['adjustment_charge_customer'];
        $summaryResults['adjustment']['totalCharge'] = $summaryResults['adjustment']['chargePartner'] + $summaryResults['adjustment']['chargeCustomer'];

        $summaryResults['net']['volume'] = $summaryResults['summary']['volume'] - $summaryResults['adjustment']['volume'];
        $summaryResults['net']['principal'] = $summaryResults['summary']['principal'] - $summaryResults['adjustment']['principal'];
        $summaryResults['net']['chargePartner'] = $summaryResults['summary']['chargePartner'] - $summaryResults['adjustment']['chargePartner'];
        $summaryResults['net']['chargeCustomer'] = $summaryResults['summary']['chargeCustomer'] - $summaryResults['adjustment']['chargeCustomer'];
        $summaryResults['net']['totalCharge'] = $summaryResults['summary']['totalCharge'] - $summaryResults['adjustment']['totalCharge'];
        $summaryResults['net']['settlementAmount'] = $summaryResults['net']['principal'] - $summaryResults['net']['totalCharge'];
    }

    // Calculate pagination
    $offset = ($page - 1) * $rows_per_page;
    $totalPages = $totalRecords > 0 ? ceil($totalRecords / $rows_per_page) : 0;

    // Main data query with pagination
    $dataQuery = "SELECT * FROM mldb.billspayment_transaction
                $whereClause
                ORDER BY datetime DESC
                LIMIT $rows_per_page OFFSET $offset";

    // Execute main query
    $data = [];
    if (!empty($params)) {
        $result = $execPrepared($conn, $dataQuery, $types, $params);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
        }
    } else {
        $result = $conn->query($dataQuery);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
        }
    }

    // Return JSON response
    echo json_encode([
        'success' => true,
        'data' => $data,
        'summary' => $summaryResults,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_records' => (int)$totalRecords,
            'rows_per_page' => $rows_per_page,
            'start_record' => $totalRecords > 0 ? $offset + 1 : 0,
            'end_record' => $totalRecords > 0 ? min($offset + $rows_per_page, $totalRecords) : 0
        ]
    ]);
    exit;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaction Details Report | <?php if($_SESSION['user_type'] === 'admin' || $_SESSION['user_type'] === 'user') echo ucfirst($_SESSION['user_type']); else echo "Guest";?></title>
    <!-- custom CSS file link  -->
    <link rel="stylesheet" href="../../../assets/css/templates/style.css?v=<?php echo time(); ?>">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Select2 CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css" rel="stylesheet">
    <!-- Select2 Bootstrap theme -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/select2-bootstrap-5-theme/1.3.0/select2-bootstrap-5-theme.min.css" rel="stylesheet">

    <script src="https://kit.fontawesome.com/30b908cc5a.js" crossorigin="anonymous"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Bootstrap JS Bundle (includes Popper) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script src="../../../assets/js/sweetalert2.all.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>

    <link rel="icon" href="../../../images/MLW logo.png" type="image/png">
    <style>
        #loading-overlay {
            position: fixed;
            inset: 0;
            z-index: 2000;
            background: rgba(33, 37, 41, 0.35);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Style for Select2 validation */
        .select2-container.is-invalid .select2-selection {
            border-color: #dc3545 !important;
            box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25) !important;
        }

        /* Keep Select2 clear/remove icons visible above Bootstrap/theme styles */
        .select2-container--bootstrap-5 .select2-selection__clear,
        .select2-container .select2-selection__clear {
            color: #dc3545 !important;
            font-size: 1rem !important;
            font-weight: 700 !important;
            line-height: 1 !important;
            opacity: 1 !important;
            z-index: 3;
            cursor: pointer !important;
        }

        .select2-container--bootstrap-5 .select2-selection__choice__remove,
        .select2-container .select2-selection__choice__remove {
            color: #dc3545 !important;
            font-size: 1rem !important;
            font-weight: 700 !important;
            opacity: 1 !important;
            cursor: pointer !important;
            margin-right: 4px !important;
        }
        
        /* Transaction row hover effect */
        .transaction-row:hover {
            background-color: #f8f9fa !important;
            transform: scale(1.01);
            transition: all 0.2s ease;
        }
        
        /* Modal styling */
        .form-control-plaintext {
            background-color: #f8f9fa;
            padding: 0.375rem 0.75rem;
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
            margin: 0;
        }

        .transaction-row {
            cursor: pointer !important;
        }

        /* Enhanced modal section styling */
        .modal-body h6.border-bottom {
            border-color: #dee2e6 !important;
        }

        .modal-body .form-control-plaintext {
            min-height: 38px;
            display: flex;
            align-items: center;
        }

        /* Financial amounts styling */
        /* #modal-principal, #modal-charge-partner, #modal-charge-customer {
            font-weight: bold !important;
            text-align: center;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border: 2px solid #dee2e6;
        } */

        /* Section icons */
        .modal-body .fas {
            width: 20px;
            text-align: center;
        }

        .filter-compact-select {
            width: auto;
            min-width: 120px;
            max-width: 100%;
            display: block;
        }

        .filter-action-buttons.is-compact {
            gap: 0.35rem !important;
        }

        .filter-action-buttons.is-compact .btn {
            width: auto;
            min-width: 0;
            padding-left: 0.65rem;
            padding-right: 0.65rem;
        }

        .transaction-results-group {
            min-height: 100%;
        }

        .system-table-group {
            min-height: 0;
            height: 500px;
            flex: 1 1 auto;
        }

        .system-table-group .table-responsive {
            height: 100%;
            overflow: auto;
        }
    </style>
    <style>
        /* Remove border from Principal Amount card */
        /* #modal-amount-paid {
            border: none !important;
        } */

        /* If you want to remove border from the entire card container */
        .modal-body .card {
            border: none !important;
        }

        /* Alternative: Remove border from all cards in the modal */
        .modal-body .card {
            border: none;
            box-shadow: none;
        }

        /* If you want to remove border from specific card only */
        .modal-body .card:first-child {
            border: none;
            box-shadow: none;
        }
    </style>
</head>
<body>
    <div class="main-container">
        <?php include '../../../templates/header_ui.php'; ?>
        <!-- Show and Hide Side Nav Menu -->
        <?php include '../../../templates/sidebar.php'; ?>
        <div id="loading-overlay" class="d-none" aria-live="polite" aria-busy="true">
            <div class="bg-white rounded-3 shadow p-4 text-center">
                <div class="spinner-border text-danger" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <div class="mt-2 fw-semibold text-secondary">Loading transaction data...</div>
            </div>
        </div>
        <div class="bp-section-header" role="region" aria-label="Page title">
            <div class="bp-section-title">
                <i class="fa-solid fa-file-invoice-dollar" aria-hidden="true"></i>
                <div>
                    <h2>Transaction Details Report</h2>
                    <p class="bp-section-sub">Detailed transaction filters and listing</p>
                </div>
            </div>
        </div>
        <div class="bp-card container-fluid mt-3 p-4">
            <div class="row">
                <div class="col-md-18">
                    <div class="card">
                        <div class="card-header">
                            <div class="mb-3">
                                <label id="searchHint" class="h5 text-muted" style="display:none;">Hint: <i>Double click the row to view the details</i></label>
                            </div>
                            <div class="row g-2 align-items-end">
                                <!-- Partner List -->
                                <div class="col-md-2 col-sm-6">
                                    <label for="partnerlistDropdown" class="form-label small text-muted mb-1">Partner:</label>
                                    <select id="partnerlistDropdown" class="form-select form-select-sm select2" aria-label="Select Partner" name="partnerlist" data-placeholder="Search Partner..." required>
                                        <option value="">Select Partner</option>
                                        <option value="All" selected>All</option>
                                        <!-- options will be populated by JS -->
                                    </select>
                                </div>
                                
                                <!-- Date Range -->
                                <div class="col-md-3 col-sm-6">
                                    <label class="form-label small text-muted mb-1">Transaction Date:</label>
                                    <div class="row g-1">
                                        <div class="col-6">
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text">From</span>
                                                <input type="date" 
                                                    id="start_date" 
                                                    name="start_date" 
                                                    class="form-control" 
                                                    required 
                                                    max="<?php echo date('Y-m-d'); ?>">
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text">To</span>
                                                <input type="date" 
                                                    id="end_date" 
                                                    name="end_date" 
                                                    class="form-control" 
                                                    required 
                                                    max="<?php echo date('Y-m-d'); ?>">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- CAD Status Dropdown -->
                                <div class="col-auto d-none">
                                    <label for="post_transaction_filter" class="form-label small text-muted mb-1">CAD Status:</label>
                                    <select id="post_transaction_filter" name="post_transaction" class="form-select form-select-sm filter-compact-select">
                                        <option value="All" selected>All</option>
                                        <option value="posted">Posted</option>
                                        <option value="unposted">Unposted</option>
                                    </select>
                                </div>
                                
                                <!-- Transaction Status Dropdown -->
                                <div class="col-auto">
                                    <label for="status_filter" class="form-label small text-muted mb-1">Transaction Status:</label>
                                    <select id="status_filter" name="status" class="form-select form-select-sm filter-compact-select">
                                        <option value="All">All</option>
                                        <option value="active" selected>Active</option>
                                        <option value="cancelled">Cancelled</option>
                                    </select>
                                </div>

                                <!-- Source File Dropdown -->
                                <div class="col-auto">
                                    <label for="source_file_filter" class="form-label small text-muted mb-1">Source File:</label>
                                    <select id="source_file_filter" name="source_file" class="form-select form-select-sm filter-compact-select">
                                        <option value="All" selected>All</option>
                                        <option value="KP7">KP7</option>
                                        <option value="KPX">KPX</option>
                                    </select>
                                </div>

                                <!-- Mainzone Dropdown -->
                                <div class="col-auto">
                                    <label for="mainzone_filter" class="form-label small text-muted mb-1">Mainzone:</label>
                                    <select id="mainzone_filter" name="mainzone" class="form-select form-select-sm filter-compact-select">
                                        <option value="">Select Mainzone</option>
                                        <option value="All">All</option>
                                        <!-- options will be populated by JS -->
                                    </select>
                                </div>
                                
                                <!-- Zone Dropdown -->
                                <div class="col-auto">
                                    <label for="zone_filter" class="form-label small text-muted mb-1">Zone:</label>
                                    <select id="zone_filter" name="zone" class="form-select form-select-sm filter-compact-select">
                                        <option value="">Select Zone</option>
                                        <option value="All">All</option>
                                        <!-- options will be populated by JS -->
                                    </select>
                                </div>

                                <!-- Region Dropdown -->
                                <div class="col-auto">
                                    <label for="region_filter" class="form-label small text-muted mb-1">Region:</label>
                                    <select id="region_filter" name="region" class="form-select form-select-sm filter-compact-select">
                                        <option value="">Select Region</option>
                                        <option value="All">All</option>
                                    </select>
                                </div>

                                <!-- Branch Name Dropdown -->
                                <div class="col-md-2 col-sm-6">
                                    <label for="branchDropdown" class="form-label small text-muted mb-1">Branch Name:</label>
                                    <select id="branchDropdown" class="form-select form-select-sm select2" aria-label="Select Branch Name" name="branch" data-placeholder="Search Branch Name..." required>
                                        <option value="">Select Branch Name</option>
                                        <option value="All">All</option>
                                        <!-- options will be populated by JS -->
                                    </select>
                                </div>
                                
                                <!-- Search Reference Number Input -->
                                <div class="col-md-2 col-sm-6">
                                    <label for="search_input" class="form-label small text-muted mb-1">Search Reference Number:</label>
                                    <input type="text" 
                                        id="search_input" 
                                        name="search" 
                                        class="form-control form-control-sm" 
                                        placeholder="Search...">
                                </div>
                                
                                <!-- Action Buttons -->
                                <div class="col-auto">
                                    <label class="form-label small text-muted mb-1 d-block">&nbsp;</label>
                                    <div class="d-flex gap-2 filter-action-buttons">
                                        <button type="button" id="searchButton" class="btn btn-danger btn-sm">
                                            <i class="fas fa-search"></i> Search
                                        </button>
                                        <button type="button" id="clearButton" class="btn btn-secondary btn-sm" style="display: none;">
                                            <i class="fas fa-eraser"></i> Clear
                                        </button>
                                        <button type="button" id="ExportButton" class="btn btn-danger btn-sm" style="display: none;">
                                            <i class="fas fa-download"></i> Export To
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card-body" style="display: none;">
                            <div class="row g-3 align-items-stretch">
                                <div class="col-lg-3 col-md-4">
                                    <div class="card mb-3 h-100">
                                        <div class="card-body">
                                            <div class="fw-semibold text-center mb-3">Summary Results</div>

                                            <div class="mb-3">
                                                <div class="fw-semibold text-danger mb-2">Summary</div>
                                                <div class="d-flex justify-content-between border-bottom py-1">
                                                    <span class="text-muted small">Volume</span>
                                                    <span id="summaryVolume" class="fw-bold">0</span>
                                                </div>
                                                <div class="d-flex justify-content-between border-bottom py-1">
                                                    <span class="text-muted small">Principal</span>
                                                    <span id="summaryPrincipal" class="fw-bold">0.00</span>
                                                </div>
                                                <div class="d-flex justify-content-between border-bottom py-1">
                                                    <span class="text-muted small">Charge to Partner</span>
                                                    <span id="summaryChargePartner" class="fw-bold">0.00</span>
                                                </div>
                                                <div class="d-flex justify-content-between border-bottom py-1">
                                                    <span class="text-muted small">Charge to Customer</span>
                                                    <span id="summaryChargeCustomer" class="fw-bold">0.00</span>
                                                </div>
                                                <div class="d-flex justify-content-between py-1">
                                                    <span class="text-muted small">Total Charge</span>
                                                    <span id="summaryTotalCharge" class="fw-bold">0.00</span>
                                                </div>
                                            </div>

                                            <div class="mb-3">
                                                <div class="fw-semibold text-danger mb-2">Adjustment</div>
                                                <div class="d-flex justify-content-between border-bottom py-1">
                                                    <span class="text-muted small">Volume</span>
                                                    <span id="adjustmentVolume" class="fw-bold">0</span>
                                                </div>
                                                <div class="d-flex justify-content-between border-bottom py-1">
                                                    <span class="text-muted small">Principal</span>
                                                    <span id="adjustmentPrincipal" class="fw-bold">0.00</span>
                                                </div>
                                                <div class="d-flex justify-content-between border-bottom py-1">
                                                    <span class="text-muted small">Charge to Partner</span>
                                                    <span id="adjustmentChargePartner" class="fw-bold">0.00</span>
                                                </div>
                                                <div class="d-flex justify-content-between border-bottom py-1">
                                                    <span class="text-muted small">Charge to Customer</span>
                                                    <span id="adjustmentChargeCustomer" class="fw-bold">0.00</span>
                                                </div>
                                                <div class="d-flex justify-content-between py-1">
                                                    <span class="text-muted small">Total Charge</span>
                                                    <span id="adjustmentTotalCharge" class="fw-bold">0.00</span>
                                                </div>
                                            </div>

                                            <div>
                                                <div class="fw-semibold text-danger mb-2">Net</div>
                                                <div class="d-flex justify-content-between border-bottom py-1">
                                                    <span class="text-muted small">Volume</span>
                                                    <span id="netVolume" class="fw-bold">0</span>
                                                </div>
                                                <div class="d-flex justify-content-between border-bottom py-1">
                                                    <span class="text-muted small">Principal</span>
                                                    <span id="netPrincipal" class="fw-bold">0.00</span>
                                                </div>
                                                <div class="d-flex justify-content-between border-bottom py-1">
                                                    <span class="text-muted small">Charge to Partner</span>
                                                    <span id="netChargePartner" class="fw-bold">0.00</span>
                                                </div>
                                                <div class="d-flex justify-content-between border-bottom py-1">
                                                    <span class="text-muted small">Charge to Customer</span>
                                                    <span id="netChargeCustomer" class="fw-bold">0.00</span>
                                                </div>
                                                <div class="d-flex justify-content-between border-bottom py-1">
                                                    <span class="text-muted small">Total Charge</span>
                                                    <span id="netTotalCharge" class="fw-bold">0.00</span>
                                                </div>
                                                <div class="d-flex justify-content-between py-1">
                                                    <span class="text-muted small">Settlement Amount</span>
                                                    <span id="netSettlementAmount" class="fw-bold">0.00</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-9 col-md-8">
                            <div class="transaction-results-group h-100 d-flex flex-column">
                            <div class="system-table-group">
                            <div class="table-responsive">
                                <table id="transactionReportTable" class="table table-bordered table-hover table-striped">
                                    <thead class="table-light sticky-top">
                                        <tr>
                                            <th rowspan="2" class='text-truncate text-center align-middle'>Transaction Date</th>
                                            <th rowspan="2" class='text-truncate text-center align-middle'>Cancelled Date</th>
                                            <th rowspan="2" class='text-truncate text-center align-middle'>Reference Number</th>
                                            <th rowspan="2" class='text-truncate text-center align-middle'>Branch ID</th>
                                            <th rowspan="2" class='text-truncate text-center align-middle'>Branch Name</th>
                                            <th rowspan="2" class='text-truncate text-center align-middle'>Source</th>
                                            <th rowspan="2" class='text-truncate text-center align-middle'>Partner Name</th>
                                            <th colspan="2" class='text-truncate text-center align-middle'>Partner ID</th>
                                            <th rowspan="2" class='text-truncate text-center align-middle'>Principal Amount</th>
                                            <th colspan="2" class='text-truncate text-center align-middle'>Charge to</th>
                                            <th rowspan="2" class='text-truncate text-center align-middle'>Billing Invoice</th>
                                            <th rowspan="2" class='text-truncate text-center align-middle'>CAD Status</th>
                                            <th rowspan="2" class='text-truncate text-center align-middle'>Transaction Status</th>
                                        </tr>
                                        <tr>
                                            <th class="text-center">KP7</th>
                                            <th class="text-center">KPX</th>
                                            <th class="text-center">Partner</th>
                                            <th class="text-center">Customer</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <!-- Data will be populated via JavaScript -->
                                    </tbody>
                                </table>
                            </div>
                            
                            </div>
                            
                            <!-- Pagination Controls -->
                            <div class="pagination-controls-group d-flex justify-content-between align-items-center pt-3">
                                <div class="d-flex align-items-center">
                                    <span class="me-2">Show:</span>
                                    <select id="rowsPerPage" class="form-select form-select-sm" style="width: auto;">
                                        <option value="15" selected>15</option>
                                        <option value="25">25</option>
                                        <option value="50">50</option>
                                        <option value="100">100</option>
                                    </select>
                                    <span class="ms-2">entries</span>
                                </div>
                                
                                <div id="pagination-info" class="text-muted">
                                    Showing 0 to 0
                                </div>
                                
                                <nav aria-label="Table pagination">
                                    <ul id="pagination" class="pagination pagination-sm mb-0">
                                        <!-- Pagination will be generated by JavaScript -->
                                    </ul>
                                </nav>
                            </div>
                            </div>
                                </div>
                            </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Transaction Details Modal -->
    <div class="modal fade" id="transactionDetailsModal" tabindex="-1" aria-labelledby="transactionDetailsModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="transactionDetailsModalLabel">
                        <i class="fas fa-receipt me-2"></i>Transaction Details
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="container-fluid">
                        <div class="row">
                            <div class="col-md-6 pb-3">
                                <h6 class=" border-bottom pb-2">
                                    <i class="fas fa-info-circle text-danger"></i> Transaction Information
                                </h6>
                                <table>
                                    <tbody>
                                        <tr>
                                            <td style="width: 180px;">
                                                <strong>CAD Status:</strong>
                                            </td>
                                            <td>
                                                <span id="modal-cad-status" class="text-muted"></span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>
                                                <strong>Source:</strong>
                                            </td>
                                            <td>
                                                <span id="modal-source-file" class="text-muted"></span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>
                                                <strong>Transaction Date:</strong>
                                            </td>
                                            <td>
                                                <span id="modal-datetime" class="text-muted"></span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>
                                                <strong>Cancelled Date:</strong>
                                            </td>
                                            <td>
                                                <span id="modal-cancelled-date" class="text-muted"></span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>
                                                <strong>Reference Number:</strong>
                                            </td>
                                            <td>
                                                <mark>
                                                    <span id="modal-reference-no" class="text-muted"></span>
                                                </mark>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>
                                                <strong>Control Number:</strong>
                                            </td>
                                            <td>
                                                <mark>
                                                    <span id="modal-control-number" class="text-muted"></span>
                                                </mark>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>
                                                <strong>Billing Invoice:</strong>
                                            </td>
                                            <td>
                                                <span id="modal-billing-invoice" class="text-muted"></span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>
                                                <strong>Transaction Status:</strong>
                                            </td>
                                            <td>
                                                <span id="modal-status" class="text-muted"></span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <div class="col-md-6 pb-3">
                                <h6 class="border-bottom pb-2">
                                    <i class="fas fa-university text-danger"></i> Branch Information
                                </h6>
                                <table>
                                    <tbody>
                                        <tr>
                                            <td style="width: 130px;">
                                                <strong>Mainzone:</strong>
                                            </td>
                                            <td>
                                                <span id="modal-mainzone" class="text-muted"></span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>
                                                <strong>Zone:</strong>
                                            </td>
                                            <td>
                                                <span id="modal-zone-code" class="text-muted"></span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>
                                                <strong>Region Code:</strong>
                                            </td>
                                            <td>
                                                <span id="modal-region-code" class="text-muted"></span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>
                                                <strong>Region Name:</strong>
                                            </td>
                                            <td>
                                                <span id="modal-region-name" class="text-muted"></span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>
                                                <strong>Branch Name:</strong>
                                            </td>
                                            <td>
                                                <span id="modal-outlet" class="text-muted"></span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>
                                                <strong>Branch ID:</strong>
                                            </td>
                                            <td>
                                                <span id="modal-branch-id" class="text-muted"></span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>
                                                <strong>Branch Code:</strong>
                                            </td>
                                            <td>
                                                <span id="modal-branch-code" class="text-muted"></span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>   
                        <div class="row">
                            <div class="col-md-12 pb-3">
                                <!-- <h6 class="border-bottom pb-2">
                                    <i class="fas fa-building text-danger"></i> Partner Information
                                </h6> -->
                                <table>
                                    <tbody>
                                        <tr>
                                            <td style="width: 180px;">
                                                <strong>Partner Name:</strong>
                                            </td>
                                            <td>
                                                <span id="modal-partner-name" class="text-muted"></span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>
                                                <strong>Partner ID (KP7):</strong>
                                            </td>
                                            <td>
                                                <span id="modal-partner-id" class="text-muted"></span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>
                                                <strong>Partner ID (KPX):</strong>
                                            </td>
                                            <td>
                                                <span id="modal-partner-id-kpx" class="text-muted"></span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>
                                                <strong>GL Code:</strong>
                                            </td>
                                            <td>
                                                <span id="modal-gl-code" class="text-muted"></span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>
                                                <strong>GL Description:</strong>
                                            </td>
                                            <td>
                                                <span id="modal-gl-description" class="text-muted"></span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12 pb-3">
                                <!-- <h6 class="border-bottom pb-2">
                                    <i class="fas fa-credit-card-alt text-danger"></i> Payor Information
                                </h6> -->
                                <table>
                                    <tbody>
                                        <tr>
                                            <td style="width: 180px;">
                                                <strong>Payor Name:</strong>
                                            </td>
                                            <td>
                                                <span id="modal-payor-name" class="text-muted"></span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>
                                                <strong>Account Number:</strong>
                                            </td>
                                            <td>
                                                <span id="modal-account-number" class="text-muted"></span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>
                                                <strong>Account Name:</strong>
                                            </td>
                                            <td>
                                                <span id="modal-account-name" class="text-muted"></span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>
                                                <strong>Address:</strong>
                                            </td>
                                            <td>
                                                <span id="modal-address" class="text-muted"></span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>
                                                <strong>Contact Number:</strong>
                                            </td>
                                            <td>
                                                <span id="modal-contact-number" class="text-muted"></span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>
                                                <strong>Operator:</strong>
                                            </td>
                                            <td>
                                                <span id="modal-operator" class="text-muted"></span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>
                                                <strong>Remote Branch:</strong>
                                            </td>
                                            <td>
                                                <span id="modal-remote-branch" class="text-muted"></span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>
                                                <strong>Remote Operator:</strong>
                                            </td>
                                            <td>
                                                <span id="modal-remote-operator" class="text-muted"></span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>
                                                <strong>Second Approver:</strong>
                                            </td>
                                            <td>
                                                <span id="modal-second-approver" class="text-muted"></span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12 pb-3">
                                <!-- <h6 class="border-bottom pb-2">
                                    <i class="fas fa-user text-danger"></i> Personnel Information
                                </h6> -->
                                <table>
                                    <tbody>
                                        <tr>
                                            <td style="width: 180px;">
                                                <strong>Uploaded By:</strong>
                                            </td>
                                            <td>
                                                <span id="modal-uploaded-by" class="text-muted">Test</span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>
                                                <strong>Uploaded Date:</strong>
                                            </td>
                                            <td>
                                                <span id="modal-uploaded-date" class="text-muted">01-01-2026</span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12">
                                <!-- <h6 class="border-bottom pb-2">
                                    <i class="fas fa-money-bill-wave text-danger"></i> Financial Details
                                </h6> -->
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="card">
                                            <div class="card-body text-center">
                                                <h6 class="card-title">Principal Amount</h6>
                                                <h4 id="modal-amount-paid" class="card-text text-danger fw-bold">₱0.00</h4>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="card">
                                            <div class="card-body text-center">
                                                <h6 class="card-title">Charge to Partner</h6>
                                                <h4 id="modal-charge-partner" class="card-text text-danger fw-bold">₱0.00</h4>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="card">
                                            <div class="card-body text-center">
                                                <h6 class="card-title">Charge to Customer</h6>
                                                <h4 id="modal-charge-customer" class="card-text text-danger fw-bold">₱0.00</h4>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>Close
                    </button>
                </div> -->
            </div>
        </div>
    </div>
</body>
<?php include '../../../templates/footer.php'; ?>
<!-- Include Select2 for partner dropdown -->
<script>
    $(document).ready(function() {
        // Initialize Select2 for partner dropdown
        $('#partnerlistDropdown').select2({
            placeholder: 'Search or select a Partner...',
            allowClear: true
        });

        // Keep dropdown behavior stable: close immediately after choosing a partner.
        $('#partnerlistDropdown').on('select2:select', function() {
            if ($(this).val()) {
                $('#status_filter').val('active');
            }
            $(this).select2('close');
        });

        $('#partnerlistDropdown').on('change', function() {
            if ($(this).val()) {
                $('#status_filter').val('active');
            } else {
                $('#status_filter').val('All');
            }
        });

        // Fetch partner list from server
        // Load partners on page load
        loadPartners();
        
        function loadPartners() {
            $.ajax({
                url: '../../../fetch/get_partners.php',
                type: 'GET',
                dataType: 'json',
                success: function(result) {
                    if (result && result.success === true && Array.isArray(result.data)) {
                        const select = $('#partnerlistDropdown');

                        // Keep default static options only, then append fetched partners.
                        select.find('option').not('[value=""]').not('[value="All"]').remove();

                        result.data.forEach(partner => {
                            if (partner && partner.partner_name) {
                                select.append(new Option(partner.partner_name, partner.partner_name));
                            }
                        });
                    } else {
                        console.error('Error loading partners: Invalid response format', result);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error loading partners:', { xhr: xhr, status: status, error: error });
                }
            });
        }
    });
</script>

<!-- Include for mainzone dropdown -->
<script>
    $(document).ready(function() {
        $.ajax({
            url: '../../../fetch/zoning/get_mainzone.php',
            type: 'GET',
            dataType: 'json',
            success: function(result) {
                if (result && result.success === true && Array.isArray(result.data)) {
                    const select = $('#mainzone_filter');

                    // Keep default static options only, then append fetched mainzones.
                    select.find('option').not('[value=""]').not('[value="All"]').remove();

                    result.data.forEach(item => {
                        // Support different shapes: string array or objects with a `mainzone`/`name` field
                        let value = null;
                        if (typeof item === 'string') {
                            value = item;
                        } else if (item && (item.mainzone || item.name)) {
                            value = item.mainzone || item.name;
                        } else if (item && item.value) {
                            value = item.value;
                        }

                        if (value) {
                            select.append(new Option(value, value));
                        }
                    });
                } else {
                    console.error('Error loading mainzones: Invalid response format', result);
                }
            },
            error: function(xhr, status, error) {
                console.error('Error loading mainzones:', { xhr: xhr, status: status, error: error });
            }
        });
    });
</script>

<!-- Include for zone dropdown -->
<script>
    $(document).ready(function() {
        function loadZones(mainzone) {
            const select = $('#zone_filter');
            // If no mainzone selected, keep only the default placeholder option (and "All")
            if (!mainzone || mainzone === '' || mainzone === 'Select Mainzone') {
                // Keep only the placeholder option (no 'All') when no mainzone selected
                select.find('option').not('[value=""]').not('[value=""]').remove();
                // ensure only the first placeholder remains
                select.find('option').not(':first').remove();
                return;
            }

            const params = {};
            // If selected mainzone is 'All', request without mainzone param to return all zones
            if (mainzone !== 'All') params.mainzone = mainzone;
            $.ajax({
                url: '../../../fetch/zoning/get_zone-code.php',
                type: 'GET',
                data: params,
                dataType: 'json',
                success: function(result) {
                    if (result && result.success === true && Array.isArray(result.data)) {
                        const select = $('#zone_filter');

                        // Keep only the first placeholder option, then append fetched zones.
                        const placeholder = select.find('option').first();
                        select.find('option').not(':first').remove();

                        // If requested for all zones (mainzone==='All'), ensure 'All' option will be added after placeholder
                        const requestedAll = (mainzone === 'All');
                        result.data.forEach(item => {
                            let value = null;
                            if (typeof item === 'string') {
                                value = item;
                            } else if (item && (item.zone || item.name)) {
                                value = item.zone || item.name;
                            } else if (item && item.value) {
                                value = item.value;
                            }
                            if (value) {
                                select.append(new Option(value, value));
                            }
                        });

                        if (requestedAll) {
                            // Ensure 'All' exists immediately after the placeholder
                            if (select.find('option[value="All"]').length === 0) {
                                placeholder.after(new Option('All', 'All'));
                            } else {
                                const allOpt = select.find('option[value="All"]').remove();
                                placeholder.after(allOpt);
                            }
                            // Ensure 'SHOWROOM' (value 'Showroom') is present — place after 'VIS' if present, otherwise at end
                            if (select.find('option[value="Showroom"]').length === 0) {
                                const showroomOpt = new Option('SHOWROOM', 'Showroom');
                                if (select.find('option[value="VIS"]').length) {
                                    select.find('option[value="VIS"]').after(showroomOpt);
                                } else {
                                    select.append(showroomOpt);
                                }
                            } else {
                                const showroomOpt = select.find('option[value="Showroom"]').remove();
                                if (select.find('option[value="VIS"]').length) {
                                    select.find('option[value="VIS"]').after(showroomOpt);
                                } else {
                                    select.append(showroomOpt);
                                }
                            }
                        }

                        // Also include 'All' and 'SHOWROOM' for LNCR-specific request
                        if (mainzone === 'LNCR' || mainzone === 'VISMIN') {
                            if (select.find('option[value="All"]').length === 0) {
                                placeholder.after(new Option('All', 'All'));
                            } else {
                                const allOpt = select.find('option[value="All"]').remove();
                                placeholder.after(allOpt);
                            }

                            // Ensure SHOWROOM placed after VIS when possible
                            if (select.find('option[value="Showroom"]').length === 0) {
                                const showroomOpt = new Option('SHOWROOM', 'Showroom');
                                if (select.find('option[value="VIS"]').length) {
                                    select.find('option[value="VIS"]').after(showroomOpt);
                                } else {
                                    select.append(showroomOpt);
                                }
                            } else {
                                const showroomOpt = select.find('option[value="Showroom"]').remove();
                                if (select.find('option[value="VIS"]').length) {
                                    select.find('option[value="VIS"]').after(showroomOpt);
                                } else {
                                    select.append(showroomOpt);
                                }
                            }
                        }
                    } else {
                        console.error('Error loading zones: Invalid response format', result);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error loading zones:', { xhr: xhr, status: status, error: error });
                }
            });
        }

        // Always run loadZones on initial load to enforce correct placeholder state
        const initialMainzone = $('#mainzone_filter').val();
        loadZones(initialMainzone);

        // Reload zones whenever mainzone changes
        $('#mainzone_filter').on('change', function() {
            const mz = $(this).val();
            // normalize placeholder
            const normMz = (mz === 'Select Mainzone') ? '' : mz;
            loadZones(normMz);
            // when mainzone changes, reset region dropdown to placeholder only immediately
            const regionSelect = $('#region_filter');
            const firstOpt = regionSelect.find('option').first();
            regionSelect.find('option').not(':first').remove();
            regionSelect.val(firstOpt.val());
            // also call loadRegions('') to ensure any logic-run cleanup
            if (typeof loadRegions === 'function') loadRegions('');
            // reset branch dropdown and reload branches filtered by mainzone
            const branchSelect = $('#branchDropdown');
            branchSelect.find('option').not(':first').remove();
            try { branchSelect.val(null).trigger('change'); } catch (e) { /* ignore */ }
            loadBranches(normMz, '', '');
        });
    });
</script>

<!-- Include for region dropdown -->
<script>
    $(document).ready(function() {
        function loadRegions(zone) {
            const select = $('#region_filter');
            // If no zone selected, keep only the default placeholder option (and "All")
            if (!zone || zone === '' || zone === 'Select Zone') {
                // Keep only the placeholder option (no 'All') when no zone selected
                select.find('option').not('[value=""]').not('[value=""]').remove();
                // ensure only the first placeholder remains
                select.find('option').not(':first').remove();
                return;
            }

            const params = {};
            // If selected zone is 'All', we may need to special-case by mainzone
            const currentMainzone = $('#mainzone_filter').val();

            if (zone === 'All' && currentMainzone === 'VISMIN') {
                // For VISMIN + All: fetch regions for VIS and MIN, combine unique
                const reqVIS = $.ajax({ url: '../../../fetch/zoning/get_region-code.php', type: 'GET', data: { zone: 'VIS' }, dataType: 'json' });
                const reqMIN = $.ajax({ url: '../../../fetch/zoning/get_region-code.php', type: 'GET', data: { zone: 'MIN' }, dataType: 'json' });

                $.when(reqVIS, reqMIN).done(function(visRes, minRes) {
                    const visData = (visRes && visRes[0] && visRes[0].success && Array.isArray(visRes[0].data)) ? visRes[0].data : [];
                    const minData = (minRes && minRes[0] && minRes[0].success && Array.isArray(minRes[0].data)) ? minRes[0].data : [];

                    // combine and dedupe by region_code (or string)
                    const combinedMap = {};
                    visData.concat(minData).forEach(item => {
                        let key = null;
                        let desc = null;
                        if (typeof item === 'string') {
                            key = item; desc = item;
                        } else if (item && item.region_code) {
                            key = item.region_code; desc = item.region_description || item.region_code;
                        }
                        if (key && !combinedMap[key]) combinedMap[key] = desc;
                    });

                    const select = $('#region_filter');
                    const placeholder = select.find('option').first();
                    select.find('option').not(':first').remove();

                    // Insert 'All' after placeholder
                    placeholder.after(new Option('All', 'All'));

                    // Append combined regions
                    Object.keys(combinedMap).forEach(k => {
                        const label = combinedMap[k] || k;
                        select.append(new Option(label, k));
                    });

                    // Insert showroom entries after the last region so they appear below the list
                    select.append(new Option('VISAYAS SHOWROOM', 'VIS'));
                    select.append(new Option('MINDANAO SHOWROOM', 'MIN'));
                }).fail(function() {
                    console.error('Error loading VIS/MIN regions');
                });

                return;
            }

            if (zone === 'All' && currentMainzone === 'LNCR') {
                // For LNCR + All: fetch the zones for LNCR, then fetch regions for each zone and combine
                $.ajax({
                    url: '../../../fetch/zoning/get_zone-code.php',
                    type: 'GET',
                    data: { mainzone: 'LNCR' },
                    dataType: 'json',
                    success: function(zres) {
                        if (zres && zres.success === true && Array.isArray(zres.data)) {
                            const zones = zres.data.map(it => (typeof it === 'string') ? it : (it.zone || it.name || it.value)).filter(Boolean);
                            const reqs = zones.map(z => $.ajax({ url: '../../../fetch/zoning/get_region-code.php', type: 'GET', data: { zone: z }, dataType: 'json' }));
                            $.when.apply($, reqs).done(function() {
                                const responses = Array.from(arguments);
                                const allData = [];
                                if (reqs.length === 1) {
                                    const single = responses[0];
                                    if (single && single[0] && single[0].success && Array.isArray(single[0].data)) allData.push.apply(allData, single[0].data);
                                } else {
                                    responses.forEach(r => { if (r && r[0] && r[0].success && Array.isArray(r[0].data)) allData.push.apply(allData, r[0].data); });
                                }

                                // dedupe
                                const combinedMap = {};
                                allData.forEach(item => {
                                    let key = null; let desc = null;
                                    if (typeof item === 'string') { key = item; desc = item; }
                                    else if (item && item.region_code) { key = item.region_code; desc = item.region_description || item.region_code; }
                                    if (key && !combinedMap[key]) combinedMap[key] = desc;
                                });

                                const select = $('#region_filter');
                                const placeholder = select.find('option').first();
                                select.find('option').not(':first').remove();
                                placeholder.after(new Option('All', 'All'));
                                Object.keys(combinedMap).forEach(k => select.append(new Option(combinedMap[k], k)));

                                // LNCR showroom entries
                                select.append(new Option('LUZON SHOWROOM', 'LZN'));
                                select.append(new Option('NCR SHOWROOM', 'NCR'));
                            }).fail(function() { console.error('Error loading LNCR regions'); });
                        } else {
                            console.error('Failed to fetch LNCR zones', zres);
                        }
                    },
                    error: function() { console.error('Failed to fetch zones for LNCR'); }
                });

                return;
            }

            // Special case: when user selects Showroom, show showroom entries only
            if (zone === 'Showroom' && (currentMainzone === 'VISMIN' || currentMainzone === 'LNCR' || currentMainzone === 'All')) {
                const select = $('#region_filter');
                const placeholder = select.find('option').first();
                select.find('option').not(':first').remove();

                // Insert 'All' after placeholder
                placeholder.after(new Option('All', 'All'));

                if (currentMainzone === 'LNCR') {
                    select.append(new Option('LUZON SHOWROOM', 'LZN'));
                    select.append(new Option('NCR SHOWROOM', 'NCR'));
                } else if (currentMainzone === 'All') {
                    select.append(new Option('LUZON SHOWROOM', 'LZN'));
                    select.append(new Option('NCR SHOWROOM', 'NCR'));
                    select.append(new Option('VISAYAS SHOWROOM', 'VIS'));
                    select.append(new Option('MINDANAO SHOWROOM', 'MIN'));
                } else {
                    select.append(new Option('VISAYAS SHOWROOM', 'VIS'));
                    select.append(new Option('MINDANAO SHOWROOM', 'MIN'));
                }

                return;
            }

            // default behavior: If selected zone is 'All', request without zone param to return all regions
            if (zone !== 'All') params.zone = zone;
            $.ajax({
                url: '../../../fetch/zoning/get_region-code.php',
                type: 'GET',
                data: params,
                dataType: 'json',
                success: function(result) {
                    if (result && result.success === true && Array.isArray(result.data)) {
                        const select = $('#region_filter');

                        // Keep only the first placeholder option, then append fetched regions.
                        const placeholder = select.find('option').first();
                        select.find('option').not(':first').remove();

                        // Determine special modes
                        const singleZoneAllList = ['VIS','MIN','LZN','NCR'];
                        const isSingleZoneAll = (zone && singleZoneAllList.indexOf(zone) !== -1);
                        const isGlobalAll = (zone === 'All' && currentMainzone === 'All');

                        if (isSingleZoneAll) {
                            // insert All immediately after placeholder
                            if (select.find('option[value="All"]').length === 0) {
                                placeholder.after(new Option('All', 'All'));
                            } else {
                                const allOpt = select.find('option[value="All"]').remove();
                                placeholder.after(allOpt);
                            }
                        }
                        if (isGlobalAll) {
                            // ensure All exists immediately after placeholder for global All
                            if (select.find('option[value="All"]').length === 0) {
                                placeholder.after(new Option('All', 'All'));
                            }
                        }

                        result.data.forEach(item => {
                            let val = null;
                            let label = null;
                            if (typeof item === 'string') {
                                val = item;
                                label = item;
                            } else if (item && item.region_code) {
                                val = item.region_code;
                                // Show only region_description as label when available
                                label = (item.region_description && item.region_description.length) ? item.region_description : item.region_code;
                            } else if (item && item.name) {
                                val = item.name;
                                label = item.name;
                            } else if (item && item.value) {
                                val = item.value;
                                label = item.value;
                            }
                                if (val) {
                                    select.append(new Option(label, val));
                                }
                        });

                            if (isGlobalAll) {
                                // after all regions, append showroom entries for all mainzones
                                select.append(new Option('LUZON SHOWROOM', 'LZN'));
                                select.append(new Option('NCR SHOWROOM', 'NCR'));
                                select.append(new Option('VISAYAS SHOWROOM', 'VIS'));
                                select.append(new Option('MINDANAO SHOWROOM', 'MIN'));
                            }
                    } else {
                        console.error('Error loading regions: Invalid response format', result);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error loading regions:', { xhr: xhr, status: status, error: error });
                }
            });
        }
        // Initial run to set correct placeholder state on page load
        let initialZone = $('#zone_filter').val();
        if (initialZone === 'Select Zone') initialZone = '';
        loadRegions(initialZone);

        // Reload regions whenever zone changes
        $('#zone_filter').on('change', function() {
            let z = $(this).val();
            // normalize placeholder text value
            if (z === 'Select Zone') z = '';
            console.debug('zone changed ->', z);
            loadRegions(z);
            // reset branch dropdown and reload branches filtered by zone
            const branchSelect = $('#branchDropdown');
            branchSelect.find('option').not(':first').remove();
            try { branchSelect.val(null).trigger('change'); } catch (e) { /* ignore */ }
            let mz = $('#mainzone_filter').val();
            if (mz === 'Select Mainzone') mz = '';
            loadBranches(mz, z, '');
        });
        // If zone is a Select2 control, also handle select2:select
        $('#zone_filter').on('select2:select', function(e) {
            let z = $(this).val();
            if (z === 'Select Zone') z = '';
            console.debug('zone select2:select ->', z, e);
            loadRegions(z);
            // reset branch dropdown and reload branches filtered by zone
            const branchSelect = $('#branchDropdown');
            branchSelect.find('option').not(':first').remove();
            try { branchSelect.val(null).trigger('change'); } catch (e) { /* ignore */ }
            let mz = $('#mainzone_filter').val();
            if (mz === 'Select Mainzone') mz = '';
            loadBranches(mz, z, '');
        });
    });
</script>

<!-- Include for branch dropdown -->
<script>
    $(document).ready(function() {
        // Initialize Select2 for branch dropdown
        $('#branchDropdown').select2({
            placeholder: 'Search or select a Branch...',
            allowClear: true
        });

        // Keep dropdown behavior stable: close immediately after choosing a partner.
        // Keep dropdown behavior stable: close immediately after choosing branch (like partner)
        $('#branchDropdown').on('select2:select', function() {
            $(this).select2('close');
        });

        // Load branches for current filters and bind to region changes
        function loadBranches(mainzone, zone, region) {
            const select = $('#branchDropdown');

            // Normalize: treat empty strings and placeholder texts as no selection
            const mainzoneSet = mainzone && mainzone !== '' && mainzone !== 'Select Mainzone';
            const zoneSet = zone && zone !== '' && zone !== 'Select Zone';
            const regionSet = region && region !== '' && region !== 'Select Region';

            // clear non-placeholder options
            select.find('option:not(:first)').remove();
            // reset Select2 value to placeholder
            try {
                select.val(null).trigger('change');
            } catch (e) { /* ignore */ }

            // If no parent filter has a real selection, skip the AJAX call entirely
            if (!mainzoneSet && !zoneSet && !regionSet) {
                return;
            }

            // Only show "All" option if at least one parent filter has a real selection
            if (mainzoneSet || zoneSet || regionSet) {
                select.append(new Option('All', 'All'));
            }

            const params = {};
            if (mainzoneSet) params.mainzone = mainzone;
            if (zoneSet) params.zone = zone;
            if (regionSet) params.region = region;

            $.ajax({
                url: '../../../fetch/zoning/get_branch.php',
                type: 'GET',
                data: params,
                dataType: 'json',
                success: function(res) {
                    console.debug('get_branch response:', res);
                    if (res && res.success === true && Array.isArray(res.data) && res.data.length) {
                        // append options then refresh Select2
                        res.data.forEach(b => {
                            if (b && b.branch_id && b.branch_name) {
                                select.append(new Option(b.branch_name, b.branch_id));
                            }
                        });
                        try {
                            // Re-render options while preserving clear-button behavior.
                            select.trigger('change.select2');
                            if (select.data('select2')) {
                                select.select2('destroy');
                            }
                            select.select2({
                                placeholder: 'Search or select a Branch...',
                                allowClear: true
                            });
                        } catch (e) { /* ignore */ }
                    } else {
                        console.warn('No branches returned for params', params, res);
                    }
                },
                error: function(xhr, status, err) { console.error('Branch request failed', status, err, xhr && xhr.responseText); }
            });
        }

        // Initial load and on region change
        const initialMainzone = $('#mainzone_filter').val();
        const initialZone = $('#zone_filter').val();
        const initialRegion = $('#region_filter').val();
        loadBranches(initialMainzone, initialZone, initialRegion);

        $('#region_filter').on('change select2:select', function() {
            let mz = $('#mainzone_filter').val();
            let z = $('#zone_filter').val();
            let r = $(this).val();
            if (mz === 'Select Mainzone') mz = '';
            if (z === 'Select Zone') z = '';
            if (r === 'Select Region') r = '';

            const branchSelect = $('#branchDropdown');

            // When region is reset to placeholder, clear branch dropdown completely (no All)
            if (r === '' || (mz === '' && z === '')) {
                branchSelect.find('option').not(':first').remove();
                try { branchSelect.val(null).trigger('change'); } catch (e) { /* ignore */ }
            } else {
                loadBranches(mz, z, r);
            }
        });
    });
</script>

<!-- display Data Table Result -->
<script>
$(document).ready(function() {
    // Trigger search on button click
    $('#searchButton').off('click').on('click', function() {
        $('#clearButton').show();
        setActionButtonsCompact(true);
        fetchTransactionData(1);
    });

    // Trigger search on rows per page change
    $('#rowsPerPage').off('change').on('change', function() {
        fetchTransactionData(1);
    });

    // Export button click handler
    $('#ExportButton').off('click').on('click', function() {
        showExportModal();
    });

    $('#clearButton').off('click').on('click', function() {
        $(this).hide();
        setActionButtonsCompact(false);
        clearReportFiltersAndResults();
    });

    function setActionButtonsCompact(isCompact) {
        $('.filter-action-buttons').toggleClass('is-compact', isCompact);
        if (isCompact) {
            $('#searchButton').html('<i class="fas fa-search"></i> Search').removeAttr('title');
            $('#clearButton').html('<i class="fas fa-eraser"></i> Clear').removeAttr('title');
            $('#ExportButton').html('<i class="fas fa-download"></i> Export To').removeAttr('title');
        } else {
            $('#searchButton').html('<i class="fas fa-search"></i> Search').removeAttr('title');
            $('#clearButton').html('<i class="fas fa-eraser"></i> Clear').removeAttr('title');
            $('#ExportButton').html('<i class="fas fa-download"></i> Export To').removeAttr('title');
        }
    }

    // Format currency
    function formatCurrency(value) {
        const num = parseFloat(value) || 0;
        return '₱' + num.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function formatAmount(value) {
        const num = Math.abs(parseFloat(value) || 0);
        return num.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function formatSummaryAmount(value) {
        const num = parseFloat(value) || 0;
        return num.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function createSummaryBucket() {
        return {
            volume: 0,
            principal: 0,
            chargePartner: 0,
            chargeCustomer: 0,
            totalCharge: 0
        };
    }

    function applySummaryBucket(prefix, bucket) {
        $('#' + prefix + 'Volume').text(bucket.volume.toLocaleString('en-PH'));
        $('#' + prefix + 'Principal').text(formatSummaryAmount(bucket.principal));
        $('#' + prefix + 'ChargePartner').text(formatSummaryAmount(bucket.chargePartner));
        $('#' + prefix + 'ChargeCustomer').text(formatSummaryAmount(bucket.chargeCustomer));
        $('#' + prefix + 'TotalCharge').text(formatSummaryAmount(bucket.totalCharge));
    }

    function renderSummaryResults(summaryData) {
        const summary = summaryData && summaryData.summary ? summaryData.summary : createSummaryBucket();
        const adjustment = summaryData && summaryData.adjustment ? summaryData.adjustment : createSummaryBucket();
        const net = summaryData && summaryData.net ? summaryData.net : {
            volume: 0,
            principal: 0,
            chargePartner: 0,
            chargeCustomer: 0,
            totalCharge: 0,
            settlementAmount: 0
        };

        applySummaryBucket('summary', summary);
        applySummaryBucket('adjustment', adjustment);
        applySummaryBucket('net', net);
        $('#netSettlementAmount').text(formatSummaryAmount(net.settlementAmount || 0));
    }

    // Escape HTML to prevent XSS
    function esc(str) {
        if (str === null || str === undefined) return '';
        const div = document.createElement('div');
        div.textContent = String(str);
        return div.innerHTML;
    }

    // Format MySQL datetime to "January 01, 2026"
    function formatDisplayDate(dt) {
        if (!dt) return '';
        const d = new Date(dt.replace(' ', 'T'));
        if (isNaN(d.getTime())) return dt;
        const months = ['January', 'February', 'March', 'April', 'May', 'June',
                       'July', 'August', 'September', 'October', 'November', 'December'];
        return months[d.getMonth()] + ' ' + String(d.getDate()).padStart(2, '0') + ', ' + d.getFullYear();
    }

    function clearReportFiltersAndResults() {
        $('#partnerlistDropdown').val(null).trigger('change');
        $('#start_date, #end_date, #search_input').val('');
        $('#post_transaction_filter, #status_filter, #source_file_filter').val('All');
        $('#mainzone_filter').val('').trigger('change');
        $('#zone_filter').html('<option value="">Select Zone</option><option value="All">All</option>').val('');
        $('#region_filter').html('<option value="">Select Region</option><option value="All">All</option>').val('');
        $('#branchDropdown').html('<option value="">Select Branch Name</option><option value="All">All</option>').val(null).trigger('change');

        $('#transactionReportTable tbody').empty();
        renderSummaryResults([]);
        $('#pagination').empty();
        $('#pagination-info').text('Showing 0 to 0');
        $('#rowsPerPage').val('15');
        $('#searchHint, #ExportButton, #clearButton').hide();
        setActionButtonsCompact(false);
        $('#transactionReportTable').closest('.card-body').hide();
    }

    function fetchTransactionData(page) {
        const start_date = $('#start_date').val() || '';
        const end_date = $('#end_date').val() || '';
        const partner = $('#partnerlistDropdown').val() || '';
        const post_transaction = $('#post_transaction_filter').val() || '';
        const status = $('#status_filter').val() || '';
        const source_file = $('#source_file_filter').val() || '';
        const mainzone = $('#mainzone_filter').val() || '';
        const zone = $('#zone_filter').val() || '';
        const region = $('#region_filter').val() || '';
        const branch = $('#branchDropdown').val() || '';
        const search = $('#search_input').val() || '';
        const rows_per_page = parseInt($('#rowsPerPage').val()) || 15;

        // Show loading state
        $('#loading-overlay').removeClass('d-none').addClass('d-flex');
        $('#searchButton').prop('disabled', true);

        $.ajax({
            url: window.location.pathname,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'get_transaction_data',
                start_date: start_date,
                end_date: end_date,
                partner: partner,
                post_transaction: post_transaction,
                status: status,
                source_file: source_file,
                mainzone: mainzone,
                zone: zone,
                region: region,
                branch: branch,
                search: search,
                page: page,
                rows_per_page: rows_per_page
            },
            success: function(response) {
                if (response.success) {
                    updateReportDateText(start_date, end_date);
                    renderSummaryResults(response.summary);
                    renderTable(response.data);
                    renderPagination(response.pagination);
                    $('.card-body').show();
                    $('#searchHint').show();
                } else {
                    Swal.fire('Error', response.error || 'Failed to fetch data', 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('Search error:', { xhr, status, error });
                Swal.fire('Error', 'Failed to fetch transaction data.', 'error');
            },
            complete: function() {
                $('#loading-overlay').removeClass('d-flex').addClass('d-none');
                $('#searchButton').prop('disabled', false);
            }
        });
    }

    function formatReportDate(rawDate) {
        if (!rawDate) return '';
        const d = new Date(rawDate + 'T00:00:00');
        if (isNaN(d.getTime())) return rawDate;
        const months = ['January', 'February', 'March', 'April', 'May', 'June',
                        'July', 'August', 'September', 'October', 'November', 'December'];
        return months[d.getMonth()] + ' ' + String(d.getDate()).padStart(2, '0') + ', ' + d.getFullYear();
    }

    function updateReportDateText(startDate, endDate) {
        const start = formatReportDate(startDate);
        const end = formatReportDate(endDate);
        let text = '';

        if (startDate && endDate) {
            text = (startDate === endDate) ? start : (start + ' to ' + end);
        } else if (startDate) {
            text = start;
        } else if (endDate) {
            text = end;
        }

        $('#reportDateValue').text(text);
    }

    function renderTable(data) {
        const tbody = $('#transactionReportTable tbody');
        tbody.empty();

        if (!data || data.length === 0) {
            tbody.html('<tr><td colspan="15" class="text-center text-muted py-4">No records found.</td></tr>');
            $('#ExportButton').hide();
            return;
        }
        $('#ExportButton').show();

        $.each(data, function(i, row) {
            const partnerName = row.sub_billers_name || row.partner_name || '-';
            const kp7Id = row.partner_id || '-';
            const kpxId = row.partner_id_kpx || '-';
            const source = row.source_file || '-';
            const statusText = (row.status === null || row.status === '') ? 'Active' : (row.status === 'cancelled' || row.status === '*') ? 'Cancelled' : row.status;
            const postStatus = row.post_transaction || '';
            const postLabel = postStatus === 'posted' ? 'Posted' : (postStatus === 'unposted' ? 'Unposted' : '-');

            const tr = $('<tr class="transaction-row"></tr>');
            tr.html(
                '<td class="text-center text-truncate" style="max-width:120px;">' + esc(formatDisplayDate(row.datetime) || '-') + '</td>' +
                '<td class="text-center text-truncate" style="max-width:120px;">' + esc(formatDisplayDate(row.cancellation_date) || '-') + '</td>' +
                '<td class="text-center text-truncate" style="max-width:150px;">' + esc(row.reference_no || '-') + '</td>' +
                '<td class="text-center text-truncate">' + esc(row.branch_id || '-') + '</td>' +
                '<td class="text-truncate" style="max-width:150px;">' + esc(row.outlet || '-') + '</td>' +
                '<td class="text-center text-truncate">' + esc(source) + '</td>' +
                '<td class="text-truncate" style="max-width:160px;">' + esc(partnerName) + '</td>' +
                '<td class="text-center text-truncate">' + esc(kp7Id) + '</td>' +
                '<td class="text-center text-truncate">' + esc(kpxId) + '</td>' +
                '<td class="text-end text-truncate">' + formatAmount(row.amount_paid || 0) + '</td>' +
                '<td class="text-end text-truncate">' + formatAmount(row.charge_to_partner || 0) + '</td>' +
                '<td class="text-end text-truncate">' + formatAmount(row.charge_to_customer || 0) + '</td>' +
                '<td class="text-center text-truncate" style="max-width:120px;">' + esc(row.billing_invoice || '-') + '</td>' +
                '<td class="text-center text-truncate">' + (postLabel === 'Posted' ? '<span class="badge bg-success text-white">Posted</span>' : (postLabel === 'Unposted' ? '<span class="badge bg-warning text-dark">Unposted</span>' : esc(postLabel))) + '</td>' +
                '<td class="text-center"><span class="badge ' + (statusText === 'Active' ? 'bg-success' : 'bg-danger') + '">' + esc(statusText) + '</span></td>'
            );

            // Double click to open modal
            tr.off('dblclick').on('dblclick', function() {
                openTransactionModal(row);
            });

            tbody.append(tr);
        });
    }

    function renderPagination(pagination) {
        const ul = $('#pagination');
        ul.empty();

        if (!pagination || pagination.total_pages <= 0) {
            $('#pagination-info').text('Showing 0 to 0');
            return;
        }

        const { current_page, total_pages, total_records, start_record, end_record } = pagination;
        const maxVisible = 5;

        $('#pagination-info').text(
            'Showing ' + start_record + ' to ' + end_record
        );

        // Previous button
        ul.append(
            '<li class="page-item ' + (current_page <= 1 ? 'disabled' : '') + '">' +
            '<a class="page-link" href="#" data-page="' + (current_page - 1) + '">&laquo;</a></li>'
        );

        // Page numbers
        let startPage = Math.max(1, current_page - Math.floor(maxVisible / 2));
        let endPage = Math.min(total_pages, startPage + maxVisible - 1);
        startPage = Math.max(1, endPage - maxVisible + 1);

        if (startPage > 1) {
            ul.append('<li class="page-item"><a class="page-link" href="#" data-page="1">1</a></li>');
            if (startPage > 2) ul.append('<li class="page-item disabled"><a class="page-link" href="#">...</a></li>');
        }

        for (let i = startPage; i <= endPage; i++) {
            ul.append(
                '<li class="page-item ' + (i === current_page ? 'active' : '') + '">' +
                '<a class="page-link" href="#" data-page="' + i + '">' + i + '</a></li>'
            );
        }

        if (endPage < total_pages) {
            if (endPage < total_pages - 1) ul.append('<li class="page-item disabled"><a class="page-link" href="#">...</a></li>');
            ul.append('<li class="page-item"><a class="page-link" href="#" data-page="' + total_pages + '">' + total_pages + '</a></li>');
        }

        // Next button
        ul.append(
            '<li class="page-item ' + (current_page >= total_pages ? 'disabled' : '') + '">' +
            '<a class="page-link" href="#" data-page="' + (current_page + 1) + '">&raquo;</a></li>'
        );

        // Page click handler
        ul.off('click', '.page-link').on('click', '.page-link', function(e) {
            e.preventDefault();
            const page = parseInt($(this).data('page'));
            if (page && page > 0) fetchTransactionData(page);
        });
    }

    function openTransactionModal(row) {
        const formatDate = function(dt) {
            if (!dt) return '<span class="text-muted">—</span>';
            const formatted = formatDisplayDate(dt);
            return formatted ? esc(formatted) : esc(dt);
        };

        const formatVal = function(v) {
            if (v === null || v === undefined || v === '') return '<span class="text-muted">—</span>';
            return esc(v);
        };

        const statusText = (row.status === null || row.status === '') ? 'Active' : (row.status === 'cancelled' || row.status === '*') ? 'Cancelled' : esc(row.status);
        const postStatus = row.post_transaction || '';
        const postLabel = postStatus === 'posted' ? '<span class="badge bg-success text-white">Posted</span>' : (postStatus === 'unposted' ? '<span class="badge bg-warning text-dark">Unposted</span>' : '<span class="text-muted">—</span>');

        $('#modal-cad-status').html(postLabel);
        $('#modal-source-file').html(formatVal(row.source_file || ''));
        $('#modal-datetime').html(formatDate(row.datetime));
        $('#modal-cancelled-date').html(formatDate(row.cancellation_date));
        $('#modal-reference-no').html(formatVal(row.reference_no));
        $('#modal-control-number').html(formatVal(row.control_no));
        $('#modal-billing-invoice').html(formatVal(row.billing_invoice));
        $('#modal-status').html('<span class="badge ' + (statusText === 'Active' ? 'bg-success' : 'bg-danger') + '">' + esc(statusText) + '</span>');

        // Branch info
        (function() {
            const zc = row.zone_code || '';
            let mz = '';
            if (zc === 'VIS' || zc === 'MIN') mz = 'VISMIN';
            else if (zc === 'NCR' || zc === 'LZN') mz = 'LNCR';
            $('#modal-mainzone').html(formatVal(mz));
        })();
        $('#modal-zone-code').html(formatVal(row.zone_code || ''));
        $('#modal-region-code').html(formatVal(row.region_code || ''));
        $('#modal-region-name').html(formatVal(row.region || ''));
        $('#modal-outlet').html(formatVal(row.outlet || ''));
        $('#modal-branch-id').html(formatVal(row.branch_id || ''));
        $('#modal-branch-code').html(formatVal(row.branch_code || ''));

        // Partner info
        $('#modal-partner-name').html(formatVal(row.partner_name || ''));
        $('#modal-partner-id').html(formatVal(row.partner_id || ''));
        $('#modal-partner-id-kpx').html(formatVal(row.partner_id_kpx || ''));
        $('#modal-gl-code').html(formatVal(row.mpm_gl_code || ''));
        $('#modal-gl-description').html(formatVal(row.gl_description || ''));

        // Payor info
        $('#modal-payor-name').html(formatVal(row.payor || ''));
        $('#modal-account-number').html(formatVal(row.account_no || ''));
        $('#modal-account-name').html(formatVal(row.account_name || ''));
        $('#modal-address').html(formatVal(row.address || ''));
        $('#modal-contact-number').html(formatVal(row.contact_no || ''));
        $('#modal-operator').html(formatVal(row.operator || ''));
        $('#modal-remote-branch').html(formatVal(row.remote_branch || ''));
        $('#modal-remote-operator').html(formatVal(row.remote_operator || ''));
        $('#modal-second-approver').html(formatVal(row['2nd_approver'] || ''));

        // Personnel / Upload info
        $('#modal-uploaded-by').html(formatVal(row.imported_by || ''));
        $('#modal-uploaded-date').html(formatDate(row.imported_date || ''));

        // Financial amounts
        $('#modal-amount-paid').text(formatCurrency(row.amount_paid || 0));
        $('#modal-charge-partner').text(formatCurrency(row.charge_to_partner || 0));
        $('#modal-charge-customer').text(formatCurrency(row.charge_to_customer || 0));

        // Open modal
        const modalEl = document.getElementById('transactionDetailsModal');
        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();
    }

    // Show export modal with SweetAlert2
    function showExportModal() {
        Swal.fire({
            title: 'Export Report',
            text: 'Select the format you would like to export the Transaction Report:',
            icon: 'question',
            showDenyButton: true,
            confirmButtonText: '<i class="fas fa-file-pdf"></i> PDF Format',
            denyButtonText: '<i class="fas fa-file-csv"></i> CSV Format',
            confirmButtonColor: '#dc3545',
            denyButtonColor: '#198754',
            customClass: {
                confirmButton: 'btn btn-danger me-2',
                denyButton: 'btn btn-success me-2',
            },
            buttonsStyling: false
        }).then(function(result) {
            if (result.isConfirmed) {
                exportToPDF();
            } else if (result.isDenied) {
                exportToCSV();
            }
        });
    }

    // Export to PDF (placeholder)
    function exportToPDF() {
        $('#loading-overlay').removeClass('d-none').addClass('d-flex');
        setTimeout(function() {
            $('#loading-overlay').removeClass('d-flex').addClass('d-none');
            Swal.fire({
                title: 'PDF Export',
                text: 'PDF export functionality is under development.',
                icon: 'info',
                confirmButtonText: 'OK'
            });
        }, 1000);
    }

    // Export to CSV
    function exportToCSV() {
        $('#loading-overlay').removeClass('d-none').addClass('d-flex');

        var formData = {
            partner: $('#partnerlistDropdown').val() || '',
            start_date: $('#start_date').val() || '',
            end_date: $('#end_date').val() || '',
            post_transaction: $('#post_transaction_filter').val() || '',
            status: $('#status_filter').val() || '',
            source_file: $('#source_file_filter').val() || '',
            mainzone: $('#mainzone_filter').val() || '',
            zone: $('#zone_filter').val() || '',
            region: $('#region_filter').val() || '',
            branch: $('#branchDropdown').val() || '',
            search: $('#search_input').val() || ''
        };

        var queryParams = new URLSearchParams();
        Object.keys(formData).forEach(function(key) {
            if (formData[key]) {
                queryParams.append(key, formData[key]);
            }
        });

        var exportUrl = '../../../models/generate/excel/generate-excel-transaction-report.php?' + queryParams.toString();

        var link = document.createElement('a');
        link.href = exportUrl;
        link.download = 'Transaction_Report_' + new Date().toISOString().slice(0, 19).replace(/:/g, '-') + '.csv';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);

        $('#loading-overlay').removeClass('d-flex').addClass('d-none');

        Swal.fire({
            title: 'Export Started',
            text: 'Your CSV file is being downloaded.',
            icon: 'success',
            confirmButtonText: 'OK',
            timer: 3000,
            timerProgressBar: true
        });
    }
});
</script>
</html>
