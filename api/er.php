<?php

require_once '../config/database.php';

header('Content-Type: application/json');

try {

    $sql = "
    SELECT
        COUNT(DISTINCT o.hn) AS month_patient,
        COUNT(DISTINCT o.vn) AS month_visit,
        COUNT(DISTINCT CASE
            WHEN er.vstdate = CURRENT_DATE
            THEN o.vn
        END) AS today
    FROM er_regist er

    LEFT JOIN ovst o
        ON o.vn = er.vn

    WHERE er.vstdate BETWEEN
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