// คลิกกราฟ/ตาราง/แถว → รายชื่อผู้ป่วยของช่วงเวลาที่เลือก (erOpenList ใน er_summary.js · api/er_visit_list.php ต้อง login)
// o = { title, type, v?, d1?, d2?, sub?, keep? }
function erPeriodList(o) {
    const s = bbhPeriod.state;
    window.erOpenList({ d1: s.d1, d2: s.d2, sub: bbhPeriod.label(), ...o });
}
const ER_SHIFT_TYPE = { 1: 'shift_morning', 2: 'shift_evening', 3: 'shift_night' };   // order ของ api/er_timeslot.php
const erIso = d => d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');

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
        style: { fontFamily: 'BBH Sans Chart' }
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
            borderWidth: 0,
            cursor: 'pointer',
            // คลิกแท่ง → รายชื่อของวัน/เดือนนั้น (ตัดให้อยู่ในช่วงที่เลือก) แยกตามส่วนที่คลิก
            point: { events: { click() {
                const t = trendData[this.index], s = bbhPeriod.state;
                let d2 = t.start;
                if (json.unit === 'month') { const [y, m] = t.start.split('-').map(Number); d2 = erIso(new Date(y, m, 0)); }
                const acc = this.series.index === 1;
                erPeriodList({
                    title: acc ? 'ผู้ป่วยอุบัติเหตุ' : 'ผู้ป่วยอื่น (ไม่ใช่อุบัติเหตุ)', sub: t.label,
                    d1: t.start < s.d1 ? s.d1 : t.start, d2: d2 > s.d2 ? s.d2 : d2,
                    type: acc ? 'pt' : 'non_accident', v: acc ? 2 : undefined
                });
            } } }
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
ประเภทผู้ป่วย — แท่งแนวนอนเรียงตามรหัสประเภท (เดิมเป็นโดนัท: ชิ้นเล็กอ่านยาก ต้องดูคำอธิบายสลับกับชิ้น)
ป้าย "จำนวน (%)" ที่ปลายแท่ง · tooltip บอกระดับ 1–2 และ Admit ของประเภทนั้น · คลิกดูรายชื่อ
==========================
*/

const typeTotal = pieData.reduce((s, d) => s + d.y, 0);
const typePct   = n => typeTotal ? (n * 100 / typeTotal).toFixed(1) : '0.0';

