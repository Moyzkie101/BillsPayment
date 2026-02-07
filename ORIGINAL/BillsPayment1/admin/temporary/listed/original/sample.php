<?php
session_start();

include '../config/config.php';
require '../vendor/autoload.php';


if (!isset($_SESSION['admin_name'])) {
    header('location:../login_form.php');
    exit();
}

include '../models/queries/all-queries.php';
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Transaction | RECONCILIATION</title>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css?v=<?php echo time(); ?>">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
        <link rel="stylesheet" href="../node_modules/bootstrap/dist/css/bootstrap.min.css">
        <link rel="stylesheet" href="../node_modules/bootstrap/dist/css/bootstrap.css">
        <link rel="stylesheet" href="https://cdn.datatables.net/1.10.22/css/jquery.dataTables.min.css" />
        <script src="../node_modules/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
        <script src="../node_modules/bootstrap/dist/js/bootstrap.bundle.js"></script>
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <!-- Include SweetAlert JavaScript -->
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <script>
            function showConfirmationDialog() {
                Swal.fire({
                    title: 'Are you sure?',
                    text: 'Do you want to proceed with this action?',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, proceed',
                    cancelButtonText: 'No, cancel',
                    reverseButtons: false,
                    allowOutsideClick: false
                }).then((result) => {
                    if (result.isConfirmed) {
                        // User confirmed the action
                        sendAjaxRequest();
                    } else if (result.dismiss === Swal.DismissReason.cancel) {
                        // User canceled the action
                        showCancellationMessage();
                    }
                });
            }

            function sendAjaxRequest() {
                $.ajax({
                    url: '', // Specify the correct URL
                    type: 'POST',
                    data: { confirm: true }, // Add any other necessary data
                    success: handleResponse,
                    error: function() {
                        Swal.fire({
                            icon: 'error',
                            title: 'Oops!',
                            text: 'Something went wrong. Please try again later.',
                            confirmButtonText: 'Close',
                            allowOutsideClick: false
                        });
                    }
                });
            }

            function isJsonString(str) {
                try {
                    JSON.parse(str);
                } catch (e) {
                    return false;
                }
                return true;
            }

            function handleResponse(response) {

                if (isJsonString(response)) {
                    var responseData = JSON.parse(response);
                    if (responseData.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Success!',
                            text: responseData.message,
                            confirmButtonText: 'OK',
                            allowOutsideClick: false
                        }).then((result) => {
                            if (result.isConfirmed) {
                                window.location.href = 'sample.php?confirm=true';
                            }
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error!',
                            text: responseData.message,
                            confirmButtonText: 'OK',
                            allowOutsideClick: false
                        });
                    }
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error!',
                        text: 'Unexpected server response. Please try again later.',
                        confirmButtonText: 'OK',
                        allowOutsideClick: false
                    });
                }
            }

            function showCancellationMessage() {
                Swal.fire({
                    icon: 'info',
                    title: 'Cancelled',
                    text: 'Your action has been cancelled.',
                    confirmButtonText: 'OK',
                    allowOutsideClick: false
                });
            }
        </script>
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

            .table-container2 {
                position: relative;
                max-width: 75%;
                overflow-x: auto; /* Enable horizontal scrolling */
                overflow-y: auto; /* Enable vertical scrolling */
                max-height: calc(100vh - 215px);
                margin: 20px; 
            }
            .table-container3 {
                position: relative;
                max-width: 95%;
                overflow-x: auto; /* Enable horizontal scrolling */
                overflow-y: auto; /* Enable vertical scrolling */
                max-height: calc(100vh - 215px);
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
                background-color: #3262e6;
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
    </head>
    <body>
        <nav class="navbar">
            <div class="container-fluid">
                <div class="usernav">
                        <h4><i style="margin-right: 10px;" class="fa-solid fa-user-shield"></i><?php echo "Hi, " . htmlspecialchars($_SESSION['admin_name']); ?></h4>
                        <h5 style="margin-left:50px;"><?php echo "Username: ". htmlspecialchars($_SESSION['admin_email']); ?></h5>
                    </div>
                    <div class="btn-nav">
                        <ul class="nav-list">
                            <li>
                                <a href="admin_page.php">HOME</a></li>
                            <li class="dropdown">
                                <button class="dropdown-btn">Import File</button>
                                <div class="dropdown-content">
                                    <a id="user" href="billspaymentImportFile.php">BILLSPAYMENT TRANSACTION</a>
                                    <a id="user" href="import_billspaymentfeedback.php">BILLSPAYMENT FEEDBACK</a>
                                </div>
                            </li>
                            <li class="dropdown">
                                <button class="dropdown-btn">Transaction</button>
                                <div class="dropdown-content">
                                    <a id="user" href="billspaymentSettlement.php">SETTLEMENT</a>
                                    <a id="user" href="#">RECONCILIATION</a>
                                </div>
                            </li>
                            <li class="dropdown">
                                <button class="dropdown-btn">Report</button>
                                <div class="dropdown-content">
                                    <a id="user" href="billspaymentReport.php">BILLS PAYMENT</a>
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
        </nav>
        <center><h2>TRANSACTION <span style="font-size: 22px; color: red;">[RECONCILIATION]</span></h2></center>

        <div id="loading-overlay" style="display: none;">
            <div class="loading-spinner"></div>
        </div>

        <div class="import-file">
            <form id="uploadForm" action="" method="post" enctype="multipart/form-data">
                <div class="custom-select-wrapper">
                    <label for="option1">PARTNER'S: </label>
                    <select name="option1" id="option1" required>
                        <option value="">Select Partner's</option>
                        <option value="PAGIBIG">PAG-IBIG</option>
                    </select>
                    <div class="custom-arrow"></div>
                </div>
                <div class="custom-select-wrapper">
                    <label for="option1">Transaction DATE: </label>
                    <input type="date" name="date" id="date" required>
                </div>
                <div class="custom-select-wrapper">
                    <input type="submit" class="upload-btn" name="process" value="Proceed">
                </div>
            </form>
        </div>
        <div class="form-group row g-2">
            <div class="table-container2">
                <table class="file-table hover">
                    <thead>
                        <?php
                            if(isset($_POST['process'])){
                                echo '<tr>
                                    <th aria-rowindex="1" colspan="4"><center>MCL DATA</center></th>
                                    <td rowspan="2"></td>
                                    <th rowspan="1"  colspan="4"><center>EXCEL DATA</center></th>
                                    <td rowspan="2"></td>
                                </tr>';
                            }
                        ?>
                        <tr>
                            <th>Account No.</th>
                            <th>Account Name</th>
                            <th>Reference No.</th>
                            <th>Amount Paid</th>
                            <?php
                                if(isset($_POST['process'])){

                                }else{
                                    echo '<td></td>';
                                }
                            ?>
                            <th>Account No.</th>
                            <th>Reference No.</th>
                            <th>Amount Paid</th>
                            <th>Time</th>
                            <?php
                                if(isset($_POST['process'])){

                                }else{
                                    echo '<td></td>';
                                }
                            ?>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                            if (isset($_POST['process'])) {
                                if (empty($mclData) && empty($excelData)) {
                                    echo "<tr><td colspan='11'><center>No data found for the selected date.</center></td></tr>";
                                } else {
                                    $matchedCount = 0;
                                    $unmatchedCount = 0;
                                    $matchedRecords = [];
                
                                    foreach ($mclData as $mclValue) {
                                        // Format account name
                                        $mclValue['account_name'] = str_replace(array("\r", "\n", "\t", " "), "", ucwords($mclValue['lastname'])) . ", " .
                                            str_replace(array("\r", "\n", "\t", " "), "", ucwords($mclValue['firstname'])) . " " .
                                            str_replace(array("\r", "\n", "\t", " "), "", ucwords($mclValue['middlename']));
                
                                        // Check for matches in Excel data
                                        $isMatched = false;
                                        foreach ($excelData as $key => $excelValue) {
                                            if (
                                                str_replace(array("\r", "\n", "\t", " "), "", ucwords($mclValue['account_no'])) === str_replace(array("\r", "\n", "\t", " "), "", ucwords($excelValue['feedback_account_no'])) &&
                                                str_replace(array("\r", "\n", "\t", " "), "", ucwords($mclValue['feedback_reference_code'])) === str_replace(array("\r", "\n", "\t", " "), "", ucwords($excelValue['feedback_reference_no'])) && 
                                                str_replace(array("\r", "\n", "\t", " "), "", ucwords($mclValue['type_of_amount'])) === str_replace(array("\r", "\n", "\t", " "), "", ucwords($excelValue['feedback_amount_of_paid']))
                                            ) {
                                                $isMatched = true;
                                                $matchedRecords[] = $key; // Mark as matched
                                                $matchedCount++; // Increment matched counter
                                                break;
                                            }
                                        }

                                        // Display MCL data row
                                        echo "<tr>";
                                            echo "<td>{$mclValue['account_no']}</td>";
                                            echo "<td>{$mclValue['account_name']}</td>";
                                            echo "<td>{$mclValue['feedback_reference_code']}</td>";
                                            echo "<td>{$mclValue['type_of_amount']}</td>";
                                            echo '<td></td>';
                            
                                            // Display Excel match if exists
                                            if ($isMatched) {
                                                echo "<td>{$excelValue['feedback_account_no']}</td>";
                                                echo "<td>{$excelValue['feedback_reference_no']}</td>";
                                                echo "<td>{$excelValue['feedback_amount_of_paid']}</td>";
                                                echo "<td>{$excelValue['feedback_timestamp']}</td>";
                                                echo '<td></td>';
                                                echo "<td>" . ($isMatched ? "Matched" : "UnMatched") . "</td>";
                                            } else {
                                                
                                                echo "<td colspan='4'><center>No Match</center></td>";
                                                echo '<td></td>';
                                                echo "<td>" . ($isMatched ? "Matched" : "UnMatched") . "</td>";
                                            }
                                        echo "</tr>";
                            
                                        if (!$isMatched) {
                                            $unmatchedCount++; // Increment unmatched counter
                                        }
                                    }

                                    // Display unmatched Excel data
                                    foreach ($excelData as $key => $excelValue) {
                                        if (!in_array($key, $matchedRecords)) {
                                            echo "<tr>";
                                                echo "<td colspan='4'><center>No Match</center></td>";
                                                echo '<td></td>';
                                                echo "<td>{$excelValue['feedback_account_no']}</td>";
                                                echo "<td>{$excelValue['feedback_reference_no']}</td>";
                                                echo "<td>{$excelValue['feedback_amount_of_paid']}</td>";
                                                echo "<td>{$excelValue['feedback_timestamp']}</td>";
                                                echo '<td></td>';
                                                echo "<td>UnMatched</td>";
                                            echo "</tr>";

                                            $unmatchedCount++; // Increment unmatched counter

                                        }
                                    }

                                }
                            } else {
                                echo "<tr><td colspan='11'><center>No data processed. Please select a date and process.</center></td></tr>";
                            }
                        ?>
                    </tbody>
                </table>
            </div>
            <div class="form-group col tabcont" align="right">
                <?php
                if ($mclConfirmStatus === 'NO' && $excelConfirmStatus === 'NO') {
                    if($isMatched){
                        echo '<div class ="row">
                            <div class="col-2">
                                <form method="post" enctype="multipart/form-data">
                                    <input type="button" name="confirm" class="btn btn-success" value="CONFIRM" onclick="showConfirmationDialog()">
                                </form>
                            </div>
                        </div>';
                    }
                }
                ?>
                <div class ="row">
                    <table class="table-container3 table table-bordered table-hover border-black freeze-table">
                        <thead>
                            <tr><th colspan="2" style="border: none; text-align: left;">PARTNER : ( <?php echo isset($_POST['option1']) ? $_POST['option1'] : 'UNKNOWN'; ?> )</th></tr>
                            <tr><th style="border: none;">Transaction Date : </th><th style="border: none; text-align: left;">( <b><?php echo isset($_POST['date']) ? date("m-d-Y", strtotime($_POST['date'])) : '--,--,----'; ?></b> )</th></tr>
                            <tr>
                                <th style="border: none;">Date Uploaded (<b style="color: red"><i> .mcl </i></b>) : </th>
                                <th style="border: none; text-align: left;">( 
                                    <b>
                                        <?php  
                                            if(isset($mclValue['uploaded_date'])){
                                                echo date("m-d-Y", strtotime($mclValue['uploaded_date']));
                                            }else{
                                                echo '--,--,----';
                                            }
                                        ?>
                                    </b> 
                                )</th>
                            </tr>
                            <tr>
                                <th style="border: none;">Date Uploaded (<b style="color: red"><i> .xls </i></b>) : </th>
                                <th style="border: none; text-align: left;">( 
                                    <b>
                                        <?php  
                                            if(isset($excelValue['uploaded_date'])){
                                                echo date("m-d-Y", strtotime($excelValue['uploaded_date']));
                                            }else{
                                                echo '--,--,----';
                                            }
                                        ?>
                                    </b> 
                                )</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td style="border: none;">Number of Matched : </td>
                                <td style="border: none; text-align: center;"><b><?php echo $matchedCount ? $matchedCount : '0'; ?></b></td>
                            </tr>
                            <tr>
                                <td style="border: none;">Number of UnMatched (<b style="color: red"><i> .mcl </i></b>) : </td>
                                <td style="border: none; text-align: center;"><b><?php echo $unmatchedCount ? $unmatchedCount-1 : '0'; ?></b></td>
                            </tr>
                            <tr>
                                <td style="border: none;">Number of UnMatched (<b style="color: red"><i> .xls </i></b>) : </td>
                                <td style="border: none; text-align: center;"><b><?php echo $unmatchedCount ? $unmatchedCount-1 : '0'; ?></b></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </body>
</html>