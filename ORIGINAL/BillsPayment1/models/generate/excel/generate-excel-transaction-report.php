<?php
// Connect to the database
include '../../../config/config.php';

// Start the session
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_type'])) {
    http_response_code(403);
    exit('Unauthorized access');
}

ignore_user_abort(true);

// Get filter parameters
$partner = isset($_GET['partner']) ? $_GET['partner'] : '';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';
$post_transaction = isset($_GET['post_transaction']) ? $_GET['post_transaction'] : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';
$source_file = isset($_GET['source_file']) ? $_GET['source_file'] : '';
$mainzone = isset($_GET['mainzone']) ? $_GET['mainzone'] : '';
$zone = isset($_GET['zone']) ? $_GET['zone'] : '';
$region = isset($_GET['region']) ? $_GET['region'] : '';
$branch = isset($_GET['branch']) ? $_GET['branch'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Build WHERE conditions (same as in main report)
$whereConditions = [];
$params = [];
$types = '';

if (!empty($search)) {
    $whereConditions[] = "(reference_no LIKE ?)";
    $searchParam = "%$search%";
    $params = array_merge($params, [$searchParam]);
    $types .= 's';
}

if (!empty($partner) && $partner !== 'All') {
    $whereConditions[] = "partner_name = ?";
    $params[] = $partner;
    $types .= 's';
}

if (!empty($start_date)) {
    $whereConditions[] = "(DATE(datetime) >= ? OR DATE(cancellation_date) >= ?)";
    $params[] = $start_date;
    $params[] = $start_date;
    $types .= 'ss';
}

if (!empty($end_date)) {
    $whereConditions[] = "(DATE(datetime) <= ? OR DATE(cancellation_date) <= ?)";
    $params[] = $end_date;
    $params[] = $end_date;
    $types .= 'ss';
}

if (!empty($post_transaction)) {
    $whereConditions[] = "post_transaction = ?";
    $params[] = $post_transaction;
    $types .= 's';
}

if (!empty($status)) {
    if ($status === 'active') {
        // Handle cases for Active status (NULL or empty values in database)
        $whereConditions[] = "status IS NULL";
    } else {
        // Handle other specific statuses
        $whereConditions[] = "status = ?";
        $params[] = $status;
        $types .= 's';
    }
}

if (!empty($source_file)) {
    $whereConditions[] = "source_file = ?";
    $params[] = $source_file;
    $types .= 's';
}

//for mainzone and zone filtering
if($mainzone ==='VISMIN'){
    if (!empty($zone)) {
        $whereConditions[] = "zone_code = ?";
        $params[] = $zone;
        $types .= 's';
    }else{
        $whereConditions[] = "zone_code IN ('VIS', 'MIN')";
    }
}elseif($mainzone ==='LNCR'){
    if (!empty($zone)) {
        $whereConditions[] = "zone_code = ?";
        $params[] = $zone;
        $types .= 's';
    }else{
        $whereConditions[] = "zone_code IN ('LZN', 'NCR')";
    }
}

if (!empty($region)) {
    $whereConditions[] = "region_code = ?";
    $params[] = $region;
    $types .= 's';
}

if (!empty($branch)) {
    $whereConditions[] = "branch_id = ?";
    $params[] = $branch;
    $types .= 's';
}

// Build WHERE clause
$whereClause = '';
if (!empty($whereConditions)) {
    $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
}

// Check database connection
if (!$conn) {
    http_response_code(500);
    exit('Database connection failed');
}

// First, get total count
$countQuery = "SELECT COUNT(*) as total FROM mldb.billspayment_transaction $whereClause";
$totalRows = 0;

if (!empty($params)) {
    $stmt = $conn->prepare($countQuery);
    if ($stmt) {
        if (!empty($types)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result) {
            $row = $result->fetch_assoc();
            $totalRows = (int)$row['total'];
        }
        $stmt->close();
    }
} else {
    $result = $conn->query($countQuery);
    if ($result) {
        $row = $result->fetch_assoc();
        $totalRows = (int)$row['total'];
    }
}

// Check if there's data to export
if ($totalRows === 0) {
    http_response_code(204);
    exit('No data found for the selected filters');
}

// 1. Determine chunk size dynamically
if ($totalRows <= 10000) {
    $chunkSize = 1000;
} elseif ($totalRows <= 100000) {
    $chunkSize = 2000;
} elseif ($totalRows <= 300000) {
    $chunkSize = 3000;
} else {
    $chunkSize = 5000;
}

// 2. Calculate total chunks and offsets
$totalChunks = ceil($totalRows / $chunkSize);
$offsets = [];
for ($i = 0; $i < $totalChunks; $i++) {
    $offsets[] = $i * $chunkSize;
}

// 3. Set PHP limits based on total chunks
$secondsPerChunk = 2; // estimated seconds per chunk
$maxExecutionTime = max(300, $totalChunks * $secondsPerChunk);

if ($totalRows <= 100000) {
    $memoryLimit = '128M';
} elseif ($totalRows <= 300000) {
    $memoryLimit = '256M';
} else {
    $memoryLimit = '512M';
}

ini_set('max_execution_time', $maxExecutionTime);
set_time_limit($maxExecutionTime);
ini_set('memory_limit', $memoryLimit);

// Set headers for CSV download
$filename = 'Transaction_Report_' . date('Y-m-d_H-i-s') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');

// Open output stream
$output = fopen('php://output', 'w');

// Add BOM for proper UTF-8 encoding in Excel
fwrite($output, "\xEF\xBB\xBF");

// Write CSV header
$headers = [
    'CAD Status',
    'Billing Invoice',
    'Transaction Status',
    'Transaction Date',
    'Cancelled Date',
    'Reference Number',
    'Branch ID',
    'Branch Name',
    'Source',
    'Partner Name',
    'Partner ID (KP7)',
    'Partner ID (KPX)',
    'GL Code',
    'GL Description',
    'Principal Amount',
    'Charge to Partner',
    'Charge to Customer'
];

fputcsv($output, $headers);

// Alternative formatNumberAsDecimal function
function formatNumberAsDecimal($value) {
    // Convert to float, format with 2 decimal places, add tab character to force text
    return "\t" . number_format((float)($value ?? 0), 2, '.', '');
}

// Fetch and write data in chunks to handle large datasets
// $offset = 0;

foreach ($offsets as $offset) {
    // Query for chunk of data
    $dataQuery = "SELECT * FROM mldb.billspayment_transaction 
                  $whereClause 
                  ORDER BY datetime DESC 
                  LIMIT $chunkSize OFFSET $offset";
    
    $data = [];
    if (!empty($params)) {
        $stmt = $conn->prepare($dataQuery);
        if ($stmt) {
            if (!empty($types)) {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $data[] = $row;
                }
            }
            $stmt->close();
        }
    } else {
        $result = $conn->query($dataQuery);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
        }
    }
    
    // Write data rows
    foreach ($data as $row) {
        $csvRow = [
            $row['post_transaction'] ?? '',
            $row['billing_invoice'] ?? '',
            ($row['status'] === null || $row['status'] === '') ? 'Active' : 'Cancelled',
            $row['datetime'] ? date('F d, Y', strtotime($row['datetime'])) : '',
            $row['cancellation_date'] ? date('F d, Y', strtotime($row['cancellation_date'])) : '',
            $row['reference_no'] ?? '',
            $row['branch_id'] ?? '',
            $row['outlet'] ?? '',
            $row['source_file'] ?? '',
            $row['partner_name'] ?? '',
            $row['partner_id'] ?? '',
            $row['partner_id_kpx'] ?? '',
            $row['mpm_gl_code'] ?? '',
            $row['mpm_gl_description'] ?? '',
            formatNumberAsDecimal($row['amount_paid']),
            formatNumberAsDecimal($row['charge_to_partner']),
            formatNumberAsDecimal($row['charge_to_customer'])
        ];
        
        fputcsv($output, $csvRow);
    }
    
    $offset += $chunkSize;
    $recordsProcessed = count($data);
    
}

