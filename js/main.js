
const appPath = (path) => {
    const baseUrl = (window.INSTA_BASE_URL || '').replace(/\/$/, '');
    const cleanRoutes = {
        'views/activity.php': 'activity',
        'views/appearance.php': 'appearance',
        'views/create_post.php': 'create-post',
        'views/edit_profile.php': 'edit-profile',
        'views/explore.php': 'explore',
        'views/follow.php': 'follow',
        'views/forget.php': 'forgot-password',
        'views/home.php': 'home',
        'views/like.php': 'like',
        'views/logout.php': 'logout',
        'views/messages.php': 'messages',
        'views/notification.php': 'notifications',
        'views/post.php': 'post',
        'views/profile.php': 'profile',
        'views/reels.php': 'reels',
        'views/report_problem.php': 'report-problem',
        'views/saved.php': 'saved',
        'views/search.php': 'search',
        'views/settings.php': 'settings',
        'views/sign_in.php': 'sign-in',
        'views/sign_up.php': 'sign-up',
        'views/single_post.php': 'single-post',
        'views/switch_accounts.php': 'switch-accounts',
        'views/tagged.php': 'tagged',
        'admin/comments.php': 'admin/comments',
        'admin/dashboard.php': 'admin/dashboard',
        'admin/follows.php': 'admin/follows',
        'admin/index.php': 'admin',
        'admin/likes.php': 'admin/likes',
        'admin/logout.php': 'admin/logout',
        'admin/messages.php': 'admin/messages',
        'admin/notifications.php': 'admin/notifications',
        'admin/posts.php': 'admin/posts',
        'admin/register.php': 'admin/register',
        'admin/reports.php': 'admin/reports',
        'admin/users.php': 'admin/users'
    };
    const normalized = path.replace(/^\//, '');
    const split = normalized.match(/^([^?#]*)(.*)$/);
    const route = cleanRoutes[split?.[1] || ''];

    return `${baseUrl}/${route ? route + (split?.[2] || '') : normalized}`;
};

// ── Search toggle ─────────────────────────────────────────────────────────────
const searchToggle = document.getElementById('searchToggle');
const searchSection = document.getElementById('search');
if (searchToggle && searchSection) {
    searchToggle.addEventListener('click', function (e) {
        e.preventDefault();
        searchSection.style.display = searchSection.style.display === 'block' ? 'none' : 'block';
    });
}

// ── Heart / Bookmark visual toggle ───────────────────────────────────────────
document.addEventListener('click', function (e) {
    if (e.target.closest('[data-save-post-id]')) return;
    if (
        e.target.classList.contains('fa-heart') &&
        !e.target.hasAttribute('onclick') &&
        !e.target.closest('.explore_action')
    ) {
        e.target.classList.toggle('fa-regular');
        e.target.classList.toggle('fa-solid');
        e.target.style.color = e.target.classList.contains('fa-solid') ? '#ed4956' : '';
    }
    if (e.target.classList.contains('fa-bookmark')) {
        e.target.classList.toggle('fa-regular');
        e.target.classList.toggle('fa-solid');
    }
});

// ── Save post ─────────────────────────────────────────────────────────────────
function toggleSavePost(saveElement, event) {
    if (event) { event.preventDefault(); event.stopPropagation(); }
    if (!saveElement?.dataset?.savePostId) return;
    fetch(appPath('controllers/toggle_save.php'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ post_id: saveElement.dataset.savePostId })
    })
    .then(r => { if (!r.ok) throw new Error(); return r.json(); })
    .then(data => {
        if (!data.success) throw new Error();
        const saved = data.status === 'saved';
        document.querySelectorAll(`[data-save-post-id="${saveElement.dataset.savePostId}"]`).forEach(el => {
            const icon = el.classList.contains('fa-bookmark') ? el : el.querySelector('.fa-bookmark');
            if (!icon) return;
            icon.classList.toggle('fa-solid', saved);
            icon.classList.toggle('fa-regular', !saved);
        });
    })
    .catch(() => { window.location.href = appPath('views/sign_in.php'); });
}

document.addEventListener('click', function (e) {
    const saveIcon = e.target.closest('[data-save-post-id]');
    if (!saveIcon) return;
    toggleSavePost(saveIcon, e);
});

// ── Like ──────────────────────────────────────────────────────────────────────
function toggleLike(icon, postId) {
    fetch(appPath('controllers/toggle_like.php'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ post_id: postId })
    })
    .then(r => { if (!r.ok) throw new Error(); return r.json(); })
    .then(data => {
        if (data.status === 'liked') {
            icon.classList.remove('fa-regular');
            icon.classList.add('fa-solid', 'text-danger');
        } else {
            icon.classList.remove('fa-solid', 'text-danger');
            icon.classList.add('fa-regular');
        }
        const likesCount = document.getElementById('likes-count-' + postId);
        if (likesCount) {
            likesCount.innerText = likesCount.dataset.countFormat === 'number'
                ? Number(data.total || 0).toLocaleString()
                : data.total + ' likes';
        }
    })
    .catch(() => { window.location.href = appPath('views/sign_in.php'); });
}

