<?php
require_once 'db_connect.php';

if (isset($_GET['token'])) {
    $verificationToken = $_GET['token'];
    
    // Check if the token exists and get user ID
    $stmt = $conn->prepare("SELECT user_id FROM verification_tokens WHERE token = ?");
    $stmt->bind_param("s", $verificationToken);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $userId = $row['user_id'];
        
        // Verify the user
        $stmt = $conn->prepare("UPDATE users SET is_verified = 1 WHERE id = ?");
        $stmt->bind_param("i", $userId);
        
        if ($stmt->execute()) {
            // Delete the used token
            $stmt = $conn->prepare("DELETE FROM verification_tokens WHERE user_id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            
            // Get user info for welcome message
            $stmt = $conn->prepare("SELECT fullname FROM users WHERE id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
            
            // Redirect to login with success message
            header("Location: login.php?success=Your account has been verified! You can now log in.");
            exit();
        } else {
            header("Location: login.php?error=Error verifying account. Please try again.");
            exit();
        }
    } else {
        header("Location: login.php?error=Invalid or expired verification token.");
        exit();
    }
} else {
    header("Location: login.php?error=No verification token provided.");
    exit();
}
?>