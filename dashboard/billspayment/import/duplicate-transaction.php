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
    $results = [];

    // Grouping criteria: reference_no + DATE(datetime) + partner_id + amount_paid
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

    echo json_encode(['success' => true, 'groups' => $results]);
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
    </style>
</head>
<body>
    <?php include '../../../templates/header_ui.php'; ?>
    <?php include '../../../templates/sidebar.php'; ?>

    <div style="padding:18px;">
        <?php bp_section_header_html('fa-solid fa-code-compare', 'Duplicate Checker', 'Transaction duplicates in the database'); ?>

        <div style="margin-top:12px;">
            <div class="controls">
                <button id="btn-check" class="btn-proceed">Check Duplicates</button>
                <button id="btn-delete-all" class="btn-proceed" style="display:none;background:#6c757d;">Delete All Duplicates</button>
            </div>
            <div id="result-count" style="margin-top:10px;color:#6c757d"></div>
        </div>
    </div>

    <div id="loading-overlay">
            <div class="loading-spinner"></div>
        </div>

    <!-- Duplicates container (inline under the result-count) -->
    <div id="duplicates-container" style="display:block; width:100%;">
        <div class="modal-card" id="modal-card" style="max-height:calc(100vh - 110px); width:100%; max-width:100%;"> </div>
    </div>

    <script>
        function renderGroups(groups){
                if(!groups || groups.length === 0){
                    $('#modal-card').html('<div style="padding:10px;color:#6c757d">No duplicates found.</div>');
                    $('#btn-delete-all').hide();
                    $('#result-count').text('');
                    return;
                }
            let html = '';
            let totalDuplicates = 0;
            groups.forEach(function(g){
                const rows = g.rows;
                if(rows.length === 0) return;
                // first is original (green)
                html += '<div style="margin-bottom:8px;font-weight:700;">Reference: '+ (rows[0].reference_no || '') +'</div>';
                rows.forEach(function(r, idx){
                    const cls = idx === 0 ? 'green' : 'red';
                    if(idx > 0) totalDuplicates++;
                    const partnerName = r.partner_name || '';
                    const partnerKpx = r.partner_id_kpx || '';
                    const partnerId = r.partner_id || '';
                    const amount = (typeof r.amount_paid !== 'undefined' && r.amount_paid !== null && r.amount_paid !== '') ? ('₱' + Number(r.amount_paid).toLocaleString('en-PH', {minimumFractionDigits:2, maximumFractionDigits:2})) : '';
                    html += '<div class="dup-row '+cls+'" data-id="'+r.id+'">'
                        + '<div><div><strong>'+ (r.reference_no || '') +'</strong></div>'
                        + '<div style="font-size:12px;color:#6c757d">'
                           + '<span title="Datetime">'+(r.datetime||'')+'</span> • '
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
            // scroll to results
            setTimeout(function(){ document.getElementById('modal-card').scrollIntoView({behavior:'smooth', block:'start'}); }, 60);
        }

        function showOverlay(){ $('#loading-overlay').css('display','flex'); }
        function hideOverlay(){ $('#loading-overlay').hide(); }

        $(function(){
            $('#btn-check').on('click', function(){
                showOverlay();
                $('#modal-card').html('Checking duplicates...');
                $.post(window.location.href, { check_duplicates_db: 1 }, function(resp){
                    if(resp && resp.success){ renderGroups(resp.groups); }
                    else { $('#modal-card').html('<div style="padding:10px;color:#c00">Error occurred</div>'); }
                    hideOverlay();
                }, 'json').fail(function(){ $('#modal-card').html('<div style="padding:10px;color:#c00">Request failed</div>'); hideOverlay(); });
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

            // ensure duplicates container is visible when results are present
            // (click-outside handler removed because results are inline)
        });
    </script>
    <?php include '../../../templates/footer.php'; ?>
</body>
</html>
