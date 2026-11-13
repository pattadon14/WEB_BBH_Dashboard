<?php

// จำนวนเตียงที่ใช้จริง ทับค่า ward.bedcount ใน HOSxP (ฐานข้อมูลอ่านอย่างเดียว แก้จากเว็บไม่ได้)
// ใช้ทุก API ที่คิดเตียง/อัตราครองเตียง ให้ตัวเลขตรงกันทุกหน้า — แก้รายการที่นี่ที่เดียว
// ถ้าแก้ bedcount ใน HOSxP ให้ตรงแล้ว ลบรายการออกจากที่นี่ได้
const WARD_BED_OVERRIDE = [
    '02' => 22, // ผู้ป่วยเด็ก (HOSxP ตั้งไว้ 27)
];

// นิพจน์ SQL จำนวนเตียงของ ward — $alias = ชื่อย่อตาราง ward ใน query ('' ถ้าไม่มี)
// ค่าคงที่ในโค้ด ไม่ได้มาจากผู้ใช้ จึงต่อสตริงได้
function ward_bedcount_sql(string $alias = ''): string
{
    $p = $alias === '' ? '' : $alias . '.';
    $sql = "CASE {$p}ward::text";
    foreach (WARD_BED_OVERRIDE as $ward => $beds) {
        $sql .= " WHEN '{$ward}' THEN " . (int)$beds;
    }
    return $sql . " ELSE COALESCE({$p}bedcount, 0) END";
}
