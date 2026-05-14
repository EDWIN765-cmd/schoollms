-- Create database
CREATE DATABASE IF NOT EXISTS school_lms;
USE school_lms;

-- Users table (students and admins)
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    role ENUM('student', 'admin') DEFAULT 'student',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Pending registrations (awaiting admin approval)
CREATE TABLE pending_registrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    password VARCHAR(255) NOT NULL,
    verification_token VARCHAR(255),
    status ENUM('pending', 'verified', 'rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_at TIMESTAMP NULL,
    reviewed_by INT NULL,
    rejection_reason TEXT,
    FOREIGN KEY (reviewed_by) REFERENCES users(id)
);

-- Courses table
CREATE TABLE courses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_code VARCHAR(20) UNIQUE NOT NULL,
    course_name VARCHAR(100) NOT NULL,
    description TEXT,
    instructor VARCHAR(100),
    credits INT DEFAULT 3
);

-- Enrollments
CREATE TABLE enrollments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT,
    course_id INT,
    enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES users(id),
    FOREIGN KEY (course_id) REFERENCES courses(id)
);

-- Assignments table
CREATE TABLE assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT,
    student_id INT,
    title VARCHAR(150) NOT NULL,
    description TEXT,
    due_date DATETIME,
    status ENUM('pending', 'submitted', 'graded') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id),
    FOREIGN KEY (student_id) REFERENCES users(id)
);

-- Announcements table
CREATE TABLE announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Exams table
CREATE TABLE exams (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT,
    exam_title VARCHAR(100) NOT NULL,
    total_questions INT DEFAULT 10,
    duration_minutes INT DEFAULT 30,
    FOREIGN KEY (course_id) REFERENCES courses(id)
);

-- Questions table
CREATE TABLE questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    exam_id INT,
    question_text TEXT NOT NULL,
    option_a VARCHAR(255),
    option_b VARCHAR(255),
    option_c VARCHAR(255),
    option_d VARCHAR(255),
    correct_option CHAR(1),
    FOREIGN KEY (exam_id) REFERENCES exams(id)
);

-- Exam results
CREATE TABLE exam_results (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT,
    exam_id INT,
    score INT,
    total_questions INT,
    percentage DECIMAL(5,2),
    taken_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES users(id),
    FOREIGN KEY (exam_id) REFERENCES exams(id)
);

-- AI learning history
CREATE TABLE ai_learning_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    topic VARCHAR(100),
    interaction TEXT,
    response TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Course modules and lessons
CREATE TABLE course_modules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT,
    module_title VARCHAR(150),
    module_description TEXT,
    position INT DEFAULT 0,
    FOREIGN KEY (course_id) REFERENCES courses(id)
);

-- Study materials and resource library
CREATE TABLE study_materials (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT,
    title VARCHAR(255) NOT NULL,
    material_type ENUM('video', 'pdf', 'slides', 'quiz', 'document') DEFAULT 'document',
    resource_url VARCHAR(255),
    uploaded_by INT,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id),
    FOREIGN KEY (uploaded_by) REFERENCES users(id)
);

-- Discussion forum
CREATE TABLE forum_threads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT,
    title VARCHAR(255) NOT NULL,
    started_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id),
    FOREIGN KEY (started_by) REFERENCES users(id)
);

CREATE TABLE forum_posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    thread_id INT,
    user_id INT,
    content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (thread_id) REFERENCES forum_threads(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Group projects and collaboration
CREATE TABLE group_projects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT,
    project_title VARCHAR(255) NOT NULL,
    description TEXT,
    status ENUM('planning', 'active', 'completed') DEFAULT 'planning',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id)
);

CREATE TABLE project_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT,
    user_id INT,
    role VARCHAR(80),
    FOREIGN KEY (project_id) REFERENCES group_projects(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Attendance and progress tracking
CREATE TABLE attendance_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT,
    course_id INT,
    attended_at DATE,
    status ENUM('present', 'absent', 'late') DEFAULT 'present',
    FOREIGN KEY (student_id) REFERENCES users(id),
    FOREIGN KEY (course_id) REFERENCES courses(id)
);

-- Assignments and rubric grading
CREATE TABLE assignment_submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    assignment_id INT,
    student_id INT,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    grade DECIMAL(5,2),
    feedback TEXT,
    rubric_score DECIMAL(5,2),
    FOREIGN KEY (assignment_id) REFERENCES assignments(id),
    FOREIGN KEY (student_id) REFERENCES users(id)
);

-- Feedback and evaluation
CREATE TABLE feedback_surveys (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT,
    title VARCHAR(255),
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id)
);

