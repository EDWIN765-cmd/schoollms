<?php
require_once 'config.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: index.html");
    exit();
}

$full_name = $_SESSION['full_name'];
$success = '';
$error = '';

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verifyCSRFToken($csrf_token)) {
        $error = 'Security validation failed.';
    } elseif (isset($_POST['add_user'])) {
        $username = sanitizeInput($_POST['username'] ?? '');
        $email = sanitizeInput($_POST['email'] ?? '');
        $full_name_input = sanitizeInput($_POST['full_name'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? 'student';

        if (empty($username) || empty($email) || empty($full_name_input) || empty($password)) {
            $error = 'All fields are required.';
        } elseif (!validateEmail($email)) {
            $error = 'Invalid email address.';
        } else {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                $error = 'Username already exists.';
            } else {
                try {
                    $hashed_password = hashPassword($password);
                    $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, email, role) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$username, $hashed_password, $full_name_input, $email, $role]);
                    $success = 'User added successfully!';
                } catch (PDOException $e) {
                    $error = 'Failed to add user.';
                }
            }
        }
    } elseif (isset($_POST['delete_user'])) {
        $user_id_to_delete = (int)$_POST['user_id'];
        if ($user_id_to_delete == $_SESSION['user_id']) {
            $error = 'You cannot delete your own account.';
        } else {
            try {
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$user_id_to_delete]);
                $success = 'User deleted successfully!';
            } catch (PDOException $e) {
                $error = 'Failed to delete user.';
            }
        }
    } elseif (isset($_POST['update_user_role'])) {
        $user_id_to_update = (int)$_POST['user_id'];
        $new_role = $_POST['role'];
        if ($user_id_to_update == $_SESSION['user_id']) {
            $error = 'You cannot change your own role.';
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
                $stmt->execute([$new_role, $user_id_to_update]);
                $success = 'User role updated!';
            } catch (PDOException $e) {
                $error = 'Failed to update user.';
            }
        }
    }
}

// Get all users
$stmt = $pdo->query("SELECT * FROM users ORDER BY created_at DESC");
$users = $stmt->fetchAll();

// Get statistics
$stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'student'");
$total_students = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'admin'");
$total_admins = $stmt->fetch()['total'];

$csrf_token = generateCSRFToken();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - School LMS</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <div class="logo">👥 User Management</div>
            <div class="nav-links">
                <a href="admin.php">Admin</a>
                <a href="dashboard.php">Dashboard</a>
                <a href="course-management.php">Courses</a>
                <a href="user-management.php">Users</a>
                <a href="analytics.php">Analytics</a>
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
                    <a href="logout.php">Logout</a>
                </div>
            </div>
            <div class="mobile-menu-btn" onclick="toggleMobileMenu()">☰</div>
        </div>
    </nav>

    <div class="container">
        <h1>👥 User Management</h1>
        <p class="lead">Manage student and admin accounts in the system.</p>

        <?php if ($success): ?>
        <div class="alert success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="alert danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">👨‍🎓</div>
                <div class="stat-value"><?php echo $total_students; ?></div>
                <div class="stat-label">Students</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">👨‍💼</div>
                <div class="stat-value"><?php echo $total_admins; ?></div>
                <div class="stat-label">Admins</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">👥</div>
                <div class="stat-value"><?php echo count($users); ?></div>
                <div class="stat-label">Total Users</div>
            </div>
        </div>

        <!-- Add User Form -->
        <div class="card" style="margin-bottom: 30px;">
            <h2>➕ Add New User</h2>
            <form method="POST" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                <input type="text" name="username" placeholder="Username" required>
                <input type="email" name="email" placeholder="Email" required>
                <input type="text" name="full_name" placeholder="Full Name" required>
                <input type="password" name="password" placeholder="Password" required>
                <select name="role" required>
                    <option value="student">Student</option>
                    <option value="admin">Admin</option>
                </select>
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="add_user" value="1">
                <button type="submit" class="btn-primary">Add User</button>
            </form>
        </div>

        <!-- Users Table -->
        <div class="card">
            <h2>📋 All Users</h2>
            <div class="table-container">
                <table class="users-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Full Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Joined</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                        <tr>
                            <td><?php echo $u['id']; ?></td>
                            <td><strong><?php echo htmlspecialchars($u['username']); ?></strong></td>
                            <td><?php echo htmlspecialchars($u['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($u['email']); ?></td>
                            <td>
                                <span class="badge <?php echo $u['role'] == 'admin' ? 'admin' : 'student'; ?>">
                                    <?php echo ucfirst($u['role']); ?>
                                </span>
                            </td>
                            <td><?php echo date('M j, Y', strtotime($u['created_at'])); ?></td>
                            <td>
                                <?php if ($u['id'] != $_SESSION['user_id']): ?>
                                <form method="POST" style="display: inline;">
                                    <select name="role" onchange="if(confirm('Change role?')) this.form.submit();">
                                        <option value="<?php echo $u['role']; ?>" selected>Change Role</option>
                                        <option value="<?php echo $u['role'] == 'admin' ? 'student' : 'admin'; ?>">
                                            Switch to <?php echo $u['role'] == 'admin' ? 'Student' : 'Admin'; ?>
                                        </option>
                                    </select>
                                    <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                    <input type="hidden" name="update_user_role" value="1">
                                </form>
                                <form method="POST" style="display: inline;">
                                    <button type="submit" onclick="return confirm('Delete this user?');" class="btn-delete">Delete</button>
                                    <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                    <input type="hidden" name="delete_user" value="1">
                                </form>
                                <?php else: ?>
                                <span class="text-muted">(Your account)</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <style>
        .table-container {
            overflow-x: auto;
        }

        .users-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        .users-table th {
            background: #f7fafc;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            border-bottom: 2px solid #e2e8f0;
        }

        .users-table td {
            padding: 12px;
            border-bottom: 1px solid #e2e8f0;
        }

        .users-table tr:hover {
            background: #f7fafc;
        }

        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge.admin {
            background: #fed7d7;
            color: #c53030;
        }

        .badge.student {
            background: #c6f6d5;
            color: #22543d;
        }

        .btn-delete {
            background: #f56565;
            color: white;
            padding: 8px 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            transition: background 0.3s;
        }

        .btn-delete:hover {
            background: #e53e3e;
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

        .text-muted {
            color: var(--gray);
            font-size: 12px;
        }
    </style>

    <script src="script.js"></script>
</body>
</html>
