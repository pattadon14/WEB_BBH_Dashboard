/* =========================================================
   BBH DASHBOARD - IPD WARD PATIENT FILTER / SORT
   เพิ่มตัวกรองและการเรียงลำดับผู้ป่วยในหน้า Ward Detail
========================================================= */
(function () {
    'use strict';

    function injectStyles() {
        if (document.getElementById('bbh-ipd-filter-style')) return;

        var style = document.createElement('style');
        style.id = 'bbh-ipd-filter-style';
        style.textContent = `
            .ipd-ward-page .ipd-patient-tools {
                display: flex;
                align-items: center;
                gap: 8px;
                flex-wrap: wrap;
                margin: 0 0 14px;
                padding: 10px 12px;
                border: 1px solid #dfe7e3;
                border-radius: 10px;
                background: #f8faf9;
            }

            .ipd-ward-page .ipd-filter-group {
                display: flex;
                align-items: center;
                gap: 6px;
                min-width: 0;
            }

            .ipd-ward-page .ipd-filter-label {
                color: #66727b;
                font-size: 14px;
                font-weight: 600;
                white-space: nowrap;
            }

            .ipd-ward-page .ipd-filter-input,
            .ipd-ward-page .ipd-filter-select {
                height: 38px;
                border: 1px solid #cfdad5;
                border-radius: 7px;
                background: #fff;
                color: #344047;
                font-size: 14px;
                padding: 6px 10px;
                outline: none;
                transition: border-color .15s ease, box-shadow .15s ease;
            }

            .ipd-ward-page .ipd-filter-input {
                width: 245px;
            }

            .ipd-ward-page .ipd-filter-select {
                min-width: 170px;
            }

            .ipd-ward-page .ipd-filter-input:focus,
            .ipd-ward-page .ipd-filter-select:focus {
                border-color: #198754;
                box-shadow: 0 0 0 2px rgba(25, 135, 84, .10);
            }

            .ipd-ward-page .ipd-filter-reset {
                height: 38px;
                padding: 6px 12px;
                border: 1px solid #198754;
                border-radius: 7px;
                background: #fff;
                color: #198754;
                font-size: 14px;
                font-weight: 600;
                cursor: pointer;
                transition: all .15s ease;
            }

            .ipd-ward-page .ipd-filter-reset:hover {
                background: #198754;
                color: #fff;
            }

            .ipd-ward-page .ipd-filter-result {
                margin-left: auto;
                color: #71808d;
                font-size: 14px;
                font-weight: 600;
                white-space: nowrap;
            }

            .ipd-ward-page .patient-col.ipd-filter-hidden {
                display: none !important;
            }

            @media (max-width: 991px) {
                .ipd-ward-page .ipd-filter-input {
                    width: 210px;
                }

                .ipd-ward-page .ipd-filter-result {
                    width: 100%;
                    margin-left: 0;
                }
            }

            @media (max-width: 575px) {
                .ipd-ward-page .ipd-patient-tools {
                    align-items: stretch;
                    flex-direction: column;
                }

                .ipd-ward-page .ipd-filter-group {
                    width: 100%;
                    align-items: stretch;
                    flex-direction: column;
                }

                .ipd-ward-page .ipd-filter-label {
                    font-size: 13px;
                }

                .ipd-ward-page .ipd-filter-input,
                .ipd-ward-page .ipd-filter-select,
                .ipd-ward-page .ipd-filter-reset {
                    width: 100%;
                    min-width: 0;
                }

                .ipd-ward-page .ipd-filter-result {
                    text-align: center;
                }
            }
        `;
        document.head.appendChild(style);
    }

    function parseThaiDate(text) {
        var match = String(text || '').match(/(\d{1,2})\/(\d{1,2})\/(\d{4})/);
        if (!match) return 0;

        var day = Number(match[1]);
        var month = Number(match[2]) - 1;
        var year = Number(match[3]);
        if (year > 2400) year -= 543;

        var date = new Date(year, month, day);
        return Number.isNaN(date.getTime()) ? 0 : date.getTime();
    }

    function parseStayDays(text) {
        var match = String(text || '').match(/([\d,]+)\s*วัน/);
        if (!match) return -1;
        return Number(match[1].replace(/,/g, '')) || 0;
    }

    function getCardMeta(col) {
        var card = col.querySelector('.patient-card');
        if (!card) return null;

        var text = card.textContent.replace(/\s+/g, ' ').trim();
        var meta = card.querySelector('.patient-meta');
        var admit = card.querySelector('.patient-info-row:nth-child(3)');
        var stay = card.querySelector('.patient-info-row:nth-child(2)');

        return {
            col: col,
            card: card,
            text: text.toLowerCase(),
            gender: meta ? meta.textContent.trim() : '',
            admitTime: admit ? parseThaiDate(admit.textContent) : 0,
            stayDays: stay ? parseStayDays(stay.textContent) : -1,
            name: (card.querySelector('.patient-name')?.textContent || '').trim()
        };
    }

    function createTools() {
        if (document.getElementById('ipd-patient-tools')) return document.getElementById('ipd-patient-tools');

        var head = document.querySelector('.ipd-ward-page .patient-section-head');
        if (!head) return null;

        /* ยืนยันว่าจำนวนผู้ป่วยที่ซ้ำกับ Overview ไม่แสดง */
        var countBadge = head.querySelector('.patient-count-badge');
        if (countBadge) countBadge.remove();

        var tools = document.createElement('div');
        tools.id = 'ipd-patient-tools';
        tools.className = 'ipd-patient-tools';
        tools.innerHTML = `
            <div class="ipd-filter-group">
                <label class="ipd-filter-label" for="ipd-patient-search">ค้นหา</label>
                <input id="ipd-patient-search" class="ipd-filter-input" type="search"
                    placeholder="ชื่อ / HN / AN / เตียง">
            </div>

            <div class="ipd-filter-group">
                <label class="ipd-filter-label" for="ipd-patient-gender">เพศ</label>
                <select id="ipd-patient-gender" class="ipd-filter-select">
                    <option value="all">ทั้งหมด</option>
                    <option value="male">ชาย</option>
                    <option value="female">หญิง</option>
                </select>
            </div>

            <div class="ipd-filter-group">
                <label class="ipd-filter-label" for="ipd-patient-stay">วันนอน</label>
                <select id="ipd-patient-stay" class="ipd-filter-select">
                    <option value="all">ทั้งหมด</option>
                    <option value="0-3">0–3 วัน</option>
                    <option value="4-7">4–7 วัน</option>
                    <option value="8-14">8–14 วัน</option>
                    <option value="15+">15 วันขึ้นไป</option>
                </select>
            </div>

            <div class="ipd-filter-group">
                <label class="ipd-filter-label" for="ipd-patient-sort">เรียงตาม</label>
                <select id="ipd-patient-sort" class="ipd-filter-select">
                    <option value="admit-desc">วันที่เข้า: ล่าสุด → เก่าสุด</option>
                    <option value="admit-asc">วันที่เข้า: เก่าสุด → ล่าสุด</option>
                    <option value="stay-desc">วันนอน: มาก → น้อย</option>
                    <option value="stay-asc">วันนอน: น้อย → มาก</option>
                    <option value="name-asc">ชื่อผู้ป่วย: ก → ฮ</option>
                </select>
            </div>

            <button type="button" class="ipd-filter-reset" id="ipd-patient-reset">
                <i class="fas fa-rotate-left mr-1"></i> รีเซ็ต
            </button>

            <div class="ipd-filter-result" id="ipd-filter-result">กำลังโหลด...</div>
        `;

        head.insertAdjacentElement('afterend', tools);
        return tools;
    }

    function applyFilters() {
        var grid = document.getElementById('patient-grid');
        var tools = document.getElementById('ipd-patient-tools');
        if (!grid || !tools) return;

        var search = (document.getElementById('ipd-patient-search')?.value || '').trim().toLowerCase();
        var gender = document.getElementById('ipd-patient-gender')?.value || 'all';
        var stayRange = document.getElementById('ipd-patient-stay')?.value || 'all';
        var sort = document.getElementById('ipd-patient-sort')?.value || 'admit-desc';
        var result = document.getElementById('ipd-filter-result');

        var cards = Array.from(grid.querySelectorAll(':scope > .patient-col'))
            .map(getCardMeta)
            .filter(Boolean);

        cards.sort(function (a, b) {
            if (sort === 'admit-asc') return a.admitTime - b.admitTime;
            if (sort === 'stay-desc') return b.stayDays - a.stayDays;
            if (sort === 'stay-asc') return a.stayDays - b.stayDays;
            if (sort === 'name-asc') return a.name.localeCompare(b.name, 'th');
            return b.admitTime - a.admitTime;
        });

        cards.forEach(function (item) {
            grid.appendChild(item.col);
        });

        var visible = 0;
        cards.forEach(function (item) {
            var textMatch = !search || item.text.indexOf(search) !== -1;
            var genderText = item.gender;
            var genderMatch = gender === 'all'
                || (gender === 'male' && /ชาย/.test(genderText) && !/หญิง/.test(genderText))
                || (gender === 'female' && /หญิง/.test(genderText));

            var stayMatch = true;
            if (stayRange !== 'all') {
                if (item.stayDays < 0) stayMatch = false;
                else if (stayRange === '0-3') stayMatch = item.stayDays >= 0 && item.stayDays <= 3;
                else if (stayRange === '4-7') stayMatch = item.stayDays >= 4 && item.stayDays <= 7;
                else if (stayRange === '8-14') stayMatch = item.stayDays >= 8 && item.stayDays <= 14;
                else if (stayRange === '15+') stayMatch = item.stayDays >= 15;
            }

            var show = textMatch && genderMatch && stayMatch;
            item.col.classList.toggle('ipd-filter-hidden', !show);
            if (show) visible++;
        });

        if (result) {
            result.textContent = 'แสดง ' + visible.toLocaleString('th-TH') + ' ราย';
        }

        var emptyFiltered = grid.querySelector('.ipd-filter-empty');
        if (!visible && cards.length) {
            if (!emptyFiltered) {
                emptyFiltered = document.createElement('div');
                emptyFiltered.className = 'col-12 ipd-filter-empty';
                emptyFiltered.innerHTML = '<div class="empty-state"><i class="fas fa-filter d-block"></i>ไม่พบผู้ป่วยตามเงื่อนไขที่เลือก</div>';
                grid.appendChild(emptyFiltered);
            }
        } else if (emptyFiltered) {
            emptyFiltered.remove();
        }
    }

    function init() {
        if (!document.getElementById('patient-grid')) return;

        injectStyles();
        createTools();

        var observer = new MutationObserver(function () {
            var grid = document.getElementById('patient-grid');
            if (!grid) return;
            var hasPatients = grid.querySelector('.patient-card');
            if (hasPatients) {
                observer.disconnect();
                applyFilters();
                setTimeout(function () {
                    observer.observe(grid, { childList: true });
                }, 0);
            }
        });

        var grid = document.getElementById('patient-grid');
        observer.observe(grid, { childList: true });

        ['ipd-patient-search', 'ipd-patient-gender', 'ipd-patient-stay', 'ipd-patient-sort'].forEach(function (id) {
            var el = document.getElementById(id);
            if (!el) return;
            el.addEventListener(el.tagName === 'INPUT' ? 'input' : 'change', applyFilters);
        });

        var reset = document.getElementById('ipd-patient-reset');
        if (reset) {
            reset.addEventListener('click', function () {
                document.getElementById('ipd-patient-search').value = '';
                document.getElementById('ipd-patient-gender').value = 'all';
                document.getElementById('ipd-patient-stay').value = 'all';
                document.getElementById('ipd-patient-sort').value = 'admit-desc';
                applyFilters();
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
