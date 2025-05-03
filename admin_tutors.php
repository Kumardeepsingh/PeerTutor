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

// Handle filter parameters
$status_filter = isset($_GET['filter']) ? $_GET['filter'] : 'pending';
$valid_filters = ['pending', 'approved', 'rejected', 'all'];
if (!in_array($status_filter, $valid_filters)) {
    $status_filter = 'pending'; // Default to pending if invalid filter
}

// Prepare query based on status filter
if ($status_filter === 'all') {
    $query = "
        SELECT tp.*, u.fullname, u.email, u.profile_image
        FROM tutor_profiles tp
        JOIN users u ON tp.user_id = u.id
        ORDER BY 
            CASE 
                WHEN tp.status = 'pending' THEN 1
                WHEN tp.status = 'approved' THEN 2
                WHEN tp.status = 'rejected' THEN 3
            END,
            tp.id DESC
    ";
} else {
    $query = "
        SELECT tp.*, u.fullname, u.email, u.profile_image
        FROM tutor_profiles tp
        JOIN users u ON tp.user_id = u.id
        WHERE tp.status = ?
        ORDER BY tp.id DESC
    ";
}

$stmt = $conn->prepare($query);

// Bind parameters if filtering by status
if ($status_filter !== 'all') {
    $stmt->bind_param("s", $status_filter);
}

$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Tutors - PeerTutor</title>
    <link rel="stylesheet" href="styles/styles.css">
    <link rel="stylesheet" href="styles/dashboard-styles.css">
    <link rel="stylesheet" href="styles/admin-styles.css">
    <style>

        .filter-tabs {
            display: flex;
            margin-bottom: 1.5rem;
            border-bottom: 1px solid var(--border);
        }
        
        .filter-tab {
            padding: 0.75rem 1.5rem;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            font-weight: 500;
            color: var(--gray);
            text-decoration: none;
        }
        
        .filter-tab:hover {
            color: var(--primary);
        }
        
        .filter-tab.active {
            color: var(--primary);
            border-bottom-color: var(--primary);
        }
        
        .tutor-card {
            display: flex;
            background-color: white;
            border-radius: 10px;
            box-shadow: var(--shadow);
            padding: 1.5rem;
            margin-bottom: 1rem;
        }
        
        .tutor-card .profile-image {
            width: 80px;
            height: 80px;
            margin-right: 1.5rem;
        }
        
        .tutor-info {
            flex: 1;
        }
        
        .tutor-info h3 {
            margin: 0 0 0.5rem 0;
            color: var(--dark);
        }
        
        .tutor-meta {
            margin-bottom: 0.5rem;
            color: var(--gray);
        }
        
        .tutor-actions {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 0.75rem;
        }
        
        .btn.view {
            background-color: var(--primary);
            color: white;
        }
        
        .btn.view:hover {
            background-color: #2980b9;
        }
        
        .btn.small {
            font-size: 0.9rem;
            padding: 0.4rem 0.75rem;
        }
        
        .no-tutors {
            background-color: white;
            border-radius: 10px;
            box-shadow: var(--shadow);
            padding: 2rem;
            text-align: center;
            color: var(--gray);
        }
        
        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-right: 0.5rem;
        }
        
        .status-pending {
            background-color: rgba(243, 156, 18, 0.1);
            color: var(--warning);
        }
        
        .status-approved {
            background-color: rgba(46, 204, 113, 0.1);
            color: var(--success);
        }
        
        .status-rejected {
            background-color: rgba(231, 76, 60, 0.1);
            color: var(--danger);
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar dashboard-nav">
        <a href="admin_dashboard.php" class="logo">Peer<span>Tutor</span></a>
        <ul class="nav-links">
            <li><a href="admin_dashboard.php">Dashboard</a></li>
            <li><a href="admin_tutors.php" class="active">Tutors</a></li>
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
                    <li><a href="admin_tutors.php" class="active"><span>👨‍🏫</span> Manage Tutors</a></li>
                    <li><a href="admin_reports.php"><span>🚩</span> Review Reports</a></li>
                    <li><a href="admin_reviews.php"><span>⭐</span> Review Management</a></li>
                </ul>
            </div>
        </aside>

        <!-- Main Dashboard Area -->
        <main class="dashboard-main">
            <h1>Manage Tutors</h1>
            
            <?php if(isset($_GET['success'])): ?>
                <div class="alert alert-success">
                    <p><?php echo htmlspecialchars($_GET['success']); ?></p>
                </div>
            <?php endif; ?>
            
            <?php if(isset($_GET['error'])): ?>
                <div class="alert alert-danger">
                    <p><?php echo htmlspecialchars($_GET['error']); ?></p>
                </div>
            <?php endif; ?>
            
            <div class="filter-tabs">
                <a href="admin_tutors.php?filter=pending" class="filter-tab <?php echo $status_filter === 'pending' ? 'active' : ''; ?>">
                    Pending
                </a>
                <a href="admin_tutors.php?filter=approved" class="filter-tab <?php echo $status_filter === 'approved' ? 'active' : ''; ?>">
                    Approved
                </a>
                <a href="admin_tutors.php?filter=rejected" class="filter-tab <?php echo $status_filter === 'rejected' ? 'active' : ''; ?>">
                    Rejected
                </a>
                <a href="admin_tutors.php?filter=all" class="filter-tab <?php echo $status_filter === 'all' ? 'active' : ''; ?>">
                    All Tutors
                </a>
            </div>
            
            <?php if ($result->num_rows > 0): ?>
                <div class="tutors-list">
                    <?php while ($tutor = $result->fetch_assoc()): ?>
                        <div class="tutor-card">
                            <div class="profile-image">
                                <?php if (!empty($tutor['profile_image'])): ?>
                                    <img src="<?php echo htmlspecialchars($tutor['profile_image']); ?>" alt="Profile Picture">
                                <?php else: ?>
                                    <div class="profile-initial"><?php echo substr(htmlspecialchars($tutor['fullname']), 0, 1); ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="tutor-info">
                                <h3><?php echo htmlspecialchars($tutor['fullname']); ?></h3>
                                <div class="tutor-meta">
                                    <span class="status-badge status-<?php echo $tutor['status']; ?>">
                                        <?php echo ucfirst($tutor['status']); ?>
                                    </span>
                                    <span><strong>Email:</strong> <?php echo htmlspecialchars($tutor['email']); ?></span>
                                </div>
                                <p>
                                    <strong>Education:</strong> <?php echo htmlspecialchars($tutor['education'] ?: 'Not specified'); ?><br>
                                    <strong>Rate:</strong> $<?php echo number_format($tutor['hourly_rate'], 2); ?>/hour
                                </p>
                            </div>
                            <div class="tutor-actions">
                                <a href="tutor_application.php?id=<?php echo $tutor['id']; ?>" class="btn view small">Review Application</a>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="no-tutors">
                    <?php if ($status_filter === 'pending'): ?>
                        <p>There are no pending tutor applications at the moment.</p>
                    <?php elseif ($status_filter === 'approved'): ?>
                        <p>There are no approved tutors at the moment.</p>
                    <?php elseif ($status_filter === 'rejected'): ?>
                        <p>There are no rejected tutor applications at the moment.</p>
                    <?php else: ?>
                        <p>There are no tutor applications in the system.</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
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