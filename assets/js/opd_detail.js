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

// ชุดสีกราฟหน้า OPD: เขียวเป็นหลัก (ตามธีม opd_theme.css) · แท่งแต่ละแถวมีชื่อกำกับอยู่แล้ว จึงใช้เขียวไล่เฉดสีเดียว
// สีเสริมมีเฉพาะที่มีความหมาย: ยังไม่มา/ไม่มาตามนัด = ส้ม, เส้นแนวโน้ม = ส้มเข้ม · "อื่นๆ" = เทา
const G = { light: '#5fb98a', main: '#22894f', dark: '#146c3c', deep: '#0f5132', soft: '#9fd3b5' };
const OTHER_COLOR = '#9aa9a2';
const MISS_COLOR = '#c2410c';   // ตัวเลขขาวบนแท่งผ่าน 5:1
// ไล่เฉดตามทิศของแท่ง: bar (แนวนอน) ซ้าย→ขวา, column (แนวตั้ง) ล่าง→บน
const gBar = (from = G.light, to = G.dark) => ({ linearGradient: { x1: 0, y1: 0, x2: 1, y2: 0 }, stops: [[0, from], [1, to]] });
const gCol = (from = G.light, to = G.dark) => ({ linearGradient: { x1: 0, y1: 1, x2: 0, y2: 0 }, stops: [[0, from], [1, to]] });
// แท่งกราฟที่มี point.drill = [type, key, label?, slot?] คลิกแล้วเปิดรายชื่อ (แท่ง "อื่นๆ" ที่รวมหลายกลุ่มไม่มี drill)
const DRILL_BAR = {
    cursor: 'pointer',
    point: { events: { click() { const d = this.options.drill; if (d) openDrill(d[0], d[1], d[2] || this.name, d[3]); } } }
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

// แท็บตรวจโรคทั่วไป/คลินิคพิเศษใช้แผงเดียวกัน — opdScope = ชุดแผนกที่กำลังดู (config/opd.php)
// ผลที่กลับมาหลังสลับแท็บแล้ว (scope ไม่ตรง) ทิ้งไป ไม่ให้ตัวเลขแท็บเก่าทับแท็บใหม่
let opdScope = 'general';
const SCOPE_NAMES = { general: 'ตรวจโรคทั่วไป', special: 'คลินิคพิเศษ' };
const opdQs = () => `date=${encodeURIComponent(getSelectedServiceDate())}&scope=${opdScope}`;

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
            ? { y, color: G.soft, partial: true }   // เดือนที่ยังไม่จบ ใช้สีอ่อน
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
            color: gCol(G.main, G.dark),
            borderRadius: 4,
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

    const scope = opdScope;
    const response =
        await fetch('../api/opd_pttype_today.php?' + opdQs());

    const data =
        await response.json();
    if (scope !== opdScope) return;

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
    // แท่งเขียวไล่เฉด · "อื่นๆ" (ไม่อยู่ในกลุ่มใด) เป็นสีเทา
    const points = Object.values(groups).sort((a, b) => b.y - a.y).map(g => {
        // คลิกแท่งเพื่อดูรายชื่อ: ส่งรหัส hipdata ทุกตัวของกลุ่ม ("อื่นๆ" ที่ไม่มีรหัสในตาราง คลิกไม่ได้)
        const codes = Object.keys(PAYER_GROUPS).filter(c => PAYER_GROUPS[c] === g.name);
        return { ...g, color: codes.length ? gBar() : OTHER_COLOR,
                 drill: codes.length ? ['payer', codes.join(',')] : null };
    });
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
                minPointLength: 6,
                ...DRILL_BAR,
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
            data: points
        }]

    });

}

