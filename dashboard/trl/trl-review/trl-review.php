<?php
include '../../../config/config.php';
session_start();
include '../../../templates/middleware.php';

// Canonical auth guard
$id = resolve_user_identifier();
if (empty($id)) {
    header('Location: ../../../login_form.php');
    exit;
}

// Page-level permission enforcement
if (!function_exists('has_any_permission') || !has_any_permission(['TRL Review', 'Bills Payment'])) {
    header('Location: ../../home.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>TRL - Review</title>
    <link rel="icon" href="../../../images/MLW%20logo.png" type="image/png">
    <link rel="stylesheet" href="../../../assets/css/templates/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="trl-review.css?v=<?php echo time(); ?>">
</head>
<body>
    <div class="main-container">
        <?php include '../../../templates/header_ui.php'; ?>
        <?php include '../../../templates/sidebar.php'; ?>

        <?php bp_section_header_html('fa-solid fa-clipboard-check', 'TRL - Review', 'Review, update, and settle refunds for imported or encoded TRL records'); ?>

        <div class="bp-card container-fluid mt-3 p-4 trl-review-wrap">
            <div class="trl-review-topbar">
                <div class="trl-filters">
                    <div class="trl-field">
                        <label for="trl-search">Search TRL</label>
                        <input id="trl-search" type="text" placeholder="Reference no., account, or partner">
                    </div>
                    <div class="trl-field">
                        <label for="trl-source">Source</label>
                        <select id="trl-source">
                            <option value="">All sources</option>
                            <option value="import">Imported</option>
                            <option value="entry">Manual Entry</option>
                        </select>
                    </div>
                    <div class="trl-field">
                        <label for="trl-status">Refund Status</label>
                        <select id="trl-status">
                            <option value="">All status</option>
                            <option value="pending">Pending</option>
                            <option value="review">For Review</option>
                            <option value="settled">Settled</option>
                        </select>
                    </div>
                </div>
                <div class="trl-top-actions">
                    <button type="button" class="btn btn-outline-secondary">Reset Filters</button>
                    <button type="button" class="btn btn-danger">Apply Filters</button>
                </div>
            </div>

            <div class="trl-grid">
                <div class="trl-card">
                    <h3>TRL Records For Review</h3>
                    <p class="trl-muted">Select a record to review details, update values, and proceed with settlement.</p>

                    <div class="trl-table-wrap">
                        <table class="trl-table">
                            <thead>
                                <tr>
                                    <th>TRL Ref #</th>
                                    <th>Source</th>
                                    <th>Customer</th>
                                    <th>Amount</th>
                                    <th>Refund Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>TRL-2026-00041</td>
                                    <td>Import</td>
                                    <td>Juan Dela Cruz</td>
                                    <td class="amount">2,450.00</td>
                                    <td><span class="status-chip review">For Review</span></td>
                                    <td><button type="button" class="btn btn-sm btn-outline-danger">Open</button></td>
                                </tr>
                                <tr>
                                    <td>TRL-2026-00039</td>
                                    <td>Entry</td>
                                    <td>Maria Santos</td>
                                    <td class="amount">1,200.00</td>
                                    <td><span class="status-chip pending">Pending</span></td>
                                    <td><button type="button" class="btn btn-sm btn-outline-danger">Open</button></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="trl-card settlement">
                    <h3>Refund Settlement Panel</h3>
                    <p class="trl-muted">Use this panel to update TRL values and complete refund settlement after review.</p>

                    <div class="trl-field">
                        <label for="selected-trl">Selected TRL Ref #</label>
                        <input id="selected-trl" type="text" value="TRL-2026-00041" readonly>
                    </div>

                    <div class="trl-field">
                        <label for="review-notes">Review Notes</label>
                        <textarea id="review-notes" rows="4" placeholder="Add findings, corrections, or investigation notes"></textarea>
                    </div>

                    <div class="trl-field">
                        <label for="refund-amount">Refund Amount</label>
                        <input id="refund-amount" type="number" min="0" step="0.01" placeholder="0.00">
                    </div>

                    <div class="trl-field">
                        <label for="settlement-date">Settlement Date</label>
                        <input id="settlement-date" type="date">
                    </div>

                    <div class="trl-actions">
                        <button type="button" class="btn btn-outline-secondary">Save Update</button>
                        <button type="button" class="btn btn-danger">Settle Refund</button>
                    </div>
                </div>
            </div>
        </div>

        <?php include '../../../templates/footer.php'; ?>
    </div>
</body>
</html>
