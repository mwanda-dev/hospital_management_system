 
<?php
$page_title = "User Management";
require_once 'includes/header.php';

// Check if user is admin
if ($user['role'] != 'admin') {
    $_SESSION['error'] = "You don't have permission to access this page.";
    header("Location: index.php");
    exit();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_user'])) {
        // Add new user
        $password_hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
        
        $stmt = $conn->prepare("
            INSERT INTO users (
                username, password_hash, email, first_name, last_name, 
                role, specialization, phone, address, hire_date, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->bind_param(
            "sssssssssss",
            $_POST['username'],
            $password_hash,
            $_POST['email'],
            $_POST['first_name'],
            $_POST['last_name'],
            $_POST['role'],
            $_POST['specialization'],
            $_POST['phone'],
            $_POST['address'],
            $_POST['hire_date'],
            $_POST['status']
        );
        
        if ($stmt->execute()) {
            $_SESSION['message'] = "User added successfully!";
            header("Location: users.php");
            exit();
        } else {
            $error = "Error adding user: " . $conn->error;
        }
    } elseif (isset($_POST['update_user'])) {
        // Update user
        $password_update = !empty($_POST['password']) ? ", password_hash = ?" : "";
        
        $stmt = $conn->prepare("
            UPDATE users SET 
                username = ?,
                email = ?,
                first_name = ?,
                last_name = ?,
                role = ?,
                specialization = ?,
                phone = ?,
                address = ?,
                hire_date = ?,
                status = ?
                $password_update
            WHERE user_id = ?
        ");
        
        if (!empty($_POST['password'])) {
            $password_hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $stmt->bind_param(
                "sssssssssssi",
                $_POST['username'],
                $_POST['email'],
                $_POST['first_name'],
                $_POST['last_name'],
                $_POST['role'],
                $_POST['specialization'],
                $_POST['phone'],
                $_POST['address'],
                $_POST['hire_date'],
                $_POST['status'],
                $password_hash,
                $_POST['user_id']
            );
        } else {
            $stmt->bind_param(
                "ssssssssssi",
                $_POST['username'],
                $_POST['email'],
                $_POST['first_name'],
                $_POST['last_name'],
                $_POST['role'],
                $_POST['specialization'],
                $_POST['phone'],
                $_POST['address'],
                $_POST['hire_date'],
                $_POST['status'],
                $_POST['user_id']
            );
        }
        
        if ($stmt->execute()) {
            $_SESSION['message'] = "User updated successfully!";
            header("Location: users.php");
            exit();
        } else {
            $error = "Error updating user: " . $conn->error;
        }
    }
}

// Handle delete action
if (isset($_GET['delete'])) {
    // Prevent deleting yourself
    if ($_GET['delete'] == $_SESSION['user_id']) {
        $_SESSION['error'] = "You cannot delete your own account!";
        header("Location: users.php");
        exit();
    }
    
    $user_id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    
    if ($stmt->execute()) {
        $_SESSION['message'] = "User deleted successfully!";
        header("Location: users.php");
        exit();
    } else {
        $error = "Error deleting user: " . $conn->error;
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

// Check if we're adding or editing a user
$editing = false;
$user_data = null;

if (isset($_GET['edit'])) {
    $editing = true;
    $user_id = intval($_GET['edit']);
    $stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user_data = $result->fetch_assoc();
    
    if (!$user_data) {
        $_SESSION['error'] = "User not found!";
        header("Location: users.php");
        exit();
    }
} elseif (isset($_GET['add'])) {
    $editing = true;
}
?>

<?php if ($editing): ?>
<!-- User Form -->
<div class="bg-white rounded-lg shadow p-6 mb-6">
    <h3 class="font-semibold text-lg mb-4"><?php echo isset($_GET['edit']) ? 'Edit User' : 'Add New User'; ?></h3>
    
    <form method="POST" action="">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2" for="username">Username</label>
                <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                    id="username" name="username" type="text" placeholder="Username" 
                    value="<?php echo htmlspecialchars($user_data['username'] ?? ''); ?>" required>
            </div>
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2" for="email">Email</label>
                <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                    id="email" name="email" type="email" placeholder="Email" 
                    value="<?php echo htmlspecialchars($user_data['email'] ?? ''); ?>" required>
            </div>
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2" for="first_name">First Name</label>
                <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                    id="first_name" name="first_name" type="text" placeholder="First Name" 
                    value="<?php echo htmlspecialchars($user_data['first_name'] ?? ''); ?>" required>
            </div>
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2" for="last_name">Last Name</label>
                <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                    id="last_name" name="last_name" type="text" placeholder="Last Name" 
                    value="<?php echo htmlspecialchars($user_data['last_name'] ?? ''); ?>" required>
            </div>
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2" for="role">Role</label>
                <select class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                    id="role" name="role" required>
                    <option value="admin" <?php echo (isset($user_data['role']) && $user_data['role'] == 'admin') ? 'selected' : ''; ?>>Administrator</option>
                    <option value="doctor" <?php echo (isset($user_data['role']) && $user_data['role'] == 'doctor') ? 'selected' : ''; ?>>Doctor</option>
                    <option value="nurse" <?php echo (isset($user_data['role']) && $user_data['role'] == 'nurse') ? 'selected' : ''; ?>>Nurse</option>
                    <option value="receptionist" <?php echo (isset($user_data['role']) && $user_data['role'] == 'receptionist') ? 'selected' : ''; ?>>Receptionist</option>
                    <option value="lab_technician" <?php echo (isset($user_data['role']) && $user_data['role'] == 'lab_technician') ? 'selected' : ''; ?>>Lab Technician</option>
                    <option value="pharmacist" <?php echo (isset($user_data['role']) && $user_data['role'] == 'pharmacist') ? 'selected' : ''; ?>>Pharmacist</option>
                </select>
            </div>
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2" for="specialization">Specialization</label>
                <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                    id="specialization" name="specialization" type="text" placeholder="Specialization (for doctors)" 
                    value="<?php echo htmlspecialchars($user_data['specialization'] ?? ''); ?>">
            </div>
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2" for="phone">Phone</label>
                <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                    id="phone" name="phone" type="tel" placeholder="Phone" 
                    value="<?php echo htmlspecialchars($user_data['phone'] ?? ''); ?>">
            </div>
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2" for="hire_date">Hire Date</label>
                <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                    id="hire_date" name="hire_date" type="date" 
                    value="<?php echo htmlspecialchars($user_data['hire_date'] ?? date('Y-m-d')); ?>">
            </div>
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2" for="status">Status</label>
                <select class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                    id="status" name="status" required>
                    <option value="active" <?php echo (isset($user_data['status']) && $user_data['status'] == 'active') ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo (isset($user_data['status']) && $user_data['status'] == 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                    <option value="on_leave" <?php echo (isset($user_data['status']) && $user_data['status'] == 'on_leave') ? 'selected' : ''; ?>>On Leave</option>
                </select>
            </div>
            <div class="md:col-span-2">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="address">Address</label>
                <textarea class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                    id="address" name="address" placeholder="Address" rows="3"><?php echo htmlspecialchars($user_data['address'] ?? ''); ?></textarea>
            </div>
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2" for="password">Password</label>
                <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                    id="password" name="password" type="password" placeholder="Leave blank to keep current">
            </div>
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2" for="confirm_password">Confirm Password</label>
                <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                    id="confirm_password" name="confirm_password" type="password" placeholder="Confirm password">
            </div>
        </div>
        
        <div class="mt-6 flex justify-end space-x-4">
            <a href="users.php" class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                Cancel
            </a>
            <?php if (isset($_GET['edit'])): ?>
                <input type="hidden" name="user_id" value="<?php echo $user_data['user_id']; ?>">
                <button type="submit" name="update_user" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                    Update User
                </button>
            <?php else: ?>
                <button type="submit" name="add_user" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                    Add User
                </button>
            <?php endif; ?>
        </div>
    </form>
</div>
<?php else: ?>
<!-- Users List -->
<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="p-4 border-b flex justify-between items-center">
        <h3 class="font-semibold">System Users</h3>
        <div class="flex space-x-2">
            <a href="users.php?add" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                <i class="fas fa-plus"></i> Add User
            </a>
        </div>
    </div>
    
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contact</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php
                $users = $conn->query("SELECT * FROM users ORDER BY last_name, first_name");
                
                while ($usr = $users->fetch_assoc()):
                    // Status badge color
                    $status_class = '';
                    switch ($usr['status']) {
                        case 'active': $status_class = 'bg-green-100 text-green-800'; break;
                        case 'inactive': $status_class = 'bg-red-100 text-red-800'; break;
                        case 'on_leave': $status_class = 'bg-yellow-100 text-yellow-800'; break;
                    }
                ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 h-10 w-10">
                                <img class="h-10 w-10 rounded-full" src="https://randomuser.me/api/portraits/lego/<?php echo $usr['user_id'] % 10; ?>.jpg" alt="">
                            </div>
                            <div class="ml-4">
                                <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($usr['first_name'] . ' ' . $usr['last_name']); ?></div>
                                <div class="text-sm text-gray-500"><?php echo htmlspecialchars($usr['username']); ?></div>
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm text-gray-900"><?php echo ucfirst($usr['role']); ?></div>
                        <div class="text-sm text-gray-500"><?php echo htmlspecialchars($usr['specialization']); ?></div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm text-gray-900"><?php echo htmlspecialchars($usr['email']); ?></div>
                        <div class="text-sm text-gray-500"><?php echo htmlspecialchars($usr['phone']); ?></div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $status_class; ?>">
                            <?php echo ucfirst(str_replace('_', ' ', $usr['status'])); ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                        <a href="users.php?edit=<?php echo $usr['user_id']; ?>" class="text-blue-600 hover:text-blue-900 mr-3">Edit</a>
                        <?php if ($usr['user_id'] != $_SESSION['user_id']): ?>
                            <a href="users.php?delete=<?php echo $usr['user_id']; ?>" class="text-red-600 hover:text-red-900" onclick="return confirm('Are you sure you want to delete this user?');">Delete</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>