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
    }else{
        // Redirect to login page if user_type is not set
        header("Location: ../../../index.php");
        session_abort();
        session_destroy();
        exit();
    }
}else{
    // Redirect to login page if user_type is not set
    header("Location: ../../../index.php");
    session_abort();
    session_destroy();
    exit();
}

// if (!isset($_SESSION['user_type'])) {
//     // Redirect to login page if user is not logged in
//     header("Location: ../../../login.php");
//     exit();
// }

// dropdown queries for partner list
$partnersQuery = "SELECT partner_name FROM masterdata.partner_masterfile ORDER BY partner_name";
$partnersResult = $conn->query($partnersQuery);

// Fix the post_transaction dropdown query to get distinct values
$dataQuery = "SELECT DISTINCT post_transaction FROM mldb.billspayment_transaction WHERE post_transaction IS NOT NULL ORDER BY post_transaction";
$post_transaction_result = $conn->query($dataQuery);

// Fix the status dropdown query to get distinct values
$dataQuery = "SELECT DISTINCT status FROM mldb.billspayment_transaction ORDER BY status";
$status_result = $conn->query($dataQuery);

//Fix the all information comes from branch profile dropdown query to get distinct values
// mainzone
$dataQuery = "SELECT DISTINCT mainzone FROM masterdata.branch_profile WHERE NOT mainzone IN ('HO') GROUP BY mainzone ORDER BY mainzone";
$mainzone_result = $conn->query($dataQuery);

// zone - Modified to be populated via AJAX based on mainzone selection
$zone_result = null; // Will be populated via AJAX

