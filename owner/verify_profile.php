<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../assets/includes/security.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$owner_id = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: dashboard.php");
    exit();
}

// Only allow redirects back to known pages
$redirect = $_POST['redirect_to'] ?? 'dashboard.php';
if (!is_string($redirect) || !preg_match('/^(dashboard\.php|create_group\.php)(\?platform_id=\d+)?$/', $redirect)) {
    $redirect = 'dashboard.php';
}

if (!csrf_validate($_POST['csrf_token'] ?? '')) {
    flash_set('error', 'Your session expired. Please refresh the page and try again.');
    header("Location: " . $redirect);
    exit();
}

$phone = trim($_POST['phone'] ?? '');
$error = '';

// Validate phone number (at least 7 digits)
if ($phone === '' || strlen(preg_replace('/[^0-9]/', '', $phone)) < 7) {
    $error = "Please enter a valid phone number.";
} elseif (strlen($phone) > 20) {
    $error = "Phone number is too long.";
}

// Validate ID card image upload
$file = null;
$extension = null;
if ($error === '') {
    if (!isset($_FILES['id_card']) || $_FILES['id_card']['error'] !== UPLOAD_ERR_OK) {
        $error = "Please upload a photo of your ID card.";
    } else {
        $file = $_FILES['id_card'];
        $allowed_types = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
        ];
        $max_size = 5 * 1024 * 1024; // 5MB

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = $finfo ? finfo_file($finfo, $file['tmp_name']) : false;
        if ($finfo) {
            finfo_close($finfo);
        }

        if (!isset($allowed_types[$mime_type])) {
            $error = "Only JPG, PNG, and WebP images are allowed.";
        } elseif ($file['size'] > $max_size) {
            $error = "File size must be under 5MB.";
        } elseif (!is_uploaded_file($file['tmp_name'])) {
            $error = "Invalid upload. Please try again.";
        } else {
            // Never trust the client-supplied extension
            $extension = $allowed_types[$mime_type];
        }
    }
}

// Process upload if no errors
if ($error === '' && $file !== null && $extension !== null) {
    $upload_dir = __DIR__ . '/../uploads/id_cards/';
    if (!is_dir($upload_dir) && !mkdir($upload_dir, 0755, true) && !is_dir($upload_dir)) {
        $error = "Could not create the upload directory.";
    } else {
        $filename = 'id_' . $owner_id . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
        $filepath = $upload_dir . $filename;

        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            $error = "Failed to upload file. Please try again.";
        } else {
            $relative_path = 'uploads/id_cards/' . $filename;

            $stmt = mysqli_prepare($conn, "UPDATE users SET phone = ?, id_card_image = ?, verification_status = 'verified' WHERE user_id = ?");
            mysqli_stmt_bind_param($stmt, "ssi", $phone, $relative_path, $owner_id);

            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['verification_status'] = 'verified';
            } else {
                @unlink($filepath);
                $error = "We could not save your verification. Please try again.";
            }
            mysqli_stmt_close($stmt);
        }
    }
}

if ($error === '') {
    $separator = strpos($redirect, '?') !== false ? '&' : '?';
    header("Location: " . $redirect . $separator . "verified=1");
    exit();
}

flash_set('error', $error);
header("Location: " . $redirect);
exit();
?>
