<?php
/*
 * ตรวจความหมายคอลัมน์เวลาของบ้านบึงก่อนเชื่อตัวเลข 3D Monitor — GET api/opd_flow_check.php?days=7
 *
 * ต้อง login · ส่งเฉพาะตัวเลขรวม (ไม่มีข้อมูลผู้ป่วยรายบุคคล):
 *  - service1..20: จำนวน visit ที่มีเวลา, เวลากลาง (นาทีหลังลงทะเบียน), แผนกที่กดบ่อยสุด
 *  - แผนกที่มีผู้ป่วย + กลุ่มที่จัดให้ (ใช้ตั้ง FLOW_DEP_MAP / FLOW_ST_MAP ใน .env)
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
    $total = $conn->prepare('SELECT COUNT(*) FROM ovst WHERE vstdate >= :f AND an IS NULL');
    $total->execute([':f' => $from]);
    $visits = (int) $total->fetchColumn();

    $cols = [];
    for ($i = 1; $i <= 20; $i++) {
        $c = "service$i";
        $stmt = $conn->prepare("
            SELECT COUNT(s.$c) n,
              PERCENTILE_CONT(0.5) WITHIN GROUP (ORDER BY EXTRACT(EPOCH FROM (s.$c - COALESCE(s.vsttime, o.vsttime)))/60.0) med
            FROM service_time s JOIN ovst o ON o.vn = s.vn
            WHERE o.vstdate >= :f AND o.an IS NULL AND s.$c >= COALESCE(s.vsttime, o.vsttime)");
        $stmt->execute([':f' => $from]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $top = $conn->prepare("
            SELECT s.{$c}_dep dep, COUNT(*) n FROM service_time s JOIN ovst o ON o.vn = s.vn
            WHERE o.vstdate >= :f AND s.$c IS NOT NULL GROUP BY s.{$c}_dep ORDER BY n DESC LIMIT 3");
        $top->execute([':f' => $from]);
        $cols[] = [
            'col' => $c,
            'filled_pct' => $visits ? round($row['n'] * 100 / $visits, 1) : 0,
            'median_min_after_reg' => $row['med'] === null ? null : round((float) $row['med']),
            'top_deps' => array_map(fn($t) => [
                'code' => $t['dep'], 'name' => $deps[$t['dep']]['name'] ?? null, 'group' => $deps[$t['dep']]['group'] ?? null, 'n' => (int) $t['n'],
            ], $top->fetchAll(PDO::FETCH_ASSOC)),
        ];
    }

    $md = $conn->prepare('SELECT main_dep, COUNT(*) n FROM ovst WHERE vstdate >= :f AND an IS NULL GROUP BY main_dep ORDER BY n DESC');
    $md->execute([':f' => $from]);
    $mainDeps = array_map(fn($t) => [
        'code' => $t['main_dep'], 'name' => $deps[$t['main_dep']]['name'] ?? null, 'group' => $deps[$t['main_dep']]['group'] ?? null, 'n' => (int) $t['n'],
    ], $md->fetchAll(PDO::FETCH_ASSOC));

    flow_json([
        'from' => $from,
        'visits' => $visits,
        'st_map' => flow_env('FLOW_ST_MAP'),
        'xray_request_time' => flow_has_column($conn, 'xray_report', 'request_time'),
        'service_time' => $cols,
        'main_deps' => $mainDeps,
    ]);
} catch (PDOException $e) {
    error_log('opd_flow_check: ' . $e->getMessage());
    flow_json(['error' => 'ตรวจไม่สำเร็จ: ' . $e->getMessage()], 500);
}