// ── Comment icon click → focus input ─────────────────────────────────────────
document.addEventListener('click', function (e) {
    const commentTrigger = e.target.closest('[data-comment-post-id]');
    if (!commentTrigger) return;
    e.preventDefault();
    const postId = commentTrigger.dataset.commentPostId;

    // Profile modal check
    const profileModal = document.getElementById('profilePostPreview' + postId);
    if (profileModal) {
        const focusInput = () => {
            const input = profileModal.querySelector('input[name="comment"]');
            if (input) { input.focus(); input.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
        };
        if (profileModal.classList.contains('show')) {
            focusInput();
        } else {
            profileModal.addEventListener('shown.bs.modal', function h() {
                focusInput();
                profileModal.removeEventListener('shown.bs.modal', h);
            });
            bootstrap.Modal.getOrCreateInstance(profileModal).show();
        }
        return;
    }

    // Home feed
    const input = document.querySelector(`.comment_form[data-post-id="${postId}"] input[name="comment"]`);
    if (input) { input.focus(); input.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
});

// ── Share icon click → bottom sheet ──────────────────────────────────────────
document.addEventListener('click', function (e) {
    const shareTrigger = e.target.closest('[data-message-share], [data-message-post-id]');
    if (!shareTrigger) return;
    // data-share-url wale alag hain
    if (shareTrigger.dataset.shareUrl) return;
    e.preventDefault();
    e.stopPropagation();
    const postId    = shareTrigger.dataset.messagePostId || '';
    const shareText = shareTrigger.dataset.messageShare  || window.location.href;
    openShareSheet(postId, shareText);
});

// ── Copy/native share ─────────────────────────────────────────────────────────
document.addEventListener('click', function (e) {
    const shareTrigger = e.target.closest('[data-share-url]');
    if (!shareTrigger) return;
    e.preventDefault();
    const shareUrl   = shareTrigger.dataset.shareUrl;
    const shareTitle = shareTrigger.dataset.shareTitle || 'Instagram post';
    if (navigator.share) { navigator.share({ title: shareTitle, url: shareUrl }).catch(() => {}); return; }
    if (navigator.clipboard) {
        navigator.clipboard.writeText(shareUrl).then(() => {
            const orig = shareTrigger.getAttribute('title') || '';
            shareTrigger.setAttribute('title', 'Link copied');
            setTimeout(() => shareTrigger.setAttribute('title', orig), 1200);
        }).catch(() => window.prompt('Copy this link', shareUrl));
        return;
    }
    window.prompt('Copy this link', shareUrl);
});

// ── Comment submit (AJAX) ─────────────────────────────────────────────────────
const instaEscapeHtml = (value) => {
    const div = document.createElement('div');
    div.textContent = value || '';
    return div.innerHTML;
};

document.addEventListener('submit', function (e) {
    const form = e.target.closest('.comment_form[data-post-id]');
    if (!form) return;
    e.preventDefault();
    const postId  = form.dataset.postId;
    const input   = form.querySelector('input[name="comment"]');
    const comment = (input?.value || '').trim();
    if (!comment) return;
    fetch(form.action, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
        body: new URLSearchParams({ post_id: postId, comment: comment })
    })
    .then(r => { if (!r.ok) throw new Error(); return r.json(); })
    .then(data => {
        if (data.status !== 'ok') throw new Error();
        const commentsList  = document.getElementById('comments-list-' + postId);
        const commentsCount = document.getElementById('comments-count-' + postId);
        if (commentsList) {
            const delBtn = data.comment.id
                ? `<form action="${appPath('controllers/delete_comment.php')}" method="POST" style="display:inline"><input type="hidden" name="comment_id" value="${Number(data.comment.id)}"><button type="submit" class="btn btn-link btn-sm p-0 text-danger"><i class="fa-solid fa-trash"></i></button></form>`
                : '';
            commentsList.insertAdjacentHTML('beforeend',
                `<div class="comment_item mb-1 small d-flex align-items-start gap-2" data-comment-id="${Number(data.comment.id||0)}"><p class="mb-0 flex-grow-1"><strong>${instaEscapeHtml(data.comment.username)}</strong> ${instaEscapeHtml(data.comment.comment)}</p>${delBtn}</div>`);
            commentsList.scrollTop = commentsList.scrollHeight;
        }
        if (commentsCount) commentsCount.innerText = 'View all ' + data.total + ' comments';
        const reelCount = document.getElementById('reel-comments-count-' + postId);
        if (reelCount) reelCount.textContent = Number(data.total || 0).toLocaleString();
        input.value = '';
    })
    .catch(() => {
        const button = form.querySelector('button[type="submit"]');
        if (button) {
            const originalText = button.textContent;
            button.textContent = 'Retry';
            setTimeout(() => { button.textContent = originalText || 'Post'; }, 1500);
        }
    });
});

// ── View all comments ─────────────────────────────────────────────────────────
document.addEventListener('click', function (e) {
    const viewButton = e.target.closest('[data-view-comments], .comments_link[id^="comments-count-"]');
    if (!viewButton) return;
    const postId = viewButton.dataset.viewComments || (viewButton.id || '').replace('comments-count-', '');
    const commentsList = document.getElementById('comments-list-' + postId);
    if (!commentsList) return;
    e.preventDefault();
    commentsList.querySelectorAll('.is-hidden-comment').forEach(c => c.classList.remove('is-hidden-comment'));
    viewButton.classList.add('d-none');
});

// ── Delete comment ────────────────────────────────────────────────────────────
document.addEventListener('submit', function (e) {
    const form = e.target.closest('form[action$="delete_comment.php"]');
    if (!form) return;
    e.preventDefault();
    const commentItem  = form.closest('.comment_item');
    const commentsList = form.closest('[id^="comments-list-"]');
    const commentId    = form.querySelector('input[name="comment_id"]')?.value || '';
    fetch(form.action, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
        body: new URLSearchParams({ comment_id: commentId })
    })
    .then(r => { if (!r.ok) throw new Error(); return r.json(); })
    .then(data => {
        if (!data.success) throw new Error();
        commentItem?.remove();
        if (data.post_id) {
            const homeCount = document.getElementById('comments-count-' + data.post_id);
            const reelCount = document.getElementById('reel-comments-count-' + data.post_id);
            if (homeCount) homeCount.innerText = 'View all ' + data.total + ' comments';
            if (reelCount) reelCount.textContent = Number(data.total || 0).toLocaleString();
        }
        if (commentsList && !commentsList.querySelector('.comment_item')) commentsList.innerHTML = '';
    })
    .catch(() => {
        const button = form.querySelector('button[type="submit"]');
        if (button) {
            button.disabled = false;
            button.title = 'Could not delete. Try again.';
        }
    });
});

