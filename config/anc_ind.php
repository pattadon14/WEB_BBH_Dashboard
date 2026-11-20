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
const ANC_LOW_BASE = 5;

// รายการค่าคงที่ใส่ใน IN (...) — ค่าทั้งหมดมาจากค่าคงที่ข้างบน ไม่ใช่ input ผู้ใช้
function anc_in_list(array $xs): string
{
    return '(' . implode(', ', array_map(fn($x) => "'" . str_replace("'", "''", (string)$x) . "'", $xs)) . ')';
}

// เป้าตามปีงบประมาณ พ.ศ. ของวันสิ้นสุดช่วง (นอกตารางใช้ค่าขอบ)
function anc_fy_target(array $table, string $d2): float
{
    $y = (int)substr($d2, 0, 4);
    $fy = ($y + ((int)substr($d2, 5, 2) >= 10 ? 1 : 0)) + 543;
    return $table[min(max($fy, min(array_keys($table))), max(array_keys($table)))];
}

function anc_rate_t(int $num, int $den, ?float $target, bool $lower): array
{
    $value = anc_pct($num, $den);
    return ['num' => $num, 'den' => $den, 'value' => $value, 'target' => $target,
            'ok' => $value === null || $target === null ? null : ($lower ? $value <= $target : $value >= $target),
            'low_base' => $den > 0 && $den < ANC_LOW_BASE];
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
        $x['hct_late'] = anc_pos_float($latest[$id][1] ?? null) ?? anc_pos_float($x['hct_anc']);
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
            'hn' => $r['hn'] ?: null];
    }
    return [$extra, $infants, $pnc];
}

// ค่าตัวเลขที่ > 0 (ผล Hct ว่าง/0/ไม่ใช่ตัวเลข = ไม่มีผล)
function anc_pos_float($v): ?float
{
    if ($v === null || !is_numeric(trim((string)$v))) {
        return null;
    }
    $f = (float)trim((string)$v);
    return $f > 0 ? $f : null;
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
                $ok = $k === 'q_cbc' ? (!empty($x['has_hct']) || !empty($x['q_cbc'])) : !empty($x[$k]);
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
        if ($in($p['labor']) && (!$area || anc_in_area($p)) && $d2 - $p['labor'] >= ANC_PNC_WINDOWS[2][1]) {
            $cnt['pnc_d']++;
            $days = array_map(fn($d) => $d - $p['labor'], $pnc[$id] ?? []);
            $okAll = true;
            foreach (ANC_PNC_WINDOWS as [$lo, $hi]) {
                $okAll = $okAll && count(array_filter($days, fn($v) => $v >= $lo && $v <= $hi)) > 0;
            }
            $cnt['pnc_n'] += $okAll ? 1 : 0;
        }
        // ไอโอดีน: มาฝากครรภ์ที่ รพ. (ไม่นับ precare) ในช่วง
        foreach ($vs as $v) {
            if (!$v['precare'] && $in($v['date'])) {
                $cnt['iod_d']++;
                $cnt['iod_n'] += !empty($x['has_iodine']) ? 1 : 0;
                break;
            }
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
    $r = fn(string $k, int $n, int $d, ?float $target = null) => anc_rate_t($n, $d, $target ?? $t($k), in_array($k, ANC_LOWER_BETTER, true));
    $q = [];
    foreach (ANC_QUALITY_PARTS as $k => $label) {
        $q[] = ['key' => $k, 'label' => $label, 'rate' => anc_rate_t($parts[$k], $den, null, false)];
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

// มี visit (รวม precare) ครบทุกช่วงอายุครรภ์ของชุดที่ส่งมา (ใช้กับ ANC ครบ 8 ครั้ง)
function anc_is_ge_buckets(array $p, array $vs, array $buckets): bool
{
    $have = [];
    foreach ($vs as $v) {
        $ga = anc_visit_ga($v, $p['lmp']);
        foreach ($buckets as $i => [$lo, $hi]) {
            if ($ga !== null && $ga >= $lo && $ga <= $hi) {
                $have[$i] = true;
                break;
            }
        }
    }
    return count($have) === count($buckets);
}
