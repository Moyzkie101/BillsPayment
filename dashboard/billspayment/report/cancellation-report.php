<?php
include '../../../config/config.php';
require '../../../vendor/autoload.php';
session_start();

if (!isset($_SESSION['user_type'])) {
    header('Location: ../../../index.php');
    exit();
}

$current_user_email = '';
if ($_SESSION['user_type'] === 'admin' && isset($_SESSION['admin_email'])) {
    $current_user_email = $_SESSION['admin_email'];
} elseif ($_SESSION['user_type'] === 'user' && isset($_SESSION['user_email'])) {
    $current_user_email = $_SESSION['user_email'];
}

// AJAX handler for fetching cancellation data
if (isset($_POST['action']) && $_POST['action'] === 'get_cancellation_data') {
    ob_clean();
    header('Content-Type: application/json');

    $partner = isset($_POST['partner']) ? trim($_POST['partner']) : '';
    $start_date = isset($_POST['start_date']) ? trim($_POST['start_date']) : '';
    $end_date = isset($_POST['end_date']) ? trim($_POST['end_date']) : '';
    $source_file = isset($_POST['source_file']) ? trim($_POST['source_file']) : '';
    $region = isset($_POST['region']) ? trim($_POST['region']) : '';
    $branch = isset($_POST['branch']) ? trim($_POST['branch']) : '';
    $search = isset($_POST['search']) ? trim($_POST['search']) : '';
    $page = isset($_POST['page']) ? max(1, (int)$_POST['page']) : 1;
    $rows_per_page = isset($_POST['rows_per_page']) ? max(1, (int)$_POST['rows_per_page']) : 10;

    $where = [];
    $params = [];
    $types = '';

    if ($search !== '') {
        $where[] = "(reference_no LIKE ? OR account_no LIKE ? OR account_name LIKE ? OR partner_name LIKE ? )";
        $like = "%{$search}%";
        $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
        $types .= 'ssss';
    }
    if ($partner !== '' && $partner !== 'All') {
        $where[] = "partner_name = ?";
        $params[] = $partner; $types .= 's';
    }
    if ($start_date !== '') {
        $where[] = "DATE(cancellation_datetime) >= ?";
        $params[] = $start_date; $types .= 's';
    }
    if ($end_date !== '') {
        $where[] = "DATE(cancellation_datetime) <= ?";
        $params[] = $end_date; $types .= 's';
    }
    if ($source_file !== '' && $source_file !== 'All') {
        $where[] = "source_file = ?";
        $params[] = $source_file; $types .= 's';
    }
    if ($region !== '' && $region !== 'All') {
        $where[] = "region_code = ?"; $params[] = $region; $types .= 's';
    }
    if ($branch !== '' && $branch !== 'All') {
        $where[] = "branch_id = ?"; $params[] = $branch; $types .= 's';
    }

    $whereClause = '';
    if (!empty($where)) $whereClause = 'WHERE ' . implode(' AND ', $where);

    // Count total
    $countSql = "SELECT COUNT(*) as total FROM mldb.billspayment_cancellation $whereClause";
    $total = 0;
    if (!empty($params)) {
        $cstmt = $conn->prepare($countSql);
        if ($cstmt) {
            $bind = array_merge([$types], $params);
            $tmp = [];
            foreach ($bind as $k => $v) $tmp[$k] = &$bind[$k];
            call_user_func_array([$cstmt, 'bind_param'], $tmp);
            $cstmt->execute();
            $cres = $cstmt->get_result();
            if ($cres) { $crow = $cres->fetch_assoc(); $total = intval($crow['total']); }
            $cstmt->close();
        }
    } else {
        $r = $conn->query($countSql);
        if ($r) { $row = $r->fetch_assoc(); $total = intval($row['total']); }
    }

    // Totals
    $totals = ['principal' => 0, 'partner' => 0, 'customer' => 0];
    $totalsSql = "SELECT COALESCE(SUM(principal_amount),0) as total_principal, COALESCE(SUM(charge_to_partner),0) as total_partner, COALESCE(SUM(charge_to_customer),0) as total_customer FROM mldb.billspayment_cancellation $whereClause";
    if (!empty($params)) {
        $tstmt = $conn->prepare($totalsSql);
        if ($tstmt) {
            $bind = array_merge([$types], $params);
            $tmp = []; foreach ($bind as $k => $v) $tmp[$k] = &$bind[$k];
            call_user_func_array([$tstmt, 'bind_param'], $tmp);
            $tstmt->execute(); $tres = $tstmt->get_result(); if ($tres) { $trow = $tres->fetch_assoc(); $totals['principal'] = floatval($trow['total_principal']); $totals['partner'] = floatval($trow['total_partner']); $totals['customer'] = floatval($trow['total_customer']); } $tstmt->close();
        }
    } else {
        $r = $conn->query($totalsSql); if ($r) { $trow = $r->fetch_assoc(); $totals['principal'] = floatval($trow['total_principal']); $totals['partner'] = floatval($trow['total_partner']); $totals['customer'] = floatval($trow['total_customer']); }
    }

    // Data with pagination
    $offset = ($page - 1) * $rows_per_page;
    $dataSql = "SELECT * FROM mldb.billspayment_cancellation $whereClause ORDER BY cancellation_datetime DESC LIMIT ?, ?";
    $data = [];
    if (!empty($params)) {
        $dstmt = $conn->prepare($dataSql);
        if ($dstmt) {
            // bind params + offset,limit
            $fullTypes = $types . 'ii';
            $bindVals = array_merge([$fullTypes], $params, [$offset, $rows_per_page]);
            $tmp = []; foreach ($bindVals as $k => $v) $tmp[$k] = &$bindVals[$k];
            call_user_func_array([$dstmt, 'bind_param'], $tmp);
            $dstmt->execute(); $dres = $dstmt->get_result(); if ($dres) { while ($r = $dres->fetch_assoc()) $data[] = $r; } $dstmt->close();
        }
    } else {
        $q = $conn->prepare($dataSql);
        $q->bind_param('ii', $offset, $rows_per_page);
        $q->execute(); $dres = $q->get_result(); if ($dres) { while ($r = $dres->fetch_assoc()) $data[] = $r; } $q->close();
    }

    echo json_encode(['success' => true, 'data' => $data, 'pagination' => ['total' => $total, 'page' => $page, 'rows_per_page' => $rows_per_page], 'totals' => $totals]);
    exit;
}

