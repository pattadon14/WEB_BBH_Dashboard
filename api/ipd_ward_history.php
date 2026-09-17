<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');
require_once '../config/database.php';

try {
    $ward = trim((string)($_GET['ward'] ?? ''));
    $fiscalYear = (int)($_GET['fiscal_year'] ?? 0);

    if ($ward === '' || !ctype_digit($ward)) {
        http_response_code(400);
        echo json_encode(['error' => 'ไม่พบรหัส Ward ที่ถูกต้อง'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $currentThaiYear = (int)date('Y') + 543;
    if ($fiscalYear < 2500 || $fiscalYear > 2700) {
        $fiscalYear = (int)date('n') >= 10 ? $currentThaiYear + 1 : $currentThaiYear;
    }

    /* ปีงบประมาณไทย 2569 = 1 ต.ค. 2568 ถึง 30 ก.ย. 2569 */
    $startYear = $fiscalYear - 544;
    $endYear = $startYear + 1;

    $sql = "WITH params AS (
                SELECT :ward AS ward_code,
                       :start_year::int AS start_year,
                       :end_year::int AS end_year
            ),
            months AS (
                SELECT generate_series(
                    make_date(p.start_year, 10, 1),
                    make_date(p.end_year, 9, 1),
                    interval '1 month'
                )::date AS month_start
                FROM params p
            ),
            ward_info AS (
                SELECT w.ward, w.name AS ward_name, COALESCE(w.bedcount, 0) AS bedcount
                FROM ward w
                INNER JOIN params p ON w.ward::text = p.ward_code
                LIMIT 1
            ),
            monthly_admit AS (
                SELECT date_trunc('month', i.regdate)::date AS month_start,
                       COUNT(i.an) AS admit_count
                FROM ipt i
                INNER JOIN params p ON i.ward::text = p.ward_code
                WHERE i.regdate >= make_date(p.start_year, 10, 1)
                  AND i.regdate < make_date(p.end_year, 10, 1)
                GROUP BY 1
            ),
            monthly_patient_days AS (
                SELECT m.month_start,
                       SUM(
                           GREATEST(
                               0,
                               LEAST(
                                   COALESCE(i.dchdate::date, (m.month_start + INTERVAL '1 month')::date),
                                   (m.month_start + INTERVAL '1 month')::date
                               ) - GREATEST(i.regdate::date, m.month_start)
                           )
                       ) AS patient_days
                FROM months m
                INNER JOIN params p ON TRUE
                INNER JOIN ipt i
                    ON i.ward::text = p.ward_code
                   AND i.regdate::date < (m.month_start + INTERVAL '1 month')::date
                   AND (i.dchdate IS NULL OR i.dchdate::date > m.month_start)
                GROUP BY m.month_start
            )
            SELECT
                TO_CHAR(m.month_start, 'MM/YYYY') AS month,
                CASE
                    WHEN EXTRACT(MONTH FROM m.month_start) >= 10
                    THEN EXTRACT(YEAR FROM m.month_start)::int + 544
                    ELSE EXTRACT(YEAR FROM m.month_start)::int + 543
                END AS fiscal_year,
                CASE EXTRACT(MONTH FROM m.month_start)::int
                    WHEN 10 THEN 'ต.ค.' WHEN 11 THEN 'พ.ย.' WHEN 12 THEN 'ธ.ค.'
                    WHEN 1 THEN 'ม.ค.' WHEN 2 THEN 'ก.พ.' WHEN 3 THEN 'มี.ค.'
                    WHEN 4 THEN 'เม.ย.' WHEN 5 THEN 'พ.ค.' WHEN 6 THEN 'มิ.ย.'
                    WHEN 7 THEN 'ก.ค.' WHEN 8 THEN 'ส.ค.' WHEN 9 THEN 'ก.ย.'
                END AS month_name,
                wi.ward,
                wi.ward_name,
                wi.bedcount,
                COALESCE(ma.admit_count, 0) AS admit_count,
                COALESCE(mpd.patient_days, 0) AS patient_days,
                ROUND(
                    (COALESCE(mpd.patient_days, 0) * 100.0) /
                    NULLIF(wi.bedcount * ((m.month_start + INTERVAL '1 month')::date - m.month_start), 0),
                    2
                ) AS occupancy_rate
            FROM months m
            CROSS JOIN ward_info wi
            LEFT JOIN monthly_admit ma ON ma.month_start = m.month_start
            LEFT JOIN monthly_patient_days mpd ON mpd.month_start = m.month_start
            ORDER BY m.month_start";

    $stmt = $conn->prepare($sql);
    $stmt->execute([
        'ward' => $ward,
        'start_year' => $startYear,
        'end_year' => $endYear
    ]);

    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$data) {
        http_response_code(404);
        echo json_encode(['error' => 'ไม่พบข้อมูล Ward สำหรับปีงบประมาณที่เลือก'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode([
        'ward' => $ward,
        'fiscal_year' => $fiscalYear,
        'ward_name' => $data[0]['ward_name'] ?? '',
        'bedcount' => isset($data[0]['bedcount']) ? (int)$data[0]['bedcount'] : 0,
        'data' => $data
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'เกิดข้อผิดพลาดในการโหลดสถิติย้อนหลัง',
        'detail' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
