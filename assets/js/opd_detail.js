function formatThaiDate(dateString) {
    const parts = dateString.split('-').map(Number);
    if (parts.length !== 3) return dateString;

    const thaiMonths = [
        'มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน',
        'กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม'
    ];

    const d = new Date(parts[0], parts[1] - 1, parts[2]);
    return `${d.getDate()} ${thaiMonths[d.getMonth()]} ${d.getFullYear() + 543}`;
}

// กลุ่มสิทธิ์หลักตาม pttype.hipdata_code (ชื่อกลุ่มตั้งจากชื่อสิทธิ์ย่อยที่ใช้จริงของ รพ.)
const PAYER_GROUPS = {
    UCS: 'บัตรทอง (UC)',
    SSS: 'ประกันสังคม',
    SSI: 'ประกันสังคม',
    OFC: 'ข้าราชการ / เบิกกรมบัญชีกลาง',
    BKK: 'ข้าราชการ / เบิกกรมบัญชีกลาง',
    LGO: 'ท้องถิ่น (อปท.)',
    NRH: 'ต่างด้าว',
    STP: 'ผู้มีปัญหาสถานะและสิทธิ',
    INS: 'พ.ร.บ.',
    OTH: 'ชำระเงินเอง / อื่นๆ',
    PVT: 'ชำระเงินเอง / อื่นๆ'
};

// กราฟแท่งแนวนอน: ชื่อยาวขึ้นบรรทัดใหม่แทนการตัด "..." และตัวเลขอยู่นอกแท่งเสมอ (ในแท่งสีเข้มอ่านไม่ออก)
const BAR_LABEL_STYLE = { fontSize: '18px', width: '180px', textOverflow: 'none' };
const BAR_DATA_LABELS = { enabled: true, inside: false, crop: false, overflow: 'allow', color: '#000', style: { fontSize: '18px', textOutline: 'none' } };

// ฟอนต์ THSarabun ตัวเล็กกว่าฟอนต์ทั่วไป จึงขยายขนาดตัวอักษรทุกกราฟในหน้านี้
Highcharts.setOptions({
    chart: { style: { fontFamily: 'THSarabun' } },
    title: { style: { fontSize: '24px' } },
    subtitle: { style: { fontSize: '17px' } },
    xAxis: { labels: { style: { fontSize: '17px' } }, title: { style: { fontSize: '17px' } } },
    yAxis: { labels: { style: { fontSize: '16px' } }, title: { style: { fontSize: '17px' } } },
    tooltip: { style: { fontSize: '17px' } },
    legend: { itemStyle: { fontSize: '18px' } }
});

function getSelectedServiceDate() {
    const input = document.getElementById('opd-service-date');
    if (input && input.value) return input.value;

    return getTodayServiceDate();
}

function getTodayServiceDate() {
    const today = new Date();
    return today.getFullYear() + '-' +
        String(today.getMonth() + 1).padStart(2, '0') + '-' +
        String(today.getDate()).padStart(2, '0');
}

