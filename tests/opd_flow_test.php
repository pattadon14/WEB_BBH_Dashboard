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
    'PED' => ['name' => '134 จุดซักประวัติคลินิกเด็ก', 'group' => flow_classify('134 จุดซักประวัติคลินิกเด็ก')],
    'ARI' => ['name' => 'ห้องตรวจแพทย์ ARI', 'group' => flow_classify('ห้องตรวจแพทย์ ARI')],
];
function row(array $v): array
{
    $keys = array_merge(FLOW_ORDER, ['home', 'md', 'cd', 'd3', 'd4', 'd5', 'd7', 'd12', 'd20', 'ap', 'dr']);
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
check('classify ari before room', $deps['ARI']['group'], 'ARI');
check('classify room 8', flow_classify('ห้องตรวจ 8'), 'R8');
check('classify room 12 -> 10', flow_classify('ห้องตรวจ 12'), 'R10');

// OPD ทั่วไป: ลงทะเบียน → ซักประวัติ → รอหน้าห้องตรวจ → ห้องตรวจ 3 → ห้องยา → ส่งกลับบ้าน
[$s, $e] = flow_segments(row(['reg' => 480.0, 's4' => 490.0, 's11' => 500.0, 's5' => 530.0, 'd5' => 'R03',
    's12' => 540.0, 's16' => 560.0, 'home' => 565.0, 'md' => 'OPD']), $deps, $idx, null);
check('opd segments', $s, [[480.0, 'REG'], [483.0, 'SCRW'], [500.0, 'DRWW'], [530.0, 'R3'], [540.0, 'RX']]);
check('opd exit = send-home time', $e, 565.0);

// แบบบ้านบึง: ไม่มีเวลาส่งตรวจ/คัดกรองเสร็จ/ตรวจเสร็จ → ประมาณขั้นที่ขาด · ตรวจ 10 นาทีแล้วไปรอที่จุดถัดไป (การเงิน)
[$s, $e] = flow_segments(row(['reg' => 480.0, 's4' => 500.0, 's5' => 560.0, 's6' => 585.0, 's7' => 580.0,
    's16' => 600.0, 'md' => 'OPD']), $deps, $idx, null);
check('banbung estimated steps', $s, [[480.0, 'REG'], [483.0, 'SCRW'], [505.0, 'DRWW'], [560.0, 'R1'], [570.0, 'CASH'], [585.0, 'RX']]);
check('banbung exit after dispense', $e, 602.0);

// สั่งแล็บหลังตรวจ: ห้องตรวจ → แล็บ → รับสิ่งส่งตรวจแล้วกลับไปรอหน้าห้องตรวจ (ไม่ค้างหน้าแล็บ)
[$s] = flow_segments(row(['reg' => 480.0, 's4' => 490.0, 's5' => 530.0, 'd5' => 'R03', 'lo' => 535.0, 'lr' => 545.0,
    's6' => 620.0, 's16' => 640.0, 'md' => 'OPD']), $deps, $idx, null);
check('lab then back to wait', $s, [[480.0, 'REG'], [483.0, 'SCRW'], [495.0, 'DRWW'], [530.0, 'R3'], [535.0, 'LAB'],
    [545.0, 'DRWW'], [620.0, 'RX']]);

// ผู้ป่วยนัดเจาะเลือดก่อนซักประวัติ → หลังแล็บรับสิ่งส่งตรวจไปรอที่จุดซักประวัติฝั่งนัด ไม่ใช่หน้าห้องตรวจ
[$s] = flow_segments(row(['reg' => 400.0, 'lo' => 405.0, 'lr' => 415.0, 's4' => 470.0, 's5' => 530.0, 'd5' => 'R03',
    'ap' => true, 'md' => 'OPD']), $deps, $idx, null);
check('appt lab before screening', $s, [[400.0, 'REG'], [403.0, 'SCRA'], [405.0, 'LAB'], [415.0, 'SCRA'], [475.0, 'DRWW'],
    [530.0, 'R3']]);

// คลินิก ARV: ซักประวัติแล้ว แล็บรับสิ่งส่งตรวจ แพทย์ตรวจบ่าย → รอหน้าห้องตรวจ ไม่นั่งในห้องตรวจ 4 ชม.
[$s] = flow_segments(row(['reg' => 505.0, 'lo' => 506.0, 's4' => 536.0, 'lr' => 551.0, 's5' => 844.0, 'd5' => 'R03',
    's6' => 870.0, 'md' => 'OPD']), $deps, $idx, null);
check('lab then wait outside room', $s, [[505.0, 'REG'], [506.0, 'LAB'], [508.0, 'SCRW'], [541.0, 'DRWW'], [844.0, 'R3'],
    [854.0, 'RX']]);

// กายภาพ: บันทึกแค่เวลาจบบริการ (service20) → อยู่คลินิกกายภาพตั้งแต่หลังลงทะเบียนจนจบบริการ
$deps['PTX'] = ['name' => '068 กายภาพบำบัด', 'group' => flow_classify('068 กายภาพบำบัด')];
[$s, $e] = flow_segments(row(['reg' => 480.0, 's20' => 560.0, 'md' => 'PTX']), $deps, $idx, null);
check('pt shown in clinic', $s, [[480.0, 'REG'], [483.0, 'PT']]);
check('pt leaves after service end', $e, 562.0);

// ARI: ซักประวัติ ตรวจ รับยา อยู่ในคลินิก ARI ทั้งหมด
[$s] = flow_segments(row(['reg' => 480.0, 's4' => 490.0, 'd4' => 'ARI', 's5' => 520.0, 'd5' => 'ARI', 's6' => 540.0,
    's16' => 550.0, 'md' => 'ARI']), $deps, $idx, null);
check('ari stays in clinic', $s, [[480.0, 'REG'], [483.0, 'ARI']]);

// ผู้ป่วยนัด (oapp.visit_vn) ไม่รู้ห้อง → ซักประวัติ/หน้าห้องตรวจฝั่งนัด ห้อง 7–10 ตามรหัสแพทย์ (คงที่)
[$s] = flow_segments(row(['reg' => 480.0, 's4' => 490.0, 's5' => 520.0, 'md' => 'OPD', 'ap' => true, 'dr' => 'D01']), $deps, $idx, null);
check('appointment side', [$s[1][1], $s[2][1], in_array($s[3][1], ['R7', 'R8', 'R9', 'R10'], true)], ['SCRA', 'DRWA', true]);
check('same doctor same room', flow_room_for('D01', true), flow_room_for('D01', true));

// คลินิกเด็ก: ซักประวัติและรอในคลินิกเด็ก แล้วตรวจห้อง 6
[$s] = flow_segments(row(['reg' => 480.0, 's4' => 490.0, 'd4' => 'PED', 's5' => 520.0, 'md' => 'OPD']), $deps, $idx, null);
check('pediatric', $s, [[480.0, 'REG'], [483.0, 'PED'], [520.0, 'R6']]);

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
check('dent segments', $s, [[500.0, 'REG'], [503.0, 'DENT']]);   // ไม่มีเวลาคัดกรอง → เดินไปรอในคลินิกเลย
check('dent linger', $e, 572.0);

// เวลาก่อนลงทะเบียน (กดผิด) ไม่นับ
[$s] = flow_segments(row(['reg' => 500.0, 's4' => 400.0, 'md' => 'OPD']), $deps, $idx, null);
check('ignore time before reg', $s, [[500.0, 'REG']]);

check('urgency er level', flow_urgency('2', '0'), 2);
check('urgency opd emergency', flow_urgency(null, '4'), 2);
check('urgency elderly', flow_urgency(null, '3'), 6);
check('urgency normal', flow_urgency(null, '0'), 5);
echo "all passed\n";
