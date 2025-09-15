<?php
// Simple admin reset script. DELETE THIS FILE AFTER USE!

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old_password = $_POST['old_password'] ?? '';
    $new_username = $_POST['username'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    if ($old_password && $new_username && $new_password && $confirm_password) {
        require_once __DIR__ . '/config/db.php';
        // Fetch current admin
        $admin = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM users WHERE role='admin' LIMIT 1"));
        if ($admin && password_verify($old_password, $admin['password'])) {
            if ($new_password === $confirm_password) {
                $hash = password_hash($new_password, PASSWORD_DEFAULT);
                $sql = "UPDATE users SET username=?, password=? WHERE role='admin' LIMIT 1";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, 'ss', $new_username, $hash);
                if (mysqli_stmt_execute($stmt)) {
                    $msg = 'Admin credentials updated! Please delete this file now.';
                } else {
                    $msg = 'Error updating admin: ' . mysqli_error($conn);
                }//comment
                mysqli_stmt_close($stmt);
            } else {
                $msg = 'New password and confirm password do not match.';
            }
        } else {
            $msg = 'Old password is incorrect.';
        }
    } else {
        $msg = 'Please fill in all fields.';
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Reset Admin Credentials</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root{ --bh-primary:#0d6efd; --bh-deep:#0b3d5c; --bh-teal:#1f8a70; --bh-sand:#f4e9d8; --bh-coral:#ff6f59; --bh-sky:#e6f4ff; }
        body { background: var(--bh-sky); font-family:'Poppins', system-ui, -apple-system, Segoe UI, Roboto, "Helvetica Neue", Arial; min-height:100vh; display:flex; align-items:center; }
        .auth-card { border:0; border-radius:16px; overflow:hidden; box-shadow:0 10px 30px rgba(0,0,0,.08); }
        .auth-hero { position:relative; min-height:420px; background:url('Hero-Boarding.jpg') center/cover no-repeat; }
        .auth-hero::after { content:""; position:absolute; inset:0; background:linear-gradient(180deg, rgba(11,61,92,.6), rgba(31,138,112,.45)); }
        .auth-hero-inner { position:relative; z-index:1; color:#fff; padding:32px; display:flex; height:100%; flex-direction:column; justify-content:flex-end; }
        .brand { display:flex; align-items:center; gap:.5rem; font-weight:600; color:#fff; }
        .brand i { font-size:1.25rem; }
        .form-area { padding:28px; }
        .form-title { font-weight:600; }
        .text-sm { font-size:.9rem; }
        .error-msg { color:#dc3545; }
    </style>
</head>
<body>
<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-9 col-xl-8">
            <div class="card auth-card">
                <div class="row g-0">
                    <div class="col-md-6 d-none d-md-block">
                        <div class="auth-hero">
                            <div class="auth-hero-inner">
                                <div class="brand mb-2"><i class="bi bi-houses"></i> Boarding House</div>
                                <div class="text-sm">Reset your admin credentials below.</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-area">
                            <h1 class="h4 form-title mb-1">Reset Admin</h1>
                            <div class="text-muted text-sm mb-3">Set a new admin username and password.</div>
                            <?php if (!empty($msg)) echo "<div class='alert alert-info py-2 mb-3'>$msg</div>"; ?>
                            <form method="post">
                                <div class="mb-3">
                                    <label class="form-label">Old Admin Password</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" name="old_password" id="old_password" placeholder="Enter old admin password" required>
                                        <button type="button" class="btn btn-outline-secondary" tabindex="-1" onclick="togglePassword('old_password', this)"><i class="bi bi-eye"></i></button>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">New Admin Username</label>
                                    <input type="text" class="form-control" name="username" placeholder="Enter new admin username" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">New Admin Password</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" name="new_password" id="new_password" placeholder="Enter new admin password" required>
                                        <button type="button" class="btn btn-outline-secondary" tabindex="-1" onclick="togglePassword('new_password', this)"><i class="bi bi-eye"></i></button>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Confirm New Password</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" name="confirm_password" id="confirm_password" placeholder="Confirm new admin password" required>
                                        <button type="button" class="btn btn-outline-secondary" tabindex="-1" onclick="togglePassword('confirm_password', this)"><i class="bi bi-eye"></i></button>
                                    </div>
                                </div>
</div>
<script>
function togglePassword(fieldId, btn) {
    const input = document.getElementById(fieldId);
    const icon = btn.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('bi-eye');
        icon.classList.add('bi-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('bi-eye-slash');
        icon.classList.add('bi-eye');
    }
}
</script>
                                <button type="submit" class="btn btn-primary w-100">Reset Admin</button>
                            </form>
                            <p class="text-danger mt-3">Delete this file after use for security!</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