async function loadChart() {

    const year =
        document.getElementById(
            'budget-year'
        ).value;

    /*
    แสดงช่วงวันที่ปีงบประมาณ
    */

    const yearAD =
        parseInt(year) - 543;

    const startDateText =
        `1 ตุลาคม ${year - 1}`;

    /*
    ปีปัจจุบันไหม
    */

    const now = new Date();
    const currentThaiYear =
        Number(window.BBH_CURRENT_FISCAL_YEAR) ||
        (now.getFullYear() + 543 + (now.getMonth() + 1 >= 10 ? 1 : 0));

    let endDateText;

    if (parseInt(year) === currentThaiYear) {

        const today =
            new Date();

        const thaiMonths = [
            'มกราคม',
            'กุมภาพันธ์',
            'มีนาคม',
            'เมษายน',
            'พฤษภาคม',
            'มิถุนายน',
            'กรกฎาคม',
            'สิงหาคม',
            'กันยายน',
            'ตุลาคม',
            'พฤศจิกายน',
            'ธันวาคม'
        ];

        endDateText =
            `${today.getDate()} ${
                thaiMonths[today.getMonth()]
            } ${today.getFullYear() + 543}`;

    } else {

        endDateText =
            `30 กันยายน ${year}`;

    }

    /*
    แสดงข้อความ
    */

    document.getElementById(
        'budget-date-text'
    ).innerHTML = `

        ปีงบประมาณ ${year}
        (${startDateText} - ${endDateText})

    `;

    const response = await fetch(
        '../api/service_trend.php?year=' + year
    );

    const data = await response.json();

    /*
    Current month ตามปีงบประมาณ
    */
    const allMonths = [
    'ต.ค.',
    'พ.ย.',
    'ธ.ค.',
    'ม.ค.',
    'ก.พ.',
    'มี.ค.',
    'เม.ย.',
    'พ.ค.',
    'มิ.ย.',
    'ก.ค.',
    'ส.ค.',
    'ก.ย.'
    ];

    /*
    ปีปัจจุบันไหม
    */

    let months = allMonths;

    let monthCount = 12;

    /*
    ถ้าเป็นปีปัจจุบัน
    ให้แสดงถึงเดือนปัจจุบัน
    */

    if (parseInt(year) === currentThaiYear) {

        const currentDate =
            new Date();

        const currentMonth =
            currentDate.getMonth() + 1;

        let currentFiscalIndex;

        if (currentMonth >= 10) {

            currentFiscalIndex =
                currentMonth - 10;

        } else {

            currentFiscalIndex =
                currentMonth + 2;
        }

        monthCount =
            currentFiscalIndex + 1;

        months =
            allMonths.slice(0, monthCount);
    }

    /*
    สร้าง array ตามจำนวนเดือน
    */

    let opdData =
        Array(monthCount).fill(0);

    /*
    ใส่ข้อมูล
    */

    data.forEach(row => {

        let month =
            parseInt(row.month);

        let index =
            month >= 10
            ? month - 10
            : month + 2;

        if (index < monthCount) {

            opdData[index] +=
                parseInt(row.opd);
        }
    });

    // เดือนปัจจุบันของปีงบนี้ยังไม่ครบเดือน: แท่งสีอ่อน และเส้นช่วงสุดท้ายเป็นจุดไข่ปลา
    const isCurrentYear = parseInt(year) === currentThaiYear;

    const columnData = opdData.map((y, i) =>
        isCurrentYear && i === monthCount - 1
            ? { y, color: '#8fd1b0', partial: true }   // เดือนที่ยังไม่จบ ใช้สีอ่อน
            : y
    );

    Highcharts.chart('opd-chart', {

        chart: {
            style: {
                fontFamily: 'THSarabun'
            }
        },

        title: {
            text:
            'จำนวนครั้งที่มารับบริการผู้ป่วยนอก ปีงบประมาณ ' + year
        },

        subtitle: {
            text: isCurrentYear ? 'แท่งสีอ่อน / เส้นจุดไข่ปลา = เดือนปัจจุบัน (ยังไม่ครบเดือน)' : null
        },

        xAxis: {
            categories: months
        },

        yAxis: {

            title: {
                text: 'จำนวน (ครั้ง)'
            }

        },

        legend: {
            enabled: true
        },

        credits: {
            enabled: false
        },

        tooltip: {
            pointFormatter: function () {
                const note = this.options.partial ? ' (ยังไม่ครบเดือน)' : '';
                return `${this.series.name}: <b>${Math.round(this.y).toLocaleString()} ครั้ง</b>${note}`;
            }
        },

        series: [{
            type: 'column',
            name: 'ผู้ป่วยนอก',
            color: '#198754',
            data: columnData,
            dataLabels: {
                enabled: true,
                color: '#000000',
                style: {
                    fontSize: '17px',
                }
            }
        }, {
            // เส้นแนวโน้ม: ลากต่อกันทุกเดือนตามยอดจริง
            type: 'spline',
            name: 'แนวโน้ม',
            color: '#c2410c',
            lineWidth: 3,
            marker: { enabled: true, radius: 5, fillColor: '#fff', lineWidth: 2, lineColor: '#c2410c' },
            data: columnData.map(p => (typeof p === 'object' ? { y: p.y, partial: true } : p)),
            zoneAxis: 'x',
            zones: isCurrentYear ? [{ value: monthCount - 2 }, { dashStyle: 'Dot' }] : []
        }]

    });

}

