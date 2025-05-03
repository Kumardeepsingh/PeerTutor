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

// Handle review deletion
if(isset($_POST['delete_review'])) {
    $review_id = $_POST['review_id'];
    
    // Delete the review
    $stmt = $conn->prepare("DELETE FROM reviews WHERE id = ?");
    $stmt->bind_param("i", $review_id);
    
    if($stmt->execute()) {
        // Also delete any reports associated with this review
        $stmt = $conn->prepare("DELETE FROM review_reports WHERE review_id = ?");
        $stmt->bind_param("i", $review_id);
        $stmt->execute();
        
        $success_message = "Review has been successfully removed.";
    } else {
        $error_message = "Error removing review. Please try again.";
    }
}

// Get filter parameter
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

// Build query based on filters
$query_conditions = [];
$query_params = [];
$param_types = "";

if($filter === 'reported') {
    $query_conditions[] = "r.id IN (SELECT review_id FROM review_reports)";
}

if($filter === 'low_rating') {
    $query_conditions[] = "r.rating <= 2";
}

// Combine conditions
$where_clause = "";
if(!empty($query_conditions)) {
    $where_clause = "WHERE " . implode(" AND ", $query_conditions);
}

// Get all reviews without pagination
$reviews_sql = "
    SELECT r.*, 
           u1.fullname as student_name, 
           u2.fullname as tutor_name,
           s.subject_id,
           subj.name as subject_name,
           (SELECT COUNT(*) FROM review_reports WHERE review_id = r.id) as report_count
    FROM reviews r
    JOIN users u1 ON r.student_id = u1.id
    JOIN users u2 ON r.tutor_id = u2.id
    JOIN sessions s ON r.session_id = s.id
    JOIN subjects subj ON s.subject_id = subj.id
    $where_clause
    ORDER BY r.created_at DESC
";