// Render page
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Cancellation Report</title>
    <link rel="stylesheet" href="../../../assets/css/templates/style.css?v=<?php echo time(); ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Select2 -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/select2-bootstrap-5-theme/1.3.0/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>
    <style>
        /* Improve cancellation table readability: prevent wrapping so each row is one line */
        #resultsTable { min-width: 1800px; }
        #resultsTable th { white-space: nowrap; vertical-align: middle; font-weight:600; }
        #resultsTable td { white-space: nowrap; vertical-align: middle; overflow: visible; }
        #resultsTable th, #resultsTable td { padding: .65rem .75rem; }
        #resultsTable td.text-end, #resultsTable th.text-end { text-align: right; }
        /* Slightly increase font for readability */
        #resultsTable th, #resultsTable td { font-size: 0.9rem; }
        /* Make the table container scroll horizontally */
        .table-responsive { overflow-x: auto; }
    </style>
</head>
<body>
<?php include '../../../templates/header_ui.php'; ?>
<?php include '../../../templates/sidebar.php'; ?>
<div id="loading-overlay">
    <div class="loading-spinner"></div>
</div>
<div class="bp-section-header" role="region" aria-label="Page title">
    <div class="bp-section-title">
        <i class="fa-solid fa-file-invoice-dollar" aria-hidden="true"></i>
        <div>
            <h2>Cancellation Report</h2>
            <p class="bp-section-sub">Cancellation listing and filters</p>
        </div>
    </div>
