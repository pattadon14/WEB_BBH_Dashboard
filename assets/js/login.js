/* =========================================================
   BBH DASHBOARD - HOSxP LOGIN MODAL
   Explicitly opens Bootstrap 4 modal after jQuery/Bootstrap load.
========================================================= */
(function () {
    function initHosxpLoginModal() {
        var loginButton = document.getElementById('hosxp-login-btn');
        var modal = document.getElementById('hosxpLoginModal');

        if (!loginButton || !modal) return;

        loginButton.addEventListener('click', function (event) {
            event.preventDefault();

            if (window.jQuery && typeof window.jQuery.fn.modal === 'function') {
                window.jQuery('#hosxpLoginModal').modal('show');
            } else {
                // Fallback if Bootstrap modal plugin is unavailable.
                modal.classList.add('show');
                modal.style.display = 'block';
                modal.setAttribute('aria-hidden', 'false');
                document.body.classList.add('modal-open');
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initHosxpLoginModal);
    } else {
        initHosxpLoginModal();
    }
})();