async function loadOpdTodaySummary() {

    try {

        const scope = opdScope;
        const response =
            await fetch(
                '../api/opd_visit_today.php?' + opdQs()
            );

        const data =
            await response.json();
        if (scope !== opdScope) return;

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
        setOpdLive(isToday);

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

        // 4 ช่องรวมได้ยอดรวม — วันที่ผ่านมาแล้วไม่มีสถานะ "รอ" ทุกคนที่ไม่มีบันทึกพบแพทย์อยู่ช่องนี้
        setText('no_doctor', fmtN(isToday ? data.no_doctor : data.opd_total_today - data.finish_exam));

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

// ป้าย LIVE ใน navbar (templates/navbar.php) ข้อความเดียวกับหน้าแรก — แสดงเฉพาะตอนดูวันนี้ ซึ่งรีเฟรชทุก 60 วินาที
function setOpdLive(isToday) {
    const box = document.getElementById('live-status');
    if (!box) return;
    box.style.display = isToday ? '' : 'none';
    if (!isToday) return;
    const now = new Date();
    const text = 'ข้อมูล ณ วันที่ ' +
        now.toLocaleDateString('th-TH', { day: 'numeric', month: 'long', year: 'numeric' }) +
        ' เวลา ' +
        now.toLocaleTimeString('th-TH', { hour: '2-digit', minute: '2-digit', second: '2-digit' }) + ' น.';
    document.getElementById('last-updated').textContent = text;
    box.title = text;   // จอเล็กซ่อนข้อความยาว ดูได้จาก tooltip
}

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
    // เทียบวันเดียวกันของสัปดาห์ก่อน (จันทร์กับจันทร์) — เทียบเมื่อวานไม่ได้ถ้าเมื่อวานเป็นวันหยุด
    const prev = new Date(getSelectedServiceDate() + 'T00:00:00');
    prev.setDate(prev.getDate() - 7);
    const prevLabel = prev.toLocaleDateString('th-TH', { weekday: 'long', day: 'numeric', month: 'short' });
    document.getElementById('opd-compare').innerHTML =
        `เทียบ${prevLabel} ${fmtN(data.prev_week)} ครั้ง${change(data.prev_week)}` +
        (isToday ? ' — นับถึงเวลาเดียวกัน' : '');

    // % คิดจากนัดที่ไม่ได้ยกเลิก (มา + ไม่มา)
    const apptBase = data.oapp_success + data.miss_today;
    const apptPct = n => apptBase ? Math.round(n * 100 / apptBase) + '%' : '–';
    setText('oapp_success_pct', apptPct(data.oapp_success));
    setText('miss_pct', apptPct(data.miss_today));
    document.getElementById('appt-progress-bar').style.width = apptPct(data.oapp_success).replace('–', '0%');
    document.getElementById('appt-progress').setAttribute('aria-label', `มาตามนัด ${apptPct(data.oapp_success)}`);

    // Walk-in + มาตามนัด = ยอดรวม (นิยามใน config/opd.php)
    const total = data.opd_total_today;
    const share = n => total ? Math.round(n * 100 / total) + '%' : '0%';
    document.getElementById('mix-walkin-bar').style.width = share(data.walkin);
    document.getElementById('mix-appt-bar').style.width = share(data.oapp_success);
    setText('mix-walkin-pct', share(data.walkin));
    setText('mix-appt-pct', share(data.oapp_success));
    document.getElementById('visit-mix-bar').setAttribute('aria-label',
        `Walk-in ${share(data.walkin)} มาตามนัด ${share(data.oapp_success)} ของทั้งหมด ${fmtN(total)} ครั้ง`);

    renderDeptChart(data.depts || []);
    renderApptTimeChart(data.appt_times || []);
    renderNationChart(data.nations || []);
}

// สัญชาติ (patient.nationality) — 6 อันดับแรก ที่เหลือรวมเป็น "อื่นๆ"
function renderNationChart(nations) {

    const total = nations.reduce((s, r) => s + r.total, 0);
    const thai = nations.filter(r => r.name === 'ไทย').reduce((s, r) => s + r.total, 0);
    const pct = n => total ? (n * 100 / total).toFixed(1) : '0';
    setText('nation-note', total
        ? `ไทย ${pct(thai)}% · ไม่ใช่ไทย ${fmtN(total - thai)} ครั้ง (${pct(total - thai)}%)`
        : '—');

    // ถ้าเกินมาแค่ 1 กลุ่ม แสดงชื่อจริงไปเลย ไม่ต้องรวมเป็น "อื่นๆ"
    const TOP = nations.length <= 7 ? 7 : 6;
    const points = nations.slice(0, TOP).map(r => ({
        name: r.name, y: r.total, people: r.people, drill: ['nation', r.name],
        color: r.name === 'ไม่ระบุ' ? OTHER_COLOR : gBar()
    }));
    const rest = nations.slice(TOP);
    if (rest.length) {
        points.push({
            name: `อื่นๆ (${rest.length} สัญชาติ)`,
            y: rest.reduce((s, r) => s + r.total, 0),
            people: rest.reduce((s, r) => s + r.people, 0),
            detail: rest.map(r => `${r.name} ${fmtN(r.total)}`).join('<br>'),
            color: OTHER_COLOR
        });
    }

    if (!points.length) {
        document.getElementById('opd-nation-chart').innerHTML = '<p class="text-muted text-center py-5">ไม่มีผู้มารับบริการในวันที่เลือก</p>';
        return;
    }

    Highcharts.chart('opd-nation-chart', {
        chart: { type: 'bar', height: Math.max(260, points.length * 40 + 80), style: { fontFamily: 'THSarabun' } },
        title: { text: null },
        xAxis: { type: 'category', labels: { style: BAR_LABEL_STYLE } },
        yAxis: { allowDecimals: false, maxPadding: 0.45, title: { text: 'จำนวน (ครั้ง)' } },
        tooltip: {
            pointFormatter() {
                return `<b>${fmtN(this.y)} ครั้ง</b> (${fmtN(this.people)} คน)` + (this.detail ? `<br>${this.detail}` : '');
            }
        },
        plotOptions: {
            bar: {
                borderRadius: 4,
                minPointLength: 6,   // แท่งที่มีแค่ 1–2 ครั้งยังเห็นสีประจำสัญชาติ
                ...DRILL_BAR,
                dataLabels: { ...BAR_DATA_LABELS, formatter() { return `${fmtN(this.y)} (${pct(this.y)}%)`; } }
            }
        },
        credits: { enabled: false },
        legend: { enabled: false },
        series: [{ name: 'สัญชาติ', data: points }]
    });
}

// A4: 10 แผนกที่ลงทะเบียนมากสุด ที่เหลือรวมเป็น "อื่นๆ"
function renderDeptChart(depts) {

    const TOP = 10;
    const points = depts.slice(0, TOP).map(d => ({ name: d.name.replace(/^\d+\s*/, ''), y: d.total, drill: ['dept', d.name] }));
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
        plotOptions: { bar: { borderRadius: 4, ...DRILL_BAR, dataLabels: { ...BAR_DATA_LABELS } } },
        credits: { enabled: false },
        legend: { enabled: false },
        series: [{ name: 'ผู้มารับบริการ', color: gBar(), data: points }]
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
        chart: { type: 'areaspline', height: 320, style: { fontFamily: 'THSarabun' } },
        title: { text: null },
        xAxis: { categories: times.map(t => t.time), title: { text: 'เวลานัด (ช่วงละ 30 นาที)' } },
        yAxis: { allowDecimals: false, min: 0, title: { text: 'จำนวนนัด (ราย)' } },
        tooltip: { headerFormat: '<b>{point.key} น.</b><br>', pointFormat: 'นัด <b>{point.y} ราย</b><br>คลิกเพื่อดูรายชื่อ' },
        plotOptions: {
            areaspline: {
                lineWidth: 3,
                fillColor: { linearGradient: { x1: 0, y1: 0, x2: 0, y2: 1 }, stops: [[0, 'rgba(34, 137, 79, .35)'], [1, 'rgba(34, 137, 79, .02)']] },
                marker: { enabled: true, radius: 5, fillColor: '#fff', lineWidth: 2, lineColor: G.dark },
                ...DRILL_BAR,
                dataLabels: { enabled: true, color: '#000', style: { fontSize: '17px', textOutline: '2px #fff' } }
            }
        },
        credits: { enabled: false },
        legend: { enabled: false },
        // คลิกจุด = รายชื่อนัดของช่วง 30 นาทีนั้น (นับเหมือนกราฟ: เวลานัดแรกของแต่ละราย)
        series: [{ name: 'นัดหมาย', color: G.dark,
            data: times.map(t => ({ y: t.total, drill: ['appt', '', `ช่วง ${t.time}${t.time === 'ไม่ระบุ' ? '' : ' น.'}`, t.time] })) }]
    });
}

