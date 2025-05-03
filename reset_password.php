<?php
// Start the session
session_start();
require_once 'db_connect.php';

// Check if token is provided
if (!isset($_GET['token']) || empty($_GET['token'])) {
    header("Location: login.php?error=Invalid password reset link");
    exit();
}

$token = htmlspecialchars($_GET['token']);
$currentTime = date('Y-m-d H:i:s');

// Validate token
$stmt = $conn->prepare("SELECT user_id, expires_at FROM password_reset_tokens WHERE token = ?");
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    header("Location: login.php?error=Invalid or expired password reset link");
    exit();
}

$tokenData = $result->fetch_assoc();

// Check if token is expired
if ($tokenData['expires_at'] < $currentTime) {
    header("Location: login.php?error=Password reset link has expired");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data and sanitize inputs
    $token = htmlspecialchars($_POST['token']);
    $password = htmlspecialchars($_POST['password']);
    $confirm_password = htmlspecialchars($_POST['confirm_password']);
    $currentTime = date('Y-m-d H:i:s');
    
    // Validate inputs
    if (empty($token) || empty($password) || empty($confirm_password)) {
        header("Location: reset_password.php?token=$token&error=Please fill in all fields");
        exit();
    }
    
    // Validate password - must be at least 8 characters
    if (strlen($password) < 8) {
        header("Location: reset_password.php?token=$token&error=Password must be at least 8 characters long");
        exit();
    }
    
    // Check if passwords match
    if ($password !== $confirm_password) {
        header("Location: reset_password.php?token=$token&error=Passwords do not match");
        exit();
    }
    
    try {
        // Validate token and check if it's expired
        $stmt = $conn->prepare("SELECT user_id, expires_at FROM password_reset_tokens WHERE token = ?");
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows !== 1) {
            header("Location: login.php?error=Invalid or expired password reset link");
            exit();
        }
        
        $tokenData = $result->fetch_assoc();
        
        // Check if token is expired
        if ($tokenData['expires_at'] < $currentTime) {
            header("Location: login.php?error=Password reset link has expired");
            exit();
        }
        
        $user_id = $tokenData['user_id'];
        
        // Hash new password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Update user's password
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $hashed_password, $user_id);
        
        if ($stmt->execute()) {
            // Delete used token
            $stmt = $conn->prepare("DELETE FROM password_reset_tokens WHERE token = ?");
            $stmt->bind_param("s", $token);
            $stmt->execute();
            
            header("Location: login.php?success=Your password has been reset successfully. You can now log in with your new password.");
            exit();
        } else {
            header("Location: reset_password.php?token=$token&error=Failed to update password. Please try again.");
            exit();
        }
    } catch (Exception $e) {
        header("Location: reset_password.php?token=$token&error=An error occurred. Please try again later.");
        exit();
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - PeerTutor</title>
    <link rel="stylesheet" href="styles/styles.css">
    <link rel="stylesheet" href="styles/auth-styles.css">
</head>
<body>
<nav class="navbar">
    <a href="index.php" class="logo">Peer<span>Tutor</span></a>
    <a href="login.php" class="btn secondary">Login</a>
</nav>

<!-- Reset Password Form Section -->
<section class="auth-container">
    <div class="auth-card">
        <div class="auth-header">
            <h2>Create New Password</h2>
            <p>Enter your new password below</p>
        </div>
        
        <form action="" method="POST" class="auth-form">
            <?php if(isset($_GET['error'])) { ?>
                <div class="error-message">
                    <?php echo htmlspecialchars($_GET['error']); ?>
                </div>
            <?php } ?>
            
            <input type="hidden" name="token" value="<?php echo $token; ?>">
            
            <div class="form-group">
                <label for="password">New Password</label>
                <input type="password" id="password" name="password" required placeholder="Enter new password">
            </div>
            
            <div class="form-group">
                <label for="confirm_password">Confirm New Password</label>
                <input type="password" id="confirm_password" name="confirm_password" required placeholder="Confirm new password">
            </div>
            
            <button type="submit" class="btn auth-btn">Reset Password</button>
        </form>
    </div>
</section>

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