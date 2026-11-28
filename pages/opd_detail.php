<?php
// ป้าย LIVE ใน navbar แบบหน้าแรก — opd_detail.js แสดงเฉพาะตอนเลือกดูข้อมูลวันนี้
$showLiveStatus = true;

require_once __DIR__ . '/../auth/provider.php';
provider_require_login_page();   // ยังไม่ login → กลับหน้าแรก

include '../templates/header.php';
include '../templates/navbar.php';
include '../templates/sidebar.php';

// ปุ่ม (i) ข้างหัวข้อ → หน้าต่างที่มาของข้อมูล (เนื้อหา assets/js/opd_info.js, หน้าต่าง assets/js/er_info.js)
function opd_info_btn(string $key, string $label, bool $onMast = false): string
{
    return '<button type="button" class="er-info-btn' . ($onMast ? ' is-on-mast' : '') . '" data-info="' . $key . '"'
        . ' aria-label="ที่มาและนิยามของข้อมูล: ' . htmlspecialchars($label) . '" title="ที่มาและนิยามของข้อมูล">'
        . '<i class="fa-solid fa-circle-info" aria-hidden="true"></i></button>';
}
?>

<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/opd_tabs.css?v=<?= filemtime(__DIR__ . '/../assets/css/opd_tabs.css') ?>">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/opd_quality.css?v=<?= filemtime(__DIR__ . '/../assets/css/opd_quality.css') ?>">
<!-- โครงหน้าเดียวกับหน้า ER (er_theme.css) แต่ใช้ชุดสีเขียว (opd_theme.css ตั้ง token ทับใต้ .opd-theme) -->
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/er_theme.css?v=<?= filemtime(__DIR__ . '/../assets/css/er_theme.css') ?>">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/opd_theme.css?v=<?= filemtime(__DIR__ . '/../assets/css/opd_theme.css') ?>">

