<?php

// Start the Provider ID session before any HTML output is sent.
// This is required so the session cookie can be created and the
// Provider ID login state remains available to navbar.php.
require_once __DIR__ . '/../auth/provider.php';
require_once __DIR__ . '/../config/app.php';

?>

<!DOCTYPE html>
<html lang="th">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Dashboard BBH</title>

    <script>
        // ธีมสว่าง/มืด: ตั้งก่อนโหลด CSS เพื่อไม่ให้หน้ากระพริบเป็นสีขาว
        // ค่าเริ่มต้น = โหมดสว่าง · กดปุ่มเลือกแล้วจำไว้ในเครื่องนั้น (localStorage)
        (function () {
            var t = null;
            try { t = localStorage.getItem('bbh-theme'); } catch (e) {}
            if (t !== 'dark') t = 'light';
            document.documentElement.setAttribute('data-theme', t);
            document.documentElement.setAttribute('data-bs-theme', t);   // ตาราง/ฟอร์ม/dropdown ของ Bootstrap 5.3 (AdminLTE 4)
        })();
        window.bbhToggleTheme = function () {
            var next = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', next);
            document.documentElement.setAttribute('data-bs-theme', next);
            try { localStorage.setItem('bbh-theme', next); } catch (e) {}
            document.querySelectorAll('[data-theme-toggle]').forEach(function (b) { b.setAttribute('aria-pressed', String(next === 'dark')); });
        };
    </script>
    <!-- ไอคอนแท็บ = ไอคอนเดียวกับเว็บหลัก รพ. (WordPress Site Icon ที่ /favicon.ico ของโดเมนหลัก)
         ใช้ URL เต็มเพื่อให้เครื่องทดสอบ (localhost) เห็นเหมือนเว็บจริง และเปลี่ยนตามเมื่อเว็บหลักเปลี่ยนไอคอน -->
    <link rel="icon" href="https://banbunghospital.moph.go.th/favicon.ico">
    <!-- ไอคอนเมื่อบันทึกลงหน้าจอโทรศัพท์ -->
    <link rel="apple-touch-icon" href="<?= BASE_URL ?>assets/img/apple-touch-icon.png">

    <!-- AdminLTE -->
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/AdminLTE/dist/css/adminlte.min.css">
    <!-- ?v= เวลาแก้ไฟล์ — ผู้ใช้ได้ CSS ใหม่ทันทีหลัง git pull ไม่ค้าง cache -->
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/custom.css?v=<?= filemtime(__DIR__ . '/../assets/css/custom.css') ?>">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/sidebar.css?v=<?= filemtime(__DIR__ . '/../assets/css/sidebar.css') ?>">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/ipd_ward_detail.css?v=<?= filemtime(__DIR__ . '/../assets/css/ipd_ward_detail.css') ?>">

    <!-- Font Awesome 6 (รองรับ class แบบ fas/far ของ v5 ด้วย) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- OPD page styles -->
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/opd_tabs.css?v=<?= filemtime(__DIR__ . '/../assets/css/opd_tabs.css') ?>">

    <!-- Login styles -->
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/login.css?v=<?= filemtime(__DIR__ . '/../assets/css/login.css') ?>">

    <!-- ธีมกลางทุกหน้า (โหลดท้ายสุด) -->
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/theme.css?v=<?= filemtime(__DIR__ . '/../assets/css/theme.css') ?>">

    <!-- โหมดมืด: dark.generated.css สร้างจาก tools/build_dark_css.mjs · dark.css = ปรับด้วยมือ (ทำงานเฉพาะ html[data-theme="dark"]) -->
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/dark.generated.css?v=<?= filemtime(__DIR__ . '/../assets/css/dark.generated.css') ?>">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/dark.css?v=<?= filemtime(__DIR__ . '/../assets/css/dark.css') ?>">

    <style>
        /* =========================================================
           OPD SUMMARY - BALANCED COLUMNS
           ทำให้ 4 ช่องหลักมีความกว้างและความสูงสมดุลกัน
        ========================================================= */
        .opd-summary-horizontal .opd-summary-grid {
            display: grid !important;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            align-items: stretch;
        }

        .opd-summary-horizontal .opd-summary-section {
            min-width: 0;
            min-height: 170px;
            display: flex;
            flex-direction: column;
            box-sizing: border-box;
        }

        .opd-summary-horizontal .opd-summary-section > .horizontal-summary-item,
        .opd-summary-horizontal .opd-summary-section > .appointment-horizontal-card,
        .opd-summary-horizontal .opd-summary-section > .time-horizontal-group,
        .opd-summary-horizontal .opd-summary-section > .exam-horizontal-group {
            flex: 1;
            min-height: 125px;
            box-sizing: border-box;
        }

        /* ช่องเวลา 3 Card แบ่งความสูงเท่า ๆ กัน */
        .opd-summary-horizontal .time-horizontal-group {
            display: flex;
            flex-direction: column;
            gap: 7px;
        }

        .opd-summary-horizontal .time-horizontal-card {
            flex: 1;
            min-height: 0;
        }

        /* ช่องสถานะ 3 Card แบ่งความสูงเท่า ๆ กัน */
        .opd-summary-horizontal .exam-horizontal-group {
            display: flex;
            flex-direction: column;
            gap: 7px;
        }

        .opd-summary-horizontal .exam-horizontal-card {
            flex: 1;
            min-height: 0;
        }

        /* Appointment ให้เต็มความสูงของช่อง */
        .opd-summary-horizontal .appointment-horizontal-card {
            display: flex;
            flex-direction: column;
        }

        .opd-summary-horizontal .appointment-horizontal-card .appointment-total {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .opd-summary-horizontal .appointment-horizontal-card .appointment-status {
            flex: 1;
        }

        /* Walk-in ให้เต็มพื้นที่ */
        .opd-summary-horizontal .horizontal-summary-item {
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        @media (max-width: 991px) {
            .opd-summary-horizontal .opd-summary-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 576px) {
            .opd-summary-horizontal .opd-summary-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <script>
        // รีเฟรชข้อมูลอัตโนมัติเฉพาะตอนแท็บเปิดดูอยู่ (ย่อ/สลับแท็บ/ล็อกจอ = หยุดดึงข้อมูล ลดภาระฐานข้อมูล HOSxP)
        // กลับมาดูหลังหายไปนานกว่า 1 รอบ จะโหลดใหม่ทันที
        window.bbhPoll = function (fn, ms) {
            let last = Date.now();
            const run = () => { last = Date.now(); fn(); };
            setInterval(() => { if (!document.hidden && Date.now() - last >= ms - 1000) run(); }, ms);
            document.addEventListener('visibilitychange', () => {
                if (!document.hidden && Date.now() - last >= ms) run();
            });
        };

        // ป้าย Live: แถบหัวของหน้า (badgeId) + ป้าย LIVE ใน navbar (ต้องตั้ง $showLiveStatus ก่อน include navbar.php)
        // แสดงเฉพาะตอนดูข้อมูลวันนี้ซึ่งรีเฟรชอัตโนมัติ — เรียกทุกครั้งหลังโหลดข้อมูลสำเร็จ เวลาใน navbar จะเป็นเวลาล่าสุด
        window.bbhSetLive = function (isLive, badgeId) {
            const badge = badgeId && document.getElementById(badgeId);
            if (badge) badge.hidden = !isLive;
            const box = document.getElementById('live-status');
            if (!box) return;
            box.style.display = isLive ? '' : 'none';
            if (!isLive) return;
            const now = new Date();
            const text = 'ข้อมูล ณ วันที่ ' +
                now.toLocaleDateString('th-TH', { day: 'numeric', month: 'long', year: 'numeric' }) +
                ' เวลา ' + now.toLocaleTimeString('th-TH', { hour: '2-digit', minute: '2-digit', second: '2-digit' }) + ' น.';
            const label = document.getElementById('last-updated');
            if (label) label.textContent = text;
            box.title = text;   // จอเล็กซ่อนข้อความยาว ดูได้จาก tooltip
        };
    </script>

</head>

<body class="hold-transition sidebar-mini layout-fixed layout-navbar-fixed">

    <div class="wrapper">