/* =========================================================
   หน้างานฝากครรภ์ (pages/ANC_detail.php)
   ช่วงเวลา: assets/js/period_filter.js (bbhPeriod) · ตัวหาร/สัญชาติ: ปุ่มบนแถบหัว
   ตัวเลข: api/anc_summary.php (1–2), anc_indicators.php (3–6, 12), anc_screening.php (7–10), anc_refer.php (11)
   คลิกตัวเลข/แถว → รายชื่อ (api/anc_visit_list.php ต้อง login) ผ่าน bbhDrill
========================================================= */

const C = { visit: '#db2777', new: '#701a75', refer: '#ea580c' };   // สีชุดเดียวกับต้นแบบ ANC (--c-visit / --c-new / --c-refer)
const AGE_C = { teen: '#be185d', repro: '#7c3aed', elder: '#0e7490', unknown: '#94a3b8' };
const AGE_TH = { teen: 'อายุ < 20 ปี', repro: 'อายุ 20–34 ปี', elder: 'อายุ ≥ 35 ปี', unknown: 'ไม่ทราบอายุ' };
const esc = bbhDrill.esc;
const fmtN = n => (n === null || n === undefined ? '—' : Number(n).toLocaleString('th-TH'));
const fmtP = n => (n === null || n === undefined ? '—' : Number(n).toLocaleString('th-TH', { minimumFractionDigits: 1, maximumFractionDigits: 1 }) + '%');
const fmt1 = n => (n === null || n === undefined ? '—' : Number(n).toLocaleString('th-TH', { minimumFractionDigits: 1, maximumFractionDigits: 1 }));
const setText = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = v; };
const setHtml = (id, v) => { const el = document.getElementById(id); if (el) el.innerHTML = v; };
const thaiDate = iso => iso ? new Date(iso + 'T00:00:00').toLocaleDateString('th-TH', { day: 'numeric', month: 'short', year: 'numeric' }) : '';
const shortDate = iso => { const [y, m, d] = String(iso || '').split('-').map(Number); return y ? `${d}/${m}/${String(y + 543).slice(2)}` : ''; };
const TH_MON = ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
const monthLabel = ym => { const [y, m] = ym.split('-').map(Number); return `${TH_MON[m - 1]} ${String(y + 543).slice(2)}`; };
const opts = { scope: 'area', nat: '' };
const errRow = (cols, err) => `<tr><td colspan="${cols}" class="er-note">โหลดข้อมูลไม่สำเร็จ: ${esc(err.message)}</td></tr>`;

// loadSeq เพิ่มทุกครั้งที่ loadAll() — คำตอบของรอบเก่าที่มาช้าไม่ถูก render ทับตัวกรองใหม่ (promise ค้างไว้ ไม่ resolve)
let loadSeq = 0;
async function getJson(url) {
    const seq = loadSeq;
    const res = await fetch(url);
    const data = await res.json();
    if (seq !== loadSeq) return new Promise(() => {});
    if (!res.ok || data.error) throw new Error(data.error || 'HTTP ' + res.status);
    return data;
}
const qs = (extra = '') => bbhPeriod.query() + '&scope=' + opts.scope + (opts.nat ? '&nat=' + opts.nat : '') + extra;

// แถบในช่อง KPI: ความยาว = ค่า % · ขีด = เป้า (aria บอกทั้งสองค่า ไม่พึ่งสี)
function kbar(id, value, target, label) {
    const el = document.getElementById(id);
    el.firstElementChild.style.width = Math.min(100, Number(value) || 0) + '%';
    el.lastElementChild.style.left = target + '%';
    el.setAttribute('aria-label', `${label} ${fmtP(value)} เป้า ${target}%`);
}

