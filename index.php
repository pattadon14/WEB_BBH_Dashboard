<?php

// navbar.php แสดงป้าย LIVE เฉพาะหน้าที่ตั้งค่านี้ (เวลาอัปเดตโดย markUpdated() ด้านล่าง)
$showLiveStatus = true;

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

?>

<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/index.css?v=<?= filemtime(__DIR__ . '/assets/css/index.css') ?>">

<div class="content-wrapper home-dash">

    <section class="content pt-3">

        <div class="container-fluid">

            <!-- ============================================
                 SECTION 1: สรุปยอดวันนี้
                 ============================================ -->
            <div class="row mt-2">

                <div class="col-12 mb-2">
                    <h5 class="text-success mb-0" style="font-weight:bold;">
                        <i class="fa-solid fa-gauge-high"></i>
                        ภาพรวมวันนี้
                    </h5>
                </div>

                <div class="col-lg-4 col-md-6 col-12 mb-2">

                    <!-- สีการ์ด = สีแถบหัวของหน้ารายละเอียด (kpi-opd เขียว / kpi-ipd น้ำเงิน / kpi-er แดง ใน index.css) -->
                    <div class="small-box elevation-3 opd-monitor-card kpi-tile kpi-opd">

                        <div class="inner">

                            <h3 id="opd-total">
                                Loading…
                            </h3>

                            <!-- นิยามเดียวกับหน้า OPD หัวข้อ 7 (api/opd.php: ทุกแผนก OPD นับครั้ง) -->
                            <p class="kpi-title mb-0">
                                ผู้ป่วยนอก (OPD) วันนี้ <?= home_info_btn('opd', 'ผู้ป่วยนอกวันนี้') ?>
                            </p>
                            <span class="kpi-scope">ตรวจโรคทั่วไป + คลินิคพิเศษ</span>

                            <small class="kpi-sub">

                                (เดือนนี้

                                <span id="opd-month-patient">
                                    Loading…
                                </span>

                                คน /

                                <span id="opd-month-visit">
                                    Loading…
                                </span>

                                ครั้ง)

                            </small>
                            <!-- วันเดียวกันของสัปดาห์ก่อน ถึงเวลาเดียวกัน (prev_week ของ API การ์ด) -->
                            <span class="kpi-trend" id="opd-trend"></span>


                        </div>

                        <div class="icon">

                            <i class="fa-solid fa-stethoscope"></i>

                        </div>

                        <a href="pages/opd_monitor.php"
                           class="opd-monitor-launch"
                           title="เปิด OPD Real-time Monitor"
                           aria-label="เปิด OPD Real-time Monitor">
                            <i class="fa-solid fa-tower-broadcast"></i>
                            <span>Real-time Monitor</span>
                        </a>

                        <a href="pages/opd_detail.php" class="small-box-footer">

                            รายละเอียด
                            <i class="fas fa-arrow-circle-right"></i>

                        </a>

                    </div>

                </div>
                <div class="col-lg-4 col-md-6 col-12 mb-2">

                    <div class="small-box elevation-3 kpi-tile kpi-ipd">

                        <div class="inner">

                            <h3 id="ipd-total">
                                Loading…
                            </h3>

                            <!-- นิยามเดียวกับช่อง "รับใหม่" หน้า IPD (api/ipd.php: ipt.regdate ทุกหอ นับ AN) -->
                            <p class="kpi-title mb-0">
                                ผู้ป่วยในรับใหม่ (Admit) วันนี้ <?= home_info_btn('ipd', 'ผู้ป่วยในรับใหม่วันนี้') ?>
                            </p>
                            <span class="kpi-scope">ทุกหอผู้ป่วย</span>

                            <small class="kpi-sub">

                                (เดือนนี้

                                <span id="ipd-month-patient">
                                    Loading…
                                </span>

                                คน /

                                <span id="ipd-month-visit">
                                    Loading…
                                </span>

                                ราย)

                            </small>
                            <!-- วันเดียวกันของสัปดาห์ก่อน ถึงเวลาเดียวกัน (prev_week ของ API การ์ด) -->
                            <span class="kpi-trend" id="ipd-trend"></span>

                        </div>

                        <div class="icon">

                            <i class="fa-solid fa-hospital-user"></i>

                        </div>
                        <a href="pages/ipd_detail.php" class="small-box-footer">

                            รายละเอียด
                            <i class="fas fa-arrow-circle-right"></i>

                        </a>

                    </div>

                </div>
                <div class="col-lg-4 col-md-12 col-12 mb-2"><!-- แท็บเล็ต: การ์ด ER เต็มแถว ไม่เหลือช่องว่าง -->

                    <div class="small-box elevation-3 kpi-tile kpi-er">

                        <div class="inner">

                            <h3 id="er-total">
                                Loading…
                            </h3>

                            <!-- นิยามเดียวกับ "ผู้มารับบริการ ER" หน้า ER (api/er.php: er_regist ทุกประเภท นับครั้ง) -->
                            <p class="kpi-title mb-0">
                                ผู้มารับบริการ ER วันนี้ <?= home_info_btn('er', 'ผู้มารับบริการ ER วันนี้') ?>
                            </p>
                            <span class="kpi-scope">ทุกประเภทผู้ป่วย ER</span>

                            <small class="kpi-sub">

                                (เดือนนี้

                                <span id="er-month-patient">
                                    Loading…
                                </span>

                                คน /

                                <span id="er-month-visit">
                                    Loading…
                                </span>

                                ครั้ง)

                            </small>
                            <!-- วันเดียวกันของสัปดาห์ก่อน ถึงเวลาเดียวกัน (prev_week ของ API การ์ด) -->
                            <span class="kpi-trend" id="er-trend"></span>

                        </div>

                        <div class="icon">

                            <i class="fa-solid fa-truck-medical"></i>

                        </div>
                        <a href="pages/ER_detail.php" class="small-box-footer">

                            รายละเอียด
                            <i class="fas fa-arrow-circle-right"></i>

                        </a>

                    </div>

                </div>

            </div>

            <!-- ============================================
                 SECTION 1.5: คุณภาพบริการวันนี้ (assets/js/home_quality.js)
                 ใช้ API ชุดเดียวกับหน้ารายละเอียด ตัวเลขจึงตรงกัน: OPD = api/opd_service_kpi.php (แท็บเวลารอ),
                 ER = api/er_los.php (สถานะตอนนี้) + er_d2d.php + er_today_summary.php
                 ============================================ -->
            <div class="row mt-1">

                <div class="col-12 mb-2">
                    <h5 class="text-success mb-0" style="font-weight:bold;">
                        <i class="fa-solid fa-stopwatch"></i>
                        คุณภาพบริการวันนี้
                    </h5>
                </div>

                <div class="col-xl-7 col-12 mb-2">
                    <section class="q-panel q-opd" aria-labelledby="q-opd-title">
                        <header class="q-head">
                            <div class="q-title">
                                <i class="fa-solid fa-stethoscope" aria-hidden="true"></i>
                                <div>
                                    <h6 id="q-opd-title">เวลารอ OPD <?= home_info_btn('q_opd', 'เวลารอ OPD') ?></h6>
                                    <p>นาทีเฉลี่ยต่อราย เทียบเป้าหมาย</p>
                                </div>
                            </div>
                            <a class="q-go" href="pages/opd_detail.php">ดูหน้า OPD <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
                        </header>
                        <div class="q-grid" id="q-opd" aria-live="polite"><p class="q-msg">กำลังโหลด…</p></div>
                    </section>
                </div>

                <div class="col-xl-5 col-12 mb-2">
                    <section class="q-panel q-er" aria-labelledby="q-er-title">
                        <header class="q-head">
                            <div class="q-title">
                                <i class="fa-solid fa-truck-medical" aria-hidden="true"></i>
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

            </div>

            <!-- ============================================
                 SECTION 3: สถานะเตียงและบริการวันนี้
                 ============================================ -->
            <div class="row mt-2">
                <div class="col-12 mb-2">
                    <h5 class="text-success mb-0" style="font-weight:bold;">
                        <i class="fa-solid fa-bed-pulse"></i>
                        สถานะเตียงและบริการวันนี้ <?= home_info_btn('wards', 'สถานะเตียงรายหอผู้ป่วย') ?>
                    </h5>
                </div>

            </div>

            <div class="row mt-1">

                <div class="col-12">

                    <div class="card shadow-lg border-white">

                        <div class="card-body">

                            <!-- BED STATUS -->
                            <div class="row mt-2">

                                <!-- =========================
                                    LEFT SUMMARY
                                    ========================== -->
                                <div class="col-xl-3 col-lg-4 col-12 mb-4">

                                    <div class="bed-summary-card">

                                        <div class="summary-header">

                                            <div class="summary-icon">

                                                <i class="fa-solid fa-chart-simple"></i>

                                            </div>

                                            <div>

                                                <div class="summary-title">

                                                    สถิติผู้ป่วยในวันนี้ <?= home_info_btn('beds', 'สถิติผู้ป่วยในวันนี้') ?>

                                                </div>

                                                <div class="summary-sub">

                                                    จำนวนเตียงทั้งหมด
                                                    <span id="bedcount">
                                                        Loading…
                                                    </span>
                                                    เตียง

                                                </div>

                                            </div>

                                        </div>

                                        <div class="summary-list">

                                            <div class="summary-item">

                                                <span>อัตราครองเตียงขณะนี้</span>

                                                <span class="badge bg-danger" id="occupancy">
                                                    Loading…
                                                </span>

                                            </div>

                                            <div class="summary-item">

                                                <span>รับใหม่วันนี้</span>

                                                <span class="badge bg-success" id="admittoday">
                                                    Loading…
                                                </span>

                                            </div>

                                            <div class="summary-item">

                                                <span>Admit อยู่</span>

                                                <span class="badge bg-success" id="wtotal">
                                                    Loading…
                                                </span>

                                            </div>

                                            <div class="summary-item">

                                                <span>เตียงว่าง</span>

                                                <span class="badge bg-success" id="wblank">
                                                    Loading…
                                                </span>

                                            </div>

                                            <div class="summary-item">

                                                <span>จำหน่ายวันนี้</span>

                                                <span class="badge bg-success" id="dchtoday">
                                                    Loading…
                                                </span>

                                            </div>

                                            <div class="summary-item">

                                                <span>สิทธิ์ชำระเงินและเบิกได้</span>

                                                <span class="badge bg-info" id="mo">
                                                    Loading…
                                                </span>

                                            </div>

                                            <div class="summary-item">

                                                <span>สิทธิ์ UC</span>

                                                <span class="badge bg-info" id="uc">
                                                    Loading…
                                                </span>

                                            </div>

                                            <div class="summary-item">

                                                <span>สิทธิ์อื่นๆ</span>

                                                <span class="badge bg-info" id="ot">
                                                    Loading…
                                                </span>

                                            </div>

                                        </div>

                                    </div>

                                </div>

                                <!-- =========================
                                    CENTER WARD
                                    ========================== -->
                                <div class="col-xl-6 col-lg-8 col-12 mb-4">

                                    <div class="row" id="ward-container">

                                    </div>

                                </div>

                                <!-- =========================
                                    RIGHT OTHER SERVICE
                                    ========================== -->
                                <div class="col-xl-3 col-lg-12 col-12 mb-3">

                                    <div class="service-summary-card">

                                        <div class="service-header">

                                            <i class="fa-solid fa-hospital"></i>

                                            สถิติการใช้บริการอื่นๆ วันนี้ <?= home_info_btn('services', 'สถิติการใช้บริการอื่นๆ') ?>

                                        </div>

                                        <div id="service-container">

                                            <div class="service-item">

                                                <div class="service-name">

                                                    <i class="fa-solid fa-video"></i>
                                                    ห้องส่องกล้อง

                                                </div>

                                                <div class="service-total" id="scope_total">

                                                    Loading…

                                                </div>

                                            </div>

                                            <div class="service-item">

                                                <div class="service-name">

                                                    <i class="fa-solid fa-mask-face"></i>
                                                    ห้องผ่าตัด

                                                </div>

                                                <div class="service-total" id="or_total">

                                                    Loading…

                                                </div>

                                            </div>

                                            <div class="service-item">

                                                <div class="service-name">

                                                    <i class="fa-solid fa-person-walking"></i>
                                                    กายภาพบำบัด

                                                </div>

                                                <div class="service-total" id="pt_total">

                                                    Loading…

                                                </div>

                                            </div>

                                            <div class="service-item">

                                                <div class="service-name">

                                                    <i class="fa-solid fa-mortar-pestle"></i>
                                                    แพทย์แผนไทย

                                                </div>

                                                <div class="service-total" id="thai_total">

                                                    Loading…

                                                </div>

                                            </div>

                                            <div class="service-item">

                                                <div class="service-name">

                                                    <i class="fa-solid fa-tooth"></i>
                                                    ทันตกรรม

                                                </div>

                                                <div class="service-total" id="dent_total">

                                                    Loading…

                                                </div>

                                            </div>

                                            <div class="service-item">

                                                <div class="service-name">

                                                    <i class="fa-solid fa-droplet"></i>
                                                    ไตเทียม

                                                </div>

                                                <div class="service-total" id="hemo_total">

                                                    Loading…

                                                </div>

                                            </div>
                                            <div class="service-item">

                                                <div class="service-name">

                                                    <i class="fa-solid fa-syringe"></i>
                                                    ห้องทำแผล-ฉีดยา

                                                </div>

                                                <div class="service-total" id="wound_total">

                                                    Loading…

                                                </div>

                                            </div>
                                            <div class="service-item">

                                                <div class="service-name">

                                                    <i class="fa-solid fa-wave-square"></i>
                                                    อัลตราซาวน์

                                                </div>

                                                <div class="service-total" id="us_total">

                                                    Loading…

                                                </div>

                                            </div>

                                            <div class="service-item">

                                                <div class="service-name">

                                                    <i class="fa-solid fa-user-clock"></i>
                                                    Observe ER

                                                </div>

                                                <div class="service-total" id="observe_total">

                                                    Loading…

                                                </div>

                                            </div>

                                            <div class="service-item">

                                                <div class="service-name">

                                                    <i class="fa-solid fa-x-ray"></i>
                                                    X-Ray

                                                </div>

                                                <div class="service-total" id="xray_total">

                                                    Loading…

                                                </div>

                                            </div>

                                            <div class="service-item">

                                                <div class="service-name">

                                                    <i class="fa-solid fa-person-pregnant"></i>
                                                    LR

                                                </div>

                                                <div class="service-total" id="lr_total">

                                                    Loading…

                                                </div>

                                            </div>
                                            <div class="service-item">

                                                <div class="service-name">

                                                    <i class="fa-solid fa-flask-vial"></i>
                                                    Lab

                                                </div>

                                                <div class="service-total" id="lab_total">

                                                    Loading…

                                                </div>
                                            </div>

                                            <div class="service-item">

                                                <div class="service-name">

                                                    <i class="fa-solid fa-circle-radiation"></i>
                                                    CT Scan

                                                </div>

                                                <div class="service-total" id="ct_total">

                                                    Loading…

                                                </div>

                                            </div>

                                            <div class="service-item">

                                                <div class="service-name">

                                                    <i class="fa-solid fa-cash-register"></i>
                                                    ห้องเก็บเงิน

                                                </div>

                                                <div class="service-total" id="cashier_total">

                                                    Loading…

                                                </div>

                                            </div>

                                            <div class="service-item">

                                                <div class="service-name">

                                                    <i class="fa-solid fa-scale-balanced"></i>
                                                    ศูนย์พิทักษ์สิทธิ์

                                                </div>

                                                <div class="service-total" id="rights_total">

                                                    Loading…

                                                </div>

                                            </div>

                                        </div>

                                        <!-- แสดงเมื่อมีรายการเกิน 10 และยังเลื่อนไม่สุด (fitServiceList) -->
                                        <p class="service-more" id="service-more" hidden>
                                            <i class="fa-solid fa-angles-down" aria-hidden="true"></i> เลื่อนลงดูอีก <b>0</b> รายการ
                                        </p>

                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>

            </div>

            <!-- ============================================
                 SECTION 2: Heatmap ภาระงานแต่ละแผนก
                 ============================================ -->
            <div class="row mt-1">

                <div class="col-12 mb-2">
                    <h5 class="text-success mb-0" style="font-weight:bold;">
                        <i class="fa-solid fa-fire-flame-curved"></i>
                        ภาระงานแต่ละแผนกตามช่วงเวลา วันนี้ <?= home_info_btn('heatmap', 'ภาระงานแต่ละแผนก') ?>
                    </h5>
                </div>

                <div class="col-12 mb-2">
                    <div class="card shadow-sm">
                        <div class="card-body">

                            <!-- KPI mini cards -->
                            <div class="row mb-3">

                                <div class="col-lg-3 col-6 mb-2">
                                    <div class="summary-card">
                                        <div class="summary-title" style="font-size:1.3rem;color:#c2410c">
                                            <i class="fa-solid fa-clock"></i> ชั่วโมงที่แออัดสุด
                                        </div>
                                        <div class="summary-number" style="font-size:2.2rem;color:#c2410c">
                                            <span id="hm-peak-hour">—</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-lg-3 col-6 mb-2">
                                    <div class="summary-card">
                                        <div class="summary-title" style="font-size:1.3rem;color:#b02a37">
                                            <i class="fa-solid fa-hospital"></i> แผนกที่แออัดสุดวันนี้
                                        </div>
                                        <div class="summary-number" style="font-size:2rem;color:#b02a37">
                                            <span id="hm-peak-dept">—</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-lg-6 col-12 mb-2 d-flex align-items-end justify-content-end">
                                    <button type="button" id="hm-toggle" class="btn btn-outline-success" aria-controls="heatmap-chart" hidden></button>
                                </div>

                            </div>

                            <!-- Heatmap chart -->
                            <div id="heatmap-chart" style="min-height:400px;"></div>

                        </div>
                    </div>
                </div>

            </div>

        </div>

    </section>

    <!-- รายละเอียดเมื่อคลิกบริการในการ์ด "สถิติการใช้บริการอื่นๆ วันนี้" (assets/js/service_detail.js · รายชื่อต้อง login) -->
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
// เรียกหลังโหลดข้อมูลสำเร็จเท่านั้น ถ้า API ล่ม เวลาจะค้างไว้ที่ครั้งล่าสุดที่ได้ข้อมูลจริง
function markUpdated() {

    const now = new Date();

    document.getElementById('last-updated').textContent =
        'ข้อมูล ณ วันที่ ' +
        now.toLocaleDateString('th-TH', { day: 'numeric', month: 'long', year: 'numeric' }) +
        ' เวลา ' +
        now.toLocaleTimeString('th-TH', { hour: '2-digit', minute: '2-digit', second: '2-digit' }) +
        ' น.';

    // จอเล็กซ่อนข้อความยาว ให้ดูเวลาได้จาก tooltip
    document.getElementById('live-status').title =
        document.getElementById('last-updated').textContent;

}

