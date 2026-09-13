<?php
/**
 * ============================================================
 * admin/gallery/index.php
 * ------------------------------------------------------------
 * Photo gallery management. Admin uploads images (title +
 * file); this feeds the public pages/gallery.php page. Uses the
 * existing validateUploadedImage()/storeUploadedImage() helpers
 * (same ones profile-photo uploads use) so file-type/size rules
 * stay consistent project-wide.
 * ============================================================
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/middleware.php';

requireRole('admin');

$errors = [];
$uploaded = false;
$adminId = (int) $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $conn) {
    if (($_POST['form_type'] ?? '') === 'upload' && verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $title = cleanInput($_POST['title'] ?? '');
        $imageCheck = validateUploadedImage($_FILES['image'] ?? []);

        if (empty($_FILES['image']['name'])) {
            $errors[] = 'Please choose an image to upload.';
        } elseif (!$imageCheck['valid']) {
            $errors[] = $imageCheck['error'];
        } else {
            $result = storeUploadedImage($_FILES['image'], 'gallery');
            if ($result['success'] && $result['path']) {
                $titleVal = $title !== '' ? $title : null;
                $stmt = $conn->prepare('INSERT INTO gallery (title, image, uploaded_by) VALUES (?, ?, ?)');
                $stmt->bind_param('ssi', $titleVal, $result['path'], $adminId);
                $stmt->execute();
                $stmt->close();
                logActivity($conn, 'admin', $adminId, 'Uploaded a gallery image' . ($title ? ": {$title}" : ''));
                $uploaded = true;
            } else {
                $errors[] = $result['error'] ?? 'Could not save the image.';
            }
        }
    } elseif (($_POST['form_type'] ?? '') === 'delete' && verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $imageId = (int) ($_POST['image_id'] ?? 0);
        $stmt = $conn->prepare('SELECT image FROM gallery WHERE image_id = ? LIMIT 1');
        $stmt->bind_param('i', $imageId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row) {
            deleteUploadedFile($row['image']);
            $stmt = $conn->prepare('DELETE FROM gallery WHERE image_id = ?');
            $stmt->bind_param('i', $imageId);
            $stmt->execute();
            $stmt->close();
            logActivity($conn, 'admin', $adminId, "Deleted gallery image #{$imageId}");
        }
    }
}

$images = [];
if ($conn) {
    $images = $conn->query(
        "SELECT g.*, a.full_name AS uploader_name FROM gallery g
         LEFT JOIN admins a ON g.uploaded_by = a.admin_id ORDER BY g.uploaded_at DESC"
    )->fetch_all(MYSQLI_ASSOC);
}

$pageTitle = 'Gallery Management';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo $pageTitle; ?> | <?php echo SITE_NAME; ?></title>
<link rel="icon" type="image/svg+xml" href="<?php echo ASSETS_URL; ?>/images/logo.svg">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/includes/dashboard.css">
<style>
  .gallery-grid{ display:grid; grid-template-columns:repeat(auto-fill,minmax(200px,1fr)); gap:16px; }
  .gallery-card{ border:1px solid var(--border); border-radius:var(--radius-sm); overflow:hidden; background:var(--surface); }
  .gallery-card img{ width:100%; height:150px; object-fit:cover; display:block; }
  .gallery-card-body{ padding:10px 12px; }
  .gallery-card-title{ font-weight:600; font-size:.85rem; margin:0 0 4px; }
  .gallery-card-meta{ font-size:.72rem; color:var(--text-muted); }
</style>
</head>
<body class="admin-body">
<div id="pageLoadingOverlay" class="page-loading-overlay"><span class="dash-spinner"></span></div>
<div class="admin-layout">
  <?php require_once __DIR__ . '/../../includes/admin_sidebar.php'; ?>
  <main class="admin-main">
    <?php require_once __DIR__ . '/../../includes/admin_topbar.php'; ?>
    <div class="admin-content">

      <div class="page-heading">
        <div><h1>Gallery Management</h1><p>Photos shown on the public Gallery page.</p></div>
      </div>

      <?php if ($uploaded): ?><div class="alert alert-success">Image uploaded successfully.</div><?php endif; ?>
      <?php if (!empty($errors)): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e): ?><li><?php echo safeOut($e); ?></li><?php endforeach; ?></ul></div><?php endif; ?>

      <div class="panel">
        <div class="panel-head"><h2><i class="fa-solid fa-upload me-2"></i>Upload Image</h2></div>
        <form method="POST" enctype="multipart/form-data" class="row g-3">
          <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
          <input type="hidden" name="form_type" value="upload">
          <div class="col-md-5">
            <label class="form-label fw-semibold">Title (optional)</label>
            <input type="text" name="title" class="form-control" maxlength="150">
          </div>
          <div class="col-md-5">
            <label class="form-label fw-semibold">Image *</label>
            <input type="file" name="image" class="form-control" accept=".jpg,.jpeg,.png" required>
            <div class="form-text">JPG or PNG, up to 2MB.</div>
          </div>
          <div class="col-md-2 d-flex align-items-end">
            <button type="submit" class="btn btn-primary w-100"><i class="fa-solid fa-upload me-1"></i>Upload</button>
          </div>
        </form>
      </div>

      <div class="panel">
        <div class="panel-head"><h2>All Images <span class="panel-sub">(<?php echo count($images); ?>)</span></h2></div>
        <?php if (empty($images)): ?>
          <p class="text-muted mb-0">No images uploaded yet.</p>
        <?php else: ?>
          <div class="gallery-grid">
            <?php foreach ($images as $img): ?>
              <div class="gallery-card">
                <img src="<?php echo UPLOADS_URL . '/' . $img['image']; ?>" alt="<?php echo safeOut($img['title'] ?? 'Gallery image'); ?>">
                <div class="gallery-card-body">
                  <p class="gallery-card-title"><?php echo safeOut($img['title'] ?: 'Untitled'); ?></p>
                  <p class="gallery-card-meta mb-2"><?php echo timeAgo($img['uploaded_at']); ?><?php echo $img['uploader_name'] ? ' &middot; ' . safeOut($img['uploader_name']) : ''; ?></p>
                  <form method="POST" onsubmit="return confirm('Delete this image?');">
                    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                    <input type="hidden" name="form_type" value="delete">
                    <input type="hidden" name="image_id" value="<?php echo $img['image_id']; ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger w-100"><i class="fa-solid fa-trash me-1"></i>Delete</button>
                  </form>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

    </div>
  <?php require_once __DIR__ . '/../../includes/admin_footer.php'; ?>
