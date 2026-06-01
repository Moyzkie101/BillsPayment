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

// Increase memory and execution time for large batches (short-term mitigation).
// Recommended: implement chunked reads for robust long-term handling.
// Make memory unlimited for import processing to avoid OOM during heavy validation.
// NOTE: Use with caution in production. Consider setting to a large finite value if preferred.
ini_set('memory_limit', '-1');
set_time_limit(0);

// Shutdown handler logging removed per request (previously wrote import_debug.log)

// Configure PhpSpreadsheet cell caching to reduce memory usage for large files
// Use a disk-based cache (discISAM) or php temp dir. This helps prevent exhausting RAM.
try {
    // Some PhpSpreadsheet versions expose caching APIs differently.
    // Use string class names to avoid static analysis errors on missing classes.
    $cacheFactoryClass = 'PhpOffice\\PhpSpreadsheet\\CachedObjectStorageFactory';
    $settingsClass = 'PhpOffice\\PhpSpreadsheet\\Settings';

    if (class_exists($cacheFactoryClass) && class_exists($settingsClass)) {
        $cacheSettings = ['dir' => sys_get_temp_dir()];
        $cacheConst = $cacheFactoryClass . '::cache_to_discISAM';

        if (method_exists($settingsClass, 'setCacheStorageMethod') && defined($cacheConst)) {
            $cacheMethod = constant($cacheConst);
            $settingsClass::setCacheStorageMethod($cacheMethod, $cacheSettings);
        } elseif (method_exists($cacheFactoryClass, 'initialize') && defined($cacheConst)) {
            // Fallback for versions exposing an initialize() helper
            $cacheFactoryClass::initialize(constant($cacheConst), $cacheSettings);
        }
    } else {
        error_log('[IMPORT INFO] PhpSpreadsheet caching classes not available; skipping cache configuration.');
    }
} catch (Exception $e) {
    // If cache setting fails, log and continue — we'll rely on increased memory limit
    error_log('[IMPORT WARNING] PhpSpreadsheet cache configuration failed: ' . $e->getMessage());
}

// ============================================================================
// AJAX Endpoint: Check for Duplicate Records
// ============================================================================
if (isset($_POST['check_duplicates']) && isset($_FILES['files'])) {
    header('Content-Type: application/json');
    
    $results = [];
    $fileCount = count($_FILES['files']['name']);
    
    for ($i = 0; $i < $fileCount; $i++) {
        if ($_FILES['files']['error'][$i] === UPLOAD_ERR_OK) {
            $tmpPath = $_FILES['files']['tmp_name'][$i];
            $fileName = $_FILES['files']['name'][$i];
            $partnerId = $_POST['partner_ids'][$i] ?? '';
            $sourceType = $_POST['source_types'][$i] ?? '';
            $importMode = strtolower(trim((string)($_POST['import_modes'][$i] ?? 'auto')));
            $subBillersId = $_POST['sub_billers_ids'][$i] ?? '';
            
            try {
                // Load and parse Excel file
                $spreadsheet = loadSpreadsheet($tmpPath, true);
                $worksheet = $spreadsheet->getActiveSheet();
                $highestRow = $worksheet->getHighestRow();
                $highestColumn = $worksheet->getHighestColumn();
                $columnLabels = [];

                // Read row 9 to determine column layout for KPX
                $rowIterator = $worksheet->getRowIterator(9, 9)->current();
                $cellIterator = $rowIterator->getCellIterator('A', $highestColumn);
                foreach ($cellIterator as $cell) {
                    $columnLabels[] = trim(strval($cell->getValue()));
                }
                
                $totalRows = 0;
                $duplicateRows = 0;
                $newRows = 0;
                $postedRows = 0;
                $unpostedRows = 0;
                $rowKeys = [];
                $parsedRows = [];
                
                // Process rows starting from row 10 (data rows)
                for ($row = 10; $row <= $highestRow; $row++) {
                    $cellA = trim(strval($worksheet->getCell('A' . $row)->getValue()));
                    $cellB = trim(strval($worksheet->getCell('B' . $row)->getValue()));
                    $cellC = trim(strval($worksheet->getCell('C' . $row)->getValue()));
                    $cellD = trim(strval($worksheet->getCell('D' . $row)->getValue()));
                    $cellE = trim(strval($worksheet->getCell('E' . $row)->getValue()));
                    $cellF = trim(strval($worksheet->getCell('F' . $row)->getValue()));
                    
                    // Skip empty rows
                    if (empty($cellA) && empty($cellB) && empty($cellC) && empty($cellD) && empty($cellE)) {
                        break;
                    }
                    
                    $totalRows++;
                    
                    // Extract reference number and datetime
                    $reference_number = '';
                    $datetime = null;
                    
                    if ($sourceType === 'KP7') {
                        $reference_number = $cellE;
                        $datetimeValue = $cellC;
                    } else { // KPX
                        if (isset($columnLabels[1]) && $columnLabels[1] === 'Date / Time') {
                            $reference_number = $cellD;
                            $datetimeValue = $cellB;
                        } elseif (isset($columnLabels[2]) && $columnLabels[2] === 'Date / Time') {
                            $reference_number = $cellE;
                            $datetimeValue = $cellC;
                        } else {
                            $reference_number = $cellF;
                            $datetimeValue = trim($cellD . (empty($cellE) ? '' : (' ' . $cellE)));
                        }
                    }

                    if (is_numeric($datetimeValue)) {
                        $datetime = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($datetimeValue)->format('Y-m-d H:i:s');
                    } elseif (!empty($datetimeValue)) {
                        $datetime = date('Y-m-d H:i:s', strtotime($datetimeValue));
                    }

                    if (empty($reference_number) || empty($datetime)) {
                        $newRows++;
                        continue;
                    }

                    $rowPartnerIdForDup = $partnerId;
                    if ($importMode === 'multiple' && strtoupper((string)$sourceType) === 'KPX') {
                        $partnerIdU = trim((string)$worksheet->getCell('U' . $row)->getValue());
                        if ($partnerIdU !== '' && strtoupper($partnerIdU) !== 'NAN') {
                            $rowPartnerIdForDup = $partnerIdU;
                        }
                    }

                    $key = $reference_number . '||' . $datetime . '||' . $rowPartnerIdForDup;
                    $rowKeys[$key] = true;
                    $parsedRows[] = ['key' => $key];
                }

                // Batch duplicate lookup per file (replaces per-row query loop)
                $duplicateMap = [];
                if (!empty($rowKeys)) {
                    $keys = array_keys($rowKeys);
                    $chunks = array_chunk($keys, 500);
                    foreach ($chunks as $chunkKeys) {
                        $orParts = [];
                        $params = [];
                        $types = '';
                        foreach ($chunkKeys as $k) {
                            $split = explode('||', $k, 3);
                            $ref = $split[0] ?? '';
                            $dt = $split[1] ?? '';
                            $pRow = $split[2] ?? '';
                            $orParts[] = "(reference_no = ? AND (`datetime` = ? OR cancellation_date = ?))";
                            $types .= "sss";
                            $params[] = $ref;
                            $params[] = $dt;
                            $params[] = $dt;
                            $orParts[count($orParts)-1] .= " AND (partner_id = ? OR partner_id_kpx = ?)";
                            $types .= "ss";
                            $params[] = $pRow;
                            $params[] = $pRow;
                        }

                        $sql = "SELECT reference_no, `datetime`, cancellation_date, partner_id, partner_id_kpx, post_transaction, COUNT(*) as cnt
                                FROM mldb.billspayment_transaction WHERE (" . implode(" OR ", $orParts) . ")";

                        if (!empty($subBillersId)) {
                            $sql .= " AND sub_billers_id = ?";
                            $types .= "s";
                            $params[] = $subBillersId;
                        }

                        $sql .= " GROUP BY reference_no, `datetime`, cancellation_date, partner_id, partner_id_kpx, post_transaction";
                        $stmt = $conn->prepare($sql);
                        $bind = [$types];
                        foreach ($params as $idx => $val) { $bind[] = &$params[$idx]; }
                        call_user_func_array([$stmt, 'bind_param'], $bind);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        if ($result) {
                            while ($r = $result->fetch_assoc()) {
                                $status = isset($r['post_transaction']) ? strtolower(trim($r['post_transaction'])) : '';
                                $cnt = intval($r['cnt']);
                                $dbPartner = ($r['partner_id_kpx'] !== null && $r['partner_id_kpx'] !== '') ? $r['partner_id_kpx'] : ($r['partner_id'] ?? '');
                                $k1 = ($r['reference_no'] ?? '') . '||' . ($r['datetime'] ?? '') . '||' . $dbPartner;
                                $k2 = ($r['reference_no'] ?? '') . '||' . ($r['cancellation_date'] ?? '') . '||' . $dbPartner;
                                if (!isset($duplicateMap[$k1])) $duplicateMap[$k1] = ['total' => 0, 'posted' => 0, 'unposted' => 0];
                                $duplicateMap[$k1]['total'] += $cnt;
                                if ($status === 'posted') { $duplicateMap[$k1]['posted'] += $cnt; } else { $duplicateMap[$k1]['unposted'] += $cnt; }
                                if (!empty($r['cancellation_date'])) {
                                    if (!isset($duplicateMap[$k2])) $duplicateMap[$k2] = ['total' => 0, 'posted' => 0, 'unposted' => 0];
                                    $duplicateMap[$k2]['total'] += $cnt;
                                    if ($status === 'posted') { $duplicateMap[$k2]['posted'] += $cnt; } else { $duplicateMap[$k2]['unposted'] += $cnt; }
                                }
                            }
                        }
                        $stmt->close();
                    }
                }

                foreach ($parsedRows as $pr) {
                    $k = $pr['key'];
                    if (isset($duplicateMap[$k]) && intval($duplicateMap[$k]['total']) > 0) {
                        $duplicateRows++;
                        $postedRows += intval($duplicateMap[$k]['posted']);
                        $unpostedRows += intval($duplicateMap[$k]['unposted']);
                    } else {
                        $newRows++;
                    }
                }

                // Free spreadsheet resources for this file to reduce memory usage
                if (isset($spreadsheet) && is_object($spreadsheet)) {
                    try { $spreadsheet->disconnectWorksheets(); } catch (Exception $e) {}
                    unset($worksheet, $spreadsheet);
                    if (function_exists('gc_collect_cycles')) gc_collect_cycles();
                }

                $results[] = [
                    'fileName' => $fileName,
                    'partnerId' => $partnerId,
                    'sourceType' => $sourceType,
                    'totalRows' => $totalRows,
                    'duplicateRows' => $duplicateRows,
                    'newRows' => $newRows,
                    'postedRows' => $postedRows,
                    'unpostedRows' => $unpostedRows,
                    'hasDuplicates' => $duplicateRows > 0
                ];
                
            } catch (Exception $e) {
                $results[] = [
                    'fileName' => $fileName,
                    'error' => $e->getMessage()
                ];
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'files' => $results
    ]);
    exit;
}

// ============================================================================
// AJAX Endpoint: Fix Head Office Branch IDs
// Expects: POST fix_head_office=1, file_id, fixes (JSON array [{row:..., value:...}])
// This will update the spreadsheet on disk and re-run validation for that file.
// ============================================================================
if (isset($_POST['fix_head_office']) && !empty($_POST['file_id'])) {
    header('Content-Type: application/json');
    $fileId = $_POST['file_id'];
    $fixesJson = $_POST['fixes'] ?? '[]';
    $fixes = json_decode($fixesJson, true);

    if (!isset($_SESSION['uploaded_files']) || !is_array($_SESSION['uploaded_files'])) {
        echo json_encode(['success' => false, 'error' => 'No session upload data']);
        exit;
    }

    $found = null;
    foreach ($_SESSION['uploaded_files'] as &$f) {
        if (isset($f['id']) && $f['id'] === $fileId) { $found = &$f; break; }
    }
    if ($found === null) {
        echo json_encode(['success' => false, 'error' => 'File not found in session']);
        exit;
    }

    $path = $found['path'] ?? null;
    if (!$path || !file_exists($path)) {
        echo json_encode(['success' => false, 'error' => 'File not found on disk']);
        exit;
    }

    try {
        $reader = IOFactory::createReaderForFile($path);
        $spreadsheet = $reader->load($path);
        $worksheet = $spreadsheet->getActiveSheet();

        // Apply fixes - decide branch id automatically when no explicit value provided.
        // Use exact outlet matching on column O only:
        // - ML CEBU HEAD OFFICE / CEBU HEAD OFFICE => 581
        // - ML HEAD OFFICE / HEAD OFFICE => 2607
        foreach ($fixes as $fix) {
            $r = intval($fix['row'] ?? 0);
            $v = isset($fix['value']) ? strval($fix['value']) : '';
            if ($r > 0) {
                // attempt to inspect outlet cell O for this row to infer proper head office
                try {
                    $outlet = '';
                    $modeNormFix = strtolower(trim((string)($found['import_mode'] ?? 'auto')));
                    $outletCol = ($modeNormFix === 'auto') ? 'O' : 'N';
                    try { $outlet = trim(strval($worksheet->getCell($outletCol . $r)->getValue())); } catch (Exception $e) { $outlet = ''; }
                    if ($v === '') {
                        $normalizedOutlet = strtoupper(preg_replace('/\s+/', ' ', $outlet));
                        if ($normalizedOutlet === 'ML CEBU HEAD OFFICE' || $normalizedOutlet === 'CEBU HEAD OFFICE') {
                            $v = '581';
                        } elseif ($normalizedOutlet === 'ML HEAD OFFICE' || $normalizedOutlet === 'HEAD OFFICE') {
                            $v = '2607';
                        }
                    }
                } catch (Exception $e) {
                    // keep explicit value only if provided; do not force fallback value
                }

                // Target branch id column depends on import mode:
                // Auto Mode -> N, Multiple Mode -> M
                if ($v !== '') {
                    $modeNormFix = strtolower(trim((string)($found['import_mode'] ?? 'auto')));
                    $branchCol = ($modeNormFix === 'auto') ? 'N' : 'M';
                    try { $worksheet->setCellValue($branchCol . $r, $v); } catch (Exception $e) {}
                }
            }
        }

        // Save back to same path (overwrite)
        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save($path);

        // Re-run validation for this file and update session
        $newValidation = validateFile($conn, $path, $found['source_type'] ?? '', $found['partner_id'] ?? '', $found['import_mode'] ?? 'auto');
        $found['validation_result'] = $newValidation;
        // update status with Head Office priority
        if (!empty($newValidation['head_office_issues'])) {
            $found['status'] = 'head_office';
        } elseif ($newValidation['valid']) {
            $found['status'] = 'valid';
        } else {
            $found['status'] = 'invalid';
        }
        $_SESSION['uploaded_files'] = $_SESSION['uploaded_files'];

        // Free resources
        try { $spreadsheet->disconnectWorksheets(); } catch (Exception $e) {}

        echo json_encode(['success' => true, 'validation_result' => $newValidation]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

// ============================================================================
// AJAX Endpoint: Fix All Head Office Issues
// ============================================================================
if (isset($_POST['fix_head_office_all']) && isset($_SESSION['uploaded_files'])) {
    header('Content-Type: application/json');
    $results = [];
    $any = false;
    foreach ($_SESSION['uploaded_files'] as &$f) {
        if (!empty($f['validation_result']['head_office_issues']) && isset($f['path']) && file_exists($f['path'])) {
            $any = true;
            $path = $f['path'];
            try {
                $reader = IOFactory::createReaderForFile($path);
                $spreadsheet = $reader->load($path);
                $worksheet = $spreadsheet->getActiveSheet();

                $fixes = $f['validation_result']['head_office_issues'];
                foreach ($fixes as $fix) {
                    $r = intval($fix['row'] ?? 0);
                    $v = '';
                    if ($r > 0) {
                        try {
                            $outlet = '';
                            $modeNormFixAll = strtolower(trim((string)($f['import_mode'] ?? 'auto')));
                            $outletCol = ($modeNormFixAll === 'auto') ? 'O' : 'N';
                            try { $outlet = trim(strval($worksheet->getCell($outletCol . $r)->getValue())); } catch (Exception $e) { $outlet = ''; }
                            if ($v === '') {
                                $normalizedOutlet = strtoupper(preg_replace('/\s+/', ' ', $outlet));
                                if ($normalizedOutlet === 'ML CEBU HEAD OFFICE' || $normalizedOutlet === 'CEBU HEAD OFFICE') {
                                    $v = '581';
                                } elseif ($normalizedOutlet === 'ML HEAD OFFICE' || $normalizedOutlet === 'HEAD OFFICE') {
                                    $v = '2607';
                                }
                            }
                        } catch (Exception $e) {
                            // do not force fallback value
                        }

                        if ($v !== '') {
                            $modeNormFixAll = strtolower(trim((string)($f['import_mode'] ?? 'auto')));
                            $branchCol = ($modeNormFixAll === 'auto') ? 'N' : 'M';
                            try { $worksheet->setCellValue($branchCol . $r, $v); } catch (Exception $e) {}
                        }
                    }
                }

                $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
                $writer->save($path);

                try { $spreadsheet->disconnectWorksheets(); } catch (Exception $e) {}

                // Re-run validation
                $newValidation = validateFile($conn, $path, $f['source_type'] ?? '', $f['partner_id'] ?? '', $f['import_mode'] ?? 'auto');
                $f['validation_result'] = $newValidation;
                if (!empty($newValidation['head_office_issues'])) {
                    $f['status'] = 'head_office';
                } elseif ($newValidation['valid']) {
                    $f['status'] = 'valid';
                } else {
                    $f['status'] = 'invalid';
                }

                $results[] = ['id' => $f['id'] ?? '', 'success' => true, 'status' => $f['status']];
            } catch (Exception $e) {
                $results[] = ['id' => $f['id'] ?? '', 'success' => false, 'error' => $e->getMessage()];
            }
        }
    }
    unset($f);
    $_SESSION['uploaded_files'] = $_SESSION['uploaded_files'];
    if (!$any) {
        echo json_encode(['success' => false, 'error' => 'No head office issues found']);
        exit;
    }
    echo json_encode(['success' => true, 'results' => $results]);
    exit;
}

// ============================================================================
// AJAX Endpoint: Check Single File for Duplicates (Manual Mode)
// ============================================================================
if (isset($_POST['check_single_duplicate']) && isset($_FILES['import_file'])) {
    header('Content-Type: application/json');
    
    if ($_FILES['import_file']['error'] === UPLOAD_ERR_OK) {
        $tmpPath = $_FILES['import_file']['tmp_name'];
        $fileName = $_FILES['import_file']['name'];
        $partnerId = $_POST['partner_id'] ?? '';
        $sourceType = $_POST['source_type'] ?? '';
        $importMode = strtolower(trim((string)($_POST['import_mode'] ?? 'auto')));
        
        try {
            // Load and parse Excel file
            $spreadsheet = loadSpreadsheet($tmpPath, true);
            $worksheet = $spreadsheet->getActiveSheet();
            $highestRow = $worksheet->getHighestRow();
            
            $totalRows = 0;
            $duplicateRows = 0;
            $newRows = 0;
            $postedRows = 0;
            $unpostedRows = 0;
            
            // Process rows starting from row 10 (data rows)
            for ($row = 10; $row <= $highestRow; $row++) {
                $cellA = trim(strval($worksheet->getCell('A' . $row)->getValue()));
                $cellB = trim(strval($worksheet->getCell('B' . $row)->getValue()));
                $cellC = trim(strval($worksheet->getCell('C' . $row)->getValue()));
                $cellD = trim(strval($worksheet->getCell('D' . $row)->getValue()));
                $cellE = trim(strval($worksheet->getCell('E' . $row)->getValue()));
                
                // Skip empty rows
                if (empty($cellA) && empty($cellB) && empty($cellC) && empty($cellD) && empty($cellE)) {
                    break;
                }
                
                $totalRows++;
                
                // Extract reference number and datetime
                $reference_number = '';
                $datetime = null;
                
                if ($sourceType === 'KP7') {
                    $reference_number = $cellD;
                    $datetimeValue = $cellC;
                    if (is_numeric($datetimeValue)) {
                        $datetime = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($datetimeValue)->format('Y-m-d H:i:s');
                    } else {
                        $datetime = date('Y-m-d H:i:s', strtotime($datetimeValue));
                    }
                } else { // KPX
                    $reference_number = $cellC;
                    $datetimeValue = $cellB;
                    if (is_numeric($datetimeValue)) {
                        $datetime = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($datetimeValue)->format('Y-m-d H:i:s');
                    } else {
                        $datetime = date('Y-m-d H:i:s', strtotime($datetimeValue));
                    }
                }
                
                $rowPartnerIdForDup = $partnerId;
                if ($importMode === 'multiple' && strtoupper((string)$sourceType) === 'KPX') {
                    $partnerIdU = trim((string)$worksheet->getCell('U' . $row)->getValue());
                    if ($partnerIdU !== '' && strtoupper($partnerIdU) !== 'NAN') {
                        $rowPartnerIdForDup = $partnerIdU;
                    }
                }

                // Check for duplicates (posted or unposted) with partner filter
                if (!empty($rowPartnerIdForDup) && strtoupper($rowPartnerIdForDup) !== 'ALL') {
                    $sql = "SELECT post_transaction, COUNT(*) as cnt FROM mldb.billspayment_transaction 
                            WHERE reference_no = ? 
                            AND (partner_id = ? OR partner_id_kpx = ?)
                            AND (`datetime` = ? OR cancellation_date = ?) GROUP BY post_transaction";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("sssss", $reference_number, $rowPartnerIdForDup, $rowPartnerIdForDup, $datetime, $datetime);
                } else {
                    $sql = "SELECT post_transaction, COUNT(*) as cnt FROM mldb.billspayment_transaction 
                            WHERE reference_no = ? 
                            AND (`datetime` = ? OR cancellation_date = ?) GROUP BY post_transaction";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("sss", $reference_number, $datetime, $datetime);
                }
                $stmt->execute();
                $result = $stmt->get_result();
                $row_count_total = 0;
                if ($result) {
                    while ($r = $result->fetch_assoc()) {
                        $cnt = intval($r['cnt']);
                        $row_count_total += $cnt;
                        $status = isset($r['post_transaction']) ? strtolower(trim($r['post_transaction'])) : '';
                        if ($status === 'posted') {
                            $postedRows += $cnt;
                        } else {
                            $unpostedRows += $cnt;
                        }
                    }
                }
                $stmt->close();

                if ($row_count_total > 0) {
                    $duplicateRows++;
                } else {
                    $newRows++;
                }
            }

            // Free spreadsheet resources for this single-file check
            if (isset($spreadsheet) && is_object($spreadsheet)) {
                try { $spreadsheet->disconnectWorksheets(); } catch (Exception $e) {}
                unset($worksheet, $spreadsheet);
                if (function_exists('gc_collect_cycles')) gc_collect_cycles();
            }

            echo json_encode([
                'success' => true,
                'fileName' => $fileName,
                'partnerId' => $partnerId,
                'sourceType' => $sourceType,
                'totalRows' => $totalRows,
                'duplicateRows' => $duplicateRows,
                'newRows' => $newRows,
                'postedRows' => $postedRows,
                'unpostedRows' => $unpostedRows,
                'hasDuplicates' => $duplicateRows > 0
            ]);
            
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'File upload error'
        ]);
    }
    exit;
}

// Progress polling endpoint (returns JSON for a given token)
if (isset($_GET['get_progress']) && !empty($_GET['token'])) {
    $token = basename($_GET['token']);
    $progressFile = __DIR__ . "/../../admin/temporary/progress_" . $token . ".json";
    header('Content-Type: application/json');
    if (file_exists($progressFile)) {
        echo file_get_contents($progressFile);
    } else {
        echo json_encode(['total' => 0, 'done' => 0]);
    }
    exit;
}

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
    
    // Also remove leftover generated XLSX and progress files in admin/temporary
    $tmpDir = __DIR__ . '/../../admin/temporary/';
    $tmpReal = realpath($tmpDir);
    if ($tmpReal && is_dir($tmpReal)) {
        // Use forward-slash glob patterns (works reliably across platforms)
        $safeBase = rtrim(str_replace('\\', '/', $tmpReal), '/');
        $genPattern = $safeBase . '/*_generated.xlsx';
        $progPattern = $safeBase . '/progress_*.json';

        $deletedCount = 0;
        foreach (glob($genPattern) as $gfile) {
            $real = realpath($gfile);
            if ($real && strpos(str_replace('\\','/',$real), $safeBase) === 0 && file_exists($real)) {
                if (@unlink($real)) $deletedCount++;
            }
        }
        foreach (glob($progPattern) as $pfile) {
            $real = realpath($pfile);
            if ($real && strpos(str_replace('\\','/',$real), $safeBase) === 0 && file_exists($real)) {
                if (@unlink($real)) $deletedCount++;
            }
        }
        if (function_exists('error_log')) {
            error_log('[IMPORT CLEANUP] Cancel removed ' . $deletedCount . ' files from admin/temporary');
        }

        // Debug file writing disabled - no debug artifacts will be left in admin/temporary
    }

    // Clear session
    unset($_SESSION['uploaded_files']);
    unset($_SESSION['batch_upload']);
    unset($_SESSION['user_decision']);
    
    echo '<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>';
    echo "<script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                icon: 'info',
                title: 'Import Cancelled',
                text: 'All uploaded files have been removed.',
                confirmButtonText: 'OK'
            }).then(() => {
                window.location.href = '/billspayment/dashboard/billspayment/import/billspay-transaction.php';
            });
        });
    </script>";
    exit;
}

