<?php

header('Content-Type: application/json; charset=utf-8');

require_once '../config/opd.php';
require_once '../config/cache.php';
$scope = opd_scope(); // ?scope=general (ตรวจโรคทั่วไป) | special (คลินิกพิเศษ)
api_cache_by_date("opd_visit_today_$scope"); // คำตอบเดียวกันทุกจอ รีเฟรชทุก 60 วินาที
require_once '../config/database.php';

try {

    $targetDate = $_GET['date'] ?? date('Y-m-d');

    $dateObj = DateTime::createFromFormat('Y-m-d', $targetDate);
    if (!$dateObj || $dateObj->format('Y-m-d') !== $targetDate) {
        throw new Exception('วันที่ไม่ถูกต้อง');
    }

    /*
    =========================
    OPD Summary Query หลัก
    รวม นัด / Walk-in / ช่วงเวลา
    =========================
    */

    // นัดหมาย: นิยามใน config/opd.php — มาตามนัด = ยอดรวม − Walk-in, ยังไม่มา นับเป็นคน
    $sql_summary = "SELECT
            opd.total                   AS opd_total_today,
            opd.total - opd.walkin      AS oapp_success,
            appt.not_came               AS miss_today,
            opd.walkin                  AS walkin_total,
            opd.night                   AS before_time,
            opd.morning                 AS worktime,
            opd.evening                 AS after_time
        FROM
            (
                SELECT COUNT(DISTINCT oa.hn) AS not_came
                FROM oapp oa
                WHERE " . opd_appt_miss_sql(':targetDate', $scope) . "
            ) appt,
            (
                SELECT
                    COUNT(*) AS total,
                    -- Walk-in = visit ที่ไม่ถูกผูกกับนัดใดเลย (นับ visit ชุดเดียวกับยอดรวม)
                    COUNT(CASE
                        WHEN NOT " . opd_appt_came_sql('ovst.vn') . "
                        THEN 1
                    END) AS walkin,
                    COUNT(CASE
                        WHEN vsttime::TIME >  '20:00:00'
                          OR vsttime::TIME <  '08:00:00'
                        THEN 1
                    END) AS night,
                    COUNT(CASE
                        WHEN vsttime::TIME >= '08:00:00'
                         AND vsttime::TIME <  '16:00:00'
                        THEN 1
                    END) AS morning,
                    COUNT(CASE
                        WHEN vsttime::TIME >= '16:00:00'
                         AND vsttime::TIME <= '20:00:00'
                        THEN 1
                    END) AS evening
                FROM ovst
                WHERE vstdate = :targetDate
                  AND main_dep IN (" . opd_dep_sql($scope) . ")
            ) opd
    ";

    $stmt  = $conn->prepare($sql_summary);
    $stmt->execute([':targetDate' => $targetDate]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$result) {
        $result = [
            'opd_total_today'    => 0,
            'oapp_success'       => 0,
            'miss_today'         => 0,
            'walkin_total'       => 0,
            'before_time'        => 0,
            'worktime'           => 0,
            'after_time'         => 0,
        ];
    }

    /*
    ==========================
    สถานะการตรวจ (นับ visit ในชุด OPD เดียวกับยอดรวม โดยดูจากบันทึกเวลาใน ovst_service_time)
    - ตรวจเสร็จแล้ว : มีบันทึกพบแพทย์ (OPD-DOCTOR) แม้ยังรอยา/การเงินอยู่
    - รอซักประวัติ  : ยังไม่มีบันทึกซักประวัติและพบแพทย์ ยังไม่กลับบ้าน ไม่ Admit
    - รอตรวจ       : ซักประวัติแล้ว ยังไม่พบแพทย์ ยังไม่กลับบ้าน ไม่ Admit
                     และไม่ได้อยู่ที่การเงิน/ห้องยา/Exit (ผ่านจุดตรวจไปแล้ว)
    ==========================
    */

    $sql_status = "SELECT
            COUNT(*) FILTER (WHERE in_service AND NOT doctor AND NOT screen) AS wait_triage,
            COUNT(*) FILTER (WHERE in_service AND NOT doctor AND screen
                AND cur_dep NOT IN (
                    '014','053','083','200','225','234',
                    '025','203','204',
                    '076','131','176','199','206'
                ))                                                           AS wait_exam,
            COUNT(*) FILTER (WHERE doctor)                                   AS finish_exam
        FROM (
            SELECT
                o.cur_dep,
                o.an IS NULL AND o.cur_dep IS DISTINCT FROM '999' AS in_service,
                EXISTS (SELECT 1 FROM ovst_service_time s
                        WHERE s.vn = o.vn AND s.ovst_service_time_type_code = 'OPD-SCREEN') AS screen,
                EXISTS (SELECT 1 FROM ovst_service_time s
                        WHERE s.vn = o.vn AND s.ovst_service_time_type_code = 'OPD-DOCTOR') AS doctor
            FROM ovst o
            WHERE o.vstdate = :targetDate
              AND o.main_dep IN (" . opd_dep_sql($scope) . ")
        ) v
    ";

    $stmt = $conn->prepare($sql_status);
    $stmt->execute([':targetDate' => $targetDate]);
    $status = $stmt->fetch(PDO::FETCH_ASSOC);

    /*
    ==========================
    จำนวนคน / ผู้ป่วยใหม่ / Admit / Refer (visit ชุด OPD เดียวกับยอดรวม)
    - ผู้ป่วยใหม่ : จำนวนคน (HN) ที่ patient.firstday = วันที่มารับบริการ (ลงทะเบียนครั้งแรกวันนั้น)
    - Admit      : visit ที่มี an
    - Refer      : visit ที่มีใบส่งต่อใน referout
    ==========================
    */

    $sql_extra = "SELECT
            COUNT(DISTINCT o.hn)                                         AS total_people,
            COUNT(DISTINCT o.hn) FILTER (WHERE p.firstday = o.vstdate)   AS new_patient,
            COUNT(*) FILTER (WHERE COALESCE(o.an, '') <> '')             AS admit,
            COUNT(*) FILTER (WHERE EXISTS (SELECT 1 FROM referout r WHERE r.vn = o.vn)) AS refer
        FROM ovst o
        LEFT JOIN patient p ON p.hn = o.hn
        WHERE o.vstdate = :targetDate
          AND o.main_dep IN (" . opd_dep_sql($scope) . ")
    ";

    $stmt = $conn->prepare($sql_extra);
    $stmt->execute([':targetDate' => $targetDate]);
    $extra = $stmt->fetch(PDO::FETCH_ASSOC);

    /*
    ==========================
    เทียบกับวันเดียวกันของสัปดาห์ก่อน (จันทร์เทียบจันทร์ — เทียบเมื่อวานไม่ได้เพราะวันหยุดยอดต่างกันมาก)
    ถ้าเลือกวันนี้ นับถึงเวลาเดียวกับตอนนี้ (เวลาของฐานข้อมูล) จึงเทียบกันได้ระหว่างวัน
    ==========================
    */

    $sql_compare = "SELECT COUNT(*) AS prev_week
        FROM ovst
        WHERE vstdate = CAST(:targetDate AS date) - 7
          AND main_dep IN (" . opd_dep_sql($scope) . ")
          AND (CAST(:targetDate AS date) <> CURRENT_DATE OR vsttime <= LOCALTIME)
    ";

    $stmt = $conn->prepare($sql_compare);
    $stmt->execute([':targetDate' => $targetDate]);
    $compare = $stmt->fetch(PDO::FETCH_ASSOC);

    /*
    ==========================
    ผู้ป่วยแยกตามแผนกที่ลงทะเบียน (main_dep) — visit ชุด OPD
    ==========================
    */

    $sql_depts = "SELECT
            COALESCE(k.department, o.main_dep) AS name,
            COUNT(*)                           AS total
        FROM ovst o
        LEFT JOIN kskdepartment k ON k.depcode = o.main_dep
        WHERE o.vstdate = :targetDate
          AND o.main_dep IN (" . opd_dep_sql($scope) . ")
        GROUP BY 1
        ORDER BY 2 DESC
    ";

    $stmt = $conn->prepare($sql_depts);
    $stmt->execute([':targetDate' => $targetDate]);
    $depts = array_map(
        fn($r) => ['name' => $r['name'], 'total' => (int)$r['total']],
        $stmt->fetchAll(PDO::FETCH_ASSOC)
    );

    /*
    ==========================
    สัญชาติ (patient.nationality → ตาราง nationality) — visit ชุด OPD, นับครั้ง + คน
    ไม่มีรหัสหรือรหัสไม่อยู่ในตาราง = "ไม่ระบุ"
    ==========================
    */

    $sql_nations = "SELECT
            COALESCE(n.name, 'ไม่ระบุ') AS name,
            COUNT(*)                    AS total,
            COUNT(DISTINCT o.hn)        AS people
        FROM ovst o
        LEFT JOIN patient p     ON p.hn = o.hn
        LEFT JOIN nationality n ON n.nationality = p.nationality
        WHERE o.vstdate = :targetDate
          AND o.main_dep IN (" . opd_dep_sql($scope) . ")
        GROUP BY 1
        ORDER BY 2 DESC
    ";

    $stmt = $conn->prepare($sql_nations);
    $stmt->execute([':targetDate' => $targetDate]);
    $nations = array_map(
        fn($r) => ['name' => $r['name'], 'total' => (int)$r['total'], 'people' => (int)$r['people']],
        $stmt->fetchAll(PDO::FETCH_ASSOC)
    );

    /*
    ==========================
    การกระจายเวลานัด (oapp.nexttime) — ชุดเดียวกับการ์ดนัดหมาย (มาตามนัด 1 visit + ยังไม่มา 1 คน = 1 ราย
    ใช้เวลานัดแรกถ้ามีหลายนัด) ผลรวม = ตัวเลขบนการ์ด · ปัดลงเป็นช่วงละ 30 นาที (08:01, 08:02 รวมเข้าช่วง 08:00)
    ==========================
    */

    $sql_appt_times = "SELECT
            COALESCE(TO_CHAR(t - (EXTRACT(MINUTE FROM t)::int % 30) * INTERVAL '1 minute', 'HH24:MI'), 'ไม่ระบุ') AS time,
            COUNT(*) AS total
        FROM (
            SELECT MIN(oa.nexttime) AS t
            FROM oapp oa
            JOIN ovst o ON o.vn = oa.visit_vn
            WHERE o.vstdate = :d1
              AND o.main_dep IN (" . opd_dep_sql($scope) . ")
            GROUP BY oa.visit_vn
            UNION ALL
            SELECT MIN(oa.nexttime)
            FROM oapp oa
            WHERE " . opd_appt_miss_sql(':d2', $scope) . "
            GROUP BY oa.hn
        ) x
        GROUP BY 1
        ORDER BY 1
    ";

    $stmt = $conn->prepare($sql_appt_times);
    $stmt->execute([':d1' => $targetDate, ':d2' => $targetDate]);
    $appt_times = array_map(
        fn($r) => ['time' => $r['time'], 'total' => (int)$r['total']],
        $stmt->fetchAll(PDO::FETCH_ASSOC)
    );

    /*
    =========================
    RETURN JSON
    =========================
    */

    echo json_encode([
        'opd_total_today'   => (int)$result['opd_total_today'],
        'appoint_today'     => (int)$result['oapp_success'] + (int)$result['miss_today'],
        'oapp_success'      => (int)$result['oapp_success'],
        'miss_today'        => (int)$result['miss_today'],
        'walkin'            => (int)$result['walkin_total'],
        'before_time'       => (int)$result['before_time'],
        'worktime'          => (int)$result['worktime'],
        'after_time'        => (int)$result['after_time'],
        'wait_triage'       => (int)$status['wait_triage'],
        'wait_exam'         => (int)$status['wait_exam'],
        'finish_exam'       => (int)$status['finish_exam'],
        // ไม่มีบันทึกพบแพทย์และไม่ได้รออยู่ (กลับบ้าน/Admit/อยู่ห้องยา-การเงิน) — ให้ 4 ช่องสถานะรวมได้ยอดรวม
        'no_doctor'         => (int)$result['opd_total_today'] - (int)$status['wait_triage'] - (int)$status['wait_exam'] - (int)$status['finish_exam'],
        'total_people'      => (int)$extra['total_people'],
        'new_patient'       => (int)$extra['new_patient'],
        'admit'             => (int)$extra['admit'],
        'refer'             => (int)$extra['refer'],
        'prev_week'         => (int)$compare['prev_week'],
        'depts'             => $depts,
        'nations'           => $nations,
        'appt_times'        => $appt_times,
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {

    echo json_encode([
        'error' => $e->getMessage()
    ]);

}