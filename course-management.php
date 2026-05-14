<?php
require_once 'config.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: index.html');
    exit();
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$full_name = $_SESSION['full_name'];

// Fetch available courses
if ($role === 'admin') {
    $courseStmt = $pdo->query('SELECT * FROM courses ORDER BY course_name');
} else {
    $courseStmt = $pdo->prepare('SELECT c.* FROM courses c JOIN enrollments e ON c.id = e.course_id WHERE e.student_id = ? ORDER BY c.course_name');
    $courseStmt->execute([$user_id]);
}
$courses = $courseStmt->fetchAll();

$search = trim($_GET['search'] ?? '');
$searchSql = $search ? 'AND (sm.title LIKE ? OR cm.module_title LIKE ? OR c.course_name LIKE ?)' : '';
$params = [];
if ($search) {
    $searchTerm = "%{$search}%";
    $params = [$searchTerm, $searchTerm, $searchTerm];
}

$materialsQuery = "SELECT sm.*, c.course_name FROM study_materials sm JOIN courses c ON sm.course_id = c.id ";
$modulesQuery = "SELECT cm.*, c.course_name FROM course_modules cm JOIN courses c ON cm.course_id = c.id ";
if ($role !== 'admin') {
    $materialsQuery .= 'JOIN enrollments e ON sm.course_id = e.course_id WHERE e.student_id = ? ' . $searchSql;
    $modulesQuery .= 'JOIN enrollments e ON cm.course_id = e.course_id WHERE e.student_id = ? ' . $searchSql;
    if ($search) {
        array_unshift($params, $user_id);
    } else {
        $params = [$user_id];
    }
} else {
    $materialsQuery .= 'WHERE 1=1 ' . $searchSql;
    $modulesQuery .= 'WHERE 1=1 ' . $searchSql;
}
$materialsQuery .= ' ORDER BY sm.uploaded_at DESC LIMIT 20';
$modulesQuery .= ' ORDER BY cm.position ASC LIMIT 20';

$materialsStmt = $pdo->prepare($materialsQuery);
$materialsStmt->execute($params);
$materials = $materialsStmt->fetchAll();

$modulesStmt = $pdo->prepare($modulesQuery);
$modulesStmt->execute($params);
$modules = $modulesStmt->fetchAll();

$message = '';
if ($role === 'admin' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_module'])) {
        $course_id = $_POST['course_id'];
        $module_title = trim($_POST['module_title']);
        $module_description = trim($_POST['module_description']);
        $position = (int)($_POST['position'] ?? 0);

        if ($course_id && $module_title) {
            $stmt = $pdo->prepare('INSERT INTO course_modules (course_id, module_title, module_description, position) VALUES (?, ?, ?, ?)');
            $stmt->execute([$course_id, $module_title, $module_description, $position]);
            $message = 'Module added successfully.';
            header('Location: course-management.php');
            exit();
        }
    }
    if (isset($_POST['add_material'])) {
        $course_id = $_POST['course_id'];
        $title = trim($_POST['title']);
        $material_type = $_POST['material_type'];
        $resource_url = trim($_POST['resource_url']);

        if ($course_id && $title) {
            $stmt = $pdo->prepare('INSERT INTO study_materials (course_id, title, material_type, resource_url, uploaded_by) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$course_id, $title, $material_type, $resource_url, $user_id]);
            $message = 'Material uploaded successfully.';
            header('Location: course-management.php');
            exit();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Course Management - School LMS</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <div class="logo">📘 Course Management</div>
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
        <h1>Course Management Hub</h1>
        <p class="lead">Organize modules, upload materials, and build accessible learning paths for every course.</p>

        <?php if ($message): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <div class="page-section">
            <h2>Search Resources</h2>
            <form method="get" class="search-form">
                <input type="search" name="search" class="search-input" placeholder="Search modules, materials, or courses" value="<?php echo htmlspecialchars($search); ?>">
                <button class="btn-small" type="submit">Search</button>
            </form>
        </div>

        <div class="dashboard-grid">
            <div class="card">
                <h2>Course Modules</h2>
                <?php if ($modules): ?>
                    <?php foreach ($modules as $module): ?>
                    <div class="course-item">
                        <div>
                            <h3><?php echo htmlspecialchars($module['module_title']); ?></h3>
                            <p><?php echo htmlspecialchars($module['module_description']); ?></p>
                            <small><?php echo htmlspecialchars($module['course_name']); ?></small>
                        </div>
                        <span class="badge"><?php echo $module['position']; ?></span>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>No modules found yet.</p>
                <?php endif; ?>
            </div>

            <div class="card">
                <h2>Learning Materials</h2>
                <?php if ($materials): ?>
                    <div class="materials-grid">
                        <?php foreach ($materials as $material): ?>
                        <div class="material-item" onclick="window.open('<?php echo htmlspecialchars($material['resource_url']); ?>', '_blank')">
                            <div class="material-icon"><?php echo $material['material_type'] === 'video' ? '🎥' : ($material['material_type'] === 'pdf' ? '📄' : '📎'); ?></div>
                            <strong><?php echo htmlspecialchars($material['title']); ?></strong>
                            <small><?php echo htmlspecialchars($material['course_name']); ?></small>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p>No resources match your search.</p>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($role === 'admin'): ?>
        <div class="dashboard-grid">
            <div class="card">
                <h2>Upload New Material</h2>
                <form method="post" class="form-grid">
                    <label>Course</label>
                    <select name="course_id" required>
                        <?php foreach ($courses as $course): ?>
                        <option value="<?php echo $course['id']; ?>"><?php echo htmlspecialchars($course['course_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <label>Title</label>
                    <input type="text" name="title" required>
                    <label>Type</label>
                    <select name="material_type">
                        <option value="video">Video</option>
                        <option value="pdf">PDF</option>
                        <option value="slides">Slides</option>
                        <option value="quiz">Quiz</option>
                        <option value="document">Document</option>
                    </select>
                    <label>Resource URL</label>
                    <input type="url" name="resource_url" placeholder="https://...">
                    <button class="btn-small" type="submit" name="add_material">Upload Material</button>
                </form>
            </div>
            <div class="card">
                <h2>Create Module / Lesson</h2>
                <form method="post" class="form-grid">
                    <label>Course</label>
                    <select name="course_id" required>
                        <?php foreach ($courses as $course): ?>
                        <option value="<?php echo $course['id']; ?>"><?php echo htmlspecialchars($course['course_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <label>Module Title</label>
                    <input type="text" name="module_title" required>
                    <label>Description</label>
                    <textarea name="module_description" rows="4"></textarea>
                    <label>Position</label>
                    <input type="number" name="position" min="1" value="1">
                    <button class="btn-small" type="submit" name="add_module">Add Module</button>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <div class="card">
            <h2>Resource Library</h2>
            <p>Centralized repository for videos, documents, quizzes, and slides. Use search or filters to find content fast.</p>
        </div>
    </div>

    <script src="script.js"></script>
</body>
</html>
