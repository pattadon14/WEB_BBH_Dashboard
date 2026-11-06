<?php
/*
 * ทดสอบตรรกะ คิวส่งแผนก → จุดบริการของ 3D Monitor (ไม่ต่อฐานข้อมูล) · รัน: php tests/opd_flow_test.php
 * กรณีทดสอบจำลองจาก visit จริงของบ้านบึง 4 พ.ย. 2569 (เวลาเป็นนาทีนับจากเที่ยงคืน)
 */
if (PHP_SAPI !== 'cli') {
    exit;
}
require __DIR__ . '/../api/opd_flow_lib.php';

$names = ['193 ห้องบัตร OPD', '207 จุดซักประวัติ GP', '115 จุดซักประวัติ OPD เฉพาะทาง', '109 ห้องตรวจโรค 7',
    '129 หน้าห้องตรวจ3(นัด)', '131 Exit นัด', '014 ห้องเก็บเงิน', '203 ห้องจ่ายยาผู้ป่วยนอก', '999 กลับบ้าน',
    '011 คัดกรองผู้ป่วย ER', '065 ห้องอุบัติเหตุ ER', "078 คัดกรองห้องท\u{0E4D}\u{0E32}แผล", '047 ห้องทำแผลตึกใหม่',
    '023 OPDนอกเวลา', '035 ห้องตรวจ พิเศษ 1', '134 จุดซักประวัติคลินิกเด็ก', '108 ห้องตรวจโรค 6',
    '143 ส่งเวชภัณฑ์ทางไปรษณีย์', '068 กายภาพบำบัด'];
$deps = [];
foreach ($names as $n) {
    $deps[substr($n, 0, 3)] = ['name' => $n, 'group' => flow_classify($n)];
}
function visit(array $v): array
{
    return array_merge(['reg' => 480.0, 'md' => '207', 'ap' => false, 'dr' => null, 'lo' => null, 'lr' => null,
        'xr' => null, 'us' => null], $v);
}
function check(string $name, $got, $want): void
{
    if ($got !== $want) {
        fwrite(STDERR, "FAIL $name\n  got:  " . json_encode($got, JSON_UNESCAPED_UNICODE) . "\n  want: "
            . json_encode($want, JSON_UNESCAPED_UNICODE) . "\n");
        exit(1);
    }
    echo "ok   $name\n";
}

// ---- จัดกลุ่มแผนกจากชื่อจริงของบ้านบึง
foreach ([
    ["078 คัดกรองห้องท\u{0E4D}\u{0E32}แผล", 'WOUND'], ['047 ห้องทำแผลตึกใหม่', 'WOUND'], ['131 Exit นัด', 'EXIT'],
    ['076 Exit ทั่วไป', 'EXIT'], ['218 หลังพบแพทย์ NCD', 'EXIT'], ['199 Exit ARI', 'ARI'], ['200 การเงิน ARI', 'ARI'],
    ['195 ห้องยา ARI', 'ARI'], ['162 ห้องตรวจแพทย์ ARI', 'ARI'], ['053 การเงินห้องทันตกรรม', 'DENT'],
    ['189 ห้องบัตรทันตกรรม', 'DENT'], ['083 การเงินห้องกายภาพ', 'PT'], ['129 หน้าห้องตรวจ3(นัด)', 'DRW'],
    ['002 หน้าห้องตรวจ 1(ทั่วไป)', 'DRW'], ['109 ห้องตรวจโรค 7', 'R7'], ['177 ห้องตรวจโรค14', 'R10'],
    ['035 ห้องตรวจ พิเศษ 1', 'ROOMX'], ['023 OPDนอกเวลา', 'SCRW'], ['040 ER(นอกเวลาราชการ)', 'ER'],
    ['193 ห้องบัตร OPD', 'ADMIN'], ['203 ห้องจ่ายยาผู้ป่วยนอก', 'RX'], ['014 ห้องเก็บเงิน', 'CASH'],
    ['134 จุดซักประวัติคลินิกเด็ก', 'PED'], ['143 ส่งเวชภัณฑ์ทางไปรษณีย์', 'REMOTE'], ['234 PCU การเงิน', 'REMOTE'],
    ['178 Telemedicine', 'TELE'], ['039 คลินิคเบาหวาน GP', null],
] as [$n, $g]) {
    check("classify $n", flow_classify($n), $g);
}

// ---- ผู้ป่วยนัด: ซักประวัติ → หน้าห้องตรวจ → ห้อง 7 → Exit นัด → การเงิน (ส่งกลับบ้านช้า 4 ชม. = การเงินไม่กดรับคิว)
[$s, $e] = flow_segments(visit(['reg' => 446.0, 'md' => '115', 'ap' => true]), [
    [446.0, null, null, '115'], [595.0, 598.0, 599.0, '129'], [599.0, 619.0, 623.0, '109'],
    [623.0, 626.0, 628.0, '131'], [628.0, null, null, '014'], [903.0, null, null, '999']], $deps, null);
check('appointment path', $s, [[446.0, 'REG'], [449.0, 'SCRA'], [595.0, 'DRWA'], [619.0, 'R7'], [623.0, 'DRWA'], [628.0, 'CASH']]);
check('cashier capped at 30 min', $e, 658.0);

