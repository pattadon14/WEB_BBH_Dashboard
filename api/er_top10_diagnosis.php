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
    $cacheFile = $cacheDir . 'er_top10_dx_' . $year . '.json';
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
    Query: Top 10 ICD-10 ผู้ป่วย ER
    ==========================
    */

    $sql = "
        SELECT
            v.pdx                                         AS icd10,
            i.name                                        AS diag_en,
            i.tname                                       AS diag_th,
            COUNT(*)                                      AS total,
            SUM(CASE WHEN p.sex = '1' THEN 1 ELSE 0 END) AS male,
            SUM(CASE WHEN p.sex = '2' THEN 1 ELSE 0 END) AS female
        FROM er_regist er
        LEFT JOIN vn_stat v
            ON v.vn = er.vn
        LEFT JOIN patient p
            ON p.hn = v.hn
        LEFT JOIN icd101 i
            ON i.code = v.pdx
        WHERE
            er.vstdate BETWEEN :startDate::date AND :endDate::date
            AND COALESCE(v.pdx, '') != ''
        GROUP BY
            v.pdx,
            i.name,
            i.tname
        ORDER BY
            total DESC
        LIMIT 10
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':startDate' => $startDate,
        ':endDate'   => $endDate,
    ]);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    /*
    ==========================
    จัด format + rank
    ==========================
    */

    $data = [];
    $rank = 1;

    foreach ($rows as $row) {
        $data[] = [
            'rank'    => $rank++,
            'icd10'   => $row['icd10']   ?? '',
            'diag_en' => $row['diag_en'] ?? '',
            'diag_th' => $row['diag_th'] ?? '',
            'total'   => (int)$row['total'],
            'male'    => (int)$row['male'],
            'female'  => (int)$row['female'],
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