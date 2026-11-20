<?php

/*
 * งานฝากครรภ์ — คัดกรองครรภ์เสี่ยง 27 ข้อ · โรคติดเชื้อทางเพศสัมพันธ์/สารเสพติด/NIPT · OGTT · ติดตามภาวะซีด
 * พอร์ตจากต้นแบบ risk27.py / sti.py / ogtt.py / anemia.py + queries.py (risk_hits, sti_results, ogtt_results, hct_series)
 * ผลเป็นจำนวนนับเท่านั้น · รหัส lab = ค่าที่ต้นแบบเลือกจากชื่อรายการของบ้านบึง (ตรึงไว้ ตรวจ 20 พ.ย. 2569)
 */

require_once __DIR__ . '/anc.php';

/* ================= คัดกรองครรภ์เสี่ยง 27 ข้อ (สมุดสีชมพู) ================= */

const ANC_R27_GROUPS = ['age_body' => 'ปัจจัยด้านอายุและร่างกาย', 'history' => 'ประวัติการตั้งครรภ์และการคลอดในอดีต',
    'medical' => 'โรคประจำตัวและภาวะอายุรกรรม', 'current' => 'ภาวะแทรกซ้อนของการตั้งครรภ์ปัจจุบัน'];
// [key, กลุ่ม, ชื่อ, ข้อของฟอร์ม HOSxP (person_anc_classifying_item_id), คำนวณ?]
const ANC_R27_DEF = [
    ['age_lt17', 'age_body', 'อายุต่ำกว่า 17 ปี', [], true],
    ['age_ge35', 'age_body', 'อายุ 35 ปีขึ้นไป', [], true],
    ['height_lt145', 'age_body', 'ส่วนสูงน้อยกว่า 145 ซม.', [], true],
    ['bmi_low', 'age_body', 'น้ำหนักน้อย (BMI < 18.5)', [], true],
    ['bmi_high', 'age_body', 'น้ำหนักมาก / อ้วน (BMI ≥ 25)', [], true],
    ['recurrent_abortion', 'history', 'เคยแท้งติดต่อกัน ≥ 2 ครั้ง', ['2'], false],
    ['fetal_death', 'history', 'เคยทารกเสียชีวิตในครรภ์ / แรกเกิด', ['1'], false],
    ['prev_lbw', 'history', 'เคยคลอดบุตรน้ำหนัก < 2,500 กรัม', ['3'], false],
    ['prev_macrosomia', 'history', 'เคยคลอดบุตรน้ำหนัก > 4,000 กรัม', ['4'], false],
    ['prev_preterm', 'history', 'เคยคลอดก่อนกำหนด (< 37 สัปดาห์)', [], false],
    ['prev_surgery', 'history', 'เคยผ่าตัดคลอด / ผ่าตัดเนื้องอกมดลูก', ['6'], false],
    ['ectopic_molar', 'history', 'เคยท้องนอกมดลูก / ไข่ปลาอุก', [], false],
    ['prev_pih', 'history', 'เคยครรภ์เป็นพิษ', ['5'], false],
    ['prev_pph', 'history', 'เคยตกเลือดหลังคลอด / รกติดแน่น', [], false],
    ['prev_anomaly', 'history', 'เคยคลอดบุตรพิการแต่กำเนิด / โรคพันธุกรรม', [], false],
    ['chronic_ht', 'medical', 'ความดันโลหิตสูงเรื้อรัง', [], false],
    ['pre_dm', 'medical', 'เบาหวาน (เป็นก่อนตั้งครรภ์)', ['14'], false],
    ['heart', 'medical', 'โรคหัวใจและหลอดเลือด', ['16'], false],
    ['ckd', 'medical', 'โรคไตเรื้อรัง', ['15'], false],
    ['blood', 'medical', 'โรคเลือด / ธาลัสซีเมียรุนแรง', [], false],
    ['infection', 'medical', 'ติดเชื้อเรื้อรัง (HIV, ตับอักเสบบี/ซี, ซิฟิลิส)', [], false],
    ['thyroid', 'medical', 'โรคต่อมไทรอยด์', [], false],
    ['neuro_psych', 'medical', 'ระบบประสาท / จิตเวช (ลมชัก, ซึมเศร้า)', [], false],
    ['autoimmune', 'medical', 'ภูมิแพ้ตนเอง (SLE)', [], false],
    ['twin', 'current', 'ครรภ์แฝด', ['7'], false],
    ['bleeding', 'current', 'เลือดออกทางช่องคลอด / รกเกาะต่ำ / รกลอกตัว', ['11'], false],
    ['fetal_abn', 'current', 'ทารกโตช้า / ท่าผิดปกติ / น้ำคร่ำผิดปกติ', [], false],
];
// ข้อของฟอร์ม HOSxP ที่ไม่ตรงกับ 27 ข้อ — นับเข้า "มีความเสี่ยงอย่างน้อย 1 ข้อ" เท่านั้น
const ANC_R27_EXTRAS = [['extra_10', '10', 'Rh Negative'], ['extra_12', '12', 'ก้อนในอุ้งเชิงกราน'],
    ['extra_13', '13', 'ความดัน Diastolic > 90 mmHg'], ['extra_17', '17', 'ติดยาเสพติด / สุรา'],
    ['extra_18', '18', 'โรคอายุรกรรมอื่น ๆ (โลหิตจาง ไทรอยด์ SLE ฯลฯ)']];
