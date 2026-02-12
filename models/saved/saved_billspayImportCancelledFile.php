<?php

include '../../config/config.php';
require '../../vendor/autoload.php';

// Start the session
session_start();

if (isset($_SESSION['user_type'])) {
    $current_user_email = '';
    if ($_SESSION['user_type'] === 'admin' && isset($_SESSION['admin_email'])) {
        $current_user_email = $_SESSION['admin_email'];
    } elseif ($_SESSION['user_type'] === 'user' && isset($_SESSION['user_email'])) {
        $current_user_email = $_SESSION['user_email'];
        if($_SESSION['user_email'] === 'balb01013333' || $_SESSION['user_email'] === 'pera94005055'){
            header("Location:../../index.php");
            session_destroy();
            exit();
        }
    }else{
        header("Location:../../index.php");
        session_destroy();
        exit();
    }
}

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;


ini_set('memory_limit', '-1');
set_time_limit(0);
// Enable mysqli exceptions for clearer DB errors during import
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Handle cancel action (clear uploaded data / session)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel_import') {
    // Delete temporary JSON files for this import
    $temporaryDir = __DIR__ . '/temporary';
    $deleted = 0;
    if (is_dir($temporaryDir)) {
        $files = glob($temporaryDir . '/import_cancelled_*.json');
        if (!empty($files)) {
            foreach ($files as $f) {
                if (is_file($f)) {
                    @unlink($f);
                    $deleted++;
                }
            }
        }
    }

    // Clear relevant session keys used by import workflow
    $keysToClear = [
        'partnerselection', 'ready_to_override_data', 'processed_override_data',
        'Matched_BranchID_data', 'cancellation_BranchID_data', 'original_file_name',
        'source_file_type', 'transactionDate'
    ];
    foreach ($keysToClear as $k) {
        if (isset($_SESSION[$k])) unset($_SESSION[$k]);
    }

    // Also clear POST and FILES arrays on server side for this request
    $_POST = [];
    $_FILES = [];

    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'deleted_files' => $deleted]);
    exit;
}

