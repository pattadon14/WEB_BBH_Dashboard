<?php

/*
 * งานฝากครรภ์ — ตัวชี้วัดคุณภาพ 10 ตัว (ส่วนที่ 3 ของหน้า ANC) · ต่อจาก config/anc.php
 * พอร์ตจากต้นแบบ queries.py (PREGNANCIES / ANC_HCT_LABS / POSTNATAL / INFANTS) + metrics.py (indicators)
 * รหัสด้านล่างคือค่าที่ต้นแบบเลือกไว้ตรวจกับข้อมูลบ้านบึง (ปีงบ 2569) — ตรึงเป็นค่าคงที่ ไม่เดาจากชื่อรายการตอนรัน
 */

require_once __DIR__ . '/anc.php';

const ANC_DT_VACCINE_CODES = ['106'];           // dT ANC; P41 (Tdap) ยังไม่นับ — รอผู้ใช้ตัดสิน
const ANC_HCT_LAB_IDS = [5, 6];                 // anc_lab.anc_lab_id ของผล Hct
const ANC_HCT_CUTOFF = 33.0;                    // ซีด = Hct < 33 ครั้งล่าสุดที่ GA >= 28
const ANC_HCT_LATE_GA = 28;
const ANC_LBW_GRAMS = 2500;
const ANC_DEAD_DCHSTTS = ['08', '09'];          // ipt_labour_infant.infant_dchstts ที่ถือว่าทารกเสียชีวิต
const ANC_CBC_LAB_CODES = ['1120', '522', '69', '505', '3', '447', '2', '973', '511'];
const ANC_DM_LAB_CODES = ['1079', '171', '173', '178', '167', '168', '165', '994', '94', '641', '185', '667', '636', '176', '163'];
const ANC_THAL_EXPORT = ['1', '2', '4'];
const ANC_THAL_LAB_CODES = ['84', '511', '508', '510'];
const ANC_US_ICD9 = ['8878', '5260700', '5260701'];
const ANC_DOWN_DX = ['Z360', 'Z362'];
const ANC_DM_DX = ['Z131'];
const ANC_IODINE_DIDSTD = ['201120320037726221781506', '201110100019999920381199', '101110000003082121781506',
    '201110100019999920381341', '201110100019999921881341', '202030120019999920381252', '202030220017745221781506'];
// ANC ครบ 8 ครั้ง: ≤12 · 13–20 · 21–26 · 27–30 · 31–34 · 35–36 · 37–38 · 39–40 สัปดาห์
const ANC_GA_BUCKETS_8 = [[0, 12], [13, 20], [21, 26], [27, 30], [31, 34], [35, 36], [37, 38], [39, 40]];
// ดูแลหลังคลอดครบ 3 ครั้ง: วันที่ 1–7 · 8–15 · 16–42 นับจากวันคลอด
const ANC_PNC_WINDOWS = [[1, 7], [8, 15], [16, 42]];
// เป้าที่ไม่ขึ้นกับปีงบ (ต้นแบบ TARGET_DEFAULTS) · ตัวชี้วัดที่ยิ่งต่ำยิ่งดี
const ANC_TARGETS_ALL = ['early12' => 75, 'visit5' => 75, 'postnatal' => 75, 'quality' => 70, 'dt' => 90, 'dental' => 50,
    'anemia' => 14, 'lbw' => 7];
const ANC_LOWER_BETTER = ['anemia', 'lbw', 'teen_repeat'];
const ANC_VISIT8_TARGETS = [2566 => 50, 2567 => 55, 2568 => 60, 2569 => 65, 2570 => 70];
const ANC_TEEN_REPEAT_TARGETS = [2567 => 13.0, 2568 => 12.5, 2569 => 12.0, 2570 => 11.5];
const ANC_QUALITY_PARTS = ['q_cbc' => 'CBC / Hct / Hb', 'q_thal' => 'คัดกรองธาลัสซีเมีย', 'q_us' => 'อัลตราซาวด์',
    'q_down' => 'คัดกรองดาวน์', 'q_dm' => 'คัดกรองเบาหวาน'];
// ภาวะแทรกซ้อน: Dx (OPD/IPD) รหัส 3 หรือ 4 หลัก ระหว่าง LMP ถึงหลังคลอด 42 วัน
const ANC_DX_HTN = ['O10', 'O11', 'O13', 'O14', 'O15', 'O16'];
const ANC_DX_GDM = ['O244'];
const ANC_DX_PPH = ['O72'];
const ANC_DX_PRETERM = ['O60'];
const ANC_COMPLICATIONS = ['cx_htn' => 'ความดันโลหิตสูง / ครรภ์เป็นพิษ', 'cx_gdm' => 'เบาหวานขณะตั้งครรภ์',
    'cx_preterm' => 'เจ็บครรภ์คลอดก่อนกำหนด', 'cx_pph' => 'ตกเลือดหลังคลอด'];
