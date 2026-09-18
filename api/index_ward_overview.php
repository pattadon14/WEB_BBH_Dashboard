<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

require_once '../config/database.php';

try {
    $sql = "SELECT
        w.ward,
        w.name,
        COALESCE(w.bedcount, 0) AS bedcount,
        COUNT(i.an) FILTER (WHERE i.dchdate IS NULL) AS current_admit,
        GREATEST(
            COALESCE(w.bedcount, 0) -
            COUNT(i.an) FILTER (WHERE i.dchdate IS NULL),
            0
        ) AS available_bed,
        ROUND(
            COUNT(i.an) FILTER (WHERE i.dchdate IS NULL) * 100.0 /
            NULLIF(w.bedcount, 0),
            2
        ) AS occupancy_rate,
        COUNT(i.an) FILTER (
            WHERE i.regdate >= CURRENT_DATE
              AND i.regdate < CURRENT_DATE + INTERVAL '1 day'
        ) AS admit_today,
        COUNT(i.an) FILTER (
            WHERE i.dchdate >= CURRENT_DATE
              AND i.dchdate < CURRENT_DATE + INTERVAL '1 day'
        ) AS discharge_today,
        COUNT(i.an) FILTER (
            WHERE i.dchdate IS NULL
              AND i.regdate::date < CURRENT_DATE - INTERVAL '7 days'
        ) AS long_stay_7,
        COUNT(i.an) FILTER (
            WHERE i.dchdate IS NULL
              AND i.regdate::date < CURRENT_DATE - INTERVAL '14 days'
        ) AS long_stay_14
    FROM ward w
    LEFT JOIN ipt i ON i.ward = w.ward
    WHERE w.ward_active = 'Y'
      AND w.ward NOT IN ('10','11','05','04')
    GROUP BY w.ward, w.name, w.bedcount
    ORDER BY
        CASE
            WHEN COALESCE(w.bedcount, 0) > 0
             AND COUNT(i.an) FILTER (WHERE i.dchdate IS NULL) * 100.0 / w.bedcount >= 100 THEN 0
            WHEN COALESCE(w.bedcount, 0) > 0
             AND COUNT(i.an) FILTER (WHERE i.dchdate IS NULL) * 100.0 / w.bedcount >= 90 THEN 1
            WHEN COUNT(i.an) FILTER (
                WHERE i.dchdate IS NULL
                  AND i.regdate::date < CURRENT_DATE - INTERVAL '14 days'
            ) > 0 THEN 2
            ELSE 3
        END,
        w.name";

    $stmt = $conn->query($sql);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as &$row) {
        $rate = (float)($row['occupancy_rate'] ?? 0);
        $current = (int)($row['current_admit'] ?? 0);
        $bedcount = (int)($row['bedcount'] ?? 0);
        $available = (int)($row['available_bed'] ?? 0);
        $admitToday = (int)($row['admit_today'] ?? 0);
        $dischargeToday = (int)($row['discharge_today'] ?? 0);
        $longStay7 = (int)($row['long_stay_7'] ?? 0);
        $longStay14 = (int)($row['long_stay_14'] ?? 0);

        if ($bedcount > 0 && $rate >= 100) {
            $row['status'] = 'full';
            $row['status_text'] = 'เตียงเต็ม';
        } elseif ($bedcount > 0 && $rate >= 90) {
            $row['status'] = 'watch';
            $row['status_text'] = 'เตียงใกล้เต็ม';
        } elseif ($longStay14 > 0) {
            $row['status'] = 'watch';
            $row['status_text'] = 'มีผู้ป่วยนอนนาน';
        } else {
            $row['status'] = 'normal';
            $row['status_text'] = 'สถานะปกติ';
        }

        $row['attention'] =
            ($row['status'] !== 'normal')
            || $longStay14 > 0
            || ($available > 0 && $admitToday > $available);

        $row['net_today'] = $admitToday - $dischargeToday;
    }
    unset($row);

    echo json_encode(
        $rows,
        JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
    );

} catch (Throwable $e) {
    http_response_code(500);

    echo json_encode([
        'error' => 'ไม่สามารถโหลดข้อมูลสถานการณ์ Ward ได้',
        'detail' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
