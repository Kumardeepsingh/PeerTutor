<?php
session_start();

// Check if user is logged in
if(!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'student'){
    header("Location: login.php?error=Please login to find tutors");
    exit();
}

// Include database connection
require_once 'db_connect.php';


// Get all subjects for the filter
$subject_query = "SELECT * FROM subjects ORDER BY category, name";
$subject_result = $conn->query($subject_query);

// Initialize variables for search
$subjects = [];
$languages = [];
$rating = "";
$min_price = "";
$max_price = "";
$day = "";
$search_query = "";

// Process search form if submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get search parameters
    $subjects = $_POST['subjects'] ?? [];
    $languages = $_POST['languages'] ?? [];
    $rating = $_POST['rating'] ?? "";
    $min_price = $_POST['min_price'] ?? "";
    $max_price = $_POST['max_price'] ?? "";
    $day = $_POST['day'] ?? "";
    $search_query = $_POST['search_query'] ?? "";

    // Start with the base query
    $sql = "SELECT DISTINCT u.id, u.fullname, u.profile_image, tp.hourly_rate, tp.bio, tp.id AS tutor_profile_id,
            (SELECT AVG(rating) FROM reviews WHERE tutor_id = u.id) AS avg_rating,
            (SELECT COUNT(*) FROM reviews WHERE tutor_id = u.id) AS review_count,
            (SELECT COUNT(*) FROM sessions WHERE tutor_id = u.id AND status = 'completed') AS completed_sessions
            FROM users u
            JOIN tutor_profiles tp ON u.id = tp.user_id
            WHERE u.role = 'tutor' AND tp.status = 'approved' AND u.is_verified = 1";

    // Add filters conditionally

    // Filter by subjects
    if (!empty($subjects)) {
        $subject_placeholders = implode(',', array_fill(0, count($subjects), '?'));
        $sql .= " AND tp.id IN (SELECT ts.tutor_profile_id FROM tutor_subjects ts WHERE ts.subject_id IN ($subject_placeholders))";
    }

    // Filter by languages
    if (!empty($languages)) {
        // Build the query to match any of the languages using LIKE
        $sql .= " AND (" . implode(' OR ', array_fill(0, count($languages), "tp.preferred_languages LIKE ?")) . ")";
    }

    // Filter by availability (day of the week)
    if (!empty($day)) {
        $sql .= " AND EXISTS (SELECT 1 FROM tutor_availability ta WHERE ta.tutor_id = u.id AND ta.day_of_week = ?)";
    }

    // Filter by rating
    if (!empty($rating)) {
        $sql .= " HAVING avg_rating >= ?";
    }

    // Filter by price range
    if (!empty($min_price)) {
        $sql .= " AND tp.hourly_rate >= ?";
    }

    if (!empty($max_price)) {
        $sql .= " AND tp.hourly_rate <= ?";
    }

    // Filter by search query (name or bio)
    if (!empty($search_query)) {
        $sql .= " AND (u.fullname LIKE ? OR tp.bio LIKE ?)";
    }

    // Add sorting by rating, review count, and completed sessions
    $sql .= " ORDER BY avg_rating DESC, review_count DESC, completed_sessions DESC";

    // Prepare statement
    $stmt = $conn->prepare($sql);

    // Bind parameters
    $bind_params = [];
    $bind_types = "";

    // Add subject parameters
    if (!empty($subjects)) {
        foreach ($subjects as $subject_id) {
            $bind_types .= "i";
            $bind_params[] = $subject_id;
        }
    }

    // Add language parameter
    if (!empty($languages)) {
        foreach ($languages as $language) {
            $bind_types .= "s";
            $bind_params[] = "%$language%";
        }
    }

    // Add day parameter
    if (!empty($day)) {
        $bind_types .= "s";
        $bind_params[] = $day;
    }

    // Add rating parameter
    if (!empty($rating)) {
        $bind_types .= "d";
        $bind_params[] = $rating;
    }

    // Add price range parameters
    if (!empty($min_price)) {
        $bind_types .= "d";
        $bind_params[] = $min_price;
    }

    if (!empty($max_price)) {
        $bind_types .= "d";
        $bind_params[] = $max_price;
    }

    // Add search query parameters
    if (!empty($search_query)) {
        $search_param = "%$search_query%";
        $bind_types .= "ss";
        $bind_params[] = $search_param;
        $bind_params[] = $search_param;
    }

    // Bind parameters if any
    if (!empty($bind_params)) {
        $stmt->bind_param($bind_types, ...$bind_params);
    }

    // Execute query
    $stmt->execute();
    $tutors_result = $stmt->get_result();
} else {
    // Default query - just get all approved tutors
    $sql = "SELECT DISTINCT u.id, u.fullname, u.profile_image, tp.hourly_rate, tp.bio, tp.id AS tutor_profile_id,
            (SELECT AVG(rating) FROM reviews WHERE tutor_id = u.id) AS avg_rating,
            (SELECT COUNT(*) FROM reviews WHERE tutor_id = u.id) AS review_count,
            (SELECT COUNT(*) FROM sessions WHERE tutor_id = u.id AND status = 'completed') AS completed_sessions
            FROM users u
            JOIN tutor_profiles tp ON u.id = tp.user_id
            WHERE u.role = 'tutor' AND tp.status = 'approved' AND u.is_verified = 1
            ORDER BY avg_rating DESC, review_count DESC, completed_sessions DESC";
    
    $tutors_result = $conn->query($sql);
}

