<?php
session_start();

// Prevent caching of this page
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

if (!isset($_SESSION['tenant'])) {
    header("Location: ../login.php");
    exit();
}

include '../config/db.php';

$tenant_id = $_SESSION['tenant_id'];

// Fetch tenant info
$tenant = mysqli_fetch_assoc(mysqli_query(
    $conn,
    "SELECT a.username, t.contact, t.start_date, t.rent_amount
     FROM tenants t
    JOIN users a ON t.tenant_id = a.id
     WHERE t.tenant_id = '$tenant_id'"
));

// Update (when tenant submits edit form)
if (isset($_POST['save_profile'])) {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $contact  = mysqli_real_escape_string($conn, $_POST['contact']);

    // Update contact in tenants
    mysqli_query($conn, "UPDATE tenants SET contact='$contact' WHERE tenant_id='$tenant_id'");

    // Update username in users
    mysqli_query($conn, "UPDATE users SET username='$username' WHERE id='$tenant_id'");


    if (!empty($_POST['password'])) {
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    mysqli_query($conn, "UPDATE users SET password='$password' WHERE id='$tenant_id'");
    }

    // Also update session username (displayed on top)
    $_SESSION['tenant'] = $username;
}

// Enhanced payment logic and notification system
$start_day = (new DateTime($tenant['start_date']))->format('d');
$rent_amount = floatval($tenant['rent_amount']);
$payments = mysqli_query($conn, "SELECT * FROM payments WHERE tenant_id = '$tenant_id' ORDER BY month_paid ASC");

?>
<script>
// Prevent navigating back to login page from dashboard
(function() {
    window.history.pushState({page: 'dashboard'}, '', window.location.href);
    window.addEventListener('popstate', function(event) {
        window.history.pushState({page: 'dashboard'}, '', window.location.href);
    });
})();
</script>
<?php
$today = new DateTime();
$current_month = $today->format('Y-m');
$next_month = $today->modify('first day of next month')->format('Y-m');
$next_rent_date = DateTime::createFromFormat('Y-m-d', $next_month . '-' . $start_day);

// Get current and next month payments

$current_payment = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(amount) as total FROM payments WHERE tenant_id='$tenant_id' AND month_paid='$current_month'"));
$next_payment = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(amount) as total FROM payments WHERE tenant_id='$tenant_id' AND month_paid='$next_month'"));

// Calculate notification data - for unpaid/partial payments and near due dates
$notifications = [];
$notification_count = 0;

// Check current month payment status
// For current month, the due date is the same day next month
$current_due_date = DateTime::createFromFormat('Y-m-d', $next_month . '-' . $start_day);
$is_overdue = $today > $current_due_date; // Check if payment is overdue

if ($is_overdue) {
    $days_overdue = $current_due_date->diff($today)->days; // Days since due date
    $days_until_due = 0; // Not applicable when overdue
} else {
    $days_until_due = $today->diff($current_due_date)->days; // Days until due date
    $days_overdue = 0; // Not applicable when not overdue
}
$is_due_soon = !$is_overdue && $days_until_due <= 3; // Notify if due within 3 days

