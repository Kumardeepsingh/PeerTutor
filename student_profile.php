<?php session_start();

// Start session and check if user is logged in as a student
if(!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'student'){
    header("Location: login.php?error=Please login to access the student profile");
    exit();
} 

// Include database connection
require_once 'db_connect.php';

// Get student information
$user_id = $_SESSION['user_id'];
$fullname = $_SESSION['fullname'];

// Process form submission
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle profile image upload
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['size'] > 0) {
        $target_dir = "uploads/profile_images/";
        
        // Create directory if it doesn't exist
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        $file_extension = strtolower(pathinfo($_FILES["profile_image"]["name"], PATHINFO_EXTENSION));
        $new_filename = "user_" . $user_id . "_" . time() . "." . $file_extension;
        $target_file = $target_dir . $new_filename;
        
        // Check file size (limit to 5MB)
        if ($_FILES["profile_image"]["size"] > 5000000) {
            $error_message = "Sorry, your file is too large. Maximum size is 5MB.";
        } 
        // Allow certain file formats
        else if (!in_array($file_extension, ["jpg", "jpeg", "png", "gif"])) {
            $error_message = "Sorry, only JPG, JPEG, PNG & GIF files are allowed.";
        } 
        // Upload file
        else if (move_uploaded_file($_FILES["profile_image"]["tmp_name"], $target_file)) {
            // Update database with new image path
            $stmt = $conn->prepare("UPDATE users SET profile_image = ? WHERE id = ?");
            $profile_image_path = $target_file;
            $stmt->bind_param("si", $profile_image_path, $user_id);
            
            if ($stmt->execute()) {
                // Update the session variable immediately
                $_SESSION['profile_image'] = $profile_image_path;
                $success_message = "Profile image updated successfully!";
                
                // Reload the page to show updated image immediately
                header("Location: student_profile.php?success=Profile image updated successfully");
                exit();
            } else {
                $error_message = "Error updating profile image in database.";
            }
        } else {
            $error_message = "Sorry, there was an error uploading your file.";
        }
    }
    
    // Update profile information
    if (isset($_POST['update_profile'])) {
        $school = $_POST['school'];
        $grade_level = $_POST['grade_level'];
        $bio = $_POST['bio'];
        $preferred_languages = $_POST['preferred_languages'];
        
        // Check if student profile exists
        $stmt = $conn->prepare("SELECT id FROM student_profiles WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            // Update existing profile
            $stmt = $conn->prepare("UPDATE student_profiles SET school = ?, grade_level = ?, bio = ?, preferred_languages = ? WHERE user_id = ?");
            $stmt->bind_param("ssssi", $school, $grade_level, $bio, $preferred_languages, $user_id);
        } else {
            // Create new profile
            $stmt = $conn->prepare("INSERT INTO student_profiles (user_id, school, grade_level, bio, preferred_languages) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("issss", $user_id, $school, $grade_level, $bio, $preferred_languages);
        }
        
        if ($stmt->execute()) {
            $success_message = "Profile updated successfully!";
        } else {
            $error_message = "Error updating profile: " . $conn->error;
        }
    }
}

