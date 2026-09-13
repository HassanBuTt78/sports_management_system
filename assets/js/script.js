/* ============================================================
   script.js
   ------------------------------------------------------------
   Sports Management System — Module 1 (Landing Website)
   Handles: AOS init, sticky navbar shrink-on-scroll, animated
   stat counters, testimonials carousel (Swiper), gallery hover
   (CSS-driven, JS just ensures graceful fallback), back-to-top
   button, and Bootstrap tooltip activation.
   ============================================================ */

document.addEventListener('DOMContentLoaded', function () {

  /* ---------- 1. AOS (Animate On Scroll) ---------- */
  if (typeof AOS !== 'undefined') {
    AOS.init({
      duration: 700,
      easing: 'ease-out-cubic',
      once: true,
      offset: 60,
    });
  }

  /* ---------- 2. Bootstrap tooltips (Login buttons before auth exists) ---------- */
  var tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
  tooltipTriggerList.forEach(function (el) {
    new bootstrap.Tooltip(el);
  });

  /* ---------- 3. Navbar shrink + shadow on scroll ---------- */
  var navbar = document.getElementById('mainNavbar');
  var backToTop = document.querySelector('.back-to-top');

  function handleScroll() {
    var scrolled = window.scrollY > 40;
    if (navbar) navbar.classList.toggle('scrolled', scrolled);
    if (backToTop) backToTop.classList.toggle('show', window.scrollY > 400);
  }
  window.addEventListener('scroll', handleScroll);
  handleScroll();

  /* ---------- 4. Animated statistic counters ---------- */
  var counters = document.querySelectorAll('.counter');
  function animateCounter(el) {
    var target = parseInt(el.getAttribute('data-count'), 10) || 0;
    var duration = 1500;
    var startTime = null;

    function step(timestamp) {
      if (!startTime) startTime = timestamp;
      var progress = Math.min((timestamp - startTime) / duration, 1);
      // ease-out for a natural "settling" finish
      var eased = 1 - Math.pow(1 - progress, 3);
      el.textContent = Math.floor(eased * target);
      if (progress < 1) {
        requestAnimationFrame(step);
      } else {
        el.textContent = target;
      }
    }
    requestAnimationFrame(step);
  }

  if ('IntersectionObserver' in window && counters.length) {
    var counterObserver = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          animateCounter(entry.target);
          counterObserver.unobserve(entry.target);
        }
      });
    }, { threshold: 0.5 });

    counters.forEach(function (c) { counterObserver.observe(c); });
  } else {
    // Fallback for very old browsers: just show the final numbers
    counters.forEach(function (c) { c.textContent = c.getAttribute('data-count'); });
  }

  /* ---------- 5. Testimonials carousel (Swiper.js) ---------- */
  if (typeof Swiper !== 'undefined' && document.querySelector('.testimonial-swiper')) {
    new Swiper('.testimonial-swiper', {
      loop: true,
      autoplay: { delay: 4500, disableOnInteraction: false },
      pagination: { el: '.swiper-pagination', clickable: true },
      spaceBetween: 24,
    });
  }

  /* ---------- 6. Back-to-top click ---------- */
  if (backToTop) {
    backToTop.addEventListener('click', function (e) {
      e.preventDefault();
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });
  }

  /* ---------- 7. Close mobile nav after clicking a link ---------- */
  var navLinks = document.querySelectorAll('.navbar-nav .nav-link');
  var navCollapseEl = document.getElementById('navMain');
  if (navCollapseEl) {
    var bsCollapse = bootstrap.Collapse.getOrCreateInstance(navCollapseEl, { toggle: false });
    navLinks.forEach(function (link) {
      link.addEventListener('click', function () {
        if (navCollapseEl.classList.contains('show')) {
          bsCollapse.hide();
        }
      });
    });
  }

});
