<?php
/*
 * รายชื่อผู้ป่วยเบื้องหลังตัวเลขในแท็บตรวจโรคทั่วไป/คลินิกพิเศษ (opd_detail.php)
 * GET api/opd_visit_list.php?date=YYYY-MM-DD&type=<ชนิด>[&key=<ค่า>][&scope=general|special]
 *
 * ข้อมูลส่วนบุคคล (PDPA): ต้อง login Provider ID · ไม่ cache ลงดิสก์
 * เงื่อนไขแต่ละ type ต้องตรงกับที่ใช้นับใน opd_visit_today.php (ถ้าแก้ที่หนึ่ง ให้แก้อีกที่ด้วย)
 * admit_ward = ward ของ ipt (ผ่าน ovst.an) · refer_to = รพ.ปลายทางจาก referout.refer_hospcode
 *   (บาง visit มี referout มากกว่า 1 แถว ราว 21 visit / 90 วัน → รวมชื่อในช่องเดียว ไม่เพิ่มแถว)
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
// ?scope=general (ตรวจโรคทั่วไป) | special (คลินิกพิเศษ) | all (OPD ทั้งหมด — ส่วนปีงบประมาณ)
$scope = ($_GET['scope'] ?? '') === 'all' ? 'all' : opd_scope();

$type = $_GET['type'] ?? '';
$key  = trim((string)($_GET['key'] ?? ''));

$isDate = fn($s) => ($o = DateTime::createFromFormat('Y-m-d', (string)$s)) && $o->format('Y-m-d') === $s;
// ช่วงวันที่ (?d1=&d2=) ใช้กับส่วนปีงบประมาณ: แท่งรายเดือน (all), กลุ่มอายุ (age), 10 อันดับโรค (pdx) — ไม่เกิน 400 วัน
$isRange = isset($_GET['d1']) || isset($_GET['d2']);
// รายชื่อช่วงยาวจำกัดจำนวนแถว: ทั้งปีของกลุ่มใหญ่ (เช่น วัยทำงาน ~150,000 ครั้ง) เกินหน่วยความจำ PHP และหนักเกินเบราว์เซอร์
const OPD_LIST_MAX = 35000;
if ($isRange) {
    $d1 = (string)($_GET['d1'] ?? '');
    $d2 = (string)($_GET['d2'] ?? '');
    if (!$isDate($d1) || !$isDate($d2) || $d2 < $d1 || (strtotime($d2) - strtotime($d1)) / 86400 > 400
        || !in_array($type, ['all', 'age', 'pdx'], true)) {
        http_response_code(400);
        echo json_encode(['error' => 'ช่วงวันที่ไม่ถูกต้อง'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $date = $d1;
} else {
    $date = $_GET['date'] ?? date('Y-m-d');
    if (!$isDate($date)) {
        http_response_code(400);
        echo json_encode(['error' => 'วันที่ไม่ถูกต้อง'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

$EXIT_DEPS = "'014','053','083','200','225','234','025','203','204','076','131','176','199','206'";
$IN_SERVICE = "(o.an IS NULL AND o.cur_dep IS DISTINCT FROM '999')";
$WAIT_TRIAGE = "$IN_SERVICE AND st.doctor_at IS NULL AND st.screen_at IS NULL";
$WAIT_EXAM = "$IN_SERVICE AND st.doctor_at IS NULL AND st.screen_at IS NOT NULL AND o.cur_dep NOT IN ($EXIT_DEPS)";
// นัดที่มาแล้วผ่าน visit ชุด OPD ของวันนั้น (นิยามเดียวกับ opd_appt_came_sql ใน config/opd.php)
$APPT_CAME = "oa.visit_vn IN (SELECT o2.vn FROM ovst o2 WHERE o2.vstdate = :d AND o2.main_dep IN (" . opd_dep_sql($scope) . "))";
$APPT_MISS = opd_appt_miss_sql(':d', $scope);

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
    // แท่งกราฟรายชั่วโมง / จุดตรวจ (api/opd_general_today.php): มาลงทะเบียน = ชั่วโมงของ vsttime,
    // พบแพทย์ = ชั่วโมงของบันทึกพบแพทย์ครั้งแรกที่อยู่ในวันเดียวกัน, จุดตรวจ = แผนกของบันทึกพบแพทย์ครั้งแรก
    'hour_in'     => 'EXTRACT(HOUR FROM o.vsttime)::int = CAST(:key AS int)',
    'hour_seen'   => 'st.doctor_at::date = o.vstdate AND EXTRACT(HOUR FROM st.doctor_at)::int = CAST(:key AS int)',
    'room'        => "(SELECT COALESCE(k2.department, t.depcode) FROM ovst_service_time t
                         LEFT JOIN kskdepartment k2 ON k2.depcode = t.depcode
                        WHERE t.vn = o.vn AND t.ovst_service_time_type_code = 'OPD-DOCTOR'
                        ORDER BY t.service_begin_datetime LIMIT 1) = :key",
    // ส่วนปีงบประมาณ: กลุ่มอายุ ณ วันที่มา (นิยามเดียวกับ api/opd_age_group.php) และการวินิจฉัยหลัก (api/opd_top10_disease.php)
    'age'         => "p.birthday IS NOT NULL AND CASE
                        WHEN DATE_PART('year', AGE(o.vstdate, p.birthday)) BETWEEN 0 AND 5 THEN 1
                        WHEN DATE_PART('year', AGE(o.vstdate, p.birthday)) BETWEEN 6 AND 14 THEN 2
                        WHEN DATE_PART('year', AGE(o.vstdate, p.birthday)) BETWEEN 15 AND 59 THEN 3
                        WHEN DATE_PART('year', AGE(o.vstdate, p.birthday)) >= 60 THEN 4
                        ELSE 0 END = CAST(:key AS int)",
    'pdx'         => 'EXISTS (SELECT 1 FROM vn_stat vs WHERE vs.vn = o.vn AND vs.pdx = :key)',
];
$APPT_FILTERS = [
    'appt'      => "(($APPT_CAME) OR ($APPT_MISS))",
    'appt_came' => $APPT_CAME,
    'appt_miss' => $APPT_MISS,
];

$needsKey = in_array($type, ['dept', 'payer', 'nation', 'hour_in', 'hour_seen', 'room', 'age', 'pdx'], true);
if ((!isset($VISIT_FILTERS[$type]) && !isset($APPT_FILTERS[$type])) || ($needsKey && $key === '')) {
    http_response_code(400);
    echo json_encode(['error' => 'ไม่รู้จักรายการที่เลือก'], JSON_UNESCAPED_UNICODE);
    exit;
}
if (($type === 'payer' && !preg_match('/^[A-Z]{2,4}(,[A-Z]{2,4})*$/', $key))
    || (str_starts_with($type, 'hour_') && !preg_match('/^\d{1,2}$/', $key))
    || ($type === 'age' && !preg_match('/^[1-4]$/', $key))
    || ($type === 'pdx' && !preg_match('/^[A-Z][0-9A-Z.]{1,7}$/', $key))) {
    http_response_code(400);
    echo json_encode(['error' => 'ค่าที่เลือกไม่ถูกต้อง'], JSON_UNESCAPED_UNICODE);
    exit;
}

$params = $isRange ? [':d1' => $d1, ':d2' => $d2] : [':d' => $date];
$visitDate = $isRange ? 'o.vstdate BETWEEN CAST(:d1 AS date) AND CAST(:d2 AS date)' : 'o.vstdate = :d';
if ($needsKey) $params[':key'] = $key;

// นัดหมาย: กรองคลินิก (key = ชื่อคลินิกในกราฟนัดหมายรายคลินิก) และช่วงเวลานัด 30 นาที (slot = "HH:MM" | "ไม่ระบุ")
$apptClinic = '';
$apptSlot = '';
if (isset($APPT_FILTERS[$type])) {
    if ($key !== '') {
        $apptClinic = " AND COALESCE(cl.name, oa.clinic, 'ไม่ระบุคลินิก') = :key";
        $params[':key'] = $key;
    }
    $slot = (string)($_GET['slot'] ?? '');
    if ($slot !== '') {
        if (!preg_match('/^(\d{2}:\d{2}|ไม่ระบุ)$/u', $slot)) {
            http_response_code(400);
            echo json_encode(['error' => 'ช่วงเวลานัดไม่ถูกต้อง'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        // ปัดเวลานัดแรกลงเป็นช่วง 30 นาที เหมือน appt_times ใน api/opd_visit_today.php
        $apptSlot = "WHERE COALESCE(TO_CHAR(sort_time - (EXTRACT(MINUTE FROM sort_time)::int % 30) * INTERVAL '1 minute', 'HH24:MI'), 'ไม่ระบุ') = :slot";
        $params[':slot'] = $slot;
    }
}

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
                TO_CHAR(o.vstdate, 'YYYY-MM-DD')             AS vstdate,
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
                COALESCE(o.an, '') <> ''                     AS is_admit,
                (SELECT COALESCE(w.name, i.ward) FROM ipt i LEFT JOIN ward w ON w.ward = i.ward
                  WHERE i.an = o.an LIMIT 1)                 AS admit_ward,
                (SELECT STRING_AGG(DISTINCT COALESCE(h.name, r.refer_hospcode), ', ')
                   FROM referout r LEFT JOIN hospcode h ON h.hospcode = r.refer_hospcode
                  WHERE r.vn = o.vn)                         AS refer_to
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
            WHERE {$visitDate}
              AND o.main_dep IN (" . opd_dep_sql($scope) . ")
              AND {$VISIT_FILTERS[$type]}
            ORDER BY o.vstdate, o.vsttime, o.hn" . ($isRange ? ' LIMIT ' . (OPD_LIST_MAX + 1) : '');
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
            WHERE ({$APPT_FILTERS[$type]}){$apptClinic}
            ORDER BY COALESCE(NULLIF(oa.visit_vn, ''), 'hn:' || oa.hn), oa.nexttime NULLS LAST
        ) a
        {$apptSlot}
        ORDER BY sort_time NULLS LAST, hn";
        $kind = 'appt';
    }

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($isRange && count($rows) > OPD_LIST_MAX) {
        http_response_code(413);
        echo json_encode(['error' => 'รายชื่อเกิน ' . number_format(OPD_LIST_MAX) . ' ครั้ง แสดงไม่ได้ในครั้งเดียว'
            . ' กรุณาคลิกแท่งรายเดือนในกราฟด้านบนเพื่อดูทีละเดือน'], JSON_UNESCAPED_UNICODE);
        exit;
    }

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