</div>
<div class="bp-card container-fluid mt-3 p-4">
    <div class="card mb-3">
        <div class="card-header">
            <div class="row g-2 align-items-end">
                <div class="col-md-2 col-sm-6">
                    <label class="form-label small text-muted mb-1">Partner:</label>
                    <select id="partnerlistDropdown" class="form-select form-select-sm select2" aria-label="Select Partner" name="partnerlist" data-placeholder="Search Partner...">
                        <option value="">Select Partner</option>
                        <option value="All">All</option>
                        <?php
                        $pQ = $conn->query("SELECT partner_name FROM masterdata.partner_masterfile ORDER BY partner_name");
                        if ($pQ) while ($r = $pQ->fetch_assoc()) { $pn = htmlspecialchars($r['partner_name']); echo "<option value='". $pn ."'>" . ucfirst($pn) . "</option>"; }
                        ?>
                    </select>
                </div>

                <div class="col-md-3 col-sm-6">
                    <label class="form-label small text-muted mb-1">Cancellation Date:</label>
                    <div class="row g-1">
                        <div class="col-6">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">From</span>
                                <input type="date" id="start_date" name="start_date" class="form-control" max="<?php echo date('Y-m-d'); ?>">
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">To</span>
                                <input type="date" id="end_date" name="end_date" class="form-control" max="<?php echo date('Y-m-d'); ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-2 col-sm-6">
                    <label class="form-label small text-muted mb-1">Source File:</label>
                    <select id="source_file_filter" name="source_file" class="form-select form-select-sm">
                        <option value="All">All</option>
                        <option value="KPX">KPX</option>
                        <option value="KP7">KP7</option>
                    </select>
                </div>

                <div class="col-md-2 col-sm-6">
                    <label class="form-label small text-muted mb-1">Region:</label>
                    <select id="region_filter" name="region" class="form-select form-select-sm">
                        <option value="All">All Region</option>
                        <?php
                            $regionQ = $conn->query("SELECT DISTINCT region FROM mldb.billspayment_cancellation WHERE region IS NOT NULL AND region <> '' ORDER BY region");
                            if ($regionQ) while ($rr = $regionQ->fetch_assoc()) { $rv = htmlspecialchars($rr['region']); echo "<option value='". $rv ."'>" . $rv . "</option>"; }
                        ?>
                    </select>
                </div>

                <div class="col-md-2 col-sm-6">
                    <label class="form-label small text-muted mb-1">Branch Name:</label>
                    <select id="branchDropdown" class="form-select form-select-sm select2" aria-label="Select Branch Name" name="branch" data-placeholder="Search Branch Name...">
                        <option value="All">All Branch Name</option>
                        <?php
                            $branchQ = $conn->query("SELECT DISTINCT branch_name FROM mldb.billspayment_cancellation WHERE branch_name IS NOT NULL AND branch_name <> '' ORDER BY branch_name");
                            if ($branchQ) while ($br = $branchQ->fetch_assoc()) { $bn = htmlspecialchars($br['branch_name']); echo "<option value='". $bn ."'>" . $bn . "</option>"; }
                        ?>
                    </select>
                </div>

                <div class="col-md-2 col-sm-6">
                    <label class="form-label small text-muted mb-1">Search:</label>
                    <input id="search_input" class="form-control form-control-sm" placeholder="Search reference/account/name">
                </div>

                <div class="col-md-1 col-sm-6">
                    <button type="button" id="searchButton" class="btn btn-danger btn-sm w-100"><i class="fas fa-search me-1"></i> Search</button>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="row g-2 align-items-center">
                <div class="col-md-2">
                    <label class="form-label small text-muted mb-1">Rows</label>
                    <select id="rowsPerPage" class="form-select form-select-sm" style="width:auto;"><option value="5" selected>5</option><option value="10">10</option><option value="25">25</option><option value="50">50</option><option value="100">100</option></select>
                </div>
                <div class="col-md-10 text-end"><strong>Totals:</strong> <span id="totalsDisplay">-</span></div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-striped mb-0" id="resultsTable">
                    <thead class="table-light">
                        <tr>
                            <th style="width:48px;">No.</th>
                            <th style="width:160px;">Cancellation Date/Time</th>
                            <th style="width:160px;">Sendout Date/Time</th>
                            <th style="width:220px;">Partner Name</th>
                            <th style="width:160px;">Reference No.</th>
                            <th style="width:120px;">Control No</th>
                            <th style="width:120px;">Account No</th>
                            <th style="width:220px;">Account Name</th>
                            <th style="width:160px;">Payor</th>
                            <th style="width:120px;">IR No.</th>
                            <th class="text-end" style="width:120px;">Amount</th>
                            <th class="text-end" style="width:120px;">Cancellation Charge</th>
                            <th class="text-end" style="width:100px;">CTC</th>
                            <th class="text-end" style="width:100px;">CTP</th>
                            <th style="width:140px;">Resource</th>
                            <th style="width:160px;">Branch Name</th>
                            <th style="width:140px;">Remote Operator</th>
                            <th style="width:140px;">Remote Branch</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
        <div class="card-footer">
            <nav><ul class="pagination" id="pagination"></ul></nav>
        </div>
    </div>
