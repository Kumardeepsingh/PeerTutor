<?php session_start();

// Check if user is logged in as admin
if(!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'admin'){
    header("Location: login.php?error=Please login as an administrator to access this page");
    exit();
} 

// Include database connection
require_once 'db_connect.php';

// Get admin information
$user_id = $_SESSION['user_id'];
$fullname = $_SESSION['fullname'];

// Get pending tutor applications count
$stmt = $conn->prepare("
    SELECT COUNT(*) as pending_tutors 
    FROM tutor_profiles 
    WHERE status = 'pending'
");
$stmt->execute();
$pending_result = $stmt->get_result();
$pending_data = $pending_result->fetch_assoc();
$pending_tutors = $pending_data['pending_tutors'];

// Get pending review reports count
$stmt = $conn->prepare("
    SELECT COUNT(*) as pending_review_reports 
    FROM review_reports 
    WHERE status = 'pending'
");
$stmt->execute();
$reports_result = $stmt->get_result();
$reports_data = $reports_result->fetch_assoc();
$pending_review_reports = $reports_data['pending_review_reports'];

// Get pending tutor reports count
$stmt = $conn->prepare("
    SELECT COUNT(*) as pending_tutor_reports 
    FROM tutor_reports 
    WHERE status = 'pending'
");
$stmt->execute();
$tutor_reports_result = $stmt->get_result();
$tutor_reports_data = $tutor_reports_result->fetch_assoc();
$pending_tutor_reports = $tutor_reports_data['pending_tutor_reports'];

// Get recent tutor applications
$stmt = $conn->prepare("
    SELECT tp.*, u.fullname, u.email
    FROM tutor_profiles tp
    JOIN users u ON tp.user_id = u.id
    WHERE tp.status = 'pending'
    ORDER BY tp.id DESC
    LIMIT 5
");
$stmt->execute();
$recent_applications = $stmt->get_result();

// Get recent reports
$stmt = $conn->prepare("
    SELECT rr.*, r.rating, r.comment, 
           u1.fullname as reviewer_name, 
           u2.fullname as reported_by
    FROM review_reports rr
    JOIN reviews r ON rr.review_id = r.id
    JOIN users u1 ON r.student_id = u1.id
    JOIN users u2 ON rr.reporter_id = u2.id
    WHERE rr.status = 'pending'
    ORDER BY rr.created_at DESC
    LIMIT 5
");
$stmt->execute();
$recent_review_reports = $stmt->get_result();

// Get recent tutor reports
$stmt = $conn->prepare("
    SELECT tr.*, 
           u1.fullname as tutor_name, 
           u2.fullname as reporter_name,
           s.session_date, s.start_time, s.end_time
    FROM tutor_reports tr
    JOIN users u1 ON tr.tutor_id = u1.id
    JOIN users u2 ON tr.reporter_id = u2.id
    LEFT JOIN sessions s ON tr.session_id = s.id
    WHERE tr.status = 'pending'
    ORDER BY tr.created_at DESC
    LIMIT 5
");
$stmt->execute();
$recent_tutor_reports = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - PeerTutor</title>
    <link rel="stylesheet" href="styles/styles.css">
    <link rel="stylesheet" href="styles/dashboard-styles.css">
    <link rel="stylesheet" href="styles/admin-styles.css">
    <style>

.admin-badge {
    background-color: rgba(142, 68, 173, 0.1);
    color: #8e44ad;
    display: inline-block;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    margin: 0.5rem 0;
}

.alert-icon {
    background-color: rgba(52, 152, 219, 0.1);
    color: var(--primary);
}

.warning-icon {
    background-color: rgba(243, 156, 18, 0.1);
    color: var(--warning);
}

.danger-icon {
    background-color: rgba(231, 76, 60, 0.1);
    color: var(--danger);
}

.rating-stars {
    display: flex;
}

.dashboard-nav .nav-links a.active {
    color: var(--primary);
    font-weight: 600;
}

.data-table {
    font-size: 0.9rem;
}

.data-table th {
    background-color: rgba(52, 152, 219, 0.05);
}

.btn.small {
    font-size: 0.8rem;
    padding: 0.25rem 0.5rem;
}
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar dashboard-nav">
        <a href="admin_dashboard.php" class="logo">Peer<span>Tutor</span></a>
        <ul class="nav-links">
            <li><a href="admin_dashboard.php" class="active">Dashboard</a></li>
            <li><a href="admin_tutors.php">Tutors</a></li>
            <li><a href="admin_reports.php">Reports</a></li>
            <li><a href="logout.php" class="btn secondary">Logout</a></li>
        </ul>
        <div class="user-greeting">
            <p>Welcome, Admin <?php echo htmlspecialchars($fullname); ?></p>
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
                <p class="status-badge admin-badge">Administrator</p>
            </div>
            <div class="sidebar-menu">
                <ul>
                    <li><a href="admin_tutors.php"><span>👨‍🏫</span> Manage Tutors</a></li>
                    <li><a href="admin_reports.php"><span>🚩</span> Review Reports</a></li>
                    <li><a href="admin_reviews.php"><span>⭐</span> Review Management</a></li>
                </ul>
            </div>
        </aside>

        <!-- Main Dashboard Area -->
        <main class="dashboard-main">
            <h1>Admin Dashboard</h1>
            
            <div class="stats-summary">
                <div class="stat-card">
                    <div class="stat-icon alert-icon">👨‍🏫</div>
                    <div class="stat-content">
                        <h3><?php echo $pending_tutors; ?></h3>
                        <p>Pending Tutors</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon warning-icon">🚩</div>
                    <div class="stat-content">
                        <h3><?php echo $pending_review_reports; ?></h3>
                        <p>Review Reports</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon danger-icon">⚠️</div>
                    <div class="stat-content">
                        <h3><?php echo $pending_tutor_reports; ?></h3>
                        <p>Tutor Reports</p>
                    </div>
                </div>
            </div>

            <div class="dashboard-sections">
                <!-- Pending Tutor Applications -->
                <section class="dashboard-section">
                    <div class="section-header">
                        <h2>Pending Tutor Applications</h2>
                        <a href="admin_tutors.php?filter=pending" class="view-all">View All</a>
                    </div>
                    
                    <div class="section-content">
                        <?php if ($recent_applications->num_rows > 0): ?>
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Education</th>
                                        <th>Rate</th>
                                        <th>Applied On</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($application = $recent_applications->fetch_assoc()): 
                                        // Convert user_id to timestamp to estimate application date
                                        $application_date = date('M d, Y', $application['id'] / 1000);
                                    ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($application['fullname']); ?></td>
                                            <td><?php echo htmlspecialchars($application['email']); ?></td>
                                            <td><?php echo htmlspecialchars($application['education']); ?></td>
                                            <td>$<?php echo number_format($application['hourly_rate'], 2); ?>/hr</td>
                                            <td><?php echo $application_date; ?></td>
                                            <td>
                                                <a href="tutor_application.php?id=<?php echo $application['id']; ?>" class="btn small">Review</a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div class="empty-state">
                                <p>There are no pending tutor applications.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>

                <!-- Reported Reviews -->
                <section class="dashboard-section">
                    <div class="section-header">
                        <h2>Reported Reviews</h2>
                        <a href="admin_reports.php?type=reviews" class="view-all">View All</a>
                    </div>
                    
                    <div class="section-content">
                        <?php if ($recent_review_reports->num_rows > 0): ?>
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Reported Review</th>
                                        <th>Rating</th>
                                        <th>Reason</th>
                                        <th>Reported By</th>
                                        <th>Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($report = $recent_review_reports->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars(substr($report['comment'], 0, 50)) . (strlen($report['comment']) > 50 ? '...' : ''); ?></td>
                                            <td>
                                                <div class="rating-stars">
                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                        <?php if ($i <= $report['rating']): ?>
                                                            <span class="star filled">★</span>
                                                        <?php else: ?>
                                                            <span class="star">★</span>
                                                        <?php endif; ?>
                                                    <?php endfor; ?>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($report['reason']); ?></td>
                                            <td><?php echo htmlspecialchars($report['reported_by']); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($report['created_at'])); ?></td>
                                            <td>
                                                <a href="admin_reviews.php?id=<?php echo $report['id']; ?>" class="btn small">Handle</a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div class="empty-state">
                                <p>There are no pending review reports.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>

                <!-- Reported Tutors -->
                <section class="dashboard-section">
                    <div class="section-header">
                        <h2>Reported Tutors</h2>
                        <a href="admin_reports.php?type=tutors" class="view-all">View All</a>
                    </div>
                    
                    <div class="section-content">
                        <?php if ($recent_tutor_reports->num_rows > 0): ?>
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Tutor</th>
                                        <th>Reported By</th>
                                        <th>Reason</th>
                                        <th>Session Date</th>
                                        <th>Date Reported</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($report = $recent_tutor_reports->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($report['tutor_name']); ?></td>
                                            <td><?php echo htmlspecialchars($report['reporter_name']); ?></td>
                                            <td><?php echo htmlspecialchars($report['reason']); ?></td>
                                            <td>
                                                <?php if ($report['session_date']): ?>
                                                    <?php echo date('M d, Y', strtotime($report['session_date'])); ?>
                                                <?php else: ?>
                                                    N/A
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($report['created_at'])); ?></td>
                                            <td>
                                                <a href="admin_reports.php?id=<?php echo $report['id']; ?>" class="btn small">Handle</a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div class="empty-state">
                                <p>There are no pending tutor reports.</p>
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