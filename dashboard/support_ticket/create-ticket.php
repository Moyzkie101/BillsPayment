<?php
include_once __DIR__ . '/includes/bootstrap.php';
include_once __DIR__ . '/includes/ticket_queries.php';

st_require_login('../../login_form.php');
st_require_permission_page(['Support Ticket Create'], '../home.php');

$userId = st_user_id_or_null();
$ticketTypes = st_get_ticket_types($conn);
$subbillers = st_get_subbillers($conn, 2500);
$flash = st_flash_get('create_ticket');
$mode = strtolower(trim((string) ($_GET['mode'] ?? 'open')));
if ($mode !== 'open' && $mode !== 'closed') {
    $mode = 'open';
}

$branchTickets = [];
$openTickets = [];
$closedTickets = [];
if ($userId !== null) {
    $branchTickets = st_get_branch_tickets($conn, $userId);
}

foreach ($branchTickets as $ticket) {
    if (strtolower((string) ($ticket['status'] ?? '')) === 'closed') {
        $closedTickets[] = $ticket;
    } else {
        $openTickets[] = $ticket;
    }
}

function st_status_class_branch($status)
{
    return 'st-status st-status-' . strtolower((string) $status);
}

function st_card_partner_name($ticket)
{
    $partner = trim((string) ($ticket['partner_name'] ?? ''));
    if ($partner !== '') {
        return $partner;
    }
    $ext = trim((string) ($ticket['partner_ext_id'] ?? ''));
    return $ext !== '' ? $ext : 'N/A';
}

function st_trail_type_label($type)
{
    $t = strtolower(trim((string) $type));
    if ($t === 'accept') return 'Accepted';
    if ($t === 'transfer') return 'Transferred';
    if ($t === 'resolve') return 'Resolved';
    if ($t === 'close') return 'Closed';
    if ($t === 'auto_close') return 'Auto Closed';
    return 'Message';
}

function st_trail_role_icon($role)
{
    $r = strtoupper(trim((string) $role));
    if ($r === 'BRANCH') return '🟢';
    if ($r === 'VPO') return '🔵';
    if ($r === 'CAD') return '🔴';
    return '⚙️';
}

