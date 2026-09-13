/* ============================================================
   player.js
   ------------------------------------------------------------
   All interactive behavior for Player Management (Module 5):
   sport→coach/team dependent dropdowns, image upload preview,
   soft-delete + reset-password via SweetAlert2/AJAX, the
   quick-view popup, "Print Login Credentials", and the
   DataTable with Excel/PDF/Print export buttons.
   ============================================================ */

document.addEventListener('DOMContentLoaded', function () {

  var BASE = window.SMS_BASE_URL || '';

  /* ============================================================
     1. Sport -> Coach / Team dependent dropdowns
     (create.php has empty coach/team selects; edit.php pre-loads
     them and needs the CURRENT value re-selected after loading)
  ============================================================ */
  var sportSelect = document.getElementById('sport_id');
  var coachSelect = document.getElementById('coach_id');
  var teamSelect  = document.getElementById('team_id');

  function loadDependentDropdown(url, select, valueKey, labelKey, currentValue, placeholder) {
    fetch(url)
      .then(function (r) { return r.json(); })
      .then(function (data) {
        var items = data.coaches || data.teams || [];
        select.innerHTML = '<option value="">' + placeholder + '</option>';
        items.forEach(function (item) {
          var opt = document.createElement('option');
          opt.value = item[valueKey];
          opt.textContent = item[labelKey];
          if (currentValue && String(item[valueKey]) === String(currentValue)) {
            opt.selected = true;
          }
          select.appendChild(opt);
        });
      })
      .catch(function () {
        select.innerHTML = '<option value="">Could not load</option>';
      });
  }

  function refreshDependentDropdowns(keepCurrent) {
    var sportId = sportSelect.value;
    var currentCoach = keepCurrent ? coachSelect.getAttribute('data-current') : '';
    var currentTeam  = keepCurrent ? teamSelect.getAttribute('data-current') : '';

    if (!sportId) {
      coachSelect.innerHTML = '<option value="">Select Sport First</option>';
      teamSelect.innerHTML = '<option value="">Select Sport First</option>';
      return;
    }
    loadDependentDropdown(BASE + '/admin/player/ajax_get_coaches.php?sport_id=' + sportId, coachSelect, 'coach_id', 'full_name', currentCoach, 'No Coach Assigned');
    loadDependentDropdown(BASE + '/admin/player/ajax_get_teams.php?sport_id=' + sportId, teamSelect, 'team_id', 'team_name', currentTeam, 'No Team Assigned');
  }

  if (sportSelect && coachSelect && teamSelect) {
    // On edit.php, pre-load using the record's current sport so the
    // saved coach/team show up selected; on create.php there's simply
    // nothing to pre-select yet.
    if (sportSelect.value) {
      refreshDependentDropdowns(true);
    }
    sportSelect.addEventListener('change', function () { refreshDependentDropdowns(false); });
  }

  /* ============================================================
     2. Image upload preview
  ============================================================ */
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
      var validTypes = ['image/jpeg', 'image/png'];
      if (!validTypes.includes(file.type)) {
        Swal.fire({ icon: 'error', title: 'Invalid file type', text: 'Only JPG, JPEG, and PNG images are allowed.' });
        imageInput.value = '';
        return;
      }

      var reader = new FileReader();
      reader.onload = function (e) { imagePreview.src = e.target.result; };
      reader.readAsDataURL(file);
    });
  }

  /* ============================================================
     3. Bootstrap client-side validation (form UX only)
  ============================================================ */
  document.querySelectorAll('form.needs-validation').forEach(function (form) {
    form.addEventListener('submit', function (event) {
      if (!form.checkValidity()) {
        event.preventDefault();
        event.stopPropagation();
      }
      form.classList.add('was-validated');
    });
  });

  /* ============================================================
     4. Deactivate (soft delete) player
  ============================================================ */
  document.querySelectorAll('.delete-player-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var playerId = btn.getAttribute('data-id');
      var playerName = btn.getAttribute('data-name');

      Swal.fire({
        title: 'Deactivate ' + playerName + '?',
        text: 'This does not delete their record — match history and scores are kept. They can be reactivated later from Edit.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, deactivate',
        confirmButtonColor: '#c1443c',
      }).then(function (result) {
        if (!result.isConfirmed) return;

        fetch(BASE + '/admin/player/delete.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: 'player_id=' + encodeURIComponent(playerId),
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

  /* ============================================================
     5. Reset Player Password
  ============================================================ */
  function handleResetPassword(playerId) {
    Swal.fire({
      title: 'Generate a New Password?',
      text: 'This player\'s current password will stop working immediately. A new strong password will be generated automatically.',
      icon: 'warning',
      confirmButtonText: 'Yes, Generate New Password',
      showCancelButton: true,
    }).then(function (result) {
      if (!result.isConfirmed) return;

      fetch(BASE + '/admin/player/reset_password.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'player_id=' + encodeURIComponent(playerId),
      })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (data.success) {
            Swal.fire({
              icon: 'success',
              title: 'Password Reset',
              html:
                '<p>New password for <strong>' + data.player_name + '</strong> (shown once — copy it now):</p>' +
                '<div class="swal2-input" style="font-family:monospace; user-select:all; display:flex; align-items:center; justify-content:center;">' + data.password + '</div>',
              confirmButtonText: 'Copy & Close',
            }).then(function () {
              if (navigator.clipboard) navigator.clipboard.writeText(data.password);
            });
          } else {
            Swal.fire({ icon: 'error', title: 'Failed', text: data.message || 'Please try again.' });
          }
        })
        .catch(function () {
          Swal.fire({ icon: 'error', title: 'Network error', text: 'Could not reach the server.' });
        });
    });
  }
  document.querySelectorAll('.reset-password-btn, #resetPasswordBtn').forEach(function (btn) {
    btn.addEventListener('click', function () { handleResetPassword(btn.getAttribute('data-player-id') || btn.getAttribute('data-id')); });
  });

  /* ============================================================
     5b. Show Player Password (Admin-only reveal)
  ============================================================ */
  document.querySelectorAll('.show-password-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var playerId = btn.getAttribute('data-player-id');

      Swal.fire({
        title: 'Reveal this player\'s password?',
        text: 'This action is logged. Only Admin can see this.',
        icon: 'warning',
        confirmButtonText: 'Yes, Show It',
        showCancelButton: true,
      }).then(function (result) {
        if (!result.isConfirmed) return;

        fetch(BASE + '/admin/player/reveal_password.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: 'player_id=' + encodeURIComponent(playerId),
        })
          .then(function (r) { return r.json(); })
          .then(function (data) {
            if (data.success) {
              document.getElementById('passwordDisplay').textContent = data.password;
              btn.disabled = true;
              setTimeout(function () {
                document.getElementById('passwordDisplay').textContent = '********';
                btn.disabled = false;
              }, 15000);
            } else {
              Swal.fire({ icon: 'error', title: 'Failed', text: data.message || 'Could not retrieve the password.' });
            }
          })
          .catch(function () {
            Swal.fire({ icon: 'error', title: 'Network error', text: 'Could not reach the server.' });
          });
      });
    });
  });

  /* ============================================================
     6. Quick View (from the listing table)
  ============================================================ */
  document.querySelectorAll('.quick-view-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var playerId = btn.getAttribute('data-id');
      fetch(BASE + '/admin/player/player_details.php?id=' + playerId)
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (!data.success) {
            Swal.fire({ icon: 'error', title: 'Could not load player.' });
            return;
          }
          var p = data.player;
          Swal.fire({
            title: p.full_name,
            html:
              '<img src="' + p.profile_image_url + '" style="width:80px;height:80px;border-radius:50%;object-fit:cover;margin-bottom:10px;">' +
              '<p style="margin:0;"><strong>Sport:</strong> ' + (p.sport_name || '—') + '</p>' +
              '<p style="margin:0;"><strong>Coach:</strong> ' + (p.coach_name || '—') + '</p>' +
              '<p style="margin:0;"><strong>Team:</strong> ' + (p.team_name || '—') + '</p>' +
              '<p style="margin:0;"><strong>Status:</strong> ' + p.status + '</p>' +
              '<p style="margin:0;"><strong>Rating:</strong> ' + (p.average_rating ? p.average_rating + ' ★' : '—') + '</p>',
            showCancelButton: true,
            confirmButtonText: 'View Full Profile',
            cancelButtonText: 'Close',
          }).then(function (result) {
            if (result.isConfirmed) window.location.href = p.view_url;
          });
        })
        .catch(function () {
          Swal.fire({ icon: 'error', title: 'Network error', text: 'Could not reach the server.' });
        });
    });
  });

  /* ============================================================
     7. Print Login Credentials (Add Player success panel)
  ============================================================ */
  var printBtn = document.getElementById('printCredentialsBtn');
  if (printBtn) {
    printBtn.addEventListener('click', function () {
      var panel = document.getElementById('credentialPanel');
      var printable = document.createElement('div');
      printable.id = 'printableCredentials';
      printable.innerHTML =
        '<h2>' + (window.SMS_SITE_NAME || 'Sports Management System') + '</h2>' +
        '<h3>Player Account Details</h3>' +
        '<p><strong>Player ID:</strong> #' + panel.getAttribute('data-player-id') + '</p>' +
        '<p><strong>Name:</strong> ' + panel.getAttribute('data-name') + '</p>' +
        '<p><strong>Email:</strong> ' + panel.getAttribute('data-email') + '</p>' +
        '<p><strong>Password:</strong> ' + panel.getAttribute('data-password') + '</p>' +
        '<p style="margin-top:20px; font-size:.8rem;">Log in with the email and password above. This password is shown once — the player should change it after first login if desired.</p>';
      document.body.appendChild(printable);
      window.print();
      document.body.removeChild(printable);
    });
  }

  /* ============================================================
     8. Players DataTable — pagination/search/sort + Excel/PDF/Print
  ============================================================ */
  if (typeof $ !== 'undefined' && $.fn.DataTable && document.getElementById('playersTable')) {
    $('#playersTable').DataTable({
      pageLength: 10,
      order: [[10, 'desc']],
      columnDefs: [{ orderable: false, targets: [0, 11] }],
      dom: 'Bfrtip',
      buttons: [
        { extend: 'excelHtml5', text: '<i class="fa-solid fa-file-excel me-1"></i>Excel', className: 'btn btn-sm', exportOptions: { columns: ':not(:first-child):not(:last-child)' } },
        { extend: 'pdfHtml5',   text: '<i class="fa-solid fa-file-pdf me-1"></i>PDF',   className: 'btn btn-sm', exportOptions: { columns: ':not(:first-child):not(:last-child)' }, orientation: 'landscape' },
        { extend: 'print',      text: '<i class="fa-solid fa-print me-1"></i>Print',    className: 'btn btn-sm', exportOptions: { columns: ':not(:first-child):not(:last-child)' } },
      ],
      language: { search: '', searchPlaceholder: 'Search players...' },
    });
  }

  /* ---------- Show/hide password toggle (Edit page's Change Password panel) ---------- */
  document.querySelectorAll('.toggle-password-visibility').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var field = document.getElementById(btn.getAttribute('data-target'));
      var icon = btn.querySelector('i');
      if (field.type === 'password') {
        field.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
      } else {
        field.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
      }
    });
  });

});
