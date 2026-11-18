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
