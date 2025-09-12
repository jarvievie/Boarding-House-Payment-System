 <?php
session_start();

// Prevent caching of this page
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

if (!isset($_SESSION['admin'])) {
    header("Location: ../login.php");
    exit();
}

include '../config/db.php';

$search = $_GET['search'] ?? '';

// Dashboard statistics
$total_income = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(amount) AS total FROM payments"))['total'];
$total_tenants = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM tenants"))['total'];
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


// Check if viewing a single tenant
$tenant_id = $_GET['tenant_id'] ?? null;

?>
<script>
// Prevent navigating back to login page from dashboard (replace login in history, do not push new state)
(function() {
    // Replace the previous (login) entry with dashboard only
    window.history.replaceState({page: 'dashboard'}, '', window.location.href);
    // Optionally, handle popstate to prevent further back navigation
    window.addEventListener('popstate', function(event) {
        window.history.replaceState({page: 'dashboard'}, '', window.location.href);
    });
})();
</script>
<?php

if ($tenant_id) {
    // Fetch tenant info
    $tenant = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM tenants WHERE tenant_id=$tenant_id"));

    // Fetch payments
    $payments = mysqli_query($conn, "SELECT amount, date_paid, month_paid FROM payments WHERE tenant_id=$tenant_id ORDER BY month_paid ASC");

    $payments_list = [];
    $months_paid = [];
    $paid_months = [];
    $partial_months = [];
    $unpaid_months = [];
    $rent_for_month = floatval($tenant['rent_amount']);
    while ($p = mysqli_fetch_assoc($payments)) {
        $amount_val = floatval($p['amount']);
        $month_key  = $p['month_paid'];
        $payments_list[] = "₱" . number_format($amount_val, 2) . " - " . ($p['date_paid'] ?? 'Unpaid');
        $months_paid[] = $month_key;

        if ($amount_val >= $rent_for_month) {
            $paid_months[] = ['month' => $month_key, 'amount' => $amount_val, 'date_paid' => $p['date_paid']];
        } elseif ($amount_val > 0) {
            $partial_months[] = ['month' => $month_key, 'amount' => $amount_val];
        } else {
            $unpaid_months[] = $month_key;
        }
    }

    // Calculate months pending
    $start = new DateTime($tenant['start_date']);
    $end = new DateTime(date('Y-m-01'));
    $interval = new DateInterval('P1M');
    $period = new DatePeriod($start, $interval, $end->modify('+1 month'));
    $all_months = [];
    foreach ($period as $dt)
        $all_months[] = $dt->format('Y-m');
    $months_pending = array_diff($all_months, $months_paid);

} else {
    // Fetch tenants and determine current month payment status using same logic as payments.php
    $sql = "
        SELECT 
            t.tenant_id,
            t.name,
            t.room_number,
            t.rent_amount,
            COALESCE(SUM(p.amount), 0) AS amount_this_month
        FROM tenants t
        LEFT JOIN payments p ON p.tenant_id = t.tenant_id AND p.month_paid = DATE_FORMAT(CURDATE(), '%Y-%m')
    ";
    if ($search) {
        $search_safe = mysqli_real_escape_string($conn, $search);
        $sql .= " WHERE t.name LIKE '%$search_safe%' OR t.room_number LIKE '%$search_safe%' OR t.contact LIKE '%$search_safe%' ";
    }
    $sql .= " GROUP BY t.tenant_id ORDER BY t.name ASC";
    $tenants = mysqli_query($conn, $sql);
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root{
            --bh-primary:#0d6efd;
            --bh-deep:#0b3d5c;    /* deep nautical */
            --bh-teal:#1f8a70;    /* coastal teal */
            --bh-sand:#f4e9d8;    /* beach sand */
            --bh-coral:#ff6f59;   /* coral accent */
            --bh-sky:#e6f4ff;     /* light sky */
        }
        body{ 
            background-color: var(--bh-sky);
            font-family: 'Poppins', system-ui, -apple-system, Segoe UI, Roboto, "Helvetica Neue", Arial, "Noto Sans", "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol";
        }
        .navbar{
            background: linear-gradient(90deg, var(--bh-deep), var(--bh-teal));
            position: sticky;
            top: 0;
            z-index: 1030;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .navbar .nav-link, .navbar .navbar-brand{ color:#fff; }
        .navbar .nav-link:hover{ color:#f8f9fa; opacity:.9; }
        .hero{
            position: relative;
            background: url('../Hero-Boarding.jpg') center/cover no-repeat;
            color: #fff;
            border-radius: 12px;
            overflow: hidden;
        }
        .hero::after{
            content:"";
            position:absolute; inset:0;
            background: linear-gradient(180deg, rgba(11,61,92,.6), rgba(31,138,112,.5));
        }
        .hero-content{
            position:relative; z-index:1;
            padding: 48px 24px;
        }
        .metric-card{
            border: 0; border-radius: 12px; overflow:hidden;
            box-shadow: 0 6px 20px rgba(0,0,0,.06);
        }
        .metric-card .card-body{ padding: 22px; }
        .metric-icon{
            width:48px; height:48px; border-radius:10px; display:flex; align-items:center; justify-content:center;
            background: var(--bh-sand); color: var(--bh-deep);
        }
        .card-header{ background: var(--bh-deep); color:#fff; font-weight:600; }
        .badge-paid{ background-color: #28a745; color: white; }
        .badge-partial{ background-color: #fd7e14; color: white; }
        .badge-unpaid{ background-color: #dc3545; color: white; }
        .badge{ border-radius: 50rem; }
        .table thead{ background-color: #0f2d44; color:white; }
        .table thead th{ text-transform: uppercase; letter-spacing:.02em; font-size:.8rem; }
        .table-hover tbody tr:hover{ background-color:#f7fbff; }
        .table tbody td{ padding:.75rem .9rem; }
        .table-card{ border-radius:12px; overflow:hidden; box-shadow:0 6px 20px rgba(0,0,0,.06); }
        .search-box{ max-width: 560px; }
        .btn-coral{ background: var(--bh-coral); color:#fff; border:none; }
        .btn-coral:hover{ filter: brightness(.95); color:#fff; }
        .footer-note{ color:#6c757d; font-size:.9rem; }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark">
	<div class="container">
		<a class="navbar-brand fw-semibold" href="dashboard.php"><i class="bi bi-houses me-2"></i>Boarding House Admin</a>
		<button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarsMain" aria-controls="navbarsMain" aria-expanded="false" aria-label="Toggle navigation">
			<span class="navbar-toggler-icon"></span>
		</button>
		<div class="collapse navbar-collapse" id="navbarsMain">
			<ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                <li class="nav-item"><a class="nav-link" href="update_profile.php" title="Update Profile"><i class="bi bi-person-gear me-1" style="font-size:1.5rem;"></i></a></li>
                <li class="nav-item"><a class="nav-link" href="tenants.php"><i class="bi bi-people me-1"></i>Tenants</a></li>
				<li class="nav-item"><a class="nav-link" href="payments.php"><i class="bi bi-cash-stack me-1"></i>Payments</a></li>
				<li class="nav-item ms-lg-2"><a class="btn btn-sm btn-light" href="../logout.php"><i class="bi bi-box-arrow-right me-1"></i>Logout</a></li>
			</ul>
		</div>
	</div>
</nav>

<div class="container mt-4">
	<div class="hero mb-4">
		<div class="hero-content d-flex flex-column flex-md-row align-items-md-center justify-content-between">
			<div class="mb-3 mb-md-0">
				<h1 class="h3 fw-semibold mb-1">Welcome, <?php echo $_SESSION['admin']; ?></h1>
				<p class="mb-0">Manage tenants, payments, and insights for your boarding house.</p>
			</div>
			<div class="d-flex gap-2">
				<a href="tenants.php" class="btn btn-light"><i class="bi bi-people me-1"></i>Manage Tenants</a>
				<a href="payments.php" class="btn btn-coral"><i class="bi bi-cash-coin me-1"></i>Manage Payments</a>
			</div>
		</div>
	</div>

	<!-- Dashboard Statistics -->
	<div class="row g-3 mb-4">
		<div class="col-md-3">
			<div class="card metric-card">
				<div class="card-body d-flex align-items-center justify-content-between">
					<div>
						<div class="text-muted small">Total Tenants</div>
						<div class="h4 mb-0 fw-semibold"><?php echo $total_tenants; ?></div>
					</div>
					<div class="metric-icon"><i class="bi bi-people"></i></div>
				</div>
			</div>
		</div>
		<div class="col-md-3">
			<div class="card metric-card">
				<div class="card-body d-flex align-items-center justify-content-between">
					<div>
						<div class="text-muted small">Total Income</div>
						<div class="h4 mb-0 fw-semibold">₱<?php echo number_format($total_income,2); ?></div>
					</div>
					<div class="metric-icon"><i class="bi bi-currency-dollar"></i></div>
				</div>
			</div>
		</div>
		<div class="col-md-3">
			<div class="card metric-card">
				<div class="card-body d-flex align-items-center justify-content-between">
					<div>
						<div class="text-muted small">This Month</div>
						<div class="h4 mb-0 fw-semibold">₱<?php echo number_format($current_month_income,2); ?></div>
					</div>
					<div class="metric-icon"><i class="bi bi-calendar-month"></i></div>
				</div>
			</div>
		</div>
		<!-- <div class="col-md-3">
			<div class="card metric-card">
				<div class="card-body d-flex align-items-center justify-content-between">
					<div>
						<div class="text-muted small">Paid This Month</div>
						<div class="h4 mb-0 fw-semibold"><?php echo $paid_tenants; ?></div>
					</div>
					<div class="metric-icon" style="background:#28a745; color:#fff;"><i class="bi bi-check-circle"></i></div>
				</div>
			</div>
		</div> -->
		<div class="col-md-3">
			<div class="card metric-card">
				<div class="card-body d-flex align-items-center justify-content-between">
					<div>
						<div class="text-muted small">Unpaid This Month</div>
						<div class="h4 mb-0 fw-semibold"><?php echo $unpaid_tenants; ?></div>
					</div>
					<div class="metric-icon" style="background:#dc3545; color:#fff;"><i class="bi bi-exclamation-circle"></i></div>
				</div>
			</div>
		</div>
	</div>

    <?php if ($tenant_id && $tenant): ?>
        <div class="card mb-4">
            <div class="card-header">Tenant Details: <?php echo htmlspecialchars($tenant['name']); ?></div>
            <div class="card-body">
                <p><strong>Room:</strong> <?php echo $tenant['room_number']; ?></p>
                <p><strong>Rent:</strong> ₱<?php echo number_format($tenant['rent_amount'],); ?></p>
                <p><strong>Total Paid:</strong>
                    ₱<?php echo number_format(array_sum(array_map(fn($p)=>floatval(preg_replace('/[^0-9.]/','',$p)), $payments_list)),); ?></p>
                <p><strong>Balance:</strong>
                    ₱<?php echo number_format($tenant['rent_amount'] - array_sum(array_map(fn($p)=>floatval(preg_replace('/[^0-9.]/','',$p)), $payments_list)),); ?></p>

                <div class="row g-3 mt-3">
                    <div class="col-md-4">
                        <div class="card h-100">
                            <div class="card-header">Paid Months</div>
                            <div class="card-body">
                                <?php if (empty($paid_months)): ?>
                                    <div class="text-muted small">No fully paid months yet.</div>
                                <?php else: ?>
                                    <ul class="mb-0">
                                    <?php foreach ($paid_months as $pm):
                                        $dt = DateTime::createFromFormat('Y-m',$pm['month']);
                                        $datePaid = $pm['date_paid'] ? (new DateTime($pm['date_paid']))->format('M d, Y') : '-';
                                        echo "<li><span class='badge badge-paid me-2'>".$dt->format('F Y')."</span> ₱".number_format($pm['amount'],2)." <span class='text-muted small'>(".$datePaid.")</span></li>";
                                    endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card h-100">
                            <div class="card-header">Partial Months</div>
                            <div class="card-body">
                                <?php if (empty($partial_months)): ?>
                                    <div class="text-muted small">No partial payments.</div>
                                <?php else: ?>
                                    <ul class="mb-0">
                                    <?php foreach ($partial_months as $pp):
                                        $dt = DateTime::createFromFormat('Y-m',$pp['month']);
                                        echo "<li><span class='badge badge-partial me-2'>".$dt->format('F Y')."</span> ₱".number_format($pp['amount'],2)." / ₱".number_format($rent_for_month,2)."</li>";
                                    endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card h-100">
                            <div class="card-header">Unpaid Months</div>
                            <div class="card-body">
                                <?php if (empty($unpaid_months) && empty($months_pending)): ?>
                                    <div class="text-muted small">No unpaid months.</div>
                                <?php else: ?>
                                    <ul class="mb-0">
                                    <?php foreach ($unpaid_months as $m):
                                        $dt = DateTime::createFromFormat('Y-m',$m);
                                        echo "<li><span class='badge badge-unpaid'>".$dt->format('F Y')."</span></li>";
                                    endforeach; ?>
                                    <?php foreach ($months_pending as $m):
                                        $dt = DateTime::createFromFormat('Y-m',$m);
                                        echo "<li><span class='badge badge-unpaid'>".$dt->format('F Y')."</span> <span class='text-muted small'>(no record)</span></li>";
                                    endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <a href="dashboard.php" class="btn btn-secondary btn-sm mt-3">Back to Tenant List</a>
            </div>
        </div>
    <?php else: ?>
        <div class="search-box mb-3">
            <form method="GET" class="d-flex">
                <input type="text" name="search" class="form-control me-2" placeholder="Search by name, room, or contact" value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit" class="btn btn-sm btn-primary" title="Search"><i class="bi bi-search"></i></button>
            </form>
        </div>

        <div class="table-responsive table-card">
            <table class="table table-bordered table-hover table-striped align-middle mb-0">
                <thead>
                    <tr>
                        <th>TENANT</th>
                        <th>ROOM</th>
                        <th>RENT</th>
                        <th>PAYMENT STATUS</th>
                        <th>ACTIONS</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $found = false;
                while($t = mysqli_fetch_assoc($tenants)):
                    $found = true;
                    $current_amount = floatval($t['amount_this_month']);
                    $rent_amount = floatval($t['rent_amount']);
                    if ($current_amount >= $rent_amount) {
                        $status = 'Paid';
                    } elseif ($current_amount > 0) {
                        $status = 'Partial';
                    } else {
                        $status = 'Unpaid';
                    }
                    $status_class = 'badge-' . strtolower($status);
                    // Get tenant contact info
                    $tenant_info = mysqli_fetch_assoc(mysqli_query($conn, "SELECT contact FROM tenants WHERE tenant_id=".$t['tenant_id']));
                    $contact = $tenant_info['contact'] ?? '';
                    ?>
                    <tr>
                        <td>
                            <div class="fw-semibold"><?php echo htmlspecialchars($t['name']); ?></div>
                            <div class="text-muted small"><?php echo htmlspecialchars($contact); ?></div>
                        </td>
                        <td>
                            <span class="badge bg-secondary">Room <?php echo htmlspecialchars($t['room_number']); ?></span>
                        </td>
                        <td>
                            <div class="fw-semibold">₱<?php echo number_format($rent_amount, 2); ?></div>
                            <div class="text-muted small">per month</div>
                        </td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge <?php echo $status_class; ?> payment-status"><?php echo $status; ?></span>
                                <div class="small text-muted">
                                    <?php if($status === 'Partial'): ?>
                                        ₱<?php echo number_format($current_amount, 2); ?> / ₱<?php echo number_format($rent_amount, 2); ?>
                                    <?php elseif($status === 'Paid'): ?>
                                        Complete
                                    <?php else: ?>
                                        ₱0.00 / ₱<?php echo number_format($rent_amount, 2); ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td>
                            <a href="dashboard.php?tenant_id=<?php echo $t['tenant_id']; ?>" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-eye me-1"></i>View Details
                            </a>
                        </td>
                    </tr>
                <?php endwhile; ?>
                <?php if(!$found): ?>
                    <tr><td colspan="5" class="text-center">No tenants found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<div class="container mt-4 mb-5">
	<p class="footer-note text-center">Keeping your rooms full and payments on time.</p>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    
(function() {
    // Completely disable back button functionality
    window.history.replaceState(null, null, window.location.href);
    
    // Add many entries to create an ultra-strong buffer
    for (let i = 1; i <= 10; i++) {
        window.history.pushState({page: 'dashboard' + i}, '', window.location.href);
    }
    
    // Handle back button - completely block it with aggressive push
    window.addEventListener('popstate', function(event) {
        // Immediately push 10 dashboard entries back
        for (let i = 1; i <= 10; i++) {
            window.history.pushState({page: 'dashboard' + i}, '', window.location.href);
        }
    });
    
    // Handle page load/reload
    window.addEventListener('load', function() {
        // Re-establish ultra-strong history buffer on every load
        window.history.replaceState(null, null, window.location.href);
        for (let i = 1; i <= 10; i++) {
            window.history.pushState({page: 'dashboard' + i}, '', window.location.href);
        }
    });
    
    // Handle page show from cache
    window.addEventListener('pageshow', function(event) {
        // Re-establish ultra-strong history buffer on every page show
        window.history.replaceState(null, null, window.location.href);
        for (let i = 1; i <= 10; i++) {
            window.history.pushState({page: 'dashboard' + i}, '', window.location.href);
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