const ANC_ASPHYXIA_APGAR = 7;          // APGAR นาทีที่ 1 ต่ำกว่านี้ = ขาดออกซิเจน (หรือติ๊ก has_birth_asphyxia)
const ANC_TARGET_ASPHYXIA = 25;        // ต่อ 1,000 เกิดมีชีพ
const ANC_BBA_TYPES = ['3'];           // ipt_labour_infant.infant_delivery_type_id ที่ต้นแบบถือเป็น BBA (ต้องตรวจกับบ้านบึง)
const ANC_PRETERM_DAYS = 37 * 7;
const ANC_IND_LABELS = ['early12' => 'ฝากครรภ์ก่อน 12 wk', 'visit5' => 'ANC ครบ 5 ครั้ง', 'visit8' => 'ANC ครบ 8 ครั้ง',
    'postnatal' => 'ดูแลหลังคลอดครบ 3 ครั้ง', 'quality' => 'ฝากครรภ์คุณภาพ', 'iodine' => 'ยาเม็ดไอโอดีน', 'dt' => 'วัคซีน dT',
    'dental' => 'ตรวจช่องปาก', 'anemia' => 'โลหิตจางใกล้คลอด', 'lbw' => 'ทารก LBW', 'teen_repeat' => 'ตั้งครรภ์ซ้ำในวัยรุ่น'];
const ANC_ACTIONS = ['early12' => 'ค้นหาหญิงตั้งครรภ์ในชุมชนร่วมกับ อสม.', 'visit5' => 'ติดตามนัด ANC ตามช่วงอายุครรภ์',
    'visit8' => 'นัด ANC ให้ครบ 8 ช่วงอายุครรภ์ (≤12 · 13–20 · 21–26 · 27–30 · 31–34 · 35–36 · 37–38 · 39–40 wk)',
    'postnatal' => 'นัดเยี่ยมหลังคลอดวันที่ 1–7, 8–15, 16–42 ร่วมกับ รพ.สต.',
    'quality' => 'ตรวจกิจกรรมที่ขาด (lab / อัลตราซาวด์ / ดาวน์ / เบาหวาน)', 'iodine' => 'จ่ายยาเม็ดเสริมไอโอดีนทุกครั้งที่ฝากครรภ์',
    'dt' => 'ตรวจการบันทึกวัคซีน dT / ฉีดตามนัด', 'dental' => 'ส่งตรวจฟันในวันฝากครรภ์ครั้งแรก',
    'anemia' => 'ติดตามยาเสริมธาตุเหล็กและตรวจ Hct ซ้ำ', 'lbw' => 'ทบทวนรายที่คลอดน้ำหนักน้อย',
    'teen_repeat' => 'ให้คำปรึกษาและคุมกำเนิดกึ่งถาวรหลังคลอด/แท้งในวัยรุ่น'];

// มี Dx ในรหัสชุดนี้ (OPD vstdate หรือ IPD dchdate) ระหว่าง LMP (หรือวันลงทะเบียน) ถึงวันคลอด + 42 วัน (ยังไม่คลอด = d2)
function anc_cx_sql(array $codes, string $d2): string
{
    $in = anc_in_list($codes);
    $win = "BETWEEN coalesce(a.lmp, a.anc_register_date) AND coalesce(a.labor_date + 42, DATE '$d2')";
    return "(EXISTS (SELECT 1 FROM ovstdiag x WHERE x.hn = pt.hn AND (left(x.icd10, 3) IN $in OR left(x.icd10, 4) IN $in)
                AND x.vstdate $win)
            OR EXISTS (SELECT 1 FROM iptdiag y JOIN ipt ON ipt.an = y.an WHERE ipt.hn = pt.hn
                AND (left(y.icd10, 3) IN $in OR left(y.icd10, 4) IN $in) AND ipt.dchdate $win))";
}

// เป้าตามปีงบประมาณ พ.ศ. ของวันสิ้นสุดช่วง (นอกตารางใช้ค่าขอบ)
function anc_fy_target(array $table, string $d2): int|float
{
    $y = (int)substr($d2, 0, 4);
    $fy = ($y + ((int)substr($d2, 5, 2) >= 10 ? 1 : 0)) + 543;
    return $table[min(max($fy, min(array_keys($table))), max(array_keys($table)))];
}

