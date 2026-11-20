/* =========================================================
   หน้าผู้ป่วยใน (pages/ipd_detail.php)
   ช่วงเวลา: assets/js/period_filter.js (bbhPeriod) · ตัวเลข: api/ipd_kpi_summary.php, bed_occupancy_trend.php, ipd_top10_disease.php
   คลิกตัวเลข/แท่ง/แถว → รายชื่อ (api/ipd_visit_list.php ต้อง login) ผ่าน bbhDrill
========================================================= */

const G = { light: '#60a5fa', main: '#2563eb', dark: '#1e40af', soft: '#bfdbfe' };   // ชุดฟ้า–น้ำเงินของหน้า IPD (ชื่อ G คงไว้แบบหน้า OPD)
const OCC_WARN = '#b45309';   // ครองเตียง 90–100%
const OCC_BAD = '#c2410c';    // เกิน 100%
const gCol = (top, bottom) => ({ linearGradient: { x1: 0, y1: 0, x2: 0, y2: 1 }, stops: [[0, top], [1, bottom]] });
const esc = bbhDrill.esc;
const fmtN = n => Number(n || 0).toLocaleString('th-TH');
const fmtP = n => Number(n || 0).toLocaleString('th-TH', { minimumFractionDigits: 1, maximumFractionDigits: 1 }) + '%';
const isoDate = d => d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
const todayIso = () => isoDate(new Date());
const thaiDate = iso => iso ? new Date(iso + 'T00:00:00').toLocaleDateString('th-TH', { day: 'numeric', month: 'short', year: 'numeric' }) : '';
const shortDate = iso => { const [y, m, d] = String(iso || '').split('-').map(Number); return y ? `${d}/${m}/${String(y + 543).slice(2)}` : ''; };
const setText = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = v; };

async function getJson(url) {
    const res = await fetch(url);
    const data = await res.json();
    if (!res.ok || data.error) throw new Error(data.error || 'HTTP ' + res.status);
    return data;
}