const ANC_R27_DX = [
    'ectopic_molar' => ['O00', 'O01'],
    'chronic_ht' => ['I10', 'I11', 'I12', 'I13', 'I15', 'O10'],
    'pre_dm' => ['E10', 'E11', 'E12', 'E13', 'E14', 'O240', 'O241', 'O242', 'O243'],
    'heart' => ['I05', 'I06', 'I07', 'I08', 'I09', 'I20', 'I21', 'I25', 'I42', 'I48', 'I50', 'O994'],
    'ckd' => ['N18', 'N19', 'O102'],
    'blood' => ['D56', 'D57', 'D58', 'D61'],
    'infection' => ['B20', 'B21', 'B22', 'B23', 'B24', 'Z21', 'B16', 'B17', 'B18', 'B19', 'A50', 'A51', 'A52', 'A53', 'O986'],
    'thyroid' => ['E00', 'E01', 'E02', 'E03', 'E04', 'E05', 'E06', 'E07'],
    'neuro_psych' => ['G40', 'G41', 'F20', 'F21', 'F22', 'F23', 'F25', 'F28', 'F29', 'F30', 'F31', 'F32', 'F33', 'F34', 'F38', 'F39'],
    'autoimmune' => ['M30', 'M31', 'M32', 'M33', 'M34', 'M35'],
    'twin' => ['O30'],
    'bleeding' => ['O20', 'O44', 'O45', 'O46'],
    'fetal_abn' => ['O32', 'O365', 'O40', 'O41'],
];
// คำค้นใน HPI/PMH (regex แบบ PostgreSQL ARE: \y = ขอบคำ) — ประมาณการ ตัดประโยคปฏิเสธออกก่อน
const ANC_R27_TEXT = [
    'recurrent_abortion' => 'แท้งซ้ำ|แท้ง.{0,6}(ติดต่อ|[2-9] ?ครั้ง)|recurrent|habitual',
    'fetal_death' => 'ตายในครรภ์|ตายคลอด|IUFD|stillbirth|เสียชีวิตแรกเกิด|neonatal death',
    'prev_preterm' => 'ก่อนกำหนด|preterm|\yPTL\y|premature',
    'prev_surgery' => '\yC/S\y|\yLSCS\y|ผ่าตัดคลอด|ผ่าท้องคลอด|myomectomy|เนื้องอกมดลูก',
    'ectopic_molar' => 'ท้องนอกมดลูก|ตั้งครรภ์นอกมดลูก|ectopic|\yEP\y|ไข่ปลาอุก|molar|\yGTD\y',
    'prev_pih' => 'ครรภ์เป็นพิษ|\yPIH\y|pre-?eclampsia|eclampsia',
    'prev_pph' => 'ตกเลือดหลังคลอด|\yPPH\y|รกค้าง|รกติด',
    'prev_anomaly' => 'พิการแต่กำเนิด|congenital|ดาวน์|down syndrome|ปากแหว่ง|เพดานโหว่',
    'chronic_ht' => '\yCHT\y|ความดันโลหิตสูง|ความดันสูง|\yHT\y|hypertension',
    'pre_dm' => '\yDM\y|เบาหวาน|T2DM|T1DM',
    'heart' => 'โรคหัวใจ|หัวใจ|\yCHF\y|valv|rheumatic|heart disease',
    'ckd' => '\yCKD\y|โรคไต|ไตวาย|\yCRF\y',
    'blood' => 'ธาลัสซีเมีย|thalass|โลหิตจางรุนแรง|severe anemia|ซิกเคิล',
    'infection' => '\yHIV ?(positive|\+)|HIV\+|ซิฟิลิส|syphilis|ไวรัสตับอักเสบ|hepatitis|\yHBV\y|\yHCV\y|ตับอักเสบ|HBsAg ?(positive|pos|\+)',
    'thyroid' => 'ไทรอยด์|thyroid|graves|hyperT|hypoT',
    'neuro_psych' => 'ลมชัก|epilep|ซึมเศร้า|depress|จิตเวช|psych|schizo|ไบโพลาร์|bipolar|จิตเภท',
    'autoimmune' => '\ySLE\y|lupus|ภูมิแพ้ตนเอง|autoimmune',
    'twin' => 'ครรภ์แฝด|twin|แฝด',
    'bleeding' => 'รกเกาะต่ำ|previa|รกลอกตัว|abruption|\yAPH\y|เลือดออกทางช่องคลอด|PV bleed',
    'fetal_abn' => 'IUGR|\yFGR\y|โตช้า|oligo|polyhydr|น้ำคร่ำ|ท่าก้น|breech|ท่าผิดปกติ|ท่าขวาง|transverse',
];
const ANC_R27_NEGATION = '(ปฏิเสธ|ไม่เคย|ไม่มี|ไม่พบ|ไม่ได้|ไม่|denied|denies|deny|\yno\y|\ynil\y|negative)[^\n;.]*';
// ช่องข้อความที่ค้นของแต่ละหมวด: โรคประจำตัว = PMH · ประวัติอดีต = HPI + PMH · ภาวะปัจจุบัน = HPI
const ANC_R27_TEXT_FIELD = ['age_body' => 'all', 'history' => 'all', 'medical' => 'pmh', 'current' => 'hpi'];
const ANC_RISK_AGE_LOW = 17;
const ANC_RISK_AGE_HIGH = 35;
const ANC_RISK_HEIGHT_CM = 145;
const ANC_RISK_BMI_LOW = 18.5;
const ANC_RISK_BMI_HIGH = 25;

