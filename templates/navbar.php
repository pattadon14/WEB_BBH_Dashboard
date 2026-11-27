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
$providerFirst = trim((string) ($_SESSION['provider_auth']['firstname_th'] ?? ''));
if ($providerFirst === '') {
    $providerFirst = $providerName;
}

// Provider ID profile สามารถมีหลายหน่วยงาน ให้เลือกตำแหน่งจากหน่วยงานแรกที่มีข้อมูล
$providerPosition = '';
foreach (($_SESSION['provider_auth']['organization'] ?? []) as $organization) {
    $providerPosition = trim((string) ($organization['position'] ?? ''));
    if ($providerPosition !== '') {
        break;
    }
}

// อักษรย่อในวงกลม: พยัญชนะไทย/อักษรละตินตัวแรกของชื่อ (ข้ามสระหน้า เช่น เ แ โ)
$providerInitial = preg_match('/[ก-ฮA-Za-z]/u', $providerFirst, $m) ? $m[0] : '';

$h = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
?>

<!-- แถบบน (แทน navbar เดิม 2026-11-27) — หน้าตาอยู่ที่ assets/css/sidebar.css (.bbh-topbar)
     หน้าใดตั้ง $showWelcome = true จะแสดงคำทักทายทางซ้าย (หน้าแรก) · $showLiveStatus = ป้าย LIVE (header.php bbhSetLive) -->
<header class="bbh-topbar" id="bbh-topbar">

    <button type="button" class="tb-icon bbh-menu-toggle" id="bbh-menu-toggle"
        aria-label="แสดงแถบเมนู" aria-expanded="false" aria-controls="bbh-sidebar">
        <i class="fa-solid fa-bars" aria-hidden="true"></i>
    </button>

    <a href="<?= BASE_URL ?>" class="tb-brand">
        <img class="tb-logo" src="<?= BASE_URL ?>assets/img/logo-bbh-mark.png" alt="" width="36" height="36">
        <span><b>BBH Dashboard</b><small>โรงพยาบาลบ้านบึง</small></span>
    </a>

    <?php if (!empty($showWelcome)): ?>
        <div class="tb-heading">
            <h1 id="hx-hello"><?= $isProviderLoggedIn && $providerFirst !== '' ? 'ยินดีต้อนรับ, ' . $h($providerFirst) : 'ยินดีต้อนรับสู่ BBH Dashboard' ?></h1>
            <!-- วันที่ + เวลาอัปเดต ตั้งโดย markUpdated() ของหน้าแรก -->
            <p class="tb-status" role="status" aria-live="polite">
                <span class="tb-pulse" aria-hidden="true"></span>
                <span><span id="hx-today"></span>, <span id="last-updated">กำลังโหลดข้อมูล…</span></span>
            </p>
        </div>
    <?php endif; ?>

    <div class="tb-actions">

        <?php if (!empty($showLiveStatus)): ?>
            <div class="tb-live" id="live-status" role="status" aria-live="polite">
                <span class="tb-pulse" aria-hidden="true"></span>
                <span class="tb-live-label">LIVE</span>
                <span id="last-updated">กำลังโหลดข้อมูล…</span>
            </div>
        <?php endif; ?>

        <a href="https://docs.google.com/forms/d/e/1FAIpQLScKGR0_xvvq1uHqdwm3Npj0y6tEAZq0o5KEp9tQaufokUWzwg/viewform?usp=dialog"
            target="_blank" rel="noopener" class="tb-pill tb-feedback" title="เสนอความคิดเห็น (Google Form)">
            <i class="fa-solid fa-comment-dots" aria-hidden="true"></i>
            <span class="tb-label">เสนอความคิดเห็น</span>
        </a>

        <!-- สวิตช์โหมดสว่าง/มืด: ลูกบิดเลื่อนซ้าย-ขวา (aria-checked = โหมดมืด) -->
        <button type="button" class="tb-switch" data-theme-toggle role="switch" aria-checked="false"
            aria-label="โหมดมืด" title="สลับโหมดสว่าง / มืด" onclick="bbhToggleTheme()">
            <i class="fa-solid fa-sun tb-sw-sun" aria-hidden="true"></i>
            <i class="fa-solid fa-moon tb-sw-moon" aria-hidden="true"></i>
            <span class="tb-knob" aria-hidden="true"></span>
        </button>
        <script>document.currentScript.previousElementSibling.setAttribute('aria-checked', String(document.documentElement.getAttribute('data-theme') === 'dark'));</script>

        <?php if ($isProviderLoggedIn): ?>
            <div class="tb-user" title="<?= $h($providerName !== '' ? $providerName : 'Provider ID') ?>">
                <span class="tb-avatar" aria-hidden="true"><?= $providerInitial !== '' ? $h($providerInitial) : '<i class="fa-solid fa-user"></i>' ?></span>
                <span class="tb-user-text">
                    <b><?= $h($providerName !== '' ? $providerName : 'Provider ID') ?></b>
                    <?php if ($providerPosition !== ''): ?><small><?= $h($providerPosition) ?></small><?php endif; ?>
                </span>
                <a href="<?= BASE_URL ?>auth/logout.php" class="tb-logout" aria-label="ออกจากระบบ" title="ออกจากระบบ">
                    <i class="fa-solid fa-arrow-right-from-bracket" aria-hidden="true"></i>
                </a>
            </div>
        <?php else: ?>
            <a href="<?= BASE_URL ?>auth/provider_login.php" class="tb-pill tb-login" id="provider-login-btn" aria-label="เข้าสู่ระบบด้วย MOPH Provider ID">
                <i class="fa-solid fa-id-card" aria-hidden="true"></i>
                <span class="tb-label">เข้าสู่ระบบ</span>
            </a>
        <?php endif; ?>
    </div>
</header>
