<?php

require_once '../config/database.php';

header('Content-Type: application/json');

try {

    $sql = "
    SELECT
        COUNT(DISTINCT hn) AS month_patient,

        COUNT(
            CASE
                WHEN vn IS NOT NULL
                AND vn <> ''
                THEN vn
            END
        ) AS month_visit,

        COUNT(
            CASE
                WHEN request_date = CURRENT_DATE
                AND vn IS NOT NULL
                AND vn <> ''
                THEN vn
            END
        ) AS today

    FROM xray_report

    WHERE request_date BETWEEN
        DATE_TRUNC('month', CURRENT_DATE)
        AND CURRENT_DATE
    ";

    $stmt = $conn->query($sql);

    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode($result);

} catch (PDOException $e) {

    echo json_encode([
        'error' => $e->getMessage()
    ]);

}