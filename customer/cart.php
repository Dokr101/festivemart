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
        $selectedItemsIds = $_POST['selected_items'] ?? [];
        if (empty($selectedItemsIds)) {
            $error = "Please select at least one item to checkout.";
        } else {
            $allCartItems = getCartItems();
            $cartItems = array_filter($allCartItems, function ($item) use ($selectedItemsIds) {
                return in_array($item['cart_id'], $selectedItemsIds);
            });

            if (empty($cartItems)) {
                $error = "The selected items are no longer available.";
            } else {
                $province = trim($_POST['province'] ?? '');
                $district = trim($_POST['district'] ?? '');

                // Dynamic Shipping Logic: Rs. 50 for Kathmandu, Rs. 150 otherwise
                $shipping = ($province === 'Bagmati Pradesh' && $district === 'Kathmandu') ? 50.00 : 150.00;

                $subtotal = 0;
                foreach ($cartItems as $item) {
                    $subtotal += getDiscountedPrice($item['price'], $item['discount_percent']) * $item['quantity'];
                }

                $discountAmt = isset($_SESSION['coupon_discount']) ? ($subtotal * $_SESSION['coupon_discount'] / 100) : 0;
                $total = $subtotal - $discountAmt + $shipping;

                $addressParts = array_filter([trim($_POST['street_address'] ?? ''), trim($_POST['municipality'] ?? ''), $district, $province]);
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

                    // 3. Clear ONLY selected items from Cart
                    $inQuery = implode(',', array_fill(0, count($selectedItemsIds), '?'));
                    $pdo->prepare("DELETE FROM cart WHERE id IN ($inQuery) AND user_id = ?")
                        ->execute(array_merge($selectedItemsIds, [$user_id]));

                    $pdo->commit();

                    // Unset coupon
                    unset($_SESSION['coupon_id'], $_SESSION['coupon_code'], $_SESSION['coupon_discount']);

                    header("Location: account.php?tab=orders&msg=order_success");
                    exit;
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error = "Failed to place order. " . $e->getMessage();
                }
            }
        }
    } elseif ($action === 'update_qty_ajax') {
        $cart_id = (int) $_POST['cart_id'];
        $qty = max(1, (int) $_POST['quantity']);
        $s = $pdo->prepare("UPDATE cart SET quantity = ? WHERE id = ? AND user_id = ?");
        $s->execute([$qty, $cart_id, $user_id]);

        $itemTotal = 0;
        $allTotal = 0;

        // Fetch new state to return
        $s = $pdo->prepare("SELECT c.quantity, p.price, p.discount_percent FROM cart c JOIN products p ON c.product_id = p.id WHERE c.id = ?");
        $s->execute([$cart_id]);
        $data = $s->fetch();
        if ($data) {
            $price = getDiscountedPrice($data['price'], $data['discount_percent']);
            $itemTotal = $price * $data['quantity'];
        }

        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'item_total' => formatPrice($itemTotal),
            'item_total_raw' => $itemTotal
        ]);
        exit;
    } elseif ($action === 'toggle_selection_ajax') {
        $cart_id = (int) $_POST['cart_id'];
        $selected = $_POST['selected'] === 'true';
        if (!isset($_SESSION['cart_selection'])) {
            $_SESSION['cart_selection'] = [];
        }
        $_SESSION['cart_selection'][$cart_id] = $selected;
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit;
    } elseif ($action === 'select_all_ajax') {
        $selected = $_POST['selected'] === 'true';
        if (!isset($_SESSION['cart_selection'])) {
            $_SESSION['cart_selection'] = [];
        }
        foreach (getCartItems() as $item) {
            $_SESSION['cart_selection'][$item['cart_id']] = $selected;
        }
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit;
    }
}

