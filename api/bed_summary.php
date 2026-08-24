<?php

header('Content-Type: application/json; charset=utf-8');

require_once '../config/database.php';

try {

    // =========================
    // รับใหม่วันนี้
    // =========================
    $sql = "
        SELECT COUNT(*) AS admittoday
        FROM ipt
        WHERE regdate = CURRENT_DATE
    ";

    $query = $conn->query($sql);
    $admittoday = $query->fetch(PDO::FETCH_ASSOC)['admittoday'];


    // =========================
    // จำนวนเตียงทั้งหมด
    // =========================
    $sql = "SELECT SUM
	( bedcount ) AS bedcount 
        FROM
            ward 
        WHERE
            ward_active = 'Y' 
            AND ward NOT IN ( '10', '11', '05', '04','23' )
    ";

    $query = $conn->query($sql);
    $bedcount = $query->fetch(PDO::FETCH_ASSOC)['bedcount'];


    // =========================
    // Admit อยู่ + เตียงว่าง
    // =========================
    $sql = "SELECT COUNT
	( * ) AS wtotal,
	( SELECT SUM ( bedcount ) FROM ward WHERE ward_active = 'Y' AND ward NOT IN ( '10', '11', '05', '04', '23' ) ) - COUNT ( * ) AS wblank 
    FROM
        ipt 
    WHERE
        dchdate IS NULL
        AND ward NOT IN ( '10', '11', '05', '04', '23' );
    ";

    $query = $conn->query($sql);
    $ward = $query->fetch(PDO::FETCH_ASSOC);


    // =========================
    // จำหน่ายวันนี้
    // =========================
    $sql = "
        SELECT COUNT(*) AS dchtoday
        FROM ipt
        WHERE dchdate = CURRENT_DATE
    ";

    $query = $conn->query($sql);
    $dchtoday = $query->fetch(PDO::FETCH_ASSOC)['dchtoday'];


    // =========================
    // สิทธิการรักษา
    // =========================
    $sql = "
        SELECT

            COUNT(
                CASE
                    WHEN pc.code = 'UC'
                    THEN i.an
                END
            ) AS uc,

            COUNT(
                CASE
                    WHEN pt.pcode IN ('A1','A2')
                    THEN i.an
                END
            ) AS mo,

            COUNT(
                CASE
                    WHEN pc.code IS DISTINCT FROM 'UC'
                    AND pt.pcode NOT IN ('A1','A2')
                    THEN i.an
                END
            ) AS ot

        FROM ipt i

        LEFT JOIN pttype pt
            ON pt.pttype = i.pttype

        LEFT JOIN pcode pc
            ON pc.code = pt.pcode

        WHERE i.dchdate IS NULL
    ";

    $query = $conn->query($sql);
    $right = $query->fetch(PDO::FETCH_ASSOC);


    // =========================
    // ปีงบประมาณ
    // =========================
    if (empty($_POST['submitsend'])) {

        $myearb = date("Y") - 1;
        $myeare = date("Y");

    } else {

        $myearb = $_POST['year'] - 544;
        $myeare = $_POST['year'] - 543;
    }


    // =========================
    // วันที่สิ้นสุดคำนวณ
    // =========================
    $nowdate = date("Y-m-d");

    if ($nowdate > $myeare . "-09-30") {

        $enddate = $myeare . "-09-30";

    } else {

        $enddate = $nowdate;
    }


    // =========================
    // อัตราครองเตียงเฉลี่ย
    // =========================
    $sql = "SELECT
	ROUND(
		( SUM ( i.admdate ) * 100 ) / ( ( SELECT SUM ( bedcount ) FROM ward WHERE ward_active = 'Y' AND ward NOT IN ( '10', '11', '05', '04','23' ) ) * 
        ( ( DATE'$enddate' - DATE'$myearb-10-01' ) + 1 ) ),
		2 
	) AS admsum 
    FROM
        an_stat i 
    WHERE
        i.dchdate BETWEEN DATE'$myearb-10-01' 
        AND DATE'$myeare-09-30'";

    $query = $conn->query($sql);
    $admsum = $query->fetch(PDO::FETCH_ASSOC)['admsum'];


    // =========================
    // ส่งข้อมูลกลับ
    // =========================
    echo json_encode([

        // เตียง
        'bedcount'      => (int)$bedcount,
        'wtotal'        => (int)$ward['wtotal'],
        'wblank'        => (int)$ward['wblank'],

        // วันนี้
        'admittoday'    => (int)$admittoday,
        'dchtoday'      => (int)$dchtoday,

        // สิทธิ
        'uc'            => (int)$right['uc'],
        'mo'            => (int)$right['mo'],
        'ot'            => (int)$right['ot'],

        // occupancy
        'occupancy'     => (float)$admsum,

        // ปีงบประมาณ
        'fiscal_start'  => $myearb . '-10-01',
        'fiscal_end'    => $enddate

    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch(Exception $e) {

    echo json_encode([

        'error' => $e->getMessage()

    ]);

}