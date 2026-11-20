<?php

/*
 * งานฝากครรภ์ — คัดกรอง: ?part=risk27 | sti | ogtt | anemia (pages/ANC_detail.php)
 *   ?d1&d2 หรือ ?year · scope = area | all (ใช้กับ anemia) · nat = th | fr | (ว่าง)
 * คืนเฉพาะจำนวนนับ ไม่มีข้อมูลรายบุคคล · นิยามดู config/anc_screen.php (ตรงกับต้นแบบ risk27/sti/ogtt/anemia.py)
 */

header('Content-Type: application/json; charset=utf-8');

require_once '../config/database.php';
require_once '../config/anc_screen.php';

anc_require_login();

try {
    $part = $_GET['part'] ?? '';
    if (!in_array($part, ['risk27', 'sti', 'ogtt', 'anemia'], true)) {
        throw new InvalidArgumentException('part ไม่ถูกต้อง');
    }
    $p = bbh_period();
    $p['end'] = min($p['end'], date('Y-m-d'));
    if ($p['start'] > $p['end']) {
        throw new InvalidArgumentException('ช่วงวันที่ยังไม่เริ่ม');
    }
    $scope = ($_GET['scope'] ?? 'area') === 'all' ? 'all' : 'area';
    $nat = in_array($_GET['nat'] ?? '', ['th', 'fr'], true) ? $_GET['nat'] : '';
    $p['key'] = $p['start'] . '_' . $p['end'];
    $name = "anc_{$part}_v1_" . ($part === 'anemia' ? $scope . '_' : '') . ($nat ?: 'all');

    ipd_cached($name, $p, function () use ($conn, $p, $part, $scope, $nat) {
        [$d1, $d2] = [$p['start'], $p['end']];
        [$pregs] = anc_load($conn, $d1, $d2, $nat);
        $data = match ($part) {
            'risk27' => anc_risk27($pregs, anc_risk_hits($conn, $d1, $d2), $d1, $d2),
            'sti' => anc_sti($conn, $pregs, $d1, $d2),
            'ogtt' => anc_ogtt($conn, $pregs, $d1, $d2),
            'anemia' => anc_anemia($conn, $pregs, $d1, $d2, $scope === 'area'),
        };
        return ['d1' => $d1, 'd2' => $d2, 'part' => $part, 'data' => $data];
    }, ANC_CACHE_LIVE_TTL);
} catch (Throwable $e) {
    ipd_fail($e, 'anc_screening');
}
