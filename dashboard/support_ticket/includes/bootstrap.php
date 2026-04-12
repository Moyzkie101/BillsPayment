<?php
if (!defined('SUPPORT_TICKET_BOOTSTRAP_LOADED')) {
    define('SUPPORT_TICKET_BOOTSTRAP_LOADED', true);

    include_once __DIR__ . '/../../../config/config.php';
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    include_once __DIR__ . '/../../../templates/middleware.php';

    function st_schema()
    {
        return 'support_ticket';
    }

    function st_user_identifier()
    {
        if (function_exists('resolve_user_identifier')) {
            return resolve_user_identifier();
        }

        if (!empty($_SESSION['id_number'])) {
            return $_SESSION['id_number'];
        }
        if (!empty($_SESSION['idnum'])) {
            return $_SESSION['idnum'];
        }
        if (!empty($_SESSION['user_id'])) {
            return $_SESSION['user_id'];
        }
        return null;
    }

    function st_user_id_or_null()
    {
        $id = st_user_identifier();
        if ($id === null || $id === '') {
            return null;
        }
        return is_numeric($id) ? (int) $id : null;
    }

    function st_require_login($redirectTo = '../../login_form.php')
    {
        $id = st_user_identifier();
        if (empty($id)) {
            header('Location: ' . $redirectTo);
            exit;
        }
    }

    function st_require_permission_page($permissions, $redirectTo = '../home.php')
    {
        if (!function_exists('has_any_permission') || !has_any_permission($permissions)) {
            header('Location: ' . $redirectTo);
            exit;
        }
    }

    function st_require_permission_api($permissions)
    {
        if (!function_exists('has_any_permission') || !has_any_permission($permissions)) {
            st_json(false, 'You do not have permission to perform this action.', [], 403);
        }
    }

    function st_flash_set($key, $type, $message)
    {
        if (!isset($_SESSION['support_ticket_flash'])) {
            $_SESSION['support_ticket_flash'] = [];
        }
        $_SESSION['support_ticket_flash'][$key] = [
            'type' => $type,
            'message' => $message,
        ];
    }

    function st_flash_get($key)
    {
        if (!isset($_SESSION['support_ticket_flash'][$key])) {
            return null;
        }

        $flash = $_SESSION['support_ticket_flash'][$key];
        unset($_SESSION['support_ticket_flash'][$key]);
        return $flash;
    }

    function st_redirect_with_flash($flashKey, $type, $message, $redirectUrl)
    {
        st_flash_set($flashKey, $type, $message);
        header('Location: ' . $redirectUrl);
        exit;
    }

    function st_json($success, $message, $data = [], $status = 200)
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => (bool) $success,
            'message' => $message,
            'data' => $data,
        ]);
        exit;
    }

    function st_to_decimal($value)
    {
        if ($value === null) {
            return null;
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        $clean = str_replace(',', '', $raw);
        if (!is_numeric($clean)) {
            return null;
        }

        return (float) $clean;
    }

    function st_upper($value)
    {
        return strtoupper(trim((string) $value));
    }

    function st_generate_ticket_number($conn)
    {
        $schema = st_schema();

        $seedSql = "INSERT IGNORE INTO {$schema}.ticket_number_seq (seq_date, last_seq) VALUES (CURDATE(), 0)";
        if (!$conn->query($seedSql)) {
            throw new Exception('Unable to initialize ticket sequence.');
        }

        $lockSql = "SELECT last_seq FROM {$schema}.ticket_number_seq WHERE seq_date = CURDATE() FOR UPDATE";
        $res = $conn->query($lockSql);
        if (!$res || $res->num_rows === 0) {
            throw new Exception('Unable to lock ticket sequence row.');
        }

        $row = $res->fetch_assoc();
        $next = (int) $row['last_seq'] + 1;

        $updSql = "UPDATE {$schema}.ticket_number_seq SET last_seq = ? WHERE seq_date = CURDATE()";
        $stmt = $conn->prepare($updSql);
        if (!$stmt) {
            throw new Exception('Unable to prepare ticket sequence update.');
        }

        $stmt->bind_param('i', $next);
        if (!$stmt->execute()) {
            $stmt->close();
            throw new Exception('Unable to increment ticket sequence.');
        }
        $stmt->close();

        return 'TKT-' . date('Ymd') . '-' . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    function st_get_ticket_types($conn)
    {
        $schema = st_schema();
        $list = [];

        $sql = "SELECT id, label FROM {$schema}.ticket_types ORDER BY label ASC";
        $res = $conn->query($sql);
        if (!$res) {
            return $list;
        }

        while ($row = $res->fetch_assoc()) {
            $list[] = $row;
        }

        return $list;
    }

    function st_get_subbiller_by_ext_id($conn, $subbillerExtId)
    {
        $schema = st_schema();
        $sql = "SELECT subbiller_ext_id, subbiller_name, partner_ext_id FROM {$schema}.vw_mldb_subbillers WHERE subbiller_ext_id = ? LIMIT 1";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return null;
        }

        $stmt->bind_param('s', $subbillerExtId);
        if (!$stmt->execute()) {
            $stmt->close();
            return null;
        }

        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();

        return $row ?: null;
    }

    function st_get_subbillers($conn, $limit = 2000)
    {
        $schema = st_schema();
        $limit = max(1, min(5000, (int) $limit));
        $sql = "SELECT subbiller_ext_id, subbiller_name, partner_ext_id FROM {$schema}.vw_mldb_subbillers ORDER BY subbiller_name ASC LIMIT {$limit}";

        $list = [];
        $res = $conn->query($sql);
        if (!$res) {
            return $list;
        }

        while ($row = $res->fetch_assoc()) {
            $list[] = $row;
        }

        return $list;
    }

    function st_insert_trail($conn, $ticketId, $type, $senderId, $senderRole, $targetRole, $message, $meta = null)
    {
        $schema = st_schema();
        $metaJson = $meta === null ? null : json_encode($meta, JSON_UNESCAPED_SLASHES);

        $sql = "INSERT INTO {$schema}.ticket_trails (ticket_id, type, sender_id, sender_role, target_role, message, meta) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception('Unable to prepare trail insert.');
        }

        $senderIdParam = $senderId === null ? null : (int) $senderId;
        $targetRoleParam = $targetRole === null || $targetRole === '' ? null : $targetRole;
        $messageParam = $message === null || $message === '' ? null : $message;

        $stmt->bind_param(
            'isissss',
            $ticketId,
            $type,
            $senderIdParam,
            $senderRole,
            $targetRoleParam,
            $messageParam,
            $metaJson
        );

        if (!$stmt->execute()) {
            $stmt->close();
            throw new Exception('Unable to insert ticket trail.');
        }

        $trailId = (int) $conn->insert_id;
        $stmt->close();

        return $trailId;
    }

    function st_insert_attachment($conn, $ticketId, $trailId, $createdBy, $file)
    {
        $schema = st_schema();

        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return;
        }

        $binary = file_get_contents($file['tmp_name']);
        if ($binary === false) {
            throw new Exception('Unable to read uploaded attachment.');
        }

        $name = (string) ($file['name'] ?? 'attachment');
        $mime = (string) ($file['type'] ?? 'application/octet-stream');
        $size = (int) ($file['size'] ?? strlen($binary));

        $sql = "INSERT INTO {$schema}.ticket_attachments (ticket_trail_id, ticket_id, file_name, mime_type, file_size, file_data, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception('Unable to prepare attachment insert.');
        }

        $createdByParam = $createdBy === null ? null : (int) $createdBy;

        $null = null;
        $stmt->bind_param('iissibi', $trailId, $ticketId, $name, $mime, $size, $null, $createdByParam);
        $stmt->send_long_data(5, $binary);

        if (!$stmt->execute()) {
            $stmt->close();
            throw new Exception('Unable to save attachment.');
        }

        $stmt->close();
    }

    function st_uploads_to_array($fieldName)
    {
        if (!isset($_FILES[$fieldName])) {
            return [];
        }

        $file = $_FILES[$fieldName];

        if (!is_array($file['name'])) {
            return [$file];
        }

        $list = [];
        $count = count($file['name']);
        for ($i = 0; $i < $count; $i++) {
            if ((int) $file['error'][$i] !== UPLOAD_ERR_OK) {
                continue;
            }

            $list[] = [
                'name' => $file['name'][$i],
                'type' => $file['type'][$i],
                'tmp_name' => $file['tmp_name'][$i],
                'error' => $file['error'][$i],
                'size' => $file['size'][$i],
            ];
        }

        return $list;
    }
}
