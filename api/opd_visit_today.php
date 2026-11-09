<?php

header('Content-Type: application/json; charset=utf-8');

require_once '../config/database.php';
require_once '../config/opd.php';

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

    $sql_summary = "SELECT
            opd.total                   AS opd_total_today,
            appt.total_appointment      AS appointment_total,
            appt.came                   AS oapp_success,
            appt.not_came               AS miss_today,
            opd.walkin                  AS walkin_total,
            opd.night                   AS before_time,
            opd.morning                 AS worktime,
            opd.evening                 AS after_time
        FROM
            (
                SELECT
                    COUNT(*) AS total_appointment,
                    COUNT(CASE
                        WHEN oa.visit_vn IS NOT NULL
                         AND oa.visit_vn <> ''
                        THEN 1
                    END) AS came,
                    -- ไม่นับนัดที่ยกเลิกแล้ว (oapp_status_id 4)
                    COUNT(CASE
                        WHEN (oa.visit_vn IS NULL OR oa.visit_vn = '')
                         AND oa.oapp_status_id IS DISTINCT FROM 4
                        THEN 1
                    END) AS not_came
                FROM oapp oa
                LEFT JOIN clinic cl ON cl.clinic = oa.clinic
                LEFT JOIN patient p  ON p.hn      = oa.hn
                WHERE oa.nextdate       = :targetDate
                  AND cl.active_status  = 'Y'
                  AND (p.death = 'N' OR p.death IS NULL)
                  AND oa.clinic IN (
                        '004','011','049','074','083','106','111','113','116','118','119','123','129','131',
						'001','014','020','030','076','084','085','087','097','107','114','120','126','128','130','132'
                  )
            ) appt,
            (
                SELECT
                    COUNT(*) AS total,
                    -- Walk-in = visit ที่ไม่ถูกผูกกับนัดใดเลย (นับ visit ชุดเดียวกับยอดรวม)
                    COUNT(CASE
                        WHEN NOT EXISTS (SELECT 1 FROM oapp oa2 WHERE oa2.visit_vn = ovst.vn)
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
                  AND main_dep IN (" . opd_main_dep_sql() . ")
            ) opd
    ";

    $stmt  = $conn->prepare($sql_summary);
    $stmt->execute([':targetDate' => $targetDate]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$result) {
        $result = [
            'opd_total_today'    => 0,
            'appointment_total'  => 0,
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
              AND o.main_dep IN (" . opd_main_dep_sql() . ")
        ) v
    ";

    $stmt = $conn->prepare($sql_status);
    $stmt->execute([':targetDate' => $targetDate]);
    $status = $stmt->fetch(PDO::FETCH_ASSOC);

    /*
    =========================
    RETURN JSON
    =========================
    */

    echo json_encode([
        'opd_total_today'   => (int)$result['opd_total_today'],
        'appoint_today'     => (int)$result['appointment_total'],
        'oapp_success'      => (int)$result['oapp_success'],
        'miss_today'        => (int)$result['miss_today'],
        'walkin'            => (int)$result['walkin_total'],
        'before_time'       => (int)$result['before_time'],
        'worktime'          => (int)$result['worktime'],
        'after_time'        => (int)$result['after_time'],
        'wait_triage'       => (int)$status['wait_triage'],
        'wait_exam'         => (int)$status['wait_exam'],
        'finish_exam'       => (int)$status['finish_exam'],
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {

    echo json_encode([
        'error' => $e->getMessage()
    ]);

}