/* ---------- 1–5 ตัวเลขสรุป ---------- */
async function loadKpi() {
    try {
        const d = await getJson('../api/ipd_kpi_summary.php?' + bbhPeriod.query());

        setText('kpi-current', fmtN(d.current_inpatients));
        setText('kpi-beds', fmtN(d.total_beds));
        setText('kpi-admit', fmtN(d.admissions));
        setText('kpi-dch', fmtN(d.discharges));
        setText('kpi-death', fmtN(d.deaths));
        setText('kpi-death-rate', fmtP(d.death_rate));
        setText('kpi-refer', fmtN(d.refers));
        setText('kpi-refer-rate', fmtP(d.refer_rate));

        // 2 เตียง: เกณฑ์ < 90% ปกติ · 90–100% ใกล้เต็ม · > 100% เกินศักยภาพ (ข้อความกำกับเสมอ ไม่พึ่งสีอย่างเดียว)
        const occ = Number(d.occupancy_rate || 0);
        setText('kpi-occ', fmtP(occ));
        const occLevel = occ > 100 ? 'bad' : occ >= 90 ? 'warn' : 'good';
        const occStatus = document.getElementById('occ-status');
        occStatus.textContent = { bad: 'เกิน 100% เตียงเต็มเกินศักยภาพ', warn: '90–100% ใกล้เต็ม', good: 'ต่ำกว่า 90%' }[occLevel];
        occStatus.className = occLevel === 'good' ? '' : 'ipd-flag-' + occLevel;
        const meter = document.getElementById('occ-meter');
        meter.className = 'er-meter is-' + occLevel;
        meter.firstElementChild.style.width = Math.min(100, occ) + '%';
        meter.setAttribute('aria-label', 'อัตราครองเตียง ' + fmtP(occ));
        setText('kpi-beds2', fmtN(d.total_beds));
        setText('kpi-pdays', fmtN(d.patient_days));

        // 3 คุณภาพ: Readmission เป้า < 8% · 8–10% เฝ้าระวัง · > 10% สูง
        setText('kpi-los', Number(d.avg_los || 0).toLocaleString('th-TH', { minimumFractionDigits: 1, maximumFractionDigits: 1 }));
        setText('kpi-readmit', fmtN(d.readmission_cases));
        setText('kpi-readmit-rate', fmtP(d.readmission_rate));
        const rr = Number(d.readmission_rate || 0);
        const rs = document.getElementById('readmit-status');
        rs.textContent = rr > 10 ? 'สูงกว่า 10%' : rr >= 8 ? 'เฝ้าระวัง 8–10%' : 'ตามเป้า < 8%';
        rs.className = rr > 10 ? 'ipd-flag-bad' : rr >= 8 ? 'ipd-flag-warn' : '';
        setText('kpi-death-rate2', fmtP(d.death_rate));
        setText('kpi-refer-rate2', fmtP(d.refer_rate));

        // 4 สถานะการจำหน่าย — แถวที่เพิ่มทีหลังต้องใส่ role/tabindex เอง (bbhDrill.bind ตั้งให้เฉพาะตอนผูก)
        const total = d.discharges || 0;
        const types = [...d.dch_types].sort((a, b) => b.n - a.n);
        document.getElementById('ipd-dch-list').innerHTML = types.length ? types.map(t => `
            <div class="er-stat" data-drill="dchtype" data-key="${esc(t.code)}" data-label="${esc(t.name)}" role="button" tabindex="0" title="คลิกเพื่อดูรายชื่อ">
                <span class="er-stat-label"><span class="er-ico" aria-hidden="true"><i class="fa-solid ${dchIcon(t.code)}"></i></span>${esc(t.name)}</span>
                <span class="er-stat-value"><b>${fmtN(t.n)}</b> ราย <small>${total ? fmtP(t.n * 100 / total) : '–'}</small></span>
            </div>`).join('') : '<p class="er-note">ไม่มีผู้ป่วยจำหน่ายในช่วงนี้</p>';

        // 5 รายหอผู้ป่วย: การ์ดแบบหน้าแรก (index.php loadWardBed) — ครองเตียงตอนนี้ + ยอดของช่วงที่เลือก
        document.getElementById('ipd-ward-list').innerHTML = d.wards.map(wardCard).join('') || '<p class="er-note">ไม่พบข้อมูลหอผู้ป่วย</p>';

    } catch (err) {
        console.error('IPD KPI error:', err);
        ['kpi-current', 'kpi-admit', 'kpi-dch', 'kpi-death', 'kpi-refer', 'kpi-occ', 'kpi-los', 'kpi-readmit'].forEach(id => setText(id, '—'));
        document.getElementById('ipd-dch-list').innerHTML = '<p class="er-note">โหลดข้อมูลไม่สำเร็จ</p>';
        document.getElementById('ipd-ward-list').innerHTML = '<p class="er-note">โหลดข้อมูลไม่สำเร็จ</p>';
    }
}

// ไอคอนตามชื่อ Ward ชุดเดียวกับหน้าแรก (index.php WARD_ICONS) — "หลังคลอด" ต้องมาก่อน "คลอด"
const WARD_ICONS = [
    [/พิเศษ/, 'fa-door-open'], [/หลังคลอด|^pp/i, 'fa-person-breastfeeding'], [/ทารก|snb/i, 'fa-baby'],
    [/เด็ก/, 'fa-child'], [/stroke/i, 'fa-brain'], [/icu/i, 'fa-heart-pulse'], [/ศัลย/, 'fa-user-doctor'],
    [/หญิง/, 'fa-person-dress'], [/ชาย/, 'fa-person'], [/คลอด|^lr/i, 'fa-person-pregnant'], [/home/i, 'fa-house-user']
];
const wardIcon = name => (WARD_ICONS.find(([re]) => re.test(String(name || '').trim())) || [0, 'fa-bed'])[1];
// เกณฑ์สีและป้ายเดียวกับการ์ดหน้าแรก: ≥100 เต็ม/เกิน · ≥75 ใกล้เต็ม · ≥50 ปานกลาง · ต่ำกว่านั้น ว่าง
const WARD_LABEL = { success: 'ว่าง', info: 'ปานกลาง', warning: 'ใกล้เต็ม', danger: 'เต็ม/เกิน' };
const wardLevel = r => (r >= 100 ? 'danger' : r >= 75 ? 'warning' : r >= 50 ? 'info' : 'success');

