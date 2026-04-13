<?php
include_once __DIR__ . '/includes/bootstrap.php';
include_once __DIR__ . '/includes/ticket_queries.php';
include_once __DIR__ . '/includes/ticket-report.php';

st_require_login('../../login_form.php');

$hasReportPermission = function_exists('has_permission') && has_permission('Support Ticket Report');
if (!$hasReportPermission) {
    header('Location: ../home.php');
    exit;
}

$mode = strtolower(trim((string) ($_GET['mode'] ?? 'open')));
if (!in_array($mode, ['open', 'active', 'closed'], true)) {
    $mode = 'open';
}

$searchTicketNumber = strtoupper(trim((string) ($_GET['ticket_number'] ?? '')));

$allTickets = st_get_report_tickets($conn);
[$openTickets, $activeTickets, $closedTickets] = st_partition_report_tickets($allTickets);

$ticketTrailsByTicketId = [];
$ticketAttachmentsByTicketId = [];
$ticketSupplementalByTicketNumber = [];
$ownerIds = [];
$ticketByNumber = [];

foreach ($allTickets as $ticket) {
    $ticketId = (int) ($ticket['id'] ?? 0);
    if ($ticketId <= 0) {
        continue;
    }

    $ticketTrailsByTicketId[$ticketId] = st_get_ticket_trails($conn, $ticketId);
    $ticketAttachmentsByTicketId[$ticketId] = st_get_ticket_attachments_grouped_by_trail_report($conn, $ticketId);

    $ticketNumber = strtoupper(trim((string) ($ticket['ticket_number'] ?? '')));
    if ($ticketNumber !== '') {
        $ticketByNumber[$ticketNumber] = $ticket;
        $ticketSupplementalByTicketNumber[$ticketNumber] = [
            'wrongbiller' => st_get_ticket_wrongbiller_by_ticket_number($conn, $ticketNumber),
            'overstated' => st_get_ticket_overstatedamount_by_ticket_number($conn, $ticketNumber),
            'cancelled' => st_get_ticket_cancelledtransaction_by_ticket_number($conn, $ticketNumber),
        ];
    }

    if (isset($ticket['created_by']) && is_numeric($ticket['created_by'])) {
        $ownerIds[] = (int) $ticket['created_by'];
    }
    if (isset($ticket['vpo_owner']) && is_numeric($ticket['vpo_owner'])) {
        $ownerIds[] = (int) $ticket['vpo_owner'];
    }
    if (isset($ticket['cad_owner']) && is_numeric($ticket['cad_owner'])) {
        $ownerIds[] = (int) $ticket['cad_owner'];
    }
}

$ownerNamesById = st_get_user_names_by_id_numbers($conn, $ownerIds);
$stats = st_build_report_stats($allTickets, $openTickets, $activeTickets, $closedTickets);

$autoOpenModalId = '';
$searchNotFound = false;
if ($searchTicketNumber !== '') {
    if (isset($ticketByNumber[$searchTicketNumber])) {
        $matchedTicket = $ticketByNumber[$searchTicketNumber];
        $autoOpenModalId = 'stTicketTrailModalReport-' . (int) ($matchedTicket['id'] ?? 0);
    } else {
        $searchNotFound = true;
    }
}

