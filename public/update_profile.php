<?php
require_once '../config/config.php';

// Require login
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: profile.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$name = trim($_POST['name'] ?? '');
$hostel = trim($_POST['hostel'] ?? '');
$roll_no = trim($_POST['roll_no'] ?? '');
$current_password = $_POST['current_password'] ?? '';
$new_password = $_POST['new_password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

$errors = [];

// Validate name
if (empty($name)) {
    $errors[] = "Name is required";
} elseif (strlen($name) > 100) {
    $errors[] = "Name cannot exceed 100 characters";
}

// Validate hostel
if (!empty($hostel) && strlen($hostel) > 50) {
    $errors[] = "Hostel name cannot exceed 50 characters";
}

// Validate roll number
if (!empty($roll_no) && strlen($roll_no) > 20) {
    $errors[] = "Roll number cannot exceed 20 characters";
}

// Password validation if changing password
if (!empty($new_password)) {
    // Get current user password hash
    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);

    // Verify current password
    if (empty($current_password)) {
        $errors[] = "Current password is required to change password";
    } elseif (!password_verify($current_password, $user_data['password'])) {
        $errors[] = "Current password is incorrect";
    }

    // Validate new password
    if (strlen($new_password) < 6) {
        $errors[] = "New password must be at least 6 characters";
    }

    // Confirm passwords match
    if ($new_password !== $confirm_password) {
        $errors[] = "Passwords do not match";
    }
}

// Handle file upload
$profile_pic_path = null;
if (!empty($_FILES['profile_pic']['name'])) {
    $file = $_FILES['profile_pic'];
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $max_size = 2 * 1024 * 1024; // 2MB

    // Validate file type
    if (!in_array($file['type'], $allowed_types)) {
        $errors[] = "Only JPG, PNG, GIF, and WebP images are allowed";
    }

    // Validate file size
    if ($file['size'] > $max_size) {
        $errors[] = "File size must not exceed 2MB";
    }

    // Validate no upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = "File upload failed. Please try again.";
    }

    if (empty($errors)) {
        // Create unique filename
        $file_ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $file_name = 'profile_' . $user_id . '_' . time() . '.' . $file_ext;
        $file_path = '../storage/uploads/' . $file_name;

        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $file_path)) {
            $profile_pic_path = 'storage/uploads/' . $file_name;
        } else {
            $errors[] = "Failed to save profile picture";
        }
    }
}

// If there are errors, redirect back with error messages
if (!empty($errors)) {
    $error_msg = implode("; ", $errors);
    header('Location: profile.php?edit=1&error=' . urlencode($error_msg));
    exit();
}

try {
    $conn->beginTransaction();

    // Prepare update query
    $update_fields = ['name' => $name, 'hostel' => $hostel, 'roll_no' => $roll_no];

    if ($profile_pic_path) {
        $update_fields['profile_pic'] = $profile_pic_path;
    }

    if (!empty($new_password)) {
        $update_fields['password'] = password_hash($new_password, PASSWORD_DEFAULT);
    }

    // Build SQL query dynamically
    $set_clauses = [];
    $params = [];
    foreach ($update_fields as $field => $value) {
        $set_clauses[] = "$field = ?";
        $params[] = $value;
    }
    $params[] = $user_id;

    $sql = "UPDATE users SET " . implode(", ", $set_clauses) . " WHERE id = ?";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);

    $conn->commit();

    // Redirect with success message
    header('Location: profile.php?success=' . urlencode('Profile updated successfully!'));
    exit();

} catch (Exception $e) {
    $conn->rollBack();
    header('Location: profile.php?edit=1&error=' . urlencode('An error occurred while updating your profile. Please try again.'));
    exit();
}
?>