// Handle uploaded file (supports .csv, .xls, .xlsx)
if (isset($_POST['upload'])) {
    if (isset($_FILES['import_file']) && $_FILES['import_file']['error'] === UPLOAD_ERR_OK) {
        $tmpPath = $_FILES['import_file']['tmp_name'];
        $fileName = $_FILES['import_file']['name'];
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        $rows = [];
        $startRow = 7; // 1-based
        $startColIndex = 1; // B -> index 1 (0-based)
        $endColIndex = 16; // Q -> index 16 (0-based), inclusive

        // read partner name from POST (support both 'partner_name' and 'partner')
        $partnerName = $_POST['partner_name'] ?? $_POST['partner'] ?? '';
        // get partner_id, partner_id_kpx, gl_code converted based on selected partner name for cancellation
        $partnerSQL = "SELECT DISTINCT partner_name, partner_id, partner_id_kpx, gl_code FROM masterdata.partner_masterfile WHERE partner_name = ? LIMIT 1";
        $partnerId = null;
        $partnerIdKpx = null;
        $glCode = null;
        if (!empty($partnerName) && strtoupper($partnerName) !== 'ALL') {
            $stmt = $conn->prepare($partnerSQL);
            $stmt->bind_param("s", $partnerName);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result && $result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $partnerId = $row['partner_id'];
                $partnerIdKpx = $row['partner_id_kpx'];
                $glCode = $row['gl_code'];
            }
            $stmt->close();
        }

        // get branch_id, branch_code, zone_code, region_code, region based on reference number provided in POST
        $referenceNo = $_POST['reference_no'] ?? '';
        $BranchNameSql = "SELECT DISTINCT branch_id, branch_code, zone_code, region_code, region FROM billspayment_transaction WHERE reference_no = ? LIMIT 1";
        $branchId = null;
        $branchCode = null;
        $zoneCode = null;
        $regionCode = null;
        $region = null;
        if (!empty($referenceNo)) {
            $stmt = $conn->prepare($BranchNameSql);
            $stmt->bind_param("s", $referenceNo);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result && $result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $branchId = $row['branch_id'];
                $branchCode = $row['branch_code'];
                $zoneCode = $row['zone_code'];
                $regionCode = $row['region_code'];
                $region = $row['region'];
            }
            $stmt->close();
        }
        
        try {
            if ($fileExt === 'csv') {
                // Parse CSV
                $handle = fopen($tmpPath, 'r');
                if ($handle === false) throw new Exception('Unable to open uploaded CSV file.');

                $rowIndex = 0;
                while (($data = fgetcsv($handle)) !== false) {
                    $rowIndex++;
                    if ($rowIndex < $startRow) continue; // skip until start row

                    // Ensure the row has enough columns
                    $rowData = [];
                    $allEmpty = true;
                    for ($i = $startColIndex; $i <= $endColIndex; $i++) {
                        $value = isset($data[$i]) ? trim((string)$data[$i]) : '';
                        $rowData[] = $value;
                        if ($value !== '') $allEmpty = false;
                    }

                    if ($allEmpty) break;
                    $rows[] = $rowData;
                }
                fclose($handle);
            } elseif (in_array($fileExt, ['xls', 'xlsx'])) {
                // Use PhpSpreadsheet for Excel files
                $spreadsheet = IOFactory::load($tmpPath);
                $worksheet = $spreadsheet->getActiveSheet();
                $highestRow = $worksheet->getHighestRow();

                for ($r = $startRow; $r <= $highestRow; $r++) {
                    $rowData = [];
                    $allEmpty = true;
                    // Column B..Q correspond to column numbers 2..17
                    for ($c = 2; $c <= 17; $c++) {
                        $cell = $worksheet->getCellByColumnAndRow($c, $r);
                        $cellValue = $cell !== null ? $cell->getValue() : '';
                        $value = trim((string)$cellValue);
                        $rowData[] = $value;
                        if ($value !== '') $allEmpty = false;
                    }
                    if ($allEmpty) break;
                    $rows[] = $rowData;
                }
                // Free resources
                if (isset($spreadsheet) && is_object($spreadsheet)) {
                    try { $spreadsheet->disconnectWorksheets(); } catch (Exception $e) {}
                    unset($worksheet, $spreadsheet);
                    if (function_exists('gc_collect_cycles')) gc_collect_cycles();
                }
            } else {
                throw new Exception('Unsupported file type. Accepted: .csv, .xls, .xlsx');
            }

            if (empty($rows)) {
                echo '<script>alert("No data found starting at row 7 in columns B to Q.");window.history.back();</script>';
                exit;
            }

            $outDir = __DIR__ . '/temporary';
            if (!is_dir($outDir)) mkdir($outDir, 0777, true);

            $outFile = $outDir . '/import_cancelled_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.json';
            file_put_contents($outFile, json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            // store import metadata in session for display on summary
            $uploadedBy = '';
            if (isset($_SESSION['user_type'])) {
                if ($_SESSION['user_type'] === 'admin' && isset($_SESSION['admin_name'])) $uploadedBy = $_SESSION['admin_name'];
                elseif ($_SESSION['user_type'] === 'user' && isset($_SESSION['user_name'])) $uploadedBy = $_SESSION['user_name'];
            }
            $source = $_POST['fileType'] ?? $_POST['source'] ?? '';

            $_SESSION['import_meta'] = [
                'partner_id' => $partnerId,
                'partner_id_kpx' => $partnerIdKpx,
                'gl_code' => $glCode,
                'partner_name' => $partnerName,
                'reference_no' => $referenceNo,
                'source' => $source,
                'uploaded_date' => date('Y-m-d'),
                'uploaded_by' => $uploadedBy,
                'branch_id' => $branchId,
                'branch_code' => $branchCode,
                'zone_code' => $zoneCode,
                'region_code' => $regionCode,
                'region' => $region,
                'rows_saved' => count($rows),
                'json_file' => basename($outFile)
            ];

            echo '<script>alert("File processed successfully. Rows saved: ' . count($rows) . '");window.location.href = "' . basename(__FILE__) . '";</script>';
            exit;
        } catch (Exception $e) {
            echo '<script>alert("Error processing file: ' . addslashes($e->getMessage()) . '");window.history.back();</script>';
            exit;
        }
    } else {
        echo '<script>alert("No file uploaded or upload error.");window.history.back();</script>';
        exit;
    }
}

