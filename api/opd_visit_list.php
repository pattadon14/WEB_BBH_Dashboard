<?php
/*
 * รายชื่อผู้ป่วยเบื้องหลังตัวเลขในแท็บตรวจโรคทั่วไป (opd_detail.php)
 * GET api/opd_visit_list.php?date=YYYY-MM-DD&type=<ชนิด>[&key=<ค่า>]
 *
 * ข้อมูลส่วนบุคคล (PDPA): ต้อง login Provider ID · ไม่ cache ลงดิสก์
 * เงื่อนไขแต่ละ type ต้องตรงกับที่ใช้นับใน opd_visit_today.php (ถ้าแก้ที่หนึ่ง ให้แก้อีกที่ด้วย)
 */

require_once __DIR__ . '/../auth/provider.php';

header('Content-Type: application/json; charset=utf-8');

if (!provider_is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'กรุณาเข้าสู่ระบบ Provider ID เพื่อดูรายชื่อผู้ป่วย'], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/opd.php';

$date = $_GET['date'] ?? date('Y-m-d');
$d = DateTime::createFromFormat('Y-m-d', $date);
if (!$d || $d->format('Y-m-d') !== $date) {
    http_response_code(400);
    echo json_encode(['error' => 'วันที่ไม่ถูกต้อง'], JSON_UNESCAPED_UNICODE);
    exit;
}

$type = $_GET['type'] ?? '';
$key  = trim((string)($_GET['key'] ?? ''));

$EXIT_DEPS = "'014','053','083','200','225','234','025','203','204','076','131','176','199','206'";
$IN_SERVICE = "(o.an IS NULL AND o.cur_dep IS DISTINCT FROM '999')";
$WAIT_TRIAGE = "$IN_SERVICE AND st.doctor_at IS NULL AND st.screen_at IS NULL";
$WAIT_EXAM = "$IN_SERVICE AND st.doctor_at IS NULL AND st.screen_at IS NOT NULL AND o.cur_dep NOT IN ($EXIT_DEPS)";
// นัดที่มาแล้วผ่าน visit ชุด OPD ของวันนั้น (นิยามเดียวกับ opd_appt_came_sql ใน config/opd.php)
$APPT_CAME = "oa.visit_vn IN (SELECT o2.vn FROM ovst o2 WHERE o2.vstdate = :d AND o2.main_dep IN (" . opd_main_dep_sql() . "))";
$APPT_MISS = opd_appt_miss_sql(':d');

// เงื่อนไขเพิ่มของแต่ละ type (ข้อความคงที่ทั้งหมด ค่าจากผู้ใช้ผ่าน :key เท่านั้น)
$VISIT_FILTERS = [
    'all'         => 'TRUE',
    'new'         => 'p.firstday = o.vstdate',
    'old'         => 'p.firstday IS DISTINCT FROM o.vstdate',
    'walkin'      => 'NOT ' . opd_appt_came_sql(),
    'night'       => "(o.vsttime > '20:00:00' OR o.vsttime < '08:00:00')",
    'morning'     => "(o.vsttime >= '08:00:00' AND o.vsttime < '16:00:00')",
    'evening'     => "(o.vsttime >= '16:00:00' AND o.vsttime <= '20:00:00')",
    'wait_triage' => $WAIT_TRIAGE,
    'wait_exam'   => $WAIT_EXAM,
    'finish'      => 'st.doctor_at IS NOT NULL',
    'no_doctor'   => "st.doctor_at IS NULL AND NOT COALESCE(($WAIT_TRIAGE) OR ($WAIT_EXAM), FALSE)",
    'not_seen'    => 'st.doctor_at IS NULL',   // วันที่ผ่านมาแล้ว: การ์ด "ไม่มีบันทึกพบแพทย์" = ยอดรวม − ตรวจเสร็จ
    'admit'       => "COALESCE(o.an, '') <> ''",
    'refer'       => 'EXISTS (SELECT 1 FROM referout r WHERE r.vn = o.vn)',
    'dept'        => 'COALESCE(k.department, o.main_dep) = :key',
    'payer'       => "pt.hipdata_code = ANY(string_to_array(:key, ','))",
    'nation'      => "COALESCE(n.name, 'ไม่ระบุ') = :key",
];
$APPT_FILTERS = [
    'appt'      => "(($APPT_CAME) OR ($APPT_MISS))",
    'appt_came' => $APPT_CAME,
    'appt_miss' => $APPT_MISS,
];

$needsKey = in_array($type, ['dept', 'payer', 'nation'], true);
if ((!isset($VISIT_FILTERS[$type]) && !isset($APPT_FILTERS[$type])) || ($needsKey && $key === '')) {
    http_response_code(400);
    echo json_encode(['error' => 'ไม่รู้จักรายการที่เลือก'], JSON_UNESCAPED_UNICODE);
    exit;
}
if ($type === 'payer' && !preg_match('/^[A-Z]{2,4}(,[A-Z]{2,4})*$/', $key)) {
    http_response_code(400);
    echo json_encode(['error' => 'รหัสสิทธิ์ไม่ถูกต้อง'], JSON_UNESCAPED_UNICODE);
    exit;
}

$params = [':d' => $date];
if ($needsKey) $params[':key'] = $key;

