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

    function initTicketTrailModals() {
        function closeModalById(id) {
            var m = document.getElementById(id);
            if (m) {
                m.classList.remove('open');
            }
        }

        var triggers = document.querySelectorAll('[data-ticket-modal]');
        triggers.forEach(function (btn) {
            btn.addEventListener('click', function () {
                var targetId = btn.getAttribute('data-ticket-modal');
                if (!targetId) return;
                var targetModal = document.getElementById(targetId);
                if (targetModal) {
                    targetModal.classList.add('open');
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

        var backdrops = document.querySelectorAll('.st-ticket-trail-backdrop');
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

    document.addEventListener('DOMContentLoaded', function () {
        initModeCards();
        initCreateModal();
        initTicketTrailModals();
    });
})();