// ── Follow pill ───────────────────────────────────────────────────────────────
document.addEventListener('click', function (e) {
    const btn = e.target.closest('.follow-pill[data-user-id]');
    if (!btn) return;
    fetch(appPath('controllers/toggle_follow.php'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ following_id: btn.dataset.userId })
    })
    .then(r => { if (!r.ok) throw new Error(); return r.json(); })
    .then(data => {
        if (!data.success) throw new Error();
        document.querySelectorAll(`.follow-pill[data-user-id="${btn.dataset.userId}"]`).forEach(b => {
            b.dataset.state = data.state;
            b.textContent   = data.state === 'following' ? 'Following' : 'Follow';
            b.classList.toggle('is-following', data.state === 'following');
        });
    })
    .catch(() => { window.location.href = appPath('views/sign_in.php'); });
});

// ── Share Bottom Sheet ────────────────────────────────────────────────────────
let _sharePostId   = '';
let _shareUrl      = '';
let _shareAllUsers = [];
let _shareSentIds  = new Set();

function openShareSheet(postId, shareText) {
    _sharePostId  = postId  || '';
    _shareUrl     = shareText || window.location.href;
    _shareSentIds.clear();

    // Create sheet if not exists
    if (!document.getElementById('shareBottomSheet')) {
        _buildShareSheet();
    }

    const sheet    = document.getElementById('shareBottomSheet');
    const searchEl = document.getElementById('shareSheetSearch');
    const usersEl  = document.getElementById('shareSheetUsers');

    searchEl.value = '';
    usersEl.innerHTML = '<p style="color:#8e8e8e;font-size:13px;padding:8px;">Loading...</p>';
    sheet.style.display = 'flex';
    document.body.style.overflow = 'hidden';

    fetch(appPath('controllers/get_share_users.php'), { credentials: 'same-origin' })
        .then(r => r.json())
        .then(data => {
            _shareAllUsers = data.users || [];
            _renderShareUsers(_shareAllUsers);
        })
        .catch(() => {
            document.getElementById('shareSheetUsers').innerHTML =
                '<p style="color:#8e8e8e;font-size:13px;padding:8px;">Could not load users.</p>';
        });
}

