  </main><!-- /.admin-main (opened in dashboard.php/profile.php/settings.php) -->
</div><!-- /.admin-layout -->

<footer class="admin-footer">
  <span>&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?> &middot; <?php echo COLLEGE_NAME; ?></span>
  <span class="admin-footer-module">Module 4 — Admin Dashboard</span>
</footer>

<!-- Bootstrap 5 -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<!-- DataTables (+ jQuery, required by DataTables) -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.11/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.11/js/dataTables.bootstrap5.min.js"></script>
<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
  /**
   * BUGFIX (post-Module 7): this used to only be set inline on
   * admin/dashboard.php, so every OTHER admin page's fetch() calls
   * (dependent Coach/Team dropdowns in Player/Coach/Team create+edit
   * forms, delete/reset-password/quick-view buttons, notification
   * mark-read/delete) silently fell back to an empty base URL and
   * 404'd — which is why Coach/Team dropdowns showed "Could not
   * load" and newly created players/teams never actually got linked.
   * Defining it here, once, on every page that loads this footer,
   * fixes all of them at once.
   */
  window.SMS_BASE_URL = <?php echo json_encode(BASE_URL); ?>;
</script>

<!-- Dashboard-specific JS (charts, counters, sidebar, dark mode, notifications) -->
<script src="<?php echo BASE_URL; ?>/includes/dashboard.js"></script>
<?php
/**
 * MODULE 5 ADDITION: a page can set $extraFooterScripts (a string of
 * <script> tags) before requiring this file to load extra libraries
 * (e.g. DataTables Buttons for Excel/PDF/Print export on the Player
 * Management listing). Pages that don't set it are unaffected.
 */
if (!empty($extraFooterScripts)) {
    echo $extraFooterScripts;
}
?>
</body>
</html>
