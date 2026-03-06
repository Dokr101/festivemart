<?php
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

// Pick up any error set by Payment/initiate.php via session flash
if (isset($_SESSION['cart_error'])) {
    $error = $_SESSION['cart_error'];
    unset($_SESSION['cart_error']);
}

// Handle cart actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $product_id = (int) $_POST['product_id'];
        $qty = max(1, (int) ($_POST['quantity'] ?? 1));

        // Simple insert or update
        $s = $pdo->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE quantity = quantity + ?");
        $s->execute([$user_id, $product_id, $qty, $qty]);
        header("Location: " . $_SERVER['HTTP_REFERER']);
        exit;
    } elseif ($action === 'update') {
        foreach ($_POST['cart'] ?? [] as $cart_id => $qty) {
            $qty = max(1, (int) $qty);
            $s = $pdo->prepare("UPDATE cart SET quantity = ? WHERE id = ? AND user_id = ?");
            $s->execute([$qty, $cart_id, $user_id]);
        }
        $success = "Cart updated successfully.";
    } elseif ($action === 'remove') {
        $cart_id = (int) $_POST['cart_id'];
        $s = $pdo->prepare("DELETE FROM cart WHERE id = ? AND user_id = ?");
        $s->execute([$cart_id, $user_id]);
        $success = "Item removed from cart.";
    } elseif ($action === 'apply_coupon') {
        $code = trim($_POST['coupon_code']);
        $total = getCartTotal();
        $cartItems = getCartItems();

        if ($total <= 1000) {
            unset($_SESSION['coupon_id'], $_SESSION['coupon_code'], $_SESSION['coupon_discount']);
            $error = "Invalid coupon code, expired, or minimum order not met. More than Rs. 1000 should be spent.";
        } else {
            $coupon = validateCoupon($code, $total);
            if ($coupon) {
                $_SESSION['coupon_id'] = $coupon['id'];
                $_SESSION['coupon_code'] = $coupon['code'];
                $_SESSION['coupon_discount'] = $coupon['discount_percent'];
                $success = "Coupon applied! " . (float) $coupon['discount_percent'] . "% off.";
            } else {
                unset($_SESSION['coupon_id'], $_SESSION['coupon_code'], $_SESSION['coupon_discount']);
                $error = "Invalid coupon code, expired, or minimum order not met. More than Rs. 1000 should be spent.";
            }
        }
    } elseif ($action === 'remove_coupon') {
        unset($_SESSION['coupon_id'], $_SESSION['coupon_code'], $_SESSION['coupon_discount']);
        $success = "Coupon removed.";
    } elseif ($action === 'checkout') {
        $cartItems = getCartItems();
        if (empty($cartItems)) {
            $error = "Your cart is empty.";
        } else {
            $subtotal = getCartTotal();
            $shipping = 100.00;
            $discountAmt = isset($_SESSION['coupon_discount']) ? ($subtotal * $_SESSION['coupon_discount'] / 100) : 0;
            $total = $subtotal - $discountAmt + $shipping;

            // Build address from dropdown fields + street
            $province = trim($_POST['province'] ?? '');
            $district = trim($_POST['district'] ?? '');
            $municipality = trim($_POST['municipality'] ?? '');
            $street = trim($_POST['street_address'] ?? '');
            $addressParts = array_filter([$street, $municipality, $district, $province]);
            $address = !empty($addressParts) ? implode(', ', $addressParts) : 'Default Address';
            $couponId = $_SESSION['coupon_id'] ?? null;

            try {
                $pdo->beginTransaction();

                // 1. Create Order
                $s = $pdo->prepare("INSERT INTO orders (user_id, coupon_id, subtotal, shipping, discount, total, delivery_address) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $s->execute([$user_id, $couponId, $subtotal, $shipping, $discountAmt, $total, $address]);
                $order_id = $pdo->lastInsertId();

                // 2. Insert Order Items & Update Stock
                $sItem = $pdo->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
                $sStock = $pdo->prepare("UPDATE products SET stock = GREATEST(0, stock - ?) WHERE id = ?");

                foreach ($cartItems as $item) {
                    $finalPrice = getDiscountedPrice($item['price'], $item['discount_percent']);
                    $sItem->execute([$order_id, $item['product_id'], $item['quantity'], $finalPrice]);
                    $sStock->execute([$item['quantity'], $item['product_id']]);
                }

                // 3. Clear Cart
                $pdo->prepare("DELETE FROM cart WHERE user_id = ?")->execute([$user_id]);

                $pdo->commit();

                // Unset coupon
                unset($_SESSION['coupon_id'], $_SESSION['coupon_code'], $_SESSION['coupon_discount']);

                // Redirect to success
                header("Location: account.php?tab=orders&msg=order_success");
                exit;
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Failed to place order. " . $e->getMessage();
            }
        }
    }
}

