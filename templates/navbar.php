<?php
require_once __DIR__ . '/../auth/provider.php';

$isProviderLoggedIn = provider_is_logged_in();
$providerName = trim((string) ($_SESSION['provider_auth']['name_th'] ?? ''));
if ($providerName === '') {
    $providerName = trim(
        (string) ($_SESSION['provider_auth']['firstname_th'] ?? '') . ' ' .
        (string) ($_SESSION['provider_auth']['lastname_th'] ?? '')
    );
}
?>

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
        <li class="nav-item mr-3">
            <a href="https://docs.google.com/forms/d/e/1FAIpQLScKGR0_xvvq1uHqdwm3Npj0y6tEAZq0o5KEp9tQaufokUWzwg/viewform?usp=dialog" target="_blank" class="feedback-btn">
                <i class="fas fa-comment-dots"></i>
                <span>เสนอความคิดเห็น</span>
            </a>
        </li>

        <?php if ($isProviderLoggedIn): ?>
            <li class="nav-item">
                <a href="<?= BASE_URL ?>auth/logout.php" class="login-btn" aria-label="ออกจากระบบ">
                    <i class="fas fa-user-check"></i>
                    <span><?= htmlspecialchars($providerName !== '' ? $providerName : 'Provider ID', ENT_QUOTES, 'UTF-8') ?></span>
                    <i class="fas fa-sign-out-alt ml-1"></i>
                </a>
            </li>
        <?php else: ?>
            <li class="nav-item">
                <a href="<?= BASE_URL ?>auth/provider_login.php" class="login-btn" id="provider-login-btn" aria-label="เข้าสู่ระบบด้วย MOPH Provider ID">
                    <i class="fas fa-id-card"></i>
                    <span>เข้าสู่ระบบ</span>
                </a>
            </li>
        <?php endif; ?>
    </ul>

</nav>
