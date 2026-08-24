async function loadChart() {

/*
==========================
ปีที่เลือก
==========================
*/

const year =
    document.getElementById('budget-year').value;

const currentThaiYear =
    new Date().getFullYear() + 543;

/*
==========================
วันที่ปีงบประมาณ
==========================
*/

const startDateText = `1 ตุลาคม ${year - 1}`;

let endDateText;

if (parseInt(year) === currentThaiYear) {

    const today = new Date();

    const thaiMonths = [
        'มกราคม','กุมภาพันธ์','มีนาคม',
        'เมษายน','พฤษภาคม','มิถุนายน',
        'กรกฎาคม','สิงหาคม','กันยายน',
        'ตุลาคม','พฤศจิกายน','ธันวาคม'
    ];

    endDateText = `${today.getDate()} ${
        thaiMonths[today.getMonth()]
    } ${today.getFullYear() + 543}`;

} else {

    endDateText = `30 กันยายน ${year}`;

}

document.getElementById('budget-date-text').innerHTML =
    `ปีงบประมาณ ${year} (${startDateText} - ${endDateText})`;

/*
==========================
โหลดข้อมูล
==========================
*/

const response = await fetch('../api/er_chart.php?year=' + year);
const json     = await response.json();

const trendData = json.trend;
const pieData   = json.pie;

/*
==========================
เดือนตามปีงบประมาณ
==========================
*/

const allMonths = [
    'ต.ค.','พ.ย.','ธ.ค.',
    'ม.ค.','ก.พ.','มี.ค.',
    'เม.ย.','พ.ค.','มิ.ย.',
    'ก.ค.','ส.ค.','ก.ย.'
];

let months     = allMonths;
let monthCount = 12;

if (parseInt(year) === currentThaiYear) {

    const currentMonth = new Date().getMonth() + 1;

    const currentFiscalIndex =
        currentMonth >= 10
            ? currentMonth - 10
            : currentMonth + 2;

    monthCount = currentFiscalIndex + 1;
    months     = allMonths.slice(0, monthCount);

}

/*
==========================
Bar Chart Data
==========================
*/

let erData = Array(monthCount).fill(0);

trendData.forEach(row => {

    const month = parseInt(row.month);
    const index = month >= 10 ? month - 10 : month + 2;

    if (index < monthCount) {
        erData[index] = parseInt(row.er);
    }

});

/*
==========================
Bar Chart
==========================
*/

Highcharts.chart('er-chart', {

    chart: {
        type: 'column',
        backgroundColor: '#ffffff',
        style: { fontFamily: 'THSarabun' }
    },

    title: {
        text: 'จำนวนผู้รับบริการ ER ปีงบประมาณ ' + year
    },

    xAxis: {
        categories: months
    },

    yAxis: {
        min: 0,
        tickInterval: 250,
        endOnTick: true,
        title: { text: 'จำนวนผู้ป่วย' }
    },

    tooltip: {
        valueSuffix: ' ราย'
    },

    credits: { enabled: false },

    legend: { enabled: true },

    plotOptions: {
        column: {
            borderRadius: 4,
            dataLabels: {
                enabled: true,
                color: '#000',
                style: { fontSize: '16px' }
            }
        }
    },

    series: [{
        name: 'จำนวนผู้ป่วยงานอุบัติเหตุและฉุกเฉิน',
        color: '#dc3545',
        data: erData
    }]

});

/*
==========================
Pie Chart
==========================
*/

Highcharts.chart('er-pie-chart', {

    chart: {
        type: 'pie',
        backgroundColor: '#ffffff',
        style: { fontFamily: 'THSarabun' }
    },

    title: {
        text: 'สัดส่วนผู้รับบริการ ER ปีงบประมาณ ' + year,
        style: { fontSize: '1.2em' }
    },

    tooltip: {
        pointFormat:
            '{series.name}: <b>{point.y} ราย</b><br/>คิดเป็น: <b>{point.percentage:.2f}%</b>'
    },

    credits: { enabled: false },

    plotOptions: {
        pie: {
            allowPointSelect: true,
            cursor: 'pointer',
            dataLabels: {
                enabled: true,
                format: '<b>{point.name}</b><br>{point.percentage:.2f} %',
                style: {
                    fontSize: '16px',
                    fontFamily: 'THSarabun'
                }
            },
            showInLegend: false
        }
    },

    legend: {
        enabled: false
    },

    series: [{
        name: 'จำนวนผู้ป่วย',
        colorByPoint: true,
        data: pieData
    }]

});

}

