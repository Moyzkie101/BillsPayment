<?php
// Connect to the database
include '../../../config/config.php';
require '../../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

// Start the session
session_start();

if (!isset($_SESSION['user_type'])) {
    http_response_code(403);
    exit('Unauthorized access');
}

if (isset($_POST['action']) && $_POST['action'] === 'export_excel') {
    $partner = $_POST['partner'];
    $filterType = $_POST['filterType'];
    $startDate = $_POST['startDate'];
    $endDate = $_POST['endDate'];

    // Convert date formats based on filter type
    // Keep original inputs for display decisions
    $origStartDate = $startDate;
    $origEndDate = $endDate;

    if ($filterType === 'daily') {
        // Daily: single date or date range if endDate provided
        $filterType1 = 'Daily';
        if (!empty($endDate) && $endDate !== $startDate) {
            // treat as date range
            $dateOBJ = date('F d, Y', strtotime($startDate)) . ' to ' . date('F d, Y', strtotime($endDate));
            $sqlDATE = "(
                        DATE(bt.datetime) BETWEEN '$startDate' AND '$endDate'
                        OR DATE(bt.cancellation_date) BETWEEN '$startDate' AND '$endDate'
                    )";
        } else {
            // single day
            $dateOBJ = date('F d, Y', strtotime($startDate));
            $sqlDATE = "(
                        DATE(bt.datetime) = '$startDate'
                        OR DATE(bt.cancellation_date) = '$startDate'
                    )";
        }
    } else {
        if ($filterType === 'monthly') {
            $filterType1 = 'Monthly';
            $startDate = $startDate . '-01';
            $endDate = date('Y-m-t', strtotime($endDate . '-01'));
            // If the provided start and end month are the same, display single month
            $startLabel = date('F Y', strtotime($startDate));
            $endLabel = date('F Y', strtotime($endDate));
            $dateOBJ = ($startLabel === $endLabel) ? $startLabel : ($startLabel . ' to ' . $endLabel);
        } elseif ($filterType === 'yearly') {
            $filterType1 = 'Yearly';
            $startDate = $startDate . '-01-01';
            $endDate = $endDate . '-12-31';
            // If the provided start and end year are the same, display single year
            $startLabel = date('Y', strtotime($startDate));
            $endLabel = date('Y', strtotime($endDate));
            $dateOBJ = ($startLabel === $endLabel) ? $startLabel : ($startLabel . ' to ' . $endLabel);
        } else {
            // fallback
            $filterType1 = ucfirst($filterType);
            $dateOBJ = date('F d, Y', strtotime($startDate));
        }

        $sqlDATE = "(
                        DATE(bt.datetime) BETWEEN '$startDate' AND '$endDate'
                        OR DATE(bt.cancellation_date) BETWEEN '$startDate' AND '$endDate'
                    )";
    }

    // Same query as in volume-report.php
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
                    $sqlDATE
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
                $sqlDATE
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
            mpm.status = 'ACTIVE'";
    
    // Add partner filter if not "All"
    if ($partner !== 'All') {
        $DataQuery .= " AND mpm.partner_name = '" . mysqli_real_escape_string($conn, $partner) . "'";
    }
    
    $DataQuery .= " ORDER BY mpm.partner_name";

    try {
        $DataResult = $conn->query($DataQuery);
        
        if ($DataResult) {
            $data = $DataResult->fetch_all(MYSQLI_ASSOC);
            
            // Create new Spreadsheet object
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            
            // Set document properties
            $spreadsheet->getProperties()
                ->setCreator("ML Bills Payment System")
                ->setLastModifiedBy("ML System")
                ->setTitle("Volume Report")
                ->setSubject("Bills Payment Volume Report")
                ->setDescription("Volume report generated from ML Bills Payment System");

            // Set sheet title
            $sheet->setTitle('Volume Report');
            
            // Create header with department info
            $sheet->setCellValue('A1', 'BILLS PAYMENT DEPARTMENT');
            // $sheet->mergeCells('A1:M1');

            if ($filterType === 'weekly') {
                $filterType1 = 'Daily';
                $startLabel = date('F d, Y', strtotime($startDate));
                $endLabel = date('F d, Y', strtotime($endDate));
                $dateOBJ = ($startLabel === $endLabel) ? $startLabel : ($startLabel . ' to ' . $endLabel);
            } elseif ($filterType === 'monthly') {
                $filterType1 = 'Monthly';
                $startLabel = date('F Y', strtotime($startDate));
                $endLabel = date('F Y', strtotime($endDate));
                $dateOBJ = ($startLabel === $endLabel) ? $startLabel : ($startLabel . ' to ' . $endLabel);
            } elseif ($filterType === 'yearly') {
                $filterType1 = 'Yearly';
                $startLabel = date('Y', strtotime($startDate));
                $endLabel = date('Y', strtotime($endDate));
                $dateOBJ = ($startLabel === $endLabel) ? $startLabel : ($startLabel . ' to ' . $endLabel);
            }

            $sheet->setCellValue('A2', 'VOLUME REPORT - ' . strtoupper($filterType1));
            // $sheet->mergeCells('A2:M2');

            // Add empty row for spacing
            $sheet->setCellValue('A3', '');

            // Report details in table format
            $sheet->setCellValue('A4', 'Partners');
            $sheet->setCellValue('B4', ($partner === 'All' ? 'All' : $partner));

            $sheet->setCellValue('A5', 'Generated Date');
            $sheet->setCellValue('B5', date('F d, Y h:i A'));

            $sheet->setCellValue('A6', 'Filtered Date');
            $sheet->setCellValue('B6', $dateOBJ);

            $sheet->setCellValue('A7', 'Filter Type');
            $sheet->setCellValue('B7', ucfirst($filterType));

            $sheet->setCellValue('A8', 'Generated By');
            $sheet->setCellValue('B8', 'Administrator');
            $sheet->setCellValue('A9', '');

            // Style the department header
            $departmentStyle = [
                'font' => ['bold' => true, 'size' => 14]
                // ,
                // 'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER]
            ];
            $sheet->getStyle('A1')->applyFromArray($departmentStyle);

            // Style the report type header
            $reportTypeStyle = [
                'font' => ['bold' => true, 'size' => 12]
                // ,
                // 'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER]
            ];
            $sheet->getStyle('A2')->applyFromArray($reportTypeStyle);

            // Style the report details section
            $detailsLabelStyle = [
                'font' => ['bold' => true]
                // ,
                // 'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'e9ecef']],
                // 'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
            ];
            // $detailsValueStyle = [
            //     'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
            // ];

            $sheet->getStyle('A4:A8')->applyFromArray($detailsLabelStyle);
            // $sheet->getStyle('B4:B8')->applyFromArray($detailsValueStyle);

            // Adjust column widths for details section
            $sheet->getColumnDimension('A')->setWidth(15);
            $sheet->getColumnDimension('B')->setWidth(25);

            // Table headers (moved down to accommodate new structure)
            $headerRow = 11;
            $sheet->setCellValue('A' . ($headerRow-1), 'No.');
            $sheet->mergeCells('A10:A11');
            $sheet->setCellValue('B' . ($headerRow-1), 'Partner Name');
            $sheet->mergeCells('B10:B11');
            $sheet->setCellValue('C' . ($headerRow-1), 'Bank');
            $sheet->mergeCells('C10:C11');
            $sheet->setCellValue('D' . ($headerRow-1), 'Biller\'s Name');
            $sheet->mergeCells('D10:D11');
            
            // KP7 / KPX headers
            $sheet->setCellValue('E' . ($headerRow - 1), 'KP7 / KPX');
            $sheet->mergeCells('E' . ($headerRow - 1) . ':G' . ($headerRow - 1));
            $sheet->setCellValue('E' . $headerRow, 'Vol.');
            $sheet->setCellValue('F' . $headerRow, 'Principal');
            $sheet->setCellValue('G' . $headerRow, 'Charge');
            
            // Adjustment headers
            $sheet->setCellValue('H' . ($headerRow - 1), 'Adjustment');
            $sheet->mergeCells('H' . ($headerRow - 1) . ':J' . ($headerRow - 1));
            $sheet->setCellValue('H' . $headerRow, 'Vol.');
            $sheet->setCellValue('I' . $headerRow, 'Principal');
            $sheet->setCellValue('J' . $headerRow, 'Charge');
            
            // Net headers
            $sheet->setCellValue('K' . ($headerRow - 1), 'Net');
            $sheet->mergeCells('K' . ($headerRow - 1) . ':M' . ($headerRow - 1));
            $sheet->setCellValue('K' . $headerRow, 'Vol.');
            $sheet->setCellValue('L' . $headerRow, 'Principal');
            $sheet->setCellValue('M' . $headerRow, 'Charge');
            
            // Style the headers
            $headerStyle = [
                'font' => ['bold' => true],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
            ];
            
            $sheet->getStyle('A10:M11')->applyFromArray($headerStyle);
            
            // Initialize totals
            $totals = [
                'summaryVol' => 0,
                'summaryPrincipal' => 0,
                'summaryCharge' => 0,
                'adjustmentVol' => 0,
                'adjustmentPrincipal' => 0,
                'adjustmentCharge' => 0,
                'netVol' => 0,
                'netPrincipal' => 0,
                'netCharge' => 0
            ];
            
            // Populate data
            $row = 12; // Changed from 8 to 12
            foreach ($data as $index => $rowData) {
                $sheet->setCellValue('A' . $row, $index + 1);
                $sheet->setCellValue('B' . $row, $rowData['partner_name']);
                $sheet->setCellValue('C' . $row, ''); // Bank - empty as per original
                $sheet->setCellValue('D' . $row, ''); // Biller's Name - empty as per original
                $sheet->setCellValue('E' . $row, (int)$rowData['summary_vol']);
                $sheet->setCellValue('F' . $row, (float)$rowData['summary_principal']);
                $sheet->setCellValue('G' . $row, (float)$rowData['summary_charges']);
                $sheet->setCellValue('H' . $row, (int)$rowData['adjustment_vol']);
                $sheet->setCellValue('I' . $row, (float)$rowData['adjustment_principal']);
                $sheet->setCellValue('J' . $row, (float)$rowData['adjustment_charges']);
                $sheet->setCellValue('K' . $row, (int)$rowData['net_vol']);
                $sheet->setCellValue('L' . $row, (float)$rowData['net_principal']);
                $sheet->setCellValue('M' . $row, (float)$rowData['net_charges']);
                
                // Add to totals
                $totals['summaryVol'] += $rowData['summary_vol'];
                $totals['summaryPrincipal'] += $rowData['summary_principal'];
                $totals['summaryCharge'] += $rowData['summary_charges'];
                $totals['adjustmentVol'] += $rowData['adjustment_vol'];
                $totals['adjustmentPrincipal'] += $rowData['adjustment_principal'];
                $totals['adjustmentCharge'] += $rowData['adjustment_charges'];
                $totals['netVol'] += $rowData['net_vol'];
                $totals['netPrincipal'] += $rowData['net_principal'];
                $totals['netCharge'] += $rowData['net_charges'];
                
                $row++;
            }
            
            // Add totals row
            $sheet->setCellValue('A' . $row, 'Total:');
            $sheet->mergeCells('A' . $row . ':D' . $row); // Merge A to D for "Total:" label
            $sheet->setCellValue('E' . $row, (int)$totals['summaryVol']);
            $sheet->setCellValue('F' . $row, (float)$totals['summaryPrincipal']);
            $sheet->setCellValue('G' . $row, (float)$totals['summaryCharge']);
            $sheet->setCellValue('H' . $row, (int)$totals['adjustmentVol']);
            $sheet->setCellValue('I' . $row, (float)$totals['adjustmentPrincipal']);
            $sheet->setCellValue('J' . $row, (float)$totals['adjustmentCharge']);
            $sheet->setCellValue('K' . $row, (int)$totals['netVol']);
            $sheet->setCellValue('L' . $row, (float)$totals['netPrincipal']);
            $sheet->setCellValue('M' . $row, (float)$totals['netCharge']);

            // Apply number formatting to volume columns (whole numbers)
            $volumeColumns = ['E', 'H', 'K'];
            foreach ($volumeColumns as $col) {
                $sheet->getStyle($col . '12:' . $col . $row)->getNumberFormat()->setFormatCode('#,##0');
            }

            // Apply number formatting to amount columns (2 decimal places)
            $amountColumns = ['F', 'G', 'I', 'J', 'L', 'M'];
            foreach ($amountColumns as $col) {
                $sheet->getStyle($col . '12:' . $col . $row)->getNumberFormat()->setFormatCode('#,##0.00');
            }
            
            // Style the totals row
            $totalStyle = [
                'font' => ['bold' => true],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'f8f9fa']],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT, 'vertical' => Alignment::VERTICAL_CENTER]
            ];
            $sheet->getStyle('A' . $row . ':M' . $row)->applyFromArray($totalStyle);
            
            // Auto-fit columns
            foreach (range('A', 'M') as $column) {
                $sheet->getColumnDimension($column)->setAutoSize(true);
            }
            
            // Set borders for data area
            $dataStyle = [
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
            ];
            $sheet->getStyle('A11:M' . $row)->applyFromArray($dataStyle);
            
            // Generate filename
            $filename = 'Volume_Report_' . date('Y-m-d_H-i-s') . '.xlsx';
            
            // Set headers for download
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="' . $filename . '"');
            header('Cache-Control: max-age=0');
            
            // Create Excel file
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            
            // Clean up
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
            
        } else {
            echo json_encode(['status' => 'error', 'message' => 'No data found']);
        }
    } catch (Exception $e) {
        error_log("Excel export error: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Error generating Excel file']);
    }
    exit();
}
?>