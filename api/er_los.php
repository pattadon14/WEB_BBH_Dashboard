<?php

/*
 * สถานะ ER ตอนนี้ + ระยะเวลาอยู่ใน ER (LOS) — ?date=YYYY-MM-DD (ค่าเริ่มต้นวันนี้) · นิยามใน config/er.php
 * now (เฉพาะวันนี้): อยู่ใน ER = ยังไม่มี leave_datetime + cur_dep เป็นแผนก ER + เข้า ER ภายใน 24 ชม.
 *   waiting = ยังไม่มีเวลาพบแพทย์ (ER_D2D_SQL + HPI) · over4h = อยู่เกิน 240 นาที · longest = นาทีของรายที่อยู่นานสุด
 * los: visit ที่ ER คัดกรองแล้ว ไม่นับงานบริการ (er_pt_type 4, 5 ทำแผล/ฉีดยา) · avg / median เฉพาะรายที่มี leave_datetime
 * admit / refer: เวลาเข้า ER ถึงลงทะเบียน Admit (ipt) / ถึงเวลา Refer (referout) — ตรงกับต้นแบบ time_to_disposition
 * ยอดรวมเท่านั้น ไม่มีข้อมูลรายคน
 */

require_once '../config/cache.php';
date_default_timezone_set('Asia/Bangkok');
api_cache_by_date('er_los', 60);
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

    $deps   = ER_DEPCODES;
    $dch    = ER_DCH_SQL;
    $board  = ER_BOARDING_MIN;
    $screen = "e.enter_er_time IS NOT NULL
          AND (e.er_emergency_level_id IS NOT NULL OR e.er_emergency_type IS NOT NULL) AND e.er_pt_type IS NOT NULL";

    // LOS ของวันที่เลือก
    $st = $conn->prepare("
        SELECT COUNT(*) AS n, COUNT(m) AS timed, ROUND(AVG(m)) AS avg,
               percentile_cont(0.5) WITHIN GROUP (ORDER BY m) AS median,
               COUNT(*) FILTER (WHERE m > 240) AS over4h, COUNT(*) FILTER (WHERE m > 360) AS over6h
        FROM (
            SELECT CASE WHEN x.m BETWEEN 0 AND 1440 THEN x.m END AS m
            FROM (SELECT (EXTRACT(EPOCH FROM (e.leave_datetime - e.enter_er_time)) / 60)::integer AS m
                  FROM er_regist e WHERE e.vstdate = :d AND $screen AND e.er_pt_type NOT IN (4, 5)) x
        ) y
    ");
    $st->execute([':d' => $date]);
    $los = $st->fetch(PDO::FETCH_ASSOC);

    // เข้า ER ถึง Admit / Refer (นับ 0–1440 นาที) — SQL ชุดเดียวกับต้นแบบ
    $ttd = function (string $inner) use ($conn, $date, $board) {
        $st = $conn->prepare("SELECT COUNT(*) AS n, ROUND(AVG(m)) AS avg, COUNT(*) FILTER (WHERE m > $board) AS over
                              FROM ($inner) x WHERE m BETWEEN 0 AND 1440");
        $st->execute([':d' => $date]);
        return array_map(fn($v) => $v === null ? null : (int)$v, $st->fetch(PDO::FETCH_ASSOC));
    };
    $admit = $ttd("SELECT (EXTRACT(EPOCH FROM ((i.regdate::date + i.regtime) - e.enter_er_time)) / 60)::integer AS m
                   FROM er_regist e JOIN ipt i ON i.vn = e.vn WHERE e.vstdate = :d AND $screen AND i.regtime IS NOT NULL");
    $refer = $ttd("SELECT (EXTRACT(EPOCH FROM ((SELECT MIN(r.refer_date::date + r.refer_time) FROM referout r WHERE r.vn = e.vn)
                          - e.enter_er_time)) / 60)::integer AS m
                   FROM er_regist e WHERE e.vstdate = :d AND $screen AND ($dch) = 2");

    $out = [
        'date'  => $date,
        'los'   => [
            'n' => (int)$los['n'], 'timed' => (int)$los['timed'],
            'avg' => $los['avg'] === null ? null : (int)$los['avg'],
            'median' => $los['median'] === null ? null : (int)round($los['median']),
            'over4h' => (int)$los['over4h'], 'over6h' => (int)$los['over6h'],
        ],
        'admit' => $admit, 'refer' => $refer, 'boarding_min' => $board,
        'now'   => null,
    ];

    if ($date === $today) {
        $level = ER_LEVEL_SQL;
        $d2d   = ER_D2D_SQL;
        $hpi   = ER_D2D_HPI_SQL;
        $st = $conn->prepare("
            SELECT $level AS lvl, $d2d AS d2d, e.enter_er_time AS t, $hpi AS hpi,
                   (EXTRACT(EPOCH FROM (LOCALTIMESTAMP - e.enter_er_time)) / 60)::integer AS stay
            FROM er_regist e
            JOIN ovst o                    ON o.vn = e.vn
            LEFT JOIN er_emergency_type et ON et.er_emergency_type = e.er_emergency_type
            LEFT JOIN service_time s       ON s.vn = e.vn
            LEFT JOIN opdscreen sc         ON sc.vn = e.vn
            WHERE e.vstdate >= CAST(:d AS date) - 1
              AND e.enter_er_time BETWEEN LOCALTIMESTAMP - interval '24 hours' AND LOCALTIMESTAMP
              AND e.leave_datetime IS NULL AND o.cur_dep IN $deps
        ");
        $st->execute([':d' => $date]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        $waiting = 0;
        $l12 = 0;
        foreach ($rows as $r) {
            $seen = $r['d2d'] !== null || er_hpi_minutes($r['t'], $r['hpi']) !== null;
            $waiting += $seen ? 0 : 1;
            $l12 += in_array((int)$r['lvl'], [1, 2], true) ? 1 : 0;
        }
        $stays = array_map(fn($r) => (int)$r['stay'], $rows);
        $out['now'] = [
            'in_er'   => count($rows),
            'l12'     => $l12,
            'waiting' => $waiting,
            'over4h'  => count(array_filter($stays, fn($m) => $m > 240)),
            'longest' => $stays ? max($stays) : null,
            'at'      => date('H:i'),
        ];
    }

    echo json_encode($out, JSON_UNESCAPED_UNICODE);

} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    error_log('er_los: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'โหลดข้อมูลไม่สำเร็จ'], JSON_UNESCAPED_UNICODE);
}
