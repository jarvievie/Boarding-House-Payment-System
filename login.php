<?php

session_start();
// Prevent caching of this page
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// If already logged in, redirect to appropriate dashboard
if (isset($_SESSION['admin'])) {
    header("Location: admin/dashboard.php");
    exit();
}
if (isset($_SESSION['tenant'])) {
    header("Location: tenant/dashboard.php");
    exit();
}

include 'config/db.php';

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    // Fetch user by username from unified users table
    $stmt = mysqli_prepare($conn, "SELECT id, username, password, role FROM users WHERE username = ? LIMIT 1");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 's', $username);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if ($result && mysqli_num_rows($result) === 1) {
            $account = mysqli_fetch_assoc($result);
            $isValid = false;

            if ($account['role'] === 'admin') {
                // Admin passwords now use password_hash (bcrypt)
                $isValid = password_verify($password, $account['password']);
                if ($isValid) {
                    $_SESSION['admin'] = $account['username'];
                    echo '<script>location.replace("admin/dashboard.php");</script>';
                    exit();
                }
            } elseif ($account['role'] === 'tenant') {
                // Tenants use password_hash (bcrypt)
                $isValid = password_verify($password, $account['password']);
                if ($isValid) {
                    $_SESSION['tenant'] = $account['username'];
                    // Map tenant_id to users.id (kept consistent in schema)
                    $_SESSION['tenant_id'] = (int)$account['id'];
                    echo '<script>location.replace("tenant/dashboard.php");</script>';
                    exit();
                }
            }
        }
        mysqli_stmt_close($stmt);
    }

    // Invalid login fallback
    $error = "Invalid username or password.";
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Boarding House Login</title>
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
                                <div class="text-sm">Welcome back! Please sign in to continue.</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-area">
                            <h1 class="h4 form-title mb-1">Sign in</h1>
                            <div class="text-muted text-sm mb-3">Use your admin or tenant credentials.</div>
                            <?php if (!empty($error)) echo "<div class='alert alert-danger py-2 mb-3'>$error</div>"; ?>
                            <form method="POST">
                                <div class="mb-3">
                                    <label class="form-label">Username</label>
                                    <input type="text" class="form-control" name="username" placeholder="Enter username" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Password</label>
                                    <input type="password" class="form-control" name="password" placeholder="Enter password" required>
                                </div>
                                <button type="submit" class="btn btn-primary w-100">Login</button>
                            </form>
                            <div class="mt-3 text-center">
                                <a href="forgot_password.php">Forgot Password?</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Simple back button prevention for login - no auto-redirect
(function() {
    // Clear history and add current page
    window.history.replaceState(null, null, window.location.href);
    window.history.pushState({page: 'login'}, '', window.location.href);
    
    // Handle back button - just stay on login
    window.addEventListener('popstate', function(event) {
        // Always stay on login page
        window.history.pushState({page: 'login'}, '', window.location.href);
    });
    
    // Handle page load/reload
    window.addEventListener('load', function() {
        // Re-establish history on every load
        window.history.replaceState(null, null, window.location.href);
        window.history.pushState({page: 'login'}, '', window.location.href);
    });
    
    // Handle page show from cache
    window.addEventListener('pageshow', function(event) {
        // Re-establish history on every page show
        window.history.replaceState(null, null, window.location.href);
        window.history.pushState({page: 'login'}, '', window.location.href);
    });
})();
</script>
</body>
</html>

