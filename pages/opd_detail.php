<?php
include '../templates/header.php';
include '../templates/navbar.php';
include '../templates/sidebar.php';
?>

<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/opd_tabs.css">

<div class="content-wrapper">
    <section class="content pt-3">
        <div class="container-fluid">

            <!-- =====================================================
                 OPD TABS
            ====================================================== -->
            <div class="opd-page-tabs mb-2" role="tablist" aria-label="ประเภทผู้รับบริการ OPD">
                <button class="opd-page-tab active" type="button" role="tab" aria-selected="true"
                    data-opd-tab="general">
                    <i class="fa-solid fa-stethoscope"></i>
                    ตรวจโรคทั่วไป
                </button>

                <button class="opd-page-tab" type="button" role="tab" aria-selected="false" data-opd-tab="special">
                    <i class="fa-solid fa-hospital-user"></i>
                    คลินิคพิเศษ
                </button>
            </div>

            <!-- =====================================================
                 TAB 1 : ตรวจโรคทั่วไป
            ====================================================== -->
            <div class="opd-tab-panel active" id="opd-tab-general" role="tabpanel">

                <div class="row">
                    <div class="col-12 mb-3">
                        <div class="card shadow-sm opd-summary-horizontal">
                            <div class="card-body">

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
                                        <small>คน</small>
                                    </div>
                                </div>

                                <div class="opd-summary-grid">

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
                                                <span class="summary-unit">คน</span>
                                            </div>
                                            <div class="todaysummary-sub">
                                                มารับบริการโดยไม่มีนัดล่วงหน้า
                                            </div>
                                        </div>
                                    </div>

                                    <div class="opd-summary-section">
                                        <div class="summary-section-title">
                                            <i class="fa-solid fa-calendar-days"></i>
                                            นัดหมายวันนี้
                                        </div>

                                        <div class="appointment-horizontal-card">
                                            <div class="appointment-total">
                                                <span id="appoint_today">—</span>
                                                <small>คน</small>
                                            </div>

                                            <div class="appointment-status">
                                                <div class="appointment-status-item came">
                                                    <div>
                                                        <i class="fa-solid fa-square-check"></i>
                                                        มาตามนัด
                                                    </div>
                                                    <strong id="oapp_success">—</strong>
                                                    <small>คน</small>
                                                </div>

                                                <div class="appointment-status-item miss">
                                                    <div>
                                                        <i class="fa-solid fa-square-xmark"></i>
                                                        ไม่มาตามนัด
                                                    </div>
                                                    <strong id="miss_today">—</strong>
                                                    <small>คน</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="opd-summary-section">
                                        <div class="summary-section-title">
                                            <i class="fa-solid fa-clock"></i>
                                            แยกตามช่วงเวลา
                                        </div>

                                        <div class="time-horizontal-group">
                                            <div class="time-horizontal-card time-night">
                                                <div class="time-icon"><i class="fa-solid fa-moon"></i></div>
                                                <div class="time-info">
                                                    <div class="time-label">ก่อนเวลาราชการ</div>
                                                    <div class="time-range">20:01 – 07:59 น.</div>
                                                </div>
                                                <div class="time-count">
                                                    <span id="before_time">—</span><small>คน</small>
                                                </div>
                                            </div>

                                            <div class="time-horizontal-card time-morning">
                                                <div class="time-icon"><i class="fa-solid fa-sun"></i></div>
                                                <div class="time-info">
                                                    <div class="time-label">ในเวลาราชการ</div>
                                                    <div class="time-range">08:00 – 16:00 น.</div>
                                                </div>
                                                <div class="time-count">
                                                    <span id="worktime">—</span><small>คน</small>
                                                </div>
                                            </div>

                                            <div class="time-horizontal-card time-evening">
                                                <div class="time-icon"><i class="fa-solid fa-cloud-sun"></i></div>
                                                <div class="time-info">
                                                    <div class="time-label">นอกเวลาราชการ</div>
                                                    <div class="time-range">16:00 – 20:00 น.</div>
                                                </div>
                                                <div class="time-count">
                                                    <span id="after_time">—</span><small>คน</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="opd-summary-section">
                                        <div class="summary-section-title">
                                            <i class="fa-solid fa-stethoscope"></i>
                                            สถานะการตรวจ
                                        </div>

                                        <div class="exam-horizontal-group">
                                            <div class="exam-horizontal-card wait-card">
                                                <div class="summary-title">
                                                    <i class="fa-solid fa-user-nurse"></i>
                                                    รอซักประวัติ
                                                </div>
                                                <div class="summary-number">
                                                    <span id="wait_triage">—</span>
                                                    <span class="summary-unit">คน</span>
                                                </div>
                                            </div>

                                            <div class="exam-horizontal-card exam-card">
                                                <div class="summary-title">
                                                    <i class="fa-solid fa-stethoscope"></i>
                                                    รอตรวจ
                                                </div>
                                                <div class="summary-number">
                                                    <span id="wait_exam">—</span>
                                                    <span class="summary-unit">คน</span>
                                                </div>
                                            </div>

                                            <div class="exam-horizontal-card finish-card">
                                                <div class="summary-title">
                                                    <i class="fa-solid fa-circle-check"></i>
                                                    ตรวจเสร็จแล้ว
                                                </div>
                                                <div class="summary-number">
                                                    <span id="finish_exam">—</span>
                                                    <span class="summary-unit">คน</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="card shadow-sm mb-3">
                            <div class="opd-chart">
                                <div class="row align-items-center">
                                    <div class="col-md-7">
                                        <h2 class="mb-1 text-success" style="font-weight:bold;">
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

                    <div class="col-12 mb-3">
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
                                                    <th class="text-center" style="width:80px"><i
                                                            class="fa-solid fa-mars" style="color:#3b82f6"></i> ชาย</th>
                                                    <th class="text-center" style="width:80px"><i
                                                            class="fa-solid fa-venus" style="color:#ec4899"></i> หญิง
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
                                    <div id="opd-age-chart" style="height:360px;"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- =====================================================
                 TAB 2 : คลินิคพิเศษ
            ====================================================== -->
            <div class="opd-tab-panel" id="opd-tab-special" role="tabpanel" hidden>

                <div class="row">
                    <div class="col-12 mb-3">
                        <div class="card shadow-sm opd-summary-horizontal special-clinic-summary">
                            <div class="card-body">

                                <div class="opd-summary-main">
                                    <div class="opd-summary-main-icon">
                                        <i class="fa-solid fa-hospital-user"></i>
                                    </div>

                                    <div class="opd-summary-main-info">
                                        <div class="opd-summary-title">
                                            ผู้มารับบริการตรวจโรคคลินิคพิเศษวันนี้
                                        </div>
                                        <div class="opd-summary-total-label">
                                            จำนวนผู้มารับบริการคลินิคพิเศษ
                                        </div>
                                    </div>

                                    <div class="opd-summary-main-number special-total-number">
                                        <span id="special_total">—</span>
                                        <small>คน</small>
                                    </div>
                                </div>

                                <div class="special-clinic-grid">

                                    <div class="special-clinic-section">
                                        <div class="summary-section-title">
                                            <i class="fa-solid fa-calendar-days"></i>
                                            นัดหมายวันนี้
                                        </div>

                                        <div class="special-appointment-card">
                                            <div class="special-appointment-total">
                                                <span id="special_appointment">—</span>
                                                <small>คน</small>
                                            </div>

                                            <div class="special-appointment-result">
                                                <div class="special-result-item came">
                                                    <div class="special-result-label">
                                                        <i class="fa-solid fa-square-check"></i>
                                                        มาตามนัด
                                                    </div>
                                                    <div class="special-result-number">
                                                        <span id="special_came">—</span>
                                                        <small>คน</small>
                                                    </div>
                                                </div>

                                                <div class="special-result-divider"></div>

                                                <div class="special-result-item miss">
                                                    <div class="special-result-label">
                                                        <i class="fa-solid fa-square-xmark"></i>
                                                        ไม่มาตามนัด
                                                    </div>
                                                    <div class="special-result-number">
                                                        <span id="special_not_came">—</span>
                                                        <small>คน</small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="special-clinic-section">
                                        <div class="summary-section-title">
                                            <i class="fa-solid fa-stethoscope"></i>
                                            สถานะการตรวจ
                                        </div>

                                        <div class="special-status-list">
                                            <div class="special-status-item status-triage">
                                                <div class="special-status-icon"><i class="fa-solid fa-user-nurse"></i>
                                                </div>
                                                <div class="special-status-info">
                                                    <div class="special-status-name">รอซักประวัติ</div>
                                                    <div class="special-status-description">รอซักประวัติและคัดกรอง</div>
                                                </div>
                                                <div class="special-status-count"><span
                                                        id="special_wait_triage">—</span><small>คน</small></div>
                                            </div>

                                            <div class="special-status-item status-exam">
                                                <div class="special-status-icon"><i class="fa-solid fa-stethoscope"></i>
                                                </div>
                                                <div class="special-status-info">
                                                    <div class="special-status-name">รอตรวจ</div>
                                                    <div class="special-status-description">กำลังรอพบแพทย์</div>
                                                </div>
                                                <div class="special-status-count"><span
                                                        id="special_wait_exam">—</span><small>คน</small></div>
                                            </div>

                                            <div class="special-status-item status-finish">
                                                <div class="special-status-icon"><i
                                                        class="fa-solid fa-circle-check"></i></div>
                                                <div class="special-status-info">
                                                    <div class="special-status-name">ตรวจเสร็จแล้ว</div>
                                                    <div class="special-status-description">ตรวจเสร็จและออกจากระบบแล้ว
                                                    </div>
                                                </div>
                                                <div class="special-status-count"><span
                                                        id="special_finish_exam">—</span><small>คน</small></div>
                                            </div>
                                        </div>
                                    </div>

                                </div>

                                <div class="special-clinic-placeholder mt-4">
                                    <i class="fa-solid fa-chart-line"></i>
                                    <div>
                                        <strong>พื้นที่สำหรับข้อมูลคลินิคพิเศษเพิ่มเติม</strong>
                                        <div>สามารถเพิ่มกราฟ / ตารางของคลินิคพิเศษในขั้นตอนถัดไปได้</div>
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
<script src="<?= BASE_URL ?>assets/js/opd_detail.js"></script>

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

            tabs.forEach(item => {
                const active = item === this;
                item.classList.toggle('active', active);
                item.setAttribute('aria-selected', active ? 'true' : 'false');
            });

            panels.forEach(panel => {
                const active = panel.id === 'opd-tab-' + target;
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