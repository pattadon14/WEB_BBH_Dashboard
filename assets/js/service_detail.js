/*
 * Popup รายละเอียดบริการ เมื่อคลิกรายการในการ์ด "สถิติการใช้บริการอื่นๆ วันนี้" (index.php)
 * ข้อมูลจาก api/service_detail.php — สรุปแสดงทุกคน, รายชื่อผู้ป่วยเฉพาะผู้ที่ login Provider ID
 * ใช้สไตล์หน้าต่างรายชื่อเดียวกับหน้า OPD (.opd-drill-* ใน opd_tabs.css)
 */
(function () {

    const dlg = document.getElementById('svc-detail');
    const box = document.getElementById('service-container');
    if (!dlg || !box) return;

    const $ = id => document.getElementById(id);
    const esc = v => String(v ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const fmt = n => Number(n).toLocaleString('th-TH');
    const PAGE = 50;
    const BY_LABEL = { item: 'แยกตามรายการ', dep: 'แยกตามแผนกที่ลงทะเบียน', pttype: 'แยกตามสิทธิ์' };
    // ตัวกรองรายชื่อ (แสดงเฉพาะที่มีค่ามากกว่า 1 แบบ) — item: 1 รายมีได้หลายรายการ กรองแบบ "มีรายการนี้"
    const FILTERS = [['kind', 'ประเภทผู้ป่วย'], ['shift', 'ช่วงเวลา'], ['item', 'รายการ'], ['dep', 'แผนก / วอร์ด'], ['pttype', 'สิทธิ์']];
    const SHIFTS = ['ในเวลาราชการ (08:00–15:59)', 'นอกเวลา (16:00–23:59)', 'นอกเวลา (00:00–07:59)'];
    const shiftOf = t => {
        if (!t) return '';
        const h = Number(t.slice(0, 2));
        return h >= 8 && h < 16 ? SHIFTS[0] : h >= 16 ? SHIFTS[1] : SHIFTS[2];
    };

    // วันที่ดูข้อมูล: ค่าเริ่มต้นวันนี้ (เวลาเครื่องผู้ใช้ = เวลาไทย) เลือกย้อนหลังได้ ไม่เกินวันนี้
    const iso = d => d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    const today = () => iso(new Date());
    const daysAgo = n => { const d = new Date(); d.setDate(d.getDate() - n); return iso(d); };
    const dateInput = $('svc-detail-date');

    let state = { rows: [], cols: [], file: '', page: 1 };
    let current = { svc: '', icon: '' };   // บริการที่เปิดอยู่ (เปลี่ยนวันที่แล้วโหลดบริการเดิม)
    let seq = 0;   // กันคำตอบของบริการก่อนหน้ามาทับ เมื่อกดเปลี่ยนเร็ว ๆ
    let chart = null;

    // รายการในการ์ดเป็นปุ่ม (คีย์บอร์ดใช้ได้) — key = id ของตัวเลข เช่น xray_total → xray
    box.querySelectorAll('.service-item').forEach(el => {
        el.setAttribute('role', 'button');
        el.setAttribute('tabindex', '0');
        el.setAttribute('title', 'คลิกเพื่อดูรายละเอียด');
    });
    const trigger = e => {
        const el = e.target.closest('.service-item');
        if (!el) return;
        if (e.type === 'keydown') {
            if (e.key !== 'Enter' && e.key !== ' ') return;
            e.preventDefault();
        }
        const total = el.querySelector('.service-total');
        // เปิดจากการ์ด = วันนี้เสมอ (การ์ดเป็นยอดวันนี้)
        open(total.id.replace(/_total$/, ''), el.querySelector('.service-name i')?.className || '', today());
    };
    box.addEventListener('click', trigger);
    box.addEventListener('keydown', trigger);

    $('svc-detail-close').addEventListener('click', () => dlg.close());
    dlg.addEventListener('click', e => { if (e.target === dlg) dlg.close(); });
    const refilter = () => { state.page = 1; renderTable(); };
    $('svc-detail-q').addEventListener('input', refilter);
    $('svc-detail-filters').addEventListener('change', refilter);
    $('svc-detail-pager').addEventListener('click', e => {
        const b = e.target.closest('button[data-page]');
        if (!b || b.disabled) return;
        state.page = Number(b.dataset.page);
        renderTable();
    });
    $('svc-detail-export').addEventListener('click', exportCsv);
    // เปลี่ยนวันที่: ปุ่มลัด หรือปฏิทิน → โหลดบริการเดิมของวันนั้น
    dlg.querySelectorAll('[data-svc-day]').forEach(b => b.addEventListener('click', () => {
        if (current.svc) open(current.svc, current.icon, daysAgo(Number(b.dataset.svcDay)));
    }));
    dateInput.addEventListener('click', () => { try { dateInput.showPicker(); } catch (e) { /* เบราว์เซอร์เก่า: ปฏิทินปกติ */ } });
    dateInput.addEventListener('change', () => {
        if (!dateInput.value || !current.svc) return;
        open(current.svc, current.icon, dateInput.value > today() ? today() : dateInput.value);
    });
    // คลิกแถบ "แยกตาม…" = ค้นหารายชื่อด้วยค่านั้น
    const pickGroup = e => {
        const g = e.target.closest('[data-group]');
        if (!g || !state.rows.length) return;
        if (e.type === 'keydown') {
            if (e.key !== 'Enter' && e.key !== ' ') return;
            e.preventDefault();
        }
        // แยกตามแผนก/สิทธิ์ = เลือกตัวกรองนั้นตรง ๆ · แยกตามรายการ (1 รายมีหลายรายการ) = ค้นหาข้อความ
        const sel = $('svc-detail-filters').querySelector(`select[data-filter="${state.by}"]`);
        const input = sel || $('svc-detail-q');
        input.value = input.value === g.dataset.group ? '' : g.dataset.group;
        refilter();
        $('svc-detail-list').scrollIntoView({ behavior: 'smooth', block: 'start' });
    };
    $('svc-detail-summary').addEventListener('click', pickGroup);
    $('svc-detail-summary').addEventListener('keydown', pickGroup);

    async function open(svc, iconClass, date) {

        const my = ++seq;
        current = { svc, icon: iconClass };
        dateInput.max = today();
        dateInput.value = date;
        $('svc-detail-date-text').textContent = new Date(date + 'T00:00:00')
            .toLocaleDateString('th-TH', { day: 'numeric', month: 'short', year: 'numeric' });
        dlg.querySelectorAll('[data-svc-day]').forEach(b =>
            b.setAttribute('aria-pressed', String(daysAgo(Number(b.dataset.svcDay)) === date)));
        $('svc-detail-title').innerHTML = `<i class="${esc(iconClass)}" aria-hidden="true"></i> กำลังโหลด…`;
        $('svc-detail-sub').textContent = '';
        $('svc-detail-summary').innerHTML = '<p class="opd-drill-msg"><i class="fa-solid fa-spinner fa-spin"></i> กำลังโหลดข้อมูล…</p>';
        $('svc-detail-list').hidden = true;
        $('svc-detail-q').value = '';
        if (!dlg.open) dlg.showModal();

        try {
            const res = await fetch('api/service_detail.php?svc=' + encodeURIComponent(svc) + '&date=' + encodeURIComponent(date));
            const data = await res.json();
            if (my !== seq) return;
            if (!res.ok || data.error) throw new Error(data.error || 'โหลดข้อมูลไม่สำเร็จ');

            const thDate = new Date(data.date + 'T00:00:00').toLocaleDateString('th-TH', { day: 'numeric', month: 'long', year: 'numeric' });
            const now = new Date().toLocaleTimeString('th-TH', { hour: '2-digit', minute: '2-digit' });
            $('svc-detail-title').innerHTML = `<i class="${esc(iconClass)}" aria-hidden="true"></i> ${esc(data.label)}`;
            $('svc-detail-sub').textContent = `วันที่ ${thDate}` + (data.date === today() ? ` · ข้อมูล ณ ${now} น.` : '');
            renderSummary(data);
            renderList(data);

        } catch (e) {
            if (my !== seq) return;
            $('svc-detail-title').textContent = 'รายละเอียดบริการ';
            $('svc-detail-summary').innerHTML = `<p class="opd-drill-msg opd-drill-error"><i class="fa-solid fa-triangle-exclamation"></i> ${esc(e.message)}</p>`;
        }
    }

    function renderSummary(d) {

        const diff = d.total - d.prev;
        const pct = d.prev ? Math.round(diff / d.prev * 100) : null;
        const trend = diff === 0 ? '<span class="svc-trend">เท่าเดิม</span>'
            : `<span class="svc-trend"><i class="fa-solid fa-arrow-${diff > 0 ? 'up' : 'down'}" aria-hidden="true"></i>
               ${diff > 0 ? '+' : ''}${fmt(diff)}${pct === null ? '' : ` (${pct > 0 ? '+' : ''}${pct}%)`}</span>`;
        const peak = d.hours.reduce((a, b) => (b.n > a.n ? b : a), { h: null, n: 0 });
        const hh = h => String(h).padStart(2, '0');

        const groups = d.groups.slice(0, 10);
        const rest = d.groups.slice(10);
        const max = groups.length ? groups[0].n : 1;
        const canFilter = Array.isArray(d.rows) && d.rows.length > 0;
        const isToday = d.date === today();

        $('svc-detail-summary').innerHTML = `
            <div class="svc-kpis">
                <div class="svc-kpi"><span>${isToday ? 'ยอดวันนี้' : 'ยอดวันที่เลือก'}</span><strong>${fmt(d.total)}</strong> ราย</div>
                <div class="svc-kpi"><span>สัปดาห์ก่อน (วันเดียวกัน${isToday ? ' ถึงเวลาเดียวกัน' : ''})</span><strong>${fmt(d.prev)}</strong> ราย ${trend}</div>
                <div class="svc-kpi"><span>ช่วงเวลาที่มากที่สุด</span>${peak.h === null ? '<strong>–</strong>'
                    : `<strong>${hh(peak.h)}:00–${hh(peak.h)}:59</strong> ${fmt(peak.n)} ราย`}</div>
            </div>
            <div class="svc-panels">
                <section class="svc-panel">
                    <h4>จำนวนรายชั่วโมง</h4>
                    <div id="svc-hour-chart" class="svc-hour-chart" role="img" aria-label="กราฟจำนวนรายชั่วโมง"></div>
                </section>
                <section class="svc-panel">
                    <h4>${BY_LABEL[d.by]}${canFilter ? ' <small>คลิกเพื่อกรองรายชื่อ</small>' : ''}</h4>
                    ${groups.length ? `<ol class="svc-groups">${groups.map(g => `
                        <li${canFilter ? ` data-group="${esc(g.name)}" role="button" tabindex="0"` : ''}>
                            <span class="svc-group-name" title="${esc(g.name)}">${esc(g.name)}</span>
                            <span class="svc-group-bar"><i style="width:${Math.max(2, g.n / max * 100)}%"></i></span>
                            <span class="svc-group-n">${fmt(g.n)}</span>
                        </li>`).join('')}</ol>
                        ${rest.length ? `<p class="svc-groups-more">และอีก ${fmt(rest.length)} รายการ (${fmt(rest.reduce((s, g) => s + g.n, 0))} ครั้ง)</p>` : ''}
                        ${d.by === 'item' ? '<p class="svc-groups-more">1 รายอาจได้รับหลายรายการ ผลรวมจึงอาจมากกว่ายอดวันนี้</p>' : ''}`
                    : '<p class="opd-drill-msg">ไม่มีข้อมูล</p>'}
                </section>
            </div>`;

        renderChart(d.hours);
    }

    function renderChart(hours) {

        if (chart) { chart.destroy(); chart = null; }
        const el = $('svc-hour-chart');
        if (!hours.length || !window.Highcharts) {
            el.innerHTML = '<p class="opd-drill-msg">ไม่มีข้อมูล</p>';
            return;
        }
        // แกนชั่วโมงต่อเนื่องจากชั่วโมงแรกถึงชั่วโมงสุดท้ายที่มีข้อมูล (ชั่วโมงที่ไม่มี = 0)
        const byH = new Map(hours.map(x => [x.h, x.n]));
        const from = hours[0].h, to = hours[hours.length - 1].h;
        const cats = [], vals = [];
        for (let h = from; h <= to; h++) { cats.push(String(h).padStart(2, '0')); vals.push(byH.get(h) || 0); }

        chart = Highcharts.chart(el, {
            chart: { type: 'column', height: 230, spacing: [8, 4, 4, 4], style: { fontFamily: 'inherit' } },
            title: { text: null },
            credits: { enabled: false },
            legend: { enabled: false },
            xAxis: { categories: cats, gridLineWidth: 0, lineColor: '#d5ddd8', title: { text: 'ชั่วโมง (น.)' }, labels: { style: { fontSize: '13px' } } },
            yAxis: { title: { text: null }, allowDecimals: false, gridLineColor: '#eef2ef' },
            tooltip: { formatter() { return `<b>${this.category}:00–${this.category}:59 น.</b><br>${fmt(this.y)} ราย`; } },
            plotOptions: { column: { color: '#198754', borderRadius: 4, borderWidth: 0, pointPadding: 0.08, groupPadding: 0.08 } },
            series: [{ name: 'จำนวน', data: vals }]
        });
    }

    function renderList(d) {

        const list = $('svc-detail-list');
        list.hidden = false;
        const tools = list.querySelector('.opd-drill-tools');

        if (!Array.isArray(d.rows)) {
            tools.hidden = true;
            $('svc-detail-filters').innerHTML = '';
            $('svc-detail-pager').innerHTML = '';
            $('svc-detail-body').innerHTML = `
                <div class="opd-drill-msg">
                    <p><i class="fa-solid fa-lock"></i> รายชื่อผู้ป่วยแสดงเฉพาะผู้ที่เข้าสู่ระบบ Provider ID</p>
                    <a class="svc-login" href="auth/provider_login.php"><i class="fa-solid fa-right-to-bracket"></i> เข้าสู่ระบบ</a>
                </div>`;
            state = { rows: [], cols: [], file: '', page: 1 };
            return;
        }

        tools.hidden = false;
        const cols = [['time', 'เวลา'], ['hn', 'HN'], ['name', 'ชื่อ-สกุล'], ['kind', 'ประเภท'], ['dep', 'แผนก / วอร์ด'], ['pttype', 'สิทธิ์']];
        if (d.rows.some(r => r.item)) cols.push(['item', 'รายการ']);
        // ช่วงเวลา (จากเวลาที่รับบริการ) + รายการแยกเป็นชุด สำหรับตัวกรอง
        const rows = d.rows.map(r => ({ ...r, shift: shiftOf(r.time), items: r.item ? r.item.split(' · ') : [] }));
        state = { rows, cols, by: d.by, file: `${d.svc}_${d.date}.csv`, page: 1 };
        $('svc-detail-export').disabled = !rows.length;

        $('svc-detail-filters').innerHTML = FILTERS.map(([k, label]) => {
            const counts = new Map();
            rows.forEach(r => (k === 'item' ? r.items : [r[k]]).forEach(v => { if (v) counts.set(v, (counts.get(v) || 0) + 1); }));
            if (counts.size < 2) return '';
            const order = k === 'shift' ? [...counts].sort((a, b) => SHIFTS.indexOf(a[0]) - SHIFTS.indexOf(b[0]))
                : [...counts].sort((a, b) => b[1] - a[1]);
            const opts = order.map(([v, n]) => `<option value="${esc(v)}">${esc(v)} (${fmt(n)})</option>`).join('');
            return `<label class="opd-drill-filter"><span>${label}</span>
                <select data-filter="${k}"><option value="">ทั้งหมด</option>${opts}</select></label>`;
        }).join('');

        renderTable();
    }

    function filtered() {
        const q = $('svc-detail-q').value.trim().toLowerCase();
        const picks = [...$('svc-detail-filters').querySelectorAll('select')].filter(s => s.value).map(s => [s.dataset.filter, s.value]);
        return state.rows.filter(r =>
            picks.every(([k, v]) => (k === 'item' ? r.items.includes(v) : r[k] === v)) &&
            (!q || state.cols.some(([k]) => String(r[k] ?? '').toLowerCase().includes(q))));
    }

    function renderTable() {

        if (!state.cols.length) return;
        const rows = filtered();
        const all = state.rows.length;
        $('svc-detail-count').textContent = rows.length !== all ? `พบ ${fmt(rows.length)} จาก ${fmt(all)} รายการ` : '';
        const pager = $('svc-detail-pager');
        const body = $('svc-detail-body');

        if (!rows.length) {
            pager.innerHTML = '';
            body.innerHTML = `<p class="opd-drill-msg">${all ? 'ไม่พบรายการตามที่ค้นหา/กรอง' : 'ไม่มีผู้รับบริการในวันที่เลือก'}</p>`;
            return;
        }

        const last = Math.ceil(rows.length / PAGE);
        const page = state.page = Math.min(Math.max(state.page, 1), last);
        const start = (page - 1) * PAGE;
        const cell = v => v === null || v === '' ? '<span class="text-muted">–</span>' : esc(v);

        body.innerHTML = `
            <table class="opd-drill-table">
                <thead><tr><th>#</th>${state.cols.map(([, t]) => `<th>${t}</th>`).join('')}</tr></thead>
                <tbody>${rows.slice(start, start + PAGE).map((r, i) =>
                    `<tr><td>${start + i + 1}</td>${state.cols.map(([k]) => `<td>${cell(r[k])}</td>`).join('')}</tr>`).join('')}</tbody>
            </table>`;

        const btn = (p, label, aria, disabled, current) =>
            `<button type="button" data-page="${p}" aria-label="${aria}"${disabled ? ' disabled' : ''}${current ? ' aria-current="page" class="is-current"' : ''}>${label}</button>`;
        const pages = [...new Set([1, last, page - 1, page, page + 1])].filter(p => p >= 1 && p <= last).sort((a, b) => a - b);
        pager.innerHTML = `
            <span class="opd-drill-range">แสดง ${fmt(start + 1)}–${fmt(Math.min(start + PAGE, rows.length))} จาก ${fmt(rows.length)} รายการ</span>
            ${last > 1 ? `<div class="opd-drill-pages">
                ${btn(page - 1, '<i class="fa-solid fa-chevron-left"></i>', 'หน้าก่อน', page === 1)}
                ${pages.map((p, i) => (i && p - pages[i - 1] > 1 ? '<span class="gap">…</span>' : '') + btn(p, p, 'หน้า ' + p, false, p === page)).join('')}
                ${btn(page + 1, '<i class="fa-solid fa-chevron-right"></i>', 'หน้าถัดไป', page === last)}
            </div>` : ''}`;
    }

    function exportCsv() {
        const cell = v => '"' + String(v ?? '').replace(/"/g, '""') + '"';
        const lines = [state.cols.map(([, t]) => cell(t)).join(',')]
            .concat(filtered().map(r => state.cols.map(([k]) => cell(r[k])).join(',')));
        const url = URL.createObjectURL(new Blob(['﻿' + lines.join('\r\n')], { type: 'text/csv;charset=utf-8;' }));
        const a = Object.assign(document.createElement('a'), { href: url, download: state.file });
        document.body.appendChild(a);
        a.click();
        a.remove();
        URL.revokeObjectURL(url);
    }
})();
