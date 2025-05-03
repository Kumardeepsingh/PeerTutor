<?php
session_start();

// Check if user is logged in
if(!isset($_SESSION['logged_in'])) {
    header("Location: login.php?error=Please login to view your sessions");
    exit();
}

// Include database connection
require_once 'db_connect.php';
require_once 'vendor/autoload.php'; 
require_once 'stripe_config.php';

use GuzzleHttp\Client;

// Zoom API credentials
$clientId = $_ENV['ZOOM_CLIENT_ID']; 
$clientSecret = $_ENV['ZOOM_CLIENT_SECRET'];
$accountId = $_ENV['ZOOM_ACCOUNT_ID'];

// Get user information
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

if(isset($_GET['stripe_account_id'])){
    // Update the tutor_profiles table with the new Stripe account ID
    $update_stmt = $conn->prepare("
    UPDATE tutor_profiles 
    SET stripe_account_id = ? 
    WHERE user_id = ?
");
$update_stmt->bind_param("si",$_GET['stripe_account_id'] , $user_id);
$update_stmt->execute();

}

// get stripe_account_id from tutor profiles
$stmt = $conn->prepare("
SELECT stripe_account_id
FROM tutor_profiles 
WHERE user_id = ?
");
$stmt->bind_param("i",$user_id);
$stmt->execute();
$stripe_account_result = $stmt->get_result();
$stripe_account_row = $stripe_account_result->fetch_assoc();
$stripe_account_id = $stripe_account_row ? $stripe_account_row['stripe_account_id'] : null;


if ($role === 'tutor' && empty($stripe_account_id)) {
    $stripe_account = createStripeAccountForTutor($tutor_email);
    if ($stripe_account['status'] === 'success') {
        $account_url = $stripe_account['accountLinkUrl'];
        
        if (!empty($account_url)) {
            header('Location: ' . $account_url);
            exit;
        } else {
            $error_message = "Could not complete Stripe account setup. Please try again.";
        }
    } else {
        $error_message = "Could not create Stripe account: " . $stripe_account['message'];
    }
}

// get user
$stmt = $conn->prepare("
SELECT *
FROM users 
WHERE id = ?
");
$stmt->bind_param("i",$user_id);
$stmt->execute();
$user_result = $stmt->get_result();
$user= $user_result->fetch_assoc();
$tutor_email = $user['email'];



// Handle session approval (for tutors only)
if ($role === 'tutor' && isset($_GET['approve']) && isset($_GET['session_id'])) {
    $session_id = $_GET['session_id'];

    // get payment intent id for the session
    $stmt = $conn->prepare("
    SELECT stripe_payment_intent_id FROM payment_transactions WHERE session_id = ?  
");
$stmt->bind_param("i",$session_id);
$stmt->execute();
$payment_result = $stmt->get_result();
$payment = $payment_result->fetch_assoc();
$payment_intent_id = $payment['stripe_payment_intent_id'];

$payment_capture = approveSession($payment_intent_id);

if($payment_capture['status']==='success'){  
    // change payemnt status to captured 

    $stmt = $conn->prepare("
    UPDATE payment_transactions SET status = 'captured' WHERE session_id = ?  
");
$stmt->bind_param("i", $session_id);
$stmt->execute();
    // First get session details to set up the Zoom meeting
    $stmt = $conn->prepare("
        SELECT s.*, u.fullname as student_name, subj.name as subject_name
        FROM sessions s
        JOIN users u ON s.student_id = u.id
        JOIN subjects subj ON s.subject_id = subj.id
        WHERE s.id = ? AND s.tutor_id = ? AND s.status = 'pending'
    ");
    $stmt->bind_param("ii", $session_id, $user_id);
    $stmt->execute();
    $session_result = $stmt->get_result();
    
    if ($session = $session_result->fetch_assoc()) {
        // Get an OAuth Access Token from Zoom
        $client = new Client();
        try {
            $response = $client->post('https://zoom.us/oauth/token', [
                'form_params' => [
                    'grant_type' => 'account_credentials',
                    'account_id' => $accountId
                ],
                'headers' => [
                    'Authorization' => 'Basic ' . base64_encode("$clientId:$clientSecret"),
                    'Content-Type' => 'application/x-www-form-urlencoded'
                ]
            ]);
            
            $token_data = json_decode($response->getBody(), true);
            $accessToken = $token_data['access_token'];
            
            // Format date and time for Zoom API
            $session_datetime = $session['session_date'].$session['start_time'];
            
            // Calculate duration in minutes
            $start_time = strtotime($session['start_time']);
            $end_time = strtotime($session['end_time']);
            $duration_minutes = ($end_time - $start_time) / 60;
            
            // Create a Zoom meeting
            $response = $client->post('https://api.zoom.us/v2/users/me/meetings', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type' => 'application/json'
                ],
                'json' => [
                    'topic' => 'Tutoring: ' . $session['subject_name'] . ' with ' . $session['student_name'],
                    'type' => 2, 
                    'start_time' => $session_datetime,
                    'duration' => $duration_minutes,
                    'timezone' => 'UTC',
                    'settings' => [
                        'host_video' => true,
                        'participant_video' => true,
                        'auto_recording' => 'cloud',
                        'join_before_host' => true,
                        'waiting_room' => false
                    ]
                ]
            ]);
            
            // Get Zoom meeting details
            $meeting_data = json_decode($response->getBody(), true);
            $zoom_meeting_id = $meeting_data['id'];
            $zoom_join_url = $meeting_data['join_url'];
            $zoom_start_url = $meeting_data['start_url'];
            
            // Update session status to scheduled and add Zoom details
            $stmt = $conn->prepare("
                UPDATE sessions 
                SET status = 'scheduled', 
                    zoom_meeting_id = ?, 
                    zoom_join_url = ?, 
                    zoom_start_url = ? 
                WHERE id = ? AND tutor_id = ? AND status = 'pending'
            ");
            $stmt->bind_param("sssii", $zoom_meeting_id, $zoom_join_url, $zoom_start_url, $session_id, $user_id);
            
            if ($stmt->execute()) {
                $success_message = "Session approved successfully and Zoom meeting created!";
                
            } else {
                $error_message = "Failed to approve session. Please try again.";
            }
            
        } catch (Exception $e) {
            $error_message = "Failed to create Zoom meeting: " . $e->getMessage();
        }
    } else {
        $error_message = "Session not found or already processed.";
    }
}else{
    $error_message = "There was a problem with payment. This session cannot be approved. Please reject this session.";
}
}
// Handle session rejection (for tutors only)
if ($role === 'tutor' && isset($_GET['reject']) && isset($_GET['session_id'])) {
    $session_id = $_GET['session_id'];

    // get payment intent id for the session
    $stmt = $conn->prepare("
    SELECT stripe_payment_intent_id FROM payment_transactions WHERE session_id = ?");
    $stmt->bind_param("i",$session_id);
    $stmt->execute();
    $payment_result = $stmt->get_result();
    $payment = $payment_result->fetch_assoc();
    $payment_intent_id = $payment['stripe_payment_intent_id'];

    $refund = refundUncapturedPayment($payment_intent_id);

    if($refund['status'] === 'success'){ 

    $stmt = $conn->prepare("
    UPDATE payment_transactions SET status = 'refunded' WHERE session_id = ?  ");
    $stmt->bind_param("i", $session_id);
    $stmt->execute();
    
    // Update session status to rejected
    $stmt = $conn->prepare("UPDATE sessions SET status = 'rejected' WHERE id = ? AND tutor_id = ? AND status = 'pending'");
    $stmt->bind_param("ii", $session_id, $user_id);
    
    if ($stmt->execute()) {
        $success_message = "Session rejected successfully.";
    } else {
        $error_message = "Failed to reject session. Please try again.";
    }
}else{
    $error_message = "Failed to reject session because payment was not refunded. Please try again.";
}
}

// Handle session cancellation
if (isset($_GET['cancel']) && isset($_GET['session_id'])) {
    $session_id = $_GET['session_id'];

    // get payment intent id for the session
    $stmt = $conn->prepare("
    SELECT stripe_payment_intent_id FROM payment_transactions WHERE session_id = ?");
    $stmt->bind_param("i",$session_id);
    $stmt->execute();
    $payment_result = $stmt->get_result();
    $payment = $payment_result->fetch_assoc();
    $payment_intent_id = $payment['stripe_payment_intent_id'];

    $refund = refundCapturedPayment($payment_intent_id);

    if($refund['status'] === 'success'){ 

            
    $stmt = $conn->prepare("
    UPDATE payment_transactions SET status = 'refunded' WHERE session_id = ?  ");
    $stmt->bind_param("i", $session_id);
    $stmt->execute();

    // Prepare statement based on user role to ensure proper authorization
    if ($role === 'student') {
        $stmt = $conn->prepare("UPDATE sessions SET status = 'cancelled' WHERE id = ? AND student_id = ? AND status = 'scheduled'");
        $stmt->bind_param("ii", $session_id, $user_id);
    } else {
        $stmt = $conn->prepare("UPDATE sessions SET status = 'cancelled' WHERE id = ? AND tutor_id = ? AND status = 'scheduled'");
        $stmt->bind_param("ii", $session_id, $user_id);
    }
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            $success_message = "Session cancelled successfully.";
        } else {
            $error_message = "Could not cancel the session. It may have already been cancelled or completed.";
        }
    } else {
        $error_message = "Failed to cancel session. Please try again.";
    }
}
}

// Get sessions based on role
if ($role === 'student') {
    // For students, get all their sessions
    $stmt = $conn->prepare("
        SELECT s.*, 
               u.fullname as tutor_name, 
               subj.name as subject_name, 
               subj.category as subject_category
        FROM sessions s
        JOIN users u ON s.tutor_id = u.id
        JOIN subjects subj ON s.subject_id = subj.id
        WHERE s.student_id = ?
        ORDER BY 
            CASE 
                WHEN s.status = 'pending' THEN 1
                WHEN s.status = 'scheduled' THEN 2
                WHEN s.status = 'completed' THEN 3
                ELSE 4
            END,
            s.session_date ASC, 
            s.start_time ASC
    ");
    $stmt->bind_param("i", $user_id);
} else {
    // For tutors, get all their sessions
    $stmt = $conn->prepare("
        SELECT s.*, 
               u.fullname as student_name, 
               subj.name as subject_name, 
               subj.category as subject_category
        FROM sessions s
        JOIN users u ON s.student_id = u.id
        JOIN subjects subj ON s.subject_id = subj.id
        WHERE s.tutor_id = ?
        ORDER BY 
            CASE 
                WHEN s.status = 'pending' THEN 1
                WHEN s.status = 'scheduled' THEN 2
                WHEN s.status = 'completed' THEN 3
                ELSE 4
            END,
            s.session_date ASC, 
            s.start_time ASC
    ");
    $stmt->bind_param("i", $user_id);
}

$stmt->execute();
$sessions_result = $stmt->get_result();

// Calculate stats
$pending_count = 0;
$scheduled_count = 0;
$completed_count = 0;
$total_count = 0;

$sessions = [];
while ($session = $sessions_result->fetch_assoc()) {
    $sessions[] = $session;
    $total_count++;
    
    if ($session['status'] === 'pending') {
        $pending_count++;
    } elseif ($session['status'] === 'scheduled') {
        $scheduled_count++;
    } elseif ($session['status'] === 'completed') {
        $completed_count++;
    }
}

// Prepare page title and subtitle based on role
$page_title = ($role === 'student') ? "My Sessions" : "Tutor Sessions";
$page_subtitle = ($role === 'student') 
    ? "Manage your tutoring sessions and view upcoming appointments" 
    : "Manage your tutoring schedule and student sessions";

// Determine dashboard URL based on role
$dashboard_url = ($role === 'student') ? "student_dashboard.php" : "tutor_dashboard.php";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - PeerTutor</title>
    <link rel="stylesheet" href="styles/styles.css">
    <style>
        .sessions-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .sessions-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .sessions-stats {
            display: flex;
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 8px;
            padding: 15px;
            flex: 1;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            text-align: center;
        }
        
        .stat-count {
            font-size: 28px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #666;
            font-size: 14px;
        }
        
        
        .sessions-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .sessions-table th {
            background-color: #f8f9fa;
            text-align: left;
            padding: 15px;
            border-bottom: 2px solid #eee;
        }
        
        .sessions-table td {
            padding: 15px;
            border-bottom: 1px solid #eee;
            vertical-align: middle;
        }
        
        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: bold;
            text-align: center;
        }
        
        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .status-scheduled {
            background-color: #d1ecf1;
            color: #0c5460;
        }
        
        .status-completed {
            background-color: #d4edda;
            color: #155724;
        }
        
        .status-cancelled, .status-rejected {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
        }
        
        .action-buttons .btn {
            padding: 8px 12px;
            font-size: 14px;
        }
        
        .alert {
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px;
            background-color: #f8f9fa;
            border-radius: 8px;
            margin-top: 20px;
        }
        
        .empty-state p {
            color: #666;
            margin-bottom: 20px;
        }
        
        .session-details {
            font-size: 14px;
            color: #555;
        }
        
        .price {
            font-weight: bold;
            color: #333;
        }
        
        .meeting-link {
            display: inline-block;
            margin-top: 5px;
            font-size: 14px;
            color: #0066cc;
            text-decoration: none;
        }
        
        .meeting-link:hover {
            text-decoration: underline;
        }
        
        .session-notes {
            max-width: 300px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            color: #666;
            font-style: italic;
        }
        
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar">
        <?php if ($role === 'student'): ?>
            <a href="student_dashboard.php" class="logo">Peer<span>Tutor</span></a>
        <?php elseif ($role === 'tutor'): ?>
            <a href="tutor_dashboard.php" class="logo">Peer<span>Tutor</span></a>
        <?php endif; ?>
        <ul class="nav-links">
            <?php if ($role === 'student'): ?>
                <li><a href="student_dashboard.php">Dashboard</a></li>
                <li><a href="find_tutors.php">Find Tutors</a></li>
                <li><a href="my_sessions.php" class="active">My Sessions</a></li>
                <li><a href="student_profile.php">Profile</a></li>
            <?php elseif ($role === 'tutor'): ?>
                <li><a href="tutor_dashboard.php">Dashboard</a></li>
                <li><a href="my_sessions.php" class="active">Sessions</a></li>
                <li><a href="tutor_profile.php">Profile</a></li>
                <li><a href="tutor_availability.php">Availability</a></li>
            <?php endif; ?>
            <li><a href="logout.php" class="btn secondary">Logout</a></li>
        </ul>
    </nav>

    <!-- Main Content -->
    <div class="container">
        <h1 class="page-title"><?php echo $page_title; ?></h1>
        <p class="page-subtitle"><?php echo $page_subtitle; ?></p>
        
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger"><?php echo $error_message; ?></div>
        <?php endif; ?>
        
        <div class="sessions-container">
            <div class="sessions-stats">
                <div class="stat-card">
                    <div class="stat-count"><?php echo $pending_count; ?></div>
                    <div class="stat-label">Pending</div>
                </div>
                <div class="stat-card">
                    <div class="stat-count"><?php echo $scheduled_count; ?></div>
                    <div class="stat-label">Upcoming</div>
                </div>
                <div class="stat-card">
                    <div class="stat-count"><?php echo $completed_count; ?></div>
                    <div class="stat-label">Completed</div>
                </div>
                <div class="stat-card">
                    <div class="stat-count"><?php echo $total_count; ?></div>
                    <div class="stat-label">Total</div>
                </div>
            </div>
            
            <?php if (count($sessions) > 0): ?>
                <table class="sessions-table">
                    <thead>
                        <tr>
                            <th>Date & Time</th>
                            <?php if ($role === 'student'): ?>
                                <th>Tutor</th>
                            <?php else: ?>
                                <th>Student</th>
                            <?php endif; ?>
                            <th>Subject</th>
                            <th>Details</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sessions as $session): ?>
                            <tr>
                                <td>
                                    <?php echo date('M d, Y', strtotime($session['session_date'])); ?><br>
                                    <?php echo date('h:i A', strtotime($session['start_time'])); ?> - 
                                    <?php echo date('h:i A', strtotime($session['end_time'])); ?>
                                </td>
                                <td>
                                    <?php 
                                    if ($role === 'student') {
                                        echo htmlspecialchars($session['tutor_name']);
                                    } else {
                                        echo htmlspecialchars($session['student_name']);
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($session['subject_name']); ?><br>
                                    <span class="subject-category"><?php echo htmlspecialchars($session['subject_category']); ?></span>
                                </td>
                                <td>
                                    <div class="session-details">
                                        <div class="price">
                                            $<?php echo number_format($session['total_price'], 2); ?>
                                        </div>
                                        
                                        <?php if (!empty($session['additional_notes'])): ?>
                                            <div class="session-notes" title="<?php echo htmlspecialchars($session['additional_notes']); ?>">
                                                <?php echo htmlspecialchars($session['additional_notes']); ?>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($session['status'] === 'scheduled' && !empty($session['zoom_join_url'])): ?>
                                            <?php if ($role === 'student'): ?>
                                                <a href="<?php echo htmlspecialchars($session['zoom_join_url']); ?>" class="meeting-link" target="_blank">
                                                    Join Zoom Meeting
                                                </a>
                                            <?php else: ?>
                                                <a href="<?php echo htmlspecialchars($session['zoom_start_url']); ?>" class="meeting-link" target="_blank">
                                                    Start Zoom Meeting
                                                </a>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="status-badge status-<?php echo $session['status']; ?>">
                                        <?php 
                                        if ($session['status'] === 'pending') {
                                            echo 'Pending Approval';
                                        } else {
                                            echo ucfirst($session['status']);
                                        }
                                        ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <?php if ($role === 'tutor' && $session['status'] === 'pending'): ?>
                                            <a href="?approve=true&session_id=<?php echo $session['id']; ?>" class="btn small">Approve</a>
                                            <a href="?reject=true&session_id=<?php echo $session['id']; ?>" class="btn small secondary">Reject</a>
                                        <?php endif; ?>
                                        
                                        <?php if ($session['status'] === 'scheduled'): ?>
                                    <?php
                                        $session_start = strtotime($session['session_date'] . ' ' . $session['start_time']);
                                        $current_time = time();
                                        $time_difference = $session_start - $current_time;
                                        $hours_difference = $time_difference / 3600; 
                                        
                                        if ($hours_difference > 2): 
                                        ?>
                                            <a href="?cancel=true&session_id=<?php echo $session['id']; ?>" class="btn small secondary">Cancel</a>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                        
                                        <?php if ($role === 'student' && $session['status'] === 'completed' && !isset($session['review_id'])): ?>
                                            <a href="rate_tutor.php?tutor_id=<?php echo $session['tutor_id']; ?>" class="btn small">Review</a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <?php if ($role === 'student'): ?>
                        <p>You don't have any sessions yet.</p>
                        <a href="find_tutors.php" class="btn">Find a Tutor</a>
                    <?php else: ?>
                        <p>You don't have any sessions yet.</p>
                        <a href="tutor_availability.php" class="btn">Update Availability</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
    function updateMeetingStatus() {
        fetch('update_meeting_status.php')
        .then(response => response.text())
        .then(data => {
            console.log("Meeting Status Updated:", data);
            
            if (data.includes("Updated Session ID") || data.includes("completed") || data.includes("recording URL")) {
                location.reload();  
            }
        })
        .catch(error => console.error("Error updating meetings:", error));
    }

    // Run the update when the user switches back to the tab
    document.addEventListener("visibilitychange", function() {
        if (!document.hidden) {
            updateMeetingStatus();
        }
    });

    // Run update every 30 seconds to check for session updates or missing recording URLs
    setInterval(updateMeetingStatus, 30000);
</script>


    
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