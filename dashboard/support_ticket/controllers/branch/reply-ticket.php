<?php
include_once __DIR__ . '/../../includes/bootstrap.php';

st_require_login('../../../../login_form.php');
st_require_permission_page(['Support Ticket Create'], '../../../home.php');

$returnMode = strtolower(trim((string) ($_POST['return_mode'] ?? '')));
$redirectBack = '../../create-ticket.php';
if (in_array($returnMode, ['open', 'closed'], true)) {
    $redirectBack .= '?mode=' . $returnMode;
}

$isAjax = st_is_ajax_request();
$fail = function ($message, $statusCode = 400) use ($isAjax, $redirectBack) {
    if ($isAjax) {
        st_json(false, $message, [], $statusCode);
    }
    st_redirect_with_flash('create_ticket', 'danger', $message, $redirectBack);
};

$ok = function ($message, $data = []) use ($isAjax, $redirectBack) {
    if ($isAjax) {
        st_json(true, $message, $data, 200);
    }
    st_redirect_with_flash('create_ticket', 'success', $message, $redirectBack);
};

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $fail('Invalid request method.', 405);
}

$ticketId = (int) ($_POST['ticket_id'] ?? 0);
$message = trim((string) ($_POST['message'] ?? ''));
$userId = st_user_id_or_null();

if ($ticketId <= 0 || $userId === null) {
    $fail('Invalid ticket or user context.', 401);
}

if ($message === '') {
    $fail('Reply message is required.');
}

$conn->autocommit(false);

try {
    $schema = st_schema();

    $lockSql = "SELECT id, status, current_handler_role, created_by, vpo_owner
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

    $statusNow = strtolower((string) ($ticket['status'] ?? ''));
    $targetRole = (string) $ticket['current_handler_role'];

    // Branch reply on a resolved ticket should reopen it and hand back to VPO owner.
    $trailMeta = null;

    if ($statusNow === 'resolved') {
        $vpoOwner = isset($ticket['vpo_owner']) && is_numeric($ticket['vpo_owner']) ? (int) $ticket['vpo_owner'] : 0;
        $reopenSql = "UPDATE {$schema}.tickets
                      SET status = 'resolving',
                          current_handler_role = 'VPO',
                          assigned_to = " . ($vpoOwner > 0 ? '?' : 'NULL') . "
                      WHERE id = ?";
        $reopenStmt = $conn->prepare($reopenSql);
        if (!$reopenStmt) {
            throw new Exception('Unable to prepare ticket reopen update.');
        }
        if ($vpoOwner > 0) {
            $reopenStmt->bind_param('ii', $vpoOwner, $ticketId);
        } else {
            $reopenStmt->bind_param('i', $ticketId);
        }
        if (!$reopenStmt->execute()) {
            $reopenStmt->close();
            throw new Exception('Unable to reopen resolved ticket.');
        }
        $reopenStmt->close();
        $targetRole = 'VPO';
        $trailMeta = ['reopened' => true];
    } elseif ($targetRole !== 'VPO' && $targetRole !== 'CAD') {
        $targetRole = 'VPO';
    }

    $trailId = st_insert_trail($conn, $ticketId, 'message', $userId, 'BRANCH', $targetRole, $message, $trailMeta);

    $attachments = st_uploads_to_array('attachments');
    foreach ($attachments as $file) {
        st_insert_attachment($conn, $ticketId, $trailId, $userId, $file);
    }

    $conn->commit();
    $conn->autocommit(true);

    $ok('Reply submitted successfully.');
} catch (Exception $e) {
    $conn->rollback();
    $conn->autocommit(true);
    $fail($e->getMessage(), 500);
}