// Add AJAX handler for fetching transaction data
if (isset($_POST['action']) && $_POST['action'] === 'get_transaction_data') {
    // Clear any previous output and set headers
    ob_clean();
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
    $rows_per_page = isset($_POST['rows_per_page']) ? (int)$_POST['rows_per_page'] : 10;
    
    // Initialize arrays and variables
    $whereConditions = [];
    $params = [];
    $types = '';
    
    // Build WHERE conditions
    if (!empty($search)) {
        $whereConditions[] = "(reference_no LIKE ?)";
        $searchParam = "%$search%";
        $params = array_merge($params, [$searchParam]);
        $types .= 's';
    }

    if (!empty($partner) && $partner !== 'All') {
        $whereConditions[] = "partner_name = ?";
        $params[] = $partner;
        $types .= 's';
    }

    if (!empty($start_date)) {
        $whereConditions[] = "(DATE(datetime) >= ? OR DATE(cancellation_date) >= ?)";
        $params[] = $start_date;
        $params[] = $start_date;
        $types .= 'ss';
    }

    if (!empty($end_date)) {
        $whereConditions[] = "(DATE(datetime) <= ? OR DATE(cancellation_date) <= ?)";
        $params[] = $end_date;
        $params[] = $end_date;
        $types .= 'ss';
    }

    if (!empty($post_transaction)) {
        $whereConditions[] = "post_transaction = ?";
        $params[] = $post_transaction;
        $types .= 's';
    }

    if (!empty($status)) {
        if ($status === 'active') {
            // Handle cases for Active status (NULL or empty values in database)
            $whereConditions[] = "status IS NULL";
        } else {
            // Handle other specific statuses
            $whereConditions[] = "status = ?";
            $params[] = $status;
            $types .= 's';
        }
    }

    if (!empty($source_file)) {
        $whereConditions[] = "source_file = ?";
        $params[] = $source_file;
        $types .= 's';
    }

    //for mainzone and zone filtering
    if($mainzone ==='VISMIN'){
        if (!empty($zone)) {
            $whereConditions[] = "zone_code = ?";
            $params[] = $zone;
            $types .= 's';
        }else{
            $whereConditions[] = "zone_code IN ('VIS', 'MIN')";
        }
    }elseif($mainzone ==='LNCR'){
        if (!empty($zone)) {
            $whereConditions[] = "zone_code = ?";
            $params[] = $zone;
            $types .= 's';
        }else{
            $whereConditions[] = "zone_code IN ('LZN', 'NCR')";
        }
    }

    if (!empty($region)) {
        $whereConditions[] = "region_code = ?";
        $params[] = $region;
        $types .= 's';
    }

    if (!empty($branch)) {
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
    
    // Count total records
    $totalRecords = 0;
    $countQuery = "SELECT COUNT(*) as total FROM mldb.billspayment_transaction $whereClause";
    
    if (!empty($params)) {
        $countStmt = $conn->prepare($countQuery);
        if ($countStmt) {
            if (!empty($types)) {
                $countStmt->bind_param($types, ...$params);
            }
            $countStmt->execute();
            $countResult = $countStmt->get_result();
            if ($countResult) {
                $totalRecords = $countResult->fetch_assoc()['total'];
            }
            $countStmt->close();
        }
    } else {
        $countResult = $conn->query($countQuery);
        if ($countResult) {
            $totalRecords = $countResult->fetch_assoc()['total'];
        }
    }
    
    // Calculate totals for ALL filtered records (not just current page)
    $totals = ['principal' => 0, 'partner' => 0, 'customer' => 0];
    $totalsQuery = "SELECT 
                        COALESCE(SUM(amount_paid), 0) as total_principal,
                        COALESCE(SUM(charge_to_partner), 0) as total_partner,
                        COALESCE(SUM(charge_to_customer), 0) as total_customer
                    FROM mldb.billspayment_transaction $whereClause";
    
    if (!empty($params)) {
        $totalsStmt = $conn->prepare($totalsQuery);
        if ($totalsStmt) {
            if (!empty($types)) {
                $totalsStmt->bind_param($types, ...$params);
            }
            $totalsStmt->execute();
            $totalsResult = $totalsStmt->get_result();
            if ($totalsResult) {
                $totalsRow = $totalsResult->fetch_assoc();
                $totals['principal'] = number_format((float)$totalsRow['total_principal'], 2);
                $totals['partner'] = number_format((float)$totalsRow['total_partner'], 2);
                $totals['customer'] = number_format((float)$totalsRow['total_customer'], 2);
            }
            $totalsStmt->close();
        }
    } else {
        $totalsResult = $conn->query($totalsQuery);
        if ($totalsResult) {
            $totalsRow = $totalsResult->fetch_assoc();
            $totals['principal'] = number_format((float)$totalsRow['total_principal'], 2);
            $totals['partner'] = number_format((float)$totalsRow['total_partner'], 2);
            $totals['customer'] = number_format((float)$totalsRow['total_customer'], 2);
        }
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
    if (!empty($whereConditions)) {
        $stmt = $conn->prepare($dataQuery);
        if ($stmt) {
            if (!empty($types)) {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $data[] = $row;
                }
            }
            $stmt->close();
        }
    } else {
        $result = $conn->query($dataQuery);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
        }
    }
    
    // Return JSON response with totals
    echo json_encode([
        'success' => true,
        'data' => $data,
        'totals' => $totals,
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

// Add AJAX handler for fetching zone data based on mainzone
if (isset($_POST['action']) && $_POST['action'] === 'get_zones') {
    header('Content-Type: application/json');
    
    $mainzone = isset($_POST['mainzone']) ? $_POST['mainzone'] : '';
    
    if (empty($mainzone)) {
        echo json_encode(['success' => false, 'error' => 'Mainzone is required']);
        exit;
    }
    
    $zoneQuery = "SELECT DISTINCT zone FROM masterdata.branch_profile WHERE mainzone = ? ORDER BY zone";
    $zoneStmt = $conn->prepare($zoneQuery);
    
    if ($zoneStmt) {
        $zoneStmt->bind_param('s', $mainzone);
        $zoneStmt->execute();
        $zoneResult = $zoneStmt->get_result();
        
        $zones = [];
        while ($row = $zoneResult->fetch_assoc()) {
            $zones[] = $row['zone'];
        }
        
        $zoneStmt->close();
        echo json_encode(['success' => true, 'zones' => $zones]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to fetch zones']);
    }
    exit;
}

// Add AJAX handler for fetching regions based on zone
if (isset($_POST['action']) && $_POST['action'] === 'get_regions') {
    header('Content-Type: application/json');
    
    $zone = isset($_POST['zone']) ? $_POST['zone'] : '';
    
    if (empty($zone)) {
        echo json_encode(['success' => false, 'error' => 'Zone is required']);
        exit;
    }
    
    $regionQuery = "SELECT DISTINCT region_code, region FROM masterdata.branch_profile WHERE zone = ? ORDER BY region";
    $regionStmt = $conn->prepare($regionQuery);
    
    if ($regionStmt) {
        $regionStmt->bind_param('s', $zone);
        $regionStmt->execute();
        $regionResult = $regionStmt->get_result();
        
        $regions = [];
        while ($row = $regionResult->fetch_assoc()) {
            $regions[] = ['region_code' => $row['region_code'], 'region' => $row['region']];
        }
        
        $regionStmt->close();
        echo json_encode(['success' => true, 'regions' => $regions]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to fetch regions']);
    }
    exit;
}

// Add AJAX handler for fetching branches based on region
if (isset($_POST['action']) && $_POST['action'] === 'get_branches') {
    header('Content-Type: application/json');
    
    $region_code = isset($_POST['region']) ? $_POST['region'] : '';
    
    if (empty($region_code)) {
        echo json_encode(['success' => false, 'error' => 'Region is required']);
        exit;
    }
    
    $branchQuery = "SELECT DISTINCT branch_id, branch_name FROM masterdata.branch_profile WHERE region_code = ? AND ml_matic_status IN ('Active', 'Pending', 'Inactive') AND branch_name IS NOT NULL ORDER BY branch_name";
    $branchStmt = $conn->prepare($branchQuery);
    
    if ($branchStmt) {
        $branchStmt->bind_param('s', $region_code);
        $branchStmt->execute();
        $branchResult = $branchStmt->get_result();
        
        $branches = [];
        while ($row = $branchResult->fetch_assoc()) {
            $branches[] = ['branch_id' => $row['branch_id'], 'branch_name' => $row['branch_name']];
        }
        
        $branchStmt->close();
        echo json_encode(['success' => true, 'branches' => $branches]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to fetch branches']);
    }
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
        .scrollable-table tfoot th {
            position: sticky;
            bottom: 0;
            background-color: var(--bs-dark);
            color: white;
            z-index: 10;
            border-top: 2px solid #dee2e6;
        }
        
        /* Style for Select2 validation */
        .select2-container.is-invalid .select2-selection {
            border-color: #dc3545 !important;
            box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25) !important;
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
        <div id="loading-overlay">
            <div class="loading-spinner"></div>
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
                                        <option value="All">All</option>
                                        <?php 
                                            if ($partnersResult && mysqli_num_rows($partnersResult) > 0) {
                                                while ($row = mysqli_fetch_assoc($partnersResult)) {
                                                    $partner_names = htmlspecialchars($row['partner_name']);
                                                    $selected = (isset($_GET['partner_name']) && $_GET['partner_name'] == $partner_names) ? 'selected' : '';
                                                    echo "<option value='$partner_names' $selected>" . ucfirst($partner_names) . "</option>";
                                                }
                                            }
                                        ?>
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
                                <div class="col-md-2 col-sm-6">
                                    <label for="post_transaction_filter" class="form-label small text-muted mb-1">CAD Status:</label>
                                    <select id="post_transaction_filter" name="post_transaction" class="form-select form-select-sm">
                                        <option value="">All Status</option>
                                        <?php 
                                            if ($post_transaction_result && mysqli_num_rows($post_transaction_result) > 0) {
                                                while ($row = mysqli_fetch_assoc($post_transaction_result)) {
                                                    $status = htmlspecialchars($row['post_transaction']);
                                                    $selected = (isset($_GET['post_transaction']) && $_GET['post_transaction'] == $status) ? 'selected' : '';
                                                    echo "<option value='$status' $selected>" . ucfirst($status) . "</option>";
                                                }
                                            }
                                        ?>
                                    </select>
                                </div>
                                
                                <!-- Transaction Status Dropdown -->
                                <div class="col-md-2 col-sm-6">
                                    <label for="status_filter" class="form-label small text-muted mb-1">Transaction Status:</label>
                                    <select id="status_filter" name="status" class="form-select form-select-sm">
                                        <option value="">All Status</option>
                                        <option value="active">Active</option>
                                        <option value="*">Cancelled</option>
                                    </select>
                                </div>

                                <!-- Source File Dropdown -->
                                <div class="col-md-2 col-sm-6">
                                    <label for="source_file_filter" class="form-label small text-muted mb-1">Source File:</label>
                                    <select id="source_file_filter" name="source_file" class="form-select form-select-sm">
                                        <option value="">Select Source File</option>
                                        <option value="KP7">KP7</option>
                                        <option value="KPX">KPX</option>
                                    </select>
                                </div>

                                <!-- Mainzone Dropdown -->
                                <div class="col-md-2 col-sm-6">
                                    <label for="mainzone_filter" class="form-label small text-muted mb-1">Mainzone:</label>
                                    <select id="mainzone_filter" name="mainzone" class="form-select form-select-sm">
                                        <option value="">Select Mainzone</option>
                                        <?php 
                                            if ($mainzone_result && mysqli_num_rows($mainzone_result) > 0) {
                                                while ($row = mysqli_fetch_assoc($mainzone_result)) {
                                                    $mainzone = htmlspecialchars($row['mainzone']);
                                                    $selected = (isset($_GET['mainzone']) && $_GET['mainzone'] == $mainzone) ? 'selected' : '';
                                                    echo "<option value='$mainzone' $selected>" . ucfirst($mainzone) . "</option>";
                                                }
                                            }
                                        ?>
                                    </select>
                                </div>
                                
                                <!-- Zone Dropdown -->
                                <div class="col-md-2 col-sm-6">
                                    <label for="zone_filter" class="form-label small text-muted mb-1">Zone:</label>
                                    <select id="zone_filter" name="zone" class="form-select form-select-sm">
                                        <option value="">Select Zone</option>
                                    </select>
                                </div>

                                <!-- Region Dropdown -->
                                <div class="col-md-2 col-sm-6">
                                    <label for="region_filter" class="form-label small text-muted mb-1">Region:</label>
                                    <select id="region_filter" name="region" class="form-select form-select-sm">
                                        <option value="">Select Region</option>
                                    </select>
                                </div>

                                <!-- Branch Name Dropdown -->
                                <div class="col-md-2 col-sm-6">
                                    <label for="branchDropdown" class="form-label small text-muted mb-1">Branch Name:</label>
                                    <select id="branchDropdown" class="form-select form-select-sm select2" aria-label="Select Branch Name" name="branch" data-placeholder="Search Branch Name..." required>
                                        <option value="">Select Branch Name</option>
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
                                
                                <!-- Action Button -->
                                <div class="col-md-1 col-sm-6">
                                    <button type="button" id="searchButton" class="btn btn-danger btn-sm w-100">
                                        <i class="fas fa-search"></i> Search
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="card-body" style="display: none;">
                            <div class="text-center mb-3">
                                <button type="button" id="ExportButton" class="btn btn-danger btn-sm">
                                    <i class="fas fa-download"></i> Export To
                                </button>
                            </div>
                            <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
                                <table id="transactionReportTable" class="table table-bordered table-hover table-striped">
                                    <thead class="table-light sticky-top">
                                        <tr>
                                            <th rowspan="2" class='text-truncate text-center align-middle'>CAD Status</th>
                                            <th rowspan="2" class='text-truncate text-center align-middle'>Billing Invoice</th>
                                            <th rowspan="2" class='text-truncate text-center align-middle'>Transaction Status</th>
                                            <th rowspan="2" class='text-truncate text-center align-middle'>Transaction Date</th>
                                            <th rowspan="2" class='text-truncate text-center align-middle'>Cancelled Date</th>
                                            <th rowspan="2" class='text-truncate text-center align-middle'>Reference Number</th>
                                            <th rowspan="2" class='text-truncate text-center align-middle'>Branch ID</th>
                                            <th rowspan="2" class='text-truncate text-center align-middle'>Branch Name</th>
                                            <th rowspan="2" class='text-truncate text-center align-middle'>Source</th>
                                            <th rowspan="2" class='text-truncate text-center align-middle'>Partner Name</th>
                                            <th colspan="2" class='text-truncate text-center align-middle'>Partner ID</th>
                                            <th rowspan="2" class='text-truncate text-center align-middle'>GL Code</th>
                                            <th rowspan="2" class='text-truncate text-center align-middle'>GL Description</th>
                                            <th rowspan="2" class='text-truncate text-center align-middle'>Principal Amount</th>
                                            <th colspan="2" class='text-truncate text-center align-middle'>Charge to</th>
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
                                        <tfoot class="sticky-bottom table-dark">
                                            <tr>
                                                <th colspan="14" style="text-align:right">Total : </th>
                                                <th id="totalPrincipalAmount" class="text-end">0.00</th>
                                                <th id="totalChargetoPartner" class="text-end">0.00</th>
                                                <th id="totalChargetoCustomer" class="text-end">0.00</th>
                                            </tr>
                                        </tfoot>
                                </table>
                            </div>
                            
                            <!-- Pagination Controls -->
                            <div class="d-flex justify-content-between align-items-center mt-3">
                                <div class="d-flex align-items-center">
                                    <span class="me-2">Show:</span>
                                    <select id="rowsPerPage" class="form-select form-select-sm" style="width: auto;">
                                        <option value="5">5</option>
                                        <option value="10" selected>10</option>
                                        <option value="25">25</option>
                                        <option value="50">50</option>
                                        <option value="100">100</option>
                                    </select>
                                    <span class="ms-2">entries</span>
                                </div>
                                
                                <div id="pagination-info" class="text-muted">
                                    Showing 0 to 0 of 0 entries
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
<script>
$(document).ready(function() {
    // Initialize Select2 for partner dropdown
    $('#partnerlistDropdown').select2({
        placeholder: 'Search or select a Partner...',
        allowClear: true
    });
    $('#branchDropdown').select2({
        placeholder: 'Search or select a Branch Name...',
        allowClear: true
    });
    
    // Initialize variables
    let currentPage = 1;
    let rowsPerPage = 10;
    
    // Event handlers
    $('#searchButton').click(function() {
        // Validate required fields before searching
        if (validateRequiredFields()) {
            currentPage = 1;
            loadTransactionData();
        }
    });
    
    // Add Enter key handler for search input
    $('#search_input').keypress(function(e) {
        if (e.which === 13) { // Enter key
            $('#searchButton').click();
        }
    });

    // Hide hint and results when search input is cleared
    $('#search_input').on('input', function() {
        if ($(this).val().trim() === '') {
            $('.card-body').hide();
            $('#transactionReportTable tbody').empty();
            updateTotalsFromServer({ principal: '0.00', partner: '0.00', customer: '0.00' });
            $('#searchHint').hide();
        }
    });
    
    // Add Export button click handler
    $('#ExportButton').click(function() {
        showExportModal();
    });
    
    $('#rowsPerPage').change(function() {
        rowsPerPage = parseInt($(this).val());
        currentPage = 1;
        loadTransactionData();
    });
    
    // Pagination click handler
    $(document).on('click', '.page-link', function(e) {
        e.preventDefault();
        const page = parseInt($(this).data('page'));
        if (page && page !== currentPage && !isNaN(page)) {
            currentPage = page;
            loadTransactionData();
        }
    });
    
    // Add double-click event handler for table rows
    $(document).on('dblclick', '.transaction-row', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        const rowIndex = $(this).data('row-index');
        
        if (window.currentTableData && window.currentTableData[rowIndex]) {
            const rowData = window.currentTableData[rowIndex];
            showTransactionModal(rowData);
        }
    });
    
    // Function to validate required fields
    function validateRequiredFields() {
        let isValid = true;
        
        // Validate partner selection
        const partner = $('#partnerlistDropdown').val();
        if (!partner || partner === '') {
            // Add visual feedback to Select2 dropdown
            $('#partnerlistDropdown').next('.select2-container').addClass('is-invalid');
            showAlert('Validation Error', 'Please select a partner.', 'error');
            isValid = false;
        } else {
            $('#partnerlistDropdown').next('.select2-container').removeClass('is-invalid');
        }
        
        // Validate start date
        const startDate = $('#start_date').val();
        if (!startDate) {
            $('#start_date').addClass('is-invalid');
            if (isValid) { // Only show one error at a time
                showAlert('Validation Error', 'Please select a start date.', 'error');
            }
            isValid = false;
        } else {
            $('#start_date').removeClass('is-invalid');
        }
        
        // Validate end date
        const endDate = $('#end_date').val();
        if (!endDate) {
            $('#end_date').addClass('is-invalid');
            if (isValid) { // Only show one error at a time
                showAlert('Validation Error', 'Please select an end date.', 'error');
            }
            isValid = false;
        } else {
            $('#end_date').removeClass('is-invalid');
        }
        
        // Validate date range (end date should not be before start date)
        if (startDate && endDate && new Date(startDate) > new Date(endDate)) {
            $('#end_date').addClass('is-invalid');
            if (isValid) {
                showAlert('Validation Error', 'End date cannot be before start date.', 'error');
            }
            isValid = false;
        }
        
        return isValid;
    }
    
    // Clear validation styling when user changes the input
    $('#start_date, #end_date').change(function() {
        $(this).removeClass('is-invalid');
    });
    
    // Clear validation styling when partner selection changes
    $('#partnerlistDropdown').change(function() {
        $('#partnerlistDropdown').next('.select2-container').removeClass('is-invalid');
    });

    // Function to show export modal
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
        }).then((result) => {
            if (result.isConfirmed) {
                // PDF Format selected
                exportToPDF();
            } else if (result.isDenied) {
                // CSV Format selected
                exportToCSV();
            }
            // If cancelled, do nothing
        });
    }
    
    // Function to export to PDF
    function exportToPDF() {
        showLoading();
        // Add your PDF export logic here
        setTimeout(() => {
            hideLoading();
            Swal.fire({
                title: 'PDF Export',
                text: 'PDF export functionality is under development.',
                // text: 'PDF export functionality will be implemented here.',
                icon: 'info',
                confirmButtonText: 'OK'
            });
        }, 1000);
    }
    
    // Function to export to CSV (updated)
    function exportToCSV() {
        showLoading();
        
        // Get current filter values
        const formData = {
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
        
        // Build query string
        const queryParams = new URLSearchParams();
        Object.keys(formData).forEach(key => {
            if (formData[key]) {
                queryParams.append(key, formData[key]);
            }
        });
        
        // Create download URL
        const exportUrl = '../../../models/generate/excel/generate-excel-transaction-report.php?' + queryParams.toString();
        
        // Create a temporary link and trigger download
        const link = document.createElement('a');
        link.href = exportUrl;
        link.download = 'Transaction_Report_' + new Date().toISOString().slice(0, 19).replace(/:/g, '-') + '.csv';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        
        hideLoading();
        
        Swal.fire({
            title: 'Export Started',
            text: 'Your CSV file is being downloaded.',
            icon: 'success',
            confirmButtonText: 'OK',
            timer: 3000,
            timerProgressBar: true
        });
    }
    
    // Main function to load transaction data
    function loadTransactionData() {
        showLoading();
        
        const formData = {
            action: 'get_transaction_data',
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
            search: $('#search_input').val() || '',
            page: currentPage,
            rows_per_page: rowsPerPage
        };
        
        $.ajax({
            url: window.location.href,
            type: 'POST',
            data: formData,
            dataType: 'json',
            timeout: 30000,
            success: function(response) {
                hideLoading();

                if (response && response.success) {
                    var rows = response.data || [];
                    populateTable(rows);
                    updatePagination(response.pagination || {});
                    updateTotalsFromServer(response.totals || {});
                    $('.card-body').show();
                    if (Array.isArray(rows) && rows.length > 0) {
                        $('#searchHint').show();
                    } else {
                        $('#searchHint').hide();
                    }
                } else {
                    showAlert('Error', response.error || 'Failed to load transaction data', 'error');
                    $('.card-body').hide();
                    $('#searchHint').hide();
                }
            },
            error: function(xhr, status, error) {
                hideLoading();
                
                let errorMessage = 'Failed to load transaction data';
                if (xhr.responseText) {
                    if (xhr.responseText.includes('Fatal error') || xhr.responseText.includes('Parse error')) {
                        errorMessage = 'Server error occurred. Please check the logs.';
                    } else if (xhr.responseText.includes('Connection failed')) {
                        errorMessage = 'Database connection failed.';
                    }
                }
                
                showAlert('Error', errorMessage, 'error');
                $('.card-body').hide();
            }
        });
    }
    
    // Function to populate table with data
    function populateTable(data) {
        const tbody = $('#transactionReportTable tbody');
        tbody.empty();
        
        if (!Array.isArray(data) || data.length === 0) {
            tbody.append('<tr><td colspan="17" class="text-center">No data found</td></tr>');
            return;
        }
        
        data.forEach(function(row, index) {
            const cadStatusBadge = getStatusBadge(row.post_transaction);
            const transactionStatusBadge = getTransactionStatusBadge(row.status);
            
            const tr = `
                <tr class="transaction-row" data-row-index="${index}" style="cursor: pointer;">
                    <td class="text-center">${cadStatusBadge}</td>
                    <td>${escapeHtml(row.billing_invoice || '-')}</td>
                    <td class="text-center">${transactionStatusBadge}</td>
                    <td>${formatDate(row.datetime) || '-'}</td>
                    <td>${formatDate(row.cancellation_date) || '-'}</td>
                    <td>${escapeHtml(row.reference_no || '-')}</td>
                    <td>${escapeHtml(row.branch_id || '-')}</td>
                    <td class="text-truncate">${escapeHtml(row.outlet || '-')}</td>
                    <td>${escapeHtml(row.source_file || '-')}</td>
                    <td class="text-truncate">${escapeHtml(row.partner_name || '-')}</td>
                    <td>${escapeHtml(row.partner_id || '-')}</td>
                    <td>${escapeHtml(row.partner_id_kpx || '-')}</td>
                    <td>${escapeHtml(row.mpm_gl_code || '-')}</td>
                    <td>${escapeHtml(row.mpm_gl_description || '-')}</td>
                    <td class="text-end">${formatCurrency(row.amount_paid)}</td>
                    <td class="text-end">${formatCurrency(row.charge_to_partner)}</td>
                    <td class="text-end">${formatCurrency(row.charge_to_customer)}</td>
                </tr>
            `;
            tbody.append(tr);
        });
        
        // Store the data globally for modal access
        window.currentTableData = data;
    }

    // Function to get CAD status badge HTML (existing function)
    function getStatusBadge(status) {
        if (!status) return '<span class="badge bg-secondary text-white">-</span>';
        
        const statusLower = status.toLowerCase();
        const statusCapitalized = capitalizeFirstLetter(status);
        
        if (statusLower === 'unposted') {
            return `<span class="badge bg-warning text-dark">${escapeHtml(statusCapitalized)}</span>`;
        } else if (statusLower === 'posted') {
            return `<span class="badge bg-success text-white">${escapeHtml(statusCapitalized)}</span>`;
        } else {
            // Default for other statuses
            return `<span class="badge bg-secondary text-white">${escapeHtml(statusCapitalized)}</span>`;
        }
    }

    // NEW Function to get Transaction Status badge HTML
    function getTransactionStatusBadge(status) {
        if (!status || status === '' || status === null) {
            // Active status - green/success color
            return '<span class="badge bg-success text-white">Active</span>';
        } else if (status === '*') {
            // Cancelled status - red/danger color
            return '<span class="badge bg-danger text-white">Cancelled</span>';
        } else {
            // Any other status - show as is with secondary color
            const statusCapitalized = capitalizeFirstLetter(status);
            return `<span class="badge bg-secondary text-white">${escapeHtml(statusCapitalized)}</span>`;
        }
    }

    // Function to show transaction details modal (updated to use new status function)
    function showTransactionModal(data) {
        // Populate modal with data - Basic Information
        $('#modal-reference-no').text(data.reference_no || '-');
        $('#modal-status').html(getTransactionStatusBadge(data.status || ''));
        $('#modal-cad-status').html(getStatusBadge(data.post_transaction));
        $('#modal-source-file').text(data.source_file || '-');
        $('#modal-datetime').text(formatDate(data.datetime) || '-');
        $('#modal-cancelled-date').text(formatDate(data.cancellation_date) || '-');
        $('#modal-billing-invoice').text(data.billing_invoice || '-');
        if (data.zone_code === 'VIS' || data.zone_code === 'MIN') {
            $('#modal-mainzone').text('VISMIN' || '-');
        } else if (data.zone_code === 'LZN' || data.zone_code === 'NCR') {
            $('#modal-mainzone').text('LNCR' || '-');
        }
        $('#modal-zone-code').text(data.zone_code || '-');
        $('#modal-zone-code').text(data.zone_code || '-');
        $('#modal-region-code').text(data.region_code || '-');
        $('#modal-region-name').text(data.region || '-');
        $('#modal-branch-code').text(data.branch_code || '-');
        $('#modal-branch-id').text(data.branch_id || '-');
        $('#modal-outlet').text(data.outlet || '-');
        $('#modal-source-file').text(data.source_file || '-');
        $('#modal-partner-name').text(data.partner_name || '-');
        $('#modal-partner-id').text(data.partner_id || '-');
        $('#modal-partner-id-kpx').text(data.partner_id_kpx || '-');
        $('#modal-gl-code').text(data.mpm_gl_code || '-');
        $('#modal-gl-description').text(data.mpm_gl_description || '-');
        $('#modal-control-number').text(data.control_no || '-');
        $('#modal-payor-name').text(data.payor || '-');
        $('#modal-account-number').text(data.account_no || '-');
        $('#modal-account-name').text(data.account_name || '-');
        $('#modal-address').text(data.address || '-');
        $('#modal-contact-number').text(data.contact_no || '-');
        $('#modal-operator').text(data.operator || '-');
        $('#modal-remote-branch').text(data.remote_branch || '-');
        $('#modal-remote-operator').text(data.remote_operator || '-');
        $('#modal-second-approver').text(data['2nd_approver'] || '-');
        $('#modal-uploaded-by').text(data.imported_by || '-');
        $('#modal-uploaded-date').text(formatDate(data.imported_date) || '-');
        $('#modal-amount-paid').text(formatCurrency(data.amount_paid));
        $('#modal-charge-partner').text(formatCurrency(data.charge_to_partner));
        $('#modal-charge-customer').text(formatCurrency(data.charge_to_customer));
        
        // Show the modal using Bootstrap 5 syntax
        const modalElement = document.getElementById('transactionDetailsModal');
        const modal = new bootstrap.Modal(modalElement);
        modal.show();
    }

    // Function to get status badge HTML (updated to capitalize first letter)
    function getStatusBadge(status) {
        if (!status) return '<span class="badge bg-secondary text-white">-</span>';
        
        const statusLower = status.toLowerCase();
        const statusCapitalized = capitalizeFirstLetter(status);
        
        if (statusLower === 'unposted') {
            return `<span class="badge bg-warning text-dark">${escapeHtml(statusCapitalized)}</span>`;
        } else if (statusLower === 'posted') {
            return `<span class="badge bg-success text-white">${escapeHtml(statusCapitalized)}</span>`;
        } else {
            // Default for other statuses
            return `<span class="badge bg-secondary text-white">${escapeHtml(statusCapitalized)}</span>`;
        }
    }

    // Add helper function to capitalize first letter
    function capitalizeFirstLetter(string) {
        if (!string) return '';
        return string.charAt(0).toUpperCase() + string.slice(1).toLowerCase();
    }

    // Function to update totals from server response (all filtered records)
    function updateTotalsFromServer(totals) {
        // Update the footer totals with server-calculated totals
        $('#totalPrincipalAmount').text(totals.principal || '0.00');
        $('#totalChargetoPartner').text(totals.partner || '0.00');
        $('#totalChargetoCustomer').text(totals.customer || '0.00');

        // Check if all totals are 0 and hide/show Export button accordingly
        const principalAmount = parseFloat(totals.principal) || 0;
        const partnerAmount = parseFloat(totals.partner) || 0;
        const customerAmount = parseFloat(totals.customer) || 0;
        
        // Hide Export button if all totals are 0, otherwise show it
        if (principalAmount === 0 && partnerAmount === 0 && customerAmount === 0) {
            $('#ExportButton').hide();
        } else {
            $('#ExportButton').show();
        }
    }
    
    // Function to update pagination
    function updatePagination(pagination) {
        const paginationContainer = $('#pagination');
        paginationContainer.empty();
        
        if (!pagination || !pagination.total_records) {
            $('#pagination-info').text('Showing 0 to 0 of 0 entries');
            return;
        }
        
        // Update info text
        $('#pagination-info').text(
            `Showing ${pagination.start_record || 0} to ${pagination.end_record || 0} of ${pagination.total_records || 0} entries`
        );
        
        if (pagination.total_pages <= 1) return;
        
        // Previous button
        const prevDisabled = pagination.current_page === 1 ? 'disabled' : '';
        paginationContainer.append(`
            <li class="page-item ${prevDisabled}">
                <a class="page-link" href="#" data-page="${pagination.current_page - 1}">Previous</a>
            </li>
        `);
        
        // Page numbers
        const startPage = Math.max(1, pagination.current_page - 2);
        const endPage = Math.min(pagination.total_pages, pagination.current_page + 2);
        
        for (let i = startPage; i <= endPage; i++) {
            const active = i === pagination.current_page ? 'active' : '';
            paginationContainer.append(`
                <li class="page-item ${active}">
                    <a class="page-link" href="#" data-page="${i}">${i}</a>
                </li>
            `);
        }
        
        // Next button
        const nextDisabled = pagination.current_page === pagination.total_pages ? 'disabled' : '';
        paginationContainer.append(`
            <li class="page-item ${nextDisabled}">
                <a class="page-link" href="#" data-page="${pagination.current_page + 1}">Next</a>
            </li>
        `);
    }
    
    // Utility functions
    function formatDate(dateString) {
        if (!dateString) return '';
        const date = new Date(dateString);
        if (isNaN(date.getTime())) return '';
        
        // Format as "F d, Y" (e.g., "January 20, 2026")
        const options = { 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric' 
        };
        return date.toLocaleDateString('en-US', options);
    }

    function formatCurrency(amount) {
        if (!amount || isNaN(amount)) return '0.00';
        return parseFloat(amount).toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    function showLoading() {
        $('#loading-overlay').show();
    }
    
    function hideLoading() {
        $('#loading-overlay').hide();
    }
    
    function showAlert(title, message, type) {
        Swal.fire({
            title: title,
            text: message,
            icon: type,
            confirmButtonText: 'OK'
        });
    }

    // Add cascading dropdown functionality
    $('#mainzone_filter').change(function() {
        const selectedMainzone = $(this).val();
        const zoneDropdown = $('#zone_filter');
        const regionDropdown = $('#region_filter');
        const branchDropdown = $('#branchDropdown');
        
        // Clear dependent dropdowns
        zoneDropdown.html('<option value="">Select Zone</option>');
        regionDropdown.html('<option value="">Select Region</option>');
        branchDropdown.html('<option value="">Select Branch Name</option>');
        
        if (selectedMainzone) {
            // Fetch zones based on selected mainzone
            $.ajax({
                url: window.location.href,
                type: 'POST',
                data: {
                    action: 'get_zones',
                    mainzone: selectedMainzone
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success && response.zones) {
                        response.zones.forEach(function(zone) {
                            zoneDropdown.append(`<option value="${zone}">${zone}</option>`);
                        });
                    }
                },
                error: function() {
                    console.error('Failed to fetch zones');
                }
            });
        }
    });

    $('#zone_filter').change(function() {
        const selectedZone = $(this).val();
        const regionDropdown = $('#region_filter');
        const branchDropdown = $('#branchDropdown');
        
        // Clear dependent dropdowns
        regionDropdown.html('<option value="">Select Region</option>');
        branchDropdown.html('<option value="">Select Branch Name</option>');
        
        if (selectedZone) {
            // Fetch regions based on selected zone
            $.ajax({
                url: window.location.href,
                type: 'POST',
                data: {
                    action: 'get_regions',
                    zone: selectedZone
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success && response.regions) {
                        response.regions.forEach(function(region) {
                            regionDropdown.append(`<option value="${region.region_code}">${region.region}</option>`);
                        });
                    }
                },
                error: function() {
                    console.error('Failed to fetch regions');
                }
            });
        }
    });

    $('#region_filter').change(function() {
        const selectedRegion = $(this).val();
        const branchDropdown = $('#branchDropdown');
        
        // Clear branch dropdown
        branchDropdown.html('<option value="">Select Branch Name</option>');
        
        if (selectedRegion) {
            // Fetch branches based on selected region
            $.ajax({
                url: window.location.href,
                type: 'POST',
                data: {
                    action: 'get_branches',
                    region: selectedRegion
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success && response.branches) {
                        response.branches.forEach(function(branch) {
                            branchDropdown.append(`<option value="${branch.branch_id}">${branch.branch_name}</option>`);
                        });
                    }
                },
                error: function() {
                    console.error('Failed to fetch branches');
                }
            });
        }
    });
});
</script>
</html>