// หลังคลอด: ตัวหาร = คลอดแล้วพ้น 42 วัน ณ d2 · ผ่าน = ได้ดูแลครบทั้ง 3 ช่วงวัน ($days = เลขวันของการดูแล)
function anc_pnc_due(array $p, int $d2): bool
{
    return $p['labor'] !== null && $d2 - $p['labor'] >= ANC_PNC_WINDOWS[2][1];
}

function anc_pnc_ok(array $p, array $days): bool
{
    foreach (ANC_PNC_WINDOWS as [$lo, $hi]) {
        if (!array_filter($days, fn($d) => $d - $p['labor'] >= $lo && $d - $p['labor'] <= $hi)) {
            return false;
        }
    }
    return true;
}

// กิจกรรมฝากครรภ์คุณภาพแต่ละข้อ (q_cbc ผ่านได้ด้วยผล Hct)
function anc_quality_part(array $x, string $k): bool
{
    return $k === 'q_cbc' ? (!empty($x['has_hct']) || !empty($x['q_cbc'])) : !empty($x[$k]);
}

// มาฝากครรภ์ที่ รพ. (ไม่นับ precare) ในช่วง — ตัวหารไอโอดีน
function anc_came_in(array $vs, int $d1, int $d2): bool
{
    foreach ($vs as $v) {
        if (!$v['precare'] && $v['date'] !== null && $v['date'] >= $d1 && $v['date'] <= $d2) {
            return true;
        }
    }
    return false;
}

/*
 * ข้อมูลเพิ่มเติมของครรภ์ที่ anc_load() ดึงมา (คีย์ anc_id เดียวกัน) + ทารก + วันดูแลหลังคลอด
 * คืน [$extra, $infants, $pnc]: extra[anc_id] = flag ต่าง ๆ · infants = [{birth, weight, alive, hn}] · pnc[anc_id] = [เลขวัน...]
 */
