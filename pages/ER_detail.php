<?php

require_once __DIR__ . '/../auth/provider.php';
provider_require_login_page();   // ยังไม่ login → กลับหน้าแรก

include '../templates/header.php';
include '../templates/navbar.php';
include '../templates/sidebar.php';

?>
<!-- ตัวเลือกวันที่ในการ์ดสรุป (ปุ่มวันนี้/เมื่อวาน + กล่องวันที่) ใช้ชุดเดียวกับหน้า OPD -->
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/opd_tabs.css?v=<?= filemtime(__DIR__ . '/../assets/css/opd_tabs.css') ?>">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/opd_quality.css?v=<?= filemtime(__DIR__ . '/../assets/css/opd_quality.css') ?>">
<!-- ธีมแดง–ขาวของหน้า ER (ทำงานใต้ .er-theme) -->
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/er_theme.css?v=<?= filemtime(__DIR__ . '/../assets/css/er_theme.css') ?>">

<div class="content-wrapper er-theme">

    <section class="content pt-3">

        <div class="container-fluid">

            <!-- สรุปรายวัน (api/er_today_summary.php · assets/js/er_summary.js) — รูปแบบเดียวกับการ์ดสรุปหน้า OPD -->
            <div id="er-summary" class="mb-3">
                <div class="card shadow-sm opd-summary-horizontal" id="er-summary-card">
                    <div class="card-body">

                        <div class="opd-summary-main">
                            <div class="opd-summary-main-icon">
                                <i class="fa-solid fa-truck-medical"></i>
                            </div>

                            <div class="opd-summary-main-info">
                                <div class="opd-summary-title" id="er-day-title">ผู้มารับบริการ ER วันนี้</div>
                                <div class="opd-summary-total-label">
                                    จำนวนผู้มารับบริการ <span id="er-people">—</span> คน
                                </div>
                                <div class="opd-summary-compare" id="er-compare">—</div>
                            </div>

                            <div class="oq-controls opd-day-filter">
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

                            <div class="opd-summary-main-total">
                                <div class="opd-summary-main-number" data-drill="all">
                                    <span id="er-total">—</span>
                                    <small>ครั้ง</small>
                                </div>
                                <div class="opd-summary-newold">
                                    <span class="newpt" data-drill="ems" title="ovstist 09 FR / 10 BLS / 11 ALS">EMS นำส่ง <strong id="er-ems">—</strong> ราย</span>
                                </div>
                            </div>
                        </div>

                        <div class="opd-summary-grid">

                            <div class="opd-summary-section">
                                <div class="summary-section-title">
                                    <i class="fa-solid fa-triangle-exclamation"></i>
                                    ระดับความเร่งด่วน
                                </div>
                                <div class="horizontal-summary-item walkin-card er-crit-card" data-drill="l12">
                                    <div class="walkin-head">
                                        <span class="opd-ico" aria-hidden="true"><i class="fa-solid fa-heart-pulse"></i></span>
                                        <div>
                                            <div class="summary-title">วิกฤต / ฉุกเฉิน</div>
                                            <div class="todaysummary-sub">ระดับ 1–2 ต้องพบแพทย์ภายใน 10 นาที</div>
                                        </div>
                                    </div>
                                    <div class="summary-number">
                                        <span id="er-l12">—</span>
                                        <span class="summary-unit">ครั้ง</span>
                                    </div>
                                    <div class="visit-mix">
                                        <div class="visit-mix-bar" role="img" id="er-level-bar" aria-label="สัดส่วนระดับความเร่งด่วน">
                                            <span id="er-bar-1" class="er-tri er-tri-1"></span>
                                            <span id="er-bar-2" class="er-tri er-tri-2"></span>
                                            <span id="er-bar-3" class="er-tri er-tri-3"></span>
                                            <span id="er-bar-4" class="er-tri er-tri-4"></span>
                                            <span id="er-bar-5" class="er-tri er-tri-5"></span>
                                            <span id="er-bar-6" class="er-tri er-tri-6"></span>
                                            <span id="er-bar-0" class="er-tri er-tri-0"></span>
                                        </div>
                                        <div class="visit-mix-legend er-level-legend">
                                            <span><i class="mix-dot er-tri er-tri-1" aria-hidden="true"></i>ระดับ 1 <b id="er-l1">–</b></span>
                                            <span><i class="mix-dot er-tri er-tri-2" aria-hidden="true"></i>ระดับ 2 <b id="er-l2">–</b></span>
                                            <span><i class="mix-dot er-tri er-tri-3" aria-hidden="true"></i>ระดับ 3 <b id="er-l3">–</b></span>
                                            <span><i class="mix-dot er-tri er-tri-4" aria-hidden="true"></i>ระดับ 4 <b id="er-l4">–</b></span>
                                            <span><i class="mix-dot er-tri er-tri-5" aria-hidden="true"></i>ระดับ 5 <b id="er-l5">–</b></span>
                                            <span id="er-lg-6"><i class="mix-dot er-tri er-tri-6" aria-hidden="true"></i>Black <b id="er-l6">–</b></span>
                                            <span id="er-lg-0"><i class="mix-dot er-tri er-tri-0" aria-hidden="true"></i>ไม่ระบุ <b id="er-l0">–</b></span>
                                        </div>
                                    </div>
                                </div>

                                <!-- การส่งต่อ: Refer in = วิธีมา 04 หรือมีใบ referin · Refer out = ผลจำหน่าย Refer (config/er.php) -->
                                <div class="summary-section-title er-refer-title">
                                    <i class="fa-solid fa-right-left"></i>
                                    การส่งต่อ (Refer)
                                </div>
                                <div class="opd-mini-pair er-refer-pair">
                                    <div class="exam-horizontal-card refer-in-card" data-drill="refer_in" title="Refer in: รับส่งต่อจากสถานพยาบาลอื่น">
                                        <span class="opd-ico" aria-hidden="true"><i class="fa-solid fa-truck-arrow-right fa-flip-horizontal"></i></span>
                                        <div class="summary-title">Refer in</div>
                                        <div class="summary-number"><span id="er-refer-in">—</span> <span class="summary-unit">ราย</span></div>
                                    </div>
                                    <div class="exam-horizontal-card refer-card" data-drill="refer" title="Refer out: ส่งต่อไปสถานพยาบาลอื่น">
                                        <span class="opd-ico" aria-hidden="true"><i class="fa-solid fa-truck-medical"></i></span>
                                        <div class="summary-title">Refer out</div>
                                        <div class="summary-number"><span id="er-refer-out">—</span> <span class="summary-unit">ราย</span></div>
                                    </div>
                                </div>
                            </div>

                            <div class="opd-summary-section">
                                <div class="summary-section-title">
                                    <i class="fa-solid fa-users"></i>
                                    ประเภทผู้ป่วย
                                </div>
                                <div class="exam-horizontal-group">
                                    <div class="exam-horizontal-card refer-card" data-drill="pt_emergency">
                                        <span class="opd-ico" aria-hidden="true"><i class="fa-solid fa-heart-pulse"></i></span>
                                        <div class="summary-title">ฉุกเฉิน</div>
                                        <div class="summary-number"><span id="er-pt-emergency">—</span> <span class="summary-unit">ครั้ง</span></div>
                                    </div>
                                    <div class="exam-horizontal-card admit-card" data-drill="pt_accident">
                                        <span class="opd-ico" aria-hidden="true"><i class="fa-solid fa-car-burst"></i></span>
                                        <div class="summary-title">อุบัติเหตุ</div>
                                        <div class="summary-number"><span id="er-pt-accident">—</span> <span class="summary-unit">ครั้ง</span></div>
                                    </div>
                                    <div class="exam-horizontal-card wait-card" data-drill="pt_general">
                                        <span class="opd-ico" aria-hidden="true"><i class="fa-solid fa-stethoscope"></i></span>
                                        <div class="summary-title">ตรวจโรคทั่วไป</div>
                                        <div class="summary-number"><span id="er-pt-general">—</span> <span class="summary-unit">ครั้ง</span></div>
                                    </div>
                                    <div class="exam-horizontal-card nodr-card" data-drill="pt_service" title="ทำแผล ฉีดยา ใบรับรองแพทย์ และห้องทำแผลเวรเช้า">
                                        <span class="opd-ico" aria-hidden="true"><i class="fa-solid fa-syringe"></i></span>
                                        <div class="summary-title">รับบริการอื่น</div>
                                        <div class="summary-number"><span id="er-pt-service">—</span> <span class="summary-unit">ครั้ง</span></div>
                                    </div>
                                </div>
                            </div>

                            <div class="opd-summary-section">
                                <div class="summary-section-title">
                                    <i class="fa-solid fa-clock"></i>
                                    แยกตามเวร
                                </div>
                                <div class="time-horizontal-group">
                                    <div class="time-horizontal-card time-night" data-drill="shift_night">
                                        <div class="time-icon" aria-hidden="true"><i class="fa-solid fa-moon"></i></div>
                                        <div class="time-info">
                                            <div class="time-label">เวรดึก</div>
                                            <div class="time-range">00:00 – 07:59 น.</div>
                                        </div>
                                        <div class="time-count"><span id="er-shift-night">—</span><small>ครั้ง</small></div>
                                    </div>
                                    <div class="time-horizontal-card time-morning" data-drill="shift_morning">
                                        <div class="time-icon" aria-hidden="true"><i class="fa-solid fa-sun"></i></div>
                                        <div class="time-info">
                                            <div class="time-label">เวรเช้า</div>
                                            <div class="time-range">08:00 – 15:59 น.</div>
                                        </div>
                                        <div class="time-count"><span id="er-shift-morning">—</span><small>ครั้ง</small></div>
                                    </div>
                                    <div class="time-horizontal-card time-evening" data-drill="shift_evening">
                                        <div class="time-icon" aria-hidden="true"><i class="fa-solid fa-cloud-sun"></i></div>
                                        <div class="time-info">
                                            <div class="time-label">เวรบ่าย</div>
                                            <div class="time-range">16:00 – 23:59 น.</div>
                                        </div>
                                        <div class="time-count"><span id="er-shift-evening">—</span><small>ครั้ง</small></div>
                                    </div>
                                </div>
                            </div>

                            <div class="opd-summary-section">
                                <div class="summary-section-title">
                                    <i class="fa-solid fa-door-open"></i>
                                    ผลการจำหน่าย
                                </div>
                                <!-- 6 ช่องรวมได้ยอดทั้งหมด · Admit/Refer นับจาก ipt / referout จุด ER ด้วย (config/er.php) -->
                                <div class="exam-horizontal-group">
                                    <div class="exam-horizontal-card finish-card" data-drill="home">
                                        <span class="opd-ico" aria-hidden="true"><i class="fa-solid fa-house"></i></span>
                                        <div class="summary-title">กลับบ้าน</div>
                                        <div class="summary-number"><span id="er-home">—</span> <span class="summary-unit">ครั้ง</span></div>
                                    </div>
                                    <div class="exam-horizontal-card exam-card" data-drill="gone" title="ไม่รอตรวจ / หนีกลับ / ปฏิเสธการรักษา">
                                        <span class="opd-ico" aria-hidden="true"><i class="fa-solid fa-person-walking-arrow-right"></i></span>
                                        <div class="summary-title">ไม่รอตรวจ / หนีกลับ</div>
                                        <div class="summary-number"><span id="er-gone">—</span> <span class="summary-unit">ครั้ง</span></div>
                                    </div>
                                    <div class="exam-horizontal-card wait-card" data-drill="no_dch" title="ยังอยู่ใน ER หรือยังไม่ได้บันทึกผลการจำหน่าย">
                                        <span class="opd-ico" aria-hidden="true"><i class="fa-solid fa-user-clock"></i></span>
                                        <div class="summary-title">ยังไม่ลงผลจำหน่าย</div>
                                        <div class="summary-number"><span id="er-no-dch">—</span> <span class="summary-unit">ครั้ง</span></div>
                                    </div>
                                    <div class="exam-horizontal-card nodr-card" data-drill="other_dch" title="สังเกตอาการ / เสียชีวิต">
                                        <span class="opd-ico" aria-hidden="true"><i class="fa-solid fa-ellipsis"></i></span>
                                        <div class="summary-title">อื่น ๆ</div>
                                        <div class="summary-number"><span id="er-other-dch">—</span> <span class="summary-unit">ครั้ง</span></div>
                                    </div>
                                </div>
                                <div class="opd-mini-pair">
                                    <div class="exam-horizontal-card admit-card" data-drill="admit">
                                        <span class="opd-ico" aria-hidden="true"><i class="fa-solid fa-bed-pulse"></i></span>
                                        <div class="summary-title">Admit</div>
                                        <div class="summary-number"><span id="er-admit">—</span> <span class="summary-unit">ครั้ง</span></div>
                                    </div>
                                    <div class="exam-horizontal-card refer-card" data-drill="refer_all" title="Refer ทั้งหมด: รับเข้า (in) + ส่งออก (out) · visit ที่มีทั้งสองนับครั้งเดียว">
                                        <span class="opd-ico" aria-hidden="true"><i class="fa-solid fa-right-left"></i></span>
                                        <div class="summary-title">Refer</div>
                                        <div class="summary-number"><span id="er-refer">—</span> <span class="summary-unit">ครั้ง</span></div>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </div>

            <!-- HEADER -->
            <div class="card shadow-sm mb-3">

                <div class="opd-chart">

                    <div class="row align-items-center">

                        <!-- TITLE -->
                        <div class="col-xl-5 col-12">

                            <h2 class="mb-1 text-success" style="font-weight: bold;">
                                <i class="fa-solid fa-chart-column"></i>
                                ข้อมูลสถิติ งานอุบัติเหตุและฉุกเฉิน
                            </h2>

                            <div id="budget-date-text" class="text-success fs-5"></div>

                        </div>

                        <!-- ช่วงเวลา: ปุ่มลัด + ปีงบ + ช่วงวันที่ (assets/js/period_filter.js เติมปุ่มและช่องวันที่) -->
                        <div class="col-xl-7 col-12">

                            <div id="period-filter" class="bbh-period">
                            <div class="budget-select-wrapper">

                                <select id="budget-year" class="form-control budget-select">
                                    <?php
                                    $currentYear = date('Y') + 543 + (date('n') >= 10 ? 1 : 0);
                                    for ($y = $currentYear; $y >= 2565; $y--) {
                                        echo "<option value='$y'>ปีงบประมาณ $y</option>";
                                    }
                                    ?>
                                </select>

                                <i class="fas fa-chevron-down budget-select-icon"></i>

                            </div>
                            </div>

                        </div>

                    </div>

                </div>

                <!-- GRAPHS -->
                <div class="card">

                    <div class="card-body">

                        <div class="row">

                            <!-- Bar Chart (ซ้าย) -->
                            <div class="col-xl-6 col-lg-12 mb-2">

                                <div class="chart-box">

                                    <h5 class="chart-title">
                                        <i class="fa-solid fa-hospital-user"></i>
                                        จำนวนผู้รับบริการ ER
                                    </h5>

                                    <div id="er-chart" style="height: 420px;"></div>

                                </div>

                            </div>

                            <!-- Pie Chart (ขวา) -->
                            <div class="col-xl-6 col-lg-12 mb-2">

                                <div class="chart-box">

                                    <h5 class="chart-title">
                                        <i class="fa-solid fa-chart-pie"></i>
                                        สัดส่วนผู้รับบริการแยกตามประเภท
                                    </h5>

                                    <div id="er-pie-chart" style="height: 420px;"></div>

                                </div>

                            </div>

                        </div>

                        <!-- Row 2: Triage + Time Slot -->
                        <div class="row mt-3">

                            <!-- Triage Chart (ซ้าย) -->
                            <div class="col-xl-6 col-lg-12 mb-2">

                                <div class="chart-box">

                                    <h5 class="chart-title">
                                        <i class="fa-solid fa-triangle-exclamation"></i>
                                        Triage Level — ระดับความเร่งด่วน
                                    </h5>

                                    <div id="er-triage-chart" style="height: 380px;"></div>

                                </div>

                            </div>

                            <!-- Time Slot Chart (ขวา) -->
                            <div class="col-xl-6 col-lg-12 mb-2">

                                <div class="chart-box">

                                    <h5 class="chart-title">
                                        <i class="fa-solid fa-clock"></i>
                                        ช่วงเวลาที่ผู้ป่วยเข้า ER
                                    </h5>

                                    <div id="er-timeslot-chart" style="height: 380px;"></div>

                                </div>

                            </div>

                        </div>
                        <!-- Row 3: Top 10 Diagnosis -->

                        <div class="row mt-3">

                            <div class="col-12">

                                <div class="chart-box">

                                    <h5 class="chart-title">
                                        <i class="fa-solid fa-stethoscope"></i>
                                        10 อันดับโรค/การวินิจฉัยผู้ป่วย ER
                                    </h5>

                                    <div class="table-responsive">
                                        <table class="table table-ipd-top10 table-hover align-middle">
                                            <thead>
                                                <tr>
                                                    <th class="text-center" style="width:60px">อันดับ</th>
                                                    <th class="text-center" style="width:100px">ICD-10</th>
                                                    <th>ชื่อโรค / การวินิจฉัย</th>
                                                    <th class="text-center" style="width:90px">รวม</th>
                                                    <th class="text-center" style="width:80px">
                                                        <i class="fa-solid fa-mars"></i>
                                                        ชาย
                                                    </th>
                                                    <th class="text-center" style="width:80px">
                                                        <i class="fa-solid fa-venus"></i>
                                                        หญิง
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody id="er-top10-tbody">
                                                <tr>
                                                    <td colspan="6" class="text-center text-muted py-4">
                                                        <i class="fa-solid fa-spinner fa-spin me-2"></i>
                                                        กำลังโหลดข้อมูล…
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>

            </div>

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