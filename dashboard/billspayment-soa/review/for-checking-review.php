<?php
// Connect to the database
include '../../../config/config.php';
require '../../../vendor/autoload.php';

// Start the session
session_start();

if (isset($_SESSION['user_type'])) {
    $current_user_email = '';
    if ($_SESSION['user_type'] === 'admin' && isset($_SESSION['admin_email'])) {
        $current_user_email = $_SESSION['admin_email'];

        $_SESSION['user_name'] = $_SESSION['admin_name'];

    } elseif ($_SESSION['user_type'] === 'user' && isset($_SESSION['user_email'])) {
        $current_user_email = $_SESSION['user_email'];
        if ($_SESSION['user_email'] === 'balb01013333' || $_SESSION['user_email'] === 'pera94005055') {
            header("Location:../../../index.php");
            session_destroy();
            exit();
        }elseif($_SESSION['user_email'] !== 'cill17098209'){
            header("Location:../../../index.php");
            session_destroy();
            exit();
        }
    } else {
        header("Location:../../../index.php");
        session_destroy();
        exit();
    }
}

// Function to display a modal with a message
function displayModal($message, $isError = false)
{
    echo '
    <script>
    window.onload = function() {
        var modal = document.getElementById("messageModal");
        var message = document.getElementById("modalMessage");
        
        if (' . ($isError ? 'true' : 'false') . ') {
            message.innerHTML = \'<div class="icon-container"><div class="err-icon">&#10060;</div></div> \' + "' . $message . '";
        } else {
            message.innerHTML = \'<div class="icon-container"><div class="icon">&#10003;</div></div> \' + "' . $message . '";
        }
        modal.style.display = "block";
    }
    </script>
    ';
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['confirmBtn'])) {
        $referenceNumber = $_POST['reference'];
        $reviewedBy = $_SESSION['user_name'];
        $reviewedSignature = 'electronically signed';
        $currentDate = date("m-d-Y"); // Get the current date
        $reviewedFix_signature = 'ELVIE CILLO';

        // Retrieve prepared_by value from the database
        $preparedByQuery = "SELECT prepared_by FROM soa_transaction WHERE reference_number = '$referenceNumber'";
        $preparedByResult = mysqli_query($conn, $preparedByQuery);
        $preparedByRow = mysqli_fetch_assoc($preparedByResult);
        $preparedBy = $preparedByRow['prepared_by'];

        // Check if the prepared_by and reviewed_by have the same value
        if ($preparedBy === $_SESSION['user_name']) {
            $errorMessage = "Please assign another person to review.";
            displayModal($errorMessage, true);
        } else {
            $updateQuery = "UPDATE soa_transaction SET status = 'Reviewed', reviewed_signature = '$reviewedSignature', reviewed_by = '$reviewedBy', reviewedDate_signature = '$currentDate', reviewedFix_signature = '$reviewedFix_signature' WHERE reference_number = '$referenceNumber'";
            if (mysqli_query($conn, $updateQuery)) {
                $successMessage = "Selected row(s) updated to 'Reviewed'.";
                displayModal($successMessage);
            } else {
                $errorMessage = "Error updating transaction: " . mysqli_error($conn);
                displayModal($errorMessage, true);
            }
        }
    } elseif (isset($_POST['reviewed'])) {
        // Check if any rows are selected
        if (isset($_POST['selectedRows'])) {
            // Process the selected rows
            $selectedRows = $_POST['selectedRows'];
            $reviewedBy = $_SESSION['user_name'];
            $reviewedSignature = 'electronically signed';
            $currentDate = date("m-d-Y"); // Get the current date
            $reviewedFix_signature = 'ELVIE CILLO';

            // Check if the prepared_by and reviewed_by have the same value for any selected row
            $selectQuery = "SELECT prepared_by FROM soa_transaction WHERE reference_number IN ('" . implode("','", $selectedRows) . "') AND prepared_by = '$reviewedBy'";
            $result = mysqli_query($conn, $selectQuery);
            if ($result && mysqli_num_rows($result) > 0) {
                $errorMessage = "Please assign another person to review.";
                displayModal($errorMessage, true);
            } else {
                // Update the status of selected rows to "Reviewed"
                $updateQuery = "UPDATE soa_transaction SET status = 'Reviewed', reviewed_signature = '$reviewedSignature', reviewed_by = '$reviewedBy', reviewedDate_signature = '$currentDate', reviewedFix_signature = '$reviewedFix_signature' WHERE reference_number IN ('" . implode("','", $selectedRows) . "')";
                if (mysqli_query($conn, $updateQuery)) {
                    $successMessage = "Selected row(s) updated to 'Reviewed'.";
                    displayModal($successMessage);
                } else {
                    $errorMessage = "Error updating selected row(s): " . mysqli_error($conn);
                    displayModal($errorMessage, true);
                }
            }
        }
    } elseif (isset($_POST['multipleCancelConfirmBtn']) && !empty($_POST['cancelledBy'])) {
        // Check if any rows are selected
        if (isset($_POST['selectedRows'])) {
            // Process the selected rows
            $selectedRows = $_POST['selectedRows'];
            $cancelledBy = $_POST['cancelledBy'];
            $reasonOf_cancellation = $_POST['cancellationReason'];
            $cancelled_date = $_POST['cancel_date'];

            // Update the status of selected rows to "Cancelled" and set cancelled_by value
            $updateQuery = "UPDATE soa_transaction SET status = 'Cancelled', reasonOf_cancellation = '$reasonOf_cancellation', cancelled_by = '$cancelledBy' , cancelled_date = '$cancelled_date' WHERE reference_number IN ('" . implode("','", $selectedRows) . "')";
            if (mysqli_query($conn, $updateQuery)) {
                $successMessage = "Selected row(s) updated to 'Cancelled' \<br>'";
                $successMessage .= " Cancelled by: " . $cancelledBy;
                displayModal($successMessage);
            } else {
                $errorMessage = "Error updating selected row(s): " . mysqli_error($conn);
                displayModal($errorMessage, true);
            }
        }
    } elseif (isset($_POST['cancelConfirmBtn']) && !empty($_POST['cancelledBy'])) {
        $referenceNumber = $_POST['reference'];
        $cancelledBy = $_POST['cancelledBy'];
        $reasonOf_cancellation = $_POST['cancellationReason'];
        $cancelled_date = $_POST['cancel_date'];

        // Update the status of the selected row to "Cancelled" and set cancelled_by value
        $updateQuery = "UPDATE soa_transaction SET status = 'Cancelled', reasonOf_cancellation = '$reasonOf_cancellation', cancelled_by = '$cancelledBy', cancelled_date = '$cancelled_date' WHERE reference_number = '$referenceNumber' AND status = ''";
        if (mysqli_query($conn, $updateQuery)) {
            $successMessage = "Selected row(s) updated to 'Cancelled Status'\<br>";
            $successMessage .= " Cancelled by: " . $cancelledBy;
            displayModal($successMessage);
        } else {
            $errorMessage = "Error updating selected row(s): " . mysqli_error($conn);
            displayModal($errorMessage, true);
        }
    }
}

