<?php
// Start session and check if user is logged in as a tutor
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'tutor') {
    header("Location: login.php?error=Please login to access the tutor dashboard");
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

// Process form submission for adding new availability
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_availability'])) {
    $day_of_week = $_POST['day_of_week'];
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];
    
    // Validate times
    if (strtotime($end_time) <= strtotime($start_time)) {
        $error_message = "End time must be after start time.";
    } else {
        // Check for overlapping time slots
        $stmt = $conn->prepare("
            SELECT * FROM tutor_availability 
            WHERE tutor_id = ? AND day_of_week = ? AND 
            ((start_time <= ? AND end_time > ?) OR
            (start_time < ? AND end_time >= ?) OR
            (start_time >= ? AND end_time <= ?))
        ");
        $stmt->bind_param("isssssss", $user_id, $day_of_week, $end_time, $start_time, $end_time, $start_time, $start_time, $end_time);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error_message = "This time slot overlaps with an existing availability.";
        } else {
            // Insert the new availability
            $stmt = $conn->prepare("
                INSERT INTO tutor_availability 
                (tutor_id, day_of_week, start_time, end_time ) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->bind_param("isss", $user_id, $day_of_week, $start_time, $end_time );
            
            if ($stmt->execute()) {
                $success_message = "Availability added successfully!";
            } else {
                $error_message = "Error adding availability: " . $conn->error;
            }
        }
    }
}

// Process delete availability request
if (isset($_GET['delete_id']) && is_numeric($_GET['delete_id'])) {
    $availability_id = $_GET['delete_id'];
    
    // Verify the availability belongs to this tutor
    $stmt = $conn->prepare("SELECT * FROM tutor_availability WHERE id = ? AND tutor_id = ?");
    $stmt->bind_param("ii", $availability_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $stmt = $conn->prepare("DELETE FROM tutor_availability WHERE id = ?");
        $stmt->bind_param("i", $availability_id);
        
        if ($stmt->execute()) {
            header("Location: tutor_availability.php");
            exit();
        } else {
            $error_message = "Error removing availability: " . $conn->error;
        }
    } else {
        $error_message = "Invalid availability selection.";
    }
}

