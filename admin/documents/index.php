<?php
/**
 * ============================================================
 * admin/documents/index.php
 * ------------------------------------------------------------
 * Document management (handbooks, forms, policies, etc.) that
 * coaches/players can download. Admin manages the full list here;
 * the `documents` table also supports coach/player uploads
 * (uploader_role), so this listing shows everyone's uploads,
 * but only Admin can delete from this page.
 * ============================================================
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/middleware.php';

requireRole('admin');

$errors = [];
$uploaded = false;
$adminId = (int) $_SESSION['user_id'];

$allowedExt = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'];
$maxBytes = 10 * 1024 * 1024; // 10MB

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $conn) {
    if (($_POST['form_type'] ?? '') === 'upload' && verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $title = cleanInput($_POST['title'] ?? '');
        $fileCheck = validateUploadedFile($_FILES['document'] ?? [], $allowedExt, $maxBytes);

        if (empty($_FILES['document']['name'])) {
            $errors[] = 'Please choose a file to upload.';
        } elseif (!$fileCheck['valid']) {
            $errors[] = $fileCheck['error'];
        } else {
            $result = storeUploadedFile($_FILES['document'], 'documents');
            if ($result['success'] && $result['path']) {
                $titleVal = $title !== '' ? $title : $_FILES['document']['name'];
                $stmt = $conn->prepare('INSERT INTO documents (title, file_path, uploaded_by, uploader_role) VALUES (?, ?, ?, ?)');
                $role = 'admin';
                $stmt->bind_param('ssis', $titleVal, $result['path'], $adminId, $role);
                $stmt->execute();
                $stmt->close();
                logActivity($conn, 'admin', $adminId, "Uploaded document: {$titleVal}");
                $uploaded = true;
            } else {
                $errors[] = $result['error'] ?? 'Could not save the file.';
            }
        }
    } elseif (($_POST['form_type'] ?? '') === 'delete' && verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $docId = (int) ($_POST['document_id'] ?? 0);
        $stmt = $conn->prepare('SELECT file_path FROM documents WHERE document_id = ? LIMIT 1');
        $stmt->bind_param('i', $docId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row) {
            deleteUploadedFile($row['file_path']);
            $stmt = $conn->prepare('DELETE FROM documents WHERE document_id = ?');
            $stmt->bind_param('i', $docId);
            $stmt->execute();
            $stmt->close();
            logActivity($conn, 'admin', $adminId, "Deleted document #{$docId}");
        }
    }
}

$documents = [];
if ($conn) {
    $documents = $conn->query('SELECT * FROM documents ORDER BY uploaded_at DESC')->fetch_all(MYSQLI_ASSOC);
    foreach ($documents as &$d) {
        $name = null;
        if ($d['uploader_role'] && $d['uploaded_by']) {
            $table = $d['uploader_role'] === 'admin' ? 'admins' : ($d['uploader_role'] === 'coach' ? 'coaches' : 'players');
            $idCol = $d['uploader_role'] === 'admin' ? 'admin_id' : ($d['uploader_role'] === 'coach' ? 'coach_id' : 'player_id');
            $stmt = $conn->prepare("SELECT full_name FROM {$table} WHERE {$idCol} = ? LIMIT 1");
            $stmt->bind_param('i', $d['uploaded_by']);
            $stmt->execute();
            $name = $stmt->get_result()->fetch_assoc()['full_name'] ?? null;
            $stmt->close();
        }
        $d['uploader_name'] = $name;
    }
    unset($d);
}

$pageTitle = 'Documents Management';
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
</head>
<body class="admin-body">
<div id="pageLoadingOverlay" class="page-loading-overlay"><span class="dash-spinner"></span></div>
<div class="admin-layout">
  <?php require_once __DIR__ . '/../../includes/admin_sidebar.php'; ?>
  <main class="admin-main">
    <?php require_once __DIR__ . '/../../includes/admin_topbar.php'; ?>
    <div class="admin-content">

      <div class="page-heading">
        <div><h1>Documents Management</h1><p>Handbooks, forms, and policies coaches and players can download.</p></div>
      </div>

      <?php if ($uploaded): ?><div class="alert alert-success">Document uploaded successfully.</div><?php endif; ?>
      <?php if (!empty($errors)): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e): ?><li><?php echo safeOut($e); ?></li><?php endforeach; ?></ul></div><?php endif; ?>

      <div class="panel">
        <div class="panel-head"><h2><i class="fa-solid fa-upload me-2"></i>Upload Document</h2></div>
        <form method="POST" enctype="multipart/form-data" class="row g-3">
          <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
          <input type="hidden" name="form_type" value="upload">
          <div class="col-md-5">
            <label class="form-label fw-semibold">Title (optional)</label>
            <input type="text" name="title" class="form-control" maxlength="150" placeholder="e.g. Player Code of Conduct">
          </div>
          <div class="col-md-5">
            <label class="form-label fw-semibold">File *</label>
            <input type="file" name="document" class="form-control" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx" required>
            <div class="form-text">PDF, Word, Excel, or PowerPoint — up to 10MB.</div>
          </div>
          <div class="col-md-2 d-flex align-items-end">
            <button type="submit" class="btn btn-primary w-100"><i class="fa-solid fa-upload me-1"></i>Upload</button>
          </div>
        </form>
      </div>

      <div class="panel">
        <div class="panel-head"><h2>All Documents <span class="panel-sub">(<?php echo count($documents); ?>)</span></h2></div>
        <?php if (empty($documents)): ?>
          <p class="text-muted mb-0">No documents uploaded yet.</p>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table align-middle">
              <thead><tr><th>Title</th><th>Uploaded By</th><th>Date</th><th>Actions</th></tr></thead>
              <tbody>
                <?php foreach ($documents as $d): ?>
                  <tr>
                    <td><i class="fa-solid fa-file-lines me-2 text-muted"></i><?php echo safeOut($d['title'] ?: 'Untitled'); ?></td>
                    <td><?php echo safeOut($d['uploader_name'] ?? '—'); ?> <span class="text-muted small">(<?php echo safeOut(ucfirst($d['uploader_role'] ?? '')); ?>)</span></td>
                    <td><?php echo timeAgo($d['uploaded_at']); ?></td>
                    <td class="text-nowrap">
                      <a href="<?php echo UPLOADS_URL . '/' . $d['file_path']; ?>" target="_blank" class="table-action-btn" title="Download"><i class="fa-solid fa-download"></i></a>
                      <form method="POST" class="d-inline" onsubmit="return confirm('Delete this document?');">
                        <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                        <input type="hidden" name="form_type" value="delete">
                        <input type="hidden" name="document_id" value="<?php echo $d['document_id']; ?>">
                        <button type="submit" class="table-action-btn" title="Delete"><i class="fa-solid fa-trash text-danger"></i></button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>

    </div>
  <?php require_once __DIR__ . '/../../includes/admin_footer.php'; ?>
