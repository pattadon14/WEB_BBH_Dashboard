/* =========================================================
   หน้างานฝากครรภ์ (pages/ANC_detail.php)
   ช่วงเวลา: assets/js/period_filter.js (bbhPeriod) · ตัวหาร/สัญชาติ: ปุ่มบนแถบหัว · ตัวเลข: api/anc_summary.php
========================================================= */

const C = { visit: '#db2777', new: '#701a75', refer: '#ea580c' };   // สีชุดเดียวกับต้นแบบ ANC (--c-visit / --c-new / --c-refer)
const fmtN = n => (n === null || n === undefined ? '—' : Number(n).toLocaleString('th-TH'));
const fmtP = n => (n === null || n === undefined ? '—' : Number(n).toLocaleString('th-TH', { minimumFractionDigits: 1, maximumFractionDigits: 1 }) + '%');
const setText = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = v; };
const opts = { scope: 'area', nat: '' };
const esc = s => String(s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

async function getJson(url) {
    const res = await fetch(url);
    const data = await res.json();
    if (!res.ok || data.error) throw new Error(data.error || 'HTTP ' + res.status);
    return data;
}

// แถบในช่อง KPI: ความยาว = ค่า % · ขีด = เป้า (ข้อความ aria บอกทั้งสองค่า ไม่พึ่งสี)
function kbar(id, value, target, label) {
    const el = document.getElementById(id);
    el.firstElementChild.style.width = Math.min(100, Number(value) || 0) + '%';
    el.lastElementChild.style.left = target + '%';
    el.setAttribute('aria-label', `${label} ${fmtP(value)} เป้า ${target}%`);
}

function rate(key, r, label) {
    setText('kpi-' + key, fmtP(r.value));
    setText('kpi-' + key + '-nd', `${fmtN(r.num)}/${fmtN(r.den)}`);
    kbar('bar-' + key, r.value, r.target, label);
}

async function load() {
    const qs = bbhPeriod.query() + '&scope=' + opts.scope + (opts.nat ? '&nat=' + opts.nat : '');
    let d;
    loadIndicators();   // ตารางตัวชี้วัด โหลดคู่ขนาน (API แยก)
    try {
        d = await getJson('../api/anc_summary.php?' + qs);
    } catch (err) {
        console.error('ANC summary error:', err);
        ['kpi-new', 'kpi-visits', 'kpi-per-month', 'kpi-early12', 'kpi-visit5', 'kpi-refer', 'kpi-labor'].forEach(id => setText(id, '—'));
        document.getElementById('anc-trend-chart').innerHTML = `<p class="er-note">โหลดข้อมูลไม่สำเร็จ: ${bbhEsc(err.message)}</p>`;
        return;
    }

    setText('kpi-new', fmtN(d.new));
    setText('kpi-open', fmtN(d.open));
    setText('kpi-visits', fmtN(d.visits));
    setText('kpi-per-month', Number(d.per_month || 0).toLocaleString('th-TH', { minimumFractionDigits: 1, maximumFractionDigits: 1 }));
    rate('early12', d.early12, 'ฝากครรภ์ก่อน 12 สัปดาห์');
    rate('visit5', d.visit5, 'ANC ครบ 5 ครั้ง');
    setText('kpi-refer', fmtN(d.refer));

    // บันทึกวันคลอด: ต่ำกว่าเกณฑ์ = ตัวหาร "ครรภ์ที่คลอดในช่วง" ขาด → แจ้งเตือนเหนือการ์ด
    const lb = d.labor;
    setText('kpi-labor', fmtP(lb.pct));
    setText('kpi-labor-nd', `${fmtN(lb.recorded)}/${fmtN(lb.due)}`);
    kbar('bar-labor', lb.pct, lb.warn_pct, 'บันทึกวันคลอด');
    const low = lb.pct !== null && lb.pct < lb.warn_pct;
    document.getElementById('kpi-labor-tile').classList.toggle('is-low', low);
    const alert = document.getElementById('anc-labor-alert');
    alert.hidden = !low;
    if (low) {
        alert.innerHTML = `<i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i> <b>บันทึกวันคลอดในทะเบียนฝากครรภ์เพียง ${fmtP(lb.pct)}</b>
            (${fmtN(lb.recorded)}/${fmtN(lb.due)} ครรภ์ที่ครบกำหนดแล้ว · ยังไม่ลง ${fmtN(lb.due - lb.recorded)} ราย)
            — ตัวชี้วัดที่หารด้วย "ครรภ์ที่คลอดในช่วง" จะขาดรายที่ไม่ได้ลงวันคลอด`;
    }

    drawTrend(d.trend);
}

function drawTrend(rows) {
    const many = rows.length > 14;
    const label = { enabled: !many, style: { fontSize: '15px', textOutline: 'none', color: '#2b1a22' } };
    Highcharts.chart('anc-trend-chart', {
        chart: { style: { fontFamily: 'THSarabun' } },
        title: { text: null },
        xAxis: { categories: rows.map(r => r.label) },
        yAxis: { min: 0, allowDecimals: false, title: { text: 'จำนวน' } },
        legend: { itemStyle: { fontSize: '17px' } },
        credits: { enabled: false },
        tooltip: { shared: true },
        plotOptions: { column: { maxPointWidth: 40, borderRadius: 4, borderWidth: 0, groupPadding: 0.12 } },
        series: [
            { type: 'column', name: 'ครั้งบริการ ANC', color: C.visit, data: rows.map(r => r.visits), dataLabels: label, tooltip: { valueSuffix: ' ครั้ง' } },
            { type: 'column', name: 'ฝากครรภ์รายใหม่', color: C.new, data: rows.map(r => r.new), dataLabels: label, tooltip: { valueSuffix: ' ราย' } },
            { type: 'line', name: 'ส่งต่อ (Refer)', color: C.refer, lineWidth: 2, marker: { radius: 4 }, data: rows.map(r => r.refer),
              dataLabels: { ...label, style: { ...label.style, color: '#9a3412' } }, tooltip: { valueSuffix: ' ครั้ง' } }
        ]
    });
}

/* ---------- 3 ตัวชี้วัดคุณภาพ ---------- */
const IND_ROWS = [
    ['early12', 'ฝากครรภ์ครั้งแรก ≤ 12 wk', 'person_anc_service.pa_week · person_anc_other_precare'],
    ['visit5', 'ANC ครบ 5 ครั้งตามเกณฑ์ (≤12 · 13–<20 · 20–<26 · 26–<32 · 32–40 wk)', 'person_anc_service.pa_week · person_anc.labor_date'],
    ['visit8', 'ANC ครบ 8 ครั้งตามเกณฑ์ (≤12 · 13–20 · 21–26 · 27–30 · 31–34 · 35–36 · 37–38 · 39–40 wk) · เป้าตามปีงบ', 'person_anc_service.pa_week · person_anc.labor_date'],
    ['postnatal', 'ดูแลหลังคลอดครบ 3 ครั้ง (วันที่ 1–7 · 8–15 · 16–42)', 'person_anc_preg_care · person_anc.post_labor_service'],
    ['quality', 'ฝากครรภ์คุณภาพ (HDC) — ครบ 5 กิจกรรม', 'lab_order · thalassaemia_result · doctor_operation · ovstdiag'],
    ['iodine', 'ยาเม็ดไอโอดีน (Workload) — ในหญิงที่มาฝากครรภ์', 'opitemrece · drugitems.did'],
    ['dt', 'วัคซีน dT', 'ovst_vaccine · person_anc'],
    ['dental', 'ตรวจช่องปาก', 'dtmain · person_anc'],
    ['anemia', 'โลหิตจางใกล้คลอด (Hct < 33% · GA ≥ 28)', 'person_anc_lab · person_anc'],
    ['lbw', 'ทารก LBW (< 2500 g)', 'ipt_labour_infant'],
    ['teen_repeat', 'ตั้งครรภ์ซ้ำในหญิงอายุ < 20 ปี (ครรภ์ที่ ≥ 2 · คลอด/แท้งในช่วง) · เป้าตามปีงบ', 'person_anc.preg_no · person_anc.labor_date']
];
const LOWER = ['anemia', 'lbw', 'teen_repeat'];

function indRow(key, label, src, r) {
    const nd = r.den === null ? '—' : `${fmtN(r.num)}/${fmtN(r.den)}`;
    const target = r.target === null ? '—' : `${LOWER.includes(key) ? '≤' : '≥'} ${r.target}%`;
    const status = r.ok === true ? '<span class="anc-st is-ok">ผ่าน</span>'
        : r.ok === false ? '<span class="anc-st is-no">ไม่ผ่าน</span>'
        : r.target !== null ? '<span class="anc-st">ไม่มีข้อมูล</span>' : '';
    const faint = r.low_base ? ' class="is-faint" title="ตัวหารน้อยกว่า 5 ใช้เทียบเป้าไม่ได้"' : '';
    return `<tr${faint}><td>${esc(label)}<span class="anc-src">HOSxP: ${esc(src)}</span></td>
        <td class="text-right"><b>${fmtP(r.value)}</b></td><td class="text-right">${nd}</td>
        <td class="text-right">${target}</td><td>${status}</td></tr>`;
}

async function loadIndicators() {
    const body = document.getElementById('anc-ind-body');
    const qs = bbhPeriod.query() + '&scope=' + opts.scope + (opts.nat ? '&nat=' + opts.nat : '');
    document.getElementById('anc-ind-sub').textContent = 'ANC 11 ตัว · ตัวหาร' + (opts.scope === 'area' ? 'ในเขต' : 'ทุกครรภ์');
    try {
        const d = await getJson('../api/anc_indicators.php?' + qs);
        body.innerHTML = IND_ROWS.map(([key, label, src]) => {
            let html = indRow(key, label, src, d[key]);
            if (key === 'quality') {
                html += d.quality_parts.map(p => `<tr class="anc-sub"><td>↳ ${esc(p.label)}</td>
                    <td class="text-right">${fmtP(p.rate.value)}</td><td class="text-right">${fmtN(p.rate.num)}/${fmtN(p.rate.den)}</td><td></td><td></td></tr>`).join('');
            }
            return html;
        }).join('');
    } catch (err) {
        console.error('ANC indicators error:', err);
        body.innerHTML = `<tr><td colspan="5" class="er-note">โหลดข้อมูลไม่สำเร็จ: ${esc(err.message)}</td></tr>`;
    }
}

function bbhEsc(s) {
    return String(s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

// ปุ่มตัวหาร / สัญชาติ
document.querySelectorAll('.anc-filters [data-scope], .anc-filters [data-nat]').forEach(b => b.addEventListener('click', () => {
    const key = 'scope' in b.dataset ? 'scope' : 'nat';
    opts[key] = b.dataset[key];
    document.querySelectorAll(`.anc-filters [data-${key}]`).forEach(x => x.setAttribute('aria-pressed', String(x === b)));
    load();
}));

load();
document.getElementById('budget-year').addEventListener('change', load);