function wardCard(w) {
    const level = wardLevel(w.current_rate);
    const free = w.current >= w.beds ? '<span class="bed-full">เต็ม</span>' : `ว่าง ${fmtN(w.beds - w.current)}`;
    const avg = w.occupancy_rate.toLocaleString('th-TH', { minimumFractionDigits: 1, maximumFractionDigits: 1 });
    return `
    <a class="ward-card" href="ipd_ward_detail.php?ward=${encodeURIComponent(w.ward)}" aria-label="${esc(w.name)} ดูรายละเอียดหอผู้ป่วย">
        <span class="ward-icon text-${level}" aria-hidden="true"><i class="fa-solid ${wardIcon(w.name)}"></i></span>
        <div class="ward-name">${esc(w.name)}</div>
        <div class="ward-detail">Admit ${fmtN(w.current)} / ${fmtN(w.beds)} เตียง (${free})</div>
        <div class="ward-percent">
            <span>ครองเตียง <strong class="ward-rate-${level}">${w.current_rate}%</strong></span>
            <span class="ward-status ward-status-${level}">${WARD_LABEL[level]}</span>
        </div>
        <div class="progress ward-progress" role="img" aria-label="ครองเตียงตอนนี้ ${w.current_rate}%"><div class="progress-bar bg-${level}" style="width:${Math.min(w.current_rate, 100)}%"></div></div>
        <div class="ward-period">ช่วงที่เลือก: รับใหม่ <b>${fmtN(w.admissions)}</b> ราย · ครองเตียงเฉลี่ย <b>${avg}%</b>${w.home ? '<br>ไม่นับรวมในเตียงทั้งหมด (ผู้ป่วยนอนที่บ้าน)' : ''}</div>
    </a>`;
}

function dchIcon(code) {
    return { '01': 'fa-house-medical-circle-check', '02': 'fa-user-xmark', '03': 'fa-person-running', '04': 'fa-truck-medical', '08': 'fa-heart-crack', '09': 'fa-heart-crack' }[code] || 'fa-circle-question';
}

/* ---------- 6 แนวโน้ม ---------- */
async function loadTrend() {
    let json;
    try {
        json = await getJson('../api/bed_occupancy_trend.php?' + bbhPeriod.query());
    } catch (err) {
        console.error('IPD trend error:', err);
        return;
    }
    const s = bbhPeriod.state;
    const rows = json.data;
    const isDay = json.unit === 'day';
    const labels = rows.map(r => r.label);
    const lastPartial = rows.length > 0 && s.d2 === todayIso();   // วัน/เดือนสุดท้ายยังไม่จบ
    const many = rows.length > 16;                               // รายวันหลายแท่ง ป้ายตัวเลขจะทับกัน

    const bucket = r => {
        let d2 = r.start;
        if (!isDay) { const [y, m] = r.start.split('-').map(Number); d2 = isoDate(new Date(y, m, 0)); }
        return { d1: r.start < s.d1 ? s.d1 : r.start, d2: d2 > s.d2 ? s.d2 : d2 };
    };

    Highcharts.chart('ipd-chart', {
        chart: { type: 'column', style: { fontFamily: 'THSarabun' } },
        title: { text: null },
        subtitle: { text: lastPartial ? `แท่งสีอ่อน = ${isDay ? 'วันนี้ (ยังไม่จบวัน)' : 'เดือนปัจจุบัน (ยังไม่ครบเดือน)'}` : null },
        xAxis: { categories: labels },
        yAxis: { min: 0, allowDecimals: false, title: { text: 'จำนวน (ราย)' } },
        legend: { enabled: false },
        credits: { enabled: false },
        tooltip: { pointFormat: 'รับใหม่: <b>{point.y:,.0f} ราย</b>' },
        plotOptions: {
            column: {
                maxPointWidth: 72, borderRadius: 4, borderWidth: 0, cursor: 'pointer',
                point: { events: { click() {
                    const r = rows[this.index], b = bucket(r);
                    if (this.y) openList('admit', '', (isDay ? 'วันที่ ' : 'เดือน') + r.label, b.d1, b.d2);
                } } }
            }
        },
        series: [{
            name: 'รับใหม่',
            color: gCol(G.main, G.dark),
            data: rows.map((r, i) => (lastPartial && i === rows.length - 1 ? { y: r.admit, color: G.soft } : r.admit)),
            dataLabels: { enabled: !many, style: { fontSize: '16px', textOutline: 'none', color: '#17241d' } }
        }]
    });

    Highcharts.chart('bed-occupancy-chart', {
        chart: { type: 'column', style: { fontFamily: 'THSarabun' } },
        title: { text: null },
        xAxis: { categories: labels },
        yAxis: {
            min: 0, softMax: 105, title: { text: 'อัตราครองเตียง (%)' }, labels: { format: '{value}%' },
            plotLines: [
                { value: 90, color: OCC_WARN, dashStyle: 'Dash', width: 1, zIndex: 4, label: { text: '90%', align: 'right', style: { color: OCC_WARN } } },
                { value: 100, color: OCC_BAD, dashStyle: 'Dash', width: 1, zIndex: 4, label: { text: '100%', align: 'right', style: { color: OCC_BAD } } }
            ]
        },
        legend: { enabled: false },
        credits: { enabled: false },
        tooltip: { pointFormat: 'อัตราครองเตียง: <b>{point.y:.1f}%</b>' },
        plotOptions: { column: { maxPointWidth: 72, borderRadius: 4, borderWidth: 0 } },
        series: [{
            name: 'อัตราครองเตียง',
            // สีตามเกณฑ์ (เส้นประ 90% / 100% บอกความหมายด้วย)
            data: rows.map(r => ({ y: r.occupancy_rate, color: r.occupancy_rate > 100 ? OCC_BAD : r.occupancy_rate >= 90 ? OCC_WARN : G.main })),
            dataLabels: { enabled: !many, format: '{y:.1f}%', style: { fontSize: '16px', textOutline: 'none', color: '#17241d' } }
        }]
    });
}

