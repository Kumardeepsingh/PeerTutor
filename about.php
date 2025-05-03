<?php session_start(); ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us - PeerTutor</title>
    <link rel="stylesheet" href="styles/styles.css">
    <style>
        .about-container {
            max-width: 1000px;
            margin: 2rem auto;
            padding: 0 1.5rem;
        }
        
        .about-section {
            margin-bottom: 3rem;
        }
        
        .team-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 2rem;
            margin-top: 2rem;
        }
        
        .team-member {
            text-align: center;
        }
        
        .team-member img {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 1rem;
        }
        
        .team-member h3 {
            margin-bottom: 0.5rem;
        }
        
        .team-member p {
            color: var(--secondary-text);
            font-size: 0.9rem;
        }
        
        .mission-values {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 2rem;
            margin-top: 2rem;
        }
        
        .value-card {
            background-color: #f9f9f9;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        
        .value-card h3 {
            color: var(--primary);
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar">
        <a href="index.php" class="logo">Peer<span>Tutor</span></a>
        <ul class="nav-links">
            <li><a href="index.php">Home</a></li>
            <li><a href="about.php" class="active">About Us</a></li>
            <?php if(isset($_SESSION['logged_in'])): ?>
                <?php if($_SESSION['role'] === 'admin'): ?>
                    <li><a href="admin_dashboard.php">Dashboard</a></li>
                <?php elseif($_SESSION['role'] === 'tutor'): ?>
                    <li><a href="tutor_dashboard.php">Dashboard</a></li>
                <?php else: ?>
                    <li><a href="student_dashboard.php">Dashboard</a></li>
                <?php endif; ?>
                <li><a href="logout.php" class="btn secondary">Logout</a></li>
            <?php else: ?>
                <li><a href="login.php" class="btn secondary">Login</a></li>
            <?php endif; ?>
        </ul>
    </nav>

    <!-- Main Content -->
    <div class="about-container">
        <header class="page-header">
            <h1>About PeerTutor</h1>
            <p>Connecting students with peer tutors for academic success</p>
        </header>

        <section class="about-section">
            <h2>Our Story</h2>
            <p>PeerTutor was created in 2025 by a group of college students who recognized the powerful impact of peer-to-peer learning. Frustrated with the limitations of traditional tutoring services, they envisioned a platform where students could connect with qualified peers who recently mastered the same courses.</p>
            <p>What started as a campus initiative quickly grew into a comprehensive online platform serving students across multiple universities. Today, PeerTutor continues to expand its network of qualified peer tutors while maintaining our commitment to accessible, high-quality academic support.</p>
        </section>

        <section class="about-section">
            <h2>Our Mission & Values</h2>
            <p>At PeerTutor, we believe learning is most effective when it happens through collaboration with peers who understand the challenges students face.</p>
            
            <div class="mission-values">
                <div class="value-card">
                    <h3>Accessibility</h3>
                    <p>We strive to make quality tutoring accessible to all students regardless of their background or financial situation.</p>
                </div>
                <div class="value-card">
                    <h3>Quality</h3>
                    <p>We verify our tutors' qualifications and monitor session quality to ensure students receive excellent academic support.</p>
                </div>
                <div class="value-card">
                    <h3>Community</h3>
                    <p>We foster a supportive learning community where knowledge sharing and collaboration are encouraged.</p>
                </div>
            </div>
        </section>

        <section class="about-section">
            <h2>How It Works</h2>
            <p>PeerTutor simplifies the process of finding and connecting with qualified peer tutors:</p>
            <ol>
                <li><strong>Sign Up</strong> - Create your free account to access our platform</li>
                <li><strong>Find a Tutor</strong> - Browse profiles of verified peer tutors based on subject, availability, and ratings</li>
                <li><strong>Book a Session</strong> - Schedule a tutoring session at a time that works for you</li>
                <li><strong>Learn & Succeed</strong> - Meet with your tutor online or in-person and improve your understanding</li>
                <li><strong>Rate & Review</strong> - Provide feedback to help other students find the right tutor</li>
            </ol>
        </section>

        <section class="about-section">
            <h2>Become a Tutor</h2>
            <p>Are you passionate about a subject and want to help fellow students while earning money? Consider becoming a PeerTutor!</p>
            <p>We welcome students who excel academically and can effectively communicate concepts to others. As a tutor, you'll set your own schedule and rates while building valuable teaching experience.</p>
        </section>
    </div>

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