<div class="content-wrapper er-theme opd-theme">
    <section class="content">
        <div class="er-page">

            <!-- =====================================================
                 OPD TABS
            ====================================================== -->
            <!-- แถบบน: กลับหน้าแรก | แท็บ | หน้า 3D Monitor -->
            <div class="opd-toolbar">
            <a class="opd-tool-btn" href="<?= BASE_URL ?>index.php">
                <i class="fa-solid fa-arrow-left" aria-hidden="true"></i> หน้าแรก
            </a>
            <div class="opd-page-tabs" role="tablist" aria-label="ประเภทผู้รับบริการ OPD">
                <button class="opd-page-tab active" type="button" role="tab" aria-selected="true"
                    data-opd-tab="general">
                    <i class="fa-solid fa-stethoscope" aria-hidden="true"></i>
                    ตรวจโรคทั่วไป
                </button>

                <!-- คลินิคพิเศษใช้แผง/layout เดียวกับตรวจโรคทั่วไป เปลี่ยนแค่ชุดแผนก (?scope=special) -->
                <button class="opd-page-tab" type="button" role="tab" aria-selected="false" data-opd-tab="special" data-opd-panel="general">
                    <i class="fa-solid fa-hospital-user" aria-hidden="true"></i>
                    คลินิคพิเศษ
                </button>

                <button class="opd-page-tab" type="button" role="tab" aria-selected="false" data-opd-tab="quality">
                    <i class="fa-solid fa-stopwatch" aria-hidden="true"></i>
                    เวลารอ &amp; คุณภาพบริการ
                </button>
            </div>
            <a class="opd-tool-btn is-3d" href="<?= BASE_URL ?>pages/opd_monitor.php">
                <i class="fa-solid fa-tower-broadcast" aria-hidden="true"></i> Real time Monitor
            </a>
            </div>

            <!-- =====================================================
                 TAB 1 : ตรวจโรคทั่วไป / คลินิคพิเศษ
                 ทุกตัวเลขที่มี data-drill ต้องอยู่ใน #opd-tab-general (bbhDrill.bind ใน opd_detail.js)
            ====================================================== -->
            <div class="opd-tab-panel active" id="opd-tab-general" role="tabpanel">

                <!-- แถบหัวเขียว: ชื่อหน้า + เทียบสัปดาห์ก่อน + ตัวเลือกวันที่ -->
                <header class="er-mast" id="opd-summary">
                    <div class="er-mast-top">
                        <div class="er-mast-heading">
                            <div class="er-mast-title-row">
                                <h1 class="er-mast-title" id="opd-selected-date-title">ผู้มารับบริการตรวจโรคทั่วไป</h1>
                                <!-- แสดงเมื่อดูวันนี้ (opd_detail.js) แทนข้อความวันที่ -->
                                <span class="opd-live" id="opd-live-badge" title="ข้อมูลวันนี้ รีเฟรชทุก 60 วินาที" hidden><span class="opd-live-dot" aria-hidden="true"></span>Live</span>
                                <?= opd_info_btn('mast', 'ผู้มารับบริการ OPD', true) ?>
                            </div>
                            <p class="er-mast-sub" id="opd-compare">—</p>
                        </div>

                        <div class="oq-controls opd-day-filter er-mast-controls">
                            <div class="oq-seg" role="group" aria-label="เลือกวันด่วน">
                                <button type="button" data-opd-day="0" aria-pressed="true">วันนี้</button>
                                <button type="button" data-opd-day="1" aria-pressed="false">เมื่อวาน</button>
                            </div>
                            <div class="oq-range">
                                <i class="fa-solid fa-calendar-day" aria-hidden="true"></i>
                                <label class="oq-date">
                                    <span id="opd-service-date-text">—</span>
                                    <input type="date" id="opd-service-date" aria-label="เลือกวันที่">
                                </label>
                            </div>
                        </div>
                    </div>
                </header>

                <!-- 1 ภาพรวม: ช่องสีทึบโทนเขียว · ใหม่ + เก่า = จำนวนคน -->
                <section class="er-panel" aria-labelledby="opd-sec1">
                    <header class="er-sec-head">
                        <span class="er-sec-no" aria-hidden="true">1</span>
                        <div>
                            <h2 class="er-h2" id="opd-sec1">ภาพรวมผู้ป่วยนอก <?= opd_info_btn('sec1', 'ภาพรวมผู้ป่วยนอก') ?></h2>
                            <p class="er-sec-sub">ยอดของวันที่เลือก นับเป็นครั้งที่มารับบริการ</p>
                        </div>
                    </header>
                    <div class="er-kpis opd-kpis">
                        <div class="er-kpi er-k1" data-drill="all">
                            <span class="er-kpi-label"><i class="fa-solid fa-hospital-user" aria-hidden="true"></i>ผู้มารับบริการ</span>
                            <b class="er-kpi-num" id="opd_total_today">—</b>
                            <span class="er-kpi-sub">ครั้ง จาก <b id="opd_total_people">—</b> คน</span>
                        </div>
                        <div class="er-kpi er-k2" data-drill="new">
                            <span class="er-kpi-label"><i class="fa-solid fa-user-plus" aria-hidden="true"></i>ผู้ป่วยใหม่</span>
                            <b class="er-kpi-num" id="new_patient">—</b>
                            <span class="er-kpi-sub">คน</span>
                        </div>
                        <div class="er-kpi er-k3" data-drill="old">
                            <span class="er-kpi-label"><i class="fa-solid fa-user-check" aria-hidden="true"></i>ผู้ป่วยเก่า</span>
                            <b class="er-kpi-num" id="old_patient">—</b>
                            <span class="er-kpi-sub">คน</span>
                        </div>
                        <div class="er-kpi er-k4" data-drill="admit">
                            <span class="er-kpi-label"><i class="fa-solid fa-bed-pulse" aria-hidden="true"></i>Admit</span>
                            <b class="er-kpi-num" id="opd_admit">—</b>
                            <span class="er-kpi-sub">ครั้ง</span>
                        </div>
                        <div class="er-kpi er-k5" data-drill="refer">
                            <span class="er-kpi-label"><i class="fa-solid fa-truck-medical" aria-hidden="true"></i>Refer ส่งต่อ</span>
                            <b class="er-kpi-num" id="opd_refer">—</b>
                            <span class="er-kpi-sub">ครั้ง</span>
                        </div>
                    </div>
                </section>

                <!-- 2–5 ผู้ป่วยของวันที่เลือก: แต่ละหมวดเป็นการ์ดของตัวเอง แต่ละแถวคลิกดูรายชื่อ -->
                <div class="er-cols">

                    <!-- Walk-in + มาตามนัด = ทั้งหมด (config/opd.php) -->
                    <section class="er-panel er-col" aria-labelledby="opd-sec2">
                        <header class="er-sec-head">
                            <span class="er-sec-no" aria-hidden="true">2</span>
                            <div><h2 class="er-h2" id="opd-sec2">ประเภทการมารับบริการ <?= opd_info_btn('sec2', 'ประเภทการมารับบริการ') ?></h2><p class="er-sec-sub">Walk-in และมาตามนัด</p></div>
                        </header>
                        <div class="er-stat er-stat-lead" data-drill="walkin">
                            <span class="er-stat-label"><span class="er-ico" aria-hidden="true"><i class="fa-solid fa-person-walking"></i></span>Walk-in <small>มารับบริการโดยไม่มีนัดล่วงหน้า</small></span>
                            <span class="er-stat-value"><b id="walkin_today">—</b> ราย</span>
                        </div>
                        <div class="er-mix-bar" role="img" id="visit-mix-bar" aria-label="สัดส่วน Walk-in และมาตามนัด">
                            <span class="mix-walkin" id="mix-walkin-bar"></span>
                            <span class="mix-appt" id="mix-appt-bar"></span>
                        </div>
                        <div class="er-mix-legend">
                            <span><i class="er-dot mix-walkin" aria-hidden="true"></i>Walk-in <b id="mix-walkin-pct">–</b></span>
                            <span><i class="er-dot mix-appt" aria-hidden="true"></i>มาตามนัด <b id="mix-appt-pct">–</b></span>
                        </div>
                    </section>

                    <!-- % คิดจากนัดที่ไม่ได้ยกเลิก (มา + ไม่มา) -->
                    <section class="er-panel er-col" aria-labelledby="appt-section-title">
                        <header class="er-sec-head">
                            <span class="er-sec-no" aria-hidden="true">3</span>
                            <div><h2 class="er-h2"><span id="appt-section-title">นัดหมายวันนี้</span> <?= opd_info_btn('sec3', 'นัดหมาย') ?></h2><p class="er-sec-sub">ไม่นับนัดที่ยกเลิก</p></div>
                        </header>
                        <div class="er-stat er-stat-lead" data-drill="appt">
                            <span class="er-stat-label"><span class="er-ico" aria-hidden="true"><i class="fa-solid fa-calendar-days"></i></span>นัดทั้งหมด</span>
                            <span class="er-stat-value"><b id="appoint_today">—</b> ราย</span>
                        </div>
                        <div class="er-meter opd-appt-meter" role="img" id="appt-progress" aria-label="สัดส่วนมาตามนัด"><span id="appt-progress-bar"></span></div>
                        <div class="er-stat" data-drill="appt_came">
                            <span class="er-stat-label"><span class="er-ico" aria-hidden="true"><i class="fa-solid fa-square-check"></i></span>มาตามนัด</span>
                            <span class="er-stat-value"><b id="oapp_success">—</b> ราย <small id="oapp_success_pct">–</small></span>
                        </div>
                        <div class="er-stat" data-drill="appt_miss">
                            <span class="er-stat-label"><span class="er-ico" aria-hidden="true"><i class="fa-solid fa-square-xmark"></i></span><span id="miss_label">ไม่มาตามนัด</span></span>
                            <span class="er-stat-value"><b id="miss_today">—</b> ราย <small id="miss_pct">–</small></span>
                        </div>
                    </section>

                    <section class="er-panel er-col" aria-labelledby="opd-sec4">
                        <header class="er-sec-head">
                            <span class="er-sec-no" aria-hidden="true">4</span>
                            <div><h2 class="er-h2" id="opd-sec4">แยกตามช่วงเวลา <?= opd_info_btn('sec4', 'แยกตามช่วงเวลา') ?></h2><p class="er-sec-sub">ตามเวลาที่ลงทะเบียน</p></div>
                        </header>
                        <div class="er-stat" data-drill="night">
                            <span class="er-stat-label"><span class="er-ico" aria-hidden="true"><i class="fa-solid fa-moon"></i></span>ก่อนเวลาราชการ <small>20:01 – 07:59 น.</small></span>
                            <span class="er-stat-value"><b id="before_time">—</b> ราย</span>
                        </div>
                        <div class="er-stat" data-drill="morning">
                            <span class="er-stat-label"><span class="er-ico" aria-hidden="true"><i class="fa-solid fa-sun"></i></span>ในเวลาราชการ <small>08:00 – 15:59 น.</small></span>
                            <span class="er-stat-value"><b id="worktime">—</b> ราย</span>
                        </div>
                        <div class="er-stat" data-drill="evening">
                            <span class="er-stat-label"><span class="er-ico" aria-hidden="true"><i class="fa-solid fa-cloud-sun"></i></span>นอกเวลาราชการ <small>16:00 – 20:00 น.</small></span>
                            <span class="er-stat-value"><b id="after_time">—</b> ราย</span>
                        </div>
                    </section>

                    <!-- 4 แถวรวมได้ยอดรวม -->
                    <section class="er-panel er-col" aria-labelledby="opd-sec5">
                        <header class="er-sec-head">
                            <span class="er-sec-no" aria-hidden="true">5</span>
                            <div><h2 class="er-h2" id="opd-sec5">สถานะการตรวจ <?= opd_info_btn('sec5', 'สถานะการตรวจ') ?></h2><p class="er-sec-sub">4 แถวรวมได้ยอดทั้งหมด</p></div>
                        </header>
                        <div class="er-stat" data-drill="wait_triage">
                            <span class="er-stat-label"><span class="er-ico" aria-hidden="true"><i class="fa-solid fa-user-nurse"></i></span>รอซักประวัติ</span>
                            <span class="er-stat-value"><b id="wait_triage">—</b> ราย</span>
                        </div>
                        <div class="er-stat" data-drill="wait_exam">
                            <span class="er-stat-label"><span class="er-ico" aria-hidden="true"><i class="fa-solid fa-stethoscope"></i></span>รอตรวจ</span>
                            <span class="er-stat-value"><b id="wait_exam">—</b> ราย</span>
                        </div>
                        <div class="er-stat" data-drill="finish">
                            <span class="er-stat-label"><span class="er-ico" aria-hidden="true"><i class="fa-solid fa-circle-check"></i></span>ตรวจเสร็จแล้ว</span>
                            <span class="er-stat-value"><b id="finish_exam">—</b> ราย</span>
                        </div>
                        <div class="er-stat" data-drill="no_doctor"
                            title="กลับบ้าน / Admit / อยู่ห้องยา–การเงิน โดยไม่มีบันทึกเวลาพบแพทย์ (เช่น รับยาเดิม ตรวจแล็บ หรือลืมกดเวลา)">
                            <span class="er-stat-label"><span class="er-ico" aria-hidden="true"><i class="fa-solid fa-user-clock"></i></span>ไม่มีบันทึกพบแพทย์ <small>รับยาเดิม ตรวจแล็บ หรือไม่ได้กดเวลา</small></span>
                            <span class="er-stat-value"><b id="no_doctor">—</b> ราย</span>
                        </div>
                    </section>

                </div>

                <!-- 6 ข้อมูลของวันที่เลือก (api/opd_general_today.php, api/opd_visit_today.php, api/opd_service_kpi.php) -->
                <section class="er-panel" aria-labelledby="opd-day-title">
                    <header class="er-sec-head">
                        <span class="er-sec-no" aria-hidden="true">6</span>
                        <div>
                            <h2 class="er-h2"><span id="opd-day-title">ข้อมูลผู้มารับบริการวันนี้</span> <?= opd_info_btn('day', 'ข้อมูลของวันที่เลือก') ?></h2>
                            <p class="er-sec-sub">เปลี่ยนวันที่ได้จากตัวกรองด้านบน, นับเป็นครั้งที่มารับบริการ</p>
                        </div>
                    </header>

                    <div class="er-charts">
                        <figure class="er-chart opd-span-4">
                            <figcaption class="er-h3"><span class="er-ico" aria-hidden="true"><i class="fa-solid fa-chart-column"></i></span><span>ผู้ป่วยเข้า – พบแพทย์ รายชั่วโมง <?= opd_info_btn('flow', 'ผู้ป่วยเข้า พบแพทย์ รายชั่วโมง') ?><small class="opd-cap-sub" id="flow-note">—</small></span></figcaption>
                            <div id="opd-flow-chart"></div>
                        </figure>
                        <figure class="er-chart opd-span-2">
                            <figcaption class="er-h3"><span class="er-ico" aria-hidden="true"><i class="fa-solid fa-stopwatch"></i></span><span>เวลารอเฉลี่ย <?= opd_info_btn('wait', 'เวลารอเฉลี่ย') ?><small class="opd-cap-sub" id="wait-note">—</small></span></figcaption>
                            <div id="opd-wait-list" class="opd-wait-list"></div>
                            <button type="button" class="opd-wait-more" data-goto-tab="quality">
                                ดูรายละเอียดที่แท็บเวลารอ &amp; คุณภาพบริการ <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                            </button>
                        </figure>

                        <figure class="er-chart opd-span-3">
                            <figcaption class="er-h3"><span class="er-ico" aria-hidden="true"><i class="fa-solid fa-user-doctor"></i></span><span>จุดตรวจของแพทย์ <?= opd_info_btn('room', 'จุดตรวจของแพทย์') ?><small class="opd-cap-sub">จำนวนที่ตรวจ, ตัวเลขในวงเล็บ = รอแพทย์เฉลี่ยหลังซักประวัติ (นาที)</small></span></figcaption>
                            <div id="opd-room-chart"></div>
                        </figure>
                        <figure class="er-chart opd-span-3">
                            <figcaption class="er-h3"><span class="er-ico" aria-hidden="true"><i class="fa-solid fa-calendar-xmark"></i></span><span>นัดหมายรายคลินิก <?= opd_info_btn('clinic', 'นัดหมายรายคลินิก') ?><small class="opd-cap-sub" id="clinic-note">—</small></span></figcaption>
                            <div id="opd-clinic-chart"></div>
                        </figure>

                        <figure class="er-chart opd-span-3">
                            <figcaption class="er-h3"><span class="er-ico" aria-hidden="true"><i class="fa-solid fa-hospital"></i></span><span>แผนกที่ลงทะเบียน <?= opd_info_btn('dept', 'แผนกที่ลงทะเบียน') ?><small class="opd-cap-sub">10 แผนกที่มีผู้มารับบริการมากที่สุด</small></span></figcaption>
                            <div id="opd-dept-chart"></div>
                        </figure>
                        <figure class="er-chart opd-span-3">
                            <figcaption class="er-h3"><span class="er-ico" aria-hidden="true"><i class="fa-solid fa-id-card"></i></span><span>กลุ่มสิทธิ์การรักษา <?= opd_info_btn('pttype', 'กลุ่มสิทธิ์การรักษา') ?><small class="opd-cap-sub">ชี้ที่แท่งเพื่อดูสิทธิ์ย่อย</small></span></figcaption>
                            <div id="pttype-chart" class="pttype-chart"></div>
                        </figure>

                        <figure class="er-chart opd-span-4">
                            <figcaption class="er-h3"><span class="er-ico" aria-hidden="true"><i class="fa-solid fa-clock"></i></span><span>การกระจายเวลานัดหมาย <?= opd_info_btn('appttime', 'การกระจายเวลานัดหมาย') ?><small class="opd-cap-sub" id="appt-time-note">—</small></span></figcaption>
                            <div id="opd-appt-time-chart"></div>
                        </figure>
                        <figure class="er-chart opd-span-2">
                            <figcaption class="er-h3"><span class="er-ico" aria-hidden="true"><i class="fa-solid fa-earth-asia"></i></span><span>สัญชาติ <?= opd_info_btn('nation', 'สัญชาติ') ?><small class="opd-cap-sub" id="nation-note">—</small></span></figcaption>
                            <div id="opd-nation-chart"></div>
                        </figure>
                    </div>
                </section>

                <!-- 7 ข้อมูลตามช่วงเวลา (assets/js/period_filter.js: วันนี้ / เดือนนี้ / ปีงบ / กำหนดเอง) — OPD ทั้งหมด (ทั่วไป + คลินิคพิเศษ) แสดงเหมือนกันทั้ง 2 แท็บ -->
                <section class="er-panel er-stats" aria-labelledby="opd-fy-title">
                    <div class="er-stats-head">
                        <header class="er-sec-head">
                            <span class="er-sec-no" aria-hidden="true">7</span>
                            <div>
                                <h2 class="er-h2" id="opd-fy-title">ข้อมูลตามช่วงเวลา <?= opd_info_btn('fy', 'ข้อมูลตามช่วงเวลา') ?></h2>
                                <p class="er-sec-sub">ผู้ป่วยนอกทั้งหมด (ตรวจโรคทั่วไป + คลินิคพิเศษ) · <span id="budget-date-text"></span></p>
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

                    <p class="er-sec-sub er-refer-hint"><i class="fa-solid fa-hand-pointer" aria-hidden="true"></i> คลิกแท่งกราฟ หรือแถวโรค เพื่อดูรายชื่อผู้ป่วยของช่วงเวลาที่เลือก</p>
                    <!-- กราฟมีชื่อในตัว (opd_detail.js) -->
                    <div class="er-chart">
                        <div id="opd-chart" style="height:470px;"></div>
                    </div>

                    <h3 class="er-h3 er-h3-gap"><span class="er-ico" aria-hidden="true"><i class="fa-solid fa-users"></i></span>กลุ่มอายุผู้ป่วย OPD <?= opd_info_btn('age', 'กลุ่มอายุผู้ป่วย') ?></h3>
                    <div class="row mb-3" id="opd-age-summary"></div>

                    <h3 class="er-h3 er-h3-gap" id="opd-top10-title"><span class="er-ico" aria-hidden="true"><i class="fa-solid fa-trophy"></i></span>10 อันดับโรค/การวินิจฉัยผู้ป่วยนอก <?= opd_info_btn('top10', '10 อันดับโรค') ?></h3>
                    <div class="table-responsive">
                        <table class="table er-table table-ipd-top10" aria-labelledby="opd-top10-title">
                            <thead>
                                <tr>
                                    <th class="text-center" style="width:64px">อันดับ</th>
                                    <th class="text-center d-none d-sm-table-cell" style="width:100px">ICD-10</th>
                                    <th>ชื่อโรค / การวินิจฉัย</th>
                                    <th class="text-center" style="width:90px">รวม</th>
                                    <th class="text-center" style="width:80px"><i class="fa-solid fa-mars" aria-hidden="true"></i> ชาย</th>
                                    <th class="text-center" style="width:80px"><i class="fa-solid fa-venus" aria-hidden="true"></i> หญิง</th>
                                </tr>
                            </thead>
                            <tbody id="opd-top10-tbody">
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

            <!-- =====================================================
                 TAB 3 : เวลารอ & คุณภาพบริการ (api/opd_service_kpi.php)
            ====================================================== -->
            <div class="opd-tab-panel" id="opd-tab-quality" role="tabpanel" hidden>
                <!-- ตัวกรองช่วงวันที่: ปุ่มลัด + ช่องวันที่แสดงแบบ พ.ศ. (input date จริงซ้อนอยู่ใต้ข้อความ กดแล้วเปิดปฏิทิน) -->
                <header class="er-mast">
                    <div class="er-mast-top">
                        <div class="er-mast-heading">
                            <div class="er-mast-title-row"><h1 class="er-mast-title">เวลารอและคุณภาพบริการผู้ป่วยนอก</h1><?= opd_info_btn('q_kpi', 'เวลารอและคุณภาพบริการ', true) ?></div>
                            <p class="er-mast-sub" id="oq-period">—</p>
                        </div>
                        <div class="oq-controls er-mast-controls">
                            <div class="oq-seg" role="group" aria-label="ช่วงวันที่ด่วน">
                                <button type="button" data-oq-days="1" aria-pressed="false">วันนี้</button>
                                <button type="button" data-oq-days="7" aria-pressed="false">7 วัน</button>
                                <button type="button" data-oq-days="30" aria-pressed="false">30 วัน</button>
                            </div>
                            <div class="oq-range">
                                <i class="fa-regular fa-calendar" aria-hidden="true"></i>
                                <label class="oq-date">
                                    <span id="oq-d1-text">—</span>
                                    <input type="date" id="oq-d1" aria-label="วันที่เริ่มต้น">
                                </label>
                                <span class="oq-range-sep" aria-hidden="true">ถึง</span>
                                <label class="oq-date">
                                    <span id="oq-d2-text">—</span>
                                    <input type="date" id="oq-d2" aria-label="วันที่สิ้นสุด">
                                </label>
                            </div>
                        </div>
                    </div>
                </header>

                <div class="oq-kpis" id="oq-kpis"></div>

                <div class="er-split">
                    <section class="er-panel" aria-labelledby="oq-sec1">
                        <header class="er-sec-head">
                            <span class="er-sec-no" aria-hidden="true">1</span>
                            <div><h2 class="er-h2" id="oq-sec1">เวลาแต่ละขั้นเทียบเป้าหมาย (SLA) <?= opd_info_btn('q_sla', 'SLA') ?></h2></div>
                        </header>
                        <div class="table-responsive">
                            <table class="table table-sm oq-table">
                                <thead><tr><th>ขั้นตอน</th><th class="text-right">เฉลี่ย (นาที)</th><th class="text-right">เป้าหมาย</th><th class="text-right">ทันเป้า</th><th class="text-right">จำนวน</th><th class="text-center">ผล</th></tr></thead>
                                <tbody id="oq-sla"></tbody>
                            </table>
                        </div>
                        <p class="oq-note">เวลาจาก ovst_service_time · ซักประวัติ/พบแพทย์ = ครั้งแรก (ไม่นับจุด Exit) · นับเฉพาะ visit ที่บันทึกทั้งสองขั้น · ติดลบหรือเกิน 6 ชม. ไม่นับ</p>
                    </section>
                    <section class="er-panel" aria-labelledby="oq-sec2">
                        <header class="er-sec-head">
                            <span class="er-sec-no" aria-hidden="true">2</span>
                            <div><h2 class="er-h2" id="oq-sec2">Patient Flow — % ที่บันทึกเวลาแต่ละขั้น <?= opd_info_btn('q_funnel', 'Patient Flow') ?></h2></div>
                        </header>
                        <div id="oq-funnel"></div>
                        <p class="oq-note">ขั้นที่ % ต่ำผิดปกติ = จุดนั้นไม่ได้กดเวลาใน HOSxP ทำให้เวลารอของขั้นนั้นเชื่อได้น้อย</p>
                    </section>
                </div>

                <section class="er-panel" aria-labelledby="oq-sec3">
                    <header class="er-sec-head">
                        <span class="er-sec-no" aria-hidden="true">3</span>
                        <div><h2 class="er-h2" id="oq-sec3">ผู้ป่วยตามชั่วโมงที่ลงทะเบียน — Walk-in / นัด <?= opd_info_btn('q_hours', 'ผู้ป่วยตามชั่วโมง') ?></h2></div>
                    </header>
                    <div id="oq-hours" style="height:320px;"></div>
                    <p class="oq-note" id="oq-hours-note"></p>
                </section>

                <section class="er-panel" aria-labelledby="oq-sec4">
                    <header class="er-sec-head">
                        <span class="er-sec-no" aria-hidden="true">4</span>
                        <div><h2 class="er-h2" id="oq-sec4">Alert &amp; Action <?= opd_info_btn('q_alerts', 'Alert and Action') ?></h2></div>
                    </header>
                    <div class="table-responsive">
                        <table class="table table-sm oq-table">
                            <thead><tr><th>ประเด็น</th><th class="text-right">ค่าจริง</th><th class="text-right">เป้าหมาย</th><th class="text-center">ระดับ</th><th>แนวทาง</th></tr></thead>
                            <tbody id="oq-alerts"></tbody>
                        </table>
                    </div>
                </section>

                <section class="er-panel" aria-labelledby="oq-sec5">
                    <header class="er-sec-head">
                        <span class="er-sec-no" aria-hidden="true">5</span>
                        <div><h2 class="er-h2" id="oq-sec5">รายแผนก (แผนกส่งคิวแรก) — 20 อันดับ <?= opd_info_btn('q_deps', 'รายแผนก') ?></h2></div>
                    </header>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover oq-table">
                            <thead><tr><th>แผนก</th><th class="text-right">Visit</th><th class="text-right">พบแพทย์</th><th class="text-right">Door-to-Doctor</th><th class="text-right">ทันเป้า</th><th class="text-right">รอรับยา</th><th class="text-right">เวลารวม</th></tr></thead>
                            <tbody id="oq-deps"></tbody>
                        </table>
                    </div>
                    <p class="oq-note">เวลาเป็นนาทีเฉลี่ย · ไม่รวม Admit และ Telemed/PCU · ผู้ป่วยนัด = มาตามนัดในระบบ (oapp) · เป้าหมายเป็นค่าตั้งต้น รอทีมคุณภาพยืนยัน</p>
                </section>
            </div>

        </div>
    </section>
