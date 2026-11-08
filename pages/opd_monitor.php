<?php
/*
 * 3D Monitor ผู้ป่วยนอก (OPD) — ผัง 2.5D แบบ live / replay
 * เอนจินวาดผังพอร์ตจาก flow.js ของ opd-dashboard-wnk (MIT, © 2569 นายเผด็จชัย สิงหล รพ.วังน้ำเขียว)
 */
require_once __DIR__ . '/../auth/provider.php';
require_once __DIR__ . '/../config/app.php';

date_default_timezone_set('Asia/Bangkok');   // เซิร์ฟเวอร์เป็น UTC
$today = date('Y-m-d');
$d = $_GET['d'] ?? $today;
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) || $d > $today) {
    $d = $today;
}
$loggedIn = provider_is_logged_in();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>3D Monitor OPD — โรงพยาบาลบ้านบึง</title>
    <!-- โลโก้ รพ.บ้านบึง (assets/img: ตัดเฉพาะสัญลักษณ์จาก logo-bbh.png) -->
    <link rel="icon" type="image/png" sizes="32x32" href="<?= BASE_URL ?>assets/img/favicon-32.png">
    <link rel="apple-touch-icon" href="<?= BASE_URL ?>assets/img/apple-touch-icon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Kanit:wght@400;500;600&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- ?v= เวลาแก้ไฟล์ — กันเบราว์เซอร์ใช้ไฟล์เก่าจากแคช -->
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/opd_monitor.css?v=<?= filemtime(__DIR__ . '/../assets/css/opd_monitor.css') ?>">
</head>
<body>
<canvas id="map" aria-label="ผังผู้ป่วยนอกแบบ 3 มิติ"></canvas>
<div id="tip" class="tip" hidden></div>

<header class="top">
    <a class="brand card" href="<?= BASE_URL ?>index.php" title="กลับหน้าหลัก">
        <img class="brand-icon brand-logo-img" src="<?= BASE_URL ?>assets/img/logo-bbh-mark.png" alt="" width="36" height="36">
        <span><b>โรงพยาบาลบ้านบึง</b><small>ผู้ป่วยนอก (OPD) · 3D Monitor</small></span>
    </a>
    <form class="search card" id="search" role="search">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input id="q" inputmode="numeric" autocomplete="off"
            placeholder="<?= $loggedIn ? 'ค้นหา HN (ไม่ต้องใส่ 0 นำหน้า) หรือเลขคิว' : 'เข้าสู่ระบบเพื่อค้นหา HN / เลขคิว' ?>"
            <?= $loggedIn ? '' : 'disabled' ?> aria-label="ค้นหา HN หรือเลขคิว">
    </form>
    <button type="button" class="pill card" id="btnHow"><i class="fa-solid fa-circle-info"></i> วิธีคำนวณ</button>
</header>

<div class="clockcard card">
    <span class="dot" id="modeDot"></span>
    <b id="clock">--:--</b><span class="unit">น.</span>
    <span class="mode" id="mode">LIVE</span>
    <span class="date" id="dateLabel"></span>
</div>

<section class="kpis">
    <div class="kpi card"><small>ผู้ป่วยใน รพ.</small><b id="kIn">0</b><span>คน</span></div>
    <div class="kpi card"><small>รอเฉลี่ย</small><b id="kWait">0</b><span>นาที</span></div>
    <div class="kpi card"><small>ความหนาแน่น</small><b id="kDens">0%</b><em class="chip" id="kDensT">ว่าง</em></div>
    <div class="kpi card"><small>กลับบ้านแล้ววันนี้</small><b id="kLeft">0</b><span>คน</span></div>
    <div class="kpi kpi-sm card"><small>Telemed / PCU วันนี้</small><b id="kPcu">0</b><span>ราย</span></div>
    <div class="kpi kpi-sm card"><small>บันทึกเวลาครบคู่</small><b id="kQual">0%</b><span id="kQualSub"></span></div>
</section>

<nav class="zoom card" aria-label="ซูม">
    <button id="zIn" title="ซูมเข้า"><i class="fa-solid fa-plus"></i></button>
    <button id="zOut" title="ซูมออก"><i class="fa-solid fa-minus"></i></button>
    <button id="zFit" title="พอดีจอ"><i class="fa-solid fa-house"></i></button>
    <button id="zFull" title="เต็มจอ"><i class="fa-solid fa-expand"></i></button>
</nav>

