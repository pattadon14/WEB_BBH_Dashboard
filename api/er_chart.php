<?php

header('Content-Type: application/json; charset=utf-8');

require_once '../config/database.php';
require_once '../config/period.php';

try {

    // ช่วงเวลา: ?d1&d2 หรือ ?year (config/period.php)
    $p         = bbh_period();
    $startDate = $p['start'];
    $endDate   = $p['end'];

    /*
    ==========================
    Cache Settings
    ==========================
    */

    $cacheDir  = __DIR__ . '/../storage/cache/';
    $cacheFile = $cacheDir . 'er_chart_v4_' . $p['key'] . '.json';
    $cacheTTL  = 5 * 60; // 5 นาที

    if (
        file_exists($cacheFile) &&
        (time() - filemtime($cacheFile)) < $cacheTTL
    ) {
        echo file_get_contents($cacheFile);
        exit;
    }

    /*
    ==========================
    ER Trend รายวัน/รายเดือน (ตาม unit ของช่วงเวลา)
    er       = ผู้รับบริการ ER ทุกประเภท (นับครั้ง จาก er_regist ตรงกับการ์ด ER หน้าหลัก)
    accident = เฉพาะ er_pt_type 2 ผู้ป่วยอุบัติเหตุ (เดิมนับ 2,5 แต่ 5 = ห้องทำแผลเวรเช้า ไม่ใช่อุบัติเหตุ)
    ==========================
    */

    $sqlTrend = "
        SELECT
            TO_CHAR(DATE_TRUNC(:unit, vstdate), 'YYYY-MM-DD') AS bucket,
            COUNT(*) AS er_total,
            COUNT(*) FILTER (WHERE er_pt_type = 2) AS accident_total
        FROM er_regist
        WHERE
            vstdate BETWEEN :startDate AND :endDate
        GROUP BY 1
    ";

    $stmtTrend = $conn->prepare($sqlTrend);
    $stmtTrend->execute([
        ':unit'      => $p['unit'],
        ':startDate' => $startDate,
        ':endDate'   => $endDate,
    ]);
    $byBucket = [];
    foreach ($stmtTrend->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $byBucket[$row['bucket']] = $row;
    }

    $trend = [];
    foreach (bbh_period_buckets($p) as $bucket => $label) {
        $trend[] = [
            'label'    => $label,
            'er'       => (int)($byBucket[$bucket]['er_total'] ?? 0),
            'accident' => (int)($byBucket[$bucket]['accident_total'] ?? 0),
        ];
    }

    /*
    ==========================
    Pie Chart — สัดส่วนประเภทผู้ป่วย
    ==========================
    */

    $sqlPie = "SELECT
            er.er_pt_type  AS ertype,
            et.name        AS typename,
            COUNT(*)       AS total
        FROM er_regist er
        LEFT JOIN er_pt_type et
            ON et.er_pt_type = er.er_pt_type
        WHERE
            er.vstdate BETWEEN :startDate AND :endDate
        GROUP BY
            er.er_pt_type,
            et.name
        ORDER BY total DESC
    ";

    $stmtPie = $conn->prepare($sqlPie);
    $stmtPie->execute([
        ':startDate' => $startDate,
        ':endDate'   => $endDate,
    ]);
    $pieRows = $stmtPie->fetchAll(PDO::FETCH_ASSOC);

    // ชื่อใน er_pt_type ยาวและถูกตัดกลางคำ (เช่น "...ตั้งแต่") ใช้ชื่อสั้นตาม code ของ รพ.บ้านบึง
    $shortName = [
        1 => 'ฉุกเฉิน (ไม่ใช่อุบัติเหตุ)',
        2 => 'อุบัติเหตุ',
        3 => 'ตรวจโรคทั่วไป',
        4 => 'บริการอื่น (ทำแผล/ฉีดยา/ใบรับรองแพทย์)',
        5 => 'ห้องทำแผล เวรเช้า',
    ];

    $pie = [];
    foreach ($pieRows as $row) {
        $pie[] = [
            'name' => $shortName[(int)$row['ertype']] ?? ($row['typename'] ?? 'ไม่ระบุ'),
            'y'    => (int)$row['total'],
        ];
    }

    /*
    ==========================
    Output
    ==========================
    */

    $result = [
        'unit'  => $p['unit'],
        'trend' => $trend,
        'pie'   => $pie,
    ];

    $json = json_encode($result, JSON_UNESCAPED_UNICODE);

    // บันทึก Cache
    if (!is_dir($cacheDir)) {
        mkdir($cacheDir, 0755, true);
    }
    file_put_contents($cacheFile, $json);

    echo $json;

} catch (Exception $e) {

    echo json_encode([
        'error' => $e->getMessage()
    ]);

}