// รายการแบบแถบ (ชื่อ · แถบ · จำนวน) ใช้กับภาวะแทรกซ้อน ปลายทาง Dx
function barList(items, empty) {
    if (!items.length) return `<li class="is-empty er-note">${empty}</li>`;
    const max = Math.max(...items.map(x => x.n), 1);
    return items.map(x => `<li${x.drill ? ` data-drill="${x.drill}" data-label="${esc(x.label)}"` : ''}>
        <span class="er-refer-nm">${esc(x.name)}${x.sub ? `<small>${esc(x.sub)}</small>` : ''}</span>
        <span class="er-meter" aria-hidden="true"><span style="width:${(x.n * 100 / max).toFixed(1)}%"></span></span>
        <span class="er-refer-n">${fmtN(x.n)}${x.pct !== undefined ? `<small>${fmtP(x.pct)}</small>` : ''}</span></li>`).join('');
}

function tile(label, value, sub, opt = {}) {
    return `<div class="anc-tile${opt.bad ? ' is-bad' : ''}"${opt.drill ? ` data-drill="${opt.drill}" data-label="${esc(label)}"` : ''}>
        <span class="anc-tile-label">${opt.icon ? `<i class="fa-solid ${opt.icon}" aria-hidden="true"></i>` : ''}${esc(label)}</span>
        <b class="anc-tile-num">${value}</b><small>${sub}</small></div>`;
}

