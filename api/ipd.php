<?php

require_once '../config/database.php';

header('Content-Type: application/json');

try {

    $sql = "
    SELECT
        COUNT(DISTINCT hn) AS month_patient,
        COUNT(DISTINCT an) AS month_visit,
        COUNT(DISTINCT CASE
            WHEN regdate = CURRENT_DATE
            THEN an
        END) AS today
    FROM ipt
    WHERE regdate BETWEEN
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