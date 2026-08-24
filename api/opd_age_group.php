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
    $cacheFile = $cacheDir . 'opd_age_group_' . $year . '.json';
    $cacheTTL  = 30 * 60; // 30 นาที

    if (
        file_exists($cacheFile) &&
        (time() - filemtime($cacheFile)) < $cacheTTL
    ) {
        echo file_get_contents($cacheFile);
        exit;
    }

    /*
    ==========================
    Query: แบ่งกลุ่มอายุ
    คำนวณจาก birthday เพื่อความแม่นยำ
    fallback ใช้ age_y ถ้าไม่มี birthday
    ==========================
    */

    $sql = "
        SELECT
            CASE
                WHEN DATE_PART('year', AGE(o.vstdate, p.birthday)) BETWEEN 0  AND 5  THEN 1
                WHEN DATE_PART('year', AGE(o.vstdate, p.birthday)) BETWEEN 6  AND 14 THEN 2
                WHEN DATE_PART('year', AGE(o.vstdate, p.birthday)) BETWEEN 15 AND 59 THEN 3
                WHEN DATE_PART('year', AGE(o.vstdate, p.birthday)) >= 60             THEN 4
                ELSE 0
            END AS age_group,

            COUNT(*)                                       AS total,
            SUM(CASE WHEN p.sex = '1' THEN 1 ELSE 0 END)  AS male,
            SUM(CASE WHEN p.sex = '2' THEN 1 ELSE 0 END)  AS female

        FROM ovst o
        LEFT JOIN patient p
            ON p.hn = o.hn
        WHERE
            o.vstdate BETWEEN :startDate::date AND :endDate::date
            AND p.birthday IS NOT NULL

        GROUP BY age_group
        ORDER BY age_group ASC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':startDate' => $startDate,
        ':endDate'   => $endDate,
    ]);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    /*
    ==========================
    Map กลุ่มอายุ + สี
    ==========================
    */

    $groupConfig = [
        1 => ['label' => 'เด็กเล็ก (0-5 ปี)',     'color' => '#f59e0b'],
        2 => ['label' => 'เด็ก (6-14 ปี)',          'color' => '#3b82f6'],
        3 => ['label' => 'วัยทำงาน (15-59 ปี)',     'color' => '#198754'],
        4 => ['label' => 'ผู้สูงอายุ (60 ปีขึ้นไป)', 'color' => '#dc3545'],
    ];

    // เตรียม map จาก query
    $resultMap = [];
    foreach ($rows as $row) {
        $resultMap[(int)$row['age_group']] = $row;
    }

    // สร้าง data ครบทุกกลุ่ม (กลุ่มที่ไม่มีข้อมูลใส่ 0)
    $data = [];
    foreach ($groupConfig as $id => $config) {
        $row    = $resultMap[$id] ?? [];
        $data[] = [
            'id'     => $id,
            'name'   => $config['label'],
            'color'  => $config['color'],
            'total'  => (int)($row['total']  ?? 0),
            'male'   => (int)($row['male']   ?? 0),
            'female' => (int)($row['female'] ?? 0),
        ];
    }

    /*
    ==========================
    Output + Cache
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