/* OPD Monitor prototype — all values are simulated */
(function () {
    'use strict';

    const services = [
        { id:'registration', name:'ลงทะเบียน', icon:'fa-clipboard-list', capacity:20, waiting:2, serving:3, done:142, waitMin:4, polygon:[158,145], marker:[157,126], note:'จุดลงทะเบียนผู้ป่วยนอกและตรวจสอบสิทธิ์' },
        { id:'triage', name:'ซักประวัติ', icon:'fa-notes-medical', capacity:30, waiting:32, serving:8, done:208, waitMin:63, polygon:[411,101], marker:[413,79], note:'จุดซักประวัติ วัดสัญญาณชีพ และจัดลำดับคิว' },
        { id:'doctor', name:'ห้องตรวจแพทย์', icon:'fa-user-doctor', capacity:40, waiting:97, serving:12, done:260, waitMin:96, polygon:[284,211], marker:[285,187], note:'ห้องตรวจโรคทั่วไปและประเมินอาการโดยแพทย์' },
        { id:'lab', name:'ห้องแล็บ', icon:'fa-flask', capacity:20, waiting:5, serving:4, done:71, waitMin:6, polygon:[538,165], marker:[538,143], note:'จุดเจาะเลือดและรับสิ่งส่งตรวจ' },
        { id:'xray', name:'ห้อง X-Ray', icon:'fa-x-ray', capacity:10, waiting:0, serving:2, done:36, waitMin:0, polygon:[687,238], marker:[688,215], note:'บริการถ่ายภาพรังสีทั่วไป' },
        { id:'pharmacy', name:'ห้องยา', icon:'fa-prescription-bottle-medical', capacity:40, waiting:15, serving:7, done:116, waitMin:73, polygon:[478,281], marker:[480,258], note:'จ่ายยา ให้คำแนะนำการใช้ยา และตรวจสอบใบสั่งยา' },
        { id:'emergency', name:'จุดคัดกรองเร่งด่วน', icon:'fa-truck-medical', capacity:12, waiting:14, serving:3, done:20, waitMin:40, polygon:[787,319], marker:[789,297], note:'จุดคัดกรองอาการเร่งด่วนและส่งต่อบริการที่เหมาะสม' }
    ];

    let selectedId = null;
    let refreshCount = 0;

    const $ = (id) => document.getElementById(id);
    const number = (value) => Number(value || 0).toLocaleString('th-TH');
    const getService = (id) => services.find((item) => item.id === id);
    const occupancy = (item) => Math.round((item.waiting / Math.max(item.capacity, 1)) * 100);
    const stateFor = (item) => {
        const ratio = occupancy(item);
        if (ratio >= 80) return 'red';
        if (ratio >= 60) return 'amber';
        return 'green';
    };
    const stateLabel = (state) => ({green:'ปกติ', amber:'เริ่มหนาแน่น', red:'แออัด'})[state] || 'ปกติ';
    const stateColor = (state) => ({green:'#20a464', amber:'#e2a126', red:'#dc3545'})[state] || '#20a464';

    function renderMarkers() {
        const group = $('monitor-map-markers');
        const filter = $('monitor-status-filter').value;
        group.innerHTML = services.map((item) => {
            const state = stateFor(item);
            const [x,y] = item.marker;
            const hidden = filter !== 'all' && state !== filter;
            return `<g class="map-marker ${hidden ? 'is-filtered-out' : ''} ${selectedId === item.id ? 'is-selected' : ''}" data-service="${item.id}" tabindex="0" role="button" aria-label="${item.name}: รอ ${item.waiting} คน" style="color:${stateColor(state)}">
                <circle class="marker-halo" cx="${x}" cy="${y}" r="17"></circle>
                <circle class="marker-core" cx="${x}" cy="${y}" r="9"></circle>
                <text x="${x+13}" y="${y-11}">${item.waiting} คน</text>
            </g>`;
        }).join('');

        document.querySelectorAll('.map-room[data-service]').forEach((room) => {
            const item = getService(room.dataset.service);
            if (!item) return;
            const state = stateFor(item);
            const roomHidden = filter !== 'all' && state !== filter;
            room.classList.toggle('is-filtered-out', roomHidden);
            room.classList.toggle('is-selected', selectedId === item.id);
            const floor = room.querySelector('.room-floor');
            if (floor) {
                const fills = {green:'#dff3e7', amber:'#fff0cc', red:'#ffe1e4'};
                floor.setAttribute('fill', fills[state]);
                floor.setAttribute('stroke', stateColor(state));
            }
        });
    }

    function renderServiceList() {
        const filter = $('monitor-status-filter').value;
        const visible = services.filter((item) => filter === 'all' || stateFor(item) === filter)
            .sort((a,b) => occupancy(b)-occupancy(a));

        $('monitor-service-list').innerHTML = visible.map((item) => {
            const state = stateFor(item);
            const ratio = occupancy(item);
            return `<div class="monitor-service-item ${selectedId === item.id ? 'is-selected' : ''}" data-service="${item.id}" tabindex="0" role="button" aria-label="ดูรายละเอียด ${item.name}">
                <div class="service-state-icon ${state}"><i class="fa-solid ${item.icon}"></i></div>
                <div class="monitor-service-main">
                    <div class="monitor-service-name">${item.name}</div>
                    <div class="monitor-service-meta"><span>${stateLabel(state)}</span><span>รอ ${number(item.waiting)} คน</span></div>
                    <div class="monitor-service-progress"><span style="width:${Math.min(ratio,100)}%;background:${stateColor(state)}"></span></div>
                </div>
                <div class="monitor-service-count">${ratio}%<small>ความหนาแน่น</small></div>
            </div>`;
        }).join('');
    }

    function renderDetails() {
        const item = getService(selectedId);
        const status = $('selected-service-status');
        if (!item) {
            status.textContent = '—';
            status.className = 'detail-status-pill';
            $('selected-service-subtitle').textContent = 'เลือกจุดบริการเพื่อดูข้อมูล';
            $('monitor-detail-content').innerHTML = '<div class="monitor-detail-empty"><i class="fa-solid fa-arrow-pointer"></i><span>คลิกห้องบนแผนผังหรือรายการด้านบน</span></div>';
            return;
        }
        const state = stateFor(item);
        const ratio = occupancy(item);
        status.textContent = stateLabel(state);
        status.className = 'detail-status-pill ' + (state === 'green' ? '' : state);
        $('selected-service-subtitle').textContent = 'ข้อมูลจำลอง · อัปเดตล่าสุด ' + new Date().toLocaleTimeString('th-TH', {hour:'2-digit',minute:'2-digit',second:'2-digit'});
        $('monitor-detail-content').innerHTML = `
            <div class="detail-selected-summary">
                <div class="detail-selected-icon"><i class="fa-solid ${item.icon}"></i></div>
                <div><div class="detail-selected-name">${item.name}</div><div class="detail-selected-sub">รอรับบริการ / ขีดความสามารถจำลอง</div></div>
                <div class="detail-occupancy"><strong style="color:${stateColor(state)}">${ratio}%</strong><small>ความหนาแน่น</small></div>
            </div>
            <div class="detail-metrics-grid">
                <div class="detail-metric"><span><i class="fa-solid fa-hourglass-half"></i> กำลังรอ</span><strong>${number(item.waiting)} คน</strong></div>
                <div class="detail-metric"><span><i class="fa-solid fa-user-doctor"></i> กำลังรับบริการ</span><strong>${number(item.serving)} คน</strong></div>
                <div class="detail-metric"><span><i class="fa-solid fa-users"></i> ให้บริการแล้ววันนี้</span><strong>${number(item.done)} คน</strong></div>
                <div class="detail-metric"><span><i class="fa-regular fa-clock"></i> เวลารอเฉลี่ย</span><strong>${number(item.waitMin)} นาที</strong></div>
            </div>
            <div class="detail-status-note"><strong>เกี่ยวกับจุดบริการ</strong><br>${item.note}<br><span style="color:#9b6b10">* ค่าขีดความสามารถใช้เพื่อสาธิตการคำนวณเท่านั้น</span></div>`;
    }

    function updateKpis() {
        const totalWaiting = services.reduce((sum,item) => sum + item.waiting, 0);
        const totalDone = services.reduce((sum,item) => sum + item.done, 0);
        const busy = services.filter((item) => occupancy(item) >= 80).length;
        const weightedWait = Math.round(services.reduce((sum,item) => sum + item.waitMin * Math.max(item.waiting,1), 0) / services.reduce((sum,item) => sum + Math.max(item.waiting,1), 0));
        $('kpi-total').innerHTML = number(totalWaiting + services.reduce((sum,item)=>sum+item.serving,0)) + ' <small>คน</small>';
        $('kpi-wait').innerHTML = number(totalWaiting) + ' <small>คน</small>';
        $('kpi-wait-time').textContent = number(weightedWait) + ' นาที';
        $('kpi-busy').innerHTML = number(busy) + ' <small>จุด</small>';
        $('kpi-done').innerHTML = number(totalDone) + ' <small>คน</small>';
    }

    function selectService(id) {
        const item = getService(id);
        if (!item) return;
        selectedId = id;
        document.querySelectorAll('[data-service]').forEach((node) => {
            node.classList.toggle('is-selected', node.dataset.service === id);
        });
        renderServiceList();
        renderDetails();
    }

    function renderAll() {
        updateKpis();
        renderMarkers();
        renderServiceList();
        renderDetails();
        if (selectedId) selectService(selectedId);
    }

    function refreshMockData() {
        refreshCount++;
        services.forEach((item) => {
            item.waiting = Math.max(0, Math.min(item.capacity * 1.4, item.waiting + Math.round(Math.random() * 8 - 4)));
            item.serving = Math.max(0, Math.min(20, item.serving + Math.round(Math.random() * 4 - 2)));
            item.done = Math.max(0, item.done + Math.round(Math.random() * 6));
            item.waitMin = Math.max(0, Math.min(180, item.waitMin + Math.round(Math.random() * 10 - 5)));
        });
        renderAll();
        $('monitor-refresh').innerHTML = '<i class="fa-solid fa-check"></i> อัปเดตแล้ว';
        window.setTimeout(() => {
            $('monitor-refresh').innerHTML = '<i class="fa-solid fa-rotate"></i> รีเฟรชข้อมูล';
        }, 1200);
    }

    function updateClock() {
        $('monitor-clock').textContent = new Date().toLocaleTimeString('th-TH', {hour:'2-digit',minute:'2-digit',second:'2-digit'});
    }

    function activateFromEvent(event) {
        const target = event.target.closest('[data-service]');
        if (target) selectService(target.dataset.service);
    }

    document.addEventListener('click', activateFromEvent);
    document.addEventListener('keydown', function(event) {
        if ((event.key === 'Enter' || event.key === ' ') && event.target.matches('[data-service][tabindex="0"]')) {
            event.preventDefault();
            selectService(event.target.dataset.service);
        }
    });
    $('monitor-status-filter').addEventListener('change', function () {
        renderMarkers();
        renderServiceList();
    });
    $('monitor-refresh').addEventListener('click', refreshMockData);
    window.setInterval(updateClock, 1000);
    updateClock();
    renderAll();
})();
