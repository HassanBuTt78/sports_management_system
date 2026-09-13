<?php
/**
 * ============================================================
 * auth_header.php
 * ------------------------------------------------------------
 * Shared <head> + opening markup for every auth page (4 login
 * pages, forgot password, reset password). Deliberately NOT the
 * same header.php used by the public site — auth pages get a
 * focused, centered card layout instead of the full nav/ticker,
 * so the login experience feels dedicated and distraction-free.
 *
 * Expects $pageTitle to be set before this file is required.
 * ============================================================
 */
if (!isset($pageTitle)) {
    $pageTitle = 'Login';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo htmlspecialchars($pageTitle) . ' | ' . SITE_NAME; ?></title>
<link rel="icon" type="image/svg+xml" href="<?php echo ASSETS_URL; ?>/images/logo.svg">

<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

<link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/style.css">
<link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/auth.css">
</head>
<body class="auth-body">

<div class="auth-wrapper">
  <div class="auth-card-outer">

    <div class="auth-brand">
      <img src="<?php echo ASSETS_URL; ?>/images/logo.svg" alt="<?php echo SITE_NAME; ?> logo" width="52" height="52">
      <div>
        <span class="auth-college"><?php echo COLLEGE_NAME; ?></span>
        <h1 class="auth-title">Sports Management System</h1>
      </div>
    </div>
