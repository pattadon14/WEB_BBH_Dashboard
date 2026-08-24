<?php

include 'templates/header.php';
include 'templates/navbar.php';
include 'templates/sidebar.php';

?>

<div class="content-wrapper">

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

                    <div class="small-box elevation-3">

                        <div class="inner">

                            <h3 id="opd-total">
                                Loading...
                            </h3>

                            <p class="kpi-title mb-0">
                                ผู้มารับบริการผู้ป่วยนอกวันนี้
                            </p>

                            <small class="kpi-sub">

                                (เดือนนี้

                                <span id="opd-month-patient">
                                    Loading...
                                </span>

                                คน /

                                <span id="opd-month-visit">
                                    Loading...
                                </span>

                                ครั้ง)

                            </small>


                        </div>

                        <div class="icon">

                            <i class="fas fa-stethoscope"></i>

                        </div>

                        <a href="pages/opd_detail.php" class="small-box-footer">

                            รายละเอียด
                            <i class="fas fa-arrow-circle-right"></i>

                        </a>

                    </div>

                </div>
                <div class="col-lg-4 col-md-6 col-12 mb-2">

                    <div class="small-box elevation-3">

                        <div class="inner">

                            <h3 id="ipd-total">
                                Loading...
                            </h3>

                            <p class="kpi-title mb-0">
                                Admit วันนี้
                            </p>

                            <small class="kpi-sub">

                                (เดือนนี้

                                <span id="ipd-month-patient">
                                    Loading...
                                </span>

                                คน /

                                <span id="ipd-month-visit">
                                    Loading...
                                </span>

                                ครั้ง)

                            </small>

                        </div>

                        <div class="icon">

                            <i class="fas fa-bed"></i>

                        </div>
                        <a href="pages/ipd_detail.php" class="small-box-footer">

                            รายละเอียด
                            <i class="fas fa-arrow-circle-right"></i>

                        </a>

                    </div>

                </div>
                <div class="col-lg-4 col-md-6 col-12 mb-2">

                    <div class="small-box elevation-3">

                        <div class="inner">

                            <h3 id="er-total">
                                Loading...
                            </h3>

                            <p class="kpi-title mb-0">
                                ER วันนี้
                            </p>

                            <small class="kpi-sub">

                                (เดือนนี้

                                <span id="er-month-patient">
                                    Loading...
                                </span>

                                คน /

                                <span id="er-month-visit">
                                    Loading...
                                </span>

                                ครั้ง)

                            </small>

                        </div>

                        <div class="icon">

                            <i class="fas fa-ambulance"></i>

                        </div>
                        <a href="pages/ER_detail.php" class="small-box-footer">

                            รายละเอียด
                            <i class="fas fa-arrow-circle-right"></i>

                        </a>

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
                        ภาระงานแต่ละแผนกตามช่วงเวลา วันนี้
                    </h5>
                </div>

                <div class="col-12 mb-2">
                    <div class="card shadow-sm">
                        <div class="card-body">

                            <!-- KPI mini cards -->
                            <div class="row mb-3">

                                <div class="col-lg-3 col-6 mb-2">
                                    <div class="summary-card" style="border-left:5px solid #fd7e14">
                                        <div class="summary-title" style="font-size:1.3rem;color:#fd7e14">
                                            <i class="fa-solid fa-clock"></i> ชั่วโมงที่แออัดสุด
                                        </div>
                                        <div class="summary-number" style="font-size:2.2rem;color:#fd7e14">
                                            <span id="hm-peak-hour">—</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-lg-3 col-6 mb-2">
                                    <div class="summary-card" style="border-left:5px solid #dc3545">
                                        <div class="summary-title" style="font-size:1.3rem;color:#dc3545">
                                            <i class="fa-solid fa-hospital"></i> แผนกที่แออัดสุดวันนี้
                                        </div>
                                        <div class="summary-number" style="font-size:2rem;color:#dc3545">
                                            <span id="hm-peak-dept">—</span>
                                        </div>
                                    </div>
                                </div>

                            </div>

                            <!-- Heatmap chart -->
                            <div id="heatmap-chart" style="min-height:400px;"></div>

                        </div>
                    </div>
                </div>

            </div>

            <!-- ============================================
                 SECTION 3: สถานะเตียงและบริการวันนี้
                 ============================================ -->
            <div class="row mt-2">
                <div class="col-12 mb-2">
                    <h5 class="text-success mb-0" style="font-weight:bold;">
                        <i class="fa-solid fa-bed-pulse"></i>
                        สถานะเตียงและบริการวันนี้
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

                                                <i class="fa-solid fa-square-poll-vertical"></i>

                                            </div>

                                            <div>

                                                <div class="summary-title">

                                                    สถิติผู้ป่วยในวันนี้

                                                </div>

                                                <div class="summary-sub">

                                                    จำนวนเตียงทั้งหมด
                                                    <span id="bedcount">
                                                        Loading...
                                                    </span>
                                                    เตียง

                                                </div>

                                            </div>

                                        </div>

                                        <div class="summary-list">

                                            <div class="summary-item">

                                                <span>อัตราครองเตียง</span>

                                                <span class="badge bg-danger" id="occupancy">
                                                    Loading...
                                                </span>

                                            </div>

                                            <div class="summary-item">

                                                <span>รับใหม่วันนี้</span>

                                                <span class="badge bg-success" id="admittoday">
                                                    Loading...
                                                </span>

                                            </div>

                                            <div class="summary-item">

                                                <span>Admit อยู่</span>

                                                <span class="badge bg-success" id="wtotal">
                                                    Loading...
                                                </span>

                                            </div>

                                            <div class="summary-item">

                                                <span>เตียงว่าง</span>

                                                <span class="badge bg-success" id="wblank">
                                                    Loading...
                                                </span>

                                            </div>

                                            <div class="summary-item">

                                                <span>จำหน่ายวันนี้</span>

                                                <span class="badge bg-success" id="dchtoday">
                                                    Loading...
                                                </span>

                                            </div>

                                            <div class="summary-item">

                                                <span>สิทธิ์ชำระเงินและเบิกได้</span>

                                                <span class="badge bg-info" id="mo">
                                                    Loading...
                                                </span>

                                            </div>

                                            <div class="summary-item">

                                                <span>สิทธิ์ UC</span>

                                                <span class="badge bg-info" id="uc">
                                                    Loading...
                                                </span>

                                            </div>

                                            <div class="summary-item">

                                                <span>สิทธิ์อื่นๆ</span>

                                                <span class="badge bg-info" id="ot">
                                                    Loading...
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

                                            สถิติการใช้บริการอื่นๆ วันนี้

                                        </div>

                                        <div id="service-container">

                                            <div class="service-item">

                                                <div class="service-name">

                                                    <i class="fa-solid fa-microscope"></i>
                                                    ห้องส่องกล้อง

                                                </div>

                                                <div class="service-total" id="scope_total">

                                                    Loading...

                                                </div>

                                            </div>

                                            <div class="service-item">

                                                <div class="service-name">

                                                    <i class="fa-solid fa-user-doctor"></i>
                                                    ห้องผ่าตัด

                                                </div>

                                                <div class="service-total" id="or_total">

                                                    Loading...

                                                </div>

                                            </div>

                                            <div class="service-item">

                                                <div class="service-name">

                                                    <i class="fa-solid fa-dumbbell"></i>
                                                    กายภาพบำบัด

                                                </div>

                                                <div class="service-total" id="pt_total">

                                                    Loading...

                                                </div>

                                            </div>

                                            <div class="service-item">

                                                <div class="service-name">

                                                    <i class="fa-solid fa-leaf"></i>
                                                    แพทย์แผนไทย

                                                </div>

                                                <div class="service-total" id="thai_total">

                                                    Loading...

                                                </div>

                                            </div>

                                            <div class="service-item">

                                                <div class="service-name">

                                                    <i class="fa-solid fa-tooth"></i>
                                                    ทันตกรรม

                                                </div>

                                                <div class="service-total" id="dent_total">

                                                    Loading...

                                                </div>

                                            </div>

                                            <div class="service-item">

                                                <div class="service-name">

                                                    <i class="fa-solid fa-droplet"></i>
                                                    ไตเทียม

                                                </div>

                                                <div class="service-total" id="hemo_total">

                                                    Loading...

                                                </div>

                                            </div>
                                            <div class="service-item">

                                                <div class="service-name">

                                                    <i class="fa-solid fa-bandage"></i>
                                                    ห้องทำแผล-ฉีดยา

                                                </div>

                                                <div class="service-total" id="wound_total">

                                                    Loading...

                                                </div>

                                            </div>
                                            <div class="service-item">

                                                <div class="service-name">

                                                    <i class="fa-solid fa-wave-square"></i>
                                                    อัลตราซาวน์

                                                </div>

                                                <div class="service-total" id="us_total">

                                                    Loading...

                                                </div>

                                            </div>

                                            <div class="service-item">

                                                <div class="service-name">

                                                    <i class="fa-solid fa-bed-pulse"></i>
                                                    Observe ER

                                                </div>

                                                <div class="service-total" id="observe_total">

                                                    Loading...

                                                </div>

                                            </div>

                                            <div class="service-item">

                                                <div class="service-name">

                                                    <i class="fa-solid fa-x-ray"></i>
                                                    X-Ray

                                                </div>

                                                <div class="service-total" id="xray_total">

                                                    Loading...

                                                </div>

                                            </div>

                                            <div class="service-item">

                                                <div class="service-name">

                                                    <i class="fa-solid fa-baby"></i>
                                                    LR

                                                </div>

                                                <div class="service-total" id="lr_total">

                                                    Loading...

                                                </div>

                                            </div>
                                            <div class="service-item">

                                                <div class="service-name">

                                                    <i class="fa-solid fa-flask-vial"></i>
                                                    Lab

                                                </div>

                                                <div class="service-total" id="lab_total">

                                                    Loading...

                                                </div>
                                            </div>

                                            <div class="service-item">

                                                <div class="service-name">

                                                    <i class="fa-solid fa-circle-radiation"></i>
                                                    CT Scan

                                                </div>

                                                <div class="service-total" id="ct_total">

                                                    Loading...

                                                </div>

                                            </div>

                                        </div>

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

