<?php
/*
 * รายชื่อผู้ป่วยเบื้องหลังตัวเลขบนการ์ดสรุป ER (ER_detail.php)
 * GET api/er_visit_list.php?date=YYYY-MM-DD&type=<ชนิด>
 *
 * ข้อมูลส่วนบุคคล (PDPA): ต้อง login Provider ID · ไม่ cache ลงดิสก์
 * นับเป็นครั้ง (แถว er_regist ของวันนั้น) · เงื่อนไขแต่ละ type ต้องตรงกับ er_today_summary.php
 * (ใช้ ER_LEVEL_SQL / ER_DCH_SQL ชุดเดียวกันใน config/er.php — ถ้าแก้ที่หนึ่ง ให้แก้อีกที่ด้วย)
 */

require_once __DIR__ . '/../auth/provider.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');   // รายชื่อผู้ป่วย: ไม่ให้ browser/proxy เก็บสำเนา

if (!provider_is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'กรุณาเข้าสู่ระบบ Provider ID เพื่อดูรายชื่อผู้ป่วย'], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/er.php';
date_default_timezone_set('Asia/Bangkok');

$date = (string)($_GET['date'] ?? date('Y-m-d'));
$d = DateTime::createFromFormat('!Y-m-d', $date);
if (!$d || $d->format('Y-m-d') !== $date || $date > date('Y-m-d')) {
    http_response_code(400);
    echo json_encode(['error' => 'วันที่ไม่ถูกต้อง'], JSON_UNESCAPED_UNICODE);
    exit;
}

// เงื่อนไขของแต่ละ type บนคอลัมน์ที่คำนวณแล้ว (x.*) — ข้อความคงที่ทั้งหมด ไม่มีค่าจากผู้ใช้
$ems = ER_EMS_CODES;
$FILTERS = [
    'all'           => 'TRUE',
    'ems'           => "x.ovstist IN $ems",
    'refer_in'      => "x.ovstist = '04'",
    'l12'           => 'x.lvl IN (1, 2)',
    'pt_emergency'  => 'x.pt = 1',
    'pt_accident'   => 'x.pt = 2',
    'pt_general'    => 'x.pt = 3',
    'pt_service'    => 'x.pt IN (4, 5)',
    'shift_night'   => 'x.h < 8',
    'shift_morning' => 'x.h BETWEEN 8 AND 15',
    'shift_evening' => 'x.h >= 16',
    'home'          => 'x.dch = 1',
    'gone'          => 'x.dch IN (6, 7, 8)',
    'no_dch'        => 'x.dch IS NULL',
    // = total − admit − refer − home − gone − no_dch ใน er_today_summary.php (สังเกตอาการ / เสียชีวิต / รหัสอื่น)
    'other_dch'     => 'x.dch IS NOT NULL AND x.dch NOT IN (1, 2, 3, 6, 7, 8)',
    'admit'         => 'x.dch = 3',
    'refer'         => 'x.dch = 2',
];

$type = (string)($_GET['type'] ?? '');
if (!isset($FILTERS[$type])) {
    http_response_code(400);
    echo json_encode(['error' => 'ไม่รู้จักรายการที่เลือก'], JSON_UNESCAPED_UNICODE);
    exit;
}

const ER_PT_NAMES = [1 => 'ฉุกเฉิน', 2 => 'อุบัติเหตุ', 3 => 'ตรวจโรคทั่วไป', 4 => 'รับบริการอื่น', 5 => 'ห้องทำแผลเวรเช้า'];

$level = ER_LEVEL_SQL;
$dch   = ER_DCH_SQL;
// หมายเหตุ SQL (คอมเมนต์ไว้นอก SQL — การเชื่อมต่อเป็น WIN874 ส่งอักขระอย่าง → · ไม่ได้):
// ประเภทผู้ป่วย: ชื่อในตาราง er_pt_type ยาวและถูกตัดกลางคำ จึงใช้ชื่อสั้นชุดเดียวกับการ์ด (ER_PT_NAMES)
// admit_ward / refer_to: ward ที่ Admit (ipt ของ visit) / รพ.ปลายทาง (referout ทุกจุดของ visit รวมในช่องเดียว) — ชุดเดียวกับ opd_visit_list.php
// d2d: door-to-doctor (นาที) จาก er_regist.door_to_doctor_second — แสดงเฉพาะ 1 วินาที–24 ชม.
//      0 = ไม่ได้บันทึก (ข้อมูลจริง 30 วัน: 0 มี 1,713 แถว มีเวลาแพทย์แค่ 6 แถว) · มีค่าจริงราว 17% ของ visit

