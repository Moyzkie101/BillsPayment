<?php
include_once __DIR__ . '/../../includes/bootstrap.php';

st_require_login('../../../../login_form.php');
st_require_permission_page(['Support Ticket Create'], '../../../home.php');

$redirectBack = '../../create-ticket.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    st_redirect_with_flash('create_ticket', 'danger', 'Invalid request method.', $redirectBack);
}

$ticketId = (int) ($_POST['ticket_id'] ?? 0);
$message = trim((string) ($_POST['message'] ?? ''));
$userId = st_user_id_or_null();

if ($ticketId <= 0 || $userId === null) {
    st_redirect_with_flash('create_ticket', 'danger', 'Invalid ticket or user context.', $redirectBack);
}

if ($message === '') {
    st_redirect_with_flash('create_ticket', 'danger', 'Reply message is required.', $redirectBack);
}

$conn->autocommit(false);

try {
    $schema = st_schema();

    $lockSql = "SELECT id, status, current_handler_role, created_by
                FROM {$schema}.tickets
                WHERE id = ? FOR UPDATE";
    $lockStmt = $conn->prepare($lockSql);
    if (!$lockStmt) {
        throw new Exception('Unable to prepare ticket lock query.');
    }

    $lockStmt->bind_param('i', $ticketId);
    if (!$lockStmt->execute()) {
        $lockStmt->close();
        throw new Exception('Unable to lock ticket row.');
    }

    $res = $lockStmt->get_result();
    $ticket = $res ? $res->fetch_assoc() : null;
    $lockStmt->close();

    if (!$ticket) {
        throw new Exception('Ticket not found.');
    }

    if ((int) $ticket['created_by'] !== (int) $userId) {
        throw new Exception('You can only reply to your own tickets.');
    }

    if ((string) $ticket['status'] === 'closed') {
        throw new Exception('Cannot reply to a closed ticket.');
    }

    $targetRole = (string) $ticket['current_handler_role'];
    if ($targetRole !== 'VPO' && $targetRole !== 'CAD') {
        $targetRole = 'VPO';
    }

    $trailId = st_insert_trail($conn, $ticketId, 'message', $userId, 'BRANCH', $targetRole, $message, null);

    $attachments = st_uploads_to_array('attachments');
    foreach ($attachments as $file) {
        st_insert_attachment($conn, $ticketId, $trailId, $userId, $file);
    }

    $conn->commit();
    $conn->autocommit(true);

    st_redirect_with_flash('create_ticket', 'success', 'Reply submitted successfully.', $redirectBack);
} catch (Exception $e) {
    $conn->rollback();
    $conn->autocommit(true);
    st_redirect_with_flash('create_ticket', 'danger', $e->getMessage(), $redirectBack);
}
