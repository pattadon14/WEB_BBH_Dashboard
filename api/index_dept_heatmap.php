<?php

header('Content-Type: application/json; charset=utf-8');

require_once '../config/cache.php';
api_cache('home_dept_heatmap'); // คำตอบเดียวกันทุกจอ รีเฟรชทุก 60 วินาที
require_once '../config/database.php';

try {

    /*
    ==========================
    Query: จำนวนผู้ป่วยตามแผนก × ชั่วโมง
    ไม่นับ visit ที่ผู้ป่วยไม่ได้มาที่ รพ. (ovst.ovstist): 13 เยี่ยมบ้าน/ออกหน่วย, 14 วัคซีนในโรงเรียน,
    15 อนามัยโรงเรียน (ทันตกรรมโรงเรียน), 17 Telehealth/Telemedicine/ส่งยาไปรษณีย์ — ไม่ใช่ความแออัดหน้าห้อง
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
            AND o.ovstist NOT IN ('13', '14', '15', '17')
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

    /*
    ==========================
    ห้อง LAB: ไม่มีแผนกลงทะเบียนของตัวเอง จึงนับจากใบสั่งแล็ป (lab_head)
    1 ราย (vn) นับครั้งเดียว ที่ชั่วโมงแรกที่แล็ปรับสิ่งส่งตรวจวันนี้ (รวมใบสั่งจาก ward)
    ผู้ป่วยกลุ่มนี้ถูกนับที่แผนกลงทะเบียนแล้ว จึงไม่รวมใน total
    ==========================
    */

    $labRows = $conn->query("
        SELECT EXTRACT(HOUR FROM first_receive)::int AS hour, COUNT(*) AS value
        FROM (
            SELECT vn, MIN(receive_time) AS first_receive
            FROM lab_head
            WHERE receive_date = CURRENT_DATE
              AND receive_time IS NOT NULL
              AND COALESCE(vn, '') <> ''
            GROUP BY vn
        ) l
        GROUP BY 1
    ")->fetchAll(PDO::FETCH_ASSOC);

    foreach ($labRows as $row) {
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

    $hourIndex = array_flip($hourRange);
    $lab = [];
    foreach ($labRows as $row) {
        $lab[] = [$hourIndex[(int)$row['hour']], (int)$row['value']];
    }

    $result = [
        'depts'   => $depts,
        'lab'     => $lab,
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

    error_log(basename(__FILE__) . ': ' . $e->getMessage());   // ไม่ส่งรายละเอียดฐานข้อมูลออกไปหน้าเว็บ
    http_response_code(500);
    echo json_encode(['error' => 'โหลดข้อมูลไม่สำเร็จ'], JSON_UNESCAPED_UNICODE);

}