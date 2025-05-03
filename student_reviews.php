<?php
session_start();
require_once 'db_connect.php';

// Check if user is logged in as a student
if(!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit();
}

$student_id = $_SESSION['user_id'];
$error = '';
$success = '';
$edit_review_id = null;
$edit_content = '';

// Handle review deletion
if (isset($_GET['delete'])) {
    $review_id = $_GET['delete'];
    
    // Verify the review belongs to this student
    $stmt = $conn->prepare("SELECT id FROM reviews WHERE id = ? AND student_id = ?");
    $stmt->bind_param("ii", $review_id, $student_id);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows > 0) {
        // Delete the review
        $stmt = $conn->prepare("DELETE FROM reviews WHERE id = ?");
        $stmt->bind_param("i", $review_id);
        
        if ($stmt->execute()) {
            $success = "Review deleted successfully!";
        } else {
            $error = "Error deleting review: " . $conn->error;
        }
    } else {
        $error = "Invalid review or you don't have permission to delete this review";
    }
}

// Handle edit form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_edit'])) {
    $review_id = $_POST['review_id'];
    $new_rating = $_POST['rating'];
    $new_comment = trim($_POST['comment']);
    
    // Verify the review belongs to this student
    $stmt = $conn->prepare("SELECT id FROM reviews WHERE id = ? AND student_id = ?");
    $stmt->bind_param("ii", $review_id, $student_id);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows > 0) {
        // Update the review and set the updated_at timestamp
        $stmt = $conn->prepare("UPDATE reviews SET rating = ?, comment = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param("isi", $new_rating, $new_comment, $review_id);
        
        if ($stmt->execute()) {
            $success = "Review updated successfully!";
            $edit_review_id = null; // Exit edit mode
        } else {
            $error = "Error updating review: " . $conn->error;
        }
    } else {
        $error = "Invalid review or you don't have permission to edit this review";
    }
}