function anc_load_extra(PDO $conn, string $d1, string $d2): array
{
    // $d2 ถูกฝังใน SQL เป็น DATE literal — ตรวจรูปแบบอีกชั้นแม้มาจาก bbh_period() แล้ว
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d1) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $d2)) {
        throw new InvalidArgumentException('วันที่ไม่ถูกต้อง');
    }
    $rmin = date('Y-m-d', strtotime($d1 . ' -' . ANC_MAX_PREG_DAYS . ' days'));
    // ช่วงของครรภ์: LMP (หรือวันลงทะเบียน) ถึงวันคลอด (หรือ d2) · +42 วันสำหรับภาวะหลังคลอดไม่ใช้ในชุดนี้
    $s = 'coalesce(a.lmp, a.anc_register_date)';
    $e = "coalesce(a.labor_date, DATE '$d2')";
    $dt = anc_in_list(ANC_DT_VACCINE_CODES);

    $sql = "
        SELECT a.person_anc_id AS anc_id, a.preg_no AS gravida,
               extract(year from age(a.labor_date, coalesce(pt.birthday, pe.birthdate)))::int AS age_labor,
               CASE WHEN a.blood_check2_date IS NOT NULL THEN a.blood_hct_result END AS hct_anc,
               CASE WHEN pe.death = 'Y' THEN pe.death_date END AS death_date, lh.name AS labour_place,
               " . anc_cx_sql(ANC_DX_HTN, $d2) . " AS cx_htn,
               " . anc_cx_sql(ANC_DX_GDM, $d2) . " AS cx_gdm,
               " . anc_cx_sql(ANC_DX_PPH, $d2) . " AS cx_pph,
               " . anc_cx_sql(ANC_DX_PRETERM, $d2) . " AS cx_preterm,
               (a.vaccine_dtanc1_date IS NOT NULL OR a.vaccine_dtanc2_date IS NOT NULL OR a.vaccine_dtanc3_date IS NOT NULL
                OR a.vaccine_dtanc4_date IS NOT NULL OR a.vaccine_dtanc5_date IS NOT NULL
                OR EXISTS (SELECT 1 FROM ovst_vaccine ov
                             JOIN person_vaccine pv ON pv.person_vaccine_id = ov.person_vaccine_id
                             JOIN ovst o ON o.vn = ov.vn
                            WHERE o.hn = pt.hn AND pv.export_vaccine_code IN $dt
                              AND o.vstdate BETWEEN $s AND $e)) AS has_dt,
               (a.dental_tx_date IS NOT NULL
                OR EXISTS (SELECT 1 FROM dtmain t WHERE t.hn = pt.hn AND t.vstdate BETWEEN $s AND $e)) AS has_dental,
               EXISTS (SELECT 1 FROM opitemrece op JOIN drugitems di ON di.icode = op.icode
                        WHERE op.hn = pt.hn AND di.did IN " . anc_in_list(ANC_IODINE_DIDSTD) . "
                          AND op.vstdate BETWEEN $s AND $e) AS has_iodine,
               EXISTS (SELECT 1 FROM lab_head lh JOIN lab_order lo ON lo.lab_order_number = lh.lab_order_number
                        WHERE lh.hn = pt.hn AND lo.lab_items_code::text IN " . anc_in_list(ANC_CBC_LAB_CODES) . "
                          AND lh.order_date BETWEEN $s AND $e) AS q_cbc,
               (EXISTS (SELECT 1 FROM thalassaemia_result tr WHERE tr.thalassaemia_result_id = a.thalassaemia_result_id
                         AND tr.thalassaemia_result_export IN " . anc_in_list(ANC_THAL_EXPORT) . ")
                OR EXISTS (SELECT 1 FROM lab_head lh JOIN lab_order lo ON lo.lab_order_number = lh.lab_order_number
                            WHERE lh.hn = pt.hn AND lo.lab_items_code::text IN " . anc_in_list(ANC_THAL_LAB_CODES) . "
                              AND lh.order_date BETWEEN $s AND $e)) AS q_thal,
               EXISTS (SELECT 1 FROM doctor_operation d JOIN ovst o ON o.vn = d.vn
                        WHERE o.hn = pt.hn AND replace(d.icd9, '.', '') IN " . anc_in_list(ANC_US_ICD9) . "
                          AND o.vstdate BETWEEN $s AND $e) AS q_us,
               EXISTS (SELECT 1 FROM ovstdiag x WHERE x.hn = pt.hn AND x.icd10 IN " . anc_in_list(ANC_DOWN_DX) . "
                        AND x.vstdate BETWEEN $s AND $e) AS q_down,
               (EXISTS (SELECT 1 FROM lab_head lh JOIN lab_order lo ON lo.lab_order_number = lh.lab_order_number
                         WHERE lh.hn = pt.hn AND lo.lab_items_code::text IN " . anc_in_list(ANC_DM_LAB_CODES) . "
                           AND lh.order_date BETWEEN $s AND $e)
                OR EXISTS (SELECT 1 FROM ovstdiag x WHERE x.hn = pt.hn AND x.icd10 IN " . anc_in_list(ANC_DM_DX) . "
                            AND x.vstdate BETWEEN $s AND $e)) AS q_dm
          FROM person_anc a
          JOIN person pe ON pe.person_id = a.person_id
          LEFT JOIN patient pt ON pt.hn = coalesce(nullif(pe.patient_hn, ''),
                (SELECT p2.hn FROM patient p2 WHERE p2.cid = pe.cid AND pe.cid <> '' LIMIT 1))
          LEFT JOIN hospcode lh ON lh.hospcode = a.labour_hospcode
         WHERE a.anc_register_date BETWEEN :rmin AND :d2";
    $st = $conn->prepare($sql);
    $st->execute([':rmin' => $rmin, ':d2' => $d2]);
    $extra = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $extra[(int)$r['anc_id']] = $r + ['hct_late' => null, 'has_hct' => false];
    }

    // Hct: มีผลที่ไม่ว่าง (นับเป็นกิจกรรม CBC) และผลครั้งล่าสุดที่ GA >= 28 (วันที่ใหม่กว่าชนะ เท่ากันเอาแถวแรก)
    $st = $conn->prepare("
        SELECT s.person_anc_id AS anc_id, s.anc_service_date AS d, s.pa_week AS ga, l.anc_lab_result AS res
          FROM person_anc_service s
          JOIN person_anc_lab l ON l.person_anc_service_id = s.person_anc_service_id
          JOIN person_anc a ON a.person_anc_id = s.person_anc_id
         WHERE a.anc_register_date BETWEEN :rmin AND :d2 AND l.anc_lab_id IN (" . implode(',', ANC_HCT_LAB_IDS) . ")");
    $st->execute([':rmin' => $rmin, ':d2' => $d2]);
    $latest = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $id = (int)$r['anc_id'];
        if (!isset($extra[$id])) {
            continue;
        }
        if ($r['res'] !== null && (string)$r['res'] !== '') {
            $extra[$id]['has_hct'] = true;
        }
        $ga = $r['ga'] === null || $r['ga'] === '' ? null : (int)$r['ga'];
        $d = anc_day($r['d']);
        if ($ga !== null && $ga >= ANC_HCT_LATE_GA && $d !== null && (!isset($latest[$id]) || $d > $latest[$id][0])) {
            $latest[$id] = [$d, $r['res']];
        }
    }
    foreach ($extra as $id => &$x) {
        $x['hct_late'] = anc_pos($latest[$id][1] ?? null) ?? anc_pos($x['hct_anc']);
    }
    unset($x);

    // ดูแลหลังคลอด: person_anc_preg_care + post_labor_service1–3_date
    $st = $conn->prepare("
        SELECT c.person_anc_id AS anc_id, c.care_date AS d FROM person_anc_preg_care c
          JOIN person_anc a ON a.person_anc_id = c.person_anc_id WHERE a.anc_register_date BETWEEN :rmin AND :d2
        UNION ALL SELECT person_anc_id, post_labor_service1_date FROM person_anc a WHERE a.anc_register_date BETWEEN :rmin1 AND :d21
        UNION ALL SELECT person_anc_id, post_labor_service2_date FROM person_anc a WHERE a.anc_register_date BETWEEN :rmin2 AND :d22
        UNION ALL SELECT person_anc_id, post_labor_service3_date FROM person_anc a WHERE a.anc_register_date BETWEEN :rmin3 AND :d23");
    $st->execute([':rmin' => $rmin, ':d2' => $d2, ':rmin1' => $rmin, ':d21' => $d2, ':rmin2' => $rmin, ':d22' => $d2,
        ':rmin3' => $rmin, ':d23' => $d2]);
    $pnc = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $d = anc_day($r['d']);
        if ($d !== null) {
            $pnc[(int)$r['anc_id']][] = $d;
        }
    }

    // ทารกเกิดใน รพ. ในช่วง · hn = HN มารดา (ipt_labour) · ตาย = ipt_newborn.dead='Y' หรือสถานะจำหน่ายทารกเป็นรหัสตาย
    $st = $conn->prepare("
        SELECT i.birth_date, i.birth_weight AS weight, coalesce(n.dead, 'N') AS dead, i.infant_dchstts AS dchstts,
               i.apgar_score_min1 AS apgar1, i.has_birth_asphyxia AS asphyxia, i.infant_delivery_type_id AS delivery_type,
               pt.hn
          FROM ipt_labour_infant i
          JOIN ipt_labour il ON il.ipt_labour_id = i.ipt_labour_id
          LEFT JOIN ipt ON ipt.an = il.an
          LEFT JOIN patient pt ON pt.hn = coalesce(nullif(il.hn, ''), ipt.hn)
          LEFT JOIN ipt_newborn n ON n.an = i.infant_an
         WHERE i.birth_date BETWEEN :d1 AND :d2");
    $st->execute([':d1' => $d1, ':d2' => $d2]);
    $infants = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $infants[] = ['birth' => anc_day($r['birth_date']), 'weight' => (int)$r['weight'] ?: null,
            'alive' => strtoupper((string)$r['dead']) !== 'Y' && !in_array(trim((string)$r['dchstts']), ANC_DEAD_DCHSTTS, true),
            'hn' => $r['hn'] ?: null,
            'apgar1' => $r['apgar1'] === null || $r['apgar1'] === '' ? null : (int)$r['apgar1'],
            'asphyxia' => strtoupper(trim((string)$r['asphyxia'])) === 'Y',
            'delivery_type' => trim((string)$r['delivery_type']) ?: null];
    }
    return [$extra, $infants, $pnc];
}

