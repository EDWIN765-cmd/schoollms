<?php
require_once 'config.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: index.html");
    exit();
}

$full_name = $_SESSION['full_name'];
$success = '';
$error = '';

// Handle attendance marking
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['mark_attendance'])) {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verifyCSRFToken($csrf_token)) {
        $error = 'Security validation failed.';
    } else {
        $student_id = (int)$_POST['student_id'];
        $course_id = (int)$_POST['course_id'];
        $attended_date = $_POST['attended_date'] ?? date('Y-m-d');
        $status = $_POST['status'];

        try {
            // Check if record exists for this date
            $stmt = $pdo->prepare("SELECT id FROM attendance_records WHERE student_id = ? AND course_id = ? AND attended_at = ?");
            $stmt->execute([$student_id, $course_id, $attended_date]);
            
            if ($stmt->fetch()) {
                // Update existing
                $stmt = $pdo->prepare("UPDATE attendance_records SET status = ? WHERE student_id = ? AND course_id = ? AND attended_at = ?");
                $stmt->execute([$status, $student_id, $course_id, $attended_date]);
            } else {
                // Insert new
                $stmt = $pdo->prepare("INSERT INTO attendance_records (student_id, course_id, attended_at, status) VALUES (?, ?, ?, ?)");
                $stmt->execute([$student_id, $course_id, $attended_date, $status]);
            }
            $success = 'Attendance recorded successfully!';
        } catch (PDOException $e) {
            $error = 'Failed to record attendance.';
        }
    }
}

// Get students and courses
$students = $pdo->query("SELECT * FROM users WHERE role = 'student' ORDER BY full_name")->fetchAll();
$courses = $pdo->query("SELECT * FROM courses ORDER BY course_name")->fetchAll();

// Get attendance records
$stmt = $pdo->query("
    SELECT ar.*, u.full_name, u.username, c.course_name
    FROM attendance_records ar
    JOIN users u ON ar.student_id = u.id
    JOIN courses c ON ar.course_id = c.id
    ORDER BY ar.attended_at DESC LIMIT 100
");
$attendances = $stmt->fetchAll();

$csrf_token = generateCSRFToken();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance - School LMS</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <div class="logo">📋 Attendance</div>
            <div class="nav-links">
                <a href="admin.php">Admin</a>
                <a href="dashboard.php">Dashboard</a>
                <a href="course-management.php">Courses</a>
                <a href="assignments.php">Assignments</a>
                <a href="user-management.php">Users</a>
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
        <h1>📋 Attendance Management</h1>
        <p class="lead">Track and manage student attendance records.</p>

        <?php if ($success): ?>
        <div class="alert success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="alert danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- Mark Attendance Form -->
        <div class="card" style="margin-bottom: 30px;">
            <h2>➕ Mark Attendance</h2>
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
                            <option value="<?php echo $course['id']; ?>"><?php echo htmlspecialchars($course['course_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Date *</label>
                        <input type="date" name="attended_date" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Status *</label>
                        <select name="status" required>
                            <option value="present">Present</option>
                            <option value="late">Late</option>
                            <option value="absent">Absent</option>
                        </select>
                    </div>

                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    <input type="hidden" name="mark_attendance" value="1">
                    <button type="submit" class="btn-primary">Mark Attendance</button>
                </div>
            </form>
        </div>

        <!-- Attendance Records -->
        <div class="card">
            <h2>📊 Recent Attendance Records</h2>
            <div class="table-container">
                <table class="attendance-table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Course</th>
                            <th>Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($attendances as $att): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($att['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($att['course_name']); ?></td>
                            <td><?php echo date('F j, Y', strtotime($att['attended_at'])); ?></td>
                            <td>
                                <span class="status-badge <?php echo $att['status']; ?>">
                                    <?php echo ucfirst($att['status']); ?>
                                </span>
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

        .form-group input,
        .form-group select {
            padding: 12px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .table-container {
            overflow-x: auto;
            margin-top: 15px;
        }

        .attendance-table {
            width: 100%;
            border-collapse: collapse;
        }

        .attendance-table th {
            background: #f7fafc;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            border-bottom: 2px solid #e2e8f0;
        }

        .attendance-table td {
            padding: 12px;
            border-bottom: 1px solid #e2e8f0;
        }

        .attendance-table tr:hover {
            background: #f7fafc;
        }

        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-badge.present {
            background: #c6f6d5;
            color: #22543d;
        }

        .status-badge.late {
            background: #feebc8;
            color: #7c2d12;
        }

        .status-badge.absent {
            background: #fed7d7;
            color: #c53030;
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
            grid-column: auto / span 2;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
        }
    </style>

    <script src="script.js"></script>
</body>
</html>
