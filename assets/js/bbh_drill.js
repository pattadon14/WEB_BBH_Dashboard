/* หน้าต่างรายชื่อผู้ป่วยเบื้องหลังตัวเลขบนการ์ด (ใช้ที่หน้า ER) — <dialog> + ค้นหา + ตัวกรอง + แบ่งหน้า + Export CSV
 * ใช้ที่หน้า OPD (opd_detail.js) และ ER (er_summary.js) · หน้าตาใช้ class .opd-drill* ของ assets/css/opd_tabs.css
 *
 * bbhDrill.open({
 *   title, sub,            // หัวหน้าต่าง
 *   url,                   // API ที่คืน { rows: [...] } หรือ { error } (ต้อง login — API ตรวจเอง)
 *   cols: [[key, หัวคอลัมน์], ...]          หรือ data => [...] ถ้าคอลัมน์ขึ้นกับผลที่ได้ (เช่น รายการนัด / visit)
 *   filters: [[key, ชื่อตัวกรอง], ...]      หรือ data => [...]
 *   mapRows: data => rows,                 (ไม่ใส่ = data.rows) เติมฟิลด์ที่คำนวณได้ เพื่อใช้กรอง/ค้นหา
 *   placeholder: 'ค้นหา …',               searchKeys: [key, ...] (ไม่ใส่ = ทุกคอลัมน์)
 *   exportCols: [[key, หัว], ...]          (ไม่ใส่ = cols)
 *   cell: (row, key) => html | undefined,   // แสดงเซลล์พิเศษ (ค่าที่คืนต้อง escape เองด้วย bbhDrill.esc)
 *   unit: 'ครั้ง', file: 'ER_xxx.csv',
 *   summary: (rows, data) => ข้อความต่อท้ายหัวรอง เช่น "12 ครั้ง (11 คน)"
 * })
 */