const ANC_RISK_FROM = "
  FROM person_anc a
  JOIN person pe ON pe.person_id = a.person_id
  LEFT JOIN patient pt ON pt.hn = coalesce(nullif(pe.patient_hn, ''),
        (SELECT p2.hn FROM patient p2 WHERE p2.cid = pe.cid AND pe.cid <> '' LIMIT 1))";

function anc_pos(?string $v): ?float
{
    $v = trim((string)$v);
    if ($v === '' || !is_numeric($v)) {
        return null;
    }
    return (float)$v > 0 ? (float)$v : null;
}

// หลักฐานรายครรภ์ (ครรภ์ที่ลงทะเบียนในช่วง): form = ข้อที่ติ๊ก Y · dx = รหัส 3/4 หลัก · text = key ที่พบ · body = (ส่วนสูง, น้ำหนัก) visit แรก
function anc_risk_hits(PDO $conn, string $d1, string $d2): array
{
    $hits = ['form' => [], 'dx' => [], 'text' => [], 'body' => []];
    $st = $conn->prepare("SELECT c.person_anc_id AS anc_id, c.person_anc_classifying_item_id AS item
          FROM person_anc_classifying c JOIN person_anc a ON a.person_anc_id = c.person_anc_id
         WHERE a.anc_register_date BETWEEN :d1 AND :d2 AND c.check_value = 'Y'");
    $st->execute([':d1' => $d1, ':d2' => $d2]);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $hits['form'][(int)$r['anc_id']][trim((string)$r['item'])] = true;
    }

    // Dx: หมวด O นับตั้งแต่ LMP · โรคเรื้อรังนับย้อนหลังได้ทั้งหมด · ทั้งคู่ถึงหลังคลอด 42 วัน
    $codes = [];
    foreach (ANC_R27_DX as $v) {
        $codes = array_merge($codes, $v);
    }
    $in = anc_in_list(array_values(array_unique($codes)));
    $from = ANC_RISK_FROM;
    $st = $conn->prepare("
        SELECT DISTINCT a.person_anc_id AS anc_id, left(x.icd10, 3) AS c3, left(x.icd10, 4) AS c4 $from
          JOIN ovstdiag x ON x.hn = pt.hn
         WHERE a.anc_register_date BETWEEN :d1 AND :d2
           AND (left(x.icd10, 3) IN $in OR left(x.icd10, 4) IN $in)
           AND x.vstdate <= coalesce(a.labor_date + 42, CAST(:d2b AS date))
           AND (left(x.icd10, 1) <> 'O' OR x.vstdate >= coalesce(a.lmp, a.anc_register_date))
        UNION
        SELECT DISTINCT a.person_anc_id, left(y.icd10, 3), left(y.icd10, 4) $from
          JOIN ipt ON ipt.hn = pt.hn
          JOIN iptdiag y ON y.an = ipt.an
         WHERE a.anc_register_date BETWEEN :d1c AND :d2c
           AND (left(y.icd10, 3) IN $in OR left(y.icd10, 4) IN $in)
           AND ipt.dchdate <= coalesce(a.labor_date + 42, CAST(:d2d AS date))
           AND (left(y.icd10, 1) <> 'O' OR ipt.dchdate >= coalesce(a.lmp, a.anc_register_date))");
    $st->execute([':d1' => $d1, ':d2' => $d2, ':d2b' => $d2, ':d1c' => $d1, ':d2c' => $d2, ':d2d' => $d2]);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        foreach ([$r['c3'], $r['c4']] as $c) {
            if ($c) {
                $hits['dx'][(int)$r['anc_id']][$c] = true;
            }
        }
    }

    // ข้อความ HPI/PMH ของ visit ระหว่าง LMP ถึงวันคลอด (ตัดประโยคปฏิเสธก่อน) + ส่วนสูง/น้ำหนัก visit แรกที่มีค่า
    $group = [];
    foreach (ANC_R27_DEF as [$k, $g]) {
        $group[$k] = $g;
    }
    $flags = '';
    $args = [':d1' => $d1, ':d2' => $d2, ':d2e' => $d2, ':neg1' => '(?i)' . ANC_R27_NEGATION,
        ':neg2' => '(?i)' . ANC_R27_NEGATION, ':neg3' => '(?i)' . ANC_R27_NEGATION];
    foreach (ANC_R27_TEXT as $k => $rx) {
        $flags .= ",\n bool_or(q." . ANC_R27_TEXT_FIELD[$group[$k]] . "_txt ~ :t_$k) AS t_$k";
        $args[":t_$k"] = '(?i)(' . $rx . ')';
    }
    $st = $conn->prepare("
        SELECT q.anc_id,
               (array_agg(q.height ORDER BY q.vstdate, q.vn) FILTER (WHERE q.height > 0))[1] AS height,
               (array_agg(q.bw ORDER BY q.vstdate, q.vn) FILTER (WHERE q.bw > 0))[1] AS bw $flags
          FROM (SELECT a.person_anc_id AS anc_id, o.vstdate, o.vn, s.height, s.bw,
                       regexp_replace(concat_ws(' ', s.hpi, s.pmh), :neg1, ' ', 'g') AS all_txt,
                       regexp_replace(coalesce(s.pmh, ''), :neg2, ' ', 'g') AS pmh_txt,
                       regexp_replace(coalesce(s.hpi, ''), :neg3, ' ', 'g') AS hpi_txt $from
                  JOIN ovst o ON o.hn = pt.hn
                       AND o.vstdate BETWEEN coalesce(a.lmp, a.anc_register_date) AND coalesce(a.labor_date, CAST(:d2e AS date))
                  JOIN opdscreen s ON s.vn = o.vn
                 WHERE a.anc_register_date BETWEEN :d1 AND :d2) q
         GROUP BY q.anc_id");
    $st->execute($args);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $id = (int)$r['anc_id'];
        foreach (ANC_R27_TEXT as $k => $_) {
            if ($r["t_$k"] === true || $r["t_$k"] === 't' || $r["t_$k"] === 1) {
                $hits['text'][$id][$k] = true;
            }
        }
        $hits['body'][$id] = [anc_pos($r['height']), anc_pos($r['bw'])];
    }
    return $hits;
}

