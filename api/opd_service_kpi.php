<?php
/*
 * คุณภาพบริการ OPD (เวลารอ / SLA / Patient Flow / Alert) — GET api/opd_service_kpi.php?d1=YYYY-MM-DD&d2=YYYY-MM-DD
 *
 * พอร์ตนิยามจาก queries.py ของ opd-dashboard-wnk (service_times, patient_flow, hourly, appointments, alerts)
 * นิยาม:
 *  - นับเป็น visit (ovst.vn) · ไม่รวม visit ที่ admit (ovst.an) และ Telemed/PCU (pt_subtype ≠ 0) — ชุดเดียวกับ 3D Monitor
 *  - เวลาแต่ละขั้นจาก service_time ผ่าน FLOW_ST_MAP (opd_flow_lib.php) — ช่วงที่ติดลบหรือเกิน 360 นาที = กดผิด/ลืมกด ไม่นับ
 *  - เวลารวม = ลงทะเบียน → ขั้นสุดท้ายที่บันทึก นับเฉพาะ 1–600 นาที
 *  - ผู้ป่วยนัด = มี oapp.visit_vn = vn (เหมือน opd_visit_today.php)
 *  - ใช้ค่าเฉลี่ย (avg) เหมือนต้นแบบ เพื่อเทียบตัวเลขกับ /api/data.json ของ opd-dashboard ได้
 * ไม่มีข้อมูลรายบุคคลในผลลัพธ์ จึงไม่ต้อง login
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/opd_flow_lib.php';

const SK_MAX_GAP = 360;
const SK_MAX_TOTAL = 600;
// [ชื่อขั้น, คอลัมน์เริ่ม, คอลัมน์จบ, เป้าหมายนาที] — คอลัมน์เป็นชื่อมาตรฐานหลัง FLOW_ST_MAP
// shortcut: เป้าหมายเป็นค่าตั้งต้นจากต้นแบบวังน้ำเขียว ให้ทีมคุณภาพ รพ. กำหนดค่าจริงแล้วแก้ที่นี่
const SK_STEPS = [
    ['รอคัดกรอง', 'reg', 'service4', 15],
    ['คัดกรอง → พบแพทย์', 'service4', 'service5', 30],
    ['Door-to-Doctor', 'reg', 'service5', 45],
    ['รอรับยา', 'service6', 'service16', 20],
];
const SK_TARGET_TOTAL = 120;    // เวลารวมเฉลี่ยต่อราย (นาที)
const SK_TARGET_DONE = 80;      // % จบบริการภายใน 2 ชม.
const SK_TARGET_NOSHOW = 10;    // % ผิดนัด
const SK_HOUR_FROM = 6;
const SK_HOUR_TO = 20;

function sk_date(string $key, string $default): string
{
    $d = $_GET[$key] ?? $default;
    return (preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && strtotime($d)) ? $d : $default;
}

/** สรุปเป็นกลุ่ม (แผนก × ชั่วโมง × นัด/walk-in) ในคำสั่งเดียว แล้วรวมต่อในฝั่ง PHP */
function sk_rows(PDO $conn, string $st, string $d1, string $d2): array
{
    $gap = fn($a, $b) => "EXTRACT(EPOCH FROM (v.$b - v.$a))/60.0";
    $cols = [];
    foreach (SK_STEPS as $i => [, $a, $b, $t]) {
        $g = $gap($a, $b);
        $ok = "$g BETWEEN 0 AND " . SK_MAX_GAP;
        $cols[] = "SUM($g) FILTER (WHERE $ok) s$i, COUNT(*) FILTER (WHERE $ok) n$i, COUNT(*) FILTER (WHERE $g BETWEEN 0 AND $t) k$i";
    }
    $tt = "EXTRACT(EPOCH FROM (GREATEST(COALESCE(v.service5, TIME '00:00'), COALESCE(v.service6, TIME '00:00'),"
        . " COALESCE(v.service7, TIME '00:00'), COALESCE(v.service16, TIME '00:00'), COALESCE(v.service20, TIME '00:00')) - v.reg))/60.0";
    $sql = "
        SELECT v.md, v.h, v.ap, COUNT(*) c, " . implode(', ', $cols) . ",
          COUNT(*) FILTER (WHERE v.service4 IS NOT NULL) scr,
          COUNT(*) FILTER (WHERE v.service5 IS NOT NULL) seen,
          COUNT(*) FILTER (WHERE v.service6 IS NOT NULL OR v.service16 IS NOT NULL) rx,
          COUNT(*) FILTER (WHERE v.service7 IS NOT NULL) paid,
          SUM($tt) FILTER (WHERE $tt BETWEEN 1 AND " . SK_MAX_TOTAL . ") tts,
          COUNT(*) FILTER (WHERE $tt BETWEEN 1 AND " . SK_MAX_TOTAL . ") ttn,
          COUNT(*) FILTER (WHERE $tt BETWEEN 1 AND 120) done
        FROM (
          SELECT o.main_dep md, COALESCE(s.vsttime, o.vsttime) reg,
            EXTRACT(HOUR FROM COALESCE(s.vsttime, o.vsttime))::int h,
            s.service4, s.service5, s.service6, s.service7, s.service16, s.service20,
            EXISTS (SELECT 1 FROM oapp a WHERE a.visit_vn = o.vn) ap
          FROM ovst o LEFT JOIN $st s ON s.vn = o.vn
          WHERE o.vstdate BETWEEN :d1 AND :d2 AND (o.an IS NULL OR o.an = '')
            AND COALESCE(o.pt_subtype::text, '0') = '0'
        ) v
        GROUP BY v.md, v.h, v.ap";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':d1' => $d1, ':d2' => $d2]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/** รวมแถวที่ผ่าน filter → ค่าเฉลี่ย/นับ */
function sk_sum(array $rows, ?callable $keep = null): array
{
    $t = ['c' => 0, 'scr' => 0, 'seen' => 0, 'rx' => 0, 'paid' => 0, 'tts' => 0.0, 'ttn' => 0, 'done' => 0];
    foreach (SK_STEPS as $i => $_) {
        $t["s$i"] = 0.0;
        $t["n$i"] = 0;
        $t["k$i"] = 0;
    }
    foreach ($rows as $r) {
        if ($keep && !$keep($r)) {
            continue;
        }
        foreach ($t as $k => $_) {
            $t[$k] += (float) ($r[$k] ?? 0);
        }
    }
    $avg = fn($s, $n) => $n ? (int) round($s / $n) : null;
    $pct = fn($a, $b) => $b ? round($a * 100 / $b, 1) : null;
    $out = ['count' => (int) $t['c'], 'scr' => (int) $t['scr'], 'seen' => (int) $t['seen'], 'rx' => (int) $t['rx'],
        'paid' => (int) $t['paid'], 'fin' => (int) $t['ttn'], 'total' => $avg($t['tts'], $t['ttn']),
        'done' => $pct($t['done'], $t['ttn']), 'steps' => []];
    foreach (SK_STEPS as $i => [$label, , , $target]) {
        $out['steps'][] = ['label' => $label, 'target' => $target, 'avg' => $avg($t["s$i"], $t["n$i"]),
            'n' => (int) $t["n$i"], 'ok_pct' => $pct($t["k$i"], $t["n$i"])];
    }
    return $out;
}

$today = date('Y-m-d');
$d2 = min(sk_date('d2', $today), $today);
$d1 = min(sk_date('d1', $d2), $d2);
if ((strtotime($d2) - strtotime($d1)) / 86400 > 92) {
    $d1 = date('Y-m-d', strtotime("$d2 -92 days"));
}
$days = (int) round((strtotime($d2) - strtotime($d1)) / 86400) + 1;
$p2 = date('Y-m-d', strtotime("$d1 -1 day"));
$p1 = date('Y-m-d', strtotime("$p2 -" . ($days - 1) . " days"));

$cacheFile = __DIR__ . "/../storage/cache/opd_service_kpi_{$d1}_{$d2}.json";
$ttl = $d2 === $today ? 300 : 3600;
if (is_file($cacheFile) && time() - filemtime($cacheFile) < $ttl) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    readfile($cacheFile);
    exit;
}