// Retrieve the updated data after the modifications
$query = "SELECT * FROM soa_transaction";
$result = mysqli_query($conn, $query);

if (isset($_POST['EditConfirmBtn'])) {
    // Retrieve the updated values from the form
    $referenceNumber = $_POST['reference'];
    $transactionFromDate = $_POST['transactionFromDate'];
    $transactionToDate = $_POST['transactionToDate'];
    $numOfTransaction = $_POST['numTransactions'];
    $amount = $_POST['amount-modal'];
    $addAmount = $_POST['e-addAmount'];
    $vatAmount = $_POST['e-vatAmount'];
    $netOfVat = $_POST['e-netOfVAT'];
    $wtax = $_POST['e-withholdingTax'];
    $netAmountDue = $_POST['e-netAmountDue'];

    // Prepare and execute the SQL update statement
    $sql = "UPDATE soa_transaction SET ";
    $updates = [];

    if (!empty($transactionFromDate)) {
        $updates[] = "from_date = '$transactionFromDate'";
    }

    if (!empty($transactionToDate)) {
        $updates[] = "to_date = '$transactionToDate'";
    }

    if (!empty($numOfTransaction)) {
        $updates[] = "number_of_transactions = '$numOfTransaction'";
    }

    if (!empty($amount)) {
        $updates[] = "amount = '$amount'";
    }

    if (!empty($addAmount)) {
        $updates[] = "add_amount = '$addAmount'";
    }

    if (!empty($vatAmount)) {
        $updates[] = "vat_amount = '$vatAmount'";
    }

    if (!empty($netOfVat)) {
        $updates[] = "net_of_vat = '$netOfVat'";
    }

    if (!empty($wtax)) {
        $updates[] = "withholding_tax = '$wtax'";
    }

    if (!empty($netAmountDue)) {
        $updates[] = "net_amount_due = '$netAmountDue'";
    }

    // Check if any field was edited
    if (count($updates) === 0) {
        displayModal("No field was edited.", true);
        exit; // Exit early to prevent executing the update statement
    }

    $sql .= implode(", ", $updates);
    $sql .= " WHERE reference_number = '$referenceNumber'";

    $stmt = $conn->prepare($sql);
    $stmt->execute();

    // Check if the update was successful
    if ($stmt->affected_rows > 0) {
        // Redirect or display a success message
        $successMessage = "Selected row(s) Successfully Updated";
        displayModal($successMessage);
    } else {
        // Handle the update failure
        $errorMessage = "Failed to update the record." . mysqli_error($conn);
        displayModal($errorMessage, true);
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>For Checking / Review | <?php if ($_SESSION['user_type'] === 'admin' || $_SESSION['user_type'] === 'user') echo ucfirst($_SESSION['user_type']);
                                    else echo "Guest"; ?></title>
    <!-- custom CSS file link  -->
    <link rel="stylesheet" href="../../../assets/css/templates/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../../../assets/css/user_review.css?v=<?php echo time(); ?>">
    <script src="https://kit.fontawesome.com/30b908cc5a.js" crossorigin="anonymous"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="../../../assets/js/sweetalert2.all.min.js"></script>

    <link rel="icon" href="../../../images/MLW logo.png" type="image/png">

    <style>
        .reviewed-btn {
            display: flex;
            gap: 10px;
            margin: 0;
            padding: 0;
            align-items: center;
            justify-content: flex-start;
        }

        .reviewed-btn button {
            margin: 0;
            padding: 8px 16px;
            border: none;
            border-radius: 5px;
            font-weight: bold;
            cursor: pointer;
            font-size: 14px;
        }

        .reviewed-btn #forreviewed {
            background-color: #4caf50;
            color: #fff;
        }

        .reviewed-btn #forcancelled {
            background-color: #d70c0c;
            color: #fff;
        }

        .reviewed-btn button:hover {
            opacity: 0.8;
        }

        .data-table {
            position: relative;
            max-height: 600px;
            overflow: hidden;
            border: 1px solid #ddd;
        }

        .data-table table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .data-table thead {
            display: block;
            width: 100%;
            background-color: #f8f9fa;
            position: sticky;
            top: 0;
        }

        .data-table thead tr {
            display: flex;
            width: 100%;
        }

        .data-table thead th {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: center;
            font-weight: bold;
            background-color: #f8f9fa;
            flex-shrink: 0;
        }

        .data-table tbody {
            display: block;
            width: 100%;
            max-height: 400px;
            overflow-y: auto;
            overflow-x: hidden;
        }

        .data-table tbody tr {
            display: flex;
            width: 100%;
        }

        .data-table tbody td {
            border: 1px solid #ddd;
            padding: 8px;
            flex-shrink: 0;
            overflow: hidden;
        }

        .text-truncate-custom {
            max-width: 150px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            cursor: pointer;
            display: block;
        }

        /* Compress the table to fit exactly with scrollbar */
        .container-fluid {
            padding: 10px;
            margin: 0;
            overflow: hidden;
        }

        /* Fixed column widths */
        .data-table thead th:nth-child(1),
        .data-table tbody td:nth-child(1) {
            width: 50px;
            min-width: 50px;
            max-width: 50px;
        }

        .data-table thead th:nth-child(2),
        .data-table tbody td:nth-child(2) {
            width: 120px;
            min-width: 120px;
            max-width: 120px;
        }

        .data-table thead th:nth-child(3),
        .data-table tbody td:nth-child(3) {
            width: 150px;
            min-width: 150px;
            max-width: 150px;
        }

        .data-table thead th:nth-child(4),
        .data-table tbody td:nth-child(4) {
            width: 200px;
            min-width: 200px;
            max-width: 200px;
        }

        .data-table thead th:nth-child(5),
        .data-table tbody td:nth-child(5) {
            width: 120px;
            min-width: 120px;
            max-width: 120px;
        }

        .data-table thead th:nth-child(6),
        .data-table tbody td:nth-child(6) {
            width: 120px;
            min-width: 120px;
            max-width: 120px;
        }

        .data-table thead th:nth-child(7),
        .data-table tbody td:nth-child(7) {
            width: 120px;
            min-width: 120px;
            max-width: 120px;
        }

        .data-table thead th:nth-child(8),
        .data-table tbody td:nth-child(8) {
            width: 120px;
            min-width: 120px;
            max-width: 120px;
        }

        .data-table thead th:nth-child(9),
        .data-table tbody td:nth-child(9) {
            width: 120px;
            min-width: 120px;
            max-width: 120px;
        }

        .data-table thead th:nth-child(10),
        .data-table tbody td:nth-child(10) {
            width: 120px;
            min-width: 120px;
            max-width: 120px;
        }

        .data-table thead th:nth-child(11),
        .data-table tbody td:nth-child(11) {
            width: 120px;
            min-width: 120px;
            max-width: 120px;
        }

        .data-table thead th:nth-child(12),
        .data-table tbody td:nth-child(12) {
            width: 120px;
            min-width: 120px;
            max-width: 120px;
        }

        .data-table thead th:nth-child(13),
        .data-table tbody td:nth-child(13) {
            width: 120px;
            min-width: 120px;
            max-width: 120px;
        }

        .data-table thead th:nth-child(14),
        .data-table tbody td:nth-child(17) {
            width: 120px;
            min-width: 120px;
            max-width: 120px;
        }

        .data-table thead th:nth-child(15),
        .data-table tbody td:nth-child(18) {
            width: 120px;
            min-width: 120px;
            max-width: 120px;
        }

        .data-table thead th:nth-child(16),
        .data-table tbody td:nth-child(19) {
            width: 120px;
            min-width: 120px;
            max-width: 120px;
        }

        .data-table thead th:nth-child(17),
        .data-table tbody td:nth-child(20) {
            width: 120px;
            min-width: 120px;
            max-width: 120px;
        }

        .data-table thead th:nth-child(18),
        .data-table tbody td:nth-child(21) {
            width: 120px;
            min-width: 120px;
            max-width: 120px;
        }

        .data-table thead th:nth-child(19),
        .data-table tbody td:nth-child(22) {
            width: 120px;
            min-width: 120px;
            max-width: 120px;
        }

        .data-table thead th:nth-child(20),
        .data-table tbody td:nth-child(23) {
            width: 120px;
            min-width: 120px;
            max-width: 120px;
        }

        /* Scrollbar styling */
        .data-table tbody::-webkit-scrollbar {
            width: 8px;
        }

        .data-table tbody::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }

        .data-table tbody::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 4px;
        }

        .data-table tbody::-webkit-scrollbar-thumb:hover {
            background: #555;
        }
    </style>
