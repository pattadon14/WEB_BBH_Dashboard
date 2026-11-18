<?php

/*
 * นิยามข้อมูล ER ของ รพ.บ้านบึง — ใช้ร่วมกันทุก API ของหน้า ER (การ์ดสรุปรายวัน, กราฟ Triage)
 * ตรงกับต้นแบบ er-dashboard (queries.py: LEVEL, DCH) — ตรวจกับข้อมูลจริง 1 ต.ค.–16 พ.ย. 2569
 * ทุก SQL ใช้ alias e = er_regist, et = er_emergency_type (LEFT JOIN)
 */

// ระดับความเร่งด่วน 1–5 (1 = รุนแรงสุด) · 6 = Black · NULL = ไม่ระบุระดับ
// er_emergency_level_id ว่าง 20% → ใช้ export_code ของ er_emergency_type (ลง 99%)
// รหัส er_emergency_type ไม่เรียงความรุนแรง (1 = Emergency, 2 = Resuscitation) แต่ export_code เป็นระดับจริง
const ER_LEVEL_SQL = "CASE
        WHEN e.er_emergency_type = 6 THEN 6
        ELSE COALESCE(e.er_emergency_level_id,
                      CASE WHEN et.export_code ~ '^[0-9]+$' THEN LEAST(et.export_code::integer, 5) END)
    END";

// ผลการจำหน่าย: er_dch_type ว่าง 46% และลง Admit แค่ราวครึ่งของที่ Admit จริง
// 3 = Admit (มี ipt ของ visit นี้) · 2 = Refer (referout ที่ส่งจากจุด ER) · นอกนั้นตาม er_dch_type:
// 1 กลับบ้าน · 5 สังเกตอาการ · 6 ไม่รอตรวจ · 7 หนีกลับ · 8 ปฏิเสธการรักษา · 4/9/10 เสียชีวิต · NULL ยังไม่ลงผล
const ER_DCH_SQL = "CASE
        WHEN e.er_dch_type = 3 OR EXISTS (SELECT 1 FROM ipt ai WHERE ai.vn = e.vn) THEN 3
        WHEN e.er_dch_type = 2 OR EXISTS (SELECT 1 FROM referout ro WHERE ro.vn = e.vn AND ro.refer_point = 'ER') THEN 2
        ELSE e.er_dch_type
    END";

// วิธีมาถึง (ovst.ovstist): EMS = 09 FR / 10 BLS / 11 ALS · 04 = รับส่งต่อจากสถานพยาบาลอื่น
const ER_EMS_CODES = "('09', '10', '11')";

// Refer in (รับส่งต่อจากสถานพยาบาลอื่น) = ลงวิธีมา 04 หรือ มีใบรับส่งต่อใน referin ของ visit นี้ (ต้องมี alias o = ovst)
// ข้อมูลจริง 30 วัน (ER 4,999 visit): ovstist 04 = 35, referin = 41, ซ้อนกัน 29 → รวม 47
// มีใบ referin แต่วิธีมาไม่ใช่ 04 ได้ เช่น ALS / ญาตินำมา — ใช้อย่างใดอย่างหนึ่งจะนับขาด
// Refer out = ผลจำหน่าย 2 ใน ER_DCH_SQL (er_dch_type 2 หรือ referout จากจุด ER)
const ER_REFER_IN_SQL = "(o.ovstist = '04' OR EXISTS (SELECT 1 FROM referin ri WHERE ri.vn = e.vn))";

