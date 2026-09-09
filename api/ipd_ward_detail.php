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

    /*
     * ดึงข้อมูลผู้ป่วยที่ยัง Admit อยู่ใน Ward
     * - patient.sex       : ใช้แยกเพศเพื่อเลือก Icon
     * - patient.birthday  : ใช้คำนวณอายุ และแยกเด็ก/ผู้ใหญ่
     * - ipt.admdoctor     : แพทย์ผู้รับผิดชอบ/แพทย์เจ้าของไข้
     */
    $sql = "SELECT
                i.an,
                i.hn,
                concat_ws(' ', p.pname, p.fname, p.lname) AS patient_name,
                p.sex,
                p.birthday,
                i.regdate,
                i.bedno,
                COALESCE(
                    NULLIF(TRIM(d.name), ''),
                    NULLIF(TRIM(concat_ws(' ', d.fname, d.lname)), ''),
                    '-'
                ) AS doctor_name,
                w.name AS ward_name
            FROM ipt i
            LEFT JOIN patient p ON p.hn = i.hn
            LEFT JOIN doctor d ON d.code = i.admdoctor
            LEFT JOIN ward w ON w.ward = i.ward
            WHERE i.ward = :ward
              AND i.dchdate IS NULL
            ORDER BY i.bedno NULLS LAST, i.an";

    $stmt = $conn->prepare($sql);
    $stmt->execute(['ward' => $ward]);

    $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

    /* คำนวณอายุ ณ วันที่เปิดหน้า */
    foreach ($patients as &$patient) {
        $patient['age'] = null;

        if (!empty($patient['birthday'])) {
            try {
                $birthDate = new DateTime($patient['birthday']);
                $today = new DateTime('today');
                $patient['age'] = $birthDate->diff($today)->y;
            } catch (Throwable $e) {
                $patient['age'] = null;
            }
        }
    }
    unset($patient);

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
