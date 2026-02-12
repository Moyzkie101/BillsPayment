<?php
// Duplicate Transaction Checker
include '../../../config/config.php';
require '../../../vendor/autoload.php';
session_start();

// simple user email for permission checks
$current_user_email = '';
if (isset($_SESSION['user_type'])) {
    if ($_SESSION['user_type'] === 'admin' && isset($_SESSION['admin_email'])) {
        $current_user_email = $_SESSION['admin_email'];
    } elseif ($_SESSION['user_type'] === 'user' && isset($_SESSION['user_email'])) {
        $current_user_email = $_SESSION['user_email'];
    }
}

// AJAX: find duplicate groups in billspayment_transaction
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['check_duplicates_db'])) {
    header('Content-Type: application/json');
    $mode = isset($_POST['mode']) ? trim($_POST['mode']) : 'normal';

    // Normal mode: restore previous grouping (reference_no + DATE(datetime) + partner_id + amount_paid)
    if ($mode === 'normal') {
        $results = [];

        $groupSql = "SELECT reference_no, DATE(datetime) AS dt, partner_id, amount_paid, COUNT(*) AS cnt
                     FROM billspayment_transaction
                     GROUP BY reference_no, DATE(datetime), partner_id, amount_paid
                     HAVING cnt > 1
                     ORDER BY reference_no, dt";

        $groups = $conn->query($groupSql);
        if ($groups && $groups->num_rows > 0) {
            while ($g = $groups->fetch_assoc()) {
                // fetch all rows for this group
                $ref = $conn->real_escape_string($g['reference_no']);
                $dt = $g['dt'];
                $partner = $conn->real_escape_string($g['partner_id']);
                $amount = $g['amount_paid'];

                $rowsSql = "SELECT id, reference_no, datetime, partner_id, partner_name, partner_id_kpx, amount_paid, payor, branch_id
                            FROM billspayment_transaction
                            WHERE reference_no = '" . $ref . "' AND DATE(datetime) = '" . $dt . "' AND partner_id = '" . $partner . "' AND amount_paid = '" . $amount . "'
                            ORDER BY id ASC";

                $rowsRes = $conn->query($rowsSql);
                $rows = [];
                if ($rowsRes && $rowsRes->num_rows > 0) {
                    while ($r = $rowsRes->fetch_assoc()) {
                        $rows[] = $r;
                    }
                }

                if (!empty($rows)) {
                    $results[] = ['group_key' => $g['reference_no'] . '|' . $g['dt'] . '|' . $g['partner_id'], 'rows' => $rows];
                }
            }
        }

        echo json_encode(['success' => true, 'mode' => 'normal', 'groups' => $results]);
        exit;
    }

    // Dev mode: return all rows for reference_no's that have more than one occurrence
    if ($mode === 'dev') {
        $results = [];
        // use fully-qualified table name for clarity
        $sql = "SELECT t.* FROM mldb.billspayment_transaction t
                 JOIN (
                   SELECT reference_no
                   FROM mldb.billspayment_transaction
                   GROUP BY reference_no
                   HAVING COUNT(*) > 1
                 ) dup ON t.reference_no = dup.reference_no
                 ORDER BY t.reference_no, t.id";
        $res = $conn->query($sql);
        if ($res && $res->num_rows > 0) {
            while ($row = $res->fetch_assoc()) {
                $ref = $row['reference_no'];
                if (!isset($results[$ref])) $results[$ref] = [];
                $results[$ref][] = $row;
            }
        }
        // convert to array of groups
        $out = [];
        foreach ($results as $ref => $rows) {
            $out[] = ['reference_no' => $ref, 'rows' => $rows];
        }
        echo json_encode(['success' => true, 'mode' => 'dev', 'groups' => $out]);
        exit;
    }

    // Summary mode: global duplicates by reference_no (used when legacy grouping finds nothing)
    if ($mode === 'summary') {
        $summary = [];
        // Exclude rows where status = '*' (cancellation footprint) from summary counts
        $sql = "SELECT reference_no, COUNT(*) AS total_count
                FROM mldb.billspayment_transaction
                WHERE COALESCE(status, '') <> '*'
                GROUP BY reference_no
                HAVING COUNT(*) > 1
                ORDER BY total_count DESC";
        $res = $conn->query($sql);
        if ($res && $res->num_rows > 0) {
            while ($r = $res->fetch_assoc()) {
                $summary[] = ['reference_no' => $r['reference_no'], 'total_count' => intval($r['total_count'])];
            }
        }
        echo json_encode(['success' => true, 'mode' => 'summary', 'groups' => $summary]);
        exit;
    }

    // default fallback: behave like legacy grouping (reference+date+partner+amount)
    $results = [];
    $groupSql = "SELECT reference_no, DATE(datetime) AS dt, partner_id, amount_paid, COUNT(*) AS cnt
                 FROM billspayment_transaction
                 GROUP BY reference_no, DATE(datetime), partner_id, amount_paid
                 HAVING cnt > 1
                 ORDER BY reference_no, dt";
    $groups = $conn->query($groupSql);
    if ($groups && $groups->num_rows > 0) {
        while ($g = $groups->fetch_assoc()) {
            // fetch all rows for this group
            $ref = $conn->real_escape_string($g['reference_no']);
            $dt = $g['dt'];
            $partner = $conn->real_escape_string($g['partner_id']);
            $amount = $g['amount_paid'];

            $rowsSql = "SELECT id, reference_no, datetime, partner_id, partner_name, partner_id_kpx, amount_paid, payor, branch_id
                        FROM billspayment_transaction
                        WHERE reference_no = '" . $ref . "' AND DATE(datetime) = '" . $dt . "' AND partner_id = '" . $partner . "' AND amount_paid = '" . $amount . "'
                        ORDER BY id ASC";

            $rowsRes = $conn->query($rowsSql);
            $rows = [];
            if ($rowsRes && $rowsRes->num_rows > 0) {
                while ($r = $rowsRes->fetch_assoc()) {
                    $rows[] = $r;
                }
            }

            if (!empty($rows)) {
                $results[] = ['group_key' => $g['reference_no'] . '|' . $g['dt'] . '|' . $g['partner_id'], 'rows' => $rows];
            }
        }
    }

    echo json_encode(['success' => true, 'mode' => 'legacy', 'groups' => $results]);
    exit;
}

