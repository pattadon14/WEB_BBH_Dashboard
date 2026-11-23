<?php

/*
 * งานฝากครรภ์ — ส่งต่องานแม่และเด็ก (metrics.select_refers + refer_summary) · ต่อจาก config/anc.php
 * refer ที่นับ = referout ในช่วง ที่อยู่ในช่วงครรภ์ของทะเบียน (LMP ถึงคลอด + 42 วัน / LMP + 44 สัปดาห์ถ้ายังไม่คลอด)
 *               หรือ Dx หลักขึ้นต้น O / Z32–Z36 · นับครั้ง (referout_id)
 * กลุ่มอายุ = อายุแม่ ณ วันลงทะเบียนของครรภ์ที่ตรง (ไม่มีครรภ์ = อายุ ณ วัน refer)
 */

require_once __DIR__ . '/anc.php';

// ไม่จัดอันดับใน Top 10 Dx (Z32 ยังไม่ยืนยันการตั้งครรภ์, Z34 มาฝากครรภ์ปกติ) — ยังนับใน total และตาราง
const ANC_REFER_DX_EXCLUDE = ['Z32', 'Z34'];
const ANC_CHAPTER_NAMES = [
    'A' => 'โรคติดเชื้อและปรสิต', 'B' => 'โรคติดเชื้อและปรสิต', 'C' => 'เนื้องอก',
    'D' => 'เนื้องอก / โรคเลือดและภูมิคุ้มกัน', 'E' => 'โรคต่อมไร้ท่อ โภชนาการ และเมแทบอลิซึม',
    'F' => 'ความผิดปกติทางจิตและพฤติกรรม', 'G' => 'โรคระบบประสาท', 'H' => 'โรคตาและหู',
    'I' => 'โรคระบบไหลเวียนเลือด', 'J' => 'โรคระบบหายใจ', 'K' => 'โรคระบบย่อยอาหาร', 'L' => 'โรคผิวหนัง',
    'M' => 'โรคระบบกล้ามเนื้อและกระดูก', 'N' => 'โรคระบบสืบพันธุ์และทางเดินปัสสาวะ', 'O' => 'ตั้งครรภ์/คลอด',
    'P' => 'ภาวะในระยะปริกำเนิด', 'Q' => 'รูปผิดปกติแต่กำเนิด', 'R' => 'อาการและอาการแสดงผิดปกติ',
    'S' => 'การบาดเจ็บ', 'T' => 'การบาดเจ็บ / การเป็นพิษ', 'U' => 'รหัสใช้เป็นการเฉพาะ',
    'V' => 'สาเหตุภายนอก', 'W' => 'สาเหตุภายนอก', 'X' => 'สาเหตุภายนอก', 'Y' => 'สาเหตุภายนอก',
    'Z' => 'ปัจจัยที่มีผลต่อสถานะสุขภาพ', 'Z3x' => 'ดูแลครรภ์ (Z32–Z36)', 'ไม่ระบุ' => 'ไม่มี Dx หลัก',
];

function anc_dx3(?string $pdx): string
{
    return substr(strtoupper(trim(str_replace('.', '', (string)$pdx))), 0, 3);
}

function anc_dx_chapter(?string $pdx): string
{
    $code = strtoupper(trim(str_replace('.', '', (string)$pdx)));
    if ($code === '') {
        return 'ไม่ระบุ';
    }
    if ($code[0] === 'O') {
        return 'O';
    }
    return in_array(substr($code, 0, 3), ['Z32', 'Z33', 'Z34', 'Z35', 'Z36'], true) ? 'Z3x' : $code[0];
}