loadChart();

async function loadPttypeChart(){

    const response =
        await fetch('../api/opd_pttype_today.php?date=' + encodeURIComponent(getSelectedServiceDate()));

    const data =
        await response.json();

    if (!Array.isArray(data)) {
        console.error('pttype API error:', data && data.error);
        return;
    }

    // รหัสสิทธิ์ย่อยมี 40+ รหัส จึงรวมเป็นกลุ่มสิทธิ์หลักตาม hipdata_code แล้วแสดงรายการย่อยใน tooltip
    const groups = {};
    data.forEach(row => {
        const name = PAYER_GROUPS[row.hipdata_code] || 'อื่นๆ';
        const n = parseInt(row.total_patient, 10) || 0;
        const g = groups[name] || (groups[name] = { name, y: 0, subs: [] });
        g.y += n;
        g.subs.push({ name: row.pttype_name || 'ไม่ระบุสิทธิ์', n });
    });
    const points = Object.values(groups).sort((a, b) => b.y - a.y);
    const total = points.reduce((s, p) => s + p.y, 0);
    const pct = y => total ? (y * 100 / total).toFixed(1) : '0.0';

    Highcharts.chart('pttype-chart', {

        chart: {
            type: 'bar',
            height: Math.max(260, points.length * 34 + 80),
            style: {
                fontFamily: 'THSarabun'
            }
        },

        title: {
            text: null
        },

        xAxis: {
            type: 'category',
            labels: {
                style: BAR_LABEL_STYLE
            }
        },

        yAxis: {
            allowDecimals: false,
            maxPadding: 0.4,   // เว้นที่ให้ป้าย "จำนวน (%)" อยู่นอกแท่ง
            title: {
                text: 'จำนวน (ครั้ง)'
            }
        },

        tooltip: {
            formatter: function () {
                const subs = this.point.subs.slice().sort((a, b) => b.n - a.n);
                const lines = subs.slice(0, 6).map(s => `${s.name}: ${s.n}`);
                if (subs.length > 6) lines.push(`และอีก ${subs.length - 6} รายการ`);
                return `<b>${this.point.name}</b> ${this.point.y} ครั้ง (${pct(this.point.y)}%)<br>` + lines.join('<br>');
            }
        },

        plotOptions: {
            bar: {
                borderRadius: 4,
                dataLabels: {
                    ...BAR_DATA_LABELS,
                    formatter: function () {
                        return `${this.y} (${pct(this.y)}%)`;
                    }
                }
            }
        },

        credits: {
            enabled: false
        },

        legend: {
            enabled: false
        },

        series: [{
            name: 'จำนวนผู้ป่วย',
            color: '#198754',
            data: points
        }]

    });

}