// ============================================================================
// Handle Manual Mode Upload (single file with company name)
// ============================================================================
if (isset($_POST['upload']) && isset($_POST['company']) && isset($_FILES['import_file'])) {
    $userDecision = $_POST['user_decision'] ?? 'skip';
    $company = $_POST['company'];
    $fileType = $_POST['fileType'];
    
    if ($_FILES['import_file']['error'] === UPLOAD_ERR_OK) {
        $tmpPath = $_FILES['import_file']['tmp_name'];
        $fileName = $_FILES['import_file']['name'];
        
        // Get partner ID from company name
        $partnerId = '';
        if ($company !== 'All') {
            $stmt = $conn->prepare("SELECT partner_id FROM masterdata.partner_masterfile WHERE partner_name = ? LIMIT 1");
            $stmt->bind_param("s", $company);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result && $result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $partnerId = $row['partner_id'];
            }
            $stmt->close();
        } else {
            $partnerId = 'ALL';
        }
        
        // Generate unique ID for temp storage
        $fileId = uniqid('file_', true);
        $tempRel = "/../../admin/temporary/" . $fileId . "_" . basename($fileName);
        $tempPath = __DIR__ . $tempRel;
        
        // Move uploaded file to temp directory
        if (move_uploaded_file($tmpPath, $tempPath)) {
            $partnerName = ($company === 'All') ? 'All' : $company;
            
                $uploadedFiles[] = [
                'id' => $fileId,
                'name' => $fileName,
                'path' => $tempPath,
                'partner_id' => $partnerId,
                'partner_name' => $partnerName,
                    'source_type' => $fileType,
                    'sub_billers_id' => null,
                'status' => 'pending',
                'validation_result' => null,
                'uploaded_by' => $current_user_email,
                'uploaded_date' => date('Y-m-d H:i:s')
            ];
            
            // Store in session
            $_SESSION['uploaded_files'] = $uploadedFiles;
            $_SESSION['batch_upload'] = true;
            $_SESSION['user_decision'] = $userDecision;
            
            // Redirect to validation page (self)
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        }
    }
}

// ============================================================================
// STEP 1: Handle File Upload and Store in Session
// ============================================================================
if (isset($_POST['upload']) && isset($_FILES['files'])) {
    $uploadedFiles = [];
    $fileCount = count($_FILES['files']['name']);
    $userDecision = $_POST['user_decision'] ?? 'skip'; // Get user decision (override/skip/cancel)
    
    for ($i = 0; $i < $fileCount; $i++) {
        if ($_FILES['files']['error'][$i] === UPLOAD_ERR_OK) {
            $tmpPath = $_FILES['files']['tmp_name'][$i];
            $fileName = $_FILES['files']['name'][$i];
            $partnerId = $_POST['partner_ids'][$i] ?? '';
            $sourceType = $_POST['source_types'][$i] ?? '';
            $importMode = $_POST['import_modes'][$i] ?? 'auto';
            $billersName = $_POST['billers_names'][$i] ?? '';
            $subBillersId = $_POST['sub_billers_ids'][$i] ?? null;
            
            // Generate unique ID for temp storage
            $fileId = uniqid('file_', true);
            $tempRel = "/../../admin/temporary/" . $fileId . "_" . basename($fileName);
            $tempPath = __DIR__ . $tempRel;
            
            // Move uploaded file to temp directory
            if (move_uploaded_file($tmpPath, $tempPath)) {
                // Get partner name from database
                $partnerName = getPartnerName($conn, $partnerId, $sourceType);
                // capture report date if client provided one
                $reportDateRaw = $_POST['report_dates'][$i] ?? '';
                $reportDate = null;
                if (!empty($reportDateRaw)) {
                    $ts = strtotime($reportDateRaw);
                    if ($ts !== false) $reportDate = date('Y-m-d', $ts);
                }

                $uploadedFiles[] = [
                    'id' => $fileId,
                    'name' => $fileName,
                    'path' => $tempPath,
                    'partner_id' => $partnerId,
                    'partner_name' => $partnerName,
                    'billers_name' => $billersName,
                    'sub_billers_id' => $subBillersId,
                    'source_type' => $sourceType,
                    'import_mode' => $importMode,
                    'report_date_raw' => $reportDateRaw,
                    'report_date' => $reportDate,
                    'status' => 'pending',
                    'validation_result' => null,
                    'uploaded_by' => $current_user_email,
                    'uploaded_date' => date('Y-m-d H:i:s')
                ];
            }
        }
    }
    
    // Store in session along with user decision
    $_SESSION['uploaded_files'] = $uploadedFiles;
    $_SESSION['batch_upload'] = true;
    $_SESSION['user_decision'] = $userDecision; // Store user decision in session
    
    // Redirect to validation page (self)
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// ============================================================================
// STEP 2: Perform Validation on Stored Files
// ============================================================================
if (isset($_SESSION['uploaded_files']) && !isset($_POST['perform_import'])) {
    // Run full validation on all files to get transaction summaries and previews
    foreach ($_SESSION['uploaded_files'] as &$file) {
        $needsRefresh = (
            !isset($file['validation_result']) ||
            !is_array($file['validation_result']) ||
            !array_key_exists('has_head_office_outlet', $file['validation_result']) ||
            !array_key_exists('validation_rule_version', $file['validation_result']) ||
            intval($file['validation_result']['validation_rule_version']) < 2
        );
        if (($file['status'] === 'pending') || $needsRefresh) {
            // Ensure stored path exists; attempt to resolve using helper
            $resolved = resolve_uploaded_path($file['path']);
            if ($resolved === null) {
                error_log("[IMPORT ERROR] File not found for validation: {$file['path']}");
                $validationResult = [
                    'valid' => false,
                    'row_count' => 0,
                    'errors' => [[ 'row' => 'N/A', 'type' => 'critical', 'message' => "File does not exist: {$file['path']}", 'value' => '' ]],
                    'warnings' => [],
                    'preview_data' => [],
                    'source_type' => $file['source_type'],
                    'partner_data' => null,
                    'transaction_date' => null,
                    'transaction_summary' => null
                ];
            } else {
                // update stored path and session
                $file['path'] = $resolved;
                $_SESSION['uploaded_files'] = $_SESSION['uploaded_files'];
                $validationResult = validateFile($conn, $file['path'], $file['source_type'], $file['partner_id'], $file['import_mode'] ?? 'auto');
            }
            $file['validation_result'] = $validationResult;
            // Priority: Head Office (highest) -> Invalid -> Valid
            if (!empty($validationResult['head_office_issues'])) {
                $file['status'] = 'head_office';
            } elseif ($validationResult['valid']) {
                $file['status'] = 'valid';
            } else {
                $file['status'] = 'invalid';
            }
        }
    }
    unset($file); // Clear the reference
}

// ============================================================================
// STEP 3: Handle Import Action
// ============================================================================
    $isAjax = isset($_POST['is_ajax']) && $_POST['is_ajax'] == '1';
    
    if (isset($_POST['perform_import']) && isset($_SESSION['uploaded_files'])) {
    $imported = 0;
    $failed = 0;
    $errors = [];
    $allDebugStats = []; // Collect debug stats from all files
    
    // collect progress token if provided
    $globalProgressToken = $_POST['progress_token'] ?? null;
    
    // Initialize progress file once for ALL files
    $progressFile = null;
    $validFiles = array_filter($_SESSION['uploaded_files'], function($f) { return $f['status'] === 'valid'; });
    $totalFiles = count($validFiles);
    
    if (!empty($globalProgressToken) && $totalFiles > 0) {
        $token = basename($globalProgressToken);
        $progressFile = __DIR__ . "/../../admin/temporary/progress_" . $token . ".json";
        @file_put_contents($progressFile, json_encode(['total' => $totalFiles, 'done' => 0]));
        // Close session once to allow polling
        @session_write_close();
    }

    foreach ($_SESSION['uploaded_files'] as $file) {
        if ($file['status'] === 'valid') {
            // Ensure we always attempt to remove the temp file after processing
            try {
                // Get user decision from session (default to 'skip' if not set)
                $userDecision = $_SESSION['user_decision'] ?? 'skip';

                // Import file with user decision
                $result = importFileData($conn, $file['path'], $file['source_type'], $file['partner_id'], $current_user_email, null, null, $userDecision, $file);

                if ($result['success']) {
                    $imported++;
                    // Collect debug stats
                    if (isset($result['debug_stats'])) {
                        $allDebugStats[] = array_merge(['file' => $file['name']], $result['debug_stats']);
                    }
                    // Update progress after each FILE completes
                    if (!empty($progressFile) && file_exists($progressFile)) {
                        @file_put_contents($progressFile, json_encode(['total' => $totalFiles, 'done' => $imported]));
                    }
                    if (!empty($result['warnings'])) {
                        foreach ($result['warnings'] as $warn) {
                            $errors[] = "File: " . $file['name'] . " - " . $warn;
                        }
                    }
                } else {
                    $failed++;
                    // Collect debug stats even on failure
                    if (isset($result['debug_stats'])) {
                        $allDebugStats[] = array_merge(['file' => $file['name'], 'failed' => true], $result['debug_stats']);
                    }
                    // Update progress even on failure
                    if (!empty($progressFile) && file_exists($progressFile)) {
                        @file_put_contents($progressFile, json_encode(['total' => $totalFiles, 'done' => $imported + $failed]));
                    }
                    $errors[] = "File: " . $file['name'] . " - " . ($result['error'] ?? 'Unknown error');
                }
            } catch (Exception $e) {
                $failed++;
                // Update progress even on exception
                if (!empty($progressFile) && file_exists($progressFile)) {
                    @file_put_contents($progressFile, json_encode(['total' => $totalFiles, 'done' => $imported + $failed]));
                }
                $errors[] = "File: " . $file['name'] . " - " . $e->getMessage();
            } finally {
                // Always attempt to delete the temp file for this uploaded file (safety: only inside admin/temporary)
                if (!empty($file['path']) && file_exists($file['path'])) {
                    $tmpDir = realpath(__DIR__ . '/../../admin/temporary/');
                    $real = realpath($file['path']);
                    if ($tmpDir && $real && strpos(str_replace('\\','/',$real), str_replace('\\','/',$tmpDir)) === 0) {
                        @unlink($real);
                    }
                }
            }
        }
    }

    // Additionally, remove any leftover uploaded files (not only valid) from admin/temporary to ensure cleanup
    if (!empty($_SESSION['uploaded_files'])) {
        $tmpDir = realpath(__DIR__ . '/../../admin/temporary/');
        foreach ($_SESSION['uploaded_files'] as $uf) {
            if (!empty($uf['path']) && file_exists($uf['path'])) {
                $real = realpath($uf['path']);
                if ($tmpDir && $real && strpos(str_replace('\\','/',$real), str_replace('\\','/',$tmpDir)) === 0) {
                    @unlink($real);
                }
            }
        }
    }
    
    // Clean up progress file
    if (!empty($progressFile) && file_exists($progressFile)) {
        @unlink($progressFile);
    }
    
    // Clear session
    unset($_SESSION['uploaded_files']);
    unset($_SESSION['batch_upload']);
    unset($_SESSION['user_decision']);
    
    if ($isAjax) {
        // Calculate aggregate debug stats
        $aggregateStats = [
            'total_attempts' => 0,
            'total_success' => 0,
            'total_failures' => 0,
            'total_deleted' => 0,
            'files_processed' => count($allDebugStats)
        ];
        
        foreach ($allDebugStats as $stats) {
            $aggregateStats['total_attempts'] += $stats['attempts'] ?? 0;
            $aggregateStats['total_success'] += $stats['success'] ?? 0;
            $aggregateStats['total_failures'] += $stats['failures'] ?? 0;
            $aggregateStats['total_deleted'] += $stats['deleted'] ?? 0;
        }
        
        // Return JSON response for AJAX
        header('Content-Type: application/json');
        echo json_encode([
            'success' => $imported > 0,
            'imported' => $imported,
            'failed' => $failed,
            'errors' => $errors,
            'debug_stats' => $aggregateStats,
            'per_file_stats' => $allDebugStats
        ]);
        exit;
    }
    
    // Show result and redirect
    $errorDetailsJson = json_encode($errors, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    // compute project base path dynamically so redirects work from any project folder name
    $scriptPath = $_SERVER['SCRIPT_NAME'] ?? '';
    $projectBase = '';

    // Prefer explicit project folder if present
    if (stripos($scriptPath, '/billspayment/') !== false) {
        $pos = stripos($scriptPath, '/billspayment/');
        $projectBase = substr($scriptPath, 0, $pos) . '/billspayment';
    } elseif (stripos($scriptPath, '/dashboard/') !== false) {
        // fallback: remove /dashboard/... part
        $pos = stripos($scriptPath, '/dashboard/');
        $projectBase = substr($scriptPath, 0, $pos);
    } else {
        // last-resort: go up three levels from script path
        $projectBase = rtrim(dirname(dirname(dirname($scriptPath))), '/\\');
    }

    // ensure leading slash
    if ($projectBase === '' || $projectBase[0] !== '/') {
        $projectBase = '/' . ltrim($projectBase, '/\\');
    }

    // Use production upload page route explicitly
    $returnUrl = '/billspayment/dashboard/billspayment/import/billspay-transaction.php';
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
                        window.location.href = " . json_encode($returnUrl) . ";
                    });
                } else {
                    window.location.href = " . json_encode($returnUrl) . ";
                }
            });
        });
    </script>";
    exit;
} elseif ($isAjax) {
    // AJAX request but no valid import conditions
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'imported' => 0,
        'failed' => 0,
        'errors' => ['No valid files to import or session expired.']
    ]);
    exit;
}