// Calculate and add totals row
$totalsQuery = "SELECT 
                    COALESCE(SUM(amount_paid), 0) as total_principal,
                    COALESCE(SUM(charge_to_partner), 0) as total_partner,
                    COALESCE(SUM(charge_to_customer), 0) as total_customer
                FROM mldb.billspayment_transaction $whereClause";

$totals = ['principal' => 0, 'partner' => 0, 'customer' => 0];

if (!empty($params)) {
    $stmt = $conn->prepare($totalsQuery);
    if ($stmt) {
        if (!empty($types)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result) {
            $totalsRow = $result->fetch_assoc();
            $totals['principal'] = $totalsRow['total_principal'];
            $totals['partner'] = $totalsRow['total_partner'];
            $totals['customer'] = $totalsRow['total_customer'];
        }
        $stmt->close();
    }
} else {
    $result = $conn->query($totalsQuery);
    if ($result) {
        $totalsRow = $result->fetch_assoc();
        $totals['principal'] = $totalsRow['total_principal'];
        $totals['partner'] = $totalsRow['total_partner'];
        $totals['customer'] = $totalsRow['total_customer'];
    }
}

// Add empty row before totals
fputcsv($output, []);

// Add totals row
$totalsRowData = [
    '', '', '', '', '', '', '', '', '', '', '', '','', 'TOTAL:',
    "\t" . number_format((float)$totals['principal'], 2, '.', ''),
    "\t" . number_format((float)$totals['partner'], 2, '.', ''),
    "\t" . number_format((float)$totals['customer'], 2, '.', '')
];

fputcsv($output, $totalsRowData);

// Close output stream
fclose($output);

// Close database connection
$conn->close();
exit();
?>