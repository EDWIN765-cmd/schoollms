<?php
require_once 'config.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: index.html');
    exit();
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$full_name = $_SESSION['full_name'];

$attendanceStmt = $pdo->prepare('SELECT COUNT(*) AS total, SUM(status = "present") AS present, SUM(status = "late") AS late FROM attendance_records WHERE student_id = ?');
$attendanceStmt->execute([$user_id]);
$attendance = $attendanceStmt->fetch();

$completionStmt = $pdo->prepare('SELECT COUNT(*) AS total, SUM(grade >= 60) AS passed FROM assignment_submissions WHERE student_id = ?');
$completionStmt->execute([$user_id]);
$completion = $completionStmt->fetch();

$gradeStmt = $pdo->prepare('SELECT AVG(grade) AS avg_grade FROM assignment_submissions WHERE student_id = ?');
$gradeStmt->execute([$user_id]);
$avgGrade = round($gradeStmt->fetch()['avg_grade'] ?? 0, 1);

$surveyStmt = $pdo->prepare('SELECT COUNT(*) AS total FROM survey_responses WHERE user_id = ?');
$surveyStmt->execute([$user_id]);
$feedbackCount = $surveyStmt->fetch()['total'];

$reportCount = $pdo->prepare('SELECT COUNT(*) AS total FROM exam_results WHERE student_id = ?');
$reportCount->execute([$user_id]);
$reports = $reportCount->fetch()['total'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Analytics - School LMS</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <div class="logo">📊 Analytics</div>
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
                    $notifStmt = $pdo->query('SELECT title, created_at FROM announcements ORDER BY created_at DESC LIMIT 3');
                    while ($notif = $notifStmt->fetch()): ?>
                    <a href="announcements.php" class="dropdown-item">
                        <?php echo htmlspecialchars($notif['title']); ?>
                        <span><?php echo date('M j', strtotime($notif['created_at'])); ?></span>
                    </a>
                    <?php endwhile; ?>
                    <a href="announcements.php" class="dropdown-action">View all announcements</a>
                </div>
                <button class="nav-action" type="button" onclick="toggleDropdown('profileDropdown')">
                    <?php echo htmlspecialchars($full_name ?? $_SESSION['username']); ?> ▼
                </button>
                <div class="dropdown profile-dropdown" id="profileDropdown">
                    <a href="dashboard.php">Dashboard</a>
                    <a href="assignments.php">Assignments</a>
                    <a href="announcements.php">Announcements</a>
                    <a href="logout.php">Logout</a>
                </div>
            </div>
            <div class="mobile-menu-btn" onclick="toggleMobileMenu()">☰</div>
        </div>
    </nav>

    <div class="container">
        <h1>Progress Tracking & Reports</h1>
        <p class="lead">Monitor attendance, grades, completion rates, and feedback across your learning journey.</p>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">🕒</div>
                <div class="stat-value"><?php echo htmlspecialchars($attendance['present'] ?? 0); ?></div>
                <div class="stat-label">Days Present</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">📉</div>
                <div class="stat-value"><?php echo htmlspecialchars($attendance['late'] ?? 0); ?></div>
                <div class="stat-label">Days Late</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">🏁</div>
                <div class="stat-value"><?php echo htmlspecialchars($completion['passed'] ?? 0); ?>/<?php echo htmlspecialchars($completion['total'] ?? 0); ?></div>
                <div class="stat-label">Tasks Passed</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">⭐</div>
                <div class="stat-value"><?php echo htmlspecialchars($avgGrade); ?>%</div>
                <div class="stat-label">Average Grade</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">🗂️</div>
                <div class="stat-value"><?php echo htmlspecialchars($reports); ?></div>
                <div class="stat-label">Reports Generated</div>
            </div>
        </div>

        <div class="dashboard-grid">
            <div class="card">
                <h2>Adaptive Learning Path</h2>
                <p>Your performance is analyzed to recommend next modules, review materials, and targeted assignments.</p>
                <div class="progress-bar"><div class="progress-fill" style="width: <?php echo min(100, $avgGrade); ?>%"></div></div>
                <p class="small">Learning path is currently <?php echo min(100, $avgGrade); ?>% aligned with your progress.</p>
            </div>

            <div class="card">
                <h2>Engagement Summary</h2>
                <p>Attendance, completion, and feedback are combined to create a holistic learner report.</p>
                <ul>
                    <li>Feedback responses: <?php echo htmlspecialchars($feedbackCount); ?></li>
                    <li>Attendance: <?php echo htmlspecialchars(($attendance['present'] ?? 0) + ($attendance['late'] ?? 0)); ?> sessions</li>
                    <li>Learning completion: <?php echo round((($completion['passed'] ?? 0) / max(1, ($completion['total'] ?? 1))) * 100); ?>%</li>
                </ul>
            </div>
        </div>
    </div>

    <script src="script.js"></script>
</body>
</html>
