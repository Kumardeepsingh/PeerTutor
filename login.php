<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - PeerTutor</title>
    <link rel="stylesheet" href="styles/styles.css">
    <link rel="stylesheet" href="styles/auth-styles.css">
</head>
<body>

<nav class="navbar">
        <a href="index.php" class="logo">Peer<span>Tutor</span></a>
        <a href="register.php" class="btn secondary">Register</a>
    </nav>

    <!-- Login Form Section -->
    <section class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <h2>Welcome Back</h2>
                <p>Login to access your account and connect with tutors</p>
            </div>
            
            <form action="login_process.php" method="POST" class="auth-form">
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
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required placeholder="Enter your password">
                </div>
                
                <div class="form-options">
                    <a href="forgot_password.php" class="forgot-link">Forgot Password?</a>
                </div>
                
                <button type="submit" class="btn auth-btn">Login</button>
                
                <div class="auth-separator">
                    <span>OR</span>
                </div>
            </form>
            
            <div class="auth-footer">
                <p>Don't have an account? <a href="register.php">Register</a></p>
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
    
</body>
</html>