// ---- Door-to-Doctor (นาที) = เข้า ER (enter_er_time) ถึง "การกระทำแรกของแพทย์" — ตรงกับต้นแบบ queries.py (D2D + HPI)
// เร็วที่สุดของ: กดเริ่มตรวจ (service_time.service2 = OPD-DOCTOR ของบ้านบึง) · สั่ง Lab · สั่ง X-ray · สั่งยา
//   · เวลาที่พยาบาลจดใน HPI ว่ารายงาน/พบแพทย์ (er_hpi_minutes)
// แต่ละแหล่งนับเฉพาะ 0–240 นาทีหลังเข้า ER (ติดลบ = รายการจาก OPD ก่อนย้ายมา, เกิน 4 ชม. = ลืมกด) ไม่พบ = NULL
// door_to_doctor_second ใช้ไม่ได้ (ลงแค่ 17% ของ visit) · ข้อมูลจริง 30 วัน: มีเวลาแพทย์อย่างน้อย 1 แหล่ง 86% ของ visit
// ต้อง LEFT JOIN service_time s ON s.vn = e.vn · service_time เก็บเป็น TIME ของวันมา — ข้ามเที่ยงคืนบวก 1 วัน
const ER_D2D_MAX = 240;
const ER_D2D_SQL = "NULLIF(LEAST(
        COALESCE((SELECT CASE WHEN m BETWEEN 0 AND 240 THEN m END FROM
            (SELECT CASE WHEN r < -60 THEN r + 1440 ELSE r END AS m FROM
                (SELECT (EXTRACT(EPOCH FROM ((e.enter_er_time::date + s.service2) - e.enter_er_time)) / 60)::integer AS r) r0) m0), 99999),
        COALESCE((SELECT MIN((EXTRACT(EPOCH FROM ((x.order_date::date + x.order_time) - e.enter_er_time)) / 60)::integer)
            FROM lab_head x WHERE x.vn = e.vn
             AND (x.order_date::date + x.order_time) BETWEEN e.enter_er_time AND e.enter_er_time + interval '240 minutes'), 99999),
        COALESCE((SELECT MIN((EXTRACT(EPOCH FROM ((x.request_date::date + x.request_time) - e.enter_er_time)) / 60)::integer)
            FROM xray_report x WHERE x.vn = e.vn
             AND (x.request_date::date + x.request_time) BETWEEN e.enter_er_time AND e.enter_er_time + interval '240 minutes'), 99999),
        COALESCE((SELECT MIN((EXTRACT(EPOCH FROM ((x.rxdate::date + x.rxtime) - e.enter_er_time)) / 60)::integer)
            FROM opitemrece x JOIN drugitems di ON di.icode = x.icode WHERE x.vn = e.vn
             AND (x.rxdate::date + x.rxtime) BETWEEN e.enter_er_time AND e.enter_er_time + interval '240 minutes'), 99999)
    ), 99999)";
// HPI ที่อาจมีเวลาพบแพทย์ (ต้อง LEFT JOIN opdscreen sc ON sc.vn = e.vn) — แยกเวลาด้วย er_hpi_minutes() ใน PHP
const ER_D2D_HPI_SQL = "CASE WHEN sc.hpi LIKE '%แพทย์%' OR sc.hpi LIKE '%onsult%'
        THEN REPLACE(REPLACE(REPLACE(sc.hpi, chr(13), ' '), chr(10), ' '), chr(9), ' ') END";
// เป้าหมายเวลาพบแพทย์ (นาที) ตามระดับ — ชุดเดียวกับต้นแบบ (D2D_TARGET / D2D_TARGET_LABEL)
const ER_D2D_TARGET = [1 => 5, 2 => 10, 3 => 30, 4 => 60, 5 => 120];
const ER_D2D_TARGET_LABEL = [1 => 'ทันที', 2 => '≤ 10 นาที', 3 => '≤ 30 นาที', 4 => '≤ 60 นาที', 5 => '≤ 120 นาที'];

// นาทีจากเข้า ER ถึงเวลาแรกที่พยาบาลจดว่ารายงาน/พบแพทย์ใน HPI เช่น "รายงานแพทย์ at 09.15", "13.46 น. รายงานแพทย์"
// (0–240 นาที) ไม่พบ = null · พอร์ตจาก hpi_minutes() ในต้นแบบ · ไม่ใช้ "dr" — ตรงกับ drip / drink / Dressing
function er_hpi_minutes(string $enter, ?string $hpi): ?int
{
    if ($hpi === null || $hpi === '') {
        return null;
    }
    $kw = '(?:รายงานแพทย์|แจ้งแพทย์|พบแพทย์|ตามแพทย์|แพทย์|[Cc]onsult)';
    $t  = '(?:[01]?\d|2[0-3])\s*[.:]\s*[0-5]\d';
    // shortcut: \d / \s เป็น ASCII (ต้นแบบ Python รับเลขไทย ๐–๙ ด้วย) — ถ้าพบว่าพยาบาลจดเวลาเป็นเลขไทย ให้แปลงก่อน
    $re = "/{$kw}[^0-9]{0,30}?{$t}|{$t}(?:\s*[,\/]\s*{$t})*\s*(?:น\.?)?[^0-9]{0,20}?{$kw}/u";
    if (!preg_match_all($re, $hpi, $m)) {
        return null;
    }
    $in = strtotime($enter);
    $day = date('Y-m-d', $in);
    $best = null;
    foreach ($m[0] as $hit) {
        preg_match_all('/([01]?\d|2[0-3])\s*[.:]\s*([0-5]\d)/u', $hit, $tm, PREG_SET_ORDER);
        foreach ($tm as [, $hh, $mm]) {
            $d = (int)((strtotime(sprintf('%s %02d:%02d:00', $day, $hh, $mm)) - $in) / 60);   // ตัดเศษเข้าหา 0 แบบต้นแบบ
            if ($d < -60) {
                $d += 1440;   // จดเวลาหลังเที่ยงคืน
            }
            if ($d >= 0 && $d <= ER_D2D_MAX && ($best === null || $d < $best)) {
                $best = $d;
            }
        }
    }
    return $best;
}