if ($current_payment) {
    $current_amount = isset($current_payment['total']) ? floatval($current_payment['total']) : 0;
    if ($current_amount == 0) {
        // Unpaid - always notify regardless of due date
        if ($is_overdue) {
            $notifications[] = [
                'type' => 'overdue',
                'title' => 'Payment Overdue',
                'message' => 'Your rent payment for ' . DateTime::createFromFormat('Y-m', $current_month)->format('F Y') . ' is overdue by ' . $days_overdue . ' day(s).',
                'amount' => $rent_amount,
                'due_date' => $current_due_date->format('M d, Y'),
                'priority' => 'urgent'
            ];
        } elseif ($is_due_soon) {
            $notifications[] = [
                'type' => 'overdue',
                'title' => 'Payment Due Soon',
                'message' => 'Your rent payment for ' . DateTime::createFromFormat('Y-m', $current_month)->format('F Y') . ' is due in ' . $days_until_due . ' day(s).',
                'amount' => $rent_amount,
                'due_date' => $current_due_date->format('M d, Y'),
                'priority' => 'high'
            ];
        } else {
            $notifications[] = [
                'type' => 'overdue',
                'title' => 'Payment Pending',
                'message' => 'Your rent payment for ' . DateTime::createFromFormat('Y-m', $current_month)->format('F Y') . ' is pending.',
                'amount' => $rent_amount,
                'due_date' => $current_due_date->format('M d, Y'),
                'priority' => 'medium'
            ];
        }
        $notification_count++;
    } elseif ($current_amount < $rent_amount) {
        // Partial payment - always notify regardless of due date
        $remaining = $rent_amount - $current_amount;
        if ($is_overdue) {
            $notifications[] = [
                'type' => 'partial',
                'title' => 'Partial Payment - Balance Overdue',
                'message' => 'You have a remaining balance of ₱' . number_format($remaining, 2) . ' for ' . DateTime::createFromFormat('Y-m', $current_month)->format('F Y') . ' that is overdue by ' . $days_overdue . ' day(s).',
                'amount' => $remaining,
                'due_date' => $current_due_date->format('M d, Y'),
                'priority' => 'urgent'
            ];
        } elseif ($is_due_soon) {
            $notifications[] = [
                'type' => 'partial',
                'title' => 'Partial Payment - Balance Due Soon',
                'message' => 'You have a remaining balance of ₱' . number_format($remaining, 2) . ' for ' . DateTime::createFromFormat('Y-m', $current_month)->format('F Y') . ' due in ' . $days_until_due . ' day(s).',
                'amount' => $remaining,
                'due_date' => $current_due_date->format('M d, Y'),
                'priority' => 'high'
            ];
        } else {
            $notifications[] = [
                'type' => 'partial',
                'title' => 'Partial Payment - Balance Pending',
                'message' => 'You have a remaining balance of ₱' . number_format($remaining, 2) . ' for ' . DateTime::createFromFormat('Y-m', $current_month)->format('F Y') . '.',
                'amount' => $remaining,
                'due_date' => $current_due_date->format('M d, Y'),
                'priority' => 'medium'
            ];
        }
        $notification_count++;
    }
} else {
    // No payment record - always notify regardless of due date
    if ($is_overdue) {
        $notifications[] = [
            'type' => 'overdue',
            'title' => 'Payment Overdue',
            'message' => 'Your rent payment for ' . DateTime::createFromFormat('Y-m', $current_month)->format('F Y') . ' is overdue by ' . $days_overdue . ' day(s).',
            'amount' => $rent_amount,
            'due_date' => $current_due_date->format('M d, Y'),
            'priority' => 'urgent'
        ];
    } elseif ($is_due_soon) {
        $notifications[] = [
            'type' => 'overdue',
            'title' => 'Payment Due Soon',
            'message' => 'Your rent payment for ' . DateTime::createFromFormat('Y-m', $current_month)->format('F Y') . ' is due in ' . $days_until_due . ' day(s).',
            'amount' => $rent_amount,
            'due_date' => $current_due_date->format('M d, Y'),
            'priority' => 'high'
        ];
    } else {
        $notifications[] = [
            'type' => 'overdue',
            'title' => 'Payment Pending',
            'message' => 'Your rent payment for ' . DateTime::createFromFormat('Y-m', $current_month)->format('F Y') . ' is pending.',
            'amount' => $rent_amount,
            'due_date' => $current_due_date->format('M d, Y'),
            'priority' => 'medium'
        ];
    }
    $notification_count++;
}

