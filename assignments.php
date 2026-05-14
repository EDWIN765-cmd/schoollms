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

// Handle assignment creation (admin only)
if ($role == 'admin' && $_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_assignment'])) {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verifyCSRFToken($csrf_token)) {
        $error = 'Security validation failed.';
    } else {
        $course_id = (int)$_POST['course_id'];
        $student_id = (int)$_POST['student_id'] ?? null;
        $title = sanitizeInput($_POST['title'] ?? '');
        $description = sanitizeInput($_POST['description'] ?? '');
        $due_date = $_POST['due_date'] ?? '';

        if ($title && $due_date && $course_id) {
            try {
                $stmt = $pdo->prepare("INSERT INTO assignments (course_id, student_id, title, description, due_date, status) VALUES (?, ?, ?, ?, ?, 'pending')");
                $stmt->execute([$course_id, $student_id, $title, $description, $due_date]);
                $success = 'Assignment created successfully!';
            } catch (PDOException $e) {
                $error = 'Failed to create assignment.';
            }
        } else {
            $error = 'Please fill in all required fields.';
        }
    }
}

// Handle submission (student only)
if ($role == 'student' && $_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_assignment'])) {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verifyCSRFToken($csrf_token)) {
        $error = 'Security validation failed.';
    } else {
        $assignment_id = (int)$_POST['assignment_id'];
        $notes = sanitizeInput($_POST['notes'] ?? '');

        try {
            $stmt = $pdo->prepare("INSERT INTO assignment_submissions (assignment_id, student_id, feedback) VALUES (?, ?, ?)");
            $stmt->execute([$assignment_id, $user_id, $notes]);
            
            // Update assignment status
            $stmt = $pdo->prepare("UPDATE assignments SET status = 'submitted' WHERE id = ?");
            $stmt->execute([$assignment_id]);
            
            $success = 'Assignment submitted successfully!';
        } catch (PDOException $e) {
            $error = 'Failed to submit assignment.';
        }
    }
}

// Handle grading (admin only)
if ($role == 'admin' && $_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['grade_assignment'])) {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verifyCSRFToken($csrf_token)) {
        $error = 'Security validation failed.';
    } else {
        $submission_id = (int)$_POST['submission_id'];
        $grade = (float)$_POST['grade'];
        $feedback = sanitizeInput($_POST['feedback'] ?? '');

        if ($grade >= 0 && $grade <= 100) {
            try {
                $stmt = $pdo->prepare("UPDATE assignment_submissions SET grade = ?, rubric_score = ?, feedback = ? WHERE id = ?");
                $stmt->execute([$grade, $grade, $feedback, $submission_id]);
                
                // Update assignment status
                $stmt = $pdo->prepare("SELECT assignment_id FROM assignment_submissions WHERE id = ?");
                $stmt->execute([$submission_id]);
                $assign_id = $stmt->fetch()['assignment_id'];
                
                $stmt = $pdo->prepare("UPDATE assignments SET status = 'graded' WHERE id = ?");
                $stmt->execute([$assign_id]);
                
                $success = 'Grade recorded successfully!';
            } catch (PDOException $e) {
                $error = 'Failed to record grade.';
            }
        } else {
            $error = 'Grade must be between 0 and 100.';
        }
    }
}

