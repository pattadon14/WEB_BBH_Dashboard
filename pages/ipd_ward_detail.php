<?php

include '../templates/header.php';
include '../templates/navbar.php';
include '../templates/sidebar.php';

$ward = trim((string)($_GET['ward'] ?? ''));

?>

<style>
/* =========================================================
   IPD WARD DETAIL - PROFESSIONAL HOSPITAL UI
========================================================= */
.ipd-ward-page {
    --ipd-green: #198754;
    --ipd-green-dark: #147447;
    --ipd-blue: #1683c5;
    --ipd-orange: #f59e0b;
    --ipd-red: #dc3545;
    --ipd-text: #263238;
    --ipd-muted: #71808d;
    --ipd-border: #dfe7e3;
}

.ipd-ward-page .content {
    padding-top: 1rem !important;
}

.ipd-ward-page .ward-page-header {
    margin-bottom: 15px;
    padding: 2px 2px 0;
}

.ipd-ward-page .ward-title-wrap {
    min-width: 0;
}

.ipd-ward-page .ward-page-title {
    display: flex;
    align-items: center;
    gap: 11px;
    color: var(--ipd-green);
    font-weight: 700;
    margin: 0;
    font-size: 2.05rem;
    line-height: 1.2;
}

.ipd-ward-page .ward-page-title i {
    font-size: 1.85rem;
}

.ipd-ward-page .ward-page-subtitle {
    color: var(--ipd-muted);
    margin-top: 5px;
    font-size: 1.02rem;
}

.ipd-ward-page .back-btn {
    border-radius: 8px;
    padding: 8px 14px;
    white-space: nowrap;
    font-size: .94rem;
}

.ipd-ward-page .ward-main-card {
    border: 1px solid var(--ipd-border);
    border-radius: 14px;
    box-shadow: 0 3px 12px rgba(31, 45, 61, .07);
    background: #fff;
    overflow: hidden;
}

.ipd-ward-page .ward-main-card-body {
    padding: 20px;
}

/* =========================================================
   WARD OVERVIEW - DISTINCT COLORED CARDS
========================================================= */
.ipd-ward-page .ward-overview-title {
    display: flex;
    align-items: center;
    gap: 9px;
    color: var(--ipd-text);
    font-size: 1.3rem;
    font-weight: 700;
    margin: 0 0 14px 2px;
}

.ipd-ward-page .ward-overview-title i {
    color: var(--ipd-green);
}

.ipd-ward-page .ward-overview-grid {
    margin-left: -7px;
    margin-right: -7px;
    margin-bottom: 25px;
}

.ipd-ward-page .overview-col {
    padding-left: 7px;
    padding-right: 7px;
    margin-bottom: 10px;
}

.ipd-ward-page .overview-card {
    position: relative;
    min-height: 132px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    padding: 18px 22px;
    border: 1px solid transparent;
    border-left: 6px solid var(--ipd-green);
    border-radius: 12px;
    background: #eaf7f0;
    box-shadow: 0 4px 10px rgba(31, 45, 61, .08);
    overflow: hidden;
}

.ipd-ward-page .overview-card::after {
    content: '';
    position: absolute;
    width: 115px;
    height: 115px;
    right: -30px;
    bottom: -55px;
    border-radius: 50%;
    background: rgba(25, 135, 84, .08);
}

.ipd-ward-page .overview-card.blue {
    border-left-color: var(--ipd-blue);
    background: #edf7fc;
}

.ipd-ward-page .overview-card.blue::after {
    background: rgba(22, 131, 197, .09);
}

.ipd-ward-page .overview-card.orange {
    border-left-color: var(--ipd-orange);
    background: #fff7e6;
}

.ipd-ward-page .overview-card.orange::after {
    background: rgba(245, 158, 11, .09);
}

.ipd-ward-page .overview-card.red {
    border-left-color: var(--ipd-red);
    background: #fff0f2;
}

.ipd-ward-page .overview-card.red::after {
    background: rgba(220, 53, 69, .09);
}

.ipd-ward-page .overview-left {
    display: flex;
    align-items: center;
    gap: 14px;
    min-width: 0;
    position: relative;
    z-index: 1;
}

