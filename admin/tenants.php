<?php
session_start();

// Prevent caching of this page
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

if (!isset($_SESSION['admin'])) { header("Location: ../login.php"); exit(); }
include '../config/db.php';

$msg="";

/* ADD Tenant */
if (isset($_POST['add'])) {
    $name     = mysqli_real_escape_string($conn, $_POST['name']);
    $room     = mysqli_real_escape_string($conn, $_POST['room_number']);
    $contact  = mysqli_real_escape_string($conn, $_POST['contact']);
    $start    = mysqli_real_escape_string($conn, $_POST['start_date']);
    $rent     = floatval($_POST['rent_amount']);
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    // Check username uniqueness
  $ex = mysqli_query($conn, "SELECT username FROM users WHERE username='$username' LIMIT 1");
    if (mysqli_num_rows($ex) > 0) {
        $msg="⚠ Username already exists";
    } else {
        // Check room capacity (max 6 tenants per room)
        $room_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM tenants WHERE room_number='$room'"))['count'];
        if ($room_count >= 6) {
            $msg="⚠ Room $room is full (6/6 tenants). Please choose another room.";
        } else {
            mysqli_query($conn, "INSERT INTO tenants (name,room_number,contact,start_date,rent_amount)
                                VALUES ('$name','$room','$contact','$start',$rent)");
            $tenant_id = mysqli_insert_id($conn);
            // Create corresponding account record using same id
            mysqli_query($conn, "INSERT INTO users (id, username, password, role) VALUES ($tenant_id, '$username', '$password', 'tenant')");

            $d = new DateTime($start);
      for ($i=0; $i<12; $i++) {
        // Default received_by as empty for initial months
        mysqli_query($conn, "INSERT INTO payments (tenant_id,month_paid,amount,received_by) VALUES ($tenant_id,'".$d->format('Y-m')."',0,'')");
        $d->modify('+1 month');
      }
        }
    }
}

/* UPDATE Tenant */
if (isset($_POST['update'])) {
    $id       = intval($_POST['tenant_id']);
    $name     = $_POST['name'];
    $room     = $_POST['room_number'];
    $contact  = $_POST['contact'];
    $start    = $_POST['start_date'];
    $rent     = floatval($_POST['rent_amount']);
    $username = mysqli_real_escape_string($conn, $_POST['username']);

    // Check room capacity (max 6 tenants per room) - exclude current tenant
    $room_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM tenants WHERE room_number='$room' AND tenant_id != $id"))['count'];
    if ($room_count >= 6) {
        $msg="⚠ Room $room is full (6/6 tenants). Please choose another room.";
    } else {
        // Update tenant info
        mysqli_query($conn, "UPDATE tenants SET name='$name',room_number='$room',contact='$contact',start_date='$start',rent_amount=$rent WHERE tenant_id=$id");
        // Update account username if changed and unique
  $existsUser = mysqli_query($conn, "SELECT id FROM users WHERE username='$username' AND id <> $id LIMIT 1");
        if (mysqli_num_rows($existsUser) === 0) {
            mysqli_query($conn, "UPDATE users SET username='$username' WHERE id=$id AND role='tenant'");
        } else {
            $msg = $msg ? $msg : "⚠ Username already exists";
        }
        if (!empty($_POST['password'])) {
            $pass = password_hash($_POST['password'], PASSWORD_DEFAULT);
            mysqli_query($conn, "UPDATE users SET password='$pass' WHERE id=$id AND role='tenant'");
        }
    }
}

/* DELETE Tenant */
if (isset($_POST['delete'])) {
    $id = intval($_POST['tenant_id']);
    mysqli_query($conn, "DELETE FROM payments WHERE tenant_id=$id");
    mysqli_query($conn, "DELETE FROM tenants  WHERE tenant_id=$id");
  mysqli_query($conn, "DELETE FROM users WHERE id=$id AND role='tenant'");
}

/* Search */
$search = $_GET['search'] ?? '';
$sql = "SELECT t.*, a.username FROM tenants t LEFT JOIN users a ON a.id = t.tenant_id AND a.role='tenant'";
if($search){
  $s = mysqli_real_escape_string($conn,$search);
  $sql.=" WHERE t.name LIKE '%$s%' OR t.room_number LIKE '%$s%' OR t.contact LIKE '%$s%' OR a.username LIKE '%$s%'";
}
$sql.=" ORDER BY t.name";
$tenants = mysqli_query($conn,$sql);

// Tenant statistics
$total_tenants = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM tenants"))['count'] ?? 0;
$current_month = date('Y-m');

// Count tenants by room occupancy
$room_occupancy = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT room_number, COUNT(*) as count 
    FROM tenants 
    GROUP BY room_number 
    ORDER BY room_number
"));

// Count tenants with current month payment status using same logic as manage payments
$paid_tenants = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) as count FROM tenants t
    LEFT JOIN payments p ON t.tenant_id = p.tenant_id AND p.month_paid = '$current_month'
    WHERE COALESCE(p.amount, 0) >= t.rent_amount
