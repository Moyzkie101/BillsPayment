<?php
session_start();

include '../config/config.php';
require '../vendor/autoload.php';

if (!isset($_SESSION['admin_name'])) {
    header('location:../login_form.php');
    exit();
}

use League\Csv\Reader;
use PhpParser\ParserFactory;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;


function convertExcelDate($excelDate) {
    // Excel dates are stored as serial numbers starting from January 1, 1900
    $unixDate = ($excelDate - 25569) * 86400; // convert Excel date to Unix timestamp
    return gmdate("Y-m-d", $unixDate);
}

function convertExcelTime($excelTime) {
    // Multiply the fraction of a day by the number of seconds in a day
    $seconds = $excelTime * 86400;
    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds / 60) % 60);
    $seconds = $seconds % 60;
    
    return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
}

function convertToMySQLDate($dateStr) {
    $year = substr($dateStr, 0, 4);
    $month = substr($dateStr, 4, 2);
    $day = substr($dateStr, 6, 2);

    $date = "$year-$month-$day";

    if ( $date >= date("Y-m-d")) {
        return false;
    }

    $date1 = DateTime::createFromFormat('Y-m-d', "$date");

    if ($date1) {
        return $date1->format('Y-m-d');
    }

    error_log('Invalid date format: ' . htmlspecialchars($dateStr));
    return 'Invalid Date';
}

function convertToMySQLTime($timeStr) {
    $hour = substr($timeStr, 0, 2);
    $minute = substr($timeStr, 2, 2);
    $second = substr($timeStr, 4, 2);
    $microsecond = substr($timeStr, 6, 6);

    $time = "$hour:$minute:$second.$microsecond";

    $time1 = DateTime::createFromFormat('H:i:s.u', "$time");

    if ($time1) {
        return $time1->format('H:i:s.u');
    }

    error_log('Invalid date format: ' . htmlspecialchars($timeStr));
    return 'Invalid Time';
}