.ipd-ward-page .overview-icon {
    width: 58px;
    height: 58px;
    flex: 0 0 58px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 14px;
    background: rgba(255, 255, 255, .78);
    color: var(--ipd-green);
    font-size: 1.7rem;
    box-shadow: 0 2px 5px rgba(31, 45, 61, .06);
}

.ipd-ward-page .overview-card.blue .overview-icon {
    color: var(--ipd-blue);
}

.ipd-ward-page .overview-card.orange .overview-icon {
    color: var(--ipd-orange);
}

.ipd-ward-page .overview-card.red .overview-icon {
    color: var(--ipd-red);
}

.ipd-ward-page .overview-label {
    color: #3f4c54;
    font-size: 1.08rem;
    font-weight: 700;
    line-height: 1.3;
}

.ipd-ward-page .overview-help {
    color: #71808d;
    font-size: .88rem;
    margin-top: 5px;
}

.ipd-ward-page .overview-value-wrap {
    text-align: right;
    white-space: nowrap;
    position: relative;
    z-index: 1;
}

.ipd-ward-page .overview-value {
    color: var(--ipd-green);
    font-size: 2.55rem;
    line-height: 1;
    font-weight: 800;
}

.ipd-ward-page .overview-card.blue .overview-value {
    color: var(--ipd-blue);
}

.ipd-ward-page .overview-card.orange .overview-value {
    color: var(--ipd-orange);
}

.ipd-ward-page .overview-card.red .overview-value {
    color: var(--ipd-red);
}

.ipd-ward-page .overview-unit {
    color: #69767e;
    font-weight: 600;
    margin-left: 5px;
}

.ipd-ward-page .occupancy-bar {
    height: 7px;
    min-width: 105px;
    background: rgba(255, 255, 255, .75);
    border-radius: 10px;
    margin-top: 9px;
    overflow: hidden;
}

.ipd-ward-page .occupancy-fill {
    height: 100%;
    width: 0;
    background: var(--ipd-green);
    border-radius: inherit;
    transition: width .35s ease;
}

.ipd-ward-page .overview-card.orange .occupancy-fill {
    background: var(--ipd-orange);
}

.ipd-ward-page .overview-card.red .occupancy-fill {
    background: var(--ipd-red);
}

/* =========================================================
   PATIENT SECTION
========================================================= */
.ipd-ward-page .patient-section-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin: 0 2px 14px;
}

.ipd-ward-page .patient-section-title {
    display: flex;
    align-items: center;
    gap: 9px;
    color: var(--ipd-text);
    font-size: 1.32rem;
    font-weight: 700;
}

.ipd-ward-page .patient-section-title i {
    color: var(--ipd-green);
}

.ipd-ward-page .patient-count-badge {
    background: #e9f6ef;
    color: var(--ipd-green);
    border: 1px solid #cfe9da;
    border-radius: 20px;
    padding: 6px 13px;
    font-size: .92rem;
    font-weight: 700;
}

.ipd-ward-page .patient-grid {
    margin-left: -7px;
    margin-right: -7px;
}

.ipd-ward-page .patient-col {
    padding-left: 7px;
    padding-right: 7px;
    margin-bottom: 15px;
}

.ipd-ward-page .patient-card {
    position: relative;
    height: 100%;
    min-height: 205px;
    background: #fff;
    border: 1px solid var(--ipd-border);
    border-top: 4px solid var(--ipd-green);
    border-radius: 10px;
    padding: 14px 15px 13px;
    box-shadow: 0 3px 8px rgba(31, 45, 61, .075);
    transition: transform .16s ease, box-shadow .16s ease, border-color .16s ease;
}

.ipd-ward-page .patient-card:hover {
    transform: translateY(-2px);
    border-color: #c9d9d1;
    box-shadow: 0 7px 18px rgba(31, 45, 61, .12);
}

.ipd-ward-page .patient-top {
    display: flex;
    align-items: center;
    gap: 11px;
    min-height: 53px;
}

.ipd-ward-page .patient-avatar {
    width: 49px;
    height: 49px;
    flex: 0 0 49px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: #edf6f1;
    color: var(--ipd-green);
    font-size: 1.6rem;
}

