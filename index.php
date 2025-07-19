 
<?php
$page_title = "Dashboard";
require_once 'includes/header.php';

// Get counts for dashboard
$patients_count = $conn->query("SELECT COUNT(*) FROM patients")->fetch_row()[0];
$appointments_count = $conn->query("SELECT COUNT(*) FROM appointments WHERE DATE(appointment_date) = CURDATE()")->fetch_row()[0];
$beds_count = $conn->query("SELECT COUNT(*) FROM beds WHERE status = 'occupied'")->fetch_row()[0];
$pending_payments = $conn->query("SELECT SUM(total_amount - paid_amount) FROM billing WHERE status IN ('pending', 'partial')")->fetch_row()[0];
?>

<!-- Stats Cards -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <div class="dashboard-card bg-white rounded-lg shadow p-6 flex items-center space-x-4 transition-all duration-300">
        <div class="bg-blue-100 p-3 rounded-full">
            <i class="fas fa-user-injured text-blue-500 text-xl"></i>
        </div>
        <div>
            <p class="text-gray-500 text-sm">Total Patients</p>
            <h3 class="text-2xl font-bold"><?php echo number_format($patients_count); ?></h3>
        </div>
    </div>
    
    <div class="dashboard-card bg-white rounded-lg shadow p-6 flex items-center space-x-4 transition-all duration-300">
        <div class="bg-green-100 p-3 rounded-full">
            <i class="fas fa-calendar-check text-green-500 text-xl"></i>
        </div>
        <div>
            <p class="text-gray-500 text-sm">Today's Appointments</p>
            <h3 class="text-2xl font-bold"><?php echo number_format($appointments_count); ?></h3>
        </div>
    </div>
    
    <div class="dashboard-card bg-white rounded-lg shadow p-6 flex items-center space-x-4 transition-all duration-300">
        <div class="bg-purple-100 p-3 rounded-full">
            <i class="fas fa-procedures text-purple-500 text-xl"></i>
        </div>
        <div>
            <p class="text-gray-500 text-sm">Occupied Beds</p>
            <h3 class="text-2xl font-bold"><?php echo number_format($beds_count); ?></h3>
            <?php 
            $total_beds = $conn->query("SELECT COUNT(*) FROM beds")->fetch_row()[0];
            $capacity = $total_beds > 0 ? round(($beds_count / $total_beds) * 100) : 0;
            ?>
            <p class="text-xs text-gray-500"><?php echo $capacity; ?>% capacity</p>
        </div>
    </div>
    
    <div class="dashboard-card bg-white rounded-lg shadow p-6 flex items-center space-x-4 transition-all duration-300">
        <div class="bg-yellow-100 p-3 rounded-full">
            <i class="fas fa-file-invoice-dollar text-yellow-500 text-xl"></i>
        </div>
        <div>
            <p class="text-gray-500 text-sm">Pending Payments</p>
            <h3 class="text-2xl font-bold">$<?php echo number_format($pending_payments ?? 0, 2); ?></h3>
        </div>
    </div>
</div>

