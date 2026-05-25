<?php
require_once __DIR__ . '/../../views/_page_helpers.php';
$pageTitle = $pageTitle ?? 'Admin Panel';
$currentPage = $currentPage ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle) ?> | Insta Admin</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<style>
*{box-sizing:border-box;margin:0;padding:0}
:root{
  --blue:#0095f6;--blue-dark:#0081d6;--blue-light:#e8f3fc;
  --green:#057642;--green-light:#e8f5e9;
  --red:#cc1016;--red-light:#fde8e8;
  --orange:#e65100;--orange-light:#fff8e1;
  --purple:#7b1fa2;--purple-light:#f3e5f5;
  --border:#dbdbdb;--bg:#fafafa;--white:#fff;
  --text:#262626;--text2:#555;--text3:#737373;--text4:#aaa;
  --sidebar:240px;
  --radius:8px;--radius-sm:6px;
  --shadow:0 2px 8px rgba(0,0,0,0.08);
}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:var(--bg);display:flex;min-height:100vh;color:var(--text)}

.sidebar{width:var(--sidebar);min-width:var(--sidebar);background:var(--white);border-right:1px solid var(--border);display:flex;flex-direction:column;position:fixed;top:0;left:0;height:100vh;overflow-y:auto;z-index:100}
.sidebar-logo{display:flex;align-items:center;gap:10px;padding:18px;border-bottom:1px solid var(--border);background:linear-gradient(135deg,#1a1a2e 0%,#16213e 100%)}
.li-icon{width:32px;height:32px;background:rgba(255,255,255,0.15);border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:15px;font-weight:900;color:#fff;letter-spacing:-1px}
.brand-name{font-size:16px;font-weight:700;color:#fff}
.brand-sub{font-size:10px;color:rgba(255,255,255,0.55);display:block;margin-top:1px}
.nav-section{padding:10px 0 4px}
.nav-label{font-size:10px;font-weight:700;color:var(--text4);letter-spacing:.09em;text-transform:uppercase;padding:4px 16px 2px}
.nav-item{display:flex;align-items:center;gap:10px;padding:10px 16px;font-size:13px;color:var(--text2);text-decoration:none;border-left:3px solid transparent;transition:all .15s;position:relative}
.nav-item:hover{background:#f5f5f5;color:var(--blue)}
.nav-item.active{background:var(--blue-light);color:var(--blue);border-left-color:var(--blue);font-weight:600}
.nav-item i{font-size:18px;flex-shrink:0}
.ti{display:inline-flex;align-items:center;justify-content:center;min-width:1em;min-height:1em;line-height:1}
.ti::before{font-family:Arial,sans-serif;font-size:.95em;font-weight:700}
.ti-layout-dashboard::before{content:"▦"}
.ti-users::before{content:"U"}
.ti-photo::before{content:"▧"}
.ti-heart::before{content:"♥"}
.ti-message-circle::before,.ti-message::before{content:"✉"}
.ti-user-check::before{content:"✓"}
.ti-bell::before{content:"●"}
.ti-flag::before{content:"⚑"}
.ti-logout::before{content:"↪"}
.ti-moon::before{content:"◐"}
.ti-sun::before{content:"☼"}
.ti-clock::before{content:"◷"}
.ti-refresh::before{content:"↻"}
.ti-x::before{content:"×"}
.ti-search::before{content:"⌕"}
.ti-eye::before{content:"◉"}
.ti-ban::before{content:"⊘"}
.ti-trash::before{content:"⌫"}
.ti-activity::before{content:"⌁"}
.ti-clipboard-list::before{content:"☷"}
.ti-mail::before{content:"@"}
.ti-bell-off::before,.ti-message-off::before{content:"○"}
.sidebar-bottom{margin-top:auto;border-top:1px solid var(--border);padding:14px 16px}
.s-user{display:flex;align-items:center;gap:10px}
.s-av{width:36px;height:36px;border-radius:50%;background:var(--blue);display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;color:#fff;flex-shrink:0}
.s-name{font-size:13px;font-weight:600;color:var(--text)}
.s-email{font-size:11px;color:var(--text3);max-width:130px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.logout-a{margin-left:auto;color:var(--red);font-size:20px;padding:4px;border-radius:4px;transition:opacity .15s;text-decoration:none}
.logout-a:hover{opacity:.7}

.main{margin-left:var(--sidebar);flex:1;display:flex;flex-direction:column;min-height:100vh}
.topbar{background:var(--white);border-bottom:1px solid var(--border);padding:0 24px;height:58px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:50;box-shadow:0 1px 4px rgba(0,0,0,0.04)}
.topbar-left{display:flex;align-items:center;gap:14px}
.topbar-title{font-size:16px;font-weight:700;color:var(--text)}
.breadcrumb{font-size:12px;color:var(--text4);margin-top:2px}
.topbar-right{display:flex;align-items:center;gap:8px}
.icon-btn{width:36px;height:36px;border-radius:50%;background:var(--bg);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;cursor:pointer;transition:background .15s;text-decoration:none}
.icon-btn:hover{background:#e8e8e8}
.icon-btn i{font-size:18px;color:var(--text2)}
.content{padding:24px;flex:1}

#toast-wrap{position:fixed;top:18px;right:18px;z-index:9999;display:flex;flex-direction:column;gap:10px;pointer-events:none}
.toast{display:flex;align-items:center;gap:10px;padding:13px 18px;border-radius:var(--radius);font-size:13px;font-weight:500;box-shadow:0 4px 20px rgba(0,0,0,0.14);min-width:280px;max-width:380px;pointer-events:all;animation:slideIn .3s ease;transition:opacity .4s,transform .4s}
.toast.hiding{opacity:0;transform:translateX(30px)}
.toast-success{background:#fff;border-left:4px solid var(--green);color:#1a6e2e}
.toast-error{background:#fff;border-left:4px solid var(--red);color:#a00}
.toast-info{background:#fff;border-left:4px solid var(--blue);color:var(--blue)}
.toast i{font-size:18px}
.toast-msg{flex:1}
.toast-close{background:none;border:none;cursor:pointer;color:var(--text3);font-size:16px;padding:0;line-height:1}
@keyframes slideIn{from{opacity:0;transform:translateX(30px)}to{opacity:1;transform:none}}

.panel{
  width:100wh;
}

.activity-grid{
  display:flex;
 
  gap:20px;
  width:100%;
}

.act-item{
  width:340px;
  min-height:120px;
  background:var(--white);
  border:1px solid var(--border);
  border-radius:18px;
  padding:18px;
  display:flex;
  align-items:flex-start;
  gap:14px;
  box-shadow:var(--shadow);
  transition:.25s;
}

.act-item:hover{
  transform:translateY(-4px);
}

.stats-grid{
  display:grid;
  grid-template-columns:repeat(auto-fit,minmax(250px,1fr));
  gap:20px;
  margin-bottom:28px;
}

.stat-card{
  background:var(--white);
  border:1px solid var(--border);
  border-radius:22px;
  padding:22px;
  display:flex;
  align-items:center;
  gap:18px;
  box-shadow:var(--shadow);
  transition:all .25s ease;
  position:relative;
  overflow:hidden;
}

.stat-card:hover{
  transform:translateY(-5px);
  box-shadow:0 12px 30px rgba(0,0,0,.12);
}

.stat-icon-box{
  width:64px;
  height:64px;
  border-radius:18px;
  display:flex;
  align-items:center;
  justify-content:center;
  flex-shrink:0;
}

.stat-icon-box i{
  font-size:28px !important;
}

.stat-num{
  font-size:34px;
  font-weight:800;
  color:var(--text);
  line-height:1;
}

.stat-lbl{
  margin-top:6px;
  font-size:14px;
  color:var(--text3);
  font-weight:500;
}

.chart-grid{
  display:grid;
  grid-template-columns:1fr 1fr;
  gap:20px;
  margin-bottom:22px;
}

.chart-box{
  background:var(--white);
  border:1px solid var(--border);
  border-radius:20px;
  padding:24px;
  box-shadow:var(--shadow);
}

.chart-title{
  font-size:15px;
  font-weight:700;
  color:var(--text);
  margin-bottom:16px;
}

.chart-canvas-wrapper{
  position:relative;
  height:220px;
  width:100%;
}

.tbl-wrap{overflow-x:auto}
table{width:100%;border-collapse:collapse;font-size:13px}
thead tr{background:#f8f8f8}
th{padding:11px 14px;text-align:left;font-weight:600;color:var(--text2);font-size:12px;border-bottom:1px solid #e8e8e8;white-space:nowrap}
td{padding:11px 14px;color:#333;border-bottom:1px solid #f0f0f0;vertical-align:middle}
tr:last-child td{border-bottom:none}
tr:hover td{background:#fafafa}

.badge{display:inline-block;font-size:11px;font-weight:600;padding:3px 10px;border-radius:10px}
.badge-green{background:#e8f5e9;color:#2e7d32}
.badge-red{background:var(--red-light);color:#b71c1c}
.badge-yellow{background:var(--orange-light);color:#e65100}
.badge-blue{background:var(--blue-light);color:var(--blue)}
.badge-gray{background:#f5f5f5;color:#777}
.badge-purple{background:var(--purple-light);color:var(--purple)}

.btn{display:inline-flex;align-items:center;gap:6px;padding:7px 16px;border-radius:var(--radius-sm);font-size:13px;font-weight:600;cursor:pointer;border:none;transition:all .15s;text-decoration:none}
.btn-primary{background:var(--blue);color:#fff}.btn-primary:hover{background:var(--blue-dark)}
.btn-danger{background:var(--red);color:#fff}.btn-danger:hover{background:#a00d12}
.btn-success{background:var(--green);color:#fff}.btn-success:hover{background:#035c33}
.btn-outline{background:var(--white);color:var(--text2);border:1px solid var(--border)}.btn-outline:hover{background:var(--bg)}
.btn-sm{padding:5px 11px;font-size:12px}
.act-btns{display:flex;gap:6px}
.act-btn{width:30px;height:30px;border-radius:var(--radius-sm);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;cursor:pointer;background:var(--white);transition:all .15s;text-decoration:none}
.act-btn:hover{background:var(--bg)}
.act-btn i{font-size:16px}
.act-btn.edit i{color:var(--blue)}.act-btn.del i{color:var(--red)}.act-btn.block i{color:var(--orange)}.act-btn.approve i{color:var(--green)}.act-btn.view i{color:var(--purple)}

.filters{display:flex;gap:10px;flex-wrap:wrap;align-items:center}
.filter-input{padding:8px 12px;border:1px solid var(--border);border-radius:var(--radius-sm);font-size:13px;color:#333;outline:none;background:var(--white)}
.filter-input:focus{border-color:var(--blue)}
.av{width:34px;height:34px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;color:#fff;flex-shrink:0;background:var(--blue)}
.empty-state{text-align:center;padding:50px 20px;color:var(--text4)}
.empty-state i{font-size:44px;margin-bottom:12px;display:block;opacity:.5}
.empty-state p{font-size:14px}
.activity-grid{
  display:grid;
  grid-template-columns:repeat(auto-fit,minmax(300px,1fr));
  gap:16px;
}

.act-item{
  background:var(--white);
  border:1px solid var(--border);
  border-radius:18px;
  padding:18px;
  display:flex;
  align-items:flex-start;
  gap:14px;
  transition:.25s ease;
  box-shadow:var(--shadow);
}

.act-item:hover{
  transform:translateY(-4px);
}

.act-ic{
  width:46px;
  height:46px;
  border-radius:14px;
  background:var(--blue-light);
  display:flex;
  align-items:center;
  justify-content:center;
  flex-shrink:0;
}

.act-ic i{
  font-size:20px;
  color:var(--blue);
}

.act-txt{
  font-size:14px;
  color:var(--text);
  line-height:1.5;
  font-weight:500;
}

.act-tm{
  margin-top:6px;
  font-size:12px;
  color:var(--text4);
}

#idle-modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:9998;align-items:center;justify-content:center}
#idle-modal.show{display:flex}
.idle-box{background:#fff;border-radius:var(--radius);padding:32px;max-width:380px;width:90%;text-align:center;box-shadow:0 10px 40px rgba(0,0,0,.2)}
.idle-box h3{font-size:18px;font-weight:700;margin-bottom:10px}
.idle-box p{font-size:14px;color:var(--text2);margin-bottom:6px}
.idle-countdown{font-size:32px;font-weight:800;color:var(--red);margin:12px 0}

body.dark-theme{
  --bg:#111827;--white:#1f2937;--border:#374151;
  --text:#f9fafb;--text2:#d1d5db;--text3:#9ca3af;--text4:#6b7280;
  --blue-light:#1e3a5f;--green-light:#14532d;--red-light:#450a0a;
  --orange-light:#431407;--purple-light:#3b0764;
  --shadow:0 2px 8px rgba(0,0,0,0.4);
}
body.dark-theme .sidebar{background:#1f2937;border-color:#374151}
body.dark-theme .nav-item{color:#d1d5db}
body.dark-theme .nav-item:hover{background:#374151;color:#93c5fd}
body.dark-theme .nav-item.active{background:#1e3a5f;color:#93c5fd;border-left-color:#3b82f6}
body.dark-theme .nav-label{color:#6b7280}
body.dark-theme .topbar{background:#1f2937;border-color:#374151}
body.dark-theme .topbar-title{color:#f9fafb}
body.dark-theme .breadcrumb{color:#9ca3af}
body.dark-theme .icon-btn{background:#374151;border-color:#4b5563}
body.dark-theme .icon-btn:hover{background:#4b5563}
body.dark-theme .icon-btn i{color:#d1d5db}
body.dark-theme .panel{background:#1f2937;border-color:#374151}
body.dark-theme .panel-title{color:#f9fafb}
body.dark-theme .stat-card{background:#1f2937;border-color:#374151}
body.dark-theme .stat-num{color:#f9fafb}
body.dark-theme .stat-lbl{color:#9ca3af}
body.dark-theme .chart-box{background:#1f2937;border-color:#374151}
body.dark-theme .chart-title{color:#f9fafb}
body.dark-theme table{color:#d1d5db}
body.dark-theme thead tr{background:#374151}
body.dark-theme th{color:#9ca3af;border-color:#4b5563}
body.dark-theme td{color:#d1d5db;border-color:#374151}
body.dark-theme tr:hover td{background:#374151}
body.dark-theme .filter-input{background:#374151;border-color:#4b5563;color:#f9fafb}
body.dark-theme .act-item{border-color:#374151}
body.dark-theme .act-txt{color:#d1d5db}
body.dark-theme .btn-outline{background:#374151;color:#d1d5db;border-color:#4b5563}
body.dark-theme .btn-outline:hover{background:#4b5563}
body.dark-theme .act-btn{background:#374151;border-color:#4b5563}
body.dark-theme .act-btn:hover{background:#4b5563}
body.dark-theme .idle-box{background:#1f2937}
body.dark-theme .idle-box h3{color:#f9fafb}
body.dark-theme .idle-box p{color:#d1d5db}
body.dark-theme .s-name{color:#f9fafb}
body.dark-theme .s-email{color:#9ca3af}
body.dark-theme .empty-state{color:#6b7280}
body.dark-theme .detail-box{background:#1f2937}
body.dark-theme .detail-header{background:linear-gradient(135deg,#111827 0%,#1f2937 100%);border-color:#374151}
body.dark-theme .detail-header-name{color:#f9fafb}
body.dark-theme .detail-header-sub{color:#9ca3af}
body.dark-theme .detail-close{background:#374151;border-color:#4b5563;color:#9ca3af}
body.dark-theme .detail-field{background:#111827}
body.dark-theme .detail-field-label{color:#6b7280}
body.dark-theme .detail-field-value{color:#f9fafb}
body.dark-theme .detail-footer{background:#111827;border-color:#374151}
body.dark-theme .detail-section-title{color:#6b7280}
body.dark-theme .detail-section-title::after{background:#374151}
body.dark-theme #profile-menu{background:#1f2937 !important;border-color:#374151 !important}
body.dark-theme #profile-menu div{color:#f9fafb !important}

#detail-modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:9997;align-items:flex-start;justify-content:center;padding:40px 16px;overflow-y:auto}
#detail-modal.show{display:flex}
.detail-box{background:#fff;border-radius:12px;width:100%;max-width:620px;box-shadow:0 20px 60px rgba(0,0,0,.2);animation:modalIn .25s ease;margin:auto;overflow:hidden}
@keyframes modalIn{from{opacity:0;transform:translateY(-20px)}to{opacity:1;transform:none}}
.detail-header{display:flex;align-items:center;gap:14px;padding:20px 24px;border-bottom:1px solid var(--border);background:linear-gradient(135deg,#f8f9ff 0%,#fff 100%)}
.detail-header-icon{width:48px;height:48px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:22px;font-weight:800;color:#fff;flex-shrink:0;overflow:hidden}
.detail-header-icon img{width:48px;height:48px;object-fit:cover}
.detail-header-info{flex:1;min-width:0}
.detail-header-name{font-size:17px;font-weight:700;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.detail-header-sub{font-size:12px;color:var(--text3);margin-top:3px}
.detail-close{width:34px;height:34px;border-radius:50%;border:1px solid var(--border);background:#fff;display:flex;align-items:center;justify-content:center;cursor:pointer;color:var(--text3);font-size:20px;flex-shrink:0;transition:all .15s}
.detail-close:hover{background:var(--red-light);color:var(--red);border-color:var(--red)}
.detail-body{padding:24px}
.detail-section{margin-bottom:20px}
.detail-section-title{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--text4);margin-bottom:10px;display:flex;align-items:center;gap:6px}
.detail-section-title::after{content:'';flex:1;height:1px;background:var(--border)}
.detail-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.detail-field{background:#f8f9fa;border-radius:8px;padding:11px 14px}
.detail-field.full{grid-column:1/-1}
.detail-field-label{font-size:11px;color:var(--text4);font-weight:600;margin-bottom:4px;text-transform:uppercase;letter-spacing:.04em}
.detail-field-value{font-size:13px;color:var(--text);font-weight:500;word-break:break-word}
.detail-footer{padding:16px 24px;border-top:1px solid var(--border);display:flex;align-items:center;justify-content:flex-end;gap:10px;background:#fafafa}
.detail-id-chip{font-size:11px;font-weight:700;background:var(--blue-light);color:var(--blue);padding:4px 10px;border-radius:20px;margin-right:auto}

.post-img-wrap{width:100%;aspect-ratio:1;border-radius:10px;overflow:hidden;background:#f0f0f0;margin-bottom:12px}
.post-img-wrap img{width:100%;height:100%;object-fit:cover}

@media(max-width:768px){.stats-grid{grid-template-columns:1fr 1fr}.chart-grid{grid-template-columns:1fr}}
@media(max-width:500px){.stats-grid{grid-template-columns:1fr}}
</style>
</head>
<body>

<div id="toast-wrap"></div>

<div id="idle-modal">
  <div class="idle-box">
    <i class="ti ti-clock" style="font-size:40px;color:var(--orange);display:block;margin-bottom:12px"></i>
    <h3>Session Expiring</h3>
    <p>You have been <strong>inactive</strong>. Auto-logout in:</p>
    <div class="idle-countdown" id="idle-count">60</div>
    <p style="font-size:12px;color:#aaa;margin-bottom:16px">seconds</p>
    <button class="btn btn-primary" onclick="resetIdle()"><i class="ti ti-refresh"></i> Stay Logged In</button>
    <a href="<?= htmlspecialchars(app_url('admin/logout.php')) ?>" class="btn btn-outline"><i class="ti ti-logout"></i> Logout Now</a>
  </div>
</div>