.ipd-ward-page .patient-avatar.avatar-male,
.ipd-ward-page .patient-avatar.avatar-child-male {
    color: #1683c5;
    background: #edf7fc;
}

.ipd-ward-page .patient-avatar.avatar-female,
.ipd-ward-page .patient-avatar.avatar-child-female {
    color: #d45a88;
    background: #fff0f5;
}

.ipd-ward-page .patient-name {
    color: #263238;
    font-size: 1.12rem;
    line-height: 1.25;
    font-weight: 700;
    word-break: break-word;
}

.ipd-ward-page .patient-meta {
    color: #8a969e;
    font-size: .9rem;
    margin-top: 4px;
}

.ipd-ward-page .patient-badges {
    display: flex;
    justify-content: flex-end;
    flex-wrap: wrap;
    gap: 5px;
    margin-top: 10px;
}

.ipd-ward-page .patient-badge {
    display: inline-flex;
    align-items: center;
    padding: 4px 9px;
    border-radius: 13px;
    background: var(--ipd-blue);
    color: #fff;
    font-size: .8rem;
    font-weight: 700;
    line-height: 1.2;
    gap: 5px;
}

.ipd-ward-page .patient-badge.bed-badge {
    background: var(--ipd-green);
}

.ipd-ward-page .patient-info {
    border-top: 1px solid #edf0f2;
    margin-top: 10px;
    padding-top: 9px;
    font-size: .9rem;
    line-height: 1.75;
}

.ipd-ward-page .patient-info-row {
    display: flex;
    align-items: baseline;
    gap: 6px;
    min-width: 0;
}

.ipd-ward-page .patient-info-label {
    color: #66727b;
    font-weight: 600;
    white-space: nowrap;
}

.ipd-ward-page .patient-info .value {
    color: #1683c5;
    font-weight: 700;
}

.ipd-ward-page .patient-info .admit-date {
    color: #59636b;
}

.ipd-ward-page .doctor-row {
    margin-top: 2px;
    min-width: 0;
}

.ipd-ward-page .doctor-row i {
    color: var(--ipd-green);
    width: 16px;
    text-align: center;
}

.ipd-ward-page .doctor-name {
    color: #1683c5;
    font-weight: 700;
    word-break: break-word;
}

.ipd-ward-page .empty-state {
    padding: 60px 15px;
    color: #7b8794;
    text-align: center;
    font-size: 1rem;
}

.ipd-ward-page .empty-state i {
    font-size: 2.5rem;
    margin-bottom: 10px;
    color: #adb5bd;
}

@media (min-width:1200px) {
    .ipd-ward-page .patient-col {
        flex: 0 0 25%;
        max-width: 25%;
    }
}

@media (max-width:1199px) and (min-width:768px) {
    .ipd-ward-page .patient-col {
        flex: 0 0 33.333333%;
        max-width: 33.333333%;
    }
}

@media (max-width:767px) {

    .ipd-ward-page .patient-col,
    .ipd-ward-page .overview-col {
        flex: 0 0 50%;
        max-width: 50%;
    }
}

@media (max-width:575px) {
    .ipd-ward-page .ward-page-header {
        gap: 10px;
        align-items: flex-start !important;
    }

    .ipd-ward-page .ward-page-title {
        font-size: 1.5rem;
    }

    .ipd-ward-page .ward-page-title i {
        font-size: 1.35rem;
    }

    .ipd-ward-page .ward-page-subtitle {
        font-size: .84rem;
    }

    .ipd-ward-page .back-btn {
        font-size: .78rem;
        padding: 6px 8px;
    }

    .ipd-ward-page .ward-main-card-body {
        padding: 11px;
    }

    .ipd-ward-page .overview-col,
    .ipd-ward-page .patient-col {
        flex: 0 0 100%;
        max-width: 100%;
    }

    .ipd-ward-page .overview-card {
        min-height: 112px;
        padding: 14px 15px;
    }

    .ipd-ward-page .overview-icon {
        width: 46px;
        height: 46px;
        flex-basis: 46px;
        font-size: 1.35rem;
    }

    .ipd-ward-page .overview-label {
        font-size: .98rem;
    }

    .ipd-ward-page .overview-help {
        font-size: .78rem;
    }

    .ipd-ward-page .overview-value {
        font-size: 2rem;
    }

    .ipd-ward-page .patient-section-title {
        font-size: 1.12rem;
    }

    .ipd-ward-page .patient-name {
        font-size: 1rem;
    }
}
</style>

