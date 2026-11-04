<?php
/*
 * ฟังก์ชันร่วมของ 3D Monitor OPD (api/opd_flow*.php)
 *
 * พอร์ตจาก flow.py / deps.py / stmap.py ของ opd-dashboard-wnk
 * Copyright (c) 2569 นายเผด็จชัย สิงหล โรงพยาบาลวังน้ำเขียว — MIT License (ดู opd-dashboard/NOTICE.md)
 *
 * อ่านอย่างเดียว: ทุกคำสั่งเป็น SELECT + prepared statement
 * ไฟล์นี้ไม่ต่อฐานข้อมูลเอง (endpoint require config/database.php) จึงทดสอบ flow_segments() แยกได้
 */

// เซิร์ฟเวอร์ตั้งเป็น UTC — วันที่/นาทีปัจจุบันต้องเป็นเวลาไทยให้ตรงกับเวลาใน HOSxP
date_default_timezone_set('Asia/Bangkok');

const FLOW_MAX_TOTAL = 600;     // นาที — เวลาที่บันทึกเกินลงทะเบียน + 10 ชม. = กดผิดวัน ไม่นับ
// บ้านบึงไม่บันทึก "ส่งไปจุดซักประวัติ" และ "คัดกรองเสร็จ → ส่งพบแพทย์" จึงประมาณ (นาที):
// ส่งไปซักประวัติ = ลงทะเบียน + 3 · คัดกรองเสร็จ = เริ่มคัดกรอง + 5 (ไม่งั้นคนรอคัดกรองไปนั่งที่เวชระเบียน)
const FLOW_EST_SEND = 3;
const FLOW_EST_SCREEN = 5;

// (รหัส, ชื่อที่แสดง, เป้าหมายนาที) — เป้าหมายใช้ระบายสี: ≤ เป้า เขียว · เกิน ส้ม · ≥ 1.5 เท่า แดง
const FLOW_AREAS = [
    ['REG', 'เวชระเบียน', 10],
    ['SCR', 'ซักประวัติ', 30],
    ['LAB', 'ห้องแล็บ', 30],
    ['XRAY', 'ห้อง X-Ray', 30],
    ['RX', 'ห้องยา การเงิน', 30],
    ['DRW', 'ห้องตรวจแพทย์', 40],
    ['R1', 'ห้องตรวจ 1', 15],
    ['R2', 'ห้องตรวจ 2', 15],
    ['R3', 'ห้องตรวจ 3', 15],
    ['R4', 'ห้องตรวจ 4', 15],
    ['R5', 'ห้องตรวจ 5', 15],
    ['DENT', 'ห้องฟัน', 60],
    ['TTM', 'แพทย์แผนไทย', 60],
    ['PT', 'กายภาพบำบัด', 60],
    ['ER', 'ห้องฉุกเฉิน', 60],
];
const FLOW_ROOMS = ['R1', 'R2', 'R3', 'R4', 'R5'];
// คลินิกที่ตรวจ/ทำหัตถการเอง มักกด "ตรวจเสร็จ" ตั้งแต่รับเข้าคิว จึงให้อยู่ในคลินิกต่อจนขั้นถัดไป
const FLOW_CLINICS = ['DENT', 'TTM', 'PT'];
// ขั้นสุดท้ายไม่ใช่ "จบ" ให้ค้างที่จุดนั้นได้นานสุดกี่นาที (กันลืมกดเวลาแล้วค้างทั้งวัน)
const FLOW_LINGER = ['REG' => 15, 'SCR' => 60, 'LAB' => 60, 'XRAY' => 30, 'DRW' => 120, 'R1' => 20, 'R2' => 20,
    'R3' => 20, 'R4' => 20, 'R5' => 20, 'ER' => 180, 'DENT' => 60, 'TTM' => 60, 'PT' => 60, 'RX' => 60];
