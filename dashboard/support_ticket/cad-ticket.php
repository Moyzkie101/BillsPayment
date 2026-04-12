<?php
include_once __DIR__ . '/includes/bootstrap.php';
include_once __DIR__ . '/includes/ticket_queries.php';

st_require_login('../../login_form.php');
st_require_permission_page(['Support Ticket CAD'], '../home.php');

$userId = st_user_id_or_null();
$flash = st_flash_get('cad_ticket');
$mode = strtolower(trim((string) ($_GET['mode'] ?? 'open')));
if (!in_array($mode, ['open', 'active', 'closed'], true)) {
    $mode = 'open';
}

$cadOpen = st_get_cad_open_tickets($conn);
$cadActive = $userId !== null ? st_get_cad_active_tickets($conn, $userId) : [];
$cadClosed = $userId !== null ? st_get_cad_closed_tickets($conn, $userId) : [];

function st_status_class_cad($status)
{
    return 'st-status st-status-' . strtolower((string) $status);
}

function st_partner_name_cad($ticket)
{
    $partner = trim((string) ($ticket['partner_name'] ?? ''));
    if ($partner !== '') {
        return $partner;
    }
    $ext = trim((string) ($ticket['partner_ext_id'] ?? ''));
    return $ext !== '' ? $ext : 'N/A';
}

function st_trail_type_label_cad($type)
{
    $t = strtolower(trim((string) $type));
    if ($t === 'accept') return 'Accepted';
    if ($t === 'transfer') return 'Transferred';
    if ($t === 'resolve') return 'Resolved';
    if ($t === 'close') return 'Closed';
    if ($t === 'auto_close') return 'Auto Closed';
    return 'Message';
}

function st_trail_role_icon_cad($role)
{
    $r = strtoupper(trim((string) $role));
    if ($r === 'BRANCH') return '🟢';
    if ($r === 'VPO') return '🔵';
    if ($r === 'CAD') return '🔴';
    return '⚙️';
}

function st_get_ticket_attachments_grouped_by_trail_cad($conn, $ticketId)
{
    $schema = st_schema();
    $sql = "SELECT id, ticket_trail_id, file_name, mime_type, file_size, created_at
            FROM {$schema}.ticket_attachments
            WHERE ticket_id = ?
            ORDER BY id ASC";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }

    $stmt->bind_param('i', $ticketId);
    if (!$stmt->execute()) {
        $stmt->close();
        return [];
    }

    $res = $stmt->get_result();
    $grouped = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $trailId = (int) ($row['ticket_trail_id'] ?? 0);
            if ($trailId <= 0) {
                continue;
            }
            if (!isset($grouped[$trailId])) {
                $grouped[$trailId] = [];
            }
            $grouped[$trailId][] = $row;
        }
    }

    $stmt->close();
    return $grouped;
}

$ticketTrailsByTicketId = [];
$ticketAttachmentsByTicketId = [];
$ticketSupplementalByTicketNumber = [];
$ownerIds = [];
$allCadTickets = array_merge($cadOpen, $cadActive, $cadClosed);
foreach ($allCadTickets as $ticket) {
    $ticketId = (int) ($ticket['id'] ?? 0);
    if ($ticketId <= 0 || isset($ticketTrailsByTicketId[$ticketId])) {
        continue;
    }
    $ticketTrailsByTicketId[$ticketId] = st_get_ticket_trails($conn, $ticketId);
    $ticketAttachmentsByTicketId[$ticketId] = st_get_ticket_attachments_grouped_by_trail_cad($conn, $ticketId);

    $ticketNumber = (string) ($ticket['ticket_number'] ?? '');
    if ($ticketNumber !== '' && !isset($ticketSupplementalByTicketNumber[$ticketNumber])) {
        $ticketSupplementalByTicketNumber[$ticketNumber] = [
            'wrongbiller' => st_get_ticket_wrongbiller_by_ticket_number($conn, $ticketNumber),
            'overstated' => st_get_ticket_overstatedamount_by_ticket_number($conn, $ticketNumber),
            'cancelled' => st_get_ticket_cancelledtransaction_by_ticket_number($conn, $ticketNumber),
        ];
    }

    if (isset($ticket['vpo_owner']) && is_numeric($ticket['vpo_owner'])) {
        $ownerIds[] = (int) $ticket['vpo_owner'];
    }
    if (isset($ticket['cad_owner']) && is_numeric($ticket['cad_owner'])) {
        $ownerIds[] = (int) $ticket['cad_owner'];
    }
    if (isset($ticket['created_by']) && is_numeric($ticket['created_by'])) {
        $ownerIds[] = (int) $ticket['created_by'];
    }
}

