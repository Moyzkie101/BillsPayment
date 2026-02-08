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
}else {
    // Redirect to login page if user_type is not set
    header("Location: ../../../index.php");
    session_abort();
    session_destroy();
    exit();
}

// $partnersQuery = "SELECT partner_name FROM masterdata.partner_masterfile order by partner_name";
// $partnersResult = $conn->query($partnersQuery);

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

if(isset($_POST['action']) && $_POST['action'] === 'generate_report'){
    $partner = $_POST['partner'];
    $filterType = $_POST['filterType'];
    $startDate = $_POST['startDate'];
    $endDate = isset($_POST['endDate']) ? $_POST['endDate'] : '';

    // Initialize arrays and variables
    $whereConditions = [];
    $params = [];
    $types = '';
    
    // Build WHERE conditions based on filter type - prepare date parameters first
    $dateParams = [];
    $dateTypes = '';
    
    if(!empty($filterType)){
        if($filterType === 'daily'){
            // For daily, use only startDate for both datetime and cancellation_date
            // Each CTE needs the same parameters, so we need 4 total (2 for each CTE)
            $dateCondition = "(DATE(bt.datetime) = ? OR DATE(bt.cancellation_date) = ?)";
            $dateParams = [$startDate, $endDate, $startDate, $endDate]; // 4 params for 2 CTEs
            $dateTypes = 'ssss';
        }elseif($filterType === 'weekly'){
            // For weekly, use BETWEEN for date range
            // Each CTE needs 4 parameters, so we need 8 total
            $dateCondition = "(DATE(bt.datetime) BETWEEN ? AND ? OR DATE(bt.cancellation_date) BETWEEN ? AND ?)";
            // $dateParams = [$startDate, $endDate, $startDate, $endDate];
            $dateParams = [$startDate, $endDate, $startDate, $endDate, $startDate, $endDate, $startDate, $endDate];
            // $dateTypes = 'ssss';
            $dateTypes = 'ssssssss';
        }elseif($filterType === 'monthly'){
            // For monthly, convert to first and last day of month
            $dateCondition = "(DATE(bt.datetime) BETWEEN ? AND ? OR DATE(bt.cancellation_date) BETWEEN ? AND ?)";
            $startMonth = $startDate . '-01';
            $endMonth = date('Y-m-t', strtotime($endDate . '-01')); // Last day of month
            // $dateParams = [$startMonth, $endMonth, $startMonth, $endMonth];
            // $dateTypes = 'ssss';
            $dateParams = [$startMonth, $endMonth, $startMonth, $endMonth, $startMonth, $endMonth, $startMonth, $endMonth];
            $dateTypes = 'ssssssss';
        }elseif($filterType === 'yearly'){
            // For yearly, convert to first and last day of year
            $dateCondition = "(DATE(bt.datetime) BETWEEN ? AND ? OR DATE(bt.cancellation_date) BETWEEN ? AND ?)";
            $startYear = $startDate . '-01-01';
            $endYear = $endDate . '-12-31';
            $dateParams = [$startYear, $endYear, $startYear, $endYear];
            $dateTypes = 'ssss';
            // $dateParams = [$startYear, $endYear, $startYear, $endYear, $startYear, $endYear, $startYear, $endYear];
            // $dateTypes = 'ssssssss';
        }
    }
    
    // Combine date parameters with other parameters
    $params = array_merge($dateParams, $params);
    $types = $dateTypes . $types;
    
    // Partner filter
    if (!empty($partner)) {
        if($partner !== 'All'){
            $whereConditions[] = "mpm.partner_name = ?";
            $params[] = $partner;
            $types .= 's';
        }
    }

    // Build WHERE clause for main query
    $mainWhereClause = '';
    if (!empty($whereConditions)) {
        $mainWhereClause = 'AND ' . implode(' AND ', $whereConditions);
    }

    $DataQuery = "WITH summary_vol AS (
                SELECT
                    bt.partner_id,
                    bt.partner_id_kpx,
                    COUNT(*) AS vol1,
                    sum(bt.amount_paid) AS principal1,
                    sum(bt.charge_to_partner + charge_to_customer) AS charge1
                FROM
                    mldb.billspayment_transaction AS bt 
                WHERE
                    $dateCondition
                    AND bt.status IS NULL 
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
                sum(bt.charge_to_partner + charge_to_customer) AS charge2
            FROM
                mldb.billspayment_transaction AS bt 
            WHERE
                $dateCondition
                AND bt.status = '*' 
            GROUP BY
                bt.partner_id,
                bt.partner_id_kpx
        )

        SELECT
            mpm.partner_name,
            COALESCE(sv.vol1, 0) AS summary_vol,
            COALESCE(sv.principal1, 0) AS summary_principal,
            COALESCE(sv.charge1, 0) AS summary_charges,
            
            COALESCE(av.vol2, 0) AS adjustment_vol,
            COALESCE(ABS(av.principal2), 0) AS adjustment_principal,
            COALESCE(ABS(av.charge2), 0) AS adjustment_charges,
            
            (COALESCE(sv.vol1, 0) - COALESCE(av.vol2, 0)) AS net_vol,
            (COALESCE(sv.principal1, 0) - COALESCE(ABS(av.principal2), 0)) AS net_principal,
            (COALESCE(sv.charge1, 0) - COALESCE(ABS(av.charge2), 0)) AS net_charges
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
        ORDER BY mpm.partner_name";

    try {
        // Use prepared statement to execute the query
        $stmt = $conn->prepare($DataQuery);
        
        if ($stmt) {
            // Bind parameters if any exist
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            
            $stmt->execute();
            $DataResult = $stmt->get_result();
            
            if ($DataResult) {
                $data = $DataResult->fetch_all(MYSQLI_ASSOC);
                echo json_encode([
                    'status' => 'success',
                    'data' => $data,
                    'debug' => [
                        'filterType' => $filterType,
                        'startDate' => $startDate,
                        'endDate' => $endDate,
                        'partner' => $partner,
                        'dateCondition' => $dateCondition,
                        'params' => $params,
                        'types' => $types,
                        'paramCount' => count($params),
                        'placeholderCount' => substr_count($DataQuery, '?')
                    ]
                ]);
            } else {
                echo json_encode([
                    'status' => 'error',
                    'message' => 'No result from query',
                    'data' => []
                ]);
            }
            
            $stmt->close();
        } else {
            throw new Exception("Prepare failed: " . $conn->error);
        }
    } catch (Exception $e) {
        error_log("Database error: " . $e->getMessage());
        echo json_encode([
            'status' => 'error',
            'message' => 'Database error: ' . $e->getMessage(),
            'data' => [],
            'debug' => [
                'query' => $DataQuery,
                'params' => $params,
                'types' => $types,
                'paramCount' => count($params),
                'placeholderCount' => substr_count($DataQuery, '?')
            ]
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
    <title>Volume Report | <?php if($_SESSION['user_type'] === 'admin' || $_SESSION['user_type'] === 'user') echo ucfirst($_SESSION['user_type']); else echo "Guest";?></title>
    <!-- custom CSS file link  -->
    <link rel="stylesheet" href="../../../assets/css/templates/style.css?v=<?php echo time(); ?>">
    <script src="https://kit.fontawesome.com/30b908cc5a.js" crossorigin="anonymous"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="../../../assets/js/sweetalert2.all.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>

    <link rel="icon" href="../../../images/MLW logo.png" type="image/png">
    <style>
        /* Day Shortcut Buttons Styling */
        .day-shortcut-container {
            padding: 10px 5px;
            border-radius: 5px;
            /* margin-bottom: 15px; */
            display: flex;
            align-items: center;
            background-color: #f8f9fa;
            border: 1px solid #e0e0e0;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .day-buttons-label {
            font-weight: bold;
            margin-right: 15px;
            color: #666;
            white-space: nowrap;
            padding-left: 10px;
        }

        .day-buttons-wrapper {
            display: flex;
            flex-wrap: wrap;
            gap: 2px;
            max-width: 100%;
            overflow-x: auto;
            padding: 5px;
            align-items: center;
        }

        .day-button {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            border: 1px solid #dc3545;
            background-color: white;
            color: #dc3545;
            font-weight: bold;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        /* Pill shape for month buttons */
        .day-button.month-button {
            width: auto !important;
            min-width: 120px !important;
            padding: 8px 16px !important;
            border-radius: 25px !important;
            font-size: 12px !important;
            white-space: nowrap !important;
        }

        /* Pill shape for year buttons */
        .day-button.year-button {
            width: auto !important;
            min-width: 70px !important;
            padding: 8px 16px !important;
            border-radius: 25px !important;
            font-size: 12px !important;
        }

        /* Ensure day buttons remain circular */
        .day-button:not(.month-button):not(.year-button):not(.day-button-all) {
            width: 35px;
            height: 35px;
            border-radius: 50%;
        }

        .day-button:hover {
            background-color: rgba(220,53,69,0.8);
            color: white;
            transform: scale(1.05);
            box-shadow: 0 2px 4px rgba(220,53,69,0.3);
            cursor: pointer;
            z-index: 4;
        }

        .day-button-active {
            background-color: #dc3545;
            color: white;
            transform: scale(1.1);
            box-shadow: 0 2px 5px rgba(220,53,69,0.4);
            position: relative;
            z-index: 5;
        }

        .day-button-active:after {
            content: "";
            position: absolute;
            bottom: -3px;
            left: 50%;
            transform: translateX(-50%);
            width: 6px;
            height: 6px;
            background-color: #ff8a04ff;
            border-radius: 50%;
        }

        .day-button-all {
            width: auto;
            padding: 0 15px;
            border-radius: 20px;
            background-color: #6c757d;
            color: white;
            border-color: #6c757d;
        }

        .day-button-all:hover {
            background-color: #5a6268;
            border-color: #545b62;
        }

        /* Export Modal Styling */
        .export-options {
            text-align: center;
            padding: 20px 0;
        }

        .export-buttons-container {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-top: 20px;
        }

        .export-btn {
            padding: 12px 24px;
            font-size: 16px;
            font-weight: bold;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            min-width: 150px;
        }

        .export-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }

        .export-btn i {
            margin-right: 8px;
            font-size: 18px;
        }

        /* Custom SweetAlert styling */
        .swal2-popup {
            border-radius: 15px !important;
        }

        .swal2-title {
            color: #333 !important;
            font-weight: bold !important;
        }

        .swal2-html-container {
            margin: 0 !important;
        }

        .month-separator {
            width: 100%;
            text-align: center;
            font-size: 10px;
            color: #666;
            margin: 5px 0;
            font-weight: bold;
            border-bottom: 1px solid #ddd;
            padding-bottom: 2px;
        }

        /* Ensure day number buttons remain circular */
        .day-button.day-number-button {
            width: 35px !important;
            height: 35px !important;
            border-radius: 50% !important;
            font-size: 12px !important;
            margin: 2px !important;
        }

        /* Responsive adjustments for smaller screens */
        @media (max-width: 768px) {
            .day-buttons-wrapper {
                gap: 1px;
            }
            
            .day-button.day-number-button {
                width: 30px !important;
                height: 30px !important;
                font-size: 11px !important;
                margin: 1px !important;
            }
        }
    </style>
</head>
<body>
    <div class="main-container">
        <div class="top-content">
            <div class="nav-container">
                <div style="text-align: left;">
                    <i id="menu-btn" class="fa-solid fa-bars"></i>Menu
                </div>
                <div class="usernav">
                    <h6><?php 
                            if($_SESSION['user_type'] === 'admin'){
                                echo $_SESSION['admin_name'];
                            }elseif($_SESSION['user_type'] === 'user'){
                                echo $_SESSION['user_name']; 
                            }else{
                                echo "GUEST";
                            }
                    ?></h6>
                    <h6 style="margin-left:5px;"><?php 
                        if($_SESSION['user_type'] === 'admin'){
                            echo "(".$_SESSION['admin_email'].")";
                        }elseif($_SESSION['user_type'] === 'user'){
                            echo "(".$_SESSION['user_email'].")";
                        }else{
                            echo "GUEST";
                        }
                    ?></h6>
                </div>
            </div>
        </div>
        <!-- Show and Hide Side Nav Menu -->
        <?php include '../../../templates/sidebar.php'; ?>
        <div id="loading-overlay">
            <div class="loading-spinner"></div>
        </div>
        <center><h3>VOLUME REPORT</h3></center>
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
                                        <option value="All">All</option>
                                        <!-- options will be populated by JS -->
                                    </select>
                                </div>

                                <!-- Time Frame -->
                                <div class="col-md-2 col-sm-6">
                                    <label class="form-label">Time Frame:</label>
                                    <select class="form-select" name="filterType" required>
                                        <option value="">Select Time Frame</option>
                                        <option value="daily">Per Day</option>
                                        <option value="weekly">Date Range</option>
                                        <option value="monthly">Monthly</option>
                                        <!-- <option value="yearly">Yearly</option> -->
                                    </select>
                                </div>

                                <!-- Date Range based on selected Time Frame -->
                                <div class="col-md-2" style="display: none;">
                                    <label class="form-label">Start Date:</label>
                                    <input type="date" class="form-control" name="startDate" required>
                                </div>
                                <div class="col-md-2" style="display: none;">
                                    <label class="form-label">End Date:</label>
                                    <input type="date" class="form-control" name="endDate" required>
                                </div>

                                <!-- Action Button -->
                                <div class="col-md-1 col-sm-6">
                                    <div class="col-md-3 d-flex align-items-end">
                                        <button type="button" class="btn btn-secondary" id="generateReport" disabled>Generate</button>
                                    </div>
                                    
                                </div>

                                <!-- Export Button -->
                                <div class="col-md-1 col-sm-6">
                                    <div class="align-items-end">
                                        <button class="btn btn-danger" id="exportButton" type="button">Export to</button>
                                    </div>
                                </div>
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
                                            <th rowspan="2" class='text-truncate text-center align-middle'>Bank</th>
                                            <th rowspan="2" class='text-truncate text-center align-middle'>Biller's Name</th>
                                            <th colspan="3" class='text-truncate text-center align-middle'>KP7 / KPX</th>
                                            <th colspan="3" class='text-truncate text-center align-middle'>Adjustment</th>
                                            <th colspan="3" class='text-truncate text-center align-middle'>Net</th>
                                        </tr>
                                        <tr>
                                            <!-- Column header for KP7 / KPX -->
                                            <th class='text-center'>Vol.</th>
                                            <th class='text-center'>Principal</th>
                                            <th class='text-center'>Charge</th>

                                            <!-- Column header for Adjustment -->
                                            <th class='text-center'>Vol.</th>
                                            <th class='text-center'>Principal</th>
                                            <th class='text-center'>Charge</th>

                                            <!-- Column header for Net -->
                                            <th class='text-center'>Vol.</th>
                                            <th class='text-center'>Principal</th>
                                            <th class='text-center'>Charge</th>
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
                                            <td></td>
                                            <td></td>
                                        </tr>
                                    </tbody>
                                    <tfoot class="sticky-bottom table-dark">
                                        <tr>
                                            <th colspan="4" class="text-end">Total : </th>
                                            <th class="text-center" id="totalsummaryvolume">0</th>
                                            <th class="text-end" id="totalsummaryprincipal">0.00</th>
                                            <th class="text-end" id="totalsummarycharge">0.00</th>
                                            <th class="text-center" id="totaladjustmentvolume">0</th>
                                            <th class="text-end" id="totaladjustmentprincipal">0.00</th>
                                            <th class="text-end" id="totaladjustmentcharge">0.00</th>
                                            <th class="text-center" id="totalnetvolume">0</th>
                                            <th class="text-end" id="totalnetprincipal">0.00</th>
                                            <th class="text-end" id="totalnetcharge">0.00</th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!--<div class="container-fluid">
             <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">Partners Name:</label>
                    <select id="partnerlistDropdown" class="form-select select2" aria-label="Select Partner" name="partnerlist" data-placeholder="Search or select a Partner..." required>
                        <option value="">Select Partner</option>
                        <option value="All">All</option> -->
                        <!-- options will be populated by JS -->
                    <!-- </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Time Frame:</label>
                    <select class="form-select" name="filterType" required>
                        <option value="">Select Time Frame</option>
                        <option value="daily">Per Day</option>
                        <option value="weekly">Date Range</option>
                        <option value="monthly">Monthly</option> -->
                        <!-- <option value="yearly">Yearly</option> -->
                    <!-- </select>
                </div>
                <div class="col-md-2" style="display: none;">
                    <label class="form-label">Start Date:</label>
                    <input type="date" class="form-control" name="startDate" required>
                </div>
                <div class="col-md-2" style="display: none;">
                    <label class="form-label">End Date:</label>
                    <input type="date" class="form-control" name="endDate" required>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="button" class="btn btn-secondary" id="generateReport" disabled>Generate</button>
                    <button class="btn btn-danger" id="exportButton" type="button">Export to</button>
                </div>
            </div> -->

            <!-- <div class="container-fluid">
                <div class="text-center">
                    
                </div>
                <div class="day-shortcut-container mt-2">
                    <div class="day-buttons-label">Filter by Day:</div>
                    <div class="day-buttons-wrapper" id="dayButtonsWrapper">
                        <button class="day-button day-button-all day-button-active" id="allDaysButton">All</button>
                    </div>
                </div>
                <div class="day-shortcut-container mt-2">
                    <div class="day-buttons-label">Filter by Month:</div>
                    <div class="day-buttons-wrapper" id="dayButtonsWrapper">
                        <button class="day-button day-button-all day-button-active" id="allDaysButton">All</button>
                    </div>
                </div>
                <div class="day-shortcut-container mt-2">
                    <div class="day-buttons-label">Filter by Year:</div>
                    <div class="day-buttons-wrapper" id="dayButtonsWrapper">
                        <button class="day-button day-button-all day-button-active" id="allDaysButton">All</button>
                    </div>
                </div>
                <div class="table-responsive mt-2">
                    <table id="transactionReportTable" class="table table-bordered table-hover table-striped">
                        <thead class="table-light">
                            <tr>
                                <th rowspan="2">No.</th>
                                <th rowspan="2">Partner Name</th>
                                <th rowspan="2">Bank</th>
                                <th rowspan="2">Biller's Name</th>
                                <th colspan="3">KP7 / KPX</th>
                                <th colspan="3">Adjustment</th>
                                <th colspan="3">Net</th>
                            </tr>
                            <tr> -->
                                <!-- Column header for KP7 / KPX -->
                                <!-- <th>Vol.</th>
                                <th>Principal</th>
                                <th>Charge</th> -->

                                <!-- Column header for Adjustment -->
                                <!-- <th>Vol.</th>
                                <th>Principal</th>
                                <th>Charge</th> -->

                                <!-- Column header for Net -->
                                <!-- <th>Vol.</th>
                                <th>Principal</th>
                                <th>Charge</th> -->
                            <!-- </tr>
                        </thead>
                        <tbody> -->
                            <!-- Data will be populated via JavaScript -->
                            <!-- <tr>
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
                                <td></td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="4" class="text-end">Total : </th>
                                <th id="totalsummaryvolume">0</th>
                                <th id="totalsummaryprincipal">0.00</th>
                                <th id="totalsummarycharge">0.00</th>
                                <th id="totaladjustmentvolume">0</th>
                                <th id="totaladjustmentprincipal">0.00</th>
                                <th id="totaladjustmentcharge">0.00</th>
                                <th id="totalnetvolume">0</th>
                                <th id="totalnetprincipal">0.00</th>
                                <th id="totalnetcharge">0.00</th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div> -->
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
    // Initialize filter containers as hidden
    $('.day-shortcut-container').hide();
    
    // Hide date inputs by default until filter type is selected
    $('input[name="startDate"]').closest('.col-md-2').hide();
    $('input[name="endDate"]').closest('.col-md-2').hide();
    
    // Hide export button initially
    $('#exportButton').hide();
    
    // Handle date input changes
    $('input[name="startDate"], input[name="endDate"]').on('change', function() {
        toggleGenerateButton();
    });
    
    // Handle filter type change - show appropriate input fields
    $('select[name="filterType"]').on('change', function() {
        const filterType = $(this).val();
        
        // Hide all input containers first
        $('input[name="startDate"]').closest('.col-md-2').hide();
        $('input[name="endDate"]').closest('.col-md-2').hide();
        
        // Reset input values
        $('input[name="startDate"]').val('');
        $('input[name="endDate"]').val('');
        
        // Hide all filter containers and reset them to default state
        $('.day-shortcut-container').hide();
        resetFilterContainers();
        
        // Hide export button when filter type changes
        $('#exportButton').hide();
        
        // Clear the report table
        clearReportTable();
        
        if (filterType) {
            // Show and configure inputs based on filter type
            configureInputsForFilterType(filterType);
            const $startDateInput = $('input[name="startDate"]');
            const $endDateInput = $('input[name="endDate"]');
            
            // Show the appropriate filter containers
            switch(filterType) {
                case 'daily':
                    $startDateInput.closest('.col-md-2').show();
                    $endDateInput.closest('.col-md-2').hide();
                    break;
                case 'weekly':
                    // Show both input containers
                    $startDateInput.closest('.col-md-2').show();
                    $endDateInput.closest('.col-md-2').show();
                    break;
                case 'monthly':
                    // Show both input containers
                    $startDateInput.closest('.col-md-2').show();
                    $endDateInput.closest('.col-md-2').show();
                    break;
                case 'yearly':
                    // Show both input containers
                    $startDateInput.closest('.col-md-2').show();
                    $endDateInput.closest('.col-md-2').show();
                    break;
            }
        }
        
        toggleGenerateButton();
    });
    
    function configureInputsForFilterType(filterType) {
        const startDateInput = $('input[name="startDate"]');
        const endDateInput = $('input[name="endDate"]');
        const startLabel = startDateInput.closest('.col-md-2').find('label');
        const endLabel = endDateInput.closest('.col-md-2').find('label');
        
        switch(filterType) {
            case 'daily':
                // Date input for daily
                startDateInput.attr('type', 'date');
                startLabel.text('Select Date:');
                break;
            case 'weekly':
                // Date input for weekly
                startDateInput.attr('type', 'date');
                endDateInput.attr('type', 'date');
                startLabel.text('Start Date:');
                endLabel.text('End Date:');
                break;
                
            case 'monthly':
                // Month input for monthly
                startDateInput.attr('type', 'month');
                endDateInput.attr('type', 'month');
                startLabel.text('Start Month:');
                endLabel.text('End Month:');
                break;
                
            case 'yearly':
                // Year input for yearly
                startDateInput.attr('type', 'number');
                endDateInput.attr('type', 'number');
                startDateInput.attr('min', '2020');
                endDateInput.attr('min', '2020');
                startDateInput.attr('max', '2030');
                endDateInput.attr('max', '2030');
                startDateInput.attr('placeholder', 'YYYY');
                endDateInput.attr('placeholder', 'YYYY');
                startLabel.text('Start Year:');
                endLabel.text('End Year:');
                break;
        }
    }
    
    // Handle filter button clicks
    $('.day-button').on('click', function() {
        const container = $(this).closest('.day-shortcut-container');
        
        // Remove active class from all buttons in this container
        container.find('.day-button').removeClass('day-button-active');
        
        // Add active class to clicked button
        $(this).addClass('day-button-active');
        
        // Update date inputs based on selection
        updateDateInputsFromFilter($(this));
        
        // Enable generate button
        toggleGenerateButton();
    });
    
    // Generate button click handler
    $('#generateReport').on('click', function() {
        const filterType = $('select[name="filterType"]').val();
        const partner = $('#partnerlistDropdown').val();
        let startDate = $('input[name="startDate"]').val();
        let endDate = $('input[name="endDate"]').val();
        
        if (!filterType || !partner) {
            Swal.fire({
                icon: 'warning',
                title: 'Missing Information',
                text: 'Please select both Filter Type and Partner before generating the report.'
            });
            return;
        }
        
        if (!startDate) {
            Swal.fire({
                icon: 'warning',
                title: 'Missing Date',
                text: filterType === 'daily' ? 'Please select a date.' : 'Please fill in the Start Date.'
            });
            return;
        }
        
        // For daily selection, set endDate same as startDate
        if (filterType === 'daily') {
            endDate = startDate;
        } else {
            // For other filter types, check if endDate is provided
            if (!endDate) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Missing End Date',
                    text: 'Please fill in the End Date.'
                });
                return;
            }
        }
        
        console.log('Generating report with:', {
            filterType: filterType,
            partner: partner,
            startDate: startDate,
            endDate: endDate
        });
        
        // Determine and show appropriate filter containers based on date format
        showFilterContainersBasedOnDates(startDate, endDate);
        
        // Show loading
        $('#loading-overlay').show();
        
        // Make AJAX request to generate report
        $.ajax({
            url: '',
            type: 'POST',
            data: {
                action: 'generate_report',
                partner: partner,
                filterType: filterType,
                startDate: startDate,
                endDate: endDate
            },
            success: function(response) {
                console.log('Response received:', response);
                try {
                    const result = JSON.parse(response);
                    
                    // Check if the response has the new structure with status
                    if (result.status) {
                        if (result.status === 'success') {
                            populateReportTable(result.data);
                        } else {
                            console.error('Server error:', result.message);
                            Swal.fire({
                                icon: 'error',
                                title: 'Server Error',
                                text: result.message || 'Error processing report data.'
                            });
                        }
                    } else {
                        // Handle legacy response format (array of data)
                        populateReportTable(result);
                    }
                } catch (e) {
                    console.error('Error parsing response:', e);
                    console.log('Raw response:', response);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Error processing report data. Please check console for details.'
                    });
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', {xhr: xhr, status: status, error: error});
                Swal.fire({
                    icon: 'error',
                    title: 'Connection Error',
                    text: 'Failed to generate report. Please check your connection and try again.'
                });
            },
            complete: function() {
                $('#loading-overlay').hide();
            }
        });
    });

    function adjustTableHeight() {
        const dayFilter = $('#dayFilterContainer');
        const monthFilter = $('#monthFilterContainer');
        const yearFilter = $('#yearFilterContainer');
        const tableContainer = $('#tableContainer');
        
        // Check if any filter containers are visible
        const hasVisibleFilters = dayFilter.is(':visible') || monthFilter.is(':visible') || yearFilter.is(':visible');
        
        if (hasVisibleFilters) {
            tableContainer.css('max-height', '700px');
        } else {
            tableContainer.css('max-height', '745px');
        }
    }
    
    function showFilterContainersBasedOnDates(startDate, endDate) {
        // Hide all containers first
        $('.day-shortcut-container').hide();
        
        // Reset all filter buttons
        $('.day-button').removeClass('day-button-active');
        
        const filterType = $('select[name="filterType"]').val();
        
        // Show appropriate containers based on filter type
        switch(filterType) {
            case 'daily':
                $('#dayFilterContainer').hide();
                generateDayButtons(startDate, endDate);
                highlightMatchingDayButtons(startDate, endDate);
                break;
            case 'weekly':
                $('#dayFilterContainer').show();
                generateDayButtons(startDate, endDate);
                highlightMatchingDayButtons(startDate, endDate);
                break;
            case 'monthly':
                $('#monthFilterContainer').show();
                generateMonthButtons(startDate, endDate);
                highlightMatchingMonthButtons(startDate, endDate);
                break;
            case 'yearly':
                $('#yearFilterContainer').show();
                generateYearButtons(startDate, endDate);
                highlightMatchingYearButtons(startDate, endDate);
                break;
        }

        // Adjust table height after showing/hiding filters
        adjustTableHeight();
    }
    
    function generateDayButtons(startDate, endDate) {
        const container = $('.day-shortcut-container').eq(0);
        const wrapper = container.find('.day-buttons-wrapper');
        
        // Clear existing buttons except "All"
        wrapper.find('.day-button:not(.day-button-all)').remove();
        
        // Generate day range from startDate to endDate
        const start = new Date(startDate);
        const end = new Date(endDate);
        const dateButtons = [];
        
        // Create buttons for each date in the range
        for (let d = new Date(start); d <= end; d.setDate(d.getDate() + 1)) {
            const dateString = formatDate(d);
            const day = d.getDate();
            const month = d.toLocaleDateString('en-US', { month: 'short' });
            const displayText = `${day} ${month}`;
            
            dateButtons.push({
                date: dateString,
                day: day,
                display: displayText,
                fullDate: new Date(d)
            });
        }
        
        // Group by month for better organization
        const monthGroups = {};
        dateButtons.forEach(btn => {
            const monthYear = btn.fullDate.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
            if (!monthGroups[monthYear]) {
                monthGroups[monthYear] = [];
            }
            monthGroups[monthYear].push(btn);
        });
        
        // Create month separators and buttons
        Object.keys(monthGroups).forEach((monthYear, index) => {
            // Add month separator if more than one month
            if (Object.keys(monthGroups).length > 1) {
                const monthSeparator = $(`<div class="month-separator" style="width: 100%; text-align: center; font-size: 10px; color: #666; margin: 5px 0; font-weight: bold;">${monthYear}</div>`);
                wrapper.append(monthSeparator);
            }
            
            // Add day buttons for this month
            monthGroups[monthYear].forEach(btnData => {
                const button = $(`<button type="button" class="day-button day-number-button" data-date="${btnData.date}" data-day="${btnData.day}" title="${btnData.date}">${btnData.day}</button>`);
                
                // Apply circular styling for day buttons
                button.css({
                    'width': '35px',
                    'height': '35px',
                    'border-radius': '50%',
                    'font-size': '12px',
                    'margin': '2px'
                });
                
                // Add click handler for day filtering
                button.on('click', function() {
                    filterBySpecificDateRange($(this), btnData.date, btnData.date);
                });
                wrapper.append(button);
            });
            
            // Add a small gap between months if multiple months
            if (Object.keys(monthGroups).length > 1 && index < Object.keys(monthGroups).length - 1) {
                const gap = $('<div style="width: 100%; height: 5px;"></div>');
                wrapper.append(gap);
            }
        });
    }

    function generateMonthButtons(startDate, endDate) {
        const container = $('.day-shortcut-container').eq(1);
        const wrapper = container.find('.day-buttons-wrapper');
        
        // Clear existing buttons except "All"
        wrapper.find('.day-button:not(.day-button-all)').remove();
        
        const start = new Date(startDate + '-01');
        const end = new Date(endDate + '-01');
        const months = [];
        
        for (let d = new Date(start); d <= end; d.setMonth(d.getMonth() + 1)) {
            const yearMonth = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0');
            const monthName = d.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
            months.push({ value: yearMonth, label: monthName });
        }
        
        // Create buttons for each month
        months.forEach(month => {
            const button = $(`<button type="button" class="day-button month-button" data-date="${month.value}">${month.label}</button>`);
            
            // Apply pill shape styling for month buttons
            button.css({
                'width': 'auto',
                'min-width': '120px',
                'padding': '8px 16px',
                'border-radius': '25px',
                'font-size': '12px',
                'white-space': 'nowrap'
            });
            
            // Add click handler for month filtering
            button.on('click', function() {
                filterBySpecificMonth($(this));
            });
            wrapper.append(button);
        });
    }

    function generateYearButtons(startDate, endDate) {
        const container = $('.day-shortcut-container').eq(2);
        const wrapper = container.find('.day-buttons-wrapper');
        
        // Clear existing buttons except "All"
        wrapper.find('.day-button:not(.day-button-all)').remove();
        
        const startYear = parseInt(startDate);
        const endYear = parseInt(endDate);
        
        for (let year = startYear; year <= endYear; year++) {
            const button = $(`<button type="button" class="day-button year-button" data-date="${year}">${year}</button>`);
            
            // Apply pill shape styling for year buttons
            button.css({
                'width': 'auto',
                'min-width': '70px',
                'padding': '8px 16px',
                'border-radius': '25px',
                'font-size': '12px'
            });
            
            // Add click handler for year filtering
            button.on('click', function() {
                filterBySpecificYear($(this));
            });
            wrapper.append(button);
        }
    }

    // New function to handle date range filtering
    function filterBySpecificDateRange(button, specificStartDate, specificEndDate) {
        const container = button.closest('.day-shortcut-container');
        
        // Remove active class from all buttons in this container
        container.find('.day-button').removeClass('day-button-active');
        // Add active class to clicked button
        button.addClass('day-button-active');
        
        // Generate report for specific date range
        generateFilteredReport(specificStartDate, specificEndDate);
    }

    // Update the existing filterBySpecificDay function to use the new approach
    function filterBySpecificDay(button, originalStartDate, originalEndDate) {
        const container = button.closest('.day-shortcut-container');
        const buttonDate = button.data('date');
        
        // Remove active class from all buttons in this container
        container.find('.day-button').removeClass('day-button-active');
        // Add active class to clicked button
        button.addClass('day-button-active');
        
        // If the button has a full date, use it directly
        if (buttonDate && buttonDate.includes('-')) {
            generateFilteredReport(buttonDate, buttonDate);
        } else {
            // Legacy support for day number only
            const day = button.data('day') || buttonDate;
            const startDateParts = originalStartDate.split('-');
            const year = startDateParts[0];
            const month = startDateParts[1];
            const specificDate = `${year}-${month}-${String(day).padStart(2, '0')}`;
            generateFilteredReport(specificDate, specificDate);
        }
    }

    // New function to filter by specific month
    function filterBySpecificMonth(button) {
        const container = button.closest('.day-shortcut-container');
        const monthValue = button.data('date');
        
        // Remove active class from all buttons in this container
        container.find('.day-button').removeClass('day-button-active');
        // Add active class to clicked button
        button.addClass('day-button-active');
        
        // Generate report for specific month
        generateFilteredReport(monthValue, monthValue);
    }

    // New function to filter by specific year
    function filterBySpecificYear(button) {
        const container = button.closest('.day-shortcut-container');
        const year = button.data('date');
        
        // Remove active class from all buttons in this container
        container.find('.day-button').removeClass('day-button-active');
        // Add active class to clicked button
        button.addClass('day-button-active');
        
        // Generate report for specific year
        generateFilteredReport(year, year);
    }

    // Update the formatDate helper function if it doesn't exist
    function formatDate(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }

    // Update the highlightMatchingDayButtons function to work with the new structure
    function highlightMatchingDayButtons(startDate, endDate) {
        const container = $('.day-shortcut-container').eq(0);
        
        if (startDate === endDate) {
            // Single day selected - find and highlight the specific date button
            const dayButton = container.find(`[data-date="${startDate}"]`);
            if (dayButton.length) {
                dayButton.addClass('day-button-active');
            } else {
                // Fallback to "All" if specific date not found
                container.find('.day-button-all').addClass('day-button-active');
            }
        } else {
            // Multiple days or range - highlight "All"
            container.find('.day-button-all').addClass('day-button-active');
        }
    }
    
    function highlightMatchingMonthButtons(startDate, endDate) {
        const container = $('.day-shortcut-container').eq(1);
        
        if (startDate === endDate) {
            // Single month selected
            const monthButton = container.find(`[data-date="${startDate}"]`);
            if (monthButton.length) {
                monthButton.addClass('day-button-active');
            } else {
                // Fallback to "All" if specific month not found
                container.find('.day-button-all').addClass('day-button-active');
            }
        } else {
            // Multiple months or range - highlight "All"
            container.find('.day-button-all').addClass('day-button-active');
        }
    }
    
    function highlightMatchingYearButtons(startDate, endDate) {
        const container = $('.day-shortcut-container').eq(2);
        
        if (startDate === endDate) {
            // Single year selected
            const yearButton = container.find(`[data-date="${startDate}"]`);
            if (yearButton.length) {
                yearButton.addClass('day-button-active');
            } else {
                // Fallback to "All" if specific year not found
                container.find('.day-button-all').addClass('day-button-active');
            }
        } else {
            // Multiple years or range - highlight "All"
            container.find('.day-button-all').addClass('day-button-active');
        }
    }
    
    function toggleGenerateButton() {
        const filterType = $('select[name="filterType"]').val();
        const partner = $('#partnerlistDropdown').val();
        const startDate = $('input[name="startDate"]').val();
        const endDate = $('input[name="endDate"]').val();
        
        // For daily filter, only check startDate since endDate is hidden
        let datesValid = false;
        if (filterType === 'daily') {
            datesValid = startDate !== '';
        } else {
            datesValid = startDate !== '' && endDate !== '';
        }
        
        if (filterType && partner && datesValid) {
            $('#generateReport').prop('disabled', false)
            .removeClass('btn-secondary')
            .addClass('btn-danger');
        } else {
            $('#generateReport').prop('disabled', true)
            .removeClass('btn-danger')
            .addClass('btn-secondary');
        }
    }
    
    function updateDateInputsFromFilter(button) {
        const buttonData = button.data('date');
        const currentDate = new Date();
        const currentYear = currentDate.getFullYear();
        const currentMonth = String(currentDate.getMonth() + 1).padStart(2, '0');
        const container = button.closest('.day-shortcut-container');
        const containerIndex = $('.day-shortcut-container').index(container);
        const filterType = $('select[name="filterType"]').val();
        
        if (button.hasClass('day-button-all')) {
            // Handle "All" selection - keep the original date range
            // Don't change the inputs when "All" is selected
            return;
        } else {
            // Handle specific selection
            if (containerIndex === 0 && buttonData) {
                // Day selection - set to specific day, but use the month/year from the original range
                const originalStartDate = $('input[name="startDate"]').val();
                const dateParts = originalStartDate.split('-');
                const year = dateParts[0];
                const month = dateParts[1];
                const day = String(buttonData).padStart(2, '0');
                
                $('input[name="startDate"]').val(`${year}-${month}-${day}`);
                $('input[name="endDate"]').val(`${year}-${month}-${day}`);
            } else if (containerIndex === 1 && buttonData) {
                // Month selection
                $('input[name="startDate"]').val(buttonData);
                $('input[name="endDate"]').val(buttonData);
            } else if (containerIndex === 2 && buttonData) {
                // Year selection
                $('input[name="startDate"]').val(buttonData);
                $('input[name="endDate"]').val(buttonData);
            }
        }
        
        // Update generate button state
        toggleGenerateButton();
    }
    
    function populateReportTable(data) {
        const tbody = $('#transactionReportTable tbody');
        tbody.empty();
        
        let totals = {
            summaryVol: 0,
            summaryPrincipal: 0,
            summaryCharge: 0,
            adjustmentVol: 0,
            adjustmentPrincipal: 0,
            adjustmentCharge: 0,
            netVol: 0,
            netPrincipal: 0,
            netCharge: 0
        };
        
        // Ensure data is an array
        if (!Array.isArray(data)) {
            console.error('Data is not an array:', data);
            data = [];
        }
        
        if (data.length === 0) {
            tbody.append('<tr><td colspan="13" class="text-center">No data found for the selected criteria</td></tr>');
        } else {
            // Limit to first 15 rows for display, but calculate totals from all data
            const displayData = data.slice(0, 15);

            data.forEach((row, index) => {
                const tr = $(`
                <tr>
                    <td>${index + 1}</td>
                    <td>${row.partner_name || ''}</td>
                    <td></td>
                    <td></td>
                    <td class="text-end">${parseInt(row.summary_vol || 0).toLocaleString()}</td>
                    <td class="text-end">${parseFloat(row.summary_principal || 0).toLocaleString('en-US', {minimumFractionDigits: 2})}</td>
                    <td class="text-end">${parseFloat(row.summary_charges || 0).toLocaleString('en-US', {minimumFractionDigits: 2})}</td>
                    <td class="text-end">${parseInt(row.adjustment_vol || 0).toLocaleString()}</td>
                    <td class="text-end">${parseFloat(row.adjustment_principal || 0).toLocaleString('en-US', {minimumFractionDigits: 2})}</td>
                    <td class="text-end">${parseFloat(row.adjustment_charges || 0).toLocaleString('en-US', {minimumFractionDigits: 2})}</td>
                    <td class="text-end">${parseInt(row.net_vol || 0).toLocaleString()}</td>
                    <td class="text-end">${parseFloat(row.net_principal || 0).toLocaleString('en-US', {minimumFractionDigits: 2})}</td>
                    <td class="text-end">${parseFloat(row.net_charges || 0).toLocaleString('en-US', {minimumFractionDigits: 2})}</td>
                </tr>
            `);
                tbody.append(tr);
                
                // Add to totals
                totals.summaryVol += parseInt(row.summary_vol || 0);
                totals.summaryPrincipal += parseFloat(row.summary_principal || 0);
                totals.summaryCharge += parseFloat(row.summary_charges || 0);
                totals.adjustmentVol += parseInt(row.adjustment_vol || 0);
                totals.adjustmentPrincipal += parseFloat(row.adjustment_principal || 0);
                totals.adjustmentCharge += parseFloat(row.adjustment_charges || 0);
                totals.netVol += parseInt(row.net_vol || 0);
                totals.netPrincipal += parseFloat(row.net_principal || 0);
                totals.netCharge += parseFloat(row.net_charges || 0);
            });
        }
        
        // Update totals
        $('#totalsummaryvolume').text(totals.summaryVol.toLocaleString());
        $('#totalsummaryprincipal').text(totals.summaryPrincipal.toLocaleString('en-US', {minimumFractionDigits: 2}));
        $('#totalsummarycharge').text(totals.summaryCharge.toLocaleString('en-US', {minimumFractionDigits: 2}));
        $('#totaladjustmentvolume').text(totals.adjustmentVol.toLocaleString());
        $('#totaladjustmentprincipal').text(totals.adjustmentPrincipal.toLocaleString('en-US', {minimumFractionDigits: 2}));
        $('#totaladjustmentcharge').text(totals.adjustmentCharge.toLocaleString('en-US', {minimumFractionDigits: 2}));
        $('#totalnetvolume').text(totals.netVol.toLocaleString());
        $('#totalnetprincipal').text(totals.netPrincipal.toLocaleString('en-US', {minimumFractionDigits: 2}));
        $('#totalnetcharge').text(totals.netCharge.toLocaleString('en-US', {minimumFractionDigits: 2}));
        
        // Check if all totals are zero and toggle export button visibility
        toggleExportButton(totals);
    }
    
    function toggleExportButton(totals) {
        const hasData = totals.summaryVol > 0 || 
            totals.summaryPrincipal > 0 || 
            totals.summaryCharge > 0 || 
            totals.adjustmentVol > 0 || 
            totals.adjustmentPrincipal > 0 || 
            totals.adjustmentCharge > 0 || 
            totals.netVol > 0 || 
            totals.netPrincipal > 0 || 
            totals.netCharge > 0;
        
        if (hasData) {
            $('#exportButton').show();
        } else {
            $('#exportButton').hide();
        }
    }
    
    // Load partners on page load
    loadPartners();
    
    function loadPartners() {
        $.ajax({
            url: '',
            type: 'POST',
            data: { action: 'get_partner_list' },
            success: function(response) {
                try {
                    const result = JSON.parse(response);
                    if (result.status === 'success') {
                        const select = $('#partnerlistDropdown');
                        result.data.forEach(partner => {
                            select.append(new Option(partner.partner_name, partner.partner_name));
                        });
                    }
                } catch (e) {
                    console.error('Error loading partners:', e);
                }
            }
        });
    }
    
    // Partner selection change handler
    $('#partnerlistDropdown').on('change', function() {
        // Hide filter containers when partner changes
        $('.day-shortcut-container').hide();
        resetFilterContainers();
        
        // Hide export button when partner changes
        $('#exportButton').hide();
        
        // Clear the report table
        clearReportTable();
        
        toggleGenerateButton();
    });

    // Add this export button click handler in your existing $(document).ready(function() {
    $('#exportButton').on('click', function() {
        Swal.fire({
            title: 'Export Report',
            text: 'Choose your preferred export format:',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: '<i class="fas fa-file-pdf"></i> PDF Format',
            cancelButtonText: '<i class="fas fa-file-excel"></i> XLS Format',
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#28a745',
            customClass: {
                confirmButton: 'btn-export-pdf',
                cancelButton: 'btn-export-xls'
            },
            buttonsStyling: false,
            allowOutsideClick: true,
            allowEscapeKey: true,
            reverseButtons: true,
            html: `
                <div class="export-options">
                    <p>Select the format you would like to export the Volume Report:</p>
                    <div class="export-buttons-container">
                        <button type="button" class="btn btn-danger export-btn" id="exportPDF">
                            <i class="fas fa-file-pdf"></i> PDF Format
                        </button>
                        <button type="button" class="btn btn-success export-btn" id="exportXLS">
                            <i class="fas fa-file-excel"></i> XLS Format
                        </button>
                    </div>
                </div>
            `,
            showConfirmButton: false,
            showCancelButton: false,
            didOpen: () => {
                // Handle PDF export
                // document.getElementById('exportPDF').addEventListener('click', function() {
                //     Swal.fire({
                //         title: 'Exporting to PDF...',
                //         text: 'Please wait while we generate your PDF report.',
                //         icon: 'info',
                //         allowOutsideClick: false,
                //         allowEscapeKey: false,
                //         showConfirmButton: false,
                //         didOpen: () => {
                //             Swal.showLoading();
                //             // Add your PDF export logic here
                //             setTimeout(() => {
                //                 exportToPDF();
                //             }, 1000);
                //         }
                //     });
                // });

                document.getElementById('exportPDF').addEventListener('click', function() {
                    // Close chooser and start PDF export which will open in a new tab
                    Swal.close();
                    exportToPDF();
                });

                // Handle XLS export
                document.getElementById('exportXLS').addEventListener('click', function() {
                    // Close chooser and start Excel export (downloads file)
                    Swal.close();
                    exportToXLS();
                });
            }
        });
    });

    // Export functions
    function exportToPDF() {
        // Use effective dates (respect active Filter-by-Day/Month/Year)
        const params = getEffectiveExportParams();

        // Create a form to request PDF generation from server (opens in new tab)
        const form = $('<form>', {
            'method': 'POST',
            'action': '../../../models/generate/pdf/generate-pdf-volume-report.php',
            'target': '_blank'
        });

        form.append($('<input>', {'type': 'hidden', 'name': 'action', 'value': 'export_pdf'}));
        form.append($('<input>', {'type': 'hidden', 'name': 'partner', 'value': params.partner}));
        form.append($('<input>', {'type': 'hidden', 'name': 'filterType', 'value': params.filterType}));
        form.append($('<input>', {'type': 'hidden', 'name': 'startDate', 'value': params.startDate}));
        form.append($('<input>', {'type': 'hidden', 'name': 'endDate', 'value': params.endDate}));

        $('body').append(form);
        form.submit();
        form.remove();

        // Provide a brief success toast after submission
        setTimeout(() => {
            Swal.fire({
                title: 'Export started',
                text: 'PDF generation has started. The file will open in a new tab when ready.',
                icon: 'info',
                confirmButtonText: 'OK',
                confirmButtonColor: '#dc3545'
            });
        }, 700);
    }

    function exportToXLS() {
        // Use effective dates (respect active Filter-by-Day/Month/Year)
        const params = getEffectiveExportParams();

        // Create a form to submit the export request
        const form = $('<form>', {
            'method': 'POST',
            'action': '../../../models/generate/excel/generate-excel-volume-report.php',
            'target': '_blank'
        });

        // Add hidden fields
        form.append($('<input>', {'type': 'hidden', 'name': 'action', 'value': 'export_excel'}));
        form.append($('<input>', {'type': 'hidden', 'name': 'partner', 'value': params.partner}));
        form.append($('<input>', {'type': 'hidden', 'name': 'filterType', 'value': params.filterType}));
        form.append($('<input>', {'type': 'hidden', 'name': 'startDate', 'value': params.startDate}));
        form.append($('<input>', {'type': 'hidden', 'name': 'endDate', 'value': params.endDate}));

        // Append to body and submit
        $('body').append(form);
        form.submit();
        form.remove();

        // Inform user
        setTimeout(() => {
            Swal.fire({
                title: 'Export started',
                text: 'Excel generation has started. The file will download shortly.',
                icon: 'info',
                confirmButtonText: 'OK',
                confirmButtonColor: '#dc3545'
            });
        }, 700);
    }

    // Helper to determine the effective dates to send for export
    function getEffectiveExportParams() {
        const partner = $('#partnerlistDropdown').val();
        const filterType = $('select[name="filterType"]').val();

        // Default to current input values
        let startDate = $('input[name="startDate"]').val();
        let endDate = $('input[name="endDate"]').val();

        // Find any active filter button (day/month/year) that is not the "All" button
        const activeBtn = $('.day-shortcut-container').find('.day-button-active').not('.day-button-all').first();

        if (activeBtn && activeBtn.length) {
            const container = activeBtn.closest('.day-shortcut-container');
            const idx = $('.day-shortcut-container').index(container);
            const dataDate = activeBtn.data('date');

            if (idx === 0) {
                // Day buttons: data-date is YYYY-MM-DD
                if (dataDate && dataDate.toString().includes('-')) {
                    startDate = dataDate;
                    endDate = dataDate;
                }
            } else if (idx === 1) {
                // Month buttons: data-date is YYYY-MM
                if (dataDate) {
                    startDate = dataDate;
                    endDate = dataDate;
                }
            } else if (idx === 2) {
                // Year buttons: data-date is YYYY
                if (dataDate) {
                    startDate = dataDate;
                    endDate = dataDate;
                }
            }
        }

        return { partner: partner, filterType: filterType, startDate: startDate, endDate: endDate };
    }

    // Update the existing "All" button click handlers
    $(document).on('click', '.day-button-all', function() {
        const container = $(this).closest('.day-shortcut-container');
        
        // Remove active class from all buttons in this container
        container.find('.day-button').removeClass('day-button-active');
        // Add active class to "All" button
        $(this).addClass('day-button-active');
        
        // Generate report with original date range
        const originalStartDate = $('input[name="startDate"]').val();
        const originalEndDate = $('input[name="endDate"]').val();
        
        if (originalStartDate && originalEndDate) {
            generateFilteredReport(originalStartDate, originalEndDate);
        }
    });

    // Add new function to reset filter containers to default state
    function resetFilterContainers() {
        // Reset all filter containers to default state with only "All" button
        $('.day-shortcut-container').each(function() {
            const wrapper = $(this).find('.day-buttons-wrapper');
            
            // Remove all buttons except the "All" button
            wrapper.find('.day-button:not(.day-button-all)').remove();
            
            // Remove any month separators
            wrapper.find('.month-separator').remove();
            wrapper.find('div[style*="height: 5px"]').remove(); // Remove gap divs
            
            // Reset "All" button state
            const allButton = wrapper.find('.day-button-all');
            allButton.removeClass('day-button-active').addClass('day-button-active');
        });
    }

    // Add new function to clear the report table
    function clearReportTable() {
        const tbody = $('#transactionReportTable tbody');
        tbody.empty();
        
        // Add empty row
        tbody.append(`
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
                <td></td>
                <td></td>
            </tr>
        `);
        
        // Reset all totals to 0
        $('#totalsummaryvolume').text('0');
        $('#totalsummaryprincipal').text('0.00');
        $('#totalsummarycharge').text('0.00');
        $('#totaladjustmentvolume').text('0');
        $('#totaladjustmentprincipal').text('0.00');
        $('#totaladjustmentcharge').text('0.00');
        $('#totalnetvolume').text('0');
        $('#totalnetprincipal').text('0.00');
        $('#totalnetcharge').text('0.00');
    }

    // Add new function to handle when date inputs change
    $('input[name="startDate"], input[name="endDate"]').on('change', function() {
        // Hide filter containers when date inputs change
        $('.day-shortcut-container').hide();
        resetFilterContainers();
        
        // Hide export button when dates change
        $('#exportButton').hide();
        
        // Clear the report table
        clearReportTable();
        
        toggleGenerateButton();
    });

    // Add the missing generateFilteredReport function after the existing functions

    function generateFilteredReport(startDate, endDate) {
        const partner = $('#partnerlistDropdown').val();
        const filterType = $('select[name="filterType"]').val();
        
        // Show loading
        $('#loading-overlay').show();
        
        // Make AJAX request to generate filtered report
        $.ajax({
            url: '',
            type: 'POST',
            data: {
                action: 'generate_report',
                partner: partner,
                filterType: filterType,
                startDate: startDate,
                endDate: endDate
            },
            success: function(response) {
                console.log('Response received:', response);
                try {
                    const result = JSON.parse(response);
                    
                    // Check if the response has the new structure with status
                    if (result.status) {
                        if (result.status === 'success') {
                            populateReportTable(result.data);
                        } else {
                            console.error('Server error:', result.message);
                            Swal.fire({
                                icon: 'error',
                                title: 'Server Error',
                                text: result.message || 'Error processing report data.'
                            });
                        }
                    } else {
                        // Handle legacy response format (array of data)
                        populateReportTable(result);
                    }
                } catch (e) {
                    console.error('Error parsing response:', e);
                    console.log('Raw response:', response);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Error processing report data. Please check console for details.'
                    });
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', {xhr: xhr, status: status, error: error});
                Swal.fire({
                    icon: 'error',
                    title: 'Connection Error',
                    text: 'Failed to generate filtered report. Please try again.'
                });
            },
            complete: function() {
                $('#loading-overlay').hide();
            }
        });
    }
}); // Final closing brace for $(document).ready
</script>
</html>