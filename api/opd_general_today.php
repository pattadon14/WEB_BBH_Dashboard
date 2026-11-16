<?php
/*
 * ข้อมูลเสริมแท็บตรวจโรคทั่วไป/คลินิกพิเศษ (opd_detail.php) — GET api/opd_general_today.php?date=YYYY-MM-DD&scope=general|special
 * visit ชุดเดียวกับ opd_visit_today.php (ovst.main_dep ในชุดของแท็บ config/opd.php) · ไม่มีข้อมูลรายบุคคล
 *  - hours : ต่อชั่วโมง มาลงทะเบียน (ovst.vsttime) / พบแพทย์ (OPD-DOCTOR ครั้งแรกของ visit ในวันนั้น)
 *  - rooms : จุดที่บันทึกพบแพทย์ครั้งแรก (ovst_service_time.depcode) จำนวนที่ตรวจ + รอแพทย์เฉลี่ย
 *            รอแพทย์ = ซักประวัติครั้งแรกก่อนพบแพทย์ → พบแพทย์ นับเฉพาะ 0–360 นาที (กฎเดียวกับ opd_service_kpi.php)
 *  - clinics : นัดรายคลินิก มาตามนัด (visit) / ยังไม่มา (คน) นิยามเดียวกับการ์ดนัดหมาย (config/opd.php)
 */

header('Content-Type: application/json; charset=utf-8');

require_once '../config/opd.php';
require_once '../config/cache.php';
$scope = opd_scope(); // ?scope=general (ตรวจโรคทั่วไป) | special (คลินิกพิเศษ)
api_cache_by_date("opd_general_today_$scope"); // คำตอบเดียวกันทุกจอ รีเฟรชทุก 60 วินาที
require_once '../config/database.php';

$date = $_GET['date'] ?? date('Y-m-d');
$d = DateTime::createFromFormat('Y-m-d', $date);
if (!$d || $d->format('Y-m-d') !== $date) {
    http_response_code(400);
    echo json_encode(['error' => 'วันที่ไม่ถูกต้อง'], JSON_UNESCAPED_UNICODE);
    exit;
}

$deps = opd_dep_sql($scope);
// เวลาพบแพทย์/ซักประวัติครั้งแรกต่อ visit (ซักประวัติต้องก่อนพบแพทย์ — ตัดการซักที่จุด Exit หลังตรวจ)
$visits = "
    WITH v AS (
        SELECT o.vn, o.vsttime
        FROM ovst o
        WHERE o.vstdate = :d AND o.main_dep IN ($deps)
    ), dr AS (
        SELECT DISTINCT ON (t.vn) t.vn, t.service_begin_datetime AS dr_at, t.depcode
        FROM ovst_service_time t
        JOIN v ON v.vn = t.vn
        WHERE t.ovst_service_time_type_code = 'OPD-DOCTOR'
        ORDER BY t.vn, t.service_begin_datetime
    ), sc AS (
        SELECT dr.vn, MIN(t.service_begin_datetime) AS sc_at
        FROM dr
        JOIN ovst_service_time t ON t.vn = dr.vn
        WHERE t.ovst_service_time_type_code = 'OPD-SCREEN' AND t.service_begin_datetime <= dr.dr_at
        GROUP BY dr.vn
    )";

try {
    $stmt = $conn->prepare("$visits
        SELECT h, SUM(arrive)::int AS arrive, SUM(seen)::int AS seen FROM (
            SELECT EXTRACT(HOUR FROM v.vsttime)::int AS h, 1 AS arrive, 0 AS seen FROM v
            UNION ALL
            SELECT EXTRACT(HOUR FROM dr.dr_at)::int, 0, 1 FROM dr WHERE dr.dr_at::date = CAST(:d2 AS date)
        ) x
        GROUP BY h ORDER BY h");
    $stmt->execute([':d' => $date, ':d2' => $date]);
    $hours = array_map(fn($r) => array_map('intval', $r), $stmt->fetchAll(PDO::FETCH_ASSOC));

    $stmt = $conn->prepare("$visits
        SELECT COALESCE(k.department, dr.depcode) AS name,
               COUNT(*)::int AS seen,
               ROUND(AVG(EXTRACT(EPOCH FROM dr.dr_at - sc.sc_at) / 60)
                   FILTER (WHERE EXTRACT(EPOCH FROM dr.dr_at - sc.sc_at) / 60 BETWEEN 0 AND 360))::int AS wait
        FROM dr
        LEFT JOIN sc ON sc.vn = dr.vn
        LEFT JOIN kskdepartment k ON k.depcode = dr.depcode
        GROUP BY 1
        ORDER BY 2 DESC");
    $stmt->execute([':d' => $date]);
    $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $came = "oa.visit_vn IN (SELECT o2.vn FROM ovst o2 WHERE o2.vstdate = :d1 AND o2.main_dep IN ($deps))";
    $miss = opd_appt_miss_sql(':d2', $scope);
    $stmt = $conn->prepare("
        SELECT COALESCE(cl.name, oa.clinic, 'ไม่ระบุคลินิก') AS name,
               COUNT(DISTINCT oa.visit_vn) FILTER (WHERE COALESCE(oa.visit_vn, '') <> '')::int AS came,
               COUNT(DISTINCT oa.hn) FILTER (WHERE COALESCE(oa.visit_vn, '') = '')::int AS miss
        FROM oapp oa
        LEFT JOIN clinic cl ON cl.clinic = oa.clinic
        WHERE ($came) OR ($miss)
        GROUP BY 1
        ORDER BY 3 DESC, 2 DESC");
    $stmt->execute([':d1' => $date, ':d2' => $date]);
    $clinics = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['hours' => $hours, 'rooms' => $rooms, 'clinics' => $clinics], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    error_log('opd_general_today: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'โหลดข้อมูลไม่สำเร็จ'], JSON_UNESCAPED_UNICODE);
}
