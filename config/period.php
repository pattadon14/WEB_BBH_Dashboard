<?php

/*
 * ช่วงเวลาของรายงาน (หน้า ER / IPD) — ใช้ร่วมกันทุก API
 *   ?d1=YYYY-MM-DD&d2=YYYY-MM-DD  ช่วงวันที่ที่เลือก (วันนี้ / เดือนนี้ / กำหนดเอง)
 *   ?year=2570                     ปีงบประมาณ (ต.ค.–ก.ย.) — ค่าเริ่มต้นเมื่อไม่ส่ง d1/d2
 *
 * คืน start/end (รวมวันสุดท้าย), key สำหรับชื่อไฟล์ cache และ unit สำหรับกราฟแนวโน้ม
 * (ช่วงไม่เกิน 62 วันแสดงรายวัน นอกนั้นรายเดือน)
 */

const BBH_PERIOD_MAX_DAYS = 400;
const BBH_THAI_MONTHS = ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];

function bbh_period(): array
{
    $d1 = $_GET['d1'] ?? '';
    $d2 = $_GET['d2'] ?? '';

    if ($d1 !== '' || $d2 !== '') {
        $a = DateTime::createFromFormat('!Y-m-d', $d1);
        $b = DateTime::createFromFormat('!Y-m-d', $d2);
        if (!$a || !$b || $a->format('Y-m-d') !== $d1 || $b->format('Y-m-d') !== $d2) {
            throw new InvalidArgumentException('วันที่ไม่ถูกต้อง');
        }
        if ($a > $b) {
            throw new InvalidArgumentException('วันที่เริ่มต้องไม่เกินวันที่สิ้นสุด');
        }
        $days = (int)$a->diff($b)->days + 1;
        if ($days > BBH_PERIOD_MAX_DAYS) {
            throw new InvalidArgumentException('ช่วงวันที่ยาวเกิน ' . BBH_PERIOD_MAX_DAYS . ' วัน');
        }
        return [
            'start' => $d1,
            'end'   => $d2,
            'key'   => $d1 . '_' . $d2,
            'unit'  => $days <= 62 ? 'day' : 'month',
        ];
    }

    $year = isset($_GET['year'])
        ? (int)$_GET['year']
        : date('Y') + 543 + (date('n') >= 10 ? 1 : 0);

    if ($year < 2565 || $year > 2600) {
        throw new InvalidArgumentException('ปีงบประมาณไม่ถูกต้อง');
    }

    return [
        'start' => ($year - 544) . '-10-01',
        'end'   => ($year - 543) . '-09-30',
        'key'   => (string)$year,
        'unit'  => 'month',
    ];
}

/*
 * ช่องของกราฟแนวโน้มตั้งแต่ start ถึง min(end, วันนี้) — key = วันแรกของช่อง (YYYY-MM-DD)
 * ให้ SQL จัดกลุ่มด้วย to_char(date_trunc(unit, วันที่), 'YYYY-MM-DD') แล้วเติม 0 ช่องที่ไม่มีข้อมูล
 * ช่องแรกของ unit=month อาจไม่ใช่วันที่ 1 (ช่วงกำหนดเอง) จึงใช้ date_trunc เป็น key เสมอ
 */
function bbh_period_buckets(array $p): array
{
    $unit = $p['unit'];
    $cur = new DateTime($p['start']);
    if ($unit === 'month') {
        $cur->modify('first day of this month');
    }
    $last = min($p['end'], date('Y-m-d'));
    $out = [];
    while ($cur->format('Y-m-d') <= $last) {
        $m = (int)$cur->format('n');
        $yy = substr((string)((int)$cur->format('Y') + 543), 2);
        $out[$cur->format('Y-m-d')] = $unit === 'day'
            ? (int)$cur->format('j') . ' ' . BBH_THAI_MONTHS[$m - 1]
            : BBH_THAI_MONTHS[$m - 1] . ' ' . $yy;
        $cur->modify($unit === 'day' ? '+1 day' : '+1 month');
    }
    return $out;
}
