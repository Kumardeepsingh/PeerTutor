<?php
session_start();

// Check if user is logged in as a student
if(!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'student'){
    header("Location: login.php");
    exit();
}

// Include database connection
require_once 'db_connect.php';
require_once 'stripe_config.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Initialize variables
$tutor_id = $_GET['tutor_id'] ?? 0;
$subject_id = $_GET['subject_id'] ?? 0;
$student_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Check if student is verified
$stmt = $conn->prepare("SELECT is_verified FROM users WHERE id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$is_verified = $user['is_verified'];

if (isset($_GET['session_id'])) {
    try {
        // Retrieve the Checkout Session
        $checkout_session = \Stripe\Checkout\Session::retrieve($_GET['session_id']);
        
        // Get Payment Intent ID from the Checkout Session
        $paymentIntentId = $checkout_session->payment_intent;
        
        // Retrieve session data from session storage
        if (isset($_SESSION['pending_session'])) {
            $pending_session = $_SESSION['pending_session'];
            
            // Insert session into database
            $stmt = $conn->prepare("
                INSERT INTO sessions (student_id, tutor_id, subject_id, session_date, start_time, end_time, status, additional_notes, total_price)
                VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, ?)
            ");
            $stmt->bind_param("iiissssd", 
                $pending_session['student_id'], 
                $pending_session['tutor_id'], 
                $pending_session['subject_id'], 
                $pending_session['session_date'], 
                $pending_session['start_time'], 
                $pending_session['end_time'], 
                $pending_session['additional_notes'], 
                $pending_session['total_price']
            );
            
            if ($stmt->execute()) {
                $session_id = $conn->insert_id;
                
                // Create payment transaction
                $stmt = $conn->prepare("
                    INSERT INTO payment_transactions 
                    (session_id, student_id, tutor_id, total_amount, stripe_payment_intent_id, status) 
                    VALUES (?, ?, ?, ?, ?, 'authorized') 
                ");
                $stmt->bind_param("iiids", $session_id, $student_id, $pending_session['tutor_id'], 
                                $pending_session['total_price'], $paymentIntentId);
                $stmt->execute();
                
                // Send email notification to tutor
                $stmt = $conn->prepare("SELECT email FROM users WHERE id = ?");
                $stmt->bind_param("i", $pending_session['tutor_id']);
                $stmt->execute();
                $tutor_email_result = $stmt->get_result();
                $tutor_email = $tutor_email_result->fetch_assoc()['email'];
                
                $stmt = $conn->prepare("SELECT name FROM subjects WHERE id = ?");
                $stmt->bind_param("i", $pending_session['subject_id']);
                $stmt->execute();
                $subject_result = $stmt->get_result();
                $subject_name = $subject_result->fetch_assoc()['name'];
                
                $stmt = $conn->prepare("SELECT fullname FROM users WHERE id = ?");
                $stmt->bind_param("i", $student_id);
                $stmt->execute();
                $student_result = $stmt->get_result();
                $student_name = $student_result->fetch_assoc()['fullname'];
                
                $mail = new PHPMailer(true);
                try {
                    // Server settings
                    $mail->isSMTP();
                    $mail->Host = 'smtp.gmail.com';
                    $mail->SMTPAuth = true;
                    $mail->Username = $_ENV['GMAIL_USERNAME']; 
                    $mail->Password = $_ENV['GMAIL_PASSWORD'];
                    $mail->Port = 587;
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    
                    // Recipients
                    $mail->setFrom($_ENV['GMAIL_USERNAME'], 'PeerTutor');
                    $mail->addAddress($tutor_email);
                    
                    // Calculate fees and earnings
                    $platform_fee = $pending_session['total_price'] * 0.10; // 10% platform fee
                    $tutor_earnings = $pending_session['total_price'] - $platform_fee; // 90% to tutor
                    
                    // Content
                    $mail->isHTML(true);
                    $mail->Subject = 'New Session Request - ' . $subject_name;
                    
                    $email_body = "
                        <h2>New Session Request</h2>
                        <p>You have received a new session request from $student_name.</p>
                        <p><strong>Subject:</strong> $subject_name</p>
                        <p><strong>Date:</strong> {$pending_session['session_date']}</p>
                        <p><strong>Time:</strong> " . date('h:i A', strtotime($pending_session['start_time'])) . " to " . date('h:i A', strtotime($pending_session['end_time'])) . "</p>
                        <p><strong>Duration:</strong> {$pending_session['duration']} hour(s)</p>
                        <p><strong>Total Price:</strong> $" . number_format($pending_session['total_price'], 2) . "</p>
                        <p><strong>Platform Fee (10%):</strong> $" . number_format($platform_fee, 2) . "</p>
                        <p><strong>Your Earnings (90%):</strong> $" . number_format($tutor_earnings, 2) . "</p>";
                    
                    if (!empty($pending_session['additional_notes'])) {
                        $email_body .= "<p><strong>Student Notes:</strong><br>" . htmlspecialchars($pending_session['additional_notes']) . "</p>";
                    }
                    
                    $email_body .= "
                        <p>Please log in to your dashboard to review and respond to this request.</p>
                        <p>Thank you,<br>The PeerTutor Team</p>";
                    
                    $mail->Body = $email_body;
                    $mail->AltBody = strip_tags($email_body);
                    
                    $mail->send();
                    
                } catch (Exception $e) {
                    error_log("Email sending failed: " . $mail->ErrorInfo);
                }
                
                // Clear the pending session from session storage
                unset($_SESSION['pending_session']);
                
                $success = "Payment is successful and Session is requested! The tutor has been notified and will respond soon.";
            }
        } else {
            $error = "Session data not found. Please try booking again.";
        }

    } catch (\Stripe\Exception\ApiErrorException $e) {
        die("Error retrieving session: " . $e->getMessage());
    }
}

// Check if tutor exists and is approved
$stmt = $conn->prepare("
    SELECT u.id, u.fullname, u.profile_image, tp.hourly_rate, tp.preferred_languages, tp.bio 
    FROM users u 
    JOIN tutor_profiles tp ON u.id = tp.user_id 
    WHERE u.id = ? AND u.role = 'tutor' AND tp.status = 'approved'
");
$stmt->bind_param("i", $tutor_id);
$stmt->execute();
$tutor_result = $stmt->get_result();

if ($tutor_result->num_rows === 0) {
    header("Location: find_tutors.php?error=Invalid tutor selected");
    exit();
}

$tutor = $tutor_result->fetch_assoc();

// Get tutor's subjects
$stmt = $conn->prepare("
    SELECT s.id, s.name, s.category, ts.proficiency_level
    FROM subjects s
    JOIN tutor_subjects ts ON s.id = ts.subject_id
    JOIN tutor_profiles tp ON ts.tutor_profile_id = tp.id
    WHERE tp.user_id = ?
    ORDER BY s.category, s.name
");
$stmt->bind_param("i", $tutor_id);
$stmt->execute();
$subjects_result = $stmt->get_result();

// Get tutor's availability
$stmt = $conn->prepare("
    SELECT day_of_week, start_time, end_time
    FROM tutor_availability
    WHERE tutor_id = ?
    ORDER BY FIELD(day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday')
");
$stmt->bind_param("i", $tutor_id);
$stmt->execute();
$availability_result = $stmt->get_result();

// Get tutor's reviews and completed sessions count
$stmt = $conn->prepare("
    SELECT AVG(rating) AS avg_rating, COUNT(r.id) AS review_count, 
           (SELECT COUNT(*) FROM sessions WHERE tutor_id = ? AND status = 'completed') AS completed_sessions
    FROM reviews r
    WHERE r.tutor_id = ?
");
$stmt->bind_param("ii", $tutor_id, $tutor_id);
$stmt->execute();
$review_result = $stmt->get_result();
$review_data = $review_result->fetch_assoc();

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // First check if user is verified
    if (!$is_verified) {
        $error = "You must verify your email address before booking sessions. Please check your email for the verification link or <a href='resend_verification.php'>resend the verification email</a>.";
    } else {
        // Get form data
        $subject_id = $_POST['subject_id'] ?? 0;
        $session_date = $_POST['session_date'] ?? '';
        $start_time = $_POST['start_time'] ?? '';
        $duration = $_POST['duration'] ?? 0;
        $additional_notes = $_POST['additional_notes'] ?? '';
        
        // Calculate end time
        $end_time = date('H:i:s', strtotime($start_time) + ($duration * 60 * 60));
        
        // Validate inputs
        if (empty($subject_id) || empty($session_date) || empty($start_time) || empty($duration)) {
            $error = "All fields are required.";
        } else {
            // Validate that session date is in the future
            $today = date('Y-m-d');
            if ($session_date < $today) {
                $error = "Session date cannot be in the past.";
            } else {
                // Get day of week from date
                $day_of_week = date('l', strtotime($session_date));
                
                // Check if tutor is available on that day and time
                $stmt = $conn->prepare("
                    SELECT * FROM tutor_availability
                    WHERE tutor_id = ? AND day_of_week = ? 
                    AND start_time <= ? AND end_time >= ?
                ");
                $stmt->bind_param("isss", $tutor_id, $day_of_week, $start_time, $end_time);
                $stmt->execute();
                $available_result = $stmt->get_result();
                
                if ($available_result->num_rows === 0) {
                    $error = "Tutor is not available at the selected time.";
                } else {
                    // Check for any overlapping scheduled sessions
                    $stmt = $conn->prepare("
                    SELECT id FROM sessions 
                    WHERE tutor_id = ? 
                    AND session_date = ?
                    AND status = 'scheduled' 
                    AND (
                        (start_time < ? AND end_time > ?) OR  -- New session starts during existing
                        (start_time < ? AND end_time > ?) OR  -- New session ends during existing
                        (start_time >= ? AND end_time <= ?)   -- New session completely within existing
                    )
                    ");
                    $stmt->bind_param("isssssss", $tutor_id, $session_date, 
                                $end_time, $start_time,    
                                $end_time, $start_time,    
                                $start_time, $end_time);   
                    $stmt->execute();
                    $overlap_result = $stmt->get_result();

                    if ($overlap_result->num_rows > 0) {
                        $error = "The tutor already has a scheduled session during this time. Please choose a different time.";
                    } else {
                        // Check for overlapping pending or scheduled sessions for this student
                        $stmt = $conn->prepare("
                            SELECT id FROM sessions 
                            WHERE tutor_id = ? 
                            AND session_date = ?
                            AND student_id = ?
                            AND status IN ('pending', 'scheduled')
                            AND (
                                (start_time < ? AND end_time > ?) OR
                                (start_time < ? AND end_time > ?) OR
                                (start_time >= ? AND end_time <= ?)
                            )
                        ");
                        $stmt->bind_param("isissssss", $tutor_id, $session_date, $student_id,
                                        $end_time, $start_time,
                                        $end_time, $start_time,
                                        $start_time, $end_time);
                        $stmt->execute();
                        $student_overlap_result = $stmt->get_result();

                        if ($student_overlap_result->num_rows > 0) {
                            $error = "You already have a pending or scheduled session with this tutor during this time. Please choose a different time.";
                        } else {
                            // Calculate total price
                            $total_price = $tutor['hourly_rate'] * $duration;
                            $total_price_cents = intval($total_price * 100);

                            try {
                                // Store session details temporarily until payment is complete
                                $_SESSION['pending_session'] = [
                                    'student_id' => $student_id,
                                    'tutor_id' => $tutor_id,
                                    'subject_id' => $subject_id,
                                    'session_date' => $session_date,
                                    'start_time' => $start_time,
                                    'end_time' => $end_time,
                                    'duration' => $duration,
                                    'additional_notes' => $additional_notes,
                                    'total_price' => $total_price
                                ];

                                // Create Stripe Checkout Session
                                $checkout_session = \Stripe\Checkout\Session::create([
                                    'payment_method_types' => ['card'],
                                    'line_items' => [[
                                        'price_data' => [
                                            'currency' => 'cad',
                                            'unit_amount' => $total_price_cents,
                                            'product_data' => [
                                                'name' => 'Tutoring Session with ' . $tutor['fullname'],
                                                'description' => "Subject: " . $subjects_result->fetch_assoc()['name'] . " | Duration: {$duration} hours"
                                            ],
                                        ],
                                        'quantity' => 1,
                                    ]],
                                    'mode' => 'payment',
                                    'payment_intent_data' => [
                                        'capture_method' => 'manual',
                                    ],
                                    'success_url' => 'http://localhost/project1/book_session.php?tutor_id='. $tutor_id . '&session_id={CHECKOUT_SESSION_ID}',
                                    'cancel_url' => 'http://localhost/project1/book_session.php?tutor_id='. $tutor_id . '&error=Payment%20cancelled',
                                    'client_reference_id' => $_SESSION['user_id']
                                ]);

                                // Redirect to Stripe Checkout
                                header("Location: " . $checkout_session->url);
                                exit();
                            } catch (Exception $e) {
                                $error = "Payment processing error: " . $e->getMessage();
                            }
                        }
                    }
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
    <title>Book Session - PeerTutor</title>
    <link rel="stylesheet" href="styles/styles.css">
    <link rel="stylesheet" href="styles/booking-styles.css">
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
        <h2>Book a Session</h2>
        <div class="booking-container">
            <div class="tutor-info">
                <div class="tutor-image">
                    <?php if($tutor['profile_image']): ?>
                        <img src="<?php echo $tutor['profile_image']; ?>" alt="<?php echo htmlspecialchars($tutor['fullname']); ?>">
                    <?php else: ?>
                        <span><?php echo strtoupper($tutor['fullname'][0]); ?></span>
                    <?php endif; ?>
                </div>
                
                <!-- Tutor name with rating display -->
                <div class="tutor-name-container">
                    <div class="tutor-name-rating-row">
                        <h3 class="tutor-name"><?php echo htmlspecialchars($tutor['fullname']); ?></h3>
                        <div class="tutor-rating">
                            <div class="stars">
                                <?php
                                $avg_rating = $review_data['avg_rating'] ? $review_data['avg_rating'] : 0;
                                $full_stars = floor($avg_rating);
                                $half_star = ($avg_rating - $full_stars) >= 0.5;
                                
                                for ($i = 1; $i <= 5; $i++) {
                                    if ($i <= $full_stars) {
                                        echo '<span class="star">★</span>';
                                    } elseif ($i == $full_stars + 1 && $half_star) {
                                        echo '<span class="half-star">★</span>';
                                    } else {
                                        echo '<span class="empty-star">☆</span>';
                                    }
                                }
                                ?>
                            </div>
                            <span class="rating-count">
                                <?php 
                                if ($review_data['review_count'] > 0) {
                                    echo number_format($avg_rating, 1) . ' (' . $review_data['review_count'] . ' reviews)';
                                } else {
                                    echo '0 reviews';
                                }
                                ?>
                            </span>
                        </div>
                    </div>
                    <div class="tutor-meta">
                        <span class="completed-sessions">
                            <?php echo $review_data['completed_sessions']; ?> completed sessions
                        </span>
                    </div>
                </div>
                
                <div class="tutor-rate">$<?php echo number_format($tutor['hourly_rate'], 2); ?> per hour</div>

                <!-- Rate & Report Buttons -->
                <div class="rate-report-buttons">
                    <a href="rate_tutor.php?tutor_id=<?php echo $tutor_id; ?>" class="btn btn-primary">Rate Tutor</a>
                    <a href="report_tutor.php?tutor_id=<?php echo $tutor_id; ?>" class="btn btn-danger">Report Tutor</a>
                </div>
                
                <div class="tutor-bio"><?php echo htmlspecialchars($tutor['bio']); ?></div>
                
                <div class="availability-list">
                    <h4>Availability</h4>
                    <?php if ($availability_result->num_rows > 0): ?>
                        <?php while ($availability = $availability_result->fetch_assoc()): ?>
                            <div class="availability-item">
                                <span class="day"><?php echo $availability['day_of_week']; ?>:</span>
                                <span><?php echo date('h:i A', strtotime($availability['start_time'])) . ' - ' . date('h:i A', strtotime($availability['end_time'])); ?></span>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="availability-item">
                            <span class="tutor-bio">Tutor has not set up availability yet.</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Booking Form -->
            <div class="booking-form">
                <form method="POST">
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>
                    <?php if ($success): ?>
                        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                    <?php endif; ?>
                    <?php if (isset($_SESSION['success'])): ?>
                        <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
                    <?php endif; ?>
                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
                    <?php endif; ?>
                    <?php if (!$is_verified): ?>
                        <div class="verification-notice">
                            <p>You must verify your email before booking sessions.</p>
                            <a href="resend_verification.php" class="btn small">Resend Verification Email</a>
                        </div>
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label for="subject">Select Subject</label>
                        <select name="subject_id" id="subject" class="form-control">
                            <?php 
                            $subjects_result->data_seek(0);
                            while ($subject = $subjects_result->fetch_assoc()): ?>
                                <option value="<?php echo $subject['id']; ?>" <?php echo (isset($_POST['subject_id']) && $_POST['subject_id'] == $subject['id']) ? "selected" : ""; ?>><?php echo $subject['name']; ?> (<?php echo $subject['category']; ?>)</option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="session_date">Session Date</label>
                            <input type="date" name="session_date" id="session_date" value = "<?php echo isset($_POST['session_date']) ? $_POST['session_date']: ""; ?>" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="start_time">Start Time</label>
                            <input type="time" name="start_time" id="start_time" value = "<?php echo isset($_POST['start_time']) ? $_POST['start_time']: ""; ?>" class="form-control" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="duration">Duration:</label>
                        <select name="duration" id="duration" class="form-control" required>
                            <option value="1" <?php echo (isset($_POST['duration']) && $_POST['duration'] == "1") ? "selected" : ""; ?>>1 hour</option>
                            <option value="1.5" <?php echo (isset($_POST['duration']) && $_POST['duration'] == "1.5") ? "selected" : ""; ?>>1.5 hours</option>
                            <option value="2" <?php echo (isset($_POST['duration']) && $_POST['duration'] == "2") ? "selected" : ""; ?>>2 hours</option>
                            <option value="2.5" <?php echo (isset($_POST['duration']) && $_POST['duration'] == "2.5") ? "selected" : ""; ?>>2.5 hours</option>
                            <option value="3" <?php echo (isset($_POST['duration']) && $_POST['duration'] == "3") ? "selected" : ""; ?>>3 hours</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="additional_notes">Additional Notes (optional):</label>
                        <textarea name="additional_notes" id="additional_notes" class="form-control" rows="4" placeholder="Let the tutor know what you need help with, any specific topics, etc."><?php echo isset($_POST['additional_notes']) ? htmlspecialchars($_POST['additional_notes']) : ''; ?></textarea>
                    </div>
                    
                    <button type="submit" class="btn" <?php echo !$is_verified ? 'disabled' : ''; ?>>Request Session</button>
                    
                </form>
            </div>
        </div>
        
        <!-- Reviews Container -->
        <div class="reviews-container">
            <div class="reviews-header">
                <h3 class="reviews-title">Student Reviews</h3>
                <span class="reviews-amount"><?php echo $review_data['review_count']; ?> reviews</span>
            </div>

            <?php 
            // Check if user is logged in
            $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
            
            $stmt = $conn->prepare("
                SELECT r.id, r.rating, r.comment, r.created_at, r.updated_at, r.tutor_comment, r.tutor_comment_date,
                    su.name as subject_name, u.fullname as student_name 
                FROM reviews r
                JOIN sessions se ON r.session_id = se.id 
                JOIN subjects su ON se.subject_id = su.id
                JOIN users u ON r.student_id = u.id 
                WHERE r.tutor_id = ?
                ORDER BY r.created_at DESC
            ");
            $stmt->bind_param("i", $tutor_id);
            $stmt->execute();
            $reviews = $stmt->get_result();

            if ($reviews->num_rows > 0): ?>
                <?php while ($review = $reviews->fetch_assoc()): ?>
                    <?php
                    // Check if current user has reported this review
                    $reported = false;
                    if ($user_id) {
                        $report_stmt = $conn->prepare("SELECT id FROM review_reports WHERE review_id = ? AND reporter_id = ?");
                        $report_stmt->bind_param("ii", $review['id'], $user_id);
                        $report_stmt->execute();
                        $report_result = $report_stmt->get_result();
                        $reported = $report_result->num_rows > 0;
                    }
                    ?>
                    <div class="review-item">
                        <div class="review-header">
                            <div>
                                <span class="review-student"><?php echo htmlspecialchars($review['student_name']); ?></span>
                                <span class="review-subject"><?php echo htmlspecialchars($review['subject_name']); ?></span>
                                <span class="review-date">
                                    Posted on <?php echo date('F j, Y', strtotime($review['created_at'])); ?>
                                    <?php if (!empty($review['updated_at']) && $review['updated_at'] !== $review['created_at']): ?>
                                        <span class="separator">|</span> Edited on <?php echo date('F j, Y', strtotime($review['updated_at'])); ?>
                                    <?php endif; ?>
                                </span>
                            </div>
                        </div>
                        <div class="review-rating">
                            <?php
                            for ($i = 1; $i <= 5; $i++) {
                                echo $i <= $review['rating'] ? '★' : '☆';
                            }
                            ?>
                        </div>
                        <div class="review-comment"><?php echo htmlspecialchars($review['comment']); ?></div>

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

                        <!-- Report Button -->
                        <div class="review-actions">
                            <?php if ($reported): ?>
                                <span class="reported-indicator">Reported</span>
                            <?php else: ?>
                                <a href="report_review.php?review_id=<?php echo $review['id']; ?>&tutor_id=<?php echo $tutor_id; ?>" class="btn btn-danger">Report Review</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="no-reviews">This tutor hasn't received any reviews yet.</div>
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