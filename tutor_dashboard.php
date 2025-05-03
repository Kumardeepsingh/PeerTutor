<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tutor Dashboard - PeerTutor</title>
    <link rel="stylesheet" href="styles/styles.css">
    <link rel="stylesheet" href="styles/dashboard-styles.css">
</head>
<body>
    <?php session_start();

    // Start session and check if user is logged in as a tutor

    if(!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'tutor'){
        header("Location: login.php?error=Please login to access the tutor dashboard");
        exit();
    } 
    
     // Include database connection
     require_once 'db_connect.php';

     // Get tutor information
    $user_id = $_SESSION['user_id'];
    $fullname = $_SESSION['fullname'];

    // Get tutor profile
    $stmt = $conn->prepare("SELECT * FROM tutor_profiles WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $tutor_result = $stmt->get_result();
    $tutor_profile = $tutor_result->fetch_assoc();

    // Get verification status
    $stmt = $conn->prepare("SELECT is_verified FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $verification_result = $stmt->get_result();
    $verification_status = $verification_result->fetch_assoc();
    $is_verified = $verification_status['is_verified'];
        
    // Get upcoming sessions
    $stmt = $conn->prepare("
    SELECT s.*, u.fullname as student_name, subj.name as subject_name 
    FROM sessions s 
    JOIN users u ON s.student_id = u.id 
    JOIN subjects subj ON s.subject_id = subj.id
    WHERE s.tutor_id = ? AND (s.status = 'scheduled' OR s.status = 'pending') AND s.session_date >= CURDATE()
    ORDER BY s.session_date ASC, s.start_time ASC
    LIMIT 5
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$upcoming_sessions = $stmt->get_result();

    // Get recent reviews
    $stmt = $conn->prepare("
        SELECT r.*, u.fullname as student_name, s.session_date, subj.name as subject_name 
        FROM reviews r
        JOIN sessions s ON r.session_id = s.id
        JOIN users u ON r.student_id = u.id
        JOIN subjects subj ON s.subject_id = subj.id
        WHERE r.tutor_id = ?
        ORDER BY r.created_at DESC
        LIMIT 3
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $recent_reviews = $stmt->get_result();

    // Get tutor subjects
    $stmt = $conn->prepare("
        SELECT s.name, s.category, ts.proficiency_level
        FROM tutor_subjects ts
        JOIN subjects s ON ts.subject_id = s.id
        JOIN tutor_profiles tp ON ts.tutor_profile_id = tp.id
        WHERE tp.user_id = ?
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $tutor_subjects = $stmt->get_result();

    // Calculate average rating
    $stmt = $conn->prepare("
        SELECT AVG(rating) as avg_rating, COUNT(*) as total_reviews
        FROM reviews
        WHERE tutor_id = ?
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $rating_result = $stmt->get_result();
    $rating_data = $rating_result->fetch_assoc();
    $avg_rating = round($rating_data['avg_rating'], 1);
    $total_reviews = $rating_data['total_reviews'];

    // Count total completed sessions
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total_sessions
        FROM sessions
        WHERE tutor_id = ? AND status = 'completed'
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $sessions_result = $stmt->get_result();
    $sessions_data = $sessions_result->fetch_assoc();
    $total_sessions = $sessions_data['total_sessions'];
    ?>

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
        <div class="user-greeting">
            <p>Welcome, <?php echo htmlspecialchars($fullname); ?></p>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="dashboard-sidebar">
            <div class="profile-summary">
                <div class="profile-image">
                    <?php if (isset($_SESSION['profile_image']) && !empty($_SESSION['profile_image'])): ?>
                        <img src="<?php echo htmlspecialchars($_SESSION['profile_image']); ?>" alt="Profile Picture">
                    <?php else: ?>
                        <div class="profile-initial"><?php echo substr(htmlspecialchars($fullname), 0, 1); ?></div>
                    <?php endif; ?>
                </div>
                <h3><?php echo htmlspecialchars($fullname); ?></h3>
                <p class="approval-status status-<?php echo htmlspecialchars($tutor_profile['status']); ?>">
                    <?php echo ucfirst(htmlspecialchars($tutor_profile['status'])); ?>
                </p>
                <div class="rating-summary">
                    <div class="stars">
                        <?php
                        for ($i = 1; $i <= 5; $i++) {
                            if ($i <= floor($avg_rating)) {
                                echo '<span class="star filled">★</span>';
                            } elseif ($i - 0.5 <= $avg_rating) {
                                echo '<span class="star half">★</span>';
                            } else {
                                echo '<span class="star">★</span>';
                            }
                        }
                        ?>
                    </div>
                    <p><?php echo $avg_rating; ?> (<?php echo $total_reviews; ?> reviews)</p>
                </div>
                <p class="hourly-rate">$<?php echo number_format($tutor_profile['hourly_rate'], 2); ?>/hour</p>
                <a href="tutor_profile.php" class="btn secondary small">Edit Profile</a>
            </div>
            <div class="sidebar-menu">
                <ul>
                    <li><a href="my_sessions.php"><span>📅</span> My Sessions</a></li>
                    <li><a href="tutor_availability.php"><span>⏰</span> Set Availability</a></li>
                    <li><a href="tutor_reviews.php"><span>⭐</span> My Reviews</a></li>
                </ul>
            </div>
        </aside>

        <!-- Main Dashboard Area -->
        <main class="dashboard-main">
            <h1>Tutor Dashboard</h1>
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
            <?php endif; ?>
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
            <?php endif; ?>
            <!-- Verification Status Banner -->
            <?php if (!$is_verified): ?>
            <div class="verification-banner">
                <p>Your email is not yet verified. Please check your inbox for the verification link.</p>
                <a href="resend_verification.php" class="btn small">Resend Verification Email</a>
            </div>
            <?php endif; ?>
            
            <?php if ($tutor_profile['status'] === 'pending'): ?>
                <div class="alert alert-info">
                    <p><strong>Your tutor application is pending review.</strong> Once approved, you'll be able to receive session requests and start tutoring.</p>
                </div>
            <?php elseif ($tutor_profile['status'] === 'rejected'): ?>
                <div class="alert alert-danger">
                    <p><strong>Your tutor application was not approved.</strong> Please update your profile with more information about your qualifications and experience.</p>
                </div>
            <?php elseif ($tutor_profile['status'] === 'suspended'): ?>
                <div class="alert alert-danger">
                    <p><strong>Your tutor application has been suspended.</strong> Please contact us at peertutor.webapp@gmail.com for further details</p>
                </div>
            <?php endif; ?>
            <div class="stats-summary">
                <div class="stat-card">
                    <div class="stat-icon">📚</div>
                    <div class="stat-content">
                        <h3><?php echo $tutor_subjects->num_rows; ?></h3>
                        <p>Subjects</p>
                    </div>
                    </div>
                <div class="stat-card">
                    <div class="stat-icon">✓</div>
                    <div class="stat-content">
                        <h3><?php echo $total_sessions; ?></h3>
                        <p>Completed Sessions</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">⭐</div>
                    <div class="stat-content">
                        <h3><?php echo $avg_rating; ?></h3>
                        <p>Average Rating</p>
                    </div>
                </div>
            </div>
                <div class="dashboard-sections">
               <!-- Upcoming Sessions Section -->
<section class="dashboard-section">
    <div class="section-header">
        <h2>Upcoming Sessions</h2>
        <a href="my_sessions.php" class="view-all">View All</a>
    </div>
    
    <div class="section-content">
        <?php if ($upcoming_sessions->num_rows > 0): ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Student</th>
                        <th>Subject</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($session = $upcoming_sessions->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo date('M d, Y', strtotime($session['session_date'])); ?></td>
                            <td><?php echo date('h:i A', strtotime($session['start_time'])); ?> - 
                                <?php echo date('h:i A', strtotime($session['end_time'])); ?></td>
                            <td><?php echo htmlspecialchars($session['student_name']); ?></td>
                            <td><?php echo htmlspecialchars($session['subject_name']); ?></td>
                            <td>
                                <?php if ($session['status'] == 'pending'): ?>
                                    <span class="status status-pending">Awaiting Approval</span>
                                <?php elseif ($session['status'] == 'scheduled'): ?>
                                    <span class="status status-approved">Approved</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($session['status'] == 'pending'): ?>
                                <?php elseif ($session['status'] == 'scheduled' && !empty($session['zoom_start_url'])): ?>
                                    <a href="<?php echo htmlspecialchars($session['zoom_start_url']); ?>" target="_blank" class="btn small">Join</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="empty-state">
                <p>You have no upcoming sessions scheduled.</p>
                <a href="tutor_availability.php" class="btn secondary small">Update Availability</a>
            </div>
        <?php endif; ?>
    </div>
</section>

                <!-- Recent Reviews Section -->
                <section class="dashboard-section">
                    <div class="section-header">
                        <h2>Recent Reviews</h2>
                        <a href="tutor_reviews.php" class="view-all">View All</a>
                    </div>
                    
                    <div class="section-content">
                        <?php if ($recent_reviews->num_rows > 0): ?>
                            <div class="reviews-container">
                                <?php while ($review = $recent_reviews->fetch_assoc()): ?>
                                    <div class="review-card">
                                        <div class="review-header">
                                            <div class="review-student"><?php echo htmlspecialchars($review['student_name']); ?></div>
                                            <div class="review-date"><?php echo date('M d, Y', strtotime($review['created_at'])); ?></div>
                                        </div>
                                        <div class="review-subject">
                                            Subject: <?php echo htmlspecialchars($review['subject_name']); ?>
                                        </div>
                                        <div class="review-rating">
                                            <?php
                                            for ($i = 1; $i <= 5; $i++) {
                                                if ($i <= $review['rating']) {
                                                    echo '<span class="star filled">★</span>';
                                                } else {
                                                    echo '<span class="star">★</span>';
                                                }
                                            }
                                            ?>
                                        </div>
                                        <div class="review-comment">
                                            <?php echo htmlspecialchars($review['comment']); ?>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <p>You don't have any reviews yet.</p>
                                <p>Complete sessions to receive reviews from students.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>

                <!-- My Subjects Section -->
                <section class="dashboard-section">
                    <div class="section-header">
                        <h2>My Subjects</h2>
                        <a href="tutor_profile.php" class="view-all">Edit Subjects</a>
                    </div>
                    
                    <div class="section-content">
                        <?php if ($tutor_subjects->num_rows > 0): ?>
                            <div class="subjects-container">
                                <?php 
                                $categories = [];
                                while ($subject = $tutor_subjects->fetch_assoc()) {
                                    $categories[$subject['category']][] = $subject;
                                }
                                
                                foreach ($categories as $category => $subjects): ?>
                                    <div class="subject-category">
                                        <h3><?php echo htmlspecialchars($category); ?></h3>
                                        <div class="subject-list">
                                            <?php foreach ($subjects as $subject): ?>
                                                <div class="subject-badge <?php echo strtolower($subject['proficiency_level']); ?>">
                                                    <?php echo htmlspecialchars($subject['name']); ?>
                                                    <span class="proficiency"><?php echo ucfirst($subject['proficiency_level']); ?></span>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <p>You haven't added any subjects yet.</p>
                                <a href="tutor_subjects.php" class="btn secondary small">Add Subjects</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>
        </main>
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