<?php

/*
 * งานฝากครรภ์ — ช่อง KPI + แนวโน้มรายเดือน (pages/ANC_detail.php ส่วนที่ 1–2)
 *   ?d1&d2 หรือ ?year (config/period.php) · scope = area (ตัวหารในเขต, ค่าเริ่มต้น) | all · nat = th | fr | (ว่าง = ทั้งหมด)
 * คืนเฉพาะจำนวนนับ ไม่มีข้อมูลรายบุคคล · นิยามตัวชี้วัดดู config/anc.php (ตรงกับต้นแบบ metrics.py)
 */

header('Content-Type: application/json; charset=utf-8');

require_once '../config/database.php';
require_once '../config/anc.php';

anc_require_login();

try {
    $p = bbh_period();
    // ปีงบปัจจุบันนับถึงวันนี้ (เหมือนต้นแบบ) — วันอนาคตทำให้ "กำลังติดตาม" / "บันทึกวันคลอด" ผิด
    $p['end'] = min($p['end'], date('Y-m-d'));
    if ($p['start'] > $p['end']) {
        throw new InvalidArgumentException('ช่วงวันที่ยังไม่เริ่ม');
    }

    $scope = ($_GET['scope'] ?? 'area') === 'all' ? 'all' : 'area';
    $nat = in_array($_GET['nat'] ?? '', ['th', 'fr'], true) ? $_GET['nat'] : '';
    $p['key'] = $p['start'] . '_' . $p['end'];

    ipd_cached("anc_summary_v1_{$scope}_" . ($nat ?: 'all'), $p, function () use ($conn, $p, $scope, $nat) {
        [$pregs, $visits, $refers] = anc_load($conn, $p['start'], $p['end'], $nat);
        return ['d1' => $p['start'], 'd2' => $p['end'], 'scope' => $scope, 'nat' => $nat]
            + anc_summary($pregs, $visits, $refers, $p['start'], $p['end'], $scope === 'area', $nat !== '');
    }, ANC_CACHE_LIVE_TTL);
} catch (Throwable $e) {
    ipd_fail($e, 'anc_summary');
}
