/* 3D Monitor ผู้ป่วยนอก (OPD) แบบ 2.5D (isometric) — วาดด้วย canvas ล้วน ไม่ใช้ไลบรารีภายนอก
 *
 * เอนจินวาดผัง/หุ่น/ไทม์ไลน์พอร์ตจาก static/flow.js ของ opd-dashboard-wnk
 * Copyright (c) 2569 นายเผด็จชัย สิงหล โรงพยาบาลวังน้ำเขียว — MIT License
 * (Permission is hereby granted, free of charge, ... ดูข้อความเต็มใน opd-dashboard/opd-dashboard-wnk-main/LICENSE)
 *
 * ข้อมูล: api/opd_flow.php?d=YYYY-MM-DD → visits[{i, s:[[นาทีที่เข้า, ลำดับจุด],...], e:นาทีที่ออก, u, g, d, dr, q}]
 * ทุกเฟรมคำนวณว่าเวลาจำลอง T แต่ละ visit อยู่จุดไหน แล้วให้หุ่นเดินไปเก้าอี้ว่างของจุดนั้น
 * รายงานในเมนูขวาคำนวณจากข้อมูลชุดเดียวกันในเบราว์เซอร์ (ไม่มีข้อมูลระบุตัวตน)
 */
(function () {
  'use strict';

  var CFG = window.FLOW;
  var $ = function (id) { return document.getElementById(id); };

  // ------------------------------------------------------------------ ผังโรงพยาบาล (หน่วย ≈ เมตร)
  // ตำแหน่ง/สัดส่วนอาคารและถนนตามแผนผังอาคาร รพ.บ้านบึง (ได้รับ 17 พ.ย. 2569) · ห้องภายในอาคารยังเป็นผังย่อ
  // x = ไปทางด้านล่างของแผนผัง (ขวาล่างของจอ), y = ไปทางด้านซ้ายของแผนผัง (ซ้ายล่างของจอ) — มองจากทางเข้า รพ.
  // b = อาคาร, kind = สีพื้น, counter = เคาน์เตอร์ชิดผนังด้านหลัง, room = ห้องตรวจ (โต๊ะแพทย์ + เตียง),
  // wait = จุดรอหน้าห้องของห้องตรวจนี้, door = ทางเข้าเฉพาะ (ไม่ใช้ประตูอาคาร), node = จุดบนทางเดิน (NODES) หน้าประตู
  // cap = ที่นั่งรอจริงของบ้านบึง (แจ้งโดยหน่วยงาน 4 พ.ย. 2569) · queue = ยืนต่อแถว ไม่มีเก้าอี้ ไม่คิดความหนาแน่น
  var BLD = {
    OPD: { name: 'อาคารผู้ป่วยนอก', r: [57, 128, 55, 45], door: [84, 173], node: 'OPD', sign: [70, 175.5] },
    ERB: { name: 'อาคารอุบัติเหตุฉุกเฉิน', r: [122, 131, 36, 42], door: [139, 173], node: 'ERB', sign: [130, 175.5] },
    ARI: { name: 'อาคาร ARI', r: [86, 218, 22, 8], door: [89, 218], node: 'ARI', sign: [97, 227.5] },
    // แพทย์แผนไทย/กายภาพ = อาคาร 2 หลังฝั่งตรงข้ามถนนลาดยาง ตรงข้ามอาคารอุบัติเหตุ (ตำแหน่งตามที่หน่วยงานชี้ 17 พ.ย. 2569)
    TTM: { name: 'อาคารแพทย์แผนไทย', r: [199, 165, 7, 7], door: [199, 170], node: 'TTM', sign: [207.5, 173] },
    PT:  { name: 'อาคารกายภาพบำบัด', r: [199, 138, 7.5, 7], door: [199, 142], node: 'PT', sign: [208, 146] },
    // คลินิกพิเศษ = ด้านหลังอาคารผู้ป่วยนอก ไปทางเดินเชื่อม (ออกประตูหลังฝั่งนัด เดินตรง แล้วเลี้ยวซ้าย) — inner = เข้าออกผ่านอาคารนี้เท่านั้น
    // shortcut: ขนาด/ระยะอาคารคลินิกพิเศษกะเอง — แก้เมื่อหน่วยงานแจ้งขนาดจริง
    SPC: { name: 'อาคารคลินิกพิเศษ', r: [58, 103, 22, 16], door: [80, 111], node: 'OPD', sign: [69, 120.5], inner: 'OPD' }
  };
  var LAYOUT = {
    // อาคารผู้ป่วยนอก (แปลน + ภาพที่หน่วยงานวงไว้ 24 พ.ย. 2569) ย่อให้กระชับ: ทางเดินตรงกลาง x 81–87 จากประตูหน้าถึงผนังหลัง
    // ซ้ายทางเดิน (x 48–81) = ฝั่งผู้ป่วยนัด ตามแปลน · ขวาทางเดิน (x 87–120) = ฝั่งผู้ป่วยทั่วไป · ทางเดินย่อยดู AISLES
    // gate = ประตูห้อง [x, y] (ช่องในผนัง) · walls4 = ผนังครบ 4 ด้าน · face 'x-' = เก้าอี้หันไปทาง −x
    // เวชระเบียน = ห้องบัตรหน้าฝั่งนัด (ประตูออกทางเดินตรงกลาง + ทางเชื่อมคลินิกเด็ก)
    REG:  { b: 'OPD', r: [73, 164, 8, 9], kind: 'hall', counter: true, queue: true, walls4: true, gate: [[81, 168.5], [73, 168.5]] },
    // ฝั่งทั่วไป (หลัง → หน้า): ห้องยา (ซ้าย) + การเงิน (ขวา) · หน้าห้องตรวจ · ซักประวัติ (เก้าอี้หันไปทางทางเข้า)
    // · โต๊ะซักประวัติ 3 คู่ชิดผนังหน้า หันหน้าเข้าหาที่นั่ง · ห้องตรวจ 1–5 แถวเดียวชิดผนังขวา (ซ้ายมือของคนนั่ง) ห้อง 1 ชิดผนังหน้า
    // shortcut: ไม่มีแปลนภายในฝั่งทั่วไป — วางตามที่หน่วยงานบอกด้วยข้อความ/ภาพ แก้เมื่อได้แปลน
    RX:   { b: 'OPD', r: [87, 128, 12.5, 11], kind: 'rx', counter: true, cap: 21 },
    CASH: { b: 'OPD', r: [99.5, 128, 12.5, 11], kind: 'rx', counter: true, cap: 21 },    // ห้องยา + การเงิน OPD รวม 42
    DRWW: { b: 'OPD', r: [87, 141, 16.4, 8], kind: 'walk', cap: 15, open: true },       // ห้อง 1–5 ห้องละ 3
    SCRW: { b: 'OPD', r: [87, 149, 16.4, 19], kind: 'walk', cap: 64, open: true, rev: true },  // rev = เติมเก้าอี้จากแถวหน้า (ใกล้โต๊ะ)
    R5:   { b: 'OPD', r: [106.2, 144, 5.8, 5.8],   kind: 'room', room: true, wait: 'DRWW', short: 'ห้อง 5', gate: [[106.2, 146.9]] },
    R4:   { b: 'OPD', r: [106.2, 149.8, 5.8, 5.8], kind: 'room', room: true, wait: 'DRWW', short: 'ห้อง 4', gate: [[106.2, 152.7]] },
    R3:   { b: 'OPD', r: [106.2, 155.6, 5.8, 5.8], kind: 'room', room: true, wait: 'DRWW', short: 'ห้อง 3', gate: [[106.2, 158.5]] },
    R2:   { b: 'OPD', r: [106.2, 161.4, 5.8, 5.8], kind: 'room', room: true, wait: 'DRWW', short: 'ห้อง 2', gate: [[106.2, 164.3]] },
    R1:   { b: 'OPD', r: [106.2, 167.2, 5.8, 5.8], kind: 'room', room: true, wait: 'DRWW', short: 'ห้อง 1', gate: [[106.2, 170.1]] },
    // ฝั่งผู้ป่วยนัด (แปลน: ซ้าย = ถนน, ล่าง = ทางเดินตรงกลาง) — แปลน x → ผัง y ลดลง, แปลน y → ผัง x เพิ่มขึ้น
    // ห้อง 10 ชิดผนังหลังอาคาร · โต๊ะ 1–3 ชิดผนังซ้าย · ที่นั่งรอกลาง · ห้อง 11 ติดทางเดินหน้าห้อง 6–9 · คลินิกเด็ก + ห้องบัตร ติดถนน
    // shortcut: แปลนไม่มีจุดคลินิกเด็ก — วางหน้าห้อง 6 ฝั่งถนน แก้เมื่อหน่วยงานชี้ตำแหน่ง
    PED:  { b: 'OPD', r: [57, 164, 16, 9], kind: 'ped', counter: true, cap: 20, gate: [[71.3, 164, 0.8]] },  // ซักประวัติ + รอพบแพทย์เด็ก (ตรวจห้อง 6)
    // ประตูห้องตรวจหันเข้าจุดนั่งรอ ยกเว้นห้อง 6 (คลินิกเด็ก) และห้อง 10 (เปิดทางซ้าย)
    R9:   { b: 'OPD', r: [57, 158.2, 4.5, 5.8], kind: 'roomA', room: true, wait: 'DRWA', short: 'ห้อง 9', gate: [[59.25, 158.2]] },
    R8:   { b: 'OPD', r: [61.5, 158.2, 4.5, 5.8], kind: 'roomA', room: true, wait: 'DRWA', short: 'ห้อง 8', gate: [[63.75, 158.2]] },
    R7:   { b: 'OPD', r: [66, 158.2, 4.5, 5.8], kind: 'roomA', room: true, wait: 'DRWA', short: 'ห้อง 7', gate: [[68.25, 158.2]] },
    R6:   { b: 'OPD', r: [70.5, 158.2, 4.5, 5.8], kind: 'room', room: true, wait: 'PED', short: 'ห้อง 6 เด็ก', gate: [[71.3, 164, 0.8]] },
    SCRA: { b: 'OPD', r: [60.5, 137, 8, 19], kind: 'appt', cap: 36, open: true, face: 'x-' },   // นั่งหันหน้าเข้าโต๊ะซักประวัติ 1–3
    DRWA: { b: 'OPD', r: [68.5, 137, 6, 19], kind: 'appt', cap: 12, open: true, face: 'x-' },   // ห้อง 7–10 ห้องละ 3
    R10:  { b: 'OPD', r: [63, 128, 6, 7], kind: 'roomA', room: true, wait: 'DRWA', short: 'ห้อง 10', gate: [[63, 131.5]] },
    // ห้อง 11 แพทย์มาช่วยตรวจตอนคนไข้เยอะ ไม่มีจุดรอของฝั่งไหน (ส่งคิวมาก็นับอยู่ในห้อง)
    R11:  { b: 'OPD', r: [75, 141, 6, 7], kind: 'room', room: true, short: 'ห้อง 11', gate: [[75, 144.5]] },
    // อาคารอุบัติเหตุฉุกเฉิน (แปลนหน่วยงาน 24 พ.ย. 2569): ประตูหน้า → ทางเดินตรงกลาง x 136–142 ทุกห้องเปิดประตูเข้าทางเดินนี้
    // ซ้าย (หน้า → หลัง): ห้องเจาะเลือด · X-Ray · Ultrasound · โถงหลังรับทางเชื่อม OPD · ขวา (หน้า → หลัง): ห้องฉุกเฉิน · ห้องทำแผล-ฉีดยา · ห้องทันตกรรม
    DENT: { b: 'ERB', r: [142, 131, 16, 14], kind: 'room2', cap: 40, top: 3.4, face: 'y-', gate: [[142, 138]] },
    // shortcut: ที่นั่งห้องทำแผลยังไม่ได้รับแจ้ง ใช้ 10 ไปก่อน — แก้เมื่อหน่วยงานแจ้งจำนวนจริง
    WOUND: { b: 'ERB', r: [142, 145, 16, 14], kind: 'lab', cap: 10, top: 3.4, face: 'y-', gate: [[142, 152]] },     // 078 คัดกรอง → 047 ห้องทำแผล
    LAB:  { b: 'ERB', r: [122, 158, 14, 15], kind: 'lab', counter: true, cap: 20, walls4: true, gate: [[136, 165.5]] },
    XRAY: { b: 'ERB', r: [122, 150.5, 14, 7.5], kind: 'xray', cap: 4, top: 3.4, face: 'y-', walls4: true, gate: [[136, 154.25]] },
    US:   { b: 'ERB', r: [122, 143, 14, 7.5], kind: 'xray', cap: 4, top: 3.4, face: 'y-', walls4: true, gate: [[136, 146.75]] },
    // คัดกรอง 8 + รอแพทย์ 4 + เตียงรอแพทย์ ~7 + Observe 4
    ER:   { b: 'ERB', r: [142, 159, 16, 14], kind: 'er', cap: 23, top: 3.4, face: 'y-', gate: [[142, 166]] },
    // อาคารแยก
    ARI:  { b: 'ARI', r: [86, 218, 22, 8], kind: 'er', counter: true, cap: 38 },     // รอซักประวัติ 12 + รอแพทย์/การเงิน/ยา 26
    TTM:  { b: 'TTM', r: [199, 165, 7, 7], kind: 'ttm', counter: true, cap: 4 },
    PT:   { b: 'PT',  r: [199, 138, 7.5, 7], kind: 'room2', counter: true, cap: 6 },
    // shortcut: ที่นั่งคลินิกพิเศษยังไม่ได้รับแจ้ง ใช้ 40 ไปก่อน — แก้เมื่อหน่วยงานแจ้งจำนวนจริง
    SPC:  { b: 'SPC', r: [58, 103, 22, 16], kind: 'spc', counter: true, cap: 40 }
  };
  // โซนในอาคารผู้ป่วยนอก (เส้นประ + ป้ายบนพื้น)
  var ZONES = [
    { name: 'ฝั่งผู้ป่วยทั่วไป', r: [87, 128, 25, 45], c: '#1f9d6a' },
    { name: 'ฝั่งผู้ป่วยนัด', r: [57, 128, 24, 45], c: '#2563eb' }
  ];
  // ทางเดินในอาคาร (พื้นกระเบื้อง + ขอบ): ทางเดินตรงกลาง · ข้างห้อง 10 · ไปคลินิกพิเศษ · ระหว่างแถวห้อง · ทางเชื่อมคลินิกเด็ก ↔ ห้องบัตร
  var AISLES = [
    [81, 128, 6, 45], [57, 135, 24, 2], [57, 156, 24, 2.2], [75, 158.2, 6, 5.8],
    [87, 139, 25, 2], [103.4, 144, 2.8, 29],
    // อาคารอุบัติเหตุ: ทางเดินตรงกลาง · โถงหลังรับทางเชื่อมจาก OPD
    [136, 131, 6, 42], [122, 131, 14, 12]
  ];
  // พื้นที่ไม่มีจุดบริการ · open = ไม่มีผนัง · gate = ประตู
  var DECOR = [
  ];
  // โมเดลประกอบฉาก: desk = โต๊ะคอมพิวเตอร์ซักประวัติ (เจ้าหน้าที่นั่งเมื่อจุด on เปิดบริการ)
  var PROPS = [
    { t: 'desk', x: 58.3, y: 152, name: 'โต๊ะ 1', on: 'SCRA' },
    { t: 'desk', x: 58.3, y: 146.5, name: 'โต๊ะ 2', on: 'SCRA' },
    { t: 'desk', x: 58.3, y: 141, name: 'โต๊ะ 3', on: 'SCRA' }
  ];
  // อาคารอุบัติเหตุ: โต๊ะคอมชิดผนังหลังของแต่ละห้อง เจ้าหน้าที่หันเข้าที่นั่งรอ (แบบจุดซักประวัติ OPD)
  [[145.5, 'DENT', 131], [150, 'DENT', 131], [154.5, 'DENT', 131], [147, 'WOUND', 145], [153, 'WOUND', 145], [147, 'ER', 159], [153, 'ER', 159],
    [129, 'US', 143], [129, 'XRAY', 150.5]].forEach(function (d) { PROPS.push({ t: 'desk', dir: 'y-', x: d[0], y: d[2] + 0.75, name: '', on: d[1] }); });
  // เก้าอี้รอหน้าห้อง 10 จำนวน 5 ตัว หันเข้าประตูห้อง (ประตูด้าน −x ของห้อง)
  for (var ci = 0; ci < 5; ci++) PROPS.push({ t: 'chair', face: 'x+', x: 61.2, y: 128.9 + ci * 1.3, name: '' });
  // ฝั่ง Walk-in: 3 คู่ คู่ละ 2 โต๊ะ ชิดผนังหน้า (เจ้าหน้าที่หลังพิงผนัง) เว้นระหว่างคู่เล็กน้อย · dir 'y' = เจ้าหน้าที่หันไปทาง −y
  [88.2, 93.7, 99.2].forEach(function (px, i) {
    PROPS.push({ t: 'desk', dir: 'y', x: px, y: 170.3, name: 'โต๊ะซักประวัติ คู่ ' + (i + 1), on: 'SCRW' },
      { t: 'desk', dir: 'y', x: px + 2.3, y: 170.3, name: '', on: 'SCRW' });
  });
  // ทางเดินเชื่อม (มีหลังคา) — ย้ายระหว่าง 2 อาคารเดินผ่านทางนี้ ไม่ออกถนน · rs = พื้น/หลังคา · pts = เส้นทางเดินจาก a ไป b
  // คลินิกพิเศษ: ออกประตูหลังชิดผนังด้านในของห้อง 10 เดินตรง แล้วเลี้ยวซ้าย
  var LINKS = [
    { a: 'OPD', b: 'ERB', rs: [[112, 139, 10, 2]], pts: [[110.5, 140], [112, 140], [122, 140], [123.5, 140]] },
    { a: 'OPD', b: 'SPC', rs: [[82.5, 109.5, 3, 18.5], [80, 109.5, 2.5, 3]], pts: [[84, 129.5], [84, 128], [84, 111], [80, 111], [78.5, 111]] }
  ];
  function stadium(x0, x1, y0, y1) {   // สี่เหลี่ยมหัวมน (ปลายครึ่งวงกลมทางแกน x)
    var r = (y1 - y0) / 2, cy = y0 + r, pts = [];
    for (var i = 0; i <= 8; i++) { var t = Math.PI / 2 + i * Math.PI / 8; pts.push([x0 + r + r * Math.cos(t), cy + r * Math.sin(t)]); }
    for (var j = 0; j <= 8; j++) { var u = -Math.PI / 2 + j * Math.PI / 8; pts.push([x1 - r + r * Math.cos(u), cy + r * Math.sin(u)]); }
    return pts;
  }
  // ถนน (เทาเข้ม) · วงเวียนรอบหอพระ (RING ถนน, ISLAND เกาะกลางสนามหญ้า) · LANES = เส้นประกลางถนน
  var ROADS = [
    [188, 60, 6, 180],        // ถนนลาดยางด้านล่างของแผนผัง ปลาย y = 240 คือทางเข้า รพ.
    [10, 178, 178, 6]         // ถนนเลียบอาคารผู้ป่วยนอก (ผ่านด้านบนของวงเวียน)
  ];
  var RING = stadium(54, 112, 178, 210), ISLAND = stadium(60, 106, 184, 204);
  var RING_LANE = stadium(57, 109, 181, 207);
  var LANES = [[[191, 60], [191, 240]], [[10, 181], [70, 181]], [[96, 181], [188, 181]], RING_LANE.concat([RING_LANE[0]])];
  // ทางเท้า: หน้าอาคารผู้ป่วยนอก/อุบัติเหตุ · ริมถนนลาดยาง · วงเวียน → อาคาร ARI · ทางเข้าแผนไทย/กายภาพ
  var WALKS = [[16, 173, 172, 5], [184, 70, 4, 108], [184, 184, 4, 56], [87, 209, 4, 9], [194, 139.5, 5, 5], [194, 167.5, 5, 5]];
  // ทางเดินของหุ่น: กราฟจุดบนทางเท้า/ถนน — เดินตามเส้นที่สั้นที่สุด (route) · S = ทางเข้า รพ. · K–R3 = ตามวงเวียน
  var NODES = {
    S: [186, 240], A: [186, 181], W: [186, 175.5], ERD: [159, 175.5], ERB: [139, 175.5], OPD: [85, 175.5],
    K: [100, 181], R1: [109, 194], R2: [105, 203], R3: [89, 207], ARI: [89, 214], TTM: [186, 170], PT: [186, 142]
  };
  var EDGES = [['S', 'A'], ['A', 'W'], ['W', 'ERD'], ['ERD', 'ERB'], ['ERB', 'OPD'], ['A', 'K'], ['K', 'R1'], ['R1', 'R2'],
    ['R2', 'R3'], ['R3', 'ARI'], ['W', 'TTM'], ['TTM', 'PT']];
  // อาคารอื่นในแผนผัง (ไม่มีจุดบริการ): รูปหลายเหลี่ยม [x, y], สูง h, สี c = [หลังคา, ผนังซ้าย, ผนังขวา]
  var GREY = ['#dfe5ea', '#c3ced8', '#aebbc7'], GOLD = ['#f3d98b', '#dcb75a', '#c79f3f'];
  var SITE = [
    { name: 'อาคารจอดรถ', h: 3.2, c: GREY, sign: [78, 246],
      pts: [[40, 216], [70, 216], [70, 238], [76, 238], [76, 250], [40, 250]] },
    { name: 'อาคารอเนกประสงค์', h: 2.8, c: GREY, sign: [46, 196], pts: [[12, 187], [44, 187], [44, 203], [12, 203]] },
    { name: 'หอพระ', h: 1.2, c: GOLD, sign: [83, 201.5], pts: stadium(71, 95, 188, 200) }
  ];
  // ป้ายทางเข้า รพ. (กำแพงป้ายชื่อ + จอภาพด้านบน + พุ่มไม้) — โมเดล 3D วาดใน drawGate
  var GATE = { x: 181.3, y0: 198, y1: 214 };
  var TREES = [[48, 191], [124, 191], [20, 140], [40, 134],
    [140, 20], [156, 38], [170, 58], [142, 76], [168, 96], [148, 112], [176, 120], [130, 50], [112, 94], [92, 94],
    [26, 214], [30, 230], [82, 256], [100, 240], [116, 236], [124, 214], [118, 200], [140, 200], [156, 205], [168, 222], [150, 230]];
  for (var ty = 190; ty <= 230; ty += 10) TREES.push([197, ty]);   // ริมถนนลาดยางฝั่งตรงข้าม
  // สีพื้นเข้มพอให้แยกห้องได้ชัดบนจอใหญ่ แต่ยังอ่อนกว่าหุ่น
  var FLOOR = {
    hall: '#e8edf2', lab: '#e3dbf7', room: '#d6e5fa', rx: '#d3eedf', xray: '#d9e2ee',
    er: '#f8d8d5', room2: '#d2ecf2', ttm: '#efe3c3', decor: '#dde3ea',
    walk: '#d5efe0', appt: '#d5e3fa', ped: '#f9e3c3', roomA: '#cadcf9', spc: '#f3dcea'
  };
  var WALL_TOP = '#15803d';

  // ------------------------------------------------------------------ แสงตามเวลา
  // แสงกลางวัน 0–1 จากเวลาที่เลือก (นาทีนับจากเที่ยงคืน): สว่างเต็ม 06:50–17:10 · มืดก่อน 05:40 / หลัง 18:20 (ไทย ช่วง พ.ย.)
  function smooth01(x) { x = Math.max(0, Math.min(1, x)); return x * x * (3 - 2 * x); }
  function daylight(T) { var h = T / 60; return Math.min(smooth01((h - 5.7) / 1.1), 1 - smooth01((h - 17.2) / 1.1)); }
  // เสาไฟถนน: ริมทางเท้าหน้าอาคาร และทางเท้าริมถนนลาดยาง
  var LAMPS = [];
  for (var lx = 22; lx <= 180; lx += 15) LAMPS.push([lx, 177.6]);
  for (var ly = 76; ly <= 236; ly += 16) if (ly < 196 || ly > 216) LAMPS.push([185.2, ly]);

  var COL = { ok: '#1fa463', mid: '#f59e0b', high: '#e5484d' };
  var COL_TEXT = { mid: '#b45309', high: '#c9302c' };   // same hues, dark enough for text (>= 4.5:1 on white)
  // ความหนาแน่น = คนในโซน ÷ ที่นั่ง · < 45% ว่าง · 45–79% ปานกลาง · ≥ 80% แน่น
  var DENS = { low: { c: '#1fa463', t: 'ว่าง' }, mid: { c: '#f59e0b', t: 'ปานกลาง' }, full: { c: '#e5484d', t: 'แน่น' } };
  function densLevel(r) { return r >= 0.8 ? 'full' : r >= 0.45 ? 'mid' : 'low'; }
  function median(xs) {
    if (!xs.length) return 0;
    var a = xs.slice().sort(function (p, q) { return p - q; }), m = a.length >> 1;
    return a.length % 2 ? a[m] : (a[m - 1] + a[m]) / 2;
  }
  function pct(xs, p) {
    if (!xs.length) return 0;
    var a = xs.slice().sort(function (x, y) { return x - y; });
    return a[Math.min(a.length - 1, Math.floor(p * a.length))];
  }
  // สีเสื้อ = ความเร่งด่วน (u จาก API) · กางเกง = เพศ
  var SHIRT = { 1: '#dc2626', 2: '#f97316', 3: '#facc15', 4: '#22c55e', 5: '#0d9488', 6: '#8b5cf6' };
  var PANTS = { 1: '#3b82f6', 2: '#f472b6', 0: '#64748b' };
  var MOOD = { mid: '😤', high: '😡' };
  var URG_NAME = { 1: 'วิกฤต', 2: 'ฉุกเฉิน', 3: 'เร่งด่วน', 4: 'กึ่งเร่งด่วน', 5: 'ทั่วไป', 6: 'สูงอายุ / พระภิกษุ / ผู้พิการ' };
  var hover = null;
  function level(mins, target) {
    if (mins <= target) return 'ok';
    return mins >= target * 1.5 ? 'high' : 'mid';
  }
  function isWait(a) { return !a.nowait && !a.L.room; }

  // ------------------------------------------------------------------ สถานะ
  var canvas = $('map'), ctx = canvas.getContext('2d');
  var tlc = $('timeline'), tctx = tlc.getContext('2d');

  var data = null, areas = [], people = new Map(), occ = null;
  var state = {
    date: CFG.date, live: CFG.date === CFG.today, playing: true, speed: 60,
    T: 6 * 60, lastT: null, dep: '', find: null, findAt: 0, track: null, follow: false, followAt: 0
  };

  var cam = { k: 12, ox: 0, oy: 0 };
  var W = 0, H = 0, DPR = 1;
  var C30 = Math.cos(Math.PI / 6), S30 = 0.5, ZK = 0.95;
  function P(x, y, z) {
    return [cam.ox + (x - y) * C30 * cam.k, cam.oy + ((x + y) * S30 - (z || 0) * ZK) * cam.k];
  }

  // ------------------------------------------------------------------ เรขาคณิต
  var spare = 0;
  function spareSpot(id) {   // จุดที่ไม่มีในผัง — เรียงหลังอาคารผู้ป่วยนอก
    var L = { r: [90 + spare++ * 7, 92, 6, 6], kind: 'hall', spare: true };
    LAYOUT[id] = L;
    return L;
  }

  function buildAreas() {
    spare = 0;
    areas = data.areas.map(function (a) {
      var L = LAYOUT[a.id] || spareSpot(a.id);
      var r = L.r, x = r[0], y = r[1], w = r[2], d = r[3];
      var bld = BLD[L.b] || BLD.OPD, door = L.door || bld.door;
      var seats = [];
      if (L.room) {
        // จุดยืนในห้องตรวจ: เก้าอี้ผู้ป่วยหน้าโต๊ะ + ที่ว่างฝั่ง −x (เตียงชิดผนัง +x ด้านหลัง)
        seats = [[x + w / 2, y + d - 3.2], [x + 1.0, y + 1.3], [x + 1.0, y + 2.4], [x + 2.0, y + 1.3], [x + 2.0, y + 2.4]];
      } else if (L.face === 'x-') {   // แถวเก้าอี้หันไปทาง −x: แถวห่างกันตามแกน x ทางเดินกลางตามแกน y
        for (var fx = x + 1.2; fx <= x + w - 0.9; fx += 2.0) {
          for (var fy = y + 1.0; fy <= y + d - 0.9; fy += 1.45) {
            if (fy > y + d / 2 - 0.7 && fy < y + d / 2 + 0.5) continue;
            seats.push([fx, fy]);
          }
        }
      } else {
        var top = L.top || (L.counter ? 2.7 : 1.4), sx = 1.45, sy = 2.0;   // top = เว้นแถวหน้าไว้ให้โต๊ะ/เคาน์เตอร์
        for (var yy = y + top; yy <= y + d - 1.0; yy += sy) {
          for (var xx = x + 1.2; xx <= x + w - 0.9; xx += sx) {
            if (xx > x + w / 2 - 0.7 && xx < x + w / 2 + 0.5) continue;   // ทางเดินกลาง
            seats.push([xx, yy]);
          }
        }
      }
      if (L.rev) seats.reverse();
      if (L.cap && !L.room) seats = seats.slice(0, L.cap);   // วาดเก้าอี้ไม่เกินจำนวนจริง (ห้องเล็กวาดได้น้อยกว่า)
      var stand = [];
      for (var k = 0; k < 4; k++) {
        var off = [[0.6, 0.85], [0, 0.85], [0.6, 0.0], [0.3, 0.42]][k];
        seats.forEach(function (s) {
          var px = s[0] + off[0], py = s[1] + off[1];
          if (px < x + w - 0.4 && py < y + d - 0.4) stand.push([px, py]);
        });
      }
      return {
        id: a.id, name: a.name, target: a.target, nowait: !!a.nowait, deps: a.deps || [], L: L,
        x: x, y: y, w: w, d: d, cx: x + w / 2, cy: y + d / 2,
        chairs: L.queue ? [] : L.room ? seats.slice(0, 1) : seats, spots: seats.concat(stand), used: [],   // ห้องตรวจ: เก้าอี้ผู้ป่วยตัวเดียว
        door: door, node: L.node || bld.node, n: 0, avg: 0, max: 0
      };
    });
    // ช่วงเปิดบริการของแต่ละจุด = ผู้ป่วยคนแรกเข้า → คนสุดท้ายออก (ใช้แสดงเจ้าหน้าที่ประจำโต๊ะ)
    data.visits.forEach(function (v) {
      v.s.forEach(function (sg, i) {
        var a = areas[sg[1]], end = i + 1 < v.s.length ? v.s[i + 1][0] : v.e;
        if (!a) return;
        a.open = a.open ? [Math.min(a.open[0], sg[0]), Math.max(a.open[1], end)] : [sg[0], end];
      });
    });
  }

  // ------------------------------------------------------------------ จำลอง
  function visible(v) { return !state.dep || v.d === state.dep; }

  function whereAt(v, T) {
    if (T < v.s[0][0] || T >= v.e) return null;
    var seg = v.s[0];
    for (var i = 1; i < v.s.length; i++) {
      if (v.s[i][0] <= T) seg = v.s[i]; else break;
    }
    return seg;
  }

  function freeSpot(a) {
    for (var i = 0; i < a.used.length; i++) if (!a.used[i]) return i;
    return a.used.length;
  }

  function spotPos(a, i) {
    if (i < a.spots.length) return a.spots[i];
    var h = Math.sin(i * 12.9898) * 43758.5453, f = h - Math.floor(h);
    var g = Math.sin(i * 78.233) * 12345.678, e = g - Math.floor(g);
    return [a.x + 0.6 + f * (a.w - 1.2), a.y + 0.6 + e * (a.d - 1.2)];
  }

  // เส้นทางสั้นสุดบนกราฟทางเดินจาก node a ไป b (Dijkstra — กราฟเล็ก คำนวณครั้งเดียวแล้วจำไว้)
  var ROUTES = {};
  function route(a, b) {
    var key = a + '>' + b;
    if (!ROUTES[key]) {
      var dist = {}, prev = {}, todo = Object.keys(NODES);
      todo.forEach(function (n) { dist[n] = Infinity; });
      dist[a] = 0;
      while (todo.length) {
        todo.sort(function (p, q) { return dist[p] - dist[q]; });
        var u = todo.shift();
        if (u === b || dist[u] === Infinity) break;
        EDGES.forEach(function (e) {
          var v = e[0] === u ? e[1] : e[1] === u ? e[0] : null;
          if (!v) return;
          var d = dist[u] + Math.hypot(NODES[u][0] - NODES[v][0], NODES[u][1] - NODES[v][1]);
          if (d < dist[v]) { dist[v] = d; prev[v] = u; }
        });
      }
      var path = [];
      for (var n = b; n; n = prev[n]) path.unshift(NODES[n]);
      ROUTES[key] = path;
    }
    return ROUTES[key].slice();   // p.path ถูก shift ระหว่างเดิน — ห้ามแชร์ array
  }
  // ย้ายอาคาร: ออกประตูจุด a → ทางเดิน → เข้าประตูจุด b · อาคารที่มีทางเดินเชื่อม (LINKS) เดินผ่านทางเชื่อม ไม่ออกถนน
  // อาคาร inner (คลินิกพิเศษ) เข้าออกผ่านอาคารแม่เท่านั้น
  function linkPts(A, B) {
    for (var i = 0; i < LINKS.length; i++) {
      var l = LINKS[i];
      if (l.a === A && l.b === B) return l.pts.slice();
      if (l.a === B && l.b === A) return l.pts.slice().reverse();
    }
    return null;
  }
  function hop(A, B, a, b) {   // a, b = จุดบริการต้นทาง/ปลายทาง (null = ใช้ประตูอาคาร)
    var d = linkPts(A, B);
    if (d) return d;
    var inn, p1, p2;
    if ((inn = BLD[A].inner)) { p1 = linkPts(A, inn); p2 = hop(inn, B, null, b); return p1.concat(navBetween(inn, p1, p2), p2); }
    if ((inn = BLD[B].inner)) { p1 = hop(A, inn, a, null); p2 = linkPts(inn, B); return p1.concat(navBetween(inn, p1, p2), p2); }
    var da = a ? a.door : BLD[A].door, db = b ? b.door : BLD[B].door;
    return [da].concat(route(a ? a.node : BLD[A].node, b ? b.node : BLD[B].node), [db]);
  }
  function link(a, b) { return hop(a.L.b, b.L.b, a, b); }
  function fromGate(ta) {      // เข้า รพ. → จุดแรก
    var inn = BLD[ta.L.b].inner;
    return inn ? route('S', BLD[inn].node).concat([BLD[inn].door], linkPts(inn, ta.L.b)) : route('S', ta.node).concat([ta.door]);
  }
  function toGate(src) {       // ออกจาก รพ.
    var inn = BLD[src.L.b].inner;
    return inn ? linkPts(src.L.b, inn).concat([BLD[inn].door], route(BLD[inn].node, 'S')) : [src.door].concat(route(src.node, 'S'));
  }

  // ------------------------------------------------------------------ เดินในอาคาร
  // ตาราง 0.5 หน่วยต่ออาคาร: ผนังที่วาด (WALLS) กั้นการเดินข้ามช่อง · ช่องประตูเดินผ่านได้ · ในจุดบริการ/ห้องคิดค่าเดินแพงกว่าทางเดิน
  // → คนเดินตามทางเดิน เข้าออกทางประตูห้อง ไม่ทะลุผนัง · ระยะจากทุกช่องไปเป้าหมายคำนวณครั้งเดียว (Dijkstra) แล้วจำไว้
  var WALLS = [], NAV = {}, NAV_CS = 0.5, NAV_AREA_COST = 2.2;
  function navGrid(b) {
    if (NAV[b]) return NAV[b];
    var r = BLD[b].r, cs = NAV_CS, nx = Math.round(r[2] / cs), ny = Math.round(r[3] / cs), N = nx * ny;
    var g = { x0: r[0], y0: r[1], nx: nx, ny: ny, cost: new Float32Array(N), bR: new Uint8Array(N), bD: new Uint8Array(N), fields: {} };
    var inArea = function (x, y) {
      return areas.some(function (a) { return a.L.b === b && x > a.x && x < a.x + a.w && y > a.y && y < a.y + a.d; });
    };
    for (var j = 0; j < ny; j++) for (var i = 0; i < nx; i++) {
      g.cost[i + j * nx] = inArea(r[0] + (i + 0.5) * cs, r[1] + (j + 0.5) * cs) ? NAV_AREA_COST : 1;
    }
    // ผนังแนว x (y คงที่) กั้นการเดินลง (j → j+1) · ผนังแนว y (x คงที่) กั้นการเดินขวา (i → i+1)
    WALLS.forEach(function (w) {
      if (w[1] === w[3]) {
        var jw = Math.floor((w[1] - r[1]) / cs - 0.5);
        if (jw < 0 || jw >= ny - 1) return;
        for (var i2 = 0; i2 < nx; i2++) {
          var cx = r[0] + (i2 + 0.5) * cs;
          if (cx >= w[0] - 0.01 && cx <= w[2] + 0.01) g.bD[i2 + jw * nx] = 1;
        }
      } else {
        var iw = Math.floor((w[0] - r[0]) / cs - 0.5);
        if (iw < 0 || iw >= nx - 1) return;
        for (var j2 = 0; j2 < ny; j2++) {
          var cy = r[1] + (j2 + 0.5) * cs;
          if (cy >= w[1] - 0.01 && cy <= w[3] + 0.01) g.bR[iw + j2 * nx] = 1;
        }
      }
    });
    NAV[b] = g;
    return g;
  }
  function navCell(g, x, y) {
    var i = Math.max(0, Math.min(g.nx - 1, Math.floor((x - g.x0) / NAV_CS)));
    var j = Math.max(0, Math.min(g.ny - 1, Math.floor((y - g.y0) / NAV_CS)));
    return [i, j];
  }
  // เดินจาก (i,j) ไป (i+di, j+dj) ได้ไหม — แนวทแยงต้องผ่านได้ทั้งสองทางอ้อม (ไม่ตัดมุมผนัง)
  function navOk(g, i, j, di, dj) {
    var nx = g.nx, i2 = i + di, j2 = j + dj;
    if (i2 < 0 || j2 < 0 || i2 >= nx || j2 >= g.ny) return false;
    var h = function (a, b, d) { return d > 0 ? !g.bR[a + b * nx] : !g.bR[a - 1 + b * nx]; };
    var v = function (a, b, d) { return d > 0 ? !g.bD[a + b * nx] : !g.bD[a + (b - 1) * nx]; };
    if (!dj) return h(i, j, di);
    if (!di) return v(i, j, dj);
    return h(i, j, di) && v(i2, j, dj) && v(i, j, dj) && h(i, j2, di);
  }
  var NAV_DIRS = [[1, 0], [-1, 0], [0, 1], [0, -1], [1, 1], [1, -1], [-1, 1], [-1, -1]];
  function navField(g, tx, ty) {
    var c = navCell(g, tx, ty), key = c[0] + ',' + c[1];
    if (g.fields[key]) return g.fields[key];
    // Float64: Float32 ปัดค่าระยะลง ทำให้ d > dist[k] เป็นจริงแล้วข้ามช่องทิ้ง (เคยค้นได้แค่ ~77 ช่อง → เดินตรงทะลุผนัง)
    var nx = g.nx, N = nx * g.ny, dist = new Float64Array(N).fill(Infinity), heap = [];
    var push = function (d, k) {
      heap.push([d, k]);
      for (var n = heap.length - 1; n > 0;) { var p2 = (n - 1) >> 1; if (heap[p2][0] <= heap[n][0]) break; var t = heap[p2]; heap[p2] = heap[n]; heap[n] = t; n = p2; }
    };
    var pop = function () {
      var top = heap[0], last = heap.pop();
      if (heap.length) {
        heap[0] = last;
        for (var n = 0; ;) {
          var l = 2 * n + 1, rr = l + 1, m = n;
          if (l < heap.length && heap[l][0] < heap[m][0]) m = l;
          if (rr < heap.length && heap[rr][0] < heap[m][0]) m = rr;
          if (m === n) break;
          var t = heap[m]; heap[m] = heap[n]; heap[n] = t; n = m;
        }
      }
      return top;
    };
    var start = c[0] + c[1] * nx;
    dist[start] = 0; push(0, start);
    while (heap.length) {
      var it = pop(), d = it[0], k = it[1];
      if (d > dist[k]) continue;
      var i = k % nx, j = (k - i) / nx;
      for (var q = 0; q < 8; q++) {
        var di = NAV_DIRS[q][0], dj = NAV_DIRS[q][1];
        if (!navOk(g, i, j, di, dj)) continue;
        var k2 = k + di + dj * nx, nd = d + (di && dj ? 1.414 : 1) * (g.cost[k] + g.cost[k2]) / 2;
        if (nd < dist[k2]) { dist[k2] = nd; push(nd, k2); }
      }
    }
    g.fields[key] = dist;
    return dist;
  }
  // เส้นทางในอาคาร b จาก (sx, sy) ถึง (tx, ty): ไล่ลงตามระยะทีละช่อง แล้วตัดจุดที่อยู่แนวเดียวกันทิ้ง
  function navPath(b, sx, sy, tx, ty) {
    if (!BLD[b]) return [[tx, ty]];
    var g = navGrid(b), dist = navField(g, tx, ty), nx = g.nx;
    var c = navCell(g, sx, sy), i = c[0], j = c[1];
    if (!isFinite(dist[i + j * nx])) return [[tx, ty]];   // ไปไม่ถึง (ผนังปิด) → เดินตรงแบบเดิม
    // เริ่มที่กึ่งกลางช่องของตัวเอง — ไม่งั้นช่วงแรกตัดตรงจากตำแหน่งจริงไปจุดเลี้ยวแรก แล้วเฉียดขอบประตูทะลุผนัง
    var pts = [[g.x0 + (i + 0.5) * NAV_CS, g.y0 + (j + 0.5) * NAV_CS]], pd = null;
    for (var guard = 0; guard < 4000 && dist[i + j * nx] > 0; guard++) {
      var best = null, bd = dist[i + j * nx];
      for (var q = 0; q < 8; q++) {
        var di = NAV_DIRS[q][0], dj = NAV_DIRS[q][1];
        if (!navOk(g, i, j, di, dj)) continue;
        var dd = dist[i + di + (j + dj) * nx];
        if (dd < bd) { bd = dd; best = q; }
      }
      if (best === null) break;
      if (pd !== null && pd !== best) pts.push([g.x0 + (i + 0.5) * NAV_CS, g.y0 + (j + 0.5) * NAV_CS]);   // จุดเลี้ยว
      pd = best; i += NAV_DIRS[best][0]; j += NAV_DIRS[best][1];
    }
    pts.push([tx, ty]);
    return pts;
  }
  // ยังเดินอยู่นอกอาคาร b (บนถนน/ทางเชื่อม) แล้วมีย้ายจุดใหม่ → เดินเส้นเดิมให้จบก่อน แล้วค่อยเริ่มเส้นใหม่จากปลายเส้นเดิม
  // (ไม่งั้นเส้นใหม่เริ่มจากตำแหน่งนอกอาคาร แล้วเดินตรงทะลุผนังนอก)
  function carry(p, b) {
    var r = BLD[b].r, inside = p.x > r[0] && p.x < r[0] + r[2] && p.y > r[1] && p.y < r[1] + r[3];
    if (inside || !p.path || !p.path.length) return { keep: [], s: [p.x, p.y] };
    return { keep: p.path.slice(), s: p.path[p.path.length - 1] };
  }
  function withNav(pts, ta) {   // เส้นทางนอกอาคาร + เดินในอาคารปลายทางจากจุดสุดท้ายถึงกึ่งกลางจุดบริการ
    var l = pts[pts.length - 1];
    return pts.concat(navPath(ta.L.b, l[0], l[1], ta.cx, ta.cy));
  }
  function navBetween(b, p1, p2) {   // ผ่านอาคารกลางทาง (เช่น คลินิกพิเศษ → OPD → อุบัติเหตุ)
    var s = p1[p1.length - 1], t = p2[0];
    return navPath(b, s[0], s[1], t[0], t[1]);
  }

  function step(dt) {
    var T = state.T;
    // กระโดดเวลา (ลากไทม์ไลน์/เปลี่ยนวัน/เปลี่ยนแผนก) → วางหุ่นใหม่ทันที ไม่ต้องเดิน
    var jump = state.lastT === null || T < state.lastT - 0.01 || T - state.lastT > 20;
    state.lastT = T;
    if (jump) {
      areas.forEach(function (a) { a.used = []; });
      people.forEach(function (p) { p.area = -1; p.spot = -1; p.vis = false; p.path = null; p.leaving = false; });
    }

    var arrivals = [], visits = data.visits;
    var arrived = 0, inside = 0, left = 0, qual = 0;
    for (var i = 0; i < visits.length; i++) {
      var v = visits[i];
      if (!visible(v)) continue;
      var p = people.get(v.i);
      if (!p) {
        p = { id: v.i, area: -1, spot: -1, x: 0, y: 0, vis: false, a: 0, enter: 0, sit: false, v: 0, ph: Math.random() * 6.28 };
        people.set(v.i, p);
      }
      p.seen = true; p.u = v.u; p.g = v.g; p.dr = v.dr; p.ap = v.a;
      if (T >= v.s[0][0]) { arrived++; qual += v.q; }
      if (T >= v.e) left++;
      var seg = whereAt(v, T);
      var to = seg ? seg[1] : -1;
      var prevEnter = p.enter;
      if (seg) { inside++; p.enter = seg[0]; p.total = v.s[0][0]; }
      if (to !== p.area) {
        // ❤️ ได้รับบริการทันเวลา: ออกจากจุดรอภายในเป้าหมาย
        if (!jump && p.area >= 0 && p.vis) {
          var fa = areas[p.area], leftAt = seg ? seg[0] : v.e;
          if (isWait(fa) && fa.id !== 'REG' && leftAt - prevEnter <= fa.target) p.heart = performance.now();
        }
        if (p.area >= 0 && p.spot >= 0) areas[p.area].used[p.spot] = false;
        var from = p.area;
        p.area = to; p.spot = -1;
        if (to >= 0) {
          arrivals.push(p);
          var ta = areas[to];
          if (from < 0 && !p.vis) {
            p.x = NODES.S[0]; p.y = NODES.S[1];
            p.vis = true; p.a = jump ? 1 : 0; p.path = jump ? null : withNav(fromGate(ta), ta);
          } else if (from >= 0 && !jump && p.vis && areas[from].L.b !== ta.L.b) {
            var lk = link(areas[from], ta), c1 = carry(p, areas[from].L.b);   // ย้ายอาคาร: เดินในอาคารถึงประตู → ทางเดิน → เข้าประตูอาคารใหม่ → เดินถึงจุด
            p.path = c1.keep.concat(navPath(areas[from].L.b, c1.s[0], c1.s[1], lk[0][0], lk[0][1]), withNav(lk, ta));
          } else if (from >= 0 && !jump && p.vis) {
            var c2 = carry(p, ta.L.b);   // อาคารเดียวกัน: เดินตามทางเดิน เข้าทางประตูห้อง
            p.path = c2.keep.concat(navPath(ta.L.b, c2.s[0], c2.s[1], ta.cx, ta.cy));
          }
        } else if (p.vis) {
          var src = from >= 0 ? areas[from] : areas[0];
          if (jump) { p.vis = false; }
          else {
            var out = toGate(src), c3 = carry(p, src.L.b);
            p.path = c3.keep.concat(navPath(src.L.b, c3.s[0], c3.s[1], out[0][0], out[0][1]), out); p.leaving = true;
          }
        }
      }
    }
    people.forEach(function (p, key) { if (!p.seen) people.delete(key); p.seen = false; });

    arrivals.sort(function (a, b) { return a.enter - b.enter; });
    arrivals.forEach(function (p) {
      var a = areas[p.area];
      p.spot = freeSpot(a);
      a.used[p.spot] = true;
      if (p.leaving) { p.path = null; p.leaving = false; }
      var pos = spotPos(a, p.spot);
      p.tx = pos[0]; p.ty = pos[1];
      p.sit = p.spot < a.chairs.length;
      if (jump) { p.x = p.tx; p.y = p.ty; p.path = null; p.a = 1; p.vis = true; }
    });

    // เดินแบบนุ่มนวล: ความเร็วค่อย ๆ เร่ง/ผ่อน (ไม่กระตุก) · ใกล้จุดเลี้ยวเริ่มหันไปจุดถัดไปก่อน (มุมโค้ง) · ชะลอก่อนถึงที่นั่ง
    // ความเร็วเดินคงที่แบบคน: LIVE = 1.4 ม./วินาที (เดินปกติ) · เล่นย้อนหลังเร็วขึ้นตาม √ความเร็ว ไม่เกิน 9
    // (เดิมเร่งตามระยะ dist × 1.8 ทำให้คนไกล ๆ พุ่งเร็วผิดธรรมชาติ)
    var spd = state.live ? 1.4 : Math.min(9, 1.4 * Math.sqrt(state.speed));
    var ease = 1 - Math.exp(-dt * 7);
    people.forEach(function (p) {
      if (!p.vis) return;
      if (p.path && p.path.length > 1) {
        var w0 = p.path[0];
        if (Math.hypot(w0[0] - p.x, w0[1] - p.y) < 0.3) p.path.shift();   // เลี้ยวก่อนถึงจุดเล็กน้อย (มากกว่านี้ตัดมุมขอบประตู)
      }
      var tx = p.tx, ty = p.ty, last = !(p.path && p.path.length);
      if (!last) { tx = p.path[0][0]; ty = p.path[0][1]; last = p.path.length === 1 && p.leaving; }
      else if (p.leaving) { p.vis = false; return; }
      var dx = tx - p.x, dy = ty - p.y, dist = Math.sqrt(dx * dx + dy * dy);
      var want = spd;
      if (last || !p.path || !p.path.length) want = Math.min(want, 0.4 + dist * 2.5);   // ผ่อนก่อนถึง
      p.v += (want - p.v) * ease;
      var mv = p.v * dt;
      if (dist <= Math.max(mv, 0.01)) {
        p.x = tx; p.y = ty; p.v = Math.min(p.v, spd * 0.5);
        if (p.path && p.path.length) p.path.shift();
        p.walking = !!(p.path && p.path.length);
      } else {
        p.x += dx / dist * mv; p.y += dy / dist * mv; p.walking = dist > 0.08;
      }
      p.a = Math.min(1, p.a + dt * 2.5);
    });

    // สถิติรายจุด — เวลารอใช้ "ค่ากลาง" (มัธยฐาน) ไม่ให้คนที่ค้างนานผิดปกติดึงตัวเลข
    areas.forEach(function (a) { a.n = 0; a.waits = []; a.max = 0; });
    var worst = null, inSum = 0, capSum = 0, allWaits = [], drNow = {};
    people.forEach(function (p) {
      if (p.area < 0) return;
      var a = areas[p.area], w = T - p.enter;
      p.wait = w;
      p.lv = a.nowait ? 'ok' : level(w, a.target);
      a.n++;
      if (a.L.room && p.dr !== null && p.dr !== undefined) drNow[p.dr] = 1;
      if (!isWait(a)) return;
      a.waits.push(w);
      allWaits.push(w);
      if (w > a.max) a.max = w;
      if (!worst || w > worst.w) worst = { w: w, a: a };
    });
    areas.forEach(function (a) {
      a.served = a.L.room ? servedAt(a, T) : 0;
      a.avg = median(a.waits);
      a.lv = a.waits.length ? level(a.avg, a.target) : 'ok';
      a.cap = a.L.queue ? 0 : Math.max(1, a.L.cap || a.chairs.length);
      a.dens = a.cap ? a.n / a.cap : 0;
      a.dl = densLevel(a.dens);
      if (isWait(a) && a.cap) { inSum += a.n; capSum += a.cap; }
    });
    state.stats = {
      arrived: arrived, inside: inside, left: left, worst: worst, qual: qual,
      dens: capSum ? inSum / capSum : 0, wait: median(allWaits), drNow: Object.keys(drNow).length
    };
  }

  // ------------------------------------------------------------------ วาด
  function poly(pts, fill, stroke, lw) {
    ctx.beginPath();
    for (var i = 0; i < pts.length; i++) {
      var p = P(pts[i][0], pts[i][1], pts[i][2]);
      if (i) ctx.lineTo(p[0], p[1]); else ctx.moveTo(p[0], p[1]);
    }
    ctx.closePath();
    if (fill) { ctx.fillStyle = fill; ctx.fill(); }
    if (stroke) { ctx.strokeStyle = stroke; ctx.lineWidth = lw || 1; ctx.stroke(); }
  }
  function rect(x, y, w, d, z, fill, stroke, lw) {
    poly([[x, y, z], [x + w, y, z], [x + w, y + d, z], [x, y + d, z]], fill, stroke, lw);
  }
  function box(x, y, z, w, d, h, top, left, right) {
    poly([[x, y + d, z], [x + w, y + d, z], [x + w, y + d, z + h], [x, y + d, z + h]], left);
    poly([[x + w, y, z], [x + w, y + d, z], [x + w, y + d, z + h], [x + w, y, z + h]], right);
    poly([[x, y, z + h], [x + w, y, z + h], [x + w, y + d, z + h], [x, y + d, z + h]], top);
  }
  // แท่งรูปหลายเหลี่ยม (อาคารที่ไม่มีจุดบริการ): วาดผนังที่หันหาผู้ชม (normal ชี้ทาง +x+y) จากไกลไปใกล้ แล้วหลังคา
  function prism(pts, h, c) {
    var n = pts.length, area = 0, faces = [];
    for (var i = 0; i < n; i++) { var p = pts[i], q = pts[(i + 1) % n]; area += p[0] * q[1] - q[0] * p[1]; }
    var sg = area > 0 ? 1 : -1;
    for (var j = 0; j < n; j++) {
      var a = pts[j], b = pts[(j + 1) % n], nx = (b[1] - a[1]) * sg, ny = (a[0] - b[0]) * sg;
      if (nx + ny > 0.001) faces.push({ a: a, b: b, k: a[0] + a[1] + b[0] + b[1], c: ny >= nx ? c[1] : c[2] });
    }
    faces.sort(function (f, g) { return f.k - g.k; });
    faces.forEach(function (f) {
      poly([[f.a[0], f.a[1], 0], [f.b[0], f.b[1], 0], [f.b[0], f.b[1], h], [f.a[0], f.a[1], h]], f.c, f.c, 0.6);
    });
    poly(pts.map(function (p) { return [p[0], p[1], h]; }), c[0], 'rgba(15,42,92,.25)');
  }
  function roundRect(x, y, w, h, r) {
    r = Math.min(r, w / 2, Math.abs(h) / 2);
    ctx.beginPath();
    ctx.moveTo(x + r, y); ctx.arcTo(x + w, y, x + w, y + h, r); ctx.arcTo(x + w, y + h, x, y + h, r);
    ctx.arcTo(x, y + h, x, y, r); ctx.arcTo(x, y, x + w, y, r); ctx.closePath();
  }

  // วัตถุที่ต้องเรียงความลึก: ผนัง เก้าอี้ เคาน์เตอร์ ต้นไม้ หุ่น
  var statics = [];
  function buildStatics() {
    statics = []; WALLS = []; NAV = {};
    // ผนังกั้นห้องในอาคาร (โปร่งแสง เตี้ย) — ผนังนอกอาคารใช้ฟังก์ชันเดียวกันแต่สูง/หนากว่า และเว้นช่องประตู
    var WH = 1.1;
    var IN = { h: WH, t: 0.12, face: 'rgba(246,249,252,.92)', side: 'rgba(210,221,232,.95)', cap: '#94a6ba' };
    function nearDoor(doors, mx, my) {
      return doors && doors.some(function (d) { return Math.abs(d[0] - mx) < (d[2] || 1.7) && Math.abs(d[1] - my) < (d[2] || 1.7); });
    }
    function wallX(x0, x1, y, st, doors) {
      st = st || IN;
      for (var x = x0; x < x1 - 0.001; x += 1) {
        (function (xa, xb) {
          if (nearDoor(doors, (xa + xb) / 2, y)) return;
          WALLS.push([xa, y, xb, y]);
          statics.push({ k: (xa + xb) / 2 + y - 0.05, draw: function () {
            poly([[xa, y, 0], [xb, y, 0], [xb, y, st.h], [xa, y, st.h]], st.face, null);
            poly([[xa, y - st.t, st.h], [xb, y - st.t, st.h], [xb, y, st.h], [xa, y, st.h]], st.cap);
          } });
        })(x, Math.min(x + 1, x1));
      }
    }
    function wallY(y0, y1, x, st, doors) {
      st = st || IN;
      for (var y = y0; y < y1 - 0.001; y += 1) {
        (function (ya, yb) {
          if (nearDoor(doors, x, (ya + yb) / 2)) return;
          WALLS.push([x, ya, x, yb]);
          statics.push({ k: x + (ya + yb) / 2 - 0.05, draw: function () {
            poly([[x, ya, 0], [x, yb, 0], [x, yb, st.h], [x, ya, st.h]], st.side, null);
            poly([[x - st.t, ya, st.h], [x, ya, st.h], [x, yb, st.h], [x - st.t, yb, st.h]], st.cap);
          } });
        })(y, Math.min(y + 1, y1));
      }
    }
    // ผนังนอกอาคาร: ด้านหลัง/ซ้ายสูง (เห็นเป็นตัวตึก) · ด้านหน้า/ขวาเป็นขอบเตี้ย (ตัดให้มองเห็นข้างใน)
    var BACK = { h: 2.6, t: 0.35, face: '#edf1f4', side: '#d0d9e1', cap: WALL_TOP };
    var FRONT = { h: 0.45, t: 0.3, face: '#cbd5de', side: '#b6c3cf', cap: WALL_TOP };
    Object.keys(BLD).forEach(function (id) {
      var r = BLD[id].r, doors = [BLD[id].door];
      LINKS.forEach(function (l) { if (l.a === id || l.b === id) doors.push(l.a === id ? l.pts[1] : l.pts[l.pts.length - 2]); });
      areas.forEach(function (a) { if (a.L.b === id && a.L.door) doors.push(a.L.door); });
      wallX(r[0], r[0] + r[2], r[1], BACK, doors);
      wallY(r[1], r[1] + r[3], r[0], BACK, doors);
      wallX(r[0], r[0] + r[2], r[1] + r[3], FRONT, doors);
      wallY(r[1], r[1] + r[3], r[0] + r[2], FRONT, doors);
      var sg = BLD[id].sign;
      statics.push({ k: sg[0] + sg[1] + 0.5, draw: function () { drawSign(BLD[id].name, sg[0], sg[1]); } });
      // กันสาดหน้าประตูอาคาร (ประตูที่อยู่ผนังหน้า): เสา 2 ต้น + หลังคาบาง
      var dr = BLD[id].door;
      if (Math.abs(dr[1] - (r[1] + r[3])) < 0.01) {
        statics.push({ k: dr[0] + dr[1] + 3, draw: function () {
          [[-2.6, 2.3], [2.4, 2.3]].forEach(function (o) { box(dr[0] + o[0], dr[1] + o[1], 0, 0.2, 0.2, 2.5, '#cbd5e1', '#94a3b8', '#7c8ca3'); });
          box(dr[0] - 3, dr[1] - 0.2, 2.5, 6, 2.9, 0.18, '#f1f5f9', '#b4c1d1', '#9eadc1');
          if (id === 'ERB') drawErSign();
        } });
      }
    });
    // กรอบประตูห้อง: เสา 2 ข้างช่องประตู (ผนังแนว x หรือแนว y ดูจากตำแหน่งประตูบนขอบห้อง)
    areas.forEach(function (a) {
      (a.L.gate || []).forEach(function (gt) {
        var rr = gt[2] || 1.2, vert = Math.abs(gt[0] - a.x) < 0.01 || Math.abs(gt[0] - a.x - a.w) < 0.01;
        [-1, 1].forEach(function (s) {
          var px = vert ? gt[0] : gt[0] + s * rr, py = vert ? gt[1] + s * rr : gt[1];
          statics.push({ k: px + py, draw: function () { box(px - 0.08, py - 0.08, 0, 0.16, 0.16, 1.2, '#94a3b8', '#64748b', '#475569'); } });
        });
      });
    });
    // เสาไฟถนน: เสา + แขน + โคม (กลางคืนโคมสว่าง)
    LAMPS.forEach(function (l) {
      statics.push({ k: l[0] + l[1], draw: function () { drawLamp(l[0], l[1]); } });
    });
    SITE.forEach(function (s) {
      statics.push({ k: s.sign[0] + s.sign[1] + 0.5, draw: function () { drawSign(s.name, s.sign[0], s.sign[1], 'rgba(100,116,139,.92)'); } });
    });
    statics.push({ k: GATE.x + 1.2 + (GATE.y0 + GATE.y1) / 2, draw: drawGate });
    statics.push({ k: GATE.x + 2.4 + (GATE.y0 + GATE.y1) / 2, draw: drawGateSpots });
    // หลังคาทางเดินเชื่อม: เสา 4 ต้น + หลังคาโปร่งแสง (ยังเห็นคนที่เดินอยู่ข้างใต้)
    LINKS.forEach(function (l) { l.rs.forEach(function (br) {
      statics.push({ k: br[0] + br[2] + br[1] + br[3], draw: function () {
        [[0.1, 0.1], [br[2] - 0.3, 0.1], [0.1, br[3] - 0.3], [br[2] - 0.3, br[3] - 0.3]].forEach(function (o) {
          box(br[0] + o[0], br[1] + o[1], 0, 0.2, 0.2, 2.4, '#cbd5e1', '#94a3b8', '#7c8ca3');
        });
        box(br[0] - 0.3, br[1] - 0.3, 2.4, br[2] + 0.6, br[3] + 0.6, 0.2, 'rgba(110,190,160,.55)', 'rgba(21,128,61,.6)', 'rgba(21,128,61,.5)');
      } });
    }); });
    function counter(x, y, w) {
      var cw = Math.min(w - 2, 7), cx2 = x + (w - cw) / 2;
      statics.push({ k: cx2 + cw / 2 + y + 1.3, draw: function () {
        box(cx2, y + 0.9, 0, cw, 0.7, 0.95, '#f1f5f9', '#b4c1d1', '#9eadc1');
        for (var m = 0; m < 3; m++) {
          box(cx2 + 0.8 + m * (cw - 1.6) / 2 - 0.25, y + 1.0, 0.95, 0.5, 0.08, 0.4, '#334155', '#1e293b', '#0f172a');
        }
      } });
    }
    // ผนังห้อง: ด้านต่ำ (y, x) เสมอ · ด้านสูงเมื่อ walls4/ห้องตรวจ · ไม่ซ้ำผนังนอกอาคาร · gate = ช่องประตู
    function roomWalls(x, y, w, d, b, all, gates) {
      var r = (BLD[b] || {}).r || [-1, -1, 0, 0], g = (gates || []).map(function (q) { return [q[0], q[1], q[2] || 1.2]; });   // ประตูกว้าง ~2–3 หน่วย
      var eq = function (p, q) { return Math.abs(p - q) < 0.01; };
      if (!eq(y, r[1])) wallX(x, x + w, y, null, g);
      if (!eq(x, r[0])) wallY(y, y + d, x, null, g);
      if (!all) return;
      if (!eq(y + d, r[1] + r[3])) wallX(x, x + w, y + d, null, g);
      if (!eq(x + w, r[0] + r[2])) wallY(y, y + d, x + w, null, g);
    }
    DECOR.forEach(function (o) { if (!o.open) roomWalls(o.r[0], o.r[1], o.r[2], o.r[3], 'OPD', !!o.gate, o.gate); });
    // ชิ้นส่วนแต่ละชิ้นเรียงความลึกของตัวเอง (เจ้าหน้าที่ / โต๊ะ / เก้าอี้) — วาดรวมก้อนเดียวทำให้ซ้อนผิดชั้นกับของรอบข้าง
    PROPS.forEach(function (o) { propParts(o).forEach(function (pt) { statics.push(pt); }); });
    areas.forEach(function (a) {
      if (!a.L.open) roomWalls(a.x, a.y, a.w, a.d, a.L.b, a.L.room || a.L.walls4, a.L.gate);
      if (a.L.counter) counter(a.x, a.y, a.w);
      if (a.L.room) {
        var dw = Math.min(2.8, a.w - 1.6), dx = a.x + (a.w - dw) / 2, dy = a.y + a.d - 2.4;
        statics.push({ k: dx + dw / 2 + dy + 0.6, draw: function () {
          box(dx, dy, 0, dw, 1.1, 0.85, '#f1f5f9', '#c3cedb', '#aebccc');
          box(dx + dw / 2 - 0.35, dy + 0.9, 0.85, 0.7, 0.1, 0.45, '#334155', '#1e293b', '#0f172a');
        } });
      }
      a.chairs.forEach(function (c) {
        statics.push({ k: c[0] + c[1] - 0.2, draw: function () { drawChair(c[0], c[1], a.L.face); } });
      });
    });
    TREES.forEach(function (t) {
      statics.push({ k: t[0] + t[1], draw: function () {
        var b = P(t[0], t[1], 0);
        ctx.fillStyle = 'rgba(0,0,0,.12)';
        ctx.beginPath(); ctx.ellipse(b[0] + 0.5 * cam.k, b[1], 1.0 * cam.k, 0.45 * cam.k, 0, 0, 7); ctx.fill();
        box(t[0] - 0.12, t[1] - 0.12, 0, 0.24, 0.24, 1.0, '#8b5e3c', '#7a5133', '#6b462c');
        var c = P(t[0], t[1], 2.0);
        var gr = ctx.createRadialGradient(c[0] - 0.3 * cam.k, c[1] - 0.4 * cam.k, 0.1 * cam.k, c[0], c[1], 1.1 * cam.k);
        gr.addColorStop(0, '#4ade80'); gr.addColorStop(1, '#15803d');
        ctx.fillStyle = gr;
        ctx.beginPath(); ctx.arc(c[0], c[1], 1.05 * cam.k, 0, 7); ctx.fill();
      } });
    });
    statics.sort(function (a, b) { return a.k - b.k; });   // เรียงครั้งเดียว — ทุกเฟรมเรียงแค่หุ่นแล้ว merge
  }

  // เก้าอี้รอตรวจ: ขาโลหะ + เบาะ + พนักพิง (โทนฟ้าอ่อน ไม่แย่งสายตาจากหุ่น)
  // face 'x-' = หันหน้าไปทาง −x (พนักพิงอยู่ด้าน +x) · ปกติหันไปทาง +y
  function drawChair(cx, cy, face) {
    var x = cx - 0.26, y = cy - 0.22;
    var b = P(cx, cy + 0.1, 0);
    ctx.fillStyle = 'rgba(15,42,92,.10)';
    ctx.beginPath(); ctx.ellipse(b[0], b[1], 0.42 * cam.k, 0.2 * cam.k, 0, 0, 7); ctx.fill();
    box(x + 0.05, y + 0.36, 0, 0.05, 0.05, 0.3, '#8291a5', '#6b7a8f', '#566579');
    box(x + 0.42, y + 0.36, 0, 0.05, 0.05, 0.3, '#8291a5', '#6b7a8f', '#566579');
    box(x, y, 0.3, 0.52, 0.44, 0.08, '#6fb0e6', '#3b82c4', '#2f6fae');
    if (face === 'x-' || face === 'x+') {   // พนักพิงอยู่ด้านตรงข้ามกับทิศที่หัน
      var bx = face === 'x-' ? x + 0.48 : x - 0.04;
      box(bx, y + 0.0, 0.38, 0.08, 0.44, 0.4, '#4f97d8', '#3b82c4', '#2f6fae');
      box(bx, y + 0.0, 0.78, 0.08, 0.44, 0.02, '#8cc3ee', '#4f97d8', '#4f97d8');
      return;
    }
    if (face === 'y-') {   // หันไปทาง −y: พนักพิงด้าน +y
      box(x + 0.02, y + 0.4, 0.38, 0.48, 0.08, 0.4, '#4f97d8', '#3b82c4', '#2f6fae');
      box(x + 0.02, y + 0.4, 0.78, 0.48, 0.08, 0.02, '#8cc3ee', '#4f97d8', '#4f97d8');
      return;
    }
    box(x + 0.02, y - 0.04, 0.38, 0.48, 0.08, 0.4, '#4f97d8', '#3b82c4', '#2f6fae');
    box(x + 0.02, y - 0.04, 0.78, 0.48, 0.08, 0.02, '#8cc3ee', '#4f97d8', '#4f97d8');
  }
  // อาคารเปิดไฟ: มีคนอยู่ในอาคาร หรือเป็นอาคารอุบัติเหตุ (เปิด 24 ชม.)
  function bldLit(id) { return id === 'ERB' || areas.some(function (a) { return a.L.b === id && a.n > 0; }); }
  // เสาไฟถนน: แขนยื่นไปทางถนน (ทางเท้าหน้าอาคาร → +y · ริมถนนลาดยาง → +x)
  function drawLamp(x, y) {
    var ax = x > 184 ? 1 : 0, ay = ax ? 0 : 1, on = daylight(state.T) < 0.6;
    box(x - 0.09, y - 0.09, 0, 0.18, 0.18, 3.6, '#64748b', '#475569', '#334155');
    box(x - 0.05, y - 0.05, 3.5, 0.1 + ax * 0.9, 0.1 + ay * 0.9, 0.08, '#64748b', '#475569', '#334155');
    var hx = x + ax * 0.9 - 0.22, hy = y + ay * 0.9 - 0.22;
    box(hx, hy, 3.3, 0.44, 0.44, 0.16, '#475569', '#334155', '#1e293b');
    rect(hx + 0.05, hy + 0.05, 0.34, 0.34, 3.29, on ? '#fff1d6' : '#cbd5e1');
  }
  // ป้าย EMERGENCY บนกันสาดหน้าประตูอาคารอุบัติเหตุ: กล่องแดง ตัวอักษรขาวบนหน้า +y (หันหาถนน)
  function erSign() { var d = BLD.ERB.door; return { x: d[0] - 2.2, y: d[1] + 2.4, z: 2.68, w: 4.4, d: 0.25, h: 0.9 }; }
  function drawErSign() {
    var s = erSign(), night = daylight(state.T) < 0.6;
    box(s.x, s.y, s.z, s.w, s.d, s.h, '#b91c1c', night ? '#ef4444' : '#dc2626', '#991b1b');
    if (cam.k < 4) return;
    var o = P(s.x, s.y + s.d, s.z + s.h), k = cam.k / 20;   // หน่วยย่อย 1/20 บนหน้า +y: u ไปทาง +x, v ลงล่าง
    ctx.save();
    ctx.setTransform(DPR * C30 * k, DPR * S30 * k, 0, DPR * ZK * k, DPR * o[0], DPR * o[1]);
    ctx.font = '700 12px Kanit, sans-serif'; ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
    ctx.fillStyle = '#ffffff'; ctx.fillText('EMERGENCY', s.w * 10, s.h * 10 + 1);
    ctx.restore();
  }
  // ไฟส่องป้ายชื่อ รพ.: โคมเตี้ย 2 ดวงหน้ากำแพงป้าย (กลางคืนเลนส์สว่าง)
  function drawGateSpots() {
    var on = daylight(state.T) < 0.6;
    [GATE.y0 + 3, GATE.y1 - 3].forEach(function (y) {
      box(GATE.x + 2.2, y - 0.2, 0, 0.4, 0.4, 0.25, '#475569', '#334155', '#1e293b');
      box(GATE.x + 2.15, y - 0.15, 0.25, 0.1, 0.3, 0.2, on ? '#ffd28a' : '#94a3b8', on ? '#ffbf5e' : '#64748b', on ? '#ffbf5e' : '#64748b');
    });
  }
  // เงาอาคาร/ที่ตั้งบนพื้น: ฉายรอยเท้าอาคารไปตามทิศแสงอาทิตย์ (เช้าเอียงไปทาง −x · บ่ายไปทาง +x) ยาวขึ้นตอนแดดต่ำ
  function hull(pts) {
    pts = pts.slice().sort(function (a, b) { return a[0] - b[0] || a[1] - b[1]; });
    var cr = function (o, a, b) { return (a[0] - o[0]) * (b[1] - o[1]) - (a[1] - o[1]) * (b[0] - o[0]); }, lo = [], up = [];
    pts.forEach(function (p) { while (lo.length > 1 && cr(lo[lo.length - 2], lo[lo.length - 1], p) <= 0) lo.pop(); lo.push(p); });
    pts.slice().reverse().forEach(function (p) { while (up.length > 1 && cr(up[up.length - 2], up[up.length - 1], p) <= 0) up.pop(); up.push(p); });
    return lo.slice(0, -1).concat(up.slice(0, -1));
  }
  function drawShadows(T) {
    var L = daylight(T);
    if (L < 0.05) return;
    var h = T / 60, sx = Math.max(-1.3, Math.min(1.3, (h - 12) / 4.5)), sy = 0.45, m = 0.55 + Math.abs(h - 12) / 8;
    var col = 'rgba(30,41,59,' + (0.16 * L).toFixed(3) + ')';
    var cast = function (pts, ht) {
      var off = pts.map(function (p) { return [p[0] + sx * m * ht, p[1] + sy * m * ht]; });
      poly(hull(pts.concat(off)).map(function (p) { return [p[0], p[1], 0]; }), col);
    };
    var rp = function (r) { return [[r[0], r[1]], [r[0] + r[2], r[1]], [r[0] + r[2], r[1] + r[3]], [r[0], r[1] + r[3]]]; };
    Object.keys(BLD).forEach(function (id) { cast(rp(BLD[id].r), 2.6); });
    SITE.forEach(function (s) { cast(s.pts, s.h); });
    LINKS.forEach(function (l) { l.rs.forEach(function (r) { cast(rp(r), 2.4); }); });
  }
  // เงาสัมผัสโคนผนังในอาคาร: แถบเข้มบาง ๆ บนพื้นด้าน +x/+y ของผนังทุกเส้น ให้ห้องดูมีมิติ
  function drawWallAO() {
    var col = 'rgba(15,23,42,.07)';
    WALLS.forEach(function (w) {
      if (w[1] === w[3]) rect(w[0], w[1], w[2] - w[0], 0.35, 0.0025, col);
      else rect(w[0], w[1], 0.35, w[3] - w[1], 0.0025, col);
    });
  }
  // ความมืดกลางคืน: ชั้นมืดบน canvas แยก เจาะรูตรงอาคารที่เปิดไฟ/ใต้เสาไฟ แล้ววางทับ · แสงโทนอุ่นซ้อนแบบ screen
  // ช่วงเช้า/เย็นใส่โทนส้มอ่อน
  var shade = document.createElement('canvas'), sctx = shade.getContext('2d');
  function drawLighting(T) {
    var L = daylight(T), dark = 1 - L, dusk = 1 - Math.abs(2 * L - 1);
    if (dusk > 0.02) { ctx.fillStyle = 'rgba(255,150,80,' + (0.1 * dusk).toFixed(3) + ')'; ctx.fillRect(0, 0, W, H); }
    if (dark < 0.02) return;
    if (shade.width !== canvas.width || shade.height !== canvas.height) { shade.width = canvas.width; shade.height = canvas.height; }
    sctx.setTransform(DPR, 0, 0, DPR, 0, 0);
    sctx.globalCompositeOperation = 'source-over';
    sctx.clearRect(0, 0, W, H);
    sctx.fillStyle = 'rgba(22,30,54,' + (0.6 * dark).toFixed(3) + ')';   // กลางคืนโทนน้ำเงินเทา ไม่ดำสนิท
    sctx.fillRect(0, 0, W, H);
    sctx.globalCompositeOperation = 'destination-out';
    Object.keys(BLD).forEach(function (id) {
      if (!bldLit(id)) return;
      var r = BLD[id].r, c = [P(r[0], r[1], 0), P(r[0] + r[2], r[1], 0), P(r[0] + r[2], r[1] + r[3], 0), P(r[0], r[1] + r[3], 0)];
      sctx.fillStyle = 'rgba(0,0,0,.78)';
      sctx.beginPath(); sctx.moveTo(c[0][0], c[0][1]); for (var i = 1; i < 4; i++) sctx.lineTo(c[i][0], c[i][1]); sctx.closePath(); sctx.fill();
    });
    // เสาไฟ: แขนยื่นไปทางถนน → แสงตกพื้นเป็นวงรีใต้โคม (เอียงไปทางถนน) ไล่ความสว่างนุ่ม ๆ
    var lamps = LAMPS.map(function (l) {
      var ax = l[0] > 184 ? 1 : 0, ay = ax ? 0 : 1, hx = l[0] + ax * 0.9, hy = l[1] + ay * 0.9;
      return { l: l, ax: ax, ay: ay, head: P(hx, hy, 3.28), g: P(hx + ax * 0.6, hy + ay * 0.6, 0) };
    });
    // วงแสงบนพื้นแบบมุมเอียง: บีบทั้งวงและ gradient ตามแกนตั้ง 0.58 ให้จางถึงศูนย์ตรงขอบพอดี (ไม่เกิดขอบวงแข็ง)
    var pool = function (c, g, rr, stops) {
      c.save(); c.translate(g[0], g[1]); c.scale(1, 0.58);
      var gr = c.createRadialGradient(0, 0, 0, 0, 0, rr);
      stops.forEach(function (s) { gr.addColorStop(s[0], s[1]); });
      c.fillStyle = gr; c.beginPath(); c.arc(0, 0, rr, 0, 7); c.fill();
      c.restore();
    };
    lamps.forEach(function (m) {
      // เจาะความมืดแค่บางส่วน (ไม่งั้นเห็นพื้นเทาสว่างเป็นวงขาว) แล้วให้สีอุ่นด้านล่างเป็นตัวให้แสง
      pool(sctx, m.g, 7 * cam.k, [[0, 'rgba(0,0,0,.5)'], [0.3, 'rgba(0,0,0,.36)'], [0.6, 'rgba(0,0,0,.16)'], [0.85, 'rgba(0,0,0,.04)'], [1, 'rgba(0,0,0,0)']]);
    });
    // ป้ายชื่อ รพ. (หน้ากำแพงป้าย) และป้าย EMERGENCY: เจาะความมืดให้เห็นป้ายสว่าง
    var face = function (pts) { sctx.fillStyle = 'rgba(0,0,0,.85)'; sctx.beginPath(); pts.forEach(function (q, i) { var s = P(q[0], q[1], q[2]); if (i) sctx.lineTo(s[0], s[1]); else sctx.moveTo(s[0], s[1]); }); sctx.closePath(); sctx.fill(); };
    var fx = GATE.x + 1.2;
    face([[fx, GATE.y0, 0], [fx, GATE.y1, 0], [fx, GATE.y1, 2.4], [fx, GATE.y0, 2.4]]);
    var es = erSign();
    face([[es.x, es.y + es.d, es.z], [es.x + es.w, es.y + es.d, es.z], [es.x + es.w, es.y + es.d, es.z + es.h], [es.x, es.y + es.d, es.z + es.h]]);
    ctx.save(); ctx.setTransform(1, 0, 0, 1, 0, 0); ctx.drawImage(shade, 0, 0); ctx.restore();
    var a = function (v) { return (v * dark).toFixed(3); };
    // เงาเสาไฟทอดออกจากโคม (ด้านตรงข้ามแขน) เข้มที่โคนแล้วจางไป
    lamps.forEach(function (m) {
      var b = P(m.l[0], m.l[1], 0), e = P(m.l[0] - m.ax * 3.2, m.l[1] - m.ay * 3.2, 0);
      var gr = ctx.createLinearGradient(b[0], b[1], e[0], e[1]);
      gr.addColorStop(0, 'rgba(15,20,35,' + a(0.45) + ')'); gr.addColorStop(1, 'rgba(15,20,35,0)');
      ctx.strokeStyle = gr; ctx.lineWidth = Math.max(1, cam.k * 0.18); ctx.lineCap = 'round';
      ctx.beginPath(); ctx.moveTo(b[0], b[1]); ctx.lineTo(e[0], e[1]); ctx.stroke();
    });
    // แสงขาวอุ่น (warm white ~3000K): วงแสงบนพื้น + ลำแสงจางจากโคมลงพื้น + โคมเรืองแสง · ซ้อนแบบ screen
    ctx.globalCompositeOperation = 'lighter';   // บวกแสงสีทอง (warm white) ทับพื้น
    lamps.forEach(function (m) {
      pool(ctx, m.g, 7 * cam.k, [[0, 'rgba(255,165,70,' + a(0.42) + ')'], [0.3, 'rgba(255,150,60,' + a(0.26) + ')'], [0.6, 'rgba(255,140,50,' + a(0.1) + ')'], [0.85, 'rgba(255,130,45,' + a(0.03) + ')'], [1, 'rgba(255,130,45,0)']]);
      var h = m.head, g = m.g, rw = 3.2 * cam.k, gr = ctx.createLinearGradient(h[0], h[1], g[0], g[1]);
      gr.addColorStop(0, 'rgba(255,220,165,' + a(0.28) + ')'); gr.addColorStop(1, 'rgba(255,220,165,0)');
      ctx.fillStyle = gr;
      ctx.beginPath(); ctx.moveTo(h[0] - 0.25 * cam.k, h[1]); ctx.lineTo(h[0] + 0.25 * cam.k, h[1]);
      ctx.lineTo(g[0] + rw, g[1]); ctx.lineTo(g[0] - rw, g[1]); ctx.closePath(); ctx.fill();
      pool(ctx, h, 1.3 * cam.k, [[0, 'rgba(255,230,190,' + a(0.7) + ')'], [0.4, 'rgba(255,210,150,' + a(0.25) + ')'], [1, 'rgba(255,200,140,0)']]);
    });
    pool(ctx, P(fx, (GATE.y0 + GATE.y1) / 2, 1.2), 9 * cam.k, [[0, 'rgba(255,170,80,' + a(0.5) + ')'], [1, 'rgba(255,170,80,0)']]);
    pool(ctx, P(es.x + es.w / 2, es.y + es.d, es.z + es.h / 2), 4 * cam.k, [[0, 'rgba(255,60,60,' + a(0.7) + ')'], [1, 'rgba(255,60,60,0)']]);
    ctx.globalCompositeOperation = 'source-over';
  }
  // เจ้าหน้าที่นั่งประจำจุด: แสดงเมื่อเวลาที่เลือกอยู่ในช่วงเปิดบริการของจุด on (มีผู้ป่วยคนแรกเข้า → คนสุดท้ายออก)
  function staffOn(id) {
    var a = areas.find(function (q) { return q.id === id; });
    return !!(a && a.open && state.T >= a.open[0] && state.T <= a.open[1]);
  }
  function drawStaff(x, y) {
    if (cam.k * 1.1 < 5) return;
    var b = P(x, y, 0);
    drawChibi(b[0], b[1], cam.k * 1.1, { shirt: '#38bdf8', pants: '#1e3a8a', sit: true, walk: null });
  }
  var DESK = ['#e7d3b1', '#c9ad83', '#b89a6f'], DARK = ['#334155', '#1e293b', '#0f172a'];
  // โต๊ะคอม → ชิ้นส่วน { k ความลึก, draw } แยกกัน: เจ้าหน้าที่ · โต๊ะ (+จอ CPU คีย์บอร์ด) · เก้าอี้ผู้ป่วย
  // ปกติ: โต๊ะลึก 1 ตามแกน x กว้าง 2.2 ตามแกน y · เจ้าหน้าที่ฝั่ง −x · ผู้ป่วยฝั่ง +x
  // dir 'y'  = โต๊ะยาวตามแกน x · ผู้ป่วยฝั่ง −y · เจ้าหน้าที่ฝั่ง +y
  // dir 'y-' = โต๊ะยาวตามแกน x ชิดผนังหลัง · เจ้าหน้าที่ฝั่ง −y · ผู้ป่วยฝั่ง +y หันหน้าเข้าโต๊ะ
  function propParts(o) {
    var x = o.x, y = o.y, kb = ['#64748b', '#475569', '#334155'];
    var staff = function (sx, sy) { return { k: sx + sy, draw: function () { if (staffOn(o.on)) drawStaff(sx, sy); } }; };
    var chair = function (cx, cy, f) { return { k: cx + cy - 0.2, draw: function () { drawChair(cx, cy, f); } }; };
    if (o.t === 'chair') return [chair(x, y, o.face)];
    if (o.dir === 'y' || o.dir === 'y-') {
      var back = o.dir === 'y-', my = back ? y + 0.15 : y + 0.35, ky = back ? y + 0.1 : y + 0.6;
      return [
        chair(x, back ? y + 1.6 : y - 0.6, back ? 'y-' : null),
        { k: x + y + 0.5, draw: function () {
          box(x - 1.1, y, 0, 2.2, 1.0, 0.75, DESK[0], DESK[1], DESK[2]);
          box(x - 0.55, my, 0.75, 1.1, 0.08, 0.6, DARK[0], DARK[1], DARK[2]);
          box(x + 0.6, y + 0.4, 0.75, 0.25, 0.25, 0.35, DARK[0], DARK[1], DARK[2]);
          box(x - 0.4, back ? y + 0.6 : ky, 0.75, 0.8, 0.3, 0.03, kb[0], kb[1], kb[2]);
        } },
        staff(x, back ? y - 0.7 : y + 1.7)
      ];
    }
    return [
      staff(x - 0.7, y),
      { k: x + 0.5 + y, draw: function () {
        box(x, y - 1.1, 0, 1.0, 2.2, 0.75, DESK[0], DESK[1], DESK[2]);
        box(x + 0.55, y - 0.55, 0.75, 0.08, 1.1, 0.6, DARK[0], DARK[1], DARK[2]);      // จอหันหาเจ้าหน้าที่
        box(x + 0.6, y + 0.15, 0.75, 0.25, 0.25, 0.35, DARK[0], DARK[1], DARK[2]);     // CPU
        box(x + 0.1, y - 0.4, 0.75, 0.3, 0.8, 0.03, kb[0], kb[1], kb[2]);              // คีย์บอร์ด
      } },
      chair(x + 1.6, y, 'x-')
    ];
  }
  // ป้ายชื่ออาคารบนเสา หน้าทางเข้า
  function drawSign(text, x, y, bg) {
    var t = P(x, y, 2.4), g = P(x, y, 0);
    ctx.strokeStyle = '#94a3b8'; ctx.lineWidth = Math.max(1.5, cam.k * 0.07);
    ctx.beginPath(); ctx.moveTo(g[0], g[1]); ctx.lineTo(t[0], t[1]); ctx.stroke();
    var f = Math.round(Math.max(10, Math.min(13, cam.k * 0.75)));
    ctx.font = '500 ' + f + 'px Kanit, sans-serif';
    var w = ctx.measureText(text).width + 14, h = f + 9;
    ctx.fillStyle = 'rgba(15,42,92,.12)'; roundRect(t[0] - w / 2 + 1, t[1] - h + 2, w, h, 5); ctx.fill();
    ctx.fillStyle = bg || 'rgba(31,157,106,.92)'; roundRect(t[0] - w / 2, t[1] - h, w, h, 5); ctx.fill();
    ctx.fillStyle = '#ffffff'; ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
    ctx.fillText(text, t[0], t[1] - h / 2 + 1);
    ctx.textAlign = 'left';
  }

  // วาดลงบนผนังด้าน +x (หันหาถนน) ที่มุมบนซ้าย (x, y, z): u = ไปทาง −y (ซ้ายไปขวาบนจอ), v = ลงล่าง · หน่วย 1/20 เมตร
  function onFaceX(x, y, z, fn) {
    var o = P(x, y, z), s = cam.k / 20;
    ctx.save();
    ctx.setTransform(DPR * C30 * s, -DPR * S30 * s, 0, DPR * ZK * s, DPR * o[0], DPR * o[1]);
    fn();
    ctx.restore();
  }
  // ป้ายทางเข้า รพ. ตามภาพถ่าย: กำแพงป้ายสีครีมชื่อ รพ. (สูง 2.4 ม.) · เสาคู่ + จอภาพกรอบทองด้านบน (ยอดสูง ~9 ม.) · พุ่มไม้ด้านหน้า
  function drawGate() {
    var g = GATE, L = g.y1 - g.y0, T = 1.2, fx = g.x + T, wh = 2.4, sz = 3.6, sh = 5.2;
    var POST = ['#6b7280', '#4b5563', '#374151'];
    box(g.x + 0.45, g.y0 + 2, wh, 0.35, 0.35, sz - wh, POST[0], POST[1], POST[2]);
    box(g.x + 0.45, g.y1 - 2.35, wh, 0.35, 0.35, sz - wh, POST[0], POST[1], POST[2]);
    box(g.x, g.y0, 0, T, L, wh, '#f3e9d2', '#e2d2ac', '#efe3c4');
    box(g.x + 0.35, g.y0 + 1, sz, 0.5, L - 2, sh, '#1f2937', '#111827', '#0b0f19');
    if (cam.k >= 4) {
      onFaceX(fx, g.y1, wh, function () {
        ctx.font = '600 28px Kanit, sans-serif'; ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
        ctx.fillStyle = '#5b4a2e'; ctx.fillText('โรงพยาบาลบ้านบึง', L * 10, 20);
      });
      onFaceX(g.x + 0.85, g.y1 - 1, sz + sh, function () {
        var w = (L - 2) * 20, h = sh * 20;
        ctx.strokeStyle = '#c9a227'; ctx.lineWidth = 5; ctx.strokeRect(6, 6, w - 12, h - 12);
        ctx.strokeStyle = 'rgba(201,162,39,.5)'; ctx.lineWidth = 1.5; ctx.strokeRect(14, 14, w - 28, h - 28);
        var gr = ctx.createRadialGradient(w / 2, h / 2, 3, w / 2, h / 2, 38);
        gr.addColorStop(0, '#e0f2fe'); gr.addColorStop(1, '#5aa5c0');
        ctx.fillStyle = gr; ctx.beginPath(); ctx.ellipse(w / 2, h / 2, 27, 35, 0, 0, 7); ctx.fill();
      });
    }
    for (var y = g.y0 + 0.4; y < g.y1 - 1.4; y += 1.8) box(fx + 0.15, y, 0, 1.1, 1.6, 0.5, '#4ade80', '#16a34a', '#15803d');
  }

  // หุ่นแบบ "หมุด" (peg) ตามภาพตัวอย่าง: ตัวทรงแคปซูลสีเสื้อ ฐานสีกางเกง หัวกลมมีไฮไลต์ ไม่มีหน้า
  // วาดด้วย path ธรรมดาไม่กี่ชิ้น ไม่มี gradient — เบาเครื่องแม้มีหลายร้อยตัว
  // o: shirt, pants, sit, walk (เฟสการเดิน หรือ null)
  var SKIN = '#f2c7a0', INK = 'rgba(30,41,59,.45)';
  function capsule(x, top, bottom, w) {   // ด้านบนโค้งครึ่งวงกลม ด้านล่างมนเล็กน้อย
    var r = w / 2, rb = Math.min(w * 0.18, (bottom - top) / 3);
    ctx.beginPath();
    ctx.moveTo(x - r, top + r);
    ctx.arc(x, top + r, r, Math.PI, 0);
    ctx.lineTo(x + r, bottom - rb);
    ctx.quadraticCurveTo(x + r, bottom, x + r - rb, bottom);
    ctx.lineTo(x - r + rb, bottom);
    ctx.quadraticCurveTo(x - r, bottom, x - r, bottom - rb);
    ctx.closePath();
  }
  function drawChibi(x, by, k, o) {
    var z0 = o.sit ? 0.42 : 0;
    var walking = o.walk !== null;
    var bob = walking ? Math.abs(Math.sin(o.walk)) * 0.05 * k : 0;           // เด้งขึ้นลงตอนเดิน
    var sway = walking ? Math.sin(o.walk) * 0.025 * k : 0;                     // โยกซ้ายขวาเล็กน้อย
    var Y = function (z) { return by - bob - (z0 * cam.k + (z - z0) * k) * ZK; };
    var bw = 0.36 * k, top = o.sit ? 1.0 : 1.0, lw = Math.max(1, 0.03 * k);
    var bTop = Y(top), bBot = o.sit ? Y(z0) : by - bob;
    ctx.lineWidth = lw; ctx.strokeStyle = INK;
    // ฐาน/ขา (สีกางเกง = เพศ) แล้วตัวสีเสื้อ (= ความเร่งด่วน) ทับด้านบน
    ctx.fillStyle = o.pants;
    capsule(x, bTop, bBot, bw); ctx.fill(); ctx.stroke();
    var shirtBot = o.sit ? Y(z0 + 0.12) : Y(0.32);
    ctx.fillStyle = o.shirt;
    capsule(x + sway * 0.4, bTop, shirtBot, bw); ctx.fill(); ctx.stroke();
    // แสงสะท้อนด้านซ้าย ให้ดูเป็นทรงกลม 3 มิติ
    ctx.fillStyle = 'rgba(255,255,255,.32)';
    roundRect(x - bw * 0.36 + sway * 0.4, bTop + bw * 0.35, bw * 0.16, (shirtBot - bTop) - bw * 0.55, bw * 0.08); ctx.fill();
    // หัวกลม
    var hr = 0.19 * k, hx = x + sway, hy = bTop - hr * 0.72;
    ctx.fillStyle = SKIN;
    ctx.beginPath(); ctx.arc(hx, hy, hr, 0, 7); ctx.fill(); ctx.stroke();
    ctx.fillStyle = 'rgba(255,255,255,.55)';
    ctx.beginPath(); ctx.arc(hx - hr * 0.35, hy - hr * 0.38, hr * 0.28, 0, 7); ctx.fill();
    return { hy: hy, hr: hr };
  }

  function drawPerson(p) {
    var k = cam.k * 1.25, base = P(p.x, p.y, 0);
    var shirt = SHIRT[p.u] || SHIRT[5], pants = PANTS[p.g] || PANTS[0];
    var x = base[0];
    ctx.globalAlpha = p.a;
    ctx.fillStyle = 'rgba(15,23,42,.2)';
    ctx.beginPath(); ctx.ellipse(x, base[1], 0.3 * k, 0.15 * k, 0, 0, 7); ctx.fill();
    if (k < 5) {   // ซูมออกไกล: จุดกลมสีเสื้อ ขอบสีกางเกง
      ctx.fillStyle = shirt; ctx.strokeStyle = pants; ctx.lineWidth = 1.5;
      ctx.beginPath(); ctx.arc(x, base[1] - 0.7 * k * ZK, Math.max(2, 0.36 * k), 0, 7); ctx.fill(); ctx.stroke();
      ctx.globalAlpha = 1; return;
    }
    var h = drawChibi(x, base[1], k, {
      shirt: shirt, pants: pants, sit: p.sit && !p.walking,
      walk: p.walking ? performance.now() / 95 + p.ph : null
    });
    var hy = h.hy, hr = h.hr;
    ctx.globalAlpha = 1;
    var ht = p.heart ? performance.now() - p.heart : 1e9;
    if (ht < 2500 && cam.k >= 4) {
      var f = ht / 2500;
      ctx.globalAlpha = Math.min(1, (1 - f) * 1.6);
      ctx.font = Math.round(0.6 * k) + 'px "Segoe UI Emoji","Apple Color Emoji","Noto Color Emoji",sans-serif';
      ctx.textAlign = 'center'; ctx.textBaseline = 'bottom';
      ctx.fillText('❤️', x, hy - hr - 0.05 * k - f * 0.9 * k);
      ctx.textAlign = 'left'; ctx.globalAlpha = 1;
      return;
    }
    var mood = areas[p.area] && isWait(areas[p.area]) ? MOOD[p.lv] : null;
    if (mood && cam.k >= 6 && !p.leaving) {
      var bob = Math.sin(performance.now() / 260 + p.x * 3) * 0.06 * k;
      ctx.font = Math.round((p.lv === 'high' ? 0.62 : 0.5) * k) + 'px "Segoe UI Emoji","Apple Color Emoji","Noto Color Emoji",sans-serif';
      ctx.textAlign = 'center'; ctx.textBaseline = 'bottom';
      ctx.fillText(mood, x, hy - hr - 0.05 * k + bob);
      ctx.textAlign = 'left';
    }
  }

  // แพทย์ในห้องตรวจ: หุ่นแบบเดียวกัน เสื้อกาวน์ขาว ฐานเขียวเข้ม นั่งหลังโต๊ะ
  function drawDoctor(a) {
    var b = P(a.x + a.w / 2, a.y + a.d - 0.8, 0), k = cam.k * 1.1;
    if (k < 5) return;
    drawChibi(b[0], b[1], k, { shirt: '#ffffff', pants: '#0f766e', sit: true, walk: null });
  }

  // พื้นแต่ละจุดผสมสีความหนาแน่น (เขียว/เหลือง/แดง) ให้เห็นความแออัดจากระยะไกล
  function tint(a) {
    if (!a.n || a.nowait || a.L.room || a.L.queue) return null;
    var c = DENS[a.dl].c;
    return c + (a.dl === 'low' ? '30' : a.dl === 'mid' ? '4a' : '5c');
  }

  function draw() {
    ctx.setTransform(DPR, 0, 0, DPR, 0, 0);
    ctx.clearRect(0, 0, W, H);
    ctx.fillStyle = '#e9efeb'; ctx.fillRect(0, 0, W, H);

    var ROAD_C = '#b9c3cd', z0 = function (p) { return [p[0], p[1], 0]; };
    ROADS.forEach(function (r) { rect(r[0], r[1], r[2], r[3], 0, ROAD_C); });
    poly(RING.map(z0), ROAD_C);
    poly(ISLAND.map(z0), '#cfe6d3', '#9ec9a8', 1);
    // เส้นประกลางถนนทุกเส้น (ความยาวขีดตามระยะซูม)
    ctx.strokeStyle = 'rgba(255,255,255,.95)'; ctx.lineWidth = Math.max(1, cam.k * 0.16);
    ctx.setLineDash([cam.k * 1.5, cam.k * 1.5]);
    LANES.forEach(function (ln) {
      ctx.beginPath();
      ln.forEach(function (p, i) { var s = P(p[0], p[1], 0.01); if (i) ctx.lineTo(s[0], s[1]); else ctx.moveTo(s[0], s[1]); });
      ctx.stroke();
    });
    ctx.setLineDash([]);
    var linkFloor = [];
    LINKS.forEach(function (l) { linkFloor = linkFloor.concat(l.rs); });
    WALKS.concat(linkFloor).forEach(function (w) { rect(w[0], w[1], w[2], w[3], 0, '#d3dae1'); });
    drawShadows(state.T);
    SITE.forEach(function (s) { prism(s.pts, s.h, s.c); });
    Object.keys(BLD).forEach(function (id) {
      var b = BLD[id].r;
      box(b[0], b[1], -0.6, b[2], b[3], 0.6, '#e6ebef', '#a9b7c3', '#93a3b1');
      rect(b[0], b[1], b[2], b[3], 0.001, '#e4e9ed');
    });
    ZONES.forEach(function (z) {
      var r = z.r, c = [P(r[0], r[1], 0.005), P(r[0] + r[2], r[1], 0.005), P(r[0] + r[2], r[1] + r[3], 0.005), P(r[0], r[1] + r[3], 0.005)];
      ctx.fillStyle = z.c + '1c';
      ctx.beginPath(); ctx.moveTo(c[0][0], c[0][1]); for (var zi = 1; zi < 4; zi++) ctx.lineTo(c[zi][0], c[zi][1]);
      ctx.closePath(); ctx.fill();
    });
    // ทางเดินในอาคาร: พื้นกระเบื้องอ่อน + ขอบ ให้เห็นเส้นทางเดินชัด
    AISLES.forEach(function (r) { rect(r[0], r[1], r[2], r[3], 0.0015, '#f6f3ec', 'rgba(148,163,184,.7)', Math.max(1, cam.k * 0.05)); });
    DECOR.forEach(function (o) { rect(o.r[0], o.r[1], o.r[2], o.r[3], 0.002, FLOOR[o.kind], 'rgba(15,42,92,.28)'); });
    areas.forEach(function (a) {
      rect(a.x, a.y, a.w, a.d, 0.002, FLOOR[a.L.kind] || '#e8edf2', 'rgba(15,42,92,.28)');
      var t = tint(a);
      if (t) rect(a.x, a.y, a.w, a.d, 0.003, t);
    });
    drawWallAO();

    var dyn = [];
    people.forEach(function (p) { if (p.vis) dyn.push({ k: p.x + p.y, p: p }); });
    areas.forEach(function (a) { if (a.L.room && a.n) dyn.push({ k: a.x + a.w / 2 + a.y + a.d - 0.8, doc: a }); });
    dyn.sort(function (a, b) { return a.k - b.k; });
    for (var si = 0, di = 0; si < statics.length || di < dyn.length;) {
      if (di < dyn.length && (si >= statics.length || dyn[di].k < statics[si].k)) {
        var it = dyn[di++];
        if (it.p) drawPerson(it.p); else drawDoctor(it.doc);
      } else statics[si++].draw();
    }
    drawLighting(state.T);
    // ป้ายข้อความเล็กและโปร่ง ให้เห็นผังชัด
    var fs = Math.round(Math.max(11, Math.min(14, cam.k * 0.85)));
    ctx.textAlign = 'center'; ctx.textBaseline = 'middle'; ctx.lineJoin = 'round';
    if (cam.k >= 6) ZONES.forEach(function (z) {
      var p = P(z.r[0] + 1.2, z.r[1] + z.r[3] / 2, 0.01);
      ctx.font = '500 ' + (fs - 1) + 'px Kanit, sans-serif';
      ctx.globalAlpha = 0.7;
      ctx.lineWidth = 3; ctx.strokeStyle = '#ffffff'; ctx.strokeText(z.name, p[0], p[1]);
      ctx.fillStyle = z.c; ctx.fillText(z.name, p[0], p[1]);
      ctx.globalAlpha = 1;
    });
    ctx.textAlign = 'left';
    if (cam.k >= 9) DECOR.concat(PROPS.map(function (o) { return { name: o.name, r: [o.x, o.y + 1.2, 0, 0] }; })).forEach(function (o) {
      if (!o.name) return;
      var p = P(o.r[0] + o.r[2] * 0.5, o.r[1] + o.r[3] * 0.5, 0.2);
      ctx.font = '500 ' + (fs - 1) + 'px Kanit, sans-serif';
      ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
      ctx.fillStyle = 'rgba(71,85,105,.45)'; ctx.fillText(o.name, p[0], p[1]);
      ctx.textAlign = 'left';
    });
    drawLabels(fs);
    if (hover) drawRing(hover, '#0f2a5c');
    if (state.track) {
      var tp = people.get(state.track);
      if (tp && tp.vis) drawRing(tp, '#f59e0b', 1.3 + 0.2 * Math.sin(performance.now() / 300));
    }
    if (state.find) {
      var pulse = (performance.now() - state.findAt) / 600;
      people.forEach(function (p) {
        if (state.find.indexOf(p.id) >= 0 && p.vis) drawRing(p, '#e5484d', 1.2 + 0.25 * Math.sin(pulse * Math.PI));
      });
    }
  }

  function drawLabels(fs) {
    var boxes = [];
    areas.forEach(function (a) {
      if (a.L.room && cam.k < 16) return;            // ห้องตรวจ: ป้ายเมื่อซูมเข้า (ยอดรวมอยู่ที่ ห้องตรวจแพทย์)
      if (W < 900 && cam.k < 10 && !(a.n + roomsIn(a))) return;   // จอแคบ: ป้ายเฉพาะจุดที่มีคน จนกว่าจะซูมเข้า
      var b = measureLabel(a, fs);
      b.a = a;
      var p = P(a.cx, a.cy, 1.2);   // ปักที่กึ่งกลางจุด ให้ป้ายชี้ตรงตำแหน่ง (ชนกันจะดันขึ้นพร้อมเส้นโยง)
      b.ax = p[0]; b.ay = p[1];
      b.x = Math.max(6, Math.min(W - b.w - 6, p[0] - b.w / 2)); b.y = p[1] - b.h - 6;   // ไม่ล้นขอบจอ
      boxes.push(b);
    });
    boxes.sort(function (p, q) { return q.ay - p.ay; });
    var placed = [];
    boxes.forEach(function (b) {
      for (var guard = 0; guard < 12; guard++) {
        var hit = null;
        for (var i = 0; i < placed.length; i++) {
          var o = placed[i];
          if (b.x < o.x + o.w + 4 && b.x + b.w + 4 > o.x && b.y < o.y + o.h + 3 && b.y + b.h + 3 > o.y) { hit = o; break; }
        }
        if (!hit) break;
        b.y = hit.y - b.h - 4;
      }
      if (b.y >= labelTop) placed.push(b);   // จอแคบ: ป้ายที่ดันขึ้นจนทับการ์ดตัวเลข → ซ่อนไว้ ซูมเข้าเพื่อดู
    });
    placed.forEach(function (b) {
      ctx.strokeStyle = 'rgba(15,42,92,.16)'; ctx.lineWidth = 1;
      ctx.beginPath(); ctx.moveTo(b.ax, b.y + b.h); ctx.lineTo(b.ax, b.ay); ctx.stroke();
      renderLabel(b, fs);
    });
  }

  function labelName(a) { return a.L.short && cam.k < 24 ? a.L.short : a.name; }
  function measureLabel(a, fs) {
    var served = roomsServed(a);
    var name = labelName(a) + (served && W >= 900 ? ' · ตรวจแล้ว ' + served : '');   // จอแคบ: ตัดส่วนท้ายให้ป้ายสั้น
    var n = a.n + roomsIn(a);
    var cnt = n + ' คน';
    ctx.font = '500 ' + fs + 'px Kanit, sans-serif';   // น้ำหนักเดียวกับตอนวาด (renderLabel) ให้ความกว้างพอดี
    var nw = ctx.measureText(name).width;
    ctx.font = '600 ' + (fs - 2) + 'px Kanit, sans-serif';
    var cw = ctx.measureText(cnt).width + 12;
    var pad = 8, h = fs + 10;
    return { name: name, cnt: cnt, n: n, nw: nw, cw: cw, pad: pad, h: h, w: nw + pad * 2 + cw + 5 };
  }
  function renderLabel(b, fs) {
    var x0 = b.x, y0 = b.y, h = b.h, w = b.w;
    ctx.fillStyle = 'rgba(15,42,92,.12)'; roundRect(x0 + 1, y0 + 2, w, h, 7); ctx.fill();
    ctx.fillStyle = 'rgba(255,255,255,.94)'; roundRect(x0, y0, w, h, 7); ctx.fill();
    ctx.textBaseline = 'middle';
    ctx.font = '500 ' + fs + 'px Kanit, sans-serif';
    ctx.fillStyle = '#1f2937'; ctx.fillText(b.name, x0 + b.pad, y0 + h / 2 + 1);
    var bx = x0 + b.pad + b.nw + 5;
    var lv = b.a.nowait || !b.n ? 'ok' : b.a.lv;
    ctx.fillStyle = b.n ? (lv === 'ok' ? '#dcf5e7' : lv === 'mid' ? '#fdefd3' : '#fde1e1') : '#e9f6ef';
    roundRect(bx, y0 + 4, b.cw, h - 8, (h - 8) / 2); ctx.fill();
    ctx.font = '600 ' + (fs - 2) + 'px Kanit, sans-serif';
    ctx.fillStyle = lv === 'ok' ? '#15803d' : lv === 'mid' ? '#b45309' : '#b42318';
    ctx.fillText(b.cnt, bx + 6, y0 + h / 2 + 1);
  }
  // ห้องตรวจที่ใช้จุดรอ w (หน้าห้องตรวจ walk-in / ผู้ป่วยนัด / คลินิกเด็ก)
  function roomsServed(w) { var s = 0; areas.forEach(function (a) { if (a.L.wait === w.id) s += a.served; }); return s; }
  function roomsIn(w) { var s = 0; areas.forEach(function (a) { if (a.L.wait === w.id) s += a.n; }); return s; }

  // ------------------------------------------------------------------ ชี้ดูเวลารอรายคน (ไม่มีข้อมูลระบุตัวตน)
  function figureCenter(p) {
    var b = P(p.x, p.y, 0);
    return [b[0], b[1] - 0.85 * cam.k * 1.25 * ZK];
  }
  function pick(cx, cy) {
    var best = null, bd = Math.max(10, 0.5 * cam.k * 1.25);
    people.forEach(function (p) {
      if (!p.vis || p.area < 0 || p.leaving) return;
      var c = figureCenter(p), d = Math.hypot(c[0] - cx, c[1] - cy);
      if (d < bd) { bd = d; best = p; }
    });
    return best;
  }
  function drawRing(p, color, scale) {
    if (!p.vis || p.area < 0) return;
    var b = P(p.x, p.y, 0), k = cam.k * 1.25 * (scale || 1);
    ctx.strokeStyle = color; ctx.lineWidth = 2.5;
    ctx.beginPath(); ctx.ellipse(b[0], b[1], 0.5 * k, 0.25 * k, 0, 0, 7); ctx.stroke();
  }

  var tip = $('tip');
  function showTip(p, cx, cy) {
    hover = p;
    if (!p) { tip.hidden = true; return; }
    tip.hidden = false;
    tip.dataset.x = cx; tip.dataset.y = cy;
    updateTip();
  }
  function updateTip() {
    var p = hover;
    if (!p) return;
    if (!p.vis || p.area < 0) { tip.hidden = true; hover = null; return; }
    var a = areas[p.area], w = state.T - p.enter, tot = state.T - p.total, lv = level(w, a.target);
    var found = state.find && state.find.indexOf(p.id) >= 0 ? '<div class="found">ผู้ป่วยที่ค้นหา</div>' : '';
    tip.innerHTML = found + whoHtml(p.id) + '<b>' + a.name + '</b>' +
      (a.nowait ? '<div>อยู่ห้องฉุกเฉิน · ไม่คิดเวลารอคิว</div>'
        : a.L.room ? '<div>กำลังพบแพทย์ ' + mins(w) + '</div>'
        : '<div class="w ' + lv + '">' + (MOOD[lv] ? MOOD[lv] + ' ' : '') + 'รอที่จุดนี้ ' + mins(w) + '</div>' +
          '<div>เป้าหมาย ≤ ' + a.target + ' นาที</div>') +
      '<div>อยู่ใน รพ. ' + mins(tot) + '</div>' +
      '<div class="u"><i style="background:' + (SHIRT[p.u] || SHIRT[5]) + '"></i>' + (URG_NAME[p.u] || 'ทั่วไป') +
      ' · ' + (p.g === 1 ? 'ชาย' : p.g === 2 ? 'หญิง' : 'ไม่ระบุเพศ') + ' · ' + (p.ap ? 'ผู้ป่วยนัด' : 'Walk-in') + '</div>' +
      '<div class="muted">คลิกเพื่อติดตามผู้ป่วยคนนี้</div>';
    var cx = +tip.dataset.x, cy = +tip.dataset.y, tw = tip.offsetWidth, th = tip.offsetHeight;
    var x = cx + 16, y = cy + 16;
    if (x + tw > W - 8) x = cx - tw - 12;
    if (y + th > H - 8) y = cy - th - 12;
    tip.style.left = Math.max(8, x) + 'px'; tip.style.top = Math.max(8, y) + 'px';
  }

  // ------------------------------------------------------------------ ชื่อผู้ป่วย (ต้อง login) และติดตามรายคน
  var names = null, namesAt = 0, visitById = {};
  function loadNames() {
    namesAt = Date.now();
    if (!CFG.loggedIn || !CFG.people) return;
    var d = state.date;
    fetch(CFG.people + '?d=' + encodeURIComponent(d), { cache: 'no-store' })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (j) { if (j && j.people && d === state.date) names = j.people; })
      .catch(function () {});
  }
  function whoHtml(id) {
    var n = names && names[id];
    return n ? '<div class="who"><b>' + esc(n[1] || '-') + '</b> <span>HN ' + esc(n[0]) + '</span></div>' : '';
  }
  function startTrack(id) {
    state.track = id; state.follow = true;
    var p = people.get(id);
    if (p && p.vis && cam.k < 18) { focusXY(p.x, p.y, 22); state.followAt = performance.now() + 500; }
    renderTrack();
  }
  function stopTrack() { state.track = null; $('track').hidden = true; }
  function followCam(dt) {
    if (!state.track || !state.follow || performance.now() < state.followAt) return;
    var p = people.get(state.track);
    if (!p || !p.vis) return;
    // จอเล็ก: การ์ดติดตามอยู่ครึ่งล่าง — ให้ผู้ป่วยอยู่ช่วงบนของจอ
    var cx = W < 900 ? W / 2 : (W - 400) / 2 + 40, cy = W < 900 ? H * 0.32 : H / 2 + 20, f = Math.min(1, dt * 4);
    cam.ox += (cx - (p.x - p.y) * C30 * cam.k - cam.ox) * f;
    cam.oy += (cy - (p.x + p.y) * S30 * cam.k - cam.oy) * f;
  }
  function renderTrack() {
    var v = state.track && visitById[state.track];
    if (!v) { $('track').hidden = true; return; }
    var T = state.T, seg = whereAt(v, T), n = names && names[v.i];
    $('trName').textContent = n ? n[1] : 'ผู้ป่วยที่เลือก';
    $('trHn').textContent = n ? 'HN ' + n[0] : (CFG.loggedIn ? '' : 'เข้าสู่ระบบเพื่อดูชื่อ / HN');
    var st = T < v.s[0][0] ? 'ยังไม่มาถึง (มา ' + hhmm(v.s[0][0]) + ' น.)'
      : T >= v.e ? 'ออกจาก รพ. แล้ว ' + hhmm(v.e) + ' น.'
      : 'อยู่ที่ <b>' + esc(areas[seg[1]].name) + '</b> ' + mins(T - seg[0]);
    var rows = v.s.map(function (s, i) {
      var cls = s[0] > T ? 'next' : s === seg ? 'now' : 'done';
      var to = i + 1 < v.s.length ? v.s[i + 1][0] : v.e;
      return '<li class="' + cls + '"><time>' + hhmm(s[0]) + '</time>' + esc(areas[s[1]].name) +
        (cls === 'done' ? ' <small>' + Math.round(to - s[0]) + ' น.</small>' : '') + '</li>';
    }).join('') + (T >= v.e ? '<li class="done"><time>' + hhmm(v.e) + '</time>ออกจาก รพ.</li>' : '');
    $('trBody').innerHTML = '<div class="trst">' + st + '</div><div class="muted">' + (v.a ? 'ผู้ป่วยนัด' : 'Walk-in') +
      ' · ' + (URG_NAME[v.u] || 'ทั่วไป') + '</div><ol class="route">' + rows + '</ol>';
    $('trFollow').hidden = state.follow;
    $('track').hidden = false;
  }
  $('trClose').onclick = stopTrack;
  $('trFollow').onclick = function () { state.follow = true; state.followAt = 0; renderTrack(); };

  // ------------------------------------------------------------------ กล้อง
  // มุมมองเริ่มต้น: อาคารที่มีจุดบริการทั้งหมด + ป้ายทางเข้า
  function bounds() {
    var xs = [], ys = [];
    Object.keys(BLD).map(function (k) { return BLD[k].r; }).concat([[GATE.x, GATE.y0, 1.2, GATE.y1 - GATE.y0]]).forEach(function (b) {
      [[b[0], b[1]], [b[0] + b[2], b[1]], [b[0], b[1] + b[3]], [b[0] + b[2], b[1] + b[3]]].forEach(function (c) {
        xs.push((c[0] - c[1]) * C30); ys.push((c[0] + c[1]) * S30);
      });
    });
    return [Math.min.apply(0, xs), Math.min.apply(0, ys) - 3 * ZK, Math.max.apply(0, xs), Math.max.apply(0, ys)];
  }
  function fit() {
    zoomGoal = null;
    var mob = W < 900, b = bounds();
    // มือถือ/แท็บเล็ตแนวตั้ง: วางผังต่ำลงให้ป้ายจุดบริการที่ซ้อนขึ้นด้านบนไม่ทับการ์ดตัวเลข
    var padL = mob ? 10 : 80, padR = mob ? 10 : 400, padT = mob ? Math.min(320, H * 0.38) : 210, padB = mob ? 170 : 170;
    var aw = Math.max(100, W - padL - padR), ah = Math.max(100, H - padT - padB);
    cam.k = Math.max(2, Math.min(aw / (b[2] - b[0]), ah / (b[3] - b[1])));
    cam.ox = padL + (aw - (b[2] - b[0]) * cam.k) / 2 - b[0] * cam.k;
    cam.oy = padT + (ah - (b[3] - b[1]) * cam.k) / 2 - b[1] * cam.k;
  }
  function zoomAt(f, px, py) {   // ซูมทันที (pinch)
    var nk = Math.max(2, Math.min(60, cam.k * f));
    f = nk / cam.k;
    cam.ox = px - (px - cam.ox) * f;
    cam.oy = py - (py - cam.oy) * f;
    cam.k = nk;
  }
  // ล้อเมาส์/ปุ่ม +/−: ตั้งเป้าแล้วค่อย ๆ ซูมเข้าหาในแต่ละเฟรม (zoomStep) — หมุนล้อต่อเนื่องจะสะสมเป้า
  var zoomGoal = null;
  function zoomSmooth(f, px, py) {
    zoomGoal = { k: Math.max(2, Math.min(60, (zoomGoal ? zoomGoal.k : cam.k) * f)), px: px, py: py };
  }
  function zoomStep(dt) {
    if (!zoomGoal) return;
    var nk = cam.k + (zoomGoal.k - cam.k) * (1 - Math.exp(-dt * 12));
    if (Math.abs(zoomGoal.k - nk) < cam.k * 0.002) nk = zoomGoal.k;
    zoomAt(nk / cam.k, zoomGoal.px, zoomGoal.py);
    if (nk === zoomGoal.k) zoomGoal = null;
  }
  function tweenCam(to, ms) {
    zoomGoal = null;
    var from = { k: cam.k, ox: cam.ox, oy: cam.oy }, t0 = performance.now();
    (function anim() {
      var t = Math.min(1, (performance.now() - t0) / ms), e = 1 - Math.pow(1 - t, 3);   // ease-out cubic
      cam.k = from.k + (to.k - from.k) * e;
      cam.ox = from.ox + (to.ox - from.ox) * e;
      cam.oy = from.oy + (to.oy - from.oy) * e;
      if (t < 1) requestAnimationFrame(anim);
    })();
  }
  function focusXY(x, y, k) {
    var target = Math.max(cam.k, k || (W < 900 ? 15 : 22));
    var cx = W < 900 ? W / 2 : (W - 400) / 2 + 40, cy = H / 2 + 20;
    var sx = (x - y) * C30, sy = (x + y) * S30;
    tweenCam({ k: target, ox: cx - sx * target, oy: cy - sy * target }, 550);
  }
  var labelTop = -1e9;   // ขอบบนสุดที่ป้ายจุดบริการวางได้ (จอแคบ = ใต้การ์ดตัวเลข)
  function resize() {
    DPR = window.devicePixelRatio || 1;
    W = window.innerWidth; H = window.innerHeight;
    var kp = document.querySelector('.kpis');
    labelTop = W < 900 && kp ? kp.getBoundingClientRect().bottom + 6 : -1e9;
    canvas.width = W * DPR; canvas.height = H * DPR;
    layoutPlayer();
    zoomGoal = null;
    fit();
  }
  // ขนาดกราฟเวลา (ซ่อนอยู่ตอนข้อมูลสด = กว้าง 0) และระยะการ์ดล่าง (--pb) ให้แผงจุดบริการ/คำอธิบายสีลอยเหนือตัวเล่นพอดี
  function layoutPlayer() {
    var r = tlc.getBoundingClientRect();
    if (r.width) { tlc.width = r.width * DPR; tlc.height = r.height * DPR; }
    document.body.style.setProperty('--pb', (document.querySelector('.player').offsetHeight + 24) + 'px');
  }

  var ptrs = new Map(), pinch = null, downAt = null;
  canvas.addEventListener('pointerdown', function (e) {
    downAt = [e.clientX, e.clientY];
    canvas.setPointerCapture(e.pointerId);
    ptrs.set(e.pointerId, [e.clientX, e.clientY]);
    canvas.classList.add('drag');
  });
  canvas.addEventListener('pointermove', function (e) {
    if (!ptrs.has(e.pointerId)) {
      if (e.pointerType === 'mouse') showTip(pick(e.clientX, e.clientY), e.clientX, e.clientY);
      return;
    }
    var prev = ptrs.get(e.pointerId);
    ptrs.set(e.pointerId, [e.clientX, e.clientY]);
    if (ptrs.size === 2) {
      var pts = Array.from(ptrs.values());
      var dd = Math.hypot(pts[0][0] - pts[1][0], pts[0][1] - pts[1][1]);
      zoomGoal = null;
      if (pinch) zoomAt(dd / pinch, (pts[0][0] + pts[1][0]) / 2, (pts[0][1] + pts[1][1]) / 2);
      pinch = dd;
      return;
    }
    if (downAt && Math.hypot(e.clientX - downAt[0], e.clientY - downAt[1]) > 6) state.follow = false;   // ลากผังเอง = หยุดกล้องตาม
    cam.ox += e.clientX - prev[0]; cam.oy += e.clientY - prev[1];
  });
  function up(e) {
    if (downAt && e.type === 'pointerup' && ptrs.size === 1 &&
        Math.hypot(e.clientX - downAt[0], e.clientY - downAt[1]) < 6) {
      var hit = pick(e.clientX, e.clientY);
      showTip(hit, e.clientX, e.clientY);
      if (hit) startTrack(hit.id);
    }
    downAt = null;
    ptrs.delete(e.pointerId); pinch = null;
    if (!ptrs.size) canvas.classList.remove('drag');
  }
  canvas.addEventListener('pointerup', up);
  canvas.addEventListener('pointercancel', up);
  canvas.addEventListener('pointerleave', function (e) { if (e.pointerType === 'mouse' && !ptrs.size) showTip(null); });
  canvas.addEventListener('wheel', function (e) {
    e.preventDefault();
    zoomSmooth(e.deltaY < 0 ? 1.15 : 1 / 1.15, e.clientX, e.clientY);
  }, { passive: false });
  $('zIn').onclick = function () { zoomSmooth(1.35, W / 2, H / 2); };
  $('zOut').onclick = function () { zoomSmooth(1 / 1.35, W / 2, H / 2); };
  $('zFit').onclick = function () {
    var from = { k: cam.k, ox: cam.ox, oy: cam.oy };
    fit();
    var to = { k: cam.k, ox: cam.ox, oy: cam.oy };
    cam.k = from.k; cam.ox = from.ox; cam.oy = from.oy;
    tweenCam(to, 600);
  };
  // ซ่อน/แสดงเมนู (ปุ่มรูปตา หรือกด H) · จำค่าไว้ในเครื่องนี้
  function setUi(off) {
    document.body.classList.toggle('ui-off', off);
    var b = $('zUi');
    b.setAttribute('aria-pressed', off ? 'true' : 'false');
    b.title = off ? 'แสดงเมนู (H)' : 'ซ่อนเมนู (H)';
    b.setAttribute('aria-label', off ? 'แสดงเมนู' : 'ซ่อนเมนู');
    b.innerHTML = '<i class="fa-solid ' + (off ? 'fa-eye' : 'fa-eye-slash') + '"></i>';
    try { localStorage.setItem('opd-monitor-ui-off', off ? '1' : '0'); } catch (e) { /* โหมดส่วนตัว: ไม่จำก็ได้ */ }
  }
  $('zUi').onclick = function () { setUi(!document.body.classList.contains('ui-off')); };
  document.addEventListener('keydown', function (e) {
    var t = e.target.tagName;
    if ((e.key === 'h' || e.key === 'H') && t !== 'INPUT' && t !== 'TEXTAREA' && !e.ctrlKey && !e.metaKey && !e.altKey) setUi(!document.body.classList.contains('ui-off'));
  });
  try { if (localStorage.getItem('opd-monitor-ui-off') === '1') setUi(true); } catch (e) { /* ไม่มีที่เก็บ: เริ่มแบบแสดงเมนู */ }
  $('zFull').onclick = function () {
    if (document.fullscreenElement) document.exitFullscreen();
    else if (document.documentElement.requestFullscreen) document.documentElement.requestFullscreen();
  };

  // ------------------------------------------------------------------ ไทม์ไลน์
  var DAY = 24 * 60, TL0 = 0, TL1 = DAY;
  function buildServed() {
    areas.forEach(function (a) { a.entries = []; });
    data.visits.forEach(function (v) {
      if (!visible(v)) return;
      v.s.forEach(function (seg) { var a = areas[seg[1]]; if (a) a.entries.push(seg[0]); });
    });
    areas.forEach(function (a) { a.entries.sort(function (p, q) { return p - q; }); });
  }
  function servedAt(a, T) {
    var lo = 0, hi = a.entries.length;
    while (lo < hi) { var m = (lo + hi) >> 1; if (a.entries[m] <= T) lo = m + 1; else hi = m; }
    return lo;
  }
  function buildOcc() {
    buildServed();
    occ = new Float32Array(DAY + 1);
    data.visits.forEach(function (v) {
      if (!visible(v)) return;
      var a = Math.max(0, Math.floor(v.s[0][0])), b = Math.min(DAY, Math.ceil(v.e));
      for (var t = a; t < b; t++) occ[t]++;
    });
    var first = -1, last = -1;
    for (var t = 0; t <= DAY; t++) if (occ[t] >= 2) { if (first < 0) first = t; last = t; }
    TL0 = first < 0 ? 6 * 60 : Math.max(0, Math.min(6 * 60, Math.floor(first / 60) * 60));
    TL1 = last < 0 ? 20 * 60 : Math.min(24 * 60, Math.max(17 * 60, Math.ceil(last / 60) * 60));
  }
  function drawTimeline() {
    var w = tlc.width / DPR, h = tlc.height / DPR, c = tctx;
    c.setTransform(DPR, 0, 0, DPR, 0, 0);
    c.clearRect(0, 0, w, h);
    if (!occ) return;
    var axis = 6, ch = h - axis, mx = 1;
    for (var t = TL0; t <= TL1; t++) mx = Math.max(mx, occ[t]);
    var X = function (t) { return (t - TL0) / (TL1 - TL0) * w; };
    c.beginPath(); c.moveTo(0, ch);
    for (var t2 = TL0; t2 <= TL1; t2 += 2) c.lineTo(X(t2), ch - occ[t2] / mx * (ch - 4));
    c.lineTo(w, ch); c.closePath();
    var g = c.createLinearGradient(0, 0, 0, ch);
    g.addColorStop(0, 'rgba(25,135,84,.45)'); g.addColorStop(1, 'rgba(25,135,84,.06)');
    c.fillStyle = g; c.fill();
    c.strokeStyle = 'rgba(25,135,84,.7)'; c.lineWidth = 1.2; c.stroke();
    var xn = X(Math.min(state.T, TL1));
    c.fillStyle = '#e5ece8'; c.fillRect(0, ch + 1, w, 4);
    c.fillStyle = '#198754'; c.fillRect(0, ch + 1, xn, 4);
    c.beginPath(); c.arc(Math.max(7, Math.min(w - 7, xn)), ch + 3, 7, 0, 7); c.fill();
    c.fillStyle = '#fff'; c.beginPath(); c.arc(Math.max(7, Math.min(w - 7, xn)), ch + 3, 3, 0, 7); c.fill();
  }
  function scrub(e) {
    var r = tlc.getBoundingClientRect();
    var f = Math.max(0, Math.min(1, (e.clientX - r.left) / r.width));
    setLive(false);
    state.T = Math.min(TL0 + f * (TL1 - TL0), maxT());
  }
  var scrubbing = false;
  tlc.addEventListener('pointerdown', function (e) { scrubbing = true; tlc.setPointerCapture(e.pointerId); scrub(e); });
  tlc.addEventListener('pointermove', function (e) { if (scrubbing) scrub(e); });
  tlc.addEventListener('pointerup', function () { scrubbing = false; });

  // ------------------------------------------------------------------ ตัวควบคุม
  function nowMin() { var d = new Date(); return d.getHours() * 60 + d.getMinutes() + d.getSeconds() / 60; }
  function maxT() { return state.date === CFG.today ? nowMin() : 24 * 60 - 0.01; }
  function hhmm(t) {
    t = Math.max(0, Math.floor(t));
    return String(Math.floor(t / 60) % 24).padStart(2, '0') + ':' + String(t % 60).padStart(2, '0');
  }
  function mins(m) {
    m = Math.round(m);
    return m >= 60 ? Math.floor(m / 60) + ' ชม. ' + (m % 60) + ' นาที' : m + ' นาที';
  }
  function esc(s) { return String(s).replace(/[&<>"']/g, function (c) { return '&#' + c.charCodeAt(0) + ';'; }); }

  function setLive(on) {
    state.live = on && state.date === CFG.today;
    $('mode').textContent = state.live ? 'LIVE' : 'REPLAY';
    $('modeDot').classList.toggle('replay', !state.live);
    if (state.live) state.playing = true;
    // ข้อมูลสด: ซ่อนแถบเลื่อนเวลา (CSS body.is-live) แล้ววางผังใหม่ตามพื้นที่ที่ได้คืน
    if (document.body.classList.contains('is-live') !== state.live) {
      document.body.classList.toggle('is-live', state.live);
      layoutPlayer();
    }
    syncButtons();
  }
  function syncButtons() {
    var playing = state.playing && !state.live;
    $('btnPlay').innerHTML = '<i class="fa-solid fa-' + (playing ? 'pause' : 'play') + '"></i>';
    $('btnPlay').title = state.live ? 'เล่นย้อนหลังวันนี้ (แสดงแถบเลื่อนเวลา)' : 'เล่น / หยุด';
    document.querySelectorAll('#speeds button').forEach(function (b) {
      b.classList.toggle('on', !state.live && +b.dataset.s === state.speed);
    });
  }
  $('btnPlay').onclick = function () {
    if (state.live) { setLive(false); state.playing = true; state.T = TL0; }
    else state.playing = !state.playing;
    if (state.playing && state.T >= maxT() - 0.05) state.T = TL0;
    syncButtons();
  };
  document.querySelectorAll('#speeds button').forEach(function (b) {
    b.onclick = function () {
      state.speed = +b.dataset.s;
      if (state.live) { setLive(false); state.T = TL0; }
      state.playing = true;
      syncButtons();
    };
  });
  function setDate(v) {
    if (!v || v > CFG.today) return;
    state.date = v; state.dep = ''; state.find = null;
    stopTrack();
    $('date').value = v;
    try { history.replaceState(null, '', '?d=' + v); } catch (e) {}
    load(true);
  }
  $('date').onchange = function () { setDate(this.value); };

  // ------------------------------------------------------------------ แผงจุดบริการ
  var rowEls = [];
  function buildPanel() {
    var box = $('rows'); box.innerHTML = '';
    rowEls = [];
    var lastB = null;
    areas.forEach(function (a) {
      if (a.L.room) return;           // ห้องตรวจรวมอยู่ในแถวหน้าห้องตรวจของฝั่งนั้น
      if (a.L.b !== lastB && BLD[a.L.b]) {
        var h = document.createElement('div');
        h.className = 'bhead'; h.textContent = BLD[a.L.b].name;
        box.appendChild(h);
        lastB = a.L.b;
      }
      var el = document.createElement('button');
      el.type = 'button';
      el.className = 'arow';
      el.innerHTML = '<span class="n"></span><span class="c"></span><span class="v"></span><span class="trk"><i></i></span>';
      el.querySelector('.n').textContent = a.name;
      el.querySelector('.n').title = a.name;
      el.onclick = function () { focusXY(a.cx, a.cy); };
      var wrap = document.createElement('div');
      wrap.className = 'arow-wrap';
      var ib = document.createElement('button');
      ib.type = 'button';
      ib.className = 'ainfo';
      ib.setAttribute('aria-label', 'ที่มาของข้อมูล ' + a.name);
      ib.innerHTML = '<i class="fa-solid fa-circle-info" aria-hidden="true"></i>';
      ib.onclick = function () { areaInfo(a); };
      wrap.appendChild(el); wrap.appendChild(ib);
      box.appendChild(wrap);
      rowEls.push({ el: el, a: a });
    });
  }
  function updatePanel() {
    rowEls.forEach(function (r) {
      var a = r.a, el = r.el;
      // บรรทัดบน: ชื่อจุด + จำนวนคนตอนนี้ (ความหนาแน่นเทียบที่นั่งดูจากแถบสี) · บรรทัดล่าง: รายละเอียด (ไม่ตกบรรทัด)
      var c = a.n + ' คน', v;
      if (a.nowait) v = 'ตรวจตามความเร่งด่วน · วันนี้ ' + servedAt(a, state.T) + ' ราย';
      else {
        v = (a.L.queue ? 'ยืนต่อแถว · ' : '') + 'รอ ~' + Math.round(a.avg) + ' นาที';
        if (areas.some(function (x) { return x.L.wait === a.id; })) v += ' · พบแพทย์ ' + roomsIn(a) + ' · ตรวจแล้ว ' + roomsServed(a);
      }
      el.querySelector('.c').textContent = c;
      el.querySelector('.v').textContent = v;
      el.querySelector('.c').style.color = a.n && !a.nowait && a.lv !== 'ok' ? COL_TEXT[a.lv] : '';
      var bar = el.querySelector('i');
      bar.style.width = Math.min(100, a.dens * 100) + '%';
      bar.style.background = a.nowait ? '#94a3b8' : DENS[a.dl].c;
      el.title = a.nowait ? 'ห้องฉุกเฉิน — ไม่คิดเวลารอ' : a.L.queue ? 'ยืนต่อแถว ไม่มีที่นั่ง — คลิกเพื่อซูม' :
        'ความหนาแน่น ' + Math.round(a.dens * 100) + '% (' + DENS[a.dl].t + ') · รอค่ากลาง ' + Math.round(a.avg) +
        ' นาที (เป้าหมาย ≤ ' + a.target + ') — คลิกเพื่อซูม';
    });
    var s = state.stats || {};
    var remoteN = 0;
    (data.remote || []).forEach(function (t) { if (t <= state.T) remoteN++; });
    $('remote').textContent = data.admitted ? 'Admit ' + data.admitted + ' ราย (ทั้งวัน) — ไม่แสดงในผัง' : '';
    $('kIn').textContent = s.inside || 0;
    $('kWait').textContent = Math.round(s.wait || 0);
    var dl = densLevel(s.dens || 0);
    $('kDens').textContent = Math.round((s.dens || 0) * 100) + '%';
    $('kDensT').textContent = DENS[dl].t;
    $('kDensT').className = 'chip ' + dl;
    $('kLeft').textContent = s.left || 0;
    $('kPcu').textContent = remoteN;
    $('kQual').textContent = s.arrived ? Math.round(s.qual * 100 / s.arrived) + '%' : '–';
    $('kQualSub').textContent = s.arrived ? s.qual + ' จาก ' + s.arrived + ' ราย' : '';
    $('chipDr').textContent = 'แพทย์ ' + (s.drNow || 0) + ' คน (ทั้งวัน ' + (data.doctors || 0) + ')';
    var nRx = 0;
    areas.forEach(function (a) { if (a.id === 'RX' || a.id === 'CASH') nRx += a.n; });
    $('chipRx').textContent = 'ห้องยา/การเงิน: รอ ' + nRx + ' คน';
    $('tlTime').textContent = hhmm(state.T) + ' น. / ' + hhmm(maxT()) + ' น.';
  }

  // ------------------------------------------------------------------ ค้นหา HN / เลขคิว (ต้อง login)
  $('search').onsubmit = function (e) {
    e.preventDefault();
    var q = $('q').value.trim();
    if (!q || !CFG.loggedIn) return;
    toast('กำลังค้นหา…');
    fetch(CFG.find + '?d=' + encodeURIComponent(state.date) + '&q=' + encodeURIComponent(q), { cache: 'no-store' })
      .then(function (r) { return r.json().then(function (j) { if (!r.ok) throw new Error(j.error || r.status); return j; }); })
      .then(function (j) {
        if (!j.ids.length) { state.find = null; toast('ไม่พบ visit ของวันที่เลือก'); return; }
        state.find = j.ids; state.findAt = performance.now();
        var v = data.visits.filter(function (x) { return j.ids.indexOf(x.i) >= 0; })[0];
        if (!v) { toast('พบ visit แต่ไม่อยู่ในผัง (Admit / Telemed / PCU)'); return; }
        if (state.dep && v.d !== state.dep) setDep('');
        if (state.T < v.s[0][0] || state.T >= v.e) {
          setLive(false); state.playing = false; syncButtons();
          state.T = Math.min(v.s[0][0] + 1, maxT());
          toast('ย้ายเวลาไปตอนที่ผู้ป่วยมาถึง ' + hhmm(v.s[0][0]) + ' น.');
        } else toast('พบผู้ป่วย — วงแดงบนผัง');
        setTimeout(function () {
          var p = people.get(v.i);
          startTrack(v.i);
          if (p && p.vis) showTip(p, W / 2, H / 2);
        }, 120);
      })
      .catch(function (err) { toast('ค้นหาไม่สำเร็จ: ' + err.message); });
  };

  var toastT = 0;
  function toast(msg) {
    $('note').textContent = msg;
    toastT = Date.now();
  }

  // ------------------------------------------------------------------ กรองแผนก
  function setDep(code) {
    state.dep = code;
    var d = (data.deps || []).filter(function (x) { return x.code === code; })[0];
    $('depTag').hidden = !code;
    $('depTag').textContent = d ? d.name : '';
    state.lastT = null;
    people.clear();
    buildOcc();
    if (state.track && !visible(visitById[state.track] || {})) stopTrack();
  }

  // ------------------------------------------------------------------ เมนูรายงาน (คำนวณจากข้อมูลในหน้า)
  function openModal(title, html) {
    $('modalTitle').textContent = title;
    $('modalBody').innerHTML = html;
    $('modal').hidden = false;
  }
  $('modalClose').onclick = function () { $('modal').hidden = true; };
  $('modal').onclick = function (e) { if (e.target === this) this.hidden = true; };
  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    if (!$('modal').hidden) $('modal').hidden = true; else stopTrack();
  });

  function shownVisits() { return data.visits.filter(visible); }
  // ระยะเวลาที่อยู่แต่ละจุด (เฉพาะช่วงที่จบก่อนเวลาจำลอง T)
  function segDurations(vs, T) {
    var out = {};
    vs.forEach(function (v) {
      for (var i = 0; i < v.s.length; i++) {
        var t0 = v.s[i][0], t1 = i + 1 < v.s.length ? v.s[i + 1][0] : v.e;
        if (t1 > T) break;
        (out[v.s[i][1]] = out[v.s[i][1]] || []).push(t1 - t0);
      }
    });
    return out;
  }
  function table(head, rows) {
    return '<table class="rt"><thead><tr>' + head.map(function (h) { return '<th>' + h + '</th>'; }).join('') +
      '</tr></thead><tbody>' + (rows.length ? rows.map(function (r) {
        return '<tr>' + r.map(function (c) { return '<td>' + c + '</td>'; }).join('') + '</tr>';
      }).join('') : '<tr><td colspan="' + head.length + '" class="muted">ยังไม่มีข้อมูล</td></tr>') + '</tbody></table>';
  }
  var asOf = function () { return '<p class="muted">ข้อมูล ' + esc(data.date_label) + ' ถึงเวลา ' + hhmm(state.T) + ' น.' +
    (state.dep ? ' · เฉพาะแผนกที่เลือก' : '') + '</p>'; };

  var REPORTS = {
    date: function () {
      openModal('เลือกวันที่', '<p>ดูย้อนหลัง (Replay) ได้ทุกวัน · วันนี้ดูแบบสดได้</p>' +
        '<div class="rbtns"><input type="date" id="mDate" max="' + CFG.today + '" value="' + state.date + '">' +
        '<button data-d="' + CFG.today + '">วันนี้</button></div>');
      $('mDate').onchange = function () { setDate(this.value); $('modal').hidden = true; };
      $('modalBody').querySelector('[data-d]').onclick = function () { setDate(this.dataset.d); $('modal').hidden = true; };
    },
    dep: function () {
      var cnt = {};
      data.visits.forEach(function (v) { cnt[v.d] = (cnt[v.d] || 0) + 1; });
      var html = '<div class="deplist"><button data-c="" class="' + (state.dep ? '' : 'on') + '">ทุกแผนกที่มีคนไข้ <b>' + data.visits.length + '</b></button>' +
        data.deps.map(function (d) {
          return '<button data-c="' + esc(d.code) + '" class="' + (state.dep === d.code ? 'on' : '') + '">' + esc(d.name) +
            ' <b>' + (cnt[d.code] || 0) + '</b></button>';
        }).join('') + '</div><p class="muted">แผนกหลักของ visit (ovst.main_dep)</p>';
      openModal('กรองตามแผนก', html);
      $('modalBody').querySelectorAll('[data-c]').forEach(function (b) {
        b.onclick = function () { setDep(this.dataset.c); $('modal').hidden = true; };
      });
    },
    staff: function () {
      var s = state.stats || {};
      var rows = areas.filter(function (a) { return a.L.room; }).map(function (a) {
        return [a.name, a.n ? '<b class="ok">มีผู้ป่วย</b>' : '<span class="muted">ว่าง</span>', a.served + ' ราย'];
      });
      var withDr = shownVisits().filter(function (v) { return v.dr !== null && v.s[0][0] <= state.T; }).length;
      openModal('แพทย์', asOf() +
        '<div class="stat3"><div><small>แพทย์ที่ตรวจอยู่ขณะนี้</small><b>' + (s.drNow || 0) + '</b></div>' +
        '<div><small>แพทย์ที่ตรวจทั้งวัน</small><b>' + data.doctors + '</b></div>' +
        '<div><small>ผู้ป่วยต่อแพทย์ (เฉลี่ย)</small><b>' + (data.doctors ? (withDr / data.doctors).toFixed(1) : '–') + '</b></div></div>' +
        table(['ห้องตรวจ', 'ขณะนี้', 'ตรวจแล้ว'], rows) +
        '<p class="muted">นับแพทย์จาก ovst.doctor ของ visit ที่มีเวลาแพทย์ตรวจ (OPD-DOCTOR) · ห้องตรวจจากชื่อแผนก "ห้องตรวจ N" ในคิว ไม่มีเลขห้อง = จัดห้องตามรหัสแพทย์ · ข้อมูลพยาบาลยังไม่มีใน HOSxP ส่วนที่ใช้</p>');
    },
    bydep: function () {
      var g = {};
      data.visits.forEach(function (v) {
        if (v.s[0][0] > state.T) return;
        var r = g[v.d] = g[v.d] || { n: 0, inside: 0, tot: [], q: 0 };
        r.n++; r.q += v.q;
        if (state.T < v.e) r.inside++; else r.tot.push(v.e - v.s[0][0]);
      });
      var name = {};
      data.deps.forEach(function (d) { name[d.code] = d.name; });
      var rows = Object.keys(g).sort(function (a, b) { return g[b].n - g[a].n; }).map(function (k) {
        var r = g[k];
        return [esc(name[k] || k), r.n, r.inside, r.tot.length ? mins(median(r.tot)) : '–', Math.round(r.q * 100 / r.n) + '%'];
      });
      openModal('สรุปตามแผนก', asOf() + table(['แผนก', 'มาแล้ว', 'อยู่ใน รพ.', 'เวลาใน รพ. (ค่ากลาง)', 'บันทึกเวลาครบ'], rows) +
        '<p class="muted">เวลาใน รพ. = ลงทะเบียน → ออก (นับเฉพาะ visit ที่ออกแล้ว)</p>');
    },
    wait: function () {
      var dur = segDurations(shownVisits(), state.T);
      var rows = areas.filter(isWait).map(function (a, i) {
        var xs = dur[areas.indexOf(a)] || [];
        var over = xs.filter(function (x) { return x > a.target; }).length;
        var m = median(xs);
        return [a.name, xs.length, xs.length ? '<b class="' + level(m, a.target) + '">' + Math.round(m) + '</b>' : '–',
          xs.length ? Math.round(pct(xs, 0.9)) : '–', a.target, xs.length ? Math.round(over * 100 / xs.length) + '%' : '–'];
      });
      openModal('รายงานเวลารอ', asOf() + table(['จุดบริการ', 'จำนวนครั้ง', 'ค่ากลาง (นาที)', 'P90 (นาที)', 'เป้าหมาย', 'เกินเป้า'], rows) +
        '<p class="muted">เวลาที่อยู่แต่ละจุด = เวลาที่บันทึกขั้นถัดไป − เวลาที่เข้าจุด · P90 = 90% ของผู้ป่วยรอไม่เกินค่านี้</p>');
    },
    insight: function () {
      var out = [], vs = shownVisits(), T = state.T;
      if (occ) {
        var best = -1, bh = 0;
        for (var h = 0; h < 24; h++) {
          if (h * 60 > T) break;
          var sum = 0; for (var t = h * 60; t < h * 60 + 60; t++) sum += occ[t];
          if (sum / 60 > best) { best = sum / 60; bh = h; }
        }
        if (best > 0) out.push(['fa-chart-area', 'ช่วงที่คนอยู่ใน รพ. มากที่สุด ' + hhmm(bh * 60) + '–' + hhmm(bh * 60 + 60) + ' น. (เฉลี่ย ' + Math.round(best) + ' คน)',
          'จัดกำลังคน/เปิดช่องบริการเพิ่มในช่วงนี้ และกระจายนัดผู้ป่วยไปช่วงที่ว่างกว่า']);
      }
      var dur = segDurations(vs, T), worst = null;
      areas.filter(isWait).forEach(function (a) {
        var xs = dur[areas.indexOf(a)] || [];
        if (xs.length < 5) return;
        var m = median(xs), over = xs.filter(function (x) { return x > a.target; }).length / xs.length;
        if (!worst || m / a.target > worst.r) worst = { a: a, m: m, r: m / a.target, over: over };
        if (over >= 0.3) out.push(['fa-triangle-exclamation', a.name + ': ผู้ป่วย ' + Math.round(over * 100) + '% รอเกินเป้า ' + a.target + ' นาที',
          'ตรวจลำดับคิวและจำนวนเจ้าหน้าที่ที่ ' + a.name]);
      });
      if (worst && worst.r > 1) out.unshift(['fa-hourglass-half', 'คอขวดหลัก: ' + worst.a.name + ' ค่ากลาง ' + Math.round(worst.m) + ' นาที (เป้า ' + worst.a.target + ')',
        'เริ่มแก้ที่จุดนี้ก่อน จะลดเวลาใน รพ. รวมได้มากที่สุด']);
      areas.forEach(function (a) {
        if (isWait(a) && a.dl === 'full') out.push(['fa-users', a.name + ' แน่นขณะนี้ (' + Math.round(a.dens * 100) + '% ของที่นั่ง)', 'แจ้งจุดถัดไปให้เร่งรับผู้ป่วย หรือเปิดที่นั่งสำรอง']);
      });
      var s = state.stats || {};
      if (s.arrived && s.qual / s.arrived < 0.7) out.push(['fa-clipboard-check', 'บันทึกเวลาครบคู่เพียง ' + Math.round(s.qual * 100 / s.arrived) + '% ของผู้ป่วย',
        'ตัวเลขเวลารออาจคลาดเคลื่อน — ย้ำการกดเวลาซักประวัติและเวลาแพทย์เริ่มตรวจใน HOSxP']);
      if (!out.length) out.push(['fa-circle-check', 'ยังไม่พบจุดที่น่ากังวล ณ เวลานี้', 'ทุกจุดอยู่ในเป้าหมาย']);
      openModal('วิเคราะห์ & แนะนำ', asOf() + '<div class="insights">' + out.map(function (o) {
        return '<div class="ins"><i class="fa-solid ' + o[0] + '"></i><div><b>' + esc(o[1]) + '</b><span>' + esc(o[2]) + '</span></div></div>';
      }).join('') + '</div><p class="muted">ข้อแนะนำสร้างจากกฎง่าย ๆ บนตัวเลขในหน้านี้ ใช้ประกอบการตัดสินใจ ไม่ใช่ข้อสรุป</p>');
    },
    live: function () {
      if (state.date !== CFG.today) { setDate(CFG.today); return; }
      setLive(true); state.T = nowMin();
    },
    toggle: function () { $('menu').classList.toggle('collapsed'); }
  };
  document.querySelectorAll('#menu [data-act]').forEach(function (b) {
    b.onclick = function () { if (data || b.dataset.act === 'toggle') REPORTS[b.dataset.act](); };
  });

  // ------------------------------------------------------------------ ปุ่ม ⓘ: ที่มาของข้อมูล + เงื่อนไขกรอง
  // ชื่อเฉพาะของ HOSxP แสดงคู่คำอธิบายภาษาไทยเสมอ (ผู้ใช้บางคนไม่รู้จักชื่อตาราง/รหัส)
  var Q = 'opd_dep_queue (บันทึกการส่งผู้ป่วยไปแผนก: เวลาส่ง / รับคิว / เสร็จ)';
  var APPT = 'มีนัดที่มาตามนัดใน visit นี้ (oapp.visit_vn = เลข visit) และถูกส่งคิวไป 115 จุดซักประวัติ OPD เฉพาะทาง, 209 จุดซักประวัติ ศัลยกรรม หรือ 191 ศัลยกรรมกระดูก คลินิกพิเศษ';
  var COMMON = ['นับเป็น visit (การมารับบริการ 1 ครั้ง, ตาราง ovst) ของวันที่เลือก',
    'ไม่รวมผู้ป่วยที่ Admit (มีเลข AN), visit ที่ไม่ได้รับบริการใน รพ. (ovst.pt_subtype ≠ 0) และแผนก Telemed / PCU / ส่งยาทางไปรษณีย์',
    'ไม่แสดงการส่งคิวไป 107 แพทย์แผนจีน, 124 กิจกรรมบำบัด, 005 ห้องผ่าตัด, 004 ห้องคลอด, 216 เวชปฏิบัติทั่วไป, Ward / ผู้ป่วยพิเศษ, ลงทะเบียน Admit และ 069 ไตเทียม (ผู้ป่วยอยู่ที่จุดเดิม)'];
  var ROOM = ['อยู่หน้าห้องตรวจเมื่อถูกส่งคิวไป "หน้าห้องตรวจ", "ห้องตรวจ" หรือจุด Exit / หลังพบแพทย์',
    'ห้องตรวจรับคิว (เวลารับคิวใน ' + Q + ') = เข้าห้องพบแพทย์ · ห้องตรวจกดเสร็จ = กลับมารอหน้าห้อง',
    'ชื่อห้องไม่มีเลข (เช่น ห้องตรวจตา) = จัดห้องตามรหัสแพทย์ ให้แพทย์คนเดียวกันอยู่ห้องเดิมทั้งวัน (ประมาณ)',
    'ห้องตรวจ 11 (127) = แพทย์มาช่วยตรวจ ผู้ป่วยรอที่หน้าห้องตรวจฝั่งที่ซักประวัติมา ถึงคิวค่อยเข้าห้อง 11 · ห้องตรวจพิเศษ 1–4 นับที่คลินิกพิเศษ · ห้องตรวจ 6 นับที่คลินิกเด็ก'];
  var AREA_INFO = {
    REG:  ['ลงทะเบียนเปิด visit (ovst.vsttime) แล้วอยู่ที่เวชระเบียนจนกว่าจะมีคิวแรก', 'ไม่มีคิวภายใน 3 นาทีหลังลงทะเบียน = ถือว่าเดินไปแผนกหลักของ visit (ovst.main_dep)', 'ยืนต่อแถว ไม่มีที่นั่ง · ค้างได้นานสุด 15 นาทีถ้าไม่มีขั้นถัดไป'],
    SCRW: ['ส่งคิวไปจุดซักประวัติทั่วไป (เช่น 207 จุดซักประวัติ GP), OPD นอกเวลา หรือแผนกที่ไม่เข้ากลุ่มใดบนผัง', 'ผู้ป่วยที่ไม่เข้าเงื่อนไขฝั่งนัดแต่ถูกส่งไป 115 / 209 ก็นับที่นี่'],
    SCRA: ['ส่งคิวไป 115 / 209 / 191 และเป็นผู้ป่วยฝั่งนัด: ' + APPT],
    PED:  ['ส่งคิวไปแผนกที่ชื่อมีคำว่า เด็ก / กุมาร (เช่น 134 จุดซักประวัติคลินิกเด็ก) · ตรวจที่ห้อง 6 แล้วกลับมารอที่นี่'],
    DRWW: ['ผู้ป่วยฝั่ง Walk-in = ทุกคนที่ไม่เข้าเงื่อนไขฝั่งนัด'].concat(ROOM),
    DRWA: ['ผู้ป่วยฝั่งนัด: ' + APPT].concat(ROOM),
    CASH: ['ส่งคิวไปแผนกที่ชื่อมีคำว่า การเงิน / เก็บเงิน / ชำระเงิน (รวม 225 การเงินคลินิกพิเศษ)', 'ห้องเก็บเงินแทบไม่กดรับคิว จึงอยู่ได้นานสุด 30 นาทีถ้าไม่มีขั้นถัดไป'],
    RX:   ['ส่งคิวไปห้องจ่ายยา / เภสัชกรรม (รวม 204 ห้องจ่ายยาคลินิกพิเศษ)', 'ค้างได้นานสุด 60 นาทีถ้าไม่มีขั้นถัดไป'],
    ER:   ['ส่งคิวไปแผนกที่ชื่อมี ER / ฉุกเฉิน / อุบัติเหตุ', 'ไม่คิดเวลารอ เพราะตรวจตามความเร่งด่วน'],
    LAB:  ['เวลาสั่งแล็บ (lab_head.order_time) ถึงเวลารับสิ่งส่งตรวจ (lab_head.receive_time) แล้วกลับจุดเดิม', 'หรือถูกส่งคิวไปแผนกชันสูตร / เทคนิคการแพทย์'],
    XRAY: ['เวลาส่ง X-Ray (xray_report.request_time) — HOSxP ไม่มีเวลาเสร็จ จึงถือว่าอยู่ 10 นาทีแล้วกลับจุดเดิม'],
    US:   ['เวลาส่ง Ultrasound (xray_report.request_time รายการชื่อมี ultra / u/s / อัลตร้า) — ถือว่าอยู่ 20 นาทีแล้วกลับจุดเดิม'],
    DENT: ['ส่งคิวไปแผนกที่ชื่อมีคำว่า ทันต (รวมห้องบัตร / การเงินทันตกรรม)'],
    WOUND: ['ส่งคิวไป 078 คัดกรองห้องทำแผล หรือ 047 ห้องทำแผล'],
    ARI:  ['ส่งคิวไปแผนกที่ชื่อมีคำว่า ARI (บัตร ซักประวัติ ตรวจ การเงิน ยา Exit อยู่ในอาคาร ARI ทั้งหมด)'],
    TTM:  ['ส่งคิวไปแผนกที่ชื่อมีคำว่า แผนไทย'],
    PT:   ['ส่งคิวไปแผนกที่ชื่อมีคำว่า กายภาพ'],
    SPC:  ['ส่งคิวไปคลินิกเฉพาะโรค = แผนกที่ชื่อมีคำว่า คลินิก / คลีนิก / clinic (เช่น 039 เบาหวาน, 197 ความดัน, 093 CKD) หรือห้องตรวจพิเศษ 1–4 (035, 056, 057, 237)',
      'ยกเว้น คลินิกเด็ก, ARI, ทันตกรรม และห้องยา / การเงิน / Exit ของคลินิกพิเศษ ซึ่งนับที่จุดของตัวเอง · 191 ของผู้ป่วยฝั่งนัดนับที่ซักประวัติ ผู้ป่วยนัด',
      'ตำแหน่งอาคารและจำนวนที่นั่ง (40) บนผังเป็นค่าชั่วคราว รอหน่วยงานแจ้งค่าจริง']
  };
  function infoHtml(def, cond, extra) {
    var li = function (xs) { return '<ul class="how">' + xs.map(function (x) { return '<li>' + esc(x) + '</li>'; }).join('') + '</ul>'; };
    return '<p class="ihead">นับอย่างไร</p>' + li(def) + '<p class="ihead">เงื่อนไขกรอง</p>' + li(cond) + (extra || '');
  }
  function areaInfo(a) {
    var deps = a.deps.length ? '<p class="ihead">แผนกในคิววันนี้ที่นับที่จุดนี้</p><p class="idep">' + a.deps.map(esc).join(' · ') + '</p>' : '';
    var show = a.nowait ? ['ตัวเลข = จำนวนคนที่อยู่ ณ เวลาที่เลือก · วันนี้ = จำนวนรายที่ถูกส่งออกจาก ER แล้ว'] :
      ['ตัวเลข = จำนวนคนที่อยู่ ณ เวลาที่เลือก', 'รอ ~ = ค่ากลาง (มัธยฐาน) ของเวลาที่คนในจุดนี้รอมาแล้ว · เป้าหมาย ≤ ' + a.target + ' นาที',
        a.L.queue ? 'ไม่คิดความหนาแน่น (ยืนต่อแถว)' : 'แถบสี = ความหนาแน่น (คนในจุด ÷ ที่นั่ง ' + (a.L.cap || a.chairs.length) + ' ที่)'];
    openModal(a.name, infoHtml((AREA_INFO[a.id] || ['ตามคิวส่งแผนกใน ' + Q]).concat(show),
      ['ตำแหน่งตาม ' + Q + ' · เวลาก่อนลงทะเบียนหรือเกิน 10 ชม. หลังลงทะเบียนไม่นับ'].concat(COMMON), deps));
  }
  var KPI_INFO = {
    kIn: ['ผู้ป่วยใน รพ.', ['จำนวน visit ที่ลงทะเบียนแล้วและยังไม่ออกจาก รพ. ณ เวลาที่เลือก', 'ออกจาก รพ. = ส่งคิว "999 กลับบ้าน" ใน ' + Q + ' หรือค้างที่จุดสุดท้ายเกินเวลาที่กำหนด']],
    kWait: ['รอเฉลี่ย', ['ค่ากลาง (มัธยฐาน) ของเวลาที่ผู้ป่วยทุกคนในจุดรอนั่งรอมาแล้ว ณ เวลาที่เลือก', 'ไม่รวมคนที่อยู่ในห้องตรวจและห้องฉุกเฉิน']],
    kDens: ['ความหนาแน่น', ['คนในจุดรอทั้งหมด ÷ ที่นั่งรวม (จำนวนที่นั่งจริงที่หน่วยงานแจ้ง)', 'ต่ำกว่า 45% = ว่าง · 45–79% = ปานกลาง · ตั้งแต่ 80% = แน่น', 'ไม่รวมจุดที่ยืนต่อแถว (เวชระเบียน)']],
    kLeft: ['กลับบ้านแล้ววันนี้', ['จำนวน visit ที่ออกจาก รพ. แล้วถึงเวลาที่เลือก (ส่งคิว 999 กลับบ้าน หรือค้างที่จุดสุดท้ายเกินเวลาที่กำหนด)']],
    kPcu: ['Telemed / PCU วันนี้', ['visit ที่แผนกหลักเป็น Telemedicine / PCU / ส่งยาทางไปรษณีย์ หรือ ovst.pt_subtype ≠ 0 (ไม่ได้รับบริการใน รพ.) ที่ลงทะเบียนแล้วถึงเวลาที่เลือก', 'นับแยก ไม่แสดงบนผัง']],
    kQual: ['บันทึกเวลาครบคู่', ['% ของ visit ที่ลงทะเบียนแล้ว ซึ่งมีทั้งเวลาซักประวัติ (OPD-SCREEN) และเวลาแพทย์เริ่มตรวจ (OPD-DOCTOR) ในตาราง ovst_service_time (บันทึกเวลาแต่ละขั้นบริการ)', 'ยิ่งต่ำ ตัวเลขเวลารอยิ่งคลาดเคลื่อน']]
  };
  document.querySelectorAll('[data-kinfo]').forEach(function (b) {
    b.onclick = function () {
      var k = KPI_INFO[b.dataset.kinfo];
      openModal(k[0], infoHtml(k[1], k[0] === 'Telemed / PCU วันนี้' ? ['นับเป็น visit (ovst) ของวันที่เลือก'] : COMMON));
    };
  });

  $('btnHow').onclick = function () {
    openModal('วิธีคำนวณ', '<ul class="how">' +
      '<li><b>ผู้ป่วยในผัง</b> = visit (ovst) ของวันที่เลือก ไม่รวม Admit, Telemed, PCU และส่งยาทางไปรษณีย์ ซึ่งนับแยกไว้</li>' +
      '<li><b>ตำแหน่ง</b> ตามคิวส่งแผนกใน HOSxP (opd_dep_queue): ส่งไปแผนก = ไปรอที่จุดนั้น · ห้องตรวจรับคิว = อยู่ในห้อง · ส่ง "กลับบ้าน" = ออกจาก รพ. · เวลาก่อนลงทะเบียนหรือเกิน 10 ชม. ไม่นับ</li>' +
      '<li><b>จุด Exit / หลังพบแพทย์</b> = พบแพทย์แล้ว รอทำนัดหรือส่งทำหัตถการต่อ แสดงที่หน้าห้องตรวจ · <b>OPD นอกเวลา</b> แสดงที่ซักประวัติ Walk-in · <b>คัดกรองห้องทำแผล / ห้องทำแผล</b> อยู่อาคารอุบัติเหตุ</li>' +
      '<li><b>ค่าประมาณ</b>: แล็บใช้เวลาสั่ง–รับสิ่งส่งตรวจ (lab_head) · X-Ray/Ultrasound มีแค่เวลาส่ง ถือว่าอยู่ 10/20 นาทีแล้วกลับจุดเดิม · การเงินแทบไม่กดรับคิว จึงอยู่ได้นานสุด 30 นาทีถ้าไม่มีขั้นถัดไป</li>' +
      '<li><b>รอเฉลี่ย</b> = ค่ากลาง (มัธยฐาน) ของเวลาที่ผู้ป่วยที่อยู่ในจุดรอทุกจุดนั่งรอมาแล้ว ณ เวลานั้น (ไม่รวมห้องตรวจและห้องฉุกเฉิน)</li>' +
      '<li><b>ความหนาแน่น</b> = คนในจุดรอทั้งหมด ÷ ที่นั่งรวม · &lt;45% ว่าง · 45–79% ปานกลาง · ≥80% แน่น</li>' +
      '<li><b>สีป้าย/หน้าหุ่น</b> = รอเกินเป้าหมายของจุด (ส้ม) หรือเกิน 1.5 เท่า (แดง) · ❤️ = ออกจากจุดรอภายในเป้า</li>' +
      '<li><b>ห้องฉุกเฉิน</b> ไม่คิดเวลารอ (ตรวจตามความเร่งด่วน) อยู่จนถูกส่งต่อหรือส่งกลับบ้านตามคิว</li>' +
      '<li><b>บันทึกเวลาครบคู่</b> = มีทั้งเวลาซักประวัติ (OPD-SCREEN) และแพทย์ตรวจ (OPD-DOCTOR) ใน ovst_service_time</li>' +
      '<li><b>ฝั่งผู้ป่วยนัด</b> (ซักประวัติ / หน้าห้องตรวจ ผู้ป่วยนัด) = ' + esc(APPT) + ' · คนอื่นทั้งหมดอยู่ฝั่ง Walk-in · ถ้า HOSxP ไม่บอกห้อง แพทย์แต่ละคนถูกจัดไว้ห้องเดิมทั้งวันตามรหัสแพทย์ (ประมาณ)</li>' +
      '<li><b>คลินิกพิเศษ</b> = ส่งคิวไปคลินิกเฉพาะโรค (ชื่อแผนกมีคำว่า คลินิก) เช่น เบาหวาน ความดัน CKD · ตำแหน่งอาคารบนผังเป็นค่าชั่วคราว</li>' +
      '<li>กดปุ่ม <i class="fa-solid fa-circle-info" aria-hidden="true"></i> ข้างแต่ละจุดบริการหรือการ์ดตัวเลข เพื่อดูที่มาและเงื่อนไขของตัวเลขนั้น</li>' +
      '<li><b>ชื่อ-สกุล / HN</b> แสดงเฉพาะผู้ที่เข้าสู่ระบบ (โหลดแยกจากข้อมูลผัง ไม่เก็บแคช) · คลิกหุ่นเพื่อติดตาม · Esc เลิกติดตาม</li>' +
      '</ul><p class="muted">เอนจินผังพอร์ตจาก opd-dashboard-wnk (MIT) © 2569 นายเผด็จชัย สิงหล รพ.วังน้ำเขียว</p>');
  };

  // ------------------------------------------------------------------ โหลดข้อมูล
  var loading = false, lastFetch = 0;
  function load(reset) {
    if (loading) return;
    loading = true;
    if (reset) toast('กำลังโหลดข้อมูล…');
    fetch(CFG.api + '?d=' + encodeURIComponent(state.date), { cache: 'no-store' })
      .then(function (r) { return r.json().then(function (j) { if (!r.ok || j.error) throw new Error(j.error || r.status); return j; }); })
      .then(function (j) {
        var first = !data;
        data = j; lastFetch = Date.now();
        visitById = {};
        j.visits.forEach(function (v) { visitById[v.i] = v; });
        if (first || reset || Date.now() - namesAt > 300000) loadNames();
        if (first || reset) {
          buildAreas(); buildStatics(); buildPanel();
          people.clear();
          state.lastT = null;
          $('depTag').hidden = true;
          buildOcc();
          if (state.date === CFG.today) { setLive(true); state.T = nowMin(); }
          else { setLive(false); state.T = TL0 + 30; state.playing = true; }
          resize();
        } else {
          buildOcc();
        }
        $('dateLabel').textContent = j.date_label;
        if (Date.now() - toastT > 4000 || reset) {
          $('note').textContent = 'ผู้ป่วย ' + j.visits.length + ' ราย · ข้อมูล ณ ' + j.generated + ' น.' +
            (j.is_today ? ' (รีเฟรชทุก 1 นาที)' : '') + (state.live ? ' · กด ▶ เพื่อเล่นย้อนหลัง' : ' · ลากกราฟเพื่อเลื่อนเวลา');
        }
      })
      .catch(function (e) { toast('โหลดข้อมูลไม่สำเร็จ (' + e.message + ') — จะลองใหม่อัตโนมัติ'); lastFetch = Date.now(); })
      .then(function () { loading = false; });
  }

  // ------------------------------------------------------------------ วนลูป
  var prev = performance.now(), panelAt = 0;
  function frame(now) {
    var dt = Math.min(0.1, (now - prev) / 1000);
    prev = now;
    if (data) {
      if (state.live) {
        state.T = nowMin();
        if (Date.now() - lastFetch > 60000) load(false);
      } else if (state.playing) {
        state.T += dt * state.speed / 60;
        var mt = maxT();
        if (state.T >= mt) {
          state.T = mt;
          if (state.date === CFG.today) setLive(true); else { state.playing = false; syncButtons(); }
        }
      }
      step(dt);
      zoomStep(dt);
      followCam(dt);
      draw();
      if (now - panelAt > 250) { updatePanel(); drawTimeline(); updateTip(); renderTrack(); panelAt = now; }
      $('clock').textContent = hhmm(state.T);
      $('mode').textContent = state.live ? 'LIVE' : 'REPLAY · ×' + state.speed;
    } else if (!W) {
      resize();
    }
    requestAnimationFrame(frame);
  }

  // จอไม่สูงพอ เมนูเต็มจะทับแผงจุดบริการ — เริ่มแบบย่อ (กด "เมนู" เพื่อขยาย)
  if (window.innerWidth < 900 || window.innerHeight < 1080) $('menu').classList.add('collapsed');
  window.addEventListener('resize', resize);
  resize();
  load(true);
  requestAnimationFrame(frame);
})();
