<?php

/*
 * รายรับตามสิทธิการรักษา — เดือนนี้เทียบเดือนก่อน  ?month=YYYY-MM (ไม่ส่ง = เดือนปัจจุบัน)
 *
 * นิยาม (มูลค่าค่ารักษาที่บันทึกใน HOSxP = ยอดเรียกเก็บ ไม่ใช่เงินที่ได้รับจริงจากกองทุน):
 *   OPD = vn_stat.income ตามวันรับบริการ (vstdate)
 *   IPD = an_stat.income ตามวันจำหน่าย (dchdate) — ผู้ป่วยที่ยังนอนอยู่ยังไม่นับ
 *   paid = ส่วนที่ผู้ป่วยชำระเอง (paid_money)
 *   สิทธิ = pttype.hipdata_code (รหัสกองทุน สปสช.) รวมเป็นกลุ่มตาม FIN_GROUPS
 * เทียบ: cur = วันที่ 1 ถึงวันนี้ (หรือสิ้นเดือนถ้าเป็นเดือนที่จบแล้ว)
 *        prev_same = เดือนก่อน วันที่ 1 ถึงวันเดียวกัน (เทียบแบบยุติธรรมระหว่างเดือน) · prev_full = เดือนก่อนทั้งเดือน
 * ยอดเดือนปัจจุบันยังเปลี่ยนได้ (ค่าใช้จ่ายบันทึกย้อนหลัง) — ตรวจกับ SQL ตรงแล้ว 27 พ.ย. 2569
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

$month = $_GET['month'] ?? date('Y-m');
$curStart = DateTime::createFromFormat('!Y-m-d', (is_string($month) ? $month : '') . '-01');
if (!$curStart || $curStart->format('Y-m') !== $month || $month > date('Y-m')) {
    http_response_code(400);
    echo json_encode(['error' => 'เดือนไม่ถูกต้อง (YYYY-MM)'], JSON_UNESCAPED_UNICODE);
    exit;
}

$isCurrent  = $month === date('Y-m');
$curEnd     = $isCurrent ? date('Y-m-d') : $curStart->format('Y-m-t');
$days       = (int)substr($curEnd, 8, 2);
$prevStart  = (clone $curStart)->modify('-1 month');
$prevEnd    = $prevStart->format('Y-m-t');
$prevSame   = $prevStart->format('Y-m-') . sprintf('%02d', min($days, (int)$prevStart->format('t')));

require_once '../config/cache.php';
api_cache('finance_income_pttype_' . $month . '_' . $curEnd, $isCurrent ? 300 : 1800);
require_once '../config/database.php';

try {
    $sql = "SELECT x.src,
            COALESCE(x.hip, '') AS hip,
            CASE WHEN x.d >= :curStart THEN 'cur'
                 WHEN x.d <= :prevSame THEN 'prev_same'
                 ELSE 'prev_rest' END AS bucket,
            COUNT(*) AS n,
            COALESCE(SUM(x.income), 0) AS income,
            COALESCE(SUM(x.paid), 0) AS paid
        FROM (
            SELECT 'opd' AS src, v.vstdate AS d, p.hipdata_code AS hip, v.income, v.paid_money AS paid
            FROM vn_stat v LEFT JOIN pttype p ON p.pttype = v.pttype
            WHERE v.vstdate BETWEEN :s1 AND :e1
            UNION ALL
            SELECT 'ipd', a.dchdate, p.hipdata_code, a.income, a.paid_money
            FROM an_stat a LEFT JOIN pttype p ON p.pttype = a.pttype
            WHERE a.dchdate BETWEEN :s2 AND :e2
        ) x
        GROUP BY 1, 2, 3";

    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':curStart' => $curStart->format('Y-m-d'),
        ':prevSame' => $prevSame,
        ':s1' => $prevStart->format('Y-m-d'), ':e1' => $curEnd,
        ':s2' => $prevStart->format('Y-m-d'), ':e2' => $curEnd,
    ]);

    $codeToGroup = [];
    foreach (FIN_GROUPS as $key => [, $codes]) {
        foreach ($codes as $c) {
            $codeToGroup[$c] = $key;
        }
    }

    $zero = fn() => ['income' => 0.0, 'opd' => 0.0, 'ipd' => 0.0, 'paid' => 0.0, 'visits' => 0, 'discharges' => 0];
    $blank = fn() => ['cur' => $zero(), 'prev_same' => $zero(), 'prev_full' => $zero()];
    $groups = [];
    foreach (FIN_GROUPS as $key => [$label, $codes]) {
        $groups[$key] = ['key' => $key, 'label' => $label, 'codes' => $codes] + $blank();
    }
    $total = $blank();

    $add = function (array &$slot, array $r) {
        $inc = (float)$r['income'];
        $slot['income'] += $inc;
        $slot[$r['src']] += $inc;
        $slot['paid'] += (float)$r['paid'];
        $slot[$r['src'] === 'opd' ? 'visits' : 'discharges'] += (int)$r['n'];
    };

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $g = $codeToGroup[$r['hip']] ?? 'oth';
        // prev_full = prev_same + prev_rest
        $slots = $r['bucket'] === 'cur' ? ['cur'] : ($r['bucket'] === 'prev_same' ? ['prev_same', 'prev_full'] : ['prev_full']);
        foreach ($slots as $s) {
            $add($groups[$g][$s], $r);
            $add($total[$s], $r);
        }
    }

    $round = function (array $p) {
        foreach (['cur', 'prev_same', 'prev_full'] as $s) {
            foreach (['income', 'opd', 'ipd', 'paid'] as $k) {
                $p[$s][$k] = round($p[$s][$k], 2);
            }
        }
        return $p;
    };
    $groups = array_map($round, array_values($groups));
    usort($groups, fn($a, $b) => $b['cur']['income'] <=> $a['cur']['income']);

    echo json_encode([
        'month'     => $month,
        'cur'       => ['start' => $curStart->format('Y-m-d'), 'end' => $curEnd, 'complete' => !$isCurrent],
        'prev'      => ['start' => $prevStart->format('Y-m-d'), 'same_end' => $prevSame, 'end' => $prevEnd],
        'total'     => $round($total),
        'groups'    => $groups,
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log('finance_income_pttype: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'โหลดข้อมูลไม่สำเร็จ'], JSON_UNESCAPED_UNICODE);
}
