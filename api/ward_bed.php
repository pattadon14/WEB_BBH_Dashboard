<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

require_once '../config/database.php';
require_once '../config/ward.php';

try {

    $beds = ward_bedcount_sql('w');
    $sql = "SELECT
	w.ward,
	w.NAME,
	$beds AS bedcount,
	COUNT ( i.an ) AS admitnow,
	( $beds - COUNT ( i.an ) ) AS available_bed,
	ROUND( ( COUNT ( i.an ) * 100.0 / NULLIF ( $beds, 0 ) ), 2 ) AS occupancy_rate 
FROM
	ward w
	LEFT OUTER JOIN ipt i ON i.ward = w.ward 
	AND i.dchdate IS NULL 
WHERE
	w.ward_active = 'Y' 
	AND w.ward NOT IN ('10','11','05','04')
GROUP BY
	w.ward,
	w.NAME,
	w.bedcount 
ORDER BY
	( COUNT ( i.an ) * 1.0 / NULLIF ( $beds, 0 ) ) ASC
    ";

    $stmt =
        $conn->query($sql);

    $data =
        $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(

        $data,

        JSON_UNESCAPED_UNICODE
        | JSON_PRETTY_PRINT

    );

} catch(Exception $e) {

    echo json_encode([

        'error' =>
            $e->getMessage()

    ]);

}