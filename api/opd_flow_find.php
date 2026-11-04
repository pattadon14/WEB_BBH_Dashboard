<?php
/*
 * ค้นหาผู้ป่วยบน 3D Monitor — GET api/opd_flow_find.php?d=YYYY-MM-DD&q=HN หรือเลขคิว
 *
 * ต้อง login Provider ID (ข้อมูลรายบุคคล) · ส่งกลับเฉพาะรหัส visit สุ่มชุดเดียวกับ opd_flow.php
 * ไว้ให้หน้าเว็บชี้หุ่นตัวนั้น — ไม่ส่งชื่อ/HN/vn กลับไป
 */

require_once __DIR__ . '/../auth/provider.php';

if (!provider_is_logged_in()) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'กรุณาเข้าสู่ระบบก่อนค้นหา'], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/opd_flow_lib.php';

$q = trim((string) ($_GET['q'] ?? ''));
if (!preg_match('/^\d{1,12}$/', $q)) {
    flow_json(['error' => 'กรอก HN หรือเลขคิวเป็นตัวเลข'], 400);
}

try {
    // HN เก็บแบบเติม 0 ข้างหน้า — เทียบหลังตัด 0 นำหน้าทั้งสองฝั่ง
    $stmt = $conn->prepare("
        SELECT vn FROM ovst
        WHERE vstdate = :d
          AND (LTRIM(hn, '0') = LTRIM(:q, '0') OR CAST(oqueue AS varchar) = :q2)
        ORDER BY vsttime");
    $stmt->execute([':d' => flow_date(), ':q' => $q, ':q2' => $q]);
    $ids = array_map(fn($vn) => flow_vid((string) $vn), $stmt->fetchAll(PDO::FETCH_COLUMN));
    flow_json(['ids' => $ids]);
} catch (PDOException $e) {
    error_log('opd_flow_find: ' . $e->getMessage());
    flow_json(['error' => 'ค้นหาไม่สำเร็จ'], 500);
}
