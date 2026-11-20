<?php
require_once __DIR__ . '/../auth/provider.php';
provider_require_login_page();   // ยังไม่ login → กลับหน้าแรก

include '../templates/header.php';
include '../templates/navbar.php';
include '../templates/sidebar.php';

// ปุ่ม (i) ข้างหัวข้อ → หน้าต่างที่มาของข้อมูล (เนื้อหา assets/js/anc_info.js, หน้าต่าง assets/js/er_info.js)
function anc_info_btn(string $key, string $label, bool $onMast = false): string
{
    return '<button type="button" class="er-info-btn' . ($onMast ? ' is-on-mast' : '') . '" data-info="' . $key . '"'
        . ' aria-label="ที่มาและนิยามของข้อมูล: ' . htmlspecialchars($label) . '" title="ที่มาและนิยามของข้อมูล">'
        . '<i class="fa-solid fa-circle-info" aria-hidden="true"></i></button>';
}

// หัวข้อการ์ดแบบหน้า ER/IPD: เลขลำดับ + ชื่อ + (i) + คำอธิบาย
function anc_sec(int $no, string $id, string $title, string $info, string $sub, string $extra = ''): string
{
    return '<header class="er-sec-head"><span class="er-sec-no" aria-hidden="true">' . $no . '</span><div class="anc-sec-title">'
        . '<h2 class="er-h2" id="' . $id . '">' . $title . ' ' . anc_info_btn($info, $title) . '</h2>'
        . '<p class="er-sec-sub">' . $sub . '</p></div>' . $extra . '</header>';
}

$currentYear = date('Y') + 543 + (date('n') >= 10 ? 1 : 0);
?>

