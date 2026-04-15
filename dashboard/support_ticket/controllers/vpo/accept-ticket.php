<?php
include_once __DIR__ . '/../../includes/bootstrap.php';

st_require_login('../../../../login_form.php');
st_require_permission_page(['Support Ticket BPO'], '../../../home.php');

$returnMode = strtolower(trim((string) ($_POST['return_mode'] ?? '')));
$redirectBack = '../../bpo-ticket.php';
if (in_array($returnMode, ['open', 'active', 'closed'], true)) {
    $redirectBack .= '?mode=' . $returnMode;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    st_redirect_with_flash('vpo_ticket', 'danger', 'Invalid request method.', $redirectBack);
}

$ticketId = (int) ($_POST['ticket_id'] ?? 0);
$userId = st_user_id_or_null();
if ($ticketId <= 0 || $userId === null) {
    st_redirect_with_flash('vpo_ticket', 'danger', 'Invalid ticket or user context.', $redirectBack);
}

$conn->autocommit(false);

try {
    $schema = st_schema();

    $updateSql = "UPDATE {$schema}.tickets
                  SET status = 'accepted',
                      assigned_to = ?,
                      vpo_owner = COALESCE(vpo_owner, ?),
                      updated_at = NOW()
                  WHERE id = ?
                    AND current_handler_role = 'VPO'
                    AND assigned_to IS NULL
                    AND status IN ('open', 'accepted')";
    $stmt = $conn->prepare($updateSql);
    if (!$stmt) {
        throw new Exception('Unable to prepare accept update.');
    }

    $stmt->bind_param('iii', $userId, $userId, $ticketId);
    if (!$stmt->execute()) {
        $stmt->close();
        throw new Exception('Unable to accept ticket.');
    }

    if ($stmt->affected_rows <= 0) {
        $stmt->close();
        throw new Exception('Ticket is already assigned or no longer in VPO open queue.');
    }
    $stmt->close();

    st_insert_trail($conn, $ticketId, 'accept', $userId, 'VPO', 'BRANCH', 'Ticket accepted by VPO.', null);

    $conn->commit();
    $conn->autocommit(true);

    st_redirect_with_flash('vpo_ticket', 'success', 'Ticket accepted successfully.', $redirectBack);
} catch (Exception $e) {
    $conn->rollback();
    $conn->autocommit(true);
    st_redirect_with_flash('vpo_ticket', 'danger', $e->getMessage(), $redirectBack);
}