loadChart();

document
    .getElementById('budget-year')
    .addEventListener('change', loadChart);

/*
============================================================
Triage Level Chart
============================================================
*/

async function loadTriageChart() {

const year =
    document.getElementById('budget-year').value;

/*
==========================
โหลดข้อมูล Triage
==========================
*/

const response = await fetch('../api/er_triage.php?year=' + year);
const data     = await response.json();

if (data.error) {
    console.error('Triage API error:', data.error);
    return;
}

/*
==========================
Highcharts — Bar Chart แนวนอน
==========================
*/

const categories = data.map(d => d.name);
const seriesData = data.map(d => ({
    y:     d.y,
    color: d.color,
    name:  d.name,
}));

Highcharts.chart('er-triage-chart', {

    chart: {
        type: 'bar',
        backgroundColor: '#ffffff',
        style: { fontFamily: 'THSarabun' }
    },

    title: {
        text: 'Triage Level ปีงบประมาณ ' + year,
        style: { fontSize: '1.2em' }
    },

    xAxis: {
        categories: categories,
        labels: {
            style: {
                fontSize: '16px',
                fontFamily: 'THSarabun',
                fontWeight: 'normal'
            }
        }
    },

    yAxis: {
        min: 0,
        tickInterval: 2000,
        title: { text: 'จำนวนผู้ป่วย (ราย)' },
        labels: {
            style: { fontSize: '13px',
                     fontWeight: 'bold' 
                }
        }
    },

    tooltip: {
        formatter: function () {
            const total = data.reduce((s, d) => s + d.y, 0);
            const pct   = total > 0
                ? ((this.y / total) * 100).toFixed(2)
                : '0.00';
            return `<b>${this.x}</b><br/>
                    จำนวน: <b>${this.y.toLocaleString()} ราย</b><br/>
                    คิดเป็น: <b>${pct}%</b>`;
        }
    },

    plotOptions: {
        bar: {
            borderRadius: 4,
            dataLabels: {
                enabled: true,
                formatter: function () {
                    const total = data.reduce((s, d) => s + d.y, 0);
                    const pct   = total > 0
                        ? ((this.y / total) * 100).toFixed(1)
                        : '0.0';
                    return `${this.y.toLocaleString()} ราย (${pct}%)`;
                },
                style: {
                    fontSize: '14px',
                    fontFamily: 'THSarabun',
                    fontWeight: 'normal',
                    textOutline: 'none'
                }
            }
        }
    },

    legend: { enabled: false },
    credits: { enabled: false },

    series: [{
        name: 'จำนวนผู้ป่วย',
        colorByPoint: true,
        data: seriesData
    }]

});

}

loadTriageChart();

document
    .getElementById('budget-year')
    .addEventListener('change', loadTriageChart);

/*
============================================================
Time Slot Chart
============================================================
*/

