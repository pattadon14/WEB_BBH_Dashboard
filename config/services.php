<?php

/*
 * นิยามบริการในการ์ด "สถิติการใช้บริการอื่นๆ วันนี้" หน้าแรก — ที่เดียว ใช้ทั้งตัวเลขบนการ์ด
 * (api/service_summary_today.php) และ popup รายละเอียด (api/service_detail.php) ตัวเลขจึงตรงกันเสมอ
 *
 * แต่ละ sql รับ :d (วันที่) คืน 1 แถวต่อ 1 หน่วยที่นับ คอลัมน์: hn, vn, an, t (เวลา), item (รายการที่ได้รับ คั่นด้วย ' | ')
 * ฐาน HOSxP เป็น TIS-620 — ห้ามใช้อักขระนอกภาษาไทย/ASCII (เช่น ·) ใน SQL
 * by = แยกยอดใน popup ตาม item (รายการ) | dep (แผนกที่ลงทะเบียน/วอร์ด) | pttype (สิทธิ์)
 */

date_default_timezone_set('Asia/Bangkok'); // เซิร์ฟเวอร์เป็น UTC — "วันนี้" และเวลาเทียบสัปดาห์ก่อนต้องเป็นเวลาไทย

// visit ของวันนั้นที่ผ่านแผนก — ดู opd_dep_queue ด้วย เพราะ ovst.cur_dep เปลี่ยนทันทีเมื่อผู้ป่วยย้ายออกจากห้อง
function svc_visit_through(string $deps, bool $mainDep = true): string
{
    return "o.vstdate = :d AND (" . ($mainDep ? "o.main_dep IN ($deps) OR " : '') . "o.cur_dep IN ($deps)
        OR EXISTS (SELECT 1 FROM opd_dep_queue q WHERE q.vn = o.vn AND q.depcode IN ($deps)))";
}

// นับคน (HN) ที่ผ่านแผนกวันนั้น รวมที่ Admit เข้าวอร์ดวันนั้น — แถวเวลาแรกสุดของแต่ละคน
function svc_people_dep_ward(string $dep, string $ward, string $opdLabel): string
{
    return "SELECT DISTINCT ON (hn) hn, vn, an, t, item FROM (
            SELECT o.hn, o.vn, NULL::varchar an, o.vsttime t, '$opdLabel'::text item
            FROM ovst o WHERE " . svc_visit_through("'$dep'") . "
            UNION ALL
            SELECT i.hn, NULL, i.an, i.regtime, 'Admit วอร์ด'
            FROM ipt i WHERE i.regdate = :d AND i.ward = '$ward'
        ) s ORDER BY hn, t";
}

// xray_report นับ visit/admit ไม่ซ้ำ
function svc_xray(string $where): string
{
    return "SELECT MIN(r.hn) hn, MIN(NULLIF(r.vn, '')) vn, MIN(NULLIF(r.an, '')) an, MIN(r.request_time) t,
            string_agg(DISTINCT i.xray_items_name, ' | ') item
        FROM xray_report r
        JOIN xray_items i ON i.xray_items_code = r.xray_items_code
        WHERE r.request_date = :d AND $where AND COALESCE(NULLIF(r.vn, ''), r.an) IS NOT NULL
        GROUP BY COALESCE(NULLIF(r.vn, ''), r.an)";
}

