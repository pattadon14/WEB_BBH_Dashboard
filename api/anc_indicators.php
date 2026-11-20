<?php

/*
 * งานฝากครรภ์ — ตัวชี้วัดคุณภาพ + กลุ่มอายุ + ผลลัพธ์แม่และทารก + รายการที่ต้องติดตาม (pages/ANC_detail.php)
 *   ?d1&d2 หรือ ?year · scope = area (ค่าเริ่มต้น) | all · nat = th | fr | (ว่าง = ทั้งหมด)
 * คืนเฉพาะจำนวนนับ ไม่มีข้อมูลรายบุคคล · นิยามดู config/anc_ind.php (ตรงกับต้นแบบ metrics.py)
 * ชุดนี้ดึงข้อมูลหนัก (EXISTS ต่อครรภ์) ครั้งแรกของปีงบใช้เวลาหลายวินาที → cache ไฟล์ (ipd_cached)
 */

header('Content-Type: application/json; charset=utf-8');

require_once '../config/database.php';
require_once '../config/anc_ind.php';

anc_require_login();

try {
    $p = bbh_period();
    $p['end'] = min($p['end'], date('Y-m-d'));
    if ($p['start'] > $p['end']) {
        throw new InvalidArgumentException('ช่วงวันที่ยังไม่เริ่ม');
    }

    $scope = ($_GET['scope'] ?? 'area') === 'all' ? 'all' : 'area';
    $nat = in_array($_GET['nat'] ?? '', ['th', 'fr'], true) ? $_GET['nat'] : '';
    $p['key'] = $p['start'] . '_' . $p['end'];

    ipd_cached("anc_ind_v2_{$scope}_" . ($nat ?: 'all'), $p, function () use ($conn, $p, $scope, $nat) {
        [$d1, $d2, $area, $natF] = [$p['start'], $p['end'], $scope === 'area', $nat !== ''];
        [$pregs, $visits] = anc_load($conn, $d1, $d2, $nat);
        [$extra, $infants, $pnc] = anc_load_extra($conn, $d1, $d2);
        $ind = anc_indicators($pregs, $visits, $extra, $infants, $pnc, $d1, $d2, $area, $natF);
        $age = anc_by_age($pregs, $visits, $extra, $d1, $d2, $area);
        $outcome = anc_outcomes($pregs, $extra, $infants, $d1, $d2, $area, $natF);
        return ['d1' => $d1, 'd2' => $d2, 'scope' => $scope, 'nat' => $nat] + $ind
            + ['age' => $age, 'outcome' => $outcome, 'alerts' => anc_alerts($ind, $age, $outcome)];
    }, ANC_CACHE_LIVE_TTL);
} catch (Throwable $e) {
    ipd_fail($e, 'anc_indicators');
}
