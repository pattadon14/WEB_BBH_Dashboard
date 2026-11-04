<?php
/*
 * ชื่อ-สกุล + HN ของผู้ป่วยบน 3D Monitor — GET api/opd_flow_people.php?d=YYYY-MM-DD
 *
 * ข้อมูลส่วนบุคคล (PDPA): ต้อง login Provider ID · ไม่ cache ลงดิสก์
 * คืน { รหัสสุ่มของ visit (ชุดเดียวกับ opd_flow.php): [HN, ชื่อ-สกุล] }
 */

require_once __DIR__ . '/../auth/provider.php';

if (!provider_is_logged_in()) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'กรุณาเข้าสู่ระบบ'], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/opd_flow_lib.php';

$d = flow_date();

try {
    $stmt = $conn->prepare("
        SELECT o.vn, o.hn,
          TRIM(COALESCE(p.pname, '') || COALESCE(p.fname, '') || ' ' || COALESCE(p.lname, '')) nm
        FROM ovst o LEFT JOIN patient p ON p.hn = o.hn
        WHERE o.vstdate = :d AND (o.an IS NULL OR o.an = '')");
    $stmt->execute([':d' => $d]);
    $out = [];
    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $out[flow_vid((string) $r['vn'])] = [(string) $r['hn'], (string) $r['nm']];
    }
    flow_json(['people' => (object) $out]);
} catch (PDOException $e) {
    error_log('opd_flow_people: ' . $e->getMessage());
    flow_json(['error' => 'โหลดรายชื่อไม่สำเร็จ'], 500);
}
