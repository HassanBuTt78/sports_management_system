/* ============================================================
   coach.js
   ------------------------------------------------------------
   Interactive behavior for Coach Management (Module 6):
   image upload preview, reset password via SweetAlert2/AJAX,
   Print/Download PDF credentials (jsPDF), Bootstrap validation,
   and the coaches DataTable with Excel/PDF/Print export.
   ============================================================ */

document.addEventListener('DOMContentLoaded', function () {

  var BASE = window.SMS_BASE_URL || '';

  /* ---------- Image upload preview ---------- */
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

  /* ---------- Reset Coach Password ---------- */
  document.querySelectorAll('.reset-password-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var coachId = btn.getAttribute('data-coach-id');

      Swal.fire({
        title: 'Generate a New Password?',
        text: 'This coach\'s current password will stop working immediately. A new strong password will be generated automatically.',
        icon: 'warning',
        confirmButtonText: 'Yes, Generate New Password',
        showCancelButton: true,
      }).then(function (result) {
        if (!result.isConfirmed) return;

        fetch(BASE + '/admin/coach/reset_password.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: 'coach_id=' + encodeURIComponent(coachId),
        })
          .then(function (r) { return r.json(); })
          .then(function (data) {
            if (data.success) {
              Swal.fire({
                icon: 'success',
                title: 'Password Reset',
                html:
                  '<p>New password for <strong>' + data.coach_name + '</strong> (shown once — copy it now):</p>' +
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
    });
  });

  /* ---------- Show Coach Password (Admin-only reveal) ---------- */
  document.querySelectorAll('.show-password-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var coachId = btn.getAttribute('data-coach-id');

      Swal.fire({
        title: 'Reveal this coach\'s password?',
        text: 'This action is logged. Only Admin can see this.',
        icon: 'warning',
        confirmButtonText: 'Yes, Show It',
        showCancelButton: true,
      }).then(function (result) {
        if (!result.isConfirmed) return;

        fetch(BASE + '/admin/coach/reveal_password.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: 'coach_id=' + encodeURIComponent(coachId),
        })
          .then(function (r) { return r.json(); })
          .then(function (data) {
            if (data.success) {
              document.getElementById('passwordDisplay').textContent = data.password;
              btn.textContent = ' Hide';
              btn.prepend((function () { var i = document.createElement('i'); i.className = 'fa-solid fa-eye-slash me-1'; return i; })());
              btn.disabled = true;
              setTimeout(function () {
                document.getElementById('passwordDisplay').textContent = '********';
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-eye me-1"></i>Show Password';
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

  /* ---------- Print Credentials (Add Coach success panel) ---------- */
  var printBtn = document.getElementById('printCredentialsBtn');
  if (printBtn) {
    printBtn.addEventListener('click', function () {
      var panel = document.getElementById('credentialPanel');
      var printable = document.createElement('div');
      printable.id = 'printableCredentials';
      printable.innerHTML =
        '<h2>' + (window.SMS_SITE_NAME || 'Sports Management System') + '</h2>' +
        '<h3>Coach Account Details</h3>' +
        '<p><strong>Coach ID:</strong> ' + panel.getAttribute('data-employee-id') + '</p>' +
        '<p><strong>Name:</strong> ' + panel.getAttribute('data-name') + '</p>' +
        '<p><strong>Email:</strong> ' + panel.getAttribute('data-email') + '</p>' +
        '<p><strong>Password:</strong> ' + panel.getAttribute('data-password') + '</p>' +
        '<p style="margin-top:20px; font-size:.8rem;">Log in with the email and password above. This password is shown once — the coach should change it after first login if desired.</p>';
      document.body.appendChild(printable);
      window.print();
      document.body.removeChild(printable);
    });
  }

  /* ---------- Download PDF (Add Coach success panel, via jsPDF) ---------- */
  var pdfBtn = document.getElementById('downloadPdfBtn');
  if (pdfBtn) {
    pdfBtn.addEventListener('click', function () {
      if (typeof window.jspdf === 'undefined') {
        Swal.fire({ icon: 'error', title: 'PDF library not loaded' });
        return;
      }
      var panel = document.getElementById('credentialPanel');
      var doc = new window.jspdf.jsPDF();

      doc.setFontSize(16);
      doc.text('Sports Management System', 20, 20);
      doc.setFontSize(13);
      doc.text('Coach Account Details', 20, 30);

      doc.setFontSize(11);
      doc.text('Coach ID: ' + panel.getAttribute('data-employee-id'), 20, 46);
      doc.text('Name: ' + panel.getAttribute('data-name'), 20, 54);
      doc.text('Email: ' + panel.getAttribute('data-email'), 20, 62);
      doc.text('Password: ' + panel.getAttribute('data-password'), 20, 70);

      doc.setFontSize(9);
      doc.text('Log in with the email and password above. This password is shown once.', 20, 84);

      doc.save('coach-credentials-' + panel.getAttribute('data-employee-id') + '.pdf');
    });
  }

  /* ---------- Coaches DataTable — pagination/search/sort + Excel/PDF/Print ---------- */
  if (typeof $ !== 'undefined' && $.fn.DataTable && document.getElementById('coachesTable')) {
    $('#coachesTable').DataTable({
      pageLength: 10,
      order: [[1, 'asc']],
      columnDefs: [{ orderable: false, targets: [0, 10] }],
      dom: 'Bfrtip',
      buttons: [
        { extend: 'excelHtml5', text: '<i class="fa-solid fa-file-excel me-1"></i>Excel', className: 'btn btn-sm', exportOptions: { columns: ':not(:first-child):not(:last-child)' } },
        { extend: 'pdfHtml5',   text: '<i class="fa-solid fa-file-pdf me-1"></i>PDF',   className: 'btn btn-sm', exportOptions: { columns: ':not(:first-child):not(:last-child)' }, orientation: 'landscape' },
        { extend: 'print',      text: '<i class="fa-solid fa-print me-1"></i>Print',    className: 'btn btn-sm', exportOptions: { columns: ':not(:first-child):not(:last-child)' } },
      ],
      language: { search: '', searchPlaceholder: 'Search coaches...' },
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
