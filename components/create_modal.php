<?php
require_once __DIR__ . '/../views/_page_helpers.php';

$modalProfilePic = $_SESSION['profile_pic'] ?? '';
$modalUsername = $_SESSION['username'] ?? 'you';
$modalProfilePic = profile_avatar($modalProfilePic, $modalUsername);
?>

<div class="modal fade" id="create_modal" tabindex="-1" aria-labelledby="createModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable create-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title w-100 text-center" id="createModalLabel">Create new post</h5>
                <button class="btn btn-link p-0 ms-auto" id="nextPostBtn" style="display:none"></button>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form class="modal-body text-center" action="<?= htmlspecialchars(app_url('views/create_post.php')) ?>" method="POST" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="upload_type" id="modalUploadType" value="post">

                <div id="uploadPrompt">
                    <div class="create_type_tabs" role="group" aria-label="Create type">
                        <button type="button" class="active" data-upload-type="post">Post</button>
                        <button type="button" data-upload-type="story">Story</button>
                        <button type="button" data-upload-type="reel">Reel</button>
                    </div>

                    <div class="upload_icon_stack mb-3">
                        <i class="fa-regular fa-image"></i>
                        <i class="fa-regular fa-circle-play"></i>
                    </div>
                    <p class="upload_title mb-1" id="modalUploadTitle">Drag photos and videos here</p>
                    <p class="upload_hint mb-3" id="modalUploadHint">Share a post on your profile and feed.</p>
                    <label class="btn ig-blue-btn">
                        Select from computer
                        <input type="file" id="modalFileInput" name="media" accept="image/*,video/*" class="d-none" required>
                    </label>
                </div>

                <div id="uploadPreview" class="d-none">
                    <div id="mediaPreviewBox"></div>
                    <div class="modal_composer">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <img src="<?= htmlspecialchars($modalProfilePic) ?>" class="rounded-circle" width="36" height="36" alt="profile">
                            <strong id="modalUsername"><?= htmlspecialchars($modalUsername) ?></strong>
                        </div>
                        <textarea id="modalCaption" name="caption" class="form-control mb-2" rows="4" placeholder="Write a caption..."></textarea>
                        <input type="text" id="modalLocation" name="location" class="form-control mb-3" placeholder="Add location">
                        <input type="text" id="modalTags" name="tags" class="form-control mb-3" placeholder="Tag followers: @username">
                        <button type="submit" class="btn ig-blue-btn w-100" id="modalShareBtn">Share</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const modalTypeInput = document.getElementById('modalUploadType');
const modalTypeButtons = document.querySelectorAll('#create_modal [data-upload-type]');
const modalTitle = document.getElementById('createModalLabel');
const modalUploadTitle = document.getElementById('modalUploadTitle');
const modalUploadHint = document.getElementById('modalUploadHint');
const modalFileInput = document.getElementById('modalFileInput');
const modalLocation = document.getElementById('modalLocation');
const modalShareBtn = document.getElementById('modalShareBtn');
const modalCopy = {
    post: {
        heading: 'Create new post',
        title: 'Drag photos and videos here',
        hint: 'Share a post on your profile and feed.',
        accept: 'image/*,video/*',
        button: 'Share'
    },
    story: {
        heading: 'Create new story',
        title: 'Add to your story',
        hint: 'Stories stay visible for 24 hours.',
        accept: 'image/*,video/*',
        button: 'Share story'
    },
    reel: {
        heading: 'Create new reel',
        title: 'Upload a reel video',
        hint: 'Choose a video to publish in Reels.',
        accept: 'video/*',
        button: 'Share reel'
    }
};

function setCreateType(type) {
    const config = modalCopy[type] || modalCopy.post;
    modalTypeInput.value = type;
    modalTitle.textContent = config.heading;
    modalUploadTitle.textContent = config.title;
    modalUploadHint.textContent = config.hint;
    modalFileInput.accept = config.accept;
    modalShareBtn.textContent = config.button;
    modalLocation.style.display = type === 'story' ? 'none' : '';
    modalTypeButtons.forEach(btn => btn.classList.toggle('active', btn.dataset.uploadType === type));
}

modalTypeButtons.forEach(btn => {
    btn.addEventListener('click', () => setCreateType(btn.dataset.uploadType));
});

document.getElementById('create_modal').addEventListener('show.bs.modal', function(event) {
    const trigger = event.relatedTarget;
    setCreateType(trigger?.dataset?.uploadType || 'post');
});

document.getElementById('create_modal').addEventListener('hidden.bs.modal', function() {
    modalFileInput.value = '';
    document.getElementById('mediaPreviewBox').innerHTML = '';
    document.getElementById('uploadPrompt').classList.remove('d-none');
    document.getElementById('uploadPreview').classList.add('d-none');
});

modalFileInput.addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (!file) return;
    if (modalTypeInput.value === 'reel' && !file.type.startsWith('video/')) {
        alert('Please select a video file for reels.');
        e.target.value = '';
        return;
    }
    const url = URL.createObjectURL(file);
    const box = document.getElementById('mediaPreviewBox');
    box.innerHTML = file.type.startsWith('image/')
        ? `<img src="${url}" alt="Post preview">`
        : `<video src="${url}" controls></video>`;
    document.getElementById('uploadPrompt').classList.add('d-none');
    document.getElementById('uploadPreview').classList.remove('d-none');
});

</script>
