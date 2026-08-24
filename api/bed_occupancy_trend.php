<?php

header('Content-Type: application/json; charset=utf-8');

require_once '../config/database.php';

try {

    $year =
        isset($_GET['year'])
        ? (int)$_GET['year']
        : date('Y') + 543;

    /*
    ==========================
    ปีงบประมาณ
    ==========================
    */

    $startDate =
        ($year - 544) . '-10-01';

    $endDate =
        ($year - 543) . '-09-30';

    /*
    ==========================
    จำนวนเตียงทั้งหมด
    ==========================
    */

    $bedSql = "SELECT SUM(bedcount)
    AS total_bed 
            FROM
                ward 
            WHERE
                ward_active = 'Y' 
                AND ward NOT IN ( '10', '11', '05', '04', '23' )";

    $bedStmt =
        $conn->query($bedSql);
    
    $totalBed = (int)$bedStmt->fetch(PDO::FETCH_ASSOC)['total_bed'];

    /*
    ==========================
    Occupancy Rate
    ==========================
    */

    $sql = "

        SELECT

            TO_CHAR(
                i.dchdate,
                'YYYY-MM'
            ) AS month_key,

            EXTRACT(
                MONTH FROM i.dchdate
            ) AS month,

            SUM(i.admdate) AS patient_day,

            ROUND(

                (
                    SUM(i.admdate) * 100.0
                )

                /

                (

                    :totalBed *

                    EXTRACT(
                        DAY FROM (
                            DATE_TRUNC(
                                'month',
                                i.dchdate
                            )
                            +
                            INTERVAL '1 month'
                            -
                            INTERVAL '1 day'
                        )
                    )

                )

            ,2)

            AS occupancy_rate

        FROM an_stat i

        LEFT JOIN ward w
            ON w.ward = i.ward

        WHERE

            i.dchdate BETWEEN
            :startDate
            AND
            :endDate

        GROUP BY

            month_key,
            month,
            DATE_TRUNC(
                'month',
                i.dchdate
            )

        ORDER BY
            month_key

    ";

    $stmt =
        $conn->prepare($sql);

    $stmt->execute([

        ':startDate' =>
            $startDate,

        ':endDate' =>
            $endDate,

        ':totalBed' =>
            $totalBed

    ]);

    $rows =
        $stmt->fetchAll(PDO::FETCH_ASSOC);

    /*
    ==========================
    จัดเรียงตามปีงบประมาณ
    ==========================
    */

    $months = [
        10,11,12,
        1,2,3,4,5,6,7,8,9
    ];

    $monthMap = [];

    foreach($rows as $row){

        $monthMap[
            (int)$row['month']
        ] = [

            'patient_day' =>
                (int)$row['patient_day'],

            'occupancy_rate' =>
                (float)$row['occupancy_rate']

        ];

    }

    $data = [];

    foreach($months as $month){

        $data[] = [

            'month' =>
                $month,

            'patient_day' =>
                $monthMap[$month]['patient_day']
                ?? 0,

            'occupancy_rate' =>
                $monthMap[$month]['occupancy_rate']
                ?? 0

        ];

    }

    echo json_encode([

        'total_bed' =>
            $totalBed,

        'data' =>
            $data

    ], JSON_UNESCAPED_UNICODE);

} catch(Exception $e){

    echo json_encode([

        'error' =>
            $e->getMessage()

    ]);

}