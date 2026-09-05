<?php
$currentPage = basename($_SERVER['PHP_SELF']);
?>

<div class="bbh-sidebar-overlay" id="bbh-sidebar-overlay"></div>

<aside class="bbh-sidebar" id="bbh-sidebar" aria-label="เมนูหลัก">
    <div class="bbh-sidebar-header">
        <div class="bbh-sidebar-brand">
            <i class="fa-solid fa-hospital me-2"></i>
            BBH DASHBOARD
        </div>
        <button type="button" class="bbh-sidebar-close" id="bbh-sidebar-close" aria-label="ปิดเมนู">
            <i class="fa-solid fa-xmark"></i>
        </button>
    </div>

    <div class="bbh-sidebar-body">
        <div class="bbh-sidebar-section-title">เมนูหลัก</div>
        <ul class="bbh-sidebar-menu">
            <li>
                <a href="<?= BASE_URL ?>" class="<?= $currentPage === 'index.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-house"></i>
                    <span>หน้าหลัก</span>
                </a>
            </li>
        </ul>

        <div class="bbh-sidebar-section-title">ข้อมูลผู้รับบริการ</div>
        <ul class="bbh-sidebar-menu">
            <li>
                <a href="<?= BASE_URL ?>pages/opd_detail.php" class="<?= $currentPage === 'opd_detail.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-user-doctor"></i>
                    <span>ผู้ป่วยนอก (OPD)</span>
                </a>
            </li>
            <li>
                <a href="<?= BASE_URL ?>pages/ipd_detail.php" class="<?= $currentPage === 'ipd_detail.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-bed-pulse"></i>
                    <span>ผู้ป่วยใน (IPD)</span>
                </a>
            </li>
            <li>
                <a href="<?= BASE_URL ?>pages/ER_detail.php" class="<?= $currentPage === 'ER_detail.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-truck-medical"></i>
                    <span>ห้องฉุกเฉิน (ER)</span>
                </a>
            </li>
        </ul>

        <div class="bbh-sidebar-divider"></div>

        <div class="bbh-sidebar-section-title">ระบบ</div>
        <ul class="bbh-sidebar-menu">
            <li>
                <a href="<?= BASE_URL ?>" title="กลับหน้าหลัก">
                    <i class="fa-solid fa-chart-line"></i>
                    <span>Dashboard</span>
                </a>
            </li>
        </ul>
    </div>
</aside>

<script src="<?= BASE_URL ?>assets/js/sidebar.js"></script>
