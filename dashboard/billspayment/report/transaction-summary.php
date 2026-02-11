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

// dropdown queries for partner list
$partnersQuery = "SELECT partner_name FROM masterdata.partner_masterfile ORDER BY partner_name";
$partnersResult = $conn->query($partnersQuery);

// Add AJAX handler for fetching transaction data
if (isset($_POST['action']) && $_POST['action'] === 'get_transaction_data') {
    // Clear any previous output and set headers
    ob_clean();
    header('Content-Type: application/json');
    
    try {
        $partner = isset($_POST['partner']) ? $_POST['partner'] : '';
        $start_date = isset($_POST['start_date']) ? $_POST['start_date'] : '';
        $end_date = isset($_POST['end_date']) ? $_POST['end_date'] : '';
        $source_file = isset($_POST['source_file']) ? $_POST['source_file'] : '';
        $page = isset($_POST['page']) ? (int)$_POST['page'] : 1;
        $rows_per_page = isset($_POST['rows_per_page']) ? (int)$_POST['rows_per_page'] : 10;
        
        // Initialize arrays and variables
        $whereConditions = [];
        $params = [];
        
        // Partner filter
        if (!empty($partner) && $partner !== 'All') {
            $whereConditions[] = "mpm.partner_name = ?";
            $params[] = $partner;
        }

        // Date condition - fix the logic
        $dateCondition = '';
        if (!empty($start_date) && !empty($end_date)) {
            $dateCondition = "(DATE(bt.datetime) BETWEEN '$start_date' AND '$end_date' OR DATE(bt.cancellation_date) BETWEEN '$start_date' AND '$end_date')";
        } elseif (!empty($start_date)) {
            $dateCondition = "(DATE(bt.datetime) >= '$start_date' OR DATE(bt.cancellation_date) >= '$start_date')";
        } elseif (!empty($end_date)) {
            $dateCondition = "(DATE(bt.datetime) <= '$end_date' OR DATE(bt.cancellation_date) <= '$end_date')";
        } else {
            // If no dates provided, use a default condition to prevent empty results
            $dateCondition = "1=1";
        }

        // Source file condition
        $sourceFileCondition = '';
        if (!empty($source_file) && $source_file !== 'All') {
            $sourceFileCondition = "AND bt.source_file = '$source_file'";
        } else {
            // If 'All' is selected or no source file specified, include both KP7 and KPX
            $sourceFileCondition = "AND bt.source_file IN ('KP7', 'KPX')";
        }

        // Build WHERE clause for main query
        $mainWhereClause = '';
        if (!empty($whereConditions)) {
            $mainWhereClause = 'AND ' . implode(' AND ', $whereConditions);
        }
        
        // Check database connection
        if (!$conn) {
            echo json_encode(['success' => false, 'error' => 'Database connection failed']);
            exit;
        }
        
        // Main query to get transaction data
        $query = "
            WITH summary_vol AS (
                SELECT
                    bt.partner_id,
                    bt.partner_id_kpx,
                    COUNT(*) AS vol1,
                    sum(bt.amount_paid) AS principal1,
                    sum(bt.charge_to_partner) AS charge_partner1,
                    sum(bt.charge_to_customer) AS charge_customer1
                FROM
                    mldb.billspayment_transaction AS bt
                WHERE
                    $dateCondition
                    AND bt.status IS NULL
                    $sourceFileCondition
                GROUP BY
                    bt.partner_id,
                    bt.partner_id_kpx
            ),
            adjustment_vol AS (
                SELECT
                    bt.partner_id,
                    bt.partner_id_kpx,
                    COUNT(*) AS vol2,
                    sum(bt.amount_paid) AS principal2,
                    sum(bt.charge_to_partner) AS charge_partner2,
                    sum(bt.charge_to_customer) AS charge_customer2
                FROM
                    mldb.billspayment_transaction AS bt
                WHERE
                    $dateCondition
                    AND bt.status = '*'
                    $sourceFileCondition
                GROUP BY
                    bt.partner_id,
                    bt.partner_id_kpx
            )
            SELECT 
                mpm.partner_name,
                mpm.partner_id,
                mpm.partner_id_kpx,
                mpm.gl_code,
                
                COALESCE(sv.vol1, 0) AS summary_vol,
                COALESCE(sv.principal1, 0) AS summary_principal,
                COALESCE(sv.charge_partner1, 0) AS summary_charges_partner,
                COALESCE(sv.charge_customer1, 0) AS summary_charges_customer,
                (COALESCE(sv.charge_partner1, 0) + COALESCE(sv.charge_customer1, 0)) AS summary_total_charge,
                
                COALESCE(av.vol2, 0) AS adjustment_vol,
                COALESCE(ABS(av.principal2), 0) AS adjustment_principal,
                COALESCE(ABS(av.charge_partner2), 0) AS adjustment_charges_partner,
                COALESCE(ABS(av.charge_customer2), 0) AS adjustment_charges_customer,
                (COALESCE(ABS(av.charge_partner2), 0) + COALESCE(ABS(av.charge_customer2), 0)) AS adjustment_total_charge,
                
                (COALESCE(sv.vol1, 0) - COALESCE(av.vol2, 0)) AS net_vol,
                (COALESCE(sv.principal1, 0) - COALESCE(ABS(av.principal2), 0)) AS net_principal,
                (COALESCE(sv.charge_partner1, 0) - COALESCE(ABS(av.charge_partner2), 0)) AS net_charges_partner,
                (COALESCE(sv.charge_customer1, 0) - COALESCE(ABS(av.charge_customer2), 0)) AS net_charges_customer,
                ((COALESCE(sv.charge_partner1, 0) - COALESCE(ABS(av.charge_partner2), 0)) + (COALESCE(sv.charge_customer1, 0) - COALESCE(ABS(av.charge_customer2), 0))) AS net_total_charge
            FROM 
                masterdata.partner_masterfile AS mpm
            LEFT JOIN
                summary_vol AS sv
                ON (
                    mpm.partner_id = sv.partner_id
                    OR mpm.partner_id_kpx = sv.partner_id_kpx
                )
            LEFT JOIN
                adjustment_vol AS av
                ON (
                    mpm.partner_id = av.partner_id
                    OR mpm.partner_id_kpx = av.partner_id_kpx
                )
            WHERE
                mpm.status = 'ACTIVE'
                $mainWhereClause
            ORDER BY
                mpm.partner_name
        ";
        
        // Execute the query
        if (!empty($params)) {
            $stmt = $conn->prepare($query);
            if ($stmt) {
                $types = str_repeat('s', count($params));
                $stmt->bind_param($types, ...$params);
                $stmt->execute();
                $result = $stmt->get_result();
            } else {
                throw new Exception('Failed to prepare statement');
            }
        } else {
            $result = $conn->query($query);
        }
        
        if (!$result) {
            throw new Exception('Query failed: ' . $conn->error);
        }
        
        $data = [];
        $totals = [
            'total_summary_vol' => 0,
            'total_summary_principal' => 0,
            'total_summary_charges_partner' => 0,
            'total_summary_charges_customer' => 0,
            'total_summary_total_charge' => 0,
            'total_adjustment_vol' => 0,
            'total_adjustment_principal' => 0,
            'total_adjustment_charges_partner' => 0,
            'total_adjustment_charges_customer' => 0,
            'total_adjustment_total_charge' => 0,
            'total_net_vol' => 0,
            'total_net_principal' => 0,
            'total_net_charges_partner' => 0,
            'total_net_charges_customer' => 0,
            'total_net_total_charge' => 0
        ];
        
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
            
            // Calculate totals - make sure these match your column names
            $totals['total_summary_vol'] += (int)($row['summary_vol'] ?? 0);
            $totals['total_summary_principal'] += (float)($row['summary_principal'] ?? 0);
            $totals['total_summary_charges_partner'] += (float)($row['summary_charges_partner'] ?? 0);
            $totals['total_summary_charges_customer'] += (float)($row['summary_charges_customer'] ?? 0);
            $totals['total_summary_total_charge'] += (float)($row['summary_total_charge'] ?? 0);

            $totals['total_adjustment_vol'] += (int)($row['adjustment_vol'] ?? 0);
            $totals['total_adjustment_principal'] += (float)($row['adjustment_principal'] ?? 0);
            $totals['total_adjustment_charges_partner'] += (float)($row['adjustment_charges_partner'] ?? 0);
            $totals['total_adjustment_charges_customer'] += (float)($row['adjustment_charges_customer'] ?? 0);
            $totals['total_adjustment_total_charge'] += (float)($row['adjustment_total_charge'] ?? 0);

            $totals['total_net_vol'] += (int)($row['net_vol'] ?? 0);
            $totals['total_net_principal'] += (float)($row['net_principal'] ?? 0);
            $totals['total_net_charges_partner'] += (float)($row['net_charges_partner'] ?? 0);
            $totals['total_net_charges_customer'] += (float)($row['net_charges_customer'] ?? 0);
            $totals['total_net_total_charge'] += (float)($row['net_total_charge'] ?? 0);
        }
        
        // Debug: Add this to check if totals are being calculated
        error_log('Row count: ' . count($data));
        error_log('Sample row: ' . json_encode($data[0] ?? 'No data'));
        error_log('Totals calculated: ' . json_encode($totals));

        // Calculate pagination
        $total_records = count($data);
        $total_pages = ceil($total_records / $rows_per_page);
        $offset = ($page - 1) * $rows_per_page;
        $paged_data = array_slice($data, $offset, $rows_per_page);
        
        $pagination = [
            'current_page' => $page,
            'total_pages' => $total_pages,
            'total_records' => $total_records,
            'start_record' => $offset + 1,
            'end_record' => min($offset + $rows_per_page, $total_records)
        ];
        
        echo json_encode([
            'success' => true,
            'data' => $paged_data,
            'pagination' => $pagination,
            'totals' => $totals
        ]);
        
    } catch (Exception $e) {
        error_log('Transaction Summary Error: ' . $e->getMessage());
        echo json_encode([
            'success' => false,
            'error' => 'An error occurred while fetching data: ' . $e->getMessage()
        ]);
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
    <title>Transaction Summary Report | <?php if($_SESSION['user_type'] === 'admin' || $_SESSION['user_type'] === 'user') echo ucfirst($_SESSION['user_type']); else echo "Guest";?></title>
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
                <i class="fa-solid fa-chart-simple" aria-hidden="true"></i>
                <div>
                    <h2>Transaction Summary Report</h2>
                    <p class="bp-section-sub">Summarized Transaction Output</p>
                </div>
            </div>
        </div>
        <div class="bp-card container-fluid mt-3 p-4">
            <div class="row">
                <div class="col-md-18">
                    <div class="card">
                        <div class="card-header">
                            <div class="mb-3">
                                <label id="searchHint" class="h5 text-muted" style="display: none;">Hint: <i>Double click the row to view the details</i></label>
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

                                <!-- Source File Dropdown -->
                                <div class="col-md-2 col-sm-6">
                                    <label for="source_file_filter" class="form-label small text-muted mb-1">Source File:</label>
                                    <select id="source_file_filter" name="source_file" class="form-select form-select-sm">
                                        <option value="All">All</option>
                                        <option value="KP7">KP7</option>
                                        <option value="KPX">KPX</option>
                                    </select>
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
                            <!-- <div class="text-center mb-3">
                                <button type="button" id="ExportButton" class="btn btn-danger btn-sm">
                                    <i class="fas fa-download"></i> Export To
                                </button>
                            </div> -->
                            <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
                                <table id="transactionReportTable" class="table table-bordered table-hover table-striped">
                                    <thead class="table-light sticky-top">
                                        <tr>
                                            <th colspan="4" class='text-center'>Import Details</th>
                                            <th colspan="15" class='text-center'>Transaction Summary</th>
                                        </tr>
                                        <tr>
                                            <!-- <th rowspan="2">Uploaded Date</th> -->
                                            <!-- <th rowspan="2">Source</th> -->
                                            <th rowspan="2" class='text-truncate text-center align-middle'>Partner Name</th>
                                            <th colspan="2" class='text-truncate text-center'>Partner ID</th>
                                            <th rowspan="2" class='text-truncate text-center align-middle'>GL Code</th>
                                            <!-- <th rowspan="2">Number of Transactions</th> -->
                                            <!-- <th rowspan="2">Uploaded By</th> -->
                                            <th colspan="5" class='text-center'>Summary</th>
                                            <th colspan="5" class='text-center'>Adjustment</th>
                                            <th colspan="5" class='text-center'>Net</th>
                                        </tr>
                                        <tr>
                                            <th class='text-center align-middle'>KP7</th>
                                            <th class='text-center align-middle'>KPX</th>
                                            <th class='text-truncate text-center align-middle'>Total Count</th>
                                            <th class='text-truncate text-center align-middle'>Total Principal</th>
                                            <th class='text-truncate text-center align-middle'>Charge to Partner</th>
                                            <th class='text-truncate text-center align-middle'>Charge to Customer</th>
                                            <th class='text-truncate text-center align-middle'>Total Charge</th>
                                            <th class='text-truncate text-center align-middle'>Total Count</th>
                                            <th class='text-truncate text-center align-middle'>Total Principal</th>
                                            <th class='text-truncate text-center align-middle'>Charge to Partner</th>
                                            <th class='text-truncate text-center align-middle'>Charge to Customer</th>
                                            <th class='text-truncate text-center align-middle'>Total Charge</th>
                                            <th class='text-truncate text-center align-middle'>Total Count</th>
                                            <th class='text-truncate text-center align-middle'>Total Principal</th>
                                            <th class='text-truncate text-center align-middle'>Charge to Partner</th>
                                            <th class='text-truncate text-center align-middle'>Charge to Customer</th>
                                            <th class='text-truncate text-center align-middle'>Total Charge</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <!-- Data will be populated via JavaScript -->
                                    </tbody>
                                        <tfoot class="sticky-bottom table-dark">
                                            <tr>
                                                <th colspan="4" style="text-align:right">Total : </th>
                                                <!-- Data will be populated via JavaScript -->
                                                <th class="text-center">0</th>
                                                <th class="text-end">0.00</th>
                                                <th class="text-end">0.00</th>
                                                <th class="text-end">0.00</th>
                                                <th class="text-end fw-bold">0.00</th>
                                                <th class="text-center">0</th>
                                                <th class="text-end">0.00</th>
                                                <th class="text-end">0.00</th>
                                                <th class="text-end">0.00</th>
                                                <th class="text-end fw-bold">0.00</th>
                                                <th class="text-center fw-bold">0</th>
                                                <th class="text-end fw-bold">0.00</th>
                                                <th class="text-end fw-bold">0.00</th>
                                                <th class="text-end fw-bold">0.00</th>
                                                <th class="text-end fw-bold">0.00</th>
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
</body>
<?php include '../../../templates/footer.php'; ?>
<script>
    // Initialize Select2 for partner dropdown
    $('#partnerlistDropdown').select2({
        placeholder: 'Search or select a Partner...',
        allowClear: true
    });

    // Initialize variables
    let currentPage = 1;
    let rowsPerPage = 10;
    
    // Event handlers
    $('#searchButton').click(function() {
        console.log('Search button clicked'); // Debug log
        
        // Validate required fields before searching
        if (validateRequiredFields()) {
            currentPage = 1;
            loadTransactionData();
            // Show the card body when search is performed
            toggleCardBodyVisibility(true);
        }
    });

    $('#rowsPerPage').change(function() {
        rowsPerPage = parseInt($(this).val());
        currentPage = 1;
        if ($('#transactionReportTable tbody tr').length > 0 && !$('#transactionReportTable tbody tr').hasClass('no-data')) {
            loadTransactionData();
        }
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

    // Method to display transaction data in the table tbody
    function displayTransactionData(data, totals) {
        const tbody = $('#transactionReportTable tbody');
        const tfoot = $('#transactionReportTable tfoot tr');
        
        // Clear existing data
        tbody.empty();
        
        // Better way to clear tfoot totals - remove all th elements after the first 4
        const footerCells = tfoot.find('th');
        if (footerCells.length > 4) {
            footerCells.slice(4).remove();
        }
        
        if (!data || data.length === 0) {
            tbody.html(`
                <tr class="no-data">
                    <td colspan="19" class="text-center py-4">
                        <i class="fas fa-info-circle text-muted"></i>
                        <br>No transaction data found for the selected criteria
                    </td>
                </tr>
            `);
            
            // Add empty totals row
            tfoot.append(`
                <th>0</th><th>0.00</th><th>0.00</th><th>0.00</th><th>0.00</th>
                <th>0</th><th>0.00</th><th>0.00</th><th>0.00</th><th>0.00</th>
                <th>0</th><th>0.00</th><th>0.00</th><th>0.00</th><th>0.00</th>
            `);
            
            // Hide export button when no data
            $('#ExportButton').hide();
            return;
        }
        
        // Build table rows
        let tableRows = '';
        data.forEach(function(row, index) {
            tableRows += `
                <tr class="transaction-row" data-row-index="${index}">
                    <td class='text-truncate'>${escapeHtml(row.partner_name || '')}</td>
                    <td class="text-center">${escapeHtml(row.partner_id || '')}</td>
                    <td class="text-center">${escapeHtml(row.partner_id_kpx || '')}</td>
                    <td class="text-center">${escapeHtml(row.gl_code || '')}</td>
                    
                    <!-- Summary columns -->
                    <td class="text-center">${formatNumber(row.summary_vol || 0)}</td>
                    <td class="text-end">${formatCurrency(row.summary_principal || 0)}</td>
                    <td class="text-end">${formatCurrency(row.summary_charges_partner || 0)}</td>
                    <td class="text-end">${formatCurrency(row.summary_charges_customer || 0)}</td>
                    <td class="text-end fw-bold">${formatCurrency(row.summary_total_charge || 0)}</td>
                    
                    <!-- Adjustment columns -->
                    <td class="text-center">${formatNumber(row.adjustment_vol || 0)}</td>
                    <td class="text-end">${formatCurrency(row.adjustment_principal || 0)}</td>
                    <td class="text-end">${formatCurrency(row.adjustment_charges_partner || 0)}</td>
                    <td class="text-end">${formatCurrency(row.adjustment_charges_customer || 0)}</td>
                    <td class="text-end fw-bold">${formatCurrency(row.adjustment_total_charge || 0)}</td>
                    
                    <!-- Net columns -->
                    <td class="text-center fw-bold">${formatNumber(row.net_vol || 0)}</td>
                    <td class="text-end fw-bold">${formatCurrency(row.net_principal || 0)}</td>
                    <td class="text-end fw-bold">${formatCurrency(row.net_charges_partner || 0)}</td>
                    <td class="text-end fw-bold">${formatCurrency(row.net_charges_customer || 0)}</td>
                    <td class="text-end fw-bold">${formatCurrency(row.net_total_charge || 0)}</td>
                </tr>
            `;
        });
        
        // Insert rows into tbody
        tbody.html(tableRows);
        
        // Update totals footer
        updateTotalsFooter(totals || {});
        
        // Check if export button should be visible based on totals
        toggleExportButtonVisibility(totals || {});
        
        // Add double-click event handlers for transaction details
        $('.transaction-row').off('dblclick').on('dblclick', function() {
            const rowIndex = $(this).data('row-index');
            const rowData = data[rowIndex];
            if (rowData) {
                showTransactionDetails(rowData);
            }
        });
        
        console.log(`Displayed ${data.length} transaction rows`);
        // Show or hide the hint label depending on whether rows exist
        if (Array.isArray(data) && data.length > 0) {
            $('#searchHint').show();
        } else {
            $('#searchHint').hide();
        }
    }

    // Method to update totals in footer - improved version
    function updateTotalsFooter(totals) {
        const tfoot = $('#transactionReportTable tfoot');
        
        // Completely rebuild the footer row
        const footerRow = `
            <tr>
                <th colspan="4" style="text-align:right">Total : </th>
                <!-- Summary totals -->
                <th class="text-center">${formatNumber(totals.total_summary_vol || 0)}</th>
                <th class="text-end">${formatCurrency(totals.total_summary_principal || 0)}</th>
                <th class="text-end">${formatCurrency(totals.total_summary_charges_partner || 0)}</th>
                <th class="text-end">${formatCurrency(totals.total_summary_charges_customer || 0)}</th>
                <th class="text-end fw-bold">${formatCurrency(totals.total_summary_total_charge || 0)}</th>
                
                <!-- Adjustment totals -->
                <th class="text-center">${formatNumber(totals.total_adjustment_vol || 0)}</th>
                <th class="text-end">${formatCurrency(totals.total_adjustment_principal || 0)}</th>
                <th class="text-end">${formatCurrency(totals.total_adjustment_charges_partner || 0)}</th>
                <th class="text-end">${formatCurrency(totals.total_adjustment_charges_customer || 0)}</th>
                <th class="text-end fw-bold">${formatCurrency(totals.total_adjustment_total_charge || 0)}</th>
                
                <!-- Net totals -->
                <th class="text-center fw-bold">${formatNumber(totals.total_net_vol || 0)}</th>
                <th class="text-end fw-bold">${formatCurrency(totals.total_net_principal || 0)}</th>
                <th class="text-end fw-bold">${formatCurrency(totals.total_net_charges_partner || 0)}</th>
                <th class="text-end fw-bold">${formatCurrency(totals.total_net_charges_customer || 0)}</th>
                <th class="text-end fw-bold">${formatCurrency(totals.total_net_total_charge || 0)}</th>
            </tr>
        `;
        
        tfoot.html(footerRow);
    }

    // Method to check if all totals are zero and toggle Export button visibility
    function toggleExportButtonVisibility(totals) {
        const exportButton = $('#ExportButton');
        
        // Check if all total values are zero
        const allTotalsZero = (
            (totals.total_summary_vol || 0) === 0 &&
            (totals.total_summary_principal || 0) === 0 &&
            (totals.total_summary_charges_partner || 0) === 0 &&
            (totals.total_summary_charges_customer || 0) === 0 &&
            (totals.total_summary_total_charge || 0) === 0 &&
            (totals.total_adjustment_vol || 0) === 0 &&
            (totals.total_adjustment_principal || 0) === 0 &&
            (totals.total_adjustment_charges_partner || 0) === 0 &&
            (totals.total_adjustment_charges_customer || 0) === 0 &&
            (totals.total_adjustment_total_charge || 0) === 0 &&
            (totals.total_net_vol || 0) === 0 &&
            (totals.total_net_principal || 0) === 0 &&
            (totals.total_net_charges_partner || 0) === 0 &&
            (totals.total_net_charges_customer || 0) === 0 &&
            (totals.total_net_total_charge || 0) === 0
        );
        
        if (allTotalsZero) {
            exportButton.hide();
            console.log('Export button hidden - all totals are zero');
        } else {
            exportButton.show();
            console.log('Export button shown - totals contain data');
        }
    }

    // Main function to load transaction data
    function loadTransactionData() {
        console.log('Loading transaction data...'); // Debug log
        showLoading();
        
        const formData = {
            action: 'get_transaction_data',
            partner: $('#partnerlistDropdown').val() || '',
            start_date: $('#start_date').val() || '',
            end_date: $('#end_date').val() || '',
            source_file: $('#source_file_filter').val() || '',
            page: currentPage,
            rows_per_page: rowsPerPage
        };
        
        console.log('Form data:', formData); // Debug log
        
        $.ajax({
            url: window.location.href,
            type: 'POST',
            data: formData,
            dataType: 'json',
            timeout: 30000,
            beforeSend: function() {
                console.log('AJAX request started'); // Debug log
            },
            success: function(response) {
                console.log('AJAX response:', response); // Debug log
                hideLoading();
                
                if (response && response.success) {
                    // Display the data using our new method
                    displayTransactionData(response.data || [], response.totals || {});
                    updatePagination(response.pagination || {});
                    // Show the card body on successful data load
                    toggleCardBodyVisibility(true);
                    // Show or hide the hint label depending on returned rows (handled in displayTransactionData)
                } else {
                    console.error('Error in response:', response); // Debug log
                    showAlert('Error', response.error || 'Failed to load transaction data', 'error');
                    // Hide the card body on error
                    toggleCardBodyVisibility(false);
                    // Hide the hint label on error
                    $('#searchHint').hide();
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', xhr.responseText); // Debug log
                hideLoading();
                
                let errorMessage = 'Failed to load transaction data';
                if (xhr.responseText) {
                    try {
                        const errorResponse = JSON.parse(xhr.responseText);
                        errorMessage = errorResponse.error || errorMessage;
                    } catch (e) {
                        if (xhr.responseText.includes('Fatal error') || xhr.responseText.includes('Parse error')) {
                            errorMessage = 'Server error occurred. Please check the logs.';
                        } else if (xhr.responseText.includes('Connection failed')) {
                            errorMessage = 'Database connection failed.';
                        }
                    }
                }
                
                showAlert('Error', errorMessage, 'error');
                // Hide the card body on error
                toggleCardBodyVisibility(false);
                // Hide the hint label on error
                $('#searchHint').hide();
            }
        });
    }

    // Helper function to format currency
    function formatCurrency(amount) {
        if (!amount || amount === 0) return '0.00';
        return parseFloat(amount).toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    // Helper function to format numbers
    function formatNumber(num) {
        if (!num || num === 0) return '0';
        return parseInt(num).toLocaleString('en-US');
    }

    // Helper function to escape HTML
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Function to show transaction details in modal (optional)
    function showTransactionDetails(rowData) {
        console.log('Transaction details:', rowData);
    }

    // Function to update pagination info
    function updatePagination(pagination) {
        if (!pagination) return;
        
        const { current_page, total_pages, total_records, start_record, end_record } = pagination;
        
        // Update pagination info
        $('#pagination-info').text(`Showing ${start_record} to ${end_record} of ${total_records} entries`);
        
        // Generate pagination controls
        const paginationUL = $('#pagination');
        paginationUL.empty();
        
        if (total_pages <= 1) return;
        
        // Previous button
        if (current_page > 1) {
            paginationUL.append(`
                <li class="page-item">
                    <a class="page-link" href="#" data-page="${current_page - 1}">Previous</a>
                </li>
            `);
        }
        
        // Page numbers
        let startPage = Math.max(1, current_page - 2);
        let endPage = Math.min(total_pages, current_page + 2);
        
        if (startPage > 1) {
            paginationUL.append(`<li class="page-item"><a class="page-link" href="#" data-page="1">1</a></li>`);
            if (startPage > 2) {
                paginationUL.append(`<li class="page-item disabled"><span class="page-link">...</span></li>`);
            }
        }
        
        for (let i = startPage; i <= endPage; i++) {
            const activeClass = i === current_page ? 'active' : '';
            paginationUL.append(`
                <li class="page-item ${activeClass}">
                    <a class="page-link" href="#" data-page="${i}">${i}</a>
                </li>
            `);
        }
        
        if (endPage < total_pages) {
            if (endPage < total_pages - 1) {
                paginationUL.append(`<li class="page-item disabled"><span class="page-link">...</span></li>`);
            }
            paginationUL.append(`<li class="page-item"><a class="page-link" href="#" data-page="${total_pages}">${total_pages}</a></li>`);
        }
        
        // Next button
        if (current_page < total_pages) {
            paginationUL.append(`
                <li class="page-item">
                    <a class="page-link" href="#" data-page="${current_page + 1}">Next</a>
                </li>
            `);
        }
    }

    // Validation function for required fields
    function validateRequiredFields() {
        let isValid = true;
        const requiredFields = ['#start_date', '#end_date'];
        
        requiredFields.forEach(function(field) {
            const $field = $(field);
            if (!$field.val()) {
                $field.addClass('is-invalid');
                isValid = false;
            } else {
                $field.removeClass('is-invalid');
            }
        });
        
        if (!isValid) {
            showAlert('Validation Error', 'Please fill in all required fields (Start Date and End Date)', 'warning');
        }
        
        return isValid;
    }

    // Loading functions
    function showLoading() {
        $('#loading-overlay').show();
    }

    function hideLoading() {
        $('#loading-overlay').hide();
    }

    // Alert function
    function showAlert(title, message, type) {
        Swal.fire({
            title: title,
            text: message,
            icon: type,
            confirmButtonColor: '#dc3545'
        });
    }

    // Method to toggle card body visibility
    function toggleCardBodyVisibility(show = false) {
        const cardBody = $('.card-body');
        
        if (show) {
            cardBody.show();
            cardBody.css('display', 'block');
        } else {
            cardBody.hide();
            cardBody.css('display', 'none');
        }
    }

    // Method to toggle hint label visibility
    function toggleHintLabelVisibility(show = false) {
        const hintLabel = $('.card-header .mb-3 label');
        
        if (show) {
            hintLabel.show();
            hintLabel.css('display', 'block');
        } else {
            hintLabel.hide();
            hintLabel.css('display', 'none');
        }
    }

    // Method to reset to default state
    function resetToDefaultState() {
        // Hide the card body
        toggleCardBodyVisibility(false);
        
        // Hide the hint label
        toggleHintLabelVisibility(false);
        
        // Hide the export button
        $('#ExportButton').hide();
        
        // Clear the table data
        const tbody = $('#transactionReportTable tbody');
        const tfoot = $('#transactionReportTable tfoot tr');
        
        tbody.empty();
        
        // Reset footer totals
        const footerCells = tfoot.find('th');
        if (footerCells.length > 4) {
            footerCells.slice(4).remove();
        }
        tfoot.append(`
            <th class="text-center">0</th>
            <th class="text-end">0.00</th>
            <th class="text-end">0.00</th>
            <th class="text-end">0.00</th>
            <th class="text-end fw-bold">0.00</th>
            <th class="text-center">0</th>
            <th class="text-end">0.00</th>
            <th class="text-end">0.00</th>
            <th class="text-end">0.00</th>
            <th class="text-end fw-bold">0.00</th>
            <th class="text-center fw-bold">0</th>
            <th class="text-end fw-bold">0.00</th>
            <th class="text-end fw-bold">0.00</th>
            <th class="text-end fw-bold">0.00</th>
            <th class="text-end fw-bold">0.00</th>
        `);
        
        // Reset pagination info
        $('#pagination-info').text('Showing 0 to 0 of 0 entries');
        $('#pagination').empty();
        
        console.log('Reset to default state');
    }

    // Initialize page - ensure export button is hidden by default
    $(document).ready(function() {
        console.log('Page loaded');
        // Hide the card body, export button, and hint label on page load
        toggleCardBodyVisibility(false);
        toggleHintLabelVisibility(false);
        $('#ExportButton').hide();
    });
</script>
</html>