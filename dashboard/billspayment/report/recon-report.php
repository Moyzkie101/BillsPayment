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
    } elseif ($_SESSION['user_type'] === 'user' && isset($_SESSION['user_email'])) {
        $current_user_email = $_SESSION['user_email'];
    }
}

if (isset($_POST['action']) && $_POST['action'] === 'get_partner_list') {
    try {
        $partnersQuery = "SELECT partner_name FROM masterdata.partner_masterfile WHERE status = 'ACTIVE' ORDER BY partner_name";
        $partnersResult = $conn->query($partnersQuery);
        
        $partners = array();
        if ($partnersResult && $partnersResult->num_rows > 0) {
            while ($row = $partnersResult->fetch_assoc()) {
                $partners[] = $row;
            }
        }
        
        echo json_encode([
            'status' => 'success',
            'data' => $partners
        ]);
        exit();
    } catch (Exception $e) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Database error: ' . $e->getMessage()
        ]);
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
    <title>Recon Report | <?php if($_SESSION['user_type'] === 'admin' || $_SESSION['user_type'] === 'user') echo ucfirst($_SESSION['user_type']); else echo "Guest";?></title>
    <!-- custom CSS file link  -->
    <link rel="stylesheet" href="../../../assets/css/templates/style.css?v=<?php echo time(); ?>">
    <script src="https://kit.fontawesome.com/30b908cc5a.js" crossorigin="anonymous"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="../../../assets/js/sweetalert2.all.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>

    <link rel="icon" href="../../../images/MLW logo.png" type="image/png">
    <style>
        /* Day Shortcut Buttons Styling */
        .day-shortcut-container {
            padding: 10px 5px;
            border-radius: 5px;
            /* margin-bottom: 15px; */
            display: flex;
            align-items: center;
            background-color: #f8f9fa;
            border: 1px solid #e0e0e0;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .day-buttons-label {
            font-weight: bold;
            margin-right: 15px;
            color: #666;
            white-space: nowrap;
            padding-left: 10px;
        }

        .day-buttons-wrapper {
            display: flex;
            flex-wrap: wrap;
            gap: 2px;
            max-width: 100%;
            overflow-x: auto;
            padding: 5px;
            align-items: center;
        }

        .day-button {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            border: 1px solid #dc3545;
            background-color: white;
            color: #dc3545;
            font-weight: bold;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        /* Pill shape for month buttons */
        .day-button.month-button {
            width: auto !important;
            min-width: 120px !important;
            padding: 8px 16px !important;
            border-radius: 25px !important;
            font-size: 12px !important;
            white-space: nowrap !important;
        }

        /* Pill shape for year buttons */
        .day-button.year-button {
            width: auto !important;
            min-width: 70px !important;
            padding: 8px 16px !important;
            border-radius: 25px !important;
            font-size: 12px !important;
        }

        /* Ensure day buttons remain circular */
        .day-button:not(.month-button):not(.year-button):not(.day-button-all) {
            width: 35px;
            height: 35px;
            border-radius: 50%;
        }

        .day-button:hover {
            background-color: rgba(220,53,69,0.8);
            color: white;
            transform: scale(1.05);
            box-shadow: 0 2px 4px rgba(220,53,69,0.3);
            cursor: pointer;
            z-index: 4;
        }

        .day-button-active {
            background-color: #dc3545;
            color: white;
            transform: scale(1.1);
            box-shadow: 0 2px 5px rgba(220,53,69,0.4);
            position: relative;
            z-index: 5;
        }

        .day-button-active:after {
            content: "";
            position: absolute;
            bottom: -3px;
            left: 50%;
            transform: translateX(-50%);
            width: 6px;
            height: 6px;
            background-color: #ff8a04ff;
            border-radius: 50%;
        }

        .day-button-all {
            width: auto;
            padding: 0 15px;
            border-radius: 20px;
            background-color: #6c757d;
            color: white;
            border-color: #6c757d;
        }

        .day-button-all:hover {
            background-color: #5a6268;
            border-color: #545b62;
        }

        /* Export Modal Styling */
        .export-options {
            text-align: center;
            padding: 20px 0;
        }

        .export-buttons-container {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-top: 20px;
        }

        .export-btn {
            padding: 12px 24px;
            font-size: 16px;
            font-weight: bold;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            min-width: 150px;
        }

        .export-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }

        .export-btn i {
            margin-right: 8px;
            font-size: 18px;
        }

        /* Custom SweetAlert styling */
        .swal2-popup {
            border-radius: 15px !important;
        }

        .swal2-title {
            color: #333 !important;
            font-weight: bold !important;
        }

        .swal2-html-container {
            margin: 0 !important;
        }

        .month-separator {
            width: 100%;
            text-align: center;
            font-size: 10px;
            color: #666;
            margin: 5px 0;
            font-weight: bold;
            border-bottom: 1px solid #ddd;
            padding-bottom: 2px;
        }

        /* Ensure day number buttons remain circular */
        .day-button.day-number-button {
            width: 35px !important;
            height: 35px !important;
            border-radius: 50% !important;
            font-size: 12px !important;
            margin: 2px !important;
        }

        /* Responsive adjustments for smaller screens */
        @media (max-width: 768px) {
            .day-buttons-wrapper {
                gap: 1px;
            }
            
            .day-button.day-number-button {
                width: 30px !important;
                height: 30px !important;
                font-size: 11px !important;
                margin: 1px !important;
            }
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
                <i class="fa-solid fa-layer-group" aria-hidden="true"></i>
                <div>
                    <h2>Recon Report</h2>
                                
                    <!-- <p class="bp-section-sub">Sample Description</p> -->
                </div>
            </div>
        </div>
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-18">
                    <div class="card">
                        <div class="card-header">
                            <div class="row g-2 align-items-end">
                                <!-- Partner List -->
                                <div class="col-md-2 col-sm-6">
                                    <label class="form-label">Partners Name:</label>
                                    <select id="partnerlistDropdown" class="form-select select2" aria-label="Select Partner" name="partnerlist" data-placeholder="Search or select a Partner..." required>
                                        <option value="">Select Partner</option>
                                        <option value="All">All</option>
                                        <!-- options will be populated by JS -->
                                    </select>
                                </div>

                                <!-- Time Frame -->
                                <div class="col-md-2 col-sm-6">
                                    <label class="form-label">Time Frame:</label>
                                    <select class="form-select" name="filterType" required>
                                        <option value="">Select Time Frame</option>
                                        <option value="daily">Per Day</option>
                                        <option value="weekly">Date Range</option>
                                        <option value="monthly">Monthly</option>
                                    </select>
                                </div>

                                <!-- Date Range based on selected Time Frame -->
                                <div class="col-md-2" style="display: none;">
                                    <label class="form-label">Start Date:</label>
                                    <input type="date" class="form-control" name="startDate" required>
                                </div>
                                <div class="col-md-2" style="display: none;">
                                    <label class="form-label">End Date:</label>
                                    <input type="date" class="form-control" name="endDate" required>
                                </div>

                                <!-- Action Buttons -->
                                <div class="col-md-auto col-sm-12">
                                    <div class="d-flex align-items-end flex-wrap" style="gap:8px;">
                                        <button type="button" class="btn btn-secondary" id="generateReport" disabled>Generate</button>
                                        <button class="btn btn-danger" id="reconButton" type="button" style="display:none;">Recon</button>
                                        <button class="btn btn-danger" id="exportButton" type="button" style="display:none;">Export to</button>
                                        <button class="btn btn-warning" id="debugButton" type="button" style="display:none;">Debug Report</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="container-fluid">
                            <div class="day-shortcut-container mt-2" id="dayFilterContainer" style="display: none;">
                                <div class="day-buttons-label">Filter by Day:</div>
                                <div class="day-buttons-wrapper" id="dayButtonsWrapper">
                                    <button class="day-button day-button-all day-button-active" id="allDaysButton">All</button>
                                </div>
                            </div>
                            <div class="day-shortcut-container mt-2"  id="monthFilterContainer" style="display: none;">
                                <div class="day-buttons-label">Filter by Month:</div>
                                <div class="day-buttons-wrapper" id="dayButtonsWrapper">
                                    <button class="day-button day-button-all day-button-active" id="allDaysButton">All</button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="card-body">
                            <div class="table-responsive" id="tableContainer" style="overflow-y: auto;">
                                <table id="transactionReportTable" class="table table-bordered table-hover table-striped">
                                    <thead class="table-light sticky-top">
                                        <tr>
                                            <th rowspan="4" class='text-truncate text-center align-middle'>No.</th>
                                            <th rowspan="4" class='text-truncate text-center align-middle'>Partner Name</th>
                                        </tr>
                                        <tr>
                                            <th colspan="3" class='text-truncate text-center align-middle'>Volume Report</th>
                                            <th colspan="5" class='text-truncate text-center align-middle'>Settlement Per Bank Report</th>
                                            <th rowspan="2" colspan="3" class='text-truncate text-center align-middle'>Variance (Volume VS Settlement)</th>
                                        </tr>
                                        <tr>
                                            <th colspan="3" class='text-truncate text-center align-middle'>Net Total Transaction</th>
                                            <th colspan="5" class='text-truncate text-center align-middle'>Gross Total Transaction</th>
                                        </tr>
                                        <tr>
                                            <!-- Column header for Net -->
                                            <th class='text-center'>Vol.</th>
                                            <th class='text-center'>Principal</th>
                                            <th class='text-center'>Charge</th>
                                            <!-- Column header for Gross -->
                                            <th class='text-center'>Vol.</th>
                                            <th class='text-center'>Principal</th>
                                            <th class='text-center'>Charge</th>
                                            <th class='text-center'>Principal Adjustment</th>
                                            <th class='text-center'>Amount for Settlement</th>
                                            <!-- Column header for Variance -->
                                            <th class='text-center'>Vol.</th>
                                            <th class='text-center'>Principal</th>
                                            <th class='text-center'>Charge</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <!-- Data will be populated via JavaScript -->
                                        <tr>
                                            <td></td>
                                            <td></td>
                                            <td></td>
                                            <td></td>
                                            <td></td>
                                            <td></td>
                                            <td></td>
                                            <td></td>
                                            <td></td>
                                            <td></td>
                                            <td></td>
                                            <td></td>
                                            <td></td>
                                        </tr>
                                    </tbody>
                                    <tfoot class="sticky-bottom table-dark">
                                        <tr>
                                            <th colspan="2" class="text-end">Total : </th>
                                            <!-- Column header for Net -->
                                            <th class="text-center" id="totalnetvolume">0</th>
                                            <th class="text-end" id="totalnetprincipal">0.00</th>
                                            <th class="text-end" id="totalnetcharge">0.00</th>
                                            <!-- Column header for Gross -->
                                            <th class="text-center" id="totalgrossvolume">0</th>
                                            <th class="text-end" id="totalgrossprincipal">0.00</th>
                                            <th class="text-end" id="totalgrosscharge">0.00</th>
                                            <th class="text-end" id="totalprincipaladjustment">0.00</th>
                                            <th class="text-end" id="totalsettlementamount">0.00</th>
                                            <!-- Column header for Variance -->
                                            <th class="text-center" id="totalvariancevolume">0</th>
                                            <th class="text-end" id="totalvarianceprincipal">0.00</th>
                                            <th class="text-end" id="totalvariancecharge">0.00</th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
<?php include '../../../templates/footer.php'; ?>
<script>
$(document).ready(function(){
    // initialize select2 if available
    if ($.fn.select2) {
        $('#partnerlistDropdown').select2({ placeholder: 'Search or select a Partner...', allowClear: true });
    }

    // cache selectors
    const $filterType = $('select[name="filterType"]');
    const $startCol = $('input[name="startDate"]').closest('.col-md-2');
    const $endCol = $('input[name="endDate"]').closest('.col-md-2');
    const $startInput = $('input[name="startDate"]');
    const $endInput = $('input[name="endDate"]');
    const $partner = $('#partnerlistDropdown');
    const $generate = $('#generateReport');
    const $dayContainer = $('#dayFilterContainer');
    const $monthContainer = $('#monthFilterContainer');

    function resetDateInputs(){
        $startInput.val('');
        $endInput.val('');
    }

    function updateVisibility(){
        const v = $filterType.val();
        // hide by default
        $startCol.hide(); $endCol.hide(); $dayContainer.hide(); $monthContainer.hide();

        if (v === 'daily') {
            $startCol.show(); // only start date (single day)
            // day/month shortcut containers remain hidden until Generate is clicked
        } else if (v === 'weekly') {
            $startCol.show(); $endCol.show();
        } else if (v === 'monthly') {
            // show start and end for month selection; you can customize to use month picker
            $startCol.show(); $endCol.show();
            // month shortcuts remain hidden until Generate is clicked
        }
        toggleGenerateButton();
    }

    function toggleGenerateButton(){
        const partnerVal = $partner.val();
        const filter = $filterType.val();

        if (!filter) { $generate.prop('disabled', true); return; }
        if (!partnerVal) { $generate.prop('disabled', true); return; }

        if (filter === 'daily'){
            $generate.prop('disabled', !$startInput.val());
        } else if (filter === 'weekly' || filter === 'monthly'){
            $generate.prop('disabled', !($startInput.val() && $endInput.val()));
        } else {
            $generate.prop('disabled', false);
        }
    }

    // events
    $filterType.on('change', function(){
        resetDateInputs();
        // switch input types when monthly selected
        if ($(this).val() === 'monthly'){
            $startInput.attr('type','month');
            $endInput.attr('type','month');
        } else {
            $startInput.attr('type','date');
            $endInput.attr('type','date');
        }
        updateVisibility();
    });

    $startInput.on('change', toggleGenerateButton);
    $endInput.on('change', toggleGenerateButton);
    $partner.on('change', toggleGenerateButton);

    // When Generate is clicked, reveal the appropriate shortcut container (day/month) if applicable
    $generate.on('click', function(){
        const v = $filterType.val();
        // hide both first
        $dayContainer.hide(); $monthContainer.hide();
        if (v === 'weekly') {
            $dayContainer.show();
            // generate day buttons for single day (just that day)
            generateDayButtons($startInput.val(), $endInput.val() || $startInput.val());
        } else if (v === 'monthly') {
            $monthContainer.show();
            generateMonthButtons($startInput.val(), $endInput.val());
        }
        // you can trigger AJAX report generation here if needed
    });

    // initial state
    $startCol.hide(); $endCol.hide(); $generate.prop('disabled', true);
});

// Functions to generate day/month buttons
function generateDayButtons(startDate, endDate){
    if (!startDate) return;
    const start = new Date(startDate);
    const end = new Date(endDate || startDate);
    const wrapper = $('#dayFilterContainer').find('.day-buttons-wrapper');
    wrapper.find('.day-button:not(.day-button-all)').remove();
    for (let d = new Date(start); d <= end; d.setDate(d.getDate()+1)){
        const yyyy = d.getFullYear();
        const mm = String(d.getMonth()+1).padStart(2,'0');
        const dd = String(d.getDate()).padStart(2,'0');
        const label = dd;
        const btn = $('<button>').addClass('day-button day-number-button').text(label).attr('data-date', `${yyyy}-${mm}-${dd}`);
        wrapper.append(btn);
    }
        // decide active state: if more than one day in range, activate "All" pill; otherwise activate the single day
        const $dayButtons = wrapper.find('.day-button:not(.day-button-all)');
        if ($dayButtons.length > 1) {
            wrapper.find('.day-button-all').addClass('day-button-active');
        } else {
            $dayButtons.first().addClass('day-button-active');
        }
    // show container
    $('#dayFilterContainer').show();
}

function generateMonthButtons(startMonth, endMonth){
    if (!startMonth) return;
    // startMonth/endMonth expected as YYYY-MM
    const s = new Date(startMonth + '-01');
    const e = new Date((endMonth || startMonth) + '-01');
    const wrapper = $('#monthFilterContainer').find('.day-buttons-wrapper');
    wrapper.find('.day-button:not(.day-button-all)').remove();
    for (let d = new Date(s); d <= e; d.setMonth(d.getMonth()+1)){
        const yyyy = d.getFullYear();
        const monthName = d.toLocaleString('default', { month: 'short' });
        const val = `${yyyy}-${String(d.getMonth()+1).padStart(2,'0')}`;
        const btn = $('<button>').addClass('day-button month-button').text(`${monthName} ${yyyy}`).attr('data-date', val);
        wrapper.append(btn);
    }
        // decide active state: if more than one month in range, activate "All" pill; otherwise activate the single month
        const $monthButtons = wrapper.find('.day-button:not(.day-button-all)');
        if ($monthButtons.length > 1) {
            wrapper.find('.day-button-all').addClass('day-button-active');
        } else {
            $monthButtons.first().addClass('day-button-active');
        }
    $('#monthFilterContainer').show();
}
</script>
<script>
$(document).ready(function(){
    function loadPartners(){
        $.ajax({
            url: window.location.pathname,
            method: 'POST',
            data: { action: 'get_partner_list' },
            dataType: 'json'
        }).done(function(resp){
            const $ddl = $('#partnerlistDropdown');
            $ddl.empty();
            $ddl.append($('<option>').val('').text('Select Partner'));
            $ddl.append($('<option>').val('All').text('All'));
            if (resp && resp.data && Array.isArray(resp.data)){
                resp.data.forEach(function(p){
                    const name = (typeof p === 'object') ? (p.partner_name || '') : p;
                    if (name) $ddl.append($('<option>').val(name).text(name));
                });
            }
            if ($.fn.select2) $ddl.trigger('change.select2');
            // ensure generate button state updates after load
            $('#partnerlistDropdown').trigger('change');
        }).fail(function(){
            console.error('Failed to load partners');
        });
    }

    loadPartners();
});
</script>
<script>
// Toggle Generate button style based on enabled state
(function(){
    const $gen = $('#generateReport');
    function updateGenerateStyle(){
        if ($gen.prop('disabled')){
            $gen.removeClass('btn-danger').addClass('btn-secondary');
        } else {
            $gen.removeClass('btn-secondary').addClass('btn-danger');
        }
    }
    // initial
    updateGenerateStyle();
    // observe attribute changes
    const mo = new MutationObserver(updateGenerateStyle);
    const btn = document.getElementById('generateReport');
    if (btn) mo.observe(btn, { attributes: true, attributeFilter: ['disabled', 'class'] });
    // also update on input changes
    $(document).on('change input', 'select[name="filterType"], #partnerlistDropdown, input[name="startDate"], input[name="endDate"]', function(){
        setTimeout(updateGenerateStyle, 10);
    });
})();
</script>
</html>