</div>
<?php include '../../../templates/footer.php'; ?>
<script>
function formatPHP(n){ return '₱ ' + (parseFloat(n||0).toLocaleString('en-PH',{minimumFractionDigits:2,maximumFractionDigits:2})); }
function loadCancellations(page=1){
    const post = { action: 'get_cancellation_data', page: page, rows_per_page: parseInt($('#rowsPerPage').val()||5), start_date: $('#start_date').val(), end_date: $('#end_date').val(), partner: $('#partnerlistDropdown').val(), search: $('#search_input').val() };
    $.post(location.href, post, function(resp){
        if(!resp || !resp.success) return;
        const tbody = $('#resultsTable tbody'); tbody.empty();
        resp.data.forEach(function(r, idx){
            const no = ((resp.pagination.page-1) * resp.pagination.rows_per_page) + idx + 1;
            tbody.append(`<tr>
                <td>${no}</td>
                <td>${r.cancellation_datetime||''}</td>
                <td>${r.sendout_datetime||''}</td>
                <td>${r.partner_name||''}</td>
                <td>${r.reference_no||''}</td>
                <td>${r.control_no||''}</td>
                <td>${r.account_no||''}</td>
                <td>${r.account_name||''}</td>
                <td>${r.payor||''}</td>
                <td>${r.ir_no||''}</td>
                <td class="text-end">${formatPHP(r.principal_amount)}</td>
                <td class="text-end">${formatPHP(r.cancellation_charge)}</td>
                <td class="text-end">${formatPHP(r.charge_to_customer||r.charge_to_costumer)}</td>
                <td class="text-end">${formatPHP(r.charge_to_partner)}</td>
                <td>${r.resource||''}</td>
                <td>${r.branch_name||''}</td>
                <td>${r.remote_operator||''}</td>
                <td>${r.remote_branch||''}</td>
            </tr>`);
        });
        // pagination
        const total = resp.pagination.total; const rpp = resp.pagination.rows_per_page; const pages = Math.ceil(total / rpp);
        const pg = $('#pagination'); pg.empty();
        for(let i=1;i<=pages;i++){ pg.append(`<li class="page-item ${i===resp.pagination.page?'active':''}"><a class="page-link" href="#" data-page="${i}">${i}</a></li>`); }
        $('#totalsDisplay').text('Principal: ' + formatPHP(resp.totals.principal) + ' • Partner: ' + formatPHP(resp.totals.partner) + ' • Customer: ' + formatPHP(resp.totals.customer));
    }, 'json');
}
$(document).on('click', '#pagination .page-link', function(e){ e.preventDefault(); loadCancellations(parseInt($(this).data('page'))); });
$('#searchButton').on('click', function(){ loadCancellations(1); });
$('#rowsPerPage').on('change', function(){ loadCancellations(1); });
$(document).ready(function(){
    // Initialize Select2 for partner search and branch if needed
    try {
        $('#partnerlistDropdown').select2({
            theme: 'bootstrap-5',
            placeholder: 'Search or select a Partner...',
            allowClear: true,
            width: '100%'
        });
    } catch (e) {}

    loadCancellations(1);
    // Inline Today button behavior: show button on date focus, set both dates to today and lock end_date
    function todayISO(){ const d = new Date(); const mm = String(d.getMonth()+1).padStart(2,'0'); const dd = String(d.getDate()).padStart(2,'0'); return `${d.getFullYear()}-${mm}-${dd}`; }
    // When user changes start_date manually: if it's today, lock end_date and sync; otherwise unlock end_date
    $('#start_date').on('change', function(){ const v = $(this).val(); if(v === todayISO()){ $('#end_date').val(v).prop('readonly', true).prop('disabled', true); } else { $('#end_date').prop('readonly', false).prop('disabled', false); } });
});
</script>
</body>
</html>
