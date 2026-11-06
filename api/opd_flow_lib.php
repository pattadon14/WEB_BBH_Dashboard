<?php
/*
 * ฟังก์ชันร่วมของ 3D Monitor OPD (api/opd_flow*.php) และแท็บเวลารอ (api/opd_service_kpi.php)
 *
 * เอนจินเดิมพอร์ตจาก flow.py / deps.py ของ opd-dashboard-wnk
 * Copyright (c) 2569 นายเผด็จชัย สิงหล โรงพยาบาลวังน้ำเขียว — MIT License (ดู opd-dashboard/NOTICE.md)
 *
 * แหล่งข้อมูลของบ้านบึง (ตรวจกับข้อมูลจริง 29 ต.ค.–4 พ.ย. 2569):
 *  - opd_dep_queue  = ทุกครั้งที่ส่งผู้ป่วยไปแผนก (ส่ง/รับ/เสร็จ) → ตำแหน่งบนผัง · 999 = กลับบ้าน
 *  - ovst_service_time = เวลาแต่ละขั้นพร้อมประเภท (OPD-SCREEN, OPD-DOCTOR, OPD-RX-ENTRY ...) → เวลารอ
 *  - service_time ใช้ไม่ได้ดี: service1 เก็บเวลาซักประวัติได้ครั้งเดียว ทั้งที่ visit ซักประวัติหลายครั้ง
 *
 * อ่านอย่างเดียว: ทุกคำสั่งเป็น SELECT + prepared statement
 * ไฟล์นี้ไม่ต่อฐานข้อมูลเอง (endpoint require config/database.php) จึงทดสอบ flow_segments() แยกได้
 */

// เซิร์ฟเวอร์ตั้งเป็น UTC — วันที่/นาทีปัจจุบันต้องเป็นเวลาไทยให้ตรงกับเวลาใน HOSxP
date_default_timezone_set('Asia/Bangkok');

const FLOW_MAX_TOTAL = 600;     // นาที — เวลาที่บันทึกเกินลงทะเบียน + 10 ชม. = กดผิดวัน ไม่นับ
const FLOW_EST_SEND = 3;        // ไม่มีคิวแรกตอนลงทะเบียน → เดินไปแผนกหลักหลังลงทะเบียน 3 นาที
// HOSxP มีแค่เวลาส่ง X-Ray/Ultrasound ไม่มีเวลาเสร็จ — ถ่าย/ตรวจกี่นาทีแล้วกลับจุดเดิม
const FLOW_EST_XRAY = 10;
const FLOW_EST_US = 20;
const FLOW_WALK_OUT = 2;
const FLOW_BUSY_MAX = 120;      // รับคิว → เสร็จ นานกว่านี้ = ลืมกดเสร็จ ไม่ถือว่ายังรับบริการอยู่

// (รหัส, ชื่อที่แสดง, เป้าหมายนาที) — เป้าหมายใช้ระบายสี: ≤ เป้า เขียว · เกิน ส้ม · ≥ 1.5 เท่า แดง
const FLOW_AREAS = [
    // อาคารผู้ป่วยนอก
    ['REG', 'เวชระเบียน', 10],
    ['SCRW', 'ซักประวัติ Walk-in', 30],
    ['SCRA', 'ซักประวัติ ผู้ป่วยนัด', 30],
    ['PED', 'คลินิกเด็ก (ซักประวัติ/รอตรวจ)', 40],
    ['DRWW', 'หน้าห้องตรวจ Walk-in', 40],
    ['DRWA', 'หน้าห้องตรวจ ผู้ป่วยนัด', 40],
    ['CASH', 'การเงิน', 15],
    ['RX', 'ห้องจ่ายยา', 30],
    ['R1', 'ห้องตรวจ 1', 15],
    ['R2', 'ห้องตรวจ 2', 15],
    ['R3', 'ห้องตรวจ 3', 15],
    ['R4', 'ห้องตรวจ 4', 15],
    ['R5', 'ห้องตรวจ 5', 15],
    ['R6', 'ห้องตรวจ 6 (เด็ก)', 15],
    ['R7', 'ห้องตรวจ 7', 15],
    ['R8', 'ห้องตรวจ 8', 15],
    ['R9', 'ห้องตรวจ 9', 15],
    ['R10', 'ห้องตรวจ 10', 15],
    // อาคารอุบัติเหตุ
    ['ER', 'ห้องฉุกเฉิน', 60],
    ['LAB', 'ห้องแล็บ', 30],
    ['XRAY', 'ห้อง X-Ray', 30],
    ['US', 'ห้อง Ultrasound', 30],
    ['DENT', 'ห้องทันตกรรม', 60],
    ['WOUND', 'ห้องทำแผล', 30],
    // อาคารแยก
    ['ARI', 'คลินิก ARI', 60],
    ['TTM', 'แพทย์แผนไทย', 60],
    ['PT', 'กายภาพบำบัด', 60],
];
// บ้านบึง: ห้อง 1–5 walk-in · 6 ห้องตรวจเด็ก · 7–10 ผู้ป่วยนัด
const FLOW_ROOMS = ['R1', 'R2', 'R3', 'R4', 'R5', 'R6', 'R7', 'R8', 'R9', 'R10'];
// คิวค้างโดยไม่มีขั้นถัดไป — อยู่จุดนั้นต่อได้นานสุดกี่นาที (ห้องเก็บเงินแทบไม่กดรับคิว ส่งกลับบ้านช้าเป็นชั่วโมง)
const FLOW_LINGER = ['REG' => 15, 'CASH' => 30, 'RX' => 60, 'LAB' => 60, 'XRAY' => 30, 'US' => 30];
const FLOW_LINGER_DEFAULT = 120;
const FLOW_OPEN_AHEAD = 5;

