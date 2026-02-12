<?php 
// Dynamic base path detection
function getBasePath() {
    // Get the protocol (http or https)
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    
    // Get the host
    $host = $_SERVER['HTTP_HOST'];
    
    // Get project folder name from PHP_SELF
    $phpSelf = $_SERVER['PHP_SELF'];
    $pathParts = explode('/', trim($phpSelf, '/'));
    $projectFolder = $pathParts[0]; // First directory is the project folder
    
    // Check if we're in a subfolder (like dashboard)
    $subFolder = '';
    if (count($pathParts) > 1 && $pathParts[1] === 'dashboard') {
        $subFolder = 'dashboard/';
    }
    
    // Use DOCUMENT_ROOT for sub folders
    $documentRoot = $_SERVER['DOCUMENT_ROOT'];
    
    // Get filename from SCRIPT_NAME
    $scriptName = $_SERVER['SCRIPT_NAME'];
    $filename = basename($scriptName);
    
    // Build the base path
    $basePath = str_replace('\\', '/', $documentRoot);
    
    // Normalize the base path
    if ($basePath === '/') {
        $basePath = '';
    }
    
    // Return the complete base URL with subfolder if present
    return $protocol . $host . '/' . $projectFolder . '/' . $subFolder;
}

// Function for logout URL (without dashboard subfolder)
function getAuthPath() {
    // Get the protocol (http or https)
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    
    // Get the host
    $host = $_SERVER['HTTP_HOST'];
    
    // Get project folder name from PHP_SELF
    $phpSelf = $_SERVER['PHP_SELF'];
    $pathParts = explode('/', trim($phpSelf, '/'));
    $projectFolder = $pathParts[0]; // First directory is the project folder
    
    // Return base URL without any subfolder for authentication
    return $protocol . $host . '/' . $projectFolder . '/';
}

// Get dynamic paths
$base_url = getBasePath();
$auth_url = getAuthPath();


