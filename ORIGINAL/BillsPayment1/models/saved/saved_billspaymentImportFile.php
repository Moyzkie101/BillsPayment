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

    echo '<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.min.css" rel="stylesheet">
        <script src="../../assets/js/sweetalert2.all.min.js"></script>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">

    ';

    // Define formatCurrency function at the top
    function formatCurrency($amount) {
        return '₱ ' . number_format((float)$amount, 2);
    }
    
    if (isset($_POST['upload'])){
        if(isset($_FILES['import_file']) && $_FILES['import_file']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['import_file']['tmp_name'];
            $file_name = $_FILES['import_file']['name'];
            $file_name_array = explode('.', $file_name);
            $extension = strtolower(end($file_name_array));

            $fileType = $_POST['fileType'] ?? '';
            $partner = $_POST['company'] ?? '';
            $selectedDate = date('Y-m-d');

            $spreadsheet = IOFactory::load($file);
            $worksheet = $spreadsheet->getActiveSheet();
            $highestRow = $worksheet->getHighestRow();

            // Increase memory limit and execution time for large files
            ini_set('memory_limit', '100000M');
            ini_set('max_execution_time', 900); // 15 minutes

            // Initialize arrays before the loops
            $partnerselection = [];
            $cancellation_raw = [];
            $regular_transactions = [];

            $duplicate_data = [];
            $ready_to_override_data = [];
            $cancellation_BranchID_data = [];
            $region_not_found_data = [];
            $partner_not_found_data = [];
            $partner_GLCode_not_found_data = [];
            $branchID_notFoundData = [];
            $rawData = [];

            if ($partner === 'All') {
                $PartnerID = 'All';
                $PartnerName = 'All';
            } else {
                $partnerQuery = "SELECT partner_id, partner_id_kpx, gl_code, partner_name FROM masterdata.partner_masterfile where partner_name = ? LIMIT 1";
                // if ($fileType === 'KPX') {
                //     $partnerQuery .= " WHERE partner_id_kpx = ? LIMIT 1";
                // }elseif ($fileType === 'KP7') {
                //     $partnerQuery .= " WHERE partner_id = ? LIMIT 1";
                // }
                $stmt = $conn->prepare($partnerQuery);
                $stmt->bind_param("s", $partner);
                $stmt->execute();
                $partnerResult = $stmt->get_result();
                if ($partnerResult && $partnerResult->num_rows > 0) {
                    $partnerData = $partnerResult->fetch_assoc();
                    $PartnerID = $partnerData['partner_id'];
                    $PartnerID_KPX = $partnerData['partner_id_kpx'];
                    $GLCode = $partnerData['gl_code'];
                    $PartnerName = $partnerData['partner_name'];
                }
            }

            $partnerselection[] = [
                'partners_id' => $PartnerID,
                'partners_id_kpx' => $PartnerID_KPX,
                'gl_code' => $GLCode,
                'companys_name' => $PartnerName

            ];

            $_SESSION['partnerselection'] = $partnerselection;

            if(is_readable($file)) {
                if($extension === 'xlsx' || $extension === 'xls') {

                    // Load the spreadsheet first
                    try {
                        $spreadsheet = IOFactory::load($file);
                    } catch (Exception $e) {
                        echo '<script>
                            Swal.fire({
                                icon: "error",
                                title: "File Loading Error",
                                text: "Error loading the Excel file: ' . $e->getMessage() . '",
                                confirmButtonText: "OK"
                            }).then(() => {
                                window.location.href = "../../dashboard/billspayment/import/billspay-transaction.php";
                            });
                        </script>';
                        exit;
                    }

                    // Move the function outside the loop and fix it
                    function checkDuplicateData($conn, $reference_number, $datetime) {
                        $duplicateData = false;
                        $sql = "SELECT COUNT(*) as count FROM mldb.billspayment_transaction WHERE post_transaction='posted' AND reference_no = ? AND (`datetime` = ? OR cancellation_date = ?) LIMIT 1";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("sss", $reference_number, $datetime, $datetime);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        if ($result) {
                                $row = $result->fetch_assoc();
                                if ($row && $row['count'] > 0) {
                                    $duplicateData = true;
                                }
                            }
                        $stmt->close();
                        return $duplicateData;
                    }

                    function checkHasAlreadyDataReadyToOverride($conn, $reference_number, $datetime) {
                        $overrideData = false;
                        $sql = "SELECT COUNT(*) as count FROM mldb.billspayment_transaction WHERE post_transaction='unposted' AND reference_no = ? AND (`datetime` = ? OR cancellation_date = ?) LIMIT 1";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("sss", $reference_number, $datetime, $datetime);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        if ($result) {
                                $row = $result->fetch_assoc();
                                if ($row && $row['count'] > 0) {
                                    $overrideData = true;
                                }
                            }
                        $stmt->close();
                        return $overrideData;
                    }

                    // function checkSpelledRegionName($conn, $fileType, $region_description) {
                    //     $isValidRegion = false;
                        
                    //     if($fileType === 'KP7') {
                    //         // For KP7, check if the region_description exists in the gl_region field
                    //         // across multiple tables to ensure comprehensive validation
                    //         $kp7Query1 = "SELECT COUNT(*) as count FROM masterdata.region_masterfile WHERE (gl_region = ? OR region_desc_kp7 = ?) LIMIT 1";
            
                    //         // Check each query until we find a match
                    //         $queries = [$kp7Query1];
            
                    //         foreach ($queries as $query) {
                    //             $stmt = $conn->prepare($query);
                    //             if ($stmt) {
                    //                 $stmt->bind_param("ss", $region_description, $region_description);
                    //                 $stmt->execute();
                    //                 $result = $stmt->get_result();
                    //                 if ($result) {
                    //                     $row = $result->fetch_assoc();
                    //                     if ($row && $row['count'] > 0) {
                    //                         $isValidRegion = true;
                    //                         break; // Found a match, no need to check other queries
                    //                     }
                    //                 }
                    //                 $stmt->close();
                    //             }
                    //         }
            
                    //     } elseif ($fileType === 'KPX') {
                    //         // For KPX with region, check if the region exists for the specific branch
                    //         $kpxQuery1 = "SELECT COUNT(*) as count FROM masterdata.region_masterfile WHERE (gl_region = ? OR region_desc_kpx = ?) LIMIT 1";

                    //         $queries = [$kpxQuery1];

                    //         foreach ($queries as $query) {
                    //             $stmt = $conn->prepare($query);
                    //             if ($stmt) {
                    //                 $stmt->bind_param("ss", $region_description, $region_description);
                    //                 $stmt->execute();
                    //                 $result = $stmt->get_result();
                    //                 if ($result) {
                    //                     $row = $result->fetch_assoc();
                    //                     if ($row && $row['count'] > 0) {
                    //                         $isValidRegion = true;
                    //                     }
                    //                 }
                    //                 $stmt->close();
                    //             }
                    //         }
                    //     }
                        
                    //     // Return true if region is NOT found (indicating an error)
                    //     return !$isValidRegion;
                    // }

                    function checkhadPartnerID($conn, $fileType, $partner, $partnerId) {
                        $partnerExists = false;
                        
                        if($fileType === 'KP7') {
                            if($partner === 'All') {
                                // Check if the partner ID from the Excel file exists in the database
                                $sql = "SELECT COUNT(*) as count FROM masterdata.partner_masterfile WHERE partner_id = ? LIMIT 1";
                                $stmt = $conn->prepare($sql);
                                $stmt->bind_param("s", $partnerId);
                                $stmt->execute();
                                $result = $stmt->get_result();
                                
                                if ($result) {
                                    $row = $result->fetch_assoc();
                                    if ($row && $row['count'] > 0) {
                                        $partnerExists = true;
                                    }
                                }
                                $stmt->close();
                            } else {
                                // For specific partner selection, assume it exists since it was selected from dropdown
                                $partnerExists = true;
                            }
                        } 
                        elseif($fileType === 'KPX') {
                            if($partner === 'All') {
                                // Check if the partner ID from the Excel file exists in the database
                                // $sql = "SELECT COUNT(*) as count FROM masterdata.partner_masterfile WHERE partner_id = ? LIMIT 1";
                                // $stmt = $conn->prepare($sql);
                                // $stmt->bind_param("s", $partnerId);
                                // $stmt->execute();
                                // $result = $stmt->get_result();
                                
                                // if ($result) {
                                //     $row = $result->fetch_assoc();
                                //     if ($row && $row['count'] > 0) {
                                //         $partnerExists = true;
                                //     }
                                // }
                                // $stmt->close();


                            } else {
                                // For specific partner selection, assume it exists since it was selected from dropdown
                                $partnerExists = true;
                            }
                        }
                        
                        // Return true if partner is NOT found (indicating an error)
                        return !$partnerExists;
                    }

                    // function checkhadpartnerGLCode($conn, $fileType, $partner, $GLCode) {
                    //     $partnerGLCodeExists = false;
                        
                    //     if($fileType === 'KP7') {
                    //         if($partner === 'All') {
                    //             // Check if the GL Code from the Excel file exists in the database
                    //             if (!empty($GLCode)) {
                    //                 $sql = "SELECT COUNT(*) as count FROM masterdata.partner_masterfile WHERE gl_code = ? LIMIT 1";
                    //                 $stmt = $conn->prepare($sql);
                    //                 $stmt->bind_param("s", $GLCode);
                    //                 $stmt->execute();
                    //                 $result = $stmt->get_result();
                                    
                    //                 if ($result) {
                    //                     $row = $result->fetch_assoc();
                    //                     if ($row && $row['count'] > 0) {
                    //                         $partnerGLCodeExists = true;
                    //                     }
                    //                 }
                    //                 $stmt->close();
                    //             }
                    //         } else {
                    //             // For specific partner selection, check if the selected partner has GL Code
                    //             if (!empty($GLCode)) {
                    //                 $partnerGLCodeExists = true;
                    //             }
                    //         }
                    //     } 
                    //     elseif($fileType === 'KPX') {
                    //         if($partner === 'All') {
                    //             // For KPX All partners, we would need to check based on partner_id_kpx
                    //             // Since KPX files don't contain GL Code directly, we need to validate differently
                    //             // if (!empty($partnerIDKPX)) {
                    //             //     $sql = "SELECT COUNT(*) as count FROM masterdata.partner_masterfile WHERE partner_id_kpx = ? AND gl_code IS NOT NULL AND gl_code != '' LIMIT 1";
                    //             //     $stmt = $conn->prepare($sql);
                    //             //     $stmt->bind_param("s", $partnerIDKPX);
                    //             //     $stmt->execute();
                    //             //     $result = $stmt->get_result();
                                    
                    //             //     if ($result) {
                    //             //         $row = $result->fetch_assoc();
                    //             //         if ($row && $row['count'] > 0) {
                    //             //             $partnerGLCodeExists = true;
                    //             //         }
                    //             //     }
                    //             //     $stmt->close();
                    //             // }
                    //         } else {
                    //             // For specific partner selection, check if the selected partner has GL Code
                    //             if (!empty($GLCode)) {
                    //                 $partnerGLCodeExists = true;
                    //             }
                    //         }
                    //     }
                        
                    //     // Return true if partner GL Code is NOT found (indicating an error)
                    //     return !$partnerGLCodeExists;
                    // }

                    function checkHadBranchID($conn, $branch_id) {
                        $sql = "SELECT COUNT(*) as count FROM masterdata.branch_profile WHERE branch_id = ? LIMIT 1";
                            $stmt = $conn->prepare($sql);
                            $stmt->bind_param("i", $branch_id);
                            $stmt->execute();
                            $result = $stmt->get_result();
                            
                            if ($result) {
                                $row = $result->fetch_assoc();
                                if ($row && $row['count'] > 0) {
                                    $branchID = true;
                                }
                            }
                        $stmt->close();
                        return !$branchID;
                    }

                    // Initialize variables before loops
                    // $cancellStatus = '';
                    // $datetime = '';
                    // $control_number = '';
                    // $reference_number = '';
                    // $payor_name = '';
                    // $payor_address = '';
                    // $account_number = '';
                    // $account_name = '';
                    // $amount_paid = 0;
                    // $amount_charge_partner = 0;
                    // $amount_charge_customer = 0;
                    // $contact_number = '';
                    // $other_details = '';
                    // $branch_id = '';
                    // $branch_outlet = '';
                    // $region_code = '';
                    // $region_description = '';
                    // $person_operator = '';
                    // $partnerName = '';
                    // $partnerId = '';
                    // $PartnerID_KPX = '';
                    // $GLCode = '';
                    // $remote_branch = null;
                    // $remote_operator = null;
                    // $settle_unsettle = null;
                    // $claim_unclaim = null;
                    // $report_date = '';
                    // $imported_by = '';
                    // $date_uploaded = '';
                    // $rfp_no = null;
                    // $cad_no = null;
                    // $hold_status = null;
                    // $post_transaction = '';

                    //column headers
                    foreach ($spreadsheet->getWorksheetIterator() as $worksheet) {
                        $getColumnLabels = [];
                        
                        // Read row 9 to get column headers
                        $highestColumn = $worksheet->getHighestColumn();
                        $columnIterator = $worksheet->getRowIterator(9, 9)->current()->getCellIterator('A', $highestColumn);
                        
                        foreach ($columnIterator as $cell) {
                            $columnValue = trim(strval($cell->getValue()));
                            if (!empty($columnValue)) {
                                $getColumnLabels[] = $columnValue;
                            }
                        }

                        if($fileType === 'KP7'){
                            $report_date = $conn->real_escape_string(strval($worksheet->getCell('B' . '3')->getValue()));

                        }

                        // Break after first worksheet since we only need headers once
                        break;
                    }

                    

                    // Process each row starting from row 10
                    for ($row = 10; $row <= $highestRow; ++$row) {
                        // Check if essential cells (A to E) are empty - if so, break the loop
                        $cellA = trim(strval($worksheet->getCell('A' . $row)->getValue()));
                        $cellB = trim(strval($worksheet->getCell('B' . $row)->getValue()));
                        $cellC = trim(strval($worksheet->getCell('C' . $row)->getValue()));
                        $cellD = trim(strval($worksheet->getCell('D' . $row)->getValue()));
                        $cellE = trim(strval($worksheet->getCell('E' . $row)->getValue()));

                        if (empty($cellA) && empty($cellB) && empty($cellC) && empty($cellD) && empty($cellE)) {
                            break;
                        }

                        // Read row 9 column A 
                        if($getColumnLabels[0] === 'STATUS'){
                            if($fileType === 'KP7'){
                                // Reset variables for each row
                                $cancellStatus = '';
                                $is_cancellation = strpos($worksheet->getCell('A' . $row)->getValue(), '*') !== false;
                                if ($is_cancellation) {
                                    $cancellStatus = '*';
                                } else {
                                    $cancellStatus = '';
                                }
    
                                $datetime_raw = $worksheet->getCell('C' . $row)->getValue();

                                if ($datetime_raw) {
                                    $datetime = date('Y-m-d H:i:s', strtotime($datetime_raw));
                                }
    
                                $reference_number= $conn->real_escape_string(strval($worksheet->getCell('E' . $row)->getValue()));
    
                                if (substr($reference_number, 0, 3) === 'BPP') {
                                    $branch_code = intval(substr($reference_number, 3, 3));
                                } elseif (substr($reference_number, 0, 3) === 'BPX') {
                                    $branch_code = intval(substr($reference_number, 3, 3));
                                }
    
                                //GET Data for region_code and zone_code
                                $region_description_raw = strval($worksheet->getCell('P' . $row)->getValue());
                                $kp7Query = "SELECT region_code, zone_code FROM masterdata.region_masterfile  WHERE (gl_region = ? OR region_desc_kp7 = ?) LIMIT 1";
                                $stmt = $conn->prepare($kp7Query);
                                if ($stmt) {
                                    $stmt->bind_param("ss", $region_description_raw, $region_description_raw);
                                    $stmt->execute();
                                    $result = $stmt->get_result();
                                    if ($result && $result->num_rows > 0) {
                                        $regioncodeData = $result->fetch_assoc();
                                        if ($regioncodeData && isset($regioncodeData['region_code']) && isset($regioncodeData['zone_code'])) {
                                            $region_code = $conn->real_escape_string(strval($regioncodeData['region_code']));
                                            $zone_code = $conn->real_escape_string(strval($regioncodeData['zone_code']));
                                        }else{
                                            $region_code = null;
                                            $zone_code = null;
                                        }
                                    }else{
                                        $region_code = null;
                                        $zone_code = null;
                                    }
                                    $stmt->close();
                                }
    
                                // First, check in branch_profile directly
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
                                        if ($branchIDData && isset($branchIDData['branch_id'])) {
                                            $branch_id = $conn->real_escape_string(intval($branchIDData['branch_id']));
                                        }else{
                                            $branch_id = null;
                                        }
                                    }else{
                                        $branch_id = null;
                                    }
                                    $stmt->close();
                                }
    
                                $control_number= $conn->real_escape_string(strval($worksheet->getCell('D' . $row)->getValue()));
                                $payor_name = $conn->real_escape_string(strval($worksheet->getCell('F' . $row)->getValue()));
                                $payor_address = $conn->real_escape_string(strval($worksheet->getCell('G' . $row)->getValue()));
                                $account_number = $conn->real_escape_string(strval($worksheet->getCell('H' . $row)->getValue()));
                                $account_name = $conn->real_escape_string(strval($worksheet->getCell('I' . $row)->getValue()));
    
                                $amount_paid = $conn->real_escape_string(floatval( str_replace(',', '', $worksheet->getCell('J' . $row)->getValue())));
                                $amount_charge_partner = $conn->real_escape_string(floatval( str_replace(',', '', $worksheet->getCell('K' . $row)->getValue())));
                                $amount_charge_customer = $conn->real_escape_string(floatval( str_replace(',', '', $worksheet->getCell('L' . $row)->getValue())));
    
                                $contact_number = $conn->real_escape_string(strval($worksheet->getCell('M' . $row)->getValue()));
                                $other_details = $conn->real_escape_string(strval($worksheet->getCell('N' . $row)->getValue()));
                                $branch_outlet = $conn->real_escape_string(strval($worksheet->getCell('O' . $row)->getValue()));
                                $region_description = $conn->real_escape_string($region_description_raw);
                                $person_operator = $conn->real_escape_string(strval($worksheet->getCell('Q' . $row)->getValue()));
    
                                if($partner === 'All'){
                                    $partnerName = $conn->real_escape_string(strval($worksheet->getCell('R' . $row)->getValue()));
                                    $partnerId = $conn->real_escape_string(strval($worksheet->getCell('S' . $row)->getValue()));

                                    $getGLCode_partner_kpx = "SELECT partner_id_kpx, gl_code FROM masterdata.partner_masterfile where partner_id = ? LIMIT 1";
                                    $stmt = $conn->prepare($getGLCode_partner_kpx);
                                    if ($stmt) {
                                        $stmt->bind_param("s", $partnerId);
                                        $stmt->execute();
                                        $result = $stmt->get_result();
                                        if ($result && $result->num_rows > 0) {
                                            $GLCodeData = $result->fetch_assoc();
                                            if ($GLCodeData) {
                                                $PartnerID_KPX = $conn->real_escape_string(strval($GLCodeData['partner_id_kpx']));
                                                $GLCode = $conn->real_escape_string(strval($GLCodeData['gl_code']));
                                            }else{
                                                $PartnerID_KPX = null;
                                                $GLCode = null;
                                            }
                                        }else{
                                            $PartnerID_KPX = null;
                                            $GLCode = null;
                                        }
                                        $stmt->close();
                                    }
                                }
                                else{
                                    $partnerName = $conn->real_escape_string(strval($PartnerName));
                                    $partnerId = $conn->real_escape_string(strval($PartnerID));
                                    $PartnerID_KPX = $conn->real_escape_string(strval($PartnerID_KPX));
                                    $GLCode = $conn->real_escape_string(strval($GLCode));
                                }
    
                                $remote_branch = null;
                                $remote_operator = null;
                            }
                            else{
                                echo '<script>
                                    document.addEventListener("DOMContentLoaded", function() {
                                        Swal.fire({
                                            icon: "error",
                                            title: "File Not Found",
                                            text: "The specified file could not be found or accessed.",
                                            confirmButtonText: "OK",
                                            confirmButtonColor: "#dc3545"
                                        }).then(() => {
                                            window.location.href = "../../dashboard/billspayment/import/billspay-transaction.php";
                                        });
                                    });
                                </script>';
                                exit;
                            }
                        } 
                        elseif($getColumnLabels[0] === 'No'){
                            if($fileType === 'KPX'){

                                // Initialize variables to prevent undefined variable warnings
                                $report_date = null; // Add this line for KPX files (they don't have report_date)
                                // Reset variables for each row
                                $cancellStatus = '';
                                $is_cancellation = strpos($worksheet->getCell('A' . $row)->getValue(), '*') !== false;
                                if ($is_cancellation) {
                                    $cancellStatus = '*';
                                } else {
                                    $cancellStatus = '';
                                }

                                if ($getColumnLabels[1] === 'Date / Time'){
                                    $datetime_raw = $worksheet->getCell('B' . $row)->getValue();
                                    if ($datetime_raw) {
                                        $datetime = date('Y-m-d H:i:s', strtotime($datetime_raw));
                                    }
        
                                    $control_number= $conn->real_escape_string(strval($worksheet->getCell('C' . $row)->getValue()));
                                    $reference_number= $conn->real_escape_string(strval($worksheet->getCell('D' . $row)->getValue()));
                                    
                                    $payor_name = $conn->real_escape_string(strval($worksheet->getCell('E' . $row)->getValue()));
                                    $payor_address = $conn->real_escape_string(strval($worksheet->getCell('F' . $row)->getValue()));
                                    $account_number = $conn->real_escape_string(strval($worksheet->getCell('G' . $row)->getValue()));
                                    $account_name = $conn->real_escape_string(strval($worksheet->getCell('H' . $row)->getValue()));
        
                                    $amount_paid = $conn->real_escape_string(floatval(str_replace(',', '', $worksheet->getCell('I' . $row)->getValue())));
                                    $amount_charge_customer = $conn->real_escape_string(floatval(str_replace(',', '', $worksheet->getCell('J' . $row)->getValue())));
                                    $amount_charge_partner = $conn->real_escape_string(floatval(str_replace(',', '', $worksheet->getCell('K' . $row)->getValue())));
        
                                    $contact_number = $conn->real_escape_string(strval($worksheet->getCell('L' . $row)->getValue()));
                                    $other_details = $conn->real_escape_string(strval($worksheet->getCell('M' . $row)->getValue()));
        
                                    $branch_id_raw = $worksheet->getCell('N' . $row)->getValue();
                                    $branch_outlet_raw = $conn->real_escape_string(strval($worksheet->getCell('O' . $row)->getValue()));
                                    if($getColumnLabels[13] === 'Branch ID'){
                                        if (is_numeric($branch_id_raw)) {
                                            $cntl_num_for_region = ($branch_id_raw == 581) ? intval(2607) : intval($branch_id_raw);
                                        } elseif ($branch_id_raw === 'HEAD OFFICE') {
                                            $cntl_num_for_region = intval(2607);
                                        }

                                        if($branch_outlet_raw === 'HEAD OFFICE'){
                                            $cntl_num_for_region = intval(2607);
                                            $branch_outlet = $branch_outlet_raw;
                                        }elseif($branch_outlet_raw === 'ML CEBU HEAD OFFICE'){
                                            $cntl_num_for_region = intval(2607);
                                            $branch_outlet = $branch_outlet_raw;
                                        }else{
                                            $branch_outlet = $branch_outlet_raw;
                                        }

                                        $branch_id = $conn->real_escape_string($cntl_num_for_region);
                                        $kpxbranchcodeQuery = "SELECT code FROM masterdata.branch_profile where branch_id = ? LIMIT 1";
                                        $stmt = $conn->prepare($kpxbranchcodeQuery);
                                        if ($stmt) {
                                            $stmt->bind_param("i", $cntl_num_for_region);
                                            $stmt->execute();
                                            $result = $stmt->get_result();
                                            if ($result && $result->num_rows > 0) {
                                                $branchCodeData = $result->fetch_assoc();
                                                if ($branchCodeData && isset($branchCodeData['code'])) {
                                                    $branch_code = $conn->real_escape_string(strval($branchCodeData['code']));
                                                } else {
                                                    $branch_code = null;
                                                }
                                            } else {
                                                $branch_code = null;
                                            }
                                            $stmt->close();
                                        }
        
                                        
                                        
                                        $region_description = strval($worksheet->getCell('Q' . $row)->getValue());
                                        $kpxregioncodeQuery1 = "SELECT region_code, zone_code FROM masterdata.region_masterfile
                                                                WHERE (gl_region = ? OR region_desc_kpx = ?) LIMIT 1";
                                        $stmt = $conn->prepare($kpxregioncodeQuery1);
                                        if ($stmt) {
                                            $stmt->bind_param("ss",$region_description, $region_description);
                                            $stmt->execute();
                                            $result = $stmt->get_result();
                                            if ($result && $result->num_rows > 0) {
                                                $regioncodeData = $result->fetch_assoc();
                                                if ($regioncodeData && isset($regioncodeData['region_code'])) {
                                                    $region_code = $conn->real_escape_string(strval($regioncodeData['region_code']));
                                                    $zone_code = $conn->real_escape_string(strval($regioncodeData['zone_code']));
                                                }else{
                                                    $region_code = null;
                                                    $zone_code = null;
                                                }
                                            }else{
                                                $region_code = null;
                                                $zone_code = null;
                                            }
                                            $stmt->close();
                                        }
                                        
                                        $person_operator = $conn->real_escape_string(strval($worksheet->getCell('R' . $row)->getValue()));
                                        $remote_branch = $conn->real_escape_string(strval($worksheet->getCell('S' . $row)->getValue()));
                                        $remote_operator = $conn->real_escape_string(strval($worksheet->getCell('T' . $row)->getValue()));
                                    }else{

                                        if ($branch_id_raw === 'HEAD OFFICE') {
                                            $cntl_num_for_region = intval(2607);
                                        } elseif (empty($control_number)) {
                                            if (substr($reference_number, 0, 3) === 'APB') {
                                                $cntl_num_for_region = intval(2607);
                                            }
                                        } else {
                                            if (substr($control_number, 0, 3) === 'BPX') {
                                                $cntl_num_for_region = intval(2607);
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
                                        $kpxbranchcodeQuery = "SELECT code FROM masterdata.branch_profile where branch_id = ? LIMIT 1";
                                        $stmt = $conn->prepare($kpxbranchcodeQuery);
                                        if ($stmt) {
                                            $stmt->bind_param("i", $cntl_num_for_region);
                                            $stmt->execute();
                                            $result = $stmt->get_result();
                                            if ($result && $result->num_rows > 0) {
                                                $branchCodeData = $result->fetch_assoc();
                                                if ($branchCodeData && isset($branchCodeData['code'])) {
                                                    $branch_code = $conn->real_escape_string(strval($branchCodeData['code']));
                                                } else {
                                                    $branch_code = null;
                                                }
                                            } else {
                                                $branch_code = null;
                                            }
                                            $stmt->close();
                                        }
        
                                        $branch_outlet = $conn->real_escape_string(strval($worksheet->getCell('N' . $row)->getValue()));
                                        $region_description = strval($worksheet->getCell('O' . $row)->getValue());
                                        $kpxregioncodeQuery1 = "SELECT region_code, zone_code FROM masterdata.region_masterfile
                                                                WHERE (gl_region = ? OR region_desc_kpx = ?) LIMIT 1";
                                        $stmt = $conn->prepare($kpxregioncodeQuery1);
                                        if ($stmt) {
                                            $stmt->bind_param("ss",$region_description, $region_description);
                                            $stmt->execute();
                                            $result = $stmt->get_result();
                                            if ($result && $result->num_rows > 0) {
                                                $regioncodeData = $result->fetch_assoc();
                                                if ($regioncodeData && isset($regioncodeData['region_code'])) {
                                                    $region_code = $conn->real_escape_string(strval($regioncodeData['region_code']));
                                                    $zone_code = $conn->real_escape_string(strval($regioncodeData['zone_code']));
                                                }else{
                                                    $region_code = null;
                                                    $zone_code = null;
                                                }
                                            }else{
                                                $region_code = null;
                                                $zone_code = null;
                                            }
                                            $stmt->close();
                                        }
                                        
                                        $person_operator = $conn->real_escape_string(strval($worksheet->getCell('P' . $row)->getValue()));
                                        $remote_branch = $conn->real_escape_string(strval($worksheet->getCell('Q' . $row)->getValue()));
                                        $remote_operator = $conn->real_escape_string(strval($worksheet->getCell('R' . $row)->getValue()));
                                    }
                                } elseif ($getColumnLabels[2] === 'Date / Time'){
                                    $datetime_raw = $worksheet->getCell('C' . $row)->getValue();

                                    if ($datetime_raw) {
                                        $datetime = date('Y-m-d H:i:s', strtotime($datetime_raw));
                                    }

                                    $control_number= $conn->real_escape_string(strval($worksheet->getCell('D' . $row)->getValue()));
                                    $reference_number= $conn->real_escape_string(strval($worksheet->getCell('E' . $row)->getValue()));
                                    
                                    $payor_name = $conn->real_escape_string(strval($worksheet->getCell('F' . $row)->getValue()));
                                    $payor_address = $conn->real_escape_string(strval($worksheet->getCell('G' . $row)->getValue()));
                                    $account_number = $conn->real_escape_string(strval($worksheet->getCell('H' . $row)->getValue()));
                                    $account_name = $conn->real_escape_string(strval($worksheet->getCell('I' . $row)->getValue()));
        
                                    $amount_paid = $conn->real_escape_string(floatval(str_replace(',', '', $worksheet->getCell('J' . $row)->getValue())));
                                    $amount_charge_customer = $conn->real_escape_string(floatval(str_replace(',', '', $worksheet->getCell('K' . $row)->getValue())));
                                    $amount_charge_partner = $conn->real_escape_string(floatval(str_replace(',', '', $worksheet->getCell('L' . $row)->getValue())));
        
                                    $contact_number = $conn->real_escape_string(strval($worksheet->getCell('M' . $row)->getValue()));
                                    $other_details = $conn->real_escape_string(strval($worksheet->getCell('N' . $row)->getValue()));
        
                                    $branch_id_raw = $worksheet->getCell('O' . $row)->getValue();
                                    if($getColumnLabels[14] === 'Branch ID'){
                                        if (is_numeric($branch_id_raw)) {
                                            $cntl_num_for_region = ($branch_id_raw == 581) ? intval(2607) : intval($branch_id_raw);
                                        } elseif ($branch_id_raw === 'HEAD OFFICE') {
                                            $cntl_num_for_region = intval(2607);
                                        }
                                        $branch_id = $conn->real_escape_string($cntl_num_for_region);
                                        $kpxbranchcodeQuery = "SELECT code FROM masterdata.branch_profile where branch_id = ? LIMIT 1";
                                        $stmt = $conn->prepare($kpxbranchcodeQuery);
                                        if ($stmt) {
                                            $stmt->bind_param("i", $cntl_num_for_region);
                                            $stmt->execute();
                                            $result = $stmt->get_result();
                                            if ($result && $result->num_rows > 0) {
                                                $branchCodeData = $result->fetch_assoc();
                                                if ($branchCodeData && isset($branchCodeData['code'])) {
                                                    $branch_code = $conn->real_escape_string(strval($branchCodeData['code']));
                                                } else {
                                                    $branch_code = null;
                                                }
                                            } else {
                                                $branch_code = null;
                                            }
                                            $stmt->close();
                                        }
        
                                        $branch_outlet = $conn->real_escape_string(strval($worksheet->getCell('P' . $row)->getValue()));
                                        $region_description = strval($worksheet->getCell('R' . $row)->getValue());
                                        $kpxregioncodeQuery1 = "SELECT region_code, zone_code FROM masterdata.region_masterfile
                                                                WHERE (gl_region = ? OR region_desc_kpx = ?) LIMIT 1";
                                        $stmt = $conn->prepare($kpxregioncodeQuery1);
                                        if ($stmt) {
                                            $stmt->bind_param("ss",$region_description, $region_description);
                                            $stmt->execute();
                                            $result = $stmt->get_result();
                                            if ($result && $result->num_rows > 0) {
                                                $regioncodeData = $result->fetch_assoc();
                                                if ($regioncodeData && isset($regioncodeData['region_code'])) {
                                                    $region_code = $conn->real_escape_string(strval($regioncodeData['region_code']));
                                                    $zone_code = $conn->real_escape_string(strval($regioncodeData['zone_code']));
                                                }else{
                                                    $region_code = null;
                                                    $zone_code = null;
                                                }
                                            }else{
                                                $region_code = null;
                                                $zone_code = null;
                                            }
                                            $stmt->close();
                                        }
                                        
                                        $person_operator = $conn->real_escape_string(strval($worksheet->getCell('S' . $row)->getValue()));
                                        $remote_branch = $conn->real_escape_string(strval($worksheet->getCell('T' . $row)->getValue()));
                                        $remote_operator = $conn->real_escape_string(strval($worksheet->getCell('U' . $row)->getValue()));
                                    }else{
                                        if ($branch_id_raw === 'HEAD OFFICE') {
                                            $cntl_num_for_region = intval(2607);
                                        } elseif (empty($control_number)) {
                                            if (substr($reference_number, 0, 3) === 'APB') {
                                                $cntl_num_for_region = intval(2607);
                                            }
                                        } else {
                                            if (substr($control_number, 0, 3) === 'BPX') {
                                                $cntl_num_for_region = intval(2607);
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
                                        $kpxbranchcodeQuery = "SELECT code FROM masterdata.branch_profile where branch_id = ? LIMIT 1";
                                        $stmt = $conn->prepare($kpxbranchcodeQuery);
                                        if ($stmt) {
                                            $stmt->bind_param("i", $cntl_num_for_region);
                                            $stmt->execute();
                                            $result = $stmt->get_result();
                                            if ($result && $result->num_rows > 0) {
                                                $branchCodeData = $result->fetch_assoc();
                                                if ($branchCodeData && isset($branchCodeData['code'])) {
                                                    $branch_code = $conn->real_escape_string(strval($branchCodeData['code']));
                                                } else {
                                                    $branch_code = null;
                                                }
                                            } else {
                                                $branch_code = null;
                                            }
                                            $stmt->close();
                                        }
        
                                        $branch_outlet = $conn->real_escape_string(strval($worksheet->getCell('O' . $row)->getValue()));
                                        $region_description = strval($worksheet->getCell('P' . $row)->getValue());
                                        $kpxregioncodeQuery1 = "SELECT region_code, zone_code FROM masterdata.region_masterfile
                                                                WHERE (gl_region = ? OR region_desc_kpx = ?) LIMIT 1";
                                        $stmt = $conn->prepare($kpxregioncodeQuery1);
                                        if ($stmt) {
                                            $stmt->bind_param("ss",$region_description, $region_description);
                                            $stmt->execute();
                                            $result = $stmt->get_result();
                                            if ($result && $result->num_rows > 0) {
                                                $regioncodeData = $result->fetch_assoc();
                                                if ($regioncodeData && isset($regioncodeData['region_code'])) {
                                                    $region_code = $conn->real_escape_string(strval($regioncodeData['region_code']));
                                                    $zone_code = $conn->real_escape_string(strval($regioncodeData['zone_code']));
                                                }else{
                                                    $region_code = null;
                                                    $zone_code = null;
                                                }
                                            }else{
                                                $region_code = null;
                                                $zone_code = null;
                                            }
                                            $stmt->close();
                                        }
                                        
                                        $person_operator = $conn->real_escape_string(strval($worksheet->getCell('Q' . $row)->getValue()));
                                        $remote_branch = $conn->real_escape_string(strval($worksheet->getCell('R' . $row)->getValue()));
                                        $remote_operator = $conn->real_escape_string(strval($worksheet->getCell('S' . $row)->getValue()));
                                    }
                                }
                                
                                
    
                                $partnerName = $conn->real_escape_string(strval($PartnerName));
                                $partnerId = $conn->real_escape_string(strval($PartnerID));
                                $PartnerID_KPX = $conn->real_escape_string(strval($PartnerID_KPX));
                                $GLCode = $conn->real_escape_string(strval($GLCode));
                            }else {
                                echo '<script>
                                    document.addEventListener("DOMContentLoaded", function() {
                                        Swal.fire({
                                            icon: "error",
                                            title: "File Not Found",
                                            text: "The specified file could not be found or accessed.",
                                            confirmButtonText: "OK",
                                            confirmButtonColor: "#dc3545"
                                        }).then(() => {
                                            window.location.href = "../../dashboard/billspayment/import/billspay-transaction.php";
                                        });
                                    });
                                </script>';
                            }
                        }

                        $settle_unsettle = null;
                        $claim_unclaim = null;
                        $rfp_no = null;
                        $cad_no = null;
                        $hold_status = null;
                        $post_transaction = 'unposted';
                        $imported_by = $conn->real_escape_string(strval($_SESSION['admin_name'] ?? $_SESSION['user_name']));
                        $date_uploaded = date('Y-m-d');

                        if(checkDuplicateData($conn, $reference_number, $datetime)){
                                $duplicate_data[] = [
                                    'datetime' => $datetime,
                                    'reference_number' => $reference_number,
                                    'amount_paid' => $amount_paid,
                                    'amount_charge_customer' => $amount_charge_customer,
                                    'amount_charge_partner' => $amount_charge_partner,
                                    'payor_name' => $payor_name,
                                    'row' => $row,
                                    'is_cancellation' => $is_cancellation,
                                    'control_number' => $control_number, // Add this for completeness
                                    'branch_id' => $branch_id,
                                    'branch_outlet' => $branch_outlet,
                                    'region_code' => $region_code,
                                    'region_description' => $region_description,
                                    'person_operator' => $person_operator,
                                    'partner_name' => $partnerName,
                                    'partner_id' => $partnerId,
                                    'account_number' => $account_number,
                                    'account_name' => $account_name,
                                    'contact_number' => $contact_number,
                                    'other_details' => $other_details
                                ];
                            } 
                            elseif (checkHasAlreadyDataReadyToOverride($conn, $reference_number, $datetime)) {
                                $ready_to_override_data[] = [
                                    'numeric_number' => $cancellStatus,
                                    'datetime' => $datetime,
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
                                    'region_code' => $region_code,
                                    'zone_code' => $zone_code,
                                    'region_description' => $region_description,
                                    'person_operator' => $person_operator,
                                    'partner_name' => $partnerName,
                                    'partner_id' => $partnerId,
                                    'PartnerID_KPX' => $PartnerID_KPX,
                                    'GLCode' => $GLCode,
                                    'remote_branch' => $remote_branch,
                                    'remote_operator' => $remote_operator,
                                    'settle_unsettle' => $settle_unsettle,
                                    'claim_unclaim' => $claim_unclaim,
                                    'imported_by' => $imported_by,
                                    'date_uploaded' => $date_uploaded,
                                    'rfp_no' => $rfp_no,
                                    'cad_no' => $cad_no,
                                    'hold_status' => $hold_status,
                                    'post_transaction' => $post_transaction
                                ];

                            }
                            // elseif (checkSpelledRegionName($conn, $fileType, $region_description)) {
                            //     // Region not found - add to region error array
                            //     $region_not_found_data[] = [
                            //         'row' => $row,
                            //         'branch_outlet' => $branch_outlet,
                            //         'region_description' => $region_description,
                            //         'reference_number' => $reference_number,
                            //         'payor_name' => $payor_name,
                            //         'amount_paid' => $amount_paid,
                            //         'amount_charge_customer' => $amount_charge_customer,
                            //         'amount_charge_partner' => $amount_charge_partner,
                            //         'datetime' => $datetime,
                            //         'control_number' => $control_number,
                            //         'branch_id' => $branch_id,
                            //         'region_code' => $region_code,
                            //         'person_operator' => $person_operator,
                            //         'partner_name' => $partnerName,
                            //         'partner_id' => $partnerId,
                            //         'account_number' => $account_number,
                            //         'account_name' => $account_name,
                            //         'contact_number' => $contact_number,
                            //         'other_details' => $other_details,
                            //         'payor_address' => $payor_address,
                            //         'remote_branch' => $remote_branch,
                            //         'remote_operator' => $remote_operator
                            //     ];
                            // } 
                            elseif (checkhadPartnerID($conn, $fileType, $partner, $partnerId)){
                                // Partner not found - add to partner error array
                                $partner_not_found_data[] = [
                                    'row' => $row,
                                    'partner_id' => $partnerId,
                                    'partner_name' => $partnerName
                                ];
                            } 
                            // elseif (checkhadpartnerGLCode($conn, $fileType, $partner, $GLCode)){
                            //     // Partner not found - add to partner error array
                            //     $partner_GLCode_not_found_data[] = [
                            //         'row' => $row,
                            //         'partner_id' => $partnerId,
                            //         'partner_id_kpx' => $PartnerID_KPX,
                            //         'partner_name' => $partnerName,
                            //         'gl_code' => $GLCode,
                            //         'reference_number' => $reference_number,
                            //         'datetime' => $datetime,
                            //         'amount_paid' => $amount_paid,
                            //         'file_type' => $fileType
                            //     ];
                            // } 
                            elseif (checkHadBranchID($conn, $branch_id)) {
                                // Branch ID not found - add to missing branch IDs array
                                $branchID_notFoundData[] = [
                                    'row' => $row,
                                    'branch_id' => $branch_id,
                                    'region_description' => $region_description,
                                    'reference_number' => $reference_number,
                                    'payor_name' => $payor_name,
                                    'amount_paid' => $amount_paid,
                                    'amount_charge_customer' => $amount_charge_customer,
                                    'amount_charge_partner' => $amount_charge_partner,
                                    'datetime' => $datetime,
                                    'control_number' => $control_number,
                                    'region_code' => $region_code,
                                    'person_operator' => $person_operator,
                                    'partner_name' => $partnerName,
                                    'partner_id' => $partnerId,
                                    'account_number' => $account_number,
                                    'account_name' => $account_name,
                                    'contact_number' => $contact_number,
                                    'other_details' => $other_details
                                ];
                            }
                            else{
                                $rawData[] = [
                                    'numeric_number' => $cancellStatus,
                                    'datetime' => $datetime,
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
                                    'region_code' => $region_code,
                                    'zone_code' => $zone_code,
                                    'region_description' => $region_description,
                                    'person_operator' => $person_operator,
                                    'partner_name' => $partnerName,
                                    'partner_id' => $partnerId,
                                    'PartnerID_KPX' => $PartnerID_KPX,
                                    'GLCode' => $GLCode,
                                    'remote_branch' => $remote_branch,
                                    'remote_operator' => $remote_operator,
                                    'settle_unsettle' => $settle_unsettle,
                                    'claim_unclaim' => $claim_unclaim,
                                    'report_date' => $report_date,
                                    'imported_by' => $imported_by,
                                    'date_uploaded' => $date_uploaded,
                                    'rfp_no' => $rfp_no,
                                    'cad_no' => $cad_no,
                                    'hold_status' => $hold_status,
                                    'post_transaction' => $post_transaction
                                ];
                            }

                    }

                    // Set session variables immediately after processing
                    $_SESSION['original_file_name'] = $file_name;
                    $_SESSION['source_file_type'] = $fileType;
                    $_SESSION['transactionDate'] = $selectedDate;
                    $_SESSION['duplicate_data'] = $duplicate_data;
                    $_SESSION['ready_to_override_data'] = $ready_to_override_data;
                    $_SESSION['region_not_found_data'] = $region_not_found_data; // Add this line
                    $_SESSION['partner_not_found_data'] = $partner_not_found_data;
                    $_SESSION['partner_GLCode_not_found_data'] = $partner_GLCode_not_found_data; // Add this line
                    $_SESSION['missing_branch_ids'] = $branchID_notFoundData;
                    $_SESSION['Matched_BranchID_data'] = $rawData; // Store non-duplicate data
                    $_SESSION['cancellation_BranchID_data'] = $cancellation_BranchID_data;

                }else{
                    echo '<script>
                                Swal.fire({
                                    icon: "error",
                                    title: "Invalid File Type",
                                    text: "Please upload a valid Excel file.",
                                    confirmButtonText: "OK"
                                }).then(() => {
                                    window.location.href = "billspay-transaction.php";
                                });
                            </script>';
                }
            }
        }
    }
    
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
                    window.location.href = "billspay-transaction.php";
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

        // NEW LOGIC: Process matched pairs and create final dataset
        $processed_data = [];
        $cancellation_refs = [];
        $regular_refs = [];
        
        if($_SESSION['source_file_type'] === 'KP7'){
            // Logic for KP7 file type
            $processed_data = $raw_matched_data;
        }
        elseif($_SESSION['source_file_type'] === 'KPX') {

            // First pass: separate cancellations and regular transactions
            foreach($raw_matched_data as $row) {
                $is_cancellation = isset($row['numeric_number']) && $row['numeric_number'] === '*';
                
                if ($is_cancellation) {
                    $cancellation_refs[$row['reference_number']] = $row;
                } else {
                    $regular_refs[$row['reference_number']] = $row;
                }
            }
            
            // Second pass: create processed dataset
            foreach($cancellation_refs as $ref_no => $cancellation_row) {
                if (isset($regular_refs[$ref_no])) {
                    // Found matching regular transaction - merge them
                    $merged_row = $cancellation_row; // Start with cancellation data
                    $merged_row['regular_datetime'] = $regular_refs[$ref_no]['datetime']; // Add regular datetime
                    $processed_data[] = $merged_row;
                    
                    // Remove the regular transaction from processing (it won't be inserted separately)
                    // unset($regular_refs[$ref_no]);
                } else {
                    // Cancellation without matching regular transaction
                    $processed_data[] = $cancellation_row;
                }
            }
            
            // Add any remaining regular transactions (those without cancellations)
            foreach($regular_refs as $regular_row) {
                $processed_data[] = $regular_row;
            }
        }

        // Add debug for sample row data AFTER arrays are populated
        error_log("Debug - Processed data count: " . count($processed_data));
        error_log("Debug - Sample processed row: " . print_r($processed_data[0] ?? [], true));
        
        // Start transaction for better data integrity
        $conn->autocommit(FALSE);
        
        $insertedCount = 0;
        $errors = [];
        
        try {

            foreach($processed_data as $row) {
                // Check if it's a cancellation transaction based on numeric_number
                $is_cancellation = isset($row['numeric_number']) && $row['numeric_number'] === '*';
                
                // Use the existing field names from your data structure
                $status = $is_cancellation ? '*' : null;
                $source_file = $_SESSION['source_file_type'] ?? $fileType;
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
                $branch_code = $row['branch_code'];
                $branch_outlet = $row['branch_outlet'];
                $region_code = $row['region_code'];
                $zone_code = $row['zone_code'];
                $region_description = $row['region_description'];
                $person_operator = $row['person_operator'];
                $partner_name = $row['partner_name'];
                $partner_id = $row['partner_id'];
                $partner_ID_KPX = $row['PartnerID_KPX'] ?? null;
                $GLCode = $row['GLCode'] ?? null;
                $imported_by = $row['imported_by'];
                $imported_date = $row['date_uploaded'];
                $remote_branch = $row['remote_branch'];
                $remote_operator = $row['remote_operator'];
                
                // NEW LOGIC: Handle datetime based on whether it's a matched cancellation
                $datetime_value = null;
                $cancellation_date = null;
                
                if ($is_cancellation && isset($row['regular_datetime'])) {
                    // This is a cancellation with matching regular transaction
                    $datetime_value = $row['regular_datetime']; // Regular transaction datetime
                    $cancellation_date = $row['datetime']; // Cancellation datetime
                } elseif ($is_cancellation) {
                    // This is a cancellation without matching regular transaction
                    if ($_SESSION['source_file_type'] === 'KP7') {
                        $datetime_value = $row['datetime'];
                        $cancellation_date = date('Y-m-d H:i:s', strtotime($row['report_date'])) ?? null;
                    }elseif($_SESSION['source_file_type'] === 'KPX') {
                        $cancellation_date = $row['datetime'];
                        $datetime_value = null;
                    }
                } else {
                    // This is a regular transaction without cancellation
                    $datetime_value = $row['datetime'];
                    $cancellation_date = null;
                }

                $settle_unsettle = null;
                $claim_unclaim = null;
                $rfp_no = null;
                $cad_no = null;
                $hold_status = null;
                $post_transaction = 'unposted';

                // Build SQL query with proper escaping
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
                ) VALUES (
                    " . ($status ? "'$status'" : "NULL") . ",
                    " . ($datetime_value ? "'$datetime_value'" : "NULL") . ",
                    " . ($cancellation_date ? "'$cancellation_date'" : "NULL") . ",
                    '$source_file',
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
                    '$branch_code',
                    '$branch_outlet',
                    '$zone_code',
                    '$region_code',
                    '$region_description',
                    '$person_operator',
                    '$partner_name',
                    " . ($partner_id ? "'$partner_id'" : "NULL") . ",
                    " . ($partner_ID_KPX ? "'$partner_ID_KPX'" : "NULL") . ",
                    " . ($GLCode ? "'$GLCode'" : "NULL") . ",
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
                    error_log("Successfully inserted row: " . $reference_number . 
                            " (Cancellation: " . ($is_cancellation ? 'Yes' : 'No') . 
                            ", Has matched pair: " . (isset($row['regular_datetime']) ? 'Yes' : 'No') . ")");
                } else {
                    $error_msg = "Row insert failed for reference: $reference_number - Error: " . $conn->error;
                    $errors[] = $error_msg;
                    error_log($error_msg);
                    error_log("Failed SQL: " . $sql);
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
                unset($_SESSION['ready_to_override_data']);
                unset($_SESSION['original_file_name']);
                unset($_SESSION['source_file_type']);
                unset($_SESSION['transactionDate']);
                
                echo '<script>
                    document.addEventListener("DOMContentLoaded", function() {
                        Swal.fire({
                            icon: "success",
                            title: "Data Successfully Imported",
                            html: `
                                <div class="text-center">
                                    <div class="alert alert-success">
                                        <strong>' . $insertedCount . '</strong> records inserted.
                                    </div>
                                </div>
                            `,
                            showConfirmButton: true,
                            confirmButtonText: "Close",
                            confirmButtonColor: "#28a745",
                            allowOutsideClick: false,
                            allowEscapeKey: false
                        }).then((result) => {
                            if (result.isConfirmed) {
                                window.location.href = "../../dashboard/billspayment/import/billspay-transaction.php";
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
                        allowOutsideClick: true
                    }).then((result) => {
                        if (result.isConfirmed) {
                            window.location.href = "../../dashboard/billspayment/import/billspay-transaction.php";
                        }
                    });
                });
            </script>';
        } finally {
            // Always restore autocommit regardless of success or failure
            $conn->autocommit(TRUE);
        }
    } elseif (isset($_POST['override_comfirm'])){
        $ready_to_override_data = $_SESSION['ready_to_override_data'] ?? [];

        // Increase memory limit and execution time for large files
        ini_set('memory_limit', '1024M');
        ini_set('max_execution_time', 900); // 15 minutes
        
        if (empty($ready_to_override_data)) {
            echo '<script>
                Swal.fire({
                    icon: "warning",
                    title: "No Override Data Found",
                    text: "No data available to override. Please upload a file first.",
                    confirmButtonText: "OK"
                }).then(() => {
                    window.location.href = "../../dashboard/billspayment/import/billspay-transaction.php";
                });
            </script>';
            exit;
        }

        // Process override data to handle cancellation matching
        $processed_override_data = [];
        $matched_data = $_SESSION['Matched_BranchID_data'] ?? [];
        $cancellation_data = $_SESSION['cancellation_BranchID_data'] ?? [];
        
        // Separate cancellations and regular transactions from override data
        $override_cancellations = [];
        $override_regular = [];
        
        foreach($ready_to_override_data as $row) {
            $is_cancellation = isset($row['numeric_number']) && $row['numeric_number'] === '*';
            if ($is_cancellation) {
                $override_cancellations[$row['reference_number']] = $row;
            } else {
                $override_regular[$row['reference_number']] = $row;
            }
        }
        
        // Process cancellation matching for override data
        foreach($override_cancellations as $ref_no => $cancellation_row) {
            if (isset($override_regular[$ref_no])) {
                // Found matching regular transaction - merge them
                $merged_row = $cancellation_row; // Start with cancellation data
                $merged_row['regular_datetime'] = $override_regular[$ref_no]['datetime']; // Add regular datetime
                $processed_override_data[] = $merged_row;
                
                // Remove the regular transaction from processing (it won't be inserted separately)
                // unset($override_regular[$ref_no]);
            } else {
                // Cancellation without matching regular transaction
                $processed_override_data[] = $cancellation_row;
            }
        }
        
        // Add any remaining regular transactions (those without cancellations)
        foreach($override_regular as $regular_row) {
            $processed_override_data[] = $regular_row;
        }

        // Calculate counts
        $matched_count = count($processed_override_data); // Records that match existing data (processed)
        
        // Calculate unmatched data from other session arrays  
        $unmatched_count = count($matched_data) + count($cancellation_data); // Records that don't match existing data

        // Show first confirmation modal
        echo '<script>
            document.addEventListener("DOMContentLoaded", function() {
                Swal.fire({
                    title: "Feedback Not Found",
                    html: `
                        <div class="text-center">
                            <i class="fas fa-exclamation-triangle text-warning mb-3" style="font-size: 3rem;"></i>
                            <h4 class="text-warning mb-3">Data Override Detected</h4>
                            <div class="alert alert-info">
                                <strong>' . $matched_count . '</strong> rows detected with matching reference number and datetime
                            </div>
                            <div class="alert alert-secondary">
                                <strong>' . $unmatched_count . '</strong> rows do not match existing data
                            </div>
                            <p class="text-muted">Some records already exist in the database with pending status.</p>
                            <p class="text-info"><strong>Note:</strong> Cancellation transactions (*) will be matched with their corresponding regular transactions.</p>
                        </div>
                    `,
                    icon: "warning",
                    confirmButtonText: "OK",
                    confirmButtonColor: "#ffc107",
                    allowOutsideClick: false,
                    allowEscapeKey: false
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Show second confirmation modal
                        Swal.fire({
                            title: "Do you want to Override it?",
                            html: `
                                <div class="text-center">
                                    <i class="fas fa-question-circle text-primary mb-3" style="font-size: 3rem;"></i>
                                    <h4 class="text-primary mb-3">Confirm Override Action</h4>
                                    <div class="alert alert-warning">
                                        <strong>Override Process:</strong><br>
                                        • ' . $matched_count . ' existing records will be deleted and replaced<br>
                                        • ' . $unmatched_count . ' new records will be inserted directly<br>
                                        • Cancellation (*) and regular transactions with same reference will be merged<br>
                                        • This action cannot be undone
                                    </div>
                                    <p class="text-muted"><strong>Note:</strong> All data will be processed in a single transaction.</p>
                                </div>
                            `,
                            icon: "question",
                            showCancelButton: true,
                            confirmButtonText: "Yes, Override it",
                            cancelButtonText: "No, Cancel it",
                            confirmButtonColor: "#28a745",
                            cancelButtonColor: "#dc3545",
                            allowOutsideClick: false,
                            allowEscapeKey: false
                        }).then((result) => {
                            if (result.isConfirmed) {
                                // User confirmed override - process the data
                                processOverrideData();
                            } else {
                                // User cancelled - redirect back
                                window.location.href = "../../dashboard/billspayment/import/billspay-transaction.php";
                            }
                        });
                    }
                });
            });

            function processOverrideData() {
                // Show loading
                Swal.fire({
                    title: "Processing Override...",
                    html: "Please wait while we process your data override.",
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                // Submit form to process override
                const form = document.createElement("form");
                form.method = "POST";
                form.style.display = "none";
                
                const input = document.createElement("input");
                input.type = "hidden";
                input.name = "process_override";
                input.value = "1";
                
                form.appendChild(input);
                document.body.appendChild(form);
                form.submit();
            }
        </script>';
    
    // Store processed data back to session
    $_SESSION['processed_override_data'] = $processed_override_data;
    
} elseif (isset($_POST['process_override'])) {
    $processed_override_data = $_SESSION['processed_override_data'] ?? [];
    $matched_data = $_SESSION['Matched_BranchID_data'] ?? [];
    $cancellation_data = $_SESSION['cancellation_BranchID_data'] ?? [];

    // Increase memory limit and execution time for large files
    ini_set('memory_limit', '100000M');
    ini_set('max_execution_time', 900); // 15 minutes
    
    if (empty($processed_override_data) && empty($matched_data) && empty($cancellation_data)) {
        echo '<script>
            Swal.fire({
                icon: "error",
                title: "No Data to Process",
                text: "No override data found to process.",
                confirmButtonText: "OK"
            }).then(() => {
                window.location.href = "../../dashboard/billspayment/import/billspay-transaction.php";
            });
        </script>';
        exit;
    }

    // Start transaction for better data integrity
    $conn->autocommit(FALSE);
    
    $processedCount = 0;
    $insertedCount = 0;
    $deletedCount = 0;
    $errors = [];
    
    try {
        // Step 1: Process override data (records that match existing data)
        // FIXED: First create reference map for datetime sharing
        $reference_datetime_map = [];
        
        // Build map from ALL sources: override data, matched data, and cancellation data
        $all_data_sources = [
            $processed_override_data,
            $matched_data,
            $cancellation_data
        ];
        
        // Build map of regular transaction datetimes by reference number
        foreach($all_data_sources as $data_source) {
            foreach($data_source as $row) {
                $is_cancellation = isset($row['numeric_number']) && $row['numeric_number'] === '*';
                if (!$is_cancellation) {
                    // This is a regular transaction, store its datetime
                    $reference_datetime_map[$row['reference_number']] = $row['datetime'];
                }
            }
        }
        
        foreach($processed_override_data as $row) {
            // Get source file from session or use default
            $source_file = $_SESSION['source_file_type'] ?? 'Unknown';

            // First, clean up existing data using your specified DELETE criteria
            $deleteSQL = "DELETE FROM `mldb`.`billspayment_transaction` 
                        WHERE post_transaction = ? 
                        AND reference_no = ? 
                        AND (`datetime` = ? OR cancellation_date = ?)";
            if (isset($source_file) && $source_file === 'KP7'){
                $deleteSQL .= " AND partner_id = ?";

                $deleteStmt = $conn->prepare($deleteSQL);
                $deleteStmt->bind_param("sssss", 
                    $row['post_transaction'], 
                    $row['reference_number'], 
                    $row['datetime'], 
                    $row['datetime'], 
                    $row['partner_id']
                );
            }elseif (isset($source_file) && $source_file === 'KPX'){
                $deleteSQL .= " AND partner_id_kpx = ?";

                $deleteStmt = $conn->prepare($deleteSQL);
                $deleteStmt->bind_param("sssss", 
                    $row['post_transaction'], 
                    $row['reference_number'], 
                    $row['datetime'], 
                    $row['datetime'], 
                    $row['PartnerID_KPX']
                );
            }
            
            if (!$deleteStmt->execute()) {
                throw new Exception("Failed to delete existing record for reference: " . $row['reference_number'] . " - Error: " . $deleteStmt->error);
            }
            
            $deletedCount += $deleteStmt->affected_rows;
            
            // Then insert new record - handle cancellation matching
            $is_cancellation = isset($row['numeric_number']) && $row['numeric_number'] === '*';
            $status = $is_cancellation ? '*' : null;
            
            // COMPLETELY FIXED LOGIC: Handle datetime properly for cancellations
            $datetime_value = null;
            $cancellation_date = null;
            
            if ($is_cancellation) {
                // This is a cancellation - ALWAYS try to find matching regular transaction datetime
                if (isset($reference_datetime_map[$row['reference_number']])) {
                    // Found matching regular transaction - use its datetime for main datetime field
                    $datetime_value = $reference_datetime_map[$row['reference_number']];
                    $cancellation_date = $row['datetime']; // Cancellation's own datetime goes to cancellation_date
                } else {
                    // No matching regular transaction found - handle based on file type
                    if ($_SESSION['source_file_type'] === 'KP7') {
                        // For KP7, use cancellation datetime as main datetime
                        $datetime_value = $row['datetime'];
                        $cancellation_date = null;
                    } elseif ($_SESSION['source_file_type'] === 'KPX') {
                        // For KPX, put datetime in cancellation_date field, keep main datetime null
                        $datetime_value = null;
                        $cancellation_date = $row['datetime'];
                    }
                }
            } else {
                // This is a regular transaction - always use its own datetime
                $datetime_value = $row['datetime'];
                $cancellation_date = null;
            }
            
            // Debug logging to verify the logic
            error_log("Processing override for ref: " . $row['reference_number'] . 
                    ", is_cancellation: " . ($is_cancellation ? 'true' : 'false') . 
                    ", datetime_value: " . ($datetime_value ?? 'null') . 
                    ", cancellation_date: " . ($cancellation_date ?? 'null'));
            
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
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ? , ?)";

            $insertStmt = $conn->prepare($insertSQL);
            
            $insertStmt->bind_param("ssssssssssdddssissssssssssssssssssss", //36
                $status,
                $datetime_value,
                $cancellation_date,
                $source_file,
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
                $row['settle_unsettle'],
                $row['claim_unclaim'],
                $row['imported_by'],
                $row['date_uploaded'],
                $row['rfp_no'],
                $row['cad_no'],
                $row['hold_status'],
                $row['remote_branch'],
                $row['remote_operator'],
                $row['post_transaction']
            );
            
            if (!$insertStmt->execute()) {
                throw new Exception("Failed to insert override record for reference: " . $row['reference_number'] . " - Error: " . $insertStmt->error);
            }
            
            $deleteStmt->close();
            $insertStmt->close();
            $processedCount++;
        }

        // Step 2: Process unmatched data (records that don't exist in database)
        $all_unmatched_data = array_merge($matched_data, $cancellation_data);
        
        
        // Insert all unmatched data individually
        foreach($all_unmatched_data as $row) {
            $is_cancellation = isset($row['numeric_number']) && $row['numeric_number'] === '*';
            $status = $is_cancellation ? '*' : null;
            
            // SAME FIXED LOGIC: Handle datetime properly for unmatched cancellations
            $datetime_value = null;
            $cancellation_date = null;
            
            if ($is_cancellation) {
                // This is a cancellation - use the complete reference map
                if (isset($reference_datetime_map[$row['reference_number']])) {
                    // Found matching regular transaction - use its datetime for main datetime field
                    $datetime_value = $reference_datetime_map[$row['reference_number']];
                    $cancellation_date = $row['datetime']; // Cancellation's own datetime goes to cancellation_date
                } else {
                    // No matching regular transaction found - handle based on file type
                    if ($_SESSION['source_file_type'] === 'KP7') {
                        // For KP7, use cancellation datetime as main datetime
                        $datetime_value = $row['datetime'];
                        $cancellation_date = null;
                    } elseif ($_SESSION['source_file_type'] === 'KPX') {
                        // For KPX, put datetime in cancellation_date field, keep main datetime null
                        $datetime_value = null;
                        $cancellation_date = $row['datetime'];
                    }
                }
            } else {
                // This is a regular transaction - always use its own datetime
                $datetime_value = $row['datetime'];
                $cancellation_date = null;
            }
            
            // Debug logging for unmatched data too
            error_log("Processing unmatched for ref: " . $row['reference_number'] . 
                    ", is_cancellation: " . ($is_cancellation ? 'true' : 'false') . 
                    ", datetime_value: " . ($datetime_value ?? 'null') . 
                    ", cancellation_date: " . ($cancellation_date ?? 'null'));
            
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
            
            // Get source file from session or use default
            $source_file = $_SESSION['source_file_type'] ?? 'Unknown';

            $insertStmt->bind_param("ssssssssssdddssiisssssssssssssssssss", //36
                $status,
                $datetime_value,
                $cancellation_date,
                $source_file,
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
                $row['settle_unsettle'],
                $row['claim_unclaim'],
                $row['imported_by'],
                $row['date_uploaded'],
                $row['rfp_no'],
                $row['cad_no'],
                $row['hold_status'],
                $row['remote_branch'],
                $row['remote_operator'],
                $row['post_transaction']
            );
            
            if (!$insertStmt->execute()) {
                throw new Exception("Failed to insert new record for reference: " . $row['reference_number'] . " - Error: " . $insertStmt->error);
            }
            
            $insertStmt->close();
            $insertedCount++;
        }

        // Commit transaction if all operations successful
        $conn->commit();
        
        // Clear session data after successful override
        unset($_SESSION['ready_to_override_data']);
        unset($_SESSION['processed_override_data']);
        unset($_SESSION['Matched_BranchID_data']);
        unset($_SESSION['cancellation_BranchID_data']);
        unset($_SESSION['original_file_name']);
        unset($_SESSION['source_file_type']);
        unset($_SESSION['transactionDate']);
        
        $totalProcessed = $processedCount + $insertedCount;
        
        echo '<script>
            document.addEventListener("DOMContentLoaded", function() {
                Swal.fire({
                    icon: "success",
                    title: "Override Successful!",
                    html: `
                        <div class="text-center">
                            <i class="fas fa-check-circle text-success mb-3" style="font-size: 3rem;"></i>
                            <h4 class="text-success mb-3">Data Successfully Processed</h4>
                            <div class="alert alert-success">
                                <strong>Processing Summary:</strong><br>
                                • <strong>' . $deletedCount . '</strong> existing records deleted<br>
                                • <strong>' . $processedCount . '</strong> records overridden<br>
                                • <strong>' . $insertedCount . '</strong> new records inserted<br>
                                • <strong>' . $totalProcessed . '</strong> total records processed
                            </div>
                            <p class="text-muted">Cancellation records (*) with matching reference numbers now inherit datetime from regular transactions.</p>
                        </div>
                    `,
                    showConfirmButton: true,
                    confirmButtonText: "Continue",
                    confirmButtonColor: "#28a745",
                    allowOutsideClick: false,
                    allowEscapeKey: false
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = "../../dashboard/billspayment/import/billspay-transaction.php";
                    }
                });
            });
        </script>';
        
    } 
    catch (Exception $e) {
        // Rollback transaction if there were errors
        $conn->rollback();
        
        error_log("Override transaction failed: " . $e->getMessage());
        
        echo '<script>
            document.addEventListener("DOMContentLoaded", function() {
                Swal.fire({
                    icon: "error",
                    title: "Override Failed",
                    html: `
                        <div class="text-center">
                            <i class="fas fa-exclamation-triangle text-danger mb-3" style="font-size: 3rem;"></i>
                            <h4 class="text-danger mb-3">Override Error Occurred</h4>
                            <div class="alert alert-danger">
                                <strong>Error:</strong> ' . addslashes($e->getMessage()) . '
                            </div>
                            <p class="text-muted">Please try again or contact system administrator.</p>
                        </div>
                    `,
                    showConfirmButton: true,
                    confirmButtonText: "Try Again",
                    confirmButtonColor: "#dc3545",
                    allowOutsideClick: true
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = "../../dashboard/billspayment/import/billspay-transaction.php";
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
    <link rel="stylesheet" href="../../assets/css/billspaymentImportFile.css?v=<?php echo time(); ?>">
    <link rel="icon" href="../../images/MLW logo.png" type="image/png">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css" rel="stylesheet" />
    <!-- Font Awesome for icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- SweetAlert2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.min.css" rel="stylesheet">
    <script src="../../assets/js/sweetalert2.all.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>
    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>
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
    <div id="loading-overlay">
        <div class="loading-spinner"></div>
    </div>
    <?php 
        if(isset($_POST['upload'])){
            $file = $_FILES['import_file']['tmp_name'];
            $file_name = $_FILES['import_file']['name'];
            $file_name_array = explode('.', $file_name);
            $extension = strtolower(end($file_name_array));

            if(is_readable($file)) {
                if($extension === 'xlsx' || $extension === 'xls') {

                    // Get session data
                    $partnerSelection = $_SESSION['partnerselection'] ?? [];

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

                        // First, collect all cancellation reference numbers
                        $cancellation_reference_numbers = [];
                        foreach ($matchedRows as $row) {
                            if (isset($row['numeric_number']) && $row['numeric_number'] === '*') {
                                $cancellation_reference_numbers[] = $row['reference_number'];
                            }
                        }

                        // Also check the separate cancellation array if it exists
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

                        // Remove duplicates
                        $cancellation_reference_numbers = array_unique($cancellation_reference_numbers);

                        // Calculate SUMMARY (Only regular transactions that DON'T have matching cancellations)
                        foreach ($matchedRows as $row) {
                            // Only include regular transactions (not cancellations)
                            if (!isset($row['numeric_number']) || $row['numeric_number'] !== '*') {
                                // Check if this regular transaction has a matching cancellation
                                if (!in_array($row['reference_number'], $cancellation_reference_numbers)) {
                                    // This regular transaction doesn't have a matching cancellation, include it in SUMMARY
                                    $summaries['summary']['count']++;
                                    $summaries['summary']['principal'] += abs(floatval($row['amount_paid'] ?? 0));
                                    $summaries['summary']['charge_partner'] += abs(floatval($row['amount_charge_partner'] ?? 0));
                                    $summaries['summary']['charge_customer'] += abs(floatval($row['amount_charge_customer'] ?? 0));
                                }
                            }
                        }

                        // Calculate ADJUSTMENT (only cancellation transactions)
                        foreach ($matchedRows as $row) {
                            if (isset($row['numeric_number']) && $row['numeric_number'] === '*') {
                                $summaries['adjustment']['count']++;
                                $summaries['adjustment']['principal'] += abs(floatval($row['amount_paid'] ?? 0));
                                $summaries['adjustment']['charge_partner'] += abs(floatval($row['amount_charge_partner'] ?? 0));
                                $summaries['adjustment']['charge_customer'] += abs(floatval($row['amount_charge_customer'] ?? 0));
                            }
                        }

                        // Also check the separate cancellation array for ADJUSTMENT
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

                        // Calculate NET as SUMMARY - ADJUSTMENT
                        $summaries['net']['count'] = $summaries['summary']['count'] - $summaries['adjustment']['count'];
                        $summaries['net']['principal'] = $summaries['summary']['principal'] - $summaries['adjustment']['principal'];
                        $summaries['net']['charge_partner'] = $summaries['summary']['charge_partner'] - $summaries['adjustment']['charge_partner'];
                        $summaries['net']['charge_customer'] = $summaries['summary']['charge_customer'] - $summaries['adjustment']['charge_customer'];

                        // Calculate totals and settlements for all categories
                        foreach ($summaries as $key => &$summary) {
                            $summary['total_charge'] = $summary['charge_partner'] + $summary['charge_customer'];
                            $summary['settlement'] = $summary['principal'] - $summary['charge_partner'] - $summary['charge_customer'];
                        }

                        return $summaries;
                    }

                    // Main logic flow
                    if (!empty($partner_not_found_data)) {
                        error_log("Redirecting to partner error page");
                        echo '<script>window.location.href = "../error/partnerNotFoundErrorDisplay.php";</script>'; // DONE
                    } elseif (!empty($partner_GLCode_not_found_data)) {
                        error_log("Handling GL Code error");
                        
                        // Check if it's a specific partner or All partners
                        if ($partner !== 'All') {
                            // For specific partner, show SweetAlert modal
                            $partnerInfo = $partner_GLCode_not_found_data[0]; // Get first error for partner info
                            echo '<script>
                                document.addEventListener("DOMContentLoaded", function() {
                                    Swal.fire({
                                        icon: "error",
                                        title: "No GL Code Found",
                                        html: `
                                            <div class="text-center">
                                                <i class="fas fa-exclamation-triangle text-danger mb-3" style="font-size: 3rem;"></i>
                                                <h4 class="text-danger mb-3">Partner GL Code Missing</h4>
                                                <div class="alert alert-danger">
                                                    <strong>No GL Code for this Partner, Please contact your administrator to assign GL Codes before importing.</strong>
                                                </div>
                                                <div class="text-start mt-3">
                                                    <table class="table table-bordered">
                                                        <tr>
                                                            <td><strong>File Name:</strong></td>
                                                            <td>' . htmlspecialchars($file_name) . '</td>
                                                        </tr>
                                                        <tr>
                                                            <td><strong>Uploaded Date:</strong></td>
                                                            <td>' . date('F d, Y') . '</td>
                                                        </tr>
                                                        <tr>
                                                            <td><strong>Partner Name:</strong></td>
                                                            <td>' . htmlspecialchars($partner) . '</td>
                                                        </tr>
                                                        <tr>
                                                            <td><strong>Uploaded By:</strong></td>
                                                            <td>' . htmlspecialchars($_SESSION['admin_name'] ?? $_SESSION['user_name'] ?? 'Unknown') . '</td>
                                                        </tr>
                                                        <tr>
                                                            <td><strong>File Type:</strong></td>
                                                            <td>' . htmlspecialchars($fileType) . '</td>
                                                        </tr>
                                                    </table>
                                                </div>
                                            </div>
                                        `,
                                        showConfirmButton: true,
                                        confirmButtonText: "OK",
                                        confirmButtonColor: "#dc3545",
                                        allowOutsideClick: false,
                                        allowEscapeKey: false
                                    }).then((result) => {
                                        if (result.isConfirmed) {
                                            window.location.href = "../../dashboard/billspayment/import/billspay-transaction.php";
                                        }
                                    });
                                });
                            </script>';
                        } else {
                            // For All partners, redirect to error display page
                            echo '<script>window.location.href = "../error/partnerGLCodesErrorDisplay.php";</script>';
                        }
                    } elseif (!empty($notFoundData)) {
                        error_log("Redirecting to branch ID error page");
                        echo '<script>window.location.href = "../error/branchIdErrorDisplay.php";</script>'; // DONE
                    } elseif (!empty($regionNotFoundData)) {
                        error_log("Redirecting to region error page");
                        // Redirect to region error display
                        echo '<script>window.location.href = "../error/regionNotFoundErrorDisplay.php";</script>';
                    } elseif (!empty($_SESSION['duplicate_data'])) {
                        error_log("Redirecting to duplicate error page");
                        // Redirect to duplicate error display
                        echo '<script>window.location.href = "../error/duplicateErrorDisplay.php";</script>';
                    } elseif (!empty($_SESSION['ready_to_override_data'])) {
                        error_log("Showing override confirmation");
                        // Show override confirmation page
                        echo '<script>
                            document.addEventListener("DOMContentLoaded", function() {
                                // Create a form to trigger the override confirmation
                                const form = document.createElement("form");
                                form.method = "POST";
                                form.style.display = "none";
                                
                                const input = document.createElement("input");
                                input.type = "hidden";
                                input.name = "override_comfirm";
                                input.value = "1";
                                
                                form.appendChild(input);
                                document.body.appendChild(form);
                                form.submit();
                            });
                        </script>';
                    } elseif (!empty($matchedData)) {
                        error_log("Showing summary display with " . count($matchedData) . " records");
                        // Calculate all summaries
                        $summaries = calculateTransactionSummary($matchedData, $cancellationData);

                        // Get display variables
                        $displayData = [
                            'company' => htmlspecialchars($partnerSelection[0]['companys_name']),
                            // 'company' => htmlspecialchars($_POST['company'] ?? ''),
                            // 'partnerId' => htmlspecialchars($partners_id ?? ''),
                            'partnerId' => htmlspecialchars($partnerSelection[0]['partners_id']),
                            'partnerIdKPX' => htmlspecialchars($partnerSelection[0]['partners_id_kpx']),
                            'GLCodes' => htmlspecialchars($partnerSelection[0]['gl_code']),
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
                                                                <td><i class="fas fa-id-card text-primary me-2"></i>KP7 Partner ID</td>
                                                                <td class="fw-semibold">' . $displayData['partnerId'] . '</td>
                                                            </tr>
                                                            <tr>
                                                                <td><i class="fas fa-id-card text-primary me-2"></i>KPX Partner ID</td>
                                                                <td class="fw-semibold">' . $displayData['partnerIdKPX'] . '</td>
                                                            </tr>
                                                            <tr>
                                                                <td><i class="fas fa-id-card text-primary me-2"></i>GL Code</td>
                                                                <td class="fw-semibold">' . $displayData['GLCodes'] . '</td>
                                                            </tr>
                                                            <tr>
                                                                <td><i class="fas fa-building text-primary me-2"></i>Partner Name</td>
                                                                <td class="fw-semibold">'?>
                                                                <?php 
                                                                    if($partner !== 'All') {
                                                                        echo $displayData['company'];
                                                                    } else {
                                                                        echo 'Multiple Partners';
                                                                    }
                                                                ?>
                                                                <?php echo '</td>
                                                            </tr>
                                                            <tr>
                                                                <td><i class="fas fa-list-ol text-primary me-2"></i>No. of Data Rows Uploaded</td>
                                                                <td class="fw-semibold">' . $displayData['rowCount'] . '</td>
                                                            </tr>
                                                            <tr>
                                                                <td><i class="fas fa-file-import text-primary me-2"></i>Source</td>
                                                                <td class="fw-semibold">' . $displayData['sourceType'] . '</td>
                                                            </tr>
                                                            <tr>
                                                                <td><i class="fas fa-calendar-alt text-primary me-2"></i>Uploaded Date</td>
                                                                <td class="fw-semibold">' . $displayData['transactionDate'] . '</td>
                                                            </tr>
                                                            <tr>
                                                                <td><i class="fas fa-calendar-alt text-primary me-2"></i>Uploaded By</td>
                                                                <td class="fw-semibold">'.($_SESSION['admin_name'] ?? $_SESSION['user_name']).'</td>
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
                                                            <th class="text-center" style="width: 33%">CANCELLED TRANSACTIONS</th>
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

                        echo '                      </tbody>
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
                        // echo '                  <tr class="table-secondary">
                        //                                     <td class="text-start fw-bold fs-5"><i class="fas fa-coins text-warning me-2"></i>TOTAL AMOUNT (PHP)</td>
                        //                                     <td class="text-end fw-bold fs-5"></td>
                        //                                     <td class="text-end fw-bold fs-5">' . formatCurrency($summaries['net']['settlement']) . '</td>
                        //                                 </tr>
                        //                             </tbody>
                        //                         </table>
                        //                     </div>
                        //                 </div>
                        //             </div>
                        //         </div>
                        //     </div>
                        // </div>
                        // <script>
                        //     // Hide the confirmation/summary section after clicking Confirm Import
                        //     document.addEventListener("DOMContentLoaded", function() {
                        //         var confirmForm = document.getElementById("confirmImportForm");
                        //         if (confirmForm) {
                        //             confirmForm.addEventListener("submit", function() {
                        //                 var uploadSuccess = document.getElementById("upload-success");
                        //                 if (uploadSuccess) {
                        //                     uploadSuccess.style.display = "none";
                        //                 }
                        //             });
                        //         }
                        //     });
                        // </script>';
                    } else {
                        error_log("No data found to process");
                        echo '<script>
                            Swal.fire({
                                icon: "warning",
                                title: "No Data Found",
                                text: "No valid data was found in the uploaded file.",
                                confirmButtonText: "OK"
                            }).then(() => {
                                window.location.href = "../../dashboard/billspayment/import/billspay-transaction.php";
                            });
                        </script>';
                        exit;
                    }
                }else{
                    echo '<script>
                            Swal.fire({
                                icon: "error",
                                title: "Invalid File Type",
                                text: "Please upload a valid Excel file.",
                                confirmButtonText: "OK"
                            }).then(() => {
                                window.location.href = "../../dashboard/billspayment/import/billspay-transaction.php";
                            });
                        </script>';
                }
            } else {?>
                <script>
                    Swal.fire({
                        icon: "error",
                        title: "Invalid File Type",
                        text: "Please upload a valid Excel file.",
                        confirmButtonText: "OK"
                    }).then(() => {
                        window.location.href = "../../dashboard/billspayment/import/billspay-transaction.php";
                    });
                </script>
    <?php 
            }
        }?>
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
                        window.location.href = '../../dashboard/billspayment/import/billspay-transaction.php';
                    }
                });
            }
        </script>
    </body>
</html>