function st_get_ticket_attachments_grouped_by_trail($conn, $ticketId)
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
foreach ($branchTickets as $ticket) {
    $ticketId = (int) ($ticket['id'] ?? 0);
    if ($ticketId <= 0) {
        continue;
    }
    $ticketTrailsByTicketId[$ticketId] = st_get_ticket_trails($conn, $ticketId);
    $ticketAttachmentsByTicketId[$ticketId] = st_get_ticket_attachments_grouped_by_trail($conn, $ticketId);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Support Ticket - Branch</title>
    <link rel="icon" href="../../images/MLW%20logo.png" type="image/png">
    <link rel="stylesheet" href="../../assets/css/templates/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="assets/css/support-ticket.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../trl/trl-entry/trl-entry.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../trl/trl-entry/components/trl-entry-auto.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../trl/trl-entry/components/trl-entry-manual.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../trl/trl-report/components/trl-report-subbillers.css?v=<?php echo time(); ?>">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <script src="https://kit.fontawesome.com/30b908cc5a.js" crossorigin="anonymous"></script>
</head>
<body>
    <div class="main-container">
        <?php include '../../templates/header_ui.php'; ?>
        <?php include '../../templates/sidebar.php'; ?>

        <?php if (function_exists('bp_section_header_html')): ?>
            <?php bp_section_header_html('fa-solid fa-ticket', 'Support Ticket', 'Branch - Open / Closed'); ?>
        <?php else: ?>
            <div class="container-fluid mt-3"><h3>Support Ticket - Branch</h3></div>
        <?php endif; ?>

        <div class="container-fluid st-wrapper">
            <?php if ($flash): ?>
                <div class="alert alert-<?php echo htmlspecialchars($flash['type']); ?>" role="alert">
                    <?php echo htmlspecialchars($flash['message']); ?>
                </div>
            <?php endif; ?>

            <div class="st-toolbar">
                <div class="st-small">Select mode to view your tickets.</div>
                <button type="button" class="btn btn-danger" id="stOpenCreateModal">
                    <i class="fa-solid fa-plus"></i> Create Ticket
                </button>
            </div>

            <div class="mode-cards" data-st-mode-group data-st-param="mode">
                <label class="mode-card <?php echo $mode === 'open' ? 'selected' : ''; ?>" data-mode="open">
                    <input type="radio" name="branchMode" value="open" <?php echo $mode === 'open' ? 'checked' : ''; ?>>
                    <div class="mode-icon"><i class="fa-solid fa-inbox"></i></div>
                    <div class="mode-text">
                        <p class="mode-label">OPEN</p>
                        <small>Active and resolving tickets</small>
                    </div>
                </label>

                <label class="mode-card <?php echo $mode === 'closed' ? 'selected' : ''; ?>" data-mode="closed">
                    <input type="radio" name="branchMode" value="closed" <?php echo $mode === 'closed' ? 'checked' : ''; ?>>
                    <div class="mode-icon"><i class="fa-solid fa-box-archive"></i></div>
                    <div class="mode-text">
                        <p class="mode-label">CLOSED</p>
                        <small>Completed tickets</small>
                    </div>
                </label>
            </div>

            <div class="mode-panel <?php echo $mode === 'open' ? '' : 'hidden'; ?>" data-st-panel="open">
                <?php if (empty($openTickets)): ?>
                    <div class="st-empty">No open tickets.</div>
                <?php else: ?>
                    <div class="st-ticket-table" role="table" aria-label="Open tickets">
                        <div class="st-ticket-row st-ticket-row-head" role="row">
                            <span class="st-ticket-col st-col-number">Ticket #</span>
                            <span class="st-ticket-col st-col-date">Created</span>
                            <span class="st-ticket-col st-col-type">Type</span>
                            <span class="st-ticket-col st-col-partner">Partner</span>
                            <span class="st-ticket-col st-col-status">Status</span>
                        </div>
                        <?php foreach ($openTickets as $ticket): ?>
                            <button type="button" class="st-ticket-row" role="row" data-ticket-modal="stTicketTrailModal-<?php echo (int) $ticket['id']; ?>">
                                <span class="st-ticket-col st-col-number"><?php echo htmlspecialchars((string) $ticket['ticket_number']); ?></span>
                                <span class="st-ticket-col st-col-date"><?php echo htmlspecialchars((string) $ticket['created_at']); ?></span>
                                <span class="st-ticket-col st-col-type"><?php echo htmlspecialchars((string) ($ticket['ticket_type_label'] ?: $ticket['type_of_request'])); ?></span>
                                <span class="st-ticket-col st-col-partner"><?php echo htmlspecialchars(st_card_partner_name($ticket)); ?></span>
                                <span class="st-ticket-col st-col-status"><span class="<?php echo htmlspecialchars(st_status_class_branch($ticket['status'])); ?>"><?php echo htmlspecialchars((string) $ticket['status']); ?></span></span>
                            </button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="mode-panel <?php echo $mode === 'closed' ? '' : 'hidden'; ?>" data-st-panel="closed">
                <?php if (empty($closedTickets)): ?>
                    <div class="st-empty">No closed tickets.</div>
                <?php else: ?>
                    <div class="st-ticket-table" role="table" aria-label="Closed tickets">
                        <div class="st-ticket-row st-ticket-row-head" role="row">
                            <span class="st-ticket-col st-col-number">Ticket #</span>
                            <span class="st-ticket-col st-col-date">Created</span>
                            <span class="st-ticket-col st-col-type">Type</span>
                            <span class="st-ticket-col st-col-partner">Partner</span>
                            <span class="st-ticket-col st-col-status">Status</span>
                        </div>
                        <?php foreach ($closedTickets as $ticket): ?>
                            <button type="button" class="st-ticket-row" role="row" data-ticket-modal="stTicketTrailModal-<?php echo (int) $ticket['id']; ?>">
                                <span class="st-ticket-col st-col-number"><?php echo htmlspecialchars((string) $ticket['ticket_number']); ?></span>
                                <span class="st-ticket-col st-col-date"><?php echo htmlspecialchars((string) $ticket['created_at']); ?></span>
                                <span class="st-ticket-col st-col-type"><?php echo htmlspecialchars((string) ($ticket['ticket_type_label'] ?: $ticket['type_of_request'])); ?></span>
                                <span class="st-ticket-col st-col-partner"><?php echo htmlspecialchars(st_card_partner_name($ticket)); ?></span>
                                <span class="st-ticket-col st-col-status"><span class="<?php echo htmlspecialchars(st_status_class_branch($ticket['status'])); ?>"><?php echo htmlspecialchars((string) $ticket['status']); ?></span></span>
                            </button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <?php foreach ($branchTickets as $ticket): ?>
                <?php
                    $ticketId = (int) ($ticket['id'] ?? 0);
                    $trails = $ticketTrailsByTicketId[$ticketId] ?? [];
                    $attachmentsByTrail = $ticketAttachmentsByTicketId[$ticketId] ?? [];
                    $ticketTypeText = (string) ($ticket['ticket_type_label'] ?: $ticket['type_of_request']);
                    $isClosed = strtolower((string) ($ticket['status'] ?? '')) === 'closed';
                ?>
                <div class="st-modal-backdrop st-ticket-trail-backdrop" id="stTicketTrailModal-<?php echo $ticketId; ?>" aria-hidden="true">
                    <div class="st-modal st-ticket-trail-modal">
                        <div class="st-modal-header">
                            <div>
                                <h5 class="mb-0">Ticket <?php echo htmlspecialchars((string) $ticket['ticket_number']); ?></h5>
                                <div class="st-ticket-meta-line">
                                    <span>Reference: <?php echo htmlspecialchars((string) ($ticket['reference_number'] ?? 'N/A')); ?></span>
                                    <span>Source: <?php echo htmlspecialchars((string) ($ticket['source'] ?? 'N/A')); ?></span>
                                    <span>Partner: <?php echo htmlspecialchars(st_card_partner_name($ticket)); ?></span>
                                    <span>Type: <?php echo htmlspecialchars($ticketTypeText); ?></span>
                                </div>
                            </div>
                            <button type="button" class="st-modal-close" data-st-close-modal="stTicketTrailModal-<?php echo $ticketId; ?>" aria-label="Close">&times;</button>
                        </div>

                        <div class="st-modal-body">
                            <div class="st-trail-timeline">
                                <?php if (empty($trails)): ?>
                                    <div class="st-empty">No trail entries yet.</div>
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
                                        ?>
                                        <details class="st-trail-card <?php echo $trailRole === 'SYSTEM' ? 'st-trail-card-system' : ''; ?>" <?php echo $trailIndex === $lastTrailIndex ? 'open' : ''; ?>>
                                            <summary>
                                                <div class="st-trail-summary-left">
                                                    <span class="st-trail-role-icon"><?php echo htmlspecialchars(st_trail_role_icon($trailRole)); ?></span>
                                                    <span class="st-trail-role"><?php echo htmlspecialchars($trailRole); ?></span>
                                                    <span class="st-trail-type"><?php echo htmlspecialchars(st_trail_type_label($trailType)); ?></span>
                                                </div>
                                                <div class="st-trail-datetime"><?php echo htmlspecialchars($trailDatetime); ?></div>
                                            </summary>
                                            <div class="st-trail-content">
                                                <?php if ($trailMessage !== ''): ?>
                                                    <div class="st-trail-message"><?php echo nl2br(htmlspecialchars($trailMessage)); ?></div>
                                                <?php endif; ?>
                                                <?php if (!empty($trailAttachments)): ?>
                                                    <div class="st-trail-attachments">
                                                        <?php foreach ($trailAttachments as $att): ?>
                                                            <a class="st-attachment-link" href="controllers/attachments/download.php?id=<?php echo (int) ($att['id'] ?? 0); ?>">
                                                                <i class="fa-solid fa-paperclip" aria-hidden="true"></i>
                                                                <?php echo htmlspecialchars((string) ($att['file_name'] ?? 'Attachment')); ?>
                                                            </a>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </details>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>

                            <?php if (!$isClosed): ?>
                                <form method="post" action="controllers/branch/reply-ticket.php" enctype="multipart/form-data" class="st-trail-reply-form">
                                    <input type="hidden" name="ticket_id" value="<?php echo $ticketId; ?>">
                                    <textarea name="message" class="form-control" rows="3" placeholder="Type your reply..." required></textarea>
                                    <div class="st-trail-reply-actions">
                                        <input class="form-control form-control-sm" type="file" name="attachments[]" multiple>
                                        <button class="btn btn-danger" type="submit">Submit</button>
                                    </div>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Modal: Create Ticket -->
        <div class="st-modal-backdrop" id="createTicketModal">
            <div class="st-modal">
                <div class="st-modal-header">
                    <h5 class="mb-0">Create Ticket</h5>
                    <button type="button" class="st-modal-close" id="stCloseCreateModal" aria-label="Close">&times;</button>
                </div>

                <div class="st-modal-body">
                    <form id="stCreateTicketForm" method="post" action="controllers/branch/create-ticket.php" enctype="multipart/form-data" class="entry-form auto-entry-form manual-entry-form" novalidate>
                        <input type="hidden" name="ticket_type_id" id="ticket_type_id" value="<?php echo isset($ticketTypes[0]) ? (int) $ticketTypes[0]['id'] : 1; ?>">

                        <div class="auto-content-grid">
                            <!-- Left column: Transaction Details -->
                            <div class="auto-data-column">
                                <div class="auto-data-header">
                                    <span class="material-icons">folder_open</span>
                                    <h3>Transaction Details</h3>
                                    <div class="manual-ref-toggle" style="margin-left:12px;">
                                        <div class="toggle-wrapper" style="display:flex;align-items:center;gap:8px;font-weight:600;">
                                            <span style="font-size:13px;color:#334155">Include Reference No.</span>
                                            <label class="switch" aria-label="Include Reference No.">
                                                <input id="mRefToggle" name="include_ref_no" type="checkbox" value="1">
                                                <span class="slider"></span>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                <div class="auto-data-card">
                                    <div class="data-group group-1">
                                        <div class="data-item field-span-2" data-ref-group style="display:none;">
                                            <div class="data-icon"><span class="material-icons">tag</span></div>
                                            <div class="data-content">
                                                <span class="data-label">Reference Number</span>
                                                <input id="reference_number" name="reference_number" class="data-value field-input" type="text" placeholder="Enter reference number">
                                            </div>
                                        </div>

                                        <div class="data-item">
                                            <div class="data-icon"><span class="material-icons">hub</span></div>
                                            <div class="data-content">
                                                <span class="data-label">Source</span>
                                                <select id="source" name="source" class="data-value field-input required-field" required>
                                                    <option value="KPX" selected>KPX</option>
                                                    <option value="KP7">KP7</option>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="data-item">
                                            <div class="data-icon"><span class="material-icons">schedule</span></div>
                                            <div class="data-content">
                                                <span class="data-label">Transaction Date/Time</span>
                                                <input id="transfer_datetime" name="transfer_datetime" class="data-value field-input required-field" type="datetime-local" required>
                                            </div>
                                        </div>

                                        <div class="data-item">
                                            <div class="data-icon"><span class="material-icons">account_balance</span></div>
                                            <div class="data-content">
                                                <span class="data-label">Account Number</span>
                                                <input id="account_no" name="account_no" class="data-value field-input required-field" type="text" placeholder="Enter account number" required>
                                            </div>
                                        </div>

                                        <div class="data-item field-span-2">
                                            <div class="data-icon"><span class="material-icons">person</span></div>
                                            <div class="data-content">
                                                <span class="data-label">Account Name</span>
                                                <input id="account_name" name="account_name" class="data-value field-input required-field" type="text" placeholder="Enter account name" required>
                                            </div>
                                        </div>

                                        
                                    </div>

                                    <div class="data-group group-2">
                                        <div class="data-item">
                                            <div class="data-icon"><span class="material-icons">business</span></div>
                                            <div class="data-content">
                                                <span class="data-label">Branch ID</span>
                                                <input id="payment_branch_id" name="payment_branch_id" class="data-value field-input required-field" type="text" placeholder="Enter branch ID" required>
                                            </div>
                                        </div>

                                        <div class="data-item">
                                            <div class="data-icon"><span class="material-icons">store</span></div>
                                            <div class="data-content">
                                                <span class="data-label">Payment Branch</span>
                                                <input id="payment_branch_name" name="payment_branch_name" class="data-value field-input required-field" type="text" placeholder="Enter branch name" required>
                                            </div>
                                        </div>

                                        <div class="data-item">
                                            <div class="data-icon"><span class="material-icons">warning</span></div>
                                            <div class="data-content">
                                                <span class="data-label">Biller ID</span>
                                                <select id="subbiller_ext_id" name="subbiller_ext_id" class="data-value field-input required-field">
                                                    <option value="">Select Subbiller</option>
                                                    <?php foreach ($subbillers as $sb): ?>
                                                        <option
                                                            value="<?php echo htmlspecialchars((string) $sb['subbiller_ext_id']); ?>"
                                                            data-subbiller-name="<?php echo htmlspecialchars((string) $sb['subbiller_name']); ?>"
                                                            data-partner-ext-id="<?php echo htmlspecialchars((string) $sb['partner_ext_id']); ?>"
                                                        >
                                                            <?php echo htmlspecialchars((string) $sb['subbiller_name']); ?> (<?php echo htmlspecialchars((string) $sb['subbiller_ext_id']); ?>)
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="data-item">
                                            <div class="data-icon"><span class="material-icons">business</span></div>
                                            <div class="data-content">
                                                <span class="data-label">Biller Name</span>
                                                <input id="biller_name" name="biller_name" class="data-value field-input" type="text" placeholder="Biller name" readonly>
                                                <input type="hidden" name="partner_ext_id" id="partner_ext_id">
                                            </div>
                                        </div>

                                        <div class="data-item field-span-2">
                                            <div class="data-icon"><span class="material-icons">attach_money</span></div>
                                            <div class="data-content">
                                                <span class="data-label">Amount</span>
                                                <input id="amount" name="amount" class="data-value field-input required-field currency-input" type="text" inputmode="decimal" required>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Right column: Request Information -->
                            <div class="auto-input-column">
                                <div class="auto-input-header">
                                    <span class="material-icons">edit_note</span>
                                    <h3>Request Information</h3>
                                </div>
                                <div class="auto-input-card">
                                    <div class="field-group">
                                        <label for="type_of_request"><span class="material-icons">category</span> Type of Request</label>

                                        <div class="subbiller-dropdown type-dropdown" id="typeDropdown">
                                            <button type="button" id="typeToggle" class="subbiller-toggle type-toggle">Select request type <i class="fa-solid fa-caret-down" aria-hidden="true"></i></button>
                                            <div class="subbiller-list partner-list type-list" id="typeList" aria-hidden="true">
                                                <!-- items will be populated from the select by JS -->
                                            </div>
                                        </div>

                                        <select id="type_of_request" name="type_of_request" class="field-input required-field" required style="display:none;">
                                            <option value="">Select request type</option>
                                            <option value="NO PAYMENT RECEIVED">NO PAYMENT RECEIVED</option>
                                            <option value="DOUBLE POSTING">DOUBLE POSTING</option>
                                            <option value="MULTI POSTING">MULTI POSTING</option>
                                            <option value="TRIPLE POSTING">TRIPLE POSTING</option>
                                            <option value="WRONG BILLER">WRONG BILLER</option>
                                            <option value="OVERSTATED AMOUNT">OVERSTATED AMOUNT</option>
                                            <option value="CANCELLED TRANSACTION">CANCELLED TRANSACTION</option>
                                            <option value="UNREFLECTED TRXN">UNREFLECTED TRXN</option>
                                        </select>
                                    </div>

                                    <div class="field-group overstated-group" style="display:none;">
                                        <label for="wrong_amount"><span class="material-icons">payments</span> Wrong Amount</label>
                                        <input id="wrong_amount" name="wrong_amount" class="field-input currency-input" type="text" inputmode="decimal" pattern="[0-9,\.\-]*" placeholder="0.00">
                                    </div>

                                    <div class="field-group overstated-group" style="display:none;">
                                        <label for="correct_amount"><span class="material-icons">payments</span> Correct Amount</label>
                                        <input id="correct_amount" name="correct_amount" class="field-input currency-input" type="text" inputmode="decimal" pattern="[0-9,\.\-]*" placeholder="0.00">
                                    </div>

                                    <div class="field-group overstated-group" style="display:none;">
                                        <label for="difference_value"><span class="material-icons">calculate</span> Difference</label>
                                        <input id="difference_value" name="difference_value" class="field-input currency-input" type="text" readonly placeholder="0.00">
                                    </div>

                                    <div class="field-group">
                                        <label for="correct_biller_id"><span class="material-icons">check_circle</span> Correct Biller ID</label>
                                        <input id="correct_biller_id" name="correct_biller_id" class="field-input required-field" type="text" placeholder="Enter correct biller ID" required>
                                    </div>

                                    <div class="field-group">
                                        <label for="correct_biller_name"><span class="material-icons">business</span> Correct Biller Name</label>
                                        <input id="correct_biller_name" name="correct_biller_name" class="field-input required-field" type="text" placeholder="Enter correct biller name" required>
                                    </div>

                                    <div class="field-group field-fullwidth">
                                        <label for="reason"><span class="material-icons">description</span> Reason for Request</label>
                                        <textarea id="reason" name="reason" class="field-input required-field" rows="4" placeholder="Provide detailed reason for this support ticket request" required></textarea>
                                    </div>

                                </div>
                            </div>
                        </div>

                        <!-- Attachments: bottom full-width area -->
                        <div class="st-attachments-section">
                            <h6 style="margin:0 0 8px 0;font-weight:700;color:#111827">Attachments</h6>
                            <div id="stFileUploadArea" class="file-upload-area" tabindex="0">
                                <div class="file-upload-icon"><i class="fa-solid fa-paperclip"></i></div>
                                <div><strong>Drag & drop files here</strong></div>
                                <div class="text-muted">or click to browse</div>
                                <div class="text-muted"><small>Supported: PNG, JPEG, JPG, GIF, WEBP, PDF, DOCX, TXT, XLSX, CSV, ODS</small></div>
                                <input type="file" id="attachments" name="attachments[]" accept="image/*,.pdf,.docx,.doc,.txt,.xlsx,.csv,.ods" multiple style="display:none;">
                            </div>
                            <div id="stFilesContainer" style="margin-top:8px;"></div>
                        </div>

                        <div class="mt-3 d-flex justify-content-end gap-2">
                            <button type="button" class="btn btn-light" id="stCloseCreateModalBtn">Cancel</button>
                            <button type="submit" class="btn btn-danger">Submit Ticket</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Confirmation modal (shown before final submit) -->
        <div class="st-modal-backdrop" id="stConfirmSubmitModal" aria-hidden="true">
            <div class="st-modal" style="max-width:520px;">
                <div class="st-modal-header">
                    <h5 class="mb-0">Confirm Submit</h5>
                    <button type="button" class="st-modal-close" id="stCloseConfirmModal" aria-label="Close">&times;</button>
                </div>
                <div class="st-modal-body">
                    <p>Are you sure you want to submit this ticket? This will create a support ticket for review.</p>
                    <div class="mt-3 d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-light" id="stCancelSubmitBtn">Cancel</button>
                        <button type="button" class="btn btn-danger" id="stConfirmSubmitBtn">Confirm Submit</button>
                    </div>
                </div>
            </div>
        </div>

        <?php include '../../templates/footer.php'; ?>
    </div>

    <script src="assets/js/support-ticket-ui.js?v=<?php echo time(); ?>"></script>
    <script src="assets/js/create-ticket.js?v=<?php echo time(); ?>"></script>
    <script>
        (function () {
            var cancelBtn = document.getElementById('stCloseCreateModalBtn');
            var xBtn = document.getElementById('stCloseCreateModal');
            var modal = document.getElementById('createTicketModal');

            function closeModal() {
                if (modal) modal.classList.remove('open');
            }

            if (cancelBtn) {
                cancelBtn.addEventListener('click', closeModal);
            }
            if (xBtn) {
                xBtn.addEventListener('click', closeModal);
            }

            // open button
            var openBtn = document.getElementById('stOpenCreateModal');
            if (openBtn) {
                openBtn.addEventListener('click', function (e) {
                    e.preventDefault();
                    var m = document.getElementById('createTicketModal');
                    if (m) m.classList.add('open');
                });
            }
        })();
    </script>
</body>
</html>