/* ---------- 1–2 ภาพรวม + แนวโน้มรายเดือน ---------- */
async function loadSummary() {
    let d;
    try {
        d = await getJson('../api/anc_summary.php?' + qs());
    } catch (err) {
        console.error('ANC summary error:', err);
        ['kpi-new', 'kpi-visits', 'kpi-per-month', 'kpi-early12', 'kpi-visit5', 'kpi-refer', 'kpi-labor'].forEach(id => setText(id, '—'));
        setHtml('anc-trend-chart', `<p class="er-note">โหลดข้อมูลไม่สำเร็จ: ${esc(err.message)}</p>`);
        return;
    }
    setText('kpi-new', fmtN(d.new));
    setText('kpi-open', fmtN(d.open));
    setText('kpi-visits', fmtN(d.visits));
    setText('kpi-per-month', fmt1(d.per_month));
    for (const [key, label] of [['early12', 'ฝากครรภ์ก่อน 12 สัปดาห์'], ['visit5', 'ANC ครบ 5 ครั้ง']]) {
        setText('kpi-' + key, fmtP(d[key].value));
        setText('kpi-' + key + '-nd', `${fmtN(d[key].num)}/${fmtN(d[key].den)}`);
        kbar('bar-' + key, d[key].value, d[key].target, label);
    }
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
        chart: { style: { fontFamily: 'BBH Sans Chart' } },
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

/* ---------- 3–6 ตัวชี้วัด · กลุ่มอายุ · รายการที่ต้องติดตาม · ผลลัพธ์ ---------- */
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
const IND_SHORT = { early12: 'ฝากครรภ์ก่อน 12 wk', visit5: 'ANC ครบ 5 ครั้ง', visit8: 'ANC ครบ 8 ครั้ง', postnatal: 'ดูแลหลังคลอดครบ 3 ครั้ง',
    quality: 'ฝากครรภ์คุณภาพ', iodine: 'ยาเม็ดไอโอดีน', dt: 'วัคซีน dT', dental: 'ตรวจช่องปาก', anemia: 'โลหิตจางใกล้คลอด',
    lbw: 'ทารก LBW', teen_repeat: 'ตั้งครรภ์ซ้ำในวัยรุ่น' };
const LOWER = ['anemia', 'lbw', 'teen_repeat'];
const NO_LIST = ['lbw'];   // ทารกไม่มีรายชื่อแม่ในหน้านี้

function status(r) {
    return r.ok === true ? '<span class="anc-st is-ok"><i class="fa-solid fa-check" aria-hidden="true"></i> ผ่าน</span>'
        : r.ok === false ? '<span class="anc-st is-no"><i class="fa-solid fa-xmark" aria-hidden="true"></i> ไม่ผ่าน</span>'
        : r.target !== null ? '<span class="anc-st">ไม่มีข้อมูล</span>' : '';
}

function indRow(key, label, src, r) {
    const nd = r.den === null ? '—' : `${fmtN(r.num)}/${fmtN(r.den)}`;
    const target = r.target === null ? '—' : `${LOWER.includes(key) ? '≤' : '≥'} ${r.target}%`;
    const attrs = (NO_LIST.includes(key) ? '' : ` data-drill="${key}" data-label="${esc(IND_SHORT[key])}"`)
        + (r.low_base ? ' class="is-faint" title="ตัวหารน้อยกว่า 5 ใช้เทียบเป้าไม่ได้"' : '');
    return `<tr${attrs}><td>${esc(label)}<span class="anc-src">HOSxP: ${esc(src)}</span></td>
        <td class="text-right"><b>${fmtP(r.value)}</b></td><td class="text-right">${nd}</td>
        <td class="text-right">${target}</td><td>${status(r)}</td></tr>`;
}

async function loadIndicators() {
    const body = document.getElementById('anc-ind-body');
    setText('anc-ind-sub', 'ANC 11 ตัว · ตัวหาร' + (opts.scope === 'area' ? 'ในเขต' : 'ทุกครรภ์'));
    let d;
    try {
        d = await getJson('../api/anc_indicators.php?' + qs());
    } catch (err) {
        console.error('ANC indicators error:', err);
        body.innerHTML = errRow(5, err);
        setHtml('anc-age-body', errRow(5, err));
        setHtml('anc-alerts', `<li class="er-note">โหลดข้อมูลไม่สำเร็จ: ${esc(err.message)}</li>`);
        setHtml('anc-outcome-tiles', `<p class="er-note">โหลดข้อมูลไม่สำเร็จ: ${esc(err.message)}</p>`);
        return;
    }

    // 3 ตารางตัวชี้วัด + สรุปผ่านเป้า (ไอโอดีนไม่มีเป้า ไม่นับ)
    body.innerHTML = IND_ROWS.map(([key, label, src]) => indRow(key, label, src, d[key])
        + (key === 'quality' ? d.quality_parts.map(p => `<tr class="anc-sub"><td>↳ ${esc(p.label)}</td>
            <td class="text-right">${fmtP(p.rate.value)}</td><td class="text-right">${fmtN(p.rate.num)}/${fmtN(p.rate.den)}</td><td></td><td></td></tr>`).join('') : '')).join('');
    const withTarget = IND_ROWS.map(r => d[r[0]]).filter(r => r.target !== null);
    const pass = withTarget.filter(r => r.ok === true).length;
    const fail = withTarget.filter(r => r.ok === false).length;
    setHtml('anc-pass', `<span class="anc-st is-ok">ผ่าน ${pass}</span><span class="anc-st is-no">ไม่ผ่าน ${fail}</span>`
        + (withTarget.length - pass - fail ? `<span class="anc-st">ไม่มีข้อมูล ${withTarget.length - pass - fail}</span>` : '')
        + `<span class="anc-pass-of">จาก ${withTarget.length} ตัวที่มีเป้า</span>`);

    // 4 กลุ่มอายุ
    const a = d.age;
    const totalNew = ['teen', 'repro', 'elder'].reduce((s, g) => s + a[g].new, 0);
    setHtml('anc-age-bar', ['teen', 'repro', 'elder'].map(g => a[g].new
        ? `<span style="flex:${a[g].new};background:${AGE_C[g]}" title="${AGE_TH[g]} ${fmtN(a[g].new)} ราย"></span>` : '').join(''));
    document.getElementById('anc-age-bar').setAttribute('aria-label', ['teen', 'repro', 'elder'].map(g => `${AGE_TH[g]} ${fmtP(a[g].share)}`).join(' · '));
    setHtml('anc-age-body', ['teen', 'repro', 'elder'].map(g => `<tr>
        <td><span class="er-dot" style="background:${AGE_C[g]}" aria-hidden="true"></span>${AGE_TH[g]}</td>
        <td class="text-right"><b>${fmtN(a[g].new)}</b> <small>${fmtP(a[g].share)}</small></td>
        <td class="text-right">${fmtP(a[g].early12.value)} <small>${fmtN(a[g].early12.num)}/${fmtN(a[g].early12.den)}</small></td>
        <td class="text-right">${fmtP(a[g].visit5.value)} <small>${fmtN(a[g].visit5.num)}/${fmtN(a[g].visit5.den)}</small></td>
        <td class="text-right">${fmtP(a[g].anemia.value)} <small>${fmtN(a[g].anemia.num)}/${fmtN(a[g].anemia.den)}</small></td></tr>`).join(''));
    setHtml('anc-age-note', `วัยรุ่น: อายุ 15–19 ปี <b>${fmtN(a.teen['15_19'])}</b> ราย · อายุต่ำกว่า 15 ปี <b class="${a.teen.lt15 ? 'anc-bad' : ''}">${fmtN(a.teen.lt15)}</b> ราย
        · รวม 3 กลุ่ม ${fmtN(totalNew)} ราย (ไม่นับรายที่ไม่มีวันเกิด)`);

    // 5 รายการที่ต้องติดตาม
    const levels = { red: ['is-red', 'ด่วน'], amber: ['is-amber', 'เฝ้าระวัง'], info: ['is-info', 'ข้อมูล'] };
    setText('anc-alert-count', d.alerts.length ? `${d.alerts.length} รายการ` : '');
    setHtml('anc-alerts', d.alerts.length ? d.alerts.map(x => `<li><span class="anc-lv ${levels[x.level][0]}">${levels[x.level][1]}</span>
        <div><b>${esc(x.text)}</b><small>${esc(x.owner)} · ${esc(x.action)}</small></div></li>`).join('')
        : '<li class="er-note">ไม่มีรายการที่ต้องติดตาม</li>');

    // 6 ผลลัพธ์แม่และทารก
    const o = d.outcome;
    setHtml('anc-outcome-tiles', [
        tile('คลอดก่อนกำหนด < 37 wk', fmtP(o.preterm.value), `${fmtN(o.preterm.num)}/${fmtN(o.preterm.den)} ครรภ์ที่มี LMP`, { icon: 'fa-hourglass-start', drill: 'preterm' }),
        tile('ทารกแรกเกิดขาดออกซิเจน', fmt1(o.asphyxia.value), `ต่อ 1,000 เกิดมีชีพ · ${fmtN(o.asphyxia.num)}/${fmtN(o.asphyxia.den)} · เป้า ≤ ${o.asphyxia.target}`, { icon: 'fa-lungs', bad: o.asphyxia.ok === false }),
        tile('คลอดที่ รพ.บ้านบึง', fmtP(o.place.pct_here), `${fmtN(o.place.here)}/${fmtN(o.place.den)} ครรภ์ที่คลอด`, { icon: 'fa-hospital' }),
        tile('BBA (ประเภทคลอด 3)', fmtN(o.bba), 'ราย · ทารกเกิดใน รพ. — ตรวจรหัสก่อนใช้', { icon: 'fa-car-side' }),
        tile('มารดาเสียชีวิต', fmtN(o.maternal_death), 'ราย · ตั้งครรภ์ถึงหลังคลอด 42 วัน', { icon: 'fa-heart-crack', bad: o.maternal_death > 0 }),
        tile('ทารกตายคลอด', fmtN(o.stillbirth), 'ราย · คลอดใน รพ.', { icon: 'fa-baby', bad: o.stillbirth > 0 })
    ].join(''));
    setHtml('anc-cx-list', barList(o.complications.map(c => ({ name: c.label, n: c.num, pct: c.value })), 'ไม่มีข้อมูล'));
    setHtml('anc-place-list', barList(o.place.top.map(p => ({ name: p.name, sub: p.code !== p.name ? p.code : '', n: p.n })), 'คลอดที่ รพ.บ้านบึงทั้งหมด'));
}

/* ---------- 7–10 คัดกรอง ---------- */
async function loadScreen(part, render, onError) {
    try {
        render((await getJson('../api/anc_screening.php?' + qs('&part=' + part))).data);
    } catch (err) {
        console.error('ANC ' + part + ' error:', err);
        onError(err);
    }
}

const SRC_TITLE = { F: 'ฟอร์มคัดกรอง HOSxP', C: 'คำนวณ', D: 'Dx ICD-10', T: 'ข้อความ HPI/PMH' };
function renderRisk27(r) {
    const pct = (n, p) => `${fmtN(n)} <small>${fmtP(p)}</small>`;
    setHtml('anc-r27-tiles', tile('ครรภ์เสี่ยง ≥ 1 ข้อ', fmtP(r.any.pct), `${fmtN(r.any.num)}/${fmtN(r.any.den)} รายใหม่`, { icon: 'fa-triangle-exclamation', bad: true })
        + r.groups.map(g => tile(g.label, fmtN(g.num), `ราย · ${fmtP(g.pct)} · ไทย ${fmtN(g.num_th)} · ต่างชาติ ${fmtN(g.num_fr)}`)).join(''));
    setHtml('anc-r27-note', `รายใหม่ ${fmtN(r.den)} ราย = ไทย <b>${fmtN(r.den_th)}</b> · ต่างชาติ <b>${fmtN(r.den_fr)}</b> (รวมไม่ระบุสัญชาติ) ·
        ครรภ์เสี่ยงอย่างน้อย 1 ข้อ: ไทย ${fmtN(r.any.num_th)} (${fmtP(r.any.pct_th)}) · ต่างชาติ ${fmtN(r.any.num_fr)} (${fmtP(r.any.pct_fr)})`);
    const table = (title, rows, withNo) => `<div class="anc-r27-box"><h3 class="er-h3">${esc(title)}</h3>
        <table class="table er-table anc-mini-table"><thead><tr><th>${withNo ? 'ข้อ' : ''}</th><th class="text-right">รวม</th><th class="text-right">ไทย</th><th class="text-right">ต่างชาติ</th><th>ที่มา</th></tr></thead>
        <tbody>${rows.map(it => `<tr${it.n ? '' : ' class="is-faint"'}><td>${withNo ? `<span class="anc-no">${it.num}</span>` : ''}${esc(it.label)}
            ${it.known !== undefined && it.known < r.den ? `<small class="anc-src">มีข้อมูลพอคำนวณ ${fmtN(it.known)}/${fmtN(r.den)} ราย</small>` : ''}</td>
            <td class="text-right">${pct(it.n, it.pct)}</td><td class="text-right">${fmtN(it.n_th)}</td><td class="text-right">${fmtN(it.n_fr)}</td>
            <td>${(it.sources || ['F']).map(s => `<span class="anc-srcb${s === 'T' ? ' is-t' : ''}" title="${SRC_TITLE[s]}">${s}</span>`).join('')}</td></tr>`).join('')}</tbody></table></div>`;
    const groups = { age_body: 'ปัจจัยด้านอายุและร่างกาย', history: 'ประวัติการตั้งครรภ์และการคลอดในอดีต', medical: 'โรคประจำตัวและภาวะอายุรกรรม', current: 'ภาวะแทรกซ้อนของการตั้งครรภ์ปัจจุบัน' };
    setHtml('anc-r27-groups', Object.entries(groups).map(([k, t]) => table(t, r.items.filter(it => it.group === k), true)).join('')
        + table('เพิ่มเติมจากฟอร์ม HOSxP (ไม่อยู่ใน 27 ข้อ แต่นับเป็นครรภ์เสี่ยง)', r.extras, false));
}

function renderAnemia(h) {
    setHtml('anc-hct-tiles', tile('ได้เจาะ Hct', `${fmtN(h.tested.num)}/${fmtN(h.tested.den)}`, `${fmtP(h.tested.value)} · ยังไม่ได้เจาะ ${fmtN(h.untested)} ราย`, { icon: 'fa-vial' })
        + tile('ซีดระหว่างตั้งครรภ์', fmtN(h.anemic), `ราย · ไม่ซีด ${fmtN(h.normal)} ราย`, { icon: 'fa-droplet', bad: h.anemic > 0 })
        + h.groups.map(g => tile(g.label, fmtN(g.n), `ราย${h.anemic ? ' · ' + fmtP(g.n * 100 / h.anemic) + ' ของที่ซีด' : ''}`,
            { bad: ['not_improved', 'new'].includes(g.key) && g.n > 0 })).join(''));
}

function renderOgtt(o) {
    setHtml('anc-ogtt-body', o.rows.map(t => `<tr${t.key === 'all' ? ' class="anc-total-row"' : ''}><td>${esc(t.label)}</td>
        <td class="text-right">${fmtN(t.tested)}</td><td class="text-right">${t.abn ? `<b class="anc-bad">${fmtN(t.abn)}</b>` : '0'}</td>
        <td class="text-right">${fmtP(t.rate.value)}</td><td class="text-right">${fmtN(t.one)}</td></tr>`).join(''));
}

function renderSti(rows) {
    setHtml('anc-sti-body', rows.map(t => `<tr><td>${esc(t.label)}</td>
        <td class="text-right">${fmtN(t.tested.num)}/${fmtN(t.tested.den)}</td><td class="text-right">${fmtP(t.tested.value)}</td>
        ${t.key === 'nipt' ? '<td class="text-right">–</td><td class="text-right">–</td><td class="text-right">–</td>'
            : `<td class="text-right">${t.pos.num ? `<b class="anc-bad">${fmtN(t.pos.num)}</b>` : '0'}/${fmtN(t.pos.den)}</td>
               <td class="text-right">${fmtP(t.pos.value)}</td><td class="text-right">${fmtN(t.unclear)}</td>`}
        <td class="text-right">${fmtN(t.untested)}</td></tr>`).join(''));
}

/* ---------- 11 Refer ---------- */
async function loadRefer() {
    let r;
    try {
        r = await getJson('../api/anc_refer.php?' + bbhPeriod.query() + (opts.nat ? '&nat=' + opts.nat : ''));
    } catch (err) {
        console.error('ANC refer error:', err);
        setHtml('anc-refer-chart', `<p class="er-note">โหลดข้อมูลไม่สำเร็จ: ${esc(err.message)}</p>`);
        return;
    }
    setText('anc-refer-total', fmtN(r.total));
    const groups = ['teen', 'repro', 'elder', 'unknown'];
    Highcharts.chart('anc-refer-chart', {
        chart: { type: 'column', style: { fontFamily: 'BBH Sans Chart' } },
        title: { text: null },
        xAxis: { categories: r.monthly.map(m => monthLabel(m.month)) },
        yAxis: { min: 0, allowDecimals: false, title: { text: 'ครั้ง' }, stackLabels: { enabled: r.monthly.length <= 14, style: { fontSize: '15px', textOutline: 'none' } } },
        legend: { itemStyle: { fontSize: '17px' } },
        credits: { enabled: false },
        tooltip: { shared: true, valueSuffix: ' ครั้ง' },
        plotOptions: { column: { stacking: 'normal', maxPointWidth: 48, borderWidth: 1, borderColor: '#ffffff', borderRadius: 2 } },
        series: groups.map(g => ({ name: AGE_TH[g], color: AGE_C[g], data: r.monthly.map(m => m[g]) }))
    });
    setHtml('anc-dest-list', barList(r.dest.slice(0, 10).map(x => ({ name: x.name, n: x.n })), 'ไม่มี refer ในช่วงนี้'));
    setHtml('anc-dx-list', barList(r.top_dx.map(x => ({ name: `${x.code3} ${x.name || ''}`.trim(), n: x.n, pct: x.pct })), 'ไม่มีข้อมูล'));
    setText('anc-dx-note', r.dx_excluded ? `ไม่จัดอันดับ ${r.dx_exclude.join(', ')} (ยังไม่ยืนยันการตั้งครรภ์ / มาฝากครรภ์ปกติ) ${fmtN(r.dx_excluded)} ครั้ง` : '');
    setHtml('anc-refer-table', r.table.length ? r.table.map(t => `<tr><td class="text-nowrap">${monthLabel(t.month)}</td>
        <td><b>${esc(t.chapter === 'ไม่ระบุ' ? '—' : t.chapter)}</b> ${esc(t.chapter_name)}
            ${t.dx.length ? `<small class="anc-src">${t.dx.slice(0, 3).map(x => esc(`${x.code3} ${x.name || ''}`.trim()) + (x.n > 1 ? ` (${x.n})` : '')).join(' · ')}${t.dx.length > 3 ? ` · +${t.dx.length - 3} Dx` : ''}</small>` : ''}
            <small class="anc-src"><i class="fa-solid fa-arrow-right" aria-hidden="true"></i> ${esc(t.dest_name)}</small></td>
        <td class="text-right">${fmtN(t.n)}</td><td class="text-right">${fmt1(t.avg_min)}</td></tr>`).join('')
        : '<tr><td colspan="4" class="er-note">ไม่มี refer ในช่วงนี้</td></tr>');
}

/* ---------- 12 แนวโน้ม 3 ปีงบ (ช่วงของปีงบที่มีวันสิ้นสุด และ 2 ปีก่อนหน้า) ---------- */
async function loadFy3() {
    const d2 = bbhPeriod.state.d2;
    const [y, m] = d2.split('-').map(Number);
    const start = m >= 10 ? y : y - 1;
    const years = [2, 1, 0].map(i => {
        const a = `${start - i}-10-01`;
        const full = `${start - i + 1}-09-30`;
        return { label: `ปีงบ ${start - i + 544}`, d1: a, d2: full < d2 ? full : d2, partial: full > d2 };
    });
    setHtml('anc-fy3-head', `<tr><th>ตัวชี้วัด</th>${years.map(x => `<th class="text-right">${x.label}${x.partial ? '<small> ถึง ' + shortDate(x.d2) + '</small>' : ''}</th>`).join('')}<th class="text-right">เป้า</th></tr>`);
    try {
        const data = await Promise.all(years.map(x => getJson(`../api/anc_indicators.php?d1=${x.d1}&d2=${x.d2}&scope=${opts.scope}${opts.nat ? '&nat=' + opts.nat : ''}`)));
        setHtml('anc-fy3-body', IND_ROWS.map(([key]) => {
            const cells = data.map((d, i) => {
                const r = d[key];
                let arrow = '';
                if (i > 0 && r.value !== null && data[i - 1][key].value !== null) {
                    const diff = r.value - data[i - 1][key].value;
                    const better = LOWER.includes(key) ? diff < 0 : diff > 0;
                    arrow = Math.abs(diff) < 0.05 ? '' : ` <span class="anc-delta ${better ? 'is-up' : 'is-down'}" title="${better ? 'ดีขึ้น' : 'แย่ลง'} ${fmt1(Math.abs(diff))} จุด">${diff > 0 ? '▲' : '▼'}${fmt1(Math.abs(diff))}</span>`;
                }
                return `<td class="text-right${r.low_base ? ' is-faint' : ''}"><b>${fmtP(r.value)}</b>${arrow}<small class="anc-src">${fmtN(r.num)}/${fmtN(r.den)}</small></td>`;
            }).join('');
            const t = data[2][key].target;
            return `<tr><td>${esc(IND_SHORT[key])}</td>${cells}<td class="text-right">${t === null ? '—' : (LOWER.includes(key) ? '≤ ' : '≥ ') + t + '%'}</td></tr>`;
        }).join(''));
    } catch (err) {
        setHtml('anc-fy3-body', errRow(5, err));
    }
}

/* ---------- รายชื่อ (bbhDrill) ---------- */
function openList(type, label) {
    const s = bbhPeriod.state;
    const q = new URLSearchParams({ type, scope: opts.scope });
    if (opts.nat) q.set('nat', opts.nat);
    const period = bbhPeriod.query();
    const date = { nowrap: true, display: shortDate };
    const isRefer = type === 'refer';
    const resultName = { anemia: 'ซีด (Hct < 33)', teen_repeat: 'ครรภ์ที่ ≥ 2', preterm: 'คลอด < 37 wk' }[type] || 'ผ่านเกณฑ์';
    const hasResult = !['new', 'open', 'labor_missing', 'refer'].includes(type);
    const cols = isRefer ? [
        ['date', 'วันที่ refer', date], ['hn', 'HN', { nowrap: true }], ['name', 'ชื่อ-สกุล', { nowrap: true }], ['age', 'อายุ'],
        ['pdx', 'Dx หลัก', { nowrap: true }], ['dx_name', 'ชื่อโรค', { max: '16em' }], ['dest', 'ปลายทาง', { max: '14em' }], ['reason', 'เหตุที่นับ']
    ] : [
        ['hn', 'HN', { nowrap: true }], ['name', 'ชื่อ-สกุล', { nowrap: true }], ['age', 'อายุ'], ['addr', 'ที่อยู่', { max: '10em' }],
        ['register', 'ลงทะเบียน', date], ['lmp', 'LMP', date], ['labor', 'วันคลอด', date], ['ga1', 'GA ครั้งแรก'], ['n_anc', 'ANC ที่ รพ.'],
        ...(type === 'visit5' || type === 'visit8' ? [['missing', 'ช่วง GA ที่ขาด', { max: '14em' }]] : []),
        ...(type === 'anemia' ? [['hct', 'Hct ล่าสุด']] : []),
        ...(type === 'teen_repeat' ? [['gravida', 'ครรภ์ที่']] : []),
        ...(hasResult ? [['result', resultName]] : [])
    ];
    bbhDrill.open({
        title: label,
        sub: `${thaiDate(s.d1)} – ${thaiDate(s.d2)} · ตัวหาร${opts.scope === 'area' ? 'ในเขต' : 'ทุกครรภ์'}`,
        url: '../api/anc_visit_list.php?' + period + '&' + q,
        placeholder: 'ค้นหา HN / ชื่อ / ที่อยู่',
        cols,
        filters: [...(hasResult ? [['result', resultName]] : []), ['group', 'กลุ่มอายุ'], ...(isRefer ? [['reason', 'เหตุที่นับ']] : [['nat', 'สัญชาติ']])],
        searchKeys: ['hn', 'name', 'addr', 'pdx', 'dx_name', 'dest'],
        unit: isRefer ? 'ครั้ง' : 'ราย',
        file: `ANC_${type}_${s.d1}_${s.d2}.csv`,
        summary: rows => isRefer ? `${fmtN(rows.length)} ครั้ง` : hasResult
            ? `${fmtN(rows.length)} ราย · ${resultName} ${fmtN(rows.filter(r => r.result === 'ใช่').length)} ราย` : `${fmtN(rows.length)} ราย`
    });
}
bbhDrill.bind(document.getElementById('anc-page'), (type, n) => openList(type, n.dataset.label || ''));

/* ---------- โหลดทั้งหน้า ---------- */
function loadAll() {
    loadSeq++;
    loadSummary();
    loadIndicators();
    loadRefer();
    loadScreen('risk27', renderRisk27, e => setHtml('anc-r27-tiles', `<p class="er-note">โหลดข้อมูลไม่สำเร็จ: ${esc(e.message)}</p>`));
    loadScreen('anemia', renderAnemia, e => setHtml('anc-hct-tiles', `<p class="er-note">โหลดข้อมูลไม่สำเร็จ: ${esc(e.message)}</p>`));
    loadScreen('ogtt', renderOgtt, e => setHtml('anc-ogtt-body', errRow(5, e)));
    loadScreen('sti', renderSti, e => setHtml('anc-sti-body', errRow(7, e)));
    loadFy3();
}

document.querySelectorAll('.anc-filters [data-scope], .anc-filters [data-nat]').forEach(b => b.addEventListener('click', () => {
    const key = 'scope' in b.dataset ? 'scope' : 'nat';
    opts[key] = b.dataset[key];
    document.querySelectorAll(`.anc-filters [data-${key}]`).forEach(x => x.setAttribute('aria-pressed', String(x === b)));
    loadAll();
}));

loadAll();
document.getElementById('budget-year').addEventListener('change', loadAll);
