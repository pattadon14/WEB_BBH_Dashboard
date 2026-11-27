/*
============================================================
  dashboard.js
  - loadCard()       : โหลด KPI การ์ด OPD/IPD/ER
  - loadDashboard()  : เรียก loadCard ทุกการ์ด
  - loadHeatmap()    : โหลด Heatmap แผนก × ชั่วโมง
============================================================
*/

async function loadCard(config) {

    try {

        const response = await fetch(config.api);
        const data     = await response.json();

        if (config.today) {
            document.getElementById(config.today).innerHTML =
                Number(data.today).toLocaleString();
        }

        if (config.monthPatient) {
            document.getElementById(config.monthPatient).innerHTML =
                Number(data.month_patient).toLocaleString();
        }

        if (config.monthVisit) {
            document.getElementById(config.monthVisit).innerHTML =
                Number(data.month_visit).toLocaleString();
        }

        // เทียบวันเดียวกันของสัปดาห์ก่อน ถึงเวลาเดียวกัน — ลูกศรมากขึ้นสีแดง / น้อยลงสีเขียว (ตามที่ผู้ใช้กำหนด) มีข้อความกำกับเสมอ
        const trend = document.getElementById(config.today.replace('-total', '-trend'));
        if (trend && data.prev_week !== undefined) {
            const now = Number(data.today), prev = Number(data.prev_week), diff = now - prev;
            const pct = prev ? Math.round(diff * 100 / prev) : null;
            // ป้ายผลต่างอยู่บนบรรทัดตัวเลข (ลูกศร + คำ "มากกว่า/น้อยกว่า" ไม่ใช้สีอย่างเดียว) · บรรทัดล่าง = ยอดสัปดาห์ก่อน
            trend.innerHTML = (diff === 0 ? '<span class="kpi-chip">เท่าเดิม</span>'
                    : `<span class="kpi-chip ${diff > 0 ? 'is-up' : 'is-down'}"><i class="fa-solid fa-arrow-${diff > 0 ? 'up' : 'down'}" aria-hidden="true"></i>${diff > 0 ? 'มากกว่า' : 'น้อยกว่า'} ${Math.abs(diff).toLocaleString()}${pct === null ? '' : ` (${Math.abs(pct)}%)`}</span>`)
                + `<span class="kpi-prev">เปรียบเทียบสัปดาห์ก่อน ${prev.toLocaleString()}</span>`;
        }

    } catch (error) {
        console.error(error);
    }

}

function loadDashboard() {

    loadCard({
        api: 'api/opd.php',
        today: 'opd-total',
        monthPatient: 'opd-month-patient',
        monthVisit: 'opd-month-visit'
    });

    loadCard({
        api: 'api/ipd.php',
        today: 'ipd-total',
        monthPatient: 'ipd-month-patient',
        monthVisit: 'ipd-month-visit'
    });

    loadCard({
        api: 'api/er.php',
        today: 'er-total',
        monthPatient: 'er-month-patient',
        monthVisit: 'er-month-visit'
    });

}

loadDashboard();
bbhPoll(loadDashboard, 60000); // API มี cache 60 วินาที รีเฟรชถี่กว่านี้ไม่ได้ข้อมูลใหม่

/*
============================================================
  Heatmap — ภาระงานแต่ละแผนกตามช่วงเวลา
============================================================
*/

const HEATMAP_TOP_DEPTS = 15;
let heatmapData = null;
let heatmapShowAll = false;

async function loadHeatmap() {

    try {

        const res  = await fetch('api/index_dept_heatmap.php');
        const json = await res.json();

        if (json.error) {
            console.error('Heatmap error:', json.error);
            return;
        }

        const { depts, hours, data } = json;

        heatmapData = json;

        /*
        ==========================
        peak hour  (x = index ใน hours แล้ว)
        ==========================
        */
        const hourTotals = Array(hours.length).fill(0);
        data.forEach(([h, , v]) => { hourTotals[h] += v; });
        const peakIdx = hourTotals.indexOf(Math.max(...hourTotals));
        document.getElementById('hm-peak-hour').textContent =
            (hours[peakIdx] || '—') + ' น.';

        /*
        ==========================
        peak dept
        ==========================
        */
        const deptTotals = Array(depts.length).fill(0);
        data.forEach(([, d, v]) => { deptTotals[d] += v; });
        const peakD = deptTotals.indexOf(Math.max(...deptTotals));
        document.getElementById('hm-peak-dept').textContent =
            depts[peakD]?.replace(/^\d+\s*/, '') || '—';

        renderHeatmap();

    } catch (error) {
        console.error('loadHeatmap error:', error);
    }

}