function service_defs(): array
{
    return [
        // ห้องส่องกล้อง 121 นับคน (HN) จาก 3 แหล่ง: visit ที่ผ่าน 121, ผู้ป่วยในที่มีค่าบริการลงแผนก 121,
        // ผู้ป่วยในที่ลงหัตถการส่องกล้อง (ICD-9) ซึ่งไม่อยู่ในตารางผ่าตัด (Admit อยู่ก่อนไม่มีคิว 121 · ward 10 เลิกใช้ปี 2566)
        'scope' => ['label' => 'ห้องส่องกล้อง', 'by' => 'item', 'sql' => "
            SELECT DISTINCT ON (hn) hn, vn, an, t, item FROM (
                SELECT o.hn, o.vn, NULL::varchar an, o.vsttime t, 'ผู้ป่วยนอก'::text item
                FROM ovst o WHERE " . svc_visit_through("'121'", false) . "
                UNION ALL
                SELECT r.hn, NULL, r.an, r.rxtime, 'ผู้ป่วยใน (ค่าบริการ)'
                FROM opitemrece r WHERE r.rxdate = :d AND r.dep_code = '121' AND r.an <> ''
                UNION ALL
                SELECT i.hn, NULL, io.an, io.optime, 'ผู้ป่วยใน (หัตถการ)'
                FROM iptoprt io JOIN ipt i ON i.an = io.an
                WHERE io.opdate = :d
                  AND LEFT(io.icd9, 4) IN ('4513', '4516', '4523', '4525', '4542', '4233', '4443')
                  AND NOT EXISTS (SELECT 1 FROM operation_list l WHERE l.an = io.an AND l.operation_date = io.opdate)
            ) s ORDER BY hn, t"],

        // ไม่นับเคสที่ยกเลิกการผ่าตัด (status_id = 9)
        'or' => ['label' => 'ห้องผ่าตัด', 'by' => 'item', 'sql' => "
            SELECT l.hn, NULLIF(l.vn, '') vn, NULLIF(l.an, '') an, l.operation_time t, NULLIF(l.operation_name, '') item
            FROM operation_list l
            WHERE l.operation_date = :d AND l.status_id IS DISTINCT FROM 9"],

        // นับแผนการรักษา (physic_plan) ที่มี vn — เวลา = เวลาบันทึกแผน
        'pt' => ['label' => 'กายภาพบำบัด', 'by' => 'item', 'sql' => "
            SELECT p.hn, p.vn, NULLIF(p.an, '') an,
                CASE WHEN p.physic_plan_lastupdate::date = p.physic_plan_date THEN p.physic_plan_lastupdate::time END t,
                (SELECT string_agg(DISTINCT pi.name, ' | ') FROM physic_list l
                 JOIN physic_items pi ON pi.physic_items_id = l.physic_items_id WHERE l.vn = p.vn) item
            FROM physic_plan p
            WHERE p.physic_plan_date = :d AND p.vn IS NOT NULL AND p.vn <> ''"],

        'thai' => ['label' => 'แพทย์แผนไทย', 'by' => 'item', 'sql' => "
            SELECT MIN(s.hn) hn, s.vn, MIN(NULLIF(s.an, '')) an, MIN(s.service_time) t,
                string_agg(DISTINCT oi.health_med_operation_item_name, ' | ') item
            FROM health_med_service s
            LEFT JOIN health_med_service_operation so ON so.health_med_service_id = s.health_med_service_id
            LEFT JOIN health_med_operation_item oi ON oi.health_med_operation_item_id = so.health_med_operation_item_id
            WHERE s.service_date = :d AND s.vn IS NOT NULL
            GROUP BY s.vn"],

        // dtmain.vsttime ว่างทุกแถว — ใช้เวลาเริ่มทำหัตถการ (begin_time) แทน
        'dent' => ['label' => 'ทันตกรรม', 'by' => 'item', 'sql' => "
            SELECT MIN(d.hn) hn, d.vn, MIN(NULLIF(d.an, '')) an, MIN(d.begin_time)::time t,
                string_agg(DISTINCT COALESCE(tm.name, d.tmcode), ' | ') item
            FROM dtmain d LEFT JOIN dttm tm ON tm.code = d.tmcode
            WHERE d.vstdate = :d AND d.vn IS NOT NULL
            GROUP BY d.vn"],

        'hemo' => ['label' => 'ไตเทียม', 'by' => 'pttype', 'sql' => "
            SELECT o.hn, o.vn, NULL::varchar an, o.vsttime t, NULL::text item
            FROM ovst o WHERE o.vstdate = :d AND o.main_dep = '069'"],

        // รวมคนที่ถูกส่งต่อมาจากจุดอื่น ซึ่ง main_dep ไม่ใช่ 078/047
        'wound' => ['label' => 'ห้องทำแผล-ฉีดยา', 'by' => 'dep', 'sql' => "
            SELECT o.hn, o.vn, NULL::varchar an, o.vsttime t, NULL::text item
            FROM ovst o WHERE " . svc_visit_through("'078', '047'")],

        // ศูนย์พิทักษ์สิทธิ์ 096 — ไม่นับในยอด OPD (config/opd.php) · รวมที่ถูกส่งต่อมาจากจุดอื่น
        'rights' => ['label' => 'ศูนย์พิทักษ์สิทธิ์', 'by' => 'dep', 'sql' => "
            SELECT o.hn, o.vn, NULL::varchar an, o.vsttime t, NULL::text item
            FROM ovst o WHERE " . svc_visit_through("'096'")],

        // คำขออัลตราซาวน์อยู่ในระบบ X-ray ผู้ป่วยส่วนใหญ่ไม่ผ่านแผนก 080/240
        'us' => ['label' => 'อัลตราซาวน์', 'by' => 'item',
            'sql' => svc_xray("i.xray_items_group = 2 AND i.xray_items_name ILIKE '%ultrasound%'")],

        'observe' => ['label' => 'Observe ER', 'by' => 'item', 'sql' => svc_people_dep_ward('119', '05', 'ผ่านห้อง Observe')],

        // เอกซเรย์ทั่วไปและ Portable (กลุ่ม 1, 4) — xray_report เก็บ EKG, CT, อัลตราซาวน์ และผลส่องกล้องไว้ด้วย
        'xray' => ['label' => 'X-Ray', 'by' => 'item', 'sql' => svc_xray('i.xray_items_group IN (1, 4)')],

        'lr' => ['label' => 'LR', 'by' => 'item', 'sql' => svc_people_dep_ward('004', '04', 'ผ่านห้องคลอด')],

        // xray_head.vn / lab_head.vn เก็บ AN แทน VN สำหรับผู้ป่วยใน จึงส่งค่าเดียวกันเป็นทั้ง vn และ an
        'lab' => ['label' => 'Lab', 'by' => 'item', 'sql' => "
            SELECT MIN(h.hn) hn, h.vn, h.vn an, MIN(h.order_time) t, string_agg(DISTINCT h.form_name, ' | ') item
            FROM lab_head h WHERE h.order_date = :d AND h.vn IS NOT NULL
            GROUP BY h.vn"],

        'ct' => ['label' => 'CT Scan', 'by' => 'item', 'sql' => "
            SELECT MIN(h.hn) hn, h.vn, h.vn an, MIN(COALESCE(h.order_time, h.order_date_time::time)) t, string_agg(DISTINCT h.xray_list, ' | ') item
            FROM xray_head h WHERE h.order_date = :d AND h.xray_list ILIKE '%CT%' AND h.vn IS NOT NULL
            GROUP BY h.vn"],
    ];
}

// จำนวนของบริการ ณ วันที่ — $until (HH:MM:SS) นับเฉพาะถึงเวลานั้น ใช้เทียบสัปดาห์ก่อนถึงเวลาเดียวกัน
function service_count(PDO $conn, string $sql, string $date, ?string $until = null): int
{
    $st = $conn->prepare("SELECT COUNT(*) FROM ($sql) x" . ($until ? ' WHERE x.t IS NULL OR x.t <= CAST(:until AS time)' : ''));
    $st->execute($until ? [':d' => $date, ':until' => $until] : [':d' => $date]);
    return (int) $st->fetchColumn();
}