/*
 * ตัวชี้วัด 10 ตัว (metrics.indicators)
 *   ตัวหาร = ครรภ์ที่คลอดในช่วง (scope=area: เฉพาะในเขต) ยกเว้น
 *   postnatal  คลอดในช่วงและพ้นหลังคลอด 42 วัน ณ วันสิ้นสุด · ตัวตั้ง = ได้ดูแลครบทั้ง 3 ช่วงวัน
 *   iodine     ครรภ์ที่มาฝากครรภ์ที่ รพ. ในช่วง (Workload ไม่ใช้ตัวหาร area) · ตัวตั้ง = ได้ยาเม็ดที่มีไอโอดีน
 *   anemia     คลอดและมีผล Hct (ครั้งล่าสุด GA >= 28) · ตัวตั้ง Hct < 33
 *   lbw        ทารกเกิดมีชีพที่ชั่งน้ำหนักได้ ในช่วง (ไม่ใช้ตัวหาร area) · ตัวตั้ง < 2500 g
 *   teen_repeat คลอด/แท้งในช่วงและแม่อายุ < 20 ปี ณ วันนั้น · ตัวตั้ง = ครรภ์ที่ >= 2
 */
function anc_indicators(array $pregs, array $visits, array $extra, array $infants, array $pnc, string $d1s, string $d2s,
                        bool $area, bool $natFiltered): array
{
    $d1 = anc_day($d1s);
    $d2 = anc_day($d2s);
    $in = fn($d) => $d !== null && $d >= $d1 && $d <= $d2;

    $den = 0;
    $cnt = array_fill_keys(['early12', 'visit5', 'visit8', 'quality', 'dt', 'dental', 'anemia_n', 'anemia_d', 'teen_n', 'teen_d',
        'teen_r', 'pnc_n', 'pnc_d', 'iod_n', 'iod_d'], 0);
    $parts = array_fill_keys(array_keys(ANC_QUALITY_PARTS), 0);
    foreach ($pregs as $id => $p) {
        $vs = $visits[$id] ?? [];
        $x = $extra[$id] ?? [];
        if ($in($p['labor']) && (!$area || anc_in_area($p))) {
            $den++;
            $cnt['early12'] += anc_is_early12($p, $vs) ? 1 : 0;
            $cnt['visit5'] += anc_is_5visit($p, $vs) ? 1 : 0;
            $cnt['visit8'] += anc_is_ge_buckets($p, $vs, ANC_GA_BUCKETS_8) ? 1 : 0;
            $all = true;
            foreach (array_keys(ANC_QUALITY_PARTS) as $k) {
                $ok = anc_quality_part($x, $k);
                $parts[$k] += $ok ? 1 : 0;
                $all = $all && $ok;
            }
            $cnt['quality'] += $all ? 1 : 0;
            $cnt['dt'] += !empty($x['has_dt']) ? 1 : 0;
            $cnt['dental'] += !empty($x['has_dental']) ? 1 : 0;
            if (($x['hct_late'] ?? null) !== null) {
                $cnt['anemia_d']++;
                $cnt['anemia_n'] += $x['hct_late'] < ANC_HCT_CUTOFF ? 1 : 0;
            }
            if (($x['age_labor'] ?? null) !== null && (int)$x['age_labor'] < 20) {
                $cnt['teen_d']++;
                $cnt['teen_n'] += (int)($x['gravida'] ?? 0) >= 2 ? 1 : 0;
            }
        }
        // หลังคลอด: ตัวหารกรองด้วย area (pregs ที่ส่งเข้ามา) · คลอดในช่วงและครบ 42 วัน
        if ($in($p['labor']) && (!$area || anc_in_area($p)) && anc_pnc_due($p, $d2)) {
            $cnt['pnc_d']++;
            $cnt['pnc_n'] += anc_pnc_ok($p, $pnc[$id] ?? []) ? 1 : 0;
        }
        if (anc_came_in($vs, $d1, $d2)) {
            $cnt['iod_d']++;
            $cnt['iod_n'] += !empty($x['has_iodine']) ? 1 : 0;
        }
    }

    $hns = $natFiltered ? array_flip(array_map(fn($p) => (string)$p['hn'], array_filter($pregs, fn($p) => $p['hn']))) : null;
    $lbwN = $lbwD = 0;
    foreach ($infants as $i) {
        if ($hns !== null && !isset($hns[(string)$i['hn']])) {
            continue;
        }
        if ($in($i['birth']) && $i['alive'] && $i['weight']) {
            $lbwD++;
            $lbwN += $i['weight'] < ANC_LBW_GRAMS ? 1 : 0;
        }
    }

    $t = fn(string $k) => ANC_TARGETS_ALL[$k] ?? null;
    $r = fn(string $k, int $n, int $d, int|float|null $target = null) => anc_rate($n, $d, $target ?? $t($k), in_array($k, ANC_LOWER_BETTER, true));
    $q = [];
    foreach (ANC_QUALITY_PARTS as $k => $label) {
        $q[] = ['key' => $k, 'label' => $label, 'rate' => anc_rate($parts[$k], $den)];
    }
    return [
        'early12' => $r('early12', $cnt['early12'], $den),
        'visit5' => $r('visit5', $cnt['visit5'], $den),
        'visit8' => $r('visit8', $cnt['visit8'], $den, anc_fy_target(ANC_VISIT8_TARGETS, $d2s)),
        'postnatal' => $r('postnatal', $cnt['pnc_n'], $cnt['pnc_d']),
        'quality' => $r('quality', $cnt['quality'], $den),
        'quality_parts' => $q,
        'iodine' => $r('iodine', $cnt['iod_n'], $cnt['iod_d']),
        'dt' => $r('dt', $cnt['dt'], $den),
        'dental' => $r('dental', $cnt['dental'], $den),
        'anemia' => $r('anemia', $cnt['anemia_n'], $cnt['anemia_d']),
        'lbw' => $r('lbw', $lbwN, $lbwD),
        'teen_repeat' => $r('teen_repeat', $cnt['teen_n'], $cnt['teen_d'], anc_fy_target(ANC_TEEN_REPEAT_TARGETS, $d2s)),
    ];
}

