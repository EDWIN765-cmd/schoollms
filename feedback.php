<?php
require_once 'config.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: index.html");
    exit();
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$full_name = $_SESSION['full_name'];
$success = '';
$error = '';

// Handle survey creation (admin only)
if ($role == 'admin' && $_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_survey'])) {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verifyCSRFToken($csrf_token)) {
        $error = 'Security validation failed.';
    } else {
        $course_id = (int)$_POST['course_id'];
        $title = sanitizeInput($_POST['title'] ?? '');
        $description = sanitizeInput($_POST['description'] ?? '');

        if ($title && $course_id) {
            try {
                $stmt = $pdo->prepare("INSERT INTO feedback_surveys (course_id, title, description) VALUES (?, ?, ?)");
                $stmt->execute([$course_id, $title, $description]);
                $success = 'Survey created successfully!';
            } catch (PDOException $e) {
                $error = 'Failed to create survey.';
            }
        } else {
            $error = 'Please fill in required fields.';
        }
    }
} elseif ($role == 'student' && $_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_response'])) {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verifyCSRFToken($csrf_token)) {
        $error = 'Security validation failed.';
    } else {
        $survey_id = (int)$_POST['survey_id'];
        $response = sanitizeInput($_POST['response'] ?? '');

        if ($response) {
            try {
                $stmt = $pdo->prepare("INSERT INTO survey_responses (survey_id, user_id, response) VALUES (?, ?, ?)");
                $stmt->execute([$survey_id, $user_id, $response]);
                $success = 'Thank you for your feedback!';
            } catch (PDOException $e) {
                $error = 'Failed to submit response.';
            }
        } else {
            $error = 'Please provide feedback.';
        }
    }
}

