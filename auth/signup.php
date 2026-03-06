<?php
require_once __DIR__ . '/../includes/functions.php';

if (isLoggedIn()) {
    header("Location: " . SITE_URL . "/homepage.php");
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $captcha = trim($_POST['captcha'] ?? '');
    $botcheck = $_POST['website_url'] ?? ''; // Honeypot

    // 1. Honeypot check
    if (!empty($botcheck)) {
        die("Bot detected.");
    }

    // 2. CAPTCHA
    if ($captcha !== '7') {
        $error = "Incorrect CAPTCHA answer. Please try again.";
    }
    // 3. Validations as per user request
    // Full Name: only capital letters and spaces
    elseif (!preg_match('/^[A-Z ]+$/', $fullName)) {
        $error = "Full Name must contain ONLY capital letters and spaces (No symbols, no numbers, no lowercase).";
    }
    // Username: name + numbers only (alphanumeric)
    elseif (!preg_match('/^[a-zA-Z0-9]+$/', $username)) {
        $error = "Username must contain only letters and numbers.";
    }
    // Email: basic format check
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    }
    // Phone: Exactly 10 digits starting with +977 98 or +977 97
    elseif (!preg_match('/^\+977 (98|97)[0-9]{8}$/', $phone)) {
        $error = "Phone must be exactly 10 digits starting with '+977 98' or '+977 97'. Example: +977 9812345678";
    }
    // Password: min 6 chars (digits or letters ok, request says "6 char or digits")
    elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long.";
    } else {
        // Check duplicates
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ? OR phone = ?");
        $stmt->execute([$username, $email, $phone]);
        if ($stmt->rowCount() > 0) {
            $error = "Username, Email, or Phone already exists in our system.";
        } else {
            // Hash password + Insert
            $hashed = password_hash($password, PASSWORD_BCRYPT);
            $ins = $pdo->prepare("INSERT INTO users (full_name, username, email, phone, password, role) VALUES (?, ?, ?, ?, ?, 'customer')");
            if ($ins->execute([$fullName, $username, $email, $phone, $hashed])) {
                $success = "Account created successfully! You can now login.";
                $fullName = $username = $email = $phone = ''; // clear form
            } else {
                $error = "Something went wrong. Please try again.";
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
    <title>Signup - FestiVmart</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body class="auth-page">

    <div class="auth-card">
        <div class="auth-logo">
            <a href="../homepage.php" class="auth-logo-text">FestiVmart</a>
        </div>

        <h1 class="auth-title">Create Account</h1>
        <p class="auth-subtitle">Join Nepal's premier festival store.</p>

        <?php if ($error): ?>
            <div class="alert alert-error">❌
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">✅
                <?php echo htmlspecialchars($success); ?>
            </div>
            <a href="login.php" class="btn btn-primary w-full mt-2">Go to Login</a>
        <?php else: ?>
            <form action="signup.php" method="POST">
                <!-- Honeypot -->
                <input type="text" name="website_url" style="display:none" tabindex="-1" autocomplete="off">

                <div class="form-group">
                    <label class="form-label">FULL NAME</label>
                    <input type="text" name="full_name" class="form-control" placeholder="E.g. RAM BAHADUR"
                        value="<?php echo htmlspecialchars($fullName ?? ''); ?>" required>
                    <small class="text-faint" style="font-size:0.75rem">Capital letters and spaces only.</small>
                </div>

                <div class="form-group">
                    <label class="form-label">USERNAME</label>
                    <input type="text" name="username" class="form-control" placeholder="ram123"
                        value="<?php echo htmlspecialchars($username ?? ''); ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label">EMAIL</label>
                    <input type="email" name="email" class="form-control" placeholder="ram@example.com"
                        value="<?php echo htmlspecialchars($email ?? ''); ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label">PHONE NUMBER</label>
                    <div class="input-wrap">
                        <span class="input-prefix" style="color:var(--text-primary)">+977</span>
                        <input type="text" name="phone" class="form-control has-prefix" placeholder=" 9800000000"
                            value="<?php echo htmlspecialchars(str_replace('+977 ', '', $phone ?? '')); ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">PASSWORD</label>
                    <div class="input-wrap">
                        <input type="password" name="password" id="signupPass" class="form-control"
                            placeholder="Min 6 chars" required>
                        <span class="input-icon toggle-password" data-target="signupPass">👁️</span>
                    </div>
                </div>

                <div class="captcha-box">
                    <div class="captcha-question">What is 3 + 4 ?</div>
                    <input type="text" name="captcha" class="form-control captcha-input" placeholder="Answer" required>
                </div>

                <button type="submit" class="btn btn-primary w-full">Sign Up</button>
            </form>

            <div class="auth-switch">
                Already have an account? <a href="login.php">Log In</a>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Prefix handler for phone input
        document.querySelector('input[name="phone"]').addEventListener('change', function (e) {
            let val = this.value.trim();
            if (val && !val.startsWith('+977 ')) {
                // If user didn't type it, the hidden field processes it before submit
            }
        });
        document.querySelector('form').addEventListener('submit', function (e) {
            let phoneInput = document.querySelector('input[name="phone"]');
            let val = phoneInput.value.trim();
            if (!val.startsWith('+977 ')) {
                phoneInput.value = '+977 ' + val.replace(/^\+977\s?/, '');
            }
        });
    </script>
    <script src="../assets/js/main.js"></script>
</body>

</html>