/*
============================================================
การไหลของผู้ป่วย / จุดตรวจแพทย์ / นัดรายคลินิก (api/opd_general_today.php)
และเวลารอเฉลี่ย (api/opd_service_kpi.php?scope=general|special — นิยามเดียวกับแท็บเวลารอ)
============================================================
*/

const emptyBox = (id, text) => { document.getElementById(id).innerHTML = `<p class="text-muted text-center py-5">${text}</p>`; };
const hh = h => String(h).padStart(2, '0') + ':00';

async function loadOpdGeneralExtras() {
    const date = getSelectedServiceDate();
    const isToday = date === getTodayServiceDate();
    const scope = opdScope;
    try {
        const res = await fetch('../api/opd_general_today.php?' + opdQs());
        const data = await res.json();
        if (!res.ok || data.error) throw new Error(data.error);
        if (scope !== opdScope) return;
        renderFlowChart(data.hours || []);
        renderRoomChart(data.rooms || []);
        renderClinicChart(data.clinics || [], isToday);
    } catch (e) {
        console.error('Load OPD general extras:', e);
    }
    try {
        const res = await fetch(`../api/opd_service_kpi.php?scope=${scope}&d1=${encodeURIComponent(date)}&d2=${encodeURIComponent(date)}`);
        const data = await res.json();
        if (!res.ok || data.error) throw new Error(data.error);
        if (scope !== opdScope) return;
        renderWaitList(data, isToday);
    } catch (e) {
        console.error('Load OPD wait times:', e);
        emptyBox('opd-wait-list', 'โหลดเวลารอไม่สำเร็จ');
    }
}

