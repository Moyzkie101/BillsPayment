<?php
// Connect to the database
include '../../../config/config.php';
require '../../../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

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

// Fetch main zones
$mainzoneQuery = "SELECT 
                    mmzm.main_zone_code
                FROM 
                    masterdata.main_zone_masterfile AS mmzm 
                WHERE 
                    main_zone_code NOT IN ('JEW', 'HO')";
$mainzoneResult = $conn->query($mainzoneQuery);

// Handle AJAX requests for zones
if (isset($_POST['action']) && $_POST['action'] === 'get_zones') {
    $mainzone = $_POST['mainzone'];
    
    $zoneQuery = "SELECT 
                    mzm.zone_code
                FROM masterdata.main_zone_masterfile AS mmzm
                JOIN masterdata.zone_masterfile AS mzm
                    ON mmzm.main_zone_code = mzm.main_zone_code
                AND mzm.zone_code NOT IN (
                        'HO',
                        'JEW',
                        'VISMIN-MANCOMM',
                        'LNCR-MANCOMM',
                        'VISMIN-SUPPORT',
                        'LNCR-SUPPORT'
                )
                AND mmzm.main_zone_code NOT IN ('JEW', 'HO')";
                
    if($mainzone !== 'ALL'){
        $zoneQuery .= " WHERE mmzm.main_zone_code = ?";
        $stmt = $conn->prepare($zoneQuery);
        $stmt->bind_param("s", $mainzone);
    } else {
        $stmt = $conn->prepare($zoneQuery);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $options = '';
    while ($row = $result->fetch_assoc()) {
        $options .= '<option value="' . $row['zone_code'] . '">' . $row['zone_code'] . '</option>';
    }
    
    echo $options;
    exit; // Stop execution for AJAX response
}
elseif (isset($_POST['action']) && $_POST['action'] === 'get_regions'){
    $mainzone = $_POST['mainzone'];
    $zone = $_POST['zone'];
    
    $regionQuery = "SELECT 
                    mrm.region_code,
                    mrm.region_description
                FROM masterdata.main_zone_masterfile AS mmzm
                JOIN masterdata.zone_masterfile AS mzm
                    ON mmzm.main_zone_code = mzm.main_zone_code
                AND mzm.zone_code NOT IN (
                        'VISMIN-MANCOMM',
                        'LNCR-MANCOMM',
                        'VISMIN-SUPPORT',
                        'LNCR-SUPPORT'
                    )
                AND mmzm.main_zone_code NOT IN (
                        'JEW', 'HO'
                    )
                JOIN masterdata.region_masterfile AS mrm
                    ON mzm.zone_code = mrm.zone_code";
                    
                if($mainzone !== 'ALL'){ //(LNCR, VISMIN)
                    if($zone !== 'ALL'){ // (LZN,NCR,VIS,MIN,SHOWROOM), (LNCR, VISMIN)
                        if ($zone !== 'Showroom') { // (LZN,NCR,VIS,MIN), (LNCR, VISMIN)
                            $regionQuery .= " WHERE mmzm.main_zone_code = ? AND mzm.zone_code = ?";
                        }
                    }else{
                        $regionQuery .= " WHERE mmzm.main_zone_code = ?";
                    }
                }else{ // (ALL)
                    if($zone !== 'ALL'){ // (LZN,NCR,VIS,MIN,SHOWROOM), (ALL)
                        if ($zone !== 'Showroom') { // (LZN,NCR,VIS,MIN), (ALL)
                            $regionQuery .= " WHERE mzm.zone_code = ?";
                        }
                    }
                }
    
    $stmt = $conn->prepare($regionQuery);

    if($mainzone !== 'ALL'){ //(LNCR, VISMIN)
        if($zone !== 'ALL'){ // (LZN,NCR,VIS,MIN,SHOWROOM), (LNCR, VISMIN)
            if ($zone !== 'Showroom') { // (LZN,NCR,VIS,MIN), (LNCR, VISMIN)
                $stmt->bind_param("ss", $mainzone, $zone);
            }
        }else{
            $stmt->bind_param("s", $mainzone);
        }
    }else{ // (ALL)
        if($zone !== 'ALL'){ // (LZN,NCR,VIS,MIN,SHOWROOM), (ALL)
            if ($zone !== 'Showroom') { // (LZN,NCR,VIS,MIN), (ALL)
                $stmt->bind_param("s", $zone);
            }
        }
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $options2 = '';
    while ($row = $result->fetch_assoc()) {
        $options2 .= '<option value="' . $row['region_code'] . '">' . $row['region_description'] . '</option>';
    }
    
    echo $options2;
    exit;
}
elseif (isset($_POST['action']) && $_POST['action'] === 'get_partners') {
    $partnerQuery = "SELECT partner_id, partner_name 
                     FROM masterdata.partner_masterfile 
                     ORDER BY partner_name ASC";
    $result = $conn->query($partnerQuery);
    
    $options = '';
    while ($row = $result->fetch_assoc()) {
        $options .= '<option value="' . $row['partner_id'] . '">' . $row['partner_name'] . '</option>';
    }
    
    echo $options;
    exit;
}
elseif (isset($_POST['action']) && $_POST['action'] === 'get_areas') {
    $areaQuery = "SELECT DISTINCT area 
                  FROM masterdata.branch_profile 
                  WHERE area IS NOT NULL AND area != '' 
                  ORDER BY area ASC";
    $result = $conn->query($areaQuery);
    
    $options = '';
    while ($row = $result->fetch_assoc()) {
        $options .= '<option value="' . $row['area'] . '">' . $row['area'] . '</option>';
    }
    
    echo $options;
    exit;
}
elseif (isset($_POST['action']) && $_POST['action'] === 'get_report_data') {
    $mainzone = $_POST['mainzone'];
    $zone = $_POST['zone'];
    $region = $_POST['region'];
    $partner = $_POST['partner'] ?? 'ALL';
    $area = $_POST['area'] ?? 'ALL';
    $filterType = $_POST['filterType'];
    $startDate = $_POST['startDate'];
    $endDate = $_POST['endDate'];
    
    // Build date range based on filter type
    $dateCondition = "";
    if ($filterType === 'weekly') {
        // For weekly, dates come as YYYY-MM-DD format
        $dateCondition = "DATE(bt.datetime) BETWEEN '$startDate' AND '$endDate'";
    } elseif ($filterType === 'monthly') {
        // For monthly, dates come as YYYY-MM format, convert to full date range
        $startMonth = $startDate . '-01';
        $endMonth = date('Y-m-t', strtotime($endDate . '-01')); // Last day of end month
        $dateCondition = "DATE(bt.datetime) BETWEEN '$startMonth' AND '$endMonth'";
    } elseif ($filterType === 'yearly') {
        // For yearly, dates come as YYYY format, convert to full year range
        $startYear = $startDate . '-01-01';
        $endYear = $endDate . '-12-31';
        $dateCondition = "DATE(bt.datetime) BETWEEN '$startYear' AND '$endYear'";
    }
    
    // Build the WHERE clause based on selected filters
    $whereConditions = [];
    $whereConditions[] = "($dateCondition)";
    $whereConditions[] = "bt.status IS NULL";
    $whereConditions[] = "bt.post_transaction = 'unposted'";
    
    // Add zone/region filtering - Fixed logic
    if ($mainzone !== 'ALL') {
        if ($zone !== 'ALL') {
            if ($zone !== 'Showroom') {
                // Specific zone selected
                $whereConditions[] = "mrm.zone_code = '$zone'";
                if ($region !== 'ALL') {
                    // Specific region selected
                    $whereConditions[] = "mrm.region_code = '$region'";
                }
            } else {
                // Showroom selected for specific mainzone
                if ($region !== 'ALL') {
                    if (strpos($region, 'Showroom') !== false) {
                        // Handle showroom regions
                        $showroomZones = [];
                        if ($region === $mainzone . ' Showroom') {
                            // All showrooms for this mainzone
                            if ($mainzone === 'LNCR') {
                                $showroomZones = ['LZNS', 'NCRS'];
                            } elseif ($mainzone === 'VISMIN') {
                                $showroomZones = ['VISS', 'MINS'];
                            }
                        } else {
                            // Specific showroom region
                            $showroomZones = [$region];
                        }
                        if (!empty($showroomZones)) {
                            $whereConditions[] = "mrm.zone_code IN ('" . implode("','", $showroomZones) . "')";
                        }
                    } else {
                        // Regular showroom zone codes
                        $whereConditions[] = "mrm.zone_code = '$region'";
                    }
                }
            }
        } else {
            // ALL zones for specific mainzone
            $whereConditions[] = "mrm.zone_code IN (SELECT zone_code FROM masterdata.zone_masterfile mzm JOIN masterdata.main_zone_masterfile mmzm ON mzm.main_zone_code = mmzm.main_zone_code WHERE mmzm.main_zone_code = '$mainzone')";
            if ($region !== 'ALL') {
                $whereConditions[] = "mrm.region_code = '$region'";
            }
        }
    } else {
        // ALL mainzones
        if ($zone !== 'ALL') {
            if ($zone !== 'Showroom') {
                $whereConditions[] = "mrm.zone_code = '$zone'";
                if ($region !== 'ALL') {
                    $whereConditions[] = "mrm.region_code = '$region'";
                }
            } else {
                // Showroom for all mainzones
                if ($region !== 'ALL') {
                    if (strpos($region, 'Showroom') !== false || strpos($region, 'NATIONWIDE') !== false) {
                        // Handle showroom regions for all mainzones
                        $showroomZones = ['LZNS', 'NCRS', 'VISS', 'MINS'];
                        $whereConditions[] = "mrm.zone_code IN ('" . implode("','", $showroomZones) . "')";
                    } else {
                        $whereConditions[] = "mrm.zone_code = '$region'";
                    }
                }
            }
        }
        // If both zone and region are 'ALL', no additional filtering needed
        if ($region !== 'ALL' && $zone === 'ALL') {
            $whereConditions[] = "mrm.region_code = '$region'";
        }
    }
    
    // Add partner and area filters
    if ($partner !== 'ALL') {
        $whereConditions[] = "(bt.partner_id = '$partner' OR bt.partner_id_kpx = '$partner')";
    }
    if ($area !== 'ALL') {
        $whereConditions[] = "mbp.area = '$area'";
    }
    
    $dataQuery = "SELECT
                    mrm.zone_code,
                    mbp.ml_matic_region,
                    pmf.partner_name,
                    mbp.area,
                    mbp.kp_code,
                    mkbm.branch_name,
                    (SUM(bt.charge_to_partner) + SUM(bt.charge_to_customer)) AS charges
                FROM
                    mldb.billspayment_transaction AS bt
                JOIN
                    masterdata.region_masterfile as mrm
                    ON bt.region_code = mrm.region_code
                    AND mrm.zone_code NOT IN ('HO','JEW','VISMIN-MANCOMM','LNCR-MANCOMM','VISMIN-SUPPORT','LNCR-SUPPORT')
                JOIN
                    masterdata.branch_profile AS mbp
                    ON bt.branch_id = mbp.branch_id
                JOIN
                    masterdata.kpx_branch_masterfile AS mkbm
                    ON bt.branch_id = mkbm.branch_id
                LEFT JOIN
                    masterdata.partner_masterfile AS pmf
                    ON bt.partner_id = pmf.partner_id OR bt.partner_id_kpx = pmf.partner_id_kpx
                WHERE " . implode(' AND ', $whereConditions) . "
                GROUP BY mrm.zone_code, mbp.ml_matic_region, pmf.partner_name, mbp.area, mbp.kp_code, mkbm.branch_name
                ORDER BY pmf.partner_name, mbp.area, mbp.ml_matic_region, mbp.kp_code";
    
    try {
        $dataResult = $conn->query($dataQuery);
        
        if ($dataResult) {
            $records = [];
            $totalAmount = 0;
            
            while ($row = $dataResult->fetch_assoc()) {
                $records[] = $row;
                $totalAmount += floatval($row['charges']);
            }
            
            $response = [
                'success' => true,
                'records' => $records,
                'totalRecords' => count($records),
                'totalAmount' => $totalAmount,
                'query' => $dataQuery, // For debugging - remove in production
                'filters' => [
                    'mainzone' => $mainzone,
                    'zone' => $zone, 
                    'region' => $region,
                    'partner' => $partner,
                    'area' => $area,
                    'filterType' => $filterType,
                    'startDate' => $startDate,
                    'endDate' => $endDate
                ]
            ];
            
            header('Content-Type: application/json');
            echo json_encode($response);
        } else {
            throw new Exception('Database query failed: ' . $conn->error);
        }
    } catch (Exception $e) {
        $response = [
            'success' => false,
            'message' => $e->getMessage(),
            'query' => $dataQuery, // For debugging - remove in production
            'filters' => [
                'mainzone' => $mainzone,
                'zone' => $zone, 
                'region' => $region,
                'partner' => $partner,
                'area' => $area,
                'filterType' => $filterType,
                'startDate' => $startDate,
                'endDate' => $endDate
            ]
        ];
        
        header('Content-Type: application/json');
        echo json_encode($response);
    }
    
    exit;
}
elseif (isset($_POST['action']) && $_POST['action'] === 'export_excel') {
    $mainzone = $_POST['mainzone'] ?? 'ALL';
    $zone = $_POST['zone'] ?? 'ALL';
    $region = $_POST['region'] ?? 'ALL';
    $partner = $_POST['partner'] ?? 'ALL';
    $area = $_POST['area'] ?? 'ALL';
    $filterType = $_POST['filterType'] ?? 'weekly';
    $startDate = $_POST['startDate'] ?? date('Y-m-01');
    $endDate = $_POST['endDate'] ?? date('Y-m-t');
    
    // Build date range
    $dateCondition = "";
    if ($filterType === 'weekly') {
        $dateCondition = "DATE(bt.datetime) BETWEEN '$startDate' AND '$endDate'";
    } elseif ($filterType === 'monthly') {
        $startMonth = $startDate . '-01';
        $endMonth = date('Y-m-t', strtotime($endDate . '-01'));
        $dateCondition = "DATE(bt.datetime) BETWEEN '$startMonth' AND '$endMonth'";
    } elseif ($filterType === 'yearly') {
        $startYear = $startDate . '-01-01';
        $endYear = $endDate . '-12-31';
        $dateCondition = "DATE(bt.datetime) BETWEEN '$startYear' AND '$endYear'";
    }
    
    // Build WHERE conditions (same as report query)
    $whereConditions = [];
    $whereConditions[] = "($dateCondition)";
    $whereConditions[] = "bt.status IS NULL";
    $whereConditions[] = "bt.post_transaction = 'unposted'";
    
    // Zone/Region filtering (copy from above)
    if ($mainzone !== 'ALL') {
        if ($zone !== 'ALL') {
            if ($zone !== 'Showroom') {
                $whereConditions[] = "mrm.zone_code = '$zone'";
                if ($region !== 'ALL') {
                    $whereConditions[] = "mrm.region_code = '$region'";
                }
            } else {
                if ($region !== 'ALL') {
                    if (strpos($region, 'Showroom') !== false) {
                        $showroomZones = [];
                        if ($region === $mainzone . ' Showroom') {
                            if ($mainzone === 'LNCR') {
                                $showroomZones = ['LZNS', 'NCRS'];
                            } elseif ($mainzone === 'VISMIN') {
                                $showroomZones = ['VISS', 'MINS'];
                            }
                        } else {
                            $showroomZones = [$region];
                        }
                        if (!empty($showroomZones)) {
                            $whereConditions[] = "mrm.zone_code IN ('" . implode("','", $showroomZones) . "')";
                        }
                    } else {
                        $whereConditions[] = "mrm.zone_code = '$region'";
                    }
                }
            }
        } else {
            $whereConditions[] = "mrm.zone_code IN (SELECT zone_code FROM masterdata.zone_masterfile mzm JOIN masterdata.main_zone_masterfile mmzm ON mzm.main_zone_code = mmzm.main_zone_code WHERE mmzm.main_zone_code = '$mainzone')";
            if ($region !== 'ALL') {
                $whereConditions[] = "mrm.region_code = '$region'";
            }
        }
    } else {
        if ($zone !== 'ALL') {
            if ($zone !== 'Showroom') {
                $whereConditions[] = "mrm.zone_code = '$zone'";
                if ($region !== 'ALL') {
                    $whereConditions[] = "mrm.region_code = '$region'";
                }
            } else {
                if ($region !== 'ALL') {
                    if (strpos($region, 'Showroom') !== false || strpos($region, 'NATIONWIDE') !== false) {
                        $showroomZones = ['LZNS', 'NCRS', 'VISS', 'MINS'];
                        $whereConditions[] = "mrm.zone_code IN ('" . implode("','", $showroomZones) . "')";
                    } else {
                        $whereConditions[] = "mrm.zone_code = '$region'";
                    }
                }
            }
        }
        if ($region !== 'ALL' && $zone === 'ALL') {
            $whereConditions[] = "mrm.region_code = '$region'";
        }
    }
    
    // Partner and Area filters
    if ($partner !== 'ALL') {
        $whereConditions[] = "(bt.partner_id = '$partner' OR bt.partner_id_kpx = '$partner')";
    }
    if ($area !== 'ALL') {
        $whereConditions[] = "mbp.area = '$area'";
    }
    
    $exportQuery = "SELECT
                        mrm.zone_code,
                        mbp.ml_matic_region,
                        mbp.kp_code,
                        (SUM(bt.charge_to_partner) + SUM(bt.charge_to_customer)) AS charges
                    FROM
                        mldb.billspayment_transaction AS bt
                    JOIN
                        masterdata.region_masterfile as mrm
                        ON bt.region_code = mrm.region_code
                        AND mrm.zone_code NOT IN ('HO','JEW','VISMIN-MANCOMM','LNCR-MANCOMM','VISMIN-SUPPORT','LNCR-SUPPORT')
                    JOIN
                        masterdata.branch_profile AS mbp
                        ON bt.branch_id = mbp.branch_id
                    WHERE " . implode(' AND ', $whereConditions) . "
                    GROUP BY mrm.zone_code, mbp.ml_matic_region, mbp.kp_code
                    ORDER BY mbp.ml_matic_region, mbp.kp_code";
    
    $partnerDisplayLabel = 'All Partners';
    if ($partner !== 'ALL') {
        $partnerStmt = $conn->prepare("SELECT partner_name FROM masterdata.partner_masterfile WHERE partner_id = ? OR partner_id_kpx = ? LIMIT 1");
        if ($partnerStmt) {
            $partnerStmt->bind_param("ss", $partner, $partner);
            $partnerStmt->execute();
            $partnerResult = $partnerStmt->get_result();
            if ($partnerRow = $partnerResult->fetch_assoc()) {
                $partnerDisplayLabel = $partnerRow['partner_name'] ?: $partner;
            } else {
                $partnerDisplayLabel = $partner;
            }
            $partnerStmt->close();
        } else {
            $partnerDisplayLabel = $partner;
        }
    }
    $partnerDisplayLabel = trim($partnerDisplayLabel);
    $sanitizedPartnerLabel = preg_replace('/[^A-Za-z0-9]+/', '_', $partnerDisplayLabel);
    if ($sanitizedPartnerLabel === '') {
        $sanitizedPartnerLabel = 'Partner';
    }
    // If all mainzone/zone/region/area are ALL, create a separate sheet per zone
    if ($mainzone === 'ALL' && $zone === 'ALL' && $region === 'ALL' && $area === 'ALL') {
        $zones = ['LZN', 'MIN', 'NCR', 'VIS', 'SHOWROOM'];
        $spreadsheet = new Spreadsheet();

        $sheetIndex = 0;
        foreach ($zones as $z) {
            // Prepare zone-specific WHERE conditions
            $zoneWhere = $whereConditions; // copy base conditions
            if (strtoupper($z) === 'SHOWROOM') {
                // showroom zone codes
                $showroomCodes = ['LZNS', 'NCRS', 'VISS', 'MINS'];
                $zoneWhere[] = "mrm.zone_code IN ('" . implode("','", $showroomCodes) . "')";
                $sheetName = 'SHOWROOM';
            } else {
                $zoneWhere[] = "mrm.zone_code = '$z'";
                $sheetName = strtoupper($z);
            }

            $zoneQuery = "SELECT
                        mrm.zone_code,
                        mbp.ml_matic_region,
                        mbp.kp_code,
                        (SUM(bt.charge_to_partner) + SUM(bt.charge_to_customer)) AS charges
                    FROM
                        mldb.billspayment_transaction AS bt
                    JOIN
                        masterdata.region_masterfile as mrm
                        ON bt.region_code = mrm.region_code
                        AND mrm.zone_code NOT IN ('HO','JEW','VISMIN-MANCOMM','LNCR-MANCOMM','VISMIN-SUPPORT','LNCR-SUPPORT')
                    JOIN
                        masterdata.branch_profile AS mbp
                        ON bt.branch_id = mbp.branch_id
                    WHERE " . implode(' AND ', $zoneWhere) . "
                    GROUP BY mrm.zone_code, mbp.ml_matic_region, mbp.kp_code
                    ORDER BY mbp.ml_matic_region, mbp.kp_code";

            $zResult = $conn->query($zoneQuery);

            if ($sheetIndex === 0) {
                $sheet = $spreadsheet->getActiveSheet();
                $sheet->setTitle($sheetName);
            } else {
                $sheet = $spreadsheet->createSheet();
                $sheet->setTitle($sheetName);
            }

            // Set headers for this sheet
            $headers = ['Zone', 'Region Name', 'KP Code', 'Charges'];
            $col = 'A';
            foreach ($headers as $header) {
                $sheet->setCellValue($col . '1', $header);
                $sheet->getStyle($col . '1')->getFont()->setBold(true);
                $sheet->getStyle($col . '1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('dc3545');
                $sheet->getStyle($col . '1')->getFont()->getColor()->setRGB('FFFFFF');
                $sheet->getStyle($col . '1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $col++;
            }

            // Add data rows
            $r = 2;
            $zoneTotal = 0;
            while ($d = $zResult->fetch_assoc()) {
                $sheet->setCellValue('A' . $r, $d['zone_code']);
                $sheet->setCellValue('B' . $r, $d['ml_matic_region']);
                $sheet->setCellValue('C' . $r, $d['kp_code']);
                $sheet->setCellValue('D' . $r, floatval($d['charges']));
                $sheet->getStyle('D' . $r)->getNumberFormat()->setFormatCode('#,##0.00');
                $zoneTotal += floatval($d['charges']);
                $r++;
            }

            // Insert blank row and total
            $blank = $r;
            $r++;
            $sheet->setCellValue('C' . $r, 'Total:');
            $sheet->setCellValue('D' . $r, $zoneTotal);
            $sheet->getStyle('C' . $r . ':D' . $r)->getFont()->setBold(true);
            $sheet->getStyle('D' . $r)->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyle('C' . $r . ':D' . $r)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('343a40');
            $sheet->getStyle('C' . $r . ':D' . $r)->getFont()->getColor()->setRGB('FFFFFF');

            // Auto-size and borders
            foreach (range('A', 'D') as $cc) {
                $sheet->getColumnDimension($cc)->setAutoSize(true);
            }
            $styleArray = [
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                    ],
                ],
            ];
            $last = max($r, 1);
            $sheet->getStyle('A1:D' . $last)->applyFromArray($styleArray);

            $sheetIndex++;
        }

        // Output file
        $filename = $sanitizedPartnerLabel . '_EDI-Report.xlsx';
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }
    
    // Otherwise (not full ALL), generate single sheet as before
    $result = $conn->query($exportQuery);

    // Create new spreadsheet
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('EDI Report');
    
    // Set headers
    $headers = ['Zone', 'Region Name', 'KP Code', 'Charges'];
    $col = 'A';
    foreach ($headers as $header) {
        $sheet->setCellValue($col . '1', $header);
        $sheet->getStyle($col . '1')->getFont()->setBold(true);
        $sheet->getStyle($col . '1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('dc3545');
        $sheet->getStyle($col . '1')->getFont()->getColor()->setRGB('FFFFFF');
        $sheet->getStyle($col . '1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $col++;
    }
    
    // Add data
    $row = 2;
    $totalCharges = 0;
    while ($data = $result->fetch_assoc()) {
        $sheet->setCellValue('A' . $row, $data['zone_code']);
        $sheet->setCellValue('B' . $row, $data['ml_matic_region']);
        $sheet->setCellValue('C' . $row, $data['kp_code']);
        $sheet->setCellValue('D' . $row, floatval($data['charges']));
        $sheet->getStyle('D' . $row)->getNumberFormat()->setFormatCode('#,##0.00');
        $totalCharges += floatval($data['charges']);
        $row++;
    }

    // Insert one blank row before total
    $blankRow = $row;
    $row++; // total will be at this row

    // Add total row (label in column C, value in D)
    $sheet->setCellValue('C' . $row, 'Total:');
    $sheet->setCellValue('D' . $row, $totalCharges);
    $sheet->getStyle('C' . $row . ':D' . $row)->getFont()->setBold(true);
    $sheet->getStyle('D' . $row)->getNumberFormat()->setFormatCode('#,##0.00');
    $sheet->getStyle('C' . $row . ':D' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('343a40');
    $sheet->getStyle('C' . $row . ':D' . $row)->getFont()->getColor()->setRGB('FFFFFF');

    // Auto-size columns
    foreach (range('A', 'D') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    // Set borders
    $styleArray = [
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
            ],
        ],
    ];
    $lastRow = max($row, 1);
    $sheet->getStyle('A1:D' . $lastRow)->applyFromArray($styleArray);

    // Output file
    $filename = $sanitizedPartnerLabel . '_EDI-Report.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

// $dataQuery = "SELECT
//                 mrm.zone_code,
//                 mbp.ml_matic_region,
//                 mbp.kp_code,
//                 mkbm.branch_name,
//                 (SUM(bt.charge_to_partner) + SUM(bt.charge_to_customer)) AS charges
//             FROM
//                 mldb.billspayment_transaction AS bt
//             JOIN
//                 masterdata.region_masterfile as mrm
//                 ON
//                     bt.region_code = mrm.region_code
//                 AND
//                     mrm.zone_code NOT IN ('HO','JEW','VISMIN-MANCOMM','LNCR-MANCOMM','VISMIN-SUPPORT','LNCR-SUPPORT')
//             JOIN
//                 masterdata.branch_profile AS mbp
//                 ON
//                     bt.branch_id = mbp.branch_id
//             JOIN
//                 masterdata.kpx_branch_masterfile AS mkbm
//                 ON
//                     bt.branch_id = mkbm.branch_id
//             WHERE
//                 (DATE(bt.datetime) BETWEEN '2025-08-01' AND '2025-08-31')
//                 AND bt.status IS NULL
//                 AND bt.post_transaction = 'unposted'
//             GROUP BY mrm.zone_code, mbp.ml_matic_region, mbp.kp_code, mkbm.branch_name
//             ORDER BY mbp.ml_matic_region, mbp.kp_code";
// $dataResult = $conn->query($dataQuery);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EDI Report | <?php if($_SESSION['user_type'] === 'admin' || $_SESSION['user_type'] === 'user') echo ucfirst($_SESSION['user_type']); else echo "Guest";?></title>
    <!-- custom CSS file link  -->
    <link rel="stylesheet" href="../../../assets/css/templates/style.css?v=<?php echo time(); ?>">
    <script src="https://kit.fontawesome.com/30b908cc5a.js" crossorigin="anonymous"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="../../../assets/js/sweetalert2.all.min.js"></script>

    <link rel="icon" href="../../../images/MLW logo.png" type="image/png">
    <style>
        .scrollable-table {
            max-height: 400px;
            overflow-y: auto;
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
        }

        .scrollable-table table {
            margin-bottom: 0;
        }

        .scrollable-table thead th {
            position: sticky;
            top: 0;
            background-color: var(--bs-light);
            z-index: 10;
            border-bottom: 2px solid #dee2e6;
        }

        .scrollable-table tfoot th {
            position: sticky;
            bottom: 0;
            background-color: var(--bs-dark);
            color: white;
            z-index: 10;
            border-top: 2px solid #dee2e6;
        }

        /* Custom scrollbar styling */
        .scrollable-table::-webkit-scrollbar {
            width: 8px;
        }

        .scrollable-table::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }

        .scrollable-table::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 4px;
        }

        .scrollable-table::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
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
        <center><h3 class="mb-4">EDI REPORT</h3></center>
        <div class="container-fluid">
            <!-- Improved Responsive Filter Layout -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-danger text-white">
                    <h6 class="mb-0"><i class="fa-solid fa-filter me-2"></i>Filter Options</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <!-- Row 1: Location Filters -->
                        <div class="col-12 col-sm-6 col-lg-3">
                            <label class="form-label fw-bold">Mainzone</label>
                            <select id="mainzoneSelect" class="form-select form-select-sm" required>
                                <option value="">Select Mainzone</option>
                                <option value="ALL">ALL</option>
                                <?php 
                                    while ($row = $mainzoneResult->fetch_assoc()) {
                                        echo '<option value="' . $row['main_zone_code'] . '">' . $row['main_zone_code'] . '</option>';
                                    }
                                ?>
                            </select>
                        </div>

                        <div class="col-12 col-sm-6 col-lg-3">
                            <label class="form-label fw-bold">Zone</label>
                            <select id="zoneSelect" class="form-select form-select-sm" required>
                                <option value="">Select Zone</option>
                            </select>
                        </div>

                        <div class="col-12 col-sm-6 col-lg-3">
                            <label class="form-label fw-bold">Region</label>
                            <select id="regionSelect" class="form-select form-select-sm" required>
                                <option value="">Select Region</option>
                            </select>
                        </div>

                        <div class="col-12 col-sm-6 col-lg-3">
                            <label class="form-label fw-bold">Filter Type</label>
                            <select id="filterTypeSelect" class="form-select form-select-sm" required>
                                <option value="">Select Type</option>
                                <option value="weekly">Weekly</option>
                                <option value="monthly">Monthly</option>
                                <option value="yearly">Yearly</option>
                            </select>
                        </div>

                        <!-- Row 2: Partner and Area Filters -->
                        <div class="col-12 col-sm-6 col-lg-3">
                            <label class="form-label fw-bold">Partner Name</label>
                            <select id="partnerSelect" class="form-select form-select-sm">
                                <option value="ALL">ALL</option>
                            </select>
                        </div>

                        <div class="col-12 col-sm-6 col-lg-3">
                            <label class="form-label fw-bold">Area</label>
                            <select id="areaSelect" class="form-select form-select-sm">
                                <option value="ALL">ALL</option>
                            </select>
                        </div>

                        <!-- Row 3: Date Range -->
                        <div class="col-12 col-lg-6" id="transactionDateDiv" style="display: none;">
                            <label class="form-label fw-bold">Transaction Date</label>
                            <div class="row g-2">
                                <div class="col-6">
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text" id="startDateLabel">From</span>
                                        <input type="date" class="form-control" id="startDateInput" required>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text" id="endDateLabel">To</span>
                                        <input type="date" class="form-control" id="endDateInput" required>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Proceed Button -->
                        <div class="col-12">
                            <div class="d-grid d-sm-flex justify-content-sm-end gap-2">
                                <button type="button" class="btn btn-secondary" id="submitButton" disabled>
                                    <i class="fa-solid fa-magnifying-glass me-2"></i>Generate Report
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="container-fluid" style="display: none;">
                <div class="text-center mb-4">
                    <button class="btn btn-danger" id="exportButton" type="button">Export to Excel</button>
                </div>

                <!-- Horizontal Layout: Filter Card and Table -->
                <div class="row">
                    <!-- Filter Result Card - Left Side (30%) -->
                    <div class="col-lg-3 col-md-4">
                        <div class="card mb-4 h-100" id="filterResultCard">
                            <div class="card-header bg-danger text-white">
                                <h5 class="card-title mb-0">
                                    <i class="fa-solid fa-filter me-2"></i>
                                    Filter Result
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <div class="row mb-2">
                                        <div class="col-5"><strong>Mainzone:</strong></div>
                                        <div class="col-7"><span id="filterMainzone" class="text-muted">-</span></div>
                                    </div>
                                    <div class="row mb-2">
                                        <div class="col-5"><strong>Zone:</strong></div>
                                        <div class="col-7"><span id="filterZone" class="text-muted">-</span></div>
                                    </div>
                                    <div class="row mb-2">
                                        <div class="col-5"><strong>Region:</strong></div>
                                        <div class="col-7"><span id="filterRegion" class="text-muted">-</span></div>
                                    </div>
                                    <div class="row mb-2">
                                        <div class="col-5"><strong>Partner:</strong></div>
                                        <div class="col-7"><span id="filterPartner" class="text-muted">-</span></div>
                                    </div>
                                    <div class="row mb-2">
                                        <div class="col-5"><strong>Area:</strong></div>
                                        <div class="col-7"><span id="filterArea" class="text-muted">-</span></div>
                                    </div>
                                    <div class="row mb-2">
                                        <div class="col-5"><strong></strong>Type:</strong></div>
                                        <div class="col-7"><span id="filterType" class="text-muted">-</span></div>
                                    </div>
                                    <div class="row mb-2">
                                        <div class="col-5"><strong>From :</strong></div>
                                        <div class="col-7"><span id="filterFromDate" class="text-muted small">-</span></div>
                                    </div>
                                    <div class="row mb-2">
                                        <div class="col-5"><strong>To :</strong></div>
                                        <div class="col-7"><span id="filterToDate" class="text-muted small">-</span></div>
                                    </div>
                                </div>
                                
                                <div class="alert alert-info mb-0 p-2">
                                    <div class="small">
                                        <div><strong>Records:</strong> <span id="totalRecords">0</span></div>
                                        <!-- <div><strong>Amount:</strong> ₱<span id="totalAmount">0.00</span></div> -->
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Table - Right Side (70%) -->
                    <div class="col-lg-9 col-md-8">
                        <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
                            <table id="transactionReportTable" class="table table-bordered table-hover table-striped">
                                <thead class="table-light sticky-top">
                                    <tr>
                                        <th>Zone</th>
                                        <th>Region Name</th>
                                        <th>KP Code</th>
                                        <th>Branch Name</th>
                                        <th>Charges</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Data will be populated via JavaScript -->
                                </tbody>
                                <tfoot class="sticky-bottom table-dark">
                                    <!-- Footer will be added by JavaScript -->
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
<?php include '../../../templates/footer.php'; ?>
<script>
    $(document).ready(function() {
        // Get the select elements with proper IDs
        const mainzoneSelect = $('#mainzoneSelect');
        const zoneSelect = $('#zoneSelect');
        const regionSelect = $('#regionSelect');
        const partnerSelect = $('#partnerSelect');
        const areaSelect = $('#areaSelect');
        const filterTypeSelect = $('#filterTypeSelect');
        const transactionDateDiv = $('#transactionDateDiv');
        const submitButton = $('#submitButton');
        
        // Get date input elements
        const startDateInput = $('#startDateInput');
        const endDateInput = $('#endDateInput');
        const startDateLabel = $('#startDateLabel');
        const endDateLabel = $('#endDateLabel');
        
        // Load partners and areas on page load
        loadPartners();
        loadAreas();
        
        function loadPartners() {
            $.ajax({
                type: 'POST',
                data: { action: 'get_partners' },
                success: function(response) {
                    partnerSelect.html('<option value="ALL">ALL</option>' + response);
                },
                error: function() {
                    partnerSelect.html('<option value="ALL">ALL</option>');
                }
            });
        }
        
        function loadAreas() {
            $.ajax({
                type: 'POST',
                data: { action: 'get_areas' },
                success: function(response) {
                    areaSelect.html('<option value="ALL">ALL</option>' + response);
                },
                error: function() {
                    areaSelect.html('<option value="ALL">ALL</option>');
                }
            });
        }
        
        // Function to check if all required fields are filled
        function checkFormValidity() {
            const mainzoneValue = mainzoneSelect.val();
            const zoneValue = zoneSelect.val();
            const regionValue = regionSelect.val();
            const filterTypeValue = filterTypeSelect.val();
            
            let isValid = false;
            
            if (mainzoneValue === 'ALL') {
                // For ALL mainzone, only need region and filter type
                if (regionValue !== '' && filterTypeValue !== '') {
                    // Check if date inputs have values
                    const dateInputs = transactionDateDiv.find('input');
                    let allDatesFilled = true;
                    dateInputs.each(function() {
                        if ($(this).val() === '') {
                            allDatesFilled = false;
                        }
                    });
                    isValid = allDatesFilled;
                }
            } else {
                // For specific mainzones, need mainzone, zone, region, and filter type
                if (mainzoneValue !== '' && zoneValue !== '' && regionValue !== '' && filterTypeValue !== '') {
                    // Check if date inputs have values
                    const dateInputs = transactionDateDiv.find('input');
                    let allDatesFilled = true;
                    dateInputs.each(function() {
                        if ($(this).val() === '') {
                            allDatesFilled = false;
                        }
                    });
                    isValid = allDatesFilled;
                }
            }
            
            // Enable/disable button and change color
            if (isValid) {
                submitButton.prop('disabled', false);
                submitButton.removeClass('btn-secondary').addClass('btn-danger');
            } else {
                submitButton.prop('disabled', true);
                submitButton.removeClass('btn-danger').addClass('btn-secondary');
            }
        }
        
        // Handle mainzone selection change
        mainzoneSelect.on('change', function() {
            const selectedValue = $(this).val();

            if(selectedValue !== ''){
                if (selectedValue === 'ALL') {
                    // Show Zone and Filter Type for ALL
                    $.ajax({
                        type: 'POST',
                        data: { 
                            action: 'get_zones',
                            mainzone: selectedValue 
                        },
                        success: function(response) {
                            let options = '<option value="">Select Zone</option><option value="ALL">ALL</option>';
                            options += response;
                            options += '<option value="Showroom">SHOWROOM</option>';
                            zoneSelect.html(options);
                        },
                        error: function() {
                            zoneSelect.html('<option value="">Select Zone</option><option value="ALL">ALL</option><option value="Showroom">SHOWROOM</option>');
                        }
                    });
                }else{
                     // For any other selected mainzone, make AJAX call to get zones
                    $.ajax({
                        type: 'POST',
                        data: { 
                            action: 'get_zones',
                            mainzone: selectedValue 
                        },
                        success: function(response) {
                            let options = '<option value="">Select Zone</option><option value="ALL">ALL</option>';
                            options += response;
                            options += '<option value="Showroom">SHOWROOM</option>';
                            zoneSelect.html(options);
                        },
                        error: function() {
                            zoneSelect.html('<option value="">Select Zone</option><option value="ALL">ALL</option><option value="Showroom">SHOWROOM</option>');
                        }
                    });
                }
            }else{
                zoneSelect.html('<option value="">Select Zone</option>');
                regionSelect.html('<option value="">Select Region</option>');
            }
            checkFormValidity();
        });

        // Handle zone selection change
        zoneSelect.on('change', function() {
            const selectedZone = $(this).val();

            const mainzoneValue = mainzoneSelect.val();
            const zoneValue = selectedZone;
            const allValue = mainzoneValue + ' ' + zoneValue;
            
            // Add AJAX call to populate regions based on selected zone
            if (selectedZone !== '') {
                if(mainzoneValue !== 'ALL'){ // (LNCR, VISMIN)
                    if(selectedZone !== 'ALL'){ // (LZN,NCR,VIS,MIN,SHOWROOM), (LNCR, VISMIN)
                        if (selectedZone !== 'Showroom') { // (LZN,NCR,VIS,MIN), (LNCR, VISMIN)
                            $.ajax({
                                type: 'POST',
                                data: { 
                                    action: 'get_regions',
                                    mainzone: mainzoneValue,
                                    zone: selectedZone 
                                },
                                success: function(response) {
                                    let regionOptions = '<option value="">Select Region</option><option value="ALL">ALL</option>';
                                    regionOptions += response;
                                    regionSelect.html(regionOptions);
                                },
                                error: function() {
                                    regionSelect.html('<option value="">Select Region</option><option value="ALL">ALL</option>');
                                }
                            });

                        }else{ // (Showroom) (LNCR, VISMIN)
                            let regionOptions = '<option value="">Select Region</option><option value="'+mainzoneValue+' '+zoneValue+'">ALL</option>';
                            if(mainzoneValue === 'VISMIN'){
                                regionOptions += '<option value="VIS">VISAYAS SHOWROOM</option>';
                                regionOptions += '<option value="MIN">MINDANAO SHOWROOM</option>';
                            }
                            if(mainzoneValue === 'LNCR'){
                                regionOptions += '<option value="LZN">LUZON SHOWROOM</option>';
                                regionOptions += '<option value="NCR">NCR SHOWROOM</option>';
                            }
                            regionSelect.html(regionOptions);
                        }
                    }else{ // (ALL) (LNCR, VISMIN)
                        $.ajax({
                            type: 'POST',
                            data: { 
                                action: 'get_regions',
                                mainzone: mainzoneValue,
                                zone: zoneValue 
                            },
                            success: function(response) {
                                let regionOptions = '<option value="">Select Region</option><option value="ALL">ALL</option>';
                                regionOptions += response;
                                regionOptions += '<option value="' + mainzoneValue + ' Showroom">' + mainzoneValue + ' SHOWROOM</option>';
                                regionSelect.html(regionOptions);
                            },
                            error: function() {
                                regionSelect.html('<option value="">Select Region</option><option value="ALL">ALL</option>');
                            }
                        });
                    }
                }else{ // (ALL)
                    if(selectedZone !== 'ALL'){ // (LZN,NCR,VIS,MIN,SHOWROOM), (ALL)
                        if(selectedZone !== 'Showroom'){ // (LZN,NCR,VIS,MIN), (ALL)
                            $.ajax({
                                type: 'POST',
                                data: { 
                                    action: 'get_regions',
                                    mainzone: mainzoneValue,
                                    zone: selectedZone 
                                },
                                success: function(response) {
                                    let regionOptions = '<option value="">Select Region</option><option value="ALL">ALL</option>';
                                    regionOptions += response;
                                    if(selectedZone === 'LZN'){
                                        regionOptions += '<option value="LZNS">LUZON SHOWROOM</option>';
                                    }else if (selectedZone === 'NCR'){
                                        regionOptions += '<option value="NCRS">NCR SHOWROOM</option>';
                                    }else if (selectedZone === 'VIS'){
                                        regionOptions += '<option value="VISS">VISAYAS SHOWROOM</option>';
                                    }else if (selectedZone === 'MIN'){
                                        regionOptions += '<option value="MINS">MINDANAO SHOWROOM</option>';
                                    }
                                    regionSelect.html(regionOptions);
                                },
                                error: function() {
                                    regionSelect.html('<option value="">Select Region</option><option value="ALL">ALL</option>');
                                }
                            });

                        }else{ // (Showroom) (ALL)
                            let regionOptions = '<option value="">Select Region</option><option value="NATIONWIDE '+ zoneValue +'">ALL</option>';
                            regionOptions += '<option value="LZN">LUZON SHOWROOM</option>';
                            regionOptions += '<option value="NCR">NCR SHOWROOM</option>';
                            regionOptions += '<option value="VIS">VISAYAS SHOWROOM</option>';
                            regionOptions += '<option value="MIN">MINDANAO SHOWROOM</option>';
                            regionSelect.html(regionOptions);
                        }
                    }else{ // (ALL) (ALL)
                        $.ajax({
                            type: 'POST',
                            data: { 
                                action: 'get_regions',
                                mainzone: mainzoneValue,
                                zone: zoneValue 
                            },
                            success: function(response) {
                                let regionOptions = '<option value="">Select Region</option><option value="ALL">ALL</option>';
                                regionOptions += response;
                                regionOptions += '<option value="LZNS">LUZON SHOWROOM</option>';
                                regionOptions += '<option value="NCRS">NCR SHOWROOM</option>';
                                regionOptions += '<option value="VISS">VISAYAS SHOWROOM</option>';
                                regionOptions += '<option value="MINS">MINDANAO SHOWROOM</option>';
                                regionSelect.html(regionOptions);
                            },
                            error: function() {
                                regionSelect.html('<option value="">Select Region</option><option value="ALL">ALL</option>');
                            }
                        });
                    }
                }
            }else{
                regionSelect.html('<option value="">Select Region</option>');
            }
            checkFormValidity();
        });

        // Handle region selection change
        regionSelect.on('change', function() {
            checkFormValidity();
        });

        // Handle filter type selection change
        filterTypeSelect.on('change', function() {
            const selectedFilter = $(this).val();
            
            if (selectedFilter === 'weekly') {
                // Show date inputs for weekly
                startDateInput.attr('type', 'date').removeAttr('min max placeholder');
                endDateInput.attr('type', 'date').removeAttr('min max placeholder');
                startDateLabel.text('From');
                endDateLabel.text('To');
                transactionDateDiv.show();
            } else if (selectedFilter === 'monthly') {
                // Show month inputs for monthly
                startDateInput.attr('type', 'month').removeAttr('min max placeholder');
                endDateInput.attr('type', 'month').removeAttr('min max placeholder');
                startDateLabel.text('From');
                endDateLabel.text('To');
                transactionDateDiv.show();
            } else if (selectedFilter === 'yearly') {
                // Show year inputs for yearly
                startDateInput.attr('type', 'number').attr('min', '2000').attr('max', '2099').attr('placeholder', 'YYYY');
                endDateInput.attr('type', 'number').attr('min', '2000').attr('max', '2099').attr('placeholder', 'YYYY');
                startDateLabel.text('From');
                endDateLabel.text('To');
                transactionDateDiv.show();
            } else {
                // Hide div when no filter type selected
                transactionDateDiv.hide();
            }
            
            // Add event listeners to input fields
            startDateInput.off('change').on('change', function() {
                checkFormValidity();
            });
            endDateInput.off('change').on('change', function() {
                checkFormValidity();
            });
            
            checkFormValidity();
        });

        // Handle form submission
        submitButton.on('click', function(e) {
            e.preventDefault();
            
            // Show loading overlay
            $('#loading-overlay').show();
            
            // Get all form values
            const mainzoneValue = mainzoneSelect.val();
            const zoneValue = zoneSelect.val();
            const regionValue = regionSelect.val();
            const partnerValue = partnerSelect.val();
            const areaValue = areaSelect.val();
            const filterTypeValue = filterTypeSelect.val();
            const startDate = startDateInput.val();
            const endDate = endDateInput.val();
            
            // Update filter result card
            updateFilterResultCard(mainzoneValue, zoneValue, regionValue, partnerValue, areaValue, filterTypeValue, startDate, endDate);
            
            // Fetch and display data
            fetchReportData(mainzoneValue, zoneValue, regionValue, partnerValue, areaValue, filterTypeValue, startDate, endDate);
        });

        // Function to fetch report data via AJAX
        function fetchReportData(mainzone, zone, region, partner, area, filterType, startDate, endDate) {
            // Debug: Log the data being sent
            console.log('Sending data:', {
                action: 'get_report_data',
                mainzone: mainzone,
                zone: zone,
                region: region,
                partner: partner,
                area: area,
                filterType: filterType,
                startDate: startDate,
                endDate: endDate
            });
            
            $.ajax({
                type: 'POST',
                data: {
                    action: 'get_report_data',
                    mainzone: mainzone,
                    zone: zone,
                    region: region,
                    partner: partner,
                    area: area,
                    filterType: filterType,
                    startDate: startDate,
                    endDate: endDate
                },
                success: function(response) {
                    console.log('Raw response:', response); // Debug log
                    try {
                        const data = typeof response === 'string' ? JSON.parse(response) : response;
                        console.log('Parsed data:', data); // Debug log
                        
                        if (data.success) {
                            populateTable(data.records);
                            updateFilterTotals(data.totalRecords, data.totalAmount);
                            
                            // Show the results container - Fix: Use the correct container
                            $('.container-fluid').last().fadeIn();
                            
                            // Hide loading overlay
                            $('#loading-overlay').hide();
                        } else {
                            console.error('Server error:', data.message);
                            console.log('Query used:', data.query); // Debug log
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: data.message || 'Unknown error occurred'
                            });
                            $('#loading-overlay').hide();
                        }
                    } catch (error) {
                        console.error('Error parsing response:', error);
                        console.log('Response that failed to parse:', response);
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Failed to load report data. Please try again.'
                        });
                        $('#loading-overlay').hide();
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', error);
                    console.log('XHR:', xhr);
                    Swal.fire({
                        icon: 'error',
                        title: 'Connection Error',
                        text: 'Failed to connect to server. Please try again.'
                    });
                    $('#loading-overlay').hide();
                }
            });
        }

        // Function to populate table with data
        function populateTable(records) {
            const tableBody = $('#transactionReportTable tbody');
            tableBody.empty(); // Clear existing data
            
            if (records.length === 0) {
                tableBody.append(`
                    <tr>
                        <td colspan="5" class="text-center text-muted">No data found for the selected filters</td>
                    </tr>
                `);
                return;
            }

            let totalCharges = 0;

            records.forEach(function(record) {
                const charges = parseFloat(record.charges) || 0;
                totalCharges += charges;
                
                const row = `
                    <tr>
                        <td>${record.zone_code || '-'}</td>
                        <td>${record.ml_matic_region || '-'}</td>
                        <td>${record.kp_code || '-'}</td>
                        <td>${record.branch_name || '-'}</td>
                        <td class="text-end">₱${charges.toLocaleString('en-US', {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2
                        })}</td>
                    </tr>
                `;
                tableBody.append(row);
            });

            // Add total row in footer if it doesn't exist
            let tableFooter = $('#transactionReportTable tfoot');
            if (tableFooter.length === 0) {
                $('#transactionReportTable').append('<tfoot></tfoot>');
                tableFooter = $('#transactionReportTable tfoot');
            }
            
            tableFooter.html(`
                <tr class="table-dark">
                    <th colspan="4" class="text-end">Total:</th>
                    <th class="text-end">₱${totalCharges.toLocaleString('en-US', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    })}</th>
                </tr>
            `);
        }

        // Function to update filter result card
        function updateFilterResultCard(mainzone, zone, region, partner, area, filterType, startDate, endDate) {
            // Update mainzone
            $('#filterMainzone').text(mainzone || '-');
            
            // Update zone with proper display text
            let zoneText = zone;
            if (zone === 'ALL') {
                zoneText = 'ALL ZONES';
            } else if (zone === 'Showroom') {
                zoneText = 'SHOWROOM';
            }
            $('#filterZone').text(zoneText || '-');
            
            // Update region with proper display text
            let regionText = region;
            if (region === 'ALL') {
                regionText = 'ALL REGIONS';
            } else if (region && region.includes('SHOWROOM')) {
                regionText = region.replace(/([A-Z]+)/g, '$1 ').trim();
            }
            $('#filterRegion').text(regionText || '-');
            
            // Update partner
            let partnerText = partner === 'ALL' ? 'ALL PARTNERS' : (partnerSelect.find('option:selected').text() || partner);
            $('#filterPartner').text(partnerText || '-');
            
            // Update area
            let areaText = area === 'ALL' ? 'ALL AREAS' : area;
            $('#filterArea').text(areaText || '-');
            
            // Update filter type
            $('#filterType').text(filterType ? filterType.toUpperCase() : '-');
            
            // Update date range based on filter type
            let fromDateText = '-';
            let toDateText = '-';
            
            if (startDate && endDate) {
                if (filterType === 'weekly') {
                    fromDateText = new Date(startDate).toLocaleDateString('en-US');
                    toDateText = new Date(endDate).toLocaleDateString('en-US');
                } else if (filterType === 'monthly') {
                    const startMonth = new Date(startDate + '-01');
                    const endMonth = new Date(endDate + '-01');
                    fromDateText = startMonth.toLocaleDateString('en-US', { year: 'numeric', month: 'long' });
                    toDateText = endMonth.toLocaleDateString('en-US', { year: 'numeric', month: 'long' });
                } else if (filterType === 'yearly') {
                    fromDateText = startDate;
                    toDateText = endDate;
                }
            }
            
            // Update separate From and To spans
            $('#filterFromDate').text(fromDateText);
            $('#filterToDate').text(toDateText);
            
            // Reset totals (these will be updated when data is loaded)
            $('#totalRecords').text('0');
            $('#totalAmount').text('0.00');
        }

        // Function to update totals in the filter card
        function updateFilterTotals(recordCount, totalAmount) {
            $('#totalRecords').text(recordCount.toLocaleString());
            $('#totalAmount').text(totalAmount.toLocaleString('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }));
        }

        // Handle Export to Excel button
        $('#exportButton').on('click', function() {
            const mainzoneValue = mainzoneSelect.val();
            const zoneValue = zoneSelect.val();
            const regionValue = regionSelect.val();
            const partnerValue = partnerSelect.val();
            const areaValue = areaSelect.val();
            const filterTypeValue = filterTypeSelect.val();
            const startDate = startDateInput.val();
            const endDate = endDateInput.val();
            
            // Create a form and submit it to trigger download
            const form = $('<form>', {
                'method': 'POST',
                'action': window.location.href
            });
            
            form.append($('<input>', { 'type': 'hidden', 'name': 'action', 'value': 'export_excel' }));
            form.append($('<input>', { 'type': 'hidden', 'name': 'mainzone', 'value': mainzoneValue }));
            form.append($('<input>', { 'type': 'hidden', 'name': 'zone', 'value': zoneValue }));
            form.append($('<input>', { 'type': 'hidden', 'name': 'region', 'value': regionValue }));
            form.append($('<input>', { 'type': 'hidden', 'name': 'partner', 'value': partnerValue }));
            form.append($('<input>', { 'type': 'hidden', 'name': 'area', 'value': areaValue }));
            form.append($('<input>', { 'type': 'hidden', 'name': 'filterType', 'value': filterTypeValue }));
            form.append($('<input>', { 'type': 'hidden', 'name': 'startDate', 'value': startDate }));
            form.append($('<input>', { 'type': 'hidden', 'name': 'endDate', 'value': endDate }));
            
            $('body').append(form);
            form.submit();
            form.remove();
        });
    });
</script>
</html>