async function loadOpdTodaySummary() {

    try {

        const response =
            await fetch(
                '../api/opd_visit_today.php?date=' + encodeURIComponent(getSelectedServiceDate())
            );

        const data =
            await response.json();

        /*
        =========================
        ERROR CHECK
        =========================
        */

        if (data.error) {

            console.error(data.error);

            return;
        }

        /*
        =========================
        OPD TODAY TOTAL
        =========================
        */

        document.getElementById(
            'opd_total_today'
        ).innerHTML =

            Number(
                data.opd_total_today || 0
            ).toLocaleString();

        /*
        =========================
        WALK IN
        =========================
        */

        document.getElementById(
            'walkin_today'
        ).innerHTML =

            Number(
                data.walkin || 0
            ).toLocaleString();

        /*
        =========================
        APPOINTMENT
        =========================
        */

        document.getElementById(
            'appoint_today'
        ).innerHTML =

            Number(
                data.appoint_today || 0
            ).toLocaleString();

        /*
        =========================
        MISS APPOINTMENT
        =========================
        */

        document.getElementById(
            'miss_today'
        ).innerHTML =

            Number(
                data.miss_today || 0
            ).toLocaleString();

        // ระหว่างวันยังสรุปไม่ได้ว่าใครไม่มา และสถานะ "รอ" มีความหมายเฉพาะวันนี้
        const isToday = getSelectedServiceDate() === getTodayServiceDate();

        document.getElementById('miss_label').textContent =
            isToday ? 'ยังไม่มา' : 'ไม่มาตามนัด';

        document.getElementById('wait_triage').innerHTML =
            isToday ? Number(data.wait_triage || 0).toLocaleString() : '—';

        document.getElementById('wait_exam').innerHTML =
            isToday ? Number(data.wait_exam || 0).toLocaleString() : '—';

        document.getElementById(
            'finish_exam'
        ).innerHTML =

            Number(
                data.finish_exam
            ).toLocaleString();

        /*
        =========================
        OPD SUCCESS (มาตามนัด)
        =========================
        */

        document.getElementById(
            'oapp_success'
        ).innerHTML =

            Number(
                data.oapp_success || 0
            ).toLocaleString();

        /*
        =========================
        ก่อนเวลาราชการ (20:01 - 07:59)
        =========================
        */

        document.getElementById(
            'before_time'
        ).innerHTML =

            Number(
                data.before_time || 0
            ).toLocaleString();

        /*
        =========================
        ในเวลาราชการ (08:00 - 15:59)
        =========================
        */

        document.getElementById(
            'worktime'
        ).innerHTML =

            Number(
                data.worktime || 0
            ).toLocaleString();

        /*
        =========================
        นอกเวลาราชการ (16:00 - 20:00)
        =========================
        */

        document.getElementById(
            'after_time'
        ).innerHTML =

            Number(
                data.after_time || 0
            ).toLocaleString();

        renderOpdExtras(data, isToday);

    } catch(error) {

        console.error(
            'Load OPD Summary Error:',
            error
        );

    }

}

const fmtN = n => Number(n || 0).toLocaleString();

function setText(id, value) {
    document.getElementById(id).textContent = value;
}

// A1–A3 + % นัด + กราฟแผนก/เวลานัด — ทุกค่ามาจาก opd_visit_today.php ของวันที่เลือก
function renderOpdExtras(data, isToday) {

    setText('opd_total_people', fmtN(data.total_people));
    // นับเป็นคน (HN) ให้ ใหม่ + เก่า = จำนวนคนทั้งหมด
    setText('new_patient', fmtN(data.new_patient));
    setText('old_patient', fmtN(data.total_people - data.new_patient));
    setText('opd_admit', fmtN(data.admit));
    setText('opd_refer', fmtN(data.refer));

    // มากกว่า = เขียว ▲, น้อยกว่า = แดง ▼ (มีลูกศรกำกับ ไม่พึ่งสีอย่างเดียว)
    const change = prev => {
        if (!prev) return '';
        const p = Math.round((data.opd_total_today - prev) * 100 / prev);
        if (p === 0) return ' <span class="opd-delta">(เท่ากัน)</span>';
        return ` <span class="opd-delta ${p > 0 ? 'up' : 'down'}">${p > 0 ? '▲' : '▼'} ${Math.abs(p)}%</span>`;
    };
    document.getElementById('opd-compare').innerHTML =
        `เทียบเมื่อวาน ${fmtN(data.prev_day)} ครั้ง${change(data.prev_day)}` +
        (isToday ? ' — นับถึงเวลาเดียวกัน' : '');

    // % คิดจากนัดที่ไม่ได้ยกเลิก (มา + ไม่มา)
    const apptBase = data.oapp_success + data.miss_today;
    const apptPct = n => apptBase ? Math.round(n * 100 / apptBase) + '%' : '–';
    setText('oapp_success_pct', apptPct(data.oapp_success));
    setText('miss_pct', apptPct(data.miss_today));

    renderDeptChart(data.depts || []);
    renderApptTimeChart(data.appt_times || []);
}

