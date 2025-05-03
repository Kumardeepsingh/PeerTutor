<?php
session_start();
require_once 'db_connect.php';

// Check if user is logged in as a student
if(!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php?error=Please login as a student to access this page");
    exit();
}

$tutor_id = $_GET['tutor_id'] ?? 0;
$user_id = $_SESSION['user_id'];
$error = '';
$success = '';
$hasPendingReport = false;

// Verify student has had at least one session with this tutor
$stmt = $conn->prepare("
    SELECT COUNT(*) as session_count 
    FROM sessions 
    WHERE student_id = ? AND tutor_id = ? AND status = 'completed'
");
$stmt->bind_param("ii", $user_id, $tutor_id);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_assoc();

if ($data['session_count'] == 0) {
    header("Location: find_tutors.php?error=You can only report tutors you've had sessions with");
    exit();
}

// Get tutor details
$stmt = $conn->prepare("
    SELECT id, fullname, email 
    FROM users 
    WHERE id = ? AND role = 'tutor'
");
$stmt->bind_param("i", $tutor_id);
$stmt->execute();
$tutor_result = $stmt->get_result();

if ($tutor_result->num_rows === 0) {
    header("Location: find_tutors.php?error=Invalid tutor");
    exit();
}

$tutor = $tutor_result->fetch_assoc();

// Check for existing pending report
$stmt = $conn->prepare("
    SELECT id 
    FROM tutor_reports 
    WHERE tutor_id = ? AND reporter_id = ? AND status = 'pending'
");
$stmt->bind_param("ii", $tutor_id, $user_id);
$stmt->execute();
$pending_report_result = $stmt->get_result();

if ($pending_report_result->num_rows > 0) {
    $hasPendingReport = true;
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$hasPendingReport) {
    $reason = $_POST['reason'] ?? '';
    $additional_details = trim($_POST['additional_details'] ?? '');
    $session_id = !empty($_POST['session_id']) ? $_POST['session_id'] : null;
    
    // Validate inputs
    if (empty($reason)) {
        $error = "Please select a reason for reporting";
    } else {
        // Insert report into database
        try {
            if (empty($session_id)) {
                $stmt = $conn->prepare("
                    INSERT INTO tutor_reports
                    (tutor_id, reporter_id, reason, additional_details, status) 
                    VALUES (?, ?, ?, ?, 'pending')
                ");
                $stmt->bind_param("iiss", $tutor_id, $user_id, $reason, $additional_details);
            } else {
                $stmt = $conn->prepare("
                    INSERT INTO tutor_reports
                    (tutor_id, reporter_id, session_id, reason, additional_details, status) 
                    VALUES (?, ?, ?, ?, ?, 'pending')
                ");
                $stmt->bind_param("iiiss", $tutor_id, $user_id, $session_id, $reason, $additional_details);
            }
            
            if ($stmt->execute()) {
                $success = "Thank you for your report. We will review it shortly.";
                $hasPendingReport = true;
            } else {
                $error = "Error submitting report: " . $conn->error;
            }
        } catch (mysqli_sql_exception $e) {
            $error = "Error submitting report. Please try again.";
            error_log("Report submission error: " . $e->getMessage());
        }
    }
}

// Get completed sessions with this tutor for dropdown
$stmt = $conn->prepare("
    SELECT id, session_date, subject_id 
    FROM sessions 
    WHERE student_id = ? AND tutor_id = ? AND status = 'completed'
    ORDER BY session_date DESC
");
$stmt->bind_param("ii", $user_id, $tutor_id);
$stmt->execute();
$sessions_result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Tutor - PeerTutor</title>
    <link rel="stylesheet" href="styles/styles.css">
    <link rel="stylesheet" href="styles/review-report-styles.css">
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
        <div class="report-container">
            <h2>Report Tutor</h2>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <div class="tutor-display">
                <div class="tutor-meta">
                    <strong>Tutor:</strong> <?php echo htmlspecialchars($tutor['fullname']); ?><br>
                    <strong>Email:</strong> <?php echo htmlspecialchars($tutor['email']); ?>
                </div>
            </div>
            
            <?php if ($hasPendingReport && empty($success)): ?>
                <div class="alert alert-info">
                    You already have a pending report for this tutor. Please wait for our team to review it.
                </div>
                <div class="form-group">
                    <a href="book_session.php?tutor_id=<?php echo $tutor_id; ?>" class="btn btn-danger">Back to Tutor Profile</a>
                </div>
            <?php else: ?>
                <form method="POST">
                    <div class="form-group">
                        <label for="session_id">Related Session (optional)</label>
                        <select class="form-control" name="session_id" id="session_id">
                            <option value="">-- No specific session --</option>
                            <?php while ($session = $sessions_result->fetch_assoc()): ?>
                                <?php 
                                $subject_stmt = $conn->prepare("SELECT name FROM subjects WHERE id = ?");
                                $subject_stmt->bind_param("i", $session['subject_id']);
                                $subject_stmt->execute();
                                $subject_result = $subject_stmt->get_result();
                                $subject = $subject_result->fetch_assoc();
                                ?>
                                <option value="<?php echo $session['id']; ?>">
                                    <?php echo date('M j, Y', strtotime($session['session_date'])) . ' - ' . htmlspecialchars($subject['name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Reason for Reporting</label>
                        <div class="reason-options">
                            <label class="reason-option">
                                <input type="radio" name="reason" value="Inappropriate Behavior" required> Inappropriate Behavior
                            </label>
                            <label class="reason-option">
                                <input type="radio" name="reason" value="No Show/Late Arrival"> No Show/Late Arrival
                            </label>
                            <label class="reason-option">
                                <input type="radio" name="reason" value="Poor Quality Instruction"> Poor Quality Instruction
                            </label>
                            <label class="reason-option">
                                <input type="radio" name="reason" value="Payment Issues"> Payment Issues
                            </label>
                            <label class="reason-option">
                                <input type="radio" name="reason" value="Harassment"> Harassment
                            </label>
                            <label class="reason-option">
                                <input type="radio" name="reason" value="Other"> Other
                            </label>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="additional_details">Additional Details (optional)</label>
                        <textarea name="additional_details" id="additional_details" placeholder="Please provide any additional information about why you're reporting this tutor..."></textarea>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn">Submit Report</button>
                        <a href="book_session.php?tutor_id=<?php echo $tutor_id; ?>" class="btn btn-danger">Back to Tutor Profile</a>
                    </div>
                </form>
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