try {
    if (isset($VISIT_FILTERS[$type])) {
        /*
         * เวลาแต่ละขั้น = บันทึกแรกของ visit ใน ovst_service_time
         * รอซักประวัติ = ลงทะเบียน → ซักประวัติครั้งแรก, รอแพทย์ = ซักประวัติครั้งแรก → พบแพทย์ครั้งแรก
         * ค่าติดลบ (เช่น ซักประวัติหลังพบแพทย์ที่จุด Exit) แสดงเป็นค่าว่าง
         */
        $sql = "SELECT
                o.hn,
                TRIM(COALESCE(p.pname, '') || COALESCE(p.fname, '') || ' ' || COALESCE(p.lname, '')) AS name,
                TO_CHAR(o.vsttime, 'HH24:MI')                AS vsttime,
                COALESCE(k.department, o.main_dep)           AS main_dep,
                CASE WHEN o.cur_dep = '999' THEN 'กลับบ้าน' ELSE COALESCE(kc.department, o.cur_dep) END AS cur_dep,
                COALESCE(pt.name, o.pttype)                  AS pttype,
                pt.hipdata_code                              AS hipdata,
                COALESCE(n.name, 'ไม่ระบุ')                  AS nation,
                TO_CHAR(st.screen_at, 'HH24:MI')             AS screen_at,
                TO_CHAR(st.doctor_at, 'HH24:MI')             AS doctor_at,
                (EXTRACT(EPOCH FROM st.screen_at - (o.vstdate + o.vsttime)) / 60)::int AS wait_screen,
                (EXTRACT(EPOCH FROM st.doctor_at - st.screen_at) / 60)::int           AS wait_doctor,
                (p.firstday = o.vstdate)                     AS is_new,
                COALESCE(o.an, '') <> ''                     AS is_admit
            FROM ovst o
            LEFT JOIN patient p        ON p.hn = o.hn
            LEFT JOIN kskdepartment k  ON k.depcode = o.main_dep
            LEFT JOIN kskdepartment kc ON kc.depcode = o.cur_dep
            LEFT JOIN pttype pt        ON pt.pttype = o.pttype
            LEFT JOIN nationality n    ON n.nationality = p.nationality
            LEFT JOIN LATERAL (
                SELECT
                    MIN(s.service_begin_datetime) FILTER (WHERE s.ovst_service_time_type_code = 'OPD-SCREEN') AS screen_at,
                    MIN(s.service_begin_datetime) FILTER (WHERE s.ovst_service_time_type_code = 'OPD-DOCTOR') AS doctor_at
                FROM ovst_service_time s
                WHERE s.vn = o.vn
            ) st ON TRUE
            WHERE o.vstdate = :d
              AND o.main_dep IN (" . opd_main_dep_sql() . ")
              AND {$VISIT_FILTERS[$type]}
            ORDER BY o.vsttime, o.hn";
        $kind = 'visit';
    } else {
        // 1 แถวต่อ 1 visit ที่มาตามนัด / 1 คนที่ยังไม่มา (นัดแรก) — จำนวนแถวตรงกับตัวเลขบนการ์ด
        $sql = "SELECT * FROM (
            SELECT DISTINCT ON (COALESCE(NULLIF(oa.visit_vn, ''), 'hn:' || oa.hn))
                oa.hn,
                TRIM(COALESCE(p.pname, '') || COALESCE(p.fname, '') || ' ' || COALESCE(p.lname, '')) AS name,
                TO_CHAR(oa.nexttime, 'HH24:MI')              AS appt_time,
                COALESCE(cl.name, oa.clinic)                 AS clinic,
                CASE
                    WHEN COALESCE(oa.visit_vn, '') <> '' THEN 'มาตามนัด'
                    WHEN oa.oapp_status_id = 4          THEN 'ยกเลิกนัด'
                    ELSE 'ยังไม่มา'
                END                                          AS status,
                TO_CHAR(v.vsttime, 'HH24:MI')                AS vsttime,
                COALESCE(pt.name, v.pttype)                  AS pttype,
                COALESCE(n.name, 'ไม่ระบุ')                  AS nation,
                oa.nexttime                                  AS sort_time
            FROM oapp oa
            LEFT JOIN clinic cl     ON cl.clinic = oa.clinic
            LEFT JOIN patient p     ON p.hn = oa.hn
            LEFT JOIN ovst v        ON v.vn = oa.visit_vn
            LEFT JOIN pttype pt     ON pt.pttype = v.pttype
            LEFT JOIN nationality n ON n.nationality = p.nationality
            WHERE {$APPT_FILTERS[$type]}
            ORDER BY COALESCE(NULLIF(oa.visit_vn, ''), 'hn:' || oa.hn), oa.nexttime NULLS LAST
        ) a
        ORDER BY sort_time NULLS LAST, hn";
        $kind = 'appt';
    }

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as &$r) {
        unset($r['sort_time']);
        foreach (['wait_screen', 'wait_doctor'] as $f) {
            if (array_key_exists($f, $r)) {
                $r[$f] = ($r[$f] === null || (int)$r[$f] < 0) ? null : (int)$r[$f];
            }
        }
        foreach (['is_new', 'is_admit'] as $f) {
            if (array_key_exists($f, $r)) $r[$f] = (bool)$r[$f];
        }
    }
    unset($r);

    echo json_encode(['kind' => $kind, 'rows' => $rows], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    error_log('opd_visit_list: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'โหลดรายชื่อไม่สำเร็จ'], JSON_UNESCAPED_UNICODE);
}
