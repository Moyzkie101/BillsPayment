<?php
include '../../../config/config.php';
session_start();
include '../../../templates/middleware.php';
// canonical auth guard
$id = resolve_user_identifier();
if (empty($id)) { header('Location: ../../../login_form.php'); exit; }
// quick debug endpoint: show middleware info when requested by a superuser or
// a user with the appropriate maintenance permission. Do not rely on role.
if (isset($_GET['__showperms']) && ((isset($_SESSION['access_level']) && intval($_SESSION['access_level']) === -1) || (function_exists('has_permission') && has_permission('Access Levels')))) {
    header('Content-Type: application/json');
    echo json_encode(middleware_debug_info());
    exit;
}
// page-level permission enforcement (allow existing 'Bills Payment' holders too)
if (!function_exists('has_any_permission') || !has_any_permission(['TRL Import','Bills Payment'])) { header('Location: ../../home.php'); exit; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>TRL - Import</title>
    <link rel="icon" href="../../../images/MLW%20logo.png" type="image/png">
    <link rel="stylesheet" href="../../../assets/css/templates/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="trl-import.css?v=<?php echo time(); ?>">
</head>
<body>
    <div class="main-container">
        <?php include '../../../templates/header_ui.php'; ?>
        <?php include '../../../templates/sidebar.php'; ?>

        <?php bp_section_header_html('fa-solid fa-file-import', 'TRL - Import', 'Transaction Request Log - Import'); ?>

        <div class="bp-card container-fluid mt-3 p-4">
            <div class="row">
                <div class="col-12">
                    <div class="card p-3">
                        <p>This is the TRL Import placeholder page.</p>
                    </div>
                </div>
            </div>
        </div>

        <?php include '../../../templates/footer.php'; ?>
    </div>
</body>
</html>