// ============================================================================
// Helper Functions
// ============================================================================

function getPartnerName($conn, $partnerId, $sourceType = '') {
    $isKpx1074 = (strtoupper((string)$sourceType) === 'KPX' && trim((string)$partnerId) === '1074');
    if ($isKpx1074) {
        return 'SECURITY BANK';
    }

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

/**
 * Resolve an uploaded file path to an existing file on disk.
 * Tries multiple candidate locations and returns the first existing absolute path, or null.
 */
function resolve_uploaded_path($storedPath) {
    // Already absolute and exists
    if (file_exists($storedPath)) return $storedPath;

    // Try realpath (handles relative segments)
    $rp = realpath($storedPath);
    if ($rp && file_exists($rp)) return $rp;

    // If storedPath begins with ../ or contains ../ try relative to this script dir
    $candidate = __DIR__ . DIRECTORY_SEPARATOR . $storedPath;
    $candidate = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $candidate);
    if (file_exists($candidate)) return $candidate;

    // Try admin temporary with basename
    $basename = basename($storedPath);
    $tmpDir = __DIR__ . '/../../admin/temporary/';
    if (is_dir($tmpDir)) {
        // direct candidate
        $cand = $tmpDir . $basename;
        if (file_exists($cand)) return $cand;

        // glob search for any file containing the basename (covers prefixed unique ids)
        $matches = glob($tmpDir . '*' . $basename);
        if (!empty($matches)) return $matches[0];
    }

    // Try document root + billspayment path
    if (!empty($_SERVER['DOCUMENT_ROOT'])) {
        $docCand = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . '/billspayment/admin/temporary/' . $basename;
        if (file_exists($docCand)) return $docCand;
    }

    // Not found
    return null;
}

function loadSpreadsheet($filePath, $readDataOnly = true) {
    $reader = IOFactory::createReaderForFile($filePath);
    if ($readDataOnly && method_exists($reader, 'setReadDataOnly')) {
        $reader->setReadDataOnly(true);
    }
    if (method_exists($reader, 'setReadEmptyCells')) {
        $reader->setReadEmptyCells(false);
    }

    return $reader->load($filePath);
}

function normalizeBranchId($value) {
    $trimmed = trim(strval($value));
    if ($trimmed === '') {
        return '';
    }
    if (is_numeric($trimmed)) {
        return strval((int) floatval($trimmed));
    }
    return $trimmed;
}

function normalizeBranchNameKey($value) {
    $v = strtoupper(trim((string)$value));
    if ($v === '') return '';
    return preg_replace('/\s+/', ' ', $v);
}

function loadBranchIdSet() {
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $branchFile = __DIR__ . '/../../branch.json';
    if (!file_exists($branchFile)) {
        $cache = [];
        return $cache;
    }

    $json = @file_get_contents($branchFile);
    $data = json_decode($json, true);
    if (!is_array($data)) {
        $cache = [];
        return $cache;
    }

    $set = [];
    foreach ($data as $row) {
        if (isset($row['branch_id'])) {
            $id = normalizeBranchId($row['branch_id']);
            if ($id !== '') {
                $set[$id] = true;
            }
        }
    }

    $cache = $set;
    return $cache;
}

function loadBranchNameSet() {
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $branchFile = __DIR__ . '/../../branch.json';
    if (!file_exists($branchFile)) {
        $cache = [];
        return $cache;
    }

    $json = @file_get_contents($branchFile);
    $data = json_decode($json, true);
    if (!is_array($data)) {
        $cache = [];
        return $cache;
    }

    $set = [];
    foreach ($data as $row) {
        if (isset($row['branch_name'])) {
            $k = normalizeBranchNameKey($row['branch_name']);
            if ($k !== '') $set[$k] = true;
        }
    }
    $cache = $set;
    return $cache;
}

function validateFileFast($conn, $filePath, $sourceType, $partnerId) {
    // FAST validation - just check file is readable and partner exists
    // Don't parse the entire Excel file - that's slow!
    $errors = [];
    $spreadsheet = null;
    $worksheet = null;
    
    try {
        // Quick file check
        if (!file_exists($filePath) || !is_readable($filePath)) {
            $errors[] = [
                'row' => 'N/A',
                'type' => 'file',
                'message' => 'File not accessible',
                'value' => ''
            ];
            return [
                'valid' => false,
                'row_count' => 0,
                'errors' => $errors,
                'warnings' => [],
                'preview_data' => [],
                'source_type' => $sourceType,
                'partner_data' => null,
                'transaction_date' => null,
                'transaction_summary' => null
            ];
        }
        
        // Quick partner check
        $partnerData = null;
        if ($partnerId !== 'All') {
            $isKpx1074 = (strtoupper((string)$sourceType) === 'KPX' && trim((string)$partnerId) === '1074');
            if ($isKpx1074) {
                $partnerQuery = "SELECT partner_id, partner_id_kpx, gl_code, partner_name 
                               FROM masterdata.partner_masterfile 
                               WHERE partner_id_kpx = ? AND partner_name = 'SECURITY BANK' LIMIT 1";
                $stmt = $conn->prepare($partnerQuery);
                $stmt->bind_param("s", $partnerId);
            } else {
                $partnerQuery = "SELECT partner_id, partner_id_kpx, gl_code, partner_name 
                               FROM masterdata.partner_masterfile 
                               WHERE partner_id = ? OR partner_id_kpx = ? LIMIT 1";
                $stmt = $conn->prepare($partnerQuery);
                $stmt->bind_param("ss", $partnerId, $partnerId);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result && $result->num_rows > 0) {
                $partnerData = $result->fetch_assoc();
            } else {
                $errors[] = [
                    '                row' => 'Header',
                    'type' => 'partner',
                    'message' => 'Partner ID not found in database',
                    'value' => $partnerId
                ];
            }
        }
        
        // Quick Excel check - just load to verify it's valid
        $spreadsheet = loadSpreadsheet($filePath, true);
        $worksheet = $spreadsheet->getActiveSheet();
        $highestRow = $worksheet->getHighestRow();
        
        if ($highestRow < 10) {
            $errors[] = [
                'row' => 'N/A',
                'type' => 'structure',
                'message' => 'File has insufficient data rows',
                'value' => ''
            ];
        }
        
        // Don't parse all rows - just return basic info
        return [
            'valid' => count($errors) === 0,
            'row_count' => max(0, $highestRow - 9),  // Estimate
            'errors' => $errors,
            'warnings' => [],
            'preview_data' => [],  // Will be populated on demand
            'source_type' => $sourceType,
            'partner_data' => $partnerData,
            'transaction_date' => 'N/A',  // Will be read during import
            'transaction_summary' => null  // Will be calculated during import
        ];
        
    } catch (Exception $e) {
        $errors[] = [
            'row' => 'N/A',
            'type' => 'critical',
            'message' => 'File loading error: ' . $e->getMessage(),
            'value' => ''
        ];
        
        return [
            'valid' => false,
            'row_count' => 0,
            'errors' => $errors,
            'warnings' => [],
            'preview_data' => [],
            'source_type' => $sourceType,
            'partner_data' => null,
            'transaction_date' => null,
            'transaction_summary' => null
        ];
    } finally {
        if (isset($spreadsheet) && is_object($spreadsheet)) {
            try { $spreadsheet->disconnectWorksheets(); } catch (Exception $e) {}
            unset($worksheet, $spreadsheet);
            if (function_exists('gc_collect_cycles')) gc_collect_cycles();
        }
    }
}

