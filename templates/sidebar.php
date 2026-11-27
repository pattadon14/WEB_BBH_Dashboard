<?php
$currentPage = basename($_SERVER['PHP_SELF']);
$currentWard = $_GET['ward'] ?? '';
$isProviderLoggedIn = provider_is_logged_in();
?>

<div class="bbh-sidebar-overlay" id="bbh-sidebar-overlay"></div>

<aside class="bbh-sidebar" id="bbh-sidebar" data-provider-logged-in="<?= $isProviderLoggedIn ? '1' : '0' ?>" aria-label="เมนูหลัก">
    <div class="bbh-sidebar-header">
        <div class="bbh-sidebar-brand">
            <img class="bbh-sidebar-logo" src="<?= BASE_URL ?>assets/img/logo-bbh-mark.png" alt="" width="36" height="36">
            <span><b>BBH Dashboard</b><small>โรงพยาบาลบ้านบึง</small></span>
        </div>
        <?php /* จอกว้าง = ซ่อนแถบ (ไอคอนลูกศร) · จอแคบ = ปิดลิ้นชัก (ไอคอน X) — sidebar.js */ ?>
        <button type="button" class="bbh-sidebar-close" id="bbh-sidebar-close" aria-label="ซ่อนแถบเมนู" title="ซ่อนแถบเมนู">
            <i class="fa-solid fa-angles-left is-wide" aria-hidden="true"></i>
            <i class="fa-solid fa-xmark is-narrow" aria-hidden="true"></i>
        </button>
    </div>

    <?php if (!$isProviderLoggedIn): ?>
        <div class="bbh-sidebar-guest-lock">
            <div class="bbh-sidebar-guest-lock-card">
                <i class="fa-solid fa-lock" aria-hidden="true"></i>
                <strong>กรุณาเข้าสู่ระบบ</strong>
                <span>เพื่อดูข้อมูลเพิ่มเติม</span>
                <a href="<?= BASE_URL ?>auth/provider_login.php" class="bbh-sidebar-guest-login">
                    <i class="fa-solid fa-id-card" aria-hidden="true"></i> เข้าสู่ระบบด้วย Provider ID
                </a>
            </div>
        </div>
    <?php endif; ?>

    <?php /* Guest: เมนูใต้ชั้นล็อกกด/Tab ไม่ได้ (inert) — ให้ปุ่มเข้าสู่ระบบเป็นทางเดียว */ ?>
    <div class="bbh-sidebar-body"<?= $isProviderLoggedIn ? '' : ' inert' ?>>
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
                    <?php /* ห้องคลอด (ward 04) ไม่มีการ์ดหน้าแรก (api/index_ward_bed.php ไม่นับ) จึงลิงก์ตรงด้วยรหัส ward */ ?>
                    <li><a href="<?= BASE_URL ?>pages/ipd_ward_detail.php?ward=04"
                            class="<?= $currentPage === 'ipd_ward_detail.php' && $currentWard === '04' ? 'active' : '' ?>"><i class="fa-solid fa-person-pregnant"></i><span>LR (ห้องคลอด)</span></a></li>
                    <li><a href="#" data-ipd-ward-key="icu"><i class="fa-solid fa-heart-pulse"></i><span>ICU (ผู้ป่วยหนัก)</span></a></li>
                    <li><a href="#" data-ipd-ward-key="snb"><i class="fa-solid fa-baby"></i><span>SNB (ทารกแรกเกิดป่วย)</span></a></li>
                    <li><a href="#" data-ipd-ward-key="pp"><i class="fa-solid fa-person-breastfeeding"></i><span>PP (มารดาหลังคลอด)</span></a></li>
                    <li><a href="#" data-ipd-ward-key="home"><i class="fa-solid fa-house-user"></i><span>Home Ward</span></a></li>
                </ul>
            </li>

            <li><a href="<?= BASE_URL ?>pages/ER_detail.php"
                    class="<?= $currentPage === 'ER_detail.php' ? 'active' : '' ?>"><i class="fa-solid fa-truck-medical"></i><span>ห้องอุบัติฉุกเฉินและฉุกเฉิน (ER)</span></a></li>
            <!-- แผนกที่ยังไม่มีหน้า: รวมเป็นกลุ่มเดียว พับไว้ ไม่ให้เมนูยาว -->
            <li class="bbh-sidebar-parent">
                <button type="button" class="bbh-sidebar-parent-toggle" data-sidebar-submenu="soon-submenu" aria-expanded="false">
                    <span class="bbh-sidebar-parent-main">
                        <i class="fa-solid fa-layer-group"></i><span>แผนกอื่นๆ</span><small class="bbh-soon">เร็วๆ นี้</small>
                    </span>
                    <i class="fa-solid fa-chevron-down bbh-sidebar-arrow"></i>
                </button>

                <ul class="bbh-sidebar-submenu" id="soon-submenu">
                    <li><a href="#" class="bbh-sidebar-static-item" aria-disabled="true" tabindex="-1"><i class="fa-solid fa-tooth"></i><span>ทันตกรรม</span></a></li>
                    <li><a href="#" class="bbh-sidebar-static-item" aria-disabled="true" tabindex="-1"><i class="fa-solid fa-mortar-pestle"></i><span>แพทย์แผนไทย</span></a></li>
                    <li><a href="#" class="bbh-sidebar-static-item" aria-disabled="true" tabindex="-1"><i class="fa-solid fa-person-walking"></i><span>กายภาพบำบัด</span></a></li>
                    <li><a href="#" class="bbh-sidebar-static-item" aria-disabled="true" tabindex="-1"><i class="fa-solid fa-droplet"></i><span>บำบัดไตเทียม</span></a></li>
                    <li><a href="#" class="bbh-sidebar-static-item" aria-disabled="true" tabindex="-1"><i class="fa-solid fa-person-running"></i><span>ผ่าตัดและวิสัญญี</span></a></li>
                    <li><a href="#" class="bbh-sidebar-static-item" aria-disabled="true" tabindex="-1"><i class="fa-solid fa-x-ray"></i><span>X-ray และ Ultrasound</span></a></li>
                </ul>
            </li>
        </ul>

        <div class="bbh-sidebar-divider"></div>

        <?php /* Dashboard ตามงาน — Patient Dashboard = หน้าแรก · ANC มีหน้าแล้ว (ไอคอน/ข้อความชมพู sidebar.css .bbh-sidebar-anc) · NCD / Finance ยังปิดไว้จนกว่าจะสร้างหน้า */ ?>
        <div class="bbh-sidebar-section-title">Dashboard ตามงาน</div>
        <ul class="bbh-sidebar-menu">
            <li><a href="<?= BASE_URL ?>" title="ภาพรวมการให้บริการผู้ป่วยทั้งโรงพยาบาลวันนี้ (หน้าแรก)"><i class="fa-solid fa-hospital"></i><span>Patient Dashboard</span></a></li>
            <li><a href="<?= BASE_URL ?>pages/ANC_detail.php" title="งานฝากครรภ์ (ANC)"
                    class="bbh-sidebar-anc <?= $currentPage === 'ANC_detail.php' ? 'active' : '' ?>"><i class="fa-solid fa-person-pregnant"></i><span>ANC Dashboard</span></a></li>
            <li><a href="#" class="bbh-sidebar-static-item" aria-disabled="true" tabindex="-1" title="โรคไม่ติดต่อเรื้อรัง (NCD) เช่น เบาหวาน ความดันโลหิตสูง"><i class="fa-solid fa-heart-circle-bolt"></i><span>NCD Dashboard</span><small class="bbh-soon">เร็วๆ นี้</small></a></li>
            <li><a href="#" class="bbh-sidebar-static-item" aria-disabled="true" tabindex="-1" title="การเงินและรายได้"><i class="fa-solid fa-sack-dollar"></i><span>Finance Dashboard</span><small class="bbh-soon">เร็วๆ นี้</small></a></li>
        </ul>
    </div>
</aside>