if (isset($_POST['upload'])) {
    $file = $_FILES['anyFile']['tmp_name'];
    $file_name = $_FILES['anyFile']['name'];
    $file_name_array = explode('.', $file_name);
    $extension = strtolower(end($file_name_array));
    $_SESSION['option2'] = $_POST['option2']; // Persist selected file type
    $_SESSION['option1'] = $_POST['option1']; // Persist selected file type

    $stmt1 = $conn->prepare("SELECT partner_id, partner_name FROM masterdata.partner_masterfile where partner_id is not null and partner_type is not null");
    $stmt1->bind_param("s", $_POST['option1']);
    $stmt1->execute();
    $result1 = $stmt1->get_result();
    $partners1 = $result1->fetch_all(MYSQLI_ASSOC);
    $stmt1->close();

    // Reset previous valid rows
    $_SESSION['validRows'] = [];
    $_SESSION['invalidRows'] = [];

    $messages = [];
    $validRows = [];
    $invalidRows = [];
    $recordDetails = [];

    if($partners1['partner_id'] === $_POST['option1']) {
        if ($_POST['option2'] === 'text') {
            $allowed_extension = array('mcl', 'txt');
            if (in_array($extension, $allowed_extension)) {
                if (is_readable($file)) {
                    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                    //$_SESSION['lines'] = $lines;

                    if ($lines) {
                        $totalAmountHeadOffice = 0;
                        $branchTotals = [];
                        $grandtotal = 0;

                        $duplicateFound = false;
                        $recordKeys = [];
                        $newData = [];

                        foreach ($lines as $line) {
                            $error_description = '';
                            $row = explode('|', $line);

                            // Extracting fields from the row
                            $account_number = htmlspecialchars(strval(substr($line, 0, 20)));
                            $last_name = htmlspecialchars(strval(substr($line, 20, 30)));
                            $first_name = htmlspecialchars(strval(substr($line, 50, 30)));
                            $middle_name = htmlspecialchars(strval(substr($line, 80, 30)));
                            $loan_type = htmlspecialchars(strval(substr($line, 110, 15)));
                            $total_amount = htmlspecialchars(strval(substr($line, 125, 13)));

                            if (strpos($last_name, 'Ñ') !== false || strpos($first_name, 'Ñ') !== false || strpos($middle_name, 'Ñ') !== false) {
                                $date = htmlspecialchars(strval(substr($line, 139, 8)));
                                $timestamp = htmlspecialchars(strval(substr($line, 147, 15)));
                                $ref_code = htmlspecialchars(strval(substr($line, 162, 11)));
                                $unknown_code = htmlspecialchars(strval(substr($line, 173, 19)));
                                $phone_number = htmlspecialchars(strval(substr($line, 192, 20)));
                                $status1 = htmlspecialchars(strval(substr($line, 212, 1)));
                                $branch_name = htmlspecialchars(strval(substr($line, 213, 20)));
                                $status2 = htmlspecialchars(strval(substr($line, 233, 11)));
                            } else {
                                $date = htmlspecialchars(strval(substr($line, 138, 8)));
                                $timestamp = htmlspecialchars(strval(substr($line, 146, 15)));
                                if (substr($line, 161, 3) === 'BPX') {
                                    $ref_code = htmlspecialchars(strval(substr($line, 161, 30)));
                                } else {
                                    $ref_code = htmlspecialchars(strval(substr($line, 161, 11)));
                                }
                                if (substr($line, 161, 3) === 'BPX') {
                                    $unknown_code = htmlspecialchars(strval(substr($line, 178, 1)));
                                } else {
                                    $unknown_code = htmlspecialchars(strval(substr($line, 172, 19)));
                                }
                                $phone_number = htmlspecialchars(strval(substr($line, 191, 20)));
                                $status1 = htmlspecialchars(strval(substr($line, 211, 1)));
                                $branch_name = htmlspecialchars(strval(substr($line, 212, 20)));
                                $status2 = htmlspecialchars(strval(substr($line, 232, 11)));
                            }

                            $recordKey = $account_number . '|' . $ref_code;

                            // Check if record exists in the database
                            $stmt = $conn->prepare("SELECT id FROM.billspayment_feedback_mcl WHERE account_no = ? AND feedback_reference_code = ?");
                            $stmt->bind_param("ss", $account_number, $ref_code);
                            $stmt->execute();
                            $result = $stmt->get_result();

                            if ($result->num_rows > 0) {
                                $duplicateFound = true;
                            } else {
                                $newData[] = $line;
                            }
                            $stmt->close();

                            // Check for empty account number first
                            
                                // Check for other empty fields
                                $fields = [
                                    'account_number' => $account_number,
                                    'Loan Type' => $loan_type,
                                    'Amount Paid' => $total_amount,
                                    'Reference Number' => $ref_code,
                                ];  

                                foreach ($fields as $field => $value) {
                                    if (empty($value) || trim($value) === '') {
                                        $error_description .= "$field is empty. ";
                                    }
                                }
                            
                                // validates for reference code
                                if (!empty($ref_code) && trim($ref_code) !== '') {
                                    $recordKey = $ref_code . '|' . $loan_type . '|' . $total_amount . '|' . $account_number;
                                    if (in_array($recordKey, $recordDetails)) {
                                        $error_description .= "Duplicate Reference Number with same details. ";
                                    } else {
                                        $recordDetails[] = $recordKey;
                                    }
                                }

                            // Check for duplicate account number with same details
                            // $recordKey = $ref_code . '|' . $loan_type . '|' . $total_amount . '|' . $account_number;
                            // if (in_array($recordKey, $recordDetails)) {
                            //     $error_description .= "Duplicate Reference Number with same details. ";
                            // } else {
                            //     $recordDetails[] = $recordKey;
                            // }

                            $error_description = trim($error_description);
                        
                            // // Validate Lastname
                            // if (empty($last_name)) {
                            //     $error_description .= 'Last name is empty. ';
                            // }

                            // // Validate Firstname
                            // if (empty($first_name)) {
                            //     $error_description .= 'First name is empty. ';
                            // }

                            // // Validate Middlename
                            // if (empty($middle_name)) {
                            //     $error_description .= 'Middle name is empty. ';
                            // }
                            
                            // Validate date
                            if (empty($date)) {
                                $error_description .= 'Date is empty. ';
                            }  elseif (!preg_match('/^\d{8}$/', $date)) {
                                $error_description .= 'Date format is incorrect. ';
                                $date = convertToMySQLDate($date);
                            } 
                            else {
                                $date = convertToMySQLDate($date);
                            }

                            // Validate timestamp
                            // if (empty($timestamp)) {
                            //     $error_description .= 'Timestamp is empty. ';
                            // } elseif (!preg_match('/^\d{15}$/', $timestamp)) {
                            //     $error_description .= 'Timestamp format is incorrect. ';
                            //     $timestamp = convertToMySQLTime($timestamp);
                            // } else {
                            //     $timestamp = convertToMySQLTime($timestamp);
                            // }

                            $row_data = [
                                'Account Number' => $account_number,
                                'Last Name' => $last_name,
                                'First Name' => $first_name,
                                'Middle Name' => $middle_name,
                                'Loan Type' => $loan_type,
                                'Total Amount' => $total_amount,
                                'Feedback Date' => $date,
                                'Timestamp' => $timestamp,
                                'Feedback Ref. Code.' => $ref_code,
                                'Unknown Code' => $unknown_code,
                                'Phone Number' => $phone_number,
                                'Status 1' => $status1,
                                'Branch Name' => $branch_name,
                                'Status 2' => $status2,
                                'error_description' => $error_description
                                // 'error_description' => trim($error_description), // Trim trailing spaces
                            ];

                            if (empty($error_description)) {
                                $validRows[]= $row_data;

                                if (strtoupper($branch_name) === $branch_name) {
                                    $totalAmountHeadOffice += floatval($total_amount);
                                }

                                if (!isset($branchTotals[$branch_name])) {
                                    $branchTotals[$branch_name] = 0;
                                }
                                $branchTotals[$branch_name] += floatval($total_amount);

                                $_SESSION['validRows'] = $validRows;
                                
                            } else {
                                $invalidRows[] = $row_data;
                                $_SESSION['invalidRows'] = $invalidRows;
                            }
                        }

                        if ($duplicateFound) {
                            echo "<script>
                                Swal.fire({
                                    title: 'Duplicate Data Found',
                                    text: 'Some records already exist in the database. Do you want to overwrite them?',
                                    icon: 'warning',
                                    showCancelButton: true,
                                    confirmButtonText: 'Yes, Overwrite',
                                    cancelButtonText: 'Cancel'
                                }).then((result) => {
                                    if (result.isConfirmed) {
                                        overwriteData(" . json_encode($recordKeys) . ", " . json_encode($newData) . ");
                                    }
                                });
                            </script>";
                        }

                    }
                }else{
                    echo "<script>
                        Swal.fire({
                            title: 'Error!',
                            text: 'The file is not readable.',
                            icon: 'error',
                            confirmButtonText: 'OK',
                            allowOutsideClick: false
                        });
                    </script>";
                }
            }else{
                //echo "<script>Swal.fire('Error', 'Invalid file extension.', 'error');</script>";
                echo "<script>
                    Swal.fire({
                        title: 'Error!',
                        text: 'Invalid selection file.',
                        icon: 'error',
                        confirmButtonText: 'OK',
                        allowOutsideClick: false
                    }).then(() => {
                        window.location.href = 'import_billspaymentfeedback.php';
                    });
                </script>";
            }
        } elseif ($_POST['option2'] === 'excel') {

            $allowed_extension = array('xls', 'xlsx');

            if (in_array($extension, $allowed_extension)) {
                if(is_readable($file)){
                    $reader = IOFactory::createReaderForFile($file);
                    $spreadsheet = $reader->load($file);
                    $sheet = $spreadsheet->getActiveSheet();
                    $highestRow = $sheet->getHighestRow();

                    $totalAmountHeadOffice = 0;
                    $branchTotals = [];
                    $grandtotal = 0;
                    for ($row = 10; $row <= $highestRow; $row++) {
                        $error_description = '';
                        $cells = [];

                        foreach (range('B', 'R') as $col) {
                            $cells[$col] = $conn->real_escape_string(strval($sheet->getCell($col . $row)->getValue()));
                        }

                        if (array_filter($cells, 'strlen') === []) {
                            break;
                        }

                        $date = substr($cells['B'], 0, 10);
                        $time = substr($cells['B'], 10, 12);

                        $fields = [
                            // 'Control No.' => $cells['C'],
                            'Reference No.' => $cells['D'],
                            'Account No.' => $cells['G'],
                            'Amount Paid' => $cells['I'],
                            'Charge to Customer' => $cells['J'],
                            'Charge to Partner' => $cells['K']
                        ];
                        
                        foreach ($fields as $field => $value) {
                            if (empty($value) || trim($value) === '') {
                                $error_description .= "$field is empty. ";
                            }
                        }

                        // Generate a unique key for the current record
                        $recordKey = $cells['D']. '|'. $cells['G']. '|'. $cells['I']. '|'. $cells['J']. '|'. $cells['K'];
                        
                        // Check for duplicate Reference No. with same details
                        if (in_array($recordKey, $recordDetails)) {
                            $error_description .= "Duplicate Reference Number with same details. ";
                        } else {
                            $recordDetails[] = $recordKey;
                        }

                        $error_description = trim($error_description);

                        $row_data = [
                            'Date' => $date,
                            'Timestamp' => $time,
                            'Control No.' => $cells['C'],
                            'Reference No.' => $cells['D'],
                            'Payor Name' => $cells['E'],
                            'Address' => $cells['F'],
                            'Account No.' => $cells['G'],
                            'Account Name' => $cells['H'],
                            'Amount Paid' => $cells['I'],
                            'Charge to Customer' => $cells['J'],
                            'Charge to Partner' => $cells['K'],
                            'Contact No.' => $cells['L'],
                            'Other Details' => $cells['M'],
                            'ML Branch Outlet' => $cells['N'],
                            'Region' => $cells['O'],
                            'Operator' => $cells['P'],
                            'Remote Branch' => $cells['Q'],
                            'Remote Operator' => $cells['R'],
                            'error_description' => $error_description // Trim trailing spaces
                        ];

                        if (empty($error_description)) {
                            $validRows[] = $row_data;
                            
                            $total_amount = floatval(str_replace(',', '', $cells['I'])); // Remove commas for float conversion
                            if (strtoupper($cells['N']) === $cells['N']) {
                                $totalAmountHeadOffice += $total_amount;
                            }

                            if (!isset($branchTotals[$cells['N']])) {
                                $branchTotals[$cells['N']] = 0;
                            }
                            $branchTotals[$cells['N']] += $total_amount;
                            // Save valid rows in session
                            $_SESSION['validRows'] = $validRows;

                        } else {
                            $invalidRows[] = $row_data;
                            $_SESSION['invalidRows'] = $invalidRows;
                        }
                    }
                }else{
                    echo "<script>
                        Swal.fire({
                            title: 'Error!',
                            text: 'The file is not readable.',
                            icon: 'error',
                            confirmButtonText: 'OK',
                            allowOutsideClick: false
                        });
                    </script>";
                }

            }else{
                echo "<script>
                    Swal.fire({
                        title: 'Error!',
                        text: 'Invalid selection file.',
                        icon: 'error',
                        confirmButtonText: 'OK',
                        allowOutsideClick: false
                    }).then(() => {
                        window.location.href = 'import_billspaymentfeedback.php';
                    });
                </script>";
            }

        } else{
            echo '<script>
                window.onload = function() {
                    Swal.fire({
                        title: "Error",
                        text: "Please select a valid Partner and Extension.",
                        icon: "error",
                        confirmButtonText: "OK"
                    });
                }
            </script>';
        }
    }
    
}

