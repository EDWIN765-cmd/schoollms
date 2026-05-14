<?php
require_once 'config.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] == 'admin') {
        header("Location: admin.php");
    } else {
        header("Location: dashboard.php");
    }
    exit();
}

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = sanitizeInput($_POST['username'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $full_name = sanitizeInput($_POST['full_name'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';

    // Validate CSRF token
    if (!verifyCSRFToken($csrf_token)) {
        $errors[] = 'Security validation failed. Please try again.';
    }

    // Validate inputs
    if (empty($username)) {
        $errors[] = 'Username is required.';
    } elseif (strlen($username) < 3 || strlen($username) > 50) {
        $errors[] = 'Username must be between 3 and 50 characters.';
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $errors[] = 'Username can only contain letters, numbers, and underscores.';
    }

    if (empty($email)) {
        $errors[] = 'Email is required.';
    } elseif (!validateEmail($email)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if (empty($full_name)) {
        $errors[] = 'Full name is required.';
    } elseif (strlen($full_name) > 100) {
        $errors[] = 'Full name must be 100 characters or less.';
    }

    if (empty($password)) {
        $errors[] = 'Password is required.';
    } elseif (!validatePassword($password)) {
        $errors[] = 'Password must be at least 8 characters with uppercase, lowercase, and numbers.';
    }

    if ($password !== $confirm_password) {
        $errors[] = 'Passwords do not match.';
    }

    // Check if username already exists
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            $errors[] = 'Username already exists. Please choose another.';
        }

        // Check if email already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = 'Email already registered. Please use another or login.';
        }
    }

    // If no errors, create account
    if (empty($errors)) {
        try {
            $hashed_password = hashPassword($password);
            $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, email, role) VALUES (?, ?, ?, ?, 'student')");
            $stmt->execute([$username, $hashed_password, $full_name, $email]);
            $success = true;
        } catch (PDOException $e) {
            $errors[] = 'Registration failed. Please try again later.';
        }
    }
}

$csrf_token = generateCSRFToken();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - School LMS</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body class="login-body">
    <div class="login-container">
        <div class="login-card">
            <div class="logo">
                <h1>📚 School LMS</h1>
                <p>Create Your Account</p>
            </div>

            <?php if ($success): ?>
            <div class="alert success">
                ✓ Account created successfully! <a href="index.html">Login here</a>
            </div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
            <div class="alert danger">
                <strong>Registration Error:</strong>
                <ul style="margin-top: 10px; margin-left: 20px;">
                    <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <?php if (!$success): ?>
            <form method="POST" action="">
                <div class="input-group">
                    <label>Full Name *</label>
                    <input type="text" name="full_name" required placeholder="Enter your full name" value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>">
                </div>
                
                <div class="input-group">
                    <label>Username *</label>
                    <input type="text" name="username" required placeholder="Choose a username (3-50 chars, alphanumeric_)" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                </div>
                
                <div class="input-group">
                    <label>Email *</label>
                    <input type="email" name="email" required placeholder="Enter your email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                </div>
                
                <div class="input-group">
                    <label>Password *</label>
                    <input type="password" name="password" required placeholder="Min 8 chars, uppercase, lowercase, number">
                    <small style="color: var(--gray); margin-top: 5px; display: block;">
                        Password must contain: uppercase, lowercase, and numbers
                    </small>
                </div>
                
                <div class="input-group">
                    <label>Confirm Password *</label>
                    <input type="password" name="confirm_password" required placeholder="Re-enter your password">
                </div>

                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                
                <button type="submit" class="btn-login">Create Account →</button>
                
                <p style="text-align: center; margin-top: 20px; color: var(--gray);">
                    Already have an account? <a href="index.html" style="color: var(--primary); text-decoration: none; font-weight: 600;">Login here</a>
                </p>
            </form>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
