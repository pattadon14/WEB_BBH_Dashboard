/* =========================================================
   BBH DASHBOARD - IPD WARD MANAGEMENT
   Quick Action / Patient Flow / Ward Insight / Export
========================================================= */
(function () {
    'use strict';

    var ward = new URLSearchParams(window.location.search).get('ward') || '';
    var today = new Date();
    today.setHours(0, 0, 0, 0);

    function injectStyles() {
        if (document.getElementById('bbh-ipd-management-style')) return;
        var style = document.createElement('style');
        style.id = 'bbh-ipd-management-style';
        style.textContent = `
            .ipd-ward-page .ipd-ward-management {
                margin: -8px 0 22px;
                border: 1px solid #dfe7e3;
                border-radius: 12px;
                background: #f8faf9;
                overflow: hidden;
            }
            .ipd-ward-page .ipd-management-top {
                display:flex;
                align-items:center;
                justify-content:space-between;
                gap:14px;
                padding:12px 15px;
                border-bottom:1px solid #e5ece8;
            }
            .ipd-ward-page .ipd-management-title {
                margin:0;
                color:#3f4c54;
                font-size:17px;
                font-weight:700;
            }
            .ipd-ward-page .ipd-ward-status {
                display:inline-flex;
                align-items:center;
                gap:7px;
                padding:6px 11px;
                border-radius:18px;
                font-size:15px;
                font-weight:700;
            }
            .ipd-ward-page .ipd-ward-status.normal { background:#eaf7f0; color:#198754; }
            .ipd-ward-page .ipd-ward-status.watch { background:#fff7e6; color:#b77900; }
            .ipd-ward-page .ipd-ward-status.over { background:#fff0f2; color:#dc3545; }
            .ipd-ward-page .ipd-flow-grid {
                display:grid;
                grid-template-columns:repeat(4,1fr);
                gap:0;
                background:#fff;
            }
            .ipd-ward-page .ipd-flow-item {
                padding:12px 15px;
                border-right:1px solid #e7ece9;
            }
            .ipd-ward-page .ipd-flow-item:last-child { border-right:0; }
            .ipd-ward-page .ipd-flow-label { color:#71808d; font-size:14px; }
            .ipd-ward-page .ipd-flow-value { margin-top:2px; color:#198754; font-size:23px; font-weight:800; line-height:1.2; }
            .ipd-ward-page .ipd-flow-value.blue { color:#1683c5; }
            .ipd-ward-page .ipd-flow-value.red { color:#dc3545; }
            .ipd-ward-page .ipd-flow-value.orange { color:#f59e0b; }
            .ipd-ward-page .ipd-quick-actions {
                display:flex;
                align-items:center;
                gap:7px;
                flex-wrap:wrap;
                padding:10px 15px;
                border-top:1px solid #e7ece9;
                background:#fbfcfc;
            }
            .ipd-ward-page .ipd-quick-label { color:#66727b; font-size:14px; font-weight:700; margin-right:2px; }
            .ipd-ward-page .ipd-quick-btn {
                border:1px solid #cfe1d7;
                border-radius:18px;
                padding:6px 11px;
                background:#fff;
                color:#198754;
                font-size:14px;
                font-weight:700;
                cursor:pointer;
                transition:all .15s ease;
            }
            .ipd-ward-page .ipd-quick-btn:hover,
            .ipd-ward-page .ipd-quick-btn.active { background:#198754; border-color:#198754; color:#fff; }
            .ipd-ward-page .ipd-quick-btn.export { margin-left:auto; color:#1683c5; border-color:#cfe0ec; }
            .ipd-ward-page .ipd-quick-btn.export:hover { background:#1683c5; border-color:#1683c5; color:#fff; }

            .ipd-ward-page .ipd-filter-extra-group { display:flex; align-items:center; gap:6px; }
            .ipd-ward-page .ipd-filter-extra-select {
                height:38px; min-width:175px; padding:6px 10px;
                border:1px solid #cfdad5; border-radius:7px; background:#fff;
                color:#344047; font-size:14px; outline:none;
            }
            .ipd-ward-page .ipd-management-hidden { display:none !important; }
            .ipd-ward-page .ipd-insight-summary {
                display:grid; grid-template-columns:repeat(5,1fr); gap:9px;
                margin:0 0 18px;
            }
            .ipd-ward-page .ipd-insight-item {
                padding:10px 12px; border:1px solid #e0e8e4; border-radius:9px; background:#fff;
            }
            .ipd-ward-page .ipd-insight-label { color:#71808d; font-size:13px; }
            .ipd-ward-page .ipd-insight-value { color:#198754; font-size:19px; font-weight:800; margin-top:2px; }
            .ipd-ward-page .ipd-insight-value.red { color:#dc3545; }
            .ipd-ward-page .ipd-insight-value.blue { color:#1683c5; }
            @media (max-width:991px) {
                .ipd-ward-page .ipd-flow-grid { grid-template-columns:repeat(2,1fr); }
                .ipd-ward-page .ipd-flow-item:nth-child(2) { border-right:0; }
                .ipd-ward-page .ipd-insight-summary { grid-template-columns:repeat(3,1fr); }
            }
            @media (max-width:575px) {
                .ipd-ward-page .ipd-management-top { align-items:flex-start; flex-direction:column; }
                .ipd-ward-page .ipd-flow-grid { grid-template-columns:repeat(2,1fr); }
                .ipd-ward-page .ipd-flow-item { border-right:0; border-bottom:1px solid #e7ece9; }
                .ipd-ward-page .ipd-insight-summary { grid-template-columns:repeat(2,1fr); }
                .ipd-ward-page .ipd-quick-btn.export { margin-left:0; }
                .ipd-ward-page .ipd-filter-extra-group { width:100%; flex-direction:column; align-items:stretch; }
                .ipd-ward-page .ipd-filter-extra-select { width:100%; }
            }
        `;
        document.head.appendChild(style);
    }

    function parseDate(text) {
        var m = String(text || '').match(/(\d{1,2})\/(\d{1,2})\/(\d{4})/);
        if (!m) return null;
        var y = Number(m[3]); if (y > 2400) y -= 543;
        var d = new Date(y, Number(m[2]) - 1, Number(m[1]));
        d.setHours(0,0,0,0);
        return d;
    }

    function getCards() {
        return Array.from(document.querySelectorAll('#patient-grid > .patient-col')).filter(function (col) {
            return col.querySelector('.patient-card');
        });
    }

    function cardMeta(col) {
        var card = col.querySelector('.patient-card');
        var rows = card ? card.querySelectorAll('.patient-info-row') : [];
        var stayText = rows[1] ? rows[1].textContent : '';
        var admitText = rows[2] ? rows[2].textContent : '';
        var stayMatch = stayText.match(/([\d,]+)\s*วัน/);
        return {
            col: col,
            stay: stayMatch ? Number(stayMatch[1].replace(/,/g,'')) : 0,
            admit: parseDate(admitText),
            doctor: (card.querySelector('.doctor-name')?.textContent || '').trim(),
            name: (card.querySelector('.patient-name')?.textContent || '').trim(),
            hn: card.textContent
        };
    }

    function addQuickActions() {
        if (document.getElementById('ipd-ward-management')) return;
        var overviewGrid = document.querySelector('.ipd-ward-page .ward-overview-grid');
        var patientHead = document.querySelector('.ipd-ward-page .patient-section-head');
        if (!overviewGrid || !patientHead) return;

        var box = document.createElement('section');
        box.id = 'ipd-ward-management';
        box.className = 'ipd-ward-management';
        box.innerHTML = `
            <div class="ipd-management-top">
                <h3 class="ipd-management-title"><i class="fas fa-clipboard-check mr-1"></i> สถานการณ์ Ward วันนี้</h3>
                <div id="ipd-ward-status" class="ipd-ward-status normal"><i class="fas fa-circle"></i> กำลังประเมิน...</div>
            </div>
            <div class="ipd-flow-grid">
                <div class="ipd-flow-item"><div class="ipd-flow-label">ผู้ป่วยปัจจุบัน</div><div id="ipd-flow-current" class="ipd-flow-value">-</div></div>
                <div class="ipd-flow-item"><div class="ipd-flow-label">Admit วันนี้</div><div id="ipd-flow-admit" class="ipd-flow-value blue">-</div></div>
                <div class="ipd-flow-item"><div class="ipd-flow-label">Discharge วันนี้</div><div id="ipd-flow-discharge" class="ipd-flow-value orange">-</div></div>
                <div class="ipd-flow-item"><div class="ipd-flow-label">การเปลี่ยนแปลงสุทธิวันนี้</div><div id="ipd-flow-net" class="ipd-flow-value">-</div></div>
            </div>
            <div class="ipd-quick-actions">
                <span class="ipd-quick-label">ดูด่วน:</span>
                <button type="button" class="ipd-quick-btn active" data-management-status="all">ทั้งหมด</button>
                <button type="button" class="ipd-quick-btn" data-management-status="admit-today">Admit วันนี้</button>
                <button type="button" class="ipd-quick-btn" data-management-status="stay7">นอน &gt; 7 วัน</button>
                <button type="button" class="ipd-quick-btn" data-management-status="stay14">นอน &gt; 14 วัน</button>
                <button type="button" class="ipd-quick-btn export" id="ipd-export-csv"><i class="fas fa-file-excel mr-1"></i> Export Excel</button>
            </div>
        `;
        overviewGrid.insertAdjacentElement('afterend', box);

        loadFlow();
        bindQuickActions();
    }

    async function loadFlow() {
        if (!ward) return;
        try {
            var res = await fetch((window.BASE_URL || '') + 'api/ipd_ward_daily.php?ward=' + encodeURIComponent(ward), {cache:'no-store'});
            if (!res.ok) throw new Error('HTTP ' + res.status);
            var d = await res.json();
            if (d.error) throw new Error(d.error);
            setText('ipd-flow-current', Number(d.current_admit).toLocaleString('th-TH') + ' ราย');
            setText('ipd-flow-admit', Number(d.admit_today).toLocaleString('th-TH') + ' ราย');
            setText('ipd-flow-discharge', Number(d.discharge_today).toLocaleString('th-TH') + ' ราย');
            var net = Number(d.net_today);
            var netEl = document.getElementById('ipd-flow-net');
            if (netEl) { netEl.textContent = (net > 0 ? '+' : '') + net.toLocaleString('th-TH') + ' ราย'; netEl.className = 'ipd-flow-value ' + (net > 0 ? 'red' : net < 0 ? 'blue' : ''); }
            var status = document.getElementById('ipd-ward-status');
            if (!status) return;
            var occ = Number(d.occupancy_rate || 0);
            var cls = occ > 100 ? 'over' : occ >= 95 ? 'watch' : 'normal';
            var text = occ > 100 ? 'เตียงเกินจำนวน ' + (occ - 100).toFixed(0) + '%' : occ >= 95 ? 'เตียงใกล้เต็ม' : 'สถานะปกติ';
            status.className = 'ipd-ward-status ' + cls;
            status.innerHTML = '<i class="fas fa-circle"></i> ' + text;
        } catch (e) {
            console.error('IPD Ward Daily:', e);
        }
    }

    function setText(id, text) { var el = document.getElementById(id); if (el) el.textContent = text; }

    function ensureExtraFilters() {
        var tools = document.getElementById('ipd-patient-tools');
        if (!tools || document.getElementById('ipd-management-status')) return;
        var result = document.getElementById('ipd-filter-result');
        var status = document.createElement('div');
        status.className = 'ipd-filter-extra-group';
        status.innerHTML = '<label class="ipd-filter-label" for="ipd-management-status">สถานะ</label><select id="ipd-management-status" class="ipd-filter-extra-select"><option value="all">ทั้งหมด</option><option value="admit-today">Admit วันนี้</option><option value="stay7">นอนเกิน 7 วัน</option><option value="stay14">นอนเกิน 14 วัน</option></select>';
        tools.insertBefore(status, result || null);

        var doctors = document.createElement('div');
        doctors.className = 'ipd-filter-extra-group';
        doctors.innerHTML = '<label class="ipd-filter-label" for="ipd-management-doctor">แพทย์</label><select id="ipd-management-doctor" class="ipd-filter-extra-select"><option value="all">ทั้งหมด</option></select>';
        tools.insertBefore(doctors, result || null);

        populateDoctors();
        document.getElementById('ipd-management-status').addEventListener('change', function () { applyManagementFilter(); });
        document.getElementById('ipd-management-doctor').addEventListener('change', function () { applyManagementFilter(); });
        ['ipd-patient-search','ipd-patient-gender','ipd-patient-stay','ipd-patient-sort'].forEach(function(id){
            var el=document.getElementById(id); if(el) el.addEventListener(el.tagName==='INPUT'?'input':'change', function(){setTimeout(applyManagementFilter,30);});
        });
    }

    function populateDoctors() {
        var select = document.getElementById('ipd-management-doctor');
        if (!select) return;
        var values = getCards().map(function(c){ return cardMeta(c).doctor; }).filter(Boolean).filter(function(v){return v !== '-';});
        Array.from(new Set(values)).sort(function(a,b){return a.localeCompare(b,'th');}).forEach(function(name){
            var opt=document.createElement('option'); opt.value=name; opt.textContent=name; select.appendChild(opt);
        });
    }

    function applyManagementFilter() {
        var status = document.getElementById('ipd-management-status')?.value || 'all';
        var doctor = document.getElementById('ipd-management-doctor')?.value || 'all';
        var cards = getCards();
        var visible = 0;
        cards.forEach(function(col){
            var m=cardMeta(col), ok=true;
            if(status==='admit-today') ok = !!m.admit && m.admit.getTime()===today.getTime();
            if(status==='stay7') ok = m.stay > 7;
            if(status==='stay14') ok = m.stay > 14;
            if(doctor!=='all') ok = ok && m.doctor===doctor;
            col.classList.toggle('ipd-management-hidden', !ok);
            if(ok) visible++;
        });
        updateResult(visible);
    }

    function updateResult(count) {
        var result=document.getElementById('ipd-filter-result');
        if(result) result.textContent='แสดง ' + count.toLocaleString('th-TH') + ' ราย';
    }

    function bindQuickActions() {
        document.querySelectorAll('[data-management-status]').forEach(function(btn){
            btn.addEventListener('click', function(){
                var value=btn.getAttribute('data-management-status');
                var select=document.getElementById('ipd-management-status');
                if(select) select.value=value;
                document.querySelectorAll('[data-management-status]').forEach(function(b){b.classList.remove('active');});
                btn.classList.add('active');
                applyManagementFilter();
            });
        });
        var exportBtn=document.getElementById('ipd-export-csv');
        if(exportBtn) exportBtn.addEventListener('click', exportCsv);
    }

    function exportCsv() {
        var rows=[['ชื่อผู้ป่วย','เพศ/อายุ','HN','AN','เตียง','วันนอน','วันที่ Admit','แพทย์']];
        getCards().forEach(function(col){
            if(col.classList.contains('ipd-filter-hidden') || col.classList.contains('ipd-management-hidden')) return;
            var card=col.querySelector('.patient-card'); if(!card) return;
            var meta=cardMeta(col), info=card.querySelectorAll('.patient-info-row');
            var sexAge=(card.querySelector('.patient-meta')?.textContent||'').trim();
            var badges=Array.from(card.querySelectorAll('.patient-badge')).map(function(x){return x.textContent.trim();});
            var an=(badges.find(function(x){return /^AN/.test(x);})||'').replace(/^AN\s*/,'');
            var bed=(badges.find(function(x){return /เตียง/.test(x);})||'').replace(/^.*?เตียง\s*/,'');
            rows.push([meta.name,sexAge,(card.textContent.match(/HN\s*:\s*([0-9]+)/)||[])[1]||'',an,bed,meta.stay,info[2]?.textContent.replace(/วันที่?\s*[:：]?/,'').trim()||'',meta.doctor]);
        });
        var csv='\ufeff'+rows.map(function(row){return row.map(csvCell).join(',');}).join('\r\n');
        var blob=new Blob([csv],{type:'text/csv;charset=utf-8;'});
        var url=URL.createObjectURL(blob), a=document.createElement('a');
        a.href=url; a.download='IPD_Ward_' + (ward||'') + '_' + new Date().toISOString().slice(0,10) + '.csv';
        document.body.appendChild(a); a.click(); a.remove(); URL.revokeObjectURL(url);
    }
    function csvCell(v){return '"'+String(v??'').replace(/"/g,'""')+'"';}

    function addHistoryInsight() {
        var backdrop = document.getElementById('bbh-history-modal-backdrop');
        if (!backdrop || backdrop.dataset.insightBound === '1') return;
        backdrop.dataset.insightBound='1';
        var observer=new MutationObserver(function(){
            var admit=document.getElementById('ipd-history-admit');
            var occ=document.getElementById('ipd-history-occupancy');
            if(!admit || !occ || document.getElementById('ipd-history-insight')) return;
            var box=document.createElement('div'); box.id='ipd-history-insight'; box.className='ipd-insight-summary';
            box.innerHTML='<div class="ipd-insight-item"><div class="ipd-insight-label">Admit รวม</div><div id="ins-admit" class="ipd-insight-value">-</div></div><div class="ipd-insight-item"><div class="ipd-insight-label">เฉลี่ย / เดือน</div><div id="ins-avg" class="ipd-insight-value">-</div></div><div class="ipd-insight-item"><div class="ipd-insight-label">ครองเตียงเฉลี่ย</div><div id="ins-occ" class="ipd-insight-value">-</div></div><div class="ipd-insight-item"><div class="ipd-insight-label">สูงสุด</div><div id="ins-max" class="ipd-insight-value red">-</div></div><div class="ipd-insight-item"><div class="ipd-insight-label">ต่ำสุด</div><div id="ins-min" class="ipd-insight-value blue">-</div></div>';
            var modal=backdrop.querySelector('.bbh-history-modal');
            var tabs=backdrop.querySelector('.bbh-history-tabs');
            if(modal && tabs) modal.insertBefore(box,tabs.nextElementSibling);
            loadInsight();
        });
        observer.observe(backdrop,{childList:true,subtree:true});
    }

    async function loadInsight(){
        var select=document.getElementById('bbh-history-year'); if(!select||!ward)return;
        try{
            var res=await fetch((window.BASE_URL||'')+'api/ipd_ward_history.php?ward='+encodeURIComponent(ward)+'&fiscal_year='+encodeURIComponent(select.value),{cache:'no-store'});
            var j=await res.json(), data=j.data||[]; if(!data.length)return;
            var admits=data.map(function(x){return Number(x.admit_count)||0;}), occ=data.map(function(x){return Number(x.occupancy_rate)||0;});
            var total=admits.reduce(function(a,b){return a+b;},0), avg=total/data.length;
            var max=Math.max.apply(null,admits), min=Math.min.apply(null,admits);
            var maxRow=data[admits.indexOf(max)], minRow=data[admits.indexOf(min)];
            setText('ins-admit',total.toLocaleString('th-TH')+' ราย');
            setText('ins-avg',avg.toFixed(1)+' ราย');
            setText('ins-occ',(occ.reduce(function(a,b){return a+b;},0)/occ.length).toFixed(2)+'%');
            setText('ins-max',maxRow.month_name+' · '+max+' ราย');
            setText('ins-min',minRow.month_name+' · '+min+' ราย');
        }catch(e){console.error('IPD history insight:',e);}
    }

    function init(){
        if(!document.querySelector('.ipd-ward-page')) return;
        injectStyles();
        addQuickActions();
        ensureExtraFilters();
        addHistoryInsight();
        var observer=new MutationObserver(function(){
            ensureExtraFilters();
            var grid=document.getElementById('patient-grid');
            if(grid && grid.querySelector('.patient-card')) { populateDoctors(); applyManagementFilter(); }
        });
        var grid=document.getElementById('patient-grid'); if(grid) observer.observe(grid,{childList:true});
    }
    if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',init); else init();
})();