// If profile image is not in session, fetch it from database
if (!isset($_SESSION['profile_image'])) {
    $stmt = $conn->prepare("SELECT profile_image FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user_data = $result->fetch_assoc();
    
    if ($user_data && isset($user_data['profile_image'])) {
        $_SESSION['profile_image'] = $user_data['profile_image'];
    }
}

// Get profile image from session
$profile_image = isset($_SESSION['profile_image']) ? $_SESSION['profile_image'] : '';

// Get student profile
$stmt = $conn->prepare("SELECT * FROM student_profiles WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$student_profile = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Profile - PeerTutor</title>
    <link rel="stylesheet" href="styles/styles.css">
    <link rel="stylesheet" href="styles/dashboard-styles.css">
    <style>
        .profile-form {
            background-color: white;
            border-radius: 10px;
            box-shadow: var(--shadow);
            padding: 2rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--dark);
        }
        
        .form-group input[type="text"],
        .form-group input[type="email"],
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--border);
            border-radius: 5px;
            font-size: 1rem;
        }
        
        .form-group textarea {
            min-height: 120px;
            resize: vertical;
        }
        
        .form-row {
            display: flex;
            gap: 1.5rem;
        }
        
        .form-row .form-group {
            flex: 1;
        }
        
        .profile-image-container {
            margin-bottom: 2rem;
            text-align: center;
        }
        
        .profile-image-preview {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            overflow: hidden;
            margin: 0 auto 1rem;
            border: 3px solid var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: var(--light);
        }
        
        .profile-image-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .profile-initial {
            font-size: 4rem;
            font-weight: bold;
            color: var(--primary);
        }
        
        .image-upload-btn {
            margin-top: 1rem;
        }
        
        .btn-group {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-top: 2rem;
        }
        
        /* File input styling */
        input[type="file"] {
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    
    <!-- Navbar -->
    <nav class="navbar dashboard-nav">
        <a href="student_dashboard.php" class="logo">Peer<span>Tutor</span></a>
        <ul class="nav-links">
            <li><a href="student_dashboard.php" class="active">Dashboard</a></li>
            <li><a href="find_tutors.php">Find Tutors</a></li>
            <li><a href="my_sessions.php">My Sessions</a></li>
            <li><a href="student_profile.php">Profile</a></li>
            <li><a href="logout.php" class="btn secondary">Logout</a></li>
        </ul>
    </nav>

    <!-- Main Content -->
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="dashboard-sidebar">
            <div class="profile-summary">
                <div class="profile-image">
                    <?php if (isset($_SESSION['profile_image']) && !empty($_SESSION['profile_image'])): ?>
                        <img src="<?php echo htmlspecialchars($_SESSION['profile_image']); ?>" alt="Profile Picture">
                    <?php else: ?>
                        <div class="profile-initial"><?php echo substr(htmlspecialchars($fullname), 0, 1); ?></div>
                    <?php endif; ?>
                </div>
                <h3><?php echo htmlspecialchars($fullname); ?></h3>
                <p class="student-info">
                    <?php if (isset($student_profile) && !empty($student_profile['school'])): ?>
                        <?php echo htmlspecialchars($student_profile['school']); ?><br>
                    <?php endif; ?>
                    <?php if (isset($student_profile) && !empty($student_profile['grade_level'])): ?>
                        Grade: <?php echo htmlspecialchars($student_profile['grade_level']); ?>
                    <?php endif; ?>
                </p>
            </div>
            <div class="sidebar-menu">
                <ul>
                    <li><a href="student_dashboard.php"><span>📊</span> Dashboard</a></li>
                    <li><a href="find_tutors.php"><span>🔍</span> Find Tutors</a></li>
                    <li><a href="my_sessions.php"><span>📅</span> My Sessions</a></li>
                    <li><a href="student_reviews.php"><span>⭐</span> My Reviews</a></li>
                </ul>
            </div>
        </aside>

        <!-- Main Dashboard Area -->
        <main class="dashboard-main">
            <h1>My Profile</h1>
            
            <?php if (!empty($success_message) || isset($_GET['success'])): ?>
                <div class="alert alert-success">
                    <p><?php echo !empty($success_message) ? $success_message : htmlspecialchars($_GET['success']); ?></p>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger">
                    <p><?php echo $error_message; ?></p>
                </div>
            <?php endif; ?>

            <div class="profile-form">
                <!-- Profile Image Section - Separate form for image upload -->
                <div class="profile-image-container">
                    <div class="profile-image-preview">
                        <?php if (isset($_SESSION['profile_image']) && !empty($_SESSION['profile_image'])): ?>
                            <img src="<?php echo htmlspecialchars($_SESSION['profile_image']); ?>" alt="Profile Picture">
                        <?php else: ?>
                            <div class="profile-initial"><?php echo substr(htmlspecialchars($fullname), 0, 1); ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- PHP-only file upload form -->
                    <form method="post" action="student_profile.php" enctype="multipart/form-data">
                        <input type="file" name="profile_image" id="profile-image-input">
                        <input type="submit" value="Upload Image" class="btn secondary small image-upload-btn">
                    </form>
                </div>
                
                <!-- Profile Information Form -->
                <form action="student_profile.php" method="post">
                    <!-- Personal Information -->
                    <h2>Personal Information</h2>
                    <div class="form-row">
                        <div class="form-group"><h3>Name: <?php echo htmlspecialchars($fullname); ?></h3>
                        </div>
                    </div>
                    
                    <!-- Educational Information -->
                    <h2>Educational Information</h2>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="school">School/Institution</label>
                            <input type="text" id="school" name="school" value="<?php echo htmlspecialchars($student_profile['school'] ?? ''); ?>" placeholder="Enter your school or institution">
                        </div>
                        <div class="form-group">
                            <label for="grade_level">Grade Level</label>
                            <select id="grade_level" name="grade_level">
                                <option value="">Select Grade Level</option>
                                <option value="Elementary (K-5)" <?php echo (isset($student_profile['grade_level']) && $student_profile['grade_level'] == 'Elementary (K-5)') ? 'selected' : ''; ?>>Elementary (K-5)</option>
                                <option value="Middle School (6-8)" <?php echo (isset($student_profile['grade_level']) && $student_profile['grade_level'] == 'Middle School (6-8)') ? 'selected' : ''; ?>>Middle School (6-8)</option>
                                <option value="High School (9-12)" <?php echo (isset($student_profile['grade_level']) && $student_profile['grade_level'] == 'High School (9-12)') ? 'selected' : ''; ?>>High School (9-12)</option>
                                <option value="Undergraduate" <?php echo (isset($student_profile['grade_level']) && $student_profile['grade_level'] == 'Undergraduate') ? 'selected' : ''; ?>>Undergraduate</option>
                                <option value="Graduate" <?php echo (isset($student_profile['grade_level']) && $student_profile['grade_level'] == 'Graduate') ? 'selected' : ''; ?>>Graduate</option>
                                <option value="Adult Education" <?php echo (isset($student_profile['grade_level']) && $student_profile['grade_level'] == 'Adult Education') ? 'selected' : ''; ?>>Adult Education</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Additional Information -->
                    <h2>Additional Information</h2>
                    <div class="form-group">
                        <label for="bio">Bio</label>
                        <textarea id="bio" name="bio" placeholder="Share a bit about yourself, your learning goals, and what you hope to achieve with tutoring."><?php echo htmlspecialchars($student_profile['bio'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="preferred_languages">Preferred Languages</label>
                        <input type="text" id="preferred_languages" name="preferred_languages" value="<?php echo htmlspecialchars($student_profile['preferred_languages'] ?? ''); ?>" placeholder="e.g. English, Spanish, etc.">
                        <small>Separate multiple languages with commas</small>
                    </div>
                    
                    <div class="btn-group">
                        <a href="student_dashboard.php" class="btn secondary">Cancel</a>
                        <button type="submit" name="update_profile" class="btn">Save Changes</button>
                    </div>
                </form>
            </div>
        </main>
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