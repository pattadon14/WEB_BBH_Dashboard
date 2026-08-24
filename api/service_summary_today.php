<?php

header('Content-Type: application/json; charset=utf-8');

require_once '../config/database.php';

try {

    /*
    =========================
    ห้องส่องกล้อง
    =========================
    */

    $sql_scope = "
        SELECT COUNT(DISTINCT hn) AS scope_total
        FROM ovst
        WHERE vstdate = CURRENT_DATE
        AND cur_dep = '121'
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

    $sql_or = "
        SELECT 
            COUNT(
                CASE
                    WHEN operation_date = CURRENT_DATE
                    THEN hn
                END
            ) AS or_total

        FROM operation_list

        WHERE operation_date BETWEEN
            DATE_TRUNC('month', CURRENT_DATE)
            AND CURRENT_DATE
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

    $sql_xray = "
        SELECT 
            COUNT(
                CASE
                    WHEN request_date = CURRENT_DATE
                    AND vn IS NOT NULL
                    AND vn <> ''
                    THEN vn
                END
            ) AS xray_total

        FROM xray_report

        WHERE request_date BETWEEN
            DATE_TRUNC('month', CURRENT_DATE)
            AND CURRENT_DATE
    ";

    $stmt =
        $conn->query($sql_xray);

    $xray_total =
        $stmt->fetch(PDO::FETCH_ASSOC)['xray_total'];
        
    /*
    =========================
    ไตเทียม
    =========================
    */

    $sql_observe = "
        SELECT
            COUNT(DISTINCT hn) AS observe_total

        FROM ovst

        WHERE vstdate = CURRENT_DATE
        AND cur_dep = '119'
    ";

    $stmt =
        $conn->query($sql_observe);

    $observe_total =
        $stmt->fetch(PDO::FETCH_ASSOC)['observe_total'];

    /*
    =========================
    LR
    =========================
    */

    $sql_lr = "
        SELECT
            COUNT(DISTINCT hn) AS lr_total

        FROM ovst

        WHERE vstdate = CURRENT_DATE
        AND cur_dep = '004'
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

    $sql_wound = "SELECT COUNT(DISTINCT vn) AS wound_total
        FROM ovst
        WHERE vstdate = CURRENT_DATE
        AND (
            main_dep = '078'
            OR cur_dep = '047'
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

    $sql_us = "SELECT COUNT(DISTINCT vn) AS us_total
        FROM ovst
        WHERE vstdate = CURRENT_DATE
        AND (
            main_dep IN ('080','240')
            OR cur_dep IN ('080','240')
        );
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