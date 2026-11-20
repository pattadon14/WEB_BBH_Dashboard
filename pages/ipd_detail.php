<?php
// ป้าย LIVE ใน navbar แบบหน้าแรก — ipd_detail.js แสดงเฉพาะตอนเลือก "วันนี้" (bbhSetLive ใน header.php)
$showLiveStatus = true;

require_once __DIR__ . '/../auth/provider.php';
provider_require_login_page();   // ยังไม่ login → กลับหน้าแรก

include '../templates/header.php';
include '../templates/navbar.php';
include '../templates/sidebar.php';

// ปุ่ม (i) ข้างหัวข้อ → หน้าต่างที่มาของข้อมูล (เนื้อหา assets/js/ipd_info.js, หน้าต่าง assets/js/er_info.js)
function ipd_info_btn(string $key, string $label, bool $onMast = false): string
{
    return '<button type="button" class="er-info-btn' . ($onMast ? ' is-on-mast' : '') . '" data-info="' . $key . '"'
        . ' aria-label="ที่มาและนิยามของข้อมูล: ' . htmlspecialchars($label) . '" title="ที่มาและนิยามของข้อมูล">'
        . '<i class="fa-solid fa-circle-info" aria-hidden="true"></i></button>';
}

$currentYear = date('Y') + 543 + (date('n') >= 10 ? 1 : 0);
?>

<!-- โครงหน้าเดียวกับหน้า ER/OPD (er_theme.css + ส่วนเสริม opd_theme.css) ชุดสีฟ้า–น้ำเงิน (ipd_detail.css) · หน้าต่างรายชื่อใช้ opd_tabs.css -->
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/opd_tabs.css?v=<?= filemtime(__DIR__ . '/../assets/css/opd_tabs.css') ?>">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/er_theme.css?v=<?= filemtime(__DIR__ . '/../assets/css/er_theme.css') ?>">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/opd_theme.css?v=<?= filemtime(__DIR__ . '/../assets/css/opd_theme.css') ?>">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/ipd_detail.css?v=<?= filemtime(__DIR__ . '/../assets/css/ipd_detail.css') ?>">

