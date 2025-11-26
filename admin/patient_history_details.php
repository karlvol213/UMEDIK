<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/functions.php';
require_once '../config/database.php';

if (!isset($_SESSION['loggedin']) || !isset($_SESSION['isAdmin']) || $_SESSION['isAdmin'] !== true) {
    header('Location: ../index.php');
    exit();
}

$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
if ($user_id <= 0) {
    header('Location: patient_history.php');
    exit();
}

// Patient
$pstmt = mysqli_prepare($conn, "SELECT id, first_name, last_name FROM users WHERE id = ?");
mysqli_stmt_bind_param($pstmt, 'i', $user_id);
mysqli_stmt_execute($pstmt);
$pres = mysqli_stmt_get_result($pstmt);
$patient = mysqli_fetch_assoc($pres) ?: ['first_name' => 'Unknown', 'last_name' => ''];
mysqli_stmt_close($pstmt);

// Patient Histories
$fk_col = get_history_fk_column();
$hstmt = mysqli_prepare($conn, "SELECT * FROM patient_history_records WHERE {$fk_col} = ? ORDER BY created_at DESC");
mysqli_stmt_bind_param($hstmt, 'i', $user_id);
mysqli_stmt_execute($hstmt);
$hres = mysqli_stmt_get_result($hstmt);
$histories = mysqli_fetch_all($hres, MYSQLI_ASSOC);
mysqli_stmt_close($hstmt);

// Biometrics
$bst = mysqli_prepare($conn, "SELECT id, record_date, height, weight, blood_pressure, temperature, pulse_rate, respiratory_rate, nurse_recommendation, created_at FROM biometrics WHERE user_id = ? ORDER BY record_date DESC, created_at DESC");
mysqli_stmt_bind_param($bst, 'i', $user_id);
mysqli_stmt_execute($bst);
$b_res = mysqli_stmt_get_result($bst);
$biometrics = mysqli_fetch_all($b_res, MYSQLI_ASSOC);
mysqli_stmt_close($bst);

// Aggregate/Group histories by timestamp
$grouped = [];
foreach ($histories as $h) {
    // Try to get the best timestamp for grouping:
    // 1. created_at (most precise)
    // 2. visit_date (fallback)
    // 3. unique key if no timestamp available
    $ts = null;
    if (!empty($h['created_at'])) {
        $ts = $h['created_at'];
    } elseif (!empty($h['visit_date'])) {
        // Convert visit_date to start of day timestamp
        $ts = date('Y-m-d H:i:s', strtotime($h['visit_date'] . ' 00:00:00'));
    }
    $key = $ts ?: ('id_' . ($h['id'] ?? uniqid()));
    
    if (!isset($grouped[$key])) {
    $grouped[$key] = $h;
        // Store original timestamp formats for matching
        $grouped[$key]['_match_date'] = !empty($h['visit_date']) ? date('Y-m-d', strtotime($h['visit_date'])) : null;
        $grouped[$key]['_match_ts'] = !empty($h['created_at']) ? date('Y-m-d H:i:s', strtotime($h['created_at'])) : null;
    } else {
    $fields = ['symptoms','diagnosis','interview','assessment_notes','notes','doctors_recommendations','vital_signs'];
        foreach ($fields as $f) {
            $existing = trim((string)($grouped[$key][$f] ?? ''));
            $incoming = trim((string)($h[$f] ?? ''));
            if ($incoming === '') continue;
            if ($existing === '') $grouped[$key][$f] = $incoming;
            elseif ($incoming !== $existing) $grouped[$key][$f] = $existing . "\n\n---\n\n" . $incoming;
        }
    }
}

// Build timeline array
$timeline = [];
$history_map = [];
foreach ($grouped as $k => $g) {
    $mapTs = $g['created_at'] ?? $g['visit_date'] ?? $k;
    $history_map[$mapTs] = $g;
}

