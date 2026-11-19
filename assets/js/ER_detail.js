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

    // แท่งซ้อน: อุบัติเหตุเป็นส่วนหนึ่งของทั้งหมด → ความสูงแท่ง = ผู้รับบริการทั้งหมด (เดิมวางคู่กันชวนให้บวกกัน)
    yAxis: {
        min: 0,
        title: { text: 'จำนวนครั้ง' },
        stackLabels: {
            enabled: months.length <= 16,   // รายวันหลายแท่ง ป้ายตัวเลขจะทับกัน
            style: { fontSize: '16px', textOutline: 'none', color: '#2a1a19' }   // ค่าเริ่มต้นเป็นตัวแปร CSS ที่ไม่มีในโหมดสว่าง → ขาวบนขาว
        }
    },

    tooltip: {
        shared: true,
        valueSuffix: ' ครั้ง',
        footerFormat: 'รวม <b>{point.total:,.0f} ครั้ง</b>'
    },

    credits: { enabled: false },

    legend: { enabled: true, reversed: true },

    plotOptions: {
        column: {
            stacking: 'normal',
            maxPointWidth: 72,   // ปีงบต้นปีมีแค่ 1–2 เดือน แท่งจะกว้างเต็มจอ
            borderRadius: 4,
            borderWidth: 0
        }
    },

    series: [{
        name: 'ผู้ป่วยอื่น',
        color: '#b3261e',
        data: erData.map((n, i) => n - accidentData[i])
    }, {
        name: 'อุบัติเหตุ (ประเภทผู้ป่วย ER = อุบัติเหตุ)',
        color: '#ea580c',
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
    ...(d.border ? { borderColor: d.border, borderWidth: 1 } : {}),   // ระดับ 5 สีขาว
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
        min: 0,   // ไม่ตั้ง tickInterval ตายตัว (เดิม 2000 → ช่วง "วันนี้" แกนยาวถึง 2,000 แท่งแทบมองไม่เห็น)
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
            กำลังโหลดข้อมูล…
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
            ? `style="background:${rankColor[row.rank]};color:#1f1f1f;"`
            : 'style="background:#c62828;color:#fff;"';


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
                <td class="text-center fw-bold er-total">
                    ${row.total.toLocaleString()}
                </td>
                <td class="text-center">
                    ${row.male.toLocaleString()}
                </td>
                <td class="text-center">
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

/*
============================================================
การส่งต่อ Refer out / in แยกตามสถานพยาบาล (api/er_refer.php)
แสดง 8 แห่งแรก + รวมที่เหลือเป็น "อื่น ๆ" · แถบยาวเทียบกับยอดของกล่องนั้น
============================================================
*/

async function loadRefer() {
    const $ = id => document.getElementById(id);
    const esc = s => String(s).replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
    const fmt = n => n.toLocaleString('th-TH');
    const pct = (n, t) => t ? Math.round(n * 100 / t) : 0;

    const rows = (items, total, max = 8) => {
        const top  = items.slice(0, max);
        const rest = items.slice(max);
        if (rest.length) top.push({ name: `อื่น ๆ (${rest.length} แห่ง)`, n: rest.reduce((s, x) => s + x.n, 0) });
        if (!top.length) return '<li><span class="er-refer-nm">ไม่มีข้อมูลในช่วงนี้</span></li>';
        return top.map(x => `
            <li>
                <span class="er-refer-nm">${esc(x.name)}</span>
                <span class="er-refer-n">${fmt(x.n)}<small>${pct(x.n, total)}%</small></span>
                <span class="er-meter" aria-hidden="true"><span style="width:${pct(x.n, total)}%"></span></span>
            </li>`).join('');
    };

    let data;
    try {
        data = await (await fetch('../api/er_refer.php?' + bbhPeriod.query())).json();
    } catch (e) {
        data = { error: 'โหลดข้อมูลไม่สำเร็จ' };
    }
    if (data.error) {
        $('er-refer-out-list').innerHTML = $('er-refer-in-list').innerHTML = `<li><span class="er-refer-nm">${esc(data.error)}</span></li>`;
        return;
    }

    const o = data.out, i = data.in;
    $('er-refer-out-total').textContent = fmt(o.total);
    $('er-refer-in-total').textContent  = fmt(i.total);
    $('er-refer-out-list').innerHTML    = rows(o.items, o.total);
    $('er-refer-in-list').innerHTML     = rows(i.items, i.total);
    // เหตุผล: % ของครั้งที่มีใบส่งต่อ (ไม่มีใบ = ไม่มีเหตุผล)
    $('er-refer-reason-list').innerHTML = rows(o.reasons, o.total - o.no_doc, 5);

    $('er-refer-out-note').textContent = o.no_doc
        ? `ไม่มีใบส่งต่อในระบบ ${fmt(o.no_doc)} ครั้ง (ลงผลจำหน่ายส่งต่อแต่ไม่ได้ออกใบ จึงไม่ทราบปลายทาง)`
        : '';
    const inNotes = [];
    if (i.no_doc) inNotes.push(`ไม่มีใบรับส่งต่อ ${fmt(i.no_doc)} ครั้ง (ลงวิธีมาว่ารับส่งต่อ แต่ไม่ระบุต้นทาง)`);
    if (i.self)   inNotes.push(`ลงต้นทางเป็น รพ.บ้านบึงเอง ${fmt(i.self)} ครั้ง ควรตรวจการบันทึก`);
    $('er-refer-in-note').textContent = inNotes.join(' และ ');
}

loadRefer();

document
    .getElementById('budget-year')
    .addEventListener('change', loadRefer);

// ชื่อโรค: ไทยเป็นหลัก อังกฤษตัวเล็กใต้ชื่อ (ไม่แสดงซ้ำถ้าเหมือนกันหรือไม่มีชื่อไทย) — ใช้รูปแบบเดียวกันทั้งหน้า OPD / IPD / ER
function diagCell(th, en) {
    th = (th || '').trim();
    en = (en || '').trim();
    const sub = th && en && th !== en ? `<div class="diag-en">${en}</div>` : '';
    return `<div>${th || en || '—'}</div>${sub}`;
}