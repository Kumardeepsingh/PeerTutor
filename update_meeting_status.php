<?php

// Include database connection
require_once 'db_connect.php';
require_once 'vendor/autoload.php';
require_once 'stripe_config.php';


use GuzzleHttp\Client;

// Zoom API credentials
$clientId = $_ENV['ZOOM_CLIENT_ID']; 
$clientSecret = $_ENV['ZOOM_CLIENT_SECRET'];
$accountId = $_ENV['ZOOM_ACCOUNT_ID'];

// Get current time
$currentTime = date('Y-m-d H:i:s');

// Get sessions that are still marked as 'scheduled' and their end time has passed
$stmt = $conn->prepare("
    SELECT * 
    FROM sessions 
    WHERE status = 'scheduled' 
    AND CONCAT(session_date, ' ', end_time) < ?
");
$stmt->bind_param("s", $currentTime);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    // Get an OAuth Access Token from Zoom
    $client = new Client();
    try {
        $response = $client->post('https://zoom.us/oauth/token', [
            'form_params' => [
                'grant_type' => 'account_credentials',
                'account_id' => $accountId
            ],
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode("$clientId:$clientSecret"),
                'Content-Type' => 'application/x-www-form-urlencoded'
            ]
        ]);
        
        $token_data = json_decode($response->getBody(), true);
        $accessToken = $token_data['access_token'];
        
        // Process each session
        while ($session = $result->fetch_assoc()) {
            $session_id = $session['id'];
            $zoom_meeting_id = $session['zoom_meeting_id'];
            $total_amount = $session['total_price'];
            
            if (!empty($zoom_meeting_id)) {
                // Step 1: Fetch meeting details to get the end time
                try {
                    $meeting_response = $client->get("https://api.zoom.us/v2/past_meetings/{$zoom_meeting_id}", [
                        'headers' => [
                            'Authorization' => 'Bearer ' . $accessToken
                        ]
                    ]);
                    
                    $meeting_data = json_decode($meeting_response->getBody(), true);
                    $meeting_end_time = isset($meeting_data['end_time']) ? $meeting_data['end_time'] : null;
                    
                    // Check if end time exists and the meeting has ended
                    if ($meeting_end_time) {

                        // get payment intent id and tutor id for session
                            $stmt = $conn->prepare("
                            SELECT stripe_payment_intent_id, tutor_id	 
                            FROM payment_transactions 
                            WHERE session_id = ?
                            ");
                            $stmt->bind_param("i", $session_id);
                            $stmt->execute();
                            $payment_intent_result = $stmt->get_result();
                            $row = $payment_intent_result->fetch_assoc();
                            $payment_intent_id = $row['stripe_payment_intent_id'];
                            $tutor_id = $row['tutor_id'];

                            // get stripe account id for tutor
                            $stmt = $conn->prepare("
                                SELECT stripe_account_id 
                                FROM tutor_profiles 
                                WHERE user_id = ?
                            ");
                            $stmt->bind_param("i", $tutor_id);
                            $stmt->execute();
                            $stripe_account_result = $stmt->get_result();
                            $stripe_row = $stripe_account_result->fetch_assoc();
                            $stripe_account_id = $stripe_row['stripe_account_id'];
                            $total_amount_incents = $total_amount * 100;

                        $payment_split = handlePaymentSplit($payment_intent_id,$stripe_account_id,$total_amount_incents);
                        if($payment_split['status']==='success'){
                            $stmt = $conn->prepare("
                                UPDATE payment_transactions 
                                SET tutor_amount = ?, platform_fee = ? 
                                WHERE session_id = ?
                            ");
                            $tutor_amount = $total_amount*0.9;
                            $platform_fee = $total_amount*0.1;

                            // Bind the parameters to the statement
                            $stmt->bind_param("ddi", $tutor_amount, $platform_fee, $session_id);
                            $stmt->execute();

                            //  Update session status to 'completed'
                                $update_stmt = $conn->prepare("
                                UPDATE sessions 
                                SET status = 'completed' 
                                WHERE id = ?
                            ");
                            $update_stmt->bind_param("i", $session_id);
                            $update_stmt->execute();
                            
                            // Echo update message for JavaScript to detect
                            echo "Updated Session ID {$session_id} to 'completed'.";
                        
                        
                            
                        }else{
                            error_log("{$payment_split['message']}");
                        }

                        
                        
                    }
                    
                } catch (Exception $e) {
                    error_log("Error fetching meeting details for meeting {$zoom_meeting_id}: " . $e->getMessage());
                }
            }
        }
    } catch (Exception $e) {
        error_log("Error getting Zoom token: " . $e->getMessage());
    }
}


// Now check for completed sessions with missing recording URLs
$recording_stmt = $conn->prepare("
    SELECT id, zoom_meeting_id 
    FROM sessions 
    WHERE status = 'completed' 
    AND recording_url IS NULL
");
$recording_stmt->execute();
$recording_result = $recording_stmt->get_result();

if ($recording_result->num_rows > 0) {

    $client = new Client();
    try {
        $response = $client->post('https://zoom.us/oauth/token', [
            'form_params' => [
                'grant_type' => 'account_credentials',
                'account_id' => $accountId
            ],
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode("$clientId:$clientSecret"),
                'Content-Type' => 'application/x-www-form-urlencoded'
            ]
        ]);
        
        $token_data = json_decode($response->getBody(), true);
        $accessToken = $token_data['access_token'];
    
    // Process each session marked as completed without recording URL
    while ($session = $recording_result->fetch_assoc()) {
        $session_id = $session['id'];
        $zoom_meeting_id = $session['zoom_meeting_id'];
        
        if (!empty($zoom_meeting_id)) {
            // Step 1: Get the recording URL
            $recording_url = null;
            try {
                $recording_response = $client->get("https://api.zoom.us/v2/meetings/{$zoom_meeting_id}/recordings", [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $accessToken
                    ]
                ]);
                
                $recording_data = json_decode($recording_response->getBody(), true);
                
                // Check if recording files exist
                if (isset($recording_data['recording_files']) && count($recording_data['recording_files']) > 0) {
                    foreach ($recording_data['recording_files'] as $file) {
                        if ($file['file_type'] == 'MP4') {
                            $recording_url = $file['play_url'];
                            break;
                        }
                    }
                }
            } catch (Exception $e) {
                error_log("Error fetching recording for meeting {$zoom_meeting_id}: " . $e->getMessage());
            }
            
            // Step 2: Update session with recording URL if available
            if ($recording_url) {
                $update_stmt = $conn->prepare("
                    UPDATE sessions 
                    SET recording_url = ? 
                    WHERE id = ?
                ");
                $update_stmt->bind_param("si", $recording_url, $session_id);
                $update_stmt->execute();
                
                // Echo update message for JavaScript to detect
                echo "Updated recording URL for Session ID {$session_id}.";
            }
        }
    }
}catch (Exception $e) {
    error_log("Error getting Zoom token: " . $e->getMessage());
}
} else {
    echo "No updates";  // This message is returned when no updates are made
}
    

$conn->close();

// Redirect to my_sessions.php after 3 seconds
header("Refresh: 3; URL=my_sessions.php");
exit();
?>
