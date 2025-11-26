<?php
// Debug helper: prints raw and hex/codepoint view of a clinical field for a given user record
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../config/functions.php';
require_once '../config/database.php';

if (!isset($_SESSION['loggedin']) || !isset($_SESSION['isAdmin']) || $_SESSION['isAdmin'] !== true) {
    http_response_code(403);
    echo "Forbidden: admin login required";
    exit;
}

$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$ts = isset($_GET['ts']) ? trim($_GET['ts']) : '';
$field = isset($_GET['field']) ? trim($_GET['field']) : 'diagnosis';

if ($user_id <= 0 || $ts === '') {
    echo "Usage: ?user_id=NN&ts=TIMESTAMP&field=diagnosis|interview|assessment_notes\n";
    exit;
}

$fk_col = function_exists('get_history_fk_column') ? get_history_fk_column() : 'user_id';

$sql = "SELECT * FROM patient_history_records WHERE {$fk_col} = ? AND (created_at = ? OR visit_date = ?) LIMIT 1";
$stmt = @mysqli_prepare($conn, $sql);
if (!$stmt) {
    // fallback to created_at only
    $sql = "SELECT * FROM patient_history_records WHERE {$fk_col} = ? AND created_at = ? LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
}
mysqli_stmt_bind_param($stmt, 'iss', $user_id, $ts, $ts);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$row = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

header('Content-Type: text/plain; charset=utf-8');
if (!$row) { echo "No record found matching provided ts for user_id={$user_id}\n"; exit; }

if (!array_key_exists($field, $row)) {
    echo "Field '{$field}' not present in row. Available fields: " . implode(', ', array_keys($row)) . "\n";
    exit;
}

$val = (string)$row[$field];
echo "--- Raw value (as stored) ---\n";
echo $val . "\n\n";

echo "--- php strlen / mb_strlen ---\n";
echo "bytes: " . strlen($val) . "  chars (mb): " . mb_strlen($val, 'UTF-8') . "\n\n";

echo "--- Leading bytes (hex) ---\n";
$prefix = substr($val, 0, 64);
echo bin2hex($prefix) . "\n\n";

echo "--- Codepoints (first 64 chars) ---\n";
for ($i = 0; $i < mb_strlen($prefix, 'UTF-8'); $i++) {
    $ch = mb_substr($prefix, $i, 1, 'UTF-8');
    if (function_exists('IntlChar::ord') || class_exists('IntlChar')) {
        $cp = @IntlChar::ord($ch);
    } else {
        // fallback: get codepoint using unpack
        $utf8 = mb_convert_encoding($ch, 'UTF-8', 'UTF-8');
        $bytes = unpack('C*', $utf8);
        $cp = 0;
        foreach ($bytes as $b) { $cp = ($cp << 8) + $b; }
    }
    printf("U+%04X %s\n", $cp, $ch === "\n" ? '\\n' : ($ch === ' ' ? '[space]' : $ch));
}

echo "\n--- After ltrim_unicode() output ---\n";
echo ltrim_unicode($val) . "\n";

exit;

?>
