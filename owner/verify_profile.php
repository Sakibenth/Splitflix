<?php
session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$owner_id = $_SESSION['user_id'];
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone = trim($_POST['phone'] ?? '');

    // Validate phone number (allow digits, +, -, spaces, min 7 chars)
    if (empty($phone) || strlen(preg_replace('/[^0-9]/', '', $phone)) < 7) {
        $error = "Please enter a valid phone number.";
    }

    // Validate ID card image upload
    if (empty($error)) {
        if (!isset($_FILES['id_card']) || $_FILES['id_card']['error'] !== UPLOAD_ERR_OK) {
            $error = "Please upload a photo of your ID card.";
        } else {
            $file = $_FILES['id_card'];
            $allowed_types = ['image/jpeg', 'image/png', 'image/webp'];
            $max_size = 5 * 1024 * 1024; // 5MB

            // Validate file type
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            if (!in_array($mime_type, $allowed_types)) {
                $error = "Only JPG, PNG, and WebP images are allowed.";
            } elseif ($file['size'] > $max_size) {
                $error = "File size must be under 5MB.";
            }
        }
    }

    // Process upload if no errors
    if (empty($error)) {
        // Create uploads directory if it doesn't exist
        $upload_dir = __DIR__ . '/../uploads/id_cards/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        // Generate unique filename
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'id_' . $owner_id . '_' . time() . '.' . $ext;
        $filepath = $upload_dir . $filename;

        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            // Save relative path in DB
            $relative_path = 'uploads/id_cards/' . $filename;

            // Update user record — both phone + id card = verified
            $stmt = mysqli_prepare($conn, "UPDATE users SET phone = ?, id_card_image = ?, verification_status = 'verified' WHERE user_id = ?");
            mysqli_stmt_bind_param($stmt, "ssi", $phone, $relative_path, $owner_id);

            if (mysqli_stmt_execute($stmt)) {
                $success = "Profile verified successfully!";
                $_SESSION['verification_status'] = 'verified';
            } else {
                $error = "Database error: " . mysqli_error($conn);
            }
            mysqli_stmt_close($stmt);
        } else {
            $error = "Failed to upload file. Please try again.";
        }
    }

    // Redirect back with status
    $redirect = $_POST['redirect_to'] ?? 'dashboard.php';
    // Validate redirect to prevent open redirect
    if (!in_array(parse_url($redirect, PHP_URL_PATH), ['dashboard.php', 'create_group.php'])) {
        $redirect = 'dashboard.php';
    }
    
    // Append platform_id if present
    if (isset($_GET['platform_id'])) {
        $redirect .= (strpos($redirect, '?') !== false ? '&' : '?') . 'platform_id=' . (int)$_GET['platform_id'];
    } else if (isset($_POST['redirect_to']) && strpos($_POST['redirect_to'], 'platform_id=') !== false) {
        $redirect = $_POST['redirect_to'];
    }

    if (!empty($success)) {
        $separator = (strpos($redirect, '?') !== false) ? '&' : '?';
        header("Location: " . $redirect . $separator . "verified=1");
        exit();
    } else {
        $separator = (strpos($redirect, '?') !== false) ? '&' : '?';
        header("Location: " . $redirect . $separator . "verify_error=" . urlencode($error));
        exit();
    }
}

// If accessed via GET, redirect to dashboard
header("Location: dashboard.php");
exit();
?>
