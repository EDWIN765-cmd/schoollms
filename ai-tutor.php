<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.html");
    exit();
}

$user_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];
$ai_response = '';
$question = '';
$error = '';

// OpenAI API Configuration
if (!defined('OPENAI_API_KEY')) {
    define('OPENAI_API_KEY', '');
}
define('OPENAI_API_URL', 'https://api.openai.com/v1/chat/completions');

// Function to call OpenAI API
function getOpenAIResponse($question, $user_name) {
    $api_key = OPENAI_API_KEY;
    
    // Prepare the prompt with context
    $system_prompt = "You are an AI learning assistant for a school LMS. Your name is EduBot. 
    You help students with their studies, explain concepts, provide study tips, and answer academic questions.
    The student's name is " . $user_name . ". Be friendly, encouraging, and educational.
    Keep responses concise but informative (2-4 sentences for simple questions, up to 8 sentences for complex ones).";
    
    $user_prompt = "Student question: " . $question;
    
    // Prepare request data
    $data = [
        'model' => 'gpt-3.5-turbo', // or 'gpt-4' for better responses
        'messages' => [
            ['role' => 'system', 'content' => $system_prompt],
            ['role' => 'user', 'content' => $user_prompt]
        ],
        'temperature' => 0.7,
        'max_tokens' => 500,
        'top_p' => 1.0,
        'frequency_penalty' => 0.5,
        'presence_penalty' => 0.5
    ];
    
    // Initialize cURL
    $ch = curl_init(OPENAI_API_URL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $api_key
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Only for local development
    
    // Execute request
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    // Handle errors
    if ($curl_error) {
        return "I'm having trouble connecting right now. Please try again later. (Error: " . $curl_error . ")";
    }
    
    if ($http_code !== 200) {
        $error_response = json_decode($response, true);
        $error_msg = isset($error_response['error']['message']) ? $error_response['error']['message'] : 'Unknown error';
        return "API Error: " . $error_msg . ". Please check your API key or try again later.";
    }
    
    // Parse response
    $result = json_decode($response, true);
    if (isset($result['choices'][0]['message']['content'])) {
        return trim($result['choices'][0]['message']['content']);
    }
    
    return "I couldn't generate a response. Please try asking differently.";
}

// Alternative: Use file_get_contents (if allow_url_fopen is enabled)
function getOpenAIResponseAlt($question, $user_name) {
    $api_key = OPENAI_API_KEY;
    
    $system_prompt = "You are an AI learning assistant for a school LMS. Help students with their studies.";
    
    $post_data = json_encode([
        'model' => 'gpt-3.5-turbo',
        'messages' => [
            ['role' => 'system', 'content' => $system_prompt],
            ['role' => 'user', 'content' => $question]
        ],
        'temperature' => 0.7,
        'max_tokens' => 500
    ]);
    
    $options = [
        'http' => [
            'header' => "Content-Type: application/json\r\n" .
                        "Authorization: Bearer " . $api_key . "\r\n",
            'method' => 'POST',
            'content' => $post_data,
            'timeout' => 30
        ]
    ];
    
    $context = stream_context_create($options);
    $response = @file_get_contents(OPENAI_API_URL, false, $context);
    
    if ($response === false) {
        return "Unable to reach AI service. Please try again later.";
    }
    
    $result = json_decode($response, true);
    if (isset($result['choices'][0]['message']['content'])) {
        return $result['choices'][0]['message']['content'];
    }
    
    return "I couldn't process your question. Please try again.";
}

// Process AI request
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['ask_ai'])) {
    $question = trim($_POST['question']);
    if ($question) {
        // Check if API key is configured
        if (empty(OPENAI_API_KEY)) {
            $ai_response = "⚠️ OpenAI API key not configured. Please add your API key in your .env file or set the OPENAI_API_KEY environment variable.";
        } else {
            try {
                $ai_response = getOpenAIResponse($question, $full_name);
            } catch (Exception $e) {
                $ai_response = "Sorry, I encountered an error: " . $e->getMessage() .
                              " Please try again or contact support.";
            }
        }

        // Save to history (even if API fails, we save the attempt)
        try {
            $stmt = $pdo->prepare("INSERT INTO ai_learning_history (user_id, topic, interaction, response) VALUES (?, ?, ?, ?)");
            $stmt->execute([$user_id, substr($question, 0, 100), $question, $ai_response]);
        } catch (PDOException $e) {
            // Silently fail if history can't be saved
            error_log("Failed to save AI history: " . $e->getMessage());
        }
    }
}