// เลือกตัวเลขขาวหรือเข้มตาม contrast กับสีจริงของเซลล์
function heatmapTextColor(fill) {
    const [r, g, b] = Highcharts.color(fill).rgba.slice(0, 3).map(v => {
        const c = v / 255;
        return c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4;
    });
    const lum = 0.2126 * r + 0.7152 * g + 0.0722 * b;
    // โปร่งเล็กน้อยให้ตัวเลขจางลง (ยังอ่านออก: ขาว .85 บนช่องเข้ม / เทาเข้ม .72 บนช่องอ่อน)
    return 1.05 / (lum + 0.05) > (lum + 0.05) / 0.073 ? 'rgba(255,255,255,.85)' : 'rgba(31,41,55,.72)';
}

function renderHeatmap() {

    const { depts, hours, data } = heatmapData;
    const chartEl = document.getElementById('heatmap-chart');
    const isNarrow = chartEl.clientWidth < 600;

    // API เรียงแผนกจากยอดรวมมากไปน้อย แถวบนจึงเป็นแผนกที่ยุ่งสุด
    const deptTotals = Array(depts.length).fill(0);
    data.forEach(([, d, v]) => { deptTotals[d] += v; });

    // ถ้าเหลือแผนกนอกรายการแค่แผนกเดียว แสดงเลยดีกว่ารวมเป็น "แผนกอื่นๆ"
    const canFold = depts.length > HEATMAP_TOP_DEPTS + 1;
    const shownCount = canFold && !heatmapShowAll ? HEATMAP_TOP_DEPTS : depts.length;
    const foldedCount = depts.length - shownCount;

    const rowLabels = depts.slice(0, shownCount).map(d => d.replace(/^\d+\s*/, ''));
    const rowTotals = deptTotals.slice(0, shownCount);
    if (foldedCount) {
        rowLabels.push(`แผนกอื่นๆ (${foldedCount} แผนก)`);
        rowTotals.push(deptTotals.slice(shownCount).reduce((a, b) => a + b, 0));
    }

    // แถว LAB อยู่บนสุด: ผู้ป่วยถูกนับที่แผนกลงทะเบียนแล้ว จึงไม่รวมในชั่วโมง/แผนกพีค
    const lab = heatmapData.lab || [];
    const labMax = lab.reduce((m, [, v]) => Math.max(m, v), 0);
    const top = lab.length ? 1 : 0;
    if (top) {
        rowLabels.unshift('ห้อง LAB');
        rowTotals.unshift(lab.reduce((s, [, v]) => s + v, 0));
    }

    const grid = rowLabels.map(() => Array(hours.length).fill(null));
    data.forEach(([x, d, v]) => {
        const y = Math.min(d, shownCount) + top;
        grid[y][x] = (grid[y][x] || 0) + v;
    });
    lab.forEach(([x, v]) => { grid[0][x] = v; });

    // แถวผลรวม "แผนกอื่นๆ" ใช้สีเทา เพราะเทียบกับแผนกเดี่ยวบนสเกลสีเดียวกันไม่ได้
    const points = [];
    grid.forEach((row, y) => row.forEach((value, x) => {
        const point = { x, y, value };
        if (foldedCount && y === shownCount + top && value !== null) {
            point.color = document.documentElement.dataset.theme === 'dark' ? '#3a4640' : '#dfe3e8';
        }
        points.push(point);
    }));

    // สเกลสีรวมค่า LAB ด้วย ให้ทุกแถวเทียบสีกันได้
    const maxVal = data.reduce((m, d) => Math.max(m, d[2]), labMax);
    const rowHeight = heatmapShowAll ? 28 : 32;
    const chartHeight = rowLabels.length * rowHeight + 100;
    chartEl.style.height = chartHeight + 'px';

    const toggle = document.getElementById('hm-toggle');
    toggle.hidden = !canFold;
    toggle.textContent = heatmapShowAll
        ? `แสดงเฉพาะ ${HEATMAP_TOP_DEPTS} แผนกแรก`
        : `แสดงทั้งหมด ${depts.length} แผนก`;
    toggle.setAttribute('aria-expanded', String(heatmapShowAll));

    // โหมดมืด: พื้นหลัง/ช่องว่าง/เส้นขอบต้องตามพื้นการ์ด (scrollablePlotArea ใช้ backgroundColor ทึบเป็นแผ่นบังด้านซ้าย)
    const dark = document.documentElement.dataset.theme === 'dark';
    const ink = dark ? '#e4ece7' : '#1f2937';
    const muted = dark ? '#9bada4' : '#56665e';   // = --muted ของ home.css (ป้ายแกนจางกว่าตัวเลขในช่อง)
    const surface = dark ? '#141b18' : '#f3f7f5';   // ใช้เฉพาะตอนเลื่อน: ใกล้สีกระจกของการ์ด
    const axisText = { fontSize: isNarrow ? '12px' : '13px', color: muted, fontFamily: 'inherit', fontWeight: '500' };   // ฟอนต์ตามหน้าแรก (home.css: Prompt)
    // ป้ายเวลา: ช่องกว้าง ≥ 48px แสดง "07:00" · แคบกว่านั้นแสดง "07" (ไม่ถูกตัด) · แคบกว่า 24px จึงเลื่อนแนวนอน
    // พื้นโปร่ง (กระจกของการ์ด) — ยกเว้นตอนเลื่อน: scrollablePlotArea ต้องใช้พื้นทึบเป็นแผ่นบังใต้ชื่อแผนกที่ตรึงไว้
    const labelW = isNarrow ? 130 : 270;
    const cellW = (chartEl.clientWidth - labelW) / hours.length;
    const shortHour = cellW < 48;
    const scrolls = cellW < 24;

    Highcharts.chart('heatmap-chart', {

        chart: {
            type: 'heatmap',
            height: chartHeight,
            backgroundColor: scrolls ? surface : 'transparent',
            marginRight: isNarrow ? undefined : 64,   // พื้นที่คอลัมน์ "รวม"
            style: { fontFamily: 'inherit' },
            // จอแคบหรือวันที่มีหลายชั่วโมง: เลื่อนแนวนอน ชื่อแผนกอยู่กับที่ และเริ่มที่ชั่วโมงล่าสุด
            scrollablePlotArea: scrolls ? { minWidth: hours.length * 24 + labelW, scrollPositionX: 1, opacity: 1 } : undefined
        },

        title: { text: null },

        xAxis: {
            categories: hours,
            opposite: true,
            title: { text: null },
            lineWidth: 0,
            tickWidth: 0,
            labels: { rotation: 0, autoRotation: false, style: axisText, formatter: function () { return shortHour ? String(this.value).slice(0, 2) : this.value; } }
        },

        yAxis: [{
            categories: rowLabels,
            title: null,
            reversed: true,
            gridLineWidth: 0,
            labels: {
                style: { ...axisText, color: ink, width: (isNarrow ? 115 : 200) + 'px', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }
            }
        // จอแคบไม่มีที่พอสำหรับคอลัมน์รวมที่ตรึงด้านขวา
        }].concat(isNarrow ? [] : [{
            linkedTo: 0,
            opposite: true,
            reversed: true,
            gridLineWidth: 0,
            title: { text: 'รวม', rotation: 0, align: 'high', textAlign: 'center', offset: 30, y: -22, style: axisText },
            labels: {
                align: 'center',
                x: 30,
                reserveSpace: false,
                formatter: function () { return rowTotals[this.pos] ?? ''; },
                style: { ...axisText, color: ink, fontWeight: '500' }
            }
        }]),

        // ส่วนใหญ่ของเซลล์มีค่าน้อย จึงเร่งสีช่วงต้นสเกลให้เซลล์ค่าน้อยไม่จาง
        colorAxis: {
            min: 0,
            max: maxVal || 1,
            stops: [
                [0.00, '#fee0d2'],
                [0.08, '#fcae91'],
                [0.25, '#fb6a4a'],
                [0.55, '#cb181d'],
                [1.00, '#67000d']
            ],
            labels: { style: { fontSize: '12px', color: muted } }
        },

        legend: {
            align: 'right',
            verticalAlign: 'top',
            layout: 'horizontal',
            symbolWidth: isNarrow ? 150 : 240,
            symbolHeight: 12,
            margin: 6,
            title: { text: 'จำนวนผู้ป่วย (ราย)', style: { fontSize: '12px', color: muted, fontWeight: '500' } }
        },

        tooltip: {
            useHTML: true,
            formatter: function () {
                const note = top && this.point.y === 0 ? '<br><i>นับตามเวลาที่แล็ปรับสิ่งส่งตรวจ — ซ้ำกับแผนกที่ลงทะเบียน จึงไม่รวมในยอดพีค</i>' : '';
                return `<b>${rowLabels[this.point.y]}</b> (รวม ${rowTotals[this.point.y]} ราย)<br>${hours[this.point.x]} น. — <b>${this.point.value} ราย</b>${note}`;
            }
        },

        credits: { enabled: false },

        series: [{
            name: 'จำนวนผู้ป่วย',
            // ช่องห่างด้วย pointPadding (ไม่ใช้เส้นขอบสีพื้น) จึงใช้กับพื้นโปร่งได้
            borderWidth: 0,
            pointPadding: 1.5,
            borderRadius: 4,
            nullColor: dark ? 'rgba(255,255,255,.05)' : 'rgba(19,32,26,.05)',
            data: points,
            dataLabels: {
                enabled: true,
                formatter: function () {
                    if (!this.point.value) return '';
                    return `<span style="color:${heatmapTextColor(this.point.color)}">${this.point.value}</span>`;
                },
                style: { fontSize: isNarrow ? '11px' : '12px', fontWeight: '400', textOutline: 'none' }
            }
        }]

    });

}

document.getElementById('hm-toggle')?.addEventListener('click', () => {
    heatmapShowAll = !heatmapShowAll;
    renderHeatmap();
});

// สลับโหมดมืด/สว่าง: สีของ heatmap ตั้งใน JS จึงต้องวาดใหม่
new MutationObserver(() => { if (heatmapData) renderHeatmap(); })
    .observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });

loadHeatmap();
bbhPoll(loadHeatmap, 3 * 60 * 1000); // refresh ทุก 3 นาที