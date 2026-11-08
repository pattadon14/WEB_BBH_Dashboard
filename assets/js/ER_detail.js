async function loadChart() {

/*
==========================
โหลดข้อมูลตามช่วงเวลาที่เลือก (assets/js/period_filter.js)
ช่วงสั้น API คืนรายวัน ช่วงยาวคืนรายเดือน พร้อม label ของแต่ละช่อง
==========================
*/

const response = await fetch('../api/er_chart.php?' + bbhPeriod.query());
const json     = await response.json();

if (json.error) {
    console.error('ER chart API error:', json.error);
    return;
}

const trendData    = json.trend;
const pieData      = json.pie;
const months       = trendData.map(row => row.label);
const erData       = trendData.map(row => Number(row.er) || 0);
const accidentData = trendData.map(row => Number(row.accident) || 0);

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

    title: { text: null },

    xAxis: {
        categories: months
    },

    yAxis: {
        min: 0,
        endOnTick: true,
        title: { text: 'จำนวนครั้ง' }
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
                enabled: months.length <= 16, // รายวันหลายแท่ง ป้ายตัวเลขจะทับกัน
                color: '#000',
                style: { fontSize: '16px' }
            }
        }
    },

    series: [{
        name: 'ผู้รับบริการ ER ทั้งหมด',
        color: '#dc3545',
        data: erData
    }, {
        name: 'เฉพาะผู้ป่วยอุบัติเหตุ',
        color: '#f59e0b',
        data: accidentData
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

    title: { text: null },

    tooltip: {
        pointFormat:
            '{series.name}: <b>{point.y} ราย</b><br/>คิดเป็น: <b>{point.percentage:.2f}%</b>'
    },

    credits: { enabled: false },

    // โดนัท + คำอธิบายด้านขวา: ป้ายบนชิ้นเหลือแค่ % ไม่ทับกัน
    plotOptions: {
        pie: {
            innerSize: '55%',
            allowPointSelect: true,
            cursor: 'pointer',
            dataLabels: {
                enabled: true,
                distance: 14,
                format: '{point.percentage:.1f}%',
                style: {
                    fontSize: '17px',
                    fontFamily: 'THSarabun',
                    textOutline: 'none'
                }
            },
            showInLegend: true
        }
    },

    legend: {
        enabled: true,
        layout: 'vertical',
        align: 'right',
        verticalAlign: 'middle',
        itemMarginBottom: 6,
        labelFormat: '{name} <b>{y:,.0f}</b>',
        itemStyle: { fontSize: '17px', fontWeight: 'normal' }
    },

    responsive: {
        rules: [{
            condition: { maxWidth: 520 },
            chartOptions: { legend: { layout: 'horizontal', align: 'center', verticalAlign: 'bottom' } }
        }]
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


/*
==========================
โหลดข้อมูล Triage
==========================
*/

const response = await fetch('../api/er_triage.php?' + bbhPeriod.query());
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

    title: { text: null },

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


const response = await fetch('../api/er_timeslot.php?' + bbhPeriod.query());
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

    title: { text: null },

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


const tbody =
    document.getElementById('er-top10-tbody');

// แสดง loading
tbody.innerHTML = `
    <tr>
        <td colspan="6" class="text-center text-muted py-4">
            <i class="fa-solid fa-spinner fa-spin me-2"></i>
            กำลังโหลดข้อมูล...
        </td>
    </tr>`;

try {

    const response = await fetch('../api/er_top10_diagnosis.php?' + bbhPeriod.query());
    const data     = await response.json();

    if (data.error) {
        tbody.innerHTML = `
            <tr>
                <td colspan="6" class="text-center text-danger py-3">
                    เกิดข้อผิดพลาด: ${data.error}
                </td>
            </tr>`;
        return;
    }

    if (!data.length) {
        tbody.innerHTML = `
            <tr>
                <td colspan="6" class="text-center text-muted py-3">
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
                <td>${diagCell(row.diag_th, row.diag_en)}</td>
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
            <td colspan="6" class="text-center text-danger py-3">
                ไม่สามารถโหลดข้อมูลได้
            </td>
        </tr>`;
}

}

loadTop10Table();

document
    .getElementById('budget-year')
    .addEventListener('change', loadTop10Table);

// ชื่อโรค: ไทยเป็นหลัก อังกฤษตัวเล็กใต้ชื่อ (ไม่แสดงซ้ำถ้าเหมือนกันหรือไม่มีชื่อไทย) — ใช้รูปแบบเดียวกันทั้งหน้า OPD / IPD / ER
function diagCell(th, en) {
    th = (th || '').trim();
    en = (en || '').trim();
    const sub = th && en && th !== en ? `<div class="diag-en">${en}</div>` : '';
    return `<div>${th || en || '—'}</div>${sub}`;
}