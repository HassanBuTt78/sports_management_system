/* ============================================================
   events.js
   ------------------------------------------------------------
   Interactive behavior for Event Management (Module 8): banner
   preview, admin quick actions (delete/cancel/publish/approve)
   via AJAX + SweetAlert2, participant removal, player join/
   cancel flows, DataTables, and the statistics charts.
   ============================================================ */

document.addEventListener('DOMContentLoaded', function () {

  var BASE = window.SMS_BASE_URL || '';
  var CSRF = window.SMS_CSRF_TOKEN || '';

  /* ---------- Banner image preview ---------- */
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

  /* ---------- Sport -> Coach dependent dropdown (admin create/edit) ---------- */
  var sportSelect = document.getElementById('sport_id');
  var coachSelect = document.getElementById('coach_id');
  if (sportSelect && coachSelect) {
    function loadCoaches(sportId, currentCoach) {
      if (!sportId) { coachSelect.innerHTML = '<option value="">Select Sport First</option>'; return; }
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
    if (sportSelect.value) loadCoaches(sportSelect.value, coachSelect.getAttribute('data-current'));
    sportSelect.addEventListener('change', function () { loadCoaches(sportSelect.value, null); });
  }

  /* ---------- Bootstrap validation ---------- */
  document.querySelectorAll('form.needs-validation').forEach(function (form) {
    form.addEventListener('submit', function (event) {
      if (!form.checkValidity()) { event.preventDefault(); event.stopPropagation(); }
      form.classList.add('was-validated');
    });
  });

  /* ---------- Admin event quick actions (delete/cancel/publish/approve) ---------- */
  var actionCopy = {
    delete:  { title: 'Delete this event?', text: 'This permanently removes the event.', icon: 'warning', color: '#c1443c' },
    cancel:  { title: 'Cancel this event?', text: 'Registered players will be notified.', icon: 'warning', color: '#c1443c' },
    publish: { title: 'Open registration?', text: 'Players will be able to join.', icon: 'question', color: '#0B5ED7' },
    approve: { title: 'Approve this event?', text: 'It will become visible to players.', icon: 'question', color: '#198754' },
  };
  document.querySelectorAll('.event-action-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var action = btn.getAttribute('data-action');
      var eventId = btn.getAttribute('data-id');
      var copy = actionCopy[action] || { title: 'Are you sure?', text: '', icon: 'question', color: '#0B5ED7' };

      Swal.fire({
        title: copy.title, text: copy.text, icon: copy.icon,
        showCancelButton: true, confirmButtonColor: copy.color, confirmButtonText: 'Yes',
      }).then(function (result) {
        if (!result.isConfirmed) return;
        fetch(BASE + '/admin/events/delete.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: 'event_id=' + encodeURIComponent(eventId) + '&action=' + encodeURIComponent(action) + '&csrf_token=' + encodeURIComponent(CSRF),
        })
          .then(function (r) { return r.json(); })
          .then(function (data) {
            if (data.success) {
              Swal.fire({ icon: 'success', title: 'Done', timer: 1400, showConfirmButton: false }).then(function () { window.location.reload(); });
            } else {
              Swal.fire({ icon: 'error', title: 'Could not complete', text: data.message || '' });
            }
          })
          .catch(function () { Swal.fire({ icon: 'error', title: 'Network error' }); });
      });
    });
  });

  /* ---------- Remove participant (admin) ---------- */
  var removeForm = document.getElementById('removeParticipantForm');
  document.querySelectorAll('.remove-participant-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var name = btn.getAttribute('data-name');
      Swal.fire({
        title: 'Remove ' + name + '?', text: 'They will be unregistered from this event.',
        icon: 'warning', showCancelButton: true, confirmButtonColor: '#c1443c', confirmButtonText: 'Remove',
      }).then(function (result) {
        if (result.isConfirmed && removeForm) {
          document.getElementById('removeParticipantId').value = btn.getAttribute('data-id');
          removeForm.submit();
        }
      });
    });
  });

  /* ---------- Player: Join Event ---------- */
  var joinBtn = document.getElementById('joinBtn');
  if (joinBtn) {
    joinBtn.addEventListener('click', function () {
      joinBtn.disabled = true;
      fetch(BASE + '/player/events/join.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'event_id=' + encodeURIComponent(window.SMS_EVENT_ID) + '&csrf_token=' + encodeURIComponent(CSRF),
      })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (data.success) {
            Swal.fire({ icon: 'success', title: 'Joined!', text: data.message }).then(function () { window.location.reload(); });
          } else {
            Swal.fire({ icon: 'error', title: 'Could not join', text: data.message || '' });
            joinBtn.disabled = false;
          }
        })
        .catch(function () { Swal.fire({ icon: 'error', title: 'Network error' }); joinBtn.disabled = false; });
    });
  }

  /* ---------- Player: Cancel Participation ---------- */
  var cancelBtn = document.getElementById('cancelBtn');
  if (cancelBtn) {
    cancelBtn.addEventListener('click', function () {
      Swal.fire({
        title: 'Cancel your registration?', icon: 'warning',
        showCancelButton: true, confirmButtonColor: '#c1443c', confirmButtonText: 'Yes, cancel it',
      }).then(function (result) {
        if (!result.isConfirmed) return;
        fetch(BASE + '/player/events/cancel.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: 'event_id=' + encodeURIComponent(window.SMS_EVENT_ID) + '&csrf_token=' + encodeURIComponent(CSRF),
        })
          .then(function (r) { return r.json(); })
          .then(function (data) {
            if (data.success) {
              Swal.fire({ icon: 'success', title: 'Cancelled', text: data.message }).then(function () { window.location.reload(); });
            } else {
              Swal.fire({ icon: 'error', title: 'Could not cancel', text: data.message || '' });
            }
          })
          .catch(function () { Swal.fire({ icon: 'error', title: 'Network error' }); });
      });
    });
  }

  /* ---------- Print schedule fallback (no PDF uploaded) ---------- */
  var printScheduleBtn = document.getElementById('printScheduleBtn');
  if (printScheduleBtn) {
    printScheduleBtn.addEventListener('click', function () { window.print(); });
  }

  /* ---------- DataTables ---------- */
  if (typeof $ !== 'undefined' && $.fn.DataTable) {
    if (document.getElementById('eventsTable')) {
      $('#eventsTable').DataTable({ pageLength: 10, order: [[5, 'desc']], columnDefs: [{ orderable: false, targets: [0, 8] }], language: { search: '', searchPlaceholder: 'Search events...' } });
    }
    if (document.getElementById('participantsTable')) {
      $('#participantsTable').DataTable({ pageLength: 10, language: { search: '', searchPlaceholder: 'Search participants...' } });
    }
  }

  /* ---------- Event Statistics charts ---------- */
  if (typeof Chart !== 'undefined' && window.eventStatsCharts) {
    var d = window.eventStatsCharts;
    var gridColor = '#e5eaf2';
    var palette = ['#0B5ED7', '#198754', '#FFC107', '#6f42c1'];

    if (document.getElementById('chartEventsPerSport')) {
      new Chart(document.getElementById('chartEventsPerSport'), {
        type: 'doughnut',
        data: { labels: d.eventsPerSport.labels, datasets: [{ data: d.eventsPerSport.values, backgroundColor: palette, borderWidth: 0 }] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } },
      });
    }
    if (document.getElementById('chartMonthlyEvents')) {
      new Chart(document.getElementById('chartMonthlyEvents'), {
        type: 'bar',
        data: { labels: d.monthlyEvents.labels, datasets: [{ label: 'Events', data: d.monthlyEvents.values, backgroundColor: '#0B5ED7', borderRadius: 6 }] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, grid: { color: gridColor } }, x: { grid: { display: false } } } },
      });
    }
    if (document.getElementById('chartParticipationTrend')) {
      new Chart(document.getElementById('chartParticipationTrend'), {
        type: 'line',
        data: { labels: d.participationTrend.labels, datasets: [{ label: 'Registrations', data: d.participationTrend.values, borderColor: '#198754', backgroundColor: 'rgba(25,135,84,.12)', fill: true, tension: 0.35, pointRadius: 3 }] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, grid: { color: gridColor } }, x: { grid: { display: false } } } },
      });
    }
  }

});
