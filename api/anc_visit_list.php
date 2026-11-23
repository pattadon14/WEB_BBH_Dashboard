<?php
/*
 * รายชื่อหญิงตั้งครรภ์เบื้องหลังตัวเลขหน้า ANC (pages/ANC_detail.php)
 * GET api/anc_visit_list.php?type=<ชนิด>&d1&d2|year&scope=area|all&nat=th|fr
 *
 * ข้อมูลส่วนบุคคล (PDPA): ต้อง login Provider ID · ไม่ cache ลงดิสก์ · Cache-Control no-store
 * ตัวหาร/ตัวตั้งของแต่ละชนิดใช้ฟังก์ชันชุดเดียวกับ api/anc_summary.php / anc_indicators.php / anc_refer.php
 *   ตัวชี้วัดที่หารด้วยครรภ์ที่คลอด: คืนทุกรายในตัวหาร พร้อมคอลัมน์ "ผล" (ผ่าน / ไม่ผ่าน) ให้กรองในหน้าต่างรายชื่อ
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');

require_once __DIR__ . '/../config/anc_ind.php';
require_once __DIR__ . '/../config/anc_refer.php';
anc_require_login();
require_once __DIR__ . '/../config/database.php';

const ANC_LIST_TYPES = ['new', 'open', 'labor_missing', 'early12', 'visit5', 'visit8', 'postnatal', 'quality', 'iodine',
    'dt', 'dental', 'anemia', 'teen_repeat', 'preterm', 'refer'];
const ANC_LIST_MAX = 20000;

try {
    $type = (string)($_GET['type'] ?? '');
    if (!in_array($type, ANC_LIST_TYPES, true)) {
        throw new InvalidArgumentException('ชนิดรายชื่อไม่ถูกต้อง');
    }
    $p = bbh_period();
    [$d1s, $d2s] = [$p['start'], min($p['end'], date('Y-m-d'))];
    if ($d1s > $d2s) {
        throw new InvalidArgumentException('ช่วงวันที่ยังไม่เริ่ม');
    }
    $area = ($_GET['scope'] ?? 'area') !== 'all';
    $nat = in_array($_GET['nat'] ?? '', ['th', 'fr'], true) ? $_GET['nat'] : '';

    [$pregs, $visits] = anc_load($conn, $d1s, $d2s, $nat);
    $needExtra = in_array($type, ['postnatal', 'quality', 'iodine', 'dt', 'dental', 'anemia', 'teen_repeat'], true);
    [$extra, , $pnc] = $needExtra ? anc_load_extra($conn, $d1s, $d2s) : [[], [], []];
    $d1 = anc_day($d1s);
    $d2 = anc_day($d2s);
    $in = fn($d) => $d !== null && $d >= $d1 && $d <= $d2;
    $iso = fn(?int $d) => $d === null ? null : gmdate('Y-m-d', $d * 86400);
    $delivered = fn($pp) => $in($pp['labor']) && (!$area || anc_in_area($pp));

    $rows = [];
    if ($type === 'refer') {
        $refers = anc_load_refers($conn, $d1s, $d2s);
        $sum = anc_refer_list($pregs, $refers, $nat !== '');
        foreach ($sum as $r) {
            $rows[] = ['hn' => $r['hn'], 'date' => $r['refer_date'], 'age' => $r['age_used'], 'group' => $r['group'],
                'pdx' => $r['pdx'], 'dx_name' => $r['dx_name'], 'dest' => $r['dest_name'] ?: $r['dest'],
                'reason' => $r['reason']];
        }
    } else {
        foreach ($pregs as $id => $pp) {
            $vs = $visits[$id] ?? [];
            $x = $extra[$id] ?? [];
            $res = null;
            switch ($type) {
                case 'new':
                    if (!$in($pp['register'])) continue 2;
                    break;
                case 'open':
                    if (!anc_is_open($pp, $d2)) continue 2;
                    break;
                case 'labor_missing':
                    if (!anc_is_due($pp, $d1, $d2) || $pp['labor'] !== null) continue 2;
                    break;
                case 'iodine':
                    if (!anc_came_in($vs, $d1, $d2)) continue 2;
                    $res = !empty($x['has_iodine']);
                    break;
                case 'postnatal':
                    if (!$delivered($pp) || !anc_pnc_due($pp, $d2)) continue 2;
                    $res = anc_pnc_ok($pp, $pnc[$id] ?? []);
                    break;
                default:   // ตัวหาร = ครรภ์ที่คลอดในช่วง
                    if (!$delivered($pp)) continue 2;
                    if ($type === 'anemia') {
                        if (($x['hct_late'] ?? null) === null) continue 2;
                        $res = $x['hct_late'] < ANC_HCT_CUTOFF;
                    } elseif ($type === 'teen_repeat') {
                        if (($x['age_labor'] ?? null) === null || (int)$x['age_labor'] >= 20) continue 2;
                        $res = (int)($x['gravida'] ?? 0) >= 2;
                    } elseif ($type === 'preterm') {
                        if ($pp['lmp'] === null) continue 2;
                        $res = $pp['labor'] - $pp['lmp'] < ANC_PRETERM_DAYS;
                    } else {
                        $res = match ($type) {
                            'early12' => anc_is_early12($pp, $vs),
                            'visit5' => anc_is_5visit($pp, $vs),
                            'visit8' => anc_is_ge_buckets($pp, $vs, ANC_GA_BUCKETS_8),
                            'quality' => !in_array(false, array_map(fn($k) => anc_quality_part($x, $k), array_keys(ANC_QUALITY_PARTS)), true),
                            'dt' => !empty($x['has_dt']),
                            'dental' => !empty($x['has_dental']),
                        };
                    }
            }
            $first = null;
            $nAnc = 0;
            foreach ($vs as $v) {
                if ($v['date'] !== null && ($first === null || $v['date'] < $first['date'])) {
                    $first = $v;
                }
                $nAnc += $v['precare'] ? 0 : 1;
            }
            $missing = [];
            if ($type === 'visit5' || $type === 'visit8') {
                $buckets = $type === 'visit5' ? ANC_GA_BUCKETS : ANC_GA_BUCKETS_8;
                $have = [];
                foreach ($vs as $v) {
                    $ga = anc_visit_ga($v, $pp['lmp']);
                    foreach ($buckets as $i => [$lo, $hi]) {
                        if ($ga !== null && $ga >= $lo && $ga <= $hi) {
                            $have[$i] = true;
                        }
                    }
                }
                foreach ($buckets as $i => [$lo, $hi]) {
                    if (!isset($have[$i])) {
                        $missing[] = $lo ? "{$lo}–{$hi}" : "≤{$hi}";
                    }
                }
            }
            $rows[] = ['hn' => $pp['hn'], 'age' => $pp['age'], 'group' => anc_age_group($pp['age']),
                'nat' => $pp['thai'] ? 'ไทย' : 'ต่างชาติ', 'register' => $iso($pp['register']), 'lmp' => $iso($pp['lmp']),
                'labor' => $iso($pp['labor']), 'ga1' => $first ? anc_visit_ga($first, $pp['lmp']) : null, 'n_anc' => $nAnc,
                'missing' => implode(', ', $missing), 'hct' => $type === 'anemia' ? $x['hct_late'] : null,
                'gravida' => $type === 'teen_repeat' ? (int)($x['gravida'] ?? 0) : null,
                'result' => $res === null ? null : ($res ? 'ใช่' : 'ไม่ใช่')];
        }
    }
    if (count($rows) > ANC_LIST_MAX) {
        http_response_code(413);
        echo json_encode(['error' => 'รายชื่อมากเกินไป กรุณาเลือกช่วงวันที่สั้นลง'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ชื่อ-ที่อยู่ ดึงเฉพาะ HN ในรายการ (prepared statement ทีละชุด)
    $hns = array_values(array_unique(array_filter(array_column($rows, 'hn'))));
    $names = [];
    foreach (array_chunk($hns, 500) as $chunk) {
        $ph = implode(',', array_fill(0, count($chunk), '?'));
        $st = $conn->prepare("SELECT hn, pname, fname, lname, addrpart, moopart FROM patient WHERE hn IN ($ph)");
        $st->execute($chunk);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $moo = trim((string)$r['moopart']);
            $names[$r['hn']] = [trim(trim((string)$r['pname']) . trim((string)$r['fname']) . ' ' . trim((string)$r['lname'])),
                trim(trim((string)$r['addrpart']) . (ctype_digit($moo) ? ' ม.' . (int)$moo : ''))];
        }
    }
    $groupTh = ['teen' => 'อายุ < 20', 'repro' => 'อายุ 20–34', 'elder' => 'อายุ ≥ 35'];
    foreach ($rows as &$r) {
        [$r['name'], $r['addr']] = $names[(string)$r['hn']] ?? ['', ''];
        $r['group'] = $groupTh[(string)$r['group']] ?? 'ไม่ทราบอายุ';
    }
    unset($r);
    echo json_encode(['type' => $type, 'd1' => $d1s, 'd2' => $d2s, 'rows' => $rows], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    ipd_fail($e, 'anc_visit_list');
}
