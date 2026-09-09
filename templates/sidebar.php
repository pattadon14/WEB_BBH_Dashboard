<?php
$currentPage = basename($_SERVER['PHP_SELF']);
$currentWard = $_GET['ward'] ?? '';
$isProviderLoggedIn = provider_is_logged_in();
?>

<div class="bbh-sidebar-overlay" id="bbh-sidebar-overlay"></div>

<aside class="bbh-sidebar" id="bbh-sidebar" data-provider-logged-in="<?= $isProviderLoggedIn ? '1' : '0' ?>" aria-label="เมนูหลัก">
    <div class="bbh-sidebar-header">
        <div class="bbh-sidebar-brand">
            <i class="fa-solid fa-hospital me-2"></i>
            BBH DASHBOARD
        </div>
        <button type="button" class="bbh-sidebar-close" id="bbh-sidebar-close" aria-label="ปิดเมนู">
            <i class="fa-solid fa-xmark"></i>
        </button>
    </div>

    <?php if (!$isProviderLoggedIn): ?>
        <div class="bbh-sidebar-guest-lock" aria-hidden="true">
            <div class="bbh-sidebar-guest-lock-card">
                <i class="fa-solid fa-lock"></i>
                <strong>กรุณาเข้าสู่ระบบ</strong>
                <span>เพื่อดูข้อมูลเพิ่มเติม</span>
            </div>
        </div>
    <?php endif; ?>

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

            <!-- ผู้ป่วยนอก (OPD) : เมนูซ้อนประเภทบริการ -->
            <li class="bbh-sidebar-parent <?= $currentPage === 'opd_detail.php' ? 'parent-active' : '' ?>">
                <button type="button"
                    class="bbh-sidebar-parent-toggle <?= $currentPage === 'opd_detail.php' ? 'active' : '' ?>"
                    data-sidebar-submenu="opd-submenu"
                    aria-expanded="<?= $currentPage === 'opd_detail.php' ? 'true' : 'false' ?>">
                    <span class="bbh-sidebar-parent-main">
                        <i class="fa-solid fa-user-doctor"></i><span>ผู้ป่วยนอก (OPD)</span>
                    </span>
                    <i class="fa-solid fa-chevron-down bbh-sidebar-arrow"></i>
                </button>

                <ul class="bbh-sidebar-submenu <?= $currentPage === 'opd_detail.php' ? 'open' : '' ?>" id="opd-submenu">
                    <li>
                        <a href="<?= BASE_URL ?>pages/opd_detail.php#opd-tab-general" data-opd-tab-link="general">
                            <i class="fa-solid fa-stethoscope"></i>
                            <span>ผู้ป่วยตรวจโรคทั่วไป</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?= BASE_URL ?>pages/opd_detail.php#opd-tab-special" data-opd-tab-link="special">
                            <i class="fa-solid fa-hospital-user"></i>
                            <span>ผู้ป่วยตรวจโรคคลินิคพิเศษ</span>
                        </a>
                    </li>
                </ul>
            </li>

            <!-- ผู้ป่วยใน (IPD) : เมนู Ward อ้างอิง Ward จริงจาก Card หน้า Index -->
            <li class="bbh-sidebar-parent <?= $currentPage === 'ipd_ward_detail.php' || $currentPage === 'ipd_detail.php' ? 'parent-active' : '' ?>">
                <button type="button"
                    class="bbh-sidebar-parent-toggle <?= $currentPage === 'ipd_ward_detail.php' || $currentPage === 'ipd_detail.php' ? 'active' : '' ?>"
                    data-sidebar-submenu="ipd-submenu"
                    aria-expanded="<?= $currentPage === 'ipd_ward_detail.php' || $currentPage === 'ipd_detail.php' ? 'true' : 'false' ?>">
                    <span class="bbh-sidebar-parent-main">
                        <i class="fa-solid fa-bed-pulse"></i><span>ผู้ป่วยใน (IPD)</span>
                    </span>
                    <i class="fa-solid fa-chevron-down bbh-sidebar-arrow"></i>
                </button>

                <ul class="bbh-sidebar-submenu <?= $currentPage === 'ipd_ward_detail.php' || $currentPage === 'ipd_detail.php' ? 'open' : '' ?>" id="ipd-submenu">
                    <li>
                        <a href="<?= BASE_URL ?>pages/ipd_detail.php"
                            class="<?= $currentPage === 'ipd_detail.php' && $currentWard === '' ? 'active' : '' ?>">
                            <i class="fa-solid fa-chart-pie"></i><span>ภาพรวมผู้ป่วยใน</span>
                        </a>
                    </li>

                    <li><a href="#" data-ipd-ward-key="pediatric"><i class="fa-solid fa-child"></i><span>ผู้ป่วยเด็ก</span></a></li>
                    <li><a href="#" data-ipd-ward-key="medicine_female"><i class="fa-solid fa-person-dress"></i><span>อายุรกรรมหญิง</span></a></li>
                    <li><a href="#" data-ipd-ward-key="medicine_male"><i class="fa-solid fa-person"></i><span>อายุรกรรมชาย</span></a></li>
                    <li><a href="#" data-ipd-ward-key="surgery"><i class="fa-solid fa-user-doctor"></i><span>ศัลยกรรม</span></a></li>
                    <li><a href="#" data-ipd-ward-key="private5"><i class="fa-solid fa-door-open"></i><span>ผู้ป่วยห้องพิเศษ ชั้น 5</span></a></li>
                    <li><a href="#" data-ipd-ward-key="stroke"><i class="fa-solid fa-brain"></i><span>STROKE UNIT (หลอดเลือดสมอง)</span></a></li>
                    <li><a href="#" data-ipd-ward-key="lr"><i class="fa-solid fa-person-pregnant"></i><span>LR (ห้องคลอด)</span></a></li>
                    <li><a href="#" data-ipd-ward-key="icu"><i class="fa-solid fa-heart-pulse"></i><span>ICU (ผู้ป่วยหนัก)</span></a></li>
                    <li><a href="#" data-ipd-ward-key="snb"><i class="fa-solid fa-baby"></i><span>SNB (ทารกแรกเกิดป่วย)</span></a></li>
                    <li><a href="#" data-ipd-ward-key="pp"><i class="fa-solid fa-person-breastfeeding"></i><span>PP (มารดาหลังคลอด)</span></a></li>
                    <li><a href="#" data-ipd-ward-key="home"><i class="fa-solid fa-house-user"></i><span>Home Ward</span></a></li>
                </ul>
            </li>

            <li><a href="<?= BASE_URL ?>pages/ER_detail.php"
                    class="<?= $currentPage === 'ER_detail.php' ? 'active' : '' ?>"><i class="fa-solid fa-truck-medical"></i><span>ห้องอุบัติฉุกเฉินและฉุกเฉิน (ER)</span></a></li>
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
