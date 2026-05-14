<?php
require_once 'config.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: index.html');
    exit();
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$full_name = $_SESSION['full_name'];

$projectsStmt = $pdo->query('SELECT gp.*, c.course_name FROM group_projects gp JOIN courses c ON gp.course_id = c.id ORDER BY gp.created_at DESC LIMIT 10');
$projects = $projectsStmt->fetchAll();

$membersStmt = $pdo->query('SELECT pm.*, u.full_name, gp.project_title FROM project_members pm JOIN users u ON pm.user_id = u.id JOIN group_projects gp ON pm.project_id = gp.id ORDER BY pm.id DESC');
$members = $membersStmt->fetchAll();

if ($role === 'admin' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_project'])) {
    $course_id = $_POST['course_id'];
    $title = trim($_POST['project_title']);
    $description = trim($_POST['description']);
    if ($course_id && $title) {
        $stmt = $pdo->prepare('INSERT INTO group_projects (course_id, project_title, description, status) VALUES (?, ?, ?, ?)');
        $stmt->execute([$course_id, $title, $description, 'planning']);
        header('Location: collaboration.php');
        exit();
    }
}

$courses = $pdo->query('SELECT * FROM courses ORDER BY course_name')->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Collaboration - School LMS</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <div class="logo">🤝 Collaboration</div>
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
        <h1>Group Projects & Collaboration</h1>
        <p class="lead">Coordinate teamwork, manage shared workspaces, and prepare for virtual breakout sessions.</p>

        <div class="dashboard-grid">
            <div class="card">
                <h2>Project Rooms</h2>
                <?php if ($projects): ?>
                    <?php foreach ($projects as $project): ?>
                    <div class="course-item">
                        <div>
                            <h3><?php echo htmlspecialchars($project['project_title']); ?></h3>
                            <p><?php echo htmlspecialchars($project['description']); ?></p>
                            <small><?php echo htmlspecialchars($project['course_name']); ?> • <?php echo ucfirst($project['status']); ?></small>
                        </div>
                        <button class="btn-small" onclick="alert('Joining breakout room for <?php echo htmlspecialchars(addslashes($project['project_title'])); ?>')">Join Room</button>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>No active group projects yet.</p>
                <?php endif; ?>
            </div>

            <div class="card">
                <h2>Team Members</h2>
                <?php if ($members): ?>
                    <?php foreach ($members as $member): ?>
                    <div class="forum-post">
                        <div class="forum-author"><?php echo htmlspecialchars($member['full_name']); ?></div>
                        <div class="forum-content"><?php echo htmlspecialchars($member['role']); ?> • <?php echo htmlspecialchars($member['project_title']); ?></div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>No team members assigned yet.</p>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($role === 'admin'): ?>
        <div class="card">
            <h2>Create Group Project</h2>
            <form method="post" class="form-grid">
                <label>Course</label>
                <select name="course_id" required>
                    <?php foreach ($courses as $course): ?>
                    <option value="<?php echo $course['id']; ?>"><?php echo htmlspecialchars($course['course_name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <label>Project Title</label>
                <input type="text" name="project_title" required>
                <label>Description</label>
                <textarea name="description" rows="4"></textarea>
                <button class="btn-small" type="submit" name="create_project">Create Project</button>
            </form>
        </div>
        <?php endif; ?>

        <div class="card">
            <h2>Virtual Breakout Support</h2>
            <p>Breakout rooms are now reachable from your project cards. Instructors can assign groups and manage shared collaboration spaces.</p>
        </div>
    </div>

    <script src="script.js"></script>
</body>
</html>
