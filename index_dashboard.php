<?php
// รหัสแผนกรับบริการกายภาพบำบัด
// รหัสแผนกรับบริการแพทย์แผนไทย
// ทันตกรรม นับเฉพาะผู้รับบริการที่ทำหัตถการ หรือตรวจทันตสุขภาพ (ข้อมูลจากตาราง dtmain)
// อุบัติเหตุ จะดูจากชนิดการมาห้องฉุกเฉิน (ดูจากตาราง er_pt_type ที่มีฟิลด์ accident_code = 'Y')
// จำนวนเตียงทั้งหมดเพื่อคำนวนอัตราการครองเตียง (SELECT SUM(bedcount) FROM ward WHERE ward_active = 'Y')

  try {

		include '_cfg_hos.php';

		$sql1 = "SELECT COUNT(DISTINCT hn) AS ptm_opd_hn,
       COUNT(DISTINCT vn) AS ptm_opd_vn,
       COUNT(DISTINCT CASE WHEN vstdate = CURRENT_DATE THEN vn END) AS pt_opd_today
FROM ovst
WHERE vstdate BETWEEN DATE_TRUNC('month', CURRENT_DATE) AND CURRENT_DATE";
		$query1 = $myPDO->query($sql1);
		foreach($query1 as $data1) {
			$ptm_opd_hn = $data1['ptm_opd_hn'];
			$ptm_opd_vn = $data1['ptm_opd_vn'];
			$pt_opd_today = $data1['pt_opd_today'];
      }
      
		$sql2 = "SELECT COUNT(DISTINCT hn) AS ptm_phy_hn,
		COUNT(DISTINCT vn) AS ptm_phy_vn,
    --COUNT(CASE WHEN vn IS NOT NULL AND vn <> '' THEN vn END) AS ptm_phy_vn,
    COUNT(CASE WHEN physic_plan_date = CURRENT_DATE AND vn IS NOT NULL AND vn <> '' THEN vn END) AS pt_phy_today
FROM physic_plan
WHERE physic_plan_date BETWEEN DATE_TRUNC('month', CURRENT_DATE) AND CURRENT_DATE";
		$query2 = $myPDO->query($sql2);
		foreach($query2 as $data2) {
			$ptm_phy_hn = $data2['ptm_phy_hn'];
			$ptm_phy_vn = $data2['ptm_phy_vn'];
			$pt_phy_today = $data2['pt_phy_today'];
    }
    
		$sql6 = "SELECT COUNT(DISTINCT hn) AS ptm_ipd_hn,
       COUNT(DISTINCT an) AS ptm_ipd_an,
       COUNT(DISTINCT CASE WHEN regdate = CURRENT_DATE THEN an END) AS pt_ipd_today
FROM ipt
WHERE regdate BETWEEN DATE_TRUNC('month', CURRENT_DATE) AND CURRENT_DATE ";
		$query6 = $myPDO->query($sql6);
		foreach($query6 as $data6) {
      $ptm_ipd_hn = $data6['ptm_ipd_hn'];
      $ptm_ipd_an = $data6['ptm_ipd_an'];
      $pt_ipd_today = $data6['pt_ipd_today'];
    }
    
		$sql7 = "SELECT COUNT(DISTINCT hn) AS ptm_dent_hn,
       COUNT(DISTINCT vn) AS ptm_dent_vn,
       COUNT(DISTINCT CASE WHEN vstdate = CURRENT_DATE THEN vn END) AS pt_dent_today
FROM dtmain
WHERE vstdate BETWEEN DATE_TRUNC('month', CURRENT_DATE) AND CURRENT_DATE ";
		$query7 = $myPDO->query($sql7);
		foreach($query7 as $data7) {
      $ptm_dent_hn = $data7['ptm_dent_hn'];
      $ptm_dent_vn = $data7['ptm_dent_vn'];
      $pt_dent_today = $data7['pt_dent_today'];
      }

		$sql8 = "SELECT COUNT(DISTINCT hn) AS ptm_ttm_hn,
       COUNT(*) AS ptm_ttm_vn,
       COUNT(DISTINCT CASE WHEN service_date = CURRENT_DATE THEN vn END) AS pt_ttm_today
FROM health_med_service
WHERE service_date BETWEEN DATE_TRUNC('month', CURRENT_DATE) AND CURRENT_DATE
-- AND in_hospcode_service = 'Y' ";
		$query8 = $myPDO->query($sql8);
		foreach($query8 as $data8) {
      $ptm_ttm_hn = $data8['ptm_ttm_hn'];
      $ptm_ttm_vn = $data8['ptm_ttm_vn'];
      $pt_ttm_today = $data8['pt_ttm_today'];
      }
      
		$sql3 = "SELECT COUNT(DISTINCT o.hn) AS ptm_er_hn,
       COUNT(DISTINCT o.vn) AS ptm_er_vn,
       COUNT(DISTINCT CASE WHEN er.vstdate = CURRENT_DATE THEN o.vn END) AS pt_er_today
FROM er_regist er
LEFT OUTER JOIN ovst o ON o.vn = er.vn
LEFT OUTER JOIN er_pt_type et ON et.er_pt_type = er.er_pt_type
WHERE er.vstdate::date BETWEEN DATE_TRUNC('month', CURRENT_DATE) AND CURRENT_DATE ";
		$query3 = $myPDO->query($sql3);
		foreach($query3 as $data3) {
			$ptm_er_hn = $data3['ptm_er_hn'];
			$ptm_er_vn = $data3['ptm_er_vn'];
			$pt_er_today = $data3['pt_er_today'];
    }
    
		$sql4 = "SELECT COUNT(DISTINCT hn) AS ptm_or_hn,
       COUNT(hn) AS ptm_or_vn,
       COUNT(CASE WHEN patient_department = 'OPD' THEN vn END) AS ptm_or_opd,
       COUNT(CASE WHEN patient_department = 'IPD' THEN an END) AS ptm_or_ipd,
       COUNT(CASE WHEN operation_date = CURRENT_DATE THEN hn END) AS pt_or_today
FROM operation_list
WHERE operation_date BETWEEN DATE_TRUNC('month', CURRENT_DATE) AND CURRENT_DATE ";
		$query4 = $myPDO->query($sql4);
		foreach($query4 as $data4) {
			$ptm_or_hn = $data4['ptm_or_hn'];
			$ptm_or_vn = $data4['ptm_or_vn'];
			$ptm_or_opd = $data4['ptm_or_opd'];
			$ptm_or_ipd = $data4['ptm_or_ipd'];
			$pt_or_today = $data4['pt_or_today'];
    }
    
		$sql5 = "SELECT 
    COUNT(DISTINCT hn) AS ptm_xray_hn,
    COUNT(CASE WHEN vn IS NOT NULL AND vn <> '' THEN vn END) AS ptm_xray_vn,
    COUNT(CASE WHEN request_date = CURRENT_DATE AND vn IS NOT NULL AND vn <> '' THEN vn END) AS pt_xray_today
FROM xray_report
WHERE request_date BETWEEN DATE_TRUNC('month', CURRENT_DATE) AND CURRENT_DATE ";
		$query5 = $myPDO->query($sql5);
		foreach($query5 as $data5) {
			$ptm_xray_hn = $data5['ptm_xray_hn'];
			$ptm_xray_vn = $data5['ptm_xray_vn'];
			$pt_xray_today = $data5['pt_xray_today'];
		}

	} catch (PDOException $e) {
		echo "ไม่สามารถเชื่อมต่อฐานข้อมูลได้ : ".$e->getMessage();
	}
