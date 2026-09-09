<?php

include '../templates/header.php';
include '../templates/navbar.php';
include '../templates/sidebar.php';

$ward = trim((string)($_GET['ward'] ?? ''));

?>

<style>
/* =========================================================
   IPD WARD DETAIL
   Patient Card + เพศ/ช่วงอายุ + แพทย์ผู้รับผิดชอบ
========================================================= */
.ipd-ward-page {
    --ipd-green: #198754;
    --ipd-blue: #1683c5;
    --ipd-border: #e3e8ec;
    --ipd-muted: #7b8794;
}

.ipd-ward-page .ward-page-header {
    margin-bottom: 14px;
}

.ipd-ward-page .ward-page-title {
    display: flex;
    align-items: center;
    gap: 10px;
    color: var(--ipd-green);
    font-weight: bold;
    margin: 0;
    font-size: 2rem;
}

.ipd-ward-page .ward-page-title i {
    font-size: 1.8rem;
}

.ipd-ward-page .ward-page-subtitle {
    color: var(--ipd-muted);
    margin-top: 2px;
    font-size: 1.05rem;
}

.ipd-ward-page .back-btn {
    border-radius: 7px;
    padding: 7px 13px;
    white-space: nowrap;
}

.ipd-ward-page .ward-main-card {
    border: 1px solid #dfe5e9;
    border-radius: 12px;
    box-shadow: 0 3px 10px rgba(0, 0, 0, .08);
    background: #fff;
    overflow: hidden;
}

.ipd-ward-page .ward-main-card-body {
    padding: 14px;
}

/* Summary */
.ipd-ward-page .ward-summary {
    width: 300px;
    min-height: 115px;
    border-left: 5px solid var(--ipd-green);
    border-radius: 14px;
    background: #fff;
    box-shadow: 0 4px 12px rgba(0, 0, 0, .13);
    padding: 16px 22px;
    margin-bottom: 18px;
}

.ipd-ward-page .ward-summary-title {
    color: var(--ipd-green);
    font-size: 1.2rem;
    font-weight: bold;
}

.ipd-ward-page .ward-summary-number {
    color: var(--ipd-green);
    font-size: 2.15rem;
    line-height: 1.1;
    font-weight: bold;
    text-align: center;
    margin-top: 7px;
}

/* Patient cards */
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
    min-height: 188px;
    background: #fff;
    border: 1px solid var(--ipd-border);
    border-top: 3px solid var(--ipd-green);
    border-radius: 5px;
    padding: 9px 10px 10px;
    box-shadow: 0 2px 5px rgba(0, 0, 0, .07);
    transition: transform .15s ease, box-shadow .15s ease;
}

.ipd-ward-page .patient-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 14px rgba(0, 0, 0, .13);
}

.ipd-ward-page .patient-top {
    display: flex;
    align-items: flex-start;
    gap: 9px;
    min-height: 48px;
}

.ipd-ward-page .patient-avatar {
    width: 44px;
    height: 44px;
    flex: 0 0 44px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: #eef7f2;
    color: var(--ipd-green);
    font-size: 1.65rem;
}

.ipd-ward-page .patient-avatar.avatar-male {
    color: #1683c5;
    background: #edf7fc;
}

.ipd-ward-page .patient-avatar.avatar-female {
    color: #d85a8a;
    background: #fff0f5;
}

.ipd-ward-page .patient-avatar.avatar-child-male {
    color: #1683c5;
    background: #edf7fc;
}

.ipd-ward-page .patient-avatar.avatar-child-female {
    color: #d85a8a;
    background: #fff0f5;
}

.ipd-ward-page .patient-name {
    color: #34495e;
    font-size: 1.05rem;
    line-height: 1.15;
    font-weight: bold;
    padding-top: 2px;
}

.ipd-ward-page .patient-meta {
    color: #8a949d;
    font-size: .91rem;
    margin-top: 3px;
}

.ipd-ward-page .patient-badges {
    display: flex;
    justify-content: flex-end;
    flex-wrap: wrap;
    gap: 4px;
    margin-top: 5px;
}

.ipd-ward-page .patient-badge {
    display: inline-block;
    padding: 2px 7px;
    border-radius: 10px;
    background: var(--ipd-blue);
    color: #fff;
    font-size: .83rem;
    font-weight: bold;
    line-height: 1.35;
}

.ipd-ward-page .patient-badge.bed-badge {
    background: var(--ipd-green);
}

.ipd-ward-page .patient-info {
    border-top: 1px solid #eef1f3;
    margin-top: 7px;
    padding-top: 6px;
    font-size: .93rem;
    line-height: 1.5;
}

.ipd-ward-page .patient-info strong {
    color: #333;
}

.ipd-ward-page .patient-info .value {
    color: var(--ipd-blue);
    font-weight: bold;
}

.ipd-ward-page .patient-info .admit-date {
    color: #777;
}

.ipd-ward-page .doctor-row {
    margin-top: 4px;
    white-space: normal;
}

.ipd-ward-page .doctor-row i {
    color: var(--ipd-green);
    width: 17px;
    text-align: center;
    margin-right: 3px;
}

.ipd-ward-page .doctor-name {
    color: #1683c5;
    font-weight: bold;
}

.ipd-ward-page .empty-state {
    padding: 45px 15px;
    color: #7b8794;
    text-align: center;
}

.ipd-ward-page .empty-state i {
    font-size: 2.5rem;
    margin-bottom: 10px;
    color: #adb5bd;
}

@media (max-width: 991px) {
    .ipd-ward-page .ward-summary {
        width: 100%;
    }
}

@media (max-width: 575px) {
    .ipd-ward-page .ward-page-title {
        font-size: 1.55rem;
    }

    .ipd-ward-page .ward-page-header .d-flex {
        align-items: flex-start !important;
        gap: 10px;
    }

    .ipd-ward-page .back-btn {
        font-size: .9rem;
        padding: 6px 9px;
    }
}
</style>

<div class="content-wrapper ipd-ward-page">

    <section class="content pt-3">

        <div class="container-fluid">

            <!-- PAGE HEADER -->
            <div class="ward-page-header d-flex align-items-center justify-content-between">

                <div>
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
                        <div class="ward-summary-title">
                            <i class="fa-solid fa-hospital-user mr-1"></i>
                            ผู้ป่วยที่ Admit อยู่
                        </div>
                        <div class="ward-summary-number" id="ward-total">—</div>
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
                                <div>
                                    <strong>HN :</strong>
                                    <span class="value">${escapeHtml(patient.hn || '-')}</span>
                                </div>
                                <div>
                                    <strong>วันนอน :</strong>
                                    <span class="value">${escapeHtml(stayText)}</span>
                                </div>
                                <div>
                                    <strong>Admit :</strong>
                                    <span class="admit-date">${escapeHtml(formatDate(patient.regdate))}</span>
                                </div>
                                <div class="doctor-row">
                                    <i class="fas fa-user-doctor"></i>
                                    <strong>แพทย์ :</strong>
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
