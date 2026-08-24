<?php

header('Content-Type: application/json; charset=utf-8');

require_once '../config/database.php';

try {

    $year = isset($_GET['year'])
        ? (int)$_GET['year']
        : date('Y') + 543;

    $startDate = ($year - 544) . '-10-01';
    $endDate   = ($year - 543) . '-09-30';

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

            i.regdate BETWEEN
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