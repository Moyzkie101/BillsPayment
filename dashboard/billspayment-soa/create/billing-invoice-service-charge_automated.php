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
    }
}

// dropdown queries for partner list
$partnersQuery = "SELECT partner_name FROM masterdata.partner_masterfile ORDER BY partner_name";
$partnersResult = $conn->query($partnersQuery);

// Handle AJAX request for partner details
if (isset($_POST['action']) && $_POST['action'] === 'get_partner_details') {
    $partner_name = $_POST['partner_name'];
    
    $stmt = $conn->prepare("SELECT * FROM masterdata.partner_masterfile WHERE partner_name = ?");
    $stmt->bind_param("s", $partner_name);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $partner = $result->fetch_assoc();
        $partner['user_name'] = $_SESSION['user_name'] ?? $_SESSION['admin_name'] ?? 'Unknown User';
        echo json_encode([
            'success' => true,
            'data' => $partner
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Partner not found'
        ]);
    }
    exit();
}

// Handle AJAX request for transaction data
if (isset($_POST['action']) && $_POST['action'] === 'get_transaction_data') {
    $partner_name = $_POST['partner_name'];
    $from_date = $_POST['from_date'];
    $to_date = $_POST['to_date'];
    
    // First get partner details to get partner_id and partner_id_kpx
    $partner_stmt = $conn->prepare("SELECT partner_id, partner_id_kpx FROM masterdata.partner_masterfile WHERE partner_name = ?");
    $partner_stmt->bind_param("s", $partner_name);
    $partner_stmt->execute();
    $partner_result = $partner_stmt->get_result();
    
    if ($partner_result->num_rows > 0) {
        $partner_data = $partner_result->fetch_assoc();
        $partner_id = $partner_data['partner_id'];
        $partner_id_kpx = $partner_data['partner_id_kpx'];
        
        // Build the transaction query
        $transaction_query = "
            SELECT
                SUM(amount_paid) AS total_amount_paid,
                SUM(charge_to_partner + charge_to_customer) AS total_charge,
                COUNT(*) AS transaction_count
            FROM mldb.billspayment_transaction
            WHERE
                (
                    DATE(`datetime`) BETWEEN ? AND ?
                    OR DATE(cancellation_date) BETWEEN ? AND ?
                )
                AND (
                    partner_id = ?
                    OR partner_id_kpx = ?
                )
        ";
        
        $stmt = $conn->prepare($transaction_query);
        $stmt->bind_param("ssssss", $from_date, $to_date, $from_date, $to_date, $partner_id, $partner_id_kpx);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $transaction_data = $result->fetch_assoc();
            $transaction_data['user_name'] = $_SESSION['user_name'] ?? $_SESSION['admin_name'] ?? 'Unknown User';
            echo json_encode([
                'success' => true,
                'data' => [
                    'total_amount_paid' => $transaction_data['total_amount_paid'] ?? 0,
                    'total_charge' => $transaction_data['total_charge'] ?? 0,
                    'transaction_count' => $transaction_data['transaction_count'] ?? 0
                ]
            ]);
        } else {
            echo json_encode([
                'success' => true,
                'data' => [
                    'total_amount_paid' => 0,
                    'total_charge' => 0,
                    'transaction_count' => 0
                ]
            ]);
        }
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Partner not found'
        ]);
    }
    exit();
}

if (isset($_POST['action']) && $_POST['action'] === 'get_matched_po_data') {
    $po_number = $_POST['po_number'];
    $current_year = date('Y');
    
    // Check if PO number exists in database
    $DataBillQuery = "SELECT * FROM mldb.soa_transaction WHERE po_number = ?";
    $stmt = $conn->prepare($DataBillQuery);
    $stmt->bind_param("s", $po_number);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Check if PO number was used in previous years
    $yearCheckQuery = "SELECT DISTINCT YEAR(date) as po_year FROM mldb.soa_transaction WHERE po_number = ? ORDER BY po_year DESC LIMIT 1";
    $yearStmt = $conn->prepare($yearCheckQuery);
    $yearStmt->bind_param("s", $po_number);
    $yearStmt->execute();
    $yearResult = $yearStmt->get_result();
    
    $response = [];
    
    if ($result->num_rows > 0) {
        // PO number exists - check if it's from current year or previous year
        if ($yearResult->num_rows > 0) {
            $yearData = $yearResult->fetch_assoc();
            $po_year = $yearData['po_year'];
            
            if ($po_year < $current_year) {
                // PO number is from previous year - NOT allowed for current year
                $bill_data = $result->fetch_assoc();
                $bill_data['user_name'] = $_SESSION['user_name'] ?? $_SESSION['admin_name'] ?? 'Unknown User';
                $response = [
                    'success' => true,
                    'data' => $bill_data,
                    'duplicate' => true,
                    'year_reuse' => false,
                    'previous_year_not_allowed' => true,
                    'previous_year' => $po_year
                ];
            } else {
                // PO number is from current year - allow reuse for same year
                $response = [
                    'success' => true,
                    'message' => 'PO Number from current year - allowed for reuse within same year',
                    'duplicate' => false,
                    'year_reuse' => true,
                    'current_year_reuse' => true
                ];
            }
        }
    } else {
        // PO number doesn't exist - new PO number allowed
        $response = [
            'success' => false,
            'message' => 'New PO Number - available for use',
            'duplicate' => false,
            'year_reuse' => false
        ];
    }
    
    echo json_encode($response);
    exit();
}

