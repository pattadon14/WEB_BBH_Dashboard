<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

require_once '../config/database.php';

try {

    $ward = trim((string)($_GET['ward'] ?? ''));

    if ($ward === '' || !ctype_digit($ward)) {
        http_response_code(400);
        echo json_encode([
            'error' => 'ไม่พบรหัส Ward ที่ถูกต้อง'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $sql = "SELECT
                i.an,
                i.hn,
                concat_ws(' ', p.pname, p.fname, p.lname) AS patient_name,
                i.regdate,
                w.name AS ward_name
            FROM ipt i
            LEFT JOIN patient p ON p.hn = i.hn
            LEFT JOIN ward w ON w.ward = i.ward
            WHERE i.ward = :ward
              AND i.dchdate IS NULL
            ORDER BY i.an";

    $stmt = $conn->prepare($sql);
    $stmt->execute(['ward' => $ward]);

    $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'ward' => $ward,
        'ward_name' => $patients[0]['ward_name'] ?? '',
        'total' => count($patients),
        'patients' => $patients
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Throwable $e) {

    http_response_code(500);

    echo json_encode([
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);

}
