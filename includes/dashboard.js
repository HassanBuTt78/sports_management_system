/* ============================================================
   dashboard.js
   ------------------------------------------------------------
   Admin Dashboard behavior: sidebar toggle (mobile), dark/light
   mode + persistence, animated stat counters, Chart.js charts
   (data injected by dashboard.php via window.dashboardCharts),
   DataTables init, and notification mark-read/delete via AJAX.
   ============================================================ */

document.addEventListener('DOMContentLoaded', function () {

  /* ---------- Page loading overlay ---------- */
  var overlay = document.getElementById('pageLoadingOverlay');
  if (overlay) {
    setTimeout(function () {
      overlay.style.opacity = '0';
      setTimeout(function () { overlay.remove(); }, 300);
    }, 200);
  }

  /* ---------- Sidebar toggle (mobile/tablet) ---------- */
  var sidebar   = document.getElementById('adminSidebar');
  var overlayEl = document.getElementById('sidebarOverlay');
  var openBtn   = document.getElementById('sidebarToggle');
  var closeBtn  = document.getElementById('sidebarClose');

  function openSidebar() {
    sidebar.classList.add('open');
    overlayEl.classList.add('show');
  }
  function closeSidebar() {
    sidebar.classList.remove('open');
    overlayEl.classList.remove('show');
  }
  if (openBtn) openBtn.addEventListener('click', openSidebar);
  if (closeBtn) closeBtn.addEventListener('click', closeSidebar);
  if (overlayEl) overlayEl.addEventListener('click', closeSidebar);

  /* ---------- Dark / Light mode toggle (persisted) ---------- */
  var themeToggle = document.getElementById('themeToggle');
  var htmlEl = document.documentElement;

  function applyTheme(theme) {
    htmlEl.setAttribute('data-theme', theme);
    if (themeToggle) {
      themeToggle.innerHTML = theme === 'dark'
        ? '<i class="fa-solid fa-sun"></i>'
        : '<i class="fa-solid fa-moon"></i>';
    }
  }
  var savedTheme = localStorage.getItem('sms_admin_theme') || 'light';
  applyTheme(savedTheme);

  if (themeToggle) {
    themeToggle.addEventListener('click', function () {
      var next = htmlEl.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
      localStorage.setItem('sms_admin_theme', next);
      applyTheme(next);
    });
  }

  /* ---------- Animated stat counters ---------- */
  document.querySelectorAll('[data-counter]').forEach(function (el) {
    var target = parseInt(el.getAttribute('data-counter'), 10) || 0;
    var duration = 1200;
    var start = null;
    function step(ts) {
      if (!start) start = ts;
      var progress = Math.min((ts - start) / duration, 1);
      el.textContent = Math.floor(progress * target).toLocaleString();
      if (progress < 1) requestAnimationFrame(step);
      else el.textContent = target.toLocaleString();
    }
    requestAnimationFrame(step);
  });

  /* ---------- Chart.js charts ---------- */
  // dashboard.php sets window.dashboardCharts = { playersPerSport: {...}, ... }
  if (typeof Chart !== 'undefined' && window.dashboardCharts) {
    var d = window.dashboardCharts;
    var gridColor = getComputedStyle(document.documentElement).getPropertyValue('--border') || '#e5eaf2';
    Chart.defaults.font.family = "'Inter', sans-serif";
    Chart.defaults.color = getComputedStyle(document.documentElement).getPropertyValue('--text-muted') || '#6c7789';

    var palette = ['#0B5ED7', '#198754', '#FFC107', '#6f42c1', '#0dcaf0', '#fd7e14', '#d63384', '#20c997'];

    if (d.playersPerSport && document.getElementById('chartPlayersPerSport')) {
      new Chart(document.getElementById('chartPlayersPerSport'), {
        type: 'doughnut',
        data: {
          labels: d.playersPerSport.labels,
          datasets: [{ data: d.playersPerSport.values, backgroundColor: palette, borderWidth: 0 }]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
      });
    }

    if (d.performanceStats && document.getElementById('chartPerformanceStats')) {
      new Chart(document.getElementById('chartPerformanceStats'), {
        type: 'bar',
        data: {
          labels: d.performanceStats.labels,
          datasets: [{ label: 'Avg Rating', data: d.performanceStats.values, backgroundColor: '#198754', borderRadius: 6 }]
        },
        options: {
          responsive: true, maintainAspectRatio: false,
          scales: { y: { beginAtZero: true, max: 5, grid: { color: gridColor } }, x: { grid: { display: false } } },
          plugins: { legend: { display: false } }
        }
      });
    }

    if (d.monthlyEvents && document.getElementById('chartMonthlyEvents')) {
      new Chart(document.getElementById('chartMonthlyEvents'), {
        type: 'line',
        data: {
          labels: d.monthlyEvents.labels,
          datasets: [{
            label: 'Events', data: d.monthlyEvents.values, borderColor: '#0B5ED7',
            backgroundColor: 'rgba(11,94,215,.12)', fill: true, tension: 0.35, pointRadius: 3
          }]
        },
        options: {
          responsive: true, maintainAspectRatio: false,
          scales: { y: { beginAtZero: true, grid: { color: gridColor } }, x: { grid: { display: false } } },
          plugins: { legend: { display: false } }
        }
      });
    }

    if (d.coachPerformance && document.getElementById('chartCoachPerformance')) {
      new Chart(document.getElementById('chartCoachPerformance'), {
        type: 'bar',
        data: {
          labels: d.coachPerformance.labels,
          datasets: [{ label: 'Avg Rating', data: d.coachPerformance.values, backgroundColor: '#FFC107', borderRadius: 6 }]
        },
        options: {
          indexAxis: 'y', responsive: true, maintainAspectRatio: false,
          scales: { x: { beginAtZero: true, max: 5, grid: { color: gridColor } }, y: { grid: { display: false } } },
          plugins: { legend: { display: false } }
        }
      });
    }

    if (d.playerGrowth && document.getElementById('chartPlayerGrowth')) {
      new Chart(document.getElementById('chartPlayerGrowth'), {
        type: 'line',
        data: {
          labels: d.playerGrowth.labels,
          datasets: [{
            label: 'New Players', data: d.playerGrowth.values, borderColor: '#6f42c1',
            backgroundColor: 'rgba(111,66,193,.12)', fill: true, tension: 0.35, pointRadius: 3
          }]
        },
        options: {
          responsive: true, maintainAspectRatio: false,
          scales: { y: { beginAtZero: true, grid: { color: gridColor } }, x: { grid: { display: false } } },
          plugins: { legend: { display: false } }
        }
      });
    }
  }

  /* ---------- DataTables ---------- */
  if (typeof $ !== 'undefined' && $.fn.DataTable) {
    $('.data-table').each(function () {
      $(this).DataTable({
        pageLength: 5,
        lengthChange: false,
        language: { search: '', searchPlaceholder: 'Filter...' },
      });
    });
  }

  /* ---------- Notification mark-as-read / delete (AJAX) ---------- */
  document.querySelectorAll('.notif-action-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var item = btn.closest('.notif-item');
      var notifId = item.getAttribute('data-notif-id');
      var action = btn.getAttribute('data-action');

      fetch((window.SMS_BASE_URL || '') + '/admin/notifications_action.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'notification_id=' + encodeURIComponent(notifId) + '&action=' + encodeURIComponent(action)
      })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (data.success) {
            if (action === 'delete') {
              item.remove();
            } else {
              item.classList.remove('is-unread');
              var readBtn = item.querySelector('[data-action="read"]');
              if (readBtn) readBtn.remove();
            }
            if (typeof Swal !== 'undefined') {
              Swal.fire({
                toast: true, position: 'top-end', icon: 'success',
                title: action === 'delete' ? 'Notification deleted' : 'Marked as read',
                showConfirmButton: false, timer: 1600,
              });
            }
          } else if (typeof Swal !== 'undefined') {
            Swal.fire({ icon: 'error', title: 'Something went wrong', text: data.message || '' });
          }
        })
        .catch(function () {
          if (typeof Swal !== 'undefined') {
            Swal.fire({ icon: 'error', title: 'Network error', text: 'Could not reach the server.' });
          }
        });
    });
  });

  /* ---------- Quick action buttons pointing to not-yet-built modules ---------- */
  document.querySelectorAll('[data-coming-soon]').forEach(function (el) {
    el.addEventListener('click', function (e) {
      if (typeof Swal !== 'undefined') {
        e.preventDefault();
        Swal.fire({
          icon: 'info',
          title: 'Coming soon',
          text: el.getAttribute('data-coming-soon') + ' is built in an upcoming module.',
          confirmButtonColor: '#0B5ED7',
        });
      }
    });
  });

});
