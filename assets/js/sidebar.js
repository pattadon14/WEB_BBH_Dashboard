/* =========================================================
   BBH DASHBOARD - SLIDE OUT SIDEBAR
========================================================= */
(function () {
    function initSidebar() {
        const sidebar = document.getElementById('bbh-sidebar');
        const overlay = document.getElementById('bbh-sidebar-overlay');
        const toggle = document.getElementById('bbh-menu-toggle');
        const close = document.getElementById('bbh-sidebar-close');

        if (!sidebar || !overlay || !toggle) return;

        function openSidebar() {
            sidebar.classList.add('open');
            overlay.classList.add('show');
            document.body.classList.add('bbh-sidebar-open');
            toggle.setAttribute('aria-expanded', 'true');
        }

        function closeSidebar() {
            sidebar.classList.remove('open');
            overlay.classList.remove('show');
            document.body.classList.remove('bbh-sidebar-open');
            toggle.setAttribute('aria-expanded', 'false');
        }

        toggle.addEventListener('click', function (event) {
            event.preventDefault();
            if (sidebar.classList.contains('open')) {
                closeSidebar();
            } else {
                openSidebar();
            }
        });

        if (close) close.addEventListener('click', closeSidebar);
        overlay.addEventListener('click', closeSidebar);

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') closeSidebar();
        });

        sidebar.querySelectorAll('a[href]').forEach(function (link) {
            link.addEventListener('click', function () {
                closeSidebar();
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initSidebar);
    } else {
        initSidebar();
    }
})();
