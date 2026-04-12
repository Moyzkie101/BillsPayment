<?php
include_once __DIR__ . '/../../includes/bootstrap.php';

st_require_login('../../../../login_form.php');
st_require_permission_page(['Support Ticket CAD'], '../../../home.php');

$redirectBack = '../../cad-ticket.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    st_redirect_with_flash('cad_ticket', 'danger', 'Invalid request method.', $redirectBack);
}

$action = trim((string) ($_POST['action'] ?? 'reply'));
$ticketId = (int) ($_POST['ticket_id'] ?? 0);
$message = trim((string) ($_POST['message'] ?? ''));
$userId = st_user_id_or_null();

if ($ticketId <= 0 || $userId === null) {
    st_redirect_with_flash('cad_ticket', 'danger', 'Invalid ticket or user context.', $redirectBack);
}

if (!in_array($action, ['reply', 'transfer_to_vpo'], true)) {
    st_redirect_with_flash('cad_ticket', 'danger', 'Invalid action.', $redirectBack);
}

if ($action === 'reply' && $message === '') {
    st_redirect_with_flash('cad_ticket', 'danger', 'Reply message is required.', $redirectBack);
}

$conn->autocommit(false);

try {
    $schema = st_schema();

    $lockSql = "SELECT id, status, current_handler_role, assigned_to
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

    if ((string) $ticket['current_handler_role'] !== 'CAD' || (int) $ticket['assigned_to'] !== (int) $userId) {
        throw new Exception('Ticket is not assigned to you as CAD.');
    }

    if ((string) $ticket['status'] === 'closed') {
        throw new Exception('Cannot update a closed ticket.');
    }

    if ($action === 'reply') {
        st_insert_trail($conn, $ticketId, 'message', $userId, 'CAD', 'BRANCH', $message, null);

        $conn->commit();
        $conn->autocommit(true);
        st_redirect_with_flash('cad_ticket', 'success', 'Reply submitted.', $redirectBack);
    }

    $transferMessage = $message !== '' ? $message : 'Ticket transferred to VPO.';

    $updSql = "UPDATE {$schema}.tickets
               SET current_handler_role = 'VPO',
                   assigned_to = NULL,
                   status = 'accepted',
                   updated_at = NOW()
               WHERE id = ? AND current_handler_role = 'CAD' AND assigned_to = ?";
    $updStmt = $conn->prepare($updSql);
    if (!$updStmt) {
        throw new Exception('Unable to prepare transfer update.');
    }

    $updStmt->bind_param('ii', $ticketId, $userId);
    if (!$updStmt->execute()) {
        $updStmt->close();
        throw new Exception('Unable to transfer ticket to VPO.');
    }

    if ($updStmt->affected_rows <= 0) {
        $updStmt->close();
        throw new Exception('Ticket transfer failed due to queue state change.');
    }
    $updStmt->close();

    st_insert_trail($conn, $ticketId, 'transfer', $userId, 'CAD', 'VPO', $transferMessage, null);
    st_insert_trail(
        $conn,
        $ticketId,
        'message',
        null,
        'SYSTEM',
        'VPO',
        'Ticket has been transferred to VPO.',
        ['automation' => true]
    );

    $conn->commit();
    $conn->autocommit(true);
    st_redirect_with_flash('cad_ticket', 'success', 'Ticket transferred to VPO.', $redirectBack);
} catch (Exception $e) {
    $conn->rollback();
    $conn->autocommit(true);
    st_redirect_with_flash('cad_ticket', 'danger', $e->getMessage(), $redirectBack);
}
