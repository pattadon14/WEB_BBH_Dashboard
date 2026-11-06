<?php
/*
 * ตรวจการจัดแผนก → จุดบนผัง 3D Monitor — GET api/opd_flow_check.php?days=7
 *
 * ต้อง login · ส่งเฉพาะตัวเลขรวม (ไม่มีข้อมูลผู้ป่วยรายบุคคล):
 *  - แผนกปลายทางใน opd_dep_queue: จำนวนคิว, % กดรับ/เสร็จ, กลุ่ม และจุดบนผัง (ใช้ตั้ง FLOW_DEP_MAP ใน .env)
 *  - ประเภทเวลาใน ovst_service_time ที่ใช้คำนวณเวลารอ
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

$days = max(1, min(31, (int) ($_GET['days'] ?? 7)));
$from = date('Y-m-d', strtotime('-' . ($days - 1) . ' day'));

try {
    $deps = flow_deps($conn);
    $q = $conn->prepare("
        SELECT q.depcode, COUNT(*) n, ROUND(100.0 * COUNT(q.accept_datetime) / COUNT(*)) acc,
          ROUND(100.0 * COUNT(q.finish_datetime) / COUNT(*)) fin
        FROM opd_dep_queue q JOIN ovst o ON o.vn = q.vn
        WHERE o.vstdate >= :f GROUP BY q.depcode ORDER BY n DESC");
    $q->execute([':f' => $from]);
    $queue = array_map(function ($t) use ($deps) {
        $code = (string) $t['depcode'];
        [$wait, $in] = flow_place($deps, $code, false, null);
        return ['code' => $code, 'name' => $deps[$code]['name'] ?? null, 'group' => $deps[$code]['group'] ?? null,
            'area' => $wait, 'area_in' => $in, 'n' => (int) $t['n'], 'accept_pct' => (int) $t['acc'], 'finish_pct' => (int) $t['fin']];
    }, $q->fetchAll(PDO::FETCH_ASSOC));

    $t = $conn->prepare("
        SELECT ovst_service_time_type_code type, COUNT(*) n, COUNT(DISTINCT vn) visits
        FROM ovst_service_time WHERE service_date >= :f GROUP BY 1 ORDER BY n DESC");
    $t->execute([':f' => $from]);

    flow_json([
        'from' => $from,
        'queue_deps' => $queue,      // area = จุดรอเมื่อถูกส่งมา (ฝั่ง walk-in) · area_in = จุดเมื่อรับเข้าบริการ
        'service_types' => $t->fetchAll(PDO::FETCH_ASSOC),
    ]);
} catch (PDOException $e) {
    error_log('opd_flow_check: ' . $e->getMessage());
    flow_json(['error' => 'ตรวจไม่สำเร็จ'], 500);
}
