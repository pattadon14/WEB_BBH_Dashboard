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
    $cacheFile = $cacheDir . 'er_top10_dx_v2_' . $p['key'] . '.json';
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
            -- ไม่นับงานบริการ (er_pt_type 4 ทำแผล/ฉีดยา/ใบรับรองแพทย์, 5 ห้องทำแผลเวรเช้า)
            -- และโรคหลักรหัส Z (ทำแผล ตัดไหม วัคซีน ติดตามอาการ ~10% ของ ER) — ตรงกับต้นแบบ er-dashboard
            AND COALESCE(er.er_pt_type, 0) NOT IN (4, 5)
            AND v.pdx NOT LIKE 'Z%'
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
    error_log('er_top10_diagnosis: ' . $e->getMessage());   // ไม่ส่งรายละเอียดฐานข้อมูลออกไปหน้าเว็บ
    http_response_code(500);
    echo json_encode(['error' => 'โหลดข้อมูลไม่สำเร็จ'], JSON_UNESCAPED_UNICODE);
}