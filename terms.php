<?php session_start(); ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Terms of Service - PeerTutor</title>
    <link rel="stylesheet" href="styles/styles.css">
    <style>
        .terms-container {
            max-width: 1000px;
            margin: 2rem auto;
            padding: 0 1.5rem;
        }
        
        .terms-section {
            margin-bottom: 2.5rem;
        }
        
        .terms-section h2 {
            color: var(--primary);
            margin-bottom: 1rem;
        }
        
        .terms-section h3 {
            margin-top: 1.5rem;
            margin-bottom: 0.75rem;
        }
        
        .terms-section ul, 
        .terms-section ol {
            margin-left: 1.5rem;
            margin-bottom: 1rem;
        }
        
        .terms-section li {
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
        
        .highlight {
            font-weight: 600;
            color: var(--primary);
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
    <div class="terms-container">
        <header class="page-header">
            <h1>Terms of Service</h1>
            <p class="last-updated">Last Updated: <?php echo date("F d, Y", strtotime("-1 months")); ?></p>
        </header>

        <div class="info-box">
            <p>These Terms of Service ("Terms") govern your use of the PeerTutor platform and services. By accessing or using PeerTutor, you agree to be bound by these Terms. Please read them carefully.</p>
        </div>

        <section class="terms-section">
            <h2>1. Acceptance of Terms</h2>
            <p>By creating an account, accessing, or using the PeerTutor platform and services ("Services"), you agree to be bound by these Terms of Service, our Privacy Policy, and any additional terms and conditions that may apply. If you do not agree with any of these terms, you may not use our Services.</p>
        </section>

        <section class="terms-section">
            <h2>2. Eligibility</h2>
            <p>To use PeerTutor, you must:</p>
            <ul>
                <li>Be at least 14 years of age (users under 16 must have parental consent)</li>
                <li>Be enrolled in or affiliated with an educational institution (exceptions may apply)</li>
                <li>Have the legal capacity to enter into these Terms</li>
                <li>Not have been previously suspended or removed from our Services</li>
            </ul>
            <p>By using PeerTutor, you represent and warrant that you meet all eligibility requirements.</p>
        </section>

        <section class="terms-section">
            <h2>3. Account Registration</h2>
            <p>To access certain features of our Services, you must create an account. When registering, you agree to:</p>
            <ul>
                <li>Provide accurate, current, and complete information</li>
                <li>Maintain and promptly update your account information</li>
                <li>Keep your password secure and confidential</li>
                <li>Notify us immediately of any unauthorized use of your account</li>
                <li>Be responsible for all activities that occur under your account</li>
            </ul>
            <p>We reserve the right to suspend or terminate accounts that contain false or misleading information or that are used for unauthorized purposes.</p>
        </section>

        <section class="terms-section">
            <h2>4. User Roles and Responsibilities</h2>
            
            <h3>Students</h3>
            <p>As a student using PeerTutor, you agree to:</p>
            <ul>
                <li>Provide accurate information about your academic needs</li>
                <li>Respect scheduled session times and cancellation policies</li>
                <li>Engage in sessions with honesty and academic integrity</li>
                <li>Provide fair and honest feedback about tutors</li>
                <li>Pay for services in accordance with the specified rates and payment terms</li>
            </ul>

            <h3>Tutors</h3>
            <p>As a tutor on PeerTutor, you agree to:</p>
            <ul>
                <li>Provide accurate information about your qualifications and expertise</li>
                <li>Maintain a professional demeanor in all interactions</li>
                <li>Honor scheduled sessions and provide adequate notice for cancellations</li>
                <li>Deliver high-quality tutoring services to the best of your ability</li>
                <li>Respect academic integrity principles and not complete assignments for students</li>
                <li>Comply with all platform policies and guidelines</li>
            </ul>
        </section>

        <section class="terms-section">
            <h2>5. Session Policies</h2>
            
            <h3>Scheduling and Cancellation</h3>
            <p>Sessions must be scheduled through the PeerTutor platform. Our cancellation policy is as follows:</p>
            <ul>
                <li>Cancellations made more than 2 hours before a session: full refund</li>
                <li>Cancellations made less than 2 hours before a session: no refund</li>
                <li>Tutor cancellations may be subject to penalties as outlined in the Tutor Guidelines</li>
            </ul>

            <h3>No-Shows</h3>
            <p>If a student fails to attend a scheduled session without cancellation, they will be charged the full session fee. If a tutor fails to attend, the student will receive a full refund and the tutor may incur penalties.</p>
        </section>

        <section class="terms-section">
            <h2>6. Payments and Fees</h2>
            <p>By using PeerTutor, you agree to the following payment terms:</p>
            <ul>
                <li>All payments must be processed through the PeerTutor platform</li>
                <li>Tutors set their own hourly rates within platform guidelines</li>
                <li>PeerTutor charges a service fee (percentage of the session cost) to maintain the platform</li>
                <li>Students are charged when booking a session</li>
                <li>Tutors receive payment after the successful completion of sessions</li>
                <li>Payment disputes must be submitted within 7 days of the session</li>
            </ul>
            <p class="highlight">Direct payments between students and tutors outside the platform are strictly prohibited and may result in account suspension.</p>
        </section>

        <section class="terms-section">
            <h2>7. Academic Integrity</h2>
            <p>PeerTutor is committed to academic integrity. The following activities are prohibited:</p>
            <ul>
                <li>Completing assignments or exams for students</li>
                <li>Writing papers or assignments to be submitted as the student's own work</li>
                <li>Providing answers during active exams or assessments</li>
                <li>Any activity that violates educational institutions' academic codes of conduct</li>
            </ul>
            <p>Violations of this policy may result in immediate termination of your account and forfeiture of any pending payments.</p>
        </section>

        <section class="terms-section">
            <h2>8. Code of Conduct</h2>
            <p>All users must adhere to the following code of conduct:</p>
            <ul>
                <li>Treat all users with respect and courtesy</li>
                <li>Do not engage in discriminatory behavior or harassment</li>
                <li>Do not share inappropriate or offensive content</li>
                <li>Do not use the platform for non-educational purposes</li>
                <li>Do not attempt to contact other users outside the platform for tutoring services</li>
                <li>Report any violations or concerns to our support team</li>
            </ul>
        </section>

        <section class="terms-section">
            <h2>9. Content and Intellectual Property</h2>
            <p>By using PeerTutor, you agree that:</p>
            <ul>
                <li>Content you upload to the platform must not infringe on others' intellectual property rights</li>
                <li>You grant PeerTutor a non-exclusive license to use content you share for platform operations</li>
                <li>PeerTutor owns all intellectual property rights related to our platform, features, and services</li>
                <li>You may not copy, modify, distribute, or reverse engineer any aspect of our platform</li>
            </ul>
        </section>

        <section class="terms-section">
            <h2>10. Privacy</h2>
            <p>Your privacy is important to us. Our Privacy Policy describes how we collect, use, and share your information. By using PeerTutor, you consent to our data practices as described in our <a href="privacy.php">Privacy Policy</a>.</p>
        </section>

        <section class="terms-section">
            <h2>11. Disclaimer of Warranties</h2>
            <p>The PeerTutor platform is provided "as is" and "as available" without warranties of any kind, either express or implied. We do not guarantee that:</p>
            <ul>
                <li>The platform will always be secure, error-free, or available</li>
                <li>Results obtained from using our services will be accurate or reliable</li>
                <li>All tutors will meet your expectations</li>
                <li>Any errors in the platform will be corrected</li>
            </ul>
        </section>

        <section class="terms-section">
            <h2>12. Limitation of Liability</h2>
            <p>To the maximum extent permitted by law, PeerTutor shall not be liable for any indirect, incidental, special, consequential, or punitive damages resulting from your use or inability to use the platform, unauthorized access to or alteration of your content, or any other matter related to the services.</p>
        </section>

        <section class="terms-section">
            <h2>13. Termination</h2>
            <p>We reserve the right to suspend or terminate your account at any time for violations of these Terms or for any other reason at our discretion. You may also terminate your account at any time, subject to any outstanding obligations.</p>
        </section>

        <section class="terms-section">
            <h2>14. Changes to Terms</h2>
            <p>We may update these Terms from time to time. The updated version will be effective when posted on this page with an updated effective date. Your continued use of PeerTutor after changes to the Terms constitutes your acceptance of the revised Terms.</p>
        </section>

        <section class="terms-section">
            <h2>15. Contact Information</h2>
            <p>If you have any questions about these Terms, please contact us at:</p>
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