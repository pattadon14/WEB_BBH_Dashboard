/* =========================================================
   BBH DASHBOARD - IPD WARD HISTORICAL STATISTICS
   Popup สถิติย้อนหลังรายเดือนตามปีงบประมาณของ Ward ปัจจุบัน
========================================================= */
(function () {
    'use strict';

    var historyData = [];
    var historyYear = '';

    function getBasePath() {
        var path = window.location.pathname;
        return path.indexOf('/pages/') !== -1 ? path.split('/pages/')[0] : path.replace(/\/[^/]*$/, '');
    }

    function getWard() {
        return new URLSearchParams(window.location.search).get('ward') || '';
    }

    function currentFiscalYear() {
        var now = new Date();
        return Number(window.BBH_CURRENT_FISCAL_YEAR) ||
            (now.getFullYear() + 543 + (now.getMonth() + 1 >= 10 ? 1 : 0));
    }

    function injectStyles() {
        if (document.getElementById('bbh-ipd-history-style')) return;
        var style = document.createElement('style');
        style.id = 'bbh-ipd-history-style';
        style.textContent = `
            .ipd-ward-page .ipd-history-trigger-wrap {
                display:flex; justify-content:flex-end; align-items:center; margin:-4px 0 10px;
            }
            .ipd-ward-page .ipd-history-trigger {
                display:inline-flex; align-items:center; justify-content:center; gap:8px;
                min-width:150px; min-height:42px; padding:8px 16px;
                border:1px solid #198754; border-radius:8px; background:#fff; color:#198754;
                font-size:16px; font-weight:700; box-shadow:0 2px 7px rgba(31,45,61,.08);
                transition:background-color .18s ease, color .18s ease, box-shadow .18s ease, transform .18s ease; cursor:pointer;
            }
            .ipd-ward-page .ipd-history-trigger:hover,
            .ipd-ward-page .ipd-history-trigger:focus-visible {
                background:#198754; color:#fff; box-shadow:0 4px 12px rgba(25,135,84,.22);
                transform:translateY(-1px);
            }
            /* <dialog> เต็มจอ ทำหน้าที่เป็นพื้นหลังมืด — browser ดูแล focus ให้อยู่ในหน้าต่าง และปิดด้วย Esc */
            .bbh-history-modal-backdrop {
                position:fixed; inset:0; z-index:1050; width:100%; height:100%; max-width:none; max-height:none;
                margin:0; border:0; display:none; align-items:center; justify-content:center;
                padding:22px; background:rgba(21,34,43,.48); backdrop-filter:blur(3px); -webkit-backdrop-filter:blur(3px);
            }
            .bbh-history-modal-backdrop[open] { display:flex; }
            .bbh-history-modal-backdrop::backdrop { background:transparent; }
            .bbh-history-modal {
                width:min(1180px,96vw); max-height:92vh; display:flex; flex-direction:column; overflow:hidden;
                border:1px solid #dfe7e3; border-radius:16px; background:#fff;
                box-shadow:0 18px 55px rgba(0,0,0,.22); animation:bbhHistoryModalIn .18s ease-out;
            }
            @keyframes bbhHistoryModalIn {
                from { opacity:0; transform:translateY(10px) scale(.985); }
                to { opacity:1; transform:translateY(0) scale(1); }
            }
            .bbh-history-modal-head {
                display:flex; align-items:center; justify-content:space-between; gap:15px;
                padding:16px 20px 13px; border-bottom:1px solid #e7ece9; background:#f8faf9;
            }
            .bbh-history-modal-title { margin:0; color:#147447; font-size:21px; font-weight:700; }
            .bbh-history-modal-subtitle { margin:3px 0 0; color:#5b6773; font-size:15px; }
            .bbh-history-modal-close {
                width:44px; height:44px; flex:0 0 44px; border:1px solid #d9e1dd; border-radius:10px;
                background:#fff; color:#5b6773; font-size:18px; cursor:pointer;
                transition:border-color .15s ease, background-color .15s ease, color .15s ease;
            }
            .bbh-history-modal-close:hover,
            .bbh-history-modal-close:focus-visible { border-color:#dc3545; background:#fff5f6; color:#b02a37; }
            .bbh-history-toolbar {
                display:flex; align-items:center; justify-content:flex-end; gap:9px;
                padding:12px 20px; border-bottom:1px solid #edf1ef;
            }
            .bbh-history-year-label { color:#66727b; font-size:16px; font-weight:600; }
            .bbh-history-year {
                min-width:165px; height:40px; padding:6px 12px; border:1px solid #198754; border-radius:8px;
                background:#fff; color:#146c43; font-size:16px; font-weight:700;
            }
            .bbh-history-tabs {
                display:flex; gap:4px; padding:10px 20px 0; border-bottom:1px solid #dfe7e3; background:#fff;
            }
            .bbh-history-tab {
                border:0; border-bottom:3px solid transparent; border-radius:7px 7px 0 0;
                padding:10px 18px 9px; background:transparent; color:#66727b; font-size:16px; font-weight:700; cursor:pointer;
            }
            .bbh-history-tab:hover { background:#f4f8f6; color:#146c43; }
            .bbh-history-tab:focus-visible { outline-offset:-3px; }
            .bbh-history-tab.active { color:#146c43; border-bottom-color:#198754; background:#f8faf9; }
            .bbh-history-panel { min-height:460px; overflow:auto; padding:10px 14px 14px; }
            .bbh-history-panel[hidden] { display:none; }
            .bbh-history-chart { width:100%; height:440px; }
            .bbh-history-loading,
            .bbh-history-error,
            .bbh-history-empty {
                height:440px; display:flex; align-items:center; justify-content:center; color:#5b6773; font-size:17px;
            }
            .bbh-history-error { color:#b02a37; }
            .bbh-history-empty { flex-direction:column; gap:8px; color:#5b6773; }
            .bbh-history-empty i { font-size:30px; color:#9aa8a1; }
            body.bbh-history-modal-open { overflow:hidden; }
            @media (max-width:767px) {
                .bbh-history-modal-backdrop { padding:10px; }
                .bbh-history-modal { width:100%; max-height:95vh; border-radius:12px; }
                .bbh-history-modal-head { padding:13px 14px 11px; }
                .bbh-history-modal-title { font-size:19px; }
                .bbh-history-toolbar { justify-content:stretch; padding:10px 14px; }
                .bbh-history-year { flex:1; min-width:0; }
                .bbh-history-tabs { padding:8px 10px 0; }
                .bbh-history-tab { flex:1; padding:9px 7px; font-size:14px; }
                .bbh-history-panel { min-height:390px; padding:6px 5px 10px; }
                .bbh-history-chart { height:390px; }
                .bbh-history-loading, .bbh-history-error, .bbh-history-empty { height:390px; }
            }
            @media (max-width:575px) {
                .ipd-ward-page .ipd-history-trigger-wrap { margin-top:0; }
                .ipd-ward-page .ipd-history-trigger { min-width:145px; font-size:15px; }
                .bbh-history-year-label { font-size:15px; }
            }
        `;
        document.head.appendChild(style);
    }

    function createModal() {
        if (document.getElementById('bbh-history-modal-backdrop')) return;
        var mainCard = document.querySelector('.ipd-ward-page .ward-main-card');
        if (!mainCard) return;

        var triggerWrap = document.createElement('div');
        triggerWrap.className = 'ipd-history-trigger-wrap';
        triggerWrap.innerHTML = '<button type="button" class="ipd-history-trigger" id="ipd-history-trigger"><i class="fas fa-chart-column"></i><span>แสดงกราฟสถิติย้อนหลัง</span></button>';
        mainCard.parentNode.insertBefore(triggerWrap, mainCard);

        var backdrop = document.createElement('dialog');
        backdrop.id = 'bbh-history-modal-backdrop';
        backdrop.className = 'bbh-history-modal-backdrop';
        backdrop.setAttribute('aria-labelledby', 'bbh-history-modal-title');
        backdrop.innerHTML = `
            <div class="bbh-history-modal">
                <div class="bbh-history-modal-head">
                    <div>
                        <h3 class="bbh-history-modal-title" id="bbh-history-modal-title"><i class="fas fa-chart-column"></i> สถิติย้อนหลังของ Ward</h3>
                        <div class="bbh-history-modal-subtitle" id="bbh-history-date"></div>
                    </div>
                    <button type="button" class="bbh-history-modal-close" id="bbh-history-close" aria-label="ปิด"><i class="fas fa-times"></i></button>
                </div>
                <div class="bbh-history-toolbar">
                    <label class="bbh-history-year-label" for="bbh-history-year">ปีงบประมาณ</label>
                    <select id="bbh-history-year" class="bbh-history-year"></select>
                </div>
                <div class="bbh-history-tabs" role="tablist">
                    <button type="button" class="bbh-history-tab active" id="bbh-tab-admit" role="tab" aria-selected="true" aria-controls="bbh-panel-admit">จำนวนผู้ป่วยรายเดือน</button>
                    <button type="button" class="bbh-history-tab" id="bbh-tab-occupancy" role="tab" aria-selected="false" aria-controls="bbh-panel-occupancy" tabindex="-1">อัตราการครองเตียง</button>
                </div>
                <div class="bbh-history-panel" id="bbh-panel-admit" role="tabpanel" aria-labelledby="bbh-tab-admit">
                    <div id="ipd-history-admit" class="bbh-history-chart"><div class="bbh-history-loading">กำลังโหลดข้อมูล…</div></div>
                </div>
                <div class="bbh-history-panel" id="bbh-panel-occupancy" role="tabpanel" aria-labelledby="bbh-tab-occupancy" hidden>
                    <div id="ipd-history-occupancy" class="bbh-history-chart"><div class="bbh-history-loading">กำลังโหลดข้อมูล…</div></div>
                </div>
            </div>
        `;
        document.body.appendChild(backdrop);

        document.getElementById('ipd-history-trigger').addEventListener('click', openModal);
        document.getElementById('bbh-history-close').addEventListener('click', closeModal);
        backdrop.addEventListener('click', function (event) { if (event.target === backdrop) closeModal(); });
        // Esc ปิด <dialog> เอง — ล้างสถานะหน้าและคืน focus ให้ปุ่มที่เปิด ทุกทางที่ปิด
        backdrop.addEventListener('close', function () {
            document.body.classList.remove('bbh-history-modal-open');
            var trigger = document.getElementById('ipd-history-trigger');
            if (trigger) trigger.focus();
        });
        document.getElementById('bbh-tab-admit').addEventListener('click', function () { switchTab('admit'); });
        document.getElementById('bbh-tab-occupancy').addEventListener('click', function () { switchTab('occupancy'); });
        // ลูกศรซ้าย/ขวาสลับแท็บ (รูปแบบ tablist มาตรฐาน)
        backdrop.querySelector('.bbh-history-tabs').addEventListener('keydown', function (event) {
            if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight') return;
            var next = document.getElementById('bbh-tab-admit').classList.contains('active') ? 'occupancy' : 'admit';
            switchTab(next);
            document.getElementById('bbh-tab-' + next).focus();
            event.preventDefault();
        });
    }

    function populateYears() {
        var select = document.getElementById('bbh-history-year');
        if (!select || select.options.length) return;
        var current = currentFiscalYear();
        for (var year = current; year >= current - 5; year--) {
            var option = document.createElement('option');
            option.value = year;
            option.textContent = 'ปีงบประมาณ ' + year;
            select.appendChild(option);
        }
        select.value = current;
        select.addEventListener('change', loadHistory);
    }

    function loadHighcharts(callback) {
        if (window.Highcharts) { callback(); return; }
        var existing = document.getElementById('bbh-highcharts-loader');
        if (existing) { existing.addEventListener('load', callback, { once: true }); return; }
        var script = document.createElement('script');
        script.id = 'bbh-highcharts-loader';
        script.src = getBasePath() + '/assets/lib/highcharts/13.1.1/highcharts.js'; // เวอร์ชันเดียวกับหน้าอื่น (assets/lib)
        script.onload = callback;
        script.onerror = function () {
            ['ipd-history-admit', 'ipd-history-occupancy'].forEach(function (id) {
                var el = document.getElementById(id);
                if (el) el.innerHTML = '<div class="bbh-history-error">ไม่สามารถโหลด Highcharts ได้</div>';
            });
        };
        document.head.appendChild(script);
    }


    function filterAvailableMonths(data, year) {
        var current = currentFiscalYear();
        if (Number(year) !== current) return data;

        var now = new Date();
        var currentMonth = now.getMonth() + 1;
        var currentCalendarYear = now.getFullYear();

        return data.filter(function (row) {
            var parts = String(row.month || '').split('/');
            if (parts.length !== 2) return true;

            var month = Number(parts[0]);
            var calendarYear = Number(parts[1]);

            return calendarYear < currentCalendarYear ||
                (calendarYear === currentCalendarYear && month <= currentMonth);
        });
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

    function chartFont() { return { fontFamily: 'THSarabun', fontSize: '15px' }; }

    function calculateAxis(values, targetTicks) {
        var maxValue = Math.max.apply(null, values.concat([0]));
        if (maxValue <= 0) return { max: 5, tick: 1 };
        var rough = maxValue * 1.15;
        var step = Math.ceil(rough / targetTicks);
        if (step <= 2) step = 2;
        else if (step <= 5) step = 5;
        else if (step <= 10) step = 10;
        else if (step <= 20) step = 20;
        else step = Math.ceil(step / 10) * 10;
        var axisMax = step * targetTicks;
        while (axisMax < maxValue) axisMax += step;
        return { max: axisMax, tick: step };
    }

    function renderAdmit(data, year) {
        var categories = data.map(function (r) { return r.month_name; });
        var values = data.map(function (r) { return Number(r.admit_count || 0); });
        var axis = calculateAxis(values, 5);
        Highcharts.chart('ipd-history-admit', {
            chart: { type:'column', backgroundColor:'#fff', spacing:[14,18,18,12], style:chartFont() },
            title: { text:'จำนวนผู้ป่วย Admit รายเดือน', style:{ fontSize:'18px', fontWeight:'600' } },
            subtitle: { text:'ปีงบประมาณ ' + year, style:{ fontSize:'14px', color:'#5b6773' } },
            xAxis: { categories:categories, labels:{ style:{ fontSize:'14px' } }, lineColor:'#dfe7e3' },
            yAxis: { min:0, max:axis.max, tickInterval:axis.tick, allowDecimals:false, title:{ text:'จำนวนผู้ป่วย (ราย)', style:{ fontSize:'14px' } }, labels:{ style:{ fontSize:'13px' } }, gridLineColor:'#edf1ef' },
            tooltip: { shared:false, valueSuffix:' ราย', style:{ fontSize:'14px' } },
            plotOptions: { column:{ borderRadius:4, pointPadding:.08, groupPadding:.10, color:'#198754', borderColor:'#147447', dataLabels:{ enabled:true, crop:false, overflow:'allow', formatter:function(){ return this.y > 0 ? Highcharts.numberFormat(this.y,0) : null; }, style:{ fontSize:'13px', fontWeight:'bold', textOutline:'none' } } } },
            credits:{ enabled:false }, legend:{ enabled:false },
            series:[{ name:'ผู้ป่วย Admit', color:'#198754', data:values }]
        });
    }

    function renderOccupancy(data, year) {
        var categories = data.map(function (r) { return r.month_name; });
        var values = data.map(function (r) { return Number(r.occupancy_rate || 0); });
        var axis = calculateAxis(values, 5);
        if (axis.max < 100 && Math.max.apply(null, values) > 0) { axis.max = 100; axis.tick = 20; }
        Highcharts.chart('ipd-history-occupancy', {
            chart:{ type:'line', backgroundColor:'#fff', spacing:[14,18,18,12], style:chartFont() },
            title:{ text:'อัตราการครองเตียงรายเดือน', style:{ fontSize:'18px', fontWeight:'600' } },
            subtitle:{ text:'ปีงบประมาณ ' + year, style:{ fontSize:'14px', color:'#5b6773' } },
            xAxis:{ categories:categories, labels:{ style:{ fontSize:'14px' } }, lineColor:'#dfe7e3' },
            yAxis:{ min:0, max:axis.max, tickInterval:axis.tick, title:{ text:'อัตราครองเตียง (%)', style:{ fontSize:'14px' } }, labels:{ format:'{value}%', style:{ fontSize:'13px' } }, gridLineColor:'#edf1ef' },
            tooltip:{ shared:false, valueDecimals:2, valueSuffix:'%', style:{ fontSize:'14px' } },
            plotOptions:{ line:{ lineWidth:3, color:'#198754', marker:{ enabled:true, radius:4, fillColor:'#198754', lineColor:'#147447', lineWidth:1 }, dataLabels:{ enabled:true, formatter:function(){ return Highcharts.numberFormat(this.y,1)+'%'; }, style:{ fontSize:'12px', fontWeight:'bold', textOutline:'none' } } } },
            credits:{ enabled:false }, legend:{ enabled:false },
            series:[{ name:'อัตราการครองเตียง', color:'#198754', data:values }]
        });
    }

    function renderActiveChart() {
        if (!historyData.length) return;
        if (document.getElementById('bbh-panel-admit')?.hidden === false) {
            renderAdmit(historyData, historyYear);
        }
        if (document.getElementById('bbh-panel-occupancy')?.hidden === false) {
            renderOccupancy(historyData, historyYear);
        }
    }

    async function loadHistory() {
        var ward = getWard();
        var select = document.getElementById('bbh-history-year');
        if (!ward || !select) return;
        var year = select.value;
        var dateText = document.getElementById('bbh-history-date');
        var admitEl = document.getElementById('ipd-history-admit');
        var occupancyEl = document.getElementById('ipd-history-occupancy');
        if (dateText) dateText.textContent = fiscalDateText(year);
        if (admitEl) admitEl.innerHTML = '<div class="bbh-history-loading">กำลังโหลดข้อมูล…</div>';
        if (occupancyEl) occupancyEl.innerHTML = '<div class="bbh-history-loading">กำลังโหลดข้อมูล…</div>';
        historyData = [];
        historyYear = year;
        try {
            var response = await fetch(getBasePath() + '/api/ipd_ward_history.php?ward=' + encodeURIComponent(ward) + '&fiscal_year=' + encodeURIComponent(year), { cache:'no-store' });
            if (!response.ok) throw new Error('HTTP ' + response.status);
            var json = await response.json();
            if (json.error) throw new Error(json.error);
            var rawData = Array.isArray(json.data) ? json.data : [];
            var data = filterAvailableMonths(rawData, year);

            if (!data.length) {
                // ไม่มีข้อมูล ≠ โหลดไม่ได้: บอกผู้ใช้ตรง ๆ และล้างตัวเลขสรุปของปีก่อนหน้า
                var empty = '<div class="bbh-history-empty"><i class="fas fa-calendar-xmark" aria-hidden="true"></i>' +
                    'ยังไม่มีข้อมูลของ Ward นี้ในปีงบประมาณ ' + year + '<span>ลองเลือกปีงบประมาณอื่น</span></div>';
                if (admitEl) admitEl.innerHTML = empty;
                if (occupancyEl) occupancyEl.innerHTML = empty;
                if (typeof window.bbhUpdateHistoryInsight === 'function') window.bbhUpdateHistoryInsight([]);
                return;
            }

            historyData = data;
            renderAdmit(data, year);
            if (document.getElementById('bbh-panel-occupancy')?.hidden === false) renderOccupancy(data, year);

            if (typeof window.bbhUpdateHistoryInsight === 'function') window.bbhUpdateHistoryInsight(data);
        } catch (error) {
            console.error('IPD Ward History Error:', error);
            var message = '<div class="bbh-history-error" role="alert">ไม่สามารถโหลดข้อมูลสถิติย้อนหลังได้ กรุณาลองใหม่อีกครั้ง</div>';
            if (admitEl) admitEl.innerHTML = message;
            if (occupancyEl) occupancyEl.innerHTML = message;
            if (typeof window.bbhUpdateHistoryInsight === 'function') window.bbhUpdateHistoryInsight([]);
        }
    }

    function openModal() {
        var backdrop = document.getElementById('bbh-history-modal-backdrop');
        if (!backdrop || backdrop.open) return;
        backdrop.showModal();   // focus ย้ายเข้าหน้าต่าง, เนื้อหาด้านหลังกด/Tab ไม่ได้
        document.body.classList.add('bbh-history-modal-open');
        loadHistory();
    }

    function closeModal() {
        var backdrop = document.getElementById('bbh-history-modal-backdrop');
        if (backdrop && backdrop.open) backdrop.close();   // ล้างสถานะใน handler ของ 'close'
    }

    function switchTab(tab) {
        var admitTab = document.getElementById('bbh-tab-admit');
        var occupancyTab = document.getElementById('bbh-tab-occupancy');
        var admitPanel = document.getElementById('bbh-panel-admit');
        var occupancyPanel = document.getElementById('bbh-panel-occupancy');
        var isAdmit = tab === 'admit';
        admitTab.classList.toggle('active', isAdmit);
        occupancyTab.classList.toggle('active', !isAdmit);
        admitTab.setAttribute('aria-selected', isAdmit ? 'true' : 'false');
        occupancyTab.setAttribute('aria-selected', isAdmit ? 'false' : 'true');
        admitTab.tabIndex = isAdmit ? 0 : -1;
        occupancyTab.tabIndex = isAdmit ? -1 : 0;
        admitPanel.hidden = !isAdmit;
        occupancyPanel.hidden = isAdmit;
        setTimeout(function(){
            if (historyData.length) {
                if (isAdmit) renderAdmit(historyData, historyYear);
                else renderOccupancy(historyData, historyYear);
            }
        }, 20);
    }

    function init() {
        if (!document.querySelector('.ipd-ward-page .ward-main-card')) return;
        injectStyles();
        createModal();
        populateYears();
        loadHighcharts(function () {});
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();
