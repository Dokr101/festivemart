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
    // --- SIGNUP LOGIC (UPDATED with phone & username rules) ---
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
        } elseif (!preg_match('/^[A-Z ]+$/', $fullName) || strlen($fullName) < 3) {
            $signup_error = "Full Name must be ONLY CAPITAL LETTERS and spaces (min 3 chars).";
            $active_tab = 'signup';
        } elseif (!preg_match('/^[a-zA-Z0-9]+$/', $username) || strlen($username) < 4 || !preg_match('/[0-9]/', $username)) {
            $signup_error = "Username must be alphanumeric, at least 4 characters, and contain at least one number.";
            $active_tab = 'signup';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $signup_error = "Invalid email format.";
            $active_tab = 'signup';
        } elseif (!preg_match('/^(98|97)[0-9]{8}$/', $phone)) {
            $signup_error = "Phone must be 10 digits starting with 98 or 97.";
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
    <style>
        .validation-message {
            font-size: 0.8rem;
            margin-top: 0.25rem;
            min-height: 1.2rem;
        }
        .validation-message.error {
            color: #dc3545;
        }
        .validation-message.success {
            color: #28a745;
        }
        .input-wrap {
            position: relative;
        }
    </style>
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

                    <!-- Full Name -->
                    <div class="form-group">
                        <label class="form-label">FULL NAME (CAPITAL LETTERS, min 3 chars)</label>
                        <input type="text" name="full_name" id="full_name" class="form-control" placeholder="RAM BAHADUR" required>
                        <div class="validation-message" id="fullname-message"></div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <!-- Username (updated: must contain a number) -->
                        <div class="form-group">
                            <label class="form-label">USERNAME(min 4 chars)</label>
                            <input type="text" name="username" id="username" class="form-control" placeholder="ram123" required>
                            <div class="validation-message" id="username-message"></div>
                        </div>
                        <!-- Phone (no +977) -->
                        <div class="form-group">
                            <label class="form-label">PHONE NUMBER</label>
                            <div class="input-wrap">
                                <input type="text" name="phone_raw" id="phone_raw" class="form-control"
                                    placeholder="98XXXXXXXX" required inputmode="numeric" pattern="\d*">
                                <input type="hidden" name="phone" id="phone_hidden">
                            </div>
                            <div class="validation-message" id="phone-message"></div>
                        </div>
                    </div>

                    <!-- Email -->
                    <div class="form-group">
                        <label class="form-label">EMAIL ADDRESS</label>
                        <input type="email" name="email" id="email" class="form-control" placeholder="ram@example.com" required>
                        <div class="validation-message" id="email-message"></div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <!-- Password -->
                        <div class="form-group">
                            <label class="form-label">PASSWORD</label>
                            <div class="input-wrap">
                                <input type="password" name="password" id="signupPass" class="form-control"
                                    placeholder="Min 6 chars, 1 uppercase, 1 number, 1 symbol" required>
                                <span class="input-icon toggle-password" data-target="signupPass">👁️</span>
                            </div>
                            <div class="validation-message" id="password-message"></div>
                        </div>
                        <!-- Confirm Password -->
                        <div class="form-group">
                            <label class="form-label">CONFIRM</label>
                            <div class="input-wrap">
                                <input type="password" name="confirm_password" id="signupConfirmPass"
                                    class="form-control" placeholder="••••••••" required>
                                <span class="input-icon toggle-password" data-target="signupConfirmPass">👁️</span>
                            </div>
                            <div class="validation-message" id="confirm-password-message"></div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary w-full mt-2">Sign Up</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Original tab switching and toggle password code (unchanged)
        document.querySelectorAll('.auth-tab').forEach(tab => {
            tab.addEventListener('click', () => {
                const target = tab.dataset.target;
                switchTab(target);
            });
        });

        document.querySelector('.switch-to-signup')?.addEventListener('click', () => switchTab('signup'));
        document.querySelector('.switch-to-login')?.addEventListener('click', () => switchTab('login'));

        function switchTab(target) {
            const card = document.querySelector('.auth-card');
            const oldHeight = card.offsetHeight;
            
            document.querySelectorAll('.auth-tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.auth-form-container').forEach(f => f.classList.remove('active'));
            
            document.querySelector(`.auth-tab[data-target="${target}"]`).classList.add('active');
            const targetContainer = document.getElementById(`${target}-container`);
            targetContainer.classList.add('active');
            
            card.style.height = 'auto';
            const targetHeight = card.offsetHeight;
            
            card.style.height = oldHeight + 'px';
            void card.offsetHeight;
            
            card.style.height = targetHeight + 'px';
            
            const onEnd = (e) => {
                if (e.propertyName === 'height') {
                    card.style.height = 'auto';
                    card.removeEventListener('transitionend', onEnd);
                }
            };
            card.addEventListener('transitionend', onEnd);

            const url = new URL(window.location);
            url.searchParams.set('mode', target);
            window.history.pushState({}, '', url);
        }

        // Toggle Password (unchanged)
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

        // ========== UPDATED: Real-time validation for signup form ==========
        (function() {
            const form = document.getElementById('signupForm');
            if (!form) return;

            function setMessage(elementId, message, isError = true) {
                const msgDiv = document.getElementById(elementId);
                if (msgDiv) {
                    msgDiv.textContent = message;
                    msgDiv.className = 'validation-message ' + (isError ? 'error' : 'success');
                }
            }

            // 1. Full name: uppercase + only letters/spaces, min length 3
            const fullNameInput = document.getElementById('full_name');
            fullNameInput.addEventListener('input', function(e) {
                let val = this.value;
                this.value = val.toUpperCase();
                val = this.value;
                const regex = /^[A-Z ]*$/;
                if (!regex.test(val)) {
                    setMessage('fullname-message', 'Only capital letters and spaces allowed', true);
                } else if (val.length > 0 && val.length < 3) {
                    setMessage('fullname-message', 'Must be at least 3 characters', true);
                } else {
                    setMessage('fullname-message', val ? '✓ Valid' : '', false);
                }
            });

            // 2. Username: lowercase + alphanumeric, min length 4, must contain a number
            const usernameInput = document.getElementById('username');
            usernameInput.addEventListener('input', function(e) {
                let val = this.value;
                this.value = val.toLowerCase();
                val = this.value;
                const regexAllowed = /^[a-z0-9]*$/;
                if (!regexAllowed.test(val)) {
                    setMessage('username-message', 'Only lowercase letters and numbers allowed', true);
                } else if (val.length > 0 && val.length < 4) {
                    setMessage('username-message', 'Must be at least 4 characters', true);
                } else if (val.length >= 4 && !/[0-9]/.test(val)) {
                    setMessage('username-message', 'Must contain at least one number', true);
                } else {
                    setMessage('username-message', val ? '✓ Valid' : '', false);
                }
            });

            // 3. Phone: only digits, exactly 10, first two = 98 or 97 (no +977)
            const phoneRaw = document.getElementById('phone_raw');
            const phoneHidden = document.getElementById('phone_hidden');

            phoneRaw.addEventListener('input', function(e) {
                this.value = this.value.replace(/\D/g, '');
            });

            function validatePhone() {
                let raw = phoneRaw.value.trim();
                if (raw.length > 10) {
                    raw = raw.slice(0, 10);
                    phoneRaw.value = raw;
                }
                phoneHidden.value = raw; // store raw digits

                if (raw.length === 0) {
                    setMessage('phone-message', '', false);
                } else if (raw.length < 2) {
                    setMessage('phone-message', 'Waiting for first two digits...', true);
                } else {
                    const firstTwo = raw.slice(0, 2);
                    if (firstTwo !== '98' && firstTwo !== '97') {
                        setMessage('phone-message', 'Must start with 98 or 97', true);
                    } else if (raw.length < 10) {
                        setMessage('phone-message', 'Must be exactly 10 digits', true);
                    } else {
                        setMessage('phone-message', '✓ Valid', false);
                    }
                }
            }
            phoneRaw.addEventListener('input', validatePhone);
            validatePhone();

            // 4. Email: local part must contain letters and numbers, valid domain with TLD
            const emailInput = document.getElementById('email');
            emailInput.addEventListener('input', function(e) {
                const val = this.value;
                const atIndex = val.indexOf('@');
                if (val.length === 0) {
                    setMessage('email-message', '', false);
                } else if (atIndex === -1) {
                    setMessage('email-message', 'Missing @ symbol', true);
                } else {
                    const local = val.slice(0, atIndex);
                    const domain = val.slice(atIndex + 1);
                    if (local.length === 0) {
                        setMessage('email-message', 'Local part cannot be empty', true);
                    } else if (!/[a-zA-Z]/.test(local) || !/[0-9]/.test(local)) {
                        setMessage('email-message', 'Local part must contain both letters and numbers', true);
                    } else if (domain.length === 0) {
                        setMessage('email-message', 'Domain cannot be empty', true);
                    } else if (!domain.includes('.')) {
                        setMessage('email-message', 'Domain must contain a dot', true);
                    } else {
                        const tld = domain.split('.').pop();
                        const allowedTLDs = ['com', 'org', 'edu', 'net', 'gov', 'io', 'co'];
                        if (!allowedTLDs.includes(tld.toLowerCase())) {
                            setMessage('email-message', 'TLD must be .com, .org, .edu, etc.', true);
                        } else {
                            setMessage('email-message', '✓ Valid', false);
                        }
                    }
                }
            });

            // 5. Password: min 6 chars, at least one uppercase, one number, one symbol
            const passwordInput = document.getElementById('signupPass');
            passwordInput.addEventListener('input', function(e) {
                const val = this.value;
                const errors = [];
                if (val.length < 6) errors.push('at least 6 characters');
                if (!/[A-Z]/.test(val)) errors.push('one uppercase letter');
                if (!/[0-9]/.test(val)) errors.push('one number');
                if (!/[^A-Za-z0-9]/.test(val)) errors.push('one symbol');
                
                if (val.length === 0) {
                    setMessage('password-message', '', false);
                } else if (errors.length > 0) {
                    setMessage('password-message', 'Missing: ' + errors.join(', '), true);
                } else {
                    setMessage('password-message', '✓ Strong password', false);
                }
                validateConfirm();
            });

            // 6. Confirm password: match
            const confirmInput = document.getElementById('signupConfirmPass');
            function validateConfirm() {
                const pass = passwordInput.value;
                const confirm = confirmInput.value;
                if (!confirm) {
                    setMessage('confirm-password-message', '', false);
                } else if (pass !== confirm) {
                    setMessage('confirm-password-message', 'Passwords do not match', true);
                } else {
                    setMessage('confirm-password-message', '✓ Passwords match', false);
                }
            }
            confirmInput.addEventListener('input', validateConfirm);
            passwordInput.addEventListener('input', validateConfirm);
        })();
    </script>
</body>

</html>