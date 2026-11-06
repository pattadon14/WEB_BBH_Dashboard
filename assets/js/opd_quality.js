/* =========================================================
   OPD — เวลารอ & คุณภาพบริการ (แท็บที่ 3 ของ opd_detail)
   ข้อมูลจาก api/opd_service_kpi.php · โหลดเมื่อเปิดแท็บครั้งแรก
========================================================= */
(function () {
    const $ = id => document.getElementById(id);
    const d1 = $('oq-d1'), d2 = $('oq-d2');
    if (!d1) return;

    const iso = dt => dt.getFullYear() + '-' + String(dt.getMonth() + 1).padStart(2, '0') + '-' + String(dt.getDate()).padStart(2, '0');
    const today = iso(new Date());
    d1.value = d2.value = today;
    d1.max = d2.max = today;

    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const fmt = v => v === null || v === undefined ? '–' : Number(v).toLocaleString('th-TH');

    // ลูกศรเทียบช่วงก่อน · lowerBetter = เวลารอ (ลดลง = ดี)
    function delta(v, lowerBetter) {
        if (v === null || v === undefined) return '<span class="oq-d">ไม่มีข้อมูลช่วงก่อน</span>';
        const good = lowerBetter ? v < 0 : v > 0;
        const cls = v === 0 ? '' : (good ? 'up' : 'down');
        const arrow = v > 0 ? '▲' : (v < 0 ? '▼' : '•');
        return `<span class="oq-d ${cls}">${arrow} ${Math.abs(v)}% เทียบช่วงก่อน</span>`;
    }

    function kpis(k, t) {
        const card = (label, value, unit, sub, cls = '') =>
            `<div class="oq-kpi card shadow-sm ${cls}"><small>${label}</small><b>${value}<span>${unit}</span></b>${sub}</div>`;
        const lvl = (bad) => bad ? 'bad' : 'ok';
        $('oq-kpis').innerHTML = [
            card('ผู้ป่วยนอก (visit)', fmt(k.visits), 'ราย', (k.visits_delta === null ? '<span class="oq-d">ยังไม่จบวัน ไม่เทียบช่วงก่อน</span>' : delta(k.visits_delta, false))
                + (k.remote ? `<span class="oq-d">ไม่รวม Telemed/PCU/ส่งยาไปรษณีย์ ${fmt(k.remote)} ราย</span>` : '')),
            card('Walk-in / นัด', `${fmt(k.walkin)} / ${fmt(k.appt)}`, '', `<span class="oq-d">นัด ${k.visits ? Math.round(k.appt * 100 / k.visits) : 0}%</span>`),
            card('Door-to-Doctor', fmt(k.d2d), 'นาที', delta(k.d2d_delta, true)),
            card('เวลารวมต่อราย', fmt(k.total), 'นาที', delta(k.total_delta, true), k.total !== null ? lvl(k.total > t.total) : ''),
            card('จบภายใน 2 ชม.', k.done ?? '–', '%', `<span class="oq-d">เป้า ≥ ${t.done}% · ช่วงก่อน ${k.done_prev ?? '–'}%</span>`, k.done !== null ? lvl(k.done < t.done) : ''),
            card('อัตราผิดนัด', k.noshow ?? '–', '%', `<span class="oq-d">เป้า ≤ ${t.noshow}% · ไม่รวมนัดวันนี้</span>`, k.noshow !== null ? lvl(k.noshow > t.noshow) : ''),
        ].join('');
    }

    function sla(steps) {
        $('oq-sla').innerHTML = steps.map(s => {
            const pass = s.avg === null ? null : s.avg <= s.target;
            const badge = pass === null ? '<span class="text-muted">–</span>'
                : pass ? '<span class="oq-ok"><i class="fa-solid fa-check"></i></span>' : '<span class="oq-bad"><i class="fa-solid fa-xmark"></i></span>';
            return `<tr><td>${esc(s.label)}</td><td class="text-right"><b>${fmt(s.avg)}</b></td><td class="text-right">≤ ${s.target}</td>`
                + `<td class="text-right">${s.ok_pct ?? '–'}%</td><td class="text-right">${fmt(s.n)}</td><td class="text-center">${badge}</td></tr>`;
        }).join('');
    }

    function funnel(rows) {
        $('oq-funnel').innerHTML = rows.map(r =>
            `<div class="oq-fn"><span>${esc(r.label)}</span><div class="oq-bar"><i style="width:${Math.min(r.pct, 100)}%"></i></div>`
            + `<b>${r.pct}%</b><small>${fmt(r.value)}</small></div>`).join('');
    }

    let chart = null;
    function hours(h, off) {
        const opts = {
            chart: { type: 'column', style: { fontFamily: 'inherit' } },
            title: { text: null }, credits: { enabled: false },
            xAxis: { categories: h.map(x => String(x.h).padStart(2, '0') + ':00') },
            yAxis: { title: { text: 'ราย' }, stackLabels: { enabled: true } },
            tooltip: { shared: true },
            plotOptions: { column: { stacking: 'normal', borderWidth: 0 } },
            series: [
                { name: 'Walk-in', data: h.map(x => x.walkin), color: '#1565d8' },
                { name: 'นัด', data: h.map(x => x.appt), color: '#1e8e5a' },
            ],
        };
        if (chart) chart.destroy();
        chart = Highcharts.chart('oq-hours', opts);
        const peak = h.reduce((a, b) => (b.walkin + b.appt > a.walkin + a.appt ? b : a), h[0]);
        $('oq-hours-note').textContent = `ช่วงพีค ${String(peak.h).padStart(2, '0')}:00–${String(peak.h + 1).padStart(2, '0')}:00 น. (${fmt(peak.walkin + peak.appt)} ราย)`
            + (off ? ` · ลงทะเบียนนอกช่วง 06–21 น. ${fmt(off)} ราย` : '');
    }

    function alerts(rows) {
        const lv = { high: ['เร่งด่วน', 'oq-lv-high'], mid: ['เฝ้าระวัง', 'oq-lv-mid'], ok: ['ผ่าน', 'oq-lv-ok'] };
        $('oq-alerts').innerHTML = rows.map(a => `<tr><td>${esc(a.issue)}</td><td class="text-right"><b>${esc(a.value)}</b></td>`
            + `<td class="text-right">${esc(a.target)}</td><td class="text-center"><span class="oq-lv ${lv[a.level][1]}">${lv[a.level][0]}</span></td>`
            + `<td>${a.level === 'ok' ? '<span class="text-muted">–</span>' : esc(a.action)}</td></tr>`).join('')
            || '<tr><td colspan="5" class="text-center text-muted">ไม่มีข้อมูล</td></tr>';
    }

    function deps(rows, d2dTarget) {
        $('oq-deps').innerHTML = rows.map(r => `<tr><td>${esc(r.name)}</td><td class="text-right">${fmt(r.count)}</td>`
            + `<td class="text-right">${fmt(r.seen)}</td>`
            + `<td class="text-right ${r.d2d !== null && r.d2d > d2dTarget ? 'oq-bad' : ''}">${fmt(r.d2d)}</td>`
            + `<td class="text-right">${r.d2d_ok ?? '–'}${r.d2d_ok === null ? '' : '%'}</td>`
            + `<td class="text-right">${fmt(r.rx)}</td><td class="text-right">${fmt(r.total)}</td></tr>`).join('')
            || '<tr><td colspan="7" class="text-center text-muted">ไม่มีข้อมูล</td></tr>';
    }

    let loaded = false, busy = false;
    async function load() {
        if (busy) return;
        busy = true;
        $('oq-period').textContent = 'กำลังโหลดข้อมูล…';
        try {
            const res = await fetch(`../api/opd_service_kpi.php?d1=${encodeURIComponent(d1.value)}&d2=${encodeURIComponent(d2.value)}`);
            const j = await res.json();
            if (!res.ok || j.error) throw new Error(j.error || res.status);
            d1.value = j.period.d1;
            d2.value = j.period.d2;
            $('oq-period').textContent = `${j.period.label} · ${j.period.days} วัน · ข้อมูล ณ ${j.generated} น.`;
            kpis(j.kpi, j.targets);
            sla(j.steps);
            funnel(j.funnel);
            hours(j.hours, j.off_hours);
            alerts(j.alerts);
            deps(j.deps, (j.steps[2] || {}).target);
            loaded = true;
        } catch (e) {
            $('oq-period').textContent = 'โหลดข้อมูลไม่สำเร็จ: ' + e.message;
        } finally {
            busy = false;
        }
    }

    d1.addEventListener('change', () => { if (d1.value > d2.value) d2.value = d1.value; load(); });
    d2.addEventListener('change', () => { if (d2.value < d1.value) d1.value = d2.value; load(); });
    document.querySelectorAll('[data-oq-days]').forEach(b => b.addEventListener('click', () => {
        const dt = new Date();
        dt.setDate(dt.getDate() - (Number(b.dataset.oqDays) - 1));
        d1.value = iso(dt);
        d2.value = today;
        load();
    }));
    window.addEventListener('opdTabChanged', e => {
        if (e.detail.tab !== 'quality') return;
        if (!loaded) load();
        else if (chart) chart.reflow();
    });
})();