function _closeShareSheet() {
    const sheet = document.getElementById('shareBottomSheet');
    if (sheet) sheet.style.display = 'none';
    document.body.style.overflow = '';
}

function _renderShareUsers(list) {
    const usersEl = document.getElementById('shareSheetUsers');
    if (!usersEl) return;
    if (!list.length) {
        usersEl.innerHTML = '<p style="color:#8e8e8e;font-size:13px;padding:8px;">No users found.</p>';
        return;
    }
    usersEl.innerHTML = '';
    list.forEach(u => {
        const sent = _shareSentIds.has(u.id);
        const div  = document.createElement('div');
        div.style.cssText = 'display:flex;flex-direction:column;align-items:center;gap:5px;width:76px;cursor:pointer;padding:4px;';
        div.dataset.shareUserId = u.id;
        div.innerHTML = `
            <div style="position:relative;">
                <img src="${u.avatar}" alt="${u.username}"
                    style="width:58px;height:58px;border-radius:50%;object-fit:cover;
                           border:2px solid ${sent ? '#0095f6' : '#dbdbdb'};">
                ${sent ? '<div style="position:absolute;bottom:1px;right:1px;width:18px;height:18px;background:#0095f6;border-radius:50%;display:flex;align-items:center;justify-content:center;border:2px solid #fff;"><i class=\'fa-solid fa-check\' style=\'color:#fff;font-size:9px;\'></i></div>' : ''}
            </div>
            <span style="font-size:11px;text-align:center;width:72px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:#262626;">${instaEscapeHtml(u.username)}</span>
            <span style="font-size:10px;color:${sent ? '#0095f6' : '#8e8e8e'};">${sent ? 'Sent' : 'Send'}</span>`;
        div.addEventListener('click', () => _sendToUser(u.id, u.username));
        usersEl.appendChild(div);
    });
}

function _sendToUser(userId, username) {
    if (_shareSentIds.has(userId)) return;
    _shareSentIds.add(userId);

    const params = new URLSearchParams();
    if (_sharePostId) params.set('share_post_id', _sharePostId);
    else params.set('share', _shareUrl);
    params.set('receiver_id', userId);
    params.set('message', _sharePostId ? '__POST_SHARE__:' + _sharePostId : _shareUrl);

    const renderSentState = () => {
        const q = document.getElementById('shareSheetSearch')?.value.trim().toLowerCase() || '';
        _renderShareUsers(q ? _shareAllUsers.filter(u => u.username.toLowerCase().includes(q)) : _shareAllUsers);
    };
    const renderFailedState = () => {
        _shareSentIds.delete(userId);
        renderSentState();
    };

    renderSentState();

    fetch(appPath('controllers/send_share_message.php'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        credentials: 'same-origin',
        body: params.toString()
    })
    .then(r => r.json())
    .then(data => {
        if (!data.success) throw new Error(data.message || 'Share failed');
    })
    .catch(renderFailedState);
}

