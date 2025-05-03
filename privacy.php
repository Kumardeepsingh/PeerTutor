<?php session_start(); ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Privacy Policy - PeerTutor</title>
    <link rel="stylesheet" href="styles/styles.css">
    <style>
        .privacy-container {
            max-width: 1000px;
            margin: 2rem auto;
            padding: 0 1.5rem;
        }
        
        .privacy-section {
            margin-bottom: 2.5rem;
        }
        
        .privacy-section h2 {
            color: var(--primary);
            margin-bottom: 1rem;
        }
        
        .privacy-section h3 {
            margin-top: 1.5rem;
            margin-bottom: 0.75rem;
        }
        
        .privacy-section ul, 
        .privacy-section ol {
            margin-left: 1.5rem;
            margin-bottom: 1rem;
        }
        
        .privacy-section li {
            margin-bottom: 0.5rem;
        }
        
        .last-updated {
            font-style: italic;
            color: var(--secondary-text);
            margin-bottom: 2rem;
        }
        
        .info-box {
            background-color: rgba(52, 152, 219, 0.1);
            border-left: 4px solid var(--primary);
            padding: 1rem;
            margin: 1.5rem 0;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar">
        <a href="index.php" class="logo">Peer<span>Tutor</span></a>
        <ul class="nav-links">
            <li><a href="index.php">Home</a></li>
            <li><a href="about.php">About Us</a></li>
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
    <div class="privacy-container">
        <header class="page-header">
            <h1>Privacy Policy</h1>
            <p class="last-updated">Last Updated: <?php echo date("F d, Y", strtotime("-1 months")); ?></p>
        </header>

        <div class="info-box">
            <p>This Privacy Policy describes how PeerTutor ("we", "our", or "us") collects, uses, and shares your personal information when you use our website and services. Please read this policy carefully to understand our practices regarding your information.</p>
        </div>

        <section class="privacy-section">
            <h2>Information We Collect</h2>
            
            <h3>Personal Information</h3>
            <p>When you create an account or use our services, we may collect the following types of information:</p>
            <ul>
                <li>Contact information (such as name, email address, phone number)</li>
                <li>Academic information (such as university, major, courses)</li>
                <li>Profile information (such as profile picture, bio, education history)</li>
                <li>For tutors: subjects of expertise, hourly rates, availability</li>
                <li>Payment information (processed through our secure payment processors)</li>
            </ul>

            <h3>Usage Information</h3>
            <p>We automatically collect certain information about how you interact with our platform:</p>
            <ul>
                <li>Log data (such as IP address, browser type, pages visited)</li>
                <li>Device information (such as device type, operating system)</li>
                <li>Session information (such as time spent on platform, features used)</li>
            </ul>
        </section>

        <section class="privacy-section">
            <h2>How We Use Your Information</h2>
            <p>We use the information we collect for the following purposes:</p>
            <ul>
                <li>To provide and maintain our services</li>
                <li>To process and manage tutoring sessions</li>
                <li>To match students with appropriate tutors</li>
                <li>To process payments</li>
                <li>To communicate with you about our services</li>
                <li>To send important notices, updates, and support messages</li>
                <li>To verify tutor qualifications and monitor service quality</li>
                <li>To detect and prevent fraudulent activity</li>
                <li>To analyze and improve our platform</li>
            </ul>
        </section>

        <section class="privacy-section">
            <h2>Information Sharing</h2>
            <p>We may share your information in the following circumstances:</p>
            <ul>
                <li><strong>Between Users:</strong> When students and tutors connect, certain profile information is shared to facilitate the tutoring relationship.</li>
                <li><strong>Service Providers:</strong> We work with third-party service providers who perform services on our behalf (such as payment processing, data analysis, email delivery).</li>
                <li><strong>Legal Requirements:</strong> We may disclose information if required by law or in response to valid legal requests.</li>
                <li><strong>Business Transfers:</strong> If PeerTutor is involved in a merger, acquisition, or sale of assets, your information may be transferred as part of that transaction.</li>
            </ul>
            <p>We do not sell your personal information to third parties for marketing purposes.</p>
        </section>

        <section class="privacy-section">
            <h2>Data Security</h2>
            <p>We implement appropriate technical and organizational measures to protect your personal information from unauthorized access, disclosure, alteration, and destruction. However, no method of transmission over the Internet or electronic storage is 100% secure, so we cannot guarantee absolute security.</p>
        </section>

        <section class="privacy-section">
            <h2>Your Rights and Choices</h2>
            <p>Depending on your location, you may have certain rights regarding your personal information:</p>
            <ul>
                <li><strong>Access:</strong> You can request access to the personal information we hold about you.</li>
                <li><strong>Correction:</strong> You can request that we correct inaccurate or incomplete information.</li>
                <li><strong>Deletion:</strong> You can request that we delete your personal information in certain circumstances.</li>
                <li><strong>Account Settings:</strong> You can update your profile and account information through your account settings.</li>
                <li><strong>Communications:</strong> You can opt out of marketing communications by following the unsubscribe instructions in our emails.</li>
            </ul>
            <p>To exercise these rights, please contact us at privacy@peertutor.com.</p>
        </section>

        <section class="privacy-section">
            <h2>Cookies and Tracking Technologies</h2>
            <p>We use cookies and similar tracking technologies to collect information about your browsing activities and to remember your preferences. You can manage your cookie preferences through your browser settings.</p>
        </section>

        <section class="privacy-section">
            <h2>Children's Privacy</h2>
            <p>Our services are not directed to individuals under the age of 16. We do not knowingly collect personal information from children under 16. If we become aware that we have collected personal information from a child under 16, we will take steps to delete such information.</p>
        </section>

        <section class="privacy-section">
            <h2>Changes to This Privacy Policy</h2>
            <p>We may update this Privacy Policy from time to time. The updated version will be indicated by an updated "Last Updated" date. We encourage you to review this Privacy Policy periodically to stay informed about how we are protecting your information.</p>
        </section>

        <section class="privacy-section">
            <h2>Contact Us</h2>
            <p>If you have any questions or concerns about this Privacy Policy or our data practices, please contact us at:</p>
            <p>
                Email: peertutor.webapp@gmail.com<br>
            </p>
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