try {
    $sql = "
        SELECT x.hn, x.name, x.age, x.enter_at, x.lvl, x.pt AS pt_code, x.arrival, x.dch, x.dch_name,
               x.pdx, x.diag_th, x.diag_en, x.d2d, x.admit_ward, x.refer_to
        FROM (
            SELECT
                $level                                          AS lvl,
                $dch                                            AS dch,
                e.er_pt_type                                    AS pt,
                EXTRACT(HOUR FROM e.enter_er_time)              AS h,
                o.hn,
                o.ovstist,
                TRIM(COALESCE(p.pname, '') || COALESCE(p.fname, '') || ' ' || COALESCE(p.lname, '')) AS name,
                v.age_y                                         AS age,
                TO_CHAR(e.enter_er_time, 'HH24:MI')             AS enter_at,
                e.enter_er_time                                 AS sort_at,
                ist.name                                        AS arrival,
                dt.name                                         AS dch_name,
                v.pdx,
                NULLIF(TRIM(i.tname), '')                       AS diag_th,
                i.name                                          AS diag_en,
                CASE WHEN e.door_to_doctor_second BETWEEN 1 AND 86400
                     THEN ROUND(e.door_to_doctor_second / 60.0)::int END AS d2d,
                (SELECT COALESCE(w.name, i.ward) FROM ipt i LEFT JOIN ward w ON w.ward = i.ward
                  WHERE i.vn = e.vn LIMIT 1)                    AS admit_ward,
                (SELECT STRING_AGG(DISTINCT COALESCE(h.name, r.refer_hospcode), ', ')
                   FROM referout r LEFT JOIN hospcode h ON h.hospcode = r.refer_hospcode
                  WHERE r.vn = e.vn)                            AS refer_to
            FROM er_regist e
            LEFT JOIN ovst o               ON o.vn = e.vn
            LEFT JOIN vn_stat v            ON v.vn = e.vn
            LEFT JOIN patient p            ON p.hn = o.hn
            LEFT JOIN er_emergency_type et ON et.er_emergency_type = e.er_emergency_type
            LEFT JOIN ovstist ist          ON ist.ovstist = o.ovstist
            LEFT JOIN er_dch_type dt       ON dt.er_dch_type = e.er_dch_type
            LEFT JOIN icd101 i             ON i.code = v.pdx
            WHERE e.vstdate = :d
        ) x
        WHERE {$FILTERS[$type]}
        ORDER BY x.sort_at NULLS LAST, x.hn
    ";
    $st = $conn->prepare($sql);
    $st->execute([':d' => $date]);

    // ผลจำหน่ายที่แสดง = ค่าที่ใช้นับ (Admit / Refer มาจาก ipt / referout ได้แม้ er_dch_type ว่าง)
    $rows = array_map(function ($r) {
        $dchCode = $r['dch'] === null ? null : (int)$r['dch'];
        $r['dch'] = $dchCode === 3 ? 'Admit'
            : ($dchCode === 2 ? 'Refer ส่งต่อ'
            : ($dchCode === null ? 'ยังไม่ลงผล' : ($r['dch_name'] ?: 'รหัส ' . $dchCode)));
        unset($r['dch_name']);
        $r['lvl'] = $r['lvl'] === null ? 'ไม่ระบุ' : ((int)$r['lvl'] === 6 ? 'Black' : 'ระดับ ' . (int)$r['lvl']);
        $r['pt'] = ER_PT_NAMES[(int)$r['pt_code']] ?? 'ไม่ระบุ';
        unset($r['pt_code']);
        $r['age'] = $r['age'] === null ? null : (int)$r['age'];
        $r['d2d'] = $r['d2d'] === null ? null : (int)$r['d2d'];
        return $r;
    }, $st->fetchAll(PDO::FETCH_ASSOC));

    echo json_encode(['date' => $date, 'type' => $type, 'rows' => $rows], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log('er_visit_list: ' . $e->getMessage());   // ไม่ส่งรายละเอียดฐานข้อมูลออกไปหน้าเว็บ
    http_response_code(500);
    echo json_encode(['error' => 'โหลดรายชื่อไม่สำเร็จ'], JSON_UNESCAPED_UNICODE);
}
