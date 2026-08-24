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
    $cacheFile = $cacheDir . 'er_triage_' . $year . '.json';
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
    Map สีและ label ตามมาตรฐาน HosXp
    er_emergency_type:
      1 = Resuscitate  (ดำ)
      2 = Emergency    (แดง)
      3 = Urgency      (ส้ม)
      4 = Semi Urgency (เหลือง)
      5 = Non Urgency  (เขียว)
      6 = ไม่ระบุ/walk-in (เทา)
    ==========================
    */

    $triageConfig = [
        1 => ['label' => 'Resuscitate (กู้ชีพทันที)', 'color' => '#f01a1a'],
        2 => ['label' => 'Emergency (ฉุกเฉินด่วน)',   'color' => '#dc35b2'],
        3 => ['label' => 'Urgency (ด่วนมาก)',          'color' => '#13a142'],
        4 => ['label' => 'Semi Urgency (ด่วน)',        'color' => '#07c5ff'],
        5 => ['label' => 'Non Urgency (รอได้)',        'color' => '#6c757d'],
        6 => ['label' => 'ไม่ระบุ / Walk-in',          'color' => '#bdbdbd'],
    ];

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