// Check next month payment - only if current month is fully paid and next month is due soon
$next_due_date = DateTime::createFromFormat('Y-m-d', $next_month . '-' . $start_day);
$next_month_after = (new DateTime($next_month . '-01'))->modify('+1 month')->format('Y-m');
$next_next_due_date = DateTime::createFromFormat('Y-m-d', $next_month_after . '-' . $start_day);
$days_until_next_due = $today->diff($next_next_due_date)->days;
$is_next_due_soon = $days_until_next_due <= 5; // Notify if next month is due within 5 days

// Only check next month if current month is fully paid
$current_is_fully_paid = $current_payment && (isset($current_payment['total']) ? floatval($current_payment['total']) : 0) >= $rent_amount;
if ($current_is_fully_paid && $is_next_due_soon) {
    if (!$next_payment || $next_payment['amount'] < $rent_amount) {
        $notifications[] = [
            'type' => 'reminder',
            'title' => 'Upcoming Payment Due',
            'message' => 'Your rent payment for ' . DateTime::createFromFormat('Y-m', $next_month_after)->format('F Y') . ' is due in ' . $days_until_next_due . ' day(s).',
            'amount' => $rent_amount,
            'due_date' => $next_next_due_date->format('M d, Y'),
            'priority' => 'low'
        ];
        $notification_count++;
    }
}