// จุด Exit / หลังพบแพทย์: พบแพทย์แล้ว รอทำนัดครั้งต่อไป/ส่งทำหัตถการต่อ — HOSxP บันทึกเป็น OPD-SCREEN แต่ไม่ใช่การซักประวัติ
const FLOW_EXIT_RX = '/\bexit\b|หลังพบแพทย์/iu';
const FLOW_CASH_RX = '/เก็บเงิน|การเงิน|ชำระเงิน/u';

// กลุ่มแผนกจากชื่อใน kskdepartment (ตัวแรกที่ตรงชนะ) — ชื่อแผนกบ้านบึงขึ้นต้นด้วยรหัส
const FLOW_DEP_RULES = [
    ['REMOTE', '/ไปรษณีย์|\bpcu\b/iu'],    // ผู้ป่วยไม่ได้มา รพ.
    ['ARI', '/\bari\b/iu'],                // บัตร ซักประวัติ ตรวจ การเงิน ยา Exit ของ ARI อยู่ในอาคาร ARI ทั้งหมด
    ['DENT', '/ทันต/u'],                   // รวมห้องบัตร/การเงินทันตกรรม
    ['TTM', '/แผนไทย/u'],
    ['PT', '/กายภาพ/u'],
    ['EXIT', FLOW_EXIT_RX],
    ['WOUND', '/ท(?:ำ|ํา)แผล/u'],          // 078 คัดกรองห้องทำแผล → 047 ห้องทำแผล (อาคารอุบัติเหตุ)
    ['SCRW', '/opd\s*นอกเวลา/iu'],         // 023 OPD นอกเวลา 16:00–20:30 ต่อจาก walk-in
    ['ADMIN', '/เวชระเบียน|ห้องบัตร/u'],
    ['RX', '/จ่ายยา|เภสัช|^\d*\s*ห้องยา/iu'],
    ['CASH', FLOW_CASH_RX],
    ['TELE', '/telemed|โทรเวช/iu'],
    ['PED', '/เด็ก|กุมาร/u'],             // 134 จุดซักประวัติคลินิกเด็ก → ห้องตรวจ 6
    ['ER', '/\ber\b|ฉุกเฉิน|อุบัติเหตุ/iu'],
    ['US', '/ultra|อัลตร/iu'],
    ['XRAY', '/x-?ray|เอกซเรย์|รังสี/iu'],
    ['LAB', '/\blab\b|ชันสูตร|เทคนิคการแพทย์/iu'],
    ['DRW', '/หน้าห้องตรวจ/u'],           // จุดรอหน้าห้องตรวจ (002 หน้าห้องตรวจ 1, 129 หน้าห้องตรวจ3(นัด))
    ['ROOM', '/ห้องตรวจ(?:โรค)?\s*(\d+)/u'],
    ['ROOMX', '/ห้องตรวจ/u'],             // ห้องตรวจไม่มีเลข (ห้องตรวจ พิเศษ 1–3) → เดาห้องจากแพทย์
];
// กลุ่มอื่น (คลินิกเฉพาะโรค, ซักประวัติ, ไม่รู้จัก) → จุดซักประวัติตามฝั่ง walk-in / ผู้ป่วยนัด

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
            return 'R' . min(max((int) $m[1], 1), 10);  // ห้องเลขเกิน 10 รวมไว้ห้อง 10
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

