 
<?php
$page_title = "Lab Tests Management";
require_once 'includes/header.php';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_test'])) {
        // Add new lab test
        $stmt = $conn->prepare("
            INSERT INTO medical_records (
                patient_id, doctor_id, record_type, title, description, 
                lab_results, record_date
            ) VALUES (?, ?, 'lab_result', ?, ?, ?, NOW())
        ");
        
        $stmt->bind_param(
            "iisss",
            $_POST['patient_id'],
            $_SESSION['user_id'],
            $_POST['test_name'],
            $_POST['description'],
            $_POST['results']
        );
        
        if ($stmt->execute()) {
            $_SESSION['message'] = "Lab test result added successfully!";
            header("Location: lab_tests.php");
            exit();
        } else {
            $error = "Error adding lab test result: " . $conn->error;
        }
    } elseif (isset($_POST['update_test'])) {
        // Update lab test
        $stmt = $conn->prepare("
            UPDATE medical_records SET 
                patient_id = ?,
                title = ?,
                description = ?,
                lab_results = ?
            WHERE record_id = ?
        ");
        
        $stmt->bind_param(
            "isssi",
            $_POST['patient_id'],
            $_POST['test_name'],
            $_POST['description'],
            $_POST['results'],
            $_POST['record_id']
        );
        
        if ($stmt->execute()) {
            $_SESSION['message'] = "Lab test result updated successfully!";
            header("Location: lab_tests.php");
            exit();
        } else {
            $error = "Error updating lab test result: " . $conn->error;
        }
    }
}

// Handle delete action
if (isset($_GET['delete'])) {
    $record_id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM medical_records WHERE record_id = ? AND record_type = 'lab_result'");
    $stmt->bind_param("i", $record_id);
    
    if ($stmt->execute()) {
        $_SESSION['message'] = "Lab test result deleted successfully!";
        header("Location: lab_tests.php");
        exit();
    } else {
        $error = "Error deleting lab test result: " . $conn->error;
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

// Check if we're adding or editing a test
$editing = false;
$test = null;

if (isset($_GET['edit'])) {
    $editing = true;
    $record_id = intval($_GET['edit']);
    $stmt = $conn->prepare("SELECT * FROM medical_records WHERE record_id = ? AND record_type = 'lab_result'");
    $stmt->bind_param("i", $record_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $test = $result->fetch_assoc();
    
    if (!$test) {
        $_SESSION['error'] = "Lab test not found!";
        header("Location: lab_tests.php");
        exit();
    }
} elseif (isset($_GET['add'])) {
    $editing = true;
}
?>

<?php if ($editing): ?>
<!-- Lab Test Form -->
<div class="bg-white rounded-lg shadow p-6 mb-6">
    <h3 class="font-semibold text-lg mb-4"><?php echo isset($_GET['edit']) ? 'Edit Lab Test Result' : 'Add Lab Test Result'; ?></h3>
    
    <form method="POST" action="">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2" for="patient_id">Patient</label>
                <select class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                    id="patient_id" name="patient_id" required>
                    <option value="">Select Patient</option>
                    <?php
                    $patients = $conn->query("SELECT patient_id, first_name, last_name FROM patients ORDER BY last_name, first_name");
                    while ($patient = $patients->fetch_assoc()):
                        $selected = (isset($test['patient_id']) && $test['patient_id'] == $patient['patient_id']) ? 'selected' : '';
                    ?>
                    <option value="<?php echo $patient['patient_id']; ?>" <?php echo $selected; ?>>
                        <?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?>
                    </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2" for="test_name">Test Name</label>
                <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                    id="test_name" name="test_name" type="text" placeholder="Test name" 
                    value="<?php echo htmlspecialchars($test['title'] ?? ''); ?>" required>
            </div>
            <div class="md:col-span-2">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="description">Description</label>
                <textarea class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                    id="description" name="description" placeholder="Test description" rows="3"><?php echo htmlspecialchars($test['description'] ?? ''); ?></textarea>
            </div>
            <div class="md:col-span-2">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="results">Results</label>
                <textarea class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                    id="results" name="results" placeholder="Test results" rows="5" required><?php echo htmlspecialchars($test['lab_results'] ?? ''); ?></textarea>
            </div>
        </div>
        
        <div class="mt-6 flex justify-end space-x-4">
            <a href="lab_tests.php" class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                Cancel
            </a>
            <?php if (isset($_GET['edit'])): ?>
                <input type="hidden" name="record_id" value="<?php echo $test['record_id']; ?>">
                <button type="submit" name="update_test" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                    Update Test
                </button>
            <?php else: ?>
                <button type="submit" name="add_test" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                    Add Test Result
                </button>
            <?php endif; ?>
        </div>
    </form>
</div>
<?php else: ?>
<!-- Lab Tests List -->
<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="p-4 border-b flex justify-between items-center">
        <h3 class="font-semibold">Lab Tests</h3>
        <div class="flex space-x-2">
            <a href="lab_tests.php?add" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                <i class="fas fa-plus"></i> Add Test Result
            </a>
        </div>
    </div>
    
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Patient</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Test Name</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Technician</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php
                $tests = $conn->query("
                    SELECT r.record_id, r.record_date, r.title, r.lab_results,
                           p.first_name as patient_first, p.last_name as patient_last,
                           u.first_name as tech_first, u.last_name as tech_last
                    FROM medical_records r
                    JOIN patients p ON r.patient_id = p.patient_id
                    JOIN users u ON r.doctor_id = u.user_id
                    WHERE r.record_type = 'lab_result'
                    ORDER BY r.record_date DESC
                ");
                
                while ($test = $tests->fetch_assoc()):
                    $date = date('M j, Y g:i A', strtotime($test['record_date']));
                ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo $date; ?></td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($test['patient_first'] . ' ' . $test['patient_last']); ?></div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($test['title']); ?></div>
                        <div class="text-sm text-gray-500 truncate max-w-xs"><?php echo htmlspecialchars($test['lab_results']); ?></div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($test['tech_first'] . ' ' . $test['tech_last']); ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                        <a href="lab_tests.php?edit=<?php echo $test['record_id']; ?>" class="text-blue-600 hover:text-blue-900 mr-3">View/Edit</a>
                        <a href="lab_tests.php?delete=<?php echo $test['record_id']; ?>" class="text-red-600 hover:text-red-900" onclick="return confirm('Are you sure you want to delete this lab test result?');">Delete</a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>