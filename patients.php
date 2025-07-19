 
<?php
$page_title = "Patient Management";
require_once 'includes/header.php';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_patient'])) {
        // Add new patient
        $stmt = $conn->prepare("
            INSERT INTO patients (
                first_name, last_name, date_of_birth, gender, blood_type, 
                phone, email, address, emergency_contact_name, 
                emergency_contact_phone, insurance_provider, insurance_policy_number
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->bind_param(
            "ssssssssssss",
            $_POST['first_name'],
            $_POST['last_name'],
            $_POST['date_of_birth'],
            $_POST['gender'],
            $_POST['blood_type'],
            $_POST['phone'],
            $_POST['email'],
            $_POST['address'],
            $_POST['emergency_contact_name'],
            $_POST['emergency_contact_phone'],
            $_POST['insurance_provider'],
            $_POST['insurance_policy_number']
        );
        
        if ($stmt->execute()) {
            $_SESSION['message'] = "Patient added successfully!";
            header("Location: patients.php");
            exit();
        } else {
            $error = "Error adding patient: " . $conn->error;
        }
    } elseif (isset($_POST['update_patient'])) {
        // Update patient
        $stmt = $conn->prepare("
            UPDATE patients SET 
                first_name = ?,
                last_name = ?,
                date_of_birth = ?,
                gender = ?,
                blood_type = ?,
                phone = ?,
                email = ?,
                address = ?,
                emergency_contact_name = ?,
                emergency_contact_phone = ?,
                insurance_provider = ?,
                insurance_policy_number = ?
            WHERE patient_id = ?
        ");
        
        $stmt->bind_param(
            "ssssssssssssi",
            $_POST['first_name'],
            $_POST['last_name'],
            $_POST['date_of_birth'],
            $_POST['gender'],
            $_POST['blood_type'],
            $_POST['phone'],
            $_POST['email'],
            $_POST['address'],
            $_POST['emergency_contact_name'],
            $_POST['emergency_contact_phone'],
            $_POST['insurance_provider'],
            $_POST['insurance_policy_number'],
            $_POST['patient_id']
        );
        
        if ($stmt->execute()) {
            $_SESSION['message'] = "Patient updated successfully!";
            header("Location: patients.php");
            exit();
        } else {
            $error = "Error updating patient: " . $conn->error;
        }
    }
}

// Handle delete action
if (isset($_GET['delete'])) {
    $patient_id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM patients WHERE patient_id = ?");
    $stmt->bind_param("i", $patient_id);
    
    if ($stmt->execute()) {
        $_SESSION['message'] = "Patient deleted successfully!";
        header("Location: patients.php");
        exit();
    } else {
        $error = "Error deleting patient: " . $conn->error;
    }
}

// Display messages
if (isset($_SESSION['message'])) {
    echo '<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
        <span class="block sm:inline">' . $_SESSION['message'] . '</span>
    </div>';
    unset($_SESSION['message']);
}

if (isset($error)) {
    echo '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
        <span class="block sm:inline">' . $error . '</span>
    </div>';
}

// Check if we're adding or editing a patient
$editing = false;
$patient = null;

if (isset($_GET['edit'])) {
    $editing = true;
    $patient_id = intval($_GET['edit']);
    $stmt = $conn->prepare("SELECT * FROM patients WHERE patient_id = ?");
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $patient = $result->fetch_assoc();
} elseif (isset($_GET['add'])) {
    $editing = true;
}
?>

<?php if ($editing): ?>
<!-- Patient Form -->
<div class="bg-white rounded-lg shadow p-6 mb-6">
    <h3 class="font-semibold text-lg mb-4"><?php echo isset($_GET['edit']) ? 'Edit Patient' : 'Add New Patient'; ?></h3>
    
    <form method="POST" action="">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2" for="first_name">First Name</label>
                <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                    id="first_name" name="first_name" type="text" placeholder="First Name" 
                    value="<?php echo htmlspecialchars($patient['first_name'] ?? ''); ?>" required>
            </div>
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2" for="last_name">Last Name</label>
                <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                    id="last_name" name="last_name" type="text" placeholder="Last Name" 
                    value="<?php echo htmlspecialchars($patient['last_name'] ?? ''); ?>" required>
            </div>
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2" for="date_of_birth">Date of Birth</label>
                <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                    id="date_of_birth" name="date_of_birth" type="date" 
                    value="<?php echo htmlspecialchars($patient['date_of_birth'] ?? ''); ?>" required>
            </div>
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2" for="gender">Gender</label>
                <select class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                    id="gender" name="gender" required>
                    <option value="male" <?php echo (isset($patient['gender']) && $patient['gender'] == 'male') ? 'selected' : ''; ?>>Male</option>
                    <option value="female" <?php echo (isset($patient['gender']) && $patient['gender'] == 'female') ? 'selected' : ''; ?>>Female</option>
                    <option value="other" <?php echo (isset($patient['gender']) && $patient['gender'] == 'other') ? 'selected' : ''; ?>>Other</option>
                </select>
            </div>
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2" for="blood_type">Blood Type</label>
                <select class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                    id="blood_type" name="blood_type">
                    <option value="">Select Blood Type</option>
                    <option value="A+" <?php echo (isset($patient['blood_type'])) && $patient['blood_type'] == 'A+' ? 'selected' : ''; ?>>A+</option>
                    <option value="A-" <?php echo (isset($patient['blood_type'])) && $patient['blood_type'] == 'A-' ? 'selected' : ''; ?>>A-</option>
                    <option value="B+" <?php echo (isset($patient['blood_type'])) && $patient['blood_type'] == 'B+' ? 'selected' : ''; ?>>B+</option>
                    <option value="B-" <?php echo (isset($patient['blood_type'])) && $patient['blood_type'] == 'B-' ? 'selected' : ''; ?>>B-</option>
                    <option value="AB+" <?php echo (isset($patient['blood_type'])) && $patient['blood_type'] == 'AB+' ? 'selected' : ''; ?>>AB+</option>
                    <option value="AB-" <?php echo (isset($patient['blood_type'])) && $patient['blood_type'] == 'AB-' ? 'selected' : ''; ?>>AB-</option>
                    <option value="O+" <?php echo (isset($patient['blood_type'])) && $patient['blood_type'] == 'O+' ? 'selected' : ''; ?>>O+</option>
                    <option value="O-" <?php echo (isset($patient['blood_type'])) && $patient['blood_type'] == 'O-' ? 'selected' : ''; ?>>O-</option>
                </select>
            </div>
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2" for="phone">Phone</label>
                <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                    id="phone" name="phone" type="tel" placeholder="Phone" 
                    value="<?php echo htmlspecialchars($patient['phone'] ?? ''); ?>" required>
            </div>
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2" for="email">Email</label>
                <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                    id="email" name="email" type="email" placeholder="Email" 
                    value="<?php echo htmlspecialchars($patient['email'] ?? ''); ?>">
            </div>
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2" for="address">Address</label>
                <textarea class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                    id="address" name="address" placeholder="Address"><?php echo htmlspecialchars($patient['address'] ?? ''); ?></textarea>
            </div>
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2" for="emergency_contact_name">Emergency Contact Name</label>
                <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                    id="emergency_contact_name" name="emergency_contact_name" type="text" placeholder="Emergency Contact Name" 
                    value="<?php echo htmlspecialchars($patient['emergency_contact_name'] ?? ''); ?>">
            </div>
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2" for="emergency_contact_phone">Emergency Contact Phone</label>
                <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                    id="emergency_contact_phone" name="emergency_contact_phone" type="tel" placeholder="Emergency Contact Phone" 
                    value="<?php echo htmlspecialchars($patient['emergency_contact_phone'] ?? ''); ?>">
            </div>
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2" for="insurance_provider">Insurance Provider</label>
                <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                    id="insurance_provider" name="insurance_provider" type="text" placeholder="Insurance Provider" 
                    value="<?php echo htmlspecialchars($patient['insurance_provider'] ?? ''); ?>">
            </div>
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2" for="insurance_policy_number">Insurance Policy Number</label>
                <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                    id="insurance_policy_number" name="insurance_policy_number" type="text" placeholder="Insurance Policy Number" 
                    value="<?php echo htmlspecialchars($patient['insurance_policy_number'] ?? ''); ?>">
            </div>
        </div>
        
        <div class="mt-6 flex justify-end space-x-4">
            <a href="patients.php" class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                Cancel
            </a>
            <?php if (isset($_GET['edit'])): ?>
                <input type="hidden" name="patient_id" value="<?php echo $patient['patient_id']; ?>">
                <button type="submit" name="update_patient" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                    Update Patient
                </button>
            <?php else: ?>
                <button type="submit" name="add_patient" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                    Add Patient
                </button>
            <?php endif; ?>
        </div>
    </form>
</div>
<?php else: ?>
<!-- Patient List -->
<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="p-4 border-b flex justify-between items-center">
        <h3 class="font-semibold">Patient Records</h3>
        <div class="flex space-x-2">
            <a href="patients.php?add" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                <i class="fas fa-plus"></i> Add Patient
            </a>
        </div>
    </div>
    
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Age</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Gender</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Phone</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Registered</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php
                $patients = $conn->query("
                    SELECT * FROM patients 
                    ORDER BY registration_date DESC
                ");
                
                while ($patient = $patients->fetch_assoc()):
                    $age = date_diff(date_create($patient['date_of_birth']), date_create('today'))->y;
                    $reg_date = date('M j, Y', strtotime($patient['registration_date']));
                ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">PAT-<?php echo str_pad($patient['patient_id'], 4, '0', STR_PAD_LEFT); ?></td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 h-10 w-10">
                                <img class="h-10 w-10 rounded-full" src="https://randomuser.me/api/portraits/lego/<?php echo $patient['patient_id'] % 10; ?>.jpg" alt="">
                            </div>
                            <div class="ml-4">
                                <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></div>
                                <div class="text-sm text-gray-500"><?php echo htmlspecialchars($patient['email']); ?></div>
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo $age; ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo ucfirst($patient['gender']); ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($patient['phone']); ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo $reg_date; ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                        <a href="patients.php?edit=<?php echo $patient['patient_id']; ?>" class="text-blue-600 hover:text-blue-900 mr-3">Edit</a>
                        <a href="medical_records.php?patient_id=<?php echo $patient['patient_id']; ?>" class="text-green-600 hover:text-green-900 mr-3">Records</a>
                        <a href="patients.php?delete=<?php echo $patient['patient_id']; ?>" class="text-red-600 hover:text-red-900" onclick="return confirm('Are you sure you want to delete this patient?');">Delete</a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>