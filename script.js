// Mobile menu toggle
function toggleMobileMenu() {
    const navLinks = document.querySelector('.nav-links');
    if (navLinks) {
        navLinks.classList.toggle('active');
    }
}

function toggleDropdown(id) {
    const dropdown = document.getElementById(id);
    if (!dropdown) return;
    closeAllDropdowns();
    dropdown.classList.toggle('active');
}

function closeAllDropdowns() {
    document.querySelectorAll('.dropdown.active').forEach(dropdown => {
        dropdown.classList.remove('active');
    });
}

// Close mobile menu and dropdowns on click outside
document.addEventListener('click', function(e) {
    const navLinks = document.querySelector('.nav-links');
    const menuBtn = document.querySelector('.mobile-menu-btn');
    const navActions = document.querySelector('.nav-actions');

    if (navLinks && navLinks.classList.contains('active') && menuBtn && !menuBtn.contains(e.target) && !navLinks.contains(e.target)) {
        navLinks.classList.remove('active');
    }

    if (navActions && !navActions.contains(e.target)) {
        closeAllDropdowns();
    }
});

// Show toast notification
function showToast(message, type = 'success') {
    const toast = document.createElement('div');
    toast.className = 'toast';
    toast.style.background = type === 'success' ? '#48bb78' : '#f56565';
    toast.style.color = 'white';
    toast.innerHTML = message;
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.style.opacity = '0';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// Auto-hide alerts after 3 seconds
document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 300);
        }, 3000);
    });
    
    // Add loading state to forms
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn && !submitBtn.disabled) {
                submitBtn.disabled = true;
                const originalText = submitBtn.textContent;
                submitBtn.innerHTML = '<span class="spinner"></span> Processing...';
                
                // Re-enable after timeout (in case of error)
                setTimeout(() => {
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalText;
                }, 10000);
            }
        });
    });
    
    // Add smooth scroll to top button
    addScrollToTop();
    
    // Add live search functionality
    addLiveSearch();
    
    // Add progress tracking animation
    animateProgressBars();
    
    // Add keyboard shortcuts
    addKeyboardShortcuts();
    
    // Add offline detection
    addOfflineDetection();
    
    // Add reading progress for content
    addReadingProgress();
});

// Scroll to top button
function addScrollToTop() {
    const btn = document.createElement('button');
    btn.innerHTML = '↑';
    btn.style.cssText = `
        position: fixed;
        bottom: 20px;
        right: 20px;
        width: 50px;
        height: 50px;
        border-radius: 25px;
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: white;
        border: none;
        cursor: pointer;
        font-size: 24px;
        display: none;
        z-index: 1000;
        box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        transition: all 0.3s;
    `;
    document.body.appendChild(btn);
    
    window.addEventListener('scroll', () => {
        if (window.scrollY > 300) {
            btn.style.display = 'block';
        } else {
            btn.style.display = 'none';
        }
    });
    
    btn.addEventListener('click', () => {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });
    
    btn.addEventListener('mouseenter', () => {
        btn.style.transform = 'translateY(-3px)';
    });
    
    btn.addEventListener('mouseleave', () => {
        btn.style.transform = 'translateY(0)';
    });
}

// Live search functionality
function addLiveSearch() {
    const searchInput = document.createElement('input');
    searchInput.type = 'text';
    searchInput.placeholder = '🔍 Search courses, exams, assignments, announcements...';
    searchInput.style.cssText = `
        width: 100%;
        max-width: 300px;
        padding: 10px 16px;
        border: 2px solid #e2e8f0;
        border-radius: 25px;
        font-size: 14px;
        transition: all 0.3s;
    `;
    
    const navContainer = document.querySelector('.nav-container');
    const mobileBtn = document.querySelector('.mobile-menu-btn');
    if (navContainer && !document.querySelector('.search-input')) {
        searchInput.className = 'search-input';
        if (mobileBtn) {
            navContainer.insertBefore(searchInput, mobileBtn);
        } else {
            navContainer.appendChild(searchInput);
        }
        
        searchInput.addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const courseItems = document.querySelectorAll('.course-item');
            const examCards = document.querySelectorAll('.exam-card');
            const assignmentItems = document.querySelectorAll('.assignment-item');
            const announcementItems = document.querySelectorAll('.announcement-item');
            const historyItems = document.querySelectorAll('.history-item');
            const materialItems = document.querySelectorAll('.material-item');
            const resultItems = document.querySelectorAll('.result-item');

            courseItems.forEach(item => {
                const text = item.textContent.toLowerCase();
                item.style.display = text.includes(searchTerm) ? 'flex' : 'none';
            });

            examCards.forEach(card => {
                const text = card.textContent.toLowerCase();
                card.style.display = text.includes(searchTerm) ? 'block' : 'none';
            });

            assignmentItems.forEach(item => {
                const text = item.textContent.toLowerCase();
                item.style.display = text.includes(searchTerm) ? 'flex' : 'none';
            });

            announcementItems.forEach(item => {
                const text = item.textContent.toLowerCase();
                item.style.display = text.includes(searchTerm) ? 'block' : 'none';
            });

            historyItems.forEach(item => {
                const text = item.textContent.toLowerCase();
                item.style.display = text.includes(searchTerm) ? 'block' : 'none';
            });

            materialItems.forEach(item => {
                const text = item.textContent.toLowerCase();
                item.style.display = text.includes(searchTerm) ? 'grid' : 'none';
            });

            resultItems.forEach(item => {
                const text = item.textContent.toLowerCase();
                item.style.display = text.includes(searchTerm) ? 'flex' : 'none';
            });
        });
    }
}

