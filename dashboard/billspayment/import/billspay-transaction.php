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

        /* Proceed Button Container */
        .proceed-container {
            text-align: center;
            margin-top: 30px;
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

                <!-- Files Container -->
                <div id="filesContainer" class="files-container"></div>

                <!-- Proceed Button -->
                <div class="proceed-container" id="proceedContainer" style="display: none;">
                    <button type="button" class="btn btn-danger btn-proceed" id="proceedBtn">
                        Proceed <i class="fa-solid fa-arrow-right ms-2"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
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
                    const card = $(`
                        <div class="file-card" data-id="${fileData.id}">
                            <div class="file-card-header">
                                <div class="file-card-info">
                                    <div class="file-card-label">Filename</div>
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

                // Show loading overlay
                $('#loading-overlay').css('display', 'flex');

                // Create FormData and append all files
                const formData = new FormData();
                uploadedFiles.forEach((fileData, index) => {
                    formData.append('files[]', fileData.file);
                    formData.append('partner_ids[]', fileData.partnerId);
                    formData.append('source_types[]', fileData.sourceType);
                });
                formData.append('upload', '1');

                // Send to checker page via session storage
                sessionStorage.setItem('uploadedFilesData', JSON.stringify(uploadedFiles.map(f => ({
                    name: f.name,
                    partnerId: f.partnerId,
                    partnerName: f.partnerName,
                    sourceType: f.sourceType
                }))));

                // Send to checker page
                $.ajax({
                    url: '../../../models/saved/saved_billspayImportFile.php',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        // Redirect to validation page
                        window.location.href = '../../../models/saved/saved_billspayImportFile.php';
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
            });
        });
    </script>
</body>
<?php include '../../../templates/footer.php'; ?>


</html>