/*
 * กลุ่มอายุแม่ ณ วันลงทะเบียน (metrics.by_age): รายใหม่ + สัดส่วน · ก่อน 12 wk / ครบ 5 ครั้ง / โลหิตจาง ของครรภ์ที่คลอดในช่วง
 * teen แยก < 15 ปี และ 15–19 ปี
 */
function anc_by_age(array $pregs, array $visits, array $extra, string $d1s, string $d2s, bool $area): array
{
    $d1 = anc_day($d1s);
    $d2 = anc_day($d2s);
    $in = fn($d) => $d !== null && $d >= $d1 && $d <= $d2;
    $nNew = 0;
    $g = [];
    foreach (['teen', 'repro', 'elder'] as $k) {
        $g[$k] = ['new' => 0, 'den' => 0, 'e12' => 0, 'v5' => 0, 'an_n' => 0, 'an_d' => 0, 'lt15' => 0, '15_19' => 0];
    }
    foreach ($pregs as $id => $p) {
        $grp = anc_age_group($p['age']);
        if ($in($p['register'])) {
            $nNew++;
            if ($grp) {
                $g[$grp]['new']++;
                if ($grp === 'teen') {
                    $g['teen'][$p['age'] < 15 ? 'lt15' : '15_19']++;
                }
            }
        }
        if ($grp && $in($p['labor']) && (!$area || anc_in_area($p))) {
            $vs = $visits[$id] ?? [];
            $g[$grp]['den']++;
            $g[$grp]['e12'] += anc_is_early12($p, $vs) ? 1 : 0;
            $g[$grp]['v5'] += anc_is_5visit($p, $vs) ? 1 : 0;
            $h = $extra[$id]['hct_late'] ?? null;
            if ($h !== null) {
                $g[$grp]['an_d']++;
                $g[$grp]['an_n'] += $h < ANC_HCT_CUTOFF ? 1 : 0;
            }
        }
    }
    $out = [];
    foreach ($g as $k => $x) {
        $out[$k] = ['new' => $x['new'], 'share' => anc_pct($x['new'], $nNew),
            'early12' => anc_rate($x['e12'], $x['den'], ANC_TARGETS_ALL['early12']),
            'visit5' => anc_rate($x['v5'], $x['den'], ANC_TARGETS_ALL['visit5']),
            'anemia' => anc_rate($x['an_n'], $x['an_d'], ANC_TARGETS_ALL['anemia'], true)];
        if ($k === 'teen') {
            $out[$k]['lt15'] = $x['lt15'];
            $out[$k]['15_19'] = $x['15_19'];
        }
    }
    return $out;
}

