<?php

require_once '../config/cache.php';
api_cache('home_er'); // คำตอบเดียวกันทุกจอ รีเฟรชทุก 60 วินาที
require_once '../config/database.php';

header('Content-Type: application/json');

try {

    $sql = "
    SELECT
        COUNT(DISTINCT o.hn) AS month_patient,
        COUNT(DISTINCT o.vn) AS month_visit,
        COUNT(DISTINCT CASE
            WHEN er.vstdate = CURRENT_DATE
            THEN o.vn
        END) AS today,
        -- วันเดียวกันของสัปดาห์ก่อน ถึงเวลาเดียวกับตอนนี้ (วิธีเดียวกับ prev_week ใน api/er_today_summary.php)
        (SELECT COUNT(*) FROM er_regist WHERE vstdate = CURRENT_DATE - 7 AND enter_er_time::time <= LOCALTIME) AS prev_week
    FROM er_regist er

    LEFT JOIN ovst o
        ON o.vn = er.vn

    WHERE er.vstdate BETWEEN
        DATE_TRUNC('month', CURRENT_DATE)
        AND CURRENT_DATE
    ";

    $stmt = $conn->query($sql);

    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode($result);

} catch (PDOException $e) {
    error_log('er: ' . $e->getMessage());   // ไม่ส่งรายละเอียดฐานข้อมูลออกไปหน้าเว็บ
    http_response_code(500);
    echo json_encode(['error' => 'โหลดข้อมูลไม่สำเร็จ'], JSON_UNESCAPED_UNICODE);
}