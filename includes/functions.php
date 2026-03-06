<?php
require_once __DIR__ . '/header.php';

// ── Festival Helpers ─────────────────────────────────────────────
function getCurrentFestival()
{
    global $pdo;
    $today = date('Y-m-d');
    $s = $pdo->prepare("SELECT * FROM festivals WHERE start_date <= ? AND end_date >= ? LIMIT 1");
    $s->execute([$today, $today]);
    return $s->fetch();
}

function getNextFestival()
{
    global $pdo;
    $today = date('Y-m-d');
    $s = $pdo->prepare("SELECT * FROM festivals WHERE start_date > ? ORDER BY start_date ASC LIMIT 1");
    $s->execute([$today]);
    return $s->fetch();
}

function getAllFestivals()
{
    global $pdo;
    return $pdo->query("SELECT * FROM festivals ORDER BY start_date ASC")->fetchAll();
}

function getUpcomingFestivals($limit = 4)
{
    global $pdo;
    $today = date('Y-m-d');
    $s = $pdo->prepare("SELECT * FROM festivals WHERE start_date >= ? ORDER BY start_date ASC LIMIT ?");
    $s->execute([$today, $limit]);
    return $s->fetchAll();
}

function getFestivalCountdownDays($festival)
{
    if (!$festival)
        return null;
    $today = new DateTime();
    $start = new DateTime($festival['start_date']);
    if ($today > $start)
        return 0; // ongoing
    return (int) $today->diff($start)->days;
}

