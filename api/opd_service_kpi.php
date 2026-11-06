<?php
/*
 * คุณภาพบริการ OPD (เวลารอ / SLA / Patient Flow / Alert) — GET api/opd_service_kpi.php?d1=YYYY-MM-DD&d2=YYYY-MM-DD
 *
 * พอร์ตนิยามจาก queries.py ของ opd-dashboard-wnk (service_times, patient_flow, hourly, appointments, alerts)
 * นิยาม:
 *  - นับเป็น visit (ovst.vn) · ไม่รวม visit ที่ admit (ovst.an), pt_subtype ≠ 0 และแผนก Telemed/PCU/ส่งยาไปรษณีย์
 *    (flow_remote_deps) — ชุดเดียวกับ 3D Monitor
 *  - เวลาแต่ละขั้นจาก ovst_service_time (มีประเภทขั้นชัดเจน) — ช่วงที่ติดลบหรือเกิน 360 นาที = กดผิด/ลืมกด ไม่นับ
 *    ซักประวัติ = OPD-SCREEN ครั้งแรก ไม่นับที่จุด Exit/หลังพบแพทย์ · พบแพทย์ = OPD-DOCTOR ครั้งแรก
 *    รอรับยา = OPD-RX-ENTRY ครั้งแรก → OPD-RX-DISPENSE เสร็จครั้งสุดท้าย
 *  - เวลารวม = ลงทะเบียน → ขั้นสุดท้ายที่บันทึกเสร็จ นับเฉพาะ 1–600 นาที
 *  - ส่งชำระเงิน = มี OPD-FINANCE หรือมีคิวส่งไปแผนกการเงิน (ห้องเก็บเงินแทบไม่บันทึก OPD-FINANCE)
 *  - ผู้ป่วยนัด = มี oapp.visit_vn = vn (เหมือน opd_visit_today.php)
 *  - ใช้ค่าเฉลี่ย (avg) เหมือนต้นแบบ เพื่อเทียบตัวเลขกับ /api/data.json ของ opd-dashboard ได้
 * ไม่มีข้อมูลรายบุคคลในผลลัพธ์ จึงไม่ต้อง login
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/opd_flow_lib.php';

const SK_MAX_GAP = 360;
const SK_MAX_TOTAL = 600;
// [ชื่อขั้น, เวลาเริ่ม, เวลาจบ, เป้าหมายนาที] — ชื่อเวลาตาม CTE t ใน sk_rows()
// shortcut: เป้าหมายเป็นค่าตั้งต้นจากต้นแบบวังน้ำเขียว ให้ทีมคุณภาพ รพ. กำหนดค่าจริงแล้วแก้ที่นี่
const SK_STEPS = [
    ['รอคัดกรอง', 'reg', 'scr', 15],
    ['คัดกรอง → พบแพทย์', 'scr', 'dr', 30],
    ['Door-to-Doctor', 'reg', 'dr', 45],
    ['รอรับยา', 'rxin', 'rxout', 20],
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
function sk_rows(PDO $conn, array $deps, string $d1, string $d2): array
{
    // รหัสแผนกเป็นตัวเลข/ตัวอักษรล้วน — ส่งเป็น array literal ของ PostgreSQL
    $codes = fn($rx) => '{' . implode(',', array_filter(array_map('strval', array_keys(array_filter($deps,
        fn($x) => preg_match($rx, $x['name'])))), fn($c) => preg_match('/^\w+$/', $c))) . '}';
    $gap = fn($a, $b) => "EXTRACT(EPOCH FROM (v.$b - v.$a))/60.0";
    $cols = [];
    foreach (SK_STEPS as $i => [, $a, $b, $t]) {
        $g = $gap($a, $b);
        $ok = "$g BETWEEN 0 AND " . SK_MAX_GAP;
        $cols[] = "SUM($g) FILTER (WHERE $ok) s$i, COUNT(*) FILTER (WHERE $ok) n$i, COUNT(*) FILTER (WHERE $g BETWEEN 0 AND $t) k$i";
    }
    $tt = "EXTRACT(EPOCH FROM (v.fin - v.reg))/60.0";
    $ty = fn($code) => "t.ovst_service_time_type_code = '$code'";
    $sql = "
        WITH o AS (
          SELECT o.vn, o.main_dep md, o.vstdate + o.vsttime reg, EXTRACT(HOUR FROM o.vsttime)::int h,
            EXISTS (SELECT 1 FROM oapp a WHERE a.visit_vn = o.vn) ap
          FROM ovst o
          WHERE o.vstdate BETWEEN :d1 AND :d2 AND (o.an IS NULL OR o.an = '')
            AND COALESCE(o.pt_subtype::text, '0') = '0'
        ), t AS (
          SELECT t.vn,
            MIN(t.service_begin_datetime) FILTER (WHERE {$ty('OPD-SCREEN')} AND NOT t.depcode = ANY(CAST(:exits AS text[]))) scr,
            MIN(t.service_begin_datetime) FILTER (WHERE {$ty('OPD-DOCTOR')}) dr,
            MIN(t.service_begin_datetime) FILTER (WHERE {$ty('OPD-RX-ENTRY')}) rxin,
            MAX(t.service_end_datetime) FILTER (WHERE {$ty('OPD-RX-DISPENSE')}) rxout,
            MAX(t.service_end_datetime) FILTER (WHERE t.ovst_service_time_type_code IN
              ('OPD-SCREEN', 'OPD-DOCTOR', 'OPD-RX-ENTRY', 'OPD-RX-DISPENSE', 'OPD-FINANCE', 'OPD-DENTAL', 'OPD-ER')) fin,
            BOOL_OR({$ty('OPD-FINANCE')}) fi
          FROM ovst_service_time t
          WHERE t.service_date BETWEEN :s1 AND :s2 AND t.vn IN (SELECT vn FROM o)
          GROUP BY t.vn
        ), c AS (
          SELECT DISTINCT q.vn FROM opd_dep_queue q
          WHERE q.vn IN (SELECT vn FROM o) AND q.depcode = ANY(CAST(:cash AS text[]))
        ), v AS (
          SELECT o.*, t.scr, t.dr, t.rxin, t.rxout, t.fin, (COALESCE(t.fi, false) OR c.vn IS NOT NULL) paid
          FROM o LEFT JOIN t ON t.vn = o.vn LEFT JOIN c ON c.vn = o.vn
        )
        SELECT v.md, v.h, v.ap, COUNT(*) c, " . implode(', ', $cols) . ",
          COUNT(*) FILTER (WHERE v.scr IS NOT NULL) scr,
          COUNT(*) FILTER (WHERE v.dr IS NOT NULL) seen,
          COUNT(*) FILTER (WHERE v.rxin IS NOT NULL OR v.rxout IS NOT NULL) rx,
          COUNT(*) FILTER (WHERE v.paid) paid,
          SUM($tt) FILTER (WHERE $tt BETWEEN 1 AND " . SK_MAX_TOTAL . ") tts,
          COUNT(*) FILTER (WHERE $tt BETWEEN 1 AND " . SK_MAX_TOTAL . ") ttn,
          COUNT(*) FILTER (WHERE $tt BETWEEN 1 AND 120) done
        FROM v
        GROUP BY v.md, v.h, v.ap";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':d1' => $d1, ':d2' => $d2, ':s1' => $d1, ':s2' => date('Y-m-d', strtotime("$d2 +1 day")),
        ':exits' => $codes(FLOW_EXIT_RX), ':cash' => $codes(FLOW_CASH_RX)]);
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

$cacheFile = __DIR__ . "/../storage/cache/opd_service_kpi_v2_{$d1}_{$d2}.json";
$ttl = $d2 === $today ? 300 : 3600;
if (is_file($cacheFile) && time() - filemtime($cacheFile) < $ttl) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    readfile($cacheFile);
    exit;
}

try {
    $deps = flow_deps($conn);
    // ผู้ป่วยไม่ได้มา รพ. (Telemed, PCU, ส่งยาไปรษณีย์) — ตัดด้วยกฎเดียวกับ 3D Monitor · บ้านบึงไม่ได้ใช้ pt_subtype
    $remote = flow_remote_deps($deps);
    $inHosp = fn($rs) => array_values(array_filter($rs, fn($r) => !in_array((string) $r['md'], $remote, true)));
    $all = sk_rows($conn, $deps, $d1, $d2);
    $rows = $inHosp($all);
    $remoteN = sk_sum($all)['count'] - sk_sum($rows)['count'];
    $cur = sk_sum($rows);
    $prev = sk_sum($inHosp(sk_rows($conn, $deps, $p1, $p2)));
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
            // วันนี้ยังไม่จบวัน — จำนวน visit เทียบช่วงก่อนที่ครบวันไม่ได้
            'visits' => $cur['count'], 'visits_delta' => $d2 === $today ? null : $delta($cur['count'], $prev['count']),
            'appt' => $apptN, 'walkin' => $cur['count'] - $apptN, 'remote' => $remoteN,
            'total' => $cur['total'], 'total_delta' => $delta($cur['total'], $prev['total']),
            'd2d' => $d2d, 'd2d_delta' => $delta($d2d, $prev['steps'][2]['avg']),
            'done' => $cur['done'], 'done_prev' => $prev['done'],
            'noshow' => $noshowRate,
        ],
        'steps' => $cur['steps'],
        'funnel' => [$f('ลงทะเบียน', $cur['count']), $f('คัดกรอง', $cur['scr']), $f('พบแพทย์', $cur['seen']),
            $f('ห้องยา', $cur['rx']), $f('ส่งชำระเงิน', $cur['paid']), $f('จบบริการ', $cur['fin'])],
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