$currentModeTickets = $openTickets;
if ($mode === 'active') {
    $currentModeTickets = $activeTickets;
} elseif ($mode === 'closed') {
    $currentModeTickets = $closedTickets;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Support Ticket - Report</title>
    <link rel="icon" href="../../images/MLW%20logo.png" type="image/png">
    <link rel="stylesheet" href="../../assets/css/templates/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="assets/css/support-ticket.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="assets/css/ticket-modal.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="assets/css/image-preview.css?v=<?php echo time(); ?>">
    <script src="https://kit.fontawesome.com/30b908cc5a.js" crossorigin="anonymous"></script>
    <style>
        .st-report-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 10px;
            margin-bottom: 14px;
        }

        .st-report-stat {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 10px 12px;
        }

        .st-report-stat-label {
            font-size: 11px;
            color: #64748b;
            font-weight: 700;
            text-transform: uppercase;
        }

        .st-report-stat-value {
            margin-top: 4px;
            font-size: 22px;
            font-weight: 800;
            color: #111827;
            line-height: 1.1;
        }

        .st-report-stat-help {
            margin-top: 4px;
            font-size: 11px;
            color: #475569;
        }

        .st-search-wrap {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
            margin-bottom: 12px;
        }

        .st-search-wrap input[type="text"] {
            width: 280px;
            max-width: 100%;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            padding: 8px 10px;
            font-size: 13px;
        }

        .st-search-wrap button,
        .st-search-wrap a {
            border-radius: 8px;
            padding: 8px 12px;
            font-size: 12px;
            font-weight: 700;
            text-decoration: none;
        }

        .st-search-wrap button {
            border: 1px solid #dc3545;
            background: #dc3545;
            color: #fff;
        }

        .st-search-wrap a {
            border: 1px solid #d1d5db;
            color: #374151;
            background: #fff;
        }

        .st-report-note {
            margin-top: 10px;
            border: 1px solid #fde68a;
            background: #fffbeb;
            color: #92400e;
            border-radius: 8px;
            padding: 8px 10px;
            font-size: 12px;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="main-container">
        <?php include '../../templates/header_ui.php'; ?>
        <?php include '../../templates/sidebar.php'; ?>

        <?php if (function_exists('bp_section_header_html')): ?>
            <?php bp_section_header_html('fa-solid fa-chart-line', 'Support Ticket - Report', 'Management Monitoring and Observation'); ?>
        <?php else: ?>
            <div class="container-fluid mt-3"><h3>Support Ticket - Report</h3></div>
        <?php endif; ?>

        <div class="container-fluid st-wrapper">
            <div class="st-report-grid">
                <div class="st-report-stat">
                    <div class="st-report-stat-label">Open Count</div>
                    <div class="st-report-stat-value"><?php echo (int) $stats['open_count']; ?></div>
                    <div class="st-report-stat-help">Tickets waiting for processing</div>
                </div>
                <div class="st-report-stat">
                    <div class="st-report-stat-label">Active Count</div>
                    <div class="st-report-stat-value"><?php echo (int) $stats['active_count']; ?></div>
                    <div class="st-report-stat-help">Tickets currently being handled</div>
                </div>
                <div class="st-report-stat">
                    <div class="st-report-stat-label">Closed Count</div>
                    <div class="st-report-stat-value"><?php echo (int) $stats['closed_count']; ?></div>
                    <div class="st-report-stat-help">Resolved or closed tickets</div>
                </div>
                <div class="st-report-stat">
                    <div class="st-report-stat-label">Close Rate</div>
                    <div class="st-report-stat-value"><?php echo number_format((float) $stats['close_rate'], 1); ?>%</div>
                    <div class="st-report-stat-help">Closed over total tickets</div>
                </div>
                <div class="st-report-stat">
                    <div class="st-report-stat-label">Aging Over 48h</div>
                    <div class="st-report-stat-value"><?php echo (int) $stats['aging_over_48h']; ?></div>
                    <div class="st-report-stat-help">Open or active tickets older than 48h</div>
                </div>
                <div class="st-report-stat">
                    <div class="st-report-stat-label">Average Amount</div>
                    <div class="st-report-stat-value">PHP <?php echo number_format((float) $stats['avg_amount'], 2); ?></div>
                    <div class="st-report-stat-help">Average ticket amount</div>
                </div>
                <div class="st-report-stat">
                    <div class="st-report-stat-label">Most Common Type</div>
                    <div class="st-report-stat-value" style="font-size:16px;"><?php echo htmlspecialchars((string) $stats['top_type']); ?></div>
                    <div class="st-report-stat-help"><?php echo (int) $stats['top_type_count']; ?> tickets</div>
                </div>
                <div class="st-report-stat">
                    <div class="st-report-stat-label">Current Handler Mix</div>
                    <div class="st-report-stat-help" style="margin-top:8px;line-height:1.5;">
                        BRANCH: <?php echo (int) ($stats['handler_counts']['BRANCH'] ?? 0); ?><br>
                        VPO: <?php echo (int) ($stats['handler_counts']['VPO'] ?? 0); ?><br>
                        CAD: <?php echo (int) ($stats['handler_counts']['CAD'] ?? 0); ?><br>
                        OTHER: <?php echo (int) ($stats['handler_counts']['OTHER'] ?? 0); ?>
                    </div>
                </div>
            </div>

            <form method="get" class="st-search-wrap">
                <input type="hidden" name="mode" value="<?php echo htmlspecialchars($mode); ?>">
                <input type="text" name="ticket_number" value="<?php echo htmlspecialchars($searchTicketNumber); ?>" placeholder="Search Ticket Number">
                <button type="submit"><i class="fa-solid fa-magnifying-glass"></i> Search Ticket</button>
                <a href="ticket-report.php?mode=<?php echo urlencode($mode); ?>">Clear Search</a>
            </form>

            <div class="mode-cards" data-st-mode-group data-st-param="mode">
                <label class="mode-card <?php echo $mode === 'open' ? 'selected' : ''; ?>" data-mode="open">
                    <input type="radio" name="reportMode" value="open" <?php echo $mode === 'open' ? 'checked' : ''; ?>>
                    <div class="mode-icon"><i class="fa-solid fa-inbox"></i></div>
                    <div class="mode-text"><p class="mode-label">OPEN</p><small>Unresolved queue</small></div>
                    <span class="st-mode-count-badge"><?php echo count($openTickets); ?></span>
                </label>

                <label class="mode-card <?php echo $mode === 'active' ? 'selected' : ''; ?>" data-mode="active">
                    <input type="radio" name="reportMode" value="active" <?php echo $mode === 'active' ? 'checked' : ''; ?>>
                    <div class="mode-icon"><i class="fa-solid fa-bolt"></i></div>
                    <div class="mode-text"><p class="mode-label">ACTIVE</p><small>In-progress tickets</small></div>
                    <span class="st-mode-count-badge"><?php echo count($activeTickets); ?></span>
                </label>

                <label class="mode-card <?php echo $mode === 'closed' ? 'selected' : ''; ?>" data-mode="closed">
                    <input type="radio" name="reportMode" value="closed" <?php echo $mode === 'closed' ? 'checked' : ''; ?>>
                    <div class="mode-icon"><i class="fa-solid fa-box-archive"></i></div>
                    <div class="mode-text"><p class="mode-label">CLOSED</p><small>Resolved and closed</small></div>
                    <span class="st-mode-count-badge"><?php echo count($closedTickets); ?></span>
                </label>
            </div>

            <div class="mode-panel <?php echo $mode === 'open' ? '' : 'hidden'; ?>" data-st-panel="open">
                <?php if (empty($openTickets)): ?>
                    <div class="st-empty">No open tickets available.</div>
                <?php else: ?>
                    <div class="st-ticket-table" role="table" aria-label="Open support ticket report table">
                        <div class="st-ticket-row st-ticket-row-head" role="row">
                            <span class="st-ticket-col st-col-number">Ticket #</span>
                            <span class="st-ticket-col st-col-date">Created</span>
                            <span class="st-ticket-col st-col-type">Type</span>
                            <span class="st-ticket-col st-col-partner">Partner</span>
                            <span class="st-ticket-col st-col-status">Status</span>
                        </div>
                        <?php foreach ($openTickets as $ticket): ?>
                            <button type="button" class="st-ticket-row" role="row" data-ticket-modal="stTicketTrailModalReport-<?php echo (int) $ticket['id']; ?>">
                                <span class="st-ticket-col st-col-number"><?php echo htmlspecialchars((string) $ticket['ticket_number']); ?></span>
                                <span class="st-ticket-col st-col-date"><?php echo htmlspecialchars((string) ($ticket['created_at'] ?? '')); ?></span>
                                <span class="st-ticket-col st-col-type"><?php echo htmlspecialchars((string) ($ticket['ticket_type_label'] ?: $ticket['type_of_request'])); ?></span>
                                <span class="st-ticket-col st-col-partner"><?php echo htmlspecialchars(st_partner_name_report($ticket)); ?></span>
                                <span class="st-ticket-col st-col-status"><span class="<?php echo htmlspecialchars(st_status_class_report($ticket['status'])); ?>"><?php echo htmlspecialchars((string) $ticket['status']); ?></span></span>
                            </button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="mode-panel <?php echo $mode === 'active' ? '' : 'hidden'; ?>" data-st-panel="active">
                <?php if (empty($activeTickets)): ?>
                    <div class="st-empty">No active tickets available.</div>
                <?php else: ?>
                    <div class="st-ticket-table" role="table" aria-label="Active support ticket report table">
                        <div class="st-ticket-row st-ticket-row-head" role="row">
                            <span class="st-ticket-col st-col-number">Ticket #</span>
                            <span class="st-ticket-col st-col-date">Created</span>
                            <span class="st-ticket-col st-col-type">Type</span>
                            <span class="st-ticket-col st-col-partner">Partner</span>
                            <span class="st-ticket-col st-col-status">Status</span>
                        </div>
                        <?php foreach ($activeTickets as $ticket): ?>
                            <button type="button" class="st-ticket-row" role="row" data-ticket-modal="stTicketTrailModalReport-<?php echo (int) $ticket['id']; ?>">
                                <span class="st-ticket-col st-col-number"><?php echo htmlspecialchars((string) $ticket['ticket_number']); ?></span>
                                <span class="st-ticket-col st-col-date"><?php echo htmlspecialchars((string) ($ticket['created_at'] ?? '')); ?></span>
                                <span class="st-ticket-col st-col-type"><?php echo htmlspecialchars((string) ($ticket['ticket_type_label'] ?: $ticket['type_of_request'])); ?></span>
                                <span class="st-ticket-col st-col-partner"><?php echo htmlspecialchars(st_partner_name_report($ticket)); ?></span>
                                <span class="st-ticket-col st-col-status"><span class="<?php echo htmlspecialchars(st_status_class_report($ticket['status'])); ?>"><?php echo htmlspecialchars((string) $ticket['status']); ?></span></span>
                            </button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="mode-panel <?php echo $mode === 'closed' ? '' : 'hidden'; ?>" data-st-panel="closed">
                <?php if (empty($closedTickets)): ?>
                    <div class="st-empty">No closed tickets available.</div>
                <?php else: ?>
                    <div class="st-ticket-table" role="table" aria-label="Closed support ticket report table">
                        <div class="st-ticket-row st-ticket-row-head" role="row">
                            <span class="st-ticket-col st-col-number">Ticket #</span>
                            <span class="st-ticket-col st-col-date">Created</span>
                            <span class="st-ticket-col st-col-type">Type</span>
                            <span class="st-ticket-col st-col-partner">Partner</span>
                            <span class="st-ticket-col st-col-status">Status</span>
                        </div>
                        <?php foreach ($closedTickets as $ticket): ?>
                            <button type="button" class="st-ticket-row" role="row" data-ticket-modal="stTicketTrailModalReport-<?php echo (int) $ticket['id']; ?>">
                                <span class="st-ticket-col st-col-number"><?php echo htmlspecialchars((string) $ticket['ticket_number']); ?></span>
                                <span class="st-ticket-col st-col-date"><?php echo htmlspecialchars((string) (($ticket['closed_at'] ?? '') !== '' ? $ticket['closed_at'] : ($ticket['created_at'] ?? ''))); ?></span>
                                <span class="st-ticket-col st-col-type"><?php echo htmlspecialchars((string) ($ticket['ticket_type_label'] ?: $ticket['type_of_request'])); ?></span>
                                <span class="st-ticket-col st-col-partner"><?php echo htmlspecialchars(st_partner_name_report($ticket)); ?></span>
                                <span class="st-ticket-col st-col-status"><span class="<?php echo htmlspecialchars(st_status_class_report($ticket['status'])); ?>"><?php echo htmlspecialchars((string) $ticket['status']); ?></span></span>
                            </button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="st-report-note">Observation mode only: You can view and monitor every ticket, but no actions can be performed here.</div>

            <?php foreach ($allTickets as $ticket): ?>
                <?php
                    $ticketId = (int) ($ticket['id'] ?? 0);
                    $trails = $ticketTrailsByTicketId[$ticketId] ?? [];
                    $attachmentsByTrail = $ticketAttachmentsByTicketId[$ticketId] ?? [];
                    $ticketTypeText = (string) ($ticket['ticket_type_label'] ?: $ticket['type_of_request']);
                    $statusLower = strtolower((string) ($ticket['status'] ?? ''));
                    $ticketNumber = strtoupper((string) ($ticket['ticket_number'] ?? ''));
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
                <div class="tm-overlay" id="stTicketTrailModalReport-<?php echo $ticketId; ?>" aria-hidden="true" role="dialog" aria-modal="true">
                    <div class="tm-modal">
                        <div class="tm-header">
                            <div class="tm-header-top">
                                <div class="tm-header-left">
                                    <div class="tm-ticket-number"><span class="tm-ticket-icon"><i class="fa-solid fa-ticket" aria-hidden="true"></i></span>Ticket #: <?php echo htmlspecialchars((string) $ticket['ticket_number']); ?></div>
                                    <div class="tm-ticket-meta-grid">
                                        <div class="tm-meta-item"><div class="tm-meta-label">Reference No.</div><div class="tm-meta-value"><?php echo htmlspecialchars($hdrReference); ?></div></div>
                                        <div class="tm-meta-item"><div class="tm-meta-label">Transaction D/T</div><div class="tm-meta-value"><?php echo htmlspecialchars($hdrTransfer); ?></div></div>
                                        <div class="tm-meta-item"><div class="tm-meta-label">Account No.</div><div class="tm-meta-value"><?php echo htmlspecialchars($hdrAccount); ?></div></div>
                                        <div class="tm-meta-item"><div class="tm-meta-label">Payment Branch</div><div class="tm-meta-value"><?php echo htmlspecialchars($hdrPaymentBranch); ?></div></div>
                                        <div class="tm-meta-item"><div class="tm-meta-label">Partner</div><div class="tm-meta-value"><?php echo htmlspecialchars(st_partner_name_report($ticket)); ?></div></div>
                                        <div class="tm-meta-item"><div class="tm-meta-label">Created By</div><div class="tm-meta-value"><?php echo htmlspecialchars($createdByName); ?></div></div>
                                        <div class="tm-meta-item"><div class="tm-meta-label">Type</div><div class="tm-meta-value"><?php echo htmlspecialchars($ticketTypeText); ?></div></div>
                                        <div class="tm-meta-item"><div class="tm-meta-label">Source</div><div class="tm-meta-value"><?php echo htmlspecialchars((string) ($ticket['source'] ?? 'N/A')); ?></div></div>
                                        <div class="tm-meta-item"><div class="tm-meta-label">Amount</div><div class="tm-meta-value"><?php echo htmlspecialchars($hdrAmount); ?></div></div>
                                    </div>
                                </div>
                                <div class="tm-header-right">
                                    <div class="tm-header-actions">
                                        <div class="tm-header-actions-top">
                                            <div class="tm-status tm-status--<?php echo htmlspecialchars($statusLower); ?>"><?php echo htmlspecialchars((string) $ticket['status']); ?></div>
                                            <button type="button" class="tm-close-btn" data-st-close-modal="stTicketTrailModalReport-<?php echo $ticketId; ?>" aria-label="Close">&times;</button>
                                        </div>
                                    </div>
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
                                            elseif ($trailRole === 'VPO') $avatarClass = 'tm-trail-avatar--vpo';
                                            elseif ($trailRole === 'CAD') $avatarClass = 'tm-trail-avatar--cad';

                                            $trailOwnerTooltip = '';
                                            if ($trailRole === 'BRANCH') {
                                                $trailOwnerTooltip = $createdByName;
                                            } elseif ($trailRole === 'VPO') {
                                                $trailOwnerTooltip = $vpoOwnerName;
                                            } elseif ($trailRole === 'CAD') {
                                                $trailOwnerTooltip = $cadOwnerName;
                                            }
                                        ?>
                                        <div class="tm-trail-item">
                                            <div class="tm-trail-dot-wrap">
                                                <div class="tm-trail-avatar <?php echo $avatarClass; ?>"><?php echo htmlspecialchars(st_trail_role_icon_report($trailRole)); ?></div>
                                            </div>
                                            <div class="tm-trail-card <?php echo $trailRole === 'SYSTEM' ? 'tm-trail-card--system' : ''; ?> <?php echo $trailIndex === $lastTrailIndex ? 'tm-expanded' : ''; ?>" <?php echo $trailIndex === $lastTrailIndex ? 'data-tm-latest="1"' : ''; ?>>
                                                <div class="tm-trail-card-header">
                                                    <div class="tm-trail-avatar <?php echo $avatarClass; ?>"><?php echo htmlspecialchars(st_trail_role_icon_report($trailRole)); ?></div>
                                                    <div class="tm-trail-meta">
                                                        <div class="tm-trail-sender">
                                                            <span><?php echo htmlspecialchars($trailRole); ?></span>
                                                            <?php if ($trailOwnerTooltip !== ''): ?>
                                                                <span class="tm-owner-help tm-owner-help--inline" title="<?php echo htmlspecialchars($trailOwnerTooltip); ?>">?</span>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="tm-trail-datetime"><?php echo htmlspecialchars($trailDatetime); ?></div>
                                                    </div>
                                                    <div class="tm-trail-type-label tm-trail-type-label--<?php echo htmlspecialchars(strtolower($trailType)); ?>"><?php echo htmlspecialchars(st_trail_type_label_report($trailType)); ?></div>
                                                    <div class="tm-trail-chevron">&#8250;</div>
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
                                                            <?php
                                                                $wb = !empty($ticketSupplemental['wrongbiller']) ? $ticketSupplemental['wrongbiller'] : null;
                                                                $oa = !empty($ticketSupplemental['overstated']) ? $ticketSupplemental['overstated'] : null;
                                                                $ct = !empty($ticketSupplemental['cancelled']) ? $ticketSupplemental['cancelled'] : null;
                                                                $wrongBillerId = trim((string) ($ticket['wrong_biller_id'] ?? ''));
                                                                $wrongBillerName = trim((string) ($ticket['biller_name'] ?? ''));

                                                                $typeOfRequest = strtoupper(trim((string) ($ticket['type_of_request'] ?? '')));
                                                                $isWrongBillerType = ($typeOfRequest === 'WRONG BILLER');
                                                                $isCancelledType = ($typeOfRequest === 'CANCELLED TRANSACTION');
                                                                $isOverstatedType = ($typeOfRequest === 'OVERSTATED AMOUNT');
                                                            ?>

                                                            <?php if ($isWrongBillerType): ?>
                                                                <div class="tm-ticket-billers">
                                                                    <div class="tm-ticket-details-col tm-ticket-details-col--left">
                                                                        <?php if (!empty($wb) && !empty($wb['correct_biller_id'])): ?>
                                                                            <div class="tm-ticket-detail"><span class="tm-detail-label">Correct Biller ID</span><span class="tm-detail-value"><?php echo htmlspecialchars((string) $wb['correct_biller_id']); ?></span></div>
                                                                        <?php endif; ?>
                                                                        <?php if (!empty($wb) && !empty($wb['correct_biller_name'])): ?>
                                                                            <div class="tm-ticket-detail"><span class="tm-detail-label">Correct Biller Name</span><span class="tm-detail-value"><?php echo htmlspecialchars((string) $wb['correct_biller_name']); ?></span></div>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                    <div class="tm-ticket-details-col tm-ticket-details-col--right">
                                                                        <?php if ($wrongBillerId !== ''): ?>
                                                                            <div class="tm-ticket-detail"><span class="tm-detail-label">Wrong Biller ID</span><span class="tm-detail-value"><?php echo htmlspecialchars($wrongBillerId); ?></span></div>
                                                                        <?php endif; ?>
                                                                        <?php if ($wrongBillerName !== ''): ?>
                                                                            <div class="tm-ticket-detail"><span class="tm-detail-label">Wrong Biller Name</span><span class="tm-detail-value"><?php echo htmlspecialchars($wrongBillerName); ?></span></div>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                </div>
                                                            <?php elseif ($isCancelledType || $isOverstatedType): ?>
                                                                <?php
                                                                    $amountSource = $isOverstatedType ? $oa : $ct;
                                                                    $amountCorrect = isset($amountSource['correct_amount']) ? $amountSource['correct_amount'] : null;
                                                                    $amountWrong = isset($amountSource['wrong_amount']) ? $amountSource['wrong_amount'] : null;
                                                                    $amountDifference = ($isOverstatedType && isset($amountSource['difference'])) ? $amountSource['difference'] : null;
                                                                ?>
                                                                <div class="tm-ticket-split">
                                                                    <div class="tm-ticket-details-col tm-ticket-details-col--left">
                                                                        <?php if ($amountCorrect !== null): ?>
                                                                            <div class="tm-ticket-detail"><span class="tm-detail-label">Correct Amount</span><span class="tm-detail-value"><?php echo htmlspecialchars(number_format((float) $amountCorrect, 2)); ?></span></div>
                                                                        <?php endif; ?>
                                                                        <?php if ($amountWrong !== null): ?>
                                                                            <div class="tm-ticket-detail"><span class="tm-detail-label">Wrong Amount</span><span class="tm-detail-value"><?php echo htmlspecialchars(number_format((float) $amountWrong, 2)); ?></span></div>
                                                                        <?php endif; ?>
                                                                        <?php if ($amountDifference !== null): ?>
                                                                            <div class="tm-ticket-detail"><span class="tm-detail-label">Difference</span><span class="tm-detail-value"><?php echo htmlspecialchars(number_format((float) $amountDifference, 2)); ?></span></div>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                    <div class="tm-ticket-details-col tm-ticket-details-col--right">
                                                                        <?php if ($wrongBillerId !== ''): ?>
                                                                            <div class="tm-ticket-detail"><span class="tm-detail-label">Biller ID</span><span class="tm-detail-value"><?php echo htmlspecialchars($wrongBillerId); ?></span></div>
                                                                        <?php endif; ?>
                                                                        <?php if ($wrongBillerName !== ''): ?>
                                                                            <div class="tm-ticket-detail"><span class="tm-detail-label">Biller Name</span><span class="tm-detail-value"><?php echo htmlspecialchars($wrongBillerName); ?></span></div>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                </div>
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

                        <div class="tm-footer tm-footer--closed">You cannot interact with this Ticket!</div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="tm-submodal-overlay" id="stReportNotFoundModal" style="display:<?php echo $searchNotFound ? 'flex' : 'none'; ?>;" aria-hidden="<?php echo $searchNotFound ? 'false' : 'true'; ?>">
            <div class="tm-submodal" role="dialog" aria-modal="true" aria-label="Ticket not found">
                <div class="tm-submodal-title">Ticket Number not found</div>
                <div class="tm-submodal-ticket-info">No ticket matched: <?php echo htmlspecialchars($searchTicketNumber); ?></div>
                <hr class="tm-submodal-divider">
                <div class="tm-submodal-footer">
                    <button type="button" class="tm-btn tm-btn--outline" id="stReportNotFoundClose">Close</button>
                </div>
            </div>
        </div>

        <?php include '../../templates/footer.php'; ?>
    </div>

    <script src="assets/js/support-ticket-ui.js?v=<?php echo time(); ?>"></script>
    <script>
        (function () {
            var autoOpenModalId = <?php echo json_encode($autoOpenModalId); ?>;
            if (autoOpenModalId) {
                var target = document.getElementById(autoOpenModalId);
                if (target) {
                    target.classList.add('open');
                    var body = target.querySelector('.tm-body');
                    if (body) {
                        body.scrollTop = body.scrollHeight;
                    }
                }
            }

            var notFoundOverlay = document.getElementById('stReportNotFoundModal');
            var notFoundClose = document.getElementById('stReportNotFoundClose');
            if (notFoundOverlay && notFoundClose) {
                notFoundClose.addEventListener('click', function () {
                    notFoundOverlay.style.display = 'none';
                    notFoundOverlay.setAttribute('aria-hidden', 'true');
                });
                notFoundOverlay.addEventListener('click', function (e) {
                    if (e.target === notFoundOverlay) {
                        notFoundOverlay.style.display = 'none';
                        notFoundOverlay.setAttribute('aria-hidden', 'true');
                    }
                });
            }
        })();
    </script>
</body>
</html>
