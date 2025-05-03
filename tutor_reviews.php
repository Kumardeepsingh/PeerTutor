<?php
session_start();
require_once 'db_connect.php';

// Check if user is logged in as a tutor
if(!isset($_SESSION['logged_in'])) {
    header("Location: login.php");
    exit();
}

$tutor_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Get tutor info and review stats
$stmt = $conn->prepare("
    SELECT u.fullname, 
           AVG(r.rating) as avg_rating,
           COUNT(r.id) as review_count
    FROM users u
    LEFT JOIN reviews r ON u.id = r.tutor_id
    WHERE u.id = ?
");
$stmt->bind_param("i", $tutor_id);
$stmt->execute();
$tutor_result = $stmt->get_result();
$tutor = $tutor_result->fetch_assoc();

// Handle comment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_comment'])) {
    $review_id = $_POST['review_id'];
    $comment = trim($_POST['tutor_comment']);
    
    // Verify the review belongs to this tutor
    $stmt = $conn->prepare("SELECT id FROM reviews WHERE id = ? AND tutor_id = ?");
    $stmt->bind_param("ii", $review_id, $tutor_id);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows > 0) {
        // Update with tutor's comment
        $stmt = $conn->prepare("UPDATE reviews SET tutor_comment = ?, tutor_comment_date = NOW() WHERE id = ?");
        $stmt->bind_param("si", $comment, $review_id);
        
        if ($stmt->execute()) {
            $success = "Your response has been added!";
        } else {
            $error = "Error adding response: " . $conn->error;
        }
    } else {
        $error = "Invalid review or you don't have permission to comment on this review";
    }
}

// Get all reviews for this tutor with student info and report status
$stmt = $conn->prepare("
    SELECT r.id, r.rating, r.comment, r.created_at, r.updated_at, r.tutor_comment, r.tutor_comment_date,
           u.fullname as student_name, u.profile_image as student_image,
           s.name as subject_name, se.session_date,
           (SELECT COUNT(*) FROM review_reports WHERE review_id = r.id AND reporter_id = ?) as is_reported
    FROM reviews r
    JOIN users u ON r.student_id = u.id
    JOIN sessions se ON r.session_id = se.id
    JOIN subjects s ON se.subject_id = s.id
    WHERE r.tutor_id = ?
    ORDER BY r.created_at DESC
");
$stmt->bind_param("ii", $tutor_id, $tutor_id);
$stmt->execute();
$reviews = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Reviews - PeerTutor</title>
    <link rel="stylesheet" href="styles/styles.css">
    <link rel="stylesheet" href="styles/review-styles.css">
    <link rel="stylesheet" href="styles/tutor-review-styles.css">
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar dashboard-nav">
        <a href="tutor_dashboard.php" class="logo">Peer<span>Tutor</span></a>
        <ul class="nav-links">
            <li><a href="tutor_dashboard.php" class="active">Dashboard</a></li>
            <li><a href="my_sessions.php">Sessions</a></li>
            <li><a href="tutor_profile.php">Profile</a></li>
            <li><a href="tutor_availability.php">Availability</a></li>
            <li><a href="logout.php" class="btn secondary">Logout</a></li>
        </ul>
    </nav>

    <!-- Content -->
    <div class="container">
        <div class="reviews-header">
            <h2>Reviews Received</h2>
            
            <div class="stats-container">
                <div class="stat-box">
                    <div class="stat-value"><?php echo number_format($tutor['avg_rating'], 1); ?></div>
                    <div class="stat-label">Average Rating</div>
                </div>
                <div class="stat-box">
                    <div class="stat-value"><?php echo $tutor['review_count']; ?></div>
                    <div class="stat-label">Total Reviews</div>
                </div>
            </div>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <div class="reviews-list">
            <?php if ($reviews->num_rows === 0): ?>
                <div class="empty-reviews">
                    <p>You haven't received any reviews yet.</p>
                </div>
            <?php else: ?>
                <?php while ($review = $reviews->fetch_assoc()): ?>
                    <div class="review-item">
                        <div class="review-header">
                            <div class="student-avatar">
                                <?php if (!empty($review['student_image'])): ?>
                                    <img src="<?php echo $review['student_image']; ?>"> 
                                <?php else: ?>
                                    <?php echo substr(htmlspecialchars($review['student_name']), 0, 1); ?>
                                <?php endif; ?>
                            </div>
                            <div class="student-info">
                                <div class="student-name"><?php echo htmlspecialchars($review['student_name']); ?></div>
                                <div class="review-meta">
                                    <?php echo htmlspecialchars($review['subject_name']); ?> • 
                                    <?php echo date('M j, Y', strtotime($review['session_date'])); ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="review-rating">
                            <?php
                            for ($i = 1; $i <= 5; $i++) {
                                echo $i <= $review['rating'] ? '★' : '<span style="opacity: 0.3">☆</span>';
                            }
                            ?>
                        </div>
                        
                        <div class="review-content">
                            <?php echo htmlspecialchars($review['comment']); ?>
                            <div class="review-meta" style="margin-top: 10px;">
                                Posted on <?php echo date('F j, Y', strtotime($review['created_at'])); ?>
                                <?php if (!empty($review['updated_at']) && $review['updated_at'] !== $review['created_at']): ?>
                                    <span class="separator">|</span> Edited on <?php echo date('F j, Y', strtotime($review['updated_at'])); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <?php if (!empty($review['tutor_comment'])): ?>
                            <div class="tutor-response">
                                <div class="response-header">Your Response</div>
                                <div class="response-date">
                                    <?php echo date('F j, Y', strtotime($review['tutor_comment_date'])); ?>
                                </div>
                                <div class="response-content">
                                    <?php echo htmlspecialchars($review['tutor_comment']); ?>
                                </div>
                            </div>
                        <?php else: ?>
                            <form method="POST" class="comment-form">
                                <input type="hidden" name="review_id" value="<?php echo $review['id']; ?>">
                                <div class="form-group">
                                    <label for="tutor_comment_<?php echo $review['id']; ?>">Add a response to this review</label>
                                    <textarea name="tutor_comment" id="tutor_comment_<?php echo $review['id']; ?>" class="form-control" rows="3"></textarea>
                                </div>
                                <div class="review-actions">
                                    <button type="submit" name="add_comment" class="btn btn-primary">Post Response</button>
                                    <?php if ($review['is_reported'] > 0): ?>
                                        <span class="reported-badge">Reported</span>
                                    <?php else: ?>
                                        <a href="report_review.php?review_id=<?php echo $review['id']; ?>" class="btn btn-danger">Report Review</a>
                                    <?php endif; ?>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endwhile; ?>
            <?php endif; ?>
        </div>
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
</body>
</html>