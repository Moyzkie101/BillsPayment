<?php
// Connect to the database
include '../../../config/config.php';
require '../../../vendor/autoload.php';

// Start the session
session_start();


if (isset($_SESSION['user_type'])) {
    $current_user_email = '';
    if ($_SESSION['user_type'] === 'admin' && isset($_SESSION['admin_email'])) {
        $current_user_email = $_SESSION['admin_email'];
    } elseif ($_SESSION['user_type'] === 'user' && isset($_SESSION['user_email'])) {
        $current_user_email = $_SESSION['user_email'];
    }
}

// Fetch users from database using MySQLi


$users = [];
try {
    $query = "SELECT id_number, first_name, middle_name, last_name, email as username, user_type, status, last_online, date_created, created_by, modified_date, modified_by FROM mldb.user_form ORDER BY date_created DESC";
    $result = mysqli_query($conn, $query);
    
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $users[] = $row;
        }
        mysqli_free_result($result);
    } else {
        error_log("Database query error: " . mysqli_error($conn));
    }
} catch (Exception $e) {
    error_log("Database error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Signature | <?php if($_SESSION['user_type'] === 'admin' || $_SESSION['user_type'] === 'user') echo ucfirst($_SESSION['user_type']); else echo "Guest";?></title>
    <!-- custom CSS file link  -->
    <link rel="stylesheet" href="../../../assets/css/templates/style.css?v=<?php echo time(); ?>">
    <script src="https://kit.fontawesome.com/30b908cc5a.js" crossorigin="anonymous"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="../../../assets/js/sweetalert2.all.min.js"></script>

    <link rel="icon" href="../../../images/MLW logo.png" type="image/png">
</head>
<body>
    <div class="main-container">
        <?php include '../../../templates/header_ui.php'; ?>
        <!-- Show and Hide Side Nav Menu -->
        <?php include '../../../templates/sidebar.php'; ?>
        <div id="loading-overlay">
            <div class="loading-spinner"></div>
        </div>
        <div class="bp-section-header" role="region" aria-label="Page title">
            <div class="bp-section-title">
                <i class="fa-solid fa-layer-group" aria-hidden="true"></i>
                <div>
                    <h2>User Signature - (UNDER MAINTENANCE)</h2>
                    <p class="bp-section-sub">Manage user signatures and related settings.</p>
                </div>
            </div>
        </div>
        <div class="bp-card container-fluid mt-3 p-4">
            <!-- Your content goes here -->
            <div class="row">
                <div class="col-md-12">
                    <div class="card">
                    <div class="card-header">
                        <div class="input-group" style="display: flex; justify-content: space-between; align-items: center;">
                            <div class="input-group-append" style="display: flex; align-items: center; gap: 10px;">
                                <form action="" style="display: flex; align-items: center; gap: 10px;">
                                <input type="text" id="searchInput" class="form-control" placeholder="Search by any field..." style="width: 250px;">
                                <button type="button" id="clearFilters" class="btn btn-secondary">Clear</button>
                                </form>
                            </div>
                            <div class="input-group-append" style="display: flex; align-items: center; gap: 5px;">
                                <button type="button" class="btn btn-danger" data-bs-target="#addUserModal"><i class="fa fa-upload" disabled></i> Upload Signature</button>
                                <button type="button" class="btn btn-danger" data-bs-target="#editUserModal"><i class="fa fa-trash" disabled></i> Remove Signature</button>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <table class="table table-hover" id="users-table">
                            <thead>
                                <tr>
                                    <th>No.</th>
                                    <th>ID Number</th>
                                    <th>Full Name</th>
                                    <th>Signature Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($users)): ?>
                                <?php foreach ($users as $index => $user): ?>
                                    <tr data-user-id="<?php echo htmlspecialchars($user['id_number'] ?? ''); ?>"
                                        data-user-data='<?php echo json_encode($user); ?>'
                                        style="cursor: pointer;">
                                        <td><?php echo $index + 1; ?></td>
                                        <td><?php echo htmlspecialchars($user['id_number'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars(trim(implode(' ', array_filter([
                                            $user['first_name'] ?? '',
                                            $user['middle_name'] ?? '',
                                            $user['last_name'] ?? ''
                                        ], static fn($value) => $value !== null && trim((string)$value) !== '')))); ?></td>
                                        <td>No Signature</td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php else: ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">
                                        <i class="fa fa-users fa-2x mb-2"></i><br>
                                        No users found in the database
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                            <tfoot>
                            </tfoot>
                        </table>
                    </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
<?php include '../../../templates/footer.php'; ?>
</html>