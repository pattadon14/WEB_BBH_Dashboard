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
    $cacheFile = $cacheDir . 'er_triage_v2_' . $p['key'] . '.json';
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
    Query: Triage จาก er_emergency_type
    ==========================
    */

    $sql = "
        SELECT
            er_emergency_type  AS level_id,
            COUNT(*)           AS total
        FROM er_regist
        WHERE
            vstdate BETWEEN :startDate AND :endDate
            AND er_emergency_type IS NOT NULL
        GROUP BY
            er_emergency_type
        ORDER BY
            er_emergency_type ASC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':startDate' => $startDate,
        ':endDate'   => $endDate,
    ]);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    /*
    ==========================
    Map สีและ label ตามตาราง er_emergency_type ของ รพ.บ้านบึง (ตรวจจาก DB 2026-11-08)
    ระวัง: code ไม่ได้เรียงตามความรุนแรง
      1 = 2 Emergency      2 = 1 Resuscitation   3 = 3 Urgent
      4 = 4 Semi Urgent    5 = 5 Non Urgent      6 = 6 Black
    'order' = ลำดับแสดงผลจากรุนแรงมากไปน้อย
    ==========================
    */

    $triageConfig = [
        2 => ['order' => 1, 'label' => 'Resuscitation (กู้ชีพทันที)', 'color' => '#f01a1a'],
        1 => ['order' => 2, 'label' => 'Emergency (ฉุกเฉินด่วน)',     'color' => '#dc35b2'],
        3 => ['order' => 3, 'label' => 'Urgent (ด่วนมาก)',            'color' => '#13a142'],
        4 => ['order' => 4, 'label' => 'Semi Urgent (ด่วน)',          'color' => '#07c5ff'],
        5 => ['order' => 5, 'label' => 'Non Urgent (รอได้)',          'color' => '#6c757d'],
        6 => ['order' => 6, 'label' => 'Black (เสียชีวิต)',           'color' => '#343a40'],
    ];

    usort($rows, function ($x, $y) use ($triageConfig) {
        return ($triageConfig[(int)$x['level_id']]['order'] ?? 99) <=> ($triageConfig[(int)$y['level_id']]['order'] ?? 99);
    });

    $data = [];

    foreach ($rows as $row) {

        $id     = (int)$row['level_id'];
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

} catch (Exception $e) {

    echo json_encode([
        'error' => $e->getMessage()
    ]);

}