<div class="content-wrapper er-theme opd-theme ipd-theme ipd-page">
    <section class="content">
        <div class="er-page" id="ipd-page">

            <div class="opd-toolbar">
                <a class="opd-tool-btn" href="<?= BASE_URL ?>index.php">
                    <i class="fa-solid fa-arrow-left" aria-hidden="true"></i> หน้าแรก
                </a>
            </div>

            <!-- แถบหัว: ชื่อหน้า + ป้าย Live (เลือก "วันนี้") + ช่วงเวลา (assets/js/period_filter.js เติมปุ่มและช่องวันที่) -->
            <header class="er-mast">
                <div class="er-mast-top">
                    <div class="er-mast-heading">
                        <div class="er-mast-title-row">
                            <h1 class="er-mast-title">ผู้ป่วยใน (IPD)</h1>
                            <span class="opd-live" id="ipd-live-badge" title="ข้อมูลวันนี้ รีเฟรชทุก 60 วินาที" hidden><span class="opd-live-dot" aria-hidden="true"></span>Live</span>
                            <?= ipd_info_btn('mast', 'ผู้ป่วยใน', true) ?>
                        </div>
                        <p class="er-mast-sub" id="budget-date-text">—</p>
                    </div>
                    <div id="period-filter" class="bbh-period er-mast-controls">
                        <div class="budget-select-wrapper">
                            <select id="budget-year" class="form-control budget-select" aria-label="ปีงบประมาณ">
                                <?php for ($y = $currentYear; $y >= 2565; $y--): ?>
                                    <option value="<?= $y ?>">ปีงบประมาณ <?= $y ?></option>
                                <?php endfor; ?>
                            </select>
                            <i class="fas fa-chevron-down budget-select-icon" aria-hidden="true"></i>
                        </div>
                    </div>
                </div>
            </header>

            <!-- 1 ภาพรวม · คลิกช่องดูรายชื่อ (ipd_detail.js → api/ipd_visit_list.php) -->
            <section class="er-panel" aria-labelledby="ipd-sec1">
                <header class="er-sec-head">
                    <span class="er-sec-no" aria-hidden="true">1</span>
                    <div>
                        <h2 class="er-h2" id="ipd-sec1">ภาพรวมผู้ป่วยใน <?= ipd_info_btn('sec1', 'ภาพรวมผู้ป่วยใน') ?></h2>
                        <p class="er-sec-sub">ผู้ป่วยตอนนี้ และยอดของช่วงเวลาที่เลือก นับเป็น AN</p>
                    </div>
                </header>
                <div class="er-kpis opd-kpis">
                    <div class="er-kpi er-k1" data-drill="current">
                        <span class="er-kpi-label"><i class="fa-solid fa-hospital-user" aria-hidden="true"></i>ผู้ป่วยในตอนนี้</span>
                        <b class="er-kpi-num" id="kpi-current">—</b>
                        <span class="er-kpi-sub">ราย จากเตียง <b id="kpi-beds">—</b></span>
                    </div>
                    <div class="er-kpi er-k2" data-drill="admit">
                        <span class="er-kpi-label"><i class="fa-solid fa-bed-pulse" aria-hidden="true"></i>รับใหม่</span>
                        <b class="er-kpi-num" id="kpi-admit">—</b>
                        <span class="er-kpi-sub">ราย</span>
                    </div>
                    <div class="er-kpi er-k3" data-drill="dch">
                        <span class="er-kpi-label"><i class="fa-solid fa-person-walking-arrow-right" aria-hidden="true"></i>จำหน่าย</span>
                        <b class="er-kpi-num" id="kpi-dch">—</b>
                        <span class="er-kpi-sub">ราย</span>
                    </div>
                    <div class="er-kpi er-k4" data-drill="death">
                        <span class="er-kpi-label"><i class="fa-solid fa-heart-crack" aria-hidden="true"></i>เสียชีวิต</span>
                        <b class="er-kpi-num" id="kpi-death">—</b>
                        <span class="er-kpi-sub">ราย · <b id="kpi-death-rate">–</b> ของผู้จำหน่าย</span>
                    </div>
                    <div class="er-kpi er-k5" data-drill="dchtype" data-key="04" data-label="ส่งต่อ (Refer)">
                        <span class="er-kpi-label"><i class="fa-solid fa-truck-medical" aria-hidden="true"></i>ส่งต่อ (Refer)</span>
                        <b class="er-kpi-num" id="kpi-refer">—</b>
                        <span class="er-kpi-sub">ราย · <b id="kpi-refer-rate">–</b> ของผู้จำหน่าย</span>
                    </div>
                </div>
            </section>

            <!-- 2–4 -->
            <div class="er-cols ipd-cols">

                <section class="er-panel er-col" aria-labelledby="ipd-sec2">
                    <header class="er-sec-head">
                        <span class="er-sec-no" aria-hidden="true">2</span>
                        <div><h2 class="er-h2" id="ipd-sec2">เตียงและการครองเตียง <?= ipd_info_btn('beds', 'เตียงและการครองเตียง') ?></h2><p class="er-sec-sub">ไม่นับ LR, Observe ER, Home Ward, ส่องกล้อง, ผ่าตัด</p></div>
                    </header>
                    <div class="er-stat er-stat-lead">
                        <span class="er-stat-label"><span class="er-ico" aria-hidden="true"><i class="fa-solid fa-percent"></i></span>อัตราครองเตียงเฉลี่ย <small id="occ-status">—</small></span>
                        <span class="er-stat-value"><b id="kpi-occ">—</b></span>
                    </div>
                    <div class="er-meter" role="img" id="occ-meter" aria-label="อัตราครองเตียง"><span></span></div>
                    <div class="er-stat">
                        <span class="er-stat-label"><span class="er-ico" aria-hidden="true"><i class="fa-solid fa-bed"></i></span>เตียงทั้งหมด</span>
                        <span class="er-stat-value"><b id="kpi-beds2">—</b> เตียง</span>
                    </div>
                    <div class="er-stat">
                        <span class="er-stat-label"><span class="er-ico" aria-hidden="true"><i class="fa-solid fa-calendar-days"></i></span>วันนอนรวม <small>นับคืนที่อยู่ในช่วงที่เลือก</small></span>
                        <span class="er-stat-value"><b id="kpi-pdays">—</b> วัน</span>
                    </div>
                </section>

                <section class="er-panel er-col" aria-labelledby="ipd-sec3">
                    <header class="er-sec-head">
                        <span class="er-sec-no" aria-hidden="true">3</span>
                        <div><h2 class="er-h2" id="ipd-sec3">คุณภาพการรักษา <?= ipd_info_btn('quality', 'คุณภาพการรักษา') ?></h2><p class="er-sec-sub">ของผู้ป่วยในช่วงที่เลือก</p></div>
                    </header>
                    <div class="er-stat er-stat-lead">
                        <span class="er-stat-label"><span class="er-ico" aria-hidden="true"><i class="fa-solid fa-hourglass-half"></i></span>ระยะเวลานอนเฉลี่ย (LOS)</span>
                        <span class="er-stat-value"><b id="kpi-los">—</b> วัน</span>
                    </div>
                    <div class="er-stat" data-drill="readmit">
                        <span class="er-stat-label"><span class="er-ico" aria-hidden="true"><i class="fa-solid fa-arrows-rotate"></i></span>Readmission 30 วัน <small id="readmit-status">—</small></span>
                        <span class="er-stat-value"><b id="kpi-readmit">—</b> ราย <small id="kpi-readmit-rate">–</small></span>
                    </div>
                    <div class="er-stat" data-drill="death">
                        <span class="er-stat-label"><span class="er-ico" aria-hidden="true"><i class="fa-solid fa-heart-crack"></i></span>อัตราเสียชีวิต <small>ต่อผู้ป่วยที่จำหน่าย</small></span>
                        <span class="er-stat-value"><b id="kpi-death-rate2">—</b></span>
                    </div>
                    <div class="er-stat" data-drill="dchtype" data-key="04" data-label="ส่งต่อ (Refer)">
                        <span class="er-stat-label"><span class="er-ico" aria-hidden="true"><i class="fa-solid fa-truck-medical"></i></span>อัตราส่งต่อ <small>ต่อผู้ป่วยที่จำหน่าย</small></span>
                        <span class="er-stat-value"><b id="kpi-refer-rate2">—</b></span>
                    </div>
                </section>

                <section class="er-panel er-col" aria-labelledby="ipd-sec4">
                    <header class="er-sec-head">
                        <span class="er-sec-no" aria-hidden="true">4</span>
                        <div><h2 class="er-h2" id="ipd-sec4">สถานะการจำหน่าย <?= ipd_info_btn('dch', 'สถานะการจำหน่าย') ?></h2><p class="er-sec-sub">ทุกแถวรวมได้ยอดจำหน่าย</p></div>
                    </header>
                    <div id="ipd-dch-list"><p class="er-note">กำลังโหลดข้อมูล…</p></div>
                </section>

            </div>

            <!-- 5 รายหอผู้ป่วย: การ์ดแบบหน้าแรก (ตัวเลขชุดเดียวกับ api/index_ward_bed.php) + ยอดของช่วงที่เลือก · การ์ดลิงก์ไปหน้ารายละเอียดหอ -->
            <section class="er-panel" aria-labelledby="ipd-sec5">
                <header class="er-sec-head">
                    <span class="er-sec-no" aria-hidden="true">5</span>
                    <div>
                        <h2 class="er-h2" id="ipd-sec5">รายหอผู้ป่วย <?= ipd_info_btn('wards', 'รายหอผู้ป่วย') ?></h2>
                        <p class="er-sec-sub">ครองเตียงตอนนี้ (ตรงกับหน้าแรก) และยอดของช่วงที่เลือก · คลิกการ์ดเพื่อดูรายละเอียดหอ</p>
                    </div>
                </header>
                <div class="ipd-ward-grid" id="ipd-ward-list" aria-live="polite"><p class="er-note">กำลังโหลดข้อมูล…</p></div>
            </section>

            <!-- 6 แนวโน้ม -->
            <section class="er-panel" aria-labelledby="ipd-sec6">
                <header class="er-sec-head">
                    <span class="er-sec-no" aria-hidden="true">6</span>
                    <div>
                        <h2 class="er-h2" id="ipd-sec6">แนวโน้มรับใหม่และการครองเตียง <?= ipd_info_btn('trend', 'แนวโน้ม') ?></h2>
                        <p class="er-sec-sub er-refer-hint"><i class="fa-solid fa-hand-pointer" aria-hidden="true"></i> คลิกแท่งรับใหม่เพื่อดูรายชื่อของวัน/เดือนนั้น</p>
                    </div>
                </header>
                <div class="er-charts">
                    <figure class="er-chart opd-span-3">
                        <figcaption class="er-h3"><span class="er-ico" aria-hidden="true"><i class="fa-solid fa-bed-pulse"></i></span><span>รับใหม่ (Admit)</span></figcaption>
                        <div id="ipd-chart"></div>
                    </figure>
                    <figure class="er-chart opd-span-3">
                        <figcaption class="er-h3"><span class="er-ico" aria-hidden="true"><i class="fa-solid fa-bed"></i></span><span>อัตราครองเตียง</span></figcaption>
                        <div id="bed-occupancy-chart"></div>
                    </figure>
                </div>
            </section>

            <!-- 7 10 อันดับโรค -->
            <section class="er-panel" aria-labelledby="ipd-sec7">
                <header class="er-sec-head">
                    <span class="er-sec-no" aria-hidden="true">7</span>
                    <div>
                        <h2 class="er-h2" id="ipd-sec7">10 อันดับโรคผู้ป่วยใน <?= ipd_info_btn('top10', '10 อันดับโรคผู้ป่วยใน') ?></h2>
                        <p class="er-sec-sub">ผู้ป่วยที่จำหน่ายในช่วงที่เลือก · คลิกแถวเพื่อดูรายชื่อ</p>
                    </div>
                </header>
                <div class="table-responsive">
                    <table class="table er-table table-ipd-top10" aria-labelledby="ipd-sec7">
                        <thead>
                            <tr>
                                <th class="text-center" style="width:64px">อันดับ</th>
                                <th class="text-center" style="width:100px">ICD-10</th>
                                <th>ชื่อโรค / การวินิจฉัย</th>
                                <th class="text-center" style="width:90px">รวม</th>
                                <th class="text-center" style="width:80px"><i class="fa-solid fa-mars" aria-hidden="true"></i> ชาย</th>
                                <th class="text-center" style="width:80px"><i class="fa-solid fa-venus" aria-hidden="true"></i> หญิง</th>
                            </tr>
                        </thead>
                        <tbody id="top10-ipd-body"></tbody>
                    </table>
                </div>
            </section>

        </div>
    </section>
</div>

<script src="<?= BASE_URL ?>assets/lib/highcharts/13.1.1/highcharts.js"></script>
<script src="<?= BASE_URL ?>assets/js/period_filter.js?v=<?= filemtime(__DIR__ . '/../assets/js/period_filter.js') ?>"></script>
<script src="<?= BASE_URL ?>assets/js/ipd_info.js?v=<?= filemtime(__DIR__ . '/../assets/js/ipd_info.js') ?>"></script>
<script src="<?= BASE_URL ?>assets/js/er_info.js?v=<?= filemtime(__DIR__ . '/../assets/js/er_info.js') ?>"></script>
<script src="<?= BASE_URL ?>assets/js/bbh_drill.js?v=<?= filemtime(__DIR__ . '/../assets/js/bbh_drill.js') ?>"></script>
<script src="<?= BASE_URL ?>assets/js/ipd_detail.js?v=<?= filemtime(__DIR__ . '/../assets/js/ipd_detail.js') ?>"></script>

<?php

include '../templates/footer.php';

?>
