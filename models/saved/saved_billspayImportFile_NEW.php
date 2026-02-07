<?php
/**
 * saved_billspayImportFile.php - Batch File Validator & Importer
 * 
 * This is a refactored version that supports:
 * - Batch file processing
 * - Two-step validation → confirmation
 * - Clear separation from upload page
 */

include '../../config/config.php';
require '../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

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

// Increase memory and execution time for large batches
ini_set('memory_limit', '512M');
ini_set('max_execution_time', 900);

// ============================================================================
// Handle Cancel Action - Clean up temp files
// ============================================================================
if (isset($_GET['cancel']) && $_GET['cancel'] == '1') {
    // Clean up temp files
    if (isset($_SESSION['uploaded_files'])) {
        foreach ($_SESSION['uploaded_files'] as $file) {
            if (isset($file['path']) && file_exists($file['path'])) {
                unlink($file['path']);
            }
        }
    }
    
    // Clear session
    unset($_SESSION['uploaded_files']);
    unset($_SESSION['batch_upload']);
    
    echo '<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>';
    echo "<script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                icon: 'info',
                title: 'Import Cancelled',
                text: 'All uploaded files have been removed.',
                confirmButtonText: 'OK'
            }).then(() => {
                window.location.href = '../../dashboard/billspayment/import/billspay-transaction.php';
            });
        });
    </script>";
    exit;
}

