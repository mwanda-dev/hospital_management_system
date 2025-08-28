<?php
$page_title = "Ward Management";
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

// Format functions
function format_currency($amount, $symbol) {
    return $symbol . number_format($amount, 2);
}

function format_date($date, $format_setting) {
    $formats = [
        'Y-m-d' => 'Y-m-d',
        'd/m/Y' => 'd/m/Y',
        'm/d/Y' => 'm/d/Y',
        'd-M-Y' => 'j-M-Y'
    ];
    $format = $formats[$format_setting] ?? 'Y-m-d';
    return date($format, strtotime($date));
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['admit_patient'])) {
        // Admit patient to bed
        $patient_id = intval($_POST['patient_id']);
        $bed_id = intval($_POST['bed_id']);
        $admitting_doctor_id = $_SESSION['user_id'];
        $reason = $_POST['reason'];
        $notes = $_POST['notes'] ?? '';
        
        // Check if bed is available
        $bed_check = $conn->prepare("SELECT b.*, w.ward_name FROM beds b JOIN wards w ON b.ward_id = w.ward_id WHERE b.bed_id = ? AND b.status = 'available'");
        $bed_check->bind_param("i", $bed_id);
        $bed_check->execute();
        $bed_result = $bed_check->get_result();
        
        if ($bed_result->num_rows > 0) {
            $bed_info = $bed_result->fetch_assoc();
            
            // Check if patient is already admitted
            $patient_check = $conn->prepare("SELECT * FROM admissions WHERE patient_id = ? AND status = 'admitted'");
            $patient_check->bind_param("i", $patient_id);
            $patient_check->execute();
            
            if ($patient_check->get_result()->num_rows == 0) {
                // Start transaction
                $conn->begin_transaction();
                
                try {
                    // Insert admission record
                    $stmt = $conn->prepare("
                        INSERT INTO admissions (
                            patient_id, bed_id, admitting_doctor_id, reason, notes
                        ) VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmt->bind_param("iiiss", $patient_id, $bed_id, $admitting_doctor_id, $reason, $notes);
                    $stmt->execute();
                    
                    // Update bed status to occupied
                    $stmt = $conn->prepare("UPDATE beds SET status = 'occupied' WHERE bed_id = ?");
                    $stmt->bind_param("i", $bed_id);
                    $stmt->execute();
                    
                    $conn->commit();
                    $_SESSION['message'] = "Patient admitted successfully to " . $bed_info['ward_name'] . " - Bed " . $bed_info['bed_number'] . "!";
                    header("Location: wards.php");
                    exit();
                } catch (Exception $e) {
                    $conn->rollback();
                    $error = "Error admitting patient: " . $e->getMessage();
                }
            } else {
                $error = "Patient is already admitted to another bed!";
            }
        } else {
            $error = "Selected bed is not available!";
        }
    } elseif (isset($_POST['discharge_patient'])) {
        // Discharge patient
        $admission_id = intval($_POST['admission_id']);
        
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Get admission details
            $admission_check = $conn->prepare("SELECT bed_id FROM admissions WHERE admission_id = ? AND status = 'admitted'");
            $admission_check->bind_param("i", $admission_id);
            $admission_check->execute();
            $admission_result = $admission_check->get_result();
            
            if ($admission_result->num_rows > 0) {
                $admission = $admission_result->fetch_assoc();
                
                // Update admission status and discharge date
                $stmt = $conn->prepare("UPDATE admissions SET status = 'discharged', discharge_date = NOW() WHERE admission_id = ?");
                $stmt->bind_param("i", $admission_id);
                $stmt->execute();
                
                // Update bed status to available
                $stmt = $conn->prepare("UPDATE beds SET status = 'available' WHERE bed_id = ?");
                $stmt->bind_param("i", $admission['bed_id']);
                $stmt->execute();
                
                $conn->commit();
                $_SESSION['message'] = "Patient discharged successfully!";
                header("Location: wards.php");
                exit();
            } else {
                $error = "Admission record not found!";
            }
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Error discharging patient: " . $e->getMessage();
        }
    } elseif (isset($_POST['add_ward'])) {
        // Add new ward
        $stmt = $conn->prepare("
            INSERT INTO wards (
                ward_name, ward_type, capacity, charge_per_day
            ) VALUES (?, ?, ?, ?)
        ");
        
        $stmt->bind_param(
            "ssid",
            $_POST['ward_name'],
            $_POST['ward_type'],
            $_POST['capacity'],
            $_POST['charge_per_day']
        );
        
        if ($stmt->execute()) {
            $_SESSION['message'] = "Ward added successfully!";
            header("Location: wards.php");
            exit();
        } else {
            $error = "Error adding ward: " . $conn->error;
        }
    } elseif (isset($_POST['update_ward'])) {
        // Update ward
        $stmt = $conn->prepare("
            UPDATE wards SET 
                ward_name = ?,
                ward_type = ?,
                capacity = ?,
                charge_per_day = ?
            WHERE ward_id = ?
        ");
        
        $stmt->bind_param(
            "ssidi",
            $_POST['ward_name'],
            $_POST['ward_type'],
            $_POST['capacity'],
            $_POST['charge_per_day'],
            $_POST['ward_id']
        );
        
        if ($stmt->execute()) {
            $_SESSION['message'] = "Ward updated successfully!";
            header("Location: wards.php");
            exit();
        } else {
            $error = "Error updating ward: " . $conn->error;
        }
    }
}

// Handle delete action
if (isset($_GET['delete'])) {
    $ward_id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM wards WHERE ward_id = ?");
    $stmt->bind_param("i", $ward_id);
    
    if ($stmt->execute()) {
        $_SESSION['message'] = "Ward deleted successfully!";
        header("Location: wards.php");
        exit();
    } else {
        $error = "Error deleting ward: " . $conn->error;
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

// Check if we're adding or editing a ward
$editing = false;
$ward = null;

if (isset($_GET['edit'])) {
    $editing = true;
    $ward_id = intval($_GET['edit']);
    $stmt = $conn->prepare("SELECT * FROM wards WHERE ward_id = ?");
    $stmt->bind_param("i", $ward_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $ward = $result->fetch_assoc();
    
    if (!$ward) {
        $_SESSION['error'] = "Ward not found!";
        header("Location: wards.php");
        exit();
    }
} elseif (isset($_GET['add'])) {
    $editing = true;
} elseif (isset($_GET['admit'])) {
    $admitting = true;
}
?>

<?php if ($editing): ?>
<!-- Ward Form -->
<div class="bg-white rounded-lg shadow p-6 mb-6">
    <h3 class="font-semibold text-lg mb-4"><?php echo isset($_GET['edit']) ? 'Edit Ward' : 'Add New Ward'; ?></h3>
    
    <form method="POST" action="">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2" for="ward_name">Ward Name</label>
                <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                    id="ward_name" name="ward_name" type="text" placeholder="Ward name" 
                    value="<?php echo htmlspecialchars($ward['ward_name'] ?? ''); ?>" required>
            </div>
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2" for="ward_type">Ward Type</label>
                <select class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                    id="ward_type" name="ward_type" required>
                    <option value="general" <?php echo (isset($ward['ward_type']) && $ward['ward_type'] == 'general') ? 'selected' : ''; ?>>General</option>
                    <option value="icu" <?php echo (isset($ward['ward_type']) && $ward['ward_type'] == 'icu') ? 'selected' : ''; ?>>ICU</option>
                    <option value="maternity" <?php echo (isset($ward['ward_type']) && $ward['ward_type'] == 'maternity') ? 'selected' : ''; ?>>Maternity</option>
                    <option value="pediatric" <?php echo (isset($ward['ward_type']) && $ward['ward_type'] == 'pediatric') ? 'selected' : ''; ?>>Pediatric</option>
                    <option value="surgical" <?php echo (isset($ward['ward_type']) && $ward['ward_type'] == 'surgical') ? 'selected' : ''; ?>>Surgical</option>
                </select>
            </div>
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2" for="capacity">Capacity</label>
                <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                    id="capacity" name="capacity" type="number" min="1" placeholder="Number of beds" 
                    value="<?php echo htmlspecialchars($ward['capacity'] ?? 10); ?>" required>
            </div>
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2" for="charge_per_day">Charge per Day</label>
                <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                    id="charge_per_day" name="charge_per_day" type="number" step="0.01" min="0" placeholder="0.00" 
                    value="<?php echo htmlspecialchars($ward['charge_per_day'] ?? ''); ?>" required>
            </div>
        </div>
        
        <div class="mt-6 flex justify-end space-x-4">
            <a href="wards.php" class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                Cancel
            </a>
            <?php if (isset($_GET['edit'])): ?>
                <input type="hidden" name="ward_id" value="<?php echo $ward['ward_id']; ?>">
                <button type="submit" name="update_ward" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                    Update Ward
                </button>
            <?php else: ?>
                <button type="submit" name="add_ward" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                    Add Ward
                </button>
            <?php endif; ?>
        </div>
    </form>
</div>
<?php elseif (isset($admitting)): ?>
<!-- Patient Admission Form -->
<div class="bg-white rounded-lg shadow p-6 mb-6">
    <h3 class="font-semibold text-lg mb-4">Admit Patient to Ward</h3>
    
    <!-- Patient Search -->
    <div class="mb-6">
        <label class="block text-gray-700 text-sm font-bold mb-2">Search Patient</label>
        <div class="relative">
            <input type="text" id="patientSearch" placeholder="Search by name, phone, or patient ID..." 
                class="shadow appearance-none border rounded w-full py-2 px-3 pl-10 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <i class="fas fa-search text-gray-400"></i>
            </div>
        </div>
        <div id="patientResults" class="mt-2 bg-white border border-gray-200 rounded-lg shadow-lg hidden max-h-60 overflow-y-auto"></div>
    </div>
    
    <form method="POST" action="" id="admissionForm">
        <input type="hidden" id="selectedPatientId" name="patient_id" required>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2">Selected Patient</label>
                <div id="selectedPatientInfo" class="p-3 bg-gray-50 border rounded text-sm text-gray-600">
                    No patient selected
                </div>
            </div>
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2" for="ward_select">Ward</label>
                <select class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                    id="ward_select" name="ward_id" required onchange="loadAvailableBeds(this.value)">
                    <option value="">Select Ward</option>
                    <?php
                    $wards_query = $conn->query("
                        SELECT w.*, 
                               COUNT(b.bed_id) as total_beds,
                               SUM(CASE WHEN b.status = 'available' THEN 1 ELSE 0 END) as available_beds
                        FROM wards w
                        LEFT JOIN beds b ON w.ward_id = b.ward_id
                        GROUP BY w.ward_id
                        HAVING available_beds > 0
                        ORDER BY w.ward_name
                    ");
                    while ($ward = $wards_query->fetch_assoc()):
                    ?>
                        <option value="<?php echo $ward['ward_id']; ?>" data-available="<?php echo $ward['available_beds']; ?>">
                            <?php echo htmlspecialchars($ward['ward_name']); ?> (<?php echo $ward['available_beds']; ?> beds available)
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2" for="bed_id">Available Beds</label>
                <select class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                    id="bed_id" name="bed_id" required>
                    <option value="">Select Ward First</option>
                </select>
            </div>
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2" for="reason">Admission Reason</label>
                <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                    id="reason" name="reason" type="text" placeholder="Reason for admission" required>
            </div>
        </div>
        
        <div class="mt-4">
            <label class="block text-gray-700 text-sm font-bold mb-2" for="notes">Notes</label>
            <textarea class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                id="notes" name="notes" rows="3" placeholder="Additional notes (optional)"></textarea>
        </div>
        
        <div class="mt-6 flex justify-end space-x-4">
            <a href="wards.php" class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                Cancel
            </a>
            <button type="submit" name="admit_patient" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                Admit Patient
            </button>
        </div>
    </form>
</div>
<?php else: ?>
<!-- Wards List -->
<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="p-4 border-b flex justify-between items-center">
        <h3 class="font-semibold">Wards</h3>
        <div class="flex space-x-2">
            <a href="wards.php?admit" class="bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                <i class="fas fa-user-plus"></i> Admit Patient
            </a>
            <a href="wards.php?add" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                <i class="fas fa-plus"></i> Add Ward
            </a>
        </div>
    </div>
    
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ward Name</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Beds</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Occupancy</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Daily Rate</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php
                $wards = $conn->query("
                    SELECT w.*, 
                           COUNT(b.bed_id) as total_beds,
                           SUM(CASE WHEN b.status = 'occupied' THEN 1 ELSE 0 END) as occupied_beds
                    FROM wards w
                    LEFT JOIN beds b ON w.ward_id = b.ward_id
                    GROUP BY w.ward_id
                    ORDER BY w.ward_name
                ");
                
                while ($ward = $wards->fetch_assoc()):
                    $occupancy = $ward['total_beds'] > 0 ? round(($ward['occupied_beds'] / $ward['total_beds']) * 100) : 0;
                ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($ward['ward_name']); ?></div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        <?php echo ucfirst($ward['ward_type']); ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        <?php echo $ward['occupied_beds']; ?>/<?php echo $ward['total_beds']; ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="w-full bg-gray-200 rounded-full h-2.5">
                            <div class="bg-blue-600 h-2.5 rounded-full" style="width: <?php echo $occupancy; ?>%"></div>
                        </div>
                        <div class="text-xs text-gray-500 mt-1"><?php echo $occupancy; ?>% occupied</div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        <?php echo format_currency($ward['charge_per_day'], $settings['currency_symbol']); ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                        <a href="wards.php?edit=<?php echo $ward['ward_id']; ?>" class="text-blue-600 hover:text-blue-900 mr-3">Edit</a>
                        <a href="wards.php?delete=<?php echo $ward['ward_id']; ?>" class="text-red-600 hover:text-red-900" onclick="return confirm('Are you sure you want to delete this ward?');">Delete</a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Patient Search Section -->
<div class="bg-white rounded-lg shadow p-6 mt-6">
    <h3 class="font-semibold text-lg mb-4">Find Patient Location</h3>
    <div class="relative">
        <input type="text" id="patientLocationSearch" placeholder="Search for admitted patients..." 
            class="shadow appearance-none border rounded w-full py-2 px-3 pl-10 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
            <i class="fas fa-search text-gray-400"></i>
        </div>
    </div>
    <div id="patientLocationResults" class="mt-4 space-y-2"></div>
</div>

<!-- Beds Management -->
<div class="bg-white rounded-lg shadow overflow-hidden mt-6">
    <div class="p-4 border-b flex justify-between items-center">
        <h3 class="font-semibold">Bed Management</h3>
    </div>
    
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ward</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Bed Number</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Patient</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Admission Date</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php
                $beds = $conn->query("
                    SELECT b.*, w.ward_name,
                           p.first_name as patient_first, p.last_name as patient_last,
                           a.admission_date, a.admission_id, a.reason
                    FROM beds b
                    JOIN wards w ON b.ward_id = w.ward_id
                    LEFT JOIN admissions a ON b.bed_id = a.bed_id AND a.status = 'admitted'
                    LEFT JOIN patients p ON a.patient_id = p.patient_id
                    ORDER BY w.ward_name, b.bed_number
                ");
                
                while ($bed = $beds->fetch_assoc()):
                    // Status badge color
                    $status_class = '';
                    switch ($bed['status']) {
                        case 'available': $status_class = 'bg-green-100 text-green-800'; break;
                        case 'occupied': $status_class = 'bg-blue-100 text-blue-800'; break;
                        case 'maintenance': $status_class = 'bg-red-100 text-red-800'; break;
                    }
                ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        <?php echo htmlspecialchars($bed['ward_name']); ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                        <?php echo htmlspecialchars($bed['bed_number']); ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $status_class; ?>">
                            <?php echo ucfirst($bed['status']); ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <?php if ($bed['status'] == 'occupied'): ?>
                            <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($bed['patient_first'] . ' ' . $bed['patient_last']); ?></div>
                        <?php else: ?>
                            <div class="text-sm text-gray-500">-</div>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        <?php echo $bed['status'] == 'occupied' ? format_date($bed['admission_date'], $settings['date_format']) : '-'; ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                        <?php if ($bed['status'] == 'occupied' && $bed['admission_id']): ?>
                            <form method="POST" action="" class="inline" onsubmit="return confirm('Are you sure you want to discharge this patient?');">
                                <input type="hidden" name="admission_id" value="<?php echo $bed['admission_id']; ?>">
                                <button type="submit" name="discharge_patient" class="text-red-600 hover:text-red-900">
                                    <i class="fas fa-sign-out-alt"></i> Discharge
                                </button>
                            </form>
                        <?php else: ?>
                            <span class="text-gray-400">-</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<script>
// Patient search functionality for admission
let patientSearchTimeout;
document.getElementById('patientSearch')?.addEventListener('input', function() {
    clearTimeout(patientSearchTimeout);
    const searchTerm = this.value.trim();
    
    if (searchTerm.length < 2) {
        document.getElementById('patientResults').classList.add('hidden');
        return;
    }
    
    patientSearchTimeout = setTimeout(() => {
        fetch('ajax/search_patients.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'search=' + encodeURIComponent(searchTerm)
        })
        .then(response => response.json())
        .then(data => {
            const resultsDiv = document.getElementById('patientResults');
            
            if (data.length > 0) {
                let html = '';
                data.forEach(patient => {
                    html += `
                        <div class="p-3 hover:bg-gray-50 cursor-pointer border-b" onclick="selectPatient(${patient.patient_id}, '${patient.first_name}', '${patient.last_name}', '${patient.phone}', '${patient.email}')">
                            <div class="font-medium">${patient.first_name} ${patient.last_name}</div>
                            <div class="text-sm text-gray-500">ID: PAT-${String(patient.patient_id).padStart(4, '0')} | ${patient.phone} | ${patient.email}</div>
                        </div>
                    `;
                });
                resultsDiv.innerHTML = html;
                resultsDiv.classList.remove('hidden');
            } else {
                resultsDiv.innerHTML = '<div class="p-3 text-gray-500">No patients found</div>';
                resultsDiv.classList.remove('hidden');
            }
        })
        .catch(error => {
            console.error('Error:', error);
        });
    }, 300);
});

// Select patient function
function selectPatient(id, firstName, lastName, phone, email) {
    document.getElementById('selectedPatientId').value = id;
    document.getElementById('selectedPatientInfo').innerHTML = `
        <div class="font-medium">${firstName} ${lastName}</div>
        <div class="text-xs text-gray-500">ID: PAT-${String(id).padStart(4, '0')} | ${phone} | ${email}</div>
    `;
    document.getElementById('patientResults').classList.add('hidden');
    document.getElementById('patientSearch').value = `${firstName} ${lastName}`;
}

// Load available beds when ward is selected
function loadAvailableBeds(wardId) {
    const bedSelect = document.getElementById('bed_id');
    
    if (!wardId) {
        bedSelect.innerHTML = '<option value="">Select Ward First</option>';
        return;
    }
    
    fetch('ajax/get_available_beds.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'ward_id=' + wardId
    })
    .then(response => response.json())
    .then(data => {
        let html = '<option value="">Select Bed</option>';
        data.forEach(bed => {
            html += `<option value="${bed.bed_id}">Bed ${bed.bed_number}</option>`;
        });
        bedSelect.innerHTML = html;
    })
    .catch(error => {
        console.error('Error:', error);
        bedSelect.innerHTML = '<option value="">Error loading beds</option>';
    });
}

// Patient location search functionality
let locationSearchTimeout;
document.getElementById('patientLocationSearch')?.addEventListener('input', function() {
    clearTimeout(locationSearchTimeout);
    const searchTerm = this.value.trim();
    
    if (searchTerm.length < 2) {
        document.getElementById('patientLocationResults').innerHTML = '';
        return;
    }
    
    locationSearchTimeout = setTimeout(() => {
        fetch('ajax/search_patient_location.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'search=' + encodeURIComponent(searchTerm)
        })
        .then(response => response.json())
        .then(data => {
            const resultsDiv = document.getElementById('patientLocationResults');
            
            if (data.length > 0) {
                let html = '';
                data.forEach(patient => {
                    html += `
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                            <div class="flex justify-between items-start">
                                <div>
                                    <div class="font-medium text-blue-900">${patient.first_name} ${patient.last_name}</div>
                                    <div class="text-sm text-blue-700">ID: PAT-${String(patient.patient_id).padStart(4, '0')}</div>
                                    <div class="text-sm text-blue-600 mt-1">
                                        <i class="fas fa-map-marker-alt"></i> ${patient.ward_name} - Bed ${patient.bed_number}
                                    </div>
                                    <div class="text-xs text-blue-500 mt-1">
                                        Admitted: ${new Date(patient.admission_date).toLocaleDateString()}
                                    </div>
                                </div>
                                <div class="text-right">
                                    <span class="bg-green-100 text-green-800 text-xs px-2 py-1 rounded-full">Admitted</span>
                                </div>
                            </div>
                        </div>
                    `;
                });
                resultsDiv.innerHTML = html;
            } else {
                resultsDiv.innerHTML = '<div class="text-gray-500 text-center py-4">No admitted patients found matching your search</div>';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('patientLocationResults').innerHTML = '<div class="text-red-500 text-center py-4">Error searching patients</div>';
        });
    }, 300);
});

// Hide search results when clicking outside
document.addEventListener('click', function(event) {
    const patientSearch = document.getElementById('patientSearch');
    const patientResults = document.getElementById('patientResults');
    
    if (patientSearch && patientResults && !patientSearch.contains(event.target) && !patientResults.contains(event.target)) {
        patientResults.classList.add('hidden');
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>