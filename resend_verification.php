<?php
session_start();
require_once 'db_connect.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require_once __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Check if user is logged in
if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get user email and verification status
$stmt = $conn->prepare("SELECT email, is_verified FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Check if user is already verified
if($user['is_verified']) {
    $_SESSION['error'] = "Your account is already verified";
    header("Location: ".$_SERVER['HTTP_REFERER']);
    exit();
}

// Delete any existing tokens for this user
$stmt = $conn->prepare("DELETE FROM verification_tokens WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();

// Generate new token
$verificationToken = bin2hex(random_bytes(16));
$createdAt = date('Y-m-d H:i:s');

// Store new token
$stmt = $conn->prepare("INSERT INTO verification_tokens (user_id, token, created_at) VALUES (?, ?, ?)");
$stmt->bind_param("iss", $user_id, $verificationToken, $createdAt);
$stmt->execute();

// Send verification email
$verificationLink = "http://localhost/project1/verify.php?token=$verificationToken";

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
    $mail->addAddress($user['email']);
    
    $mail->isHTML(true);
    $mail->Subject = 'Verify Your PeerTutor Account';
    
    $email_body = "
        <h2>Verify Your Account</h2>
        <p>We received a request to resend the verification email for your PeerTutor account.</p>
        <p><a href='$verificationLink' style='background-color: #4CAF50; color: white; padding: 10px 20px; text-align: center; text-decoration: none; display: inline-block; border-radius: 5px;'>Verify Email</a></p>
        <p>Or copy and paste this link into your browser:<br>$verificationLink</p>";
    
    $mail->Body = $email_body;
    $mail->AltBody = "Please verify your account by visiting this link: $verificationLink";
    
    $mail->send();
    
    $_SESSION['success'] = "Verification email has been resent. Please check your inbox.";
} catch (Exception $e) {
    error_log("Mailer Error: " . $mail->ErrorInfo);
    $_SESSION['error'] = "Failed to send verification email. Please try again later.";
}

header("Location: ".$_SERVER['HTTP_REFERER']);
exit();
?>