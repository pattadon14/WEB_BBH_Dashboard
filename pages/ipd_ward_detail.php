<?php

include '../templates/header.php';
include '../templates/navbar.php';
include '../templates/sidebar.php';

$ward = trim((string)($_GET['ward'] ?? ''));

?>

<div class="content-wrapper">

    <section class="content pt-3">

        <div class="container-fluid">

            <div class="d-flex align-items-center justify-content-between mb-3">

                <div>
                    <h2 class="text-success mb-0" style="font-weight:bold;">
                        <i class="fa-solid fa-bed-pulse"></i>
                        <span id="ward-title">ข้อมูลผู้ป่วยใน</span>
                    </h2>
                    <div class="text-muted" id="ward-subtitle">
                        กำลังโหลดข้อมูล Ward...
                    </div>
                </div>

                <a href="<?= BASE_URL ?>index.php" class="btn btn-outline-success">
                    <i class="fas fa-arrow-left mr-1"></i>
                    กลับหน้าหลัก
                </a>

            </div>

            <div class="card shadow-sm">

                <div class="card-body">

                    <div class="row mb-3">

                        <div class="col-md-4 mb-2">
                            <div class="summary-card">
                                <div class="summary-title text-success">
                                    <i class="fa-solid fa-hospital-user"></i>
                                    ผู้ป่วยที่ Admit อยู่
                                </div>
                                <div class="summary-number text-success" id="ward-total">
                                    —
                                </div>
                            </div>
                        </div>

                    </div>

                    <div class="table-responsive">

                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th class="text-center" style="width:80px;">ลำดับ</th>
                                    <th class="text-center" style="width:120px;">AN</th>
                                    <th class="text-center" style="width:120px;">HN</th>
                                    <th>ชื่อ - นามสกุล</th>
                                    <th class="text-center" style="width:160px;">วันที่ Admit</th>
                                </tr>
                            </thead>
                            <tbody id="ward-patient-body">
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">
                                        กำลังโหลดข้อมูล...
                                    </td>
                                </tr>
                            </tbody>
                        </table>

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
    const bodyEl = document.getElementById('ward-patient-body');

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function formatDate(value) {
        if (!value) return '-';

        const date = new Date(value);
        if (Number.isNaN(date.getTime())) return value;

        return date.toLocaleDateString('th-TH', {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit'
        });
    }

    async function loadWardPatients() {

        if (!ward || !/^\d+$/.test(ward)) {
            titleEl.textContent = 'ข้อมูลผู้ป่วยใน';
            subtitleEl.textContent = 'ไม่พบรหัส Ward';
            totalEl.textContent = '0';
            bodyEl.innerHTML = `
                <tr>
                    <td colspan="5" class="text-center text-danger py-4">
                        ไม่พบรหัส Ward ที่ถูกต้อง
                    </td>
                </tr>`;
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
                bodyEl.innerHTML = `
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">
                            ไม่พบผู้ป่วยที่กำลัง Admit อยู่ใน Ward นี้
                        </td>
                    </tr>`;
                return;
            }

            bodyEl.innerHTML = data.patients.map((patient, index) => `
                <tr>
                    <td class="text-center">${index + 1}</td>
                    <td class="text-center font-weight-bold">${escapeHtml(patient.an)}</td>
                    <td class="text-center">${escapeHtml(patient.hn)}</td>
                    <td>${escapeHtml(patient.patient_name || '-')}</td>
                    <td class="text-center">${escapeHtml(formatDate(patient.regdate))}</td>
                </tr>
            `).join('');

        } catch (error) {

            console.error(error);

            bodyEl.innerHTML = `
                <tr>
                    <td colspan="5" class="text-center text-danger py-4">
                        ไม่สามารถโหลดข้อมูลผู้ป่วยได้
                        <br>
                        <small>${escapeHtml(error.message)}</small>
                    </td>
                </tr>`;
        }

    }

    loadWardPatients();

})();
</script>

<?php

include '../templates/footer.php';

?>
