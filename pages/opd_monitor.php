<?php
include '../templates/header.php';
include '../templates/navbar.php';
include '../templates/sidebar.php';
?>

<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/opd_monitor.css">

<div class="content-wrapper opd-monitor-page">
    <section class="content pt-3">
        <div class="container-fluid">
            <header class="monitor-page-header">
                <div class="monitor-heading">
                    <div class="monitor-heading-icon"><i class="fa-solid fa-cubes-stacked"></i></div>
                    <div>
                        <div class="monitor-eyebrow">BBH DASHBOARD · OPD OPERATIONS</div>
                        <h1>OPD Real-time 3D Monitor</h1>
                        <p>ติดตามผู้รับบริการและความหนาแน่นของจุดบริการผู้ป่วยนอก</p>
                    </div>
                </div>
                <div class="monitor-header-actions">
                    <span class="monitor-demo-badge"><i class="fa-solid fa-flask"></i> ข้อมูลจำลอง</span>
                    <span class="monitor-live-status"><span class="monitor-pulse"></span> จำลองการติดตามสด <strong id="monitor-clock">--:--:--</strong></span>
                    <button class="monitor-action-btn" id="monitor-refresh" type="button"><i class="fa-solid fa-rotate"></i> รีเฟรชข้อมูล</button>
                    <a class="monitor-action-btn monitor-back-btn" href="<?= BASE_URL ?>index.php"><i class="fa-solid fa-arrow-left"></i> กลับหน้าแรก</a>
                </div>
            </header>

            <div class="monitor-demo-notice">
                <i class="fa-solid fa-circle-info"></i>
                <span><strong>ต้นแบบสำหรับทดลองใช้งาน</strong> — ตัวเลขและสถานะด้านล่างเป็นข้อมูลจำลอง ยังไม่ได้เชื่อมต่อฐานข้อมูล HOSxP และไม่ใช่ข้อมูลผู้ป่วยจริง</span>
            </div>

            <section class="monitor-kpi-grid" aria-label="ภาพรวมผู้รับบริการจำลอง">
                <article class="monitor-kpi-card kpi-total">
                    <div class="monitor-kpi-icon"><i class="fa-solid fa-users"></i></div>
                    <div class="monitor-kpi-label">ผู้รับบริการในระบบ</div>
                    <div class="monitor-kpi-value" id="kpi-total">175 <small>คน</small></div>
                    <div class="monitor-kpi-foot"><span class="status-dot status-green"></span> จุดบริการทั้งหมด 7 จุด</div>
                </article>
                <article class="monitor-kpi-card kpi-wait">
                    <div class="monitor-kpi-icon"><i class="fa-solid fa-hourglass-half"></i></div>
                    <div class="monitor-kpi-label">กำลังรอรับบริการ</div>
                    <div class="monitor-kpi-value" id="kpi-wait">81 <small>คน</small></div>
                    <div class="monitor-kpi-foot">เวลารอเฉลี่ย <strong id="kpi-wait-time">18 นาที</strong></div>
                </article>
                <article class="monitor-kpi-card kpi-busy">
                    <div class="monitor-kpi-icon"><i class="fa-solid fa-gauge-high"></i></div>
                    <div class="monitor-kpi-label">จุดบริการที่แออัด</div>
                    <div class="monitor-kpi-value" id="kpi-busy">3 <small>จุด</small></div>
                    <div class="monitor-kpi-foot">ความหนาแน่นตั้งแต่ 80% ขึ้นไป</div>
                </article>
                <article class="monitor-kpi-card kpi-done">
                    <div class="monitor-kpi-icon"><i class="fa-solid fa-circle-check"></i></div>
                    <div class="monitor-kpi-label">ให้บริการแล้ววันนี้</div>
                    <div class="monitor-kpi-value" id="kpi-done">853 <small>คน</small></div>
                    <div class="monitor-kpi-foot">ยอดตัวอย่างสำหรับสาธิตหน้าจอ</div>
                </article>
            </section>

            <div class="monitor-main-grid">
                <section class="monitor-map-panel">
                    <div class="monitor-panel-header">
                        <div>
                            <h2><i class="fa-solid fa-map"></i> แผนผังจุดบริการ OPD</h2>
                            <p>คลิกห้องหรือ Marker เพื่อดูรายละเอียด</p>
                        </div>
                        <label class="monitor-filter-label" for="monitor-status-filter">
                            <span>แสดงสถานะ</span>
                            <select id="monitor-status-filter">
                                <option value="all">ทุกสถานะ</option>
                                <option value="green">ปกติ</option>
                                <option value="amber">เริ่มหนาแน่น</option>
                                <option value="red">แออัด</option>
                            </select>
                        </label>
                    </div>

                    <div class="monitor-map-legend">
                        <span><i class="legend-dot green"></i> ปกติ &lt; 60%</span>
                        <span><i class="legend-dot amber"></i> เริ่มหนาแน่น 60–79%</span>
                        <span><i class="legend-dot red"></i> แออัด ≥ 80%</span>
                    </div>

                    <div class="opd-isometric-map" id="opd-isometric-map">
                        <div class="map-top-label"><i class="fa-solid fa-location-dot"></i> OPD · อาคารผู้ป่วยนอก</div>
                        <svg class="opd-floor-svg" viewBox="0 0 1000 570" role="img" aria-label="แผนผังจำลองจุดบริการผู้ป่วยนอก">
                            <defs>
                                <linearGradient id="floorGradient" x1="0" y1="0" x2="1" y2="1">
                                    <stop offset="0%" stop-color="#f8fffb"/>
                                    <stop offset="100%" stop-color="#dcefe5"/>
                                </linearGradient>
                                <linearGradient id="wallGradient" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="0%" stop-color="#ffffff"/>
                                    <stop offset="100%" stop-color="#c5d9cf"/>
                                </linearGradient>
                                <filter id="roomShadow" x="-20%" y="-20%" width="150%" height="160%">
                                    <feDropShadow dx="0" dy="8" stdDeviation="5" flood-color="#204c3b" flood-opacity=".18"/>
                                </filter>
                            </defs>
                            <!-- building base -->
                            <polygon points="90,160 615,42 930,210 405,395" fill="#b8d0c5" opacity=".5" transform="translate(0 20)"/>
                            <polygon points="65,135 590,18 905,186 380,372" fill="#ffffff" stroke="#198754" stroke-width="5"/>
                            <polygon points="65,135 380,302 380,372 65,205" fill="#b7cfc4"/>
                            <polygon points="380,302 905,116 905,186 380,372" fill="#d3e4dc"/>
                            <polygon points="65,135 590,18 905,186 380,302" fill="url(#floorGradient)" stroke="#198754" stroke-width="2"/>
                            <!-- floor lane -->
                            <polygon points="80,142 590,29 890,189 380,284" fill="#edf7f1" stroke="#c2ddd0" stroke-width="2"/>
                            <polygon points="220,111 555,37 820,179 485,253" fill="#ffffff" stroke="#d2e3da" stroke-width="2"/>
                            <path d="M95 153 L380 302 L875 127" fill="none" stroke="#b3d5c4" stroke-width="3" stroke-dasharray="8 8"/>
                            <!-- registration -->
                            <g class="map-room" data-service="registration" tabindex="0" role="button" aria-label="ลงทะเบียน">
                                <polygon class="room-floor" points="95,142 222,114 305,158 178,188" fill="#d8f0e3" stroke="#198754" stroke-width="2" filter="url(#roomShadow)"/>
                                <polygon points="95,142 95,116 222,87 222,114" fill="url(#wallGradient)" stroke="#9bbbad" stroke-width="2"/>
                                <polygon points="222,114 305,158 305,132 222,87" fill="#d1e2d9" stroke="#9bbbad" stroke-width="2"/>
                                <rect x="144" y="133" width="38" height="20" rx="4" fill="#198754" transform="rotate(-24 144 133)"/>
                                <text x="188" y="157" class="room-label" text-anchor="middle">ลงทะเบียน</text>
                            </g>
                            <!-- triage -->
                            <g class="map-room" data-service="triage" tabindex="0" role="button" aria-label="ซักประวัติ">
                                <polygon class="room-floor" points="310,94 433,67 516,112 393,140" fill="#fff0d7" stroke="#d79a24" stroke-width="2" filter="url(#roomShadow)"/>
                                <polygon points="310,94 310,65 433,38 433,67" fill="url(#wallGradient)" stroke="#9bbbad" stroke-width="2"/>
                                <polygon points="433,67 516,112 516,83 433,38" fill="#d1e2d9" stroke="#9bbbad" stroke-width="2"/>
                                <rect x="356" y="89" width="40" height="18" rx="4" fill="#d79a24" transform="rotate(-24 356 89)"/>
                                <text x="411" y="113" class="room-label" text-anchor="middle">ซักประวัติ</text>
                            </g>
                            <!-- doctor rooms -->
                            <g class="map-room" data-service="doctor" tabindex="0" role="button" aria-label="ห้องตรวจแพทย์">
                                <polygon class="room-floor" points="183,201 310,172 390,215 263,246" fill="#fee4e4" stroke="#dc3545" stroke-width="2" filter="url(#roomShadow)"/>
                                <polygon points="183,201 183,173 310,143 310,172" fill="url(#wallGradient)" stroke="#9bbbad" stroke-width="2"/>
                                <polygon points="310,172 390,215 390,187 310,143" fill="#d1e2d9" stroke="#9bbbad" stroke-width="2"/>
                                <rect x="228" y="193" width="40" height="18" rx="4" fill="#dc3545" transform="rotate(-24 228 193)"/>
                                <text x="284" y="221" class="room-label" text-anchor="middle">ห้องตรวจแพทย์</text>
                            </g>
                            <!-- lab -->
                            <g class="map-room" data-service="lab" tabindex="0" role="button" aria-label="ห้องแล็บ">
                                <polygon class="room-floor" points="432,153 555,125 637,169 514,198" fill="#dcecff" stroke="#3687c8" stroke-width="2" filter="url(#roomShadow)"/>
                                <polygon points="432,153 432,124 555,96 555,125" fill="url(#wallGradient)" stroke="#9bbbad" stroke-width="2"/>
                                <polygon points="555,125 637,169 637,140 555,96" fill="#d1e2d9" stroke="#9bbbad" stroke-width="2"/>
                                <rect x="477" y="146" width="38" height="18" rx="4" fill="#3687c8" transform="rotate(-24 477 146)"/>
                                <text x="538" y="174" class="room-label" text-anchor="middle">ห้องแล็บ</text>
                            </g>
                            <!-- xray -->
                            <g class="map-room" data-service="xray" tabindex="0" role="button" aria-label="ห้อง X-Ray">
                                <polygon class="room-floor" points="583,226 706,197 789,242 666,271" fill="#e9e2ff" stroke="#805ad5" stroke-width="2" filter="url(#roomShadow)"/>
                                <polygon points="583,226 583,197 706,168 706,197" fill="url(#wallGradient)" stroke="#9bbbad" stroke-width="2"/>
                                <polygon points="706,197 789,242 789,213 706,168" fill="#d1e2d9" stroke="#9bbbad" stroke-width="2"/>
                                <rect x="630" y="219" width="38" height="18" rx="4" fill="#805ad5" transform="rotate(-24 630 219)"/>
                                <text x="687" y="247" class="room-label" text-anchor="middle">ห้อง X-Ray</text>
                            </g>
                            <!-- pharmacy -->
                            <g class="map-room" data-service="pharmacy" tabindex="0" role="button" aria-label="ห้องยา">
                                <polygon class="room-floor" points="375,271 500,241 582,285 457,316" fill="#d8f0e3" stroke="#198754" stroke-width="2" filter="url(#roomShadow)"/>
                                <polygon points="375,271 375,242 500,212 500,241" fill="url(#wallGradient)" stroke="#9bbbad" stroke-width="2"/>
                                <polygon points="500,241 582,285 582,256 500,212" fill="#d1e2d9" stroke="#9bbbad" stroke-width="2"/>
                                <rect x="422" y="263" width="38" height="18" rx="4" fill="#198754" transform="rotate(-24 422 263)"/>
                                <text x="478" y="291" class="room-label" text-anchor="middle">ห้องยา</text>
                            </g>
                            <!-- exit / emergency -->
                            <g class="map-room" data-service="emergency" tabindex="0" role="button" aria-label="จุดคัดกรองเร่งด่วน">
                                <polygon class="room-floor" points="694,310 817,281 878,314 755,345" fill="#fee4e4" stroke="#dc3545" stroke-width="2" filter="url(#roomShadow)"/>
                                <polygon points="694,310 694,281 817,252 817,281" fill="url(#wallGradient)" stroke="#9bbbad" stroke-width="2"/>
                                <polygon points="817,281 878,314 878,285 817,252" fill="#d1e2d9" stroke="#9bbbad" stroke-width="2"/>
                                <rect x="739" y="302" width="38" height="18" rx="4" fill="#dc3545" transform="rotate(-24 739 302)"/>
                                <text x="787" y="330" class="room-label" text-anchor="middle">จุดคัดกรอง</text>
                            </g>
                            <!-- wayfinding and entrance -->
                            <g class="map-entrance">
                                <path d="M75 229 L110 248 L128 240" fill="none" stroke="#198754" stroke-width="5" stroke-linecap="round"/>
                                <circle cx="75" cy="229" r="8" fill="#198754"/>
                                <text x="83" y="265" class="entrance-label">ทางเข้า</text>
                            </g>
                            <!-- floating room markers filled by JS -->
                            <g id="monitor-map-markers"></g>
                        </svg>
                        <div class="map-bottom-hint"><i class="fa-solid fa-hand-pointer"></i> เลือกจุดบริการบนแผนผังเพื่อดูจำนวนผู้รอและรายละเอียด</div>
                    </div>
                </section>

                <aside class="monitor-side-column">
                    <section class="monitor-panel monitor-service-panel">
                        <div class="monitor-panel-header compact">
                            <div>
                                <h2><i class="fa-solid fa-list-check"></i> สถานะจุดบริการ</h2>
                                <p>เรียงตามความหนาแน่นจากมากไปน้อย</p>
                            </div>
                        </div>
                        <div class="monitor-service-list" id="monitor-service-list"></div>
                    </section>

                    <section class="monitor-panel monitor-detail-panel" id="monitor-detail-panel">
                        <div class="monitor-panel-header compact">
                            <div>
                                <h2><i class="fa-solid fa-circle-info"></i> รายละเอียดจุดบริการ</h2>
                                <p id="selected-service-subtitle">เลือกจุดบริการเพื่อดูข้อมูล</p>
                            </div>
                            <span class="detail-status-pill" id="selected-service-status">—</span>
                        </div>
                        <div class="monitor-detail-content" id="monitor-detail-content">
                            <div class="monitor-detail-empty"><i class="fa-solid fa-arrow-pointer"></i><span>คลิกห้องบนแผนผังหรือรายการด้านบน</span></div>
                        </div>
                    </section>
                </aside>
            </div>

            <footer class="monitor-page-footer">
                <span><i class="fa-solid fa-shield-halved"></i> ต้นแบบแสดงเฉพาะข้อมูลจำลอง ไม่มีข้อมูลระบุตัวผู้ป่วย</span>
                <span>BBH OPD Monitor · Prototype v0.1</span>
            </footer>
        </div>
    </section>
</div>

<script src="<?= BASE_URL ?>assets/js/opd_monitor.js"></script>
<?php include '../templates/footer.php'; ?>
