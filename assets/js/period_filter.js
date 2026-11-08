/* =========================================================
   ตัวเลือกช่วงเวลา (หน้า ER / IPD)
   [วันนี้ | เดือนนี้ | ปีงบประมาณ] [ปีงบ ▾] [📅 d1 ถึง d2]
   - โหลดก่อนสคริปต์ของหน้า: ตั้งค่าเริ่มต้น = ปีงบปัจจุบัน
   - เมื่อเปลี่ยนช่วง จะยิง event 'change' ที่ #budget-year ให้หน้าเดิมโหลดข้อมูลใหม่ตามปกติ
   - หน้าเรียก bbhPeriod.query() ได้ "year=2570" หรือ "d1=YYYY-MM-DD&d2=YYYY-MM-DD" (config/period.php)
========================================================= */
(function () {
    const select = document.getElementById('budget-year');
    const host = document.getElementById('period-filter');
    if (!select || !host) return;

    const pad = n => String(n).padStart(2, '0');
    const iso = d => d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
    const today = iso(new Date());
    const thaiDate = v => new Date(v + 'T00:00:00').toLocaleDateString('th-TH', { day: 'numeric', month: 'short', year: 'numeric' });

    function fiscalRange(year) {
        const end = (year - 543) + '-09-30';
        return [(year - 544) + '-10-01', end < today ? end : today];
    }

    const state = { mode: 'year', preset: 'year', year: Number(select.value), d1: '', d2: '' };
    [state.d1, state.d2] = fiscalRange(state.year);

    host.insertAdjacentHTML('afterbegin', `
        <div class="bbh-seg" role="group" aria-label="ช่วงเวลา">
            <button type="button" data-period="today" aria-pressed="false">วันนี้</button>
            <button type="button" data-period="month" aria-pressed="false">เดือนนี้</button>
            <button type="button" data-period="year" aria-pressed="false">ปีงบประมาณ</button>
        </div>`);
    host.insertAdjacentHTML('beforeend', `
        <div class="bbh-range">
            <i class="fa-regular fa-calendar" aria-hidden="true"></i>
            <label class="bbh-date"><span data-text="d1"></span><input type="date" data-input="d1" aria-label="วันที่เริ่มต้น" max="${today}"></label>
            <span class="bbh-range-sep" aria-hidden="true">ถึง</span>
            <label class="bbh-date"><span data-text="d2"></span><input type="date" data-input="d2" aria-label="วันที่สิ้นสุด" max="${today}"></label>
        </div>`);

    const yearWrap = select.closest('.budget-select-wrapper') || select;
    const input = k => host.querySelector(`[data-input="${k}"]`);

    function label() {
        if (state.mode === 'year') {
            return `ปีงบประมาณ ${state.year} (${thaiDate(state.d1)} – ${thaiDate(state.d2)})`;
        }
        return state.d1 === state.d2 ? `วันที่ ${thaiDate(state.d1)}` : `${thaiDate(state.d1)} – ${thaiDate(state.d2)}`;
    }

    function sync() {
        host.querySelectorAll('[data-period]').forEach(b =>
            b.setAttribute('aria-pressed', String(b.dataset.period === state.preset)));
        yearWrap.hidden = state.mode !== 'year';
        ['d1', 'd2'].forEach(k => {
            input(k).value = state[k];
            host.querySelector(`[data-text="${k}"]`).textContent = thaiDate(state[k]);
        });
        const text = document.getElementById('budget-date-text');
        if (text) text.textContent = label();
    }

    // แจ้งหน้าให้โหลดใหม่ผ่าน listener เดิมของ #budget-year (ข้าม listener ของไฟล์นี้เอง)
    let internal = false;
    function apply() {
        sync();
        internal = true;
        select.dispatchEvent(new Event('change'));
        internal = false;
    }

    // ผู้ใช้เปลี่ยนปีงบเอง: listener นี้ลงทะเบียนก่อนสคริปต์ของหน้า จึงอัปเดต state ก่อนหน้าโหลดข้อมูล
    select.addEventListener('change', () => {
        if (internal) return;
        state.mode = state.preset = 'year';
        state.year = Number(select.value);
        [state.d1, state.d2] = fiscalRange(state.year);
        sync();
    });

    host.querySelectorAll('[data-period]').forEach(b => b.addEventListener('click', () => {
        const p = b.dataset.period;
        state.preset = p;
        if (p === 'year') {
            state.mode = 'year';
            state.year = Number(select.value);
            [state.d1, state.d2] = fiscalRange(state.year);
        } else {
            state.mode = 'range';
            state.d1 = p === 'today' ? today : today.slice(0, 8) + '01';
            state.d2 = today;
        }
        apply();
    }));

    ['d1', 'd2'].forEach(k => {
        const el = input(k);
        el.addEventListener('click', () => { try { el.showPicker(); } catch (e) { /* เบราว์เซอร์เก่า: ปฏิทินปกติ */ } });
        el.addEventListener('change', () => {
            if (!el.value) return sync();
            state[k] = el.value > today ? today : el.value;
            if (state.d1 > state.d2) {
                if (k === 'd1') state.d2 = state.d1; else state.d1 = state.d2;
            }
            // API รับได้ไม่เกิน 400 วัน (config/period.php) — ยาวกว่านั้นเลื่อนอีกฝั่งให้พอดี
            const span = (new Date(state.d2) - new Date(state.d1)) / 86400000 + 1;
            if (span > 400) {
                const d = new Date((k === 'd1' ? state.d1 : state.d2) + 'T00:00:00');
                d.setDate(d.getDate() + (k === 'd1' ? 399 : -399));
                if (k === 'd1') state.d2 = iso(d) > today ? today : iso(d); else state.d1 = iso(d);
            }
            state.mode = 'range';
            state.preset = state.d1 === today && state.d2 === today ? 'today'
                : (state.d1 === today.slice(0, 8) + '01' && state.d2 === today ? 'month' : null);
            apply();
        });
    });

    window.bbhPeriod = {
        query: () => state.mode === 'year'
            ? 'year=' + encodeURIComponent(state.year)
            : 'd1=' + state.d1 + '&d2=' + state.d2,
        label,
        state
    };

    sync();
})();
