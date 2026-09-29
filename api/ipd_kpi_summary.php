<?php

header('Content-Type: application/json; charset=utf-8');

require_once '../config/database.php';

try {

    $year = isset($_GET['year'])
        ? (int)$_GET['year']
        : date('Y') + 543;

    if ($year < 2565 || $year > 2600) {
        throw new Exception('ปีงบประมาณไม่ถูกต้อง');
    }

    $startDate = ($year - 544) . '-10-01';
    $endDate   = ($year - 543) . '-10-01';

    /*
    ============================================
    IPD KPI SUMMARY

    Current Inpatients
    = ผู้ป่วยที่ยัง Admit อยู่ ณ ปัจจุบัน

    Admissions / Discharges / LOS / Readmission
    = คำนวณตามปีงบประมาณที่เลือก

    Total Beds
    = Ward ที่ใช้งาน และใช้ชุดเดียวกับ
      bed_occupancy_trend.php
    ============================================
    */

    $sql = "
        WITH params AS (
            SELECT
                CAST(:startDate AS date) AS start_date,
                CAST(:endDate AS date) AS end_date
        ),

        valid_wards AS (
            SELECT
                ward,
                COALESCE(bedcount, 0) AS bedcount
            FROM ward
            WHERE ward_active = 'Y'
              AND ward NOT IN ('10', '11', '05', '04', '23')
        ),

        current_ipd AS (
            SELECT
                COUNT(DISTINCT i.an) AS current_inpatients
            FROM ipt i
            WHERE i.dchdate IS NULL
        ),

        total_beds AS (
            SELECT
                COALESCE(SUM(bedcount), 0) AS total_beds
            FROM valid_wards
        ),

        admissions AS (
            SELECT
                COUNT(DISTINCT i.an) AS admissions
            FROM ipt i
            CROSS JOIN params p
            WHERE i.regdate >= p.start_date
              AND i.regdate < p.end_date
        ),

        discharges AS (
            SELECT
                COUNT(DISTINCT i.an) AS discharges
            FROM ipt i
            CROSS JOIN params p
            WHERE i.dchdate >= p.start_date
              AND i.dchdate < p.end_date
        ),

        avg_los AS (
            SELECT
                ROUND(
                    AVG(i.admdate)::numeric,
                    1
                ) AS avg_los
            FROM an_stat i
            CROSS JOIN params p
            WHERE i.dchdate >= p.start_date
              AND i.dchdate < p.end_date
        ),

        readmission_data AS (
            SELECT DISTINCT
                curr.an

            FROM ipt curr

            INNER JOIN ipt prev
                ON prev.hn = curr.hn
               AND prev.an <> curr.an
               AND prev.dchdate IS NOT NULL
               AND curr.regdate >= prev.dchdate
               AND curr.regdate <= prev.dchdate + INTERVAL '30 days'

            CROSS JOIN params p

            WHERE curr.regdate >= p.start_date
              AND curr.regdate < p.end_date
        ),

        readmission AS (
            SELECT
                COUNT(*) AS readmission_cases
            FROM readmission_data
        )

        SELECT

            c.current_inpatients,

            a.admissions,

            d.discharges,

            b.total_beds,

            ROUND(
                (
                    c.current_inpatients * 100.0
                    / NULLIF(b.total_beds, 0)
                )::numeric,
                1
            ) AS occupancy_rate,

            l.avg_los,

            r.readmission_cases,

            ROUND(
                (
                    r.readmission_cases * 100.0
                    / NULLIF(a.admissions, 0)
                )::numeric,
                1
            ) AS readmission_rate

        FROM current_ipd c
        CROSS JOIN admissions a
        CROSS JOIN discharges d
        CROSS JOIN total_beds b
        CROSS JOIN avg_los l
        CROSS JOIN readmission r
    ";

    $stmt = $conn->prepare($sql);

    $stmt->execute([
        ':startDate' => $startDate,
        ':endDate'   => $endDate
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        throw new Exception('ไม่พบข้อมูล IPD KPI');
    }

    $result = [
        'fiscal_year' => $year,
        'start_date' => $startDate,
        'end_date' => date('Y-m-d', strtotime($endDate . ' -1 day')),

        'current_inpatients' => (int)$row['current_inpatients'],
        'admissions' => (int)$row['admissions'],
        'discharges' => (int)$row['discharges'],
        'total_beds' => (int)$row['total_beds'],

        'occupancy_rate' => $row['occupancy_rate'] !== null
            ? (float)$row['occupancy_rate']
            : 0,

        'avg_los' => $row['avg_los'] !== null
            ? (float)$row['avg_los']
            : 0,

        'readmission_cases' => (int)$row['readmission_cases'],

        'readmission_rate' => $row['readmission_rate'] !== null
            ? (float)$row['readmission_rate']
            : 0
    ];

    echo json_encode(
        $result,
        JSON_UNESCAPED_UNICODE
    );

} catch (Throwable $e) {

    http_response_code(500);

    echo json_encode([
        'error' => 'ไม่สามารถโหลดข้อมูล IPD KPI ได้',
        'detail' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
