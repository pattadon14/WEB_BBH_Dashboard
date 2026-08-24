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
                                ข้อมูลสถิติผู้ป่วยใน Admit
                            </h2>

                            <div id="budget-date-text" class="text-success fs-5">

                            </div>

                        </div>

                        <!-- SELECT -->
                        <div class="col-md-5 col-lg-4">

                            <div class="budget-select-wrapper">

                                <select id="budget-year" class="form-control budget-select">

                                    <?php

                                    $currentYear =
                                        date('Y') + 543;

                                    for (
                                        $y = $currentYear;
                                        $y >= 2565;
                                        $y--
                                    ) {

                                        echo "

                                                <option value='$y'>

                                                    ปีงบประมาณ $y

                                                </option>

                                                ";
                                    }

                                    ?>

                                </select>

                                <i class="fas fa-chevron-down budget-select-icon"></i>

                            </div>

                        </div>

                    </div>

                </div>

                <!-- GRAPH -->
                <div class="card shadow-sm">

                    <div class="card-body">

                        <div class="row">

                            <!-- จำนวน Admit -->
                            <div class="col-xl-6 col-lg-12 mb-3">

                                <div class="chart-box">

                                    <h5 class="chart-title">
                                        <i class="fa-solid fa-hospital-user"></i>
                                        จำนวนผู้ป่วยใน Admit
                                    </h5>

                                    <div id="ipd-chart"></div>

                                </div>

                            </div>

                            <!-- อัตราการครองเตียง -->
                            <div class="col-xl-6 col-lg-12 mb-3">

                                <div class="chart-box">

                                    <h5 class="chart-title">
                                        <i class="fa-solid fa-bed"></i>
                                        อัตราการครองเตียง
                                    </h5>

                                    <div id="bed-occupancy-chart"></div>

                                </div>

                            </div>

                        </div>
                        <!-- TOP 10 IPD DISEASE -->

                        <div class="row mt-3">

                            <div class="col-12">

                                <div class="chart-box">

                                    <h5 class="chart-title">
                                        <i class="fa-solid fa-trophy"></i>
                                        10 อันดับโรคผู้ป่วยใน
                                    </h5>

                                    <div class="table-responsive">

                                        <table class="table table-hover table-ipd-top10">
                                            <thead>
                                                <tr>
                                                    <th class="text-center" style="width:60px">อันดับ</th>
                                                    <th class="text-center" style="width:100px">ICD-10</th>
                                                    <th>การวินิจฉัย (EN)</th>
                                                    <th>ชื่อโรค (ไทย)</th>
                                                    <th class="text-center" style="width:80px">
                                                        <i class="fa-solid fa-mars" style="color:#3b82f6"></i> ชาย
                                                    </th>
                                                    <th class="text-center" style="width:80px">
                                                        <i class="fa-solid fa-venus" style="color:#ec4899"></i> หญิง
                                                    </th>
                                                    <th class="text-center" style="width:90px">รวม</th>
                                                </tr>
                                            </thead>

                                            <tbody id="top10-ipd-body">

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
<script src="<?= BASE_URL ?>assets/js/ipd_detail.js"></script>

<?php

include '../templates/footer.php';

?>