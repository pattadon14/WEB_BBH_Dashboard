<?php

include '../templates/header.php';
include '../templates/navbar.php';
include '../templates/sidebar.php';

?>

<div class="content-wrapper">

    <section class="content pt-3">

        <div class="container-fluid">

            <div class="row">

                <!-- ════════════════════════════
                     LEFT SUMMARY
                ════════════════════════════ -->
                <div class="col-xl-3 col-lg-4 col-12">

                    <div class="card shadow-sm opd-summary-wrapper">

                        <div class="card-body p-3">

                            <!-- HEADER: ยอดรวมวันนี้ -->
                            <div class="opd-detail-header mb-3">
                                <div class="opd-summary-top">
                                    <i class="fa-solid fa-hospital-user"></i>
                                    <div>
                                        <div class="opd-summary-title">ผู้มารับบริการตรวจโรคทั่วไปวันนี้</div>
                                        <div class="opd-summary-total-label">จำนวนผู้มารับบริการ</div>
                                    </div>
                                </div>
                                <div class="opd-summary-total-number">
                                    <span id="opd_total_today">—</span>
                                    <small>คน</small>
                                </div>
                            </div>

                            <!-- ════════════════════════════
                                 กลุ่ม 1: ประเภทการมา
                            ════════════════════════════ -->
                            <div class="section-label mb-2">
                                <i class="fa-solid fa-users me-1"></i> ประเภทการมารับบริการ
                            </div>

                            <!-- Walk-in -->
                            <div class="summary-card walkin-card mb-2">
                                <div class="summary-title">
                                    <i class="fa-solid fa-person-walking"></i> Walk-in
                                </div>
                                <div class="summary-number">
                                    <span id="walkin_today">—</span>
                                    <span class="summary-unit">คน</span>
                                </div>
                                <div class="todaysummary-sub">มารับบริการโดยไม่มีนัดล่วงหน้า</div>
                            </div>

                            <!-- ═══ Appointment Combined Card ═══ -->
                            <div class="appoint-combined-card mb-3">

                                <!-- หัว: นัดทั้งหมด -->
                                <div class="appoint-combined-header">
                                    <i class="fa-solid fa-calendar-days me-1"></i>
                                    คนไข้ที่มีนัดหมายวันนี้ทั้งหมด
                                </div>
                                <div class="appoint-combined-total">
                                    <span id="appoint_today">—</span>
                                    <span class="summary-unit">คน</span>
                                </div>

                                <!-- แถวล่าง: มาตามนัด / ยังไม่มา -->
                                <div class="appoint-combined-row">

                                    <!-- มาตามนัด -->
                                    <div class="appoint-sub-box came">
                                        <div class="appoint-sub-label came">
                                            มาตามนัด
                                            <i class="fa-solid fa-square-check"
                                                style="color:#198754; font-size:18px;"></i>
                                        </div>
                                        <div class="appoint-sub-number came">
                                            <span id="oapp_success">—</span>
                                            <span class="appoint-sub-unit">คน</span>
                                        </div>
                                    </div>

                                    <!-- ไม่มาตามนัด -->
                                    <div class="appoint-sub-box miss">
                                        <div class="appoint-sub-label miss">
                                            ไม่มาตามนัด
                                            <i class="fa-solid fa-square-xmark"
                                                style="color:#dc3545;font-size:18px;"></i>
                                        </div>
                                        <div class="appoint-sub-number miss">
                                            <span id="miss_today">—</span>
                                            <span class="appoint-sub-unit">คน</span>
                                        </div>
                                    </div>

                                </div>

                            </div>

                            <!-- ════════════════════════════
                                 กลุ่ม 2: ช่วงเวลา
                            ════════════════════════════ -->
                            <div class="section-label mb-2">
                                <i class="fa-solid fa-clock me-1"></i> แยกตามช่วงเวลา
                            </div>

                            <div class="time-group mb-3">

                                <!-- ก่อนเวลาราชการ -->
                                <div class="time-card time-night">
                                    <div class="time-icon">
                                        <i class="fa-solid fa-moon"></i>
                                    </div>
                                    <div class="time-info">
                                        <div class="time-label">ก่อนเวลาราชการ</div>
                                        <div class="time-range">20:01 – 07:59 น.</div>
                                    </div>
                                    <div class="time-count">
                                        <span id="before_time">—</span>
                                        <span class="time-unit">คน</span>
                                    </div>
                                </div>

                                <!-- ในเวลาราชการ -->
                                <div class="time-card time-morning">
                                    <div class="time-icon">
                                        <i class="fa-solid fa-sun"></i>
                                    </div>
                                    <div class="time-info">
                                        <div class="time-label">ในเวลาราชการ</div>
                                        <div class="time-range">08:00 – 16:00 น.</div>
                                    </div>
                                    <div class="time-count">
                                        <span id="worktime">—</span>
                                        <span class="time-unit">คน</span>
                                    </div>
                                </div>

                                <!-- นอกเวลาราชการ -->
                                <div class="time-card time-evening">
                                    <div class="time-icon">
                                        <i class="fa-solid fa-cloud-sun"></i>
                                    </div>
                                    <div class="time-info">
                                        <div class="time-label">นอกเวลาราชการ</div>
                                        <div class="time-range">16:00 – 20:00 น.</div>
                                    </div>
                                    <div class="time-count">
                                        <span id="after_time">—</span>
                                        <span class="time-unit">คน</span>
                                    </div>
                                </div>

                            </div>

                            <!-- ════════════════════════════
                                 กลุ่ม 3: สถานะการตรวจ
                            ════════════════════════════ -->
                            <div class="section-label mb-2">
                                <i class="fa-solid fa-stethoscope me-1"></i> สถานะการตรวจ
                            </div>

                            <!-- รอซักประวัติ -->
                            <div class="summary-card wait-card mb-2">
                                <div class="summary-title">
                                    <i class="fa-solid fa-user-nurse"></i> รอซักประวัติ
                                </div>
                                <div class="summary-number">
                                    <span id="wait_triage">—</span>
                                    <span class="summary-unit">คน</span>
                                </div>
                                <div class="todaysummary-sub">กำลังรอซักประวัติและคัดกรอง</div>
                            </div>

                            <!-- รอตรวจ -->
                            <div class="summary-card exam-card mb-2">
                                <div class="summary-title">
                                    <i class="fa-solid fa-stethoscope"></i> รอตรวจ
                                </div>
                                <div class="summary-number">
                                    <span id="wait_exam">—</span>
                                    <span class="summary-unit">คน</span>
                                </div>
                                <div class="todaysummary-sub">กำลังรอพบแพทย์</div>
                            </div>

                            <!-- ตรวจเสร็จแล้ว -->
                            <div class="summary-card finish-card">
                                <div class="summary-title">
                                    <i class="fa-solid fa-circle-check"></i> ตรวจเสร็จแล้ว
                                </div>
                                <div class="summary-number">
                                    <span id="finish_exam">—</span>
                                    <span class="summary-unit">คน</span>
                                </div>
                                <div class="todaysummary-sub">ตรวจเสร็จและออกจากระบบแล้ว</div>
                            </div>

                        </div>

                    </div>

                </div>

                <!-- ════════════════════════════
                     RIGHT CONTENT
                ════════════════════════════ -->
                <div class="col-xl-9 col-lg-8 col-12">

                    <div class="card shadow-sm mb-3">

                        <div class="opd-chart">
                            <div class="row align-items-center">
                                <div class="col-md-7">
                                    <h2 class="mb-1 text-success" style="font-weight: bold;">
                                        <i class="fa-solid fa-chart-column"></i>
                                        ข้อมูลสถิติผู้มารับบริการ
                                    </h2>
                                    <div id="budget-date-text" class="text-success fs-5"></div>
                                </div>
                                <div class="col-md-5 col-lg-4">
                                    <div class="budget-select-wrapper">
                                        <select id="budget-year" class="form-control budget-select">
                                            <?php
                                            $currentYear = date('Y') + 543;
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

                        <div class="card shadow-sm">
                            <div class="card-body">
                                <div id="opd-chart" style="height:470px;"></div>
                                <hr class="my-2">
                                <div class="pttype-header">
                                    <i class="fa-solid fa-chart-column"></i>
                                    ผู้ใช้บริการ OPD แยกตามสิทธิ์ วันนี้
                                </div>
                                <div id="pttype-chart" class="pttype-chart"></div>
                            </div>
                        </div>

                    </div>

                </div>

            </div>

        </div>

    </section>

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