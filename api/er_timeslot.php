<?php

header('Content-Type: application/json; charset=utf-8');

require_once '../config/database.php';

try {

    $year = isset($_GET['year'])
        ? (int)$_GET['year']
        : date('Y') + 543;

    /*
    ==========================
    ปีงบประมาณ
    ==========================
    */

    $startDate = ($year - 544) . '-10-01';
    $endDate   = ($year - 543) . '-09-30';

    /*
    ==========================
    Cache Settings
    ==========================
    */

    $cacheDir  = __DIR__ . '/../storage/cache/';
    $cacheFile = $cacheDir . 'er_timeslot_' . $year . '.json';
    $cacheTTL  = 5 * 60;

    if (
        file_exists($cacheFile) &&
        (time() - filemtime($cacheFile)) < $cacheTTL
    ) {
        echo file_get_contents($cacheFile);
        exit;
    }

    /*
    ==========================
    Query: แยกช่วงเวลาจาก enter_er_time
    เช้า  08:00–15:59
    บ่าย  16:00–23:59
    ดึก   00:00–07:59
    ==========================
    */

    $sql = "
        SELECT
            CASE
                WHEN EXTRACT(HOUR FROM enter_er_time) BETWEEN 8 AND 15
                    THEN 'เช้า (08:00–16:00)'
                WHEN EXTRACT(HOUR FROM enter_er_time) BETWEEN 16 AND 23
                    THEN 'บ่าย (16:00–00:00)'
                ELSE
                    'ดึก (00:00–08:00)'
            END AS time_slot,

            CASE
                WHEN EXTRACT(HOUR FROM enter_er_time) BETWEEN 8 AND 15
                    THEN 1
                WHEN EXTRACT(HOUR FROM enter_er_time) BETWEEN 16 AND 23
                    THEN 2
                ELSE
                    3
            END AS slot_order,

            COUNT(*) AS total

        FROM er_regist

        WHERE
            vstdate BETWEEN :startDate::date AND :endDate::date
            AND enter_er_time IS NOT NULL
            AND enter_er_time > '1900-01-01'::timestamp

        GROUP BY
            time_slot,
            slot_order

        ORDER BY
            slot_order ASC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':startDate' => $startDate,
        ':endDate'   => $endDate,
    ]);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    /*
    ==========================
    กำหนดสีแต่ละช่วง
    ==========================
    */

    $colorMap = [
        1 => '#f59e0b',  // เช้า — เหลืองส้ม
        2 => '#3b82f6',  // บ่าย — ฟ้า
        3 => '#6366f1',  // ดึก  — ม่วง
    ];

    $data = [];
    foreach ($rows as $row) {
        $order  = (int)$row['slot_order'];
        $data[] = [
            'name'  => $row['time_slot'],
            'y'     => (int)$row['total'],
            'color' => $colorMap[$order] ?? '#adb5bd',
            'order' => $order,
        ];
    }

    /*
    ==========================
    Output + Cache  ← จุดที่หายไป
    ==========================
    */

    $json = json_encode($data, JSON_UNESCAPED_UNICODE);

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