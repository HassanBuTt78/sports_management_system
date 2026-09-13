/* ============================================================
   admin_player_performance.js — dashboard-mode charts for
   admin/player_performance.php (Top 10 by rating, distribution)
   ============================================================ */
document.addEventListener('DOMContentLoaded', function () {
  if (typeof Chart === 'undefined' || !window.perfSimpleCharts) return;
  var d = window.perfSimpleCharts;
  var gridColor = '#e5eaf2';

  var top10El = document.getElementById('chartTop10');
  if (top10El && d.top10 && d.top10.length) {
    new Chart(top10El, {
      type: 'bar',
      data: {
        labels: d.top10.map(function (r) { return r.name; }),
        datasets: [{ label: 'Rating', data: d.top10.map(function (r) { return r.rating; }), backgroundColor: '#0B5ED7', borderRadius: 6 }],
      },
      options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true, max: 5, grid: { color: gridColor } }, y: { grid: { display: false } } } },
    });
  }

  var distEl = document.getElementById('chartDistribution');
  if (distEl && d.distribution) {
    new Chart(distEl, {
      type: 'doughnut',
      data: {
        labels: Object.keys(d.distribution),
        datasets: [{ data: Object.values(d.distribution), backgroundColor: ['#198754', '#2FA96C', '#0B5ED7', '#FFC107', '#c1443c'], borderWidth: 0 }],
      },
      options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } },
    });
  }
});