/* ---------- 7 10 อันดับโรค ---------- */
async function loadTop10() {
    const body = document.getElementById('top10-ipd-body');
    try {
        const data = await getJson('../api/ipd_top10_disease.php?' + bbhPeriod.query());
        const rankColor = { 1: 'background:#FFD700;color:#333;', 2: 'background:#C0C0C0;color:#333;', 3: 'background:#CD7F32;color:#1f1f1f;' };
        body.innerHTML = data.map((row, i) => {
            const name = (row.tname || '').trim() || row.diag || row.pdx;
            return `
            <tr data-drill="pdx" data-key="${esc(row.pdx)}" data-label="${esc(row.pdx + ' ' + name)}" role="button" tabindex="0" title="คลิกเพื่อดูรายชื่อ">
                <td class="text-center"><span class="rank-badge${rankColor[i + 1] ? '' : ' ipd-rank'}" style="${rankColor[i + 1] || ''}">${i + 1}</span></td>
                <td class="text-center"><span class="badge bg-secondary" style="font-size:14px;letter-spacing:1px">${esc(row.pdx)}</span></td>
                <td>${diagCell(row.tname, row.diag)}</td>
                <td class="text-center fw-bold er-total">${fmtN(row.total_case)}</td>
                <td class="text-center" style="color:#2563eb">${fmtN(row.male)}</td>
                <td class="text-center" style="color:#be185d">${fmtN(row.female)}</td>
            </tr>`;
        }).join('') || '<tr><td colspan="6" class="text-center text-muted py-3">ไม่พบข้อมูลผู้ป่วยที่จำหน่ายในช่วงนี้</td></tr>';
    } catch (err) {
        console.error('IPD top10 error:', err);
        body.innerHTML = '<tr><td colspan="6" class="text-center text-danger py-3">ไม่สามารถโหลดข้อมูลได้</td></tr>';
    }
}

// ชื่อโรค: ไทยเป็นหลัก อังกฤษตัวเล็กใต้ชื่อ — รูปแบบเดียวกันทั้งหน้า OPD / IPD / ER
function diagCell(th, en) {
    th = (th || '').trim();
    en = (en || '').trim();
    const sub = th && en && th !== en ? `<div class="diag-en">${esc(en)}</div>` : '';
    return `<div>${esc(th || en || '—')}</div>${sub}`;
}

/* ---------- รายชื่อ (api/ipd_visit_list.php) ---------- */
const LIST_TITLES = {
    current: 'ผู้ป่วยในตอนนี้', admit: 'รับใหม่', dch: 'จำหน่าย', death: 'เสียชีวิต',
    dchtype: 'จำหน่าย', readmit: 'Readmission ภายใน 28 วัน (โรคเดิม)', pdx: 'การวินิจฉัยหลัก'
};

