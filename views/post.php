<?php
// views/post.php
if (!defined('MVC_RENDERING')) {
    require_once __DIR__ . '/../controllers/FrontController.php';
    FrontController::dispatchView('post');
    return;
}

$pageTitle  = 'Instagram – Create Post';
$activePage = '';
$success    = $success ?? '';
$error      = $error ?? '';

include __DIR__ . '/../components/head.php';
?>

<div class="post_page d-flex">

    <?php include __DIR__ . '/../components/navbar.php'; ?>

    <main class="flex-grow-1 d-flex align-items-center justify-content-center py-4 px-3">
        <div class="card" style="width:500px;border-radius:18px;overflow:hidden">

            <div class="card-header text-center fw-semibold bg-white border-bottom">
                Create new post
            </div>

            <div class="card-body">
                <?php if ($error):   ?><div class="alert alert-danger small py-2"><?= htmlspecialchars($error) ?></div><?php endif; ?>
                <?php if ($success): ?><div class="alert alert-success small py-2"><?= htmlspecialchars($success) ?></div><?php endif; ?>

                <form method="POST" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <!-- Preview Box -->
                    <div id="previewBox" class="border border-2 border-dashed rounded-3 d-flex align-items-center justify-content-center mb-3"
                         style="height:260px;background:#f8f8f8;overflow:hidden">
                        <div id="previewPlaceholder" class="text-center text-muted">
                            <i class="fa-regular fa-image fa-3x mb-2"></i>
                            <p class="mb-0 small">Select Photo or Video</p>
                        </div>
                    </div>

                    <label class="btn btn-primary w-100 mb-3">
                        Select from computer
                        <input type="file" name="media" id="mediaInput" accept="image/*,video/*" class="d-none" onchange="previewFile(event)" required>
                    </label>

                    <textarea name="caption" class="form-control mb-2" rows="3" placeholder="Write a caption…" required><?= htmlspecialchars($_POST['caption'] ?? '') ?></textarea>
                    <input type="text" name="location" class="form-control mb-3" placeholder="Add location" value="<?= htmlspecialchars($_POST['location'] ?? '') ?>">

                    <button type="submit" class="btn btn-primary w-100 fw-bold">Share</button>
                </form>
            </div>
        </div>
    </main>

</div>

<script>
function previewFile(e) {
    const file = e.target.files[0];
    if (!file) return;
    const url = URL.createObjectURL(file);
    const box = document.getElementById('previewBox');
    document.getElementById('previewPlaceholder').style.display = 'none';
    box.innerHTML = file.type.startsWith('image/')
        ? `<img src="${url}" style="width:100%;height:100%;object-fit:cover">`
        : `<video src="${url}" controls style="width:100%;height:100%;object-fit:cover"></video>`;
}
</script>

<?php include __DIR__ . '/../components/footer.php'; ?>
