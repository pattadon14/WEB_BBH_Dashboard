<?php

// รหัสแผนก (ovst.main_dep) ของผู้ป่วยนอก OPD แยก 2 แท็บในหน้า opd_detail — แก้รายการที่นี่ที่เดียว
// ตามรายการที่แผนก OPD กำหนด (10 พ.ย. 2569) + ตกลงเพิ่ม (16 พ.ย. 2569): 178 Telemedicine และ 248/249 สูติ/นรีเวช
// อยู่คลินิกพิเศษ · ไม่นับ 096 ศูนย์พิทักษ์สิทธิ์ · รหัสที่ใช้ร่วม (021 061 080 115 144 191 201 209 231 232 238 244)
// นับเป็นตรวจโรคทั่วไปตามแผนกที่ลงทะเบียน

// ตรวจโรคทั่วไป (walk-in และนัด) — รวม 230 Fast Track
const OPD_GENERAL_DEPS = [
    '002', '009', '010', '021', '023', '029', '036', '058', '061', '076', '080', '086', '088',
    '108', '109', '111', '115', '121', '125', '126', '127', '128', '129', '131', '134', '144',
    '162', '176', '177', '191', '201', '207', '209', '224', '230', '231', '232', '238', '244',
];

// คลินิกพิเศษ — รวม Telemed (178, 245) และส่งเวชภัณฑ์ทางไปรษณีย์ (143) ที่ผู้ป่วยไม่ได้มา รพ.
const OPD_SPECIAL_DEPS = [
    '024', '035', '039', '056', '057', '059', '060', '087', '090', '093', '097', '099', '118',
    '138', '139', '142', '143', '173', '178', '197', '213', '218', '239', '242', '245', '248', '249',
];

// OPD ทั้งหมด = ทั่วไป + คลินิกพิเศษ (ไม่ซ้ำกัน) — การ์ด OPD หน้าหลัก, แนวโน้มปีงบ, 10 อันดับโรค, กลุ่มอายุ
const OPD_MAIN_DEPS = [...OPD_GENERAL_DEPS, ...OPD_SPECIAL_DEPS];

// แท็บที่หน้าเว็บขอ (?scope=general|special) — ค่าอื่นถือเป็น general
function opd_scope(): string
{
    return ($_GET['scope'] ?? '') === 'special' ? 'special' : 'general';
}

function opd_dep_list(string $scope = 'all'): array
{
    return match ($scope) {
        'general' => OPD_GENERAL_DEPS,
        'special' => OPD_SPECIAL_DEPS,
        default   => OPD_MAIN_DEPS,
    };
}

// รายการสำหรับ SQL IN (...) — ค่าคงที่ในโค้ด ไม่ได้มาจากผู้ใช้ จึงต่อสตริงได้
function opd_dep_sql(string $scope = 'all'): string
{
    return "'" . implode("','", opd_dep_list($scope)) . "'";
}

function opd_main_dep_sql(): string
{
    return opd_dep_sql('all');
}

// นัดหมายของ OPD ชุดเดียวกับ visit (ไม่ใช้รายการรหัสคลินิก) — Walk-in + มาตามนัด = ยอดรวมเสมอ
//  - มาตามนัด = visit ชุด OPD ที่ผูกกับนัด (oapp.visit_vn) นับเป็น visit
//  - ยังไม่มา = นัดของวันนั้นที่แผนกปลายทาง (oapp.depcode) อยู่ในชุด OPD ยังไม่มี visit ไม่ยกเลิก (status 4)
//    ผู้ป่วยไม่เสียชีวิต — oapp.depcode อยู่แท็บเดียวกับแผนกที่มาจริง 99.7% (ตรวจนัดที่มาแล้ว 2–16 พ.ย. 2569)
function opd_appt_came_sql(string $vn = 'o.vn'): string
{
    return "EXISTS (SELECT 1 FROM oapp oa_c WHERE oa_c.visit_vn = $vn)";
}

function opd_appt_miss_sql(string $date, string $scope = 'all', string $a = 'oa'): string
{
    return "$a.nextdate = $date
        AND COALESCE($a.visit_vn, '') = ''
        AND $a.oapp_status_id IS DISTINCT FROM 4
        AND $a.depcode IN (" . opd_dep_sql($scope) . ")
        AND NOT EXISTS (SELECT 1 FROM patient pd WHERE pd.hn = $a.hn AND pd.death IS NOT NULL AND pd.death <> 'N')";
}