// [พบ?, มีข้อมูลพอตัดสิน?] ของข้อที่คำนวณ (อายุ ณ วันลงทะเบียน · ส่วนสูง/BMI จาก visit แรก)
function anc_r27_calc(string $key, int $id, array $p, array $hits): array
{
    if ($key === 'age_lt17' || $key === 'age_ge35') {
        if ($p['age'] === null) {
            return [false, false];
        }
        return [$key === 'age_lt17' ? $p['age'] < ANC_RISK_AGE_LOW : $p['age'] >= ANC_RISK_AGE_HIGH, true];
    }
    [$h, $w] = $hits['body'][$id] ?? [null, null];
    $h = $h !== null && $h >= 100 && $h <= 210 ? $h : null;
    $w = $w !== null && $w >= 25 && $w <= 200 ? $w : null;
    if ($key === 'height_lt145') {
        return [$h ? $h < ANC_RISK_HEIGHT_CM : false, $h !== null];
    }
    if ($h === null || $w === null) {
        return [false, false];
    }
    $bmi = $w / (($h / 100) ** 2);
    return [$key === 'bmi_low' ? $bmi < ANC_RISK_BMI_LOW : $bmi >= ANC_RISK_BMI_HIGH, true];
}

/*
 * ฐาน = ฝากครรภ์รายใหม่ในช่วง (ลงทะเบียนในช่วง) · ข้อเดียวพอถือเป็นครรภ์เสี่ยง · แยกไทย / ต่างชาติ (ไม่ใช่ไทย รวมไม่ระบุ)
 * แหล่ง: F ฟอร์ม HOSxP · C คำนวณ · D Dx ICD-10 · T ข้อความ HPI/PMH
 */
