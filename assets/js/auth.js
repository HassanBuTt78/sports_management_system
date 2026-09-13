/* ============================================================
   auth.js
   ------------------------------------------------------------
   Shared client-side behavior for every auth page: show/hide
   password toggles + Bootstrap validation styling. This is a
   UX convenience layer only — every real check is re-done on
   the server in includes/auth.php, since client-side validation
   can always be bypassed.
   ============================================================ */

document.addEventListener('DOMContentLoaded', function () {

  /* ---------- Show / Hide password toggles ---------- */
  document.querySelectorAll('.password-toggle').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var input = document.getElementById(btn.getAttribute('data-target'));
      if (!input) return;
      var showing = input.type === 'text';
      input.type = showing ? 'password' : 'text';
      btn.innerHTML = showing
        ? '<i class="fa-solid fa-eye"></i>'
        : '<i class="fa-solid fa-eye-slash"></i>';
      btn.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
    });
  });

  /* ---------- Bootstrap client-side validation ---------- */
  document.querySelectorAll('form.needs-validation').forEach(function (form) {
    form.addEventListener('submit', function (event) {
      if (!form.checkValidity()) {
        event.preventDefault();
        event.stopPropagation();
      }
      form.classList.add('was-validated');
    }, false);
  });

  /* ---------- OTP input: numeric-only, auto-advance feel ---------- */
  var otpInput = document.getElementById('otp_code');
  if (otpInput) {
    otpInput.addEventListener('input', function () {
      otpInput.value = otpInput.value.replace(/\D/g, '').slice(0, 6);
    });
  }

});