window.bbhDrill = (function () {
    'use strict';

    const PAGE_SIZE = 50;
    const esc = v => String(v ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const fmt = n => Number(n || 0).toLocaleString();
    let dlg, el = {}, state = { rows: [], opts: null, page: 1 }, seq = 0;

    function build() {
        dlg = document.createElement('dialog');
        dlg.className = 'opd-drill';
        dlg.id = 'bbh-drill';
        dlg.setAttribute('aria-labelledby', 'bbh-drill-title');
        dlg.innerHTML = `
            <header class="opd-drill-head">
                <div>
                    <h3 id="bbh-drill-title"></h3>
                    <p data-el="sub"></p>
                </div>
                <button type="button" class="opd-drill-close" data-el="close" aria-label="ปิด"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
            </header>
            <div class="opd-drill-tools">
                <label class="opd-drill-search">
                    <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                    <input type="search" data-el="q" placeholder="ค้นหา HN / ชื่อ / การวินิจฉัย" aria-label="ค้นหาในรายชื่อ">
                </label>
                <span class="opd-drill-count" data-el="count" aria-live="polite"></span>
                <button type="button" class="opd-drill-export" data-el="export"><i class="fa-solid fa-file-excel" aria-hidden="true"></i> Export Excel</button>
            </div>
            <div class="opd-drill-filters" data-el="filters"></div>
            <div class="opd-drill-body" data-el="body"></div>
            <nav class="opd-drill-pager" data-el="pager" aria-label="เปลี่ยนหน้ารายชื่อ"></nav>`;
        // วางใต้ .content-wrapper ให้ได้สีธีมของหน้า (เช่น .er-theme)
        (document.querySelector('.content-wrapper') || document.body).appendChild(dlg);
        dlg.querySelectorAll('[data-el]').forEach(n => { el[n.dataset.el] = n; });
        el.title = dlg.querySelector('#bbh-drill-title');

        el.close.addEventListener('click', () => dlg.close());
        dlg.addEventListener('click', e => { if (e.target === dlg) dlg.close(); });   // คลิกพื้นหลังเพื่อปิด
        const refilter = () => { state.page = 1; render(); };
        el.q.addEventListener('input', refilter);
        el.filters.addEventListener('change', refilter);
        el.pager.addEventListener('click', e => {
            const b = e.target.closest('button[data-page]');
            if (!b || b.disabled) return;
            state.page = Number(b.dataset.page);
            render();
        });
        el.export.addEventListener('click', exportCsv);
    }

    async function open(opts) {
        if (!dlg) build();
        const my = ++seq;
        state = { rows: [], opts, page: 1 };
        el.title.textContent = opts.title;
        el.sub.textContent = opts.sub || '';
        el.count.textContent = '';
        el.q.value = '';
        el.q.placeholder = opts.placeholder || 'ค้นหา HN / ชื่อ';
        el.export.disabled = true;
        el.filters.innerHTML = '';
        el.pager.innerHTML = '';
        el.body.innerHTML = '<p class="opd-drill-msg"><i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i> กำลังโหลดรายชื่อ…</p>';
        if (!dlg.open) dlg.showModal();

        try {
            const res = await fetch(opts.url, { cache: 'no-store' });
            const data = await res.json();
            if (!res.ok || data.error) throw new Error(data.error || 'โหลดรายชื่อไม่สำเร็จ');
            if (my !== seq) return;   // เปิดรายการอื่นไปแล้ว
            const pick = v => typeof v === 'function' ? v(data) : v;
            state.opts = { ...opts, cols: pick(opts.cols), filters: pick(opts.filters) || [] };
            state.rows = opts.mapRows ? opts.mapRows(data) : data.rows;
            if (opts.summary) el.sub.textContent = (opts.sub ? opts.sub + ' · ' : '') + opts.summary(state.rows, data);
            el.export.disabled = !state.rows.length;
            renderFilters();
            render();
        } catch (e) {
            if (my !== seq) return;
            el.body.innerHTML = `<p class="opd-drill-msg opd-drill-error"><i class="fa-solid fa-lock" aria-hidden="true"></i> ${esc(e.message)}</p>`;
        }
    }

    // ตัวเลือกสร้างจากข้อมูลจริงของรายการนั้น เรียงตามจำนวนมากไปน้อย · มีค่าเดียวไม่ต้องแสดง
    function renderFilters() {
        el.filters.innerHTML = state.opts.filters.map(([k, label]) => {
            const counts = new Map();
            state.rows.forEach(r => { const v = String(r[k] ?? ''); if (v) counts.set(v, (counts.get(v) || 0) + 1); });
            if (counts.size < 2) return '';
            const opts = [...counts].sort((a, b) => b[1] - a[1])
                .map(([v, n]) => `<option value="${esc(v)}">${esc(v)} (${fmt(n)})</option>`).join('');
            return `<label class="opd-drill-filter"><span>${esc(label)}</span>
                <select data-filter="${esc(k)}"><option value="">ทั้งหมด</option>${opts}</select></label>`;
        }).join('');
    }

    function filtered() {
        const q = el.q.value.trim().toLowerCase();
        const picks = [...el.filters.querySelectorAll('select')].filter(s => s.value).map(s => [s.dataset.filter, s.value]);
        const keys = state.opts.searchKeys || state.opts.cols.map(([k]) => k);
        return state.rows.filter(r =>
            picks.every(([k, v]) => String(r[k] ?? '') === v) &&
            (!q || keys.some(k => String(r[k] ?? '').toLowerCase().includes(q))));
    }

    // เลขหน้าแบบย่อ: 1 … 4 5 6 … 20
    function pageList(cur, last) {
        const set = new Set([1, last, cur - 1, cur, cur + 1].filter(p => p >= 1 && p <= last));
        const out = [];
        [...set].sort((a, b) => a - b).forEach((p, i, arr) => { if (i && p - arr[i - 1] > 1) out.push('…'); out.push(p); });
        return out;
    }

    function cellHtml(r, k) {
        const custom = state.opts.cell && state.opts.cell(r, k);
        if (custom !== undefined) return custom;
        const v = r[k];
        return v === null || v === undefined || v === '' ? '<span class="text-muted">–</span>' : esc(v);
    }

    function render() {
        const rows = filtered();
        const { cols } = state.opts;
        el.count.textContent = rows.length !== state.rows.length ? `พบ ${fmt(rows.length)} จาก ${fmt(state.rows.length)} รายการ` : '';

        if (!rows.length) {
            el.pager.innerHTML = '';
            el.body.innerHTML = `<p class="opd-drill-msg">${state.rows.length ? 'ไม่พบรายการตามที่ค้นหา/กรอง' : 'ไม่มีรายการในวันที่เลือก'}</p>`;
            return;
        }

        const last = Math.ceil(rows.length / PAGE_SIZE);
        const page = state.page = Math.min(Math.max(state.page, 1), last);
        const start = (page - 1) * PAGE_SIZE;
        const pageRows = rows.slice(start, start + PAGE_SIZE);

        el.body.innerHTML = `
            <table class="opd-drill-table">
                <thead><tr><th>#</th>${cols.map(([, t]) => `<th>${esc(t)}</th>`).join('')}</tr></thead>
                <tbody>${pageRows.map((r, i) =>
                    `<tr><td>${start + i + 1}</td>${cols.map(([k]) => `<td>${cellHtml(r, k)}</td>`).join('')}</tr>`).join('')}</tbody>
            </table>`;
        el.body.scrollTop = 0;

        const btn = (p, label, aria, disabled, current) =>
            `<button type="button" data-page="${p}" aria-label="${aria}"${disabled ? ' disabled' : ''}${current ? ' aria-current="page" class="is-current"' : ''}>${label}</button>`;
        el.pager.innerHTML = `
            <span class="opd-drill-range">แสดง ${fmt(start + 1)}–${fmt(start + pageRows.length)} จาก ${fmt(rows.length)} รายการ</span>
            ${last > 1 ? `<div class="opd-drill-pages">
                ${btn(page - 1, '<i class="fa-solid fa-chevron-left" aria-hidden="true"></i>', 'หน้าก่อน', page === 1)}
                ${pageList(page, last).map(p => p === '…' ? '<span class="gap">…</span>' : btn(p, p, 'หน้า ' + p, false, p === page)).join('')}
                ${btn(page + 1, '<i class="fa-solid fa-chevron-right" aria-hidden="true"></i>', 'หน้าถัดไป', page === last)}
            </div>` : ''}`;
    }

    function exportCsv() {
        const rows = filtered();
        const cols = state.opts.exportCols || state.opts.cols;
        const cell = v => '"' + String(v ?? '').replace(/"/g, '""') + '"';
        const lines = [cols.map(([, t]) => cell(t)).join(',')].concat(rows.map(r => cols.map(([k]) => cell(r[k])).join(',')));
        const url = URL.createObjectURL(new Blob(['﻿' + lines.join('\r\n')], { type: 'text/csv;charset=utf-8;' }));
        const a = document.createElement('a');
        a.href = url;
        a.download = state.opts.file || 'รายชื่อ.csv';
        document.body.appendChild(a);
        a.click();
        a.remove();
        URL.revokeObjectURL(url);
    }

    // การ์ด/ตัวเลขที่มี [data-drill] ภายใน root: เป็นปุ่มสำหรับคีย์บอร์ดด้วย แล้วเรียก onOpen(type)
    function bind(root, onOpen) {
        root.querySelectorAll('[data-drill]').forEach(n => {
            n.setAttribute('role', 'button');
            n.setAttribute('tabindex', '0');
            n.title = n.title ? n.title + ' · คลิกเพื่อดูรายชื่อ' : 'คลิกเพื่อดูรายชื่อ';   // คงคำอธิบายเดิมของการ์ดไว้
        });
        const trigger = e => {
            const n = e.target.closest('[data-drill]');
            if (!n || !root.contains(n)) return;
            if (e.type === 'keydown') {
                if (e.key !== 'Enter' && e.key !== ' ') return;
                e.preventDefault();
            }
            onOpen(n.dataset.drill, n);
        };
        root.addEventListener('click', trigger);
        root.addEventListener('keydown', trigger);
    }

    return { open, bind, esc };
})();
