<?php

/*
 * การ์ดสรุปผู้รับบริการ ER รายวัน (หน้า ER_detail) — ?date=YYYY-MM-DD (ค่าเริ่มต้นวันนี้)
 * นับเป็นครั้ง (แถว er_regist ของวันนั้น) เหมือนกราฟและการ์ด ER หน้าแรก · ยอดรวมเท่านั้น ไม่มีข้อมูลรายคน
 * prev_week = วันเดียวกันของสัปดาห์ก่อน (ถ้าดูวันนี้ นับถึงเวลาเดียวกัน)
 */

require_once '../config/cache.php';
date_default_timezone_set('Asia/Bangkok');   // เซิร์ฟเวอร์เป็น UTC
api_cache_by_date('er_today', 60);
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
    $isToday = $date === $today;

    $level = ER_LEVEL_SQL;
    $dch   = ER_DCH_SQL;
    $ems   = ER_EMS_CODES;
    $refIn = ER_REFER_IN_SQL;

    $sql = "
        SELECT
            COUNT(*)                                        AS total,
            COUNT(DISTINCT x.hn)                            AS people,
            COUNT(*) FILTER (WHERE x.lvl = 1)               AS l1,
            COUNT(*) FILTER (WHERE x.lvl = 2)               AS l2,
            COUNT(*) FILTER (WHERE x.lvl = 3)               AS l3,
            COUNT(*) FILTER (WHERE x.lvl = 4)               AS l4,
            COUNT(*) FILTER (WHERE x.lvl = 5)               AS l5,
            COUNT(*) FILTER (WHERE x.lvl = 6)               AS l6,
            COUNT(*) FILTER (WHERE x.lvl IS NULL)           AS l0,
            COUNT(*) FILTER (WHERE x.pt = 1)                AS pt_emergency,
            COUNT(*) FILTER (WHERE x.pt = 2)                AS pt_accident,
            COUNT(*) FILTER (WHERE x.pt = 3)                AS pt_general,
            COUNT(*) FILTER (WHERE x.pt IN (4, 5))          AS pt_service,
            COUNT(*) FILTER (WHERE x.h < 8)                 AS shift_night,
            COUNT(*) FILTER (WHERE x.h BETWEEN 8 AND 15)    AS shift_morning,
            COUNT(*) FILTER (WHERE x.h >= 16)               AS shift_evening,
            COUNT(*) FILTER (WHERE x.dch = 3)               AS admit,
            COUNT(*) FILTER (WHERE x.dch = 2)               AS refer,
            COUNT(*) FILTER (WHERE x.dch = 1)               AS home,
            COUNT(*) FILTER (WHERE x.dch IN (6, 7, 8))      AS gone,
            COUNT(*) FILTER (WHERE x.dch IS NULL)           AS no_dch,
            COUNT(*) FILTER (WHERE x.ovstist IN $ems)       AS ems,
            COUNT(*) FILTER (WHERE x.refer_in)              AS refer_in,
            COUNT(*) FILTER (WHERE x.dch = 2 OR x.refer_in) AS refer_all   -- การ์ด Refer: in หรือ out (visit ที่มีทั้งสองนับครั้งเดียว)
        FROM (
            SELECT
                $level                                      AS lvl,
                $dch                                        AS dch,
                e.er_pt_type                                AS pt,
                EXTRACT(HOUR FROM e.enter_er_time)          AS h,   -- เวรเดียวกับกราฟช่วงเวลา (08:00 / 16:00)
                o.hn,
                o.ovstist,
                $refIn                                      AS refer_in
            FROM er_regist e
            LEFT JOIN ovst o ON o.vn = e.vn
            LEFT JOIN er_emergency_type et ON et.er_emergency_type = e.er_emergency_type
            WHERE e.vstdate = :d
        ) x
    ";
    $st = $conn->prepare($sql);
    $st->execute([':d' => $date]);
    $out = array_map('intval', $st->fetch(PDO::FETCH_ASSOC));
    // สังเกตอาการ / เสียชีวิต / รหัสอื่น — ให้ 6 ช่องผลการจำหน่ายรวมได้ยอดทั้งหมด
    $out['other_dch'] = $out['total'] - $out['admit'] - $out['refer'] - $out['home'] - $out['gone'] - $out['no_dch'];

    $prev = (clone $d)->modify('-7 days')->format('Y-m-d');
    $st = $conn->prepare("SELECT COUNT(*) FROM er_regist WHERE vstdate = :p"
        . ($isToday ? " AND enter_er_time::time <= CAST(:t AS time)" : ''));
    $st->execute($isToday ? [':p' => $prev, ':t' => date('H:i:s')] : [':p' => $prev]);
    $out['prev_week'] = (int)$st->fetchColumn();

    $out['date'] = $date;
    echo json_encode($out, JSON_UNESCAPED_UNICODE);

} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    error_log('er_today_summary: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'โหลดข้อมูลไม่สำเร็จ'], JSON_UNESCAPED_UNICODE);
}
