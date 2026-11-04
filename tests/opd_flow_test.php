<?php
/*
 * ทดสอบตรรกะ visit → จุดบริการของ 3D Monitor (ไม่ต่อฐานข้อมูล) · รัน: php tests/opd_flow_test.php
 */
if (PHP_SAPI !== 'cli') {
    exit;
}
require __DIR__ . '/../api/opd_flow_lib.php';

$idx = [];
foreach (FLOW_AREAS as $i => $a) {
    $idx[$a[0]] = $i;
}
$deps = [
    'OPD' => ['name' => 'ห้องบัตร', 'group' => null],
    'EMR' => ['name' => 'ห้องฉุกเฉิน', 'group' => flow_classify('ห้องฉุกเฉิน')],
    'DEN' => ['name' => 'ทันตกรรม', 'group' => flow_classify('ทันตกรรม')],
    'R03' => ['name' => 'ห้องตรวจ 3', 'group' => flow_classify('ห้องตรวจ 3')],
];
function row(array $v): array
{
    $keys = array_merge(FLOW_ORDER, ['home', 'md', 'cd', 'd3', 'd4', 'd5', 'd7', 'd12', 'd20']);
    return array_merge(array_fill_keys($keys, null), $v);
}
function check(string $name, $got, $want): void
{
    if ($got !== $want) {
        fwrite(STDERR, "FAIL $name\n  got:  " . json_encode($got) . "\n  want: " . json_encode($want) . "\n");
        exit(1);
    }
    echo "ok   $name\n";
}

check('classify ER', $deps['EMR']['group'], 'ER');
check('classify room 3', $deps['R03']['group'], 'R3');
check('classify dent', $deps['DEN']['group'], 'DENT');

// OPD ทั่วไป: ลงทะเบียน → ซักประวัติ → รอหน้าห้องตรวจ → ห้องตรวจ 3 → ห้องยา → ส่งกลับบ้าน
[$s, $e] = flow_segments(row(['reg' => 480.0, 's4' => 490.0, 's11' => 500.0, 's5' => 530.0, 'd5' => 'R03',
    's12' => 540.0, 's16' => 560.0, 'home' => 565.0, 'md' => 'OPD']), $deps, $idx, null);
check('opd segments', $s, [[480.0, 'REG'], [483.0, 'SCR'], [500.0, 'DRW'], [530.0, 'R3'], [540.0, 'RX']]);
check('opd exit = send-home time', $e, 565.0);

// แบบบ้านบึง: ไม่มีเวลาส่งตรวจ/คัดกรองเสร็จ/ตรวจเสร็จ → ประมาณขั้นที่ขาด แล้วอยู่ห้องตรวจจนถึงห้องยา
[$s, $e] = flow_segments(row(['reg' => 480.0, 's4' => 500.0, 's5' => 560.0, 's6' => 585.0, 's7' => 580.0,
    's16' => 600.0, 'md' => 'OPD']), $deps, $idx, null);
check('banbung estimated steps', $s, [[480.0, 'REG'], [483.0, 'SCR'], [505.0, 'DRW'], [560.0, 'R2'], [580.0, 'RX']]);
check('banbung exit after dispense', $e, 602.0);

// ไปรษณีย์ / PCU = ไม่ได้มา รพ.
check('classify remote post', flow_classify('ส่งเวชภัณฑ์ทางไปรษณีย์'), 'REMOTE');
check('classify remote pcu', flow_classify('234 PCU การเงิน'), 'REMOTE');
check('classify remote post with code', flow_classify('143 ส่งเวชภัณฑ์ทางไปรษณีย์'), 'REMOTE');
check('classify tele with code', flow_classify('178 Telemedicine'), 'TELE');

// ER: ลงทะเบียนที่ ER, อยู่ 20 นาทีหลังเข้า ER แล้วออก
[$s, $e] = flow_segments(row(['reg' => 600.0, 'er_in' => 605.0, 'er_out' => 625.0, 'md' => 'EMR']), $deps, $idx, null);
check('er segments', $s, [[600.0, 'ER']]);
check('er exit', $e, 627.0);

// ทันตกรรม: กดตรวจเสร็จทันทีแต่ยังรอทำฟันในคลินิก
[$s, $e] = flow_segments(row(['reg' => 500.0, 's5' => 510.0, 'd5' => 'DEN', 's12' => 512.0, 'md' => 'DEN']), $deps, $idx, null);
check('dent segments', $s, [[500.0, 'REG'], [510.0, 'DENT']]);
check('dent linger', $e, 572.0);

// เวลาก่อนลงทะเบียน (กดผิด) ไม่นับ
[$s] = flow_segments(row(['reg' => 500.0, 's4' => 400.0, 'md' => 'OPD']), $deps, $idx, null);
check('ignore time before reg', $s, [[500.0, 'REG']]);

check('urgency er level', flow_urgency('2', '0'), 2);
check('urgency opd emergency', flow_urgency(null, '4'), 2);
check('urgency elderly', flow_urgency(null, '3'), 6);
check('urgency normal', flow_urgency(null, '0'), 5);
echo "all passed\n";
