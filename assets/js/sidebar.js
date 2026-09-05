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
            sidebar.classList.contains('open') ? closeSidebar() : openSidebar();
        });

        if (close) close.addEventListener('click', closeSidebar);
        overlay.addEventListener('click', closeSidebar);

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') closeSidebar();
        });

        /* IPD submenu */
        sidebar.querySelectorAll('[data-sidebar-submenu]').forEach(function (button) {
            button.addEventListener('click', function () {
                const submenuId = this.getAttribute('data-sidebar-submenu');
                const submenu = document.getElementById(submenuId);
                if (!submenu) return;

                const expanded = this.getAttribute('aria-expanded') === 'true';
                this.setAttribute('aria-expanded', expanded ? 'false' : 'true');
                submenu.classList.toggle('open', !expanded);
            });
        });

        /* ปิด Sidebar เมื่อเลือกเมนูปลายทาง */
        sidebar.querySelectorAll('a[href]').forEach(function (link) {
            link.addEventListener('click', function () {
                if (this.getAttribute('href') !== '#') closeSidebar();
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initSidebar);
    } else {
        initSidebar();
    }
})();