// Get surveys
if ($role == 'admin') {
    $stmt = $pdo->query("
        SELECT fs.*, c.course_name, COUNT(sr.id) as response_count
        FROM feedback_surveys fs
        JOIN courses c ON fs.course_id = c.id
        LEFT JOIN survey_responses sr ON fs.id = sr.survey_id
        GROUP BY fs.id
        ORDER BY fs.created_at DESC
    ");
} else {
    $stmt = $pdo->prepare("
        SELECT fs.*, c.course_name, COUNT(sr.id) as response_count
        FROM feedback_surveys fs
        JOIN courses c ON fs.course_id = c.id
        JOIN enrollments e ON c.id = e.course_id
        LEFT JOIN survey_responses sr ON fs.id = sr.survey_id
        WHERE e.student_id = ?
        GROUP BY fs.id
        ORDER BY fs.created_at DESC
    ");
    $stmt->execute([$user_id]);
}
$surveys = $stmt->fetchAll();

// Check if student has already responded to surveys
$student_responses = [];
if ($role == 'student') {
    $stmt = $pdo->prepare("SELECT survey_id FROM survey_responses WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $student_responses = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// Get courses for survey creation
$courses = $pdo->query("SELECT * FROM courses ORDER BY course_name")->fetchAll();

$csrf_token = generateCSRFToken();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feedback & Surveys - School LMS</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <div class="logo">📝 Feedback & Surveys</div>
            <div class="nav-links">
                <a href="dashboard.php">Dashboard</a>
                <a href="course-management.php">Courses</a>
                <a href="communication.php">Communication</a>
                <a href="analytics.php">Analytics</a>
                <a href="exams.php">Exams</a>
                <a href="assignments.php">Assignments</a>
                <a href="announcements.php">Announcements</a>
                <a href="feedback.php">Feedback</a>
                <?php if ($role == 'admin'): ?>
                <a href="admin.php">Admin</a>
                <a href="user-management.php">Users</a>
                <a href="enrollment.php">Enrollment</a>
                <a href="attendance.php">Attendance</a>
                <?php endif; ?>
                <a href="profile.php">Profile</a>
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
        <h1>📝 Feedback & Surveys</h1>
        <p class="lead">
            <?php echo $role == 'admin' ? 'Create and manage course surveys.' : 'Share your feedback about courses.'; ?>
        </p>

        <?php if ($success): ?>
        <div class="alert success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="alert danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($role == 'admin'): ?>
        <!-- Admin: Create Survey -->
        <div class="card" style="margin-bottom: 30px;">
            <h2>➕ Create New Survey</h2>
            <form method="POST">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Course *</label>
                        <select name="course_id" required>
                            <option value="">Select Course</option>
                            <?php foreach ($courses as $course): ?>
                            <option value="<?php echo $course['id']; ?>"><?php echo htmlspecialchars($course['course_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label>Survey Title *</label>
                        <input type="text" name="title" placeholder="e.g., Mid-course Evaluation" required>
                    </div>

                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label>Description</label>
                        <textarea name="description" placeholder="Add survey instructions or context..." style="resize: vertical; min-height: 100px;"></textarea>
                    </div>

                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    <input type="hidden" name="create_survey" value="1">
                    <button type="submit" class="btn-primary">Create Survey</button>
                </div>
            </form>
        </div>
        <?php endif; ?>

        <!-- Surveys List -->
        <div class="surveys-container">
            <?php foreach ($surveys as $survey): ?>
            <div class="card survey-card">
                <div class="survey-header">
                    <h3><?php echo htmlspecialchars($survey['title']); ?></h3>
                    <span class="survey-count"><?php echo $survey['response_count']; ?> responses</span>
                </div>
                <p class="survey-course">📚 <?php echo htmlspecialchars($survey['course_name']); ?></p>
                
                <?php if ($survey['description']): ?>
                <p><?php echo htmlspecialchars($survey['description']); ?></p>
                <?php endif; ?>

                <?php if ($role == 'student'): ?>
                    <?php if (in_array($survey['id'], $student_responses)): ?>
                    <div style="padding: 12px; background: #c6f6d5; border-radius: 6px; color: #22543d; font-weight: 600;">
                        ✓ You have already submitted your feedback for this survey
                    </div>
                    <?php else: ?>
                    <button onclick="respondModal(<?php echo $survey['id']; ?>, '<?php echo htmlspecialchars($survey['title']); ?>')" class="btn-primary">Provide Feedback</button>
                    <?php endif; ?>
                <?php else: ?>
                <button onclick="viewResponses(<?php echo $survey['id']; ?>, '<?php echo htmlspecialchars($survey['title']); ?>')" class="btn-secondary">View Responses</button>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>

            <?php if (empty($surveys)): ?>
            <div class="card">
                <p style="text-align: center; color: var(--gray);">
                    <?php echo $role == 'admin' ? 'No surveys created yet.' : 'No surveys available for your courses.'; ?>
                </p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Response Modal -->
    <div id="responseModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('responseModal')">&times;</span>
            <h2>Provide Feedback</h2>
            <form method="POST">
                <input type="hidden" name="survey_id" id="survey_id">
                <textarea name="response" placeholder="Share your feedback..." required style="resize: vertical; min-height: 150px;"></textarea>
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="submit_response" value="1">
                <button type="submit" class="btn-primary">Submit Feedback</button>
            </form>
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
        .form-group select,
        .form-group textarea {
            padding: 12px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .surveys-container {
            display: grid;
            gap: 20px;
            margin-top: 20px;
        }

        .survey-card {
            border-left: 4px solid var(--primary);
        }

        .survey-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }

        .survey-header h3 {
            margin: 0;
            color: var(--dark);
        }

        .survey-count {
            background: #edf2f7;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            color: var(--dark);
        }

        .survey-course {
            color: var(--gray);
            margin: 5px 0;
            font-size: 14px;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.4);
        }

        .modal-content {
            background-color: #fefefe;
            margin: 10% auto;
            padding: 30px;
            border-radius: 12px;
            width: 90%;
            max-width: 600px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        .close:hover {
            color: black;
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
            width: 100%;
            margin-top: 15px;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
        }

        .btn-secondary {
            background: var(--secondary);
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            transition: background 0.3s;
        }

        .btn-secondary:hover {
            background: #6a3d8d;
        }
    </style>

    <script>
        function respondModal(surveyId, title) {
            document.getElementById('survey_id').value = surveyId;
            document.getElementById('responseModal').style.display = 'block';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        window.onclick = function(event) {
            const modal = document.getElementById('responseModal');
            if (event.target == modal) modal.style.display = 'none';
        }
    </script>

    <script src="script.js"></script>
</body>
</html>
