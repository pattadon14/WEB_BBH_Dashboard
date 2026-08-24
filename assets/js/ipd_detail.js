async function loadChart() {

    const year =
        document.getElementById(
            'budget-year'
        ).value;

    /*
    ==========================
    วันที่ปีงบประมาณ
    ==========================
    */

    const startDateText =
        `1 ตุลาคม ${year - 1}`;

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

    document.getElementById(
        'budget-date-text'
    ).innerHTML =

        `ปีงบประมาณ ${year}
        (${startDateText} - ${endDateText})`;

    /*
    ==========================
    โหลดข้อมูลผู้ป่วยใน
    ==========================
    */

    const response =
        await fetch(
            '../api/service_trend.php?year=' + year
        );

    const data =
        await response.json();

    /*
    ==========================
    โหลดข้อมูลอัตราครองเตียง
    ==========================
    */

    const bedResponse =
        await fetch(
            '../api/bed_occupancy_trend.php?year=' + year
        );

    const bedData =
        await bedResponse.json();

    /*
    ==========================
    เดือน
    ==========================
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

    let months = allMonths;

    let monthCount = 12;

    if (parseInt(year) === currentThaiYear) {

        const currentMonth =
            new Date().getMonth() + 1;

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
    ==========================
    IPD DATA
    ==========================
    */

    let ipdData =
        Array(monthCount).fill(0);

    data.forEach(row => {

        const month =
            parseInt(row.month);

        const index =
            month >= 10
                ? month - 10
                : month + 2;

        if (index < monthCount) {

            ipdData[index] =
                parseInt(row.ipd);
        }

    });

    /*
    ==========================
    BED OCCUPANCY DATA
    ==========================
    */

    let occupancyData =
        Array(monthCount).fill(0);

    bedData.data.forEach(row => {

        const month =
            parseInt(row.month);

        const index =
            month >= 10
                ? month - 10
                : month + 2;

        if (index < monthCount) {

            occupancyData[index] =
                parseFloat(
                    row.occupancy_rate
                );
        }

    });

    /*
    ==========================
    กราฟผู้ป่วยใน
    ==========================
    */

    Highcharts.chart('ipd-chart', {

        chart: {
            type: 'column',
            backgroundColor: '#ffffff',
            style: {
                fontFamily: 'THSarabun'
            }
        },

        title: {
            text: 'จำนวนผู้รับบริการ ปีงบประมาณ ' + year
        },

        xAxis: {
            categories: months
        },

        yAxis: {

            title: {
                text: 'จำนวน'
            },

            tickInterval: 250
        },

        credits: {
            enabled: false
        },

        series: [

            {

                name: 'ผู้ป่วยใน',

                color: '#4aafbd',

                data: ipdData,

                dataLabels: {

                    enabled: true,

                    color: '#333333',

                    style: {
                        fontSize: '15px',
                        fontWeight: 'bold',
                        textOutline: 'none'
                    }
                }

            }

        ]

    });

    /*
    ==========================
    กราฟอัตราครองเตียง
    ==========================
    */

    Highcharts.chart('bed-occupancy-chart', {

        chart: {
            zoomType: 'xy',
            backgroundColor: '#ffffff',
            style: {
                fontFamily: 'THSarabun'
            }
        },

        title: {
            text: 'อัตราการครองเตียง ปีงบประมาณ ' + year
        },

        xAxis: {
            categories: months
        },

        yAxis: [{
            min: 0,
            max: 100,
            tickInterval: 20,

            title: {
                text: 'อัตราการครองเตียง (%)'
            },

            labels: {
                format: '{value}%'
            }
        },
        {
            min: 0,
            max: 100,
            tickInterval: 20,

            title: {
                text: 'แนวโน้ม'
            },

            opposite: true,

            labels: {
                format: '{value}%'
            }
        }],

        tooltip: {

            shared: true,

            valueSuffix: '%'
        },

        credits: {
            enabled: false
        },

        series: [

            {

                type: 'column',

                name: 'อัตราครองเตียง',

                color: '#4aafbd',

                data: occupancyData,

                dataLabels: {

                    enabled: true,

                    format: '{y:.1f}%',

                    style: {
                        fontSize: '15px',
                        fontWeight: 'bold',
                        textOutline: 'none',
                        color: '#333333'
                    }
                }

            },

            {

                type: 'spline',

                name: 'แนวโน้ม',

                color: '#dc3545',

                yAxis: 1,

                data: occupancyData,

                marker: {

                    enabled: true,

                    radius: 4
                }

            }

        ]

    });

}
async function reloadAll() {

    await loadChart();

    await loadTop10Disease();

}

reloadAll();

document.getElementById('budget-year')
    .addEventListener('change', reloadAll);

async function loadTop10Disease() {

    try {

        const year =
            document.getElementById('budget-year').value;

        const response =
            await fetch(
                `../api/ipd_top10_disease.php?year=${year}`
            );

        const data =
            await response.json();

        console.log('TOP10 IPD', data);

        const rankColor = {
            1: 'background:#FFD700;color:#333;',
            2: 'background:#C0C0C0;color:#333;',
            3: 'background:#CD7F32;color:#fff;',
        };

        let html = '';

        data.forEach((row, index) => {

            const rank      = index + 1;
            const badgeStyle = rankColor[rank]
                ? rankColor[rank]
                : 'background:#198754;color:#fff;';

            const diagName = row.tname
                ? row.tname
                : (row.diag ?? '—');

            html += `
                <tr>
                    <td class="text-center">
                        <span class="rank-badge" style="${badgeStyle}">
                            ${rank}
                        </span>
                    </td>
                    <td class="text-center">
                        <span class="badge bg-secondary"
                              style="font-size:14px;letter-spacing:1px">
                            ${row.pdx ?? '—'}
                        </span>
                    </td>
                    <td>${row.diag ?? '—'}</td>
                    <td class="text-muted" style="font-size:15px">
                        ${diagName}
                    </td>
                    <td class="text-center" style="color:#3b82f6">
                        ${Number(row.male || 0).toLocaleString()}
                    </td>
                    <td class="text-center" style="color:#ec4899">
                        ${Number(row.female || 0).toLocaleString()}
                    </td>
                    <td class="text-center fw-bold text-success">
                        ${Number(row.total_case || 0).toLocaleString()}
                    </td>
                </tr>
            `;
        });

        document.getElementById('top10-ipd-body').innerHTML = html;

    } catch (err) {

        console.error('Top10 Disease Error', err);

        document.getElementById('top10-ipd-body').innerHTML = `
            <tr>
                <td colspan="7" class="text-center text-danger">
                    ไม่สามารถโหลดข้อมูลได้
                </td>
            </tr>
        `;
    }
}