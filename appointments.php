 
<?php
$page_title = "Appointment Management";
require_once 'includes/header.php';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_appointment'])) {
        // Add new appointment
        $stmt = $conn->prepare("
            INSERT INTO appointments (
                patient_id, doctor_id, appointment_date, start_time, end_time, 
                purpose, status, notes, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $status = 'scheduled';
        $created_by = $_SESSION['user_id'];
        
        $stmt->bind_param(
            "iissssssi",
            $_POST['patient_id'],
            $_POST['doctor_id'],
            $_POST['appointment_date'],
            $_POST['start_time'],
            $_POST['end_time'],
            $_POST['purpose'],
            $status,
            $_POST['notes'],
            $created_by
        );
        
        if ($stmt->execute()) {
            $_SESSION['message'] = "Appointment scheduled successfully!";
            header("Location: appointments.php");
            exit();
        } else {
            $error = "Error scheduling appointment: " . $conn->error;
        }
    } elseif (isset($_POST['update_appointment'])) {
        // Update appointment
        $stmt = $conn->prepare("
            UPDATE appointments SET 
                patient_id = ?,
                doctor_id = ?,
                appointment_date = ?,
                start_time = ?,
                end_time = ?,
                purpose = ?,
                status = ?,
                notes = ?
            WHERE appointment_id = ?
        ");
        
        $stmt->bind_param(
            "iissssssi",
            $_POST['patient_id'],
            $_POST['doctor_id'],
            $_POST['appointment_date'],
            $_POST['start_time'],
            $_POST['end_time'],
            $_POST['purpose'],
            $_POST['status'],
            $_POST['notes'],
            $_POST['appointment_id']
        );
        
        if ($stmt->execute()) {
            $_SESSION['message'] = "Appointment updated successfully!";
            header("Location: appointments.php");
            exit();
        } else {
            $error = "Error updating appointment: " . $conn->error;
        }
    }
}

// Handle delete action
if (isset($_GET['delete'])) {
    $appointment_id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM appointments WHERE appointment_id = ?");
    $stmt->bind_param("i", $appointment_id);
    
    if ($stmt->execute()) {
        $_SESSION['message'] = "Appointment deleted successfully!";
        header("Location: appointments.php");
        exit();
    } else {
        $error = "Error deleting appointment: " . $conn->error;
    }
}

// Handle status change
if (isset($_GET['change_status'])) {
    $appointment_id = intval($_GET['change_status']);
    $new_status = $_GET['status'];
    
    $stmt = $conn->prepare("UPDATE appointments SET status = ? WHERE appointment_id = ?");
    $stmt->bind_param("si", $new_status, $appointment_id);
    
    if ($stmt->execute()) {
        $_SESSION['message'] = "Appointment status updated successfully!";
        header("Location: appointments.php");
        exit();
    } else {
        $error = "Error updating appointment status: " . $conn->error;
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

// Check if we're adding or editing an appointment
$editing = false;
$appointment = null;

if (isset($_GET['edit'])) {
    $editing = true;
    $appointment_id = intval($_GET['edit']);
    $stmt = $conn->prepare("SELECT * FROM appointments WHERE appointment_id = ?");
    $stmt->bind_param("i", $appointment_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $appointment = $result->fetch_assoc();
} elseif (isset($_GET['add'])) {
    $editing = true;
}
?>

<?php if ($editing): ?>
<!-- Appointment Form -->
<div class="bg-white rounded-lg shadow p-6 mb-6">
    <h3 class="font-semibold text-lg mb-4"><?php echo isset($_GET['edit']) ? 'Edit Appointment' : 'Schedule New Appointment'; ?></h3>
    
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
                        $selected = (isset($appointment['patient_id']) && $appointment['patient_id'] == $patient['patient_id']) ? 'selected' : '';
                    ?>
                    <option value="<?php echo $patient['patient_id']; ?>" <?php echo $selected; ?>>
                        <?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?>
                    </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2" for="doctor_id">Doctor</label>
                <select class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                    id="doctor_id" name="doctor_id" required>
                    <option value="">Select Doctor</option>
                    <?php
                    $doctors = $conn->query("SELECT user_id, first_name, last_name, specialization FROM users WHERE role = 'doctor' ORDER BY last_name, first_name");
                    while ($doctor = $doctors->fetch_assoc()):
                        $selected = (isset($appointment['doctor_id']) && $appointment['doctor_id'] == $doctor['user_id']) ? 'selected' : '';
                    ?>
                    <option value="<?php echo $doctor['user_id']; ?>" <?php echo $selected; ?>>
                        Dr. <?php echo htmlspecialchars($doctor['last_name']); ?> (<?php echo htmlspecialchars($doctor['specialization']); ?>)
                    </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2" for="appointment_date">Date</label>
                <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                    id="appointment_date" name="appointment_date" type="date" 
                    value="<?php echo htmlspecialchars($appointment['appointment_date'] ?? date('Y-m-d')); ?>" required>
            </div>
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2" for="start_time">Start Time</label>
                <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                    id="start_time" name="start_time" type="time" 
                    value="<?php echo htmlspecialchars($appointment['start_time'] ?? '09:00'); ?>" required>
            </div>
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2" for="end_time">End Time</label>
                <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                    id="end_time" name="end_time" type="time" 
                    value="<?php echo htmlspecialchars($appointment['end_time'] ?? '09:30'); ?>" required>
            </div>
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2" for="purpose">Purpose</label>
                <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                    id="purpose" name="purpose" type="text" placeholder="Purpose of appointment" 
                    value="<?php echo htmlspecialchars($appointment['purpose'] ?? ''); ?>" required>
            </div>
            <?php if (isset($_GET['edit'])): ?>
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2" for="status">Status</label>
                <select class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                    id="status" name="status" required>
                    <option value="scheduled" <?php echo (isset($appointment['status']) && $appointment['status'] == 'scheduled') ? 'selected' : ''; ?>>Scheduled</option>
                    <option value="completed" <?php echo (isset($appointment['status']) && $appointment['status'] == 'completed') ? 'selected' : ''; ?>>Completed</option>
                    <option value="canceled" <?php echo (isset($appointment['status']) && $appointment['status'] == 'canceled') ? 'selected' : ''; ?>>Canceled</option>
                    <option value="no_show" <?php echo (isset($appointment['status']) && $appointment['status'] == 'no_show') ? 'selected' : ''; ?>>No Show</option>
                </select>
            </div>
            <?php endif; ?>
            <div class="md:col-span-2">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="notes">Notes</label>
                <textarea class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                    id="notes" name="notes" placeholder="Additional notes"><?php echo htmlspecialchars($appointment['notes'] ?? ''); ?></textarea>
            </div>
        </div>
        
        <div class="mt-6 flex justify-end space-x-4">
            <a href="appointments.php" class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                Cancel
            </a>
            <?php if (isset($_GET['edit'])): ?>
                <input type="hidden" name="appointment_id" value="<?php echo $appointment['appointment_id']; ?>">
                <button type="submit" name="update_appointment" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                    Update Appointment
                </button>
            <?php else: ?>
                <button type="submit" name="add_appointment" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                    Schedule Appointment
                </button>
            <?php endif; ?>
        </div>
    </form>
</div>
<?php else: ?>
<!-- Appointment List -->
<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="p-4 border-b flex justify-between items-center">
        <h3 class="font-semibold">Appointments</h3>
        <div class="flex space-x-2">
            <a href="appointments.php?add" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                <i class="fas fa-plus"></i> New Appointment
            </a>
        </div>
    </div>
    
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date & Time</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Patient</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Doctor</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Purpose</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php
                $appointments = $conn->query("
                    SELECT a.*, 
                           p.first_name as patient_first, p.last_name as patient_last,
                           d.first_name as doctor_first, d.last_name as doctor_last, d.specialization
                    FROM appointments a
                    JOIN patients p ON a.patient_id = p.patient_id
                    JOIN users d ON a.doctor_id = d.user_id
                    ORDER BY a.appointment_date DESC, a.start_time DESC
                ");
                
                while ($appt = $appointments->fetch_assoc()):
                    $date = date('M j, Y', strtotime($appt['appointment_date']));
                    $time = date('g:i A', strtotime($appt['start_time'])) . ' - ' . date('g:i A', strtotime($appt['end_time']));
                    
                    // Status badge color
                    $status_class = '';
                    switch ($appt['status']) {
                        case 'scheduled': $status_class = 'bg-blue-100 text-blue-800'; break;
                        case 'completed': $status_class = 'bg-green-100 text-green-800'; break;
                        case 'canceled': $status_class = 'bg-red-100 text-red-800'; break;
                        case 'no_show': $status_class = 'bg-yellow-100 text-yellow-800'; break;
                    }
                ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm font-medium text-gray-900"><?php echo $date; ?></div>
                        <div class="text-sm text-gray-500"><?php echo $time; ?></div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($appt['patient_first'] . ' ' . $appt['patient_last']); ?></div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm font-medium text-gray-900">Dr. <?php echo htmlspecialchars($appt['doctor_last']); ?></div>
                        <div class="text-sm text-gray-500"><?php echo htmlspecialchars($appt['specialization']); ?></div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($appt['purpose']); ?></td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $status_class; ?>">
                            <?php echo ucfirst(str_replace('_', ' ', $appt['status'])); ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                        <a href="appointments.php?edit=<?php echo $appt['appointment_id']; ?>" class="text-blue-600 hover:text-blue-900 mr-3">Edit</a>
                        <?php if ($appt['status'] == 'scheduled'): ?>
                            <a href="appointments.php?change_status=<?php echo $appt['appointment_id']; ?>&status=completed" class="text-green-600 hover:text-green-900 mr-3">Complete</a>
                            <a href="appointments.php?change_status=<?php echo $appt['appointment_id']; ?>&status=canceled" class="text-red-600 hover:text-red-900 mr-3">Cancel</a>
                        <?php endif; ?>
                        <a href="appointments.php?delete=<?php echo $appt['appointment_id']; ?>" class="text-red-600 hover:text-red-900" onclick="return confirm('Are you sure you want to delete this appointment?');">Delete</a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>