async function loadTimeSlotChart() {

const year =
    document.getElementById('budget-year').value;

const response = await fetch('../api/er_timeslot.php?year=' + year);
const data     = await response.json();

if (data.error) {
    console.error('TimeSlot API error:', data.error);
    return;
}

/*
==========================
Highcharts — Column Chart
==========================
*/

const total = data.reduce((s, d) => s + d.y, 0);

Highcharts.chart('er-timeslot-chart', {

    chart: {
        type: 'column',
        backgroundColor: '#ffffff',
        style: { fontFamily: 'THSarabun' }
    },

    title: {
        text: 'ช่วงเวลาที่ผู้ป่วยเข้า ER ปีงบประมาณ ' + year,
        style: { fontSize: '1.2em' }
    },

    xAxis: {
        categories: data.map(d => d.name),
        labels: {
            style: {
                fontSize: '16px',
                fontFamily: 'THSarabun',
                fontWeight: 'normal'
            }
        }
    },

    yAxis: {
        min: 0,
        title: { text: 'จำนวนผู้ป่วย (ราย)' },
        labels: {
            style: { fontSize: '13px' }
        }
    },

    tooltip: {
        formatter: function () {
            const pct = total > 0
                ? ((this.y / total) * 100).toFixed(2)
                : '0.00';
            return `<b>${this.x}</b><br/>
                    จำนวน: <b>${this.y.toLocaleString()} ราย</b><br/>
                    คิดเป็น: <b>${pct}%</b>`;
        }
    },

    plotOptions: {
        column: {
            borderRadius: 6,
            colorByPoint: true,
            dataLabels: {
                enabled: true,
                formatter: function () {
                    const pct = total > 0
                        ? ((this.y / total) * 100).toFixed(1)
                        : '0.0';
                    return `${this.y.toLocaleString()} ราย<br/>(${pct}%)`;
                },
                style: {
                    fontSize: '14px',
                    fontFamily: 'THSarabun',
                    fontWeight: 'normal',
                    textOutline: 'none'
                },
                useHTML: true
            }
        }
    },

    colors: data.map(d => d.color),
    legend: { enabled: false },
    credits: { enabled: false },

    series: [{
        name: 'จำนวนผู้ป่วย',
        data: data.map(d => ({
            y:     d.y,
            color: d.color,
            name:  d.name
        }))
    }]

});

}

loadTimeSlotChart();

document
    .getElementById('budget-year')
    .addEventListener('change', loadTimeSlotChart);
/*
============================================================
Top 10 Diagnosis ER
============================================================
*/

async function loadTop10Table() {

const year =
    document.getElementById('budget-year').value;

const tbody =
    document.getElementById('er-top10-tbody');

// แสดง loading
tbody.innerHTML = `
    <tr>
        <td colspan="7" class="text-center text-muted py-4">
            <i class="fa-solid fa-spinner fa-spin me-2"></i>
            กำลังโหลดข้อมูล...
        </td>
    </tr>`;

try {

    const response = await fetch('../api/er_top10_diagnosis.php?year=' + year);
    const data     = await response.json();

    if (data.error) {
        tbody.innerHTML = `
            <tr>
                <td colspan="7" class="text-center text-danger py-3">
                    เกิดข้อผิดพลาด: ${data.error}
                </td>
            </tr>`;
        return;
    }

    if (!data.length) {
        tbody.innerHTML = `
            <tr>
                <td colspan="7" class="text-center text-muted py-3">
                    ไม่พบข้อมูล
                </td>
            </tr>`;
        return;
    }

    // สีแถวอันดับ 1-3
    const rankColor = {
        1: '#FFD700',
        2: '#C0C0C0',
        3: '#CD7F32',
    };

    let html = '';

    data.forEach(row => {

        const badgeBg = rankColor[row.rank]
            ? `style="background:${rankColor[row.rank]};color:#333;"`
            : 'style="background:#198754;color:#fff;"';

        const diagName = row.diag_th
            ? row.diag_th
            : (row.diag_en || '—');

        html += `
            <tr>
                <td class="text-center">
                    <span class="rank-badge" ${badgeBg}>
                        ${row.rank}
                    </span>
                </td>
                <td class="text-center">
                    <span class="badge bg-secondary"
                          style="font-size:14px;letter-spacing:1px">
                        ${row.icd10 || '—'}
                    </span>
                </td>
                <td>${diagName}</td>
                <td class="text-muted" style="font-size:14px">
                    ${row.diag_en || '—'}
                </td>
                <td class="text-center fw-bold text-success">
                    ${row.total.toLocaleString()}
                </td>
                <td class="text-center" style="color:#3b82f6">
                    ${row.male.toLocaleString()}
                </td>
                <td class="text-center" style="color:#ec4899">
                    ${row.female.toLocaleString()}
                </td>
            </tr>`;
    });

    tbody.innerHTML = html;

} catch (e) {
    tbody.innerHTML = `
        <tr>
            <td colspan="7" class="text-center text-danger py-3">
                ไม่สามารถโหลดข้อมูลได้
            </td>
        </tr>`;
}

}

loadTop10Table();

document
    .getElementById('budget-year')
    .addEventListener('change', loadTop10Table);