if (isset($_SESSION['user_type']) && ($_SESSION['user_type'] === 'admin' || $_SESSION['user_type'] === 'user')): ?>
    <div id="sidemenu" class="sidemenu" style="display: none;">
        <!-- Home Button -->
        <div class="onetab" onclick="parent.location='<?php echo $base_url; ?>home.php'">
        <a href="<?php echo $base_url; ?>home.php"><i class="fa-solid fa-house"></i> Home</a>
        </div>

        <!-- Show/Hide Paramount -->
        <div class="onetab" id="para-btn">
            <h6><i class="fa-solid fa-money-bill-wave"></i> Bills Payment Transaction</h6>
            <i class="fa-solid fa-chevron-right" id="closed-para" style="display: block"></i>
            <i class="fa-solid fa-chevron-down" id="open-para" style="display: none"></i>
        </div>

        <?php if ($current_user_email === 'balb01013333' || $current_user_email === 'pera94005055' || $current_user_email === 'cill17098209'):
        else:
        ?>

        <!-- Show/Hide Paramount Import -->
        <div class="tabcat" id="para-import-btn" style="display: none;">
            <h6><i class="fa-solid fa-file-import"></i> Import</h6>
            <i class="fa-solid fa-chevron-right" id="closed-para-import" style="display: block"></i>
            <i class="fa-solid fa-chevron-down" id="open-para-import" style="display: none"></i>
        </div>
        <?php endif; ?>

            <!-- Paramount Import Buttons -->
            <div class="onetab-sub" id="para-import-nav" style="display: none;">
                <div class="sub" onclick="parent.location='<?php echo $base_url; ?>billspayment/import/billspay-transaction.php'">
                    <a href="<?php echo $base_url; ?>billspayment/import/billspay-transaction.php"><i class="fa-solid fa-receipt"></i> Transaction</a>
                </div>
                <!-- <div class="sub">
                    <a href="#" id="cancellation-link"><i class="fa-solid fa-ban"></i> Cancellation</a>
                </div> -->

                <div class="sub" onclick="parent.location='<?php echo $base_url; ?>billspayment/import/billspay-cancellation.php'">
                    <a href="<?php echo $base_url; ?>billspayment/import/billspay-cancellation.php">Cancellation</a>
                </div>

                <!-- <div class="sub" onclick="parent.location='<?php //echo $base_url; ?>billsFeedback.php'">
                    <a href="<?php //echo $base_url; ?>billsFeedback.php">Feedback</a>
                </div> -->
            </div>

        <?php if ($_SESSION['user_type'] === 'admin'):?>
            <!-- Show/Hide Paramount Post -->
            <div class="tabcat" id="para-post-btn" style="display: none;">
                <h6><i class="fa-solid fa-paper-plane"></i> Post</h6>
                <i class="fa-solid fa-chevron-right" id="closed-para-post" style="display: block"></i>
                <i class="fa-solid fa-chevron-down" id="open-para-post" style="display: none"></i>
            </div>
    
                <!-- Paramount Post Buttons -->
                <div class="onetab-sub"  id="para-post-nav" style="display: none;">
                    <!-- <div class="sub">
                        <a href="#" id="post-transaction-link">Transaction</a>
                    </div> -->

                    <!-- recycle if needed -->
                    <div class="sub" onclick="parent.location='<?php echo $base_url; ?>billspayment/post/billspay-post-transaction.php'">
                        <a href="<?php echo $base_url; ?>billspayment/post/billspay-post-transaction.php"><i class="fa-solid fa-check-to-slot"></i> Transaction</a>
                    </div>

                    <!-- <div class="sub" onclick="parent.location='<?php //echo $base_url; ?>billsFeedback.php'">
                        <a href="<?php //echo $base_url; ?>billsFeedback.php">Feedback</a>
                    </div> -->
                </div>
        <?php endif; ?>

        <!-- Show/Hide Paramount Report -->
        <div class="tabcat" id="para-report-btn" style="display: none;">
            <h6><i class="fa-solid fa-chart-line"></i> Report</h6>
            <i class="fa-solid fa-chevron-right" id="closed-para-report" style="display: block"></i>
            <i class="fa-solid fa-chevron-down" id="open-para-report" style="display: none"></i>
        </div>

        <!-- Paramount Report Buttons -->
        <div class="onetab-sub" id="para-report-nav" style="display: none;">
            <!-- <div class="sub" onclick="parent.location='<?php //echo $base_url; ?>billspayment/report/daily-volume.php'">
                <a href="<?php //echo $base_url; ?>billspayment/report/daily-volume.php">Volume Report</a>
            </div> -->
            <div class="sub" onclick="parent.location='<?php echo $base_url; ?>billspayment/report/volume-report.php'">
                <a href="<?php echo $base_url; ?>billspayment/report/volume-report.php"><i class="fa-solid fa-chart-column"></i> Volume Report</a>
            </div>
            <div class="sub" onclick="parent.location='<?php echo $base_url; ?>billspayment/report/edi-report.php'">
                <a href="<?php echo $base_url; ?>billspayment/report/edi-report.php"><i class="fa-solid fa-file-lines"></i> EDI Report</a>
            </div>
            <div class="sub" onclick="parent.location='<?php echo $base_url; ?>billspayment/report/transaction-report.php'">
                <a href="<?php echo $base_url; ?>billspayment/report/transaction-report.php"><i class="fa-solid fa-list-check"></i> Transaction Report (Details)</a>
            </div>
            <div class="sub" onclick="parent.location='<?php echo $base_url; ?>billspayment/report/transaction-summary.php'">
                <a href="<?php echo $base_url; ?>billspayment/report/transaction-summary.php"><i class="fa-solid fa-table"></i> Transaction Report (Summary)</a>
            </div>
            <!-- <div class="sub">
                <a href="#" id="transaction-report-summary-link">Transaction Report (Summary)</a>
            </div> -->
            <div class="sub">
                <a href="#" id="cancellation-report-link"><i class="fa-solid fa-circle-xmark"></i> Cancellation Report</a>
            </div>
            <!-- <div class="sub" onclick="parent.location='<?php //echo $base_url; ?>billspayment/report/monthly-volume.php'">
                <a href="<?php //echo $base_url; ?>billspayment/report/monthly-volume.php">Monthly Volume Report</a>
            </div> -->
            <!-- <div class="sub" onclick="parent.location='<?php //echo $base_url; ?>date/date-filter-billsPayment.php'">
                <a href="<?php //echo $base_url; ?>date/date-filter-billsPayment.php">BP Transaction (Cancelled and Good)</a>
            </div>
            <div class="sub" onclick="parent.location='<?php //echo $base_url; ?>date/date-good-only.php'">
                <a href="<?php //echo $base_url; ?>date/date-good-only.php">BP Transaction (Good Only)</a>
            </div>
            <div class="sub" onclick="parent.location='<?php //echo $base_url; ?>date/date-cancelled-only.php'">
                <a href="<?php //echo $base_url; ?>date/date-cancelled-only.php">BP Transaction (Cancelled Only)</a>
            </div>
            <div class="sub" onclick="parent.location='<?php //echo $base_url; ?>date/date-duplicate-report.php'">
                <a href="<?php //echo $base_url; ?>date/date-duplicate-report.php">BP Transaction (Duplicate/Split Transaction)</a>
            </div> -->
        </div>

        <!-- <div class="tabcat" id="action-report-btn" style="display: none;">
            <i class="fa-solid fa-chevron-right" id="closed-action-report" style="display: block"></i>
            <i class="fa-solid fa-chevron-down" id="open-action-report" style="display: none"></i>
            <h6>Action Taken / Log Files</h6>
        </div> -->

        <div class="onetab-sub" id="action-report-nav" style="display: none;">
        <div class="sub" onclick="parent.location='<?php echo $base_url; ?>ActionLog.php'">
            <a href="<?php echo $base_url; ?>ActionLog.php">Add Logs</a>
        </div>
        <div class="sub" onclick="parent.location='<?php echo $base_url; ?>actionLogReport.php'">
            <a href="<?php echo $base_url; ?>actionLogReport.php">Action Log Reports</a>
        </div>
        </div>

        <!-- Show/Hide MAA -->
        <!-- <div class="onetab" id="maa-btn">
        <i class="fa-solid fa-caret-right" id="closed-maa" style="display: block"></i>
        <i class="fa-solid fa-caret-down" id="open-maa" style="display: none"></i>
        <h6>Bookkeeper</h6>
        </div> -->

        <div class="onetab-sub" id="maa-nav" style="display: none;">
        <div class="sub" onclick="parent.location='#'">
            <a href="#">Bookkeeper Import</a>
        </div>
        <div class="sub" onclick="parent.location='#'">
            <a href="#">Book keeper Report</a>
        </div>
        </div>

        
        <!-- Show/Hide Paramount -->
        <div class="onetab" id="soa-btn">
            <h6><i class="fa-solid fa-file-invoice-dollar"></i> Billing Invoice</h6>
            <i class="fa-solid fa-chevron-right" id="closed-soa" style="display: block"></i>
            <i class="fa-solid fa-chevron-down" id="open-soa" style="display: none"></i>
        </div>
        <!-- Show/Hide soa create Sub-menu -->
        <?php if ($current_user_email === 'balb01013333' || $current_user_email === 'pera94005055' || $current_user_email === 'cill17098209'):
        else:
        ?>
            <div class="tabcat" id="soa-create-btn" style="display: none;">
                <h6><i class="fa-solid fa-plus-circle"></i> Create</h6>
                <i class="fa-solid fa-chevron-right" id="closed-soa-create" style="display: block"></i>
                <i class="fa-solid fa-chevron-down" id="open-soa-create" style="display: none"></i>
            </div>
        <?php endif; ?>

        <!-- soa create Buttons -->
        <div class="onetab-sub" id="soa-create-nav" style="display: none;">
            <div class="sub" onclick="parent.location='<?php echo $base_url; ?>billspayment-soa/create/billing-service-charge.php'">
                <a href="<?php echo $base_url; ?>billspayment-soa/create/billing-service-charge.php"><i class="fa-solid fa-hand-holding-dollar"></i> Service Charge (MANUAL)</a>
            </div>
            <!-- recycle if needed -->
            <!-- <div class="sub">
                <a href="#" id="service-charge-automate-link">Service Charge (AUTOMATED)</a>
            </div> -->
			
            <div class="sub" onclick="parent.location='<?php echo $base_url; ?>billspayment-soa/create/billing-invoice-service-charge_automated.php'">
                <a href="<?php echo $base_url; ?>billspayment-soa/create/billing-invoice-service-charge_automated.php"><i class="fa-solid fa-gears"></i> Service Charge (AUTOMATED)</a>
            </div>

        </div>

        <?php if ($current_user_email === 'balb01013333' || $current_user_email === 'pera94005055'):
        else:
        ?>
            <?php if ($current_user_email === 'cill17098209' || $_SESSION['user_type'] === 'admin'):?>
                <!-- Show/Hide soa review Sub-menu -->
                <div class="tabcat" id="soa-review-btn" style="display: none;">
                    <h6><i class="fa-solid fa-clipboard-check"></i> Review</h6>
                    <i class="fa-solid fa-chevron-right" id="closed-soa-review" style="display: block"></i>
                    <i class="fa-solid fa-chevron-down" id="open-soa-review" style="display: none"></i>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- soa review Buttons -->
        <div class="onetab-sub" id="soa-review-nav" style="display: none;">
            <div class="sub" onclick="parent.location='<?php echo $base_url; ?>billspayment-soa/review/for-checking-review.php'">
                <a href="<?php echo $base_url; ?>billspayment-soa/review/for-checking-review.php"><i class="fa-solid fa-magnifying-glass-chart"></i> For Checking / Review</a>
            </div>
        </div>

        <?php if ($current_user_email === 'balb01013333' || $current_user_email === 'pera94005055' || $_SESSION['user_type'] === 'admin'):?>

            <!-- Show/Hide soa approval Sub-menu -->
            <div class="tabcat" id="soa-approval-btn" style="display: none;">
                <h6><i class="fa-solid fa-certificate"></i> Approval</h6>
                <i class="fa-solid fa-chevron-right" id="closed-soa-approval" style="display: block"></i>
                <i class="fa-solid fa-chevron-down" id="open-soa-approval" style="display: none"></i>
            </div>
        <?php endif; ?>

        <!-- soa approval Buttons -->
        <div class="onetab-sub" id="soa-approval-nav" style="display: none;">
            <div class="sub" onclick="parent.location='<?php echo $base_url; ?>billspayment-soa/approval/soa-approval.php'">
                <a href="<?php echo $base_url; ?>billspayment-soa/approval/soa-approval.php"><i class="fa-solid fa-check-double"></i> Billing Invoice Approval</a>
            </div>
        </div>

        <!-- Show/Hide soa report Sub-menu -->
        <div class="tabcat" id="soa-report-btn" style="display: none;">
            <h6><i class="fa-solid fa-chart-pie"></i> Report</h6>
            <i class="fa-solid fa-chevron-right" id="closed-soa-report" style="display: block"></i>
            <i class="fa-solid fa-chevron-down" id="open-soa-report" style="display: none"></i>
        </div>

        <!-- soa report Buttons -->
        <div class="onetab-sub" id="soa-report-nav" style="display: none;">
            <div class="sub" onclick="parent.location='<?php echo $base_url; ?>billspayment-soa/report/soa-report.php'">
                <a href="<?php echo $base_url; ?>billspayment-soa/report/soa-report.php"><i class="fa-solid fa-file-contract"></i> Billing Invoice Report</a>
            </div>
        </div>

        <?php if (isset($_SESSION['user_type']) && ($_SESSION['user_type'] === 'admin')): ?>
            <!-- Show/Hide Set Maintenance Main-menu -->
            <div class="onetab" id="set-btn">
            <h6><i class="fa-solid fa-wrench"></i> Maintenance</h6>
            <i class="fa-solid fa-chevron-right" id="closed-set" style="display: block"></i>
            <i class="fa-solid fa-chevron-down" id="open-set" style="display: none"></i>
            </div>

            <!-- Show/Hide Set maintenance Sub-menu -->
            <div class="tabcat" id="set-maintenance-btn" style="display: none;">
                <h6><i class="fa-solid fa-users-gear"></i> Accounts</h6>
                <i class="fa-solid fa-chevron-right" id="closed-set-maintenance" style="display: block"></i>
                <i class="fa-solid fa-chevron-down" id="open-set-maintenance" style="display: none"></i>
            </div>

            <!-- Set Maintenance Buttons -->
            <div class="onetab-sub" id="set-maintenance-nav" style="display: none;">
                <div class="sub" onclick="parent.location='<?php echo $base_url; ?>maintenance/accounts/user-management.php'">
                    <a href="<?php echo $base_url; ?>maintenance/accounts/user-management.php"><i class="fa-solid fa-user-cog"></i> User Management</a>
                </div>
            </div>

            <!-- Show/Hide Set duplicates Sub-menu -->
            <div class="tabcat" id="set-duplicate-btn" style="display: none;">
                <h6><i class="fa-solid fa-code-compare"></i> Duplicate</h6>
                <i class="fa-solid fa-chevron-right" id="closed-set-duplicate" style="display: block"></i>
                <i class="fa-solid fa-chevron-down" id="open-set-duplicate" style="display: none"></i>
            </div>

            <!-- Set Duplicate Buttons -->
            <div class="onetab-sub" id="set-duplicate-nav" style="display: none;">
                <div class="sub" onclick="parent.location='<?php echo $base_url; ?>billspayment/import/duplicate-transaction.php'">
                    <a href="<?php echo $base_url; ?>billspayment/import/duplicate-transaction.php"><i class="fa-solid fa-receipt"></i> Transaction</a>
                </div>
            </div>
        <?php endif; ?>

        <!-- Logout Button -->
        <div class="onetab" onclick="parent.location='<?php echo $auth_url; ?>logout.php'">
        <a href="<?php echo $auth_url; ?>logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
        </div>
    </div>
