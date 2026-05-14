<?php
require_once 'config.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: index.html');
    exit();
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$full_name = $_SESSION['full_name'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['new_thread'])) {
        $course_id = $_POST['course_id'];
        $title = trim($_POST['title']);
        $message = trim($_POST['message']);
        if ($course_id && $title && $message) {
            $stmt = $pdo->prepare('INSERT INTO forum_threads (course_id, title, started_by) VALUES (?, ?, ?)');
            $stmt->execute([$course_id, $title, $user_id]);
            $thread_id = $pdo->lastInsertId();
            $postStmt = $pdo->prepare('INSERT INTO forum_posts (thread_id, user_id, content) VALUES (?, ?, ?)');
            $postStmt->execute([$thread_id, $user_id, $message]);
            header('Location: communication.php');
            exit();
        }
    }
    if (isset($_POST['reply_thread'])) {
        $thread_id = $_POST['thread_id'];
        $content = trim($_POST['content']);
        if ($thread_id && $content) {
            $stmt = $pdo->prepare('INSERT INTO forum_posts (thread_id, user_id, content) VALUES (?, ?, ?)');
            $stmt->execute([$thread_id, $user_id, $content]);
            header('Location: communication.php');
            exit();
        }
    }
}

$threadStmt = $pdo->query('SELECT ft.*, c.course_name, u.full_name AS starter FROM forum_threads ft JOIN courses c ON ft.course_id = c.id JOIN users u ON ft.started_by = u.id ORDER BY ft.created_at DESC LIMIT 10');
$threads = $threadStmt->fetchAll();

$courseStmt = $pdo->prepare('SELECT c.* FROM courses c JOIN enrollments e ON c.id = e.course_id WHERE e.student_id = ? ORDER BY c.course_name');
$courseStmt->execute([$user_id]);
$courses = $courseStmt->fetchAll();

if ($role === 'admin') {
    $adminCourses = $pdo->query('SELECT * FROM courses ORDER BY course_name')->fetchAll();
} else {
    $adminCourses = $courses;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Communication - School LMS</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <div class="logo">💬 Communication</div>
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
        <h1>Communication Tools</h1>
        <p class="lead">Discussion forums, live chat-style posts, and feedback all in one place.</p>

        <div class="dashboard-grid">
            <div class="card">
                <h2>Discussion Forums</h2>
                <?php if ($threads): ?>
                    <?php foreach ($threads as $thread): ?>
                    <div class="forum-post">
                        <div class="forum-author"><?php echo htmlspecialchars($thread['starter']); ?> • <?php echo htmlspecialchars($thread['course_name']); ?></div>
                        <div class="forum-content"><?php echo htmlspecialchars($thread['title']); ?></div>
                        <div class="forum-meta"><?php echo date('M d, Y', strtotime($thread['created_at'])); ?></div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>No discussions yet. Start one to get conversations going.</p>
                <?php endif; ?>
            </div>
            <div class="card">
                <h2>Start a New Thread</h2>
                <form method="post" class="form-grid">
                    <label>Course</label>
                    <select name="course_id" required>
                        <?php foreach ($adminCourses as $course): ?>
                        <option value="<?php echo $course['id']; ?>"><?php echo htmlspecialchars($course['course_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <label>Thread Title</label>
                    <input type="text" name="title" required>
                    <label>Message</label>
                    <textarea name="message" rows="4" required></textarea>
                    <button class="btn-small" type="submit" name="new_thread">Create Thread</button>
                </form>
            </div>
        </div>

        <div class="card">
            <h2>Live Chat & Quick Feedback</h2>
            <p>Use this section for quick synchronous communication, announcements, or instant feedback reminders.</p>
            <div class="forum-post">
                <div class="forum-author">System</div>
                <div class="forum-content">Chat and live message integration is ready for Zoom, Teams, and email connectors.</div>
            </div>
        </div>
    </div>

    <script src="script.js"></script>
</body>
</html>
