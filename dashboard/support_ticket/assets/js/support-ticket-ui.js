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

    document.addEventListener('DOMContentLoaded', function () {
        initModeCards();
        initCreateModal();
        initTicketTrailModals();
        initTrailCardToggles();
        initTransferConfirmModals();
        initClosePickerModals();
        initAttachmentPreviews();
    });
})();
