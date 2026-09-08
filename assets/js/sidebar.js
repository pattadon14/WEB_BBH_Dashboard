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

        const isProviderLoggedIn = sidebar.getAttribute('data-provider-logged-in') === '1';

        function openSidebar() {
            /* Guest เปิด Sidebar ได้ แต่เนื้อหาด้านในจะถูก Lock/Blur */
            sidebar.classList.add('open');
            overlay.classList.add('show');
            document.body.classList.add('bbh-sidebar-open');
            toggle.setAttribute('aria-expanded', 'true');

            sidebar.classList.toggle('guest-locked', !isProviderLoggedIn);
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

        /* Guest: ไม่ให้คลิกเมนูที่อยู่ใต้ชั้น Lock */
        sidebar.querySelectorAll('.bbh-sidebar-body a, .bbh-sidebar-body button').forEach(function (element) {
            element.addEventListener('click', function (event) {
                if (!isProviderLoggedIn) {
                    event.preventDefault();
                    event.stopPropagation();
                }
            });
        });

        /* OPD / IPD submenu */
        sidebar.querySelectorAll('[data-sidebar-submenu]').forEach(function (button) {
            button.addEventListener('click', function () {
                if (!isProviderLoggedIn) return;

                const submenuId = this.getAttribute('data-sidebar-submenu');
                const submenu = document.getElementById(submenuId);
                if (!submenu) return;

                const expanded = this.getAttribute('aria-expanded') === 'true';
                this.setAttribute('aria-expanded', expanded ? 'false' : 'true');
                submenu.classList.toggle('open', !expanded);
            });
        });

        /* OPD submenu -> switch tab */
        function activateOpdTab(tabName) {
            if (!tabName || !isProviderLoggedIn) return false;

            const tabButton = document.querySelector('[data-opd-tab="' + tabName + '"]');
            const tabPanel = document.getElementById('opd-tab-' + tabName);

            if (!tabButton || !tabPanel) return false;

            document.querySelectorAll('.opd-page-tab').forEach(function (button) {
                const active = button.getAttribute('data-opd-tab') === tabName;
                button.classList.toggle('active', active);
                button.setAttribute('aria-selected', active ? 'true' : 'false');
            });

            document.querySelectorAll('.opd-tab-panel').forEach(function (panel) {
                const active = panel.id === 'opd-tab-' + tabName;
                panel.classList.toggle('active', active);
                panel.hidden = !active;
            });

            return true;
        }

        sidebar.querySelectorAll('[data-opd-tab-link]').forEach(function (link) {
            link.addEventListener('click', function (event) {
                if (!isProviderLoggedIn) return;

                const tabName = this.getAttribute('data-opd-tab-link');
                const targetUrl = this.href;
                const isOpdPage = window.location.pathname.endsWith('/pages/opd_detail.php') ||
                    window.location.pathname.endsWith('opd_detail.php');

                if (isOpdPage && activateOpdTab(tabName)) {
                    event.preventDefault();
                    history.replaceState(null, '', targetUrl.split('#')[0] + '#opd-tab-' + tabName);
                    closeSidebar();
                }
            });
        });

        /* ถ้าเปิดหน้า OPD จาก submenu ให้เลือก tab ตาม hash */
        const currentHash = window.location.hash;
        if (currentHash === '#opd-tab-general' || currentHash === '#opd-tab-special') {
            activateOpdTab(currentHash.replace('#opd-tab-', ''));
        }

        /* ปิด Sidebar เมื่อเลือกเมนูปลายทาง */
        sidebar.querySelectorAll('a[href]').forEach(function (link) {
            link.addEventListener('click', function () {
                if (isProviderLoggedIn && this.getAttribute('href') !== '#') closeSidebar();
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initSidebar);
    } else {
        initSidebar();
    }
})();
