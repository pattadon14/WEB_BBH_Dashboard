<?php

/*
 * รายรับตามสิทธิการรักษา — เทียบ 2 ช่วง  ?a=<ช่วง>&b=<ช่วง>
 *   ช่วง = YYYY-MM (รายเดือน ทั้งเดือน) หรือ fyNNNN (ปีงบประมาณ พ.ศ. = 1 ต.ค. NNNN-544 ถึง 30 ก.ย. NNNN-543)
 *   a, b ต้องเป็นชนิดเดียวกัน · ไม่ใส่ a = เดือนปัจจุบัน · ไม่ใส่ b = ช่วงก่อนหน้า (เดือนก่อน / ปีงบก่อน)
 *
 * นิยาม (มูลค่าค่ารักษาที่บันทึกใน HOSxP = ยอดเรียกเก็บ ไม่ใช่เงินที่ได้รับจริงจากกองทุน):
 *   OPD = vn_stat.income ตามวันรับบริการ (vstdate)
 *   IPD = an_stat.income ตามวันจำหน่าย (dchdate) — ผู้ป่วยที่ยังนอนอยู่ยังไม่นับ
 *   paid = ส่วนที่ผู้ป่วยชำระเอง (paid_money)
 *   drug_opd / drug_ipd = ต้นทุนยา (opitemrece.cost × qty) — ค่าเฉลี่ยต่อครั้ง/ต่อราย คำนวณที่หน้าเว็บ (หาร visits / discharges)
 *   สิทธิ = pttype.hipdata_code (รหัสกองทุน สปสช.) รวมเป็นกลุ่มตาม FIN_GROUPS
 * นับทั้งช่วง (ทั้งเดือน / ทั้งปีงบ) — ช่วงที่ยังไม่จบ (เดือน/ปีงบปัจจุบัน) มีข้อมูลถึงวันนี้ complete=false
 * ยอดช่วงปัจจุบันยังเปลี่ยนได้ (ค่าใช้จ่ายบันทึกย้อนหลัง) — ตรวจกับ SQL ตรงแล้ว 27 พ.ย. 2569
 */

header('Content-Type: application/json; charset=utf-8');

require_once '../auth/provider.php';

// ข้อมูลการเงินของ รพ. — ต้อง login เหมือน API รายชื่อผู้ป่วย
if (!provider_is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'กรุณาเข้าสู่ระบบ Provider ID'], JSON_UNESCAPED_UNICODE);
    exit;
}
session_write_close();

const FIN_GROUPS = [
    'uc'  => ['บัตรทอง (UC)',              ['UCS']],
    'sss' => ['ประกันสังคม',               ['SSS', 'SSI']],
    'ofc' => ['ข้าราชการ/เบิกต้นสังกัด',    ['OFC', 'BKK', 'GOF', 'PVT']],
    'lgo' => ['อปท.',                      ['LGO']],
    'nrh' => ['ต่างด้าว',                  ['NRH']],
    'ins' => ['พ.ร.บ./ประกันภัย',          ['INS']],
    'stp' => ['ผู้มีปัญหาสถานะและสิทธิ',     ['STP']],
    'oth' => ['ชำระเงินเอง/อื่น ๆ',          []],   // OTH, ไม่มีรหัส และรหัสอื่นทั้งหมด
];

// แปลงช่วงเป็น [kind, start, end(ไม่เกินวันนี้), complete] · ผิดรูปแบบ/อนาคต = null
function fin_period(string $p): ?array
{
    $today = date('Y-m-d');
    if (preg_match('/^fy(\d{4})$/', $p, $m)) {
        $y = (int)$m[1] - 543;                       // ปีงบ 2569 = 1 ต.ค. 2025 – 30 ก.ย. 2026
        $start = sprintf('%04d-10-01', $y - 1);
        $end = sprintf('%04d-09-30', $y);
        $kind = 'year';
    } else {
        $d = DateTime::createFromFormat('!Y-m-d', $p . '-01');
        if (!$d || $d->format('Y-m') !== $p) {
            return null;
        }
        $start = $d->format('Y-m-d');
        $end = $d->format('Y-m-t');
        $kind = 'month';
    }
    if ($start > $today || $start < '2010-01-01') {
        return null;
    }
    return ['kind' => $kind, 'key' => $p, 'start' => $start, 'end' => min($end, $today), 'complete' => $end < $today];
}

$aKey = is_string($_GET['a'] ?? null) ? $_GET['a'] : date('Y-m');
$a = fin_period($aKey);
$bDefault = $a === null ? '' : ($a['kind'] === 'year'
    ? 'fy' . ((int)substr($aKey, 2) - 1)
    : date('Y-m', strtotime($a['start'] . ' -1 month')));
