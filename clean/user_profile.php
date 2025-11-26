<?php
session_start();
require_once 'config/database.php';
require_once 'config/functions.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

/* Discover columns safely */
$cols = [];
if ($res = $conn->query("DESCRIBE users")) {
  while ($r = $res->fetch_assoc()) { $cols[] = $r['Field']; }
}
$selectCols = implode(', ', $cols);

/* Fetch current user */
$user_id = (int)$_SESSION['user_id'];
$stmt = $conn->prepare("SELECT $selectCols FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc() ?: [];

/* Helpers */
function safe($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function val($arr,$key,$fallback='N/A'){
  return isset($arr[$key]) && $arr[$key] !== '' ? safe($arr[$key]) : $fallback;
}
function dt($s,$fallback='Not available'){
  if(!$s) return $fallback;
  $t = strtotime($s);
  return $t ? date('F j, Y', $t) : safe($s);
}
function initials($name){
  $parts = preg_split('/\s+/', trim((string)$name));
  $first = $parts[0] ?? '';
  $last  = $parts[count($parts)-1] ?? '';
  return strtoupper(mb_substr($first,0,1) . mb_substr($last,0,1));
}

/* Derived */
$fullName  = val($user,'full_name');
$email     = val($user,'email');
$studNo    = val($user,'student_number');
$phone     = val($user,'phone');
$sexRaw    = isset($user['sex']) ? strtolower(trim($user['sex'])) : '';
$sexDisp   = $sexRaw ? ucfirst($sexRaw) : 'N/A';
$sexIcon   = $sexRaw === 'male' ? 'mars' : ($sexRaw === 'female' ? 'venus' : 'genderless');
$birthday  = isset($user['birthday']) && $user['birthday'] ? date('F j, Y', strtotime($user['birthday'])) : 'N/A';
$age       = isset($user['age']) && $user['age'] !== '' ? safe($user['age']).' years old' : 'N/A';
$dept      = isset($user['department']) && $user['department'] !== '' ? safe($user['department']) : 'CCIS';
$address   = val($user,'address');
$specStat  = (isset($user['special_status']) && $user['special_status'] !== '' && strtolower($user['special_status']) !== 'none')
            ? ucfirst($user['special_status']) : 'None';
$createdAt = isset($user['created_at']) ? dt($user['created_at']) : 'Not available';
$updatedAt = isset($user['updated_at']) ? dt($user['updated_at']) : 'Not available';
$ini       = initials($fullName);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>User Profile - UMak Medical Clinic</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="icon" type="image/x-icon" href="./assets/images/umak3.ico">
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap');

    :root{
      --brand:#111c4e;           /* primary brand */
      --ink:#111c4e;             /* text/icons (monochrome look) */
      --border:#e6eaf2;          /* subtle borders for contrast */
      --chip-bg: rgba(17,28,78,.08);
    }

    html, body { height: auto; }
    body{
      font-family:'Inter',sans-serif;
      margin:0;
      background:#f5f7ff;        /* neutral flat background (no gradient) */
      overflow-x:hidden;
      min-height:100vh;
    }
    /* Space for fixed nav from include */
    .viewport{ min-height: calc(100vh - 60px); padding: 20px 0; }

    /* Header (no gradient) - responsive */
    .page-head{
      background:#ffffff;
      border:1px solid var(--border);
      border-radius:16px;
      padding:14px 16px;
      margin-bottom:20px;
    }
    @media (max-width:768px) {
      .page-head {
        padding:12px 14px;
        margin-bottom:16px;
      }
      .page-head .flex.items-center.justify-between {
        flex-direction:column;
        gap:12px;
      }
    }

    /* Panels (no top gradient bar) */
    .panel{
      background:#fff;
      border:1px solid var(--border);
      border-radius:16px;
      padding:16px;
      box-shadow:0 10px 24px rgba(14,30,64,.05);
      position:relative;
    }

    .panel-title{
      display:flex; align-items:center; font-weight:700; color:var(--ink);
      margin-bottom:12px;
    }
    .panel-title i{ color:var(--brand); margin-right:8px; }

    /* Tiles */
    .tile{
      background:#ffffff;
      border:1px solid var(--border);
      border-radius:12px;
      padding:10px 12px;
      display:flex; align-items:flex-start;
      transition: box-shadow .2s ease, transform .2s ease;
      min-height:56px;
    }
    .tile:hover{ box-shadow:0 6px 14px rgba(14,30,64,.06); transform:translateY(-1px); }
    .tile i{ margin-right:10px; margin-top:2px; color:var(--brand); }
    .tile-label{ font-size:12px; color:#64748b; line-height:1; margin-bottom:2px; }
    .tile-value{ font-weight:600; color:var(--ink); font-size:14px; }

    /* Badges (single-color theme) - responsive */
    .chip{
      display:inline-flex; align-items:center; padding:6px 10px; border-radius:999px;
      font-size:12px; font-weight:700;
      background:var(--chip-bg); color:var(--ink);
      border:1px solid rgba(17,28,78,.15);
    }
    .chip i{ color:var(--brand); margin-right:6px; }
    @media (max-width:640px) {
      .space-x-2 {
        display:flex;
        flex-direction:column;
        gap:8px!important;
      }
      .chip {
        width:100%;
        justify-content:center;
      }
    }

    /* 2-column layout - responsive */
    .grid-2{ display:grid; grid-template-columns: 1fr; gap:20px; width:100%; }
    @media (min-width:1024px) {
      .grid-2{ grid-template-columns: 1fr 1fr; }
    }
    .col{ display:flex; flex-direction:column; overflow:visible; }
    .col-body{ overflow-y:auto; max-height:auto; }

    /* Nav colors forced - solid background */
    nav.fixed.top-0.left-0.right-0{
      background:#003366!important; 
      height:64px!important;
      box-shadow:0 2px 8px rgba(0,0,0,.12)!important;
      z-index:1000!important;
    }
    nav.fixed.top-0.left-0.right-0 a{ color:#fff!important; }
    
    /* Ensure no transparency on sidebars */
    #sidebar, #user-sidebar {
      background:#003366!important;
    }

    /* Avatar */
    .avatar{
      width:44px; height:44px; border-radius:999px;
      background:var(--brand);
      color:#fff; display:flex; align-items:center; justify-content:center;
      font-weight:800;
    }

    /* Titles/subtitles */
    .title{ color:var(--ink); }
    .subtitle{ color:#334155; }
  </style>
</head>
<body style="background: linear-gradient(to bottom right, #ffffff, #cce0ff); background-attachment: fixed; background-repeat: no-repeat;">
  <?php include 'includes/tailwind_nav.php'; ?>

  <div class="viewport px-4 md:px-6 lg:px-8 pt-4">
    <!-- Header -->
    <div class="page-head mb-4 md:mb-6">
      <div class="flex items-center justify-between">
        <div class="flex items-center space-x-3">
          <div class="avatar"><?= safe($ini) ?></div>
          <div>
            <div class="text-xl font-bold title leading-tight"><?= $fullName ?></div>
            <div class="text-sm subtitle flex items-center space-x-3">
              <span class="flex items-center"><i class="fa-solid fa-user-tag mr-1" style="color:#111c4e"></i>Patient</span>
              <span class="hidden md:inline text-gray-400">•</span>
              <span class="hidden md:inline"><i class="fa-solid fa-envelope mr-1" style="color:#111c4e"></i><?= $email ?></span>
            </div>
          </div>
        </div>
        <div class="space-x-2">
          <span class="chip"><i class="fa-solid fa-circle-check"></i> Active Account</span>
          <?php if ($specStat !== 'None'): ?>
            <span class="chip"><i class="fa-solid fa-star"></i><?= safe($specStat) ?></span>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Two columns -->
    <div class="grid-2">
      <!-- Left: Personal -->
      <div class="col">
        <div class="panel">
          <div class="panel-title"><i class="fa-solid fa-id-card"></i> Personal Information</div>
          <div class="col-body">
            <div class="grid grid-cols-2 gap-3">
              <div class="tile">
                <i class="fa-solid fa-user"></i>
                <div><div class="tile-label">Full Name</div><div class="tile-value"><?= $fullName ?></div></div>
              </div>
              <div class="tile">
                <i class="fa-solid fa-hashtag"></i>
                <div><div class="tile-label">Student Number</div><div class="tile-value"><?= $studNo ?></div></div>
              </div>
              <div class="tile">
                <i class="fa-solid fa-at"></i>
                <div><div class="tile-label">Email Address</div><div class="tile-value truncate" title="<?= $email ?>"><?= $email ?></div></div>
              </div>
              <div class="tile">
                <i class="fa-solid fa-phone"></i>
                <div><div class="tile-label">Phone Number</div><div class="tile-value"><?= $phone ?></div></div>
              </div>
              <div class="tile">
                <i class="fa-solid fa-<?= $sexIcon ?>"></i>
                <div><div class="tile-label">Sex</div><div class="tile-value"><?= safe($sexDisp) ?></div></div>
              </div>
              <div class="tile">
                <i class="fa-solid fa-cake-candles"></i>
                <div><div class="tile-label">Birthday</div><div class="tile-value"><?= safe($birthday) ?></div></div>
              </div>
              <div class="tile">
                <i class="fa-solid fa-hourglass-half"></i>
                <div><div class="tile-label">Age</div><div class="tile-value"><?= $age ?></div></div>
              </div>
              <div class="tile">
                <i class="fa-solid fa-building-columns"></i>
                <div><div class="tile-label">Department/College</div><div class="tile-value"><?= $dept ?></div></div>
              </div>
              <div class="tile">
                <i class="fa-solid fa-location-dot"></i>
                <div><div class="tile-label">Address</div><div class="tile-value truncate" title="<?= $address ?>"><?= $address ?></div></div>
              </div>
              <div class="tile">
                <i class="fa-solid fa-tag"></i>
                <div><div class="tile-label">Special Status</div><div class="tile-value"><?= safe($specStat) ?></div></div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Right: Account -->
      <div class="col">
        <div class="panel">
          <div class="panel-title"><i class="fa-solid fa-shield-halved"></i> Account Information</div>
          <div class="col-body">
            <div class="grid grid-cols-1 gap-3">
              <div class="tile">
                <i class="fa-solid fa-calendar-plus"></i>
                <div><div class="tile-label">Account Created</div><div class="tile-value"><?= safe($createdAt) ?></div></div>
              </div>
              <div class="tile">
                <i class="fa-solid fa-clock-rotate-left"></i>
                <div><div class="tile-label">Last Updated</div><div class="tile-value"><?= safe($updatedAt) ?></div></div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</body>
</html>