<!-- Recent Activity and Quick Actions -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
    <!-- Recent Activity -->
    <div class="bg-white rounded-lg shadow p-6 lg:col-span-2">
        <div class="flex justify-between items-center mb-4">
            <h3 class="font-semibold text-lg">Recent Activity</h3>
            <a href="#" class="text-blue-500 text-sm">View All</a>
        </div>
        <div class="space-y-4">
            <?php
            $activities = $conn->query("
                SELECT 'patient' as type, first_name, last_name, registration_date as date 
                FROM patients 
                ORDER BY registration_date DESC 
                LIMIT 4
            ");
            
            while ($activity = $activities->fetch_assoc()):
                $time_ago = time_elapsed_string($activity['date']);
            ?>
            <div class="flex items-start space-x-3">
                <div class="bg-blue-100 p-2 rounded-full">
                    <i class="fas fa-user-plus text-blue-500 text-sm"></i>
                </div>
                <div>
                    <p class="text-sm">New patient <span class="font-medium"><?php echo htmlspecialchars($activity['first_name'] . ' ' . $activity['last_name']); ?></span> registered</p>
                    <p class="text-xs text-gray-500"><?php echo $time_ago; ?></p>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="font-semibold text-lg mb-4">Quick Actions</h3>
        <div class="grid grid-cols-2 gap-4">
            <a href="patients.php?action=add" class="bg-blue-50 hover:bg-blue-100 rounded-lg p-4 flex flex-col items-center justify-center text-center transition-colors">
                <i class="fas fa-user-plus text-blue-500 text-xl mb-2"></i>
                <span class="text-sm font-medium">Register Patient</span>
            </a>
            <a href="appointments.php?action=add" class="bg-green-50 hover:bg-green-100 rounded-lg p-4 flex flex-col items-center justify-center text-center transition-colors">
                <i class="fas fa-calendar-plus text-green-500 text-xl mb-2"></i>
                <span class="text-sm font-medium">New Appointment</span>
            </a>
            <a href="medical_records.php?action=add" class="bg-purple-50 hover:bg-purple-100 rounded-lg p-4 flex flex-col items-center justify-center text-center transition-colors">
                <i class="fas fa-file-medical text-purple-500 text-xl mb-2"></i>
                <span class="text-sm font-medium">Add Diagnosis</span>
            </a>
            <a href="billing.php?action=add" class="bg-yellow-50 hover:bg-yellow-100 rounded-lg p-4 flex flex-col items-center justify-center text-center transition-colors">
                <i class="fas fa-file-invoice-dollar text-yellow-500 text-xl mb-2"></i>
                <span class="text-sm font-medium">Create Bill</span>
            </a>
        </div>
    </div>
</div>

<!-- Recent Patients and Appointments -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <!-- Recent Patients -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="p-4 border-b flex justify-between items-center">
            <h3 class="font-semibold">Recent Patients</h3>
            <a href="patients.php" class="text-blue-500 text-sm">View All</a>
        </div>
        <div class="divide-y">
            <?php
            $recent_patients = $conn->query("
                SELECT patient_id, first_name, last_name, date_of_birth, registration_date 
                FROM patients 
                ORDER BY registration_date DESC 
                LIMIT 4
            ");
            
            while ($patient = $recent_patients->fetch_assoc()):
                $age = date_diff(date_create($patient['date_of_birth']), date_create('today'))->y;
            ?>
            <div class="p-4 flex items-center space-x-4 hover:bg-gray-50">
                <img src="https://randomuser.me/api/portraits/lego/<?php echo $patient['patient_id'] % 10; ?>.jpg" alt="Patient" class="w-10 h-10 rounded-full">
                <div class="flex-1">
                    <p class="font-medium"><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></p>
                    <p class="text-xs text-gray-500">ID: PAT-<?php echo str_pad($patient['patient_id'], 4, '0', STR_PAD_LEFT); ?> | <?php echo $age; ?> years</p>
                </div>
                <span class="bg-green-100 text-green-800 text-xs px-2 py-1 rounded">Active</span>
            </div>
            <?php endwhile; ?>
        </div>
    </div>
    
    <!-- Upcoming Appointments -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="p-4 border-b flex justify-between items-center">
            <h3 class="font-semibold">Upcoming Appointments</h3>
            <a href="appointments.php" class="text-blue-500 text-sm">View All</a>
        </div>
        <div class="divide-y">
            <?php
            $upcoming_appointments = $conn->query("
                SELECT a.appointment_id, a.appointment_date, a.start_time, 
                       p.first_name as patient_first, p.last_name as patient_last,
                       u.first_name as doctor_first, u.last_name as doctor_last, u.specialization
                FROM appointments a
                JOIN patients p ON a.patient_id = p.patient_id
                JOIN users u ON a.doctor_id = u.user_id
                WHERE a.appointment_date >= CURDATE() AND a.status = 'scheduled'
                ORDER BY a.appointment_date, a.start_time
                LIMIT 4
            ");
            
            while ($appointment = $upcoming_appointments->fetch_assoc()):
            ?>
            <div class="p-4 flex items-center space-x-4 hover:bg-gray-50">
                <div class="bg-blue-100 p-3 rounded-full">
                    <i class="fas fa-calendar-day text-blue-500"></i>
                </div>
                <div class="flex-1">
                    <p class="font-medium">Dr. <?php echo htmlspecialchars($appointment['doctor_last']); ?> - <?php echo htmlspecialchars($appointment['specialization']); ?></p>
                    <p class="text-sm text-gray-500"><?php echo htmlspecialchars($appointment['patient_first'] . ' ' . $appointment['patient_last']); ?> | <?php echo date('g:i A', strtotime($appointment['start_time'])); ?></p>
                </div>
                <button class="text-blue-500 hover:text-blue-700">
                    <i class="fas fa-ellipsis-v"></i>
                </button>
            </div>
            <?php endwhile; ?>
        </div>
    </div>
</div>

<?php
require_once 'includes/footer.php';

function time_elapsed_string($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    // Build an array of time units with their values
    $units = [
        'y' => $diff->y,
        'm' => $diff->m,
        'd' => $diff->d,
        'h' => $diff->h,
        'i' => $diff->i,
        's' => $diff->s,
    ];

    // Convert days to weeks if needed (for non-full mode)
    if (!$full && $units['d'] >= 7) {
        $weeks = floor($units['d'] / 7);
        $units['w'] = $weeks;
        $units['d'] = $units['d'] % 7;
    }

    // Define labels for each unit
    $labels = [
        'y' => 'year',
        'm' => 'month',
        'w' => 'week',
        'd' => 'day',
        'h' => 'hour',
        'i' => 'minute',
        's' => 'second',
    ];

    // Build the output string
    $parts = [];
    foreach ($units as $unit => $value) {
        if ($value > 0 && isset($labels[$unit])) {
            $parts[] = $value . ' ' . $labels[$unit] . ($value > 1 ? 's' : '');
        }
    }

    if (!$full) {
        $parts = array_slice($parts, 0, 1);
    }

    return $parts ? implode(', ', $parts) . ' ago' : 'just now';
}
?>