// ============================================================================
// STEP 1: Handle File Upload and Store in Session
// ============================================================================
if (isset($_POST['upload']) && isset($_FILES['files'])) {
    $uploadedFiles = [];
    $fileCount = count($_FILES['files']['name']);
    
    for ($i = 0; $i < $fileCount; $i++) {
        if ($_FILES['files']['error'][$i] === UPLOAD_ERR_OK) {
            $tmpPath = $_FILES['files']['tmp_name'][$i];
            $fileName = $_FILES['files']['name'][$i];
            $partnerId = $_POST['partner_ids'][$i] ?? '';
            $sourceType = $_POST['source_types'][$i] ?? '';
            
            // Generate unique ID for temp storage
            $fileId = uniqid('file_', true);
            $tempPath = "../../admin/temporary/" . $fileId . "_" . basename($fileName);
            
            // Move uploaded file to temp directory
            if (move_uploaded_file($tmpPath, $tempPath)) {
                // Get partner name from database
                $partnerName = getPartnerName($conn, $partnerId);
                
                $uploadedFiles[] = [
                    'id' => $fileId,
                    'name' => $fileName,
                    'path' => $tempPath,
                    'partner_id' => $partnerId,
                    'partner_name' => $partnerName,
                    'source_type' => $sourceType,
                    'status' => 'pending',
                    'validation_result' => null,
                    'uploaded_by' => $current_user_email,
                    'uploaded_date' => date('Y-m-d H:i:s')
                ];
            }
        }
    }
    
    // Store in session
    $_SESSION['uploaded_files'] = $uploadedFiles;
    $_SESSION['batch_upload'] = true;
    
    // Redirect to validation page (self)
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// ============================================================================
// STEP 2: Perform Validation on Stored Files
// ============================================================================
if (isset($_SESSION['uploaded_files']) && !isset($_POST['perform_import'])) {
    // Run validation on all files
    foreach ($_SESSION['uploaded_files'] as &$file) {
        if ($file['status'] === 'pending') {
            $validationResult = validateFile($conn, $file['path'], $file['source_type'], $file['partner_id']);
            $file['validation_result'] = $validationResult;
            $file['status'] = $validationResult['valid'] ? 'valid' : 'invalid';
        }
    }
    unset($file);
    
    // Update session
    $_SESSION['uploaded_files'] = $_SESSION['uploaded_files'];
}

// ============================================================================
// STEP 3: Handle Import Action
// ============================================================================
if (isset($_POST['perform_import']) && isset($_SESSION['uploaded_files'])) {
    $imported = 0;
    $failed = 0;
    $errors = [];
    
    foreach ($_SESSION['uploaded_files'] as $file) {
        if ($file['status'] === 'valid') {
            try {
                $result = importFileData($conn, $file['path'], $file['source_type'], $file['partner_id'], $current_user_email);
                
                if ($result['success']) {
                    $imported++;
                    // Delete temp file after successful import
                    if (file_exists($file['path'])) {
                        unlink($file['path']);
                    }
                } else {
                    $failed++;
                    $errors[] = "File: " . $file['name'] . " - " . $result['error'];
                }
            } catch (Exception $e) {
                $failed++;
                $errors[] = "File: " . $file['name'] . " - " . $e->getMessage();
            }
        }
    }
    
    // Clear session
    unset($_SESSION['uploaded_files']);
    unset($_SESSION['batch_upload']);
    
    // Show result and redirect
    $errorDetailsJson = json_encode($errors, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    echo '<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>';
    echo "<script>
        document.addEventListener('DOMContentLoaded', function() {
            const errorDetails = $errorDetailsJson || [];
            const hasErrors = errorDetails.length > 0;

            const summaryHtml = hasErrors
                ? 'Import finished with some issues.<br>Successfully imported: {$imported} file(s)<br>Failed: {$failed}'
                : 'Successfully imported: {$imported} file(s)';

            Swal.fire({
                icon: hasErrors ? 'warning' : 'success',
                title: hasErrors ? 'Import Completed with Issues' : 'Import Complete',
                html: summaryHtml,
                showDenyButton: hasErrors,
                denyButtonText: 'View full details',
                confirmButtonText: 'OK',
                reverseButtons: true
            }).then((result) => {
                if (result.isDenied) {
                    const detailList = errorDetails
                        .map((item, index) => '<li><strong>No. ' + (index + 1) + ':</strong> ' + item + '</li>')
                        .join('');

                    const detailHtml =
                        '<div style=\'text-align:left; max-height: 60vh; overflow-y:auto;\'>' +
                            '<p class=\'text-muted\'>Below are the detailed errors found during import.</p>' +
                            '<ul>' + detailList + '</ul>' +
                        '</div>';

                    Swal.fire({
                        icon: 'info',
                        title: 'Import Error Details',
                        html: detailHtml,
                        width: '85%',
                        confirmButtonText: 'Close'
                    }).then(() => {
                        window.location.href = '../../dashboard/billspayment/import/billspay-transaction.php';
                    });
                } else {
                    window.location.href = '../../dashboard/billspayment/import/billspay-transaction.php';
                }
            });
        });
    </script>";
    exit;
}

// ============================================================================
// Helper Functions
// ============================================================================

function getPartnerName($conn, $partnerId) {
    $query = "SELECT partner_name FROM masterdata.partner_masterfile 
              WHERE partner_id = ? OR partner_id_kpx = ? LIMIT 1";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $partnerId, $partnerId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['partner_name'];
    }
    return 'Unknown Partner';
}

function validateFile($conn, $filePath, $sourceType, $partnerId) {
    $errors = [];
    $warnings = [];
    $rowCount = 0;
    $previewData = []; // Store sample rows for preview
    $summaryRows = [];
    $transactionDate = null;
    
    try {
        $spreadsheet = IOFactory::load($filePath);
        $worksheet = $spreadsheet->getActiveSheet();
        $highestRow = $worksheet->getHighestRow();
        $highestColumn = $worksheet->getHighestColumn();
        $columnLabels = [];

        // Read row 9 to get column headers
        $rowIterator = $worksheet->getRowIterator(9, 9)->current();
        $cellIterator = $rowIterator->getCellIterator('A', $highestColumn);
        foreach ($cellIterator as $cell) {
            $columnLabels[] = trim(strval($cell->getValue()));
        }
        
        // Extract transaction date from Column B, Row 9 (identifier) and Row 10 (value)
        $dateTimeLabel = trim(strval($worksheet->getCell('B9')->getValue()));
        if (stripos($dateTimeLabel, 'Date') !== false || stripos($dateTimeLabel, 'Time') !== false) {
            $transactionDate = trim(strval($worksheet->getCell('B10')->getValue()));
        }
        
        // Basic validation
        if ($highestRow < 10) {
            $errors[] = [
                'row' => 'N/A',
                'type' => 'structure',
                'message' => 'File has insufficient data rows',
                'value' => ''
            ];
            return [
                'valid' => false,
                'row_count' => 0,
                'errors' => $errors,
                'warnings' => $warnings,
                'preview_data' => [],
                'transaction_summary' => null
            ];
        }
        
        // Validate partner exists and get partner data
        $partnerData = null;
        if ($partnerId !== 'All') {
            $partnerQuery = "SELECT partner_id, partner_id_kpx, gl_code, partner_name 
                           FROM masterdata.partner_masterfile 
                           WHERE partner_id = ? OR partner_id_kpx = ? LIMIT 1";
            $stmt = $conn->prepare($partnerQuery);
            $stmt->bind_param("ss", $partnerId, $partnerId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result && $result->num_rows > 0) {
                $partnerData = $result->fetch_assoc();
            } else {
                $errors[] = [
                    'row' => 'Header',
                    'type' => 'partner',
                    'message' => 'Partner ID not found in database',
                    'value' => $partnerId
                ];
            }
        }
        
        // Process data rows (starting from row 10)
        for ($row = 10; $row <= $highestRow; ++$row) {
            // Check if row is empty
            $cellA = trim(strval($worksheet->getCell('A' . $row)->getValue()));
            $cellB = trim(strval($worksheet->getCell('B' . $row)->getValue()));
            $cellC = trim(strval($worksheet->getCell('C' . $row)->getValue()));
            
            if (empty($cellA) && empty($cellB) && empty($cellC)) {
                break; // End of data
            }
            
            $rowCount++;
            
            // Extract data for calculations and preview
            $rowData = [];
            $amountPaid = 0;
            $amountChargePartner = 0;
            $amountChargeCustomer = 0;
            $isCancellation = false;
            $referenceNumber = '';
            $numericNumber = null;
            
            if ($sourceType === 'KP7') {
                // KP7 format extraction
                $amountPaid = floatval(str_replace(',', '', $worksheet->getCell('J' . $row)->getValue()));
                $amountChargePartner = floatval(str_replace(',', '', $worksheet->getCell('K' . $row)->getValue()));
                $amountChargeCustomer = floatval(str_replace(',', '', $worksheet->getCell('L' . $row)->getValue()));
                $referenceNumber = trim(strval($worksheet->getCell('E' . $row)->getValue()));
                
                $rowData = [
                    'control_number' => trim(strval($worksheet->getCell('A' . $row)->getValue())),
                    'branch_id' => trim(strval($worksheet->getCell('B' . $row)->getValue())),
                    'transaction_date' => trim(strval($worksheet->getCell('C' . $row)->getValue())),
                    'transaction_time' => trim(strval($worksheet->getCell('D' . $row)->getValue())),
                    'reference_number' => $referenceNumber,
                    'payor_name' => trim(strval($worksheet->getCell('F' . $row)->getValue())),
                    'payor_address' => trim(strval($worksheet->getCell('G' . $row)->getValue())),
                    'account_number' => trim(strval($worksheet->getCell('H' . $row)->getValue())),
                    'account_name' => trim(strval($worksheet->getCell('I' . $row)->getValue())),
                    'amount_paid' => $amountPaid,
                    'service_charge' => $amountChargePartner + $amountChargeCustomer,
                    'total_amount' => $amountPaid + $amountChargePartner + $amountChargeCustomer,
                    'partner_id' => $partnerId,
                    'partner_name' => $partnerData ? $partnerData['partner_name'] : 'Unknown'
                ];
            } elseif ($sourceType === 'KPX') {
                // KPX format extraction (column positions vary)
                $numericNumber = trim(strval($worksheet->getCell('A' . $row)->getValue()));
                $isCancellation = ($numericNumber === '*');

                if (isset($columnLabels[1]) && $columnLabels[1] === 'Date / Time') {
                    // Date/Time is in column B
                    $referenceNumber = trim(strval($worksheet->getCell('D' . $row)->getValue()));
                    $amountPaid = floatval(str_replace(',', '', $worksheet->getCell('I' . $row)->getValue()));
                    $amountChargeCustomer = floatval(str_replace(',', '', $worksheet->getCell('J' . $row)->getValue()));
                    $amountChargePartner = floatval(str_replace(',', '', $worksheet->getCell('K' . $row)->getValue()));

                    $rowData = [
                        'numeric_number' => $numericNumber,
                        'control_number' => trim(strval($worksheet->getCell('C' . $row)->getValue())),
                        'branch_id' => trim(strval($worksheet->getCell('N' . $row)->getValue())),
                        'transaction_date' => trim(strval($worksheet->getCell('B' . $row)->getValue())),
                        'transaction_time' => '',
                        'reference_number' => $referenceNumber,
                        'payor_name' => trim(strval($worksheet->getCell('E' . $row)->getValue())),
                        'payor_address' => trim(strval($worksheet->getCell('F' . $row)->getValue())),
                        'account_number' => trim(strval($worksheet->getCell('G' . $row)->getValue())),
                        'account_name' => trim(strval($worksheet->getCell('H' . $row)->getValue())),
                        'amount_paid' => $amountPaid,
                        'service_charge' => $amountChargePartner + $amountChargeCustomer,
                        'total_amount' => $amountPaid + $amountChargePartner + $amountChargeCustomer,
                        'partner_id' => $partnerId,
                        'partner_name' => $partnerData ? $partnerData['partner_name'] : 'Unknown'
                    ];
                } elseif (isset($columnLabels[2]) && $columnLabels[2] === 'Date / Time') {
                    // Date/Time is in column C
                    $referenceNumber = trim(strval($worksheet->getCell('E' . $row)->getValue()));
                    $amountPaid = floatval(str_replace(',', '', $worksheet->getCell('J' . $row)->getValue()));
                    $amountChargeCustomer = floatval(str_replace(',', '', $worksheet->getCell('K' . $row)->getValue()));
                    $amountChargePartner = floatval(str_replace(',', '', $worksheet->getCell('L' . $row)->getValue()));

                    $rowData = [
                        'numeric_number' => $numericNumber,
                        'control_number' => trim(strval($worksheet->getCell('D' . $row)->getValue())),
                        'branch_id' => trim(strval($worksheet->getCell('O' . $row)->getValue())),
                        'transaction_date' => trim(strval($worksheet->getCell('C' . $row)->getValue())),
                        'transaction_time' => '',
                        'reference_number' => $referenceNumber,
                        'payor_name' => trim(strval($worksheet->getCell('F' . $row)->getValue())),
                        'payor_address' => trim(strval($worksheet->getCell('G' . $row)->getValue())),
                        'account_number' => trim(strval($worksheet->getCell('H' . $row)->getValue())),
                        'account_name' => trim(strval($worksheet->getCell('I' . $row)->getValue())),
                        'amount_paid' => $amountPaid,
                        'service_charge' => $amountChargePartner + $amountChargeCustomer,
                        'total_amount' => $amountPaid + $amountChargePartner + $amountChargeCustomer,
                        'partner_id' => $partnerId,
                        'partner_name' => $partnerData ? $partnerData['partner_name'] : 'Unknown'
                    ];
                } else {
                    // Fallback mapping
                    $referenceNumber = trim(strval($worksheet->getCell('F' . $row)->getValue()));
                    $amountPaid = floatval(str_replace(',', '', $worksheet->getCell('K' . $row)->getValue()));
                    $amountChargeCustomer = floatval(str_replace(',', '', $worksheet->getCell('L' . $row)->getValue()));
                    $amountChargePartner = 0;

                    $rowData = [
                        'numeric_number' => $numericNumber,
                        'control_number' => trim(strval($worksheet->getCell('B' . $row)->getValue())),
                        'branch_id' => trim(strval($worksheet->getCell('C' . $row)->getValue())),
                        'transaction_date' => trim(strval($worksheet->getCell('D' . $row)->getValue())),
                        'transaction_time' => trim(strval($worksheet->getCell('E' . $row)->getValue())),
                        'reference_number' => $referenceNumber,
                        'payor_name' => trim(strval($worksheet->getCell('G' . $row)->getValue())),
                        'payor_address' => trim(strval($worksheet->getCell('H' . $row)->getValue())),
                        'account_number' => trim(strval($worksheet->getCell('I' . $row)->getValue())),
                        'account_name' => trim(strval($worksheet->getCell('J' . $row)->getValue())),
                        'amount_paid' => $amountPaid,
                        'service_charge' => $amountChargePartner + $amountChargeCustomer,
                        'total_amount' => $amountPaid + $amountChargePartner + $amountChargeCustomer,
                        'partner_id' => $partnerId,
                        'partner_name' => $partnerData ? $partnerData['partner_name'] : 'Unknown'
                    ];
                }
            }

            // Store data for transaction summary
            if (!empty($referenceNumber)) {
                $summaryRows[] = [
                    'reference_number' => $referenceNumber,
                    'numeric_number' => $numericNumber,
                    'amount_paid' => $amountPaid,
                    'amount_charge_partner' => $amountChargePartner,
                    'amount_charge_customer' => $amountChargeCustomer
                ];
            }
            
            // Store preview data (first 10 rows only)
            if ($rowCount <= 10) {
                $previewData[] = $rowData;
            }
            
            // Add row-level validation
            if ($sourceType === 'KP7') {
                $referenceNo = $referenceNumber;
                if (empty($referenceNo)) {
                    $errors[] = [
                        'row' => $row,
                        'type' => 'missing_data',
                        'message' => 'Missing reference number',
                        'value' => ''
                    ];
                }
            } elseif ($sourceType === 'KPX') {
                $referenceNo = $referenceNumber;
                if (empty($referenceNo)) {
                    $errors[] = [
                        'row' => $row,
                        'type' => 'missing_data',
                        'message' => 'Missing reference number',
                        'value' => ''
                    ];
                }
            }
        }
        
    } catch (Exception $e) {
        $errors[] = [
            'row' => 'N/A',
            'type' => 'critical',
            'message' => 'File loading error: ' . $e->getMessage(),
            'value' => ''
        ];
    }
    
    // Calculate summaries (match original logic)
    $summaries = [
        'summary' => ['count' => 0, 'principal' => 0, 'charge_partner' => 0, 'charge_customer' => 0, 'total_charge' => 0, 'settlement' => 0],
        'adjustment' => ['count' => 0, 'principal' => 0, 'charge_partner' => 0, 'charge_customer' => 0, 'total_charge' => 0, 'settlement' => 0],
        'net' => ['count' => 0, 'principal' => 0, 'charge_partner' => 0, 'charge_customer' => 0, 'total_charge' => 0, 'settlement' => 0]
    ];

    $cancellationReferences = [];
    foreach ($summaryRows as $row) {
        if (isset($row['numeric_number']) && $row['numeric_number'] === '*') {
            $cancellationReferences[] = $row['reference_number'];
        }
    }
    $cancellationReferences = array_unique($cancellationReferences);

    foreach ($summaryRows as $row) {
        if (!isset($row['numeric_number']) || $row['numeric_number'] !== '*') {
            if (!in_array($row['reference_number'], $cancellationReferences)) {
                $summaries['summary']['count']++;
                $summaries['summary']['principal'] += abs(floatval($row['amount_paid'] ?? 0));
                $summaries['summary']['charge_partner'] += abs(floatval($row['amount_charge_partner'] ?? 0));
                $summaries['summary']['charge_customer'] += abs(floatval($row['amount_charge_customer'] ?? 0));
            }
        }
    }

    foreach ($summaryRows as $row) {
        if (isset($row['numeric_number']) && $row['numeric_number'] === '*') {
            $summaries['adjustment']['count']++;
            $summaries['adjustment']['principal'] += abs(floatval($row['amount_paid'] ?? 0));
            $summaries['adjustment']['charge_partner'] += abs(floatval($row['amount_charge_partner'] ?? 0));
            $summaries['adjustment']['charge_customer'] += abs(floatval($row['amount_charge_customer'] ?? 0));
        }
    }

    $summaries['net']['count'] = $summaries['summary']['count'] - $summaries['adjustment']['count'];
    $summaries['net']['principal'] = $summaries['summary']['principal'] - $summaries['adjustment']['principal'];
    $summaries['net']['charge_partner'] = $summaries['summary']['charge_partner'] - $summaries['adjustment']['charge_partner'];
    $summaries['net']['charge_customer'] = $summaries['summary']['charge_customer'] - $summaries['adjustment']['charge_customer'];

    foreach ($summaries as $key => &$summary) {
        $summary['total_charge'] = $summary['charge_partner'] + $summary['charge_customer'];
        $summary['settlement'] = $summary['principal'] - $summary['charge_partner'] - $summary['charge_customer'];
    }
    unset($summary);
    
    return [
        'valid' => count($errors) === 0,
        'row_count' => $rowCount,
        'errors' => $errors,
        'warnings' => $warnings,
        'preview_data' => $previewData,
        'source_type' => $sourceType,
        'partner_data' => $partnerData,
        'transaction_date' => $transactionDate,
        'transaction_summary' => $summaries
    ];
}

function importFileData($conn, $filePath, $sourceType, $partnerId, $currentUserEmail) {
    try {
        $spreadsheet = IOFactory::load($filePath);
        $worksheet = $spreadsheet->getActiveSheet();
        $highestRow = $worksheet->getHighestRow();
        $highestColumn = $worksheet->getHighestColumn();

        $insertCount = 0;
        $errors = [];

        // Get partner data
        $partnerQuery = "SELECT partner_id, partner_id_kpx, gl_code, partner_name 
                        FROM masterdata.partner_masterfile 
                        WHERE partner_id = ? OR partner_id_kpx = ? LIMIT 1";
        $stmt = $conn->prepare($partnerQuery);
        $stmt->bind_param("ss", $partnerId, $partnerId);
        $stmt->execute();
        $partnerResult = $stmt->get_result();
        $partnerData = $partnerResult->fetch_assoc();
        $stmt->close();

        if (!$partnerData) {
            return [
                'success' => false,
                'error' => 'Partner not found for this file.'
            ];
        }

        $PartnerID = $partnerData['partner_id'];
        $PartnerID_KPX = $partnerData['partner_id_kpx'];
        $GLCode = $partnerData['gl_code'];
        $PartnerName = $partnerData['partner_name'];

        // Read row 9 column headers
        $getColumnLabels = [];
        $columnIterator = $worksheet->getRowIterator(9, 9)->current()->getCellIterator('A', $highestColumn);
        foreach ($columnIterator as $cell) {
            $getColumnLabels[] = trim(strval($cell->getValue()));
        }

        // KP7 report date is stored in cell B3
        $kp7ReportDate = null;
        if ($sourceType === 'KP7') {
            $kp7ReportDate = trim(strval($worksheet->getCell('B3')->getValue()));
        }

        // Helper functions (duplicate and override checks)
        $checkDuplicateData = function($referenceNumber, $datetime) use ($conn) {
            $sql = "SELECT COUNT(*) as count FROM mldb.billspayment_transaction 
                    WHERE post_transaction='posted' AND reference_no = ? 
                    AND (`datetime` = ? OR cancellation_date = ?) LIMIT 1";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sss", $referenceNumber, $datetime, $datetime);
            $stmt->execute();
            $result = $stmt->get_result();
            $duplicate = false;
            if ($result) {
                $row = $result->fetch_assoc();
                $duplicate = ($row && $row['count'] > 0);
            }
            $stmt->close();
            return $duplicate;
        };

        $checkHasAlreadyDataReadyToOverride = function($referenceNumber, $datetime) use ($conn) {
            $sql = "SELECT COUNT(*) as count FROM mldb.billspayment_transaction 
                    WHERE post_transaction='unposted' AND reference_no = ? 
                    AND (`datetime` = ? OR cancellation_date = ?) LIMIT 1";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sss", $referenceNumber, $datetime, $datetime);
            $stmt->execute();
            $result = $stmt->get_result();
            $override = false;
            if ($result) {
                $row = $result->fetch_assoc();
                $override = ($row && $row['count'] > 0);
            }
            $stmt->close();
            return $override;
        };

        $matchedData = [];
        $cancellationData = [];

        // Process each row
        for ($row = 10; $row <= $highestRow; ++$row) {
            $cellA = trim(strval($worksheet->getCell('A' . $row)->getValue()));
            $cellB = trim(strval($worksheet->getCell('B' . $row)->getValue()));
            $cellC = trim(strval($worksheet->getCell('C' . $row)->getValue()));
            $cellD = trim(strval($worksheet->getCell('D' . $row)->getValue()));
            $cellE = trim(strval($worksheet->getCell('E' . $row)->getValue()));

            if (empty($cellA) && empty($cellB) && empty($cellC) && empty($cellD) && empty($cellE)) {
                break;
            }

            $cancellStatus = '';
            $isCancellation = false;
            $datetime = null;
            $reference_number = '';
            $control_number = '';
            $payor_name = '';
            $payor_address = '';
            $account_number = '';
            $account_name = '';
            $amount_paid = 0;
            $amount_charge_partner = 0;
            $amount_charge_customer = 0;
            $contact_number = '';
            $other_details = '';
            $branch_id = null;
            $branch_code = null;
            $branch_outlet = '';
            $region_code = null;
            $zone_code = null;
            $region_description = '';
            $person_operator = '';
            $remote_branch = null;
            $remote_operator = null;
            $report_date = $sourceType === 'KP7' ? $kp7ReportDate : null;

            if ($getColumnLabels[0] === 'STATUS' && $sourceType === 'KP7') {
                $isCancellation = strpos($worksheet->getCell('A' . $row)->getValue(), '*') !== false;
                $cancellStatus = $isCancellation ? '*' : '';

                $datetime_raw = $worksheet->getCell('C' . $row)->getValue();
                if ($datetime_raw) {
                    $datetime = date('Y-m-d H:i:s', strtotime($datetime_raw));
                }
                // Keep report_date from header for KP7 cancellations

                $reference_number = strval($worksheet->getCell('E' . $row)->getValue());
                if (substr($reference_number, 0, 3) === 'BPP') {
                    $branch_code = intval(substr($reference_number, 3, 3));
                } elseif (substr($reference_number, 0, 3) === 'BPX') {
                    $branch_code = intval(substr($reference_number, 3, 3));
                }

                $region_description_raw = strval($worksheet->getCell('P' . $row)->getValue());
                $kp7Query = "SELECT region_code, zone_code FROM masterdata.region_masterfile 
                            WHERE (gl_region = ? OR region_desc_kp7 = ?) LIMIT 1";
                $stmt = $conn->prepare($kp7Query);
                if ($stmt) {
                    $stmt->bind_param("ss", $region_description_raw, $region_description_raw);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    if ($result && $result->num_rows > 0) {
                        $regioncodeData = $result->fetch_assoc();
                        $region_code = $regioncodeData['region_code'] ?? null;
                        $zone_code = $regioncodeData['zone_code'] ?? null;
                    }
                    $stmt->close();
                }

                $kp7Query1 = "SELECT mbp.branch_id FROM masterdata.branch_profile as mbp
                            JOIN masterdata.region_masterfile AS mrm
                            ON mrm.region_code = mbp.region_code
                            WHERE mbp.code = ? AND mrm.region_code = ? AND mrm.zone_code = ? LIMIT 1";
                $stmt = $conn->prepare($kp7Query1);
                if ($stmt) {
                    $stmt->bind_param("iss", $branch_code, $region_code, $zone_code);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    if ($result && $result->num_rows > 0) {
                        $branchIDData = $result->fetch_assoc();
                        $branch_id = $branchIDData['branch_id'] ?? null;
                    }
                    $stmt->close();
                }

                $control_number = strval($worksheet->getCell('D' . $row)->getValue());
                $payor_name = strval($worksheet->getCell('F' . $row)->getValue());
                $payor_address = strval($worksheet->getCell('G' . $row)->getValue());
                $account_number = strval($worksheet->getCell('H' . $row)->getValue());
                $account_name = strval($worksheet->getCell('I' . $row)->getValue());
                $amount_paid = floatval(str_replace(',', '', $worksheet->getCell('J' . $row)->getValue()));
                $amount_charge_partner = floatval(str_replace(',', '', $worksheet->getCell('K' . $row)->getValue()));
                $amount_charge_customer = floatval(str_replace(',', '', $worksheet->getCell('L' . $row)->getValue()));
                $contact_number = strval($worksheet->getCell('M' . $row)->getValue());
                $other_details = strval($worksheet->getCell('N' . $row)->getValue());
                $branch_outlet = strval($worksheet->getCell('O' . $row)->getValue());
                $region_description = $region_description_raw;
                $person_operator = strval($worksheet->getCell('Q' . $row)->getValue());

            } elseif ($getColumnLabels[0] === 'No' && $sourceType === 'KPX') {
                $isCancellation = strpos($worksheet->getCell('A' . $row)->getValue(), '*') !== false;
                $cancellStatus = $isCancellation ? '*' : '';

                if (isset($getColumnLabels[1]) && $getColumnLabels[1] === 'Date / Time') {
                    $datetime_raw = $worksheet->getCell('B' . $row)->getValue();
                    if ($datetime_raw) {
                        $datetime = date('Y-m-d H:i:s', strtotime($datetime_raw));
                    }

                    $control_number = strval($worksheet->getCell('C' . $row)->getValue());
                    $reference_number = strval($worksheet->getCell('D' . $row)->getValue());
                    $payor_name = strval($worksheet->getCell('E' . $row)->getValue());
                    $payor_address = strval($worksheet->getCell('F' . $row)->getValue());
                    $account_number = strval($worksheet->getCell('G' . $row)->getValue());
                    $account_name = strval($worksheet->getCell('H' . $row)->getValue());
                    $amount_paid = floatval(str_replace(',', '', $worksheet->getCell('I' . $row)->getValue()));
                    $amount_charge_customer = floatval(str_replace(',', '', $worksheet->getCell('J' . $row)->getValue()));
                    $amount_charge_partner = floatval(str_replace(',', '', $worksheet->getCell('K' . $row)->getValue()));
                    $contact_number = strval($worksheet->getCell('L' . $row)->getValue());
                    $other_details = strval($worksheet->getCell('M' . $row)->getValue());

                    $branch_id_raw = $worksheet->getCell('N' . $row)->getValue();
                    $branch_outlet_raw = strval($worksheet->getCell('O' . $row)->getValue());
                    if (isset($getColumnLabels[13]) && $getColumnLabels[13] === 'Branch ID') {
                        if (is_numeric($branch_id_raw)) {
                            $cntl_num_for_region = ($branch_id_raw == 581) ? intval(2607) : intval($branch_id_raw);
                        } elseif ($branch_id_raw === 'HEAD OFFICE') {
                            $cntl_num_for_region = intval(2607);
                        }
                        if ($branch_outlet_raw === 'HEAD OFFICE' || $branch_outlet_raw === 'ML CEBU HEAD OFFICE') {
                            $cntl_num_for_region = intval(2607);
                        }
                        $branch_id = $cntl_num_for_region;

                        $kpxbranchcodeQuery = "SELECT code FROM masterdata.branch_profile where branch_id = ? LIMIT 1";
                        $stmt = $conn->prepare($kpxbranchcodeQuery);
                        if ($stmt) {
                            $stmt->bind_param("i", $cntl_num_for_region);
                            $stmt->execute();
                            $result = $stmt->get_result();
                            if ($result && $result->num_rows > 0) {
                                $branchCodeData = $result->fetch_assoc();
                                $branch_code = $branchCodeData['code'] ?? null;
                            }
                            $stmt->close();
                        }

                        $region_description = strval($worksheet->getCell('Q' . $row)->getValue());
                        $kpxregioncodeQuery1 = "SELECT region_code, zone_code FROM masterdata.region_masterfile
                                                WHERE (gl_region = ? OR region_desc_kpx = ?) LIMIT 1";
                        $stmt = $conn->prepare($kpxregioncodeQuery1);
                        if ($stmt) {
                            $stmt->bind_param("ss", $region_description, $region_description);
                            $stmt->execute();
                            $result = $stmt->get_result();
                            if ($result && $result->num_rows > 0) {
                                $regioncodeData = $result->fetch_assoc();
                                $region_code = $regioncodeData['region_code'] ?? null;
                                $zone_code = $regioncodeData['zone_code'] ?? null;
                            }
                            $stmt->close();
                        }

                        $person_operator = strval($worksheet->getCell('R' . $row)->getValue());
                        $remote_branch = strval($worksheet->getCell('S' . $row)->getValue());
                        $remote_operator = strval($worksheet->getCell('T' . $row)->getValue());
                        $branch_outlet = $branch_outlet_raw;
                    }
                } elseif (isset($getColumnLabels[2]) && $getColumnLabels[2] === 'Date / Time') {
                    $datetime_raw = $worksheet->getCell('C' . $row)->getValue();
                    if ($datetime_raw) {
                        $datetime = date('Y-m-d H:i:s', strtotime($datetime_raw));
                    }

                    $control_number = strval($worksheet->getCell('D' . $row)->getValue());
                    $reference_number = strval($worksheet->getCell('E' . $row)->getValue());
                    $payor_name = strval($worksheet->getCell('F' . $row)->getValue());
                    $payor_address = strval($worksheet->getCell('G' . $row)->getValue());
                    $account_number = strval($worksheet->getCell('H' . $row)->getValue());
                    $account_name = strval($worksheet->getCell('I' . $row)->getValue());
                    $amount_paid = floatval(str_replace(',', '', $worksheet->getCell('J' . $row)->getValue()));
                    $amount_charge_customer = floatval(str_replace(',', '', $worksheet->getCell('K' . $row)->getValue()));
                    $amount_charge_partner = floatval(str_replace(',', '', $worksheet->getCell('L' . $row)->getValue()));
                    $contact_number = strval($worksheet->getCell('M' . $row)->getValue());
                    $other_details = strval($worksheet->getCell('N' . $row)->getValue());

                    $branch_id_raw = $worksheet->getCell('O' . $row)->getValue();
                    if (isset($getColumnLabels[14]) && $getColumnLabels[14] === 'Branch ID') {
                        if (is_numeric($branch_id_raw)) {
                            $cntl_num_for_region = ($branch_id_raw == 581) ? intval(2607) : intval($branch_id_raw);
                        } elseif ($branch_id_raw === 'HEAD OFFICE') {
                            $cntl_num_for_region = intval(2607);
                        }
                        $branch_id = $cntl_num_for_region;

                        $kpxbranchcodeQuery = "SELECT code FROM masterdata.branch_profile where branch_id = ? LIMIT 1";
                        $stmt = $conn->prepare($kpxbranchcodeQuery);
                        if ($stmt) {
                            $stmt->bind_param("i", $cntl_num_for_region);
                            $stmt->execute();
                            $result = $stmt->get_result();
                            if ($result && $result->num_rows > 0) {
                                $branchCodeData = $result->fetch_assoc();
                                $branch_code = $branchCodeData['code'] ?? null;
                            }
                            $stmt->close();
                        }

                        $branch_outlet = strval($worksheet->getCell('N' . $row)->getValue());
                        $region_description = strval($worksheet->getCell('O' . $row)->getValue());
                        $kpxregioncodeQuery1 = "SELECT region_code, zone_code FROM masterdata.region_masterfile
                                                WHERE (gl_region = ? OR region_desc_kpx = ?) LIMIT 1";
                        $stmt = $conn->prepare($kpxregioncodeQuery1);
                        if ($stmt) {
                            $stmt->bind_param("ss", $region_description, $region_description);
                            $stmt->execute();
                            $result = $stmt->get_result();
                            if ($result && $result->num_rows > 0) {
                                $regioncodeData = $result->fetch_assoc();
                                $region_code = $regioncodeData['region_code'] ?? null;
                                $zone_code = $regioncodeData['zone_code'] ?? null;
                            }
                            $stmt->close();
                        }

                        $person_operator = strval($worksheet->getCell('P' . $row)->getValue());
                        $remote_branch = strval($worksheet->getCell('Q' . $row)->getValue());
                        $remote_operator = strval($worksheet->getCell('R' . $row)->getValue());
                    }
                }
            } else {
                continue;
            }

            if (empty($reference_number) || empty($datetime)) {
                continue;
            }

            if ($checkDuplicateData($reference_number, $datetime)) {
                $errors[] = "Duplicate reference found: {$reference_number}";
                continue;
            }

            if ($checkHasAlreadyDataReadyToOverride($reference_number, $datetime)) {
                $errors[] = "Unposted data exists for reference: {$reference_number}";
                continue;
            }

            $rowData = [
                'numeric_number' => $cancellStatus,
                'datetime' => $datetime,
                'report_date' => $report_date,
                'control_number' => $control_number,
                'reference_number' => $reference_number,
                'payor_name' => $payor_name,
                'payor_address' => $payor_address,
                'account_number' => $account_number,
                'account_name' => $account_name,
                'amount_paid' => $amount_paid,
                'amount_charge_partner' => $amount_charge_partner,
                'amount_charge_customer' => $amount_charge_customer,
                'contact_number' => $contact_number,
                'other_details' => $other_details,
                'branch_id' => $branch_id,
                'branch_code' => $branch_code,
                'branch_outlet' => $branch_outlet,
                'zone_code' => $zone_code,
                'region_code' => $region_code,
                'region_description' => $region_description,
                'person_operator' => $person_operator,
                'partner_name' => $PartnerName,
                'partner_id' => $PartnerID,
                'PartnerID_KPX' => $PartnerID_KPX,
                'GLCode' => $GLCode,
                'imported_by' => $_SESSION['admin_name'] ?? $_SESSION['user_name'] ?? $currentUserEmail,
                'date_uploaded' => date('Y-m-d'),
                'remote_branch' => $remote_branch,
                'remote_operator' => $remote_operator,
                'post_transaction' => 'unposted'
            ];

            if ($cancellStatus === '*') {
                $cancellationData[] = $rowData;
            } else {
                $matchedData[] = $rowData;
            }
        }

        if (!empty($errors)) {
            return [
                'success' => false,
                'error' => implode('; ', array_slice($errors, 0, 5))
            ];
        }

        $raw_matched_data = array_merge($matchedData, $cancellationData);
        $processed_data = [];
        $cancellation_refs = [];
        $regular_refs = [];

        if ($sourceType === 'KP7') {
            $processed_data = $raw_matched_data;
        } elseif ($sourceType === 'KPX') {
            foreach ($raw_matched_data as $row) {
                $is_cancellation = isset($row['numeric_number']) && $row['numeric_number'] === '*';
                if ($is_cancellation) {
                    $cancellation_refs[$row['reference_number']] = $row;
                } else {
                    $regular_refs[$row['reference_number']] = $row;
                }
            }

            foreach ($cancellation_refs as $ref_no => $cancellation_row) {
                if (isset($regular_refs[$ref_no])) {
                    $merged_row = $cancellation_row;
                    $merged_row['regular_datetime'] = $regular_refs[$ref_no]['datetime'];
                    $processed_data[] = $merged_row;
                } else {
                    $processed_data[] = $cancellation_row;
                }
            }

            foreach ($regular_refs as $regular_row) {
                $processed_data[] = $regular_row;
            }
        }

        // Insert processed data
        $conn->autocommit(FALSE);

        $insertSQL = "INSERT INTO mldb.billspayment_transaction (
            status, 
            datetime, 
            cancellation_date, 
            source_file, 
            control_no, 
            reference_no, 
            payor, 
            address, 
            account_no, 
            account_name, 
            amount_paid, 
            charge_to_partner, 
            charge_to_customer, 
            contact_no, 
            other_details, 
            branch_id, 
            branch_code,
            outlet, 
            zone_code,
            region_code, 
            region, 
            operator, 
            partner_name, 
            partner_id, 
            partner_id_kpx,
            mpm_gl_code,
            settle_unsettle, 
            claim_unclaim, 
            imported_by, 
            imported_date, 
            rfp_no, 
            cad_no, 
            hold_status, 
            remote_branch, 
            remote_operator, 
            post_transaction
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $insertStmt = $conn->prepare($insertSQL);

        foreach ($processed_data as $row) {
            $is_cancellation = isset($row['numeric_number']) && $row['numeric_number'] === '*';
            $status = $is_cancellation ? '*' : null;

            $datetime_value = null;
            $cancellation_date = null;

            if ($is_cancellation && isset($row['regular_datetime'])) {
                $datetime_value = $row['regular_datetime'];
                $cancellation_date = $row['datetime'];
            } elseif ($is_cancellation) {
                if ($sourceType === 'KP7') {
                    $datetime_value = $row['datetime'];
                    $cancellation_date = $row['report_date'] ? date('Y-m-d H:i:s', strtotime($row['report_date'])) : null;
                } elseif ($sourceType === 'KPX') {
                    $cancellation_date = $row['datetime'];
                    $datetime_value = null;
                }
            } else {
                $datetime_value = $row['datetime'];
                $cancellation_date = null;
            }

            $settle_unsettle = null;
            $claim_unclaim = null;
            $rfp_no = null;
            $cad_no = null;
            $hold_status = null;

            $insertStmt->bind_param(
                "ssssssssssdddssissssssssssssssssssss",
                $status,
                $datetime_value,
                $cancellation_date,
                $sourceType,
                $row['control_number'],
                $row['reference_number'],
                $row['payor_name'],
                $row['payor_address'],
                $row['account_number'],
                $row['account_name'],
                $row['amount_paid'],
                $row['amount_charge_partner'],
                $row['amount_charge_customer'],
                $row['contact_number'],
                $row['other_details'],
                $row['branch_id'],
                $row['branch_code'],
                $row['branch_outlet'],
                $row['zone_code'],
                $row['region_code'],
                $row['region_description'],
                $row['person_operator'],
                $row['partner_name'],
                $row['partner_id'],
                $row['PartnerID_KPX'],
                $row['GLCode'],
                $settle_unsettle,
                $claim_unclaim,
                $row['imported_by'],
                $row['date_uploaded'],
                $rfp_no,
                $cad_no,
                $hold_status,
                $row['remote_branch'],
                $row['remote_operator'],
                $row['post_transaction']
            );

            if (!$insertStmt->execute()) {
                throw new Exception("Insert failed for reference: {$row['reference_number']} - {$insertStmt->error}");
            }

            $insertCount++;
        }

        $insertStmt->close();
        $conn->commit();
        $conn->autocommit(TRUE);

        return [
            'success' => true,
            'inserted' => $insertCount
        ];

    } catch (Exception $e) {
        $conn->rollback();
        $conn->autocommit(TRUE);
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Validate & Import | BillsPayment</title>
    <link rel="stylesheet" href="../../assets/css/templates/style.css">
    <script src="https://kit.fontawesome.com/30b908cc5a.js" crossorigin="anonymous"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>
    <style>
        .validation-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(450px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }
        
        .file-validation-card {
            border: 2px solid #dee2e6;
            border-radius: 10px;
            padding: 20px;
            background: white;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }
        
        .file-validation-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .file-validation-card.valid {
            border-color: #28a745;
            background-color: #f0fff4;
        }
        
        .file-validation-card.invalid {
            border-color: #dc3545;
            background-color: #fff5f5;
        }
        
        .file-validation-card.pending {
            border-color: #ffc107;
            background-color: #fffbf0;
        }
        
        .status-badge {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
        }
        
        .status-valid { background: #28a745; color: white; }
        .status-invalid { background: #dc3545; color: white; }
        .status-pending { background: #ffc107; color: black; }
        
        .badge-source {
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 13px;
            font-weight: 600;
        }
        
        .badge-kpx { background-color: #0d6efd; color: white; }
        .badge-kp7 { background-color: #198754; color: white; }
        .badge-unknown { background-color: #6c757d; color: white; }
        
        .error-list {
            max-height: 150px;
            overflow-y: auto;
            font-size: 13px;
        }
        
        .action-buttons {
            text-align: center;
            margin: 40px 0;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
        }
        
        .btn-import {
            min-width: 200px;
            font-size: 18px;
            padding: 12px 30px;
        }
        
        /* Wide modal for detailed view */
        .swal-wide {
            width: 90% !important;
            max-width: 1400px !important;
        }
        
        .swal2-html-container {
            max-height: 70vh;
            overflow-y: auto;
        }
        
        /* Badge styles for status display */
        .badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .badge-valid {
            background-color: #28a745;
            color: white;
        }
        
        .badge-invalid {
            background-color: #dc3545;
            color: white;
        }
        
        .badge-pending {
            background-color: #ffc107;
            color: black;
        }
        
        /* Table styles in modal */
        .swal2-html-container table {
            margin-bottom: 0;
            font-size: 13px;
        }
        
        .swal2-html-container .table-sm th,
        .swal2-html-container .table-sm td {
            padding: 6px 10px;
            vertical-align: middle;
        }
        
        .swal2-html-container .table-striped tbody tr:nth-of-type(odd) {
            background-color: rgba(0,0,0,.02);
        }
        
        /* Scrollable containers */
        .swal2-html-container .alert {
            text-align: left;
            font-size: 13px;
        }
        
        .swal2-html-container ul {
            padding-left: 20px;
        }
        
        /* Transaction Summary specific styles */
        .transaction-summary-table {
            border-collapse: separate;
            border-spacing: 0;
        }
        
        .transaction-summary-table thead th {
            background: #dc3545 !important;
            color: white !important;
            font-weight: 700;
            padding: 12px !important;
            border: 1px solid #dee2e6;
        }
        
        .transaction-summary-table tbody td {
            padding: 10px !important;
            border: 1px solid #dee2e6;
        }
        
        .transaction-summary-table tbody tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        
        .summary-icon {
            margin-right: 8px;
        }
    </style>
</head>
<body>
    <div class="main-container">
        <div class="top-content">
            <div class="nav-container">
                <i id="menu-btn" class="fa-solid fa-bars"></i>
                <div class="usernav">
                    <h6><?php echo $_SESSION['user_type'] === 'admin' ? $_SESSION['admin_name'] : $_SESSION['user_name']; ?></h6>
                </div>
            </div>
        </div>
        
        <?php include '../../templates/sidebar.php'; ?>
        
        <div class="content-wrapper p-4">
            <center><h2>File Validation & Import</h2></center>
            
            <?php if (isset($_SESSION['uploaded_files']) && count($_SESSION['uploaded_files']) > 0): ?>
                
                <div class="validation-container">
                    <?php foreach ($_SESSION['uploaded_files'] as $file): ?>
                        <div class="file-validation-card <?php echo $file['status']; ?>">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div>
                                    <h5 class="mb-1"><?php echo htmlspecialchars($file['name']); ?></h5>
                                    <small class="text-muted"><?php echo $file['partner_name']; ?></small>
                                </div>
                                <span class="status-badge status-<?php echo $file['status']; ?>">
                                    <?php echo $file['status']; ?>
                                </span>
                            </div>
                            
                            <div class="mb-3">
                                <div class="row">
                                    <div class="col-6">
                                        <small class="text-muted d-block">Partner ID</small>
                                        <strong><?php echo $file['partner_id']; ?></strong>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted d-block">Source Type</small>
                                        <span class="badge-source badge-<?php echo strtolower($file['source_type']); ?>">
                                            <?php echo $file['source_type']; ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if ($file['validation_result']): ?>
                                <div class="mb-2">
                                    <small class="text-muted">Rows Found:</small>
                                    <strong><?php echo $file['validation_result']['row_count']; ?></strong>
                                </div>
                                
                                <?php if (!empty($file['validation_result']['errors'])): ?>
                                    <div class="alert alert-danger p-2 error-list">
                                        <strong>Validation Errors:</strong>
                                        <ul class="mb-0 mt-1">
                                            <?php foreach ($file['validation_result']['errors'] as $error): ?>
                                                <li>Row <?php echo $error['row']; ?>: <?php echo htmlspecialchars($error['message']); ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($file['validation_result']['warnings'])): ?>
                                    <div class="alert alert-warning p-2 error-list">
                                        <strong>Warnings:</strong>
                                        <ul class="mb-0 mt-1">
                                            <?php foreach ($file['validation_result']['warnings'] as $warning): ?>
                                                <li><?php echo htmlspecialchars($warning['message']); ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                            
                            <div class="btn-group mt-2" role="group">
                                <button class="btn btn-sm btn-info" onclick="viewDetails('<?php echo $file['id']; ?>')">
                                    <i class="fa-solid fa-eye"></i> View Details
                                </button>
                                <button class="btn btn-sm btn-success" onclick="viewTransactionSummary('<?php echo $file['id']; ?>')">
                                    <i class="fa-solid fa-chart-bar"></i> Transaction Summary
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Action Buttons -->
                <div class="action-buttons">
                    <?php 
                    $validFiles = array_filter($_SESSION['uploaded_files'], function($f) {
                        return $f['status'] === 'valid';
                    });
                    $validCount = count($validFiles);
                    ?>
                    
                    <?php if ($validCount > 0): ?>
                        <form method="post" style="display: inline;">
                            <button type="submit" name="perform_import" class="btn btn-success btn-lg btn-import">
                                <i class="fa-solid fa-file-import me-2"></i>
                                <?php echo $validCount === 1 ? 'Import File' : 'Import All (' . $validCount . ')'; ?>
                            </button>
                        </form>
                    <?php else: ?>
                        <div class="alert alert-warning d-inline-block">
                            <i class="fa-solid fa-exclamation-triangle me-2"></i>
                            No valid files to import. Please fix errors and try again.
                        </div>
                    <?php endif; ?>
                    
                    <button class="btn btn-secondary btn-lg ms-3" onclick="confirmCancel()">
                        <i class="fa-solid fa-times me-2"></i> Cancel
                    </button>
                </div>
                
            <?php else: ?>
                <div class="alert alert-info text-center mt-5">
                    <h4>No files uploaded</h4>
                    <p>Please go back to the upload page and select files to import.</p>
                    <a href="../../dashboard/billspayment/import/billspay-transaction.php" class="btn btn-primary">
                        Go to Upload Page
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // Store file data for JavaScript access
        const filesData = <?php echo json_encode($_SESSION['uploaded_files'] ?? []); ?>;
        
        function viewDetails(fileId) {
            // Find the file data
            const fileData = filesData.find(f => f.id === fileId);
            
            if (!fileData) {
                console.error('File not found with ID:', fileId);
                Swal.fire({
                    title: 'Error',
                    text: 'File data not found',
                    icon: 'error'
                });
                return;
            }
            
            if (!fileData.validation_result) {
                console.error('Validation result missing for file:', fileData);
                Swal.fire({
                    title: 'Error',
                    text: 'Validation data not available for this file',
                    icon: 'error'
                });
                return;
            }
            
            const validation = fileData.validation_result;
            const previewData = validation.preview_data || [];
            const sourceType = validation.source_type || fileData.source_type || 'Unknown';
            const partnerData = validation.partner_data || {};
            const transactionDate = validation.transaction_date || 'N/A';
            
            console.log('Displaying details for:', {
                fileName: fileData.name,
                sourceType: sourceType,
                previewRows: previewData.length,
                totalRows: validation.row_count
            });
            
            // Build HTML for the modal
            let html = `
                <div style="text-align: left; max-height: 600px; overflow-y: auto;">
                    <div class="mb-3">
                        <h5>File Information</h5>
                        <table class="table table-sm table-bordered">
                            <tr><th width="30%">File Name:</th><td>${fileData.name || 'N/A'}</td></tr>
                            <tr><th>Partner:</th><td>${fileData.partner_name || 'N/A'}</td></tr>
                            <tr><th>KP7 Partner ID:</th><td>${partnerData.partner_id || 'N/A'}</td></tr>
                            <tr><th>KPX Partner ID:</th><td>${partnerData.partner_id_kpx || 'N/A'}</td></tr>
                            <tr><th>GL Code:</th><td>${partnerData.gl_code || 'N/A'}</td></tr>
                            <tr><th>Source Type:</th><td><span class="badge badge-${(sourceType || 'unknown').toLowerCase()}">${sourceType}</span></td></tr>
                            <tr><th>Transaction Date:</th><td>${transactionDate}</td></tr>
                            <tr><th>Total Rows:</th><td>${validation.row_count || 0}</td></tr>
                            <tr><th>Status:</th><td><span class="badge badge-${fileData.status || 'pending'}">${(fileData.status || 'pending').toUpperCase()}</span></td></tr>
                        </table>
                    </div>`;
            
            // Show errors if any
            if (validation.errors && validation.errors.length > 0) {
                html += `
                    <div class="mb-3">
                        <h5 class="text-danger"><i class="fa-solid fa-exclamation-circle"></i> Errors (${validation.errors.length})</h5>
                        <div class="alert alert-danger" style="max-height: 200px; overflow-y: auto;">
                            <ul class="mb-0">`;
                validation.errors.forEach(err => {
                    html += `<li><strong>Row ${err.row}:</strong> ${err.message} ${err.value ? '(Value: ' + err.value + ')' : ''}</li>`;
                });
                html += `</ul></div></div>`;
            }
            
            // Show warnings if any
            if (validation.warnings && validation.warnings.length > 0) {
                html += `
                    <div class="mb-3">
                        <h5 class="text-warning"><i class="fa-solid fa-exclamation-triangle"></i> Warnings (${validation.warnings.length})</h5>
                        <div class="alert alert-warning" style="max-height: 200px; overflow-y: auto;">
                            <ul class="mb-0">`;
                validation.warnings.forEach(warn => {
                    html += `<li><strong>Row ${warn.row}:</strong> ${warn.message}</li>`;
                });
                html += `</ul></div></div>`;
            }
            
            // Show preview data
            if (previewData.length > 0) {
                html += `
                    <div class="mb-3">
                        <h5><i class="fa-solid fa-table"></i> Data Preview (First 10 rows)</h5>
                        <div style="overflow-x: auto; max-height: 400px;">
                            <table class="table table-sm table-striped table-bordered">
                                <thead class="table-dark">
                                    <tr>`;
                
                // Dynamic headers based on source type
                if (sourceType === 'KP7' || sourceType === 'kp7') {
                    html += `
                                        <th>Control #</th>
                                        <th>Branch ID</th>
                                        <th>Trans Date</th>
                                        <th>Trans Time</th>
                                        <th>Reference #</th>
                                        <th>Payor Name</th>
                                        <th>Account #</th>
                                        <th>Amount Paid</th>
                                        <th>Service Charge</th>
                                        <th>Total Amount</th>`;
                } else if (sourceType === 'KPX' || sourceType === 'kpx') {
                    html += `
                                        <th>Status</th>
                                        <th>Control #</th>
                                        <th>Branch ID</th>
                                        <th>Trans Date</th>
                                        <th>Trans Time</th>
                                        <th>Reference #</th>
                                        <th>Payor Name</th>
                                        <th>Account #</th>
                                        <th>Amount Paid</th>
                                        <th>Service Charge</th>
                                        <th>Total Amount</th>`;
                } else {
                    // Unknown source type - generic headers
                    html += `
                                        <th>Column 1</th>
                                        <th>Column 2</th>
                                        <th>Column 3</th>
                                        <th>Column 4</th>
                                        <th>Column 5</th>
                                        <th>Column 6</th>
                                        <th>Column 7</th>
                                        <th>Column 8</th>
                                        <th>Column 9</th>
                                        <th>Column 10</th>`;
                }
                
                html += `</tr></thead><tbody>`;
                
                // Data rows
                previewData.forEach(row => {
                    if (!row) return; // Skip null/undefined rows
                    
                    html += '<tr>';
                    
                    if (sourceType === 'KP7' || sourceType === 'kp7') {
                        html += `
                            <td>${row.control_number || ''}</td>
                            <td>${row.branch_id || ''}</td>
                            <td>${row.transaction_date || ''}</td>
                            <td>${row.transaction_time || ''}</td>
                            <td>${row.reference_number || ''}</td>
                            <td>${row.payor_name || ''}</td>
                            <td>${row.account_number || ''}</td>
                            <td>₱${parseFloat(row.amount_paid || 0).toFixed(2)}</td>
                            <td>₱${parseFloat(row.service_charge || 0).toFixed(2)}</td>
                            <td>₱${parseFloat(row.total_amount || 0).toFixed(2)}</td>`;
                    } else if (sourceType === 'KPX' || sourceType === 'kpx') {
                        const isCancellation = row.numeric_number === '*';
                        html += `
                            <td>${isCancellation ? '<span class="badge bg-danger">CANCEL</span>' : '<span class="badge bg-success">REGULAR</span>'}</td>
                            <td>${row.control_number || ''}</td>
                            <td>${row.branch_id || ''}</td>
                            <td>${row.transaction_date || ''}</td>
                            <td>${row.transaction_time || ''}</td>
                            <td>${row.reference_number || ''}</td>
                            <td>${row.payor_name || ''}</td>
                            <td>${row.account_number || ''}</td>
                            <td>₱${parseFloat(row.amount_paid || 0).toFixed(2)}</td>
                            <td>₱${parseFloat(row.service_charge || 0).toFixed(2)}</td>
                            <td>₱${parseFloat(row.total_amount || 0).toFixed(2)}</td>`;
                    } else {
                        // Unknown source type - display raw data
                        html += `<td colspan="10" class="text-muted">Data format not recognized</td>`;
                    }
                    
                    html += '</tr>';
                });
                
                html += `</tbody></table></div>`;
                
                if (validation.row_count > 10) {
                    html += `<p class="text-muted"><small><i class="fa-solid fa-info-circle"></i> Showing first 10 of ${validation.row_count} total rows</small></p>`;
                }
                
                html += `</div>`;
            } else {
                // No preview data available
                html += `
                    <div class=\"alert alert-info\">
                        <i class=\"fa-solid fa-info-circle\"></i> No preview data available. 
                        ${validation.row_count > 0 ? 'File contains ' + validation.row_count + ' rows.' : 'File validation in progress.'}
                    </div>`;
            }
            
            html += '</div>';
            
            // Display the modal
            Swal.fire({
                title: '<strong>File Details: ' + fileData.name + '</strong>',
                html: html,
                width: '90%',
                showCloseButton: true,
                confirmButtonText: 'Close',
                customClass: {
                    container: 'swal-wide'
                }
            });
        }
        
        function viewTransactionSummary(fileId) {
            // Find the file data
            const fileData = filesData.find(f => f.id === fileId);
            
            if (!fileData) {
                Swal.fire({
                    title: 'Error',
                    text: 'File data not found',
                    icon: 'error'
                });
                return;
            }
            
            if (!fileData.validation_result || !fileData.validation_result.transaction_summary) {
                Swal.fire({
                    title: 'Error',
                    text: 'Transaction summary not available for this file',
                    icon: 'error'
                });
                return;
            }
            
            const validation = fileData.validation_result;
            const summary = validation.transaction_summary || {};
            const summaryData = summary.summary || { count: 0, principal: 0, charge_partner: 0, charge_customer: 0, total_charge: 0 };
            const adjustmentData = summary.adjustment || { count: 0, principal: 0, charge_partner: 0, charge_customer: 0, total_charge: 0 };
            const netData = summary.net || { count: 0, principal: 0, charge_partner: 0, charge_customer: 0, total_charge: 0 };
            const partnerData = validation.partner_data || {};
            const transactionDate = validation.transaction_date || 'N/A';
            const sourceType = validation.source_type || fileData.source_type || 'Unknown';
            
            // Format currency function
            function formatCurrency(amount) {
                return '₱ ' + parseFloat(amount || 0).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
            }
            
            // Get uploaded by and date from file data
            const uploadedBy = fileData.uploaded_by || 'Unknown';
            const uploadedDateRaw = fileData.uploaded_date || new Date().toISOString();
            const uploadedDate = new Date(uploadedDateRaw).toLocaleDateString('en-US', { 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
            const totalRowsUploaded = (summaryData.count || 0) + (adjustmentData.count || 0);
            
            // Build HTML for transaction summary
            let html = `
                <div style="text-align: left; max-height: 700px; overflow-y: auto;">
                    <!-- Transaction Summary Table -->
                    <div class="mb-4" style="background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); padding: 15px; border-radius: 10px;">
                        <h4 class="text-white text-center mb-0"><i class="fa-solid fa-chart-line"></i> Transaction Summary</h4>
                    </div>
                    
                    <div class="table-responsive mb-4">
                        <table class="table table-bordered transaction-summary-table" style="font-size: 14px;">
                            <thead style="background-color: #dc3545; color: white;">
                                <tr>
                                    <th class="text-center">SUMMARY</th>
                                    <th class="text-center">CANCELLED TRANSACTIONS</th>
                                    <th class="text-center">NET</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><i class="fa-solid fa-calculator"></i> <strong>TOTAL COUNT</strong><span class="float-end"><strong>${summaryData.count}</strong></span></td>
                                    <td><i class="fa-solid fa-calculator"></i> <strong>TOTAL COUNT</strong><span class="float-end"><strong>${adjustmentData.count}</strong></span></td>
                                    <td><i class="fa-solid fa-calculator"></i> <strong>TOTAL COUNT</strong><span class="float-end"><strong>${netData.count}</strong></span></td>
                                </tr>
                                <tr>
                                    <td><i class="fa-solid fa-money-bill" style="color: green;"></i> <strong>TOTAL PRINCIPAL</strong><span class="float-end"><strong>${formatCurrency(summaryData.principal)}</strong></span></td>
                                    <td><i class="fa-solid fa-money-bill" style="color: green;"></i> <strong>TOTAL PRINCIPAL</strong><span class="float-end"><strong>${formatCurrency(adjustmentData.principal)}</strong></span></td>
                                    <td><i class="fa-solid fa-money-bill" style="color: green;"></i> <strong>TOTAL PRINCIPAL</strong><span class="float-end"><strong>${formatCurrency(netData.principal)}</strong></span></td>
                                </tr>
                                <tr>
                                    <td><i class="fa-solid fa-receipt" style="color: red;"></i> <strong>TOTAL CHARGE</strong><span class="float-end"><strong>${formatCurrency(summaryData.total_charge)}</strong></span></td>
                                    <td><i class="fa-solid fa-receipt" style="color: red;"></i> <strong>TOTAL CHARGE</strong><span class="float-end"><strong>${formatCurrency(adjustmentData.total_charge)}</strong></span></td>
                                    <td><i class="fa-solid fa-receipt" style="color: red;"></i> <strong>TOTAL CHARGE</strong><span class="float-end"><strong>${formatCurrency(netData.total_charge)}</strong></span></td>
                                </tr>
                                <tr>
                                    <td><i class="fa-solid fa-handshake" style="color: blue;"></i> <strong>CHARGE TO PARTNER</strong><span class="float-end"><strong>${formatCurrency(summaryData.charge_partner)}</strong></span></td>
                                    <td><i class="fa-solid fa-handshake" style="color: blue;"></i> <strong>CHARGE TO PARTNER</strong><span class="float-end"><strong>${formatCurrency(adjustmentData.charge_partner)}</strong></span></td>
                                    <td><i class="fa-solid fa-handshake" style="color: blue;"></i> <strong>CHARGE TO PARTNER</strong><span class="float-end"><strong>${formatCurrency(netData.charge_partner)}</strong></span></td>
                                </tr>
                                <tr>
                                    <td><i class="fa-solid fa-user" style="color: teal;"></i> <strong>CHARGE TO CUSTOMER</strong><span class="float-end"><strong>${formatCurrency(summaryData.charge_customer)}</strong></span></td>
                                    <td><i class="fa-solid fa-user" style="color: teal;"></i> <strong>CHARGE TO CUSTOMER</strong><span class="float-end"><strong>${formatCurrency(adjustmentData.charge_customer)}</strong></span></td>
                                    <td><i class="fa-solid fa-user" style="color: teal;"></i> <strong>CHARGE TO CUSTOMER</strong><span class="float-end"><strong>${formatCurrency(netData.charge_customer)}</strong></span></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Import Details -->
                    <div class="mb-3" style="background: linear-gradient(135deg, #198754 0%, #157347 100%); padding: 15px; border-radius: 10px;">
                        <h4 class="text-white text-center mb-0"><i class="fa-solid fa-info-circle"></i> Import Details</h4>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-bordered table-sm">
                            <tbody>
                                <tr>
                                    <th width="35%" style="background-color: #f8f9fa;"><i class="fa-solid fa-id-card" style="color: #0d6efd;"></i> KP7 Partner ID</th>
                                    <td><strong>${partnerData.partner_id || 'N/A'}</strong></td>
                                </tr>
                                <tr>
                                    <th style="background-color: #f8f9fa;"><i class="fa-solid fa-id-card" style="color: #0d6efd;"></i> KPX Partner ID</th>
                                    <td><strong>${partnerData.partner_id_kpx || 'N/A'}</strong></td>
                                </tr>
                                <tr>
                                    <th style="background-color: #f8f9fa;"><i class="fa-solid fa-code" style="color: #6610f2;"></i> GL Code</th>
                                    <td><strong>${partnerData.gl_code || 'N/A'}</strong></td>
                                </tr>
                                <tr>
                                    <th style="background-color: #f8f9fa;"><i class="fa-solid fa-building" style="color: #d63384;"></i> Partner Name</th>
                                    <td><strong>${partnerData.partner_name || fileData.partner_name || 'N/A'}</strong></td>
                                </tr>
                                <tr>
                                    <th style="background-color: #f8f9fa;"><i class="fa-solid fa-list" style="color: #fd7e14;"></i> No. of Data Rows Uploaded</th>
                                    <td><strong>${totalRowsUploaded}</strong></td>
                                </tr>
                                <tr>
                                    <th style="background-color: #f8f9fa;"><i class="fa-solid fa-database" style="color: #20c997;"></i> Source</th>
                                    <td><span class="badge badge-${sourceType.toLowerCase()}" style="font-size: 14px;">${sourceType} System</span></td>
                                </tr>
                                <tr>
                                    <th style="background-color: #f8f9fa;"><i class="fa-solid fa-calendar-day" style="color: #0dcaf0;"></i> Transaction Date</th>
                                    <td><strong>${transactionDate}</strong></td>
                                </tr>
                                <tr>
                                    <th style="background-color: #f8f9fa;"><i class="fa-solid fa-calendar" style="color: #0dcaf0;"></i> Uploaded Date</th>
                                    <td><strong>${uploadedDate}</strong></td>
                                </tr>
                                <tr>
                                    <th style="background-color: #f8f9fa;"><i class="fa-solid fa-user-check" style="color: #198754;"></i> Uploaded By</th>
                                    <td><strong>${uploadedBy}</strong></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>`;
            
            // Display the modal
            Swal.fire({
                title: '<strong><i class="fa-solid fa-file-invoice"></i> Transaction Summary: ' + fileData.name + '</strong>',
                html: html,
                width: '95%',
                showCloseButton: true,
                confirmButtonText: 'Close',
                customClass: {
                    container: 'swal-wide'
                }
            });
        }
        
        function confirmCancel() {
            Swal.fire({
                title: 'Cancel Import?',
                text: "All uploaded files will be discarded. This action cannot be undone.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, cancel import',
                cancelButtonText: 'No, go back'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Clean up session and temp files
                    window.location.href = '../../dashboard/billspayment/import/billspay-transaction.php?cancel=1';
                }
            });
        }
    </script>
</body>
</html>
