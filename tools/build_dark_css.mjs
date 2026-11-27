#!/usr/bin/env node
/*
 * สร้าง assets/css/dark.generated.css — โหมดมืดจากสีที่เขียนไว้ในโค้ด (ไม่ต้องแก้ CSS เดิมทีละจุด)
 *
 *   node tools/build_dark_css.mjs        (รันจากโฟลเดอร์ dashboardbbh)
 *
 * รันใหม่ทุกครั้งที่เพิ่ม/แก้สีใน CSS, <style> ใน PHP, style ที่ JS ใส่เอง หรือ style="color:…" ในโค้ด
 * ทุกกฎถูกครอบด้วย html[data-theme="dark"] → โหมดสว่างไม่เปลี่ยนเลย
 * ส่วนที่ต้องปรับด้วยมือ (token, Highcharts, จุดพิเศษ) อยู่ใน assets/css/dark.css ซึ่งโหลดถัดจากไฟล์นี้
 *
 * หลักการแปลงสี (OKLCH — คง hue เดิม):
 *   ตัวอักษร  : เข้ม/กลาง → สว่าง (L' = 0.97 - 0.32·L, อย่างน้อย 0.74) · สว่างอยู่แล้ว (L ≥ 0.75 เช่น ขาวบนปุ่ม) คงเดิม
 *   พื้นหลัง   : สว่าง (L ≥ 0.75) → มืดอมสีเดิม (L' = 0.20 + 0.45·(1-L), chroma × 0.55) · สีกลาง/เข้ม (ปุ่มเขียว) คงเดิม
 *   เส้นขอบ   : สว่าง → เทาอมสี L 0.34 · สีกลาง/เข้ม คงเดิม
 */
import { readFileSync, writeFileSync, readdirSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..');
const OUT = join(ROOT, 'assets/css/dark.generated.css');
const SKIP_CSS = new Set(['dark.css', 'dark.generated.css', 'opd_monitor.css', 'home.css']); // home.css มี token โหมดมืดเอง · monitor = หน้าแยก ไม่มี navbar/ปุ่มสลับธีม
const SCOPE = 'html[data-theme="dark"]';

/* ---------- สี ---------- */
const NAMED = { white: '#ffffff', black: '#000000' };
function parseColor(s) {
    s = s.trim().toLowerCase();
    if (NAMED[s]) s = NAMED[s];
    let m = /^#([0-9a-f]{3,8})$/.exec(s);
    if (m) {
        let h = m[1];
        if (h.length <= 4) h = [...h].map(c => c + c).join('');
        if (h.length !== 6 && h.length !== 8) return null;
        return { r: parseInt(h.slice(0, 2), 16) / 255, g: parseInt(h.slice(2, 4), 16) / 255, b: parseInt(h.slice(4, 6), 16) / 255, a: h.length === 8 ? parseInt(h.slice(6, 8), 16) / 255 : 1 };
    }
    m = /^rgba?\(([^)]+)\)$/.exec(s);
    if (m) {
        const raw = m[1].split(/[\s,\/]+/).filter(Boolean);
        const p = raw.map((v, i) => v.endsWith('%') ? parseFloat(v) / 100 * (i < 3 ? 255 : 1) : parseFloat(v));
        if (p.length < 3 || p.some(Number.isNaN)) return null;
        return { r: p[0] / 255, g: p[1] / 255, b: p[2] / 255, a: p.length > 3 ? p[3] : 1 };
    }
    return null;
}
const lin = v => v <= 0.04045 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4;
const gam = v => v <= 0.0031308 ? 12.92 * v : 1.055 * v ** (1 / 2.4) - 0.055;
function toOklch({ r, g, b }) {
    const [R, G, B] = [lin(r), lin(g), lin(b)];
    const l = Math.cbrt(0.4122214708 * R + 0.5363325363 * G + 0.0514459929 * B);
    const m = Math.cbrt(0.2119034982 * R + 0.6806995451 * G + 0.1073969566 * B);
    const s = Math.cbrt(0.0883024619 * R + 0.2817188376 * G + 0.6299787005 * B);
    const L = 0.2104542553 * l + 0.7936177850 * m - 0.0040720468 * s;
    const a = 1.9779984951 * l - 2.4285922050 * m + 0.4505937099 * s;
    const bb = 0.0259040371 * l + 0.7827717662 * m - 0.8086757660 * s;
    return { L, C: Math.hypot(a, bb), H: Math.atan2(bb, a) };
}
function fromOklch({ L, C, H }) {
    for (let c = C; ; c *= 0.9) {          // ลด chroma จนอยู่ใน sRGB
        const a = c * Math.cos(H), b = c * Math.sin(H);
        const l = (L + 0.3963377774 * a + 0.2158037573 * b) ** 3;
        const m = (L - 0.1055613458 * a - 0.0638541728 * b) ** 3;
        const s = (L - 0.0894841775 * a - 1.2914855480 * b) ** 3;
        const rgb = [4.0767416621 * l - 3.3077115913 * m + 0.2309699292 * s,
            -1.2684380046 * l + 2.6097574011 * m - 0.3413193965 * s,
            -0.0041960863 * l - 0.7034186147 * m + 1.7076147010 * s];
        if (rgb.every(v => v >= -1e-4 && v <= 1.0001) || c < 1e-4) return rgb.map(v => gam(Math.min(1, Math.max(0, v))));
    }
}
function fmt(rgb, a) {
    const h = rgb.map(v => Math.round(v * 255).toString(16).padStart(2, '0')).join('');
    return a < 1 ? `rgba(${rgb.map(v => Math.round(v * 255)).join(', ')}, ${+a.toFixed(3)})` : '#' + h;
}
function mapColor(str, role) {
    const c = parseColor(str);
    if (!c) return null;
    const o = toOklch(c);
    let L = o.L, C = o.C;
    if (role === 'text') {
        if (L >= 0.75) return null;
        L = Math.max(0.74, 0.97 - 0.32 * L);
    } else if (role === 'bg') {
        if (L < 0.75) return null;
        // พื้นโปร่ง (alpha ต่ำ) บนพื้นมืด: คงความโปร่ง ใช้สีมืดอมสีเดิม
        L = 0.20 + 0.45 * (1 - L);
        C = C * 0.55;
    } else if (role === 'border') {
        if (L < 0.75) return null;
        L = 0.34;
        C = Math.min(C, 0.03);
    } else return null;
    return fmt(fromOklch({ L, C, H: o.H }), c.a);
}
const COLOR_RE = /#[0-9a-fA-F]{3,8}\b|rgba?\([^)]*\)|\b(?:white|black)\b/g;