/*
 * ผลลัพธ์แม่และทารก (metrics.outcomes)
 *   preterm   ครรภ์ที่คลอดในช่วง (area) ที่มี LMP · คลอดก่อน 37 สัปดาห์นับจาก LMP
 *   asphyxia  ทารกเกิดมีชีพใน รพ. ที่ติ๊กขาดออกซิเจน หรือ APGAR นาทีแรก < 7 · ต่อ 1,000 เกิดมีชีพ
 *   place     คลอดที่ รพ.บ้านบึง (labour_hospcode) / คลอดที่อื่น 5 อันดับ · bba / stillbirth = ทารกเกิดใน รพ.
 *   maternal_death  มารดาเสียชีวิตระหว่างตั้งครรภ์ถึงหลังคลอด 42 วัน และวันตายอยู่ในช่วง (ทุกครรภ์ ไม่ใช้ตัวหาร area)
 *   complications   Dx ระหว่าง LMP ถึงหลังคลอด 42 วัน ในครรภ์ที่คลอด
 */
function anc_outcomes(array $pregs, array $extra, array $infants, string $d1s, string $d2s, bool $area, bool $natFiltered): array
{
    $d1 = anc_day($d1s);
    $d2 = anc_day($d2s);
    $in = fn($d) => $d !== null && $d >= $d1 && $d <= $d2;
    $den = $dated = $pre = $here = $mDeath = 0;
    $cx = array_fill_keys(array_keys(ANC_COMPLICATIONS), 0);
    $places = [];
    foreach ($pregs as $id => $p) {
        $x = $extra[$id] ?? [];
        $dd = anc_day($x['death_date'] ?? null);
        $start = $p['lmp'] ?? $p['register'];
        if ($dd !== null && $start !== null && $start <= $dd
            && ($p['labor'] === null || $dd <= $p['labor'] + ANC_POSTPARTUM_DAYS) && $in($dd)) {
            $mDeath++;
        }
        if (!$in($p['labor']) || ($area && !anc_in_area($p))) {
            continue;
        }
        $den++;
        if ($p['lmp'] !== null) {
            $dated++;
            $pre += $p['labor'] - $p['lmp'] < ANC_PRETERM_DAYS ? 1 : 0;
        }
        foreach ($cx as $k => $_) {
            $cx[$k] += !empty($x[$k]) ? 1 : 0;
        }
        $code = $p['labour_hospcode'];
        if (in_array($code, ANC_HOSPCODES, true)) {
            $here++;
        } elseif ($code) {
            $places[$code] ??= ['code' => $code, 'name' => ($x['labour_place'] ?? '') ?: $code, 'n' => 0];
            $places[$code]['n']++;
        }
    }
    usort($places, fn($a, $b) => [$b['n'], $a['code']] <=> [$a['n'], $b['code']]);

    $hns = $natFiltered ? array_flip(array_map(fn($p) => (string)$p['hn'], array_filter($pregs, fn($p) => $p['hn']))) : null;
    $live = $asph = $bba = $still = 0;
    foreach ($infants as $i) {
        if (($hns !== null && !isset($hns[(string)$i['hn']])) || !$in($i['birth'])) {
            continue;
        }
        $bba += in_array((string)$i['delivery_type'], ANC_BBA_TYPES, true) ? 1 : 0;
        if (!$i['alive']) {
            $still++;
            continue;
        }
        $live++;
        $asph += $i['asphyxia'] || ($i['apgar1'] !== null && $i['apgar1'] < ANC_ASPHYXIA_APGAR) ? 1 : 0;
    }
    $per1000 = $live ? round($asph * 1000 / $live, 1, PHP_ROUND_HALF_EVEN) : null;

    $comp = [];
    foreach (ANC_COMPLICATIONS as $k => $label) {
        $comp[] = ['key' => $k, 'label' => $label] + anc_rate($cx[$k], $den);
    }
    return [
        'preterm' => anc_rate($pre, $dated),
        'asphyxia' => ['num' => $asph, 'den' => $live, 'value' => $per1000, 'target' => ANC_TARGET_ASPHYXIA,
            'ok' => $per1000 === null ? null : $per1000 <= ANC_TARGET_ASPHYXIA, 'low_base' => $live > 0 && $live < ANC_LOW_BASE],
        'place' => ['here' => $here, 'den' => $den, 'pct_here' => anc_pct($here, $den), 'top' => array_slice($places, 0, 5)],
        'bba' => $bba,
        'stillbirth' => $still,
        'maternal_death' => $mDeath,
        'complications' => $comp,
    ];
}

