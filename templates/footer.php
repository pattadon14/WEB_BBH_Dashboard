</div>

<script>window.BBH_CURRENT_FISCAL_YEAR = <?= date('Y') + 543 + (date('n') >= 10 ? 1 : 0) ?>;</script>

<!-- ค่าเริ่มต้นกราฟทุกหน้า: พื้นขาว + tooltip มุมมน (หน้าใดตั้งเองจะทับค่านี้) -->
<script>
if (window.Highcharts) {
    Highcharts.setOptions({
        chart: { backgroundColor: '#ffffff' },
        tooltip: { borderRadius: 10, borderWidth: 1, shadow: false }
    });
}
</script>

<!-- AdminLTE 4 เป็น vanilla JS ไม่ต้องใช้ jQuery และโค้ดของเว็บไม่ได้ใช้ Bootstrap JS (modal/sidebar เขียนเอง) -->
<script src="<?= BASE_URL ?>assets/AdminLTE/dist/js/adminlte.min.js"></script>

<!-- BBH Sidebar -->
<script src="<?= BASE_URL ?>assets/js/sidebar.js"></script>

<!-- HOSxP Login Modal -->
<script src="<?= BASE_URL ?>assets/js/login.js"></script>

<!-- IPD Ward Patient Filter / Sort -->
<script src="<?= BASE_URL ?>assets/js/ipd_ward_filter.js"></script>

<!-- IPD Ward Historical Statistics -->
<script src="<?= BASE_URL ?>assets/js/ipd_ward_history.js"></script>

<!-- IPD Ward Management / Patient Flow / Export -->
<script src="<?= BASE_URL ?>assets/js/ipd_ward_management.js"></script>

</body>

</html>