// ---- ทำแผล: ส่งการเงินล่วงหน้าระหว่างทำแผล → ไปการเงินเมื่อทำแผลเสร็จ
[$s, $e] = flow_segments(visit(['reg' => 521.0, 'md' => '078']), [
    [521.0, 531.0, 577.0, '078'], [532.0, null, null, '014'], [577.0, null, null, '999']], $deps, null);
check('wound then cashier', $s, [[521.0, 'REG'], [524.0, 'WOUND'], [577.0, 'CASH']]);
check('wound exit', $e, 579.0);

// ---- ER: ลงทะเบียนที่ ER · ส่งห้องยาและการเงินพร้อมกัน → ห้องยารับคิวทีหลัง
[$s, $e] = flow_segments(visit(['reg' => 488.0, 'md' => '011']), [
    [488.0, 489.0, 491.0, '011'], [521.0, 512.0, 524.0, '065'], [524.0, 528.0, 537.0, '203'],
    [524.0, null, null, '014'], [537.0, null, null, '999']], $deps, null);
check('er path', $s, [[488.0, 'ER'], [524.0, 'CASH'], [528.0, 'RX']]);
check('er exit', $e, 539.0);

// ---- เจาะเลือดก่อนพบแพทย์ → กลับไปรอที่ซักประวัติ
[$s, $e] = flow_segments(visit(['reg' => 400.0, 'md' => '115', 'ap' => true, 'lo' => 405.0, 'lr' => 415.0]), [
    [400.0, null, null, '115'], [470.0, 480.0, 485.0, '109'], [485.0, null, null, '999']], $deps, null);
check('lab and back', $s, [[400.0, 'REG'], [403.0, 'SCRA'], [405.0, 'LAB'], [415.0, 'SCRA'], [470.0, 'DRWA'], [480.0, 'R7'], [485.0, 'DRWA']]);
check('lab visit exit', $e, 487.0);

// ---- X-Ray มีแค่เวลาส่ง → อยู่ 10 นาทีแล้วกลับ · ไม่มีส่งกลับบ้าน (วันก่อน) → ค้างได้ 120 นาที
[$s, $e] = flow_segments(visit(['reg' => 500.0, 'xr' => 520.0]), [[500.0, null, null, '207']], $deps, null);
check('xray and back', $s, [[500.0, 'REG'], [503.0, 'SCRW'], [520.0, 'XRAY'], [530.0, 'SCRW']]);
check('no go-home linger', $e, 650.0);

// ---- ห้องตรวจไม่มีเลข → ห้องเดิมตามรหัสแพทย์ (walk-in 1–5)
[$s] = flow_segments(visit(['dr' => 'D9']), [[480.0, null, null, '207'], [500.0, 510.0, 515.0, '035']], $deps, null);
check('unnumbered room by doctor', [$s[2][1], $s[3][1]], ['DRWW', flow_room_for('D9', false)]);
check('same doctor same room', flow_room_for('D9', true), flow_room_for('D9', true));

// ---- คลินิกเด็ก: รอในคลินิกเด็ก ตรวจห้อง 6
[$s] = flow_segments(visit(['md' => '134']), [[480.0, null, null, '134'], [520.0, 525.0, 530.0, '108']], $deps, null);
check('pediatric', $s, [[480.0, 'REG'], [483.0, 'PED'], [525.0, 'R6'], [530.0, 'PED']]);

// ---- แผนกนอก รพ. กลางทาง (ส่งยาไปรษณีย์) ไม่ย้ายผู้ป่วย
[$s] = flow_segments(visit([]), [[480.0, null, null, '207'], [490.0, null, null, '143']], $deps, null);
check('remote dep ignored', $s, [[480.0, 'REG'], [483.0, 'SCRW']]);

// ---- ไม่มีคิวเลย → เดินไปแผนกหลัก (กายภาพ)
[$s, $e] = flow_segments(visit(['md' => '068']), [], $deps, null);
check('no queue goes to main dep', $s, [[480.0, 'REG'], [483.0, 'PT']]);

// ---- วันนี้ ยังไม่ส่งกลับบ้าน → อยู่ถึงตอนนี้
[$s, $e] = flow_segments(visit(['reg' => 580.0]), [[580.0, null, null, '207']], $deps, 600.0);
check('today still here', $e, 605.0);

// ---- คิวหลังส่งกลับบ้าน (กดย้อนหลัง) และคิวก่อนลงทะเบียน ไม่นับ
[$s, $e] = flow_segments(visit([]), [[300.0, null, null, '014'], [480.0, null, null, '207'], [500.0, null, null, '999'],
    [520.0, null, null, '014']], $deps, null);
check('ignore before reg and after home', $s, [[480.0, 'REG'], [483.0, 'SCRW']]);
check('exit at go-home', $e, 502.0);

// ---- OPD นอกเวลา อยู่ฝั่ง walk-in แม้เป็นผู้ป่วยนัด
[$s] = flow_segments(visit(['md' => '023', 'ap' => true]), [[480.0, null, null, '023']], $deps, null);
check('after-hours opd walk-in side', $s[1][1], 'SCRW');

check('urgency er level', flow_urgency('2', '0'), 2);
check('urgency opd emergency', flow_urgency(null, '4'), 2);
check('urgency elderly', flow_urgency(null, '3'), 6);
check('urgency normal', flow_urgency(null, '0'), 5);
echo "all passed\n";