foreach ($biometrics as $b) {
    $biometric_ts = !empty($b['created_at']) ? date('Y-m-d H:i:s', strtotime($b['created_at'])) : null;
    $biometric_date = !empty($b['record_date']) ? date('Y-m-d', strtotime($b['record_date'])) : null;

    // Find matching history record
    $matched_history = null;
    $matched_key = null;
    foreach ($history_map as $ts => $h) {
        // Try exact timestamp match first
        if ($biometric_ts && isset($h['_match_ts']) && $biometric_ts === $h['_match_ts']) {
            $matched_history = $h;
            $matched_key = $ts;
            break;
        }
    }
    // If no exact match, try date-only match (find first record for that date)
    if (!$matched_history && $biometric_date) {
        foreach ($history_map as $ts => $h) {
            if (isset($h['_match_date']) && $biometric_date === $h['_match_date']) {
                $matched_history = $h;
                $matched_key = $ts;
                break;
            }
        }
    }
    $display_ts = $b['created_at'] ?? $b['record_date'] ?? null;
    if ($matched_history) {
        $timeline[] = ['type' => 'combined', 'ts' => $display_ts, 'biometric' => $b, 'history' => $matched_history];
        unset($history_map[$matched_key]);
    } else {
        $timeline[] = ['type' => 'biometric', 'ts' => $display_ts, 'data' => $b];
    }
}

// Remaining histories with no matching biometric
foreach ($history_map as $ts => $g) {
    $timeline[] = ['type' => 'history', 'ts' => $ts, 'data' => $g];
}

// Sort descending by timestamp
usort($timeline, function($a, $b){
    $ta = strtotime($a['ts'] ?? '1970-01-01 00:00:00');
    $tb = strtotime($b['ts'] ?? '1970-01-01 00:00:00');
    return $tb <=> $ta;
});

// helper to format timestamp
function fmt_ts_entry($entry){
    if (empty($entry) || empty($entry['data'])) return date('Y-m-d H:i:s');
    $d = $entry['data'];
    return $d['created_at'] ?? $d['record_date'] ?? $d['visit_date'] ?? date('Y-m-d H:i:s');
}

