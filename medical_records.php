<?php
$page_title = "Medical Records";
require_once 'includes/header.php';
// Get system settings
$settings_result = $conn->query("SELECT * FROM system_settings");
$settings = [];
while ($row = $settings_result->fetch_assoc()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
// Default settings if not set
$default_settings = [
    'hospital_name' => 'MediCare Hospital',
    'hospital_address' => '123 Medical Drive, Lusaka, Zambia',
    'hospital_phone' => '+260 211 123456',
    'hospital_email' => 'info@medicare.com',
    'currency_symbol' => '$',
    'date_format' => 'Y-m-d',
    'time_format' => 'H:i',
    'records_per_page' => '10',
    'enable_sms_notifications' => '1',
    'enable_email_notifications' => '1'
];
// Merge with defaults
$settings = array_merge($default_settings, $settings);
// Check if viewing records for a specific patient
$patient_id = isset($_GET['patient_id']) ? intval($_GET['patient_id']) : 0;
$patient = null;
if ($patient_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM patients WHERE patient_id = ?");
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $patient = $result->fetch_assoc();
    
    if (!$patient) {
        $_SESSION['error'] = "Patient not found!";
        header("Location: patients.php");
        exit();
    }
}
// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_record'])) {
        // Add new medical record
        $stmt = $conn->prepare("
            INSERT INTO medical_records (
                patient_id, doctor_id, record_type, title, description, 
                diagnosis_code, treatment_plan, prescribed_medication, 
                lab_results, follow_up_date
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $follow_up_date = !empty($_POST['follow_up_date']) ? $_POST['follow_up_date'] : null;
        
        $stmt->bind_param(
            "iissssssss",
            $_POST['patient_id'],
            $_SESSION['user_id'],
            $_POST['record_type'],
            $_POST['title'],
            $_POST['description'],
            $_POST['diagnosis_code'],
            $_POST['treatment_plan'],
            $_POST['prescribed_medication'],
            $_POST['lab_results'],
            $follow_up_date
        );
        
        if ($stmt->execute()) {
            $_SESSION['message'] = "Medical record added successfully!";
            header("Location: medical_records.php?patient_id=" . $_POST['patient_id']);
            exit();
        } else {
            $error = "Error adding medical record: " . $conn->error;
        }
    } elseif (isset($_POST['update_record'])) {
        // Update medical record
        $stmt = $conn->prepare("
            UPDATE medical_records SET 
                record_type = ?,
                title = ?,
                description = ?,
                diagnosis_code = ?,
                treatment_plan = ?,
                prescribed_medication = ?,
                lab_results = ?,
                follow_up_date = ?
            WHERE record_id = ?
        ");
        
        $follow_up_date = !empty($_POST['follow_up_date']) ? $_POST['follow_up_date'] : null;
        
        $stmt->bind_param(
            "ssssssssi",
            $_POST['record_type'],
            $_POST['title'],
            $_POST['description'],
            $_POST['diagnosis_code'],
            $_POST['treatment_plan'],
            $_POST['prescribed_medication'],
            $_POST['lab_results'],
            $follow_up_date,
            $_POST['record_id']
        );
        
        if ($stmt->execute()) {
            $_SESSION['message'] = "Medical record updated successfully!";
            header("Location: medical_records.php?patient_id=" . $_POST['patient_id']);
            exit();
        } else {
            $error = "Error updating medical record: " . $conn->error;
        }
    } elseif (isset($_POST['add_prescription'])) {
        // Add new prescription
        $stmt = $conn->prepare("
            INSERT INTO prescriptions (
                patient_id, doctor_id, prescription_date, status, 
                instructions, refills_remaining
            ) VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->bind_param(
            "iisssi",
            $_POST['patient_id'],
            $_SESSION['user_id'],
            $_POST['prescription_date'],
            $_POST['status'],
            $_POST['instructions'],
            $_POST['refills_remaining']
        );
        
        if ($stmt->execute()) {
            $_SESSION['message'] = "Prescription added successfully!";
            header("Location: medical_records.php?patient_id=" . $_POST['patient_id']);
            exit();
        } else {
            $error = "Error adding prescription: " . $conn->error;
        }
    }
}
// Handle delete actions
if (isset($_GET['delete'])) {
    $record_id = intval($_GET['delete']);
    $patient_id = intval($_GET['patient_id']);
    
    $stmt = $conn->prepare("DELETE FROM medical_records WHERE record_id = ?");
    $stmt->bind_param("i", $record_id);
    
    if ($stmt->execute()) {
        $_SESSION['message'] = "Medical record deleted successfully!";
        header("Location: medical_records.php?patient_id=" . $patient_id);
        exit();
    } else {
        $error = "Error deleting medical record: " . $conn->error;
    }
}

if (isset($_GET['delete_prescription'])) {
    $prescription_id = intval($_GET['delete_prescription']);
    $patient_id = intval($_GET['patient_id']);
    
    $stmt = $conn->prepare("DELETE FROM prescriptions WHERE prescription_id = ?");
    $stmt->bind_param("i", $prescription_id);
    
    if ($stmt->execute()) {
        $_SESSION['message'] = "Prescription deleted successfully!";
        header("Location: medical_records.php?patient_id=" . $patient_id);
        exit();
    } else {
        $error = "Error deleting prescription: " . $conn->error;
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
// Check if we're adding or editing a record
$editing = false;
$record = null;
if (isset($_GET['edit'])) {
    $editing = true;
    $record_id = intval($_GET['edit']);
    $stmt = $conn->prepare("SELECT * FROM medical_records WHERE record_id = ?");
    $stmt->bind_param("i", $record_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $record = $result->fetch_assoc();
    
    if (!$record) {
        $_SESSION['error'] = "Medical record not found!";
        header("Location: medical_records.php" . ($patient_id ? "?patient_id=$patient_id" : ""));
        exit();
    }
    
    // Make sure we have patient_id set
    $patient_id = $record['patient_id'];
    $patient_stmt = $conn->prepare("SELECT * FROM patients WHERE patient_id = ?");
    $patient_stmt->bind_param("i", $patient_id);
    $patient_stmt->execute();
    $patient_result = $patient_stmt->get_result();
    $patient = $patient_result->fetch_assoc();
} elseif (isset($_GET['add']) && $patient_id > 0) {
    $editing = true;
}
// Function to format date according to system settings
function formatSystemDate($dateString, $includeTime = false) {
    global $settings;
    if (empty($dateString)) return '';
    
    $timestamp = strtotime($dateString);
    if ($includeTime) {
        return date($settings['date_format'] . ' ' . $settings['time_format'], $timestamp);
    }
    return date($settings['date_format'], $timestamp);
}
?>
<?php if ($patient): ?>
<div class="bg-white rounded-lg shadow p-6 mb-6">
    <div class="flex items-center space-x-4">
        <img src="https://randomuser.me/api/portraits/lego/<?php echo $patient['patient_id'] % 10; ?>.jpg" alt="Patient" class="w-16 h-16 rounded-full">
        <div>
            <h3 class="text-xl font-bold"><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></h3>
            <div class="flex space-x-4 text-sm text-gray-600">
                <div>ID: PAT-<?php echo str_pad($patient['patient_id'], 4, '0', STR_PAD_LEFT); ?></div>
                <div>Age: <?php echo date_diff(date_create($patient['date_of_birth']), date_create('today'))->y; ?></div>
                <div>Gender: <?php echo ucfirst($patient['gender']); ?></div>
                <div>Blood Type: <?php echo $patient['blood_type'] ?? 'N/A'; ?></div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
<?php if ($editing): ?>
<!-- Medical Record Form -->
<div class="bg-white rounded-lg shadow p-6 mb-6">
    <h3 class="font-semibold text-lg mb-4"><?php echo isset($_GET['edit']) ? 'Edit Medical Record' : 'Add Medical Record'; ?></h3>
    
    <form method="POST" action="">
        <input type="hidden" name="patient_id" value="<?php echo $patient_id; ?>">
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="md:col-span-2">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="record_type">Record Type</label>
                <select class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                    id="record_type" name="record_type" required>
                    <option value="diagnosis" <?php echo (isset($record['record_type']) && $record['record_type'] == 'diagnosis') ? 'selected' : ''; ?>>Diagnosis</option>
                    <option value="treatment" <?php echo (isset($record['record_type']) && $record['record_type'] == 'treatment') ? 'selected' : ''; ?>>Treatment</option>
                    <option value="lab_result" <?php echo (isset($record['record_type']) && $record['record_type'] == 'lab_result') ? 'selected' : ''; ?>>Lab Result</option>
                    <option value="prescription" <?php echo (isset($record['record_type']) && $record['record_type'] == 'prescription') ? 'selected' : ''; ?>>Prescription</option>
                    <option value="vital_signs" <?php echo (isset($record['record_type']) && $record['record_type'] == 'vital_signs') ? 'selected' : ''; ?>>Vital Signs</option>
                    <option value="progress_note" <?php echo (isset($record['record_type']) && $record['record_type'] == 'progress_note') ? 'selected' : ''; ?>>Progress Note</option>
                </select>
            </div>
            <div class="md:col-span-2">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="title">Title</label>
                <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                    id="title" name="title" type="text" placeholder="Record title" 
                    value="<?php echo htmlspecialchars($record['title'] ?? ''); ?>" required>
            </div>
            <div class="md:col-span-2">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="description">Description</label>
                <textarea class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                    id="description" name="description" placeholder="Detailed description" rows="4" required><?php echo htmlspecialchars($record['description'] ?? ''); ?></textarea>
            </div>
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2" for="diagnosis_code">Diagnosis Code</label>
                <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                    id="diagnosis_code" name="diagnosis_code" type="text" placeholder="ICD-10 code" 
                    value="<?php echo htmlspecialchars($record['diagnosis_code'] ?? ''); ?>">
            </div>
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2" for="follow_up_date">Follow-up Date</label>
                <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                    id="follow_up_date" name="follow_up_date" type="date" 
                    value="<?php echo htmlspecialchars($record['follow_up_date'] ?? ''); ?>">
            </div>
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2" for="treatment_plan">Treatment Plan</label>
                <textarea class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                    id="treatment_plan" name="treatment_plan" placeholder="Treatment plan" rows="3"><?php echo htmlspecialchars($record['treatment_plan'] ?? ''); ?></textarea>
            </div>
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2" for="prescribed_medication">Prescribed Medication</label>
                <textarea class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                    id="prescribed_medication" name="prescribed_medication" placeholder="Medication details" rows="3"><?php echo htmlspecialchars($record['prescribed_medication'] ?? ''); ?></textarea>
            </div>
            <div class="md:col-span-2">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="lab_results">Lab Results</label>
                <textarea class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                    id="lab_results" name="lab_results" placeholder="Lab test results" rows="3"><?php echo htmlspecialchars($record['lab_results'] ?? ''); ?></textarea>
            </div>
        </div>
        
        <div class="mt-6 flex justify-end space-x-4">
            <a href="medical_records.php?patient_id=<?php echo $patient_id; ?>" class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                Cancel
            </a>
            <?php if (isset($_GET['edit'])): ?>
                <input type="hidden" name="record_id" value="<?php echo $record['record_id']; ?>">
                <button type="submit" name="update_record" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                    Update Record
                </button>
            <?php else: ?>
                <button type="submit" name="add_record" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                    Add Record
                </button>
            <?php endif; ?>
        </div>
    </form>
</div>
<?php elseif ($patient_id > 0): ?>
<!-- Medical Records List -->
<div class="bg-white rounded-lg shadow overflow-hidden mb-6">
    <div class="p-4 border-b flex justify-between items-center">
        <h3 class="font-semibold">Medical Records</h3>
        <div class="flex space-x-2">
            <a href="medical_records.php?add&patient_id=<?php echo $patient_id; ?>" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                <i class="fas fa-plus"></i> Add Record
            </a>
            <a href="patients.php" class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                Back to Patients
            </a>
        </div>
    </div>
    
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Doctor</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php
                // Pagination
                $records_per_page = $settings['records_per_page'];
                $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
                $offset = ($page - 1) * $records_per_page;
                
                // Get total records count
                $count_result = $conn->query("SELECT COUNT(*) as total FROM medical_records WHERE patient_id = $patient_id");
                $total_records = $count_result->fetch_assoc()['total'];
                $total_pages = ceil($total_records / $records_per_page);
                
                $records = $conn->query("
                    SELECT r.*, u.first_name as doctor_first, u.last_name as doctor_last
                    FROM medical_records r
                    JOIN users u ON r.doctor_id = u.user_id
                    WHERE r.patient_id = $patient_id
                    ORDER BY r.record_date DESC
                    LIMIT $offset, $records_per_page
                ");
                
                while ($record = $records->fetch_assoc()):
                    $date = formatSystemDate($record['record_date'], true);
                    $type = ucfirst(str_replace('_', ' ', $record['record_type']));
                ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo $date; ?></td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                            <?php echo $type; ?>
                        </span>
                    </td>
                    <td class="px-6 py-4">
                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($record['title']); ?></div>
                        <div class="text-sm text-gray-500 truncate max-w-xs"><?php echo htmlspecialchars($record['description']); ?></div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">Dr. <?php echo htmlspecialchars($record['doctor_last']); ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                        <a href="medical_records.php?edit=<?php echo $record['record_id']; ?>&patient_id=<?php echo $patient_id; ?>" class="text-blue-600 hover:text-blue-900 mr-3">Edit</a>
                        <a href="medical_records.php?delete=<?php echo $record['record_id']; ?>&patient_id=<?php echo $patient_id; ?>" class="text-red-600 hover:text-red-900" onclick="return confirm('Are you sure you want to delete this record?');">Delete</a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6">
            <div class="flex-1 flex justify-between sm:hidden">
                <?php if ($page > 1): ?>
                <a href="medical_records.php?patient_id=<?php echo $patient_id; ?>&page=<?php echo $page - 1; ?>" class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                    Previous
                </a>
                <?php endif; ?>
                <?php if ($page < $total_pages): ?>
                <a href="medical_records.php?patient_id=<?php echo $patient_id; ?>&page=<?php echo $page + 1; ?>" class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                    Next
                </a>
                <?php endif; ?>
            </div>
            <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                <div>
                    <p class="text-sm text-gray-700">
                        Showing <span class="font-medium"><?php echo $offset + 1; ?></span> to <span class="font-medium"><?php echo min($offset + $records_per_page, $total_records); ?></span> of <span class="font-medium"><?php echo $total_records; ?></span> records
                    </p>
                </div>
                <div>
                    <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                        <?php if ($page > 1): ?>
                        <a href="medical_records.php?patient_id=<?php echo $patient_id; ?>&page=<?php echo $page - 1; ?>" class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                            <span class="sr-only">Previous</span>
                            <i class="fas fa-chevron-left"></i>
                        </a>
                        <?php endif; ?>
                        
                        <?php 
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);
                        
                        if ($start_page > 1) {
                            echo '<a href="medical_records.php?patient_id='.$patient_id.'&page=1" class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">1</a>';
                            if ($start_page > 2) {
                                echo '<span class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700">...</span>';
                            }
                        }
                        
                        for ($i = $start_page; $i <= $end_page; $i++): 
                        ?>
                            <a href="medical_records.php?patient_id=<?php echo $patient_id; ?>&page=<?php echo $i; ?>" class="<?php echo $i == $page ? 'z-10 bg-blue-50 border-blue-500 text-blue-600' : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50'; ?> relative inline-flex items-center px-4 py-2 border text-sm font-medium">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; 
                        
                        if ($end_page < $total_pages) {
                            if ($end_page < $total_pages - 1) {
                                echo '<span class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700">...</span>';
                            }
                            echo '<a href="medical_records.php?patient_id='.$patient_id.'&page='.$total_pages.'" class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">'.$total_pages.'</a>';
                        }
                        ?>
                        
                        <?php if ($page < $total_pages): ?>
                        <a href="medical_records.php?patient_id=<?php echo $patient_id; ?>&page=<?php echo $page + 1; ?>" class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                            <span class="sr-only">Next</span>
                            <i class="fas fa-chevron-right"></i>
                        </a>
                        <?php endif; ?>
                    </nav>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Prescriptions Section -->
<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="p-4 border-b flex justify-between items-center">
        <h3 class="font-semibold">Prescriptions</h3>
        <button id="showPrescriptionForm" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
            <i class="fas fa-plus"></i> Add Prescription
        </button>
    </div>

    <!-- Prescription Form (initially hidden) -->
    <div id="prescriptionForm" class="p-4 border-b hidden">
        <form method="POST" action="">
            <input type="hidden" name="patient_id" value="<?php echo $patient_id; ?>">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="prescription_date">Prescription Date</label>
                    <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                        id="prescription_date" name="prescription_date" type="date" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="status">Status</label>
                    <select class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                        id="status" name="status" required>
                        <option value="active" selected>Active</option>
                        <option value="completed">Completed</option>
                        <option value="canceled">Canceled</option>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="instructions">Instructions</label>
                    <textarea class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                        id="instructions" name="instructions" placeholder="Prescription instructions" rows="3" required></textarea>
                </div>
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="refills_remaining">Refills Remaining</label>
                    <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                        id="refills_remaining" name="refills_remaining" type="number" min="0" value="0" required>
                </div>
            </div>
            <div class="mt-4 flex justify-end space-x-4">
                <button type="button" id="hidePrescriptionForm" class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                    Cancel
                </button>
                <button type="submit" name="add_prescription" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                    Add Prescription
                </button>
            </div>
        </form>
    </div>

    <!-- Prescriptions Table -->
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Doctor</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Refills</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php
                // Fetch prescriptions for this patient
                $prescriptions = $conn->query("
                    SELECT p.*, u.first_name as doctor_first, u.last_name as doctor_last
                    FROM prescriptions p
                    JOIN users u ON p.doctor_id = u.user_id
                    WHERE p.patient_id = $patient_id
                    ORDER BY p.prescription_date DESC
                ");

                while ($prescription = $prescriptions->fetch_assoc()):
                    $date = formatSystemDate($prescription['prescription_date']);
                    $statusClass = '';
                    if ($prescription['status'] == 'active') {
                        $statusClass = 'bg-green-100 text-green-800';
                    } elseif ($prescription['status'] == 'completed') {
                        $statusClass = 'bg-blue-100 text-blue-800';
                    } else {
                        $statusClass = 'bg-red-100 text-red-800';
                    }
                ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo $date; ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">Dr. <?php echo htmlspecialchars($prescription['doctor_last']); ?></td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $statusClass; ?>">
                            <?php echo ucfirst($prescription['status']); ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo $prescription['refills_remaining']; ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                        <a href="medical_records.php?delete_prescription=<?php echo $prescription['prescription_id']; ?>&patient_id=<?php echo $patient_id; ?>" class="text-red-600 hover:text-red-900" onclick="return confirm('Are you sure you want to delete this prescription?');">Delete</a>
                    </td>
                </tr>
                <?php endwhile; ?>
                
                <?php if ($prescriptions->num_rows == 0): ?>
                <tr>
                    <td colspan="5" class="px-6 py-4 text-center text-sm text-gray-500">
                        No prescriptions found for this patient.
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    document.getElementById('showPrescriptionForm').addEventListener('click', function() {
        document.getElementById('prescriptionForm').classList.remove('hidden');
    });

    document.getElementById('hidePrescriptionForm').addEventListener('click', function() {
        document.getElementById('prescriptionForm').classList.add('hidden');
    });
</script>
<?php else: ?>
<!-- Patient Selection -->
<div class="bg-white rounded-lg shadow p-6">
    <h3 class="font-semibold text-lg mb-4">Select a Patient to View Records</h3>
    
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Age</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Gender</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Phone</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php
                // Pagination for patient list
                $records_per_page = $settings['records_per_page'];
                $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
                $offset = ($page - 1) * $records_per_page;
                
                // Get total patients count
                $count_result = $conn->query("SELECT COUNT(*) as total FROM patients");
                $total_records = $count_result->fetch_assoc()['total'];
                $total_pages = ceil($total_records / $records_per_page);
                
                $patients = $conn->query("
                    SELECT * FROM patients 
                    ORDER BY last_name, first_name
                    LIMIT $offset, $records_per_page
                ");
                
                while ($patient = $patients->fetch_assoc()):
                    $age = date_diff(date_create($patient['date_of_birth']), date_create('today'))->y;
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
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                        <a href="medical_records.php?patient_id=<?php echo $patient['patient_id']; ?>" class="text-blue-600 hover:text-blue-900">View Records</a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6">
            <div class="flex-1 flex justify-between sm:hidden">
                <?php if ($page > 1): ?>
                <a href="medical_records.php?page=<?php echo $page - 1; ?>" class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                    Previous
                </a>
                <?php endif; ?>
                <?php if ($page < $total_pages): ?>
                <a href="medical_records.php?page=<?php echo $page + 1; ?>" class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                    Next
                </a>
                <?php endif; ?>
            </div>
            <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                <div>
                    <p class="text-sm text-gray-700">
                        Showing <span class="font-medium"><?php echo $offset + 1; ?></span> to <span class="font-medium"><?php echo min($offset + $records_per_page, $total_records); ?></span> of <span class="font-medium"><?php echo $total_records; ?></span> patients
                    </p>
                </div>
                <div>
                    <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                        <?php if ($page > 1): ?>
                        <a href="medical_records.php?page=<?php echo $page - 1; ?>" class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                            <span class="sr-only">Previous</span>
                            <i class="fas fa-chevron-left"></i>
                        </a>
                        <?php endif; ?>
                        
                        <?php 
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);
                        
                        if ($start_page > 1) {
                            echo '<a href="medical_records.php?page=1" class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">1</a>';
                            if ($start_page > 2) {
                                echo '<span class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700">...</span>';
                            }
                        }
                        
                        for ($i = $start_page; $i <= $end_page; $i++): 
                        ?>
                            <a href="medical_records.php?page=<?php echo $i; ?>" class="<?php echo $i == $page ? 'z-10 bg-blue-50 border-blue-500 text-blue-600' : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50'; ?> relative inline-flex items-center px-4 py-2 border text-sm font-medium">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; 
                        
                        if ($end_page < $total_pages) {
                            if ($end_page < $total_pages - 1) {
                                echo '<span class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700">...</span>';
                            }
                            echo '<a href="medical_records.php?page='.$total_pages.'" class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">'.$total_pages.'</a>';
                        }
                        ?>
                        
                        <?php if ($page < $total_pages): ?>
                        <a href="medical_records.php?page=<?php echo $page + 1; ?>" class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                            <span class="sr-only">Next</span>
                            <i class="fas fa-chevron-right"></i>
                        </a>
                        <?php endif; ?>
                    </nav>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>
<?php require_once 'includes/footer.php'; ?>