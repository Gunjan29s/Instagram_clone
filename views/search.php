<?php
// views/search.php

require_once __DIR__ . '/_page_helpers.php';
app_start_session();

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . app_url('views/sign_in.php'));
    exit;
}

require_once __DIR__ . '/../config/database.php';

$db = Database::getInstance()->getConnection();
$db->exec("
    CREATE TABLE IF NOT EXISTS blocked_users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        blocker_id INT NOT NULL,
        blocked_id INT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_block (blocker_id, blocked_id),
        FOREIGN KEY (blocker_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (blocked_id) REFERENCES users(id) ON DELETE CASCADE
    )
");

$pageTitle  = 'Instagram – Search';
$activePage = 'search';

$current_user_id = $_SESSION['user_id'];


// Search Query
$search = trim($_GET['q'] ?? '');


// Search Users
$users = [];

if (!empty($search)) {

    $stmt = $db->prepare("
        SELECT *
        FROM users
        WHERE
            (username LIKE ? OR full_name LIKE ?)
            AND NOT EXISTS (
                SELECT 1 FROM blocked_users
                WHERE (blocker_id = ? AND blocked_id = users.id)
                   OR (blocker_id = users.id AND blocked_id = ?)
            )
        ORDER BY username ASC
        LIMIT 50
    ");

    $keyword = "%$search%";

    $stmt->execute([
        $keyword,
        $keyword,
        $current_user_id,
        $current_user_id
    ]);

    $users = $stmt->fetchAll();

} else {

    // Suggested Users
    $stmt = $db->prepare("
        SELECT *
        FROM users
        WHERE id != ?
        AND NOT EXISTS (
            SELECT 1 FROM blocked_users
            WHERE (blocker_id = ? AND blocked_id = users.id)
               OR (blocker_id = users.id AND blocked_id = ?)
        )
        ORDER BY RAND()
        LIMIT 20
    ");

    $stmt->execute([$current_user_id, $current_user_id, $current_user_id]);

    $users = $stmt->fetchAll();
}


// Follow Check
function isFollowing($db, $current_user_id, $user_id)
{
    $stmt = $db->prepare("
        SELECT id
        FROM follows
        WHERE follower_id = ?
        AND following_id = ?
    ");

    $stmt->execute([
        $current_user_id,
        $user_id
    ]);

    return $stmt->rowCount() > 0;
}

include __DIR__ . '/../components/head.php';
?>

<style>
.search_wrapper{
    max-width:700px;
    margin:auto;
}

.search_bar{
    position:sticky;
    top:0;
    z-index:20;
    background:#fff;
    padding-bottom:12px;
}

.search_input{
    background:#efefef;
    border:none;
    border-radius:12px;
    padding:12px 16px;
    font-size:15px;
}

.search_input:focus{
    background:#efefef;
    box-shadow:none;
}

.search_user{
    transition:.2s;
    border-radius:14px;
}

.search_user:hover{
    background:#fafafa;
}

.search_profile_link{
    color:inherit;
    min-width:0;
    text-decoration:none;
}

.search_avatar{
    width:58px;
    height:58px;
    border-radius:50%;
    object-fit:cover;
}

.follow_btn{
    border:none;
    background:#0095f6;
    color:#fff;
    padding:7px 16px;
    border-radius:8px;
    font-size:14px;
    font-weight:600;
}

.following_btn{
    border:none;
    background:#efefef;
    color:#000;
    padding:7px 16px;
    border-radius:8px;
    font-size:14px;
    font-weight:600;
}

.search_empty{
    min-height:60vh;
    display:flex;
    justify-content:center;
    align-items:center;
    flex-direction:column;
}

.search_bio{
    font-size:13px;
    color:#8e8e8e;
}

@media(max-width:768px){

    .search_wrapper{
        max-width:100%;
    }
}
</style>


<div class="post_page d-flex">

    <?php include __DIR__ . '/../components/navbar.php'; ?>


    <main class="flex-grow-1 py-4 px-3">

        <div class="search_wrapper">


            <!-- Search -->
            <div class="search_bar">

                <form method="GET" id="userSearchForm">

                    <div class="position-relative">

                        <i class="fa-solid fa-magnifying-glass text-muted"
                           style="
                           position:absolute;
                           left:14px;
                           top:50%;
                           transform:translateY(-50%);
                           ">
                        </i>

                        <input type="search"
                               id="userSearchInput"
                               name="q"
                               value="<?= htmlspecialchars($search) ?>"
                               class="form-control search_input ps-5"
                               placeholder="Search users..."
                               autocomplete="off">

                    </div>

                </form>

            </div>


            <!-- Title -->
            <div class="d-flex justify-content-between align-items-center mt-4 mb-3">

                <h5 class="fw-bold mb-0" id="searchResultsTitle">

                    <?= !empty($search) ? 'Search Results' : 'Suggested Users' ?>

                </h5>

            </div>


            <div id="searchResults">

            <?php if(count($users) > 0): ?>


                <?php foreach($users as $user): ?>


                <?php
                $following = isFollowing(
                    $db,
                    $current_user_id,
                    $user['id']
                );
                ?>


                <div class="search_user d-flex align-items-center gap-3 p-3 mb-2">


                    <a href="<?= htmlspecialchars(app_url('views/profile.php?id=' . (int) $user['id'])) ?>"
                       class="search_profile_link d-flex align-items-center gap-3 flex-grow-1">
                        <!-- Avatar -->
                        <img src="<?= htmlspecialchars(profile_avatar($user['profile_pic'], $user['username'])) ?>"
                             class="search_avatar"
                             alt="user">


                        <!-- Info -->
                        <div class="flex-grow-1 overflow-hidden">

                            <div class="fw-semibold">

                                <?= htmlspecialchars($user['username']) ?>

                            </div>


                            <?php if(!empty($user['full_name'])): ?>

                            <div class="text-muted small">

                                <?= htmlspecialchars($user['full_name']) ?>

                            </div>

                            <?php endif; ?>


                            <?php if(!empty($user['bio'])): ?>

                            <div class="search_bio text-truncate">

                                <?= htmlspecialchars($user['bio']) ?>

                            </div>

                            <?php endif; ?>

                        </div>
                    </a>


                    <!-- Button -->
                    <?php if($user['id'] != $current_user_id): ?>

                    <button type="button"
                            class="<?= $following ? 'following_btn' : 'follow_btn' ?>"
                            data-user-id="<?= (int) $user['id'] ?>"
                            onclick="toggleFollow(this)">

                        <?= $following ? 'Following' : 'Follow' ?>

                    </button>

                    <?php endif; ?>

                </div>

                <?php endforeach; ?>


            <?php else: ?>


            <!-- Empty -->
            <div class="search_empty text-center">

                <i class="fa-solid fa-magnifying-glass fs-1 mb-4 text-muted"></i>

                <h4>No Users Found</h4>

                <p class="text-muted">
                    Try searching with another username.
                </p>

            </div>


            <?php endif; ?>

            </div>

        </div>

    </main>

</div>


<script>
function toggleFollow(btn){
    const userId = btn.dataset.userId;
    if (!userId) return;

    fetch(<?= json_encode(app_url('controllers/toggle_follow.php')) ?>, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: new URLSearchParams({ following_id: userId })
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Follow request failed');
        }
        return response.json();
    })
    .then(data => {
        if (!data.success) {
            throw new Error('Follow was not updated');
        }

        if(data.state === 'following'){
            btn.innerText = 'Following';

            btn.classList.remove('follow_btn');

            btn.classList.add('following_btn');

        }else{

            btn.innerText = 'Follow';

            btn.classList.remove('following_btn');

            btn.classList.add('follow_btn');
        }
    })
    .catch(() => {
        window.location.href = <?= json_encode(app_url('views/sign_in.php')) ?>;
    });
}

const userSearchForm = document.getElementById('userSearchForm');
const userSearchInput = document.getElementById('userSearchInput');
const searchResults = document.getElementById('searchResults');
const searchResultsTitle = document.getElementById('searchResultsTitle');
let searchTimer = null;
let activeSearchRequest = null;

function refreshUserSearch(query) {
    const params = new URLSearchParams();
    if (query) {
        params.set('q', query);
    }

    const targetUrl = <?= json_encode(app_url('views/search.php')) ?> + (params.toString() ? '?' + params.toString() : '');

    if (activeSearchRequest) {
        activeSearchRequest.abort();
    }

    activeSearchRequest = new AbortController();

    fetch(targetUrl, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        signal: activeSearchRequest.signal
    })
    .then(response => response.text())
    .then(html => {
        const page = new DOMParser().parseFromString(html, 'text/html');
        const nextResults = page.getElementById('searchResults');
        const nextTitle = page.getElementById('searchResultsTitle');

        if (nextResults && searchResults) {
            searchResults.innerHTML = nextResults.innerHTML;
        }

        if (nextTitle && searchResultsTitle) {
            searchResultsTitle.textContent = nextTitle.textContent.trim();
        }

        window.history.replaceState({}, '', targetUrl);
    })
    .catch(error => {
        if (error.name !== 'AbortError') {
            userSearchForm.submit();
        }
    });
}

if (userSearchInput && userSearchForm) {
    userSearchInput.addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => {
            refreshUserSearch(userSearchInput.value.trim());
        }, 180);
    });

    userSearchForm.addEventListener('submit', event => {
        event.preventDefault();
        refreshUserSearch(userSearchInput.value.trim());
    });
}
</script>


<?php include __DIR__ . '/../components/create_modal.php'; ?>
<?php include __DIR__ . '/../components/footer.php'; ?>