// ── Product Helpers ──────────────────────────────────────────────
function getProductsByFestival($festival_id, $limit = 8)
{
    global $pdo;
    $s = $pdo->prepare("SELECT p.*, f.name AS festival_name, f.theme_color, f.accent_color, c.name AS category_name
        FROM products p
        LEFT JOIN festivals f ON p.festival_id = f.id
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.festival_id = ?
        ORDER BY p.is_featured DESC, p.created_at DESC
        LIMIT ?");
    $s->execute([$festival_id, $limit]);
    return $s->fetchAll();
}

function getFeaturedProducts($limit = 8)
{
    global $pdo;
    $s = $pdo->prepare("SELECT p.*, f.name AS festival_name, f.theme_color, f.accent_color
        FROM products p
        LEFT JOIN festivals f ON p.festival_id = f.id
        WHERE p.is_featured = 1
        ORDER BY p.created_at DESC
        LIMIT ?");
    $s->execute([$limit]);
    return $s->fetchAll();
}

function getProducts($search = '', $festival_id = 0, $sort = 'newest', $min_price = 0, $max_price = 999999)
{
    global $pdo;
    $sql = "SELECT p.*, f.name AS festival_name, f.theme_color, f.slug AS festival_slug
            FROM products p
            LEFT JOIN festivals f ON p.festival_id = f.id
            WHERE p.price BETWEEN :min AND :max";
    $params = [':min' => $min_price, ':max' => $max_price];

    if ($search) {
        $sql .= " AND (p.name LIKE :search OR p.description LIKE :search2)";
        $params[':search'] = "%$search%";
        $params[':search2'] = "%$search%";
    }
    if ($festival_id > 0) {
        $sql .= " AND p.festival_id = :fid";
        $params[':fid'] = $festival_id;
    }
    $orderMap = [
        'newest' => 'p.created_at DESC',
        'price_asc' => 'p.price ASC',
        'price_desc' => 'p.price DESC',
        'featured' => 'p.is_featured DESC, p.created_at DESC',
    ];
    $sql .= " ORDER BY " . ($orderMap[$sort] ?? 'p.created_at DESC');
    $s = $pdo->prepare($sql);
    $s->execute($params);
    return $s->fetchAll();
}

function getProductById($id)
{
    global $pdo;
    $s = $pdo->prepare("SELECT p.*, f.name AS festival_name, f.theme_color, f.accent_color
        FROM products p LEFT JOIN festivals f ON p.festival_id = f.id WHERE p.id = ?");
    $s->execute([$id]);
    return $s->fetch();
}

function getProductRating($product_id)
{
    global $pdo;
    $s = $pdo->prepare("SELECT AVG(rating) AS avg_rating, COUNT(*) AS total FROM reviews WHERE product_id = ?");
    $s->execute([$product_id]);
    return $s->fetch();
}

// ── Cart Helpers ─────────────────────────────────────────────────
function getCartCount()
{
    global $pdo;
    if (!isset($_SESSION['user_id']))
        return 0;
    $s = $pdo->prepare("SELECT COALESCE(SUM(quantity),0) FROM cart WHERE user_id = ?");
    $s->execute([$_SESSION['user_id']]);
    return (int) $s->fetchColumn();
}

function getCartItems()
{
    global $pdo;
    if (!isset($_SESSION['user_id']))
        return [];
    $s = $pdo->prepare("SELECT c.id AS cart_id, c.quantity, p.id AS product_id, p.name, p.price,
        p.discount_percent, p.image, p.stock, f.theme_color
        FROM cart c
        JOIN products p ON c.product_id = p.id
        LEFT JOIN festivals f ON p.festival_id = f.id
        WHERE c.user_id = ?");
    $s->execute([$_SESSION['user_id']]);
    return $s->fetchAll();
}

function getCartTotal()
{
    $items = getCartItems();
    $subtotal = 0;
    foreach ($items as $item) {
        $price = getDiscountedPrice($item['price'], $item['discount_percent']);
        $subtotal += $price * $item['quantity'];
    }
    return round($subtotal, 2);
}

// ── Coupon Helpers ───────────────────────────────────────────────
function validateCoupon($code, $order_total)
{
    global $pdo;
    $today = date('Y-m-d H:i:s');
    $s = $pdo->prepare("SELECT * FROM coupons WHERE code = ? AND is_active = 1 AND expires_at >= ? AND min_spend <= ?");
    $s->execute([$code, $today, $order_total]);
    return $s->fetch();
}

// ── Auth Helpers ─────────────────────────────────────────────────
function requireLogin($redirect = null)
{
    if (!isset($_SESSION['user_id'])) {
        $target = $redirect ?? $_SERVER['REQUEST_URI'];
        header("Location: " . SITE_URL . "/auth/login.php?redirect=" . urlencode($target));
        exit;
    }
}

function requireAdmin()
{
    requireLogin();
    if (($_SESSION['role'] ?? '') !== 'admin') {
        header("Location: " . SITE_URL . "/homepage.php");
        exit;
    }
}

function isLoggedIn()
{
    return isset($_SESSION['user_id']);
}
function isAdmin()
{
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

// ── Formatting ───────────────────────────────────────────────────
function sanitize($v)
{
    return htmlspecialchars(strip_tags(trim($v)));
}
function formatPrice($p)
{
    return 'Rs. ' . number_format($p, 2);
}
function getDiscountedPrice($p, $d)
{
    return $p - ($p * $d / 100);
}

function starRating($rating, $total = 0)
{
    $html = '<span class="stars">';
    for ($i = 1; $i <= 5; $i++) {
        $html .= $i <= round($rating) ? '<span class="star filled">★</span>' : '<span class="star">☆</span>';
    }
    $html .= '</span>';
    if ($total > 0)
        $html .= ' <small>(' . $total . ')</small>';
    return $html;
}

// ── Festival Theme CSS vars ──────────────────────────────────────
function festivalThemeStyle($festival)
{
    if (!$festival)
        return '';
    return 'style="--fest-primary:' . $festival['theme_color'] . ';--fest-accent:' . $festival['accent_color'] . ';"';
}

// ── Admin Stats ──────────────────────────────────────────────────
function getAdminStats()
{
    global $pdo;
    return [
        'total_sales' => $pdo->query("SELECT COALESCE(SUM(total),0) FROM orders WHERE status != 'Cancelled'")->fetchColumn(),
        'total_orders' => $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn(),
        'total_users' => $pdo->query("SELECT COUNT(*) FROM users WHERE role='customer'")->fetchColumn(),
        'total_products' => $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn(),
        'pending_orders' => $pdo->query("SELECT COUNT(*) FROM orders WHERE status='Pending'")->fetchColumn(),
    ];
}
