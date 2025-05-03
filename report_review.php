<?php
session_start();
require_once 'db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['logged_in'])) {
    header("Location: login.php?error=Please login to access this page");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role']; // 'student' or 'tutor'
$tutor_id = $_GET['tutor_id'] ?? ''; // Only needed if the user is a student
$review_id = $_GET['review_id'] ?? 0;
$error = '';
$success = '';
$already_reported = false;

// Check if this user has already reported this review
$stmt = $conn->prepare("SELECT id FROM review_reports WHERE review_id = ? AND reporter_id = ?");
$stmt->bind_param("ii", $review_id, $user_id);
$stmt->execute();
$report_result = $stmt->get_result();

if ($report_result->num_rows > 0) {
    $already_reported = true;
    $success = "You have already reported this review. Our team will review it shortly.";
}

// Get review details
$stmt = $conn->prepare("
    SELECT r.id, r.comment, r.tutor_id, u.fullname as student_name, t.fullname as tutor_name
    FROM reviews r
    JOIN users u ON r.student_id = u.id
    JOIN users t ON r.tutor_id = t.id
    WHERE r.id = ?
");
$stmt->bind_param("i", $review_id);
$stmt->execute();
$review_result = $stmt->get_result();

if ($review_result->num_rows === 0) {
    header("Location: find_tutors.php?error=Invalid review");
    exit();
}

$review = $review_result->fetch_assoc();

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$already_reported) {
    $reason = $_POST['reason'] ?? '';
    $additional_details = trim($_POST['additional_details'] ?? '');
    
    // Validate inputs
    if (empty($reason)) {
        $error = "Please select a reason for reporting";
    } else {
        // Insert report into database
        $stmt = $conn->prepare("
            INSERT INTO review_reports
            (review_id, reporter_id, reason, additional_details, status) 
            VALUES (?, ?, ?, ?, 'pending')
        ");
        $stmt->bind_param("iiss", $review_id, $user_id, $reason, $additional_details);
        
        if ($stmt->execute()) {
            $success = "Thank you for your report. We will review it shortly.";
            $already_reported = true;
        } else {
            $error = "Error submitting report: " . $conn->error;
        }
    }
}

// Determine the back button link
$back_url = ($user_role === 'tutor') ? "tutor_reviews.php" : "book_session.php?tutor_id=$tutor_id";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Review - PeerTutor</title>
    <link rel="stylesheet" href="styles/styles.css">
    <link rel="stylesheet" href="styles/review-report-styles.css">
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar">
        <a href="<?php echo $user_role === 'tutor' ? 'tutor_dashboard.php' : 'student_dashboard.php'; ?>" class="logo">Peer<span>Tutor</span></a>
        <ul class="nav-links">
            <li><a href="<?php echo $user_role === 'tutor' ? 'tutor_dashboard.php' : 'student_dashboard.php'; ?>">Dashboard</a></li>
            <li><a href="find_tutors.php">Find Tutors</a></li>
            <li><a href="logout.php">Logout</a></li>
        </ul>
    </nav>

    <!-- Content -->
    <div class="container">
        <div class="report-container">
            <h2>Report Review</h2>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <div class="review-display">
                <div class="review-meta">
                    <strong>Student:</strong> <?php echo htmlspecialchars($review['student_name']); ?><br>
                    <strong>Tutor:</strong> <?php echo htmlspecialchars($review['tutor_name']); ?>
                </div>
                <div class="review-content">
                    <?php echo htmlspecialchars($review['comment']); ?>
                </div>
            </div>
            
            <?php if (!$already_reported): ?>
                <form method="POST">
                    <div class="form-group">
                        <label>Reason for Reporting</label>
                        <div class="reason-options">
                            <label class="reason-option">
                                <input type="radio" name="reason" value="Inappropriate Content" required> Inappropriate Content
                            </label>
                            <label class="reason-option">
                                <input type="radio" name="reason" value="False Information"> False Information
                            </label>
                            <label class="reason-option">
                                <input type="radio" name="reason" value="Harassment"> Harassment
                            </label>
                            <label class="reason-option">
                                <input type="radio" name="reason" value="Spam"> Spam
                            </label>
                            <label class="reason-option">
                                <input type="radio" name="reason" value="Other"> Other
                            </label>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="additional_details">Additional Details (optional)</label>
                        <textarea name="additional_details" id="additional_details" placeholder="Please provide any additional information about why you're reporting this review..."></textarea>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn">Submit Report</button>
                        <a href="<?php echo $back_url; ?>" class="btn btn-danger">Back</a>
                    </div>
                </form>
            <?php else: ?>
                <div class="form-group">
                    <a href="<?php echo $back_url; ?>" class="btn btn-danger">Back</a>
                </div>
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