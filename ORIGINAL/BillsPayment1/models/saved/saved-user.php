<?php
include '../../config/config.php';

session_start();

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is authorized
if (!isset($_SESSION['user_type'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Get JSON input
        $input = json_decode(file_get_contents('php://input'), true);
        
        // Check if JSON decode was successful
        if ($input === null) {
            echo json_encode(['success' => false, 'message' => 'Invalid JSON data']);
            exit;
        }
        
        // Validate required fields
        $required_fields = ['user_type', 'id_number', 'first_name', 'last_name', 'username'];
        foreach ($required_fields as $field) {
            if (empty($input[$field])) {
                echo json_encode(['success' => false, 'message' => "Field '$field' is required"]);
                exit;
            }
        }
        
        // Sanitize inputs
        $id_number = mysqli_real_escape_string($conn, trim($input['id_number']));
        $first_name = mysqli_real_escape_string($conn, trim($input['first_name']));
        $middle_name = mysqli_real_escape_string($conn, trim($input['middle_name'] ?? ''));
        $last_name = mysqli_real_escape_string($conn, trim($input['last_name']));
        $username = mysqli_real_escape_string($conn, trim($input['username']));
        $user_type = mysqli_real_escape_string($conn, trim($input['user_type']));
        $password = md5('Mlinc1234'); // Hash the default password
        $status = 'Active'; // Default status
        
        // Get current user info for created_by field
        $created_by = '';
        if ($_SESSION['user_type'] === 'admin' && isset($_SESSION['admin_name'])) {
            $created_by = $_SESSION['admin_name'];
        } elseif ($_SESSION['user_type'] === 'user' && isset($_SESSION['user_name'])) {
            $created_by = $_SESSION['user_name'];
        } else {
            $created_by = 'System';
        }
        
        $date_created = date('Y-m-d H:i:s');
        
        // Check if ID number already exists in user_form table
        $check_query = "SELECT id_number FROM mldb.user_form WHERE id_number = ?";
        $check_stmt = mysqli_prepare($conn, $check_query);
        mysqli_stmt_bind_param($check_stmt, "s", $id_number);
        mysqli_stmt_execute($check_stmt);
        $check_result = mysqli_stmt_get_result($check_stmt);
        
        if (mysqli_num_rows($check_result) > 0) {
            echo json_encode(['success' => false, 'message' => 'ID Number already exists']);
            exit;
        }
        
        // Check if email/username already exists in user_form table
        $check_email_query = "SELECT email FROM mldb.user_form WHERE email = ?";
        $check_email_stmt = mysqli_prepare($conn, $check_email_query);
        mysqli_stmt_bind_param($check_email_stmt, "s", $username);
        mysqli_stmt_execute($check_email_stmt);
        $check_email_result = mysqli_stmt_get_result($check_email_stmt);
        
        if (mysqli_num_rows($check_email_result) > 0) {
            echo json_encode(['success' => false, 'message' => 'Username/Email already exists']);
            exit;
        }
        
        // Insert new user into user_form table (matching your SELECT query structure)
        $insert_query = "INSERT INTO mldb.user_form 
                        (id_number, first_name, middle_name, last_name, email, password, user_type, status, date_created, created_by) 
                        VALUES 
                        (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $insert_stmt = mysqli_prepare($conn, $insert_query);
        mysqli_stmt_bind_param($insert_stmt, "ssssssssss", 
            $id_number, $first_name, $middle_name, $last_name, $username, 
            $password, $user_type, $status, $date_created, $created_by
        );
        
        if (mysqli_stmt_execute($insert_stmt)) {
            // Get the newly created user data from user_form table
            $new_user_query = "SELECT id_number, first_name, middle_name, last_name, email as username, user_type, status, last_online, date_created, created_by, modified_date, modified_by FROM mldb.user_form WHERE id_number = ?";
            $new_user_stmt = mysqli_prepare($conn, $new_user_query);
            mysqli_stmt_bind_param($new_user_stmt, "s", $id_number);
            mysqli_stmt_execute($new_user_stmt);
            $new_user_result = mysqli_stmt_get_result($new_user_stmt);
            $new_user_data = mysqli_fetch_assoc($new_user_result);
            
            echo json_encode([
                'success' => true, 
                'message' => 'User created successfully',
                'user_data' => $new_user_data
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($conn)]);
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}

mysqli_close($conn);
?>