const FLOW_WALK_OUT = 2;
// ลำดับเมื่อเวลาเท่ากัน
const FLOW_ORDER = ['reg', 's3', 'er_in', 's4', 'lo', 'lr', 'xr', 's11', 's5', 'er_out', 's12', 's6', 's7', 's16', 's20'];
// HOSxP ไม่มีเวลาออกจาก ER ที่ใช้ได้ → อยู่ ER กี่นาทีนับจาก enter_er_time (observe = 'Y' นานกว่า)
const FLOW_ER_STAY = 20;
const FLOW_ER_OBSERVE = 180;
const FLOW_HOME_MAX = 120;          // ส่งกลับบ้าน (cur_dep 999) หลังกิจกรรมสุดท้ายไม่เกินนี้ = เวลาออกจริง
const FLOW_HOME_LINGER = 30;
const FLOW_CLINIC_HOME_MAX = 240;
const FLOW_CLINIC_OPEN_MAX = 300;
const FLOW_OPEN_AHEAD = 5;

// กลุ่มแผนกจากชื่อใน kskdepartment (ตัวแรกที่ตรงชนะ) — รหัสแผนกแต่ละ รพ. ไม่เหมือนกัน
const FLOW_DEP_RULES = [
    ['REMOTE', '/ไปรษณีย์|\bpcu\b/iu'],    // ผู้ป่วยไม่ได้มา รพ. (ส่งยาทางไปรษณีย์, PCU) · ชื่อแผนกบ้านบึงขึ้นต้นด้วยรหัส
    ['ADMIN', '/เวชระเบียน/u'],
    ['RX', '/จ่ายยา|เภสัช|^ห้องยา/iu'],
    ['CASH', '/เก็บเงิน|การเงิน|ชำระเงิน/u'],
    ['TELE', '/telemed|โทรเวช/iu'],
    ['ARI', '/\bari\b/iu'],
    ['ER', '/\ber\b|ฉุกเฉิน|อุบัติเหตุ/iu'],
    ['DENT', '/ทันต/u'],
    ['TTM', '/แผนไทย/u'],
    ['PT', '/กายภาพ/u'],
    ['MH', '/จิตเวช|สุขภาพจิต/u'],
    ['SPEC', '/ปฐมภูมิ|เวชศาสตร์ครอบครัว|เวชปฏิบัติครอบครัว/u'],
    ['XRAY', '/x-?ray|เอกซเรย์|รังสี/iu'],
    ['LAB', '/\blab\b|ชันสูตร|เทคนิคการแพทย์/iu'],
    ['NCD', '/\bncd\b|เบาหวาน|ความดัน|โรคเรื้อรัง/iu'],
    ['ROOM', '/ห้องตรวจ(?:โรค)?\s*(\d+)/u'],
    ['SCR', '/ซักประวัติ|คัดกรอง|หน้าห้องตรวจ/u'],
];
// กลุ่มที่ผังของบ้านบึงรวมไว้จุดเดียวกัน
const FLOW_GROUP_AREA = ['NCD' => 'SCR', 'ARI' => 'SCR', 'CASH' => 'RX', 'SPEC' => 'DRW', 'MH' => 'DRW', 'TELE' => 'DRW'];

// คอลัมน์มาตรฐานของ service_time (ความหมายตามแบบ รพ.วังน้ำเขียว):
// service3 ส่งตรวจ · service4 เริ่มคัดกรอง · service11 คัดกรองเสร็จ · service5 แพทย์เริ่มตรวจ · service12 แพทย์ตรวจเสร็จ
// service6 ใบสั่งยาถึงห้องยา · service7 ชำระเงิน · service16 จ่ายยาเสร็จ · service20 จบบริการหน่วยเฉพาะ
const FLOW_ST_STD = ['service3', 'service4', 'service11', 'service5', 'service12', 'service6', 'service7', 'service16', 'service20'];
// บ้านบึงกดเวลาคนละคอลัมน์ — ตรวจจาก api/opd_flow_check.php (7 วัน ถึง 4 พ.ย. 2569, 7,865 visit):
// service1 คัดกรอง +22 น. · service2 ห้องตรวจแพทย์ +88 · service5 ห้องจ่ายยา +111 · service6 การเงิน +104
// service7 ห้องจ่ายยา +131 · service9 ทันตกรรม/กายภาพ +118 · service3 = คัดกรอง ER (ใช้ er_regist แทน)
// แก้ทับได้ด้วย .env FLOW_ST_MAP รูปแบบเดียวกัน
const FLOW_ST_MAP_DEFAULT = 'service3:none,service4:service1,service11:none,service5:service2,service12:none,'
    . 'service6:service5,service7:service6,service16:service7,service20:service9';

