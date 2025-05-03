<?php
session_start();

// Check if user is logged in as admin
if(!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'admin'){
    header("Location: login.php?error=Please login as an administrator to access this page");
    exit();
} 

// Include database connection
require_once 'db_connect.php';

// Check if ID is provided
if(!isset($_GET['id']) || empty($_GET['id'])){
    header("Location: admin_tutors.php?error=Invalid tutor application ID");
    exit();
}

$tutor_profile_id = $_GET['id'];

// Get tutor profile information
$stmt = $conn->prepare("
    SELECT tp.*, u.fullname, u.email, u.profile_image
    FROM tutor_profiles tp
    JOIN users u ON tp.user_id = u.id
    WHERE tp.id = ?
");
$stmt->bind_param("i", $tutor_profile_id);
$stmt->execute();
$result = $stmt->get_result();

if($result->num_rows === 0){
    header("Location: admin_tutors.php?error=Tutor application not found");
    exit();
}

$tutor = $result->fetch_assoc();

// Get tutor subjects
$stmt = $conn->prepare("
    SELECT s.name, s.category, ts.proficiency_level
    FROM tutor_subjects ts
    JOIN subjects s ON ts.subject_id = s.id
    WHERE ts.tutor_profile_id = ?
");
$stmt->bind_param("i", $tutor_profile_id);
$stmt->execute();
$subjects_result = $stmt->get_result();

// Process approval/rejection
if($_SERVER['REQUEST_METHOD'] === 'POST'){
    if(isset($_POST['action'])){
        $new_status = ($_POST['action'] === 'approve') ? 'approved' : 'rejected';
        $admin_notes = isset($_POST['admin_notes']) ? $_POST['admin_notes'] : '';
        
        $stmt = $conn->prepare("
            UPDATE tutor_profiles 
            SET status = ? 
            WHERE id = ?
        ");
        $stmt->bind_param("si", $new_status, $tutor_profile_id);
        
        if($stmt->execute()){
            
            // Redirect back to tutors page with success message
            header("Location: admin_tutors.php?success=Tutor application " . $new_status . " successfully");
            exit();
        } else {
            $error = "Failed to update tutor status. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Tutor Application - PeerTutor</title>
    <link rel="stylesheet" href="styles/styles.css">
    <link rel="stylesheet" href="styles/dashboard-styles.css">
    <link rel="stylesheet" href="styles/admin-styles.css">
    <style>

.back-link {
    display: inline-block;
    color: var(--primary);
    margin-bottom: 0.5rem;
    text-decoration: none;
    font-weight: 500;
}

.back-link:hover {
    text-decoration: underline;
}

.page-header {
    margin-bottom: 2rem;
}

.tutor-application {
    background-color: white;
    border-radius: 10px;
    box-shadow: var(--shadow);
    overflow: hidden;
}

.application-header {
    padding: 2rem;
    border-bottom: 1px solid var(--border);
    background-color: rgba(52, 152, 219, 0.05);
}

.tutor-profile {
    display: flex;
    align-items: center;
    gap: 2rem;
}

.profile-image.large {
    width: 120px;
    height: 120px;
}

.tutor-info {
    flex: 1;
}

.tutor-info h2 {
    margin-bottom: 0.5rem;
    color: var(--dark);
}

.status-badge {
    display: inline-block;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    margin-top: 0.5rem;
}

.status-pending {
    background-color: rgba(243, 156, 18, 0.1);
    color: var(--pending);
}

.status-approved {
    background-color: rgba(46, 204, 113, 0.1);
    color: var(--success);
}

.status-rejected {
    background-color: rgba(231, 76, 60, 0.1);
    color: var(--cancelled);
}

.application-details {
    padding: 2rem;
}

.application-section {
    margin-bottom: 2rem;
}

.application-section h3 {
    color: var(--dark);
    margin-bottom: 0.75rem;
    font-size: 1.2rem;
}

.no-data {
    color: var(--gray);
    font-style: italic;
}

.credentials-preview {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.application-actions {
    padding: 2rem;
    border-top: 1px solid var(--border);
    background-color: rgba(236, 240, 241, 0.3);
}

.action-buttons {
    display: flex;
    gap: 1rem;
    margin-top: 1rem;
}

.btn.success {
    background-color: var(--success);
}

.btn.success:hover {
    background-color: #27ae60;
}

.btn.danger {
    background-color: var(--danger);
}

.btn.danger:hover {
    background-color: #c0392b;
}

.form-group {
    margin-bottom: 1rem;
}

label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 500;
}

.form-control {
    width: 100%;
    padding: 0.75rem;
    border: 1px solid var(--border);
    border-radius: 5px;
    font-size: 1rem;
    transition: border-color 0.3s;
}

.form-control:focus {
    border-color: var(--primary);
    outline: none;
}

.nav-links a.active {
    color: var(--primary);
    font-weight: 600;
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
    </nav>

    <!-- Main Content -->
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="dashboard-sidebar">
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
            <div class="page-header">
                <a href="admin_tutors.php" class="back-link">< Back to Tutors</a>
                <h1>Review Tutor Application</h1>
            </div>
            
            <?php if(isset($error)): ?>
                <div class="alert alert-danger">
                    <p><?php echo $error; ?></p>
                </div>
            <?php endif; ?>

            <div class="tutor-application">
                <div class="application-header">
                    <div class="tutor-profile">
                        <div class="profile-image large">
                            <?php if (!empty($tutor['profile_image'])): ?>
                                <img src="<?php echo htmlspecialchars($tutor['profile_image']); ?>" alt="Profile Picture">
                            <?php else: ?>
                                <div class="profile-initial"><?php echo substr(htmlspecialchars($tutor['fullname']), 0, 1); ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="tutor-info">
                            <h2><?php echo htmlspecialchars($tutor['fullname']); ?></h2>
                            <p><strong>Email:</strong> <?php echo htmlspecialchars($tutor['email']); ?></p>
                            <p><strong>Hourly Rate:</strong> $<?php echo number_format($tutor['hourly_rate'], 2); ?>/hour</p>
                            <p class="status-badge status-<?php echo $tutor['status']; ?>">
                                <?php echo ucfirst($tutor['status']); ?>
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="application-details">
                    <div class="application-section">
                        <h3>Education</h3>
                        <p><?php echo nl2br(htmlspecialchars($tutor['education'])); ?></p>
                    </div>
                    
                    <div class="application-section">
                        <h3>Bio</h3>
                        <p><?php echo nl2br(htmlspecialchars($tutor['bio'])); ?></p>
                    </div>
                    
                    <div class="application-section">
                        <h3>Preferred Languages</h3>
                        <p><?php echo htmlspecialchars($tutor['preferred_languages'] ?: 'Not specified'); ?></p>
                    </div>
                    
                    <div class="application-section">
                        <h3>Subjects</h3>
                        <?php if ($subjects_result->num_rows > 0): ?>
                            <div class="subjects-container">
                                <?php 
                                $categories = [];
                                while ($subject = $subjects_result->fetch_assoc()) {
                                    $categories[$subject['category']][] = $subject;
                                }
                                
                                foreach ($categories as $category => $subjects): ?>
                                    <div class="subject-category">
                                        <h4><?php echo htmlspecialchars($category); ?></h4>
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
                            <p class="no-data">No subjects specified</p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="application-section">
                        <h3>Credentials</h3>
                        <?php if (!empty($tutor['credentials_pdf'])): ?>
                            <div class="credentials-preview">
                                <p>Credentials document provided:</p>
                                <a href="<?php echo htmlspecialchars($tutor['credentials_pdf']); ?>" class="btn secondary small" target="_blank">View Credentials PDF</a>
                            </div>
                        <?php else: ?>
                            <p class="no-data">No credentials document uploaded</p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="application-actions">
                    <form action="" method="post">
                        
                        
                        <div class="action-buttons">
                            <button type="submit" name="action" value="approve" class="btn success">Approve Application</button>
                            <button type="submit" name="action" value="reject" class="btn danger">Reject Application</button>
                        </div>
                    </form>
                </div>
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