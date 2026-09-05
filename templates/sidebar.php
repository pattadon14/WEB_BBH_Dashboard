<?php
$currentPage = basename($_SERVER['PHP_SELF']);
$currentWard = $_GET['ward'] ?? '';
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
                    <i class="fa-solid fa-house"></i><span>หน้าหลัก</span>
                </a>
            </li>
        </ul>

        <div class="bbh-sidebar-section-title">ข้อมูลผู้รับบริการ</div>
        <ul class="bbh-sidebar-menu">
            <li>
                <a href="<?= BASE_URL ?>pages/opd_detail.php" class="<?= $currentPage === 'opd_detail.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-user-doctor"></i><span>ผู้ป่วยนอก (OPD)</span>
                </a>
            </li>

            <!-- ผู้ป่วยใน (IPD) : เมนูซ้อน Ward -->
            <li class="bbh-sidebar-parent <?= $currentPage === 'ipd_detail.php' ? 'parent-active' : '' ?>">
                <button type="button" class="bbh-sidebar-parent-toggle <?= $currentPage === 'ipd_detail.php' ? 'active' : '' ?>"
                    data-sidebar-submenu="ipd-submenu"
                    aria-expanded="<?= $currentPage === 'ipd_detail.php' ? 'true' : 'false' ?>">
                    <span class="bbh-sidebar-parent-main">
                        <i class="fa-solid fa-bed-pulse"></i><span>ผู้ป่วยใน (IPD)</span>
                    </span>
                    <i class="fa-solid fa-chevron-down bbh-sidebar-arrow"></i>
                </button>

                <ul class="bbh-sidebar-submenu <?= $currentPage === 'ipd_detail.php' ? 'open' : '' ?>" id="ipd-submenu">
                    <li><a href="<?= BASE_URL ?>pages/ipd_detail.php?ward=pediatric" class="<?= $currentWard === 'pediatric' ? 'active' : '' ?>"><i class="fa-solid fa-child"></i><span>ผู้ป่วยเด็ก</span></a></li>
                    <li><a href="<?= BASE_URL ?>pages/ipd_detail.php?ward=medicine_female" class="<?= $currentWard === 'medicine_female' ? 'active' : '' ?>"><i class="fa-solid fa-person-dress"></i><span>อายุรกรรมหญิง</span></a></li>
                    <li><a href="<?= BASE_URL ?>pages/ipd_detail.php?ward=medicine_male" class="<?= $currentWard === 'medicine_male' ? 'active' : '' ?>"><i class="fa-solid fa-person"></i><span>อายุรกรรมชาย</span></a></li>
                    <li><a href="<?= BASE_URL ?>pages/ipd_detail.php?ward=surgery" class="<?= $currentWard === 'surgery' ? 'active' : '' ?>"><i class="fa-solid fa-user-doctor"></i><span>ศัลยกรรม</span></a></li>
                    <li><a href="<?= BASE_URL ?>pages/ipd_detail.php?ward=private5" class="<?= $currentWard === 'private5' ? 'active' : '' ?>"><i class="fa-solid fa-door-open"></i><span>ผู้ป่วยห้องพิเศษ ชั้น 5</span></a></li>
                    <li><a href="<?= BASE_URL ?>pages/ipd_detail.php?ward=stroke" class="<?= $currentWard === 'stroke' ? 'active' : '' ?>"><i class="fa-solid fa-brain"></i><span>STROKE UNIT (หลอดเลือดสมอง)</span></a></li>
                    <li><a href="<?= BASE_URL ?>pages/ipd_detail.php?ward=lr" class="<?= $currentWard === 'lr' ? 'active' : '' ?>"><i class="fa-solid fa-person-pregnant"></i><span>LR (ห้องคลอด)</span></a></li>
                    <li><a href="<?= BASE_URL ?>pages/ipd_detail.php?ward=icu" class="<?= $currentWard === 'icu' ? 'active' : '' ?>"><i class="fa-solid fa-heart-pulse"></i><span>ICU (ผู้ป่วยหนัก)</span></a></li>
                    <li><a href="<?= BASE_URL ?>pages/ipd_detail.php?ward=snb" class="<?= $currentWard === 'snb' ? 'active' : '' ?>"><i class="fa-solid fa-baby"></i><span>SNB (ทารกแรกเกิดป่วย)</span></a></li>
                    <li><a href="<?= BASE_URL ?>pages/ipd_detail.php?ward=pp" class="<?= $currentWard === 'pp' ? 'active' : '' ?>"><i class="fa-solid fa-person-breastfeeding"></i><span>PP (มารดาหลังคลอด)</span></a></li>
                    <li><a href="<?= BASE_URL ?>pages/ipd_detail.php?ward=home" class="<?= $currentWard === 'home' ? 'active' : '' ?>"><i class="fa-solid fa-house-user"></i><span>Home Ward</span></a></li>
                </ul>
            </li>

            <li><a href="<?= BASE_URL ?>pages/ER_detail.php" class="<?= $currentPage === 'ER_detail.php' ? 'active' : '' ?>"><i class="fa-solid fa-truck-medical"></i><span>ห้องฉุกเฉิน (ER)</span></a></li>
            <li><a href="#" class="bbh-sidebar-static-item"><i class="fa-solid fa-tooth"></i><span>ทันตกรรม</span></a></li>
            <li><a href="#" class="bbh-sidebar-static-item"><i class="fa-solid fa-mortar-pestle"></i><span>แพทย์แผนไทย</span></a></li>
            <li><a href="#" class="bbh-sidebar-static-item"><i class="fa-solid fa-person-walking"></i><span>กายภาพบำบัด</span></a></li>
            <li><a href="#" class="bbh-sidebar-static-item"><i class="fa-solid fa-droplet"></i><span>บำบัดไตเทียม</span></a></li>
            <li><a href="#" class="bbh-sidebar-static-item"><i class="fa-solid fa-person-running"></i><span>ผ่าตัดและวิสัญญี</span></a></li>
            <li><a href="#" class="bbh-sidebar-static-item"><i class="fa-solid fa-x-ray"></i><span>X-ray และ Ultrasound</span></a></li>
        </ul>

        <div class="bbh-sidebar-divider"></div>

        <div class="bbh-sidebar-section-title">ระบบ</div>
        <ul class="bbh-sidebar-menu">
            <li><a href="<?= BASE_URL ?>" title="กลับหน้าหลัก"><i class="fa-solid fa-chart-line"></i><span>Dashboard</span></a></li>
        </ul>
    </div>
</aside>

<script src="<?= BASE_URL ?>assets/js/sidebar.js"></script>
