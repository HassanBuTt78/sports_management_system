/* ============================================================
   rate_player.js — star rating input + AJAX prefill for
   coach/rate_player.php
   ============================================================ */
document.addEventListener('DOMContentLoaded', function () {
  var BASE = window.SMS_BASE_URL || '';
  var playerId = window.SMS_PLAYER_ID;

  /* ---------- Star rating picker ---------- */
  var stars = document.querySelectorAll('.star-choice');
  var ratingField = document.getElementById('ratingField');
  function setStars(value) {
    stars.forEach(function (s) {
      s.classList.toggle('active', parseInt(s.getAttribute('data-value'), 10) <= value);
    });
    if (ratingField) ratingField.value = value;
  }
  stars.forEach(function (s) {
    s.addEventListener('click', function () { setStars(parseInt(s.getAttribute('data-value'), 10)); });
  });

  /* ---------- Match select -> AJAX prefill (edit-mode detection) ---------- */
  var matchSelect = document.getElementById('matchSelect');
  var scoreField = document.getElementById('scoreField');
  var commentField = document.getElementById('commentField');
  var duplicateNotice = document.getElementById('duplicateNotice');
  var submitBtn = document.getElementById('submitBtn');

  if (matchSelect) {
    matchSelect.addEventListener('change', function () {
      var matchId = matchSelect.value;
      setStars(0);
      scoreField.value = '';
      commentField.value = '';
      duplicateNotice.classList.add('d-none');
      submitBtn.innerHTML = '<i class="fa-solid fa-floppy-disk me-2"></i>Submit Performance';

      if (!matchId) return;

      fetch(BASE + '/coach/get_player_match_rating.php?player_id=' + playerId + '&match_id=' + matchId)
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (!data.success) return;
          if (data.exists) {
            duplicateNotice.classList.remove('d-none');
            submitBtn.innerHTML = '<i class="fa-solid fa-pen me-2"></i>Update Performance';
            if (data.score !== null) scoreField.value = data.score;
            if (data.rating !== null) setStars(data.rating);
            if (data.comment) commentField.value = data.comment;
          }
        });
    });
  }

  /* ---------- Client-side validation (server re-validates everything, §24) ---------- */
  var rateForm = document.getElementById('rateForm');
  if (rateForm) {
    rateForm.addEventListener('submit', function (e) {
      var score = parseFloat(scoreField.value);
      var rating = parseInt(ratingField.value, 10);
      if (isNaN(score) || score < 0) {
        e.preventDefault();
        Swal.fire({ icon: 'error', title: 'Invalid score', text: 'Please enter a valid, non-negative score.' });
        return;
      }
      if (!rating || rating < 1 || rating > 5) {
        e.preventDefault();
        Swal.fire({ icon: 'error', title: 'Rating required', text: 'Please select a rating from 1 to 5 stars.' });
      }
    });
  }
});
