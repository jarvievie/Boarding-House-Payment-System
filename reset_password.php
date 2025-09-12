<?php
// Password reset page
session_start();
require_once __DIR__ . '/config/db.php';

$msg = '';
$show_form = false;
$token = $_GET['token'] ?? '';

// ...existing code...

if ($token) {
    $safe_token = mysqli_real_escape_string($conn, $token);
    $user = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM users WHERE reset_token='$safe_token' LIMIT 1"));
    if ($user) {
        // Check expiry
        if (strtotime($user['reset_expires']) > time()) {
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $new_password = $_POST['new_password'] ?? '';
                $confirm_password = $_POST['confirm_password'] ?? '';
                if ($new_password && $confirm_password) {
                    if ($new_password === $confirm_password) {
                        $hash = password_hash($new_password, PASSWORD_DEFAULT);
                        $uid = $user['id'];
                        // Update password, clear token and expiry
                        $sql = "UPDATE users SET password='$hash', reset_token=NULL, reset_expires=NULL WHERE id=$uid";
                        if (mysqli_query($conn, $sql)) {
                            $msg = 'Password has been reset successfully! You can now <a href=\'login.php\'>login</a>.';
                        } else {
                            $msg = 'Error updating password: ' . mysqli_error($conn);
                        }
                    } else {
                        $msg = 'Passwords do not match.';
                    }
                } else {
                    $msg = 'Please fill in both password fields.';
                }
            }
        } else {
            $msg = 'Your reset link has expired. Please request a new one.';
        }
    } else {
        $msg = 'Reset token not found. Please check your link or request a new one.';
    }
} else {
    $msg = 'No reset token provided.';
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Reset Password</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root{ --bh-primary:#0d6efd; --bh-deep:#0b3d5c; --bh-teal:#1f8a70; --bh-sand:#f4e9d8; --bh-coral:#ff6f59; --bh-sky:#e6f4ff; }
        body { background: var(--bh-sky); font-family:'Poppins', system-ui, -apple-system, Segoe UI, Roboto, "Helvetica Neue", Arial; min-height:100vh; display:flex; align-items:center; }
        .auth-card { border:0; border-radius:16px; overflow:hidden; box-shadow:0 10px 30px rgba(0,0,0,.08); }
        .form-area { padding:28px; }
        .form-title { font-weight:600; }
        .text-sm { font-size:.9rem; }
    </style>
</head>
<body>
<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-7 col-xl-6">
            <div class="card auth-card">
                <div class="form-area">
                    <h1 class="h4 form-title mb-1">Reset Password</h1>
                    <div class="text-muted text-sm mb-3">Set your new password below.</div>
                    <?php if (!empty($msg)) echo "<div class='alert alert-info py-2 mb-3'>$msg</div>"; ?>
                    <form method="post">
                        <div class="mb-3">
                            <label class="form-label">New Password</label>
                            <input type="password" class="form-control" name="new_password" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Confirm New Password</label>
                            <input type="password" class="form-control" name="confirm_password" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Reset Password</button>
                    </form>
                    <div class="mt-3 text-center">
                        <a href="login.php">Back to Login</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
