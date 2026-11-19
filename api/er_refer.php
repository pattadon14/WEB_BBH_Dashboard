<?php

/*
 * การส่งต่อผู้ป่วย ER ตามช่วงเวลา (?d1&d2 หรือ ?year) — แยกตามสถานพยาบาล
 *
 * out = visit ER ที่ผลจำหน่าย = Refer (ER_DCH_SQL = 2 — ชุดเดียวกับการ์ด "ผลการจำหน่าย"; Admit มาก่อน Refer)
 * ipd = Admit แล้วส่งต่อจากหอผู้ป่วย (ER_IPD_REFER_SQL) — ไม่ซ้อนกับ out
 * in  = ER_REFER_IN_SQL (วิธีมา 04 หรือมีใบ referin) · ต้นทางเป็นรหัส รพ.เอง (opdconfig.hospitalcode) = self (บันทึกผิด)
 * ปลายทาง/ต้นทาง/เหตุผล = ใบที่เลือกด้วย ER_*_PICK ใน config/er.php (ชุดเดียวกับรายชื่อ api/er_visit_list.php)
 * ไม่มีใบเลย = no_doc · นับ visit · l12 = ระดับ 1–2 · ชื่อ รพ. ย่อและรวมรหัสที่ชื่อเดียวกัน (er_hosp_short)
 * ข้อมูลจริง 1 ต.ค.–18 พ.ย. 2569: out 319 (ไม่มีใบ 37) · in 82 (ไม่มีใบ 12, รหัส รพ.เอง 2)
 */

header('Content-Type: application/json; charset=utf-8');

require_once '../config/database.php';
require_once '../config/period.php';
require_once '../config/er.php';

try {

    $p = bbh_period();

    $cacheDir  = __DIR__ . '/../storage/cache/';
    $cacheFile = $cacheDir . 'er_refer_v2_' . $p['key'] . '.json';
    if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 5 * 60) {
        echo file_get_contents($cacheFile);
        exit;
    }

    $level = ER_LEVEL_SQL;
    $args  = [':d1' => $p['start'], ':d2' => $p['end']];
    $run = function (string $pick, string $alias, string $where, string $extra = 'NULL') use ($conn, $level, $args) {
        $st = $conn->prepare("
            SELECT $alias.code, h.name, rf.name AS reason, ($level) IN (1, 2) AS l12, $extra AS flag, COUNT(*) AS n
            FROM er_regist e
            LEFT JOIN er_emergency_type et ON et.er_emergency_type = e.er_emergency_type
            LEFT JOIN ovst o ON o.vn = e.vn
            $pick
            LEFT JOIN hospcode h ON h.hospcode = $alias.code
            LEFT JOIN rfrcs rf   ON rf.rfrcs = " . ($alias === 'rin' ? 'NULL' : "$alias.rfrcs") . "
            WHERE e.vstdate BETWEEN :d1 AND :d2 AND $where
            GROUP BY 1, 2, 3, 4, 5
        ");
        $st->execute($args);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    };

    $out = $run(ER_REFER_OUT_PICK, 'ro', '(' . ER_DCH_SQL . ') = 2');
    $ipd = $run(ER_IPD_REFER_PICK, 'rq', ER_IPD_REFER_SQL, 'rq.transfer');
    $in  = $run(ER_REFER_IN_PICK, 'rin', ER_REFER_IN_SQL, 'rin.code = (SELECT hospitalcode FROM opdconfig LIMIT 1)');

    $tot = $conn->prepare("SELECT COUNT(*) FROM er_regist WHERE vstdate BETWEEN :d1 AND :d2");
    $tot->execute($args);

    $result = [
        'er_total' => (int)$tot->fetchColumn(),
        'out' => er_refer_group($out),
        'ipd' => er_refer_group($ipd, 'transfer'),
        'in'  => er_refer_group($in, 'self'),
    ];

    $json = json_encode($result, JSON_UNESCAPED_UNICODE);
    if (!is_dir($cacheDir)) {
        mkdir($cacheDir, 0755, true);
    }
    file_put_contents($cacheFile, $json);
    echo $json;

} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    error_log('er_refer: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'โหลดข้อมูลไม่สำเร็จ'], JSON_UNESCAPED_UNICODE);
}

// รวมแถวตามชื่อ รพ. (ย่อแล้ว) → {total, l12, no_doc, items:[{name,n,l12}], reasons:[{name,n}]} เรียงมากไปน้อย
// $flag: 'self' = ต้นทางเป็น รพ.เอง (ไม่นับเป็นรายการ รพ.) · 'transfer' = นับจำนวนที่จำหน่ายแบบ By Transfer
function er_refer_group(array $rows, ?string $flag = null): array
{
    $g = ['total' => 0, 'l12' => 0, 'no_doc' => 0];
    if ($flag) {
        $g[$flag] = 0;
    }
    $items = [];
    $reasons = [];
    foreach ($rows as $r) {
        $n = (int)$r['n'];
        $l12 = $r['l12'] ? $n : 0;
        $g['total'] += $n;
        $g['l12'] += $l12;
        $on = $flag && !empty($r['flag']);
        if ($flag === 'transfer' && $on) {
            $g['transfer'] += $n;
        }
        if ($flag === 'self' && $on) {
            $g['self'] += $n;
            continue;
        }
        $name = er_hosp_short($r['name'] ?: $r['code']);
        if ($name === null) {
            $g['no_doc'] += $n;
            continue;
        }
        $items[$name]['n'] = ($items[$name]['n'] ?? 0) + $n;
        $items[$name]['l12'] = ($items[$name]['l12'] ?? 0) + $l12;
        if ($r['reason'] !== null) {
            $reasons[$r['reason']] = ($reasons[$r['reason']] ?? 0) + $n;
        }
    }
    uasort($items, fn($a, $b) => $b['n'] <=> $a['n']);
    arsort($reasons);
    $g['items'] = array_map(fn($k, $v) => ['name' => (string)$k] + $v, array_keys($items), $items);
    $g['reasons'] = array_map(fn($k, $v) => ['name' => (string)$k, 'n' => $v], array_keys($reasons), $reasons);
    return $g;
}