try {
    $st = flow_st_table();
    $rows = sk_rows($conn, $st, $d1, $d2);
    $cur = sk_sum($rows);
    $prev = sk_sum(sk_rows($conn, $st, $p1, $p2));
    $delta = fn($a, $b) => ($a === null || !$b) ? null : round(($a - $b) * 100 / $b, 1);
    $apptN = sk_sum($rows, fn($r) => !empty($r['ap']))['count'];
    $d2d = $cur['steps'][2]['avg'];

    // ชั่วโมงลงทะเบียน: walk-in / นัด
    $hours = [];
    for ($h = SK_HOUR_FROM; $h <= SK_HOUR_TO; $h++) {
        $hours[$h] = ['h' => $h, 'walkin' => 0, 'appt' => 0];
    }
    $off = 0;
    foreach ($rows as $r) {
        $h = (int) $r['h'];
        if (!isset($hours[$h])) {
            $off += (int) $r['c'];
            continue;
        }
        $hours[$h][empty($r['ap']) ? 'walkin' : 'appt'] += (int) $r['c'];
    }

    // รายแผนก (main_dep = แผนกส่งคิวแรก)
    $deps = flow_deps($conn);
    $depRows = [];
    foreach (array_unique(array_map(fn($r) => (string) $r['md'], $rows)) as $code) {
        $s = sk_sum($rows, fn($r) => (string) $r['md'] === $code);
        $depRows[] = ['code' => $code, 'name' => $deps[$code]['name'] ?? ($code ?: 'ไม่ระบุแผนก'), 'count' => $s['count'],
            'seen' => $s['seen'], 'd2d' => $s['steps'][2]['avg'], 'd2d_ok' => $s['steps'][2]['ok_pct'],
            'rx' => $s['steps'][3]['avg'], 'total' => $s['total']];
    }
    usort($depRows, fn($a, $b) => $b['count'] <=> $a['count']);

    // นัดหมาย: วันที่ผ่านไปแล้วเท่านั้น (นัดวันนี้ยังมาได้อีก แยกแสดง)
    $stmt = $conn->prepare("
        SELECT COUNT(*) FILTER (WHERE a.nextdate < :t1) booked,
          COUNT(*) FILTER (WHERE a.nextdate < :t2 AND EXISTS (SELECT 1 FROM ovst o WHERE o.hn = a.hn AND o.vstdate = a.nextdate)) came,
          COUNT(*) FILTER (WHERE a.nextdate >= :t3) tb,
          COUNT(*) FILTER (WHERE a.nextdate >= :t4 AND EXISTS (SELECT 1 FROM ovst o WHERE o.hn = a.hn AND o.vstdate = a.nextdate)) tc
        FROM oapp a WHERE a.nextdate BETWEEN :d1 AND :d2");
    // PDO pgsql ไม่ให้ใช้ชื่อ parameter ซ้ำ
    $stmt->execute([':t1' => $today, ':t2' => $today, ':t3' => $today, ':t4' => $today, ':d1' => $d1, ':d2' => $d2]);
    $a = array_map('intval', $stmt->fetch(PDO::FETCH_ASSOC));
    $noshowRate = $a['booked'] ? round(($a['booked'] - $a['came']) * 100 / $a['booked'], 1) : null;

    // Alert & Action — เกินเป้ามากสุดขึ้นก่อน (ratio ≥ 1.5 = เร่งด่วน)
    $alerts = [];
    $add = function ($issue, $value, $target, $bad, $ratio, $action) use (&$alerts) {
        $alerts[] = ['issue' => $issue, 'value' => $value, 'target' => $target, 'action' => $action,
            'level' => $bad ? ($ratio >= 1.5 ? 'high' : 'mid') : 'ok', 'ratio' => $bad ? $ratio : 0];
    };
    $actions = ['เพิ่มจุด/เจ้าหน้าที่คัดกรองช่วงพีค', 'เกลี่ยแพทย์ออกตรวจให้ตรงช่วงผู้ป่วยมาก', 'เร่งเริ่มตรวจตรงเวลา ลดคิวค้างช่วงเช้า',
        'เพิ่มเภสัชกร/ช่องจ่ายยาช่วงพีค'];
    foreach ($cur['steps'] as $i => $s) {
        if ($s['avg'] !== null) {
            $add($s['label'], "{$s['avg']} นาที", "≤ {$s['target']} นาที", $s['avg'] > $s['target'], $s['avg'] / $s['target'], $actions[$i]);
        }
    }
    if ($cur['total'] !== null) {
        $add('เวลารวมต่อราย', "{$cur['total']} นาที", '≤ ' . SK_TARGET_TOTAL . ' นาที', $cur['total'] > SK_TARGET_TOTAL,
            $cur['total'] / SK_TARGET_TOTAL, 'ดูขั้นที่เกินเป้ามากสุดในตาราง SLA');
    }
    if ($cur['done'] !== null) {
        $add('จบบริการภายใน 2 ชม.', "{$cur['done']}%", '≥ ' . SK_TARGET_DONE . '%', $cur['done'] < SK_TARGET_DONE,
            $cur['done'] ? SK_TARGET_DONE / $cur['done'] : 2, 'ลดเวลารอขั้นที่เกินเป้า');
    }
    if ($noshowRate !== null) {
        $add('อัตราผิดนัด', "$noshowRate%", '≤ ' . SK_TARGET_NOSHOW . '%', $noshowRate > SK_TARGET_NOSHOW,
            $noshowRate / SK_TARGET_NOSHOW, 'โทร/SMS เตือนก่อนวันนัด');
    }
    $scrPct = $cur['count'] ? round($cur['scr'] * 100 / $cur['count'], 1) : null;
    if ($scrPct !== null) {
        $add('บันทึกเวลาคัดกรอง', "$scrPct%", '≥ 90%', $scrPct < 90, $scrPct ? 90 / $scrPct : 2,
            'ย้ำจุดคัดกรองกดเวลาใน HOSxP ทุกราย (ตัวเลขเวลารอเชื่อได้มากขึ้น)');
    }
    usort($alerts, fn($x, $y) => $y['ratio'] <=> $x['ratio']);

    $f = fn($label, $v) => ['label' => $label, 'value' => $v, 'pct' => $cur['count'] ? round($v * 100 / $cur['count']) : 0];
    $data = [
        'period' => ['d1' => $d1, 'd2' => $d2, 'days' => $days, 'prev_d1' => $p1, 'prev_d2' => $p2,
            'label' => $d1 === $d2 ? flow_thai_date($d1) : flow_thai_date($d1) . ' – ' . flow_thai_date($d2)],
        'kpi' => [
            'visits' => $cur['count'], 'visits_delta' => $delta($cur['count'], $prev['count']),
            'appt' => $apptN, 'walkin' => $cur['count'] - $apptN,
            'total' => $cur['total'], 'total_delta' => $delta($cur['total'], $prev['total']),
            'd2d' => $d2d, 'd2d_delta' => $delta($d2d, $prev['steps'][2]['avg']),
            'done' => $cur['done'], 'done_prev' => $prev['done'],
            'noshow' => $noshowRate,
        ],
        'steps' => $cur['steps'],
        'funnel' => [$f('ลงทะเบียน', $cur['count']), $f('คัดกรอง', $cur['scr']), $f('พบแพทย์', $cur['seen']),
            $f('ห้องยา', $cur['rx']), $f('ชำระเงิน', $cur['paid']), $f('จบบริการ', $cur['fin'])],
        'hours' => array_values($hours),
        'off_hours' => $off,
        'deps' => array_slice($depRows, 0, 20),
        'appt' => ['booked' => $a['booked'], 'came' => $a['came'], 'noshow' => $a['booked'] - $a['came'],
            'today_booked' => $a['tb'], 'today_came' => $a['tc']],
        'alerts' => array_slice($alerts, 0, 8),
        'targets' => ['total' => SK_TARGET_TOTAL, 'done' => SK_TARGET_DONE, 'noshow' => SK_TARGET_NOSHOW],
        'generated' => date('H:i'),
    ];
    $json = json_encode($data, JSON_UNESCAPED_UNICODE);
    @file_put_contents($cacheFile, $json, LOCK_EX);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo $json;
} catch (PDOException $e) {
    error_log('opd_service_kpi: ' . $e->getMessage());
    flow_json(['error' => 'โหลดข้อมูลไม่สำเร็จ'], 500);
}
