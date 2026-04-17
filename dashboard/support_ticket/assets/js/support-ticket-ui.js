(function () {
    function initModeCards() {
        var groups = document.querySelectorAll('[data-st-mode-group]');
        groups.forEach(function (group) {
            var paramName = group.getAttribute('data-st-param') || 'mode';
            var radios = group.querySelectorAll('input[type="radio"][name]');
            var cards = group.querySelectorAll('.mode-card');

            function setMode(mode) {
                cards.forEach(function (card) {
                    card.classList.toggle('selected', card.getAttribute('data-mode') === mode);
                });

                document.querySelectorAll('[data-st-panel]').forEach(function (panel) {
                    panel.classList.toggle('hidden', panel.getAttribute('data-st-panel') !== mode);
                });

                var params = new URLSearchParams(window.location.search);
                params.set(paramName, mode);
                history.replaceState(null, '', window.location.pathname + '?' + params.toString());
            }

            radios.forEach(function (radio) {
                radio.addEventListener('change', function () {
                    if (radio.checked) {
                        setMode(radio.value);
                    }
                });
            });

            var checked = group.querySelector('input[type="radio"]:checked');
            if (checked) {
                setMode(checked.value);
            }
        });
    }

    function initCreateModal() {
        var openBtn = document.getElementById('stOpenCreateModal');
        var closeBtn = document.getElementById('stCloseCreateModal');
        var modal = document.getElementById('createTicketModal');

        if (!modal) {
            return;
        }

        function openModal() {
            modal.classList.add('open');
        }

        function closeModal() {
            modal.classList.remove('open');
        }

        if (openBtn) {
            openBtn.addEventListener('click', function (e) {
                e.preventDefault();
                openModal();
            });
        }

        if (closeBtn) {
            closeBtn.addEventListener('click', function (e) {
                e.preventDefault();
                closeModal();
            });
        }

        modal.addEventListener('click', function (e) {
            if (e.target === modal) {
                closeModal();
            }
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                closeModal();
            }
        });
    }

    function adjustTrailCardHeights(root) {
        var container = root || document;
        var cards = container.querySelectorAll('.tm-trail-card');
        cards.forEach(function (card) {
            var body = card.querySelector('.tm-trail-card-body');
            if (!body) return;
            if (card.classList.contains('tm-expanded')) {
                // for the latest card keep fully expanded (no max-height cap)
                if (card.hasAttribute('data-tm-latest')) {
                    body.style.maxHeight = 'none';
                } else {
                    // ensure measurement happens when element is visible
                    body.style.maxHeight = body.scrollHeight + 'px';
                }
            } else {
                body.style.maxHeight = '0px';
            }
        });
    }

    function toggleTrailCard(card) {
        if (!card) return;
        if (card.hasAttribute('data-tm-latest')) return; // cannot toggle latest
        var body = card.querySelector('.tm-trail-card-body');
        if (!body) return;
        var header = card.querySelector('.tm-trail-card-header');
        var isExpanded = card.classList.contains('tm-expanded');

        if (isExpanded) {
            // collapse: measure current height then animate to 0
            var start = body.scrollHeight;
            body.style.maxHeight = start + 'px';
            // force reflow
            /* eslint-disable no-unused-expressions */ void body.offsetHeight;
            requestAnimationFrame(function () {
                body.style.maxHeight = '0px';
                card.classList.remove('tm-expanded');
                if (header) header.setAttribute('aria-expanded', 'false');
            });
        } else {
            // expand: add class, measure, animate to measured height, then remove cap
            card.classList.add('tm-expanded');
            // ensure any layout changes from the class apply
            /* eslint-disable no-unused-expressions */ void body.offsetHeight;
            var target = body.scrollHeight;
            // start from 0 to ensure transition
            body.style.maxHeight = '0px';
            // animate to measured height
            requestAnimationFrame(function () {
                body.style.maxHeight = target + 'px';
                if (header) header.setAttribute('aria-expanded', 'true');
            });

            // after expand transition ends, remove maxHeight cap so content can grow naturally
            var onEnd = function (e) {
                if (e.propertyName !== 'max-height') return;
                body.removeEventListener('transitionend', onEnd);
                body.style.maxHeight = 'none';
            };
            body.addEventListener('transitionend', onEnd);
        }
    }

    function initTrailCardToggles() {
        var headers = document.querySelectorAll('.tm-trail-card-header');
        headers.forEach(function (header) {
            var card = header.closest('.tm-trail-card');
            if (!card) return;
            header.setAttribute('role', 'button');
            header.setAttribute('tabindex', '0');
            header.setAttribute('aria-expanded', card.classList.contains('tm-expanded') ? 'true' : 'false');

            header.addEventListener('click', function (e) {
                toggleTrailCard(card);
            });

            header.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    toggleTrailCard(card);
                }
            });
        });
    }

    function initTicketTrailModals() {
        function closeModalById(id) {
            var m = document.getElementById(id);
            if (m) {
                m.classList.remove('open');
            }
        }

        function scrollModalToLatest(modalEl) {
            if (!modalEl) return;
            var body = modalEl.querySelector('.tm-body');
            if (!body) return;

            var latestCard = modalEl.querySelector('.tm-trail-card[data-tm-latest]');
            if (latestCard) {
                latestCard.classList.add('tm-expanded');
            }

            // Wait one more frame so expanded heights are applied before scrolling.
            requestAnimationFrame(function () {
                body.scrollTop = body.scrollHeight;
            });
        }

        var triggers = document.querySelectorAll('[data-ticket-modal]');
        triggers.forEach(function (btn) {
            btn.addEventListener('click', function () {
                var targetId = btn.getAttribute('data-ticket-modal');
                if (!targetId) return;
                var targetModal = document.getElementById(targetId);
                if (targetModal) {
                    targetModal.classList.add('open');

                    // Mark ticket badge as seen for the current page role.
                    var seenTicketId = btn.getAttribute('data-ticket-id');
                    var seenRole = btn.getAttribute('data-seen-role');
                    if (seenTicketId && seenRole) {
                        var fd = new FormData();
                        fd.append('ticket_id', seenTicketId);
                        fd.append('role', seenRole);
                        fetch('controllers/badges/mark-seen.php', {
                            method: 'POST',
                            credentials: 'same-origin',
                            body: fd
                        }).then(function (res) {
                            if (!res || !res.ok) return null;
                            return res.json();
                        }).then(function (json) {
                            if (!json || !json.success) return;

                            // Remove the per-ticket unread badge in the row we clicked
                            try {
                                var unread = btn.querySelector('.st-ticket-unread-badge');
                                if (unread && unread.parentNode) unread.parentNode.removeChild(unread);
                            } catch (e) {
                                // ignore DOM update errors
                            }

                            // Decrement the mode-level active badge if present
                            try {
                                var modeCard = document.querySelector('.mode-card[data-mode="active"]');
                                if (modeCard) {
                                    var modeBadge = modeCard.querySelector('.st-mode-count-badge');
                                    if (modeBadge) {
                                        var n = parseInt(modeBadge.textContent.trim(), 10) || 0;
                                        n = Math.max(0, n - 1);
                                        if (n === 0) {
                                            modeBadge.parentNode && modeBadge.parentNode.removeChild(modeBadge);
                                        } else {
                                            modeBadge.textContent = n;
                                        }
                                    }
                                }
                            } catch (e) {
                                // ignore DOM update errors
                            }
                        }).catch(function () {
                            // no-op: badge sync failure should not block modal open
                        });
                    }

                    // Ensure trail card bodies have correct heights when modal becomes visible
                    requestAnimationFrame(function () {
                        adjustTrailCardHeights(targetModal);
                        scrollModalToLatest(targetModal);
                    });
                }
            });
        });

        var closers = document.querySelectorAll('[data-st-close-modal]');
        closers.forEach(function (btn) {
            btn.addEventListener('click', function () {
                var targetId = btn.getAttribute('data-st-close-modal');
                if (!targetId) return;
                closeModalById(targetId);
            });
        });

        var backdrops = document.querySelectorAll('.st-ticket-trail-backdrop, .tm-overlay');
        backdrops.forEach(function (backdrop) {
            backdrop.addEventListener('click', function (e) {
                if (e.target === backdrop) {
                    backdrop.classList.remove('open');
                }
            });
        });

        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape') return;
            backdrops.forEach(function (backdrop) {
                backdrop.classList.remove('open');
            });
        });
    }

    function initTransferConfirmModals() {
        var openButtons = document.querySelectorAll('[data-confirm-transfer-open]');
        openButtons.forEach(function (btn) {
            btn.addEventListener('click', function () {
                var modalId = btn.getAttribute('data-confirm-transfer-open');
                if (!modalId) return;
                var modal = document.getElementById(modalId);
                if (modal) {
                    modal.style.display = 'flex';
                    modal.setAttribute('aria-hidden', 'false');
                }
            });
        });

        var cancelButtons = document.querySelectorAll('[data-confirm-transfer-cancel]');
        cancelButtons.forEach(function (btn) {
            btn.addEventListener('click', function () {
                var modalId = btn.getAttribute('data-confirm-transfer-cancel');
                if (!modalId) return;
                var modal = document.getElementById(modalId);
                if (modal) {
                    modal.style.display = 'none';
                    modal.setAttribute('aria-hidden', 'true');
                }
            });
        });

        var submitButtons = document.querySelectorAll('[data-confirm-transfer-submit]');
        submitButtons.forEach(function (btn) {
            btn.addEventListener('click', function () {
                var formId = btn.getAttribute('data-transfer-form');
                if (!formId) return;
                var form = document.getElementById(formId);
                if (form) {
                    form.submit();
                }
            });
        });

        var transferModals = document.querySelectorAll('.tm-submodal-overlay[id^="stTransferTo"]');
        transferModals.forEach(function (overlay) {
            overlay.addEventListener('click', function (e) {
                if (e.target === overlay) {
                    overlay.style.display = 'none';
                    overlay.setAttribute('aria-hidden', 'true');
                }
            });
        });
    }

    function initClosePickerModals() {
        var openButtons = document.querySelectorAll('[data-close-picker-open]');
        openButtons.forEach(function (btn) {
            btn.addEventListener('click', function () {
                var modalId = btn.getAttribute('data-close-picker-open');
                if (!modalId) return;
                var modal = document.getElementById(modalId);
                if (modal) {
                    modal.style.display = 'flex';
                    modal.setAttribute('aria-hidden', 'false');
                }
            });
        });

        var cancelButtons = document.querySelectorAll('[data-close-picker-cancel]');
        cancelButtons.forEach(function (btn) {
            btn.addEventListener('click', function () {
                var modalId = btn.getAttribute('data-close-picker-cancel');
                if (!modalId) return;
                var modal = document.getElementById(modalId);
                if (modal) {
                    modal.style.display = 'none';
                    modal.setAttribute('aria-hidden', 'true');
                }
            });
        });

        var closePickers = document.querySelectorAll('.tm-submodal-overlay[id^="stClosePicker"]');
        closePickers.forEach(function (overlay) {
            overlay.addEventListener('click', function (e) {
                if (e.target === overlay) {
                    overlay.style.display = 'none';
                    overlay.setAttribute('aria-hidden', 'true');
                }
            });
        });
    }

    function initAttachmentPreviews() {
        // Delegated handler so attachments inside modals are caught
        document.addEventListener('click', function (e) {
            var a = e.target.closest('a.tm-attachment');
            if (!a) return;
            // Only handle if href contains id= (attachment link)
            var href = a.getAttribute('href') || '';
            try {
                var u = new URL(href, window.location.href);
                var id = u.searchParams.get('id');
                if (!id) return; // fallback to default navigation
            } catch (err) {
                return;
            }

            e.preventDefault();

            // Remove any existing preview overlay
            var existing = document.getElementById('stImagePreviewOverlay');
            if (existing && existing.parentNode) existing.parentNode.removeChild(existing);

            // Fetch server snippet and insert
            fetch('image-preview.php?id=' + encodeURIComponent(id), { credentials: 'same-origin' })
                .then(function (res) { return res.text(); })
                .then(function (html) {
                    var wrapper = document.createElement('div');
                    wrapper.innerHTML = html;
                    // The snippet root has .ip-overlay — give it an ID to manage
                    var overlay = wrapper.querySelector('.ip-overlay');
                    if (!overlay) return;
                    // ensure unique id
                    overlay.id = 'stImagePreviewOverlay';
                    document.body.appendChild(overlay);

                    function close() {
                        overlay.parentNode && overlay.parentNode.removeChild(overlay);
                        document.removeEventListener('keydown', onKey);
                    }

                    function onKey(evt) {
                        if (evt.key === 'Escape') close();
                    }

                    // close button
                    var cb = overlay.querySelector('[data-ip-close]');
                    if (cb) cb.addEventListener('click', function (ev) { ev.preventDefault(); close(); });

                    // clicking backdrop closes
                    overlay.addEventListener('click', function (ev) {
                        if (ev.target === overlay) close();
                    });

                    document.addEventListener('keydown', onKey);
                })
                .catch(function (err) {
                    // if preview fails, fallback to download navigation
                    window.location.href = href;
                });
        });
    }

    function initReplyAttachmentPreviews() {
        function formatBytes(bytes) {
            if (!bytes) return '0 B';
            var sizes = ['B', 'KB', 'MB', 'GB'];
            var i = Math.floor(Math.log(bytes) / Math.log(1024));
            return (bytes / Math.pow(1024, i)).toFixed(2) + ' ' + sizes[i];
        }

        function openLocalFilePreview(file) {
            if (!file) return;

            var existing = document.getElementById('stImagePreviewOverlay');
            if (existing && existing.parentNode) existing.parentNode.removeChild(existing);

            var overlay = document.createElement('div');
            overlay.className = 'ip-overlay';
            overlay.id = 'stImagePreviewOverlay';

            var safeName = (file.name || 'attachment').replace(/[&<>\"]/g, function (ch) {
                return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' })[ch] || ch;
            });

            var isImage = (file.type || '').indexOf('image/') === 0;
            var bodyHtml = '';
            if (isImage) {
                var blobUrl = URL.createObjectURL(file);
                bodyHtml = '<img class="ip-image" src="' + blobUrl + '" alt="' + safeName + '">';
                overlay.dataset.blobUrl = blobUrl;
            } else {
                bodyHtml =
                    '<div class="ip-file-placeholder">' +
                        '<div class="ip-file-icon"><i class="fa-solid fa-file"></i></div>' +
                        '<div class="ip-file-name">' + safeName + '</div>' +
                        '<div class="ip-file-help">Preview unavailable for this file type.</div>' +
                    '</div>';
            }

            overlay.innerHTML =
                '<div class="ip-modal">' +
                    '<button type="button" class="ip-close" data-ip-close aria-label="Close">&times;</button>' +
                    '<div class="ip-body">' + bodyHtml + '</div>' +
                '</div>';

            document.body.appendChild(overlay);

            function close() {
                if (overlay.dataset.blobUrl) {
                    URL.revokeObjectURL(overlay.dataset.blobUrl);
                }
                if (overlay.parentNode) overlay.parentNode.removeChild(overlay);
                document.removeEventListener('keydown', onKey);
            }

            function onKey(evt) {
                if (evt.key === 'Escape') close();
            }

            var cb = overlay.querySelector('[data-ip-close]');
            if (cb) cb.addEventListener('click', function (ev) { ev.preventDefault(); close(); });
            overlay.addEventListener('click', function (ev) {
                if (ev.target === overlay) close();
            });
            document.addEventListener('keydown', onKey);
        }

        var replyInputs = document.querySelectorAll('input[type="file"][id^="reply_attachments_"]');
        replyInputs.forEach(function (input) {
            var suffix = input.id.replace('reply_attachments_', '');
            var preview = document.getElementById('replyPreview_' + suffix);
            if (!preview) return;

            var selectedFiles = [];

            function syncInputFiles() {
                try {
                    var dt = new DataTransfer();
                    selectedFiles.forEach(function (f) { dt.items.add(f); });
                    input.files = dt.files;
                } catch (err) {
                    // ignore browser without DataTransfer write support
                }
            }

            function renderPreview() {
                preview.innerHTML = '';
                if (!selectedFiles.length) return;

                selectedFiles.forEach(function (file, index) {
                    var chip = document.createElement('div');
                    var isImage = (file.type || '').indexOf('image/') === 0;
                    chip.className = 'tm-attach-chip';
                    if (isImage) chip.setAttribute('data-previewable', '1');

                    var icon = isImage ? 'fa-image' : 'fa-file';
                    chip.innerHTML =
                        '<i class="fa-solid ' + icon + '" aria-hidden="true"></i>' +
                        '<span title="' + (file.name || '') + '">' + (file.name || 'Attachment') + ' (' + formatBytes(file.size || 0) + ')</span>' +
                        '<button type="button" class="tm-attach-chip-remove" data-remove-index="' + index + '" aria-label="Remove">&times;</button>';

                    chip.addEventListener('click', function (ev) {
                        if (ev.target && ev.target.closest('.tm-attach-chip-remove')) {
                            return;
                        }
                        if (isImage) {
                            openLocalFilePreview(file);
                        }
                    });

                    preview.appendChild(chip);
                });

                preview.querySelectorAll('[data-remove-index]').forEach(function (btn) {
                    btn.addEventListener('click', function (ev) {
                        ev.preventDefault();
                        ev.stopPropagation();
                        var idx = parseInt(btn.getAttribute('data-remove-index'), 10);
                        if (isNaN(idx)) return;
                        selectedFiles.splice(idx, 1);
                        syncInputFiles();
                        renderPreview();
                    });
                });
            }

            input.addEventListener('change', function () {
                var incoming = Array.prototype.slice.call(input.files || []);
                if (incoming.length) {
                    incoming.forEach(function (file) { selectedFiles.push(file); });
                }
                syncInputFiles();
                renderPreview();
            });
        });
    }

    function initTicketCopyButtons() {
        // Delegated click handler for copy buttons
        document.addEventListener('click', function (e) {
            var btn = e.target.closest ? e.target.closest('.tm-copy-ticket') : null;
            if (!btn) return;
            e.preventDefault();
            var ticket = btn.getAttribute('data-ticket-number') || '';
            if (!ticket) return;

            function showTemp(iconHtml) {
                var orig = btn.innerHTML;
                btn.innerHTML = iconHtml;
                setTimeout(function () { btn.innerHTML = orig; }, 1400);
            }

            function fallbackCopy(text) {
                var ta = document.createElement('textarea');
                ta.value = text;
                ta.style.position = 'fixed';
                ta.style.left = '-9999px';
                document.body.appendChild(ta);
                ta.select();
                try {
                    var ok = document.execCommand('copy');
                    document.body.removeChild(ta);
                    if (ok) {
                        showTemp('<i class="fa-solid fa-check" aria-hidden="true"></i>');
                        showCopyToast('Ticket number copied to clipboard');
                    } else {
                        showTemp('<i class="fa-solid fa-ban" aria-hidden="true"></i>');
                        showCopyToast('Unable to copy ticket number');
                    }
                } catch (err) {
                    document.body.removeChild(ta);
                    showTemp('<i class="fa-solid fa-ban" aria-hidden="true"></i>');
                    showCopyToast('Unable to copy ticket number');
                }
            }

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(ticket).then(function () {
                    showTemp('<i class="fa-solid fa-check" aria-hidden="true"></i>');
                    showCopyToast('Ticket number copied to clipboard');
                }).catch(function () {
                    fallbackCopy(ticket);
                });
            } else {
                fallbackCopy(ticket);
            }
        });
    }

    function stShowToast(message, type) {
        if (!message) return;
        var tone = (type || 'success').toLowerCase();
        var klass = tone === 'danger' ? 'st-copy-toast--danger' : 'st-copy-toast--success';
        var existing = document.getElementById('st-copy-toast');
        if (existing) {
            existing.textContent = message;
            existing.classList.remove('st-copy-toast--hide', 'st-copy-toast--danger', 'st-copy-toast--success');
            existing.classList.add('st-copy-toast--show', klass);
            clearTimeout(existing._hideTimeout);
            existing._hideTimeout = setTimeout(function () {
                existing.classList.remove('st-copy-toast--show');
                existing.classList.add('st-copy-toast--hide');
                setTimeout(function () { try { existing.remove(); } catch (e) {} }, 260);
            }, 2200);
            return;
        }

        var toast = document.createElement('div');
        toast.id = 'st-copy-toast';
        toast.className = 'st-copy-toast st-copy-toast--show ' + klass;
        toast.textContent = message;
        document.body.appendChild(toast);
        toast._hideTimeout = setTimeout(function () {
            toast.classList.remove('st-copy-toast--show');
            toast.classList.add('st-copy-toast--hide');
            setTimeout(function () { try { toast.remove(); } catch (e) {} }, 260);
        }, 2200);
    }

    function showCopyToast(message) {
        stShowToast(message || 'Ticket number copied to clipboard', 'success');
    }

    function initInitialFlashToast() {
        var flash = window.supportTicketInitialFlash;
        if (!flash || !flash.message) return;
        var type = String(flash.type || 'success').toLowerCase();
        stShowToast(String(flash.message), type === 'danger' ? 'danger' : 'success');
    }

    function clearReplyFormUI(form) {
        if (!form) return;
        var textarea = form.querySelector('textarea[name="message"]');
        if (textarea) textarea.value = '';

        var fileInput = form.querySelector('input[type="file"][id^="reply_attachments_"]');
        if (fileInput) {
            fileInput.value = '';
            var suffix = (fileInput.id || '').replace('reply_attachments_', '');
            var preview = document.getElementById('replyPreview_' + suffix);
            if (preview) preview.innerHTML = '';
        }
    }

    function shouldHandleAjaxReply(form) {
        if (!form) return false;
        var action = String(form.getAttribute('action') || '').toLowerCase();
        if (action.indexOf('controllers/branch/reply-ticket.php') !== -1) {
            return true;
        }

        if (action.indexOf('controllers/vpo/submit-ticket.php') !== -1 || action.indexOf('controllers/cad/submit-ticket.php') !== -1) {
            var actionInput = form.querySelector('input[name="action"]');
            return !!actionInput && String(actionInput.value || '').toLowerCase() === 'reply';
        }

        return false;
    }

    function stEscapeHtml(str) {
        return String(str == null ? '' : str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function getReplySenderRole(form) {
        if (!form) return 'SYSTEM';
        var action = String(form.getAttribute('action') || '').toLowerCase();
        if (action.indexOf('/branch/reply-ticket.php') !== -1) return 'BRANCH';
        if (action.indexOf('/vpo/submit-ticket.php') !== -1) return 'VPO';
        if (action.indexOf('/cad/submit-ticket.php') !== -1) return 'CAD';
        return 'SYSTEM';
    }

    function nowTrailDatetimeText() {
        try {
            return new Date().toLocaleString(undefined, {
                month: 'short',
                day: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                hour12: true
            }).replace(',', '');
        } catch (e) {
            return '';
        }
    }

    function formatBytesShort(bytes) {
        if (!bytes) return '0 B';
        var sizes = ['B', 'KB', 'MB', 'GB'];
        var i = Math.floor(Math.log(bytes) / Math.log(1024));
        return (bytes / Math.pow(1024, i)).toFixed(2) + ' ' + sizes[i];
    }

    function appendRealtimeReplyToTrail(form, messageText, attachments) {
        if (!form || !messageText) return;

        var modal = form.closest('.tm-modal');
        if (!modal) return;

        var trail = modal.querySelector('.tm-trail');
        if (!trail) return;

        var empty = trail.querySelector('.tm-empty-trail');
        if (empty && empty.parentNode) {
            empty.parentNode.removeChild(empty);
        }

        var prevLatest = trail.querySelector('.tm-trail-card[data-tm-latest]');
        if (prevLatest) {
            prevLatest.removeAttribute('data-tm-latest');
        }

        var role = getReplySenderRole(form);
        var icon = '⚙️';
        var avatarClass = 'tm-trail-avatar--system';
        if (role === 'BRANCH') {
            icon = '🟢';
            avatarClass = 'tm-trail-avatar--branch';
        } else if (role === 'VPO') {
            icon = '🔵';
            avatarClass = 'tm-trail-avatar--vpo';
        } else if (role === 'CAD') {
            icon = '🔴';
            avatarClass = 'tm-trail-avatar--cad';
        }

        var dtText = nowTrailDatetimeText();
        var safeMessage = stEscapeHtml(messageText).replace(/\n/g, '<br>');
        var attachmentHtml = '';

        if (attachments && attachments.length) {
            var nodes = [];
            attachments.forEach(function (file) {
                if (!file) return;
                nodes.push(
                    '<div class="tm-attachment" title="Attachment uploaded">' +
                        '<span class="tm-attachment-icon"><i class="fa-solid fa-paperclip" aria-hidden="true"></i></span>' +
                        '<span class="tm-attachment-name">' + stEscapeHtml(file.name || 'Attachment') + '</span>' +
                        '<span class="tm-attachment-size">' + stEscapeHtml(formatBytesShort(file.size || 0)) + '</span>' +
                    '</div>'
                );
            });

            if (nodes.length) {
                attachmentHtml = '<div class="tm-attachments">' + nodes.join('') + '</div>';
            }
        }

        var item = document.createElement('div');
        item.className = 'tm-trail-item';
        item.innerHTML =
            '<div class="tm-trail-dot-wrap">' +
                '<div class="tm-trail-avatar ' + avatarClass + '">' + stEscapeHtml(icon) + '</div>' +
            '</div>' +
            '<div class="tm-trail-card tm-expanded" data-tm-latest="1">' +
                '<div class="tm-trail-card-header">' +
                    '<div class="tm-trail-avatar ' + avatarClass + '">' + stEscapeHtml(icon) + '</div>' +
                    '<div class="tm-trail-meta">' +
                        '<div class="tm-trail-sender"><span>' + stEscapeHtml(role) + '</span></div>' +
                        '<div class="tm-trail-datetime">' + stEscapeHtml(dtText) + '</div>' +
                    '</div>' +
                    '<div class="tm-trail-type-label tm-trail-type-label--message">Message</div>' +
                    '<div class="tm-trail-chevron">›</div>' +
                '</div>' +
                '<div class="tm-trail-card-body">' +
                    '<div class="tm-trail-message">' + safeMessage + '</div>' +
                    attachmentHtml +
                '</div>' +
            '</div>';

        trail.appendChild(item);
        adjustTrailCardHeights(modal);

        var body = modal.querySelector('.tm-body');
        if (body) {
            requestAnimationFrame(function () {
                body.scrollTop = body.scrollHeight;
            });
        }
    }

    function initAjaxReplySubmits() {
        var forms = document.querySelectorAll('form[method="post"], form[method="POST"]');
        forms.forEach(function (form) {
            if (!shouldHandleAjaxReply(form)) return;

            form.addEventListener('submit', function (e) {
                e.preventDefault();

                var formData = new FormData(form);
                var submittedMessage = String(formData.get('message') || '').trim();
                var fileInput = form.querySelector('input[type="file"][id^="reply_attachments_"]');
                var submittedAttachments = fileInput && fileInput.files ? Array.prototype.slice.call(fileInput.files) : [];
                var submitBtn = form.querySelector('button[type="submit"]');
                if (submitBtn) submitBtn.disabled = true;

                fetch(form.getAttribute('action') || window.location.href, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: formData
                }).then(function (res) {
                    return res.json().catch(function () {
                        return { success: false, message: 'Unexpected server response.' };
                    });
                }).then(function (json) {
                    if (!json || !json.success) {
                        stShowToast((json && json.message) ? json.message : 'Unable to submit reply.', 'danger');
                        return;
                    }

                    appendRealtimeReplyToTrail(form, submittedMessage, submittedAttachments);
                    clearReplyFormUI(form);
                    stShowToast(json.message || 'Reply submitted successfully.', 'success');
                }).catch(function () {
                    stShowToast('Network error while submitting reply.', 'danger');
                }).finally(function () {
                    if (submitBtn) submitBtn.disabled = false;
                });
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        window.stShowToast = stShowToast;
        initModeCards();
        initCreateModal();
        initTicketTrailModals();
        initTrailCardToggles();
        initTransferConfirmModals();
        initClosePickerModals();
        initAttachmentPreviews();
        initReplyAttachmentPreviews();
        initTicketCopyButtons();
        initAjaxReplySubmits();
        initInitialFlashToast();
    });
})();
