<?php
// Migration: split assessment_notes into diagnosis (assessment_notes) and interview (notes)
// Backup originals to migrations/backup_patient_history_before_split.csv

// Try to use the app's DB config if available. For CLI environments where
// PDO may not be enabled, fall back to creating a mysqli connection directly.
$dbHost = '127.0.0.1';
$dbName = 'medical_appointment_db';
$dbUser = 'root';
$dbPass = '';
$dbPort = 3306;

$conn = @new mysqli($dbHost, $dbUser, $dbPass, $dbName, $dbPort);
if ($conn->connect_error) {
    // Fall back to trying to load app config (best-effort). If that fails,
    // the script will still try with defaults above.
    // Note: avoid require_once('../config/database.php') because some setups
    // may die early if PDO driver is missing when included.
    echo "Warning: mysqli connect failed ({$conn->connect_error}), continuing with defaults.\n";
}

$backupFile = __DIR__ . '/backup_patient_history_before_split.csv';
$fh = fopen($backupFile, 'w');
if (!$fh) {
    echo "Failed to open backup file: $backupFile\n";
    exit(1);
}
// header
fputcsv($fh, ['id','assessment_notes_original','notes_original','updated_assessment_notes','updated_notes']);

// Find rows where assessment_notes looks like it contains both Diagnosis and Interview
$sql = "SELECT id, COALESCE(assessment_notes, '') AS assessment_notes, COALESCE(notes, '') AS notes FROM patient_history_records WHERE assessment_notes IS NOT NULL AND assessment_notes <> ''";
$res = mysqli_query($conn, $sql);
if (!$res) {
    echo "Query failed: " . mysqli_error($conn) . "\n";
    exit(1);
}

$updated = 0;
$total = 0;
while ($row = mysqli_fetch_assoc($res)) {
    $total++;
    $id = (int)$row['id'];
    $a = str_replace(["\r\n","\r"], "\n", $row['assessment_notes']);
    $n = str_replace(["\r\n","\r"], "\n", $row['notes']);

    // look for 'Diagnosis:' and 'Interview:' labels (case-insensitive)
    $diag = '';
    $interv = '';

    $lower = mb_strtolower($a);
    $hasDiag = mb_strpos($lower, 'diagnosis:') !== false;
    $hasInterv = mb_strpos($lower, 'interview:') !== false;

    if ($hasDiag && $hasInterv) {
        // Extract text between Diagnosis: and Interview:
        $posDiag = mb_stripos($a, 'Diagnosis:');
        $posInterv = mb_stripos($a, 'Interview:');
        if ($posDiag !== false && $posInterv !== false && $posInterv > $posDiag) {
            $start = $posDiag + mb_strlen('Diagnosis:');
            $diag = trim(mb_substr($a, $start, $posInterv - $start));
            $start2 = $posInterv + mb_strlen('Interview:');
            $interv = trim(mb_substr($a, $start2));
        }
    } elseif ($hasDiag && !$hasInterv) {
        // Only diagnosis label present: extract after Diagnosis:
        $posDiag = mb_stripos($a, 'Diagnosis:');
        $start = $posDiag + mb_strlen('Diagnosis:');
        $diag = trim(mb_substr($a, $start));
    } elseif (!$hasDiag && $hasInterv) {
        // Only interview label present: extract after Interview:
        $posInterv = mb_stripos($a, 'Interview:');
        $start2 = $posInterv + mb_strlen('Interview:');
        $interv = trim(mb_substr($a, $start2));
    }

    // If parsing found values, update; else skip
    $newA = $diag !== '' ? $diag : ''; // keep only diagnosis text
    $newN = $interv !== '' ? $interv : $n; // prefer parsed interview, fallback to existing notes

    // If there's nothing to change, skip
    if ($newA === trim($a) && $newN === trim($n)) {
        // still write CSV backup entry for traceability
        fputcsv($fh, [$id, $a, $n, $newA, $newN]);
        continue;
    }

    // Backup current and planned values
    fputcsv($fh, [$id, $a, $n, $newA, $newN]);

    // Run update
    $stmt = mysqli_prepare($conn, "UPDATE patient_history_records SET assessment_notes = ?, notes = ? WHERE id = ?");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'ssi', $newA, $newN, $id);
        mysqli_stmt_execute($stmt);
        if (mysqli_stmt_affected_rows($stmt) >= 0) {
            $updated++;
        }
        mysqli_stmt_close($stmt);
    } else {
        echo "Prepare failed for id=$id: " . mysqli_error($conn) . "\n";
    }
}

fclose($fh);

echo "Total rows scanned: $total\n";
echo "Rows updated: $updated\n";
echo "Backup CSV: $backupFile\n";

?>
