/* ============================================================
   matches.js
   ------------------------------------------------------------
   Interactive behavior for Match Management (Module 9): sport→
   team/coach dependent dropdowns (reuses the existing Player
   Management AJAX endpoints — no new endpoints needed), admin
   quick actions (cancel/delete) via SweetAlert2 + AJAX,
   DataTable, and the clickable 1-5 star rating input.
   ============================================================ */

document.addEventListener('DOMContentLoaded', function () {

  var BASE = window.SMS_BASE_URL || '';
  var CSRF = window.SMS_CSRF_TOKEN || '';

  /* ---------- Sport -> Team One / Team Two / Coach dependent dropdowns ---------- */
  var sportSelect = document.getElementById('sport_id');
  var teamOneSelect = document.getElementById('team_one');
  var teamTwoSelect = document.getElementById('team_two');
  var coachSelect = document.getElementById('coach_id');

  function loadOptions(url, select, valueKey, labelKey, currentValue, placeholder, allowNone) {
    fetch(url)
      .then(function (r) { return r.json(); })
      .then(function (data) {
        var items = data.coaches || data.teams || [];
        select.innerHTML = allowNone ? '<option value="">' + placeholder + '</option>' : '<option value="">Select...</option>';
        items.forEach(function (item) {
          var opt = document.createElement('option');
          opt.value = item[valueKey];
          opt.textContent = item[labelKey];
          if (currentValue && String(item[valueKey]) === String(currentValue)) opt.selected = true;
          select.appendChild(opt);
        });
      })
      .catch(function () { select.innerHTML = '<option value="">Could not load</option>'; });
  }

  function refreshMatchDropdowns(keepCurrent) {
    var sportId = sportSelect.value;
    var currentTeamOne = keepCurrent ? teamOneSelect.getAttribute('data-current') : '';
    var currentTeamTwo = keepCurrent ? teamTwoSelect.getAttribute('data-current') : '';
    var currentCoach = keepCurrent ? coachSelect.getAttribute('data-current') : '';

    if (!sportId) {
      teamOneSelect.innerHTML = '<option value="">Select Sport First</option>';
      teamTwoSelect.innerHTML = '<option value="">Select Sport First</option>';
      coachSelect.innerHTML = '<option value="">Select Sport First</option>';
      return;
    }
    loadOptions(BASE + '/admin/player/ajax_get_teams.php?sport_id=' + sportId, teamOneSelect, 'team_id', 'team_name', currentTeamOne, 'Select Team One', false);
    loadOptions(BASE + '/admin/player/ajax_get_teams.php?sport_id=' + sportId, teamTwoSelect, 'team_id', 'team_name', currentTeamTwo, 'None (individual sport)', true);
    loadOptions(BASE + '/admin/player/ajax_get_coaches.php?sport_id=' + sportId, coachSelect, 'coach_id', 'full_name', currentCoach, 'No Coach Assigned', true);
  }

  if (sportSelect && teamOneSelect && teamTwoSelect && coachSelect) {
    if (sportSelect.value) refreshMatchDropdowns(true);
    sportSelect.addEventListener('change', function () { refreshMatchDropdowns(false); });
  }

  /* ---------- Bootstrap validation ---------- */
  document.querySelectorAll('form.needs-validation').forEach(function (form) {
    form.addEventListener('submit', function (event) {
      if (!form.checkValidity()) { event.preventDefault(); event.stopPropagation(); }
      form.classList.add('was-validated');
    });
  });

  /* ---------- Admin match quick actions (cancel/delete) ---------- */
  var actionCopy = {
    cancel:  { title: 'Cancel this match?', text: 'Teams and coaches will be notified.', icon: 'warning', color: '#c1443c' },
    delete:  { title: 'Delete this match?', text: 'This permanently removes the match.', icon: 'warning', color: '#c1443c' },
  };
  document.querySelectorAll('.match-action-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var action = btn.getAttribute('data-action');
      var matchId = btn.getAttribute('data-id');
      var copy = actionCopy[action] || { title: 'Are you sure?', text: '', icon: 'question', color: '#0B5ED7' };

      Swal.fire({
        title: copy.title, text: copy.text, icon: copy.icon,
        showCancelButton: true, confirmButtonColor: copy.color, confirmButtonText: 'Yes',
      }).then(function (result) {
        if (!result.isConfirmed) return;
        fetch(BASE + '/admin/matches/delete.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: 'match_id=' + encodeURIComponent(matchId) + '&action=' + encodeURIComponent(action) + '&csrf_token=' + encodeURIComponent(CSRF),
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

  /* ---------- DataTable ---------- */
  if (typeof $ !== 'undefined' && $.fn.DataTable && document.getElementById('matchesTable')) {
    $('#matchesTable').DataTable({ pageLength: 10, order: [[5, 'desc']], columnDefs: [{ orderable: false, targets: [8] }], language: { search: '', searchPlaceholder: 'Search matches...' } });
  }

  /* ---------- Clickable 1-5 star rating input (coach/matches/player_rating.php) ---------- */
  document.querySelectorAll('.star-rating-input').forEach(function (group) {
    var icons = group.querySelectorAll('.star-icon');
    icons.forEach(function (icon) {
      icon.addEventListener('click', function () {
        var value = parseInt(icon.getAttribute('data-value'), 10);
        var radio = group.querySelector('input[type="radio"][value="' + value + '"]');
        if (radio) radio.checked = true;
        icons.forEach(function (i) {
          i.classList.toggle('active', parseInt(i.getAttribute('data-value'), 10) <= value);
        });
      });
    });
  });

});