<div class="content-wrapper ipd-ward-page">
    <section class="content">
        <div class="container-fluid">

            <div class="ward-page-header d-flex align-items-center justify-content-between">
                <div class="ward-title-wrap">
                    <h2 class="ward-page-title">
                        <i class="fa-solid fa-bed-pulse"></i>
                        <span id="ward-title">ข้อมูลผู้ป่วยใน</span>
                    </h2>
                    <div class="ward-page-subtitle" id="ward-subtitle">กำลังโหลดข้อมูล Ward...</div>
                </div>
                <a href="<?= BASE_URL ?>index.php" class="btn btn-outline-success back-btn">
                    <i class="fas fa-arrow-left mr-1"></i> กลับหน้าหลัก
                </a>
            </div>

            <div class="ward-main-card">
                <div class="ward-main-card-body">

                    <div class="ward-overview-title">
                        <i class="fa-solid fa-chart-column"></i>
                        ภาพรวม Ward วันนี้
                    </div>

                    <div class="row ward-overview-grid">
                        <div class="col-xl-3 col-md-6 col-12 overview-col">
                            <div class="overview-card">
                                <div class="overview-left">
                                    <div class="overview-icon"><i class="fa-solid fa-bed"></i></div>
                                    <div>
                                        <div class="overview-label">เตียงทั้งหมด</div>
                                        <div class="overview-help">จำนวนเตียงที่จัดไว้ใน Ward</div>
                                    </div>
                                </div>
                                <div class="overview-value-wrap">
                                    <span class="overview-value" id="total-beds">—</span><span
                                        class="overview-unit">เตียง</span>
                                </div>
                            </div>
                        </div>

                        <div class="col-xl-3 col-md-6 col-12 overview-col">
                            <div class="overview-card blue">
                                <div class="overview-left">
                                    <div class="overview-icon"><i class="fa-solid fa-hospital-user"></i></div>
                                    <div>
                                        <div class="overview-label">ผู้ป่วย Admit</div>
                                        <div class="overview-help">ผู้ป่วยที่ยังพักรักษาอยู่</div>
                                    </div>
                                </div>
                                <div class="overview-value-wrap">
                                    <span class="overview-value" id="admit-now">—</span><span
                                        class="overview-unit">ราย</span>
                                </div>
                            </div>
                        </div>

                        <div class="col-xl-3 col-md-6 col-12 overview-col">
                            <div class="overview-card orange">
                                <div class="overview-left">
                                    <div class="overview-icon"><i class="fa-solid fa-bed"></i></div>
                                    <div>
                                        <div class="overview-label">เตียงว่าง</div>
                                        <div class="overview-help">เตียงที่ยังสามารถรับผู้ป่วยได้</div>
                                    </div>
                                </div>
                                <div class="overview-value-wrap">
                                    <span class="overview-value" id="available-beds">—</span><span
                                        class="overview-unit">เตียง</span>
                                </div>
                            </div>
                        </div>

                        <div class="col-xl-3 col-md-6 col-12 overview-col">
                            <div class="overview-card red" id="occupancy-card">
                                <div class="overview-left">
                                    <div class="overview-icon"><i class="fa-solid fa-chart-pie"></i></div>
                                    <div>
                                        <div class="overview-label">อัตราครองเตียง</div>
                                        <div class="overview-help">สัดส่วนผู้ป่วยต่อจำนวนเตียง</div>
                                    </div>
                                </div>
                                <div class="overview-value-wrap">
                                    <span class="overview-value" id="occupancy-rate">—</span><span
                                        class="overview-unit">%</span>
                                    <div class="occupancy-bar">
                                        <div class="occupancy-fill" id="occupancy-fill"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="patient-section-head">
                        <div class="patient-section-title">
                            <i class="fa-solid fa-users"></i>
                            ผู้ป่วยที่กำลัง Admit อยู่
                        </div>
                        <div class="patient-count-badge"><span id="patient-count">—</span> ราย</div>
                    </div>

                    <div class="row patient-grid" id="patient-grid">
                        <div class="col-12">
                            <div class="empty-state">
                                <i class="fas fa-spinner fa-spin d-block"></i>
                                กำลังโหลดข้อมูลผู้ป่วย...
                            </div>
                        </div>
                    </div>

                </div>
            </div>

        </div>
    </section>
