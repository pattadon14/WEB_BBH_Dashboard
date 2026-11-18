<?php

/*
 * เวลาเข้า ER ถึงพบแพทย์ (Door-to-Doctor) แยกตามระดับความเร่งด่วน — ?date=YYYY-MM-DD (ค่าเริ่มต้นวันนี้)
 * นิยามเวลา: ER_D2D_SQL + er_hpi_minutes() ใน config/er.php (ตรงกับต้นแบบ er-dashboard บล็อก Triage)
 * นับเป็นครั้ง เฉพาะ visit ที่ ER คัดกรองแล้ว (มีระดับ และมีประเภทผู้ป่วย) เหมือนต้นแบบ
 * avg = ค่าเฉลี่ยนาทีของรายที่มีเวลาพบแพทย์ · within = จำนวนที่พบแพทย์ทันเป้า (≤ ER_D2D_TARGET)
 * ยอดรวมเท่านั้น ไม่มีข้อมูลรายคน
 */

require_once '../config/cache.php';
date_default_timezone_set('Asia/Bangkok');
api_cache_by_date('er_d2d', 300);
require_once '../config/database.php';
require_once '../config/er.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $today = date('Y-m-d');
    $date  = $_GET['date'] ?? $today;
    $d     = DateTime::createFromFormat('!Y-m-d', (string)$date);
    if (!$d || $d->format('Y-m-d') !== $date || $date > $today) {
        throw new InvalidArgumentException('วันที่ไม่ถูกต้อง');
    }

    $level = ER_LEVEL_SQL;
    $d2d   = ER_D2D_SQL;
    $hpi   = ER_D2D_HPI_SQL;
    $st = $conn->prepare("
        SELECT $level AS lvl, $d2d AS d2d, e.enter_er_time AS t, $hpi AS hpi
        FROM er_regist e
        LEFT JOIN er_emergency_type et ON et.er_emergency_type = e.er_emergency_type
        LEFT JOIN service_time s       ON s.vn = e.vn
        LEFT JOIN opdscreen sc         ON sc.vn = e.vn
        WHERE e.vstdate = :d AND e.enter_er_time IS NOT NULL
          AND (e.er_emergency_level_id IS NOT NULL OR e.er_emergency_type IS NOT NULL)
          AND e.er_pt_type IS NOT NULL
    ");
    $st->execute([':d' => $date]);

    $g = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $lv = (int)$r['lvl'];
        if ($lv < 1 || $lv > 5) {
            continue;   // Black / ไม่ระบุระดับ: ไม่มีเป้าหมายเวลาพบแพทย์
        }
        $m = $r['d2d'] === null ? null : (int)$r['d2d'];
        $h = er_hpi_minutes($r['t'], $r['hpi']);
        if ($h !== null && ($m === null || $h < $m)) {
            $m = $h;
        }
        $g[$lv][] = $m;
    }

    $items = [];
    foreach (ER_D2D_TARGET as $lv => $tgt) {
        $all   = $g[$lv] ?? [];
        $times = array_values(array_filter($all, fn($x) => $x !== null));
        $items[] = [
            'level'  => $lv,
            'n'      => count($all),
            'timed'  => count($times),
            'avg'    => $times ? round(array_sum($times) / count($times), 1) : null,
            'within' => count(array_filter($times, fn($x) => $x <= $tgt)),
            'target' => $tgt,
            'target_label' => ER_D2D_TARGET_LABEL[$lv],
        ];
    }

    echo json_encode(['date' => $date, 'items' => $items], JSON_UNESCAPED_UNICODE);

} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    error_log('er_d2d: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'โหลดข้อมูลไม่สำเร็จ'], JSON_UNESCAPED_UNICODE);
}
