<?php
require_once 'config.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: index.html");
    exit();
}

$full_name = $_SESSION['full_name'];
$success = '';
$error = '';

// Handle enrollment
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_enrollment'])) {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verifyCSRFToken($csrf_token)) {
        $error = 'Security validation failed.';
    } else {
        $student_id = (int)$_POST['student_id'];
        $course_id = (int)$_POST['course_id'];

        // Check if already enrolled
        $stmt = $pdo->prepare("SELECT id FROM enrollments WHERE student_id = ? AND course_id = ?");
        $stmt->execute([$student_id, $course_id]);
        if ($stmt->fetch()) {
            $error = 'Student is already enrolled in this course.';
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO enrollments (student_id, course_id) VALUES (?, ?)");
                $stmt->execute([$student_id, $course_id]);
                $success = 'Student enrolled successfully!';
            } catch (PDOException $e) {
                $error = 'Failed to enroll student.';
            }
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['remove_enrollment'])) {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verifyCSRFToken($csrf_token)) {
        $error = 'Security validation failed.';
    } else {
        $enrollment_id = (int)$_POST['enrollment_id'];
        try {
            $stmt = $pdo->prepare("DELETE FROM enrollments WHERE id = ?");
            $stmt->execute([$enrollment_id]);
            $success = 'Enrollment removed!';
        } catch (PDOException $e) {
            $error = 'Failed to remove enrollment.';
        }
    }
}

// Get students and courses
$students = $pdo->query("SELECT * FROM users WHERE role = 'student' ORDER BY full_name")->fetchAll();
$courses = $pdo->query("SELECT * FROM courses ORDER BY course_name")->fetchAll();

// Get enrollments
$stmt = $pdo->query("
    SELECT e.*, u.full_name, u.username, c.course_name, c.course_code
    FROM enrollments e
    JOIN users u ON e.student_id = u.id
    JOIN courses c ON e.course_id = c.id
    ORDER BY u.full_name, c.course_name
");
$enrollments = $stmt->fetchAll();

// Stats
$stmt = $pdo->query("SELECT COUNT(*) as total FROM enrollments");
$total_enrollments = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'student'");
$total_students_count = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM courses");
$total_courses_count = $stmt->fetch()['total'];

$csrf_token = generateCSRFToken();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enrollment Management - School LMS</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <div class="logo">📚 Enrollment Management</div>
            <div class="nav-links">
                <a href="admin.php">Admin</a>
                <a href="dashboard.php">Dashboard</a>
                <a href="user-management.php">Users</a>
                <a href="enrollment.php">Enrollment</a>
                <a href="attendance.php">Attendance</a>
                <a href="logout.php">Logout</a>
            </div>
            <div class="nav-actions">
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
        <h1>📚 Enrollment Management</h1>
        <p class="lead">Manage student course enrollments and view enrollment status.</p>

        <?php if ($success): ?>
        <div class="alert success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="alert danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">👨‍🎓</div>
                <div class="stat-value"><?php echo $total_students_count; ?></div>
                <div class="stat-label">Students</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">📚</div>
                <div class="stat-value"><?php echo $total_courses_count; ?></div>
                <div class="stat-label">Courses</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">✓</div>
                <div class="stat-value"><?php echo $total_enrollments; ?></div>
                <div class="stat-label">Enrollments</div>
            </div>
        </div>

        <!-- Add Enrollment Form -->
        <div class="card" style="margin-bottom: 30px;">
            <h2>➕ Add New Enrollment</h2>
            <form method="POST">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Student *</label>
                        <select name="student_id" required>
                            <option value="">Select Student</option>
                            <?php foreach ($students as $student): ?>
                            <option value="<?php echo $student['id']; ?>"><?php echo htmlspecialchars($student['full_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Course *</label>
                        <select name="course_id" required>
                            <option value="">Select Course</option>
                            <?php foreach ($courses as $course): ?>
                            <option value="<?php echo $course['id']; ?>"><?php echo htmlspecialchars($course['course_name']); ?> (<?php echo htmlspecialchars($course['course_code']); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    <input type="hidden" name="add_enrollment" value="1">
                    <button type="submit" class="btn-primary" style="grid-column: auto / span 2;">Enroll Student</button>
                </div>
            </form>
        </div>

        <!-- Enrollments Table -->
        <div class="card">
            <h2>📋 Student Enrollments</h2>
            <div class="table-container">
                <table class="enrollments-table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Username</th>
                            <th>Course</th>
                            <th>Course Code</th>
                            <th>Enrolled On</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($enrollments as $enroll): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($enroll['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($enroll['username']); ?></td>
                            <td><?php echo htmlspecialchars($enroll['course_name']); ?></td>
                            <td><?php echo htmlspecialchars($enroll['course_code']); ?></td>
                            <td><?php echo date('F j, Y', strtotime($enroll['enrolled_at'])); ?></td>
                            <td>
                                <form method="POST" style="display: inline;">
                                    <button type="submit" onclick="return confirm('Remove this enrollment?');" class="btn-delete">Remove</button>
                                    <input type="hidden" name="enrollment_id" value="<?php echo $enroll['id']; ?>">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                    <input type="hidden" name="remove_enrollment" value="1">
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <style>
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--dark);
        }

        .form-group select {
            padding: 12px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
        }

        .form-group select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .table-container {
            overflow-x: auto;
            margin-top: 15px;
        }

        .enrollments-table {
            width: 100%;
            border-collapse: collapse;
        }

        .enrollments-table th {
            background: #f7fafc;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            border-bottom: 2px solid #e2e8f0;
        }

        .enrollments-table td {
            padding: 12px;
            border-bottom: 1px solid #e2e8f0;
        }

        .enrollments-table tr:hover {
            background: #f7fafc;
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
    </style>

    <script src="script.js"></script>
</body>
</html>
