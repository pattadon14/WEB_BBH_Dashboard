<?php

require_once '../config/database.php';
require_once '../config/opd.php';

header('Content-Type: application/json');

try {

    $sql = "SELECT COUNT
	( DISTINCT hn ) AS month_patient,
	COUNT ( DISTINCT vn ) AS month_visit,
	COUNT ( DISTINCT CASE WHEN vstdate = CURRENT_DATE THEN vn END ) AS today 
    FROM ovst 
    WHERE
	vstdate BETWEEN DATE_TRUNC( 'month', CURRENT_DATE ) AND CURRENT_DATE
	AND main_dep IN (" . opd_main_dep_sql() . ");
    ";

    $stmt = $conn->query($sql);

    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode($result);

} catch (PDOException $e) {

    echo json_encode([
        'error' => $e->getMessage()
    ]);

}