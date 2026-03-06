<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/KhaltiHelper.php';

requireLogin();

$user_id = $_SESSION['user_id'];

// Khalti sends these as GET params on redirect
$pidx = $_GET['pidx'] ?? '';
$status = $_GET['status'] ?? '';
$transaction_id = $_GET['transaction_id'] ?? '';
$tidx = $_GET['tidx'] ?? '';
$amount = (int) ($_GET['amount'] ?? 0);

// Retrieve order_id from session
$order_id = $_SESSION['khalti_order_id'] ?? null;

if (empty($pidx) || empty($order_id)) {
    header('Location: ../customer/cart.php?err=invalid_callback');
    exit;
}

// Server-side verification
$verification = khaltiVerify($pidx);

if ($verification['success'] && $verification['status'] === 'Completed') {
    // Mark order as Processing (paid)
    $pdo->prepare("UPDATE orders SET status = 'Processing' WHERE id = ? AND user_id = ?")
        ->execute([$order_id, $user_id]);

    // Update khalti_transactions record
    $pdo->prepare("UPDATE khalti_transactions SET transaction_id = ?, status = 'Completed', response_data = ? WHERE order_id = ? AND pidx = ?")
        ->execute([
            $verification['transaction_id'],
            json_encode($verification['raw']),
            $order_id,
            $pidx
        ]);

    // Increment coupon used_count if a coupon was used
    $pdo->prepare("UPDATE coupons c JOIN orders o ON c.id = o.coupon_id SET c.used_count = c.used_count + 1 WHERE o.id = ?")
        ->execute([$order_id]);

    // Clear the cart
    $pdo->prepare("DELETE FROM cart WHERE user_id = ?")->execute([$user_id]);

    // Clear coupon session
    unset($_SESSION['coupon_id'], $_SESSION['coupon_code'], $_SESSION['coupon_discount'], $_SESSION['khalti_order_id']);

    // Redirect to success
    header('Location: success.php?order_id=' . $order_id . '&txn=' . urlencode($verification['transaction_id']));
    exit;

} else {
    // Payment failed / cancelled
    $pdo->prepare("UPDATE orders SET status = 'Cancelled' WHERE id = ? AND user_id = ?")
        ->execute([$order_id, $user_id]);

    // Restore stock
    $items = $pdo->prepare("SELECT product_id, quantity FROM order_items WHERE order_id = ?");
    $items->execute([$order_id]);
    foreach ($items->fetchAll() as $item) {
        $pdo->prepare("UPDATE products SET stock = stock + ? WHERE id = ?")
            ->execute([$item['quantity'], $item['product_id']]);
    }

    $failStatus = $verification['status'] ?? $status ?? 'Failed';
    $pdo->prepare("UPDATE khalti_transactions SET status = ?, response_data = ? WHERE order_id = ? AND pidx = ?")
        ->execute([
            $failStatus,
            json_encode($verification['raw'] ?? []),
            $order_id,
            $pidx
        ]);

    unset($_SESSION['khalti_order_id']);

    header('Location: failed.php?order_id=' . $order_id . '&reason=' . urlencode($failStatus));
    exit;
}