// Get assignments
if ($role == 'admin') {
    $stmt = $pdo->query("
        SELECT a.*, c.course_name, u.full_name as student_name 
        FROM assignments a 
        JOIN courses c ON a.course_id = c.id 
        LEFT JOIN users u ON a.student_id = u.id 
        ORDER BY a.due_date ASC
    ");
} else {
    $stmt = $pdo->prepare("
        SELECT a.*, c.course_name 
        FROM assignments a 
        JOIN courses c ON a.course_id = c.id 
        JOIN enrollments e ON c.id = e.course_id 
        WHERE e.student_id = ? 
        ORDER BY a.due_date ASC
    ");
    $stmt->execute([$user_id]);
}
$assignments = $stmt->fetchAll();

// Get submissions for admin view
$submissions = [];
if ($role == 'admin') {
    $stmt = $pdo->query("
        SELECT asub.*, a.title, u.full_name, u.username, a.due_date
        FROM assignment_submissions asub
        JOIN assignments a ON asub.assignment_id = a.id
        JOIN users u ON asub.student_id = u.id
        ORDER BY asub.submitted_at DESC
    ");
    $submissions = $stmt->fetchAll();
}

// Get courses for assignment creation
$courses = $pdo->query("SELECT * FROM courses ORDER BY course_name")->fetchAll();

// Get students for assignment allocation
$students = $pdo->query("SELECT * FROM users WHERE role = 'student' ORDER BY full_name")->fetchAll();

$csrf_token = generateCSRFToken();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assignments - School LMS</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <div class="logo">📝 Assignments</div>
            <div class="nav-links">
                <a href="dashboard.php">Dashboard</a>
                <a href="course-management.php">Courses</a>
                <a href="communication.php">Communication</a>
                <a href="analytics.php">Analytics</a>
                <a href="exams.php">Exams</a>
                <a href="assignments.php">Assignments</a>
                <a href="announcements.php">Announcements</a>
                <?php if($role == 'admin'): ?>
                <a href="admin.php">Admin</a>
                <a href="user-management.php">Users</a>
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
                    <a href="assignments.php">Assignments</a>
                    <a href="logout.php">Logout</a>
                </div>
            </div>
            <div class="mobile-menu-btn" onclick="toggleMobileMenu()">☰</div>
        </div>
    </nav>

    <div class="container">
        <h1>📝 Assignment Tracker</h1>
        
        <?php if ($success): ?>
        <div class="alert success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
        <div class="alert danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($role == 'admin'): ?>
        <!-- Admin: Create Assignment -->
        <div class="card" style="margin-bottom: 30px;">
            <h2>➕ Create New Assignment</h2>
            <form method="POST" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                <select name="course_id" required>
                    <option value="">Select Course</option>
                    <?php foreach ($courses as $course): ?>
                    <option value="<?php echo $course['id']; ?>"><?php echo htmlspecialchars($course['course_name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="text" name="title" placeholder="Assignment Title" required>
                <textarea name="description" placeholder="Description" style="grid-column: span 2;"></textarea>
                <input type="datetime-local" name="due_date" required>
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="create_assignment" value="1">
                <button type="submit" class="btn-primary">Create Assignment</button>
            </form>
        </div>

        <!-- Admin: View Submissions -->
        <div class="card">
            <h2>📊 Student Submissions</h2>
            <div class="table-container">
                <table class="submissions-table">
                    <thead>
                        <tr>
                            <th>Assignment</th>
                            <th>Student</th>
                            <th>Submitted</th>
                            <th>Grade</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($submissions as $sub): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($sub['title']); ?></td>
                            <td><?php echo htmlspecialchars($sub['full_name']); ?></td>
                            <td><?php echo date('M j, Y', strtotime($sub['submitted_at'])); ?></td>
                            <td>
                                <?php if ($sub['grade'] !== null): ?>
                                <strong><?php echo number_format($sub['grade'], 1); ?>%</strong>
                                <?php else: ?>
                                <span class="text-muted">Not graded</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($sub['grade'] !== null): ?>
                                <span class="badge success">Graded</span>
                                <?php else: ?>
                                <span class="badge warning">Pending</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button onclick="gradeModal(<?php echo $sub['id']; ?>, '<?php echo htmlspecialchars($sub['title']); ?>')" class="btn-small">Grade</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php else: ?>
        <!-- Student: View Assignments -->
        <div class="assignments-list">
            <?php foreach($assignments as $assignment): ?>
            <div class="card assignment-card">
                <div class="assignment-header">
                    <h3><?php echo htmlspecialchars($assignment['title']); ?></h3>
                    <span class="assignment-status <?php echo $assignment['status']; ?>">
                        <?php echo ucfirst($assignment['status']); ?>
                    </span>
                </div>
                <p><?php echo htmlspecialchars($assignment['description']); ?></p>
                <div class="assignment-meta">
                    <p><strong>Course:</strong> <?php echo htmlspecialchars($assignment['course_name']); ?></p>
                    <p><strong>Due:</strong> <?php echo date('F j, Y \a\t g:i A', strtotime($assignment['due_date'])); ?></p>
                </div>
                <?php if($assignment['status'] == 'pending'): ?>
                <button onclick="submitModal(<?php echo $assignment['id']; ?>, '<?php echo htmlspecialchars($assignment['title']); ?>')" class="btn-primary">Submit Assignment</button>
                <?php elseif($assignment['status'] == 'submitted'): ?>
                <p class="text-success">✓ Submitted - Awaiting grading</p>
                <?php else: ?>
                <p class="text-success">✓ Graded</p>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            
            <?php if(empty($assignments)): ?>
            <div class="card">No assignments found.</div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Modals -->
    <div id="submitModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('submitModal')">&times;</span>
            <h2>Submit Assignment</h2>
            <form method="POST">
                <input type="hidden" name="assignment_id" id="assignment_id">
                <textarea name="notes" placeholder="Add submission notes..." required></textarea>
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="submit_assignment" value="1">
                <button type="submit" class="btn-primary">Submit</button>
            </form>
        </div>
    </div>

    <div id="gradeModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('gradeModal')">&times;</span>
            <h2>Grade Submission</h2>
            <form method="POST">
                <input type="hidden" name="submission_id" id="submission_id">
                <div class="form-group">
                    <label>Grade (0-100)</label>
                    <input type="number" name="grade" min="0" max="100" step="0.5" required>
                </div>
                <div class="form-group">
                    <label>Feedback</label>
                    <textarea name="feedback" placeholder="Add feedback for student..."></textarea>
                </div>
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="grade_assignment" value="1">
                <button type="submit" class="btn-primary">Save Grade</button>
            </form>
        </div>
    </div>

    <style>
        .assignments-list {
            display: grid;
            gap: 20px;
            margin-top: 20px;
        }

        .assignment-card {
            border-left: 4px solid var(--primary);
        }

        .assignment-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .assignment-header h3 {
            margin: 0;
            color: var(--dark);
        }

        .assignment-status {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .assignment-status.pending {
            background: #fed7d7;
            color: #c53030;
        }

        .assignment-status.submitted {
            background: #feebc8;
            color: #7c2d12;
        }

        .assignment-status.graded {
            background: #c6f6d5;
            color: #22543d;
        }

        .assignment-meta {
            margin: 15px 0;
            padding: 15px;
            background: #f7fafc;
            border-radius: 8px;
        }

        .assignment-meta p {
            margin: 5px 0;
            font-size: 14px;
        }

        .table-container {
            overflow-x: auto;
            margin-top: 15px;
        }

        .submissions-table {
            width: 100%;
            border-collapse: collapse;
        }

        .submissions-table th {
            background: #f7fafc;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            border-bottom: 2px solid #e2e8f0;
        }

        .submissions-table td {
            padding: 12px;
            border-bottom: 1px solid #e2e8f0;
        }

        .submissions-table tr:hover {
            background: #f7fafc;
        }

        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge.success {
            background: #c6f6d5;
            color: #22543d;
        }

        .badge.warning {
            background: #feebc8;
            color: #7c2d12;
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
            max-width: 500px;
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

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 14px;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 120px;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
        }

        .text-success {
            color: var(--success);
            font-weight: 600;
        }

        .text-muted {
            color: var(--gray);
            font-size: 12px;
        }
    </style>

    <script>
        function submitModal(assignmentId, title) {
            document.getElementById('assignment_id').value = assignmentId;
            document.getElementById('submitModal').style.display = 'block';
        }

        function gradeModal(submissionId, title) {
            document.getElementById('submission_id').value = submissionId;
            document.getElementById('gradeModal').style.display = 'block';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        window.onclick = function(event) {
            const submitModal = document.getElementById('submitModal');
            const gradeModal = document.getElementById('gradeModal');
            if (event.target == submitModal) submitModal.style.display = 'none';
            if (event.target == gradeModal) gradeModal.style.display = 'none';
        }
    </script>

    <script src="script.js"></script>
</body>
</html>