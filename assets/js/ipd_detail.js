
async function loadKpiSummary() {


    try {

        const response =
            await fetch(
                '../api/ipd_kpi_summary.php?' + bbhPeriod.query(),
                { cache: 'no-store' }
            );

        if (!response.ok) {
            throw new Error('HTTP ' + response.status);
        }

        const data =
            await response.json();

        if (data.error) {
            throw new Error(data.error);
        }

        const numberFormat = new Intl.NumberFormat('th-TH');

        document.getElementById('kpi-current-inpatients').textContent =
            numberFormat.format(data.current_inpatients);

        document.getElementById('kpi-admissions').textContent =
            numberFormat.format(data.admissions);

        document.getElementById('kpi-discharges').textContent =
            numberFormat.format(data.discharges);

        document.getElementById('kpi-total-beds').textContent =
            numberFormat.format(data.total_beds);

        const occupancyRate = Number(data.occupancy_rate || 0);

        document.getElementById('kpi-occupancy-rate').textContent =
            occupancyRate.toLocaleString('th-TH', {
                minimumFractionDigits: 1,
                maximumFractionDigits: 1
            }) + '%';

        const occupancyCard =
            document.querySelector('.ipd-kpi-card.kpi-occupancy');

        const occupancyStatus =
            document.getElementById('kpi-occupancy-status');

        if (occupancyCard && occupancyStatus) {

            occupancyCard.classList.remove(
                'kpi-status-normal',
                'kpi-status-warning',
                'kpi-status-danger'
            );

            if (occupancyRate > 100) {

                occupancyCard.classList.add('kpi-status-danger');
                occupancyStatus.innerHTML =
                    '<i class="fa-solid fa-triangle-exclamation"></i> เกิน 100% — เตียงเต็มเกินศักยภาพ';

            } else if (occupancyRate >= 90) {

                occupancyCard.classList.add('kpi-status-warning');
                occupancyStatus.innerHTML =
                    '<i class="fa-solid fa-circle-exclamation"></i> 90–100% — ใกล้เต็ม';

            } else {

                occupancyCard.classList.add('kpi-status-normal');
                occupancyStatus.innerHTML =
                    '<i class="fa-solid fa-circle-check"></i> ต่ำกว่า 90%';

            }
        }

        document.getElementById('kpi-avg-los').textContent =
            Number(data.avg_los || 0).toLocaleString('th-TH', {
                minimumFractionDigits: 1,
                maximumFractionDigits: 1
            });

        const readmissionRate = Number(data.readmission_rate || 0);

        document.getElementById('kpi-readmission-rate').textContent =
            readmissionRate.toLocaleString('th-TH', {
                minimumFractionDigits: 1,
                maximumFractionDigits: 1
            }) + '%';

        document.getElementById('kpi-readmission-cases').textContent =
            numberFormat.format(data.readmission_cases) + ' ราย';

        const readmissionCard =
            document.querySelector('.ipd-kpi-card.kpi-readmission');

        const readmissionStatus =
            document.getElementById('kpi-readmission-status');

        if (readmissionCard && readmissionStatus) {

            readmissionCard.classList.remove(
                'kpi-status-normal',
                'kpi-status-warning',
                'kpi-status-danger'
            );

            if (readmissionRate > 10) {

                readmissionCard.classList.add('kpi-status-danger');
                readmissionStatus.innerHTML =
                    '<i class="fa-solid fa-triangle-exclamation"></i> สูงกว่า 10%';

            } else if (readmissionRate >= 8) {

                readmissionCard.classList.add('kpi-status-warning');
                readmissionStatus.innerHTML =
                    '<i class="fa-solid fa-circle-exclamation"></i> ช่วงเฝ้าระวัง 8–10%';

            } else {

                readmissionCard.classList.add('kpi-status-normal');
                readmissionStatus.innerHTML =
                    '<i class="fa-solid fa-circle-check"></i> เป้าหมาย < 8%';

            }
        }

    } catch (error) {

        console.error('IPD KPI Summary Error:', error);

        [
            'kpi-current-inpatients',
            'kpi-admissions',
            'kpi-discharges',
            'kpi-total-beds',
            'kpi-occupancy-rate',
            'kpi-avg-los',
            'kpi-readmission-rate'
        ].forEach(id => {

            const element =
                document.getElementById(id);

            if (element) {
                element.textContent = '-';
            }

        });

        const cases =
            document.getElementById('kpi-readmission-cases');

        if (cases) {
            cases.textContent = 'โหลดข้อมูลไม่สำเร็จ';
        }

        const occupancyStatus =
            document.getElementById('kpi-occupancy-status');

        if (occupancyStatus) {
            occupancyStatus.textContent = 'ไม่สามารถประเมินได้';
        }

        const readmissionStatus =
            document.getElementById('kpi-readmission-status');

        if (readmissionStatus) {
            readmissionStatus.textContent = 'ไม่สามารถประเมินได้';
        }
    }
}