// คนเข้ามากกว่าคนได้พบแพทย์ในชั่วโมงไหน = คิวสะสมช่วงนั้น
function renderFlowChart(hours) {
    if (!hours.length) {
        emptyBox('opd-flow-chart', 'ไม่มีผู้มารับบริการในวันที่เลือก');
        setText('flow-note', '—');
        return;
    }
    const from = hours[0].h, to = hours[hours.length - 1].h;
    const byH = new Map(hours.map(r => [r.h, r]));
    const cats = [], arrive = [], seen = [];
    for (let h = from; h <= to; h++) {
        cats.push(hh(h));
        arrive.push(byH.get(h)?.arrive || 0);
        seen.push(byH.get(h)?.seen || 0);
    }
    const peak = arr => arr.reduce((b, v, i) => (v > arr[b] ? i : b), 0);
    const pa = peak(arrive), ps = peak(seen);
    // คลิกแท่ง = รายชื่อของชั่วโมงนั้น (มาลงทะเบียน / พบแพทย์ครั้งแรก)
    const hourPts = (arr, type) => arr.map((y, i) => ({ y, drill: y ? [type, String(from + i), `ช่วง ${cats[i]} น.`] : null }));
    setText('flow-note', `มาลงทะเบียนมากสุดช่วง ${cats[pa]} น. (${fmtN(arrive[pa])} ครั้ง) · ` +
        `พบแพทย์มากสุดช่วง ${cats[ps]} น. (${fmtN(seen[ps])} ครั้ง)`);

    Highcharts.chart('opd-flow-chart', {
        chart: { type: 'column', height: 400 },   // สูงเท่าแผงเวลารอข้าง ๆ
        title: { text: null },
        xAxis: { categories: cats, title: { text: 'ช่วงเวลา (ชั่วโมง)' } },
        yAxis: { allowDecimals: false, min: 0, title: { text: 'จำนวน (ครั้ง)' } },
        tooltip: { shared: true, headerFormat: '<b>{point.key} น.</b><br>' },
        plotOptions: { column: { borderRadius: 4, groupPadding: 0.12, pointPadding: 0.04, ...DRILL_BAR,
            dataLabels: { enabled: true, color: '#000', style: { fontSize: '15px', textOutline: 'none' } } } },
        legend: { itemStyle: { fontSize: '17px' } },
        credits: { enabled: false },
        // สองชุดเขียวต่างความเข้ม: มาลงทะเบียน = อ่อน, พบแพทย์ = เข้ม
        series: [
            { name: 'มาลงทะเบียน', color: gCol('#a9dcbd', G.light), data: hourPts(arrive, 'hour_in') },
            { name: 'พบแพทย์', color: gCol(G.main, G.deep), data: hourPts(seen, 'hour_seen') }
        ]
    });
}