CREATE TABLE survey_responses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    survey_id INT,
    user_id INT,
    response TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (survey_id) REFERENCES feedback_surveys(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Insert sample data
INSERT INTO users (username, password, full_name, email, role) VALUES
('admin', MD5('admin123'), 'System Admin', 'admin@school.com', 'admin'),
('student1', MD5('student123'), 'John Doe', 'john@student.com', 'student');

INSERT INTO courses (course_code, course_name, description, instructor, credits) VALUES
('CS101', 'Introduction to Computer Science', 'Learn basics of programming and computing', 'Prof. Smith', 3),
('MATH201', 'Calculus I', 'Limits, derivatives, and integrals', 'Dr. Johnson', 4),
('ENG101', 'English Composition', 'Academic writing and literature', 'Prof. Williams', 3);

INSERT INTO enrollments (student_id, course_id) VALUES (2, 1), (2, 2);

INSERT INTO assignments (course_id, student_id, title, description, due_date, status) VALUES
(1, 2, 'Intro to Programming', 'Complete the first programming assignment using Python.', '2026-05-15 23:59:00', 'pending'),
(2, 2, 'Calculus Review', 'Submit your solutions for the first derivative worksheet.', '2026-05-18 23:59:00', 'pending');

INSERT INTO announcements (title, content, created_by) VALUES
('Welcome to the course!', 'We have updated the course materials and introduced new discussion forums.', 1),
('Live review session', 'Join the live Zoom session this Friday for exam prep and Q&A.', 1);

INSERT INTO exams (course_id, exam_title, total_questions, duration_minutes) VALUES
(1, 'CS101 Midterm Exam', 3, 30),
(2, 'Calculus I Quiz 1', 3, 20);

INSERT INTO questions (exam_id, question_text, option_a, option_b, option_c, option_d, correct_option) VALUES
(1, 'What does CPU stand for?', 'Computer Processing Unit', 'Central Processing Unit', 'Central Program Unit', 'Computer Program Unit', 'B'),
(1, 'Which of these is a programming language?', 'HTML', 'CSS', 'Python', 'HTTP', 'C'),
(1, 'What does RAM stand for?', 'Readily Accessed Memory', 'Random Access Memory', 'Read Access Memory', 'Run Access Memory', 'B'),
(2, 'What is the derivative of x²?', 'x', '2x', 'x²', '2', 'B'),
(2, 'What is the limit of 1/x as x approaches infinity?', '0', '1', 'Infinity', 'Undefined', 'A'),
(2, 'What is the integral of 2x dx?', 'x² + C', '2x² + C', 'x²', '2 + C', 'A');

-- Sample course modules and materials
INSERT INTO course_modules (course_id, module_title, module_description, position) VALUES
(1, 'Getting Started with Programming', 'Introduction to programming fundamentals, syntax, and logic.', 1),
(1, 'Variables and Control Flow', 'Learn about variables, conditions, and loops in programming.', 2),
(2, 'Limits and Continuity', 'Foundations of calculus including limits and continuity.', 1);

INSERT INTO study_materials (course_id, title, material_type, resource_url, uploaded_by) VALUES
(1, 'Intro to Python', 'video', 'https://example.com/python-intro', 1),
(1, 'Programming Basics Slides', 'slides', 'https://example.com/programming-slides', 1),
(2, 'Calculus I Lecture Notes', 'pdf', 'https://example.com/calculus-notes', 1);

INSERT INTO forum_threads (course_id, title, started_by) VALUES
(1, 'Clarification on assignment requirements', 2),
(2, 'Exam preparation strategy', 2);

INSERT INTO forum_posts (thread_id, user_id, content) VALUES
(1, 2, 'Can someone explain the second task for the assignment?'),
(1, 1, 'I suggest reviewing the module on loops and conditionals first.'),
(2, 2, 'What topics should I focus on for the quiz?');

INSERT INTO group_projects (course_id, project_title, description, status) VALUES
(1, 'AI Chatbot Prototype', 'Build a simple chatbot using Python and web APIs.', 'active'),
(2, 'Calculus Study Group', 'Collaborate on practice problems and exam review.', 'planning');

INSERT INTO project_members (project_id, user_id, role) VALUES
(1, 2, 'Student Member'),
(1, 1, 'Instructor Mentor');

INSERT INTO attendance_records (student_id, course_id, attended_at, status) VALUES
(2, 1, '2026-05-01', 'present'),
(2, 1, '2026-05-02', 'late'),
(2, 2, '2026-05-01', 'present');

INSERT INTO assignment_submissions (assignment_id, student_id, grade, feedback, rubric_score) VALUES
(1, 2, 88.50, 'Good progress; check formatting and comments.', 8.5);

INSERT INTO feedback_surveys (course_id, title, description) VALUES
(1, 'Mid-course Feedback', 'Share your thoughts on course pace, materials, and support.');

INSERT INTO survey_responses (survey_id, user_id, response) VALUES
(1, 2, 'The course is well-paced, but I would like more video explanations.');