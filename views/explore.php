<?php
// views/explore.php

if (!defined('MVC_RENDERING')) {
    require_once __DIR__ . '/../controllers/FrontController.php';
    FrontController::dispatchView('explore');
    return;
}

require_once __DIR__ . '/_page_helpers.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . app_url('views/sign_in.php'));
    exit;
}

$pageTitle  = 'Instagram – Explore';
$activePage = 'explore';


// Search
$search = $search ?? trim($_GET['search'] ?? '');
$posts = $posts ?? [];


include __DIR__ . '/../components/head.php';
?>

<style>
.explore_search{
    max-width:420px;
}

.explore_container .row{
    overflow:visible;
}

/* ── Grid item ── */
.explore_item{
    position:relative;
    overflow:visible;
    z-index:1;
}
.explore_item:hover{
    z-index:20;
}

/* ── Card ── */
.explore_card{
    position:relative;
    aspect-ratio:1;
    overflow:hidden;
    cursor:pointer;
    background:#111;
    border-radius:2px;
    transition: transform 0.28s cubic-bezier(0.25,0.46,0.45,0.94),
                box-shadow 0.28s ease;
}
.explore_card:hover{
    transform: scale(1.12);
    box-shadow: 0 10px 36px rgba(0,0,0,0.42);
    border-radius:6px;
    z-index:20;
}

/* ── Media ── */
.explore_media_wrap{
    width:100%;
    height:100%;
    overflow:hidden;
}
.explore_card img,
.explore_card video{
    width:100%;
    height:100%;
    object-fit:cover;
    display:block;
    transition: transform 0.28s cubic-bezier(0.25,0.46,0.45,0.94);
}
.explore_card:hover img,
.explore_card:hover video{
    transform: scale(1.05);
}

/* ── Overlay ── */
.explore_overlay{
    position:absolute;
    inset:0;
    background:rgba(0,0,0,.42);
    opacity:0;
    transition: opacity 0.22s ease;
    display:flex;
    align-items:center;
    justify-content:center;
    gap:24px;
    color:#fff;
    font-weight:700;
    z-index:8;
}
.explore_card:hover .explore_overlay{ opacity:1; }

.explore_action{
    border:0;
    background:transparent;
    color:#fff;
    padding:0;
    font:inherit;
    cursor:pointer;
}

.explore_user{
    position:absolute;
    top:8px;
    left:8px;
    background:rgba(0,0,0,.55);
    color:#fff;
    padding:3px 9px;
    border-radius:20px;
    font-size:12px;
    z-index:5;
    pointer-events:none;
}
.video_icon{
    position:absolute;
    top:8px;
    right:8px;
    color:#fff;
    z-index:5;
    font-size:16px;
    pointer-events:none;
}

/* ── Lightbox ── */
#exploreLightbox{
    display:none;
    position:fixed;
    inset:0;
    background:rgba(0,0,0,0.88);
    z-index:9999;
    align-items:center;
    justify-content:center;
    cursor:zoom-out;
}
#exploreLightbox.open{
    display:flex;
}
#exploreLightbox .lb_inner{
    position:relative;
    max-width:min(90vw,900px);
    max-height:90vh;
    cursor:default;
    animation: lbIn 0.22s ease;
}
@keyframes lbIn{
    from{ opacity:0; transform:scale(0.92); }
    to  { opacity:1; transform:scale(1); }
}
#exploreLightbox img,
#exploreLightbox video{
    display:block;
    max-width:100%;
    max-height:90vh;
    width:auto;
    height:auto;
    object-fit:contain;
    border-radius:6px;
    box-shadow:0 20px 60px rgba(0,0,0,0.6);
}
#exploreLightbox .lb_close{
    position:absolute;
    top:-14px;
    right:-14px;
    width:34px;
    height:34px;
    border-radius:50%;
    background:#fff;
    color:#111;
    border:none;
    font-size:18px;
    display:grid;
    place-items:center;
    cursor:pointer;
    box-shadow:0 2px 8px rgba(0,0,0,0.3);
    z-index:2;
}

.explore_empty{
    min-height:60vh;
    display:flex;
    justify-content:center;
    align-items:center;
    flex-direction:column;
}

.explore_caption{
    font-size:12px;
    line-height:1.35;
    color:#555;
    padding:6px 2px 10px;
    min-height:28px;
}
</style>


