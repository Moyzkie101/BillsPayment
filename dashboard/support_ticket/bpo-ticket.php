<?php
include_once __DIR__ . '/includes/bootstrap.php';
include_once __DIR__ . '/includes/ticket_queries.php';

st_require_login('../../login_form.php');
st_require_permission_page(['Support Ticket BPO'], '../home.php');

$userId = st_user_id_or_null();
$flash = st_flash_get('vpo_ticket');
$mode = strtolower(trim((string) ($_GET['mode'] ?? 'open')));
if (!in_array($mode, ['open', 'active', 'closed'], true)) {
    $mode = 'open';
}

$vpoOpen = st_get_vpo_open_tickets($conn);
$vpoActive = $userId !== null ? st_get_vpo_active_tickets($conn, $userId) : [];
$vpoClosed = $userId !== null ? st_get_vpo_closed_tickets($conn, $userId) : [];

function st_status_class_vpo($status)
{
    return 'st-status st-status-' . strtolower((string) $status);
}

function st_partner_name_vpo($ticket)
{
    $partner = trim((string) ($ticket['partner_name'] ?? ''));
    if ($partner !== '') {
        return $partner;
    }
    $ext = trim((string) ($ticket['partner_ext_id'] ?? ''));
    return $ext !== '' ? $ext : 'N/A';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Support Ticket - VPO</title>
    <link rel="icon" href="../../images/MLW%20logo.png" type="image/png">
    <link rel="stylesheet" href="../../assets/css/templates/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="assets/css/support-ticket.css?v=<?php echo time(); ?>">
    <script src="https://kit.fontawesome.com/30b908cc5a.js" crossorigin="anonymous"></script>
</head>
<body>
    <div class="main-container">
        <?php include '../../templates/header_ui.php'; ?>
        <?php include '../../templates/sidebar.php'; ?>

        <?php if (function_exists('bp_section_header_html')): ?>
            <?php bp_section_header_html('fa-solid fa-headset', 'Support Ticket - VPO', 'Open / Active / Closed'); ?>
        <?php else: ?>
            <div class="container-fluid mt-3"><h3>Support Ticket - VPO</h3></div>
        <?php endif; ?>

        <div class="container-fluid st-wrapper">
            <?php if ($flash): ?>
                <div class="alert alert-<?php echo htmlspecialchars($flash['type']); ?>" role="alert">
                    <?php echo htmlspecialchars($flash['message']); ?>
                </div>
            <?php endif; ?>

            <div class="mode-cards" data-st-mode-group data-st-param="mode">
                <label class="mode-card <?php echo $mode === 'open' ? 'selected' : ''; ?>" data-mode="open">
                    <input type="radio" name="vpoMode" value="open" <?php echo $mode === 'open' ? 'checked' : ''; ?>>
                    <div class="mode-icon"><i class="fa-solid fa-inbox"></i></div>
                    <div class="mode-text"><p class="mode-label">OPEN</p><small>Unassigned VPO queue</small></div>
                </label>

                <label class="mode-card <?php echo $mode === 'active' ? 'selected' : ''; ?>" data-mode="active">
                    <input type="radio" name="vpoMode" value="active" <?php echo $mode === 'active' ? 'checked' : ''; ?>>
                    <div class="mode-icon"><i class="fa-solid fa-bolt"></i></div>
                    <div class="mode-text"><p class="mode-label">ACTIVE</p><small>Assigned to you</small></div>
                </label>

                <label class="mode-card <?php echo $mode === 'closed' ? 'selected' : ''; ?>" data-mode="closed">
                    <input type="radio" name="vpoMode" value="closed" <?php echo $mode === 'closed' ? 'checked' : ''; ?>>
                    <div class="mode-icon"><i class="fa-solid fa-box-archive"></i></div>
                    <div class="mode-text"><p class="mode-label">CLOSED</p><small>Completed tickets</small></div>
                </label>
            </div>

            <div class="mode-panel <?php echo $mode === 'open' ? '' : 'hidden'; ?>" data-st-panel="open">
                <?php if (empty($vpoOpen)): ?>
                    <div class="st-empty">No tickets in VPO open queue.</div>
                <?php else: ?>
                    <div class="st-ticket-grid">
                        <?php foreach ($vpoOpen as $ticket): ?>
                            <article class="st-ticket-card">
                                <div class="st-ticket-head">
                                    <div class="st-ticket-number"><?php echo htmlspecialchars((string) $ticket['ticket_number']); ?></div>
                                    <span class="<?php echo htmlspecialchars(st_status_class_vpo($ticket['status'])); ?>"><?php echo htmlspecialchars((string) $ticket['status']); ?></span>
                                </div>
                                <div class="st-ticket-date"><?php echo htmlspecialchars((string) $ticket['created_at']); ?></div>
                                <div class="st-ticket-type"><?php echo htmlspecialchars((string) ($ticket['ticket_type_label'] ?: $ticket['type_of_request'])); ?></div>
                                <div class="st-ticket-partner">Partner: <?php echo htmlspecialchars(st_partner_name_vpo($ticket)); ?></div>
                                <div class="st-ticket-reason"><?php echo htmlspecialchars((string) $ticket['reason']); ?></div>

                                <div class="st-ticket-actions">
                                    <form method="post" action="controllers/vpo/accept-ticket.php">
                                        <input type="hidden" name="ticket_id" value="<?php echo (int) $ticket['id']; ?>">
                                        <button class="btn btn-sm btn-primary" type="submit">Accept</button>
                                    </form>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="mode-panel <?php echo $mode === 'active' ? '' : 'hidden'; ?>" data-st-panel="active">
                <?php if (empty($vpoActive)): ?>
                    <div class="st-empty">No active tickets assigned to you.</div>
                <?php else: ?>
                    <div class="st-ticket-grid">
                        <?php foreach ($vpoActive as $ticket): ?>
                            <article class="st-ticket-card">
                                <div class="st-ticket-head">
                                    <div class="st-ticket-number"><?php echo htmlspecialchars((string) $ticket['ticket_number']); ?></div>
                                    <span class="<?php echo htmlspecialchars(st_status_class_vpo($ticket['status'])); ?>"><?php echo htmlspecialchars((string) $ticket['status']); ?></span>
                                </div>
                                <div class="st-ticket-date"><?php echo htmlspecialchars((string) $ticket['created_at']); ?></div>
                                <div class="st-ticket-type"><?php echo htmlspecialchars((string) ($ticket['ticket_type_label'] ?: $ticket['type_of_request'])); ?></div>
                                <div class="st-ticket-partner">Partner: <?php echo htmlspecialchars(st_partner_name_vpo($ticket)); ?></div>
                                <div class="st-ticket-reason"><?php echo htmlspecialchars((string) $ticket['reason']); ?></div>

                                <details class="st-ticket-actions">
                                    <summary>Manage Ticket</summary>

                                    <form method="post" action="controllers/vpo/submit-ticket.php">
                                        <input type="hidden" name="action" value="reply">
                                        <input type="hidden" name="ticket_id" value="<?php echo (int) $ticket['id']; ?>">
                                        <input class="form-control form-control-sm" type="text" name="message" placeholder="Reply to Branch" required>
                                        <button class="btn btn-sm btn-secondary" type="submit">Submit Reply</button>
                                    </form>

                                    <form method="post" action="controllers/vpo/submit-ticket.php">
                                        <input type="hidden" name="action" value="transfer_to_cad">
                                        <input type="hidden" name="ticket_id" value="<?php echo (int) $ticket['id']; ?>">
                                        <input class="form-control form-control-sm" type="text" name="message" placeholder="Optional transfer note">
                                        <button class="btn btn-sm btn-warning" type="submit">Submit to CAD</button>
                                    </form>

                                    <form method="post" action="controllers/vpo/close-ticket.php">
                                        <input type="hidden" name="close_mode" value="immediate">
                                        <input type="hidden" name="ticket_id" value="<?php echo (int) $ticket['id']; ?>">
                                        <button class="btn btn-sm btn-danger" type="submit">Close Immediately</button>
                                    </form>

                                    <form method="post" action="controllers/vpo/close-ticket.php">
                                        <input type="hidden" name="close_mode" value="auto">
                                        <input type="hidden" name="ticket_id" value="<?php echo (int) $ticket['id']; ?>">
                                        <div class="d-flex gap-1">
                                            <input class="form-control form-control-sm" style="max-width:80px;" type="number" min="1" name="duration_value" placeholder="3" required>
                                            <select class="form-select form-select-sm" name="duration_unit" required>
                                                <option value="minute">Min</option>
                                                <option value="hour">Hr</option>
                                                <option value="day" selected>Days</option>
                                                <option value="week">Weeks</option>
                                                <option value="month">Mths</option>
                                                <option value="year">Yrs</option>
                                            </select>
                                        </div>
                                        <button class="btn btn-sm btn-success" type="submit">Auto Close</button>
                                    </form>
                                </details>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="mode-panel <?php echo $mode === 'closed' ? '' : 'hidden'; ?>" data-st-panel="closed">
                <?php if (empty($vpoClosed)): ?>
                    <div class="st-empty">No closed tickets.</div>
                <?php else: ?>
                    <div class="st-ticket-grid">
                        <?php foreach ($vpoClosed as $ticket): ?>
                            <article class="st-ticket-card">
                                <div class="st-ticket-head">
                                    <div class="st-ticket-number"><?php echo htmlspecialchars((string) $ticket['ticket_number']); ?></div>
                                    <span class="<?php echo htmlspecialchars(st_status_class_vpo($ticket['status'])); ?>"><?php echo htmlspecialchars((string) $ticket['status']); ?></span>
                                </div>
                                <div class="st-ticket-date"><?php echo htmlspecialchars((string) ($ticket['closed_at'] ?: $ticket['created_at'])); ?></div>
                                <div class="st-ticket-type"><?php echo htmlspecialchars((string) ($ticket['ticket_type_label'] ?: $ticket['type_of_request'])); ?></div>
                                <div class="st-ticket-partner">Partner: <?php echo htmlspecialchars(st_partner_name_vpo($ticket)); ?></div>
                                <div class="st-ticket-reason"><?php echo htmlspecialchars((string) $ticket['reason']); ?></div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php include '../../templates/footer.php'; ?>
    </div>

    <script src="assets/js/support-ticket-ui.js?v=<?php echo time(); ?>"></script>
</body>
</html>
