/* ปุ่ม (i) ข้างหัวข้อหน้า ER → หน้าต่างอธิบายที่มาของข้อมูล: ตาราง HOSxP, เงื่อนไข, นิยาม/ตัวชี้วัด, ข้อจำกัด, แนวคิด
 * ปุ่ม: <button type="button" class="er-info-btn" data-info="<key>" aria-label="..."> ในหัวข้อ (pages/ER_detail.php)
 * เนื้อหาเขียนตามโค้ดจริง — ถ้าแก้ SQL/นิยามใน config/er.php หรือ api/er_*.php ให้แก้ข้อความที่นี่ด้วย
 * ข้อความคงที่ทั้งหมด (ไม่มีค่าจากผู้ใช้/ฐานข้อมูล) จึงใส่เป็น HTML ได้ */
(function () {
    'use strict';

    const T = s => `<code>${s}</code>`;   // ชื่อตาราง/คอลัมน์

    // ป้ายหัวข้อย่อยของทุกหน้าต่าง (เรียงตามนี้ ไม่มีข้อมูลก็ข้าม)
    const LABELS = {
        src:   ['database', 'ที่มาของข้อมูล'],
        cond:  ['filter', 'เงื่อนไขการนับ'],
        def:   ['ruler', 'นิยามและตัวชี้วัด'],
        limit: ['triangle-exclamation', 'ข้อจำกัดที่ควรรู้'],
        idea:  ['lightbulb', 'แนวคิด']
    };

    const SCREENED = `ER คัดกรองแล้ว = มีเวลาเข้า ER (${T('enter_er_time')}) มีระดับความเร่งด่วน และมีประเภทผู้ป่วย`;
    const LEVEL = `ระดับ = ${T('er_regist.er_emergency_level_id')} ถ้ามี ถ้าไม่มีใช้ ${T('er_emergency_type.export_code')} ของประเภทที่เลือก
        (รหัสประเภทของบ้านบึงไม่เรียงตามความรุนแรง เช่น 1 = Emergency, 2 = Resuscitation จึงต้องแปลงผ่าน export_code)
       , ประเภท 6 = Black (เสียชีวิต), ไม่มีทั้งสองค่า = ไม่ระบุระดับ`;
    const DCH = `ผลจำหน่าย (เรียงตามลำดับการตัดสิน): <b>Admit</b> = ${T('er_dch_type')} 3 หรือมีรายการ Admit ใน ${T('ipt')} ของ visit นี้
        → <b>Refer</b> = ${T('er_dch_type')} 2 หรือมีใบส่งต่อ ${T('referout')} ที่ออกจากจุด ER
        → นอกนั้นตาม ${T('er_dch_type')}: 1 กลับบ้าน, 6 ไม่รอตรวจ, 7 หนีกลับ, 8 ปฏิเสธการรักษา, ว่าง = ยังไม่ลงผล,
        ที่เหลือ (5 สังเกตอาการ, 4/9/10 เสียชีวิต, รหัสอื่น) = ผลจำหน่ายอื่น ๆ`;
    const PT = `ประเภทผู้ป่วย ${T('er_regist.er_pt_type')}: 1 ฉุกเฉิน (ไม่ใช่อุบัติเหตุ), 2 อุบัติเหตุ, 3 ตรวจโรคทั่วไป,
        4 บริการอื่น (ทำแผล ฉีดยา ใบรับรองแพทย์), 5 ห้องทำแผลเวรเช้า`;
    const SHIFT = `เวรตามชั่วโมงของเวลาเข้า ER (${T('enter_er_time')}): ดึก 00:00–07:59, เช้า 08:00–15:59, บ่าย 16:00–23:59`;
    const D2D = `Door-to-Doctor = นาทีจากเข้า ER ถึง "การกระทำแรกของแพทย์" ใช้เวลาที่เร็วที่สุดของ
        กดเริ่มตรวจ (${T('service_time.service2')}), สั่ง Lab (${T('lab_head')}), สั่ง X-ray (${T('xray_report')}),
        สั่งยา (${T('opitemrece')} + ${T('drugitems')}) และเวลาที่พยาบาลจดใน HPI (${T('opdscreen.hpi')}) ว่ารายงาน/พบแพทย์
       , แต่ละแหล่งนับเฉพาะ 0–240 นาทีหลังเข้า ER`;
    const REFER_IN = `Refer in = วิธีมา ${T('ovst.ovstist')} 04 (รับส่งต่อ) หรือมีใบรับส่งต่อ ${T('referin')} ของ visit นี้`;
    const ACCIDENT_GAP = `"อุบัติเหตุ" นับตามประเภทที่ ER เลือก (${T('er_pt_type')} = 2) ไม่ใช่ตามการวินิจฉัย
       , ข้อมูล 1 ต.ค.–18 พ.ย. 2569 มีอีกราว 630 ครั้งที่การวินิจฉัยหลักเป็นการบาดเจ็บ (รหัส S/T) แต่ลงประเภทอื่น
        ต้นแบบ er-dashboard นับตามรหัสการวินิจฉัย S/T/V จึงได้ตัวเลขสูงกว่าราว 18%`;
    const PERIOD = `ตามช่วงเวลาที่เลือกด้านบน (วันนี้ / เดือนนี้ / ปีงบประมาณ ต.ค.–ก.ย. / กำหนดเอง ไม่เกิน 400 วัน)
        นับตามวันที่มารับบริการ (${T('er_regist.vstdate')})`;

    // หน้าอื่น (เช่น OPD) ใช้หน้าต่างเดียวกันโดยตั้ง window.BBH_INFO = { key: { title, src, cond, def, limit, idea } } ก่อนโหลดไฟล์นี้
    const INFO = window.BBH_INFO || {
        mast: {
            title: 'ผู้มารับบริการ ER (หัวหน้า)',
            src: [`${T('er_regist')} ทะเบียนผู้ป่วย ER ของ HOSxP (1 แถว = 1 visit ที่ผ่าน ER)`],
            cond: ['นับทุก visit ของวันที่เลือก',
                   'เทียบกับวันเดียวกันของสัปดาห์ก่อน ถ้าดูวันนี้จะนับถึงเวลาเดียวกัน เพื่อให้เทียบกันได้ระหว่างวัน'],
            limit: ['ดูวันนี้: ตัวเลขรีเฟรชทุก 60 วินาที และยังเพิ่มขึ้นจนจบวัน'],
            idea: ['ตอบคำถามแรกของหัวหน้า ER: วันนี้คนมากหรือน้อยกว่าปกติ']
        },
        sec1: {
            title: '1 ภาพรวม ER',
            src: [`${T('er_regist')} (visit ER, เวลาเข้า/ออก ER, ระดับ, ประเภท)`,
                  `${T('ovst')} (วิธีมา ${T('ovstist')}, แผนกปัจจุบัน ${T('cur_dep')})`,
                  `เวลาพบแพทย์: ${T('service_time')}, ${T('lab_head')}, ${T('xray_report')}, ${T('opitemrece')}, ${T('opdscreen')}`],
            cond: ['ผู้มารับบริการ = ทุก visit ของวันที่เลือก นับเป็นครั้ง และนับคนจาก HN ไม่ซ้ำ',
                   `EMS นำส่ง = วิธีมา ${T('ovstist')} 09 FR, 10 BLS, 11 ALS`,
                   `ช่อง "ตอนนี้" (แสดงเฉพาะเมื่อดูวันนี้) = ยังไม่มีเวลาออกจาก ER (${T('leave_datetime')} ว่าง)
                    และแผนกปัจจุบันเป็น ER (011, 040, 065) และเข้า ER ภายใน 24 ชม.`],
            def: ['ระดับ 1–2 ตอนนี้: ' + LEVEL,
                  'ยังไม่พบแพทย์ = ยังไม่มีเวลาแพทย์จากแหล่งใดเลย ตามนิยาม Door-to-Doctor (ส่วนที่ 2)',
                  'อยู่เกิน 4 ชม. / อยู่นานสุด = เวลาตั้งแต่เข้า ER ถึงตอนนี้'],
            limit: ['ลืมลงเวลาออกจาก ER แต่ย้ายแผนกแล้ว = ไม่นับว่าอยู่ใน ER',
                    'ไม่ย้ายแผนกและไม่ลงเวลาออก = ค้างในช่อง "ตอนนี้" จนครบ 24 ชม.',
                    '"ยังไม่พบแพทย์" อาจพบแพทย์แล้วแต่ยังไม่มีการกด/สั่งใด ๆ ในระบบ'],
            idea: ['ดูภาระงานและความเสี่ยงใน ER ขณะนี้ เพื่อจัดกำลังคนและเร่งรายที่รอนาน', 'คลิกช่องเพื่อดูรายชื่อ (ต้อง login)']
        },
        d2d: {
            title: '2 Door-to-Doctor',
            src: [`${T('er_regist.enter_er_time')} เวลาเข้า ER`, `${T('service_time')}, ${T('lab_head')}, ${T('xray_report')}, ${T('opitemrece')} + ${T('drugitems')}, ${T('opdscreen.hpi')}`],
            cond: [`เฉพาะ visit ที่ ${SCREENED}`, 'แยกระดับ 1–5 (ไม่รวม Black)'],
            def: [D2D, LEVEL,
                  'ค่าเฉลี่ย = เฉลี่ยนาทีของรายที่มีเวลาพบแพทย์',
                  'ทันเป้า = เวลา ≤ เป้าของระดับ: ระดับ 1 ทันที (≤ 5 นาที), 2 ≤ 10, 3 ≤ 30, 4 ≤ 60, 5 ≤ 120 นาที'],
            limit: [`ไม่ใช้ ${T('door_to_doctor_second')} ของ HOSxP เพราะลงไว้แค่ราว 17% ของ visit`,
                    'เวลาสั่ง Lab / ยา อาจช้ากว่าเวลาพบแพทย์จริง ค่าที่ได้จึงอาจสูงกว่าจริงเล็กน้อย',
                    'รายที่ไม่มีเวลาแพทย์เลยไม่นำมาเฉลี่ย ดูจำนวนในบรรทัด "มีเวลาพบแพทย์ x จาก y ราย"'],
            idea: ['นิยามเดียวกับต้นแบบ er-dashboard (รพ.วังน้ำเขียว) และตรวจเทียบตัวเลขแล้ว', 'คลิกแถวเพื่อดูรายชื่อระดับนั้น']
        },
        los: {
            title: '3 Length of Stay',
            src: [`${T('er_regist')} (${T('enter_er_time')}, ${T('leave_datetime')})`, `${T('ipt')} เวลาลงทะเบียน Admit (${T('regdate')} + ${T('regtime')})`, `${T('referout')} เวลาส่งต่อ (${T('refer_date')} + ${T('refer_time')})`],
            cond: [`เฉพาะ visit ที่ ${SCREENED}`, 'LOS ไม่นับงานบริการ (ประเภท 4, 5 ทำแผล ฉีดยา)',
                   'นับเฉพาะค่า 0–1,440 นาที (ติดลบหรือเกิน 1 วัน = บันทึกผิด)'],
            def: ['LOS = เวลาออกจาก ER − เวลาเข้า ER, แสดงค่าเฉลี่ย มัธยฐาน และจำนวนที่เกิน 4 / 6 ชม.',
                  'เข้า ER ถึง Admit = ถึงเวลาลงทะเบียน Admit, เข้า ER ถึง Refer = ถึงเวลาใบส่งต่อใบแรก (ผลจำหน่าย = Refer)',
                  'รอเกินเกณฑ์ = เกิน 120 นาที (เกณฑ์ boarding ชุดเดียวกับต้นแบบ)'],
            limit: [`${T('leave_datetime')} ลงไว้ราว 89% ของ visit, รายที่ไม่มีไม่นำมาคิด`,
                    'เวลาลงทะเบียน Admit อาจไม่ใช่เวลาที่ผู้ป่วยออกจาก ER จริง (รอเตียง / รอย้าย)',
                    'แถว Admit / Refer นับเฉพาะรายที่มีเวลา แต่รายชื่อที่คลิกแสดงทุกรายของวัน'],
            idea: ['ER แออัดเมื่อผู้ป่วยค้างนาน โดยเฉพาะรายที่รอ Admit / Refer']
        },
        sec4: {
            title: '4 ระดับความเร่งด่วน',
            src: [`${T('er_regist')} + ${T('er_emergency_type')}`, `${T('ovst.ovstist')}, ${T('referin')}, ${T('referout')} (การส่งต่อ)`],
            cond: ['ทุก visit ของวันที่เลือก (รวม Black และไม่ระบุระดับ) แถบสีรวมได้ยอดทั้งหมด'],
            def: [LEVEL, REFER_IN,
                  'Refer out = ผลจำหน่าย Refer (ดูส่วนที่ 7), Refer รวม = รับเข้าหรือส่งออก visit ที่มีทั้งสองนับครั้งเดียว'],
            limit: [`${T('er_emergency_level_id')} ว่างราว 20% จึงต้องใช้ export_code ของประเภทแทน (ลงไว้ 99%)`,
                    'Refer in ของหน้านี้สูงกว่าต้นแบบ เพราะต้นแบบนับเฉพาะวิธีมา 04 แต่บางรายมีใบรับส่งต่อโดยลงวิธีมาอื่น (เช่น ALS)'],
            idea: ['สัดส่วนระดับ 1–2 บอกความหนักของงานได้ดีกว่ายอดรวม']
        },
        sec5: {
            title: '5 ประเภทผู้ป่วย',
            src: [`${T('er_regist.er_pt_type')} (ชื่อจากตาราง ${T('er_pt_type')} ย่อให้อ่านง่าย)`],
            cond: ['ทุก visit ของวันที่เลือก, การ์ดรวมประเภท 4 และ 5 เป็น "รับบริการอื่น"'],
            def: [PT],
            limit: ['เป็นประเภทที่เจ้าหน้าที่เลือกตอนลงทะเบียน ไม่ได้มาจากการวินิจฉัย มีบางรายลงไม่ตรง เช่น "ตรวจโรคทั่วไป" แต่ระดับ 1–2', ACCIDENT_GAP],
            idea: ['แยกงานฉุกเฉินจริงออกจากงานตรวจทั่วไปและงานบริการที่มาใช้ ER']
        },
        sec6: {
            title: '6 แยกตามเวร',
            src: [`${T('er_regist.enter_er_time')}`],
            cond: ['ทุก visit ของวันที่เลือก'],
            def: [SHIFT],
            limit: ['ใช้เวลาเข้า ER ไม่ใช่เวลาพบแพทย์, ต้นแบบใช้ขอบเวร 08:01 / 16:01 ต่างกันไม่เกิน 1 นาที'],
            idea: ['ดูว่าเวรไหนรับภาระมาก เพื่อจัดอัตรากำลัง']
        },
        sec7: {
            title: '7 ผลการจำหน่าย',
            src: [`${T('er_regist.er_dch_type')} (${T('er_dch_type')})`, `${T('ipt')} (Admit), ${T('referout')} (ส่งต่อ)`],
            cond: ['ทุก visit ของวันที่เลือก, 6 แถวรวมกันได้ยอดทั้งหมด (แต่ละ visit อยู่แถวเดียว)'],
            def: [DCH],
            limit: [`${T('er_dch_type')} ว่างราว 46% และลง Admit ไว้แค่ราวครึ่งของที่ Admit จริง จึงเติมจาก ${T('ipt')} / ${T('referout')}, "ยังไม่ลงผล" จึงยังสูง`],
            idea: ['ใช้ข้อมูลที่เกิดขึ้นจริงในระบบ (Admit, ใบส่งต่อ) ก่อนค่าที่ ER เลือก เพื่อไม่ให้นับขาด']
        },
        stats: {
            title: '8 สถิติงานอุบัติเหตุและฉุกเฉิน',
            src: [`${T('er_regist')} เป็นหลัก ร่วมกับ ${T('ovst')}, ${T('ipt')}, ${T('referout')}, ${T('referin')}, ${T('vn_stat')}, ${T('icd101')}, ${T('patient')}`],
            cond: [PERIOD, 'ช่วงไม่เกิน 62 วันแสดงรายวัน ยาวกว่านั้นแสดงรายเดือน',
                   'ทุกกราฟนับเป็นครั้ง (visit) และยอดรวมของทุกกราฟเท่ากัน'],
            limit: ['ข้อมูลเก็บสำเนาไว้ 5 นาที (10 อันดับโรค 30 นาที) ตัวเลขล่าสุดอาจช้าได้ไม่เกินนั้น'],
            idea: ['ดูแนวโน้มและสัดส่วนงาน ER ระยะยาว', 'คลิกแท่งกราฟ แถวการส่งต่อ หรือแถวในตาราง เพื่อดูรายชื่อ (ต้อง login)']
        },
        trend: {
            title: 'จำนวนผู้รับบริการ ER',
            src: [`${T('er_regist')} (${T('vstdate')}, ${T('er_pt_type')})`],
            cond: [PERIOD],
            def: ['ความสูงแท่ง = ผู้รับบริการ ER ทั้งหมด, ส่วนสีส้ม = อุบัติเหตุ (ประเภท 2), ส่วนสีแดง = ผู้ป่วยอื่น'],
            limit: [ACCIDENT_GAP],
            idea: ['แท่งซ้อนกันเพราะอุบัติเหตุเป็นส่วนหนึ่งของทั้งหมด (วางคู่กันจะชวนให้บวกกัน)']
        },
        triage: {
            title: 'ระดับความเร่งด่วน (Triage)',
            src: [`${T('er_regist')} + ${T('er_emergency_type')}`],
            cond: [PERIOD, 'แสดง Black และไม่ระบุระดับเป็นแท่งของตัวเอง (ต้นแบบรวม Black ไว้ในระดับ 5)'],
            def: [LEVEL, 'สีตามป้ายจำแนกผู้ป่วยหน้าห้อง ER (แดง ชมพู เหลือง เขียว ขาว)'],
            idea: ['ดูว่า ER รับผู้ป่วยหนักมากน้อยเพียงใดในช่วงนั้น']
        },
        type: {
            title: 'ประเภทผู้ป่วย',
            src: [`${T('er_regist.er_pt_type')}, ${T('ipt')} (Admit), ${T('er_emergency_type')} (ระดับ)`],
            cond: [PERIOD],
            def: [PT, 'ชี้ที่แท่งเพื่อดูจำนวนระดับ 1–2 และ Admit (มีรายการใน ipt) ของแต่ละประเภท'],
            limit: ['ประเภทเลือกตอนลงทะเบียน ไม่ได้มาจากการวินิจฉัย', ACCIDENT_GAP],
            idea: ['เปลี่ยนจากโดนัทเป็นแท่ง เพราะเทียบความยาวแท่งได้แม่นกว่าเทียบขนาดชิ้น']
        },
        shift: {
            title: 'แยกตามเวร',
            src: [`${T('er_regist.enter_er_time')}`],
            cond: [PERIOD],
            def: [SHIFT],
            idea: ['ภาระงานตามเวรในระยะยาว (ส่วนที่ 6 คือของวันเดียว)']
        },
        refer: {
            title: 'การส่งต่อผู้ป่วย (Refer)',
            src: [`${T('referout')} ใบส่งต่อ (ปลายทาง ${T('refer_hospcode')}, เหตุผล ${T('rfrcs')})`,
                  `${T('referin')} ใบรับส่งต่อ (ต้นทาง ${T('refer_hospcode')})`, `${T('ipt')} (Admit, แบบจำหน่าย ${T('dchtype')})`,
                  `${T('hospcode')} ชื่อสถานพยาบาล, ${T('opdconfig')} รหัส รพ.บ้านบึง`],
            cond: [PERIOD, '1 visit นับ 1 ครั้ง แม้มีใบส่งต่อหลายใบ, ไม่นับใบที่ยกเลิก'],
            def: ['<b>ส่งต่อออกจาก ER</b> = ผลจำหน่าย Refer (ส่วนที่ 7), ปลายทางใช้ใบที่ออกจากจุด ER ก่อน',
                  '<b>Admit แล้วส่งต่อ</b> = มาทาง ER แล้ว Admit และจำหน่ายแบบ By Transfer (dchtype 04) หรือมีใบส่งต่อจากหอผู้ป่วย/จุดอื่นที่ไม่ใช่ ER (ไม่นับใบ "Follow up ตามนัด"), ไม่ซ้ำกับส่งต่อออกจาก ER',
                  '<b>รับส่งต่อเข้า ER</b> = ' + REFER_IN,
                  'ชื่อ รพ. ย่อ และรวมรหัสต่างกันที่เป็นแห่งเดียวกัน, แสดง 6 แห่งแรก ที่เหลือรวมเป็น "อื่น ๆ"'],
            limit: ['ลงผลจำหน่าย Refer แต่ไม่ได้ออกใบส่งต่อ = ไม่ทราบปลายทาง (แสดงเป็นแถวแยก)',
                    'ลงวิธีมารับส่งต่อแต่ไม่มีใบรับส่งต่อ = ไม่ทราบต้นทาง, บางรายลงต้นทางเป็น รพ.บ้านบึงเอง (แสดงคำเตือน)',
                    'Admit แล้วส่งต่อ: ราวครึ่งหนึ่งลงแบบจำหน่ายอื่น (เช่น With Approval) ทั้งที่มีใบส่งต่อ จึงนับจากใบส่งต่อด้วย'],
            idea: ['ดูว่าส่งต่อไปที่ไหนมาก ด้วยเหตุผลอะไร และงานส่งต่อทั้งจาก ER และจากหอผู้ป่วย', 'คลิกชื่อ รพ. เหตุผล หรือหัวกล่องเพื่อดูรายชื่อ']
        },
        top10: {
            title: '10 อันดับโรค/การวินิจฉัย',
            src: [`${T('vn_stat.pdx')} การวินิจฉัยหลักของ visit, ${T('icd101')} ชื่อโรค, ${T('patient.sex')} เพศ`],
            cond: [PERIOD, 'ไม่นับงานบริการ (ประเภท 4, 5) และการวินิจฉัยรหัส Z (ทำแผล ตัดไหม วัคซีน ติดตามอาการ)',
                   'ไม่นับ visit ที่ยังไม่มีการวินิจฉัยหลัก'],
            def: ['นับเป็นครั้ง (visit) ตามรหัส ICD-10 ของการวินิจฉัยหลัก แยกชาย / หญิง'],
            limit: ['ใช้เฉพาะการวินิจฉัยหลัก โรคร่วมไม่ถูกนับ, รหัสใกล้เคียงกัน (เช่น S06.0 กับ S06.00) แยกอันดับกัน'],
            idea: ['นิยามเดียวกับต้นแบบ er-dashboard', 'คลิกแถวเพื่อดูรายชื่อ']
        }
    };

    // คำอธิบายชื่อตาราง/คอลัมน์/รหัสของ HOSxP — ใส่ต่อท้าย <code> ครั้งแรกที่พบในแต่ละหน้าต่าง ทุกหน้าที่ใช้หน้าต่างนี้
    // ไม่พบชื่อเต็ม (ตาราง.คอลัมน์) → ใช้คำอธิบายของคอลัมน์ · ชื่อที่ไม่มีในรายการแสดงตามเดิม
    const GLOSS = {
        ovst: 'ข้อมูลการมารับบริการผู้ป่วยนอก 1 แถว = 1 visit', vstdate: 'วันที่มารับบริการ', 'ovst.vsttime': 'เวลาลงทะเบียน',
        an: 'เลขผู้ป่วยใน มีค่า = Admit', main_dep: 'แผนกหลักของ visit', cur_dep: 'แผนกที่ผู้ป่วยอยู่ล่าสุด',
        ovstist: 'วิธีมารับบริการ เช่น มาเอง มาตามนัด รับส่งต่อ', pttype: 'สิทธิการรักษา',
        'pttype.hipdata_code': 'รหัสกลุ่มสิทธิมาตรฐาน', pcode: 'รหัสกลุ่มสิทธิ เช่น UC เบิกได้',
        ovst_service_time: 'เวลาแต่ละขั้นบริการของ visit เช่น ซักประวัติ พบแพทย์ ห้องยา', depcode: 'รหัสแผนก',
        'OPD-SCREEN': 'ขั้นซักประวัติ', 'OPD-DOCTOR': 'ขั้นแพทย์ตรวจ', 'OPD-RX-ENTRY': 'ห้องยารับใบสั่งยา',
        'OPD-RX-DISPENSE': 'จ่ายยาให้ผู้ป่วย', begin_time: 'เวลาเริ่มขั้นบริการ',
        opd_dep_queue: 'บันทึกการส่งผู้ป่วยไปแผนก เวลาส่ง / รับคิว / เสร็จ', kskdepartment: 'รายชื่อแผนก / จุดบริการ',
        service_time: 'เวลาบริการแต่ละจุดของ visit (จุดละครั้ง)', 'service_time.service2': 'เวลาแพทย์กดเริ่มตรวจ',
        oapp: 'ใบนัดหมาย', visit_vn: 'visit ที่มาตามนัดใบนี้', 'oapp.clinic': 'คลินิกที่นัด', 'oapp.depcode': 'แผนกที่นัด',
        nexttime: 'เวลานัด', nextdate: 'วันนัด', clinic: 'รายชื่อคลินิก',
        patient: 'ทะเบียนผู้ป่วยของ รพ.', sex: 'เพศ', birthday: 'วันเกิด', 'patient.death': 'สถานะเสียชีวิต',
        firstday: 'วันที่มา รพ. ครั้งแรก', nationality: 'สัญชาติ',
        er_regist: 'ทะเบียนผู้ป่วยห้องฉุกเฉิน', enter_er_time: 'เวลาเข้า ER', leave_datetime: 'เวลาออกจาก ER',
        er_emergency_level_id: 'ระดับความเร่งด่วน (Triage)', er_emergency_type: 'ประเภทความเร่งด่วนที่ ER เลือก',
        export_code: 'รหัสระดับมาตรฐานของประเภท', er_pt_type: 'ประเภทผู้ป่วย ER', er_dch_type: 'ผลการจำหน่ายจาก ER',
        door_to_doctor_second: 'วินาทีจากเข้า ER ถึงพบแพทย์ที่ HOSxP คำนวณ',
        lab_head: 'ใบสั่งตรวจแล็บ (เวลาสั่ง / รับสิ่งส่งตรวจ)', lab_order: 'รายการแล็บที่สั่ง',
        xray_report: 'ใบสั่ง X-Ray / Ultrasound', xray_head: 'ใบสั่ง X-Ray', xray_items: 'รายการ X-Ray',
        request_date: 'วันที่ส่งตรวจ', order_date: 'วันที่สั่ง',
        opitemrece: 'รายการยา เวชภัณฑ์ ค่าบริการที่สั่งใน visit', drugitems: 'รายชื่อยา', did: 'รหัสยามาตรฐาน 24 หลัก',
        opdscreen: 'ข้อมูลซักประวัติ (สัญญาณชีพ อาการ)', hpi: 'ประวัติการเจ็บป่วยปัจจุบันที่พยาบาลจด', pmh: 'ประวัติการเจ็บป่วยในอดีต',
        ovstdiag: 'การวินิจฉัยของ visit', vn_stat: 'สรุปข้อมูลแต่ละ visit', pdx: 'การวินิจฉัยหลัก', icd101: 'รายชื่อโรค ICD-10',
        ipt: 'ข้อมูลการ Admit 1 แถว = 1 AN', regdate: 'วันที่ Admit', regtime: 'เวลา Admit', dchdate: 'วันที่จำหน่าย',
        dchtype: 'วิธีจำหน่าย', ward: 'หอผู้ป่วย', plan_admit: 'Admit ตามแผน (นัดนอน)', bedcount: 'จำนวนเตียง',
        ward_active: 'หอผู้ป่วยที่เปิดใช้งาน', an_stat: 'สรุปข้อมูลผู้ป่วยในแต่ละ AN', 'an_stat.admdate': 'จำนวนวันนอน',
        iptdiag: 'การวินิจฉัยผู้ป่วยใน', iptoprt: 'หัตถการผู้ป่วยใน', ipt_newborn: 'ข้อมูลทารกแรกเกิด',
        ipt_labour_infant: 'ทารกจากการคลอด',
        referout: 'ใบส่งต่อผู้ป่วยออก', referin: 'ใบรับส่งต่อผู้ป่วยเข้า', refer_hospcode: 'รหัสสถานพยาบาลปลายทาง / ต้นทาง',
        refer_date: 'วันที่ส่งต่อ', refer_time: 'เวลาส่งต่อ', rfrcs: 'เหตุผลการส่งต่อ', hospcode: 'ทะเบียนสถานพยาบาล',
        opdconfig: 'ค่าตั้งค่าของ รพ. (รหัส รพ.)',
        operation_list: 'รายการผ่าตัด', doctor_operation: 'หัตถการที่แพทย์ทำ', operation_date: 'วันที่ผ่าตัด',
        dtmain: 'งานทันตกรรม', pregnancy: 'ตรวจฟันหญิงตั้งครรภ์', dttm: 'รายการหัตถการทันตกรรม',
        health_med_service: 'บริการแพทย์แผนไทย', health_med_operation_item: 'หัตถการแพทย์แผนไทย',
        physic_list: 'บริการกายภาพบำบัด', physic_items: 'รายการกายภาพบำบัด', physic_plan: 'แผนการกายภาพบำบัด',
        person: 'ทะเบียนประชากรในเขตรับผิดชอบ', birthdate: 'วันเกิด', death_date: 'วันที่เสียชีวิต',
        person_anc: 'ทะเบียนหญิงตั้งครรภ์', anc_register_date: 'วันที่ลงทะเบียนฝากครรภ์', labor_date: 'วันคลอด',
        labour_hospcode: 'สถานที่คลอด', person_anc_service: 'การมาฝากครรภ์แต่ละครั้ง', anc_service_date: 'วันที่มาฝากครรภ์',
        person_anc_lab: 'ผลแล็บฝากครรภ์', person_anc_classifying: 'การจำแนกความเสี่ยงหญิงตั้งครรภ์',
        person_anc_other_precare: 'บริการระหว่างตั้งครรภ์อื่น ๆ', person_anc_preg_care: 'บันทึกการดูแลการตั้งครรภ์',
        'post_labor_service1–3_date': 'วันตรวจหลังคลอดครั้งที่ 1–3', pa_week: 'อายุครรภ์ (สัปดาห์)',
        person_vaccine: 'วัคซีนที่ประชากรในเขตได้รับ', ovst_vaccine: 'วัคซีนที่ฉีดใน visit', thalassaemia_result: 'ผลตรวจธาลัสซีเมีย'
    };
    function gloss(term) {
        if (GLOSS[term]) return GLOSS[term];
        if (term.startsWith('config/')) return 'ไฟล์ตั้งค่าของเว็บนี้ ไม่ใช่ HOSxP';
        const col = term.slice(term.lastIndexOf('.') + 1);
        return term.includes('.') ? GLOSS[col] : undefined;
    }
    function addGloss(body) {
        const seen = new Set();
        body.querySelectorAll('code').forEach(c => {
            const t = c.textContent.trim(), g = gloss(t);
            if (!g || seen.has(t)) return;
            seen.add(t);
            c.insertAdjacentHTML('afterend', ` <span class="er-gloss">(${g})</span>`);
        });
    }

    let dlg;
    function build() {
        dlg = document.createElement('dialog');
        dlg.className = 'er-info-dlg';
        dlg.setAttribute('aria-labelledby', 'er-info-title');
        dlg.innerHTML = `
            <header class="er-info-head">
                <span class="er-ico" aria-hidden="true"><i class="fa-solid fa-circle-info"></i></span>
                <h3 id="er-info-title"></h3>
                <button type="button" class="er-info-close" aria-label="ปิด"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
            </header>
            <div class="er-info-body"></div>`;
        (document.querySelector('.content-wrapper') || document.body).appendChild(dlg);   // ได้สีธีม .er-theme
        dlg.querySelector('.er-info-close').addEventListener('click', () => dlg.close());
        dlg.addEventListener('click', e => { if (e.target === dlg) dlg.close(); });   // คลิกพื้นหลังเพื่อปิด
    }

    function open(key) {
        const info = INFO[key];
        if (!info) return;
        if (!dlg) build();
        dlg.querySelector('#er-info-title').textContent = 'ที่มาของข้อมูล: ' + info.title;
        dlg.querySelector('.er-info-body').innerHTML = Object.entries(LABELS)
            .filter(([k]) => info[k] && info[k].length)
            .map(([k, [icon, label]]) => `
                <section class="er-info-sec">
                    <h4><i class="fa-solid fa-${icon}" aria-hidden="true"></i> ${label}</h4>
                    <ul>${info[k].map(t => `<li>${t}</li>`).join('')}</ul>
                </section>`).join('');
        addGloss(dlg.querySelector('.er-info-body'));
        dlg.querySelector('.er-info-body').scrollTop = 0;
        if (!dlg.open) dlg.showModal();
    }

    // ปุ่มอยู่ทั้งในการ์ดรายวันและกราฟ → จับที่ document · stopPropagation ไม่ให้ไปเปิดรายชื่อของการ์ดที่ปุ่มอยู่
    document.addEventListener('click', e => {
        const b = e.target.closest('.er-info-btn[data-info]');
        if (!b) return;
        e.preventDefault();
        e.stopPropagation();
        open(b.dataset.info);
    }, true);

    window.erInfo = { open };
})();
