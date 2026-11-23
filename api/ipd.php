<?php

require_once '../config/cache.php';
api_cache('home_ipd'); // คำตอบเดียวกันทุกจอ รีเฟรชทุก 60 วินาที
require_once '../config/database.php';

header('Content-Type: application/json');

try {

    $sql = "
    SELECT
        COUNT(DISTINCT hn) AS month_patient,
        COUNT(DISTINCT an) AS month_visit,
        COUNT(DISTINCT CASE
            WHEN regdate = CURRENT_DATE
            THEN an
        END) AS today,
        -- วันเดียวกันของสัปดาห์ก่อน ถึงเวลาเดียวกับตอนนี้
        (SELECT COUNT(DISTINCT an) FROM ipt WHERE regdate = CURRENT_DATE - 7 AND regtime <= LOCALTIME) AS prev_week
    FROM ipt
    WHERE regdate BETWEEN
        DATE_TRUNC('month', CURRENT_DATE)
        AND CURRENT_DATE
    ";

    $stmt = $conn->query($sql);

    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode($result);

} catch (PDOException $e) {

    error_log(basename(__FILE__) . ': ' . $e->getMessage());   // ไม่ส่งรายละเอียดฐานข้อมูลออกไปหน้าเว็บ
    http_response_code(500);
    echo json_encode(['error' => 'โหลดข้อมูลไม่สำเร็จ'], JSON_UNESCAPED_UNICODE);

}