// AJAX: delete single duplicate row
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_duplicate']) && !empty($_POST['id'])) {
    header('Content-Type: application/json');
    $id = intval($_POST['id']);
    $del = $conn->query("DELETE FROM billspayment_transaction WHERE id = " . $id);
    if ($del) echo json_encode(['success' => true]); else echo json_encode(['success' => false, 'error' => $conn->error]);
    exit;
}

// AJAX: delete multiple duplicate ids
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_multiple']) && !empty($_POST['ids'])) {
    header('Content-Type: application/json');
    $ids = $_POST['ids'];
    $clean = array_map('intval', $ids);
    $in = implode(',', $clean);
    if ($in === '') { echo json_encode(['success' => false, 'error' => 'No ids']); exit; }
    $del = $conn->query("DELETE FROM billspayment_transaction WHERE id IN (" . $in . ")");
    if ($del) echo json_encode(['success' => true]); else echo json_encode(['success' => false, 'error' => $conn->error]);
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Duplicate Checker - Transaction</title>
    <link rel="stylesheet" href="../../../assets/css/templates/style.css?v=<?php echo time(); ?>">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://kit.fontawesome.com/30b908cc5a.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.9.3/html2pdf.bundle.min.js"></script>
    <link rel="icon" href="../../../images/MLW logo.png" type="image/png">
    <style>
           /* Loading Overlay */
        #loading-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            z-index: 9999;
            justify-content: center;
            align-items: center;
        }

        .loading-spinner {
            border: 5px solid #f3f3f3;
            border-top: 5px solid #dc3545;
            border-radius: 50%;
            width: 60px;
            height: 60px;
            animation: spin 1s linear infinite;
        }
          @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        #loading-overlay{ position:fixed; inset:0; background:rgba(0,0,0,0.35); display:none; align-items:center; justify-content:center; z-index:20000 }
        .modal-card{ background:#fff; border-radius:10px; padding:14px; width:100%; max-width:100%; max-height:85vh; overflow:auto; box-sizing:border-box; }
        .dup-row{ border-radius:8px; padding:12px; margin-bottom:8px; display:flex; justify-content:space-between; align-items:center }
        .dup-row.green{ background:#e6ffed; border:1px solid #b6f0c1 }
        .dup-row.red{ background:#fff2f2; border:1px solid #f5bcbc }
        .dup-actions button{ background:transparent; border:none; cursor:pointer; font-size:18px; color:#212529; padding:6px; border-radius:6px }
        .dup-actions button:hover { color:#dc3545; background: rgba(220,53,69,0.06); }
        .controls { display:flex; gap:8px; align-items:center }
        #btn-delete-all { background:#6c757d; color:#fff; border:none; padding:8px 14px; border-radius:8px; font-weight:700; transition:all 160ms ease; cursor:pointer }
        #btn-delete-all:hover { background:#5a6268; transform:translateY(-1px); color:#fff }
        /* Dev mode cell coloring */
        .cell-green{ background:#e6ffed !important; }
        .cell-red{ background:#fff2f2 !important; }
        /* Dev mode card containers */
        .dev-group-card { 
            margin-bottom:16px; 
            background:#fff; 
            border:1px solid #e9ecef; 
            border-radius:10px; 
            box-shadow: 0 2px 4px rgba(0,0,0,0.04);
            overflow:hidden;
        }
        .dev-group-header {
            padding:12px 16px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-bottom:1px solid #dee2e6;
            font-weight:700;
            color:#212529;
        }
        .dev-group-body {
            padding:0;
            overflow-x:auto;
            overflow-y:visible;
        }
        /* Dev mode table */
        .dev-table { 
            width:100%; 
            border-collapse:collapse; 
            font-size:12px;
            min-width: 100%;
            border: 1px solid #e6e6e6;
        }
        .dev-table thead th { 
            padding:10px 8px; 
            background:#fff;
            border: 1px solid #e9ecef;
            font-weight:700; 
            text-align:left;
            white-space:nowrap;
            position:sticky;
            top:0;
            z-index:2;
        }
        .dev-table tbody td { 
            padding:8px; 
            border: 1px solid #e9ecef; 
            vertical-align:top;
            max-width:200px;
            overflow:hidden;
            text-overflow:ellipsis;
        }
        .dev-table tbody tr:hover { background:#f8f9fa; }
        .dev-table .action-col { width:60px; text-align:center; white-space:nowrap; }
        .dev-delete-btn { 
            background:transparent; 
            border:none; 
            color:#6c757d; 
            cursor:pointer; 
            padding:6px; 
            border-radius:6px;
            font-size:16px;
        }
        .dev-delete-btn:hover { background:#fff2f2; color:#dc3545; }
        /* Mode toggle styling */
        .mode-toggle { display:inline-flex; background:#fff; border:1px solid #e9ecef; border-radius:8px; overflow:hidden; }
        .mode-toggle .mode-btn { padding:6px 12px; border:0; background:transparent; cursor:pointer; font-weight:700; color:#495057; }
        .mode-toggle .mode-btn.active { background:#dc3545; color:#fff; }
        /* Dev table improvements */
        .group-table { border:1px solid #e6e6e6; border-collapse:collapse; }
        .group-table thead th { position: sticky; top: 0; background: #fff; z-index: 3; border:1px solid #e9ecef; }
        .group-table tbody td { border:1px solid #e9ecef; padding:8px; }
        .dev-cell { white-space:nowrap; max-width:200px; overflow:hidden; text-overflow:ellipsis; }
    </style>
</head>
<body>
    <?php include '../../../templates/header_ui.php'; ?>
    <?php include '../../../templates/sidebar.php'; ?>

    <div style="padding:18px;">
        <?php bp_section_header_html('fa-solid fa-code-compare', 'Duplicate Checker', 'Transaction duplicates in the database'); ?>

        <div style="margin-top:12px; display:flex; align-items:center; gap:12px;">
            <div class="controls" style="display:flex; gap:8px; align-items:center;">
                <button id="btn-check" class="btn-proceed">Check Duplicates</button>
                <button id="btn-export" class="btn-proceed" style="display:none;background:#0d6efd;color:#fff;margin-left:6px;">Export</button>
                <button id="btn-delete-all" class="btn-proceed" style="display:none;background:#6c757d;">Delete All Duplicates</button>
            </div>
            <div style="margin-left:auto; display:flex; align-items:center; gap:8px;">
                <label style="font-weight:700; color:#495057; margin-right:6px;">Mode:</label>
                <div id="mode-toggle" class="mode-toggle" role="tablist" aria-label="Duplicate checker mode">
                    <button type="button" class="mode-btn active" data-mode="normal" aria-pressed="true">Normal Mode</button>
                    <button type="button" class="mode-btn" data-mode="dev" aria-pressed="false">Dev Mode</button>
                </div>
            </div>
        </div>
        <div id="result-count" style="margin-top:10px;color:#6c757d"></div>
    </div>

    <div id="loading-overlay">
            <div class="loading-spinner"></div>
        </div>

    <!-- Duplicates container (inline under the result-count) -->
    <div id="duplicates-container" style="display:block; width:100%;">
        <div class="modal-card" id="modal-card" style="max-height:calc(100vh - 110px); width:100%; max-width:100%;"> </div>
    </div>

    <script>
        // Renderers for Normal and Dev modes
        // Format a date string to "Month dd, yyyy" (e.g. January 01, 2026)
        function formatLongDate(val){
            if(!val && val !== 0) return '';
            try{
                var s = String(val).trim();
                if(s === '') return '';
                // convert common SQL datetime 'YYYY-MM-DD HH:MM:SS' to ISO 'YYYY-MM-DDTHH:MM:SS'
                if(/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/.test(s)){
                    s = s.replace(' ', 'T');
                }
                // If it's just date 'YYYY-MM-DD' it's parseable
                var d = new Date(s);
                if(isNaN(d.getTime())) return val;
                return d.toLocaleDateString('en-US', { month: 'long', day: '2-digit', year: 'numeric' });
            } catch(e){ return val; }
        }
        function renderNormal(groups){
            if(!groups || groups.length === 0){
                $('#modal-card').html('<div style="padding:10px;color:#6c757d">No duplicates found.</div>');
                $('#btn-delete-all').hide();
                $('#result-count').text('');
                return;
            }
            let html = '<div style="overflow:auto"><table class="group-table"><thead><tr><th style="width:60%">Reference No.</th><th style="width:20%">Duplicate Count</th></tr></thead><tbody>';
            groups.forEach(function(g){
                html += '<tr><td>' + (g.reference_no||'') + '</td><td style="text-align:right">' + (g.count||0) + '</td></tr>';
            });
            html += '</tbody></table></div>';
            $('#modal-card').html(html);
            $('#result-count').text('Found ' + groups.length + ' reference_no(s) with duplicates.');
            $('#btn-delete-all').hide();
            // hide export when not in Dev mode
            $('#btn-export').hide();
            setTimeout(function(){ document.getElementById('modal-card').scrollIntoView({behavior:'smooth', block:'start'}); }, 60);
        }

        // Legacy detailed renderer (used by restored Normal mode)
        function renderLegacy(groups){
            removeSummaryIcon();
            if(!groups || groups.length === 0){
                $('#modal-card').html('<div style="padding:10px;color:#6c757d">No duplicates found.</div>');
                $('#btn-delete-all').hide();
                $('#result-count').text('');
                return;
            }
            let html = '';
            let totalDuplicates = 0;
            groups.forEach(function(g){
                const rows = g.rows || [];
                if(rows.length === 0) return;
                html += '<div style="margin-bottom:8px;font-weight:700;">Reference No.: '+ (rows[0].reference_no || '') +'</div>';
                rows.forEach(function(r, idx){
                    const cls = idx === 0 ? 'green' : 'red';
                    if(idx > 0) totalDuplicates++;
                    const partnerName = r.partner_name || '';
                    const partnerKpx = r.partner_id_kpx || '';
                    const partnerId = r.partner_id || '';
                          const amount = (typeof r.amount_paid !== 'undefined' && r.amount_paid !== null && r.amount_paid !== '') ? ('₱' + Number(r.amount_paid).toLocaleString('en-PH', {minimumFractionDigits:2, maximumFractionDigits:2})) : '';
                          const formattedDate = formatLongDate(r.datetime || '');
                          html += '<div class="dup-row '+cls+'" data-id="'+r.id+'">'
                                + '<div><div><strong>'+ (r.reference_no || '') +'</strong></div>'
                                + '<div style="font-size:12px;color:#6c757d">'
                                    + '<span title="Datetime">'+ formattedDate +'</span> • '
                                    + '<span title="Partner Name">'+ partnerName +'</span> • '
                                    + '<span title="Partner ID">'+ partnerId +'</span> • '
                                    + '<span title="Partner KPX ID">'+ partnerKpx +'</span> • '
                                    + '<span title="Amount">'+ amount +'</span>'
                                + '</div></div>'
                                + '<div class="dup-actions">' + (idx>0? '<button class="btn-delete" title="Delete" data-id="'+r.id+'"><i class="fa-solid fa-trash"></i></button>' : '') + '</div>'
                                + '</div>';
                });
                html += '<hr>';
            });
            $('#modal-card').html(html);
            $('#result-count').text('Found '+ totalDuplicates +' duplicate row(s).');
            if(totalDuplicates>0) $('#btn-delete-all').show(); else $('#btn-delete-all').hide();
            // hide export when showing legacy view
            $('#btn-export').hide();
            setTimeout(function(){ document.getElementById('modal-card').scrollIntoView({behavior:'smooth', block:'start'}); }, 60);
        }

        function renderDev(groups){
            removeSummaryIcon();
            if(!groups || groups.length === 0){
                $('#modal-card').html('<div style="padding:10px;color:#6c757d">No duplicates found.</div>');
                $('#btn-delete-all').hide();
                $('#btn-export').hide();
                $('#result-count').text('');
                return;
            }
            // columns to compare (in requested order)
            const columns = [
                'id','status','billing_invoice','datetime','cancellation_date','source_file','control_no','reference_no','payor','address','account_no','account_name','amount_paid','charge_to_customer','charge_to_partner','contact_no','other_details','branch_id','branch_code','outlet','zone_code','region_code','region','operator','remote_branch','remote_operator','2nd_approver','partner_name','partner_id','partner_id_kpx','mpm_gl_code','settle_unsettle','claim_unclaim','imported_by','imported_date','rfp_no','cad_no','hold_status','post_transaction'
            ];

            let html = '';
            let totalDupRows = 0;
            groups.forEach(function(g){
                const rows = g.rows || [];
                if(rows.length === 0) return;
                
                // Card container for each reference_no group
                html += '<div class="dev-group-card">';
                
                // Card header
                html += '<div class="dev-group-header">';
                html += 'Reference No.: <strong>' + (g.reference_no||'') + '</strong>';
                html += ' &nbsp;<span style="color:#6c757d;font-weight:400;">(' + rows.length + ' rows)</span>';
                html += '</div>';
                
                // Card body with table
                html += '<div class="dev-group-body">';

                // determine uniformity per column
                const uniform = {};
                columns.forEach(function(col){
                    const vals = new Set(rows.map(r => (typeof r[col] === 'undefined' || r[col] === null) ? '' : String(r[col])));
                    uniform[col] = (vals.size === 1);
                });

                // build table
                html += '<table class="dev-table">';
                html += '<thead><tr>';
                html += '<th class="action-col">Action</th>'; // DELETE ICON COLUMN FIRST
                columns.forEach(function(col){ html += '<th>' + col + '</th>'; });
                html += '</tr></thead><tbody>';

                rows.forEach(function(r, idx){
                    html += '<tr data-id="'+(r.id||'')+'">';
                    
                    // Action column (delete icon) - LEFTMOST
                    html += '<td class="action-col">';
                    html += '<button class="dev-delete-btn btn-delete" title="Delete this row" data-id="'+r.id+'">';
                    html += '<i class="fa-solid fa-trash"></i>';
                    html += '</button>';
                    html += '</td>';
                    
                    // Data columns with green/red coloring
                    columns.forEach(function(col){
                        var raw = (typeof r[col] === 'undefined' || r[col] === null) ? '' : String(r[col]);
                        var display = raw;
                        // format date-like columns
                        if(col === 'datetime' || col.toLowerCase().includes('date')){
                            display = formatLongDate(raw);
                        }
                        const cls = uniform[col] ? 'cell-green' : 'cell-red';
                        html += '<td class="'+cls+'" title="'+$('<div>').text(display).html()+'">' + $('<div>').text(display).html() + '</td>';
                    });
                    
                    html += '</tr>';
                    if(idx>0) totalDupRows++;
                });
                html += '</tbody></table>';
                html += '</div>'; // dev-group-body
                html += '</div>'; // dev-group-card
            });

            $('#modal-card').html(html);
            $('#result-count').text('Found '+ totalDupRows +' duplicate row(s) across ' + groups.length + ' reference number(s).');
            // HIDE bulk delete button in Dev Mode - manual deletion only
            $('#btn-delete-all').hide();
            // Show Export button in Dev Mode
            $('#btn-export').show();
            // store last dev groups for export
            window.lastDevGroups = groups;
            setTimeout(function(){ document.getElementById('modal-card').scrollIntoView({behavior:'smooth', block:'start'}); }, 60);
        }

        function showOverlay(){ $('#loading-overlay').css('display','flex'); }
        function hideOverlay(){ $('#loading-overlay').hide(); }

        $(function(){
            $('#btn-check').on('click', function(){
                showOverlay();
                $('#modal-card').html('Checking duplicates...');
                // read mode from the toggle buttons
                const mode = $('#mode-toggle .mode-btn.active').data('mode') || 'normal';
                $.post(window.location.href, { check_duplicates_db: 1, mode: mode }, function(resp){
                        if(resp && resp.success){
                                // Normal (legacy) - if no groups found, request global summary
                                    if(resp.mode === 'normal'){
                                        if(!resp.groups || resp.groups.length === 0){
                                            // request summary counts
                                            // clear the checking text
                                            $('#modal-card').html('');
                                            requestSummary();
                                        } else {
                                            removeSummaryIcon();
                                            renderLegacy(resp.groups);
                                        }
                                    }
                                    else if(resp.mode === 'dev') { removeSummaryIcon(); renderDev(resp.groups); }
                                    else renderNormal(resp.groups);
                        } else { $('#modal-card').html('<div style="padding:10px;color:#c00">Error occurred</div>'); }
                    hideOverlay();
                }, 'json').fail(function(){ $('#modal-card').html('<div style="padding:10px;color:#c00">Request failed</div>'); hideOverlay(); });
            });

                // request summary (global reference_no counts)
                function requestSummary(){
                    $.post(window.location.href, { check_duplicates_db: 1, mode: 'summary' }, function(sr){
                        if(sr && sr.success && sr.groups && sr.groups.length>0){
                            // show yellow question icon/button next to Check Duplicates
                            showSummaryIcon(sr.groups);
                            // Inform the user that a summary is available
                            $('#modal-card').html('<div style="padding:10px;color:#6c757d">No grouped duplicates found. A global summary is available (click the yellow icon).</div>');
                            $('#result-count').text('0 duplicates found');
                        } else {
                            removeSummaryIcon();
                            $('#modal-card').html('<div style="padding:10px;color:#6c757d">No duplicates found.</div>');
                            $('#result-count').text('0 duplicates found');
                        }
                    }, 'json');
                }

                // show the yellow question icon with click handler to open modal with summary table
                function showSummaryIcon(groups){
                    removeSummaryIcon();
                    const btn = $('<button id="btn-summary" title="Show global duplicate summary" style="background:#ffd966;border:0;padding:8px 10px;border-radius:6px;margin-left:8px;cursor:pointer;color:#212529;font-weight:700;"></button>');
                    btn.html('<i class="fa-solid fa-circle-question"></i>');
                    $('#btn-check').after(btn);
                    btn.on('click', function(){
                        // build improved HTML table using existing styles
                        let html = '<div style="max-height:60vh; overflow:auto; padding:12px 6px; position:relative;">';
                        html += '<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">';
                        html += '<div style="color:#6c757d;font-size:13px;">Switch to dev mode for root checking duplicates to confirm duplicates issue</div>';
                        html += '<div><button id="switch-to-dev" class="btn-proceed" style="background:#dc3545;color:#fff;border:none;padding:6px 10px;border-radius:6px;font-weight:700;">Switch to Dev Mode</button></div>';
                        html += '</div>';
                        html += '<table class="group-table" style="width:100%;">';
                        html += '<thead><tr><th style="text-align:left;padding:8px;">Reference No.</th><th style="text-align:right;padding:8px;">Total Count</th></tr></thead><tbody>';
                        groups.forEach(function(g){ html += '<tr><td style="padding:8px;border-bottom:1px solid #f5f5f5;">'+ $('<div>').text(g.reference_no).html() +'</td><td style="padding:8px;text-align:right;border-bottom:1px solid #f5f5f5;">'+ (g.total_count||0) +'</td></tr>'; });
                        html += '</tbody></table></div>';

                        Swal.fire({
                            title: 'Potential duplicates (summary)',
                            html: html,
                            width: '80%',
                            showConfirmButton: false,
                            showCloseButton: true,
                            didOpen: () => {
                                // attach handler for switch to dev
                                $('#switch-to-dev').on('click', function(){
                                    // remove summary icon before switching
                                    removeSummaryIcon();
                                    // switch toggle to dev and trigger check
                                    $('#mode-toggle .mode-btn').removeClass('active').attr('aria-pressed','false');
                                    $('#mode-toggle .mode-btn[data-mode="dev"]').addClass('active').attr('aria-pressed','true');
                                    Swal.close();
                                    $('#btn-check').trigger('click');
                                });
                            }
                        });
                    });
                }

                function removeSummaryIcon(){ $('#btn-summary').remove(); }

            // Mode toggle click handler
            $(document).on('click', '#mode-toggle .mode-btn', function(){
                $('#mode-toggle .mode-btn').removeClass('active').attr('aria-pressed','false');
                $(this).addClass('active').attr('aria-pressed','true');
            });

            // delete single (with SweetAlert2 confirmation)
            $(document).on('click', '.btn-delete', function(){
                const id = $(this).data('id');
                Swal.fire({
                    title: 'Delete this duplicate?',
                    text: 'This will permanently remove the selected duplicate row.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#dc3545',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Delete',
                    cancelButtonText: 'Cancel'
                }).then((result) => {
                    if (result.isConfirmed) {
                        showOverlay();
                        $.post(window.location.href, { delete_duplicate: 1, id: id }, function(res){
                            hideOverlay();
                            if(res && res.success){
                                $('[data-id="'+id+'"]').remove();
                                Swal.fire('Deleted','Row removed','success');
                            } else {
                                Swal.fire('Error','Delete failed','error');
                            }
                        }, 'json').fail(function(){ hideOverlay(); Swal.fire('Error','Request failed','error'); });
                    }
                });
            });

            // delete all duplicates (delete all red rows currently shown)
            $('#btn-delete-all').on('click', function(){
                // collect red rows ids
                const ids = [];
                $('#modal-card .dup-row.red').each(function(){ ids.push($(this).data('id')); });
                if(ids.length === 0) { Swal.fire('Nothing to delete','No duplicate rows selected','info'); return; }
                Swal.fire({
                    title: 'Delete ALL duplicates?',
                    text: 'This will permanently remove all duplicate rows currently displayed.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#dc3545',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Delete All',
                    cancelButtonText: 'Cancel'
                }).then((result) => {
                    if (result.isConfirmed) {
                        showOverlay();
                        $.post(window.location.href, { delete_multiple: 1, ids: ids }, function(resp){
                            hideOverlay();
                            if(resp && resp.success){
                                $('#modal-card .dup-row.red').remove();
                                $('#btn-delete-all').hide();
                                $('#result-count').text('');
                                Swal.fire('Deleted','Selected rows removed','success');
                            } else {
                                Swal.fire('Error','Delete failed','error');
                            }
                        }, 'json').fail(function(){ hideOverlay(); Swal.fire('Error','Request failed','error'); });
                    }
                });
            });

            // Helpers for export
            function escapeHtml(str){
                return String(str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
            }

            function exportToExcel(groups){
                const columns = [
                    'id','status','billing_invoice','datetime','cancellation_date','source_file','control_no','reference_no','payor','address','account_no','account_name','amount_paid','charge_to_customer','charge_to_partner','contact_no','other_details','branch_id','branch_code','outlet','zone_code','region_code','region','operator','remote_branch','remote_operator','2nd_approver','partner_name','partner_id','partner_id_kpx','mpm_gl_code','settle_unsettle','claim_unclaim','imported_by','imported_date','rfp_no','cad_no','hold_status','post_transaction'
                ];

                let html = '<!DOCTYPE html><html><head><meta charset="utf-8"><meta http-equiv="X-UA-Compatible" content="IE=edge" /><meta name="viewport" content="width=device-width, initial-scale=1.0" /><style>table{border-collapse:collapse;font-family:Arial,Helvetica,sans-serif}th,td{border:1px solid #e9ecef;padding:6px;}</style></head><body>';
                html += '<h3>Duplicate Checker - Dev Export</h3>';

                groups.forEach(function(g){
                    const rows = g.rows || [];
                    if(rows.length === 0) return;
                    const uniform = {};
                    columns.forEach(function(col){
                        const vals = new Set(rows.map(r => (typeof r[col] === 'undefined' || r[col] === null) ? '' : String(r[col])));
                        uniform[col] = (vals.size === 1);
                    });

                    html += '<div style="margin-top:14px;margin-bottom:6px;font-weight:700;">Reference No.: ' + escapeHtml(g.reference_no || '') + ' (' + rows.length + ' rows)</div>';
                    html += '<table><thead><tr><th>Action</th>';
                    columns.forEach(function(col){ html += '<th>' + escapeHtml(col) + '</th>'; });
                    html += '</tr></thead><tbody>';

                    rows.forEach(function(r){
                        html += '<tr>';
                        html += '<td></td>';
                        columns.forEach(function(col){
                            var raw = (typeof r[col] === 'undefined' || r[col] === null) ? '' : String(r[col]);
                            var display = raw;
                            if(col === 'datetime' || col.toLowerCase().includes('date')){ display = formatLongDate(raw); }
                            const bg = uniform[col] ? '#e6ffed' : '#fff2f2';
                            html += '<td style="background:' + bg + ';">' + escapeHtml(display) + '</td>';
                        });
                        html += '</tr>';
                    });

                    html += '</tbody></table>';
                });

                html += '</body></html>';

                const blob = new Blob([html], { type: 'application/vnd.ms-excel' });
                const ts = new Date().toISOString().replace(/[:.]/g,'-');
                const filename = 'duplicate_report_dev_' + ts + '.xls';
                const link = document.createElement('a');
                link.href = URL.createObjectURL(blob);
                link.download = filename;
                document.body.appendChild(link);
                link.click();
                setTimeout(function(){ URL.revokeObjectURL(link.href); link.remove(); }, 5000);
            }

            function exportToPDF(groups){
                // build same HTML as Excel export but render to PDF via html2pdf
                let html = '<div style="font-family:Arial,Helvetica,sans-serif;">';
                html += '<h3>Duplicate Checker - Dev Export</h3>';
                const columns = [
                    'id','status','billing_invoice','datetime','cancellation_date','source_file','control_no','reference_no','payor','address','account_no','account_name','amount_paid','charge_to_customer','charge_to_partner','contact_no','other_details','branch_id','branch_code','outlet','zone_code','region_code','region','operator','remote_branch','remote_operator','2nd_approver','partner_name','partner_id','partner_id_kpx','mpm_gl_code','settle_unsettle','claim_unclaim','imported_by','imported_date','rfp_no','cad_no','hold_status','post_transaction'
                ];

                groups.forEach(function(g){
                    const rows = g.rows || [];
                    if(rows.length === 0) return;
                    const uniform = {};
                    columns.forEach(function(col){
                        const vals = new Set(rows.map(r => (typeof r[col] === 'undefined' || r[col] === null) ? '' : String(r[col])));
                        uniform[col] = (vals.size === 1);
                    });

                    html += '<div style="margin-top:14px;margin-bottom:6px;font-weight:700;">Reference No.: ' + escapeHtml(g.reference_no || '') + ' (' + rows.length + ' rows)</div>';
                    html += '<table style="width:100%;border-collapse:collapse;">';
                    html += '<thead><tr><th style="border:1px solid #e9ecef;padding:6px;">Action</th>';
                    columns.forEach(function(col){ html += '<th style="border:1px solid #e9ecef;padding:6px;">' + escapeHtml(col) + '</th>'; });
                    html += '</tr></thead><tbody>';

                    rows.forEach(function(r){
                        html += '<tr>';
                        html += '<td style="border:1px solid #e9ecef;padding:6px;"></td>';
                        columns.forEach(function(col){
                            var raw = (typeof r[col] === 'undefined' || r[col] === null) ? '' : String(r[col]);
                            var display = raw;
                            if(col === 'datetime' || col.toLowerCase().includes('date')){ display = formatLongDate(raw); }
                            const bg = uniform[col] ? '#e6ffed' : '#fff2f2';
                            html += '<td style="background:' + bg + ';border:1px solid #e9ecef;padding:6px;">' + escapeHtml(display) + '</td>';
                        });
                        html += '</tr>';
                    });

                    html += '</tbody></table>';
                });

                html += '</div>';

                // create container and call html2pdf
                const container = document.createElement('div');
                container.style.padding = '10px';
                container.innerHTML = html;
                document.body.appendChild(container);

                const opt = {
                    margin:       10,
                    filename:     'duplicate_report_dev_' + new Date().toISOString().replace(/[:.]/g,'-') + '.pdf',
                    image:        { type: 'jpeg', quality: 0.98 },
                    html2canvas:  { scale: 2, useCORS: true },
                    jsPDF:        { unit: 'mm', format: 'a4', orientation: 'landscape' }
                };

                // html2pdf available via CDN
                try {
                    html2pdf().set(opt).from(container).save().then(function(){ setTimeout(function(){ container.remove(); }, 5000); });
                } catch (e) {
                    container.remove();
                    Swal.fire('Error','PDF export failed: ' + (e.message || e),'error');
                }
            }

            // open modal to choose format
            $('#btn-export').on('click', function(){
                const groups = window.lastDevGroups || [];
                if(!groups || groups.length === 0){ Swal.fire('No data','No dev-mode data to export','info'); return; }

                const html = '<div style="display:flex;gap:10px;justify-content:center;padding:8px;"><button id="export-excel" class="swal2-confirm swal2-styled" style="background:#0d6efd;border:none;color:#fff;">Export Excel</button><button id="export-pdf" class="swal2-confirm swal2-styled" style="background:#6c757d;border:none;color:#fff;">Export PDF</button></div>';

                Swal.fire({ title: 'Export format', html: html, showConfirmButton: false, showCloseButton: true, didOpen: () => {
                    document.getElementById('export-excel').onclick = function(){ exportToExcel(groups); Swal.close(); };
                    document.getElementById('export-pdf').onclick = function(){ exportToPDF(groups); Swal.close(); };
                }});
            });

            // ensure duplicates container is visible when results are present
            // (click-outside handler removed because results are inline)
        });
    </script>
    <?php include '../../../templates/footer.php'; ?>
</body>
</html>
