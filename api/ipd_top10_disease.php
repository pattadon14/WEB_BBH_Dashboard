<?php

header('Content-Type: application/json; charset=utf-8');

require_once '../config/database.php';
require_once '../config/period.php';

try {

    // ช่วงเวลา: ?d1&d2 หรือ ?year (config/period.php)
    $p         = bbh_period();
    $startDate = $p['start'];
    $endDate   = $p['end'];

    $sql = "

        SELECT

            i.pdx,

            d.name AS diag,

            d.tname,

            COUNT(*) AS total_case,

            SUM(
                CASE
                    WHEN i.sex = '1'
                    THEN 1
                    ELSE 0
                END
            ) AS male,

            SUM(
                CASE
                    WHEN i.sex = '2'
                    THEN 1
                    ELSE 0
                END
            ) AS female

        FROM an_stat i

        LEFT JOIN icd101 d
            ON d.code = i.pdx

        WHERE

            -- นับตามวันจำหน่าย: an_stat มีการวินิจฉัยหลัก (pdx) หลังจำหน่ายเท่านั้น
            -- ถ้านับตามวัน admit ช่วงสั้น/ล่าสุดจะได้แค่ไม่กี่ราย (ผู้ป่วยส่วนใหญ่ยังไม่จำหน่าย)
            i.dchdate BETWEEN
            :startDate
            AND
            :endDate

            AND COALESCE(i.pdx,'') <> ''

        GROUP BY

            i.pdx,
            d.name,
            d.tname

        ORDER BY
            COUNT(*) DESC

        LIMIT 10

    ";

    $stmt = $conn->prepare($sql);

    $stmt->execute([

        ':startDate' => $startDate,
        ':endDate'   => $endDate

    ]);

    echo json_encode(

        $stmt->fetchAll(PDO::FETCH_ASSOC),

        JSON_UNESCAPED_UNICODE

    );

} catch(Exception $e){

    echo json_encode([
        'error' => $e->getMessage()
    ]);

}