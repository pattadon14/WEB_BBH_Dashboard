<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');
require_once '../config/database.php';

try {
    $ward = trim((string)($_GET['ward'] ?? ''));

    if ($ward === '' || !ctype_digit($ward)) {
        http_response_code(400);
        echo json_encode(['error' => 'ไม่พบรหัส Ward ที่ถูกต้อง'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /* ใช้เงื่อนไขแบบเดียวกับ API ภาพรวม Ward เพื่อให้ตัวเลขสอดคล้องกัน */
    $sql = "SELECT
                w.ward,
                w.name AS ward_name,
                COALESCE(w.bedcount, 0) AS bedcount,
                COUNT(i.an) FILTER (WHERE i.dchdate IS NULL) AS current_admit,
                COUNT(i.an) FILTER (
                    WHERE i.regdate >= CURRENT_DATE
                      AND i.regdate < CURRENT_DATE + INTERVAL '1 day'
                ) AS admit_today,
                COUNT(i.an) FILTER (
                    WHERE i.dchdate >= CURRENT_DATE
                      AND i.dchdate < CURRENT_DATE + INTERVAL '1 day'
                ) AS discharge_today
            FROM ward w
            LEFT JOIN ipt i ON i.ward::text = w.ward::text
            WHERE w.ward::text = :ward
            GROUP BY w.ward, w.name, w.bedcount
            LIMIT 1";

    $stmt = $conn->prepare($sql);
    $stmt->execute(['ward' => $ward]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        http_response_code(404);
        echo json_encode(['error' => 'ไม่พบข้อมูล Ward'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $bedcount = (int)$row['bedcount'];
    $currentAdmit = (int)$row['current_admit'];
    $admitToday = (int)$row['admit_today'];
    $dischargeToday = (int)$row['discharge_today'];
    $occupancy = $bedcount > 0 ? round(($currentAdmit / $bedcount) * 100, 2) : 0;

    echo json_encode([
        'ward' => $row['ward'],
        'ward_name' => $row['ward_name'],
        'bedcount' => $bedcount,
        'current_admit' => $currentAdmit,
        'admit_today' => $admitToday,
        'discharge_today' => $dischargeToday,
        'net_today' => $admitToday - $dischargeToday,
        'occupancy_rate' => $occupancy,
        'today' => date('Y-m-d')
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'เกิดข้อผิดพลาดในการโหลดข้อมูล Ward วันนี้',
        'detail' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
