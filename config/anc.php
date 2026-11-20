<?php

/*
 * งานฝากครรภ์ (ANC) — ค่าและการคำนวณร่วมของ api/anc_*.php
 * พอร์ตจากต้นแบบ ANC Dashboard (Flask: queries.py + metrics.py) ที่เทียบกับ HOSxP จริงแล้ว — นิยามต้องตรงกับ metrics.py
 *
 * หน่วยนับหลัก = ครรภ์ (person_anc_id) · ร้อยละส่วนใหญ่หารด้วย "ครรภ์ที่คลอดในช่วง" (ต้องดูย้อนทั้งครรภ์)
 * ดึงแถวระดับครรภ์/visit/refer แล้วคำนวณใน PHP แบบเดียวกับต้นแบบ — ไม่มีชื่อ/CID (hn ใช้จับคู่ refer ภายในเท่านั้น)
 */

require_once __DIR__ . '/period.php';
require_once __DIR__ . '/ipd.php';   // ipd_cached() / ipd_fail(): cache ไฟล์และ error กลางแบบเดียวกับหน้า IPD

// เขตบ้านบึง (ตัวหาร scope=area) · สัญชาติไทย
const ANC_CHWPART = '20';
const ANC_AMPPART = '02';
const ANC_THAI_NATIONALITY = '99';
// รหัสโรงพยาบาลบ้านบึง: person_anc.labour_hospcode บันทึกทั้ง '10817' และ 'EA0010817' (ผู้ใช้ยืนยัน 20 พ.ย. 2569)
const ANC_HOSPCODES = ['10817', 'EA0010817'];
// ครรภ์ที่ลงทะเบียนก่อนต้นช่วงเกินนี้ คลอดในช่วงไม่ได้แล้ว · ครรภ์ไม่ปิดเกินนี้ (นับจาก LMP) ไม่ถือว่ากำลังตั้งครรภ์
const ANC_MAX_PREG_DAYS = 44 * 7;
const ANC_POSTPARTUM_DAYS = 42;
// ANC ครบ 5 ครั้ง: ช่วงอายุครรภ์ (สัปดาห์) ≤12 · 13–19 · 20–25 · 26–31 · 32–40
const ANC_GA_BUCKETS = [[0, 12], [13, 19], [20, 25], [26, 31], [32, 40]];
// refer งานแม่และเด็ก = อยู่ในช่วงตั้งครรภ์–หลังคลอด 42 วันของครรภ์ในทะเบียน หรือ Dx หลักขึ้นต้นด้วยรหัสเหล่านี้
const ANC_REFER_DX = ['O', 'Z32', 'Z33', 'Z34', 'Z35', 'Z36'];
const ANC_LABOR_WARN_PCT = 80;
// เป้าตามเอกสาร KPI (ต้นแบบ .env ANC_TARGET_*)
const ANC_TARGETS = ['early12' => 75, 'visit5' => 75];

/* ---------- ตัวช่วย ---------- */

// วันที่ 'YYYY-MM-DD' → เลขวัน (ใช้ลบหาจำนวนวัน) · null คงเป็น null
function anc_day(?string $d): ?int
{
    return $d === null || $d === '' ? null : intdiv(strtotime(substr($d, 0, 10) . ' 00:00:00 UTC'), 86400);
}

function anc_pct(int $num, int $den): ?float
{
    return $den ? round($num * 100 / $den, 1, PHP_ROUND_HALF_EVEN) : null;
}

function anc_rate(string $key, int $num, int $den): array
{
    $value = anc_pct($num, $den);
    $target = ANC_TARGETS[$key] ?? null;
    return ['num' => $num, 'den' => $den, 'value' => $value, 'target' => $target,
            'ok' => $value === null || $target === null ? null : $value >= $target];
}

// หมู่บ้าน 8 หลักจากที่อยู่ใน patient (จังหวัด อำเภอ ตำบล หมู่ ส่วนละ 2 หลัก) · ไม่ครบใช้ village.village_code ของ person
function anc_village(array $r): ?string
{
    $parts = [];
    foreach (['chwpart', 'amppart', 'tmbpart', 'moopart'] as $k) {
        $parts[] = trim((string)($r[$k] ?? ''));
    }
    if (count(array_filter($parts, 'ctype_digit')) === 4) {
        return implode('', array_map(fn($p) => substr(str_pad($p, 2, '0', STR_PAD_LEFT), -2), $parts));
    }
    $v = trim((string)($r['v_code'] ?? ''));
    return strlen($v) === 8 && ctype_digit($v) ? $v : null;
}

