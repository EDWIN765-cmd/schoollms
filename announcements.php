<?php
require_once 'config.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: index.html");
    exit();
}

// Get announcements
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$success = false;
$error = '';

// Handle new announcement (admin only)
if ($role == 'admin' && $_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_announcement'])) {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');

    if ($title === '' || $content === '') {
        $error = 'Please provide both a title and content for the announcement.';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO announcements (title, content, created_by) VALUES (?, ?, ?)");
            $stmt->execute([$title, $content, $user_id]);
            $success = true;
        } catch (PDOException $e) {
            $error = 'Unable to save announcement. Please try again later.';
        }
    }
}

$stmt = $pdo->query("SELECT * FROM announcements ORDER BY created_at DESC LIMIT 10");
$announcements = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcements - School LMS</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <div class="logo">📢 Announcements</div>
            <div class="nav-links">
                <a href="dashboard.php">Dashboard</a>
                <a href="course-management.php">Courses</a>
                <a href="communication.php">Communication</a>
                <a href="analytics.php">Analytics</a>
                <a href="exams.php">Exams</a>
                <a href="assignments.php">Assignments</a>
                <?php if($role == 'admin'): ?>
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
        <h1>📢 Announcements & Updates</h1>
        
        <?php if (!empty($error)): ?>
            <div class="alert danger"><?php echo htmlspecialchars($error); ?></div>
        <?php elseif ($success): ?>
            <div class="alert success">Announcement added successfully!</div>
        <?php endif; ?>
        
        <?php if($role == 'admin'): ?>
        <div class="card" style="margin-bottom: 30px;">
            <h2>➕ Add New Announcement</h2>
            <form method="POST">
                <input type="text" name="title" placeholder="Announcement Title" required style="width:100%; padding:12px; margin-bottom:10px; border-radius:10px; border:1px solid #ddd;">
                <textarea name="content" rows="3" placeholder="Announcement Content" required style="width:100%; padding:12px; margin-bottom:10px; border-radius:10px; border:1px solid #ddd;"></textarea>
                <button type="submit" name="add_announcement" class="btn-start">Post Announcement</button>
            </form>
        </div>
        <?php endif; ?>
        
        <div class="announcements-list">
            <?php foreach($announcements as $ann): ?>
            <div class="announcement-item">
                <div class="announcement-title">📌 <?php echo htmlspecialchars($ann['title']); ?></div>
                <div class="announcement-content"><?php echo nl2br(htmlspecialchars($ann['content'])); ?></div>
                <div class="announcement-date">🗓️ <?php echo date('F j, Y \a\t g:i A', strtotime($ann['created_at'])); ?></div>
            </div>
            <?php endforeach; ?>
            
            <?php if(empty($announcements)): ?>
            <div class="card">No announcements yet. Check back later!</div>
            <?php endif; ?>
        </div>
    </div>
    
    <script src="script.js"></script>
</body>
</html>