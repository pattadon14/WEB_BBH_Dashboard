<?php

header('Content-Type: application/json; charset=utf-8');

require_once '../config/database.php';

try {

    /*
    =========================
    ห้องส่องกล้อง (แผนก 121) นับคน (HN) ไม่ซ้ำ จาก 3 แหล่ง
    - visit ที่ถูกส่งเข้า 121 วันนี้: ดู opd_dep_queue ด้วย
      เพราะ ovst.cur_dep เปลี่ยนทันทีเมื่อผู้ป่วยย้ายออกจากห้อง
    - ผู้ป่วยในที่มีค่าบริการลงจากแผนก 121
    - ผู้ป่วยในที่ลงหัตถการส่องกล้อง (ICD-9) ซึ่งไม่อยู่ในตารางผ่าตัด
      ผู้ป่วยใน Admit อยู่ก่อนแล้วไม่มีคิวแผนก 121 และ ward 10 เลิกใช้ตั้งแต่ปี 2566
    =========================
    */

    $sql_scope = "
        SELECT COUNT(*) AS scope_total
        FROM (
            SELECT o.hn
            FROM ovst o
            WHERE o.vstdate = CURRENT_DATE
              AND (o.cur_dep = '121'
                   OR EXISTS (SELECT 1 FROM opd_dep_queue q
                              WHERE q.vn = o.vn AND q.depcode = '121'))
            UNION
            SELECT r.hn
            FROM opitemrece r
            WHERE r.rxdate = CURRENT_DATE
              AND r.dep_code = '121'
              AND r.an <> ''
            UNION
            SELECT i.hn
            FROM iptoprt io
            JOIN ipt i ON i.an = io.an
            WHERE io.opdate = CURRENT_DATE
              AND LEFT(io.icd9, 4) IN ('4513', '4516', '4523', '4525', '4542', '4233', '4443')
              AND NOT EXISTS (SELECT 1 FROM operation_list l
                              WHERE l.an = io.an AND l.operation_date = io.opdate)
        ) scope_patients
    ";

    $stmt =
        $conn->query($sql_scope);

    $scope_total =
        $stmt->fetch(PDO::FETCH_ASSOC)['scope_total'];

    /*
    =========================
    แพทย์แผนไทย
    =========================
    */

    $sql_thai = "
        SELECT
            COUNT(
                DISTINCT CASE
                    WHEN service_date = CURRENT_DATE
                    THEN vn
                END
            ) AS thai_total
        FROM health_med_service
        WHERE service_date BETWEEN
            DATE_TRUNC('month', CURRENT_DATE)
            AND CURRENT_DATE
    ";

    $stmt =
        $conn->query($sql_thai);

    $thai_total =
        $stmt->fetch(PDO::FETCH_ASSOC)['thai_total'];

    /*
    =========================
    กายภาพบำบัด
    =========================
    */
    
    $sql_dent = "
        SELECT
            COUNT(
                DISTINCT CASE
                    WHEN vstdate = CURRENT_DATE
                    THEN vn
                END
            ) AS dent_total
        FROM dtmain
        WHERE vstdate BETWEEN
            DATE_TRUNC('month', CURRENT_DATE)
            AND CURRENT_DATE
    ";

    $stmt =
        $conn->query($sql_dent);

    $dent_total =
        $stmt->fetch(PDO::FETCH_ASSOC)['dent_total'];

    /*
    =========================
    กายภาพบำบัด
    =========================
    */

    $sql_pt = "
        SELECT 
            COUNT(
                CASE 
                    WHEN physic_plan_date = CURRENT_DATE
                    AND vn IS NOT NULL
                    AND vn <> ''
                    THEN vn
                END
            ) AS pt_total

        FROM physic_plan

        WHERE physic_plan_date BETWEEN
            DATE_TRUNC('month', CURRENT_DATE)
            AND CURRENT_DATE
    ";

    $stmt =
        $conn->query($sql_pt);

    $pt_total =
        $stmt->fetch(PDO::FETCH_ASSOC)['pt_total'];

    /*
    =========================
    ห้องผ่าตัด
    =========================
    */

    // ไม่นับเคสที่ยกเลิกการผ่าตัด (status_id = 9)
    $sql_or = "
        SELECT COUNT(*) AS or_total
        FROM operation_list
        WHERE operation_date = CURRENT_DATE
          AND status_id IS DISTINCT FROM 9
    ";

    $stmt =
        $conn->query($sql_or);

    $or_total =
        $stmt->fetch(PDO::FETCH_ASSOC)['or_total'];

    /*
    =========================
    X-RAY
    =========================
    */

    // นับ visit/admit ไม่ซ้ำ เฉพาะเอกซเรย์ทั่วไปและ Portable (กลุ่ม 1, 4)
    // xray_report เก็บ EKG, CT, อัลตราซาวน์ และผลส่องกล้องไว้ด้วย
    $sql_xray = "
        SELECT COUNT(DISTINCT COALESCE(NULLIF(r.vn, ''), r.an)) AS xray_total
        FROM xray_report r
        JOIN xray_items i ON i.xray_items_code = r.xray_items_code
        WHERE r.request_date = CURRENT_DATE
          AND i.xray_items_group IN (1, 4)
    ";

    $stmt =
        $conn->query($sql_xray);

    $xray_total =
        $stmt->fetch(PDO::FETCH_ASSOC)['xray_total'];
        
    /*
    =========================
    Observe ER
    นับคน (HN) ที่ผ่านแผนก 119 วันนี้ รวมที่ย้ายออกแล้ว และที่ Admit ward 05 วันนี้
    =========================
    */

    $sql_observe = "
        SELECT COUNT(*) AS observe_total
        FROM (
            SELECT o.hn
            FROM ovst o
            WHERE o.vstdate = CURRENT_DATE
              AND (o.main_dep = '119' OR o.cur_dep = '119'
                   OR EXISTS (SELECT 1 FROM opd_dep_queue q
                              WHERE q.vn = o.vn AND q.depcode = '119'))
            UNION
            SELECT i.hn
            FROM ipt i
            WHERE i.regdate = CURRENT_DATE
              AND i.ward = '05'
        ) observe_patients
    ";

    $stmt =
        $conn->query($sql_observe);

    $observe_total =
        $stmt->fetch(PDO::FETCH_ASSOC)['observe_total'];

    /*
    =========================
    LR
    นับคน (HN) ที่ผ่านแผนก 004 วันนี้ รวมที่ย้ายออกแล้ว และที่ Admit ward 04 วันนี้
    =========================
    */

    $sql_lr = "
        SELECT COUNT(*) AS lr_total
        FROM (
            SELECT o.hn
            FROM ovst o
            WHERE o.vstdate = CURRENT_DATE
              AND (o.main_dep = '004' OR o.cur_dep = '004'
                   OR EXISTS (SELECT 1 FROM opd_dep_queue q
                              WHERE q.vn = o.vn AND q.depcode = '004'))
            UNION
            SELECT i.hn
            FROM ipt i
            WHERE i.regdate = CURRENT_DATE
              AND i.ward = '04'
        ) lr_patients
    ";

    $stmt =
        $conn->query($sql_lr);

    $lr_total =
        $stmt->fetch(PDO::FETCH_ASSOC)['lr_total'];

    /*
    =========================
    ไตเทียม
    =========================
    */

    $sql_hemo = "
        SELECT
            COUNT(*) AS hemo_total

        FROM ovst

        WHERE vstdate = CURRENT_DATE
        AND main_dep = '069'
    ";

    $stmt =
        $conn->query($sql_hemo);

    $hemo_total =
        $stmt->fetch(PDO::FETCH_ASSOC)['hemo_total'];
    
    /*
    =========================
    ห้องทำแผล
    =========================
    */

    // รวมคนที่ถูกส่งต่อมาจากจุดอื่น ซึ่ง main_dep ไม่ใช่ 078 (ดูจาก opd_dep_queue)
    $sql_wound = "SELECT COUNT(DISTINCT o.vn) AS wound_total
        FROM ovst o
        WHERE o.vstdate = CURRENT_DATE
        AND (
            o.main_dep IN ('078', '047')
            OR o.cur_dep IN ('078', '047')
            OR EXISTS (SELECT 1 FROM opd_dep_queue q
                       WHERE q.vn = o.vn AND q.depcode IN ('078', '047'))
        )
    ";

    $stmt =
        $conn->query($sql_wound );

    $wound_total =
        $stmt->fetch(PDO::FETCH_ASSOC)['wound_total'];

    /*
    =========================
    อัลตราซาวด์
    =========================
    */

    // คำขออัลตราซาวน์อยู่ในระบบ X-ray ผู้ป่วยส่วนใหญ่ไม่ผ่านแผนก 080/240
    $sql_us = "SELECT COUNT(DISTINCT COALESCE(NULLIF(r.vn, ''), r.an)) AS us_total
        FROM xray_report r
        JOIN xray_items i ON i.xray_items_code = r.xray_items_code
        WHERE r.request_date = CURRENT_DATE
          AND i.xray_items_group = 2
          AND i.xray_items_name ILIKE '%ultrasound%'
    ";

    $stmt =
        $conn->query($sql_us );

    $us_total =
        $stmt->fetch(PDO::FETCH_ASSOC)['us_total'];


    /*
    =========================
    CT Scan
    =========================
    */

    $sql_ct = "
        SELECT
            COUNT(DISTINCT vn) AS ct_total
        FROM xray_head
        WHERE order_date = CURRENT_DATE
        AND xray_list ILIKE '%CT%'
    ";

    $stmt =
        $conn->query($sql_ct);

    $ct_total =
        $stmt->fetch(PDO::FETCH_ASSOC)['ct_total'];

    /*
    =========================
    Lab
    =========================
    */

    $sql_lab = "SELECT COUNT(DISTINCT vn) AS lab_total
        FROM lab_head
        WHERE order_date = CURRENT_DATE;
    ";

    $stmt =
        $conn->query($sql_lab );

    $lab_total =
        $stmt->fetch(PDO::FETCH_ASSOC)['lab_total'];
        
    /*
    =========================
    RETURN JSON
    =========================
    */

    echo json_encode([

        'scope_total' =>
            (int)$scope_total,
        'thai_total' =>
            (int)$thai_total,
        'dent_total' =>
            (int)$dent_total,
        'pt_total' =>
            (int)$pt_total,
        'or_total' =>
            (int)$or_total,
        'xray_total' =>
            (int)$xray_total,
        'observe_total' =>
            (int)$observe_total,
        'lr_total' =>
            (int)$lr_total,
        'hemo_total' =>
            (int)$hemo_total,
        'wound_total' =>
            (int)$wound_total,
        'us_total' =>
            (int)$us_total,
        'lab_total' =>
            (int)$lab_total,
        'ct_total' =>
            (int)$ct_total

    ], JSON_UNESCAPED_UNICODE);

} catch(Exception $e) {

    echo json_encode([

        'error' =>
            $e->getMessage()

    ]);

}