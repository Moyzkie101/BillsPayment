<?php
$conn = mysqli_connect('localhost', 'root', 'Password1', 'mldb');
session_start();
@include '../fetch/fetch-partner-data.php';

if (!isset($_SESSION['admin_name'])) {
    header('location:../login_form.php');
    exit;
}  
    // Get the selected date range
$startDate = isset($_POST['start-date']) ? $_POST['start-date'] : '';
$endDate = isset($_POST['end-date']) ? $_POST['end-date'] : '';
$status = isset($_POST['status']) ? $_POST['status'] : '';
$partnerTin = isset($_POST['partnerTin']) ? $_POST['partnerTin'] : '';

if ($status === 'all' && !empty($partnerTin)) {
    $query = "SELECT * FROM soa_transaction WHERE partner_Tin = '$partnerTin'";
} elseif ($status === 'all') {
    $query = "SELECT * FROM soa_transaction";
} elseif ($status === 'prepare' && !empty($partnerTin)) {
    $query = "SELECT * FROM soa_transaction WHERE status = '' AND partner_Tin = '$partnerTin'";
} elseif ($status === 'prepare') {
    $query = "SELECT * FROM soa_transaction WHERE status = ''";
} elseif ($status === 'reviewed' && !empty($partnerTin)) {
    $query = "SELECT * FROM soa_transaction WHERE status = 'reviewed' AND partner_Tin = '$partnerTin'";
} elseif ($status === 'reviewed') {
    $query = "SELECT * FROM soa_transaction WHERE status = 'reviewed'";
} elseif ($status === 'approved' && !empty($partnerTin)) {
    $query = "SELECT * FROM soa_transaction WHERE status = 'approved' AND partner_Tin = '$partnerTin'";
} elseif ($status === 'approved') {
    $query = "SELECT * FROM soa_transaction WHERE status = 'approved'";
} elseif ($status === 'cancelled' && !empty($partnerTin)) {
    $query = "SELECT * FROM soa_transaction WHERE status = 'cancelled' AND partner_Tin = '$partnerTin'";
} elseif ($status === 'cancelled') {
    $query = "SELECT * FROM soa_transaction WHERE status = 'cancelled'";
} elseif (!empty($startDate) && !empty($endDate) && !empty($partnerTin)) {
    $query = "SELECT * FROM soa_transaction WHERE `date` BETWEEN '$startDate' AND '$endDate' AND partner_Tin = '$partnerTin'";
} elseif (!empty($startDate) && !empty($endDate)) {
    $query = "SELECT * FROM soa_transaction WHERE `date` BETWEEN '$startDate' AND '$endDate'";
} else {
    $query = "SELECT * FROM soa_transaction WHERE 1=0"; // Empty query to return no results
}