// Handle edit request
if (isset($_GET['edit'])) {
    $review_id = $_GET['edit'];
    
    // Get review details
    $stmt = $conn->prepare("
        SELECT r.id, r.rating, r.comment, r.created_at, r.updated_at,
               r.tutor_comment, r.tutor_comment_date,
               t.fullname as tutor_name, s.name as subject_name
        FROM reviews r
        JOIN users t ON r.tutor_id = t.id
        JOIN sessions se ON r.session_id = se.id
        JOIN subjects s ON se.subject_id = s.id
        WHERE r.id = ? AND r.student_id = ?
    ");
    $stmt->bind_param("ii", $review_id, $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $edit_content = $result->fetch_assoc();
        $edit_review_id = $review_id;
    } else {
        $error = "Review not found or you don't have permission to edit it";
    }
}

// Get all reviews by this student with tutor comments
$stmt = $conn->prepare("
    SELECT r.id, r.rating, r.comment, r.created_at, r.updated_at,
           r.tutor_comment, r.tutor_comment_date,
           t.fullname as tutor_name, s.name as subject_name,
           se.session_date, se.start_time, se.end_time
    FROM reviews r
    JOIN users t ON r.tutor_id = t.id
    JOIN sessions se ON r.session_id = se.id
    JOIN subjects s ON se.subject_id = s.id
    WHERE r.student_id = ?
    ORDER BY r.created_at DESC
");
$stmt->bind_param("i", $student_id);
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
    <link rel="stylesheet" href="styles/review-display-styles.css">
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar dashboard-nav">
        <a href="student_dashboard.php" class="logo">Peer<span>Tutor</span></a>
        <ul class="nav-links">
            <li><a href="student_dashboard.php" class="active">Dashboard</a></li>
            <li><a href="find_tutors.php">Find Tutors</a></li>
            <li><a href="my_sessions.php">My Sessions</a></li>
            <li><a href="student_profile.php">Profile</a></li>
            <li><a href="logout.php" class="btn secondary">Logout</a></li>
        </ul>
    </nav>

    <!-- Content -->
    <div class="container">
        <h2>My Reviews</h2>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <div class="reviews-container">
            <?php if ($reviews->num_rows === 0): ?>
                <div class="no-reviews">
                    <p>You haven't submitted any reviews yet.</p>
                    <a href="find_tutors.php" class="btn btn-primary">Find Tutors to Review</a>
                </div>
            <?php else: ?>
                <?php while ($review = $reviews->fetch_assoc()): ?>
                    <div class="review-item">
                        <div class="review-header">
                            <div>
                                <h3><?php echo htmlspecialchars($review['tutor_name']); ?></h3>
                            </div>
                            <div class="review-rating">
                                <?php
                                for ($i = 1; $i <= 5; $i++) {
                                    echo $i <= $review['rating'] ? '★' : '☆';
                                }
                                ?>
                            </div>
                        </div>

                        <div class="review-meta">
                            <?php echo htmlspecialchars($review['subject_name']); ?> • 
                            <?php echo date('M j, Y', strtotime($review['session_date'])); ?> • 
                            <?php echo date('g:i A', strtotime($review['start_time'])); ?>-<?php echo date('g:i A', strtotime($review['end_time'])); ?>
                        </div>
                        
                        <div class="review-comment">
                            <?php echo htmlspecialchars($review['comment']); ?>
                        </div>
                        
                        <div class="review-meta">
                            Posted on <?php echo date('F j, Y', strtotime($review['created_at'])); ?>
                            <?php if ($review['updated_at'] !== $review['created_at']): ?>
                                <span class="edited-indicator">(edited on <?php echo date('F j, Y', strtotime($review['updated_at'])); ?>)</span>
                            <?php endif; ?>
                        </div>
                        
                        <?php if (!empty($review['tutor_comment'])): ?>
                            <div class="tutor-response">
                                <div class="response-header">Tutor's Response</div>
                                <?php if (!empty($review['tutor_comment_date'])): ?>
                                    <div class="response-date">
                                        <?php echo date('F j, Y', strtotime($review['tutor_comment_date'])); ?>
                                    </div>
                                <?php endif; ?>
                                <div class="response-content">
                                    <?php echo htmlspecialchars($review['tutor_comment']); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="review-actions">
                            <a href="student_reviews.php?edit=<?php echo $review['id']; ?>" class="btn btn-secondary">Edit</a>
                            <a href="student_reviews.php?delete=<?php echo $review['id']; ?>" class="btn btn-danger" 
                               onclick="return confirm('Are you sure you want to delete this review?');">Delete</a>
                        </div>
                        
                        <?php if ($edit_review_id == $review['id']): ?>
                            <div class="edit-form">
                                <h4>Edit Review</h4>
                                <form method="POST">
                                    <input type="hidden" name="review_id" value="<?php echo $review['id']; ?>">
                                    
                                    <div class="stars-edit" id="edit-rating-stars">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <span class="star-edit <?php echo $i <= $edit_content['rating'] ? 'active' : ''; ?>" 
                                                  data-rating="<?php echo $i; ?>">★</span>
                                        <?php endfor; ?>
                                    </div>
                                    <input type="hidden" name="rating" id="edit-rating-value" value="<?php echo $edit_content['rating']; ?>">
                                    
                                    <div class="form-group">
                                        <textarea name="comment" class="form-control" rows="4"><?php echo htmlspecialchars($edit_content['comment']); ?></textarea>
                                    </div>
                                    
                                    <button type="submit" name="save_edit" class="btn btn-primary">Save Changes</button>
                                    <a href="student_reviews.php" class="btn btn-danger">Back</a>
                                </form>
                            </div>
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
    
    <script>
        // Star rating functionality for edit form
        const editStars = document.querySelectorAll('.star-edit');
        const editRatingValue = document.getElementById('edit-rating-value');
        
        if (editStars.length > 0) {
            editStars.forEach(star => {
                star.addEventListener('click', () => {
                    const rating = star.getAttribute('data-rating');
                    editRatingValue.value = rating;
                    
                    editStars.forEach((s, index) => {
                        if (index < rating) {
                            s.classList.add('active');
                        } else {
                            s.classList.remove('active');
                        }
                    });
                });
            });
        }
    </script>
</body>
</html>