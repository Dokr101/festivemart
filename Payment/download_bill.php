<?php
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

$order_id = (int) ($_GET['order_id'] ?? 0);
$txn = htmlspecialchars($_GET['txn'] ?? '');

$order = null;
if ($order_id) {
    $s = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
    $s->execute([$order_id, $_SESSION['user_id']]);
    $order = $s->fetch();
}

if (!$order) {
    die("Order not found.");
}

$date = date('F j, Y, g:i a', strtotime($order['created_at']));
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Bill - Order #<?php echo $order['id']; ?></title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');

        body {
            font-family: 'Inter', sans-serif;
            color: #333;
            background: #f4f4f9;
            margin: 0;
            padding: 0;
        }

        .bill-container {
            max-width: 750px;
            margin: 3rem auto;
            background: #fff;
            padding: 3.5rem;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            border: 1px solid #eaeaea;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 2rem;
            border-bottom: 2px solid #5c35d9;
            margin-bottom: 2.5rem;
        }

        .brand {
            font-size: 2.2rem;
            font-weight: 800;
            color: #5c35d9;
            margin: 0;
            letter-spacing: -0.5px;
        }

        .brand-subtitle {
            font-size: 0.85rem;
            color: #777;
            margin-top: 4px;
            font-weight: 500;
        }

        .receipt-info {
            text-align: right;
        }

        .bill-title {
            font-size: 1.4rem;
            font-weight: 700;
            color: #1a1a1a;
            margin: 0 0 6px 0;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .date {
            font-size: 0.85rem;
            color: #888;
        }

        .details-section {
            background: #fafafa;
            border: 1px solid #f0f0f0;
            border-radius: 12px;
            padding: 2.2rem;
            margin-bottom: 2.5rem;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1.2rem;
            font-size: 1rem;
        }

        .detail-row:last-of-type {
            margin-bottom: 0;
        }

        .label {
            color: #666;
            font-weight: 500;
            min-width: 140px;
        }

        .value {
            font-weight: 600;
            color: #111;
            text-align: right;
            flex: 1;
        }

        .address-value {
            line-height: 1.6;
            max-width: 320px;
        }

        .total-section {
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 2px dashed #ddd;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .total-label {
            font-size: 1.15rem;
            font-weight: 700;
            color: #333;
        }

        .total-value {
            font-size: 1.6rem;
            font-weight: 800;
            color: #5c35d9;
        }

        .footer {
            text-align: center;
            margin-top: 2rem;
            color: #999;
            font-size: 0.82rem;
            line-height: 1.5;
        }

        @media print {
            body {
                background: white;
                margin: 0;
                padding: 1.5cm;
            }

            .bill-container {
                box-shadow: none;
                border: none;
                margin: 0;
                padding: 0;
                max-width: 100%;
            }

            .details-section {
                background: #fff !important;
                border: 1px solid #ddd;
            }

            @page {
                margin: 0;
            }
        }
    </style>
</head>

<body onload="window.print();">
    <div class="bill-container">
        <div class="header">
            <div>
                <h1 class="brand">FestiVmart</h1>
                <div class="brand-subtitle">Your Ultimate Festival Shopping Destination</div>
            </div>
            <div class="receipt-info">
                <h2 class="bill-title">Payment Receipt</h2>
                <div class="date">Date: <?php echo $date; ?></div>
            </div>
        </div>

        <div class="details-section">
            <div class="detail-row">
                <span class="label">Order ID</span>
                <span class="value">#<?php echo $order['id']; ?></span>
            </div>

            <?php if ($txn): ?>
                <div class="detail-row">
                    <span class="label">Transaction ID</span>
                    <span class="value"><?php echo $txn; ?></span>
                </div>
            <?php endif; ?>

            <div class="detail-row">
                <span class="label">Delivery Address</span>
                <span class="value address-value">
                    <?php echo nl2br(htmlspecialchars($order['delivery_address'])); ?>
                </span>
            </div>

            <div class="total-section">
                <span class="total-label">Total Amount Paid</span>
                <span class="total-value"><?php echo formatPrice($order['total']); ?></span>
            </div>
        </div>

        <div class="footer">
            Thank you for shopping at <b>FestiVmart!</b><br>
            If you have any questions or concerns regarding this receipt,<br> please reach out to our support team.
        </div>
    </div>
</body>

</html>