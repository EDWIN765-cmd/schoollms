<?php
require_once 'config.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: index.html");
    exit();
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$full_name = $_SESSION['full_name'];
$success = '';
$error = '';

// Get current user info
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verifyCSRFToken($csrf_token)) {
        $error = 'Security validation failed.';
    } else {
        $new_full_name = sanitizeInput($_POST['full_name'] ?? '');
        $new_email = sanitizeInput($_POST['email'] ?? '');

        if (empty($new_full_name)) {
            $error = 'Full name is required.';
        } elseif (!validateEmail($new_email)) {
            $error = 'Please enter a valid email.';
        } else {
            // Check if email already exists (excluding current user)
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$new_email, $user_id]);
            if ($stmt->fetch()) {
                $error = 'This email is already registered.';
            } else {
                try {
                    $stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ? WHERE id = ?");
                    $stmt->execute([$new_full_name, $new_email, $user_id]);
                    $_SESSION['full_name'] = $new_full_name;
                    $success = 'Profile updated successfully!';
                    $user = $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                    $stmt->execute([$user_id]);
                    $user = $stmt->fetch();
                } catch (PDOException $e) {
                    $error = 'Failed to update profile.';
                }
            }
        }
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verifyCSRFToken($csrf_token)) {
        $error = 'Security validation failed.';
    } else {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        // Verify current password
        if (!verifyPassword($current_password, $user['password']) && md5($current_password) !== $user['password']) {
            $error = 'Current password is incorrect.';
        } elseif (empty($new_password)) {
            $error = 'New password is required.';
        } elseif (!validatePassword($new_password)) {
            $error = 'Password must be at least 8 characters with uppercase, lowercase, and numbers.';
        } elseif ($new_password !== $confirm_password) {
            $error = 'New passwords do not match.';
        } else {
            try {
                $hashed_password = hashPassword($new_password);
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$hashed_password, $user_id]);
                $success = 'Password changed successfully!';
            } catch (PDOException $e) {
                $error = 'Failed to change password.';
            }
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
    <title>My Profile - School LMS</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <div class="logo">👤 My Profile</div>
            <div class="nav-links">
                <a href="dashboard.php">Dashboard</a>
                <a href="course-management.php">Courses</a>
                <a href="communication.php">Communication</a>
                <a href="analytics.php">Analytics</a>
                <a href="exams.php">Exams</a>
                <a href="assignments.php">Assignments</a>
                <a href="announcements.php">Announcements</a>
                <?php if ($role == 'admin'): ?>
                <a href="admin.php">Admin</a>
                <a href="user-management.php">Users</a>
                <?php endif; ?>
                <a href="logout.php">Logout</a>
            </div>
            <div class="nav-actions">
                <button class="nav-action" type="button" onclick="toggleDropdown('notifDropdown')">
                    🔔 <span class="badge"><?php echo htmlspecialchars($notificationCount); ?></span>
                </button>
                <div class="dropdown notifications-dropdown" id="notifDropdown">
                    <div class="dropdown-title">Latest announcements</div>
                    <?php
                    $notifStmt = $pdo->query("SELECT title, created_at FROM announcements ORDER BY created_at DESC LIMIT 3");
                    while ($notif = $notifStmt->fetch()): ?>
                    <a href="announcements.php" class="dropdown-item">
                        <?php echo htmlspecialchars($notif['title']); ?>
                        <span><?php echo date('M j', strtotime($notif['created_at'])); ?></span>
                    </a>
                    <?php endwhile; ?>
                    <a href="announcements.php" class="dropdown-action">View all announcements</a>
                </div>
                <button class="nav-action" type="button" onclick="toggleDropdown('profileDropdown')">
                    <?php echo htmlspecialchars($full_name); ?> ▼
                </button>
                <div class="dropdown profile-dropdown" id="profileDropdown">
                    <a href="profile.php">Profile</a>
                    <a href="announcements.php">Announcements</a>
                    <a href="logout.php">Logout</a>
                </div>
            </div>
            <div class="mobile-menu-btn" onclick="toggleMobileMenu()">☰</div>
        </div>
    </nav>

    <div class="container">
        <h1>👤 My Profile</h1>
        <p class="lead">Manage your account information and security settings.</p>

        <?php if (!empty($success)): ?>
        <div class="alert success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
        <div class="alert danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="dashboard-grid">
            <!-- Profile Information -->
            <div class="card">
                <h2>📋 Profile Information</h2>
                <form method="POST">
                    <div class="form-group">
                        <label>Username</label>
                        <input type="text" disabled value="<?php echo htmlspecialchars($user['username']); ?>" class="input-disabled">
                        <small>Username cannot be changed</small>
                    </div>

                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Role</label>
                        <input type="text" disabled value="<?php echo ucfirst($user['role']); ?>" class="input-disabled">
                    </div>

                    <div class="form-group">
                        <label>Member Since</label>
                        <input type="text" disabled value="<?php echo date('F j, Y', strtotime($user['created_at'])); ?>" class="input-disabled">
                    </div>

                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    <input type="hidden" name="update_profile" value="1">
                    <button type="submit" class="btn-primary">Save Changes</button>
                </form>
            </div>

            <!-- Change Password -->
            <div class="card">
                <h2>🔐 Change Password</h2>
                <form method="POST">
                    <div class="form-group">
                        <label>Current Password *</label>
                        <input type="password" name="current_password" required placeholder="Enter your current password">
                    </div>

                    <div class="form-group">
                        <label>New Password *</label>
                        <input type="password" name="new_password" required placeholder="Min 8 chars, uppercase, lowercase, number">
                        <small>Must contain: uppercase, lowercase, and numbers</small>
                    </div>

                    <div class="form-group">
                        <label>Confirm New Password *</label>
                        <input type="password" name="confirm_password" required placeholder="Re-enter new password">
                    </div>

                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    <input type="hidden" name="change_password" value="1">
                    <button type="submit" class="btn-primary">Change Password</button>
                </form>
            </div>
        </div>
    </div>

    <style>
        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--dark);
        }

        .form-group input {
            width: 100%;
            padding: 12px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .input-disabled {
            background-color: #f7fafc;
            color: #a0aec0;
            cursor: not-allowed;
        }

        .form-group small {
            display: block;
            margin-top: 5px;
            color: var(--gray);
            font-size: 12px;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: background 0.3s;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
        }
    </style>

    <script src="script.js"></script>
</body>
</html>