function calculateTransactionSummary($matchedRows, $cancellationRows) {
    $summaries = [
        'net' => ['count' => 0, 'principal' => 0, 'charge_partner' => 0, 'charge_customer' => 0, 'total_charge' => 0, 'settlement' => 0],
        'adjustment' => ['count' => 0, 'principal' => 0, 'charge_partner' => 0, 'charge_customer' => 0, 'total_charge' => 0, 'settlement' => 0],
        'summary' => ['count' => 0, 'principal' => 0, 'charge_partner' => 0, 'charge_customer' => 0, 'total_charge' => 0, 'settlement' => 0]
    ];

    $cancellation_reference_numbers = [];
    foreach ($matchedRows as $row) {
        if (isset($row['numeric_number']) && $row['numeric_number'] === '*') {
            $cancellation_reference_numbers[] = $row['reference_number'];
        }
    }

    if (!empty($cancellationRows)) {
        foreach ($cancellationRows as $cancellationGroup) {
            if (is_array($cancellationGroup)) {
                if (isset($cancellationGroup[0]) && is_array($cancellationGroup[0])) {
                    foreach ($cancellationGroup as $rowArray) {
                        foreach ($rowArray as $row) {
                            if (isset($row['numeric_number']) && $row['numeric_number'] === '*') {
                                $cancellation_reference_numbers[] = $row['reference_number'];
                            }
                        }
                    }
                } else {
                    if (isset($cancellationGroup['numeric_number']) && $cancellationGroup['numeric_number'] === '*') {
                        $cancellation_reference_numbers[] = $cancellationGroup['reference_number'];
                    }
                }
            }
        }
    }

    $cancellation_reference_numbers = array_unique($cancellation_reference_numbers);

    foreach ($matchedRows as $row) {
        if (!isset($row['numeric_number']) || $row['numeric_number'] !== '*') {
            if (!in_array($row['reference_number'], $cancellation_reference_numbers)) {
                $summaries['summary']['count']++;
                $summaries['summary']['principal'] += abs(floatval($row['amount_paid'] ?? 0));
                $summaries['summary']['charge_partner'] += abs(floatval($row['amount_charge_partner'] ?? 0));
                $summaries['summary']['charge_customer'] += abs(floatval($row['amount_charge_customer'] ?? 0));
            }
        }
    }

    foreach ($matchedRows as $row) {
        if (isset($row['numeric_number']) && $row['numeric_number'] === '*') {
            $summaries['adjustment']['count']++;
            $summaries['adjustment']['principal'] += abs(floatval($row['amount_paid'] ?? 0));
            $summaries['adjustment']['charge_partner'] += abs(floatval($row['amount_charge_partner'] ?? 0));
            $summaries['adjustment']['charge_customer'] += abs(floatval($row['amount_charge_customer'] ?? 0));
        }
    }

    if (!empty($cancellationRows)) {
        foreach ($cancellationRows as $cancellationGroup) {
            if (is_array($cancellationGroup)) {
                if (isset($cancellationGroup[0]) && is_array($cancellationGroup[0])) {
                    foreach ($cancellationGroup as $rowArray) {
                        foreach ($rowArray as $row) {
                            if (isset($row['numeric_number']) && $row['numeric_number'] === '*') {
                                $summaries['adjustment']['count']++;
                                $summaries['adjustment']['principal'] += abs(floatval($row['amount_paid'] ?? 0));
                                $summaries['adjustment']['charge_partner'] += abs(floatval($row['amount_charge_partner'] ?? 0));
                                $summaries['adjustment']['charge_customer'] += abs(floatval($row['amount_charge_customer'] ?? 0));
                            }
                        }
                    }
                } else {
                    if (isset($cancellationGroup['numeric_number']) && $cancellationGroup['numeric_number'] === '*') {
                        $summaries['adjustment']['count']++;
                        $summaries['adjustment']['principal'] += abs(floatval($cancellationGroup['amount_paid'] ?? 0));
                        $summaries['adjustment']['charge_partner'] += abs(floatval($cancellationGroup['amount_charge_partner'] ?? 0));
                        $summaries['adjustment']['charge_customer'] += abs(floatval($cancellationGroup['amount_charge_customer'] ?? 0));
                    }
                }
            }
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

    return $summaries;
}

function validateFile($conn, $filePath, $sourceType, $partnerId, $importMode = 'auto') {
    $errors = [];
    $warnings = [];
    $rowCount = 0;
    $headOfficeIssues = []; // collect head office specific issues
    $hasHeadOfficeOutlet = false; // true if any row has a head-office outlet label
    $previewData = []; // Store sample rows for preview
    $matchedRows = [];
    $cancellationRows = [];
    $missingRows = []; // rows with missing Branch ID / ML Outlet / Region Code or new Branch ID
    $transactionDate = null;
    $transactionStartDate = null;
    $transactionEndDate = null;
    $cachedData = []; // Cache full data for import to avoid re-parsing Excel
    $noPartnerIds = [];
    $spreadsheet = null;
    $worksheet = null;
    $branchIdSet = loadBranchIdSet();
    $branchNameSet = loadBranchNameSet();
    
    try {
        $spreadsheet = loadSpreadsheet($filePath, true);
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
            $isKpx1074 = (strtoupper((string)$sourceType) === 'KPX' && trim((string)$partnerId) === '1074');
            if ($isKpx1074) {
                $partnerQuery = "SELECT partner_id, partner_id_kpx, gl_code, partner_name 
                               FROM masterdata.partner_masterfile 
                               WHERE partner_id_kpx = ? AND partner_name = 'SECURITY BANK' LIMIT 1";
                $stmt = $conn->prepare($partnerQuery);
                $stmt->bind_param("s", $partnerId);
            } else {
                $partnerQuery = "SELECT partner_id, partner_id_kpx, gl_code, partner_name 
                               FROM masterdata.partner_masterfile 
                               WHERE partner_id = ? OR partner_id_kpx = ? LIMIT 1";
                $stmt = $conn->prepare($partnerQuery);
                $stmt->bind_param("ss", $partnerId, $partnerId);
            }
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
            
            // Multiple mode only: detect No Partner ID from Column U and reveal Partner Name from Column V
            if (strtolower(trim((string)$importMode)) === 'multiple') {
                $uRaw = $worksheet->getCell('U' . $row)->getValue();
                $vRaw = $worksheet->getCell('V' . $row)->getValue();
                if (isEffectivelyEmptyCell($uRaw)) {
                    $partnerNameVal = trim((string)$vRaw) !== '' ? trim((string)$vRaw) : 'N/A';
                    $noPartnerIds[] = [
                        'row' => $row,
                        'partner_id' => 'No Partner ID',
                        'partner_name' => $partnerNameVal
                    ];
                    $missingRows[] = [
                        'row' => $row,
                        'missing' => ['No Partner ID'],
                        'type' => 'no_partner_id',
                        'value' => $partnerNameVal
                    ];
                    $errors[] = [
                        'row' => $row,
                        'type' => 'no_partner_id',
                        'message' => 'No Partner ID',
                        'value' => $partnerNameVal
                    ];
                }
            }
            
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

                // detect cancellation marker in column A for KP7
                $rawStatus = trim(strval($worksheet->getCell('A' . $row)->getValue()));
                $isCancellation = strpos($rawStatus, '*') !== false;
                $numericNumber = $isCancellation ? '*' : '';
                
                // Debug log first few rows to verify asterisk detection
                static $kp7ValLogCount = 0;
                if ($kp7ValLogCount < 3 && $rowCount <= 3) {
                    error_log("Validation KP7 Row {$row} - Column A: '{$rawStatus}', Detected as: " . ($isCancellation ? 'CANCELLED' : 'ACTIVE'));
                    $kp7ValLogCount++;
                }

                $rowData = [
                    'numeric_number' => $isCancellation ? '*' : '',
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
                $rawNumber = trim(strval($worksheet->getCell('A' . $row)->getValue()));
                $isCancellation = strpos($rawNumber, '*') !== false;
                $numericNumber = $isCancellation ? '*' : '';
                
                // Debug log first few rows to verify asterisk detection
                static $kpxValLogCount = 0;
                if ($kpxValLogCount < 3 && $rowCount <= 3) {
                    error_log("Validation KPX Row {$row} - Column A: '{$rawNumber}', Detected as: " . ($isCancellation ? 'CANCELLED' : 'ACTIVE'));
                    $kpxValLogCount++;
                }

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

            // Check for required location fields.
            // Multiple mode rule: Branch ID is M, ML Outlet is N.
            // Auto mode rule: Branch ID is N, ML Outlet is O.
            $branchIdCell = '';
            $branchIdCellM = '';
            $branchIdCellNForAuto = '';
            $mlOutletCell = '';
            $mlOutletCellN = '';
            $mlOutletCellOForAuto = '';
            $regionCodeCell = '';
            try {
                $branchIdCellM = trim(strval($worksheet->getCell('M' . $row)->getValue()));
                $branchIdCellNForAuto = trim(strval($worksheet->getCell('N' . $row)->getValue()));
                $mlOutletCellN = trim(strval($worksheet->getCell('N' . $row)->getValue()));
                $mlOutletCellOForAuto = trim(strval($worksheet->getCell('O' . $row)->getValue()));
                $regionCodeCell = trim(strval($worksheet->getCell('P' . $row)->getValue()));
                $branchIdCell = (strtolower(trim((string)$importMode)) === 'auto') ? $branchIdCellNForAuto : $branchIdCellM;
                $mlOutletCell = (strtolower(trim((string)$importMode)) === 'auto') ? $mlOutletCellOForAuto : $mlOutletCellN;
            } catch (Exception $e) {
                // ignore missing columns — treat as empty
            }

            $missing = [];
            $modeNorm = strtolower(trim((string)$importMode));
            $isMultipleMode = ($modeNorm === 'multiple');
            $branchIdForValidation = ($modeNorm === 'auto') ? $branchIdCellNForAuto : $branchIdCellM;
            // Auto Mode-only: if branch field contains head-office labels, resolve to canonical branch IDs.
            if ($modeNorm === 'auto') {
                $autoBranchText = strtoupper(preg_replace('/\s+/', ' ', trim((string)$branchIdForValidation)));
                if ($autoBranchText === 'ML CEBU HEAD OFFICE' || $autoBranchText === 'CEBU HEAD OFFICE') {
                    $branchIdForValidation = '581';
                } elseif ($autoBranchText === 'ML HEAD OFFICE' || $autoBranchText === 'HEAD OFFICE') {
                    $branchIdForValidation = '2607';
                }
            }
            $isBranchIdEmpty = isEffectivelyEmptyCell($branchIdForValidation) || strtoupper(trim((string)$branchIdForValidation)) === 'NAN';
            if ($isBranchIdEmpty) {
                $missingRows[] = [
                    'row' => $row,
                    'missing' => ['No Branch ID'],
                    'type' => 'no_branch_id',
                    'value' => $mlOutletCellN
                ];
                $errors[] = [
                    'row' => $row,
                    'type' => 'no_branch_id',
                    'message' => 'No Branch ID',
                    'value' => $mlOutletCellN
                ];
            }
            if (isEffectivelyEmptyCell($mlOutletCell) || strtoupper(trim((string)$mlOutletCell)) === 'NAN') $missing[] = 'ML Outlet';
            if (isEffectivelyEmptyCell($regionCodeCell) || strtoupper(trim((string)$regionCodeCell)) === 'NAN') $missing[] = 'Region Code';

            if (!empty($missing)) {
                $missingValue = '';
                $missingRows[] = [
                    'row' => $row,
                    'missing' => $missing,
                    'type' => 'missing_fields',
                    'value' => $missingValue
                ];
                $errors[] = [
                    'row' => $row,
                    'type' => 'missing_fields',
                    'message' => 'Missing fields: ' . implode(', ', $missing),
                    'value' => $missingValue
                ];
            }

            // -----------------------
            // Head Office validation
            // -----------------------
            // Detect ML CEBU HEAD OFFICE outlet issues: outlet should be exactly 'ML CEBU HEAD OFFICE'
            // and branch id MUST be 581. If outlet matches but branch id is missing/wrong,
            // flag this row as a Head Office issue (file-level Head Office status).
            try {
                $normalizedOutletForDetect = strtoupper(preg_replace('/\s+/', ' ', $mlOutletCell));
                if (
                    $normalizedOutletForDetect === 'ML CEBU HEAD OFFICE' ||
                    $normalizedOutletForDetect === 'CEBU HEAD OFFICE' ||
                    $normalizedOutletForDetect === 'ML HEAD OFFICE' ||
                    $normalizedOutletForDetect === 'HEAD OFFICE'
                ) {
                    // Mode-specific rule for showing Fix All Head Office Issues button.
                    if ($isMultipleMode) {
                        if ($isBranchIdEmpty) {
                            $hasHeadOfficeOutlet = true;
                        }
                    } elseif ($modeNorm === 'auto') {
                        if ($isBranchIdEmpty) {
                            $hasHeadOfficeOutlet = true;
                        }
                    } else {
                        $hasHeadOfficeOutlet = true;
                    }
                }

                if (strtoupper($mlOutletCell) === 'ML CEBU HEAD OFFICE' || strtoupper($mlOutletCell) === 'CEBU HEAD OFFICE') {
                    $normalizedBranch = normalizeBranchId($branchIdForValidation);
                    if ($normalizedBranch === '' || strtoupper($normalizedBranch) === 'NAN' || $normalizedBranch !== '581') {
                        $headOfficeIssues[] = [
                            'row' => $row,
                            'outlet' => $mlOutletCell,
                            'issue' => 'Wrong / Missing Branch ID',
                            'value' => ($branchIdCell === '' ? 'Empty' : $branchIdCell)
                        ];
                        // Add a non-blocking warning to warnings array for visibility
                        $warnings[] = [
                            'row' => $row,
                            'type' => 'head_office',
                            'message' => 'ML CEBU HEAD OFFICE with wrong/missing Branch ID',
                            'value' => ($branchIdCell === '' ? 'Empty' : $branchIdCell)
                        ];
                    }
                } else if (strtoupper($mlOutletCell) === 'ML HEAD OFFICE' || strtoupper($mlOutletCell) === 'HEAD OFFICE') {
                    $normalizedBranch = normalizeBranchId($branchIdForValidation);
                    if ($normalizedBranch === '' || strtoupper($normalizedBranch) === 'NAN' || $normalizedBranch !== '2607') {
                        $headOfficeIssues[] = [
                            'row' => $row,
                            'outlet' => $mlOutletCell,
                            'issue' => 'Wrong / Missing Branch ID',
                            'value' => ($branchIdCell === '' ? 'Empty' : $branchIdCell)
                        ];
                        // Add a non-blocking warning to warnings array for visibility
                        $warnings[] = [
                            'row' => $row,
                            'type' => 'head_office',
                            'message' => 'ML HEAD OFFICE with wrong/missing Branch ID',
                            'value' => ($branchIdCell === '' ? 'Empty' : $branchIdCell)
                        ];
                    }
                }
            } catch (Exception $e) {
                // ignore any unexpected issues here
            }

            $normalizedBranchId = normalizeBranchId($branchIdForValidation);
            $normalizedBranchName = normalizeBranchNameKey($branchIdForValidation);
            $isKnownBranch = false;
            if (!empty($branchIdSet) && $normalizedBranchId !== '' && isset($branchIdSet[$normalizedBranchId])) {
                $isKnownBranch = true;
            }
            if (!$isKnownBranch && !empty($branchNameSet) && $normalizedBranchName !== '' && isset($branchNameSet[$normalizedBranchName])) {
                $isKnownBranch = true;
            }
            if (!$isBranchIdEmpty && !$isKnownBranch) {
                $missingRows[] = [
                    'row' => $row,
                    'missing' => ['New Branch ID'],
                    'type' => 'new_branch_id',
                    'value' => $branchIdCell
                ];
                $errors[] = [
                    'row' => $row,
                    'type' => 'new_branch_id',
                    'message' => 'New Branch ID: ' . $branchIdCell,
                    'value' => $branchIdCell
                ];
            }

            // Store data for transaction summary
            if (!empty($referenceNumber)) {
                $summaryRow = [
                    'reference_number' => $referenceNumber,
                    'numeric_number' => $numericNumber,
                    'amount_paid' => $amountPaid,
                    'amount_charge_partner' => $amountChargePartner,
                    'amount_charge_customer' => $amountChargeCustomer
                ];

                if ($isCancellation) {
                    $cancellationRows[] = $summaryRow;
                } else {
                    $matchedRows[] = $summaryRow;
                }
            }
            
            // compute min/max datetimes for start/end dates using preview row values
            if (!empty($rowData) && isset($rowData['transaction_date'])) {
                $dtStr = $rowData['transaction_date'];
                if (!empty($rowData['transaction_time'])) {
                    $dtStr = trim($dtStr . ' ' . $rowData['transaction_time']);
                }
                $dtTs = strtotime($dtStr);
                if ($dtTs !== false) {
                    $iso = date('Y-m-d H:i:s', $dtTs);
                    if ($transactionStartDate === null || $iso < $transactionStartDate) {
                        $transactionStartDate = $iso;
                    }
                    if ($transactionEndDate === null || $iso > $transactionEndDate) {
                        $transactionEndDate = $iso;
                    }
                }
            }

            // Store preview data: first 10 rows + ALL cancelled transactions
            // This ensures cancelled transactions always show in preview filter
            if ($rowCount <= 10 || $isCancellation) {
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
    } finally {
        if (isset($spreadsheet) && is_object($spreadsheet)) {
            try { $spreadsheet->disconnectWorksheets(); } catch (Exception $e) {}
            unset($worksheet, $spreadsheet);
            if (function_exists('gc_collect_cycles')) gc_collect_cycles();
        }
    }
    
    // Calculate summaries (match original logic)
    $summaries = calculateTransactionSummary($matchedRows, $cancellationRows);
    
    // Log validation detection results for debugging
    error_log("Validation detection for {$filePath}: " . count($matchedRows) . " active, " . count($cancellationRows) . " cancelled (total {$rowCount} rows)");
    
    return [
        // valid only when no errors/missing and no head office issues
        'valid' => (count($errors) === 0 && count($missingRows) === 0 && empty($headOfficeIssues)),
        'row_count' => $rowCount,
        'errors' => $errors,
        'warnings' => $warnings,
        'preview_data' => $previewData,
        'missing_rows' => $missingRows,
        'head_office_issues' => $headOfficeIssues,
        'has_head_office_outlet' => $hasHeadOfficeOutlet,
        'validation_rule_version' => 2,
        'source_type' => $sourceType,
        'partner_data' => $partnerData,
        'transaction_date' => $transactionDate,
        'transaction_start_date' => $transactionStartDate,
        'transaction_end_date' => $transactionEndDate,
        'transaction_summary' => $summaries,
        'no_partner_ids' => $noPartnerIds
    ];
}

function isEffectivelyEmptyCell($value) {
    if ($value === null) return true;
    $v = trim((string)$value);
    if ($v === '') return true;
    if (strtoupper($v) === 'NULL') return true;
    if ($v === "''") return true;
    return false;
}

function importFileData($conn, $filePath, $sourceType, $partnerId, $currentUserEmail, $progressToken = null, $cachedData = null, $userDecision = 'skip', $fileMeta = null) {
    try {
        // Always parse Excel (simple and reliable like old code)
        $spreadsheet = loadSpreadsheet($filePath, true);
        $worksheet = $spreadsheet->getActiveSheet();
        $highestRow = $worksheet->getHighestRow();
        $highestColumn = $worksheet->getHighestColumn();

        $insertCount = 0;
        $errors = [];

        // Get partner data
        $isKpx1074 = (strtoupper((string)$sourceType) === 'KPX' && trim((string)$partnerId) === '1074');
        if ($isKpx1074) {
            $partnerQuery = "SELECT partner_id, partner_id_kpx, gl_code, partner_name 
                            FROM masterdata.partner_masterfile 
                            WHERE partner_id_kpx = ? AND partner_name = 'SECURITY BANK' LIMIT 1";
            $stmt = $conn->prepare($partnerQuery);
            $stmt->bind_param("s", $partnerId);
        } else {
            $partnerQuery = "SELECT partner_id, partner_id_kpx, gl_code, partner_name 
                            FROM masterdata.partner_masterfile 
                            WHERE partner_id = ? OR partner_id_kpx = ? LIMIT 1";
            $stmt = $conn->prepare($partnerQuery);
            $stmt->bind_param("ss", $partnerId, $partnerId);
        }
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
        $PartnerName = $isKpx1074 ? 'SECURITY BANK' : $partnerData['partner_name'];
        $BillersName = '';
        $sub_billers_id = null;
        $importMode = is_array($fileMeta) && !empty($fileMeta['import_mode']) ? strtolower(trim((string)$fileMeta['import_mode'])) : 'auto';
        if ($isKpx1074 && is_array($fileMeta) && !empty($fileMeta['billers_name'])) {
            $BillersName = trim((string)$fileMeta['billers_name']);
        }
        if (is_array($fileMeta) && array_key_exists('sub_billers_id', $fileMeta)) {
            $tmpSubBillersId = trim((string)$fileMeta['sub_billers_id']);
            if ($tmpSubBillersId !== '') {
                $sub_billers_id = $tmpSubBillersId;
            }
        }

        // Read row 9 column headers
        $getColumnLabels = [];
        $columnIterator = $worksheet->getRowIterator(9, 9)->current()->getCellIterator('A', $highestColumn);
        foreach ($columnIterator as $cell) {
            $getColumnLabels[] = trim(strval($cell->getValue()));
        }

        // Extract header report date from cell B3 (commonly present for KP7/KPX)
        $headerReportRaw = trim(strval($worksheet->getCell('B3')->getValue()));
        $headerReport = null;
        if ($headerReportRaw !== '') {
            $ts = strtotime($headerReportRaw);
            if ($ts !== false) $headerReport = date('Y-m-d', $ts);
        }

        // Prefer file metadata report date (from upload client) if provided
        $reportDateCandidate = null;
        if (is_array($fileMeta)) {
            if (!empty($fileMeta['report_date'])) {
                $reportDateCandidate = $fileMeta['report_date'];
            } elseif (!empty($fileMeta['report_date_raw'])) {
                $tmp = trim($fileMeta['report_date_raw']);
                $ts = strtotime($tmp);
                $reportDateCandidate = ($ts !== false) ? date('Y-m-d', $ts) : $tmp;
            }
        }

        // If still not set, use header report
        if (empty($reportDateCandidate) && !empty($headerReport)) {
            $reportDateCandidate = $headerReport;
        }

        // $reportDateCandidate may remain null; downstream logic will decide per-row fallback.

        // Helper functions (duplicate and override checks)
        $checkDuplicateData = function($referenceNumber, $datetime, $partnerIdParam = null, $subBillersIdParam = null) use ($conn) {
            $sql = "SELECT COUNT(*) as count FROM mldb.billspayment_transaction 
                    WHERE post_transaction='posted' AND reference_no = ? 
                    AND (`datetime` = ? OR cancellation_date = ?) LIMIT 1";
            // If a partner id is provided, require either partner_id or partner_id_kpx to match
            if (!empty($subBillersIdParam)) {
            $sql = "SELECT COUNT(*) as count FROM mldb.billspayment_transaction 
                WHERE post_transaction='posted' AND reference_no = ? 
                AND (`datetime` = ? OR cancellation_date = ?) 
                AND sub_billers_id = ? LIMIT 1";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssss", $referenceNumber, $datetime, $datetime, $subBillersIdParam);
            } elseif (!empty($partnerIdParam) && strtoupper($partnerIdParam) !== 'ALL') {
                $sql = "SELECT COUNT(*) as count FROM mldb.billspayment_transaction 
                        WHERE post_transaction='posted' AND reference_no = ? 
                        AND (`datetime` = ? OR cancellation_date = ?) 
                        AND (partner_id = ? OR partner_id_kpx = ?) LIMIT 1";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sssss", $referenceNumber, $datetime, $datetime, $partnerIdParam, $partnerIdParam);
            } else {
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sss", $referenceNumber, $datetime, $datetime);
            }
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

        $checkHasAlreadyDataReadyToOverride = function($referenceNumber, $datetime, $partnerIdParam = null, $subBillersIdParam = null) use ($conn) {
            $sql = "SELECT COUNT(*) as count FROM mldb.billspayment_transaction 
                    WHERE post_transaction='unposted' AND reference_no = ? 
                    AND (`datetime` = ? OR cancellation_date = ?) LIMIT 1";
            if (!empty($subBillersIdParam)) {
            $sql = "SELECT COUNT(*) as count FROM mldb.billspayment_transaction 
                WHERE post_transaction='unposted' AND reference_no = ? 
                AND (`datetime` = ? OR cancellation_date = ?) 
                AND sub_billers_id = ? LIMIT 1";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssss", $referenceNumber, $datetime, $datetime, $subBillersIdParam);
            } elseif (!empty($partnerIdParam) && strtoupper($partnerIdParam) !== 'ALL') {
                $sql = "SELECT COUNT(*) as count FROM mldb.billspayment_transaction 
                        WHERE post_transaction='unposted' AND reference_no = ? 
                        AND (`datetime` = ? OR cancellation_date = ?) 
                        AND (partner_id = ? OR partner_id_kpx = ?) LIMIT 1";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sssss", $referenceNumber, $datetime, $datetime, $partnerIdParam, $partnerIdParam);
            } else {
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sss", $referenceNumber, $datetime, $datetime);
            }
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
            // Use the best available report date for all rows: prefer file-level candidate, then header, then transaction date later
            $report_date = $reportDateCandidate;

            if ($getColumnLabels[0] === 'STATUS' && (strtoupper($sourceType) === 'KP7')) {
                $cellAValue = $worksheet->getCell('A' . $row)->getValue();
                $isCancellation = strpos($cellAValue, '*') !== false;
                $cancellStatus = $isCancellation ? '*' : '';
                
                // Debug log first few rows to verify asterisk detection
                static $kp7LogCount = 0;
                if ($kp7LogCount < 5) {
                    error_log("KP7 Row {$row} - Column A: '{$cellAValue}', Detected as: " . ($isCancellation ? 'CANCELLED' : 'ACTIVE'));
                    $kp7LogCount++;
                }

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

            } elseif ($getColumnLabels[0] === 'No' && (strtoupper($sourceType) === 'KPX')) {
                $cellAValue = $worksheet->getCell('A' . $row)->getValue();
                $isCancellation = strpos($cellAValue, '*') !== false;
                $cancellStatus = $isCancellation ? '*' : '';
                
                // Debug log first few rows to verify asterisk detection
                static $kpxLogCount = 0;
                if ($kpxLogCount < 5) {
                    error_log("KPX Row {$row} - Column A: '{$cellAValue}', Detected as: " . ($isCancellation ? 'CANCELLED' : 'ACTIVE'));
                    $kpxLogCount++;
                }

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
                    $other_details = strval($worksheet->getCell('L' . $row)->getValue());

                    // Auto Mode: read branch ID from column N (keep Multiple Mode behavior unchanged elsewhere)
                    $branch_id_raw = (strtolower(trim((string)$importMode)) === 'auto')
                        ? $worksheet->getCell('N' . $row)->getValue()
                        : $worksheet->getCell('M' . $row)->getValue();
                    $branch_outlet_raw = strval($worksheet->getCell('N' . $row)->getValue());
                    if (isset($getColumnLabels[13]) && $getColumnLabels[13] === 'Branch ID') {
                        if (is_numeric($branch_id_raw)) {
                            $cntl_num_for_region = ($branch_id_raw == 581) ? intval(2607) : intval($branch_id_raw);
                        } elseif ($branch_id_raw === 'ML HEAD OFFICE' || $branch_id_raw === 'HEAD OFFICE') {
                            $cntl_num_for_region = intval(2607);
                        }
                        if ($branch_outlet_raw === 'ML CEBU HEAD OFFICE' || $branch_outlet_raw === 'CEBU HEAD OFFICE') {
                            $cntl_num_for_region = intval(581);
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
                    $other_details = strval($worksheet->getCell('L' . $row)->getValue());

                    // Auto Mode: read branch ID from column N (keep Multiple Mode behavior unchanged elsewhere)
                    $branch_id_raw = (strtolower(trim((string)$importMode)) === 'auto')
                        ? $worksheet->getCell('N' . $row)->getValue()
                        : $worksheet->getCell('M' . $row)->getValue();
                    if (isset($getColumnLabels[14]) && $getColumnLabels[14] === 'Branch ID') {
                        if (is_numeric($branch_id_raw)) {
                            $cntl_num_for_region = ($branch_id_raw == 581) ? intval(2607) : intval($branch_id_raw);
                        } elseif ($branch_id_raw === 'ML HEAD OFFICE' || $branch_id_raw === 'HEAD OFFICE') {
                            $cntl_num_for_region = intval(2607);
                        }elseif($branch_id_raw === 'ML CEBU HEAD OFFICE' || $branch_id_raw === 'CEBU HEAD OFFICE'){
                            $cntl_num_for_region = intval(581);
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
                // Log warning for unrecognized format but continue processing
                $errors[] = "Warning: Row $row has unrecognized column format - attempting to process anyway";
            }

            // Log warnings for missing critical fields but don't skip - let insert/error handling deal with it
            if (empty($reference_number)) {
                $errors[] = "Warning: Row $row missing reference_number - may fail during insert";
            }
            if (empty($datetime)) {
                $errors[] = "Warning: Row $row missing datetime - may fail during insert";
            }

            // Multiple mode only: detect partner id in column U per-row.
            // If missing (empty, '', null), mark as "No Partner ID" and reveal partner name from column V.
            $rowPartnerId = $PartnerID;
            $rowPartnerIdKpx = $PartnerID_KPX;
            $rowPartnerName = $PartnerName;
            $rowGLCode = $GLCode;
            if ($importMode === 'multiple') {
                $rowGLCode = null;
            }
            if ($importMode === 'multiple') {
                $rawPartnerIdU = $worksheet->getCell('U' . $row)->getValue();
                $rowPartnerIdU = trim((string)$rawPartnerIdU);
                $rowPartnerNameV = trim((string)$worksheet->getCell('V' . $row)->getValue());
                if (isEffectivelyEmptyCell($rawPartnerIdU)) {
                    $rowPartnerId = 'No Partner ID';
                    $rowPartnerIdKpx = 'No Partner ID';
                    if ($rowPartnerNameV !== '') {
                        $rowPartnerName = $rowPartnerNameV;
                    }
                } else {
                    $rowPartnerId = $rowPartnerIdU;
                    $rowPartnerIdKpx = $rowPartnerIdU;
                    // Multiple Mode: resolve row partner using partner_id_kpx (Auto concept applied per-row)
                    $rowPartnerQuery = "SELECT partner_id, partner_id_kpx, partner_name, gl_code
                                        FROM masterdata.partner_masterfile
                                        WHERE partner_id_kpx = ? LIMIT 1";
                    $rowStmt = $conn->prepare($rowPartnerQuery);
                    if ($rowStmt) {
                        $rowStmt->bind_param("s", $rowPartnerIdU);
                        $rowStmt->execute();
                        $rowRes = $rowStmt->get_result();
                        if ($rowRes && $rowRes->num_rows > 0) {
                            $rowP = $rowRes->fetch_assoc();
                            $dbPartnerId = isset($rowP['partner_id']) ? trim((string)$rowP['partner_id']) : '';
                            $dbPartnerIdKpx = isset($rowP['partner_id_kpx']) ? trim((string)$rowP['partner_id_kpx']) : '';
                            $rowPartnerId = ($dbPartnerId === '') ? null : $dbPartnerId;
                            $rowPartnerIdKpx = ($dbPartnerIdKpx === '') ? $rowPartnerIdU : $dbPartnerIdKpx;
                            if (!empty($rowP['partner_name'])) {
                                $rowPartnerName = $rowP['partner_name'];
                            }
                            $dbGlCode = isset($rowP['gl_code']) ? trim((string)$rowP['gl_code']) : '';
                            $rowGLCode = ($dbGlCode === '') ? null : $dbGlCode;
                        } elseif ($rowPartnerNameV !== '') {
                            $rowPartnerName = $rowPartnerNameV;
                        }
                        $rowStmt->close();
                    } elseif ($rowPartnerNameV !== '') {
                        $rowPartnerName = $rowPartnerNameV;
                    }
                }
            }

            // Check for duplicates and overrides but don't skip - just log warnings
            // The delete-then-insert logic below will handle these cases
            $dupCheckPartnerId = ($importMode === 'multiple') ? $rowPartnerId : $partnerId;
            if ($checkDuplicateData($reference_number, $datetime, $dupCheckPartnerId, $sub_billers_id)) {
                $errors[] = "Warning: Duplicate found for reference {$reference_number} - will be handled by delete-then-insert";
            }

            if ($checkHasAlreadyDataReadyToOverride($reference_number, $datetime, $dupCheckPartnerId, $sub_billers_id)) {
                $errors[] = "Warning: Unposted data exists for reference {$reference_number} - will be overridden";
            }

            // Final Multiple Mode fallback for branch_id to prevent null inserts.
            if ($importMode === 'multiple' && ($branch_id === null || $branch_id === '' || strtoupper(trim((string)$branch_id)) === 'NAN')) {
                $branchIdCellM = trim((string)$worksheet->getCell('M' . $row)->getValue());
                $mlOutletCellN = strtoupper(preg_replace('/\s+/', ' ', trim((string)$worksheet->getCell('N' . $row)->getValue())));
                if (is_numeric($branchIdCellM)) {
                    $branch_id = intval($branchIdCellM);
                } elseif ($mlOutletCellN === 'ML CEBU HEAD OFFICE' || $mlOutletCellN === 'CEBU HEAD OFFICE') {
                    $branch_id = 581;
                } elseif ($mlOutletCellN === 'ML HEAD OFFICE' || $mlOutletCellN === 'HEAD OFFICE') {
                    $branch_id = 2607;
                }
            }

            // Multiple Mode: if region/zone missing, derive from resolved branch_id via branch_profile (same core lookup idea)
            if ($importMode === 'multiple' && is_numeric($branch_id) && (empty($region_code) || empty($zone_code))) {
                $bpSql = "SELECT region_code, zone FROM masterdata.branch_profile WHERE branch_id = ? LIMIT 1";
                $bpStmt = $conn->prepare($bpSql);
                if ($bpStmt) {
                    $bpid = intval($branch_id);
                    $bpStmt->bind_param("i", $bpid);
                    $bpStmt->execute();
                    $bpRes = $bpStmt->get_result();
                    if ($bpRes && $bpRes->num_rows > 0) {
                        $bpRow = $bpRes->fetch_assoc();
                        if (empty($region_code) && !empty($bpRow['region_code'])) $region_code = $bpRow['region_code'];
                        if (empty($zone_code) && !empty($bpRow['zone'])) $zone_code = $bpRow['zone'];
                    }
                    $bpStmt->close();
                }
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
                'partner_name' => $rowPartnerName,
                'billers_name' => $BillersName,
                'sub_billers_id' => $sub_billers_id,
                'partner_id' => $rowPartnerId,
                'PartnerID_KPX' => $rowPartnerIdKpx,
                'GLCode' => $rowGLCode,
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

        // Log detection results for debugging
        $totalRowsRead = $highestRow - 9;
        $activeCount = count($matchedData);
        $cancelledCount = count($cancellationData);
        error_log("[IMPORT DEBUG] Excel parsing complete: {$totalRowsRead} rows read, {$activeCount} active, {$cancelledCount} cancelled");
        error_log("[IMPORT DEBUG] File: {$filePath}");

        if (empty($matchedData) && empty($cancellationData)) {
            error_log("[IMPORT ERROR] No valid rows found in file: {$filePath}");
            return [
                'success' => false,
                'error' => !empty($errors) ? implode('; ', array_slice($errors, 0, 5)) : 'No valid rows to import'
            ];
        }

        $raw_matched_data = array_merge($matchedData, $cancellationData);
        $processed_data = [];
        $cancellation_refs = [];
        $regular_refs = [];

        if ($sourceType === 'KP7' || strtoupper($sourceType) === 'KP7') {
            $processed_data = $raw_matched_data;
        } elseif ($sourceType === 'KPX' || strtoupper($sourceType) === 'KPX') {
            foreach ($raw_matched_data as $row) {
                $is_cancellation = isset($row['numeric_number']) && $row['numeric_number'] === '*';
                if ($is_cancellation) {
                    $cancellation_refs[$row['reference_number']] = $row;
                } else {
                    $regular_refs[$row['reference_number']] = $row;
                }
            }

            // Insert regular rows first; if cancelled partner exists on same reference,
            // attach cancellation datetime to regular row, then insert cancellation row.
            foreach ($regular_refs as $ref_no => $regular_row) {
                if (isset($cancellation_refs[$ref_no]) && !empty($cancellation_refs[$ref_no]['datetime'])) {
                    $regular_row['cancellation_date_override'] = $cancellation_refs[$ref_no]['datetime'];
                }
                $processed_data[] = $regular_row;
            }

            foreach ($cancellation_refs as $ref_no => $cancellation_row) {
                if (isset($regular_refs[$ref_no])) {
                    $cancellation_row['regular_datetime'] = $regular_refs[$ref_no]['datetime'];
                }
                $processed_data[] = $cancellation_row;
            }
        }

        // Log processed data count before insert
        error_log("[IMPORT DEBUG] Processed data array prepared: " . count($processed_data) . " rows ready for insert");
        
        // Build reference datetime map for cancellations (helps match cancellations to regular transactions)
        $reference_datetime_map = [];
        foreach ($processed_data as $r) {
            if (isset($r['numeric_number']) && $r['numeric_number'] !== '*' && !empty($r['datetime'])) {
                $reference_datetime_map[$r['reference_number']] = $r['datetime'];
            }
        }

        // Insert processed data
        $conn->autocommit(FALSE);

        $insertSQL = "INSERT INTO mldb.billspayment_transaction (
            status, 
            datetime, 
            cancellation_date, 
            report_date,
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
            sub_billers_id, 
            sub_billers_name,
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
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $insertStmt = $conn->prepare($insertSQL);
        
        // Track insert statistics
        $insertAttempts = 0;
        $insertSuccess = 0;
        $insertFailures = 0;
        $deleteCount = 0;
        // Collect detailed info about rows deleted during delete-then-insert so we can return them to the client
        $deleted_rows_details = [];
        error_log("[IMPORT DEBUG] Starting insert loop for " . count($processed_data) . " rows");

        foreach ($processed_data as $idx => $row) {
            $insertAttempts++;
            if (
                isset($row['partner_id']) &&
                strtoupper(trim((string)$row['partner_id'])) === 'NO PARTNER ID'
            ) {
                $errors[] = "Skipped row {$idx}: No Partner ID";
                continue;
            }
            $is_cancellation = isset($row['numeric_number']) && $row['numeric_number'] === '*';
            
            // Log first 5 and last 5 insert attempts
            if ($insertAttempts <= 5 || $insertAttempts > (count($processed_data) - 5)) {
                error_log("[IMPORT DEBUG] Row {$insertAttempts}: Ref={$row['reference_number']}, Status=" . ($is_cancellation ? 'CANCELLED' : 'ACTIVE'));
            }
            $status = $is_cancellation ? '*' : null;

            $datetime_value = null;
            $cancellation_date = null;

            if ($is_cancellation && isset($row['regular_datetime'])) {
                $datetime_value = $row['regular_datetime'];
                $cancellation_date = $row['datetime'];
            } elseif ($is_cancellation) {
                if (strtoupper($sourceType) === 'KP7') {
                    $datetime_value = $row['datetime'];
                    $cancellation_date = $row['report_date'] ? date('Y-m-d H:i:s', strtotime($row['report_date'])) : null;
                } elseif (strtoupper($sourceType) === 'KPX') {
                    $cancellation_date = $row['datetime'];
                    $datetime_value = null;
                }
            } else {
                $datetime_value = $row['datetime'];
                $cancellation_date = $row['cancellation_date_override'] ?? null;
            }

            // If cancellation missing regular datetime, try to resolve from map
            if ($is_cancellation && empty($datetime_value) && isset($reference_datetime_map[$row['reference_number']])) {
                $datetime_value = $reference_datetime_map[$row['reference_number']];
            }

            // KPX cancellation behavior (Auto-mode concept):
            // keep cancellation row insertable, and also attach cancelled row datetime
            // into cancellation_date of the matched regular transaction row.
            if ($is_cancellation && strtoupper($sourceType) === 'KPX' && !empty($datetime_value) && !empty($cancellation_date)) {
                $updSQL = "UPDATE mldb.billspayment_transaction
                           SET cancellation_date = ?
                           WHERE reference_no = ?
                             AND `datetime` = ?
                             AND post_transaction = 'unposted'
                             AND status IS NULL";
                if (!empty($row['sub_billers_id'])) {
                    $updSQL .= " AND sub_billers_id = ?";
                    $updStmt = $conn->prepare($updSQL);
                    if ($updStmt) {
                        $u_cancel = $cancellation_date;
                        $u_ref = $row['reference_number'];
                        $u_dt = $datetime_value;
                        $u_sub = $row['sub_billers_id'];
                        $updStmt->bind_param("ssss", $u_cancel, $u_ref, $u_dt, $u_sub);
                        $updStmt->execute();
                        $updStmt->close();
                    }
                } elseif (!empty($row['PartnerID_KPX'])) {
                    $updSQL .= " AND partner_id_kpx = ?";
                    $updStmt = $conn->prepare($updSQL);
                    if ($updStmt) {
                        $u_cancel = $cancellation_date;
                        $u_ref = $row['reference_number'];
                        $u_dt = $datetime_value;
                        $u_pkpx = $row['PartnerID_KPX'];
                        $updStmt->bind_param("ssss", $u_cancel, $u_ref, $u_dt, $u_pkpx);
                        $updStmt->execute();
                        $updStmt->close();
                    }
                }
            }

            // Handle user decision: override vs skip
            $post_trans = $row['post_transaction'] ?? 'unposted';
            $deleteStmt = null;
            
            if ($userDecision === 'skip') {
                // Skip mode: Check if record exists, and if it does, skip insertion
                // First check if a matching record exists
                $checkSQL = "SELECT COUNT(*) as count FROM mldb.billspayment_transaction WHERE reference_no = ? AND (`datetime` = ? OR cancellation_date = ?) AND status <=> ?";
                if (!empty($row['sub_billers_id'])) {
                    $checkSQL .= " AND sub_billers_id = ?";
                    $checkStmt = $conn->prepare($checkSQL);
                    if ($checkStmt) {
                        $c_reference = $row['reference_number'];
                        $c_datetime = $datetime_value ?? '';
                        $c_cancellation = $cancellation_date ?? '';
                        $c_status = $status;
                        $c_sub_billers_id = $row['sub_billers_id'];
                        $checkStmt->bind_param("sssss", $c_reference, $c_datetime, $c_cancellation, $c_status, $c_sub_billers_id);
                    }
                } elseif (strtoupper($sourceType) === 'KP7') {
                    $checkSQL .= " AND partner_id = ?";
                    $checkStmt = $conn->prepare($checkSQL);
                    if ($checkStmt) {
                        $c_reference = $row['reference_number'];
                        $c_datetime = $datetime_value ?? '';
                        $c_cancellation = $cancellation_date ?? '';
                        $c_status = $status;
                        $c_partner = $row['partner_id'];
                        $checkStmt->bind_param("sssss", $c_reference, $c_datetime, $c_cancellation, $c_status, $c_partner);
                    }
                } elseif (strtoupper($sourceType) === 'KPX') {
                    $checkSQL .= " AND partner_id_kpx = ?";
                    $checkStmt = $conn->prepare($checkSQL);
                    if ($checkStmt) {
                        $c_reference = $row['reference_number'];
                        $c_datetime = $datetime_value ?? '';
                        $c_cancellation = $cancellation_date ?? '';
                        $c_status = $status;
                        $c_partner_kpx = $row['PartnerID_KPX'];
                        $checkStmt->bind_param("sssss", $c_reference, $c_datetime, $c_cancellation, $c_status, $c_partner_kpx);
                    }
                } else {
                    $checkStmt = $conn->prepare($checkSQL);
                    if ($checkStmt) {
                        $c_reference = $row['reference_number'];
                        $c_datetime = $datetime_value ?? '';
                        $c_cancellation = $cancellation_date ?? '';
                        $c_status = $status;
                        $checkStmt->bind_param("ssss", $c_reference, $c_datetime, $c_cancellation, $c_status);
                    }
                }
                
                if (isset($checkStmt) && $checkStmt) {
                    $checkStmt->execute();
                    $checkResult = $checkStmt->get_result();
                    $checkRow = $checkResult->fetch_assoc();
                    $checkStmt->close();
                    
                    // If record exists, skip this row
                    if ($checkRow && $checkRow['count'] > 0) {
                        continue; // Skip insertion for this row
                    }
                }
            } elseif ($userDecision === 'override') {
                // Override mode: Delete existing records with same reference and datetime before inserting
                // This allows updating existing records with new data
                // Delete existing unposted records for same reference and datetime/cancellation_date to avoid duplicates
                // IMPORTANT: Also match status so we don't delete regular transactions when inserting cancellations (or vice versa)
                $deleteSQL = "DELETE FROM mldb.billspayment_transaction WHERE post_transaction = ? AND reference_no = ? AND (`datetime` = ? OR cancellation_date = ? ) AND status <=> ?";
                
                if (!empty($row['sub_billers_id'])) {
                    $deleteSQL .= " AND sub_billers_id = ?";
                    $deleteStmt = $conn->prepare($deleteSQL);
                    if ($deleteStmt) {
                        $d_post_trans = $post_trans;
                        $d_reference = $row['reference_number'];
                        $d_datetime = $datetime_value ?? '';
                        $d_cancellation = $cancellation_date ?? '';
                        $d_status = $status;
                        $d_sub_billers_id = $row['sub_billers_id'];
                        $deleteStmt->bind_param("ssssss", $d_post_trans, $d_reference, $d_datetime, $d_cancellation, $d_status, $d_sub_billers_id);
                    }
                } elseif (strtoupper($sourceType) === 'KP7') {
                    $deleteSQL .= " AND partner_id = ?";
                    $deleteStmt = $conn->prepare($deleteSQL);
                    if ($deleteStmt) {
                        $d_post_trans = $post_trans;
                        $d_reference = $row['reference_number'];
                        $d_datetime = $datetime_value ?? '';
                        $d_cancellation = $cancellation_date ?? '';
                        $d_status = $status;
                        $d_partner = $row['partner_id'];
                        $deleteStmt->bind_param("ssssss", $d_post_trans, $d_reference, $d_datetime, $d_cancellation, $d_status, $d_partner);
                    }
                } elseif (strtoupper($sourceType) === 'KPX') {
                    $deleteSQL .= " AND partner_id_kpx = ?";
                    $deleteStmt = $conn->prepare($deleteSQL);
                    if ($deleteStmt) {
                        $d_post_trans = $post_trans;
                        $d_reference = $row['reference_number'];
                        $d_datetime = $datetime_value ?? '';
                        $d_cancellation = $cancellation_date ?? '';
                        $d_status = $status;
                        $d_partner_kpx = $row['PartnerID_KPX'];
                        $deleteStmt->bind_param("ssssss", $d_post_trans, $d_reference, $d_datetime, $d_cancellation, $d_status, $d_partner_kpx);
                    }
                } else {
                    $deleteStmt = $conn->prepare($deleteSQL);
                    if ($deleteStmt) {
                        $d_post_trans = $post_trans;
                        $d_reference = $row['reference_number'];
                        $d_datetime = $datetime_value ?? '';
                        $d_cancellation = $cancellation_date ?? '';
                        $d_status = $status;
                        $deleteStmt->bind_param("sssss", $d_post_trans, $d_reference, $d_datetime, $d_cancellation, $d_status);
                    }
                }

            if (isset($deleteStmt) && $deleteStmt) {
                // Debug: fetch and log existing rows that will be deleted so we can verify in DB
                $selectSQL = "SELECT id, reference_no, status, `datetime`, cancellation_date, partner_id, partner_id_kpx, post_transaction FROM mldb.billspayment_transaction WHERE post_transaction = ? AND reference_no = ? AND (`datetime` = ? OR cancellation_date = ? ) AND status <=> ?";
                if (!empty($row['sub_billers_id'])) {
                    $selectSQL .= " AND sub_billers_id = ?";
                    $selectStmt = $conn->prepare($selectSQL);
                    if ($selectStmt) {
                        $selectStmt->bind_param("ssssss", $d_post_trans, $d_reference, $d_datetime, $d_cancellation, $d_status, $d_sub_billers_id);
                    }
                } elseif (strtoupper($sourceType) === 'KP7') {
                    $selectSQL .= " AND partner_id = ?";
                    $selectStmt = $conn->prepare($selectSQL);
                    if ($selectStmt) {
                        $selectStmt->bind_param("ssssss", $d_post_trans, $d_reference, $d_datetime, $d_cancellation, $d_status, $d_partner);
                    }
                } elseif (strtoupper($sourceType) === 'KPX') {
                    $selectSQL .= " AND partner_id_kpx = ?";
                    $selectStmt = $conn->prepare($selectSQL);
                    if ($selectStmt) {
                        $selectStmt->bind_param("ssssss", $d_post_trans, $d_reference, $d_datetime, $d_cancellation, $d_status, $d_partner_kpx);
                    }
                } else {
                    $selectStmt = $conn->prepare($selectSQL);
                    if ($selectStmt) {
                        $selectStmt->bind_param("sssss", $d_post_trans, $d_reference, $d_datetime, $d_cancellation, $d_status);
                    }
                }

                if (isset($selectStmt) && $selectStmt) {
                    $selectStmt->execute();
                    $selRes = $selectStmt->get_result();
                    if ($selRes && $selRes->num_rows > 0) {
                        while ($r = $selRes->fetch_assoc()) {
                            error_log("[IMPORT DEBUG] Will delete existing row id={$r['id']}, ref={$r['reference_no']}, status={$r['status']}, datetime={$r['datetime']}, cancellation_date={$r['cancellation_date']}, partner_id={$r['partner_id']}, partner_id_kpx={$r['partner_id_kpx']}, post_transaction={$r['post_transaction']}");
                            $deleted_rows_details[] = [
                                'id' => $r['id'],
                                'reference_no' => $r['reference_no'],
                                'status' => $r['status'],
                                'datetime' => $r['datetime'],
                                'cancellation_date' => $r['cancellation_date'],
                                'partner_id' => $r['partner_id'],
                                'partner_id_kpx' => $r['partner_id_kpx'],
                                'post_transaction' => $r['post_transaction']
                            ];
                        }
                    } else {
                        error_log("[IMPORT DEBUG] No existing rows found to delete for ref: {$d_reference}");
                    }
                    $selectStmt->close();
                }

                // perform delete
                $deleteStmt->execute();
                $deletedRows = $deleteStmt->affected_rows;
                if ($deletedRows > 0) {
                    $deleteCount++;
                    if ($deleteCount <= 5) {
                        error_log("[IMPORT DEBUG] Deleted {$deletedRows} existing row(s) for reference: {$row['reference_number']}");
                    }
                }
                $deleteStmt->close();
            }
            } // End of override mode

            $settle_unsettle = null;
            $claim_unclaim = null;
            $rfp_no = null;
            $cad_no = null;
            $hold_status = null;

            // Prepare variables for bind_param (bind_param requires variables passed by reference)
            $b_status = $status;
            $b_datetime = $datetime_value;
            $b_cancellation_date = $cancellation_date;
            // Determine report date to insert. Prefer row['report_date'] (user or KP7 header),
            // but ensure it's not earlier than the transaction date portion of $b_datetime.
            $b_report_date = null;
            if (isset($row['report_date']) && $row['report_date'] !== '') {
                $candidate = date('Y-m-d', strtotime($row['report_date']));
                if (!empty($b_datetime)) {
                    $txnDate = date('Y-m-d', strtotime($b_datetime));
                    if (strtotime($candidate) < strtotime($txnDate)) {
                        // Use transaction date when report_date is earlier than transaction
                        $candidate = $txnDate;
                    }
                }
                $b_report_date = $candidate;
            } else {
                // No provided report_date: fall back to transaction date if available
                if (!empty($b_datetime)) {
                    $b_report_date = date('Y-m-d', strtotime($b_datetime));
                } else {
                    $b_report_date = null;
                }
            }
            $b_sourceType = $sourceType;
            $b_control_number = $row['control_number'] ?? null;
            $b_reference_number = $row['reference_number'] ?? null;
            $b_payor_name = $row['payor_name'] ?? null;
            $b_payor_address = $row['payor_address'] ?? null;
            $b_account_number = $row['account_number'] ?? null;
            $b_account_name = $row['account_name'] ?? null;
            $b_amount_paid = $row['amount_paid'] ?? 0;
            $b_amount_charge_partner = $row['amount_charge_partner'] ?? 0;
            $b_amount_charge_customer = $row['amount_charge_customer'] ?? 0;
            $b_contact_number = $row['contact_number'] ?? null;
            if ($b_contact_number !== null) {
                $b_contact_number = trim((string)$b_contact_number);
                // Guard for DB column length (prevents "Data too long for column 'contact_no'").
                if (strlen($b_contact_number) > 20) {
                    $b_contact_number = substr($b_contact_number, 0, 20);
                }
                if ($b_contact_number === '') {
                    $b_contact_number = null;
                }
            }
            $b_other_details = $row['other_details'] ?? null;
            $b_branch_id = $row['branch_id'] ?? null;
            $b_branch_code = $row['branch_code'] ?? null;
            $b_branch_outlet = $row['branch_outlet'] ?? null;
            $b_zone_code = $row['zone_code'] ?? null;
            $b_region_code = $row['region_code'] ?? null;
            if ($b_branch_id === null || $b_branch_id === '' || strtoupper(trim((string)$b_branch_id)) === 'NAN') {
                $outletNorm = strtoupper(preg_replace('/\s+/', ' ', trim((string)$b_branch_outlet)));
                if ($outletNorm === 'ML CEBU HEAD OFFICE' || $outletNorm === 'CEBU HEAD OFFICE') {
                    $b_branch_id = 581;
                } elseif ($outletNorm === 'ML HEAD OFFICE' || $outletNorm === 'HEAD OFFICE') {
                    $b_branch_id = 2607;
                }
            }
            $b_region_description = $row['region_description'] ?? null;
            $b_person_operator = $row['person_operator'] ?? null;
            $b_partner_name = $row['partner_name'] ?? null;
            $b_sub_billers_id = $row['sub_billers_id'] ?? null;
            $b_billers_name = $row['billers_name'] ?? null;
            $b_partner_id = $row['partner_id'] ?? null;
            $b_partner_id_kpx = $row['PartnerID_KPX'] ?? null;
            $b_gl_code = $row['GLCode'] ?? null;
            $b_settle_unsettle = $settle_unsettle;
            $b_claim_unclaim = $claim_unclaim;
            $b_imported_by = $row['imported_by'] ?? null;
            $b_date_uploaded = $row['date_uploaded'] ?? null;
            $b_rfp_no = $rfp_no;
            $b_cad_no = $cad_no;
            $b_hold_status = $hold_status;
            $b_remote_branch = $row['remote_branch'] ?? null;
            $b_remote_operator = $row['remote_operator'] ?? null;
            $b_post_transaction = $row['post_transaction'] ?? null;

            $bindParams = [
                $b_status,
                $b_datetime,
                $b_cancellation_date,
                $b_report_date,
                $b_sourceType,
                $b_control_number,
                $b_reference_number,
                $b_payor_name,
                $b_payor_address,
                $b_account_number,
                $b_account_name,
                $b_amount_paid,
                $b_amount_charge_partner,
                $b_amount_charge_customer,
                $b_contact_number,
                $b_other_details,
                $b_branch_id,
                $b_branch_code,
                $b_branch_outlet,
                $b_zone_code,
                $b_region_code,
                $b_region_description,
                $b_person_operator,
                $b_partner_name,
                $b_sub_billers_id,
                $b_billers_name,
                $b_partner_id,
                $b_partner_id_kpx,
                $b_gl_code,
                $b_settle_unsettle,
                $b_claim_unclaim,
                $b_imported_by,
                $b_date_uploaded,
                $b_rfp_no,
                $b_cad_no,
                $b_hold_status,
                $b_remote_branch,
                $b_remote_operator,
                $b_post_transaction
            ];

            // Build types string dynamically (all as strings for simplicity)
            $types = str_repeat('s', count($bindParams));
            // Prepare references for call_user_func_array
            $refs = [];
            foreach ($bindParams as $key => $value) {
                $refs[$key] = &$bindParams[$key];
            }
            array_unshift($refs, $types);
            call_user_func_array([$insertStmt, 'bind_param'], $refs);

            if (!$insertStmt->execute()) {
                $insertFailures++;
                $errorMsg = "Insert failed for reference: {$b_reference_number} - {$insertStmt->error}";
                error_log("[IMPORT ERROR] {$errorMsg}");
                
                // Log first 10 failures in detail
                if ($insertFailures <= 10) {
                    error_log("[IMPORT ERROR] Failed row details: datetime={$b_datetime}, cancellation_date={$b_cancellation_date}, branch_id={$b_branch_id}");
                }
                
                throw new Exception($errorMsg);
            }

            $insertCount++;
            $insertSuccess++;
            
            // Log progress every 1000 rows
            if ($insertSuccess % 1000 == 0) {
                error_log("[IMPORT PROGRESS] Successfully inserted {$insertSuccess} of {$insertAttempts} rows");
            }
        }

        $insertStmt->close();
        $conn->commit();
        $conn->autocommit(TRUE);
        
        // Log final statistics
        error_log("[IMPORT COMPLETE] Total rows processed: {$insertAttempts}");
        error_log("[IMPORT COMPLETE] Successfully inserted: {$insertSuccess}");
        error_log("[IMPORT COMPLETE] Failed inserts: {$insertFailures}");
        error_log("[IMPORT COMPLETE] Rows deleted before insert: {$deleteCount}");
        error_log("[IMPORT COMPLETE] Transaction committed successfully");

        // Free spreadsheet resources for this import to reduce memory usage
        if (isset($spreadsheet) && is_object($spreadsheet)) {
            try { $spreadsheet->disconnectWorksheets(); } catch (Exception $e) {}
            unset($worksheet, $spreadsheet);
            if (function_exists('gc_collect_cycles')) gc_collect_cycles();
        }

        return [
            'success' => true,
            'inserted' => $insertCount,
            'warnings' => $errors,
            'debug_stats' => [
                'attempts' => $insertAttempts,
                'success' => $insertSuccess,
                'failures' => $insertFailures,
                'deleted' => $deleteCount,
                'deleted_rows' => $deleted_rows_details
            ]
        ];

    } catch (Exception $e) {
        $conn->rollback();
        $conn->autocommit(TRUE);
        
        // Log rollback details
        error_log("[IMPORT ERROR] Transaction rolled back due to error: " . $e->getMessage());
        if (isset($insertAttempts, $insertSuccess, $insertFailures)) {
            error_log("[IMPORT ERROR] Stats before rollback - Attempts: {$insertAttempts}, Success: {$insertSuccess}, Failures: {$insertFailures}");
        }
        
        // Attempt to free spreadsheet resources even on error
        if (isset($spreadsheet) && is_object($spreadsheet)) {
            try { $spreadsheet->disconnectWorksheets(); } catch (Exception $e2) {}
            unset($worksheet, $spreadsheet);
            if (function_exists('gc_collect_cycles')) gc_collect_cycles();
        }

        return [
            'success' => false,
            'error' => $e->getMessage(),
            'debug_stats' => [
                'attempts' => $insertAttempts ?? 0,
                'success' => $insertSuccess ?? 0,
                'failures' => $insertFailures ?? 0,
                'deleted' => $deleteCount ?? 0,
                'deleted_rows' => $deleted_rows_details ?? []
            ]
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
    <script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jspdf-autotable@3.8.2/dist/jspdf.plugin.autotable.min.js"></script>
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

        .file-validation-card.head_office {
            border-color: #ffc107;
            background-color: #fffaf0;
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
        .status-head_office { background: #ffc107; color: black; }
        
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
        .badge-head_office {
            background-color: #ffc107;
            color: #856404;
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

        /* missing icon base */
        .missing-icon-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: #fff;
            margin-left: 8px;
            font-size: 14px;
        }
        .missing-icon-btn.missing-mode {
            color: #dc3545;
            border: 2px solid #dc3545;
            box-shadow: 0 2px 6px rgba(220,53,69,0.12);
        }
        .missing-icon-btn.head-office-mode {
            color: #856404;
            border: 2px solid #ffc107;
            box-shadow: 0 2px 6px rgba(255,193,7,0.12);
        }
    </style>
</head>
<body>
    <div class="main-container">
        <div class="content-wrapper p-4">
            <?php 
                // Prepare counts and progress token early so header can show action buttons
                $allUploaded = isset($_SESSION['uploaded_files']) ? $_SESSION['uploaded_files'] : [];

                // Sort so that invalid files appear first and within each status sort by name DESC
                usort($allUploaded, function($a, $b) {
                    $priority = function($s) {
                        $s = strtolower($s);
                        if ($s === 'head_office') return -1; // highest priority
                        if ($s === 'invalid') return 0;
                        if ($s === 'valid') return 2;
                        return 1; // pending/other
                    };
                    $pa = $priority($a['status'] ?? '');
                    $pb = $priority($b['status'] ?? '');
                    if ($pa !== $pb) return $pa - $pb;
                    // same priority -> sort by name descending
                    return strcasecmp($b['name'] ?? '', $a['name'] ?? '');
                });

                // Store back into a local var used for rendering
                $renderFiles = $allUploaded;

                $validFiles = array_filter($allUploaded, function($f) { return ($f['status'] ?? '') === 'valid'; });
                $validCount = count($validFiles);
                $progressToken = uniqid('prg_', true);
            ?>
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="mb-0">File Validation & Import</h2>
                <div>
                    <?php if (isset($_SESSION['uploaded_files'])): ?>
                            <?php 
                                $headOfficeCount = 0;
                                foreach ($allUploaded as $af) {
                                    $vr = $af['validation_result'] ?? [];
                                    $hasHoFromMissingRows = false;
                                    if (!empty($vr['missing_rows']) && is_array($vr['missing_rows'])) {
                                        foreach ($vr['missing_rows'] as $mr) {
                                            $mv = strtoupper(preg_replace('/\s+/', ' ', trim((string)($mr['value'] ?? ''))));
                                            if (
                                                $mv === 'ML CEBU HEAD OFFICE' ||
                                                $mv === 'CEBU HEAD OFFICE' ||
                                                $mv === 'ML HEAD OFFICE' ||
                                                $mv === 'HEAD OFFICE'
                                            ) {
                                                $hasHoFromMissingRows = true;
                                                break;
                                            }
                                        }
                                    }
                                    if (
                                        ($af['status'] ?? '') === 'head_office' ||
                                        !empty($vr['has_head_office_outlet']) ||
                                        !empty($vr['head_office_issues']) ||
                                        $hasHoFromMissingRows
                                    ) {
                                        $headOfficeCount++;
                                    }
                                }
                                $errorDetectedCount = 0;
                                foreach ($allUploaded as $af) {
                                    $vr = $af['validation_result'] ?? [];
                                    if (!empty($vr['missing_rows']) && is_array($vr['missing_rows'])) {
                                        $errorDetectedCount++;
                                    }
                                }
                            ?>
                            <?php if ($headOfficeCount > 0): ?>
                                <button type="button" id="fixAllHeadOfficeBtn" class="btn btn-warning btn-sm me-2">
                                    <i class="fa-solid fa-wrench me-1"></i>
                                    Fix All Head Office Issues (<?php echo $headOfficeCount; ?>)
                                </button>
                            <?php endif; ?>
                            <?php if ($headOfficeCount === 0 && $errorDetectedCount > 0): ?>
                                <button type="button" id="exportAllErrorsBtn" class="btn btn-danger btn-sm me-2">
                                    <i class="fa-solid fa-file-pdf me-1"></i>
                                    Export All Error Detected in PDF Format (<?php echo $errorDetectedCount; ?>)
                                </button>
                            <?php endif; ?>
                            <?php if ($validCount > 0): ?>
                            <form method="post" id="performImportForm" style="display: inline; margin-right:8px;">
                                <input type="hidden" name="progress_token" value="<?php echo htmlspecialchars($progressToken); ?>">
                                <input type="hidden" name="perform_import" value="1">
                                <button type="submit" name="perform_import" class="btn btn-success btn-sm" id="importBtn">
                                    <i class="fa-solid fa-file-import me-1"></i>
                                    <?php echo $validCount === 1 ? 'Import File' : 'Import All (' . $validCount . ')'; ?>
                                </button>
                            </form>
                        <?php endif; ?>
                        <button class="btn btn-secondary btn-sm" onclick="confirmCancel()">
                            <i class="fa-solid fa-times me-1"></i> Cancel
                        </button>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if (!empty($renderFiles) && count($renderFiles) > 0): ?>
                <div class="d-flex justify-content-end mb-2">
                    <div style="min-width:420px; text-align:right;">
                        <label for="fileFilter" class="me-2" style="font-weight:600;">Show:</label>
                        <select id="fileFilter" class="form-select form-select-sm" style="display:inline-block; width:160px;">
                            <option value="all">All</option>
                            <option value="head_office">Head Office</option>
                            <option value="invalid">Invalid</option>
                            <option value="valid">Valid</option>
                        </select>
                        <!-- Report Date will be extracted from uploaded files automatically (B3) -->
                    </div>
                </div>
                
                <div class="validation-container">
                    <?php foreach ($renderFiles as $file): ?>
                        <?php $isMultipleMode = (strtolower((string)($file['import_mode'] ?? 'auto')) === 'multiple'); ?>
                        <div class="file-validation-card <?php echo htmlspecialchars($file['status']); ?>" data-status="<?php echo htmlspecialchars($file['status']); ?>" data-name="<?php echo htmlspecialchars($file['name']); ?>">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div>
                                    <h5 class="mb-1"><?php echo htmlspecialchars($file['name']); ?></h5>
                                    <?php if (!$isMultipleMode): ?>
                                        <small class="text-muted"><?php echo $file['partner_name']; ?></small>
                                    <?php endif; ?>
                                </div>
                                <span class="status-badge status-<?php echo $file['status']; ?>">
                                    <?php echo ucwords(str_replace('_', ' ', $file['status'])); ?>
                                </span>
                                <?php /* header-level head-office button removed — use card icon instead */ ?>
                            </div>
                            
                            <div class="mb-3">
                                <div class="row">
                                    <?php if (!$isMultipleMode): ?>
                                        <div class="col-6">
                                            <small class="text-muted d-block">Partner ID</small>
                                            <strong><?php echo $file['partner_id']; ?></strong>
                                        </div>
                                    <?php endif; ?>
                                    <div class="col-6">
                                        <small class="text-muted d-block">Source Type</small>
                                        <span class="badge-source badge-<?php echo strtolower($file['source_type']); ?>">
                                            <?php echo $file['source_type']; ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <?php if (!$isMultipleMode): ?>
                                <div class="mb-2">
                                    <div class="row">
                                        <div class="col-6">
                                            <small class="text-muted d-block">Sub Billers ID</small>
                                            <strong><?php echo isset($file['sub_billers_id']) && $file['sub_billers_id'] !== null ? $file['sub_billers_id'] : '-'; ?></strong>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($file['validation_result']): ?>
                                <div class="mb-2">
                                    <small class="text-muted">Rows Found:</small>
                                    <strong><?php echo number_format((int)($file['validation_result']['row_count'] ?? 0), 0, '.', ','); ?></strong>
                                </div>
                                
                                <!-- Validation errors/warnings are shown per-row via the Missing Data modal;
                                     keep card UI minimal (row count + missing icon) -->
                            <?php endif; ?>
                            
                            <div class="btn-group mt-2" role="group">
                                <button class="btn btn-sm btn-info" onclick="viewDetails('<?php echo $file['id']; ?>')">
                                    <i class="fa-solid fa-eye"></i> View Details
                                </button>
                                <button class="btn btn-sm btn-success" onclick="viewTransactionSummary('<?php echo $file['id']; ?>')">
                                    <i class="fa-solid fa-chart-bar"></i> Transaction Summary
                                </button>
                                                <?php if (!empty($file['validation_result']['missing_rows'])): ?>
                                                    <?php if (!empty($file['validation_result']['head_office_issues'])): ?>
                                                        <button class="missing-icon-btn head-office-mode" title="View Head Office issue" data-file-id="<?php echo htmlspecialchars($file['id']); ?>">
                                                            <i class="fa-solid fa-circle-exclamation"></i>
                                                        </button>
                                                    <?php else: ?>
                                                        <button class="missing-icon-btn missing-mode" title="Missing data detected" data-file-id="<?php echo htmlspecialchars($file['id']); ?>">
                                                            <i class="fa-solid fa-circle-question"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Action Buttons moved to header -->
                
            <?php else: ?>
                <div class="alert alert-info text-center mt-5">
                    <h4>No files uploaded</h4>
                    <p>Please go back to the upload page and select files to import.</p>
                    <a href="/billspayment/dashboard/billspayment/import/billspay-transaction.php" class="btn btn-primary">
                        Go to Upload Page
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Import progress overlay -->
    <div id="importOverlay" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.6); z-index:99999; align-items:center; justify-content:center;">
        <div style="background:#fff; padding:20px; border-radius:8px; width:420px; max-width:90%; text-align:left;">
            <h5 style="margin:0 0 10px 0;"><i class="fa-solid fa-spinner fa-spin"></i> Importing...</h5>
            <div class="progress" style="height:18px; margin-bottom:10px;">
                <div id="importProgressBar" class="progress-bar bg-success" role="progressbar" style="width:0%;">0%</div>
            </div>
            <div style="display:flex; justify-content:space-between; align-items:center; font-size:14px;">
                <div id="importProgressText">0/0</div>
                <div><button id="importCancelBtn" class="btn btn-sm btn-secondary">Hide</button></div>
            </div>
        </div>
    </div>

    <script>
        // Store file data for JavaScript access
        const filesData = <?php echo json_encode($renderFiles ?? []); ?>;

        // Format internal status code to human label
        function formatStatusLabel(s) {
            if (!s) return 'Pending';
            try {
                return s.toString().replace(/_/g, ' ').replace(/\w\S*/g, function(txt){
                    return txt.charAt(0).toUpperCase() + txt.substr(1).toLowerCase();
                });
            } catch (e) { return s; }
        }

        // Client-side filtering for file cards (All / Invalid / Valid)
        document.addEventListener('DOMContentLoaded', function() {
            const filterEl = document.getElementById('fileFilter');
            if (!filterEl) return;
            filterEl.addEventListener('change', function(e) {
                const val = e.target.value;
                const cards = Array.from(document.querySelectorAll('.file-validation-card'));
                cards.forEach(card => {
                    const status = (card.dataset.status || '').toLowerCase();
                    if (val === 'all') {
                        card.style.display = '';
                    } else if (val === 'invalid') {
                        card.style.display = (status === 'invalid') ? '' : 'none';
                    } else if (val === 'head_office') {
                        card.style.display = (status === 'head_office') ? '' : 'none';
                    } else if (val === 'valid') {
                        card.style.display = (status === 'valid') ? '' : 'none';
                    }
                });
            });
            // Fix All Head Office button handler
            const fixAllBtn = document.getElementById('fixAllHeadOfficeBtn');
            if (fixAllBtn) {
                fixAllBtn.addEventListener('click', function() {
                    Swal.fire({
                        title: 'Fix All Head Office Issues',
                        text: 'This will automatically set Branch ID = 581 for all detected Head Office rows across uploaded files. Proceed?',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: 'Yes, fix all',
                        cancelButtonText: 'Cancel'
                    }).then((res) => {
                        if (!res.isConfirmed) return;
                        Swal.fire({ title: 'Applying fixes...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });
                        $.post('', { fix_head_office_all: 1 }, function(resp) {
                            if (resp && resp.success) {
                                Swal.fire({ title: 'Done', text: 'Head Office fixes applied', icon: 'success' }).then(() => location.reload());
                            } else {
                                Swal.fire({ title: 'Error', text: (resp && resp.error) ? resp.error : 'Failed to apply fixes', icon: 'error' });
                            }
                        }, 'json').fail(function() { Swal.fire('Error','Request failed','error'); });
                    });
                });
            }

            const exportAllErrorsBtn = document.getElementById('exportAllErrorsBtn');
            if (exportAllErrorsBtn) {
                exportAllErrorsBtn.addEventListener('click', function() {
                    const jsPDFRef = window.jspdf && window.jspdf.jsPDF ? window.jspdf.jsPDF : null;
                    if (!jsPDFRef) {
                        Swal.fire('Error', 'PDF library is not available.', 'error');
                        return;
                    }

                    const bodyRows = [];
                    (filesData || []).forEach((fileItem) => {
                        const missing = (fileItem && fileItem.validation_result && Array.isArray(fileItem.validation_result.missing_rows))
                            ? fileItem.validation_result.missing_rows
                            : [];
                        missing.forEach((m) => {
                            const issueType = m.type || 'missing_fields';
                            const issueText = issueType === 'new_branch_id'
                                ? 'New Branch ID'
                                : (m.missing ? ('Missing Fields: ' + m.missing.join(', ')) : 'Missing Fields');
                            const valueText = (m.value !== undefined && m.value !== null && String(m.value).trim() !== '')
                                ? String(m.value)
                                : '-';
                            bodyRows.push([
                                String(fileItem.name || 'N/A'),
                                String(m.row ?? ''),
                                issueText,
                                valueText
                            ]);
                        });
                    });

                    if (!bodyRows.length) {
                        Swal.fire('No Errors Found', 'There are no detected errors to export.', 'info');
                        return;
                    }

                    const doc = new jsPDFRef({ orientation: 'landscape' });
                    let y = 15;
                    doc.setFontSize(12);
                    doc.text('All Detected Errors', 14, y);
                    y += 7;
                    doc.setFontSize(10);
                    doc.text('Generated: ' + new Date().toLocaleString(), 14, y);
                    y += 8;

                    if (typeof doc.autoTable === 'function') {
                        doc.autoTable({
                            startY: y,
                            head: [['File', 'Row', 'Issue', 'Value']],
                            body: bodyRows,
                            styles: { fontSize: 8, cellPadding: 2 },
                            headStyles: { fillColor: [220, 53, 69] },
                            columnStyles: {
                                0: { cellWidth: 80 },
                                1: { cellWidth: 20 },
                                2: { cellWidth: 70 },
                                3: { cellWidth: 110 }
                            }
                        });
                    }

                    const now = new Date();
                    const yyyy = now.getFullYear();
                    const mm = String(now.getMonth() + 1).padStart(2, '0');
                    const dd = String(now.getDate()).padStart(2, '0');
                    doc.save(`all_error_detected_${yyyy}-${mm}-${dd}.pdf`);
                });
            }
            // Delegated click handler for Head Office and Missing buttons
            function showHeadOfficeModal(issues, fileId) {
                if (!Array.isArray(issues) || issues.length === 0) {
                    Swal.fire('No issues', 'No head office issues found for this file.', 'info');
                    return;
                }
                let table = '<div style="max-height:420px; overflow:auto; text-align:left;"><table class="table table-sm table-bordered"><thead><tr><th>Row</th><th>Outlet</th><th>Issue</th><th>Value</th></tr></thead><tbody>';
                issues.forEach(it => {
                    table += '<tr>' +
                        '<td>' + (it.row || '') + '</td>' +
                        '<td>' + (it.outlet || '') + '</td>' +
                        '<td>' + (it.issue || '') + '</td>' +
                        '<td>' + (it.value || '') + '</td>' +
                        '</tr>';
                });
                table += '</tbody></table></div>';

                Swal.fire({
                    title: 'Head Office Issues',
                    html: table,
                    width: '800px',
                    showCancelButton: true,
                    showDenyButton: false,
                    confirmButtonText: 'Fix',
                    cancelButtonText: 'Close',
                    preConfirm: () => {
                        const fixes = issues.map(i => ({ row: i.row, value: '581' }));
                        try { Swal.update({ confirmButtonText: 'Fixing...' }); Swal.showLoading(); } catch (e) {}
                        return new Promise((resolve, reject) => {
                            $.post('', { fix_head_office: 1, file_id: fileId, fixes: JSON.stringify(fixes) }, function(resp) {
                                if (resp && resp.success) resolve(resp);
                                else reject((resp && resp.error) ? resp.error : 'Unknown error');
                            }, 'json').fail(function() { reject('Request failed'); });
                        });
                    }
                }).then((res) => {
                    if (res.isConfirmed) {
                        Swal.fire({ title: 'Applying fixes...', icon: 'info', showConfirmButton: false });
                        location.reload();
                    }
                });
            }

            document.addEventListener('click', function(ev) {
                // head-office-btn (explicit head office button)
                const headBtn = ev.target.closest && ev.target.closest('.head-office-btn');
                if (headBtn) {
                    const issuesJson = headBtn.getAttribute('data-issues') || '[]';
                    let issues = [];
                    try { issues = JSON.parse(issuesJson); } catch (e) { issues = []; }
                    const fid = headBtn.getAttribute('data-file-id') || '';
                    showHeadOfficeModal(issues, fid);
                    return;
                }

                // missing-icon-btn (repurposed) - decide which modal to show
                const missBtn = ev.target.closest && ev.target.closest('.missing-icon-btn');
                if (missBtn) {
                    const fid = missBtn.getAttribute('data-file-id');
                    const file = filesData.find(f => f.id === fid);
                    if (file && file.validation_result && file.validation_result.head_office_issues && file.validation_result.head_office_issues.length > 0) {
                        showHeadOfficeModal(file.validation_result.head_office_issues, fid);
                    } else {
                        // fallback to existing missing modal behavior
                        showMissingModal(fid);
                    }
                    return;
                }
            });
        });
        
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
                            <tr><th>Sub Billers ID:</th><td>${fileData.sub_billers_id || fileData.sub_billers_id === 0 ? fileData.sub_billers_id : (fileData.sub_billers_id === null ? 'N/A' : fileData.sub_billers_id)}</td></tr>
                            <tr><th>Source Type:</th><td><span class="badge badge-${(sourceType || 'unknown').toLowerCase()}">${sourceType}</span></td></tr>
                            ${((sourceType || '').toUpperCase() === 'KPX' && String(partnerData.partner_id_kpx || fileData.partner_id || '') === '1074' && (fileData.billers_name || '').trim() !== '') ? `<tr><th>Billers Name:</th><td>${fileData.billers_name}</td></tr>` : ''}
                            <tr><th>Report Date:</th><td>${(validation && (validation.report_date || validation.report_date_raw)) ? (validation.report_date || validation.report_date_raw) : (fileData.report_date || fileData.report_date_raw || '')}</td></tr>
                            <tr><th>Transaction Date:</th><td>${validation.transaction_start_date ? ('Start Date: ' + validation.transaction_start_date + (validation.transaction_end_date ? ' - End Date: ' + validation.transaction_end_date : '')) : transactionDate}</td></tr>
                            <tr><th>Total Rows:</th><td>${validation.row_count || 0}</td></tr>
                            <tr><th>Status:</th><td><span class="badge badge-${fileData.status || 'pending'}">${formatStatusLabel(fileData.status || 'pending')}</span></td></tr>
                        </table>
                    </div>`;

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
            
            // Show preview data with filter control
            if (previewData.length > 0) {
                html += `
                    <div class="mb-3">
                        <h5><i class="fa-solid fa-table"></i> Data Preview (First 10 rows + all cancelled)</h5>
                        <div class="d-flex mb-2">
                            <div style="flex:1"></div>
                            <div style="min-width:220px; text-align:right;">
                                <label for="previewFilter" style="margin-right:8px; font-weight:600;">Filter:</label>
                                <select id="previewFilter" class="form-select form-select-sm" style="display:inline-block; width:140px;">
                                    <option value="all">All</option>
                                    <option value="active">Active</option>
                                    <option value="cancelled">Cancelled</option>
                                </select>
                            </div>
                        </div>
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
                let activeCount = 0;
                let cancelledCount = 0;
                
                previewData.forEach(row => {
                    if (!row) return; // Skip null/undefined rows

                    // Determine cancellation state robustly
                    // KP7: asterisk in STATUS column (column A), stored in numeric_number or control_number
                    // KPX: asterisk in No column as "1*", "2*" etc, stored in numeric_number
                    const hasAsterisk = (val) => val && String(val).trim().includes('*');
                    const isCancellation = hasAsterisk(row.numeric_number) || 
                                          hasAsterisk(row.control_number) || 
                                          hasAsterisk(row.reference_number) ||
                                          hasAsterisk(row.transaction_date);
                    
                    if (isCancellation) {
                        cancelledCount++;
                    } else {
                        activeCount++;
                    }

                    html += `<tr class="preview-row" data-cancelled="${isCancellation ? '1' : '0'}">`;

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
                        html += `
                            <td>${isCancellation ? '<span class="badge bg-danger">CANCEL</span>' : '<span class="badge bg-success">active</span>'}</td>
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
                    const previewNote = cancelledCount > 0 
                        ? `Showing first 10 rows + all ${cancelledCount} cancelled transaction(s) from ${validation.row_count} total rows`
                        : `Showing first 10 of ${validation.row_count} total rows`;
                    html += `<p class="text-muted"><small><i class="fa-solid fa-info-circle"></i> ${previewNote} (Preview: ${activeCount} active, ${cancelledCount} cancelled)</small></p>`;
                } else {
                    html += `<p class="text-muted"><small>Preview: ${activeCount} active, ${cancelledCount} cancelled</small></p>`;
                }
                
                console.log('Preview data detection:', { activeCount, cancelledCount, totalPreview: previewData.length });
                
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
            
            // Display the modal with filter behavior attached on open
            Swal.fire({
                title: '<strong>File Details: ' + fileData.name + '</strong>',
                html: html,
                width: '90%',
                showCloseButton: true,
                confirmButtonText: 'Close',
                customClass: {
                    container: 'swal-wide'
                },
                didOpen: (modal) => {
                    try {
                        const container = Swal.getHtmlContainer();
                        if (!container) return;
                        const filter = container.querySelector('#previewFilter');
                        if (!filter) return;
                        
                        // Log initial row states for debugging
                        const rows = Array.from(container.querySelectorAll('.preview-row'));
                        const cancelledRows = rows.filter(r => r.dataset.cancelled === '1');
                        console.log('Preview filter initialized:', {
                            totalRows: rows.length,
                            cancelledRows: cancelledRows.length,
                            activeRows: rows.length - cancelledRows.length
                        });
                        
                        // Attach change handler to show/hide rows
                        filter.addEventListener('change', (e) => {
                            const val = e.target.value;
                            let visibleCount = 0;
                            rows.forEach(r => {
                                const isCancel = r.dataset.cancelled === '1';
                                if (val === 'all') {
                                    r.style.display = '';
                                    visibleCount++;
                                } else if (val === 'active') {
                                    r.style.display = isCancel ? 'none' : '';
                                    if (!isCancel) visibleCount++;
                                } else if (val === 'cancelled') {
                                    r.style.display = isCancel ? '' : 'none';
                                    if (isCancel) visibleCount++;
                                }
                            });
                            console.log(`Filter changed to '${val}': showing ${visibleCount} rows`);
                        });
                    } catch (err) {
                        console.error('Preview filter init error', err);
                    }
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
                                ${((sourceType || '').toUpperCase() === 'KPX' && String(partnerData.partner_id_kpx || fileData.partner_id || '') === '1074' && (fileData.billers_name || '').trim() !== '') ? `<tr><th style="background-color: #f8f9fa;"><i class="fa-solid fa-file-signature" style="color: #198754;"></i> Billers Name</th><td><strong>${fileData.billers_name}</strong></td></tr>` : ''}
                                <tr>
                                    <th style="background-color: #f8f9fa;"><i class="fa-solid fa-list" style="color: #fd7e14;"></i> No. of Data Rows Uploaded</th>
                                    <td><strong>${totalRowsUploaded}</strong></td>
                                </tr>
                                <tr>
                                    <th style="background-color: #f8f9fa;"><i class="fa-solid fa-database" style="color: #20c997;"></i> Source</th>
                                    <td><span class="badge badge-${sourceType.toLowerCase()}" style="font-size: 14px;">${sourceType} System</span></td>
                                </tr>
                                <tr>
                                    <th style="background-color: #f8f9fa;"><i class="fa-solid fa-calendar-day" style="color: #6f42c1;"></i> Report Date</th>
                                    <td><strong>${(validation && (validation.report_date || validation.report_date_raw)) ? (validation.report_date || validation.report_date_raw) : (fileData.report_date || fileData.report_date_raw || '')}</strong></td>
                                </tr>
                                <tr>
                                    <th style="background-color: #f8f9fa;"><i class="fa-solid fa-calendar-day" style="color: #0dcaf0;"></i> Start Date</th>
                                    <td><strong>${validation.transaction_start_date || 'N/A'}</strong></td>
                                </tr>
                                <tr>
                                    <th style="background-color: #f8f9fa;"><i class="fa-solid fa-calendar-day" style="color: #0dcaf0;"></i> End Date</th>
                                    <td><strong>${validation.transaction_end_date || 'N/A'}</strong></td>
                                </tr>
                                <tr>
                                    <th style="background-color: #f8f9fa;"><i class="fa-solid fa-calendar-day" style="color: #0dcaf0;"></i> Transaction Date</th>
                                    <td><strong>${validation.transaction_start_date ? ('Start Date: ' + validation.transaction_start_date + (validation.transaction_end_date ? ' - End Date: ' + validation.transaction_end_date : '')) : transactionDate}</strong></td>
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

        // Show modal listing rows with missing required fields
        function showMissingModal(fileId) {
            const fileData = filesData.find(f => f.id === fileId);
            if (!fileData || !fileData.validation_result) {
                Swal.fire({ title: 'Info', text: 'No validation data available', icon: 'info' });
                return;
            }

            const missing = fileData.validation_result.missing_rows || [];
            if (!missing.length) {
                Swal.fire({ title: 'No Missing Data', text: 'No missing Branch ID / ML Outlet / Region Code / New Branch ID / No Partner ID found.', icon: 'success' });
                return;
            }

            let html = `<div style="text-align:left; max-height:60vh; overflow:auto;">
                <p class="text-danger"><strong>Rows with missing data</strong></p>`;

            if (missing.length) {
                html += `<table class="table table-sm table-bordered table-striped">
                    <thead><tr><th>Row</th><th>Issue</th><th>Value</th></tr></thead>
                    <tbody>`;
                missing.forEach(m => {
                    const issueType = m.type || 'missing_fields';
                    const issueText = issueType === 'new_branch_id'
                        ? 'New Branch ID'
                        : (m.missing ? ('Missing Fields: ' + m.missing.join(', ')) : 'Missing Fields');
                    const valueText = (m.value !== undefined && m.value !== null && String(m.value).trim() !== '')
                        ? m.value
                        : '-';
                    html += `<tr><td>${m.row}</td><td>${issueText}</td><td>${valueText}</td></tr>`;
                });
                html += `</tbody></table>`;
            }

            html += `</div>`;

            Swal.fire({
                title: '<strong>Missing Data Details: ' + (fileData.name || '') + '</strong>',
                html: html,
                width: '70%',
                showCloseButton: true,
                showDenyButton: true,
                denyButtonText: 'Export to PDF',
                confirmButtonText: 'Close',
                customClass: { denyButton: 'btn btn-danger' }
            })
            .then((result) => {
                if (!result.isDenied) return;
                const jsPDFRef = window.jspdf && window.jspdf.jsPDF ? window.jspdf.jsPDF : null;
                if (!jsPDFRef) return;
                const doc = new jsPDFRef();
                let y = 15;
                doc.setFontSize(12);
                doc.text('Missing Data Details', 14, y);
                y += 7;
                doc.setFontSize(10);
                doc.text('File: ' + (fileData.name || 'N/A'), 14, y);
                y += 8;

                const bodyRows = missing.map((m) => {
                    const issueType = m.type || 'missing_fields';
                    const issueText = issueType === 'new_branch_id'
                        ? 'New Branch ID'
                        : (m.missing ? ('Missing Fields: ' + m.missing.join(', ')) : 'Missing Fields');
                    const valueText = (m.value !== undefined && m.value !== null && String(m.value).trim() !== '') ? String(m.value) : '-';
                    return [String(m.row ?? ''), issueText, valueText];
                });

                if (typeof doc.autoTable === 'function') {
                    doc.autoTable({
                        startY: y,
                        head: [['Row', 'Issue', 'Value']],
                        body: bodyRows,
                        styles: { fontSize: 9, cellPadding: 2 },
                        headStyles: { fillColor: [220, 53, 69] },
                        columnStyles: {
                            0: { cellWidth: 18 },
                            1: { cellWidth: 55 },
                            2: { cellWidth: 110 }
                        }
                    });
                }
                const safeName = (fileData.name || 'missing-data').replace(/[^a-zA-Z0-9._-]/g, '_');
                doc.save(`missing_data_${safeName}.pdf`);
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
                    // Clean up session and temp files via this script's cancel handler
                    window.location.href = window.location.pathname + '?cancel=1';
                }
            });
        }

        // Import form handling with progress polling
        (function() {
            const form = document.getElementById('performImportForm');
            if (!form) return;

            // Import button is always available when there are valid files; report date is
            // extracted from each uploaded file (B3) and persisted per-file. No UI selector.
            const importBtnEl = document.getElementById('importBtn');
            try { if (importBtnEl) importBtnEl.style.display = ''; } catch(e){}

            const overlay = document.getElementById('importOverlay');
            const progressBar = document.getElementById('importProgressBar');
            const progressText = document.getElementById('importProgressText');
            const hideBtn = document.getElementById('importCancelBtn');

            let pollInterval = null;

            function showOverlay() {
                if (overlay) overlay.style.display = 'flex';
            }
            function hideOverlay() {
                if (overlay) overlay.style.display = 'none';
            }

            function startPolling(token) {
                if (!token) return;
                pollInterval = setInterval(async () => {
                    try {
                        const res = await fetch(window.location.pathname + '?get_progress=1&token=' + encodeURIComponent(token), { cache: 'no-store' });
                        const data = await res.json();
                        const total = parseInt(data.total || 0, 10);
                        const done = parseInt(data.done || 0, 10);
                        if (total > 0) {
                            const pct = Math.min(100, Math.round((done / total) * 100));
                            if (progressBar) progressBar.style.width = pct + '%';
                            if (progressBar) progressBar.textContent = pct + '%';
                            if (progressText) progressText.textContent = done + '/' + total;
                        } else {
                            if (progressBar) { progressBar.style.width = '100%'; progressBar.textContent = 'Processing'; }
                            if (progressText) progressText.textContent = '0/0';
                        }
                        if (total > 0 && done >= total) {
                            clearInterval(pollInterval);
                            pollInterval = null;
                        }
                    } catch (err) {
                        // ignore polling errors
                    }
                }, 600);
            }

            form.addEventListener('submit', function(e) {
                e.preventDefault();
                const fd = new FormData(form);
                const token = fd.get('progress_token');
                fd.append('is_ajax', '1'); // Indicate this is an AJAX request
                
                console.log('[IMPORT DEBUG] Import started with progress token:', token);
                console.log('[IMPORT DEBUG] Form data prepared for submission');
                
                showOverlay();
                startPolling(token);

                // submit via fetch and handle JSON response
                fetch(window.location.href, {
                    method: 'POST',
                    body: fd,
                    credentials: 'same-origin'
                }).then(resp => {
                    console.log('[IMPORT DEBUG] Server response received, status:', resp.status);
                    return resp.json();
                }).then(data => {
                    // Stop polling
                    if (pollInterval) clearInterval(pollInterval);
                    pollInterval = null;
                    hideOverlay();

                    console.log('[IMPORT DEBUG] Import response data:', data);
                    
                    // Log debug statistics if available
                    if (data.debug_stats) {
                        console.log('[IMPORT DEBUG] Aggregate statistics:');
                        console.log('  - Total files processed:', data.debug_stats.files_processed);
                        console.log('  - Total row attempts:', data.debug_stats.total_attempts);
                        console.log('  - Successful inserts:', data.debug_stats.total_success);
                        console.log('  - Failed inserts:', data.debug_stats.total_failures);
                        console.log('  - Rows deleted before insert:', data.debug_stats.total_deleted);
                    }
                    
                    // Log per-file statistics if available
                    if (data.per_file_stats && data.per_file_stats.length > 0) {
                        console.log('[IMPORT DEBUG] Per-file breakdown:');
                        data.per_file_stats.forEach((fileStats, idx) => {
                            console.log(`  File ${idx + 1}: ${fileStats.file}`);
                            console.log(`    - Attempts: ${fileStats.attempts}`);
                            console.log(`    - Success: ${fileStats.success}`);
                            console.log(`    - Failures: ${fileStats.failures}`);
                            console.log(`    - Deleted: ${fileStats.deleted}`);
                            if (fileStats.deleted_rows && Array.isArray(fileStats.deleted_rows) && fileStats.deleted_rows.length > 0) {
                                console.log('    - Deleted rows details:');
                                fileStats.deleted_rows.forEach(dr => {
                                    console.log(`      id=${dr.id}, ref=${dr.reference_no}, status=${dr.status}, datetime=${dr.datetime}, cancellation_date=${dr.cancellation_date}, partner_id=${dr.partner_id}, partner_id_kpx=${dr.partner_id_kpx}, post_transaction=${dr.post_transaction}`);
                                });
                            }
                            if (fileStats.failed) {
                                console.warn(`    ⚠ This file had import errors`);
                            }
                        });
                    }

                    // Show result modal
                    const hasErrors = data.errors && data.errors.length > 0;
                    const summaryHtml = hasErrors
                        ? `Import finished with some issues.<br>Successfully imported: ${data.imported} file(s)<br>Failed: ${data.failed}`
                        : `Successfully imported: ${data.imported} file(s)`;
                    
                    console.log('[IMPORT DEBUG] Import completed - Success:', data.imported, 'Failed:', data.failed);

                    Swal.fire({
                        icon: hasErrors ? 'warning' : 'success',
                        title: hasErrors ? 'Import Completed with Issues' : 'Import Successful',
                        html: summaryHtml,
                        showDenyButton: hasErrors,
                        denyButtonText: 'View full details',
                        confirmButtonText: 'OK',
                        reverseButtons: true
                    }).then((result) => {
                        if (result.isDenied && hasErrors) {
                            const detailList = data.errors
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
                                window.location.href = '/billspayment/dashboard/billspayment/import/billspay-transaction.php';
                            });
                        } else {
                            window.location.href = '/billspayment/dashboard/billspayment/import/billspay-transaction.php';
                        }
                    });
                }).catch(error => {
                    if (pollInterval) clearInterval(pollInterval);
                    hideOverlay();
                    console.error('[IMPORT ERROR] Failed to fetch or parse response:', error);
                    console.error('[IMPORT ERROR] Error details:', {
                        message: error.message,
                        stack: error.stack
                    });
                    Swal.fire({ 
                        icon: 'error', 
                        title: 'Import Error', 
                        text: 'An error occurred during import. Check console for details.' 
                    });
                });
            });

            if (hideBtn) hideBtn.addEventListener('click', function() { hideOverlay(); });
        })();
    </script>
</body>
</html>