?>

<!-- Small boxes (Stat box) -->
<div class="row">
    <div class="col-md-3 col-sm-6 col-xs-12">
        <!-- small box -->
        <div class="small-box bg-aqua">
            <div class="inner">
                <h3><?php echo number_format($pt_opd_today,0);?></h3>
                <p>ผู้รับบริการวันนี้ <br>(เดือนนี้ <?php echo number_format($ptm_opd_hn,0);?> คน /
                    <?php echo number_format($ptm_opd_vn,0);?> ครั้ง)</p>
            </div>
            <div class="icon">
                <i class="fa fa-stethoscope"></i>
            </div>
            <a href="?stat=opd" class="small-box-footer">รายละเอียด <i class="fa fa-arrow-circle-right"></i></a>
        </div>
    </div>
    <!-- ./col -->
    <div class="col-md-3 col-sm-6 col-xs-12">
        <!-- small box -->
        <div class="small-box bg-red">
            <div class="inner">
                <h3><?php echo number_format($pt_ipd_today,0);?></h3>
                <p>Admit วันนี้ <br>(เดือนนี้ <?php echo number_format($ptm_ipd_hn,0);?> คน /
                    <?php echo number_format($ptm_ipd_an,0);?> ครั้ง)</p>
            </div>
            <div class="icon">
                <i class="fa fa-bed"></i>
            </div>
            <a href="?stat=ipd" class="small-box-footer">รายละเอียด <i class="fa fa-arrow-circle-right"></i></a>
        </div>
    </div>
    <!-- ./col -->
    <div class="col-md-3 col-sm-6 col-xs-12">
        <!-- small box -->
        <div class="small-box bg-green">
            <div class="inner">
                <h3><?php echo number_format($pt_ttm_today,0);?></h3>
                <p>แพทย์แผนไทย วันนี้ <br>(เดือนนี้ <?php echo number_format($ptm_ttm_hn,0);?> คน /
                    <?php echo number_format($ptm_ttm_vn,0);?> ครั้ง)</p>
            </div>
            <div class="icon">
                <i class="fa fa-quote-left"></i>
            </div>
            <a href="?stat=ppt" class="small-box-footer">รายละเอียด <i class="fa fa-arrow-circle-right"></i></a>
        </div>
    </div>
    <!-- ./col -->
    <div class="col-md-3 col-sm-6 col-xs-12">
        <!-- small box -->
        <div class="small-box bg-yellow">
            <div class="inner">
                <h3><?php echo number_format($pt_dent_today,0);?></h3>
                <p>ทันตกรรม วันนี้ <br>(เดือนนี้ <?php echo number_format($ptm_dent_hn,0);?> คน /
                    <?php echo number_format($ptm_dent_vn,0);?> ครั้ง)</p>
            </div>
            <div class="icon">
                <i class="fa fa-hand-paper-o"></i>
            </div>
            <a href="?stat=dent" class="small-box-footer">รายละเอียด <i class="fa fa-arrow-circle-right"></i></a>
        </div>
    </div>
    <!-- ./col -->
