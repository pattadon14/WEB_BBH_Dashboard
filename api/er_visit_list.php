<?php
/*
 * รายชื่อผู้ป่วยเบื้องหลังตัวเลขบนการ์ดสรุป ER (ER_detail.php)
 * GET api/er_visit_list.php?date=YYYY-MM-DD&type=<ชนิด>  หรือ ?d1=&d2=&type=<ชนิด>[&v=<ค่า>] (กราฟสถิติตามช่วงเวลา)
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

// วันเดียว ?date= (การ์ดสรุปรายวัน) หรือช่วง ?d1=&d2= (กราฟสถิติตามช่วงเวลา ไม่เกิน 400 วัน)
// ปีงบที่ยังไม่จบส่ง d2 เป็นวันสิ้นปีงบ → ตัดที่วันนี้
$okDate = fn($s) => ($x = DateTime::createFromFormat('!Y-m-d', $s)) && $x->format('Y-m-d') === $s;
$date = (string)($_GET['date'] ?? date('Y-m-d'));
$d1 = (string)($_GET['d1'] ?? $date);
$d2 = (string)($_GET['d2'] ?? $date);
$ok = $okDate($d1) && $okDate($d2);
$d2 = $ok ? min($d2, date('Y-m-d')) : $d2;
if (!$ok || $d1 > $d2 || (strtotime($d2) - strtotime($d1)) / 86400 > 400) {
    http_response_code(400);
    echo json_encode(['error' => 'วันที่ไม่ถูกต้อง'], JSON_UNESCAPED_UNICODE);
    exit;
}

// เงื่อนไขของแต่ละ type บนคอลัมน์ที่คำนวณแล้ว (x.*) — ข้อความคงที่ทั้งหมด ไม่มีค่าจากผู้ใช้
$ems = ER_EMS_CODES;
$FILTERS = [
    'all'           => 'TRUE',
    'ems'           => "x.ovstist IN $ems",
    'refer_in'      => 'x.refer_in',
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
    'refer_all'     => '(x.dch = 2 OR x.refer_in)',   // = refer_all ใน er_today_summary.php
    // กราฟสถิติตามช่วงเวลา (ER_detail.js) — ชุดเดียวกับ api/er_chart.php, er_triage.php, er_refer.php, er_top10_diagnosis.php
    'non_accident'  => 'x.pt IS DISTINCT FROM 2',
    'ipd_refer'     => 'x.ipd_refer',
    'lvl'           => 'COALESCE(x.lvl, 0) = :v',                                  // 0 = ไม่ระบุระดับ · 6 = Black
    'pt'            => 'x.pt = :v',
    'pdx'           => 'x.pdx = :v AND COALESCE(x.pt, 0) NOT IN (4, 5)',          // = นิยาม 10 อันดับโรค
];
// ค่าประกอบของ type ที่มี :v (ตรวจรูปแบบก่อน bind)
$V_RULES = ['lvl' => '/^[0-6]$/', 'pt' => '/^[1-5]$/', 'pdx' => '/^[A-Z][0-9A-Z.]{1,7}$/'];

$type = (string)($_GET['type'] ?? '');
$v = (string)($_GET['v'] ?? '');
if (!isset($FILTERS[$type]) || (isset($V_RULES[$type]) && !preg_match($V_RULES[$type], $v))) {
    http_response_code(400);
    echo json_encode(['error' => 'ไม่รู้จักรายการที่เลือก'], JSON_UNESCAPED_UNICODE);
    exit;
}

const ER_PT_NAMES = [1 => 'ฉุกเฉิน', 2 => 'อุบัติเหตุ', 3 => 'ตรวจโรคทั่วไป', 4 => 'รับบริการอื่น', 5 => 'ห้องทำแผลเวรเช้า'];

$level = ER_LEVEL_SQL;
$refIn = ER_REFER_IN_SQL;
$dch   = ER_DCH_SQL;
$d2d   = ER_D2D_SQL;
$hpi   = ER_D2D_HPI_SQL;
$ipdRef = ER_IPD_REFER_SQL;
$picks  = ER_REFER_OUT_PICK . "\n" . ER_REFER_IN_PICK . "\n" . ER_IPD_REFER_PICK;
// refer_dest / refer_src / ipd_dest: ใบเดียวกับที่ api/er_refer.php ใช้นับ (ER_*_PICK) ชื่อย่อด้วย er_hosp_short — ให้คลิกชื่อ รพ. แล้วรายชื่อตรงกับตัวเลข
// หมายเหตุ SQL (คอมเมนต์ไว้นอก SQL — การเชื่อมต่อเป็น WIN874 ส่งอักขระอย่าง → · ไม่ได้):
// ประเภทผู้ป่วย: ชื่อในตาราง er_pt_type ยาวและถูกตัดกลางคำ จึงใช้ชื่อสั้นชุดเดียวกับการ์ด (ER_PT_NAMES)
// refer_from: รพ.ต้นทางจากใบรับส่งต่อ (referin) · ลงวิธีมา 04 แต่ไม่มีใบ referin จะว่าง
// admit_ward / refer_to: ward ที่ Admit (ipt ของ visit) / รพ.ปลายทาง (referout ทุกจุดของ visit รวมในช่องเดียว) — ชุดเดียวกับ opd_visit_list.php
// d2d: door-to-doctor (นาที) = ER_D2D_SQL + er_hpi_minutes() ใน config/er.php (ชุดเดียวกับ api/er_d2d.php)
//      ไม่ใช้ door_to_doctor_second (ลงแค่ราว 17% ของ visit)

try {
    $sql = "
        SELECT x.hn, x.name, x.age, x.enter_at, x.lvl, x.pt AS pt_code, x.arrival, x.dch, x.dch_name,
               x.pdx, x.diag_th, x.diag_en, x.d2d, x.enter_ts, x.hpi, x.admit_ward, x.refer_to, x.refer_from, x.refer_in,
               x.vstdate, x.refer_dest, x.refer_reason, x.refer_src, x.ipd_refer, x.ipd_dest, x.ipd_reason, x.ipd_transfer
        FROM (
            SELECT
                $level                                          AS lvl,
                $dch                                            AS dch,
                e.er_pt_type                                    AS pt,
                EXTRACT(HOUR FROM e.enter_er_time)              AS h,
                o.hn,
                o.ovstist,
                $refIn                                          AS refer_in,
                TRIM(COALESCE(p.pname, '') || COALESCE(p.fname, '') || ' ' || COALESCE(p.lname, '')) AS name,
                v.age_y                                         AS age,
                TO_CHAR(e.enter_er_time, 'HH24:MI')             AS enter_at,
                e.enter_er_time                                 AS sort_at,
                ist.name                                        AS arrival,
                dt.name                                         AS dch_name,
                v.pdx,
                NULLIF(TRIM(i.tname), '')                       AS diag_th,
                i.name                                          AS diag_en,
                $d2d                                            AS d2d,
                e.enter_er_time                                 AS enter_ts,
                $hpi                                            AS hpi,
                (SELECT COALESCE(w.name, i.ward) FROM ipt i LEFT JOIN ward w ON w.ward = i.ward
                  WHERE i.vn = e.vn LIMIT 1)                    AS admit_ward,
                (SELECT STRING_AGG(DISTINCT COALESCE(h.name, r.refer_hospcode), ', ')
                   FROM referout r LEFT JOIN hospcode h ON h.hospcode = r.refer_hospcode
                  WHERE r.vn = e.vn)                            AS refer_to,
                (SELECT STRING_AGG(DISTINCT COALESCE(h.name, ri.refer_hospcode), ', ')
                   FROM referin ri LEFT JOIN hospcode h ON h.hospcode = ri.refer_hospcode
                  WHERE ri.vn = e.vn)                           AS refer_from,
                e.vstdate,
                COALESCE(hro.name, ro.code)                     AS refer_dest,
                rfo.name                                        AS refer_reason,
                COALESCE(hin.name, rin.code)                    AS refer_src,
                $ipdRef                                         AS ipd_refer,
                COALESCE(hq.name, rq.code)                      AS ipd_dest,
                rfq.name                                        AS ipd_reason,
                rq.transfer                                     AS ipd_transfer
            FROM er_regist e
            LEFT JOIN ovst o               ON o.vn = e.vn
            LEFT JOIN vn_stat v            ON v.vn = e.vn
            LEFT JOIN patient p            ON p.hn = o.hn
            LEFT JOIN er_emergency_type et ON et.er_emergency_type = e.er_emergency_type
            LEFT JOIN ovstist ist          ON ist.ovstist = o.ovstist
            LEFT JOIN er_dch_type dt       ON dt.er_dch_type = e.er_dch_type
            LEFT JOIN icd101 i             ON i.code = v.pdx
            LEFT JOIN service_time s       ON s.vn = e.vn
            LEFT JOIN opdscreen sc         ON sc.vn = e.vn
            $picks
            LEFT JOIN hospcode hro ON hro.hospcode = ro.code
            LEFT JOIN rfrcs rfo    ON rfo.rfrcs = ro.rfrcs
            LEFT JOIN hospcode hin ON hin.hospcode = rin.code
            LEFT JOIN hospcode hq  ON hq.hospcode = rq.code
            LEFT JOIN rfrcs rfq    ON rfq.rfrcs = rq.rfrcs
            WHERE e.vstdate BETWEEN :d1 AND :d2
        ) x
        WHERE {$FILTERS[$type]}
        ORDER BY x.sort_at NULLS LAST, x.hn
    ";
    $st = $conn->prepare($sql);
    $params = [':d1' => $d1, ':d2' => $d2];
    if (isset($V_RULES[$type])) {
        $params[':v'] = $type === 'pdx' ? $v : (int)$v;
    }
    $st->execute($params);

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
        // ทิศทางการส่งต่อ (หน้าต่าง Refer รวม): in = รับเข้า · out = ผลจำหน่ายส่งต่อ
        $in = (bool)$r['refer_in'];
        $out = $dchCode === 2;
        $r['ref_dir'] = $in && $out ? 'in+out' : ($in ? 'in' : ($out ? 'out' : null));
        unset($r['refer_in']);
        // Door-to-Doctor นิยามเดียวกับ api/er_d2d.php: เร็วที่สุดของแหล่งใน SQL กับเวลาใน HPI
        $m = $r['d2d'] === null ? null : (int)$r['d2d'];
        $h = $r['enter_ts'] === null ? null : er_hpi_minutes($r['enter_ts'], $r['hpi']);
        $r['d2d'] = $h !== null && ($m === null || $h < $m) ? $h : $m;
        unset($r['enter_ts'], $r['hpi']);
        // การส่งต่อ: ชื่อ รพ. แบบสั้นชุดเดียวกับ api/er_refer.php · null = ไม่มีใบ
        foreach (['refer_dest', 'refer_src', 'ipd_dest'] as $k) {
            $r[$k] = er_hosp_short($r[$k]);
        }
        $r['ipd_refer'] = (bool)$r['ipd_refer'];
        $r['ipd_transfer'] = $r['ipd_refer'] ? ($r['ipd_transfer'] ? 'By Transfer' : 'จำหน่ายแบบอื่น') : null;
        return $r;
    }, $st->fetchAll(PDO::FETCH_ASSOC));

    echo json_encode(['d1' => $d1, 'd2' => $d2, 'type' => $type, 'rows' => $rows], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log('er_visit_list: ' . $e->getMessage());   // ไม่ส่งรายละเอียดฐานข้อมูลออกไปหน้าเว็บ
    http_response_code(500);
    echo json_encode(['error' => 'โหลดรายชื่อไม่สำเร็จ'], JSON_UNESCAPED_UNICODE);
}
