<?php

header('Content-Type: application/json; charset=utf-8');

require_once '../config/database.php';

try {

    $year =
        isset($_GET['year'])
        ? (int)$_GET['year']
        : date('Y') + 543 + (date('n') >= 10 ? 1 : 0);

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
    Occupancy Rate รายเดือน
    = วันนอนจริงในเดือน / (เตียงทั้งหมด x จำนวนวันในเดือน)
    - วันนอน: นับคืนที่อยู่ในเดือนนั้นจริง (วันจำหน่ายไม่นับ, ยังไม่จำหน่ายนับถึงวันนี้)
      เดิมใช้ an_stat.admdate ของคนที่จำหน่ายในเดือน ทำให้เดือนที่ยังไม่จบต่ำผิดปกติ และนับวันนอนข้ามเดือน
    - เดือนปัจจุบันหารด้วยจำนวนวันที่ผ่านไปแล้วเท่านั้น
    - ward ชุดเดียวกับจำนวนเตียง (ไม่นับ LR, Observe ER, ส่องกล้อง, ผ่าตัด, Home Ward)
    ==========================
    */

    $sql = "
        WITH months AS (
            SELECT
                ms::date AS month_start,
                LEAST((ms + INTERVAL '1 month')::date, CURRENT_DATE + 1) AS month_until
            FROM generate_series(CAST(:startDate AS date), CAST(:endDate AS date), INTERVAL '1 month') AS ms
            WHERE ms::date <= CURRENT_DATE
        )
        SELECT
            EXTRACT(MONTH FROM m.month_start) AS month,
            m.month_until - m.month_start AS days,
            COALESCE(SUM(GREATEST(0,
                LEAST(COALESCE(i.dchdate, CURRENT_DATE + 1), m.month_until)
                - GREATEST(i.regdate, m.month_start)
            )), 0) AS patient_day
        FROM months m
        LEFT JOIN ipt i
            ON i.regdate < m.month_until
           AND COALESCE(i.dchdate, CURRENT_DATE + 1) > m.month_start
           AND i.ward NOT IN ('10', '11', '05', '04', '23')
        GROUP BY m.month_start, m.month_until
        ORDER BY m.month_start
    ";

    $stmt =
        $conn->prepare($sql);

    $stmt->execute([

        ':startDate' =>
            $startDate,

        ':endDate' =>
            $endDate

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
                $totalBed > 0
                    ? round($row['patient_day'] * 100 / ($totalBed * $row['days']), 2)
                    : 0

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