function renderRoomChart(rooms) {
    if (!rooms.length) {
        emptyBox('opd-room-chart', 'ยังไม่มีบันทึกพบแพทย์ในวันที่เลือก');
        return;
    }
    const TOP = 12;
    const points = rooms.slice(0, TOP).map(r => ({ name: r.name.replace(/^\d+\s*/, ''), y: r.seen, wait: r.wait, drill: ['room', r.name] }));
    const rest = rooms.slice(TOP);
    if (rest.length) {
        points.push({ name: `จุดอื่นๆ (${rest.length} จุด)`, y: rest.reduce((s, r) => s + r.seen, 0), wait: null, color: OTHER_COLOR });
    }
    Highcharts.chart('opd-room-chart', {
        chart: { type: 'bar', height: Math.max(260, points.length * 34 + 80) },
        title: { text: null },
        xAxis: { type: 'category', labels: { style: BAR_LABEL_STYLE } },
        yAxis: { allowDecimals: false, maxPadding: 0.3, title: { text: 'จำนวนที่ตรวจ (ครั้ง)' } },
        tooltip: {
            pointFormatter() {
                return `ตรวจ <b>${fmtN(this.y)} ครั้ง</b>` + (this.wait != null ? `<br>รอแพทย์เฉลี่ย ${this.wait} นาที` : '');
            }
        },
        plotOptions: { bar: { borderRadius: 4, ...DRILL_BAR, dataLabels: { ...BAR_DATA_LABELS,
            formatter() { return fmtN(this.y) + (this.point.wait != null ? ` (${this.point.wait} นาที)` : ''); } } } },
        credits: { enabled: false },
        legend: { enabled: false },
        series: [{ name: 'จุดตรวจ', color: gBar(), data: points }]
    });
}

function renderClinicChart(clinics, isToday) {
    const missLabel = isToday ? 'ยังไม่มา' : 'ไม่มาตามนัด';
    if (!clinics.length) {
        emptyBox('opd-clinic-chart', 'ไม่มีนัดในวันที่เลือก');
        setText('clinic-note', '—');
        return;
    }
    const rows = clinics.map(c => ({ ...c, total: c.came + c.miss })).sort((a, b) => b.total - a.total);
    const TOP = 10;
    const shown = rows.slice(0, TOP);
    const rest = rows.slice(TOP);
    if (rest.length) {
        shown.push({ name: `คลินิกอื่นๆ (${rest.length} คลินิก)`, came: rest.reduce((s, c) => s + c.came, 0),
            miss: rest.reduce((s, c) => s + c.miss, 0) });
    }
    const worst = rows.filter(c => c.total >= 10).sort((a, b) => b.miss / b.total - a.miss / a.total)[0];
    setText('clinic-note', `เรียงตามจำนวนนัด · ${missLabel} = นัดที่ยังไม่มีการมารับบริการ` +
        (worst && worst.miss ? ` · ${missLabel}สัดส่วนสูงสุด: ${worst.name} ${Math.round(worst.miss * 100 / worst.total)}%` : ''));

    Highcharts.chart('opd-clinic-chart', {
        chart: { type: 'bar', height: Math.max(260, shown.length * 34 + 110) },
        title: { text: null },
        xAxis: { categories: shown.map(c => c.name), labels: { style: BAR_LABEL_STYLE } },
        yAxis: { allowDecimals: false, reversedStacks: false, title: { text: 'จำนวน (ราย)' } },
        tooltip: { shared: true },
        plotOptions: { bar: { stacking: 'normal', borderRadius: 3, borderWidth: 2, borderColor: '#fff', ...DRILL_BAR,
            dataLabels: { enabled: true, color: '#fff', style: { fontSize: '15px', textOutline: 'none' },
                filter: { property: 'y', operator: '>', value: 4 } } } },
        legend: { itemStyle: { fontSize: '17px' } },
        credits: { enabled: false },
        // คลิกแท่ง = รายชื่อมาตามนัด / ยังไม่มา ของคลินิกนั้น ("คลินิกอื่นๆ" ที่รวมหลายคลินิกไม่มีรายชื่อ)
        series: [
            { name: 'มาตามนัด', color: G.dark,
              data: shown.map((c, i) => ({ y: c.came, drill: i < TOP && c.came ? ['appt_came', c.name, c.name] : null })) },
            { name: missLabel, color: MISS_COLOR,
              data: shown.map((c, i) => ({ y: c.miss, drill: i < TOP && c.miss ? ['appt_miss', c.name, c.name] : null })) }
        ]
    });
}

