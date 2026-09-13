/* ============================================================
   team.js
   ------------------------------------------------------------
   Interactive behavior for Team Management (Module 7): sport→
   coach dependent dropdown (reuses the existing Player Management
   AJAX endpoint — no duplicate endpoint needed), team logo
   preview, soft-delete via SweetAlert2/AJAX, the teams DataTable,
   and the Chart.js statistics page.
   ============================================================ */

document.addEventListener('DOMContentLoaded', function () {

  var BASE = window.SMS_BASE_URL || '';

  /* ---------- Sport -> Coach dependent dropdown ---------- */
  var sportSelect = document.getElementById('sport_id');
  var coachSelect = document.getElementById('coach_id');

  function loadCoachesForSport(sportId, currentCoach) {
    if (!sportId) {
      coachSelect.innerHTML = '<option value="">Select Sport First</option>';
      return;
    }
    fetch(BASE + '/admin/player/ajax_get_coaches.php?sport_id=' + sportId)
      .then(function (r) { return r.json(); })
      .then(function (data) {
        coachSelect.innerHTML = '<option value="">No Coach Assigned</option>';
        (data.coaches || []).forEach(function (c) {
          var opt = document.createElement('option');
          opt.value = c.coach_id;
          opt.textContent = c.full_name;
          if (currentCoach && String(c.coach_id) === String(currentCoach)) opt.selected = true;
          coachSelect.appendChild(opt);
        });
      })
      .catch(function () { coachSelect.innerHTML = '<option value="">Could not load</option>'; });
  }

  if (sportSelect && coachSelect) {
    if (sportSelect.value) {
      loadCoachesForSport(sportSelect.value, coachSelect.getAttribute('data-current'));
    }
    sportSelect.addEventListener('change', function () { loadCoachesForSport(sportSelect.value, null); });
  }

  /* ---------- Team logo preview ---------- */
  var imageInput = document.getElementById('profile_image');
  var imagePreview = document.getElementById('imagePreview');
  if (imageInput && imagePreview) {
    imageInput.addEventListener('change', function () {
      var file = imageInput.files[0];
      if (!file) return;
      if (file.size > 2 * 1024 * 1024) {
        Swal.fire({ icon: 'error', title: 'Image too large', text: 'Please choose an image under 2MB.' });
        imageInput.value = '';
        return;
      }
      if (!['image/jpeg', 'image/png'].includes(file.type)) {
        Swal.fire({ icon: 'error', title: 'Invalid file type', text: 'Only JPG, JPEG, and PNG images are allowed.' });
        imageInput.value = '';
        return;
      }
      var reader = new FileReader();
      reader.onload = function (e) { imagePreview.src = e.target.result; };
      reader.readAsDataURL(file);
    });
  }

  /* ---------- Bootstrap client-side validation ---------- */
  document.querySelectorAll('form.needs-validation').forEach(function (form) {
    form.addEventListener('submit', function (event) {
      if (!form.checkValidity()) {
        event.preventDefault();
        event.stopPropagation();
      }
      form.classList.add('was-validated');
    });
  });

  /* ---------- Deactivate (soft delete) team ---------- */
  document.querySelectorAll('.delete-team-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var teamId = btn.getAttribute('data-id');
      var teamName = btn.getAttribute('data-name');

      Swal.fire({
        title: 'Deactivate ' + teamName + '?',
        text: 'This does not delete the team or its match history — it can be reactivated later from Edit.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, deactivate',
        confirmButtonColor: '#c1443c',
      }).then(function (result) {
        if (!result.isConfirmed) return;
        fetch(BASE + '/admin/team/delete.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: 'team_id=' + encodeURIComponent(teamId),
        })
          .then(function (r) { return r.json(); })
          .then(function (data) {
            if (data.success) {
              Swal.fire({ icon: 'success', title: 'Deactivated', timer: 1500, showConfirmButton: false })
                .then(function () { window.location.reload(); });
            } else {
              Swal.fire({ icon: 'error', title: 'Failed', text: data.message || 'Please try again.' });
            }
          })
          .catch(function () {
            Swal.fire({ icon: 'error', title: 'Network error', text: 'Could not reach the server.' });
          });
      });
    });
  });

  /* ---------- Teams DataTable ---------- */
  if (typeof $ !== 'undefined' && $.fn.DataTable && document.getElementById('teamsTable')) {
    $('#teamsTable').DataTable({
      pageLength: 10,
      order: [[1, 'asc']],
      columnDefs: [{ orderable: false, targets: [0, 8] }],
      language: { search: '', searchPlaceholder: 'Search teams...' },
    });
  }

  /* ---------- Team Statistics charts (statistics.php) ---------- */
  if (typeof Chart !== 'undefined' && window.teamStatsCharts) {
    var d = window.teamStatsCharts;
    var gridColor = '#e5eaf2';

    if (document.getElementById('chartPlayersPerTeam')) {
      new Chart(document.getElementById('chartPlayersPerTeam'), {
        type: 'bar',
        data: { labels: d.labels, datasets: [{ label: 'Players', data: d.players, backgroundColor: '#0B5ED7', borderRadius: 6 }] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, grid: { color: gridColor } }, x: { grid: { display: false } } } },
      });
    }
    if (document.getElementById('chartAvgRating')) {
      new Chart(document.getElementById('chartAvgRating'), {
        type: 'bar',
        data: { labels: d.labels, datasets: [{ label: 'Avg Rating', data: d.ratings, backgroundColor: '#FFC107', borderRadius: 6 }] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, max: 5, grid: { color: gridColor } }, x: { grid: { display: false } } } },
      });
    }
    if (document.getElementById('chartAvgScore')) {
      new Chart(document.getElementById('chartAvgScore'), {
        type: 'line',
        data: { labels: d.labels, datasets: [{ label: 'Avg Score', data: d.scores, borderColor: '#198754', backgroundColor: 'rgba(25,135,84,.12)', fill: true, tension: 0.35, pointRadius: 3 }] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, grid: { color: gridColor } }, x: { grid: { display: false } } } },
      });
    }
  }

});
