<?php
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $id = (int) $_POST['coupon_id'];
        $code = strtoupper(trim($_POST['code']));
        $discount = (float) $_POST['discount_percent'];
        $min = (float) $_POST['min_spend'];
        $expires = $_POST['expires_at'];
        $max_uses = (int) $_POST['max_uses'];
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        if ($discount <= 0) {
            $error = "Discount percent must be greater than 0.";
        } elseif ($min < 0) {
            $error = "Minimum spend cannot be negative.";
        } else {
            // Simple code check
            $stmtCode = $pdo->prepare("SELECT id FROM coupons WHERE code = ? AND id != ?");
            $stmtCode->execute([$code, $id]);
            if ($stmtCode->fetch()) {
                $error = "Coupon code '$code' already exists.";
            } else {
                if ($action === 'add') {
                    $s = $pdo->prepare("INSERT INTO coupons (code, discount_percent, expires_at, min_spend, max_uses, is_active) VALUES (?, ?, ?, ?, ?, ?)");
                    $s->execute([$code, $discount, $expires, $min, $max_uses, $is_active]);
                    $success = "Coupon created.";
                } else {
                    $s = $pdo->prepare("UPDATE coupons SET code=?, discount_percent=?, expires_at=?, min_spend=?, max_uses=?, is_active=? WHERE id=?");
                    $s->execute([$code, $discount, $expires, $min, $max_uses, $is_active, $id]);
                    $success = "Coupon updated.";
                }
            }
        }
    } elseif ($action === 'delete') {
        $id = (int) $_POST['coupon_id'];
        $s = $pdo->prepare("DELETE FROM coupons WHERE id=?");
        $s->execute([$id]);
        $success = "Coupon deleted.";
    }
}

$stmt = $pdo->query("SELECT * FROM coupons ORDER BY created_at DESC");
$coupons = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Coupons - FestiVmart</title>
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
            <a href="users.php" class="sidebar-link"><span class="nav-icon">👥</span> Users</a>
            <a href="coupons.php" class="sidebar-link active"><span class="nav-icon">🎟️</span> Coupons</a>
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
                <h1 class="topbar-title">Manage Coupons</h1>
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
            <?php if ($error): ?>
                <div class="alert alert-error">❌
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <div class="toolbar flex-between">
                <div>Manage discount codes for festivals and promotions.</div>
                <button class="btn btn-primary" onclick="openModal('addModal')">+ Create Coupon</button>
            </div>

            <div class="admin-section">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Discount</th>
                            <th>Min Spend</th>
                            <th>Usages</th>
                            <th>Expires</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($coupons as $c):
                            $is_expired = strtotime($c['expires_at']) < time();
                            ?>
                            <tr>
                                <td
                                    style="font-weight:700; font-family:monospace; font-size:1.1rem; color:var(--admin-accent);">
                                    <?php echo htmlspecialchars($c['code']); ?>
                                </td>
                                <td>
                                    <?php echo (float) $c['discount_percent']; ?>%
                                </td>
                                <td>
                                    <?php echo formatPrice($c['min_spend']); ?>
                                </td>
                                <td>
                                    <?php echo $c['used_count']; ?> /
                                    <?php echo $c['max_uses']; ?>
                                </td>
                                <td>
                                    <div>
                                        <?php echo date('M d, Y', strtotime($c['expires_at'])); ?>
                                    </div>
                                    <?php if ($is_expired): ?><span class="text-error"
                                            style="font-size:0.75rem;">Expired</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($c['is_active'] && !$is_expired && $c['used_count'] < $c['max_uses']): ?>
                                        <span class="active-badge">Active</span>
                                    <?php else: ?>
                                        <span class="inactive-badge">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="flex gap-1">
                                        <button class="btn btn-outline btn-xs"
                                            onclick='editCoupon(<?php echo json_encode($c); ?>)'>Edit</button>
                                        <form action="coupons.php" method="POST"
                                            onsubmit="return confirm('Delete coupon?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="coupon_id" value="<?php echo $c['id']; ?>">
                                            <button type="submit" class="btn btn-danger btn-xs">Del</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($coupons)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-3 text-muted">No coupons created yet.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- Modal Form -->
    <div class="modal-overlay" id="addModal">
        <div class="modal-box" style="max-width: 500px;">
            <div class="modal-header">
                <h3 class="modal-title" id="modalTitle">Create Coupon</h3>
                <span class="modal-close" onclick="closeModal('addModal')">&times;</span>
            </div>

            <form action="coupons.php" method="POST" id="couponForm">
                <input type="hidden" name="action" value="add" id="formAction">
                <input type="hidden" name="coupon_id" value="0" id="cId">

                <div class="form-group">
                    <label class="form-label">Coupon Code</label>
                    <input type="text" name="code" id="cCode" class="form-control" style="text-transform:uppercase;"
                        required placeholder="E.g., DASHAIN20">
                </div>

                <div class="grid-2 mb-2">
                    <div class="form-group mb-0">
                        <label class="form-label">Discount (%)</label>
                        <input type="number" step="0.01" min="0.01" name="discount_percent" id="cDisc"
                            class="form-control" required placeholder="E.g., 10">
                    </div>
                    <div class="form-group mb-0">
                        <label class="form-label">Min Spend (Rs.)</label>
                        <input type="number" step="0.01" min="0" name="min_spend" id="cMin" class="form-control"
                            required placeholder="E.g., 1000">
                    </div>
                </div>

                <div class="grid-2 mb-3">
                    <div class="form-group mb-0">
                        <label class="form-label">Max Uses</label>
                        <input type="number" name="max_uses" id="cMax" class="form-control" required value="100">
                    </div>
                    <div class="form-group mb-0">
                        <label class="form-label">Expiry Date</label>
                        <input type="datetime-local" name="expires_at" id="cExp" class="form-control" required>
                    </div>
                </div>

                <div class="form-group mb-3">
                    <label class="flex items-center gap-1"
                        style="cursor:pointer; color:var(--text-muted); font-size:0.9rem;">
                        <input type="checkbox" name="is_active" id="cActive" value="1" checked>
                        Coupon is Active and Usable
                    </label>
                </div>

                <div class="flex flex-between mt-3">
                    <button type="button" class="btn btn-outline" onclick="closeModal('addModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Coupon</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.getElementById('menu-toggle').addEventListener('click', () => document.getElementById('sidebar').classList.toggle('open'));
        if (window.innerWidth <= 900) document.getElementById('menu-toggle').classList.remove('hidden');

        function openModal(id) { document.getElementById(id).classList.add('open'); }
        function closeModal(id) {
            document.getElementById(id).classList.remove('open');
            if (id === 'addModal') {
                document.getElementById('couponForm').reset();
                document.getElementById('formAction').value = 'add';
                document.getElementById('modalTitle').innerText = 'Create Coupon';
            }
        }

        function editCoupon(c) {
            document.getElementById('formAction').value = 'edit';
            document.getElementById('cId').value = c.id;
            document.getElementById('cCode').value = c.code;
            document.getElementById('cDisc').value = c.discount_percent;
            document.getElementById('cMin').value = c.min_spend;
            document.getElementById('cMax').value = c.max_uses;
            // Format datetime-local
            document.getElementById('cExp').value = c.expires_at.replace(' ', 'T');
            document.getElementById('cActive').checked = c.is_active == 1;

            document.getElementById('modalTitle').innerText = 'Edit Coupon';
            openModal('addModal');
        }
    </script>
</body>

</html>