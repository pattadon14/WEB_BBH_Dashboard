<?php

include '../templates/header.php';
include '../templates/navbar.php';
include '../templates/sidebar.php';

?>

<div class="content-wrapper">

    <section class="content pt-3">

        <div class="container-fluid">

            <!-- HEADER -->
            <div class="card shadow-sm mb-3">

                <div class="opd-chart">

                    <div class="row align-items-center">

                        <!-- TITLE -->
                        <div class="col-md-7">

                            <h2 class="mb-1 text-success" style="font-weight: bold;">
                                <i class="fa-solid fa-chart-column"></i>
                                ข้อมูลสถิติ งานอุบัติเหตุและฉุกเฉิน
                            </h2>

                            <div id="budget-date-text" class="text-success fs-5"></div>

                        </div>

                        <!-- SELECT -->
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

                <!-- GRAPHS -->
                <div class="card">

                    <div class="card-body">

                        <div class="row">

                            <!-- Bar Chart (ซ้าย) -->
                            <div class="col-xl-6 col-lg-12 mb-2">

                                <div class="chart-box">

                                    <h5 class="chart-title">
                                        <i class="fa-solid fa-hospital-user"></i>
                                        จำนวนผู้ป่วยอุบัติเหตุ
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
                                                    <th>ชื่อโรค (ไทย)</th>
                                                    <th>การวินิจฉัย (EN)</th>
                                                    <th class="text-center" style="width:90px">รวม</th>
                                                    <th class="text-center" style="width:80px">
                                                        <i class="fa-solid fa-mars" style="color:#3b82f6"></i>
                                                        ชาย
                                                    </th>
                                                    <th class="text-center" style="width:80px">
                                                        <i class="fa-solid fa-venus" style="color:#ec4899"></i>
                                                        หญิง
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody id="er-top10-tbody">
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

                            </div>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </section>

</div>

<!-- HIGHCHART -->
<script src="https://code.highcharts.com/highcharts.js"></script>
<script src="<?= BASE_URL ?>assets/js/ER_detail.js"></script>

<?php include '../templates/footer.php'; ?>