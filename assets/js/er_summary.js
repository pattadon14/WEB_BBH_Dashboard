/* การ์ดสรุปผู้รับบริการ ER รายวัน (pages/ER_detail.php) — ข้อมูลจาก api/er_today_summary.php
 * ตัวเลือกวันที่แยกจากตัวกรองช่วงเวลาของกราฟด้านล่าง เหมือนการ์ดสรุปหน้า OPD
 * ดูวันนี้: รีเฟรชทุก 60 วินาที (bbhPoll ใน templates/header.php) */
(function () {
    'use strict';

    const $ = id => document.getElementById(id);
    const input = $('er-date');
    if (!input) return;

    const TH_MONTHS = ['มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน',
        'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];
    const iso = d => d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    const thai = s => { const [y, m, d] = s.split('-').map(Number); return `${d} ${TH_MONTHS[m - 1]} ${y + 543}`; };
    const fmt = n => Number(n || 0).toLocaleString();
    const today = () => iso(new Date());
    const daysAgo = n => { const d = new Date(); d.setDate(d.getDate() - n); return iso(d); };
    const buttons = document.querySelectorAll('[data-er-day]');

    input.max = today();
    input.value = today();

    let seq = 0;   // ผลที่กลับมาช้ากว่าการเปลี่ยนวันที่รอบหลัง ทิ้งไป
    async function load() {
        const date = input.value || today(), isToday = date === today(), my = ++seq;

        $('er-date-text').textContent = thai(date);
        $('er-day-title').textContent = 'ผู้มารับบริการ ER ' + (isToday ? 'วันนี้' : 'วันที่ ' + thai(date));
        buttons.forEach(b => b.setAttribute('aria-pressed', String(daysAgo(Number(b.dataset.erDay)) === date)));

        loadD2D(date, my);
        loadLos(date, my);

        let data;
        try {
            data = await (await fetch('../api/er_today_summary.php?date=' + encodeURIComponent(date), { cache: 'no-store' })).json();
        } catch (e) {
            data = { error: 'โหลดข้อมูลไม่สำเร็จ' };
        }
        if (my !== seq) return;
        if (data.error) { $('er-compare').textContent = data.error; return; }

        const map = {
            'er-total': data.total, 'er-people': data.people, 'er-ems': data.ems, 'er-refer-in': data.refer_in,
            'er-l12': data.l1 + data.l2,
            'er-pt-emergency': data.pt_emergency, 'er-pt-accident': data.pt_accident,
            'er-pt-general': data.pt_general, 'er-pt-service': data.pt_service,
            'er-shift-night': data.shift_night, 'er-shift-morning': data.shift_morning, 'er-shift-evening': data.shift_evening,
            'er-home': data.home, 'er-gone': data.gone, 'er-no-dch': data.no_dch, 'er-other-dch': data.other_dch,
            'er-admit': data.admit, 'er-refer': data.refer_all, 'er-refer-out': data.refer
        };
        Object.keys(map).forEach(id => { $(id).textContent = fmt(map[id]); });

        // แถบสัดส่วนระดับ 1–5 / Black / ไม่ระบุ — Black กับไม่ระบุซ่อนจากคำอธิบายเมื่อเป็น 0
        const share = n => data.total ? (n * 100 / data.total) + '%' : '0%';
        [1, 2, 3, 4, 5, 6, 0].forEach(l => {
            $('er-bar-' + l).style.width = share(data['l' + l]);
            $('er-l' + l).textContent = fmt(data['l' + l]);
        });
        $('er-lg-6').hidden = !data.l6;
        $('er-lg-0').hidden = !data.l0;
        $('er-level-bar').setAttribute('aria-label', [1, 2, 3, 4, 5].map(l => `ระดับ ${l} ${fmt(data['l' + l])}`).join(' · ') +
            ` จากทั้งหมด ${fmt(data.total)} ครั้ง`);

        // เทียบวันเดียวกันของสัปดาห์ก่อน (เหมือนหน้า OPD) — มากกว่า ▲ เขียว / น้อยกว่า ▼ แดง
        const prev = new Date(date + 'T00:00:00');
        prev.setDate(prev.getDate() - 7);
        const label = prev.toLocaleDateString('th-TH', { weekday: 'long', day: 'numeric', month: 'short' });
        let delta = '';
        if (data.prev_week) {
            const p = Math.round((data.total - data.prev_week) * 100 / data.prev_week);
            delta = p === 0 ? ' <span class="opd-delta">(เท่ากัน)</span>'
                : ` <span class="opd-delta ${p > 0 ? 'up' : 'down'}">${p > 0 ? '▲' : '▼'} ${Math.abs(p)}%</span>`;
        }
        $('er-compare').innerHTML = `เทียบ${label} ${fmt(data.prev_week)} ครั้ง${delta}` + (isToday ? ' — นับถึงเวลาเดียวกัน' : '');
    }

    /* Door-to-Doctor แยกตามระดับ: เฉลี่ย (นาที) + ทันเป้า (จากรายที่มีเวลาพบแพทย์) */
    async function loadD2D(date, my) {
        const grid = $('er-d2d-grid'), note = $('er-d2d-note');
        let data;
        try {
            data = await (await fetch('../api/er_d2d.php?date=' + encodeURIComponent(date), { cache: 'no-store' })).json();
        } catch (e) {
            data = { error: 'โหลดข้อมูลไม่สำเร็จ' };
        }
        if (my !== seq) return;
        if (data.error) { grid.innerHTML = ''; note.textContent = data.error; return; }

        const pct = (a, b) => b ? Math.round(a * 100 / b) : 0;
        grid.innerHTML = data.items.map(it => {
            const p = pct(it.within, it.timed);
            const avg = it.avg === null ? '—' : it.avg.toLocaleString(undefined, { maximumFractionDigits: 1 });
            const late = it.avg !== null && it.avg > it.target;
            return `<div class="er-d2d-tile er-tri-${it.level}">
                <div class="er-d2d-head"><span class="er-lvl">ระดับ ${it.level}</span><span class="er-d2d-target">เป้า ${it.target_label}</span></div>
                <div class="er-d2d-avg"><span class="er-d2d-label">เฉลี่ย</span> <b>${avg}</b> <span class="er-d2d-label">นาที</span>${late ? ' <span class="er-d2d-late">เกินเป้า</span>' : ''}</div>
                <div class="er-d2d-meter" role="img" aria-label="ทันเป้า ${p}%"><span style="width:${p}%"></span></div>
                <div class="er-d2d-within">${it.timed ? `ทันเป้า ${fmt(it.within)} จาก ${fmt(it.timed)} ราย (${p}%)` : (it.n ? 'ยังไม่มีเวลาพบแพทย์' : 'ไม่มีผู้ป่วย')}</div>
            </div>`;
        }).join('');
        const n = data.items.reduce((s, it) => s + it.n, 0), t = data.items.reduce((s, it) => s + it.timed, 0);
        note.textContent = `มีเวลาพบแพทย์ ${fmt(t)} จาก ${fmt(n)} ราย (${pct(t, n)}%) · ใช้เวลาที่เร็วที่สุดของ กดเริ่มตรวจ / สั่ง Lab / X-ray / สั่งยา / บันทึกรายงานแพทย์ใน HPI · ไม่รวม Black`;
    }

    /* สถานะ ER ตอนนี้ (เฉพาะวันนี้) + LOS / เข้า ER ถึง Admit / Refer ของวันที่เลือก */
    const hm = m => m === null || m === undefined ? '—' : m < 60 ? `${m} นาที` : `${Math.floor(m / 60)} ชม. ${m % 60} นาที`;
    async function loadLos(date, my) {
        const grid = $('er-los-grid'), note = $('er-los-note');
        let data;
        try {
            data = await (await fetch('../api/er_los.php?date=' + encodeURIComponent(date), { cache: 'no-store' })).json();
        } catch (e) {
            data = { error: 'โหลดข้อมูลไม่สำเร็จ' };
        }
        if (my !== seq) return;
        $('er-now').hidden = !data.now;
        if (data.error) { grid.innerHTML = ''; note.textContent = data.error; return; }

        if (data.now) {
            $('er-now-at').textContent = `(ณ ${data.now.at} น.)`;
            $('er-now-in').textContent = fmt(data.now.in_er);
            $('er-now-l12').textContent = fmt(data.now.l12);
            $('er-now-wait').textContent = fmt(data.now.waiting);
            $('er-now-over').textContent = fmt(data.now.over4h);
            $('er-now-long').textContent = hm(data.now.longest);
        }

        const L = data.los, b = data.boarding_min;
        const pct = (a, n) => n ? Math.round(a * 100 / n) : 0;
        const tile = (head, sub, avg, line, meter) => `<div class="er-d2d-tile">
                <div class="er-d2d-head"><span>${head}</span><span class="er-d2d-target">${sub}</span></div>
                <div class="er-d2d-avg"><span class="er-d2d-label">เฉลี่ย</span> <b>${avg === null ? '—' : fmt(avg)}</b> <span class="er-d2d-label">นาที</span></div>
                ${meter === null ? '' : `<div class="er-d2d-meter" role="img" aria-label="${meter.label}"><span style="width:${meter.p}%"></span></div>`}
                <div class="er-d2d-within">${line}</div>
            </div>`;
        const ttd = (name, x) => tile(`เข้า ER ถึง ${name}`, `เกณฑ์ ≤ ${b} นาที`, x.avg,
            x.n ? `รอเกิน ${b} นาที ${fmt(x.over)} จาก ${fmt(x.n)} ราย (${pct(x.over, x.n)}%)` : `ไม่มี${name}`,
            x.n ? { p: pct(x.over, x.n), label: `รอเกิน ${pct(x.over, x.n)}%` } : null);
        grid.innerHTML =
            tile('อยู่ใน ER ทั้งหมด', L.median === null ? '' : `มัธยฐาน ${fmt(L.median)} นาที`, L.avg,
                L.timed ? `เกิน 4 ชม. ${fmt(L.over4h)} ราย · เกิน 6 ชม. ${fmt(L.over6h)} ราย` : 'ยังไม่มีเวลาออกจาก ER',
                L.timed ? { p: pct(L.over4h, L.timed), label: `เกิน 4 ชม. ${pct(L.over4h, L.timed)}%` } : null) +
            ttd('Admit', data.admit) + ttd('Refer', data.refer);
        note.textContent = `มีเวลาออกจาก ER ${fmt(L.timed)} จาก ${fmt(L.n)} ราย (${pct(L.timed, L.n)}%) · ไม่นับงานบริการ (ทำแผล / ฉีดยา) · Admit / Refer นับถึงเวลาลงทะเบียน Admit / ส่งต่อ`;
    }

    /* คลิกการ์ด/ตัวเลข → รายชื่อผู้ป่วย (api/er_visit_list.php · ต้อง login · เงื่อนไขเดียวกับตัวเลขบนการ์ด) */
    const DRILL_TITLES = {
        all: 'ผู้มารับบริการ ER ทั้งหมด', ems: 'EMS นำส่ง', refer_in: 'Refer in — รับส่งต่อจากสถานพยาบาลอื่น',
        l12: 'วิกฤต / ฉุกเฉิน (ระดับ 1–2)',
        pt_emergency: 'ผู้ป่วยฉุกเฉิน', pt_accident: 'ผู้ป่วยอุบัติเหตุ', pt_general: 'ผู้ป่วยตรวจโรคทั่วไป', pt_service: 'ผู้ป่วยรับบริการอื่น',
        shift_night: 'เวรดึก (00:00–07:59 น.)', shift_morning: 'เวรเช้า (08:00–15:59 น.)', shift_evening: 'เวรบ่าย (16:00–23:59 น.)',
        home: 'กลับบ้าน', gone: 'ไม่รอตรวจ / หนีกลับ / ปฏิเสธการรักษา', no_dch: 'ยังไม่ลงผลจำหน่าย', other_dch: 'ผลจำหน่ายอื่น ๆ',
        admit: 'Admit', refer: 'Refer out — ส่งต่อไปสถานพยาบาลอื่น',
        refer_all: 'Refer ทั้งหมด (รับเข้า + ส่งออก)'
    };
    // ตัวเลือกคอลัมน์ (bbh_drill.js) แบบเดียวกับหน้า OPD: nowrap = บรรทัดเดียว · max = ยาวเกินตัด … (ชี้/แตะดูเต็ม) · display = ย่อเฉพาะตอนแสดง
    const NW = { nowrap: true };
    const shortHosp = v => String(v).replace(/โรงพยาบาลส่งเสริมสุขภาพตำบล/g, 'รพ.สต.').replace(/โรงพยาบาล/g, 'รพ.');
    const COLS = [
        ['enter_at', 'เข้า ER', NW], ['hn', 'HN', NW], ['name', 'ชื่อ-สกุล', { max: '12em' }], ['age', 'อายุ', { nowrap: true, display: v => v + ' ปี' }],
        ['lvl', 'ระดับ', NW], ['pt', 'ประเภท', NW], ['arrival', 'วิธีมา', { max: '8em' }],
        // การวินิจฉัยบรรทัดเดียว: "รหัส ชื่อไทย" · ชื่ออังกฤษอยู่ใน tooltip / แตะดู (ค่าเต็ม "… / English")
        ['dx', 'การวินิจฉัย', { max: '12em', display: v => v.split(' / ')[0] }],
        ['d2d', 'Door-to-Doctor', { nowrap: true, display: v => v + ' น.' }], ['dch', 'ผลจำหน่าย', NW]
    ];
    // คอลัมน์ที่ค่าเหมือนกันทั้งรายการ → แสดงข้อมูลที่บอกมากกว่าแทน [คอลัมน์ที่ถูกแทน, คอลัมน์ใหม่...]
    //   Admit / Refer out: ผลจำหน่าย → ปลายทาง · Refer in: วิธีมา → รพ.ต้นทาง (ใบ referin)
    //   Refer รวม: วิธีมา → สัญลักษณ์ in/out + รพ.ต้นทาง/ปลายทาง (คงผลจำหน่ายไว้ เห็นว่าเคสรับเข้าจบอย่างไร · ไม่มี Door-to-Doctor ให้พอดีจอ)
    const DEST_COL = {
        admit:    ['dch', ['admit_ward', 'Admit ไปที่', { max: '13em' }]],
        refer:    ['dch', ['refer_to', 'ส่งต่อไปที่', { display: shortHosp, max: '16em' }]],
        refer_in: ['arrival', ['refer_from', 'รับจาก', { display: shortHosp, max: '16em' }]],
        refer_all: ['arrival', ['ref_dir', 'In / Out', NW], ['ref_place', 'รับจาก / ส่งไป', { display: shortHosp, max: '10em' }]]
    };
    const withDest = (list, type) => DEST_COL[type] ? list.flatMap(c => c[0] === DEST_COL[type][0] ? DEST_COL[type].slice(1) : [c]) : list;
    // Export: ค่าเต็ม หน่วยอยู่ที่หัวคอลัมน์ แยกรหัส/ชื่อโรค
    const EXPORT_COLS = [
        ['enter_at', 'เข้า ER'], ['hn', 'HN'], ['name', 'ชื่อ-สกุล'], ['age', 'อายุ (ปี)'], ['lvl', 'ระดับ'], ['pt', 'ประเภท'],
        ['arrival', 'วิธีมา'], ['pdx', 'ICD-10'], ['diag_th', 'ชื่อโรค (ไทย)'], ['diag_en', 'ชื่อโรค (อังกฤษ)'],
        ['d2d', 'Door-to-Doctor (นาที)'], ['dch', 'ผลจำหน่าย']
    ];
    const esc = bbhDrill.esc;

    // ป้ายระดับ: จุดสีตามป้ายจำแนกผู้ป่วยของ รพ. (.er-tri-* ใน er_theme.css)
    function drillCell(r, k) {
        if (k === 'lvl') {
            const m = /^ระดับ (\d)$/.exec(r.lvl);
            const n = m ? m[1] : r.lvl === 'Black' ? '6' : '0';
            return `<span class="er-lvl er-tri-${n}">${esc(r.lvl)}</span>`;
        }
        // ทิศทาง Refer: สัญลักษณ์ (รูปต่างกัน ไม่พึ่งสีอย่างเดียว) + ข้อความสำหรับโปรแกรมอ่านหน้าจอ / tooltip
        if (k === 'ref_dir' && r.ref_dir) {
            const sym = {
                in:  '<span class="er-ref er-ref-in" title="Refer in: รับเข้า"><i class="fa-solid fa-arrow-right-to-bracket" aria-hidden="true"></i><span class="visually-hidden">Refer in</span></span>',
                out: '<span class="er-ref er-ref-out" title="Refer out: ส่งออก"><i class="fa-solid fa-truck-medical" aria-hidden="true"></i><span class="visually-hidden">Refer out</span></span>'
            };
            return r.ref_dir.split(' + ').map(d => sym[d.replace('Refer ', '')] || '').join(' ');
        }
        return undefined;   // ค่าปกติ (bbh_drill.js จัดการตามตัวเลือกคอลัมน์)
    }

    const DIR_LABEL = { in: 'Refer in', out: 'Refer out', 'in+out': 'Refer in + Refer out' };
    const withDx = r => ({
        ...r,
        // Refer รวม: ทิศทางเป็นข้อความ (กรอง/ค้นหา/Export) · สถานที่ตามทิศทาง
        ref_dir: r.ref_dir ? DIR_LABEL[r.ref_dir] : null,
        ref_place: r.ref_dir === 'in' ? r.refer_from : r.ref_dir === 'out' ? r.refer_to
            : r.ref_dir ? `รับจาก ${r.refer_from || '–'} · ส่งต่อ ${r.refer_to || '–'}` : null,
        dx: r.pdx ? [`${r.pdx} ${r.diag_th || r.diag_en || ''}`.trim(), r.diag_th && r.diag_en && r.diag_th !== r.diag_en ? r.diag_en : '']
            .filter(Boolean).join(' / ') : null
    });
    function openDrill(type) {
        if (!DRILL_TITLES[type]) return;
        const date = input.value || today();
        bbhDrill.open({
            title: DRILL_TITLES[type],
            sub: 'วันที่ ' + thai(date),
            url: '../api/er_visit_list.php?' + new URLSearchParams({ date, type }),
            cols: withDest(COLS, type).filter(c => !(type === 'refer_all' && c[0] === 'd2d')),
            exportCols: EXPORT_COLS.concat(DEST_COL[type] ? DEST_COL[type].slice(1).map(c => c.slice(0, 2)) : []),
            mapRows: data => data.rows.map(withDx),
            placeholder: 'ค้นหา HN / ชื่อ / การวินิจฉัย',
            searchKeys: ['hn', 'name', 'dx', 'arrival', 'dch', 'admit_ward', 'refer_to', 'refer_from', 'ref_dir'],
            filters: withDest([['lvl', 'ระดับ'], ['pt', 'ประเภท'], ['arrival', 'วิธีมา'], ['dch', 'ผลจำหน่าย']], type),
            cell: drillCell,
            file: `ER_${type}_${date}.csv`,
            summary: rows => (type === 'refer_all' ? `in ${fmt(rows.filter(r => /Refer in/.test(r.ref_dir || '')).length)} · out ${fmt(rows.filter(r => /Refer out/.test(r.ref_dir || '')).length)} · ` : '') + `${fmt(rows.length)} ครั้ง (${fmt(new Set(rows.map(r => r.hn)).size)} คน)`
        });
    }
    bbhDrill.bind($('er-summary'), openDrill);

    input.addEventListener('change', load);
    buttons.forEach(b => b.addEventListener('click', () => { input.value = daysAgo(Number(b.dataset.erDay)); load(); }));
    bbhPoll(() => { if ((input.value || today()) === today()) load(); }, 60000);
    load();
})();
