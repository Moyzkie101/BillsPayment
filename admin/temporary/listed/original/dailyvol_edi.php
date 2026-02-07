<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['admin_name'])) {
    header('location:../login_form.php');
    exit;
}

// Include database connection
include '../config/config.php';

require_once '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

// Handle Excel export
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    
    // Get the same parameters for export
    $partnerName = isset($_GET['partnerName']) ? $_GET['partnerName'] : 'All';
    $fromDate = isset($_GET['fromDate']) ? $_GET['fromDate'] : date('Y-m-01');
    $toDate = isset($_GET['toDate']) ? $_GET['toDate'] : date('Y-m-d');
    $selectedMainzone = isset($_GET['mainzone']) ? $_GET['mainzone'] : '';
    $selectedZone = isset($_GET['zone']) ? $_GET['zone'] : '';
    $selectedRegion = isset($_GET['region']) ? $_GET['region'] : '';
    
    // Format date range for display
    $formattedFromDate = date('M d, Y', strtotime($fromDate));
    $formattedToDate = date('M d, Y', strtotime($toDate));
    $formattedDateRange = $formattedFromDate . " to " . $formattedToDate;
    
    // Create new Spreadsheet object
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    // Set header row with M♦LHUILLIER logo, title, and period
    $sheet->setCellValue('A1', 'M♦LHUILLIER');
    $sheet->setCellValue('B1', 'ELECTRONIC DATA INTERCHANGE');
    $sheet->setCellValue('C1', 'Period: ' . $formattedDateRange);
    
    // Style the header row
    $sheet->getStyle('A1:C1')->applyFromArray([
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['argb' => 'FFE30000']
        ],
        'font' => [
            'bold' => true,
            'color' => ['argb' => 'FFFFFFFF'],
            'size' => 12
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical' => Alignment::VERTICAL_CENTER
        ]
    ]);
    
    // Merge cells for better layout
    $sheet->mergeCells('A1:C1');
    
    // Add some space
    $currentRow = 3;
    
    // Add table headers
    $sheet->setCellValue('A' . $currentRow, 'REGION');
    $sheet->setCellValue('B' . $currentRow, 'KPCODE');
    $sheet->setCellValue('C' . $currentRow, 'CHARGE');
    
    // Style table headers
    $sheet->getStyle('A' . $currentRow . ':C' . $currentRow)->applyFromArray([
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['argb' => 'FFFF0000']
        ],
        'font' => [
            'bold' => true,
            'color' => ['argb' => 'FFFFFFFF']
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER
        ],
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN
            ]
        ]
    ]);
    
    $currentRow++;
    
    // Fetch data using the same query logic
    $totalCharge = 0;
    
    if (!empty($fromDate) && !empty($toDate)) {
        $validFromDate = $fromDate;
        $validToDate = $toDate;
        
        if (strtotime($validFromDate) && strtotime($validToDate)) {
            $sql = "SELECT
                        mrm.region_description,
                        mbp.kp_code,
                        (SUM(bt.charge_to_partner) + SUM(bt.charge_to_customer)) AS total_charge
                    FROM 
                        mldb.billspayment_transaction AS bt
                    JOIN 
                        masterdata.branch_profile AS mbp
                        ON bt.branch_id = mbp.branch_id
                        AND NOT bt.branch_id =2607
                    JOIN
                        masterdata.region_masterfile AS mrm
                        ON bt.region_code = mrm.region_code
                    WHERE (DATE(bt.datetime) BETWEEN '" . $conn->real_escape_string($validFromDate) . "' 
                        AND '" . $conn->real_escape_string($validToDate) . "' OR DATE(bt.cancellation_date) BETWEEN '" . $conn->real_escape_string($validFromDate) . "' 
                        AND '" . $conn->real_escape_string($validToDate) . "')";
            
            if (!empty($selectedMainzone)) {
                $sql .= " AND mbp.mainzone = '" . $conn->real_escape_string($selectedMainzone) . "'";
            }
            
            if (!empty($selectedZone)) {
                $sql .= " AND mrm.zone_code = '" . $conn->real_escape_string($selectedZone) . "'";
            }
            
            if (!empty($selectedRegion)) {
                $sql .= " AND mrm.region_description = '" . $conn->real_escape_string($selectedRegion) . "'";
            }

            $sql .= " GROUP BY mrm.region_description, mbp.kp_code ORDER BY mrm.region_description, mbp.kp_code";

            $result = $conn->query($sql);
            
            if ($result && $result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $sheet->setCellValue('A' . $currentRow, $row['region_description']);
                    $sheet->setCellValue('B' . $currentRow, $row['kp_code']);
                    $sheet->setCellValue('C' . $currentRow, $row['total_charge']);

                    // Apply borders to data rows
                    $sheet->getStyle('A' . $currentRow . ':C' . $currentRow)->applyFromArray([
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => Border::BORDER_THIN
                            ]
                        ]
                    ]);
                    
                    // Format charge column to show 2 decimal places
                    $sheet->getStyle('C' . $currentRow)->getNumberFormat()->setFormatCode('#,##0.00');
                    
                    $totalCharge += $row['total_charge'];
                    $currentRow++;
                }
            }
        }
    }
    
    // Add total row
    $sheet->setCellValue('A' . $currentRow, 'TOTAL:');
    $sheet->setCellValue('B' . $currentRow, '');
    $sheet->setCellValue('C' . $currentRow, $totalCharge);
    
    // Style total row
    $sheet->getStyle('A' . $currentRow . ':C' . $currentRow)->applyFromArray([
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['argb' => 'FFFF0000']
        ],
        'font' => [
            'bold' => true,
            'color' => ['argb' => 'FFFFFFFF']
        ],
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN
            ]
        ]
    ]);
    
    // Format total charge column to show 2 decimal places
    $sheet->getStyle('C' . $currentRow)->getNumberFormat()->setFormatCode('#,##0.00');

    // Auto-size columns
    $sheet->getColumnDimension('A')->setAutoSize(true);
    $sheet->getColumnDimension('B')->setAutoSize(true);
    $sheet->getColumnDimension('C')->setAutoSize(true);
    
    // Create filename
    $excelFilename = 'edi_monthly_totals';
    if (!empty($selectedRegion)) {
        $excelFilename = 'EDI_' . preg_replace('/[^A-Za-z0-9_\-]/', '', $selectedRegion);
    } else if (!empty($selectedZone)) {
        $excelFilename = 'EDI_Zone_' . preg_replace('/[^A-Za-z0-9_\-]/', '', $selectedZone);
    } else if (!empty($selectedMainzone)) {
        $excelFilename = 'EDI_MainZone_' . preg_replace('/[^A-Za-z0-9_\-]/', '', $selectedMainzone);
    }
    
    $dateForFilename = date('Ymd', strtotime($fromDate)) . '_to_' . date('Ymd', strtotime($toDate));
    $excelFilename .= '_' . $dateForFilename . '.xls';
    
    // Set headers for download
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $excelFilename . '"');
    header('Cache-Control: max-age=0');
    
    // Create writer and output
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

