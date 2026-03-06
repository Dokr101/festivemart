<?php
require_once __DIR__ . '/includes/functions.php';

// Determine active and upcoming festivals
$activeFestival = getCurrentFestival();
$upcoming = getNextFestival();
$displayFestival = $activeFestival ?: $upcoming;

// Featured products for the active or next festival
$featuredProducts = [];
if ($displayFestival) {
    // Attempt to get featured for this specific festival
    $featuredProducts = getProductsByFestival($displayFestival['id'], 8);
}
// Fallback if none found
if (empty($featuredProducts)) {
    $featuredProducts = getFeaturedProducts(8);
}

// Additional upcoming festivals
$allUpcoming = getUpcomingFestivals(4);

// Theme style extraction
$themeStyle = festivalThemeStyle($displayFestival);
$particleType = $displayFestival['particle_type'] ?? 'stars';
$particleC1 = $displayFestival['theme_color'] ?? '#FF6B35';
$particleC2 = $displayFestival['accent_color'] ?? '#FFD700';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FestiVmart — Nepal's Festival Store</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body <?php echo $themeStyle; ?>>

    <!-- Navbar -->
    <nav class="navbar">
        <a href="homepage.php" class="navbar-brand">
            FestiVmart
            <span>Nepal's Festival Store</span>
        </a>

        <div class="hamburger"><span></span><span></span><span></span></div>

        <ul class="nav-links">
            <li><a href="homepage.php" class="active">Home</a></li>
            <li><a href="customer/shop.php">Shop</a></li>
            <?php if (isLoggedIn()): ?>
                <li><a href="<?php echo isAdmin() ? 'admin/dashboard.php' : 'customer/account.php'; ?>">Dashboard</a></li>
                <li><a href="customer/cart.php" class="cart-badge">Cart <span
                            class="cart-count"><?php echo getCartCount(); ?></span></a></li>
                <li><a href="auth/logout.php">Logout</a></li>
            <?php else: ?>
                <li><a href="auth/login.php" class="btn-nav-cta">Log In</a></li>
            <?php endif; ?>
        </ul>
    </nav>

    <!-- Hero Section -->
    <section class="hero">
        <div class="hero-particles" id="hero-particles" data-type="<?php echo htmlspecialchars($particleType); ?>"
            data-c1="<?php echo htmlspecialchars($particleC1); ?>"
            data-c2="<?php echo htmlspecialchars($particleC2); ?>">
            <!-- JS will populate particles here -->
        </div>

        <div class="hero-content">
            <?php if ($activeFestival): ?>
                <div class="hero-badge">
                    <span class="dot"></span> NOW ACTIVE:
                    <?php echo htmlspecialchars(strtoupper($activeFestival['name'])); ?>
                </div>
            <?php elseif ($upcoming): ?>
                <div class="hero-badge" style="background: rgba(255,255,255,0.05); border-color: var(--border);">
                    UPCOMING: <?php echo htmlspecialchars(strtoupper($upcoming['name'])); ?>
                </div>
            <?php endif; ?>

            <?php if ($displayFestival): ?>
                <h1 class="hero-title"><?php echo htmlspecialchars($displayFestival['banner_tagline']); ?></h1>
                <p class="hero-tagline"><?php echo htmlspecialchars($displayFestival['description']); ?></p>
            <?php else: ?>
                <h1 class="hero-title">Prepare for the Festivities</h1>
                <p class="hero-tagline">Your one-stop destination for all of Nepal's vibrant festivals.</p>
            <?php endif; ?>

            <div class="hero-cta-group">
                <!-- Shop Now redirects to login if not logged in -->
                <a href="<?php echo isLoggedIn() ? 'customer/shop.php' . ($displayFestival ? '?festival=' . $displayFestival['id'] : '') : 'auth/login.php?redirect=' . urlencode(SITE_URL . '/customer/shop.php'); ?>"
                    class="btn btn-primary btn-lg pulsing">Shop
                    <?php echo $displayFestival ? htmlspecialchars($displayFestival['name']) : 'Now'; ?></a>
                <a href="#festivals" class="btn btn-outline btn-lg">View All Festivals</a>
            </div>

            <?php if ($upcoming && !$activeFestival): ?>
                <!-- Countdown -->
                <?php
                $targetDate = $upcoming['start_date'] . 'T00:00:00';
                ?>
                <div class="countdown-wrapper">
                    <div class="text-center" style="text-align:left;">
                        <div class="countdown-label">Time remaining until</div>
                        <div style="font-weight:600; color:var(--text-primary);">
                            <?php echo htmlspecialchars($upcoming['name']); ?></div>
                    </div>
                    <div class="countdown-units">
                        <div class="countdown-block">
                            <div class="countdown-number" id="cd-days" data-target="<?php echo $targetDate; ?>">00</div>
                            <div class="countdown-unit-label">Days</div>
                        </div>
                        <div class="countdown-sep">:</div>
                        <div class="countdown-block">
                            <div class="countdown-number" id="cd-hours">00</div>
                            <div class="countdown-unit-label">Hours</div>
                        </div>
                        <div class="countdown-sep">:</div>
                        <div class="countdown-block">
                            <div class="countdown-number" id="cd-mins">00</div>
                            <div class="countdown-unit-label">Mins</div>
                        </div>
                        <div class="countdown-sep">:</div>
                        <div class="countdown-block">
                            <div class="countdown-number" id="cd-secs">00</div>
                            <div class="countdown-unit-label">Secs</div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Featured Products -->
    <section class="section" id="products">
        <div class="container">
            <div class="section-header">
                <span class="section-tag">Curated Picks</span>
                <h2><?php echo $displayFestival ? htmlspecialchars($displayFestival['name']) . ' Essentials' : 'Featured Products'; ?>
                </h2>
                <p>Top-rated items handpicked for your celebration.</p>
            </div>

            <div class="products-grid">
                <?php foreach ($featuredProducts as $product):
                    $price = $product['price'];
                    $hasDiscount = $product['discount_percent'] > 0;
                    $finalPrice = $hasDiscount ? getDiscountedPrice($price, $product['discount_percent']) : $price;
                    ?>
                    <div class="card product-card">
                        <div class="product-image-wrap">
                            <!-- Placeholder until real images are uploaded -->
                            <?php if (!empty($product['image'])): ?>
                                <img src="assets/uploads/products/<?php echo htmlspecialchars($product['image']); ?>"
                                    alt="<?php echo htmlspecialchars($product['name']); ?>" class="product-img">
                            <?php else: ?>
                                <div class="product-emoji-placeholder">🎁</div>
                            <?php endif; ?>
                            <?php if ($product['is_preorder']): ?>
                                <div class="product-badge preorder">Pre-Order</div>
                            <?php elseif ($hasDiscount): ?>
                                <div class="product-discount-badge">-<?php echo (int) $product['discount_percent']; ?>%</div>
                            <?php endif; ?>
                        </div>
                        <div class="product-body">
                            <?php if (isset($product['festival_name'])): ?>
                                <div class="product-festival-tag"
                                    style="color: <?php echo htmlspecialchars($product['theme_color'] ?? 'var(--fest-primary)'); ?>">
                                    <?php echo htmlspecialchars($product['festival_name']); ?>
                                </div>
                            <?php endif; ?>
                            <h3 class="product-name"><?php echo htmlspecialchars($product['name']); ?></h3>

                            <div class="product-price-row">
                                <span class="product-price"><?php echo formatPrice($finalPrice); ?></span>
                                <?php if ($hasDiscount): ?>
                                    <span class="product-price-original"><?php echo formatPrice($price); ?></span>
                                <?php endif; ?>
                            </div>

                            <div class="product-actions">
                                <?php if ($product['stock'] <= 0 && !$product['is_preorder']): ?>
                                    <button class="btn btn-outline" disabled>Out of Stock</button>
                                <?php else: ?>
                                    <form action="customer/cart.php" method="POST" style="flex:1;">
                                        <input type="hidden" name="action" value="add">
                                        <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                        <button type="submit"
                                            class="btn <?php echo $product['is_preorder'] ? 'btn-outline' : 'btn-primary'; ?> w-full add-to-cart-btn">
                                            <?php echo $product['is_preorder'] ? 'Pre-Book' : 'Add to Cart'; ?>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                            <?php if ($product['stock'] > 0 && $product['stock'] < 10): ?>
                                <div class="stock-badge stock-low text-center">Only <?php echo $product['stock']; ?> left!</div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="text-center mt-3">
                <a href="customer/shop.php" class="btn btn-outline btn-lg">Explore Full Store</a>
            </div>
        </div>
    </section>

    <!-- Calendar Preview -->
    <section class="section" id="festivals"
        style="background: rgba(0,0,0,0.2); border-top: 1px solid var(--border); border-bottom: 1px solid var(--border);">
        <div class="container">
            <div class="section-header">
                <span class="section-tag">Calendar</span>
                <h2>Upcoming Festivals</h2>
                <p>Plan ahead for Nepal's upcoming cultural celebrations.</p>
            </div>

            <div class="festivals-grid">
                <?php foreach ($allUpcoming as $fest):
                    $isActive = $activeFestival && $activeFestival['id'] === $fest['id'];
                    ?>
                    <a href="customer/shop.php?festival=<?php echo $fest['id']; ?>" class="festival-card"
                        style="--fc: <?php echo htmlspecialchars($fest['theme_color']); ?>;">
                        <div class="festival-card-inner">
                            <span class="festival-card-emoji">
                                <?php
                                $emojis = ['Holi' => '🎨', 'New Year' => '🎊', 'Dashain' => '🙏', 'Tihar' => '🪔', 'Teej' => '💃'];
                                echo $emojis[$fest['name']] ?? '🎪';
                                ?>
                            </span>

                            <?php if ($isActive): ?>
                                <div class="festival-card-status status-active mb-1">LIVE NOW</div>
                            <?php else: ?>
                                <div class="festival-card-status status-upcoming mb-1">IN
                                    <?php echo getFestivalCountdownDays($fest); ?> DAYS</div>
                            <?php endif; ?>

                            <h3 class="festival-card-name"><?php echo htmlspecialchars($fest['name']); ?></h3>
                            <div class="festival-card-dates">
                                <?php echo date('M d', strtotime($fest['start_date'])) . ' - ' . date('M d, Y', strtotime($fest['end_date'])); ?>
                            </div>
                            <p class="festival-card-desc">
                                <?php echo htmlspecialchars(substr($fest['description'], 0, 70)) . '...'; ?></p>

                            <div
                                style="color: var(--text-primary); font-size: 0.85rem; font-weight: 600; display:flex; align-items:center; gap:5px;">
                                Shop Collection <span>→</span>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-grid">
                <div>
                    <div class="footer-brand">FestiVmart</div>
                    <p style="margin-bottom: 1.5rem; line-height: 1.6; max-width: 280px;">
                        Nepal's premier e-commerce platform dedicated to celebrating our rich cultural heritage with
                        authentic festival products.
                    </p>
                    <div class="flex gap-1">
                        <span class="badge badge-primary">Authentic</span>
                        <span class="badge badge-primary">Local</span>
                    </div>
                </div>
                <div>
                    <h4 style="color: mediumpurple; margin-bottom: 1rem; font-size: 0.95rem;">Shop by Festival</h4>
                    <ul class="footer-links">
                        <li><a href="customer/shop.php?festival=1">Holi Collection</a></li>
                        <li><a href="customer/shop.php?festival=6">Dashain Special</a></li>
                        <li><a href="customer/shop.php?festival=7">Tihar Lights & Decor</a></li>
                        <li><a href="customer/shop.php?festival=4">Teej Offers</a></li>
                    </ul>
                </div>
                <div>
                    <h4 style="color: mediumpurple; margin-bottom: 1rem; font-size: 0.95rem;">Quick Links</h4>
                    <ul class="footer-links">
                        <li><a href="auth/login.php">My Account</a></li>
                        <li><a href="customer/cart.php">Shopping Cart</a></li>
                        <li><a href="#">About Us</a></li>
                        <li><a href="#">Contact Support</a></li>
                    </ul>
                </div>
                <div>
                    <h4 style="color: mediumpurple; margin-bottom: 1rem; font-size: 0.95rem;">Newsletter</h4>
                    <p style="margin-bottom: 1rem;">Get notified about upcoming festivals and exclusive offers!</p>
                    <div class="flex gap-1">
                        <input type="email" class="form-control" placeholder="Your Email..."
                            style="padding:0.5rem 0.8rem;">
                        <button class="btn btn-primary" style="padding:0.5rem 1rem;">Go</button>
                    </div>
                </div>
            </div>
            <div class="footer-bottom">
                &copy; <?php echo date('Y'); ?> FestiVmart Nepal. All rights reserved by DOKR.
            </div>
        </div>
    </footer>

    <script src="assets/js/main.js"></script>
</body>

</html>