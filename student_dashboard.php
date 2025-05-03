<?php session_start();

    // Start session and check if user is logged in as a student
    if(!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'student'){
        header("Location: login.php?error=Please login to access the student dashboard");
        exit();
    } 
    
    // Include database connection
    require_once 'db_connect.php';
    ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - PeerTutor</title>
    <link rel="stylesheet" href="styles/styles.css">
    <link rel="stylesheet" href="styles/dashboard-styles.css">
    <link rel="stylesheet" href="styles/student-dashboard-styles.css">

</head>
<body>
    
    <?php
    // Get student information
    $user_id = $_SESSION['user_id'];
    $fullname = $_SESSION['fullname'];

    // Get student profile
    $stmt = $conn->prepare("SELECT * FROM student_profiles WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $student_result = $stmt->get_result();
    $student_profile = $student_result->fetch_assoc();

    // Get verification status
    $stmt = $conn->prepare("SELECT is_verified FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $verification_result = $stmt->get_result();
    $verification_status = $verification_result->fetch_assoc();
    $is_verified = $verification_status['is_verified'];
    
    // Get upcoming sessions
    $stmt = $conn->prepare("
    SELECT s.*, u.fullname as tutor_name, subj.name as subject_name 
    FROM sessions s 
    JOIN users u ON s.tutor_id = u.id 
    JOIN subjects subj ON s.subject_id = subj.id
    WHERE s.student_id = ? AND (s.status = 'scheduled' OR s.status = 'pending') AND s.session_date >= CURDATE()
    ORDER BY s.session_date ASC, s.start_time ASC
    LIMIT 5"
    );
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $upcoming_sessions = $stmt->get_result();

    // Get past sessions
    $stmt = $conn->prepare("
        SELECT s.*, u.fullname as tutor_name, subj.name as subject_name 
        FROM sessions s 
        JOIN users u ON s.tutor_id = u.id 
        JOIN subjects subj ON s.subject_id = subj.id
        WHERE s.student_id = ? AND (s.status = 'completed' OR (s.status = 'scheduled' AND s.session_date < CURDATE()))
        ORDER BY s.session_date DESC, s.start_time DESC
        LIMIT 5
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $past_sessions = $stmt->get_result();

    // Check for sessions needing review
    $stmt = $conn->prepare("
        SELECT s.id as session_id
        FROM sessions s
        LEFT JOIN reviews r ON s.id = r.session_id
        WHERE s.student_id = ? AND s.status = 'completed' AND r.id IS NULL
        LIMIT 1
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $needs_review_result = $stmt->get_result();
    $has_session_to_review = $needs_review_result->num_rows > 0;

    // Count total completed sessions
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total_sessions
        FROM sessions
        WHERE student_id = ? AND status = 'completed'
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $sessions_result = $stmt->get_result();
    $sessions_data = $sessions_result->fetch_assoc();
    $total_sessions = $sessions_data['total_sessions'];

    // Get recent tutors (from completed sessions)
    $stmt = $conn->prepare("
        SELECT DISTINCT u.id, u.fullname, tp.hourly_rate, 
        (SELECT AVG(rating) FROM reviews WHERE tutor_id = u.id) as avg_rating,
        (SELECT COUNT(*) FROM reviews WHERE tutor_id = u.id) as review_count,
        s.subject_id, subj.name as subject_name
        FROM sessions s
        JOIN users u ON s.tutor_id = u.id
        JOIN tutor_profiles tp ON u.id = tp.user_id
        JOIN subjects subj ON s.subject_id = subj.id
        WHERE s.student_id = ? AND s.status = 'completed'
        ORDER BY s.session_date DESC
        LIMIT 3
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $recent_tutors = $stmt->get_result();
    ?>

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
                <p class="student-info">
                    <?php if (!empty($student_profile['school'])): ?>
                        <?php echo htmlspecialchars($student_profile['school']); ?><br>
                    <?php endif; ?>
                    <?php if (!empty($student_profile['grade_level'])): ?>
                        Grade: <?php echo htmlspecialchars($student_profile['grade_level']); ?>
                    <?php endif; ?>
                </p>
                <a href="student_profile.php" class="btn secondary small">Edit Profile</a>
            </div>
            <div class="sidebar-menu">
                <ul>
                    <li><a href="find_tutors.php"><span>🔍</span> Find Tutors</a></li>
                    <li><a href="my_sessions.php"><span>📅</span> My Sessions</a></li>
                    <li><a href="student_reviews.php"><span>⭐</span> My Reviews</a></li>
                </ul>
            </div>
        </aside>

        <!-- Main Dashboard Area -->
        <main class="dashboard-main">
            <h1>Student Dashboard</h1>
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
            
            <!-- Quick Actions -->
            <section class="quick-actions">
                <a href="find_tutors.php" class="action-card">
                <div class="action-icon">🔍</div>
                <div class="action-text">Find a Tutor</div>
                </a>
            </section>

            <div class="stats-summary">
                <div class="stat-card">
                    <div class="stat-icon">✓</div>
                    <div class="stat-content">
                        <h3><?php echo $total_sessions; ?></h3>
                        <p>Completed Sessions</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">📅</div>
                    <div class="stat-content">
                        <h3><?php echo $upcoming_sessions->num_rows; ?></h3>
                        <p>Sessions Booked</p>
                    </div>
                </div>
            </div>

            <section class="dashboard-section">
    <div class="section-header">
        <h2>Sessions Booked</h2>
        <a href="my_sessions.php" class="view-all">View All</a>
    </div>
    
    <div class="section-content">
        <?php if ($upcoming_sessions->num_rows > 0): ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Tutor</th>
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
                            <td><?php echo htmlspecialchars($session['tutor_name']); ?></td>
                            <td><?php echo htmlspecialchars($session['subject_name']); ?></td>
                            <td>
                                <?php if ($session['status'] == 'pending'): ?>
                                    <span class="status status-pending">Awaiting Approval</span>
                                <?php elseif ($session['status'] == 'scheduled'): ?>
                                    <span class="status status-approved">Approved</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($session['status'] == 'scheduled' && !empty($session['zoom_join_url'])): ?>
                                    <a href="<?php echo htmlspecialchars($session['zoom_join_url']); ?>" target="_blank" class="btn small">Join</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="empty-state">
                <p>You have no upcoming sessions scheduled.</p>
                <a href="find_tutors.php" class="btn secondary small">Find a Tutor</a>
            </div>
        <?php endif; ?>
    </div>
</section>
                <!-- Recent Tutors Section -->
                <section class="dashboard-section">
                    <div class="section-header">
                        <h2>Your Recent Tutors</h2>
                        <a href="find_tutors.php?filter=previous" class="view-all">Find More</a>
                    </div>
                    
                    <div class="section-content">
                        <?php if ($recent_tutors->num_rows > 0): ?>
                            <div class="tutors-grid">
                                <?php while ($tutor = $recent_tutors->fetch_assoc()): ?>
                                    <div class="tutor-card">
                                        <div class="tutor-info">
                                            <h3><?php echo htmlspecialchars($tutor['fullname']); ?></h3>
                                            <p class="tutor-subject"><?php echo htmlspecialchars($tutor['subject_name']); ?></p>
                                            <div class="tutor-rating">
                                                <?php 
                                                $avg_rating = $tutor['avg_rating'] ? round($tutor['avg_rating'], 1) : 0;
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
                                                <span class="rating-count">(<?php echo $tutor['review_count']; ?>)</span>
                                            </div>
                                            <p class="tutor-rate">$<?php echo number_format($tutor['hourly_rate'], 2); ?>/hour</p>
                                        </div>
                                        <div class="tutor-actions">
                                            <a href="book_session.php?tutor_id=<?php echo $tutor['id']; ?>&subject_id=<?php echo $tutor['subject_id']; ?>" class="btn small">Book Again</a>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <p>You haven't had any sessions with tutors yet.</p>
                                <a href="find_tutors.php" class="btn secondary small">Find a Tutor</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>

                
                <!-- Past Sessions Section -->
                <section class="dashboard-section">
                    <div class="section-header">
                        <h2>Past Sessions</h2>
                        <a href="my_sessions.php?filter=past" class="view-all">View All</a>
                    </div>
                    
                    <div class="section-content">
                        <?php if ($past_sessions->num_rows > 0): ?>
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Tutor</th>
                                        <th>Subject</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($session = $past_sessions->fetch_assoc()): 
                                        // Check if session has a review
                                        $stmt = $conn->prepare("SELECT id FROM reviews WHERE session_id = ?");
                                        $stmt->bind_param("i", $session['id']);
                                        $stmt->execute();
                                        $review_result = $stmt->get_result();
                                        $has_review = $review_result->num_rows > 0;
                                    ?>
                                        <tr>
                                            <td><?php echo date('M d, Y', strtotime($session['session_date'])); ?></td>
                                            <td><?php echo htmlspecialchars($session['tutor_name']); ?></td>
                                            <td><?php echo htmlspecialchars($session['subject_name']); ?></td>
                                            <td>
                                                <?php if ($session['status'] == 'completed'): ?>
                                                    <span class="status status-completed">Completed</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($session['status'] == 'completed' && !$has_review): ?>
                                                    <a href="rate_tutor.php?tutor_id=<?php echo $session['tutor_id']; ?>" class="btn secondary small">Review</a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div class="empty-state">
                                <p>You don't have any past sessions yet.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>
            </div>
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