<div class="post_page d-flex">

    <?php include __DIR__ . '/../components/navbar.php'; ?>


    <main class="explore_container flex-grow-1 py-4 px-3">

        <!-- Search -->
        <form method="GET"
              id="exploreSearchForm"
              class="mb-4 explore_search">

            <div class="input-group">

                <span class="input-group-text bg-light border-0">

                    <i class="fa-solid fa-magnifying-glass text-muted"></i>

                </span>

                <input type="search"
                       id="exploreSearchInput"
                       name="search"
                       value="<?= htmlspecialchars($search) ?>"
                       class="form-control bg-light border-0 shadow-none"
                       autocomplete="off"
                       placeholder="Search users, captions, locations...">

            </div>

        </form>


        <div id="exploreResults">

        <?php if(count($posts) > 0): ?>


        <!-- Explore Grid -->
        <div class="row g-1" style="overflow:visible">

            <?php foreach($posts as $post): ?>

            <?php
            $likes    = (int) ($post['total_likes'] ?? 0);
            $comments = (int) ($post['total_comments'] ?? 0);
            $mediaUrl = htmlspecialchars(post_media_url($post['media_path']));
            $isVideo  = ($post['media_type'] === 'video');
            ?>

            <div class="col-6 col-md-4 col-lg-3 explore_item"
                 style="overflow:visible"
                 data-search-text="<?= htmlspecialchars(strtolower(($post['username'] ?? '') . ' ' . ($post['caption'] ?? '') . ' ' . ($post['location'] ?? '')), ENT_QUOTES) ?>">

                <div class="explore_card"
                     data-lb-src="<?= $mediaUrl ?>"
                     data-lb-type="<?= $isVideo ? 'video' : 'image' ?>"
                     onclick="openLightbox(this)">

                    <div class="explore_user">@<?= htmlspecialchars($post['username']) ?></div>

                    <?php if($isVideo): ?>
                    <div class="video_icon"><i class="fa-solid fa-video"></i></div>
                    <?php endif; ?>

                    <div class="explore_media_wrap">
                        <?php if(!$isVideo): ?>
                            <img src="<?= $mediaUrl ?>" alt="post" loading="lazy">
                        <?php else: ?>
                            <video muted preload="metadata">
                                <source src="<?= $mediaUrl ?>">
                            </video>
                        <?php endif; ?>
                    </div>

                    <!-- Hover Overlay -->
                    <div class="explore_overlay">
                        <button type="button"
                                class="explore_action d-flex align-items-center gap-2"
                                onclick="toggleExploreLike(event, this, <?= (int)$post['id'] ?>)">
                            <i class="<?= !empty($post['is_liked']) ? 'fa-solid fa-heart text-danger' : 'fa-regular fa-heart' ?>"></i>
                            <span id="likes-count-<?= (int)$post['id'] ?>" data-count-format="number"><?= number_format($likes) ?></span>
                        </button>

                        <button type="button"
                                class="explore_action d-flex align-items-center gap-2"
                                onclick="quickExploreComment(event, <?= (int)$post['id'] ?>)">
                            <i class="fa-solid fa-comment"></i>
                            <span id="explore-comments-count-<?= (int)$post['id'] ?>"><?= number_format($comments) ?></span>
                        </button>

                        <button type="button"
                                class="explore_action d-flex align-items-center gap-2"
                                data-save-post-id="<?= (int)$post['id'] ?>"
                                onclick="toggleSavePost(this, event)">
                            <i class="<?= !empty($post['is_saved']) ? 'fa-solid' : 'fa-regular' ?> fa-bookmark"></i>
                        </button>
                    </div>

                </div>

                <?php if(trim($post['caption'] ?? '') !== ''): ?>
                    <div class="explore_caption text-truncate">
                        <strong>@<?= htmlspecialchars($post['username'] ?? 'User') ?></strong>
                        <?= htmlspecialchars($post['caption'] ?? '') ?>
                    </div>
                <?php else: ?>
                    <div class="explore_caption text-muted">&nbsp;</div>
                <?php endif; ?>

            </div>

            <?php endforeach; ?>

        </div>


        <?php else: ?>


        <!-- Empty -->
        <div class="explore_empty text-center">

            <i class="fa-solid fa-magnifying-glass fs-1 mb-4"></i>

            <h4>No Results Found</h4>

            <p class="text-muted">
                Try searching something else.
            </p>

        </div>


        <?php endif; ?>

        </div>

    </main>

</div>

