<?php
// export_record_pdf.php - render a printable medical record page and trigger print (browser PDF)
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../config/functions.php';
require_once '../config/database.php';

if (!isset($_SESSION['loggedin'])) {
    // allow viewing if logged in; otherwise redirect to login
    header('Location: index.php');
    exit();
}

$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$ts = isset($_GET['ts']) ? trim($_GET['ts']) : '';
if ($user_id <= 0) {
    echo "Missing user";
    exit;
}

$pstmt = mysqli_prepare($conn, "SELECT id, first_name, last_name, phone, email, address, student_number, birthday, sex, department FROM users WHERE id = ?");
mysqli_stmt_bind_param($pstmt, 'i', $user_id);
mysqli_stmt_execute($pstmt);
$pres = mysqli_stmt_get_result($pstmt);
$patient = mysqli_fetch_assoc($pres) ?: ['first_name'=>'Unknown','last_name'=>''];
mysqli_stmt_close($pstmt);

// fetch biometric row by timestamp (try created_at then record_date)
$bst = mysqli_prepare($conn, "SELECT height, weight, blood_pressure, temperature, pulse_rate, respiratory_rate, nurse_recommendation, created_at, record_date FROM biometrics WHERE user_id = ? AND (created_at = ? OR record_date = ?) LIMIT 1");
if ($bst) {
  mysqli_stmt_bind_param($bst, 'iss', $user_id, $ts, $ts);
  mysqli_stmt_execute($bst);
  $bres = mysqli_stmt_get_result($bst);
  $b = mysqli_fetch_assoc($bres) ?: null;
  mysqli_stmt_close($bst);
} else { $b = null; }

// fetch clinical history row by timestamp (created_at or visit_date)
$fk_col = get_history_fk_column();
$h = null;
if ($fk_col) {
  // Check if the table has a visit_date column before including it in the query
  $visit_col_exists = false;
  $colCheck = mysqli_prepare($conn, "SELECT COUNT(*) AS c FROM information_schema.columns WHERE table_schema = ? AND table_name = 'patient_history_records' AND column_name = 'visit_date'");
  if ($colCheck) {
    mysqli_stmt_bind_param($colCheck, 's', $dbname);
    mysqli_stmt_execute($colCheck);
    $cres = mysqli_stmt_get_result($colCheck);
    $crow = mysqli_fetch_assoc($cres);
    $visit_col_exists = !empty($crow['c']);
    mysqli_stmt_close($colCheck);
  }

  if ($visit_col_exists) {
    $sql = "SELECT * FROM patient_history_records WHERE {$fk_col} = ? AND (created_at = ? OR visit_date = ?) LIMIT 1";
    $hstmt = @mysqli_prepare($conn, $sql);
    if ($hstmt) {
      mysqli_stmt_bind_param($hstmt, 'iss', $user_id, $ts, $ts);
      mysqli_stmt_execute($hstmt);
      $hres = mysqli_stmt_get_result($hstmt);
      $h = mysqli_fetch_assoc($hres) ?: null;
      mysqli_stmt_close($hstmt);
    }
  } else {
    // Fallback: only match created_at when visit_date column is absent
    $sql = "SELECT * FROM patient_history_records WHERE {$fk_col} = ? AND created_at = ? LIMIT 1";
    $hstmt = @mysqli_prepare($conn, $sql);
    if ($hstmt) {
      mysqli_stmt_bind_param($hstmt, 'is', $user_id, $ts);
      mysqli_stmt_execute($hstmt);
      $hres = mysqli_stmt_get_result($hstmt);
      $h = mysqli_fetch_assoc($hres) ?: null;
      mysqli_stmt_close($hstmt);
    }
  }
}

function safe($v){ return htmlspecialchars($v ?? '—'); }

