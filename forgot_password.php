<?php
session_start();
    use PHPMailer\PHPMailer\PHPMailer;
    use PHPMailer\PHPMailer\Exception;
    require_once __DIR__ . '/vendor/autoload.php';

    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();    require_once 'db_connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    

    // Get and sanitize email
    $email = filter_var(htmlspecialchars($_POST['email']), FILTER_SANITIZE_EMAIL);
    
    // Validate email
    if (empty($email)) {
        header("Location: forgot_password.php?error=Please enter your email address");
        exit();
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header("Location: forgot_password.php?error=Invalid email format");
        exit();
    }
    
    try {
        // Check if user exists
        $stmt = $conn->prepare("SELECT id, fullname FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            $user_id = $user['id'];
            $fullname = $user['fullname'];
            
            // Generate reset token
            $resetToken = bin2hex(random_bytes(16));
            $createdAt = date('Y-m-d H:i:s');
            $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // First, delete any existing reset tokens for this user
            $stmt = $conn->prepare("DELETE FROM password_reset_tokens WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            
            // Store token in database
            $stmt = $conn->prepare("INSERT INTO password_reset_tokens (user_id, token, created_at, expires_at) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("isss", $user_id, $resetToken, $createdAt, $expiresAt);
            $stmt->execute();
            
            // Create reset link
            $resetLink = "http://localhost/project1/reset_password.php?token=$resetToken";
            
            // Send email with reset link
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = $_ENV['GMAIL_USERNAME'];
                $mail->Password = $_ENV['GMAIL_PASSWORD'];
                $mail->Port = 587;
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                
                $mail->setFrom($_ENV['GMAIL_USERNAME'], 'PeerTutor');
                $mail->addAddress($email);
                
                $mail->isHTML(true);
                $mail->Subject = 'Reset Your PeerTutor Password';
                
                $email_body = "
                    <h2>Password Reset</h2>
                    <p>Hi $fullname,</p>
                    <p>We received a request to reset your PeerTutor account password. Click the button below to set a new password:</p>
                    <p><a href='$resetLink' style='background-color: #4CAF50; color: white; padding: 10px 20px; text-align: center; text-decoration: none; display: inline-block; border-radius: 5px;'>Reset Password</a></p>
                    <p>Or copy and paste this link into your browser:<br>$resetLink</p>
                    <p>This link will expire in 1 hour.</p>
                    <p>If you didn't request a password reset, please ignore this email or contact support if you have concerns.</p>";
                
                $mail->Body = $email_body;
                $mail->AltBody = "Reset your password by visiting this link: $resetLink. This link will expire in 1 hour.";
                
                $mail->send();
                
                header("Location: forgot_password.php?success=Password reset link has been sent to your email address");
                exit();
            } catch (Exception $e) {
                error_log("Mailer Error: " . $mail->ErrorInfo);
                header("Location: forgot_password.php?error=Failed to send password reset email. Please try again later.");
                exit();
            }
        } else {
            // User not found
            // We'll show a success message anyway for security reasons
            header("Location: forgot_password.php?success=If your email is registered, a password reset link has been sent.");
            exit();
        }
    } catch (Exception $e) {
        header("Location: forgot_password.php?error=An error occurred. Please try again later.");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - PeerTutor</title>
    <link rel="stylesheet" href="styles/styles.css">
    <link rel="stylesheet" href="styles/auth-styles.css">
</head>
<body>
<nav class="navbar">
    <a href="index.php" class="logo">Peer<span>Tutor</span></a>
    <a href="login.php" class="btn secondary">Login</a>
</nav>

<!-- Forgot Password Form Section -->
<section class="auth-container">
    <div class="auth-card">
        <div class="auth-header">
            <h2>Reset Password</h2>
            <p>Enter your email to receive a password reset link</p>
        </div>
        
        <form action="forgot_password.php" method="POST" class="auth-form">
            <?php if(isset($_GET['error'])) { ?>
                <div class="error-message">
                    <?php echo htmlspecialchars($_GET['error']); ?>
                </div>
            <?php } ?>
            
            <?php if(isset($_GET['success'])) { ?>
                <div class="success-message">
                    <?php echo htmlspecialchars($_GET['success']); ?>
                </div>
            <?php } ?>
            
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" required placeholder="Enter your email">
            </div>
            
            <button type="submit" class="btn auth-btn">Send Reset Link</button>
        </form>
        
        <div class="auth-footer">
            <p>Remember your password? <a href="login.php">Login</a></p>
        </div>
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