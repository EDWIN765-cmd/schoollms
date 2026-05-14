<?php
require_once 'config.php';

// Check login
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['username'])) {
    $username = sanitizeInput($_POST['username']);
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    
    if ($user && (verifyPassword($_POST['password'], $user['password']) || md5($_POST['password']) === $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['full_name'] = $user['full_name'];
        
        if ($user['role'] == 'admin') {
            header("Location: admin.php");
        } else {
            header("Location: dashboard.php");
        }
        exit();
    } else {
        $error = "Invalid credentials!";
    }
}

// If already logged in, show dashboard
if (!isset($_SESSION['user_id'])) {
    header("Location: index.html");
    exit();
}

$user_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];

// Get enrolled courses
$stmt = $pdo->prepare("
    SELECT c.* FROM courses c 
    JOIN enrollments e ON c.id = e.course_id 
    WHERE e.student_id = ?
");
$stmt->execute([$user_id]);
$courses = $stmt->fetchAll();

// Get exam results
$stmt = $pdo->prepare("
    SELECT e.exam_title, er.score, er.total_questions, er.percentage, er.taken_at 
    FROM exam_results er 
    JOIN exams e ON er.exam_id = e.id 
    WHERE er.student_id = ? 
    ORDER BY er.taken_at DESC LIMIT 5
");
$stmt->execute([$user_id]);
$results = $stmt->fetchAll();
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM assignments WHERE student_id = ? AND status = 'pending'");
$stmt->execute([$user_id]);
$pendingAssignments = $stmt->fetch()['total'];

$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM study_materials sm JOIN enrollments e ON sm.course_id = e.course_id WHERE e.student_id = ?");
$stmt->execute([$user_id]);
$studyMaterials = $stmt->fetch()['total'];

$stmt = $pdo->prepare("SELECT AVG(percentage) as avg_score FROM exam_results WHERE student_id = ?");
$stmt->execute([$user_id]);
$avgScore = round($stmt->fetch()['avg_score'] ?? 0, 1);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Student Dashboard - School LMS</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <!-- Top Navbar -->
    <nav class="navbar">
        <div class="nav-container">
            <button class="sidebar-toggle" onclick="toggleSidebar()">☰</button>
            <div class="logo">🎓 School LMS</div>
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
                    <a href="profile.php">Profile</a>
                    <a href="dashboard.php">Dashboard</a>
                    <a href="logout.php">Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h3><?php echo htmlspecialchars(substr($full_name, 0, 1)); ?></h3>
            <p><?php echo htmlspecialchars($full_name); ?></p>
        </div>

        <div class="sidebar-nav">
            <a href="dashboard.php" class="sidebar-item active">
                <span class="icon">📊</span>
                <span class="label">Dashboard</span>
            </a>
            <a href="course-management.php" class="sidebar-item">
                <span class="icon">📚</span>
                <span class="label">Courses</span>
            </a>
            <a href="assignments.php" class="sidebar-item">
                <span class="icon">📝</span>
                <span class="label">Assignments</span>
            </a>
            <a href="exams.php" class="sidebar-item">
                <span class="icon">✏️</span>
                <span class="label">Exams</span>
            </a>
            <a href="communication.php" class="sidebar-item">
                <span class="icon">💬</span>
                <span class="label">Forums</span>
            </a>
            <a href="collaboration.php" class="sidebar-item">
                <span class="icon">🤝</span>
                <span class="label">Groups</span>
            </a>
            <a href="analytics.php" class="sidebar-item">
                <span class="icon">📈</span>
                <span class="label">Analytics</span>
            </a>
            <a href="announcements.php" class="sidebar-item">
                <span class="icon">📢</span>
                <span class="label">Announcements</span>
            </a>
            <a href="feedback.php" class="sidebar-item">
                <span class="icon">⭐</span>
                <span class="label">Feedback</span>
            </a>
            <a href="ai-tutor.php" class="sidebar-item">
                <span class="icon">🤖</span>
                <span class="label">AI Tutor</span>
            </a>
        </div>

        <div class="sidebar-footer">
            <a href="profile.php" class="sidebar-item">
                <span class="icon">⚙️</span>
                <span class="label">Settings</span>
            </a>
            <a href="logout.php" class="sidebar-item logout">
                <span class="icon">🚪</span>
                <span class="label">Logout</span>
            </a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="dashboard-wrapper">
            <!-- Carousel Section -->
            <div class="carousel-section">
                <div class="carousel">
                    <div class="carousel-inner">
                        <div class="carousel-slide active">
                            <div class="carousel-content">
                                <div class="carousel-image" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                                    <div class="carousel-text">
                                        <h2>Welcome to Learning 🎓</h2>
                                        <p>Start your journey to success</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="carousel-slide">
                            <div class="carousel-content">
                                <div class="carousel-image" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                                    <div class="carousel-text">
                                        <h2>Master New Skills 📚</h2>
                                        <p>Explore our comprehensive course catalog</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="carousel-slide">
                            <div class="carousel-content">
                                <div class="carousel-image" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                                    <div class="carousel-text">
                                        <h2>Collaborate & Grow 🤝</h2>
                                        <p>Work with peers and achieve together</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="carousel-slide">
                            <div class="carousel-content">
                                <div class="carousel-image" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                                    <div class="carousel-text">
                                        <h2>Track Progress 📈</h2>
                                        <p>Monitor your learning and improvement</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Carousel Controls -->
                    <button class="carousel-control prev" onclick="changeSlide(-1)">❮</button>
                    <button class="carousel-control next" onclick="changeSlide(1)">❯</button>

                    <!-- Carousel Indicators -->
                    <div class="carousel-indicators">
                        <span class="indicator active" onclick="currentSlide(1)"></span>
                        <span class="indicator" onclick="currentSlide(2)"></span>
                        <span class="indicator" onclick="currentSlide(3)"></span>
                        <span class="indicator" onclick="currentSlide(4)"></span>
                    </div>
                </div>
            </div>

            <!-- Welcome Section -->
            <div class="welcome-section">
                <h1>Welcome back, <?php echo htmlspecialchars($full_name); ?>! 👋</h1>
                <p>Your learning journey continues</p>
            </div>

            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">📚</div>
                    <div class="stat-value"><?php echo count($courses); ?></div>
                    <div class="stat-label">Enrolled Courses</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">✅</div>
                    <div class="stat-value"><?php echo count($results); ?></div>
                    <div class="stat-label">Exams Taken</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">📈</div>
                    <div class="stat-value"><?php echo htmlspecialchars($avgScore); ?>%</div>
                    <div class="stat-label">Avg Score</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">⏳</div>
                    <div class="stat-value"><?php echo htmlspecialchars($pendingAssignments); ?></div>
                    <div class="stat-label">Pending</div>
                </div>
            </div>

            <!-- Feature Grid -->
            <div class="feature-grid">
                <a href="course-management.php" class="feature-card">
                    <h3>📘 Courses</h3>
                    <p>Access materials and modules</p>
                </a>
                <a href="communication.php" class="feature-card">
                    <h3>💬 Forums</h3>
                    <p>Collaborate with classmates</p>
                </a>
                <a href="analytics.php" class="feature-card">
                    <h3>📈 Progress</h3>
                    <p>Track your grades</p>
                </a>
                <a href="collaboration.php" class="feature-card">
                    <h3>🤝 Groups</h3>
                    <p>Team projects</p>
                </a>
            </div>

            <!-- Dashboard Grid -->
            <div class="dashboard-grid">
                <!-- My Courses -->
                <div class="card">
                    <h2>📖 My Courses</h2>
                    <div class="course-list">
                        <?php foreach($courses as $course): ?>
                        <div class="course-item">
                            <div>
                                <h3><?php echo htmlspecialchars($course['course_code']); ?></h3>
                                <p><?php echo htmlspecialchars(substr($course['course_name'], 0, 50)); ?></p>
                                <small><?php echo htmlspecialchars($course['instructor']); ?></small>
                            </div>
                            <a href="course-management.php" class="btn-small">View</a>
                        </div>
                        <?php endforeach; ?>
                        <?php if(empty($courses)): ?>
                        <p style="text-align: center; color: var(--gray);">No courses yet</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recent Announcements -->
                <div class="card">
                    <h2>📢 Announcements</h2>
                    <div class="announcements-list">
                        <?php
                        $stmt = $pdo->query("SELECT * FROM announcements ORDER BY created_at DESC LIMIT 3");
                        $recent_announcements = $stmt->fetchAll();
                        foreach($recent_announcements as $ann):
                        ?>
                        <div class="announcement-item">
                            <div class="announcement-title">📌 <?php echo htmlspecialchars($ann['title']); ?></div>
                            <div class="announcement-date"><?php echo date('M d, Y', strtotime($ann['created_at'])); ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <a href="announcements.php" class="btn-small" style="display:inline-block; margin-top:15px;">View All →</a>
                </div>

                <!-- Upcoming Assignments -->
                <div class="card">
                    <h2>📝 Assignments</h2>
                    <div class="assignments-list">
                        <?php
                        $stmt = $pdo->prepare("
                            SELECT a.*, c.course_name 
                            FROM assignments a 
                            JOIN courses c ON a.course_id = c.id 
                            WHERE a.student_id = ? AND a.status = 'pending'
                            ORDER BY a.due_date ASC LIMIT 3
                        ");
                        $stmt->execute([$user_id]);
                        $upcoming = $stmt->fetchAll();
                        foreach($upcoming as $assign):
                        ?>
                        <div class="assignment-item">
                            <div>
                                <strong><?php echo htmlspecialchars($assign['title']); ?></strong>
                                <small>Due: <?php echo date('M d, Y', strtotime($assign['due_date'])); ?></small>
                            </div>
                            <span class="assignment-status status-pending">Pending</span>
                        </div>
                        <?php endforeach; ?>
                        <?php if(empty($upcoming)): ?>
                        <p style="text-align: center; color: var(--gray);">No pending assignments</p>
                        <?php endif; ?>
                    </div>
                    <a href="assignments.php" class="btn-small" style="display:inline-block; margin-top:15px;">View All →</a>
                </div>

                <!-- Study Materials -->
                <div class="card">
                    <h2>📚 Materials</h2>
                    <div class="materials-grid">
                        <?php
                        $stmt = $pdo->prepare("
                            SELECT sm.*, c.course_name 
                            FROM study_materials sm 
                            JOIN courses c ON sm.course_id = c.id 
                            JOIN enrollments e ON c.id = e.course_id 
                            WHERE e.student_id = ? 
                            LIMIT 6
                        ");
                        $stmt->execute([$user_id]);
                        $materials = $stmt->fetchAll();
                        foreach($materials as $material):
                        ?>
                        <div class="material-item" onclick="alert('Opening <?php echo $material['title']; ?>')">
                            <div class="material-icon">
                                <?php echo $material['material_type'] == 'video' ? '🎥' : ($material['material_type'] == 'pdf' ? '📄' : '🔗'); ?>
                            </div>
                            <div><?php echo htmlspecialchars($material['title']); ?></div>
                            <small><?php echo htmlspecialchars($material['course_name']); ?></small>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        /* Sidebar Styles */
        .sidebar {
            position: fixed;
            left: 0;
            top: 60px;
            width: 250px;
            height: calc(100vh - 60px);
            background: linear-gradient(180deg, #2d3748 0%, #1a202c 100%);
            color: white;
            overflow-y: auto;
            transition: transform 0.3s ease;
            z-index: 999;
        }

        .sidebar-toggle {
            display: none;
            background: none;
            border: none;
            color: white;
            font-size: 24px;
            cursor: pointer;
            margin-right: 15px;
        }

        .sidebar-header {
            padding: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .sidebar-header h3 {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            margin-bottom: 10px;
        }

        .sidebar-header p {
            margin: 0;
            font-size: 14px;
            color: #cbd5e0;
        }

        .sidebar-nav {
            padding: 20px 0;
        }

        .sidebar-item {
            display: flex;
            align-items: center;
            padding: 15px 20px;
            color: #cbd5e0;
            text-decoration: none;
            transition: all 0.3s;
            border-left: 3px solid transparent;
        }

        .sidebar-item:hover {
            background: rgba(255,255,255,0.1);
            color: white;
            border-left-color: var(--primary);
        }

        .sidebar-item.active {
            background: rgba(102, 126, 234, 0.2);
            color: var(--primary);
            border-left-color: var(--primary);
        }

        .sidebar-item .icon {
            width: 24px;
            margin-right: 12px;
            text-align: center;
        }

        .sidebar-item .label {
            flex: 1;
        }

        .sidebar-footer {
            position: absolute;
            bottom: 0;
            width: 100%;
            border-top: 1px solid rgba(255,255,255,0.1);
        }

        .sidebar-item.logout {
            color: #f56565;
        }

        .sidebar-item.logout:hover {
            background: rgba(245, 101, 101, 0.1);
        }

        /* Main Content */
        .main-content {
            margin-left: 250px;
            margin-top: 60px;
            min-height: calc(100vh - 60px);
            background: #f7fafc;
            transition: margin-left 0.3s ease;
        }

        .dashboard-wrapper {
            padding: 20px;
            max-width: 1400px;
            margin: 0 auto;
        }

        /* Carousel Styles */
        .carousel-section {
            margin-bottom: 30px;
        }

        .carousel {
            position: relative;
            width: 100%;
            height: 350px;
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .carousel-inner {
            position: relative;
            width: 100%;
            height: 100%;
        }

        .carousel-slide {
            position: absolute;
            width: 100%;
            height: 100%;
            opacity: 0;
            transition: opacity 0.5s ease;
        }

        .carousel-slide.active {
            opacity: 1;
        }

        .carousel-content {
            width: 100%;
            height: 100%;
        }

        .carousel-image {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        .carousel-text {
            color: white;
            text-align: center;
            z-index: 10;
        }

        .carousel-text h2 {
            font-size: 2.5em;
            margin: 0 0 10px 0;
            text-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }

        .carousel-text p {
            font-size: 1.2em;
            margin: 0;
            text-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }

        .carousel-control {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(0,0,0,0.5);
            color: white;
            border: none;
            padding: 15px 20px;
            font-size: 20px;
            cursor: pointer;
            transition: background 0.3s;
            z-index: 20;
        }

        .carousel-control:hover {
            background: rgba(0,0,0,0.8);
        }

        .carousel-control.prev {
            left: 15px;
        }

        .carousel-control.next {
            right: 15px;
        }

        .carousel-indicators {
            position: absolute;
            bottom: 15px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            gap: 10px;
            z-index: 20;
        }

        .indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: rgba(255,255,255,0.5);
            cursor: pointer;
            transition: all 0.3s;
        }

        .indicator.active {
            background: white;
            width: 30px;
            border-radius: 6px;
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.open {
                transform: translateX(0);
            }

            .sidebar-toggle {
                display: block;
            }

            .main-content {
                margin-left: 0;
            }

            .carousel {
                height: 250px;
            }

            .carousel-text h2 {
                font-size: 1.8em;
            }

            .carousel-text p {
                font-size: 0.9em;
            }

            .stats-grid {
                grid-template-columns: 1fr 1fr;
            }

            .feature-grid {
                grid-template-columns: 1fr;
            }

            .dashboard-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <script>
        // Carousel functionality
        let slideIndex = 1;

        function changeSlide(n) {
            showSlide(slideIndex += n);
        }

        function currentSlide(n) {
            showSlide(slideIndex = n);
        }

        function showSlide(n) {
            const slides = document.querySelectorAll('.carousel-slide');
            const indicators = document.querySelectorAll('.indicator');
            
            if (n > slides.length) slideIndex = 1;
            if (n < 1) slideIndex = slides.length;
            
            slides.forEach(slide => slide.classList.remove('active'));
            indicators.forEach(indicator => indicator.classList.remove('active'));
            
            slides[slideIndex - 1].classList.add('active');
            indicators[slideIndex - 1].classList.add('active');
        }

        // Auto-play carousel
        setInterval(() => {
            changeSlide(1);
        }, 5000);

        // Sidebar toggle
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('open');
        }

        // Close sidebar when clicking a link
        document.querySelectorAll('.sidebar-item').forEach(item => {
            item.addEventListener('click', () => {
                if (window.innerWidth <= 768) {
                    document.getElementById('sidebar').classList.remove('open');
                }
            });
        });

        // Initialize carousel
        document.addEventListener('DOMContentLoaded', () => {
            showSlide(slideIndex);
        });
    </script>

    <script src="script.js"></script>
</body>
</html>