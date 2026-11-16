<?php

// รหัสแผนก (ovst.main_dep) ที่นับเป็นผู้ป่วยนอก OPD — ใช้ชุดเดียวกันทุก API ให้ยอดตรงกันทุกหน้า
// (การ์ด OPD หน้าหลัก api/opd.php, หน้า OPD api/opd_visit_today.php, กราฟสิทธิ์ api/opd_pttype_today.php)
// รวม 230 Fast Track ตามที่ตกลงไว้ — แก้รายการที่นี่ที่เดียว
const OPD_MAIN_DEPS = [
    '002', '009', '010', '021', '023', '029', '036', '076', '080', '088', '096', '108', '109',
    '110', '111', '115', '121', '125', '126', '127', '128', '129', '130', '131', '134', '144',
    '162', '176', '177', '191', '199', '201', '207', '230', '231', '232', '238', '244',
    '204', '035', '039', '056', '057', '059', '060', '087', '090', '093', '097', '099', '118',
    '136', '138', '139', '142', '143', '173', '197', '206', '237', '242', '248', '249',
];

// รายการสำหรับ SQL IN (...) — ค่าคงที่ในโค้ด ไม่ได้มาจากผู้ใช้ จึงต่อสตริงได้
function opd_main_dep_sql(): string
{
    return "'" . implode("','", OPD_MAIN_DEPS) . "'";
}

// นัดหมายของ OPD ชุดเดียวกับ visit (ไม่ใช้รายการรหัสคลินิก) — Walk-in + มาตามนัด = ยอดรวมเสมอ
//  - มาตามนัด = visit ชุด OPD ที่ผูกกับนัด (oapp.visit_vn) นับเป็น visit
//  - ยังไม่มา = นัดของวันนั้นที่แผนกปลายทาง (oapp.depcode) อยู่ในชุด OPD ยังไม่มี visit ไม่ยกเลิก (status 4)
//    ผู้ป่วยไม่เสียชีวิต — oapp.depcode ตรงกลุ่มกับแผนกที่มาจริง 98.7% (ตรวจนัด 2–16 พ.ย. 2569)
function opd_appt_came_sql(string $vn = 'o.vn'): string
{
    return "EXISTS (SELECT 1 FROM oapp oa_c WHERE oa_c.visit_vn = $vn)";
}

function opd_appt_miss_sql(string $date, string $a = 'oa'): string
{
    return "$a.nextdate = $date
        AND COALESCE($a.visit_vn, '') = ''
        AND $a.oapp_status_id IS DISTINCT FROM 4
        AND $a.depcode IN (" . opd_main_dep_sql() . ")
        AND NOT EXISTS (SELECT 1 FROM patient pd WHERE pd.hn = $a.hn AND pd.death IS NOT NULL AND pd.death <> 'N')";
}