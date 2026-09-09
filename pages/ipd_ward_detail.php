<?php

include '../templates/header.php';
include '../templates/navbar.php';
include '../templates/sidebar.php';

$ward = trim((string)($_GET['ward'] ?? ''));

?>

<style>
/* =========================================================
   IPD WARD DETAIL - PROFESSIONAL UI
========================================================= */
.ipd-ward-page {
    --ipd-green: #198754;
    --ipd-green-dark: #147447;
    --ipd-blue: #1683c5;
    --ipd-text: #263238;
    --ipd-muted: #7b8794;
    --ipd-border: #e4e9ed;
    --ipd-bg: #f5f7f8;
}

.ipd-ward-page .content {
    padding-top: 1rem !important;
}

.ipd-ward-page .ward-page-header {
    margin-bottom: 14px;
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
    font-size: 1.85rem;
    line-height: 1.2;
}

.ipd-ward-page .ward-page-title i {
    font-size: 1.7rem;
}

.ipd-ward-page .ward-page-subtitle {
    color: var(--ipd-muted);
    margin-top: 4px;
    font-size: .95rem;
}

.ipd-ward-page .back-btn {
    border-radius: 7px;
    padding: 7px 13px;
    white-space: nowrap;
    font-size: .9rem;
    border-width: 1px;
}

/* Main content shell */
.ipd-ward-page .ward-main-card {
    border: 1px solid var(--ipd-border);
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(31, 45, 61, .06);
    background: #fff;
    overflow: hidden;
}

.ipd-ward-page .ward-main-card-body {
    padding: 18px;
}

