 
<?php
require_once 'config.php';

/**
 * Authenticates a user with either hashed or plain text password
 * 
 * @param string $username The username to authenticate (email or phone for patients)
 * @param string $password The password to verify
 * @return array|false Returns user data if authenticated, false otherwise
 */
function authenticateUser($username, $password) {
    global $conn;
    
    // First check in users table (staff)
    $stmt = $conn->prepare("SELECT user_id, username, password_hash, 'staff' as user_type FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        // Check if password matches plain text (for backward compatibility)
        if ($password === $user['password_hash']) {
            return $user;
        }
        
        // Check if password matches hashed version
        if (password_verify($password, $user['password_hash'])) {
            return $user;
        }
    }
    
    // If not found in staff table, check in patients table
    // For patients, we'll use email OR phone number as username
    $stmt = $conn->prepare("SELECT patient_id as user_id, email as username, phone, 'patient' as user_type 
                           FROM patients WHERE email = ? OR phone = ?");
    $stmt->bind_param("ss", $username, $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $patient = $result->fetch_assoc();
        
        // For patients, we'll use phone number as password
        // Check if the provided password matches the phone number
        if ($password === $patient['phone']) {
            return $patient;
        }
        
        // Optional: Also allow hashed version of phone number for more security
        // This doesn't require altering the table structure
        $hashed_phone = hash('sha256', $patient['phone']);
        if ($password === $hashed_phone || hash('sha256', $password) === $hashed_phone) {
            return $patient;
        }
    }
    
    return false;
}

/**
 * Generates a hashed version of phone number for more secure authentication
 * 
 * @param string $phone Phone number to hash
 * @return string Hashed phone number
 */
function hashPhoneNumber($phone) {
    return hash('sha256', $phone);
}

/**
 * Checks if the current user is authenticated
 * 
 * @return bool True if authenticated, false otherwise
 */
function isAuthenticated() {
    return isset($_SESSION['user_id']);
}

/**
 * Checks if the current user is a patient
 * 
 * @return bool True if patient, false otherwise
 */
function isPatient() {
    return isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'patient';
}

/**
 * Checks if the current user is staff
 * 
 * @return bool True if staff, false otherwise
 */
function isStaff() {
    return isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'staff';
}

/**
 * Requires authentication - redirects to login if not authenticated
 */
function requireAuth() {
    if (!isAuthenticated()) {
        header("Location: login.php");
        exit();
    }
}

/**
 * Requires staff authentication - redirects to login if not staff
 */
function requireStaff() {
    requireAuth();
    if (!isStaff()) {
        header("Location: ../patients/patientportal.php");
        exit();
    }
}

/**
 * Requires patient authentication - redirects to login if not patient
 */
function requirePatient() {
    requireAuth();
    if (!isPatient()) {
        header("Location: ../index.php");
        exit();
    }
}

/**
 * Logs out the current user
 */
function logout() {
    session_unset();
    session_destroy();
    session_start();
}

/**
 * Helper function to get patient by phone number
 * 
 * @param string $phone Phone number to search for
 * @return array|false Patient data if found, false otherwise
 */
function getPatientByPhone($phone) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT patient_id, email, phone FROM patients WHERE phone = ?");
    $stmt->bind_param("s", $phone);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        return $result->fetch_assoc();
    }
    
    return false;
}

/**
 * Helper function to get patient by email
 * 
 * @param string $email Email to search for
 * @return array|false Patient data if found, false otherwise
 */
function getPatientByEmail($email) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT patient_id, email, phone FROM patients WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        return $result->fetch_assoc();
    }
    
    return false;
}
?>
