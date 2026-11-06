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
setInterval(loadDashboard, 30000);

/*
============================================================
  Heatmap — ภาระงานแต่ละแผนกตามช่วงเวลา
============================================================
*/

async function loadHeatmap() {

    try {

        const res  = await fetch('api/index_dept_heatmap.php');
        const json = await res.json();

        if (json.error) {
            console.error('Heatmap error:', json.error);
            return;
        }

        const { depts, hours, data } = json;

        // ค่ามากสุด (ไว้คำนวณสีตัวเลข + colorAxis)
        const maxVal = data.reduce((m, d) => Math.max(m, d[2]), 0);

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

        /*
        ==========================
        *** ความสูง chart ปรับตามจำนวนแผนก ***
        ให้แต่ละแถวสูงพออ่าน label/ตัวเลขได้
        ==========================
        */
        const ROW_H        = 25;                                   // px ต่อแผนก
        const chartHeight  = Math.max(420, depts.length * ROW_H + 110);
        const cleanLabels  = depts.map(d => d.replace(/^\d+\s*/, ''));

        const chartEl = document.getElementById('heatmap-chart');
        chartEl.style.height = chartHeight + 'px';

        /*
        ==========================
        Highcharts Heatmap
        ==========================
        */

        Highcharts.chart('heatmap-chart', {

            chart: {
                type: 'heatmap',
                height: chartHeight,
                backgroundColor: '#ffffff',
                marginTop: 50,
                marginBottom: 60,
                marginRight: 90,
                style: { fontFamily: 'THSarabun' }
            },

            title: { text: null },

            xAxis: {
                categories: hours,
                opposite: true,                 // ป้ายชั่วโมงไว้ด้านบน อ่านคู่กับ label แผนกง่ายกว่า
                title: { text: null },
                lineWidth: 0,
                tickWidth: 0,
                labels: {
                    style: { fontSize: '14px', color: '#555' }
                }
            },

            yAxis: {
                categories: cleanLabels,
                title: null,
                reversed: true,                 // แผนกยุ่งสุดอยู่บนสุด อ่านเป็นอันดับ
                gridLineWidth: 0,
                labels: {
                    style: {
                        fontSize: '14px',
                        fontFamily: 'THSarabun',
                        color: '#333'
                    }
                }
            },

            /*
            ==========================
            *** color scale แบบ stops ***
            ไล่จากครีมอ่อน → ส้ม → แดงเข้ม
            ค่าน้อยก็เห็นความต่างชัด ไม่จมขาว
            ==========================
            */
            colorAxis: {
                min: 0,
                max: maxVal || null,
                stops: [
                    [0.00, '#ffebeb'],
                    [0.15, '#fda2a2'],
                    [0.40, '#fd3c3c'],
                    [0.70, '#e60d0d'],
                    [1.00, '#a60303']
                ],
                labels: { style: { fontSize: '12px' } }
            },

            legend: {
                align: 'right',
                layout: 'vertical',
                verticalAlign: 'middle',
                margin: 8,
                symbolHeight: Math.min(chartHeight - 140, 320)
            },

            tooltip: {
                useHTML: true,
                formatter: function () {
                    const dept = cleanLabels[this.point.y] || '';
                    const hour = hours[this.point.x] || '';
                    return `<b>${dept}</b><br>${hour} น. — ` +
                           `<b style="color:#e6550d">${this.point.value} ราย</b>`;
                }
            },

            credits: { enabled: false },

            series: [{
                name: 'จำนวนผู้ป่วย',
                borderWidth: 1,
                borderColor: '#ffffff',
                nullColor: '#fafafa',
                data: data,
                dataLabels: {
                    enabled: true,
                    useHTML: true,
                    formatter: function () {
                        const v = this.point.value;
                        if (!v) return '';
                        // ตัวเลขสีขาวบนเซลล์เข้ม / สีน้ำตาลเข้มบนเซลล์อ่อน
                        const ratio = maxVal ? v / maxVal : 0;
                        const color = ratio > 0.5 ? '#ffffff' : '#7a3a12';
                        return `<span style="color:${color};font-weight:600;">${v}</span>`;
                    },
                    style: {
                        fontSize: '12.5px',
                        textOutline: 'none'
                    }
                }
            }],

            responsive: {
                rules: [{
                    condition: { maxWidth: 600 },
                    chartOptions: {
                        yAxis: {
                            labels: { style: { fontSize: '12px' } }
                        },
                        xAxis: {
                            labels: { style: { fontSize: '11px' } }
                        }
                    }
                }]
            }

        });

    } catch (error) {
        console.error('loadHeatmap error:', error);
    }

}

loadHeatmap();
setInterval(loadHeatmap, 3 * 60 * 1000); // refresh ทุก 3 นาที