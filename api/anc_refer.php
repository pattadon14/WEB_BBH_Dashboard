<?php

/*
 * งานฝากครรภ์ — ส่งต่องานแม่และเด็ก: รายเดือนแยกกลุ่มอายุ · ปลายทาง · Top 10 Dx · ตาราง เดือน × กลุ่ม Dx × ปลายทาง
 *   ?d1&d2 หรือ ?year · nat = th | fr | (ว่าง) — refer ไม่ใช้ตัวหาร จึงไม่มี scope
 * คืนเฉพาะจำนวนนับ · นิยามดู config/anc_refer.php
 */

header('Content-Type: application/json; charset=utf-8');

require_once '../config/database.php';
require_once '../config/anc_refer.php';

anc_require_login();

try {
    $p = bbh_period();
    $p['end'] = min($p['end'], date('Y-m-d'));
    if ($p['start'] > $p['end']) {
        throw new InvalidArgumentException('ช่วงวันที่ยังไม่เริ่ม');
    }
    $nat = in_array($_GET['nat'] ?? '', ['th', 'fr'], true) ? $_GET['nat'] : '';
    $p['key'] = $p['start'] . '_' . $p['end'];

    ipd_cached('anc_refer_v1_' . ($nat ?: 'all'), $p, function () use ($conn, $p, $nat) {
        [$pregs] = anc_load($conn, $p['start'], $p['end'], $nat);
        return ['d1' => $p['start'], 'd2' => $p['end'], 'nat' => $nat]
            + anc_refer_summary($pregs, anc_load_refers($conn, $p['start'], $p['end']), $p['start'], $p['end'], $nat !== '');
    }, ANC_CACHE_LIVE_TTL);
} catch (Throwable $e) {
    ipd_fail($e, 'anc_refer');
}