<nav class="menu" id="menu" aria-label="เมนู">
    <button class="mbtn" data-act="toggle"><span>เมนู</span><i class="fa-solid fa-bars"></i></button>
    <button class="mbtn" data-act="date"><span>วันที่</span><i class="fa-regular fa-calendar"></i></button>
    <button class="mbtn" data-act="dep"><span>แผนก</span><i class="fa-solid fa-hospital"></i></button>
    <button class="mbtn" data-act="staff"><span>แพทย์</span><i class="fa-solid fa-user-doctor"></i></button>
    <button class="mbtn" data-act="bydep"><span>สรุปตามแผนก</span><i class="fa-solid fa-chart-column"></i></button>
    <button class="mbtn" data-act="wait"><span>รายงานเวลารอ</span><i class="fa-solid fa-stopwatch"></i></button>
    <button class="mbtn" data-act="insight"><span>วิเคราะห์ &amp; แนะนำ</span><i class="fa-regular fa-compass"></i></button>
    <button class="mbtn" data-act="live"><span>ข้อมูลสด</span><i class="fa-solid fa-tower-broadcast"></i></button>
</nav>
<input type="date" id="date" value="<?= htmlspecialchars($d) ?>" max="<?= $today ?>" class="sr-only" tabindex="-1" aria-hidden="true">

<aside class="panel card" id="panel">
    <h3>จุดบริการ <span class="tag" id="depTag" hidden></span></h3>
    <div id="rows"></div>
    <div class="remote" id="remote"></div>
</aside>

<aside class="track card" id="track" hidden aria-live="polite">
    <header>
        <span class="tr-icon"><i class="fa-solid fa-location-crosshairs"></i></span>
        <span class="tr-who"><b id="trName"></b><small id="trHn"></small></span>
        <button type="button" id="trClose" aria-label="เลิกติดตาม"><i class="fa-solid fa-xmark"></i></button>
    </header>
    <div id="trBody"></div>
    <button type="button" class="trfollow" id="trFollow" hidden><i class="fa-solid fa-video"></i> กล้องติดตามผู้ป่วย</button>
</aside>

<footer class="legend card">
    <b>ภาพรวมความหนาแน่นของโรงพยาบาล</b>
    <p>สีพื้นบอกระดับความแออัด เขียว ว่าง · เหลือง ปานกลาง · แดง แน่น (คนในจุด ÷ ที่นั่ง)</p>
    <span><i style="background:#1fa463"></i>ว่าง &lt;45%</span>
    <span><i style="background:#f59e0b"></i>ปานกลาง</span>
    <span><i style="background:#e5484d"></i>แน่น ≥80%</span>
</footer>

<section class="player card">
    <button class="play" id="btnPlay" title="เล่น / หยุด"><i class="fa-solid fa-play"></i></button>
    <div class="pmain">
        <div class="prow">
            <span class="chips">
                <span class="chip2"><i class="fa-solid fa-user-doctor"></i> <span id="chipDr">แพทย์ 0 คน</span></span>
                <span class="chip2"><i class="fa-solid fa-capsules"></i> <span id="chipRx">ห้องยา 0 คน</span></span>
            </span>
            <b class="ptime" id="tlTime">--:-- น.</b>
        </div>
        <canvas id="timeline" aria-label="กราฟจำนวนผู้ป่วยตลอดวัน ลากเพื่อเลื่อนเวลา"></canvas>
        <div class="prow">
            <span class="speeds" id="speeds">
                <button data-s="1">×1</button><button data-s="10">×10</button><button data-s="30">×30</button>
                <button data-s="60">×60</button><button data-s="120">×120</button><button data-s="300">×300</button>
            </span>
            <span class="note" id="note">กำลังโหลดข้อมูล…</span>
        </div>
    </div>
</section>

<div class="modal" id="modal" hidden>
    <div class="mbox card" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
        <header><b id="modalTitle"></b><button id="modalClose" aria-label="ปิด"><i class="fa-solid fa-xmark"></i></button></header>
        <div id="modalBody"></div>
    </div>
</div>

<script>
window.FLOW = {
    api: '<?= BASE_URL ?>api/opd_flow.php',
    find: '<?= BASE_URL ?>api/opd_flow_find.php',
    people: '<?= BASE_URL ?>api/opd_flow_people.php',
    date: '<?= htmlspecialchars($d) ?>',
    today: '<?= $today ?>',
    loggedIn: <?= $loggedIn ? 'true' : 'false' ?>
};
</script>
<script src="<?= BASE_URL ?>assets/js/opd_monitor.js?v=<?= filemtime(__DIR__ . '/../assets/js/opd_monitor.js') ?>"></script>
</body>
</html>