// อายุครรภ์ (สัปดาห์) ของ visit: pa_week ถ้ามี (0 = ไม่มี) ไม่งั้นคิดจาก LMP แบบปัดลง
function anc_visit_ga(array $v, ?int $lmp): ?int
{
    if ($v['ga'] !== null) {
        return $v['ga'];
    }
    return $lmp === null || $v['date'] === null ? null : (int)floor(($v['date'] - $lmp) / 7);
}

/* ---------- ดึงข้อมูล (queries.py: PREGNANCIES / VISITS / REFERS เฉพาะคอลัมน์ที่ใช้) ---------- */

/*
 * ครรภ์ที่ลงทะเบียนตั้งแต่ d1 - 44 สัปดาห์ ถึง d2 · ต้องมีแถวใน person (ตรงกับต้นแบบ — ครรภ์ไม่มี person ถูกตัด)
 * nat: 'th' = หญิงไทย, 'fr' = ไม่ใช่ไทย (รวมไม่ระบุ), '' = ทั้งหมด
 */
function anc_load(PDO $conn, string $d1, string $d2, string $nat): array
{
    $rmin = date('Y-m-d', strtotime($d1 . ' -' . ANC_MAX_PREG_DAYS . ' days'));

    $st = $conn->prepare("
        SELECT a.person_anc_id AS anc_id, pt.hn,
               coalesce(nullif(pt.nationality, ''), pe.nationality) AS nationality, a.labour_hospcode,
               pt.chwpart, pt.amppart, pt.tmbpart, pt.moopart, v.village_code AS v_code,
               a.anc_register_date AS register_date, a.lmp, a.labor_date
          FROM person_anc a
          JOIN person pe ON pe.person_id = a.person_id
          LEFT JOIN patient pt ON pt.hn = coalesce(nullif(pe.patient_hn, ''),
                (SELECT p2.hn FROM patient p2 WHERE p2.cid = pe.cid AND pe.cid <> '' LIMIT 1))
          LEFT JOIN village v ON v.village_id = pe.village_id
         WHERE a.anc_register_date BETWEEN :rmin AND :d2");
    $st->execute([':rmin' => $rmin, ':d2' => $d2]);

    $pregs = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $thai = trim((string)$r['nationality']) === ANC_THAI_NATIONALITY;
        if (($nat === 'th' && !$thai) || ($nat === 'fr' && $thai)) {
            continue;
        }
        $id = (int)$r['anc_id'];
        $pregs[$id] = [
            'hn' => $r['hn'] ?: null,
            'thai' => $thai,
            'village' => anc_village($r),
            'labour_hospcode' => trim((string)$r['labour_hospcode']) ?: null,
            'register' => anc_day($r['register_date']),
            'lmp' => anc_day($r['lmp']),
            'labor' => anc_day($r['labor_date']),
        ];
    }

    // visit ที่ รพ. (person_anc_service, pa_week = GA) + ฝากครรภ์ที่หน่วยบริการอื่น (precare) ของครรภ์ชุดเดียวกัน
    $st = $conn->prepare("
        SELECT s.person_anc_id AS anc_id, s.anc_service_date AS d, s.pa_week AS ga, 0 AS precare
          FROM person_anc_service s JOIN person_anc a ON a.person_anc_id = s.person_anc_id
          JOIN person pe ON pe.person_id = a.person_id
         WHERE a.anc_register_date BETWEEN :rmin AND :d2
        UNION ALL
        SELECT o.person_anc_id, o.precare_date, NULL, 1
          FROM person_anc_other_precare o JOIN person_anc a ON a.person_anc_id = o.person_anc_id
          JOIN person pe ON pe.person_id = a.person_id
         WHERE a.anc_register_date BETWEEN :rmin2 AND :d22");
    $st->execute([':rmin' => $rmin, ':d2' => $d2, ':rmin2' => $rmin, ':d22' => $d2]);

    $visits = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $id = (int)$r['anc_id'];
        if (!isset($pregs[$id])) {
            continue;
        }
        $ga = $r['ga'] === null || $r['ga'] === '' ? 0 : (int)$r['ga'];
        $visits[$id][] = ['date' => anc_day($r['d']), 'ga' => $ga ?: null, 'precare' => (bool)$r['precare']];
    }

    $st = $conn->prepare("SELECT referout_id, refer_date, hn, pdx FROM referout WHERE refer_date BETWEEN :d1 AND :d2");
    $st->execute([':d1' => $d1, ':d2' => $d2]);
    $refers = $st->fetchAll(PDO::FETCH_ASSOC);

    return [$pregs, $visits, $refers];
}

