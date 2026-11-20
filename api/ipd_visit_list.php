<?php
/*
 * รายชื่อผู้ป่วยในเบื้องหลังตัวเลขหน้า IPD (pages/ipd_detail.php)
 * GET api/ipd_visit_list.php?type=<ชนิด>[&key=<ค่า>]&d1=YYYY-MM-DD&d2=YYYY-MM-DD
 *
 * ข้อมูลส่วนบุคคล (PDPA): ต้อง login Provider ID · ไม่ cache ลงดิสก์
 * นับเป็น AN · เงื่อนไขแต่ละ type ต้องตรงกับ api/ipd_kpi_summary.php / bed_occupancy_trend.php / ipd_top10_disease.php
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
require_once __DIR__ . '/../config/ipd.php';
date_default_timezone_set('Asia/Bangkok');

$type = (string)($_GET['type'] ?? '');
$key  = trim((string)($_GET['key'] ?? ''));

$excluded = IPD_EXCLUDED_WARDS;
$deaths   = IPD_DEATH_TYPES;
$validWard = "i.ward IN (SELECT ward FROM ward WHERE ward_active = 'Y' AND ward NOT IN ($excluded))";
$byAdmit = 'i.regdate BETWEEN :d1 AND :d2';
$byDch   = 'i.dchdate BETWEEN :d1 AND :d2';
// Readmission ตามเกณฑ์มาตรฐาน (config/ipd.php — ชุดเดียวกับ api/ipd_kpi_summary.php): วันจำหน่ายครั้งก่อนที่เข้าเงื่อนไข
$prevDch = '(SELECT MAX(prev.dchdate) ' . ipd_readmit_prev_from('i') . ')';
$readmitBase = ipd_readmit_base('i');

// [เงื่อนไข, ใช้ช่วงวันที่ไหม, รูปแบบ key ที่ต้องมี] — ข้อความคงที่ทั้งหมด ค่าจากผู้ใช้ผ่าน :key / :d1 / :d2 เท่านั้น
$TYPES = [
    'current'      => ["i.dchdate IS NULL AND $validWard", false, null],
    'ward_current' => ['i.dchdate IS NULL AND i.ward = :key', false, '/^\d{2}$/'],
    'admit'        => [$byAdmit, true, null],
    'ward_admit'   => ["$byAdmit AND i.ward = :key", true, '/^\d{2}$/'],
    'readmit'      => ["$byAdmit AND $readmitBase AND $prevDch IS NOT NULL", true, null],
    'dch'          => [$byDch, true, null],
    'dchtype'      => ["$byDch AND COALESCE(i.dchtype, '') = :key", true, '/^\d{0,2}$/'],
    'death'        => ["$byDch AND i.dchtype IN ($deaths)", true, null],
    'pdx'          => ['a.dchdate BETWEEN :d1 AND :d2 AND a.pdx = :key', true, '/^[A-Z][0-9A-Z.]{1,7}$/'],
];

if (!isset($TYPES[$type]) || ($TYPES[$type][2] !== null && !preg_match($TYPES[$type][2], $key))) {
    http_response_code(400);
    echo json_encode(['error' => 'ไม่รู้จักรายการที่เลือก'], JSON_UNESCAPED_UNICODE);
    exit;
}
[$where, $usesDate, $keyRule] = $TYPES[$type];

$params = [];
if ($usesDate) {
    try {
        $p = bbh_period();   // ต้องส่ง d1/d2 (ไม่เกิน 400 วัน) — config/period.php
    } catch (InvalidArgumentException $e) {
        http_response_code(400);
        echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $params[':d1'] = $p['start'];
    $params[':d2'] = min($p['end'], date('Y-m-d'));
}
if ($keyRule !== null) {
    $params[':key'] = $key;
}

// รายชื่อช่วงยาวจำกัดจำนวนแถว (ปีงบหนึ่งมีราว 15,000 AN)
const IPD_LIST_MAX = 35000;
$prevCol = $type === 'readmit' ? "$prevDch AS prev_dch," : 'NULL AS prev_dch,';

try {
    $st = $conn->prepare("
        SELECT
            i.an, i.hn,
            TRIM(COALESCE(p.pname, '') || COALESCE(p.fname, '') || ' ' || COALESCE(p.lname, '')) AS name,
            COALESCE(a.age_y, DATE_PART('year', AGE(i.regdate, p.birthday))::int) AS age,
            p.sex,
            COALESCE(w.name, i.ward) AS ward,
            i.regdate, i.dchdate,
            CASE WHEN i.dchdate IS NULL THEN CURRENT_DATE - i.regdate
                 ELSE COALESCE(a.admdate, i.dchdate - i.regdate) END AS los,
            a.pdx,
            NULLIF(TRIM(d.tname), '') AS diag_th,
            d.name AS diag_en,
            COALESCE(i.dchtype, '') AS dchtype,
            $prevCol
            pt.name AS pttype
        FROM ipt i
        LEFT JOIN an_stat a ON a.an = i.an
        LEFT JOIN patient p ON p.hn = i.hn
        LEFT JOIN ward w    ON w.ward = i.ward
        LEFT JOIN icd101 d  ON d.code = a.pdx
        LEFT JOIN pttype pt ON pt.pttype = i.pttype
        WHERE $where
        ORDER BY i.regdate, i.regtime, i.an
        LIMIT " . (IPD_LIST_MAX + 1));
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    if (count($rows) > IPD_LIST_MAX) {
        http_response_code(413);
        echo json_encode(['error' => 'รายชื่อเกิน ' . number_format(IPD_LIST_MAX) . ' ราย กรุณาเลือกช่วงเวลาให้สั้นลง'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $rows = array_map(function ($r) {
        $r['age'] = $r['age'] === null ? null : (int)$r['age'];
        $r['los'] = $r['los'] === null ? null : (int)$r['los'];
        $r['sex'] = $r['sex'] === '1' ? 'ชาย' : ($r['sex'] === '2' ? 'หญิง' : '');
        $r['dchtype'] = $r['dchdate'] === null ? 'ยังไม่จำหน่าย'
            : (IPD_DCH_NAMES[$r['dchtype']] ?? ($r['dchtype'] === '' ? 'ไม่ระบุ' : 'รหัส ' . $r['dchtype']));
        return $r;
    }, $rows);

    echo json_encode(['type' => $type, 'd1' => $params[':d1'] ?? null, 'd2' => $params[':d2'] ?? null, 'rows' => $rows], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log('ipd_visit_list: ' . $e->getMessage());   // ไม่ส่งรายละเอียดฐานข้อมูลออกไปหน้าเว็บ
    http_response_code(500);
    echo json_encode(['error' => 'โหลดรายชื่อไม่สำเร็จ'], JSON_UNESCAPED_UNICODE);
}