// Animate progress bars
function animateProgressBars() {
    const progressBars = document.querySelectorAll('.progress-fill');
    progressBars.forEach(bar => {
        const width = bar.style.width;
        bar.style.width = '0%';
        setTimeout(() => {
            bar.style.width = width;
        }, 100);
    });
}

// Keyboard shortcuts
function addKeyboardShortcuts() {
    document.addEventListener('keydown', function(e) {
        // Alt + D for dashboard
        if (e.altKey && e.key === 'd') {
            window.location.href = 'dashboard.php';
        }
        // Alt + E for exams
        if (e.altKey && e.key === 'e') {
            window.location.href = 'exams.php';
        }
        // Alt + A for AI tutor
        if (e.altKey && e.key === 'a') {
            window.location.href = 'ai-tutor.php';
        }
        // Escape to close modals
        if (e.key === 'Escape') {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => modal.remove());
        }
    });
}

// Offline detection
function addOfflineDetection() {
    window.addEventListener('online', () => {
        showToast('🟢 You are back online!', 'success');
    });
    
    window.addEventListener('offline', () => {
        showToast('🔴 You are offline. Some features may be limited.', 'error');
    });
}

// Reading progress for articles
function addReadingProgress() {
    const progressBar = document.createElement('div');
    progressBar.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 0%;
        height: 3px;
        background: linear-gradient(90deg, #667eea, #764ba2);
        z-index: 10000;
        transition: width 0.3s;
    `;
    document.body.appendChild(progressBar);
    
    window.addEventListener('scroll', () => {
        const windowHeight = window.innerHeight;
        const documentHeight = document.documentElement.scrollHeight;
        const scrollTop = window.scrollY;
        const progress = (scrollTop / (documentHeight - windowHeight)) * 100;
        progressBar.style.width = progress + '%';
    });
}

// Exam timer with better UI
if (document.querySelector('.exam-container')) {
    let timeLeft = 30 * 60;
    let timerInterval;
    const warningThreshold = 5 * 60; // 5 minutes
    
    const timerDisplay = document.createElement('div');
    timerDisplay.className = 'exam-timer';
    timerDisplay.style.cssText = `
        position: fixed;
        top: 80px;
        right: 20px;
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: white;
        padding: 12px 24px;
        border-radius: 30px;
        font-weight: bold;
        font-size: 18px;
        z-index: 999;
        box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        animation: pulse 2s infinite;
    `;
    document.body.appendChild(timerDisplay);
    
    const timer = setInterval(() => {
        const minutes = Math.floor(timeLeft / 60);
        const seconds = timeLeft % 60;
        timerDisplay.textContent = `⏱️ ${minutes}:${seconds.toString().padStart(2, '0')}`;
        
        // Warning when time is low
        if (timeLeft === warningThreshold) {
            timerDisplay.style.animation = 'pulse 0.5s infinite';
            timerDisplay.style.background = '#ed8936';
            showToast('⚠️ 5 minutes remaining!', 'warning');
        }
        
        // Critical warning
        if (timeLeft === 60) {
            timerDisplay.style.background = '#f56565';
            showToast('⏰ 1 minute remaining!', 'error');
        }
        
        if (timeLeft <= 0) {
            clearInterval(timer);
            timerDisplay.textContent = '⏰ Time\'s Up!';
            document.getElementById('examForm')?.submit();
        }
        timeLeft--;
    }, 1000);
}

// Add CSS animation for pulse
const style = document.createElement('style');
style.textContent = `
    @keyframes pulse {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.05); }
    }
`;
document.head.appendChild(style);

// Dynamic content loading
async function loadContent(url, containerId) {
    const container = document.getElementById(containerId);
    if (container) {
        container.innerHTML = '<div class="spinner"></div> Loading...';
        try {
            const response = await fetch(url);
            const html = await response.text();
            container.innerHTML = html;
        } catch (error) {
            container.innerHTML = '<p>Error loading content. Please try again.</p>';
        }
    }
}

// Save user preferences to localStorage
function savePreference(key, value) {
    localStorage.setItem(`lms_${key}`, value);
}

function getPreference(key) {
    return localStorage.getItem(`lms_${key}`);
}

// Theme toggle (Light/Dark)
function addThemeToggle() {
    const toggle = document.createElement('button');
    toggle.innerHTML = '🌙';
    toggle.style.cssText = `
        position: fixed;
        bottom: 20px;
        left: 20px;
        width: 45px;
        height: 45px;
        border-radius: 22.5px;
        background: white;
        border: none;
        cursor: pointer;
        font-size: 20px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        z-index: 1000;
        transition: all 0.3s;
    `;
    document.body.appendChild(toggle);
    
    const isDark = getPreference('theme') === 'dark';
    if (isDark) {
        document.body.classList.add('dark-mode');
        toggle.innerHTML = '☀️';
    }
    
    toggle.addEventListener('click', () => {
        document.body.classList.toggle('dark-mode');
        const isDarkNow = document.body.classList.contains('dark-mode');
        toggle.innerHTML = isDarkNow ? '☀️' : '🌙';
        savePreference('theme', isDarkNow ? 'dark' : 'light');
    });
}

// Call this on pages with content
if (!window.location.pathname.includes('index.html')) {
    addThemeToggle();
}

// Add confirmation dialog for important actions
function confirmAction(message, callback) {
    if (confirm(message)) {
        callback();
    }
}

// Export functions for global use
window.showToast = showToast;
window.confirmAction = confirmAction;
window.loadContent = loadContent;
window.savePreference = savePreference;