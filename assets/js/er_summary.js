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

        grid.innerHTML = data.items.map(it => {
            const p = pct(it.within, it.timed);
            const avg = it.avg === null ? null : it.avg.toLocaleString(undefined, { maximumFractionDigits: 1 });
            const late = it.avg !== null && it.avg > it.target;
            return row({
                cls: 'er-tri-' + it.level, drill: it.n ? 'lvl:' + it.level : null, icon: `<span class="er-ico er-lvl-chip" aria-hidden="true">${it.level}</span>`,
                key: `ระดับ ${it.level}`, sub: `เป้า ${it.target_label}`,
                avg, late,
                meter: it.timed ? { p, label: `ทันเป้า ${p}%`, cls: late ? 'is-bad' : 'is-good' } : null,
                line: it.timed ? `ทันเป้า ${fmt(it.within)} จาก ${fmt(it.timed)} ราย (${p}%)` : (it.n ? 'ยังไม่มีเวลาพบแพทย์' : 'ไม่มีผู้ป่วย')
            });
        }).join('');
        const n = data.items.reduce((s, it) => s + it.n, 0), t = data.items.reduce((s, it) => s + it.timed, 0);
        note.textContent = `มีเวลาพบแพทย์ ${fmt(t)} จาก ${fmt(n)} ราย (${pct(t, n)}%) ไม่รวม Black. ใช้เวลาที่เร็วที่สุดของการกดเริ่มตรวจ สั่ง Lab, X-ray, สั่งยา หรือบันทึกรายงานแพทย์ใน HPI`;
    }

    /* แถวตัวชี้วัดเวลา (Door-to-Doctor / LOS): ชื่อ + เกณฑ์ | ค่าเฉลี่ย | แถบสัดส่วน + คำอธิบาย */
    const pct = (a, b) => b ? Math.round(a * 100 / b) : 0;
    // o.drill = กดดูรายชื่อ (แถวสร้างหลัง bbhDrill.bind จึงใส่ role/tabindex เอง — การคลิกจับที่ #er-summary อยู่แล้ว)
    const row = o => `<div class="er-row ${o.cls || ''}"${o.drill ? ` data-drill="${o.drill}" role="button" tabindex="0" title="คลิกเพื่อดูรายชื่อ"` : ''}>
            <div class="er-row-key">${o.icon || ''}<span>${o.key}</span><small>${o.sub}</small></div>
            <div class="er-row-num">${o.avg === null ? '<b>—</b>' : `<small>เฉลี่ย</small> <b>${o.avg}</b> <small>นาที</small>`}${o.late ? ' <span class="er-flag"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i> เกินเป้า</span>' : ''}</div>
            <div class="er-row-bar">${o.meter ? `<div class="er-meter ${o.meter.cls || ''}" role="img" aria-label="${o.meter.label}"><span style="width:${o.meter.p}%"></span></div>` : ''}<small>${o.line}</small></div>
        </div>`;

    /* สถานะ ER ตอนนี้ (เฉพาะวันนี้) + LOS / เข้า ER ถึง Admit / Refer ของวันที่เลือก */
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
        $('er-now-at').textContent = data.now ? `และสถานะตอนนี้ ณ ${data.now.at} น.` : '';
        if (data.error) { grid.innerHTML = ''; note.textContent = data.error; return; }

        if (data.now) {
            $('er-now-in').textContent = fmt(data.now.in_er);
            $('er-now-l12').textContent = fmt(data.now.l12);
            $('er-now-wait').textContent = fmt(data.now.waiting);
            $('er-now-over').textContent = fmt(data.now.over4h);
            $('er-now-long').textContent = data.now.longest === null ? '—'
                : `${Math.floor(data.now.longest / 60)}:${String(data.now.longest % 60).padStart(2, '0')}`;
        }

        const L = data.los, b = data.boarding_min;
        const ico = (c, i) => `<span class="er-ico" aria-hidden="true"><i class="fa-solid fa-${i}"></i></span>`;   // ไอคอนโทนแดงชุดเดียว
        const ttd = (name, x) => row({
            drill: x.n ? name.toLowerCase() : null,   // 'admit' / 'refer' = รายชื่อชุดเดียวกับการ์ดผลการจำหน่าย
            icon: name === 'Admit' ? ico('blue', 'bed-pulse') : ico('teal', 'truck-medical'),
            key: `เข้า ER ถึง ${name}`, sub: `เกณฑ์ ≤ ${b} นาที`, avg: x.avg === null ? null : fmt(x.avg),
            late: x.avg !== null && x.avg > b,
            meter: x.n ? { p: pct(x.over, x.n), label: `รอเกิน ${pct(x.over, x.n)}%`, cls: 'is-warn' } : null,
            line: x.n ? `รอเกิน ${b} นาที ${fmt(x.over)} จาก ${fmt(x.n)} ราย (${pct(x.over, x.n)}%)` : `ไม่มี${name}`
        });
        grid.innerHTML = row({
            icon: ico('amber', 'hourglass-half'),
            key: 'อยู่ใน ER ทั้งหมด', sub: L.median === null ? 'ยังไม่มีข้อมูล' : `มัธยฐาน ${fmt(L.median)} นาที`,
            avg: L.avg === null ? null : fmt(L.avg),
            meter: L.timed ? { p: pct(L.over4h, L.timed), label: `เกิน 4 ชม. ${pct(L.over4h, L.timed)}%`, cls: 'is-warn' } : null,
            line: L.timed ? `เกิน 4 ชม. ${fmt(L.over4h)} ราย, เกิน 6 ชม. ${fmt(L.over6h)} ราย` : 'ยังไม่มีเวลาออกจาก ER'
        }) + ttd('Admit', data.admit) + ttd('Refer', data.refer);
        note.textContent = `มีเวลาออกจาก ER ${fmt(L.timed)} จาก ${fmt(L.n)} ราย (${pct(L.timed, L.n)}%) ไม่นับงานบริการทำแผลและฉีดยา. Admit และ Refer นับถึงเวลาลงทะเบียน Admit หรือส่งต่อ`;
    }

    /* คลิกการ์ด/ตัวเลข → รายชื่อผู้ป่วย (api/er_visit_list.php · ต้อง login · เงื่อนไขเดียวกับตัวเลขบนการ์ด) */
    const DRILL_TITLES = {
        all: 'ผู้มารับบริการ ER ทั้งหมด', ems: 'EMS นำส่ง', refer_in: 'Refer in — รับส่งต่อจากสถานพยาบาลอื่น',
        l12: 'วิกฤต / ฉุกเฉิน (ระดับ 1–2)',
        pt_emergency: 'ผู้ป่วยฉุกเฉิน', pt_accident: 'ผู้ป่วยอุบัติเหตุ', pt_general: 'ผู้ป่วยตรวจโรคทั่วไป', pt_service: 'ผู้ป่วยรับบริการอื่น',
        shift_night: 'เวรดึก (00:00–07:59 น.)', shift_morning: 'เวรเช้า (08:00–15:59 น.)', shift_evening: 'เวรบ่าย (16:00–23:59 น.)',
        home: 'กลับบ้าน', gone: 'ไม่รอตรวจ / หนีกลับ / ปฏิเสธการรักษา', no_dch: 'ยังไม่ลงผลจำหน่าย', other_dch: 'ผลจำหน่ายอื่น ๆ',
        admit: 'Admit', refer: 'Refer out — ส่งต่อไปสถานพยาบาลอื่น',
        refer_all: 'Refer ทั้งหมด (รับเข้า + ส่งออก)',
        now_in: 'อยู่ใน ER ตอนนี้', now_l12: 'ระดับ 1–2 ที่อยู่ใน ER ตอนนี้',
        now_wait: 'อยู่ใน ER และยังไม่พบแพทย์', now_over: 'อยู่ใน ER เกิน 4 ชม.'
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
        // ปลายทาง/เหตุผล = ใบเดียวกับที่ api/er_refer.php นับ (refer_dest) — คลิกชื่อ รพ. แล้วรายชื่อตรงกับตัวเลข
        refer:    ['dch', ['refer_dest', 'ส่งต่อไปที่', { max: '16em' }], ['refer_reason', 'เหตุผล', { max: '12em' }]],
        refer_in: ['arrival', ['refer_src', 'รับจาก', { max: '16em' }]],
        // Admit แล้วส่งต่อจากหอผู้ป่วย: ผลจำหน่าย (Admit ทุกราย) → ward + ปลายทาง + เหตุผล + แบบจำหน่ายของ ipt
        ipd_refer: ['dch', ['admit_ward', 'Admit ที่', { max: '10em' }], ['ipd_dest', 'ส่งต่อไปที่', { max: '14em' }],
                    ['ipd_reason', 'เหตุผล', { max: '12em' }], ['ipd_transfer', 'การจำหน่าย', NW]],
        refer_all: ['arrival', ['ref_dir', 'In / Out', NW], ['ref_place', 'รับจาก / ส่งไป', { display: shortHosp, max: '10em' }]]
    };
    // ช่อง "ตอนนี้": ยังไม่มีผลจำหน่าย → แสดงเวลาที่อยู่ใน ER แล้ว (ชม.:นาที) แทน
    const STAY_COL = ['dch', ['stay', 'อยู่มาแล้ว', { nowrap: true, display: m => `${Math.floor(m / 60)}:${String(m % 60).padStart(2, '0')} ชม.` }]];
    ['now_in', 'now_l12', 'now_wait', 'now_over'].forEach(k => { DEST_COL[k] = STAY_COL; });
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
    /* รายชื่อผู้ป่วย 1 รายการ — ใช้ทั้งการ์ดรายวัน (date) และกราฟสถิติตามช่วงเวลา (d1, d2 → มีคอลัมน์วันที่)
     * o = { title, sub, type, v?, date? | d1?, d2?, keep?: row => bool (กรองต่อในหน้า เช่น ชื่อ รพ. ที่คลิก) } */
    const thaiShort = s => { const [y, m, d] = s.split('-').map(Number); return `${d}/${m}/${String(y + 543).slice(2)}`; };
    function openList(o) {
        const range = !o.date;
        const params = range ? { d1: o.d1, d2: o.d2, type: o.type } : { date: o.date, type: o.type };
        if (o.v !== undefined) params.v = o.v;
        const cols = withDest(COLS, o.type).filter(c => !(o.type === 'refer_all' && c[0] === 'd2d'));
        const dateCol = ['vstdate', 'วันที่', { nowrap: true, display: thaiShort }];
        bbhDrill.open({
            title: o.title,
            sub: o.sub,
            url: '../api/er_visit_list.php?' + new URLSearchParams(params),
            cols: range ? [dateCol, ...cols] : cols,
            exportCols: (range ? [['vstdate', 'วันที่']] : []).concat(EXPORT_COLS, DEST_COL[o.type] ? DEST_COL[o.type].slice(1).map(c => c.slice(0, 2)) : []),
            mapRows: data => data.rows.map(withDx).filter(o.keep || (() => true)),
            placeholder: 'ค้นหา HN / ชื่อ / การวินิจฉัย',
            searchKeys: ['hn', 'name', 'dx', 'arrival', 'dch', 'admit_ward', 'refer_to', 'refer_from', 'ref_dir', 'refer_dest', 'refer_src', 'ipd_dest'],
            filters: withDest([['lvl', 'ระดับ'], ['pt', 'ประเภท'], ['arrival', 'วิธีมา'], ['dch', 'ผลจำหน่าย']], o.type),
            cell: drillCell,
            file: `ER_${o.type}${o.v !== undefined ? '_' + o.v : ''}_${range ? o.d1 + '_' + o.d2 : o.date}.csv`,
            summary: rows => (o.type === 'refer_all' ? `in ${fmt(rows.filter(r => /Refer in/.test(r.ref_dir || '')).length)} · out ${fmt(rows.filter(r => /Refer out/.test(r.ref_dir || '')).length)} · ` : '') + `${fmt(rows.length)} ครั้ง (${fmt(new Set(rows.map(r => r.hn)).size)} คน)`
        });
    }
    window.erOpenList = openList;   // ใช้จากกราฟสถิติ (assets/js/ER_detail.js)

    // การ์ดรายวัน: data-drill="<type>" หรือ "lvl:<ระดับ>" (แถว Door-to-Doctor)
    function openDrill(key) {
        const date = input.value || today();
        const lv = /^lvl:(\d)$/.exec(key);
        if (lv) return openList({ title: `ผู้ป่วยระดับ ${lv[1]}`, sub: 'วันที่ ' + thai(date), type: 'lvl', v: lv[1], date });
        if (!DRILL_TITLES[key]) return;
        if (key.startsWith('now_')) {   // ไม่ขึ้นกับวันที่ (ช่องนี้แสดงเฉพาะตอนดูวันนี้)
            const t = new Date();
            return openList({ title: DRILL_TITLES[key], sub: `สถานะ ณ ${String(t.getHours()).padStart(2, '0')}:${String(t.getMinutes()).padStart(2, '0')} น.`, type: key, date: today() });
        }
        openList({ title: DRILL_TITLES[key], sub: 'วันที่ ' + thai(date), type: key, date });
    }
    bbhDrill.bind($('er-summary'), openDrill);

    input.addEventListener('change', load);
    buttons.forEach(b => b.addEventListener('click', () => { input.value = daysAgo(Number(b.dataset.erDay)); load(); }));
    bbhPoll(() => { if ((input.value || today()) === today()) load(); }, 60000);
    load();
})();
