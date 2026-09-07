<nav class="main-header navbar navbar-expand navbar-light custom-navbar">

    <!-- Left -->
    <ul class="navbar-nav">
        <li class="nav-item">
            <button type="button" class="bbh-menu-toggle" id="bbh-menu-toggle"
                aria-label="เปิดเมนู" aria-expanded="false" aria-controls="bbh-sidebar">
                <i class="fas fa-bars"></i>
            </button>
        </li>
    </ul>

    <!-- Title -->
    <a href="<?= BASE_URL ?>" class="navbar-brand fw-bold">
        BBH DASHBOARD (โรงพยาบาลบ้านบึง)
    </a>

    <!-- Right -->
    <ul class="navbar-nav ms-auto align-items-center">
        <li class="nav-item mr-2">
            <button type="button" class="login-btn" id="hosxp-login-btn"
                aria-label="เข้าสู่ระบบด้วย HOSxP User">
                <i class="fas fa-sign-in-alt"></i>
                <span>เข้าสู่ระบบ</span>
            </button>
        </li>
        <li class="nav-item">
            <a href="https://docs.google.com/forms/d/e/1FAIpQLScKGR0_xvvq1uHqdwm3Npj0y6tEAZq0o5KEp9tQaufokUWzwg/viewform?usp=dialog"
                target="_blank" class="feedback-btn">
                <i class="fas fa-comment-dots"></i>
                <span>เสนอความคิดเห็น</span>
            </a>
        </li>
    </ul>

</nav>

<!-- HOSxP Login Modal -->
<div class="modal" id="hosxpLoginModal" tabindex="-1" role="dialog" aria-labelledby="hosxpLoginModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content hosxp-login-modal">
            <div class="modal-header hosxp-login-header">
                <h5 class="modal-title" id="hosxpLoginModalLabel">
                    <i class="fas fa-user-lock mr-2"></i>เข้าสู่ระบบ
                </h5>
                <button type="button" class="hosxp-login-close" aria-label="ปิด">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <form id="hosxp-login-form" autocomplete="off">
                <div class="modal-body hosxp-login-body">
                    <div class="hosxp-login-note">
                        <i class="fas fa-hospital-user"></i>
                        <span>เข้าสู่ระบบด้วย HOSxP User</span>
                    </div>

                    <div class="form-group">
                        <label for="hosxp-username">ชื่อผู้ใช้</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fas fa-user"></i></span>
                            </div>
                            <input type="text" class="form-control" id="hosxp-username" name="username"
                                placeholder="ชื่อผู้ใช้ HOSxP" autocomplete="username">
                        </div>
                    </div>

                    <div class="form-group mb-0">
                        <label for="hosxp-password">รหัสผ่าน</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            </div>
                            <input type="password" class="form-control" id="hosxp-password" name="password"
                                placeholder="รหัสผ่าน HOSxP" autocomplete="current-password">
                        </div>
                    </div>
                </div>

                <div class="modal-footer hosxp-login-footer">
                    <button type="button" class="btn btn-light hosxp-login-cancel">ยกเลิก</button>
                    <button type="submit" class="login-submit-btn">
                        <i class="fas fa-sign-in-alt mr-1"></i>เข้าสู่ระบบ
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
