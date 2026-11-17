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
    $cacheFile = $cacheDir . 'er_triage_v3_' . $p['key'] . '.json';
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
    Query: ระดับความเร่งด่วน 1–5 (นิยามเดียวกับต้นแบบ er-dashboard: LEVEL ใน queries.py)
      = er_emergency_level_id ถ้าว่าง (20% ที่บ้านบึง) ใช้ export_code ของ er_emergency_type (ลง 99%)
      แยก Black (er_emergency_type 6) ไว้เป็นแท่งของตัวเอง — ต้นแบบนับรวมในระดับ 5
      ไม่มีทั้ง 2 ช่อง = "ไม่ระบุระดับ" (แสดงไว้ ไม่ตัดทิ้งเงียบ ๆ)
    ==========================
    */

    $sql = "
        SELECT
            CASE
                WHEN er.er_emergency_type = 6 THEN 6
                ELSE COALESCE(
                    er.er_emergency_level_id,
                    CASE WHEN et.export_code ~ '^[0-9]+$' THEN LEAST(et.export_code::integer, 5) END
                )
            END       AS level_id,
            COUNT(*)  AS total
        FROM er_regist er
        LEFT JOIN er_emergency_type et
            ON et.er_emergency_type = er.er_emergency_type
        WHERE
            er.vstdate BETWEEN :startDate AND :endDate
        GROUP BY 1
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':startDate' => $startDate,
        ':endDate'   => $endDate,
    ]);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    /*
    ==========================
    สีและ label ตามระดับ (1 = รุนแรงสุด) · 0 = ไม่ระบุระดับ
    ระวัง: รหัส er_emergency_type ของบ้านบึงไม่เรียงตามความรุนแรง (1 = Emergency, 2 = Resuscitation)
    SQL ด้านบนแปลงเป็นระดับแล้ว
    ==========================
    */

    $triageConfig = [
        1 => ['label' => 'Resuscitation (กู้ชีพทันที)', 'color' => '#f01a1a'],
        2 => ['label' => 'Emergency (ฉุกเฉินด่วน)',     'color' => '#dc35b2'],
        3 => ['label' => 'Urgent (ด่วนมาก)',            'color' => '#13a142'],
        4 => ['label' => 'Semi Urgent (ด่วน)',          'color' => '#07c5ff'],
        5 => ['label' => 'Non Urgent (รอได้)',          'color' => '#6c757d'],
        6 => ['label' => 'Black (เสียชีวิต)',           'color' => '#343a40'],
        0 => ['label' => 'ไม่ระบุระดับ',                'color' => '#cbd5e1'],
    ];

    // เรียงจากรุนแรงมากไปน้อย ไม่ระบุไว้ท้ายสุด
    usort($rows, fn($x, $y) => ((int)$x['level_id'] ?: 99) <=> ((int)$y['level_id'] ?: 99));

    $data = [];

    foreach ($rows as $row) {

        $id     = (int)$row['level_id'];   // NULL → 0 = ไม่ระบุระดับ
        $config = $triageConfig[$id] ?? [
            'label' => 'ประเภท ' . $id,
            'color' => '#adb5bd',
        ];

        $data[] = [
            'id'    => $id,
            'name'  => $config['label'],
            'color' => $config['color'],
            'y'     => (int)$row['total'],
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

} catch (InvalidArgumentException $e) {
    http_response_code(400);   // ช่วงวันที่ผิด — ข้อความของเราเอง แสดงได้
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    error_log('er_triage: ' . $e->getMessage());   // ไม่ส่งรายละเอียดฐานข้อมูลออกไปหน้าเว็บ
    http_response_code(500);
    echo json_encode(['error' => 'โหลดข้อมูลไม่สำเร็จ'], JSON_UNESCAPED_UNICODE);
}