<!-- งานฝากครรภ์ (ANC): โครงหน้าเดียวกับ ER/OPD/IPD (er_theme.css + opd_theme.css) ชุดสีชมพูกุหลาบ (anc_theme.css) · หน้าต่างรายชื่อ opd_tabs.css
     ตัวเลขพอร์ตจากต้นแบบ ANC Dashboard (Flask) — api/anc_*.php · นิยามใน config/anc*.php -->
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/opd_tabs.css?v=<?= filemtime(__DIR__ . '/../assets/css/opd_tabs.css') ?>">
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
                        <div class="er-mast-title-row">
                            <h1 class="er-mast-title">งานฝากครรภ์ (ANC)</h1>
                            <?= anc_info_btn('mast', 'งานฝากครรภ์', true) ?>
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

            <!-- 1 ภาพรวม · คลิกช่องดูรายชื่อ (api/anc_visit_list.php ต้อง login) -->
            <section class="er-panel" aria-labelledby="anc-sec1">
                <?= anc_sec(1, 'anc-sec1', 'ภาพรวมงานฝากครรภ์', 'sec1', 'นับเป็นครรภ์ · ร้อยละคิดจากครรภ์ที่คลอดในช่วงที่เลือก · ขีดเหลือง = เป้า · คลิกช่องเพื่อดูรายชื่อ') ?>
                <div class="er-kpis">
                    <div class="er-kpi er-k1" data-drill="new" data-label="ฝากครรภ์รายใหม่">
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
                    <div class="er-kpi er-k4" data-drill="early12" data-label="ฝากครรภ์ครั้งแรก ≤ 12 wk">
                        <span class="er-kpi-label"><i class="fa-solid fa-stopwatch" aria-hidden="true"></i>ฝากครรภ์ก่อน 12 wk</span>
                        <b class="er-kpi-num" id="kpi-early12">—</b>
                        <span class="er-kpi-sub"><b id="kpi-early12-nd">—</b> ราย ที่คลอด</span>
                        <span class="anc-kbar" id="bar-early12" role="img"><span></span><i></i></span>
                    </div>
                    <div class="er-kpi er-k5" data-drill="visit5" data-label="ANC ครบ 5 ครั้ง">
                        <span class="er-kpi-label"><i class="fa-solid fa-circle-check" aria-hidden="true"></i>ANC ครบ 5 ครั้ง</span>
                        <b class="er-kpi-num" id="kpi-visit5">—</b>
                        <span class="er-kpi-sub"><b id="kpi-visit5-nd">—</b> ราย ที่คลอด</span>
                        <span class="anc-kbar" id="bar-visit5" role="img"><span></span><i></i></span>
                    </div>
                    <div class="er-kpi er-k6" data-drill="refer" data-label="ส่งต่องานแม่และเด็ก">
                        <span class="er-kpi-label"><i class="fa-solid fa-truck-medical" aria-hidden="true"></i>ส่งต่อ (Refer)</span>
                        <b class="er-kpi-num" id="kpi-refer">—</b>
                        <span class="er-kpi-sub">ครั้ง งานแม่และเด็ก</span>
                    </div>
                    <div class="er-kpi er-k7" id="kpi-labor-tile" data-drill="labor_missing" data-label="ครรภ์ครบกำหนดที่ยังไม่ลงวันคลอด">
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
                <?= anc_sec(2, 'anc-sec2', 'แนวโน้มรายเดือน', 'trend', 'ครั้งบริการ ANC ที่ รพ. · ฝากครรภ์รายใหม่ · ส่งต่องานแม่และเด็ก') ?>
                <div id="anc-trend-chart" class="anc-chart"></div>
            </section>

            <!-- 3 ตัวชี้วัดคุณภาพ (api/anc_indicators.php) -->
            <section class="er-panel" aria-labelledby="anc-sec3">
                <?= anc_sec(3, 'anc-sec3', 'ตัวชี้วัดคุณภาพ', 'ind', '<span id="anc-ind-sub">ANC 11 ตัว · ตัวหารในเขต</span> · คลิกแถวเพื่อดูรายชื่อ',
                    '<div class="anc-pass" id="anc-pass" aria-live="polite"></div>') ?>
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
                <p class="er-note">ตัวหาร ≤12 wk / ครบ 5 และ 8 ครั้ง / คุณภาพ / dT / ช่องปาก = ครรภ์ที่คลอดในช่วง · หลังคลอด = คลอดในช่วงและพ้น 42 วัน ·
                    ไอโอดีน = ครรภ์ที่มาฝากครรภ์ที่ รพ. ในช่วง · โลหิตจาง = คลอดและมีผล Hct · LBW = ทารกเกิดมีชีพ ·
                    ตั้งครรภ์ซ้ำวัยรุ่น = คลอด/แท้งในช่วงและแม่อายุ &lt; 20 ปี · วัคซีน dT นับรหัส 106 · ค่าจาง = ตัวหารน้อยกว่า 5</p>
            </section>

            <!-- 4 กลุ่มอายุ | 5 รายการที่ต้องติดตาม -->
            <div class="er-split">
                <section class="er-panel" aria-labelledby="anc-sec4">
                    <?= anc_sec(4, 'anc-sec4', 'กลุ่มอายุ', 'age', 'อายุแม่ ณ วันลงทะเบียน · ร้อยละของครรภ์ที่คลอดในแต่ละกลุ่ม') ?>
                    <div class="er-mix-bar" id="anc-age-bar" role="img" aria-label="สัดส่วนกลุ่มอายุ"></div>
                    <div class="table-responsive">
                        <table class="table er-table anc-mini-table">
                            <thead><tr><th>กลุ่ม</th><th class="text-right">รายใหม่</th><th class="text-right">ก่อน 12 wk</th><th class="text-right">ครบ 5</th><th class="text-right">โลหิตจาง</th></tr></thead>
                            <tbody id="anc-age-body"><tr><td colspan="5" class="er-note">กำลังโหลดข้อมูล…</td></tr></tbody>
                        </table>
                    </div>
                    <p class="er-note" id="anc-age-note"></p>
                </section>
                <section class="er-panel" aria-labelledby="anc-sec5">
                    <?= anc_sec(5, 'anc-sec5', 'รายการที่ต้องติดตาม', 'alerts', 'ตัวชี้วัดที่ไม่ผ่านเป้า และเหตุการณ์สำคัญ', '<span class="anc-count" id="anc-alert-count"></span>') ?>
                    <ul class="anc-alerts" id="anc-alerts"><li class="er-note">กำลังโหลดข้อมูล…</li></ul>
                </section>
            </div>

            <!-- 6 ผลลัพธ์แม่และทารก -->
            <section class="er-panel" aria-labelledby="anc-sec6">
                <?= anc_sec(6, 'anc-sec6', 'ผลลัพธ์แม่และทารก', 'outcome', 'ครรภ์ที่คลอดในช่วง (ตามตัวหารที่เลือก) และทารกเกิดใน รพ.') ?>
                <div class="anc-tiles" id="anc-outcome-tiles"><p class="er-note">กำลังโหลดข้อมูล…</p></div>
                <div class="er-split anc-split-in">
                    <div>
                        <h3 class="er-h3"><span class="er-ico" aria-hidden="true"><i class="fa-solid fa-notes-medical"></i></span>ภาวะแทรกซ้อน <small class="anc-h-note">Dx ระหว่างตั้งครรภ์ถึงหลังคลอด 42 วัน</small></h3>
                        <ul class="er-refer-list anc-bars" id="anc-cx-list"></ul>
                    </div>
                    <div>
                        <h3 class="er-h3"><span class="er-ico" aria-hidden="true"><i class="fa-solid fa-hospital"></i></span>คลอดที่อื่น <small class="anc-h-note">ครรภ์ที่คลอดในช่วงแต่ไม่ได้คลอดที่ รพ.บ้านบึง</small></h3>
                        <ul class="er-refer-list anc-bars" id="anc-place-list"></ul>
                    </div>
                </div>
            </section>

            <!-- 7 คัดกรองครรภ์เสี่ยง 27 ข้อ -->
            <section class="er-panel" aria-labelledby="anc-sec7">
                <?= anc_sec(7, 'anc-sec7', 'คัดกรองครรภ์เสี่ยง 27 ข้อ', 'risk27', 'ตามสมุดบันทึกสุขภาพแม่และเด็ก (สมุดสีชมพู) · ฐาน = ฝากครรภ์รายใหม่ในช่วง · ข้อเดียวพอถือเป็นครรภ์เสี่ยง') ?>
                <div class="anc-tiles anc-tiles-5" id="anc-r27-tiles"><p class="er-note">กำลังโหลดข้อมูล…</p></div>
                <p class="er-note" id="anc-r27-note"></p>
                <div class="anc-r27-grid" id="anc-r27-groups"></div>
                <p class="er-note">ที่มา: <span class="anc-srcb">F</span> ฟอร์มคัดกรอง HOSxP · <span class="anc-srcb">C</span> คำนวณจากอายุ / ส่วนสูง / BMI ของ visit แรก ·
                    <span class="anc-srcb">D</span> Dx ICD-10 (OPD/IPD) · <span class="anc-srcb is-t">T</span> ข้อความ HPI/PMH (ประมาณการ ตัดประโยคปฏิเสธก่อน อาจพลาดบางราย)</p>
            </section>

            <!-- 8 ติดตามภาวะซีด | 9 OGTT -->
            <div class="er-split">
                <section class="er-panel" aria-labelledby="anc-sec8">
                    <?= anc_sec(8, 'anc-sec8', 'ติดตามภาวะซีด', 'anemia', 'ผล Hct ทุกครั้งระหว่างตั้งครรภ์ · เทียบครั้งแรกกับครั้งล่าสุด · ฐาน = ครรภ์ที่คลอดในช่วง') ?>
                    <div class="anc-tiles anc-tiles-2" id="anc-hct-tiles"><p class="er-note">กำลังโหลดข้อมูล…</p></div>
                    <p class="er-note">ซีด = Hct &lt; 33% · ดีขึ้น = Hct เพิ่ม ≥ 3 จุด (ประมาณ Hb +1 g/dL) · หายซีด = ครั้งล่าสุดไม่ซีด · ซีดใหม่ = ครั้งแรกปกติแต่ครั้งล่าสุดซีด</p>
                </section>
                <section class="er-panel" aria-labelledby="anc-sec9">
                    <?= anc_sec(9, 'anc-sec9', 'ตรวจน้ำตาล OGTT', 'ogtt', 'ใบสั่ง lab ในช่วง (ระหว่าง LMP ถึงวันคลอด) · ผิดปกติ ≥ 2 ค่า = เข้าเกณฑ์เบาหวานขณะตั้งครรภ์') ?>
                    <div class="table-responsive">
                        <table class="table er-table anc-mini-table">
                            <thead><tr><th>ชนิด</th><th class="text-right">ตรวจแล้ว (คน)</th><th class="text-right">ผิดปกติ ≥ 2 ค่า</th><th class="text-right">ร้อยละ</th><th class="text-right">ผิดปกติ 1 ค่า</th></tr></thead>
                            <tbody id="anc-ogtt-body"><tr><td colspan="5" class="er-note">กำลังโหลดข้อมูล…</td></tr></tbody>
                        </table>
                    </div>
                    <p class="er-note">เกณฑ์ผิดปกติ (mg/dL): OGTT 100 g ก่อนกิน/1/2/3 ชม. ≥ 95 / 180 / 155 / 140 · OGTT 75 g ก่อนกิน/1/2 ชม. ≥ 92 / 180 / 153 ·
                        ตรวจซ้ำนับชุดล่าสุดของแต่ละชนิด · บ้านบึงไม่พบรหัส OGTT 75 g</p>
                </section>
            </div>

            <!-- 10 โรคติดเชื้อทางเพศสัมพันธ์ สารเสพติด NIPT -->
            <section class="er-panel" aria-labelledby="anc-sec10">
                <?= anc_sec(10, 'anc-sec10', 'โรคติดเชื้อทางเพศสัมพันธ์ สารเสพติด และ NIPT', 'sti', 'ผล lab ระหว่างตั้งครรภ์ (LMP ถึงวันคลอด) · ฐาน = ฝากครรภ์รายใหม่ในช่วง') ?>
                <div class="table-responsive">
                    <table class="table er-table anc-mini-table">
                        <thead><tr><th>การตรวจ</th><th class="text-right">ได้ตรวจ</th><th class="text-right">ร้อยละ</th><th class="text-right">ผลบวก</th><th class="text-right">ร้อยละผลบวก</th><th class="text-right">ผลไม่ชัด</th><th class="text-right">ยังไม่ได้ตรวจ</th></tr></thead>
                        <tbody id="anc-sti-body"><tr><td colspan="7" class="er-note">กำลังโหลดข้อมูล…</td></tr></tbody>
                    </table>
                </div>
                <p class="er-note">ผลพิมพ์มือจัดกลุ่มเป็นบวก (Reactive / Positive) · ลบ (Non-reactive / Negative) · ไม่ชัด (ว่าง / Inconclusive / อื่น ๆ ไม่นับเป็นลบ) ·
                    ตรวจหลายครั้งพบบวกครั้งใดนับเป็นบวก · NIPT นับเฉพาะ "ได้ตรวจ" (ผลเป็นรายงานภายนอก)</p>
            </section>

            <!-- 11 ส่งต่องานแม่และเด็ก (api/anc_refer.php) -->
            <section class="er-panel" aria-labelledby="anc-sec11">
                <?= anc_sec(11, 'anc-sec11', 'ส่งต่องานแม่และเด็ก (Refer)', 'refer', 'ทะเบียนฝากครรภ์ถึงหลังคลอด 42 วัน + Dx O / Z32–Z36 · นับครั้ง · คลิกยอดรวมเพื่อดูรายชื่อ',
                    '<button type="button" class="anc-total-btn" data-drill="refer" data-label="ส่งต่องานแม่และเด็ก">รวม <b id="anc-refer-total">—</b> ครั้ง</button>') ?>
                <div class="er-charts">
                    <figure class="er-chart er-chart-wide">
                        <figcaption class="er-h3"><span class="er-ico" aria-hidden="true"><i class="fa-solid fa-chart-column"></i></span><span>Refer รายเดือน แยกกลุ่มอายุ</span></figcaption>
                        <div id="anc-refer-chart" class="anc-chart-sm"></div>
                    </figure>
                    <figure class="er-chart er-chart-third anc-third">
                        <figcaption class="er-h3"><span class="er-ico" aria-hidden="true"><i class="fa-solid fa-hospital"></i></span><span>โรงพยาบาลปลายทาง</span></figcaption>
                        <ul class="er-refer-list anc-bars" id="anc-dest-list"></ul>
                    </figure>
                    <figure class="er-chart er-chart-third anc-third">
                        <figcaption class="er-h3"><span class="er-ico" aria-hidden="true"><i class="fa-solid fa-stethoscope"></i></span><span>10 อันดับ Dx หลัก (3 หลัก)</span></figcaption>
                        <ul class="er-refer-list anc-bars" id="anc-dx-list"></ul>
                        <p class="er-note" id="anc-dx-note"></p>
                    </figure>
                    <figure class="er-chart er-chart-third anc-third">
                        <figcaption class="er-h3"><span class="er-ico" aria-hidden="true"><i class="fa-solid fa-file-lines"></i></span><span>สรุปรายเดือน × กลุ่ม Dx × ปลายทาง</span></figcaption>
                        <div class="anc-scroll-y">
                            <table class="table er-table anc-mini-table">
                                <thead><tr><th>เดือน</th><th>กลุ่ม Dx / ปลายทาง</th><th class="text-right">ครั้ง</th><th class="text-right">นาที*</th></tr></thead>
                                <tbody id="anc-refer-table"></tbody>
                            </table>
                        </div>
                        <p class="er-note">* นาทีเฉลี่ยจากเวลามารับบริการถึงเวลา refer</p>
                    </figure>
                </div>
            </section>

            <!-- 12 แนวโน้ม 3 ปีงบ (เรียก api/anc_indicators.php 3 ครั้ง) -->
            <section class="er-panel" aria-labelledby="anc-sec12">
                <?= anc_sec(12, 'anc-sec12', 'แนวโน้มตัวชี้วัด 3 ปีงบประมาณ', 'fy3', 'ใช้ตัวหาร/สัญชาติเดียวกับด้านบน · ปีงบล่าสุดนับถึงวันสิ้นสุดที่เลือก · ลูกศรเทียบกับปีก่อนหน้า') ?>
                <div class="table-responsive">
                    <table class="table er-table anc-mini-table" id="anc-fy3-table">
                        <thead id="anc-fy3-head"></thead>
                        <tbody id="anc-fy3-body"><tr><td class="er-note">กำลังโหลดข้อมูล 3 ปีงบ (ครั้งแรกอาจใช้เวลาประมาณครึ่งนาที)…</td></tr></tbody>
                    </table>
                </div>
            </section>

        </div>
    </section>
</div>

<script src="<?= BASE_URL ?>assets/lib/highcharts/13.1.1/highcharts.js"></script>
<script src="<?= BASE_URL ?>assets/js/period_filter.js?v=<?= filemtime(__DIR__ . '/../assets/js/period_filter.js') ?>"></script>
<script src="<?= BASE_URL ?>assets/js/anc_info.js?v=<?= filemtime(__DIR__ . '/../assets/js/anc_info.js') ?>"></script>
<script src="<?= BASE_URL ?>assets/js/er_info.js?v=<?= filemtime(__DIR__ . '/../assets/js/er_info.js') ?>"></script>
<script src="<?= BASE_URL ?>assets/js/bbh_drill.js?v=<?= filemtime(__DIR__ . '/../assets/js/bbh_drill.js') ?>"></script>
<script src="<?= BASE_URL ?>assets/js/anc_detail.js?v=<?= filemtime(__DIR__ . '/../assets/js/anc_detail.js') ?>"></script>

<?php

include '../templates/footer.php';

?>