function flow_env(string $key, string $default = ''): string
{
    $v = $_ENV[$key] ?? getenv($key);
    return ($v === false || $v === null) ? $default : (string) $v;
}

function flow_classify(?string $name): ?string
{
    $name = trim((string) $name);
    foreach (FLOW_DEP_RULES as [$group, $rx]) {
        if (!preg_match($rx, $name, $m)) {
            continue;
        }
        if ($group === 'ROOM') {
            return 'R' . min(max((int) $m[1], 1), 5);   // ห้องเลขเกิน 5 รวมไว้ห้อง 5
        }
        return $group;
    }
    return null;
}

/** depcode → ['name' => ชื่อแผนก, 'group' => กลุ่ม] · แก้ทับรายรหัสได้ด้วย FLOW_DEP_MAP=011=ER,040=TELE */
function flow_deps(PDO $conn): array
{
    static $deps = null;
    if ($deps !== null) {
        return $deps;
    }
    $deps = [];
    foreach ($conn->query('SELECT depcode, department FROM kskdepartment') as $r) {
        $deps[(string) $r['depcode']] = ['name' => (string) $r['department'], 'group' => flow_classify($r['department'])];
    }
    foreach (explode(',', flow_env('FLOW_DEP_MAP')) as $pair) {
        if (strpos($pair, '=') !== false) {
            [$code, $group] = array_map('trim', explode('=', $pair, 2));
            $deps[$code] = ['name' => $deps[$code]['name'] ?? $code, 'group' => strtoupper($group)];
        }
    }
    return $deps;
}

function flow_group(array $deps, ?string $dep): ?string
{
    return $deps[(string) $dep]['group'] ?? null;
}

/** service_time ผ่าน derived table ที่เปลี่ยนชื่อคอลัมน์ตาม FLOW_ST_MAP (ไม่ตั้ง = ใช้ตารางตรง) */
function flow_st_table(): string
{
    $map = [];
    foreach (explode(',', flow_env('FLOW_ST_MAP') ?: FLOW_ST_MAP_DEFAULT) as $part) {
        if (strpos($part, ':') === false) {
            continue;
        }
        [$k, $v] = array_map(fn($x) => strtolower(trim($x)), explode(':', $part, 2));
        // ชื่อคอลัมน์ถูกต่อเข้า SQL ตรง ๆ — รับเฉพาะรูปแบบที่ปลอดภัย
        if (in_array($k, FLOW_ST_STD, true) && preg_match('/^(service([1-9]|1\d|20)|none)$/', $v)) {
            $map[$k] = $v;
        }
    }
    if (!$map) {
        return 'service_time';
    }
    $cols = ['vn', 'hn', 'vstdate', 'vsttime'];
    foreach (FLOW_ST_STD as $c) {
        $p = $map[$c] ?? $c;
        $cols[] = $p === 'none' ? "CAST(NULL AS time) $c" : "$p $c";
        $cols[] = $p === 'none' ? "CAST(NULL AS varchar(3)) {$c}_dep" : "{$p}_dep {$c}_dep";
    }
    return '(SELECT ' . implode(', ', $cols) . ' FROM service_time)';
}

/** รหัส visit สุ่ม (ไม่ย้อนกลับเป็น vn ได้) — เกลือต้องคงที่ ไม่งั้นหุ่นสลับตัวตอนรีเฟรช */
function flow_vid(string $vn): string
{
    static $salt = null;
    if ($salt === null) {
        $salt = flow_env('FLOW_SALT') ?: hash('sha256', 'opd-flow' . flow_env('DB_USER') . flow_env('DB_PASS'));
    }
    return substr(hash('sha256', $salt . $vn), 0, 10);
}