// Clean clinical text: trim and remove leading duplicated labels like "Diagnosis:", "Interview:", "Patient Interview / Notes:", etc.
function clean_clinical_text($text) {
    // Convert to string and do initial trim
    $t = trim((string)$text);
    if ($t === '') return '';

    // Normalize all line endings to \n
    $t = str_replace(["\r\n", "\r"], "\n", $t);

    // Remove common headings anywhere in text (case-insensitive)
    $pattern = '/\b(?:Diagnosis|Patient Interview\s*\/?\s*Notes|Patient Interview|Interview|Notes|Doctor\'s Recommendations)\s*[:\-–—]*\s*/iu';
    $t = preg_replace($pattern, "\n", $t);

    // Clean up whitespace and formatting:
    // 1. Remove leading whitespace from each line
    $lines = array_map('trim', explode("\n", $t));
    
    // 2. Remove empty lines and normalize remaining ones
    $lines = array_filter($lines, function($line) {
        return trim($line) !== '';
    });
    
    // 3. Collapse multiple spaces within each line
    $lines = array_map(function($line) {
        return preg_replace('/\s+/', ' ', $line);
    }, $lines);
    
    // Rejoin with single newlines
    return implode("\n", $lines);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Patient Records - <?= htmlspecialchars($patient['first_name'].' '.$patient['last_name']) ?></title>
    <?php require_once '../includes/header.php'; outputHeader('Patient Records'); ?>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        body{ font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial; background: linear-gradient(to bottom right,#ffffff,#cce0ff); margin:0; padding-top:72px }
        .page-title{ text-align:center; color:#0D254A; margin:24px 0 12px; font-size:1.6rem }
        .dashboard{ margin:8px auto; max-width:1100px; background:transparent; padding:6px 12px }
        .timeline{ display:grid; grid-template-columns: 1fr; gap:16px; max-width:1100px; margin:0 auto; padding:8px }
        .record-card{ display:flex; justify-content:space-between; align-items:flex-start; gap:24px; background: #ffffff; border-radius:12px; padding:24px; box-shadow:0 2px 6px rgba(0,0,0,0.08); margin:0; transition: transform .12s ease, box-shadow .12s ease; border:1px solid #E9EEF6 }
        .record-card + .record-card{ margin-top:0 }
        .record-card:hover{ transform: translateY(-4px); box-shadow:0 8px 24px rgba(3,45,85,0.10) }
        .record-left { flex:1; display:flex; flex-direction:column; gap:12px; min-width:280px }
        .record-avatar { width:56px; height:56px; border-radius:50%; background:#1E88E5; color:#fff; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:18px }
        .record-time { font-size:0.9rem; color:#6C757D }
        .record-main { flex:1; min-width:0; display:flex; flex-direction:column }
        .record-right { flex:1; display:flex; flex-direction:column; gap:12px }
        .record-stats { display:flex; flex-direction:row; gap:12px; align-items:center }
        .stat-item { background:#ffffff; border:1px solid #E9EEF6; padding:10px 12px; border-radius:12px; text-align:center; min-width:110px; box-shadow: inset 0 1px 0 rgba(255,255,255,0.6) }
        .vital-box { background:#fff; border:1px solid #e6f0ff; padding:10px; border-radius:8px; text-align:center; color:#0f172a }
        .vital-label { font-size:0.85rem; color:#64748b }
        .vital-value { font-weight:700; margin-top:6px }
        .vital-grid { display:grid; grid-template-columns: repeat(2, 1fr); gap:10px }
    .card-text { max-width:56ch; white-space:pre-line; color:#495057 }
        .btn-convert { display:inline-block; background:#1E88E5; color:#fff; padding:8px 14px; border-radius:10px; text-decoration:none; font-weight:600; font-size:0.95rem; border:1px solid rgba(0,0,0,0.04) }
        .btn-convert:hover { background:#1669c6 }
        @media (max-width:720px){
            .record-card{ flex-direction:column }
            .record-left{ flex-direction:row; gap:10px; align-items:center }
            .record-right{ align-items:flex-start; width:100% }
            .record-stats{ flex-direction:row; gap:8px; overflow:auto }
            .card-text{ max-width:100% }
            .record-card{ padding:12px }
        }
    </style>
</head>
<body>
    <?php require '../includes/tailwind_nav.php'; ?>

    <?php $display_title = 'Records for ' . trim($patient['first_name'].' '.$patient['last_name']); ?>
    <h1 class="page-title"><?= htmlspecialchars($display_title) ?></h1>

    <?php
    // Debug banner removed - production rendering
    ?>

    <div class="timeline" id="timeline">
        <?php if (empty($timeline)) { ?>
            <div class="record-card"><div style="padding:12px">No records found for this patient.</div></div>
        <?php } else {
            foreach ($timeline as $entry) {
                $type = $entry['type'];
                $avatar = strtoupper(substr(trim(($patient['first_name'] ?? '') . ' ' . ($patient['last_name'] ?? '')), 0, 1));
                if ($type === 'combined') {
                    $ts = htmlspecialchars($entry['ts']);
                    $b = $entry['biometric'];
                    $h = $entry['history'];
                    // Extract data and format record card
                    $h_val = $b['height'] ?? '—';
                    $w_val = $b['weight'] ?? '—';
                    $bp = $b['blood_pressure'] ?? '—';
                    $temp = $b['temperature'] ?? '—';
                    $pulse = $b['pulse_rate'] ?? '—';
                    $resp = $b['respiratory_rate'] ?? '—';
                    $bmi = '—';
                    if (is_numeric($h_val) && is_numeric($w_val) && floatval($h_val) > 0) {
                        $h_meters = floatval($h_val) / 100;
                        $bmi = round(floatval($w_val) / ($h_meters * $h_meters), 2);
                    }
                    ?>
                    <div class="record-card">
                        <div class="record-left">
                            <div class="record-avatar"><?= $avatar ?></div>
                            <div>
                                <div style="font-weight:600;color:#0D254A">Combined Record</div>
                                <div class="record-time"><?= $ts ?></div>
                            </div>
                            <div class="vital-grid" style="margin-top:12px">
                                <div class="vital-box"><div class="vital-label">Height</div><div class="vital-value"><?= htmlspecialchars($h_val) ?> cm</div></div>
                                <div class="vital-box"><div class="vital-label">Weight</div><div class="vital-value"><?= htmlspecialchars($w_val) ?> kg</div></div>
                                <div class="vital-box"><div class="vital-label">BP</div><div class="vital-value"><?= htmlspecialchars($bp) ?></div></div>
                                <div class="vital-box"><div class="vital-label">Temperature</div><div class="vital-value"><?= htmlspecialchars($temp) ?> °C</div></div>
                                <div class="vital-box"><div class="vital-label">Pulse</div><div class="vital-value"><?= htmlspecialchars($pulse) ?> bpm</div></div>
                                <div class="vital-box"><div class="vital-label">Respiration</div><div class="vital-value"><?= htmlspecialchars($resp) ?> cpm</div></div>
                                <div class="vital-box"><div class="vital-label">BMI</div><div class="vital-value"><?= htmlspecialchars($bmi) ?></div></div>
                            </div>
                        </div>
                        <div class="record-right">
                            <?php $export_ts = isset($entry['ts']) ? urlencode($entry['ts']) : urlencode($ts); ?>
                            <div style="display:flex;justify-content:flex-end">
                                <a href="export_record_pdf.php?user_id=<?= (int)$user_id ?>&ts=<?= $export_ts ?>" target="_blank" class="btn-convert" data-entry-ts="<?= htmlspecialchars($export_ts) ?>">Download PDF</a>
                            </div>
                            <div style="font-weight:600;color:#0D254A">Clinical Notes</div>
                            <?php
                                // Fallback: diagnosis -> assessment_notes
                                // Get values from the database columns
                                $diag_raw = !empty($h['diagnosis']) ? $h['diagnosis'] : '';
                                $interv_raw = !empty($h['notes']) ? $h['notes'] : '';
                                // prefer explicit doctors_recommendations column
                                $rec_raw = !empty($h['doctors_recommendations']) ? $h['doctors_recommendations'] : '';

                                // Clean up each text field
                                $diag = clean_clinical_text($diag_raw);
                                $interv = clean_clinical_text($interv_raw);
                                $rec = clean_clinical_text($rec_raw);

                                    // If recommendation is empty, try same-day lookup for this user
                                    if (trim($rec) === '') {
                                        $fk = isset($fk_col) ? $fk_col : 'user_id';
                                        $stmtR = mysqli_prepare($conn, "SELECT doctors_recommendations FROM patient_history_records WHERE {$fk} = ? AND DATE(created_at) = DATE(?) AND TRIM(COALESCE(doctors_recommendations,'')) <> '' ORDER BY created_at DESC LIMIT 1");
                                        if ($stmtR) {
                                            mysqli_stmt_bind_param($stmtR, 'is', $user_id, $h['created_at']);
                                            mysqli_stmt_execute($stmtR);
                                            $rres = mysqli_stmt_get_result($stmtR);
                                            if ($rrow = mysqli_fetch_assoc($rres)) {
                                                $rec = clean_clinical_text($rrow['doctors_recommendations'] ?? '');
                                            }
                                            mysqli_stmt_close($stmtR);
                                        }
                                    }

                                // Use cleaned text as-is (already normalized by clean_clinical_text)
                                    $diag_display = $diag;
                                    $interv_display = $interv;                                // Fallbacks: if processing removed content, try to pull from the original cleaned fields
                                if ($diag_display === '') {
                                    $parts = preg_split("/(?:\\r?\\n\\s*\\r?\\n)+|\\n\\s*-{3,}\\s*\\n/", $diag);
                                    if (is_array($parts)) {
                                        foreach ($parts as $part) {
                                            $part = trim($part);
                                            if ($part === '') continue;
                                            $lines = preg_split("/\r?\n/", $part);
                                            if (is_array($lines)) {
                                                foreach ($lines as $l) {
                                                    $l = trim($l);
                                                    if ($l !== '') { $diag_display = $l; break 2; }
                                                }
                                            } else {
                                                $diag_display = $part; break;
                                            }
                                        }
                                    }
                                }

                                if ($interv_display === '') {
                                    $parts = preg_split("/(?:\\r?\\n\\s*\\r?\\n)+|\\n\\s*-{3,}\\s*\\n/", $interv);
                                    if (is_array($parts) && count($parts)) {
                                        for ($pi = count($parts) - 1; $pi >= 0; $pi--) {
                                            $part = trim($parts[$pi]);
                                            if ($part === '') continue;
                                            $lines = preg_split("/\r?\n/", $part);
                                            if (is_array($lines)) {
                                                for ($li = count($lines) - 1; $li >= 0; $li--) {
                                                    $l = trim($lines[$li]);
                                                    if ($l !== '') { $interv_display = $l; break 2; }
                                                }
                                            } else {
                                                $interv_display = $part; break;
                                            }
                                        }
                                    }
                                }

                                // Extra fallback: if diagnosis still empty, try other related fields (symptoms, notes)
                                if ($diag_display === '') {
                                    $candidates = [];
                                    if (!empty($h['diagnosis'])) $candidates[] = $h['diagnosis'];
                                    if (!empty($h['assessment_notes'])) $candidates[] = $h['assessment_notes'];
                                    if (!empty($h['symptoms'])) $candidates[] = $h['symptoms'];
                                    if (!empty($h['notes'])) $candidates[] = $h['notes'];
                                    foreach ($candidates as $cand) {
                                        $cand_clean = clean_clinical_text($cand);
                                        $lines = preg_split("/\r?\n/", $cand_clean);
                                        if (is_array($lines)) {
                                            foreach ($lines as $ln) {
                                                $ln = trim($ln);
                                                if ($ln !== '') { $diag_display = $ln; break 2; }
                                            }
                                        } elseif (trim($cand_clean) !== '') {
                                            $diag_display = trim($cand_clean); break;
                                        }
                                    }
                                }

                                // Display-time fallback: if diagnosis still empty, copy doctor's recommendation or nurse recommendation
                                $nurse_candidate = isset($b['nurse_recommendation']) ? clean_clinical_text($b['nurse_recommendation']) : '';
                                if ($diag_display === '') {
                                    if (trim($rec) !== '') {
                                        $diag_display = $rec;
                                    } elseif ($nurse_candidate !== '') {
                                        $diag_display = $nurse_candidate;
                                    }
                                }

                                if ($interv_display === '') {
                                    if (trim($rec) !== '') {
                                        $interv_display = $rec;
                                    } elseif ($nurse_candidate !== '') {
                                        $interv_display = $nurse_candidate;
                                    }
                                }

                                // DEBUG: output cleaned/raw values (visible in page source) to help debug missing text
                                ?>
                                <!-- DEBUG combined: diag (cleaned): <?= htmlspecialchars($diag) ?> -->
                                <!-- DEBUG combined: diag_display: <?= htmlspecialchars($diag_display) ?> -->
                                <!-- DEBUG combined: interv (cleaned): <?= htmlspecialchars($interv) ?> -->
                                <!-- DEBUG combined: interv_display: <?= htmlspecialchars($interv_display) ?> -->
                                <?php
                            ?>

                            <?php if (!empty($diag_display) || !empty($diag)): ?>
                                <div class="card-text" style="margin-top:12px;">
                                    <strong>Diagnosis:</strong>
                                    <div style="font-size:13px;color:#666;margin-top:6px;word-wrap:break-word;white-space:pre-wrap;"><?= render_clinical_html($diag_display !== '' ? $diag_display : $diag) ?></div>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($interv_display) || !empty($interv)): ?>
                                <div class="card-text" style="margin-top:12px;">
                                    <strong>Patient history:</strong>
                                    <div style="font-size:13px;color:#666;margin-top:6px;word-wrap:break-word;white-space:pre-wrap;"><?= render_clinical_html($interv_display !== '' ? $interv_display : $interv) ?></div>
                                </div>
                            <?php endif; ?>

                            <?php if ($rec !== ''): ?>
                                <div class="card-text" style="margin-top:12px;">
                                    <strong>Doctors treatment:</strong>
                                    <div style="font-size:13px;color:#666;margin-top:0;word-wrap:break-word;white-space:pre-wrap;"><?= render_clinical_html($rec) ?></div>
                                </div>
                            <?php endif; ?>

                            <?php $nurse_rec = isset($b['nurse_recommendation']) ? trim((string)$b['nurse_recommendation']) : ''; ?>
                            <?php if ($nurse_rec !== ''): ?>
                                <div class="card-text" style="margin-top:12px;">
                                    <strong>Nurse consultation:</strong>
                                    <div style="font-size:13px;color:#666;margin-top:0;word-wrap:break-word;white-space:pre-wrap;"><?= render_clinical_html($nurse_rec) ?></div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php } else { 
                    // Handle single biometric or clinical records similarly
                    $data = $entry['data'];
                    $ts = fmt_ts_entry(['data' => $data]);
                    ?>
                    <div class="record-card">
                        <div class="record-left">
                            <div class="record-avatar"><?= $avatar ?></div>
                            <div>
                                <div style="font-weight:600;color:#0D254A"><?= $type === 'biometric' ? 'Biometric Record' : 'Clinical Record' ?></div>
                                <div class="record-time"><?= htmlspecialchars($ts) ?></div>
                            </div>
                        </div>
                        <?php
                            // prepare export timestamp: prefer original entry ts if available
                            $export_ts = isset($entry['ts']) ? urlencode($entry['ts']) : urlencode($ts);
                        ?>
                        <div class="record-right">
                            <div style="display:flex;justify-content:flex-end">
                                <a href="export_record_pdf.php?user_id=<?= (int)$user_id ?>&ts=<?= $export_ts ?>" target="_blank" class="btn-convert" data-entry-ts="<?= htmlspecialchars($export_ts) ?>">Download PDF</a>
                            </div>
                            <?php if ($type === 'biometric'): 
                                $h_val = $data['height'] ?? '—';
                                $w_val = $data['weight'] ?? '—';
                                $bp = $data['blood_pressure'] ?? '—';
                                $temp = $data['temperature'] ?? '—';
                                $pulse = $data['pulse_rate'] ?? '—';
                                $resp = $data['respiratory_rate'] ?? '—';
                                $bmi = '—';
                                if (is_numeric($h_val) && is_numeric($w_val) && floatval($h_val) > 0) {
                                    $h_meters = floatval($h_val) / 100;
                                    $bmi = round(floatval($w_val) / ($h_meters * $h_meters), 2);
                                }
                                ?>
                                <div class="vital-grid">
                                    <div class="vital-box"><div class="vital-label">Height</div><div class="vital-value"><?= htmlspecialchars($h_val) ?> cm</div></div>
                                    <div class="vital-box"><div class="vital-label">Weight</div><div class="vital-value"><?= htmlspecialchars($w_val) ?> kg</div></div>
                                    <div class="vital-box"><div class="vital-label">BP</div><div class="vital-value"><?= htmlspecialchars($bp) ?></div></div>
                                    <div class="vital-box"><div class="vital-label">Temperature</div><div class="vital-value"><?= htmlspecialchars($temp) ?> °C</div></div>
                                    <div class="vital-box"><div class="vital-label">Pulse</div><div class="vital-value"><?= htmlspecialchars($pulse) ?> bpm</div></div>
                                    <div class="vital-box"><div class="vital-label">Respiration</div><div class="vital-value"><?= htmlspecialchars($resp) ?> cpm</div></div>
                                    <div class="vital-box"><div class="vital-label">BMI</div><div class="vital-value"><?= htmlspecialchars($bmi) ?></div></div>
                                </div>
                                <div style="font-weight:600;color:#0D254A;margin-top:16px">Clinical Notes</div>
                                <div class="card-text">
                                    <strong>Diagnosis:</strong><br>
                                    <em>—</em>
                                </div>
                                <div class="card-text" style="margin-top:12px">
                                    <strong>Patient Interview / Notes:</strong><br>
                                    <em>—</em>
                                </div>
                                <div class="card-text" style="margin-top:12px">
                                    <strong>Doctor's Recommendations:</strong><br>
                                    <em>—</em>
                                </div>
                            <?php else: ?>
                                <?php
                                    // Get values from database columns for standalone records
                                    $diag_raw = !empty($data['diagnosis']) ? $data['diagnosis'] : '';
                                    $interv_raw = !empty($data['notes']) ? $data['notes'] : '';
                                    $rec_raw = !empty($data['doctors_recommendations']) ? $data['doctors_recommendations'] : '';

                                    // Clean up each text field
                                    $diag = clean_clinical_text($diag_raw);
                                    $interv = clean_clinical_text($interv_raw);
                                    $rec = clean_clinical_text($rec_raw);

                                        // Fallback same-day lookup for standalone history entries
                                        if (trim($rec) === '') {
                                            $fk = isset($fk_col) ? $fk_col : 'user_id';
                                            $stmtR = mysqli_prepare($conn, "SELECT doctors_recommendations FROM patient_history_records WHERE {$fk} = ? AND DATE(created_at) = DATE(?) AND TRIM(COALESCE(doctors_recommendations,'')) <> '' ORDER BY created_at DESC LIMIT 1");
                                            if ($stmtR) {
                                                mysqli_stmt_bind_param($stmtR, 'is', $user_id, $data['created_at']);
                                                mysqli_stmt_execute($stmtR);
                                                $rres = mysqli_stmt_get_result($stmtR);
                                                if ($rrow = mysqli_fetch_assoc($rres)) {
                                                    $rec = clean_clinical_text($rrow['doctors_recommendations'] ?? '');
                                                }
                                                mysqli_stmt_close($stmtR);
                                            }
                                        }

                                    // Split into paragraphs (blank-line separated or '---' separators)
                                    $diag_paragraphs = preg_split("/(?:\\r?\\n\\s*\\r?\\n)+|\\n\\s*-{3,}\\s*\\n/", $diag);
                                    $diag_display = '';
                                    if (is_array($diag_paragraphs) && count($diag_paragraphs)) {
                                        foreach ($diag_paragraphs as $p) {
                                            $p = trim($p);
                                            if ($p !== '') { $diag_display = $p; break; }
                                        }
                                    } else {
                                        $diag_display = trim($diag);
                                    }

                                    $interv_paragraphs = preg_split("/(?:\\r?\\n\\s*\\r?\\n)+|\\n\\s*-{3,}\\s*\\n/", $interv);
                                    $interv_display = '';
                                    if (is_array($interv_paragraphs) && count($interv_paragraphs)) {
                                        for ($i = count($interv_paragraphs) - 1; $i >= 0; $i--) {
                                            $p = trim($interv_paragraphs[$i]);
                                            if ($p !== '') { $interv_display = $p; break; }
                                        }
                                    } else {
                                        $interv_display = trim($interv);
                                    }

                                    // Remove overlapping content
                                    if ($interv_display !== '' && $diag_display !== '' && mb_stripos($diag_display, $interv_display) !== false) {
                                        $diag_display = trim(str_ireplace($interv_display, '', $diag_display));
                                    }
                                    if ($interv_display !== '' && $diag_display !== '' && mb_stripos($interv_display, $diag_display) !== false) {
                                        $interv_display = trim(str_ireplace($diag_display, '', $interv_display));
                                    }

                                    // Keep only single meaningful lines: diagnosis -> first, interview -> last
                                    $diag_lines = preg_split("/\r?\n/", $diag_display);
                                    $diag_display = '';
                                    if (is_array($diag_lines)) {
                                        foreach ($diag_lines as $ln) {
                                            $ln = trim($ln);
                                            if ($ln !== '') { $diag_display = $ln; break; }
                                        }
                                    }

                                    $interv_lines = preg_split("/\r?\n/", $interv_display);
                                    $interv_display = '';
                                    if (is_array($interv_lines)) {
                                        for ($i = count($interv_lines) - 1; $i >= 0; $i--) {
                                            $ln = trim($interv_lines[$i]);
                                            if ($ln !== '') { $interv_display = $ln; break; }
                                        }
                                    }

                                    // Extra fallback for history-only: try other related fields if diagnosis is still empty
                                    if ($diag_display === '') {
                                        $candidates = [];
                                        if (!empty($data['diagnosis'])) $candidates[] = $data['diagnosis'];
                                        if (!empty($data['assessment_notes'])) $candidates[] = $data['assessment_notes'];
                                        if (!empty($data['symptoms'])) $candidates[] = $data['symptoms'];
                                        if (!empty($data['notes'])) $candidates[] = $data['notes'];
                                        foreach ($candidates as $cand) {
                                            $cand_clean = clean_clinical_text($cand);
                                            $lines = preg_split("/\r?\n/", $cand_clean);
                                            if (is_array($lines)) {
                                                foreach ($lines as $ln) {
                                                    $ln = trim($ln);
                                                    if ($ln !== '') { $diag_display = $ln; break 2; }
                                                }
                                            } elseif (trim($cand_clean) !== '') {
                                                $diag_display = trim($cand_clean); break;
                                            }
                                        }
                                    }

                                    if ($interv_display === '') {
                                        $parts = preg_split("/(?:\\r?\\n\\s*\\r?\\n)+|\\n\\s*-{3,}\\s*\\n/", $interv);
                                        if (is_array($parts) && count($parts)) {
                                            for ($pi = count($parts) - 1; $pi >= 0; $pi--) {
                                                $part = trim($parts[$pi]);
                                                if ($part === '') continue;
                                                $lines = preg_split("/\r?\n/", $part);
                                                if (is_array($lines)) {
                                                    for ($li = count($lines) - 1; $li >= 0; $li--) {
                                                        $l = trim($lines[$li]);
                                                        if ($l !== '') { $interv_display = $l; break 2; }
                                                    }
                                                } else {
                                                    $interv_display = $part; break;
                                                }
                                            }
                                        }

                                        // Display-time fallback for standalone records: copy doctor's or nurse recommendations if fields empty
                                        $nurse_candidate = isset($data['nurse_recommendation']) ? clean_clinical_text($data['nurse_recommendation']) : '';
                                        if ($diag_display === '') {
                                            if (trim($rec) !== '') {
                                                $diag_display = $rec;
                                            } elseif ($nurse_candidate !== '') {
                                                $diag_display = $nurse_candidate;
                                            }
                                        }
                                        if ($interv_display === '') {
                                            if (trim($rec) !== '') {
                                                $interv_display = $rec;
                                            } elseif ($nurse_candidate !== '') {
                                                $interv_display = $nurse_candidate;
                                            }
                                        }
                                    }
                                ?>

                                <?php if (!empty($diag_display) || !empty($diag)): ?>
                                    <div class="card-text" style="margin-top:12px;">
                                        <strong>Diagnosis:</strong><br style="margin:0;padding:0;line-height:0;height:0;">
                                        <span style="font-size:13px;color:#666;word-wrap:break-word;white-space:pre-wrap;display:block;margin:0;padding:0;"><?= render_clinical_html($diag_display !== '' ? $diag_display : $diag) ?></span>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($interv_display) || !empty($interv)): ?>
                                    <div class="card-text" style="margin-top:12px;">
                                        <strong>Patient history:</strong><br style="margin:0;padding:0;line-height:0;height:0;">
                                        <span style="font-size:13px;color:#666;word-wrap:break-word;white-space:pre-wrap;display:block;margin:0;padding:0;"><?= render_clinical_html($interv_display !== '' ? $interv_display : $interv) ?></span>
                                    </div>
                                <?php endif; ?>

                                <?php if ($rec !== ''): ?>
                                    <div class="card-text" style="margin-top:12px;">
                                        <strong>Doctors treatment:</strong>
                                        <div style="font-size:13px;color:#666;margin-top:0;word-wrap:break-word;white-space:pre-wrap;"><?= render_clinical_html($rec) ?></div>
                                    </div>
                                <?php endif; ?>

                                    <?php $nurse_rec_history = isset($data['nurse_recommendation']) ? trim((string)$data['nurse_recommendation']) : ''; ?>
                                <?php if ($nurse_rec_history !== ''): ?>
                                    <div class="card-text" style="margin-top:12px;">
                                        <strong>Nurse consultation:</strong>
                                        <div style="font-size:13px;color:#666;margin-top:0;word-wrap:break-word;white-space:pre-wrap;"><?= render_clinical_html($nurse_rec_history) ?></div>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                        </div>
                    </div>
                <?php }
            }
        } ?>
    </div>
</body>
</html>