function _absoluteShareUrl(path) {
    const url = path.match(/^https?:\/\//i) ? path : appPath(path);
    return new URL(url, window.location.origin).href;
}

function _openExternalShare(url) {
    const popup = window.open(url, '_blank', 'noopener,noreferrer');
    if (!popup) window.location.href = url;
}

function _buildShareSheet() {
    const html = `
<div id="shareBottomSheet" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.5);align-items:flex-end;justify-content:center;">
  <div style="background:#fff;width:100%;max-width:540px;border-radius:20px 20px 0 0;max-height:82vh;display:flex;flex-direction:column;overflow:hidden;box-shadow:0 -4px 30px rgba(0,0,0,0.2);">
    <div style="display:flex;align-items:center;justify-content:space-between;padding:14px 18px 8px;">
      <button id="shareSheetClose" style="background:none;border:none;font-size:20px;cursor:pointer;color:#262626;width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;">&#10005;</button>
      <span style="font-weight:700;font-size:16px;">Share</span>
      <span style="width:32px;"></span>
    </div>
    <div style="padding:0 14px 10px;">
      <div style="background:#efefef;border-radius:10px;display:flex;align-items:center;padding:8px 12px;gap:8px;">
        <i class="fa-solid fa-magnifying-glass" style="color:#8e8e8e;font-size:13px;"></i>
        <input id="shareSheetSearch" type="text" placeholder="Search" autocomplete="off"
          style="border:none;background:transparent;outline:none;font-size:14px;width:100%;">
      </div>
    </div>
    <div id="shareSheetUsers" style="display:flex;flex-wrap:wrap;gap:4px;padding:4px 14px 10px;overflow-y:auto;flex:1;"></div>
    <div style="height:1px;background:#efefef;flex-shrink:0;"></div>
    <div style="display:flex;gap:6px;padding:14px 14px 20px;overflow-x:auto;flex-shrink:0;">
      <div class="sxt" data-act="copy" style="display:flex;flex-direction:column;align-items:center;gap:5px;min-width:62px;cursor:pointer;">
        <div style="width:50px;height:50px;border-radius:50%;background:#efefef;display:flex;align-items:center;justify-content:center;font-size:18px;"><i class="fa-solid fa-link"></i></div>
        <span style="font-size:11px;color:#262626;">Copy Link</span>
      </div>
      <div class="sxt" data-act="whatsapp" style="display:flex;flex-direction:column;align-items:center;gap:5px;min-width:62px;cursor:pointer;">
        <div style="width:50px;height:50px;border-radius:50%;background:#25D366;display:flex;align-items:center;justify-content:center;font-size:20px;color:#fff;"><i class="fa-brands fa-whatsapp"></i></div>
        <span style="font-size:11px;color:#262626;">WhatsApp</span>
      </div>
      <div class="sxt" data-act="facebook" style="display:flex;flex-direction:column;align-items:center;gap:5px;min-width:62px;cursor:pointer;">
        <div style="width:50px;height:50px;border-radius:50%;background:#1877F2;display:flex;align-items:center;justify-content:center;font-size:20px;color:#fff;"><i class="fa-brands fa-facebook-f"></i></div>
        <span style="font-size:11px;color:#262626;">Facebook</span>
      </div>
      <div class="sxt" data-act="twitter" style="display:flex;flex-direction:column;align-items:center;gap:5px;min-width:62px;cursor:pointer;">
        <div style="width:50px;height:50px;border-radius:50%;background:#000;display:flex;align-items:center;justify-content:center;font-size:20px;color:#fff;"><i class="fa-brands fa-x-twitter"></i></div>
        <span style="font-size:11px;color:#262626;">X</span>
      </div>
      <div class="sxt" data-act="email" style="display:flex;flex-direction:column;align-items:center;gap:5px;min-width:62px;cursor:pointer;">
        <div style="width:50px;height:50px;border-radius:50%;background:#efefef;display:flex;align-items:center;justify-content:center;font-size:18px;"><i class="fa-regular fa-envelope"></i></div>
        <span style="font-size:11px;color:#262626;">Email</span>
      </div>
    </div>
  </div>
</div>`;
    document.body.insertAdjacentHTML('beforeend', html);

    document.getElementById('shareSheetClose').addEventListener('click', _closeShareSheet);
    document.getElementById('shareBottomSheet').addEventListener('click', e => {
        if (e.target === document.getElementById('shareBottomSheet')) _closeShareSheet();
    });
    document.getElementById('shareSheetSearch').addEventListener('input', e => {
        const q = e.target.value.trim().toLowerCase();
        _renderShareUsers(q ? _shareAllUsers.filter(u => u.username.toLowerCase().includes(q)) : _shareAllUsers);
    });
    document.querySelectorAll('.sxt').forEach(btn => {
        btn.addEventListener('click', () => {
            const link = _sharePostId
                ? _absoluteShareUrl('views/single_post.php?id=' + encodeURIComponent(_sharePostId))
                : _absoluteShareUrl(_shareUrl);
            const act = btn.dataset.act;
            if (act === 'copy') {
                navigator.clipboard?.writeText(link).then(() => {
                    const sp = btn.querySelector('span');
                    sp.textContent = 'Copied!';
                    setTimeout(() => sp.textContent = 'Copy Link', 1500);
                });
            } else if (act === 'whatsapp') _openExternalShare('https://wa.me/?text=' + encodeURIComponent(link));
            else if (act === 'facebook') _openExternalShare('https://www.facebook.com/sharer/sharer.php?u=' + encodeURIComponent(link));
            else if (act === 'twitter') _openExternalShare('https://twitter.com/intent/tweet?url=' + encodeURIComponent(link));
            else if (act === 'email') window.location.href = 'mailto:?body=' + encodeURIComponent(link);
        });
    });
}

document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && document.getElementById('shareBottomSheet')?.style.display === 'flex') {
        _closeShareSheet();
    }
});