// A4: 10 แผนกที่ลงทะเบียนมากสุด ที่เหลือรวมเป็น "อื่นๆ"
function renderDeptChart(depts) {

    const TOP = 10;
    const points = depts.slice(0, TOP).map(d => ({ name: d.name.replace(/^\d+\s*/, ''), y: d.total }));
    const rest = depts.slice(TOP);
    if (rest.length) {
        points.push({ name: `แผนกอื่นๆ (${rest.length} แผนก)`, y: rest.reduce((s, d) => s + d.total, 0), color: '#9aa9a2' });
    }

    if (!points.length) {
        document.getElementById('opd-dept-chart').innerHTML = '<p class="text-muted text-center py-5">ไม่มีผู้มารับบริการในวันที่เลือก</p>';
        return;
    }

    Highcharts.chart('opd-dept-chart', {
        chart: { type: 'bar', height: Math.max(260, points.length * 34 + 80), style: { fontFamily: 'THSarabun' } },
        title: { text: null },
        xAxis: { type: 'category', labels: { style: BAR_LABEL_STYLE } },
        yAxis: { allowDecimals: false, maxPadding: 0.2, title: { text: 'จำนวน (ครั้ง)' } },
        tooltip: { pointFormat: '<b>{point.y} ครั้ง</b>' },
        plotOptions: { bar: { borderRadius: 4, dataLabels: { ...BAR_DATA_LABELS } } },
        credits: { enabled: false },
        legend: { enabled: false },
        series: [{ name: 'ผู้มารับบริการ', color: '#198754', data: points }]
    });
}

// A5: จำนวนนัดตามเวลานัด — เห็นว่านัดกระจุกเวลาเดียวกันแค่ไหน
function renderApptTimeChart(times) {

    const total = times.reduce((s, t) => s + t.total, 0);
    const note = document.getElementById('appt-time-note');

    if (!total) {
        document.getElementById('opd-appt-time-chart').innerHTML = '<p class="text-muted text-center py-5">ไม่มีนัดในวันที่เลือก</p>';
        note.textContent = '';
        return;
    }

    const peak = times.reduce((a, b) => (b.total > a.total ? b : a));
    note.textContent = `ช่วง ${peak.time} น. (30 นาที) มีนัดมากที่สุด ${fmtN(peak.total)} ราย ` +
        `(${Math.round(peak.total * 100 / total)}% ของนัด ${fmtN(total)} ราย) · ไม่นับนัดที่ยกเลิก`;

    Highcharts.chart('opd-appt-time-chart', {
        chart: { type: 'spline', height: 320, style: { fontFamily: 'THSarabun' } },
        title: { text: null },
        xAxis: { categories: times.map(t => t.time), title: { text: 'เวลานัด (ช่วงละ 30 นาที)' } },
        yAxis: { allowDecimals: false, min: 0, title: { text: 'จำนวนนัด (ราย)' } },
        tooltip: { headerFormat: '<b>{point.key} น.</b><br>', pointFormat: 'นัด <b>{point.y} ราย</b>' },
        plotOptions: {
            spline: {
                lineWidth: 3,
                marker: { enabled: true, radius: 5 },
                dataLabels: { enabled: true, color: '#000', style: { fontSize: '17px', textOutline: '2px #fff' } }
            }
        },
        credits: { enabled: false },
        legend: { enabled: false },
        series: [{ name: 'นัดหมาย', color: '#198754', data: times.map(t => t.total) }]
    });
}

function initOpdServiceDateFilter() {
    const input = document.getElementById('opd-service-date');
    const title = document.getElementById('opd-selected-date-title');

    if (!input) return;

    const today = new Date();
    const todayString =
        today.getFullYear() + '-' +
        String(today.getMonth() + 1).padStart(2, '0') + '-' +
        String(today.getDate()).padStart(2, '0');

    input.max = todayString;
    input.value = todayString;

    const isoOf = d =>
        d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    const dayButtons = document.querySelectorAll('[data-opd-day]');

    function refreshDateSummary() {
        const selected = input.value || todayString;

        setText('opd-service-date-text', formatThaiDate(selected));
        dayButtons.forEach(b => {
            const d = new Date(today);
            d.setDate(d.getDate() - Number(b.dataset.opdDay));
            b.setAttribute('aria-pressed', String(isoOf(d) === selected));
        });

        if (title) {
            title.textContent =
                'ผู้มารับบริการตรวจโรคทั่วไป วันที่ ' +
                formatThaiDate(selected);
        }

        const dayText = selected === todayString ? 'วันนี้' : 'วันที่ ' + formatThaiDate(selected);
        setText('opd-day-title', 'ข้อมูลผู้มารับบริการ' + dayText);
        setText('appt-section-title', 'นัดหมาย' + dayText);

        loadOpdTodaySummary();
        loadPttypeChart();
    }

    // รีเฟรชอัตโนมัติเฉพาะเมื่อดูวันนี้ — วันย้อนหลังตัวเลขไม่เปลี่ยนแล้ว
    setInterval(() => {
        if ((input.value || todayString) === getTodayServiceDate()) {
            loadOpdTodaySummary();
            loadPttypeChart();
        }
    }, 60000);

    input.addEventListener('change', refreshDateSummary);
    input.addEventListener('click', () => { try { input.showPicker(); } catch (e) { /* เบราว์เซอร์เก่า: ใช้ปฏิทินปกติ */ } });

    dayButtons.forEach(b => b.addEventListener('click', () => {
        const d = new Date(today);
        d.setDate(d.getDate() - Number(b.dataset.opdDay));
        input.value = isoOf(d);
        refreshDateSummary();
    }));

    refreshDateSummary();
}

