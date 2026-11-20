<?php

header('Content-Type: application/json; charset=utf-8');

require_once '../config/database.php';
require_once '../config/ipd.php';

try {

    // ช่วงเวลา: ?d1&d2 หรือ ?year (config/period.php)
    $p = bbh_period();

    ipd_cached('ipd_top10', $p, function () use ($conn, $p) {
        // นับตามวันจำหน่าย: an_stat มีการวินิจฉัยหลัก (pdx) หลังจำหน่ายเท่านั้น
        // ถ้านับตามวัน admit ช่วงสั้น/ล่าสุดจะได้แค่ไม่กี่ราย (ผู้ป่วยส่วนใหญ่ยังไม่จำหน่าย)
        $stmt = $conn->prepare("
            SELECT
                i.pdx,
                d.name AS diag,
                d.tname,
                COUNT(*) AS total_case,
                SUM(CASE WHEN i.sex = '1' THEN 1 ELSE 0 END) AS male,
                SUM(CASE WHEN i.sex = '2' THEN 1 ELSE 0 END) AS female
            FROM an_stat i
            LEFT JOIN icd101 d ON d.code = i.pdx
            WHERE i.dchdate BETWEEN :startDate AND :endDate
              AND COALESCE(i.pdx, '') <> ''
            GROUP BY i.pdx, d.name, d.tname
            ORDER BY COUNT(*) DESC
            LIMIT 10
        ");
        $stmt->execute([':startDate' => $p['start'], ':endDate' => $p['end']]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    });

} catch (Throwable $e) {
    ipd_fail($e, 'ipd_top10_disease');
}