</head>

<body>
    <div class="main-container">
        <?php include '../../../templates/header_ui.php'; ?>
        <!-- Show and Hide Side Nav Menu -->
        <?php include '../../../templates/sidebar.php'; ?>
        <div id="loading-overlay">
            <div class="loading-spinner"></div>
        </div>
        <div class="bp-section-header" role="region" aria-label="Page title">
            <div class="bp-section-title">
                <i class="fa-solid fa-magnifying-glass-check" aria-hidden="true"></i>
                <div>
                    <h2>SOA Review</h2>
                    <div class="bp-section-sub">List of Transaction(s)</div>
                </div>
            </div>
        </div>
        <form action="" method="POST">
            <div class="bp-card container-fluid mt-3 p-4">
                <button type="submit" id="forreviewed" class="reviewed" name="reviewed">Review</button>
                <button type="button" id="forcancelled" name="cancelled">Cancel</button>
                <table class="data-table">
                    <thead>
                        <tr>
                            <!-- Table header code -->
                            <th><input type="checkbox" id="selectAllCheckbox"></th>
                            <th>Date</th>
                            <th>Reference #</th>
                            <th>Partner Name</th>
                            <th style="display:none;">Partner Tin</th>
                            <th style="display:none;">Address</th>
                            <th style="display:none;">Business Style</th>
                            <th>Service Charge</th>
                            <th>From Date</th>
                            <th>To Date</th>
                            <th style="display:none;">PO Number</th>
                            <th>Number of <br> Transactions</th>
                            <th>Amount</th>
                            <th>VAT Amount</th>
                            <th>Net of VAT</th>
                            <th>Withholding <br> Tax</th>
                            <th>Net Amount <br> Due</th>
                            <!-- <th>Prepared By</th> original -->
                            <th>Created By</th>
                            <th>Reviewed By</th>
                            <!-- <th>Noted By</th> original -->
                            <th>Approved By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = mysqli_fetch_assoc($result)): ?>
                            <?php if ($row['status'] === 'Prepared') { ?>
                                <?php
                                $referenceNumber = $row['reference_number'];
                                $isSelected = isset($_GET['reference_number']) && $_GET['reference_number'] === $referenceNumber;
                                $rowClass = $isSelected ? "selected-row" : "";
                                ?>

                                <tr class="table-row <?php echo $rowClass; ?>" onclick="selectRow(this, '<?php echo $referenceNumber; ?>')">
                                    <td style="text-align:center;"><input type="checkbox" class="row-checkbox" name="selectedRows[]" value="<?php echo $referenceNumber; ?>"></td>
                                    <td style="text-align:center;"><?php echo date('F j, Y', strtotime($row['date'])); ?></td>

                                    <td style="text-align:center;" class="text-truncate-custom"><?php echo $row['reference_number']; ?></td>

                                    <td style="text-align:left;" class="text-truncate-custom"><?php echo $row['partner_Name']; ?></td>
                                    <td style="display:none;"><?php echo $row['partner_Tin']; ?></td>
                                    <td style="display:none;"><?php echo $row['address']; ?></td>
                                    <td style="display:none;"><?php echo $row['business_style']; ?></td>
                                    <td style="text-align:center;"><?php echo $row['service_charge']; ?></td>
                                    <td style="text-align:center;"><?php echo date('F j, Y', strtotime($row['from_date'])); ?></td>
                                    <td style="text-align:center;"><?php echo date('F j, Y', strtotime($row['to_date'])); ?></td>
                                    <td style="display:none;"><?php echo $row['po_number']; ?></td>
                                    <td style="text-align:center;"><?php echo number_format($row['number_of_transactions']); ?></td>
                                    <td style="text-align:right;"><?php echo number_format($row['amount'], 2); ?></td>
                                    <td style="display:none;"><?php echo $row['add_amount']; ?></td>
                                    <td style="display:none;"><?php echo $row['formula']; ?></td>
                                    <td style="display:none;"><?php echo $row['formula_withheld']; ?></td>
                                    <td style="text-align:right;"><?php echo $row['vat_amount']; ?></td>
                                    <td style="text-align:right;"><?php echo $row['net_of_vat']; ?></td>
                                    <td style="text-align:right;"><?php echo $row['withholding_tax']; ?></td>
                                    <td style="text-align:right;"><?php echo $row['net_amount_due']; ?></td>
                                    <td style="text-align:center;" class="text-truncate-custom"><?php echo $row['prepared_by']; ?></td>
                                    <td style="text-align:left;" class="text-truncate-custom"><?php echo $row['reviewed_by']; ?></td>
                                    <td style="text-align:left;" class="text-truncate-custom"><?php echo $row['noted_by']; ?></td>
                                </tr>
                        <?php }
                        endwhile; ?>
                    </tbody>
                </table>
            </div>

    </div>
    <!-- Add the confirm modal -->
    <div id="confirmModal" class="modal">
        <div class="modal-content">
            <input type="hidden" id="cancelledBy" name="cancelledBy" placeholder="Enter date" value="<?php echo $_SESSION['user_name'] ?? $_SESSION['admin_name'];?>">
            <div class="modal-header">
                <h3>PLEASE REVIEW THE TRANSACTION!</h3>
            </div>
            <hr class="header-line">
            <div class="modal-fields">
                <div class="t-date">
                    <label for="date">Date:</label>
                    <input type="text" id="date" name="date" value="" readonly><br>
                </div>
                <div class="reference-div">
                    <label for="reference">Reference Number:</label>
                    <input type="text" id="reference" name="reference" value="<?php if (isset($_POST['reference_number'])) echo $_POST['reference_number']; ?>" readonly><br>
                </div>
                <div class="customer-div">
                    <label for="customerName">Partner Name:</label>
                    <input type="text" id="customerName" name="customerName" value="<?php if (isset($_POST['partnerName'])) echo $_POST['partnerName'] ?>" readonly><br>
                </div>
                <div class="customerTin-div">
                    <label for="customerTIN">Partner TIN:</label>
                    <input type="text" id="customerTIN" name="customerTIN" value="" readonly><br>
                </div>
                <div class="serviceCharge-div">
                    <label for="serviceCharge">Service Charge:</label>
                    <input type="text" id="serviceCharge" name="serviceCharge" value="<?php echo isset($_POST['service_Type']) ? $_POST['service_Type'] : ''; ?>" readonly><br>
                </div>
                <div class="transactionDate-div">
                    <label for="transactionDate">Transaction Date:</label>
                    <input type="text" id="transactionFromDate" name="transactionFromDate" value="From:  <?php echo isset($_POST['fromDate']) ? date('F d, Y', strtotime($_POST['fromDate'])) : ''; ?>" readonly>
                    <input type="text" id="transactionToDate" name="transactionToDate" value="To:  <?php echo isset($_POST['toDate']) ? date('F d, Y', strtotime($_POST['toDate'])) : ''; ?>" readonly>
                </div>
                <div class="numberOfTransaction-div">
                    <label for="numTransactions">Number of Transactions:</label>
                    <input type="text" id="numTransactions" name="numTransactions" value="" readonly><br>
                </div>
                <hr class="header-line">
                <div class="addAmountDue-div" id="addAmountDue-div" style="display:none;">
                    <label for="addAmount">Add Amount:</label>
                    <span class="peso-sign">₱</span>
                    <input type="text" id="addAmountInp" name="addAmount" value="" readonly>
                    <br>
                </div>
                <div class="amount-content">
                    <label for="amount">Amount:</label>
                    <span class="peso-sign">₱</span>
                    <input type="text" id="amount-modal" name="amount-modal" value="" readonly>
                    <br>
                </div>
                <div class="vat-div">
                    <label for="vatAmount">VAT Amount:</label>
                    <span class="peso-sign">₱</span>
                    <input type="text" id="vatAmount" name="vatAmount" value="" readonly>
                    <br>
                </div>
                <div class="netvat-div">
                    <label for="netOfVAT">Net of VAT:</label>
                    <span class="peso-sign">₱</span>
                    <input type="text" id="netOfVAT" name="netOfVAT" value="" readonly>
                    <br>
                </div>
                <div class="wthax-div">
                    <label for="withholdingTax">Withholding Tax:</label>
                    <span class="peso-sign">₱</span>
                    <input type="text" id="withholdingTax" name="withholdingTax" value="" readonly>
                    <br>
                </div>
                <div class="netamountDue-div">
                    <label for="netAmountDue">Net Amount Due:</label>
                    <span class="peso-sign">₱</span>
                    <input type="text" id="netAmountDue" name="netAmountDue" value="" readonly>
                    <br>
                </div>
                <hr class="header-line">
            </div>
            <div class="modal-buttons">
                <div class="submit-btn">
                    <button type="submit" id="confirmBtn" name="confirmBtn" class="confirmBtn" value="<?php echo $referenceNumber; ?>">Review</button>
                    <button type="button" id="edit-one" name="edit-one">Edit</button>
                    <button type="button" id="cancelled-one" name="cancelled-one">Cancel</button>
                </div>
                <div class="modal-close">
                    <button class="closeBtn" onclick="closeModal()">Close</button>
                </div>
            </div>
        </div>
    </div>
    <!-- Modal for reasons of cancellation -->
    <div id="cancellationModal" class="cancel-modal">
        <div class="cancel-modal-content">
            <span class="cancel-close">&times;</span>
            <h3>Reasons of Cancellation</h3>
            <input style="display:none;" type="date" name="cancel_date" id="cancel_date" value="<?php echo date('Y-m-d'); ?>">
            <textarea id="cancellationReason" rows="4" name="cancellationReason" cols="50" maxlength="500" placeholder="Please provide a reason for cancellation..."></textarea><br>
            <center>
                <button type="submit" id="cancelConfirmBtn" name="cancelConfirmBtn">Confirm</button>
            </center>
        </div>
    </div>

    <!-- Modal for reasons of Multiple cancellation -->
    <div id="multipleCancellationModal" class="multipleCancel-modal">
        <div class="multipleCancel-modal-content">
            <span class="multipleCancel-close">&times;</span>
            <h3>Reasons of Cancellation</h3>
            <input style="display:none;" type="date" name="cancel_date" id="cancel_date" value="<?php echo date('Y-m-d'); ?>">
            <textarea id="cancellationReason" name="cancellationReason" rows="4" cols="50" maxlength="500" placeholder="Please provide a reason for cancellation..."></textarea><br>
            <center>
                <button type="submit" id="multipleCancelConfirmBtn" name="multipleCancelConfirmBtn">Confirm</button>
            </center>
        </div>
    </div>
    <!-- Modal for Edit -->
    <div id="EditModal" class="Edit-modal">
        <div class="Edit-modal-content">
            <span class="Edit-close">&times;</span>
            <input type="text" id="formulaInp" name="formula" value="">
            <input type="hidden" id="formula_withheld" name="formula_withheld" value="<?php echo $formula_withheld; ?>">
            <div class="edit-date">
                <label id="label" for="date">Date:</label>
                <input class="edit-input" type="text" id="e-date" name="date" value="" readonly><br>
            </div>
            <div class="edit-reference-div">
                <label id="label" for="reference">Reference Number:</label>
                <input class="edit-input" type="text" id="e-reference" name="reference" value="<?php if (isset($_POST['reference_number'])) echo $_POST['reference_number']; ?>" readonly><br>
            </div>
            <div class="edit-customer-div">
                <label id="label" for="customerName">Partner Name:</label>
                <input class="edit-input" type="text" id="e-customerName" name="customerName" value="<?php if (isset($_POST['partnerName'])) echo $_POST['partnerName'] ?>" readonly><br>
            </div>
            <div class="edit-customerTin-div">
                <label id="label" for="customerTIN">Partner TIN:</label>
                <input class="edit-input" type="text" id="e-customerTIN" name="customerTIN" value="" readonly><br>
            </div>
            <div class="edit-serviceCharge-div">
                <label id="label" for="serviceCharge">Service Charge:</label>
                <input class="edit-input" type="text" id="e-serviceCharge" name="serviceCharge" value="<?php echo isset($_POST['service_Type']) ? $_POST['service_Type'] : ''; ?>" readonly><br>
            </div>
            <div class="edit-transactionDate-div">
                <label id="label" for="transactionDate">Transaction Date:</label>
                <input class="edit-input" type="date" id="e-transactionFromDate" name="transactionFromDate" value="">
                <input class="edit-input" type="date" id="e-transactionToDate" name="transactionToDate" value="">
            </div>
            <div class="edit-numberOfTransaction-div">
                <label id="label" for="numTransactions">Number of Transactions:</label>
                <input class="edit-input" type="text" id="e-numTransactions" name="numTransactions" value=""><br>
            </div>
            <hr class="header-line">
            <div class="edit-amount-content">
                <label id="label" for="amount">Amount:</label>
                <span class="peso-sign">₱</span>
                <input class="edit-input" type="text" id="e-amount-modal" name="amount-modal" onkeyup="formulaComputation()" value="">
            </div>
            <div class="edit-amount-content" id="edit_addAmount" style="display:none;">
                <label id="label" for="addAmount">Add Amount:</label>
                <span class="peso-sign">₱</span>
                <input class="edit-input" type="text" id="e-addAmount" name="e-addAmount" value="" readonly>
            </div>
            <div class="edit-vat-div">
                <label id="label" for="vatAmount">VAT Amount:</label>
                <span class="peso-sign">₱</span>
                <input class="edit-input" type="text" id="e-vatAmount" name="e-vatAmount" value="" readonly>
                <br>
            </div>
            <div class="edit-netvat-div">
                <label id="label" for="netOfVAT">Net of VAT:</label>
                <span class="peso-sign">₱</span>
                <input class="edit-input" type="text" id="e-netOfVAT" name="e-netOfVAT" value="" readonly>
                <br>
            </div>
            <div class="edit-wthax-div">
                <label id="label" for="withholdingTax">Withholding Tax:</label>
                <span class="peso-sign">₱</span>
                <input class="edit-input" type="text" id="e-withholdingTax" name="e-withholdingTax" value="" readonly>
                <br>
            </div>
            <div class="edit-netamountDue-div">
                <label id="label" for="netAmountDue">Net Amount Due:</label>
                <span class="peso-sign">₱</span>
                <input class="edit-input" type="text" id="e-netAmountDue" name="e-netAmountDue" value="" readonly>
                <br>
            </div>
            <center>
                <button type="submit" id="EditConfirmBtn" name="EditConfirmBtn">Update</button>
            </center>
        </div>
    </div>
    </form>
    </div>

    <!-- Modal for displaying messages -->
    <div id="messageModal" class="message-modal">
        <div class="message-modal-content">
            <span id="modalMessage"></span><br>
            <button class="close-button">CLOSE</button>
        </div>
    </div>
    <script>
        
        var tableRows = document.getElementsByClassName("table-row");
        var selectedRow;
        var clickCount = 0;
        var doubleClickDelayMs = 300; // Adjust this value for desired double click delay

        // Function to handle row selection
        function selectRow(row, referenceNumber) {
            const checkbox = row.querySelector("input[type='checkbox']");
            if (selectedRow === row && checkbox.checked) {
                clickCount++;
                if (clickCount === 2) {
                    // Row is clicked twice, open the modal
                    openModal(referenceNumber);
                    clickCount = 0; // Reset the click count
                }
            } else {
                clickCount = 1; // Reset the click count

                // Remove previous selection
                if (selectedRow && !checkbox.checked) {
                    selectedRow.classList.remove("selected-row");
                }

                // Highlight the clicked row
                row.classList.toggle("selected-row");
                selectedRow = row;
            }
        }

        // Function to handle selecting/deselecting all rows
        document.getElementById("selectAllCheckbox").addEventListener("change", function() {
            const checkboxes = document.querySelectorAll(".row-checkbox");
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
                const row = checkbox.closest(".table-row");
                if (this.checked) {
                    row.classList.add("selected-row");
                } else {
                    row.classList.remove("selected-row");
                }
            });
        });

        // Function to open the modal
        function openModal(referenceNumber) {
            // Find the corresponding row in the table
            var rowData = Array.from(tableRows).find(function(row) {
                return row.querySelector("td:nth-child(3)").textContent === referenceNumber;
            });

            if (rowData) {
                // Extract the values from the row
                var date = rowData.querySelector("td:nth-child(2)").textContent;
                var referenceNumber = rowData.querySelector("td:nth-child(3)").textContent;
                var partnerName = rowData.querySelector("td:nth-child(4)").textContent;
                var partnerTIN = rowData.querySelector("td:nth-child(5)").textContent;
                var serviceCharge = rowData.querySelector("td:nth-child(8)").textContent;
                var transactionFromDate = rowData.querySelector("td:nth-child(9)").textContent;
                var transactionToDate = rowData.querySelector("td:nth-child(10)").textContent;
                var numTransactions = rowData.querySelector("td:nth-child(12)").textContent;
                var amount = rowData.querySelector("td:nth-child(13)").textContent;
                var addAmount = rowData.querySelector("td:nth-child(14)").textContent;
                var formula = rowData.querySelector("td:nth-child(15)").textContent;
                var formulaWithheld = rowData.querySelector("td:nth-child(16)").textContent;
                var vatAmount = rowData.querySelector("td:nth-child(17)").textContent;
                var netOfVAT = rowData.querySelector("td:nth-child(18)").textContent;
                var withholdingTax = rowData.querySelector("td:nth-child(19)").textContent;
                var netAmountDue = rowData.querySelector("td:nth-child(20)").textContent;

                // Populate the input fields in the modal
                var dateInput = document.getElementById("date");
                dateInput.value = date;
                var referenceNumberInput = document.getElementById("reference");
                referenceNumberInput.value = referenceNumber;
                var customerNameInput = document.getElementById("customerName");
                customerNameInput.value = partnerName;
                var customerTINInput = document.getElementById("customerTIN");
                customerTINInput.value = partnerTIN;
                var serviceChargeInput = document.getElementById("serviceCharge");
                serviceChargeInput.value = serviceCharge;
                var transactionFromDateInput = document.getElementById("transactionFromDate");
                transactionFromDateInput.value = transactionFromDate;
                var transactionToDateInput = document.getElementById("transactionToDate");
                transactionToDateInput.value = transactionToDate;
                var numTransactionsInput = document.getElementById("numTransactions");
                numTransactionsInput.value = numTransactions;
                var amountInput = document.getElementById("amount-modal");
                amountInput.value = amount;
                var addAmountInp = document.getElementById("addAmountInp");
                addAmountInp.value = addAmount;
                var addAmountInput = document.getElementById("e-addAmount");
                addAmountInput.value = addAmount;
                var formulaInput = document.getElementById("formulaInp");
                formulaInput.value = formula;
                var formulaWithheldInput = document.getElementById("formula_withheld");
                formulaWithheldInput.value = formulaWithheld;

                var vatAmountInput = document.getElementById("vatAmount");
                vatAmountInput.value = vatAmount;
                var netOfVATInput = document.getElementById("netOfVAT");
                netOfVATInput.value = netOfVAT;
                var withholdingTaxInput = document.getElementById("withholdingTax");
                withholdingTaxInput.value = withholdingTax;
                if (partnerTIN === '005-519-158-000') {
                    document.getElementById("edit_addAmount").style.display = "block";
                    document.getElementById("addAmountDue-div").style.display = "block";
                }
                if (formula === 'INCLUSIVE') {
                    var netAmountDueInput = document.getElementById("netAmountDue");
                    netAmountDueInput.value = netAmountDue;
                } else if (formula === 'EXCLUSIVE') {
                    var netAmountDueInput = document.getElementById("netAmountDue");
                    netAmountDueInput.value = netAmountDue;
                } else if (formula === 'NON-VAT') {
                    var netAmountDueInput = document.getElementById("netAmountDue");
                    netAmountDueInput.value = amount;
                }
                var dateInput = document.getElementById("e-date");
                dateInput.value = date;
                var referenceNumberInput = document.getElementById("e-reference");
                referenceNumberInput.value = referenceNumber;
                var customerNameInput = document.getElementById("e-customerName");
                customerNameInput.value = partnerName;
                var customerTINInput = document.getElementById("e-customerTIN");
                customerTINInput.value = partnerTIN;
                var serviceChargeInput = document.getElementById("e-serviceCharge");
                serviceChargeInput.value = serviceCharge;

                var transactionFromDateInput = document.getElementById("e-transactionFromDate");
                transactionFromDateInput.value = transactionFromDate;
                var transactionToDateInput = document.getElementById("e-transactionToDate");
                transactionToDateInput.value = transactionToDate;

                var numTransactionsInput = document.getElementById("e-numTransactions");
                numTransactionsInput.value = numTransactions;

                var amountInput = document.getElementById("e-amount-modal");
                amountInput.value = amount;
                var vatAmountInput = document.getElementById("e-vatAmount");
                vatAmountInput.value = vatAmount;
                var netOfVATInput = document.getElementById("e-netOfVAT");
                netOfVATInput.value = netOfVAT;
                var withholdingTaxInput = document.getElementById("e-withholdingTax");
                withholdingTaxInput.value = withholdingTax;
                if (formula === 'INCLUSIVE') {
                    var netAmountDueInput = document.getElementById("e-netAmountDue");
                    netAmountDueInput.value = netAmountDue;
                } else if (formula === 'EXCLUSIVE') {
                    var netAmountDueInput = document.getElementById("e-netAmountDue");
                    netAmountDueInput.value = netAmountDue;
                } else if (formula === 'NON-VAT') {
                    var netAmountDueInput = document.getElementById("e-netAmountDue");
                    netAmountDueInput.value = amount;
                }


            }

            // Code to open the modal and perform necessary actions
            var modal = document.getElementById("confirmModal");
            modal.style.display = "block";
        }

        function formulaComputation() {
            var amountInput = document.getElementById('e-amount-modal');
            var vatAmountInput = document.getElementById('e-vatAmount');
            var netOfVATInput = document.getElementById('e-netOfVAT');
            var withholdingTaxInput = document.getElementById('e-withholdingTax');
            var netAmountDueInput = document.getElementById('e-netAmountDue');
            var formula = document.getElementById("formulaInp");
            var formulaWithheld = document.getElementById("formula_withheld");
            var amount = parseFloat(amountInput.value.replace(/,/g, ''));
            var customerTINInput = document.getElementById("e-customerTIN");
            var addAmountInput = document.getElementById("e-addAmount");
            var addAmountValue = parseFloat(addAmountInput.value.replace(/,/g, ''));
            if (formula.value === "INCLUSIVE" && formulaWithheld.value === 'No') {
                var vatAmount = amount / 1.12;
                var netOfVAT = amount - vatAmount;
                var withholdingTax = 0.00;
                var netAmountDue = vatAmount - withholdingTax;

                vatAmountInput.value = formatNumber(vatAmount);
                netOfVATInput.value = formatNumber(netOfVAT);
                withholdingTaxInput.value = formatNumber(withholdingTax);
                netAmountDueInput.value = formatNumber(vatAmount);
            } else if (formula.value === "INCLUSIVE") {
                var vatAmount = (amount * 0.12) / 1.12;
                var netOfVAT = amount - vatAmount;
                var withholdingTax = netOfVAT * 0.02;
                var netAmountDue = amount - withholdingTax;

                vatAmountInput.value = formatNumber(vatAmount);
                netOfVATInput.value = formatNumber(netOfVAT);
                withholdingTaxInput.value = formatNumber(withholdingTax);
                netAmountDueInput.value = formatNumber(netAmountDue);
            } else if (formula.value === 'EXCLUSIVE') {
                var vatAmount = amount * 0.12;
                var withholdingTax = amount * 0.02;
                var netOfVAT = '';
                var totalAmount = amount + vatAmount;
                var netAmountDue = totalAmount - withholdingTax;

                vatAmountInput.value = formatNumber(vatAmount);
                netOfVATInput.value = '';
                withholdingTaxInput.value = formatNumber(withholdingTax);
                netAmountDueInput.value = formatNumber(netAmountDue);
            } else if (formula.value === 'NON-VAT') {
                var vatAmount = 0;
                var netOfVAT = 0;
                var withholdingTax = 0;
                var netAmountDue = amount;

                vatAmountInput.value = formatNumber(vatAmount);
                netOfVATInput.value = formatNumber(netOfVAT);
                withholdingTaxInput.value = formatNumber(withholdingTax);
                netAmountDueInput.value = formatNumber(amount);
            }

            if (customerTINInput.value === '005-519-158-000') {
                netAmountDue += addAmountValue;
                netAmountDueInput.value = formatNumber(netAmountDue);
            }
        }

        function formatNumber(number) {
            return number.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        }
        // Function to close the modal
        function closeModal() {
            var modal = document.getElementById("confirmModal");
            modal.style.display = "none";
        }
        // Close modal when the close button is clicked
        var closeButton = document.getElementsByClassName("closeBtn")[0];
        closeButton.addEventListener("click", closeModal);

        // Close modal when the user clicks outside the modal
        window.addEventListener("click", function(event) {
            var modal = document.getElementById("confirmModal");
            if (event.target === modal) {
                closeModal();
            }
        });

        // Function to display a modal with a message and icon
        function displayModal(message, iconClass) {
            var modal = document.getElementById("messageModal");
            var messageText = document.getElementById("modalMessage");
            messageText.innerHTML = '<span class="' + iconClass + '"></span>' + message;
            modal.style.display = "block";
        }

        // Function to close the modal
        function closeModal() {
            var modal = document.getElementById("messageModal");
            modal.style.display = "none";
        }

        // Close the modal when the close button is clicked
        var closeButton = document.querySelector(".close-button");
        if (closeButton) {
            closeButton.addEventListener("click", closeModal);
        }
        // Get references to the necessary elements
        var cancellationModal = document.getElementById('cancellationModal');
        var cancelButton = document.getElementById('cancelled-one');
        var closeButton = document.querySelector('.cancel-close');
        var confirmButton = document.getElementById('cancelConfirmBtn');
        var cancellationReasonInput = document.getElementById('cancellationReason');

        // Show the cancellation modal
        function showCancellationModal() {
            cancellationModal.style.display = 'block';
        }

        // Close the cancellation modal
        function closeCancellationModal() {
            cancellationModal.style.display = 'none';
        }

        // Event listener for the Cancel button
        cancelButton.addEventListener('click', function() {
            // Open the cancellation modal
            showCancellationModal();
        });

        // Event listener for the Close button in the modal
        closeButton.addEventListener('click', function() {
            // Close the cancellation modal
            closeCancellationModal();
        });

        // Event listener for the Confirm button in the modal
        confirmButton.addEventListener('click', function() {
            // Get the cancellation reason from the textarea
            var cancellationReason = cancellationReasonInput.value;

            // Perform any necessary validation or processing with the cancellation reason here

            // Close the cancellation modal
            closeCancellationModal();
        });



        // Get references to the necessary elements
        var cancellationModal = document.getElementById('multipleCancellationModal');
        var cancelButton = document.getElementById('forcancelled');
        var closeButton = document.querySelector('.multipleCancel-close');
        var confirmButton = document.getElementById('multipleCancelConfirmBtn');
        var cancellationReasonInput = document.getElementById('multipleCancellationReason');

        // Show the cancellation modal
        function showCancellationModal() {
            cancellationModal.style.display = 'block';
        }

        // Close the cancellation modal
        function closeCancellationModal() {
            cancellationModal.style.display = 'none';
        }

        // Event listener for the Cancel button
        cancelButton.addEventListener('click', function() {
            // Check if any table checkmarks are checked
            var checkmarks = document.querySelectorAll('.selected-row');
            if (checkmarks.length > 0) {
                // Open the cancellation modal
                showCancellationModal();
            } else {
                // Show an alert or perform any other action to indicate that no checkmarks are checked
                alert('Please select at least one Transaction to cancel.');
            }
        });

        // Event listener for the Close button in the modal
        closeButton.addEventListener('click', function() {
            // Close the cancellation modal
            closeCancellationModal();
        });

        // Event listener for the Confirm button in the modal
        confirmButton.addEventListener('click', function() {
            // Get the cancellation reason from the textarea
            var cancellationReason = cancellationReasonInput.value;

            // Perform any necessary validation or processing with the cancellation reason here

            // Close the cancellation modal
            closeCancellationModal();
        });


        // Get references to the necessary elements
        var editModal = document.getElementById('EditModal');
        var editButton = document.getElementById('edit-one');
        var closeButton = document.querySelector('.Edit-close');
        var confirmButton = document.getElementById('EditConfirmBtn');

        // Show the cancellation modal
        function showeditModal() {
            editModal.style.display = 'block';
        }

        // Close the cancellation modal
        function closeeditModal() {
            editModal.style.display = 'none';
        }

        // Event listener for the Cancel button
        editButton.addEventListener('click', function() {
            // Open the cancellation modal
            showeditModal();
        });

        // Event listener for the Close button in the modal
        closeButton.addEventListener('click', function() {
            // Close the cancellation modal
            closeeditModal();
        });
    </script>
</body>
<?php include '../../../templates/footer.php'; ?>

</html>