function anc_risk27(array $pregs, array $hits, string $d1s, string $d2s): array
{
    $d1 = anc_day($d1s);
    $d2 = anc_day($d2s);
    $new = array_filter($pregs, fn($p) => $p['register'] !== null && $p['register'] >= $d1 && $p['register'] <= $d2);
    $den = count($new);
    $thai = array_filter($new, fn($p) => $p['thai']);
    $denTh = count($thai);
    $split = function (array $ids) use ($thai) {
        $th = count(array_intersect_key($ids, $thai));
        return [$th, count($ids) - $th];
    };
    $flagged = [];
    $items = [];
    foreach (ANC_R27_DEF as $n => [$key, $grp, $label, $form, $calc]) {
        $by = ['F' => 0, 'C' => 0, 'D' => 0, 'T' => 0];
        $found = [];
        $known = 0;
        $dx = ANC_R27_DX[$key] ?? [];
        foreach ($new as $id => $p) {
            $src = [];
            $ok = true;
            if ($calc) {
                [$hit, $ok] = anc_r27_calc($key, $id, $p, $hits);
                if ($hit) {
                    $src['C'] = true;
                }
            }
            if ($form && array_intersect_key($hits['form'][$id] ?? [], array_flip($form))) {
                $src['F'] = true;
            }
            if ($dx && array_intersect_key($hits['dx'][$id] ?? [], array_flip($dx))) {
                $src['D'] = true;
            }
            if (isset($hits['text'][$id][$key])) {
                $src['T'] = true;
            }
            $known += $ok ? 1 : 0;
            if ($src) {
                $found[$id] = true;
                foreach ($src as $s => $_) {
                    $by[$s]++;
                }
                $flagged[$id][$key] = true;
            }
        }
        $avail = [];
        foreach (['F' => (bool)$form, 'C' => $calc, 'D' => (bool)$dx, 'T' => isset(ANC_R27_TEXT[$key])] as $s => $on) {
            if ($on) {
                $avail[] = $s;
            }
        }
        [$nTh, $nFr] = $split($found);
        $items[] = ['num' => $n + 1, 'key' => $key, 'group' => $grp, 'label' => $label, 'form' => $form, 'calc' => $calc,
            'sources' => $avail, 'n' => count($found), 'pct' => anc_pct(count($found), $den), 'known' => $known,
            'n_th' => $nTh, 'n_fr' => $nFr, 'pct_th' => anc_pct($nTh, $denTh), 'pct_fr' => anc_pct($nFr, $den - $denTh),
            'by_source' => (object)array_filter($by)];   // object ให้ JSON เป็น {} แม้ว่าง
    }
    $extras = [];
    foreach (ANC_R27_EXTRAS as [$key, $fid, $label]) {
        $ids = [];
        foreach ($new as $id => $p) {
            if (isset($hits['form'][$id][$fid])) {
                $ids[$id] = true;
                $flagged[$id][$key] = true;
            }
        }
        [$nTh, $nFr] = $split($ids);
        $extras[] = ['key' => $key, 'label' => $label, 'n' => count($ids), 'pct' => anc_pct(count($ids), $den),
            'n_th' => $nTh, 'n_fr' => $nFr, 'pct_th' => anc_pct($nTh, $denTh), 'pct_fr' => anc_pct($nFr, $den - $denTh)];
    }
    $tally = function (array $ids) use ($split, $den, $denTh) {
        [$th, $fr] = $split($ids);
        return ['num' => count($ids), 'den' => $den, 'pct' => anc_pct(count($ids), $den), 'num_th' => $th, 'num_fr' => $fr,
            'pct_th' => anc_pct($th, $denTh), 'pct_fr' => anc_pct($fr, $den - $denTh)];
    };
    $groups = [];
    foreach (ANC_R27_GROUPS as $gk => $gl) {
        $keys = array_column(array_filter(ANC_R27_DEF, fn($d) => $d[1] === $gk), 0);
        $ids = array_filter($flagged, fn($ks) => (bool)array_intersect_key($ks, array_flip($keys)));
        $groups[] = ['key' => $gk, 'label' => $gl] + $tally($ids);
    }
    $top = array_values(array_filter($items, fn($x) => $x['n'] > 0));
    usort($top, fn($a, $b) => [$b['n'], $a['num']] <=> [$a['n'], $b['num']]);
    return ['den' => $den, 'den_th' => $denTh, 'den_fr' => $den - $denTh, 'items' => $items, 'extras' => $extras,
        'groups' => $groups, 'top' => array_slice($top, 0, 10), 'any' => $tally($flagged)];
}

/* ================= โรคติดเชื้อทางเพศสัมพันธ์ / สารเสพติด / NIPT ================= */

// [key, ชื่อ, รหัส lab_items, anc_lab_id]
const ANC_STI_TESTS = [
    ['syphilis', 'ซิฟิลิส (VDRL / TPHA)', ['1106', '1107', '908', '560', '899', '321', '448', '897'], [1, 2]],
    ['hbv', 'ไวรัสตับอักเสบบี (HBs Ag)', ['1051', '328'], []],
    ['hiv', 'HIV (Anti-HIV)', ['1131', '331', '443'], [3, 4]],
    ['amph', 'ปัสสาวะ Amphetamine (Methamphetamine)', ['414', '425', '949', '415'], []],
    ['nipt', 'NIPT (คัดกรองดาวน์ด้วย cfDNA)', ['1103'], []],
];

// ผล lab พิมพ์มือ → pos / neg / unclear (ไม่ชัดไม่นับเป็นลบ)
function anc_sti_classify(?string $text): string
{
    $s = trim(preg_replace('/[^\x20-\x7e\x{0E01}-\x{0E2E}\x{0E40}-\x{0E46}]/u', '', (string)$text) ?? '');
    if ($s === '') {
        return 'unclear';
    }
    if (preg_match('/(*UCP)non\W*r|neg/iu', $s)) {
        return 'neg';
    }
    return preg_match('/(*UCP)pos|r\w{0,2}e?a?c\w*tive|reac|rect/iu', $s) ? 'pos' : 'unclear';
}