// Fetch the tutor's availability
$stmt = $conn->prepare("
    SELECT * FROM tutor_availability 
    WHERE tutor_id = ? 
    ORDER BY FIELD(day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'), 
    start_time ASC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$availability_result = $stmt->get_result();




?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set Availability - PeerTutor</title>
    <link rel="stylesheet" href="styles/styles.css">
    <link rel="stylesheet" href="styles/dashboard-styles.css">
    <style>
        .availability-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
        }
        
        .day-card {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            padding: 1.5rem;
        }
        
        .day-header {
            font-weight: bold;
            margin-bottom: 1rem;
            color: var(--primary);
            border-bottom: 1px solid var(--border);
            padding-bottom: 0.5rem;
        }
        
        .time-slot {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--border);
        }
        
        .time-slot:last-child {
            border-bottom: none;
        }
        
        .time-info {
            flex: 1;
        }
        
        .time-range {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }
        
        .location-type {
            font-size: 0.85rem;
            color: var(--gray);
        }
        
        .location-type.online {
            color: var(--primary);
        }
        
        .location-type.in-person {
            color: var(--secondary);
        }
        
        .location-type.both {
            color: var(--dark);
        }
        
        .actions {
            display: flex;
            gap: 0.5rem;
        }
        
        .form-section {
            margin-bottom: 2rem;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            align-items: end;
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }
        
        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--border);
            border-radius: 5px;
            font-size: 1rem;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            margin-top: 0.5rem;
        }
        
        .checkbox-group input {
            margin-right: 0.5rem;
        }
        
        .empty-day {
            color: var(--gray);
            text-align: center;
            padding: 1rem 0;
        }
        
        .delete-btn {
            color: var(--danger);
            background: none;
            border: none;
            cursor: pointer;
            padding: 0.25rem 0.5rem;
        }
        
        .delete-btn:hover {
            text-decoration: underline;
        }
        
        .calendar-view {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 0.5rem;
            margin-bottom: 2rem;
        }
        
        .calendar-day {
            background-color: #f8f9fa;
            border-radius: 5px;
            padding: 0.5rem;
            text-align: center;
            min-height: 150px;
        }
        
        .calendar-day-name {
            font-weight: 600;
            margin-bottom: 0.5rem;
            border-bottom: 1px solid var(--border);
            padding-bottom: 0.25rem;
        }
        
        .calendar-slots {
            font-size: 0.85rem;
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }
        
        .calendar-slot {
            background-color: rgba(52, 152, 219, 0.1);
            border-radius: 3px;
            padding: 0.25rem;
            margin: 0.25rem 0;
            color: var(--primary);
        }
        
        .calendar-slot.in-person {
            background-color: rgba(46, 204, 113, 0.1);
            color: var(--secondary);
        }
        
        .calendar-slot.both {
            background-color: rgba(155, 89, 182, 0.1);
            color: #8e44ad;
        }
        
        .empty-slot {
            color: var(--gray);
            font-style: italic;
            padding: 1rem 0;
        }
    </style>
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
                    <?php
                    // Get average rating
                    $stmt = $conn->prepare("SELECT AVG(rating) as avg_rating, COUNT(*) as total_reviews FROM reviews WHERE tutor_id = ?");
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                    $rating_result = $stmt->get_result();
                    $rating_data = $rating_result->fetch_assoc();
                    $avg_rating = round($rating_data['avg_rating'], 1) ?: 0;
                    $total_reviews = $rating_data['total_reviews'];
                    ?>
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
                <a href="tutor_profile.php" class="btn secondary small">Edit Profile</a>
            </div>
            
            <div class="sidebar-menu">
                <ul>
                    <li><a href="tutor_dashboard.php"><span>📊</span> Dashboard</a></li>
                    <li><a href="my_sessions.php"><span>📅</span> My Sessions</a></li>
                    <li><a href="tutor_availability.php" class="active"><span>⏰</span> Set Availability</a></li>
                    <li><a href="tutor_reviews.php"><span>⭐</span> My Reviews</a></li>
                </ul>
            </div>
        </aside>

        <!-- Main Dashboard Area -->
        <main class="dashboard-main">
            <h1>Set Your Availability</h1>
            
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success">
                    <p><?php echo $success_message; ?></p>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger">
                    <p><?php echo $error_message; ?></p>
                </div>
            <?php endif; ?>
            
            
            <section class="dashboard-section">
                <div class="section-header">
                    <h2>Add New Availability</h2>
                </div>
                <div class="section-content">
                    <form method="POST" action="">
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="day_of_week">Day of Week</label>
                                <select name="day_of_week" id="day_of_week" class="form-control" required>
                                    <option value="Monday">Monday</option>
                                    <option value="Tuesday">Tuesday</option>
                                    <option value="Wednesday">Wednesday</option>
                                    <option value="Thursday">Thursday</option>
                                    <option value="Friday">Friday</option>
                                    <option value="Saturday">Saturday</option>
                                    <option value="Sunday">Sunday</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="start_time">Start Time</label>
                                <input type="time" name="start_time" id="start_time" value = "<?php echo isset($_POST['start_time']) ? $_POST['start_time']: ""; ?>" class="form-control" required>

                            </div>
                            
                            <div class="form-group">
                                <label for="end_time">End Time</label>
                                <input type="time" name="end_time" id="end_time" value = "<?php echo isset($_POST['end_time']) ? $_POST['end_time']: ""; ?>" class="form-control" required>

                            </div>
                        </div>
                        
                        <button type="submit" name="add_availability" class="btn primary">Add Availability</button>
                    </form>
                </div>
            </section>
            
            <section class="dashboard-section">
                <div class="section-header">
                    <h2>Calendar View</h2>
                </div>
                <div class="section-content">
                    <div class="calendar-view">
                        <?php
                        $days_of_week = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                        
                        foreach ($days_of_week as $day) {
                            echo '<div class="calendar-day">';
                            echo '<div class="calendar-day-name">' . $day . '</div>';
                            
                            // Get availability for this day
                            $stmt = $conn->prepare("
                                SELECT * FROM tutor_availability 
                                WHERE tutor_id = ? AND day_of_week = ? 
                                ORDER BY start_time ASC
                            ");
                            $stmt->bind_param("is", $user_id, $day);
                            $stmt->execute();
                            $day_slots = $stmt->get_result();
                            
                            echo '<div class="calendar-slots">';
                            if ($day_slots->num_rows > 0) {
                                while ($slot = $day_slots->fetch_assoc()) {
                                    $start = date("g:ia", strtotime($slot['start_time']));
                                    $end = date("g:ia", strtotime($slot['end_time']));
                                    echo '<div class="calendar-slot">' . $start . ' - ' . $end . '</div>';
                                }
                            } else {
                                echo '<div class="empty-slot">Not Available</div>';
                            }
                            echo '</div>'; // Close calendar-slots
                            echo '</div>'; // Close calendar-day
                        }
                        ?>
                    </div>
                </div>
            </section>
            
            <section class="dashboard-section">
                <div class="section-header">
                    <h2>Manage Your Availability</h2>
                </div>
                <div class="section-content">
                    <div class="availability-container">
                        <?php
                        $days_of_week = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                        
                        foreach ($days_of_week as $day) {
                            echo '<div class="day-card">';
                            echo '<div class="day-header">' . $day . '</div>';
                            
                            // Get availability for this day
                            $stmt = $conn->prepare("
                                SELECT * FROM tutor_availability 
                                WHERE tutor_id = ? AND day_of_week = ? 
                                ORDER BY start_time ASC
                            ");
                            $stmt->bind_param("is", $user_id, $day);
                            $stmt->execute();
                            $day_slots = $stmt->get_result();
                            
                            if ($day_slots->num_rows > 0) {
                                while ($slot = $day_slots->fetch_assoc()) {
                                    echo '<div class="time-slot">';
                                    echo '<div class="time-info">';
                                    echo '<div class="time-range">' . date("g:i A", strtotime($slot['start_time'])) . ' - ' . date("g:i A", strtotime($slot['end_time'])) . '</div>'; 
                                    echo '</div>';
                                    echo '<div class="actions">';
                                    echo '<a href="tutor_availability.php?delete_id=' . $slot['id'] . '" class="delete-btn" onclick="return confirm(\'Are you sure you want to remove this availability?\')">Remove</a>';
                                    echo '</div>';
                                    echo '</div>';
                                }
                            } else {
                                echo '<div class="empty-day">No availability set for this day</div>';
                            }
                            
                            echo '</div>';
                        }
                        ?>
                    </div>
                </div>
            </section>
            
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