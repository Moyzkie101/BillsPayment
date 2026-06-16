<?php
// Connect to the database
include '../../../config/config.php';
require '../../../vendor/autoload.php';

// Start the session
session_start();
@include_once __DIR__ . '/../../../templates/middleware.php';
$id = resolve_user_identifier();
if (empty($id)) { header('Location: ../../../login_form.php'); exit; }
if (!function_exists('has_any_permission') || !has_any_permission(['Import Transaction','Bills Payment'])) { header('Location: ../../home.php'); exit; }


// prefer explicit session values for current user email
$current_user_email = $_SESSION['admin_email'] ?? $_SESSION['user_email'] ?? '';

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
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script src="../../../assets/js/sweetalert2.all.min.js"></script>

    <link rel="icon" href="../../../images/MLW logo.png" type="image/png">
    <style>
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

        .mode-cards { display:flex; gap:8px; }
        .mode-card {
            border: 1px solid #e9ecef;
            padding: 8px 10px;
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
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        /* Individual File Card */
        .file-card {
            border: 1px solid #dee2e6;
            border-radius: 10px;
            padding: 14px;
            background-color: #fff;
            box-shadow: 0 2px 4px rgba(0,0,0,0.06);
            display: flex;
            flex-direction: column;
            gap: 8px;
            transition: all 0.2s ease;
            min-height: 96px;
            position: relative;
            overflow: hidden;
        }
        .file-card:hover { box-shadow: 0 6px 12px rgba(0,0,0,0.08); }

        .file-card-header { display:flex; gap:10px; align-items:flex-start; }

        .file-card-info { flex: 1 1 auto; }

        .file-card-label { font-size: 12px; color: #6c757d; font-weight:600; margin-bottom:4px; }
        .file-card-value { font-size: 14px; color:#212529; font-weight:600; word-break: break-word; }

        .file-card-delete { cursor:pointer; color:#6c757d; padding:6px; border-radius:6px; background: rgba(255,255,255,0.6); position:absolute; top:10px; right:10px; z-index:6; }
        .file-card-delete:hover { background:#f8f9fa; color:#dc3545; transform: none; }
        .file-card-view { cursor:pointer; color:#6c757d; padding:6px; border-radius:6px; background: rgba(255,255,255,0.6); position:absolute; top:10px; right:42px; z-index:6; }
        .file-card-view:hover { background:#f8f9fa; color:#0d6efd; transform: none; }

        .badge-source { padding:4px 8px; border-radius:6px; font-weight:700; font-size:12px; }
        .badge-kpx { background:#e9f7ef; color:#1e7e34; }
        .badge-kp7 { background:#eaf4ff; color:#1552c1; }
        /* Footer container stays inside card flow and is pushed to bottom */
        .file-card-footer {
            margin-top: auto;
            text-align: right;
            display: flex;
            gap: 8px;
            align-items: center;
            justify-content: flex-end;
            padding-top: 6px;
        }

        #loading-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(255, 255, 255, 0.78);
            z-index: 2000;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(2px);
        }

        #loading-overlay.show {
            display: flex;
        }

        .duplicate-modal {
            position: fixed;
            inset: 0;
            z-index: 3000;
            background: rgba(0, 0, 0, 0.35);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .duplicate-modal .duplicate-modal-content {
            width: 100%;
            max-width: 760px;
            background: #fff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 18px 40px rgba(0,0,0,0.25);
        }
        .duplicate-modal-header {
            background: #dc3545;
            color: #fff;
            padding: 16px 18px;
        }
        .duplicate-modal-header-title { display:flex; align-items:center; gap:10px; }
        .duplicate-modal-header-title h4 { margin: 0; font-size: 28px; font-weight: 800; }
        .duplicate-progress-bar-container { margin-top: 12px; height: 4px; background: rgba(255,255,255,0.35); border-radius: 4px; }
        .duplicate-progress-bar { height: 100%; width: 0%; border-radius: 4px; background: #ffd1d7; }
        .duplicate-modal-body { padding: 18px; background: #f3f4f6; }
        #duplicate-check-list {
            max-height: 260px;
            overflow-y: auto;
            scrollbar-width: none;
            -ms-overflow-style: none;
        }
        #duplicate-check-list::-webkit-scrollbar { width: 0; height: 0; }
        .check-item {
            background: #fff;
            border: 1px solid #cfe2ff;
            border-radius: 8px;
            padding: 14px 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        .check-item .name { color: #495057; font-weight: 600; font-size: 16px; }
        .check-item.success { border-color: #b7e4c7; background: #f0fff4; }
        .status-icon-success { color: #198754; font-size: 18px; }
        .check-item.fade-up {
            opacity: 0;
            transform: translateY(-10px);
            transition: opacity 260ms ease, transform 260ms ease;
        }
        .duplicate-modal-footer { padding: 12px 18px; border-top: 1px solid #e9ecef; }
        #duplicate-check-footer { display:flex; align-items:center; justify-content:space-between; font-weight:700; color:#344054; }
        .excel-detail-popup {
            width: min(96vw, 1400px) !important;
        }
        .excel-detail-table-wrap {
            max-height: 68vh;
            overflow: auto;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            background: #fff;
        }
        .excel-detail-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
            white-space: nowrap;
        }
        .excel-detail-table th,
        .excel-detail-table td {
            border-bottom: 1px solid #e9ecef;
            border-right: 1px solid #e9ecef;
            padding: 7px 8px;
            text-align: left;
            vertical-align: top;
        }
        .excel-detail-table th {
            position: sticky;
            top: 0;
            z-index: 2;
            background: #f8f9fa;
            font-weight: 700;
            color: #343a40;
        }
        .excel-detail-table td {
            color: #212529;
            max-width: 240px;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .excel-detail-table td.text-end {
            text-align: right;
        }
        .excel-detail-mode-bar {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin: -6px 0 14px;
            flex-wrap: wrap;
        }
        .excel-detail-mode-option {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            min-height: 34px;
            padding: 7px 12px;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            background: #fff;
            color: #212529;
            font-weight: 700;
            font-size: 13px;
            cursor: pointer;
        }
        .excel-detail-mode-option input {
            margin: 0;
        }
        .excel-detail-empty {
            padding: 22px;
            color: #6c757d;
            text-align: center;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="main-container">
        <?php include '../../../templates/header_ui.php'; ?>
        <!-- Show and Hide Side Nav Menu -->
        <?php include '../../../templates/sidebar.php'; ?>
        <div id="loading-overlay">
            <div class="text-center">
                <div class="spinner-border text-danger" role="status" aria-hidden="true"></div>
                <div class="mt-2 fw-semibold text-dark">Processing file(s)...</div>
            </div>
        </div>
        <div class="bp-section-header">
            <div class="bp-section-title">
                <i class="fa-solid fa-cloud-arrow-up" aria-hidden="true"></i>
                <div>
                    <h2>Import Transaction</h2>
                    <p class="bp-section-sub">Upload Excel files (.xls, .xlsx) for processing</p>
                </div>
            </div>
        </div>
        <div class="bp-card container-fluid mt-3 p-4">
            <div class="bp-card-body">
                <div class="mb-3 d-flex align-items-center justify-content-between" style="gap:12px;">
                    <div class="d-flex align-items-center" style="gap:12px;">
                        <label class="form-label me-2 mb-0">Import Mode:</label>
                        <div class="mode-cards">
                            <label class="mode-card selected" data-mode="auto">
                                <input type="radio" name="importMode" id="modeAuto" value="auto" checked style="display:none;">
                                <div class="mode-icon"><i class="fa-solid fa-cloud-arrow-up"></i></div>
                                <div class="mode-text">
                                    <div class="mode-label">Auto</div>
                                    <small>Drag & Drop</small>
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
                    <div id="proceedContainer" class="proceed-container mt-3" style="display: none;">
                        <button type="button" class="btn btn-danger btn-proceed" id="proceedBtn" data-bs-toggle="modal" data-bs-target="#proceedPreviewModal">
                            <i class="fa-solid fa-paper-plane me-2" aria-hidden="true"></i>Proceed
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
                <div id="filesContainer" class="files-container"></div>
            </div>
        </div>
    </div>
    <!-- File Card -->
    <script>
        var uploadedFiles = window.uploadedFiles || [];
        var parsedTransactionRows = window.parsedTransactionRows || [];
        var currentImportedBy = <?php echo json_encode(strval($_SESSION['admin_name'] ?? $_SESSION['user_name'] ?? '')); ?>;

        $(document).ready(function() {
            const fileUploadArea = $('#fileUploadArea');
            const fileInput = $('#fileInput');
            const filesContainer = $('#filesContainer');
            const proceedContainer = $('#proceedContainer');
            const proceedBtn = $('#proceedBtn');
            const loadingOverlay = $('#loading-overlay');

            function showLoadingOverlay() { loadingOverlay.addClass('show'); }
            function hideLoadingOverlay() { loadingOverlay.removeClass('show'); }
            function selectImportMode(mode) {
                $('input[name="importMode"][value="' + mode + '"]').prop('checked', true);
                $('.mode-card').removeClass('selected');
                $('.mode-card[data-mode="' + mode + '"]').addClass('selected');
            }

            $('.mode-card[data-mode="auto"]').on('click', function() {
                selectImportMode('auto');
            });

            $('.mode-card[data-mode="manual"]').on('click', function(event) {
                event.preventDefault();
                selectImportMode('manual');
                Swal.fire({
                    icon: 'info',
                    title: 'Under Maintenance',
                    text: 'Manual Form Upload is currently under maintenance.',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    allowEnterKey: false,
                    confirmButtonColor: '#6c757d'
                }).then(function() {
                    selectImportMode('auto');
                });
            });

            fileUploadArea.on('click', function() {
                if (fileInput.length && fileInput[0]) fileInput[0].click();
            });

            fileInput.on('change', function(e) {
                handleFiles(e.target.files);
            });

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

            function escapeHtml(str) {
                return String(str)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            }

            function normalizeHeader(value) {
                return String(value || '').replace(/"/g, '').replace(/\s+/g, ' ').trim().toUpperCase();
            }

            function isEmpty(value) {
                return value === null || value === undefined || String(value).trim() === '';
            }

            function getCellValue(sheet, address) {
                const cell = sheet[address];
                if (!cell) return '';
                return cell.w !== undefined ? String(cell.w).trim() : String(cell.v ?? '').trim();
            }

            function padDatePart(value) {
                return String(value).padStart(2, '0');
            }

            function formatDateYmd(dateValue) {
                if (!(dateValue instanceof Date) || Number.isNaN(dateValue.getTime())) return '';
                return [
                    dateValue.getFullYear(),
                    padDatePart(dateValue.getMonth() + 1),
                    padDatePart(dateValue.getDate())
                ].join('-');
            }

            function normalizeExcelDateCell(sheet, address) {
                const cell = sheet[address];
                if (!cell) return '';

                if (cell.v instanceof Date) {
                    return formatDateYmd(cell.v);
                }

                if (typeof cell.v === 'number' && window.XLSX && XLSX.SSF && typeof XLSX.SSF.parse_date_code === 'function') {
                    const parsedDate = XLSX.SSF.parse_date_code(cell.v);
                    if (parsedDate) {
                        return [
                            parsedDate.y,
                            padDatePart(parsedDate.m),
                            padDatePart(parsedDate.d)
                        ].join('-');
                    }
                }

                const rawValue = getCellValue(sheet, address);
                const parsedFromText = new Date(rawValue);
                if (!Number.isNaN(parsedFromText.getTime())) {
                    return formatDateYmd(parsedFromText);
                }

                return rawValue;
            }

            function getStatusMarker(sheet, row) {
                const rawStatus = getCellValue(sheet, 'A' + row);
                return rawStatus.includes('*') ? '*' : '';
            }

            function detectKp7HeaderIdentifier(sheet) {
                return normalizeHeader(getCellValue(sheet, 'A9')) === 'STATUS' && isEmpty(getCellValue(sheet, 'B9'));
            }

            function detectKpxHeaderIdentifier(sheet) {
                return normalizeHeader(getCellValue(sheet, 'A9')) === 'NO'
                    && normalizeHeader(getCellValue(sheet, 'B9')) === 'DATE / TIME'
                    && normalizeHeader(getCellValue(sheet, 'C9')) === 'CONTROL NO.'
                    && normalizeHeader(getCellValue(sheet, 'D9')) === 'REFERENCE NO.';
            }

            let branchJsonLookupPromise = null;
            async function getBranchJsonLookup() {
                if (branchJsonLookupPromise) return branchJsonLookupPromise;

                branchJsonLookupPromise = fetch('../../../branch.json', { credentials: 'same-origin' })
                    .then(function(response) {
                        if (!response.ok) throw new Error('branch.json HTTP error: ' + response.status);
                        return response.json();
                    })
                    .then(function(rows) {
                        const lookup = {};
                        (Array.isArray(rows) ? rows : []).forEach(function(row) {
                            const branchName = normalizeHeader(row && row.branch_name ? row.branch_name : '');
                            if (branchName !== '' && row && row.branch_id !== undefined && row.branch_id !== null) {
                                lookup[branchName] = row.branch_id;
                            }
                        });
                        return lookup;
                    })
                    .catch(function(err) {
                        console.error('[getBranchJsonLookup][exception]', err && err.message ? err.message : String(err));
                        return {};
                    });

                return branchJsonLookupPromise;
            }

            async function fillMissingBranchIdsFromOutlet(rows) {
                const branchLookup = await getBranchJsonLookup();
                (rows || []).forEach(function(row) {
                    if (!isEmpty(row.branch_id)) return;

                    const outletKey = normalizeHeader(row.branch_outlet || '');
                    if (outletKey !== '' && Object.prototype.hasOwnProperty.call(branchLookup, outletKey)) {
                        row.branch_id = branchLookup[outletKey];
                    }
                });

                return rows;
            }

            let regionJsonLookupPromise = null;
            async function getRegionJsonLookup() {
                if (regionJsonLookupPromise) return regionJsonLookupPromise;

                regionJsonLookupPromise = fetch('../../../region.json', { credentials: 'same-origin' })
                    .then(function(response) {
                        if (!response.ok) throw new Error('region.json HTTP error: ' + response.status);
                        return response.json();
                    })
                    .then(function(rows) {
                        const lookup = {};
                        (Array.isArray(rows) ? rows : []).forEach(function(row) {
                            const regionName = normalizeHeader(row && row.region_name ? row.region_name : '');
                            if (regionName !== '') {
                                lookup[regionName] = {
                                    region_code: row.region_code || null,
                                    zone_code: row.zone_code || null
                                };
                            }
                        });
                        return lookup;
                    })
                    .catch(function(err) {
                        console.error('[getRegionJsonLookup][exception]', err && err.message ? err.message : String(err));
                        return {};
                    });

                return regionJsonLookupPromise;
            }

            async function fillMissingKp7RegionCodesFromJson(rows) {
                const regionLookup = await getRegionJsonLookup();
                (rows || []).forEach(function(row) {
                    const isKp7 = String(row.source_type || '').toUpperCase() === 'KP7';
                    if (!isKp7 || (!isEmpty(row.region_code) && !isEmpty(row.zone_code))) return;

                    const regionKey = normalizeHeader(row.region_name || '');
                    const regionCodes = Object.prototype.hasOwnProperty.call(regionLookup, regionKey) ? regionLookup[regionKey] : null;
                    if (!regionCodes) return;

                    if (isEmpty(row.region_code)) row.region_code = regionCodes.region_code;
                    if (isEmpty(row.zone_code)) row.zone_code = regionCodes.zone_code;
                });

                return rows;
            }

            async function fillMissingKp7BranchIdsByCodeRegion(rows) {
                const kp7BranchLookups = [];
                (rows || []).forEach(function(row) {
                    const isKp7 = String(row.source_type || '').toUpperCase() === 'KP7';
                    const branchCode = String(row.branch_code || '').trim();
                    const regionCode = String(row.region_code || '').trim();
                    if (isKp7 && isEmpty(row.branch_id) && branchCode !== '' && regionCode !== '') {
                        kp7BranchLookups.push({
                            code: branchCode,
                            region_code: regionCode
                        });
                    }
                });

                if (kp7BranchLookups.length === 0) return rows;

                const branchCodeLookupMap = (await fetchBranchCodesByBranch([], [], kp7BranchLookups)).branch_codes || {};
                (rows || []).forEach(function(row) {
                    const isKp7 = String(row.source_type || '').toUpperCase() === 'KP7';
                    if (!isKp7 || !isEmpty(row.branch_id)) return;

                    const key = String(row.branch_code || '').trim() + '|' + String(row.region_code || '').trim();
                    const branchLookup = Object.prototype.hasOwnProperty.call(branchCodeLookupMap, key) ? branchCodeLookupMap[key] : null;
                    if (branchLookup && branchLookup.branch_id) {
                        row.branch_id = branchLookup.branch_id;
                    }
                });

                return rows;
            }

            function getBranchCodeFromReference(referenceNo) {
                const normalizedReference = String(referenceNo || '').trim().toUpperCase();
                const prefix = normalizedReference.substring(0, 3);
                if (prefix === 'BPP' || prefix === 'BPX') {
                    const branchCode = parseInt(normalizedReference.substring(3, 6), 10);
                    return Number.isNaN(branchCode) ? null : branchCode;
                }
                return null;
            }

            const excelDetailColumns = [
                { label: '$report_date', key: 'report_date' },
                { label: '$source_type', key: 'source_type' },
                { label: '$status', key: 'status' },
                { label: '$datetime', key: 'datetime' },
                { label: '$cancellation_date', key: 'cancellation_date' },
                { label: '$control_no', key: 'control_no' },
                { label: '$reference_no', key: 'reference_no' },
                { label: '$payor_name', key: 'payor_name' },
                { label: '$address', key: 'address' },
                { label: '$account_no', key: 'account_no' },
                { label: '$account_name', key: 'account_name' },
                { label: '$amount_paid', key: 'amount_paid' },
                { label: '$charge_customer', key: 'charge_customer' },
                { label: '$charge_partner', key: 'charge_partner' },
                { label: '$contact_no', key: 'contact_no' },
                { label: '$other_details', key: 'other_details' },
                { label: '$branch_id', key: 'branch_id' },
                { label: '$branch_code', key: 'branch_code' },
                { label: '$branch_outlet', key: 'branch_outlet' },
                { label: '$zone_code', key: 'zone_code' },
                { label: '$region_code', key: 'region_code' },
                { label: '$region_name', key: 'region_name' },
                { label: '$operator', key: 'operator' },
                { label: '$remote_branch', key: 'remote_branch' },
                { label: '$remote_operator', key: 'remote_operator' },
                { label: '$2nd_approver', key: 'second_approver' },
                { label: '$partner_name', key: 'partner_name' },
                { label: '$partner_id_kpx', key: 'partner_id_kpx' },
                { label: '$partner_id', key: 'partner_id' },
                { label: '$gl_code', key: 'gl_code' },
                { label: '$post_transaction', key: 'post_transaction' },
                { label: '$imported_date', key: 'imported_date' },
                { label: '$imported_by', key: 'imported_by' }
            ];

            const originalDetailColumns = [
                { label: 'Date / Time', key: 'date_time' },
                { label: 'Control No.', key: 'control_no' },
                { label: 'Reference No.', key: 'reference_no' },
                { label: 'Payor', key: 'payor' },
                { label: 'Address', key: 'address' },
                { label: 'Account No.', key: 'account_no' },
                { label: 'Account Name', key: 'account_name' },
                { label: 'Amount Paid', key: 'amount_paid' },
                { label: 'Charge to Customer', key: 'charge_customer' },
                { label: 'Charge to Partner', key: 'charge_partner' },
                { label: 'Contact No.', key: 'contact_no' },
                { label: 'Other Details', key: 'other_details' },
                { label: 'Branch ID', key: 'branch_id' },
                { label: 'ML Outlet', key: 'ml_outlet' },
                { label: 'Region Code', key: 'region_code' },
                { label: 'Region', key: 'region' },
                { label: 'Operator', key: 'operator' },
                { label: 'Remote Branch', key: 'remote_branch' },
                { label: 'Remote Operator', key: 'remote_operator' },
                { label: '2nd Approver', key: 'second_approver' },
                { label: 'Partner ID', key: 'partner_id' },
                { label: 'Partner Name', key: 'partner_name' }
            ];

            function formatDetailValue(value) {
                if (value === null || value === undefined || value === '') return '';
                return String(value);
            }

            function normalizeAmountValue(value) {
                return String(value ?? '').replace(/,/g, '').trim();
            }

            function formatAmountForDisplay(value) {
                const normalized = normalizeAmountValue(value);
                if (normalized === '' || Number.isNaN(Number(normalized))) return formatDetailValue(value);
                return Number(normalized).toLocaleString('en-US', {
                    minimumFractionDigits: normalized.includes('.') ? 2 : 0,
                    maximumFractionDigits: 2
                });
            }

            function formatIntegerForDisplay(value) {
                const numericValue = Number(value || 0);
                if (Number.isNaN(numericValue)) return '0';
                return Math.trunc(numericValue).toLocaleString('en-US', {
                    maximumFractionDigits: 0
                });
            }

            function getDisplayValue(row, key) {
                if (key === 'amount_paid' || key === 'charge_customer' || key === 'charge_partner') {
                    return formatAmountForDisplay(row[key]);
                }
                return formatDetailValue(row[key]);
            }

            function buildDetailTable(rows, columns, valueResolver, rowNumberResolver) {
                if (!Array.isArray(rows) || rows.length === 0) {
                    return '<div class="excel-detail-empty">No parsed Excel data available.</div>';
                }

                const headerHtml = '<th>No.</th>' + columns.map(function(col) {
                    return '<th>' + escapeHtml(col.label) + '</th>';
                }).join('');

                const bodyHtml = rows.map(function(row, rowIndex) {
                    const cells = columns.map(function(col) {
                        const displayValue = valueResolver(row, col.key);
                        const amountClass = (col.key === 'amount_paid' || col.key === 'charge_customer' || col.key === 'charge_partner') ? ' class="text-end"' : '';
                        return '<td' + amountClass + ' title="' + escapeHtml(displayValue) + '">' + escapeHtml(displayValue) + '</td>';
                    }).join('');
                    const rowNumber = typeof rowNumberResolver === 'function' ? rowNumberResolver(row, rowIndex) : formatIntegerForDisplay(rowIndex + 1);
                    return '<tr><td>' + escapeHtml(rowNumber) + '</td>' + cells + '</tr>';
                }).join('');

                return '<div class="excel-detail-table-wrap">'
                    + '<table class="excel-detail-table">'
                    + '<thead><tr>' + headerHtml + '</tr></thead>'
                    + '<tbody>' + bodyHtml + '</tbody>'
                    + '</table>'
                    + '</div>';
            }

            function buildExcelDetailTable(rows) {
                return buildDetailTable(rows, excelDetailColumns, getDisplayValue);
            }

            function buildOriginalDetailTable(rows) {
                return buildDetailTable(rows, originalDetailColumns, function(row, key) {
                    if (key === 'amount_paid' || key === 'charge_customer' || key === 'charge_partner') {
                        return formatAmountForDisplay(row[key]);
                    }
                    return formatDetailValue(row[key]);
                }, function(row) {
                    return formatDetailValue(row.excel_no);
                });
            }

            function buildExcelDetailModeControls() {
                return '<div class="excel-detail-mode-bar">'
                    + '<label class="excel-detail-mode-option">'
                    + '<input type="radio" name="excelDetailDataMode" value="original" checked>'
                    + '<span>Original Data Mode</span>'
                    + '</label>'
                    + '<label class="excel-detail-mode-option">'
                    + '<input type="radio" name="excelDetailDataMode" value="developer">'
                    + '<span>Developer Data Mode</span>'
                    + '</label>'
                    + '</div>';
            }

            function buildExcelDetailContent(fileData, mode) {
                const selectedMode = mode === 'original' ? 'original' : 'developer';
                const originalRows = fileData && Array.isArray(fileData.originalRows) ? fileData.originalRows : [];
                const developerRows = fileData && Array.isArray(fileData.parsedRows) ? fileData.parsedRows : [];
                return selectedMode === 'original' ? buildOriginalDetailTable(originalRows) : buildExcelDetailTable(developerRows);
            }

            async function fetchBranchCodesByBranch(branchIds, regionNames, branchCodeLookups) {
                const uniqueBranchIds = Array.from(new Set((branchIds || [])
                    .map(function(branchId) { return String(branchId || '').trim(); })
                    .filter(function(branchId) { return branchId !== ''; })));
                const uniqueRegionNames = Array.from(new Set((regionNames || [])
                    .map(function(regionName) { return String(regionName || '').trim(); })
                    .filter(function(regionName) { return regionName !== ''; })));
                const branchCodeLookupList = Array.isArray(branchCodeLookups) ? branchCodeLookups : [];

                if (uniqueBranchIds.length === 0 && uniqueRegionNames.length === 0 && branchCodeLookupList.length === 0) return {};

                try {
                    const response = await fetch('get-region-code-by-branch.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        credentials: 'same-origin',
                        body: JSON.stringify({
                            branch_ids: uniqueBranchIds,
                            region_names: uniqueRegionNames,
                            branch_code_lookups: branchCodeLookupList
                        })
                    });

                    if (!response.ok) {
                        console.error('get-region-code-by-branch.php HTTP error:', response.status);
                        return {};
                    }

                    const result = await response.json();
                    if (!result || result.success !== true || (!result.branches && !result.regions && !result.region_names)) {
                        console.error('get-region-code-by-branch.php error:', result && result.error);
                        return {};
                    }

                    if (result.branches || result.region_names) {
                        return {
                            branches: result.branches || {},
                            region_names: result.region_names || {},
                            branch_codes: result.branch_codes || {}
                        };
                    }

                    const branchMap = {};
                    Object.keys(result.regions || {}).forEach(function(branchId) {
                        branchMap[branchId] = {
                            region_code: result.regions[branchId],
                            zone_code: null
                        };
                    });
                    return {
                        branches: branchMap,
                        region_names: {},
                        branch_codes: {}
                    };
                } catch (err) {
                    console.error('[fetchBranchCodesByBranch][exception]', err && err.message ? err.message : String(err));
                    return {};
                }
            }

            async function enrichRowsWithBranchCodes(rows) {
                const lookupMap = await fetchBranchCodesByBranch((rows || []).map(function(row) {
                    return row.branch_id;
                }), (rows || []).map(function(row) {
                    return String(row.source_type || '').toUpperCase() === 'KP7' ? row.region_name : '';
                }));
                const branchMap = lookupMap.branches || {};
                const regionNameMap = lookupMap.region_names || {};

                (rows || []).forEach(function(row) {
                    const isKp7 = String(row.source_type || '').toUpperCase() === 'KP7';
                    const lookupKey = isKp7 ? String(row.region_name || '').trim() : String(row.branch_id || '').trim();
                    const lookupSource = isKp7 ? regionNameMap : branchMap;
                    const branchCodes = Object.prototype.hasOwnProperty.call(lookupSource, lookupKey) ? lookupSource[lookupKey] : null;
                    row.region_code = branchCodes ? branchCodes.region_code : null;
                    row.zone_code = branchCodes ? branchCodes.zone_code : null;
                });

                return rows;
            }

            function getPartnerLookupKey(row) {
                const sourceType = String(row && row.source_type ? row.source_type : '').trim().toUpperCase();
                const partnerId = String(row && row.partner_id ? row.partner_id : '').trim();
                const partnerIdKpx = String(row && row.partner_id_kpx ? row.partner_id_kpx : '').trim();
                const partnerName = String(row && row.partner_name ? row.partner_name : '').trim();
                if (sourceType === 'KP7' && partnerId !== '') return 'kp7:' + partnerId;
                return partnerIdKpx !== '' ? 'kpx:' + partnerIdKpx : 'name:' + partnerName;
            }

            async function fetchPartnerCodes(rows) {
                const partnerMap = {};
                (rows || []).forEach(function(row) {
                    const key = getPartnerLookupKey(row);
                    if (key === 'name:' || partnerMap[key]) return;
                    partnerMap[key] = {
                        key: key,
                        source_type: String(row.source_type || '').trim(),
                        partner_id: String(row.partner_id || '').trim(),
                        partner_id_kpx: String(row.partner_id_kpx || '').trim(),
                        partner_name: String(row.partner_name || '').trim()
                    };
                });

                const partners = Object.values(partnerMap);
                if (partners.length === 0) return {};

                try {
                    const response = await fetch('get-partner-codes.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        credentials: 'same-origin',
                        body: JSON.stringify({ partners: partners })
                    });

                    if (!response.ok) {
                        console.error('get-partner-codes.php HTTP error:', response.status);
                        return {};
                    }

                    const result = await response.json();
                    if (!result || result.success !== true || !result.partners) {
                        console.error('get-partner-codes.php error:', result && result.error);
                        return {};
                    }

                    return result.partners;
                } catch (err) {
                    console.error('[fetchPartnerCodes][exception]', err && err.message ? err.message : String(err));
                    return {};
                }
            }

            async function enrichRowsWithPartnerCodes(rows) {
                const partnerMap = await fetchPartnerCodes(rows);

                (rows || []).forEach(function(row) {
                    const key = getPartnerLookupKey(row);
                    const partnerCodes = Object.prototype.hasOwnProperty.call(partnerMap, key) ? partnerMap[key] : null;
                    row.partner_id = partnerCodes ? partnerCodes.partner_id : row.partner_id;
                    row.gl_code = partnerCodes ? partnerCodes.gl_code : row.gl_code;
                    if (isEmpty(row.partner_id_kpx) && partnerCodes && !isEmpty(partnerCodes.partner_id_kpx)) {
                        row.partner_id_kpx = partnerCodes.partner_id_kpx;
                    }
                });

                return rows;
            }

            function buildDebugImportPayload() {
                return {
                    files: uploadedFiles.map(function(fileData) {
                        return {
                            filename: fileData.name,
                            file_source_type: fileData.sourceType,
                            rows: (fileData.parsedRows || []).map(function(row) {
                                return {
                                    filename: fileData.name,
                                    file_source_type: fileData.sourceType,
                                    report_date: row.report_date,
                                    source_type: row.source_type,
                                    status: row.status,
                                    datetime: row.datetime,
                                    cancellation_date: row.cancellation_date,
                                    control_no: row.control_no,
                                    reference_no: row.reference_no,
                                    payor_name: row.payor_name,
                                    address: row.address,
                                    account_no: row.account_no,
                                    account_name: row.account_name,
                                    amount_paid: row.amount_paid,
                                    charge_customer: row.charge_customer,
                                    charge_partner: row.charge_partner,
                                    contact_no: row.contact_no,
                                    other_details: row.other_details,
                                    branch_id: row.branch_id,
                                    branch_code: row.branch_code,
                                    branch_outlet: row.branch_outlet,
                                    zone_code: row.zone_code,
                                    region_code: row.region_code,
                                    region_name: row.region_name,
                                    operator: row.operator,
                                    remote_branch: row.remote_branch,
                                    remote_operator: row.remote_operator,
                                    second_approver: row.second_approver,
                                    '2nd_approver': row.second_approver,
                                    partner_name: row.partner_name,
                                    partner_id_kpx: row.partner_id_kpx,
                                    partner_id: row.partner_id,
                                    gl_code: row.gl_code,
                                    post_transaction: row.post_transaction,
                                    imported_date: row.imported_date,
                                    imported_by: row.imported_by
                                };
                            })
                        };
                    })
                };
            }

            async function submitDebugImportPayload() {
                const response = await fetch('../../../models/saved/saved_billspayImportFile_NEW.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify(buildDebugImportPayload())
                });

                const result = await response.json();
                if (!response.ok || !result || result.success !== true) {
                    throw new Error(result && result.error ? result.error : 'Unable to validate parsed JSON payload.');
                }

                return result;
            }

            function showExcelDetails(fileData) {
                Swal.fire({
                    title: escapeHtml(fileData && fileData.name ? fileData.name : 'Excel Data Details'),
                    html: buildExcelDetailModeControls() + '<div id="excelDetailTableHost">' + buildExcelDetailContent(fileData, 'original') + '</div>',
                    width: '96vw',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    allowEnterKey: false,
                    customClass: {
                        popup: 'excel-detail-popup'
                    },
                    showConfirmButton: true,
                    confirmButtonText: 'Close',
                    didOpen: function() {
                        const tableHost = document.getElementById('excelDetailTableHost');
                        document.querySelectorAll('input[name="excelDetailDataMode"]').forEach(function(input) {
                            input.addEventListener('change', function() {
                                if (tableHost) {
                                    tableHost.innerHTML = buildExcelDetailContent(fileData, input.value);
                                }
                            });
                        });
                    }
                });
            }

            function getStandardHeaderMap(isAllPartners, sourceType) {
                const normalizedSourceType = normalizeHeader(sourceType);
                if (isAllPartners && normalizedSourceType === 'KP7') {
                    return {
                        C: 'DATE / TIME',
                        D: 'CONTROL NO.',
                        E: 'REFERENCE NO.',
                        F: 'PAYOR',
                        G: 'ADDRESS',
                        H: 'ACCOUNT NO.',
                        I: '"ACCOUNT NAME"',
                        J: '"AMOUNT PAID"',
                        K: 'CHARGE TO PARTNER',
                        L: 'CHARGE TO CUSTOMER',
                        M: '"CONTACT NO."',
                        N: 'OTHER DETAILS',
                        O: 'ML OUTLET',
                        P: 'Region',
                        Q: 'OPERATOR',
                        R: 'PARTNER NAME',
                        S: 'PARTNER ID'
                    };
                }

                if (isAllPartners) {
                    return {
                        B: 'Date / Time',
                        C: 'Control No.',
                        D: 'Reference No.',
                        E: 'Payor',
                        F: 'Address',
                        G: 'Account No.',
                        H: 'Account Name',
                        I: 'Amount Paid',
                        J: 'Charge to Customer',
                        K: 'Charge to Partner',
                        L: 'Other Details',
                        M: 'Branch ID',
                        N: 'ML Outlet',
                        O: 'Region Code',
                        P: 'Region',
                        Q: 'Operator',
                        R: 'Remote Branch',
                        S: 'Remote Operator',
                        T: '2nd Approver',
                        U: 'Partner ID',
                        V: 'Partner Name'
                    };
                }

                return {
                    B: 'Date / Time',
                    C: 'Control No.',
                    D: 'Reference No.',
                    E: 'Payor',
                    F: 'Address',
                    G: 'Account No.',
                    H: 'Account Name',
                    I: 'Amount Paid',
                    J: 'Charge to Customer',
                    K: 'Charge to Partner',
                    L: 'Contact No.',
                    M: 'Other Details',
                    N: 'Branch ID',
                    O: 'ML Outlet',
                    P: 'Region Code',
                    Q: 'Region',
                    R: 'Operator',
                    S: 'Remote Branch',
                    T: 'Remote Operator',
                    U: '2nd Approver',
                };
            }

            function validateHeaderRow(sheet, headerMap) {
                const mismatches = [];
                Object.keys(headerMap).forEach(function(col) {
                    const actual = getCellValue(sheet, col + '9');
                    const expected = headerMap[col];
                    if (normalizeHeader(actual) !== normalizeHeader(expected)) {
                        mismatches.push({
                            cell: col + '9',
                            expected: expected,
                            actual: actual
                        });
                    }
                });
                return mismatches;
            }

            function getWorksheetLastRow(sheet) {
                if (!sheet || !sheet['!ref']) return 0;
                return XLSX.utils.decode_range(sheet['!ref']).e.r + 1;
            }

            function hasTransactionData(sheet, row, isAllPartners) {
                const columns = isAllPartners
                    ? ['B','C','D','E','G','I','M','U','V']
                    : ['B','C','D','E','G','I','N'];
                return columns.some(function(col) {
                    return !isEmpty(getCellValue(sheet, col + row));
                });
            }

            function buildOriginalDataRow(sheet, row, isAllPartners, partnerCell, sourceType) {
                const normalizedSourceType = normalizeHeader(sourceType);
                if (isAllPartners && normalizedSourceType === 'KP7') {
                    return {
                        excel_no: getCellValue(sheet, 'A' + row),
                        date_time: getCellValue(sheet, 'C' + row),
                        control_no: getCellValue(sheet, 'D' + row),
                        reference_no: getCellValue(sheet, 'E' + row),
                        payor: getCellValue(sheet, 'F' + row),
                        address: getCellValue(sheet, 'G' + row),
                        account_no: getCellValue(sheet, 'H' + row),
                        account_name: getCellValue(sheet, 'I' + row),
                        amount_paid: getCellValue(sheet, 'J' + row),
                        charge_customer: getCellValue(sheet, 'L' + row),
                        charge_partner: getCellValue(sheet, 'K' + row),
                        contact_no: getCellValue(sheet, 'M' + row),
                        other_details: getCellValue(sheet, 'N' + row),
                        branch_id: '',
                        ml_outlet: getCellValue(sheet, 'O' + row),
                        region_code: '',
                        region: getCellValue(sheet, 'P' + row),
                        operator: getCellValue(sheet, 'Q' + row),
                        remote_branch: '',
                        remote_operator: '',
                        second_approver: '',
                        partner_id: getCellValue(sheet, 'S' + row),
                        partner_name: getCellValue(sheet, 'R' + row)
                    };
                }

                if (isAllPartners) {
                    return {
                        excel_no: getCellValue(sheet, 'A' + row),
                        date_time: getCellValue(sheet, 'B' + row),
                        control_no: getCellValue(sheet, 'C' + row),
                        reference_no: getCellValue(sheet, 'D' + row),
                        payor: getCellValue(sheet, 'E' + row),
                        address: getCellValue(sheet, 'F' + row),
                        account_no: getCellValue(sheet, 'G' + row),
                        account_name: getCellValue(sheet, 'H' + row),
                        amount_paid: getCellValue(sheet, 'I' + row),
                        charge_customer: getCellValue(sheet, 'J' + row),
                        charge_partner: getCellValue(sheet, 'K' + row),
                        contact_no: '',
                        other_details: getCellValue(sheet, 'L' + row),
                        branch_id: getCellValue(sheet, 'M' + row),
                        ml_outlet: getCellValue(sheet, 'N' + row),
                        region_code: getCellValue(sheet, 'O' + row),
                        region: getCellValue(sheet, 'P' + row),
                        operator: getCellValue(sheet, 'Q' + row),
                        remote_branch: getCellValue(sheet, 'R' + row),
                        remote_operator: getCellValue(sheet, 'S' + row),
                        second_approver: getCellValue(sheet, 'T' + row),
                        partner_id: getCellValue(sheet, 'U' + row),
                        partner_name: getCellValue(sheet, 'V' + row)
                    };
                }

                return {
                    excel_no: getCellValue(sheet, 'A' + row),
                    date_time: getCellValue(sheet, 'B' + row),
                    control_no: getCellValue(sheet, 'C' + row),
                    reference_no: getCellValue(sheet, 'D' + row),
                    payor: getCellValue(sheet, 'E' + row),
                    address: getCellValue(sheet, 'F' + row),
                    account_no: getCellValue(sheet, 'G' + row),
                    account_name: getCellValue(sheet, 'H' + row),
                    amount_paid: getCellValue(sheet, 'I' + row),
                    charge_customer: getCellValue(sheet, 'J' + row),
                    charge_partner: getCellValue(sheet, 'K' + row),
                    contact_no: getCellValue(sheet, 'L' + row),
                    other_details: getCellValue(sheet, 'M' + row),
                    branch_id: getCellValue(sheet, 'N' + row),
                    ml_outlet: getCellValue(sheet, 'O' + row),
                    region_code: getCellValue(sheet, 'P' + row),
                    region: getCellValue(sheet, 'Q' + row),
                    operator: getCellValue(sheet, 'R' + row),
                    remote_branch: getCellValue(sheet, 'S' + row),
                    remote_operator: getCellValue(sheet, 'T' + row),
                    second_approver: getCellValue(sheet, 'U' + row),
                    partner_id: '',
                    partner_name: partnerCell
                };
            }

            function isTransactionRowEmpty(sheet, row, isAllPartners) {
                const columns = isAllPartners
                    ? ['A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V']
                    : ['A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U'];
                return columns.every(function(col) {
                    return isEmpty(getCellValue(sheet, col + row));
                });
            }

            function parseKpxRowsFromSheet(sheet, sourceType) {
                const partnerCell = getCellValue(sheet, 'B4');
                const normalizedSourceType = normalizeHeader(sourceType);
                const isAllPartners = normalizeHeader(partnerCell) === 'ALL PARTNERS' || normalizedSourceType === 'KP7';
                const headerMap = getStandardHeaderMap(isAllPartners, sourceType);
                const headerErrors = validateHeaderRow(sheet, headerMap);

                if (headerErrors.length > 0) {
                    return {
                        success: false,
                        isAllPartners: isAllPartners,
                        headerErrors: headerErrors,
                        rows: []
                    };
                }

                const reportDate = normalizeExcelDateCell(sheet, 'B3');
                const importedDate = new Date().toISOString().slice(0, 10);
                const lastRow = getWorksheetLastRow(sheet);
                const rows = [];
                const originalRows = [];

                for (let row = 10; row <= lastRow; row++) {
                    if (isTransactionRowEmpty(sheet, row, isAllPartners)) break;
                    if (!hasTransactionData(sheet, row, isAllPartners)) continue;

                    const status = getStatusMarker(sheet, row);
                    const isCancelled = status === '*';
                    const isKp7 = normalizedSourceType === 'KP7';
                    const branchId = isKp7 ? null : getCellValue(sheet, (isAllPartners ? 'M' : 'N') + row);
                    const partnerName = isKp7 ? getCellValue(sheet, 'R' + row) : (isAllPartners ? getCellValue(sheet, 'V' + row) : partnerCell);
                    const partnerIdKpx = isKp7 ? null : (isAllPartners ? getCellValue(sheet, 'U' + row) : null);
                    const partnerId = isKp7 ? getCellValue(sheet, 'S' + row) : null;
                    const referenceNo = getCellValue(sheet, (isKp7 ? 'E' : 'D') + row);

                    rows.push({
                        report_date: reportDate,
                        source_type: sourceType,
                        status: status,
                        datetime: getCellValue(sheet, (isKp7 ? 'C' : 'B') + row),
                        cancellation_date: isCancelled ? reportDate : null,
                        control_no: getCellValue(sheet, (isKp7 ? 'D' : 'C') + row),
                        reference_no: referenceNo,
                        payor_name: getCellValue(sheet, (isKp7 ? 'F' : 'E') + row),
                        address: getCellValue(sheet, (isKp7 ? 'G' : 'F') + row),
                        account_no: getCellValue(sheet, (isKp7 ? 'H' : 'G') + row),
                        account_name: getCellValue(sheet, (isKp7 ? 'I' : 'H') + row),
                        amount_paid: normalizeAmountValue(getCellValue(sheet, (isKp7 ? 'J' : 'I') + row)),
                        charge_customer: normalizeAmountValue(getCellValue(sheet, (isKp7 ? 'L' : 'J') + row)),
                        charge_partner: normalizeAmountValue(getCellValue(sheet, (isKp7 ? 'K' : 'K') + row)),
                        contact_no: isKp7 ? getCellValue(sheet, 'M' + row) : (isAllPartners ? null : getCellValue(sheet, 'L' + row)),
                        other_details: getCellValue(sheet, (isKp7 ? 'N' : (isAllPartners ? 'L' : 'M')) + row),
                        branch_id: branchId,
                        branch_code: isKp7 ? getBranchCodeFromReference(referenceNo) : null,
                        branch_outlet: getCellValue(sheet, (isKp7 ? 'O' : (isAllPartners ? 'N' : 'O')) + row),
                        zone_code: null,
                        region_code: null,
                        region_code_lookup: {
                            table: 'masterdata.branch_profile',
                            branch_id: branchId
                        },
                        region_name: getCellValue(sheet, (isKp7 ? 'P' : (isAllPartners ? 'P' : 'Q')) + row),
                        operator: getCellValue(sheet, (isKp7 ? 'Q' : (isAllPartners ? 'Q' : 'R')) + row),
                        remote_branch: isKp7 ? null : getCellValue(sheet, (isAllPartners ? 'R' : 'S') + row),
                        remote_operator: isKp7 ? null : getCellValue(sheet, (isAllPartners ? 'S' : 'T') + row),
                        second_approver: isKp7 ? null : getCellValue(sheet, (isAllPartners ? 'T' : 'U') + row),
                        partner_name: partnerName,
                        partner_id_kpx: partnerIdKpx,
                        partner_id: partnerId,
                        gl_code: null,
                        partner_lookup: isKp7 && !isEmpty(partnerId)
                            ? { table: 'masterdata.partner_masterfile', partner_id: partnerId }
                            : isAllPartners && !isEmpty(partnerIdKpx)
                            ? { table: 'masterdata.partner_masterfile', partner_id_kpx: partnerIdKpx }
                            : { table: 'masterdata.partner_masterfile', partner_name: partnerName },
                        post_transaction: 'unposted',
                        imported_date: importedDate,
                        imported_by: currentImportedBy,
                        source_row: row
                    });
                    originalRows.push(buildOriginalDataRow(sheet, row, isAllPartners, partnerCell, sourceType));
                }

                return {
                    success: true,
                    isAllPartners: isAllPartners,
                    headerErrors: [],
                    rows: rows,
                    originalRows: originalRows
                };
            }

            function readWorkbook(file) {
                return new Promise(function(resolve, reject) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        try {
                            const data = new Uint8Array(e.target.result);
                            resolve(XLSX.read(data, { type: 'array', cellDates: true }));
                        } catch (err) {
                            reject(err);
                        }
                    };
                    reader.onerror = function() { reject(reader.error); };
                    reader.readAsArrayBuffer(file);
                });
            }

            async function parseTransactionFile(file, sourceType) {
                const workbook = await readWorkbook(file);
                const sheetName = workbook.SheetNames[0];
                const sheet = workbook.Sheets[sheetName];
                let effectiveSourceType = sourceType;
                if (sourceType === 'UNKNOWN') {
                    effectiveSourceType = detectKp7HeaderIdentifier(sheet)
                        ? 'KP7'
                        : (detectKpxHeaderIdentifier(sheet) ? 'KPX' : sourceType);
                }

                if (effectiveSourceType !== 'KPX' && effectiveSourceType !== 'KP7') {
                    return {
                        success: false,
                        rows: [],
                        headerErrors: [],
                        message: 'Only KPX and KP7 parsing are configured in this debug page.'
                    };
                }

                const parsed = parseKpxRowsFromSheet(sheet, effectiveSourceType);
                if (parsed.success) {
                    parsed.rows = await fillMissingBranchIdsFromOutlet(parsed.rows);
                    parsed.rows = await enrichRowsWithBranchCodes(parsed.rows);
                    parsed.rows = await fillMissingKp7RegionCodesFromJson(parsed.rows);
                    parsed.rows = await fillMissingKp7BranchIdsByCodeRegion(parsed.rows);
                    parsed.rows = await enrichRowsWithPartnerCodes(parsed.rows);
                }
                parsed.sheetName = sheetName;
                return parsed;
            }

            async function detectSourceType(file) {
                try {
                    const formData = new FormData();
                    formData.append('file', file);

                    const response = await fetch('detect-source-type.php', {
                        method: 'POST',
                        body: formData,
                        credentials: 'same-origin'
                    });

                    if (!response.ok) {
                        console.error('detect-source-type.php HTTP error:', response.status);
                        return 'UNKNOWN';
                    }

                    const result = await response.json();
                    console.log('[detectSourceType][response]', {
                        fileName: file && file.name ? file.name : '',
                        fileSize: file && file.size ? file.size : 0,
                        httpStatus: response.status,
                        payload: result
                    });
                    if (!result || result.success !== true) {
                        console.error('detect-source-type.php error:', result && result.error);
                        return 'UNKNOWN';
                    }

                    const detected = String(result.sourceType || 'UNKNOWN').toUpperCase();
                    console.log('[detectSourceType][final]', {
                        fileName: file && file.name ? file.name : '',
                        sourceType: detected
                    });
                    return detected;
                } catch (err) {
                    console.error('[detectSourceType][exception]', {
                        fileName: file && file.name ? file.name : '',
                        message: err && err.message ? err.message : String(err)
                    });
                    return 'UNKNOWN';
                }
            }

            async function handleFiles(files) {
                showLoadingOverlay();
                try {
                    const fileArray = Array.from(files || []);
                    const excelFiles = fileArray.filter(file => {
                        const extension = file.name.split('.').pop().toLowerCase();
                        return extension === 'xls' || extension === 'xlsx';
                    });

                    if (excelFiles.length !== fileArray.length) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Invalid File Type',
                            text: 'Please select only Excel files (.xls, .xlsx)'
                        });
                    }

                    for (const file of excelFiles) {
                        if (!uploadedFiles.find(f => f.name === file.name && f.size === file.size && f.lastModified === file.lastModified)) {
                            const sourceType = await detectSourceType(file);
                            let parsedData;
                            try {
                                parsedData = await parseTransactionFile(file, sourceType);
                            } catch (err) {
                                console.error('[parseTransactionFile][exception]', {
                                    fileName: file && file.name ? file.name : '',
                                    message: err && err.message ? err.message : String(err)
                                });
                                parsedData = {
                                    success: false,
                                    rows: [],
                                    headerErrors: [],
                                    message: 'Failed to read Excel rows.'
                                };
                            }
                            const fileData = {
                                name: file.name,
                                size: file.size,
                                lastModified: file.lastModified,
                                file: file,
                                sourceType: sourceType,
                                parsedRows: parsedData.rows || [],
                                originalRows: parsedData.originalRows || [],
                                parsedMeta: parsedData
                            };

                            uploadedFiles.push(fileData);
                            parsedTransactionRows = parsedTransactionRows.concat(fileData.parsedRows);
                            window.parsedTransactionRows = parsedTransactionRows;

                            if (!parsedData.success) {
                                const headerText = parsedData.headerErrors && parsedData.headerErrors.length
                                    ? parsedData.headerErrors.map(err => `${err.cell}: expected "${err.expected}", got "${err.actual || '(blank)'}"`).join('\n')
                                    : (parsedData.message || 'Unable to parse transaction rows.');
                                Swal.fire({
                                    icon: 'warning',
                                    title: 'Header Validation Failed',
                                    text: `${file.name}\n${headerText}`
                                });
                            }
                        }
                    }

                    renderFiles();
                } finally {
                    hideLoadingOverlay();
                }
            }

            function renderFiles() {
                filesContainer.empty();
                uploadedFiles.forEach((item, index) => {
                    const parsedCount = Array.isArray(item.parsedRows) ? item.parsedRows.length : 0;
                    const parseStatus = item.parsedMeta && item.parsedMeta.success ? `${formatIntegerForDisplay(parsedCount)} row(s)` : '-';
                    filesContainer.append(`
                        <div class="file-card">
                            <span class="file-card-view" data-index="${index}" title="View Excel data details"><i class="fa-solid fa-eye"></i></span>
                            <span class="file-card-delete" data-index="${index}"><i class="fa-solid fa-xmark"></i></span>
                            <div class="file-card-header">
                                <div class="file-card-info">
                                    <div class="file-card-label">File Name</div>
                                    <div class="file-card-value">${escapeHtml(item.name)}</div>
                                </div>
                            </div>
                            <div class="file-card-body"></div>
                            <div class="file-card-footer">
                                <div class="file-card-detail">
                                    <div class="file-card-label">No. of Data Row(s)</div>
                                    <div class="file-card-value">${escapeHtml(parseStatus)}</div>
                                </div>
                                <div class="file-card-detail">
                                    <div class="file-card-label">Source Type</div>
                                    <div class="file-card-value">
                                        <span class="badge-source ${item.sourceType === 'KP7' ? 'badge-kp7' : (item.sourceType === 'KPX' ? 'badge-kpx' : '')}">${escapeHtml(item.sourceType || 'UNKNOWN')}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `);
                });

                if (uploadedFiles.length > 0) {
                    proceedContainer.show();
                    const count = uploadedFiles.length;
                    proceedBtn.html(`<i class="fa-solid fa-paper-plane me-2" aria-hidden="true"></i>${count > 1 ? `Proceed (${count})` : 'Proceed'}`);
                } else {
                    proceedContainer.hide();
                }
            }

            $(document).on('click', '.file-card-view', function(e) {
                e.stopPropagation();
                const index = Number($(this).data('index'));
                showExcelDetails(uploadedFiles[index]);
            });

            $(document).on('click', '.file-card-delete', function(e) {
                e.stopPropagation();
                const index = Number($(this).data('index'));
                uploadedFiles.splice(index, 1);
                parsedTransactionRows = uploadedFiles.reduce(function(rows, fileData) {
                    return rows.concat(fileData.parsedRows || []);
                }, []);
                window.parsedTransactionRows = parsedTransactionRows;
                renderFiles();
            });

            proceedBtn.on('click', function(e) {
                e.preventDefault();
                if (uploadedFiles.length === 0) return;

                $('.duplicate-modal').remove();
                const total = uploadedFiles.length;
                const modalHtml = '<div class="duplicate-modal">'
                    + '<div class="duplicate-modal-content">'
                    + '<div class="duplicate-modal-header">'
                    + '<div class="duplicate-modal-header-title">'
                    + '<i class="fa-solid fa-shield-halved"></i>'
                    + '<h6 id="duplicate-check-header">Checking files (0/' + total + ')</h6>'
                    + '</div>'
                    + '<div class="duplicate-progress-bar-container"><div class="duplicate-progress-bar" id="duplicate-progress-bar"></div></div>'
                    + '</div>'
                    + '<div class="duplicate-modal-body"><div id="duplicate-check-list"></div></div>'
                    + '<div class="duplicate-modal-footer">'
                    + '<div id="duplicate-check-footer"><span><i class="fa-solid fa-file-circle-check text-danger"></i> Validating files</span><span id="duplicate-progress-text"><strong>0</strong> / ' + total + '</span></div>'
                    + '</div></div></div>';
                $('body').append(modalHtml);

                const $list = $('#duplicate-check-list');
                uploadedFiles.forEach(function(fileData, idx) {
                    $list.append('<div class="check-item checking" data-idx="' + idx + '"><div class="name">' + escapeHtml(fileData.name) + '</div><div class="status"><i class="fa-solid fa-spinner fa-spin"></i></div></div>');
                });
                $('.duplicate-modal').on('wheel mousewheel DOMMouseScroll', function(evt) {
                    evt.preventDefault();
                    evt.stopPropagation();
                    return false;
                });
                let processedCount = 0;
                const finishDebugValidation = async function() {
                    try {
                        showLoadingOverlay();
                        const result = await submitDebugImportPayload();
                        window.location.href = '../../../models/saved/saved_billspayImportFile_NEW.php';
                    } catch (err) {
                        $('.duplicate-modal').remove();
                        Swal.fire({
                            icon: 'error',
                            title: 'Validation Error',
                            text: err && err.message ? err.message : 'Unable to validate parsed JSON payload.'
                        });
                    } finally {
                        hideLoadingOverlay();
                    }
                };
                const runVisualCheck = function(index) {
                    if (index >= total) {
                        finishDebugValidation();
                        return;
                    }
                    const $item = $list.find('.check-item[data-idx="' + index + '"]');
                    if ($item.length) {
                        $item.removeClass('checking').addClass('success');
                        $item.find('.status').html('<i class="fa-solid fa-circle-check status-icon-success"></i>');
                        setTimeout(function() {
                            $item.addClass('fade-up');
                            setTimeout(function() {
                                $item.remove();
                                processedCount++;
                                $('#duplicate-check-header').text('Checking files (' + processedCount + '/' + total + ')');
                                $('#duplicate-progress-text').html('<strong>' + processedCount + '</strong> / ' + total);
                                $('#duplicate-progress-bar').css('width', ((processedCount / total) * 100) + '%');
                                runVisualCheck(index + 1);
                            }, 280);
                        }, 420);
                    } else {
                        runVisualCheck(index + 1);
                    }
                };
                setTimeout(function() { runVisualCheck(0); }, 450);

                if (window.bootstrap && typeof window.bootstrap.Modal === 'function') {
                    // no-op: UI uses custom modal structure to match checking-files concept
                }
            });
        });
    </script>


</body>
<?php include '../../../templates/footer.php'; ?>
</html>
