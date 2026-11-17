<?php

header('Content-Type: application/json; charset=utf-8');

require_once '../config/cache.php';
api_cache('home_service_summary'); // คำตอบเดียวกันทุกจอ รีเฟรชทุก 60 วินาที
require_once '../config/database.php';
require_once '../config/services.php'; // นิยามแต่ละบริการ — ชุดเดียวกับ popup รายละเอียด (api/service_detail.php)

try {
    $out = [];
    foreach (service_defs() as $key => $def) {
        $out[$key . '_total'] = service_count($conn, $def['sql'], date('Y-m-d'));
    }
    echo json_encode($out, JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    error_log('service_summary_today: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'โหลดข้อมูลไม่สำเร็จ'], JSON_UNESCAPED_UNICODE);
}