async function loadChart() {

    /*
    ==========================
    รับใหม่ + อัตราครองเตียง ต่อช่อง (วัน/เดือน ตามช่วงที่เลือก) — api/bed_occupancy_trend.php
    ==========================
    */

    const bedResponse =
        await fetch(
            '../api/bed_occupancy_trend.php?' + bbhPeriod.query()
        );

    const bedData =
        await bedResponse.json();

    if (bedData.error) {
        console.error('IPD trend API error:', bedData.error);
        return;
    }

    const months = bedData.data.map(row => row.label);
    const ipdData = bedData.data.map(row => Number(row.admit) || 0);
    const occupancyData = bedData.data.map(row => Number(row.occupancy_rate) || 0);
    /*
    ==========================
    กราฟผู้ป่วยใน
    ==========================
    */

    Highcharts.chart('ipd-chart', {

        chart: {
            type: 'column',
            backgroundColor: '#ffffff',
            style: {
                fontFamily: 'THSarabun'
            }
        },

        title: { text: null },

        xAxis: {
            categories: months
        },

        yAxis: {

            title: {
                text: 'จำนวน'
            },

            allowDecimals: false
        },

        credits: {
            enabled: false
        },

        series: [

            {

                name: 'ผู้ป่วยใน',

                color: '#4aafbd',

                data: ipdData,

                dataLabels: {

                    enabled: months.length <= 16, // รายวันหลายแท่ง ป้ายตัวเลขจะทับกัน

                    color: '#333333',

                    style: {
                        fontSize: '15px',
                        fontWeight: 'bold',
                        textOutline: 'none'
                    }
                }

            }

        ]

    });

    /*
    ==========================
    กราฟอัตราครองเตียง
    ==========================
    */

    Highcharts.chart('bed-occupancy-chart', {

        chart: {
            zoomType: 'xy',
            backgroundColor: '#ffffff',
            style: {
                fontFamily: 'THSarabun'
            }
        },

        title: { text: null },

        xAxis: {
            categories: months
        },

        // แกนเดียว: เดิมมีเส้น "แนวโน้ม" ที่เป็นข้อมูลชุดเดียวกับแท่งซ้ำบนแกนที่ 2
        yAxis: {
            min: 0,
            softMax: 100,
            title: {
                text: 'อัตราการครองเตียง (%)'
            },
            labels: {
                format: '{value}%'
            }
        },

        tooltip: {

            shared: true,

            valueSuffix: '%'
        },

        credits: {
            enabled: false
        },

        series: [

            {

                type: 'column',

                name: 'อัตราครองเตียง',

                color: '#4aafbd',

                data: occupancyData,

                dataLabels: {

                    enabled: months.length <= 16, // รายวันหลายแท่ง ป้ายตัวเลขจะทับกัน

                    format: '{y:.1f}%',

                    style: {
                        fontSize: '15px',
                        fontWeight: 'bold',
                        textOutline: 'none',
                        color: '#333333'
                    }
                }

            }

        ]

    });

}
async function reloadAll() {

    await loadKpiSummary();

    await loadChart();

    await loadTop10Disease();

}

reloadAll();

document.getElementById('budget-year')
    .addEventListener('change', reloadAll);

async function loadTop10Disease() {

    try {


        const response =
            await fetch(
                '../api/ipd_top10_disease.php?' + bbhPeriod.query()
            );

        const data =
            await response.json();

        console.log('TOP10 IPD', data);

        const rankColor = {
            1: 'background:#FFD700;color:#333;',
            2: 'background:#C0C0C0;color:#333;',
            3: 'background:#CD7F32;color:#fff;',
        };

        let html = '';

        data.forEach((row, index) => {

            const rank      = index + 1;
            const badgeStyle = rankColor[rank]
                ? rankColor[rank]
                : 'background:#198754;color:#fff;';


            html += `
                <tr>
                    <td class="text-center">
                        <span class="rank-badge" style="${badgeStyle}">
                            ${rank}
                        </span>
                    </td>
                    <td class="text-center">
                        <span class="badge bg-secondary"
                              style="font-size:14px;letter-spacing:1px">
                            ${row.pdx ?? '—'}
                        </span>
                    </td>
                    <td>${diagCell(row.tname, row.diag)}</td>
                    <td class="text-center fw-bold text-success">
                        ${Number(row.total_case || 0).toLocaleString()}
                    </td>
                    <td class="text-center" style="color:#3b82f6">
                        ${Number(row.male || 0).toLocaleString()}
                    </td>
                    <td class="text-center" style="color:#ec4899">
                        ${Number(row.female || 0).toLocaleString()}
                    </td>
                </tr>
            `;
        });

        document.getElementById('top10-ipd-body').innerHTML = html ||
            '<tr><td colspan="6" class="text-center text-muted py-3">ไม่พบข้อมูลผู้ป่วยที่จำหน่ายในช่วงนี้</td></tr>';

    } catch (err) {

        console.error('Top10 Disease Error', err);

        document.getElementById('top10-ipd-body').innerHTML = `
            <tr>
                <td colspan="6" class="text-center text-danger">
                    ไม่สามารถโหลดข้อมูลได้
                </td>
            </tr>
        `;
    }
}

// ชื่อโรค: ไทยเป็นหลัก อังกฤษตัวเล็กใต้ชื่อ (ไม่แสดงซ้ำถ้าเหมือนกันหรือไม่มีชื่อไทย) — ใช้รูปแบบเดียวกันทั้งหน้า OPD / IPD / ER
function diagCell(th, en) {
    th = (th || '').trim();
    en = (en || '').trim();
    const sub = th && en && th !== en ? `<div class="diag-en">${en}</div>` : '';
    return `<div>${th || en || '—'}</div>${sub}`;
}