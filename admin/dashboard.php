<?php
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$stats = getAdminStats();

// Recent orders
$s = $pdo->query("SELECT o.*, u.full_name, u.phone FROM orders o JOIN users u ON o.user_id = u.id ORDER BY o.created_at DESC LIMIT 5");
$recent_orders = $s->fetchAll();

// Low stock products
$s2 = $pdo->query("SELECT p.name, p.stock, c.name as category, f.name as festival 
                   FROM products p 
                   LEFT JOIN categories c ON p.category_id = c.id 
                   LEFT JOIN festivals f ON p.festival_id = f.id 
                   WHERE p.stock < 10 AND p.is_preorder = 0 
                   ORDER BY p.stock ASC LIMIT 5");
$low_stock = $s2->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - FestiVmart</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <script src="../assets/vendor/chart.min.js"></script>
</head>

<body>

    <!-- Sidebar -->
    <aside class="admin-sidebar" id="sidebar">
        <div class="sidebar-logo">
            FestiVmart
            <small>Admin Portal</small>
        </div>

        <nav class="sidebar-nav">
            <div class="nav-section-title">Menu</div>
            <a href="dashboard.php" class="sidebar-link active"><span class="nav-icon">📊</span> Dashboard</a>
            <a href="products.php" class="sidebar-link"><span class="nav-icon">📦</span> Products</a>
            <a href="orders.php" class="sidebar-link">
                <span class="nav-icon">🛒</span> Orders
                <?php if ($stats['pending_orders'] > 0): ?>
                    <span class="badge-count">
                        <?php echo $stats['pending_orders']; ?>
                    </span>
                <?php endif; ?>
            </a>
            <a href="users.php" class="sidebar-link"><span class="nav-icon">👥</span> Users</a>
            <a href="coupons.php" class="sidebar-link"><span class="nav-icon">🎟️</span> Coupons</a>

            <div class="nav-section-title mt-2">Configuration</div>
            <a href="festival_settings.php" class="sidebar-link"><span class="nav-icon">🎭</span> Festival Settings</a>
        </nav>

        <div class="sidebar-footer">
            <a href="../auth/logout.php" class="sidebar-link logout"><span class="nav-icon">🚪</span> Logout</a>
        </div>
    </aside>

    <!-- Main -->
    <main class="admin-main">
        <header class="admin-topbar">
            <div class="flex items-center gap-1">
                <button id="menu-toggle" class="btn btn-outline btn-sm hidden" style="border:none;">☰</button>
                <h1 class="topbar-title">Dashboard Overview</h1>
            </div>

            <div class="topbar-user">
                <span>Welcome,
                    <?php echo htmlspecialchars($_SESSION['full_name']); ?>
                </span>
                <div class="admin-avatar">A</div>
            </div>
        </header>

        <div class="admin-content">

            <!-- Stats -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-glow stat-icon-sales"></div>
                    <div class="stat-card-icon stat-icon-sales">💰</div>
                    <div class="stat-label">Total Valid Sales</div>
                    <div class="stat-value">
                        <?php echo formatPrice($stats['total_sales']); ?>
                    </div>
                    <span class="stat-badge up">+12%</span>
                </div>

                <div class="stat-card">
                    <div class="stat-glow stat-icon-orders"></div>
                    <div class="stat-card-icon stat-icon-orders">🛒</div>
                    <div class="stat-label">Total Orders</div>
                    <div class="stat-value">
                        <?php echo number_format($stats['total_orders']); ?>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-glow stat-icon-users"></div>
                    <div class="stat-card-icon stat-icon-users">👥</div>
                    <div class="stat-label">Total Customers</div>
                    <div class="stat-value">
                        <?php echo number_format($stats['total_users']); ?>
                    </div>
                    <span class="stat-badge up">+5%</span>
                </div>

                <div class="stat-card">
                    <div class="stat-glow stat-icon-products"></div>
                    <div class="stat-card-icon stat-icon-products">📦</div>
                    <div class="stat-label">Total Products</div>
                    <div class="stat-value">
                        <?php echo number_format($stats['total_products']); ?>
                    </div>
                </div>
            </div>

            <!-- Charts & Tables Row -->
            <div class="grid-2">
                <!-- Sales Chart -->
                <div class="admin-section">
                    <div class="admin-section-header">
                        <h3>Sales Overview</h3>
                    </div>
                    <div class="chart-wrap">
                        <canvas id="salesChart"></canvas>
                    </div>
                </div>

                <!-- Low Stock Alert -->
                <div class="admin-section">
                    <div class="admin-section-header">
                        <h3>⚠️ Low Stock Alerts</h3>
                        <a href="products.php" class="text-muted" style="font-size:0.75rem;">View All</a>
                    </div>
                    <?php if (empty($low_stock)): ?>
                        <div class="text-center p-3 text-muted">All products have sufficient stock.</div>
                    <?php else: ?>
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Festival</th>
                                    <th>Stock</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($low_stock as $ls): ?>
                                    <tr>
                                        <td style="font-weight:600;">
                                            <?php echo htmlspecialchars($ls['name']); ?>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($ls['festival']); ?>
                                        </td>
                                        <td>
                                            <span class="inactive-badge">
                                                <?php echo $ls['stock']; ?> left
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Orders -->
            <div class="admin-section mt-1">
                <div class="admin-section-header">
                    <h3>Recent Incoming Orders</h3>
                    <a href="orders.php" class="btn btn-outline btn-sm">View All Orders</a>
                </div>

                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Customer</th>
                            <th>Date</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_orders as $ord): ?>
                            <tr>
                                <td style="font-family:'Poppins',sans-serif; font-weight:600;">#FV-
                                    <?php echo str_pad($ord['id'], 5, '0', STR_PAD_LEFT); ?>
                                </td>
                                <td>
                                    <div>
                                        <?php echo htmlspecialchars($ord['full_name']); ?>
                                    </div>
                                    <div class="text-muted" style="font-size:0.75rem;">
                                        <?php echo htmlspecialchars($ord['phone']); ?>
                                    </div>
                                </td>
                                <td>
                                    <?php echo date('M d, Y', strtotime($ord['created_at'])); ?>
                                </td>
                                <td style="font-weight:600;">
                                    <?php echo formatPrice($ord['total']); ?>
                                </td>
                                <td><span class="status-badge status-<?php echo $ord['status']; ?>">
                                        <?php echo $ord['status']; ?>
                                    </span></td>
                                <td>
                                    <a href="orders.php?view=<?php echo $ord['id']; ?>"
                                        class="btn btn-outline btn-xs">Manage</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (empty($recent_orders)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-3">No orders placed yet.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </div>
    </main>

    <script>
        // Toggle Mobile Sidebar
        const menuBtn = document.getElementById('menu-toggle');
        const sidebar = document.getElementById('sidebar');
        if (window.innerWidth <= 900) menuBtn.classList.remove('hidden');

        menuBtn.addEventListener('click', () => {
            sidebar.classList.toggle('open');
        });

        // Initialize Chart
        const ctx = document.getElementById('salesChart').getContext('2d');

        let gradient = ctx.createLinearGradient(0, 0, 0, 300);
        gradient.addColorStop(0, 'rgba(108, 92, 231, 0.5)');
        gradient.addColorStop(1, 'rgba(108, 92, 231, 0.0)');

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                datasets: [{
                    label: 'Revenue (Rs.)',
                    data: [15000, 22000, <?php echo $stats['total_sales'] > 0 ? $stats['total_sales'] * 0.4 : 18000; ?>,
                        40000, 35000, <?php echo $stats['total_sales'] > 0 ? $stats['total_sales'] : 56000; ?>],
                    borderColor: '#6C5CE7',
                    backgroundColor: gradient,
                    borderWidth: 3,
                    pointBackgroundColor: '#FFD700',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    x: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: 'rgba(232,232,240,0.55)' } },
                    y: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: 'rgba(232,232,240,0.55)' }, beginAtZero: true }
                }
            }
        });
    </script>
</body>

</html>