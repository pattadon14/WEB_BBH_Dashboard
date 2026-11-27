<?php

// หน้าแรกแสดงสถานะอัปเดตใต้คำทักทาย (.hx-head) แทนป้าย LIVE ใน navbar — จึงไม่ตั้ง $showLiveStatus

include 'templates/header.php';
include 'templates/navbar.php';
include 'templates/sidebar.php';

// ปุ่ม (i) ข้างหัวข้อ → หน้าต่างที่มาของข้อมูล (เนื้อหา assets/js/home_info.js, หน้าต่าง assets/js/er_info.js)
function home_info_btn(string $key, string $label): string
{
    return '<button type="button" class="er-info-btn" data-info="' . $key . '"'
        . ' aria-label="ที่มาและนิยามของข้อมูล: ' . htmlspecialchars($label) . '" title="ที่มาและนิยามของข้อมูล">'
        . '<i class="fa-solid fa-circle-info" aria-hidden="true"></i></button>';
}

// การ์ดบริการอื่น ๆ: key = key ของ api/service_summary_today.php (<key>_total) = id ตัวเลขที่ service_detail.js ใช้เปิดรายละเอียด
$services = [
    ['scope', 'ห้องส่องกล้อง', 'bi-endoscopy'],
    ['or', 'ห้องผ่าตัด', 'bi-surgical-scalpel'],
    ['pt', 'กายภาพบำบัด', 'bi-physical-therapist'],
    ['thai', 'แพทย์แผนไทย', 'bi-thai-herbal-compress-ball'],
    ['dent', 'ทันตกรรม', 'bi-tooth'],
    ['hemo', 'ไตเทียม', 'bi-kidneys'],
    ['wound', 'ห้องทำแผล-ฉีดยา', 'bi-bandage'],
    ['us', 'อัลตราซาวน์', 'bi-ultrasound-scan'],
    ['observe', 'Observe ER', 'bi-examination-couch'],
    ['xray', 'X-Ray', 'bi-x-ray-film'],
    ['lr', 'LR', 'bi-pregnant-person'],
    ['lab', 'Lab', 'bi-test-tube'],
    ['ct', 'CT Scan', 'bi-ct-scanner'],
    ['cashier', 'ห้องเก็บเงิน', 'bi-checkout-counter'],
    ['rights', 'ศูนย์พิทักษ์สิทธิ์', 'bi-balance-scale'],
];

?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;500;600;700&display=swap">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/index.css?v=<?= filemtime(__DIR__ . '/assets/css/index.css') ?>">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/bbh-icons.css?v=<?= filemtime(__DIR__ . '/assets/css/bbh-icons.css') ?>">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/home.css?v=<?= filemtime(__DIR__ . '/assets/css/home.css') ?>">

