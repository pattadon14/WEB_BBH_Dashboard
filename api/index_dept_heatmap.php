<?php

header('Content-Type: application/json; charset=utf-8');

require_once '../config/database.php';

try {

    /*
    ==========================
    Query: จำนวนผู้ป่วยตามแผนก × ชั่วโมง
    กรอง ovstist <> '4' (ไม่นับ cancel)
    ==========================
    */

    $sql = "
        SELECT
            EXTRACT(HOUR FROM (o.vstdate + o.vsttime::TIME))::int AS hour,
            k.department AS dept,
            COUNT(*) AS value
        FROM ovst o
        JOIN kskdepartment k
            ON k.depcode = o.main_dep
        WHERE
            o.vstdate = CURRENT_DATE
        GROUP BY
            EXTRACT(HOUR FROM (o.vstdate + o.vsttime::TIME))::int,
            k.department
        ORDER BY
            hour ASC,
            dept ASC
    ";

    $stmt = $conn->query($sql);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    /*
    ==========================
    รายชื่อแผนก (เรียงตามยอดรวม มากไปน้อย)
    ==========================
    */

    $deptTotal = [];
    foreach ($rows as $row) {
        $dept = $row['dept'];
        $deptTotal[$dept] = ($deptTotal[$dept] ?? 0) + (int)$row['value'];
    }
    arsort($deptTotal);
    $depts = array_keys($deptTotal);

    // lookup: hour_dept → value
    $lookup = [];
    foreach ($rows as $row) {
        $key = (int)$row['hour'] . '_' . $row['dept'];
        $lookup[$key] = (int)$row['value'];
    }

    /*
    ==========================
    *** ตัดช่วงเวลาให้เหลือเฉพาะชั่วโมงที่มีข้อมูลจริง ***
    หาเฉพาะชั่วโมงแรก → ชั่วโมงสุดท้ายที่มีคนไข้
    (ไม่โชว์ช่วงดึก/ช่วงเย็นที่ยังว่างของวันนี้)
    ==========================
    */

    $activeHours = [];
    foreach ($rows as $row) {
        $activeHours[(int)$row['hour']] = true;
    }

    if (!empty($activeHours)) {
        $minH = min(array_keys($activeHours));
        $maxH = max(array_keys($activeHours));
    } else {
        // ยังไม่มีข้อมูลวันนี้ → fallback ช่วงเวลาทำการ
        $minH = 7;
        $maxH = 16;
    }

    $hourRange = range($minH, $maxH);

    /*
    ==========================
    data array → [hourIndex, deptIndex, value]
    *** x ใช้ "index ใน hourRange" ไม่ใช่เลขชั่วโมงดิบ ***
    ==========================
    */

    $data    = [];
    $peakVal = 0;

    foreach ($hourRange as $xIdx => $h) {
        foreach ($depts as $dIdx => $dept) {
            $val = $lookup["{$h}_{$dept}"] ?? 0;
            if ($val > 0) {
                $data[] = [$xIdx, $dIdx, $val];
                if ($val > $peakVal) {
                    $peakVal = $val;
                }
            }
        }
    }

    $result = [
        'depts'   => $depts,
        'hours'   => array_map(
            fn($h) => str_pad($h, 2, '0', STR_PAD_LEFT) . ':00',
            $hourRange
        ),
        'data'    => $data,
        'total'   => array_sum($deptTotal),
        'peakVal' => $peakVal,
    ];

    $json = json_encode($result, JSON_UNESCAPED_UNICODE);

    echo $json;

} catch (Exception $e) {

    echo json_encode([
        'error' => $e->getMessage()
    ]);

}