function flow_has_column(PDO $conn, string $table, string $column): bool
{
    $st = $conn->prepare('SELECT 1 FROM information_schema.columns WHERE table_name = :t AND column_name = :c LIMIT 1');
    $st->execute([':t' => $table, ':c' => $column]);
    return (bool) $st->fetchColumn();
}

/** วันที่จาก ?d=YYYY-MM-DD (ไม่เกินวันนี้) */
function flow_date(): string
{
    $today = date('Y-m-d');
    $d = $_GET['d'] ?? $today;
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) || !strtotime($d) || $d > $today) {
        return $today;
    }
    return $d;
}

function flow_json($data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function flow_thai_date(string $d): string
{
    $m = ['', 'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม',
        'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];
    $w = ['อาทิตย์', 'จันทร์', 'อังคาร', 'พุธ', 'พฤหัสบดี', 'ศุกร์', 'เสาร์'];
    $t = strtotime($d);
    return 'วัน' . $w[(int) date('w', $t)] . 'ที่ ' . (int) date('j', $t) . ' ' . $m[(int) date('n', $t)] . ' ' . ((int) date('Y', $t) + 543);
}

/** แผนก → จุดบริการบนผัง (ไม่อยู่ในผัง = default) */
function flow_unit(array $deps, array $idx, ?string $dep, string $default): string
{
    $g = flow_group($deps, $dep);
    if ($g === null) {
        return $default;
    }
    $a = FLOW_GROUP_AREA[$g] ?? $g;
    return isset($idx[$a]) ? $a : $default;
}

/** 1 visit → [[นาทีที่เข้า, จุด], ...], นาทีที่ออก · $now = นาทีปัจจุบัน (เฉพาะวันนี้) */
function flow_segments(array $r, array $deps, array $idx, ?float $now): array
{
    $reg = $r['reg'];
    $md = $r['md'];
    // ขั้นที่บ้านบึงไม่บันทึก — ประมาณ (FLOW_EST_*) เฉพาะเมื่อมีเวลาขั้นถัดไปจริง
    if ($r['s3'] === null && $r['s4'] !== null && $r['s4'] > $reg + FLOW_EST_SEND && flow_group($deps, $md) !== 'ER') {
        $r['s3'] = $reg + FLOW_EST_SEND;
    }
    if ($r['s11'] === null && $r['s4'] !== null && $r['s5'] !== null && $r['s5'] > $r['s4'] + FLOW_EST_SCREEN) {
        $r['s11'] = $r['s4'] + FLOW_EST_SCREEN;
    }
    $scr = flow_unit($deps, $idx, $r['d4'] ?: $md, 'SCR');
    $exam = flow_unit($deps, $idx, $r['d5'] ?: ($r['d12'] ?: $md), 'DRW');
    if (in_array($exam, ['RX', 'LAB', 'XRAY', 'REG'], true)) {
        $exam = 'DRW';
    }
    if (in_array($scr, FLOW_ROOMS, true) || in_array($scr, ['RX', 'LAB', 'XRAY', 'REG', 'DRW'], true)) {
        $scr = 'SCR';
    }
    $s7 = flow_unit($deps, $idx, $r['d7'], 'RX');
    $area = [
        'reg' => flow_group($deps, $md) === 'ER' ? 'ER' : 'REG',
        's3' => $scr, 's4' => $scr,
        'lo' => 'LAB', 'lr' => 'LAB', 'xr' => 'XRAY',
        // ส่งพบแพทย์แล้วนั่งรอหน้าห้องตรวจ (คลินิกเฉพาะรอในคลินิกเอง)
        's11' => (in_array($exam, FLOW_ROOMS, true) || in_array($exam, ['SCR', 'DRW'], true)) ? 'DRW' : $exam,
        // ไม่รู้ห้องตรวจ (ไม่มีรหัสแผนกห้องตรวจ) → ห้องตรวจ 2 ไว้ก่อน
        's5' => in_array($exam, ['DRW', 'SCR'], true) ? 'R2' : $exam,
        's12' => null, 's6' => 'RX', 's7' => $s7, 's16' => null, 's20' => null,
        'er_in' => 'ER', 'er_out' => null,
    ];

    $ev = [];
    foreach (FLOW_ORDER as $oi => $k) {
        $t = $r[$k];
        if ($t === null) {
            continue;
        }
        if (($k === 's3' || $k === 's20') && flow_group($deps, $r['d' . substr($k, 1)]) === 'ADMIN') {
            continue;   // เวชระเบียนส่งต่อ/ปิด visit ย้อนหลัง ไม่ใช่ที่อยู่ของผู้ป่วย
        }
        if ($k !== 'reg' && !($reg <= $t && $t <= $reg + FLOW_MAX_TOTAL)) {
            continue;   // กดเวลาผิดวัน/ก่อนลงทะเบียน
        }
        $ev[] = [$t, $oi, $k];
    }
    usort($ev, fn($a, $b) => $a[0] <=> $b[0] ?: $a[1] <=> $b[1]);

    // แล็บ/X-Ray ที่เกิดหลังขั้นบริการสุดท้าย = ผู้ป่วยกลับไปแล้ว (ส่งตรวจต่อภายหลัง)
    $lastSrv = null;
    foreach ($ev as $e) {
        if (!in_array($e[2], ['reg', 'lo', 'lr', 'xr'], true)) {
            $lastSrv = max($lastSrv ?? $e[0], $e[0]);
        }
    }
    if ($lastSrv !== null) {
        $ev = array_values(array_filter($ev, fn($e) => !in_array($e[2], ['lo', 'lr', 'xr'], true) || $e[0] <= $lastSrv));
    }

    // ตรวจเสร็จ → รอที่ห้องยาถ้ามีขั้นยาตามมา ไม่งั้นการเงิน ไม่งั้นกลับบ้าน (ไม่ให้นั่งค้างห้องตรวจ)
    if ($r['s12'] !== null) {
        $later = [];
        foreach ($ev as $e) {
            if ($e[0] >= $r['s12']) {
                $later[$e[2]] = true;
            }
        }
        $area['s12'] = (isset($later['s6']) || isset($later['s16'])) ? 'RX'
            : (in_array($exam, FLOW_CLINICS, true) ? $exam : (isset($later['s7']) ? $area['s7'] : null));
    }

    $segs = [];
    foreach ($ev as [$t, , $k]) {
        $a = $area[$k];
        if ($a === null || ($segs && end($segs)[1] === $a)) {
            continue;
        }
        $segs[] = [$t, $a];
    }

    [$lastT, , $lastK] = end($ev);
    $lastArea = $area[$lastK];
    $end = $lastArea === null ? $lastT + FLOW_WALK_OUT : $lastT + FLOW_LINGER[$lastArea];
    $home = $r['home'];
    $inClinic = in_array($lastArea, FLOW_CLINICS, true);
    if ($home !== null && $home >= $reg) {
        if ($lastT <= $home && $home <= $lastT + ($inClinic ? FLOW_CLINIC_HOME_MAX : FLOW_HOME_MAX)) {
            $end = max($home, $lastT + FLOW_WALK_OUT);
        } elseif ($home < $lastT && $lastArea !== null) {
            $end = min($end, $lastT + FLOW_HOME_LINGER);
        }
    } elseif ($now !== null && $inClinic && flow_unit($deps, $idx, $r['cd'], '') === $lastArea) {
        $end = max($end, min($now + FLOW_OPEN_AHEAD, $reg + FLOW_CLINIC_OPEN_MAX));
    }
    if (!$segs) {
        $segs = [[$reg, $area['reg']]];
    }
    return [$segs, $end];
}

/** สีเสื้อ: ER ใช้ระดับคัดแยก · OPD ใช้ pt_priority (4 ฉุกเฉิน, 2/3 พระ-ผู้พิการ/ผู้สูงอายุ) */
function flow_urgency($erLevel, $priority): int
{
    $lv = (string) $erLevel;
    if (in_array($lv, ['1', '2', '3', '4'], true)) {
        return (int) $lv;
    }
    $pr = (string) $priority;
    return $pr === '4' ? 2 : (in_array($pr, ['2', '3'], true) ? 6 : 5);
}
