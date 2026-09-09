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

        /* =========================================================
           IPD Sidebar -> อ้างอิง Ward จริงจาก Card หน้า Index

           ไม่ hard-code เลข Ward เพราะเลข Ward ใน HOSxP สามารถเปลี่ยนได้
           จึงอ่านจาก api/index_ward_bed.php แล้วจับคู่จากชื่อ Ward
           จากนั้นเปลี่ยน href เป็น ipd_ward_detail.php?ward=<ward_id>
        ========================================================= */
        async function bindIpdWardLinks() {
            const links = sidebar.querySelectorAll('[data-ipd-ward-key]');
            if (!links.length || !isProviderLoggedIn) return;

            try {
                const baseUrl = document.body.getAttribute('data-base-url') || '';
                const response = await fetch(baseUrl + 'api/index_ward_bed.php', {
                    cache: 'no-store'
                });

                if (!response.ok) throw new Error('ไม่สามารถโหลดข้อมูล Ward ได้');

                const wards = await response.json();
                if (!Array.isArray(wards)) throw new Error('รูปแบบข้อมูล Ward ไม่ถูกต้อง');

                function normalize(value) {
                    return String(value || '')
                        .toLowerCase()
                        .replace(/\s+/g, '')
                        .replace(/[()\[\]{}]/g, '')
                        .replace(/[._-]/g, '');
                }

                function findWard(key) {
                    return wards.find(function (item) {
                        const name = normalize(item.name);

                        switch (key) {
                            case 'pediatric':
                                return name.includes('เด็ก');
                            case 'medicine_female':
                                return name.includes('medหญิง') || name.includes('อายุรกรรมหญิง');
                            case 'medicine_male':
                                return name.includes('medชาย') || name.includes('อายุรกรรมชาย');
                            case 'surgery':
                                return name.includes('ศัลยกรรม');
                            case 'private5':
                                return name.includes('พิเศษ') && name.includes('5');
                            case 'stroke':
                                return name.includes('stroke');
                            case 'lr':
                                return name === 'lr' || name.includes('ห้องคลอด');
                            case 'icu':
                                return name === 'icu' || name.includes('icu');
                            case 'snb':
                                return name.includes('snb') || name.includes('ทารกแรกเกิด');
                            case 'pp':
                                return name === 'pp' || name.includes('หลังคลอด');
                            case 'home':
                                return name.includes('homeward') || name.includes('home');
                            default:
                                return false;
                        }
                    });
                }

                links.forEach(function (link) {
                    const key = link.getAttribute('data-ipd-ward-key');
                    const ward = findWard(key);
                    if (!ward || ward.ward === undefined || ward.ward === null) return;

                    link.href = baseUrl + 'pages/ipd_ward_detail.php?ward=' +
                        encodeURIComponent(ward.ward);
                    link.setAttribute('data-ward-id', ward.ward);
                    link.classList.add('ipd-ward-linked');

                    const currentWard = new URLSearchParams(window.location.search).get('ward');
                    const isWardDetailPage = window.location.pathname.endsWith('/pages/ipd_ward_detail.php') ||
                        window.location.pathname.endsWith('ipd_ward_detail.php');

                    if (isWardDetailPage && currentWard === String(ward.ward)) {
                        link.classList.add('active');
                    }
                });

            } catch (error) {
                console.error('bindIpdWardLinks error:', error);
            }
        }

        bindIpdWardLinks();

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