$cartItems = getCartItems();
$subtotal = getCartTotal();
$shipping = count($cartItems) > 0 ? 100.00 : 0;
$discountAmt = isset($_SESSION['coupon_discount']) ? ($subtotal * $_SESSION['coupon_discount'] / 100) : 0;
$total = $subtotal - $discountAmt + $shipping;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Cart - FestiVmart</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body>

    <nav class="navbar scrolled">
        <a href="../homepage.php" class="navbar-brand">FestiVmart</a>
        <div class="hamburger"><span></span><span></span><span></span></div>
        <ul class="nav-links">
            <li><a href="../homepage.php">Home</a></li>
            <li><a href="shop.php">Shop</a></li>
            <li><a href="account.php">Dashboard</a></li>
            <li><a href="cart.php" class="active cart-badge">Cart <span class="cart-count">
                        <?php echo count($cartItems); ?>
                    </span></a></li>
            <li><a href="../auth/logout.php">Logout</a></li>
        </ul>
    </nav>

    <div class="page-wrapper container section">

        <div class="section-header">
            <h2>Shopping Cart</h2>
            <p>Review your festival items before checkout.</p>
        </div>

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

        <?php if (empty($cartItems)): ?>
            <div class="card" style="padding: 4rem 2rem; text-align: center;">
                <div class="emoji-lg mb-2">🛒</div>
                <h3>Your cart is completely empty.</h3>
                <p class="text-muted mb-2">Looks like you haven't added any festival goodies yet.</p>
                <a href="shop.php" class="btn btn-primary">Start Shopping</a>
            </div>
        <?php else: ?>
            <div class="cart-layout" style="grid-template-columns: 1fr 650px;">

                <!-- Left: Items List -->
                <div class="cart-items">
                    <form action="cart.php" method="POST" id="updateCartForm">
                        <input type="hidden" name="action" value="update">
                        <div class="card p-0 mb-3">
                            <?php foreach ($cartItems as $item):
                                $price = getDiscountedPrice($item['price'], $item['discount_percent']);
                                ?>
                                <div class="cart-item">
                                    <div class="cart-item-img">
                                        <?php if (!empty($item['image'])): ?>
                                            <img src="../assets/uploads/products/<?php echo htmlspecialchars($item['image']); ?>"
                                                alt="<?php echo htmlspecialchars($item['name']); ?>">
                                        <?php else: ?>
                                            🛍️
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <div class="product-name mb-1" style="font-size: 1rem;">
                                            <?php echo htmlspecialchars($item['name']); ?>
                                        </div>
                                        <div class="product-price">
                                            <?php echo formatPrice($price); ?>
                                        </div>
                                    </div>
                                    <div class="cart-qty-control">
                                        <button type="button" class="qty-btn minus"
                                            data-display="qty_<?php echo $item['cart_id']; ?>" data-autosubmit="true">-</button>
                                        <input type="hidden" name="cart[<?php echo $item['cart_id']; ?>]"
                                            value="<?php echo $item['quantity']; ?>" class="qty-input" min="1"
                                            max="<?php echo max(1, $item['stock'] + 10); ?>">
                                        <div class="qty-display" id="qty_<?php echo $item['cart_id']; ?>">
                                            <?php echo $item['quantity']; ?>
                                        </div>
                                        <button type="button" class="qty-btn plus"
                                            data-display="qty_<?php echo $item['cart_id']; ?>" data-autosubmit="true">+</button>
                                    </div>
                                    <div class="text-right">
                                        <div class="product-price mb-1">
                                            <?php echo formatPrice($price * $item['quantity']); ?>
                                        </div>
                                        <!-- Remove single form inside the loop -->
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </form>

                    <!-- Remove buttons outside the main update form using a tiny separate form per item -->
                    <?php foreach ($cartItems as $item): ?>
                        <form action="cart.php" method="POST" class="hidden" id="remove-form-<?php echo $item['cart_id']; ?>">
                            <input type="hidden" name="action" value="remove">
                            <input type="hidden" name="cart_id" value="<?php echo $item['cart_id']; ?>">
                        </form>
                        <!-- In a real app we'd place this inside the cart item via absolute positioning to bypass nested form rules -->
                        <div
                            style="text-align:right; margin-top:-2.5rem; margin-right: 1.2rem; margin-bottom: 2rem; position:relative; z-index:10;">
                            <button type="button"
                                onclick="document.getElementById('remove-form-<?php echo $item['cart_id']; ?>').submit()"
                                class="text-faint" style="font-size:0.8rem; text-decoration:underline;">Remove
                                <?php echo htmlspecialchars($item['name']); ?>
                            </button>
                        </div>
                    <?php endforeach; ?>

                </div>

                <!-- Right: Order Summary Expanded -->
                <div>
                    <div class="cart-order-summary"
                        style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; padding: 2rem;">
                        <!-- Left Side of Summary Box -->
                        <div>
                            <h3 class="mb-2">Order Summary</h3>
                            <div class="summary-row">
                                <span class="text-muted">Subtotal (
                                    <?php echo count($cartItems); ?> items)
                                </span>
                                <span>
                                    <?php echo formatPrice($subtotal); ?>
                                </span>
                            </div>
                            <?php if ($discountAmt > 0): ?>
                                <div class="summary-row" style="color:#2ecc71;">
                                    <span>Discount (
                                        <?php echo $_SESSION['coupon_discount']; ?>%)
                                    </span>
                                    <span>-
                                        <?php echo formatPrice($discountAmt); ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                            <div class="summary-row" id="shipping-row"
                                style="display: <?php echo $shipping > 0 ? 'flex' : 'none'; ?>;">
                                <span class="text-muted">Delivery Charge</span>
                                <span id="shipping-display">
                                    <?php echo formatPrice($shipping); ?>
                                </span>
                            </div>

                            <!-- Coupon Block -->
                            <div
                                style="margin: 1.2rem 0; padding: 1rem; border: 1px dashed var(--fest-primary); border-radius: var(--radius-card); background: var(--bg-deep);">
                                <h4 class="mb-1" style="font-size:0.95rem;">Have a Coupon?</h4>
                                <?php if (isset($_SESSION['coupon_code'])): ?>
                                    <div class="flex items-center flex-between"
                                        style="background: rgba(46,204,113,0.1); padding:0.8rem; border-radius:8px; border:1px solid rgba(46,204,113,0.3);">
                                        <div>
                                            <strong style="color:#2ecc71;">
                                                <?php echo htmlspecialchars($_SESSION['coupon_code']); ?>
                                            </strong> applied
                                            <div style="font-size:0.75rem; color:var(--text-muted);">-
                                                <?php echo $_SESSION['coupon_discount']; ?>% off
                                            </div>
                                        </div>
                                        <form action="cart.php" method="POST" style="margin: 0;">
                                            <input type="hidden" name="action" value="remove_coupon">
                                            <button type="submit" class="btn btn-outline btn-sm">Remove</button>
                                        </form>
                                    </div>
                                <?php else: ?>
                                    <form action="cart.php" method="POST" class="coupon-row" style="margin: 0;">
                                        <input type="hidden" name="action" value="apply_coupon">
                                        <input type="text" name="coupon_code" class="form-control"
                                            placeholder="Enter code (e.g., FESTIVE10)">
                                        <button type="submit" class="btn btn-outline">Apply</button>
                                    </form>
                                <?php endif; ?>
                            </div>

                            <div class="divider"></div>

                            <div class="summary-row" id="total-row"
                                style="display: <?php echo $total > 0 ? 'flex' : 'none'; ?>;">
                                <span class="text-muted">Total Payable</span>
                                <span class="summary-total" id="total-display">
                                    <?php echo formatPrice($total); ?>
                                </span>
                            </div>


                        </div>

                        <!-- Right Side of Summary Box: Delivery & Payment -->
                        <div>
                            <!-- Checkout Form -->
                            <form action="cart.php" method="POST" id="checkoutForm">
                                <input type="hidden" name="action" value="checkout" id="formAction">

                                <div class="form-group mb-3">
                                    <h3 class="mb-2" style="font-size:1.1rem; margin-top:0;">Delivery Address</h3>

                                    <p class="text-faint mt-2 mb-2" style="font-size:0.8rem;">
                                        <b>Note:</b> Delivery inside Kathmandu Valley is Rs.50 and outside the valley would
                                        be Rs.150.
                                    </p>

                                    <select name="province" id="provinceSelect" class="form-control mb-half" required>
                                        <option value="">Select Province</option>
                                    </select>

                                    <select name="district" id="districtSelect" class="form-control mb-half" required
                                        disabled>
                                        <option value="">Select District</option>
                                    </select>

                                    <select name="municipality" id="municipalitySelect" class="form-control mb-half"
                                        required disabled>
                                        <option value="">Select Municipality</option>
                                    </select>

                                    <input type="text" name="street_address" class="form-control"
                                        placeholder="Street, Landmark, Tole..." required id="streetAddress">
                                </div>

                                <div class="form-group">
                                    <label class="form-label"
                                        style="font-size:1.1rem; font-weight:700; color:var(--text-primary); margin-bottom:0.6rem;">Payment
                                        Method</label>
                                    <div class="payment-methods">

                                        <!-- Cash on Delivery -->
                                        <label class="payment-option" id="cod-option">
                                            <input type="radio" name="payment_method" value="cod" checked id="pay_cod">
                                            <div class="payment-option-inner">
                                                <span class="payment-icon">💵</span>
                                                <div>
                                                    <div class="payment-name">Cash on Delivery</div>
                                                    <div class="payment-desc">Pay when your order arrives</div>
                                                </div>
                                            </div>
                                        </label>

                                        <!-- Khalti -->
                                        <label class="payment-option" id="khalti-option">
                                            <input type="radio" name="payment_method" value="khalti" id="pay_khalti">
                                            <div class="payment-option-inner">
                                                <span class="payment-icon">💜</span>
                                                <div>
                                                    <div class="payment-name">Pay with Khalti</div>
                                                    <div class="payment-desc">Fast &amp; secure digital payment</div>
                                                </div>
                                                <span
                                                    style="margin-left:auto;background:linear-gradient(135deg,#5c35d9,#8b5cf6);color:#fff;padding:0.15rem 0.55rem;border-radius:12px;font-size:0.7rem;font-weight:700;">LIVE</span>
                                            </div>
                                        </label>

                                    </div>
                                </div>
                                <button type="submit" class="btn btn-primary w-full mt-2" id="checkoutBtn"
                                    style="padding:1rem; font-size:1.1rem;">Confirm Order</button>
                            </form>
                        </div>
                    </div> <!-- /End Expanded Order Summary -->

                    <style>
                        .payment-methods {
                            display: flex;
                            flex-direction: column;
                            gap: 0.6rem;
                        }

                        .payment-option {
                            cursor: pointer;
                            border: 1.5px solid var(--border);
                            border-radius: 10px;
                            padding: 0.9rem 1rem;
                            transition: border-color 0.2s, background 0.2s;
                            display: block;
                        }

                        .payment-option:has(input:checked) {
                            border-color: #8b5cf6;
                            background: rgba(139, 92, 246, 0.07);
                        }

                        .payment-option input[type=radio] {
                            display: none;
                        }

                        .payment-option-inner {
                            display: flex;
                            align-items: center;
                            gap: 0.8rem;
                        }

                        .payment-icon {
                            font-size: 1.3rem;
                        }

                        .payment-name {
                            font-weight: 600;
                            font-size: 0.92rem;
                        }

                        .payment-desc {
                            font-size: 0.75rem;
                            color: var(--text-muted);
                            margin-top: 0.1rem;
                        }
                    </style>

                    <style>
                        .mb-half {
                            margin-bottom: 0.5rem;
                        }

                        select.form-control:disabled {
                            opacity: 0.5;
                            cursor: not-allowed;
                        }
                    </style>

                    <script>
                        (function () {
                            var form = document.getElementById('checkoutForm');
                            var btn = document.getElementById('checkoutBtn');
                            var radios = document.querySelectorAll('input[name="payment_method"]');

                            // === Payment method toggle ===
                            function updateForm() {
                                var method = document.querySelector('input[name="payment_method"]:checked').value;
                                if (method === 'khalti') {
                                    form.action = '../Payment/initiate.php';
                                    document.getElementById('formAction').disabled = true;
                                    btn.textContent = '\uD83D\uDC9C Pay with Khalti';
                                    btn.style.background = 'linear-gradient(135deg, #5c35d9, #8b5cf6)';
                                } else {
                                    form.action = 'cart.php';
                                    document.getElementById('formAction').disabled = false;
                                    btn.textContent = 'Confirm Order';
                                    btn.style.background = '';
                                }
                            }
                            radios.forEach(function (r) { r.addEventListener('change', updateForm); });
                            updateForm();

                            // === Cascading address dropdowns ===
                            var addressData = null;
                            var provinceEl = document.getElementById('provinceSelect');
                            var districtEl = document.getElementById('districtSelect');
                            var municipalityEl = document.getElementById('municipalitySelect');

                            fetch('../assets/js/nepal-address.json')
                                .then(function (r) { return r.json(); })
                                .then(function (data) {
                                    addressData = data;
                                    Object.keys(data).forEach(function (prov) {
                                        var opt = document.createElement('option');
                                        opt.value = prov;
                                        opt.textContent = prov;
                                        provinceEl.appendChild(opt);
                                    });
                                });

                            provinceEl.addEventListener('change', function () {
                                districtEl.innerHTML = '<option value="">Select District</option>';
                                municipalityEl.innerHTML = '<option value="">Select Municipality</option>';
                                districtEl.disabled = true;
                                municipalityEl.disabled = true;

                                var prov = this.value;
                                if (prov && addressData && addressData[prov]) {
                                    Object.keys(addressData[prov]).forEach(function (dist) {
                                        var opt = document.createElement('option');
                                        opt.value = dist;
                                        opt.textContent = dist;
                                        districtEl.appendChild(opt);
                                    });
                                    districtEl.disabled = false;
                                }
                            });

                            districtEl.addEventListener('change', function () {
                                municipalityEl.innerHTML = '<option value="">Select Municipality</option>';
                                municipalityEl.disabled = true;

                                var prov = provinceEl.value;
                                var dist = this.value;
                                if (prov && dist && addressData && addressData[prov] && addressData[prov][dist]) {
                                    addressData[prov][dist].forEach(function (mun) {
                                        var opt = document.createElement('option');
                                        opt.value = mun;
                                        opt.textContent = mun;
                                        municipalityEl.appendChild(opt);
                                    });
                                    municipalityEl.disabled = false;
                                }
                            });
                        })();
                    </script>
                </div>
            </div>

        </div>
    <?php endif; ?>

    </div>

    <script src="../assets/js/main.js"></script>
</body>

</html>