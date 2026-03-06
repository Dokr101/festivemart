<?php
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

$order_id = (int) ($_GET['order_id'] ?? 0);
$reason = htmlspecialchars($_GET['reason'] ?? 'Payment was not completed');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Failed - FestiVmart</title>
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
            max-width: 500px;
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
            background: linear-gradient(90deg, #e74c3c, #c0392b);
        }

        .status-icon {
            width: 90px;
            height: 90px;
            border-radius: 50%;
            background: rgba(231, 76, 60, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            margin: 0 auto 1.5rem;
            border: 2px solid rgba(231, 76, 60, 0.3);
        }

        .status-title {
            font-size: 1.8rem;
            font-weight: 700;
            color: #e74c3c;
            margin-bottom: 0.5rem;
        }

        .reason-box {
            background: rgba(231, 76, 60, 0.08);
            border: 1px solid rgba(231, 76, 60, 0.2);
            border-radius: 10px;
            padding: 1rem 1.2rem;
            margin: 1.2rem 0;
            font-size: 0.88rem;
            color: var(--text-muted);
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
        </ul>
    </nav>

    <div class="payment-status-page">
        <div class="status-card">
            <div class="status-icon">❌</div>
            <div class="status-title">Payment Failed</div>
            <p class="text-muted">Unfortunately, your payment could not be processed.</p>

            <div class="reason-box">
                <strong>Reason:</strong>
                <?php echo $reason; ?>
                <?php if ($order_id): ?>
                    <br><small>Order #
                        <?php echo $order_id; ?> has been cancelled.
                    </small>
                <?php endif; ?>
            </div>

            <p class="text-faint" style="font-size:0.82rem;">
                Don't worry — your cart items are still saved. You can try again or choose a different payment method.
            </p>

            <div class="btn-group">
                <a href="../customer/cart.php" class="btn btn-primary">Return to Cart</a>
                <a href="../customer/shop.php" class="btn btn-outline">Browse Shop</a>
            </div>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
</body>

</html>