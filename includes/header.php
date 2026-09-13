<?php
/**
 * ============================================================
 * header.php
 * ------------------------------------------------------------
 * Outputs the <head> section and opens <body>.
 * Expects an optional $pageTitle variable to be set by the
 * including page BEFORE this file is required.
 * ============================================================
 */
require_once __DIR__ . '/config.php';

if (!isset($pageTitle)) {
    $pageTitle = 'Home';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="<?php echo SITE_NAME . ' — ' . COLLEGE_NAME; ?>">
<title><?php echo htmlspecialchars($pageTitle) . ' | ' . SITE_NAME; ?></title>

<!-- Favicon -->
<link rel="icon" type="image/svg+xml" href="<?php echo ASSETS_URL; ?>/images/logo.svg">

<!-- Google Fonts: Poppins (headings) + Inter (body) -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">

<!-- Bootstrap 5 -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">

<!-- Font Awesome 6 -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

<!-- AOS Animation Library -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.1/aos.css">

<!-- Swiper.js (used for the Testimonials carousel) -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css">

<!-- Custom stylesheet (must load last so it can override the libraries) -->
<link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/style.css">
</head>
<body>