function renderWaitList(data, isToday) {
    const steps = (data.steps || []).filter(s => s.avg != null);
    setText('wait-note', `ค่าเฉลี่ย${isToday ? 'วันนี้' : 'ของวันที่เลือก'} ${fmtN(data.kpi?.visits)} ครั้ง` +
        ' (ไม่รวม Admit และ Telemed/PCU) · ปรับทุก 5 นาที');
    if (!steps.length) {
        emptyBox('opd-wait-list', 'ยังไม่มีบันทึกเวลา');
        return;
    }
    document.getElementById('opd-wait-list').innerHTML = steps.map(s => {
        const over = s.avg > s.target;
        return `<div class="opd-wait-row${over ? ' over' : ''}">
            <span class="opd-wait-name">${escHtml(s.label)}</span>
            <span class="opd-wait-val">${s.avg} <small>นาที (เป้า ≤ ${s.target})</small></span>
            <div class="opd-wait-bar" role="img" aria-label="ทันเป้า ${s.ok_pct ?? 0}%"><span style="width:${s.ok_pct ?? 0}%"></span></div>
            <span class="opd-wait-sub">${over ? '▲ เกินเป้า · ' : '✓ ตามเป้า · '}ทันเป้า ${s.ok_pct ?? 0}% จาก ${fmtN(s.n)} ราย</span>
        </div>`;
    }).join('');
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

        // วันนี้: ชื่อแท็บ + ป้าย Live (ข้อมูลเรียลไทม์) · วันอื่น: ต่อท้ายด้วยวันที่
        const isLive = selected === todayString;
        if (title) {
            title.textContent = 'ผู้มารับบริการ' + SCOPE_NAMES[opdScope] + (isLive ? '' : ' วันที่ ' + formatThaiDate(selected));
        }
        const live = document.getElementById('opd-live-badge');
        if (live) live.hidden = !isLive;

        const dayText = selected === todayString ? 'วันนี้' : 'วันที่ ' + formatThaiDate(selected);
        setText('opd-day-title', 'ข้อมูลผู้มารับบริการ' + dayText);
        setText('appt-section-title', 'นัดหมาย' + dayText);

        loadOpdTodaySummary();
        loadPttypeChart();
        loadOpdGeneralExtras();
    }

    // รีเฟรชอัตโนมัติเฉพาะเมื่อดูวันนี้ — วันย้อนหลังตัวเลขไม่เปลี่ยนแล้ว
    bbhPoll(() => {
        if ((input.value || todayString) === getTodayServiceDate()) {
            loadOpdTodaySummary();
            loadPttypeChart();
            loadOpdGeneralExtras();
        }
    }, 60000);

    // ปุ่ม "ดูรายละเอียดที่แท็บเวลารอ" สลับไปแท็บนั้น
    document.querySelectorAll('[data-goto-tab]').forEach(b => b.addEventListener('click', () =>
        document.querySelector(`[data-opd-tab="${b.dataset.gotoTab}"]`)?.click()));

    // สลับตรวจโรคทั่วไป ↔ คลินิคพิเศษ: แผงเดิม โหลดข้อมูลชุดแผนกใหม่ (วันที่ที่เลือกไว้คงเดิม)
    window.addEventListener('opdTabChanged', e => {
        const tab = e.detail.tab;
        if (!SCOPE_NAMES[tab] || tab === opdScope) return;
        opdScope = tab;
        refreshDateSummary();
    });

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
            กำลังโหลดข้อมูล…
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
        3: 'background:#CD7F32;color:#1f1f1f;',
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
                <td class="text-center" style="color:#2563eb">
                    ${row.male.toLocaleString()}
                </td>
                <td class="text-center" style="color:#be185d">
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
    // กลุ่มอายุเรียงจากเด็กไปผู้สูงอายุ → เขียวอ่อนไปเข้มตามลำดับ (แทนสีจาก API)
    const AGE_GREENS = ['#7cc49b', '#3fa36a', '#22894f', '#0f5132'];
    data.forEach((d, i) => { d.color = AGE_GREENS[Math.min(i, AGE_GREENS.length - 1)]; });

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
                        ชาย: <span style="color:#2563eb">${male}</span> |
                        หญิง: <span style="color:#be185d">${female}</span>`;
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
                color: gBar(G.light, d.color),
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
                    border:1px solid var(--bbh-border);
                    box-shadow:rgba(0,0,0,0.08) 0 2px 6px;
                ">
                    <div style="font-size:19px;font-weight:600;color:#333;margin-bottom:6px">
                        <span aria-hidden="true" style="display:inline-block;width:12px;height:12px;border-radius:3px;background:${d.color};margin-right:6px"></span>${d.name}
                    </div>
                    <div style="font-size:32px;font-weight:bold;color:#333;line-height:1">
                        ${d.total.toLocaleString()}
                        <span style="font-size:18px;font-weight:normal;color:#555">
                            ครั้ง (${pct}%)
                        </span>
                    </div>
                    <div style="font-size:17px;margin-top:6px;color:#555">
                        <span style="color:#2563eb">♂ ชาย ${d.male.toLocaleString()}</span>
                        &nbsp;|&nbsp;
                        <span style="color:#be185d">♀ หญิง ${d.female.toLocaleString()}</span>
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

/*
============================================================
รายชื่อผู้ป่วยเมื่อคลิกตัวเลข / แท่งกราฟ (แท็บตรวจโรคทั่วไป)
ข้อมูลจาก api/opd_visit_list.php (ต้อง login) · เงื่อนไขเดียวกับตัวเลขบนการ์ด
หน้าต่าง/ค้นหา/ตัวกรอง/แบ่งหน้า/Export ใช้ assets/js/bbh_drill.js ร่วมกับหน้า ER
============================================================
*/

const DRILL_TITLES = {
    all: 'ผู้มารับบริการทั้งหมด', new: 'ผู้ป่วยใหม่', old: 'ผู้ป่วยเก่า', walkin: 'Walk-in',
    appt: 'นัดหมายทั้งหมด', appt_came: 'มาตามนัด', appt_miss: 'ยังไม่มา / ไม่มาตามนัด',
    night: 'ก่อนเวลาราชการ (20:01–07:59 น.)', morning: 'ในเวลาราชการ (08:00–15:59 น.)', evening: 'นอกเวลาราชการ (16:00–20:00 น.)',
    wait_triage: 'รอซักประวัติ', wait_exam: 'รอตรวจ', finish: 'ตรวจเสร็จแล้ว', no_doctor: 'ไม่มีบันทึกพบแพทย์', not_seen: 'ไม่มีบันทึกพบแพทย์',
    admit: 'Admit', refer: 'Refer ส่งต่อ',
    dept: 'แผนก', payer: 'สิทธิ์', nation: 'สัญชาติ',
    hour_in: 'มาลงทะเบียน', hour_seen: 'พบแพทย์ (ครั้งแรก)', room: 'จุดตรวจ'
};

// ตัวเลือกคอลัมน์ (bbh_drill.js): nowrap = บรรทัดเดียว · short = ตัดรหัสนำหน้า "115 " / "77- " · max = ยาวเกินตัดด้วย …
const NW = { nowrap: true };
const VISIT_COLS = [
    ['hn', 'HN', NW], ['name', 'ชื่อ-สกุล', NW], ['vsttime', 'เวลามา', NW],
    ['main_dep', 'แผนกที่ลงทะเบียน', { short: true, max: '13em' }], ['cur_dep', 'อยู่ที่', { short: true, max: '10em' }],
    ['pttype', 'สิทธิ์', { short: true, max: '13em' }], ['nation', 'สัญชาติ', NW],
    // เวลา + เวลารอรวมช่องเดียว (Export ยังแยกคอลัมน์ตามเดิม — VISIT_EXPORT)
    ['screen_at', 'ซักประวัติ', NW], ['doctor_at', 'พบแพทย์', NW]
];
const VISIT_EXPORT = [
    ['hn', 'HN'], ['name', 'ชื่อ-สกุล'], ['vsttime', 'เวลามา'], ['main_dep', 'แผนกที่ลงทะเบียน'], ['cur_dep', 'อยู่ที่'],
    ['pttype', 'สิทธิ์'], ['nation', 'สัญชาติ'], ['screen_at', 'ซักประวัติ'], ['wait_screen', 'รอซักประวัติ (นาที)'],
    ['doctor_at', 'พบแพทย์'], ['wait_doctor', 'รอแพทย์ (นาที)']
];
// Admit / Refer: คอลัมน์ "อยู่ที่" เปลี่ยนเป็นปลายทาง (api ส่ง admit_ward / refer_to) · ชื่อ รพ. ย่อเฉพาะตอนแสดง
const shortHosp = v => String(v).replace(/โรงพยาบาลส่งเสริมสุขภาพตำบล/g, 'รพ.สต.').replace(/โรงพยาบาล/g, 'รพ.');
const DEST_COL = {
    admit: ['admit_ward', 'Admit ไปที่', { max: '13em' }],
    refer: ['refer_to', 'ส่งต่อไปที่', { display: shortHosp, max: '16em' }]
};
const withDest = (list, type) => DEST_COL[type] ? list.map(c => c[0] === 'cur_dep' ? DEST_COL[type] : c) : list;

const APPT_COLS = [
    ['hn', 'HN', NW], ['name', 'ชื่อ-สกุล', NW], ['appt_time', 'เวลานัด', NW], ['clinic', 'คลินิก', { short: true, max: '13em' }],
    ['status', 'สถานะ', NW], ['vsttime', 'เวลามา', NW], ['pttype', 'สิทธิ์', { short: true, max: '13em' }], ['nation', 'สัญชาติ', NW]
];

// ตัวกรองแบบเลือกในหน้าต่างรายชื่อ (ตัวเลือกสร้างจากข้อมูลจริงของรายการนั้น)
const DRILL_FILTERS = {
    visit: [['main_dep', 'แผนกที่ลงทะเบียน'], ['cur_dep', 'อยู่ที่'], ['pttype', 'สิทธิ์'], ['nation', 'สัญชาติ'], ['ptkind', 'ผู้ป่วยใหม่/เก่า']],
    appt:  [['clinic', 'คลินิก'], ['status', 'สถานะ'], ['nation', 'สัญชาติ']]
};

const escHtml = v => String(v ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

// เซลล์พิเศษ: ป้าย "ใหม่" หลังชื่อ, สถานะนัดเป็นป้ายสี · ค่าอื่นให้ bbh_drill.js แสดงเอง (undefined)
function drillCell(r, k) {
    if (k === 'name' && r.is_new) return `${escHtml(r.name)} <span class="opd-drill-tag">ใหม่</span>`;
    if (k === 'status') {
        const cls = r.status === 'มาตามนัด' ? 'came' : r.status === 'ยกเลิกนัด' ? 'cancel' : 'miss';
        return `<span class="opd-drill-status ${cls}">${escHtml(r.status)}</span>`;
    }
    // ซักประวัติ / พบแพทย์: เวลา + "รอ n น." ต่อท้าย (ค่ารอติดลบ/ว่างไม่แสดง)
    const waitKey = { screen_at: 'wait_screen', doctor_at: 'wait_doctor' }[k];
    if (waitKey && r[k]) {
        const w = r[waitKey];
        return escHtml(r[k]) + (w !== null && w !== undefined && w >= 0 ? `<span class="bbh-wait">รอ ${fmtN(w)} น.</span>` : '');
    }
    return undefined;
}

// key = ค่าของแท่งที่คลิก (แผนก/สิทธิ์/สัญชาติ/ชั่วโมง/จุดตรวจ/คลินิก) · slot = ช่วงเวลานัด 30 นาที (กราฟการกระจายเวลานัด)
function openDrill(type, key, label, slot) {

    const date = getSelectedServiceDate();
    const isToday = date === getTodayServiceDate();
    // วันที่ผ่านมาแล้วการ์ดรอซักประวัติ/รอตรวจแสดง "—" จึงไม่เปิดรายชื่อ
    if (!isToday && (type === 'wait_triage' || type === 'wait_exam')) return;
    if (!isToday && type === 'no_doctor') type = 'not_seen';

    const qs = new URLSearchParams({ date, type, scope: opdScope });
    if (key) qs.set('key', key);
    if (slot) qs.set('slot', slot);

    bbhDrill.open({
        title: key || slot ? `${DRILL_TITLES[type]}: ${label || key || slot}` : DRILL_TITLES[type],
        sub: SCOPE_NAMES[opdScope] + ' · วันที่ ' + formatThaiDate(date),
        url: '../api/opd_visit_list.php?' + qs,
        placeholder: 'ค้นหา HN / ชื่อ / แผนก / สิทธิ์',
        cols: data => data.kind === 'appt' ? APPT_COLS : withDest(VISIT_COLS, type),
        exportCols: data => data.kind === 'appt' ? APPT_COLS : withDest(VISIT_EXPORT, type),
        filters: data => data.kind === 'appt' ? DRILL_FILTERS.appt : withDest(DRILL_FILTERS.visit, type),
        mapRows: data => data.rows.map(r => ({ ...r, ptkind: r.is_new ? 'ผู้ป่วยใหม่' : 'ผู้ป่วยเก่า' })),
        cell: drillCell,
        file: `OPD_${type}${key ? '_' + key.replace(/[^\wก-๙]+/g, '-') : ''}_${date}.csv`,
        summary: (rows, data) =>
            `${fmtN(rows.length)} ${data.kind === 'appt' ? 'ราย' : 'ครั้ง'} (${fmtN(new Set(rows.map(r => r.hn)).size)} คน)`
    });
}

// ตัวเลข/การ์ดที่คลิกได้ในแท็บตรวจโรคทั่วไป (แท่งกราฟเรียก openDrill ผ่าน DRILL_BAR)
const opdGeneralTab = document.getElementById('opd-tab-general');
if (opdGeneralTab) bbhDrill.bind(opdGeneralTab, type => openDrill(type));
