<?php
session_start();

// Prevent caching of this page
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

if (!isset($_SESSION['admin'])) { header("Location: ../login.php"); exit(); }
include '../config/db.php';

/* Record Payment */
if(isset($_POST['record_payment'])) {
  $tenant_id = intval($_POST['tenant_id']);
  $amount    = floatval($_POST['amount']);
  $month     = $_POST['month'];
  $received_by = mysqli_real_escape_string($conn, $_POST['received_by'] ?? '');
  $rent      = mysqli_fetch_assoc(mysqli_query($conn,"SELECT rent_amount FROM tenants WHERE tenant_id=$tenant_id"))['rent_amount'];

  // Always insert a new payment record for each transaction
  mysqli_query($conn, "INSERT INTO payments (tenant_id, month_paid, amount, payment_method, status, remarks, date_paid, received_by) VALUES ($tenant_id, '$month', $amount, '', 'Pending', NULL, NOW(), '$received_by')");

  // Update the status to 'Paid' if total payments for the month reach rent_amount
  $total_paid = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(amount) as total FROM payments WHERE tenant_id=$tenant_id AND month_paid='$month'"))['total'];
  if($total_paid >= $rent) {
    mysqli_query($conn, "UPDATE payments SET status='Paid' WHERE tenant_id=$tenant_id AND month_paid='$month'");
  }
}

/* Modify months: add or delete */
if(isset($_POST['modify_months'])){
  $tid = intval($_POST['tenant_id']);
  $n   = max(0, intval($_POST['months_to_modify']));
  $action = $_POST['month_action'] ?? 'add';

  if($n > 0 && $action === 'add'){
    $last = mysqli_fetch_assoc(mysqli_query($conn,"SELECT month_paid FROM payments WHERE tenant_id=$tid ORDER BY month_paid DESC LIMIT 1"));
    $baseMonth = $last ? $last['month_paid'] : date('Y-m');
    $d = new DateTime($baseMonth.'-01');
    for($i=0;$i<$n;$i++){
      $d->modify('+1 month');
      mysqli_query($conn,"INSERT INTO payments(tenant_id,month_paid,amount) VALUES($tid,'".$d->format('Y-m')."',0)");
    }
  }

  if($n > 0 && $action === 'delete'){
    // Delete most recent unpaid (amount = 0) months, newest first
    $res = mysqli_query($conn,"SELECT payment_id FROM payments WHERE tenant_id=$tid AND amount=0 ORDER BY month_paid DESC LIMIT $n");
    while($row = mysqli_fetch_assoc($res)){
      mysqli_query($conn,"DELETE FROM payments WHERE payment_id=".$row['payment_id']);
    }
  }
}

/* Search */
$search = $_GET['search'] ?? '';
$sql    = "SELECT * FROM tenants";
if($search){
  $s = mysqli_real_escape_string($conn,$search);
  $sql.=" WHERE name LIKE '%$s%' OR room_number LIKE '%$s%'";
}
$sql.=" ORDER BY name";
$tenants = mysqli_query($conn,$sql);

// Payment statistics
$total_payments = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(amount) as total FROM payments"))['total'] ?? 0;
$current_month = date('Y-m');

// Count tenants with current month payment status
$paid_tenants = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) as count FROM tenants t
    LEFT JOIN payments p ON t.tenant_id = p.tenant_id AND p.month_paid = '$current_month'
    WHERE COALESCE(p.amount, 0) >= t.rent_amount
"))['count'] ?? 0;

$unpaid_tenants = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) as count FROM tenants t
    LEFT JOIN payments p ON t.tenant_id = p.tenant_id AND p.month_paid = '$current_month'
    WHERE COALESCE(p.amount, 0) < t.rent_amount
"))['count'] ?? 0;

