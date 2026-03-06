<?php
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$success = '';
$error = '';

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $id = $_POST['product_id'] ?? 0;
        $name = trim($_POST['name']);
        $desc = trim($_POST['description']);
        $price = (float) $_POST['price'];
        $stock = (int) $_POST['stock'];
        $festival_id = empty($_POST['festival_id']) ? null : (int) $_POST['festival_id'];
        $category_id = empty($_POST['category_id']) ? null : (int) $_POST['category_id'];
        $discount = (float) $_POST['discount_percent'];
        $is_featured = isset($_POST['is_featured']) ? 1 : 0;
        $is_preorder = isset($_POST['is_preorder']) ? 1 : 0;
        $image_name = $_POST['existing_image'] ?? '';

        // Handle Image Upload
        if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['product_image'];
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $max_size = 5 * 1024 * 1024; // 5MB

            if (!in_array($file['type'], $allowed_types)) {
                $error = "Invalid file type. Only JPG, PNG, GIF, and WebP are allowed.";
            } elseif ($file['size'] > $max_size) {
                $error = "File is too large. Maximum size is 5MB.";
            } else {
                // Determine festival directory
                $fest_dir = 'general';
                if ($festival_id) {
                    $stmt_f = $pdo->prepare("SELECT name FROM festivals WHERE id = ?");
                    $stmt_f->execute([$festival_id]);
                    $f_data = $stmt_f->fetch();
                    if ($f_data) {
                        $fest_dir = strtolower(str_replace(' ', '_', $f_data['name']));
                    }
                }

                $upload_dir = "../assets/uploads/products/$fest_dir/";
                if (!is_dir($upload_dir))
                    mkdir($upload_dir, 0777, true);

                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $new_filename = uniqid('prod_') . '.' . $ext;
                $target_path = $upload_dir . $new_filename;

                if (move_uploaded_file($file['tmp_name'], $target_path)) {
                    // Delete old image if it exists and we're editing
                    if ($action === 'edit' && !empty($image_name)) {
                        $old_path = "../assets/uploads/products/" . $image_name;
                        if (file_exists($old_path))
                            unlink($old_path);
                    }
                    $image_name = "$fest_dir/$new_filename";
                } else {
                    $error = "Failed to upload image.";
                }
            }
        }

        if (empty($error)) {
            if ($action === 'add') {
                $s = $pdo->prepare("INSERT INTO products (name, description, price, stock, festival_id, category_id, discount_percent, is_featured, is_preorder, image) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $s->execute([$name, $desc, $price, $stock, $festival_id, $category_id, $discount, $is_featured, $is_preorder, $image_name]);
                $success = "Product added successfully.";
            } else {
                $s = $pdo->prepare("UPDATE products SET name=?, description=?, price=?, stock=?, festival_id=?, category_id=?, discount_percent=?, is_featured=?, is_preorder=?, image=? WHERE id=?");
                $s->execute([$name, $desc, $price, $stock, $festival_id, $category_id, $discount, $is_featured, $is_preorder, $image_name, $id]);
                $success = "Product updated successfully.";
            }
        }
    } elseif ($action === 'delete') {
        $id = (int) $_POST['product_id'];

        // Delete image file if exists
        $s_img = $pdo->prepare("SELECT image FROM products WHERE id = ?");
        $s_img->execute([$id]);
        $prod = $s_img->fetch();
        if ($prod && !empty($prod['image'])) {
            $old_path = "../assets/uploads/products/" . $prod['image'];
            if (file_exists($old_path))
                unlink($old_path);
        }

        $s = $pdo->prepare("DELETE FROM products WHERE id=?");
        $s->execute([$id]);
        $success = "Product deleted.";
    }
}

// Fetch all products
$search = $_GET['search'] ?? '';
$sql = "SELECT p.*, f.name as festival_name, c.name as category_name 
        FROM products p 
        LEFT JOIN festivals f ON p.festival_id = f.id 
        LEFT JOIN categories c ON p.category_id = c.id";
if ($search) {
    $sql .= " WHERE p.name LIKE :search";
}
$sql .= " ORDER BY p.id DESC";

$stmt = $pdo->prepare($sql);
if ($search)
    $stmt->execute([':search' => "%$search%"]);
else
    $stmt->execute();
$products = $stmt->fetchAll();

$festivals = getAllFestivals();
$categories = $pdo->query("SELECT * FROM categories")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Products - FestiVmart Admin</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>