Highcharts.chart('er-pie-chart', {

    chart: {
        type: 'bar',
        backgroundColor: '#ffffff',
        style: { fontFamily: 'BBH Sans Chart' }
    },

    title: { text: null },

    xAxis: {
        categories: pieData.map(d => d.name),
        labels: { style: { fontSize: '14px', fontWeight: 'normal' } }
    },

    yAxis: {
        min: 0,
        title: { text: 'จำนวนครั้ง' },
        labels: { style: { fontSize: '13px' } }
    },

    tooltip: {
        formatter: function () {
            const d = this.point.options;
            return `<b>${d.name}</b><br/>
                    จำนวน: <b>${d.y.toLocaleString()} ครั้ง</b> (${typePct(d.y)}%)<br/>
                    ระดับ 1–2: <b>${d.l12.toLocaleString()}</b>, Admit: <b>${d.admit.toLocaleString()}</b><br/>
                    คลิกเพื่อดูรายชื่อ`;
        }
    },

    plotOptions: {
        bar: {
            borderRadius: 4,
            colorByPoint: true,
            cursor: 'pointer',
            dataLabels: {
                enabled: true,
                formatter: function () { return `${this.y.toLocaleString()} (${typePct(this.y)}%)`; },
                style: { fontSize: '14px', fontWeight: 'normal', textOutline: 'none' }
            },
            point: { events: { click() {
                const d = this.options;
                if (d.code) erPeriodList({ title: 'ประเภท: ' + d.name, type: 'pt', v: d.code });
            } } }
        }
    },

    legend: { enabled: false },
    credits: { enabled: false },

    series: [{
        name: 'จำนวนผู้ป่วย',
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
        style: { fontFamily: 'BBH Sans Chart' }
    },

    title: { text: null },

    xAxis: {
        categories: categories,
        labels: {
            style: {
                fontSize: '16px',
                fontFamily: 'BBH Sans Chart',
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
            cursor: 'pointer',
            point: { events: { click() {
                const d = data[this.index];
                erPeriodList({ title: 'ระดับความเร่งด่วน: ' + d.name, type: 'lvl', v: d.id });
            } } },
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
                    fontFamily: 'BBH Sans Chart',
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
        style: { fontFamily: 'BBH Sans Chart' }
    },

    title: { text: null },

    xAxis: {
        categories: data.map(d => d.name),
        labels: {
            style: {
                fontSize: '16px',
                fontFamily: 'BBH Sans Chart',
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
            cursor: 'pointer',
            point: { events: { click() {
                const d = data[this.index];
                if (ER_SHIFT_TYPE[d.order]) erPeriodList({ title: 'เวร' + d.name, type: ER_SHIFT_TYPE[d.order] });
            } } },
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
                    fontFamily: 'BBH Sans Chart',
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


        // คลิกแถว → รายชื่อผู้ป่วยโรคนั้น (นิยามเดียวกับตาราง: ไม่นับงานบริการ 4, 5)
        html += `
            <tr data-pdx="${row.icd10}" data-dx="${(row.diag_th || row.diag_en || '').replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]))}" role="button" tabindex="0" title="คลิกเพื่อดูรายชื่อ">
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

(function () {
    const tbody = document.getElementById('er-top10-tbody');
    const go = e => {
        const tr = e.target.closest('tr[data-pdx]');
        if (!tr) return;
        if (e.type === 'keydown') { if (e.key !== 'Enter' && e.key !== ' ') return; e.preventDefault(); }
        erPeriodList({ title: `${tr.dataset.pdx} ${tr.dataset.dx}`.trim(), type: 'pdx', v: tr.dataset.pdx });
    };
    tbody.addEventListener('click', go);
    tbody.addEventListener('keydown', go);
})();

document
    .getElementById('budget-year')
    .addEventListener('change', loadTop10Table);

/*
============================================================
การส่งต่อ 3 กล่อง (api/er_refer.php): ส่งต่อออกจาก ER · Admit แล้วส่งต่อจากหอผู้ป่วย · รับส่งต่อเข้า ER
แต่ละแถว (รพ. / เหตุผล / ไม่มีใบ / อื่น ๆ) และหัวกล่อง คลิกดูรายชื่อได้ — รายชื่อใช้ใบเดียวกับที่นับ
(refer_dest / ipd_dest / refer_src ของ api/er_visit_list.php) จึงตรงกับตัวเลข
============================================================
*/

const REFER_BOX = [
    { key: 'out', cls: 'is-out', icon: 'arrow-right-from-bracket', title: 'ส่งต่อออกจาก ER', sub: 'Refer out ส่งไปที่ไหน',
      type: 'refer', field: 'refer_dest', reason: 'refer_reason', noDoc: 'ไม่มีใบส่งต่อ (ไม่ทราบปลายทาง)' },
    { key: 'ipd', cls: 'is-ipd', icon: 'bed-pulse', title: 'Admit แล้วส่งต่อ', sub: 'ส่งต่อจากหอผู้ป่วย',
      type: 'ipd_refer', field: 'ipd_dest', reason: 'ipd_reason', noDoc: 'จำหน่าย By Transfer แต่ไม่มีใบส่งต่อ' },
    { key: 'in', cls: 'is-in', icon: 'arrow-right-to-bracket', title: 'รับส่งต่อเข้า ER', sub: 'Refer in รับมาจากที่ไหน',
      type: 'refer_in', field: 'refer_src', noDoc: 'ไม่มีใบรับส่งต่อ (ไม่ทราบต้นทาง)' }
];
let referData = null;

async function loadRefer() {
    const host = document.getElementById('er-refer-panel');
    const esc = s => String(s).replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
    const fmt = n => Number(n).toLocaleString();
    const pct = (n, t) => t ? Math.round(n * 100 / t) : 0;

    let data;
    try {
        data = await (await fetch('../api/er_refer.php?' + bbhPeriod.query())).json();
    } catch (e) {
        data = { error: 'โหลดข้อมูลไม่สำเร็จ' };
    }
    if (data.error) {
        host.innerHTML = `<p class="er-note">${esc(data.error)}</p>`;
        return;
    }
    referData = data;

    // แถว: [ชื่อ | แถบ | จำนวน %] · data-pick = เงื่อนไขกรองรายชื่อ (ดู openReferList)
    const line = (box, label, n, total, pick, l12) => `
        <li role="button" tabindex="0" data-box="${box.key}" data-pick="${esc(pick)}" title="คลิกเพื่อดูรายชื่อ">
            <span class="er-refer-nm">${esc(label)}${l12 ? `<small>ระดับ 1–2: ${fmt(l12)}</small>` : ''}</span>
            <span class="er-meter" aria-hidden="true"><span style="width:${pct(n, total)}%"></span></span>
            <span class="er-refer-n">${fmt(n)}<small>${pct(n, total)}%</small></span>
        </li>`;

    host.innerHTML = REFER_BOX.map(box => {
        const g = data[box.key], max = 6;
        const top = g.items.slice(0, max), rest = g.items.slice(max);
        let list = top.map(x => line(box, x.name, x.n, g.total, 'name:' + x.name, x.l12)).join('');
        if (rest.length) list += line(box, `อื่น ๆ (${rest.length} แห่ง)`, rest.reduce((s, x) => s + x.n, 0), g.total, 'rest:' + top.map(x => x.name).join('|'));
        if (g.no_doc) list += line(box, box.noDoc, g.no_doc, g.total, 'none');
        if (!g.total) list = '<li class="is-empty"><span class="er-refer-nm">ไม่มีในช่วงนี้</span></li>';

        const share = data.er_total ? (g.total * 100 / data.er_total).toFixed(1) : '0.0';
        const stats = [`${share}% ของผู้ป่วย ER, ระดับ 1–2: ${fmt(g.l12)} ครั้ง`];
        if (box.key === 'ipd' && g.total) stats.push(`จำหน่าย By Transfer ${fmt(g.transfer)}, จำหน่ายแบบอื่นแต่มีใบส่งต่อ ${fmt(g.total - g.transfer)}`);
        const warn = box.key === 'in' && g.self ? `<p class="er-refer-warn"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i> ลงต้นทางเป็น รพ.บ้านบึงเอง ${fmt(g.self)} ครั้ง ควรตรวจการบันทึก</p>` : '';
        const reasons = box.reason && g.reasons.length ? `
            <h4 class="er-refer-sub">เหตุผลการส่งต่อ</h4>
            <ol class="er-refer-list is-reason">${g.reasons.slice(0, 3).map(x => line(box, x.name, x.n, g.total - g.no_doc, 'reason:' + x.name)).join('')}</ol>` : '';

        return `
            <section class="er-chart er-refer-box ${box.cls}" aria-label="${esc(box.title)}">
                <header class="er-refer-head" role="button" tabindex="0" data-box="${box.key}" data-pick="all" title="คลิกเพื่อดูรายชื่อทั้งหมด">
                    <span class="er-ref" aria-hidden="true"><i class="fa-solid fa-${box.icon}"></i></span>
                    <span class="er-refer-name">${esc(box.title)}<small>${esc(box.sub)}</small></span>
                    <span class="er-refer-total"><b>${fmt(g.total)}</b> ครั้ง</span>
                </header>
                <p class="er-refer-stats">${stats.map(esc).join('<br>')}</p>
                <ol class="er-refer-list">${list}</ol>
                ${warn}${reasons}
            </section>`;
    }).join('');
}

// คลิกแถว/หัวกล่อง → รายชื่อของช่วงเวลาเดียวกัน กรองตาม รพ. / เหตุผล / ไม่มีใบ / อื่น ๆ
function openReferList(node) {
    const box = REFER_BOX.find(b => b.key === node.dataset.box);
    const pick = node.dataset.pick, sep = pick.indexOf(':');
    const kind = sep < 0 ? pick : pick.slice(0, sep), val = sep < 0 ? '' : pick.slice(sep + 1);
    const keep = {
        all:    () => true,
        none:   r => !r[box.field],
        name:   r => r[box.field] === val,
        rest:   r => !!r[box.field] && !val.split('|').includes(r[box.field]),
        reason: r => r[box.reason] === val
    }[kind];
    const what = { all: '', none: ' — ' + box.noDoc, name: ' — ' + val, rest: ' — แห่งอื่น ๆ', reason: ' — เหตุผล: ' + val }[kind];
    erPeriodList({ title: box.title + what, type: box.type, keep });
}

(function () {
    const host = document.getElementById('er-refer-panel');
    const go = e => {
        const n = e.target.closest('[data-pick]');
        if (!n || !host.contains(n)) return;
        if (e.type === 'keydown') { if (e.key !== 'Enter' && e.key !== ' ') return; e.preventDefault(); }
        openReferList(n);
    };
    host.addEventListener('click', go);
    host.addEventListener('keydown', go);
})();

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