/* =========================================================
   SUMMARY BAR
========================================================= */
.ipd-ward-page .ward-summary {
    width: 100%;
    min-height: 86px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    border: 1px solid #dfe9e4;
    border-left: 5px solid var(--ipd-green);
    border-radius: 10px;
    background: linear-gradient(90deg, #f7fbf9 0%, #ffffff 55%);
    box-shadow: none;
    padding: 13px 20px;
    margin-bottom: 18px;
}

.ipd-ward-page .ward-summary-left {
    display: flex;
    align-items: center;
    gap: 12px;
}

.ipd-ward-page .ward-summary-icon {
    width: 44px;
    height: 44px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 9px;
    background: #e9f6ef;
    color: var(--ipd-green);
    font-size: 1.35rem;
}

.ipd-ward-page .ward-summary-title {
    color: var(--ipd-text);
    font-size: 1.02rem;
    font-weight: 700;
    line-height: 1.2;
}

.ipd-ward-page .ward-summary-subtitle {
    color: var(--ipd-muted);
    font-size: .82rem;
    margin-top: 3px;
}

.ipd-ward-page .ward-summary-number {
    color: var(--ipd-green);
    font-size: 2rem;
    line-height: 1;
    font-weight: 700;
    min-width: 70px;
    text-align: right;
}

.ipd-ward-page .ward-summary-unit {
    color: var(--ipd-muted);
    font-size: .8rem;
    font-weight: 400;
    margin-left: 3px;
}

/* =========================================================
   PATIENT GRID
========================================================= */
.ipd-ward-page .patient-grid {
    margin-left: -7px;
    margin-right: -7px;
}

.ipd-ward-page .patient-col {
    padding-left: 7px;
    padding-right: 7px;
    margin-bottom: 14px;
}

.ipd-ward-page .patient-card {
    position: relative;
    height: 100%;
    min-height: 194px;
    background: #fff;
    border: 1px solid var(--ipd-border);
    border-top: 3px solid var(--ipd-green);
    border-radius: 9px;
    padding: 12px 13px 12px;
    box-shadow: 0 2px 6px rgba(31, 45, 61, .07);
    transition: transform .16s ease, box-shadow .16s ease, border-color .16s ease;
}

.ipd-ward-page .patient-card:hover {
    transform: translateY(-2px);
    border-color: #cfdad5;
    box-shadow: 0 7px 18px rgba(31, 45, 61, .12);
}

.ipd-ward-page .patient-top {
    display: flex;
    align-items: center;
    gap: 10px;
    min-height: 49px;
}

.ipd-ward-page .patient-avatar {
    width: 43px;
    height: 43px;
    flex: 0 0 43px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: #edf6f1;
    color: var(--ipd-green);
    font-size: 1.45rem;
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
    font-size: .98rem;
    line-height: 1.2;
    font-weight: 700;
    word-break: break-word;
}

.ipd-ward-page .patient-meta {
    color: #89949d;
    font-size: .78rem;
    margin-top: 4px;
}

.ipd-ward-page .patient-badges {
    display: flex;
    justify-content: flex-end;
    flex-wrap: wrap;
    gap: 4px;
    margin-top: 9px;
}

.ipd-ward-page .patient-badge {
    display: inline-flex;
    align-items: center;
    padding: 3px 7px;
    border-radius: 12px;
    background: #1683c5;
    color: #fff;
    font-size: .72rem;
    font-weight: 700;
    line-height: 1.2;
}

.ipd-ward-page .patient-badge.bed-badge {
    background: var(--ipd-green);
}

.ipd-ward-page .patient-info {
    border-top: 1px solid #edf0f2;
    margin-top: 9px;
    padding-top: 8px;
    font-size: .78rem;
    line-height: 1.65;
}

.ipd-ward-page .patient-info-row {
    display: flex;
    align-items: baseline;
    gap: 5px;
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
    width: 15px;
    text-align: center;
    margin-right: 1px;
}

.ipd-ward-page .doctor-name {
    color: #1683c5;
    font-weight: 700;
    word-break: break-word;
}

.ipd-ward-page .empty-state {
    padding: 55px 15px;
    color: #7b8794;
    text-align: center;
}

.ipd-ward-page .empty-state i {
    font-size: 2.4rem;
    margin-bottom: 10px;
    color: #adb5bd;
}

@media (min-width: 1200px) {
    .ipd-ward-page .patient-col {
        flex: 0 0 25%;
        max-width: 25%;
    }
}

@media (max-width: 1199px) and (min-width: 768px) {
    .ipd-ward-page .patient-col {
        flex: 0 0 33.333333%;
        max-width: 33.333333%;
    }
}

@media (max-width: 767px) {
    .ipd-ward-page .patient-col {
        flex: 0 0 50%;
        max-width: 50%;
    }
}

@media (max-width: 575px) {
    .ipd-ward-page .ward-page-header {
        gap: 10px;
        align-items: flex-start !important;
    }

    .ipd-ward-page .ward-page-title {
        font-size: 1.4rem;
    }

    .ipd-ward-page .ward-page-title i {
        font-size: 1.3rem;
    }

    .ipd-ward-page .ward-page-subtitle {
        font-size: .82rem;
    }

    .ipd-ward-page .back-btn {
        font-size: .78rem;
        padding: 6px 8px;
    }

    .ipd-ward-page .ward-main-card-body {
        padding: 11px;
    }

    .ipd-ward-page .ward-summary {
        padding: 11px 13px;
        margin-bottom: 13px;
    }

    .ipd-ward-page .ward-summary-icon {
        width: 38px;
        height: 38px;
        font-size: 1.1rem;
    }

    .ipd-ward-page .ward-summary-title {
        font-size: .9rem;
    }

    .ipd-ward-page .ward-summary-number {
        font-size: 1.6rem;
    }

    .ipd-ward-page .patient-col {
        flex: 0 0 100%;
        max-width: 100%;
    }
}
</style>

<div class="content-wrapper ipd-ward-page">

    <section class="content">

        <div class="container-fluid">

            <!-- PAGE HEADER -->
            <div class="ward-page-header d-flex align-items-center justify-content-between">
                <div class="ward-title-wrap">
                    <h2 class="ward-page-title">
                        <i class="fa-solid fa-bed-pulse"></i>
                        <span id="ward-title">ข้อมูลผู้ป่วยใน</span>
                    </h2>
                    <div class="ward-page-subtitle" id="ward-subtitle">
                        กำลังโหลดข้อมูล Ward...
                    </div>
                </div>

                <a href="<?= BASE_URL ?>index.php" class="btn btn-outline-success back-btn">
                    <i class="fas fa-arrow-left mr-1"></i>
                    กลับหน้าหลัก
                </a>
            </div>

            <div class="ward-main-card">
                <div class="ward-main-card-body">

                    <!-- SUMMARY -->
                    <div class="ward-summary">
                        <div class="ward-summary-left">
                            <div class="ward-summary-icon">
                                <i class="fa-solid fa-hospital-user"></i>
                            </div>
                            <div>
                                <div class="ward-summary-title">ผู้ป่วยที่กำลัง Admit อยู่</div>
                                <div class="ward-summary-subtitle">เฉพาะผู้ป่วยที่ยังไม่จำหน่ายออกจาก Ward</div>
                            </div>
                        </div>

                        <div class="ward-summary-number">
                            <span id="ward-total">—</span>
                            <span class="ward-summary-unit">ราย</span>
                        </div>
                    </div>

                    <!-- PATIENT CARDS -->
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
(function () {

    const ward = <?= json_encode($ward, JSON_UNESCAPED_UNICODE) ?>;
    const baseUrl = <?= json_encode(BASE_URL, JSON_UNESCAPED_UNICODE) ?>;

    const titleEl = document.getElementById('ward-title');
    const subtitleEl = document.getElementById('ward-subtitle');
    const totalEl = document.getElementById('ward-total');
    const gridEl = document.getElementById('patient-grid');

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function parseDate(value) {
        if (!value) return null;

        const date = new Date(value);
        if (Number.isNaN(date.getTime())) return null;

        return date;
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

        const days = Math.floor((today - date) / 86400000);
        return Math.max(days, 0);
    }

    /*
     * HOSxP patient.sex โดยทั่วไปใช้ 1 = ชาย, 2 = หญิง
     * หากพบค่าอื่น จะใช้ชื่อคำนำหน้าเป็นตัวช่วยก่อน fallback
     * เกณฑ์เด็ก: อายุน้อยกว่า 15 ปี
     */
    function getGenderInfo(patient) {
        const sex = String(patient.sex ?? '').trim();
        const age = Number(patient.age);
        const pname = String(patient.pname ?? '').trim();

        let gender = '';

        if (sex === '1' || /ชาย|เด็กชาย|ด\.ช\./i.test(pname)) {
            gender = 'male';
        } else if (sex === '2' || /หญิง|เด็กหญิง|ด\.ญ\./i.test(pname)) {
            gender = 'female';
        }

        const isChild = Number.isFinite(age) && age < 15;

        if (isChild && gender === 'male') {
            return {
                icon: 'fa-child',
                className: 'avatar-child-male',
                label: 'เด็กชาย'
            };
        }

        if (isChild && gender === 'female') {
            return {
                icon: 'fa-child-dress',
                className: 'avatar-child-female',
                label: 'เด็กหญิง'
            };
        }

        if (gender === 'male') {
            return {
                icon: 'fa-person',
                className: 'avatar-male',
                label: 'ชาย'
            };
        }

        if (gender === 'female') {
            return {
                icon: 'fa-person-dress',
                className: 'avatar-female',
                label: 'หญิง'
            };
        }

        return {
            icon: 'fa-user',
            className: '',
            label: ''
        };
    }

    function renderEmpty(message, icon) {
        gridEl.innerHTML = `
            <div class="col-12">
                <div class="empty-state">
                    <i class="${icon} d-block"></i>
                    ${escapeHtml(message)}
                </div>
            </div>`;
    }

    async function loadWardPatients() {

        if (!ward || !/^\d+$/.test(ward)) {
            titleEl.textContent = 'ข้อมูลผู้ป่วยใน';
            subtitleEl.textContent = 'ไม่พบรหัส Ward';
            totalEl.textContent = '0';
            renderEmpty('ไม่พบรหัส Ward ที่ถูกต้อง', 'fas fa-circle-exclamation');
            return;
        }

        try {

            const response = await fetch(
                baseUrl + 'api/ipd_ward_detail.php?ward=' + encodeURIComponent(ward),
                { cache: 'no-store' }
            );

            const data = await response.json();

            if (!response.ok || data.error) {
                throw new Error(data.error || 'ไม่สามารถโหลดข้อมูลได้');
            }

            const wardName = data.ward_name || ('Ward ' + ward);

            titleEl.textContent = 'ข้อมูลผู้ป่วยใน ' + wardName;
            subtitleEl.textContent = 'ผู้ป่วยที่กำลัง Admit อยู่ใน Ward นี้';
            totalEl.textContent = Number(data.total || 0).toLocaleString();

            if (!data.patients || data.patients.length === 0) {
                renderEmpty('ไม่พบผู้ป่วยที่กำลัง Admit อยู่ใน Ward นี้', 'fas fa-bed');
                return;
            }

            gridEl.innerHTML = data.patients.map(function (patient) {

                const stayDays = calculateStayDays(patient.regdate);
                const stayText = stayDays === null ? '-' : stayDays + ' วัน';
                const genderInfo = getGenderInfo(patient);
                const ageText = patient.age !== null && patient.age !== '' && patient.age !== undefined
                    ? Number(patient.age).toLocaleString() + ' ปี'
                    : '-';
                const doctorName = patient.doctor_name && patient.doctor_name !== '-'
                    ? patient.doctor_name
                    : 'ไม่ระบุ';

                return `
                    <div class="col-xl-3 col-lg-4 col-md-6 col-12 patient-col">
                        <div class="patient-card">

                            <div class="patient-top">
                                <div class="patient-avatar ${genderInfo.className}" title="${escapeHtml(genderInfo.label)}">
                                    <i class="fas ${genderInfo.icon}"></i>
                                </div>

                                <div class="flex-grow-1 min-width-0">
                                    <div class="patient-name">
                                        ${escapeHtml(patient.patient_name || '-')}
                                    </div>
                                    <div class="patient-meta">
                                        ${escapeHtml(genderInfo.label || 'ไม่ระบุเพศ')} · อายุ ${escapeHtml(ageText)}
                                    </div>
                                </div>
                            </div>

                            <div class="patient-badges">
                                <span class="patient-badge">
                                    AN ${escapeHtml(patient.an || '-')}
                                </span>
                                ${patient.bedno ? `
                                    <span class="patient-badge bed-badge">
                                        <i class="fas fa-bed mr-1"></i>
                                        เตียง ${escapeHtml(patient.bedno)}
                                    </span>` : ''}
                            </div>

                            <div class="patient-info">
                                <div class="patient-info-row">
                                    <span class="patient-info-label">HN :</span>
                                    <span class="value">${escapeHtml(patient.hn || '-')}</span>
                                </div>
                                <div class="patient-info-row">
                                    <span class="patient-info-label">วันนอน :</span>
                                    <span class="value">${escapeHtml(stayText)}</span>
                                </div>
                                <div class="patient-info-row">
                                    <span class="patient-info-label">Admit :</span>
                                    <span class="admit-date">${escapeHtml(formatDate(patient.regdate))}</span>
                                </div>
                                <div class="patient-info-row doctor-row">
                                    <i class="fas fa-user-doctor"></i>
                                    <span class="patient-info-label">แพทย์ :</span>
                                    <span class="doctor-name">${escapeHtml(doctorName)}</span>
                                </div>
                            </div>

                        </div>
                    </div>
                `;

            }).join('');

        } catch (error) {

            console.error(error);
            totalEl.textContent = '—';
            renderEmpty('ไม่สามารถโหลดข้อมูลผู้ป่วยได้: ' + error.message, 'fas fa-triangle-exclamation');
        }

    }

    loadWardPatients();

})();
</script>

<?php

include '../templates/footer.php';

?>