/* ---------- ตัวชี้วัด (metrics.py) ---------- */

// ตัวหาร scope=area ตามเอกสาร KPI: หญิงไทย และ (อาศัยในอำเภอ หรือ อาศัยนอกเขตแต่มาคลอดที่ รพ.)
function anc_in_area(array $p): bool
{
    return $p['thai'] && (str_starts_with((string)$p['village'], ANC_CHWPART . ANC_AMPPART)
        || in_array($p['labour_hospcode'], ANC_HOSPCODES, true));
}

// ฝากครรภ์ครั้งแรก (รวมที่หน่วยบริการอื่น) อายุครรภ์ ≤ 12 สัปดาห์ · visit แรก = วันที่น้อยสุด (เท่ากันเอาแถวแรก)
function anc_is_early12(array $p, array $vs): bool
{
    $first = null;
    foreach ($vs as $v) {
        if ($v['date'] !== null && ($first === null || $v['date'] < $first['date'])) {
            $first = $v;
        }
    }
    if ($first === null) {
        return false;
    }
    $ga = anc_visit_ga($first, $p['lmp']);
    return $ga !== null && $ga <= 12;
}

// ANC ครบ 5 ครั้งตามเกณฑ์: มี visit (รวม precare) ครบทั้ง 5 ช่วงอายุครรภ์
function anc_is_5visit(array $p, array $vs): bool
{
    $have = [];
    foreach ($vs as $v) {
        $ga = anc_visit_ga($v, $p['lmp']);
        foreach (ANC_GA_BUCKETS as $i => [$lo, $hi]) {
            if ($ga !== null && $ga >= $lo && $ga <= $hi) {
                $have[$i] = true;
                break;
            }
        }
    }
    return count($have) === count(ANC_GA_BUCKETS);
}

// จำนวนเดือน (เศษตามสัดส่วนวัน) ของช่วง — ตัวหาร "เฉลี่ยครั้ง/เดือน"
function anc_months(string $d1, string $d2): float
{
    $total = 0.0;
    $d = new DateTime($d1);
    $end = new DateTime($d2);
    while ($d <= $end) {
        $dim = (int)$d->format('t');
        $monthEnd = (clone $d)->modify('last day of this month');
        $e = $monthEnd < $end ? $monthEnd : $end;
        $total += ((int)$d->diff($e)->days + 1) / $dim;
        $d = (clone $e)->modify('+1 day');
    }
    return $total;
}

/*
 * KPI + แนวโน้มรายเดือน (ส่วนที่ 1 ของหน้า ANC)
 *   new        ฝากครรภ์รายใหม่ = ครรภ์ที่ลงทะเบียนในช่วง (ทุกครรภ์ ไม่ใช้ตัวหาร area)
 *   visits     ครั้งบริการ ANC ที่ รพ. ในช่วง (ไม่นับ precare) · per_month = visits / จำนวนเดือน
 *   open       กำลังติดตาม = ตั้งครรภ์อยู่ ณ วันสิ้นสุดช่วง (ลงทะเบียนแล้ว ยังไม่คลอด และไม่เกิน 44 สัปดาห์จาก LMP)
 *   early12 / visit5  ตัวหาร = ครรภ์ที่คลอดในช่วง (scope=area: เฉพาะในเขต)
 *   refer      ส่งต่องานแม่และเด็ก (นับครั้ง referout_id) · labor = ครรภ์ครบ 42 สัปดาห์ (LMP ไม่เกิน 1 ปีก่อน d1) ที่ลงวันคลอดแล้ว
 */
