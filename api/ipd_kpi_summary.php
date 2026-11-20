<?php

header('Content-Type: application/json; charset=utf-8');

require_once '../config/database.php';
require_once '../config/ward.php';
require_once '../config/ipd.php';

try {

    // ช่วงเวลา: ?d1&d2 หรือ ?year (config/period.php) — SQL ด้านล่างใช้ end แบบไม่รวม จึง +1 วัน
    $p = bbh_period();

    ipd_cached('ipd_kpi_v6', $p, function () use ($conn, $p) {

        $startDate = $p['start'];
        $endDate   = date('Y-m-d', strtotime($p['end'] . ' +1 day'));
        $excluded  = IPD_EXCLUDED_WARDS;
        $bedSql    = ward_bedcount_sql();
        // Readmission ตามเกณฑ์มาตรฐาน (config/ipd.php): ตัวหาร = admit ในช่วงที่ไม่ใช่ Observe ER / นัด admit
        $readmitBase = ipd_readmit_base('i');
        $readmitPrev = ipd_readmit_prev_from('i');

        /*
        ============================================
        IPD KPI SUMMARY
        - ผู้ป่วยในปัจจุบัน = ยัง Admit อยู่ ณ ตอนนี้ (เฉพาะ valid_wards ให้ตรงกับ "Admit อยู่" หน้าหลัก)
        - อัตราครองเตียง = วันนอนจริงในช่วง / (เตียง x จำนวนวันที่ผ่านไปของช่วง) (วิธีเดียวกับ bed_occupancy_trend.php)
        - รับใหม่ / จำหน่าย / LOS / Readmission = ทุก ward ตามช่วงที่เลือก
        - เตียงทั้งหมด = ward ที่ใช้งาน ชุดเดียวกับ bed_occupancy_trend.php
        ============================================
        */
        $sql = "
            WITH params AS (
                SELECT
                    CAST(:startDate AS date) AS start_date,
                    CAST(:endDate AS date) AS end_date
            ),
            valid_wards AS (
                SELECT ward, $bedSql AS bedcount
                FROM ward
                WHERE ward_active = 'Y'
                  AND ward NOT IN ($excluded)
            ),
            current_ipd AS (
                SELECT COUNT(DISTINCT i.an) AS current_inpatients
                FROM ipt i
                WHERE i.dchdate IS NULL
                  AND i.ward IN (SELECT ward FROM valid_wards)
            ),
            occupancy_period AS (
                SELECT start_date, LEAST(end_date, CURRENT_DATE + 1) AS until_date
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
                SELECT COALESCE(SUM(bedcount), 0) AS total_beds FROM valid_wards
            ),
            admissions AS (
                SELECT COUNT(DISTINCT i.an) AS admissions
                FROM ipt i CROSS JOIN params p
                WHERE i.regdate >= p.start_date AND i.regdate < p.end_date
            ),
            discharges AS (
                SELECT COUNT(DISTINCT i.an) AS discharges
                FROM ipt i CROSS JOIN params p
                WHERE i.dchdate >= p.start_date AND i.dchdate < p.end_date
            ),
            avg_los AS (
                SELECT ROUND(AVG(i.admdate)::numeric, 1) AS avg_los
                FROM an_stat i CROSS JOIN params p
                WHERE i.dchdate >= p.start_date AND i.dchdate < p.end_date
            ),
            readmission AS (
                SELECT
                    COUNT(*) AS readmission_base,
                    COUNT(*) FILTER (WHERE EXISTS (SELECT 1 $readmitPrev)) AS readmission_cases
                FROM ipt i CROSS JOIN params p
                WHERE i.regdate >= p.start_date AND i.regdate < p.end_date
                  AND $readmitBase
            )
            SELECT
                c.current_inpatients, a.admissions, d.discharges, b.total_beds,
                pd.patient_days, pd.period_days,
                ROUND((pd.patient_days * 100.0 / NULLIF(b.total_beds * pd.period_days, 0))::numeric, 1) AS occupancy_rate,
                l.avg_los,
                r.readmission_cases, r.readmission_base,
                ROUND((r.readmission_cases * 100.0 / NULLIF(r.readmission_base, 0))::numeric, 1) AS readmission_rate
            FROM current_ipd c
            CROSS JOIN admissions a
            CROSS JOIN discharges d
            CROSS JOIN total_beds b
            CROSS JOIN avg_los l
            CROSS JOIN readmission r
            CROSS JOIN patient_days pd
        ";
        $stmt = $conn->prepare($sql);
        $stmt->execute([':startDate' => $startDate, ':endDate' => $endDate]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        // ประเภทการจำหน่าย (ทุก ward) — รวมทุกแถว = จำหน่าย
        $stmt = $conn->prepare("
            SELECT COALESCE(i.dchtype, '') AS dchtype, COUNT(DISTINCT i.an) AS n
            FROM ipt i
            WHERE i.dchdate >= CAST(:startDate AS date) AND i.dchdate < CAST(:endDate AS date)
            GROUP BY 1
            ORDER BY 1
        ");
        $stmt->execute([':startDate' => $startDate, ':endDate' => $endDate]);
        $dchTypes = [];
        $deaths = 0;
        $refers = 0;
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $t) {
            $code = $t['dchtype'];
            $n = (int)$t['n'];
            $dchTypes[] = ['code' => $code, 'name' => IPD_DCH_NAMES[$code] ?? ($code === '' ? 'ไม่ระบุ' : 'รหัส ' . $code), 'n' => $n];
            if (str_contains(IPD_DEATH_TYPES, "'$code'")) {
                $deaths += $n;
            }
            if ($code === IPD_REFER_TYPE) {
                $refers += $n;
            }
        }

        // รายหอผู้ป่วย (ward ชุดเดียวกับเตียง + Home Ward ท้ายสุด เหมือนหน้าแรก api/index_ward_bed.php)
        // ผู้ป่วยตอนนี้ / ครองเตียงตอนนี้ (= หน้าแรก) / รับใหม่ในช่วง / อัตราครองเตียงเฉลี่ยของช่วง
        $homeWard = IPD_HOME_WARD;
        $stmt = $conn->prepare("
            WITH params AS (
                SELECT CAST(:startDate AS date) AS s,
                       LEAST(CAST(:endDate AS date), CURRENT_DATE + 1) AS u,
                       CAST(:endDate2 AS date) AS e
            )
            SELECT w.ward, w.name, " . ward_bedcount_sql('w') . " AS beds,
                (SELECT COUNT(*) FROM ipt i WHERE i.ward = w.ward AND i.dchdate IS NULL) AS current,
                (SELECT COUNT(*) FROM ipt i, params p
                  WHERE i.ward = w.ward AND i.regdate >= p.s AND i.regdate < p.e) AS admissions,
                (SELECT COALESCE(SUM(GREATEST(0, LEAST(COALESCE(i.dchdate, CURRENT_DATE + 1), p.u) - GREATEST(i.regdate, p.s))), 0)
                   FROM ipt i, params p
                  WHERE i.ward = w.ward AND i.regdate < p.u AND COALESCE(i.dchdate, CURRENT_DATE + 1) > p.s) AS patient_days
            FROM ward w
            WHERE w.ward_active = 'Y' AND (w.ward NOT IN ($excluded) OR w.ward = '$homeWard')
            ORDER BY w.ward
        ");
        $stmt->execute([':startDate' => $startDate, ':endDate' => $endDate, ':endDate2' => $endDate]);
        $periodDays = (int)$row['period_days'];
        $wards = array_map(fn($w) => [
            'ward'           => $w['ward'],
            'name'           => $w['name'],
            'home'           => $w['ward'] === IPD_HOME_WARD,   // ไม่นับรวมเตียงทั้งหมด
            'beds'           => (int)$w['beds'],
            'current'        => (int)$w['current'],
            // ครองเตียงตอนนี้ = ผู้ป่วยตอนนี้ ÷ เตียง (สูตรและทศนิยมเดียวกับการ์ดหน้าแรก)
            'current_rate'   => (int)$w['beds'] > 0 ? round($w['current'] * 100 / $w['beds'], 2) : 0,
            'admissions'     => (int)$w['admissions'],
            'occupancy_rate' => (int)$w['beds'] > 0 && $periodDays > 0
                ? round($w['patient_days'] * 100 / ($w['beds'] * $periodDays), 1)
                : 0,
        ], $stmt->fetchAll(PDO::FETCH_ASSOC));
        // ลำดับเดียวกับหน้าแรก: ครองเตียงตอนนี้น้อยไปมาก, Home Ward ท้ายสุด
        usort($wards, fn($a, $b) => [$a['home'], $a['current_rate']] <=> [$b['home'], $b['current_rate']]);

        $discharges = (int)$row['discharges'];
        return [
            'start_date'         => $startDate,
            'end_date'           => $p['end'],
            'current_inpatients' => (int)$row['current_inpatients'],
            'admissions'         => (int)$row['admissions'],
            'discharges'         => $discharges,
            'total_beds'         => (int)$row['total_beds'],
            'patient_days'       => (int)$row['patient_days'],
            'occupancy_rate'     => (float)($row['occupancy_rate'] ?? 0),
            'avg_los'            => (float)($row['avg_los'] ?? 0),
            'readmission_cases'  => (int)$row['readmission_cases'],
            'readmission_rate'   => (float)($row['readmission_rate'] ?? 0),
            'readmission_base'   => (int)$row['readmission_base'],
            // อัตราต่อผู้ป่วยที่จำหน่ายในช่วงเดียวกัน
            'deaths'             => $deaths,
            'death_rate'         => $discharges > 0 ? round($deaths * 100 / $discharges, 1) : 0,
            'refers'             => $refers,
            'refer_rate'         => $discharges > 0 ? round($refers * 100 / $discharges, 1) : 0,
            'dch_types'          => $dchTypes,
            'wards'              => $wards,
        ];
    });

} catch (Throwable $e) {
    ipd_fail($e, 'ipd_kpi_summary');
}
