<?php

/*
 * งานฝากครรภ์ — ตัวชี้วัดคุณภาพ 10 ตัว (pages/ANC_detail.php ส่วนที่ 3)
 *   ?d1&d2 หรือ ?year · scope = area (ค่าเริ่มต้น) | all · nat = th | fr | (ว่าง = ทั้งหมด)
 * คืนเฉพาะจำนวนนับ ไม่มีข้อมูลรายบุคคล · นิยามดู config/anc_ind.php (ตรงกับต้นแบบ metrics.py)
 */

header('Content-Type: application/json; charset=utf-8');

require_once '../config/database.php';
require_once '../config/anc_ind.php';

try {
    $p = bbh_period();
    $p['end'] = min($p['end'], date('Y-m-d'));
    if ($p['start'] > $p['end']) {
        throw new InvalidArgumentException('ช่วงวันที่ยังไม่เริ่ม');
    }

    $scope = ($_GET['scope'] ?? 'area') === 'all' ? 'all' : 'area';
    $nat = in_array($_GET['nat'] ?? '', ['th', 'fr'], true) ? $_GET['nat'] : '';
    $p['key'] = $p['start'] . '_' . $p['end'];

    ipd_cached("anc_ind_v1_{$scope}_" . ($nat ?: 'all'), $p, function () use ($conn, $p, $scope, $nat) {
        [$pregs, $visits] = anc_load($conn, $p['start'], $p['end'], $nat);
        [$extra, $infants, $pnc] = anc_load_extra($conn, $p['start'], $p['end']);
        return ['d1' => $p['start'], 'd2' => $p['end'], 'scope' => $scope, 'nat' => $nat]
            + anc_indicators($pregs, $visits, $extra, $infants, $pnc, $p['start'], $p['end'], $scope === 'area', $nat !== '');
    });
} catch (Throwable $e) {
    ipd_fail($e, 'anc_indicators');
}
