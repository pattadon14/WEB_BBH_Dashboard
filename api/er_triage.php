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
    $cacheFile = $cacheDir . 'er_triage_v4_' . $p['key'] . '.json';
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
    Query: ระดับความเร่งด่วน (ER_LEVEL_SQL ใน config/er.php — ตรงกับต้นแบบ er-dashboard)
      แยก Black (er_emergency_type 6) ไว้เป็นแท่งของตัวเอง — ต้นแบบนับรวมในระดับ 5
      ไม่มีระดับเลย = "ไม่ระบุระดับ" (แสดงไว้ ไม่ตัดทิ้งเงียบ ๆ)
    ==========================
    */

    $level = ER_LEVEL_SQL;
    $sql = "
        SELECT
            $level    AS level_id,
            COUNT(*)  AS total
        FROM er_regist e
        LEFT JOIN er_emergency_type et
            ON et.er_emergency_type = e.er_emergency_type
        WHERE
            e.vstdate BETWEEN :startDate AND :endDate
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
        // ชื่อและสีตามป้ายจำแนกผู้ป่วยหน้าห้อง ER (แดง / ชมพู / เหลือง / เขียว / ขาว) — ชุดเดียวกับ .er-tri-* ใน assets/css/er_theme.css
        1 => ['label' => '1 Resuscitation (ฉุกเฉินวิกฤต)',     'color' => '#dc2626'],
        2 => ['label' => '2 Emergency (เจ็บป่วยรุนแรง)',      'color' => '#c2389a'],
        3 => ['label' => '3 Urgency (ฉุกเฉินเร่งด่วน)',        'color' => '#facc15'],
        4 => ['label' => '4 Semi-Urgency (ฉุกเฉินไม่รุนแรง)', 'color' => '#65a30d'],
        5 => ['label' => '5 Non-Urgency (ผู้ป่วยทั่วไป)',     'color' => '#ffffff', 'border' => '#9ca3af'],
        6 => ['label' => 'Black (เสียชีวิต)',                 'color' => '#343a40'],
        0 => ['label' => 'ไม่ระบุระดับ',                      'color' => '#d1d5db'],
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
            'border' => $config['border'] ?? null,   // ระดับ 5 สีขาว ต้องมีขอบให้เห็นแท่งกราฟ
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