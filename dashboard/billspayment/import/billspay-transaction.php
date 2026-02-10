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

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import Transaction | <?php if($_SESSION['user_type'] === 'admin' || $_SESSION['user_type'] === 'user') echo ucfirst($_SESSION['user_type']); else echo "Guest";?></title>
    <!-- custom CSS file link  -->
    <link rel="stylesheet" href="../../../assets/css/templates/style.css?v=<?php echo time(); ?>">
    <script src="https://kit.fontawesome.com/30b908cc5a.js" crossorigin="anonymous"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="../../../assets/js/sweetalert2.all.min.js"></script>

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
            border: 3px dashed #dee2e6;
            border-radius: 10px;
            padding: 40px;
            text-align: center;
            background-color: #f8f9fa;
            transition: all 0.3s ease;
            cursor: pointer;
            margin-bottom: 20px;
        }

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

        /* Duplicate check live list inside overlay (improved) */
        .duplicate-wrapper {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 12px;
        }

        #duplicate-check-list {
            width: 560px;
            max-height: 420px;
            overflow: auto;
            background: #ffffff;
            border-radius: 8px;
            padding: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.35);
            text-align: left;
        }

        #duplicate-check-header {
            font-weight: 700;
            margin-bottom: 8px;
            font-size: 14px;
            color: #333;
        }

        .check-item {
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap: 12px;
            padding:10px 8px;
            border-bottom:1px solid #f1f1f1;
            font-size:13px;
        }

        .check-item .name { flex:1; margin-right:12px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        .check-item .status { width:40px; text-align:center; margin-left:8px; }

        .fade-up {
            animation: fadeUp 700ms forwards;
        }

        @keyframes fadeUp {
            to { transform: translateY(-18px); opacity: 0; }
        }

        .empty-state {
            text-align: center;
            padding: 20px;
            color: #6c757d;
        }
        
    </style>
</head>

<body>
    <div class="main-container">
        <div class="top-content">
            <div class="nav-container">
                <i id="menu-btn" class="fa-solid fa-bars"></i>
                <div class="usernav">
                <h6><?php 
                        if($_SESSION['user_type'] === 'admin'){
                            echo $_SESSION['admin_name'];
                        }elseif($_SESSION['user_type'] === 'user'){
                            echo $_SESSION['user_name']; 
                        }else{
                            echo "GUEST";
                        }
                ?></h6>
                <h6 style="margin-left:5px;"><?php 
                    if($_SESSION['user_type'] === 'admin'){
                        echo "(".$_SESSION['admin_email'].")";
                    }elseif($_SESSION['user_type'] === 'user'){
                        echo "(".$_SESSION['user_email'].")";
                    }else{
                        echo "GUEST";
                    }
                    ?></h6>
                </div>
            </div>
        </div>
        <!-- Show and Hide Side Nav Menu -->
        <?php include '../../../templates/sidebar.php'; ?>
        <div id="loading-overlay">
            <div class="loading-spinner"></div>
        </div>
        <center><h3>Import Transaction</h3></center>
        <div class="container-fluid border border-danger rounded mt-3 p-4">
            <div class="container-fluid">
                <!-- Mode Toggle (Auto / Manual) + Proceed (moved to top-right) -->
                <div class="mb-3 d-flex align-items-center justify-content-between" style="gap:12px;">
                    <div class="d-flex align-items-center" style="gap:12px;">
                        <label class="form-label me-2 mb-0">Import Mode:</label>
                        <div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="importMode" id="modeAuto" value="auto" checked>
                                <label class="form-check-label" for="modeAuto">Auto</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="importMode" id="modeManual" value="manual">
                                <label class="form-check-label" for="modeManual">Manual</label>
                            </div>
                        </div>
                    </div>

                    <div id="proceedContainer" class="proceed-container" style="display: none;">
                        <div class="form-check form-check-inline" style="margin-right:8px; align-items:center;">
                            <input class="form-check-input" type="checkbox" id="showDebug" />
                            <label class="form-check-label" for="showDebug" style="font-size:13px;">Show debug info</label>
                        </div>
                        <button type="button" class="btn btn-danger btn-proceed" id="proceedBtn">
                            Proceed <i class="fa-solid fa-arrow-right ms-2"></i>
                        </button>
                    </div>
                </div>

                <!-- Drag and Drop Upload Area -->
                <div class="file-upload-area" id="fileUploadArea">
                    <div class="file-upload-icon">
                        <i class="fa-solid fa-cloud-arrow-up"></i>
                    </div>
                    <h5>Drag & Drop Files Here</h5>
                    <p class="text-muted">or click to browse</p>
                    <p class="text-muted"><small>Supports multiple Excel files (.xls, .xlsx)</small></p>
                    <input type="file" id="fileInput" accept=".xls,.xlsx" multiple style="display: none;">
                </div>

                <!-- Manual Import Area (hidden by default) -->
                <div id="manualArea" style="display:none;">
                    <form id="manualUploadForm" action="../../../models/saved/saved_billspayImportFile_NEW.php" method="post" enctype="multipart/form-data">
                        <div class="row mt-3">
                            <div class="col-md-5 mb-3">
                                <div class="d-flex align-items-center">
                                    <label class="form-label me-2 mb-0">Partners Name:</label>
                                    <input list="manualCompanyList" id="manualCompanyInput" name="company" class="form-control" placeholder="Search or type company name" required />
                                    <datalist id="manualCompanyList"></datalist>
                                    <!-- hidden select kept for compatibility -->
                                    <select id="manualCompanyDropdown" name="company_select" style="display:none;"></select>
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

                <!-- Files Container -->
                <div id="filesContainer" class="files-container"></div>

                <!-- Removed bottom Proceed button; top button used instead -->
            </div>
        </div>
    </div>
    <style>
        /* Professional Duplicate-Check Modal */
        .duplicate-modal {
            position: fixed;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            z-index: 19999;
            pointer-events: none;
            animation: fadeIn 0.3s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        .duplicate-modal .duplicate-modal-content {
            pointer-events: auto;
            width: 580px;
            max-width: 100%;
            max-height: 85vh;
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 24px 48px rgba(0,0,0,0.2), 0 8px 16px rgba(0,0,0,0.1);
            text-align: left;
            box-sizing: border-box;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            animation: slideUp 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        }
        
        @keyframes slideUp {
            from { 
                opacity: 0;
                transform: translateY(30px) scale(0.95);
            }
            to { 
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }
        
        /* Header Section */
        .duplicate-modal-header {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            padding: 20px 24px 18px;
            color: #ffffff;
            border-radius: 12px 12px 0 0;
        }
        
        .duplicate-modal-header-title {
            display: flex;
            align-items: center;
            margin-bottom: 12px;
        }
        
        .duplicate-modal-header-title i {
            font-size: 24px;
            margin-right: 12px;
        }
        
        #duplicate-check-header {
            font-weight: 600;
            font-size: 18px;
            color: #ffffff;
            margin: 0;
        }
        
        /* Progress Bar */
        .duplicate-progress-bar-container {
            background: rgba(255,255,255,0.25);
            height: 6px;
            border-radius: 10px;
            overflow: hidden;
            margin-top: 12px;
        }
        
        .duplicate-progress-bar {
            height: 100%;
            background: #ffffff;
            width: 0%;
            transition: width 0.3s ease;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(255,255,255,0.5);
        }
        
        /* List Container */
        .duplicate-modal-body {
            padding: 20px 24px;
            flex: 1 1 auto;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        
        #duplicate-check-list {
            overflow-y: auto;
            overflow-x: hidden;
            padding-right: 8px;
            flex: 1 1 auto;
        }
        
        /* Check Items */
        .check-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 16px;
            border-radius: 8px;
            border: 1px solid #e9ecef;
            margin-bottom: 10px;
            background: #ffffff;
            transition: all 0.3s ease;
            box-shadow: 0 2px 4px rgba(0,0,0,0.04);
        }
        
        .check-item:hover {
            box-shadow: 0 4px 8px rgba(0,0,0,0.08);
            border-color: #dee2e6;
        }
        
        .check-item .name {
            flex: 1;
            margin-right: 16px;
            font-size: 13px;
            color: #495057;
            word-break: break-word;
            line-height: 1.5;
            font-weight: 500;
        }
        
        .check-item .status {
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            flex-shrink: 0;
        }
        
        .check-item.checking {
            border-color: #007bff20;
            background: linear-gradient(to right, #ffffff, #f8f9fa);
        }
        
        .check-item.success {
            border-color: #28a74520;
            background: linear-gradient(to right, #d4edda, #ffffff);
        }
        
        .check-item.warning {
            border-color: #ffc10720;
            background: linear-gradient(to right, #fff3cd, #ffffff);
        }
        
        .check-item.fade-up {
            transform: translateX(10px);
            opacity: 0;
            transition: all 0.4s ease;
            max-height: 0;
            padding: 0 16px;
            margin-bottom: 0;
            border-color: transparent;
        }
        
        /* Footer Section */
        .duplicate-modal-footer {
            padding: 16px 24px;
            background: #f8f9fa;
            border-top: 1px solid #e9ecef;
            border-radius: 0 0 12px 12px;
        }
        
        #duplicate-check-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 13px;
            color: #6c757d;
            margin: 0;
        }
        
        .duplicate-footer-icon {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            color: #495057;
        }
        
        .duplicate-footer-icon i {
            color: #dc3545;
        }
        
        /* Loading Overlay */
        #loading-overlay {
            position: fixed;
            inset: 0;
            display: none;
            align-items: center;
            justify-content: center;
            background: rgba(0,0,0,0.4);
            z-index: 9998;
            backdrop-filter: blur(2px);
        }
        
        #loading-overlay .loading-spinner {
            width: 48px;
            height: 48px;
            border-radius: 50%;
        }
        
        /* Custom Scrollbar */
        #duplicate-check-list::-webkit-scrollbar {
            width: 8px;
        }
        
        #duplicate-check-list::-webkit-scrollbar-track {
            background: #f8f9fa;
            border-radius: 10px;
        }
        
        #duplicate-check-list::-webkit-scrollbar-thumb {
            background: #dee2e6;
            border-radius: 10px;
            transition: background 0.3s;
        }
        
        #duplicate-check-list::-webkit-scrollbar-thumb:hover {
            background: #adb5bd;
        }
        
        /* Status Icons */
        .status-icon-checking {
            color: #007bff;
            animation: spin 1s linear infinite;
        }
        
        .status-icon-success {
            color: #28a745;
        }
        
        .status-icon-warning {
            color: #ffc107;
        }
        
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
    </style>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script>
        // Global variable to store file data
        let uploadedFiles = [];

        $(document).ready(function() {
            const fileUploadArea = $('#fileUploadArea');
            const fileInput = $('#fileInput');
            const filesContainer = $('#filesContainer');
            const proceedContainer = $('#proceedContainer');
            const proceedBtn = $('#proceedBtn');

            // Click to open file dialog
            fileUploadArea.on('click', function() {
                fileInput.click();
            });

            // File input change event
            fileInput.on('change', function(e) {
                handleFiles(e.target.files);
            });

            // Drag and drop events
            fileUploadArea.on('dragover', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).addClass('drag-over');
            });

            fileUploadArea.on('dragleave', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).removeClass('drag-over');
            });

            fileUploadArea.on('drop', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).removeClass('drag-over');
                
                const files = e.originalEvent.dataTransfer.files;
                handleFiles(files);
            });

            // Handle files
            function handleFiles(files) {
                const fileArray = Array.from(files);
                
                // Filter only Excel files
                const excelFiles = fileArray.filter(file => {
                    const extension = file.name.split('.').pop().toLowerCase();
                    return extension === 'xlsx' || extension === 'xls';
                });

                if (excelFiles.length === 0) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Invalid File Type',
                        text: 'Please select only Excel files (.xls, .xlsx)',
                        confirmButtonText: 'OK'
                    });
                    return;
                }

                // Process each file
                excelFiles.forEach(file => {
                        processFile(file);
                });
            }

            // Process individual file and auto-detect metadata
            function processFile(file) {
                // Indicate files are being read so UI can block Proceed
                window._filesBeingRead = window._filesBeingRead || 0;
                window._filesBeingRead++;
                $('#loading-overlay').css('display', 'flex');
                proceedBtn.prop('disabled', true);

                const reader = new FileReader();
                
                reader.onload = function(e) {
                    try {
                        const data = new Uint8Array(e.target.result);
                        const workbook = XLSX.read(data, { type: 'array' });
                        const firstSheet = workbook.Sheets[workbook.SheetNames[0]];
                        
                        // Auto-detect Partner ID from Column G, Row 3 (G3)
                        const partnerIdCell = firstSheet['G3'];
                        const partnerId = partnerIdCell ? String(partnerIdCell.v).trim() : '';
                        
                        // Auto-detect Source Type from Column H, Row 3 (H3)
                        const sourceTypeCell = firstSheet['H3'];
                        let sourceType = sourceTypeCell ? String(sourceTypeCell.v).trim().toUpperCase() : '';
                        
                        // Validate source type
                        if (sourceType !== 'KPX' && sourceType !== 'KP7') {
                            Swal.fire({
                                icon: 'error',
                                title: 'Invalid Source Type',
                                html: `File: <strong>${file.name}</strong><br>Source Type in Column H, Row 3 must be either "KPX" or "KP7".<br>Found: "${sourceType}"`,
                                confirmButtonText: 'OK'
                            });
                            return;
                        }

                        // Validate partner ID exists
                        if (!partnerId) {
                            Swal.fire({
                                icon: 'error',
                                title: 'Missing Partner ID',
                                html: `File: <strong>${file.name}</strong><br>Partner ID not found in Column G, Row 3.`,
                                confirmButtonText: 'OK'
                            });
                            return;
                        }

                        // Check if file already added
                        const existingFile = uploadedFiles.find(f => f.name === file.name);
                        if (existingFile) {
                            Swal.fire({
                                icon: 'warning',
                                title: 'Duplicate File',
                                text: `"${file.name}" has already been added.`,
                                confirmButtonText: 'OK'
                            });
                            return;
                        }

                        // Fetch partner name from database
                        $.ajax({
                            url: '../../../fetch/get_partner_name.php',
                            method: 'POST',
                            data: { partner_id: partnerId },
                            dataType: 'json',
                            success: function(response) {
                                const partnerName = response.success ? response.partner_name : 'Unknown Partner';
                                
                                // Add file to array
                                const fileData = {
                                    file: file,
                                    name: file.name,
                                    partnerId: partnerId,
                                    partnerName: partnerName,
                                    sourceType: sourceType,
                                    id: Date.now() + Math.random()
                                };

                                uploadedFiles.push(fileData);
                                renderFileCards();

                                // file processed; decrement counter and hide overlay if done
                                window._filesBeingRead--;
                                if (window._filesBeingRead <= 0) {
                                    window._filesBeingRead = 0;
                                    $('#loading-overlay').hide();
                                    proceedBtn.prop('disabled', false);
                                }
                            },
                            error: function() {
                                // If AJAX fails, still add the file but without partner name
                                const fileData = {
                                    file: file,
                                    name: file.name,
                                    partnerId: partnerId,
                                    partnerName: 'Loading...',
                                    sourceType: sourceType,
                                    id: Date.now() + Math.random()
                                };

                                uploadedFiles.push(fileData);
                                renderFileCards();

                                // file processed; decrement counter and hide overlay if done
                                window._filesBeingRead--;
                                if (window._filesBeingRead <= 0) {
                                    window._filesBeingRead = 0;
                                    $('#loading-overlay').hide();
                                    proceedBtn.prop('disabled', false);
                                }
                            }
                        });

                    } catch (error) {
                        console.error('Error processing file:', error);
                        Swal.fire({
                            icon: 'error',
                            title: 'File Processing Error',
                            html: `Error reading file: <strong>${file.name}</strong><br>${error.message}`,
                            confirmButtonText: 'OK'
                        });
                        // ensure counter decremented on error
                        window._filesBeingRead--;
                        if (window._filesBeingRead <= 0) {
                            window._filesBeingRead = 0;
                            $('#loading-overlay').hide();
                            proceedBtn.prop('disabled', false);
                        }
                    }
                };

                reader.readAsArrayBuffer(file);
            }

            // Render file cards
            function renderFileCards() {
                filesContainer.empty();

                if (uploadedFiles.length === 0) {
                    proceedContainer.hide();
                    return;
                }

                uploadedFiles.forEach(fileData => {
                    // Determine status icon based on file state
                    let statusIcon = '';
                    if (fileData.status === 'reading') {
                        statusIcon = '<i class="fa-solid fa-spinner fa-spin text-primary"></i>';
                    } else if (fileData.status === 'valid') {
                        statusIcon = '<i class="fa-solid fa-circle-check text-success"></i>';
                    } else if (fileData.status === 'duplicates') {
                        statusIcon = '<i class="fa-solid fa-circle-xmark text-warning"></i>';
                    } else if (fileData.status === 'error') {
                        statusIcon = '<i class="fa-solid fa-circle-exclamation text-danger"></i>';
                    }
                    
                    const card = $(`
                        <div class="file-card" data-id="${fileData.id}">
                            <div class="file-card-header">
                                <div class="file-card-info">
                                    <div class="file-card-label">Filename ${statusIcon ? `<span class="ms-2">${statusIcon}</span>` : ''}</div>
                                    <div class="file-card-value">${fileData.name}</div>
                                </div>
                                <div class="file-card-delete" title="Remove file">
                                    <i class="fa-solid fa-xmark"></i>
                                </div>
                            </div>
                            <div class="file-card-body">
                                <div class="file-card-detail">
                                    <div class="file-card-label">Partner ID</div>
                                    <div class="file-card-value partner-tooltip">
                                        ${fileData.partnerId}
                                        <span class="tooltip-text">${fileData.partnerName}</span>
                                    </div>
                                </div>
                                <div class="file-card-detail">
                                    <div class="file-card-label">Source Type</div>
                                    <div class="file-card-value">
                                        <span class="badge-source ${fileData.sourceType === 'KPX' ? 'badge-kpx' : 'badge-kp7'}">
                                            ${fileData.sourceType}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `);

                    // Delete file handler
                    card.find('.file-card-delete').on('click', function() {
                        removeFile(fileData.id);
                    });

                    filesContainer.append(card);
                });

                proceedContainer.show();
            }

            // Remove file from array
            function removeFile(fileId) {
                uploadedFiles = uploadedFiles.filter(f => f.id !== fileId);
                renderFileCards();
            }

            // Proceed button click
            proceedBtn.on('click', function() {
                if (uploadedFiles.length === 0) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'No Files Selected',
                        text: 'Please select at least one file to proceed.',
                        confirmButtonText: 'OK'
                    });
                    return;
                }

                // If debug checkbox is checked, fetch server memory/error info first
                function fetchDebugInfo() {
                    return $.ajax({
                        url: '../../../fetch/debug_memory.php',
                        method: 'GET',
                        dataType: 'json'
                    });
                }

                function formatBytes(bytes) {
                    if (!bytes && bytes !== 0) return 'n/a';
                    var units = ['B','KB','MB','GB','TB'];
                    var i = 0;
                    while(bytes >= 1024 && i < units.length-1) { bytes /= 1024; i++; }
                    return bytes.toFixed(2) + ' ' + units[i];
                }

                function showDebugModal(data, extra) {
                    var d = data && data.data ? data.data : data;
                    var html = '<div style="text-align:left; font-size:13px;">';
                    html += '<p><strong>memory_limit:</strong> ' + (d.memory_limit || 'n/a') + '</p>';
                    html += '<p><strong>memory_usage:</strong> ' + formatBytes(d.memory_usage_bytes) + ' (' + (d.memory_usage_bytes || 'n') + ')</p>';
                    html += '<p><strong>memory_usage_real:</strong> ' + formatBytes(d.memory_usage_real_bytes) + '</p>';
                    html += '<p><strong>memory_peak:</strong> ' + formatBytes(d.memory_peak_bytes) + '</p>';
                    html += '<p><strong>max_execution_time:</strong> ' + (d.max_execution_time || 'n/a') + 's</p>';
                    html += '<p><strong>post_max_size:</strong> ' + (d.post_max_size || 'n/a') + '</p>';
                    html += '<p><strong>upload_max_filesize:</strong> ' + (d.upload_max_filesize || 'n/a') + '</p>';
                    if (d.error_last) {
                        html += '<hr><p><strong>Last PHP error:</strong><br>' + (d.error_last['message'] || '') + ' in ' + (d.error_last['file'] || '') + ' on line ' + (d.error_last['line'] || '') + '</p>';
                    }
                    if (extra) html += '<hr><pre style="white-space:pre-wrap; font-size:12px;">' + $('<div>').text(extra).html() + '</pre>';
                    html += '</div>';

                    Swal.fire({
                        title: 'Server debug info',
                        html: html,
                        width: 700,
                        confirmButtonText: 'Continue'
                    });
                }

                if ($('#showDebug').is(':checked')) {
                    // Fetch debug info and show it, then proceed
                    fetchDebugInfo().done(function(resp) {
                        showDebugModal(resp);
                        // Show loading overlay and continue duplicate check after user sees debug
                        $('#loading-overlay').css('display', 'flex');
                        checkForDuplicates();
                    }).fail(function(xhr, status, err) {
                        var extra = 'Debug endpoint error: ' + err + '\n' + (xhr.responseText || '');
                        showDebugModal({ data: {} }, extra);
                        $('#loading-overlay').css('display', 'flex');
                        checkForDuplicates();
                    });
                } else {
                    // Show loading overlay
                    $('#loading-overlay').css('display', 'flex');

                    // Step 1: Check for duplicates first
                    checkForDuplicates();
                }
            });

            // Function to check for duplicates (batched to avoid PHP's max_file_uploads limit)
            function checkForDuplicates() {
                // Set all files to "reading" status
                uploadedFiles.forEach(file => { file.status = 'reading'; });
                renderFileCards();

                const BATCH_SIZE = 50; // adjust as needed (PHP default max_file_uploads is often 20)
                let index = 0;
                const aggregateResults = [];

                // Build live-check modal UI (professional design)
                $('#loading-overlay').css('display', 'flex');
                // hide the small global spinner while the modal is visible
                $('#loading-overlay .loading-spinner').hide();

                var modalHtml = '<div class="duplicate-modal">'
                    + '<div class="duplicate-modal-content">'
                    + '<div class="duplicate-modal-header">'
                    + '<div class="duplicate-modal-header-title">'
                    + '<i class="fa-solid fa-shield-halved"></i>'
                    + '<h4 id="duplicate-check-header">Checking files (0/' + uploadedFiles.length + ')</h4>'
                    + '</div>'
                    + '<div class="duplicate-progress-bar-container">'
                    + '<div class="duplicate-progress-bar" id="duplicate-progress-bar"></div>'
                    + '</div>'
                    + '</div>'
                    + '<div class="duplicate-modal-body">'
                    + '<div id="duplicate-check-list"></div>'
                    + '</div>'
                    + '<div class="duplicate-modal-footer">'
                    + '<div id="duplicate-check-footer">'
                    + '<span class="duplicate-footer-icon"><i class="fa-solid fa-file-circle-check"></i> Validating files</span>'
                    + '<span id="duplicate-progress-text"><strong>0</strong> / ' + uploadedFiles.length + '</span>'
                    + '</div>'
                    + '</div>'
                    + '</div></div>';

                // append modal to body to avoid clipping by overlay containers
                $('body').append(modalHtml);
                const $list = $('#duplicate-check-list');
                $list.empty();
                uploadedFiles.forEach((f, idx) => {
                    const item = $(`<div class="check-item checking" data-idx="${idx}">
                        <div class="name">${f.name}</div>
                        <div class="status"><i class="fa-solid fa-spinner fa-spin status-icon-checking"></i></div>
                    </div>`);
                    $list.append(item);
                });

                let totalCount = uploadedFiles.length;
                let processedCount = 0;
                function updateHeader() {
                    $('#duplicate-check-header').text('Checking files (' + processedCount + '/' + totalCount + ')');
                    $('#duplicate-progress-text').html('<strong>' + processedCount + '</strong> / ' + totalCount);
                    
                    // Update progress bar
                    const progressPercent = (processedCount / totalCount) * 100;
                    $('#duplicate-progress-bar').css('width', progressPercent + '%');
                }

                function processBatch(start) {
                    const formData = new FormData();
                    const batchFiles = uploadedFiles.slice(start, start + BATCH_SIZE);
                    batchFiles.forEach((fileData) => {
                        formData.append('files[]', fileData.file);
                        formData.append('partner_ids[]', fileData.partnerId);
                        formData.append('source_types[]', fileData.sourceType);
                    });
                    formData.append('check_duplicates', '1');

                    return $.ajax({
                        url: '../../../models/saved/saved_billspayImportFile_NEW.php',
                        type: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        dataType: 'json'
                    });
                }

                // Sequentially process batches to avoid overwhelming the server
                function next() {
                    if (index >= uploadedFiles.length) {
                        // All batches done — aggregate and update UI
                        // restore global spinner visibility then hide overlay
                        $('#loading-overlay .loading-spinner').show();

                        // Flatten aggregateResults into a single array of per-file results
                        const flat = [].concat.apply([], aggregateResults);

                        // Update uploadedFiles statuses from flat results
                        flat.forEach((result, idx) => {
                            if (uploadedFiles[idx]) {
                                if (result.hasDuplicates) {
                                    uploadedFiles[idx].status = 'duplicates';
                                    uploadedFiles[idx].duplicateCount = result.duplicateRows;
                                    uploadedFiles[idx].newCount = result.newRows;
                                } else {
                                    uploadedFiles[idx].status = 'valid';
                                }
                            }
                        });

                        renderFileCards();

                        // remove live modal
                        $('.duplicate-modal').remove();
                        $('#loading-overlay').hide();

                        // Check duplicates overal
                        const filesWithDuplicates = flat.filter(f => f.hasDuplicates);
                        if (filesWithDuplicates.length > 0) {
                            showDuplicateModal(flat, filesWithDuplicates);
                        } else {
                            proceedWithUpload('skip');
                        }

                        return;
                    }

                    $('#loading-overlay').css('display', 'flex');

                    processBatch(index).done(function(response) {
                        if (response && response.success && Array.isArray(response.files)) {
                            aggregateResults.push(response.files);

                            // Update live UI for this batch
                            response.files.forEach(function(res, j) {
                                var globalIndex = index + j;
                                var $item = $list.find('.check-item[data-idx="' + globalIndex + '"]');
                                if ($item.length) {
                                    if (res.hasDuplicates) {
                                        $item.removeClass('checking').addClass('warning');
                                        $item.find('.status').html('<i class="fa-solid fa-circle-exclamation status-icon-warning"></i>');
                                    } else {
                                        $item.removeClass('checking').addClass('success');
                                        $item.find('.status').html('<i class="fa-solid fa-circle-check status-icon-success"></i>');
                                    }
                                    // animate fade-up then remove the item so new ones appear from bottom
                                    setTimeout(function() { 
                                        $item.addClass('fade-up'); 
                                        setTimeout(function(){ 
                                            $item.remove(); 
                                            processedCount++; 
                                            updateHeader(); 
                                        }, 400); 
                                    }, 300 + (j*60));
                                }
                            });

                            index += BATCH_SIZE;
                            // short delay to keep UI responsive for massive batches
                            setTimeout(next, 50);
                        } else {
                            // treat as error for this batch
                            $('#loading-overlay').hide();
                            uploadedFiles.forEach(file => { file.status = 'error'; });
                            renderFileCards();
                            Swal.fire({ icon: 'error', title: 'Validation Error', text: (response && response.error) ? response.error : 'An error occurred while checking for duplicates.', confirmButtonText: 'OK' });
                        }
                    }).fail(function(xhr, status, error) {
                        $('#loading-overlay .loading-spinner').show();
                        $('.duplicate-modal').remove();
                        $('#loading-overlay').hide();
                        uploadedFiles.forEach(file => { file.status = 'error'; });
                        renderFileCards();

                        // Fetch debug info and show combined message
                        $.ajax({ url: '../../../fetch/debug_memory.php', method: 'GET', dataType: 'json' }).done(function(debugResp) {
                            var extra = 'AJAX batch error: ' + error + '\n' + (xhr.responseText || '');
                            var d = debugResp && debugResp.data ? debugResp.data : debugResp;
                            var html = '<div style="text-align:left; font-size:13px;">';
                            html += '<p><strong>memory_limit:</strong> ' + (d.memory_limit || 'n/a') + '</p>';
                            html += '<p><strong>memory_usage:</strong> ' + (d.memory_usage_bytes ? (d.memory_usage_bytes + ' bytes') : 'n/a') + '</p>';
                            if (d.error_last) html += '<p><strong>Last PHP error:</strong> ' + (d.error_last['message'] || '') + '</p>';
                            html += '<hr><pre style="white-space:pre-wrap; font-size:12px;">' + $('<div>').text(extra).html() + '</pre>';
                            html += '</div>';

                            Swal.fire({ icon: 'error', title: 'Validation Error - Duplicate Check Failed', html: html, confirmButtonText: 'OK', width: 700 });
                        }).fail(function() {
                            Swal.fire({ icon: 'error', title: 'Validation Error', text: 'An error occurred while checking for duplicates. Please try again.', confirmButtonText: 'OK' });
                        });
                        console.error('Duplicate check batch error:', error, xhr.responseText);
                    });
                }

                // Start processing
                next();
            }

            // Function to show duplicate modal
            function showDuplicateModal(allFiles, filesWithDuplicates) {
                // Calculate totals (including posted/unposted breakdown)
                let totalDuplicates = 0;
                let totalNew = 0;
                let totalRows = 0;
                let totalPostedMatches = 0;
                let totalUnpostedMatches = 0;

                allFiles.forEach(file => {
                    totalDuplicates += file.duplicateRows || 0;
                    totalNew += file.newRows || 0;
                    totalRows += file.totalRows || 0;
                    totalPostedMatches += file.postedRows || 0;
                    totalUnpostedMatches += file.unpostedRows || 0;
                });

                // Build detailed file list HTML (initially hidden)
                let fileListHTML = '<div id="duplicate-details" style="display: none; max-height: 250px; overflow-y: auto; margin-top: 15px; text-align: left; border-top: 1px solid #ddd; padding-top: 15px;">';
                filesWithDuplicates.forEach(file => {
                    fileListHTML += `
                        <div style="padding: 10px; border: 1px solid #ddd; margin-bottom: 10px; border-radius: 5px; background-color: #fff8e1;">
                            <strong style="color: #000;">📄 ${file.fileName}</strong><br>
                            <small style="color: #666;">Partner: ${file.partnerId} | Type: ${file.sourceType}</small><br>
                            <small style="color: #d32f2f;">⚠️ ${file.duplicateRows.toLocaleString()} duplicate row(s) found</small><br>
                            <small style="color: #388e3c;">✓ ${file.newRows.toLocaleString()} new row(s)</small>
                        </div>
                    `;
                });
                fileListHTML += '</div>';

                // Simple summary message
                const summaryHTML = `
                    <div style="text-align: center;">
                        <div style="background-color: #fff8e1; padding: 15px; border-radius: 8px; margin-bottom: 15px; border-left: 4px solid #ff9800;">
                            <p style="margin: 0; color: #666; font-size: 15px;">
                                <strong style="color: #000;">${filesWithDuplicates.length}</strong> file(s) with Partner ID data already exists
                            </p>
                        </div>
                        <div style="background-color: #f5f5f5; padding: 12px; border-radius: 5px; margin-bottom: 15px;">
                            <p style="margin: 5px 0; color: #d32f2f; font-size: 14px;">
                                <i class="fa-solid fa-exclamation-triangle"></i> 
                                <strong>${totalDuplicates.toLocaleString()}</strong> duplicate row(s) detected
                            </p>
                            <p style="margin: 5px 0; color: #388e3c; font-size: 14px;">
                                <i class="fa-solid fa-check-circle"></i> 
                                <strong>${totalNew.toLocaleString()}</strong> new row(s)
                            </p>
                        </div>
                        <button id="toggle-details-btn" type="button" style="background-color: #1976d2; color: white; border: none; padding: 8px 20px; border-radius: 5px; cursor: pointer; font-size: 13px; margin-bottom: 10px;">
                            <i class="fa-solid fa-chevron-down"></i> View All Details
                        </button>
                    </div>
                `;

                // If all duplicate matches are already posted (no unposted duplicates), show an alternate modal
                if (totalDuplicates > 0 && totalUnpostedMatches === 0 && totalPostedMatches > 0) {
                    // Build a concise "Data already Existed" summary showing partner + posted status
                    const firstPartnerId = (allFiles && allFiles[0] && allFiles[0].partnerId) ? allFiles[0].partnerId : '';
                    const firstPartnerName = (allFiles && allFiles[0] && allFiles[0].partnerName) ? allFiles[0].partnerName : 'Unknown Partner';
                    const altSummary = `
                        <div style="text-align:center;">
                            <div style="background-color: #fff8e1; padding: 15px; border-radius: 8px; margin-bottom: 12px;">
                                <p style="margin: 0; color: #000; font-size:16px;"><strong>Data already Existed</strong></p>
                            </div>
                            <div style="background-color: #f5f5f5; padding: 12px; border-radius: 5px; margin-bottom: 12px; text-align:left;">
                                <p style="margin:4px 0; font-size:14px;"><strong>Partner ID:</strong> ${firstPartnerId}</p>
                                <p style="margin:4px 0; font-size:14px;"><strong>Partner Name:</strong> ${firstPartnerName}</p>
                                <p style="margin:4px 0; font-size:14px;"><strong>Status:</strong> <span style="color:#388e3c; font-weight:700;">Posted</span></p>
                                <p style="margin:8px 0; color:#d32f2f; font-size:14px;"><strong>Existing rows detected:</strong> ${totalDuplicates.toLocaleString()}</p>
                            </div>
                        </div>
                    `;

                    // Only show a single Remove action (no Override/Skip)
                    const confirmText = (allFiles.length === 1) ? '<i class="fa-solid fa-trash"></i> Remove' : '<i class="fa-solid fa-trash"></i> Remove Existing File(s)';

                    Swal.fire({
                        title: '<i class="fa-solid fa-info-circle" style="color: #388e3c;"></i> Data already Existed',
                        html: altSummary + '<div id="alt-details" style="display:none; margin-top:10px; text-align:left;">' + fileListHTML + '</div>',
                        icon: 'info',
                        showCancelButton: false,
                        showDenyButton: false,
                        showConfirmButton: true,
                        confirmButtonText: confirmText,
                        confirmButtonColor: '#6c757d',
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        width: '700px',
                        didOpen: () => {
                            // no toggle button required for this simplified message, but keep details wrapper hidden by default
                        }
                    }).then(() => {
                        if (allFiles.length === 1) {
                            // For single-file manual-like flows, treat confirm as cancel import (remove)
                            window.location.href = '../../../models/saved/saved_billspayImportFile_NEW.php?cancel=1';
                        } else {
                            // Remove files that had existing posted records and continue with the rest
                            filesWithDuplicates.forEach(f => {
                                uploadedFiles = uploadedFiles.filter(u => !(u.name === f.fileName && String(u.partnerId) === String(f.partnerId)));
                            });
                            renderFileCards();
                            if (uploadedFiles.length === 0) {
                                Swal.fire({
                                    icon: 'info',
                                    title: 'All Files Removed',
                                    text: 'No files left to import.',
                                    confirmButtonText: 'OK'
                                });
                            } else {
                                // Proceed with remaining files (default to skip duplicates)
                                proceedWithUpload('skip');
                            }
                        }
                    });

                    return;
                }

                // Otherwise show the standard duplicate modal with Override/Skip/Remove
                Swal.fire({
                    title: '<i class="fa-solid fa-triangle-exclamation" style="color: #ff9800;"></i> Duplicate Records Detected',
                    html: summaryHTML + fileListHTML,
                    icon: 'warning',
                    showCancelButton: true,
                    showDenyButton: true,
                    confirmButtonText: '<i class="fa-solid fa-rotate"></i> Override',
                    denyButtonText: '<i class="fa-solid fa-forward"></i> Skip',
                    cancelButtonText: '<i class="fa-solid fa-trash"></i> Remove',
                    confirmButtonColor: '#d33',
                    denyButtonColor: '#3085d6',
                    cancelButtonColor: '#6c757d',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    customClass: {
                        popup: 'duplicate-modal-popup'
                    },
                    width: '600px',
                    didOpen: () => {
                        // Add toggle functionality
                        const toggleBtn = document.getElementById('toggle-details-btn');
                        const detailsDiv = document.getElementById('duplicate-details');
                        let isExpanded = false;
                        
                        toggleBtn.addEventListener('click', function() {
                            isExpanded = !isExpanded;
                            if (isExpanded) {
                                detailsDiv.style.display = 'block';
                                toggleBtn.innerHTML = '<i class="fa-solid fa-chevron-up"></i> Hide Details';
                            } else {
                                detailsDiv.style.display = 'none';
                                toggleBtn.innerHTML = '<i class="fa-solid fa-chevron-down"></i> View All Details';
                            }
                        });
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        // User chose Override
                        proceedWithUpload('override');
                    } else if (result.isDenied) {
                        // User chose Skip
                        proceedWithUpload('skip');
                    } else {
                        // User chose Remove: delete the files that had duplicates and continue
                        filesWithDuplicates.forEach(f => {
                            uploadedFiles = uploadedFiles.filter(u => !(u.name === f.fileName && String(u.partnerId) === String(f.partnerId)));
                        });
                        renderFileCards();
                        if (uploadedFiles.length === 0) {
                            Swal.fire({
                                icon: 'info',
                                title: 'All Files Removed',
                                text: 'No files left to import.',
                                confirmButtonText: 'OK'
                            });
                        } else {
                            // Proceed with remaining files (default to skip duplicates)
                            proceedWithUpload('skip');
                        }
                    }
                });
            }

            // Function to proceed with upload based on user decision
            function proceedWithUpload(userDecision) {
                $('#loading-overlay').css('display', 'flex');

                // Create FormData and append all files
                const formData = new FormData();
                uploadedFiles.forEach((fileData, index) => {
                    formData.append('files[]', fileData.file);
                    formData.append('partner_ids[]', fileData.partnerId);
                    formData.append('source_types[]', fileData.sourceType);
                });
                formData.append('upload', '1');
                formData.append('user_decision', userDecision); // Pass user decision

                // Send to checker page via session storage
                sessionStorage.setItem('uploadedFilesData', JSON.stringify(uploadedFiles.map(f => ({
                    name: f.name,
                    partnerId: f.partnerId,
                    partnerName: f.partnerName,
                    sourceType: f.sourceType
                }))));

                // Send to checker page
                $.ajax({
                    url: '../../../models/saved/saved_billspayImportFile_NEW.php',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        // Redirect to validation page
                        window.location.href = '../../../models/saved/saved_billspayImportFile_NEW.php';
                    },
                    error: function(xhr, status, error) {
                        $('#loading-overlay').hide();
                        Swal.fire({
                            icon: 'error',
                            title: 'Upload Error',
                            text: 'An error occurred while uploading files. Please try again.',
                            confirmButtonText: 'OK'
                        });
                        console.error('Upload error:', error);
                    }
                });
            }
        });
    </script>
    <script>
        // Mode toggle: show/hide Auto (drag-drop) vs Manual form
        $(function() {
            function setMode(mode) {
                if (mode === 'manual') {
                    $('#fileUploadArea').hide();
                    $('#filesContainer').hide();
                    $('#proceedContainer').hide();
                    $('#manualArea').show();
                    // initialize manual partners dropdown
                    initManualSelect2();
                    loadManualPartners();
                } else {
                    $('#manualArea').hide();
                    $('#fileUploadArea').show();
                    $('#filesContainer').show();
                    if (uploadedFiles.length) $('#proceedContainer').show();
                }
            }

            $('input[name="importMode"]').on('change', function() {
                setMode($(this).val());
            });

            function initManualSelect2() {
                // No-op: we use a native searchable input + datalist.
                // Keep function for compatibility if select2 is added later.
                return;
            }

            function loadManualPartners() {
                var $select = $('#manualCompanyDropdown');
                var $input = $('#manualCompanyInput');
                var $datalist = $('#manualCompanyList');
                if ($input.length === 0 || $datalist.length === 0) return;

                // Clear existing
                $datalist.empty();
                if ($select.length) $select.empty();

                // Add default options
                var allOpt = document.createElement('option');
                allOpt.value = 'All';
                allOpt.text = 'All';
                $datalist.append(allOpt);
                if ($select.length) $select.append($('<option>', { value: '', text: 'Select Company' }));
                if ($select.length) $select.append($('<option>', { value: 'All', text: 'All' }));

                $.ajax({
                    url: '../../../fetch/get_partners.php',
                    method: 'GET',
                    dataType: 'json',
                    success: function(response) {
                        if (!response) return;
                        if (response.success === false) return;
                        var list = Array.isArray(response.data) ? response.data : response;
                        list.forEach(function(p) {
                            var name = p.partner_name || '';
                            if (name) {
                                var opt = document.createElement('option');
                                opt.value = name;
                                $datalist.append(opt);
                                if ($select.length) $select.append($('<option>', { value: name, text: name }));
                            }
                        });
                        // keep input empty
                        $input.val('');
                    },
                    error: function() {
                        // ignore error, keep defaults
                    }
                });
            }

            // Manual form validation
            $('#manualUploadForm').on('submit', function(e) {
                e.preventDefault(); // Always prevent default to handle validation first
                
                var selectedCompany = $('#manualCompanyInput').val();
                var fileType = $('#manualFileType').val();
                
                if (!fileType) {
                    Swal.fire({ 
                        icon: 'warning', 
                        title: 'Missing File Type', 
                        text: 'Please select a source file type (KPX or KP7).', 
                        confirmButtonText: 'OK' 
                    });
                    return false;
                }
                
                if (selectedCompany === 'All' && fileType === 'KPX') {
                    Swal.fire({ 
                        icon: 'error', 
                        title: 'Invalid Combination', 
                        text: 'No All Partners Available for KPX. Please select a specific partner.', 
                        confirmButtonText: 'OK' 
                    });
                    return false;
                }
                
                // Check if file is selected
                var fileInput = $('input[name="import_file"]')[0];
                if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
                    Swal.fire({ 
                        icon: 'warning', 
                        title: 'No File Selected', 
                        text: 'Please select a file to upload.', 
                        confirmButtonText: 'OK' 
                    });
                    return false;
                }
                
                // Show loading overlay and check for duplicates
                $('#loading-overlay').css('display', 'flex');
                checkManualDuplicates(this);
                
                return false;
            });

            // Function to check for duplicates in manual mode
            function checkManualDuplicates(form) {
                // Async flow: resolve partner id (if specific), then POST to the same duplicate-check endpoint
                $('#loading-overlay').css('display', 'flex');

                var selectedPartner = $('#manualCompanyInput').val();
                var fileType = $('#manualFileType').val();

                function resolvePartnerIds(name) {
                    return new Promise(function(resolve) {
                        if (!name || name === 'All') return resolve({ partner_id: 'ALL', partner_id_kpx: 'ALL' });
                        $.ajax({
                            url: '../../../fetch/get_partner_ids.php',
                            method: 'POST',
                            data: { partner_name: name },
                            dataType: 'json',
                            success: function(response) {
                                if (response && response.success) return resolve({ partner_id: response.partner_id || '', partner_id_kpx: response.partner_id_kpx || '' });
                                return resolve({ partner_id: '', partner_id_kpx: '' });
                            },
                            error: function() { return resolve({ partner_id: '', partner_id_kpx: '' }); }
                        });
                    });
                }

                resolvePartnerIds(selectedPartner).then(function(partnerResp) {
                    var partnerId = '';
                    if (partnerResp) {
                        partnerId = (fileType && fileType.toUpperCase() === 'KPX') ? (partnerResp.partner_id_kpx || '') : (partnerResp.partner_id || '');
                    }
                    var formData = new FormData();
                    var fileInput = $(form).find('input[name="import_file"]')[0];
                    if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
                        $('#loading-overlay').hide();
                        Swal.fire({ icon: 'warning', title: 'No File Selected', text: 'Please select a file to upload.', confirmButtonText: 'OK' });
                        return;
                    }

                    // Use the batch duplicate check endpoint (same as Auto) to ensure identical detection logic
                    var batchData = new FormData();
                    batchData.append('files[]', fileInput.files[0]);
                    batchData.append('partner_ids[]', partnerId || '');
                    batchData.append('source_types[]', fileType || '');
                    batchData.append('check_duplicates', '1');

                    $.ajax({
                        url: '../../../models/saved/saved_billspayImportFile_NEW.php',
                        type: 'POST',
                        data: batchData,
                        processData: false,
                        contentType: false,
                        dataType: 'json',
                        success: function(response) {
                            $('#loading-overlay').hide();
                            console.log('Manual duplicate check response:', response);
                            if (response && response.success && Array.isArray(response.files) && response.files.length > 0) {
                                var first = response.files[0];
                                if (first && (first.hasDuplicates || (first.duplicateRows && first.duplicateRows > 0))) {
                                    showManualDuplicateModal(first, form);
                                } else {
                                    proceedWithManualUpload(form, 'skip');
                                }
                            } else if (response && response.success && !Array.isArray(response.files)) {
                                // fallback if server returned single-file object
                                var f = response.files || response;
                                if (f && (f.hasDuplicates || (f.duplicateRows && f.duplicateRows > 0))) {
                                    showManualDuplicateModal(f, form);
                                } else {
                                    proceedWithManualUpload(form, 'skip');
                                }
                            } else {
                                Swal.fire({ icon: 'error', title: 'Validation Error', text: (response && response.error) ? response.error : 'An error occurred while checking for duplicates.', confirmButtonText: 'OK' });
                            }
                        },
                        error: function(xhr, status, error) {
                            $('#loading-overlay').hide();
                            Swal.fire({ icon: 'error', title: 'Validation Error', text: 'An error occurred while checking for duplicates. Please try again.', confirmButtonText: 'OK' });
                            console.error('Duplicate check error:', error, xhr.responseText);
                        }
                    });
                });
            }

            // Function to show duplicate modal for manual mode
            function showManualDuplicateModal(fileData, form) {
                // Simple summary message with expandable details
                const summaryHTML = `
                    <div style="text-align: center;">
                        <div style="background-color: #fff8e1; padding: 15px; border-radius: 8px; margin-bottom: 15px; border-left: 4px solid #ff9800;">
                            <p style="margin: 0; color: #666; font-size: 15px;">
                                File <strong style="color: #000;">${fileData.fileName}</strong> with Partner ID <strong style="color: #000;">${fileData.partnerId}</strong> data already exists in the database
                            </p>
                        </div>
                        <div style="background-color: #f5f5f5; padding: 12px; border-radius: 5px; margin-bottom: 15px;">
                            <p style="margin: 5px 0; color: #d32f2f; font-size: 14px;">
                                <i class="fa-solid fa-exclamation-triangle"></i> 
                                <strong>${fileData.duplicateRows.toLocaleString()}</strong> duplicate row(s) detected
                            </p>
                            <p style="margin: 5px 0; color: #388e3c; font-size: 14px;">
                                <i class="fa-solid fa-check-circle"></i> 
                                <strong>${fileData.newRows.toLocaleString()}</strong> new row(s)
                            </p>
                        </div>
                        <button id="toggle-manual-details-btn" type="button" style="background-color: #1976d2; color: white; border: none; padding: 8px 20px; border-radius: 5px; cursor: pointer; font-size: 13px; margin-bottom: 10px;">
                            <i class="fa-solid fa-chevron-down"></i> View All Details
                        </button>
                    </div>
                `;
                
                // Detailed breakdown (initially hidden)
                const detailsHTML = `
                    <div id="manual-duplicate-details" style="display: none; border-top: 1px solid #ddd; padding-top: 15px; margin-top: 10px; text-align: left;">
                        <div style="padding: 10px; border: 1px solid #ddd; margin-bottom: 10px; border-radius: 5px; background-color: #fff8e1;">
                            <strong style="color: #000;">📄 ${fileData.fileName}</strong><br>
                            <small style="color: #666;">Partner: ${fileData.partnerId} | Type: ${fileData.sourceType}</small><br>
                            <small style="color: #666;">Total rows: ${fileData.totalRows.toLocaleString()}</small><br>
                            <small style="color: #d32f2f;">⚠️ ${fileData.duplicateRows.toLocaleString()} duplicate row(s) found</small><br>
                            <small style="color: #388e3c;">✓ ${fileData.newRows.toLocaleString()} new row(s)</small>
                        </div>
                        <p style="font-size: 13px; color: #666; margin-top: 10px;">
                            <strong>Note:</strong> Duplicates are matched by reference number, transaction date, and cancellation date.
                        </p>
                    </div>
                `;
                
                // If duplicates exist but all matches are already posted, show alternate modal
                if (fileData.duplicateRows > 0 && (fileData.unpostedRows || 0) === 0 && (fileData.postedRows || 0) > 0) {
                    const altHtml = `
                        <div style="text-align:center;">
                            <div style="background-color: #fff8e1; padding: 15px; border-radius: 8px; margin-bottom: 12px;">
                                <p style="margin: 0; color: #000; font-size:16px;"><strong>Data already Existed</strong></p>
                            </div>
                            <div style="background-color: #f5f5f5; padding: 12px; border-radius: 5px; text-align:left;">
                                <p style="margin:4px 0; font-size:14px;"><strong>Partner ID:</strong> ${fileData.partnerId}</p>
                                <p style="margin:4px 0; font-size:14px;"><strong>Partner Name:</strong> ${fileData.partnerName || 'Unknown'}</p>
                                <p style="margin:4px 0; font-size:14px;"><strong>Status:</strong> <span style="color:#388e3c; font-weight:700;">Posted</span></p>
                                <p style="margin:8px 0; color:#d32f2f; font-size:14px;"><strong>Existing rows detected:</strong> ${fileData.duplicateRows.toLocaleString()}</p>
                            </div>
                        </div>
                    `;

                    Swal.fire({
                        title: '<i class="fa-solid fa-info-circle" style="color: #388e3c;"></i> Data already Existed',
                        html: altHtml,
                        icon: 'info',
                        showCancelButton: false,
                        showDenyButton: false,
                        showConfirmButton: true,
                        confirmButtonText: '<i class="fa-solid fa-trash"></i> Remove',
                        confirmButtonColor: '#6c757d',
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        width: '600px'
                    }).then(() => {
                        // Treat as cancel/remove for manual flow
                        window.location.href = '../../../models/saved/saved_billspayImportFile_NEW.php?cancel=1';
                    });

                    return;
                }

                Swal.fire({
                    title: '<i class="fa-solid fa-triangle-exclamation" style="color: #ff9800;"></i> Duplicate Records Detected',
                    html: summaryHTML + detailsHTML,
                    icon: 'warning',
                    showCancelButton: true,
                    showDenyButton: true,
                    confirmButtonText: '<i class="fa-solid fa-rotate"></i> Override',
                    denyButtonText: '<i class="fa-solid fa-forward"></i> Skip',
                    cancelButtonText: '<i class="fa-solid fa-trash"></i> Remove',
                    confirmButtonColor: '#d33',
                    denyButtonColor: '#3085d6',
                    cancelButtonColor: '#6c757d',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    customClass: {
                        popup: 'duplicate-modal-popup'
                    },
                    width: '600px',
                    didOpen: () => {
                        // Add toggle functionality
                        const toggleBtn = document.getElementById('toggle-manual-details-btn');
                        const detailsDiv = document.getElementById('manual-duplicate-details');
                        let isExpanded = false;
                        
                        toggleBtn.addEventListener('click', function() {
                            isExpanded = !isExpanded;
                            if (isExpanded) {
                                detailsDiv.style.display = 'block';
                                toggleBtn.innerHTML = '<i class="fa-solid fa-chevron-up"></i> Hide Details';
                            } else {
                                detailsDiv.style.display = 'none';
                                toggleBtn.innerHTML = '<i class="fa-solid fa-chevron-down"></i> View All Details';
                            }
                        });
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        // User chose Override
                        proceedWithManualUpload(form, 'override');
                    } else if (result.isDenied) {
                        // User chose Skip
                        proceedWithManualUpload(form, 'skip');
                    } else {
                        // User chose Remove: reset the manual form and inform the user
                        try {
                            form.reset();
                        } catch (e) {
                            // ignore
                        }
                        Swal.fire({
                            icon: 'info',
                            title: 'File Removed',
                            text: 'The selected file has been removed. You can select another file to upload.',
                            confirmButtonText: 'OK'
                        });
                    }
                });
            }

            // Function to proceed with manual upload based on user decision
            function proceedWithManualUpload(form, userDecision) {
                $('#loading-overlay').css('display', 'flex');

                var formData = new FormData(form);
                formData.append('upload', '1');
                formData.append('user_decision', userDecision || 'skip');

                $.ajax({
                    url: '../../../models/saved/saved_billspayImportFile_NEW.php',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        // Redirect to validation/checker page
                        window.location.href = '../../../models/saved/saved_billspayImportFile_NEW.php';
                    },
                    error: function(xhr, status, error) {
                        $('#loading-overlay').hide();
                        Swal.fire({ icon: 'error', title: 'Upload Error', text: 'An error occurred while uploading files. Please try again.', confirmButtonText: 'OK' });
                        console.error('Upload error:', error);
                    }
                });
            }

            // Start in auto mode
            setMode('auto');
        });
    </script>
</body>
<?php include '../../../templates/footer.php'; ?>


</html>