// Get learning history with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$stmt = $pdo->prepare("SELECT * FROM ai_learning_history WHERE user_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?");
$stmt->bindParam(1, $user_id, PDO::PARAM_INT);
$stmt->bindParam(2, $limit, PDO::PARAM_INT);
$stmt->bindParam(3, $offset, PDO::PARAM_INT);
$stmt->execute();
$history = $stmt->fetchAll();

// Get total count for pagination
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM ai_learning_history WHERE user_id = ?");
$stmt->execute([$user_id]);
$total_history = $stmt->fetch()['total'];
$total_pages = ceil($total_history / $limit);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>AI Learning Assistant - School LMS</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .ai-response {
            background: linear-gradient(135deg, #667eea15 0%, #764ba215 100%);
            border-left: 4px solid #667eea;
            padding: 15px;
            margin: 10px 0;
            border-radius: 10px;
        }
        
        .typing-indicator {
            display: inline-block;
            padding: 10px 20px;
            background: #f0f0f0;
            border-radius: 20px;
            margin: 10px 0;
        }
        
        .typing-indicator span {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #667eea;
            margin: 0 2px;
            animation: typing 1.4s infinite;
        }
        
        .typing-indicator span:nth-child(2) {
            animation-delay: 0.2s;
        }
        
        .typing-indicator span:nth-child(3) {
            animation-delay: 0.4s;
        }
        
        @keyframes typing {
            0%, 60%, 100% {
                transform: translateY(0);
                opacity: 0.4;
            }
            30% {
                transform: translateY(-10px);
                opacity: 1;
            }
        }
        
        .api-status {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            margin-left: 10px;
        }
        
        .api-status.active {
            background: #4caf50;
            color: white;
        }
        
        .api-status.inactive {
            background: #f44336;
            color: white;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 20px;
        }
        
        .pagination a {
            padding: 8px 12px;
            background: #f0f0f0;
            text-decoration: none;
            color: #333;
            border-radius: 5px;
        }
        
        .pagination a.active {
            background: #667eea;
            color: white;
        }
        
        @media (max-width: 768px) {
            .ai-container {
                grid-template-columns: 1fr;
            }
        }
    </style>
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
        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap;">
            <h1>🤖 AI Learning Assistant</h1>
            <div>
                <?php if(!empty(OPENAI_API_KEY)): ?>
                    <span class="api-status active">✓ OpenAI Connected</span>
                <?php else: ?>
                    <span class="api-status inactive">⚠ API Key Required</span>
                <?php endif; ?>
            </div>
        </div>
        <p class="subtitle">Powered by OpenAI GPT - Your 24/7 Intelligent Tutoring System</p>
        
        <div class="ai-container">
            <div class="ai-chat-area">
                <div class="chat-messages" id="chatMessages">
                    <?php if($ai_response): ?>
                        <div class="message user">
                            <strong>👤 You:</strong>
                            <div><?php echo htmlspecialchars($question); ?></div>
                        </div>
                        <div class="message ai">
                            <strong>🤖 AI Tutor:</strong>
                            <div class="ai-response"><?php echo nl2br(htmlspecialchars($ai_response)); ?></div>
                        </div>
                    <?php else: ?>
                        <div class="message ai">
                            <strong>🤖 AI Tutor:</strong>
                            <div>Hello <?php echo htmlspecialchars($full_name); ?>! 👋 I'm your AI learning assistant. Ask me anything about your studies, and I'll help you learn better!</div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <form method="POST" class="ai-input-form" id="aiForm">
                    <input type="text" name="question" id="questionInput" 
                           placeholder="Ask me anything about programming, math, science, or any subject..." 
                           required autocomplete="off">
                    <button type="submit" name="ask_ai" id="askButton">Ask AI →</button>
                </form>
                
                <div class="suggested-questions">
                    <p>💡 Suggested questions to ask:</p>
                    <button type="button" onclick="setQuestion('What is object-oriented programming?')">What is OOP?</button>
                    <button type="button" onclick="setQuestion('Explain quantum computing simply')">Quantum computing</button>
                    <button type="button" onclick="setQuestion('How to prepare for exams effectively?')">Exam preparation</button>
                    <button type="button" onclick="setQuestion('What is machine learning?')">Machine learning</button>
                    <button type="button" onclick="setQuestion('Tips for learning a new language')">Learning tips</button>
                    <button type="button" onclick="setQuestion('Explain the water cycle')">Water cycle</button>
                </div>
                
                <div class="info-note">
                    <small>💡 <strong>Pro tip:</strong> Ask detailed questions for better answers! The AI remembers context within each conversation.</small>
                </div>
            </div>
            
            <div class="ai-history">
                <h3>📜 Learning History</h3>
                <div class="history-list">
                    <?php if(count($history) > 0): ?>
                        <?php foreach($history as $item): ?>
                        <div class="history-item" onclick="loadQuestion('<?php echo htmlspecialchars(addslashes($item['interaction'])); ?>')">
                            <div class="history-question">❓ <?php echo htmlspecialchars(substr($item['interaction'], 0, 60)); ?>...</div>
                            <div class="history-date"><?php echo date('M d, H:i', strtotime($item['created_at'])); ?></div>
                        </div>
                        <?php endforeach; ?>
                        
                        <?php if($total_pages > 1): ?>
                        <div class="pagination">
                            <?php if($page > 1): ?>
                                <a href="?page=<?php echo $page-1; ?>">← Previous</a>
                            <?php endif; ?>
                            <span>Page <?php echo $page; ?> of <?php echo $total_pages; ?></span>
                            <?php if($page < $total_pages): ?>
                                <a href="?page=<?php echo $page+1; ?>">Next →</a>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        
                    <?php else: ?>
                        <div class="history-item">
                            <div class="history-question">No questions asked yet. Start a conversation above!</div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    // Auto-scroll to bottom of chat
    function scrollToBottom() {
        const chatMessages = document.getElementById('chatMessages');
        if (chatMessages) {
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }
    }
    
    // Set suggested question
    function setQuestion(q) {
        const input = document.querySelector('input[name="question"]');
        if (input) {
            input.value = q;
            input.focus();
        }
    }
    
    // Load previous question
    function loadQuestion(q) {
        const input = document.querySelector('input[name="question"]');
        if (input) {
            input.value = q;
            input.focus();
            // Optional: auto-submit
            // document.getElementById('aiForm').submit();
        }
    }
    
    // Show loading state on form submit
    document.getElementById('aiForm')?.addEventListener('submit', function(e) {
        const button = document.getElementById('askButton');
        const originalText = button.textContent;
        button.disabled = true;
        button.textContent = '🤔 Thinking...';
        
        // Add typing indicator
        const chatMessages = document.getElementById('chatMessages');
        const typingDiv = document.createElement('div');
        typingDiv.className = 'message ai';
        typingDiv.id = 'typingIndicator';
        typingDiv.innerHTML = '<strong>🤖 AI Tutor:</strong><div class="typing-indicator"><span></span><span></span><span></span></div>';
        chatMessages.appendChild(typingDiv);
        scrollToBottom();
        
        // Re-enable button after form submission (server will handle the rest)
        setTimeout(() => {
            button.disabled = false;
            button.textContent = originalText;
        }, 1000);
    });
    
    // Scroll to bottom on page load
    document.addEventListener('DOMContentLoaded', function() {
        scrollToBottom();
        
        // Remove typing indicator after response loads (if any)
        const typingIndicator = document.getElementById('typingIndicator');
        if (typingIndicator) {
            setTimeout(() => {
                typingIndicator.remove();
            }, 500);
        }
    });
    
    // Mobile menu toggle
    function toggleMobileMenu() {
        const navLinks = document.querySelector('.nav-links');
        if (navLinks) {
            navLinks.classList.toggle('active');
        }
    }
    </script>
</body>
</html>