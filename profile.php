<?php
$page_title = "My Profile";
require_once 'includes/header.php';

// Get system settings
$settings_result = $conn->query("SELECT * FROM system_settings");
$settings = [];
while ($row = $settings_result->fetch_assoc()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
$default_settings = [
    'hospital_name' => 'MediCare Hospital',
    'date_format' => 'Y-m-d'
];
$settings = array_merge($default_settings, $settings);

// Detect current user
$current_user_id = $_SESSION['user_id'] ?? null;
if (!$current_user_id) {
    $_SESSION['error'] = "You must be logged in to view your profile.";
    header("Location: index.php");
    exit();
}

// Fetch user data
$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->bind_param("i", $current_user_id);
$stmt->execute();
$result = $stmt->get_result();
$user_data = $result->fetch_assoc();

if (!$user_data) {
    $_SESSION['error'] = "User not found!";
    header("Location: index.php");
    exit();
}

// Handle profile update / password change / account deletion
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_profile'])) {
        $stmt = $conn->prepare("
            UPDATE users SET 
                email = ?, first_name = ?, last_name = ?, phone = ?, address = ?, specialization = ?
            WHERE user_id = ?
        ");
        $stmt->bind_param(
            "ssssssi",
            $_POST['email'],
            $_POST['first_name'],
            $_POST['last_name'],
            $_POST['phone'],
            $_POST['address'],
            $_POST['specialization'],
            $current_user_id
        );
        if ($stmt->execute()) {
            $_SESSION['message'] = "Profile updated successfully!";
            header("Location: profile.php");
            exit();
        } else {
            $error = "Error updating profile: " . $conn->error;
        }
    } elseif (isset($_POST['change_password'])) {
        // Validate old password
        if (!password_verify($_POST['old_password'], $user_data['password_hash'])) {
            $error = "Old password is incorrect.";
        } elseif ($_POST['new_password'] !== $_POST['confirm_password']) {
            $error = "New passwords do not match.";
        } elseif (strlen($_POST['new_password']) < 6) {
            $error = "New password must be at least 6 characters.";
        } else {
            $new_hash = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
            $stmt->bind_param("si", $new_hash, $current_user_id);
            if ($stmt->execute()) {
                $_SESSION['message'] = "Password changed successfully!";
                header("Location: profile.php");
                exit();
            } else {
                $error = "Error changing password: " . $conn->error;
            }
        }
    } elseif (isset($_POST['delete_account'])) {
        // Delete account
        $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
        $stmt->bind_param("i", $current_user_id);
        if ($stmt->execute()) {
            session_destroy();
            header("Location: index.php");
            exit();
        } else {
            $error = "Error deleting account: " . $conn->error;
        }
    }
}

// Display messages
if (isset($_SESSION['message'])) {
    echo '<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
        <span class="block sm:inline">' . $_SESSION['message'] . '</span>
    </div>';
    unset($_SESSION['message']);
}
if (isset($_SESSION['error'])) {
    echo '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
        <span class="block sm:inline">' . $_SESSION['error'] . '</span>
    </div>';
    unset($_SESSION['error']);
}
if (isset($error)) {
    echo '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
        <span class="block sm:inline">' . $error . '</span>
    </div>';
}
?>

<div class="bg-white rounded-lg shadow p-6 mb-6">
    <h3 class="font-semibold text-lg mb-4">My Profile</h3>
    <form method="POST" action="">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2" for="username">Username</label>
                <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 bg-gray-100" 
                    id="username" name="username" type="text" value="<?php echo htmlspecialchars($user_data['username']); ?>" readonly>
            </div>
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2" for="email">Email</label>
                <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700" 
                    id="email" name="email" type="email" value="<?php echo htmlspecialchars($user_data['email']); ?>" required>
            </div>
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2" for="first_name">First Name</label>
                <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700" 
                    id="first_name" name="first_name" type="text" value="<?php echo htmlspecialchars($user_data['first_name']); ?>" required>
            </div>
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2" for="last_name">Last Name</label>
                <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700" 
                    id="last_name" name="last_name" type="text" value="<?php echo htmlspecialchars($user_data['last_name']); ?>" required>
            </div>
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2" for="role">Role</label>
                <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 bg-gray-100" 
                    id="role" name="role" type="text" value="<?php echo ucfirst($user_data['role']); ?>" readonly>
            </div>
            <?php if ($user_data['role'] == 'doctor'): ?>
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2" for="specialization">Specialization</label>
                <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700" 
                    id="specialization" name="specialization" type="text" value="<?php echo htmlspecialchars($user_data['specialization']); ?>">
            </div>
            <?php else: ?>
            <input type="hidden" name="specialization" value="<?php echo htmlspecialchars($user_data['specialization']); ?>">
            <?php endif; ?>
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2" for="phone">Phone</label>
                <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700" 
                    id="phone" name="phone" type="tel" value="<?php echo htmlspecialchars($user_data['phone']); ?>">
            </div>
            <div class="md:col-span-2">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="address">Address</label>
                <textarea class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700" 
                    id="address" name="address" rows="3"><?php echo htmlspecialchars($user_data['address']); ?></textarea>
            </div>
        </div>
        <div class="mt-6 flex justify-end space-x-4">
            <button type="submit" name="update_profile" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                Update Profile
            </button>
        </div>
    </form>
</div>

<!-- Change Password Section -->
<div class="bg-white rounded-lg shadow p-6 mb-6">
    <h3 class="font-semibold text-lg mb-4">Change Password</h3>
    <form method="POST" action="">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2" for="old_password">Old Password</label>
                <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700" 
                    id="old_password" name="old_password" type="password" required>
            </div>
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2" for="new_password">New Password</label>
                <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700" 
                    id="new_password" name="new_password" type="password" required>
            </div>
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2" for="confirm_password">Confirm New Password</label>
                <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700" 
                    id="confirm_password" name="confirm_password" type="password" required>
            </div>
        </div>
        <div class="mt-6 flex justify-end space-x-4">
            <button type="submit" name="change_password" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                Change Password
            </button>
        </div>
    </form>
</div>

<!-- Delete Account Section -->
<div class="bg-white rounded-lg shadow p-6 mb-6">
    <h3 class="font-semibold text-lg mb-4 text-red-600">Delete Account</h3>
    <form method="POST" action="" onsubmit="return confirm('Are you sure you want to delete your account? This action cannot be undone.');">
        <button type="submit" name="delete_account" class="bg-red-500 hover:bg-red-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
            Delete My Account
        </button>
    </form>
</div>

<?php require_once 'includes/footer.php'; ?>
