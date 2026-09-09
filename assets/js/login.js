/* =========================================================
   BBH DASHBOARD - HOSxP LOGIN MODAL
   ใช้การควบคุม Modal โดยตรง เพื่อให้เปิด/ปิดได้แน่นอน
========================================================= */
(function () {
    function initHosxpLoginModal() {
        var loginButton = document.getElementById('hosxp-login-btn');
        var modal = document.getElementById('hosxpLoginModal');

        if (!loginButton || !modal) return;

        var backdrop = null;

        function createBackdrop() {
            if (backdrop) return;

            backdrop = document.createElement('div');
            backdrop.className = 'hosxp-modal-backdrop';
            backdrop.setAttribute('aria-hidden', 'true');
            document.body.appendChild(backdrop);

            backdrop.addEventListener('click', closeModal);
        }

        function openModal(event) {
            if (event) event.preventDefault();

            createBackdrop();

            modal.classList.add('hosxp-modal-show');
            modal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('hosxp-modal-open');

            var username = document.getElementById('hosxp-username');
            if (username) {
                setTimeout(function () {
                    username.focus();
                }, 100);
            }
        }

        function closeModal(event) {
            if (event) event.preventDefault();

            modal.classList.remove('hosxp-modal-show');
            modal.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('hosxp-modal-open');

            if (backdrop) {
                backdrop.remove();
                backdrop = null;
            }
        }

        loginButton.addEventListener('click', openModal);

        /* ปุ่ม X และปุ่มยกเลิก */
        modal.querySelectorAll('[data-dismiss="modal"], .hosxp-login-close').forEach(function (button) {
            button.addEventListener('click', closeModal);
        });

        /* กด ESC เพื่อปิด */
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && modal.classList.contains('hosxp-modal-show')) {
                closeModal(event);
            }
        });

        /* ป้องกัน Bootstrap modal event เดิมมารบกวนการควบคุมของเรา */
        modal.addEventListener('click', function (event) {
            if (event.target === modal) {
                closeModal(event);
            }
        });
    }

    function applyProviderAccessUI() {
        /* navbar.php แสดง .login-btn เฉพาะเมื่อยังไม่ได้ Login Provider ID */
        var isGuest = !!document.querySelector('.custom-navbar .login-btn');
        var path = window.location.pathname;
        var isRestrictedDetailPage = /\/pages\/(opd_detail|ipd_detail|ipd_ward_detail|ER_detail)\.php$/i.test(path);

        if (!isGuest) return;

        /* Guest: ซ่อนปุ่ม "รายละเอียด" บนหน้า Index */
        document.querySelectorAll('.small-box-footer').forEach(function (link) {
            link.style.display = 'none';
        });

        /* Guest: หากเข้าหน้ารายละเอียดโดยตรง ให้กลับไปหน้า Index */
        if (isRestrictedDetailPage) {
            var basePath = path.split('/pages/')[0];
            window.location.replace(basePath + '/index.php');
        }
    }

    function initIpdWardCards() {
        /*
         * Ward Card ถูกสร้างแบบ dynamic จาก index.php
         * จึงใช้ event delegation เพื่อให้คลิกได้ทันทีเมื่อ Card ถูกสร้าง
         * และไม่ต้องแก้โครงสร้าง Card เดิม
         */
        if (!document.getElementById('ward-container')) return;

        document.addEventListener('click', async function (event) {
            var card = event.target.closest('.ward-card');
            if (!card) return;

            /* ป้องกันการกดซ้ำระหว่างกำลังหา Ward */
            if (card.dataset.wardOpening === '1') return;
            card.dataset.wardOpening = '1';

            var path = window.location.pathname;
            var basePath = path.indexOf('/pages/') !== -1
                ? path.split('/pages/')[0]
                : path.replace(/\/[^/]*$/, '');

            var wardNameElement = card.querySelector('.ward-name');
            var wardName = wardNameElement
                ? wardNameElement.textContent.trim()
                : '';

            try {
                var response = await fetch(
                    basePath + '/api/index_ward_bed.php',
                    { cache: 'no-store' }
                );

                var wards = await response.json();

                var ward = wards.find(function (item) {
                    return String(item.name || '').trim() === wardName;
                });

                if (!ward || !ward.ward) {
                    console.error('ไม่พบรหัส Ward จาก Card:', wardName);
                    return;
                }

                window.location.href =
                    basePath + '/pages/ipd_ward_detail.php?ward=' +
                    encodeURIComponent(ward.ward);

            } catch (error) {
                console.error('เปิดหน้า IPD Ward ไม่สำเร็จ:', error);
            } finally {
                card.dataset.wardOpening = '0';
            }
        });

        var styleId = 'bbh-ipd-ward-card-click';
        if (!document.getElementById(styleId)) {
            var style = document.createElement('style');
            style.id = styleId;
            style.textContent = `
                #ward-container .ward-card {
                    cursor: pointer;
                    transition: transform .15s ease, box-shadow .15s ease;
                }

                #ward-container .ward-card:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 8px 18px rgba(0,0,0,.14) !important;
                }
            `;
            document.head.appendChild(style);
        }
    }

    function fixIndexSectionSpacing() {
        /*
         * หน้า Index ใช้ CSS order เพื่อย้ายสถานะเตียงขึ้นเหนือ Heatmap
         * แต่ card ครอบเดิมของสถานะเตียงมีความสูงตาม service list ด้านขวา
         * จึงเกิดพื้นที่สีขาวส่วนเกินด้านล่าง/ระหว่างเนื้อหา
         * ตัด outer card ออกทาง visual layer โดยคง card ย่อยทั้งหมดไว้
         */
        if (!document.getElementById('heatmap-chart')) return;

        var styleId = 'bbh-index-section-spacing-fix';
        if (document.getElementById(styleId)) return;

        var style = document.createElement('style');
        style.id = styleId;
        style.textContent = `
            .content-wrapper .content > .container-fluid:has(#heatmap-chart) > .row:nth-child(4) > .col-12 > .card {
                background: transparent !important;
                border: 0 !important;
                box-shadow: none !important;
                border-radius: 0 !important;
            }

            .content-wrapper .content > .container-fluid:has(#heatmap-chart) > .row:nth-child(4) > .col-12 > .card > .card-body {
                padding: 0 !important;
            }

            .content-wrapper .content > .container-fluid:has(#heatmap-chart) > .row:nth-child(4) {
                margin-top: 0 !important;
                margin-bottom: 0 !important;
            }

            .content-wrapper .content > .container-fluid:has(#heatmap-chart) > .row:nth-child(2) {
                margin-top: 1rem !important;
            }
        `;

        document.head.appendChild(style);
    }

    function init() {
        initHosxpLoginModal();
        applyProviderAccessUI();
        initIpdWardCards();
        fixIndexSectionSpacing();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
