<?php
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $name = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);

        $s = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, phone = ? WHERE id = ?");
        if ($s->execute([$name, $email, $phone, $user_id])) {
            $success = "Profile updated successfully.";
            $_SESSION['full_name'] = $name;
        } else {
            $error = "Failed to update profile. Email/phone might be in use.";
        }
    }
}

// Fetch user data
$s = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$s->execute([$user_id]);
$user = $s->fetch();

// Fetch orders
$s2 = $pdo->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC");
$s2->execute([$user_id]);
$orders = $s2->fetchAll();

$tab = $_GET['tab'] ?? 'profile';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Account - FestiVmart</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body>

    <nav class="navbar scrolled">
        <a href="../homepage.php" class="navbar-brand">FestiVmart</a>
        <div class="hamburger"><span></span><span></span><span></span></div>
        <ul class="nav-links">
            <li><a href="../homepage.php">Home</a></li>
            <li><a href="shop.php">Shop</a></li>
            <li><a href="account.php" class="active">Dashboard</a></li>
            <li><a href="cart.php" class="cart-badge">Cart <span class="cart-count">
                        <?php echo getCartCount(); ?>
                    </span></a></li>
            <li><a href="../auth/logout.php" class="logout-btn"><span class="logout-icon">🚪</span> Logout</a></li>
        </ul>
    </nav>

    <div class="page-wrapper container section">

        <div class="section-header" style="text-align: left; margin-bottom: 2rem;">
            <h2>Hello,
                <?php echo htmlspecialchars(explode(' ', $user['full_name'])[0]); ?>!
            </h2>
            <p>Manage your orders, addresses, and account details.</p>
        </div>

        <?php if ($success || (isset($_GET['msg']) && $_GET['msg'] === 'order_success')): ?>
            <div class="alert alert-success">✅
                <?php echo $success ?: 'Order placed successfully! We will prepare it right away.'; ?>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error">❌
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <div class="account-grid">

            <!-- Sidebar -->
            <aside class="account-sidebar">
                <div class="account-nav-link <?php echo $tab === 'profile' ? 'active' : ''; ?>"
                    data-target="panel-profile">
                    <span>👤</span> Profile Settings
                </div>
                <div class="account-nav-link <?php echo $tab === 'orders' ? 'active' : ''; ?>"
                    data-target="panel-orders">
                    <span>📦</span> Order History
                </div>
            </aside>

            <!-- Main Details -->
            <main>
                <!-- Profile Panel -->
                <div id="panel-profile" class="account-panel <?php echo $tab === 'profile' ? 'active' : ''; ?>">
                    <div class="card" style="padding: 3.5rem;">
                        <h3 class="text-center mb-4" style="color: var(--fest-primary); font-size: 1.5rem;">Personal
                            Information</h3>
                        <form action="account.php?tab=profile" method="POST">
                            <input type="hidden" name="update_profile" value="1">

                            <div class="grid-2 mb-3">
                                <div class="form-group mb-0">
                                    <label class="form-label">Full Name</label>
                                    <input type="text" name="full_name" class="form-control"
                                        value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                                </div>
                                <div class="form-group mb-0">
                                    <label class="form-label">Username</label>
                                    <input type="text" class="form-control"
                                        value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
                                </div>
                            </div>

                            <div class="grid-2 mb-4">
                                <div class="form-group mb-0">
                                    <label class="form-label">Email Address</label>
                                    <input type="email" name="email" class="form-control"
                                        value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                </div>
                                <div class="form-group mb-0">
                                    <label class="form-label">Phone Number</label>
                                    <input type="text" name="phone" class="form-control"
                                        value="<?php echo htmlspecialchars($user['phone']); ?>" required>
                                </div>
                            </div>

                            <div class="text-center mt-2">
                                <button type="submit" class="btn btn-primary" style="padding: 0.8rem 3.5rem;">Save
                                    Changes</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Orders Panel -->
                <div id="panel-orders" class="account-panel <?php echo $tab === 'orders' ? 'active' : ''; ?>">
                    <div class="card p-0">
                        <div style="padding: 1.5rem; border-bottom: 1px solid var(--border);">
                            <h3 class="mb-0">Your Recent Orders</h3>
                        </div>

                        <?php if (empty($orders)): ?>
                            <div style="padding: 3rem; text-align: center;">
                                <div class="emoji-lg mb-1">🎁</div>
                                <p class="text-muted">You haven't placed any orders yet.</p>
                                <a href="shop.php" class="btn btn-outline mt-2">Start Shopping</a>
                            </div>
                        <?php else: ?>
                            <?php foreach ($orders as $order): ?>
                                <div class="order-row">
                                    <div class="flex flex-between items-center mb-1">
                                        <div style="font-family: 'Poppins',sans-serif; font-weight:600;">
                                            Order #FV-
                                            <?php echo str_pad($order['id'], 5, '0', STR_PAD_LEFT); ?>
                                        </div>
                                        <span class="order-status <?php echo htmlspecialchars($order['status']); ?>">
                                            <?php echo htmlspecialchars($order['status']); ?>
                                        </span>
                                    </div>
                                    <div class="flex flex-between items-center text-muted" style="font-size:0.85rem;">
                                        <div>
                                            📅
                                            <?php echo date('M d, Y h:i A', strtotime($order['created_at'])); ?> <br>
                                            💵
                                            <?php echo formatPrice($order['total']); ?> (
                                            <?php echo $order['payment_method']; ?>)
                                        </div>
                                        <div style="text-align:right;">
                                            📍
                                            <?php echo htmlspecialchars(substr($order['delivery_address'], 0, 30)) . '...'; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

            </main>

        </div>
    </div>

    <script src="../assets/js/main.js"></script>
</body>

</html>