// ไอคอนตามชื่อ Ward ชุดเดียวกับเมนู IPD ใน templates/sidebar.php (ลำดับสำคัญ: "หลังคลอด" ต้องมาก่อน "คลอด")
const WARD_ICONS = [
    [/พิเศษ/, 'fa-door-open'],
    [/หลังคลอด|^pp/i, 'fa-person-breastfeeding'],
    [/ทารก|snb/i, 'fa-baby'],
    [/เด็ก/, 'fa-child'],
    [/stroke/i, 'fa-brain'],
    [/icu/i, 'fa-heart-pulse'],
    [/ศัลย/, 'fa-user-doctor'],
    [/หญิง/, 'fa-person-dress'],
    [/ชาย/, 'fa-person'],
    [/คลอด|^lr/i, 'fa-person-pregnant'],
    [/home/i, 'fa-house-user']
];

function wardIcon(name) {

    const hit = WARD_ICONS.find(([re]) => re.test(String(name || '').trim()));

    return hit ? hit[1] : 'fa-bed';

}

async function loadWardBed() {

    try {

        const response =
            await fetch(
                'api/index_ward_bed.php'
            );

        const data =
            await response.json();

        let html = '';

        // Home Ward (23) ผู้ป่วยนอนที่บ้าน ไม่นับรวมยอดเตียงทั้งหมด (api/bed_summary.php) — แสดงไว้ท้ายสุด
        const HOME_WARD = '23';
        data.sort((a, b) => (a.ward === HOME_WARD) - (b.ward === HOME_WARD));

        data.forEach(ward => {

            let colorClass = 'success';
            let statusText = '';
            // ป้ายข้อความคู่กับสี (ไม่สื่อด้วยสีอย่างเดียว) — เกณฑ์เดียวกับ colorClass ด้านล่าง
            const statusLabel = { success: 'ว่าง', info: 'ปานกลาง', warning: 'ใกล้เต็ม', danger: 'เต็ม/เกิน' };

            const rate =
                parseFloat(
                    ward.occupancy_rate
                );

            // กำหนดสีตาม %
            if (rate >= 100) {

                colorClass = 'danger';

            } else if (rate >= 75) {

                colorClass = 'warning';

            } else if (rate >= 50) {

                colorClass = 'info';

            }

            // สถานะเตียง
            if (
                parseInt(ward.admitnow) >=
                parseInt(ward.bedcount)
            ) {

                statusText = `
                    <span class="bed-full">
                        เต็ม
                    </span>
                `;

            } else {

                statusText = `
                    ว่าง ${ward.available_bed}
                `;

            }

            html += `

            <div class="col-xl-6 col-lg-6 col-12 mb-2">

                <div class="ward-card" tabindex="0" role="link">

                    <div class="ward-icon text-${colorClass}">

                        <i class="fa-solid ${wardIcon(ward.name)}"></i>

                    </div>

                    <div class="ward-name">

                        ${ward.name}

                    </div>

                    <div class="ward-detail">

                        Admit ${ward.admitnow}
                        / ${ward.bedcount} เตียง

                        (${statusText})

                    </div>
                    <div class="ward-percent">

                        <span>ครองเตียง <strong class="ward-rate-${colorClass}">${rate}%</strong></span>
                        <span class="ward-status ward-status-${colorClass}">${statusLabel[colorClass]}</span>

                    </div>

                    <div class="progress ward-progress">

                        <div
                            class="progress-bar bg-${colorClass}"
                            style="width:${Math.min(rate,100)}%"
                        >
                        </div>

                    </div>

                </div>

            </div>

            `;

        });

        document.getElementById(
            'ward-container'
        ).innerHTML = html;

        markUpdated();

    } catch (error) {

        console.error(error);

    }

}

