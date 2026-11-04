<?php
/**
 * Security, CSRF, Password Migration & File Upload Service
 * GYM Management System
 */

// Initialize hardened sessions
function init_secure_session() {
    if (session_status() === PHP_SESSION_NONE) {
        if (!headers_sent()) {
            session_set_cookie_params([
                'lifetime' => 0,
                'path' => '/',
                'domain' => '',
                'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
        }
        session_start();
    }
}

// CSRF Protection Functions
function get_csrf_token() {
    init_secure_session();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_input() {
    $token = get_csrf_token();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}

function verify_csrf_token($token = null) {
    init_secure_session();
    if ($token === null) {
        $token = $_POST['csrf_token'] ?? '';
    }
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

// Zero-Downtime Password Verification & Bcrypt Auto-Rehash
function verify_and_rehash_password($password, $stored_hash, $table, $id_col, $id_val, $dbh) {
    if (empty($password) || empty($stored_hash)) {
        return false;
    }
    // 1. Check if stored hash is a modern password_hash (bcrypt/argon)
    if (password_verify($password, $stored_hash)) {
        // Check if rehash needed
        if (password_needs_rehash($stored_hash, PASSWORD_DEFAULT)) {
            $new_hash = password_hash($password, PASSWORD_DEFAULT);
            $up = $dbh->prepare("UPDATE `$table` SET `password` = :pwd WHERE `$id_col` = :id");
            $up->execute([':pwd' => $new_hash, ':id' => $id_val]);
        }
        return true;
    }

    // 2. Backward compatibility: Check if legacy MD5 matches
    if (hash_equals(md5($password), $stored_hash)) {
        // Upgrade user record immediately to secure bcrypt!
        $new_hash = password_hash($password, PASSWORD_DEFAULT);
        $up = $dbh->prepare("UPDATE `$table` SET `password` = :pwd WHERE `$id_col` = :id");
        $up->execute([':pwd' => $new_hash, ':id' => $id_val]);
        return true;
    }

    return false;
}

function hash_password($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

// Secure Image Upload Helper
function handle_image_upload($file_array, $target_subfolder = 'uploads', $max_size_mb = 5) {
    if (!isset($file_array) || !is_array($file_array) || $file_array['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'No file uploaded or upload error code: ' . ($file_array['error'] ?? 'missing')];
    }

    // Check size
    $max_bytes = $max_size_mb * 1024 * 1024;
    if ($file_array['size'] > $max_bytes) {
        return ['success' => false, 'error' => "File size exceeds limit of {$max_size_mb}MB."];
    }

    // Verify MIME type using finfo
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file_array['tmp_name']);
    $allowed_mimes = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
        'image/svg+xml' => 'svg'
    ];

    if (!array_key_exists($mime, $allowed_mimes)) {
        return ['success' => false, 'error' => 'Invalid file format. Allowed types: JPG, PNG, WEBP, GIF, SVG.'];
    }

    $ext = $allowed_mimes[$mime];
    $filename = bin2hex(random_bytes(16)) . '.' . $ext;

    $base_dir = realpath(__DIR__ . '/../img');
    $target_dir = $base_dir . '/' . trim($target_subfolder, '/');
    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0755, true);
    }

    $dest_path = $target_dir . '/' . $filename;
    if (!move_uploaded_file($file_array['tmp_name'], $dest_path)) {
        return ['success' => false, 'error' => 'Failed to save uploaded file.'];
    }

    // Return relative path for web access e.g. "img/uploads/abc123.jpg"
    $relative_path = 'img/' . trim($target_subfolder, '/') . '/' . $filename;
    return ['success' => true, 'path' => $relative_path, 'filename' => $filename];
}
