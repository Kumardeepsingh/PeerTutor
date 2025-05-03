<?php
// Start session and check if user is logged in as a tutor
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'tutor') {
    header("Location: login.php?error=Please login to access the tutor profile");
    exit();
}

// Include database connection
require_once 'db_connect.php';

// Get tutor information
$user_id = $_SESSION['user_id'];
$fullname = $_SESSION['fullname'];

// Get tutor profile
$stmt = $conn->prepare("SELECT * FROM tutor_profiles WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$tutor_result = $stmt->get_result();
$tutor_profile = $tutor_result->fetch_assoc();

// Process form submission
$success_message = "";
$error_message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    if (isset($_POST['remove_subject']) && isset($_POST['subject_id'])) {
        // Handle subject removal
        $subject_id = $_POST['subject_id'];
        
        $stmt = $conn->prepare("
            DELETE FROM tutor_subjects 
            WHERE tutor_profile_id = ? AND subject_id = ?
        ");
        $stmt->bind_param("ii", $tutor_profile['id'], $subject_id);
        
        if ($stmt->execute()) {
            $success_message = "Subject removed successfully!";
            // Optionally redirect to refresh the page
            header("Location: tutor_profile.php?success=subject_removed");
            exit();
        } else {
            $error_message = "Error removing subject: " . $conn->error;
        }
    } 
    elseif (isset($_POST['save_basic_info'])) {
        // Update basic profile information only
        $education = $_POST['education'];
        $hourly_rate = $_POST['hourly_rate'];
        $bio = $_POST['bio'];
        $preferred_languages = $_POST['preferred_languages'];
        
        $stmt = $conn->prepare("
            UPDATE tutor_profiles 
            SET education = ?, hourly_rate = ?, bio = ?, preferred_languages = ? 
            WHERE user_id = ?
        ");
        $stmt->bind_param("sdssi", $education, $hourly_rate, $bio, $preferred_languages, $user_id);
        
        if ($stmt->execute()) {
            $success_message = "Profile information updated successfully!";
            header("Location: tutor_profile.php?success=basic_info_saved");
            exit();
        } else {
            $error_message = "Error updating profile: " . $conn->error;
        }
    }
    elseif (isset($_POST['update_profile_image'])) {
        // Process profile image upload
        if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            $max_size = 2 * 1024 * 1024; // 2MB
            
            if (!in_array($_FILES['profile_image']['type'], $allowed_types)) {
                $error_message = "Only JPG, PNG, and GIF images are allowed.";
            } elseif ($_FILES['profile_image']['size'] > $max_size) {
                $error_message = "Image size should be less than 2MB.";
            } else {
                $upload_dir = 'uploads/profile_images/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $filename = $user_id . '_' . time() . '_' . basename($_FILES['profile_image']['name']);
                $target_file = $upload_dir . $filename;
                
                if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $target_file)) {
                    // Update profile image in the database
                    $stmt = $conn->prepare("UPDATE users SET profile_image = ? WHERE id = ?");
                    $stmt->bind_param("si", $target_file, $user_id);
                    
                    if ($stmt->execute()) {
                        $_SESSION['profile_image'] = $target_file;
                        $success_message = "Profile image updated successfully!";
                        header("Location: tutor_profile.php?success=image_updated");
                        exit();
                    } else {
                        $error_message = "Error updating profile image in database.";
                    }
                } else {
                    $error_message = "Error uploading image.";
                }
            }
        } else {
            $error_message = "Please select an image file to upload.";
        }
    }
    elseif (isset($_POST['update_credentials'])) {
        // Process credentials PDF upload
        if (isset($_FILES['credentials_pdf']) && $_FILES['credentials_pdf']['error'] === UPLOAD_ERR_OK) {
            $allowed_types = ['application/pdf'];
            $max_size = 5 * 1024 * 1024; // 5MB
            
            if (!in_array($_FILES['credentials_pdf']['type'], $allowed_types)) {
                $error_message = "Only PDF files are allowed for credentials.";
            } elseif ($_FILES['credentials_pdf']['size'] > $max_size) {
                $error_message = "PDF size should be less than 5MB.";
            } else {
                $upload_dir = 'uploads/credentials/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $filename = $user_id . '_credentials_' . time() . '_' . basename($_FILES['credentials_pdf']['name']);
                $target_file = $upload_dir . $filename;
                
                if (move_uploaded_file($_FILES['credentials_pdf']['tmp_name'], $target_file)) {
                    // Update credentials PDF in the database
                    $stmt = $conn->prepare("UPDATE tutor_profiles SET credentials_pdf = ? WHERE user_id = ?");
                    $stmt->bind_param("si", $target_file, $user_id);
                    
                    if ($stmt->execute()) {
                        $success_message = "Credentials PDF uploaded successfully!";
                        header("Location: tutor_profile.php?success=credentials_updated");
                        exit();
                    } else {
                        $error_message = "Error updating credentials PDF in database.";
                    }
                } else {
                    $error_message = "Error uploading credentials PDF.";
                }
            }
        } else {
            $error_message = "Please select a PDF file to upload.";
        }
    }
    elseif (isset($_POST['add_subject'])) {
        // Process subject addition
        if (!empty($_POST['subject_id']) && !empty($_POST['proficiency_level'])) {
            $subject_id = $_POST['subject_id'];
            $proficiency_level = $_POST['proficiency_level'];
            
            // Check if subject already exists
            $stmt = $conn->prepare("
                SELECT * FROM tutor_subjects 
                WHERE tutor_profile_id = ? AND subject_id = ?
            ");
            $stmt->bind_param("ii", $tutor_profile['id'], $subject_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $error_message = "You have already added this subject to your profile.";
            } else {
                $stmt = $conn->prepare("
                    INSERT INTO tutor_subjects (tutor_profile_id, subject_id, proficiency_level)
                    VALUES (?, ?, ?)
                ");
                $stmt->bind_param("iis", $tutor_profile['id'], $subject_id, $proficiency_level);
                
                if ($stmt->execute()) {
                    $success_message = "Subject added successfully!";
                    header("Location: tutor_profile.php?success=subject_added");
                    exit();
                } else {
                    $error_message = "Error adding subject: " . $conn->error;
                }
            }
        } else {
            $error_message = "Please select both a subject and proficiency level.";
        }
    }
}

// Handle success parameter from redirect
if (isset($_GET['success'])) {
    if ($_GET['success'] == 'basic_info_saved') {
        $success_message = "Profile information updated successfully!";
    } elseif ($_GET['success'] == 'image_updated') {
        $success_message = "Profile image updated successfully!";
    } elseif ($_GET['success'] == 'credentials_updated') {
        $success_message = "Credentials PDF uploaded successfully!";
    } elseif ($_GET['success'] == 'subject_removed') {
        $success_message = "Subject removed successfully!";
    } elseif ($_GET['success'] == 'subject_added') {
        $success_message = "Subject added successfully!";
    }
}

// Get tutor subjects (after form processing to reflect any changes)
$stmt = $conn->prepare("
    SELECT s.id, s.name, s.category, ts.proficiency_level
    FROM tutor_subjects ts
    JOIN subjects s ON ts.subject_id = s.id
    JOIN tutor_profiles tp ON ts.tutor_profile_id = tp.id
    WHERE tp.user_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$tutor_subjects = $stmt->get_result();

// Get all available subjects for the dropdown
$stmt = $conn->prepare("
    SELECT s.id, s.name, s.category
    FROM subjects s
    ORDER BY s.category, s.name
");
$stmt->execute();
$all_subjects = $stmt->get_result();

// Get all subjects grouped by category
$all_subjects_by_category = [];
while ($subject = $all_subjects->fetch_assoc()) {
    $all_subjects_by_category[$subject['category']][] = $subject;
}

// Calculate average rating
$stmt = $conn->prepare("
    SELECT AVG(rating) as avg_rating, COUNT(*) as total_reviews
    FROM reviews
    WHERE tutor_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$rating_result = $stmt->get_result();
$rating_data = $rating_result->fetch_assoc();
$avg_rating = round($rating_data['avg_rating'], 1) ?: 0;
$total_reviews = $rating_data['total_reviews'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tutor Profile - PeerTutor</title>
    <link rel="stylesheet" href="styles/styles.css">
    <link rel="stylesheet" href="styles/dashboard-styles.css">
    <link rel="stylesheet" href="styles/tutor-profile-styles.css">
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar dashboard-nav">
        <a href="tutor_dashboard.php" class="logo">Peer<span>Tutor</span></a>
        <ul class="nav-links">
            <li><a href="tutor_dashboard.php" class="active">Dashboard</a></li>
            <li><a href="my_sessions.php">Sessions</a></li>
            <li><a href="tutor_profile.php">Profile</a></li>
            <li><a href="tutor_availability.php">Availability</a></li>
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
                <p class="approval-status status-<?php echo htmlspecialchars($tutor_profile['status']); ?>">
                    <?php echo ucfirst(htmlspecialchars($tutor_profile['status'])); ?>
                </p>
                <div class="rating-summary">
                    <div class="stars">
                        <?php
                        for ($i = 1; $i <= 5; $i++) {
                            if ($i <= floor($avg_rating)) {
                                echo '<span class="star filled">★</span>';
                            } elseif ($i - 0.5 <= $avg_rating) {
                                echo '<span class="star half">★</span>';
                            } else {
                                echo '<span class="star">★</span>';
                            }
                        }
                        ?>
                    </div>
                    <p><?php echo $avg_rating; ?> (<?php echo $total_reviews; ?> reviews)</p>
                </div>
                <p class="hourly-rate">$<?php echo number_format($tutor_profile['hourly_rate'], 2); ?>/hour</p>
            </div>
            
            <div class="sidebar-menu">
                <ul>
                    <li><a href="tutor_dashboard.php"><span>📊</span> Dashboard</a></li>
                    <li><a href="my_sessions.php"><span>📅</span> My Sessions</a></li>
                    <li><a href="tutor_availability.php"><span>⏰</span> Set Availability</a></li>
                    <li><a href="tutor_reviews.php"><span>⭐</span> My Reviews</a></li>
                </ul>
            </div>
        </aside>

        <!-- Main Profile Area -->
        <main class="dashboard-main">
            <h1>Tutor Profile</h1>
            
            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success">
                    <p><?php echo $success_message; ?></p>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger">
                    <p><?php echo $error_message; ?></p>
                </div>
            <?php endif; ?>
            
            <?php if ($tutor_profile['status'] === 'pending'): ?>
                <div class="alert alert-info">
                    <p><strong>Your tutor application is pending review.</strong> Please complete your profile to speed up the approval process.</p>
                </div>
            <?php elseif ($tutor_profile['status'] === 'rejected'): ?>
                <div class="alert alert-danger">
                    <p><strong>Your tutor application was not approved.</strong> Please update your profile with more information about your qualifications and experience, then resubmit for review.</p>
                </div>
            <?php endif; ?>
            
            <!-- Basic Information Section -->
            <div class="form-section">
                <h2>Basic Information</h2>
                <form action="tutor_profile.php" method="post" class="profile-form">
                    <h3>Full Name: <?php echo htmlspecialchars($fullname); ?></h3>
                    
                    <div class="form-group">
                        <label for="education">Education</label>
                        <input type="text" id="education" name="education" value="<?php echo htmlspecialchars($tutor_profile['education'] ?? ''); ?>" required>
                        <p class="form-note">e.g., "B.Sc. Computer Science, XYZ University"</p>
                    </div>
                    
                    <div class="form-group">
                        <label for="hourly_rate">Hourly Rate ($)</label>
                        <input type="number" id="hourly_rate" name="hourly_rate" min="5" step="0.01" value="<?php echo htmlspecialchars($tutor_profile['hourly_rate'] ?? 15.00); ?>" required>
                        <p class="form-note">Set your hourly tutoring rate (minimum $5.00)</p>
                    </div>
                    
                    <div class="form-group">
                        <label for="bio">Bio</label>
                        <textarea id="bio" name="bio" rows="6" required><?php echo htmlspecialchars($tutor_profile['bio'] ?? ''); ?></textarea>
                        <p class="form-note">Write a brief bio about yourself, your teaching style, and what students can expect.</p>
                    </div>
                    
                    <div class="form-group">
                        <label for="preferred_languages">Preferred Languages</label>
                        <input type="text" id="preferred_languages" name="preferred_languages" value="<?php echo htmlspecialchars($tutor_profile['preferred_languages'] ?? ''); ?>" placeholder="e.g. English, Spanish, etc.">
                        <p class="form-note">Separate multiple languages with commas. These are languages you're comfortable tutoring in.</p>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" name="save_basic_info" class="btn">Save Basic Information</button>
                    </div>
                </form>
            </div>
                
            <!-- Profile Image Section -->
            <div class="form-section">
                <h2>Profile Image</h2>
                <form action="tutor_profile.php" method="post" enctype="multipart/form-data" class="profile-form">
                    <div class="current-image">
                        <h3>Current Profile Image</h3>
                        <div class="image-preview">
                            <?php if (isset($_SESSION['profile_image']) && !empty($_SESSION['profile_image'])): ?>
                                <img src="<?php echo htmlspecialchars($_SESSION['profile_image']); ?>" alt="Current Profile Picture">
                            <?php else: ?>
                                <div class="profile-initial large"><?php echo substr(htmlspecialchars($fullname), 0, 1); ?></div>
                                <p>No profile image set</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="profile_image">Select New Image</label>
                        <input type="file" id="profile_image" name="profile_image" accept="image/jpeg, image/png, image/gif" required>
                        <p class="form-note">Maximum file size: 2MB. Supported formats: JPG, PNG, GIF.</p>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" name="update_profile_image" class="btn">Update Profile Image</button>
                    </div>
                </form>
            </div>
                
            <!-- Credentials Document Section -->
            <div class="form-section">
                <h2>Credentials Document</h2>
                <form action="tutor_profile.php" method="post" enctype="multipart/form-data" class="profile-form">
                    <div class="current-credentials">
                        <h3>Current Credentials Document</h3>
                        <?php if (isset($tutor_profile['credentials_pdf']) && !empty($tutor_profile['credentials_pdf'])): ?>
                            <div class="credentials-info">
                                <p class="file-name"><?php echo basename(htmlspecialchars($tutor_profile['credentials_pdf'])); ?></p>
                                <a href="<?php echo htmlspecialchars($tutor_profile['credentials_pdf']); ?>" target="_blank" class="btn btn-sm">View Document</a>
                            </div>
                        <?php else: ?>
                            <p>No credentials document uploaded</p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label for="credentials_pdf">Upload Credentials (PDF)</label>
                        <input type="file" id="credentials_pdf" name="credentials_pdf" accept="application/pdf" required>
                        <p class="form-note">Upload your certificates, transcripts, or other credential documents (PDF only, max 5MB).</p>
                        <p class="form-note">These documents help verify your expertise and increase trust with potential students.</p>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" name="update_credentials" class="btn">Update Credentials</button>
                    </div>
                </form>
            </div>
                
            <!-- Subjects Section -->
            <div class="form-section">
                <h2>Subjects</h2>
                
                <?php if ($tutor_subjects->num_rows > 0): ?>
                    <div class="subjects-container">
                        <?php 
                        $categories = [];
                        while ($subject = $tutor_subjects->fetch_assoc()) {
                            $categories[$subject['category']][] = $subject;
                        }
                        
                        foreach ($categories as $category => $subjects): ?>
                            <div class="subject-category">
                                <h3><?php echo htmlspecialchars($category); ?></h3>
                                <div class="subject-list">
                                    <?php foreach ($subjects as $subject): ?>
                                        <div class="subject-badge <?php echo strtolower($subject['proficiency_level']); ?>">
                                            <?php echo htmlspecialchars($subject['name']); ?>
                                            <span class="proficiency"><?php echo ucfirst($subject['proficiency_level']); ?></span>
                                            <form action="tutor_profile.php" method="post" class="subject-remove">
                                                <input type="hidden" name="subject_id" value="<?php echo $subject['id']; ?>">
                                                <button type="submit" name="remove_subject" class="remove-btn" title="Remove Subject">×</button>
                                            </form>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <p>You haven't added any subjects yet.</p>
                    </div>
                <?php endif; ?>
                
                <div class="add-subject-form">
                    <h3>Add a Subject</h3>
                    <form action="tutor_profile.php" method="post">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="subject_id">Subject</label>
                                <select id="subject_id" name="subject_id">
                                    <option value="">Select a subject</option>
                                    <?php foreach ($all_subjects_by_category as $category => $subjects): ?>
                                        <optgroup label="<?php echo htmlspecialchars($category); ?>">
                                            <?php foreach ($subjects as $subject): ?>
                                                <option value="<?php echo $subject['id']; ?>">
                                                    <?php echo htmlspecialchars($subject['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="proficiency_level">Proficiency Level</label>
                                <select id="proficiency_level" name="proficiency_level">
                                    <option value="beginner">Beginner</option>
                                    <option value="intermediate">Intermediate</option>
                                    <option value="advanced">Advanced</option>
                                    <option value="expert">Expert</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <button type="submit" name="add_subject" class="btn">Add Subject</button>
                            </div>
                        </div>
                    </form>
                </div>
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