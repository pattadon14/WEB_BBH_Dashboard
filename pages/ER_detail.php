<?php

require_once __DIR__ . '/../auth/provider.php';
provider_require_login_page();   // ยังไม่ login → กลับหน้าแรก

include '../templates/header.php';
include '../templates/navbar.php';
include '../templates/sidebar.php';

?>
<!-- ตัวเลือกวันที่ (opd_quality.css) และหน้าต่างรายชื่อ (opd_tabs.css) ใช้ชุดเดียวกับหน้า OPD -->
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/opd_tabs.css?v=<?= filemtime(__DIR__ . '/../assets/css/opd_tabs.css') ?>">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/opd_quality.css?v=<?= filemtime(__DIR__ . '/../assets/css/opd_quality.css') ?>">
<!-- หน้า ER: ระบบสีแดง–ขาว ทำงานใต้ .er-theme -->
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/er_theme.css?v=<?= filemtime(__DIR__ . '/../assets/css/er_theme.css') ?>">

<div class="content-wrapper er-theme">

    <section class="content">

        <div class="er-page">

            <!-- สรุปรายวัน (api/er_today_summary.php · er_d2d.php · er_los.php · assets/js/er_summary.js)
                 ทุกตัวเลขที่มี data-drill ต้องอยู่ใน #er-summary (bbhDrill.bind) · หัวข้อมีเลขลำดับ (.er-sec-no) แยกส่วนให้ชัด -->
            <div id="er-summary">

                <!-- แถบหัวแดง: ชื่อหน้า + เทียบสัปดาห์ก่อน + ตัวเลือกวันที่ -->
                <header class="er-mast" id="er-summary-card">
                    <div class="er-mast-top">
                        <div class="er-mast-heading">
                            <h1 class="er-mast-title" id="er-day-title">ผู้มารับบริการ ER วันนี้</h1>
                            <p class="er-mast-sub" id="er-compare">—</p>
                        </div>

                        <div class="oq-controls opd-day-filter er-mast-controls">
                            <div class="oq-seg" role="group" aria-label="เลือกวันด่วน">
                                <button type="button" data-er-day="0" aria-pressed="true">วันนี้</button>
                                <button type="button" data-er-day="1" aria-pressed="false">เมื่อวาน</button>
                            </div>
                            <div class="oq-range">
                                <i class="fa-solid fa-calendar-day" aria-hidden="true"></i>
                                <label class="oq-date">
                                    <span id="er-date-text">—</span>
                                    <input type="date" id="er-date" aria-label="เลือกวันที่">
                                </label>
                            </div>
                        </div>
                    </div>
                </header>

                <!-- 1 ภาพรวม: ช่องสีทึบ (โทนแดง–ส้ม) · สถานะตอนนี้แสดงเฉพาะเมื่อดูวันนี้ -->
                <section class="er-panel" aria-labelledby="er-sec1">
                    <header class="er-sec-head">
                        <span class="er-sec-no" aria-hidden="true">1</span>
                        <div>
                            <h2 class="er-h2" id="er-sec1">ภาพรวม ER</h2>
                            <p class="er-sec-sub">ยอดของวันที่เลือก <span id="er-now-at"></span></p>
                        </div>
                    </header>
                    <div class="er-kpis">
                        <div class="er-kpi er-k1" data-drill="all">
                            <span class="er-kpi-label"><i class="fa-solid fa-hospital-user" aria-hidden="true"></i>ผู้มารับบริการ</span>
                            <b class="er-kpi-num" id="er-total">—</b>
                            <span class="er-kpi-sub">ครั้ง จาก <b id="er-people">—</b> คน</span>
                        </div>
                        <div class="er-kpi er-k2" data-drill="ems" title="ovstist 09 FR / 10 BLS / 11 ALS">
                            <span class="er-kpi-label"><i class="fa-solid fa-truck-medical" aria-hidden="true"></i>EMS นำส่ง</span>
                            <b class="er-kpi-num" id="er-ems">—</b>
                            <span class="er-kpi-sub">ราย</span>
                        </div>
                        <div class="er-now" id="er-now" hidden>
                            <div class="er-kpi er-k3" data-drill="now_in">
                                <span class="er-kpi-label"><i class="fa-solid fa-bed-pulse" aria-hidden="true"></i>อยู่ใน ER ตอนนี้</span>
                                <b class="er-kpi-num" id="er-now-in">—</b>
                                <span class="er-kpi-sub">ราย</span>
                            </div>
                            <div class="er-kpi er-k4" data-drill="now_l12">
                                <span class="er-kpi-label"><i class="fa-solid fa-heart-pulse" aria-hidden="true"></i>ระดับ 1–2 ตอนนี้</span>
                                <b class="er-kpi-num" id="er-now-l12">—</b>
                                <span class="er-kpi-sub">ราย</span>
                            </div>
                            <div class="er-kpi er-k5" data-drill="now_wait" title="ยังไม่มีบันทึกการพบแพทย์ (กดเริ่มตรวจ / สั่ง Lab / X-ray / ยา / HPI)">
                                <span class="er-kpi-label"><i class="fa-solid fa-user-doctor" aria-hidden="true"></i>ยังไม่พบแพทย์</span>
                                <b class="er-kpi-num" id="er-now-wait">—</b>
                                <span class="er-kpi-sub">ราย</span>
                            </div>
                            <div class="er-kpi er-k6" data-drill="now_over">
                                <span class="er-kpi-label"><i class="fa-solid fa-hourglass-end" aria-hidden="true"></i>อยู่เกิน 4 ชม.</span>
                                <b class="er-kpi-num" id="er-now-over">—</b>
                                <span class="er-kpi-sub">ราย</span>
                            </div>
                            <div class="er-kpi er-k7">
                                <span class="er-kpi-label"><i class="fa-solid fa-clock" aria-hidden="true"></i>อยู่นานสุด</span>
                                <b class="er-kpi-num" id="er-now-long">—</b>
                                <span class="er-kpi-sub">ชม.:นาที</span>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- 2–3 เวลาให้บริการ: Door-to-Doctor (config/er.php) + ระยะเวลาอยู่ใน ER · หัวตารางแดง -->
                <div class="er-split">
                    <section class="er-panel" aria-labelledby="er-d2d-title">
                        <header class="er-sec-head">
                            <span class="er-sec-no" aria-hidden="true">2</span>
                            <div>
                                <h2 class="er-h2" id="er-d2d-title">Door-to-Doctor</h2>
                                <p class="er-sec-sub">เวลาเข้า ER ถึงพบแพทย์ แยกตามระดับความเร่งด่วน</p>
                            </div>
                        </header>
                        <div class="er-rows-head" aria-hidden="true"><span>ระดับ / เป้าหมาย</span><span>เวลาเฉลี่ย</span><span>ทันเป้า</span></div>
                        <div class="er-rows" id="er-d2d-grid" aria-live="polite"></div>
                        <p class="er-note" id="er-d2d-note">กำลังโหลดข้อมูล…</p>
                    </section>

                    <section class="er-panel" aria-labelledby="er-los-title">
                        <header class="er-sec-head">
                            <span class="er-sec-no" aria-hidden="true">3</span>
                            <div>
                                <h2 class="er-h2" id="er-los-title">Length of Stay</h2>
                                <p class="er-sec-sub">ระยะเวลาอยู่ใน ER และเวลาถึง Admit / Refer</p>
                            </div>
                        </header>
                        <div class="er-rows-head" aria-hidden="true"><span>ช่วงเวลา / เกณฑ์</span><span>เวลาเฉลี่ย</span><span>รอเกินเกณฑ์</span></div>
                        <div class="er-rows" id="er-los-grid"></div>
                        <p class="er-note" id="er-los-note">กำลังโหลดข้อมูล…</p>
                    </section>
                </div>

                <!-- 4–7 ผู้ป่วยของวันที่เลือก: แต่ละหมวดเป็นการ์ดของตัวเอง แต่ละแถวคลิกดูรายชื่อ -->
                <div class="er-cols">

                    <section class="er-panel er-col" aria-labelledby="er-sec4">
                        <header class="er-sec-head">
                            <span class="er-sec-no" aria-hidden="true">4</span>
                            <div><h2 class="er-h2" id="er-sec4">ระดับความเร่งด่วน</h2><p class="er-sec-sub">Triage และการส่งต่อ</p></div>
                        </header>
                        <div class="er-stat er-stat-lead" data-drill="l12">
                            <span class="er-stat-label"><span class="er-ico" aria-hidden="true"><i class="fa-solid fa-triangle-exclamation"></i></span>วิกฤต / ฉุกเฉิน <small>ระดับ 1–2 ต้องพบแพทย์ภายใน 10 นาที</small></span>
                            <span class="er-stat-value"><b id="er-l12">—</b> ครั้ง</span>
                        </div>
                        <div class="er-mix-bar" role="img" id="er-level-bar" aria-label="สัดส่วนระดับความเร่งด่วน">
                            <span id="er-bar-1" class="er-tri er-tri-1"></span>
                            <span id="er-bar-2" class="er-tri er-tri-2"></span>
                            <span id="er-bar-3" class="er-tri er-tri-3"></span>
                            <span id="er-bar-4" class="er-tri er-tri-4"></span>
                            <span id="er-bar-5" class="er-tri er-tri-5"></span>
                            <span id="er-bar-6" class="er-tri er-tri-6"></span>
                            <span id="er-bar-0" class="er-tri er-tri-0"></span>
                        </div>
                        <div class="er-mix-legend">
                            <span><i class="er-dot er-tri er-tri-1" aria-hidden="true"></i>ระดับ 1 <b id="er-l1">–</b></span>
                            <span><i class="er-dot er-tri er-tri-2" aria-hidden="true"></i>ระดับ 2 <b id="er-l2">–</b></span>
                            <span><i class="er-dot er-tri er-tri-3" aria-hidden="true"></i>ระดับ 3 <b id="er-l3">–</b></span>
                            <span><i class="er-dot er-tri er-tri-4" aria-hidden="true"></i>ระดับ 4 <b id="er-l4">–</b></span>
                            <span><i class="er-dot er-tri er-tri-5" aria-hidden="true"></i>ระดับ 5 <b id="er-l5">–</b></span>
                            <span id="er-lg-6"><i class="er-dot er-tri er-tri-6" aria-hidden="true"></i>Black <b id="er-l6">–</b></span>
                            <span id="er-lg-0"><i class="er-dot er-tri er-tri-0" aria-hidden="true"></i>ไม่ระบุ <b id="er-l0">–</b></span>
                        </div>

                        <!-- Refer in = วิธีมา 04 หรือมีใบ referin · Refer รวม = in หรือ out (config/er.php) -->
                        <h3 class="er-h3 er-h3-gap">การส่งต่อ (Refer)</h3>
                        <div class="er-stat" data-drill="refer_in" title="Refer in: รับส่งต่อจากสถานพยาบาลอื่น">
                            <span class="er-stat-label"><span class="er-ico" aria-hidden="true"><i class="fa-solid fa-arrow-right-to-bracket"></i></span>Refer in</span>
                            <span class="er-stat-value"><b id="er-refer-in">—</b> ราย</span>
                        </div>
                        <div class="er-stat" data-drill="refer_all" title="Refer ทั้งหมด: รับเข้า (in) + ส่งออก (out) · visit ที่มีทั้งสองนับครั้งเดียว">
                            <span class="er-stat-label"><span class="er-ico" aria-hidden="true"><i class="fa-solid fa-right-left"></i></span>Refer รวม</span>
                            <span class="er-stat-value"><b id="er-refer">—</b> ราย</span>
                        </div>
                    </section>

                    <section class="er-panel er-col" aria-labelledby="er-sec5">
                        <header class="er-sec-head">
                            <span class="er-sec-no" aria-hidden="true">5</span>
                            <div><h2 class="er-h2" id="er-sec5">ประเภทผู้ป่วย</h2><p class="er-sec-sub">ตามประเภทที่ ER ลงทะเบียน</p></div>
                        </header>
                        <div class="er-stat" data-drill="pt_emergency">
                            <span class="er-stat-label"><span class="er-ico" aria-hidden="true"><i class="fa-solid fa-heart-pulse"></i></span>ฉุกเฉิน</span>
                            <span class="er-stat-value"><b id="er-pt-emergency">—</b> ครั้ง</span>
                        </div>
                        <div class="er-stat" data-drill="pt_accident">
                            <span class="er-stat-label"><span class="er-ico" aria-hidden="true"><i class="fa-solid fa-car-burst"></i></span>อุบัติเหตุ</span>
                            <span class="er-stat-value"><b id="er-pt-accident">—</b> ครั้ง</span>
                        </div>
                        <div class="er-stat" data-drill="pt_general">
                            <span class="er-stat-label"><span class="er-ico" aria-hidden="true"><i class="fa-solid fa-stethoscope"></i></span>ตรวจโรคทั่วไป</span>
                            <span class="er-stat-value"><b id="er-pt-general">—</b> ครั้ง</span>
                        </div>
                        <div class="er-stat" data-drill="pt_service" title="ทำแผล ฉีดยา ใบรับรองแพทย์ และห้องทำแผลเวรเช้า">
                            <span class="er-stat-label"><span class="er-ico" aria-hidden="true"><i class="fa-solid fa-syringe"></i></span>รับบริการอื่น</span>
                            <span class="er-stat-value"><b id="er-pt-service">—</b> ครั้ง</span>
                        </div>
                    </section>

                    <section class="er-panel er-col" aria-labelledby="er-sec6">
                        <header class="er-sec-head">
                            <span class="er-sec-no" aria-hidden="true">6</span>
                            <div><h2 class="er-h2" id="er-sec6">แยกตามเวร</h2><p class="er-sec-sub">ตามเวลาเข้า ER</p></div>
                        </header>
                        <div class="er-stat" data-drill="shift_night">
                            <span class="er-stat-label"><span class="er-ico" aria-hidden="true"><i class="fa-solid fa-moon"></i></span>เวรดึก <small>00:00 – 07:59 น.</small></span>
                            <span class="er-stat-value"><b id="er-shift-night">—</b> ครั้ง</span>
                        </div>
                        <div class="er-stat" data-drill="shift_morning">
                            <span class="er-stat-label"><span class="er-ico" aria-hidden="true"><i class="fa-solid fa-sun"></i></span>เวรเช้า <small>08:00 – 15:59 น.</small></span>
                            <span class="er-stat-value"><b id="er-shift-morning">—</b> ครั้ง</span>
                        </div>
                        <div class="er-stat" data-drill="shift_evening">
                            <span class="er-stat-label"><span class="er-ico" aria-hidden="true"><i class="fa-solid fa-cloud-sun"></i></span>เวรบ่าย <small>16:00 – 23:59 น.</small></span>
                            <span class="er-stat-value"><b id="er-shift-evening">—</b> ครั้ง</span>
                        </div>
                    </section>

                    <!-- 6 แถวรวมได้ยอดทั้งหมด · Admit/Refer out นับจาก ipt / referout จุด ER ด้วย (config/er.php) -->
                    <section class="er-panel er-col" aria-labelledby="er-sec7">
                        <header class="er-sec-head">
                            <span class="er-sec-no" aria-hidden="true">7</span>
                            <div><h2 class="er-h2" id="er-sec7">ผลการจำหน่าย</h2><p class="er-sec-sub">6 แถวรวมได้ยอดทั้งหมด</p></div>
                        </header>
                        <div class="er-stat" data-drill="home">
                            <span class="er-stat-label"><span class="er-ico" aria-hidden="true"><i class="fa-solid fa-house"></i></span>กลับบ้าน</span>
                            <span class="er-stat-value"><b id="er-home">—</b> ครั้ง</span>
                        </div>
                        <div class="er-stat" data-drill="admit">
                            <span class="er-stat-label"><span class="er-ico" aria-hidden="true"><i class="fa-solid fa-bed-pulse"></i></span>Admit</span>
                            <span class="er-stat-value"><b id="er-admit">—</b> ครั้ง</span>
                        </div>
                        <div class="er-stat" data-drill="refer" title="Refer out: ส่งต่อไปสถานพยาบาลอื่น">
                            <span class="er-stat-label"><span class="er-ico" aria-hidden="true"><i class="fa-solid fa-truck-medical"></i></span>Refer out</span>
                            <span class="er-stat-value"><b id="er-refer-out">—</b> ครั้ง</span>
                        </div>
                        <div class="er-stat" data-drill="gone" title="ไม่รอตรวจ / หนีกลับ / ปฏิเสธการรักษา">
                            <span class="er-stat-label"><span class="er-ico" aria-hidden="true"><i class="fa-solid fa-person-walking-arrow-right"></i></span>ไม่รอตรวจ / หนีกลับ</span>
                            <span class="er-stat-value"><b id="er-gone">—</b> ครั้ง</span>
                        </div>
                        <div class="er-stat" data-drill="no_dch" title="ยังอยู่ใน ER หรือยังไม่ได้บันทึกผลการจำหน่าย">
                            <span class="er-stat-label"><span class="er-ico" aria-hidden="true"><i class="fa-solid fa-user-clock"></i></span>ยังไม่ลงผลจำหน่าย</span>
                            <span class="er-stat-value"><b id="er-no-dch">—</b> ครั้ง</span>
                        </div>
                        <div class="er-stat" data-drill="other_dch" title="สังเกตอาการ / เสียชีวิต">
                            <span class="er-stat-label"><span class="er-ico" aria-hidden="true"><i class="fa-solid fa-ellipsis"></i></span>อื่น ๆ</span>
                            <span class="er-stat-value"><b id="er-other-dch">—</b> ครั้ง</span>
                        </div>
                    </section>

                </div>
            </div>

            <!-- สถิติตามช่วงเวลา: ปุ่มลัด + ปีงบ + ช่วงวันที่ (assets/js/period_filter.js เติมปุ่มและช่องวันที่) -->
            <section class="er-panel er-stats" aria-labelledby="er-stats-title">
                <div class="er-stats-head">
                    <header class="er-sec-head">
                        <span class="er-sec-no" aria-hidden="true">8</span>
                        <div>
                            <h2 class="er-h2" id="er-stats-title">สถิติงานอุบัติเหตุและฉุกเฉิน</h2>
                            <div id="budget-date-text" class="er-sec-sub"></div>
                        </div>
                    </header>

                    <div id="period-filter" class="bbh-period">
                        <div class="budget-select-wrapper">
                            <select id="budget-year" class="form-control budget-select" aria-label="ปีงบประมาณ">
                                <?php
                                $currentYear = date('Y') + 543 + (date('n') >= 10 ? 1 : 0);
                                for ($y = $currentYear; $y >= 2565; $y--) {
                                    echo "<option value='$y'>ปีงบประมาณ $y</option>";
                                }
                                ?>
                            </select>
                            <i class="fas fa-chevron-down budget-select-icon" aria-hidden="true"></i>
                        </div>
                    </div>
                </div>

                <!-- แนวโน้มเต็มความกว้าง → 3 กราฟสัดส่วน → การส่งต่อ → 10 อันดับโรค · คลิกแท่ง/แถว = รายชื่อ (ER_detail.js erPeriodList) -->
                <p class="er-sec-sub er-refer-hint"><i class="fa-solid fa-hand-pointer" aria-hidden="true"></i> คลิกแท่งกราฟ แถวการส่งต่อ หรือแถวในตาราง เพื่อดูรายชื่อผู้ป่วยของช่วงเวลาที่เลือก</p>
                <div class="er-charts">
                    <figure class="er-chart er-chart-wide">
                        <figcaption class="er-h3"><span class="er-ico" aria-hidden="true"><i class="fa-solid fa-chart-column"></i></span>จำนวนผู้รับบริการ ER (แยกผู้ป่วยอุบัติเหตุ)</figcaption>
                        <div id="er-chart" style="height: 360px;"></div>
                    </figure>
                    <figure class="er-chart er-chart-third">
                        <figcaption class="er-h3"><span class="er-ico" aria-hidden="true"><i class="fa-solid fa-triangle-exclamation"></i></span>ระดับความเร่งด่วน (Triage)</figcaption>
                        <div id="er-triage-chart" style="height: 360px;"></div>
                    </figure>
                    <figure class="er-chart er-chart-third">
                        <figcaption class="er-h3"><span class="er-ico" aria-hidden="true"><i class="fa-solid fa-chart-bar"></i></span>ประเภทผู้ป่วย</figcaption>
                        <div id="er-pie-chart" style="height: 360px;"></div>
                    </figure>
                    <figure class="er-chart er-chart-third">
                        <figcaption class="er-h3"><span class="er-ico" aria-hidden="true"><i class="fa-solid fa-clock"></i></span>แยกตามเวร (เวลาเข้า ER)</figcaption>
                        <div id="er-timeslot-chart" style="height: 360px;"></div>
                    </figure>
                </div>

                <!-- การส่งต่อตามช่วงเวลา (api/er_refer.php → loadRefer ใน ER_detail.js) -->
                <h3 class="er-h3 er-h3-gap" id="er-refer-title"><span class="er-ico" aria-hidden="true"><i class="fa-solid fa-truck-medical"></i></span>การส่งต่อผู้ป่วย (Refer)</h3>
                <!-- 3 กล่อง: ส่งต่อออกจาก ER · Admit แล้วส่งต่อจากหอผู้ป่วย · รับส่งต่อเข้า ER (loadRefer สร้าง) -->
                <div class="er-refer" id="er-refer-panel" aria-labelledby="er-refer-title">
                    <p class="er-note"><i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i> กำลังโหลดข้อมูล…</p>
                </div>

                <h3 class="er-h3 er-h3-gap" id="er-top10-title"><span class="er-ico" aria-hidden="true"><i class="fa-solid fa-notes-medical"></i></span>10 อันดับโรค/การวินิจฉัยผู้ป่วย ER</h3>
                <div class="table-responsive">
                    <table class="table er-table table-ipd-top10" aria-labelledby="er-top10-title">
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
                        <tbody id="er-top10-tbody">
                            <tr>
                                <td colspan="6" class="text-center er-note py-4">
                                    <i class="fa-solid fa-spinner fa-spin me-2" aria-hidden="true"></i>
                                    กำลังโหลดข้อมูล…
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

        </div>

    </section>

</div>

<!-- HIGHCHART -->
<script src="<?= BASE_URL ?>assets/lib/highcharts/13.1.1/highcharts.js"></script>
<script>window.BBH_CURRENT_FISCAL_YEAR = <?= $currentYear ?>;</script>
<script src="<?= BASE_URL ?>assets/js/period_filter.js?v=<?= filemtime(__DIR__ . '/../assets/js/period_filter.js') ?>"></script>
<script src="<?= BASE_URL ?>assets/js/ER_detail.js?v=<?= filemtime(__DIR__ . '/../assets/js/ER_detail.js') ?>"></script>
<script src="<?= BASE_URL ?>assets/js/bbh_drill.js?v=<?= filemtime(__DIR__ . '/../assets/js/bbh_drill.js') ?>"></script>
<script src="<?= BASE_URL ?>assets/js/er_summary.js?v=<?= filemtime(__DIR__ . '/../assets/js/er_summary.js') ?>"></script>

<?php include '../templates/footer.php'; ?>