$cartItems = getCartItems();
$subtotal = getCartTotal();
$shipping = count($cartItems) > 0 ? 150.00 : 0;
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
            <li><a href="../auth/logout.php" class="logout-btn"><span class="logout-icon">🚪</span> Logout</a></li>
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
            <div class="cart-layout">

                <!-- Left Column: Products -->
                <div class="cart-items-column">
                    <div class="cart-selection-header">
                        <div class="flex items-center gap-1">
                            <?php
                            $allSelected = true;
                            if (empty($cartItems)) {
                                $allSelected = false;
                            } else {
                                foreach ($cartItems as $c) {
                                    if (isset($_SESSION['cart_selection'][$c['cart_id']]) && !$_SESSION['cart_selection'][$c['cart_id']]) {
                                        $allSelected = false;
                                        break;
                                    }
                                }
                            }
                            ?>
                            <input type="checkbox" id="selectAllItems" class="item-checkbox" <?php echo $allSelected ? 'checked' : ''; ?>>
                            <label for="selectAllItems"
                                style="font-weight: 600; cursor: pointer; color: var(--text);">Select All Items</label>
                        </div>
                        <div class="text-muted" style="font-size: 0.85rem;">
                            <?php echo count($cartItems); ?> Items in Cart
                        </div>
                    </div>

                    <form action="cart.php" method="POST" id="updateCartForm">
                        <input type="hidden" name="action" value="update">
                        <div class="card p-0 mb-3">
                            <?php foreach ($cartItems as $item):
                                $price = getDiscountedPrice($item['price'], $item['discount_percent']);
                                ?>
                                <?php
                                $isSelected = !isset($_SESSION['cart_selection'][$item['cart_id']]) || $_SESSION['cart_selection'][$item['cart_id']];
                                ?>
                                <div class="cart-item <?php echo !$isSelected ? 'unselected' : ''; ?>"
                                    data-price="<?php echo $price; ?>" data-cart-id="<?php echo $item['cart_id']; ?>">
                                    <div class="cart-item-select">
                                        <input type="checkbox" name="selected_items[]" value="<?php echo $item['cart_id']; ?>"
                                            class="item-checkbox" <?php echo $isSelected ? 'checked' : ''; ?>
                                            data-cart-id="<?php echo $item['cart_id']; ?>">
                                    </div>
                                    <div class="cart-item-img">
                                        <?php if (!empty($item['image'])): ?>
                                            <img src="../assets/uploads/products/<?php echo htmlspecialchars($item['image']); ?>"
                                                alt="<?php echo htmlspecialchars($item['name']); ?>">
                                        <?php else: ?>
                                            🛍️
                                        <?php endif; ?>
                                    </div>
                                    <div class="cart-item-info">
                                        <div class="product-name"
                                            style="font-weight: 600; color: var(--text); font-size: 1.1rem;">
                                            <?php echo htmlspecialchars($item['name']); ?>
                                        </div>

                                        <div class="cart-item-details-row">
                                            <div class="unit-price"
                                                style="font-size: 0.95rem; color: var(--text-muted); min-width: 100px;">
                                                <small style="display:block; opacity: 0.7;">Per unit</small>
                                                <?php echo formatPrice($price); ?>
                                            </div>

                                            <div class="cart-qty-control">
                                                <button type="button" class="qty-btn-ajax minus"
                                                    data-cart-id="<?php echo $item['cart_id']; ?>">-</button>
                                                <div class="qty-display" id="qty_<?php echo $item['cart_id']; ?>">
                                                    <?php echo $item['quantity']; ?>
                                                </div>
                                                <button type="button" class="qty-btn-ajax plus"
                                                    data-cart-id="<?php echo $item['cart_id']; ?>">+</button>
                                            </div>

                                            <div class="text-right" style="min-width: 120px;">
                                                <small style="display:block; opacity: 0.7;">Subtotal</small>
                                                <div class="product-price item-total" id="total_<?php echo $item['cart_id']; ?>"
                                                    style="font-weight: 700; font-size: 1.15rem; color: var(--fest-primary);">
                                                    <?php echo formatPrice($price * $item['quantity']); ?>
                                                </div>
                                            </div>
                                        </div>

                                        <button type="button" class="remove-item-btn" style="margin-top: 0.5rem;"
                                            onclick="removeCartItem(<?php echo $item['cart_id']; ?>)">
                                            <span style="margin-right: 4px;">🗑️</span> Remove Item
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </form>

                    <!-- Hidden real remove forms -->
                    <div id="removeFormsWrap" style="display:none;">
                        <?php foreach ($cartItems as $item): ?>
                            <form action="cart.php" method="POST" id="remove-form-<?php echo $item['cart_id']; ?>">
                                <input type="hidden" name="action" value="remove">
                                <input type="hidden" name="cart_id" value="<?php echo $item['cart_id']; ?>">
                            </form>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Right Column: Order Summary & Checkout -->
                <div class="cart-summary-column">
                    <div class="cart-order-summary">
                        <form action="cart.php" method="POST" id="checkoutForm">
                            <input type="hidden" name="action" value="checkout" id="formAction">

                            <!-- Top Row: Totals and Address -->
                            <div class="summary-top-row">
                                <!-- Order Totals Box -->
                                <div class="summary-box">
                                    <h3 class="mb-2" style="border-bottom: 1px solid var(--border); padding-bottom: 1rem;">Order Totals</h3>
                                    <div class="summary-details">
                                        <div class="summary-row">
                                            <span class="text-muted" id="subtotal-label">Subtotal (<?php echo count($cartItems); ?> items)</span>
                                            <span id="subtotal-display" style="font-weight: 600;"><?php echo formatPrice($subtotal); ?></span>
                                        </div>

                                        <?php if ($discountAmt > 0): ?>
                                            <div class="summary-row" style="color:#2ecc71;">
                                                <span>Discount (<?php echo $_SESSION['coupon_discount']; ?>%)</span>
                                                <span>-<?php echo formatPrice($discountAmt); ?></span>
                                            </div>
                                        <?php endif; ?>

                                        <div class="summary-row" id="shipping-row" style="display: <?php echo $shipping > 0 ? 'flex' : 'none'; ?>;">
                                            <span class="text-muted">Delivery Charge</span>
                                            <span id="shipping-display" style="font-weight: 600;"><?php echo formatPrice($shipping); ?></span>
                                        </div>

                                        <!-- Coupon Block -->
                                        <div style="margin: 1.5rem 0; padding: 1.2rem; border: 1px dashed var(--fest-primary); border-radius: 12px; background: var(--bg-deep);">
                                            <h4 class="mb-1" style="font-size:0.9rem;">Have a Coupon?</h4>
                                            <?php if (isset($_SESSION['coupon_code'])): ?>
                                                <div class="flex items-center flex-between" style="background: rgba(46,204,113,0.1); padding:0.8rem; border-radius:8px; border:1px solid rgba(46,204,113,0.3);">
                                                    <div>
                                                        <strong style="color:#2ecc71;"><?php echo htmlspecialchars($_SESSION['coupon_code']); ?></strong>
                                                        <div style="font-size:0.75rem; color:var(--text-muted);">-<?php echo $_SESSION['coupon_discount']; ?>% off</div>
                                                    </div>
                                                    <button type="button" onclick="document.getElementById('removeCouponForm').submit();" class="btn btn-outline btn-sm" style="padding: 0.3rem 0.6rem; font-size: 0.75rem;">Remove</button>
                                                </div>
                                            <?php else: ?>
                                                <div class="coupon-row" style="margin: 0; display: flex; gap: 0.5rem;">
                                                    <input type="text" id="couponCodeInput" class="form-control" placeholder="Code..." style="padding: 0.5rem;">
                                                    <button type="button" onclick="applyCoupon()" class="btn btn-outline" style="padding: 0.5rem 1rem;">Apply</button>
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                        <div class="divider" style="margin: 1.5rem 0;"></div>

                                        <div class="summary-row" id="total-row" style="display: <?php echo $total > 0 ? 'flex' : 'none'; ?>; margin-bottom: 0.5rem;">
                                            <span style="font-size: 1rem; font-weight: 700; color: var(--text);">Total Payable</span>
                                            <span class="summary-total" id="total-display" style="font-size: 1.2rem; color: var(--fest-primary);"><?php echo formatPrice($total); ?></span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Delivery Address Box -->
                                <div class="summary-box delivery-address-section">
                                    <h3 class="mb-2" style="border-bottom: 1px solid var(--border); padding-bottom: 1rem;">Delivery Address</h3>
                                    <p class="text-faint mb-2" style="font-size:0.75rem;">
                                        <b>Rate:</b> Rs.50 (Kathmandu) / Rs.150 (Outside)
                                    </p>

                                    <div style="display: flex; flex-direction: column; gap: 0.8rem;">
                                        <select name="province" id="provinceSelect" class="form-control" required>
                                            <option value="">Select Province</option>
                                        </select>
                                        <select name="district" id="districtSelect" class="form-control" required disabled>
                                            <option value="">Select District</option>
                                        </select>
                                        <select name="municipality" id="municipalitySelect" class="form-control" required disabled>
                                            <option value="">Select Municipality</option>
                                        </select>
                                        <input type="text" name="street_address" class="form-control" placeholder="Street, Landmark..." required>
                                    </div>
                                </div>
                            </div>

                            <!-- Middle Row: Payment Method Spanning -->
                            <div class="summary-box summary-payment-row">
                                <h4 class="mb-2" style="font-size: 1.1rem; color: var(--text); text-align: center;">Payment Method</h4>
                                <div class="payment-methods">
                                    <label class="payment-option">
                                        <input type="radio" name="payment_method" value="cod" checked>
                                        <div class="payment-option-inner">
                                            <span class="payment-icon">💵</span>
                                            <div>
                                                <div class="payment-name">Cash on Delivery</div>
                                                <div class="payment-desc">Pay at door</div>
                                            </div>
                                        </div>
                                    </label>

                                    <label class="payment-option">
                                        <input type="radio" name="payment_method" value="khalti">
                                        <div class="payment-option-inner">
                                            <span class="payment-icon">💜</span>
                                            <div>
                                                <div class="payment-name">Khalti Digital</div>
                                                <div class="payment-desc">Secure online</div>
                                            </div>
                                        </div>
                                    </label>
                                </div>
                            </div>

                            <!-- Bottom Row: Confirm Action -->
                            <div class="summary-action-row">
                                <button type="submit" class="btn btn-primary" id="checkoutBtn"
                                    style="padding: 1.2rem 3rem; font-size: 1.1rem; font-weight: 700; border-radius: 12px; min-width: 300px;">
                                    Confirm Order
                                </button>
                            </div>
                        </form>

                        <!-- Hidden forms for AJAX-like actions without nested forms -->
                        <form id="removeCouponForm" action="cart.php" method="POST" style="display:none;">
                            <input type="hidden" name="action" value="remove_coupon">
                        </form>
                        <form id="applyCouponForm" action="cart.php" method="POST" style="display:none;">
                            <input type="hidden" name="action" value="apply_coupon">
                            <input type="hidden" name="coupon_code" id="hiddenCouponCode">
                        </form>

                        <script>
                            function applyCoupon() {
                                const code = document.getElementById('couponCodeInput').value;
                                if (!code) return;
                                document.getElementById('hiddenCouponCode').value = code;
                                document.getElementById('applyCouponForm').submit();
                            }
                        </script>
                    </div>
                </div>
            </div>

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
                    var selectAll = document.getElementById('selectAllItems');
                    var itemCheckboxes = document.querySelectorAll('.cart-item .item-checkbox');
                    var provinceEl = document.getElementById('provinceSelect');
                    var districtEl = document.getElementById('districtSelect');
                    var municipalityEl = document.getElementById('municipalitySelect');
                    var subtotalLabel = document.getElementById('subtotal-label');
                    var subtotalDisplay = document.getElementById('subtotal-display');
                    var shippingDisplay = document.getElementById('shipping-display');
                    var totalDisplay = document.getElementById('total-display');
                    var addressData = null;

                    // Global Remove Function
                    window.removeCartItem = function (cartId) {
                        if (confirm("Remove this item from your cart?")) {
                            document.getElementById('remove-form-' + cartId).submit();
                        }
                    }

                    function updateCalculation() {
                        var subtotal = 0;
                        var selectedCount = 0;

                        itemCheckboxes.forEach(function (cb) {
                            var itemRow = cb.closest('.cart-item');
                            if (!itemRow) return;

                            if (cb.checked) {
                                var cartId = cb.getAttribute('data-cart-id');
                                var qtyEl = document.getElementById('qty_' + cartId);
                                if (!qtyEl) return;

                                var qty = parseInt(qtyEl.textContent);
                                var price = parseFloat(itemRow.getAttribute('data-price'));
                                subtotal += price * qty;
                                selectedCount++;
                                itemRow.classList.remove('unselected');
                            } else {
                                itemRow.classList.add('unselected');
                            }
                        });

                        // Dynamic Shipping Logic: Rs. 50 for Kathmandu, Rs. 150 otherwise
                        var prov = provinceEl.value;
                        var dist = districtEl.value;
                        var ship = 0;

                        if (selectedCount > 0) {
                            ship = 150; // Default
                            if (prov === 'Bagmati Pradesh' && dist === 'Kathmandu') {
                                ship = 50;
                            }
                        }

                        var discountPercent = <?php echo (float) ($_SESSION['coupon_discount'] ?? 0); ?>;
                        var discountAmt = (subtotal * discountPercent / 100);
                        var total = subtotal - discountAmt + ship;

                        if (subtotalLabel) subtotalLabel.textContent = 'Subtotal (' + selectedCount + ' items)';
                        if (subtotalDisplay) subtotalDisplay.textContent = 'Rs. ' + subtotal.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                        if (shippingDisplay) shippingDisplay.textContent = 'Rs. ' + ship.toFixed(2);
                        if (totalDisplay) totalDisplay.textContent = 'Rs. ' + total.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

                        // Disable checkout if no items selected
                        btn.disabled = (selectedCount === 0);
                        btn.style.opacity = (selectedCount === 0) ? '0.5' : '1';
                        btn.style.cursor = (selectedCount === 0) ? 'not-allowed' : 'pointer';
                    }

                    // Selection Toggles
                    if (selectAll) {
                        selectAll.addEventListener('change', function () {
                            const isChecked = this.checked;
                            itemCheckboxes.forEach(cb => cb.checked = isChecked);
                            updateCalculation();

                            // AJAX to persist
                            const formData = new FormData();
                            formData.append('action', 'select_all_ajax');
                            formData.append('selected', isChecked);
                            fetch('cart.php', { method: 'POST', body: formData });
                        });
                    }

                    itemCheckboxes.forEach(cb => {
                        cb.addEventListener('change', function () {
                            const cartId = this.dataset.cartId;
                            const isChecked = this.checked;
                            var allChecked = Array.from(itemCheckboxes).every(c => c.checked);
                            if (selectAll) selectAll.checked = allChecked;
                            updateCalculation();

                            // AJAX to persist
                            const formData = new FormData();
                            formData.append('action', 'toggle_selection_ajax');
                            formData.append('cart_id', cartId);
                            formData.append('selected', isChecked);
                            fetch('cart.php', { method: 'POST', body: formData });
                        });
                    });

                    // Payment method toggle
                    function updateForm() {
                        var method = document.querySelector('input[name="payment_method"]:checked').value;
                        if (method === 'khalti') {
                            form.action = '../Payment/initiate.php';
                            document.getElementById('formAction').disabled = true;
                            btn.innerHTML = '💜 Pay with Khalti';
                            btn.style.background = 'linear-gradient(135deg, #5c35d9, #8b5cf6)';
                        } else {
                            form.action = 'cart.php';
                            document.getElementById('formAction').disabled = false;
                            btn.textContent = 'Confirm Order';
                            btn.style.background = '';
                        }
                    }
                    radios.forEach(r => r.addEventListener('change', updateForm));
                    updateForm();

                    // Cascading address dropdowns
                    fetch('../assets/js/nepal-address.json')
                        .then(r => r.json())
                        .then(data => {
                            addressData = data;
                            Object.keys(data).forEach(prov => {
                                var opt = document.createElement('option');
                                opt.value = prov; opt.textContent = prov;
                                provinceEl.appendChild(opt);
                            });
                        });

                    provinceEl.addEventListener('change', function () {
                        districtEl.innerHTML = '<option value="">Select District</option>';
                        municipalityEl.innerHTML = '<option value="">Select Municipality</option>';
                        districtEl.disabled = municipalityEl.disabled = true;

                        var prov = this.value;
                        if (prov && addressData && addressData[prov]) {
                            Object.keys(addressData[prov]).forEach(dist => {
                                var opt = document.createElement('option');
                                opt.value = dist; opt.textContent = dist;
                                districtEl.appendChild(opt);
                            });
                            districtEl.disabled = false;
                        }
                        updateCalculation();
                    });

                    districtEl.addEventListener('change', function () {
                        municipalityEl.innerHTML = '<option value="">Select Municipality</option>';
                        municipalityEl.disabled = true;

                        var prov = provinceEl.value;
                        var dist = this.value;
                        if (prov && dist && addressData && addressData[prov] && addressData[prov][dist]) {
                            addressData[prov][dist].forEach(mun => {
                                var opt = document.createElement('option');
                                opt.value = mun; opt.textContent = mun;
                                municipalityEl.appendChild(opt);
                            });
                            municipalityEl.disabled = false;
                        }
                        updateCalculation();
                    });

                    // Qty Buttons AJAX logic
                    document.querySelectorAll('.qty-btn-ajax').forEach(btn => {
                        btn.addEventListener('click', function () {
                            const cartId = this.dataset.cartId;
                            const display = document.getElementById('qty_' + cartId);
                            const totalEl = document.getElementById('total_' + cartId);
                            let currentQty = parseInt(display.textContent);
                            let newQty = this.classList.contains('plus') ? currentQty + 1 : currentQty - 1;

                            if (newQty < 1) return;

                            // Update UI immediately (optimistic)
                            display.textContent = newQty;
                            updateCalculation();

                            // AJAX request
                            const formData = new FormData();
                            formData.append('action', 'update_qty_ajax');
                            formData.append('cart_id', cartId);
                            formData.append('quantity', newQty);

                            fetch('cart.php', {
                                method: 'POST',
                                body: formData
                            })
                                .then(r => r.json())
                                .then(res => {
                                    if (res.success) {
                                        totalEl.textContent = res.item_total;
                                        updateCalculation();
                                    }
                                })
                                .catch(err => {
                                    console.error("Update failed", err);
                                    // Revert UI if failed
                                    display.textContent = currentQty;
                                    updateCalculation();
                                });
                        });
                    });

                    updateCalculation();
                })();

                // Ensure checkout form submits selected items
var checkoutForm = document.getElementById('checkoutForm');
if (checkoutForm) {
    checkoutForm.addEventListener('submit', function(e) {
        // Remove any previously added hidden inputs with name 'selected_items[]'
        var existingHidden = this.querySelectorAll('input[name="selected_items[]"]');
        existingHidden.forEach(el => el.remove());

        // Get all checked item checkboxes (they are outside this form)
        var checkedBoxes = document.querySelectorAll('.cart-item .item-checkbox:checked');
        if (checkedBoxes.length === 0) {
            e.preventDefault(); // Stop submission
            alert('Please select at least one item to checkout.');
            return false;
        }

        // For each checked checkbox, create a hidden input and append to form
        checkedBoxes.forEach(cb => {
            var hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = 'selected_items[]';
            hidden.value = cb.value; // cart_id
            this.appendChild(hidden);
        });

        // Continue with normal form submission
    });
}
            </script>
        <?php endif; ?>
        
    </div> <!-- .page-wrapper -->

    <script src="../assets/js/main.js"></script>
</body>

</html>