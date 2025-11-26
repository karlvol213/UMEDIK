<?php
session_start();

require_once '../config/database.php';
require_once '../config/admin_access.php';

header('Content-Type: application/json');

// Check if user is logged in and is a super admin
if (!isset($_SESSION['loggedin']) || !isset($_SESSION['isAdmin']) || $_SESSION['isAdmin'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Check if user has admin role (super admin only)
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Only super admins can edit patient information']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;

if ($user_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
    exit();
}

try {
    // Sanitize input
    $first_name = isset($_POST['first_name']) ? trim($_POST['first_name']) : '';
    $last_name = isset($_POST['last_name']) ? trim($_POST['last_name']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
    $sex = isset($_POST['sex']) ? trim($_POST['sex']) : '';
    $birthday = isset($_POST['birthday']) ? trim($_POST['birthday']) : '';
    $department = isset($_POST['department']) ? trim($_POST['department']) : '';
    $address = isset($_POST['address']) ? trim($_POST['address']) : '';
    $student_number = isset($_POST['student_number']) ? trim($_POST['student_number']) : '';
    $special_status = isset($_POST['special_status']) ? trim($_POST['special_status']) : 'none';

    // Validate required fields
    if (empty($first_name) || empty($last_name) || empty($email)) {
        echo json_encode(['success' => false, 'message' => 'First name, last name, and email are required']);
        exit();
    }

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid email format']);
        exit();
    }

    // Update user information
    $full_name = trim($first_name . ' ' . $last_name);
    
    $sql = "UPDATE users SET 
            first_name = :first_name,
            last_name = :last_name,
            full_name = :full_name,
            email = :email,
            phone = :phone,
            sex = :sex,
            birthday = :birthday,
            department = :department,
            address = :address,
            student_number = :student_number,
            special_status = :special_status,
            updated_at = NOW()
            WHERE id = :user_id";

    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([
        ':first_name' => $first_name,
        ':last_name' => $last_name,
        ':full_name' => $full_name,
        ':email' => $email,
        ':phone' => $phone,
        ':sex' => $sex,
        ':birthday' => $birthday ? $birthday : null,
        ':department' => $department,
        ':address' => $address,
        ':student_number' => $student_number,
        ':special_status' => $special_status,
        ':user_id' => $user_id
    ]);

    if ($result) {
        // Log the edit action
        if (function_exists('log_action')) {
            log_action($_SESSION['user_id'], 'EDIT_USER_INFO', "Administrator edited patient information for user_id={$user_id}");
        }
        
        echo json_encode(['success' => true, 'message' => 'Patient information updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update patient information']);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