</div>

<script>
(function() {
    const ward = <?= json_encode($ward, JSON_UNESCAPED_UNICODE) ?>;
    const baseUrl = <?= json_encode(BASE_URL, JSON_UNESCAPED_UNICODE) ?>;

    const titleEl = document.getElementById('ward-title');
    const subtitleEl = document.getElementById('ward-subtitle');
    const totalBedsEl = document.getElementById('total-beds');
    const admitEl = document.getElementById('admit-now');
    const availableEl = document.getElementById('available-beds');
    const occupancyEl = document.getElementById('occupancy-rate');
    const occupancyFillEl = document.getElementById('occupancy-fill');
    const occupancyCard = document.getElementById('occupancy-card');
    const patientCountEl = document.getElementById('patient-count');
    const gridEl = document.getElementById('patient-grid');

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/\"/g, '&quot;').replace(/'/g, '&#039;');
    }

    function parseDate(value) {
        if (!value) return null;
        const date = new Date(value);
        return Number.isNaN(date.getTime()) ? null : date;
    }

    function formatDate(value) {
        const date = parseDate(value);
        if (!date) return '-';
        return date.toLocaleDateString('th-TH', {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit'
        });
    }

    function calculateStayDays(value) {
        const date = parseDate(value);
        if (!date) return null;
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        date.setHours(0, 0, 0, 0);
        return Math.max(Math.floor((today - date) / 86400000), 0);
    }

    function getGenderInfo(patient) {
        const sex = String(patient.sex ?? '').trim();
        const age = Number(patient.age);
        const pname = String(patient.pname ?? '').trim();
        let gender = '';
        if (sex === '1' || /ชาย|เด็กชาย|ด\.ช\./i.test(pname)) gender = 'male';
        else if (sex === '2' || /หญิง|เด็กหญิง|ด\.ญ\./i.test(pname)) gender = 'female';
        const child = Number.isFinite(age) && age < 15;
        if (child && gender === 'male') return {
            icon: 'fa-child',
            className: 'avatar-child-male',
            label: 'เด็กชาย'
        };
        if (child && gender === 'female') return {
            icon: 'fa-child-dress',
            className: 'avatar-child-female',
            label: 'เด็กหญิง'
        };
        if (gender === 'male') return {
            icon: 'fa-person',
            className: 'avatar-male',
            label: 'ชาย'
        };
        if (gender === 'female') return {
            icon: 'fa-person-dress',
            className: 'avatar-female',
            label: 'หญิง'
        };
        return {
            icon: 'fa-user',
            className: '',
            label: 'ไม่ระบุเพศ'
        };
    }

    function renderEmpty(message, icon) {
        gridEl.innerHTML =
            `<div class="col-12"><div class="empty-state"><i class="${icon} d-block"></i>${escapeHtml(message)}</div></div>`;
    }

    async function loadWardOverview() {
        const response = await fetch(baseUrl + 'api/index_ward_bed.php', {
            cache: 'no-store'
        });
        if (!response.ok) throw new Error('ไม่สามารถโหลดข้อมูลสรุป Ward ได้');
        const wards = await response.json();
        if (!Array.isArray(wards)) throw new Error('รูปแบบข้อมูล Ward ไม่ถูกต้อง');

        const item = wards.find(function(row) {
            return String(row.ward) === String(ward);
        });

        if (!item) return;

        const beds = Number(item.bedcount || 0);
        const admit = Number(item.admitnow || 0);
        const available = Number(item.available_bed ?? beds - admit);
        const rate = Number(item.occupancy_rate || 0);

        totalBedsEl.textContent = beds.toLocaleString();
        admitEl.textContent = admit.toLocaleString();
        availableEl.textContent = available.toLocaleString();
        occupancyEl.textContent = rate.toFixed(2);
        occupancyFillEl.style.width = Math.min(Math.max(rate, 0), 100) + '%';

        occupancyCard.classList.remove('red', 'orange');
        if (rate >= 90) occupancyCard.classList.add('red');
        else if (rate >= 70) occupancyCard.classList.add('orange');

        if (item.name) {
            titleEl.textContent = 'ข้อมูลผู้ป่วยใน ' + item.name;
            subtitleEl.textContent = 'ผู้ป่วยที่กำลัง Admit อยู่ใน ' + item.name;
        }
    }

    async function loadWardPatients() {
        if (!ward || !/^\d+$/.test(ward)) {
            titleEl.textContent = 'ข้อมูลผู้ป่วยใน';
            subtitleEl.textContent = 'ไม่พบรหัส Ward';
            patientCountEl.textContent = '0';
            renderEmpty('ไม่พบรหัส Ward ที่ถูกต้อง', 'fas fa-circle-exclamation');
            return;
        }

        const response = await fetch(baseUrl + 'api/ipd_ward_detail.php?ward=' + encodeURIComponent(ward), {
            cache: 'no-store'
        });
        const data = await response.json();
        if (!response.ok || data.error) throw new Error(data.error || 'ไม่สามารถโหลดข้อมูลได้');

        const patients = Array.isArray(data.patients) ? data.patients : [];
        patientCountEl.textContent = patients.length.toLocaleString();

        if (!patients.length) {
            renderEmpty('ไม่พบผู้ป่วยที่กำลัง Admit อยู่ใน Ward นี้', 'fas fa-bed');
            return;
        }

        gridEl.innerHTML = patients.map(function(patient) {
            const gender = getGenderInfo(patient);
            const age = patient.age !== null && patient.age !== undefined && patient.age !== '' ?
                Number(patient.age).toLocaleString() + ' ปี' : '-';
            const stay = calculateStayDays(patient.regdate);
            const doctor = patient.doctor_name && patient.doctor_name !== '-' ? patient.doctor_name :
                'ไม่ระบุ';

            return `<div class="col-xl-3 col-lg-4 col-md-6 col-12 patient-col">
                <div class="patient-card">
                    <div class="patient-top">
                        <div class="patient-avatar ${gender.className}" title="${escapeHtml(gender.label)}"><i class="fas ${gender.icon}"></i></div>
                        <div class="flex-grow-1 min-width-0">
                            <div class="patient-name">${escapeHtml(patient.patient_name || '-')}</div>
                            <div class="patient-meta">${escapeHtml(gender.label)} · อายุ ${escapeHtml(age)}</div>
                        </div>
                    </div>
                    <div class="patient-badges">
                        <span class="patient-badge">AN ${escapeHtml(patient.an || '-')}</span>
                        ${patient.bedno ? `<span class="patient-badge bed-badge"><i class="fas fa-bed mr-1"></i>เตียง ${escapeHtml(patient.bedno)}</span>` : ''}
                    </div>
                    <div class="patient-info">
                        <div class="patient-info-row"><span class="patient-info-label">HN :</span><span class="value">${escapeHtml(patient.hn || '-')}</span></div>
                        <div class="patient-info-row"><span class="patient-info-label">วันนอน :</span><span class="value">${stay === null ? '-' : stay + ' วัน'}</span></div>
                        <div class="patient-info-row"><span class="patient-info-label">Admit :</span><span class="admit-date">${escapeHtml(formatDate(patient.regdate))}</span></div>
                        <div class="patient-info-row doctor-row"><i class="fas fa-user-doctor"></i><span class="patient-info-label">แพทย์ :</span><span class="doctor-name">${escapeHtml(doctor)}</span></div>
                    </div>
                </div>
            </div>`;
        }).join('');
    }

    async function init() {
        try {
            await Promise.all([loadWardOverview(), loadWardPatients()]);
        } catch (error) {
            console.error(error);
            patientCountEl.textContent = '—';
            renderEmpty('ไม่สามารถโหลดข้อมูลผู้ป่วยได้: ' + error.message, 'fas fa-triangle-exclamation');
        }
    }

    init();
})();
</script>

<?php include '../templates/footer.php'; ?>