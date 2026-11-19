<?php

header('Content-Type: application/json; charset=utf-8');

require_once '../config/database.php';
require_once '../config/period.php';
require_once '../config/er.php';

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
    $cacheFile = $cacheDir . 'er_chart_v7_' . $p['key'] . '.json';
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
            'start'    => $bucket,   // วันแรกของช่อง (คลิกแท่ง → รายชื่อช่วงนั้น)
            'er'       => (int)($byBucket[$bucket]['er_total'] ?? 0),
            'accident' => (int)($byBucket[$bucket]['accident_total'] ?? 0),
        ];
    }

    /*
    ==========================
    Pie Chart — สัดส่วนประเภทผู้ป่วย
    ==========================
    */

    // l12 = ระดับ 1-2 (ER_LEVEL_SQL) · admit = มี ipt ของ visit (ชุดเดียวกับ ER_DCH_SQL) — แสดงใน tooltip ให้เห็นว่าแต่ละประเภทหนักแค่ไหน
    $level = ER_LEVEL_SQL;
    $sqlPie = "SELECT
            e.er_pt_type   AS ertype,
            pt.name        AS typename,
            COUNT(*)       AS total,
            COUNT(*) FILTER (WHERE ($level) IN (1, 2)) AS l12,
            COUNT(*) FILTER (WHERE EXISTS (SELECT 1 FROM ipt ai WHERE ai.vn = e.vn)) AS admit
        FROM er_regist e
        LEFT JOIN er_pt_type pt
            ON pt.er_pt_type = e.er_pt_type
        LEFT JOIN er_emergency_type et
            ON et.er_emergency_type = e.er_emergency_type
        WHERE
            e.vstdate BETWEEN :startDate AND :endDate
        GROUP BY
            e.er_pt_type,
            pt.name
        ORDER BY e.er_pt_type
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

    // สีตามประเภท (โทนอุ่นของหน้า ER แต่ hue ต่างกันพอแยกได้) — บริการที่ไม่ใช่การรักษา = เทา
    $typeColor = [1 => '#b3261e', 2 => '#ea580c', 3 => '#ca8a04', 4 => '#64748b', 5 => '#db2777'];

    $pie = [];
    foreach ($pieRows as $row) {
        $pie[] = [
            'name'  => $shortName[(int)$row['ertype']] ?? ($row['typename'] ?? 'ไม่ระบุ'),
            'y'     => (int)$row['total'],
            'color' => $typeColor[(int)$row['ertype']] ?? '#94a3b8',
            'code'  => $row['ertype'] === null ? null : (int)$row['ertype'],
            'l12'   => (int)$row['l12'],
            'admit' => (int)$row['admit'],
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

} catch (InvalidArgumentException $e) {
    http_response_code(400);   // ช่วงวันที่ผิด — ข้อความของเราเอง แสดงได้
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    error_log('er_chart: ' . $e->getMessage());   // ไม่ส่งรายละเอียดฐานข้อมูลออกไปหน้าเว็บ
    http_response_code(500);
    echo json_encode(['error' => 'โหลดข้อมูลไม่สำเร็จ'], JSON_UNESCAPED_UNICODE);
}