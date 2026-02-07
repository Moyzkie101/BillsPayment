<?php

    session_start();
    include '../config/config.php';
    require '../vendor/autoload.php';

    if (!isset($_SESSION['admin_name'])) {
        header('location:../login_form.php');
    }

    use PhpOffice\PhpSpreadsheet\IOFactory;
    use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

    // Add this optimized function after the original checkDistinctBranchId function
    function preloadBranchData($conn) {
        $branchData = [];
        
        // Load KP7 branch data (code + gl_region combination)
        // First, try to load data from branch_profile directly
        $kp7Query1 = "SELECT DISTINCT code, gl_region, branch_id, region_code, 'direct' as source FROM masterdata.branch_profile WHERE code IS NOT NULL AND gl_region IS NOT NULL";

        // Second, load data from joined tables
        $kp7Query2 = "SELECT DISTINCT mbp.code, mmp.gl_region, mbp.branch_id, mgr.region_code, 'joined' as source FROM masterdata.branch_profile as mbp
                    JOIN masterdata.mlmatic_profile AS mmp
                    ON mmp.code = mbp.code
                    AND mmp.kpcode = mbp.kp_code
                    JOIN masterdata.gl_region_masterfile as mgr
                    ON mgr.gl_region = mmp.gl_region 
                    WHERE mbp.code IS NOT NULL AND mmp.gl_region IS NOT NULL";

        // Third, load data from joined tables with region_masterfile
        $kp7Query3 = "SELECT DISTINCT mbp.code, mmp.gl_region, mbp.branch_id, mrm.region_code, 'region_joined' as source FROM masterdata.branch_profile as mbp
                    JOIN masterdata.mlmatic_profile AS mmp
                    ON mmp.code = mbp.code
                    AND mmp.kpcode = mbp.kp_code
                    JOIN masterdata.region_masterfile as mrm
                    ON  mrm.region_description = mmp.kp_region
                    WHERE mmp.code IS NOT NULL AND mmp.gl_region IS NOT NULL";

        // Combine all three queries
        $kp7Query = "($kp7Query1) UNION ($kp7Query2) UNION ($kp7Query3)";

        $kp7Result = $conn->query($kp7Query);
        if ($kp7Result) {
            while ($row = $kp7Result->fetch_assoc()) {
                $key = $row['code'] . '_' . $row['gl_region'];
                $branchData['kp7'][$key] = $row;
            }
        }
        
        // Load KPX branch data (branch_id only)
        $kpxQuery = "SELECT DISTINCT branch_id, region_code FROM masterdata.branch_profile WHERE branch_id IS NOT NULL";
        $kpxResult = $conn->query($kpxQuery);
        if ($kpxResult) {
            while ($row = $kpxResult->fetch_assoc()) {
                $branchData['kpx'][$row['branch_id']] = $row;
            }
        }
        
        return $branchData;
    }

    // Add this new function after preloadBranchData
    function processCancellationMatching($allRowsData) {
        $processedData = [];
        $referenceGroups = [];
        
        // Group rows by reference_number
        foreach ($allRowsData as $index => $row) {
            $refNum = $row['reference_number'];
            if (!isset($referenceGroups[$refNum])) {
                $referenceGroups[$refNum] = [];
            }
            $referenceGroups[$refNum][] = ['index' => $index, 'data' => $row];
        }
        
        // Process each reference number group
        foreach ($referenceGroups as $refNum => $group) {
            if (count($group) >= 2) {
                // Look for cancellation and regular transaction pairs
                $regularTxn = null;
                $cancellationTxn = null;
                
                foreach ($group as $item) {
                    $hasCancellation = strpos($item['data']['numeric_number'], '*') !== false;
                    if ($hasCancellation) {
                        $cancellationTxn = $item;
                    } else {
                        $regularTxn = $item;
                    }
                }
                
                // If we have both regular and cancellation transactions
                if ($regularTxn && $cancellationTxn) {
                    // Update the regular transaction with cancellation datetime
                    $regularTxn['data']['cancellation_date'] = $cancellationTxn['data']['datetime'];
                    $regularTxn['data']['has_cancellation'] = true;
                    
                    // Mark cancellation transaction to be excluded from final insert
                    $cancellationTxn['data']['exclude_from_insert'] = true;
                    
                    $processedData[] = $regularTxn['data'];
                    $processedData[] = $cancellationTxn['data'];
                } else {
                    // Add all transactions in this group as-is
                    foreach ($group as $item) {
                        $processedData[] = $item['data'];
                    }
                }
            } else {
                // Single transaction, add as-is
                $processedData[] = $group[0]['data'];
            }
        }
        
        return $processedData;
    }

    // Optimized branch validation function
    function validateBranchIdFast($branchData, $control_number, $reference_number, $region_description, $fileType, $partner, $numeric_number, $branch_id, $branchIDLabel) {
        $cntl_num = '';
        $is_cancellation = strpos($numeric_number, '*') !== false;
        
        // Extract control number based on file type and partner
        if ($is_cancellation) {
            if ($partner === 'All') {
                if ($fileType === 'KP7') {
                    if (substr($reference_number, 0, 3) === 'BPP') {
                        $cntl_num = intval(substr($reference_number, 3, 3));
                    } elseif (substr($reference_number, 0, 3) === 'BPX') {
                        $cntl_num = intval(substr($reference_number, 3, 3));
                    }
                }
            } else {
                if ($branchIDLabel === 'Branch ID') {
                    if ($fileType === 'KPX') {
                        if (isset($branch_id) && is_numeric($branch_id)) {
                            $cntl_num = ($branch_id == 581) ? 2607 : intval($branch_id);
                        } elseif (isset($branch_id) && $branch_id === 'HEAD OFFICE') {
                            $cntl_num = 2607;
                        }
                    }
                } else {
                    if ($fileType === 'KP7') {

                        if (substr($reference_number, 0, 3) === 'BPP') {
                            $cntl_num = intval(substr($reference_number, 3, 3));
                        } elseif (substr($reference_number, 0, 3) === 'BPX') {
                            $cntl_num = intval(substr($reference_number, 3, 3));
                        }

                    } elseif ($fileType === 'KPX') {
                        if ($branch_id === 'HEAD OFFICE') {
                            $cntl_num = 2607;
                        } elseif (empty($control_number)) {
                            if (substr($reference_number, 0, 3) === 'APB') {
                                $cntl_num = 2607;
                            }
                        } else {
                            if (substr($control_number, 0, 3) === 'BPX') {
                                $cntl_num = 2607;
                            } else {
                                $cntl_no_str = '';
                                for ($i = 0; $i < strlen($control_number); $i++) {
                                    if ($control_number[$i] === '-') break;
                                    if (is_numeric($control_number[$i])) {
                                        $cntl_no_str .= $control_number[$i];
                                    }
                                }
                                $cntl_num = intval($cntl_no_str);
                            }
                        }
                    }
                }
            }
        }else{
            if ($partner === 'All') {
                if ($fileType === 'KP7') {
                    if (substr($reference_number, 0, 3) === 'BPP') {
                        $cntl_num = intval(substr($reference_number, 3, 3));
                    } elseif (substr($reference_number, 0, 3) === 'BPX') {
                        $cntl_num = intval(substr($reference_number, 3, 3));
                    }
                }
            } else {
                if ($branchIDLabel === 'Branch ID') {
                    if ($fileType === 'KPX') {
                        if (isset($branch_id) && is_numeric($branch_id)) {
                            $cntl_num = ($branch_id == 581) ? 2607 : intval($branch_id);
                        } elseif (isset($branch_id) && $branch_id === 'HEAD OFFICE') {
                            $cntl_num = 2607;
                        }
                    }
                } else {
                    if ($fileType === 'KP7') {

                        if (substr($reference_number, 0, 3) === 'BPP') {
                            $cntl_num = intval(substr($reference_number, 3, 3));
                        } elseif (substr($reference_number, 0, 3) === 'BPX') {
                            $cntl_num = intval(substr($reference_number, 3, 3));
                        }

                    } elseif ($fileType === 'KPX') {
                        if ($branch_id === 'HEAD OFFICE') {
                            $cntl_num = 2607;
                        } elseif (empty($control_number)) {
                            if (substr($reference_number, 0, 3) === 'APB') {
                                $cntl_num = 2607;
                            }
                        } else {
                            if (substr($control_number, 0, 3) === 'BPX') {
                                $cntl_num = 2607;
                            } else {
                                $cntl_no_str = '';
                                for ($i = 0; $i < strlen($control_number); $i++) {
                                    if ($control_number[$i] === '-') break;
                                    if (is_numeric($control_number[$i])) {
                                        $cntl_no_str .= $control_number[$i];
                                    }
                                }
                                $cntl_num = intval($cntl_no_str);
                            }
                        }
                    }
                }
            }
        }
        
        
        // Fast lookup in preloaded data
        if ($fileType === 'KP7') {
            $key = $cntl_num . '_' . $region_description;
            return isset($branchData['kp7'][$key]);
        } elseif ($fileType === 'KPX') {
            return isset($branchData['kpx'][$cntl_num]);
        }
        
        return false;
    }
    

    // Enhanced duplicate validation function
    function checkDuplicateTransaction($postedTransactions, $numeric_number, $datetime, $reference_number) {
        // Check if it's a cancellation (has *) or regular transaction
        $is_cancellation = strpos($numeric_number, '*') !== false;
        
        // Only check if datetime is not empty
        if (!empty($datetime)) {
            // Convert datetime to consistent format for comparison
            $datetime_formatted = date('Y-m-d H:i:s', strtotime($datetime));
            
            // Create a unique key combining datetime and reference number for more accurate duplicate detection
            $unique_key = $datetime_formatted . '_' . $reference_number;
            
            if ($is_cancellation) {
                // For cancellation transactions, check against cancellation_date in database
                if (isset($postedTransactions['cancellation'][$unique_key])) {
                    return true; // Duplicate found
                }
            } else {
                // For regular transactions, check against datetime in database
                if (isset($postedTransactions['regular'][$unique_key])) {
                    return true; // Duplicate found
                }
            }
        }
        
        return false; // No duplicate
    }

    // Enhanced preload function to include reference numbers
    function preloadPostedTransactions($conn) {
        $postedTransactions = [];
        
        // Load all posted transactions with their datetime, cancellation_date, and reference_no
        $query = "SELECT DISTINCT datetime, cancellation_date, reference_no FROM mldb.billspayment_transaction WHERE post_transaction = 'posted'";
        $result = $conn->query($query);
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                // Store regular transaction datetime with reference
                if (!empty($row['datetime'])) {
                    $unique_key = $row['datetime'] . '_' . $row['reference_no'];
                    $postedTransactions['regular'][$unique_key] = true;
                }
                
                // Store cancellation transaction datetime with reference
                if (!empty($row['cancellation_date'])) {
                    $unique_key = $row['cancellation_date'] . '_' . $row['reference_no'];
                    $postedTransactions['cancellation'][$unique_key] = true;
                }
            }
        }
        
        return $postedTransactions;
    }

    if(isset($_POST['upload'])){
        if(isset($_FILES['import_file']) && $_FILES['import_file']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['import_file']['tmp_name'];
            $file_name = $_FILES['import_file']['name'];

            // Define variables BEFORE using them
            $fileType = $_POST['fileType'] ?? '';
            $selectedDate = date('Y-m-d');

            // Set session variables immediately after getting file info
            $_SESSION['original_file_name'] = $file_name;
            $_SESSION['source_file_type'] = $fileType;
            $_SESSION['transactionDate'] = $selectedDate;

            $spreadsheet = IOFactory::load($file);
            $worksheet = $spreadsheet->getActiveSheet();
            $highestRow = $worksheet->getHighestRow();

            // Preload all branch data and posted transactions once at the beginning
            $branchData = preloadBranchData($conn);
            $postedTransactions = preloadPostedTransactions($conn);

            //extracting partner id and partner name from the using partner
            $partner = $_POST['company'] ?? '';

            if($partner !== "All") {
                $squery = $conn->query("SELECT partner_id, partner_name FROM masterdata.partner_masterfile WHERE partner_name = '$partner' LIMIT 1");
                $partner_row = $squery ? $squery->fetch_assoc() : ['partner_id'=>'','partner_name'=>''];
                $partners_id = $partner_row['partner_id'] ?? '';
                $partners_name = $partner_row['partner_name'] ?? '';
            } else {
                $partners_id = 'All';
                $partners_name = 'All';
            }

            // Initialize session data arrays
            $Matched_BranchID_data = [];
            $Not_found_BranchID_data = [];
            $regionNotFoundData = [];
            $cancellation_BranchID_data = [];
            $duplicate_data = [];
            $allRowsData = []; // Add this to collect all rows first

            // Process rows with optimized logic - FIRST PASS: Collect all data
            for ($row = 10; $row <= $highestRow; $row++) {
                // Initialize variables
                $numeric_number = $conn->real_escape_string(strval($worksheet->getCell('A' . $row)->getValue()));
                
                // Quick empty row check
                if (empty(trim($numeric_number))) {
                    $isEmpty = true;
                    $checkCols = ($fileType === 'KP7') ? ['A', 'C', 'D', 'E'] : ['A', 'B', 'C', 'D'];
                    foreach ($checkCols as $col) {
                        if (!empty(trim(strval($worksheet->getCell($col . $row)->getValue())))) {
                            $isEmpty = false;
                            break;
                        }
                    }
                    if ($isEmpty) break;
                }

                // Initialize all variables
                $datetime = '';
                $control_number = '';
                $reference_number = '';
                $payor_name = '';
                $payor_address = '';
                $account_number = '';
                $account_name = '';
                $amount_paid = 0;
                $amount_charge_customer = 0;
                $amount_charge_partner = 0;
                $contact_number = '';
                $other_details = '';
                $branch_outlet = '';
                $region_description = '';
                $person_operator = '';
                $remote_branch = '';
                $remote_operator = '';
                
                // Get branch_id efficiently
                $branch_id_cell = $worksheet->getCell('N' . $row);
                $branch_id_value = $branch_id_cell ? $branch_id_cell->getValue() : '';
                
                if (is_numeric($branch_id_value)) {
                    $branch_id = $conn->real_escape_string(intval($branch_id_value));
                    $branchIDLabel = 'Branch ID';
                } else {
                    $branch_id = '';
                    $branchIDLabel = 'Branch Name';
                }

                $region_code = '';
                $settle_unsettle = null;
                $claim_unclaim = null;
                $imported_by = $conn->real_escape_string($_SESSION['admin_name']);
                // $date_uploaded = date('Y-m-d H:i:s');
                $date_uploaded = date('Y-m-d');

                // Partner handling
                if($partner === "All") {
                    $anypartnerid = $conn->real_escape_string(strval($worksheet->getCell('S' . $row)->getValue()));
                    if (!empty($anypartnerid)) {
                        $partner_query = $conn->query("SELECT partner_name FROM masterdata.partner_masterfile WHERE partner_id = '$anypartnerid' LIMIT 1");
                        $partner_result = $partner_query ? $partner_query->fetch_assoc() : ['partner_name' => ''];
                        $partner_name = $conn->real_escape_string($partner_result['partner_name'] ?? 'Unknown');
                        $partner_id = $conn->real_escape_string($anypartnerid);
                    } else {
                        $partner_name = $conn->real_escape_string($partners_name);
                        $partner_id = $conn->real_escape_string($partners_id);
                    }
                } else {
                    $partner_name = $conn->real_escape_string($partners_name);
                    $partner_id = $conn->real_escape_string($partners_id);
                }

                $source_file_type = $conn->real_escape_string($fileType);
                $is_cancellation = strpos($numeric_number, '*') !== false;

                // Extract data based on file type and partner selection
                if ($fileType === "KP7") {
                    // Fix datetime format - remove AM/PM and convert to 24-hour format
                    $datetime_raw = $worksheet->getCell('C' . $row)->getValue();
                    if ($datetime_raw) {
                        $datetime = date('Y-m-d H:i:s', strtotime($datetime_raw));
                    }
                    $control_number = $conn->real_escape_string(strval($worksheet->getCell('D' . $row)->getValue()));
                    $reference_number = $conn->real_escape_string(strval($worksheet->getCell('E' . $row)->getValue()));
                    $payor_name = $conn->real_escape_string(strval($worksheet->getCell('F' . $row)->getValue()));
                    $payor_address = $conn->real_escape_string(strval($worksheet->getCell('G' . $row)->getValue()));
                    $account_number = $conn->real_escape_string(strval($worksheet->getCell('H' . $row)->getValue()));
                    $account_name = $conn->real_escape_string(strval($worksheet->getCell('I' . $row)->getValue()));
                    $amount_paid = $conn->real_escape_string(floatval(str_replace(',', '', $worksheet->getCell('J' . $row)->getValue())));
                    $amount_charge_customer = $conn->real_escape_string(floatval(str_replace(',', '', $worksheet->getCell('K' . $row)->getValue())));
                    $amount_charge_partner = $conn->real_escape_string(floatval(str_replace(',', '', $worksheet->getCell('L' . $row)->getValue())));
                    $contact_number = $conn->real_escape_string(strval($worksheet->getCell('M' . $row)->getValue()));
                    $other_details = $conn->real_escape_string(strval($worksheet->getCell('N' . $row)->getValue()));
                    $branch_outlet = $conn->real_escape_string(strval($worksheet->getCell('O' . $row)->getValue()));
                    $region_description = $conn->real_escape_string(strval($worksheet->getCell('P' . $row)->getValue()));
                    $remote_operator = $conn->real_escape_string(strval($worksheet->getCell('Q' . $row)->getValue()));
                    
                    if($partner === "All") {
                        $anypartnerid = $conn->real_escape_string(strval($worksheet->getCell('S' . $row)->getValue()));
                    }
                } elseif ($fileType === "KPX") {
                    // Fix datetime format - remove AM/PM and convert to 24-hour format
                    $datetime_raw = $worksheet->getCell('B' . $row)->getValue();
                    if ($datetime_raw) {
                        $datetime = date('Y-m-d H:i:s', strtotime($datetime_raw));
                    }
                    $control_number = $conn->real_escape_string(strval($worksheet->getCell('C' . $row)->getValue()));
                    $reference_number = $conn->real_escape_string(strval($worksheet->getCell('D' . $row)->getValue()));
                    $payor_name = $conn->real_escape_string(strval($worksheet->getCell('E' . $row)->getValue()));
                    $payor_address = $conn->real_escape_string(strval($worksheet->getCell('F' . $row)->getValue()));
                    $account_number = $conn->real_escape_string(strval($worksheet->getCell('G' . $row)->getValue()));
                    $account_name = $conn->real_escape_string(strval($worksheet->getCell('H' . $row)->getValue()));
                    $amount_paid = $conn->real_escape_string(floatval(str_replace(',', '', $worksheet->getCell('I' . $row)->getValue())));
                    $amount_charge_customer = $conn->real_escape_string(floatval(str_replace(',', '', $worksheet->getCell('J' . $row)->getValue())));
                    $amount_charge_partner = $conn->real_escape_string(floatval(str_replace(',', '', $worksheet->getCell('K' . $row)->getValue())));
                    $contact_number = $conn->real_escape_string(strval($worksheet->getCell('L' . $row)->getValue()));
                    $other_details = $conn->real_escape_string(strval($worksheet->getCell('M' . $row)->getValue()));
                    
                    if (!empty($branch_id)) {
                        $branch_outlet = $conn->real_escape_string(strval($worksheet->getCell('O' . $row)->getValue()));
                        $region_description = $conn->real_escape_string(strval($worksheet->getCell('Q' . $row)->getValue()));
                        $person_operator = $conn->real_escape_string(strval($worksheet->getCell('R' . $row)->getValue()));
                        $remote_branch = $conn->real_escape_string(strval($worksheet->getCell('S' . $row)->getValue()));
                        $remote_operator = $conn->real_escape_string(strval($worksheet->getCell('T' . $row)->getValue()));
                    } else {
                        $branch_outlet = $conn->real_escape_string(strval($worksheet->getCell('N' . $row)->getValue()));
                        $region_description = $conn->real_escape_string(strval($worksheet->getCell('O' . $row)->getValue()));
                        $person_operator = $conn->real_escape_string(strval($worksheet->getCell('P' . $row)->getValue()));
                        $remote_branch = $conn->real_escape_string(strval($worksheet->getCell('Q' . $row)->getValue()));
                        $remote_operator = $conn->real_escape_string(strval($worksheet->getCell('R' . $row)->getValue()));
                    }
                }

                // After extracting datetime and reference_number, check for duplicates
                $datetime_raw = '';
                $reference_number = '';
                
                if ($fileType === "KP7") {
                    $datetime_raw = $worksheet->getCell('C' . $row)->getValue();
                    $reference_number = $conn->real_escape_string(strval($worksheet->getCell('E' . $row)->getValue()));
                } elseif ($fileType === "KPX") {
                    $datetime_raw = $worksheet->getCell('B' . $row)->getValue();
                    $reference_number = $conn->real_escape_string(strval($worksheet->getCell('D' . $row)->getValue()));
                }
                
                if ($datetime_raw) {
                    $datetime = date('Y-m-d H:i:s', strtotime($datetime_raw));
                    
                    // Create unique identifier for this row
                    $row_identifier = $datetime . '_' . $reference_number . '_' . $numeric_number;
                    
                    // Check if we've already processed this exact row
                    if (isset($processedRows[$row_identifier])) {
                        continue; // Skip duplicate processing
                    }
                    
                    // Mark this row as processed
                    $processedRows[$row_identifier] = true;
                    
                    // Check for duplicate transaction in database
                    $isDuplicate = checkDuplicateTransaction($postedTransactions, $numeric_number, $datetime, $reference_number);
                    
                    if ($isDuplicate) {
                        // Add to duplicate data array only once per unique transaction
                        $duplicate_data[] = [
                            'numeric_number' => $numeric_number,
                            'datetime' => $datetime,
                            'reference_number' => $reference_number,
                            'amount_paid' => floatval(str_replace(',', '', $worksheet->getCell(($fileType === 'KP7' ? 'J' : 'I') . $row)->getValue())),
                            'amount_charge_customer' => floatval(str_replace(',', '', $worksheet->getCell(($fileType === 'KP7' ? 'K' : 'J') . $row)->getValue())),
                            'amount_charge_partner' => floatval(str_replace(',', '', $worksheet->getCell(($fileType === 'KP7' ? 'L' : 'K') . $row)->getValue())),
                            'payor_name' => $conn->real_escape_string(strval($worksheet->getCell(($fileType === 'KP7' ? 'F' : 'E') . $row)->getValue())),
                            'row' => $row,
                            'is_cancellation' => strpos($numeric_number, '*') !== false
                        ];
                        continue; // Skip further processing for this row
                    }
                }
                
                // Use fast validation function
                $isValidBranch = validateBranchIdFast($branchData, $control_number, $reference_number, $region_description, $fileType, $partner, $numeric_number, $branch_id, $branchIDLabel);


                // Convert HEAD OFFICE to 2607 for database storage
                if ($branch_id === 'HEAD OFFICE') {
                    $branch_id = '2607';
                }

                // Get region_code based on file type and branch validation
                $region_code = '';
                if ($isValidBranch) {
                    // Extract control number for region_code lookup
                    $cntl_num_for_region = '';
                    $is_cancellation = strpos($numeric_number, '*') !== false;
                    
                    if ($fileType === 'KPX') {
                        // For KPX files, extract control number same way as in validateBranchIdFast
                        if ($branchIDLabel === 'Branch ID') {
                            if (isset($branch_id) && is_numeric($branch_id)) {
                                $cntl_num_for_region = ($branch_id == 581) ? 2607 : intval($branch_id);
                            } elseif ($branch_id === '2607') { // Check for converted HEAD OFFICE
                                $cntl_num_for_region = 2607;
                            }
                        } else {
                            if ($branch_id === '2607') { // Check for converted HEAD OFFICE
                                $cntl_num_for_region = 2607;
                            } elseif (empty($control_number)) {
                                if (substr($reference_number, 0, 3) === 'APB') {
                                    $cntl_num_for_region = 2607;
                                }
                            } else {
                                if (substr($control_number, 0, 3) === 'BPX') {
                                    $cntl_num_for_region = 2607;
                                } else {
                                    $cntl_no_str = '';
                                    for ($i = 0; $i < strlen($control_number); $i++) {
                                        if ($control_number[$i] === '-') break;
                                        if (is_numeric($control_number[$i])) {
                                            $cntl_no_str .= $control_number[$i];
                                        }
                                    }
                                    $cntl_num_for_region = intval($cntl_no_str);
                                }
                            }

                            $branch_id = $conn->real_escape_string($cntl_num_for_region);
                        }
                        
                        // Use branch_id to get region_code
                        if (isset($branchData['kpx'][$cntl_num_for_region])) {
                            $region_code = $branchData['kpx'][$cntl_num_for_region]['region_code'] ?? '';
                        }
                    } elseif ($fileType === 'KP7') {
                        // For KP7 files, extract from reference_number
                        if ($partner === 'All') {
                            if (substr($reference_number, 0, 3) === 'BPP') {
                                $cntl_num_for_region = intval(substr($reference_number, 3, 3));
                            } elseif (substr($reference_number, 0, 3) === 'BPX') {
                                $cntl_num_for_region = intval(substr($reference_number, 3, 3));
                            }
                        } else {
                            if (substr($reference_number, 0, 3) === 'BPP') {
                                $cntl_num_for_region = intval(substr($reference_number, 3, 3));
                            } elseif (substr($reference_number, 0, 3) === 'BPX') {
                                $cntl_num_for_region = intval(substr($reference_number, 3, 3));
                            }
                        }

                        // Multiple query approach to get exact branch_id result
                        $kp7branch_id1 = $conn->query("SELECT DISTINCT branch_id FROM masterdata.branch_profile WHERE code = '$cntl_num_for_region' AND gl_region = '$region_description' LIMIT 1");

                        $kp7branch_id2 = $conn->query("SELECT DISTINCT mbp.branch_id FROM masterdata.branch_profile AS mbp
                                                        JOIN masterdata.mlmatic_profile AS mmp ON mmp.code = mbp.code AND mmp.kpcode = mbp.kp_code
                                                        JOIN masterdata.gl_region_masterfile AS mgr ON mgr.gl_region = mmp.gl_region
                                                        WHERE mbp.code = '$cntl_num_for_region' AND mmp.gl_region = '$region_description' LIMIT 1");

                        $kp7branch_id3 = $conn->query("SELECT DISTINCT mbp.code, mmp.gl_region, mbp.branch_id, mrm.region_code FROM masterdata.branch_profile as mbp
                                                        JOIN masterdata.mlmatic_profile AS mmp
                                                        ON mmp.code = mbp.code
                                                        AND mmp.kpcode = mbp.kp_code
                                                        JOIN masterdata.region_masterfile as mrm
                                                        ON mrm.region_description = mmp.kp_region
                                                        WHERE mmp.code = '$cntl_num_for_region' AND mmp.gl_region = '$region_description' LIMIT 1");

                        // Check results in order of preference and get the first available result
                        $kp7branchid_result = null;
                        if ($kp7branch_id1 && $kp7branch_id1->num_rows > 0) {
                            $kp7branchid_result = $kp7branch_id1->fetch_assoc();
                        } elseif ($kp7branch_id2 && $kp7branch_id2->num_rows > 0) {
                            $kp7branchid_result = $kp7branch_id2->fetch_assoc();
                        } elseif ($kp7branch_id3 && $kp7branch_id3->num_rows > 0) {
                            $kp7branchid_result = $kp7branch_id3->fetch_assoc();
                        }

                        $branch_id = $conn->real_escape_string($kp7branchid_result['branch_id'] ?? '');

                        // Use code + region_description to get region_code
                        $key = $cntl_num_for_region . '_' . $region_description;
                        if (isset($branchData['kp7'][$key])) {
                            $region_code = $branchData['kp7'][$key]['region_code'] ?? '';
                        }
                    }
                }

                // After all data extraction, add to allRowsData array
                $rowData = [
                    'numeric_number' => $numeric_number,
                    'datetime' => $datetime,
                    'source_file_type' => $fileType,
                    'control_number' => $control_number,
                    'reference_number' => $reference_number,
                    'payor_name' => $payor_name,
                    'payor_address' => $payor_address,
                    'account_number' => $account_number,
                    'account_name' => $account_name,
                    'amount_paid' => $amount_paid,
                    'amount_charge_customer' => $amount_charge_customer,
                    'amount_charge_partner' => $amount_charge_partner,
                    'contact_number' => $contact_number,
                    'other_details' => $other_details,
                    'branch_id' => $branch_id,
                    'branch_outlet' => $branch_outlet,
                    'region_code' => $region_code,
                    'region_description' => $region_description,
                    'person_operator' => $person_operator,
                    'partner_name' => $partner_name,
                    'partner_id' => $partner_id,
                    'settle_unsettle' => $settle_unsettle,
                    'claim_unclaim' => $claim_unclaim,
                    'imported_by' => $imported_by,
                    'imported_date' => $date_uploaded,
                    'remote_branch' => $remote_branch,
                    'remote_operator' => $remote_operator,
                    'row_number' => $row
                ];
                
                $allRowsData[] = $rowData;
            }

            // SECOND PASS: Process cancellation matching
            $processedData = processCancellationMatching($allRowsData);

            // THIRD PASS: Categorize processed data
            foreach ($processedData as $rowData) {
                // Skip if marked for exclusion
                if (isset($rowData['exclude_from_insert']) && $rowData['exclude_from_insert']) {
                    continue;
                }
                
                // Check for duplicates
                $isDuplicate = checkDuplicateTransaction($postedTransactions, $rowData['numeric_number'], $rowData['datetime'], $rowData['reference_number']);
                
                if ($isDuplicate) {
                    $duplicate_data[] = [
                        'numeric_number' => $rowData['numeric_number'],
                        'datetime' => $rowData['datetime'],
                        'reference_number' => $rowData['reference_number'],
                        'amount_paid' => $rowData['amount_paid'],
                        'amount_charge_customer' => $rowData['amount_charge_customer'],
                        'amount_charge_partner' => $rowData['amount_charge_partner'],
                        'payor_name' => $rowData['payor_name'],
                        'row' => $rowData['row_number'],
                        'is_cancellation' => strpos($rowData['numeric_number'], '*') !== false
                    ];
                    continue;
                }
                
                // Use fast validation function
                $isValidBranch = validateBranchIdFast($branchData, $rowData['control_number'], $rowData['reference_number'], $rowData['region_description'], $fileType, $partner, $rowData['numeric_number'], $rowData['branch_id'], $branchIDLabel);
                
                // Categorize the data
                if ($isValidBranch) {
                    $is_cancellation = strpos($rowData['numeric_number'], '*') !== false;
                    if ($is_cancellation) {
                        $rowData['is_cancellation'] = true;
                        $cancellation_BranchID_data[] = $rowData;
                    } else {
                        $Matched_BranchID_data[] = $rowData;
                    }
                } else {
                    if($branch_id === '') {
                        $Not_found_BranchID_data[] = [
                            'outlet' => $rowData['branch_outlet'],
                            'reference_number' => $rowData['reference_number'],
                            'amount_paid' => $rowData['amount_paid'],
                            'amount_charge_customer' => $rowData['amount_charge_customer'],
                            'amount_charge_partner' => $rowData['amount_charge_partner'],
                            'branch_id' => $rowData['branch_id'],
                            'row' => $rowData['row_number']
                        ];
                    } else {
                        $regionNotFoundData[] = [
                            'branch_outlet' => $rowData['branch_outlet'],
                            'region_description' => $rowData['region_description'],
                            'reference_number' => $rowData['reference_number'],
                            'payor_name' => $rowData['payor_name'],
                            'amount_paid' => $rowData['amount_paid'],
                            'amount_charge_customer' => $rowData['amount_charge_customer'],
                            'amount_charge_partner' => $rowData['amount_charge_partner'],
                            'datetime' => $rowData['datetime'],
                            'row' => $rowData['row_number']
                        ];
                    }
                    
                }
            }

            // Save to session after loop (add duplicate data)
            $_SESSION['Matched_BranchID_data'] = $Matched_BranchID_data;
            $_SESSION['cancellation_BranchID_data'] = $cancellation_BranchID_data;
            $_SESSION['missing_branch_ids'] = $Not_found_BranchID_data;
            $_SESSION['region_not_found_data'] = $regionNotFoundData;
            $_SESSION['duplicate_data'] = $duplicate_data; // Add this line
        }
        
        // Define formatCurrency function if not already defined
        if (!function_exists('formatCurrency')) {
            function formatCurrency($amount) {
                return '₱ ' . number_format((float)$amount, 2);
            }
        }

    }


    // Check if the confirm_import button was clicked
    if(isset($_POST['confirm_import'])) {
        $Matched_BranchID_data = $_SESSION['Matched_BranchID_data'] ?? [];
        $cancellation_BranchID_data = $_SESSION['cancellation_BranchID_data'] ?? [];

        // Add this debug code INSIDE the confirm_import block
        error_log("Debug - Matched data count: " . count($Matched_BranchID_data));
        error_log("Debug - Cancellation data count: " . count($cancellation_BranchID_data));

        if (empty($Matched_BranchID_data) && empty($cancellation_BranchID_data)) {
            echo '<script>
                Swal.fire({
                    icon: "warning",
                    title: "No Data Found",
                    text: "No data available to import. Please upload a file first.",
                    confirmButtonText: "OK"
                }).then(() => {
                    window.location.href = "billspaymentImportFile.php";
                });
            </script>';
            exit;
        }

        $raw_matched_data = [];

        // Add matched data to raw_matched_data array
        foreach($Matched_BranchID_data as $matched_row) {
            $raw_matched_data[] = $matched_row;
        }
        
        // Add cancellation data to raw_matched_data array
        foreach($cancellation_BranchID_data as $cancellation_row) {
            $raw_matched_data[] = $cancellation_row;
        }

        // Add debug for sample row data AFTER arrays are populated
        error_log("Debug - Sample row data: " . print_r($raw_matched_data[0] ?? [], true));
        
        // Start transaction for better data integrity
        $conn->autocommit(FALSE);
        
        $insertedCount = 0;
        $errors = [];
        
        try {
            // Prepare statement for better performance and security
            $stmt = $conn->prepare("INSERT INTO mldb.billspayment_transaction (
                `status`, 
                `datetime`, 
                cancellation_date, 
                source_file, 
                control_no, 
                reference_no, 
                payor, 
                `address`, 
                account_no, 
                account_name, 
                amount_paid, 
                charge_to_partner, 
                charge_to_customer, 
                contact_no, 
                other_details, 
                branch_id, 
                outlet, 
                region_code, 
                region, 
                operator, 
                partner_name, 
                partner_id, 
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
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

            if (!$stmt) {
                throw new Exception("Failed to prepare statement: " . $conn->error);
            }

            foreach($raw_matched_data as $row) {
                $is_cancellation = strpos($row['numeric_number'], '*') !== false;
                
                // Data is already escaped, just clean it properly
                $numeric_number = $row['numeric_number'];
                $datetime = $row['datetime'];
                $source_file_type = $row['source_file_type'];
                $control_number = $row['control_number'];
                $reference_number = $row['reference_number'];
                $payor_name = $row['payor_name'];
                $payor_address = $row['payor_address'];
                $account_number = $row['account_number'];
                $account_name = $row['account_name'];
                $amount_paid = floatval($row['amount_paid']);
                $amount_charge_partner = floatval($row['amount_charge_partner']);
                $amount_charge_customer = floatval($row['amount_charge_customer']);
                $contact_number = $row['contact_number'];
                $other_details = $row['other_details'];
                $branch_id = $row['branch_id'];
                $branch_outlet = $row['branch_outlet'];
                $region_code = $row['region_code'] ?? '';
                $region_description = $row['region_description'];
                $person_operator = $row['person_operator'] ?? '';
                $partner_name = $row['partner_name'] ?? '';
                $partner_id = $row['partner_id'] ?? '';
                $imported_by = $row['imported_by'];
                $imported_date = $row['imported_date'];
                $remote_branch = $row['remote_branch'] ?? '';
                $remote_operator = $row['remote_operator'] ?? '';
                
                // Set status and datetime values
                $status = $is_cancellation ? '*' : null;
                $datetime_value = $is_cancellation ? null : $datetime;
                
                // Enhanced cancellation date logic
                $cancellation_date = null;
                if ($is_cancellation) {
                    $cancellation_date = $datetime;
                } elseif (isset($row['cancellation_date']) && !empty($row['cancellation_date'])) {
                    // This is a regular transaction with a linked cancellation
                    $cancellation_date = $row['cancellation_date'];
                }

                $settle_unsettle = null;
                $claim_unclaim = null;
                $rfp_no = null;
                $cad_no = null;
                $hold_status = null;
                $post_transaction = 'pending';

                // Build SQL query directly
                $sql = "INSERT INTO mldb.billspayment_transaction (
                    `status`, 
                    `datetime`, 
                    cancellation_date, 
                    source_file, 
                    control_no, 
                    reference_no, 
                    payor, 
                    `address`, 
                    account_no, 
                    account_name, 
                    amount_paid, 
                    charge_to_partner, 
                    charge_to_customer, 
                    contact_no, 
                    other_details, 
                    branch_id, 
                    outlet, 
                    region_code, 
                    region, 
                    operator, 
                    partner_name, 
                    partner_id, 
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
                ) VALUES (
                    " . ($status ? "'$status'" : "NULL") . ",
                    " . ($datetime_value ? "'$datetime_value'" : "NULL") . ",
                    " . ($cancellation_date ? "'$cancellation_date'" : "NULL") . ",
                    '$source_file_type',
                    '$control_number',
                    '$reference_number',
                    '$payor_name',
                    '$payor_address',
                    '$account_number',
                    '$account_name',
                    $amount_paid,
                    $amount_charge_partner,
                    $amount_charge_customer,
                    '$contact_number',
                    '$other_details',
                    '$branch_id',
                    '$branch_outlet',
                    '$region_code',
                    '$region_description',
                    '$person_operator',
                    '$partner_name',
                    '$partner_id',
                    " . ($settle_unsettle ? "'$settle_unsettle'" : "NULL") . ",
                    " . ($claim_unclaim ? "'$claim_unclaim'" : "NULL") . ",
                    '$imported_by',
                    '$imported_date',
                    " . ($rfp_no ? "'$rfp_no'" : "NULL") . ",
                    " . ($cad_no ? "'$cad_no'" : "NULL") . ",
                    " . ($hold_status ? "'$hold_status'" : "NULL") . ",
                    '$remote_branch',
                    '$remote_operator',
                    '$post_transaction'
                )";

                // Execute the query
                $result = $conn->query($sql);
                
                if ($result) {
                    $insertedCount++;
                    error_log("Successfully inserted row: " . $reference_number);
                } else {
                    $error_msg = "Row insert failed for reference: $reference_number - Error: " . $conn->error;
                    $errors[] = $error_msg;
                    error_log($error_msg);
                    error_log("Failed SQL: " . $sql); // Log the actual SQL for debugging
                }
            }

            if (empty($errors)) {
                // Commit transaction if all inserts successful
                $conn->commit();
                
                // Clear session data after successful import
                unset($_SESSION['Matched_BranchID_data']);
                unset($_SESSION['cancellation_BranchID_data']);
                unset($_SESSION['missing_branch_ids']);
                unset($_SESSION['region_not_found_data']);
                unset($_SESSION['duplicate_data']);
                unset($_SESSION['original_file_name']);
                unset($_SESSION['source_file_type']);
                unset($_SESSION['transactionDate']);
                
                echo '<script>
                    document.addEventListener("DOMContentLoaded", function() {
                        Swal.fire({
                            icon: "success",
                            title: "Import Successful!",
                            html: `
                                <div class="text-center">
                                    <i class="fas fa-check-circle text-success mb-3" style="font-size: 3rem;"></i>
                                    <h4 class="text-success mb-3">Data Successfully Imported</h4>
                                    <div class="alert alert-success">
                                        <strong>' . $insertedCount . '</strong> records have been successfully imported into the database.
                                    </div>
                                    <p class="text-muted">You can now proceed to view the imported data or import another file.</p>
                                </div>
                            `,
                            showConfirmButton: true,
                            confirmButtonText: "Continue",
                            confirmButtonColor: "#28a745",
                            allowOutsideClick: false,
                            allowEscapeKey: false
                        }).then((result) => {
                            if (result.isConfirmed) {
                                window.location.href = "billspaymentImportFile.php";
                            }
                        });
                    });
                </script>';
            } else {
                throw new Exception("Insert errors occurred: " . implode("; ", array_slice($errors, 0, 5)));
            }
        
        } catch (Exception $e) {
            // Rollback transaction if there were errors
            $conn->rollback();
            
            error_log("Import transaction failed: " . $e->getMessage());
            
            echo '<script>
                document.addEventListener("DOMContentLoaded", function() {
                    Swal.fire({
                        icon: "error",
                        title: "Import Transaction Failed",
                        html: `
                            <div class="text-center">
                                <i class="fas fa-exclamation-triangle text-danger mb-3" style="font-size: 3rem;"></i>
                                <h4 class="text-danger mb-3">Import Error Occurred</h4>
                                <div class="alert alert-danger">
                                    <strong>Error:</strong> ' . addslashes($e->getMessage()) . '
                                </div>
                                <div class="text-start mt-3">
                                    <small class="text-muted">Error details:</small>
                                    <ul class="list-unstyled mt-2">
                                        ' . (!empty($errors) ? implode('', array_map(function($error) { 
                                            return '<li class="text-danger small">• ' . htmlspecialchars($error) . '</li>'; 
                                        }, array_slice($errors, 0, 3))) : '<li class="text-danger small">• No specific error details available</li>') . '
                                        ' . (count($errors) > 3 ? '<li class="text-muted small">... and ' . (count($errors) - 3) . ' more errors</li>' : '') . '
                                    </ul>
                                </div>
                            </div>
                        `,
                        showConfirmButton: true,
                        confirmButtonText: "Try Again",
                        confirmButtonColor: "#dc3545",
                        allowOutsideClick: false
                    }).then((result) => {
                        if (result.isConfirmed) {
                            window.location.href = "billspaymentImportFile.php";
                        }
                    });
                });
            </script>';
        } finally {
            // Always restore autocommit regardless of success or failure
            $conn->autocommit(TRUE);
        }
    }



?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import File</title>
    <!-- custom CSS file link  -->
    <link rel="stylesheet" href="../assets/css/billspaymentImportFile.css?v=<?php echo time(); ?>">
    <link rel="icon" href="../images/MLW logo.png" type="image/png">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css" rel="stylesheet" />
    <!-- Font Awesome for icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- SweetAlert2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.min.css" rel="stylesheet">
    <script src="../assets/js/sweetalert2.all.min.js"></script>
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
            /* Make sure the table-responsive container shows all content */
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

        
        /* Enhanced SweetAlert2 backdrop for confidentiality */
        .swal2-container.swal2-backdrop-show {
            backdrop-filter: blur(10px);
            background-color: rgba(0,0,0,0.8) !important;
        }
        
        /* Make sure the modal itself is still clear */
        .swal2-popup {
            backdrop-filter: none !important;
            box-shadow: 0 0 20px rgba(0,0,0,0.5);
        }
        
    </style>
</head>

<body>
    <div>
        <div class="top-content">
            <div class="usernav">
                <h4 style="margin-right: 0.5rem; font-size: 1rem;"><?php echo $_SESSION['admin_name'] ?></h4>
                <h5 style="font-size: 1rem;"><?php echo "- ".$_SESSION['admin_email']."" ?></h5>
            </div>
            <?php include '../templates/admin/sidebar.php'; ?>
        </div>
    </div>
    <div id="loading-overlay">
        <div class="loading-spinner"></div>
    </div>
    <?php 
        if(isset($_POST['upload'])){
            $file = $_FILES['import_file']['tmp_name'];
            $file_name = $_FILES['import_file']['name'];
            $file_name_array = explode('.', $file_name);
            $extension = strtolower(end($file_name_array));

            // echo '<script>document.getElementById("loading-overlay").style.display = "block";</script>';
            // Show loading overlay
            if($extension === 'xlsx' || $extension === 'xls') {

                if(is_readable($file)) {
                    // Get session data
                    $matchedData = $_SESSION['Matched_BranchID_data'] ?? [];
                    $cancellationData = $_SESSION['cancellation_BranchID_data'] ?? [];
                    $notFoundData = $_SESSION['missing_branch_ids'] ?? [];
                    $regionNotFoundData = $_SESSION['region_not_found_data'] ?? [];

                    // Enhanced summary calculation function
                    function calculateTransactionSummary($matchedRows, $cancellationRows) {
                        $summaries = [
                            'net' => ['count' => 0, 'principal' => 0, 'charge_partner' => 0, 'charge_customer' => 0, 'total_charge' => 0, 'settlement' => 0],
                            'adjustment' => ['count' => 0, 'principal' => 0, 'charge_partner' => 0, 'charge_customer' => 0, 'total_charge' => 0, 'settlement' => 0],
                            'summary' => ['count' => 0, 'principal' => 0, 'charge_partner' => 0, 'charge_customer' => 0, 'total_charge' => 0, 'settlement' => 0]
                        ];

                        // Calculate NET (matched data only)
                        foreach ($matchedRows as $row) {
                            $summaries['net']['count']++;
                            $summaries['net']['principal'] += floatval($row['amount_paid'] ?? 0);
                            $summaries['net']['charge_partner'] += floatval($row['amount_charge_partner'] ?? 0);
                            $summaries['net']['charge_customer'] += floatval($row['amount_charge_customer'] ?? 0);
                        }

                        // Calculate ADJUSTMENT (cancellation data only)
                        foreach ($cancellationRows as $row) {
                            $summaries['adjustment']['count']++;
                            $summaries['adjustment']['principal'] += preg_replace( '/-/', '', floatval($row['amount_paid']) ?? 0);
                            $summaries['adjustment']['charge_partner'] += preg_replace( '/-/', '', floatval($row['amount_charge_partner'] ?? 0));
                            $summaries['adjustment']['charge_customer'] += preg_replace( '/-/', '', floatval($row['amount_charge_customer'] ?? 0));
                        }

                        // Calculate SUMMARY (all combined)
                        $allRows = array_merge($matchedRows, $cancellationRows);
                        foreach ($allRows as $row) {
                            $summaries['summary']['count']++;
                            $summaries['summary']['principal'] += floatval($row['amount_paid'] ?? 0);
                            $summaries['summary']['charge_partner'] += floatval($row['amount_charge_partner'] ?? 0);
                            $summaries['summary']['charge_customer'] += floatval($row['amount_charge_customer'] ?? 0);
                        }

                        // Calculate totals and settlements for all categories
                        foreach ($summaries as $key => &$summary) {
                            $summary['total_charge'] = $summary['charge_partner'] + $summary['charge_customer'];
                            $summary['settlement'] = $summary['principal'] - $summary['charge_partner'] - $summary['charge_customer'];
                        }

                        return $summaries;
                    }

                    // Main logic flow
                    if (!empty($notFoundData)) {
                        echo '<script>window.location.href = "branchIdErrorDisplay.php";</script>';
                    } elseif (!empty($regionNotFoundData)) {
                        // Redirect to region error display
                        echo '<script>window.location.href = "regionNotFoundErrorDisplay.php";</script>';
                    } elseif (!empty($_SESSION['duplicate_data'])) {
                        // Redirect to duplicate error display
                        echo '<script>window.location.href = "duplicateErrorDisplay.php";</script>';
                    } elseif (!empty($matchedData)) {
                        // Calculate all summaries
                        $summaries = calculateTransactionSummary($matchedData, $cancellationData);

                        // Get display variables
                        $displayData = [
                            'company' => htmlspecialchars($_POST['company'] ?? ''),
                            'partnerId' => htmlspecialchars($partners_id ?? ''),
                            'rowCount' => number_format($summaries['summary']['count']),
                            'sourceType' => htmlspecialchars(($_POST['fileType'] ?? '') . " System"),
                            'transactionDate' => htmlspecialchars(date('F d, Y', strtotime($_POST['datePicker'] ?? date('Y-m-d'))))
                        ];

                        // Define table rows data
                        $tableRows = [
                            ['label' => 'TOTAL COUNT', 'icon' => 'fas fa-calculator text-secondary'],
                            ['label' => 'TOTAL PRINCIPAL', 'icon' => 'fas fa-money-bill-wave text-success'],
                            ['label' => 'TOTAL CHARGE', 'icon' => 'fas fa-receipt text-danger'],
                            ['label' => 'CHARGE TO PARTNER', 'icon' => 'fas fa-building text-primary'],
                            ['label' => 'CHARGE TO CUSTOMER', 'icon' => 'fas fa-user text-info']
                        ];

                        echo '<div id="summary-section">
                            <div id="upload-success" class="container-fluid py-4" style="margin-top: 20px;">
                                <div class="text-center mb-4">
                                    <div class="card shadow-sm border-0 bg-light py-4">
                                        <h3 class="text-center fw-bold text-primary">Would you like to proceed inserting the data?</h3>
                                        <div class="card-body">
                                            <form method="post" id="confirmImportForm" class="d-inline">
                                                <input type="hidden" name="confirm_import" value="1">
                                                <button type="submit" class="btn btn-success btn-lg me-3 shadow-sm">
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
                                                                <td><i class="fas fa-id-card text-primary me-2"></i>Partner ID</td>
                                                                <td class="fw-semibold">' . $displayData['partnerId'] . '</td>
                                                            </tr>
                                                            <tr>
                                                                <td><i class="fas fa-building text-primary me-2"></i>Partner Name</td>
                                                                <td class="fw-semibold">' . $displayData['company'] . '</td>
                                                            </tr>
                                                            <tr>
                                                                <td><i class="fas fa-list-ol text-primary me-2"></i>Rows Imported</td>
                                                                <td class="fw-semibold">' . $displayData['rowCount'] . '</td>
                                                            </tr>
                                                            <tr>
                                                                <td><i class="fas fa-file-import text-primary me-2"></i>Source</td>
                                                                <td class="fw-semibold">' . $displayData['sourceType'] . '</td>
                                                            </tr>
                                                            <tr>
                                                                <td><i class="fas fa-calendar-alt text-primary me-2"></i>Import Date</td>
                                                                <td class="fw-semibold">' . $displayData['transactionDate'] . '</td>
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
                                                <h4 class="mb-0 text-center"><i class="fas fa-chart-line me-2"></i>Transaction Summary</h4>
                                            </div>
                                            <div class="card-body">
                                                <table class="table table-bordered table-hover align-middle">
                                                    <thead>
                                                        <tr class="bg-danger text-white text-center fw-bold">
                                                            <th class="text-center" style="width: 33%">SUMMARY</th>
                                                            <th class="text-center" style="width: 33%">ADJUSTMENT</th>
                                                            <th class="text-center" style="width: 33%">NET</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>';

                        // Generate dynamic table rows
                        foreach ($tableRows as $row) {
                            // Fix field mapping logic
                            $field = '';
                            switch ($row['label']) {
                                case 'TOTAL COUNT':
                                    $field = 'count';
                                    break;
                                case 'TOTAL PRINCIPAL':
                                    $field = 'principal';
                                    break;
                                case 'TOTAL CHARGE':
                                    $field = 'total_charge';
                                    break;
                                case 'CHARGE TO PARTNER':
                                    $field = 'charge_partner';
                                    break;
                                case 'CHARGE TO CUSTOMER':
                                    $field = 'charge_customer';
                                    break;
                                default:
                                    $field = 'count';
                            }
                            
                            echo '<tr>
                                <td class="border-end">
                                    <div class="row">
                                        <div class="col-6 fw-semibold"><i class="' . $row['icon'] . ' me-2"></i>' . $row['label'] . '</div>
                                        <div class="col-6 text-end fw-bold">' . ($row['label'] === 'TOTAL COUNT' ? number_format($summaries['summary'][$field]) : formatCurrency($summaries['summary'][$field])) . '</div>
                                    </div>
                                </td>
                                <td class="border-end">
                                    <div class="row">
                                        <div class="col-6 fw-semibold"><i class="' . $row['icon'] . ' me-2"></i>' . $row['label'] . '</div>
                                        <div class="col-6 text-end fw-bold">' . ($row['label'] === 'TOTAL COUNT' ? number_format($summaries['adjustment'][$field]) : formatCurrency($summaries['adjustment'][$field])) . '</div>
                                    </div>
                                </td>
                                <td>
                                    <div class="row">
                                        <div class="col-6 fw-semibold"><i class="' . $row['icon'] . ' me-2"></i>' . $row['label'] . '</div>
                                        <div class="col-6 text-end fw-bold">' . ($row['label'] === 'TOTAL COUNT' ? number_format($summaries['net'][$field]) : formatCurrency($summaries['net'][$field])) . '</div>
                                    </div>
                                </td>
                            </tr>';
                        }

                        echo '                  <tr class="table-secondary">
                                                            <td class="text-start fw-bold fs-5"><i class="fas fa-coins text-warning me-2"></i>TOTAL AMOUNT (PHP)</td>
                                                            <td class="text-end fw-bold fs-5"></td>
                                                            <td class="text-end fw-bold fs-5">' . formatCurrency($summaries['net']['settlement']) . '</td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <script>
                            // Hide the confirmation/summary section after clicking Confirm Import
                            document.addEventListener("DOMContentLoaded", function() {
                                var confirmForm = document.getElementById("confirmImportForm");
                                if (confirmForm) {
                                    confirmForm.addEventListener("submit", function() {
                                        var uploadSuccess = document.getElementById("upload-success");
                                        if (uploadSuccess) {
                                            uploadSuccess.style.display = "none";
                                        }
                                    });
                                }
                            });
                        </script>';
                    }
                }else{
                    echo '<script>
                                Swal.fire({
                                    icon: "error",
                                    title: "Invalid File Type",
                                    text: "Please upload a valid Excel file.",
                                    confirmButtonText: "OK"
                                }).then(() => {
                                    window.location.href = "billspaymentImportFile.php";
                                });
                            </script>';
                }
            } else {?>
                <div class="container-fluid border border-danger rounded mt-3">
                    <div class="container-fluid">
                        <form id="uploadForm" action="" method="post" enctype="multipart/form-data">
                            <div class="row mt-4 w-100 align-items-center">
                                    <!-- Partners Name Dropdown -->
                                    <?php
                                        // Fetch partners from the database
                                        $partners = [];
                                        $sql = "SELECT partner_name FROM masterdata.partner_masterfile ORDER BY partner_name ASC";
                                        $result = $conn->query($sql);
                                        if ($result && $result->num_rows > 0) {
                                            while ($row = $result->fetch_assoc()) {
                                                $partners[] = $row['partner_name'];
                                            }
                                        }
                                    ?>
                                    <div class="col-md-5 mb-3">
                                        <div class="d-flex align-items-center">
                                            <label class="form-label me-2 mb-0">Partners Name:</label>
                                                <select id="companyDropdown" class="form-select select2" aria-label="Select Company" name="company" required 
                                                    data-placeholder="Search or select a company...">
                                                                <option value="">Select Company</option> 
                                                                <option value="All">All</option>
                                                    <?php foreach ($partners as $partner): ?>
                                                        <option value="<?php echo htmlspecialchars($partner); ?>"><?php echo (isset($_SESSION['selected_partner']) && $_SESSION['selected_partner'] === $partner) ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($partner); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                        </div>
                                    </div>
                                    <!-- Source File Type Dropdown -->
                                    <div class="col-md-3 mb-3">
                                        <div class="d-flex align-items-center">
                                            <label for="fileType" class="form-label me-2 mb-0">Source File Type:</label>
                                            <select id="fileType" class="form-select" aria-label="Select File Type" name="fileType" required>
                                                <option value="">Select Source File Type</option>
                                                <option value="KPX">KPX </option>
                                                <option value="KP7">KP7 </option>
                                            </select>
                                        </div>
                                    </div>

                                        <!-- Date Picker -->
                                    <!-- <div class="col-md-3 mb-3">
                                        <div class="d-flex align-items-center">
                                            <label for="datePicker" class="form-label me-2 mb-0">Select Date:</label>
                                            <input type="date" id="datePicker" name="datePicker" class="form-control" required>
                                        </div>
                                    </div> -->

                                        <!-- File Upload Form -->
                                    <div class="col-md-6 mb-3 d-flex">
                                            <input type="file" name="import_file" accept=".xls,.xlsx" class="form-control me-2" required />
                                            <input type="submit" class="btn btn-danger" name="upload" value="Proceed">
                                    </div>
                                </div>
                        </form>
                    </div>
                </div>
                <?php
                    echo '<script>
                        Swal.fire({
                            icon: "error",
                            title: "Invalid File Type",
                            text: "Please upload a valid Excel file.",
                            confirmButtonText: "OK"
                        }).then(() => {
                            window.location.href = "billspaymentImportFile.php";
                        });
                    </script>';
            }                
        }else{?>
            <div class="container-fluid border border-danger rounded mt-3">
                <div class="container-fluid">
                    <form id="uploadForm" action="" method="post" enctype="multipart/form-data">
                        <div class="row mt-4 w-100 align-items-center">
                                                <!-- Partners Name Dropdown -->
                                <?php
                                    // Fetch partners from the database
                                    $partners = [];
                                    $sql = "SELECT partner_name FROM masterdata.partner_masterfile ORDER BY partner_name ASC";
                                    $result = $conn->query($sql);
                                    if ($result && $result->num_rows > 0) {
                                        while ($row = $result->fetch_assoc()) {
                                            $partners[] = $row['partner_name'];
                                        }
                                    }
                                ?>
                                <div class="col-md-5 mb-3">
                                    <div class="d-flex align-items-center">
                                        <label class="form-label me-2 mb-0">Partners Name:</label>
                                            <select id="companyDropdown" class="form-select select2" aria-label="Select Company" name="company" required 
                                                data-placeholder="Search or select a company...">
                                                            <option value="">Select Company</option> 
                                                            <option value="All">All</option>
                                                <?php foreach ($partners as $partner): ?>
                                                    <option value="<?php echo htmlspecialchars($partner); ?>"><?php echo (isset($_SESSION['selected_partner']) && $_SESSION['selected_partner'] === $partner) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($partner); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                    </div>
                                </div>
                                    <!-- Source File Type Dropdown -->
                                <div class="col-md-3 mb-3">
                                    <div class="d-flex align-items-center">
                                        <label for="fileType" class="form-label me-2 mb-0">Source File Type:</label>
                                        <select id="fileType" class="form-select" aria-label="Select File Type" name="fileType" required>
                                            <option value="">Select Source File Type</option>
                                            <option value="KPX">KPX </option>
                                            <option value="KP7">KP7 </option>
                                        </select>
                                    </div>
                                </div>

                                    <!-- Date Picker -->
                                <!-- <div class="col-md-3 mb-3">
                                    <div class="d-flex align-items-center">
                                        <label for="datePicker" class="form-label me-2 mb-0">Select Date:</label>
                                        <input type="date" id="datePicker" name="datePicker" class="form-control" required>
                                    </div>
                                </div> -->

                                    <!-- File Upload Form -->
                                <div class="col-md-6 mb-3 d-flex">
                                        <input type="file" name="import_file" accept=".xls,.xlsx" class="form-control me-2" required />
                                        <input type="submit" class="btn btn-danger" name="upload" value="Proceed">
                                </div>
                            </div>
                    </form>
                </div>
            </div>
    <?php
        }
    ?>


        <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>
        <!-- SweetAlert2 JS -->
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>
        <script>
            document.addEventListener("DOMContentLoaded", function() {
                if (typeof $ !== "undefined" && $.fn.select2) {
                    console.log("Initializing Select2 for partner dropdown");
                    $("#companyDropdown").select2({
                        placeholder: "Search or select a company...",
                        allowClear: true,
                        width: "100%",
                        minimumResultsForSearch: 0,
                        dropdownParent: $("#companyDropdown").parent()
                    });
                } else {
                    console.error("jQuery or Select2 library not loaded");
                }
            });
        </script>
        <script>
            // script.js or within <script> tags in <head> or before </body>
            document.getElementById('uploadForm').addEventListener('submit', function() {
                // Show loading overlay when form is submitted
                document.getElementById('loading-overlay').style.display = 'block';
            });
             // Loop through each element and set its display style to "block"
            for (var i = 0; i < elements.length; i++) {
                elements[i].style.display = "block";
            }

            $(document).ready(function() {
                $('#companyDropdown').select2({
                    placeholder: "Search or select a company...",
                    allowClear: true,
                    width: '100%',
                    dropdownParent: $('#companyDropdown').parent(),
                    minimumResultsForSearch: 0, // Always show search box
                    searchInputPlaceholder: 'Type to search partners...',
                    language: {
                        noResults: function() {
                            return "No partner found with that name";
                        }
                    }
                });

                // Add change event handler for company dropdown
                $('#companyDropdown').on('change', function() {
                    var selectedValue = $(this).val();
                    var datePicker = $('#datePicker');
                    
                    // Always keep date picker enabled and required regardless of selection
                    datePicker.prop('disabled', false);
                    datePicker.prop('required', true);
                });

                // Form validation
                $('#uploadForm').on('submit', function(e) {
                    var selectedCompany = $('#companyDropdown').val();
                    var datePicker = $('#datePicker');
                    var fileType = $('#fileType').val();
                    
                    // Validate source file type is selected
                    if (!fileType) {
                        e.preventDefault();
                        Swal.fire({
                            title: 'Missing File Type',
                            text: 'Please select a source file type (KPX or KP7).',
                            icon: 'warning',
                            confirmButtonText: 'OK'
                        });
                        return false;
                    }
                    
                    // Validate date is selected regardless of partner selection
                    if (!datePicker.val()) {
                        e.preventDefault();
                        Swal.fire({
                            title: 'Missing Date',
                            text: 'Please select a date for the upload.',
                            icon: 'warning',
                            confirmButtonText: 'OK'
                        });
                        return false;
                    }
                    
                    // Show loading overlay
                    document.getElementById('loading-overlay'). style.display = 'block';
                });
            });
            // Add the JavaScript function for the confirmation
            function confirmCancel() {
                Swal.fire({
                    title: 'Are you sure?',
                    text: "Cancelling the process will discard all uploaded data",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'Yes, cancel it!',
                    cancelButtonText: 'No, continue'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = 'billspaymentImportFile.php';
                    }
                });
            }
        </script>
    </body>
</html>