initOpdServiceDateFilter();

document.getElementById(
    'budget-year'
).addEventListener(
    'change',
    loadChart
);
/*
============================================================
Top 10 Diagnosis OPD
============================================================
*/

async function loadOpdTop10Table() {

const year =
    document.getElementById('budget-year').value;

const tbody =
    document.getElementById('opd-top10-tbody');

tbody.innerHTML = `
    <tr>
        <td colspan="6" class="text-center text-muted py-4">
            <i class="fa-solid fa-spinner fa-spin me-2"></i>
            กำลังโหลดข้อมูล...
        </td>
    </tr>`;

try {

    const response = await fetch('../api/opd_top10_disease.php?year=' + year);
    const data     = await response.json();

    if (data.error) {
        tbody.innerHTML = `
            <tr>
                <td colspan="6" class="text-center text-danger py-3">
                    เกิดข้อผิดพลาด: ${data.error}
                </td>
            </tr>`;
        return;
    }

    if (!data.length) {
        tbody.innerHTML = `
            <tr>
                <td colspan="6" class="text-center text-muted py-3">
                    ไม่พบข้อมูล
                </td>
            </tr>`;
        return;
    }

    const rankColor = {
        1: 'background:#FFD700;color:#333;',
        2: 'background:#C0C0C0;color:#333;',
        3: 'background:#CD7F32;color:#fff;',
    };

    let html = '';

    data.forEach(row => {

        const badgeStyle = rankColor[row.rank]
            ? rankColor[row.rank]
            : 'background:#198754;color:#fff;';


        html += `
            <tr>
                <td class="text-center">
                    <span class="rank-badge" style="${badgeStyle}">
                        ${row.rank}
                    </span>
                </td>
                <td class="text-center">
                    <span class="badge bg-secondary"
                          style="font-size:14px;letter-spacing:1px">
                        ${row.icd10 || '—'}
                    </span>
                </td>
                <td>${diagCell(row.diag_th, row.diag_en)}</td>

                <td class="text-center fw-bold text-success">
                    ${row.total.toLocaleString()}
                </td>
                <td class="text-center" style="color:#3b82f6">
                    ${row.male.toLocaleString()}
                </td>
                <td class="text-center" style="color:#ec4899">
                    ${row.female.toLocaleString()}
                </td>
            </tr>`;
    });

    tbody.innerHTML = html;

} catch (e) {
    tbody.innerHTML = `
        <tr>
            <td colspan="6" class="text-center text-danger py-3">
                ไม่สามารถโหลดข้อมูลได้
            </td>
        </tr>`;
}

}

loadOpdTop10Table();

document
    .getElementById('budget-year')
    .addEventListener('change', loadOpdTop10Table);
/*
============================================================
Age Group Chart — กลุ่มอายุผู้ป่วย OPD
============================================================
*/

