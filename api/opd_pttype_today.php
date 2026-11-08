<?php

header('Content-Type: application/json; charset=utf-8');

require_once '../config/database.php';
require_once '../config/opd.php';

try {

    $targetDate = $_GET['date'] ?? date('Y-m-d');
    $dateObj = DateTime::createFromFormat('Y-m-d', $targetDate);
    if (!$dateObj || $dateObj->format('Y-m-d') !== $targetDate) {
        throw new Exception('วันที่ไม่ถูกต้อง');
    }

    $sql = "

    SELECT
        ov.pttype,
        pt.name AS pttype_name,
        COUNT(*) AS total_patient

    FROM ovst ov

    LEFT JOIN pttype pt
        ON pt.pttype = ov.pttype

    WHERE ov.vstdate = :targetDate
      AND ov.main_dep IN (" . opd_main_dep_sql() . ")

    GROUP BY
        ov.pttype,
        pt.name

    ORDER BY total_patient DESC

    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute([':targetDate' => $targetDate]);

    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE
    );

} catch(Exception $e){

    echo json_encode([
        'error' => $e->getMessage()
    ]);
}