loadWardBed();

bbhPoll(
    loadWardBed,
    60000
);

async function loadBedSummary() {

    const response =
        await fetch(
            'api/bed_summary.php'
        );

    const data =
        await response.json();

    document.getElementById(
            'bedcount'
        ).innerHTML =
        data.bedcount;

    document.getElementById(
            'admittoday'
        ).innerHTML =
        data.admittoday + ' ราย';

    document.getElementById(
            'wtotal'
        ).innerHTML =
        data.wtotal + ' ราย';

    document.getElementById(
            'wblank'
        ).innerHTML =
        data.wblank + ' เตียง';

    document.getElementById('dchtoday').innerHTML =
        data.dchtoday + ' ราย';

    document.getElementById('uc').innerHTML =
        data.uc + ' ราย';

    document.getElementById('mo').innerHTML =
        data.mo + ' ราย';

    document.getElementById('ot').innerHTML =
        data.ot + ' ราย';

    document.getElementById('occupancy').innerHTML =
        data.occupancy + '%';

    markUpdated();

}

loadBedSummary();

bbhPoll(
    loadBedSummary,
    60000
);

async function loadServiceSummary() {

    const response =
        await fetch('api/service_summary_today.php');

    const data =
        await response.json();

    // ตัวเลขแต่ละบริการ: key ของ API (<บริการ>_total) = id ในการ์ด (config/services.php)
    Object.entries(data).forEach(([id, n]) => {
        const el = document.getElementById(id);
        if (el) el.textContent = Number(n).toLocaleString('th-TH') + ' ราย';
    });

    // เรียงมากไปน้อย และทำให้รายการที่เป็น 0 เป็นสีเทา ให้เห็นบริการที่มีผู้ใช้ก่อน (sort แบบ stable: เท่ากันคงลำดับเดิม)
    const serviceBox = document.getElementById('service-container');
    [...serviceBox.children]
        .map(el => [el, parseInt(el.querySelector('.service-total').textContent.replace(/,/g, ''), 10) || 0])
        .sort((a, b) => b[1] - a[1])
        .forEach(([el, total]) => {
            el.classList.toggle('is-zero', total === 0);
            serviceBox.appendChild(el);
        });
    fitServiceList();

    markUpdated();

}