<!-- ── Single Lightbox ── -->
<div id="exploreLightbox" role="dialog" aria-modal="true" aria-label="Image preview" onclick="closeLightboxBg(event)">
    <div class="lb_inner">
        <button class="lb_close" onclick="closeLightbox()" aria-label="Close">
            <i class="fa-solid fa-xmark"></i>
        </button>
        <div id="lb_media"></div>
    </div>
</div>

<script>
// ── Lightbox ──────────────────────────────────────────────────────────────────
function openLightbox(card) {
    const src  = card.dataset.lbSrc;
    const type = card.dataset.lbType;
    if (!src) return;

    const box   = document.getElementById('exploreLightbox');
    const media = document.getElementById('lb_media');

    if (type === 'video') {
        media.innerHTML = `<video controls autoplay playsinline src="${src}"></video>`;
    } else {
        media.innerHTML = `<img src="${src}" alt="preview">`;
    }

    box.classList.add('open');
    document.body.style.overflow = 'hidden';
}

function closeLightbox() {
    const box   = document.getElementById('exploreLightbox');
    const media = document.getElementById('lb_media');
    box.classList.remove('open');
    document.body.style.overflow = '';
    media.querySelectorAll('video').forEach(v => { v.pause(); v.src = ''; });
    media.innerHTML = '';
}

function closeLightboxBg(e) {
    // Sirf background click pe close karo
    if (e.target === document.getElementById('exploreLightbox')) closeLightbox();
}

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') closeLightbox();
});

// ── Like / Comment / Save ─────────────────────────────────────────────────────
function toggleExploreLike(event, btn, postId) {
    event.preventDefault();
    event.stopPropagation();
    const icon = btn.querySelector('.fa-heart');
    toggleLike(icon, postId);
}

function quickExploreComment(event, postId) {
    event.preventDefault();
    event.stopPropagation();

    const comment = window.prompt('Add a comment');
    if (!comment || !comment.trim()) return;

    fetch(<?= json_encode(app_url('controllers/add_comment.php')) ?>, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: new URLSearchParams({ post_id: postId, comment: comment.trim() })
    })
    .then(r => r.json())
    .then(data => {
        if (data.status !== 'ok') return;
        const el = document.getElementById('explore-comments-count-' + postId);
        if (el) el.textContent = Number(data.total || 0).toLocaleString();
    })
    .catch(() => {});
}

// ── Search ────────────────────────────────────────────────────────────────────
const exploreSearchForm  = document.getElementById('exploreSearchForm');
const exploreSearchInput = document.getElementById('exploreSearchInput');
const exploreResults     = document.getElementById('exploreResults');
let exploreSearchTimer   = null;
let activeExploreRequest = null;

function filterExploreCards(query) {
    query = (query || '').trim().toLowerCase();
    document.querySelectorAll('#exploreResults .explore_item').forEach(item => {
        const text = item.dataset.searchText || '';
        item.classList.toggle('d-none', query !== '' && !text.includes(query));
    });
}

function refreshExplore(search) {
    const params    = new URLSearchParams();
    if (search) params.set('search', search);
    const targetUrl = <?= json_encode(app_url('views/explore.php')) ?> + (params.toString() ? '?' + params.toString() : '');

    if (activeExploreRequest) activeExploreRequest.abort();
    activeExploreRequest = new AbortController();

    fetch(targetUrl, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        signal: activeExploreRequest.signal
    })
    .then(r => r.text())
    .then(html => {
        const page        = new DOMParser().parseFromString(html, 'text/html');
        const nextResults = page.getElementById('exploreResults');
        if (nextResults && exploreResults) exploreResults.innerHTML = nextResults.innerHTML;
        window.history.replaceState({}, '', targetUrl);
    })
    .catch(err => {
        if (err.name !== 'AbortError') exploreSearchForm.submit();
    });
}

if (exploreSearchInput && exploreSearchForm) {
    exploreSearchInput.addEventListener('input', () => {
        filterExploreCards(exploreSearchInput.value);
        clearTimeout(exploreSearchTimer);
        exploreSearchTimer = setTimeout(() => refreshExplore(exploreSearchInput.value.trim()), 200);
    });
    exploreSearchForm.addEventListener('submit', e => {
        e.preventDefault();
        refreshExplore(exploreSearchInput.value.trim());
    });
}
</script>

<?php include __DIR__ . '/../components/create_modal.php'; ?>
<?php include __DIR__ . '/../components/footer.php'; ?>