// referout ในช่วง + ปลายทาง / ชื่อ Dx / อายุ ณ วัน refer / นาทีจากรับบริการถึง refer
function anc_load_refers(PDO $conn, string $d1, string $d2): array
{
    $st = $conn->prepare("
        SELECT r.referout_id, r.refer_date, r.hn, r.pdx, coalesce(i3.name, i.name) AS dx_name,
               coalesce(nullif(h.hospcode_5_digit, ''), nullif(r.refer_hospcode, ''), r.hospcode) AS dest, h.name AS dest_name,
               extract(year from age(r.refer_date, pt.birthday))::int AS age,
               trunc(extract(epoch from (r.refer_date + r.refer_time) - (o.vstdate + o.vsttime)) / 60)::int AS minutes
          FROM referout r
          LEFT JOIN patient pt ON pt.hn = r.hn
          LEFT JOIN hospcode h ON h.hospcode = coalesce(nullif(r.refer_hospcode, ''), r.hospcode)
          LEFT JOIN icd101 i ON i.code = r.pdx
          LEFT JOIN icd101 i3 ON i3.code = left(replace(r.pdx, '.', ''), 3)
          LEFT JOIN ovst o ON o.vn = r.vn
         WHERE r.refer_date BETWEEN :d1 AND :d2
         ORDER BY r.referout_id");
    // ชื่อ Dx = ชื่อหมวด 3 หลักใน icd101 (ต้นแบบใช้ชื่อรหัสย่อยของแถวแรกที่เจอ ซึ่งเปลี่ยนตามลำดับแถว) — ไม่มีหมวด 3 หลักใช้ชื่อรหัสเต็ม
    $st->execute([':d1' => $d1, ':d2' => $d2]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

// refer ที่นับ (ใช้ร่วมกับหน้ารายชื่อ) — เพิ่ม age (อายุที่ใช้จัดกลุ่ม), group, reason (registry / dx / both)
function anc_refer_list(array $pregs, array $refers, bool $natFiltered): array
{
    $win = [];
    foreach ($pregs as $p) {
        if ($p['lmp'] !== null && $p['hn']) {
            $win[$p['hn']][] = [$p['lmp'], $p['labor'] !== null ? $p['labor'] + ANC_POSTPARTUM_DAYS : $p['lmp'] + ANC_MAX_PREG_DAYS, $p['age']];
        }
    }
    $hns = $natFiltered ? array_flip(array_map(fn($p) => (string)$p['hn'], $pregs)) : null;
    $seen = $out = [];
    foreach ($refers as $r) {
        $rid = (int)$r['referout_id'];
        if (isset($seen[$rid])) {
            continue;
        }
        $day = anc_day($r['refer_date']);
        $hit = null;
        foreach ($win[(string)$r['hn']] ?? [] as $w) {
            if ($day !== null && $day >= $w[0] && $day <= $w[1]) {
                $hit = $w;
                break;
            }
        }
        $code = strtoupper(trim(str_replace('.', '', (string)$r['pdx'])));
        $isDx = $code !== '' && (bool)array_filter(ANC_REFER_DX, fn($x) => str_starts_with($code, $x));
        if (!$hit && !$isDx) {
            continue;
        }
        $seen[$rid] = true;
        if ($hns !== null && !isset($hns[(string)$r['hn']])) {
            continue;
        }
        $age = $hit && $hit[2] !== null ? $hit[2] : ($r['age'] === null ? null : (int)$r['age']);
        $out[] = $r + ['day' => $day, 'age_used' => $age, 'group' => anc_age_group($age),
            'reason' => $hit && $isDx ? 'ทะเบียน + Dx' : ($hit ? 'อยู่ในทะเบียนฝากครรภ์' : 'Dx งานแม่และเด็ก')];
    }
    return $out;
}

function anc_refer_summary(array $pregs, array $refers, string $d1s, string $d2s, bool $natFiltered): array
{
    $refers = anc_refer_list($pregs, $refers, $natFiltered);
    $monthly = [];
    for ($m = new DateTime(substr($d1s, 0, 7) . '-01'); $m->format('Y-m') <= substr($d2s, 0, 7); $m->modify('+1 month')) {
        $monthly[$m->format('Y-m')] = ['month' => $m->format('Y-m'), 'teen' => 0, 'repro' => 0, 'elder' => 0, 'unknown' => 0];
    }
    $dx = $dest = $table = [];
    $total = $excluded = 0;
    foreach ($refers as $r) {
        $total++;
        $month = gmdate('Y-m', $r['day'] * 86400);
        if (isset($monthly[$month])) {
            $monthly[$month][$r['group'] ?? 'unknown']++;
        }
        $c3 = anc_dx3($r['pdx']);
        if ($c3 !== '' && array_filter(ANC_REFER_DX_EXCLUDE, fn($x) => str_starts_with($c3, $x))) {
            $excluded++;
        } elseif ($c3 !== '') {
            $dx[$c3] ??= ['code3' => $c3, 'name' => null, 'n' => 0];
            $dx[$c3]['n']++;
            $dx[$c3]['name'] = $dx[$c3]['name'] ?: ($r['dx_name'] ?: null);
        }
        $dcode = (string)($r['dest'] ?? '');
        $dname = ($r['dest_name'] ?: $dcode) ?: 'ไม่ระบุ';
        $dest[$dcode] ??= ['code' => $dcode, 'name' => $dname, 'n' => 0];
        $dest[$dcode]['n']++;
        $min = $r['minutes'] === null ? null : (int)$r['minutes'];
        $table[$month . "\0" . anc_dx_chapter($r['pdx']) . "\0" . $dname][] = [$c3, $r['dx_name'] ?: null, $min !== null && $min >= 0 ? $min : null];
    }

    $top = array_values($dx);
    usort($top, fn($a, $b) => [$b['n'], $a['code3']] <=> [$a['n'], $b['code3']]);
    $top = array_slice($top, 0, 10);
    foreach ($top as &$t) {
        $t['pct'] = anc_pct($t['n'], $total - $excluded);
    }
    unset($t);
    $dest = array_values($dest);
    usort($dest, fn($a, $b) => [$b['n'], $a['code']] <=> [$a['n'], $b['code']]);

    ksort($table, SORT_STRING);
    $rows = [];
    foreach ($table as $key => $rs) {
        [$month, $chapter, $dname] = explode("\0", $key);
        $codes = [];
        $mins = [];
        foreach ($rs as [$c3, $name, $min]) {
            if ($min !== null) {
                $mins[] = $min;
            }
            if ($c3 !== '') {
                $codes[$c3] ??= ['code3' => $c3, 'name' => $name, 'n' => 0];
                $codes[$c3]['n']++;
                $codes[$c3]['name'] = $codes[$c3]['name'] ?: $name;
            }
        }
        $codes = array_values($codes);
        usort($codes, fn($a, $b) => [$b['n'], $a['code3']] <=> [$a['n'], $b['code3']]);
        $rows[] = ['month' => $month, 'chapter' => $chapter, 'chapter_name' => ANC_CHAPTER_NAMES[$chapter] ?? $chapter,
            'dx' => $codes, 'dest_name' => $dname, 'n' => count($rs),
            'avg_min' => $mins ? round(array_sum($mins) / count($mins), 1, PHP_ROUND_HALF_EVEN) : null];
    }
    return ['monthly' => array_values($monthly), 'top_dx' => $top, 'dest' => $dest, 'table' => $rows, 'total' => $total,
        'dx_excluded' => $excluded, 'dx_exclude' => ANC_REFER_DX_EXCLUDE];
}
