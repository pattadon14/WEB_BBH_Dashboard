<!DOCTYPE html>
<html lang="th">

<?php

require_once __DIR__ . '/../config/app.php';

?>

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Dashboard BBH</title>

    <!-- AdminLTE -->
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/AdminLTE/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/custom.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/sidebar.css">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/AdminLTE/plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- OPD page styles -->
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/opd_tabs.css">

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

</head>

<body class="hold-transition sidebar-mini layout-fixed layout-navbar-fixed">

    <div class="wrapper">