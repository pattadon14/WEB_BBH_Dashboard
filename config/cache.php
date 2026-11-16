<?php

// cache คำตอบ API ที่หน้าเว็บรีเฟรชอัตโนมัติ ไว้ใช้ร่วมกันทุกจอ
// เปิดกี่จอพร้อมกันก็ query ฐานข้อมูล HOSxP แค่ครั้งเดียวต่อรอบ TTL
// เรียกก่อน require database.php — ถ้าได้จาก cache จะจบเลยโดยไม่ต่อฐานข้อมูล
// เก็บเฉพาะคำตอบที่สำเร็จ (HTTP 200, เป็น JSON และไม่มี key "error")
function api_cache(string $key, int $ttl = 60): void
{
    $dir  = __DIR__ . '/../storage/cache/';
    $file = $dir . 'api_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $key) . '.json';

    if (is_file($file) && time() - filemtime($file) < $ttl) {
        header('Content-Type: application/json; charset=utf-8');
        readfile($file);
        exit;
    }

    ob_start();
    register_shutdown_function(function () use ($dir, $file) {
        $out = ob_get_contents();
        if ($out === false || $out === '' || http_response_code() !== 200) {
            return;
        }
        $json = json_decode($out, true);
        if (!is_array($json) || isset($json['error'])) {
            return;
        }
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        // เขียนไฟล์ชั่วคราวแล้ว rename เพื่อไม่ให้อีกจออ่านเจอไฟล์ที่เขียนไม่เสร็จ
        $tmp = $file . '.' . getmypid() . '.tmp';
        if (@file_put_contents($tmp, $out) !== false) {
            @rename($tmp, $file);
        }
    });
}

// cache แยกตามวันที่ ?date=YYYY-MM-DD — รูปแบบอื่นไม่ cache (กันไฟล์ขยะจาก query string แปลก ๆ)
function api_cache_by_date(string $key, int $ttl = 60): void
{
    $date = $_GET['date'] ?? date('Y-m-d');
    if (is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        api_cache($key . '_' . $date, $ttl);
    }
}
