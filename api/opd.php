<?php

require_once '../config/cache.php';
api_cache('home_opd'); // คำตอบเดียวกันทุกจอ รีเฟรชทุก 60 วินาที
require_once '../config/database.php';
require_once '../config/opd.php';

header('Content-Type: application/json');

try {

    $sql = "SELECT COUNT
	( DISTINCT hn ) AS month_patient,
	COUNT ( DISTINCT vn ) AS month_visit,
	COUNT ( DISTINCT CASE WHEN vstdate = CURRENT_DATE THEN vn END ) AS today,
	-- วันเดียวกันของสัปดาห์ก่อน ถึงเวลาเดียวกับตอนนี้ (เทียบระหว่างวันได้ยุติธรรม)
	( SELECT COUNT ( DISTINCT vn ) FROM ovst WHERE vstdate = CURRENT_DATE - 7 AND vsttime <= LOCALTIME
	  AND main_dep IN (" . opd_main_dep_sql() . ") ) AS prev_week 
    FROM ovst 
    WHERE
	vstdate BETWEEN DATE_TRUNC( 'month', CURRENT_DATE ) AND CURRENT_DATE
	AND main_dep IN (" . opd_main_dep_sql() . ");
    ";

    $stmt = $conn->query($sql);

    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode($result);

} catch (PDOException $e) {

    error_log(basename(__FILE__) . ': ' . $e->getMessage());   // ไม่ส่งรายละเอียดฐานข้อมูลออกไปหน้าเว็บ
    http_response_code(500);
    echo json_encode(['error' => 'โหลดข้อมูลไม่สำเร็จ'], JSON_UNESCAPED_UNICODE);

}