/* ============================================================
   performance.js — Module 11 Player Performance Analysis
   ============================================================ */
document.addEventListener('DOMContentLoaded', function () {
  var BASE = window.SMS_BASE_URL || '';
  var gridColor = '#e5eaf2';
  var palette = ['#0B5ED7', '#198754', '#FFC107', '#6f42c1', '#c1443c'];

  /* ============ Admin dashboard charts ============ */
  if (typeof Chart !== 'undefined' && window.perfCharts) {
    var d = window.perfCharts;

    var bySportEl = document.getElementById('chartBySport');
    if (bySportEl) {
      new Chart(bySportEl, {
        type: 'bar',
        data: {
          labels: d.bySport.map(function (r) { return r.sport_name; }),
          datasets: [{ label: 'Avg Performance', data: d.bySport.map(function (r) { return Math.round(r.avg_score * 10) / 10; }), backgroundColor: palette, borderRadius: 6 }],
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, max: 100, grid: { color: gridColor } }, x: { grid: { display: false } } } },
      });
    }

    var top10El = document.getElementById('chartTop10');
    if (top10El) {
      new Chart(top10El, {
        type: 'bar',
        data: {
          labels: d.top10.map(function (r) { return r.full_name; }),
          datasets: [{ label: 'Score', data: d.top10.map(function (r) { return r.performance_score; }), backgroundColor: '#0B5ED7', borderRadius: 6 }],
        },
        options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true, max: 100, grid: { color: gridColor } }, y: { grid: { display: false } } } },
      });
    }

    var distEl = document.getElementById('chartDistribution');
    if (distEl) {
      new Chart(distEl, {
        type: 'doughnut',
        data: { labels: d.distribution.map(function (r) { return r.performance_level; }), datasets: [{ data: d.distribution.map(function (r) { return r.c; }), backgroundColor: palette, borderWidth: 0 }] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } },
      });
    }

    var ratingEl = document.getElementById('chartRating');
    if (ratingEl) {
      new Chart(ratingEl, {
        type: 'bar',
        data: { labels: d.ratingDistribution.map(function (r) { return r.bucket + '★'; }), datasets: [{ label: 'Players', data: d.ratingDistribution.map(function (r) { return r.c; }), backgroundColor: '#FFC107', borderRadius: 6 }] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, grid: { color: gridColor } }, x: { grid: { display: false } } } },
      });
    }
  }

  /* ============ Player trend chart (with range switching) ============ */
  var trendCanvas = document.getElementById('chartTrend');
  if (trendCanvas && typeof Chart !== 'undefined') {
    var trendChart = new Chart(trendCanvas, {
      type: 'line',
      data: {
        labels: (window.perfHistory || []).map(function (h) { return h.label; }),
        datasets: [{ label: 'Performance Score', data: (window.perfHistory || []).map(function (h) { return h.score; }), borderColor: '#0B5ED7', backgroundColor: 'rgba(11,94,215,.1)', fill: true, tension: 0.35, pointRadius: 4 }],
      },
      options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, max: 100, grid: { color: gridColor } }, x: { grid: { display: false } } } },
    });

    var playerIdMatch = window.location.search.match(/[?&]id=(\d+)/);
    var playerId = playerIdMatch ? playerIdMatch[1] : null;
    document.querySelectorAll('.trend-range-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        document.querySelectorAll('.trend-range-btn').forEach(function (b) { b.classList.remove('active'); });
        btn.classList.add('active');
        if (!playerId) return;
        fetch(BASE + '/performance/api/trend_data.php?player_id=' + playerId + '&range=' + btn.getAttribute('data-range'))
          .then(function (r) { return r.json(); })
          .then(function (data) {
            if (!data.success) return;
            trendChart.data.labels = data.labels;
            trendChart.data.datasets[0].data = data.scores;
            trendChart.update();
          });
      });
    });
  }

  /* ============ Compare chart ============ */
  var compareCanvas = document.getElementById('chartCompare');
  if (compareCanvas && typeof Chart !== 'undefined' && window.compareData) {
    new Chart(compareCanvas, {
      type: 'bar',
      data: { labels: window.compareData.labels, datasets: [{ label: 'Performance Score', data: window.compareData.scores, backgroundColor: palette, borderRadius: 6 }] },
      options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, max: 100, grid: { color: gridColor } }, x: { grid: { display: false } } } },
    });
  }

  /* ============ Compare page: multi-select -> hidden ids field ============ */
  var playerPicker = document.getElementById('playerPicker');
  var idsInput = document.getElementById('idsInput');
  if (playerPicker && idsInput) {
    playerPicker.addEventListener('change', function () {
      var selected = Array.from(playerPicker.selectedOptions).slice(0, 3).map(function (o) { return o.value; });
      idsInput.value = selected.join(',');
    });
  }
});
