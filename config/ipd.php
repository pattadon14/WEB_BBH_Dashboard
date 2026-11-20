<?php

/*
 * ค่าร่วมของหน้า IPD (api/ipd_*.php, api/bed_occupancy_trend.php) — แก้ที่นี่ที่เดียว
 */

require_once __DIR__ . '/period.php';

// ward ที่ไม่นับเตียง/อัตราครองเตียง/ผู้ป่วยปัจจุบัน: 10 ส่องกล้อง, 11 ผ่าตัด, 05 Observe ER, 04 LR, 23 Home Ward
// (รับใหม่/จำหน่าย/LOS/Readmission/10 อันดับโรค ยังนับทุก ward ตามนิยามเดิม)
const IPD_EXCLUDED_WARDS = "'10', '11', '05', '04', '23'";
// Home Ward: ผู้ป่วยนอนที่บ้าน ไม่นับเตียงรวม แต่หน้าแรกแสดงเป็นการ์ดท้ายสุด (api/index_ward_bed.php) — หน้า IPD แสดงเหมือนกัน
const IPD_HOME_WARD = '23';

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

/*
 * Readmission (ทีมคุณภาพยืนยัน 20 พ.ย. 2569): กลับมารักษาด้วยโรคเดิมภายใน 1 เดือนหลังจำหน่าย โดยไม่นับ Refer Back และไม่ได้วางแผน
 * - ครั้งนี้ ($alias) admit ภายใน 1 เดือนปฏิทินหลังวันจำหน่ายครั้งก่อนของ HN เดียวกัน (จำหน่าย 15 ม.ค. → นับถึง 15 ก.พ.)
 * - โรคเดิม = การวินิจฉัยหลัก (an_stat.pdx) กลุ่ม ICD-10 3 หลักเดียวกัน → ครั้งนี้ต้องจำหน่ายแล้ว (มี pdx) จึงตัดสินได้
 * - ไม่นับ: ครั้งก่อนหรือครั้งนี้เป็น Observe ER (สังเกตอาการที่ ER ไม่ใช่การ admit),
 *   Refer Back (ทีมคุณภาพ: เคย admit ที่ รพ.บ้านบึง ถูก Refer ไปที่อื่นชั่วคราวแล้วกลับมา admit ต่อ) =
 *     · ครั้งก่อนเป็นการ admit ที่มี Refer ออก (จำหน่ายแบบส่งต่อ dchtype 04 หรือมีใบ referout ของ visit นั้น — ผู้ป่วยที่ส่งไปชั่วคราวแล้วกลับมามักไม่ได้ลงจำหน่ายแบบส่งต่อ)
 *     · มี admit ที่ Refer ออกจำหน่ายภายใน 1 เดือนก่อนครั้งนี้ (ครั้งนี้คือการกลับมาจากการส่งต่อ แม้จะมี admit ก่อนหน้านั้นที่โรคเดียวกัน)
 *     · ครั้งนี้มีใบรับส่งต่อเข้า (referin)
 *   ครั้งนี้ลงว่านัด admit (ipt.plan_admit = Y — ปัจจุบันยังไม่มีการบันทึก)
 * ใช้ร่วมกันใน api/ipd_kpi_summary.php และ api/ipd_visit_list.php — $alias เป็นค่าคงที่ในโค้ด
 */
const IPD_OBSERVE_WARD = '05';
const IPD_READMIT_WINDOW = "INTERVAL '1 month'";

// เงื่อนไขของ AN ครั้งนี้ (ตัวหาร = admit ที่ผ่านเงื่อนไขนี้)
function ipd_readmit_base(string $alias): string
{
    return "$alias.ward <> '" . IPD_OBSERVE_WARD . "' AND COALESCE($alias.plan_admit, '') <> 'Y'";
}

// admit ($a = ชื่อย่อตาราง ipt) ที่มีการ Refer ออก: จำหน่ายแบบส่งต่อ หรือมีใบ referout ของ visit นั้น
function ipd_referred_out_sql(string $a): string
{
    return "($a.dchtype = '" . IPD_REFER_TYPE . "' OR EXISTS (SELECT 1 FROM referout ro WHERE ro.vn = $a.vn))";
}

// FROM … WHERE ของ AN ครั้งก่อนที่ทำให้ครั้งนี้เป็น readmission (ใช้กับ EXISTS (SELECT 1 …) หรือ SELECT MAX(prev.dchdate) …)
function ipd_readmit_prev_from(string $alias): string
{
    $win = IPD_READMIT_WINDOW;
    return "FROM ipt prev
            JOIN an_stat pa ON pa.an = prev.an
            WHERE prev.hn = $alias.hn AND prev.an <> $alias.an AND prev.dchdate IS NOT NULL
              AND $alias.regdate >= prev.dchdate AND $alias.regdate <= prev.dchdate + $win
              AND prev.ward <> '" . IPD_OBSERVE_WARD . "'
              AND NOT " . ipd_referred_out_sql('prev') . "
              AND LEFT(pa.pdx, 3) = (SELECT LEFT(ca.pdx, 3) FROM an_stat ca WHERE ca.an = $alias.an)
              AND NOT EXISTS (SELECT 1 FROM referin rb WHERE rb.vn = $alias.vn)
              AND NOT EXISTS (SELECT 1 FROM ipt rbk
                               WHERE rbk.hn = $alias.hn AND rbk.an <> $alias.an AND rbk.dchdate IS NOT NULL
                                 AND $alias.regdate >= rbk.dchdate AND $alias.regdate <= rbk.dchdate + $win
                                 AND " . ipd_referred_out_sql('rbk') . ")";
}

// ส่ง JSON จาก cache ไฟล์ หรือสร้างใหม่ — ช่วงที่รวมวันนี้เก็บ 60 วินาที (เท่าหน้าแรก ผู้ป่วยตอนนี้จึงตรงกัน) ช่วงที่จบแล้ว 30 นาที
function ipd_cached(string $name, array $p, callable $build, int $liveTtl = 60): void   // liveTtl: ช่วงที่รวมวันนี้ (ANC ใช้ 300)
{
    $dir  = __DIR__ . '/../storage/cache/';
    $file = $dir . $name . '_' . $p['key'] . '.json';
    $ttl  = $p['end'] >= date('Y-m-d') ? $liveTtl : 30 * 60;

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
