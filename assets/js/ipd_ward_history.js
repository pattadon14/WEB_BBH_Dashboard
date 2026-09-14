/* =========================================================
   BBH DASHBOARD - IPD WARD HISTORICAL STATISTICS
   กราฟสถิติย้อนหลังรายเดือนตามปีงบประมาณของ Ward ปัจจุบัน
========================================================= */
(function () {
    'use strict';

    var MONTHS = ['ต.ค.', 'พ.ย.', 'ธ.ค.', 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.'];

    function getBasePath() {
        var path = window.location.pathname;
        return path.indexOf('/pages/') !== -1 ? path.split('/pages/')[0] : path.replace(/\/[^/]*$/, '');
    }

    function getWard() {
        return new URLSearchParams(window.location.search).get('ward') || '';
    }

    function currentFiscalYear() {
        var now = new Date();
        var thaiYear = now.getFullYear() + 543;
        return now.getMonth() + 1 >= 10 ? thaiYear + 1 : thaiYear;
    }

    function injectStyles() {
        if (document.getElementById('bbh-ipd-history-style')) return;
        var style = document.createElement('style');
        style.id = 'bbh-ipd-history-style';
        style.textContent = `
            .ipd-ward-page .ipd-history-section {
                margin-top: 26px;
                padding-top: 4px;
                border-top: 1px solid #edf0f2;
            }
            .ipd-ward-page .ipd-history-head {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 15px;
                margin: 0 2px 14px;
                flex-wrap: wrap;
            }
            .ipd-ward-page .ipd-history-title {
                display: flex;
                align-items: center;
                gap: 9px;
                margin: 0;
                color: #6c6d75;
                font-size: 1.32rem;
                font-weight: 700;
            }
            .ipd-ward-page .ipd-history-title i { color: #198754; }
            .ipd-ward-page .ipd-history-year-wrap {
                display: flex;
                align-items: center;
                gap: 8px;
            }
            .ipd-ward-page .ipd-history-year-label {
                color: #66727b;
                font-size: 14px;
                font-weight: 600;
            }
            .ipd-ward-page .ipd-history-year {
                min-width: 150px;
                height: 40px;
                padding: 6px 12px;
                border: 1px solid #198754;
                border-radius: 8px;
                background: #fff;
                color: #198754;
                font-size: 15px;
                font-weight: 700;
                outline: none;
            }
            .ipd-ward-page .ipd-history-date {
                width: 100%;
                margin-top: -7px;
                margin-bottom: 13px;
                color: #71808d;
                font-size: 14px;
            }
            .ipd-ward-page .ipd-history-grid {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 18px;
            }
            .ipd-ward-page .ipd-history-card {
                min-width: 0;
                border: 1px solid #dfe7e3;
                border-radius: 12px;
                background: #fff;
                box-shadow: 0 3px 10px rgba(31,45,61,.08);
                overflow: hidden;
            }
            .ipd-ward-page .ipd-history-card-head {
                display: flex;
                align-items: center;
                gap: 8px;
                padding: 13px 16px 4px;
                color: #198754;
                font-size: 16px;
                font-weight: 700;
            }
            .ipd-ward-page .ipd-history-chart {
                width: 100%;
                height: 430px;
            }
            .ipd-ward-page .ipd-history-loading,
            .ipd-ward-page .ipd-history-error {
                height: 430px;
                display: flex;
                align-items: center;
                justify-content: center;
                color: #71808d;
                font-size: 16px;
            }
            .ipd-ward-page .ipd-history-error { color: #dc3545; }
            @media (max-width: 991px) {
                .ipd-ward-page .ipd-history-grid { grid-template-columns: 1fr; }
            }
            @media (max-width: 575px) {
                .ipd-ward-page .ipd-history-head { align-items: stretch; flex-direction: column; }
                .ipd-ward-page .ipd-history-year-wrap { width: 100%; }
                .ipd-ward-page .ipd-history-year { flex: 1; min-width: 0; }
                .ipd-ward-page .ipd-history-chart,
                .ipd-ward-page .ipd-history-loading,
                .ipd-ward-page .ipd-history-error { height: 360px; }
            }
        `;
        document.head.appendChild(style);
    }

    function createSection() {
        if (document.getElementById('ipd-history-section')) return document.getElementById('ipd-history-section');
        var mainCard = document.querySelector('.ipd-ward-page .ward-main-card');
        if (!mainCard) return null;

        var section = document.createElement('section');
        section.id = 'ipd-history-section';
        section.className = 'ipd-history-section';
        section.innerHTML = `
            <div class="ipd-history-head">
                <h3 class="ipd-history-title"><i class="fas fa-chart-column"></i>สถิติย้อนหลังของ Ward</h3>
                <div class="ipd-history-year-wrap">
                    <label class="ipd-history-year-label" for="ipd-history-year">ปีงบประมาณ</label>
                    <select id="ipd-history-year" class="ipd-history-year" aria-label="เลือกปีงบประมาณ"></select>
                </div>
            </div>
            <div id="ipd-history-date" class="ipd-history-date"></div>
            <div class="ipd-history-grid">
                <div class="ipd-history-card">
                    <div class="ipd-history-card-head"><i class="fas fa-user-injured"></i> จำนวนผู้ป่วย Admit รายเดือน</div>
                    <div id="ipd-history-admit" class="ipd-history-chart"><div class="ipd-history-loading">กำลังโหลดข้อมูล...</div></div>
                </div>
                <div class="ipd-history-card">
                    <div class="ipd-history-card-head"><i class="fas fa-bed"></i> อัตราครองเตียงรายเดือน</div>
                    <div id="ipd-history-occupancy" class="ipd-history-chart"><div class="ipd-history-loading">กำลังโหลดข้อมูล...</div></div>
                </div>
            </div>
        `;
        mainCard.insertAdjacentElement('afterend', section);
        return section;
    }

    function populateYears() {
        var select = document.getElementById('ipd-history-year');
        if (!select || select.options.length) return;
        var current = currentFiscalYear();
        for (var year = current; year >= current - 5; year--) {
            var option = document.createElement('option');
            option.value = year;
            option.textContent = 'ปีงบประมาณ ' + year;
            select.appendChild(option);
        }
        select.value = current;
    }

    function loadHighcharts(callback) {
        if (window.Highcharts) { callback(); return; }
        var existing = document.getElementById('bbh-highcharts-loader');
        if (existing) {
            existing.addEventListener('load', callback, { once: true });
            return;
        }
        var script = document.createElement('script');
        script.id = 'bbh-highcharts-loader';
        script.src = 'https://code.highcharts.com/highcharts.js';
        script.onload = callback;
        script.onerror = function () {
            var a = document.getElementById('ipd-history-admit');
            var b = document.getElementById('ipd-history-occupancy');
            if (a) a.innerHTML = '<div class="ipd-history-error">ไม่สามารถโหลด Highcharts ได้</div>';
            if (b) b.innerHTML = '<div class="ipd-history-error">ไม่สามารถโหลด Highcharts ได้</div>';
        };
        document.head.appendChild(script);
    }

    function fiscalDateText(year) {
        var current = currentFiscalYear();
        if (Number(year) === current) {
            var now = new Date();
            var months = ['มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน','กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม'];
            return 'ปีงบประมาณ ' + year + ' (1 ตุลาคม ' + (year - 1) + ' - ' + now.getDate() + ' ' + months[now.getMonth()] + ' ' + (now.getFullYear() + 543) + ')';
        }
        return 'ปีงบประมาณ ' + year + ' (1 ตุลาคม ' + (year - 1) + ' - 30 กันยายน ' + year + ')';
    }

    function renderAdmit(data, year) {
        var categories = data.map(function (r) { return r.month_name; });
        var values = data.map(function (r) { return Number(r.admit_count || 0); });
        Highcharts.chart('ipd-history-admit', {
            chart: { type: 'column', backgroundColor: '#ffffff', style: { fontFamily: 'THSarabun' } },
            title: { text: 'จำนวนผู้ป่วย Admit ปีงบประมาณ ' + year, style: { fontSize: '16px', fontWeight: '600' } },
            xAxis: { categories: categories, title: { text: null } },
            yAxis: { min: 0, title: { text: 'จำนวนผู้ป่วย' } },
            tooltip: { shared: true, valueSuffix: ' ราย' },
            plotOptions: { column: { borderRadius: 3, dataLabels: { enabled: true, style: { fontSize: '14px', fontWeight: 'bold', textOutline: 'none' } } } },
            credits: { enabled: false },
            legend: { enabled: false },
            series: [{ name: 'ผู้ป่วย Admit', data: values, color: '#4aafbd' }]
        });
    }

    function renderOccupancy(data, year) {
        var categories = data.map(function (r) { return r.month_name; });
        var values = data.map(function (r) { return Number(r.occupancy_rate || 0); });
        Highcharts.chart('ipd-history-occupancy', {
            chart: { zoomType: 'xy', backgroundColor: '#ffffff', style: { fontFamily: 'THSarabun' } },
            title: { text: 'อัตราการครองเตียง ปีงบประมาณ ' + year, style: { fontSize: '16px', fontWeight: '600' } },
            xAxis: { categories: categories },
            yAxis: [{ min: 0, max: 100, tickInterval: 20, title: { text: 'อัตราครองเตียง (%)' }, labels: { format: '{value}%' } }, { min: 0, max: 100, tickInterval: 20, title: { text: null }, opposite: true, labels: { format: '{value}%' } }],
            tooltip: { shared: true, valueSuffix: '%' },
            plotOptions: { column: { borderRadius: 3, dataLabels: { enabled: true, format: '{y:.1f}%', style: { fontSize: '13px', fontWeight: 'bold', textOutline: 'none' } } } },
            credits: { enabled: false },
            series: [
                { type: 'column', name: 'อัตราครองเตียง', data: values, color: '#4aafbd' },
                { type: 'spline', name: 'แนวโน้ม', data: values, yAxis: 1, color: '#dc3545', marker: { enabled: true, radius: 4 } }
            ]
        });
    }

    async function loadHistory() {
        var ward = getWard();
        var select = document.getElementById('ipd-history-year');
        if (!ward || !select) return;
        var year = select.value;
        var dateText = document.getElementById('ipd-history-date');
        var admitEl = document.getElementById('ipd-history-admit');
        var occupancyEl = document.getElementById('ipd-history-occupancy');
        if (dateText) dateText.textContent = fiscalDateText(year);
        if (admitEl) admitEl.innerHTML = '<div class="ipd-history-loading">กำลังโหลดข้อมูล...</div>';
        if (occupancyEl) occupancyEl.innerHTML = '<div class="ipd-history-loading">กำลังโหลดข้อมูล...</div>';

        try {
            var response = await fetch(getBasePath() + '/api/ipd_ward_history.php?ward=' + encodeURIComponent(ward) + '&fiscal_year=' + encodeURIComponent(year), { cache: 'no-store' });
            if (!response.ok) throw new Error('HTTP ' + response.status);
            var json = await response.json();
            if (json.error) throw new Error(json.error);
            var data = Array.isArray(json.data) ? json.data : [];
            if (data.length !== 12) throw new Error('ข้อมูลย้อนหลังไม่ครบ 12 เดือน');
            renderAdmit(data, year);
            renderOccupancy(data, year);
        } catch (error) {
            console.error('IPD Ward History Error:', error);
            var message = '<div class="ipd-history-error">ไม่สามารถโหลดข้อมูลสถิติย้อนหลังได้</div>';
            if (admitEl) admitEl.innerHTML = message;
            if (occupancyEl) occupancyEl.innerHTML = message;
        }
    }

    function init() {
        if (!document.querySelector('.ipd-ward-page .ward-main-card')) return;
        injectStyles();
        createSection();
        populateYears();
        var select = document.getElementById('ipd-history-year');
        if (select) select.addEventListener('change', loadHistory);
        loadHighcharts(loadHistory);
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();