<script src="https://code.highcharts.com/highcharts.js"></script>
<script src="https://code.highcharts.com/modules/heatmap.js"></script>
<script src="assets/js/dashboard.js"></script>
<script>
async function loadWardBed() {

    try {

        const response =
            await fetch(
                'api/index_ward_bed.php'
            );

        const data =
            await response.json();

        let html = '';

        data.forEach(ward => {

            let colorClass = 'success';
            let statusText = '';

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

                <div class="ward-card border-${colorClass}">

                    <div class="ward-icon text-${colorClass}">

                        <i class="fas fa-bed"></i>

                    </div>

                    <div class="ward-name text-${colorClass}">

                        ${ward.name}

                    </div>

                    <div class="ward-detail">

                        Admit ${ward.admitnow}
                        / ${ward.bedcount} เตียง

                        (${statusText})

                    </div>

                    <div class="ward-percent">

                        ครองเตียง ${rate}%

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

    } catch (error) {

        console.error(error);

    }

}

loadWardBed();

setInterval(
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
        data.admittoday + ' เตียง';

    document.getElementById(
            'wtotal'
        ).innerHTML =
        data.wtotal + ' เตียง';

    document.getElementById(
            'wblank'
        ).innerHTML =
        data.wblank + ' เตียง';

    document.getElementById('dchtoday').innerHTML =
        data.dchtoday + ' เตียง';

    document.getElementById('uc').innerHTML =
        data.uc + ' เตียง';

    document.getElementById('mo').innerHTML =
        data.mo + ' เตียง';

    document.getElementById('ot').innerHTML =
        data.ot + ' เตียง';

    document.getElementById('occupancy').innerHTML =
        data.occupancy + '%';

}

loadBedSummary();

setInterval(
    loadBedSummary,
    60000
);

async function loadServiceSummary() {

    const response =
        await fetch('api/service_summary_today.php');

    const data =
        await response.json();

    document.getElementById('scope_total').innerHTML =
        data.scope_total + ' ราย';

    document.getElementById('observe_total').innerHTML =
        data.observe_total + ' ราย';

    document.getElementById('or_total').innerHTML =
        data.or_total + ' ราย';

    document.getElementById('lr_total').innerHTML =
        data.lr_total + ' ราย';

    document.getElementById('pt_total').innerHTML =
        data.pt_total + ' ราย';

    document.getElementById('thai_total').innerHTML =
        data.thai_total + ' ราย';

    document.getElementById('dent_total').innerHTML =
        data.dent_total + ' ราย';

    document.getElementById('xray_total').innerHTML =
        data.xray_total + ' ราย';

    document.getElementById('hemo_total').innerHTML =
        data.hemo_total + ' ราย';
    document.getElementById('wound_total').innerHTML =
        data.wound_total + ' ราย';
    document.getElementById('us_total').innerHTML =
        data.us_total + ' ราย';
    document.getElementById('lab_total').innerHTML =
        data.lab_total + ' ราย';

    document.getElementById('ct_total').innerHTML =
        data.ct_total + ' ราย';

}

loadServiceSummary();

setInterval(
    loadServiceSummary,
    60000
);
</script>

<?php

include 'templates/footer.php';