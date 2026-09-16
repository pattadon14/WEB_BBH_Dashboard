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

    function initGuestAccessPopup() {
        var isGuest = !!document.querySelector('.custom-navbar .login-btn');
        if (!isGuest) return;

        var popup = null;

        function injectStyles() {
            if (document.getElementById('bbh-guest-access-popup-style')) return;

            var style = document.createElement('style');
            style.id = 'bbh-guest-access-popup-style';
            style.textContent = `
                .bbh-guest-access-backdrop {
                    position: fixed;
                    inset: 0;
                    z-index: 1100;
                    display: none;
                    align-items: center;
                    justify-content: center;
                    padding: 20px;
                    background: rgba(20, 35, 29, .46);
                    backdrop-filter: blur(3px);
                    -webkit-backdrop-filter: blur(3px);
                }

                .bbh-guest-access-backdrop.is-open {
                    display: flex;
                }

                .bbh-guest-access-popup {
                    width: min(430px, 94vw);
                    border: 1px solid #dce8e2;
                    border-radius: 16px;
                    background: #fff;
                    box-shadow: 0 16px 45px rgba(0, 0, 0, .22);
                    overflow: hidden;
                    animation: bbhGuestPopupIn .18s ease-out;
                }

                @keyframes bbhGuestPopupIn {
                    from { opacity: 0; transform: translateY(10px) scale(.98); }
                    to { opacity: 1; transform: translateY(0) scale(1); }
                }

                .bbh-guest-access-icon {
                    width: 68px;
                    height: 68px;
                    margin: 24px auto 13px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    border-radius: 50%;
                    background: #eaf7f0;
                    color: #198754;
                    font-size: 30px;
                }

                .bbh-guest-access-body {
                    padding: 0 28px 24px;
                    text-align: center;
                }

                .bbh-guest-access-title {
                    margin: 0 0 8px;
                    color: #147447;
                    font-size: 21px;
                    font-weight: 700;
                }

                .bbh-guest-access-message {
                    margin: 0;
                    color: #66727b;
                    font-size: 16px;
                    line-height: 1.65;
                }

                .bbh-guest-access-actions {
                    display: flex;
                    justify-content: center;
                    gap: 10px;
                    margin-top: 20px;
                }

                .bbh-guest-access-btn {
                    min-width: 125px;
                    min-height: 42px;
                    padding: 8px 16px;
                    border-radius: 8px;
                    font-size: 16px;
                    font-weight: 700;
                    cursor: pointer;
                    transition: all .16s ease;
                }

                .bbh-guest-access-login {
                    border: 1px solid #198754;
                    background: #198754;
                    color: #fff;
                }

                .bbh-guest-access-login:hover {
                    border-color: #147447;
                    background: #147447;
                    transform: translateY(-1px);
                }

                .bbh-guest-access-cancel {
                    border: 1px solid #d8e0dc;
                    background: #fff;
                    color: #68757d;
                }

                .bbh-guest-access-cancel:hover {
                    background: #f5f7f6;
                    color: #3f4a50;
                }

                body.bbh-guest-popup-open {
                    overflow: hidden;
                }

                @media (max-width: 575px) {
                    .bbh-guest-access-body {
                        padding-left: 20px;
                        padding-right: 20px;
                    }

                    .bbh-guest-access-actions {
                        flex-direction: column-reverse;
                    }

                    .bbh-guest-access-btn {
                        width: 100%;
                    }
                }
            `;
            document.head.appendChild(style);
        }

        function createPopup() {
            if (popup) return;

            popup = document.createElement('div');
            popup.className = 'bbh-guest-access-backdrop';
            popup.setAttribute('aria-hidden', 'true');
            popup.innerHTML = `
                <div class="bbh-guest-access-popup" role="dialog" aria-modal="true" aria-labelledby="bbh-guest-access-title">
                    <div class="bbh-guest-access-icon">
                        <i class="fas fa-lock"></i>
                    </div>
                    <div class="bbh-guest-access-body">
                        <h3 class="bbh-guest-access-title" id="bbh-guest-access-title">กรุณาเข้าสู่ระบบก่อน</h3>
                        <p class="bbh-guest-access-message">ข้อมูลส่วนนี้สำหรับผู้ใช้งานที่เข้าสู่ระบบ Provider ID เท่านั้น<br>กรุณาเข้าสู่ระบบเพื่อดูรายละเอียดเพิ่มเติม</p>
                        <div class="bbh-guest-access-actions">
                            <button type="button" class="bbh-guest-access-btn bbh-guest-access-cancel">ยกเลิก</button>
                            <button type="button" class="bbh-guest-access-btn bbh-guest-access-login"><i class="fas fa-sign-in-alt"></i> เข้าสู่ระบบ</button>
                        </div>
                    </div>
                </div>
            `;

            document.body.appendChild(popup);

            popup.addEventListener('click', function (event) {
                if (event.target === popup || event.target.closest('.bbh-guest-access-cancel')) {
                    closePopup();
                }

                if (event.target.closest('.bbh-guest-access-login')) {
                    closePopup();
                    var loginButton = document.getElementById('hosxp-login-btn');
                    if (loginButton) loginButton.click();
                }
            });
        }

        function openPopup() {
            createPopup();
            popup.classList.add('is-open');
            popup.setAttribute('aria-hidden', 'false');
            document.body.classList.add('bbh-guest-popup-open');
        }

        function closePopup() {
            if (!popup) return;
            popup.classList.remove('is-open');
            popup.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('bbh-guest-popup-open');
        }

        injectStyles();

        /*
         * ดักการคลิก Card ที่นำไปดูข้อมูลรายละเอียด
         * เฉพาะ Guest เท่านั้น ส่วนผู้ที่ Login แล้วทำงานตามปกติ
         */
        document.addEventListener('click', function (event) {
            var card = event.target.closest('.small-box, .ward-card');
            if (!card) return;

            event.preventDefault();
            event.stopImmediatePropagation();
            openPopup();
        }, true);

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && popup && popup.classList.contains('is-open')) {
                closePopup();
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
        initGuestAccessPopup();
        initIpdWardCards();
        fixIndexSectionSpacing();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
