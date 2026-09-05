<?php

include '../templates/header.php';
include '../templates/navbar.php';
include '../templates/sidebar.php';

?>

<div class="content-wrapper">

    <section class="content pt-3">

        <div class="container-fluid">

            <!-- ════════════════════════════
                     LEFT SUMMARY
                ════════════════════════════ -->
            <div class="row">

                <!-- =====================================================
         TOP SUMMARY : แนวนอนเต็มความกว้าง
    ====================================================== -->
                <div class="col-12 mb-3">

                    <div class="card shadow-sm opd-summary-horizontal">

                        <div class="card-body">

                            <!-- =========================
                     HEADER / TOTAL
                ========================== -->
                            <div class="opd-summary-main">

                                <div class="opd-summary-main-icon">
                                    <i class="fa-solid fa-hospital-user"></i>
                                </div>

                                <div class="opd-summary-main-info">

                                    <div class="opd-summary-title">
                                        ผู้มารับบริการตรวจโรคทั่วไปวันนี้
                                    </div>

                                    <div class="opd-summary-total-label">
                                        จำนวนผู้มารับบริการ
                                    </div>

                                </div>

                                <div class="opd-summary-main-number">

                                    <span id="opd_total_today">—</span>

                                    <small>
                                        คน
                                    </small>

                                </div>

                            </div>


                            <!-- =================================================
                     SUMMARY CONTENT
                ================================================== -->
                            <div class="opd-summary-grid">


                                <!-- =========================
                         WALK-IN
                    ========================== -->
                                <div class="opd-summary-section">

                                    <div class="summary-section-title">
                                        <i class="fa-solid fa-users"></i>
                                        ประเภทการมารับบริการ
                                    </div>

                                    <div class="horizontal-summary-item walkin-card">

                                        <div class="summary-title">

                                            <i class="fa-solid fa-person-walking"></i>

                                            Walk-in

                                        </div>

                                        <div class="summary-number">

                                            <span id="walkin_today">—</span>

                                            <span class="summary-unit">
                                                คน
                                            </span>

                                        </div>

                                        <div class="todaysummary-sub">
                                            มารับบริการโดยไม่มีนัดล่วงหน้า
                                        </div>

                                    </div>

                                </div>


                                <!-- =========================
                         APPOINTMENT
                    ========================== -->
                                <div class="opd-summary-section">

                                    <div class="summary-section-title">

                                        <i class="fa-solid fa-calendar-days"></i>

                                        นัดหมายวันนี้

                                    </div>


                                    <div class="appointment-horizontal-card">

                                        <div class="appointment-total">

                                            <span id="appoint_today">
                                                —
                                            </span>

                                            <small>
                                                คน
                                            </small>

                                        </div>


                                        <div class="appointment-status">

                                            <!-- มาตามนัด -->
                                            <div class="appointment-status-item came">

                                                <div>

                                                    <i class="fa-solid fa-square-check"></i>

                                                    มาตามนัด

                                                </div>

                                                <strong id="oapp_success">
                                                    —
                                                </strong>

                                                <small>
                                                    คน
                                                </small>

                                            </div>


                                            <!-- ไม่มาตามนัด -->
                                            <div class="appointment-status-item miss">

                                                <div>

                                                    <i class="fa-solid fa-square-xmark"></i>

                                                    ไม่มาตามนัด

                                                </div>

                                                <strong id="miss_today">
                                                    —
                                                </strong>

                                                <small>
                                                    คน
                                                </small>

                                            </div>

                                        </div>

                                    </div>

                                </div>


                                <!-- =========================
                         TIME
                    ========================== -->
                                <div class="opd-summary-section">

                                    <div class="summary-section-title">

                                        <i class="fa-solid fa-clock"></i>

                                        แยกตามช่วงเวลา

                                    </div>


                                    <div class="time-horizontal-group">


                                        <!-- ก่อนเวลาราชการ -->
                                        <div class="time-horizontal-card time-night">

                                            <div class="time-icon">

                                                <i class="fa-solid fa-moon"></i>

                                            </div>

                                            <div class="time-info">

                                                <div class="time-label">
                                                    ก่อนเวลาราชการ
                                                </div>

                                                <div class="time-range">
                                                    20:01 – 07:59 น.
                                                </div>

                                            </div>

                                            <div class="time-count">

                                                <span id="before_time">
                                                    —
                                                </span>

                                                <small>
                                                    คน
                                                </small>

                                            </div>

                                        </div>


                                        <!-- ในเวลาราชการ -->
                                        <div class="time-horizontal-card time-morning">

                                            <div class="time-icon">

                                                <i class="fa-solid fa-sun"></i>

                                            </div>

                                            <div class="time-info">

                                                <div class="time-label">
                                                    ในเวลาราชการ
                                                </div>

                                                <div class="time-range">
                                                    08:00 – 16:00 น.
                                                </div>

                                            </div>

                                            <div class="time-count">

                                                <span id="worktime">
                                                    —
                                                </span>

                                                <small>
                                                    คน
                                                </small>

                                            </div>

                                        </div>


                                        <!-- นอกเวลาราชการ -->
                                        <div class="time-horizontal-card time-evening">

                                            <div class="time-icon">

                                                <i class="fa-solid fa-cloud-sun"></i>

                                            </div>

                                            <div class="time-info">

                                                <div class="time-label">
                                                    นอกเวลาราชการ
                                                </div>

                                                <div class="time-range">
                                                    16:00 – 20:00 น.
                                                </div>

                                            </div>

                                            <div class="time-count">

                                                <span id="after_time">
                                                    —
                                                </span>

                                                <small>
                                                    คน
                                                </small>

                                            </div>

                                        </div>

                                    </div>

                                </div>


                                <!-- =========================
                         EXAM STATUS
                    ========================== -->
                                <div class="opd-summary-section">

                                    <div class="summary-section-title">

                                        <i class="fa-solid fa-stethoscope"></i>

                                        สถานะการตรวจ

                                    </div>


                                    <div class="exam-horizontal-group">


                                        <!-- รอซักประวัติ -->
                                        <div class="exam-horizontal-card wait-card">

                                            <div class="summary-title">

                                                <i class="fa-solid fa-user-nurse"></i>

                                                รอซักประวัติ

                                            </div>

                                            <div class="summary-number">

                                                <span id="wait_triage">
                                                    —
                                                </span>

                                                <span class="summary-unit">
                                                    คน
                                                </span>

                                            </div>

                                        </div>


                                        <!-- รอตรวจ -->
                                        <div class="exam-horizontal-card exam-card">

                                            <div class="summary-title">

                                                <i class="fa-solid fa-stethoscope"></i>

                                                รอตรวจ

                                            </div>

                                            <div class="summary-number">

                                                <span id="wait_exam">
                                                    —
                                                </span>

                                                <span class="summary-unit">
                                                    คน
                                                </span>

                                            </div>

                                        </div>


                                        <!-- ตรวจเสร็จ -->
                                        <div class="exam-horizontal-card finish-card">

                                            <div class="summary-title">

                                                <i class="fa-solid fa-circle-check"></i>

                                                ตรวจเสร็จแล้ว

                                            </div>

                                            <div class="summary-number">

                                                <span id="finish_exam">
                                                    —
                                                </span>

                                                <span class="summary-unit">
                                                    คน
                                                </span>

                                            </div>

                                        </div>

                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>
                <div class="col-12 mb-3">

                    <div class="card shadow-sm opd-summary-horizontal">

                        <div class="card-body">

                            <!-- =========================
                     HEADER / TOTAL
                ========================== -->
                            <div class="opd-summary-main">

                                <div class="opd-summary-main-icon">
                                    <i class="fa-solid fa-hospital-user"></i>
                                </div>

                                <div class="opd-summary-main-info">

                                    <div class="opd-summary-title">
                                        ผู้มารับบริการตรวจโรคคลินิคพิเศษวันนี้
                                    </div>

                                    <div class="opd-summary-total-label">
                                        จำนวนผู้มารับบริการ
                                    </div>

                                </div>

                                <div class="opd-summary-main-number">

                                    <span id="opd_total_today"> loading... </span>

                                    <small>
                                        คน
                                    </small>

                                </div>

                            </div>


                            <!-- =================================================
                     SUMMARY CONTENT
                ================================================== -->
                            <div class="opd-summary-grid">

                                <!-- =========================
                         APPOINTMENT
                    ========================== -->
                                <div class="opd-summary-section">

                                    <div class="summary-section-title">

                                        <i class="fa-solid fa-calendar-days"></i>

                                        นัดหมายวันนี้

                                    </div>


                                    <div class="appointment-horizontal-card">

                                        <div class="appointment-total">

                                            <span id="appoint_today">
                                                loading...
                                            </span>

                                            <small>
                                                คน
                                            </small>

                                        </div>


                                        <div class="appointment-status">

                                            <!-- มาตามนัด -->
                                            <div class="appointment-status-item came">

                                                <div>

                                                    <i class="fa-solid fa-square-check"></i>

                                                    มาตามนัด

                                                </div>

                                                <strong id="oapp_success">
                                                    —
                                                </strong>

                                                <small>
                                                    คน
                                                </small>

                                            </div>


                                            <!-- ไม่มาตามนัด -->
                                            <div class="appointment-status-item miss">

                                                <div>

                                                    <i class="fa-solid fa-square-xmark"></i>

                                                    ไม่มาตามนัด

                                                </div>

                                                <strong id="miss_today">
                                                    —
                                                </strong>

                                                <small>
                                                    คน
                                                </small>

                                            </div>

                                        </div>

                                    </div>

                                </div>

                                <!-- =========================
                         EXAM STATUS
                    ========================== -->
                                <div class="opd-summary-section">

                                    <div class="summary-section-title">

                                        <i class="fa-solid fa-stethoscope"></i>

                                        สถานะการตรวจ

                                    </div>


                                    <div class="exam-horizontal-group">


                                        <!-- รอซักประวัติ -->
                                        <div class="exam-horizontal-card wait-card">

                                            <div class="summary-title">

                                                <i class="fa-solid fa-user-nurse"></i>

                                                รอซักประวัติ

                                            </div>

                                            <div class="summary-number">

                                                <span id="wait_triage">
                                                    loading...
                                                </span>

                                                <span class="summary-unit">
                                                    คน
                                                </span>

                                            </div>

                                        </div>


                                        <!-- รอตรวจ -->
                                        <div class="exam-horizontal-card exam-card">

                                            <div class="summary-title">

                                                <i class="fa-solid fa-stethoscope"></i>

                                                รอตรวจ

                                            </div>

                                            <div class="summary-number">

                                                <span id="wait_exam">
                                                    loading...
                                                </span>

                                                <span class="summary-unit">
                                                    คน
                                                </span>

                                            </div>

                                        </div>


                                        <!-- ตรวจเสร็จ -->
                                        <div class="exam-horizontal-card finish-card">

                                            <div class="summary-title">

                                                <i class="fa-solid fa-circle-check"></i>

                                                ตรวจเสร็จแล้ว

                                            </div>

                                            <div class="summary-number">

                                                <span id="finish_exam">
                                                    loading...
                                                </span>

                                                <span class="summary-unit">
                                                    คน
                                                </span>

                                            </div>

                                        </div>

                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>

                <!-- =====================================================
         CHART SECTION
    ====================================================== -->
                <div class="col-12">

                    <div class="card shadow-sm mb-3">

                        <div class="opd-chart">

                            <div class="row align-items-center">

                                <div class="col-md-7">

                                    <h2 class="mb-1 text-success" style="font-weight:bold;">

                                        <i class="fa-solid fa-chart-column"></i>

                                        ข้อมูลสถิติผู้มารับบริการ

                                    </h2>

                                    <div id="budget-date-text" class="text-success fs-5">
                                    </div>

                                </div>


                                <div class="col-md-5 col-lg-4">

                                    <div class="budget-select-wrapper">

                                        <select id="budget-year" class="form-control budget-select">

                                            <?php

                                $currentYear = date('Y') + 543;

                                for (
                                    $y = $currentYear;
                                    $y >= 2565;
                                    $y--
                                ) {

                                    echo "<option value='$y'>
                                            ปีงบประมาณ $y
                                          </option>";

                                }

                                ?>

                                        </select>

                                        <i class="fas fa-chevron-down budget-select-icon"></i>

                                    </div>

                                </div>

                            </div>

                        </div>


                        <!-- OPD MONTHLY CHART -->
                        <div class="card-body">

                            <div id="opd-chart" style="height:470px;">
                            </div>


                            <hr class="my-2">


                            <!-- PTTYPE -->
                            <div class="pttype-header">

                                <i class="fa-solid fa-chart-column"></i>

                                ผู้ใช้บริการ OPD แยกตามสิทธิ์ วันนี้

                            </div>


                            <div id="pttype-chart" class="pttype-chart">
                            </div>

                        </div>

                    </div>

                </div>

            </div>

            <!-- TOP 10 + AGE GROUP SECTION -->
            <div class="container-fluid mt-3 mb-3">
                <div class="row mb-3">
                    <div class="col-12">
                        <div class="card shadow-sm">
                            <div class="card-body">

                                <div class="chart-box">
                                    <h5 class="chart-title">
                                        <i class="fa-solid fa-trophy"></i>
                                        10 อันดับโรค/การวินิจฉัยผู้ป่วยนอก ปีงบประมาณ
                                    </h5>
                                    <div class="table-responsive">
                                        <table class="table table-hover table-ipd-top10">
                                            <thead>
                                                <tr>
                                                    <th class="text-center" style="width:60px">อันดับ</th>
                                                    <th class="text-center" style="width:100px">ICD-10</th>
                                                    <th>ชื่อโรค (ไทย)</th>
                                                    <th>การวินิจฉัย (EN)</th>
                                                    <th class="text-center" style="width:90px">รวม</th>
                                                    <th class="text-center" style="width:80px">
                                                        <i class="fa-solid fa-mars" style="color:#3b82f6"></i> ชาย
                                                    </th>
                                                    <th class="text-center" style="width:80px">
                                                        <i class="fa-solid fa-venus" style="color:#ec4899"></i> หญิง
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody id="opd-top10-tbody">
                                                <tr>
                                                    <td colspan="7" class="text-center text-muted py-4">
                                                        <i class="fa-solid fa-spinner fa-spin me-2"></i>
                                                        กำลังโหลดข้อมูล...
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <div class="chart-box mt-4">
                                    <h5 class="chart-title">
                                        <i class="fa-solid fa-users"></i>
                                        กลุ่มอายุผู้ป่วย OPD ปีงบประมาณ
                                    </h5>
                                    <div class="row mb-3" id="opd-age-summary"></div>
                                    <div id="opd-age-chart" style="height: 360px;"></div>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <script src="https://code.highcharts.com/highcharts.js"></script>
            <script src="<?= BASE_URL ?>assets/js/opd_detail.js"></script>

            <?php include '../templates/footer.php'; ?>