$ownerNamesById = st_get_user_names_by_id_numbers($conn, $ownerIds);
st_sync_ticket_active_counts($conn, $userId);
$activeBadgeRowCad = st_get_ticket_active_row($conn, $userId);
$cadActiveBadgeCount = (int) ($activeBadgeRowCad['cad_count'] ?? count($cadActive));

$ticketNumbersCad = [];
foreach ($allCadTickets as $ticket) {
    $tn = trim((string) ($ticket['ticket_number'] ?? ''));
    if ($tn !== '') {
        $ticketNumbersCad[] = $tn;
    }
}
$ticketBadgeCountsCad = st_get_ticket_badge_counts($conn, $ticketNumbersCad, 'CAD');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Support Ticket - CAD</title>
    <link rel="icon" href="../../images/MLW%20logo.png" type="image/png">
    <link rel="stylesheet" href="../../assets/css/templates/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="assets/css/support-ticket.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="assets/css/ticket-modal.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="assets/css/image-preview.css?v=<?php echo time(); ?>">
    <script src="https://kit.fontawesome.com/30b908cc5a.js" crossorigin="anonymous"></script>
</head>
<body>
    <div class="main-container">
        <?php include '../../templates/header_ui.php'; ?>
        <?php include '../../templates/sidebar.php'; ?>

        <?php if (function_exists('bp_section_header_html')): ?>
            <?php bp_section_header_html('fa-solid fa-screwdriver-wrench', 'Support Ticket - CAD', 'Open / Active / Closed'); ?>
        <?php else: ?>
            <div class="container-fluid mt-3"><h3>Support Ticket - CAD</h3></div>
        <?php endif; ?>

        <div class="container-fluid st-wrapper">
            <?php if ($flash): ?>
                <div class="alert alert-<?php echo htmlspecialchars($flash['type']); ?>" role="alert">
                    <?php echo htmlspecialchars($flash['message']); ?>
                </div>
            <?php endif; ?>

            <div class="mode-cards" data-st-mode-group data-st-param="mode">
                <label class="mode-card <?php echo $mode === 'open' ? 'selected' : ''; ?>" data-mode="open">
                    <input type="radio" name="cadMode" value="open" <?php echo $mode === 'open' ? 'checked' : ''; ?>>
                    <div class="mode-icon"><i class="fa-solid fa-inbox"></i></div>
                    <div class="mode-text"><p class="mode-label">OPEN</p><small>Unassigned CAD queue</small></div>
                </label>

                <label class="mode-card <?php echo $mode === 'active' ? 'selected' : ''; ?>" data-mode="active">
                    <input type="radio" name="cadMode" value="active" <?php echo $mode === 'active' ? 'checked' : ''; ?>>
                    <div class="mode-icon"><i class="fa-solid fa-bolt"></i></div>
                    <div class="mode-text"><p class="mode-label">ACTIVE</p><small>Assigned to you</small></div>
                    <?php if ($cadActiveBadgeCount > 0): ?><span class="st-mode-count-badge"><?php echo (int) $cadActiveBadgeCount; ?></span><?php endif; ?>
                </label>

                <label class="mode-card <?php echo $mode === 'closed' ? 'selected' : ''; ?>" data-mode="closed">
                    <input type="radio" name="cadMode" value="closed" <?php echo $mode === 'closed' ? 'checked' : ''; ?>>
                    <div class="mode-icon"><i class="fa-solid fa-box-archive"></i></div>
                    <div class="mode-text"><p class="mode-label">CLOSED</p><small>Completed tickets</small></div>
                </label>
            </div>

            <div class="mode-panel <?php echo $mode === 'open' ? '' : 'hidden'; ?>" data-st-panel="open">
                <?php if (empty($cadOpen)): ?>
                    <div class="st-empty">No tickets in CAD open queue.</div>
                <?php else: ?>
                    <div class="st-ticket-table" role="table" aria-label="Open CAD tickets">
                        <div class="st-ticket-row st-ticket-row-head" role="row">
                            <span class="st-ticket-col st-col-number">Ticket #</span>
                            <span class="st-ticket-col st-col-date">Created</span>
                            <span class="st-ticket-col st-col-type">Type</span>
                            <span class="st-ticket-col st-col-partner">Partner</span>
                            <span class="st-ticket-col st-col-status">Status</span>
                        </div>
                        <?php foreach ($cadOpen as $ticket): ?>
                            <button type="button" class="st-ticket-row" role="row" data-ticket-modal="stTicketTrailModalCad-<?php echo (int) $ticket['id']; ?>" data-ticket-id="<?php echo (int) $ticket['id']; ?>" data-seen-role="CAD">
                                <?php $cadUnread = (int) ($ticketBadgeCountsCad[(string) ($ticket['ticket_number'] ?? '')] ?? 0); ?>
                                <span class="st-ticket-col st-col-number"><?php echo htmlspecialchars((string) $ticket['ticket_number']); ?><?php if ($cadUnread > 0): ?> <span class="st-ticket-unread-badge"><?php echo $cadUnread; ?></span><?php endif; ?></span>
                                <span class="st-ticket-col st-col-date"><?php echo htmlspecialchars((string) $ticket['created_at']); ?></span>
                                <span class="st-ticket-col st-col-type"><?php echo htmlspecialchars((string) ($ticket['ticket_type_label'] ?: $ticket['type_of_request'])); ?></span>
                                <span class="st-ticket-col st-col-partner"><?php echo htmlspecialchars(st_partner_name_cad($ticket)); ?></span>
                                <span class="st-ticket-col st-col-status"><span class="<?php echo htmlspecialchars(st_status_class_cad($ticket['status'])); ?>"><?php echo htmlspecialchars((string) $ticket['status']); ?></span></span>
                            </button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="mode-panel <?php echo $mode === 'active' ? '' : 'hidden'; ?>" data-st-panel="active">
                <?php if (empty($cadActive)): ?>
                    <div class="st-empty">No active tickets assigned to you.</div>
                <?php else: ?>
                    <div class="st-ticket-table" role="table" aria-label="Active CAD tickets">
                        <div class="st-ticket-row st-ticket-row-head" role="row">
                            <span class="st-ticket-col st-col-number">Ticket #</span>
                            <span class="st-ticket-col st-col-date">Created</span>
                            <span class="st-ticket-col st-col-type">Type</span>
                            <span class="st-ticket-col st-col-partner">Partner</span>
                            <span class="st-ticket-col st-col-status">Status</span>
                        </div>
                        <?php foreach ($cadActive as $ticket): ?>
                            <button type="button" class="st-ticket-row" role="row" data-ticket-modal="stTicketTrailModalCad-<?php echo (int) $ticket['id']; ?>" data-ticket-id="<?php echo (int) $ticket['id']; ?>" data-seen-role="CAD">
                                <?php $cadUnread = (int) ($ticketBadgeCountsCad[(string) ($ticket['ticket_number'] ?? '')] ?? 0); ?>
                                <span class="st-ticket-col st-col-number"><?php echo htmlspecialchars((string) $ticket['ticket_number']); ?><?php if ($cadUnread > 0): ?> <span class="st-ticket-unread-badge"><?php echo $cadUnread; ?></span><?php endif; ?></span>
                                <span class="st-ticket-col st-col-date"><?php echo htmlspecialchars((string) $ticket['created_at']); ?></span>
                                <span class="st-ticket-col st-col-type"><?php echo htmlspecialchars((string) ($ticket['ticket_type_label'] ?: $ticket['type_of_request'])); ?></span>
                                <span class="st-ticket-col st-col-partner"><?php echo htmlspecialchars(st_partner_name_cad($ticket)); ?></span>
                                <span class="st-ticket-col st-col-status"><span class="<?php echo htmlspecialchars(st_status_class_cad($ticket['status'])); ?>"><?php echo htmlspecialchars((string) $ticket['status']); ?></span></span>
                            </button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="mode-panel <?php echo $mode === 'closed' ? '' : 'hidden'; ?>" data-st-panel="closed">
                <?php if (empty($cadClosed)): ?>
                    <div class="st-empty">No closed tickets.</div>
                <?php else: ?>
                    <div class="st-ticket-table" role="table" aria-label="Closed CAD tickets">
                        <div class="st-ticket-row st-ticket-row-head" role="row">
                            <span class="st-ticket-col st-col-number">Ticket #</span>
                            <span class="st-ticket-col st-col-date">Created</span>
                            <span class="st-ticket-col st-col-type">Type</span>
                            <span class="st-ticket-col st-col-partner">Partner</span>
                            <span class="st-ticket-col st-col-status">Status</span>
                        </div>
                        <?php foreach ($cadClosed as $ticket): ?>
                            <button type="button" class="st-ticket-row" role="row" data-ticket-modal="stTicketTrailModalCad-<?php echo (int) $ticket['id']; ?>" data-ticket-id="<?php echo (int) $ticket['id']; ?>" data-seen-role="CAD">
                                <?php $cadUnread = (int) ($ticketBadgeCountsCad[(string) ($ticket['ticket_number'] ?? '')] ?? 0); ?>
                                <span class="st-ticket-col st-col-number"><?php echo htmlspecialchars((string) $ticket['ticket_number']); ?><?php if ($cadUnread > 0): ?> <span class="st-ticket-unread-badge"><?php echo $cadUnread; ?></span><?php endif; ?></span>
                                <span class="st-ticket-col st-col-date"><?php echo htmlspecialchars((string) ($ticket['closed_at'] ?: $ticket['created_at'])); ?></span>
                                <span class="st-ticket-col st-col-type"><?php echo htmlspecialchars((string) ($ticket['ticket_type_label'] ?: $ticket['type_of_request'])); ?></span>
                                <span class="st-ticket-col st-col-partner"><?php echo htmlspecialchars(st_partner_name_cad($ticket)); ?></span>
                                <span class="st-ticket-col st-col-status"><span class="<?php echo htmlspecialchars(st_status_class_cad($ticket['status'])); ?>"><?php echo htmlspecialchars((string) $ticket['status']); ?></span></span>
                            </button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <?php foreach ($allCadTickets as $ticket): ?>
                <?php
                    $ticketId = (int) ($ticket['id'] ?? 0);
                    $trails = $ticketTrailsByTicketId[$ticketId] ?? [];
                    $attachmentsByTrail = $ticketAttachmentsByTicketId[$ticketId] ?? [];
                    $ticketTypeText = (string) ($ticket['ticket_type_label'] ?: $ticket['type_of_request']);
                    $statusLower = strtolower((string) ($ticket['status'] ?? ''));
                    $isResolved = $statusLower === 'resolved';
                    $isClosed = $statusLower === 'closed';
                    $isOpen = in_array($statusLower, ['open', 'transferred'], true);
                    $isActive = !$isClosed && !$isResolved && !$isOpen;
                    $currentHandlerRole = strtoupper((string) ($ticket['current_handler_role'] ?? ''));
                    $assignedToId = (int) ($ticket['assigned_to'] ?? 0);
                    $isCadActionable = ($userId !== null && $currentHandlerRole === 'CAD' && $assignedToId === (int) $userId);
                    $ticketNumber = (string) ($ticket['ticket_number'] ?? '');
                    $ticketSupplemental = $ticketSupplementalByTicketNumber[$ticketNumber] ?? [];
                    $vpoOwnerId = (int) ($ticket['vpo_owner'] ?? 0);
                    $cadOwnerId = (int) ($ticket['cad_owner'] ?? 0);
                    $createdById = (int) ($ticket['created_by'] ?? 0);
                    $createdByName = $createdById > 0 ? ($ownerNamesById[$createdById] ?? ('ID ' . $createdById)) : 'N/A';
                    $vpoOwnerName = $vpoOwnerId > 0 ? ($ownerNamesById[$vpoOwnerId] ?? ('ID ' . $vpoOwnerId)) : 'Not assigned';
                    $cadOwnerName = $cadOwnerId > 0 ? ($ownerNamesById[$cadOwnerId] ?? ('ID ' . $cadOwnerId)) : 'Not assigned';

                    $hdrReference = (string) ($ticket['reference_number'] ?? 'N/A');
                    $hdrTransferRaw = (string) ($ticket['transfer_datetime'] ?? '');
                    $hdrTransfer = $hdrTransferRaw;
                    $tsHdr = strtotime($hdrTransferRaw);
                    if ($tsHdr !== false) {
                        $hdrTransfer = date('M d, Y h:i A', $tsHdr);
                    }
                    $hdrAccount = (string) ($ticket['account_no'] ?? ($ticket['account_number'] ?? 'N/A'));
                    $hdrPaymentBranch = (string) ($ticket['payment_branch_name'] ?? ($ticket['payment_branch_id'] ?? 'N/A'));
                    $hdrAmount = isset($ticket['amount']) && $ticket['amount'] !== null && $ticket['amount'] !== '' ? 'PHP ' . number_format((float) $ticket['amount'], 2) : 'N/A';
                ?>
                <div class="tm-overlay" id="stTicketTrailModalCad-<?php echo $ticketId; ?>" aria-hidden="true" role="dialog" aria-modal="true">
                    <div class="tm-modal">
                        <div class="tm-header">
                            <div class="tm-header-top">
                                <div class="tm-header-left">
                                    <div class="tm-ticket-number"><span class="tm-ticket-icon"><i class="fa-solid fa-ticket" aria-hidden="true"></i></span>Ticket #: <?php echo htmlspecialchars((string) $ticket['ticket_number']); ?></div>
                                    <div class="tm-ticket-meta-grid">
                                        <div class="tm-meta-item">
                                            <div class="tm-meta-label">Reference No.</div>
                                            <div class="tm-meta-value"><?php echo htmlspecialchars($hdrReference); ?></div>
                                        </div>
                                        <div class="tm-meta-item">
                                            <div class="tm-meta-label">Transaction D/T</div>
                                            <div class="tm-meta-value"><?php echo htmlspecialchars($hdrTransfer); ?></div>
                                        </div>
                                        <div class="tm-meta-item">
                                            <div class="tm-meta-label">Account No.</div>
                                            <div class="tm-meta-value"><?php echo htmlspecialchars($hdrAccount); ?></div>
                                        </div>
                                        <div class="tm-meta-item">
                                            <div class="tm-meta-label">Payment Branch</div>
                                            <div class="tm-meta-value"><?php echo htmlspecialchars($hdrPaymentBranch); ?></div>
                                        </div>
                                        <div class="tm-meta-item">
                                            <div class="tm-meta-label">Partner</div>
                                            <div class="tm-meta-value"><?php echo htmlspecialchars(st_partner_name_cad($ticket)); ?></div>
                                        </div>
                                        <div class="tm-meta-item">
                                            <div class="tm-meta-label">Created By</div>
                                            <div class="tm-meta-value"><?php echo htmlspecialchars($createdByName); ?></div>
                                        </div>
                                        <div class="tm-meta-item">
                                            <div class="tm-meta-label">Type</div>
                                            <div class="tm-meta-value"><?php echo htmlspecialchars($ticketTypeText); ?></div>
                                        </div>
                                        <div class="tm-meta-item">
                                            <div class="tm-meta-label">Source</div>
                                            <div class="tm-meta-value"><?php echo htmlspecialchars((string) ($ticket['source'] ?? 'N/A')); ?></div>
                                        </div>
                                        <div class="tm-meta-item">
                                            <div class="tm-meta-label">Amount</div>
                                            <div class="tm-meta-value"><?php echo htmlspecialchars($hdrAmount); ?></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="tm-header-right">
                                    <div class="tm-status tm-status--<?php echo htmlspecialchars($statusLower); ?>"><?php echo htmlspecialchars((string) $ticket['status']); ?></div>
                                    <button type="button" class="tm-close-btn" data-st-close-modal="stTicketTrailModalCad-<?php echo $ticketId; ?>" aria-label="Close">&times;</button>
                                </div>
                            </div>
                        </div>

                        <div class="tm-body">
                            <div class="tm-trail">
                                <?php if (empty($trails)): ?>
                                    <div class="tm-empty-trail">No trail entries yet.</div>
                                <?php else: ?>
                                    <?php $lastTrailIndex = count($trails) - 1; ?>
                                    <?php foreach ($trails as $trailIndex => $trail): ?>
                                        <?php
                                            $trailId = (int) ($trail['id'] ?? 0);
                                            $trailRole = strtoupper((string) ($trail['sender_role'] ?? 'SYSTEM'));
                                            $trailType = (string) ($trail['type'] ?? 'message');
                                            $trailDatetimeRaw = (string) ($trail['created_at'] ?? '');
                                            $trailDatetime = $trailDatetimeRaw;
                                            $ts = strtotime($trailDatetimeRaw);
                                            if ($ts !== false) {
                                                $trailDatetime = date('M d, Y h:i A', $ts);
                                            }
                                            $trailAttachments = $attachmentsByTrail[$trailId] ?? [];
                                            $trailMessage = trim((string) ($trail['message'] ?? ''));
                                            $avatarClass = 'tm-trail-avatar--system';
                                            if ($trailRole === 'BRANCH') $avatarClass = 'tm-trail-avatar--branch';
                                            else if ($trailRole === 'VPO') $avatarClass = 'tm-trail-avatar--vpo';
                                            else if ($trailRole === 'CAD') $avatarClass = 'tm-trail-avatar--cad';

                                            $trailOwnerTooltip = '';
                                            if ($trailRole === 'BRANCH') {
                                                $trailOwnerTooltip = $createdByName;
                                            } else if ($trailRole === 'VPO') {
                                                $trailOwnerTooltip = $vpoOwnerName;
                                            } else if ($trailRole === 'CAD') {
                                                $trailOwnerTooltip = $cadOwnerName;
                                            }
                                        ?>
                                        <div class="tm-trail-item">
                                            <div class="tm-trail-dot-wrap">
                                                <div class="tm-trail-avatar <?php echo $avatarClass; ?>"><?php echo htmlspecialchars(st_trail_role_icon_cad($trailRole)); ?></div>
                                            </div>
                                            <div class="tm-trail-card <?php echo $trailRole === 'SYSTEM' ? 'tm-trail-card--system' : ''; ?> <?php echo $trailIndex === $lastTrailIndex ? 'tm-expanded' : ''; ?>" <?php echo $trailIndex === $lastTrailIndex ? 'data-tm-latest="1"' : ''; ?>>
                                                <div class="tm-trail-card-header">
                                                    <div class="tm-trail-avatar <?php echo $avatarClass; ?>"><?php echo htmlspecialchars(st_trail_role_icon_cad($trailRole)); ?></div>
                                                    <div class="tm-trail-meta">
                                                        <div class="tm-trail-sender">
                                                            <span><?php echo htmlspecialchars($trailRole); ?></span>
                                                            <?php if ($trailOwnerTooltip !== ''): ?>
                                                                <span class="tm-owner-help tm-owner-help--inline" title="<?php echo htmlspecialchars($trailOwnerTooltip); ?>">?</span>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="tm-trail-datetime"><?php echo htmlspecialchars($trailDatetime); ?></div>
                                                    </div>
                                                    <div class="tm-trail-type-label tm-trail-type-label--<?php echo htmlspecialchars(strtolower($trailType)); ?>"><?php echo htmlspecialchars(st_trail_type_label_cad($trailType)); ?></div>
                                                    <div class="tm-trail-chevron">›</div>
                                                </div>
                                                <div class="tm-trail-card-body">
                                                    <?php
                                                        $showTicketDetails = false;
                                                        $ticketReason = trim((string) ($ticket['reason'] ?? ''));
                                                        if ($trailIndex === 0 || ($ticketReason !== '' && $trailMessage === $ticketReason)) {
                                                            $showTicketDetails = true;
                                                        }
                                                    ?>

                                                    <?php if ($showTicketDetails): ?>
                                                        <div class="tm-ticket-details">
                                                            <?php if (!empty($ticketSupplemental['wrongbiller'])):
                                                                $wb = $ticketSupplemental['wrongbiller'];
                                                            ?>
                                                                <?php if (!empty($wb['correct_biller_id'])): ?>
                                                                    <div class="tm-ticket-detail">
                                                                        <span class="tm-detail-icon"><i class="fa-solid fa-id-badge" aria-hidden="true"></i></span>
                                                                        <span class="tm-detail-label">Biller ID</span>
                                                                        <span class="tm-detail-value"><?php echo htmlspecialchars($wb['correct_biller_id']); ?></span>
                                                                    </div>
                                                                <?php endif; ?>
                                                                <?php if (!empty($wb['correct_biller_name'])): ?>
                                                                    <div class="tm-ticket-detail">
                                                                        <span class="tm-detail-icon"><i class="fa-solid fa-building" aria-hidden="true"></i></span>
                                                                        <span class="tm-detail-label">Biller</span>
                                                                        <span class="tm-detail-value"><?php echo htmlspecialchars($wb['correct_biller_name']); ?></span>
                                                                    </div>
                                                                <?php endif; ?>
                                                            <?php endif; ?>

                                                            <?php if (!empty($ticketSupplemental['overstated'])):
                                                                $oa = $ticketSupplemental['overstated'];
                                                            ?>
                                                                <?php if (isset($oa['wrong_amount'])): ?>
                                                                    <div class="tm-ticket-detail">
                                                                        <span class="tm-detail-icon"><i class="fa-solid fa-xmark" aria-hidden="true"></i></span>
                                                                        <span class="tm-detail-label">Wrong</span>
                                                                        <span class="tm-detail-value"><?php echo htmlspecialchars(number_format((float) $oa['wrong_amount'], 2)); ?></span>
                                                                    </div>
                                                                <?php endif; ?>
                                                                <?php if (isset($oa['correct_amount'])): ?>
                                                                    <div class="tm-ticket-detail">
                                                                        <span class="tm-detail-icon"><i class="fa-solid fa-check-circle" aria-hidden="true"></i></span>
                                                                        <span class="tm-detail-label">Correct</span>
                                                                        <span class="tm-detail-value"><?php echo htmlspecialchars(number_format((float) $oa['correct_amount'], 2)); ?></span>
                                                                    </div>
                                                                <?php endif; ?>
                                                            <?php endif; ?>

                                                            <?php if (!empty($ticketSupplemental['cancelled'])):
                                                                $ct = $ticketSupplemental['cancelled'];
                                                            ?>
                                                                <?php if (isset($ct['wrong_amount'])): ?>
                                                                    <div class="tm-ticket-detail">
                                                                        <span class="tm-detail-icon"><i class="fa-solid fa-xmark" aria-hidden="true"></i></span>
                                                                        <span class="tm-detail-label">Wrong</span>
                                                                        <span class="tm-detail-value"><?php echo htmlspecialchars(number_format((float) $ct['wrong_amount'], 2)); ?></span>
                                                                    </div>
                                                                <?php endif; ?>
                                                                <?php if (isset($ct['correct_amount'])): ?>
                                                                    <div class="tm-ticket-detail">
                                                                        <span class="tm-detail-icon"><i class="fa-solid fa-check-circle" aria-hidden="true"></i></span>
                                                                        <span class="tm-detail-label">Correct</span>
                                                                        <span class="tm-detail-value"><?php echo htmlspecialchars(number_format((float) $ct['correct_amount'], 2)); ?></span>
                                                                    </div>
                                                                <?php endif; ?>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php endif; ?>

                                                    <?php if ($trailMessage !== ''): ?>
                                                        <div class="tm-trail-message"><?php echo nl2br(htmlspecialchars($trailMessage)); ?></div>
                                                    <?php endif; ?>
                                                    <?php if (!empty($trailAttachments)): ?>
                                                        <div class="tm-attachments">
                                                            <?php foreach ($trailAttachments as $att): ?>
                                                                <a class="tm-attachment" href="controllers/attachments/download.php?id=<?php echo (int) ($att['id'] ?? 0); ?>">
                                                                    <span class="tm-attachment-icon"><i class="fa-solid fa-paperclip" aria-hidden="true"></i></span>
                                                                    <span class="tm-attachment-name"><?php echo htmlspecialchars((string) ($att['file_name'] ?? 'Attachment')); ?></span>
                                                                    <span class="tm-attachment-size"><?php echo htmlspecialchars((string) ($att['file_size'] ?? '')); ?></span>
                                                                </a>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if ($isOpen): ?>
                            <div class="tm-footer tm-footer--open">
                                <div class="tm-footer-inner" style="justify-content:flex-end;">
                                    <form method="post" action="controllers/cad/accept-ticket.php">
                                        <input type="hidden" name="ticket_id" value="<?php echo $ticketId; ?>">
                                        <input type="hidden" name="return_mode" value="active">
                                        <button class="tm-btn tm-btn--red" type="submit">Accept</button>
                                    </form>
                                </div>
                            </div>
                        <?php elseif ($isActive): ?>
                            <div class="tm-footer tm-footer--open">
                                <?php if ($isCadActionable): ?>
                                <div class="tm-footer-inner" style="display:block;">
                                    <form method="post" action="controllers/cad/submit-ticket.php" style="display:flex;gap:8px;align-items:stretch;margin-bottom:8px;width:100%;">
                                        <input type="hidden" name="action" value="reply">
                                        <input type="hidden" name="ticket_id" value="<?php echo $ticketId; ?>">
                                        <input type="hidden" name="return_mode" value="active">
                                        <div class="tm-textarea-container" style="flex:1;min-width:0;">
                                            <textarea name="message" class="tm-textarea" placeholder="Type your reply..." required></textarea>
                                        </div>
                                        <div style="display:flex;gap:8px;align-items:stretch;flex:0 0 auto;">
                                            <button type="submit" class="tm-btn tm-btn--red">Submit</button>
                                            <button type="button" class="tm-btn tm-btn--transfer" data-confirm-transfer-open="stTransferToVpoConfirm-<?php echo $ticketId; ?>">Transfer to VPO</button>
                                        </div>
                                    </form>

                                    <form id="stTransferToVpoForm-<?php echo $ticketId; ?>" method="post" action="controllers/cad/submit-ticket.php" style="display:none;">
                                        <input type="hidden" name="action" value="transfer_to_vpo">
                                        <input type="hidden" name="ticket_id" value="<?php echo $ticketId; ?>">
                                        <input type="hidden" name="return_mode" value="active">
                                        <input type="hidden" name="message" value="">
                                    </form>

                                    <div class="tm-submodal-overlay" id="stTransferToVpoConfirm-<?php echo $ticketId; ?>" style="display:none;" aria-hidden="true">
                                        <div class="tm-submodal" role="dialog" aria-modal="true" aria-label="Transfer to VPO confirmation">
                                            <div class="tm-submodal-title">Transfer Ticket to VPO?</div>
                                            <div class="tm-submodal-ticket-info">Ticket <?php echo htmlspecialchars((string) $ticket['ticket_number']); ?></div>
                                            <hr class="tm-submodal-divider">
                                            <div class="tm-submodal-footer">
                                                <button type="button" class="tm-btn tm-btn--outline" data-confirm-transfer-cancel="stTransferToVpoConfirm-<?php echo $ticketId; ?>">Cancel</button>
                                                <button type="button" class="tm-btn tm-btn--transfer" data-confirm-transfer-submit="stTransferToVpoConfirm-<?php echo $ticketId; ?>" data-transfer-form="stTransferToVpoForm-<?php echo $ticketId; ?>">Transfer to VPO</button>
                                            </div>
                                        </div>
                                    </div>

                                        <div style="margin-top:4px;display:flex;justify-content:flex-end;">
                                        <button type="button" class="tm-btn tm-btn--red tm-btn-close-ticket" data-close-picker-open="stClosePickerCad-<?php echo $ticketId; ?>"><i class="fa-solid fa-xmark" aria-hidden="true"></i> Close Ticket</button>
                                    </div>

                                    <div class="tm-submodal-overlay" id="stClosePickerCad-<?php echo $ticketId; ?>" style="display:none;" aria-hidden="true">
                                        <div class="tm-submodal" role="dialog" aria-modal="true" aria-label="Close ticket options">
                                            <div class="tm-submodal-title">Close Ticket</div>
                                            <div class="tm-submodal-ticket-info">Choose how to close Ticket <?php echo htmlspecialchars((string) $ticket['ticket_number']); ?></div>
                                            <hr class="tm-submodal-divider">
                                            <div style="display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end;">
                                                <form method="post" action="controllers/cad/close-ticket.php">
                                                    <input type="hidden" name="close_mode" value="auto">
                                                    <input type="hidden" name="ticket_id" value="<?php echo $ticketId; ?>">
                                                    <input type="hidden" name="return_mode" value="active">
                                                    <button class="tm-btn tm-btn--transfer" type="submit">Auto Close</button>
                                                </form>
                                                <form method="post" action="controllers/cad/close-ticket.php">
                                                    <input type="hidden" name="close_mode" value="immediate">
                                                    <input type="hidden" name="ticket_id" value="<?php echo $ticketId; ?>">
                                                    <input type="hidden" name="return_mode" value="active">
                                                    <button class="tm-btn tm-btn--danger" type="submit">Close Immediately</button>
                                                </form>
                                            </div>
                                            <div class="tm-submodal-footer" style="margin-top:10px;">
                                                <button type="button" class="tm-btn tm-btn--outline" data-close-picker-cancel="stClosePickerCad-<?php echo $ticketId; ?>">Cancel</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php else: ?>
                                <div class="tm-footer tm-footer--closed">Ticket is currently handled by VPO. You can still view the conversation timeline.</div>
                                <?php endif; ?>
                            </div>
                        <?php elseif ($isResolved): ?>
                            <div class="tm-footer tm-footer--closed">This ticket has been resolved!</div>
                        <?php else: ?>
                            <div class="tm-footer tm-footer--closed">This ticket is already closed!</div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php include '../../templates/footer.php'; ?>
    </div>

    <script src="assets/js/support-ticket-ui.js?v=<?php echo time(); ?>"></script>
</body>
</html>
