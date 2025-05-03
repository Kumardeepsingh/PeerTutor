<?php
// Start the session
session_start();

// Initialize variables
$account_type = isset($_GET['account_type']) ? $_GET['account_type'] : 'student'; // Default is student
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - PeerTutor</title>
    <link rel="stylesheet" href="styles/styles.css">
    <link rel="stylesheet" href="styles/auth-styles.css">
</head>
<body>
<nav class="navbar">
    <a href="index.php" class="logo">Peer<span>Tutor</span></a>
    <a href="login.php" class="btn secondary">Login</a>
</nav>

<!-- Registration Form Section -->
<section class="auth-container">
    <div class="auth-card registration">
        <div class="auth-header">
            <h2>Create an Account</h2>
            <p>Join PeerTutor to connect with expert peer tutors</p>
        </div>
        
        <form action="register_process.php" method="POST" class="auth-form" enctype="multipart/form-data">
            <?php if(isset($_GET['error'])) { ?>
                <div class="error-message">
                    <?php echo htmlspecialchars($_GET['error']); ?>
                </div>
            <?php } ?>
            
            <div class="form-group">
                <label for="fullname">Full Name</label>
                <input type="text" id="fullname" name="fullname" required placeholder="Enter your full name">
            </div>
            
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" required placeholder="Enter your email">
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required placeholder="Create a password">
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required placeholder="Confirm your password">
                </div>
            </div>
            
            <div class="form-group">
                <label>Account Type</label>
                <div class="role-selector">
                    <div class="role-option">
                        <input type="radio" id="role_student" name="role" value="student" <?php echo ($account_type == 'student') ? 'checked' : ''; ?>>
                        <label for="role_student">I'm a Student</label>
                    </div>
                    <div class="role-option">
                        <input type="radio" id="role_tutor" name="role" value="tutor" <?php echo ($account_type == 'tutor') ? 'checked' : ''; ?>>
                        <label for="role_tutor">I'm a Tutor</label>
                    </div>
                </div>
            </div>
            
           
            <div id="credentials_field" style="display: <?php echo ($account_type == 'tutor') ? 'block' : 'none'; ?>;">
                <div class="form-group">
                    <label for="credentials_pdf">Upload Your Credentials (PDF)</label>
                    <input type="file" name="credentials_pdf" id="credentials_pdf" accept=".pdf">
                </div>
            </div>
            
            <div class="form-group terms">
                <input type="checkbox" id="terms" name="terms" required>
                <label for="terms">I agree to the <a href="terms.php">Terms of Service</a> and <a href="privacy.php">Privacy Policy</a></label>
            </div>
            
            <button type="submit" class="btn auth-btn">Create Account</button>
        </form>
        
        <div class="auth-footer">
            <p>Already have an account? <a href="login.php">Login</a></p>
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

<script>
    const tutorRadio = document.getElementById('role_tutor');
    const credentialsField = document.getElementById('credentials_field');

    // Toggle the visibility of the credentials input when account type changes
    tutorRadio.addEventListener('change', function() {
        if (tutorRadio.checked) {
            credentialsField.style.display = 'block';
        }
    });

    const studentRadio = document.getElementById('role_student');
    studentRadio.addEventListener('change', function() {
        if (studentRadio.checked) {
            credentialsField.style.display = 'none';
        }
    });
</script>

</body>
</html>
