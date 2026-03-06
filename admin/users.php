<?php
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$success = '';

// Fetch users with order stats
$sql = "SELECT u.id, u.username, u.full_name, u.email, u.phone, u.role, u.created_at,
        COUNT(o.id) as total_orders,
        COALESCE(SUM(o.total), 0) as lifetime_spent
        FROM users u
        LEFT JOIN orders o ON u.id = o.user_id AND o.status != 'Cancelled'
        GROUP BY u.id
        ORDER BY u.created_at DESC";

$users = $pdo->query($sql)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - FestiVmart</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>

<body>

    <aside class="admin-sidebar" id="sidebar">
        <div class="sidebar-logo">FestiVmart<small>Admin Portal</small></div>
        <nav class="sidebar-nav">
            <div class="nav-section-title">Menu</div>
            <a href="dashboard.php" class="sidebar-link"><span class="nav-icon">📊</span> Dashboard</a>
            <a href="products.php" class="sidebar-link"><span class="nav-icon">📦</span> Products</a>
            <a href="orders.php" class="sidebar-link"><span class="nav-icon">🛒</span> Orders</a>
            <a href="users.php" class="sidebar-link active"><span class="nav-icon">👥</span> Users</a>
            <a href="coupons.php" class="sidebar-link"><span class="nav-icon">🎟️</span> Coupons</a>
            <div class="nav-section-title mt-2">Configuration</div>
            <a href="festival_settings.php" class="sidebar-link"><span class="nav-icon">🎭</span> Festival Settings</a>
        </nav>
        <div class="sidebar-footer">
            <a href="../auth/logout.php" class="sidebar-link logout"><span class="nav-icon">🚪</span> Logout</a>
        </div>
    </aside>

    <main class="admin-main">
        <header class="admin-topbar">
            <div class="flex items-center gap-1">
                <button id="menu-toggle" class="btn btn-outline btn-sm hidden" style="border:none;">☰</button>
                <h1 class="topbar-title">Manage Users</h1>
            </div>
            <div class="topbar-user">
                <span>
                    <?php echo htmlspecialchars($_SESSION['full_name']); ?>
                </span>
                <div class="admin-avatar">A</div>
            </div>
        </header>

        <div class="admin-content">
            <?php if ($success): ?>
                <div class="alert alert-success">✅
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <div class="admin-section">
                <div class="admin-section-header">
                    <h3>All Registered Users</h3>
                </div>

                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>User Details</th>
                            <th>Contact Info</th>
                            <th>Role</th>
                            <th>Orders</th>
                            <th>Total Spent</th>
                            <th>Joined</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td>
                                    <div style="font-weight:600;">
                                        <?php echo htmlspecialchars($user['full_name']); ?>
                                    </div>
                                    <div class="text-muted" style="font-size:0.75rem;">@
                                        <?php echo htmlspecialchars($user['username']); ?>
                                    </div>
                                </td>
                                <td>
                                    <div>
                                        <?php echo htmlspecialchars($user['email']); ?>
                                    </div>
                                    <div class="text-muted" style="font-size:0.75rem;">
                                        <?php echo htmlspecialchars($user['phone']); ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($user['role'] === 'admin'): ?>
                                        <span class="status-badge"
                                            style="background:rgba(108,92,231,0.15); color:var(--admin-primary);">Admin</span>
                                    <?php else: ?>
                                        <span class="status-badge"
                                            style="background:rgba(255,255,255,0.05); color:var(--text-muted);">Customer</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo $user['total_orders']; ?>
                                </td>
                                <td style="font-weight:600; color:var(--admin-accent);">
                                    <?php echo formatPrice($user['lifetime_spent']); ?>
                                </td>
                                <td class="text-muted">
                                    <?php echo date('M d, Y', strtotime($user['created_at'])); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        </div>
    </main>

    <script>
        document.getElementById('menu-toggle').addEventListener('click', () => document.getElementById('sidebar').classList.toggle('open'));
        if (window.innerWidth <= 900) document.getElementById('menu-toggle').classList.remove('hidden');
    </script>
</body>

</html>