// ฐาน = ฝากครรภ์รายใหม่ในช่วง · ผล lab ระหว่าง LMP ถึงวันคลอด + ผลในหน้า ANC · ตรวจหลายครั้ง: บวกครั้งใด = บวก > ลบ > ไม่ชัด
function anc_sti(PDO $conn, array $pregs, string $d1, string $d2): array
{
    $testOf = [];
    $codes = $ancIds = [];
    foreach (ANC_STI_TESTS as [$k, , $labs, $anc]) {
        foreach ($labs as $c) {
            $testOf[$c] = $k;
            $codes[] = $c;
        }
        foreach ($anc as $i) {
            $testOf["anc:$i"] = $k;
            $ancIds[] = (int)$i;
        }
    }
    $from = ANC_RISK_FROM;
    $st = $conn->prepare("
        SELECT a.person_anc_id AS anc_id, lo.lab_items_code::text AS code, lo.lab_order_result AS result $from
          JOIN lab_head lh ON lh.hn = pt.hn
          JOIN lab_order lo ON lo.lab_order_number = lh.lab_order_number
         WHERE pt.hn IS NOT NULL AND a.anc_register_date BETWEEN :d1 AND :d2
           AND lo.lab_items_code::text IN " . anc_in_list($codes) . "
           AND lh.order_date BETWEEN coalesce(a.lmp, a.anc_register_date) AND coalesce(a.labor_date, CAST(:d2b AS date))
        UNION ALL
        SELECT s.person_anc_id, concat('anc:', l.anc_lab_id), l.anc_lab_result
          FROM person_anc_lab l
          JOIN person_anc_service s ON s.person_anc_service_id = l.person_anc_service_id
          JOIN person_anc a ON a.person_anc_id = s.person_anc_id
         WHERE a.anc_register_date BETWEEN :d1c AND :d2c AND l.anc_lab_id IN (" . implode(',', $ancIds) . ")");
    $st->execute([':d1' => $d1, ':d2' => $d2, ':d2b' => $d2, ':d1c' => $d1, ':d2c' => $d2]);
    $rank = ['unclear' => 0, 'neg' => 1, 'pos' => 2];
    $got = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $k = $testOf[(string)$r['code']] ?? null;
        if ($k === null) {
            continue;
        }
        $s = anc_sti_classify($r['result']);
        $id = (int)$r['anc_id'];
        if (!isset($got[$id][$k]) || $rank[$s] > $rank[$got[$id][$k]]) {
            $got[$id][$k] = $s;
        }
    }

    $a = anc_day($d1);
    $b = anc_day($d2);
    $new = array_filter($pregs, fn($p) => $p['register'] !== null && $p['register'] >= $a && $p['register'] <= $b);
    $out = [];
    foreach (ANC_STI_TESTS as [$k, $label]) {
        $tested = [];
        foreach ($new as $id => $_) {
            if (isset($got[$id][$k])) {
                $tested[] = $got[$id][$k];
            }
        }
        $cnt = array_count_values($tested) + ['pos' => 0, 'unclear' => 0];
        $out[] = ['key' => $k, 'label' => $label,
            'tested' => anc_rate_n(count($tested), count($new)),
            'pos' => anc_rate_n($cnt['pos'], count($tested)),
            'unclear' => $cnt['unclear'], 'untested' => count($new) - count($tested)];
    }
    return $out;
}

// อัตราที่ไม่มีเป้า (รูปแบบเดียวกับ metrics._rate)
function anc_rate_n(int $num, int $den): array
{
    return ['num' => $num, 'den' => $den, 'value' => anc_pct($num, $den), 'target' => null, 'ok' => null,
        'low_base' => $den > 0 && $den < 5];
}

/* ================= OGTT ================= */

// [key, ชื่อ, จุดเจาะ, รหัส lab ต่อจุด (null = ไม่มีใน รพ.), เกณฑ์ผิดปกติ mg/dL ต่อจุด]
const ANC_OGTT_TYPES = [
    ['g100', 'OGTT 100 g', ['ก่อนกิน', '1 ชม.', '2 ชม.', '3 ชม.'], ['163', '165', '167', '168'], [95, 180, 155, 140]],
    ['g75', 'OGTT 75 g', ['ก่อนกิน', '1 ชม.', '2 ชม.'], [null, null, null], [92, 180, 153]],
];
const ANC_OGTT_MIN_ABN = 2;

/*
 * ใบสั่ง OGTT ที่สั่งในช่วง (ระหว่าง LMP ถึงวันคลอด) · ครรภ์ตรวจซ้ำ = ชุดล่าสุดของแต่ละชนิด
 * ชุดที่ไม่มีค่าหลังกินที่อ่านเป็นตัวเลขได้ = ไม่นับว่าตรวจ (รหัส 163 = Glucose ทั่วไป ใบสั่ง FBS เดี่ยวไม่ใช่ OGTT)
 * ฐาน = ครรภ์ในทะเบียนที่โหลดมา (ลงทะเบียน 44 สัปดาห์ก่อนต้นช่วงถึงปลายช่วง) · ผิดปกติ ≥ 2 ค่า = เข้าเกณฑ์ GDM
 */