function anc_summary(array $pregs, array $visits, array $refers, string $d1s, string $d2s, bool $area, bool $natFiltered): array
{
    $d1 = anc_day($d1s);
    $d2 = anc_day($d2s);
    $in = fn($d) => $d !== null && $d >= $d1 && $d <= $d2;
    $monthOf = fn(int $day) => gmdate('Y-m', $day * 86400);

    $trend = [];
    for ($m = new DateTime(substr($d1s, 0, 7) . '-01'); $m->format('Y-m') <= substr($d2s, 0, 7); $m->modify('+1 month')) {
        $trend[$m->format('Y-m')] = ['month' => $m->format('Y-m'), 'label' => BBH_THAI_MONTHS[(int)$m->format('n') - 1] . ' '
            . substr((string)((int)$m->format('Y') + 543), 2), 'new' => 0, 'visits' => 0, 'refer' => 0];
    }

    $new = $nVisits = $open = $due = $recorded = $e12 = $v5 = $den = 0;
    $windows = [];
    foreach ($pregs as $id => $p) {
        $vs = $visits[$id] ?? [];
        if ($in($p['register'])) {
            $new++;
            $trend[$monthOf($p['register'])]['new']++;
        }
        foreach ($vs as $v) {
            if (!$v['precare'] && $in($v['date'])) {
                $nVisits++;
                $trend[$monthOf($v['date'])]['visits']++;
            }
        }
        $start = $p['lmp'] ?? $p['register'];
        if ($start !== null && $p['register'] !== null && $p['register'] <= $d2
            && !($p['labor'] !== null && $p['labor'] <= $d2) && $d2 - $start <= ANC_MAX_PREG_DAYS) {
            $open++;
        }
        if ($p['lmp'] !== null && $d2 - $p['lmp'] >= 42 * 7 && $p['lmp'] >= $d1 - 365) {
            $due++;
            $recorded += $p['labor'] !== null ? 1 : 0;
        }
        if ($in($p['labor']) && (!$area || anc_in_area($p))) {
            $den++;
            $e12 += anc_is_early12($p, $vs) ? 1 : 0;
            $v5 += anc_is_5visit($p, $vs) ? 1 : 0;
        }
        if ($p['lmp'] !== null && $p['hn']) {
            $end = $p['labor'] !== null ? $p['labor'] + ANC_POSTPARTUM_DAYS : $p['lmp'] + ANC_MAX_PREG_DAYS;
            $windows[$p['hn']][] = [$p['lmp'], $end];
        }
    }

    // refer: อยู่ในช่วงครรภ์ของทะเบียน หรือ Dx งานแม่และเด็ก · กรองสัญชาติแล้ว เหลือเฉพาะ HN ที่ตรงกับครรภ์ที่กรอง
    // ครรภ์ไม่มี HN ใช้คีย์ '' (ต้นแบบเทียบ None == None จึงเก็บ refer ที่ไม่มี HN ไว้ด้วย)
    $hns = $natFiltered ? array_flip(array_map(fn($p) => (string)$p['hn'], $pregs)) : null;
    $nRefer = 0;
    $seen = [];
    foreach ($refers as $r) {
        $rid = (int)$r['referout_id'];
        $day = anc_day($r['refer_date']);
        if (isset($seen[$rid])) {
            continue;
        }
        $hit = false;
        foreach ($windows[(string)$r['hn']] ?? [] as [$a, $b]) {
            if ($day !== null && $day >= $a && $day <= $b) {
                $hit = true;
                break;
            }
        }
        $code = strtoupper(trim(str_replace('.', '', (string)$r['pdx'])));
        $dx = $code !== '' && array_filter(ANC_REFER_DX, fn($x) => str_starts_with($code, $x));
        if (!$hit && !$dx) {
            continue;
        }
        $seen[$rid] = true;
        if ($hns !== null && !isset($hns[(string)$r['hn']])) {
            continue;
        }
        $nRefer++;
        if (isset($trend[$monthOf($day)])) {
            $trend[$monthOf($day)]['refer']++;
        }
    }

    $months = anc_months($d1s, $d2s);
    return [
        'new' => $new,
        'visits' => $nVisits,
        // ปัดแบบ round() ของ Python (เศษ .x5 พอดีปัดเข้าเลขคู่) ให้ตรงต้นแบบ เช่น 2907/12 = 242.25 → 242.2
        'per_month' => $months ? round($nVisits / $months, 1, PHP_ROUND_HALF_EVEN) : null,
        'open' => $open,
        'early12' => anc_rate('early12', $e12, $den),
        'visit5' => anc_rate('visit5', $v5, $den),
        'refer' => $nRefer,
        'labor' => ['due' => $due, 'recorded' => $recorded, 'pct' => anc_pct($recorded, $due), 'warn_pct' => ANC_LABOR_WARN_PCT],
        'trend' => array_values($trend),
    ];
}