if (isset($_POST['action']) && $_POST['action'] === 'save_billing_invoice') {
    // Add error reporting for debugging
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    
    try {
        $date = date('Y-m-d', strtotime($_POST['date'])) ?? null;
        $partner_name = $_POST['partner_name'] ?? null; // use for update after save successfully
        $reference_number = $_POST['control_number'] ?? null;
        $partner_accountname = $_POST['partner_accountname'] ?? null;
        $partner_tin = $_POST['partner_tin'] ?? null;
        $address = $_POST['address'] ?? null;
        $business_style = $_POST['business_style'] ?? null;
        $service_charge = $_POST['service_charge'] ?? null;
        $from_date = date('Y-m-d', strtotime($_POST['from_date'])) ?? null;
        $to_date = date('Y-m-d', strtotime($_POST['to_date'])) ?? null;

        $po_number_raw = $_POST['po_number'];
        if ($po_number_raw === '-') {
            $po_number = null;
        } else {
            $po_number = $po_number_raw;
        }

        // Fix data type conversions - properly clean and convert numeric values
        $number_of_transactions = intval(str_replace(',', '', $_POST['number_of_transactions'] ?? '0'));
        $amount = floatval(str_replace(',', '', $_POST['total_charge'] ?? '0'));
        
        $add_amount_raw = floatval(str_replace(',', '', $_POST['calculated_add_amount'] ?? '0'));
        $amount_add_raw = floatval(str_replace(',', '', $_POST['add_amount_field'] ?? '0'));
        $number_of_days_raw = intval(str_replace(',', '', $_POST['number_of_days'] ?? '0'));

        if($add_amount_raw > 0 && $amount_add_raw > 0 && $number_of_days_raw > 0){
            $add_amount = number_format($add_amount_raw, 2, '.', '');
            $amount_add = number_format($amount_add_raw, 2, '.', '');
            $number_of_days = $number_of_days_raw;
        }else{
            $add_amount = null;
            $amount_add = null;
            $number_of_days = null;
        }
        $formula = $_POST['formula'] ?? null;
        $formula_withheld = $_POST['formula_withheld'] ?? null;

        $formula_inc_exc_raw = $_POST['formula_inc_exc'];
        if($formula_inc_exc_raw === ''|| $formula_inc_exc_raw === null || $formula_inc_exc_raw === '-'){
            $formula_inc_exc = null;
        }else{
            $formula_inc_exc = $formula_inc_exc_raw;
        }

        // Fix VAT amount parsing - remove commas and convert to float
        // $vat_amount = $_POST['vat_amount'] ?? '0';
        // $net_of_vat = $_POST['net_of_vat'] ?? '0';
        // $withholding_tax = $_POST['withholding_tax'] ?? '0';
        // $total_amount_due = $_POST['total_amount_due'] ?? '0';
        // $net_amount_due = $_POST['net_amount_due'] ?? '0';

        $vat_amount = number_format(floatval(str_replace(',', '', $_POST['vat_amount'] ?? '0')), 2, '.', '');
        $net_of_vat = number_format(floatval(str_replace(',', '', $_POST['net_of_vat'] ?? '0')), 2, '.', '');
        $withholding_tax = number_format(floatval(str_replace(',', '', $_POST['withholding_tax'] ?? '0')), 2, '.', '');
        $total_amount_due = number_format(floatval(str_replace(',', '', $_POST['total_amount_due'] ?? '0')), 2, '.', '');
        $net_amount_due = number_format(floatval(str_replace(',', '', $_POST['net_amount_due'] ?? '0')), 2, '.', '');

        $prepared_by = $_POST['prepared_by'] ?? null;
        $prepared_signature = 'electronically signed';
        $prepared_date_signature = $date; // Use the date at above
        $status = 'Prepared';
        
        // Check if connection exists
        if (!$conn) {
            throw new Exception("Database connection not available");
        }
        
        // Start transaction
        $conn->autocommit(false);
        
        // VALIDATION STEP: Check if partner exists BEFORE any database operations
        $partnernameQuery = "SELECT partner_id, partner_id_kpx FROM masterdata.partner_masterfile WHERE partner_name = ?";
        $partner_stmt = $conn->prepare($partnernameQuery);
        if (!$partner_stmt) {
            throw new Exception("Failed to prepare partner validation query: " . $conn->error);
        }
        
        $partner_stmt->bind_param("s", $partner_name);
        $partner_stmt->execute();
        $partner_result = $partner_stmt->get_result();
        
        if ($partner_result->num_rows === 0) {
            throw new Exception("Partner not found for update operations: " . $partner_name);
        }
        
        $partner = $partner_result->fetch_assoc();
        $partner_id = $partner['partner_id'];
        $partner_id_kpx = $partner['partner_id_kpx'];
        $partner_stmt->close();
        
        // STEP 1: Insert into soa_transaction (only after partner validation)
        $stmt = $conn->prepare("INSERT INTO mldb.soa_transaction (
            date, -- s
            reference_number, -- s
            partner_name, -- s
            partner_tin, -- s
            address, -- s
            business_style, -- s
            service_charge, -- s
            from_date, -- s
            to_date, -- s
            po_number, -- s
            number_of_transactions, -- i 
            amount, -- d
            add_amount, -- s
            amount_add, -- s
            numberOf_days, -- s
            formula, -- s
            formula_withheld, -- s
            formulaInc_Exc, -- s
            vat_amount, -- s
            net_of_vat, -- s
            withholding_tax, -- s
            totalAmountDue, -- s
            net_amount_due, -- s
            prepared_by, -- s
            prepared_signature, -- s
            preparedDate_signature, -- s
            status -- s
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        if (!$stmt) {
            throw new Exception("Prepare statement failed: " . $conn->error);
        }
        
        // Fix bind_param - use correct data types: s=string, i=integer, d=decimal/float
        $stmt->bind_param("ssssssssssidsssssssssssssss", 
            $date, $reference_number, $partner_accountname, $partner_tin, $address, $business_style,
            $service_charge, $from_date, $to_date, $po_number, $number_of_transactions,
            $amount, $add_amount, $amount_add, $number_of_days, $formula, $formula_withheld,
            $formula_inc_exc, $vat_amount, $net_of_vat, $withholding_tax, $total_amount_due,
            $net_amount_due, $prepared_by, $prepared_signature, $prepared_date_signature, $status
        );
        
        if (!$stmt->execute()) {
            throw new Exception("Step 1 failed - Could not insert into soa_transaction: " . $stmt->error);
        }
        $stmt->close();
        
        // STEP 2: Update billing_invoice in billspayment_transaction
        $update_query = "UPDATE mldb.billspayment_transaction 
                SET billing_invoice = ? 
                WHERE (DATE(datetime) BETWEEN ? AND ? OR DATE(cancellation_date) BETWEEN ? AND ?) 
                AND (partner_id = ? OR partner_id_kpx = ?)";
        
        $update_stmt = $conn->prepare($update_query);
        if (!$update_stmt) {
            throw new Exception("Failed to prepare update query: " . $conn->error);
        }
        
        $update_stmt->bind_param("sssssss", 
            $reference_number, $from_date, $to_date, $from_date, $to_date, 
            $partner_id, $partner_id_kpx
        );
        
        if (!$update_stmt->execute()) {
            throw new Exception("Step 2 failed - Could not update billing invoice reference: " . $update_stmt->error);
        }
        
        $affected_rows = $update_stmt->affected_rows;
        $update_stmt->close();
        
        // STEP 3: Update series number in partner_masterfile
        $partner_seriesQuery = "UPDATE masterdata.partner_masterfile 
                               SET series_number = series_number + 1 
                               WHERE partner_id = ? OR partner_id_kpx = ?";
        $partner_series_stmt = $conn->prepare($partner_seriesQuery);
        
        if (!$partner_series_stmt) {
            throw new Exception("Failed to prepare series update query: " . $conn->error);
        }
        
        $partner_series_stmt->bind_param("ss", $partner_id, $partner_id_kpx);
        
        if (!$partner_series_stmt->execute()) {
            throw new Exception("Step 3 failed - Could not update series number: " . $partner_series_stmt->error);
        }
        
        $series_affected_rows = $partner_series_stmt->affected_rows;
        $partner_series_stmt->close();
        
        // All steps completed successfully - commit transaction
        $conn->commit();
        $conn->autocommit(true);
        
        // Log success information
        error_log("Billing invoice process completed successfully:");
        error_log("- SOA transaction inserted with reference: " . $reference_number);
        error_log("- Billspayment transactions updated: " . $affected_rows . " rows");
        error_log("- Partner series number updated: " . $series_affected_rows . " rows");
        
        echo json_encode([
            'success' => true, 
            'message' => 'Billing invoice saved successfully',
            'details' => [
                'reference_number' => $reference_number,
                'transactions_updated' => $affected_rows,
                'partner_updated' => $series_affected_rows > 0
            ]
        ]);
        
    } catch (Exception $e) {
        // Rollback transaction on any error
        if ($conn) {
            $conn->rollback();
            $conn->autocommit(true);
        }
        
        error_log("Database error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
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
    <title>Create Billing | <?php if($_SESSION['user_type'] === 'admin' || $_SESSION['user_type'] === 'user') echo ucfirst($_SESSION['user_type']); else echo "Guest";?></title>
    <!-- custom CSS file link  -->
    <link rel="stylesheet" href="../../../assets/css/templates/style.css?v=<?php echo time(); ?>">
    <script src="https://kit.fontawesome.com/30b908cc5a.js" crossorigin="anonymous"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="../../../assets/js/sweetalert2.all.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>

    <link rel="icon" href="../../../images/MLW logo.png" type="image/png">
    <style>
    .border-danger {
        border-color: #dc3545 !important;
        box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25) !important;
    }

    .border-success {
        border-color: #198754 !important;
        box-shadow: 0 0 0 0.2rem rgba(25, 135, 84, 0.25) !important;
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
        <center><h3>Billing Invoice</h3></center>
        <div class="container">
            <div class="row">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">
                            <form action="" method="post">
                                <table class="table table-borderless">
                                    <tr class="text-start">
                                        <td>
                                            <label for="partner" class="form-label fw-medium">Partner Name</label>
                                        </td>
                                        <td class="fw-medium">:</td>
                                        <td colspan="4">
                                            <select id="partner" class="form-select select2" aria-label="Select Partner" name="partner" data-placeholder="Search or select a Partner..." required>
                                                    <option value="">Select Partner</option>
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
                                        </td>
                                    </tr>
                                    <tr class="text-start">
                                        <td>
                                            <label for="control_number" class="form-label fw-medium">Control Number</label>
                                        </td>
                                        <td class="fw-medium">:</td>
                                        <td colspan="4">
                                            <input type="text" class="form-control" id="control_number" name="control_number" value="-" disabled>
                                        </td>
                                    </tr>
                                    <tr class="text-start">
                                        <td>
                                            <label for="date" class="form-label fw-medium">Date</label>
                                        </td>
                                        <td class="fw-medium">:</td>
                                        <td colspan="4">
                                            <input type="text" class="form-control" id="date" name="date" value="<?php echo date('F d, Y'); ?>" disabled>
                                        </td>
                                    </tr>
                                    <tr class="text-start">
                                        <td>
                                            <label for="partner_accountname" class="form-label fw-medium">Partner Account Name</label>
                                        </td>
                                        <td class="fw-medium">:</td>
                                        <td colspan="4">
                                            <input type="text" class="form-control" id="partner_accountname" name="partner_accountname" value="-" disabled>
                                        </td>
                                    </tr>
                                    <tr class="text-start">
                                        <td>
                                            <label for="partner_tin" class="form-label fw-medium">Partner TIN</label>
                                        </td>
                                        <td class="fw-medium">:</td>
                                        <td colspan="4">
                                            <input type="text" class="form-control" id="partner_tin" name="partner_tin" value="-" disabled>
                                        </td>
                                    </tr>
                                    <tr class="text-start">
                                        <td>
                                            <label for="address" class="form-label fw-medium">Address</label>
                                        </td>
                                        <td class="fw-medium">:</td>
                                        <td colspan="4">
                                            <input type="text" class="form-control" id="address" name="address" value="-" disabled>
                                        </td>
                                    </tr>
                                    <tr class="text-start">
                                        <td>
                                            <label for="business_style" class="form-label fw-medium">Business Style</label>
                                        </td>
                                        <td class="fw-medium">:</td>
                                        <td colspan="4">
                                            <input type="text" class="form-control" id="business_style" name="business_style" value="-" disabled>
                                        </td>
                                    </tr>
                                    <tr class="text-start">
                                        <td>
                                            <label for="service_charge" class="form-label fw-medium">Service Charge</label>
                                        </td>
                                        <td class="fw-medium">:</td>
                                        <td colspan="4">
                                            <input type="text" class="form-control" id="service_charge" name="service_charge" value="-" disabled>
                                        </td>
                                    </tr>
                                    <tr class="text-start">
                                        <td>
                                            <label for="from_date" class="form-label fw-medium">From Date</label>
                                        </td>
                                        <td class="fw-medium">:</td>
                                        <td>
                                            <input type="date" class="form-control" id="from_date" name="from_date" max="<?php echo date('Y-m-d'); ?>" disabled required>
                                        </td>
                                        <td>
                                            <label for="to_date" class="form-label fw-medium">To Date</label>
                                        </td>
                                        <td class="fw-medium">:</td>
                                        <td>
                                            <input type="date" class="form-control" id="to_date" name="to_date" max="<?php echo date('Y-m-d'); ?>" disabled required>
                                        </td>
                                    </tr>
                                    <tr class="text-start">
                                        <td>
                                            <label for="po_number" class="form-label fw-medium">PO Number</label>
                                        </td>
                                        <td class="fw-medium">:</td>
                                        <td colspan="4">
                                            <input type="text" class="form-control" id="po_number" name="po_number" value="-" maxlength="10" placeholder="Enter PO Number" disabled required>
                                        </td>
                                    </tr>
                                    <tr class="text-start">
                                        <td>
                                            <label for="num_transactions" class="form-label fw-medium">Number of Transactions</label>
                                        </td>
                                        <td class="fw-medium">:</td>
                                        <td colspan="4">
                                            <input type="text" class="form-control" id="num_transactions" name="num_transactions" value="-" disabled>
                                        </td>
                                    </tr>
                                    <tr class="text-start">
                                        <td>
                                            <label for="amount" class="form-label fw-medium">Total Principal</label>
                                        </td>
                                        <td class="fw-medium">:</td>
                                        <td colspan="4">
                                            <input type="text" class="form-control" id="amount" name="amount" value="-" disabled>
                                        </td>
                                    </tr>
                                    <tr class="text-start">
                                        <td>
                                            <label for="charge" class="form-label fw-medium">Total Charge</label>
                                        </td>
                                        <td class="fw-medium">:</td>
                                        <td colspan="4">
                                            <input type="text" class="form-control" id="charge" name="charge" value="-" disabled>
                                        </td>
                                    </tr>
                                    <tr class="text-start">
                                        <td>
                                            <label for="add_amount" class="form-label fw-medium">Add Amount</label>
                                        </td>
                                        <td class="fw-medium">:</td>
                                        <td colspan="4">
                                            <input type="text" class="form-control" id="add_amount" name="add_amount" value="-" disabled>
                                        </td>
                                    </tr>
                                    <tr class="text-start">
                                        <td>
                                            <label for="num_days" class="form-label fw-medium">Number of Days</label>
                                        </td>
                                        <td class="fw-medium">:</td>
                                        <td colspan="4">
                                            <input type="text" class="form-control" id="num_days" name="num_days" value="-" placeholder="Enter Number of Days" disabled required>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td colspan="6" class="text-center">
                                            <button type="submit" id="process" name="process" class="btn btn-secondary" disabled>Process</button>
                                        </td>
                                    </tr>
                                </table>
                                <!-- Hidden input for VAT info to be stored in database -->
                                <input type="hidden" id="vatinfo-for-db" name="vatinfo_for_db" value="">
                            </form>
                        </div>
                        <div class="card-body" style="display: none;">
                            <table class="table table-borderless">
                                <tr class="text-center">
                                    <td colspan="12">
                                        <span class="badge text-bg-secondary fw-bold">SERVICE CHARGE</span>
                                    </td>
                                </tr>
                            </table>
                            <form action="" method="post">
                                <div class="row">
                                    <!-- PARTICULARS -->
                                    <div class="col-md-6">
                                        <table class="table table-borderless">
                                            <tr>
                                                <td colspan="6" class="text-center">
                                                    <span class="badge text-bg-secondary fw-bold">PARTICULARS</span>
                                                </td>
                                            </tr>
                                            <tr >
                                                <td class="fw-medium text-truncate">Number of transactions</td>
                                                <td class="fw-medium">:</td>
                                                <td id="num-transactions">-</td>
                                            </tr>
                                            <tr>
                                                <td class="fw-medium">From Date</td>
                                                <td class="fw-medium">:</td>
                                                <td id="from-date">-</td>
                                                <td class="fw-medium">To Date</td>
                                                <td class="fw-medium">:</td>
                                                <td id="to-date">-</td>
                                            </tr>
                                            <tr>
                                                <td class="fw-medium">Formula</td>
                                                <td class="fw-medium">:</td>
                                                <td class="text-danger" id="formula">-</td>
                                            </tr>
                                            <tr>
                                                <td colspan="4">
                                                    <i class="text-primary-emphasis" id="vat-info">-</i>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="fw-medium">Add Bank Charges</td>
                                                <td class="fw-medium">:</td>
                                                <td colspan="4">
                                                    <i  class="text-primary-emphasis" id="additional-info">-</i>
                                                </td>
                                            </tr>
                                        </table>
                                    </div>
                                    <!-- AMOUNT -->
                                    <div class="col-md-6">
                                        <table class="table table-borderless">
                                            <tr>
                                                <td colspan="6" class="text-center">
                                                    <span class="badge text-bg-secondary fw-bold">AMOUNT</span>
                                                </td>
                                            </tr>
                                            <tr >
                                                <td class="fw-medium text-truncate">Gross Principal Amount</td>
                                                <td class="fw-medium">:</td>
                                                <td>₱</td>
                                                <td id="gross-amount">-</td>
                                            </tr>
                                            <tr>
                                                <td colspan="6" class="text-center">
                                                    <span class="badge text-bg-secondary fw-bold">COMPUTATION FOR TOTAL CHARGE</span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="fw-medium text-truncate">VAT Amount (12%)</td>
                                                <td class="fw-medium">:</td>
                                                <td>₱</td>
                                                <td id="vat-amount">-</td>
                                            </tr>
                                            <tr>
                                                <td class="fw-medium text-truncate">Net of VAT</td>
                                                <td class="fw-medium">:</td>
                                                <td>₱</td>
                                                <td id="net-of-vat">-</td>
                                            </tr>
                                            <tr>
                                                <td class="fw-medium text-truncate">Withholding Tax (2%)</td>
                                                <td class="fw-medium">:</td>
                                                <td>₱</td>
                                                <td id="withholding-tax">-</td>
                                            </tr>
                                            <tr>
                                                <td class="fw-medium text-truncate">Add Amount</td>
                                                <td class="fw-medium">:</td>
                                                <td>₱</td>
                                                <td id="add-amount">-</td>
                                            </tr>
                                            <tr>
                                                <td></td>
                                            </tr>
                                            <tr>
                                                <td class="fw-medium text-truncate">TOTAL AMOUNT DUE</td>
                                                <td class="fw-medium">:</td>
                                                <td>₱</td>
                                                <td id="total-amount-due">-</td>
                                            </tr>
                                            <tr>
                                                <td class="fw-medium text-truncate">LESS WITHHOLDING TAX</td>
                                                <td class="fw-medium">:</td>
                                                <td>₱</td>
                                                <td id="less-withholding-tax">-</td>
                                            </tr>
                                            <tr>
                                                <td class="fw-medium text-truncate text-success">NET AMOUNT DUE</td>
                                                <td class="fw-medium text-success">:</td>
                                                <td class="text-success">₱</td>
                                                <td class="text-success" id="net-amount-due">-</td>
                                            </tr>
                                            
                                        </table>
                                    </div>
                                </div>
                                <table class="table table-borderless">
                                    <tr>
                                        <td>Prepared by:</td>
                                        <td></td>
                                        <td style="visibility:hidden;">Reviewed by:</td>
                                        <td></td>
                                        <td style="visibility:hidden;">Noted by:</td>
                                    </tr>
                                    <tr>
                                        <td></td>
                                        <td class="fw-medium text-truncate" id="prepared-by">-</td>
                                        <td></td>
                                        <td class="fw-medium text-truncate" style="visibility:hidden;">-</td>
                                        <td></td>
                                        <td class="fw-medium text-truncate" style="visibility:hidden;">-</td>
                                    </tr>
                                    <tr>
                                        <td></td>
                                        <td>Accounting Staff</td>
                                        <td></td>
                                        <td style="visibility:hidden;">Department Manager</td>
                                        <td></td>
                                        <td style="visibility:hidden;">Division Head</td>
                                    </tr>
                                    <tr>
                                        <td colspan="6" class="text-center">
                                            <button type="submit" id="save" name="save" class="btn btn-success">Save</button>
                                        </td>
                                    </tr>
                                </table>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
    </div>
</body>
<?php include '../../../templates/footer.php'; ?>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        if (typeof $ !== "undefined" && $.fn.select2) {
            $("#partner").select2({
                placeholder: "Search or select a Partner...",
                allowClear: true,
                width: "100%",
                minimumResultsForSearch: 0,
                dropdownParent: $("#partner").parent()
            });
        } else {
            console.error("jQuery or Select2 library not loaded");
        }
    });

    $(document).ready(function() {
        // Initialize select2
        if (typeof $ !== 'undefined' && $.fn.select2) {
            $('#partner').select2({
                placeholder: "Search or select a Partner...",
                allowClear: true,
                width: '100%',
                dropdownParent: $('#partner').parent(),
                minimumResultsForSearch: 0
            });
        }

        // Function to check if all required fields are filled and enable/disable Process button
        function checkRequiredFieldsAndEnableProcess() {
            const partnerName = $('#partner').val();
            const fromDate = $('#from_date').val();
            const toDate = $('#to_date').val();
            const partnerTin = $('#partner_tin').val();
            const poNumber = $('#po_number').val();
            const numDays = $('#num_days').val();
            const numTransactions = $('#num_transactions').val();
            const amount = $('#amount').val();
            const charge = $('#charge').val();
            
            // Array of allowed TINs
            const allowedTins = ['005-519-158-000'];
            
            let allFieldsFilled = false;
            
            // Check if partner is selected and dates are filled
            if (partnerName && fromDate && toDate) {
                // Check if transactions and amount are not zero or empty
                const transactionCount = parseInt(numTransactions.replace(/,/g, '') || 0);
                const amountValue = parseFloat(amount.replace(/,/g, '') || 0);
                
                // If both transaction count and amount are 0, disable the button
                if (transactionCount === 0 && amountValue === 0) {
                    allFieldsFilled = false;
                } else {
                    // If partner TIN is in allowed list, check PO Number and Number of Days
                    if (allowedTins.includes(partnerTin)) {
                        // Check if PO number has danger class (duplicate)
                        const hasDangerClass = $('#po_number').hasClass('border-danger');
                        
                        // For allowed TINs, all fields including PO Number and Number of Days must be filled
                        // AND PO Number must not be a duplicate
                        if (poNumber && poNumber !== '-' && poNumber.length === 10 && 
                            numDays && numDays !== '-' && numDays.trim() !== '' && 
                            !hasDangerClass) {  // Add this condition to check for duplicates
                            allFieldsFilled = true;
                        }
                    } else {
                        // For non-allowed TINs, only partner, dates are required
                        allFieldsFilled = true;
                    }
                }
            }
            
            // Enable/disable Process button and change class
            if (allFieldsFilled) {
                $('#process').prop('disabled', false)
                .removeClass('btn-secondary')
                .addClass('btn-success');
            } else {
                $('#process').prop('disabled', true)
                .removeClass('btn-success')
                .addClass('btn-secondary');
            }
        }

        // Function to check if both dates are filled and enable/disable PO Number
        function checkDatesAndEnablePO() {
            const fromDate = $('#from_date').val();
            const toDate = $('#to_date').val();
            const partner_Tin = $('#partner_tin').val();
            const add_amount = $('#add_amount').val();
            const num_days = $('#num_days').val();
            
            // Array of allowed TINs
            const allowedTins = ['005-519-158-000'];
            
            if (fromDate && toDate) {
                // Check if partner TIN is valid (not empty, not dash, not null)
                if (partner_Tin === '-' || partner_Tin === '' || partner_Tin === null || partner_Tin === undefined) {
                    $('#po_number').prop('disabled', true).val('-');
                    $('#po_number').prop('required', false);
                    return;
                } else {
                    // Both dates are filled and partner TIN is valid
                    if (allowedTins.includes(partner_Tin)) {
                        $('#po_number').prop('disabled', false).val('');
                        $('#po_number').prop('required', true);
                        return;
                    } else {
                        $('#po_number').prop('disabled', true).val('-');
                        $('#po_number').prop('required', false);
                        return;
                    }
                }
            } else {
                $('#po_number').prop('disabled', true).val('-');
                $('#po_number').prop('required', false);
                return;
            }
        }

        // Function to fetch transaction data based on partner and date range
        function fetchTransactionData() {
            const partnerName = $('#partner').val();
            const fromDate = $('#from_date').val();
            const toDate = $('#to_date').val();
            const partnerTin = $('#partner_tin').val();
            
            // Only proceed if all three values are selected
            if (partnerName && fromDate && toDate) {
                // Show loading state
                $('#loading-overlay').show();
                
                // Make AJAX request to get transaction data
                $.ajax({
                    url: '',
                    type: 'POST',
                    data: {
                        action: 'get_transaction_data',
                        partner_name: partnerName,
                        from_date: fromDate,
                        to_date: toDate
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            // Format the transaction count without decimal points
                            const transactionCount = parseInt(response.data.transaction_count || 0);
                            const formattedTransactionCount = transactionCount.toLocaleString('en-US');
                            $('#num_transactions').val(formattedTransactionCount);
                            
                            // Format the amount with proper comma separation
                            const amount = parseFloat(response.data.total_amount_paid || 0);
                            const charge = parseFloat(response.data.total_charge || 0);
                            const formattedAmount = amount.toLocaleString('en-US', {
                                minimumFractionDigits: 2,
                                maximumFractionDigits: 2
                            });
                            const formattedCharge = charge.toLocaleString('en-US', {
                                minimumFractionDigits: 2,
                                maximumFractionDigits: 2
                            });
                            $('#amount').val(formattedAmount);
                            $('#charge').val(formattedCharge);
                            
                            // Check required fields after updating transaction data
                            checkRequiredFieldsAndEnableProcess();
                        } else {
                            // Clear fields if no data found
                            $('#num_transactions').val('0');
                            $('#amount').val('0.00');
                            $('#charge').val('0.00');
                            
                            console.log('No transaction data found for the selected criteria');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX Error:', error);
                        // Clear fields on error
                        $('#num_transactions').val('0');
                        $('#amount').val('0.00');
                        $('#charge').val('0.00');
                        
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Failed to load transaction data. Please try again.'
                        });
                    },
                    complete: function() {
                        // Hide loading state
                        $('#loading-overlay').hide();
                    }
                });
            } else {
                // Clear fields if not all required data is selected
                $('#num_transactions').val('-');
                $('#amount').val('-');
                $('#charge').val('-');
            }
        }

        // Handle date field changes
        $('#from_date, #to_date').on('change', function() {
            checkDatesAndEnablePO();
            fetchTransactionData(); // Add this line
        });

        // Handle PO Number input validation (numbers only, max 10 characters)
        $('#po_number').on('input', function() {
            let value = $(this).val();
            
            // Remove any non-numeric characters
            value = value.replace(/\D/g, '');
            
            // Limit to 10 characters
            if (value.length > 10) {
                value = value.substring(0, 10);
            }
            
            $(this).val(value);
            
            // Check if exactly 10 characters, then set add_amount to 500 and enable num_days
            if (value.length === 10) {
                const formattedAddAmount = parseFloat('500').toLocaleString('en-US', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
                $('#add_amount').val(formattedAddAmount);
                $('#num_days').prop('disabled', false).val(''); // Enable Number of Days field
                $('#num_days').prop('required', true);
            } 
            else {
                $('#add_amount').val('-');
                $('#num_days').prop('disabled', true).val('-'); // Disable and clear Number of Days field
                $('#num_days').prop('required', false);
            }
        });

        // Handle PO Number paste event
        $('#po_number').on('paste', function(e) {
            setTimeout(function() {
                let value = $('#po_number').val();
                
                // Remove any non-numeric characters
                value = value.replace(/\D/g, '');
                
                // Limit to 10 characters
                if (value.length > 10) {
                    value = value.substring(0, 10);
                }
                
                $('#po_number').val(value);
                
                // Check if exactly 10 characters, then set add_amount to 500 and enable num_days
                if (value.length === 10) {
                    const formattedAddAmount = parseFloat('500').toLocaleString('en-US', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    });
                    $('#add_amount').val(formattedAddAmount);
                    $('#num_days').prop('disabled', false).val(''); // Enable Number of Days field
                    $('#num_days').prop('required', true);
                } else {
                    $('#add_amount').val('-');
                    $('#num_days').prop('disabled', true).val('-'); // Disable and clear Number of Days field
                    $('#num_days').prop('required', false);
                }
            }, 10);
        });

        // Prevent non-numeric input on keypress
        $('#po_number').on('keypress', function(e) {
            // Allow backspace, delete, tab, escape, enter
            if ([8, 9, 27, 13, 46].indexOf(e.keyCode) !== -1 ||
                // Allow Ctrl+A, Ctrl+C, Ctrl+V, Ctrl+X
                (e.keyCode === 65 && e.ctrlKey === true) ||
                (e.keyCode === 67 && e.ctrlKey === true) ||
                (e.keyCode === 86 && e.ctrlKey === true) ||
                (e.keyCode === 88 && e.ctrlKey === true)) {
                return;
            }
            // Ensure that it is a number and stop the keypress
            if ((e.shiftKey || (e.keyCode < 48 || e.keyCode > 57)) && (e.keyCode < 96 || e.keyCode > 105)) {
                e.preventDefault();
            }
        });

        // Handle partner selection change
        $('#partner').on('change', function() {
            const selectedPartner = $(this).val();
            
            // Always clear the card body and reset Process button when partner changes
            $('.card-body').slideUp('fast');
            $('#process').text('Process').removeClass('btn-warning').addClass('btn-secondary').prop('disabled', true);
            
            // Clear all PARTICULARS and AMOUNT section display values
            $('#num-transactions').text('-');
            $('#from-date').text('-');
            $('#to-date').text('-');
            $('#formula').text('-');
            $('#vat-info').text('-');
            $('#gross-amount').text('-');
            $('#vat-amount').text('-');
            $('#net-of-vat').text('-');
            $('#withholding-tax').text('-');
            $('#add-amount').text('-');
            $('#total-amount-due').text('-');
            $('#less-withholding-tax').text('-');
            $('#net-amount-due').text('-');
            $('#prepared-by').text('-');
            
            if (selectedPartner) {
                // Enable date fields when partner is selected
                $('#from_date').prop('disabled', false);
                $('#to_date').prop('disabled', false);
                
                // Show loading state
                $('#loading-overlay').show();
                
                // Make AJAX request to get partner details
                $.ajax({
                    url: '',
                    type: 'POST',
                    data: {
                        action: 'get_partner_details',
                        partner_name: selectedPartner
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            const partner = response.data;
                            
                            // Generate control number from abbreviation and series_number+1
                            const controlNumber = (partner.abbreviation || '') + '-' + (parseInt(partner.series_number || 0) + 1);
                            
                            // Populate the form fields with correct database column names
                            $('#control_number').val(controlNumber || '-');
                            $('#partner_accountname').val(partner.partner_accName || '-');
                            $('#partner_tin').val(partner.partnerTin || '-');
                            $('#address').val(partner.address || '-');
                            $('#business_style').val(partner.businessStyle || '-');
                            $('#service_charge').val(partner.serviceCharge || '-');
                            $('#po_number').val('-').prop('disabled', true).prop('required', false).removeClass('border-success border-danger');
                            $('#add_amount').val('-');
                            $('#num_days').prop('disabled', true).val('-').prop('required', false);

                            // Store partner VAT settings for calculations
                            window.partnerVATSettings = {
                                inc_exc: partner.inc_exc || '',
                                withheld: partner.withheld || '',
                                user_name: partner.user_name || 'Unknown User'
                            };
                            
                            // Clear transaction fields initially
                            $('#num_transactions').val('-');
                            $('#amount').val('-');
                            $('#charge').val('-');
                            
                            // Check required fields and enable process button
                            checkRequiredFieldsAndEnableProcess();
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: response.message || 'Failed to load partner details'
                            });
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX Error:', error);
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Failed to load partner details. Please try again.'
                        });
                    },
                    complete: function() {
                        // Hide loading state
                        $('#loading-overlay').hide();
                    }
                });
            } else {
                // Clear partner VAT settings when no partner is selected
                window.partnerVATSettings = null;
                
                $('#from_date').prop('disabled', true).val('');
                $('#to_date').prop('disabled', true).val('');
                $('#po_number').prop('disabled', true).val('-').removeClass('border-success border-danger');
                $('#add_amount').val('-');
                $('#num_days').prop('disabled', true).val('-');
                
                // Clear all other fields when no partner is selected
                $('#control_number').val('-');
                $('#partner_accountname').val('-');
                $('#partner_tin').val('-');
                $('#address').val('-');
                $('#business_style').val('-');
                $('#service_charge').val('-');
                
                // Clear transaction fields
                $('#num_transactions').val('-');
                $('#amount').val('-');
                $('#charge').val('-');
            }
        });

        // Add this function call to existing event handlers
        $('#partner, #from_date, #to_date, #po_number, #num_days').on('change input', function() {
            checkRequiredFieldsAndEnableProcess();
        });
    });
