<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$success = '';

function generateSlug($string) {
    $slug = preg_replace('/[^A-Za-z0-9-]+/', '-', $string);
    return strtolower(trim($slug, '-'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $id = (int) ($_POST['festival_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $start = $_POST['start_date'] ?? '';
        $end = $_POST['end_date'] ?? '';
        $color = trim($_POST['theme_color'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $slug = generateSlug($name);

        // Validate dates
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
            $success = "Invalid date format.";
        } elseif (strtotime($start) === false || strtotime($end) === false) {
            $success = "Invalid date value.";
        } else {
            if ($action === 'add') {
                $stmt = $pdo->prepare("INSERT INTO festivals (name, slug, start_date, end_date, theme_color, description) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$name, $slug, $start, $end, $color, $desc]);
                $success = "Festival added.";
            } else {
                if ($id < 1) {
                    $success = "Missing festival ID for edit.";
                } else {
                    $stmt = $pdo->prepare("UPDATE festivals SET name=?, slug=?, start_date=?, end_date=?, theme_color=?, description=? WHERE id=?");
                    $stmt->execute([$name, $slug, $start, $end, $color, $desc, $id]);
                    $success = "Festival details updated.";
                }
            }
        }
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['festival_id'] ?? 0);
        if ($id < 1) {
            $success = "Missing festival ID for delete.";
        } else {
            $stmt = $pdo->prepare("DELETE FROM festivals WHERE id=?");
            $stmt->execute([$id]);
            $success = "Festival deleted.";
        }
    }
}

$festivals = getAllFestivals();

// To find next upcoming:
$upcoming = array_filter($festivals, function ($f) {
    return strtotime($f['start_date']) > time();
});
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Festival Settings - FestiVmart</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        .fest-color-blob {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.5);
            border: 2px solid #fff;
        }
    </style>
</head>

<body>

    <aside class="admin-sidebar" id="sidebar">
        <div class="sidebar-logo">FestiVmart<small>Admin Portal</small></div>
        <nav class="sidebar-nav">
            <div class="nav-section-title">Menu</div>
            <a href="dashboard.php" class="sidebar-link"><span class="nav-icon">📊</span> Dashboard</a>
            <a href="products.php" class="sidebar-link"><span class="nav-icon">📦</span> Products</a>
            <a href="orders.php" class="sidebar-link"><span class="nav-icon">🛒</span> Orders</a>
            <a href="users.php" class="sidebar-link"><span class="nav-icon">👥</span> Users</a>
            <a href="coupons.php" class="sidebar-link"><span class="nav-icon">🎟️</span> Coupons</a>
            <div class="nav-section-title mt-2">Configuration</div>
            <a href="festival_settings.php" class="sidebar-link active"><span class="nav-icon">🎭</span> Festival
                Settings</a>
        </nav>
        <div class="sidebar-footer">
            <a href="../auth/logout.php" class="sidebar-link logout"><span class="nav-icon">🚪</span> Logout</a>
        </div>
    </aside>

    <main class="admin-main">
        <header class="admin-topbar">
            <div class="flex items-center gap-1">
                <button id="menu-toggle" class="btn btn-outline btn-sm hidden" style="border:none;">☰</button>
                <h1 class="topbar-title">Festival Configuration</h1>
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

            <div class="toolbar flex-between">
                <div>Configure festival dates to control homepage banners and themes.</div>
                <button class="btn btn-primary" onclick="openModal('addModal')">+ Add Festival</button>
            </div>

            <div class="admin-section">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Theme</th>
                            <th>Festival Name</th>
                            <th>Active Dates</th>
                            <th>Description</th>
                            <th>Status (Auto)</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $now = time();
                        foreach ($festivals as $f):
                            $start = strtotime($f['start_date']);
                            $end = strtotime($f['end_date'] . ' 23:59:59');

                            $status = "Past";
                            $status_class = "inactive-badge";

                            if ($now >= $start && $now <= $end) {
                                $status = "Live Now";
                                $status_class = "active-badge";
                            } elseif ($now < $start) {
                                $status = "Upcoming";
                                $status_class = "status-badge status-Processing";
                            }
                            ?>
                            <tr>
                                <td>
                                    <div class="fest-color-blob"
                                        style="background-color: <?php echo htmlspecialchars($f['theme_color']); ?>;"></div>
                                </td>
                                <td style="font-weight:600; font-size:1.05rem;">
                                    <?php echo htmlspecialchars($f['name']); ?>
                                </td>
                                <td>
                                    <div>
                                        <?php echo date('M d, Y', $start); ?>
                                    </div>
                                    <div class="text-faint text-sm">to
                                        <?php echo date('M d, Y', $end); ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="text-muted" style="font-size:0.85rem; max-width:300px;">
                                        <?php echo htmlspecialchars($f['description']); ?>
                                    </div>
                                </td>
                                <td><span class="<?php echo $status_class; ?>">
                                        <?php echo $status; ?>
                                    </span></td>
                                <td>
                                    <div class="flex gap-1">
                                        <button class="btn btn-outline btn-xs"
                                            onclick='editFestival(<?php echo json_encode($f); ?>)'>Edit</button>
                                        <form action="festival_settings.php" method="POST"
                                            onsubmit="return confirm('Delete festival?\nProducts linked to this festival will remain but lose the tag.');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="festival_id" value="<?php echo $f['id']; ?>">
                                            <button type="submit" class="btn btn-danger btn-xs">Del</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($festivals)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-3 text-muted">No festivals registered.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="card p-3"
                style="background: rgba(108, 92, 231, 0.05); border: 1px dashed var(--admin-primary);">
                <h3 style="color:var(--admin-primary); margin-bottom:10px;">💡 How the Live System Works</h3>
                <p class="text-muted mb-2">
                    The FestiVmart homepage <strong>automatically updates</strong> based on the dates configured here.
                </p>
                <ul class="text-muted" style="margin-left:20px;">
                    <li>If a festival is currently <strong>Live Now</strong>, the homepage adopts its Theme Color and
                        shows an active banner.</li>
                    <li>If no festival is live, it checks for <strong>Upcoming</strong> festivals and displays a
                        countdown timer.</li>
                    <li>If nothing is live or upcoming, a generic Nepal Heritage fallback theme is displayed.</li>
                </ul>
            </div>

        </div>
    </main>

    <!-- Modal Form -->
    <div class="modal-overlay" id="addModal">
        <div class="modal-box" style="max-width: 500px;">
            <div class="modal-header">
                <h3 class="modal-title" id="modalTitle">Add Festival</h3>
                <span class="modal-close" onclick="closeModal('addModal')">&times;</span>
            </div>

            <form action="festival_settings.php" method="POST" id="festForm">
                <input type="hidden" name="action" value="add" id="formAction">
                <input type="hidden" name="festival_id" value="0" id="fId">

                <div class="form-group">
                    <label class="form-label">Festival Name</label>
                    <input type="text" name="name" id="fName" class="form-control" required
                        placeholder="E.g., Dashain Festival">
                </div>

                <div class="grid-2 mb-2">
                    <div class="form-group mb-0">
                        <label class="form-label">Start Date</label>
                        <input type="date" name="start_date" id="fStart" class="form-control" required>
                    </div>
                    <div class="form-group mb-0">
                        <label class="form-label">End Date</label>
                        <input type="date" name="end_date" id="fEnd" class="form-control" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Theme Color Override (Hex Code)</label>
                    <div class="flex items-center gap-1">
                        <input type="color" id="colorPicker"
                            style="height:42px; width:42px; border:none; background:transparent; cursor:pointer;"
                            onchange="document.getElementById('fColor').value = this.value">
                        <input type="text" name="theme_color" id="fColor" class="form-control" required
                            placeholder="E.g., #e74c3c" style="flex:1;">
                    </div>
                    <div class="text-faint text-sm mt-1">This color will be used for buttons, banners, and highlights
                        when the festival is active.</div>
                </div>

                <div class="form-group">
                    <label class="form-label">Brief Description / Banner Subtitle</label>
                    <textarea name="description" id="fDesc" class="form-control" rows="2"
                        placeholder="Experience the joy..."></textarea>
                </div>

                <div class="flex flex-between mt-3">
                    <button type="button" class="btn btn-outline" onclick="closeModal('addModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Festival</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.getElementById('menu-toggle').addEventListener('click', () => document.getElementById('sidebar').classList.toggle('open'));
        if (window.innerWidth <= 900) document.getElementById('menu-toggle').classList.remove('hidden');

        function openModal(id) { document.getElementById(id).classList.add('open'); }
        function closeModal(id) {
            document.getElementById(id).classList.remove('open');
            if (id === 'addModal') {
                document.getElementById('festForm').reset();
                document.getElementById('formAction').value = 'add';
                document.getElementById('modalTitle').innerText = 'Add Festival';
                document.getElementById('fColor').value = '#6c5ce7';
                document.getElementById('colorPicker').value = '#6c5ce7';
            }
        }

        function editFestival(f) {
            document.getElementById('formAction').value = 'edit';
            document.getElementById('fId').value = f.id;
            document.getElementById('fName').value = f.name;
            document.getElementById('fStart').value = f.start_date;
            document.getElementById('fEnd').value = f.end_date;
            document.getElementById('fColor').value = f.theme_color;
            document.getElementById('colorPicker').value = f.theme_color;
            document.getElementById('fDesc').value = f.description;

            document.getElementById('modalTitle').innerText = 'Edit Festival';
            openModal('addModal');
        }
    </script>
</body>

</html>