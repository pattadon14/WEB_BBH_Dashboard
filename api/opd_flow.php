<?php
/*
 * ข้อมูล 3D Monitor OPD — GET api/opd_flow.php?d=YYYY-MM-DD
 *
 * แปลงคิวส่งแผนก (opd_dep_queue) + lab_head + xray_report ของแต่ละ visit เป็นเส้นทาง
 * "ช่วงเวลาไหน อยู่จุดบริการไหน" ให้หน้าเว็บขยับหุ่นตามเวลา (เอนจินพอร์ตจาก flow.py ของ opd-dashboard-wnk, MIT)
 *
 * นิยาม (ดูปุ่ม "วิธีคำนวณ" บนหน้าเว็บด้วย):
 *  - ฝั่งผู้ป่วยนัด = มี oapp.visit_vn = vn (มาตามนัด) · ไม่งั้นฝั่ง walk-in
 *  - นับเป็น visit (ovst.vn) ของวันที่เลือก · ไม่รวม visit ที่ admit (ovst.an), pt_subtype ≠ 0 และแผนก Telemed/PCU/ไปรษณีย์
 *  - ส่งไปแผนก = ไปรอที่จุดนั้น · รับเข้าห้องตรวจ = อยู่ในห้อง · ส่ง 999 = กลับบ้าน · เวลาเกินลงทะเบียน + 10 ชม. ไม่นับ
 *
 * ส่งออกเฉพาะเวลา (นาทีนับจากเที่ยงคืน) จุดบริการ เพศ ระดับเร่งด่วน รหัสแผนก ลำดับแพทย์ — ไม่มี vn / HN / ชื่อ
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/opd_flow_lib.php';

$d = flow_date();
$today = date('Y-m-d');
$isToday = $d === $today;

$cacheFile = __DIR__ . '/../storage/cache/opd_flow_v4_' . $d . '.json';
$ttl = $isToday ? 45 : 3600;
if (is_file($cacheFile) && time() - filemtime($cacheFile) < $ttl) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    readfile($cacheFile);
    exit;
}

$AREA_IDX = [];
foreach (FLOW_AREAS as $i => $a) {
    $AREA_IDX[$a[0]] = $i;
}

try {
    $deps = flow_deps($conn);
    $m = fn($col) => "EXTRACT(EPOCH FROM $col)/60.0";
    $mt = fn($col) => "EXTRACT(EPOCH FROM ($col - o.vstdate))/60.0";     // timestamp → นาทีนับจากเที่ยงคืนวันที่มา
    // X-Ray กับ Ultrasound อยู่ใน xray_report เดียวกัน — แยกด้วยชื่อรายการ (xray_items) ถ้าตารางมีคอลัมน์ให้
    $xr = 'NULL';
    $us = 'NULL';
    if (flow_has_column($conn, 'xray_report', 'request_time')) {
        $base = "FROM xray_report x WHERE x.vn = o.vn AND x.request_date = o.vstdate";
        if (flow_has_column($conn, 'xray_report', 'xray_items_code') && flow_has_column($conn, 'xray_items', 'xray_items_name')) {
            $isUs = "EXISTS (SELECT 1 FROM xray_items i WHERE i.xray_items_code = x.xray_items_code
                AND i.xray_items_name ~* '(ultra|u/s|อัลตร)')";
            $xr = "(SELECT {$m('MIN(x.request_time)')} $base AND NOT $isUs)";
            $us = "(SELECT {$m('MIN(x.request_time)')} $base AND $isUs)";
        } else {
            $xr = "(SELECT {$m('MIN(x.request_time)')} $base)";
        }
    }

    $sql = "
        SELECT o.vn, {$m('o.vsttime')} reg, o.main_dep md, o.pt_priority pr, p.sex sex, o.doctor dr,
          o.an, o.pt_subtype pst,
          (SELECT MAX(e.er_emergency_level_id) FROM er_regist e WHERE e.vn = o.vn) erl,
          (SELECT {$m('MIN(l.order_time)')} FROM lab_head l WHERE l.vn = o.vn AND l.order_date = o.vstdate) lo,
          (SELECT {$m('MIN(l.receive_time)')} FROM lab_head l WHERE l.vn = o.vn AND l.receive_date = o.vstdate) lr,
          $xr xr, $us us,
          EXISTS (SELECT 1 FROM oapp a WHERE a.visit_vn = o.vn) ap,
          EXISTS (SELECT 1 FROM ovst_service_time t WHERE t.vn = o.vn AND t.ovst_service_time_type_code = 'OPD-SCREEN') has_scr,
          EXISTS (SELECT 1 FROM ovst_service_time t WHERE t.vn = o.vn AND t.ovst_service_time_type_code = 'OPD-DOCTOR') has_dr
        FROM ovst o LEFT JOIN patient p ON p.hn = o.hn
        WHERE o.vstdate = :d";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':d' => $d]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // คิวส่งแผนกของทั้งวัน → [vn => [[ส่ง, รับ, เสร็จ, depcode], ...]]
    $qs = $conn->prepare("
        SELECT q.vn, {$mt('q.queue_datetime')} tq, {$mt('q.accept_datetime')} ta, {$mt('q.finish_datetime')} tf, q.depcode
        FROM opd_dep_queue q JOIN ovst o ON o.vn = q.vn
        WHERE o.vstdate = :d ORDER BY q.vn, q.queue_datetime, q.opd_dep_queue_id");
    $qs->execute([':d' => $d]);
    $queue = [];
    $num = fn($x) => $x === null ? null : round((float) $x, 1);
    while ($q = $qs->fetch(PDO::FETCH_ASSOC)) {
        $queue[$q['vn']][] = [$num($q['tq']), $num($q['ta']), $num($q['tf']), (string) $q['depcode']];
    }

    // Telemed (ผู้ป่วยอยู่นอก รพ.) · FLOW_REMOTE_DEPS=none = วาดทุกคน
    $remoteDeps = flow_remote_deps($deps);

    $now = $isToday ? (int) date('G') * 60 + (int) date('i') : null;
    $visits = [];
    $remote = [];
    $admitted = 0;
    $doctors = [];
    $depUsed = [];
    foreach ($rows as $r) {
        if (!empty($r['an'])) {
            $admitted++;        // admit จาก OPD/ER — ไม่นับในผัง OPD
            continue;
        }
        if ($r['reg'] === null) {
            continue;
        }
        if (in_array((string) $r['md'], $remoteDeps, true) || (string) ($r['pst'] ?? '0') !== '0') {
            $remote[] = round((float) $r['reg'], 1);    // Telemed / PCU ไม่ได้อยู่ใน รพ.
            continue;
        }
        foreach (['reg', 'lo', 'lr', 'xr', 'us'] as $k) {
            $r[$k] = $num($r[$k]);
        }
        if ($r['lo'] !== null && $r['lr'] !== null && $r['lr'] < $r['lo']) {
            $r['lr'] = null;
        }
        [$segs, $end] = flow_segments($r, $queue[$r['vn']] ?? [], $deps, $now);
        $dr = (string) $r['dr'];
        $drIdx = null;
        if ($dr !== '' && $r['has_dr']) {
            $drIdx = $doctors[$dr] ??= count($doctors);   // ลำดับแพทย์ (ไม่ส่งรหัสแพทย์ออกไป)
        }
        $md = (string) $r['md'];
        $depUsed[$md] = true;
        $sex = (string) $r['sex'];
        $visits[] = [
            'i' => flow_vid((string) $r['vn']),
            's' => array_map(fn($s) => [$s[0], $AREA_IDX[$s[1]]], $segs),
            'e' => round($end, 1),
            'u' => flow_urgency($r['erl'], $r['pr']),
            'g' => in_array($sex, ['1', '2'], true) ? (int) $sex : 0,
            'd' => $md,
            'dr' => $drIdx,
            // บันทึกเวลาครบคู่ = มีทั้งเวลาซักประวัติและแพทย์ตรวจใน ovst_service_time
            'q' => ($r['has_scr'] && $r['has_dr']) ? 1 : 0,
            'a' => $r['ap'] ? 1 : 0,     // ผู้ป่วยนัด
        ];
    }
    usort($visits, fn($a, $b) => strcmp($a['i'], $b['i']));     // เรียงตามรหัสสุ่ม โยงกลับลำดับ vn ไม่ได้
    sort($remote);

    $depList = [];
    foreach (array_keys($depUsed) as $code) {
        $depList[] = ['code' => (string) $code, 'name' => $deps[$code]['name'] ?? (string) $code];
    }
    usort($depList, fn($a, $b) => strcmp($a['name'], $b['name']));

    $data = [
        'date' => $d,
        'date_label' => flow_thai_date($d),
        'is_today' => $isToday,
        'areas' => array_map(fn($a) => ['id' => $a[0], 'name' => $a[1], 'target' => $a[2], 'nowait' => $a[0] === 'ER'], FLOW_AREAS),
        'deps' => $depList,
        'doctors' => count($doctors),
        'admitted' => $admitted,
        'visits' => $visits,
        'remote' => $remote,
        'generated' => date('H:i:s'),
    ];
    $json = json_encode($data, JSON_UNESCAPED_UNICODE);
    @file_put_contents($cacheFile, $json, LOCK_EX);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo $json;
} catch (PDOException $e) {
    error_log('opd_flow: ' . $e->getMessage());
    flow_json(['error' => 'โหลดข้อมูลไม่สำเร็จ'], 500);
}