/** แผนกที่ผู้ป่วยไม่ได้อยู่ใน รพ. (Telemed, PCU, ส่งยาไปรษณีย์) · FLOW_REMOTE_DEPS=none = ไม่ตัด, หรือระบุรหัสคั่นด้วย , */
function flow_remote_deps(array $deps): array
{
    $env = flow_env('FLOW_REMOTE_DEPS', '-');
    if ($env !== '-') {
        return strtolower(trim($env)) === 'none' ? [] : array_map('trim', explode(',', $env));
    }
    return flow_group_deps($deps, ['TELE', 'REMOTE']);
}

/** รหัสแผนกของกลุ่มที่ระบุ (string เสมอ — array_keys คืนรหัสที่เป็นตัวเลขเป็น int) */
function flow_group_deps(array $deps, array $groups): array
{
    return array_map('strval', array_keys(array_filter($deps, fn($x) => in_array($x['group'], $groups, true))));
}

function flow_group(array $deps, ?string $dep): ?string
{
    return $deps[(string) $dep]['group'] ?? null;
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

/**
 * ห้องตรวจเมื่อ HOSxP ไม่บอกเลขห้อง — ให้แพทย์คนเดียวกันอยู่ห้องเดิมทั้งวัน (ฝั่ง walk-in ห้อง 1–5, ผู้ป่วยนัด 7–10)
 * shortcut: เดาห้องจากรหัสแพทย์ ถ้ามีตารางแพทย์ ↔ ห้องตรวจของบ้านบึง ให้เปลี่ยนมาใช้ตารางนั้น
 */
function flow_room_for(?string $dr, bool $appt): string
{
    $rooms = $appt ? ['R7', 'R8', 'R9', 'R10'] : ['R1', 'R2', 'R3', 'R4', 'R5'];
    return $rooms[$dr ? crc32($dr) % count($rooms) : 0];
}

/**
 * แผนกในคิว → [จุดที่รอเมื่อถูกส่งมา, จุดเมื่อรับเข้าบริการ]
 * 'OUT' = กลับบ้าน (999) · null = ไม่ย้าย (แผนกนอก รพ. เช่น ส่งยาไปรษณีย์)
 */
function flow_place(array $deps, ?string $dep, bool $appt, ?string $dr): array
{
    if ((string) $dep === '999') {
        return ['OUT', 'OUT'];
    }
    $g = flow_group($deps, $dep);
    $side = $appt ? 'A' : 'W';
    if ($g === 'ROOMX') {
        $g = flow_room_for($dr, $appt);
    }
    if ($g === 'R6') {
        return ['PED', 'R6'];
    }
    if (in_array($g, FLOW_ROOMS, true)) {
        return [(int) substr($g, 1) >= 7 ? 'DRWA' : 'DRWW', $g];
    }
    switch ($g) {
        case 'REMOTE':
        case 'TELE':
            return [null, null];
        case 'ADMIN':
            return ['REG', 'REG'];
        case 'DRW':
        case 'EXIT':
            return ["DRW$side", "DRW$side"];
        case 'SCRW':
        case 'PED':
        case 'ER':
        case 'LAB':
        case 'XRAY':
        case 'US':
        case 'DENT':
        case 'WOUND':
        case 'ARI':
        case 'TTM':
        case 'PT':
        case 'CASH':
        case 'RX':
            return [$g, $g];
    }
    return ["SCR$side", "SCR$side"];
}

/**
 * 1 visit → [[นาทีที่เข้า, จุด], ...], นาทีที่ออก (นาทีนับจากเที่ยงคืนของวันที่มา)
 * $v: reg, md (แผนกหลัก), ap (ผู้ป่วยนัด), dr (แพทย์), lo/lr (แล็บสั่ง/รับสิ่งส่งตรวจ), xr, us
 * $q: แถว opd_dep_queue [ส่ง, รับ, เสร็จ, depcode] · $now = นาทีปัจจุบัน (เฉพาะวันนี้)
 */
function flow_segments(array $v, array $q, array $deps, ?float $now): array
{
    $reg = $v['reg'];
    $appt = !empty($v['ap']);
    $dr = $v['dr'] ?? null;
    $ok = fn($t) => $t !== null && $t >= $reg - 1 && $t <= $reg + FLOW_MAX_TOTAL;

    $main = flow_place($deps, $v['md'] ?? null, $appt, $dr)[0];
    $ev = [[$reg, 0, in_array($main, ['ER', 'ARI'], true) ? $main : 'REG']];   // ห้องบัตร ER/ARI อยู่ในอาคารนั้น
    if (!array_filter($q, fn($r) => $ok($r[0]) && $r[0] <= $reg + FLOW_EST_SEND) && $main !== null) {
        $ev[] = [$reg + FLOW_EST_SEND, 1, $main];
    }
    // กำลังรับบริการ (รับคิวแล้วยังไม่เสร็จ) — คิวที่ส่งไปที่อื่นระหว่างนั้น (เช่น ส่งการเงินล่วงหน้า) มีผลเมื่อบริการเสร็จ
    $busy = [];
    foreach ($q as $i => [, $ta, $tf]) {
        if ($ok($ta) && $ok($tf) && $tf > $ta && $tf - $ta <= FLOW_BUSY_MAX) {
            $busy[$i] = [$ta, $tf];
        }
    }
    foreach ($q as $i => [$tq, $ta, $tf, $dep]) {
        [$wait, $in] = flow_place($deps, $dep, $appt, $dr);
        if ($wait === null) {
            continue;
        }
        if ($ok($tq)) {
            $t = max($tq, $reg + FLOW_EST_SEND);    // แสดงที่เวชระเบียนสักครู่ก่อนไปจุดแรก
            if ($wait !== 'OUT') {
                foreach ($busy as $j => [$a, $b]) {
                    $t = ($j !== $i && $t > $a && $t < $b) ? $b : $t;
                }
            }
            $ev[] = [$t, 1, $wait];
        }
        if ($wait !== 'OUT' && $ok($ta)) {
            $ev[] = [max($ta, $reg), 2, $in];
            if ($in !== $wait && $ok($tf) && $tf > $ta) {
                $ev[] = [$tf, 0, $wait];     // ออกจากห้องตรวจ — ถ้ามีคิวถัดไปเวลาเดียวกัน คิวนั้นชนะ
            }
        }
    }
    foreach ([['lo', 'LAB', null], ['xr', 'XRAY', FLOW_EST_XRAY], ['us', 'US', FLOW_EST_US]] as [$k, $a, $dur]) {
        if ($ok($v[$k] ?? null)) {
            $ev[] = [$v[$k], 1, $a];
            $back = $dur === null ? ($v['lr'] ?? null) : $v[$k] + $dur;
            if ($ok($back) && $back > $v[$k]) {
                $ev[] = [$back, 1, 'BACK'];   // กลับจุดเดิมหลังเจาะเลือด/ถ่ายภาพ
            }
        }
    }
    usort($ev, fn($a, $b) => $a[0] <=> $b[0] ?: $a[1] <=> $b[1]);

    $segs = [];
    $home = null;    // จุดล่าสุดที่ไม่ใช่แล็บ/X-Ray/Ultrasound
    $out = null;
    foreach ($ev as [$t, , $a]) {
        if ($a === 'OUT') {
            $out = $t;
            break;                            // หลังส่งกลับบ้านไม่นับ (กดย้อนหลัง)
        }
        if ($a === 'BACK') {
            $a = $home;
            if ($a === null) {
                continue;
            }
        } elseif (!in_array($a, ['LAB', 'XRAY', 'US'], true)) {
            $home = $a;
        }
        if ($segs && end($segs)[0] == $t) {
            array_pop($segs);                 // เหตุการณ์เวลาเดียวกัน — ตัวหลังชนะ
        }
        if (!$segs || end($segs)[1] !== $a) {
            $segs[] = [$t, $a];
        }
    }
    [$lastT, $lastA] = end($segs);
    $cap = $lastT + (FLOW_LINGER[$lastA] ?? FLOW_LINGER_DEFAULT);
    if ($out !== null) {
        $end = min(max($out, $lastT) + FLOW_WALK_OUT, $cap);
    } elseif ($now !== null && $now <= $cap) {
        $end = min(max($now + FLOW_OPEN_AHEAD, $lastT + FLOW_WALK_OUT), $cap);   // ยังอยู่ใน รพ.
    } else {
        $end = $cap;
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
