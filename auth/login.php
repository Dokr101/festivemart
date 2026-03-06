<?php
require_once __DIR__ . '/../includes/functions.php';

// Handle redirects if already logged in
if (isLoggedIn()) {
    $redirect = isAdmin() ? SITE_URL . '/admin/dashboard.php' : SITE_URL . '/customer/shop.php';
    header("Location: " . $redirect);
    exit;
}

$error = '';
$success = '';
$login_error = '';
$signup_error = '';
$active_tab = $_GET['mode'] ?? 'login'; // 'login' or 'signup'
$redirect = $_GET['redirect'] ?? '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // --- LOGIN LOGIC ---
    if ($action === 'login') {
        $loginData = trim($_POST['login_data'] ?? '');
        $password = $_POST['password'] ?? '';
        $redirect = $_POST['redirect'] ?? '';

        if (empty($loginData) || empty($password)) {
            $login_error = "Please enter all fields.";
            $active_tab = 'login';
        } else {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? OR phone = ? OR username = ? LIMIT 1");
            $stmt->execute([$loginData, $loginData, $loginData]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['full_name'] = $user['full_name'];

                if ($user['role'] === 'admin') {
                    header("Location: " . SITE_URL . "/admin/dashboard.php");
                } else {
                    header("Location: " . ($redirect ?: SITE_URL . "/customer/shop.php"));
                }
                exit;
            } else {
                $login_error = "Invalid credentials. Please try again.";
                $active_tab = 'login';
            }
        }
    }
    // --- SIGNUP LOGIC ---
    elseif ($action === 'signup') {
        $fullName = trim($_POST['full_name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $captcha = trim($_POST['captcha'] ?? '');
        $botcheck = $_POST['website_url'] ?? '';

        if (!empty($botcheck))
            die("Bot detected.");

        if ($password !== $confirm_password) {
            $signup_error = "Passwords do not match.";
            $active_tab = 'signup';
        } elseif (!preg_match('/^[A-Z ]+$/', $fullName)) {
            $signup_error = "Full Name must be ONLY CAPITAL LETTERS and spaces.";
            $active_tab = 'signup';
        } elseif (!preg_match('/^[a-zA-Z0-9]+$/', $username)) {
            $signup_error = "Username must be alphanumeric.";
            $active_tab = 'signup';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $signup_error = "Invalid email format.";
            $active_tab = 'signup';
        } elseif (!preg_match('/^\+977 (98|97)[0-9]{8}$/', $phone)) {
            $signup_error = "Phone must be in format: +977 98xxxxxxxx";
            $active_tab = 'signup';
        } elseif (strlen($password) < 6) {
            $signup_error = "Password must be at least 6 characters.";
            $active_tab = 'signup';
        } else {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ? OR phone = ?");
            $stmt->execute([$username, $email, $phone]);
            if ($stmt->rowCount() > 0) {
                $signup_error = "Account already exists.";
                $active_tab = 'signup';
            } else {
                $hashed = password_hash($password, PASSWORD_BCRYPT);
                $ins = $pdo->prepare("INSERT INTO users (full_name, username, email, phone, password, role) VALUES (?, ?, ?, ?, ?, 'customer')");
                if ($ins->execute([$fullName, $username, $email, $phone, $hashed])) {
                    $success = "Account created! You can now login.";
                    $active_tab = 'login';
                } else {
                    $signup_error = "Failed to create account.";
                    $active_tab = 'signup';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Authentication - FestiVmart</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body class="auth-page">

    <div class="auth-card">
        <div class="auth-header">
            <a href="../homepage.php" class="back-btn" title="Back to Home">
                <span style="font-size: 1.2rem;">←</span>
            </a>
            <div class="auth-logo-text">FestiVmart</div>
            <div style="width: 40px;"></div> <!-- Spacer -->
        </div>

        <div class="auth-tabs">
            <div class="auth-tab <?php echo $active_tab === 'login' ? 'active' : ''; ?>" data-target="login">Login</div>
            <div class="auth-tab <?php echo $active_tab === 'signup' ? 'active' : ''; ?>" data-target="signup">Sign Up
            </div>
        </div>

        <div class="auth-body">
            <!-- LOGIN FORM -->
            <div id="login-container"
                class="auth-form-container <?php echo $active_tab === 'login' ? 'active' : ''; ?>">
                <h2 class="auth-title">Welcome Back</h2>
                <p class="auth-subtitle">Login to access your festival essentials.</p>

                <?php if ($login_error): ?>
                    <div class="alert alert-error">❌ <?php echo htmlspecialchars($login_error); ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success">✅ <?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>

                <form action="login.php?mode=login" method="POST">
                    <input type="hidden" name="action" value="login">
                    <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($redirect); ?>">

                    <div class="form-group">
                        <label class="form-label">USERNAME / EMAIL / PHONE</label>
                        <input type="text" name="login_data" class="form-control" placeholder="Enter details..."
                            required autofocus>
                    </div>

                    <div class="form-group">
                        <label class="form-label">PASSWORD</label>
                        <div class="input-wrap">
                            <input type="password" name="password" id="loginPass" class="form-control"
                                placeholder="••••••••" required>
                            <span class="input-icon toggle-password" data-target="loginPass">👁️</span>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary w-full mt-2">Log In</button>

                </form>
            </div>

            <!-- SIGNUP FORM -->
            <div id="signup-container"
                class="auth-form-container <?php echo $active_tab === 'signup' ? 'active' : ''; ?>">
                <h2 class="auth-title">Join FestiVmart</h2>
                <p class="auth-subtitle">The premier store for every festival.</p>

                <?php if ($signup_error): ?>
                    <div class="alert alert-error">❌ <?php echo htmlspecialchars($signup_error); ?></div>
                <?php endif; ?>

                <form action="login.php?mode=signup" method="POST" id="signupForm">
                    <input type="hidden" name="action" value="signup">
                    <input type="text" name="website_url" style="display:none" tabindex="-1" autocomplete="off">

                    <div class="form-group">
                        <label class="form-label">FULL NAME (CAPITAL LETTERS)</label>
                        <input type="text" name="full_name" class="form-control" placeholder="RAM BAHADUR" required>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label class="form-label">USERNAME</label>
                            <input type="text" name="username" class="form-control" placeholder="ram123" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">PHONE</label>
                            <div class="input-wrap">
                                <span class="input-prefix" style="color:var(--text-primary)">+977</span>
                                <input type="text" name="phone_raw" class="form-control has-prefix"
                                    placeholder="98XXXXXXXX" required>
                                <input type="hidden" name="phone">
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">EMAIL ADDRESS</label>
                        <input type="email" name="email" class="form-control" placeholder="ram@example.com" required>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label class="form-label">PASSWORD</label>
                            <div class="input-wrap">
                                <input type="password" name="password" id="signupPass" class="form-control"
                                    placeholder="Min 6 chars" required>
                                <span class="input-icon toggle-password" data-target="signupPass">👁️</span>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">CONFIRM</label>
                            <div class="input-wrap">
                                <input type="password" name="confirm_password" id="signupConfirmPass"
                                    class="form-control" placeholder="••••••••" required>
                                <span class="input-icon toggle-password" data-target="signupConfirmPass">👁️</span>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary w-full mt-2">Sign Up</button>


                </form>
            </div>
        </div>
    </div>

    <script>
        document.querySelectorAll('.auth-tab').forEach(tab => {
            tab.addEventListener('click', () => {
                const target = tab.dataset.target;
                switchTab(target);
            });
        });

        document.querySelector('.switch-to-signup').addEventListener('click', () => switchTab('signup'));
        document.querySelector('.switch-to-login').addEventListener('click', () => switchTab('login'));

        function switchTab(target) {
            const card = document.querySelector('.auth-card');
            const oldHeight = card.offsetHeight;
            
            // 1. Switch active states
            document.querySelectorAll('.auth-tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.auth-form-container').forEach(f => f.classList.remove('active'));
            
            document.querySelector(`.auth-tab[data-target="${target}"]`).classList.add('active');
            const targetContainer = document.getElementById(`${target}-container`);
            targetContainer.classList.add('active');
            
            // 2. MEASURE: Temporarily get the natural height of the new content
            card.style.height = 'auto';
            const targetHeight = card.offsetHeight;
            
            // 3. Reset to start height for animation
            card.style.height = oldHeight + 'px';
            void card.offsetHeight; // Force reflow
            
            // 4. Animate to target
            card.style.height = targetHeight + 'px';
            
            // 5. Cleanup
            const onEnd = (e) => {
                if (e.propertyName === 'height') {
                    card.style.height = 'auto';
                    card.removeEventListener('transitionend', onEnd);
                }
            };
            card.addEventListener('transitionend', onEnd);

            // Update URL without reload
            const url = new URL(window.location);
            url.searchParams.set('mode', target);
            window.history.pushState({}, '', url);
        }

        // Phone prefix handler
        document.getElementById('signupForm')?.addEventListener('submit', function (e) {
            const rawPhone = this.querySelector('input[name="phone_raw"]').value.trim();
            this.querySelector('input[name="phone"]').value = '+977 ' + rawPhone;
        });

        // Toggle Password
        document.querySelectorAll('.toggle-password').forEach(btn => {
            btn.addEventListener('click', () => {
                const input = document.getElementById(btn.dataset.target);
                if (input.type === 'password') {
                    input.type = 'text';
                    btn.textContent = '🙈';
                } else {
                    input.type = 'password';
                    btn.textContent = '👁️';
                }
            });
        });
    </script>
</body>

</html>