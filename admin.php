<?php
require_once 'config.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: index.html");
    exit();
}

$full_name = $_SESSION['full_name'];

// Get statistics
$stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'student'");
$total_students = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM courses");
$total_courses = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM exam_results");
$total_exams_taken = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT AVG(percentage) as avg FROM exam_results");
$avg_score = round($stmt->fetch()['avg'] ?? 0, 1);

// Get enrollments count
$stmt = $pdo->query("SELECT COUNT(*) as total FROM enrollments");
$total_enrollments = $stmt->fetch()['total'];

// Get assignments count
$stmt = $pdo->query("SELECT COUNT(*) as total FROM assignments WHERE status = 'pending'");
$pending_assignments = $stmt->fetch()['total'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - School LMS</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <div class="logo">🎓 School LMS Admin</div>
            <div class="nav-links">
                <a href="admin.php">Admin</a>
                <a href="dashboard.php">Dashboard</a>
                <a href="course-management.php">Courses</a>
                <a href="user-management.php">Users</a>
                <a href="enrollment.php">Enrollment</a>
                <a href="attendance.php">Attendance</a>
                <a href="assignments.php">Assignments</a>
                <a href="communication.php">Communication</a>
                <a href="analytics.php">Analytics</a>
                <a href="announcements.php">Announcements</a>
                <a href="feedback.php">Feedback</a>
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
                    <a href="dashboard.php">Dashboard</a>
                    <a href="logout.php">Logout</a>
                </div>
            </div>
            <div class="mobile-menu-btn" onclick="toggleMobileMenu()">☰</div>
        </div>
    </nav>

    <div class="container">
        <h1>Admin Dashboard</h1>
        <p class="lead">System overview and management hub.</p>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">👨‍🎓</div>
                <div class="stat-value"><?php echo $total_students; ?></div>
                <div class="stat-label">Total Students</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">📚</div>
                <div class="stat-value"><?php echo $total_courses; ?></div>
                <div class="stat-label">Total Courses</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">✓</div>
                <div class="stat-value"><?php echo $total_enrollments; ?></div>
                <div class="stat-label">Enrollments</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">📝</div>
                <div class="stat-value"><?php echo $pending_assignments; ?></div>
                <div class="stat-label">Pending Assignments</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">✏️</div>
                <div class="stat-value"><?php echo $total_exams_taken; ?></div>
                <div class="stat-label">Exams Completed</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">📊</div>
                <div class="stat-value"><?php echo $avg_score; ?>%</div>
                <div class="stat-label">Average Score</div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="quick-actions">
            <a href="user-management.php" class="action-card">
                <span class="action-icon">👥</span>
                <span class="action-label">Manage Users</span>
            </a>
            <a href="enrollment.php" class="action-card">
                <span class="action-icon">📚</span>
                <span class="action-label">Manage Enrollment</span>
            </a>
            <a href="attendance.php" class="action-card">
                <span class="action-icon">📋</span>
                <span class="action-label">Track Attendance</span>
            </a>
            <a href="assignments.php" class="action-card">
                <span class="action-icon">📝</span>
                <span class="action-label">Grade Assignments</span>
            </a>
            <a href="course-management.php" class="action-card">
                <span class="action-icon">📖</span>
                <span class="action-label">Manage Courses</span>
            </a>
            <a href="announcements.php" class="action-card">
                <span class="action-icon">📢</span>
                <span class="action-label">Post Announcement</span>
            </a>
        </div>
        
        <div class="admin-sections">
            <div class="card">
                <h2>👥 Recent Students</h2>
                <table class="admin-table">
                    <thead>
                        <tr><th>ID</th><th>Name</th><th>Username</th><th>Email</th><th>Joined</th></tr>
                    </thead>
                    <tbody>
                        <?php
                        $stmt = $pdo->query("SELECT id, full_name, username, email, created_at FROM users WHERE role = 'student' ORDER BY created_at DESC LIMIT 10");
                        while($student = $stmt->fetch()): ?>
                        <tr>
                            <td><?php echo $student['id']; ?></td>
                            <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($student['username']); ?></td>
                            <td><?php echo htmlspecialchars($student['email']); ?></td>
                            <td><?php echo date('M j, Y', strtotime($student['created_at'])); ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="card">
                <h2>📈 System Analytics</h2>
                <div class="analytics-grid">
                    <div class="analytics-item">
                        <h4>Top Performing Students</h4>
                        <ul>
                            <?php
                            $stmt = $pdo->query("
                                SELECT u.full_name, AVG(er.percentage) as avg_score 
                                FROM exam_results er 
                                JOIN users u ON er.student_id = u.id 
                                GROUP BY er.student_id 
                                ORDER BY avg_score DESC LIMIT 5
                            ");
                            while($top = $stmt->fetch()): ?>
                            <li><?php echo htmlspecialchars($top['full_name']); ?> - <?php echo round($top['avg_score'], 1); ?>%</li>
                            <?php endwhile; ?>
                        </ul>
                    </div>
                    <div class="analytics-item">
                        <h4>Recent Activity</h4>
                        <ul>
                            <?php
                            $stmt = $pdo->query("
                                SELECT u.full_name, e.exam_title, er.percentage, er.taken_at 
                                FROM exam_results er 
                                JOIN users u ON er.student_id = u.id 
                                JOIN exams e ON er.exam_id = e.id 
                                ORDER BY er.taken_at DESC LIMIT 5
                            ");
                            while($recent = $stmt->fetch()): ?>
                            <li><?php echo htmlspecialchars($recent['full_name']); ?> took <?php echo htmlspecialchars($recent['exam_title']); ?> - <?php echo $recent['percentage']; ?>%</li>
                            <?php endwhile; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <style>
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin: 30px 0;
        }

        .action-card {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
            text-decoration: none;
            transition: transform 0.3s, box-shadow 0.3s;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .action-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 12px rgba(0,0,0,0.15);
        }

        .action-icon {
            display: block;
            font-size: 32px;
            margin-bottom: 10px;
        }

        .action-label {
            display: block;
            font-weight: 600;
            font-size: 14px;
        }

        .admin-table {
            width: 100%;
            border-collapse: collapse;
        }

        .admin-table th {
            background: #f7fafc;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            border-bottom: 2px solid #e2e8f0;
        }

        .admin-table td {
            padding: 12px;
            border-bottom: 1px solid #e2e8f0;
        }

        .admin-table tr:hover {
            background: #f7fafc;
        }

        .analytics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }

        .analytics-item h4 {
            margin-top: 0;
            color: var(--dark);
        }

        .analytics-item ul {
            list-style: none;
            padding: 0;
        }

        .analytics-item li {
            padding: 8px 0;
            border-bottom: 1px solid #e2e8f0;
            color: var(--gray);
        }

        .analytics-item li:last-child {
            border-bottom: none;
        }
    </style>
    
    <script src="script.js"></script>
</body>
</html>