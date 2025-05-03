<?php
session_start();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require_once __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();
require_once 'db_connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data and sanitize inputs
    $fullname = htmlspecialchars($_POST['fullname']);
    $email = htmlspecialchars($_POST['email']);
    $password = htmlspecialchars($_POST['password']);
    $confirm_password = htmlspecialchars($_POST['confirm_password']);
    $role = htmlspecialchars($_POST['role']);
    $terms = isset($_POST['terms']) ? true : false;
    $default_is_verified = 0;

    // Validate inputs
    if (empty($fullname) || empty($email) || empty($password) || empty($confirm_password)) {
        header("Location: register.php?error=Please fill in all fields");
        exit();
    }

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header("Location: register.php?error=Invalid email format");
        exit();
    }

    // Validate password - must be at least 8 characters
    if (strlen($password) < 8) {
        header("Location: register.php?error=Password must be at least 8 characters long");
        exit();
    }

    // Check if passwords match
    if ($password !== $confirm_password) {
        header("Location: register.php?error=Passwords do not match");
        exit();
    }

    // Check if terms are accepted
    if (!$terms) {
        header("Location: register.php?error=You must accept the Terms of Service and Privacy Policy");
        exit();
    }

    // Validate role
    if ($role !== 'student' && $role !== 'tutor') {
        header("Location: register.php?error=Invalid account type");
        exit();
    }

    try {
        // Check if email already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            header("Location: register.php?error=Email already exists");
            exit();
        }

        // Hash password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $createdAt = date('Y-m-d H:i:s');

        // Create new user with is_verified=0
        $stmt = $conn->prepare("INSERT INTO users (fullname, email, password, role, is_verified) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssi", $fullname, $email, $hashed_password, $role, $default_is_verified);

        if ($stmt->execute()) {
            $user_id = $conn->insert_id;
            
            // Generate verification token
            $verificationToken = bin2hex(random_bytes(16));
            
            // Store token in database
            $stmt = $conn->prepare("INSERT INTO verification_tokens (user_id, token, created_at) VALUES (?, ?, ?)");
            $stmt->bind_param("iss", $user_id, $verificationToken, $createdAt);
            $stmt->execute();
            
            // File Upload Handling for Tutors
            $credentials_pdf = null;

            if ($role === 'tutor' && isset($_FILES['credentials_pdf']) && $_FILES['credentials_pdf']['error'] === UPLOAD_ERR_OK) {
                $fileTmpPath = $_FILES['credentials_pdf']['tmp_name'];
                $fileName = $_FILES['credentials_pdf']['name'];

                // Define upload directory
                $uploadDir = 'uploads/credentials/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

                // Generate unique file name to avoid duplicates
                $newFileName = uniqid() . "_" . basename($fileName);
                $destPath = $uploadDir . $newFileName;

                // Move file and save full path in DB
                if (move_uploaded_file($fileTmpPath, $destPath)) {
                    $credentials_pdf = $destPath; 
                }
            }

            // Insert into tutor_profiles with credentials file
            if ($role === 'tutor') {
                $stmt = $conn->prepare("INSERT INTO tutor_profiles (user_id, credentials_pdf, status) VALUES (?, ?, 'pending')");
                $stmt->bind_param("is", $user_id, $credentials_pdf);
                $stmt->execute();
            } else {
                $stmt = $conn->prepare("INSERT INTO student_profiles (user_id) VALUES (?)");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
            }

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
                $mail->addAddress($email);
                
                $mail->isHTML(true);
                $mail->Subject = 'Verify Your PeerTutor Account';
                
                $email_body = "
                    <h2>Welcome to PeerTutor!</h2>
                    <p>Thank you for registering as a $role. Please verify your email address to complete your registration.</p>
                    <p><a href='$verificationLink' style='background-color: #4CAF50; color: white; padding: 10px 20px; text-align: center; text-decoration: none; display: inline-block; border-radius: 5px;'>Verify Email</a></p>
                    <p>Or copy and paste this link into your browser:<br>$verificationLink</p>
                    <p>If you didn't create this account, please ignore this email.</p>";
                
                $mail->Body = $email_body;
                $mail->AltBody = "Please verify your account by visiting this link: $verificationLink";
                
                $mail->send();
                
                // Store email in session for success page
                $_SESSION['registered_email'] = $email;
                header("Location: login.php?success=Registration successful! You can now log in.");
                exit();
            } catch (Exception $e) {
                error_log("Mailer Error: " . $mail->ErrorInfo);
                header("Location: register.php?error=Registration complete but verification email failed to send. Please contact support.");
                exit();
            }
        } else {
            header("Location: register.php?error=Registration failed. Please try again.");
            exit();
        }
    } catch (Exception $e) {
        header("Location: register.php?error=Registration failed. Please try again later.");
        exit();
    }
} else {
    // If accessed directly
    header("Location: register.php");
    exit();
}
?>