// Get partner name and date range from URL parameters
$partnerName = isset($_GET['partnerName']) ? $_GET['partnerName'] : 'All';
$fromDate = isset($_GET['fromDate']) ? $_GET['fromDate'] : date('Y-m-01');
$toDate = isset($_GET['toDate']) ? $_GET['toDate'] : date('Y-m-d');

// Get filter parameters
$selectedMainzone = isset($_GET['mainzone']) ? $_GET['mainzone'] : '';
$selectedZone = isset($_GET['zone']) ? $_GET['zone'] : '';
$selectedRegion = isset($_GET['region']) ? $_GET['region'] : '';

// Format date range for display
$formattedFromDate = date('M d, Y', strtotime($fromDate));
$formattedToDate = date('M d, Y', strtotime($toDate));
$formattedDateRange = $formattedFromDate . " to " . $formattedToDate;

// Query for mainzones from branch_profile
$mainzoneQuery = "SELECT DISTINCT mainzone FROM masterdata.branch_profile WHERE mainzone IS NOT NULL AND mainzone != '' ORDER BY mainzone ASC";
$mainzoneResult = $conn->query($mainzoneQuery);
$mainzones = [];

if ($mainzoneResult && $mainzoneResult->num_rows > 0) {
    while ($row = $mainzoneResult->fetch_assoc()) {
        $mainzones[] = $row['mainzone'];
    }
}

// Get zones from region_masterfile
$zoneQuery = "SELECT DISTINCT zone_code 
              FROM masterdata.region_masterfile
              WHERE zone_code IS NOT NULL AND zone_code != '' 
              ORDER BY zone_code ASC";
              
