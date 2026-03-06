<?php
require_once __DIR__ . '/../includes/functions.php';

if (isLoggedIn()) {
    $redirect = isAdmin() ? SITE_URL . '/admin/dashboard.php' : SITE_URL . '/customer/shop.php';
    header("Location: " . $redirect);
    exit;
}

$error = '';
$redirect = $_GET['redirect'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $loginData = trim($_POST['login_data'] ?? ''); // Email, Phone, or Username
    $password = $_POST['password'] ?? '';
    $redirect = $_POST['redirect'] ?? '';

    if (empty($loginData) || empty($password)) {
        $error = "Please enter all fields.";
    } else {
        // Query to match email, phone, OR username
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? OR phone = ? OR username = ? LIMIT 1");
        $stmt->execute([$loginData, $loginData, $loginData]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // Login success
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['full_name'] = $user['full_name'];

            // Role-based or Intent-based redirect
            if ($user['role'] === 'admin') {
                header("Location: " . SITE_URL . "/admin/dashboard.php");
            } else {
                if ($redirect) {
                    header("Location: " . $redirect);
                } else {
                    // Default for customer: if they came from homepage -> shop.php (Requested logic: 'redirect to shoping page')
                    header("Location: " . SITE_URL . "/customer/shop.php");
                }
            }
            exit;
        } else {
            $error = "Invalid credentials. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - FestiVmart</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body class="auth-page">

    <div class="auth-card">
        <div class="auth-logo">
            <a href="../homepage.php" class="auth-logo-text">FestiVmart</a>
        </div>

        <h1 class="auth-title">Welcome Back</h1>
        <p class="auth-subtitle">Login to access festival offers and your cart.</p>

        <?php if ($error): ?>
            <div class="alert alert-error">❌
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form action="login.php" method="POST">
            <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($redirect); ?>">

            <div class="form-group">
                <label class="form-label">EMAIL / PHONE / USERNAME</label>
                <input type="text" name="login_data" class="form-control" placeholder="Enter your detail..." required
                    autofocus>
            </div>

            <div class="form-group">
                <label class="form-label flex flex-between">
                    <span>PASSWORD</span>
                    <a href="#" class="text-faint" style="font-size:0.75rem">Forgot Password?</a>
                </label>
                <div class="input-wrap">
                    <input type="password" name="password" id="loginPass" class="form-control" placeholder="••••••••"
                        required>
                    <span class="input-icon toggle-password" data-target="loginPass">👁️</span>
                </div>
            </div>

            <button type="submit" class="btn btn-primary w-full mt-2">Log In</button>
        </form>

        <div class="auth-divider"><span>OR</span></div>

        <div class="auth-switch">
            Don't have an account? <a href="signup.php">Sign Up</a>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
</body>

</html>