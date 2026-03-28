<?php
include '../../../config/config.php';
session_start();
include '../../../templates/middleware.php';

$id = resolve_user_identifier();
if (empty($id)) {
    header('Location: ../../../login_form.php');
    exit;
}

if (!function_exists('has_any_permission') || !has_any_permission(['TRL Import', 'Bills Payment'])) {
    header('Location: ../../home.php');
    exit;
}

$rows = $_SESSION['trl_import_rows'] ?? [];
$summary = $_SESSION['trl_import_summary'] ?? ['total_rows' => 0, 'duplicate_rows' => 0, 'unique_rows' => 0];
$flash = $_SESSION['trl_import_flash'] ?? null;
unset($_SESSION['trl_import_flash']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>TRL Import Preview</title>
    <link rel="icon" href="../../../images/MLW%20logo.png" type="image/png">
    <link rel="stylesheet" href="../../../assets/css/templates/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="trl-import-preview.css?v=<?php echo time(); ?>">
</head>
<body>
    <div class="main-container trl-preview-page">
        <?php include '../../../templates/header_ui.php'; ?>

        <main class="trl-preview-container">
            <div class="trl-preview-header">
                <div>
                    <h1>TRL Import Preview</h1>
                    <p>Review fetched rows before importing to mldb.trl.</p>
                </div>
                <div class="trl-preview-actions">
                    <a id="backToImport" class="btn btn-outline-secondary" href="trl-import.php">Back to Import</a>
                    <?php if (!empty($rows)): ?>
                    <form method="post" action="controllers/trl-import-insert.php" style="display:inline;">
                        <button type="submit" class="btn btn-danger">Import All</button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($flash): ?>
            <div class="trl-alert <?php echo htmlspecialchars($flash['type'] ?? 'info'); ?>">
                <?php echo htmlspecialchars($flash['message'] ?? ''); ?>
            </div>
            <?php endif; ?>

            <section class="trl-summary-grid">
                <div class="summary-card">
                    <span>Total Rows</span>
                    <strong><?php echo (int) ($summary['total_rows'] ?? 0); ?></strong>
                </div>
                <div class="summary-card">
                    <span>Duplicate Rows</span>
                    <strong><?php echo (int) ($summary['duplicate_rows'] ?? 0); ?></strong>
                </div>
                <div class="summary-card">
                    <span>Unique Rows</span>
                    <strong><?php echo (int) ($summary['unique_rows'] ?? 0); ?></strong>
                </div>
            </section>

            <section class="trl-table-section">
                <?php if (empty($rows)): ?>
                <div class="trl-empty">No fetched rows found. Upload a file in TRL Import first.</div>
                <?php else: ?>
                <div class="trl-table-wrap">
                    <table class="trl-table">
                        <thead>
                            <tr>
                                <th>TRANS. DATE/TIME</th>
                                <th>REF. NO.</th>
                                <th>WRONG BILLER ID</th>
                                <th>BILLER NAME</th>
                                <th>ACCOUNT NO.</th>
                                <th>NAME</th>
                                <th>PAYMENT BRANCH ID</th>
                                <th>PAYMENT BRANCH</th>
                                <th>AMOUNT</th>
                                <th>TYPE OF REQUEST</th>
                                <th>CORRECT BILLER ID</th>
                                <th>CORRECT BILLER NAME</th>
                                <th>REASON</th>
                                
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $row): ?>
                            <tr>
                                <td><?php echo htmlspecialchars((string) ($row['transfer_datetime'] ?? '')); ?></td>
                                <td><?php echo htmlspecialchars((string) ($row['ref_no'] ?? '')); ?></td>
                                <td><?php echo htmlspecialchars((string) ($row['wrong_biller_id'] ?? '')); ?></td>
                                <td><?php echo htmlspecialchars((string) ($row['biller_name'] ?? '')); ?></td>
                                <td><?php echo htmlspecialchars((string) ($row['account_no'] ?? '')); ?></td>
                                <td><?php echo htmlspecialchars((string) ($row['name'] ?? '')); ?></td>
                                <td><?php echo htmlspecialchars((string) ($row['payment_branch_id'] ?? '')); ?></td>
                                <td><?php echo htmlspecialchars((string) ($row['payment_branch'] ?? '')); ?></td>
                                <td class="amount"><?php echo number_format((float) ($row['amount'] ?? 0), 2); ?></td>
                                <td><?php echo htmlspecialchars((string) ($row['type_of_request'] ?? '')); ?></td>
                                <td><?php echo htmlspecialchars((string) ($row['correct_biller_id'] ?? '')); ?></td>
                                <td><?php echo htmlspecialchars((string) ($row['correct_biller_name'] ?? '')); ?></td>
                                <td><?php echo htmlspecialchars((string) ($row['reason'] ?? '')); ?></td>
                                
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </section>
        </main>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var back = document.getElementById('backToImport');
        if (!back) return;
        back.addEventListener('click', function(e) {
            e.preventDefault();
            Swal.fire({
                title: 'Are you sure?',
                html: 'Going back will cancel the current import. Do you want to continue?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Yes, go back',
                cancelButtonText: 'No, stay'
            }).then(function(result) {
                if (result.isConfirmed) {
                    window.location.href = back.getAttribute('href');
                }
            });
        });
    });
    </script>
</body>
</html>