$show_reminder = $notification_count > 0;
?>
<!DOCTYPE html>
<html>
<head>
    <title>Tenant Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root{
            --bh-primary:#0d6efd; --bh-deep:#0b3d5c; --bh-teal:#1f8a70; --bh-sand:#f4e9d8; --bh-coral:#ff6f59; --bh-sky:#e6f4ff;
        }
        body { background-color: var(--bh-sky); font-family: 'Poppins', system-ui, -apple-system, Segoe UI, Roboto, "Helvetica Neue", Arial; }
        .navbar{ background: linear-gradient(90deg, var(--bh-deep), var(--bh-teal)); position: sticky; top: 0; z-index: 1030; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .navbar .nav-link, .navbar .navbar-brand{ color:#fff; }
        .navbar .nav-link:hover{ color:#f8f9fa; opacity:.9; }
        .hero{ position:relative; background:url('../Hero-Boarding.jpg') center/cover no-repeat; color:#fff; border-radius:12px; overflow:hidden; }
        .hero::after{ content:""; position:absolute; inset:0; background:linear-gradient(180deg, rgba(11,61,92,.6), rgba(31,138,112,.45)); }
        .hero-content{ position:relative; z-index:1; padding:36px 20px; }
        .notification{position:relative;display:inline-block}
        .notification .dot{position:absolute;top:-5px;right:-5px;height:12px;width:12px;background:#dc3545;border-radius:50%}
        .notification .dot.urgent{background:#dc2626;animation:pulse 2s infinite}
        .notification .dot.high{background:#dc3545}
        .notification .dot.medium{background:#fd7e14}
        .notification .dot.low{background:#17a2b8}
        @keyframes pulse{0%{opacity:1}50%{opacity:0.5}100%{opacity:1}}
    .status-badge{font-weight:600;padding:0.35em 0.7em;border-radius:50rem}
    .badge-paid { background: #28a745 !important; color: #fff !important; }
    .badge-partial { background: #fd7e14 !important; color: #fff !important; }
    .badge-unpaid { background: #dc3545 !important; color: #fff !important; }
        .notification-card{border-radius:12px;box-shadow:0 4px 12px rgba(0,0,0,0.1);margin-bottom:1rem;overflow:hidden}
        .notification-card.urgent{border-left:4px solid #dc2626;background:linear-gradient(135deg,#fef2f2,#fee2e2)}
        .notification-card.high{border-left:4px solid #dc3545;background:linear-gradient(135deg,#fff5f5,#ffe6e6)}
        .notification-card.medium{border-left:4px solid #fd7e14;background:linear-gradient(135deg,#fff8f0,#ffe4cc)}
        .notification-card.low{border-left:4px solid #17a2b8;background:linear-gradient(135deg,#f0f9ff,#e0f2fe)}
        .notification-header{display:flex;justify-content:space-between;align-items:center;padding:1rem;border-bottom:1px solid rgba(0,0,0,0.1)}
        .notification-body{padding:1rem}
        .notification-amount{font-size:1.25rem;font-weight:700;color:#1f2937}
        .notification-due{color:#6b7280;font-size:0.9rem}
        .notification-icon{width:40px;height:40px;border-radius:50%;display:flex;align-items:center;justify-content:center;color:#fff;font-size:1.2rem}
        .notification-icon.urgent{background:#dc2626}
        .notification-icon.high{background:#dc3545}
        .notification-icon.medium{background:#fd7e14}
        .notification-icon.low{background:#17a2b8}
        .table thead{ background:#0f2d44; color:#fff; }
        .table thead th{ text-transform:uppercase; letter-spacing:.02em; font-size:.8rem; }
        .table th, .table td{vertical-align:middle;text-align:center}
        .table-card{ border-radius:12px; overflow:hidden; box-shadow:0 6px 20px rgba(0,0,0,.06); }
        .footer-note{ color:#6c757d; font-size:.9rem; }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark">
    <div class="container">
        <a class="navbar-brand fw-semibold" href="dashboard.php"><i class="bi bi-house-heart me-2"></i>Tenant Portal</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarsTenant"><span class="navbar-toggler-icon"></span></button>
        <div class="collapse navbar-collapse" id="navbarsTenant">
            <ul class="navbar-nav ms-auto">
                <!-- <li class="nav-item"><a class="nav-link active" href="dashboard.php"><i class="bi bi-speedometer2 me-1"></i>Dashboard</a></li> -->
                <li class="nav-item ms-lg-2"><a class="btn btn-sm btn-light" href="../logout.php"><i class="bi bi-box-arrow-right me-1"></i>Logout</a></li>
            </ul>
        </div>
    </div>
</nav>

<div class="container mt-4">
    <div class="hero mb-4">
        <div class="hero-content d-flex flex-column flex-md-row align-items-md-center justify-content-between">
            <div class="mb-2 mb-md-0">
                <h1 class="h4 fw-semibold mb-1">Welcome, <?php echo htmlspecialchars($_SESSION['tenant']); ?></h1>
                <div class="small">View your payments and manage your profile.</div>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#editProfileModal"><i class="bi bi-person-gear me-1"></i>Edit Profile</button>
                <button class="btn btn-warning btn-sm notification" data-bs-toggle="modal" data-bs-target="#notificationsModal">
                    <i class="bi bi-bell me-1"></i>Notifications
                    <?php if ($show_reminder): ?>
                        <span class="dot <?php echo $notifications[0]['priority']; ?>"><?php echo $notification_count; ?></span>
                    <?php endif; ?>
                </button>
            </div>
        </div>
    </div>

    <?php if ($show_reminder): ?>
        <div class="row mb-4">
            <div class="col-12">
                <h5 class="mb-3"><i class="bi bi-bell-fill me-2"></i>Important Notifications</h5>
                <?php foreach ($notifications as $notification): ?>
                    <div class="notification-card <?php echo $notification['priority']; ?>">
                        <div class="notification-header">
                            <div class="d-flex align-items-center">
                                <div class="notification-icon <?php echo $notification['priority']; ?> me-3">
                                    <?php 
                                    switch($notification['type']) {
                                        case 'overdue': echo '<i class="bi bi-exclamation-triangle-fill"></i>'; break;
                                        case 'partial': echo '<i class="bi bi-clock-fill"></i>'; break;
                                        case 'reminder': echo '<i class="bi bi-calendar-check"></i>'; break;
                                        case 'warning': echo '<i class="bi bi-shield-exclamation"></i>'; break;
                                        default: echo '<i class="bi bi-info-circle-fill"></i>';
                                    }
                                    ?>
                                </div>
                                <div>
                                    <h6 class="mb-1 fw-semibold"><?php echo $notification['title']; ?></h6>
                                    <p class="mb-0 text-muted"><?php echo $notification['message']; ?></p>
                                </div>
                            </div>
                            <div class="text-end">
                                <div class="notification-amount">₱<?php echo number_format($notification['amount'], 2); ?></div>
                                <div class="notification-due">Due: <?php echo $notification['due_date']; ?></div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <h4 id="payment-history">Your Payment History</h4>
    <div class="table-responsive table-card">
        <table class="table table-bordered table-striped table-hover table-sm align-middle mb-0">
            <thead>
                <tr><th>Month</th><th>Day Paid</th><th>Amount</th><th>Status</th><th>Received By</th></tr>
            </thead>
            <tbody>
                <?php while ($row = mysqli_fetch_assoc($payments)) {
                    $amount = floatval($row['amount']);
                    $rent = $rent_amount;
                    if ($amount >= $rent) {
                        $status = 'Paid';
                    } elseif ($amount > 0) {
                        $status = 'Partial';
                    } else {
                        $status = 'Unpaid';
                    }
                    $display_amount = ($status === 'Paid') ? number_format($amount, 2) : (($status === 'Partial') ? number_format($amount, 2) . ' / ' . number_format($rent, 2) : '0.00 / ' . number_format($rent, 2));
                    $month_display = DateTime::createFromFormat('Y-m', $row['month_paid'])->format('F Y');
                    $day_display   = $row['date_paid'] ? (new DateTime($row['date_paid']))->format('d F Y') : '-';
                    $receiver = !empty($row['received_by']) ? htmlspecialchars($row['received_by']) : 'admin';
                ?>
                <tr>
                    <td><?php echo $month_display; ?></td>
                    <td><?php echo $day_display; ?></td>
                    <td>₱<?php echo $display_amount; ?></td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge badge-<?php echo strtolower($status); ?> payment-status" style="font-size:0.85em; padding:4px 10px; min-width:70px; text-align:center;">
                                <?php echo $status; ?>
                            </span>
                            <div class="small text-muted">
                                <?php if($status === 'Partial'): ?>
                                    ₱<?php echo number_format($amount, 2); ?> / ₱<?php echo number_format($rent, 2); ?>
                                <?php elseif($status === 'Paid'): ?>
                                    Complete
                                <?php else: ?>
                                    ₱0.00 / ₱<?php echo number_format($rent, 2); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>
                    <td><?php echo $receiver; ?></td>
                </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
<div class="container mt-4 mb-5">
    <p class="footer-note text-center">Keeping your stay comfortable and payments on time.</p>
</div>

<!-- Notifications Modal -->
<div class="modal fade" id="notificationsModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-bell-fill me-2"></i>All Notifications</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <?php if (empty($notifications)): ?>
          <div class="text-center py-5">
            <i class="bi bi-check-circle-fill text-success" style="font-size: 3rem;"></i>
            <h5 class="mt-3 text-muted">All caught up!</h5>
            <p class="text-muted">You have no pending notifications at this time.</p>
          </div>
        <?php else: ?>
          <?php foreach ($notifications as $index => $notification): ?>
            <div class="notification-card <?php echo $notification['priority']; ?> mb-3">
              <div class="notification-header">
                <div class="d-flex align-items-center">
                  <div class="notification-icon <?php echo $notification['priority']; ?> me-3">
                    <?php 
                    switch($notification['type']) {
                      case 'overdue': echo '<i class="bi bi-exclamation-triangle-fill"></i>'; break;
                      case 'partial': echo '<i class="bi bi-clock-fill"></i>'; break;
                      case 'reminder': echo '<i class="bi bi-calendar-check"></i>'; break;
                      case 'warning': echo '<i class="bi bi-shield-exclamation"></i>'; break;
                      default: echo '<i class="bi bi-info-circle-fill"></i>';
                    }
                    ?>
                  </div>
                  <div class="flex-grow-1">
                    <h6 class="mb-1 fw-semibold"><?php echo $notification['title']; ?></h6>
                    <p class="mb-1 text-muted"><?php echo $notification['message']; ?></p>
                    <small class="text-muted">
                      <i class="bi bi-calendar-event me-1"></i>
                      Due: <?php echo $notification['due_date']; ?>
                    </small>
                  </div>
                </div>
                <div class="text-end">
                  <div class="notification-amount">₱<?php echo number_format($notification['amount'], 2); ?></div>
                  <small class="badge bg-<?php 
                    switch($notification['priority']) {
                      case 'urgent': echo 'danger'; break;
                      case 'high': echo 'warning'; break;
                      case 'medium': echo 'info'; break;
                      case 'low': echo 'secondary'; break;
                      default: echo 'secondary';
                    }
                  ?>"><?php echo ucfirst($notification['priority']); ?> Priority</small>
                </div>
              </div>
              <?php if ($notification['type'] == 'warning' || $notification['priority'] == 'urgent'): ?>
                <div class="notification-body bg-light border-top">
                  <div class="d-flex align-items-center">
                    <i class="bi bi-telephone-fill text-primary me-2"></i>
                    <div>
                      <strong>Need immediate assistance?</strong><br>
                      <small class="text-muted">Contact management at your earliest convenience to resolve this matter.</small>
                    </div>
                  </div>
                </div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- Edit Profile Modal -->
<div class="modal fade" id="editProfileModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST">
        <div class="modal-header">
          <h5 class="modal-title">Edit Profile</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label>Username</label>
            <input type="text" name="username" class="form-control"
                   value="<?php echo htmlspecialchars($tenant['username']); ?>" required>
          </div>
          <div class="mb-3">
            <label>Mobile Number</label>
            <input type="text" name="contact" class="form-control"
                   value="<?php echo htmlspecialchars($tenant['contact']); ?>"
                   maxlength="11"
                   pattern="09\d{9}"
                   title="Mobile number must start with 09 and be 11 digits"
                   oninput="this.value = '09' + this.value.replace(/[^0-9]/g, '').slice(2, 11)"
                   required>
          </div>
          <div class="mb-3">
            <label>New Password (leave blank if no change)</label>
            <input type="password" name="password" class="form-control">
          </div>
        </div>
        <div class="modal-footer">
          <button type="submit" name="save_profile" class="btn btn-success">Save Changes</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Ultra-strong back button prevention for tenant dashboard
(function() {
    // Completely disable back button functionality
    window.history.replaceState(null, null, window.location.href);
    
    // Add many entries to create an ultra-strong buffer
    for (let i = 1; i <= 10; i++) {
        window.history.pushState({page: 'tenant' + i}, '', window.location.href);
    }
    
    // Handle back button - completely block it with aggressive push
    window.addEventListener('popstate', function(event) {
        // Immediately push 10 dashboard entries back
        for (let i = 1; i <= 10; i++) {
            window.history.pushState({page: 'tenant' + i}, '', window.location.href);
        }
    });
    
    // Handle page load/reload
    window.addEventListener('load', function() {
        // Re-establish ultra-strong history buffer on every load
        window.history.replaceState(null, null, window.location.href);
        for (let i = 1; i <= 10; i++) {
            window.history.pushState({page: 'tenant' + i}, '', window.location.href);
        }
    });
    
    // Handle page show from cache
    window.addEventListener('pageshow', function(event) {
        // Re-establish ultra-strong history buffer on every page show
        window.history.replaceState(null, null, window.location.href);
        for (let i = 1; i <= 10; i++) {
            window.history.pushState({page: 'tenant' + i}, '', window.location.href);
        }
    });
    
    // Ultra-frequent protection - check URL and force redirect
    setInterval(function() {
        if (window.location.href.includes('login.php')) {
            // If somehow on login page, force redirect back to dashboard
            window.location.replace('dashboard.php');
        }
    }, 100);
})();
</script>
</body>
</html>
