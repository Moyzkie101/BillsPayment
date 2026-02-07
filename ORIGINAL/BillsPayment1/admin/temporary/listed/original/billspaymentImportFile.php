<?php

   session_start();
   include '../config/config.php';

   if (!isset($_SESSION['admin_name'])) {
      header('location:../login_form.php');
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
    <div class="">
        <div class="top-content">
            <div class="usernav">
                <h4><?php echo $_SESSION['admin_name'] ?></h4>
                <h5 style="margin-left:5px;"><?php echo " - ".$_SESSION['admin_email']."" ?></h5>
            </div>
            <div class="btn-nav">
               <ul class="nav-list">
                  <li><a href="admin_page.php">HOME</a></li>
                  <li class="dropdown">
                        <button class="dropdown-btn">Import File</button>
                        <div class="dropdown-content">
                        <a id="user" href="billspaymentImportFile.php">BILLSPAYMENT TRANSACTION</a>
                        </div>
                  </li>
                  <li class="dropdown">
                        <button class="dropdown-btn">Transaction</button>
                        <div class="dropdown-content">
                        <a id="user" href="billspaymentSettlement.php">SETTLEMENT</a>
                        </div>
                  </li>
                  <li class="dropdown">
                     <button class="dropdown-btn">Report</button>
                     <div class="dropdown-content">
                        <a id="user" href="billspaymentReport.php">BILLS PAYMENT</a>
                        <a id="user" href="dailyVolume.php">DAILY VOLUME</a>

                     </div>
                  </li>
                  <li class="dropdown">
                        <button class="dropdown-btn">MAINTENANCE</button>
                        <div class="dropdown-content">
                        <a id="user" href="userLog.php">USER</a>
                        <a id="user" href="partnerLog.php">PARTNER</a>
                        <a id="user" href="natureOfBusinessLog.php">NATURE OF BUSINESS</a>
                        <a id="user" href="bankLog.php">BANK</a>
                        </div>
                  </li>
                  <li><a href="../logout.php">LOGOUT</a></li>
               </ul>
            </div>
        </div>
    </div>

    <div id="loading-overlay">
        <div class="loading-spinner"></div>
    </div>
        <div class="container-fluid border border-danger rounded mt-3"> <!-- import-file css removed -->
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
                                <div class="col-md-3 mb-3">
                                    <div class="d-flex align-items-center">
                                        <label for="datePicker" class="form-label me-2 mb-0">Select Date:</label>
                                         <input type="date" id="datePicker" name="datePicker" class="form-control" required>
                                    </div>
                                </div>

                                    <!-- File Upload Form -->
                                <div class="col-md-6 mb-3 d-flex">
                                        <input type="file" name="excelFile" accept=".xls,.xlsx" class="form-control me-2" required />
                                        <input type="submit" class="btn btn-danger" name="upload" value="Proceed">
                                </div>
                        </div>
                </form>
            </div>
        </div>

        <?php
            include '../config/config.php';
            require '../vendor/autoload.php';
            
            use PhpOffice\PhpSpreadsheet\IOFactory;
            use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
            
            // Improved date conversion function to handle more formats
            if (!function_exists('convertToMySQLDateTime')) {
                function convertToMySQLDateTime($dateTimeStr) {
                    // Try the primary expected format first
                    $dateTime = DateTime::createFromFormat('Y-m-d g:i:s A', $dateTimeStr);
                    
                    // If primary format fails, try alternative formats
                    if ($dateTime === false) {
                        $possibleFormats = [
                            'Y-m-d H:i:s',
                            'm/d/Y g:i:s A',
                            'm/d/Y H:i:s',
                            'd-m-Y g:i:s A',
                            'Y/m/d g:i:s A',
                            'Y/m/d H:i:s'
                        ];
                        
                        foreach ($possibleFormats as $format) {
                            $dateTime = DateTime::createFromFormat($format, $dateTimeStr);
                            if ($dateTime !== false) {
                                break; // Exit loop if successful
                            }
                        }
                    }
                    
                    // If still not successful, log error
                    if ($dateTime === false) {
                        error_log('Invalid date format: ' . htmlspecialchars($dateTimeStr));
                        return null;
                    }
                    
                    return $dateTime->format('Y-m-d H:i:s');
                }
            }
            
            function insertData($spreadsheet, $conn) {
                $allDataValid = true;
                $referenceNumbers = []; // Array to track reference numbers in Excel file
                $duplicateRefs = []; // Array to store duplicate reference numbers
                $rowsProcessed = 0; // Counter for rows processed
                $preparedData = []; // Array to store prepared data
                
                // Get the source file type and partner from the form submission
                $sourceFileType = isset($_POST['fileType']) ? $_POST['fileType'] : '';
                $selectedPartner = isset($_POST['company']) ? $_POST['company'] : '';
                
                // If a specific partner (not "All") is selected, get its ID from the database
                $partnerIdFromDB = null;
                $partnerNameFromDB = null;
                if (!empty($selectedPartner) && $selectedPartner !== 'All') {
                    $sql = "SELECT partner_id, partner_name FROM masterdata.partner_masterfile WHERE partner_name = '" . 
                        $conn->real_escape_string($selectedPartner) . "' LIMIT 1";
                    $result = $conn->query($sql);
                    if ($result && $result->num_rows > 0) {
                        $partnerData = $result->fetch_assoc();
                        $partnerIdFromDB = $partnerData['partner_id'];
                        $partnerNameFromDB = $partnerData['partner_name'];

                        // Store the selected partner in the session for later use
                        $_SESSION['selected_partner_id'] = $partnerIdFromDB;
                        $_SESSION['selected_partner_name'] = $partnerNameFromDB;

                        error_log("Partner data stored in session: ID = $partnerIdFromDB, Name = $partnerNameFromDB");
                    } else {
                        error_log("Warning: Partner not found in database for name: " . htmlspecialchars($selectedPartner));
                    }
                }

                // First pass: Collect all non-asterisk dates to find the most common date
                $nonAsteriskDates = [];
                // Track the selected date match status specifically for KP7 validation
                $selectedDate = isset($_POST['datePicker']) ? date('Y-m-d', strtotime($_POST['datePicker'])) : null;
                $selectedDateFound = false;
                
                foreach ($spreadsheet->getWorksheetIterator() as $worksheet) {
                    $highestRow = $worksheet->getHighestRow();
                    for ($row = 10; $row <= $highestRow; ++$row) {
                        $rawStatus = strval($worksheet->getCell('A' . $row)->getValue());
                        $hasAsterisk = strpos($rawStatus, '*') !== false;
                        
                        if (!$hasAsterisk) {
                            $dateColumn = $sourceFileType === 'KPX' ? 'B' : 'C';
                            $excelDateTime = $worksheet->getCell($dateColumn . $row)->getValue();
                            if (!empty($excelDateTime)) {
                                $mysqlDateTime = convertToMySQLDateTime($excelDateTime);
                                if ($mysqlDateTime) {
                                    $dateOnly = date('Y-m-d', strtotime($mysqlDateTime));
                                    $nonAsteriskDates[] = $dateOnly;
                                    
                                    // Check if this date matches the selected date (especially important for KP7)
                                    if ($selectedDate && $dateOnly === $selectedDate) {
                                        $selectedDateFound = true;
                                    }
                                }
                            }
                        }
                    }
                }
                
                // Validate that the selected date exists for KP7 files
                if ($sourceFileType === 'KP7' && $selectedDate && !$selectedDateFound) {
                    error_log("KP7 validation failed: Selected date $selectedDate not found in transaction dates");
                    throw new Exception("The selected date does not match any transaction dates. Please check the date of the file.");
                }
                
                // Find the most common date
                $mostCommonDate = null;
                if (!empty($nonAsteriskDates)) {
                    $dateFrequency = array_count_values($nonAsteriskDates);
                    arsort($dateFrequency); // Sort by frequency (highest first)
                    $mostCommonDate = key($dateFrequency); // Get the most frequent date
                }
                
                // Second pass: Process each row for data preparation
                foreach ($spreadsheet->getWorksheetIterator() as $worksheet) {
                    $sheetName = $worksheet->getTitle();
                    $highestRow = $worksheet->getHighestRow();
                    
                    for ($row = 10; $row <= $highestRow; ++$row) {
                        // Choose which cells to check based on file type
                        if ($sourceFileType === 'KPX') {
                            // Get the status and check for asterisk
                            $rawStatus = strval($worksheet->getCell('A' . $row)->getValue());
                            $status = (strpos($rawStatus, '*') !== false) ? '*' : ''; // Only store * if present, otherwise empty
                            $excelDateTime = $worksheet->getCell('B' . $row)->getValue();
                            $mysqlDateTime = convertToMySQLDateTime($excelDateTime);
                            
                            // Skip row if datetime is empty or invalid
                            if (empty($mysqlDateTime)) {
                                error_log("Skipping row $row: Empty or invalid datetime value");
                                continue;
                            }
                            
                            // Check if status contains an asterisk (*) to determine cancellation_date
                            $cancellationDate = "NULL"; // Default is NULL
                            if ($status === '*' && $mostCommonDate) {
                                // Use the most common date from non-asterisk rows
                                $cancellationDate = "'" . $conn->real_escape_string($mostCommonDate) . "'";
                            }
                            
                            // Map KPX columns according to specifications
                            $column1 = $conn->real_escape_string($status); // Now only contains * or empty string
                            $column2 = $conn->real_escape_string($mysqlDateTime);
                            $column3 = $conn->real_escape_string(strval($worksheet->getCell('C' . $row)->getValue())); // control_no
                            
                            // Extract branch_id from control_no (numeric part before dash)
                            $control_no = strval($worksheet->getCell('C' . $row)->getValue());

                            $prefix = substr($control_no, 0, 3);
                            $prefix1 = $conn->real_escape_string($prefix);
                            $main_branch = intval(substr($control_no, 3, 3));

                            $numeric_branch_id = '';

                            $valid_prefix = in_array($prefix, ['"'.$prefix1.'"']);
                            if($valid_prefix==='BPX'){
                                $numeric_branch_id = $main_branch; // Get the 001 after prefix
                            }else{
                                for ($i = 0; $i < strlen($control_no); $i++) {
                                    if ($control_no[$i] === '-') {
                                        break;
                                    }
                                    if (is_numeric($control_no[$i])) {
                                        $numeric_branch_id .= $control_no[$i];
                                    }
                                }
                            }
                            
                            // Look up branch information in branch_profile table
                            $branch_id = '';
                            $region_code = '';
                            // Initialize array to collect branch ID errors if not already defined
                            if(!isset($missingBranchIds)) {
                                $missingBranchIds = [];
                            }
                            
                            if (!empty($numeric_branch_id)) {
                                $branch_query = "SELECT branch_id, region_code FROM masterdata.branch_profile WHERE branch_id = '" . 
                                    $conn->real_escape_string($numeric_branch_id) . "' LIMIT 1";
                                $branch_result = $conn->query($branch_query);
                                if ($branch_result && $branch_result->num_rows > 0) {
                                    $branch_data = $branch_result->fetch_assoc();
                                    $branch_id = $branch_data['branch_id'];
                                    $region_code = $branch_data['region_code'];
                                    } else {
                                    // Get outlet and region for confidentiality instead of showing branch_id and control_no
                                    $outlet = strval($worksheet->getCell('N' . $row)->getValue());
                                    $region = strval($worksheet->getCell('O' . $row)->getValue());
                                    
                                    // Instead of stopping, collect the error with outlet and region for confidentiality
                                    $missingBranchIds[] = [
                                        'outlet' => $outlet,
                                        'region' => $region,
                                        'row' => $row
                                    ];
                                    // Use empty value for now and continue processing
                                    $branch_id = '';
                                    $region_code = '';
                                }
                            }
                            
                            $column4 = $conn->real_escape_string(strval($worksheet->getCell('D' . $row)->getValue())); // reference_no
                            $column5 = substr($conn->real_escape_string(trim(strval($worksheet->getCell('E' . $row)->getValue()))), 0, 150); // payor
                            $column6 = $conn->real_escape_string(strval($worksheet->getCell('F' . $row)->getValue())); // address
                            $column7 = $conn->real_escape_string(strval($worksheet->getCell('G' . $row)->getValue())); // account_no
                            $column8 = $conn->real_escape_string(strval($worksheet->getCell('H' . $row)->getValue())); // account_name
                            $column9 = $conn->real_escape_string(floatval(str_replace(',', '', $worksheet->getCell('I' . $row)->getValue()))); // amount_paid
                            $column10 = $conn->real_escape_string(floatval($worksheet->getCell('K' . $row)->getValue())); // charge_to_partner from cell K
                            $column11 = $conn->real_escape_string(floatval($worksheet->getCell('J' . $row)->getValue())); // charge_to_customer from cell J
                            $column12 = $conn->real_escape_string(strval($worksheet->getCell('L' . $row)->getValue())); // contact_no
                            $column13 = $conn->real_escape_string(strval($worksheet->getCell('M' . $row)->getValue())); // other_details
                            
                            // Detect if this is a new KPX format by checking if control_no matches branch_id in cell N
                            $potential_branch_id = strval($worksheet->getCell('N' . $row)->getValue());
                            $control_no = strval($worksheet->getCell('C' . $row)->getValue());
                            $is_new_kpx_format = false;
                            
                            // Extract numeric part from control_no for comparison
                            $numeric_control_no = '';
                            for ($i = 0; $i < strlen($control_no); $i++) {
                                if ($control_no[$i] === '-') {
                                    break;
                                }
                                if (is_numeric($control_no[$i])) {
                                    $numeric_control_no .= $control_no[$i];
                                }
                            }
                            
                           // New validation logic based on cell N content
                            if (empty($potential_branch_id)) {
                                // Initialize array to collect empty cell errors if not already defined
                                if(!isset($emptyCellErrors)) {
                                    $emptyCellErrors = [];
                                }
                                
                                // Get outlet and region for confidentiality instead of showing branch_id and control_no
                                $outlet = strval($worksheet->getCell('O' . $row)->getValue());
                                $region = strval($worksheet->getCell('Q' . $row)->getValue()); // Changed from 'P' to 'Q' for new KPX format
                                
                                // Collect the error with outlet and region for confidentiality
                                $emptyCellErrors[] = [
                                    'outlet' => $outlet,
                                    'region' => $region,
                                    'row' => $row
                                ];
                            } else if (is_numeric($potential_branch_id)) {
                                // Cell contains a number
                                if ($potential_branch_id === $numeric_control_no) {
                                    $is_new_kpx_format = true;
                                } else {
                                    // Initialize array to collect branch ID mismatch errors if not already defined
                                    if(!isset($branchIdMismatches)) {
                                        $branchIdMismatches = [];
                                    }
                                    
                                    // Get outlet and region for confidentiality instead of showing branch_id and control_no
                                    $outlet = strval($worksheet->getCell('O' . $row)->getValue());
                                    $region = strval($worksheet->getCell('Q' . $row)->getValue()); // Changed from 'P' to 'Q' for new KPX format
                                    
                                    // Collect the error with outlet and region for confidentiality
                                    $branchIdMismatches[] = [
                                        'outlet' => $outlet,
                                        'region' => $region,
                                        'row' => $row,
                                        'found' => $potential_branch_id,
                                        'expected' => $numeric_control_no
                                    ];
                                    
                                    // Set format to false for consistent handling
                                    $is_new_kpx_format = false;
                                }
                            } else {
                                // Cell contains letters or words - use old KPX format
                                $is_new_kpx_format = false;
                            }
                            
                            if ($is_new_kpx_format) {
                                // New KPX format mapping
                                $branch_id_cell = $potential_branch_id;
                                
                                // Validate branch_id is not empty
                                if (empty($branch_id_cell)) {
                                    echo "<script>
                                        Swal.fire({
                                            title: 'Empty Branch ID',
                                            text: 'Empty branch ID found in cell N{$row}. Cannot proceed with import.',
                                            icon: 'error',
                                            confirmButtonText: 'OK',
                                            allowOutsideClick: false,
                                            allowEscapeKey: false
                                        }).then((result) => {
                                            if (result.isConfirmed) {
                                                window.location.href='billspaymentImportFile.php';
                                            }
                                        });
                                    </script>";
                                    exit;
                                }
                                
                                // Look up region_code from database using branch_id from Cell N
                                $region_code = '';
                                $branch_query = "SELECT region_code FROM masterdata.branch_profile WHERE branch_id = '" . 
                                    $conn->real_escape_string($branch_id_cell) . "' LIMIT 1";
                                $branch_result = $conn->query($branch_query);
                                if ($branch_result && $branch_result->num_rows > 0) {
                                    $branch_data = $branch_result->fetch_assoc();
                                    $region_code = $branch_data['region_code'];
                                } else {
                                    // Branch ID not found in database - show error and stop processing
                                    echo "<script>
                                        Swal.fire({
                                            title: 'Branch ID Not Found',
                                            text: 'Branch ID {$branch_id_cell} from cell N{$row} is not found in the branch profile database.',
                                            icon: 'error',
                                            confirmButtonText: 'OK',
                                            allowOutsideClick: false,
                                            allowEscapeKey: false
                                        }).then((result) => {
                                            if (result.isConfirmed) {
                                                window.location.href='billspaymentImportFile.php';
                                            }
                                        });
                                    </script>";
                                    exit;
                                }
                                
                                // Map other columns according to new KPX format
                                $column14 = $conn->real_escape_string(strval($worksheet->getCell('O' . $row)->getValue())); // outlet
                                $column15 = $conn->real_escape_string(strval($worksheet->getCell('Q' . $row)->getValue())); // region
                                $column16 = $conn->real_escape_string(strval($worksheet->getCell('R' . $row)->getValue())); // operator
                                
                                // Partner data remains the same logic, just shifted columns
                                if (isset($_SESSION['selected_partner_id']) && isset($_SESSION['selected_partner_name']) && 
                                    !empty($_SESSION['selected_partner_id']) && !empty($_SESSION['selected_partner_name'])) {
                                    $column17 = $conn->real_escape_string($_SESSION['selected_partner_name']); // partner_name
                                    $column18 = $conn->real_escape_string($_SESSION['selected_partner_id']); // partner_id
                                } elseif ($partnerNameFromDB && $partnerIdFromDB) {
                                    $column17 = $conn->real_escape_string($partnerNameFromDB); // partner_name
                                    $column18 = $conn->real_escape_string($partnerIdFromDB); // partner_id
                                } else {
                                    $column17 = $conn->real_escape_string(strval($worksheet->getCell('S' . $row)->getValue())); // partner_name
                                    $column18 = $conn->real_escape_string(strval($worksheet->getCell('T' . $row)->getValue())); // partner_id
                                    
                                    // If we have a name but no ID, try to look up the ID
                                    if (!empty($column17) && empty($column18)) {
                                        $lookupQuery = "SELECT partner_id FROM masterdata.partner_masterfile WHERE partner_name = '" . 
                                            $conn->real_escape_string($column17) . "' LIMIT 1";
                                        $lookupResult = $conn->query($lookupQuery);
                                        if ($lookupResult && $lookupResult->num_rows > 0) {
                                            $lookupData = $lookupResult->fetch_assoc();
                                            $column18 = $conn->real_escape_string($lookupData['partner_id']);
                                        }
                                    }
                                }
                            } else {
                                // Original KPX format mapping
                                $column14 = $conn->real_escape_string(strval($worksheet->getCell('N' . $row)->getValue())); // outlet
                                $column15 = $conn->real_escape_string(strval($worksheet->getCell('O' . $row)->getValue())); // region
                                $column16 = $conn->real_escape_string(strval($worksheet->getCell('P' . $row)->getValue())); // operator
                                
                                // Enhanced partner data handling - first check session, then DB, then Excel
                                if (isset($_SESSION['selected_partner_id']) && isset($_SESSION['selected_partner_name']) && 
                                    !empty($_SESSION['selected_partner_id']) && !empty($_SESSION['selected_partner_name'])) {
                                    // Use session data (most reliable for specific partner selection)
                                    $column17 = $conn->real_escape_string($_SESSION['selected_partner_name']); // partner_name
                                    $column18 = $conn->real_escape_string($_SESSION['selected_partner_id']); // partner_id
                                } elseif ($partnerNameFromDB && $partnerIdFromDB) {
                                    // Use DB data from earlier query
                                    $column17 = $conn->real_escape_string($partnerNameFromDB); // partner_name
                                    $column18 = $conn->real_escape_string($partnerIdFromDB); // partner_id
                                } else {
                                    // Fall back to Excel data
                                    $column17 = $conn->real_escape_string(strval($worksheet->getCell('Q' . $row)->getValue())); // partner_name
                                    $column18 = $conn->real_escape_string(strval($worksheet->getCell('R' . $row)->getValue())); // partner_id
                                    
                                    // If we have a name but no ID, try to look up the ID
                                    if (!empty($column17) && empty($column18)) {
                                        $lookupQuery = "SELECT partner_id FROM masterdata.partner_masterfile WHERE partner_name = '" . 
                                            $conn->real_escape_string($column17) . "' LIMIT 1";
                                        $lookupResult = $conn->query($lookupQuery);
                                        if ($lookupResult && $lookupResult->num_rows > 0) {
                                            $lookupData = $lookupResult->fetch_assoc();
                                            $column18 = $conn->real_escape_string($lookupData['partner_id']);
                                        }
                                    }
                                }
                            }
                        } else {
                            // Get the status and check for asterisk
                            $rawStatus = strval($worksheet->getCell('A' . $row)->getValue());
                            $status = (strpos($rawStatus, '*') !== false) ? '*' : ''; // Only store * if present, otherwise empty
                            $excelDateTime = $worksheet->getCell('C' . $row)->getValue();
                            $mysqlDateTime = convertToMySQLDateTime($excelDateTime);
                            
                            // Skip row if datetime is empty or invalid
                            if (empty($mysqlDateTime)) {
                                error_log("Skipping row $row: Empty or invalid datetime value");
                                continue;
                            }
                            
                            // Check if status contains an asterisk (*) to determine cancellation_date
                            $cancellationDate = "NULL"; // Default is NULL
                            if ($status === '*' && $mostCommonDate) {
                                // Use the most common date from non-asterisk rows
                                $cancellationDate = "'" . $conn->real_escape_string($mostCommonDate) . "'";
                            }
                            
                            $column1 = $conn->real_escape_string($status); // Now only contains * or empty string
                            $column2 = $conn->real_escape_string($mysqlDateTime);
                            $column3 = $conn->real_escape_string(strval($worksheet->getCell('D' . $row)->getValue()));
                            $column4 = $conn->real_escape_string(strval($worksheet->getCell('E' . $row)->getValue()));
                            $column5 = substr($conn->real_escape_string(trim(strval($worksheet->getCell('F' . $row)->getValue()))), 0, 150);
                            $column6 = $conn->real_escape_string(strval($worksheet->getCell('G' . $row)->getValue()));
                            $column7 = $conn->real_escape_string(strval($worksheet->getCell('H' . $row)->getValue()));
                            $column8 = $conn->real_escape_string(strval($worksheet->getCell('I' . $row)->getValue()));
                            $column9 = $conn->real_escape_string(floatval(str_replace(',', '', $worksheet->getCell('J' . $row)->getValue())));
                            $column10 = $conn->real_escape_string(floatval($worksheet->getCell('K' . $row)->getValue())); //charge to partner
                            $column11 = $conn->real_escape_string(floatval($worksheet->getCell('L' . $row)->getValue())); // charge to customer
                            $column12 = $conn->real_escape_string(strval($worksheet->getCell('M' . $row)->getValue()));
                            $column13 = $conn->real_escape_string(strval($worksheet->getCell('N' . $row)->getValue()));
                            $column14 = $conn->real_escape_string(strval($worksheet->getCell('O' . $row)->getValue()));
                            $column15 = $conn->real_escape_string(strval($worksheet->getCell('P' . $row)->getValue()));
                            $column16 = $conn->real_escape_string(strval($worksheet->getCell('Q' . $row)->getValue()));
                            
                            // When handling KP7 files with "All" partners, bypass all partner validation
                            if ($sourceFileType === 'KP7' && $selectedPartner === 'All') {
                                // For KP7 "All" mode: Just use the Excel data directly without validation
                                $column17 = $conn->real_escape_string(strval($worksheet->getCell('R' . $row)->getValue())); // partner_name from Excel
                                $column18 = $conn->real_escape_string(strval($worksheet->getCell('S' . $row)->getValue())); // partner_id from Excel
                                
                                // No lookups or validation - use data as-is
                            } else if ($sourceFileType === 'KPX') {
                                // Keep existing partner data handling for KPX files
                                if ($partnerNameFromDB && $partnerIdFromDB) {
                                    $column17 = $conn->real_escape_string($partnerNameFromDB); // partner_name
                                    $column18 = $conn->real_escape_string($partnerIdFromDB); // partner_id
                                } else {
                                    $column17 = $conn->real_escape_string(strval($worksheet->getCell('R' . $row)->getValue())); // partner_name from Excel
                                    $column18 = $conn->real_escape_string(strval($worksheet->getCell('S' . $row)->getValue())); // partner_id from Excel
                                }
                            } else {
                                // Handle KP7 files with individual partner selected
                                if ($partnerNameFromDB && $partnerIdFromDB) {
                                    $column17 = $conn->real_escape_string($partnerNameFromDB); // partner_name
                                    $column18 = $conn->real_escape_string($partnerIdFromDB); // partner_id
                                } else {
                                    // Fallback to Excel data if DB lookup failed
                                    $column17 = $conn->real_escape_string(strval($worksheet->getCell('R' . $row)->getValue())); // partner_name from Excel
                                    $column18 = $conn->real_escape_string(strval($worksheet->getCell('S' . $row)->getValue())); // partner_id from Excel
                                }
                            }
                        }
                        
                        $settle_unsettle = "UNSETTLED";
                        $claim_unclaim = "UNCLAIM";
                        $imported_by = $conn->real_escape_string($_SESSION['admin_name']);
                        date_default_timezone_set('Asia/Manila');
                        $imported_date = date('Y-m-d H:i:s');
                        
                        // Prepare data array instead of direct insertion
                        $preparedData[] = [
                            'status' => $column1,
                            'datetime' => $column2,
                            'source_file' => $sourceFileType,
                            'cancellation_date' => $cancellationDate,
                            'control_no' => $column3,
                            'reference_no' => $column4,
                            'payor' => $column5,
                            'address' => $column6,
                            'account_no' => $column7,
                            'account_name' => $column8,
                            'amount_paid' => $column9,
                            'charge_to_partner' => $column10,
                            'charge_to_customer' => $column11,
                            'contact_no' => $column12,
                            'other_details' => $column13,
                            'branch_id' => $branch_id, // Add branch_id from lookup
                            'outlet' => $column14,
                            'region' => $column15,
                            'region_code' => $region_code, // Add region_code from lookup
                            'operator' => $column16,
                            'partner_name' => $column17,
                            'partner_id' => $column18,
                            'settle_unsettle' => $settle_unsettle,
                            'claim_unclaim' => $claim_unclaim,
                            'imported_by' => $imported_by,
                            'imported_date' => $imported_date
                        ];
                        
                        // Determine the reference number column based on file type
                        if ($sourceFileType === 'KPX') {
                            $referenceNo = strval($worksheet->getCell('D' . $row)->getValue());
                        } else {
                            $referenceNo = strval($worksheet->getCell('E' . $row)->getValue());
                        }

                        // Duplicate reference number check
                        if (!empty($referenceNo)) {
                            // Initialize array to collect duplicate reference numbers if not already defined
                            if(!isset($duplicateReferenceNumbers)) {
                                $duplicateReferenceNumbers = [];
                            }
                            
                            if (isset($referenceNumbers[$referenceNo])) {
                                // Collect duplicate reference number
                                $duplicateReferenceNumbers[] = [
                                    'reference_no' => $referenceNo,
                                    'row' => $row,
                                    'payor' => $sourceFileType === 'KPX' ? strval($worksheet->getCell('E' . $row)->getValue()) : strval($worksheet->getCell('F' . $row)->getValue()),
                                    'amount' => $sourceFileType === 'KPX' ? floatval(str_replace(',', '', $worksheet->getCell('I' . $row)->getValue())) : floatval(str_replace(',', '', $worksheet->getCell('J' . $row)->getValue()))
                                ];
                            }
                            $referenceNumbers[$referenceNo] = true;
                        }

                        $rowsProcessed++;
                    }
                }
                
                // Check if any duplicate reference numbers were found
                if (!empty($duplicateReferenceNumbers)) {
                    // Store the duplicate references in the session
                    $_SESSION['duplicate_references'] = $duplicateReferenceNumbers;
                    $_SESSION['original_file_name'] = isset($_FILES['excelFile']['name']) ? $_FILES['excelFile']['name'] : "Unknown file";
                    $_SESSION['source_file_type'] = isset($_POST['fileType']) ? $_POST['fileType'] : 'Unknown';
                    $_SESSION['transactionDate'] = isset($_POST['datePicker']) ? $_POST['datePicker'] : date('Y-m-d');
                    
                    // Redirect to the error display page
                    echo "<script>
                        window.location.href='referenceNumberErrorDisplay.php';
                    </script>";
                    exit;
                }
                
                // Check if any branch IDs were not found
                if (!empty($missingBranchIds)) {
                    // Store the missing branch IDs in the session
                    $_SESSION['missing_branch_ids'] = $missingBranchIds;
                    $_SESSION['original_file_name'] = isset($_FILES['excelFile']['name']) ? $_FILES['excelFile']['name'] : "Unknown file";
                    $_SESSION['source_file_type'] = isset($_POST['fileType']) ? $_POST['fileType'] : 'Unknown';
                    $_SESSION['transactionDate'] = isset($_POST['datePicker']) ? $_POST['datePicker'] : date('Y-m-d');
                    
                    // Redirect to the error display page
                    echo "<script>
                        window.location.href='branchIdErrorDisplay.php';
                    </script>";
                    exit;
                }
                
                // Check if any empty cells were found
                if (!empty($emptyCellErrors)) {
                    // Store the empty cell errors in the session
                    $_SESSION['empty_cell_errors'] = $emptyCellErrors;
                    $_SESSION['original_file_name'] = isset($_FILES['excelFile']['name']) ? $_FILES['excelFile']['name'] : "Unknown file";
                    $_SESSION['source_file_type'] = isset($_POST['fileType']) ? $_POST['fileType'] : 'Unknown';
                    $_SESSION['transactionDate'] = isset($_POST['datePicker']) ? $_POST['datePicker'] : date('Y-m-d');
                    
                    // Redirect to the empty cell error display page
                    echo "<script>
                        window.location.href='emptyCellErrorDisplay.php';
                    </script>";
                    exit;
                }

                if (!empty($branchIdMismatches)) {
                    // Store the branch ID mismatches in the session
                    $_SESSION['branch_id_mismatches'] = $branchIdMismatches;
                    $_SESSION['original_file_name'] = isset($_FILES['excelFile']['name']) ? $_FILES['excelFile']['name'] : "Unknown file";
                    $_SESSION['source_file_type'] = isset($_POST['fileType']) ? $_POST['fileType'] : 'Unknown';
                    $_SESSION['transactionDate'] = isset($_POST['datePicker']) ? $_POST['datePicker'] : date('Y-m-d');
                    
                    // Redirect to the branch ID mismatch error display page
                    echo "<script>
                        window.location.href='branchIdMismatchErrorDisplay.php';
                    </script>";
                    exit;
                }
                
                return ['success' => $allDataValid, 'rowCount' => $rowsProcessed, 'data' => $preparedData];
            }

            // New function to handle actual database insertion
            function insertDataToDatabase($data, $conn) {
                $rowsInserted = 0;
                $allInsertionsSuccessful = true;
                
                foreach ($data as $row) {
                    $sql = "INSERT INTO mldb.billspayment_transaction (
                        status, datetime, source_file, cancellation_date, control_no, reference_no, 
                        payor, address, account_no, account_name, amount_paid, charge_to_partner, 
                        charge_to_customer, contact_no, other_details, branch_id, outlet, region, region_code, operator, 
                        partner_name, partner_id, settle_unsettle, claim_unclaim, imported_by, imported_date
                    ) VALUES (
                        '{$row['status']}', '{$row['datetime']}', '{$row['source_file']}', {$row['cancellation_date']}, 
                        '{$row['control_no']}', '{$row['reference_no']}', '{$row['payor']}', '{$row['address']}', 
                        '{$row['account_no']}', '{$row['account_name']}', '{$row['amount_paid']}', '{$row['charge_to_partner']}', 
                        '{$row['charge_to_customer']}', '{$row['contact_no']}', '{$row['other_details']}', '{$row['branch_id']}', '{$row['outlet']}', 
                        '{$row['region']}', '{$row['region_code']}', '{$row['operator']}', '{$row['partner_name']}', '{$row['partner_id']}', 
                        '{$row['settle_unsettle']}', '{$row['claim_unclaim']}', '{$row['imported_by']}', '{$row['imported_date']}'
                    )";
                    
                    if ($conn->query($sql)) {
                        $rowsInserted++;
                    } else {
                        $allInsertionsSuccessful = false;
                        error_log('Query failed: ' . htmlspecialchars($conn->error));
                        error_log('Failed SQL: ' . $sql);
                    }
                }
                
                return ['success' => $allInsertionsSuccessful, 'rowCount' => $rowsInserted];
            }

            // Function to calculate transaction summary
            function calculateTransactionSummary($spreadsheet, $sourceFileType) {
                $summary = [
                    'regular' => [
                        'count' => 0,
                        'principal' => 0,
                        'charge_customer' => 0,
                        'charge_partner' => 0
                    ],
                    'adjustments' => [
                        'count' => 0,
                        'principal' => 0,
                        'charge_customer' => 0,
                        'charge_partner' => 0
                    ],
                ];
                
                $totalRowCount = 0;
                
                // Process worksheets in a single pass - count rows and calculate details simultaneously
                foreach ($spreadsheet->getWorksheetIterator() as $worksheet) {
                    $highestRow = $worksheet->getHighestRow();
                    
                    for ($row = 10; $row <= $highestRow; ++$row) {
                        // Check if this is a valid data row
                        $dateColumn = $sourceFileType === 'KPX' ? 'B' : 'C';
                        $dateValue = $worksheet->getCell($dateColumn . $row)->getValue();
                        
                        if (empty($dateValue)) {
                            break; // Stop when we hit empty date cells
                        }
                        
                        $totalRowCount++; // Increment total row counter
                        
                        // Check if this transaction has an asterisk (adjustment/cancellation)
                        $statusColumn = 'A';
                        $status = strval($worksheet->getCell($statusColumn . $row)->getValue());
                        $isAdjustment = (strpos($status, '*') !== false);
                        
                        // Get amount columns based on file type
                        if ($sourceFileType === 'KPX') {
                            $principalColumn = 'I';
                            $chargePartnerColumn = 'K';
                            $chargeCustomerColumn = 'J';
                        } else { // KP7
                            $principalColumn = 'J';
                            $chargePartnerColumn = 'K';
                            $chargeCustomerColumn = 'L';
                        }
                        
                        // Get values and convert to float if needed
                        $principal = floatval(str_replace(',', '', $worksheet->getCell($principalColumn . $row)->getValue()));
                        $chargePartner = floatval(str_replace(',', '', $worksheet->getCell($chargePartnerColumn . $row)->getValue()));
                        $chargeCustomer = floatval(str_replace(',', '', $worksheet->getCell($chargeCustomerColumn . $row)->getValue()));
                        
                        // Add to appropriate summary - use absolute values for adjustments
                        $type = $isAdjustment ? 'adjustments' : 'regular';
                        $summary[$type]['count']++;
                        
                        // For adjustments, use absolute values to remove negative signs
                        if ($isAdjustment) {
                            $summary[$type]['principal'] += abs($principal);
                            $summary[$type]['charge_partner'] += abs($chargePartner);
                            $summary[$type]['charge_customer'] += abs($chargeCustomer);
                        } else {
                            $summary[$type]['principal'] += $principal;
                            $summary[$type]['charge_partner'] += $chargePartner;
                            $summary[$type]['charge_customer'] += $chargeCustomer;
                        }
                    }
                }
                
                $summary['count'] = $totalRowCount; //$summary['count']= $totalRowCount; original
                
                // Calculate net values
                $summary['net'] = [
                    'count' => $totalRowCount - $summary['adjustments']['count'],
                    'principal' => $summary['regular']['principal'] - $summary['adjustments']['principal'],
                    'charge_customer' => $summary['regular']['charge_customer'] - $summary['adjustments']['charge_customer'],
                    'charge_partner' => $summary['regular']['charge_partner'] - $summary['adjustments']['charge_partner']
                ];
                
                // Calculate total charges and settlement amount
                $summary['regular']['total_charge'] = $summary['regular']['charge_customer'] + $summary['regular']['charge_partner'];
                $summary['adjustments']['total_charge'] = $summary['adjustments']['charge_customer'] + $summary['adjustments']['charge_partner'];
                $summary['net']['total_charge'] = $summary['net']['charge_customer'] + $summary['net']['charge_partner'];
                $summary['net']['settlement'] = $summary['net']['principal'] - $summary['net']['total_charge'];
                
                return $summary;
            }

            // Function to format currency values
            function formatCurrency($value) {
                return number_format($value, 2);
            }

            // Initialize select2 for partner dropdown
            echo '<script>
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
            </script>';

            if (isset($_POST['upload'])) {
                $fileTmpPath = $_FILES['excelFile']['tmp_name'];
                $originalFileName = $_FILES['excelFile']['name'];
                $fileExtension = pathinfo($originalFileName, PATHINFO_EXTENSION);
                $selectedCompany = isset($_POST['company']) ? $_POST['company'] : '';
                $selectedDate = isset($_POST['datePicker']) ? date('Y-m-d', strtotime($_POST['datePicker'])) : '';
                $sourceFileType = isset($_POST['fileType']) ? $_POST['fileType'] : '';

                try {
                    $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($fileTmpPath);
                    $worksheet = $spreadsheet->getActiveSheet();
                    
                    // Validate file format matches selected file type
                    $isValidFormat = false;
                    if ($sourceFileType === 'KPX') {
                        // For KPX files, check if column B contains datetime values
                        $row = 10;
                        $dateInColumnB = false;
                        while ($row < 20) { // Check first few rows
                            $bValue = $worksheet->getCell('B' . $row)->getValue();
                            if (!empty($bValue) && preg_match('/\d{4}-\d{2}-\d{2}/', $bValue)) {
                                $dateInColumnB = true;
                                break;
                            }
                            $row++;
                        }
                        $isValidFormat = $dateInColumnB;
                    } else { // KP7
                        // For KP7 files, check if column C contains datetime values and column B is generally empty
                        $row = 10;
                        $dateInColumnC = false;
                        $bHasData = false;
                        $rowsChecked = 0;
                        
                        while ($row < 20 && $rowsChecked < 5) { // Check first few rows with data
                            $bValue = $worksheet->getCell('B' . $row)->getValue();
                            $cValue = $worksheet->getCell('C' . $row)->getValue();
                            
                            if (!empty($cValue)) {
                                $dateInColumnC = true;
                                $rowsChecked++;
                                
                                // Check if B has significant data in these rows
                                if (!empty($bValue)) {
                                    $bHasData = true;
                                }
                            }
                            $row++;
                        }
                        
                        // Valid KP7 format should have dates in column C and mostly empty column B
                        $isValidFormat = ($dateInColumnC && !$bHasData);
                    }
                    
                    if (!$isValidFormat) {
                        echo "<script>
                            Swal.fire({
                                title: 'Invalid File Format',
                                text: 'The uploaded file does not match the selected file type.',
                                icon: 'error',
                                confirmButtonText: 'OK'
                            }).then(() => {
                                    window.location.href='billspaymentImportFile.php';
                            });
                        </script>";
                        exit;
                    }
                    
                    // After validating format, continue with date checks
                    if ($selectedCompany === 'All') {
                        $partnersFound = [];
                        $row = 10;
                        
                        // Only validate partner IDs for KPX files, skip validation for KP7
                        if ($sourceFileType === 'KPX') {
                            $hasPartnerId = false;
                            // Keep existing partner validation for KPX files
                            while (true) {
                                $partnerId = $worksheet->getCell('S' . $row)->getValue();
                                $partnerName = $worksheet->getCell('R' . $row)->getValue();

                                if (empty($partnerId) && empty($partnerName)) {
                                    break;
                                }

                                if (!empty($partnerId)) {
                                    $hasPartnerId = true;
                                    if (!empty($partnerName) && !in_array($partnerName, $partnersFound)) {
                                        $partnersFound[] = $partnerName;
                                    }
                                }

                                $row++;
                            }

                            if (!$hasPartnerId) {
                                echo "<script>
                                    Swal.fire({
                                        title: 'Partner ID Missing',
                                        text: 'No Partner ID found in the Excel file.',
                                        icon: 'error',
                                        confirmButtonText: 'OK'
                                    }).then((result) => {
                                        if (result.isConfirmed) {
                                            window.location.href='billspaymentImportFile.php';
                                        }
                                    });
                                </script>";
                                exit;
                            }
                        } else {
                            // For KP7: Skip all partner validation entirely
                            // Just collect partner names without any validation
                            $partnersFound = ['Multiple Partners (Validation Skipped)'];
                        }

                        // Add date validation for "All" companies
                        $dateMatched = false;
                        $row = 10;
                        while (true) {
                            // Get the status and column based on file type
                            $cellColumn = $sourceFileType === 'KPX' ? 'B' : 'C';
                            $cellValue = $worksheet->getCell($cellColumn . $row)->getValue();
                            
                            if (empty($cellValue)) {
                                break;
                            }

                            $mysqlDateTime = convertToMySQLDateTime($cellValue);
                            if ($mysqlDateTime) {
                                $transactionDate = date('Y-m-d', strtotime($mysqlDateTime));
                                if ($transactionDate === $selectedDate) {
                                    $dateMatched = true;
                                    break;
                                }
                            }

                            $row++;
                        }

                        if (!$dateMatched) {
                            echo "<script>
                                Swal.fire({
                                    title: 'Date Mismatch',
                                    text: 'Selected date does not match any transaction dates in the Excel file. Please Verify the date.',
                                    icon: 'warning',
                                    confirmButtonText: 'OK'
                                }).then(() => {
                                    window.location.href='billspaymentImportFile.php';
                                });
                            </script>";
                            exit;
                        }
                    } else {
                        $selectedDate = date('Y-m-d', strtotime($_POST['datePicker']));
                        $dateMatched = false;
                        $sourceFileType = isset($_POST['fileType']) ? $_POST['fileType'] : '';
                        
                        $row = 10;
                        while (true) {
                            // Get the status value from column A
                            $status = $worksheet->getCell('A' . $row)->getValue();
                            
                            // Check if status contains an asterisk (*)
                            $hasAsterisk = strpos($status, '*') !== false;
                            
                            // Choose which column to check based on file type
                            if ($sourceFileType === 'KPX') {
                                $cellValue = $worksheet->getCell('B' . $row)->getValue(); // datetime in column B for KPX
                            } else {
                                $cellValue = $worksheet->getCell('C' . $row)->getValue(); // datetime in column C for KP7
                            }

                            if (empty($cellValue)) {
                                break;
                            }

                            $mysqlDateTime = convertToMySQLDateTime($cellValue);
                            if ($mysqlDateTime) {
                                $transactionDate = date('Y-m-d', strtotime($mysqlDateTime));
                                if ($transactionDate === $selectedDate) {
                                    $dateMatched = true;
                                    break;
                                }
                            }

                            $row++;
                        }

                        if (!$dateMatched) {
                            echo "<script>
                                Swal.fire({
                                    title: 'Date Mismatch',
                                    text: 'Selected date does not match any transaction dates in the Excel file.',
                                    icon: 'warning',
                                    confirmButtonText: 'OK'
                                }).then(() => {
                                    window.location.href='billspaymentImportFile.php';
                                });
                            </script>";
                            exit;
                        }
                    }

                    
                    // This is where all validations have passed for both cases
                    
                    $destinationDirectory = '../billspayment_transaction_excel_file/';
                    if (!empty($selectedCompany) && $selectedCompany !== 'All') {
                        $sanitizedCompany = preg_replace('/[^a-zA-Z0-9-_]/', '_', $selectedCompany);
                        $newFileName = $sanitizedCompany . '_' . date('YmdHis') . '.' . $fileExtension;
                    } else {
                        $newFileName = $originalFileName;
                    }

                    $destinationFilePath = $destinationDirectory . $newFileName;
                    $_SESSION['existingBillspaymentFile'] = $destinationFilePath;

                    if (move_uploaded_file($fileTmpPath, $destinationFilePath)) {
                        // Count rows in the spreadsheet before showing success message
                        function countInsertableRows($spreadsheet, $sourceFileType) {
                            $rowCount = 0;
                            foreach ($spreadsheet->getWorksheetIterator() as $worksheet) {
                                $highestRow = $worksheet->getHighestRow();
                                for ($row = 10; $row <= $highestRow; ++$row) {
                                    // Check if row has essential data (based on file type)
                                    $dateColumn = $sourceFileType === 'KPX' ? 'B' : 'C';
                                    $dateValue = $worksheet->getCell($dateColumn . $row)->getValue();
                                    
                                    if (!empty($dateValue)) {
                                        $rowCount++;
                                    } else {
                                        // Stop counting when we hit empty date cells
                                        break;
                                    }
                                }
                            }
                            return $rowCount;
                        }
                        
                        // Get a count of rows that will be inserted
                        $expectedRowCount = countInsertableRows($spreadsheet, $sourceFileType);

                        // Get the actual partner ID from the database based on the selected partner name
                        $partnerId = '';
                        if (!empty($selectedCompany) && $selectedCompany !== 'All') {
                            $partnerIdQuery = "SELECT partner_id FROM masterdata.partner_masterfile WHERE partner_name = '" . 
                                $conn->real_escape_string($selectedCompany) . "' LIMIT 1";
                            $partnerIdResult = $conn->query($partnerIdQuery);
                            if ($partnerIdResult && $partnerIdResult->num_rows > 0) {
                                $partnerIdData = $partnerIdResult->fetch_assoc();
                                $partnerId = $partnerIdData['partner_id'];
                            }
                        }
                        
                        // Calculate transaction summary
                        $transactionSummary = calculateTransactionSummary($spreadsheet, $sourceFileType);

                        $result = insertData($spreadsheet, $conn);
                        
                        // Only if validation passes, then check for existing dates
                        if ($result['success']) {
                            // Store the prepared data in session
                            $_SESSION['prepared_billspayment_data'] = $result['data'];
                        // Check for existing dates first
                        $existingDates = [];
                        $sourceFileType = isset($_POST['fileType']) ? $_POST['fileType'] : '';
                        foreach ($spreadsheet->getWorksheetIterator() as $worksheet) {
                            $highestRow = $worksheet->getHighestRow();
                            for ($row = 10; $row <= $highestRow; ++$row) {
                                // Get the cell value based on file type
                                $cellColumn = $sourceFileType === 'KPX' ? 'B' : 'C';
                                $cellValue = $worksheet->getCell($cellColumn . $row)->getValue();
                                
                                if (empty($cellValue)) {
                                    break;
                                }
                                $excelDateTime = $cellValue;
                                $mysqlDateTime = convertToMySQLDateTime($excelDateTime);

                                $sql = "SELECT COUNT(*) as count FROM mldb.billspayment_transaction WHERE datetime = '" 
                                    . $conn->real_escape_string($mysqlDateTime) . "'";
                                $result = $conn->query($sql);
                                $row_count = $result->fetch_assoc();
                                if ($row_count['count'] > 0 && !in_array($mysqlDateTime, $existingDates)) {
                                    $existingDates[] = $mysqlDateTime;
                                };
                            }
                        }

                        if (!empty($existingDates)) {
                            // Store the file type for override
                            $_SESSION['fileType'] = $sourceFileType;

                            // Prepare partner info for display
                            $partnerIdDisplay = isset($partnerId) ? htmlspecialchars($partnerId) : '';
                            $partnerNameDisplay = isset($selectedCompany) ? htmlspecialchars($selectedCompany) : '';
                            $sourceDisplay = htmlspecialchars($sourceFileType . " SYSTEM");
                            $existingCount = count($existingDates);

                            // Use SweetAlert2 for the modal
                            echo "
                            <script>
                                Swal.fire({
                                    icon: 'warning',
                                    title: 'EXISTING DATES FOUND',
                                    html: `<div style='text-align:left;'>
                                            <b>PARTNER ID:</b> {$partnerIdDisplay}<br>
                                            <b>PARTNER NAME:</b> {$partnerNameDisplay}<br>
                                            <b>Source:</b> {$sourceDisplay}<br><br>
                                            There are <b>{$existingCount}</b> existing date(s) found in this file.<br>
                                            Would you like to override?
                                        </div>`,
                                    showCancelButton: true,
                                    confirmButtonText: 'Override Data',
                                    cancelButtonText: 'Cancel',
                                    allowOutsideClick: false,
                                    allowEscapeKey: false,
                                    customClass: {
                                        confirmButton: 'btn btn-danger me-2',
                                        cancelButton: 'btn btn-secondary'
                                    },
                                    buttonsStyling: false
                                }).then((result) => {
                                    if (result.isConfirmed) {
                                        // Create and submit a form to override
                                        var form = document.createElement('form');
                                        form.method = 'post';
                                        form.action = '';
                                        var input = document.createElement('input');
                                        input.type = 'hidden';
                                        input.name = 'overrideData';
                                        input.value = '1';
                                        form.appendChild(input);
                                        var fileTypeInput = document.createElement('input');
                                        fileTypeInput.type = 'hidden';
                                        fileTypeInput.name = 'fileType';
                                        fileTypeInput.value = '" . addslashes($sourceFileType) . "';
                                        form.appendChild(fileTypeInput);
                                        // Add partner name and id as hidden fields
                                        var companyInput = document.createElement('input');
                                        companyInput.type = 'hidden';
                                        companyInput.name = 'company';
                                        companyInput.value = '" . addslashes($selectedCompany) . "';
                                        form.appendChild(companyInput);
                                        var partnerIdInput = document.createElement('input');
                                        partnerIdInput.type = 'hidden';
                                        partnerIdInput.name = 'partner_id';
                                        partnerIdInput.value = '" . addslashes($partnerId) . "';
                                        form.appendChild(partnerIdInput);
                                        document.body.appendChild(form);
                                        form.submit();
                                    } else {
                                        window.location.href = 'billspaymentImportFile.php';
                                    }
                                });
                            </script>
                            ";
                            exit;
                        }
                        // Only display success message if no existing dates were found
                        echo '
                        <script>
                            // Hide the loading overlay
                            document.getElementById("loading-overlay").style.display = "none";
                            
                            // Hide the upload form after successful upload
                            document.querySelector(".container-fluid.border.border-danger").style.display = "none";
                        </script>
                        
                        <div id="upload-success" class="container-fluid py-4" style="margin-top: 20px;">
                            <!-- Confirmation Buttons with Enhanced Styling -->
                            <div class="text-center mb-4">
                                <div class="card shadow-sm border-0 bg-light py-4">
                                    <!-- <h3 class="text-center fw-bold text-primary">Would you like to proceed inserting the data?</h3> -->
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
                                                        <tr>
                                                        <td><i class="fas fa-id-card text-primary me-2"></i>Partner ID</td>
                                                            <td class="fw-semibold">' . htmlspecialchars($partnerId) . '</td>
                                                        </tr>
                                                        <tr>
                                                            <td><i class="fas fa-building text-primary me-2"></i>Partner Name</td>
                                                            <td class="fw-semibold">' . htmlspecialchars($selectedCompany) . '</td>
                                                        </tr>
                                                        <tr>
                                                        <td><i class="fas fa-list-ol text-primary me-2"></i>Rows Imported</td>
                                                            <td class="fw-semibold">' . number_format($expectedRowCount) . '</td>
                                                        </tr>
                                                        <tr>
                                                            <td><i class="fas fa-file-import text-primary me-2"></i>Source</td>
                                                            <td class="fw-semibold">' . htmlspecialchars($sourceFileType . " System") . '</td>
                                                        </tr> 
                                                        <tr>
                                                            <td><i class="fas fa-calendar-alt text-primary me-2"></i>Transaction Date</td>
                                                            <td class="fw-semibold">' . htmlspecialchars(date('F d, Y', strtotime($selectedDate))) . '</td>
                                                        </tr>
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
                                                <tbody>
                                                    <!-- Total Count Row -->
                                                    <tr>
                                                        <td class="border-end">
                                                            <div class="row">
                                                                <div class="col-6 fw-semibold"><i class="fas fa-calculator text-secondary me-2"></i>TOTAL COUNT</div>
                                                                <div class="col-6 text-end fw-bold">' . number_format($transactionSummary['count']) . '</div>
                                                            </div>
                                                        </td>
                                                        <td class="border-end">
                                                            <div class="row">
                                                            <div class="col-6 fw-semibold"><i class="fas fa-calculator text-secondary me-2"></i>TOTAL COUNT</div>
                                                                <div class="col-6 text-end fw-bold">' . number_format($transactionSummary['adjustments']['count']) . '</div>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <div class="row">
                                                                <div class="col-6 fw-semibold"><i class="fas fa-calculator text-secondary me-2"></i>TOTAL COUNT</div>
                                                                <div class="col-6 text-end fw-bold">' . number_format($transactionSummary['net']['count']) . '</div>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                    
                                                    <!-- Total Principal Row -->
                                                    <tr>
                                                        <td class="border-end">
                                                            <div class="row">
                                                            <div class="col-6 fw-semibold"><i class="fas fa-money-bill-wave text-success me-2"></i>TOTAL PRINCIPAL</div>
                                                            <div class="col-6 text-end fw-bold">' . formatCurrency($transactionSummary['regular']['principal']) . '</div>
                                                            </div>
                                                        </td>
                                                        <td class="border-end">
                                                            <div class="row">
                                                            <div class="col-6 fw-semibold"><i class="fas fa-money-bill-wave text-success me-2"></i>TOTAL PRINCIPAL</div>
                                                            <div class="col-6 text-end fw-bold">' . formatCurrency($transactionSummary['adjustments']['principal']) . '</div>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <div class="row">
                                                            <div class="col-6 fw-semibold"><i class="fas fa-money-bill-wave text-success me-2"></i>TOTAL PRINCIPAL</div>
                                                            <div class="col-6 text-end fw-bold">' . formatCurrency($transactionSummary['net']['principal']) . '</div>
                                                            </div>
                                                        </td>
                                                    </tr>

                                                    <!-- Total Charges for both partner and customer -->
                                                    <tr>
                                                        <td class="border-end">
                                                            <div class="row">
                                                            <div class="col-6 fw-semibold"><i class="fas fa-receipt text-danger me-2"></i>TOTAL CHARGE</div>
                                                            <div class="col-6 text-end fw-bold">' . formatCurrency($transactionSummary['regular']['total_charge']) . '</div>
                                                            </div>
                                                        </td>
                                                        <td class="border-end">
                                                            <div class="row">
                                                            <div class="col-6 fw-semibold"><i class="fas fa-receipt text-danger me-2"></i>TOTAL CHARGE</div>
                                                            <div class="col-6 text-end fw-bold">' . formatCurrency($transactionSummary['adjustments']['total_charge']) . '</div>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <div class="row">
                                                            <div class="col-6 fw-semibold"><i class="fas fa-receipt text-danger me-2"></i>TOTAL CHARGE</div>
                                                            <div class="col-6 text-end fw-bold">' . formatCurrency($transactionSummary['net']['total_charge']) . '</div>
                                                            </div>
                                                        </td>
                                                    </tr>

                                                    <!-- Total charge to Partner Row -->
                                                    <tr>
                                                        <td class="border-end">
                                                            <div class="row">
                                                            <div class="col-6 fw-semibold"><i class="fas fa-building text-primary me-2"></i>CHARGE TO PARTNER</div>
                                                            <div class="col-6 text-end fw-bold">' . formatCurrency($transactionSummary['regular']['charge_partner']) . '</div>
                                                            </div>
                                                        </td>
                                                        <td class="border-end">
                                                            <div class="row">
                                                            <div class="col-6 fw-semibold"><i class="fas fa-building text-primary me-2"></i>CHARGE TO PARTNER</div>
                                                            <div class="col-6 text-end fw-bold">' . formatCurrency($transactionSummary['adjustments']['charge_partner']) . '</div>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <div class="row">
                                                            <div class="col-6 fw-semibold"><i class="fas fa-building text-primary me-2"></i>CHARGE TO PARTNER</div>
                                                            <div class="col-6 text-end fw-bold">' . formatCurrency($transactionSummary['net']['charge_partner']) . '</div>
                                                            </div>
                                                        </td>
                                                    </tr>

                                                    <!-- Total charge to customer Row -->
                                                    <tr>
                                                        <td class="border-end">
                                                            <div class="row">
                                                            <div class="col-6 fw-semibold"><i class="fas fa-user text-info me-2"></i>CHARGE TO CUSTOMER</div>
                                                            <div class="col-6 text-end fw-bold">' . formatCurrency($transactionSummary['regular']['charge_customer']) . '</div>
                                                            </div>
                                                        </td>
                                                        <td class="border-end">
                                                            <div class="row">
                                                            <div class="col-6 fw-semibold"><i class="fas fa-user text-info me-2"></i>CHARGE TO CUSTOMER</div>
                                                            <div class="col-6 text-end fw-bold">' . formatCurrency($transactionSummary['adjustments']['charge_customer']) . '</div>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <div class="row">
                                                            <div class="col-6 fw-semibold"><i class="fas fa-user text-info me-2"></i>CHARGE TO CUSTOMER</div>
                                                            <div class="col-6 text-end fw-bold">' . formatCurrency($transactionSummary['net']['charge_customer']) . '</div>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                    <tr class="table-secondary">
                                                        <td class="text-start fw-bold fs-5"><i class="fas fa-coins text-warning me-2"></i>TOTAL AMOUNT (PHP)</td>
                                                        <td colspan="2" class="text-end fw-bold fs-5">' . formatCurrency($transactionSummary['net']['settlement']) . '</td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div> 
                        </div>
                        ';
                        
                        // If we get here, no existing dates found - proceed with data insertion
                        $result = insertData($spreadsheet, $conn);
                        if ($result['success']) {
                            // Store the prepared data in session
                            $_SESSION['prepared_billspayment_data'] = $result['data'];
                            
                            echo '<script>
                                // Hide the loading overlay
                                document.getElementById("loading-overlay").style.display = "none";
                                
                                // Hide the upload form after successful upload
                                document.querySelector(".container-fluid.border.border-danger").style.display = "none";
                                
                                // Show success message with confirm button
                                Swal.fire({
                                    title: "Data Prepared Successfully!",
                                    html: "<strong>File Processing Complete</strong><br><br>" +
                                        "• Rows Processed: <php echo ' . number_format($result['rowCount']) . '<br>" +
                                        "• File Type: ' . $sourceFileType . '<br>" +
                                        "• File Name: ' . addslashes($originalFileName) . '<br><br>" +
                                        "The data has been prepared for import. Click \\"Confirm Import"\\ to proceed with database insertion.",
                                    icon: "success",
                                    showCancelButton: true,
                                    confirmButtonText: "Confirm Import",
                                    cancelButtonText: "Cancel"
                                }).then((result) => {
                                    if (result.isConfirmed) {
                                        // Submit form to trigger actual database insertion
                                        var form = document.createElement("form");
                                        form.method = "post";
                                        form.action = "billspaymentImportFile.php";
                                        
                                        var input = document.createElement("input");
                                        input.type = "hidden";
                                        input.name = "confirm_import";
                                        input.value = "1";
                                        
                                        form.appendChild(input);
                                        document.body.appendChild(form);
                                        form.submit();
                                    } else {
                                        window.location.href = "billspaymentImportFile.php";
                                    }
                                });
                            </script>';
                            }
                        } else {
                            error_log("Billspayment import failed. Error details may be in the PHP error log.");
                            echo '<script>
                                var loadingOverlay = document.getElementById("loading-overlay");
                                if (loadingOverlay) {
                                    loadingOverlay.style.display = "none";
                                }
                                Swal.fire({
                                    title: "Import Failed",
                                    text: "Failed to import data. Please check the log for details.",
                                    icon: "error",
                                    confirmButtonText: "OK"
                                }).then((result) => {
                                    if (result.isConfirmed) {
                                        window.location.href="billspaymentImportFile.php";
                                    }
                                });
                            </script>';
                        }
                    } else {
                        echo "<script>
                            Swal.fire({
                                title: 'Upload Failed',
                                text: 'Failed to move uploaded file.',
                                icon: 'error',
                                confirmButtonText: 'OK'
                            }).then((result) => {
                                if (result.isConfirmed) {
                                    window.location.href='billspaymentImportFile.php';
                                }
                            });
                        </script>";
                        exit;
                    }
                } catch (Exception $e) {
                    echo "<script>
                        Swal.fire({
                            title: 'Error Processing File',
                            text: 'Error processing Excel file: " . $e->getMessage() . "',
                            icon: 'error',
                            confirmButtonText: 'OK'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                window.location.href='billspaymentImportFile.php';
                            }
                        });
                    </script>";
                    exit;
                }
            }
            
            if (isset($_POST['overrideData'])) {
                // Retrieve the file path from session and load the spreadsheet
                $filePath = $_SESSION['existingBillspaymentFile'];
                $spreadsheet = IOFactory::load($filePath);

                // Get the source file type from hidden input or session
                $sourceFileType = isset($_POST['fileType']) ? $_POST['fileType'] : 
                                (isset($_SESSION['fileType']) ? $_SESSION['fileType'] : 'KP7');

                // Get partner info from POST (from hidden fields)
                $overridePartnerName = isset($_POST['company']) ? $_POST['company'] : '';
                $overridePartnerId = isset($_POST['partner_id']) ? $_POST['partner_id'] : '';

                // Collect all transaction dates that need to be deleted
                $txnDateToDelete = [];
                foreach ($spreadsheet->getAllSheets() as $sheet) {
                    $startRow = 10;
                    $endRow = $sheet->getHighestRow();
                    for ($row = $startRow; $row <= $endRow; $row++) {
                        // Choose column based on file type
                        $dateColumn = $sourceFileType === 'KPX' ? 'B' : 'C';
                        $date = $sheet->getCell($dateColumn . $row)->getValue();
                        if (empty($date)) {
                            continue; // Skip empty cells
                        }
                        
                        $excelDateTime = $date;
                        $mysqlDateTime = convertToMySQLDateTime($excelDateTime);

                        if (!empty($mysqlDateTime) && !in_array($mysqlDateTime, $txnDateToDelete)) {
                            $txnDateToDelete[] = $mysqlDateTime;
                        }
                    }
                }

                // Check if any of these transactions are already settled (which prevents override)
                foreach ($txnDateToDelete as $mysqlDateTime) {
                    $sql = "SELECT DISTINCT settle_unsettle FROM mldb.billspayment_transaction WHERE datetime = '" . $conn->real_escape_string($mysqlDateTime) . "'";
                    $resultPost = $conn->query($sql);
                    
                    // Only check if records exist
                    if ($resultPost && $resultPost->num_rows > 0) {
                        $row_resultPost = $resultPost->fetch_assoc();
                        if ($row_resultPost['settle_unsettle'] !== 'UNSETTLED') {
                            echo "<script>
                                Swal.fire({
                                    title: 'Unable to Override',
                                    text: 'Oops! Unable to Override. Data Already Posted.',
                                    icon: 'warning',
                                    confirmButtonText: 'OK'
                                }).then((result) => {
                                    if (result.isConfirmed) {
                                        window.location.href='billspaymentImportFile.php';
                                    }
                                });
                            </script>";
                            exit;
                        }
                    }
                }

                // Delete the existing transactions with these dates
                foreach ($txnDateToDelete as $mysqlDateTime) {
                    $sql = "DELETE FROM mldb.billspayment_transaction WHERE datetime = '" . $conn->real_escape_string($mysqlDateTime) . "'";
                    $conn->query($sql);
                }

                // Add calculation of transaction summary
                $transactionSummary = calculateTransactionSummary($spreadsheet, $sourceFileType);

                // Insert new data from the spreadsheet
                // Pass override partner info to insertData via $_POST (simulate original upload context)
                $_POST['company'] = $overridePartnerName;
                if (!empty($overridePartnerId)) {
                    // Optionally, if your insertData logic uses $_SESSION for partner id, set it here
                    $_SESSION['selected_partner_id'] = $overridePartnerId;
                    $_SESSION['selected_partner_name'] = $overridePartnerName;
                }

                $result = insertData($spreadsheet, $conn);

                // Actually insert the prepared data into the database
                if ($result['success'] && !empty($result['data'])) {
                    $insertResult = insertDataToDatabase($result['data'], $conn);
                    if ($insertResult['success']) {
                        echo "<script>
                            Swal.fire({
                                title: 'Success!',
                                text: 'Data successfully loaded. " . $insertResult['rowCount'] . " rows have been added.',
                                icon: 'success',
                                confirmButtonText: 'OK'
                            }).then((result) => {
                                if (result.isConfirmed) {
                                    window.location.href = 'billspaymentImportFile.php';
                                }
                            });
                        </script>";
                    } else {
                        echo "<script>
                            Swal.fire({
                                title: 'Insertion Failed',
                                text: 'Failed to insert data into the database.',
                                icon: 'error',
                                confirmButtonText: 'OK'
                            }).then((result) => {
                                if (result.isConfirmed) {
                                    window.location.href = 'billspaymentImportFile.php';
                                }
                            });
                        </script>";
                    }
                } else {
                    echo "<script>
                        Swal.fire({
                            title: 'Preparation Failed',
                            text: 'Failed to prepare data for insertion.',
                            icon: 'error',
                            confirmButtonText: 'OK'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                window.location.href = 'billspaymentImportFile.php';
                            }
                        });
                    </script>";
                }
            }

            // Add handler for confirm_import
            if (isset($_POST['confirm_import']) && isset($_SESSION['prepared_billspayment_data'])) {
                $preparedData = $_SESSION['prepared_billspayment_data'];
                $referenceNumbers = [];
                $refsToCheck = [];

                // Collect all reference numbers from prepared data
                foreach ($preparedData as $row) {
                    $ref = $row['reference_no'];
                    if (!empty($ref)) {
                        $referenceNumbers[$ref] = true;
                    }
                }

                // Check for duplicates in the database
                if (!empty($referenceNumbers)) {
                    $refList = array_map(function($ref) use ($conn) {
                        return "'" . $conn->real_escape_string($ref) . "'";
                    }, array_keys($referenceNumbers));
                    $refListStr = implode(',', $refList);
                    $sql = "SELECT reference_no FROM mldb.billspayment_transaction WHERE reference_no IN ($refListStr) LIMIT 1";
                    $result = $conn->query($sql);
                    if ($result && $result->num_rows > 0) {
                        $row = $result->fetch_assoc();
                        $duplicateInDB = $row['reference_no'];
                        echo "<script>
                            Swal.fire({
                                title: 'Duplicate Reference Number',
                                text: 'Duplicate reference number {$duplicateInDB} has already exist in the database please check the file',
                                icon: 'error',
                                confirmButtonText: 'OK',
                                allowOutsideClick: false,
                                allowEscapeKey: false
                            }).then((result) => {
                                if (result.isConfirmed) {
                                    window.location.href='billspaymentImportFile.php';
                                }
                            });
                        </script>";
                        unset($_SESSION['prepared_billspayment_data']);
                        exit;
                    }
                }

                // ...existing code for actual insertion...
                $result = insertDataToDatabase($preparedData, $conn);
                unset($_SESSION['prepared_billspayment_data']);
                if ($result['success']) {
                    echo '<script>
                        Swal.fire({
                            title: "Success!",
                            text: "Data successfully imported. ' . number_format($result['rowCount']) . ' rows have been added.",
                            icon: "success",
                            confirmButtonText: "OK"
                        }).then((result) => {
                            if (result.isConfirmed) {
                                window.location.href = "billspaymentImportFile.php";
                            }
                        });
                    </script>';
                } else {
                    echo '<script>
                        Swal.fire({
                            title: "Import Failed",
                            text: "Failed to import data. Please check the log for details.",
                            icon: "error",
                            confirmButtonText: "OK"
                        }).then((result) => {
                            if (result.isConfirmed) {
                                window.location.href = "billspaymentImportFile.php";
                            }
                        });
                    </script>';
                }
            }
        ?>

        <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>
        <!-- SweetAlert2 JS -->
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>
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

