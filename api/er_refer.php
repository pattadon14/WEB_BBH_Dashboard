<?php

/*
 * การส่งต่อผู้ป่วย ER ตามช่วงเวลา (?d1&d2 หรือ ?year) — แยกตามสถานพยาบาล
 *
 * out = visit ER ที่ผลจำหน่าย = Refer (ER_DCH_SQL = 2 — ชุดเดียวกับการ์ด "ผลการจำหน่าย"; Admit มาก่อน Refer)
 *       ปลายทาง/เหตุผล = ใบ referout ของ visit (ใบจากจุด ER ก่อน, ไม่นับใบที่ยกเลิก) · ไม่มีใบเลย = no_doc
 * in  = visit ER ที่ ER_REFER_IN_SQL (วิธีมา 04 หรือมีใบ referin) · ต้นทาง = referin.refer_hospcode · ไม่มีใบ = no_doc
 *       ต้นทางเป็นรหัส รพ.เอง (opdconfig.hospitalcode) = self — บันทึกผิด แสดงแยกให้เห็น
 * นับ visit (1 visit = 1 ครั้ง แม้มีหลายใบ) · ชื่อ รพ. ย่อและรวมรหัสที่ชื่อเดียวกัน (เช่น 10662 / EA0010662)
 * ข้อมูลจริง 1 ต.ค.–18 พ.ย. 2569: out 323 visit (ไม่มีใบ 37) · in 82 visit (ไม่มีใบ 12, รหัส รพ.เอง 2)
 */

header('Content-Type: application/json; charset=utf-8');

require_once '../config/database.php';
require_once '../config/period.php';
require_once '../config/er.php';

try {

    $p = bbh_period();

    $cacheDir  = __DIR__ . '/../storage/cache/';
    $cacheFile = $cacheDir . 'er_refer_v1_' . $p['key'] . '.json';
    if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 5 * 60) {
        echo file_get_contents($cacheFile);
        exit;
    }

    $dch   = ER_DCH_SQL;
    $refIn = ER_REFER_IN_SQL;
    $args  = [':d1' => $p['start'], ':d2' => $p['end']];

    $out = $conn->prepare("
        WITH v AS (
            SELECT e.vn FROM er_regist e
            WHERE e.vstdate BETWEEN :d1 AND :d2 AND ($dch) = 2
        ), d AS (
            SELECT DISTINCT ON (v.vn) v.vn, r.refer_hospcode AS code, r.rfrcs
            FROM v
            LEFT JOIN referout r ON r.vn = v.vn AND COALESCE(r.cancel_status, '') <> 'Y'
            ORDER BY v.vn, (r.refer_point = 'ER') DESC NULLS LAST, r.referout_id
        )
        SELECT d.code, h.name, rf.name AS reason, COUNT(*) AS n
        FROM d
        LEFT JOIN hospcode h ON h.hospcode = d.code
        LEFT JOIN rfrcs rf   ON rf.rfrcs = d.rfrcs
        GROUP BY 1, 2, 3
    ");
    $out->execute($args);

    $in = $conn->prepare("
        WITH v AS (
            SELECT e.vn FROM er_regist e JOIN ovst o ON o.vn = e.vn
            WHERE e.vstdate BETWEEN :d1 AND :d2 AND $refIn
        ), d AS (
            SELECT DISTINCT ON (v.vn) v.vn, ri.refer_hospcode AS code
            FROM v LEFT JOIN referin ri ON ri.vn = v.vn
            ORDER BY v.vn, ri.referin_id
        )
        SELECT d.code, h.name, NULL AS reason, COUNT(*) AS n,
               d.code = (SELECT hospitalcode FROM opdconfig LIMIT 1) AS self
        FROM d LEFT JOIN hospcode h ON h.hospcode = d.code
        GROUP BY 1, 2, 5
    ");
    $in->execute($args);

    $result = [
        'out' => er_refer_group($out->fetchAll(PDO::FETCH_ASSOC)),
        'in'  => er_refer_group($in->fetchAll(PDO::FETCH_ASSOC)),
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

// รวมแถวตามชื่อ รพ. (ย่อแล้ว) → {total, no_doc, self, items:[{name,n}], reasons:[{name,n}]} เรียงมากไปน้อย
function er_refer_group(array $rows): array
{
    $total = 0; $noDoc = 0; $self = 0; $items = []; $reasons = [];
    foreach ($rows as $r) {
        $n = (int)$r['n'];
        $total += $n;
        if ($r['code'] === null || $r['code'] === '') { $noDoc += $n; continue; }
        if (!empty($r['self'])) { $self += $n; continue; }
        $name = er_hosp_short($r['name'] ?: $r['code']);
        $items[$name] = ($items[$name] ?? 0) + $n;
        if ($r['reason'] !== null) {
            $reasons[$r['reason']] = ($reasons[$r['reason']] ?? 0) + $n;
        }
    }
    $list = fn(array $a) => array_map(fn($k, $v) => ['name' => (string)$k, 'n' => $v], array_keys($a), $a);
    arsort($items);
    arsort($reasons);
    return ['total' => $total, 'no_doc' => $noDoc, 'self' => $self, 'items' => $list($items), 'reasons' => $list($reasons)];
}

// "โรงพยาบาลพญาไทศรีราชาโรงพยาบาลทั่วไปขนาดใหญ่" → "รพ.พญาไทศรีราชา"
function er_hosp_short(string $name): string
{
    $s = preg_replace('/\s*โรงพยาบาลทั่วไป\s*ขนาด(ใหญ่|กลาง|เล็ก)\s*/u', ' ', trim($name));
    $s = preg_replace('/\s*ขนาด(ใหญ่|กลาง|เล็ก)\s*$/u', '', $s);
    $s = str_replace(['โรงพยาบาลส่งเสริมสุขภาพตำบล', 'โรงพยาบาล'], ['รพ.สต.', 'รพ.'], $s);
    $s = trim(preg_replace('/\s+/u', ' ', $s));
    return preg_match('/^(รพ\.|คลินิก)|คลินิก/u', $s) ? $s : 'รพ.' . $s;
}