// รายการที่ต้องติดตาม (metrics.alerts — ไม่มีส่วน รพ.สต. เพราะยังไม่มีไฟล์จับคู่หมู่บ้าน)
function anc_alerts(array $ind, array $age, array $outcome): array
{
    $out = [];
    if ($outcome['maternal_death']) {
        $out[] = ['level' => 'red', 'text' => "มารดาเสียชีวิต {$outcome['maternal_death']} ราย",
            'owner' => 'งานแม่และเด็ก / ทีมนำทางคลินิก', 'action' => 'ทบทวนกรณีเสียชีวิต (Dead case conference)'];
    }
    $a = $outcome['asphyxia'];
    if ($a['ok'] === false) {
        $out[] = ['level' => 'red', 'text' => "ทารกแรกเกิดขาดออกซิเจน {$a['value']} ต่อ 1,000 เกิดมีชีพ (เป้า ≤ {$a['target']})",
            'owner' => 'ห้องคลอด', 'action' => 'ทบทวนการเฝ้าระวังระหว่างคลอดและการกู้ชีพทารกแรกเกิด'];
    }
    foreach (ANC_IND_LABELS as $k => $label) {
        $r = $ind[$k] ?? [];
        if (($r['ok'] ?? null) === false) {
            $sign = in_array($k, ANC_LOWER_BETTER, true) ? '≤' : '≥';
            $out[] = ['level' => 'red', 'text' => "$label " . anc_num($r['value']) . "% (เป้า $sign " . anc_num($r['target']) . '%)',
                'owner' => 'งานฝากครรภ์', 'action' => ANC_ACTIONS[$k]];
        }
    }
    if ($age['teen']['lt15']) {
        $out[] = ['level' => 'red', 'text' => "แม่อายุต่ำกว่า 15 ปี {$age['teen']['lt15']} ราย", 'owner' => 'งานแม่และเด็ก / ทีมสหวิชาชีพ',
            'action' => 'ประเมินความปลอดภัยและส่งต่อทีมคุ้มครองเด็ก'];
    }
    return $out;
}

// ตัวเลขในข้อความแบบ Python str(): 63.4 · 12.0 · 75
function anc_num($v): string
{
    return is_float($v) ? (floor($v) == $v ? number_format($v, 1, '.', '') : (string)$v) : (string)$v;
}
