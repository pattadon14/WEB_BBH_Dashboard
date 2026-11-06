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
  // x = ไปทางขวาล่างของจอ, y = ไปทางซ้ายล่างของจอ · ผังย่อส่วน ไม่ใช่แบบแปลนจริง
  // b = อาคาร, kind = สีพื้น, counter = เคาน์เตอร์ชิดผนังด้านหลัง, room = ห้องตรวจ (โต๊ะแพทย์ + เตียง),
  // wait = จุดรอหน้าห้องของห้องตรวจนี้, door = ทางเข้าเฉพาะ (ไม่ใช้ประตูอาคาร)
  // cap = ที่นั่งรอจริงของบ้านบึง (แจ้งโดยหน่วยงาน 4 พ.ย. 2569) · queue = ยืนต่อแถว ไม่มีเก้าอี้ ไม่คิดความหนาแน่น
  var BLD = {
    OPD: { name: 'อาคารผู้ป่วยนอก', r: [0, 34, 72, 44], door: [65, 34], sign: [30, 80] },
    ERB: { name: 'อาคารอุบัติเหตุ', r: [30, 0, 70, 30], door: [50, 30], sign: [101, 7.5] },
    ARI: { name: 'อาคาร ARI', r: [78, 36, 22, 14], door: [100, 43], sign: [101, 39.5] },
    TTM: { name: 'อาคารแพทย์แผนไทย', r: [78, 53, 22, 13], door: [100, 59.5], sign: [101, 56] },
    PT:  { name: 'อาคารกายภาพบำบัด', r: [78, 69, 22, 13], door: [100, 75.5], sign: [101, 72] }
  };
  var LAYOUT = {
    // อาคารผู้ป่วยนอก: บริการร่วมชิดทางเข้า · ฝั่ง walk-in (บน) · ฝั่งผู้ป่วยนัด (ล่าง)
    REG:  { b: 'OPD', r: [58, 34, 14, 12], kind: 'hall', counter: true, queue: true },
    CASH: { b: 'OPD', r: [58, 48, 14, 12], kind: 'rx', counter: true, cap: 21 },    // ห้องยา + การเงิน OPD รวม 42
    RX:   { b: 'OPD', r: [58, 62, 14, 16], kind: 'rx', counter: true, cap: 21 },
    SCRW: { b: 'OPD', r: [38, 34, 18, 14], kind: 'walk', counter: true, cap: 64 },
    DRWW: { b: 'OPD', r: [0, 34, 36, 8], kind: 'walk', cap: 15 },                    // ห้อง 1–5 ห้องละ 3
    R1:   { b: 'OPD', r: [0, 42, 7.2, 6],    kind: 'room', room: true, wait: 'DRWW', short: 'ห้อง 1' },
    R2:   { b: 'OPD', r: [7.2, 42, 7.2, 6],  kind: 'room', room: true, wait: 'DRWW', short: 'ห้อง 2' },
    R3:   { b: 'OPD', r: [14.4, 42, 7.2, 6], kind: 'room', room: true, wait: 'DRWW', short: 'ห้อง 3' },
    R4:   { b: 'OPD', r: [21.6, 42, 7.2, 6], kind: 'room', room: true, wait: 'DRWW', short: 'ห้อง 4' },
    R5:   { b: 'OPD', r: [28.8, 42, 7.2, 6], kind: 'room', room: true, wait: 'DRWW', short: 'ห้อง 5' },
    PED:  { b: 'OPD', r: [0, 50, 26, 10], kind: 'ped', counter: true, cap: 20 },    // ซักประวัติ + รอพบแพทย์เด็ก
    R6:   { b: 'OPD', r: [26, 50, 10, 10], kind: 'room', room: true, wait: 'PED', short: 'ห้อง 6 เด็ก' },
    SCRA: { b: 'OPD', r: [38, 62, 18, 14], kind: 'appt', counter: true, cap: 36 },
    DRWA: { b: 'OPD', r: [0, 62, 36, 8], kind: 'appt', cap: 12 },                    // ห้อง 7–10 ห้องละ 3
    R7:   { b: 'OPD', r: [0, 70, 9, 6],  kind: 'roomA', room: true, wait: 'DRWA', short: 'ห้อง 7' },
    R8:   { b: 'OPD', r: [9, 70, 9, 6],  kind: 'roomA', room: true, wait: 'DRWA', short: 'ห้อง 8' },
    R9:   { b: 'OPD', r: [18, 70, 9, 6], kind: 'roomA', room: true, wait: 'DRWA', short: 'ห้อง 9' },
    R10:  { b: 'OPD', r: [27, 70, 9, 6], kind: 'roomA', room: true, wait: 'DRWA', short: 'ห้อง 10' },
    // อาคารอุบัติเหตุ
    DENT: { b: 'ERB', r: [30, 0, 26, 18], kind: 'room2', counter: true, cap: 40 },
    // shortcut: ที่นั่งห้องทำแผลยังไม่ได้รับแจ้ง ใช้ 10 ไปก่อน — แก้เมื่อหน่วยงานแจ้งจำนวนจริง
    WOUND: { b: 'ERB', r: [30, 18, 26, 12], kind: 'lab', counter: true, cap: 10 },     // 078 คัดกรอง → 047 ห้องทำแผล
    LAB:  { b: 'ERB', r: [56, 0, 20, 13], kind: 'lab', counter: true, cap: 20 },
    XRAY: { b: 'ERB', r: [56, 16, 10, 14], kind: 'xray', counter: true, cap: 4 },
    US:   { b: 'ERB', r: [66, 16, 10, 14], kind: 'xray', counter: true, cap: 4 },
    // คัดกรอง 8 + รอแพทย์ 4 + เตียงรอแพทย์ ~7 + Observe 4
    ER:   { b: 'ERB', r: [76, 0, 24, 30], kind: 'er', door: [100, 12], cap: 23 },
    // อาคารแยก
    ARI:  { b: 'ARI', r: [78, 36, 22, 14], kind: 'er', counter: true, cap: 38 },     // รอซักประวัติ 12 + รอแพทย์/การเงิน/ยา 26
    TTM:  { b: 'TTM', r: [78, 53, 22, 13], kind: 'ttm', counter: true, cap: 4 },
    PT:   { b: 'PT',  r: [78, 69, 22, 13], kind: 'room2', counter: true, cap: 6 }
  };
  // โซนในอาคารผู้ป่วยนอก (เส้นประ + ป้ายบนพื้น)
  var ZONES = [
    { name: 'ฝั่ง Walk-in', r: [0, 34, 57, 26], c: '#1f9d6a' },
    { name: 'ฝั่งผู้ป่วยนัด', r: [0, 60.5, 57, 17], c: '#2563eb' }
  ];
  var DECOR = [{ name: 'ห้องเจ้าหน้าที่', r: [38, 50, 18, 10], kind: 'decor' }];
  var STREET_Y = 32, SIDE_X = 102;           // ทางเดินระหว่างอาคาร · ทางเท้าริมถนน
  var WALKS = [[30, 30.4, 74, 3.2], [100, -8, 4, 100]];
  var ROAD = [104, -8, 4, 100];
  var ROAD_X = 106, SPAWN_Y = 90;
  var BUILDINGS = Object.keys(BLD).map(function (k) { return BLD[k].r; });
  var TREES = [[6, 6], [16, 14], [6, 24], [20, 3], [22, 24]];
  for (var tx = 34; tx <= 98; tx += 8) TREES.push([tx, -4.5]);
  for (var ty = 38; ty <= 76; ty += 8) TREES.push([-4.5, ty]);
  for (var fx = 3; fx <= 70; fx += 8) TREES.push([fx, 81.5]);
  for (var ty2 = -2; ty2 <= 88; ty2 += 8) TREES.push([110.5, ty2]);
  var FLOOR = {
    hall: '#fbfcfd', lab: '#f3f0fb', room: '#eef4fc', rx: '#eef8f2', xray: '#eef2f7',
    er: '#fdf0ef', room2: '#eef7f9', ttm: '#f7f3e8', decor: '#f1f4f7',
    walk: '#f2faf5', appt: '#eef4fd', ped: '#fdf6ec', roomA: '#e8f0fd'
  };
  var WALL_TOP = '#1f9d6a';

  var COL = { ok: '#1fa463', mid: '#f59e0b', high: '#e5484d' };
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
  var SHIRT = { 1: '#dc2626', 2: '#f97316', 3: '#facc15', 4: '#22c55e', 5: '#ffffff', 6: '#8b5cf6' };
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
  function spareSpot(id) {   // จุดที่ไม่มีในผัง — เรียงใต้อาคาร
    var L = { r: [spare++ * 7, 84, 6, 6], kind: 'hall', spare: true };
    LAYOUT[id] = L;
    return L;
  }

  function buildAreas() {
    spare = 0;
    areas = data.areas.map(function (a) {
      var L = LAYOUT[a.id] || spareSpot(a.id);
      var r = L.r, x = r[0], y = r[1], w = r[2], d = r[3];
      var door = L.door || (BLD[L.b] || BLD.OPD).door;
      var seats = [];
      if (L.room) {
        seats = [[x + w / 2, y + d - 3.2], [x + w - 1.0, y + 1.3], [x + w - 1.0, y + 2.6],
                 [x + 1.0, y + 1.3], [x + 1.0, y + 2.6]];
      } else {
        var top = L.counter ? 2.7 : 1.4, sx = 1.45, sy = 2.0;
        for (var yy = y + top; yy <= y + d - 1.0; yy += sy) {
          for (var xx = x + 1.2; xx <= x + w - 0.9; xx += sx) {
            if (xx > x + w / 2 - 0.7 && xx < x + w / 2 + 0.5) continue;   // ทางเดินกลาง
            seats.push([xx, yy]);
          }
        }
      }
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
        id: a.id, name: a.name, target: a.target, nowait: !!a.nowait, L: L,
        x: x, y: y, w: w, d: d, cx: x + w / 2, cy: y + d / 2,
        chairs: L.queue ? [] : seats, spots: seats.concat(stand), used: [],
        door: door, out: door[0] >= SIDE_X - 2 ? [SIDE_X, door[1]] : [door[0], STREET_Y], n: 0, avg: 0, max: 0
      };
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

  // ทางเดินนอกอาคารจากประตูจุด a ไปประตูจุด b (ทางเดินระหว่างอาคาร y = STREET_Y, ทางเท้าริมถนน x = SIDE_X)
  function link(a, b) {
    var mid = a.out[1] === b.out[1] ? [] : [[SIDE_X, a.out[1]], [SIDE_X, b.out[1]]];
    return [a.door, a.out].concat(mid, [b.out, b.door]);
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
        p = { id: v.i, area: -1, spot: -1, x: 0, y: 0, vis: false, a: 0, enter: 0, sit: false };
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
            p.x = SIDE_X; p.y = SPAWN_Y;
            p.vis = true; p.a = jump ? 1 : 0; p.path = jump ? null : [[SIDE_X, ta.out[1]], ta.out, ta.door];
          } else if (from >= 0 && !jump && p.vis && areas[from].L.b !== ta.L.b) {
            p.path = link(areas[from], ta);    // ย้ายอาคาร: ออกประตู → ทางเดิน → เข้าประตูอาคารใหม่
          }
        } else if (p.vis) {
          var src = from >= 0 ? areas[from] : areas[0];
          if (jump) { p.vis = false; }
          else {
            p.path = [src.door, src.out, [SIDE_X, src.out[1]], [SIDE_X, SPAWN_Y]]; p.leaving = true;
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

    var spd = 6;   // เมตร/วินาที (เร็วกว่าคนจริงให้ทันเวลาจำลอง)
    people.forEach(function (p) {
      if (!p.vis) return;
      var tx = p.tx, ty = p.ty;
      if (p.path && p.path.length) { tx = p.path[0][0]; ty = p.path[0][1]; }
      else if (p.leaving) { p.vis = false; return; }
      var dx = tx - p.x, dy = ty - p.y, dist = Math.sqrt(dx * dx + dy * dy);
      var mv = Math.max(spd, dist * 2.2) * dt;
      if (dist <= mv) {
        p.x = tx; p.y = ty;
        if (p.path && p.path.length) p.path.shift();
        p.walking = !!(p.path && p.path.length);
      } else {
        p.x += dx / dist * mv; p.y += dy / dist * mv; p.walking = true;
      }
      p.a = Math.min(1, p.a + dt * 3);
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
  function roundRect(x, y, w, h, r) {
    r = Math.min(r, w / 2, Math.abs(h) / 2);
    ctx.beginPath();
    ctx.moveTo(x + r, y); ctx.arcTo(x + w, y, x + w, y + h, r); ctx.arcTo(x + w, y + h, x, y + h, r);
    ctx.arcTo(x, y + h, x, y, r); ctx.arcTo(x, y, x + w, y, r); ctx.closePath();
  }

  // วัตถุที่ต้องเรียงความลึก: ผนัง เก้าอี้ เคาน์เตอร์ ต้นไม้ หุ่น
  var statics = [];
  function buildStatics() {
    statics = [];
    // ผนังกั้นห้องในอาคาร (โปร่งแสง เตี้ย) — ผนังนอกอาคารใช้ฟังก์ชันเดียวกันแต่สูง/หนากว่า และเว้นช่องประตู
    var WH = 1.1;
    var IN = { h: WH, t: 0.12, face: 'rgba(255,255,255,.82)', side: 'rgba(236,241,247,.88)', cap: '#d3dbe6' };
    function nearDoor(doors, mx, my) {
      return doors && doors.some(function (d) { return Math.abs(d[0] - mx) < 1.7 && Math.abs(d[1] - my) < 1.7; });
    }
    function wallX(x0, x1, y, st, doors) {
      st = st || IN;
      for (var x = x0; x < x1 - 0.001; x += 1) {
        (function (xa, xb) {
          if (nearDoor(doors, (xa + xb) / 2, y)) return;
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
          statics.push({ k: x + (ya + yb) / 2 - 0.05, draw: function () {
            poly([[x, ya, 0], [x, yb, 0], [x, yb, st.h], [x, ya, st.h]], st.side, null);
            poly([[x - st.t, ya, st.h], [x, ya, st.h], [x, yb, st.h], [x - st.t, yb, st.h]], st.cap);
          } });
        })(y, Math.min(y + 1, y1));
      }
    }
    // ผนังนอกอาคาร: ด้านหลัง/ซ้ายสูง (เห็นเป็นตัวตึก) · ด้านหน้า/ขวาเป็นขอบเตี้ย (ตัดให้มองเห็นข้างใน)
    var BACK = { h: 2.6, t: 0.35, face: '#f7f9fa', side: '#e8edf1', cap: WALL_TOP };
    var FRONT = { h: 0.45, t: 0.3, face: '#e3e9ee', side: '#d5dde4', cap: WALL_TOP };
    Object.keys(BLD).forEach(function (id) {
      var r = BLD[id].r, doors = [BLD[id].door];
      areas.forEach(function (a) { if (a.L.b === id && a.L.door) doors.push(a.L.door); });
      wallX(r[0], r[0] + r[2], r[1], BACK, doors);
      wallY(r[1], r[1] + r[3], r[0], BACK, doors);
      wallX(r[0], r[0] + r[2], r[1] + r[3], FRONT, doors);
      wallY(r[1], r[1] + r[3], r[0] + r[2], FRONT, doors);
      var sg = BLD[id].sign;
      statics.push({ k: sg[0] + sg[1] + 0.5, draw: function () { drawSign(BLD[id].name, sg[0], sg[1]); } });
    });
    function onEdge(a) {   // ผนังห้องที่ทับผนังนอกอาคาร ไม่ต้องวาดซ้ำ
      var r = (BLD[a.L.b] || {}).r || [-1, -1];
      return { x: Math.abs(a.y - r[1]) < 0.01, y: Math.abs(a.x - r[0]) < 0.01 };
    }
    function counter(x, y, w) {
      var cw = Math.min(w - 2, 7), cx2 = x + (w - cw) / 2;
      statics.push({ k: cx2 + cw / 2 + y + 1.3, draw: function () {
        box(cx2, y + 0.9, 0, cw, 0.7, 0.95, '#ffffff', '#d6deea', '#c4cfdf');
        for (var m = 0; m < 3; m++) {
          box(cx2 + 0.8 + m * (cw - 1.6) / 2 - 0.25, y + 1.0, 0.95, 0.5, 0.08, 0.4, '#334155', '#1e293b', '#0f172a');
        }
      } });
    }
    DECOR.forEach(function (o) {
      wallX(o.r[0], o.r[0] + o.r[2], o.r[1]);
      wallY(o.r[1], o.r[1] + o.r[3], o.r[0]);
    });
    areas.forEach(function (a) {
      if (!a.L.open) {
        var e = onEdge(a);
        if (!e.x) wallX(a.x, a.x + a.w, a.y);
        if (!e.y) wallY(a.y, a.y + a.d, a.x);
      }
      if (a.L.counter) counter(a.x, a.y, a.w);
      if (a.L.room) {
        var dw = Math.min(2.8, a.w - 1.6), dx = a.x + (a.w - dw) / 2, dy = a.y + a.d - 2.4;
        statics.push({ k: dx + dw / 2 + dy + 0.6, draw: function () {
          box(dx, dy, 0, dw, 1.1, 0.85, '#f8fafc', '#dbe3ee', '#cbd5e1');
          box(dx + dw / 2 - 0.35, dy + 0.9, 0.85, 0.7, 0.1, 0.45, '#334155', '#1e293b', '#0f172a');
        } });
        statics.push({ k: a.x + 0.9 + a.y + 4.6, draw: function () {
          box(a.x + 0.35, a.y + 3.6, 0, 1.0, 2.0, 0.7, '#e0f2fe', '#bae6fd', '#93c5fd');
        } });
      }
      a.chairs.forEach(function (c) {
        statics.push({ k: c[0] + c[1] - 0.2, draw: function () { drawChair(c[0], c[1]); } });
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
        gr.addColorStop(0, '#6ee7a0'); gr.addColorStop(1, '#22a35a');
        ctx.fillStyle = gr;
        ctx.beginPath(); ctx.arc(c[0], c[1], 1.05 * cam.k, 0, 7); ctx.fill();
      } });
    });
  }

  // เก้าอี้รอตรวจ: ขาโลหะ + เบาะ + พนักพิง (โทนฟ้าอ่อน ไม่แย่งสายตาจากหุ่น)
  function drawChair(cx, cy) {
    var x = cx - 0.26, y = cy - 0.22;
    var b = P(cx, cy + 0.1, 0);
    ctx.fillStyle = 'rgba(15,42,92,.07)';
    ctx.beginPath(); ctx.ellipse(b[0], b[1], 0.42 * cam.k, 0.2 * cam.k, 0, 0, 7); ctx.fill();
    box(x + 0.05, y + 0.36, 0, 0.05, 0.05, 0.3, '#a8b4c4', '#8291a5', '#6b7a8f');
    box(x + 0.42, y + 0.36, 0, 0.05, 0.05, 0.3, '#a8b4c4', '#8291a5', '#6b7a8f');
    box(x, y, 0.3, 0.52, 0.44, 0.08, '#9bd0f2', '#5ba3d6', '#4b92c6');
    box(x + 0.02, y - 0.04, 0.38, 0.48, 0.08, 0.4, '#7fbfea', '#5ba3d6', '#4b92c6');
    box(x + 0.02, y - 0.04, 0.78, 0.48, 0.08, 0.02, '#b9e0f8', '#7fbfea', '#7fbfea');
  }
  // ป้ายชื่ออาคารบนเสา หน้าทางเข้า
  function drawSign(text, x, y) {
    var t = P(x, y, 2.4), g = P(x, y, 0);
    ctx.strokeStyle = '#94a3b8'; ctx.lineWidth = Math.max(1.5, cam.k * 0.07);
    ctx.beginPath(); ctx.moveTo(g[0], g[1]); ctx.lineTo(t[0], t[1]); ctx.stroke();
    var f = Math.round(Math.max(10, Math.min(13, cam.k * 0.75)));
    ctx.font = '500 ' + f + 'px Kanit, sans-serif';
    var w = ctx.measureText(text).width + 14, h = f + 9;
    ctx.fillStyle = 'rgba(15,42,92,.12)'; roundRect(t[0] - w / 2 + 1, t[1] - h + 2, w, h, 5); ctx.fill();
    ctx.fillStyle = 'rgba(31,157,106,.92)'; roundRect(t[0] - w / 2, t[1] - h, w, h, 5); ctx.fill();
    ctx.fillStyle = '#ffffff'; ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
    ctx.fillText(text, t[0], t[1] - h / 2 + 1);
    ctx.textAlign = 'left';
  }

  function drawPerson(p) {
    var k = cam.k * 1.25, base = P(p.x, p.y, 0);
    var shirt = SHIRT[p.u] || SHIRT[5], pants = PANTS[p.g] || PANTS[0];
    var sit = p.sit && !p.walking, z0 = sit ? 0.42 : 0;
    var Y = function (z) { return base[1] - (z0 * cam.k + (z - z0) * k) * ZK; };
    var x = base[0];
    ctx.globalAlpha = p.a;
    ctx.fillStyle = 'rgba(0,0,0,.16)';
    ctx.beginPath(); ctx.ellipse(x, base[1], 0.28 * k, 0.14 * k, 0, 0, 7); ctx.fill();
    if (k < 5) {
      ctx.fillStyle = shirt; ctx.strokeStyle = pants; ctx.lineWidth = 1.2;
      ctx.beginPath(); ctx.arc(x, Y(0.8), Math.max(1.8, 0.34 * k), 0, 7); ctx.fill(); ctx.stroke();
      ctx.globalAlpha = 1; return;
    }
    var bodyB = sit ? 0.6 : 0.66, bodyT = sit ? 1.1 : 1.34;
    ctx.lineCap = 'round';
    if (sit) {
      ctx.fillStyle = pants;
      roundRect(x - 0.21 * k, Y(0.66), 0.42 * k, Y(0.42) - Y(0.66) + 0.12 * k, 0.08 * k); ctx.fill();
      ctx.strokeStyle = pants; ctx.lineWidth = 0.15 * k;
      ctx.beginPath();
      ctx.moveTo(x - 0.08 * k, Y(0.42) + 0.1 * k); ctx.lineTo(x - 0.08 * k, base[1] - 0.02 * k);
      ctx.moveTo(x + 0.08 * k, Y(0.42) + 0.1 * k); ctx.lineTo(x + 0.08 * k, base[1] - 0.02 * k);
      ctx.stroke();
    } else {
      ctx.strokeStyle = pants; ctx.lineWidth = 0.17 * k;
      var sw = p.walking ? Math.sin(performance.now() / 90 + p.x) * 0.07 * k : 0;
      ctx.beginPath(); ctx.moveTo(x - 0.08 * k, Y(0.68)); ctx.lineTo(x - 0.08 * k + sw, Y(0.02));
      ctx.moveTo(x + 0.08 * k, Y(0.68)); ctx.lineTo(x + 0.08 * k - sw, Y(0.02)); ctx.stroke();
    }
    var bw = 0.44 * k;
    ctx.fillStyle = shirt;
    roundRect(x - bw / 2, Y(bodyT), bw, Y(bodyB) - Y(bodyT), 0.16 * k);
    ctx.fill();
    if (p.u === 5) { ctx.strokeStyle = 'rgba(15,42,92,.35)'; ctx.lineWidth = 1; ctx.stroke(); }
    var hy = Y(bodyT + 0.24), hr = 0.2 * k;
    if (p.g === 2) {
      ctx.fillStyle = '#3b2f2a';
      roundRect(x - hr * 1.12, hy - hr * 0.6, hr * 2.24, hr * 2.3, hr); ctx.fill();
    }
    ctx.fillStyle = '#f3c9a1';
    ctx.beginPath(); ctx.arc(x, hy, hr, 0, 7); ctx.fill();
    ctx.fillStyle = '#3b2f2a';
    ctx.beginPath(); ctx.arc(x, hy - 0.05 * k, hr, Math.PI * (p.g === 2 ? 0.95 : 1.05), Math.PI * (p.g === 2 ? 2.05 : 1.95)); ctx.fill();
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

  function drawDoctor(a) {
    var x = a.x + a.w / 2, y = a.y + a.d - 0.8, k = cam.k;
    var b0 = P(x, y, 0.55), b1 = P(x, y, 1.3), h = P(x, y, 1.54);
    ctx.fillStyle = '#ffffff'; ctx.strokeStyle = '#94a3b8'; ctx.lineWidth = 1;
    roundRect(b1[0] - 0.22 * k, b1[1], 0.44 * k, b0[1] - b1[1], 0.16 * k); ctx.fill(); ctx.stroke();
    ctx.fillStyle = '#f3c9a1'; ctx.beginPath(); ctx.arc(h[0], h[1], 0.2 * k, 0, 7); ctx.fill();
    ctx.fillStyle = '#1d2433';
    ctx.beginPath(); ctx.arc(h[0], h[1] - 0.05 * k, 0.2 * k, Math.PI * 1.05, Math.PI * 1.95); ctx.fill();
  }

  // พื้นแต่ละจุดผสมสีความหนาแน่น (เขียว/เหลือง/แดง) ให้เห็นความแออัดจากระยะไกล
  function tint(a) {
    if (!a.n || a.nowait || a.L.room || a.L.queue) return null;
    var c = DENS[a.dl].c;
    return c + (a.dl === 'low' ? '22' : a.dl === 'mid' ? '38' : '48');
  }

  function draw() {
    ctx.setTransform(DPR, 0, 0, DPR, 0, 0);
    ctx.clearRect(0, 0, W, H);
    ctx.fillStyle = '#f6f8f7'; ctx.fillRect(0, 0, W, H);

    rect(ROAD[0], ROAD[1], ROAD[2], ROAD[3], 0, '#dfe5ea');
    for (var m = ROAD[1] + 1; m < ROAD[1] + ROAD[3] - 2; m += 4) rect(ROAD_X - 0.12, m, 0.25, 2, 0, '#ffffff');
    WALKS.forEach(function (w) { rect(w[0], w[1], w[2], w[3], 0, '#e5e9ec'); });
    BUILDINGS.forEach(function (b) {
      box(b[0], b[1], -0.6, b[2], b[3], 0.6, '#ffffff', '#cfd8df', '#bcc7d0');
      rect(b[0], b[1], b[2], b[3], 0.001, '#f4f7f6');
    });
    ZONES.forEach(function (z) {
      var r = z.r, c = [P(r[0], r[1], 0.005), P(r[0] + r[2], r[1], 0.005), P(r[0] + r[2], r[1] + r[3], 0.005), P(r[0], r[1] + r[3], 0.005)];
      ctx.fillStyle = z.c + '10';
      ctx.beginPath(); ctx.moveTo(c[0][0], c[0][1]); for (var zi = 1; zi < 4; zi++) ctx.lineTo(c[zi][0], c[zi][1]);
      ctx.closePath(); ctx.fill();
    });
    DECOR.forEach(function (o) { rect(o.r[0], o.r[1], o.r[2], o.r[3], 0.002, FLOOR[o.kind], 'rgba(15,42,92,.10)'); });
    areas.forEach(function (a) {
      rect(a.x, a.y, a.w, a.d, 0.002, FLOOR[a.L.kind] || '#f4f7f8', 'rgba(15,42,92,.10)');
      var t = tint(a);
      if (t) rect(a.x, a.y, a.w, a.d, 0.003, t);
    });

    var items = statics.slice();
    people.forEach(function (p) { if (p.vis) items.push({ k: p.x + p.y, p: p }); });
    areas.forEach(function (a) { if (a.L.room && a.n) items.push({ k: a.x + a.w / 2 + a.y + a.d - 0.8, doc: a }); });
    items.sort(function (a, b) { return a.k - b.k; });
    for (var i = 0; i < items.length; i++) {
      var it = items[i];
      if (it.p) drawPerson(it.p); else if (it.doc) drawDoctor(it.doc); else it.draw();
    }
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
    if (cam.k >= 9) DECOR.forEach(function (o) {
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
      var b = measureLabel(a, fs);
      b.a = a;
      var p = P(a.x + a.w * 0.5, a.y + 0.2, 3.0);
      if (a.L.room) p = P(a.x + a.w * 0.5, a.y + a.d, 0.6);
      b.ax = p[0]; b.ay = p[1];
      b.x = p[0] - b.w / 2; b.y = p[1] - b.h - 6;
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
      placed.push(b);
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
    var name = labelName(a) + (served ? ' · ตรวจแล้ว ' + served : '');
    var n = a.n + roomsIn(a);
    var cnt = n + ' คน';
    ctx.font = '500 ' + fs + 'px Kanit, sans-serif';
    var nw = ctx.measureText(name).width;
    ctx.font = '600 ' + (fs - 2) + 'px Kanit, sans-serif';
    var cw = ctx.measureText(cnt).width + 12;
    var pad = 8, h = fs + 10;
    return { name: name, cnt: cnt, n: n, nw: nw, cw: cw, pad: pad, h: h, w: nw + pad * 2 + cw + 5 };
  }
  function renderLabel(b, fs) {
    var x0 = b.x, y0 = b.y, h = b.h, w = b.w;
    ctx.fillStyle = 'rgba(15,42,92,.06)'; roundRect(x0 + 1, y0 + 2, w, h, 7); ctx.fill();
    ctx.fillStyle = 'rgba(255,255,255,.8)'; roundRect(x0, y0, w, h, 7); ctx.fill();
    ctx.textBaseline = 'middle';
    ctx.font = '400 ' + fs + 'px Kanit, sans-serif';
    ctx.fillStyle = '#475467'; ctx.fillText(b.name, x0 + b.pad, y0 + h / 2 + 1);
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
  function bounds() {
    var xs = [], ys = [];
    BUILDINGS.concat([ROAD]).forEach(function (b) {
      [[b[0], b[1]], [b[0] + b[2], b[1]], [b[0], b[1] + b[3]], [b[0] + b[2], b[1] + b[3]]].forEach(function (c) {
        xs.push((c[0] - c[1]) * C30); ys.push((c[0] + c[1]) * S30);
      });
    });
    ys.push(-3 * ZK);
    return [Math.min.apply(0, xs), Math.min.apply(0, ys), Math.max.apply(0, xs), Math.max.apply(0, ys)];
  }
  function fit() {
    var mob = W < 900, b = bounds();
    var padL = mob ? 10 : 80, padR = mob ? 10 : 400, padT = mob ? 200 : 210, padB = mob ? 160 : 170;
    var aw = Math.max(100, W - padL - padR), ah = Math.max(100, H - padT - padB);
    cam.k = Math.max(3, Math.min(aw / (b[2] - b[0]), ah / (b[3] - b[1])));
    cam.ox = padL + (aw - (b[2] - b[0]) * cam.k) / 2 - b[0] * cam.k;
    cam.oy = padT + (ah - (b[3] - b[1]) * cam.k) / 2 - b[1] * cam.k;
  }
  function zoomAt(f, px, py) {
    var nk = Math.max(3, Math.min(60, cam.k * f));
    f = nk / cam.k;
    cam.ox = px - (px - cam.ox) * f;
    cam.oy = py - (py - cam.oy) * f;
    cam.k = nk;
  }
  function focusXY(x, y, k) {
    var target = Math.max(cam.k, k || (W < 900 ? 15 : 22));
    var from = { k: cam.k, ox: cam.ox, oy: cam.oy };
    var cx = W < 900 ? W / 2 : (W - 400) / 2 + 40, cy = H / 2 + 20;
    var sx = (x - y) * C30, sy = (x + y) * S30;
    var to = { k: target, ox: cx - sx * target, oy: cy - sy * target };
    var t0 = performance.now();
    (function anim() {
      var t = Math.min(1, (performance.now() - t0) / 450), e = t * (2 - t);
      cam.k = from.k + (to.k - from.k) * e;
      cam.ox = from.ox + (to.ox - from.ox) * e;
      cam.oy = from.oy + (to.oy - from.oy) * e;
      if (t < 1) requestAnimationFrame(anim);
    })();
  }
  function resize() {
    DPR = window.devicePixelRatio || 1;
    W = window.innerWidth; H = window.innerHeight;
    canvas.width = W * DPR; canvas.height = H * DPR;
    var r = tlc.getBoundingClientRect();
    tlc.width = r.width * DPR; tlc.height = r.height * DPR;
    fit();
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
    zoomAt(e.deltaY < 0 ? 1.12 : 1 / 1.12, e.clientX, e.clientY);
  }, { passive: false });
  $('zIn').onclick = function () { zoomAt(1.25, W / 2, H / 2); };
  $('zOut').onclick = function () { zoomAt(0.8, W / 2, H / 2); };
  $('zFit').onclick = fit;
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
    syncButtons();
  }
  function syncButtons() {
    var playing = state.playing && !state.live;
    $('btnPlay').innerHTML = '<i class="fa-solid fa-' + (playing ? 'pause' : 'play') + '"></i>';
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
      el.innerHTML = '<span class="n"></span><span class="v"></span><span class="trk"><i></i></span>';
      el.querySelector('.n').textContent = a.name;
      el.onclick = function () { focusXY(a.cx, a.cy); };
      box.appendChild(el);
      rowEls.push({ el: el, a: a });
    });
  }
  function updatePanel() {
    rowEls.forEach(function (r) {
      var a = r.a, el = r.el;
      var v;
      if (a.nowait) v = a.n + '/' + a.cap + ' ตรวจตามความเร่งด่วน · วันนี้ ' + servedAt(a, state.T) + ' ราย';
      else {
        v = (a.L.queue ? 'ยืนต่อแถว ' + a.n + ' คน' : a.n + '/' + a.cap) + ' รอ ~' + Math.round(a.avg) + ' น.';
        if (areas.some(function (x) { return x.L.wait === a.id; })) v += ' · พบแพทย์ ' + roomsIn(a) + ' · ตรวจแล้ว ' + roomsServed(a);
      }
      el.querySelector('.v').textContent = v;
      el.querySelector('.v').style.color = a.n && !a.nowait && a.lv !== 'ok' ? COL[a.lv] : '';
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
        'ตัวเลขเวลารออาจคลาดเคลื่อน — ย้ำการกดเวลาคัดกรองและเวลาแพทย์เริ่มตรวจใน HOSxP']);
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
      '<li><b>ฝั่ง Walk-in / ผู้ป่วยนัด</b> = มีนัดที่มาตามนัดใน visit นี้ (oapp.visit_vn) · ห้องตรวจ 1–5 walk-in, 6 เด็ก, 7–10 ผู้ป่วยนัด · ถ้า HOSxP ไม่บอกห้อง แพทย์แต่ละคนถูกจัดไว้ห้องเดิมทั้งวันตามรหัสแพทย์ (ประมาณ)</li>' +
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
            (j.is_today ? ' (รีเฟรชทุก 1 นาที)' : '') + ' · ลากกราฟเพื่อเลื่อนเวลา';
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
