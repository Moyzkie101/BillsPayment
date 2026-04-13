(function () {
    function byId(id) {
        return document.getElementById(id);
    }

    function parseCurrencyToNumber(str) {
        if (str == null) return NaN;
        var s = String(str).replace(/,/g, '').trim();
        if (s === '') return NaN;
        return parseFloat(s);
    }

    function formatCurrencyNumber(num) {
        if (num == null || isNaN(num)) return '';
        return Number(num).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function formatInputCurrency(el) {
        if (!el) return;
        var raw = el.value || '';
        var cleaned = String(raw).replace(/[^\d.\-]/g, '');
        if (cleaned === '' || cleaned === '-' || cleaned === '.' || cleaned === '-.') {
            el.value = '';
            return;
        }
        var n = parseFloat(cleaned);
        if (isNaN(n)) {
            el.value = '';
            return;
        }
        el.value = formatCurrencyNumber(n);
    }

    function unformatInputCurrency(el) {
        if (!el) return;
        var v = el.value || '';
        var cleaned = String(v).replace(/,/g, '').trim();
        if (cleaned === '' || cleaned === '-' || cleaned === '.' || cleaned === '-.') {
            el.value = '';
            return;
        }
        el.value = cleaned;
        try {
            var len = el.value.length;
            el.setSelectionRange(len, len);
        } catch (e) {
            // ignore
        }
    }

    function bindSubbillerSelect() {
        var select = byId('subbiller_ext_id');
        var billerId = byId('biller_id');
        var partnerExt = byId('partner_ext_id');

        if (!select) return;

        function updateFromOption() {
            var opt = select.options[select.selectedIndex];
            var sbId = opt ? (opt.value || '') : '';
            var ptExt = opt ? (opt.getAttribute('data-partner-ext-id') || '') : '';

            if (billerId) billerId.value = sbId;
            if (partnerExt) partnerExt.value = ptExt;
        }

        select.addEventListener('change', updateFromOption);
        // initialize values if an option is pre-selected
        updateFromOption();
    }

    function bindRefToggle(form) {
        var toggle = byId('mRefToggle');
        var refGroup = form ? form.querySelector('[data-ref-group]') : null;
        var refInput = byId('reference_number');

        if (!toggle || !refGroup || !refInput) return;

        function setVisible(show) {
            refGroup.style.display = show ? '' : 'none';
            if (refInput) refInput.required = !!show;
            if (!show && refInput) {
                refInput.value = '';
            }
        }

        toggle.addEventListener('change', function () {
            setVisible(toggle.checked);
        });

        setVisible(!!toggle.checked);
    }

    function manageRequestFields(form) {
        if (!form) return;

        var typeSelect = byId('type_of_request');
        var reasonField = byId('reason');
        var correctId = byId('correct_biller_id');
        var correctName = byId('correct_biller_name');
        var wrongAmount = byId('wrong_amount');
        var correctAmount = byId('correct_amount');
        var difference = byId('difference_value');

        if (!typeSelect) return;

        var reasonGroup = reasonField ? reasonField.closest('.field-group') : null;
        var correctIdGroup = correctId ? correctId.closest('.field-group') : null;
        var correctNameGroup = correctName ? correctName.closest('.field-group') : null;
        var wrongAmountGroup = wrongAmount ? wrongAmount.closest('.field-group') : null;
        var correctAmountGroup = correctAmount ? correctAmount.closest('.field-group') : null;
        var differenceGroup = difference ? difference.closest('.field-group') : null;

        var typeVal = (typeSelect.value || '').toUpperCase();
        var isEmptyType = typeVal === '';
        var isWrongBiller = typeVal === 'WRONG BILLER';
        var isOverstated = typeVal === 'OVERSTATED AMOUNT';
        var isCancelled = typeVal === 'CANCELLED TRANSACTION';
        var showWrongCorrectAmount = isOverstated || isCancelled;

        if (correctIdGroup) correctIdGroup.style.display = isWrongBiller ? '' : 'none';
        if (correctNameGroup) correctNameGroup.style.display = isWrongBiller ? '' : 'none';
        if (correctId) {
            correctId.required = isWrongBiller;
            if (!isWrongBiller) correctId.value = '';
        }
        if (correctName) {
            correctName.required = isWrongBiller;
            if (!isWrongBiller) correctName.value = '';
        }

        if (wrongAmountGroup) wrongAmountGroup.style.display = showWrongCorrectAmount ? '' : 'none';
        if (correctAmountGroup) correctAmountGroup.style.display = showWrongCorrectAmount ? '' : 'none';
        if (differenceGroup) differenceGroup.style.display = isOverstated ? '' : 'none';

        if (wrongAmount) {
            wrongAmount.required = showWrongCorrectAmount;
            if (!showWrongCorrectAmount) wrongAmount.value = '';
        }
        if (correctAmount) {
            correctAmount.required = showWrongCorrectAmount;
            if (!showWrongCorrectAmount) correctAmount.value = '';
        }
        if (difference && !isOverstated) {
            difference.value = '';
        }

        if (reasonGroup) reasonGroup.style.display = isEmptyType ? 'none' : '';
        if (reasonField) {
            reasonField.required = !isEmptyType;
            if (isEmptyType) reasonField.value = '';
        }

        computeTypeReason(form);
    }

    function computeTypeReason(form) {
        var typeSelect = byId('type_of_request');
        var wrongAmount = byId('wrong_amount');
        var correctAmount = byId('correct_amount');
        var difference = byId('difference_value');
        var reasonField = byId('reason');
        if (!typeSelect || !wrongAmount || !correctAmount || !reasonField) return;

        var typeVal = typeSelect.value || '';
        var wrongNum = parseCurrencyToNumber(wrongAmount.value) || 0;
        var correctNum = parseCurrencyToNumber(correctAmount.value) || 0;
        var diffNum = wrongNum - correctNum;

        var wrongFmt = formatCurrencyNumber(wrongNum);
        var correctFmt = formatCurrencyNumber(correctNum);
        var diffFmt = formatCurrencyNumber(diffNum);

        if (typeVal === 'OVERSTATED AMOUNT') {
            if (difference) difference.value = formatCurrencyNumber(diffNum);
            var autoReasonOver = 'OVERSTATED AMOUNT PHP ' + wrongFmt + ' INSTEAD OF PHP ' + correctFmt + ' WITH THE DIFFERENCE OF PHP ' + diffFmt;
            var current = (reasonField.value || '').trim();
            var lastAutoOver = reasonField.dataset.lastAuto || '';
            if (current === '' || current === lastAutoOver) {
                reasonField.value = autoReasonOver;
                reasonField.dataset.lastAuto = autoReasonOver;
            }
        } else if (typeVal === 'CANCELLED TRANSACTION') {
            if (difference) difference.value = '';
            var autoReasonCancel = 'Wrong amount posted PHP ' + wrongFmt + ' instead of PHP ' + correctFmt;
            var currentCancel = (reasonField.value || '').trim();
            var lastAutoCancel = reasonField.dataset.lastAuto || '';
            if (currentCancel === '' || currentCancel === lastAutoCancel) {
                reasonField.value = autoReasonCancel;
                reasonField.dataset.lastAuto = autoReasonCancel;
            }
        }
    }

    function bindTypeSelect(form) {
        var typeSelect = byId('type_of_request');
        var reasonField = byId('reason');
        if (!typeSelect) return;

        if (reasonField && !reasonField._perType) {
            reasonField._perType = {};
        }
        typeSelect._prevType = typeSelect.value || '';

        typeSelect.addEventListener('change', function () {
            try {
                var prev = typeSelect._prevType || '';
                if (reasonField) {
                    reasonField._perType = reasonField._perType || {};
                    reasonField._perType[prev] = reasonField.value || '';
                }
            } catch (e) {
                // ignore
            }

            manageRequestFields(form);

            try {
                var cur = typeSelect.value || '';
                var autoTypes = { 'OVERSTATED AMOUNT': 1, 'CANCELLED TRANSACTION': 1 };
                if (reasonField && reasonField._perType && Object.prototype.hasOwnProperty.call(reasonField._perType, cur)) {
                    reasonField.value = reasonField._perType[cur] || '';
                } else if (!(cur in autoTypes) && reasonField) {
                    reasonField.value = '';
                    reasonField.removeAttribute('data-last-auto');
                }
            } catch (e2) {
                // ignore
            }

            typeSelect._prevType = typeSelect.value || '';
        });

        manageRequestFields(form);
    }

    function bindCustomTypeDropdown() {
        var tToggle = byId('typeToggle');
        var tList = byId('typeList');
        var tSelect = byId('type_of_request');
        if (!tToggle || !tList || !tSelect) return;

        // populate custom list from the select options if empty
        if (!tList.querySelectorAll('.type-item').length) {
            Array.prototype.slice.call(tSelect.options).forEach(function (opt) {
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'partner-item type-item';
                btn.setAttribute('data-value', opt.value || '');
                btn.textContent = opt.textContent || opt.value || '';
                tList.appendChild(btn);
            });
        }

        // set initial label
        var cur = (tSelect.value || '').trim();
        if (cur) {
            var curOpt = tSelect.options[tSelect.selectedIndex];
            if (curOpt) tToggle.innerHTML = (curOpt.textContent || cur) + ' <i class="fa-solid fa-caret-down" aria-hidden="true"></i>';
        }

        tToggle.addEventListener('click', function (e) {
            tList.classList.toggle('open');
            e.stopPropagation();
        });

        document.addEventListener('click', function (ev) {
            if (tList.classList.contains('open') && !tList.contains(ev.target) && !tToggle.contains(ev.target)) {
                tList.classList.remove('open');
            }
        });

        var items = tList.querySelectorAll('.type-item');
        items.forEach(function (it) {
            it.addEventListener('click', function () {
                var val = it.getAttribute('data-value') || '';
                tSelect.value = val;
                tToggle.innerHTML = it.textContent + ' <i class="fa-solid fa-caret-down" aria-hidden="true"></i>';
                tList.classList.remove('open');
                try {
                    var ev = new Event('change', { bubbles: true });
                    tSelect.dispatchEvent(ev);
                } catch (e) {
                    var ev2 = document.createEvent('HTMLEvents');
                    ev2.initEvent('change', true, false);
                    tSelect.dispatchEvent(ev2);
                }
            });
        });
    }

    function bindAmountInputs(form) {
        var wrongAmount = byId('wrong_amount');
        var correctAmount = byId('correct_amount');

        if (wrongAmount) {
            wrongAmount.addEventListener('input', function () { computeTypeReason(form); });
            wrongAmount.addEventListener('blur', function () { formatInputCurrency(wrongAmount); computeTypeReason(form); });
            wrongAmount.addEventListener('focus', function () { unformatInputCurrency(wrongAmount); });
            if (wrongAmount.value) formatInputCurrency(wrongAmount);
        }

        if (correctAmount) {
            correctAmount.addEventListener('input', function () { computeTypeReason(form); });
            correctAmount.addEventListener('blur', function () { formatInputCurrency(correctAmount); computeTypeReason(form); });
            correctAmount.addEventListener('focus', function () { unformatInputCurrency(correctAmount); });
            if (correctAmount.value) formatInputCurrency(correctAmount);
        }
    }

    function bindPaymentBranchLookup() {
        var idInput = byId('payment_branch_id');
        var nameInput = byId('payment_branch_name');
        if (!idInput) return;

        function lookup() {
            var id = (idInput.value || '').trim();
            if (id === '') {
                if (nameInput) nameInput.value = '';
                return;
            }

            var xhr = new XMLHttpRequest();
            xhr.open('POST', '/fetch/get_branch.php', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onreadystatechange = function () {
                if (xhr.readyState !== 4) return;
                if (xhr.status === 200) {
                    try {
                        var res = JSON.parse(xhr.responseText || '{}');
                        if (res && res.success && res.branch_name) {
                            if (nameInput) nameInput.value = res.branch_name;
                        } else {
                            if (nameInput) nameInput.value = '';
                        }
                    } catch (e) {
                        if (nameInput) nameInput.value = '';
                    }
                } else {
                    if (nameInput) nameInput.value = '';
                }
            };
            xhr.send('branch_id=' + encodeURIComponent(id));
        }

        idInput.addEventListener('blur', lookup);
        idInput.addEventListener('change', lookup);
        // initialize on load
        lookup();
            const paymentBranchIdInput = byId('payment_branch_id');
            const paymentBranchNameInput = byId('payment_branch_name');
            const paymentBranchSelect = byId('payment_branch_select');
            if (!paymentBranchIdInput || !paymentBranchSelect) return;

            // When select changes, set the readonly id and the text name
            paymentBranchSelect.addEventListener('change', function () {
                const opt = paymentBranchSelect.options[paymentBranchSelect.selectedIndex];
                const bname = opt ? opt.value : '';
                const bid = opt ? opt.getAttribute('data-branch-id') || '' : '';
                paymentBranchIdInput.value = bid;
                if (paymentBranchNameInput) paymentBranchNameInput.value = bname;
            });

            // If someone manually enters an ID in future, support lookup
            if (paymentBranchIdInput) {
                function lookupBranchById() {
                    const branchId = paymentBranchIdInput.value.trim();
                    if (!branchId) return;
                    fetch('/fetch/get_branch.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: 'branch_id=' + encodeURIComponent(branchId)
                    }).then(r => r.json()).then(json => {
                        if (json && json.success) {
                            if (paymentBranchNameInput) paymentBranchNameInput.value = json.branch_name || '';
                            // try to select option if present
                            for (let i = 0; i < paymentBranchSelect.options.length; i++) {
                                if (paymentBranchSelect.options[i].getAttribute('data-branch-id') === branchId) {
                                    paymentBranchSelect.selectedIndex = i;
                                    break;
                                }
                            }
                        }
                    }).catch(err => console.error(err));
                }

                paymentBranchIdInput.addEventListener('blur', lookupBranchById);
                paymentBranchIdInput.addEventListener('change', lookupBranchById);
            }
    }

    // duplicate reference field removed; no sync needed

    function initAttachments() {
        var area = byId('stFileUploadArea');
        var input = byId('attachments');
        var container = byId('stFilesContainer');
        if (!area || !input || !container) return;

        var files = [];

        function formatBytes(bytes) {
            if (!bytes) return '0 B';
            var sizes = ['B', 'KB', 'MB', 'GB'];
            var i = Math.floor(Math.log(bytes) / Math.log(1024));
            return (bytes / Math.pow(1024, i)).toFixed(2) + ' ' + sizes[i];
        }

        function updateInputFiles() {
            try {
                var dt = new DataTransfer();
                files.forEach(function (f) { dt.items.add(f); });
                input.files = dt.files;
            } catch (err) {
                // legacy browsers fallback
            }
        }

        function renderFiles() {
            container.innerHTML = '';
            if (files.length === 0) {
                container.innerHTML = '<div class="st-empty">No files selected.</div>';
                return;
            }

            files.forEach(function (f, idx) {
                var card = document.createElement('div');
                card.className = 'file-card';
                card.innerHTML =
                    '<div style="min-width:0">' +
                        '<div style="font-weight:700;color:#111827;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' + f.name + '</div>' +
                        '<div style="font-size:12px;color:#6b7280;">' + formatBytes(f.size) + '</div>' +
                    '</div>' +
                    '<button type="button" class="file-remove" data-idx="' + idx + '" title="Delete file" aria-label="Delete file"><i class="fa-solid fa-trash" aria-hidden="true"></i></button>';
                container.appendChild(card);
            });

            container.querySelectorAll('.file-remove').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var idx = parseInt(btn.getAttribute('data-idx'), 10);
                    if (isNaN(idx)) return;
                    files.splice(idx, 1);
                    updateInputFiles();
                    renderFiles();
                });
            });
        }

        function addFiles(fileList) {
            var arr = Array.prototype.slice.call(fileList || []);
            if (!arr.length) return;
            arr.forEach(function (f) { files.push(f); });
            updateInputFiles();
            renderFiles();
        }

        area.addEventListener('click', function () { input.click(); });
        input.addEventListener('change', function () { addFiles(input.files); });

        area.addEventListener('dragover', function (e) {
            e.preventDefault();
            area.classList.add('drag-over');
        });
        area.addEventListener('dragleave', function (e) {
            e.preventDefault();
            area.classList.remove('drag-over');
        });
        area.addEventListener('drop', function (e) {
            e.preventDefault();
            area.classList.remove('drag-over');
            addFiles(e.dataTransfer.files);
        });

        area.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                input.click();
            }
        });

        renderFiles();
    }

    document.addEventListener('DOMContentLoaded', function () {
        var form = byId('stCreateTicketForm');
        if (!form) return;

        bindSubbillerSelect();
        bindRefToggle(form);
        bindPaymentBranchLookup();
        bindTypeSelect(form);
        bindCustomTypeDropdown();
        bindAmountInputs(form);
        initAttachments();

        // Confirmation modal: intercept submit and show confirm dialog
        var confirmModal = byId('stConfirmSubmitModal');
        var confirmClose = byId('stCloseConfirmModal');
        var confirmCancel = byId('stCancelSubmitBtn');
        var confirmConfirm = byId('stConfirmSubmitBtn');

        function openConfirm() { if (confirmModal) confirmModal.classList.add('open'); }
        function closeConfirm() { if (confirmModal) confirmModal.classList.remove('open'); }

        if (confirmClose) confirmClose.addEventListener('click', closeConfirm);
        if (confirmCancel) confirmCancel.addEventListener('click', closeConfirm);

        if (form) {
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                openConfirm();
            });
        }

        if (confirmConfirm) {
            confirmConfirm.addEventListener('click', function () {
                if (!form) return;
                closeConfirm();
                // use native submit to bypass this submit handler
                form.submit();
            });
        }
    });
})();
