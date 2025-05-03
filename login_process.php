<?php
// Start session
session_start();

// Include database connection
require_once 'db_connect.php';

// Check if form was submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Get form data and sanitize inputs
    $email = filter_var(htmlspecialchars($_POST['email']), FILTER_SANITIZE_EMAIL);
    $password = htmlspecialchars($_POST['password']);
    
    // Validate inputs
    if (empty($email) || empty($password)) {
        header("Location: login.php?error=Please fill in all fields");
        exit();
    }
    
    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header("Location: login.php?error=Invalid email format");
        exit();
    }
    
    try {
        // Check if user exists
        $stmt = $conn->prepare("SELECT id, fullname, email, password, role, profile_image FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            // User found, verify password
            $user = $result->fetch_assoc();
            
            if (password_verify($password, $user['password'])) {
                // Password is correct, create session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['fullname'] = $user['fullname'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['logged_in'] = true;
                if(!empty($user['profile_image'])){
                    $_SESSION['profile_image'] = $user['profile_image'];
                    }

                
                
                // Redirect based on user role
                if ($user['role'] === 'student') {
                    header("Location: student_dashboard.php");
                } elseif($user['role'] === 'admin') {
                    header("Location: admin_dashboard.php");
                }else{
                    header("Location: tutor_dashboard.php");
                }
                exit();
            } else {
                // Invalid password
                header("Location: login.php?error=Invalid email or password");
                exit();
            }
        } else {
            // User not found
            header("Location: login.php?error=Invalid email or password");
            exit();
        }
    } catch (Exception $e) {
        header("Location: login.php?error=Login failed. Please try again later.");
        exit();
    }
} else {
    // If someone tries to access this page directly
    header("Location: login.php");
    exit();
}
?>