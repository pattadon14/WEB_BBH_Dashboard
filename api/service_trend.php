<?php

header('Content-Type: application/json');

require_once '../config/database.php';

$budgetYear = $_GET['year'] ?? 2569;

/*
งบ 2569
= 2025-10-01 ถึง 2026-09-30
*/

$yearAD = $budgetYear - 543;

$startDate = ($yearAD - 1) . '-10-01';
$endDate = $yearAD . '-09-30';

$sql = "SELECT
    EXTRACT(MONTH FROM vstdate) AS month,
    COUNT(DISTINCT vn) AS opd,
    0 AS ipd
FROM ovst
WHERE vstdate BETWEEN :startDate
AND :endDate
GROUP BY month
UNION ALL
SELECT
    EXTRACT(MONTH FROM regdate) AS month,
    0 AS opd,
    COUNT(DISTINCT an) AS ipd
FROM ipt
WHERE regdate BETWEEN :startDate
AND :endDate
GROUP BY month";

$stmt = $conn->prepare($sql);

$stmt->execute([
    ':startDate' => $startDate,
    ':endDate' => $endDate
]);

$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($data);