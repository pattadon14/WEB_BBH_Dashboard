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

    const currentThaiYear =
        new Date().getFullYear() + 543;

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

    let ipdData =
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

            ipdData[index] +=
                parseInt(row.ipd);
        }
    });

    Highcharts.chart('opd-chart', {

        chart: {
            type: 'column',
            style: {
                fontFamily: 'THSarabun'
            }
        },

        title: {
            text:
            'จำนวนผู้รับบริการ ปีงบประมาณ ' + year
        },

        xAxis: {
            categories: months
        },

        yAxis: {

            title: {
                text: 'จำนวน'
            },

            tickInterval: 10000

        },

        series: [

    {
        name: 'ผู้ป่วยนอก',

        color: '#198754',

        data: opdData,

        dataLabels: {
            enabled: true,
            color: '#000000',
            style: {
                fontSize: '16px',
            }
        }

    },

    {
        name: 'ผู้ป่วยใน',

        color: '#6EC1E4',

        data: ipdData,

        dataLabels: {
            enabled: true,
            color: '#000000',
            style: {
                fontSize: '16px',
            }
                }

            }

        ]

    });

}

loadChart();

async function loadPttypeChart(){

    const response =
        await fetch('../api/opd_pttype_today.php');

    const data =
        await response.json();

    const categories = [];
    const totals = [];

    data.forEach(row => {

        categories.push(
            row.pttype_name
        );

        totals.push(
            parseInt(row.total_patient)
        );

    });

    Highcharts.chart('pttype-chart', {

        chart: {
            type: 'column',
            height: 420,
            style: {
                fontFamily: 'THSarabun'
            }
        },

        title: {
            text: null
        },

        xAxis: {

            categories: categories,

            labels: {

                rotation: -90,

                style: {
                    fontSize: '14px'
                }
            }
        },

        yAxis: {

            title: {
                text: 'จำนวนผู้รับบริการ'
            }

        },

        tooltip: {

            pointFormat:
                '<b>{point.y} คน</b>'
        },

        plotOptions: {

            column: {

                borderRadius: 4,

                dataLabels: {

                    enabled: true,

                    color: '#000',

                    style: {
                        fontSize: '14px'
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

        series: [

            {

                name: 'จำนวนผู้ป่วย',

                color: '#198754',

                data: totals

            }

        ]

    });

}
    
loadPttypeChart();
setInterval(loadPttypeChart, 60000);

async function loadOpdTodaySummary() {

    try {

        const response =
            await fetch(
                '../api/opd_visit_today.php'
            );

        const data =
            await response.json();

        console.log(data);

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

        document.getElementById(
            'wait_triage'
        ).innerHTML =

            Number(
                data.wait_triage || 0
            ).toLocaleString();

        document.getElementById(
            'wait_exam'
        ).innerHTML =

            Number(
                data.wait_exam
            ).toLocaleString();

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

    } catch(error) {

        console.error(
            'Load OPD Summary Error:',
            error
        );

    }

}

loadOpdTodaySummary();

setInterval(
    loadOpdTodaySummary,
    60000
);

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
        <td colspan="7" class="text-center text-muted py-4">
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
                <td colspan="7" class="text-center text-danger py-3">
                    เกิดข้อผิดพลาด: ${data.error}
                </td>
            </tr>`;
        return;
    }

    if (!data.length) {
        tbody.innerHTML = `
            <tr>
                <td colspan="7" class="text-center text-muted py-3">
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

        const diagName = row.diag_th
            ? row.diag_th
            : (row.diag_en || '—');

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
                <td>${diagName}</td>
                <td class="text-muted" style="font-size:15px">
                    ${row.diag_en || '—'}
                </td>
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
            <td colspan="7" class="text-center text-danger py-3">
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
            style: { fontSize: '16px' }
        },

        xAxis: {
            categories: data.map(d => d.name),
            labels: {
                style: {
                    fontSize: '15px',
                    fontFamily: 'THSarabun',
                    fontWeight: 'normal'
                }
            }
        },

        yAxis: {
            min: 0,
            title: { text: 'จำนวนผู้ป่วย (ราย)' },
            labels: { style: { fontSize: '13px' } }
        },

        tooltip: {
            formatter: function () {
                const row    = data.find(d => d.name === this.x);
                const pct    = total > 0 ? ((this.y / total) * 100).toFixed(2) : '0.00';
                const male   = row ? row.male.toLocaleString()   : 0;
                const female = row ? row.female.toLocaleString() : 0;
                return `<b>${this.x}</b><br/>
                        รวม: <b>${this.y.toLocaleString()} ราย</b> (${pct}%)<br/>
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
                        return `${this.y.toLocaleString()} ราย (${pct}%)`;
                    },
                    style: {
                        fontSize: '14px',
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
                    <div style="font-size:15px;font-weight:500;color:#333;margin-bottom:6px">
                        ${d.name}
                    </div>
                    <div style="font-size:26px;font-weight:bold;color:${d.color};line-height:1">
                        ${d.total.toLocaleString()}
                        <span style="font-size:15px;font-weight:normal;color:#888">
                            ราย (${pct}%)
                        </span>
                    </div>
                    <div style="font-size:14px;margin-top:6px;color:#666">
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