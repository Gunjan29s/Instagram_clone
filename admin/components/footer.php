<?php require_once __DIR__ . '/../../views/_page_helpers.php'; ?>
<div id="detail-modal">
  <div class="detail-box">
    <div class="detail-header">
      <div class="detail-header-icon" id="dm-icon-box">U</div>
      <div class="detail-header-info">
        <div class="detail-header-name" id="dm-name">Loading...</div>
        <div class="detail-header-sub" id="dm-sub"></div>
      </div>
      <button class="detail-close" onclick="closeDetailModal()"><i class="ti ti-x"></i></button>
    </div>
    <div class="detail-body" id="dm-body"></div>
    <div class="detail-footer">
      <span class="detail-id-chip" id="dm-id-chip"></span>
      <button class="btn btn-outline btn-sm" onclick="closeDetailModal()"><i class="ti ti-x"></i> Close</button>
    </div>
  </div>
</div>

<script>
const ADMIN_BASE_URL = <?= json_encode(rtrim(app_base_url(), '/')) ?>;
const ADMIN_CSRF_TOKEN = <?= json_encode(csrf_token()) ?>;
const PLACEHOLDER_AVATAR = <?= json_encode(placeholder_avatar('User')) ?>;
const PLACEHOLDER_MEDIA = <?= json_encode(placeholder_media()) ?>;
const adminPath = (path) => {
  const cleanRoutes = {
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
  return `${ADMIN_BASE_URL}/${cleanRoutes[normalized] || normalized}`;
};

function toggleProfileMenu() {
  const menu = document.getElementById('profile-menu');
  menu.style.display = menu.style.display === 'none' ? 'block' : 'none';
}
document.addEventListener('click', function(e) {
  const wrap = document.querySelector('.profile-wrap');
  if (wrap && !wrap.contains(e.target)) document.getElementById('profile-menu').style.display = 'none';
});

(function(){
  const saved = localStorage.getItem('insta_admin_theme');
  if(saved === 'dark') applyDark();
  function applyDark(){ document.body.classList.add('dark-theme'); document.getElementById('theme-icon').className='ti ti-sun'; }
  function applyLight(){ document.body.classList.remove('dark-theme'); document.getElementById('theme-icon').className='ti ti-moon'; }
  window.toggleTheme = function(){
    if(document.body.classList.contains('dark-theme')){ applyLight(); localStorage.setItem('insta_admin_theme','light'); }
    else { applyDark(); localStorage.setItem('insta_admin_theme','dark'); }
  };
})();
function showToast(msg, type='success', dur=4000){
  const icons={success:'ti-circle-check',error:'ti-alert-circle',info:'ti-info-circle'};
  const wrap=document.getElementById('toast-wrap');
  const t=document.createElement('div');
  t.className=`toast toast-${type}`;
  t.innerHTML=`<i class="ti ${icons[type]||icons.info}" style="flex-shrink:0"></i><span class="toast-msg">${msg}</span><button class="toast-close" onclick="this.parentElement.remove()"><i class="ti ti-x"></i></button>`;
  wrap.appendChild(t);
  setTimeout(()=>{t.classList.add('hiding');setTimeout(()=>t.remove(),400)},dur);
}
(function(){
  const IDLE_MINS=15, WARN_SECS=60;
  let idleTimer,countTimer,secs;
  const modal=document.getElementById('idle-modal');
  const counter=document.getElementById('idle-count');
  function startIdle(){ clearTimeout(idleTimer); idleTimer=setTimeout(showWarning,IDLE_MINS*60*1000); }
  function showWarning(){
    secs=WARN_SECS; counter.textContent=secs; modal.classList.add('show');
    countTimer=setInterval(()=>{ secs--; counter.textContent=secs; if(secs<=0){ clearInterval(countTimer); window.location.href=adminPath('admin/logout.php'); } },1000);
  }
  window.resetIdle=function(){ clearInterval(countTimer); modal.classList.remove('show'); startIdle(); };
  ['mousemove','keydown','click','scroll','touchstart'].forEach(e=>
    document.addEventListener(e,()=>{ if(!modal.classList.contains('show')) startIdle(); },{passive:true})
  );
  startIdle();
})();
(function(){
  const modal = document.getElementById('detail-modal');
  window.closeDetailModal = function(){ modal.classList.remove('show'); };
  modal.addEventListener('click', function(e){ if(e.target===modal) closeDetailModal(); });
  document.addEventListener('keydown', function(e){ if(e.key==='Escape') closeDetailModal(); });

  function esc(str){ const d=document.createElement('div'); d.textContent=String(str||''); return d.innerHTML; }
  function fmtDate(d){
    if(!d) return '—';
    try{ return new Date(d).toLocaleDateString('en-IN',{day:'numeric',month:'short',year:'numeric'}); } catch(e){ return d; }
  }
  function badge(text,color){ return `<span class="badge badge-${color||'blue'}">${esc(text||'—')}</span>`; }
  function field(label,value,full){
    if(value===null||value===undefined||value==='') value='<span style="color:#ccc">—</span>';
    return `<div class="detail-field${full?' full':''}"><div class="detail-field-label">${label}</div><div class="detail-field-value">${value}</div></div>`;
  }
  function section(title,fields){
    return `<div class="detail-section"><div class="detail-section-title">${title}</div><div class="detail-grid">${fields}</div></div>`;
  }
  window.openUserModal = function(data){
    const avatar = data.profile_pic || PLACEHOLDER_AVATAR;
    document.getElementById('dm-icon-box').innerHTML = `<img src="${avatar}" onerror="this.onerror=null;this.src='${PLACEHOLDER_AVATAR}'" style="width:48px;height:48px;object-fit:cover;border-radius:12px">`;
    document.getElementById('dm-name').textContent = data.username || 'Unknown User';
    document.getElementById('dm-sub').innerHTML = `<i class="ti ti-mail" style="font-size:11px"></i> ${esc(data.email||'')}`;
    document.getElementById('dm-id-chip').textContent = `User #${data.id}`;

    const banned = data.is_banned==1;
    document.getElementById('dm-body').innerHTML =
      section('Basic Info',
        field('Username', esc(data.username)) +
        field('Full Name', esc(data.full_name)) +
        field('Email', esc(data.email)) +
        field('Status', banned ? badge('Banned','red') : badge('Active','green'))
      ) +
      section('Profile',
        field('Bio', esc(data.bio), true) +
        field('Website', data.website ? `<a href="${esc(data.website)}" target="_blank" style="color:var(--blue)">${esc(data.website)}</a>` : null) +
        field('Gender', esc(data.gender))
      ) +
      section('Stats',
        field('Posts', `<i class="ti ti-photo" style="color:var(--purple)"></i> ${data.post_count||0}`) +
        field('Followers', `<i class="ti ti-users" style="color:var(--blue)"></i> ${data.followers||0}`) +
        field('Joined', fmtDate(data.created_at)) +
        field('User ID', `#${data.id}`)
      );

    document.getElementById('dm-footer-actions') && document.getElementById('dm-footer-actions').remove();
    const footer = document.querySelector('.detail-footer');
    const actDiv = document.createElement('div');
    actDiv.id = 'dm-footer-actions';
    actDiv.style.cssText = 'display:flex;gap:8px;margin-right:auto';
    actDiv.innerHTML = `
      <form method="POST" action="${adminPath('admin/users.php')}" onsubmit="return confirm('Ban this user and delete all their data?')" style="display:inline">
        <input type="hidden" name="csrf_token" value="${ADMIN_CSRF_TOKEN}">
        <input type="hidden" name="user_id" value="${data.id}">
        <button type="submit" name="ban_user" class="btn btn-sm btn-outline" style="border-color:var(--orange);color:var(--orange)">
          <i class="ti ti-ban"></i> Ban
        </button>
      </form>
      <form method="POST" action="${adminPath('admin/users.php')}" onsubmit="return confirm('Delete this user?')" style="display:inline">
        <input type="hidden" name="csrf_token" value="${ADMIN_CSRF_TOKEN}">
        <input type="hidden" name="user_id" value="${data.id}">
        <button type="submit" name="delete_user" class="btn btn-sm btn-danger"><i class="ti ti-trash"></i> Delete</button>
      </form>`;
    footer.insertBefore(actDiv, footer.firstChild.nextSibling);
    modal.classList.add('show');
  };

  window.openPostModal = function(data){
    const imgSrc = data.media_path || PLACEHOLDER_MEDIA;
    document.getElementById('dm-icon-box').innerHTML = `<img src="${imgSrc}" onerror="this.onerror=null;this.src='${PLACEHOLDER_MEDIA}'" style="width:48px;height:48px;object-fit:cover;border-radius:12px">`;
    document.getElementById('dm-name').textContent = `@${data.username || 'unknown'}`;
    document.getElementById('dm-sub').textContent = 'Post / Media';
    document.getElementById('dm-id-chip').textContent = `Post #${data.id}`;

    const bigImg = data.media_path || PLACEHOLDER_MEDIA;
    document.getElementById('dm-body').innerHTML =
      `<div class="post-img-wrap"><img src="${bigImg}" onerror="this.onerror=null;this.src='${PLACEHOLDER_MEDIA}'" alt="post"></div>` +
      section('Post Info',
        field('By', esc(data.username)) +
        field('Email', esc(data.email)) +
        field('Posted', fmtDate(data.created_at)) +
        field('Post ID', `#${data.id}`) +
        field('Caption', esc(data.caption), true)
      ) +
      section('Engagement',
        field('Likes', `<i class="ti ti-heart" style="color:var(--red)"></i> ${data.like_count||0}`) +
        field('Comments', `<i class="ti ti-message" style="color:var(--blue)"></i> ${data.comment_count||0}`)
      );

    document.getElementById('dm-footer-actions') && document.getElementById('dm-footer-actions').remove();
    const footer = document.querySelector('.detail-footer');
    const actDiv = document.createElement('div');
    actDiv.id = 'dm-footer-actions';
    actDiv.style.cssText = 'margin-right:auto';
    const itemType = data.item_type || 'post';
    actDiv.innerHTML = `
      <form method="POST" action="${adminPath('admin/posts.php')}" onsubmit="return confirm('Delete this ${itemType}?')" style="display:inline">
        <input type="hidden" name="csrf_token" value="${ADMIN_CSRF_TOKEN}">
        <input type="hidden" name="post_id" value="${data.id}">
        <input type="hidden" name="item_type" value="${itemType}">
        <button type="submit" name="delete_post" class="btn btn-sm btn-danger"><i class="ti ti-trash"></i> Delete ${itemType}</button>
      </form>`;
    footer.insertBefore(actDiv, footer.firstChild.nextSibling);
    modal.classList.add('show');
  };

})();
</script>
</body>
</html>
