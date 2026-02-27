
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

// dropdown queries for partner list
$partnersQuery = "SELECT partner_name FROM masterdata.partner_masterfile ORDER BY partner_name";
$partnersResult = $conn->query($partnersQuery);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import Cancellation | <?php if($_SESSION['user_type'] === 'admin' || $_SESSION['user_type'] === 'user') echo ucfirst($_SESSION['user_type']); else echo "Guest";?></title>
    <!-- custom CSS file link  -->
    <link rel="stylesheet" href="../../../assets/css/templates/style.css?v=<?php echo time(); ?>">
    <script src="https://kit.fontawesome.com/30b908cc5a.js" crossorigin="anonymous"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="../../../assets/js/sweetalert2.all.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>

    <link rel="icon" href="../../../images/MLW logo.png" type="image/png">
    <style>
       /* Print styles */
        @media print {
            body * {
                visibility: hidden;
                visibility: visible;
            }
            .alert-warning {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                border: none !important;
                background-color: white !important;
                color: black !important;
            }
            .alert-warning .d-flex {
                display: none !important;
            }
            .alert-warning h4 {
                text-align: center;
                font-size: 18px;
                margin-bottom: 15px;
            }
            .alert-warning p {
                text-align: center;
                margin-bottom: 15px;
            }
            /* Make sure the table-responsive container shows all content */
            .table-responsive {
                max-height: none !important;
                height: auto !important;
                overflow: visible !important;
            }
            .table {
                width: 100%;
                border-collapse: collapse;
                page-break-inside: auto;
            }
            .table th, .table td {
                border: 1px solid #000;
            }
            .table tr {
                page-break-inside: avoid;
                page-break-after: auto;
            }
            .sticky-top {
                position: static;
            }
        }

        
        /* Enhanced SweetAlert2 backdrop for confidentiality */
        .swal2-container.swal2-backdrop-show {
            backdrop-filter: blur(10px);
            background-color: rgba(0,0,0,0.8) !important;
        }
        
        /* Make sure the modal itself is still clear */
        .swal2-popup {
            backdrop-filter: none !important;
            box-shadow: 0 0 20px rgba(0,0,0,0.5);
        }
        

        /* File Upload Area Styles */
       .file-upload-area {
            border: 2px dashed rgba(220,53,69,0.16);
            border-radius: 10px;
            padding: 34px 18px;
            text-align: center;
            background: #fff;
            transition: all 180ms ease;
            cursor: pointer;
            user-select: none;
        }

        .file-upload-area.drag-over { background:#fff5f5; transform: translateY(-4px); box-shadow: 0 10px 20px rgba(220,53,69,0.06); border-color:#dc3545; }

        .file-upload-icon i { font-size:36px; color:#dc3545; margin-bottom:8px; }
        .file-upload-area h5 { margin:8px 0 4px; font-weight:700; }
        .file-upload-area p { margin:0; color:#6c757d; }
        /* Mode card selector (match transaction UI) */
        .mode-cards { display:flex; gap:8px; align-items:center; }
        .mode-card {
            border: 1px solid #e9ecef;
            padding: 8px 12px;
            border-radius: 8px;
            cursor: pointer;
            min-width: 120px;
            text-align: left;
            background: #fff;
            transition: all 120ms ease;
            box-shadow: 0 1px 2px rgba(0,0,0,0.03);
            display:flex;
            flex-direction:row;
            align-items:center;
            gap:10px;
        }
        .mode-card .mode-icon { font-size:18px; color:#6c757d; width:28px; text-align:center; }
        .mode-card .mode-text { display:flex; flex-direction:column; }
        .mode-card .mode-label { font-weight:700; margin:0; font-size:13px; }
        .mode-card small { color:#6c757d; display:block; font-size:11px; }
        .mode-card.selected { border-color: #dc3545; box-shadow: 0 8px 24px rgba(220,53,69,0.06); }
        .mode-card.selected .mode-icon { color:#dc3545; }

        .file-upload-area.drag-over {
            border-color: #dc3545;
            background-color: #ffe5e5;
        }

        .file-upload-area:hover {
            border-color: #dc3545;
            background-color: #fff;
        }

        .file-upload-icon {
            font-size: 48px;
            color: #6c757d;
            margin-bottom: 15px;
        }

        /* File Cards Container */
        .files-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }

        /* Individual File Card */
        .file-card {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            background-color: #fff;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            display: flex;
            flex-direction: column;
            gap: 10px;
            transition: all 0.3s ease;
        }

        .file-card:hover {
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }

        .file-card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 1px solid #dee2e6;
            padding-bottom: 10px;
        }

        .file-card-info {
            flex: 1;
        }

        .file-card-label {
            font-size: 11px;
            color: #6c757d;
            text-transform: uppercase;
            font-weight: 600;
            margin-bottom: 3px;
        }

        .file-card-value {
            font-size: 14px;
            color: #212529;
            font-weight: 500;
            word-break: break-word;
        }

        .file-card-delete {
            cursor: pointer;
            color: #dc3545;
            font-size: 20px;
            transition: all 0.2s ease;
        }

        .file-card-delete:hover {
            color: #bb2d3b;
            transform: scale(1.1);
        }

        .file-card-body {
            display: flex;
            gap: 15px;
        }

        .file-card-detail {
            flex: 1;
        }

        .badge-source {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-kpx {
            background-color: #0d6efd;
            color: white;
        }

        .badge-kp7 {
            background-color: #198754;
            color: white;
        }

        /* Tooltip for partner name */
        .partner-tooltip {
            position: relative;
            cursor: help;
            display: inline-block;
        }

        .partner-tooltip .tooltip-text {
            visibility: hidden;
            width: 200px;
            background-color: #212529;
            color: #fff;
            text-align: center;
            border-radius: 6px;
            padding: 8px;
            position: absolute;
            z-index: 1000;
            bottom: 125%;
            left: 50%;
            margin-left: -100px;
            opacity: 0;
            transition: opacity 0.3s;
            font-size: 12px;
        }

        .partner-tooltip .tooltip-text::after {
            content: "";
            position: absolute;
            top: 100%;
            left: 50%;
            margin-left: -5px;
            border-width: 5px;
            border-style: solid;
            border-color: #212529 transparent transparent transparent;
        }

        .partner-tooltip:hover .tooltip-text {
            visibility: visible;
            opacity: 1;
        }

        /* Proceed Button Container (top-right, sticky) */
        .proceed-container {
            margin-top: 0;
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 12px;
            position: sticky;
            top: 12px;
            z-index: 1050;
        }

        .btn-proceed {
            min-width: 200px;
            padding: 12px 30px;
            font-size: 16px;
            font-weight: 600;
        }

        /* Loading Overlay */
        #loading-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            z-index: 9999;
            justify-content: center;
            align-items: center;
        }

        .loading-spinner {
            border: 5px solid #f3f3f3;
            border-top: 5px solid #dc3545;
            border-radius: 50%;
            width: 60px;
            height: 60px;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .file-name-display {
            font-size: 12px;
            color: #6c757d;
            margin-top: 3px;
        }

        .empty-state {
            text-align: center;
            padding: 20px;
            color: #6c757d;
        }
        
        /* Page header, card and upload area - match transaction UI */
        .bp-section-header {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 18px;
            background: #ffffff;
            border-radius: 8px;
            color: #212529;
            margin: 18px 0 8px;
            box-shadow: 0 6px 18px rgba(16,24,40,0.04);
            border-left: 4px solid #dc3545;
        }

    .bp-section-title { display:flex; align-items:center; gap:12px; }
        .bp-section-title i { font-size:32px; color: #dc3545; }
        .bp-section-title h2 { margin:0; font-size:20px; color:#212529; font-weight:700; }
        .bp-section-sub { margin:0; font-size:13px; color:#6c757d; }
        .bp-card { background:#ffffff; border-radius:8px; box-shadow:0 6px 18px rgba(0,0,0,0.04); border:1px solid #f1f1f1; }
    </style>
</head>
<body>
    <div class="main-container">
        <!-- Show and Hide Side Nav Menu -->
        <?php include '../../../templates/header_ui.php'; ?>
        <?php include '../../../templates/sidebar.php'; ?>
        <div id="loading-overlay">
            <div class="loading-spinner"></div>
        </div>
        <div class="bp-section-header">
            <div class="bp-section-title">
                <i class="fa-solid fa-cloud-arrow-up" aria-hidden="true"></i>
                <div>
                    <h2>Import Cancellation</h2>
                    <p class="bp-section-sub">Upload Excel files (.xls, .xlsx) for processing</p>
                </div>
            </div>
        </div>
        <div class="bp-card container-fluid mt-3 p-4">
            <div class="bp-card-body">
                <!-- Mode Toggle (Auto / Manual) + Proceed (moved to top-right) -->
                <div class="mb-3 d-flex align-items-center justify-content-between" style="gap:12px;">
                    <div class="d-flex align-items-center" style="gap:12px;">
                        <label class="form-label me-2 mb-0">Import Mode:</label>
                        <div class="mode-cards">
                                <label class="mode-card selected" data-mode="auto">
                                    <input type="radio" name="importMode" id="modeAuto" value="auto" checked style="display:none;">
                                    <div class="mode-icon"><i class="fa-solid fa-cloud-arrow-up"></i></div>
                                    <div class="mode-text">
                                        <div class="mode-label">Auto</div>
                                        <small>Drag &amp; Drop</small>
                                    </div>
                                </label>
                                <label class="mode-card" data-mode="manual">
                                    <input type="radio" name="importMode" id="modeManual" value="manual" style="display:none;">
                                    <div class="mode-icon"><i class="fa-solid fa-file-lines"></i></div>
                                    <div class="mode-text">
                                        <div class="mode-label">Manual</div>
                                        <small>Form Upload</small>
                                    </div>
                                </label>
                        </div>
                    </div>

                    <div id="proceedContainer" class="proceed-container" style="display: none;">
                        <button type="button" class="btn btn-danger btn-proceed" id="proceedBtn">
                            <i class="fa-solid fa-paper-plane me-2" aria-hidden="true"></i>Proceed
                        </button>
                    </div>
                </div>

                <!-- Drag and Drop Upload Area -->
                <div class="file-upload-area" id="fileUploadArea">
                    <div class="file-upload-icon">
                        <i class="fa-solid fa-cloud-arrow-up"></i>
                    </div>
                    <h5>Drag &amp; Drop Files Here</h5>
                    <p class="text-muted">or click to browse</p>
                    <p class="text-muted"><small>Supports multiple Excel files (.xls, .xlsx)</small></p>
                    <input type="file" id="fileInput" accept=".xls,.xlsx" multiple style="display: none;">
                </div>
                <!-- Manual Import Area (hidden by default) - transaction-style -->
                <div id="manualArea" style="display:none;">
                    <form id="manualUploadForm" action="../../../models/saved/saved_billspayImportCancelledFile.php" method="post" enctype="multipart/form-data">
                        <input type="hidden" name="upload" value="1">
                        <div class="row mt-3">
                            <div class="col-md-5 mb-3">
                                <div class="d-flex align-items-center">
                                    <label class="form-label me-2 mb-0">Partners Name:</label>
                                    <input list="manualCompanyList" id="manualCompanyInput" name="partner_name" class="form-control" placeholder="Search or type company name" required />
                                    <datalist id="manualCompanyList">
                                        <?php
                                            if ($partnersResult && mysqli_num_rows($partnersResult) > 0) {
                                                // populate datalist options
                                                mysqli_data_seek($partnersResult, 0);
                                                while ($row = mysqli_fetch_assoc($partnersResult)) {
                                                    $partner_names = htmlspecialchars($row['partner_name']);
                                                    echo "<option value=\"{$partner_names}\"></option>\n";
                                                }
                                            }
                                        ?>
                                    </datalist>
                                </div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <div class="d-flex align-items-center">
                                    <label for="manualFileType" class="form-label me-2 mb-0">Source File Type:</label>
                                    <select id="manualFileType" class="form-select" name="fileType" required>
                                        <option value="">Select Source File Type</option>
                                        <option value="KPX">KPX</option>
                                        <option value="KP7">KP7</option>
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-4 mb-3 d-flex">
                                <input type="file" name="import_file" accept=".xls,.xlsx" class="form-control me-2" required />
                                <input type="submit" class="btn btn-danger" id="manualProceed" value="Proceed">
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body><?php include '../../../templates/footer.php'; ?>
<!-- Manual input uses datalist; no Select2 init required -->

<!-- IMPORT MODE RADIO BUTTONS -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const modeAuto = document.getElementById('modeAuto');
    const modeManual = document.getElementById('modeManual');
    const manualArea = document.getElementById('manualArea');
    const fileUploadArea = document.getElementById('fileUploadArea');
    const filesContainer = document.getElementById('filesContainer');
    const proceedContainer = document.getElementById('proceedContainer');

    function applyManualTemplate() {
        if (manualArea) manualArea.style.display = 'block';
        if (fileUploadArea) fileUploadArea.style.display = 'none';
        if (filesContainer) filesContainer.style.display = 'none';
        if (proceedContainer) proceedContainer.style.display = 'none';
    }

    function applyAutoTemplate() {
        if (manualArea) manualArea.style.display = 'none';
        if (fileUploadArea) fileUploadArea.style.display = 'block';
        if (filesContainer) filesContainer.style.display = '';
        if (proceedContainer) proceedContainer.style.display = 'none';
    }

    function updateMode() {
        if (modeManual && modeManual.checked) {
            applyManualTemplate();
        } else {
            applyAutoTemplate();
        }
    }

    if (modeAuto) modeAuto.addEventListener('change', updateMode);
    if (modeManual) modeManual.addEventListener('change', updateMode);

    // initialize on load
    updateMode();
});
</script>

<script>
// mode-card click handling to keep visuals and radios in sync
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.mode-card').forEach(function(card) {
        card.addEventListener('click', function(e) {
            var input = card.querySelector('input[type="radio"]');
            if (input) {
                input.checked = true;
                input.dispatchEvent(new Event('change'));
            }
            document.querySelectorAll('.mode-card').forEach(function(c){ c.classList.remove('selected'); });
            card.classList.add('selected');
        });
    });

    // ensure selected class matches radio initial state
    var checked = document.querySelector('input[name="importMode"]:checked');
    if (checked) {
        var parent = checked.closest('.mode-card');
        if (parent) {
            document.querySelectorAll('.mode-card').forEach(function(c){ c.classList.remove('selected'); });
            parent.classList.add('selected');
        }
    }
});
</script>

<!-- Drag and Drop File Upload under the Developer Area -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const fileUploadArea = document.getElementById('fileUploadArea');
        const fileInput = document.getElementById('fileInput');

        if (!fileUploadArea) return;

        // Visual drag states
        ['dragenter', 'dragover'].forEach(evt => {
            fileUploadArea.addEventListener(evt, function(e) {
                e.preventDefault();
                e.stopPropagation();
                fileUploadArea.classList.add('drag-over');
            });
        });

        ['dragleave', 'dragend'].forEach(evt => {
            fileUploadArea.addEventListener(evt, function(e) {
                e.preventDefault();
                e.stopPropagation();
                fileUploadArea.classList.remove('drag-over');
            });
        });

        // Handle dropped files
        fileUploadArea.addEventListener('drop', function(e) {
            e.preventDefault();
            e.stopPropagation();
            fileUploadArea.classList.remove('drag-over');

            const dt = e.dataTransfer;
            const files = dt ? dt.files : null;
            if (files && files.length > 0) {
                // Build a short HTML list of filenames
                let listHtml = `<p>${files.length} file(s) detected.</p><ul style="text-align:left; margin-left:1.1rem;">`;
                for (let i = 0; i < files.length; i++) {
                    listHtml += `<li>${files[i].name}</li>`;
                }
                listHtml += `</ul>`;

                // Show SweetAlert2 notice (Under Development)
                Swal.fire({
                    title: 'Under Development Area',
                    html: listHtml + '<p>This import/cancellation drag-and-drop feature is currently under development and is read-only.</p>',
                    icon: 'info',
                    confirmButtonText: 'OK'
                });
            }
        });

        // Allow clicking the area to open file picker
        fileUploadArea.addEventListener('click', function() {
            if (fileInput) fileInput.click();
        });

        // If files are selected via the input, show same alert
        if (fileInput) {
            fileInput.addEventListener('change', function(e) {
                const files = e.target.files;
                if (files && files.length > 0) {
                    let listHtml = `<p>${files.length} file(s) selected.</p><ul style="text-align:left; margin-left:1.1rem;">`;
                    for (let i = 0; i < files.length; i++) listHtml += `<li>${files[i].name}</li>`;
                    listHtml += `</ul>`;

                    Swal.fire({
                        title: 'Under Development Area',
                        html: listHtml + '<p>This import/cancellation file selection is currently under development and is read-only.</p>',
                        icon: 'info',
                        confirmButtonText: 'OK'
                    });

                    // Reset input so same file can be re-selected if needed
                    e.target.value = '';
                }
            });
        }
    });
</script>
</html>