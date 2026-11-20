<?php
require_once __DIR__ . '/../auth/provider.php';
provider_require_login_page();   // ยังไม่ login → กลับหน้าแรก

include '../templates/header.php';
include '../templates/navbar.php';
include '../templates/sidebar.php';

$currentYear = date('Y') + 543 + (date('n') >= 10 ? 1 : 0);
?>

<!-- งานฝากครรภ์ (ANC): โครงหน้าเดียวกับ ER/OPD/IPD (er_theme.css + opd_theme.css) ชุดสีชมพูกุหลาบ (anc_theme.css)
     ตัวเลขพอร์ตจากต้นแบบ ANC Dashboard (Flask) — api/anc_summary.php · นิยามใน config/anc.php -->
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/er_theme.css?v=<?= filemtime(__DIR__ . '/../assets/css/er_theme.css') ?>">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/opd_theme.css?v=<?= filemtime(__DIR__ . '/../assets/css/opd_theme.css') ?>">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/anc_theme.css?v=<?= filemtime(__DIR__ . '/../assets/css/anc_theme.css') ?>">

<div class="content-wrapper er-theme opd-theme anc-theme anc-page">
    <section class="content">
        <div class="er-page" id="anc-page">

            <div class="opd-toolbar">
                <a class="opd-tool-btn" href="<?= BASE_URL ?>index.php">
                    <i class="fa-solid fa-arrow-left" aria-hidden="true"></i> หน้าแรก
                </a>
            </div>

            <!-- แถบหัว: ชื่อหน้า + ช่วงเวลา (period_filter.js) + ตัวหาร / สัญชาติ (anc_detail.js) -->
            <header class="er-mast">
                <div class="er-mast-top">
                    <div class="er-mast-heading">
                        <h1 class="er-mast-title">งานฝากครรภ์ (ANC)</h1>
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
                <div class="anc-filters">
                    <span class="anc-filter-label" id="anc-scope-label">ตัวหาร</span>
                    <div class="bbh-seg" role="group" aria-labelledby="anc-scope-label">
                        <button type="button" data-scope="area" aria-pressed="true" title="หญิงไทยที่อาศัยในอำเภอบ้านบึง หรืออยู่นอกเขตแต่มาคลอดที่ รพ. (นิยามเอกสาร KPI)">ในเขต</button>
                        <button type="button" data-scope="all" aria-pressed="false" title="ทุกครรภ์ในทะเบียนฝากครรภ์">ทุกครรภ์</button>
                    </div>
                    <span class="anc-filter-label" id="anc-nat-label">สัญชาติ</span>
                    <div class="bbh-seg" role="group" aria-labelledby="anc-nat-label">
                        <button type="button" data-nat="" aria-pressed="true">ทั้งหมด</button>
                        <button type="button" data-nat="th" aria-pressed="false">ไทย</button>
                        <button type="button" data-nat="fr" aria-pressed="false" title="สัญชาติไม่ใช่ไทย รวมที่ไม่ระบุ">ต่างชาติ</button>
                    </div>
                </div>
            </header>

            <div class="anc-alert" id="anc-labor-alert" role="status" hidden></div>

            <!-- 1 ภาพรวม -->
            <section class="er-panel" aria-labelledby="anc-sec1">
                <header class="er-sec-head">
                    <span class="er-sec-no" aria-hidden="true">1</span>
                    <div>
                        <h2 class="er-h2" id="anc-sec1">ภาพรวมงานฝากครรภ์</h2>
                        <p class="er-sec-sub">นับเป็นครรภ์ · ร้อยละคิดจากครรภ์ที่คลอดในช่วงที่เลือก · ขีดเหลือง = เป้า</p>
                    </div>
                </header>
                <div class="er-kpis">
                    <div class="er-kpi er-k1">
                        <span class="er-kpi-label"><i class="fa-solid fa-person-pregnant" aria-hidden="true"></i>ฝากครรภ์รายใหม่</span>
                        <b class="er-kpi-num" id="kpi-new">—</b>
                        <span class="er-kpi-sub">ราย · กำลังติดตาม <b id="kpi-open">—</b> ราย</span>
                    </div>
                    <div class="er-kpi er-k2">
                        <span class="er-kpi-label"><i class="fa-solid fa-clipboard-list" aria-hidden="true"></i>ครั้งบริการ ANC</span>
                        <b class="er-kpi-num" id="kpi-visits">—</b>
                        <span class="er-kpi-sub">ครั้ง ที่ รพ.</span>
                    </div>
                    <div class="er-kpi er-k3">
                        <span class="er-kpi-label"><i class="fa-solid fa-calendar-days" aria-hidden="true"></i>เฉลี่ยครั้ง/เดือน</span>
                        <b class="er-kpi-num" id="kpi-per-month">—</b>
                        <span class="er-kpi-sub">ครั้ง/เดือน</span>
                    </div>
                    <div class="er-kpi er-k4">
                        <span class="er-kpi-label"><i class="fa-solid fa-stopwatch" aria-hidden="true"></i>ฝากครรภ์ก่อน 12 wk</span>
                        <b class="er-kpi-num" id="kpi-early12">—</b>
                        <span class="er-kpi-sub"><b id="kpi-early12-nd">—</b> ราย ที่คลอด</span>
                        <span class="anc-kbar" id="bar-early12" role="img"><span></span><i></i></span>
                    </div>
                    <div class="er-kpi er-k5">
                        <span class="er-kpi-label"><i class="fa-solid fa-circle-check" aria-hidden="true"></i>ANC ครบ 5 ครั้ง</span>
                        <b class="er-kpi-num" id="kpi-visit5">—</b>
                        <span class="er-kpi-sub"><b id="kpi-visit5-nd">—</b> ราย ที่คลอด</span>
                        <span class="anc-kbar" id="bar-visit5" role="img"><span></span><i></i></span>
                    </div>
                    <div class="er-kpi er-k6">
                        <span class="er-kpi-label"><i class="fa-solid fa-truck-medical" aria-hidden="true"></i>ส่งต่อ (Refer)</span>
                        <b class="er-kpi-num" id="kpi-refer">—</b>
                        <span class="er-kpi-sub">ครั้ง งานแม่และเด็ก</span>
                    </div>
                    <div class="er-kpi er-k7" id="kpi-labor-tile">
                        <span class="er-kpi-label"><i class="fa-solid fa-file-pen" aria-hidden="true"></i>บันทึกวันคลอด</span>
                        <b class="er-kpi-num" id="kpi-labor">—</b>
                        <span class="er-kpi-sub"><b id="kpi-labor-nd">—</b> ครรภ์ครบกำหนด</span>
                        <span class="anc-kbar" id="bar-labor" role="img"><span></span><i></i></span>
                    </div>
                </div>
                <p class="er-note">ครั้งบริการ / เฉลี่ยต่อเดือน นับเฉพาะที่ รพ. (ไม่รวมฝากครรภ์ที่หน่วยบริการอื่น) · ฝากครรภ์ก่อน 12 wk และครบ 5 ครั้งนับรวมที่หน่วยบริการอื่น</p>
            </section>

            <!-- 2 แนวโน้มรายเดือน -->
            <section class="er-panel" aria-labelledby="anc-sec2">
                <header class="er-sec-head">
                    <span class="er-sec-no" aria-hidden="true">2</span>
                    <div>
                        <h2 class="er-h2" id="anc-sec2">แนวโน้มรายเดือน</h2>
                        <p class="er-sec-sub">ครั้งบริการ ANC ที่ รพ. · ฝากครรภ์รายใหม่ · ส่งต่องานแม่และเด็ก</p>
                    </div>
                </header>
                <div id="anc-trend-chart" class="anc-chart"></div>
            </section>

            <!-- 3 ตัวชี้วัดคุณภาพ (api/anc_indicators.php) -->
            <section class="er-panel" aria-labelledby="anc-sec3">
                <header class="er-sec-head">
                    <span class="er-sec-no" aria-hidden="true">3</span>
                    <div>
                        <h2 class="er-h2" id="anc-sec3">ตัวชี้วัดคุณภาพ</h2>
                        <p class="er-sec-sub" id="anc-ind-sub">ANC 11 ตัว · ตัวหารในเขต</p>
                    </div>
                </header>
                <div class="table-responsive">
                    <table class="table er-table anc-ind-table" aria-labelledby="anc-sec3">
                        <thead>
                            <tr>
                                <th>ตัวชี้วัด</th>
                                <th class="text-right">ผลงาน</th>
                                <th class="text-right">ตัวตั้ง/ตัวหาร</th>
                                <th class="text-right">เป้า</th>
                                <th>สถานะ</th>
                            </tr>
                        </thead>
                        <tbody id="anc-ind-body"><tr><td colspan="5" class="er-note">กำลังโหลดข้อมูล…</td></tr></tbody>
                    </table>
                </div>
                <p class="er-note" id="anc-ind-note">ตัวหาร ≤12 wk / ครบ 5 และ 8 ครั้ง / คุณภาพ / dT / ช่องปาก = ครรภ์ที่คลอดในช่วง · หลังคลอด = คลอดในช่วงและพ้น 42 วัน ·
                    ไอโอดีน = ครรภ์ที่มาฝากครรภ์ที่ รพ. ในช่วง · โลหิตจาง = คลอดและมีผล Hct · LBW = ทารกเกิดมีชีพ ·
                    ตั้งครรภ์ซ้ำวัยรุ่น = คลอด/แท้งในช่วงและแม่อายุ &lt; 20 ปี · วัคซีน dT นับรหัส 106 · ค่าจาง = ตัวหารน้อยกว่า 5</p>
            </section>

        </div>
    </section>
</div>

<script src="<?= BASE_URL ?>assets/lib/highcharts/13.1.1/highcharts.js"></script>
<script src="<?= BASE_URL ?>assets/js/period_filter.js?v=<?= filemtime(__DIR__ . '/../assets/js/period_filter.js') ?>"></script>
<script src="<?= BASE_URL ?>assets/js/anc_detail.js?v=<?= filemtime(__DIR__ . '/../assets/js/anc_detail.js') ?>"></script>

<?php

include '../templates/footer.php';

?>
