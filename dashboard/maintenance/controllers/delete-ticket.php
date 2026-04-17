<?php
include_once __DIR__ . '/../../support_ticket/includes/bootstrap.php';

st_require_login('../../../login_form.php');
st_require_permission_page(['Support Ticket Report', 'Maintenance Support Ticket'], '../../home.php');

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
    st_redirect_with_flash('maintenance_ticket', 'danger', 'Invalid request method.', '../ticket/ticket-managment.php');
}

$ticketId = (int) ($_POST['ticket_id'] ?? 0);
$returnMode = strtolower(trim((string) ($_POST['return_mode'] ?? 'open')));
if (!in_array($returnMode, ['open', 'active', 'closed'], true)) {
    $returnMode = 'open';
}

$redirectUrl = '../ticket/ticket-managment.php?mode=' . urlencode($returnMode);

if ($ticketId <= 0) {
    st_redirect_with_flash('maintenance_ticket', 'danger', 'Invalid ticket selected.', $redirectUrl);
}

$schema = st_schema();

$ticketNumber = '';
$q = $conn->prepare("SELECT ticket_number FROM {$schema}.tickets WHERE id = ? LIMIT 1");
if ($q) {
    $q->bind_param('i', $ticketId);
    if ($q->execute()) {
        $res = $q->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        if ($row) {
            $ticketNumber = (string) ($row['ticket_number'] ?? '');
        }
    }
    $q->close();
}

if ($ticketNumber === '') {
    st_redirect_with_flash('maintenance_ticket', 'danger', 'Ticket not found or already deleted.', $redirectUrl);
}

$conn->begin_transaction();

try {
    $statements = [
        "DELETE FROM {$schema}.ticket_attachments WHERE ticket_id = ?",
        "DELETE FROM {$schema}.ticket_trails WHERE ticket_id = ?",
        "DELETE FROM {$schema}.ticket_info WHERE ticket_number = ?",
        "DELETE FROM {$schema}.ticket_info_wrongbiller WHERE ticket_number = ?",
        "DELETE FROM {$schema}.ticket_info_overstatedamount WHERE ticket_number = ?",
        "DELETE FROM {$schema}.ticket_info_cancelledtransaction WHERE ticket_number = ?",
        "DELETE FROM {$schema}.ticket_badge WHERE ticket_number = ?",
        "DELETE FROM {$schema}.ticket_active WHERE ticket_number = ?",
    ];

    foreach ($statements as $sql) {
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            continue;
        }

        if (strpos($sql, 'ticket_id = ?') !== false) {
            $stmt->bind_param('i', $ticketId);
        } else {
            $stmt->bind_param('s', $ticketNumber);
        }

        $stmt->execute();
        $stmt->close();
    }

    $del = $conn->prepare("DELETE FROM {$schema}.tickets WHERE id = ? LIMIT 1");
    if (!$del) {
        throw new Exception('Unable to prepare final ticket delete.');
    }
    $del->bind_param('i', $ticketId);
    if (!$del->execute()) {
        $del->close();
        throw new Exception('Unable to delete ticket.');
    }
    $affected = (int) $del->affected_rows;
    $del->close();

    if ($affected <= 0) {
        throw new Exception('Ticket was not deleted.');
    }

    $conn->commit();
    st_redirect_with_flash('maintenance_ticket', 'success', 'Ticket ' . $ticketNumber . ' deleted successfully.', $redirectUrl);
} catch (Throwable $e) {
    $conn->rollback();
    st_redirect_with_flash('maintenance_ticket', 'danger', 'Delete failed: ' . $e->getMessage(), $redirectUrl);
}
