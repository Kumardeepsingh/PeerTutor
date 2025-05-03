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

// Set default filter value
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'pending';

// Handle report actions
if (isset($_POST['action']) && isset($_POST['report_id'])) {
    $report_id = $_POST['report_id'];
    $action = $_POST['action'];
    
    // Handle tutor report actions
    if ($action === 'dismiss') {
        // Update report status to reviewed
        $stmt = $conn->prepare("UPDATE tutor_reports SET status = 'reviewed' WHERE id = ?");
        $stmt->bind_param("i", $report_id);
        $stmt->execute();
        
        $message = "Tutor report has been dismissed and marked as reviewed.";
    } elseif ($action === 'suspend_tutor') {
        // Get tutor ID first
        $stmt = $conn->prepare("SELECT tutor_id FROM tutor_reports WHERE id = ?");
        $stmt->bind_param("i", $report_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $report_data = $result->fetch_assoc();
        
        if (isset($report_data['tutor_id'])) {
            $tutor_id = $report_data['tutor_id'];
            
            // Update tutor profile status to suspended)
            $stmt = $conn->prepare("UPDATE tutor_profiles SET status = 'suspended' WHERE user_id = ?");
            $stmt->bind_param("i", $tutor_id);
            $stmt->execute();
            
            // Update report status to reviewed
            $stmt = $conn->prepare("UPDATE tutor_reports SET status = 'reviewed' WHERE id = ?");
            $stmt->bind_param("i", $report_id);
            $stmt->execute();
            
            $message = "Tutor has been suspended and report marked as reviewed.";
        }
    }
    
    // Redirect to prevent form resubmission
    header("Location: admin_reports.php?status=$status_filter&message=".urlencode($message));
    exit();
}

// Fetch tutor reports
$tutor_reports_query = "
    SELECT tr.*, 
           u1.fullname as tutor_name, u1.id as tutor_id,
           u2.fullname as reporter_name, u2.id as reporter_id,
           s.id as session_id, s.session_date, s.start_time, s.end_time,
           sbj.name as subject_name
    FROM tutor_reports tr
    JOIN users u1 ON tr.tutor_id = u1.id
    JOIN users u2 ON tr.reporter_id = u2.id
    LEFT JOIN sessions s ON tr.session_id = s.id
    LEFT JOIN subjects sbj ON s.subject_id = sbj.id
    WHERE 1=1
";

// Add status filter to query
if ($status_filter !== 'all') {
    $tutor_reports_query .= " AND tr.status = '$status_filter'";
}

// Order by creation date
$tutor_reports_query .= " ORDER BY tr.created_at DESC";

// Execute query
$tutor_reports_result = $conn->query($tutor_reports_query);

// Get counts for sidebar
$pending_tutor_reports = $conn->query("SELECT COUNT(*) as count FROM tutor_reports WHERE status = 'pending'")->fetch_assoc()['count'];
$reviewed_tutor_reports = $conn->query("SELECT COUNT(*) as count FROM tutor_reports WHERE status = 'reviewed'")->fetch_assoc()['count'];
$total_reports = $pending_tutor_reports + $reviewed_tutor_reports;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tutor Reports Management - PeerTutor</title>
    <link rel="stylesheet" href="styles/styles.css">
    <link rel="stylesheet" href="styles/dashboard-styles.css">
    <link rel="stylesheet" href="styles/admin-styles.css">
    <style>

        .report-filters {
            display: flex;
            margin-bottom: 1.5rem;
            background-color: #f8f9fa;
            padding: 1rem;
            border-radius: 8px;
            align-items: center;
        }
        
        .filter-group {
            margin-right: 1.5rem;
        }
        
        .filter-group label {
            font-weight: 600;
            margin-right: 0.5rem;
        }
        
        .filter-group select {
            padding: 0.5rem;
            border-radius: 4px;
            border: 1px solid #ddd;
        }
        
        .report-card {
            background-color: white;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .report-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 1rem;
            align-items: center;
        }
        
        .report-title {
            font-size: 1.1rem;
            font-weight: 600;
        }
        
        .report-meta {
            display: flex;
            flex-wrap: wrap;
            margin-bottom: 1rem;
        }
        
        .meta-item {
            margin-right: 1.5rem;
            margin-bottom: 0.5rem;
        }
        
        .meta-label {
            font-weight: 500;
            color: #6c757d;
            margin-right: 0.25rem;
        }
        
        .report-content {
            border-top: 1px solid #e9ecef;
            border-bottom: 1px solid #e9ecef;
            padding: 1rem 0;
            margin-bottom: 1rem;
        }
        
        .session-info {
            background-color: #f8f9fa;
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1rem;
        }
        
        .report-actions {
            display: flex;
            justify-content: flex-end;
        }
        
        .action-btn {
            margin-left: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            font-size: 0.9rem;
            cursor: pointer;
        }
        
        .dismiss-btn {
            background-color: #6c757d;
            color: white;
            border: none;
        }
        
        .suspend-btn {
            background-color: #dc3545;
            color: white;
            border: none;
        }
        
        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .status-pending {
            background-color: rgba(255, 193, 7, 0.2);
            color: #856404;
        }
        
        .status-reviewed {
            background-color: rgba(40, 167, 69, 0.2);
            color: #155724;
        }
        
        .alert-message {
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1.5rem;
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .empty-state {
            text-align: center;
            padding: 2rem;
            background-color: #f8f9fa;
            border-radius: 8px;
            margin-top: 1rem;
        }
        
        .empty-state p {
            color: #6c757d;
            font-size: 1.1rem;
        }
        
        /* Form styles */
        form {
            display: inline;
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
            <li><a href="admin_reports.php" class="active">Reports</a></li>
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
                    <li>
                        <a href="admin_reports.php?status=pending">
                            <span>🚩</span> Pending Reports (<?php echo $pending_tutor_reports; ?>)
                        </a>
                    </li>
                    <li>
                        <a href="admin_reports.php?status=reviewed">
                            <span>✓</span> Reviewed Reports (<?php echo $reviewed_tutor_reports; ?>)
                        </a>
                    </li>
                    <li>
                        <a href="admin_reports.php?status=all">
                            <span>📋</span> All Reports (<?php echo $total_reports; ?>)
                        </a>
                    </li>
                </ul>
            </div>
        </aside>

        <!-- Main Dashboard Area -->
        <main class="dashboard-main">
            <h1>Tutor Reports Management</h1>
            
            <?php if(isset($_GET['message'])): ?>
                <div class="alert-message">
                    <?php echo htmlspecialchars($_GET['message']); ?>
                </div>
            <?php endif; ?>
            
            <!-- Report Filters -->
            <div class="report-filters">
                <div class="filter-group">
                    <label for="status-filter">Status:</label>
                    <select id="status-filter" onchange="window.location.href='admin_reports.php?status='+this.value;">
                        <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="reviewed" <?php echo $status_filter === 'reviewed' ? 'selected' : ''; ?>>Reviewed</option>
                        <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All</option>
                    </select>
                </div>
            </div>
            
            <!-- Tutor Reports Section -->
            <section id="tutor-reports-section">
                <h2>Tutor Reports</h2>
                
                <?php if ($tutor_reports_result->num_rows > 0): ?>
                    <?php while ($report = $tutor_reports_result->fetch_assoc()): ?>
                        <div class="report-card">
                            <div class="report-header">
                                <div class="report-title">Tutor Report #<?php echo $report['id']; ?></div>
                                <div class="status-badge status-<?php echo $report['status']; ?>">
                                    <?php echo ucfirst($report['status']); ?>
                                </div>
                            </div>
                            
                            <div class="report-meta">
                                <div class="meta-item">
                                    <span class="meta-label">Reported Tutor:</span>
                                    <span><?php echo htmlspecialchars($report['tutor_name']); ?></span>
                                </div>
                                <div class="meta-item">
                                    <span class="meta-label">Reported by:</span>
                                    <span><?php echo htmlspecialchars($report['reporter_name']); ?></span>
                                </div>
                                <div class="meta-item">
                                    <span class="meta-label">Report Date:</span>
                                    <span><?php echo date('M d, Y', strtotime($report['created_at'])); ?></span>
                                </div>
                                <div class="meta-item">
                                    <span class="meta-label">Reason:</span>
                                    <span><?php echo htmlspecialchars($report['reason']); ?></span>
                                </div>
                            </div>
                            
                            <div class="report-content">
                                <?php if ($report['additional_details']): ?>
                                    <p><strong>Additional Details:</strong></p>
                                    <p><?php echo nl2br(htmlspecialchars($report['additional_details'])); ?></p>
                                <?php endif; ?>
                                
                                <?php if ($report['session_id']): ?>
                                    <div class="session-info">
                                        <p><strong>Related Session:</strong></p>
                                        <p>
                                            <span class="meta-label">Date:</span> 
                                            <?php echo date('M d, Y', strtotime($report['session_date'])); ?>
                                        </p>
                                        <p>
                                            <span class="meta-label">Time:</span> 
                                            <?php echo date('h:i A', strtotime($report['start_time'])); ?> - 
                                            <?php echo date('h:i A', strtotime($report['end_time'])); ?>
                                        </p>
                                        <?php if ($report['subject_name']): ?>
                                            <p>
                                                <span class="meta-label">Subject:</span> 
                                                <?php echo htmlspecialchars($report['subject_name']); ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($report['status'] === 'pending'): ?>
                                <div class="report-actions">
                                    <form method="POST" action="admin_reports.php">
                                        <input type="hidden" name="report_id" value="<?php echo $report['id']; ?>">
                                        <input type="hidden" name="action" value="dismiss">
                                        <button type="submit" class="action-btn dismiss-btn">Dismiss Report</button>
                                    </form>
                                    
                                    <form method="POST" action="admin_reports.php">
                                        <input type="hidden" name="report_id" value="<?php echo $report['id']; ?>">
                                        <input type="hidden" name="action" value="suspend_tutor">
                                        <button type="submit" class="action-btn suspend-btn" onclick="return confirm('Are you sure you want to suspend this tutor? This will prevent them from accepting new students.')">Suspend Tutor</button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <p>No tutor reports found with the selected filter.</p>
                    </div>
                <?php endif; ?>
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