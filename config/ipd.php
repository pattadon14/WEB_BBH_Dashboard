<?php

/*
 * ค่าร่วมของหน้า IPD (api/ipd_*.php, api/bed_occupancy_trend.php) — แก้ที่นี่ที่เดียว
 */

require_once __DIR__ . '/period.php';

// ward ที่ไม่นับเตียง/อัตราครองเตียง/ผู้ป่วยปัจจุบัน: 10 ส่องกล้อง, 11 ผ่าตัด, 05 Observe ER, 04 LR, 23 Home Ward
// (รับใหม่/จำหน่าย/LOS/Readmission/10 อันดับโรค ยังนับทุก ward ตามนิยามเดิม)
const IPD_EXCLUDED_WARDS = "'10', '11', '05', '04', '23'";

// ประเภทการจำหน่าย (ipt.dchtype) — ชื่อไทยแทนชื่ออังกฤษในตาราง dchtype
const IPD_DCH_NAMES = [
    '01' => 'ทุเลา / แพทย์อนุญาต',
    '02' => 'ไม่สมัครใจอยู่',
    '03' => 'หนีกลับ',
    '04' => 'ส่งต่อ (Refer)',
    '05' => 'อื่น ๆ',
    '08' => 'เสียชีวิต (ชันสูตร)',
    '09' => 'เสียชีวิต',
];
const IPD_DEATH_TYPES = "'08', '09'";
const IPD_REFER_TYPE = '04';

// ส่ง JSON จาก cache ไฟล์ หรือสร้างใหม่ — ช่วงที่รวมวันนี้เก็บ 5 นาที ช่วงที่จบแล้ว 30 นาที
function ipd_cached(string $name, array $p, callable $build): void
{
    $dir  = __DIR__ . '/../storage/cache/';
    $file = $dir . $name . '_' . $p['key'] . '.json';
    $ttl  = $p['end'] >= date('Y-m-d') ? 5 * 60 : 30 * 60;

    if (is_file($file) && time() - filemtime($file) < $ttl) {
        echo file_get_contents($file);
        return;
    }
    $json = json_encode($build(), JSON_UNESCAPED_UNICODE);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    file_put_contents($file, $json);
    echo $json;
}

// ช่วงวันที่ผิด = 400 พร้อมข้อความของเราเอง · อย่างอื่น = 500 ข้อความกลาง (รายละเอียดลง error_log เท่านั้น)
function ipd_fail(Throwable $e, string $who): void
{
    if ($e instanceof InvalidArgumentException) {
        http_response_code(400);
        echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        return;
    }
    error_log($who . ': ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'โหลดข้อมูลไม่สำเร็จ'], JSON_UNESCAPED_UNICODE);
}
