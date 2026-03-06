<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/KhaltiHelper.php';

requireLogin();

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../customer/cart.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Build delivery address from dropdown fields + street/landmark
$province = trim($_POST['province'] ?? '');
$district = trim($_POST['district'] ?? '');
$municipality = trim($_POST['municipality'] ?? '');
$street = trim($_POST['street_address'] ?? '');

// Combine into one address string
$addressParts = array_filter([$street, $municipality, $district, $province]);
$address = !empty($addressParts) ? implode(', ', $addressParts) : trim($_POST['delivery_address'] ?? '');

if (empty($address)) {
    $_SESSION['cart_error'] = 'Please provide a delivery address.';
    header('Location: ../customer/cart.php');
    exit;
}

$cartItems = getCartItems();
if (empty($cartItems)) {
    $_SESSION['cart_error'] = 'Your cart is empty.';
    header('Location: ../customer/cart.php');
    exit;
}

$subtotal = getCartTotal();
$shipping = 100.00;
$discountAmt = isset($_SESSION['coupon_discount']) ? ($subtotal * $_SESSION['coupon_discount'] / 100) : 0;
$total = $subtotal - $discountAmt + $shipping;
$couponId = $_SESSION['coupon_id'] ?? null;

try {
    $pdo->beginTransaction();

    // 1. Create a PENDING order with payment_method = Khalti
    $s = $pdo->prepare("INSERT INTO orders (user_id, coupon_id, subtotal, shipping, discount, total, delivery_address, status, payment_method) VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending', 'Khalti')");
    $s->execute([$user_id, $couponId, $subtotal, $shipping, $discountAmt, $total, $address]);
    $order_id = $pdo->lastInsertId();

    // 2. Insert order items & update stock (reserved)
    $sItem = $pdo->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
    $sStock = $pdo->prepare("UPDATE products SET stock = GREATEST(0, stock - ?) WHERE id = ?");

    foreach ($cartItems as $item) {
        $finalPrice = getDiscountedPrice($item['price'], $item['discount_percent']);
        $sItem->execute([$order_id, $item['product_id'], $item['quantity'], $finalPrice]);
        $sStock->execute([$item['quantity'], $item['product_id']]);
    }

    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    // Log error
    error_log('FestiVmart Khalti: Order creation failed — ' . $e->getMessage());
    $_SESSION['cart_error'] = 'Failed to create order. Please try again.';
    header('Location: ../customer/cart.php');
    exit;
}

// 3. Fetch customer details
$userRow = $pdo->prepare("SELECT full_name, email, phone FROM users WHERE id = ?");
$userRow->execute([$user_id]);
$user = $userRow->fetch();

// 4. Store order_id in session for retrieval on callback
$_SESSION['khalti_order_id'] = $order_id;

// 5. Amount in paisa (NPR × 100)
$amountPaisa = (int) round($total * 100);

// Log the Khalti initiation attempt
error_log("FestiVmart Khalti: Initiating payment for Order #{$order_id}, amount={$amountPaisa} paisa");

$result = khaltiInitiate(
    $amountPaisa,
    'ORDER-' . $order_id,
    'FestiVmart Order #' . $order_id,
    [
        'name' => $user['full_name'],
        'email' => $user['email'],
        'phone' => $user['phone'],
    ]
);

if ($result['success']) {
    // Save pidx against order for later verification
    $pdo->prepare("INSERT INTO khalti_transactions (order_id, pidx, amount, status) VALUES (?, ?, ?, 'Initiated')")
        ->execute([$order_id, $result['pidx'], $total]);

    error_log("FestiVmart Khalti: Redirecting to {$result['payment_url']}");

    // Redirect user to Khalti payment page
    header('Location: ' . $result['payment_url']);
    exit;
} else {
    // Khalti initiation failed — cancel the order and restore stock
    $pdo->prepare("UPDATE orders SET status = 'Cancelled' WHERE id = ?")->execute([$order_id]);
    $items = $pdo->prepare("SELECT product_id, quantity FROM order_items WHERE order_id = ?");
    $items->execute([$order_id]);
    foreach ($items->fetchAll() as $item) {
        $pdo->prepare("UPDATE products SET stock = stock + ? WHERE id = ?")
            ->execute([$item['quantity'], $item['product_id']]);
    }

    $errorDetail = $result['error'] ?? 'Unknown error';
    error_log('FestiVmart Khalti: Initiation FAILED — ' . $errorDetail . ' | Raw: ' . json_encode($result['raw'] ?? []));

    $_SESSION['cart_error'] = 'Khalti payment initiation failed: ' . $errorDetail;
    header('Location: ../customer/cart.php');
    exit;
}
