<?php
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

$order_id = (int) ($_GET['order_id'] ?? 0);
$txn = htmlspecialchars($_GET['txn'] ?? '');

// Fetch order details
$order = null;
if ($order_id) {
    $s = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
    $s->execute([$order_id, $_SESSION['user_id']]);
    $order = $s->fetch();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Successful - FestiVmart</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .payment-status-page {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }

        .status-card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 3rem 2.5rem;
            max-width: 520px;
            width: 100%;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .status-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #5c35d9, #8b5cf6, #a78bfa);
        }

        .status-icon {
            width: 90px;
            height: 90px;
            border-radius: 50%;
            background: linear-gradient(135deg, rgba(92, 53, 217, 0.15), rgba(139, 92, 246, 0.15));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            margin: 0 auto 1.5rem;
            border: 2px solid rgba(139, 92, 246, 0.3);
            animation: pulse-glow 2s ease-in-out infinite;
        }

        @keyframes pulse-glow {

            0%,
            100% {
                box-shadow: 0 0 0 0 rgba(139, 92, 246, 0.3);
            }

            50% {
                box-shadow: 0 0 0 16px rgba(139, 92, 246, 0);
            }
        }

        .status-title {
            font-size: 1.8rem;
            font-weight: 700;
            color: #a78bfa;
            margin-bottom: 0.5rem;
        }

        .detail-grid {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 1.2rem 1.5rem;
            margin: 1.5rem 0;
            text-align: left;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.45rem 0;
            font-size: 0.88rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }

        .detail-row:last-child {
            border-bottom: none;
        }

        .detail-row .label {
            color: var(--text-muted);
        }

        .detail-row .value {
            font-weight: 600;
            color: var(--text);
        }

        .khalti-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            background: linear-gradient(135deg, #5c35d9, #8b5cf6);
            color: #fff;
            padding: 0.25rem 0.8rem;
            border-radius: 20px;
            font-size: 0.78rem;
            font-weight: 600;
            margin-bottom: 1rem;
        }

        .btn-group {
            display: flex;
            gap: 0.8rem;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 1.5rem;
        }
    </style>
</head>

<body>
    <nav class="navbar scrolled">
        <a href="../homepage.php" class="navbar-brand">FestiVmart</a>
        <ul class="nav-links">
            <li><a href="../homepage.php">Home</a></li>
            <li><a href="../customer/shop.php">Shop</a></li>
            <li><a href="../customer/account.php">My Orders</a></li>
            <li><a href="../auth/logout.php" class="logout-btn"><span class="logout-icon">🚪</span> Logout</a></li>
        </ul>
    </nav>

    <div class="payment-status-page">
        <div class="status-card">
            <div class="status-icon">✅</div>
            <div class="khalti-badge">💜 Paid via Khalti</div>
            <div class="status-title">Payment Successful!</div>
            <p class="text-muted" style="margin-bottom:0;">Your order has been confirmed and is now being processed.</p>

            <?php if ($order): ?>
                <div class="detail-grid">
                    <div class="detail-row">
                        <span class="label">Order ID</span>
                        <span class="value">#
                            <?php echo $order['id']; ?>
                        </span>
                    </div>
                    <?php if ($txn): ?>
                        <div class="detail-row">
                            <span class="label">Transaction ID</span>
                            <span class="value" style="font-size:0.78rem;">
                                <?php echo $txn; ?>
                            </span>
                        </div>
                    <?php endif; ?>
                    <div class="detail-row">
                        <span class="label">Amount Paid</span>
                        <span class="value" style="color:#a78bfa;">
                            <?php echo formatPrice($order['total']); ?>
                        </span>
                    </div>
                    <div class="detail-row">
                        <span class="label">Status</span>
                        <span class="value" style="color:#2ecc71;">✅ Processing</span>
                    </div>
                    <div class="detail-row">
                        <span class="label">Delivery Address</span>
                        <span class="value">
                            <?php echo htmlspecialchars($order['delivery_address']); ?>
                        </span>
                    </div>
                </div>
            <?php endif; ?>

            <p class="text-faint" style="font-size:0.8rem;">🎉 Thank you for shopping at FestiVmart! You'll receive your
                festival goodies soon.</p>

            <div class="btn-group">
                <a href="../customer/account.php?tab=orders" class="btn btn-primary">View My Orders</a>
                <a href="../customer/shop.php" class="btn btn-outline">Continue Shopping</a>
                <?php if ($order): ?>
                    <a href="download_bill.php?order_id=<?php echo $order['id']; ?>&txn=<?php echo urlencode($txn); ?>"
                        target="_blank" class="btn btn-outline" style="border-color: #5c35d9; color: #5c35d9;">📄 Download
                        Bill</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
</body>

</html>