<div class="content-wrapper home-dash">

    <section class="content">

        <!-- ธีมกระจกเขียว: assets/css/home.css (ครอบด้วย #hx) -->
        <div id="hx">
            <div class="hx-orbs" aria-hidden="true"><i></i><i></i><i></i></div>

            <div class="hx-wrap">

                <header class="hx-head">
                    <div>
                        <h1 id="hx-hello">สวัสดี</h1>
                        <!-- เวลาอัปเดตโดย markUpdated() หลังโหลดข้อมูลสำเร็จ (รีเฟรชอัตโนมัติด้วย bbhPoll) -->
                        <p class="hx-status" role="status" aria-live="polite">
                            <span class="hx-pulse" aria-hidden="true"></span>
                            <span><span id="hx-today"></span>, <span id="last-updated">กำลังโหลดข้อมูล…</span></span>
                        </p>
                    </div>
                </header>

                <!-- ============ ตัวเลขหลักวันนี้ (assets/js/dashboard.js loadCard) ============ -->
                <section class="glass hx-strip" aria-label="ตัวเลขหลักวันนี้">

                    <!-- นิยามเดียวกับหน้า OPD หัวข้อ 7 (api/opd.php: ทุกแผนก OPD นับครั้ง) -->
                    <div class="hx-kpi">
                        <div class="hx-lbl"><i class="bi bi-stethoscope" aria-hidden="true"></i>ผู้ป่วยนอก (OPD) วันนี้ <?= home_info_btn('opd', 'ผู้ป่วยนอกวันนี้') ?></div>
                        <div class="hx-big"><span id="opd-total">–</span><small>ครั้ง</small></div>
                        <!-- วันเดียวกันของสัปดาห์ก่อน ถึงเวลาเดียวกัน (prev_week ของ API การ์ด) -->
                        <span class="kpi-trend" id="opd-trend"></span>
                        <span class="hx-sub">เดือนนี้ <b id="opd-month-patient">–</b> คน, <b id="opd-month-visit">–</b> ครั้ง</span>
                        <div class="hx-links">
                            <a href="pages/opd_detail.php" class="hx-go">รายละเอียด <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
                            <a href="pages/opd_monitor.php" class="hx-go is-solid" aria-label="เปิด OPD Real-time Monitor"><i class="bi bi-live-broadcast" aria-hidden="true"></i> Real-time Monitor</a>
                        </div>
                    </div>

                    <!-- นิยามเดียวกับ "ผู้มารับบริการ ER" หน้า ER (api/er.php: er_regist ทุกประเภท นับครั้ง) -->
                    <div class="hx-kpi">
                        <div class="hx-lbl"><i class="bi bi-ambulance" aria-hidden="true"></i>ผู้มารับบริการ ER วันนี้ <?= home_info_btn('er', 'ผู้มารับบริการ ER วันนี้') ?></div>
                        <div class="hx-big"><span id="er-total">–</span><small>ครั้ง</small></div>
                        <span class="kpi-trend" id="er-trend"></span>
                        <span class="hx-sub">เดือนนี้ <b id="er-month-patient">–</b> คน, <b id="er-month-visit">–</b> ครั้ง</span>
                        <div class="hx-links">
                            <a href="pages/ER_detail.php" class="hx-go">รายละเอียด <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
                        </div>
                    </div>

                    <!-- นิยามเดียวกับช่อง "รับใหม่" หน้า IPD (api/ipd.php: ipt.regdate ทุกหอ นับ AN) -->
                    <div class="hx-kpi">
                        <div class="hx-lbl"><i class="bi bi-hospital-bed" aria-hidden="true"></i>ผู้ป่วยในรับใหม่ (Admit) วันนี้ <?= home_info_btn('ipd', 'ผู้ป่วยในรับใหม่วันนี้') ?></div>
                        <div class="hx-big"><span id="ipd-total">–</span><small>ราย</small></div>
                        <span class="kpi-trend" id="ipd-trend"></span>
                        <span class="hx-sub">เดือนนี้ <b id="ipd-month-patient">–</b> คน, <b id="ipd-month-visit">–</b> ราย</span>
                        <div class="hx-links">
                            <a href="pages/ipd_detail.php" class="hx-go">รายละเอียด <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
                        </div>
                    </div>

                </section>

                <!-- ============ แนวโน้มปีงบ + เตียง ============ -->
                <div class="hx-lay">

                    <!-- api/service_trend.php?year= (นับ visit ทุกแผนก OPD รายเดือน — นิยามเดียวกับกราฟแนวโน้มหน้า OPD) -->
                    <section class="glass hx-sec hx-chart" aria-labelledby="hx-trend-title">
                        <div class="hx-sec-h">
                            <div>
                                <h2 id="hx-trend-title">แนวโน้มผู้ป่วยนอกรายเดือน</h2>
                                <p id="hx-trend-sum">กำลังโหลด…</p>
                            </div>
                            <a href="pages/opd_detail.php" class="hx-go">ดูหน้า OPD <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
                        </div>
                        <svg id="hx-trend" role="img" aria-label="กราฟจำนวนผู้ป่วยนอกรายเดือน ปีงบประมาณนี้เทียบปีก่อน"></svg>
                        <div class="hx-legend" id="hx-trend-legend"></div>
                    </section>

                    <!-- api/bed_summary.php (ไม่นับ Home Ward) -->
                    <section class="glass hx-sec" aria-labelledby="hx-bed-title">
                        <div class="hx-sec-h">
                            <div>
                                <h2 id="hx-bed-title">เตียงผู้ป่วยใน <?= home_info_btn('beds', 'สถิติผู้ป่วยในวันนี้') ?></h2>
                                <p>อัตราครองเตียงทั้ง รพ. ตอนนี้</p>
                            </div>
                            <span class="chip" id="hx-bed-chip"></span>
                        </div>
                        <div class="hx-ring" id="hx-ring">
                            <svg viewBox="0 0 136 136" id="hx-ring-svg" aria-hidden="true"></svg>
                            <div class="hx-kv">
                                <div><span id="wblank-label">เตียงว่าง</span><b id="wblank">–</b></div>
                                <div><span>รับใหม่วันนี้</span><b id="admittoday">–</b></div>
                                <div><span>จำหน่ายวันนี้</span><b id="dchtoday">–</b></div>
                                <div><span>เตียงทั้งหมด</span><b id="bedcount">–</b></div>
                            </div>
                        </div>
                        <div class="hx-rights" aria-label="ผู้ป่วยในตอนนี้แยกตามสิทธิ์">
                            <div class="hx-mini"><span>สิทธิ์ UC</span><b id="uc">–</b></div>
                            <div class="hx-mini"><span>ชำระเงิน/เบิกได้</span><b id="mo">–</b></div>
                            <div class="hx-mini"><span>สิทธิ์อื่น ๆ</span><b id="ot">–</b></div>
                        </div>
                    </section>

                </div>

                <!-- ============ คุณภาพบริการวันนี้ (assets/js/home_quality.js) ============
                     ใช้ API ชุดเดียวกับหน้ารายละเอียด ตัวเลขจึงตรงกัน: OPD = api/opd_service_kpi.php (แท็บเวลารอ),
                     ER = api/er_los.php (สถานะตอนนี้) + er_d2d.php + er_today_summary.php -->
                <div class="hx-q">
                    <section class="q-panel q-opd" aria-labelledby="q-opd-title">
                        <header class="q-head">
                            <div class="q-title">
                                <i class="bi bi-stopwatch" aria-hidden="true"></i>
                                <div>
                                    <h6 id="q-opd-title">เวลารอ OPD วันนี้ <?= home_info_btn('q_opd', 'เวลารอ OPD') ?></h6>
                                    <p>นาทีเฉลี่ยต่อราย เทียบเป้าหมาย</p>
                                </div>
                            </div>
                            <a class="q-go" href="pages/opd_detail.php">ดูหน้า OPD <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
                        </header>
                        <div class="q-grid" id="q-opd" aria-live="polite"><p class="q-msg">กำลังโหลด…</p></div>
                    </section>

                    <section class="q-panel q-er" aria-labelledby="q-er-title">
                        <header class="q-head">
                            <div class="q-title">
                                <i class="bi bi-ambulance" aria-hidden="true"></i>
                                <div>
                                    <h6 id="q-er-title">ER ตอนนี้ <?= home_info_btn('q_er', 'ER ตอนนี้') ?></h6>
                                    <p id="q-er-at">สถานะผู้ป่วยในห้องฉุกเฉิน</p>
                                </div>
                            </div>
                            <a class="q-go" href="pages/ER_detail.php">ดูหน้า ER <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
                        </header>
                        <div class="q-grid" id="q-er" aria-live="polite"><p class="q-msg">กำลังโหลด…</p></div>
                    </section>
                </div>

                <!-- ============ รายรับตามสิทธิเดือนนี้ (api/finance_income_pttype.php · ต้อง login) ============ -->
                <section class="glass hx-sec hx-fin" aria-labelledby="hx-fin-title">
                    <div class="hx-sec-h">
                        <div>
                            <h2 id="hx-fin-title"><i class="bi bi-banknote" aria-hidden="true"></i>รายรับตามสิทธิการรักษา เดือนนี้ <?= home_info_btn('finance', 'รายรับตามสิทธิ') ?></h2>
                            <p id="hx-fin-sub">ยอดเรียกเก็บ เทียบเดือนก่อนช่วงวันเดียวกัน</p>
                        </div>
                    </div>
                    <div class="fin-body" id="hx-fin" aria-live="polite"><p class="fin-msg">กำลังโหลด…</p></div>
                </section>

                <!-- ============ สถานะเตียงรายหอผู้ป่วย (api/index_ward_bed.php · คลิกการ์ด = login.js เปิดหน้า Ward) ============ -->
                <section class="glass hx-sec" aria-labelledby="hx-ward-title">
                    <div class="hx-sec-h">
                        <div>
                            <h2 id="hx-ward-title"><i class="bi bi-hospital" aria-hidden="true"></i>สถานะเตียงรายหอผู้ป่วย <?= home_info_btn('wards', 'สถานะเตียงรายหอผู้ป่วย') ?></h2>
                            <p>เรียงจากหอที่แน่นที่สุด คลิกเพื่อดูรายละเอียด</p>
                        </div>
                        <div class="hx-count" id="ward-sum"></div>
                    </div>
                    <div class="hx-wards" id="ward-container" aria-live="polite"></div>
                </section>

                <!-- ============ บริการอื่น ๆ วันนี้ (api/service_summary_today.php · คลิก = service_detail.js) ============ -->
                <section class="glass hx-sec" aria-labelledby="hx-svc-title">
                    <div class="hx-sec-h">
                        <div>
                            <h2 id="hx-svc-title"><i class="bi bi-first-aid-kit" aria-hidden="true"></i>บริการอื่น ๆ วันนี้ <?= home_info_btn('services', 'สถิติการใช้บริการอื่นๆ') ?></h2>
                            <p>เรียงจากมากไปน้อย คลิกเพื่อดูรายละเอียด</p>
                        </div>
                    </div>
                    <div class="hx-svc" id="service-container">
                        <?php foreach ($services as [$key, $name, $icon]): ?>
                            <div class="service-item">
                                <div class="service-name"><i class="bi <?= $icon ?>" aria-hidden="true"></i><span><?= $name ?></span></div>
                                <div class="svc-num"><span class="service-total" id="<?= $key ?>_total">–</span><small>ราย</small></div>
                                <div class="hx-tr" aria-hidden="true"><i></i></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>

                <!-- ============ ความหนาแน่นแต่ละแผนกตามช่วงเวลา (Highcharts ใน dashboard.js) ============ -->
                <section class="hx-hm" aria-labelledby="hx-hm-title">
                    <div class="hx-sec-h">
                        <div>
                            <h2 id="hx-hm-title"><i class="bi bi-flame" aria-hidden="true"></i>ภาระงานแต่ละแผนกตามช่วงเวลา วันนี้ <?= home_info_btn('heatmap', 'ภาระงานแต่ละแผนก') ?></h2>
                            <p>ผู้ป่วยรายชั่วโมงของแต่ละแผนก สีเข้ม = มาก</p>
                        </div>
                    </div>
                    <div class="hx-hm-k">
                        <div class="hx-mini"><span>ชั่วโมงที่แออัดสุด</span><b id="hm-peak-hour">–</b></div>
                        <div class="hx-mini"><span>แผนกที่แออัดสุดวันนี้</span><b id="hm-peak-dept">–</b></div>
                        <button type="button" id="hm-toggle" aria-controls="heatmap-chart" hidden></button>
                    </div>
                    <div id="heatmap-chart" style="min-height:400px;"></div>
                </section>

                <!-- เครดิตตามเงื่อนไข IconSaga Free License (ไอคอนใน assets/img/iconsaga/) -->
                <p class="hx-credit">Icons by <a href="https://iconsaga.com" target="_blank" rel="noopener">IconSaga</a></p>

            </div>
        </div>

    </section>

    <!-- รายละเอียดเมื่อคลิกบริการในการ์ด "บริการอื่น ๆ วันนี้" (assets/js/service_detail.js · รายชื่อต้อง login) -->
    <dialog class="opd-drill svc-detail" id="svc-detail" aria-labelledby="svc-detail-title">
        <!-- หัวหน้าต่าง: ชื่อบริการ | เลือกวันที่ (service_detail.js โหลดบริการเดิมของวันที่เลือก) | ปิด — จอแคบวันที่ลงบรรทัดใหม่ใต้ชื่อ -->
        <header class="opd-drill-head svc-head">
            <div class="svc-head-title">
                <!-- ปุ่ม (i) อยู่นอก h3 เพราะ service_detail.js เขียนทับ h3 ทุกครั้งที่เปิด · data-info = svc_<key> ตั้งตอนเปิด -->
                <div class="svc-head-row">
                    <h3 id="svc-detail-title">รายละเอียดบริการ</h3>
                    <?= home_info_btn('', 'บริการนี้') ?>
                </div>
                <p id="svc-detail-sub"></p>
            </div>
            <div class="svc-date-bar">
                <div class="svc-seg" role="group" aria-label="เลือกวันด่วน">
                    <button type="button" data-svc-day="0" aria-pressed="true">วันนี้</button>
                    <button type="button" data-svc-day="1" aria-pressed="false">เมื่อวาน</button>
                    <button type="button" data-svc-day="7" aria-pressed="false">สัปดาห์ก่อน</button>
                </div>
                <!-- ช่องวันที่แบบเดียวกับตัวเลือกช่วงเวลา (theme.css .bbh-range/.bbh-date): แสดง พ.ศ. ทับ input date ที่โปร่งใส -->
                <div class="bbh-range">
                    <i class="fa-solid fa-calendar-day" aria-hidden="true"></i>
                    <label class="bbh-date"><span id="svc-detail-date-text"></span><input type="date" id="svc-detail-date" aria-label="เลือกวันที่"></label>
                </div>
            </div>
            <button type="button" class="opd-drill-close" id="svc-detail-close" aria-label="ปิด"><i class="fa-solid fa-xmark"></i></button>
        </header>
        <div class="svc-detail-scroll">
            <div class="svc-detail-summary" id="svc-detail-summary"></div>
            <div class="svc-detail-list" id="svc-detail-list" hidden>
                <h4 class="svc-list-title">รายชื่อผู้รับบริการ</h4>
                <!-- แผงค้นหา + ตัวกรอง + Export รวมกล่องเดียว -->
                <div class="svc-filterbar">
                    <div class="opd-drill-tools">
                        <label class="opd-drill-search">
                            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                            <input type="search" id="svc-detail-q" placeholder="ค้นหา HN / ชื่อ / แผนก / สิทธิ์ / รายการ" aria-label="ค้นหาในรายชื่อ">
                        </label>
                        <span class="opd-drill-count" id="svc-detail-count" aria-live="polite"></span>
                        <button type="button" class="svc-filter-reset" id="svc-detail-reset" hidden><i class="fa-solid fa-rotate-left" aria-hidden="true"></i> ล้างตัวกรอง</button>
                        <button type="button" class="opd-drill-export" id="svc-detail-export"><i class="fa-solid fa-file-excel"></i> Export Excel</button>
                    </div>
                    <div class="opd-drill-filters" id="svc-detail-filters"></div>
                </div>
                <div class="opd-drill-body" id="svc-detail-body"></div>
                <nav class="opd-drill-pager" id="svc-detail-pager" aria-label="เปลี่ยนหน้ารายชื่อ"></nav>
            </div>
        </div>
    </dialog>

</div>

<!-- Highcharts เก็บไว้ในเว็บเอง (assets/lib) ไม่โหลดจาก code.highcharts.com: CDN นั้นปฏิเสธคำขอที่ไม่มี Referer และเปลี่ยนเวอร์ชันเองได้ -->
<script src="assets/lib/highcharts/13.1.1/highcharts.js"></script>
<script src="assets/lib/highcharts/13.1.1/modules/heatmap.js"></script>
<script src="assets/js/dashboard.js?v=<?= filemtime(__DIR__ . '/assets/js/dashboard.js') ?>"></script>
<script src="assets/js/service_detail.js?v=<?= filemtime(__DIR__ . '/assets/js/service_detail.js') ?>"></script>
<script src="assets/js/home_quality.js?v=<?= filemtime(__DIR__ . '/assets/js/home_quality.js') ?>"></script>
<script src="assets/js/home_info.js?v=<?= filemtime(__DIR__ . '/assets/js/home_info.js') ?>"></script>
<script src="assets/js/er_info.js?v=<?= filemtime(__DIR__ . '/assets/js/er_info.js') ?>"></script>
<script>
const fmt = n => Number(n).toLocaleString('th-TH');
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
// แอนิเมชันเข้ามาเฉพาะครั้งแรกที่วาด ไม่เล่นซ้ำทุกครั้งที่ bbhPoll รีเฟรช
const enterOnce = el => { if (!el.dataset.entered) { el.dataset.entered = '1'; el.classList.add('is-enter'); } };

// คำทักทาย + วันที่ ตามเวลาเครื่องผู้ดู (เปลี่ยนเองเมื่อข้ามช่วงเวลา/ข้ามวัน เพราะเรียกซ้ำทุกครั้งที่อัปเดต)
function setGreeting(now) {
    const h = now.getHours();
    document.getElementById('hx-hello').textContent =
        'สวัสดี' + (h < 12 ? 'ตอนเช้า' : h < 16 ? 'ตอนบ่าย' : h < 19 ? 'ตอนเย็น' : 'ตอนค่ำ');
    document.getElementById('hx-today').textContent =
        now.toLocaleDateString('th-TH', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
}
setGreeting(new Date());

// เรียกหลังโหลดข้อมูลสำเร็จเท่านั้น ถ้า API ล่ม เวลาจะค้างไว้ที่ครั้งล่าสุดที่ได้ข้อมูลจริง
function markUpdated() {
    const now = new Date();
    setGreeting(now);
    document.getElementById('last-updated').textContent =
        'อัปเดตข้อมูลล่าสุด ' + now.toLocaleTimeString('th-TH', { hour: '2-digit', minute: '2-digit', second: '2-digit' }) + ' น.';
    document.querySelector('.hx-status').classList.add('is-live');
}

/* ---------------- แนวโน้มผู้ป่วยนอกรายเดือน: ปีงบนี้ เทียบปีงบก่อน ---------------- */
// ปีงบประมาณ พ.ศ. (ต.ค.–ก.ย.) แบบเดียวกับ config/period.php
const FY = new Date().getFullYear() + 543 + (new Date().getMonth() >= 9 ? 1 : 0);
let trendData = null;

async function loadTrend() {
    const sum = document.getElementById('hx-trend-sum');
    try {
        const [cur, prev] = await Promise.all([FY, FY - 1].map(y =>
            fetch('api/service_trend.php?year=' + y).then(r => { if (!r.ok) throw new Error(r.status); return r.json(); })));
        trendData = { cur: cur.trend.map(t => t.opd), prev: prev.trend.map(t => t.opd), labels: prev.trend.map(t => t.label.split(' ')[0]) };

        // เทียบเฉพาะเดือนที่จบแล้ว — เดือนปัจจุบันยังนับไม่ครบเดือน เทียบกับเดือนเต็มของปีก่อนไม่ได้
        const done = trendData.cur.length - 1;
        const a = trendData.cur.slice(0, done).reduce((s, v) => s + v, 0);
        const b = trendData.prev.slice(0, done).reduce((s, v) => s + v, 0);
        if (done > 0 && b > 0) {
            const pct = Math.round((a - b) * 100 / b);
            const span = done === 1 ? trendData.labels[0] : trendData.labels[0] + '–' + trendData.labels[done - 1];
            sum.innerHTML = `${span} รวม <b>${fmt(a)}</b> visit, `
                + (pct === 0 ? 'เท่ากับ' : `${pct > 0 ? 'มากกว่า' : 'น้อยกว่า'} <b>${Math.abs(pct)}%</b> จาก`) + `ช่วงเดียวกันปีงบก่อน`;
        } else {
            sum.textContent = `ปีงบ ${FY} เริ่มเดือนนี้ ยังไม่มีเดือนที่จบแล้วให้เทียบ`;
        }
        document.getElementById('hx-trend-legend').innerHTML =
            `<span><i></i>ปีงบ ${FY}</span><span><i class="is-prev"></i>ปีงบ ${FY - 1}</span><span>จุดเส้นประ = ยังไม่จบเดือน</span>`;
        drawTrend();
    } catch (e) {
        console.error('loadTrend error:', e);
        if (!trendData) sum.textContent = 'โหลดข้อมูลแนวโน้มไม่สำเร็จ';
    }
}

function drawTrend() {
    if (!trendData) return;
    const svg = document.getElementById('hx-trend');
    const W = Math.max(svg.clientWidth, 300), H = svg.clientHeight || 260;
    const { cur, prev, labels } = trendData;
    const L = 52, R = 12, T = 10, B = 30;
    const max = Math.max(...cur, ...prev, 1);
    const step = Math.pow(10, Math.floor(Math.log10(max / 4)));
    const tick = [1, 2, 2.5, 5, 10].map(m => m * step).find(s => max / s <= 5) || step * 10;
    const top = Math.ceil(max / tick) * tick;
    const X = i => L + i * (W - L - R) / 11, Y = v => T + (1 - v / top) * (H - T - B);
    const line = a => a.map((v, i) => (i ? 'L' : 'M') + X(i).toFixed(1) + ',' + Y(v).toFixed(1)).join('');

    let s = '';
    for (let v = 0; v <= top; v += tick) {
        s += `<line class="gl" x1="${L}" x2="${W - R}" y1="${Y(v)}" y2="${Y(v)}"/><text x="${L - 8}" y="${Y(v) + 4}" text-anchor="end">${fmt(v)}</text>`;
    }
    // จอแคบ: ชื่อเดือนเว้นเดือน ไม่ให้ทับกัน (ชี้ที่แถบยังเห็นทุกเดือน)
    labels.forEach((m, i) => { if (W >= 520 || i % 2 === 0) s += `<text x="${X(i)}" y="${H - 8}" text-anchor="middle">${m}</text>`; });
    s += `<path class="ln-prev" d="${line(prev)}"/>`;
    if (cur.length) s += `<path class="ln-cur" pathLength="1" style="stroke-dasharray:1" d="${line(cur)}"/>`;
    cur.forEach((v, i) => { s += `<circle class="pt${i === cur.length - 1 ? ' is-partial' : ''}" cx="${X(i)}" cy="${Y(v)}" r="4"/>`; });
    // แถบโปร่งรายเดือน: ชี้แล้วเห็นตัวเลขทั้งสองปี
    const bw = (W - L - R) / 11;
    labels.forEach((m, i) => {
        const tip = `${m} ปีงบ ${FY}: ${cur[i] === undefined ? 'ยังไม่ถึง' : fmt(cur[i]) + ' visit' + (i === cur.length - 1 ? ' (นับถึงวันนี้)' : '')}\n${m} ปีงบ ${FY - 1}: ${fmt(prev[i] ?? 0)} visit`;
        s += `<rect class="hit" x="${X(i) - bw / 2}" y="${T}" width="${bw}" height="${H - T - B}" rx="8"><title>${tip}</title></rect>`;
    });
    svg.setAttribute('viewBox', `0 0 ${W} ${H}`);
    svg.innerHTML = s;
    enterOnce(svg.parentElement);
}
let trendResize;
window.addEventListener('resize', () => { clearTimeout(trendResize); trendResize = setTimeout(drawTrend, 150); });

loadTrend();
bbhPoll(loadTrend, 5 * 60 * 1000);   // API cache 5 นาที

/* ---------------- เตียงผู้ป่วยใน (api/bed_summary.php) ---------------- */
async function loadBedSummary() {
    try {
        const data = await (await fetch('api/bed_summary.php')).json();
        const occ = Number(data.occupancy);
        const level = occ >= 100 ? 'hot' : occ >= 85 ? 'warm' : 'ok';

        const ring = document.getElementById('hx-ring');
        ring.className = 'hx-ring is-' + level;
        document.getElementById('hx-ring-svg').innerHTML =
            `<circle class="rb" cx="68" cy="68" r="56" fill="none" stroke-width="12"/>`
            + `<circle class="rf" cx="68" cy="68" r="56" fill="none" stroke-width="12" pathLength="1" style="stroke-dasharray:${Math.min(occ, 100) / 100} 1"/>`
            + `<text x="68" y="70" text-anchor="middle" font-size="27" font-weight="700">${occ.toLocaleString('th-TH', { maximumFractionDigits: 1 })}%</text>`
            + `<text x="68" y="92" text-anchor="middle" font-size="12" opacity=".7">${fmt(data.wtotal)} / ${fmt(data.bedcount)} เตียง</text>`;
        enterOnce(ring);

        const chip = document.getElementById('hx-bed-chip');
        chip.className = 'chip is-' + level;
        chip.textContent = { hot: 'เกินจำนวนเตียง', warm: 'ใกล้เต็ม', ok: 'ปกติ' }[level];

        // ติดลบ = ผู้ป่วยเกินจำนวนเตียง: แสดงเป็น "เกินเตียง" แทนเลขติดลบ
        const over = data.wblank < 0;
        document.getElementById('wblank-label').textContent = over ? 'เกินเตียง' : 'เตียงว่าง';
        const wb = document.getElementById('wblank');
        wb.textContent = fmt(Math.abs(data.wblank)) + ' เตียง';
        wb.classList.toggle('is-bad', over);

        document.getElementById('admittoday').textContent = fmt(data.admittoday) + ' ราย';
        document.getElementById('dchtoday').textContent = fmt(data.dchtoday) + ' ราย';
        document.getElementById('bedcount').textContent = fmt(data.bedcount) + ' เตียง';
        ['uc', 'mo', 'ot'].forEach(k => { document.getElementById(k).textContent = fmt(data[k]) + ' ราย'; });

        markUpdated();
    } catch (e) {
        console.error('loadBedSummary error:', e);
    }
}

loadBedSummary();
bbhPoll(loadBedSummary, 60000);

/* ---------------- การ์ดหอผู้ป่วย (api/index_ward_bed.php) ---------------- */
// ไอคอนตามชื่อ Ward ชุดเดียวกับเมนู IPD ใน templates/sidebar.php (ลำดับสำคัญ: "หลังคลอด" ต้องมาก่อน "คลอด")
const WARD_ICONS = [
    [/พิเศษ/, 'bi-bedroom'],
    [/หลังคลอด|^pp/i, 'bi-public-lactation-room'],
    [/ทารก|snb/i, 'bi-baby'],
    [/เด็ก/, 'bi-child'],
    [/stroke/i, 'bi-brain'],
    [/icu/i, 'bi-ecg-monitor'],
    [/ศัลย/, 'bi-surgical-scalpel'],
    [/หญิง/, 'bi-doctor'],
    [/ชาย/, 'bi-doctor'],
    [/คลอด|^lr/i, 'bi-pregnant-person'],
    [/home/i, 'bi-home']
];

function wardIcon(name) {
    const hit = WARD_ICONS.find(([re]) => re.test(String(name || '').trim()));
    return hit ? hit[1] : 'bi-hospital-bed';
}

async function loadWardBed() {
    try {
        const data = await (await fetch('api/index_ward_bed.php')).json();

        // เรียงหอที่แน่นสุดก่อน · Home Ward (23) ผู้ป่วยนอนที่บ้าน ไม่นับรวมยอดเตียงทั้งหมด (api/bed_summary.php) — อยู่ท้ายสุด
        const HOME_WARD = '23';
        data.sort((a, b) => (a.ward === HOME_WARD) - (b.ward === HOME_WARD) || b.occupancy_rate - a.occupancy_rate);

        const box = document.getElementById('ward-container');
        const lv = { hot: 0, warm: 0, ok: 0 };
        box.innerHTML = data.map(w => {
            const rate = parseFloat(w.occupancy_rate) || 0;
            // เกณฑ์เดียวกับการ์ดเดิม: ≥100 เต็ม/เกิน, ≥75 ใกล้เต็ม, ≥50 ปานกลาง — สีคู่กับข้อความเสมอ
            const level = rate >= 100 ? 'hot' : rate >= 75 ? 'warm' : 'ok';
            const label = rate >= 100 ? 'เต็ม/เกิน' : rate >= 75 ? 'ใกล้เต็ม' : rate >= 50 ? 'ปานกลาง' : 'ว่าง';
            const admit = parseInt(w.admitnow, 10), beds = parseInt(w.bedcount, 10);
            const free = admit >= beds ? (admit > beds ? `เกิน ${fmt(admit - beds)} เตียง` : 'เต็มทุกเตียง') : `ว่าง ${fmt(w.available_bed)} เตียง`;
            if (w.ward !== HOME_WARD) lv[level]++;
            // .ward-card + .ward-name (ชื่อตรงกับ API) — login.js ใช้หา Ward แล้วเปิดหน้ารายละเอียด
            return `<div class="ward-card is-${level}" tabindex="0" role="link" aria-label="${esc(w.name)} ครองเตียง ${rate}% ${label} ดูรายละเอียดหอผู้ป่วย">
                <div class="wc-hd"><i class="bi ${wardIcon(w.name)}" aria-hidden="true"></i><div class="ward-name" title="${esc(w.name)}">${esc(w.name)}</div></div>
                <div class="wc-ln"><span class="wc-pc">${rate}%</span><span class="wc-st">${label}</span></div>
                <div class="hx-tr" aria-hidden="true"><i style="--w:${Math.min(rate, 100) / 100}"></i></div>
                <div class="wc-ft"><span>ผู้ป่วย <b>${fmt(admit)}</b> / ${fmt(beds)} เตียง</span><b class="wc-free">${free}</b></div>
                ${w.ward === HOME_WARD ? '<small>ผู้ป่วยอยู่ที่บ้าน ไม่นับรวมยอดเตียง</small>' : ''}
            </div>`;
        }).join('');
        // สรุปจำนวนหอตามสถานะ (ไม่นับ Home Ward) — สีคู่ข้อความ
        document.getElementById('ward-sum').innerHTML =
            [['hot', 'เต็ม/เกิน'], ['warm', 'ใกล้เต็ม'], ['ok', 'ยังว่าง']]
                .map(([k, t]) => `<span class="chip is-${k}">${t} ${lv[k]} หอ</span>`).join('');
        enterOnce(box);

        markUpdated();
    } catch (e) {
        console.error('loadWardBed error:', e);
    }
}

loadWardBed();
bbhPoll(loadWardBed, 60000);

/* ---------------- บริการอื่น ๆ วันนี้ (api/service_summary_today.php) ---------------- */
async function loadServiceSummary() {
    try {
        const data = await (await fetch('api/service_summary_today.php')).json();
        const box = document.getElementById('service-container');

        // key ของ API (<บริการ>_total) = id ตัวเลขในการ์ด · เรียงมากไปน้อย (stable: เท่ากันคงลำดับเดิม) · 0 = สีเทา
        const rows = [...box.children].map(el => [el, Number(data[el.querySelector('.service-total').id]) || 0]);
        const max = Math.max(1, ...rows.map(r => r[1]));
        rows.sort((a, b) => b[1] - a[1]).forEach(([el, n]) => {
            el.querySelector('.service-total').textContent = fmt(n);
            el.querySelector('.hx-tr > i').style.setProperty('--w', (n / max).toFixed(4));   // แถบ = สัดส่วนเทียบบริการที่มากที่สุด
            el.classList.toggle('is-zero', n === 0);
            box.appendChild(el);
        });
        enterOnce(box);

        markUpdated();
    } catch (e) {
        console.error('loadServiceSummary error:', e);
    }
}

loadServiceSummary();
bbhPoll(loadServiceSummary, 60000);

/* ---------------- รายรับตามสิทธิเดือนนี้ (api/finance_income_pttype.php · guest ได้ 401) ---------------- */
// หน่วยเดียวทั้งการ์ด (ล้านบาท ทศนิยม 2) ให้เทียบแถวกันได้ทันที
const baht = n => (n / 1e6).toLocaleString('th-TH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ล้าน';
const thDay = (iso, withYear) => new Date(iso + 'T00:00:00').toLocaleDateString('th-TH',
    withYear ? { day: 'numeric', month: 'short', year: 'numeric' } : { day: 'numeric', month: 'short' });

// เทียบเดือนก่อนช่วงวันเดียวกัน: ลูกศร + ข้อความ (ไม่ใช้สีอย่างเดียว) · ต่างไม่ถึง 1% = ใกล้เคียง
function finDelta(cur, prev) {
    if (!prev) return '<span class="fin-d is-flat">ไม่มีข้อมูลเดือนก่อน</span>';
    const p = (cur - prev) / prev * 100;
    if (Math.abs(p) < 1) return '<span class="fin-d is-flat"><i class="fa-solid fa-equals" aria-hidden="true"></i>ใกล้เคียง</span>';
    const up = p > 0;
    return `<span class="fin-d ${up ? 'is-up' : 'is-down'}"><i class="fa-solid fa-arrow-${up ? 'up' : 'down'}" aria-hidden="true"></i>${up ? 'มากกว่า' : 'น้อยกว่า'} ${Math.abs(p).toFixed(1)}%</span>`;
}

async function loadFinance() {
    const box = document.getElementById('hx-fin');
    try {
        const res = await fetch('api/finance_income_pttype.php');
        if (res.status === 401) {
            box.innerHTML = '<p class="fin-msg"><i class="fa-solid fa-lock" aria-hidden="true"></i> เข้าสู่ระบบ Provider ID เพื่อดูรายรับ</p>';
            return;
        }
        const d = await res.json();
        if (!res.ok || d.error) throw new Error(d.error || res.status);

        const t = d.total;
        document.getElementById('hx-fin-sub').textContent =
            `${thDay(d.cur.start)}–${thDay(d.cur.end, true)} เทียบ ${thDay(d.prev.start)}–${thDay(d.prev.same_end, true)}`;

        const rows = d.groups.filter(g => g.cur.income || g.prev_same.income);
        const max = Math.max(1, ...rows.map(g => Math.max(g.cur.income, g.prev_same.income)));

        box.innerHTML = `
            <div class="fin-sum">
                <span class="fin-lbl">รวมทุกสิทธิ</span>
                <div class="fin-big">${baht(t.cur.income)}<small>บาท</small></div>
                ${finDelta(t.cur.income, t.prev_same.income)}
                <p class="fin-cmp">เดือนก่อน ${baht(t.prev_same.income)} (ทั้งเดือน ${baht(t.prev_full.income)})</p>
                <div class="fin-split">
                    <div class="hx-mini" title="ผู้ป่วยนอกทุกแผนก ${fmt(t.cur.visits)} visit"><span>ผู้ป่วยนอก</span><b>${baht(t.cur.opd)}</b></div>
                    <div class="hx-mini" title="ผู้ป่วยในจำหน่าย ${fmt(t.cur.discharges)} ราย"><span>ผู้ป่วยใน</span><b>${baht(t.cur.ipd)}</b></div>
                    <div class="hx-mini" title="ส่วนที่ผู้ป่วยจ่ายเอง ทุกสิทธิ"><span>ผู้ป่วยจ่ายเอง</span><b>${baht(t.cur.paid)}</b></div>
                </div>
            </div>
            <ul class="fin-list" aria-label="รายรับแยกตามสิทธิ เรียงมากไปน้อย">
                ${rows.map(g => `
                <li class="fin-row">
                    <span class="fin-name">${esc(g.label)}</span>
                    <span class="fin-amt">${baht(g.cur.income)}</span>
                    ${finDelta(g.cur.income, g.prev_same.income)}
                    <div class="fin-bar" title="เดือนนี้ ${baht(g.cur.income)} · เดือนก่อนช่วงเดียวกัน ${baht(g.prev_same.income)} บาท">
                        <div class="hx-tr"><i style="--w:${(g.cur.income / max).toFixed(4)}"></i></div>
                        <b class="fin-prev" style="--x:${(g.prev_same.income / max).toFixed(4)}" aria-hidden="true"></b>
                    </div>
                </li>`).join('')}
            </ul>
            <p class="fin-note">ขีดตั้ง = เดือนก่อนช่วงวันเดียวกัน · ยอดเรียกเก็บ ไม่ใช่เงินที่ได้รับจริง (รายละเอียดที่ปุ่ม i)</p>`;
        enterOnce(box);
        markUpdated();
    } catch (e) {
        console.error('loadFinance error:', e);
        box.innerHTML = '<p class="fin-msg">โหลดรายรับไม่สำเร็จ</p>';
    }
}

loadFinance();
bbhPoll(loadFinance, 5 * 60 * 1000);   // API cache 5 นาที
</script>

<?php

include 'templates/footer.php';
