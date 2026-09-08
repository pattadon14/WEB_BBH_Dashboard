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
        var isRestrictedDetailPage = /\/pages\/(opd_detail|ipd_detail|ER_detail)\.php$/i.test(path);

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

    function init() {
        initHosxpLoginModal();
        applyProviderAccessUI();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
