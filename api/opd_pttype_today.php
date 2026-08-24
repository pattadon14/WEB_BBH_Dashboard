<?php

header('Content-Type: application/json; charset=utf-8');

require_once '../config/database.php';

try {

    $sql = "

    SELECT
        ov.pttype,
        pt.name AS pttype_name,
        COUNT(*) AS total_patient

    FROM ovst ov

    LEFT JOIN pttype pt
        ON pt.pttype = ov.pttype

    WHERE ov.vstdate = CURRENT_DATE

    GROUP BY
        ov.pttype,
        pt.name

    ORDER BY total_patient DESC

    ";

    $stmt = $conn->query($sql);

    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE
    );

} catch(Exception $e){

    echo json_encode([
        'error' => $e->getMessage()
    ]);
}