<?php
include '../../../config/config.php';
session_start();
include '../../../templates/middleware.php';
// canonical auth guard
$id = resolve_user_identifier();
if (empty($id)) { header('Location: ../../../login_form.php'); exit; }
// page-level permission enforcement (allow existing 'Bills Payment' holders too)
if (!function_exists('has_any_permission') || !has_any_permission(['TRL Entry','Bills Payment'])) { header('Location: ../../home.php'); exit; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>TRL - Entry</title>
    <link rel="icon" href="../../../images/MLW%20logo.png" type="image/png">
    <link rel="stylesheet" href="../../../assets/css/templates/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="trl-entry.css?v=<?php echo time(); ?>">
</head>
<body>
    <div class="main-container">
        <?php include '../../../templates/header_ui.php'; ?>
        <?php include '../../../templates/sidebar.php'; ?>

        <?php bp_section_header_html('fa-solid fa-pen-to-square', 'TRL - Entry', 'Transaction Request Log - Entry'); ?>

        <div class="bp-card container-fluid mt-3 p-4">
            <div class="row">
                <div class="col-12">
                    <div class="card p-3">
                        <p>This is the TRL Entry placeholder page.</p>
                    </div>
                </div>
            </div>
        </div>

        <?php include '../../../templates/footer.php'; ?>
    </div>
</body>
</html>