</div>

<script src="<?= BASE_URL ?>assets/lib/highcharts/13.1.1/highcharts.js"></script>
<script src="<?= BASE_URL ?>assets/js/period_filter.js?v=<?= filemtime(__DIR__ . '/../assets/js/period_filter.js') ?>"></script>
<script src="<?= BASE_URL ?>assets/js/opd_info.js?v=<?= filemtime(__DIR__ . '/../assets/js/opd_info.js') ?>"></script>
<script src="<?= BASE_URL ?>assets/js/er_info.js?v=<?= filemtime(__DIR__ . '/../assets/js/er_info.js') ?>"></script>
<script src="<?= BASE_URL ?>assets/js/bbh_drill.js?v=<?= filemtime(__DIR__ . '/../assets/js/bbh_drill.js') ?>"></script>
<script src="<?= BASE_URL ?>assets/js/opd_detail.js?v=<?= filemtime(__DIR__ . '/../assets/js/opd_detail.js') ?>"></script>
<script src="<?= BASE_URL ?>assets/js/opd_quality.js?v=<?= filemtime(__DIR__ . '/../assets/js/opd_quality.js') ?>"></script>

<script>
/* =========================================================
   OPD DETAIL TABS
   ตรวจโรคทั่วไป / คลินิคพิเศษ
========================================================= */
(function() {
    const tabs = document.querySelectorAll('[data-opd-tab]');
    const panels = document.querySelectorAll('.opd-tab-panel');

    tabs.forEach(tab => {
        tab.addEventListener('click', function() {
            const target = this.dataset.opdTab;
            const panelName = this.dataset.opdPanel || target;

            tabs.forEach(item => {
                const active = item === this;
                item.classList.toggle('active', active);
                item.setAttribute('aria-selected', active ? 'true' : 'false');
            });

            panels.forEach(panel => {
                const active = panel.id === 'opd-tab-' + panelName;
                panel.classList.toggle('active', active);
                panel.hidden = !active;
            });

            window.dispatchEvent(new CustomEvent('opdTabChanged', {
                detail: {
                    tab: target
                }
            }));
        });
    });
})();
</script>

<?php include '../templates/footer.php'; ?>
