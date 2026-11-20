<?php

header('Content-Type: application/json; charset=utf-8');

require_once '../config/database.php';
require_once '../config/period.php';
require_once '../config/opd.php';

try {

    // ช่วงเวลา: ?d1&d2 หรือ ?year (config/period.php)
    $p         = bbh_period();
    $startDate = $p['start'];
    $endDate   = $p['end'];

    /*
    ==========================
    Cache Settings — ช่วงที่รวมวันนี้ 5 นาที, ช่วงที่จบแล้ว 30 นาที
    ==========================
    */

    $cacheDir  = __DIR__ . '/../storage/cache/';
    $cacheFile = $cacheDir . 'opd_top10_dx_' . $p['key'] . '.json';
    $cacheTTL  = $endDate >= date('Y-m-d') ? 5 * 60 : 30 * 60;

    if (
        file_exists($cacheFile) &&
        (time() - filemtime($cacheFile)) < $cacheTTL
    ) {
        echo file_get_contents($cacheFile);
        exit;
    }

    /*
    ==========================
    Query: Top 10 ICD-10 ผู้ป่วยนอก
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
        FROM ovst o
        LEFT JOIN vn_stat v
            ON v.vn = o.vn
        LEFT JOIN patient p
            ON p.hn = v.hn
        LEFT JOIN icd101 i
            ON i.code = v.pdx
        WHERE
            o.vstdate BETWEEN :startDate::date AND :endDate::date
            AND o.main_dep IN (" . opd_main_dep_sql() . ")
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

} catch (InvalidArgumentException $e) {
    http_response_code(400);   // ช่วงวันที่ผิด — ข้อความของเราเอง แสดงได้
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    error_log('opd_top10_disease: ' . $e->getMessage());   // ไม่ส่งรายละเอียดฐานข้อมูลออกไปหน้าเว็บ
    http_response_code(500);
    echo json_encode(['error' => 'โหลดข้อมูลไม่สำเร็จ'], JSON_UNESCAPED_UNICODE);
}