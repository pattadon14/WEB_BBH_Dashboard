<?php

header('Content-Type: application/json; charset=utf-8');

require_once '../config/database.php';

try {

    $year = isset($_GET['year'])
        ? (int)$_GET['year']
        : date('Y') + 543 + (date('n') >= 10 ? 1 : 0);

    /*
    ==========================
    ปีงบประมาณ
    ==========================
    */

    $startDate = ($year - 544) . '-10-01';
    $endDate   = ($year - 543) . '-09-30';

    /*
    ==========================
    Cache Settings
    ==========================
    */

    $cacheDir  = __DIR__ . '/../storage/cache/';
    $cacheFile = $cacheDir . 'er_chart_v2_' . $year . '.json';
    $cacheTTL  = 5 * 60; // 5 นาที

    if (
        file_exists($cacheFile) &&
        (time() - filemtime($cacheFile)) < $cacheTTL
    ) {
        echo file_get_contents($cacheFile);
        exit;
    }

    /*
    ==========================
    ER Trend รายเดือน
    er       = ผู้รับบริการ ER ทุกประเภท (นับครั้ง จาก er_regist ตรงกับการ์ด ER หน้าหลัก)
    accident = เฉพาะ er_pt_type 2 ผู้ป่วยอุบัติเหตุ (เดิมนับ 2,5 แต่ 5 = ห้องทำแผลเวรเช้า ไม่ใช่อุบัติเหตุ)
    ==========================
    */

    $sqlTrend = "
        SELECT
            EXTRACT(MONTH FROM vstdate) AS month,
            TO_CHAR(vstdate, 'YYYY-MM') AS amonth,
            COUNT(*) AS er_total,
            COUNT(*) FILTER (WHERE er_pt_type = 2) AS accident_total
        FROM er_regist
        WHERE
            vstdate BETWEEN :startDate AND :endDate
        GROUP BY
            TO_CHAR(vstdate, 'YYYY-MM'),
            EXTRACT(MONTH FROM vstdate)
        ORDER BY amonth
    ";

    $stmtTrend = $conn->prepare($sqlTrend);
    $stmtTrend->execute([
        ':startDate' => $startDate,
        ':endDate'   => $endDate,
    ]);
    $rows = $stmtTrend->fetchAll(PDO::FETCH_ASSOC);

    // เรียงตามปีงบประมาณ
    $months   = [10,11,12,1,2,3,4,5,6,7,8,9];
    $monthMap = [];
    foreach ($rows as $row) {
        $monthMap[(int)$row['month']] = [(int)$row['er_total'], (int)$row['accident_total']];
    }

    $trend = [];
    foreach ($months as $month) {
        $trend[] = [
            'month' => $month,
            'er'       => $monthMap[$month][0] ?? 0,
            'accident' => $monthMap[$month][1] ?? 0,
        ];
    }

    /*
    ==========================
    Pie Chart — สัดส่วนประเภทผู้ป่วย
    ==========================
    */

    $sqlPie = "SELECT
            er.er_pt_type  AS ertype,
            et.name        AS typename,
            COUNT(*)       AS total
        FROM er_regist er
        LEFT JOIN er_pt_type et
            ON et.er_pt_type = er.er_pt_type
        WHERE
            er.vstdate BETWEEN :startDate AND :endDate
        GROUP BY
            er.er_pt_type,
            et.name
        ORDER BY total DESC
    ";

    $stmtPie = $conn->prepare($sqlPie);
    $stmtPie->execute([
        ':startDate' => $startDate,
        ':endDate'   => $endDate,
    ]);
    $pieRows = $stmtPie->fetchAll(PDO::FETCH_ASSOC);

    $pie = [];
    foreach ($pieRows as $row) {
        $pie[] = [
            'name' => $row['typename'] ?? 'ไม่ระบุ',
            'y'    => (int)$row['total'],
        ];
    }

    /*
    ==========================
    Output
    ==========================
    */

    $result = [
        'trend' => $trend,
        'pie'   => $pie,
    ];

    $json = json_encode($result, JSON_UNESCAPED_UNICODE);

    // บันทึก Cache
    if (!is_dir($cacheDir)) {
        mkdir($cacheDir, 0755, true);
    }
    file_put_contents($cacheFile, $json);

    echo $json;

} catch (Exception $e) {

    echo json_encode([
        'error' => $e->getMessage()
    ]);

}