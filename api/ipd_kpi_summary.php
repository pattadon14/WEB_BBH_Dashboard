<?php

header('Content-Type: application/json; charset=utf-8');

require_once '../config/database.php';
require_once '../config/period.php';
require_once '../config/ward.php';

try {

    // ช่วงเวลา: ?d1&d2 หรือ ?year (config/period.php) — SQL ด้านล่างใช้ end แบบไม่รวม จึง +1 วัน
    $p = bbh_period();
    $startDate = $p['start'];
    $endDate   = date('Y-m-d', strtotime($p['end'] . ' +1 day'));

    /*
    ============================================
    IPD KPI SUMMARY

    Current Inpatients
    = ผู้ป่วยที่ยัง Admit อยู่ ณ ปัจจุบัน (เฉพาะ valid_wards ให้ตรงกับ "Admit อยู่" หน้าหลัก)

    Occupancy Rate
    = อัตราครองเตียงเฉลี่ยของช่วงที่เลือก (ปีงบ หรือ d1–d2) (วิธีเดียวกับ bed_occupancy_trend.php)

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
                " . ward_bedcount_sql() . " AS bedcount
            FROM ward
            WHERE ward_active = 'Y'
              AND ward NOT IN ('10', '11', '05', '04', '23')
        ),

        current_ipd AS (
            SELECT
                COUNT(DISTINCT i.an) AS current_inpatients
            FROM ipt i
            WHERE i.dchdate IS NULL
              AND i.ward IN (SELECT ward FROM valid_wards)
        ),

        -- วันนอนจริงในช่วงปีงบ (นับคืน: วันจำหน่ายไม่นับ, ยังไม่จำหน่ายนับถึงวันนี้) ตัดช่วงที่อยู่นอกปีงบออก
        occupancy_period AS (
            SELECT
                start_date,
                LEAST(end_date, CURRENT_DATE + 1) AS until_date
            FROM params
        ),

        patient_days AS (
            SELECT
                COALESCE(SUM(GREATEST(0,
                    LEAST(COALESCE(i.dchdate, CURRENT_DATE + 1), op.until_date)
                    - GREATEST(i.regdate, op.start_date)
                )), 0) AS patient_days,
                MAX(op.until_date - op.start_date) AS period_days
            FROM occupancy_period op
            LEFT JOIN ipt i
                ON i.regdate < op.until_date
               AND COALESCE(i.dchdate, CURRENT_DATE + 1) > op.start_date
               AND i.ward IN (SELECT ward FROM valid_wards)
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

            -- อัตราครองเตียงเฉลี่ยปีงบ = วันนอนรวม / (เตียง x จำนวนวันที่ผ่านไปของปีงบ)
            ROUND(
                (
                    pd.patient_days * 100.0
                    / NULLIF(b.total_beds * pd.period_days, 0)
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
        CROSS JOIN patient_days pd
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