</script>
<script>


    // New function to calculate VAT based on partner settings
    function calculateVATAmounts(chargeAmount) {
        const inc_exc = window.partnerVATSettings?.inc_exc || '';
        const withheld = window.partnerVATSettings?.withheld || '';
        
        let calculatedVatAmount = 0;
        let calculatedNetOfVat = 0;
        let calculatedWithholdingTax = 0;
        let calculatedTotalAmountDue = 0;
        let calculatedLessWithholdingTax = 0;
        let calculatedNetAmountDue = 0;
        
        if (inc_exc === 'INCLUSIVE') {
            if (withheld === 'Yes') {
                // VAT calculations (12% of charge) - INCLUSIVE with withholding
                calculatedVatAmount = (chargeAmount * 0.12) / 1.12;
                calculatedNetOfVat = chargeAmount - calculatedVatAmount;
                calculatedWithholdingTax = calculatedNetOfVat * 0.02;
                calculatedTotalAmountDue = chargeAmount + calculatedVatAmount;
                calculatedLessWithholdingTax = calculatedWithholdingTax;
                calculatedNetAmountDue = chargeAmount - calculatedWithholdingTax;
            } else if (withheld === 'No') {
                // VAT calculations (12% of charge) - INCLUSIVE without withholding
                calculatedVatAmount = (chargeAmount * 0.12) / 1.12;
                calculatedNetOfVat = chargeAmount - calculatedVatAmount;
                calculatedWithholdingTax = 0;
                calculatedTotalAmountDue = chargeAmount;
                calculatedLessWithholdingTax = 0;
                calculatedNetAmountDue = chargeAmount;
            }
        } else if (inc_exc === 'EXCLUSIVE') {
            if (withheld === 'Yes') {
                // VAT calculations - EXCLUSIVE with withholding
                calculatedVatAmount = chargeAmount * 0.12;
                calculatedNetOfVat = 0;
                calculatedWithholdingTax = chargeAmount * 0.02;
                calculatedTotalAmountDue = chargeAmount + calculatedVatAmount;
                calculatedLessWithholdingTax = calculatedWithholdingTax;
                calculatedNetAmountDue = calculatedTotalAmountDue - calculatedWithholdingTax;
            } 
            // else {
            //     // VAT calculations - EXCLUSIVE without withholding
            //     calculatedVatAmount = chargeAmount * 0.12;
            //     calculatedNetOfVat = chargeAmount;
            //     calculatedWithholdingTax = 0;
            //     calculatedTotalAmountDue = chargeAmount + calculatedVatAmount;
            //     calculatedLessWithholdingTax = 0;
            //     calculatedNetAmountDue = calculatedTotalAmountDue;
            // }
        } else if (inc_exc === 'NON-VAT') {
            if (withheld === 'Yes') {
                calculatedTotalAmountDue = chargeAmount;
                calculatedNetAmountDue = calculatedTotalAmountDue;
            }
        }
        // else {
        //     // Default calculation if inc_exc is not set
        //     calculatedVatAmount = (chargeAmount * 0.12) / 1.12;
        //     calculatedNetOfVat = chargeAmount - calculatedVatAmount;
        //     calculatedWithholdingTax = calculatedNetOfVat * 0.02;
        //     calculatedTotalAmountDue = chargeAmount;
        //     calculatedLessWithholdingTax = calculatedWithholdingTax;
        //     calculatedNetAmountDue = chargeAmount - calculatedWithholdingTax;
        // }
        
        return {
            vatAmount: calculatedVatAmount,
            netOfVat: calculatedNetOfVat,
            withholdingTax: calculatedWithholdingTax,
            totalAmountDue: calculatedTotalAmountDue,
            lessWithholdingTax: calculatedLessWithholdingTax,
            netAmountDue: calculatedNetAmountDue
        };
    }

    // Handle Process button click
    $('#process').on('click', function(e) {
        e.preventDefault(); // Prevent form submission
        
        // Get form data
        const partnerName = $('#partner').val();
        const fromDate = $('#from_date').val();
        const toDate = $('#to_date').val();
        const numTransactions = $('#num_transactions').val();
        const amount = $('#amount').val();
        const charge = $('#charge').val();
        const addAmount = $('#add_amount').val();
        const numDays = $('#num_days').val();
        
        // Show the card body with animation
        $('.card-body').slideDown('slow');
        
        // Populate the PARTICULARS section
        const particularsTable = $('.col-md-6').first().find('table');
        particularsTable.find('tr').eq(1).find('td').eq(2).text(numTransactions);

        // Format dates using JavaScript Date object
        const formatDate = (dateString) => {
            const date = new Date(dateString);
            const options = { year: 'numeric', month: 'long', day: 'numeric' };
            return date.toLocaleDateString('en-US', options);
        };

        // Use formatted dates instead of raw date strings
        particularsTable.find('tr').eq(2).find('td').eq(2).text(formatDate(fromDate));
        particularsTable.find('tr').eq(2).find('td').eq(5).text(formatDate(toDate));

        // Also update the display elements with IDs
        $('#from-date').text(formatDate(fromDate));
        $('#to-date').text(formatDate(toDate));

        // Calculate amounts
        const grossAmount = parseFloat(amount.replace(/,/g, '') || 0);
        const chargeAmount = parseFloat(charge.replace(/,/g, '') || 0);
        const additionalAmount = parseFloat(addAmount.replace(/,/g, '') || 0);
        const numOfDays = parseInt(numDays || 0);
        
        // Use the new VAT calculation method based on partner settings
        const vatCalculations = calculateVATAmounts(chargeAmount);
        let vatAmount = vatCalculations.vatAmount;
        let netOfVat = vatCalculations.netOfVat;
        let withholdingTax = vatCalculations.withholdingTax;
        let totalAmountDue = vatCalculations.totalAmountDue;
        let lessWithholdingTax = vatCalculations.lessWithholdingTax;
        let netAmountDue = vatCalculations.netAmountDue;
        
        // Calculate display add amount
        let displayAddAmount = 0;
        if (additionalAmount > 0 && numOfDays > 0) {
            displayAddAmount = additionalAmount * numOfDays;
            
            netAmountDue = (totalAmountDue - lessWithholdingTax) + displayAddAmount;
        }
        
        // Format amounts with proper currency formatting
        const formatCurrency = (value) => {
            return parseFloat(value || 0).toLocaleString('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        };
        
        // Update AMOUNT section
        const amountTable = $('.col-md-6').last().find('table');
        amountTable.find('tr').eq(1).find('td').eq(3).text(formatCurrency(grossAmount));
        amountTable.find('tr').eq(3).find('td').eq(3).text(formatCurrency(vatAmount));
        amountTable.find('tr').eq(4).find('td').eq(3).text(formatCurrency(netOfVat));
        amountTable.find('tr').eq(5).find('td').eq(3).text(formatCurrency(withholdingTax));
        amountTable.find('tr').eq(6).find('td').eq(3).text(formatCurrency(displayAddAmount));
        amountTable.find('tr').eq(8).find('td').eq(3).text(formatCurrency(totalAmountDue));
        amountTable.find('tr').eq(9).find('td').eq(3).text(formatCurrency(lessWithholdingTax));
        amountTable.find('tr').eq(10).find('td').eq(3).text(formatCurrency(netAmountDue));
        
        // Scroll to the card body
        $('html, body').animate({
            scrollTop: $('.card-body').offset().top - 50
        }, 500);
        
        // Optional: Hide the Process button or change it to "Recalculate"
        $(this).text('Recalculate').removeClass('btn-success').addClass('btn-warning');

        // Display the formula (inc_exc value) from partner settings
        const incExcValue = window.partnerVATSettings?.inc_exc || 'N/A';
        $('#formula').text(incExcValue);

        // Also add VAT information based on inc_exc and withheld values
        const withheldValue = window.partnerVATSettings?.withheld || '';
        let vatInfoText;
        let additionalInfoText;
        let vatinfotextforwritedDB;

        let vatInfoContentText = [
                'VAT Amount (12%) = (Total Charge * 12%) / 1.12;Net of VAT = Total Charge - VAT Amount;Withholding Tax (2%) = Net of VAT * 2%;', //incExcValue === 'INCLUSIVE' then withheldValue === 'Yes'
                'VAT Amount (12%) = Total Charge / 1.12;Net of VAT = Total Charge - VAT Amount;Withholding Tax (2%) = Total Charge * 2%;', // incExcValue === 'INCLUSIVE' then withheldValue === 'No'
                'VAT Amount (12%) = Total Charge * 12%;Withholding Tax (2%) = Total Charge * 2%;', // incExcValue === 'EXCLUSIVE' then withheldValue === 'Yes'
                '-'
            ];
        vatinfotextconverted = vatInfoContentText.map(text => text.replace(/;/g, '<br>'));

        if (incExcValue === 'INCLUSIVE') {
            vatInfoText = withheldValue === 'Yes' ? vatinfotextconverted[0] : vatinfotextconverted[1];
            additionalInfoText = '-';
            vatinfotextforwritedDB = withheldValue === 'Yes' ? vatInfoContentText[0] : vatInfoContentText[1];

        } else if (incExcValue === 'EXCLUSIVE') {
            if (additionalAmount > 0 && numOfDays > 0) {
                vatInfoText = withheldValue === 'Yes' ? vatinfotextconverted[2] : '-';
                additionalInfoText = 'Add Amount = 500.00 * Number of Days';
                vatinfotextforwritedDB = withheldValue === 'Yes' ? vatInfoContentText[2] : null;

            } else {
                vatInfoText = withheldValue === 'Yes' ? vatinfotextconverted[2] : '-';
                additionalInfoText = '-';
                vatinfotextforwritedDB = withheldValue === 'Yes' ? vatInfoContentText[2] : null;
            }

        } else if (incExcValue === 'NON-VAT') {
            vatInfoText = withheldValue === 'Yes' ? vatinfotextconverted[3] : '-';
            additionalInfoText = '-';
            vatinfotextforwritedDB = withheldValue === 'Yes' ? vatInfoContentText[3] : null;
            
        } else {
            vatInfoText = 'VAT settings not configured';
            additionalInfoText = '-';
            vatinfotextforwritedDB = null;
        }

        $('#vat-info').html(vatInfoText);
        $('#additional-info').html(additionalInfoText) || '-';
        $('#vatinfo-for-db').val(vatinfotextforwritedDB); // make as varialbe for id argument for writed at database
        // Also update the prepared by field
        const userName = window.partnerVATSettings.user_name || 'Unknown User';
        $('#prepared-by').text(userName);
    });

    // Handle Save button click
    $('#save').on('click', function(e) {
        e.preventDefault(); // Prevent default form submission
        
        Swal.fire({
            title: 'Do you want to Save this action?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#198754',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, Proceed',
            cancelButtonText: 'No, Cancel',
            reverseButtons: false
        }).then((result) => {
            if (result.isConfirmed) {
                // User clicked "Yes, Proceed" - collect all data and save
            
                // Get all form field values
                const partnerName = $('#partner').val();
                const controlNumber = $('#control_number').val();
                const date = $('#date').val();
                const partnerAccountName = $('#partner_accountname').val();
                const partnerTin = $('#partner_tin').val();
                const address = $('#address').val();
                const businessStyle = $('#business_style').val();
                const serviceCharge = $('#service_charge').val();
                const fromDate = $('#from_date').val();
                const toDate = $('#to_date').val();
                const poNumber = $('#po_number').val();
                const numberOfTransactions = $('#num_transactions').val();
                const totalCharge = $('#charge').val(); // Amount from Total Charge field
                const addAmountField = $('#add_amount').val(); // Add Amount field value
                const numberOfDays = $('#num_days').val();
                
                // Get calculated values from display and convert to decimal format
                const vatAmountText = $('#vat-amount').text().replace(/,/g, '');
                const netOfVatText = $('#net-of-vat').text().replace(/,/g, '');
                const withholdingTaxText = $('#withholding-tax').text().replace(/,/g, '');
                const totalAmountDueText = $('#total-amount-due').text().replace(/,/g, '');
                const netAmountDueText = $('#net-amount-due').text().replace(/,/g, '');
                
                // Convert to proper decimal format with 2 decimal places
                const vatAmount = parseFloat(vatAmountText || 0).toFixed(2);
                const netOfVat = parseFloat(netOfVatText || 0).toFixed(2);
                const withholdingTax = parseFloat(withholdingTaxText || 0).toFixed(2);
                const totalAmountDue = parseFloat(totalAmountDueText || 0).toFixed(2);
                const netAmountDue = parseFloat(netAmountDueText || 0).toFixed(2);
                
                const formula = $('#formula').text();
                const formulaIncExc = $('#vatinfo-for-db').val();
                const preparedBy = $('#prepared-by').text();
                
                // Calculate add_amount (Add Amount field * Number of Days) with proper decimal formatting
                const addAmountFieldValue = parseFloat(addAmountField.replace(/,/g, '') || 0);
                const numDaysValue = parseInt(numberOfDays || 0);
                const calculatedAddAmount = (addAmountFieldValue * numDaysValue).toFixed(2);
                
                // Get withheld value from partner settings
                const formulaWithheld = window.partnerVATSettings?.withheld || '';
                
                // Send AJAX request to save data
                $.ajax({
                    url: '',
                    type: 'POST',
                    data: {
                        action: 'save_billing_invoice',
                        partner_name: partnerName,
                        control_number: controlNumber,
                        date: date,
                        partner_accountname: partnerAccountName,
                        partner_tin: partnerTin,
                        address: address,
                        business_style: businessStyle,
                        service_charge: serviceCharge,
                        from_date: fromDate,
                        to_date: toDate,
                        po_number: poNumber,
                        number_of_transactions: numberOfTransactions,
                        total_charge: totalCharge,
                        calculated_add_amount: calculatedAddAmount,
                        add_amount_field: addAmountField,
                        number_of_days: numberOfDays,
                        formula: formula,
                        formula_withheld: formulaWithheld,
                        formula_inc_exc: formulaIncExc,
                        vat_amount: vatAmount,
                        net_of_vat: netOfVat,
                        withholding_tax: withholdingTax,
                        total_amount_due: totalAmountDue,
                        net_amount_due: netAmountDue,
                        prepared_by: preparedBy
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            // Show success message
                            Swal.fire({
                                icon: 'success',
                                title: 'Saved!',
                                text: 'Your billing invoice has been saved successfully.',
                                timer: 2000,
                                showConfirmButton: false,
                                allowOutsideClick: false,
                                allowEscapeKey: false
                            }).then(() => {
                                // Refresh the page after the SweetAlert closes
                                window.location.reload();
                            });
                        } 
                        else {
                            // Show error message
                            Swal.fire({
                                icon: 'error',
                                title: 'Error!',
                                text: response.message || 'Failed to save billing invoice.',
                                showConfirmButton: false,
                                allowOutsideClick: false,
                                allowEscapeKey: false,
                                timer: 2000
                            });
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Save Error:', error);
                        Swal.fire({
                            icon: 'error',
                            title: 'Error!',
                            text: 'An error occurred while saving. Please try again.',
                            showConfirmButton: false,
                            allowOutsideClick: false,
                            allowEscapeKey: false,
                            timer: 2000
                        });
                    }
                });
            }
            // If user clicked "No, Cancel", nothing happens (dialog just closes)
        });
    });
</script>
<script>
    // Handle PO Number validation
    // Enhanced PO Number validation with corrected year-based logic
    function checkPONumberInDatabase(poNumber) {
        if (poNumber && poNumber.length === 10) {
            $.ajax({
                url: '',
                type: 'POST',
                data: {
                    action: 'get_matched_po_data',
                    po_number: poNumber
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success && response.duplicate && response.previous_year_not_allowed) {
                        // PO Number from previous year - not allowed
                        $('#po_number').removeClass('border-success').addClass('border-danger');
                        $('#add_amount').val('0.00');
                        $('#num_days').prop('disabled', true).val('-').prop('required', false);
                        
                        Swal.fire({
                            icon: 'error',
                            title: 'Previous Year PO Number',
                            text: `This PO Number was used in ${response.previous_year}. Previous year PO numbers cannot be reused.`,
                            toast: true,
                            position: 'top-end',
                            showConfirmButton: false,
                            timer: 4000
                        });
                    } else if (response.current_year_reuse) {
                        // PO Number from current year - allowed for reuse
                        $('#po_number').removeClass('border-danger').addClass('border-success');
                        const formattedAddAmount = parseFloat('500').toLocaleString('en-US', {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2
                        });
                        $('#add_amount').val(formattedAddAmount);
                        $('#num_days').prop('disabled', false).val('').prop('required', true);
                        
                        Swal.fire({
                            icon: 'success',
                            title: 'PO Number Reuse',
                            text: 'This PO Number is being reused within the current year - allowed.',
                            toast: true,
                            position: 'top-end',
                            showConfirmButton: false,
                            timer: 2000
                        });
                    } else if (!response.duplicate) {
                        // New PO Number - allowed
                        $('#po_number').removeClass('border-danger').addClass('border-success');
                        const formattedAddAmount = parseFloat('500').toLocaleString('en-US', {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2
                        });
                        $('#add_amount').val(formattedAddAmount);
                        $('#num_days').prop('disabled', false).val('').prop('required', true);
                    } else {
                        // Fallback for other cases
                        $('#po_number').removeClass('border-success').addClass('border-danger');
                        $('#add_amount').val('0.00');
                        $('#num_days').prop('disabled', true).val('-').prop('required', false);
                    }
                    
                    checkRequiredFieldsAndEnableProcess();
                },
                error: function(xhr, status, error) {
                    console.error('PO Number validation error:', error);
                    $('#po_number').removeClass('border-success').addClass('border-danger');
                    $('#add_amount').val('0.00');
                    $('#num_days').prop('disabled', true).val('-').prop('required', false);
                    checkRequiredFieldsAndEnableProcess();
                }
            });
        } else {
            $('#po_number').removeClass('border-success border-danger');
            $('#add_amount').val('-');
            $('#num_days').prop('disabled', true).val('-').prop('required', false);
            checkRequiredFieldsAndEnableProcess();
        }
    }

    // Update the existing PO Number input handler
    $('#po_number').on('input', function() {
        let value = $(this).val();
        
        // Remove any non-numeric characters
        value = value.replace(/\D/g, '');
        
        // Limit to 10 characters
        if (value.length > 10) {
            value = value.substring(0, 10);
        }
        
        $(this).val(value);
        
        // Check PO number in database when exactly 10 characters
        if (value.length === 10) {
            checkPONumberInDatabase(value);
        } else {
            // Reset fields if less than 10 characters
            $(this).removeClass('border-success border-danger');
            $('#add_amount').val('-');
            $('#num_days').prop('disabled', true).val('-').prop('required', false);
            checkRequiredFieldsAndEnableProcess();
        }
    });

    // Update the existing PO Number paste handler
    $('#po_number').on('paste', function(e) {
        setTimeout(function() {
            let value = $('#po_number').val();
            
            // Remove any non-numeric characters
            value = value.replace(/\D/g, '');
            
            // Limit to 10 characters
            if (value.length > 10) {
                value = value.substring(0, 10);
            }
            
            $('#po_number').val(value);
            
            // Check PO number in database when exactly 10 characters
            if (value.length === 10) {
                checkPONumberInDatabase(value);
            } else {
                // Reset fields if less than 10 characters
                $('#po_number').removeClass('border-success border-danger');
                $('#add_amount').val('-');
                $('#num_days').prop('disabled', true).val('-').prop('required', false);
                checkRequiredFieldsAndEnableProcess();
            }
        }, 10);
    });
</script>
</html>