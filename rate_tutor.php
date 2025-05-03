<?php
session_start();
require_once 'db_connect.php';

// Check if user is logged in as a student
if(!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit();
}

$tutor_id = $_GET['tutor_id'] ?? 0;
$student_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Get tutor info
$stmt = $conn->prepare("SELECT fullname FROM users WHERE id = ? AND role = 'tutor'");
$stmt->bind_param("i", $tutor_id);
$stmt->execute();
$tutor_result = $stmt->get_result();

if ($tutor_result->num_rows === 0) {
    header("Location: student_dashboard.php?error=Invalid tutor");
    exit();
}

$tutor = $tutor_result->fetch_assoc();

// Get completed sessions between this student and tutor
$stmt = $conn->prepare("
    SELECT s.id, s.session_date, s.start_time, s.end_time, sub.name AS subject_name
    FROM sessions s
    JOIN subjects sub ON s.subject_id = sub.id
    WHERE s.student_id = ? AND s.tutor_id = ? AND s.status = 'completed'
    AND NOT EXISTS (
        SELECT 1 FROM reviews r WHERE r.session_id = s.id
    )
    ORDER BY s.session_date DESC, s.start_time DESC
");
$stmt->bind_param("ii", $student_id, $tutor_id);
$stmt->execute();
$sessions_result = $stmt->get_result();

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $session_id = $_POST['session_id'] ?? 0;
    $rating = $_POST['rating'] ?? 0;
    $comment = trim($_POST['comment'] ?? '');
    
    // Validate inputs
    if (empty($session_id)) {
        $error = "Please select a session to rate";
    } elseif (empty($rating)) {
        $error = "Please provide a rating";
    } else {
        // Check if session belongs to this student and tutor
        $stmt = $conn->prepare("
            SELECT id FROM sessions 
            WHERE id = ? AND student_id = ? AND tutor_id = ? AND status = 'completed'
        ");
        $stmt->bind_param("iii", $session_id, $student_id, $tutor_id);
        $stmt->execute();
        
        if ($stmt->get_result()->num_rows === 0) {
            $error = "Invalid session selected";
        } else {
            try {
                // Insert review
                $stmt = $conn->prepare("
                    INSERT INTO reviews (session_id, student_id, tutor_id, rating, comment)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->bind_param("iiiis", $session_id, $student_id, $tutor_id, $rating, $comment);
                $stmt->execute();
        
                $success = "Thank you for your review!";
                
                // Refresh sessions list to exclude the just-rated one
                $stmt = $conn->prepare("
                    SELECT s.id, s.session_date, s.start_time, s.end_time, sub.name AS subject_name
                    FROM sessions s
                    JOIN subjects sub ON s.subject_id = sub.id
                    WHERE s.student_id = ? AND s.tutor_id = ? AND s.status = 'completed'
                    AND NOT EXISTS (
                        SELECT 1 FROM reviews r WHERE r.session_id = s.id
                    )
                    ORDER BY s.session_date DESC, s.start_time DESC
                ");
                $stmt->bind_param("ii", $student_id, $tutor_id);
                $stmt->execute();
                $sessions_result = $stmt->get_result();
                
            } catch (mysqli_sql_exception $e) {
                if ($e->getCode() == 1062) { // MySQL duplicate entry error
                    $error = "You have already reviewed this session.";
                } else {
                    $error = "Error submitting review: " . $conn->error;
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rate Tutor - PeerTutor</title>
    <link rel="stylesheet" href="styles/styles.css">
    <link rel="stylesheet" href="styles/review-styles.css">
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar">
        <a href="student_dashboard.php" class="logo">Peer<span>Tutor</span></a>
        <ul class="nav-links">
            <li><a href="student_dashboard.php">Dashboard</a></li>
            <li><a href="find_tutors.php">Find Tutors</a></li>
            <li><a href="logout.php">Logout</a></li>
        </ul>
    </nav>

    <!-- Content -->
    <div class="container">
        <h2>Rate Tutor: <?php echo htmlspecialchars($tutor['fullname']); ?></h2>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <?php if ($sessions_result->num_rows === 0): ?>
            <div class="no-sessions">
                <p>You have no completed sessions with this tutor that need rating.</p>
                <a href="book_session.php?tutor_id=<?php echo $tutor_id; ?>" class="btn btn-primary">Back to Tutor Profile</a>
            </div>
        <?php else: ?>
            <form method="POST" class="rating-form">
                <div class="form-group">
                    <h3>Select a Session</h3>
                    <?php while ($session = $sessions_result->fetch_assoc()): ?>
                        <label class="session-option">
                            <input type="radio" name="session_id" value="<?php echo $session['id']; ?>" required>
                            <?php 
                                echo date('M j, Y', strtotime($session['session_date'])) . ' - ' . 
                                date('g:i A', strtotime($session['start_time'])) . ' to ' . 
                                date('g:i A', strtotime($session['end_time'])) . 
                                ' (' . htmlspecialchars($session['subject_name']) . ')';
                            ?>
                        </label>
                    <?php endwhile; ?>
                </div>
                
                <div class="form-group rating-container">
                    <h3>Your Rating</h3>
                    <div class="stars" id="rating-stars">
                        <span class="star" data-rating="1">☆</span>
                        <span class="star" data-rating="2">☆</span>
                        <span class="star" data-rating="3">☆</span>
                        <span class="star" data-rating="4">☆</span>
                        <span class="star" data-rating="5">☆</span>
                    </div>
                    <input type="hidden" name="rating" id="rating-value" required>
                </div>
                
                <div class="form-group">
                    <h3>Your Review (Optional)</h3>
                    <textarea name="comment" placeholder="Share your experience with this tutor..."></textarea>
                </div>
                
                <button type="submit" class="btn btn-primary">Submit Review</button>
            </form>
        <?php endif; ?>
    </div>
        <!-- Footer -->
    <footer>
        <div class="footer-content">
            <div class="footer-about">
                <div class="footer-logo">Peer<span>Tutor</span></div>
                <p>Connecting students with peer tutors for academic success. Our platform makes it easy to find help in any subject, at any level.</p>
            </div>
            <div class="footer-links">
                <h4>Company</h4>
                <ul>
                    <li><a href="about.php">About Us</a></li>
                    <li><a href="privacy.php">Privacy Policy</a></li>
                    <li><a href="terms.php">Terms of Service</a></li>
                </ul>
            </div>
        </div>
        <div class="copyright">
            &copy; <?php echo date("Y"); ?> PeerTutor. All rights reserved.
        </div>
    </footer>
    <script>
        // Star rating functionality
        const stars = document.querySelectorAll('.star');
        const ratingValue = document.getElementById('rating-value');
        
        stars.forEach(star => {
            star.addEventListener('click', () => {
                const rating = star.getAttribute('data-rating');
                ratingValue.value = rating;
                
                stars.forEach((s, index) => {
                    if (index < rating) {
                        s.textContent = '★';
                        s.classList.add('active');
                    } else {
                        s.textContent = '☆';
                        s.classList.remove('active');
                    }
                });
            });
        });
        
        // Session selection styling
        const sessionOptions = document.querySelectorAll('.session-option');
        sessionOptions.forEach(option => {
            const radio = option.querySelector('input[type="radio"]');
            
            radio.addEventListener('change', () => {
                sessionOptions.forEach(opt => opt.classList.remove('selected'));
                if (radio.checked) {
                    option.classList.add('selected');
                }
            });
        });
    </script>
</body>
</html>