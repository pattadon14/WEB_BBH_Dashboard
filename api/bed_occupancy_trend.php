<?php

header('Content-Type: application/json; charset=utf-8');

require_once '../config/database.php';
require_once '../config/period.php';
require_once '../config/ward.php';

try {

    // ช่วงเวลา: ?d1&d2 หรือ ?year (config/period.php) — ช่วงสั้นแสดงรายวัน ช่วงยาวแสดงรายเดือน
    $p = bbh_period();

    /*
    ==========================
    จำนวนเตียงทั้งหมด
    ==========================
    */

    $bedSql = "SELECT SUM(" . ward_bedcount_sql() . ")
    AS total_bed
            FROM
                ward
            WHERE
                ward_active = 'Y'
                AND ward NOT IN ( '10', '11', '05', '04', '23' )";

    $totalBed = (int)$conn->query($bedSql)->fetch(PDO::FETCH_ASSOC)['total_bed'];

    /*
    ==========================
    ต่อช่อง (วัน/เดือน) ในช่วงที่เลือก
    - admit      = รับใหม่ (ipt.regdate) ทุก ward นับ AN — ตรงกับการ์ด "รับใหม่" และ Admit หน้าหลัก
    - occupancy  = วันนอนจริงในช่อง / (เตียงทั้งหมด x จำนวนวันในช่อง)
      วันนอน: นับคืนที่อยู่ในช่องนั้นจริง (วันจำหน่ายไม่นับ, ยังไม่จำหน่ายนับถึงวันนี้)
      ช่องที่ยังไม่จบ (เดือนนี้/วันนี้) หารด้วยจำนวนวันที่ผ่านไปแล้วเท่านั้น
      ward ชุดเดียวกับจำนวนเตียง (ไม่นับ LR, Observe ER, ส่องกล้อง, ผ่าตัด, Home Ward)
    ==========================
    */

    $sql = "
        WITH params AS (
            SELECT
                CAST(:startDate AS date) AS start_date,
                CAST(:endDate AS date) + 1 AS end_excl,
                CAST(('1 ' || :unit) AS interval) AS step
        ),
        buckets AS (
            SELECT
                TO_CHAR(gs, 'YYYY-MM-DD') AS bucket,
                GREATEST(gs::date, p.start_date) AS b_start,
                LEAST((gs + p.step)::date, p.end_excl, CURRENT_DATE + 1) AS b_until
            FROM params p,
                 generate_series(DATE_TRUNC(:unit2, p.start_date), LEAST(p.end_excl - 1, CURRENT_DATE)::timestamp, p.step) AS gs
        )
        SELECT
            b.bucket,
            b.b_until - b.b_start AS days,
            (SELECT COUNT(*) FROM ipt a WHERE a.regdate >= b.b_start AND a.regdate < b.b_until) AS admit,
            COALESCE(SUM(GREATEST(0,
                LEAST(COALESCE(i.dchdate, CURRENT_DATE + 1), b.b_until)
                - GREATEST(i.regdate, b.b_start)
            )), 0) AS patient_day
        FROM buckets b
        LEFT JOIN ipt i
            ON i.regdate < b.b_until
           AND COALESCE(i.dchdate, CURRENT_DATE + 1) > b.b_start
           AND i.ward NOT IN ('10', '11', '05', '04', '23')
        GROUP BY b.bucket, b.b_start, b.b_until
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':startDate' => $p['start'],
        ':endDate'   => $p['end'],
        ':unit'      => $p['unit'],
        ':unit2'     => $p['unit'],
    ]);

    $byBucket = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $byBucket[$row['bucket']] = $row;
    }

    $data = [];
    foreach (bbh_period_buckets($p) as $bucket => $label) {
        $row = $byBucket[$bucket] ?? null;
        $days = $row ? (int)$row['days'] : 0;
        $data[] = [
            'label'          => $label,
            'admit'          => $row ? (int)$row['admit'] : 0,
            'patient_day'    => $row ? (int)$row['patient_day'] : 0,
            'occupancy_rate' => $totalBed > 0 && $days > 0
                ? round($row['patient_day'] * 100 / ($totalBed * $days), 2)
                : 0,
        ];
    }

    echo json_encode([
        'total_bed' => $totalBed,
        'unit'      => $p['unit'],
        'data'      => $data,
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {

    http_response_code($e instanceof InvalidArgumentException ? 400 : 500);
    echo json_encode([
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);

}