async function loadAgeGroupChart() {

const year =
    document.getElementById('budget-year').value;

try {

    const response = await fetch('../api/opd_age_group.php?year=' + year);
    const data     = await response.json();

    if (data.error) {
        console.error('AgeGroup API error:', data.error);
        return;
    }

    const total = data.reduce((s, d) => s + d.total, 0);

    /*
    ==========================
    Bar Chart แนวนอน
    ==========================
    */

    Highcharts.chart('opd-age-chart', {

        chart: {
            type: 'bar',
            backgroundColor: '#ffffff',
            style: { fontFamily: 'THSarabun' }
        },

        title: {
            text: 'กลุ่มอายุผู้ป่วย OPD ปีงบประมาณ ' + year,
            style: { fontSize: '20px' }
        },

        xAxis: {
            categories: data.map(d => d.name),
            labels: {
                style: {
                    fontSize: '18px',
                    fontFamily: 'THSarabun',
                    fontWeight: 'normal'
                }
            }
        },

        yAxis: {
            min: 0,
            maxPadding: 0.2,
            title: { text: 'จำนวน (ครั้ง)' }
        },

        tooltip: {
            formatter: function () {
                const row    = data.find(d => d.name === this.x);
                const pct    = total > 0 ? ((this.y / total) * 100).toFixed(2) : '0.00';
                const male   = row ? row.male.toLocaleString()   : 0;
                const female = row ? row.female.toLocaleString() : 0;
                return `<b>${this.x}</b><br/>
                        รวม: <b>${this.y.toLocaleString()} ครั้ง</b> (${pct}%)<br/>
                        ชาย: <span style="color:#3b82f6">${male}</span> |
                        หญิง: <span style="color:#ec4899">${female}</span>`;
            }
        },

        plotOptions: {
            bar: {
                borderRadius: 4,
                dataLabels: {
                    enabled: true,
                    formatter: function () {
                        const pct = total > 0
                            ? ((this.y / total) * 100).toFixed(1)
                            : '0.0';
                        return `${this.y.toLocaleString()} ครั้ง (${pct}%)`;
                    },
                    style: {
                        fontSize: '17px',
                        fontFamily: 'THSarabun',
                        fontWeight: 'normal',
                        textOutline: 'none'
                    }
                }
            }
        },

        legend: { enabled: false },
        credits: { enabled: false },

        series: [{
            name: 'จำนวนผู้ป่วย',
            colorByPoint: true,
            data: data.map(d => ({
                y:     d.total,
                color: d.color,
                name:  d.name
            }))
        }]

    });

    /*
    ==========================
    Summary Cards แยกชาย/หญิง
    ==========================
    */

    let cardHtml = '';
    data.forEach(d => {
        const pct = total > 0
            ? ((d.total / total) * 100).toFixed(1)
            : '0.0';

        cardHtml += `
            <div class="col-xl-3 col-md-6 mb-2">
                <div style="
                    background:#fff;
                    border-radius:12px;
                    padding:12px 16px;
                    border-left:5px solid ${d.color};
                    box-shadow:rgba(0,0,0,0.08) 0 2px 6px;
                ">
                    <div style="font-size:19px;font-weight:600;color:#333;margin-bottom:6px">
                        ${d.name}
                    </div>
                    <div style="font-size:32px;font-weight:bold;color:${d.color};line-height:1">
                        ${d.total.toLocaleString()}
                        <span style="font-size:18px;font-weight:normal;color:#555">
                            ครั้ง (${pct}%)
                        </span>
                    </div>
                    <div style="font-size:17px;margin-top:6px;color:#555">
                        <span style="color:#3b82f6">♂ ชาย ${d.male.toLocaleString()}</span>
                        &nbsp;|&nbsp;
                        <span style="color:#ec4899">♀ หญิง ${d.female.toLocaleString()}</span>
                    </div>
                </div>
            </div>`;
    });

    const summaryEl = document.getElementById('opd-age-summary');
    if (summaryEl) summaryEl.innerHTML = cardHtml;

} catch (e) {
    console.error('loadAgeGroupChart error:', e);
}

}

loadAgeGroupChart();

document
    .getElementById('budget-year')
    .addEventListener('change', loadAgeGroupChart);

// ชื่อโรค: ไทยเป็นหลัก อังกฤษตัวเล็กใต้ชื่อ (ไม่แสดงซ้ำถ้าเหมือนกันหรือไม่มีชื่อไทย) — ใช้รูปแบบเดียวกันทั้งหน้า OPD / IPD / ER
function diagCell(th, en) {
    th = (th || '').trim();
    en = (en || '').trim();
    const sub = th && en && th !== en ? `<div class="diag-en">${en}</div>` : '';
    return `<div>${th || en || '—'}</div>${sub}`;
}