$current_month_income = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(amount) as total FROM payments WHERE month_paid = '$current_month'"))['total'] ?? 0;
?>
<!DOCTYPE html>
<html>
<head>
<title>Manage Payments</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
  :root{
    --bh-primary:#0d6efd;
    --bh-deep:#0b3d5c;
    --bh-teal:#1f8a70;
    --bh-sand:#f4e9d8;
    --bh-coral:#ff6f59;
    --bh-sky:#e6f4ff;
  }
  body{ background:var(--bh-sky); font-family:'Poppins', system-ui, -apple-system, Segoe UI, Roboto, "Helvetica Neue", Arial; }
  .navbar{ background: linear-gradient(90deg, var(--bh-deep), var(--bh-teal)); position: sticky; top: 0; z-index: 1030; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
  .navbar .nav-link, .navbar .navbar-brand{ color:#fff; }
  .navbar .nav-link:hover{ color:#f8f9fa; opacity:.9; }
  .hero{ position:relative; background:url('../Hero-Boarding.jpg') center/cover no-repeat; color:#fff; border-radius:12px; overflow:hidden; }
  .hero::after{ content:""; position:absolute; inset:0; background:linear-gradient(180deg, rgba(11,61,92,.6), rgba(31,138,112,.45)); }
  .hero-content{ position:relative; z-index:1; padding:36px 20px; }
  .badge-paid{ background:#28a745; }
  .badge-partial{ background:#fd7e14; }
  .badge-unpaid{ background:#dc3545; }
  .table thead{ background:#0f2d44; color:#fff; }
  .table thead th{ text-transform:uppercase; letter-spacing:.02em; font-size:.8rem; }
  .table-hover tbody tr:hover{ background:#f7fbff; }
  .table tbody td{ padding:.75rem .9rem; }
  .table-card{ border-radius:12px; overflow:hidden; box-shadow:0 6px 20px rgba(0,0,0,.06); }
  .btn-coral{ background:var(--bh-coral); color:#fff; border:none; }
  .btn-coral:hover{ filter:brightness(.95); color:#fff; }
      .footer-note{ color:#6c757d; font-size:.9rem; }
    .search-w{ max-width:560px; }
    .stat-card{ border:0; border-radius:12px; overflow:hidden; box-shadow:0 4px 15px rgba(0,0,0,.08); }
    .stat-card .card-body{ padding:20px; }
    .stat-icon{ width:40px; height:40px; border-radius:8px; display:flex; align-items:center; justify-content:center; }
    .payment-status{ font-size:.75rem; padding:2px 6px; border-radius:10px; }
    .payment-item{ border-left:3px solid #e9ecef; padding-left:8px; margin-bottom:4px; }
    .payment-item.paid{ border-left-color:#28a745; }
    .payment-item.partial{ border-left-color:#fd7e14; }
    .payment-item.unpaid{ border-left-color:#dc3545; }
  </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark">
  <div class="container">
    <a class="navbar-brand fw-semibold" href="dashboard.php"><i class="bi bi-houses me-2"></i>Boarding House Admin</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarsMain"><span class="navbar-toggler-icon"></span></button>
    <div class="collapse navbar-collapse" id="navbarsMain">
      <ul class="navbar-nav ms-auto">
        <li class="nav-item"><a class="nav-link" href="tenants.php"><i class="bi bi-people me-1"></i>Tenants</a></li>
        <li class="nav-item"><a class="nav-link active" href="payments.php"><i class="bi bi-cash-stack me-1"></i>Payments</a></li>
        <li class="nav-item"><a class="nav-link" href="dashboard.php"><i class="bi bi-speedometer2 me-1"></i>Dashboard</a></li>
        <li class="nav-item ms-lg-2"><a class="btn btn-sm btn-light" href="../logout.php"><i class="bi bi-box-arrow-right me-1"></i>Logout</a></li>
      </ul>
    </div>
  </div>
</nav>

<div class="container mt-4">
  <div class="hero mb-4">
    <div class="hero-content d-flex flex-column flex-md-row align-items-md-center justify-content-between">
      <div class="mb-2 mb-md-0">
        <h1 class="h4 fw-semibold mb-1">Manage Payments</h1>
        <div class="small">Record and track monthly dues across rooms.</div>
      </div>
      <div class="d-flex gap-2">
        <a href="dashboard.php" class="btn btn-light btn-sm"><i class="bi bi-speedometer2 me-1"></i>Dashboard</a>
        <a href="tenants.php" class="btn btn-coral btn-sm"><i class="bi bi-people me-1"></i>Tenants</a>
      </div>
    </div>
  </div>

  <!-- Payment Statistics -->
  <div class="row g-3 mb-4">
    <div class="col-md-3">
      <div class="card stat-card">
        <div class="card-body d-flex align-items-center justify-content-between">
          <div>
            <div class="text-muted small">Total Income</div>
            <div class="h5 mb-0 fw-semibold">₱<?= number_format($total_payments, 2) ?></div>
          </div>
          <div class="stat-icon" style="background:var(--bh-sand); color:var(--bh-deep);"><i class="bi bi-currency-dollar"></i></div>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card stat-card">
        <div class="card-body d-flex align-items-center justify-content-between">
          <div>
            <div class="text-muted small">This Month</div>
            <div class="h5 mb-0 fw-semibold">₱<?= number_format($current_month_income, 2) ?></div>
          </div>
          <div class="stat-icon" style="background:var(--bh-teal); color:#fff;"><i class="bi bi-calendar-month"></i></div>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card stat-card">
        <div class="card-body d-flex align-items-center justify-content-between">
          <div>
            <div class="text-muted small">Paid Tenants</div>
            <div class="h5 mb-0 fw-semibold"><?= $paid_tenants ?></div>
          </div>
          <div class="stat-icon" style="background:#28a745; color:#fff;"><i class="bi bi-check-circle"></i></div>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card stat-card">
        <div class="card-body d-flex align-items-center justify-content-between">
          <div>
            <div class="text-muted small">Unpaid Tenants</div>
            <div class="h5 mb-0 fw-semibold"><?= $unpaid_tenants ?></div>
          </div>
          <div class="stat-icon" style="background:#dc3545; color:#fff;"><i class="bi bi-exclamation-circle"></i></div>
        </div>
      </div>
    </div>
  </div>

  <!-- Search bar (50% width)-->
  <form method="GET" class="d-flex mb-3 search-w">
    <input type="text" name="search"
           value="<?=htmlspecialchars($search)?>" 
           placeholder="Search by name or room"
           class="form-control me-2">
  <button class="btn btn-sm btn-primary" title="Search"><i class="bi bi-search"></i></button>
  </form>

  <div class="table-responsive table-card">
  <table class="table table-bordered table-striped table-hover align-middle mb-0">
    <thead>
      <tr>
        <th>Tenant</th>
        <th>Room</th>
        <th>Rent</th>
        <th>Payment Status</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
    <?php while($t=mysqli_fetch_assoc($tenants)): 
      // Get payment summary for this tenant
  $current_month_payment = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(amount) as total FROM payments WHERE tenant_id=".$t['tenant_id']." AND month_paid='$current_month'"));
  $current_amount = $current_month_payment ? floatval($current_month_payment['total']) : 0;
  $rent_amount = floatval($t['rent_amount']);
  $status = $current_amount >= $rent_amount ? 'Paid' : ($current_amount > 0 ? 'Partial' : 'Unpaid');
  $status_class = strtolower($status);
    ?>
    <tr>
      <td>
        <div class="fw-semibold"><?=htmlspecialchars($t['name'])?></div>
        <div class="text-muted small"><?=htmlspecialchars($t['contact'])?></div>
      </td>
      <td>
        <span class="badge bg-secondary">Room <?=$t['room_number']?></span>
      </td>
      <td>
        <div class="fw-semibold">₱<?= number_format($rent_amount, 2) ?></div>
        <div class="text-muted small">per month</div>
      </td>
      <td>
        <div class="d-flex align-items-center gap-2">
          <span class="badge badge-<?=$status_class?> payment-status"><?=$status?></span>
          <div class="small text-muted">
            <?php if($status === 'Partial'): ?>
              ₱<?= number_format($current_amount, 2) ?> / ₱<?= number_format($rent_amount, 2) ?>
            <?php elseif($status === 'Paid'): ?>
              Complete
            <?php else: ?>
              ₱0.00 / ₱<?= number_format($rent_amount, 2) ?>
            <?php endif; ?>
          </div>
        </div>
      </td>
      <td>
        <div class="d-flex gap-1">
          <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#paymentModal<?=$t['tenant_id']?>">
            <i class="bi bi-cash-coin"></i>
          </button>
          <button class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#historyModal<?=$t['tenant_id']?>">
            <i class="bi bi-clock-history"></i>
          </button>
          <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#monthsModal<?=$t['tenant_id']?>">
            <i class="bi bi-calendar-plus"></i>
          </button>
        </div>
      </td>
    </tr>
    <?php endwhile; ?>
    </tbody>
  </table>
  </div>

  <!-- Payment Modals for each tenant -->
  <?php 
  mysqli_data_seek($tenants, 0); // Reset result pointer
  while($t=mysqli_fetch_assoc($tenants)): 
  ?>
  <!-- Payment Modal -->
  <div class="modal fade" id="paymentModal<?=$t['tenant_id']?>" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Record Payment - <?=htmlspecialchars($t['name'])?></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form method="POST">
          <div class="modal-body">
            <input type="hidden" name="tenant_id" value="<?=$t['tenant_id']?>">
            <div class="mb-3">
              <label class="form-label">Select Month</label>
              <select name="month" class="form-select" required>
                <?php
                  $p2 = mysqli_query($conn,"SELECT month_paid, SUM(amount) as total_amount FROM payments WHERE tenant_id=".$t['tenant_id']." GROUP BY month_paid ORDER BY month_paid ASC");
                  while($row=mysqli_fetch_assoc($p2)){
                    $paid = $row['total_amount'] >= $t['rent_amount'];
                    echo '<option value="'.$row['month_paid'].'" '.($paid?'disabled':'').'>'.$row['month_paid'].($paid?' (Paid)':'').'</option>';
                  }
                ?>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label">Amount</label>
              <input type="number" step="0.01" name="amount" class="form-control" placeholder="Enter amount" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Received By</label>
              <input type="text" name="received_by" class="form-control" placeholder="Enter receiver's name" required>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" name="record_payment" class="btn btn-primary">Record Payment</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- History Modal -->
  <div class="modal fade" id="historyModal<?=$t['tenant_id']?>" tabindex="-1">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Payment History - <?=htmlspecialchars($t['name'])?></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <?php
$p = mysqli_query($conn,"SELECT * FROM payments WHERE tenant_id=".$t['tenant_id']." ORDER BY month_paid ASC, date_paid ASC");
            while($row=mysqli_fetch_assoc($p)){
              $amount = floatval($row['amount']);
              $total_for_month = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(amount) as total FROM payments WHERE tenant_id=".$t['tenant_id']." AND month_paid='".$row['month_paid']."'"))['total'];
              if ($amount == 0 && $total_for_month > 0) continue; // Skip 0 amount rows if there are payments in the month
              $status = $total_for_month >= $t['rent_amount'] ? 'Paid' : ($total_for_month > 0 ? 'Partial' : 'Unpaid');
              $status_class = strtolower($status);
              $month_display = DateTime::createFromFormat('Y-m', $row['month_paid'])->format('F Y');
              $date_display = $row['date_paid'] ? (new DateTime($row['date_paid']))->format('M d, Y') : 'Not paid';
              $receiver = $row['received_by'] ? htmlspecialchars($row['received_by']) : 'N/A';
          ?>
          <div class="payment-item <?=$status_class?>">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <strong><?=$month_display?></strong>
                <div class="text-muted small"><?=$date_display?></div>
                <div class="text-muted small">Received by: <?=$receiver?></div>
              </div>
              <div class="text-end">
                <span class="badge badge-<?=$status_class?> payment-status"><?=$status?></span>
                <div class="fw-semibold">₱<?= $amount == 0 ? "0.00 / ".number_format($t['rent_amount'], 2) : number_format($row['amount'], 2) ?></div>
              </div>
            </div>
          </div>
          <?php } ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Months Management Modal -->
  <div class="modal fade" id="monthsModal<?=$t['tenant_id']?>" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Manage Months - <?=htmlspecialchars($t['name'])?></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form method="POST">
          <div class="modal-body">
            <input type="hidden" name="tenant_id" value="<?=$t['tenant_id']?>">
            <div class="mb-3">
              <label class="form-label">Action</label>
              <select name="month_action" class="form-select">
                <option value="add">Add months</option>
                <option value="delete">Delete months</option>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label">Number of months</label>
              <input type="number" name="months_to_modify" class="form-control" min="1" placeholder="Enter number" required>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" name="modify_months" class="btn btn-success">Apply</button>
          </div>
        </form>
      </div>
    </div>
  </div>
  <?php endwhile; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Complete back button prevention for admin payments
(function() {
    // Completely disable back button functionality
    window.history.replaceState(null, null, window.location.href);
    
    // Add multiple entries to create a strong buffer
    window.history.pushState({page: 'payments1'}, '', window.location.href);
    window.history.pushState({page: 'payments2'}, '', window.location.href);
    window.history.pushState({page: 'payments3'}, '', window.location.href);
    window.history.pushState({page: 'payments4'}, '', window.location.href);
    window.history.pushState({page: 'payments5'}, '', window.location.href);
    
    // Handle back button - completely block it
    window.addEventListener('popstate', function(event) {
        // Immediately push multiple page entries back
        window.history.pushState({page: 'payments1'}, '', window.location.href);
        window.history.pushState({page: 'payments2'}, '', window.location.href);
        window.history.pushState({page: 'payments3'}, '', window.location.href);
        window.history.pushState({page: 'payments4'}, '', window.location.href);
        window.history.pushState({page: 'payments5'}, '', window.location.href);
    });
    
    // Handle page load/reload
    window.addEventListener('load', function() {
        // Re-establish strong history buffer on every load
        window.history.replaceState(null, null, window.location.href);
        window.history.pushState({page: 'payments1'}, '', window.location.href);
        window.history.pushState({page: 'payments2'}, '', window.location.href);
        window.history.pushState({page: 'payments3'}, '', window.location.href);
        window.history.pushState({page: 'payments4'}, '', window.location.href);
        window.history.pushState({page: 'payments5'}, '', window.location.href);
    });
    
    // Handle page show from cache
    window.addEventListener('pageshow', function(event) {
        // Re-establish strong history buffer on every page show
        window.history.replaceState(null, null, window.location.href);
        window.history.pushState({page: 'payments1'}, '', window.location.href);
        window.history.pushState({page: 'payments2'}, '', window.location.href);
        window.history.pushState({page: 'payments3'}, '', window.location.href);
        window.history.pushState({page: 'payments4'}, '', window.location.href);
        window.history.pushState({page: 'payments5'}, '', window.location.href);
    });
    
    // Additional protection - check URL and force redirect if needed
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