// Get list of languages for the filter
$languages_list = [
    'English', 'Spanish', 'French', 'German', 'Chinese', 'Japanese', 'Korean', 'Arabic', 'Hindi', 'Portuguese', 'Russian', 'Punjabi'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Find a Tutor - PeerTutor</title>
    <link rel="stylesheet" href="styles/styles.css">
    <style>
        .filters-column {
            flex: 0 0 250px;
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
        }
        
        .tutors-column {
            flex: 1;
            padding-left: 20px;
        }
        
        .tutor-card {
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            background-color: white;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        
        .tutor-header {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .tutor-image {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            margin-right: 15px;
            background-color: #e0e0e0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: #555;
        }
        
        .tutor-image img {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
        }
        
        .tutor-info {
            flex: 1;
        }
        
        .tutor-name {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .tutor-rating {
            display: flex;
            align-items: center;
            margin-bottom: 5px;
        }
        
        .tutor-stars {
            margin-right: 5px;
        }
        
        .tutor-stats {
            font-size: 14px;
            color: #666;
            margin-bottom: 5px;
        }
        
        .tutor-price {
            font-weight: bold;
            color: #333;
        }
        
        .tutor-bio {
            margin: 15px 0;
            font-size: 14px;
            color: #555;
        }
        
        .tutor-subjects {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 15px;
        }
        
        .subject-badge {
            background-color: #e9f5ff;
            color: #0066cc;
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 13px;
        }
        
        .tutor-languages {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 15px;
        }
        
        .language-badge {
            background-color: #f5f5f5;
            color: #555;
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 13px;
        }
        
        .find-tutor-container {
            display: flex;
            max-width: 1200px;
            margin: 30px auto;
            gap: 20px;
        }
        
        .filter-group {
            margin-bottom: 20px;
        }
        
        .filter-group h3 {
            margin-bottom: 10px;
            font-size: 16px;
            color: #333;
        }
        
        .price-range {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .price-input {
            width: 80px;
        }
        
        .star {
            color: #ddd;
            font-size: 18px;
        }
        
        .star.filled {
            color: #ffc107;
        }
        
        .star.half {
            position: relative;
        }
        
        .star.half:after {
            content: '★';
            color: #ffc107;
            position: absolute;
            left: 0;
            top: 0;
            width: 50%;
            overflow: hidden;
        }
        
        .filter-actions {
            margin-top: 20px;
        }
        
        .empty-results {
            padding: 30px;
            text-align: center;
            background-color: #f8f9fa;
            border-radius: 8px;
        }
        
        .tutor-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .total-results {
            margin: 0 0 20px;
            color: #666;
        }
        
        @media (max-width: 768px) {
            .find-tutor-container {
                flex-direction: column;
            }
            
            .filters-column {
                flex: 0 0 auto;
                margin-bottom: 20px;
            }
            
            .tutors-column {
                padding-left: 0;
            }
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
    <div class="container">
        <h1 class="page-title">Find a Tutor</h1>
        <p class="page-subtitle">Search for tutors by subject, language, price, and availability</p>
        
        <div class="find-tutor-container">
            <!-- Filters Column -->
            <div class="filters-column">
                <h2>Filter Tutors</h2>
                <form method="post" action="">
                    <!-- Search by name or bio -->
                    <div class="filter-group">
                        <h3>Search</h3>
                        <input type="text" name="search_query" class="form-control" placeholder="Search by name or bio" value="<?php echo htmlspecialchars($search_query); ?>">
                    </div>
                    
                    <!-- Subject Filter -->
                    <div class="filter-group">
                        <h3>Subjects</h3>
                        <?php
                        $current_category = '';
                        while ($subject = $subject_result->fetch_assoc()) {
                            if ($subject['category'] != $current_category) {
                                if ($current_category != '') {
                                    echo '</div>';
                                }
                                $current_category = $subject['category'];
                                echo '<p><strong>' . htmlspecialchars($current_category) . '</strong></p>';
                                echo '<div class="checkbox-group">';
                            }
                            $checked = in_array($subject['id'], $subjects) ? 'checked' : '';
                            echo '<div class="checkbox-item">';
                            echo '<input type="checkbox" name="subjects[]" id="subject_' . $subject['id'] . '" value="' . $subject['id'] . '" ' . $checked . '>';
                            echo '<label for="subject_' . $subject['id'] . '">' . htmlspecialchars($subject['name']) . '</label>';
                            echo '</div>';
                        }
                        if ($current_category != '') {
                            echo '</div>';
                        }
                        ?>
                    </div>
                    
                    <!-- Language Filter -->
                    <div class="filter-group">
                        <h3>Languages</h3>
                        <div class="checkbox-group">
                            <?php foreach ($languages_list as $language): ?>
                                <?php $checked = in_array($language, $languages) ? 'checked' : ''; ?>
                                <div class="checkbox-item">
                                    <input type="checkbox" name="languages[]" id="lang_<?php echo htmlspecialchars($language); ?>" value="<?php echo htmlspecialchars($language); ?>" <?php echo $checked; ?>>
                                    <label for="lang_<?php echo htmlspecialchars($language); ?>"><?php echo htmlspecialchars($language); ?></label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <!-- Price Range Filter -->
                    <div class="filter-group">
                        <h3>Price Range ($/hour)</h3>
                        <div class="price-range">
                            <input type="number" name="min_price" class="form-control price-input" placeholder="Min" min="0" value="<?php echo htmlspecialchars($min_price); ?>">
                            <span>to</span>
                            <input type="number" name="max_price" class="form-control price-input" placeholder="Max" min="0" value="<?php echo htmlspecialchars($max_price); ?>">
                        </div>
                    </div>
                    
                    <!-- Rating Filter -->
                    <div class="filter-group">
                        <h3>Minimum Rating</h3>
                        <select name="rating" class="form-control">
                            <option value="">Any Rating</option>
                            <option value="4.5" <?php if($rating == '4.5') echo 'selected'; ?>>4.5+ Stars</option>
                            <option value="4.0" <?php if($rating == '4.0') echo 'selected'; ?>>4.0+ Stars</option>
                            <option value="3.5" <?php if($rating == '3.5') echo 'selected'; ?>>3.5+ Stars</option>
                            <option value="3.0" <?php if($rating == '3.0') echo 'selected'; ?>>3.0+ Stars</option>
                        </select>
                    </div>
                    
                    <!-- Availability Filter -->
                    <div class="filter-group">
                        <h3>Availability</h3>
                        <select name="day" class="form-control">
                            <option value="">Any Day</option>
                            <option value="Monday" <?php if($day == 'Monday') echo 'selected'; ?>>Monday</option>
                            <option value="Tuesday" <?php if($day == 'Tuesday') echo 'selected'; ?>>Tuesday</option>
                            <option value="Wednesday" <?php if($day == 'Wednesday') echo 'selected'; ?>>Wednesday</option>
                            <option value="Thursday" <?php if($day == 'Thursday') echo 'selected'; ?>>Thursday</option>
                            <option value="Friday" <?php if($day == 'Friday') echo 'selected'; ?>>Friday</option>
                            <option value="Saturday" <?php if($day == 'Saturday') echo 'selected'; ?>>Saturday</option>
                            <option value="Sunday" <?php if($day == 'Sunday') echo 'selected'; ?>>Sunday</option>
                        </select>
                    </div>
                    
                    <div class="filter-actions">
                        <button type="submit" class="btn">Apply Filters</button>
                        <a href="find_tutors.php" class="btn secondary">Clear All</a>
                    </div>
                </form>
            </div>
            
            <!-- Tutors Column -->
            <div class="tutors-column">
                <?php if($tutors_result->num_rows > 0): ?>
                    <p class="total-results"><?php echo $tutors_result->num_rows; ?> Tutors Found</p>
                    
                    <?php while($tutor = $tutors_result->fetch_assoc()): ?>
                        <?php
                        // Get tutor subjects
                        $subject_sql = "SELECT s.name, ts.proficiency_level
                                       FROM tutor_subjects ts
                                       JOIN subjects s ON ts.subject_id = s.id
                                       WHERE ts.tutor_profile_id = ?
                                       LIMIT 10";
                        $subject_stmt = $conn->prepare($subject_sql);
                        $subject_stmt->bind_param("i", $tutor['tutor_profile_id']);
                        $subject_stmt->execute();
                        $tutor_subjects = $subject_stmt->get_result();
                        
                        // Get tutor languages
                        $tutor_languages = explode(',', $tutor['preferred_languages'] ?? 'English');
                        
                        // Format rating
                        $avg_rating = round($tutor['avg_rating'] ?? 0, 1);
                        ?>
                        
                        <div class="tutor-card">
                            <div class="tutor-header">
                                <div class="tutor-image">
                                    <?php if(isset($tutor['profile_image']) && !empty($tutor['profile_image'])): ?>
                                        <img src="<?php echo htmlspecialchars($tutor['profile_image']); ?>" alt="Profile Picture">
                                    <?php else: ?>
                                        <?php echo substr(htmlspecialchars($tutor['fullname']), 0, 1); ?>
                                    <?php endif; ?>
                                </div>
                                <div class="tutor-info">
                                    <div class="tutor-name"><?php echo htmlspecialchars($tutor['fullname']); ?></div>
                                    <div class="tutor-rating">
                                        <div class="tutor-stars">
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
                                        <span><?php echo $avg_rating; ?> (<?php echo $tutor['review_count']; ?> reviews)</span>
                                    </div>
                                    <div class="tutor-stats">
                                        <span><?php echo $tutor['completed_sessions']; ?> Completed Sessions</span>
                                    </div>
                                    <div class="tutor-price">$<?php echo number_format($tutor['hourly_rate'], 2); ?>/hour</div>
                                </div>
                            </div>
                            
                            <div class="tutor-bio">
                                <?php 
                                $short_bio = substr($tutor['bio'], 0, 200);
                                echo htmlspecialchars($short_bio);
                                if (strlen($tutor['bio']) > 200) echo '...';
                                ?>
                            </div>
                            
                            <?php if($tutor_subjects->num_rows > 0): ?>
                                <div class="tutor-subjects">
                                    <?php while($subject = $tutor_subjects->fetch_assoc()): ?>
                                        <span class="subject-badge">
                                            <?php echo htmlspecialchars($subject['name']); ?>
                                            (<?php echo ucfirst($subject['proficiency_level']); ?>)
                                        </span>
                                    <?php endwhile; ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if(!empty($tutor_languages)): ?>
                                <div class="tutor-languages">
                                    <?php foreach($tutor_languages as $language): ?>
                                        <span class="language-badge">
                                            <?php echo htmlspecialchars(trim($language)); ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="tutor-actions">
                                <a href="book_session.php?tutor_id=<?php echo $tutor['id']; ?>" class="btn">Book Session</a>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-results">
                        <h3>No tutors found</h3>
                        <p>Try adjusting your search filters to find more tutors.</p>
                        <a href="find_tutors.php" class="btn secondary">Clear Filters</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
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