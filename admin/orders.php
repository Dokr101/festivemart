<?php
require_once __DIR__.'/../includes/functions.php';
requireAdmin();

$success = '';

// Handle Status Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_status'])) {
        $order_id = (int)$_POST['order_id'];
        $status = $_POST['status'];
        
        // Validate enum
        $validStatuses = ['Pending', 'Processing', 'Shipped', 'Delivered', 'Cancelled'];
        if (in_array($status, $validStatuses)) {
            $s = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
            if ($s->execute([$status, $order_id])) {
                $success = "Order #FV-" . str_pad($order_id, 5, '0', STR_PAD_LEFT) . " status updated to $status.";
                
                // If cancelled, restore stock
                if ($status === 'Cancelled') {
                    $items = $pdo->prepare("SELECT product_id, quantity FROM order_items WHERE order_id = ?");
                    $items->execute([$order_id]);
                    $restore = $pdo->prepare("UPDATE products SET stock = stock + ? WHERE id = ?");
                    foreach ($items->fetchAll() as $item) {
                        $restore->execute([$item['quantity'], $item['product_id']]);
                    }
                }
            }
        }
    }
}

// Fetch Orders
$filter = $_GET['status'] ?? 'all';
$sql = "SELECT o.*, u.full_name, u.email, u.phone 
        FROM orders o 
        JOIN users u ON o.user_id = u.id";
if ($filter !== 'all') {
    $sql .= " WHERE o.status = :status";
}
$sql .= " ORDER BY o.created_at DESC";

$stmt = $pdo->prepare($sql);
if ($filter !== 'all') $stmt->execute([':status' => $filter]);
else $stmt->execute();
$orders = $stmt->fetchAll();

