<?php
/*
 * รายละเอียดบริการในการ์ด "สถิติการใช้บริการอื่นๆ วันนี้" (popup หน้าแรก)
 * GET api/service_detail.php?svc=<key ใน config/services.php>[&date=YYYY-MM-DD]
 *
 * สรุป (ยอด, เทียบสัปดาห์ก่อน, รายชั่วโมง, แยกรายการ) แสดงได้ทุกคน
 * รายชื่อผู้ป่วย (PDPA) ส่งเฉพาะเมื่อ login Provider ID · ไม่ cache ลงดิสก์
 */

require_once __DIR__ . '/../auth/provider.php';
require_once __DIR__ . '/../config/services.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$fail = function (int $code, string $msg) {
    http_response_code($code);
    echo json_encode(['error' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
};

$defs = service_defs();
$key  = (string) ($_GET['svc'] ?? '');
if (!isset($defs[$key])) {
    $fail(400, 'ไม่รู้จักบริการนี้');
}
$def = $defs[$key];

$today = date('Y-m-d');
$date  = (string) ($_GET['date'] ?? $today);
$dt    = DateTime::createFromFormat('Y-m-d', $date);
if (!$dt || $dt->format('Y-m-d') !== $date || $date > $today) {
    $fail(400, 'วันที่ไม่ถูกต้อง');
}

require_once __DIR__ . '/../config/database.php';

try {
    // 1 แถวต่อ 1 หน่วยที่นับบนการ์ด (ovst.vn / ipt.an / patient.hn เป็น key ไม่ซ้ำ join แล้วจำนวนแถวไม่เปลี่ยน)
    $st = $conn->prepare("
        SELECT COALESCE(x.hn, ov.hn, i.hn) AS hn,
               TRIM(COALESCE(p.pname, '') || COALESCE(p.fname, '') || ' ' || COALESCE(p.lname, '')) AS name,
               TO_CHAR(COALESCE(x.t, ov.vsttime, i.regtime), 'HH24:MI') AS time,
               COALESCE(k.department, w.name, '') AS dep,
               COALESCE(pt.name, ov.pttype, i.pttype, '') AS pttype,
               COALESCE(x.item, '') AS item
        FROM ({$def['sql']}) x
        LEFT JOIN ovst ov          ON ov.vn = x.vn
        LEFT JOIN ipt i            ON i.an = x.an
        LEFT JOIN patient p        ON p.hn = COALESCE(x.hn, ov.hn, i.hn)
        LEFT JOIN kskdepartment k  ON k.depcode = ov.main_dep
        LEFT JOIN ward w           ON w.ward = i.ward
        LEFT JOIN pttype pt        ON pt.pttype = COALESCE(ov.pttype, i.pttype)
        ORDER BY 3 NULLS LAST, 1
    ");
    $st->execute([':d' => $date]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    // สัปดาห์ก่อน (วันเดียวกัน) — ถ้าเป็นวันนี้นับถึงเวลาเดียวกัน
    $prevDate = (clone $dt)->modify('-7 days')->format('Y-m-d');
    $prev = service_count($conn, $def['sql'], $prevDate, $date === $today ? date('H:i:s') : null);

    $hours = [];
    $groups = [];
    foreach ($rows as &$r) {
        $items = array_values(array_filter(array_map('trim', explode(' | ', $r['item'])), 'strlen'));
        $r['item'] = implode(' · ', $items);
        if ($r['time'] !== null) {
            $h = (int) substr($r['time'], 0, 2);
            $hours[$h] = ($hours[$h] ?? 0) + 1;
        }
        $names = $def['by'] === 'item' ? $items : [$r[$def['by']]];
        foreach ($names ?: ['ไม่ระบุ'] as $n) {
            $n = $n === '' ? 'ไม่ระบุ' : $n;
            $groups[$n] = ($groups[$n] ?? 0) + 1;
        }
    }
    unset($r);
    ksort($hours);
    arsort($groups);

    $logged = provider_is_logged_in();
    echo json_encode([
        'svc'      => $key,
        'label'    => $def['label'],
        'by'       => $def['by'],
        'date'     => $date,
        'total'    => count($rows),
        'prev'     => $prev,
        'hours'    => array_map(fn($h, $n) => ['h' => $h, 'n' => $n], array_keys($hours), $hours),
        'groups'   => array_map(fn($k, $n) => ['name' => (string) $k, 'n' => $n], array_keys($groups), $groups),
        'rows'     => $logged ? $rows : null,
        'login_required' => !$logged,
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log('service_detail: ' . $e->getMessage());
    $fail(500, 'โหลดข้อมูลไม่สำเร็จ');
}
