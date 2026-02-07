<?php
        $conn = mysqli_connect('localhost', 'root', 'Password1', 'mldb');
        session_start();
        @include '../fetch/fetch-partner-data.php';
        @include '../fetch/fetch-service-type.php';

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
            <title>Admin Dashboard</title>
            <!-- custom CSS file link  -->
            <link rel="stylesheet" href="../css/admin_page.css?v=<?php echo time(); ?>">
        </head>
    <body>
    <form id="partnerForm" action="" method="POST">
        <div class="container">
            <div class="top-content">
                <div class="nav-container">
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
            </div>
        </div>

        <div class="soa-div">
            <div class="header">
                <h5>STATEMENT OF ACCOUNT</h5>
            </div>
            <div class="soa-inputs">
                <div id="date-div">
                <div id="label">
                    <label for="">Date:</label>
                </div>
                 <!-- Hidden input field to store the date in the desired format -->
                 <input type="hidden" id="transaction-date-hidden" name="transaction-date" value="<?php echo date('Y-m-d'); ?>">

                <!-- Visible input field to display the formatted date -->
                <input type="text" id="transaction-date" value="<?php echo date('F j, Y'); ?>" readonly>
                </div>
                <div id="reference-div">
                <div id="label">
                    <label for="">Reference No:</label>
                </div>
                <input type="text" id="reference-number" name="reference_number" readonly value="">
                </div>
                <div id="customer-div">
                <div id="label">
                    <label for="">Partner Name:</label>
                </div>
                <select class="form-control" onchange="updateSoaForm()" id="partner-select" name="partnerName">
                    <option value=""></option>
                    <?php
                    // Sort the $options array by partner_name in ascending order
                    usort($options, function($a, $b) {
                        return strcmp($a['partner_name'], $b['partner_name']);
                    });

                    foreach ($options as $option) {
                        $selected = ($_SESSION['partnerName_soa'] === $option['partner_name']) ? 'selected' : '';
                        echo '<option value="'.$option['partner_name'].'" data-reference="' . $option['abbreviation'] . "-" . $option['series_number'] . '" data-address="' . $option['address'] . '" data-businessStyle="' . $option['businessStyle'] . '" data-partner-tin="' . $option['partnerTin'] . '" data-partnerid="' . $option['partner_id'] . '" data-servicecharge="' . $option['serviceCharge'] . '" data-formula ="' . $option['inc_exc'] . '" ' . $selected . '>' . $option['partner_name'] . '</option>';
                    }
                    ?>
                </select>

                <input style="display:none;" type="text" id="partnerID" name="partnerID" value="<?php echo $partnerID; ?>" readonly required>
                </div>
                <div id="customerTin-div">
                <div id="label">
                    <label for="">Partner TIN:</label>
                </div>
                <input type="text" id="customerTin" name="customerTin" readonly value="">
                </div>
                <div id="address-div">
                <div id="label">
                    <label for="">Address:</label>
                </div>
                <input type="text" id="address" name="address" value="">
                </div>
                <div id="business-div">
                <div id="label">
                    <label for="">Business Style:</label>
                </div>
                <input type="text" id="businessStyle" name="businessStyle" value="">
                </div>
                <div id="service-charge-div">
                    <div id="label">
                        <label for="service-charge">Service Charge:</label>
                    </div>
                        <input type="text" id="servicetype-select" name="service_Type" value="" readonly>
                </div>
                <div id="date-range-div">
                <div id="label">
                    <label for="from-date">From Date:</label>
                </div>
                <input type="date" id="from-date" name="fromDate" value="" required>

                <div id="label">
                    <label for="to-date">To Date:</label>
                </div>
                <input type="date" id="to-date" name="toDate" value="" required>
                </div>
                <div id="poNumber-div">
                <div id="label">
                    <label for="" id="po_numberlbl">PO Number:</label>
                </div>
                <input type="text" id="poNumber" name="poNumber" value="" autocomplete="off">
                </div> 
                <div id="numberOfTransaction-div">
                    <div id="numOfTransaction">
                        <label for="" id="numOfTransaction">Number of Transactions:</label>
                    </div>
                    <input type="text" id="numberOfTransaction" name="numberOfTransaction" value="" required>
                </div>
                <div id="amount-div">
                    <div id="amountlbl">
                        <label for="">Amount:</label>
                    </div>
                    <input type="text" id="amount" name="amount" value="" required onkeyup="soa_trap()">
                </div>
                <div id="addAmount-div" style="display: none;">
                    <div id="addAmountlbl">
                        <label for="" id="addAmount_lbl">Add Amount:</label>
                    </div>
                    <input type="text" id="addAmount" name="addAmount" value="">
                </div>
                <div id="multiplyAmount-div" style="display: none;">
                    <div id="multiplyAmountlbl">
                        <label for="" id="multiplyAmount_lbl">Number of Days:</label>
                    </div>
                    <input type="number" id="multiplyAmount" name="multiplyAmount" value="">
                </div>

                <div class="process-div">
                <button type="button" id="process" name="process" disabled>Process</button>     
                </div>
            </div>
            </div>
        <div class="container-div">
            <div class="content-div">
                <table class="transaction-table">
                    <thead>
                        <th id="particulars">PARTICULARS</th>
                        <th id="head-amount" colspan="2">AMOUNT</th>
                    </thead>
                    <tbody>
                        <tr id="table-row">
                            <td id="row">
                                <div id="serviceCharge-div">
                                    <input type="text" name="service-charge-t" id="service-charge-t" value="" readonly>
                                </div>
                                <div id="number-of-transactions">
                                    <label for="">Number of transactions:</label>
                                    <input type="text" id="numberTransaction" name="numberTransaction" value="" readonly>
                                </div>
                                <span id="from-span">From:</span>
                                <input type="text" id="from-date-range" name="from-date-range" value="<?php echo isset($_POST['fromDate']) ? date('F d, Y', strtotime($_POST['fromDate'])) : ''; ?>" readonly>
                                <span id="to-span">To:</span>
                                <input type="text" id="to-date-range" name="to-date-range" value="<?php echo isset($_POST['toDate']) ? date('F d, Y', strtotime($_POST['toDate'])) : ''; ?>" readonly>
                                <div id="formula">
                                    <label for="" id="formulalbl">Formula: </label>
                                    <input type="text" id="formulaInp"  name="formula" value="">
                                    <br>
                                    <div class="formula-content" id="formulaInc" style="display: none;">
                                    <textarea name="formulaIncDisplay" id="formulaIncDisplay" cols="1" rows="3">VAT Amount 12% = (Amount * 12%) / 1.12 
