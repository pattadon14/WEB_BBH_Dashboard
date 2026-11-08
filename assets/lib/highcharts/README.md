# Highcharts (self-hosted)

- Version 13.1.1 from https://code.highcharts.com/13.1.1/ (highcharts.js, modules/heatmap.js)
- Kept in the repo instead of loading code.highcharts.com: that CDN rejects requests without a Referer
  and always serves the latest major version, so charts could break without any change here.
- To upgrade: download the new version into a new folder and update the <script> tags
  (index.php, pages/ER_detail.php, pages/ipd_detail.php, pages/opd_detail.php, assets/js/ipd_ward_history.js).
- License: Highcharts is commercial software, free for non-commercial use — see https://shop.highcharts.com/