function openList(type, key, label, d1, d2) {
    const s = bbhPeriod.state;
    d1 = d1 || s.d1;
    d2 = d2 || s.d2;
    const now = type === 'current';
    const qs = new URLSearchParams({ type });
    if (key) qs.set('key', key);
    if (!now) { qs.set('d1', d1); qs.set('d2', d2); }

    const date = { nowrap: true, display: shortDate };
    const cols = [
        ['an', 'AN', { nowrap: true }], ['hn', 'HN', { nowrap: true }], ['name', 'ชื่อ-สกุล', { nowrap: true }],
        ['age', 'อายุ'], ['sex', 'เพศ'], ['ward', 'หอผู้ป่วย', { max: '10em' }],
        ['regdate', 'วันที่ Admit', date],
        ...(now ? [] : [['dchdate', 'วันที่จำหน่าย', date]]),
        ...(type === 'readmit' ? [['prev_dch', 'จำหน่ายครั้งก่อน', date]] : []),
        ['los', now ? 'นอนมาแล้ว (วัน)' : 'วันนอน'],
        ['pdx', 'การวินิจฉัยหลัก', { max: '16em' }],
        ...(now ? [] : [['dchtype', 'สถานะจำหน่าย']]),
        ['pttype', 'สิทธิ์', { short: true, max: '12em' }]
    ];
    const head = type === 'dchtype' || type === 'pdx' ? `${LIST_TITLES[type]}: ${label}` : LIST_TITLES[type] + (label ? `: ${label}` : '');

    bbhDrill.open({
        title: head,
        sub: now ? 'ผู้ป่วยที่ยังไม่จำหน่าย ณ ตอนนี้ (ไม่นับ LR, Observe ER, Home Ward)' : `${thaiDate(d1)} – ${thaiDate(d2)}`,
        url: '../api/ipd_visit_list.php?' + qs,
        placeholder: 'ค้นหา AN / HN / ชื่อ / หอผู้ป่วย / โรค',
        cols,
        exportCols: cols.map(c => (c[0] === 'pdx' ? ['pdx_text', 'การวินิจฉัยหลัก'] : c)),
        filters: [['ward', 'หอผู้ป่วย'], ...(now ? [] : [['dchtype', 'สถานะจำหน่าย']]), ['pttype', 'สิทธิ์']],
        mapRows: data => data.rows.map(r => ({ ...r, pdx_text: r.pdx ? `${r.pdx} ${r.diag_th || r.diag_en || ''}`.trim() : '' })),
        cell: (r, k) => (k === 'pdx' && r.pdx ? `<b>${esc(r.pdx)}</b> ${esc(r.diag_th || r.diag_en || '')}` : undefined),
        searchKeys: ['an', 'hn', 'name', 'ward', 'pdx_text', 'pttype'],
        unit: 'ราย',
        file: `IPD_${type}${key ? '_' + key : ''}${now ? '' : '_' + d1 + '_' + d2}.csv`,
        summary: rows => `${fmtN(rows.length)} ราย (${fmtN(new Set(rows.map(r => r.hn)).size)} คน)`
    });
}

// การ์ด/แถวที่มี data-drill ทั้งหน้า (แถวที่สร้างภายหลังใช้ event delegation เดียวกัน)
bbhDrill.bind(document.getElementById('ipd-page'), (type, n) => openList(type, n.dataset.key || '', n.dataset.label || ''));

// เลือก "วันนี้" (ช่วง 1 วัน = วันนี้) = Live: ป้ายบนแถบหัว + LIVE ใน navbar และรีเฟรชทุก 60 วินาที (cache API 60 วินาที)
const isLive = () => { const s = bbhPeriod.state; return s.d1 === todayIso() && s.d2 === todayIso(); };

async function reloadAll() {
    const live = isLive();
    if (!live) window.bbhSetLive(false, 'ipd-live-badge');   // เปลี่ยนไปช่วงอื่น: ซ่อนทันที ไม่รอโหลดเสร็จ
    await Promise.all([loadKpi(), loadTrend(), loadTop10()]);
    window.bbhSetLive(live && isLive(), 'ipd-live-badge');
}

reloadAll();
document.getElementById('budget-year').addEventListener('change', reloadAll);
bbhPoll(() => { if (isLive()) reloadAll(); }, 60000);
