<?php
// Admin profile update page with OTP verification
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: ../login.php");
    exit();
}
require_once '../config/db.php';
$msg = '';
$step = $_POST['step'] ?? 'form';

// Generate OTP and send email
if ($step === 'send_otp' && isset($_POST['gmail'])) {
    $otp = rand(100000, 999999);
    $_SESSION['admin_otp'] = $otp;
    $_SESSION['admin_profile'] = [
        'gmail' => $_POST['gmail'],
        'username' => $_POST['username'],
        'password' => $_POST['password'],
    ];
    // Send OTP to new Gmail
    require_once '../vendor/autoload.php';
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'danaojarviee@gmail.com';
        $mail->Password = 'zjknoprzazaieoak';
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        $mail->setFrom('example@gmail.com', 'Boarding House System');
        $mail->addAddress($_POST['gmail']);
        $mail->Subject = 'OTP for Admin Profile Update';
        $mail->Body = "Your OTP code is: $otp";
        $mail->send();
        $msg = 'OTP sent to your new Gmail address.';
        $step = 'verify_otp';
    } catch (Exception $e) {
        $msg = 'Error sending OTP: ' . $mail->ErrorInfo;
    }
}
// Verify OTP and update profile
if ($step === 'verify_otp' && isset($_POST['otp'])) {
    if ($_POST['otp'] == ($_SESSION['admin_otp'] ?? '')) {
        $profile = $_SESSION['admin_profile'];
        $hash = password_hash($profile['password'], PASSWORD_DEFAULT);
        $sql = "UPDATE users SET email=?, username=?, password=? WHERE role='admin' LIMIT 1";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'sss', $profile['gmail'], $profile['username'], $hash);
        if (mysqli_stmt_execute($stmt)) {
            unset($_SESSION['admin_otp'], $_SESSION['admin_profile']);
            header('Location: dashboard.php?profile_updated=1');
            exit();
        } else {
            $msg = 'Error updating profile: ' . mysqli_error($conn);
        }
        mysqli_stmt_close($stmt);
    } else {
        $msg = 'Invalid OTP.';
        $step = 'verify_otp';
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Update Admin Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: #e6f4ff; font-family: 'Poppins', Arial, sans-serif; }
        .card { border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,.08); }
        .form-title { font-weight: 600; }
    </style>
</head>
<body>
<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-6">
            <div class="card p-4">
                <h1 class="h4 form-title mb-3"><i class="bi bi-person-gear me-2"></i>Update Profile</h1>
                <?php if (!empty($msg)) echo "<div class='alert alert-info py-2 mb-3'>$msg</div>"; ?>
                <?php if ($step === 'form'): ?>
                <form method="post">
                    <input type="hidden" name="step" value="send_otp">
                    <div class="mb-3">
                        <label class="form-label">New Gmail</label>
                        <input type="email" class="form-control" name="gmail" required placeholder="Enter new Gmail">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">New Username</label>
                        <input type="text" class="form-control" name="username" required placeholder="Enter new username">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">New Password</label>
                        <div class="input-group">
                            <input type="password" class="form-control" name="password" id="profile_password" required placeholder="Enter new password">
                            <button type="button" class="btn btn-outline-secondary" tabindex="-1" onclick="togglePassword('profile_password', this)"><i class="bi bi-eye"></i></button>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Confirm New Password</label>
                        <div class="input-group">
                            <input type="password" class="form-control" name="confirm_password" id="profile_confirm_password" required placeholder="Confirm new password">
                            <button type="button" class="btn btn-outline-secondary" tabindex="-1" onclick="togglePassword('profile_confirm_password', this)"><i class="bi bi-eye"></i></button>
                        </div>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-send-check me-1"></i>Send OTP & Update</button>
                        <a href="dashboard.php" class="btn btn-secondary w-100"><i class="bi bi-x-circle me-1"></i>Cancel</a>
                    </div>
                </form>
                <?php elseif ($step === 'verify_otp'): ?>
                <form method="post" onsubmit="return validateProfilePasswords()">
                    <input type="hidden" name="step" value="verify_otp">
                    <div class="mb-3">
                        <label class="form-label">Enter OTP</label>
                        <input type="text" class="form-control" name="otp" required placeholder="Enter OTP from Gmail">
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-success w-100"><i class="bi bi-shield-check me-1"></i>Verify & Update</button>
                        <a href="dashboard.php" class="btn btn-secondary w-100"><i class="bi bi-x-circle me-1"></i>Cancel</a>
                    </div>
                </form>
                <?php endif; ?>
            </div>
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
function validateProfilePasswords() {
    var pw = document.getElementById('profile_password').value;
    var cpw = document.getElementById('profile_confirm_password').value;
    if (pw !== cpw) {
        alert('New password and confirm password do not match.');
        return false;
    }
    return true;
}
</script>
</body>
</html>
