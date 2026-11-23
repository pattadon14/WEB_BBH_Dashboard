/*
 * หัวข้อ "คุณภาพบริการวันนี้" หน้าแรก (index.php) — ไม่มีข้อมูลรายคน ไม่ต้อง login
 * ใช้ API ชุดเดียวกับหน้ารายละเอียด ตัวเลขจึงตรงกับหน้า OPD (แท็บเวลารอ) และหน้า ER (ภาพรวม/Door-to-Doctor/สถานะตอนนี้)
 *   OPD: api/opd_service_kpi.php?d1=วันนี้&d2=วันนี้ (เป้าหมายนาทีมาจาก API — แก้ที่ SK_STEPS / SK_TARGET_*)
 *   ER : api/er_los.php (now), api/er_d2d.php, api/er_today_summary.php
 * สถานะทุกช่องมีทั้งไอคอนและข้อความ ไม่สื่อด้วยสีอย่างเดียว
 */
(function () {
    'use strict';

    const $ = id => document.getElementById(id);
    const fmt = n => Number(n).toLocaleString('th-TH');
    const iso = d => d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    const hm = m => `${Math.floor(m / 60)}:${String(m % 60).padStart(2, '0')}`;
    const STATE = {
        ok:   ['fa-circle-check', 'ทันเป้า'],
        bad:  ['fa-circle-exclamation', 'เกินเป้า'],
        warn: ['fa-triangle-exclamation', 'เฝ้าระวัง']
    };

    // ช่องตัวเลข: label, value (ตัวเลขหรือ null), unit, sub (บรรทัดล่าง), state (ok|bad|warn|null), stateText ทับข้อความสถานะ
    const tile = t => `
        <div class="q-tile${t.state ? ' is-' + t.state : ''}">
            <span class="q-label">${t.label}</span>
            <span class="q-value">${t.value === null || t.value === undefined ? '—' : `<b>${t.value}</b>`}${t.unit && t.value !== null ? ` <small>${t.unit}</small>` : ''}</span>
            ${t.state ? `<span class="q-state"><i class="fa-solid ${STATE[t.state][0]}" aria-hidden="true"></i> ${t.stateText || STATE[t.state][1]}</span>` : ''}
            ${t.sub ? `<span class="q-sub">${t.sub}</span>` : ''}
        </div>`;

    async function getJson(url) {
        const res = await fetch(url, { cache: 'no-store' });
        const data = await res.json();
        if (!res.ok || data.error) throw new Error(data.error || 'โหลดข้อมูลไม่สำเร็จ');
        return data;
    }

    async function loadOpd() {
        const box = $('q-opd');
        try {
            const day = iso(new Date());
            const d = await getJson(`api/opd_service_kpi.php?d1=${day}&d2=${day}`);
            const step = (s, label) => ({
                label: label || s.label,
                value: s.avg === null ? null : fmt(s.avg), unit: 'นาที',
                state: s.avg === null ? null : (s.avg <= s.target ? 'ok' : 'bad'),
                sub: `เป้า ≤ ${s.target} นาที` + (s.n ? ` · ทันเป้า ${s.ok_pct}% จาก ${fmt(s.n)} ราย` : '')
            });
            const [scr, toDr, d2d, rx] = d.steps;
            const t = d.targets || {};
            box.innerHTML = [
                step(d2d, 'Door-to-Doctor'),
                step(scr),
                step(toDr),
                step(rx),
                {
                    label: 'เวลารวมต่อราย', value: d.kpi.total === null ? null : fmt(d.kpi.total), unit: 'นาที',
                    state: d.kpi.total === null ? null : (d.kpi.total <= t.total ? 'ok' : 'bad'),
                    sub: `เป้า ≤ ${t.total} นาที` + (d.kpi.done === null ? '' : ` · จบใน 2 ชม. ${d.kpi.done}% (เป้า ≥ ${t.done}%)`)
                }
            ].map(tile).join('');
        } catch (e) {
            box.innerHTML = '<p class="q-msg">โหลดข้อมูลไม่สำเร็จ</p>';
        }
    }

    async function loadEr() {
        const box = $('q-er');
        try {
            const [los, d2d, sum] = await Promise.all([
                getJson('api/er_los.php'), getJson('api/er_d2d.php'), getJson('api/er_today_summary.php')
            ]);
            const now = los.now || {};
            $('q-er-at').textContent = now.at ? `ณ ${now.at} น.` : '';
            // Door-to-Doctor ทันเป้า: รวมระดับ 1–5 (ไม่รวม Black) ตัวหาร = รายที่มีเวลาพบแพทย์ เหมือนหน้า ER
            const within = d2d.items.reduce((s, i) => s + i.within, 0);
            const timed = d2d.items.reduce((s, i) => s + i.timed, 0);
            const urgent = d2d.items.filter(i => i.level <= 2);
            const uw = urgent.reduce((s, i) => s + i.within, 0), ut = urgent.reduce((s, i) => s + i.timed, 0);
            const p = timed ? Math.round(within * 100 / timed) : null;
            box.innerHTML = [
                { label: 'อยู่ใน ER', value: fmt(now.in_er ?? 0), unit: 'ราย', sub: `ระดับ 1–2: ${fmt(now.l12 ?? 0)} ราย` },
                { label: 'ยังไม่พบแพทย์', value: fmt(now.waiting ?? 0), unit: 'ราย', state: now.waiting ? 'warn' : null, stateText: 'รอพบแพทย์' },
                {
                    label: 'อยู่เกิน 4 ชม.', value: fmt(now.over4h ?? 0), unit: 'ราย', state: now.over4h ? 'bad' : null, stateText: 'ควรติดตาม',
                    sub: now.longest == null ? '' : `นานสุด ${hm(now.longest)} ชม.`
                },
                {
                    // ไม่มีเป้ารวม (หน้า ER ตั้งเป้าแยกตามระดับ) จึงไม่ใส่สถานะ
                    label: 'Door-to-Doctor ทันเป้าตามระดับ', value: p, unit: '%',
                    sub: timed ? `${fmt(within)} จาก ${fmt(timed)} ราย` + (ut ? ` · ระดับ 1–2: ${fmt(uw)}/${fmt(ut)}` : '') : 'ยังไม่มีเวลาพบแพทย์'
                },
                { label: 'Admit / Refer จาก ER วันนี้', value: `${fmt(sum.admit)} / ${fmt(sum.refer)}`, unit: 'ราย', sub: `จากผู้มารับบริการ ${fmt(sum.total)} ราย` }
            ].map(tile).join('');
        } catch (e) {
            box.innerHTML = '<p class="q-msg">โหลดข้อมูลไม่สำเร็จ</p>';
        }
    }

    function load() { loadOpd(); loadEr(); }
    load();
    bbhPoll(load, 60000);   // API เก็บผลไว้ 60 วินาที
})();
