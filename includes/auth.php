 
<?php
require_once 'config.php';

/**
 * Authenticates a user with either hashed or plain text password
 * 
 * @param string $username The username to authenticate
 * @param string $password The password to verify
 * @return array|false Returns user data if authenticated, false otherwise
 */
function authenticateUser($username, $password) {
    global $conn;
    
    // Prepare statement to get user data
    $stmt = $conn->prepare("SELECT user_id, username, password_hash FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows !== 1) {
        return false;
    }
    
    $user = $result->fetch_assoc();
    
    // Check if password matches plain text (for backward compatibility)
    if ($password === $user['password_hash']) {
        return $user;
    }
    
    // Check if password matches hashed version
    if (password_verify($password, $user['password_hash'])) {
        return $user;
    }
    
    return false;
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
 * Requires authentication - redirects to login if not authenticated
 */
function requireAuth() {
    if (!isAuthenticated()) {
        header("Location: login.php");
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
?>