function roleOf(prop) {
    prop = prop.toLowerCase();
    if (/^(color|fill|caret-color|-webkit-text-fill-color|accent-color)$/.test(prop)) return 'text';
    if (/^background/.test(prop)) return 'bg';
    if (/^(border|outline|stroke|column-rule|text-decoration-color)/.test(prop)) return 'border';
    if (prop.startsWith('--')) {
        if (/(text|ink|muted|fg|title|label)/.test(prop)) return 'text';
        if (/(bg|surface|soft|wash|tint|-50$|-100$|light)/.test(prop)) return 'bg';
        if (/(border|line|rule|grid)/.test(prop)) return 'border';
    }
    return null;
}
// คืนค่าเมื่อเป็น property สี — รวมค่าที่ไม่ต้องเปลี่ยนด้วย (เช่น ขาวบนปุ่มที่เลือก)
// เพื่อให้กฎของสถานะ (:hover, [aria-pressed], .active) ยังชนะกฎทั่วไปเหมือนโหมดสว่าง
function convertValue(prop, value, { keepUnchanged = true } = {}) {
    const role = roleOf(prop);
    if (!role) return null;
    let changed = false;
    const out = value.replace(COLOR_RE, m => { const r = mapColor(m, role); if (r) { changed = true; return r; } return m; });
    if (changed) return out;
    return keepUnchanged && COLOR_RE.test(value) && !/var\(/.test(value) ? out : null;
}

/* ---------- แยก CSS (ไม่ต้องใช้ library) ---------- */
function parse(css) {
    css = css.replace(/\/\*[\s\S]*?\*\//g, '');
    let i = 0;
    function block() {
        const nodes = [];
        while (i < css.length) {
            const close = css.indexOf('}', i), open = css.indexOf('{', i);
            if (close !== -1 && (open === -1 || close < open)) { i = close + 1; return nodes; }
            if (open === -1) { i = css.length; break; }
            const head = css.slice(i, open).trim(); i = open + 1;
            if (head.startsWith('@')) {
                const kids = block();
                if (/^@(media|supports|container)/.test(head)) nodes.push({ at: head.replace(/\s+/g, ' '), kids });
                continue;                                   // @keyframes / @font-face: ข้าม
            }
            const end = css.indexOf('}', i);
            nodes.push({ sel: head.replace(/\s+/g, ' '), body: css.slice(i, end) });
            i = end + 1;
        }
        return nodes;
    }
    return block();
}
function scopeSel(sel) {
    return sel.split(/,(?![^(]*\))/).map(s => {
        s = s.trim();
        if (!s) return s;
        if (/^:root\b/.test(s)) return SCOPE + s.slice(5);
        if (/^html\b/.test(s)) return SCOPE + s.slice(4);
        return `${SCOPE} ${s}`;
    }).join(',\n');
}
function emit(nodes, indent = '') {
    let out = '';
    for (const n of nodes) {
        if (n.at) { const inner = emit(n.kids, indent + '    '); if (inner) out += `${indent}${n.at} {\n${inner}${indent}}\n`; continue; }
        const decls = [];
        for (const d of n.body.split(/;(?![^(]*\))/)) {
            const k = d.indexOf(':'); if (k === -1) continue;
            const prop = d.slice(0, k).trim(), val = d.slice(k + 1).trim();
            const v = convertValue(prop, val);
            if (v) decls.push(`${prop}: ${v}`);
        }
        if (decls.length) out += `${indent}${scopeSel(n.sel)} {\n${decls.map(d => `${indent}    ${d};`).join('\n')}\n${indent}}\n`;
    }
    return out;
}

/* ---------- แหล่ง CSS ---------- */
const sources = [];
for (const f of readdirSync(join(ROOT, 'assets/css')).filter(f => f.endsWith('.css') && !SKIP_CSS.has(f)).sort())
    sources.push([`assets/css/${f}`, readFileSync(join(ROOT, 'assets/css', f), 'utf8')]);

const codeFiles = [
    'templates/header.php', 'index.php',
    ...readdirSync(join(ROOT, 'pages')).filter(f => f.endsWith('.php') && f !== 'opd_monitor.php').map(f => 'pages/' + f),
    ...readdirSync(join(ROOT, 'assets/js')).filter(f => f.endsWith('.js') && f !== 'opd_monitor.js').map(f => 'assets/js/' + f),
];
const inline = new Map();   // style="…" ที่มีสี → กฎ [style*="…"]
for (const f of codeFiles) {
    const src = readFileSync(join(ROOT, f), 'utf8');
    for (const m of src.matchAll(/<style[^>]*>([\s\S]*?)<\/style>/g)) sources.push([`${f} <style>`, m[1]]);
    for (const m of src.matchAll(/(?:textContent|innerHTML)\s*=\s*`([\s\S]*?)`/g))   // style ที่ JS ใส่เอง
    {
        const css = m[1].replace(/\/\*[\s\S]*?\*\//g, '');
        if (/\{[^}]*:/.test(css) && !/<\w/.test(css.slice(0, 200))) sources.push([`${f} (js style)`, css.replace(/\$\{[^}]*\}/g, '0')]);
    }
    for (const m of src.matchAll(/style="([^"]*)"/g)) {
        for (const d of m[1].split(';')) {
            const k = d.indexOf(':'); if (k === -1) continue;
            const prop = d.slice(0, k).trim(), val = d.slice(k + 1).trim();
            if (val.includes('${') || !/^(color|background|background-color)$/i.test(prop)) continue;
            const v = convertValue(prop, val, { keepUnchanged: false });
            if (v) inline.set(`[style*="${prop}:${val}"],\n${SCOPE} [style*="${prop}: ${val}"]`, `${prop}: ${v} !important`);
        }
    }
}

let out = `/* ไฟล์สร้างอัตโนมัติจาก tools/build_dark_css.mjs — ห้ามแก้ตรง ๆ (แก้ใน dark.css แทน)\n * แหล่ง: ${sources.length} ชุด CSS + style="…" ${inline.size} แบบ */\n\n`;
for (const [name, css] of sources) {
    const body = emit(parse(css));
    if (body) out += `/* ---- ${name} ---- */\n${body}\n`;
}
if (inline.size) {
    out += '/* ---- style="…" ในโค้ด (ต้อง !important จึงชนะ inline) ---- */\n';
    for (const [sel, decl] of inline) out += `${SCOPE} ${sel} {\n    ${decl};\n}\n`;
}
writeFileSync(OUT, out.replace(/\n/g, '\r\n'));
console.log(`wrote ${OUT} — ${sources.length} sources, ${inline.size} inline styles, ${out.split('\n').length} lines`);