// View single order details logic
$view_order = null;
$order_items = [];
if (isset($_GET['view'])) {
    $oid = (int)$_GET['view'];
    $s = $pdo->prepare("SELECT o.*, u.full_name, u.email, u.phone FROM orders o JOIN users u ON o.user_id = u.id WHERE o.id = ?");
    $s->execute([$oid]);
    $view_order = $s->fetch();
    if ($view_order) {
        $si = $pdo->prepare("SELECT oi.*, p.name FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ?");
        $si->execute([$oid]);
        $order_items = $si->fetchAll();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Orders - FestiVmart Admin</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        .filter-tabs { display:flex; gap:10px; margin-bottom:1.5rem; border-bottom:1px solid var(--admin-border); padding-bottom:10px; overflow-x:auto; }
        .filter-tab { padding:6px 16px; border-radius:999px; font-size:0.85rem; font-weight:600; color:var(--text-muted); background:var(--admin-card); border:1px solid var(--admin-border); transition:all .2s; }
        .filter-tab.active { background:var(--admin-primary); color:#fff; border-color:var(--admin-primary); }
    </style>
</head>
<body>

    <aside class="admin-sidebar" id="sidebar">
        <div class="sidebar-logo">FestiVmart<small>Admin Portal</small></div>
        <nav class="sidebar-nav">
            <div class="nav-section-title">Menu</div>
            <a href="dashboard.php" class="sidebar-link"><span class="nav-icon">📊</span> Dashboard</a>
            <a href="products.php" class="sidebar-link"><span class="nav-icon">📦</span> Products</a>
            <a href="orders.php" class="sidebar-link active"><span class="nav-icon">🛒</span> Orders</a>
            <a href="users.php" class="sidebar-link"><span class="nav-icon">👥</span> Users</a>
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
                <h1 class="topbar-title">Manage Orders</h1>
            </div>
            <div class="topbar-user">
                <span><?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
                <div class="admin-avatar">A</div>
            </div>
        </header>

        <div class="admin-content">
            <?php if($success): ?><div class="alert alert-success">✅ <?php echo htmlspecialchars($success); ?></div><?php endif; ?>
            
            <?php if($view_order): ?>
                <!-- Order Details View -->
                <div class="mb-3 flex items-center gap-1">
                    <a href="orders.php" class="btn btn-outline btn-sm">← Back to Orders</a>
                    <h2 class="ml-2">Order #FV-<?php echo str_pad($view_order['id'], 5, '0', STR_PAD_LEFT); ?></h2>
                    <span class="status-badge status-<?php echo $view_order['status']; ?>"><?php echo $view_order['status']; ?></span>
                </div>
                
                <div class="grid-2">
                    <div class="admin-section mb-0">
                        <div class="admin-section-header">
                            <h3>Customer & Delivery</h3>
                        </div>
                        <div style="padding:1.5rem;">
                            <div class="mb-1"><strong>Name:</strong> <?php echo htmlspecialchars($view_order['full_name']); ?></div>
                            <div class="mb-1"><strong>Phone:</strong> <?php echo htmlspecialchars($view_order['phone']); ?></div>
                            <div class="mb-1"><strong>Email:</strong> <?php echo htmlspecialchars($view_order['email']); ?></div>
                            <div class="mb-1 mt-2"><strong>Delivery Address:</strong></div>
                            <div class="text-muted"><?php echo nl2br(htmlspecialchars($view_order['delivery_address'])); ?></div>
                        </div>
                    </div>
                    
                    <div class="admin-section mb-0">
                        <div class="admin-section-header">
                            <h3>Update Status</h3>
                        </div>
                        <div style="padding:1.5rem;">
                            <form action="orders.php?view=<?php echo $view_order['id']; ?>" method="POST" class="flex items-center gap-1">
                                <input type="hidden" name="order_id" value="<?php echo $view_order['id']; ?>">
                                <select name="status" class="form-control" style="max-width:200px;">
                                    <option value="Pending" <?php echo $view_order['status']==='Pending'?'selected':''; ?>>Pending</option>
                                    <option value="Processing" <?php echo $view_order['status']==='Processing'?'selected':''; ?>>Processing</option>
                                    <option value="Shipped" <?php echo $view_order['status']==='Shipped'?'selected':''; ?>>Shipped</option>
                                    <option value="Delivered" <?php echo $view_order['status']==='Delivered'?'selected':''; ?>>Delivered</option>
                                    <option value="Cancelled" <?php echo $view_order['status']==='Cancelled'?'selected':''; ?>>Cancelled</option>
                                </select>
                                <button type="submit" name="update_status" class="btn btn-primary">Update</button>
                            </form>
                            <div class="text-muted mt-2" style="font-size:0.8rem;">
                                Note: Cancelling an order will automatically restore stock for the included products.
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="admin-section mt-2">
                    <div class="admin-section-header"><h3>Order Items</h3></div>
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Price</th>
                                <th>Qty</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($order_items as $itm): ?>
                            <tr>
                                <td style="font-weight:600;"><?php echo htmlspecialchars($itm['name']); ?></td>
                                <td><?php echo formatPrice($itm['price']); ?></td>
                                <td><?php echo $itm['quantity']; ?></td>
                                <td><?php echo formatPrice($itm['price'] * $itm['quantity']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div style="padding:1.5rem; text-align:right; border-top:1px solid var(--admin-border);">
                        <div class="mb-1 text-muted">Subtotal: <?php echo formatPrice($view_order['subtotal']); ?></div>
                        <div class="mb-1 text-muted">Shipping: <?php echo formatPrice($view_order['shipping']); ?></div>
                        <?php if($view_order['discount'] > 0): ?>
                        <div class="mb-1 text-muted" style="color:var(--admin-success);">Discount: -<?php echo formatPrice($view_order['discount']); ?></div>
                        <?php endif; ?>
                        <div style="font-size:1.2rem; font-weight:700; color:var(--admin-accent); margin-top:10px;">
                            Total: <?php echo formatPrice($view_order['total']); ?>
                        </div>
                    </div>
                </div>
            
            <?php else: ?>
                <!-- Orders List -->
                <div class="filter-tabs">
                    <a href="orders.php" class="filter-tab <?php echo $filter==='all'?'active':''; ?>">All Orders</a>
                    <a href="orders.php?status=Pending" class="filter-tab <?php echo $filter==='Pending'?'active':''; ?>">Pending</a>
                    <a href="orders.php?status=Processing" class="filter-tab <?php echo $filter==='Processing'?'active':''; ?>">Processing</a>
                    <a href="orders.php?status=Shipped" class="filter-tab <?php echo $filter==='Shipped'?'active':''; ?>">Shipped</a>
                    <a href="orders.php?status=Delivered" class="filter-tab <?php echo $filter==='Delivered'?'active':''; ?>">Delivered</a>
                    <a href="orders.php?status=Cancelled" class="filter-tab <?php echo $filter==='Cancelled'?'active':''; ?>">Cancelled</a>
                </div>

                <div class="admin-section">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Customer Info</th>
                                <th>Date</th>
                                <th>Total Amount</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($orders as $o): ?>
                            <tr>
                                <td style="font-weight:600;">#FV-<?php echo str_pad($o['id'], 5, '0', STR_PAD_LEFT); ?></td>
                                <td>
                                    <div><?php echo htmlspecialchars($o['full_name']); ?></div>
                                    <div class="text-muted" style="font-size:0.75rem;"><?php echo htmlspecialchars($o['phone']); ?></div>
                                </td>
                                <td><?php echo date('M d, Y h:i A', strtotime($o['created_at'])); ?></td>
                                <td style="font-weight:600;"><?php echo formatPrice($o['total']); ?></td>
                                <td><span class="status-badge status-<?php echo $o['status']; ?>"><?php echo $o['status']; ?></span></td>
                                <td>
                                    <div class="flex gap-1">
                                        <a href="orders.php?view=<?php echo $o['id']; ?>" class="btn btn-outline btn-xs">View/Edit</a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if(empty($orders)): ?>
                            <tr><td colspan="6" class="text-center py-3 text-muted">No orders found for this filter.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            
        </div>
    </main>

    <script>
        const menuBtn = document.getElementById('menu-toggle');
        const sidebar = document.getElementById('sidebar');
        if(window.innerWidth <= 900) menuBtn.classList.remove('hidden');
        menuBtn.addEventListener('click', () => sidebar.classList.toggle('open'));
    </script>
</body>
</html>
