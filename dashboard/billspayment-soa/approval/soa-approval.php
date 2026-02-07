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
        if ($_SESSION['user_email'] !== 'balb01013333' && $_SESSION['user_email'] !== 'pera94005055') {
            header("Location:../../../index.php");
            session_destroy();
            exit();
        }
    }else{
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
            message.innerHTML = \'<div class="icon-container"><div class="icon">&#10060;</div></div> \' + "' . $message . '";
        } else {
            message.innerHTML = \'<div class="icon-container"><div class="icon">&#10003;</div></div> \' + "' . $message . '";
        }
        
        modal.style.display = "block";
    }
    </script>
    ';
}

$query = "SELECT * FROM soa_transaction";
$result = mysqli_query($conn, $query);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['confirmBtn'])) {
        $referenceNumber = $_POST['reference'];
        $approvedby = $_SESSION['user_name'];
        $approvedSignature = 'electronically signed';
        $currentDate = date("m-d-Y"); // Get the current date
        $notedFix_signature = 'LUELLA PERALTA';
        // Update the status and reviewed_by columns in the database
        $updateQuery = "UPDATE soa_transaction SET status = 'Approved', noted_signature = '$approvedSignature', noted_by = '$approvedby', notedDate_signature = '$currentDate', notedFix_signature = '$notedFix_signature' WHERE reference_number = '$referenceNumber'";
        if (mysqli_query($conn, $updateQuery)) {
            // Successful update, redirect or display a success message
            $successMessage = "Selected row(s) updated to 'Approved'.";
            displayModal($successMessage);
        } else {
            // Failed to update, handle the error
            $errorMessage = "Error updating transaction: " . mysqli_error($conn);
            displayModal($errorMessage);
        }
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approved']) && isset($_POST['selectedRows'])) {
    // Process the selected rows
    $selectedRows = $_POST['selectedRows'];
    $approvedby = $_SESSION['user_name'];
    $approvedSignature = 'electronically signed';
    $currentDate = date("m-d-Y"); // Get the current date
    $notedFix_signature = 'LUELLA PERALTA';

    // Update the status of selected rows to "Approved"
    foreach ($selectedRows as $referenceNumber) {
        // Perform the necessary database update operation to set the status to "Approved"
        $updateQuery = "UPDATE soa_transaction SET status = 'Approved' , noted_signature = '$approvedSignature', noted_by = '$approvedby', notedDate_signature = '$currentDate',  notedFix_signature = '$notedFix_signature' WHERE reference_number = '$referenceNumber'";
        mysqli_query($conn, $updateQuery);
    }

    $successMessage = "Selected row(s) updated to 'Approved'.";
    displayModal($successMessage);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['multipleCancelConfirmBtn']) && !empty($_POST['cancelledBy'])) {
        // Check if any rows are selected
        if (isset($_POST['selectedRows'])) {
            // Process the selected rows
            $selectedRows = $_POST['selectedRows'];
            $cancelledBy = $_POST['cancelledBy'];
            $reasonOf_cancellation = $_POST['cancellationReason'];
            $cancelled_date = $_POST['cancel_date'];

            // Update the status of selected rows to "Cancelled" and set cancelled_by value
            $updateQuery = "UPDATE soa_transaction SET status = 'Cancelled',reasonOf_cancellation = '$reasonOf_cancellation', cancelled_by = '$cancelledBy', cancelled_date = '$cancelled_date' WHERE reference_number IN ('" . implode("','", $selectedRows) . "')";
            if (mysqli_query($conn, $updateQuery)) {
                $successMessage = "Selected row(s) updated to 'Cancelled'.<br>";
                $successMessage .= "Cancelled by: " . $cancelledBy;
                displayModal($successMessage);
            } else {
                $errorMessage = "Error updating selected row(s): " . mysqli_error($conn);
                displayModal($errorMessage, true);
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['cancelConfirmBtn']) && !empty($_POST['cancelledBy'])) {
        // Check if any rows are selected
        if (isset($_POST['selectedRows'])) {
            // Process the selected rows
            $selectedRows = $_POST['selectedRows'];
            $cancelledBy = $_POST['cancelledBy'];
            $reasonOf_cancellation = $_POST['cancellationReason'];
            $cancelled_date = $_POST['cancel_date'];

            // Update the status of selected rows to "Cancelled" and set cancelled_by value
            $updateQuery = "UPDATE soa_transaction SET status = 'Cancelled',reasonOf_cancellation = '$reasonOf_cancellation', cancelled_by = '$cancelledBy', , cancelled_date = '$cancelled_date' WHERE reference_number IN ('" . implode("','", $selectedRows) . "')";
            if (mysqli_query($conn, $updateQuery)) {
                $successMessage = "Selected row(s) updated to 'Cancelled'.<br>";
                $successMessage .= "Cancelled by: " . $cancelledBy;
                displayModal($successMessage);
            } else {
                $errorMessage = "Error updating selected row(s): " . mysqli_error($conn);
                displayModal($errorMessage, true);
            }
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SOA Approval | <?php if($_SESSION['user_type'] === 'admin' || $_SESSION['user_type'] === 'user') echo ucfirst($_SESSION['user_type']); else echo "Guest";?></title>
    <!-- custom CSS file link  -->
    <link rel="stylesheet" href="../../../assets/css/templates/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../../../assets/css/user_review.css?v=<?php echo time(); ?>">
    <script src="https://kit.fontawesome.com/30b908cc5a.js" crossorigin="anonymous"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="../../../assets/js/sweetalert2.all.min.js"></script>

    <link rel="icon" href="../../../images/MLW logo.png" type="image/png">
</head>
<body>
    <div class="main-container">
        <div class="top-content">
            <div class="nav-container">
                <i id="menu-btn" class="fa-solid fa-bars"></i>
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
        <center><h3>SOA Approval</h3></center>
        <center><h5>List of Transaction(s)</h5></center>
        <form action="" method="POST">
            <div class="data-table">
                <div class="approved-btn">
                    <button type="submit" id="forapproved" class="approved" name="approved">Approve</button>
                    <button type="button" id="forcancelled" name="cancelled">Cancel</button>
                </div>
                <table>
                    <tr>
                        <!-- Table header code -->
                        <th><input type="checkbox" id="selectAllCheckbox"></th>
                        <th>Date</th>
                        <th>Reference Number</th>
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
                        <th>VAT <br> Amount</th>
                        <th>Net of VAT</th>
                        <th>Withholding <br> Tax</th>
                        <th>Net Amount <br> Due</th>
                        <!-- <th>Prepared By</th> -->
                        <th>Created By</th>
                        <th>Reviewed By</th>
                        <!-- <th>Noted By</th>original -->
                        <th>Approved By</th>
                    </tr>
                    <?php while ($row = mysqli_fetch_assoc($result)): ?>
                        <?php if ($row['status'] === 'Reviewed' && $row['status'] !== 'Cancelled') { ?>
                            <?php
                            $referenceNumber = $row['reference_number'];
                            $isSelected = isset($_GET['reference_number']) && $_GET['reference_number'] === $referenceNumber;
                            $rowClass = $isSelected ? "selected-row" : "";
                            ?>

                            <tr class="table-row <?php echo $rowClass; ?>" onclick="selectRow(this, '<?php echo $referenceNumber; ?>')">
                                <td><input type="checkbox" class="row-checkbox" name="selectedRows[]" value="<?php echo $referenceNumber; ?>"></td>
                                <td style="text-align:left;"><?php echo date('F j, Y', strtotime($row['date'])); ?></td>
                                <td style="text-align:left;"><?php echo $row['reference_number']; ?></td>
                                <td style="text-align:left;"><?php echo $row['partner_Name']; ?></td>
                                <td style="display:none;"><?php echo $row['partner_Tin']; ?></td>
                                <td style="display:none;"><?php echo $row['address']; ?></td>
                                <td style="display:none;"><?php echo $row['business_style']; ?></td>
                                <td style="text-align:right;"><?php echo $row['service_charge']; ?></td>
                                <td style="text-align:left;"><?php echo date('F j, Y', strtotime($row['from_date'])); ?></td>
                                <td style="text-align:left;"><?php echo date('F j, Y', strtotime($row['to_date'])); ?></td>
                                <td style="display:none;"><?php echo $row['po_number']; ?></td>
                                <td style="text-align:right;"><?php echo number_format($row['number_of_transactions']); ?></td>
                                <td style="text-align:right;"><?php echo number_format($row['amount'], 2); ?></td>
                                <td style="display:none;"><?php echo $row['add_amount']; ?></td>
                                <td style="display:none;"><?php echo $row['formula']; ?></td>
                                <td style="text-align:right;"><?php echo $row['vat_amount']; ?></td>
                                <td style="text-align:right;"><?php echo $row['net_of_vat']; ?></td>
                                <td style="text-align:right;"><?php echo $row['withholding_tax']; ?></td>
                                <td style="text-align:right;"><?php echo $row['net_amount_due']; ?></td>
                                <td style="text-align:left;"><?php echo $row['prepared_by']; ?></td>
                                <td style="text-align:left;"><?php echo $row['reviewed_by']; ?></td>
                                <td style="text-align:left;"><?php echo $row['noted_by']; ?></td>
                            </tr>

                    <?php }
                    endwhile; ?>
                </table>
            </div>
            <!-- Add the confirm modal -->
            <div id="confirmModal" class="modal">
                <div class="modal-content">
                    <input type="hidden" id="cancelledBy" name="cancelledBy" value="<?php echo $_SESSION['user_name'] ?>">
                    <div class="modal-header">
                        <h3>REVIEW THE TRANSACTION</h3>
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
                            <button type="submit" id="confirmBtn" name="confirmBtn" class="confirmBtn" value="<?php echo $referenceNumber; ?>">Approve</button>
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
        </form>
    </div>
    <!-- Modal for displaying messages -->
    <div id="messageModal" class="message-modal">
        <form action="" method="POST">
        <div class="message-modal-content">
            <span id="modalMessage"></span><br>
            <button type="submit" class="close-button">CLOSE</button>
        </div>
        </form>
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
                var vatAmount = rowData.querySelector("td:nth-child(16)").textContent;
                var netOfVAT = rowData.querySelector("td:nth-child(17)").textContent;
                var withholdingTax = rowData.querySelector("td:nth-child(18)").textContent;
                var netAmountDue = rowData.querySelector("td:nth-child(19)").textContent;

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
                var addAmountInput = document.getElementById("addAmountInp");
                addAmountInput.value = addAmount;
                var vatAmountInput = document.getElementById("vatAmount");
                vatAmountInput.value = vatAmount;
                var netOfVATInput = document.getElementById("netOfVAT");
                netOfVATInput.value = netOfVAT;
                var withholdingTaxInput = document.getElementById("withholdingTax");
                withholdingTaxInput.value = withholdingTax;
                if (partnerTIN === '005-519-158-000') {
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
            }


            // Code to open the modal and perform necessary actions
            var modal = document.getElementById("confirmModal");
            modal.style.display = "block";
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
    </script>
</body>
<?php include '../../../templates/footer.php'; ?>
</html>