// Construct the SQL query to retrieve transactions
$result = mysqli_query($conn, $query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="UTF-8">
   <meta http-equiv="X-UA-Compatible" content="IE=edge">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>User Report</title>
   <!-- custom CSS file link  -->
   <link rel="stylesheet" href="../css/admin_review.css?v=<?php echo time(); ?>">
</head>
<body>
    
   <div class="container">
      <div class="top-content">
         <div class="usernav">
            <h4><?php echo $_SESSION['admin_name'] ?></h4>
            <h5 style="margin-left:5px;"><?php echo "(".$_SESSION['admin_email'].")" ?></h5>
         </div>
         <div class="btn-nav">
            <ul class="nav-list">
               <li><a href="admin_page.php">HOME</a></li>
               <li class="dropdown">
                        <button class="dropdown-btn">SOA</button>
                        <div class="dropdown-content">
                           <a id="user" href="admin_soa.php">CREATE SOA</a>
                           <a id="user" href="admin_review.php">FOR REVIEW</a>
                           <a id="user" href="admin_approval.php">FOR APPROVAL</a>
                           <a id="user" href="admin_reports.php">REPORTS</a>
                        </div>
                    </li>
                  <li class="dropdown">
                        <button class="dropdown-btn">MAINTENANCE</button>
                        <div class="dropdown-content">
                        <a id="user" href="userLog.php">USER</a>
                        </div>
                    </li>
               <li><a href="../logout.php">LOGOUT</a></li>
            </ul>
         </div>
      </div>
      <center><h4>REPORTS</h4></center>
      <form action="" method="POST">
        <div class="status-select">
            <input type="date" id="start-date" name="start-date" value="<?php echo isset($_POST['start-date']) ? $_POST['start-date'] : ''; ?>">
            <input type="date" id="end-date" onchange="dateRange_trap()" name="end-date" value="<?php echo isset($_POST['end-date']) ? $_POST['end-date'] : ''; ?>">
            <select name="status" class="status" id="status" onchange="status_trap()">
                <option value="status" <?php echo ($status === 'status') ? 'selected' : ''; ?>>-- SELECT STATUS --</option>
                <option value="all" <?php echo ($status === 'all') ? 'selected' : ''; ?>>All</option>
                <option value="prepare" <?php echo ($status === 'prepare') ? 'selected' : ''; ?>>Prepared</option>
                <option value="reviewed" <?php echo ($status === 'reviewed') ? 'selected' : ''; ?>>Reviewed</option>
                <option value="approved" <?php echo ($status === 'approved') ? 'selected' : ''; ?>>Approved</option>
                <option value="cancelled" <?php echo ($status === 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
            </select>
            <select autofocus class="form-control" class="partner-select" id="partner-select" name="partnerTin" onchange="updatePartnerTin()">
                <option name="partner-name">-- Select Partner Name --</option>
                <?php 
                    // Sort the options array in ascending order based on 'partner_name'
                    usort($options, function ($a, $b) {
                        return strcmp($a['partner_name'], $b['partner_name']);
                    });

                    foreach ($options as $option) {
                        $selected = ($partnerTinSelected && $_POST['partnerTin'] === $option['partnerTin']) ? 'selected' : '';
                        echo '<option data-partnerid="' . $option['partnerTin'] . '" ' . $selected . '>' . $option['partner_name'] . '</option>';
                    }
                ?>
            </select>
            <button id="proceed-button" class="proceed-button" name="proceed-button">Proceed</button>
        </div>
        
        <input style="display:none;" type="text" id="partnerTin" name="partnerTin" value="" readonly required>
    <div class="data-table">  
        
        <?php
        if (isset($_POST['proceed-button'])) {
            if (mysqli_num_rows($result) > 0) {
        ?>
                <table>
                    <!-- Table header code -->
                    <tr>
                        <th style="display:none;">Select</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Reference<br>Number</th>
                        <th>Partner Name</th>
                        <th style="display:none;">Partner Tin</th>
                        <th style="display:none;">Address</th>
                        <th style="display:none;">Business Style</th>
                        <th>Service Charge</th>
                        <th>From Date</th>
                        <th>To Date</th>
                        <th style="display:none;">PO Number</th>
                        <th>Number of<br>Transactions</th>
                        <th>Amount</th>
                        <th>VAT<br>Amount</th>
                        <th>Net of VAT</th>
                        <th>Withholding<br>Tax</th>
                        <th>Net Amount<br>Due</th>
                        <th>Prepared By</th>
                        <th>Reviewed By</th>
                        <th>Noted By</th>
                        <th>Cancelled By</th>
                    </tr>
                    <?php while ($row = mysqli_fetch_assoc($result)) { ?>
                        <?php
                        $referenceNumber = $row['reference_number'];
                        $isSelected = isset($_GET['reference_number']) && $_GET['reference_number'] === $referenceNumber;
                        $rowClass = $isSelected ? "selected-row" : "";
                        ?>
                        <tr class="table-row <?php echo $rowClass; ?>" onclick="selectRow(this, '<?php echo $referenceNumber; ?>')">
                            <td style="display:none;"><input type="checkbox" class="row-checkbox"></td>
                            <td style="text-align:left;"><?php echo $row['status']; ?></td>
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
                            <td style="display:none;"><?php echo $row['formula']; ?></td>
                            <td style="display:none;"><?php echo $row['formulaInc_Exc']; ?></td>
                            <td style="text-align:right;"><?php echo $row['vat_amount']; ?></td>
                            <td style="text-align:right;"><?php echo $row['net_of_vat']; ?></td>
                            <td style="text-align:right;"><?php echo $row['withholding_tax']; ?></td>
                            <td style="display:none;"><?php echo $row['totalAmountDue']; ?></td>
                            <td style="text-align:right;"><?php echo $row['net_amount_due']; ?></td>
                            <td style="text-align:left;"><?php echo $row['prepared_by']; ?></td>
                            <td style="text-align:left;"><?php echo $row['reviewed_by']; ?></td>
                            <td style="text-align:left;"><?php echo $row['noted_by']; ?></td>
                            <td style="display:none;"><?php echo $row['prepared_signature']; ?></td>
                            <td style="display:none;"><?php echo $row['reviewed_signature']; ?></td>
                            <td style="display:none;"><?php echo $row['noted_signature']; ?></td>
                            <td style="display:none;"><?php echo $row['preparedDate_signature']; ?></td>
                            <td style="display:none;"><?php echo $row['reviewedDate_signature']; ?></td>
                            <td style="display:none;"><?php echo $row['notedDate_signature']; ?></td>
                            <td style="display:none;"><?php echo $row['reviewedFix_signature']; ?></td>
                            <td style="display:none;"><?php echo $row['notedFix_signature']; ?></td>
                            <td><?php echo $row['cancelled_by']; ?></td>
                        </tr>
                    <?php } ?>
                </table>
        <?php
            } else {
                echo "<p>No transactions found.</p>";
            }
        }
        ?>
    </div>
    <!-- Add the confirm modal -->
    <div id="confirmModal" class="modal">
        <form action="" method="POST">
            <div class="modal-content">
                <div class="modal-fields">
                    <div class="t-date">
                        <label id="date-lbl" style="width:150px;">Date:</label>
                        <span id="date-value"></span><br>
                    </div>
                    <div class="reference-div">
                        <label  id="reference-lbl" style="width:150px;">Reference No:</label>
                        <span id="reference-value"><input id="reference-value" name="reference" value="<?php echo $row['reference_number']; ?>"></span><br>
                    </div>
                    <div class="position-print">
                        <div class="customer-div">
                            <label id="partnerName-lbl" style="width:150px;">Customer Name:</label>
                            <span id="customerName-value"></span><br>
                        </div>
                        <div class="customerTin-div">
                            <label id="partnerTin-lbl" style="width:150px;">Customer TIN:</label>
                            <span id="customerTIN-value"></span><br>
                        </div>
                        <div class="customerTin-div">
                            <label id="address-lbl" style="width:150px;">Address:</label>
                            <span id="address-value"></span><br>
                        </div>
                        <div class="customerTin-div" style=" margin-bottom: 8px;">
                            <label id="business-lbl" style="width:150px;">Business Style:</label>
                            <span id="businessStyle-value"></span><br>
                        </div>
                    </div>
                    <table id="print-table">
                        <thead>
                            <th class="print-head">PARTICULARS</th>
                            <th class="print-head">AMOUNT</th>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="print-td" style="padding: 20px 20px 20px 20px;">
                                    <div class="serviceCharge-div">
                                        <span id="serviceCharge-value"></span><br>        
                                    </div>
                                    <div class="numberOfTransaction-div">
                                        <label id="num-lbl">Number of Transactions:</label>
                                        <span id="numTransactions-value"></span><br>
                                    </div>
                                    <div class="transactionDate-div">
                                        <span id="transactionFromDate-value"></span>
                                        <span id="transactionToDate-value"></span><br>
                                    </div>
                                    <span id="formula-value"></span><br>
                                    <span id="formulaIncExc-value"></span>
                                </td>
                                <td class="print-td">
                                    <div id="amount-content">
                                        <div>
                                            <label style="width:120px; text-align: left;">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                                            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<span id="pesos1" style="color:green;">₱</span></label>
                                        </div>       
                                        <div>
                                            <span id="amount-modal-value"></span><br>
                                        </div>
                                    </div>
                                    <div id="vat-div">
                                        <div>
                                            <label style="width:120px; text-align: left;" id="vatAmount-label">VAT Amount:&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<span id="pesos2" style="color:green;">₱</span></label>
                                        </div>
                                        
                                        <div>
                                            <span id="vatAmount-value"></span><br>
                                        </div>
                                    </div>  
                                    <div id="netvat-div">
                                        <div>
                                            <label style="width:120px; text-align: left;" id="netOfVat-label">Net of VAT:&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<span id="pesos3" style="color:green;">₱</span></label>
                                        </div>
                                        
                                        <div>
                                            <span id="netOfVAT-value"></span><br>
                                        </div>
                                    </div>
                                    <div id="wthax-div">
                                        <div>
                                            <label style="width:120px; text-align: left;" id="wtax-label">Withholding Tax:<span id="pesos4" style="color:green;">₱</span></label>
                                        </div>
                                        
                                        <div>
                                            <span id="withholdingTax-value"></span><br>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td class="print-td" style="text-align:right;">
                                    <div>
                                        <label for="" id="totalAmount-lbl">TOTAL AMOUNT DUE</label>
                                    </div>
                                    <div>
                                        <label for="" id="L-wtax-lbl">LESS WITHHOLDING TAX</label>
                                    </div>
                                    <div>
                                        <label for="" id="netAmountDue-lbl">NET AMOUNT DUE</label>
                                    </div>
                                </td>
                                <td class="print-td">
                                    <div class="total-amount-div">
                                        <div class="peso-sign" >
                                            <span style="color: green;" >₱</span>
                                        </div>
                                        <div>
                                            <span id="total-amount-value"></span><br>
                                        </div>
                                    </div>
                                    <div class="less-withax-div">
                                        <div class="peso-sign">
                                            <span style="color: green;" >₱</span>
                                        </div>
                                        <div>
                                            <span id="less-withholdingTax-value"></span><br>
                                        </div>
                                    </div>
                                    <div class="netAmountDue-div">
                                        <div class="peso-sign">
                                            <span style="color: green;" >₱</span>
                                        </div>
                                        <div>
                                            <span id="netAmountDue-value"></span><br>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
            <table class="table-signature">
                <tr>
                    <td class="preparedby" id="signature-row" style="width:35%;">
                        <div class="preparedby-lbl">
                            <label for="" id="prepared-lbl">Prepared by:</label>
                        </div>
                        <div class="signature-inp">
                            <input type="text" id="prepared-signature" readonly>
                        </div>
                        <div class="Datesignature-inp">
                            <input type="text" id="preparedDate-signature" readonly>
                        </div>
                        <div class="Fixsignature-inp">
                            <input type="text" id="preparedfix-signature">
                        </div>
                        <div class="prepared-inp">
                            <input type="text" id="preparedInput-value">
                        </div>
                        <div class="position-lbl">
                            <label for="" id="accountingStaff-lbl">Accounting Staff</label>
                        </div>
                    </td>
                    <td class="reviewby" id="signature-row" style="width:30%;">
                        <div class="reviewedby-lbl">
                            <label for="" id="reviewedby-lbl">Reviewed by:</label>
                        </div>
                        <div class="signature-inp">
                            <input type="text" id="reviewed-signature" readonly>
                        </div>
                        <div class="Datesignature-inp">
                            <input type="text" id="reviewedDate-signature" readonly>
                        </div>
                        
                        <div class="reviewedby-inp">
                            <input type="text" id="reviewed-value">
                        </div>
                    
                        <div class="Fixsignature-inp">
                            <input type="text" id="reviewedfix-signature">
                        </div>
                        <div class="position-lbl">
                            <label for="" id="departmentManager-lbl">Department Manager</label>
                        </div>
                    </td>
                    <td class="notedby" id="signature-row" style="width:30%;">
                        <div class="notedby-lbl">
                            <label for="" id="notedby-lbl">Noted by:</label>
                        </div>
                        <div class="signature-inp">
                            <input type="text" id="noted-signature" readonly>
                        </div>
                        <div class="Datesignature-inp">
                            <input type="text" id="notedDate-signature" readonly>
                        </div>
                        <div class="notedby-inp">
                            <input type="text" id="noted-value">
                        </div>
                        <div class="Fixsignature-inp">
                            <input type="text" id="notedfix-signature">
                        </div>
                        <div class="position-lbl">
                            <label for="" id="divisionHead-lbl">Division Head</label>
                        </div>
                    </td>
                </tr>
            </table>
            </div>
            <br>
            <div class="modal-buttons">
                <div class="submit-btn">
                    <button class="printBtn" id="print-Btn" onclick="printModal()">Print Without Form</button>
                    <button class="printBtn" id="printBtn" onclick="printWithModal()">Print With Form</button>
                </div>
                <div class="modal-close">
                    <button class="closeBtn" onclick="closeModal()">Close</button>
                </div>
            </div>
        </div>

</div>
    </form>

     

<script>
    function dateRange_trap(){
        var startDate = document.getElementById('start-date');
        var endDate = document.getElementById('end-date');
        var status = document.getElementById('status');

        if(startDate !== '' && endDate !== ''){
            status.disabled = true;
        }else{
            status.disabled = false;
        } 
    }
    function status_trap(){
        var startDate = document.getElementById('start-date');
        var endDate = document.getElementById('end-date');
        var status = document.getElementById('status');
        if(status !== ''){
            startDate.disabled = true;
            endDate.disabled = true;
        }else{
            startDate.disabled = false;
            endDate.disabled = false;
        }
    }

    function updatePartnerTin() {
        var select = document.getElementById("partner-select");
        var partnerTinInput = document.getElementById("partnerTin");
        var selectedOption = select.options[select.selectedIndex];
        var partnerTin = selectedOption.getAttribute("data-partnerid");
        partnerTinInput.value = partnerTin;
    }

    var tableRows = document.getElementsByClassName("table-row");
    var selectedRow;
    var clickCount = 0;
    var doubleClickDelayMs = 300; // Adjust this value for desired double click delay

    // Function to handle row selection
    function selectRow(row, referenceNumber) {
        if (selectedRow === row) {
            clickCount++;
            if (clickCount === 2) {
                // Row is clicked twice, open the modal
                openModal(referenceNumber);
                clickCount = 0; // Reset the click count
            }
        } else {
            clickCount = 1; // Reset the click count

            // Remove previous selection
            if (selectedRow) {
                selectedRow.classList.remove("selected-row");
            }

            // Highlight the clicked row
            row.classList.add("selected-row");
            selectedRow = row;
        }
    }
    

// Function to open the modal
function openModal(referenceNumber) {
    // Find the corresponding row in the table
    var rowData = Array.from(tableRows).find(function (row) {
        return row.querySelector("td:nth-child(4)").textContent === referenceNumber;
    });

    if (rowData) {
        // Extract the values from the row
        var status = rowData.querySelector("td:nth-child(2)").textContent;
        var date = rowData.querySelector("td:nth-child(3)").textContent;
        var referenceNumber = rowData.querySelector("td:nth-child(4)").textContent;
        var partnerName = rowData.querySelector("td:nth-child(5)").textContent;
        var partnerTIN = rowData.querySelector("td:nth-child(6)").textContent;
        var address = rowData.querySelector("td:nth-child(7)").textContent;
        var businessS = rowData.querySelector("td:nth-child(8)").textContent;
        var serviceCharge = rowData.querySelector("td:nth-child(9)").textContent;
        var transactionFromDate = rowData.querySelector("td:nth-child(10)").textContent;
        var transactionToDate = rowData.querySelector("td:nth-child(11)").textContent;
        var numTransactions = rowData.querySelector("td:nth-child(13)").textContent;
        var amount = rowData.querySelector("td:nth-child(14)").textContent;
        var formula = rowData.querySelector("td:nth-child(15)").textContent;
        var formulaInc_Exc = rowData.querySelector("td:nth-child(16)").textContent;
        var vatAmount = rowData.querySelector("td:nth-child(17)").textContent;
        var netOfVAT = rowData.querySelector("td:nth-child(18)").textContent;
        var withholdingTax = rowData.querySelector("td:nth-child(19)").textContent;
        var totalAmountDue = rowData.querySelector("td:nth-child(20)").textContent;
        var lesswithholdingTaxvalue = rowData.querySelector("td:nth-child(19)").textContent;
        var netAmountDue = rowData.querySelector("td:nth-child(21)").textContent;
        var preparedby = rowData.querySelector("td:nth-child(22)").textContent;
        var reviewedby = rowData.querySelector("td:nth-child(23)").textContent;
        var notedby = rowData.querySelector("td:nth-child(24)").textContent;
        var preparedSignature = rowData.querySelector("td:nth-child(25)").textContent;
        var reviewedSignature = rowData.querySelector("td:nth-child(26)").textContent;
        var notedSignature = rowData.querySelector("td:nth-child(27)").textContent;
        var preparedDateSignature = rowData.querySelector("td:nth-child(28)").textContent;
        var reviewedDateSignature = rowData.querySelector("td:nth-child(29)").textContent;
        var notedDateSignature = rowData.querySelector("td:nth-child(30)").textContent;
        var reviewedFixSignature = rowData.querySelector("td:nth-child(31)").textContent;
        var notedFixSignature = rowData.querySelector("td:nth-child(32)").textContent;
        var cancelledby = rowData.querySelector("td:nth-child(33)").textContent;

       
        document.getElementById("date-value").textContent = date;
        document.getElementById("reference-value").textContent = referenceNumber;
        document.getElementById("customerName-value").textContent = partnerName.substring(0, 45);
        document.getElementById("customerTIN-value").textContent = partnerTIN;
        document.getElementById("address-value").textContent = address.substring(0, 65);
        document.getElementById("businessStyle-value").textContent = businessS;
        document.getElementById("serviceCharge-value").textContent = serviceCharge;
       
        if(formula === 'INCLUSIVE'){
            document.getElementById("transactionFromDate-value").textContent = "From: " + transactionFromDate;
            document.getElementById("transactionToDate-value").textContent = "To: " + transactionToDate;
            document.getElementById("numTransactions-value").textContent = numTransactions;
            document.getElementById("formula-value").textContent = formula;
            document.getElementById("vatAmount-value").textContent = vatAmount; 
            document.getElementById("netOfVAT-value").textContent = netOfVAT;
            document.getElementById("withholdingTax-value").textContent = withholdingTax;

        }else if(formula === 'EXCLUSIVE'){
            document.getElementById("transactionFromDate-value").textContent = "From: " + transactionFromDate;
            document.getElementById("transactionToDate-value").textContent = "To: " + transactionToDate;
            document.getElementById("numTransactions-value").textContent = numTransactions;
            document.getElementById("formula-value").textContent = formula;
            document.getElementById("vatAmount-value").textContent = vatAmount; 
            document.getElementById("netOfVAT-value").textContent = netOfVAT;
            document.getElementById("withholdingTax-value").textContent = withholdingTax;

        }else if(formula === 'NON-VAT'){
            document.getElementById("transactionFromDate-value").textContent = '';
            document.getElementById("transactionToDate-value").textContent = '';
            document.getElementById("num-lbl").textContent = '';
            document.getElementById("numTransactions-value").textContent = '';
            document.getElementById("formula-value").textContent = formula;
            document.getElementById("vatAmount-label").textContent = ''; 
            document.getElementById("vatAmount-value").textContent = '';
            document.getElementById("netOfVat-label").textContent = '';
            document.getElementById("netOfVAT-value").textContent = '';
            document.getElementById("wtax-label").textContent = '';
            document.getElementById("withholdingTax-value").textContent = '';
            document.getElementById("pesos1").textContent = '';
        }
        document.getElementById("amount-modal-value").textContent = amount;
        if(formula === 'INCLUSIVE'){
            document.getElementById("total-amount-value").textContent = amount;
        }else{
        document.getElementById("total-amount-value").textContent = totalAmountDue;
        }
        document.getElementById("less-withholdingTax-value").textContent = withholdingTax;
        document.getElementById("netAmountDue-value").textContent = netAmountDue;
        document.getElementById("preparedInput-value").outerHTML = preparedby.toUpperCase();
        if (status.toLowerCase() === "cancelled") {
            document.getElementById("printBtn").style.display = "none";
            document.getElementById("print-Btn").style.display = "none";
        } else {
            document.getElementById("printBtn").style.display = "block";
            document.getElementById("print-Btn").style.display = "block";
        }
        if (reviewedby.replace(/\s/g, "").toLowerCase() === reviewedFixSignature.replace(/\s/g, "").toLowerCase()) {
            document.getElementById("reviewed-value").style.visibility = "hidden";
        } else {
            var reviewedLabel = document.createElement("span");
            reviewedLabel.textContent = "for: ";
            document.getElementById("reviewed-value").parentNode.insertBefore(reviewedLabel, document.getElementById("reviewed-value"));
            document.getElementById("reviewed-value").outerHTML = reviewedby.toUpperCase();
        }

        if (notedby.replace(/\s/g, "").toLowerCase() === notedFixSignature.replace(/\s/g, "").toLowerCase()) {
            document.getElementById("noted-value").style.visibility = "hidden";
        } else {
            var notedLabel = document.createElement("span");
            notedLabel.textContent = "for: ";
            document.getElementById("noted-value").parentNode.insertBefore(notedLabel, document.getElementById("noted-value"));
            document.getElementById("noted-value").outerHTML = notedby.toUpperCase();
        }

        document.getElementById("prepared-signature").outerHTML = "<input type='text' value='" + preparedSignature + "' style='font-size: 14px; letter-spacing:1px; font-family: Great Vibes' readonly>";
        document.getElementById("reviewed-signature").outerHTML = "<input type='text' value='" + reviewedSignature + "' style='font-size: 14px; letter-spacing:1px; font-family: Great Vibes' readonly>";
        document.getElementById("noted-signature").outerHTML = "<input type='text' value='" + notedSignature + "' style='font-size: 14px; letter-spacing:1px; font-family: Great Vibes' readonly>";
        document.getElementById("preparedDate-signature").outerHTML = "<input type='text' value='" + preparedDateSignature + "' style='font-size: 14px; letter-spacing:1px; font-family: Great Vibes' readonly>";
        document.getElementById("reviewedDate-signature").outerHTML = "<input type='text' value='" + reviewedDateSignature + "' style='font-size: 14px; letter-spacing:1px; font-family: Great Vibes' readonly>";
        document.getElementById("notedDate-signature").outerHTML = "<input type='text' value='" + notedDateSignature + "' style='font-size: 14px; letter-spacing:1px; font-family: Great Vibes' readonly>";
        document.getElementById("reviewedfix-signature").outerHTML = reviewedFixSignature.toUpperCase();
        document.getElementById("notedfix-signature").outerHTML = notedFixSignature.toUpperCase();
        var formulaIncExcValue = document.getElementById("formulaIncExc-value");
        formulaIncExcValue.innerHTML = formulaInc_Exc.replace(/\n/g, "<br>");

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
    window.addEventListener("click", function (event) {
        var modal = document.getElementById("confirmModal");
        if (event.target === modal) {
            closeModal();
        }
    });
// JavaScript code to calculate the total amount and format it with commas and two decimal places
window.addEventListener('DOMContentLoaded', () => {
  const table = document.querySelector('.data-table table');
  if (table) {
    const amountColumnIndex = 13; // Adjust this index based on the column position (0-based index)
    const rows = table.getElementsByTagName('tr');
    let totalAmount = 0;

    for (let i = 1; i < rows.length; i++) {
      const cells = rows[i].getElementsByTagName('td');
      const amount = parseFloat(cells[amountColumnIndex].textContent.replace(/,/g, ''));
      totalAmount += isNaN(amount) ? 0 : amount;
    }

    // Display the total amount with commas and two decimal places
    const totalRow = document.createElement('tr');
    const totalAmountCell = document.createElement('td');
    totalAmountCell.setAttribute('colspan', '22');
    const formattedAmount = totalAmount.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    totalAmountCell.innerHTML = 'Total Amount: <strong>' + formattedAmount + '</strong>';
    totalRow.appendChild(totalAmountCell);
    
    // Move the total amount row to the top of the table
    table.insertBefore(totalRow, table.firstChild);
  }
});

    function printWithModal() {
        var printContents = document.getElementById("confirmModal").innerHTML;
  var headerContent = `
    <center>
      <div style="font-size: 19px; margin-top:80px; margin-bottom: 24px; visibility:hidden;">
        MICHEL J. LHUILLIER FINANCIAL <br>
        SERVICES (PAWNSHOPS), INC. <br>
        <span style="font-size:10px;">
          58 Colon St., Sto. Niño, Cebu City North, Cebu City 6000<br>
          Telephone No. (032) 416-6656 and (032) 232-5681<br>
          VAT REG. TIN: 002-394-238-000
        </span><br>
        <span style="font-size:15px;">STATEMENT OF ACCOUNT</span>
      </div>
    </center>
  `;


  // Apply styles to fit the content in the printable area
  var printableStyle = `
    <style>
    @page {
          size: auto;
          margin: 0;
        }
        body {
          margin: 2cm;
        }
      .modal-content {
        max-width: 100%;
        max-height: 100%;
        overflow: auto;
      }
      .position-print{
        position:relative;
        bottom: 10px;
      }
      .table-signature{
        position:relative;
        bottom:17px;
        right:40px;
        width:75%;
        border-collapse: collapse;
      }
      table {
            width: 65%;
            border-collapse: collapse;
            margin-left: 125px;
            margin-right: auto;
            }

            .print-head {
                visibility:hidden;
            text-align: center;
            padding: 8px;
            font-size: 12px;
            }

            td {
            padding: 2px 5px 2px 5px;
            }

            th,
            td {
            border: none;
            font-size: 12px;
            text-align: left;
            }

            #signature-row {
            border: none;
            }

            .t-date {
            width: 100%;
            max-width: 100%;
            text-align: right;
            }

            .reference-div {
            width: 100%;
            max-width: 100%;
            text-align: right;
            margin-bottom: 5px;
            }
            .peso-sign{
                position:relative;
                bottom: 5px;
                margin-left: 90px;
            }
            .signature-inp{
                display:none;
            }
            .Datesignature-inp{
                display:none;
            }
            #date-lbl {
            font-size: 12px;
            margin-right: 90px;
            margin-right: 0px;
            visibility:hidden;
            }

            #reference-lbl {
            font-size: 12px;
            margin-right: 10px;
            visibility:hidden;
            }

            #partnerName-lbl {
            font-size: 12px;
            margin-right: 15px;
            margin-left: 125px;
            visibility:hidden;
            }

            #partnerTin-lbl {
            font-size: 12px;
            margin-right: 25px;
            margin-left: 125px;
            visibility:hidden;
            }

            #address-lbl {
            font-size: 12px;
            margin-right: 43px;
            margin-left: 125px;
            visibility:hidden;
            }

            #business-lbl {
            font-size: 12px;
            margin-right: 12px;
            margin-left: 125px;
            visibility:hidden;
            }
            #date-value,
            #reference-value,
            #address-value,
            #customerName-value,
            #customerTIN-value,
            #businessStyle-value {
            font-size: 14px;
            }
            #date-value,
            #reference-value {
            margin-right: 105px;
            }
            #address-value{
            margin-left: 12px;
            max-width: 200px;
            width:200px;
            }
            #businessStyle-value{
            margin-left: 12px;
            }
            .reviewedby-inp{
                padding-top: 2px;
                font-size:10px;
            }
            .prepared-inp{
                margin-top: 18px;
                font-size:10px;
            }
            .notedby-inp{
                padding-top: 2px;
                font-size:10px;
            }
            .total-amount-div,
            .less-withax-div,
            .netamountDue-div{
                padding-top: 2px;
            }

            .total-amount-div,
            .less-withax-div,
            .netamountDue-div,
            #amount-content,
            #vat-div,
            #netvat-div,
            #wthax-div {
            display: flex;
            justify-content: space-between;
            text-align: right;
            font-size: 12px;
            }
            #totalAmount-lbl{
                visibility:hidden;
            }
            #L-wtax-lbl{
                visibility:hidden;
            }
            #netAmountDue-lbl{
                visibility:hidden;
            }
            #prepared-lbl{
                visibility:hidden;
            }
            #accountingStaff-lbl{
                visibility:hidden;
            }
            #reviewedby-lbl{
                visibility:hidden;
            }
            #departmentManager-lbl{
                visibility:hidden;
            }
            #notedby-lbl{
                visibility:hidden;
            }
            #divisionHead-lbl{
                visibility:hidden;
            }
            #signature{
                display:none;
            }
            #preparedfix-signature{
                display:none;
            }
            #total-amount-value{
                position:relative;
                bottom:5px;
            }
            #netAmountDue-value{
                position:relative;
                bottom:5px;
            }
            #less-withholdingTax-value{
                position:relative;
                bottom:5px;
            }
            .notedby-inp{
                font-size:10px;
                font-style: italic;
            }
            .reviewedby-inp{
                font-size:10px;
                font-style: italic;
            }
            #preparedfix-signature{
                font-size:10px;
                font-style: italic;
            }
            .prepared-inp{
                font-size:10px;
            }
            #pesos1{
                position:relative;
                right:9px;
            }
            #pesos2{
                position:relative;
                right:4px;
            }
            #pesos3{
                position:relative;
                right:7px;
            }
           .modal-buttons{
                display:none;
            }
            @media print {
            table {
                font-size: 12px; /* Adjust the font size for printing */
            }

            .print-head {
                font-size: 12px; /* Adjust the font size for printing */
            }

            th,
            td {
                font-size: 12px; /* Adjust the font size for printing */
            }

            #address-value {
                font-size: 10px; /* Adjust the font size for printing */

            }
            #customerName-value{
                font-size: 12px;
            }
            #preparedInput-value{
                font-size: 10px;
            }
            #reviewed-value{
                font-size: 10px;
            }
            #noted-value{
                font-size: 10px;
            }
            #fix-signature{
                font-size: 10px;
            }
            }

                /* Add any other necessary styles here */
                </style>
            `;

            var printableContent = `
                <html>
                <head>
                    ${printableStyle}
                </head>
                <body>
                    ${headerContent}
                    ${printContents}
                </body>
                </html>
            `;

            var printWindow = window.open("", "_blank");
            printWindow.document.open();
            printWindow.document.write(printableContent);
            printWindow.document.close();
            printWindow.print();
            printWindow.close();
            }

    function printModal() {
  var printContents = document.getElementById("confirmModal").innerHTML;
  var headerContent = `
    <center>
      <div style="font-size: 19px; margin-top:80px; margin-bottom: 22px;">
        MICHEL J. LHUILLIER FINANCIAL <br>
        SERVICES (PAWNSHOPS), INC. <br>
        <span style="font-size:10px;">
          58 Colon St., Sto. Niño, Cebu City North, Cebu City 6000<br>
          Telephone No. (032) 416-6656 and (032) 232-5681<br>
          VAT REG. TIN: 002-394-238-000
        </span><br>
        <span style="font-size:15px;">STATEMENT OF ACCOUNT</span>
      </div>
    </center>
  `;


  // Apply styles to fit the content in the printable area
  var printableStyle = `
    <style>
    @import url('https://fonts.googleapis.com/css2?family=Great+Vibes&display=swap');
    @page {
          size: auto;
          margin: 0;
        }
        body {
          margin: 2cm;
        }
      .modal-content {
        max-width: 100%;
        max-height: 100%;
        overflow: auto;
      }
      .table-signature{
        width:75%;
        border-collapse: collapse;
        margin-top: 20px;
      }
      .position-print{
        margin-top: 15px;
      }
      table {
        margin-top: 10px;
            width: 66%;
            border-collapse: collapse;
            margin-left: 125px;
            margin-right: auto;
            }

            .print-head {
            text-align: center;
            padding: 8px;
            font-size: 12px;
            }

            td {
            padding: 2px 5px 2px 5px;
            }

            th,
            td {
                border-collapse: collapse;
            border: 1px solid black;
            font-size: 12px;
            text-align: left;
            }

            #signature-row {
            border: none;
            }

            .t-date {
            width: 100%;
            max-width: 100%;
            text-align: right;
            }

            .reference-div {
            width: 100%;
            max-width: 100%;
            text-align: right;
            }

            #date-lbl {
            font-size: 12px;
            margin-right: 90px;
            margin-right: 0px;
            }

            #reference-lbl {
            font-size: 12px;
            margin-right: 10px;
            }

            #partnerName-lbl {
            font-size: 12px;
            margin-right: 15px;
            margin-left: 125px;
            }

            #partnerTin-lbl {
            font-size: 12px;
            margin-right: 25px;
            margin-left: 125px;
            }

            #address-lbl {
            font-size: 12px;
            margin-right: 43px;
            margin-left: 125px;
            }

            #business-lbl {
            font-size: 12px;
            margin-right: 12px;
            margin-left: 125px;
            }
            #date-value,
            #reference-value,
            #address-value,
            #customerName-value,
            #customerTIN-value,
            #businessStyle-value {
            border-bottom: 1px solid #000;
            font-size: 12px;
            }
            #date-value,
            #reference-value {
            margin-right: 105px;
            }
            #address-value{
            margin-left: 12px;
            }
            #businessStyle-value{
            margin-left: 12px;
            }
         
            .total-amount-div,
            .less-withax-div,
            .netamountDue-div,
            #amount-content,
            #vat-div,
            #netvat-div,
            #wthax-div {
            display: flex;
            justify-content: space-between;
            text-align: right;
            font-size: 12px;
            }
            #signature{
                font-family: 'Great Vibes', cursive;
                font-size: 12px;
                border:none;
                width:100%;
            }
            .signature-inp{
                margin-top: 1px;
                visibility:hidden;
            }
            .Datesignature-inp{
                visibility:hidden;
            }
            .notedby-inp{
                font-size:10px;
                margin-bottom: 8px;
                font-style: italic;
            }
            .reviewedby-inp{
                font-size:10px;
                margin-bottom: 8px;
                font-style: italic;
            }
            #preparedfix-signature{
                font-size:10px;
                margin-bottom: 8px;
                font-style: italic;
            }
            .prepared-inp{
                font-size:10px;
            }
            #pesos1{
                position:relative;
                right:9px;
            }
            #pesos2{
                position:relative;
                right:4px;
            }
            #pesos3{
                position:relative;
                right:7px;
            }
            .modal-buttons{
                display:none;
            }
            @media print {
            table {
                font-size: 12px; /* Adjust the font size for printing */
            }

            .print-head {
                font-size: 12px; /* Adjust the font size for printing */
            }

            th,
            td {
                font-size: 12px; /* Adjust the font size for printing */
            }

            #address-value {
                font-size: 10px; /* Adjust the font size for printing */
            }
            #customerName-value{
                font-size: 12px;
            }
            #preparedInput-value{
                font-size: 10px;
            }
            #reviewed-value{
                font-size: 10px;
            }
            input{
                border:none;
            }
            #noted-value{
                font-size: 10px;
            }
            #notedfix-signature{
                font-size: 10px;
            }
            }

                /* Add any other necessary styles here */
                </style>
            `;

            var printableContent = `
                <html>
                <head>
                    ${printableStyle}
                </head>
                <body>
                    ${headerContent}
                    ${printContents}
                </body>
                </html>
            `;

            var printWindow = window.open("", "_blank");
            printWindow.document.open();
            printWindow.document.write(printableContent);
            printWindow.document.close();
            printWindow.print();
            printWindow.close();
            }
</script>

</body>
</html>
