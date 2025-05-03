<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PeerTutor - Connect with Expert Peer Tutors</title>
    <link rel="stylesheet" href="styles/styles.css">
</head>
<body>
    <nav class = "navbar">
        <a href="index.php" class="logo">Peer<span>Tutor</span></a>
        <a href="login.php" class="btn">Login</a>
    </nav>

    <!-- Hero Section -->
    <section class="hero">
        <div class="hero-content">
            <h1>Connect with Expert Peer Tutors</h1>
            <p>Learn from top students who excel in your subjects. Get personalized, one-on-one help to improve your grades and master difficult concepts.</p>
            <div>
                <a href="register.php" class="btn">Find a Tutor</a>
                <a href="register.php?account_type=tutor" class="btn secondary" style="margin-left: 1rem">Become a Tutor</a>
            </div>
            </div>
        </div>
        <div class="hero-image">
            <img src="images/hero.jpg" alt="Students in a tutoring session">
        </div>
    </section>

    <!-- Features Section -->
    <section class="features" id="features">
        <div class="section-title">
            <h2>Why Choose PeerTutor?</h2>
            <p>Our platform offers unique advantages that make learning easier, more effective, and personalized to your needs.</p>
        </div>
        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon">🎯</div>
                <h3>Perfect Match</h3>
                <p>Our matching algorithm connects you with tutors who match your academic goals, and schedule.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">💰</div>
                <h3>Affordable Rates</h3>
                <p>Peer tutors offer competitive rates, making quality education accessible to everyone without breaking the bank.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">🔍</div>
                <h3>Verified Experts</h3>
                <p>All tutors go through a verification process to ensure they have mastered the subjects they teach.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">📱</div>
                <h3>Learn Anywhere</h3>
                <p>Connect with tutors online through our integrated video platform.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">⏰</div>
                <h3>Flexible Scheduling</h3>
                <p>Book sessions at times that work for you</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">🔒</div>
                <h3>Secure Payments</h3>
                <p>Our secure payment system ensures your transactions are protected</p>
            </div>
        </div>
    </section>

    <!-- How It Works -->
    <section class="how-it-works">
        <div class="section-title">
            <h2>How PeerTutor Works</h2>
            <p>Getting started with PeerTutor is easy. Find your perfect tutor in just a few steps.</p>
        </div>
        <div class="steps">
            <div class="step">
                <div class="step-number">1</div>
                <h3>Create an Account</h3>
                <p>Sign up and tell us about your academic needs and learning preferences.</p>
            </div>
            <div class="step">
                <div class="step-number">2</div>
                <h3>Find Your Tutor</h3>
                <p>Browse profiles or use our matching system to find the perfect tutor for your subject.</p>
            </div>
            <div class="step">
                <div class="step-number">3</div>
                <h3>Book a Session</h3>
                <p>Schedule a session at your convenience.</p>
            </div>
            <div class="step">
                <div class="step-number">4</div>
                <h3>Learn & Succeed</h3>
                <p>Connect with your tutor, learn effectively, and improve your grades.</p>
            </div>
        </div>
    </section>

    <!-- Subjects Section -->
    <section class="subjects" id="subjects">
        <div class="section-title">
            <h2>Explore Subjects</h2>
            <p>Our tutors cover a wide range of subjects at all academic levels, from high school to university.</p>
        </div>
        <div class="subjects-grid">
            <div class="subject-card">
                <div class="subject-icon">➗</div>
                <h3>Mathematics</h3>
            </div>
            <div class="subject-card">
                <div class="subject-icon">🧪</div>
                <h3>Chemistry</h3>
            </div>
            <div class="subject-card">
                <div class="subject-icon">🔭</div>
                <h3>Physics</h3>
            </div>
            <div class="subject-card">
                <div class="subject-icon">🧬</div>
                <h3>Biology</h3>
            </div>
            <div class="subject-card">
                <div class="subject-icon">💻</div>
                <h3>Computer Science</h3>
            </div>
            <div class="subject-card">
                <div class="subject-icon">📚</div>
                <h3>Literature</h3>
            </div>
            <div class="subject-card">
                <div class="subject-icon">🌎</div>
                <h3>Languages</h3>
            </div>
            <div class="subject-card">
                <div class="subject-icon">📈</div>
                <h3>Economics</h3>
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