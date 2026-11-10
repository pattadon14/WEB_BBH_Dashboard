<?php

header('Content-Type: application/json');

require_once '../config/database.php';
require_once '../config/opd.php';

$budgetYear = isset($_GET['year'])
    ? (int)$_GET['year']
    : (date('Y') + 543 + (date('n') >= 10 ? 1 : 0));

/*
ปีงบประมาณปัจจุบัน
= 2025-10-01 ถึง 2026-09-30
*/

$yearAD = $budgetYear - 543;

$startDate = ($yearAD - 1) . '-10-01';
$endDate = $yearAD . '-09-30';

// สแกน ovst ทั้งปีงบ จึง cache 30 นาทีแบบเดียวกับ 10 อันดับโรค
$cacheDir  = __DIR__ . '/../storage/cache/';
$cacheFile = $cacheDir . 'opd_trend_' . $budgetYear . '.json';

if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 30 * 60) {
    echo file_get_contents($cacheFile);
    exit;
}

// ผู้ป่วยนอกรายเดือน (นับ visit ชุด OPD) — ผู้ป่วยในดูที่หน้า IPD
$sql = "SELECT
    EXTRACT(MONTH FROM vstdate) AS month,
    COUNT(DISTINCT vn) AS opd
FROM ovst
WHERE vstdate BETWEEN :startDate
AND :endDate
AND main_dep IN (" . opd_main_dep_sql() . ")
GROUP BY month";

$stmt = $conn->prepare($sql);

$stmt->execute([
    ':startDate' => $startDate,
    ':endDate' => $endDate
]);

$json = json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));

if (!is_dir($cacheDir)) {
    mkdir($cacheDir, 0755, true);
}
file_put_contents($cacheFile, $json);

echo $json;