// Accept either an explicit 'action=confirm_import' or the form field 'confirm_import'
if ((isset($_POST['action']) && $_POST['action'] === 'confirm_import') || isset($_POST['confirm_import'])) {
    // Load latest JSON rows
    $temporaryDir = __DIR__ . '/temporary';
    $latestFile = null;
    $rowsToInsert = [];
    if (is_dir($temporaryDir)) {
        $files = glob($temporaryDir . '/import_cancelled_*.json');
        if (!empty($files)) {
            usort($files, function($a, $b) {
                return filemtime($b) - filemtime($a);
            });
            $latestFile = $files[0];
            $content = @file_get_contents($latestFile);
            if ($content !== false) {
                $decoded = json_decode($content, true);
                if (is_array($decoded)) $rowsToInsert = $decoded;
            }
        }
    }

    header('Content-Type: application/json');

    if (empty($rowsToInsert)) {
        echo json_encode(['success' => false, 'error' => 'No rows to import']);
        exit;
    }

    // Prepare insert statement for cancellation table
    $insertSQL = "INSERT INTO mldb.billspayment_cancellation (
        cancellation_datetime, 
        sendout_datetime, 
        source_file, 
        control_no, 
        reference_no, 
        ir_no,
        payor, 
        account_no, 
        account_name, 
        principal_amount, 
        charge_to_customer, 
        charge_to_partner,
        cancellation_charge, 
        resource, 
        branch_id, 
        branch_code, 
        branch_name, 
        zone_code, 
        region_code,
        region, 
        remote_branch, 
        remote_operator, 
        partner_name, 
        partner_id, 
        partner_id_kpx, 
        mpm_gl_code,
        imported_by, 
        imported_date
    ) VALUES (" . rtrim(str_repeat('?,', 28), ',') . ")";

    if (!($stmt = $conn->prepare($insertSQL))) {
        echo json_encode(['success' => false, 'error' => 'Prepare failed: ' . $conn->error]);
        exit;
    }

    // types: first 9 strings, then 4 doubles, then 15 strings => total 28
    $types = 'sssssssssddddsssssssssssssss';

    // Start transaction
    $conn->begin_transaction();
    $inserted = 0;
    $errors = [];

    // helper to clean numeric
    $cleanNumber = function($v) {
        $s = trim((string)$v);
        if ($s === '') return 0.0;
        // remove parentheses, commas, currency symbols
        $s = preg_replace('/[\(\)\$,\s]/', '', $s);
        $s = preg_replace('/[^0-9.\-]/', '', $s);
        return is_numeric($s) ? floatval($s) : 0.0;
    };

    try {
        foreach ($rowsToInsert as $row) {
            $meta = $_SESSION['import_meta'] ?? [];
            // row indexes: B..Q => 0..15
            $cancellation_datetime = date('Y-m-d H:i:s', strtotime($row[0])); // B $row[0] ?? null;
            $sendout_datetime = date('Y-m-d H:i:s', strtotime($row[1] ?? '')); // Q
            $source_file = $meta['source'] ?? '';
            $control_no = $row[3] ?? null;
            $reference_no = $row[2] ?? null;
            $ir_no = $row[7] ?? null;
            $payor = $row[6] ?? null;
            $account_no = $row[4] ?? null;
            $account_name = $row[5] ?? null;

            // indexes for money
            $principal_amount = $cleanNumber($row[8] ?? ''); // J
            $charge_to_customer = $cleanNumber($row[10] ?? ''); // L
            $charge_to_partner = $cleanNumber($row[11] ?? ''); // M
            $cancellation_charge = $cleanNumber($row[9] ?? ''); // K

            $resource = $row[12] ?? null;
            $branch_id = $meta['branch_id'] ?? null;
            $branch_code = $meta['branch_code'] ?? null;
            $branch_name = $row[13] ?? null; // P
            $zone_code = $meta['zone_code'] ?? null;
            $region_code = $meta['region_code'] ?? null;
            $region = $meta['region'] ?? null;
            $remote_branch = $row[15] ?? null; // N
            $remote_operator = $row[14] ?? null; // O

            $partner_name = $meta['partner_name'] ?? null;
            $partner_id = $meta['partner_id'] ?? null;
            $partner_id_kpx = $meta['partner_id_kpx'] ?? null;
            $mpm_gl_code = $meta['gl_code'] ?? null;

            $imported_by = $meta['uploaded_by'] ?? null;
            $imported_date = $meta['uploaded_date'] ?? date('Y-m-d');

            // bind params by reference
            $params = [ $types,
                $cancellation_datetime, $sendout_datetime, $source_file, $control_no, $reference_no, $ir_no,
                $payor, $account_no, $account_name, $principal_amount, $charge_to_customer, $charge_to_partner,
                $cancellation_charge, $resource, $branch_id, $branch_code, $branch_name, $zone_code, $region_code,
                $region, $remote_branch, $remote_operator, $partner_name, $partner_id, $partner_id_kpx, $mpm_gl_code,
                $imported_by, $imported_date
            ];

            // make references
            $bindParams = [];
            foreach ($params as $key => $value) {
                $bindParams[$key] = &$params[$key];
            }

            // bind parameters and check result
            $bindResult = @call_user_func_array([$stmt, 'bind_param'], $bindParams);
            if ($bindResult === false) {
                $err = $stmt->error ?: $conn->error;
                $errors[] = 'bind_param failed: ' . $err;
                error_log('[IMPORT ERROR] bind_param failed: ' . $err);
                continue;
            }

            // execute and capture any error
            if (!$stmt->execute()) {
                $err = $stmt->error ?: $conn->error;
                $errors[] = 'execute failed: ' . $err;
                error_log('[IMPORT ERROR] execute failed: ' . $err);
            } else {
                $inserted++;
            }
        }

        if (empty($errors)) {
            $conn->commit();
            echo json_encode(['success' => true, 'inserted' => $inserted]);
        } else {
            $conn->rollback();
            echo json_encode(['success' => false, 'error' => implode('; ', $errors)]);
        }
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }

    $stmt->close();
    exit;
}

