<?php
require_once __DIR__ . '/../includes/functions.php';

$festival_id = isset($_GET['festival']) ? (int) $_GET['festival'] : 0;
$search = $_GET['search'] ?? '';
$sort = $_GET['sort'] ?? 'newest';
$minPrice = isset($_GET['min_price']) && is_numeric($_GET['min_price']) ? max(0, (float) $_GET['min_price']) : 0;
$maxPrice = isset($_GET['max_price']) && is_numeric($_GET['max_price']) ? max(0, (float) $_GET['max_price']) : 999999;

// Fetch products based on filters
$products = getProducts($search, $festival_id, $sort, $minPrice, $maxPrice);
$allFestivals = getAllFestivals();

// Theme if exploring a specific festival
$filterFestival = null;
if ($festival_id > 0) {
    global $pdo;
    $s = $pdo->prepare("SELECT * FROM festivals WHERE id = ?");
    $s->execute([$festival_id]);
    $filterFestival = $s->fetch();
}
$themeStyle = festivalThemeStyle($filterFestival);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shop - FestiVmart</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body <?php echo $themeStyle; ?>>

    <!-- Navbar -->
    <nav class="navbar scrolled">
        <a href="../homepage.php" class="navbar-brand">FestiVmart<span>Nepal's Festival Store</span></a>
        <div class="hamburger"><span></span><span></span><span></span></div>
        <ul class="nav-links">
            <li><a href="../homepage.php">Home</a></li>
            <li><a href="shop.php" class="active">Shop</a></li>
            <?php if (isLoggedIn()): ?>
                <li><a href="<?php echo isAdmin() ? '../admin/dashboard.php' : 'account.php'; ?>">Dashboard</a></li>
                <li><a href="cart.php" class="cart-badge">Cart <span
                            class="cart-count"><?php echo getCartCount(); ?></span></a></li>
                <li><a href="../auth/logout.php">Logout</a></li>
            <?php else: ?>
                <li><a href="../auth/login.php" class="btn-nav-cta">Log In</a></li>
            <?php endif; ?>
        </ul>
    </nav>

    <div class="page-wrapper container section">

        <?php if ($filterFestival): ?>
            <div class="section-header">
                <span class="section-tag"
                    style="background:var(--bg-card); border-color:var(--fest-primary);">COLLECTION</span>
                <h1 style="color:var(--fest-primary); margin-bottom:10px;">
                    <?php echo htmlspecialchars($filterFestival['name']); ?> Essentials
                </h1>
                <p><?php echo htmlspecialchars($filterFestival['description']); ?></p>
            </div>
        <?php else: ?>
            <div class="section-header">
                <h2>All Festival Products</h2>
                <p>Browse through hundreds of authentic Nepalese festival items.</p>
            </div>
        <?php endif; ?>

        <div class="shop-layout">
            <!-- Sidebar Filters -->
            <aside class="filter-sidebar">
                <form action="shop.php" method="GET">
                    <?php if ($search): ?><input type="hidden" name="search"
                            value="<?php echo htmlspecialchars($search); ?>"><?php endif; ?>

                    <div class="filter-group">
                        <div class="filter-title">Filter by Festival</div>
                        <label class="filter-option <?php echo $festival_id === 0 ? 'active' : ''; ?>">
                            <input type="radio" name="festival" value="0" <?php echo $festival_id === 0 ? 'checked' : ''; ?> onchange="this.form.submit()"> All Festivals
                        </label>
                        <?php foreach ($allFestivals as $f): ?>
                            <label class="filter-option <?php echo $festival_id === $f['id'] ? 'active' : ''; ?>">
                                <input type="radio" name="festival" value="<?php echo $f['id']; ?>" <?php echo $festival_id === $f['id'] ? 'checked' : ''; ?> onchange="this.form.submit()">
                                <span class="color-dot"
                                    style="background: <?php echo htmlspecialchars($f['theme_color']); ?>;"></span>
                                <?php echo htmlspecialchars($f['name']); ?>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <div class="divider"></div>

                    <div class="filter-group">
                        <div class="filter-title">Price Range (Rs.)</div>
                        <div class="flex gap-1 items-center mb-1">
                            <input type="number" min="0" step="1" name="min_price" class="form-control"
                                value="<?php echo $minPrice > 0 ? $minPrice : ''; ?>" placeholder="Min">
                            <span class="text-faint">-</span>
                            <input type="number" min="0" step="1" name="max_price" class="form-control"
                                value="<?php echo $maxPrice < 999999 ? $maxPrice : ''; ?>" placeholder="Max">
                        </div>
                    </div>

                    <div class="divider"></div>

                    <div class="filter-group">
                        <div class="filter-title">Sort By</div>
                        <select name="sort" class="form-control" onchange="this.form.submit()">
                            <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Newest Arrivals
                            </option>
                            <option value="price_asc" <?php echo $sort === 'price_asc' ? 'selected' : ''; ?>>Price: Low to
                                High</option>
                            <option value="price_desc" <?php echo $sort === 'price_desc' ? 'selected' : ''; ?>>Price: High
                                to Low</option>
                            <option value="featured" <?php echo $sort === 'featured' ? 'selected' : ''; ?>>Featured Items
                            </option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-outline w-full mb-1">Apply Filters</button>
                    <?php if ($festival_id > 0 || $sort !== 'newest' || $minPrice > 0 || $maxPrice < 999999): ?>
                        <a href="shop.php" class="btn btn-primary w-full"
                            style="background:transparent; border:1px solid var(--border); color:var(--text-muted);">Clear
                            Filters</a>
                    <?php endif; ?>
                </form>
            </aside>

            <!-- Main Grid -->
            <main>
                <!-- Search Bar -->
                <form action="shop.php" method="GET" class="mb-3">
                    <?php if ($festival_id > 0): ?><input type="hidden" name="festival"
                            value="<?php echo $festival_id; ?>"><?php endif; ?>
                    <div class="input-wrap">
                        <span class="input-prefix">🔍</span>
                        <input type="text" name="search" class="form-control has-prefix"
                            placeholder="Search for items, categories..."
                            value="<?php echo htmlspecialchars($search); ?>">
                        <button type="submit" class="btn btn-primary"
                            style="position: absolute; right: 4px; top: 4px; bottom: 4px; border-radius: 6px; padding: 0 1.5rem;">Search</button>
                    </div>
                </form>

                <div class="flex flex-between items-center mb-2">
                    <div class="text-muted text-sm">Showing <strong><?php echo count($products); ?></strong> products
                    </div>
                </div>

                <?php if (count($products) > 0): ?>
                    <div class="products-grid">
                        <?php foreach ($products as $product):
                            $price = $product['price'];
                            $hasDiscount = $product['discount_percent'] > 0;
                            $finalPrice = $hasDiscount ? getDiscountedPrice($price, $product['discount_percent']) : $price;
                            ?>
                            <div class="card product-card">
                                <div class="product-image-wrap">
                                    <?php if (!empty($product['image'])): ?>
                                        <img src="../assets/uploads/products/<?php echo htmlspecialchars($product['image']); ?>"
                                            alt="<?php echo htmlspecialchars($product['name']); ?>" class="product-img">
                                    <?php else: ?>
                                        <div class="product-emoji-placeholder">🛍️</div>
                                    <?php endif; ?>
                                    <?php if ($product['is_preorder']): ?>
                                        <div class="product-badge preorder">Pre-Order</div>
                                    <?php elseif ($hasDiscount): ?>
                                        <div class="product-discount-badge">-<?php echo (int) $product['discount_percent']; ?>%
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="product-body">
                                    <?php if (isset($product['festival_name']) && !$filterFestival): ?>
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
                                            <form action="cart.php" method="POST" style="flex:1;">
                                                <input type="hidden" name="action" value="add">
                                                <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                                <button type="submit"
                                                    class="btn <?php echo $product['is_preorder'] ? 'btn-outline' : 'btn-primary'; ?> w-full add-to-cart-btn">
                                                    <?php echo $product['is_preorder'] ? 'Pre-Book' : 'Add to Cart'; ?>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="card" style="padding: 4rem 2rem; text-align: center;">
                        <div class="emoji-lg mb-2">🎭</div>
                        <h3>No Products Found</h3>
                        <p class="text-muted mb-2">Try adjusting your filters or searching for something else.</p>
                        <a href="shop.php" class="btn btn-primary">Clear All Filters</a>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
</body>

</html>