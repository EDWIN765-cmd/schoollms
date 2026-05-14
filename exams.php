<?php
require_once 'config.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student') {
    header("Location: index.html");
    exit();
}

$user_id = $_SESSION['user_id'];
$message = '';

// Handle exam submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_exam'])) {
    $exam_id = $_POST['exam_id'];
    $answers = $_POST['answers'] ?? [];
    
    // Get correct answers
    $stmt = $pdo->prepare("SELECT * FROM questions WHERE exam_id = ?");
    $stmt->execute([$exam_id]);
    $questions = $stmt->fetchAll();
    
    $score = 0;
    foreach ($questions as $index => $question) {
        if (isset($answers[$question['id']]) && $answers[$question['id']] == $question['correct_option']) {
            $score++;
        }
    }
    
    $total = count($questions);
    $percentage = ($score / $total) * 100;
    
    // Save result
    $stmt = $pdo->prepare("INSERT INTO exam_results (student_id, exam_id, score, total_questions, percentage) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, $exam_id, $score, $total, $percentage]);
    
    $message = "<div class='alert success'>Exam submitted! Score: $score/$total ($percentage%)</div>";
}

// Get available exams
$stmt = $pdo->prepare("
    SELECT DISTINCT e.*, c.course_name 
    FROM exams e 
    JOIN courses c ON e.course_id = c.id 
    JOIN enrollments en ON c.id = en.course_id 
    WHERE en.student_id = ?
");
$stmt->execute([$user_id]);
$exams = $stmt->fetchAll();

// Get taken exams
$stmt = $pdo->prepare("SELECT exam_id FROM exam_results WHERE student_id = ?");
$stmt->execute([$user_id]);
$taken_exams = $stmt->fetchAll(PDO::FETCH_COLUMN);

$selected_exam = null;
$questions = [];
if (isset($_GET['take']) && in_array($_GET['take'], array_column($exams, 'id'))) {
    $selected_exam_id = $_GET['take'];
    $stmt = $pdo->prepare("SELECT * FROM exams WHERE id = ?");
    $stmt->execute([$selected_exam_id]);
    $selected_exam = $stmt->fetch();
    
    $stmt = $pdo->prepare("SELECT * FROM questions WHERE exam_id = ?");
    $stmt->execute([$selected_exam_id]);
    $questions = $stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Online Exams - School LMS</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <div class="logo">🎓 School LMS</div>
            <div class="nav-links">
                <a href="dashboard.php">Dashboard</a>
                <a href="course-management.php">Courses</a>
                <a href="communication.php">Communication</a>
                <a href="analytics.php">Analytics</a>
                <a href="exams.php">Exams</a>
                <a href="assignments.php">Assignments</a>
                <a href="announcements.php">Announcements</a>
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
        <h1>📝 Online Examinations</h1>
        
        <?php echo $message; ?>
        
        <?php if ($selected_exam && !in_array($selected_exam['id'], $taken_exams)): ?>
            <div class="exam-container">
                <div class="exam-header">
                    <h2><?php echo htmlspecialchars($selected_exam['exam_title']); ?></h2>
                    <p>Duration: <?php echo $selected_exam['duration_minutes']; ?> minutes</p>
                    <p>Total Questions: <?php echo count($questions); ?></p>
                </div>
                
                <form method="POST" id="examForm">
                    <input type="hidden" name="exam_id" value="<?php echo $selected_exam['id']; ?>">
                    <input type="hidden" name="submit_exam" value="1">
                    
                    <?php foreach($questions as $index => $q): ?>
                    <div class="question-card">
                        <h3>Question <?php echo $index + 1; ?>: <?php echo htmlspecialchars($q['question_text']); ?></h3>
                        <div class="options">
                            <label><input type="radio" name="answers[<?php echo $q['id']; ?>]" value="A" required> A) <?php echo htmlspecialchars($q['option_a']); ?></label>
                            <label><input type="radio" name="answers[<?php echo $q['id']; ?>]" value="B"> B) <?php echo htmlspecialchars($q['option_b']); ?></label>
                            <label><input type="radio" name="answers[<?php echo $q['id']; ?>]" value="C"> C) <?php echo htmlspecialchars($q['option_c']); ?></label>
                            <label><input type="radio" name="answers[<?php echo $q['id']; ?>]" value="D"> D) <?php echo htmlspecialchars($q['option_d']); ?></label>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    
                    <button type="submit" class="btn-submit" onclick="return confirm('Submit your exam?')">Submit Exam</button>
                </form>
            </div>
        <?php else: ?>
            <div class="exams-grid">
                <?php foreach($exams as $exam): ?>
                <div class="exam-card">
                    <h3><?php echo htmlspecialchars($exam['exam_title']); ?></h3>
                    <p><?php echo htmlspecialchars($exam['course_name']); ?></p>
                    <p>⏱️ <?php echo $exam['duration_minutes']; ?> mins | 📝 <?php echo $exam['total_questions']; ?> questions</p>
                    <?php if(in_array($exam['id'], $taken_exams)): ?>
                        <button class="btn-disabled" disabled>✓ Completed</button>
                    <?php else: ?>
                        <a href="?take=<?php echo $exam['id']; ?>" class="btn-start">Start Exam →</a>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <script src="script.js"></script>
</body>
</html>