</div>
<!-- /.row -->

<!-- Info boxes -->
<div class="row">
    <div class="col-md-3 col-sm-6 col-xs-12">
        <a data-toggle="tooltip" title="คลิกดูรายละเอียด --> X-RAY" style="color:black;" href="?stat=xray">
            <div class="info-box">
                <span class="info-box-icon bg-aqua"><i class="fa fa-times"></i></span>

                <div class="info-box-content">
                    <span class="info-box-number">วันนี้ <?php echo number_format($pt_xray_today,0);?></span>
                    <span class="info-box-text">X-RAY <br>เดือนนี้ <?php echo number_format($ptm_xray_hn,0);?> คน /
                        <?php echo number_format($ptm_xray_vn,0);?> ครั้ง</span>
        </a>
    </div>
    <!-- /.info-box-content -->
</div>
<!-- /.info-box -->
</div>
<!-- /.col -->
<div class="col-md-3 col-sm-6 col-xs-12">
    <a data-toggle="tooltip" title="คลิกดูรายละเอียด --> อุบัติเหตุ" style="color:black;" href="?stat=er">
        <div class="info-box">
            <span class="info-box-icon bg-red"><i class="fa fa-ambulance"></i></span>

            <div class="info-box-content">
                <span class="info-box-number">วันนี้ <?php echo number_format($pt_er_today,0);?></span>
                <span class="info-box-text">อุบัติเหตุ <br>เดือนนี้ <?php echo number_format($ptm_er_hn,0);?> คน /
                    <?php echo number_format($ptm_er_vn,0);?> ครั้ง</span>
    </a>
</div>
<!-- /.info-box-content -->
</div>
<!-- /.info-box -->
</div>
<!-- /.col -->

<!-- fix for small devices only -->
<!-- <div class="clearfix visible-sm-block"></div> -->

<div class="col-md-3 col-sm-6 col-xs-12">
    <a data-toggle="tooltip" title="คลิกดูรายละเอียด --> กายภาพบำบัด" style="color:black;" href="?stat=pts">
        <div class="info-box">
            <span class="info-box-icon bg-green"><i class="fa fa-wheelchair"></i></span>

            <div class="info-box-content">
                <span class="info-box-number">วันนี้ <?php echo number_format($pt_phy_today,0);?></span>
                <span class="info-box-text">กายภาพบำบัด <br>เดือนนี้ <?php echo number_format($ptm_phy_hn,0);?> คน /
                    <?php echo number_format($ptm_phy_vn,0);?> ครั้ง</span>
    </a>
</div>
<!-- /.info-box-content -->
</div>
<!-- /.info-box -->
</div>
<!-- /.col -->

<div class="col-md-3 col-sm-6 col-xs-12">
    <a data-toggle="tooltip" title="คลิกดูรายละเอียด --> ผู้ป่วยผ่าตัด" style="color:black;" href="?stat=or">
        <div class="info-box">
            <span class="info-box-icon bg-yellow"><i class="fa fa-heartbeat"></i></span>

            <div class="info-box-content">
                <span class="info-box-number">วันนี้ <?php echo number_format($pt_or_today,0);?></span>
                <span class="info-box-text">ผู้ป่วยผ่าตัด <br>เดือนนี้ <?php echo number_format($ptm_or_hn,0);?> คน /
                    <?php echo number_format($ptm_or_vn,0);?> ครั้ง (OPD <?php echo number_format($ptm_or_opd,0);?> /
                    IPD <?php echo number_format($ptm_or_ipd,0);?>))</span>
    </a>
</div>
<!-- /.info-box-content -->
</div>
<!-- /.info-box -->
</div>
<!-- /.col -->

</div>
<!-- /.row -->

<?php
  include 'stat/module_disease.php';
  
	if ($mis_user_level >= 4) {
    // include 'index_financial.php';
	} else {
    // include 'index_financial.php';
  }
  
  include 'stat/module_opd.php';
  include 'stat/module_ipd.php';

?>