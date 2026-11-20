<?php

header('Content-Type: application/json; charset=utf-8');

require_once '../config/database.php';
require_once '../config/period.php';
require_once '../config/opd.php';

try {

    // ช่วงเวลา: ?d1&d2 (เดือนนี้ / กำหนดเอง) หรือ ?year (ปีงบ) — config/period.php
    $p = bbh_period();

    // ช่วงที่รวมวันนี้ cache 5 นาที, ช่วงที่จบแล้ว 30 นาที
    $cacheDir  = __DIR__ . '/../storage/cache/';
    $cacheFile = $cacheDir . 'opd_trend_v2_' . $p['key'] . '.json';
    $cacheTTL  = $p['end'] >= date('Y-m-d') ? 5 * 60 : 30 * 60;

    if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTTL) {
        echo file_get_contents($cacheFile);
        exit;
    }

    // ผู้ป่วยนอกรายวัน/รายเดือน (นับ visit ทุกแผนก OPD — ตรวจโรคทั่วไป + คลินิคพิเศษ)
    $sql = "SELECT
            TO_CHAR(DATE_TRUNC(:unit, vstdate), 'YYYY-MM-DD') AS bucket,
            COUNT(DISTINCT vn) AS opd
        FROM ovst
        WHERE vstdate BETWEEN :startDate AND :endDate
          AND main_dep IN (" . opd_main_dep_sql() . ")
        GROUP BY 1";

    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':unit'      => $p['unit'],
        ':startDate' => $p['start'],
        ':endDate'   => $p['end'],
    ]);
    $byBucket = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    $trend = [];
    foreach (bbh_period_buckets($p) as $bucket => $label) {
        $trend[] = [
            'label' => $label,
            'start' => $bucket,   // วันแรกของช่อง (คลิกแท่ง → รายชื่อช่วงนั้น)
            'opd'   => (int)($byBucket[$bucket] ?? 0),
        ];
    }

    $json = json_encode(['unit' => $p['unit'], 'trend' => $trend], JSON_UNESCAPED_UNICODE);

    if (!is_dir($cacheDir)) {
        mkdir($cacheDir, 0755, true);
    }
    file_put_contents($cacheFile, $json);

    echo $json;

} catch (InvalidArgumentException $e) {
    http_response_code(400);   // ช่วงวันที่ผิด — ข้อความของเราเอง แสดงได้
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    error_log('service_trend: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'โหลดข้อมูลไม่สำเร็จ'], JSON_UNESCAPED_UNICODE);
}
