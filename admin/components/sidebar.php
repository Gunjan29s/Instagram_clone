<?php
require_once __DIR__ . '/../../views/_page_helpers.php';
$currentPage = $currentPage ?? '';
?>
<aside class="sidebar">
  <div class="sidebar-logo">
    <div class="li-icon" style="background:rgba(255,255,255,0.15);font-size:13px">
      📸
    </div>

    <div>
      <div class="brand-name">Insta Admin</div>
      <small class="brand-sub">Admin Portal</small>
    </div>
  </div>

  <div class="nav-section">

    <div class="nav-label">
      Overview
    </div>

    <a class="nav-item <?= $currentPage==='dashboard' ? 'active' : '' ?>"
       href="<?= htmlspecialchars(app_url('admin/dashboard.php')) ?>">

      <i class="ti ti-layout-dashboard"></i>
      Dashboard
    </a>

  </div>

  <div class="nav-section">

    <div class="nav-label">
      Management
    </div>
    <a class="nav-item <?= $currentPage==='users' ? 'active' : '' ?>"
       href="<?= htmlspecialchars(app_url('admin/users.php')) ?>">

      <i class="ti ti-users"></i>
      Users
    </a>
    <a class="nav-item <?= $currentPage==='posts' ? 'active' : '' ?>"
       href="<?= htmlspecialchars(app_url('admin/posts.php')) ?>">

      <i class="ti ti-photo"></i>
      Posts
    </a>

    <a class="nav-item <?= $currentPage==='likes' ? 'active' : '' ?>"
       href="<?= htmlspecialchars(app_url('admin/likes.php')) ?>">

      <i class="ti ti-heart"></i>
      Likes
    </a>

    <a class="nav-item <?= $currentPage==='comments' ? 'active' : '' ?>"
       href="<?= htmlspecialchars(app_url('admin/comments.php')) ?>">

      <i class="ti ti-message-circle"></i>
      Comments
    </a>

    <a class="nav-item <?= $currentPage==='follows' ? 'active' : '' ?>"
       href="<?= htmlspecialchars(app_url('admin/follows.php')) ?>">

      <i class="ti ti-user-check"></i>
      Follows
    </a>

    <a class="nav-item <?= $currentPage==='messages' ? 'active' : '' ?>"
       href="<?= htmlspecialchars(app_url('admin/messages.php')) ?>">

      <i class="ti ti-message"></i>
      Messages
    </a>

    <a class="nav-item <?= $currentPage==='notifications' ? 'active' : '' ?>"
       href="<?= htmlspecialchars(app_url('admin/notifications.php')) ?>">

      <i class="ti ti-bell"></i>
      Notifications
    </a>

  </div>

  <div class="nav-section">

    <div class="nav-label">
      Support & Reports
    </div>

    <a class="nav-item <?= $currentPage==='reports' ? 'active' : '' ?>"
       href="<?= htmlspecialchars(app_url('admin/reports.php')) ?>">

      <i class="ti ti-flag"></i>
      Reports
    </a>

  </div>

  <div class="sidebar-bottom">

    <div class="s-user">

      <div class="s-av">
        <?= strtoupper(substr($_SESSION['admin_username'] ?? 'A',0,2)) ?>
      </div>

      <div>

        <div class="s-name">
          <?= htmlspecialchars($_SESSION['admin_username'] ?? 'Admin') ?>
        </div>

        <div class="s-email">
          Administrator
        </div>

      </div>

      <a href="<?= htmlspecialchars(app_url('admin/logout.php')) ?>"
         class="logout-a"
         title="Logout">

        <i class="ti ti-logout"></i>

      </a>

    </div>

  </div>

</aside>


<div class="main">

  <div class="topbar">

    <div class="topbar-left">

      <div>

        <div class="topbar-title">
          <?= htmlspecialchars($pageTitle ?? 'Dashboard') ?>
        </div>

        <div class="breadcrumb">
          Insta Admin &rsaquo;
          <?= htmlspecialchars($pageTitle ?? 'Overview') ?>
        </div>

      </div>

    </div>

    <div class="topbar-right">

      <button class="icon-btn"
              id="theme-toggle-btn"
              title="Toggle Dark Mode"
              onclick="toggleTheme()"
              style="cursor:pointer">

        <i class="ti ti-moon" id="theme-icon"></i>

      </button>

      <div class="profile-wrap" style="position:relative">

        <div class="icon-btn"
             title="Admin"
             onclick="toggleProfileMenu()"
             style="cursor:pointer">

          <div style="
            width:28px;
            height:28px;
            border-radius:50%;
            background:var(--blue);
            display:flex;
            align-items:center;
            justify-content:center;
            font-size:12px;
            font-weight:700;
            color:#fff">

            <?= strtoupper(substr($_SESSION['admin_username'] ?? 'A',0,2)) ?>

          </div>

        </div>

        <div id="profile-menu"
             style="
             display:none;
             position:absolute;
             right:0;
             top:44px;
             background:#fff;
             border:1px solid var(--border);
             border-radius:var(--radius);
             box-shadow:0 4px 20px rgba(0,0,0,0.12);
             min-width:200px;
             z-index:999">

          <div style="
            padding:14px 16px;
            border-bottom:1px solid var(--border)">

            <div style="
              font-size:13px;
              font-weight:700;
              color:var(--text)">

              <?= htmlspecialchars($_SESSION['admin_username'] ?? 'Admin') ?>

            </div>

            <div style="margin-top:4px">

              <span class="badge badge-blue" style="font-size:10px">
                Admin
              </span>

            </div>

          </div>

          <div style="padding:6px 0">

            <a href="<?= htmlspecialchars(app_url('admin/logout.php')) ?>"
               style="
               display:flex;
               align-items:center;
               gap:10px;
               padding:9px 16px;
               font-size:13px;
               color:var(--red);
               text-decoration:none"

               onmouseover="this.style.background='#fff5f5'"
               onmouseout="this.style.background=''"

               onclick="return confirm('Logout karna chahte ho?')">

              <i class="ti ti-logout" style="font-size:16px"></i>

              Logout

            </a>

          </div>

        </div>

      </div>

    </div>

  </div>

  <div class="content">