function anc_ogtt(PDO $conn, array $pregs, string $d1, string $d2): array
{
    $slot = [];
    foreach (ANC_OGTT_TYPES as [$k, , , $codes]) {
        foreach ($codes as $i => $c) {
            if ($c !== null) {
                $slot[$c] = [$k, $i];
            }
        }
    }
    $from = ANC_RISK_FROM;
    $st = $conn->prepare("
        SELECT a.person_anc_id AS anc_id, lh.lab_order_number AS ord, lh.order_date AS d, lo.lab_items_code::text AS code,
               lo.lab_order_result AS v $from
          JOIN lab_head lh ON lh.hn = pt.hn
          JOIN lab_order lo ON lo.lab_order_number = lh.lab_order_number
         WHERE lh.order_date BETWEEN :d1 AND :d2 AND lo.lab_items_code::text IN " . anc_in_list(array_keys($slot)) . "
           AND lh.order_date >= coalesce(a.lmp, a.anc_register_date)
           AND lh.order_date <= coalesce(a.labor_date, coalesce(a.lmp, a.anc_register_date) + 300)
         ORDER BY lh.lab_order_number");
    $st->execute([':d1' => $d1, ':d2' => $d2]);
    $npts = [];
    foreach (ANC_OGTT_TYPES as [$k, , $pts]) {
        $npts[$k] = count($pts);
    }
    $sets = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        [$k, $i] = $slot[(string)$r['code']];
        $key = $r['anc_id'] . '|' . $k . '|' . $r['ord'];
        $sets[$key] ??= ['id' => (int)$r['anc_id'], 'k' => $k, 'date' => (string)$r['d'], 'vals' => array_fill(0, $npts[$k], null)];
        $sets[$key]['vals'][$i] = preg_match('/\d+(?:\.\d+)?/', (string)$r['v'], $m) && (float)$m[0] >= 20 && (float)$m[0] <= 800
            ? (float)$m[0] : null;
    }
    $sets = array_values($sets);
    usort($sets, fn($a, $b) => strcmp($a['date'], $b['date']));   // usort ของ PHP 8 คงลำดับเดิมเมื่อวันเท่ากัน
    $res = [];
    foreach ($sets as $s) {
        if (array_filter(array_slice($s['vals'], 1), fn($v) => $v !== null)) {
            $res[$s['id']][$s['k']] = $s['vals'];
        }
    }

    $cut = [];
    foreach (ANC_OGTT_TYPES as [$k, , , , $c]) {
        $cut[$k] = $c;
    }
    $order = ['ok' => 0, 'one' => 1, 'abn' => 2];
    $status = function (int $id, string $key) use ($res, $cut, $order) {
        $best = null;
        foreach ($key === 'all' ? array_keys($cut) : [$key] as $k) {
            if (!isset($res[$id][$k])) {
                continue;
            }
            $n = 0;
            foreach ($res[$id][$k] as $i => $v) {
                $n += $v !== null && $v >= $cut[$k][$i] ? 1 : 0;
            }
            $st = $n >= ANC_OGTT_MIN_ABN ? 'abn' : ($n ? 'one' : 'ok');
            $best = $best === null || $order[$st] > $order[$best] ? $st : $best;
        }
        return $best;
    };
    $rows = [];
    $types = array_merge(array_map(fn($t) => [$t[0], $t[1]], ANC_OGTT_TYPES), [['all', 'รวมทุกชนิด (นับคนละครั้ง)']]);
    foreach ($types as [$k, $label]) {
        $tested = [];
        foreach ($pregs as $id => $_) {
            $s = $status($id, $k);
            if ($s !== null) {
                $tested[] = $s;
            }
        }
        $c = array_count_values($tested) + ['abn' => 0, 'one' => 0];
        $rows[] = ['key' => $k, 'label' => $label, 'tested' => count($tested), 'abn' => $c['abn'], 'one' => $c['one'],
            'rate' => anc_rate_n($c['abn'], count($tested))];
    }
    $points = [];
    foreach (ANC_OGTT_TYPES as [$k, , $pts]) {
        $points[$k] = $pts;
    }
    return ['rows' => $rows, 'min_abn' => ANC_OGTT_MIN_ABN, 'cuts' => $cut, 'points' => $points];
}

/* ================= ติดตามภาวะซีด ================= */