$zoneResult = $conn->query($zoneQuery);
$zones = [];

if ($zoneResult && $zoneResult->num_rows > 0) {
    while ($row = $zoneResult->fetch_assoc()) {
        $zones[] = $row['zone_code'];
    }
}

// Get all regions from region_masterfile directly
$regionQuery = "SELECT DISTINCT region_description FROM masterdata.region_masterfile WHERE 1=1";

// Apply zone filter if selected
if (!empty($selectedZone)) {
    $regionQuery .= " AND zone_code = '" . $conn->real_escape_string($selectedZone) . "'";
}

$regionQuery .= " ORDER BY region_description ASC";
$regionResult = $conn->query($regionQuery);
$regions = [];

if ($regionResult && $regionResult->num_rows > 0) {
    while ($row = $regionResult->fetch_assoc()) {
        $regions[] = $row['region_description'];
    }
}

// If a region was previously selected but is no longer in the filtered list, reset it
if (!empty($selectedRegion) && !in_array($selectedRegion, $regions)) {
    $selectedRegion = '';
}

// Create a base filename for Excel export based on selected filters
$excelFilename = 'edi_monthly_totals';
if (!empty($selectedRegion)) {
    $excelFilename = 'EDI_' . preg_replace('/[^A-Za-z0-9_\-]/', '', $selectedRegion);
} else if (!empty($selectedZone)) {
    $excelFilename = 'EDI_Zone_' . preg_replace('/[^A-Za-z0-9_\-]/', '', $selectedZone);
} else if (!empty($selectedMainzone)) {
    $excelFilename = 'EDI_MainZone_' . preg_replace('/[^A-Za-z0-9_\-]/', '', $selectedMainzone);
}

// Add date range to filename
$dateForFilename = date('Ymd', strtotime($fromDate)) . '_to_' . date('Ymd', strtotime($toDate));
$excelFilename .= '_' . $dateForFilename . '.xls';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>EDI TOTAL</title>
    <link rel="stylesheet" href="../assets/css/billspaymentSettlement.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../assets/css/edi_styles.css?v=<?php echo time(); ?>">
    <link rel="icon" href="../images/MLW logo.png" type="image/png">
    <style>
        .edi-title-text{
            font-size: 16px;
        }
    </style>