$stmt = $conn->prepare($reviews_sql);
if(!empty($query_params)) {
    $stmt->bind_param($param_types, ...$query_params);
}
$stmt->execute();
$reviews_result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Management - PeerTutor Admin</title>
    <link rel="stylesheet" href="styles/styles.css">
    <link rel="stylesheet" href="styles/dashboard-styles.css">
    <link rel="stylesheet" href="styles/admin-styles.css">
    <style>

        .review-card {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            position: relative;
        }
        
        .review-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }
        
        .review-info {
            flex: 1;
        }
        
        .review-rating {
            display: flex;
            align-items: center;
            margin: 0.5rem 0;
        }
        
        .review-rating .star {
            color: #ccc;
            font-size: 1.2rem;
            margin-right: 2px;
        }
        
        .review-rating .star.filled {
            color: #f1c40f;
        }
        
        .review-meta {
            display: flex;
            font-size: 0.85rem;
            color: #777;
            margin-bottom: 0.5rem;
        }
        
        .review-meta div {
            margin-right: 1rem;
        }
        
        .review-content {
            line-height: 1.6;
        }
        
        .review-actions form {
            display: inline;
        }
        
        .review-flags {
            display: flex;
            align-items: center;
            margin-top: 1rem;
            font-size: 0.85rem;
        }
        
        .review-flag {
            display: inline-flex;
            align-items: center;
            background-color: rgba(231, 76, 60, 0.1);
            color: #e74c3c;
            padding: 0.25rem 0.5rem;
            border-radius: 20px;
            margin-right: 0.5rem;
        }
        
        .review-flag span {
            margin-left: 0.25rem;
        }
        
        .filter-controls {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }
        
        .filter-controls .filter-group {
            flex: 1;
            min-width: 200px;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }
        
        .alert-success {
            background-color: rgba(46, 204, 113, 0.1);
            color: #2ecc71;
            border: 1px solid rgba(46, 204, 113, 0.2);
        }
        
        .alert-error {
            background-color: rgba(231, 76, 60, 0.1);
            color: #e74c3c;
            border: 1px solid rgba(231, 76, 60, 0.2);
        }
        
        .empty-message {
            text-align: center;
            padding: 3rem 1rem;
            background-color: #f9f9f9;
            border-radius: 8px;
            color: #777;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar dashboard-nav">
        <a href="admin_dashboard.php" class="logo">Peer<span>Tutor</span></a>
        <ul class="nav-links">
            <li><a href="admin_dashboard.php">Dashboard</a></li>
            <li><a href="admin_tutors.php">Tutors</a></li>
            <li><a href="admin_reports.php">Reports</a></li>
            <li><a href="admin_reviews.php" class="active">Reviews</a></li>
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
                    <li><a href="admin_reviews.php" class="active"><span>⭐</span> Review Management</a></li>
                </ul>
            </div>
        </aside>

        <!-- Main Dashboard Area -->
        <main class="dashboard-main">
            <h1>Review Management</h1>
            
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success">
                    <p><?php echo $success_message; ?></p>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error_message)): ?>
                <div class="alert alert-error">
                    <p><?php echo $error_message; ?></p>
                </div>
            <?php endif; ?>
            
            <!-- Filter Controls -->
            <div class="filter-controls">
                <div class="filter-group">
                    <form method="GET" action="">
                        <select name="filter" class="form-control" onchange="this.form.submit()">
                            <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>All Reviews</option>
                            <option value="reported" <?php echo $filter === 'reported' ? 'selected' : ''; ?>>Reported Reviews</option>
                            <option value="low_rating" <?php echo $filter === 'low_rating' ? 'selected' : ''; ?>>Low Ratings (≤ 2 stars)</option>
                        </select>
                    </form>
                </div>
            </div>
            
            <!-- Reviews List -->
            <div class="reviews-list">
                <?php if ($reviews_result->num_rows > 0): ?>
                    <?php while ($review = $reviews_result->fetch_assoc()): ?>
                        <div class="review-card">
                            <div class="review-header">
                                <div class="review-info">
                                    <h3><?php echo htmlspecialchars($review['tutor_name']); ?> (Tutor)</h3>
                                    <div class="review-rating">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <?php if ($i <= $review['rating']): ?>
                                                <span class="star filled">★</span>
                                            <?php else: ?>
                                                <span class="star">★</span>
                                            <?php endif; ?>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                                <div class="review-actions">
                                    <form method="POST" action="" onsubmit="return confirm('Are you sure you want to delete this review? This action cannot be undone.');">
                                        <input type="hidden" name="review_id" value="<?php echo $review['id']; ?>">
                                        <button type="submit" name="delete_review" class="btn danger small">Delete Review</button>
                                    </form>
                                </div>
                            </div>
                            
                            <div class="review-meta">
                                <div>By: <?php echo htmlspecialchars($review['student_name']); ?> (Student)</div>
                                <div>Subject: <?php echo htmlspecialchars($review['subject_name']); ?></div>
                                <div>Date: <?php echo date('M d, Y', strtotime($review['created_at'])); ?></div>
                            </div>
                            
                            <div class="review-content">
                                <p><?php echo nl2br(htmlspecialchars($review['comment'])); ?></p>
                            </div>
                            
                            <?php if ($review['tutor_comment']): ?>
                                <div class="tutor-response">
                                    <h4>Tutor Response:</h4>
                                    <p><?php echo nl2br(htmlspecialchars($review['tutor_comment'])); ?></p>
                                    <small>Posted on: <?php echo date('M d, Y', strtotime($review['tutor_comment_date'])); ?></small>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($review['report_count'] > 0): ?>
                                <div class="review-flags">
                                    <div class="review-flag">
                                        🚩 <span>Reported <?php echo $review['report_count']; ?> time<?php echo $review['report_count'] > 1 ? 's' : ''; ?></span>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-message">
                        <h3>No reviews found</h3>
                        <p>There are no reviews matching your criteria at this time.</p>
                    </div>
                <?php endif; ?>
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