$bKey = is_string($_GET['b'] ?? null) ? $_GET['b'] : $bDefault;
$b = fin_period($bKey);
if (!$a || !$b || $a['kind'] !== $b['kind']) {
    http_response_code(400);
    echo json_encode(['error' => 'ช่วงเวลาไม่ถูกต้อง (YYYY-MM หรือ fyNNNN ชนิดเดียวกัน)'], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once '../config/cache.php';
// ช่วงที่ยังไม่จบ cache 5 นาที · ช่วงที่จบแล้ว 6 ชม. (ยังมีบันทึกย้อนหลังได้บ้าง)
api_cache('finance_income_pttype_v2_' . $aKey . '_' . $bKey . '_' . date('Y-m-d'),
    ($a['complete'] && $b['complete']) ? 21600 : 300);
require_once '../config/database.php';

try {
    $stmt = $conn->prepare("SELECT x.src,
            COALESCE(x.hip, '') AS hip,
            COUNT(*) AS n,
            COALESCE(SUM(x.income), 0) AS income,
            COALESCE(SUM(x.paid), 0) AS paid
        FROM (
            SELECT 'opd' AS src, p.hipdata_code AS hip, v.income, v.paid_money AS paid
            FROM vn_stat v LEFT JOIN pttype p ON p.pttype = v.pttype
            WHERE v.vstdate BETWEEN :s1 AND :e1
            UNION ALL
            SELECT 'ipd', p.hipdata_code, a.income, a.paid_money
            FROM an_stat a LEFT JOIN pttype p ON p.pttype = a.pttype
            WHERE a.dchdate BETWEEN :s2 AND :e2
        ) x
        GROUP BY 1, 2");

    // ต้นทุนยา = opitemrece.cost (ราคาทุนต่อหน่วย = drugitems.unitcost ณ วันจ่าย) × qty เฉพาะรายการยา (drugitems)
    // ผูกสิทธิ/วันที่เหมือนรายรับ: OPD ผ่าน vn → vn_stat (vstdate) · IPD ผ่าน an → an_stat (dchdate)
    // ไม่รวมเวชภัณฑ์/ค่าบริการ — ต้นทุนลงไว้ ~50% ของรายการ ยังเชื่อไม่ได้ (ตรวจ ต.ค. 2569)
    $drug = $conn->prepare("SELECT x.src, COALESCE(x.hip, '') AS hip, COALESCE(SUM(x.c), 0) AS cost
        FROM (
            SELECT 'opd' AS src, p.hipdata_code AS hip, o.cost * o.qty AS c
            FROM vn_stat v
            JOIN opitemrece o ON o.vn = v.vn
            JOIN drugitems d ON d.icode = o.icode
            LEFT JOIN pttype p ON p.pttype = v.pttype
            WHERE v.vstdate BETWEEN :s1 AND :e1
            UNION ALL
            SELECT 'ipd', p.hipdata_code, o.cost * o.qty
            FROM an_stat a
            JOIN opitemrece o ON o.an = a.an
            JOIN drugitems d ON d.icode = o.icode
            LEFT JOIN pttype p ON p.pttype = a.pttype
            WHERE a.dchdate BETWEEN :s2 AND :e2
        ) x
        GROUP BY 1, 2");

    $codeToGroup = [];
    foreach (FIN_GROUPS as $key => [, $codes]) {
        foreach ($codes as $c) {
            $codeToGroup[$c] = $key;
        }
    }

    $zero = fn() => ['income' => 0.0, 'opd' => 0.0, 'ipd' => 0.0, 'paid' => 0.0, 'visits' => 0, 'discharges' => 0,
                     'drug_opd' => 0.0, 'drug_ipd' => 0.0];
    $groups = [];
    foreach (FIN_GROUPS as $key => [$label, $codes]) {
        $groups[$key] = ['key' => $key, 'label' => $label, 'codes' => $codes, 'a' => $zero(), 'b' => $zero()];
    }
    $total = ['a' => $zero(), 'b' => $zero()];

    $add = function (array &$slot, array $r) {
        $inc = (float)$r['income'];
        $slot['income'] += $inc;
        $slot[$r['src']] += $inc;
        $slot['paid'] += (float)$r['paid'];
        $slot[$r['src'] === 'opd' ? 'visits' : 'discharges'] += (int)$r['n'];
    };

    // คำนวณแยกทีละช่วง — ช่วงซ้อนกันได้ (เช่นเลือกช่วงเดียวกัน) โดยไม่นับปนกัน
    foreach (['a' => $a, 'b' => $b] as $slot => $per) {
        $stmt->execute([':s1' => $per['start'], ':e1' => $per['end'], ':s2' => $per['start'], ':e2' => $per['end']]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $add($groups[$codeToGroup[$r['hip']] ?? 'oth'][$slot], $r);
            $add($total[$slot], $r);
        }
        $drug->execute([':s1' => $per['start'], ':e1' => $per['end'], ':s2' => $per['start'], ':e2' => $per['end']]);
        foreach ($drug->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $k = 'drug_' . $r['src'];
            $groups[$codeToGroup[$r['hip']] ?? 'oth'][$slot][$k] += (float)$r['cost'];
            $total[$slot][$k] += (float)$r['cost'];
        }
    }

    $round = function (array $p) {
        foreach (['a', 'b'] as $s) {
            foreach (['income', 'opd', 'ipd', 'paid', 'drug_opd', 'drug_ipd'] as $k) {
                $p[$s][$k] = round($p[$s][$k], 2);
            }
        }
        return $p;
    };
    $groups = array_map($round, array_values($groups));
    usort($groups, fn($x, $y) => $y['a']['income'] <=> $x['a']['income']);

    echo json_encode([
        'kind'   => $a['kind'],
        'a'      => $a,
        'b'      => $b,
        'total'  => $round($total),
        'groups' => $groups,
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log('finance_income_pttype: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'โหลดข้อมูลไม่สำเร็จ'], JSON_UNESCAPED_UNICODE);
}