// การ์ดบริการแสดง 10 รายการแรก (เรียงมากไปน้อย) ที่เหลือเลื่อนดูในกรอบ — สูงพอดี 10 รายการ วัดจากหน้าจอจริง
const SERVICE_VISIBLE = 10;
function fitServiceList() {
    const box = document.getElementById('service-container');
    const items = box.children;
    const more = document.getElementById('service-more');
    const extra = items.length - SERVICE_VISIBLE;
    box.style.maxHeight = '';
    box.classList.toggle('is-scroll', extra > 0);
    if (extra > 0) {
        const last = items[SERVICE_VISIBLE - 1];
        box.style.maxHeight = (last.offsetTop + last.offsetHeight - items[0].offsetTop) + 'px';
    }
    if (more) {
        more.hidden = extra <= 0 || box.scrollTop + box.clientHeight >= box.scrollHeight - 4;
        more.querySelector('b').textContent = Math.max(extra, 0);
    }
}
document.getElementById('service-container').addEventListener('scroll', () => {
    const box = document.getElementById('service-container');
    document.getElementById('service-more').hidden = box.scrollTop + box.clientHeight >= box.scrollHeight - 4;
}, { passive: true });
window.addEventListener('resize', fitServiceList);

loadServiceSummary();

bbhPoll(
    loadServiceSummary,
    60000
);
</script>

<?php

include 'templates/footer.php';