<?php else: ?>
    <?php header("Location:" . $auth_url); session_destroy(); exit(); ?>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// Function to show under construction alert
function showUnderConstructionAlert() {
    Swal.fire({
        icon: 'info',
        title: 'Feature Under Development',
        text: 'This feature is currently under construction. Please check back later!',
        confirmButtonText: 'Got it!',
        confirmButtonColor: '#3085d6'
    });
}

// Array of IDs for features under construction
const underConstructionIds = [
    'cancellation-link',
    'post-transaction-link',
    'transaction-report-summary-link',
    'cancellation-report-link',
    'service-charge-automate-link'
];

// Add event listeners to all under construction features
document.addEventListener('DOMContentLoaded', function() {
    underConstructionIds.forEach(function(id) {
        const element = document.getElementById(id);
        if (element) {
            // Add event listener to both the link and its parent div
            element.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation(); // Prevent parent onclick from firing
                showUnderConstructionAlert();
            });
            
            // Also add to parent div if it exists
            const parentDiv = element.closest('.sub');
            if (parentDiv) {
                parentDiv.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    showUnderConstructionAlert();
                });
            }
        }
    });

    // Active state management based on current URL
    const currentPath = window.location.pathname;
    const currentPage = currentPath.split('/').pop();
    
    // Highlight active menu items
    document.querySelectorAll('.sub a').forEach(function(link) {
        const linkHref = link.getAttribute('href');
        if (linkHref && linkHref.includes(currentPage) && currentPage !== '') {
            // Add active class to the sub menu item
            link.closest('.sub').classList.add('active');
            
            // Find and expand parent categories
            let parent = link.closest('.onetab-sub');
            if (parent) {
                parent.style.display = 'block';
                
                // Find the parent tabcat button and mark it active
                const parentId = parent.id.replace('-nav', '-btn');
                const parentBtn = document.getElementById(parentId);
                if (parentBtn) {
                    parentBtn.classList.add('active');
                    // Show the down arrow, hide the right arrow
                    const openIcon = parentBtn.querySelector('[id^="open-"]');
                    const closedIcon = parentBtn.querySelector('[id^="closed-"]');
                    if (openIcon) openIcon.style.display = 'block';
                    if (closedIcon) closedIcon.style.display = 'none';
                }
                
                // Find the main menu parent and mark it active
                let mainParent = parent.previousElementSibling;
                while (mainParent && !mainParent.classList.contains('onetab')) {
                    mainParent = mainParent.previousElementSibling;
                }
                if (mainParent && mainParent.classList.contains('onetab')) {
                    mainParent.classList.add('active');
                    // Show the down arrow, hide the right arrow
                    const openIcon = mainParent.querySelector('[id^="open-"]');
                    const closedIcon = mainParent.querySelector('[id^="closed-"]');
                    if (openIcon) openIcon.style.display = 'block';
                    if (closedIcon) closedIcon.style.display = 'none';
                }
            }
        }
    });

    // Check for direct onetab links (like Home)
    document.querySelectorAll('.onetab a').forEach(function(link) {
        const linkHref = link.getAttribute('href');
        if (linkHref && linkHref.includes(currentPage) && currentPage !== '') {
            link.closest('.onetab').classList.add('active');
        }
    });

    // Special case for home.php
    if (currentPage === 'home.php' || currentPage === '') {
        const homeBtn = document.querySelector('.onetab a[href*="home.php"]');
        if (homeBtn) {
            homeBtn.closest('.onetab').classList.add('active');
        }
    }
});
</script>