"))['count'] ?? 0;

$partial_tenants = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) as count FROM tenants t
    LEFT JOIN payments p ON t.tenant_id = p.tenant_id AND p.month_paid = '$current_month'
    WHERE COALESCE(p.amount, 0) > 0 AND COALESCE(p.amount, 0) < t.rent_amount
    AND CURDATE() < STR_TO_DATE(CONCAT(DATE_FORMAT(CURDATE(), '%Y-%m'), '-', DAY(t.start_date)), '%Y-%m-%d')
"))['count'] ?? 0;

$unpaid_tenants = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) as count FROM tenants t
    LEFT JOIN payments p ON t.tenant_id = p.tenant_id AND p.month_paid = '$current_month'
    WHERE COALESCE(p.amount, 0) < t.rent_amount
    AND (CURDATE() >= STR_TO_DATE(CONCAT(DATE_FORMAT(CURDATE(), '%Y-%m'), '-', DAY(t.start_date)), '%Y-%m-%d')
         OR COALESCE(p.amount, 0) = 0)
"))['count'] ?? 0;

?>
<!DOCTYPE html>
<html>
<head>
<title>Manage Tenants</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
  :root{
    --bh-primary:#0d6efd; --bh-deep:#0b3d5c; --bh-teal:#1f8a70; --bh-sand:#f4e9d8; --bh-coral:#ff6f59; --bh-sky:#e6f4ff;
  }
  body{ background:var(--bh-sky); font-family:'Poppins', system-ui, -apple-system, Segoe UI, Roboto, "Helvetica Neue", Arial; }
  .navbar{ background: linear-gradient(90deg, var(--bh-deep), var(--bh-teal)); position: sticky; top: 0; z-index: 1030; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
  .navbar .nav-link, .navbar .navbar-brand{ color:#fff; }
  .navbar .nav-link:hover{ color:#f8f9fa; opacity:.9; }
  .hero{ position:relative; background:url('../Hero-Boarding.jpg') center/cover no-repeat; color:#fff; border-radius:12px; overflow:hidden; }
  .hero::after{ content:""; position:absolute; inset:0; background:linear-gradient(180deg, rgba(11,61,92,.6), rgba(31,138,112,.45)); }
  .hero-content{ position:relative; z-index:1; padding:36px 20px; }
  .table thead{ background:#0f2d44; color:#fff; }
  .table thead th{ text-transform:uppercase; letter-spacing:.02em; font-size:.8rem; }
  .table-hover tbody tr:hover{ background:#f7fbff; }
  .table tbody td{ padding:.75rem .9rem; }
  .table-card{ border-radius:12px; overflow:hidden; box-shadow:0 6px 20px rgba(0,0,0,.06); }
  .btn-coral{ background:var(--bh-coral); color:#fff; border:none; }
  .btn-coral:hover{ filter:brightness(.95); color:#fff; }
  .footer-note{ color:#6c757d; font-size:.9rem; }
  .stat-card{ border:0; border-radius:12px; overflow:hidden; box-shadow:0 4px 15px rgba(0,0,0,.08); }
  .stat-card .card-body{ padding:20px; }
  .stat-icon{ width:40px; height:40px; border-radius:8px; display:flex; align-items:center; justify-content:center; }
  .room-badge{ font-size:.75rem; padding:4px 8px; border-radius:12px; }
  .room-full{ background:#dc3545; color:#fff; }
  .room-available{ background:#28a745; color:#fff; }
  .room-partial{ background:#fd7e14; color:#fff; }
  .tenant-avatar{ width:40px; height:40px; border-radius:50%; background:var(--bh-sand); display:flex; align-items:center; justify-content:center; color:var(--bh-deep); font-weight:600; }
  .badge-paid{ background-color: #28a745; color: white; }
  .badge-partial{ background-color: #fd7e14; color: white; }
  .badge-unpaid{ background-color: #dc3545; color: white; }
  .badge{ border-radius: 50rem; }
  .search-w{ max-width:560px; }
</style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark">
  <div class="container">
    <a class="navbar-brand fw-semibold" href="dashboard.php"><i class="bi bi-houses me-2"></i>Boarding House Admin</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarsMain"><span class="navbar-toggler-icon"></span></button>
    <div class="collapse navbar-collapse" id="navbarsMain">
      <ul class="navbar-nav ms-auto">
        <li class="nav-item"><a class="nav-link active" href="tenants.php"><i class="bi bi-people me-1"></i>Tenants</a></li>
        <li class="nav-item"><a class="nav-link" href="payments.php"><i class="bi bi-cash-stack me-1"></i>Payments</a></li>
        <li class="nav-item"><a class="nav-link active" href="dashboard.php"><i class="bi bi-speedometer2 me-1"></i>Dasboard</a></li>
        <!-- <li class="nav-item ms-lg-2"><a class="btn btn-sm btn-light me-lg-2" href="dashboard.php"><i class="bi bi-speedometer2 me-1"></i>Dashboard</a></li> -->
        <li class="nav-item ms-lg-2"><a class="btn btn-sm btn-light" href="../logout.php"><i class="bi bi-box-arrow-right me-1"></i>Logout</a></li>
      </ul>
    </div>
  </div>
</nav>

<div class="container mt-4">
  <div class="hero mb-4">
    <div class="hero-content d-flex flex-column flex-md-row align-items-md-center justify-content-between">
      <div class="mb-2 mb-md-0">
        <h1 class="h4 fw-semibold mb-1">Manage Tenants</h1>
        <div class="small">Add, edit, and organize boarders and rooms.</div>
      </div>
      <div class="d-flex gap-2">
        <a href="dashboard.php" class="btn btn-light btn-sm"><i class="bi bi-speedometer2 me-1"></i>Dashboard</a>
        <a href="payments.php" class="btn btn-coral btn-sm"><i class="bi bi-cash-coin me-1"></i>Payments</a>
        <!-- <button class="btn btn-coral btn-sm" data-bs-toggle="modal" data-bs-target="#addModal"><i class="bi bi-person-plus me-1"></i>Add Tenant</button> -->
      </div>
    </div>
  </div>
  <!-- Tenant Statistics -->
  <div class="row g-3 mb-4">
    <div class="col-md-3">
      <div class="card stat-card">
        <div class="card-body d-flex align-items-center justify-content-between">
          <div>
            <div class="text-muted small">Total Tenants</div>
            <div class="h5 mb-0 fw-semibold"><?= $total_tenants ?></div>
          </div>
          <div class="stat-icon" style="background:var(--bh-sand); color:var(--bh-deep);"><i class="bi bi-people"></i></div>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card stat-card">
        <div class="card-body d-flex align-items-center justify-content-between">
          <div>
            <div class="text-muted small">Paid This Month</div>
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
            <div class="text-muted small">Partial This Month</div>
            <div class="h5 mb-0 fw-semibold"><?= $partial_tenants ?></div>
          </div>
          <div class="stat-icon" style="background:#fd7e14; color:#fff;"><i class="bi bi-clock"></i></div>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card stat-card">
        <div class="card-body d-flex align-items-center justify-content-between">
          <div>
            <div class="text-muted small">Unpaid This Month</div>
            <div class="h5 mb-0 fw-semibold"><?= $unpaid_tenants ?></div>
          </div>
          <div class="stat-icon" style="background:#dc3545; color:#fff;"><i class="bi bi-exclamation-circle"></i></div>
        </div>
      </div>
    </div>
  </div>

  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">Tenant Management</h3>
    <div>
      <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal"><i class="bi bi-person-plus me-1"></i>Add Tenant</button>
    </div>
  </div>

  <!-- Search bar -->
  <form method="GET" class="d-flex mb-3 search-w">
    <input type="text" name="search"
           value="<?=htmlspecialchars($search)?>" 
           placeholder="Search by name, room, contact, or username"
           class="form-control me-2">
  <button class="btn btn-sm btn-primary" title="Search"><i class="bi bi-search"></i></button>
  </form>

  <?php if($msg) echo "<div class='alert alert-warning'>$msg</div>"; ?>

  <div class="table-responsive table-card">
    <table class="table table-bordered table-striped table-sm table-hover align-middle mb-0">
      <thead>
        <tr>
          <th>Tenant</th>
          <th>Room</th>
          <th>Contact</th>
          <th>Start Date</th>
          <th>Rent</th>
          <th>Payment Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php while($t=mysqli_fetch_assoc($tenants)){ 
    // Get current month payment status using sum of all payments for the month
    $current_month_payment = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(amount) as total FROM payments WHERE tenant_id=".$t['tenant_id']." AND month_paid='$current_month'"));
    $current_amount = $current_month_payment ? floatval($current_month_payment['total']) : 0;
    $rent_amount = floatval($t['rent_amount']);
    // Status logic: Paid, Partial, Unpaid
    if ($current_amount >= $rent_amount) {
      $status = 'Paid';
    } elseif ($current_amount > 0) {
      $status = 'Partial';
    } else {
      $status = 'Unpaid';
    }
    $status_class = strtolower($status);
    // Get room occupancy
    $room_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM tenants WHERE room_number='".$t['room_number']."'"))['count'];
    $room_status = $room_count >= 6 ? 'full' : ($room_count >= 4 ? 'partial' : 'available');
      ?>
      <tr>
        <td>
          <div class="d-flex align-items-center gap-3">
            <div class="tenant-avatar"><?= strtoupper(substr($t['name'], 0, 2)) ?></div>
            <div>
              <div class="fw-semibold"><?=htmlspecialchars($t['name'])?></div>
              <div class="text-muted small">@<?=htmlspecialchars($t['username'])?></div>
            </div>
          </div>
        </td>
        <td>
          <span class="room-badge room-<?=$room_status?>">Room <?=$t['room_number']?></span>
          <div class="text-muted small"><?=$room_count?>/6 tenants</div>
        </td>
        <td>
          <div class="fw-semibold"><?=$t['contact']?></div>
        </td>
        <td>
          <div class="fw-semibold"><?=date('M d, Y', strtotime($t['start_date']))?></div>
          <div class="text-muted small"><?=date('Y', strtotime($t['start_date']))?></div>
        </td>
        <td>
          <div class="fw-semibold">₱<?=number_format($t['rent_amount'],2)?></div>
          <div class="text-muted small">per month</div>
        </td>
        <td>
          <div class="d-flex align-items-center gap-2">
            <span class="badge badge-<?=$status_class?>"><?=$status?></span>
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
            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#edit<?=$t['tenant_id']?>" title="Edit">
              <i class="bi bi-pencil"></i>
            </button>
            <button class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#view<?=$t['tenant_id']?>" title="View Details">
              <i class="bi bi-eye"></i>
            </button>
            <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#del<?=$t['tenant_id']?>" title="Delete">
              <i class="bi bi-trash"></i>
            </button>
          </div>
        </td>
      </tr>

    <!-- Edit Modal -->
    <div class="modal fade" id="edit<?=$t['tenant_id']?>" tabindex="-1">
      <div class="modal-dialog"><div class="modal-content">
        <form method="POST">
          <div class="modal-header"><h5>Edit Tenant</h5></div>
          <div class="modal-body">
            <input type="hidden" name="tenant_id" value="<?=$t['tenant_id']?>">
            <input type="text" name="name" class="form-control mb-2" value="<?=$t['name']?>" required>
            <input type="text" name="room_number" class="form-control mb-2" value="<?=$t['room_number']?>" required>
            <input type="text" name="contact" class="form-control mb-2" value="<?=$t['contact']?>" maxlength="11" pattern="09\d{9}"
                   oninput="this.value='09'+this.value.replace(/[^0-9]/g,'').slice(2,11)">
            <input type="date"   name="start_date" class="form-control mb-2" value="<?=$t['start_date']?>" required>
            <input type="number" name="rent_amount" step="0.01" class="form-control mb-2" value="<?=$t['rent_amount']?>" required>
            <input type="text"   name="username" class="form-control mb-2" value="<?=$t['username']?>" required>
            <input type="password" name="password" class="form-control" placeholder="Leave blank to keep old password">
          </div>
          <div class="modal-footer"><button type="submit" name="update" class="btn btn-success">Save</button></div>
        </form>
      </div></div>
    </div>

    <!-- View Details Modal -->
    <div class="modal fade" id="view<?=$t['tenant_id']?>" tabindex="-1">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Tenant Details - <?=htmlspecialchars($t['name'])?></h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="row g-3">
              <div class="col-md-6">
                <div class="card">
                  <div class="card-header">Personal Information</div>
                  <div class="card-body">
                    <p><strong>Name:</strong> <?=htmlspecialchars($t['name'])?></p>
                    <p><strong>Username:</strong> @<?=htmlspecialchars($t['username'])?></p>
                    <p><strong>Contact:</strong> <?=$t['contact']?></p>
                    <p><strong>Start Date:</strong> <?=date('F d, Y', strtotime($t['start_date']))?></p>
                  </div>
                </div>
              </div>
              <div class="col-md-6">
                <div class="card">
                  <div class="card-header">Room & Payment</div>
                  <div class="card-body">
                    <p><strong>Room:</strong> <?=$t['room_number']?> (<?=$room_count?>/6 tenants)</p>
                    <p><strong>Monthly Rent:</strong> ₱<?=number_format($t['rent_amount'],2)?></p>
                    <p><strong>Current Status:</strong> 
                      <span class="badge badge-<?=$status_class?>"><?=$status?></span>
                    </p>
                    <?php if($status === 'Partial'): ?>
                      <p><strong>Amount Paid:</strong> ₱<?= number_format($current_amount, 2) ?> / ₱<?= number_format($rent_amount, 2) ?></p>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            <button type="button" class="btn btn-primary" data-bs-dismiss="modal" data-bs-toggle="modal" data-bs-target="#edit<?=$t['tenant_id']?>">Edit Tenant</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Delete Modal -->
    <div class="modal fade" id="del<?=$t['tenant_id']?>" tabindex="-1">
      <div class="modal-dialog"><div class="modal-content">
        <form method="POST">
          <input type="hidden" name="tenant_id" value="<?=$t['tenant_id']?>">
          <div class="modal-header"><h5>Delete Tenant</h5></div>
          <div class="modal-body">Are you sure?</div>
          <div class="modal-footer">
            <button type="submit" name="delete" class="btn btn-danger">Delete</button>
            <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          </div>
        </form>
      </div></div>
    </div>
    <?php } ?>
    </table>
  </div>
</div>

<!-- Add Modal -->
<div class="modal fade" id="addModal" tabindex="-1">
  <div class="modal-dialog"><div class="modal-content">
    <form method="POST">
      <div class="modal-header"><h5>Add Tenant</h5></div>
      <div class="modal-body">
        <input type="text" name="name" class="form-control mb-2" placeholder="Name" required>
        <input type="text" name="room_number" class="form-control mb-2" placeholder="Room" required>
        <input type="text" name="contact" class="form-control mb-2" placeholder="Contact"
               value="09" maxlength="11" pattern="09\d{9}" oninput="this.value='09'+this.value.replace(/[^0-9]/g,'').slice(2,11)" required>
        <input type="date" name="start_date" class="form-control mb-2" required>
        <input type="number" step="0.01" name="rent_amount" class="form-control mb-2" placeholder="Rent" required>
        <input type="text" name="username"   class="form-control mb-2" placeholder="Username" required>
        <input type="password" name="password" class="form-control" placeholder="Password" required>
      </div>
      <div class="modal-footer"><button class="btn btn-primary" name="add">Add</button></div>
    </form>
  </div></div>
</div>

<div class="container mt-4 mb-5">
  <p class="footer-note text-center">Keeping your rooms full and payments on time.</p>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Complete back button prevention for admin tenants
(function() {
    // Completely disable back button functionality
    window.history.replaceState(null, null, window.location.href);
    
    // Add multiple entries to create a strong buffer
    window.history.pushState({page: 'tenants1'}, '', window.location.href);
    window.history.pushState({page: 'tenants2'}, '', window.location.href);
    window.history.pushState({page: 'tenants3'}, '', window.location.href);
    window.history.pushState({page: 'tenants4'}, '', window.location.href);
    window.history.pushState({page: 'tenants5'}, '', window.location.href);
    
    // Handle back button - completely block it
    window.addEventListener('popstate', function(event) {
        // Immediately push multiple page entries back
        window.history.pushState({page: 'tenants1'}, '', window.location.href);
        window.history.pushState({page: 'tenants2'}, '', window.location.href);
        window.history.pushState({page: 'tenants3'}, '', window.location.href);
        window.history.pushState({page: 'tenants4'}, '', window.location.href);
        window.history.pushState({page: 'tenants5'}, '', window.location.href);
    });
    
    // Handle page load/reload
    window.addEventListener('load', function() {
        // Re-establish strong history buffer on every load
        window.history.replaceState(null, null, window.location.href);
        window.history.pushState({page: 'tenants1'}, '', window.location.href);
        window.history.pushState({page: 'tenants2'}, '', window.location.href);
        window.history.pushState({page: 'tenants3'}, '', window.location.href);
        window.history.pushState({page: 'tenants4'}, '', window.location.href);
        window.history.pushState({page: 'tenants5'}, '', window.location.href);
    });
    
    // Handle page show from cache
    window.addEventListener('pageshow', function(event) {
        // Re-establish strong history buffer on every page show
        window.history.replaceState(null, null, window.location.href);
        window.history.pushState({page: 'tenants1'}, '', window.location.href);
        window.history.pushState({page: 'tenants2'}, '', window.location.href);
        window.history.pushState({page: 'tenants3'}, '', window.location.href);
        window.history.pushState({page: 'tenants4'}, '', window.location.href);
        window.history.pushState({page: 'tenants5'}, '', window.location.href);
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
