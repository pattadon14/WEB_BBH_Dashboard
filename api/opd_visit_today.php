<?php

header('Content-Type: application/json; charset=utf-8');

require_once '../config/database.php';

try {

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
            opd.total - appt.came       AS walkin_total,
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
                    COUNT(CASE
                        WHEN oa.visit_vn IS NULL
                          OR oa.visit_vn = ''
                        THEN 1
                    END) AS not_came
                FROM oapp oa
                LEFT JOIN clinic cl ON cl.clinic = oa.clinic
                LEFT JOIN patient p  ON p.hn      = oa.hn
                WHERE oa.nextdate       = CURRENT_DATE
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
                WHERE vstdate = CURRENT_DATE
                  AND main_dep IN (
                        '002','009','010','021','023','029','036','076','080','088','096','108','109',
						'110','111','115','121','125','126','127','128','129','130','131','134','144',
						'162','176','177','191','199','201','207','230','231','232','238','244',
						'204','035','039','056','057','059','060','087','090','093','097','099','118',
						'136','138','139','142','143','173','197','206','237','242','248','249'
                  )
            ) opd
    ";

    $stmt  = $conn->query($sql_summary);
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
    รอซักประวัติ
    ==========================
    */

    $sql_wait_triage = "SELECT COUNT
        ( DISTINCT vn ) AS wait_triage 
    FROM
        opd_qs_slot o1 
    WHERE
        o1.schedule_date = CURRENT_DATE 
        AND o1.opd_queue_slot_type_id IN ( 1, 2, 3 ) 
        AND o1.call_status = 'N'
        AND (
            (
                doctor_code IN ( '106', '125', '004', '083', '118', '049', '011' ) 
                AND COALESCE ( opd_qs_schedule_tmpl_type_id, 2 ) = 2 
                AND slot_register = 'Y' 
            ) 
        OR ( doctor_code IN ( '0798', '0785', '0784', '0735', '0804' ) AND COALESCE ( opd_qs_schedule_tmpl_type_id, 1 ) = 1 AND slot_register = 'Y' ) 
        );
    ";

    $stmt       = $conn->query($sql_wait_triage);
    $wait_row   = $stmt->fetch(PDO::FETCH_ASSOC);
    $wait_triage = $wait_row['wait_triage'] ?? 0;

    /*
    ==========================
    รอตรวจ
    ==========================
    */

    $sql_wait_exam = "SELECT COUNT(*) AS wait_exam
        FROM ovst o2
        WHERE o2.cur_dep IN (
                '009','010','036','058','086',
                '108','109','111','125','126','162'
            )
          AND o2.vstdate  = CURRENT_DATE
          AND o2.cur_dep != '999'
    ";

    $stmt      = $conn->query($sql_wait_exam);
    $exam_row  = $stmt->fetch(PDO::FETCH_ASSOC);
    $wait_exam = $exam_row['wait_exam'] ?? 0;

    /*
    ==========================
    ตรวจเสร็จแล้ว
    ==========================
    */

    $sql_finish_exam = "SELECT COUNT(*) AS finish_exam
        FROM ovst o
        WHERE o.vstdate  = CURRENT_DATE
          AND o.main_dep IN (
                      '002','009','010','021','023','029','036','058',
                      '061','076','086','088','108','109','110','111',
                      '125','126','127','128','129','130','131','134',
                      '144','162','176','177','201','207','224'
                  )
          AND o.cur_dep = '999'
    ";

    $stmt        = $conn->query($sql_finish_exam);
    $finish_row  = $stmt->fetch(PDO::FETCH_ASSOC);
    $finish_exam = $finish_row['finish_exam'] ?? 0;

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
        'wait_triage'       => (int)$wait_triage,
        'wait_exam'         => (int)$wait_exam,
        'finish_exam'       => (int)$finish_exam,
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {

    echo json_encode([
        'error' => $e->getMessage()
    ]);

}