<?php
// Start session and include DB
session_start();
require_once __DIR__ . '/config/db.php';


$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim(strtolower($_POST['email'] ?? ''));
    // ...existing code...
    if ($email) {
        $query = "SELECT * FROM users WHERE LOWER(email)='" . mysqli_real_escape_string($conn, $email) . "' LIMIT 1";
    // ...existing code...
        $user = mysqli_fetch_assoc(mysqli_query($conn, $query));
        if ($user) {
            // Generate a secure token
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+5 minutes'));
            // Store token and expiry in DB (add columns if needed)
            mysqli_query($conn, "UPDATE users SET reset_token='$token', reset_expires='$expires' WHERE id=" . $user['id']);
            $reset_link = "http://localhost/BoardingHousePaymentSystem/reset_password.php?token=$token";

            // PHPMailer integration
            require_once __DIR__ . '/vendor/autoload.php';
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            try {
                // Gmail SMTP settings
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                // IMPORTANT: Use a Gmail App Password, not your normal Gmail password.
                // See: https://myaccount.google.com/apppasswords
                $mail->Username   = 'danaojarviee@gmail.com'; // Your Gmail address
                    $mail->Password   = 'zjknoprzazaieoak'; // Gmail App Password
                $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;

                //Recipients
                $mail->setFrom('example@gmail.com', 'Boarding House System');
                $mail->addAddress($user['email'], $user['username']);

                // Content
                $mail->isHTML(true);
                $mail->Subject = 'Password Reset Request';
                $mail->Body    = "Hello <b>{$user['username']}</b>,<br><br>Click the link below to reset your password:<br><a href='$reset_link'>$reset_link</a><br><br>If you did not request this, you can ignore this email.";

                $mail->send();
                $msg = "A password reset link has been sent to your email address.";
            } catch (Exception $e) {
                $msg = "Mailer Error: {$mail->ErrorInfo}<br>For testing, here is your reset link:<br><a href='$reset_link'>$reset_link</a>";
            }
        } else {
            $msg = 'No user found with that email address.';
        }
    } else {
        $msg = 'Please enter your email address.';
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Forgot Password</title>
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
                    <h1 class="h4 form-title mb-1">Forgot Password</h1>
                    <div class="text-muted text-sm mb-3">Enter your email to receive a password reset link.</div>
                    <?php if (!empty($msg)) echo "<div class='alert alert-info py-2 mb-3'>$msg</div>"; ?>
                    <form method="post">
                        <div class="mb-3">
                            <label class="form-label">Email Address</label>
                            <input type="email" class="form-control" name="email" placeholder="Enter your email" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Send Reset Link</button>
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