<body>

    <!-- Sidebar -->
    <aside class="admin-sidebar" id="sidebar">
        <div class="sidebar-logo">FestiVmart<small>Admin Portal</small></div>
        <nav class="sidebar-nav">
            <div class="nav-section-title">Menu</div>
            <a href="dashboard.php" class="sidebar-link"><span class="nav-icon">📊</span> Dashboard</a>
            <a href="products.php" class="sidebar-link active"><span class="nav-icon">📦</span> Products</a>
            <a href="orders.php" class="sidebar-link"><span class="nav-icon">🛒</span> Orders</a>
            <a href="users.php" class="sidebar-link"><span class="nav-icon">👥</span> Users</a>
            <a href="coupons.php" class="sidebar-link"><span class="nav-icon">🎟️</span> Coupons</a>
            <div class="nav-section-title mt-2">Configuration</div>
            <a href="festival_settings.php" class="sidebar-link"><span class="nav-icon">🎭</span> Festival Settings</a>
        </nav>
        <div class="sidebar-footer">
            <a href="../auth/logout.php" class="sidebar-link logout"><span class="nav-icon">🚪</span> Logout</a>
        </div>
    </aside>

    <main class="admin-main">
        <header class="admin-topbar">
            <div class="flex items-center gap-1">
                <button id="menu-toggle" class="btn btn-outline btn-sm hidden" style="border:none;">☰</button>
                <h1 class="topbar-title">Manage Products</h1>
            </div>
            <div class="topbar-user">
                <span>
                    <?php echo htmlspecialchars($_SESSION['full_name']); ?>
                </span>
                <div class="admin-avatar">A</div>
            </div>
        </header>

        <div class="admin-content">
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

            <div class="toolbar flex-between">
                <form action="products.php" method="GET" class="flex gap-1" style="flex:1;">
                    <input type="text" name="search" class="form-control" placeholder="Search products..."
                        value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="btn btn-outline">Search</button>
                    <?php if ($search): ?><a href="products.php" class="btn btn-outline">Clear</a>
                    <?php endif; ?>
                </form>
                <button class="btn btn-primary" onclick="openModal('addModal')">+ Add Product</button>
            </div>

            <div class="admin-section">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Product Name</th>
                            <th>Price</th>
                            <th>Stock</th>
                            <th>Festival</th>
                            <th>Status/Tags</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $p): ?>
                            <tr>
                                <td>
                                    <?php echo $p['id']; ?>
                                </td>
                                <td style="font-weight:600;">
                                    <?php echo htmlspecialchars($p['name']); ?>
                                </td>
                                <td>
                                    <?php echo formatPrice($p['price']); ?>
                                </td>
                                <td>
                                    <?php if ($p['stock'] > 10): ?>
                                        <span class="active-badge">
                                            <?php echo $p['stock']; ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="inactive-badge">
                                            <?php echo $p['stock']; ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($p['festival_name'] ?? '-'); ?>
                                </td>
                                <td>
                                    <?php if ($p['is_featured']): ?><span class="featured-badge">Featured</span>
                                    <?php endif; ?>
                                    <?php if ($p['is_preorder']): ?><span class="preorder-badge">Pre-order</span>
                                    <?php endif; ?>
                                    <?php if ($p['discount_percent'] > 0): ?><span class="status-badge"
                                            style="background:rgba(231,76,60,0.15); color:#e74c3c;">-
                                            <?php echo (int) $p['discount_percent']; ?>%
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="flex gap-1">
                                        <button class="btn btn-outline btn-xs"
                                            onclick='editProduct(<?php echo json_encode($p); ?>)'>Edit</button>
                                        <form action="products.php" method="POST"
                                            onsubmit="return confirm('Delete this product?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="product_id" value="<?php echo $p['id']; ?>">
                                            <button type="submit" class="btn btn-danger btn-xs">Del</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($products)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-3 text-muted">No products found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- Modal: Add / Edit -->
    <div class="modal-overlay" id="addModal">
        <div class="modal-box">
            <div class="modal-header">
                <h3 class="modal-title" id="modalTitle">Add Product</h3>
                <span class="modal-close" onclick="closeModal('addModal')">&times;</span>
            </div>

            <form action="products.php" method="POST" id="productForm" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add" id="formAction">
                <input type="hidden" name="product_id" value="0" id="prodId">
                <input type="hidden" name="existing_image" value="" id="prodExistingImg">

                <div class="form-group">
                    <label class="form-label">Product Name</label>
                    <input type="text" name="name" id="prodName" class="form-control" required>
                </div>

                <div class="grid-2 mb-2">
                    <div class="form-group mb-0">
                        <label class="form-label">Base Price (Rs.)</label>
                        <input type="number" step="0.01" name="price" id="prodPrice" class="form-control" required>
                    </div>
                    <div class="form-group mb-0">
                        <label class="form-label">Stock Quantity</label>
                        <input type="number" name="stock" id="prodStock" class="form-control" required>
                    </div>
                </div>

                <div class="grid-2 mb-2">
                    <div class="form-group mb-0">
                        <label class="form-label">Festival (Optional)</label>
                        <select name="festival_id" id="prodFest" class="form-control">
                            <option value="">None</option>
                            <?php foreach ($festivals as $f): ?>
                                <option value="<?php echo $f['id']; ?>">
                                    <?php echo htmlspecialchars($f['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group mb-0">
                        <label class="form-label">Category</label>
                        <select name="category_id" id="prodCat" class="form-control">
                            <option value="">None</option>
                            <?php foreach ($categories as $c): ?>
                                <option value="<?php echo $c['id']; ?>">
                                    <?php echo htmlspecialchars($c['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" id="prodDesc" class="form-control" rows="3"></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label">Product Image (Max 5MB)</label>
                    <input type="file" name="product_image" id="prodImgInput" class="form-control" accept="image/*"
                        onchange="previewImage(this)">
                    <div id="imgPreviewWrap" class="mt-1" style="display:none;">
                        <img id="imgPreview" src=""
                            style="max-height:100px; border-radius:8px; border:1px solid var(--admin-border);">
                        <p class="text-muted" style="font-size:0.7rem;">Current image</p>
                    </div>
                </div>

                <div class="grid-2 mb-3">
                    <div class="form-group mb-0">
                        <label class="form-label">Discount (%)</label>
                        <input type="number" step="0.01" name="discount_percent" id="prodDisc" class="form-control"
                            value="0.00">
                    </div>
                    <div class="form-group mb-0 flex items-center gap-1 mt-2">
                        <label class="flex items-center gap-1"
                            style="font-size:0.85rem; color:var(--text-muted); cursor:pointer;">
                            <input type="checkbox" name="is_featured" id="prodFeat" value="1"> Featured
                        </label>
                        <label class="flex items-center gap-1"
                            style="font-size:0.85rem; color:var(--text-muted); cursor:pointer; margin-left:1rem;">
                            <input type="checkbox" name="is_preorder" id="prodPre" value="1"> Pre-order
                        </label>
                    </div>
                </div>

                <div class="flex flex-between mt-3">
                    <button type="button" class="btn btn-outline" onclick="closeModal('addModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Product</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const menuBtn = document.getElementById('menu-toggle');
        const sidebar = document.getElementById('sidebar');
        if (window.innerWidth <= 900) menuBtn.classList.remove('hidden');
        menuBtn.addEventListener('click', () => sidebar.classList.toggle('open'));

        function openModal(id) { document.getElementById(id).classList.add('open'); }
        function closeModal(id) {
            document.getElementById(id).classList.remove('open');
            if (id === 'addModal') {
                document.getElementById('productForm').reset();
                document.getElementById('formAction').value = 'add';
                document.getElementById('modalTitle').innerText = 'Add Product';
                document.getElementById('imgPreviewWrap').style.display = 'none';
                document.getElementById('prodExistingImg').value = '';
            }
        }

        function previewImage(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function (e) {
                    document.getElementById('imgPreview').src = e.target.result;
                    document.getElementById('imgPreviewWrap').style.display = 'block';
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        function editProduct(p) {
            document.getElementById('formAction').value = 'edit';
            document.getElementById('prodId').value = p.id;
            document.getElementById('prodName').value = p.name;
            document.getElementById('prodPrice').value = p.price;
            document.getElementById('prodStock').value = p.stock;
            document.getElementById('prodFest').value = p.festival_id || '';
            document.getElementById('prodCat').value = p.category_id || '';
            document.getElementById('prodDesc').value = p.description;
            document.getElementById('prodDisc').value = p.discount_percent;
            document.getElementById('prodFeat').checked = p.is_featured == 1;
            document.getElementById('prodPre').checked = p.is_preorder == 1;
            document.getElementById('prodExistingImg').value = p.image || '';

            if (p.image) {
                document.getElementById('imgPreview').src = '../assets/uploads/products/' + p.image;
                document.getElementById('imgPreviewWrap').style.display = 'block';
            } else {
                document.getElementById('imgPreviewWrap').style.display = 'none';
            }

            document.getElementById('modalTitle').innerText = 'Edit Product';
            openModal('addModal');
        }
    </script>
</body>

</html>