const ANC_HCT_SERIES_LABS = ['447', '2'];   // lab_items Hct / Hematocrit
const ANC_HCT_SERIES_ANC = [5, 6];          // anc_lab_id ของผล Hct ในหน้า ANC
const ANC_HCT_CUT = 33.0;
const ANC_HCT_CUT_T2 = 33.0;                // ไตรมาส 2 (GA 14–27) ตั้งต่างได้ ปัจจุบันเท่ากัน
const ANC_HCT_IMPROVE = 3.0;
const ANC_ANEMIA_GROUPS = ['recovered' => 'หายซีด', 'improving' => 'ดีขึ้นแต่ยังซีด', 'not_improved' => 'ซีดไม่ดีขึ้น',
    'new' => 'ซีดใหม่ระหว่างตั้งครรภ์', 'single' => 'ซีด · เจาะครั้งเดียว'];

/*
 * ฐาน = ครรภ์ที่คลอดในช่วง (scope area: ในเขต) · ผล Hct ทุกครั้ง (lab_order + หน้า ANC วันเดียวกันใช้ผล lab) ค่า 10–60%
 * เทียบครั้งแรกกับครั้งล่าสุด: หายซีด / ดีขึ้น (+3 จุด) / ไม่ดีขึ้น / ซีดใหม่ / ซีดแต่เจาะครั้งเดียว
 */
function anc_anemia(PDO $conn, array $pregs, string $d1s, string $d2s, bool $area): array
{
    $st = $conn->prepare("
        SELECT a.person_anc_id AS anc_id, lh.order_date AS d, a.lmp, lo.lab_order_result AS v, 'lab' AS src
          FROM person_anc a
          JOIN person pe ON pe.person_id = a.person_id
          JOIN patient pt ON pt.hn = coalesce(nullif(pe.patient_hn, ''),
                (SELECT p2.hn FROM patient p2 WHERE p2.cid = pe.cid AND pe.cid <> '' LIMIT 1))
          JOIN lab_head lh ON lh.hn = pt.hn
          JOIN lab_order lo ON lo.lab_order_number = lh.lab_order_number
         WHERE a.labor_date BETWEEN :d1 AND :d2 AND lo.lab_items_code::text IN " . anc_in_list(ANC_HCT_SERIES_LABS) . "
           AND lh.order_date BETWEEN coalesce(a.lmp, a.anc_register_date) AND a.labor_date
        UNION ALL
        SELECT a.person_anc_id, s.anc_service_date, a.lmp, l.anc_lab_result, 'anc'
          FROM person_anc_lab l
          JOIN person_anc_service s ON s.person_anc_service_id = l.person_anc_service_id
          JOIN person_anc a ON a.person_anc_id = s.person_anc_id
         WHERE a.labor_date BETWEEN :d1b AND :d2b AND l.anc_lab_id IN (" . implode(',', ANC_HCT_SERIES_ANC) . ")");
    $st->execute([':d1' => $d1s, ':d2' => $d2s, ':d1b' => $d1s, ':d2b' => $d2s]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    usort($rows, fn($a, $b) => ($a['src'] !== 'lab') <=> ($b['src'] !== 'lab'));   // lab ก่อน (คงลำดับเดิม)
    $series = [];
    foreach ($rows as $r) {
        $v = anc_pos($r['v']);
        $d = anc_day($r['d']);
        if ($v === null || $d === null || $v < 10 || $v > 60) {
            continue;
        }
        $lmp = anc_day($r['lmp']);
        $series[(int)$r['anc_id']][$d] ??= ['ga' => $lmp === null ? null : (int)floor(($d - $lmp) / 7), 'hct' => $v];
    }

    $anemic = fn($x) => $x['hct'] < ($x['ga'] !== null && $x['ga'] >= 14 && $x['ga'] <= 27 ? ANC_HCT_CUT_T2 : ANC_HCT_CUT);
    $d1 = anc_day($d1s);
    $d2 = anc_day($d2s);
    $den = 0;
    $got = [];
    foreach ($pregs as $id => $p) {
        if ($p['labor'] === null || $p['labor'] < $d1 || $p['labor'] > $d2 || ($area && !anc_in_area($p))) {
            continue;
        }
        $den++;
        $s = $series[$id] ?? [];
        if (!$s) {
            continue;
        }
        ksort($s);
        $s = array_values($s);
        if (!array_filter($s, $anemic)) {
            $got[] = 'normal';
        } elseif (count($s) === 1) {
            $got[] = 'single';
        } elseif (!$anemic(end($s))) {
            $got[] = 'recovered';
        } elseif (!$anemic($s[0])) {
            $got[] = 'new';
        } else {
            $got[] = end($s)['hct'] - $s[0]['hct'] >= ANC_HCT_IMPROVE ? 'improving' : 'not_improved';
        }
    }
    $c = array_count_values($got);
    $groups = [];
    foreach (ANC_ANEMIA_GROUPS as $k => $label) {
        $groups[] = ['key' => $k, 'label' => $label, 'n' => $c[$k] ?? 0];
    }
    return ['tested' => anc_rate_n(count($got), $den), 'untested' => $den - count($got),
        'anemic' => count($got) - ($c['normal'] ?? 0), 'normal' => $c['normal'] ?? 0, 'groups' => $groups];
}