Net of VAT = Amount - VAT Amount 
WTax = Net of VAT * 2%</textarea>
                                    </div>
                                    <div class="formula-content" id="formulaExc" style="display: none;">
                                    <textarea name="formulaExcDisplay" id="formulaExcDisplay" cols="1" rows="3">VAT Amount 12% = Amount * 12% 
WTax = Amount * 2%</textarea>
                                    </div>
                                    <div class="formula-content" id="formulaNon" style="display: none;">
                                    <textarea name="formulaNonVat" id="formulaNonVat" cols="1" rows="3"></textarea>
                                    </div>
                                </div>
                            </td>
                            <td id="row" colspan="2">
                                <div id="amountRow">
                                    <div class="col">
                                        <div class="label">
                                            <label for="" id="lbl-amount"> Amount:</label>
                                        </div>
                                        <div class="pesos-div">
                                            <h4 class="green-peso">&#8369;</h4>
                                        </div>
                                        <div class="amount-inp">
                                            <input type="text" id="amount-t" name="amount-t" value="" onkeyup="soa_trap()" maxlength="15" readonly>
                                        </div>
                                    </div>
                                    <div class="col">
                                        <div class="label">
                                            <label for="" id="lbl"> VAT Amount (12%):</label>
                                        </div>
                                        <div class="pesos-div">
                                            <h4 class="green-peso">&#8369;</h4>
                                        </div>
                                        <div class="vat-inp">
                                            <input type="text" id="vat-amount" class="vat-amount" name="vat-amount" value=""  maxlength="15" readonly>
                                        </div>
                                    </div>
                                    <div class="col">
                                        <div class="label">
                                            <label for="" id="lbl"> Net of VAT:</label>
                                        </div>
                                        <div class="pesos-div">
                                            <h4 class="green-peso">&#8369;</h4>
                                        </div>
                                        <div class="net-inp">
                                            <input type="text" id="net-amount" class="net-amount" name="net-amount" value="" maxlength="15" readonly>
                                        </div>
                                    </div>
                                    <div class="col">
                                        <div class="label">
                                            <label for="" id="lbl"> Withholding Tax (2%):</label>
                                        </div>
                                        <div class="pesos-div">
                                            <h4 class="green-peso">&#8369;</h4>
                                        </div>
                                        <div class="wtax-inp">
                                            <input type="text" id="wtax-amount" class="wtax-amount" name="wtax-amount" value=""  maxlength="15" readonly>
                                        </div>
                                    </div>
                                    <div class="col" id="add_Amount" style="display:none;">
                                        <div class="label">
                                            <label for="" id="lbl"> Add Amount:</label>
                                        </div>
                                        <div class="pesos-div">
                                            <h4 class="green-peso">&#8369;</h4>
                                        </div>
                                        <div class="addAmount-inp">
                                            <input type="text" id="addAmount-amount" class="addAmount-amount" name="addAmount-amount" value=""  maxlength="15" readonly>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <tr id="table-row">
                            <td id="t-row" style="text-align:right; border-top:none; font-size: 12px; border-bottom:none; padding-right: 10px;">TOTAL AMOUNT DUE</td>
                            <td id="t-row" colspan="2">
                                <div class="col">
                                    <div class="pesos-div2">
                                        <h4 class="green-peso">&#8369;</h4>
                                    </div>
                                    <div class="totalAmountDue-inp">
                                        <input type="text" id="totalAmount" class="totalAmount" name="totalAmount" value="" maxlength="15" readonly>
                                    </div>
                                </div>
                            </td>
                        </tr>

                        <tr id="table-row">
                            <td id="t-row" style="text-align: right; border-top: none; font-size: 12px; border-bottom: none; padding-right: 10px;">LESS WITHHOLDING TAX</td>
                            <td id="t-row" colspan="2">
                                <div class="col">
                                    <div class="pesos-div2">
                                        <h4 class="green-peso">&#8369;</h4>
                                    </div>
                                    <div class="lesswtax-inp">
                                        <input type="text" id="lesswtax-amount" class="lesswtax-amount" name="lesswtax-amount" value="" maxlength="15" readonly>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <tr id="table-row">
                            <td id="t-row" style="text-align:right; border-top:none; font-size: 12px; border-bottom:none; padding-right: 10px;">NET AMOUNT DUE</td>
                            <td id="t-row" colspan="2">
                                <div class="col">
                                    <div class="pesos-div2">
                                        <h4 class="green-peso">&#8369;</h4>
                                    </div>
                                    <div class="netAmount-inp">
                                        <input type="text" id="netAmount-amount" class="netAmount-amount" name="netAmount-amount" value="" maxlength="15" readonly>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="table-div">
            <table class="sig-div">
                <tr>
                        <td id="signature-row">
                            <div class="preparedby-lbl">
                                <label for="">Prepared by:</label>
                            </div>
                            <div class="signature-inp">
                                <input type="text" id="signature" class="signature" name="signature" value="" readonly>
                            </div>
                            <div class="dateSignature-inp">
                                <input type="hidden" id="dateSignature" class="dateSignature" name="preparedDateSignature" value="<?php echo date('m-d-Y')?>" readonly>
                            </div>
                            <div class="preparedFix-inp">
                                <input type="text" id="preparedFix_signature" class="preparedFix_signature" name="preparedFix_signature" value="" readonly>
                            </div>
                            <div class="prepared-inp">
                                <input type="text" id="preparedInput" class="preparedInput" name="preparedInput" value="" readonly>
                            </div>
                            <div class="position-lbl">
                                <label for="">Accounting Staff</label>
                            </div>
                        </td>
                        <td id="signature-row">
                            <div class="reviewedby-lbl">
                                <label for="">Reviewed by:</label>
                            </div>
                            <div class="signature-inp">
                                <input type="text" id="signature" class="signature" name="signature" value="" readonly>
                            </div>
                            <div class="dateSignature-inp">
                                <input type="hidden" id="dateSignature" class="dateSignature" name="reviewedDateSignature" value="" readonly>
                            </div>
                            <div class="reviewedFix-inp">
                                <input type="text" id="reviewedFix_signature" class="reviewedFix_signature" name="reviewedFix_signature" value="" readonly>
                            </div>
                            <div class="reviewedby-inp">
                                <input type="text" id="reviewdbyInput" class="reviewdbyInput" name="reviewdbyInput" value="" readonly>
                            </div>
                           
                            <div class="position-lbl">
                                <label for="">Department Manager</label>
                            </div>
                        </td>
                        <td id="signature-row">
                            <div class="notedby-lbl">
                                <label for="">Noted by:</label>
                            </div>
                            <div class="signature-inp">
                                <input type="text" id="signature" class="signature" name="signature" value="" readonly>
                            </div>
                            <div class="dateSignature-inp">
                                <input type="hidden" id="dateSignature" class="dateSignature" name="notedDateSignature" value="" readonly>
                            </div>
                            <div class="notedFix-inp">
                                <input type="text" id="notedfix_signature" class="notedfix_signature" name="notedfix_signature" value="" readonly>
                            </div>
                            <div class="notedby-inp">
                                <input type="text" id="noteInput" class="noteInput" name="noteInput" value="" readonly>
                            </div>
                            
                            <div class="position-lbl">
                                <label for="">Division Head</label>
                            </div>
                        </td>
                </tr>
            </table>
            <div class="submit-btn">
                <button type="button" id="saveBtn" class="saveBtn" name="saveBtn" onclick="showConfirmModal()" disabled>Save</button>
            </div>
        </div>

        <!-- Add the confirm modal -->
        <div id="confirmModal" class="modal">
            <div class="modal-content">
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
                        <input type="text" id="reference" name="reference" value="<?php if(isset($_POST['reference_number'])) echo $_POST['reference_number']; ?>" readonly><br>
                    </div>
                    <div class="customer-div">
                        <label for="customerName">Partner Name:</label>
                        <input type="text" id="customerName" name="customerName" onkeyup="soa_trap()" value="<?php if(isset($_POST['partnerName'])) echo $_POST['partnerName']?>" readonly><br>
                    </div>
                    <div class="customerTin-div">
                        <label for="customerTIN">Partner TIN:</label>
                        <input type="text" id="customerTIN" name="customerTIN" value="" readonly><br>
                    </div>
                    <div class="serviceCharge-div">
                        <label for="serviceCharge">Service Charge:</label>
                        <input type="text" id="serviceCharge" name="serviceCharge" onkeyup="soa_trap()" value="<?php echo isset($_POST['service_Type']) ? $_POST['service_Type'] : ''; ?>" readonly><br>        
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
                        <input type="text" id="withholdingTax" name="withholdingTax"value="" readonly>
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
                    <button type="submit" id="confirmBtn" name="confirmBtn" class="confirmBtn" formaction="admin_save-soaTransactions.php">Confirm</button>
                    <button type="button" class="closeBtn" onclick="closeModal()">Close</button>
                </div>
            </div>
        </div>
    </form>
        <script>
            // Get the hidden input element
            var hiddenInput = document.getElementById('transaction-date-hidden');

            // Get the visible input element
            var visibleInput = document.getElementById('transaction-date');

            // Format the date and set it as the value of the visible input
            visibleInput.value = new Date(hiddenInput.value).toLocaleDateString('en-US', {
            month: 'long',
            day: '2-digit',
            year: 'numeric'
            });
            function updateSoaForm() {
            var entitySelect = document.getElementById('partner-select');
            var partnerID = document.getElementById('partnerID');
            var referenceNumber = document.getElementById('reference-number');
            var customerTin = document.getElementById('customerTin');
            var businessStyle = document.getElementById('businessStyle');
            var address = document.getElementById('address');
            var processButton = document.getElementById('process');
            var formula = document.getElementById('formulaInp');
            var serviceChargeInput = document.getElementById('servicetype-select');
            var formulaInc = document.getElementById('formulaInc');
            var formulaExc = document.getElementById('formulaExc');
            var formulaNon = document.getElementById('formulaNon');
            var addAmount = document.getElementById('addAmount');
            var addAmountlbl = document.getElementById('addAmountlbl');
           
            var selectedOption = entitySelect.options[entitySelect.selectedIndex];
            if (entitySelect.value !== "") {
                var abbreviation = selectedOption.getAttribute('data-reference').split("-")[0];
                var seriesNumber = selectedOption.getAttribute('data-reference').split("-")[1];
                var partnerTin = selectedOption.getAttribute('data-partner-tin');
                var businessStyleValue = selectedOption.getAttribute('data-businessStyle');
                var addressValue = selectedOption.getAttribute('data-address');
                var referenceValue = abbreviation + '-' + seriesNumber;
                var partnerIDValue = selectedOption.getAttribute('data-partnerid');
                var serviceCharge = selectedOption.getAttribute('data-servicecharge');
                var formulaValue = selectedOption.getAttribute('data-formula');
                if (partnerTin === "005-519-158-000") {
                    document.getElementById("addAmount-div").style.display = "flex";
                    document.getElementById("multiplyAmount-div").style.display = "flex";
                    document.getElementById("add_Amount").style.display = "flex";
                    document.getElementById("addAmountDue-div").style.display = "block";
                }
                    partnerID.value = partnerIDValue;
                    referenceNumber.value = referenceValue;
                    customerTin.value = partnerTin;
                    businessStyle.value = businessStyleValue;
                    address.value = addressValue;
                    serviceChargeInput.value = serviceCharge;
                    formula.value = formulaValue;
                    if (formulaValue === 'INCLUSIVE') {
                        formulaInc.style.display = 'block';
                        formulaExc.style.display = 'none';
                        formulaNon.style.display = 'none';
                    } else if (formulaValue === 'EXCLUSIVE') {
                        formulaInc.style.display = 'none';
                        formulaExc.style.display = 'block';
                        formulaNon.style.display = 'none';
                    } else if(formulaValue == 'NON-VAT'){
                        formulaInc.style.display = 'none';
                        formulaExc.style.display = 'none';
                        formulaNon.style.display = 'block';
                    }
                } else {
                    partnerID.value = '';
                    referenceNumber.value = '';
                    customerTin.value = '';
                    businessStyle.value = '';
                    address.value = '';
                    serviceChargeInput.value = '';
                    formula.value = '';
                    formulaInc.style.display = 'none';
                    formulaExc.style.display = 'none';
                
                }
            }

            function showConfirmModal() {
                var confirmModal = document.getElementById('confirmModal');
                confirmModal.style.display = "block";
            }
            function closeModal() {
                var confirmModal = document.getElementById('confirmModal');
                confirmModal.style.display = "none";
            }
            document.addEventListener('DOMContentLoaded', function() {
                var processButton = document.getElementById('process');
                var amountInput = document.getElementById('amount');
                var addAmountInput = document.getElementById('addAmount');
                var numberOfDaysInput = document.getElementById('multiplyAmount');
                var addAmountDisplay = document.getElementById('addAmount-amount');
                var amountDisplay = document.getElementById('amount-t');
                var vat_amountDisplay = document.getElementById('vat-amount');
                var net_amountDisplay = document.getElementById('net-amount');
                var wtax_amountDisplay = document.getElementById('wtax-amount');
                var totalAmountDisplay = document.getElementById('totalAmount');
                var lesswtaxAmountDisplay = document.getElementById('lesswtax-amount');
                var netAmountDueDisplay = document.getElementById('netAmount-amount');
                var serviceChargeInput = document.getElementById('servicetype-select');
                var serviceChargeDisplay = document.getElementById('service-charge-t');
                var transactionNumberInput = document.getElementById('numberOfTransaction');
                var transactionNumberDisplay = document.getElementById('numberTransaction');
                var fromDateInput = document.getElementById('from-date');
                var fromDateDisplay = document.getElementById('from-date-range');

               processButton.addEventListener('click', function() {
                var formula = document.getElementById('formulaInp');
                var addAmount = document.getElementById('addAmount');
                var customerTin = document.getElementById('customerTin');
                var numberOfDays = document.getElementById('multiplyAmount');

                    // Retrieve the amount from the input field
                    var amount = parseFloat(amountInput.value);
                    var addAmount = parseFloat(addAmountInput.value);
                    var numOfDays =parseFloat(numberOfDaysInput.value)
                    if(formula.value === 'INCLUSIVE'){
                        // Compute VAT Amount
                        var vatAmount = (amount * 0.12) / 1.12;
                        // Compute Net of VAT
                        var netOfVat = amount - vatAmount;
                         // Compute WTax
                        var wTax = netOfVat * 0.02;
                        // Compute Amount - WTax
                        var amountMinusWTax = amount - wTax;

                        // Format the computed values as numbers with 2 decimal places
                        var formatter = new Intl.NumberFormat('en-US', {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2,
                        });
                    }
                    if(formula.value === 'EXCLUSIVE'){
                         // Compute VAT Amount
                         var vatAmount = amount * 0.12;
                        // Compute Net of VAT
                        var netOfVat = '';
                         // Compute WTax
                        var wTax = amount * 0.02;

                        var totalAmount = amount + vatAmount;
                        // Compute Amount - WTax
                        var amountMinusWTax = totalAmount - wTax;
                        var addAmountTotal = addAmount *numOfDays;
                        // Format the computed values as numbers with 2 decimal places
                        var formatter = new Intl.NumberFormat('en-US', {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2,
                        });
                    }
                    if (customerTin.value === '005-519-158-000') {
                        var addAmountTotal = addAmount *numOfDays;
                        amountMinusWTax += addAmount * numOfDays;
                    }
                   
                    var userName = "<?php echo $_SESSION['admin_name']; ?>"; // Retrieve the username from PHP session
                    var fromDateInput = document.getElementById('from-date');
                    var fromDateDisplay = document.getElementById('from-date-range')
                    var toDateInput = document.getElementById('to-date');
                    var toDateDisplay = document.getElementById('to-date-range');
                   
                    preparedInput.value = userName;
                    if(formula.value === 'NON-VAT'){
                        amountDisplay.value = amount.toFixed(2);
                        totalAmountDisplay.value = amount.toFixed(2);
                    }
                    if(formula.value === 'INCLUSIVE'){
                        amountDisplay.value = amount.toFixed(2);
                    }
                    if(formula.value === 'EXCLUSIVE'){
                        amountDisplay.value = amount.toFixed(2);
                    }
                    addAmountDisplay.value = isNaN(addAmountTotal) ? '' : addAmountTotal.toFixed(2);
                    vat_amountDisplay.value = isNaN(vatAmount) ? '' : formatter.format(vatAmount);
                    wtax_amountDisplay.value = isNaN(wTax) ? '' : formatter.format(wTax);
                    net_amountDisplay.value = isNaN(netOfVat) ? '' : formatter.format(netOfVat);
                    if (formula.value === 'INCLUSIVE') {
                    totalAmountDisplay.value = amount.toLocaleString('en-US', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    });
                    } else {
                    totalAmountDisplay.value = totalAmount.toLocaleString('en-US', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    });
                    }

                    lesswtaxAmountDisplay.value = isNaN(wTax) ? '' : formatter.format(wTax);
                    if(formula.value === 'INCLUSIVE'){
                        netAmountDueDisplay.value = formatter.format(amountMinusWTax);
                    }else if(formula.value === 'EXCLUSIVE'){
                        netAmountDueDisplay.value = formatter.format(amountMinusWTax);
                    }
                    if(customerTin === '005-519-158-000'){
                        netAmountDueDisplay.value = formatter.format(amountMinusWTax);
                    }
                    var serviceChargeValue = serviceChargeInput.value;
                    serviceChargeDisplay.value = serviceChargeValue;
                    var transactionNumberValue = transactionNumberInput.value;
                    transactionNumberDisplay.value = transactionNumberValue;

                    // Retrieve fromDate and toDate values from PHP session
                    var fromDateValue = fromDateInput.value;
                    var toDateValue = toDateInput.value;

                    // Set the values of fromDateInput and toDateInput
                    fromDateDisplay.value = fromDateValue;
                    toDateDisplay.value = toDateValue;
                });

            });
            document.addEventListener('DOMContentLoaded', function() {
            var dateInput = document.getElementById('transaction-date');
            var dateDisplay = document.getElementById('date');
            var referenceInput = document.getElementById('reference-number');
            var referenceDisplay = document.getElementById('reference');
            var partnerSelectInput = document.getElementById('partner-select');
            var partnerSelectDisplay = document.getElementById('customerName');
            var partnerTinInput = document.getElementById('customerTin');
            var partnerTinDisplay = document.getElementById('customerTIN');
            var serviceChargeInput = document.getElementById('servicetype-select');
            var serviceChargeDisplay = document.getElementById('serviceCharge');
            var transactionNumberInput = document.getElementById('numberOfTransaction');
            var transactionNumberDisplay = document.getElementById('numTransactions');
            var fromDateInput = document.getElementById('from-date-range');
            var fromDateDisplay = document.getElementById('transactionFromDate');
            var toDateInput = document.getElementById('to-date-range');
            var toDateDisplay = document.getElementById('transactionToDate');

            var saveButton = document.getElementById('saveBtn');
            var amountInputModal = document.getElementById('amount-t');
            var amountDisplayModal = document.getElementById('amount-modal');
            var vat_amountInput = document.getElementById('vat-amount');
            var vatAmountDisplay = document.getElementById('vatAmount');
            var net_amountInput = document.getElementById('net-amount');
            var netAmountDisplay = document.getElementById('netOfVAT');
            var wtax_amountInput = document.getElementById('wtax-amount');
            var wtaxAmountDisplay = document.getElementById('withholdingTax');
            var netAmountDueInput = document.getElementById('netAmount-amount');
            var netAmountDueDisplay = document.getElementById('netAmountDue');
            var addAmountInput = document.getElementById('addAmount-amount');
            var addAmountDisplay = document.getElementById('addAmountInp');

            saveButton.addEventListener('click', function() {
                    var formula = document.getElementById('formulaInp');
                    var customerTIN = document.getElementById('customerTin');
                    var numberOfDays = document.getElementById('multiplyAmount');

                    // Retrieve the value of inputs
                    var amountTValue = parseFloat(amountInputModal.value);
                    var addAmountValue = parseFloat(addAmountInput.value);
                    var numOfDays = parseFloat(numberOfDays.value);

                    var vatAmountValue, netAmountValue, wtaxAmountValue, netAmountDueValue, totalAmountValue;
                    
                    if (formula.value === 'INCLUSIVE') {
                        vatAmountValue = (amountTValue * 0.12) / 1.12;
                        netAmountValue = amountTValue - vatAmountValue;
                        wtaxAmountValue = netAmountValue * 0.02;
                        netAmountDueValue = amountTValue - wtaxAmountValue;
                    }

                    if (formula.value === 'EXCLUSIVE') {
                        vatAmountValue = amountTValue * 0.12;
                        netAmountValue = '';
                        wtaxAmountValue = amountTValue * 0.02;
                        totalAmountValue =amountTValue + vatAmountValue;
                        netAmountDueValue = totalAmountValue - wtaxAmountValue;
                        var addAmountTotal = addAmountValue * numOfDays;

                    }

                    // Check if customerTIN is equal to 005-519-158-000
                    if (customerTIN.value === '005-519-158-000') {
                       
                        netAmountDueValue += addAmountValue;
                    }

                    var dateValue = dateInput.value;
                    var referenceValue = referenceInput.value;
                    var partnerSelectValue = partnerSelectInput.value;
                    var partnerTinValue = partnerTinInput.value;
                    var serviceChargeValue = serviceChargeInput.value;
                    var transactionNumberValue = transactionNumberInput.value;

                    // Format the computed values as numbers with 2 decimal places and comma as thousand separator
                    var formatter = new Intl.NumberFormat('en-US', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2,
                    });

                    // Print the values to the respective display inputs
                    amountDisplayModal.value = formatter.format(amountTValue);
                    addAmountDisplay.value = isNaN(addAmountValue) ? '' : formatter.format(addAmountValue);
                    vatAmountDisplay.value = isNaN(vatAmountValue) ? '' : formatter.format(vatAmountValue);
                    netAmountDisplay.value = isNaN(netAmountDueValue) ? '' : formatter.format(netAmountValue);
                    wtaxAmountDisplay.value = isNaN(wtaxAmountValue) ? '' : formatter.format(wtaxAmountValue);
                    if(formula.value === 'INCLUSIVE'){
                        netAmountDueDisplay.value = formatter.format(netAmountDueValue);
                    }
                    if(formula.value === 'EXCLUSIVE'){
                        netAmountDueDisplay.value = formatter.format(netAmountDueValue);
                    }
                    if(formula.value === 'NON-VAT'){
                        netAmountDueDisplay.value = formatter.format(amountTValue);
                    }
                    if (customerTIN.value === '005-519-158-000') {
                        netAmountDueDisplay.value = formatter.format(netAmountDueValue);
                    }
                    dateDisplay.value = dateValue;
                    referenceDisplay.value = referenceValue;
                    partnerSelectDisplay.value = partnerSelectValue;
                    partnerTinDisplay.value = partnerTinValue;
                    serviceChargeDisplay.value = serviceChargeValue;
                    transactionNumberDisplay.value = transactionNumberValue;

                    // Retrieve fromDate and toDate values from PHP session
                    var fromDateValue = fromDateInput.value;
                    var toDateValue = toDateInput.value;

                    // Set the values of fromDateInput and toDateInput
                    fromDateDisplay.value = fromDateValue;
                    toDateDisplay.value = toDateValue;
                });

            });

            function soa_trap() {
                var partnerSelect = document.getElementById('partner-select');
                var serviceCharge = document.getElementById('servicetype-select');
                var fromDate = document.getElementById('from-date');
                var toDate = document.getElementById('to-date');
                var numberOfTransaction = document.getElementById('numberOfTransaction');
                var amount = document.getElementById('amount');
                var processBtn = document.getElementById('process');
                var saveBtn = document.getElementById('saveBtn');

                if (
                partnerSelect.value === '' ||
                serviceCharge.value === '' ||
                fromDate.value === '' ||
                toDate.value === '' ||
                numberOfTransaction.value === '' ||
                amount.value === ''
                ) {
                processBtn.disabled = true;
                saveBtn.disabled = true;
                } else {
                processBtn.disabled = false;
                }
                // Enable saveBtn when processBtn is clicked
                processBtn.addEventListener('click', function() {
                saveBtn.disabled = false;
                });
            }
            
    </script>
    </body>
</html>