</head>
<body>
    <!-- Filter Section -->
    <div class="filter-section">
        <form id="filterForm" method="GET" style="display: flex; gap: 15px; align-items: center; margin: 0;">
            <select name="mainzone" class="form-input-3d">
                <option value="">MainZone</option>
                <?php foreach($mainzones as $mainzone): ?>
                    <option value="<?php echo htmlspecialchars($mainzone); ?>" <?php echo ($selectedMainzone == $mainzone) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($mainzone); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <select name="zone" class="form-input-3d">
                <option value="">Zone</option>
                <?php foreach($zones as $zone): ?>
                    <option value="<?php echo htmlspecialchars($zone); ?>" <?php echo ($selectedZone == $zone) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($zone); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <select name="region" class="form-input-3d">
                <option value="">Region</option>
                <?php foreach($regions as $region): ?>
                    <option value="<?php echo htmlspecialchars($region); ?>" <?php echo ($selectedRegion == $region) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($region); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <!-- Add date range filters -->
            <div class="custom-select-wrapper" style="display:flex; flex-direction:column;">
                <label for="fromDate" class="form-label-3d">From</label>
                <input type="date" id="fromDate" name="fromDate" value="<?php echo $fromDate; ?>" class="date-input-3d">
            </div>
            <div class="custom-select-wrapper" style="display:flex; flex-direction:column;">
                <label for="toDate" class="form-label-3d">To</label>
                <input type="date" id="toDate" name="toDate" value="<?php echo $toDate; ?>" class="date-input-3d">
            </div>
            <button type="submit" class="go-button-3d">GO</button>
        </form>
    </div>
    <div class="main-content edi-container" style="max-width: 1000px; margin: 140px auto 40px; background:rgb(246, 234, 234); border-radius: 8px; padding: 30px;">
        <div class="button-container">
            <a class="back-button-3d" href="dailyvolume.php">&larr; Back</a>
            <button id="exportExcel" class="export-button-3d">EDI Loading .xlsx</button>
        </div>
        <div class="edi-header">
            <div class="edi-header-content" style="display:flex; align-items:center; justify-content:space-between;">
                <div style="display:flex; align-items:center; padding-left:10px;">
                    <img src="../images/ml.png" alt="MLHUILLIER Logo" class="edi-logo" style="height:40px; width:auto; margin-right:15px;">
                    <span class="edi-title-text">ELECTRONIC DATA INTERCHANGE</span>
                </div>
                <div style="padding-right:10px;">
                    <p class="period-text" style="margin:0;"><strong>Period:</strong> <?php echo $formattedDateRange; ?></p>
                </div>
            </div>
        </div>
        <div class="table-container">
            <table class="edi-table" id="ediTable">
                <thead>
                    <tr>
                        <th>REGION</th>
                        <th>KPCODE</th>
                        <th>CHARGE</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Initialize total charge
                    $totalCharge = 0;
                    
                    // Only execute query when filters are applied
                    if ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_GET)) {
                        // Validate date values
                        $validFromDate = !empty($fromDate) ? $fromDate : date('Y-m-01');
                        $validToDate = !empty($toDate) ? $toDate : date('Y-m-d');
                        
                        // Check if dates are valid format
                        if (strtotime($validFromDate) && strtotime($validToDate)) {
                            // Build SQL query to fetch data
                            $sql = "SELECT
                                        mrm.region_description,
                                        mbp.kp_code,
                                        (SUM(bt.charge_to_partner) + SUM(bt.charge_to_customer)) AS total_charge
                                    FROM 
                                        mldb.billspayment_transaction AS bt
                                    JOIN 
                                        masterdata.branch_profile AS mbp
                                        ON bt.branch_id = mbp.branch_id
                                        AND NOT bt.branch_id =2607
                                    JOIN
                                        masterdata.region_masterfile AS mrm
                                        ON bt.region_code = mrm.region_code
                                    WHERE (DATE(bt.datetime) BETWEEN '" . $conn->real_escape_string($validFromDate) . "' 
                                    AND '" . $conn->real_escape_string($validToDate) . "' OR DATE(bt.cancellation_date) BETWEEN '" . $conn->real_escape_string($validFromDate) . "' 
                                    AND '" . $conn->real_escape_string($validToDate) . "')";
                            
                            // Apply filters
                            if (!empty($selectedMainzone)) {
                                $sql .= " AND mbp.mainzone = '" . $conn->real_escape_string($selectedMainzone) . "'";
                            }
                            
                            if (!empty($selectedZone)) {
                                $sql .= " AND mrm.zone_code = '" . $conn->real_escape_string($selectedZone) . "'";
                            }
                            
                            if (!empty($selectedRegion)) {
                                $sql .= " AND mrm.region_description = '" . $conn->real_escape_string($selectedRegion) . "'";
                            }
                            
                            // Group by region and kpcode
                            $sql .= " GROUP BY mrm.region_description, mbp.kp_code ORDER BY mrm.region_description, mbp.kp_code";

                            // Execute query only if we have valid dates
                            $result = $conn->query($sql);
                            
                            if ($result && $result->num_rows > 0) {
                                while ($row = $result->fetch_assoc()) {
                                    $region = htmlspecialchars($row['region_description']);
                                    $kpcode = htmlspecialchars($row['kp_code']);
                                    $charge = $row['total_charge'];
                                    $totalCharge += $row['total_charge'];
                            ?>
                                    <tr>
                                        <td><?php echo $region; ?></td>
                                        <td><?php echo $kpcode; ?></td>
                                        <td><?php echo number_format($charge, 2); ?></td>
                                    </tr>
                            <?php
                                }
                                    
                                
                            } else {
                                echo "<tr><td colspan='3'>No records found for the selected filters.</td></tr>";
                            }
                        } else {
                            echo "<tr><td colspan='3'>Please apply filters to view data.</td></tr>";
                        }
                    } else {
                        echo "<tr><td colspan='3'>Please apply filters to view data.</td></tr>";
                    }
                    ?>
                    <tr style="font-weight:bold; background:#ffeaea;">
                        <td colspan="2" style="text-align:right;">TOTAL</td>
                        <td><?php echo number_format($totalCharge, 2); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    <script>
    // PhpSpreadsheet Excel export
    document.getElementById('exportExcel').addEventListener('click', function () {
        // Get current URL parameters
        var currentUrl = new URL(window.location.href);
        currentUrl.searchParams.set('export', 'excel');
        
        // Redirect to trigger export
        window.location.href = currentUrl.toString();
    });
    </script>
</body>
</html>