?>

<?php
// Load latest processed JSON file from temporary folder for display
$temporaryDir = __DIR__ . '/temporary';
$latestFile = null;
$rows = [];
$fileCreatedAt = null;
if (is_dir($temporaryDir)) {
    $files = glob($temporaryDir . '/import_cancelled_*.json');
    if (!empty($files)) {
        usort($files, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });
        $latestFile = $files[0];
        $fileContent = @file_get_contents($latestFile);
        if ($fileContent !== false) {
            $decoded = json_decode($fileContent, true);
            if (is_array($decoded)) $rows = $decoded;
        }
        $fileCreatedAt = $latestFile ? date('Y-m-d H:i:s', filemtime($latestFile)) : null;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import File - Summary</title>
    <!-- custom CSS file link  -->
    <link rel="stylesheet" href="../../assets/css/billspaymentImportFile.css?v=1">
    <link rel="icon" href="../../images/MLW logo.png" type="image/png">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css" rel="stylesheet" />
    <!-- Font Awesome for icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- SweetAlert2 CSS (styling only) -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.min.css" rel="stylesheet">

    <!-- SWEET ALERT CONFIRM AND CANCEL BUTTONS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
       /* Print styles */
        @media print {
            body * {
                visibility: hidden;
                visibility: visible;
            }
            .alert-warning {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                border: none !important;
                background-color: white !important;
                color: black !important;
            }
            .alert-warning .d-flex {
                display: none !important;
            }
            .alert-warning h4 {
                text-align: center;
                font-size: 18px;
                margin-bottom: 15px;
            }
            .alert-warning p {
                text-align: center;
                margin-bottom: 15px;
            }
            .table-responsive {
                max-height: none !important;
                height: auto !important;
                overflow: visible !important;
            }
            .table {
                width: 100%;
                border-collapse: collapse;
                page-break-inside: auto;
            }
            .table th, .table td {
                border: 1px solid #000;
            }
            .table tr {
                page-break-inside: avoid;
                page-break-after: auto;
            }
            .sticky-top {
                position: static;
            }
        }

        .swal2-container.swal2-backdrop-show {
            backdrop-filter: blur(10px);
            background-color: rgba(0,0,0,0.8) !important;
        }
        .swal2-popup {
            backdrop-filter: none !important;
            box-shadow: 0 0 20px rgba(0,0,0,0.5);
        }

        /* Loading overlay visuals (static) */
        #loading-overlay { display: none; position: fixed; inset: 0; background: rgba(255,255,255,0.8); z-index: 1050; }
        .loading-spinner { width: 3rem; height: 3rem; border-radius: 50%; border: 4px solid #ccc; border-top-color: #0d6efd; margin: 20% auto; }
    </style>
</head>
<body>
    <div id="loading-overlay">
        <div class="loading-spinner"></div>
    </div>

    <div id="summary-section">
        <div id="upload-success" class="container-fluid py-4" style="margin-top: 20px;">
            <div class="text-center mb-4">
                <div class="card shadow-sm border-0 bg-light py-4">
                    <h3 class="text-center fw-bold text-primary">Would you like to proceed inserting the data?</h3>
                    <div class="card-body">
                        <form method="post" id="confirmImportForm" class="d-inline">
                            <input type="hidden" name="confirm_import" value="1">
                            <button type="button" class="btn btn-success btn-lg me-3 shadow-sm" onclick="confirmImport()">
                                <i class="fas fa-check-circle me-2"></i>Confirm Import
                            </button>
                        </form>
                        <button type="button" class="btn btn-danger btn-lg shadow-sm" onclick="confirmCancel()">
                            <i class="fas fa-times-circle me-2"></i>Cancel
                        </button>
                    </div>
                </div>
            </div>
            <div class="row mt-4 gx-4">
                <!-- Import Details Card -->
                <div class="col-md-3">
                    <div class="card shadow border-0 h-100">
                        <div class="card-header bg-success text-white py-3">
                            <h4 class="mb-0 text-center"><i class="fas fa-info-circle me-2"></i>Import Details</h4>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover align-middle">
                                    <thead>
                                        <tr class="table-secondary">
                                            <th>Property</th>
                                            <th>Value</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td><i class="fas fa-id-card text-primary me-2"></i>KP7 Partner ID</td>
                                            <td class="fw-semibold"><?php echo htmlspecialchars($_SESSION['import_meta']['partner_id'] ?? '—'); ?></td>
                                        </tr>
                                        <tr>
                                            <td><i class="fas fa-id-card text-primary me-2"></i>KPX Partner ID</td>
                                            <td class="fw-semibold"><?php echo htmlspecialchars($_SESSION['import_meta']['partner_id_kpx'] ?? '—'); ?></td>
                                        </tr>
                                        <tr>
                                            <td><i class="fas fa-id-card text-primary me-2"></i>GL Code</td>
                                            <td class="fw-semibold"><?php echo htmlspecialchars($_SESSION['import_meta']['gl_code'] ?? '—'); ?></td>
                                        </tr>
                                        <tr>
                                            <td><i class="fas fa-building text-primary me-2"></i>Partner Name</td>
                                            <td class="fw-semibold"><?php echo htmlspecialchars($_SESSION['import_meta']['partner_name'] ?? '—'); ?></td>
                                        </tr>
                                        <tr>
                                            <td><i class="fas fa-list-ol text-primary me-2"></i>Rows Imported</td>
                                            <td id="rowsImported" class="fw-semibold">0</td>
                                        </tr>
                                        <tr>
                                            <td><i class="fas fa-file-import text-primary me-2"></i>Source</td>
                                            <td class="fw-semibold"><?php echo htmlspecialchars($_SESSION['import_meta']['source'] ?? '—'); ?></td>
                                        </tr>
                                        <tr>
                                            <td><i class="fas fa-calendar-alt text-primary me-2"></i>Uploaded Date</td>
                                            <td class="fw-semibold"><?php echo htmlspecialchars(date('F d, Y', strtotime($_SESSION['import_meta']['uploaded_date'])) ?? '—'); ?></td>
                                        </tr>
                                        <tr>
                                            <td><i class="fas fa-calendar-alt text-primary me-2"></i>Uploaded By</td>
                                            <td class="fw-semibold"><?php echo htmlspecialchars($_SESSION['import_meta']['uploaded_by'] ?? '—'); ?></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Transaction Summary Table -->
                <div class="col-md-9">
                    <div class="card shadow border-0">
                        <div class="card-header bg-danger text-white py-3">
                            <h4 class="mb-0 text-center"><i class="fas fa-chart-line me-2"></i>Cancellation Summary</h4>
                        </div>
                        <div class="card-body">
                            <table class="table table-bordered table-hover align-middle">
                                <thead>
                                    <tr class="bg-danger text-white text-center fw-bold">
                                        <th class="text-center" style="width: 33%">CANCELLED TRANSACTIONS</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td class="border-end">
                                            <div class="row">
                                                <div class="col-6 fw-semibold"><i class="fas fa-calculator text-secondary me-2"></i>TOTAL COUNT</div>
                                                <div class="col-6 text-end fw-bold"><span id="totalCount">0</span></div>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="border-end">
                                            <div class="row">
                                                <div class="col-6 fw-semibold"><i class="fas fa-money-bill-wave text-success me-2"></i>TOTAL PRINCIPAL</div>
                                                <div class="col-6 text-end fw-bold"><span id="totalPrincipal">PHP 0.00</span></div>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="border-end">
                                            <div class="row">
                                                <div class="col-6 fw-semibold"><i class="fas fa-receipt text-danger me-2"></i>TOTAL CHARGE</div>
                                                <div class="col-6 text-end fw-bold"><span id="totalCharge">PHP 0.00</span></div>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="border-end">
                                            <div class="row">
                                                <div class="col-6 fw-semibold"><i class="fas fa-building text-primary me-2"></i>CHARGE TO PARTNER</div>
                                                <div class="col-6 text-end fw-bold"><span id="chargeToPartner">PHP 0.00</span></div>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="border-end">
                                            <div class="row">
                                                <div class="col-6 fw-semibold"><i class="fas fa-user text-info me-2"></i>CHARGE TO CUSTOMER</div>
                                                <div class="col-6 text-end fw-bold"><span id="chargeToCustomer">PHP 0.00</span></div>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- DISPLAYED TBODY TABLE -->
    <script>
        // Rows data injected from PHP
        const rowsData = <?php echo json_encode($rows, JSON_UNESCAPED_UNICODE); ?> || [];

        // Parse numeric string to float. Handles parentheses and currency symbols/commas.
        function parseNumber(value) {
            if (value === null || value === undefined) return 0;
            let s = String(value).trim();
            if (s === '') return 0;
            let negative = false;
            if (/^\(.*\)$/.test(s)) {
                negative = true;
                s = s.replace(/[()]/g, '');
            }
            // Remove any non-digit, non-dot, non-minus characters (commas, currency signs, spaces)
            s = s.replace(/[^0-9.\-]/g, '');
            let n = parseFloat(s);
            if (isNaN(n)) n = 0;
            return negative ? -n : n;
        }

        function formatPHP(amount) {
            return 'PHP ' + Number(amount).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        function computeCancellationSummary() {
            const totalRows = rowsData.length;

            // Column indexes relative to B..Q: B=0, ..., J=8, K=9, L=10, M=11
            const IDX_PRINCIPAL = 8; // J
            const IDX_CHARGE = 9; // K
            const IDX_CHARGE_TO_CUSTOMER = 10; // L
            const IDX_CHARGE_TO_PARTNER = 11; // M

            let sumPrincipal = 0;
            let sumCharge = 0;
            let sumChargeToCustomer = 0;
            let sumChargeToPartner = 0;

            for (let i = 0; i < rowsData.length; i++) {
                const row = rowsData[i] || [];
                sumPrincipal += parseNumber(row[IDX_PRINCIPAL] || '');
                sumCharge += parseNumber(row[IDX_CHARGE] || '');
                sumChargeToCustomer += parseNumber(row[IDX_CHARGE_TO_CUSTOMER] || '');
                sumChargeToPartner += parseNumber(row[IDX_CHARGE_TO_PARTNER] || '');
            }

            // Update DOM
            const elRowsImported = document.getElementById('rowsImported');
            const elTotalCount = document.getElementById('totalCount');
            const elTotalPrincipal = document.getElementById('totalPrincipal');
            const elTotalCharge = document.getElementById('totalCharge');
            const elChargeToPartner = document.getElementById('chargeToPartner');
            const elChargeToCustomer = document.getElementById('chargeToCustomer');

            if (elRowsImported) elRowsImported.textContent = totalRows;
            if (elTotalCount) elTotalCount.textContent = totalRows;
            if (elTotalPrincipal) elTotalPrincipal.textContent = formatPHP(sumPrincipal);
            if (elTotalCharge) elTotalCharge.textContent = formatPHP(sumCharge);
            if (elChargeToPartner) elChargeToPartner.textContent = formatPHP(sumChargeToPartner);
            if (elChargeToCustomer) elChargeToCustomer.textContent = formatPHP(sumChargeToCustomer);
        }

        document.addEventListener('DOMContentLoaded', function() {
            computeCancellationSummary();
        });
    </script>

    
    <script>
        function confirmImport() {
            // Show loading
            document.getElementById('loading-overlay').style.display = 'block';

            fetch(window.location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=confirm_import'
            }).then(r => r.json()).then(resp => {
                document.getElementById('loading-overlay').style.display = 'none';
                if (resp.success) {
                    const insertedCount = resp.inserted || 0;
                    Swal.fire({
                        icon: 'success',
                        title: 'Data Successfully Imported',
                        html: `
                            <div class="text-center">
                                <div class="alert alert-success">
                                    <strong>${insertedCount}</strong> records inserted.
                                </div>
                            </div>
                        `,
                        showConfirmButton: true,
                        confirmButtonText: 'Close',
                        confirmButtonColor: '#28a745',
                        allowOutsideClick: false,
                        allowEscapeKey: false
                    }).then((result) => {
                        if (result.isConfirmed) {
                            // clear temporary/session data on server (reuse cancel_import handler)
                            fetch(window.location.href, {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                body: 'action=cancel_import'
                            }).then(() => {
                                // Redirect after server cleared data
                                window.location.href = '../../dashboard/billspayment/import/billspay-transaction.php';
                            }).catch(() => {
                                // fallback redirect even if AJAX fails
                                window.location.href = '../../dashboard/billspayment/import/billspay-transaction.php';
                            });
                        }
                    });
                } else {
                    Swal.fire({ icon: 'error', title: 'Import Failed', text: resp.error || 'Unknown error' });
                }
            }).catch(err => {
                document.getElementById('loading-overlay').style.display = 'none';
                Swal.fire({ icon: 'error', title: 'Request Failed', text: err.message || 'Network error' });
            });
        }
        function confirmCancel() {
            Swal.fire({
                title: 'Notice',
                text: "Cancelling the process will discard all uploaded data",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, cancel it!',
                cancelButtonText: 'No, continue'
            }).then((result) => {
                if (result.isConfirmed) {
                    // POST to this same endpoint to clear server-side data
                    fetch(window.location.href, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: 'action=cancel_import'
                    }).then(r => r.json()).then(resp => {
                        // Redirect back to import page after server cleared data
                        window.location.href = '../../dashboard/billspayment/import/billspay-cancellation.php';
                    }).catch(err => {
                        // fallback redirect even if AJAX fails
                        window.location.href = '../../dashboard/billspayment/import/billspay-cancellation.php';
                    });
                }
            });
        }
    </script>
</body>
</html>