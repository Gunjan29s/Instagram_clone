<?php
// views/create_post.php

if (!defined('MVC_RENDERING')) {
    require_once __DIR__ . '/../controllers/FrontController.php';
    FrontController::dispatchView('create_post');
    return;
}

$pageTitle  = 'Create Post';
$activePage = 'create';

$error   = $error ?? '';
$success = $success ?? '';


// Upload Post
include __DIR__ . '/../components/head.php';
?>

<div class="post_page d-flex">

    <?php include __DIR__ . '/../components/navbar.php'; ?>


    <main class="flex-grow-1 py-5 px-3">

        <div class="card shadow-sm border-0 mx-auto"
             style="max-width:700px;border-radius:18px;overflow:hidden;">


            <!-- Header -->
            <div class="card-header bg-white text-center py-3">

                <h5 class="mb-0 fw-bold">
                    Create New Post
                </h5>

            </div>


            <!-- Body -->
            <div class="card-body p-4">


                <?php if($error): ?>

                <div class="alert alert-danger">
                    <?= htmlspecialchars($error) ?>
                </div>

                <?php endif; ?>


                <?php if($success): ?>

                <div class="alert alert-success">
                    <?= htmlspecialchars($success) ?>
                </div>

                <?php endif; ?>


                <!-- Form -->
                <form method="POST"
                      enctype="multipart/form-data"
                      id="create-post-form">

                    <?= csrf_field() ?>
                    <input type="hidden" name="upload_type" id="pageUploadType" value="post">

                    <div class="create_type_tabs mb-4" role="group" aria-label="Create type">
                        <button type="button" class="active" data-page-upload-type="post">Post</button>
                        <button type="button" data-page-upload-type="story">Story</button>
                        <button type="button" data-page-upload-type="reel">Reel</button>
                    </div>


                    <!-- Upload Area -->
                    <div class="upload_box border rounded-4 p-5 text-center mb-4"
                         id="uploadBox"
                         style="border-style:dashed !important;
                                cursor:pointer;
                                background:#fafafa;">

                        <i class="fa-regular fa-images fs-1 mb-3 text-muted"></i>

                        <h5 class="fw-semibold">
                            <span id="pageUploadTitle">Drag photos and videos here</span>
                        </h5>

                        <p class="text-muted small" id="pageUploadHint">
                            Share your moments with everyone
                        </p>

                        <button type="button"
                                class="btn btn-primary rounded-pill px-4 mt-2"
                                onclick="document.getElementById('mediaInput').click()">

                            Select from computer

                        </button>

                        <input type="file"
                               name="media"
                               id="mediaInput"
                               class="d-none"
                               accept="image/*,video/*"
                               required>

                    </div>


                    <!-- Preview -->
                    <div id="previewContainer"
                         class="mb-4 text-center"
                         style="display:none;">

                        <img id="imagePreview"
                             class="img-fluid rounded-4 shadow-sm"
                             style="max-height:400px;display:none;">

                        <video id="videoPreview"
                               controls
                               class="w-100 rounded-4 shadow-sm"
                               style="max-height:400px;display:none;">
                        </video>

                    </div>


                    <!-- Caption -->
                    <div class="mb-3">

                        <label class="form-label fw-semibold">
                            Caption
                        </label>

                        <textarea name="caption"
                                  class="form-control"
                                  rows="4"
                                  maxlength="2200"
                                  placeholder="Write a caption..."></textarea>

                    </div>


                    <!-- Location -->
                    <div class="mb-4">

                        <label class="form-label fw-semibold">
                            Location
                        </label>

                        <input type="text"
                               name="location"
                               id="pageLocation"
                               class="form-control"
                               placeholder="Add location">

                    </div>

                    <div class="mb-4">

                        <label class="form-label fw-semibold">
                            Tag people
                        </label>

                        <input type="text"
                               name="tags"
                               id="pageTags"
                               class="form-control"
                               placeholder="@username followers only">

                    </div>


                    <!-- Submit -->
                    <button type="submit"
                            class="btn btn-primary w-100 py-2 rounded-pill fw-semibold">

                        <span id="pageShareText">Share Post</span>

                    </button>

                </form>

            </div>

        </div>

    </main>

</div>


<script>
const mediaInput = document.getElementById('mediaInput');

const imagePreview = document.getElementById('imagePreview');

const videoPreview = document.getElementById('videoPreview');

const previewContainer = document.getElementById('previewContainer');
const pageUploadType = document.getElementById('pageUploadType');
const pageTypeButtons = document.querySelectorAll('[data-page-upload-type]');
const pageUploadTitle = document.getElementById('pageUploadTitle');
const pageUploadHint = document.getElementById('pageUploadHint');
const pageLocation = document.getElementById('pageLocation');
const pageShareText = document.getElementById('pageShareText');

const pageCopy = {
    post: ['Drag photos and videos here', 'Share your moments with everyone', 'image/*,video/*', 'Share Post'],
    story: ['Add to your story', 'Stories stay visible for 24 hours.', 'image/*,video/*', 'Share Story'],
    reel: ['Upload a reel video', 'Choose a video to publish in Reels.', 'video/*', 'Share Reel']
};

function setPageUploadType(type) {
    const copy = pageCopy[type] || pageCopy.post;
    pageUploadType.value = type;
    pageUploadTitle.textContent = copy[0];
    pageUploadHint.textContent = copy[1];
    mediaInput.accept = copy[2];
    pageShareText.textContent = copy[3];
    pageLocation.closest('.mb-4').style.display = type === 'story' ? 'none' : '';
    pageTypeButtons.forEach(btn => btn.classList.toggle('active', btn.dataset.pageUploadType === type));
}

pageTypeButtons.forEach(btn => {
    btn.addEventListener('click', () => setPageUploadType(btn.dataset.pageUploadType));
});


mediaInput.addEventListener('change', function(e) {

    const file = e.target.files[0];

    if(!file) return;

    if(pageUploadType.value === 'reel' && !file.type.startsWith('video/')) {
        alert('Please select a video file for reels.');
        mediaInput.value = '';
        return;
    }

    const fileURL = URL.createObjectURL(file);

    previewContainer.style.display = 'block';

    imagePreview.style.display = 'none';
    videoPreview.style.display = 'none';

    if(file.type.startsWith('image/')) {

        imagePreview.src = fileURL;
        imagePreview.style.display = 'block';

    } else if(file.type.startsWith('video/')) {

        videoPreview.src = fileURL;
        videoPreview.style.display = 'block';
    }
});


// Drag Drop
const uploadBox = document.getElementById('uploadBox');

uploadBox.addEventListener('dragover', (e) => {
    e.preventDefault();
    uploadBox.classList.add('border-primary');
});

uploadBox.addEventListener('dragleave', () => {
    uploadBox.classList.remove('border-primary');
});

uploadBox.addEventListener('drop', (e) => {

    e.preventDefault();

    uploadBox.classList.remove('border-primary');

    const files = e.dataTransfer.files;

    if(files.length > 0) {

        mediaInput.files = files;

        const event = new Event('change');

        mediaInput.dispatchEvent(event);
    }
});
</script>
<script>
document.getElementById('create-post-form')?.addEventListener('submit', function () {
    const button = this.querySelector('button[type="submit"]');
    const label = document.getElementById('pageShareText');
    if (button) button.disabled = true;
    if (label) label.textContent = 'Uploading...';
});
</script>


<?php include __DIR__ . '/../components/footer.php'; ?>