if (isset($_POST['proceed'])) {
    $option2 = $_SESSION['option2'];
    $option1 = $_SESSION['option1'];
    $validRows = $_SESSION['validRows'] ?? [];
    $insertedCount = 0;
    $errors = [];

    if($option2 === '.mcl'){

        foreach ($validRows as $row) {
            if (!isset($row['Account Number']) || !isset($row['Last Name']) || !isset($row['First Name']) || !isset($row['Middle Name']) || !isset($row['Loan Type']) || !isset($row['Total Amount']) || !isset($row['Feedback Date']) || !isset($row['Timestamp']) || !isset($row['Feedback Ref. Code.']) || !isset($row['Unknown Code']) || !isset($row['Phone Number']) || !isset($row['Status 1']) || !isset($row['Branch Name']) || !isset($row['Status 2'])) {
                continue; // Skip rows that don't have all the necessary keys
            }

            $account_no = $row['Account Number'];
            $lastname = $row['Last Name'];
            $firstname = str_replace(array("\r", "\n", "\t", " "), "", $row['First Name']);
            $middlename = str_replace(array("\r", "\n", "\t", " "), "", $row['Middle Name']);
            $type_of_loan = str_replace(array("\r", "\n", "\t", " "), "", $row['Loan Type']);
            //$type_of_amount1 = str_replace(array("\r", "\n", "\t", " "), "", $row['Total Amount']);
            $type_of_amount = number_format(str_replace(array("\r", "\n", "\t", " "), "", $row['Total Amount']), 2, '.', ','); // Format number with 2 decimal places, dot as decimal point, and comma as thousand separator
            $date = $row['Feedback Date'];
            $timestamp = $row['Timestamp'];
            $feedback_reference_code = $row['Feedback Ref. Code.'];
            $unknown_code = $row['Unknown Code'];
            $phone_no = $row['Phone Number'];
            $status1 = $row['Status 1'];
            $branch_name = $row['Branch Name'];
            $status2 = str_replace(array("\r", "\n", "\t", " "), "", $row['Status 2']);
            $uploaded_date = date("Y-m-d H:i:s");
            $uploaded_by = $_SESSION['admin_name']; // Change this to the actual username
            $usertype = $_SESSION['user_type']; // Change this to the actual usertype

            $sql = "INSERT INTO mldb.billspayment_feedback_mcl (account_no, lastname, firstname, middlename, type_of_loan, type_of_amount, `date`, `timestamp`, feedback_reference_code, unknown_code, phone_no, `status1`, branch_name, `status2`, partner_type, confirm_status, uploaded_date, uploaded_by, user_type)
                    VALUES ('$account_no', '$lastname', '$firstname', '$middlename', '$type_of_loan', '$type_of_amount', '$date', '$timestamp', '$feedback_reference_code', '$unknown_code', '$phone_no', '$status1', '$branch_name', '$status2', '$option1','NO', '$uploaded_date', '$uploaded_by', '$usertype')";

            if ($conn->query($sql) === TRUE) {
                $insertedCount++;
            } else {
                $errors[] = "Error: " . $sql . "<br>" . $conn->error;
            }
        }

        //$response = ['success' => $insertedCount > 0, 'message' => "$insertedCount record(s) inserted successfully."];
        if ($insertedCount > 0) {
            //$response['message'] = !empty($errors) ? implode("\n", $errors) : 'No records were inserted.';
            $response = ['success' => $insertedCount > 0, 'message' => "$insertedCount record(s) inserted successfully."];
            echo json_encode($response);
            exit();

        }else{
            $response['message'] = !empty($errors) ? implode("\n", $errors) : 'No records were inserted.';
            $conn->close();
            exit();
        }

    }elseif($option2 === '.xls'){
        if (empty($validRows)) {
            echo json_encode(['success' => false, 'message' => 'No valid rows found to process.']);
            exit();
        }

        foreach ($validRows as $row) {
            // Ensure all required fields are present
            $requiredFields = [
                'Date', 'Timestamp', 'Control No.', 'Reference No.', 'Payor Name', 'Address',
                'Account No.', 'Account Name', 'Amount Paid', 'Charge to Customer', 
                'Charge to Partner', 'Contact No.', 'Other Details', 'ML Branch Outlet',
                'Region', 'Operator', 'Remote Branch', 'Remote Operator'
            ];

            $missingFields = array_diff($requiredFields, array_keys($row));
            if (!empty($missingFields)) {
                continue; // Skip rows missing required fields
            }

            // Sanitize and prepare data
            $data = array_map(function($value) use ($conn) {
                return $conn->real_escape_string($value);
            }, $row);

            // Additional fields
            $data['uploaded_date'] = date("Y-m-d H:i:s");
            $data['partner_type'] = $conn->real_escape_string($option1);
            $data['usertype'] = $conn->real_escape_string($_SESSION['user_type']);
            $data['uploaded_by'] = $conn->real_escape_string($_SESSION['admin_name'] ?? $_SESSION['user_name']);

            // Insert query
            $sql = "INSERT INTO mldb.billspayment_feedback_excel (
                feedback_date, feedback_timestamp, feedback_control_no, feedback_reference_no, payor_name,
                feedback_address, feedback_account_no, feedback_account_name, feedback_amount_of_paid, charges_of_amount_customer,
                charges_of_amount_partner, feedback_phone_no, other_details, branch_outlet, region,
                operator, remote_branch, remote_operator, partner_type, confirm_status, uploaded_date, uploaded_by, user_type
            ) VALUES (
                '{$data['Date']}', '{$data['Timestamp']}', '{$data['Control No.']}', '{$data['Reference No.']}', '{$data['Payor Name']}',
                '{$data['Address']}', '{$data['Account No.']}', '{$data['Account Name']}', '{$data['Amount Paid']}', '{$data['Charge to Customer']}',
                '{$data['Charge to Partner']}', '{$data['Contact No.']}', '{$data['Other Details']}', '{$data['ML Branch Outlet']}', '{$data['Region']}',
                '{$data['Operator']}', '{$data['Remote Branch']}', '{$data['Remote Operator']}', '{$data['partner_type']}', 'NO', '{$data['uploaded_date']}', '{$data['uploaded_by']}', '{$data['usertype']}'
            )";

            if ($conn->query($sql) === TRUE) {
                $insertedCount++;
            } else {
                $errors[] = "Error: " . $conn->error;
            }
        }

        if ($insertedCount > 0) {
            $response = ['success' => $insertedCount > 0, 'message' => "$insertedCount record(s) inserted successfully."];
            echo json_encode($response);
            //$response['message'] = !empty($errors) ? implode("\n", $errors) : 'No records were inserted.';
            // echo json_encode(['success' => true, 'message' => "$insertedCount record(s) inserted successfully."]);
        }else{
            echo json_encode(['success' => false, 'message' => $errorMessage]);
            $conn->close();
            // $response['message'] = !empty($errors) ? implode("\n", $errors) : 'No records were inserted.';
            //$errorMessage = !empty($errors) ? implode("\n", $errors) : 'No records were inserted.';
        }
        exit();
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import File | FEEDBACK</title>
    <link rel="stylesheet" href="../assets/css/billspaymentImportFile.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="../node_modules/bootstrap/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="../node_modules/bootstrap/dist/css/bootstrap.css">
    <script src="../node_modules/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../node_modules/bootstrap/dist/js/bootstrap.bundle.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <!-- Include SweetAlert JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>


    <link rel="icon" href="../images/MLW logo.png" type="image/png">
    <style>
        /* for table */
        .table-container {
            position: relative;
            max-width: 70%;
            overflow-x: auto; /* Enable horizontal scrolling */
            overflow-y: auto; /* Enable vertical scrolling */
            max-height: calc(100vh - 215px);
            margin: 20px; 
        }
        .table-container1 {
            position: relative;
            max-width: 60%;
            overflow-x: auto; /* Enable horizontal scrolling */
            overflow-y: auto; /* Enable vertical scrolling */
            max-height: calc(100vh - 500px);
            margin: 20px; 
        }

        .tabcont {
            position: relative;
            max-width: 76%;
            max-height: calc(100vh - 500px);
            margin: 20px; 
        }

        .table-container-showcadno {
            left: 25%;
            position: relative;
            height: 200px;
            max-width: 50%;
            overflow-x: auto; /* Enable horizontal scrolling */
            overflow-y: auto; /* Enable vertical scrolling */
            max-height: calc(100vh - 200px);
            margin: 3px; 
        }

        .table-container-error {
            position: relative;
            max-width: 100%;
            overflow-x: auto; /* Enable horizontal scrolling */
            overflow-y: auto; /* Enable vertical scrolling */
            max-height: calc(100vh - 200px); 
            margin: 20px; 
        }
        .file-table {
            width: 100%;
            border-collapse: collapse;
            overflow: auto;
            max-height: 855px;
        }
        .file-table th, .file-table td {
            padding: 8px 12px;
            border: 1px solid #ddd;
            text-align: left;
        }
        .file-table th {
            background-color: #f2f2f2;
        }
        thead th {
            top: 0;
            position: sticky;
            z-index: 20;

        }
        .error-row {
            background-color: #ed968c;
        }
        #showEP {
            display: none;
        }
        .custom-select-wrapper {
            position: relative;
            display: inline-block;
            margin-left: 20px;
        }
        select {
            width: 200px;
            padding: 10px;
            font-size: 16px;
            border: 2px solid #ccc;
            border-radius: 15px;
            background-color: #f9f9f9;
            -webkit-appearance: none; /* Remove default arrow in WebKit browsers */
            -moz-appearance: none; /* Remove default arrow in Firefox */
            appearance: none; /* Remove default arrow in most modern browsers */
        }
        .custom-arrow {
            position: absolute;
            top: 50%;
            right: 10px;
            width: 0;
            height: 0;
            padding: 0;
            margin-top: -2px;
            border-left: 5px solid transparent;
            border-right: 5px solid transparent;
            border-top: 5px solid #333;
            pointer-events: none;
        }
        .import-file {
            /* background-color: #3262e6; */
            height: 70px;
            width: auto;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        input[type="file"]::file-selector-button {
            border-radius: 15px;
            padding: 0 16px;
            height: 35px;
            cursor: pointer;
            background-color: white;
            border: 1px solid rgba(0, 0, 0, 0.16);
            box-shadow: 0px 1px 0px rgba(0, 0, 0, 0.05);
            margin-right: 16px;
            transition: background-color 200ms;
        }

        input[type="file"]::file-selector-button:hover {
            background-color: #f3f4f6;
        }

        input[type="file"]::file-selector-button:active {
            background-color: #e5e7eb;
        }

        .upload-btn {
            background-color: #db120b; 
            border: none;
            color: white;
            padding: 9px 15px;
            text-align: center;
            text-decoration: none;
            display: inline-block;
            font-size: 16px;
            border-radius: 20px;
            cursor: pointer;
        }
        /* loading screen */
        #loading-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(255, 255, 255, 0.7);
            z-index: 9999;
        }

        .loading-spinner {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 50px;
            height: 50px;
            border-radius: 50%;
            border: 5px solid #f3f3f3;
            border-top: 5px solid #3498db;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .usernav {
            display: flex;
            justify-content: left;
            align-items: center;
            font-size: 10px;
            font-weight: bold;
            margin: 0;
        }

        .nav-list {
            list-style: none;
            display: flex;
        }
        
        .nav-list li {
            margin-right: 20px;
        }
        
        .nav-list li a {
            text-decoration: none;
            color: #fff;
            font-size: 12px;
            font-weight: bold;
            padding: 5px 20px 5px 20px;
        }
        
        .nav-list li #user {
            text-decoration: none;
            color: #d70c0c;
            font-size: 12px;
            font-weight: bold;
            padding: 5px 20px 5px 20px;
        }
        
        .nav-list li a:hover {
            color: #d70c0c;
            background-color: whitesmoke;
        }
        
        .nav-list li #user:hover {
            color: #d70c0c;
        }

        .dropdown-btn {
            position: relative;
            display: inline-block;
            background-color: transparent;
            border: none;
            color: #fff;
            font-weight: 700;
            font-size: 12px;
            width: 150px;
            padding: 5px 20px 5px 20px;
            transition: background-color 0.3s ease;
        }
        
        .dropdown-btn:hover {
            position: relative;
            display: inline-block;
            background-color: whitesmoke;
            border: none;
            color: #d70c0c;
            width: 150px;
            font-weight: 700;
            font-size: 12px;
            padding: 5px;
            transition: background-color 0.3s ease;
        }
        .dropdown:hover .dropdown-content {
            display: block;
            z-index: 1;
            text-align: center;
            box-shadow: 0px 8px 16px 0px rgba(0, 0, 0, 0.2);
        }
        
        .logout a {
            text-decoration: none;
            background-color: transparent;
            padding: 5px 10px 5px 10px;
            color: #fff;
            font-weight: 700;
            font-size: 12px;
            transition: background-color 0.3s ease;
        }
        
        
        .logout a:hover {
            text-decoration: none;
            background-color: black;
            padding: 5px 10px 5px 10px;
            color: #d70c0c;
            transition: background-color 0.3s ease;
        }
        
        .dropdown-content {
            display: none;
            position: absolute;
            background-color: #f9f9f9;
            min-width: 150px;
            box-shadow: 0px 8px 16px 0px rgba(0, 0, 0, 0.2);
            z-index: 1;
            text-align: center;
        }
        
        .dropdown-content a {
            color: black;
            padding: 12px 16px;
            text-decoration: none;
            display: block;
            font-size: 12px;
            text-align: left;
            font-weight: bold;
        }
        
        .dropdown-content a:hover {
            background-color: #d70c0c;
            color: white;
        }
        body {
            font-family: "Open Sans", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", Helvetica, Arial, sans-serif; 
        }
        .row{
            margin-top: calc(5* var(--bs-gutter-y));
            --bs-gutter-x: 0;
            --bs-gutter-y: 0;
            display: flex;
            margin-right: calc(-0.5* var(--bs-gutter-x));
            margin-left: calc(0* var(--bs-gutter-x));
        }
    </style>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.4.0/jspdf.umd.min.js"></script>
    <script src="../node_modules/jspdf/dist/jspdf.umd.min.js"></script>
    <script src="../node_modules/jspdf/dist/jspdf.umd.js"></script>
    
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
    <center><h2>Billspayment Feedback<span style="font-size: 22px; color: red;">[Import]</span></h2></center>
    <div id="loading-overlay" style="display: none;">
        <div class="loading-spinner"></div>
    </div>
    <div class="import-file">
        <form id="uploadForm" action="" method="post" enctype="multipart/form-data">
            <div class="custom-select-wrapper">
                <?php
                    // Fetch partner options from the database
                    $stmt = $conn->prepare("SELECT partner_id, partner_name FROM masterdata.partner_masterfile where partner_type is not null");
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $partners = $result->fetch_all(MYSQLI_ASSOC);
                    $stmt->close();
                ?>
                <label for="option1">PARTNER'S: </label>
                <select name="option1" id="option1" required>
                    <option value="">Select Partner's</option>
                    <?php foreach ($partners as $partner): ?>
                        <option value="<?php echo $partner['partner_id']; ?>"><?php echo $partner['partner_name']; ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="custom-arrow"></div>
            </div>
            <div class="custom-select-wrapper">
                <label for="option2">EXTENSION FILE: </label>
                <select name="option2" id="option2" required>
                    <option value="">Select Extension File</option>
                    <option value="text">.mcl</option>
                    <option value="excel">.xls, .xlsx</option>
                </select>
                <div class="custom-arrow"></div>
            </div>
            <div class="custom-select-wrapper">
                <input type="file" id="anyFile" name="anyFile" accept="" required />
                <input type="submit" class="upload-btn" name="upload" value="Upload">
            </div>
            
        </form>
        
    </div>
    <div class="form-group row g-2">
        <div class="table-container">
            <table class="file-table">
                <thead>
                    <?php
                        if (isset($_POST['upload']) && $_POST['option1'] === $partner['partner_id']) {
                            if($_POST['option2'] === 'text'){
                                echo "<tr style='white-space: nowrap !important;'>
                                    <th>Account Number</th>
                                    <th>Loan Type</th>
                                    <th>Amount Paid</th>
                                    <th>Transaction Date</th>
                                    <th>Reference No.</th>
                                    <th></th>
                                    <th>Branch Name</th>
                                    <th>Remarks</th>
                                </tr>";
                            } elseif($_POST['option2'] === 'excel'){
                                echo '<tr>
                                    <th>Date</th>
                                    <th>Time</th>
                                    <th>Control No.</th>
                                    <th>Reference No.</th>
                                    <th>Account No.</th>
                                    <th>Amount Paid</th>
                                    <th>Charge to Customer</th>
                                    <th>Charge to Partner</th>
                                    <th>ML Branch Outlet</th>
                                    <th>Remarks</th>
                                </tr>';
                            }
                        } else{
                            echo "<tr style='white-space: nowrap !important;'>
                                <th>Account Number</th>
                                <th>Loan Type</th>
                                <th>Amount Paid</th>
                                <th>Transaction Date</th>
                                <th>Reference No.</th>
                                <th></th>
                                <th>Branch Name</th>
                                <th>Remarks</th>
                            </tr>";
                        }
                    ?>
                </thead>
                <tbody>
                    <?php
                    
                        if (isset($_POST['upload']) && $_POST['option1'] === $partner['partner_id']) {
                            if($_POST['option2'] === 'text') {
                                $validRows = $_SESSION['validRows'];
                                $invalidRows = $_SESSION['invalidRows'];
                                // $validRows = $_SESSION['validRows'] ?? [];
                                // $invalidRows = $_SESSION['invalidRows'] ?? [];

                                foreach ($invalidRows as $row) {
                                    echo "<tr style='background-color: #f8d7da;'>
                                        <td>" . htmlspecialchars($row['Account Number']) . "</td>
                                        <td>" . htmlspecialchars($row['Loan Type']) . "</td>
                                        <td>" . htmlspecialchars($row['Total Amount']) . "</td>
                                        <td>" . htmlspecialchars($row['Feedback Date']) . "</td>
                                        <td>" . htmlspecialchars($row['Feedback Ref. Code.']) . "</td>
                                        <td>" . htmlspecialchars($row['Unknown Code']) . "</td>
                                        <td>" . htmlspecialchars($row['Branch Name']) . "</td>
                                        <td>" . htmlspecialchars($row['error_description']) . "</td>
                                    </tr>";
                                }

                                foreach ($validRows as $row) {
                                    echo "<tr>
                                        <td>" . htmlspecialchars($row['Account Number']) . "</td>
                                        <td>" . htmlspecialchars($row['Loan Type']) . "</td>
                                        <td>" . htmlspecialchars($row['Total Amount']) . "</td>
                                        <td>" . htmlspecialchars($row['Feedback Date']) . "</td>
                                        <td>" . htmlspecialchars($row['Feedback Ref. Code.']) . "</td>
                                        <td>" . htmlspecialchars($row['Unknown Code']) . "</td>
                                        <td>" . htmlspecialchars($row['Branch Name']) . "</td>
                                        <td> Passed</td>
                                    </tr>";
                                }

                                //old code
                                // if (empty($error_description)) {
                                //     foreach ($validRows as $row) {
                                //         echo "<tr>
                                //             <td>" . htmlspecialchars($row['Account Number']) . "</td>
                                //             <td>" . htmlspecialchars($row['Loan Type']) . "</td>
                                //             <td>" . htmlspecialchars($row['Total Amount']) . "</td>
                                //             <td>" . htmlspecialchars($row['Feedback Date']) . "</td>
                                //             <td>" . htmlspecialchars($row['Feedback Ref. Code.']) . "</td>
                                //             <td>" . htmlspecialchars($row['Unknown Code']) . "</td>
                                //             <td>" . htmlspecialchars($row['Branch Name']) . "</td>
                                //             <td> Passed</td>
                                //         </tr>";
                                //     }  
                                // }else{
                                //     foreach ($validRows as $row) {
                                //         echo "<tr>
                                //             <td>" . htmlspecialchars($row['Account Number']) . "</td>
                                //             <td>" . htmlspecialchars($row['Loan Type']) . "</td>
                                //             <td>" . htmlspecialchars($row['Total Amount']) . "</td>
                                //             <td>" . htmlspecialchars($row['Feedback Date']) . "</td>
                                //             <td>" . htmlspecialchars($row['Feedback Ref. Code.']) . "</td>
                                //             <td>" . htmlspecialchars($row['Unknown Code']) . "</td>
                                //             <td>" . htmlspecialchars($row['Branch Name']) . "</td>
                                //             <td> Passed</td>
                                //         </tr>";
                                //     }
                                //     foreach ($invalidRows as $row) {
                                //         echo "<tr style='background-color: #f8d7da;'>
                                //             <td>" . htmlspecialchars($row['Account Number']) . "</td>
                                //             <td>" . htmlspecialchars($row['Loan Type']) . "</td>
                                //             <td>" . htmlspecialchars($row['Total Amount']) . "</td>
                                //             <td>" . htmlspecialchars($row['Feedback Date']) . "</td>
                                //             <td>" . htmlspecialchars($row['Feedback Ref. Code.']) . "</td>
                                //             <td>" . htmlspecialchars($row['Unknown Code']) . "</td>
                                //             <td>" . htmlspecialchars($row['Branch Name']) . "</td>
                                //             <td>" . htmlspecialchars($row['error_description']) . "</td>
                                //         </tr>";
                                //     }
                                // }
                            } elseif($_POST['option2'] === 'excel') {
                                $validRows = $_SESSION['validRows'];
                                $invalidRows = $_SESSION['invalidRows'];
                                // $validRows = $_SESSION['validRows'] ?? [];
                                // $invalidRows = $_SESSION['invalidRows'] ?? [];

                                if (!empty($error_description)) {
                                    //$validRows = $_SESSION['validRows'] ?? [];

                                    // // Display valid rows
                                    // foreach ($validRows as $row) {
                                    //     echo '<tr>';
                                    //     echo '<td>' . htmlspecialchars($row['Date']) . '</td>';
                                    //     echo '<td>' . htmlspecialchars($row['Timestamp']) . '</td>';
                                    //     echo '<td>' . htmlspecialchars($row['Control No.']) . '</td>';
                                    //     echo '<td>' . htmlspecialchars($row['Reference No.']) . '</td>';
                                    //     echo '<td>' . htmlspecialchars($row['Account No.']) . '</td>';
                                    //     echo '<td>' . htmlspecialchars($row['Amount Paid']) . '</td>';
                                    //     echo '<td>' . htmlspecialchars($row['Charge to Customer']) . '</td>';
                                    //     echo '<td>' . htmlspecialchars($row['Charge to Partner']) . '</td>';
                                    //     echo '<td>' . htmlspecialchars($row['ML Branch Outlet']) . '</td>';
                                    //     echo '<td>Passed</td>'; // Mark as valid
                                    //     echo '</tr>';
                                    // }

                                }else{
                                    // $validRows = $_SESSION['validRows'] ?? [];
                                    // $invalidRows = $_SESSION['invalidRows'] ?? [];

                                    // Display valid rows
                                    foreach ($validRows as $row) {
                                        echo '<tr>';
                                        echo '<td>' . htmlspecialchars($row['Date']) . '</td>';
                                        echo '<td>' . htmlspecialchars($row['Timestamp']) . '</td>';
                                        echo '<td>' . htmlspecialchars($row['Control No.']) . '</td>';
                                        echo '<td>' . htmlspecialchars($row['Reference No.']) . '</td>';
                                        echo '<td>' . htmlspecialchars($row['Account No.']) . '</td>';
                                        echo '<td>' . htmlspecialchars($row['Amount Paid']) . '</td>';
                                        echo '<td>' . htmlspecialchars($row['Charge to Customer']) . '</td>';
                                        echo '<td>' . htmlspecialchars($row['Charge to Partner']) . '</td>';
                                        echo '<td>' . htmlspecialchars($row['ML Branch Outlet']) . '</td>';
                                        echo '<td>Passed</td>'; // Mark as valid
                                        echo '</tr>';
                                    }

                                    // Display invalid rows
                                    foreach ($invalidRows as $row) {
                                        echo '<tr style="background-color: #f8d7da;">'; // Highlight invalid rows (optional)
                                        echo '<td>' . htmlspecialchars($row['Date']) . '</td>';
                                        echo '<td>' . htmlspecialchars($row['Timestamp']) . '</td>';
                                        echo '<td>' . htmlspecialchars($row['Control No.']) . '</td>';
                                        echo '<td>' . htmlspecialchars($row['Reference No.']) . '</td>';
                                        echo '<td>' . htmlspecialchars($row['Account No.']) . '</td>';
                                        echo '<td>' . htmlspecialchars($row['Amount Paid']) . '</td>';
                                        echo '<td>' . htmlspecialchars($row['Charge to Customer']) . '</td>';
                                        echo '<td>' . htmlspecialchars($row['Charge to Partner']) . '</td>';
                                        echo '<td>' . htmlspecialchars($row['ML Branch Outlet']) . '</td>';
                                        echo '<td>' . htmlspecialchars($row['error_description']) . '</td>'; // Display error description
                                        echo '</tr>';
                                    }
                                }
                            }
                            
                        } else{
                            echo '<tr>
                                <td colspan="8" style="text-align:center;"><i>Please Upload a valid file.</i></td>
                            </tr>';
                        }
                    
                    ?>
                </tbody>
            </table>
        </div>
        <div class="form-group col tabcont" align="right">
            <div class="container-fluid row"><?php
                if (isset($_POST['upload'])) {
                if (empty($error_description)) {?>
                    <div class="m-4 col-2">
                        <form method="post" enctype="multipart/form-data">
                            <input type="button" name="proceed" class="btn btn-success" value="POST" onclick="showConfirmationDialog()">
                        </form>
                    </div>
                <?php }else{
                        if(empty($error_description)){
                    ?>
                            <div class="m-4 col-3">
                                <button type="button" class="export-btn btn btn-danger" onclick="overwriteData()">Overwrite Data</button>
                            </div>
                    <?php }else{?>
                            <div class="m-4 col-3">
                                <button type="button" class="export-btn btn btn-info" onclick="exportToPDF()">Export to PDF</button>
                            </div>
                    <?php }}}?>                    
                    </div>
                    <div class ="row">
                        <table class="table-container1 table table-bordered table-hover border-black freeze-table">
                        <thead>
                            <tr><th colspan="2" style="border: none; text-align: left;">PARTNER : ( <?php echo isset($_POST['option1']) ? $_POST['option1'] : 'UNKNOWN'; ?> )</th></tr>
                            <tr><th colspan="2" style="border: none; text-align: left;">File Type : ( <b style="color: red;"><?php echo isset($_POST['option2']) ? $_POST['option2'] : 'UNKNOWN'; ?></b> )</th></tr>
                            <tr><th style="border: none;">Date of Uploaded : </th><th style="border: none; text-align: left;">( <b><?php echo isset($_POST['upload']) ? date("m-d-Y") : '--,--,----'; ?></b> )</th></tr>
                        </thead>
                        </table>
                        <div class="table-container1">
                            <table class=" table table-bordered table-hover border-black">
                                <thead>
                                    <tr><th style="border: none;">Branch Outlet</th><th style="border: none;"> Sub-Total</th></tr>
                                </thead>
                                <tbody>
                                    <?php
                                    if (isset($_POST['upload'])) {
                                        if($_POST['option1'] === 'PAGIBIG' && $_POST['option2'] === '.mcl'){
                                            if(empty($error_description)){
                                                foreach ($branchTotals as $branch => $total) {
                                                    echo '<tr>
                                                    <td>'.htmlspecialchars($branch).'</td>
                                                    <td style="text-align: right;">'.number_format($total, 2).'</td>
                                                    </tr>';
                                                        $grandtotal += $total;
                                                }
                                            }
                                            else {
                                                $invalidTotal = 0; // Initialize the grand total for invalid rows
                                            
                                                foreach ($invalidRows as $row) {
                                                    // Only display rows where 'Total Amount' is not empty and is numeric
                                                    if (!empty($row['Total Amount']) && is_numeric($row['Total Amount'])) {
                                                        echo "<tr style='background-color: #f8d7da;'>
                                                            <td style='color: red; font-weight: bold;'>". htmlspecialchars($row['Branch Name']) . "</td>
                                                            <td style='text-align: right; color: red;'>" . number_format(floatval($row['Total Amount']), 2) . "</td>
                                                        </tr>";
                                            
                                                        // Add the Total Amount to the grand total
                                                        $invalidTotal += floatval($row['Total Amount']);
                                                    }
                                                }
                                            

                                                foreach ($branchTotals as $branch => $total) {
                                                    echo '<tr>
                                                    <td>'.htmlspecialchars($branch).'</td>
                                                    <td style="text-align: right;">'.number_format($total, 2).'</td>
                                                    </tr>';
                                                        $grandtotal += $total;
                                                }

                                                
                                            }

                                            // this code will total all the amount paid if the branch name are the same bali gi usa ang mga the same og branch name //
                                            //     $invalidTotal = 0; // Initialize the grand total for invalid rows
                                            //     $branchErrorTotals = []; // Array to store totals for each branch
                                            
                                            //     foreach ($invalidRows as $row) {
                                            //         // Only process rows where 'Total Amount' is not empty and is numeric
                                            //         if (!empty($row['Total Amount']) && is_numeric($row['Total Amount'])) {
                                            //             $branchName = $row['Branch Name'];
                                            //             $amountPaid = floatval($row['Total Amount']);
                                            
                                            //             // Add the amount to the corresponding branch total
                                            //             if (!isset($branchErrorTotals[$branchName])) {
                                            //                 $branchErrorTotals[$branchName] = 0;
                                            //             }
                                            //             $branchErrorTotals[$branchName] += $amountPaid;
                                            
                                            //             // Add the amount to the overall invalid total
                                            //             $invalidTotal += $amountPaid;
                                            //         }
                                            //     }
                                            
                                            //     // Display the totals for each branch
                                            //     foreach ($branchErrorTotals as $branch => $total) {
                                            //         echo "<tr style='background-color: #f8d7da;'>
                                            //                 <td style='color: red; font-weight: bold;'>Branch: " . htmlspecialchars($branch) . "</td>
                                            //                 <td style='text-align: right; color: red;'>Total: " . number_format($total, 2) . "</td>
                                            //               </tr>";
                                            //     }
                                            
                                            //     // Display the overall invalid total
                                            //     echo "<tr style='background-color: #f8d7da;'>
                                            //             <td style='color: red; font-weight: bold;'>Overall Total for Errors:</td>
                                            //             <td style='text-align: right; color: red;'>" . number_format($invalidTotal, 2) . "</td>
                                            //           </tr>";
                                            // }
                                            
                                            
                                            
                                            //else{
                                            //     foreach ($branchTotals as $branch => $total) {
                                            //         echo '<tr>
                                            //         <td>'.htmlspecialchars($branch).'</td>
                                            //         <td style="text-align: right;">'.number_format($total, 2).'</td>
                                            //         </tr>';
                                            //             $grandtotal += $total;
                                            //     } 
                                            // }
                                        }elseif($_POST['option1'] === 'PAGIBIG' && $_POST['option2'] === '.xls'){
                                            if(!empty($error_description)){
                                                // foreach ($branchTotals as $branch => $total) {
                                                //     echo '<tr>
                                                //     <td>'.htmlspecialchars($branch).'</td>
                                                //     <td style="text-align: right;">'.number_format($total, 2).'</td>
                                                //     </tr>';
                                                //         $grandtotal += $total;
                                                // }
                                            }else{
                                                // foreach ($branchTotals as $branch => $total) {
                                                //     echo '<tr>
                                                //     <td>'.htmlspecialchars($branch).'</td>
                                                //     <td style="text-align: right;">'.number_format($total, 2).'</td>
                                                //     </tr>';
                                                //         $grandtotal += $total;
                                                // } 
                                            }
                                        }
                                    }else{
                                        echo '<tr>
                                            <td colspan="2" style="text-align:center;"><i>Please Upload a valid file.</i></td>
                                        </tr>';
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                        <!-- this will display if ther is an error data in a file -->>
                            <?php if (!empty($invalidRows)) { ?>
                                <div class="table-container1 freeze-table">
                                    <table>
                                        <tr>
                                            <th style="border: none; color: red;">Total Amount of Errors:</th>
                                            <th style="border: none; text-align: right; color: red;"><?php echo number_format($invalidTotal, 2); ?></th>
                                        </tr>
                                    </table>
                                </div>
                            <?php } ?>
                        <table class="table-container1 table table-bordered table-hover border-black freeze-table">
                            <thead>
                                <?php
                                    if (isset($_POST['upload'])) {
                                    echo '<tr>
                                        <th style="border: none;">Total Amount</th>
                                        <th style="border: none; align: right;">'. number_format($grandtotal, 2) .'</th>
                                    </tr>';
                                    }else{
                                        echo '<tr>
                                            <th style="border: none;">Total Amount</th>
                                            <th style="border: none; align: right;">0.00</th>
                                        </tr>';
                                    }
                                ?>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
<script>
    function exportToPDF() {
        window.open ('../models/exports/pdf/pag-ibig_mcltopdf.php','_blank'); // Redirect to the PHP script for PDF generation
    }
</script>
</html>