// derive values for display
$h_val = $b['height'] ?? '—';
$w_val = $b['weight'] ?? '—';
$bp = $b['blood_pressure'] ?? '—';
$temp = $b['temperature'] ?? '—';
$pulse = $b['pulse_rate'] ?? '—';
$resp = $b['respiratory_rate'] ?? '—';
$bmi = '—';
if (is_numeric($h_val) && is_numeric($w_val) && floatval($h_val) > 0) {
  $hh = floatval($h_val);
  $ww = floatval($w_val);
  $bmi = round($ww/(($hh/100)*($hh/100)),2);
}

$diag = '';
$inter = '';
$rec = '';
$nurse_rec = '';
if (!empty($h)) {
  // Direct column extraction (new schema)
  if (!empty($h['diagnosis'])) $diag = trim($h['diagnosis']);
  if (!empty($h['interview'])) $inter = trim($h['interview']);
  if (!empty($h['doctors_recommendations'])) $rec = trim($h['doctors_recommendations']);
  if (!empty($h['nurse_recommendation'])) $nurse_rec = trim($h['nurse_recommendation']);
  
  // If diagnosis is still empty, try assessment_notes (which now contains only diagnosis text)
  if ($diag === '' && !empty($h['assessment_notes'])) {
    $diag = trim($h['assessment_notes']);
  }
  
  // If interview is still empty, try notes
  if ($inter === '' && !empty($h['notes'])) {
    $inter = trim($h['notes']);
  }
}
// Helper: wrap text by words and also break very long unbroken strings
function wrap_words_and_chars($text, $maxWords = 40, $maxChars = 120) {
  $text = trim((string)$text);
  if ($text === '') return '';

  // Insert hard newlines after every $maxWords words so nl2br() outputs <br>
  $words = preg_split('/(\s+)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
  if ($words === false) return $text;

  $out = '';
  $wordCount = 0;
  foreach ($words as $tok) {
    if (preg_match('/^\s+$/u', $tok)) {
      $out .= $tok; // preserve whitespace
      continue;
    }
    $out .= $tok;
    $wordCount++;
    if ($wordCount % $maxWords === 0) {
      // hard break: newline
      $out .= "\n";
    }
  }

  $out = trim($out);

  // Break extremely long unbroken strings by inserting newlines every $maxChars
  $out = preg_replace_callback('/(\S{' . ($maxChars + 1) . ',})/u', function($m) use ($maxChars) {
    $s = $m[1];
    // use mb_str_split if available for multibyte safety
    if (function_exists('mb_str_split')) {
      $parts = mb_str_split($s, $maxChars);
    } else {
      $parts = str_split($s, $maxChars);
    }
    return implode("\n", $parts);
  }, $out);

  return $out;
}

// Apply wrapping to fields so nl2br() will render reasonable lines in the PDF
$diag = wrap_words_and_chars($diag, 20, 120);
$inter = wrap_words_and_chars($inter, 20, 120);
$rec = wrap_words_and_chars($rec, 20, 120);
$nurse_rec = wrap_words_and_chars($nurse_rec, 20, 120);

?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Medical Record - UNIVERSITY OF MAKATI MEDICAL AND CLINIC</title>
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap');
    body{ font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, Arial; background: linear-gradient(180deg,#eaf6ff,#ffffff); margin:0; padding:40px }
    .sheet{ width:800px; margin:24px auto; background:#fff; border-radius:12px; box-shadow:0 6px 24px rgba(2,6,23,0.08); padding:28px }
    .center { text-align:center }
    .h-small{ font-size:0.85rem; color:#6C757D }
    .title-annex{ font-weight:700; font-size:1rem; color:#000; margin-bottom:8px }
    .header-box{ border:1px solid #E9EEF6; padding:12px; border-radius:10px; display:flex; gap:12px; align-items:center }
  .logo{ width:72px; height:72px; border-radius:50%; background:transparent; color:#fff; display:flex; align-items:center; justify-content:center; font-weight:700; overflow:hidden }
    .inst{ color:#002D62; font-weight:700; font-size:0.95rem }
    .inst-sub{ color:#6C757D; font-size:0.85rem }
    .info-row{ display:flex; justify-content:space-between; margin-top:12px }
    .info-left, .info-right{ width:48% }
    .info-line{ margin:6px 0 }
    .doc-title{ font-weight:800; color:#002D62; font-size:1.25rem; margin:14px 0 }
    .section-row{ display:flex; gap:16px; margin-top:18px }
    .card{ flex:1; border:1px solid #E9EEF6; border-radius:10px; padding:14px }
    .card h3{ margin:0;color:#002D62 }
    .ts{ font-size:0.85rem;color:#6C757D;margin-top:6px }
  .metrics{ display:grid; grid-template-columns:1fr; gap:6px; margin-top:12px; justify-items:start }
  .metric{ display:flex; gap:12px; color:#6C757D; justify-content:flex-start }
    .metric .val{ color:#002D62; font-weight:700 }
    .bmi{ margin-top:12px; border-top:1px solid #F1F6FB; padding-top:10px; font-weight:700 }
    .clinic-text{ margin-top:12px; color:#495057 }
    /* Ensure long runs wrap in print rendering */
    .clinic-text div{ word-break:break-word; overflow-wrap:anywhere; white-space:pre-wrap; }
    @media print{ body{ background: #ffffff } .sheet{ box-shadow:none; margin:0; width:100%; border-radius:0 } }
  </style>
</head>
<body>
  <div class="sheet">
    <div class="header-box">
      <div class="logo">
        <img src="../assets/images/umak3.ico" alt="UM Logo" style="width:72px;height:72px;border-radius:50%;object-fit:cover;display:block" />
      </div>
      <div style="flex:1; display:flex; flex-direction:column; gap:6px">
        <div style="display:flex;flex-direction:column">
          <div class="inst">UNIVERSITY OF MAKATI MEDICAL AND CLINIC</div>
          <div class="inst-sub">J.P Rizal Ext., West Rembo, Makati City, Philippines</div>
        </div>
      </div>
      <div class="logo">
        <img src="../assets/images/clinic_umak.ico" alt="Clinic Logo" style="width:72px;height:72px;border-radius:50%;object-fit:cover;display:block" />
      </div>
      <div style="flex:0 1 auto; margin-left:auto">
        <div style="margin-top:8px">
          
          <?php
            // prefer biometric timestamp if available, otherwise use current date/time
            $display_date = '';
            $display_time = '';
            if (!empty($b) && !empty($b['created_at'])) {
              $dt = strtotime($b['created_at']);
              if ($dt) {
                $display_date = date('F j, Y', $dt);
                $display_time = date('g:i A', $dt);
              }
            } elseif (!empty($b) && !empty($b['record_date'])) {
              $dt = strtotime($b['record_date']);
              if ($dt) {
                $display_date = date('F j, Y', $dt);
                $display_time = date('g:i A', $dt);
              }
            } elseif (!empty($ts)) {
              $t = strtotime($ts);
              if ($t) {
                $display_date = date('F j, Y', $t);
                $display_time = date('g:i A', $t);
              }
            }
            if ($display_date === '') $display_date = date('F j, Y');
            if ($display_time === '') $display_time = date('g:i A');
          ?>
          <div class="info-line"><strong>Date:</strong> <?= $display_date ?></div>
          <div class="info-line"><strong>Time:</strong> <?= $display_time ?></div>
        </div>
      </div>
    </div>

    <div class="center doc-title">MEDICAL RECORD</div>

    <div class="section-row">
      <div class="card">
        <h3>Personal Information</h3>
        <div class="metrics">
          <div class="info-line"><strong>Client:</strong> <?= htmlspecialchars(trim($patient['first_name'].' '.$patient['last_name'])) ?></div>
          <div class="metric"><div>Sex</div><div class="val"><?= safe(!empty($patient['sex']) ? ucfirst($patient['sex']) : '—') ?></div></div>
          <div class="metric"><div>Birthday</div><div class="val"><?php echo (!empty($patient['birthday']) && $patient['birthday'] !== '0000-00-00') ? date('F j, Y', strtotime($patient['birthday'])) : '—'; ?></div></div>
          <div class="metric"><div>Department</div><div class="val"><?= safe($patient['department'] ?? '—') ?></div></div>
          <div class="metric"><div>Phone</div><div class="val"><?= safe($patient['phone'] ?? '—') ?></div></div>
          <div class="metric"><div>Email</div><div class="val"><?= safe($patient['email'] ?? '—') ?></div></div>
          <div class="metric"><div>Address</div><div class="val"><?= safe($patient['address'] ?? '—') ?></div></div>
        </div>
        <div class="contact-info" style="margin-top:16px; border-top:1px solid #F1F6FB; padding-top:12px">
          <div style="font-weight:600;color:#002D62;margin-bottom:8px">Biometric</div>
          <div class="metrics">
            <div class="metric"><div>Height</div><div class="val"><?= safe(is_numeric($h_val)?number_format((float)$h_val,2):$h_val) ?> cm</div></div>
            <div class="metric"><div>Weight</div><div class="val"><?= safe(is_numeric($w_val)?number_format((float)$w_val,2):$w_val) ?> kg</div></div>
            <div class="metric"><div>Blood Pressure</div><div class="val"><?= safe($bp) ?></div></div>
            <div class="metric"><div>Temperature</div><div class="val"><?= safe($temp) ?> °C</div></div>
            <div class="metric"><div>Pulse</div><div class="val"><?= safe($pulse) ?> bpm</div></div>
            <div class="metric"><div>Respiration</div><div class="val"><?= safe(is_numeric($resp) ? number_format((float)$resp,0) : $resp) ?><?= is_numeric($resp) ? ' cpm' : '' ?></div></div>
          </div>
          <div class="bmi">BMI: <?= safe(is_numeric($bmi)?$bmi:'—') ?> <?= is_numeric($bmi)?'kg/m²':'' ?></div>
        </div>
      </div>
      <div class="card">
        <h3>Clinical</h3>
        <div class="clinic-text">
          <div style="font-weight:700;color:#002D62">Diagnosis:</div>
          <?php
            // Debug info for diagnosis formatting (visible in page source only)
            $diag_preview = substr(str_replace("\n", "\\n", $diag), 0, 240);
            $diag_has_nl = strpos($diag, "\n") !== false ? 1 : 0;
          ?>
          <!-- DIAG_DEBUG length=<?= strlen($diag) ?> has_newline=<?= $diag_has_nl ?> preview="<?= htmlspecialchars($diag_preview) ?>" -->
          <div><?= $diag !== '' ? render_clinical_html($diag) : '<em>—</em>' ?></div>
          <div style="height:10px"></div>
          <div style="font-weight:700;color:#002D62">Patient history:</div>
          <div><?= $inter !== '' ? render_clinical_html($inter) : '<em>—</em>' ?></div>
          <div style="height:10px"></div>
          <div style="font-weight:700;color:#002D62">Doctors treatment:</div>
          <div><?= $rec !== '' ? render_clinical_html($rec) : '<em>—</em>' ?></div>
          <div style="height:10px"></div>
          <div style="font-weight:700;color:#002D62">Nurse consultation:</div>
          <div><?= $nurse_rec !== '' ? render_clinical_html($nurse_rec) : '<em>—</em>' ?></div>
        </div>
      </div>
    </div>
  </div>

  <script>
    // Auto-print and close window after print (when opened in new tab)
    window.addEventListener('load', function(){
      setTimeout(function(){ window.print(); }, 400);
      // optional: close after printing (may be blocked by browser)
      window.onafterprint = function(){ try{ window.close(); }catch(e){} };
    });
  </script>
</body>
</html>
