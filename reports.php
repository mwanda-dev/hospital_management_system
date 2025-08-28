<?php
$page_title = "Reports";
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
    'date_format' => 'Y-m-d',
    'time_format' => 'H:i',
    'currency_symbol' => '$'
];
$settings = array_merge($default_settings, $settings);

// Generate reports based on filters
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : 'appointments';

// Get report data based on type
switch ($report_type) {
    case 'patients':
        $report_title = "Patient Registration Report";
        $report_data = $conn->query("
            SELECT * FROM patients 
            WHERE registration_date BETWEEN '$start_date' AND '$end_date 23:59:59'
            ORDER BY registration_date DESC
        ");
        break;
        
    case 'appointments':
        $report_title = "Appointments Report";
        $report_data = $conn->query("
            SELECT a.*, 
                   p.first_name as patient_first, p.last_name as patient_last,
                   u.first_name as doctor_first, u.last_name as doctor_last, u.specialization
            FROM appointments a
            JOIN patients p ON a.patient_id = p.patient_id
            JOIN users u ON a.doctor_id = u.user_id
            WHERE a.appointment_date BETWEEN '$start_date' AND '$end_date'
            ORDER BY a.appointment_date, a.start_time
        ");
        break;
        
    case 'billing':
        $report_title = "Billing Report";
        $report_data = $conn->query("
            SELECT b.*, p.first_name as patient_first, p.last_name as patient_last
            FROM billing b
            JOIN patients p ON b.patient_id = p.patient_id
            WHERE b.invoice_date BETWEEN '$start_date' AND '$end_date'
            ORDER BY b.invoice_date DESC
        ");
        break;
        
    default:
        $report_title = "Reports";
        break;
}

// Format dates based on system settings
function formatSystemDate($date, $format = null) {
    global $settings;
    $dateFormat = $format ?: $settings['date_format'];
    return date($dateFormat, strtotime($date));
}

function formatSystemTime($time) {
    global $settings;
    return date($settings['time_format'], strtotime($time));
}

function formatSystemCurrency($amount) {
    global $settings;
    return $settings['currency_symbol'] . number_format($amount, 2);
}
?>

<div class="bg-white rounded-lg shadow p-6 mb-6">
    <h3 class="font-semibold text-lg mb-4">Generate Report</h3>
    
    <form method="GET" action="">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2" for="report_type">Report Type</label>
                <select class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                    id="report_type" name="report_type" required>
                    <option value="appointments" <?php echo $report_type == 'appointments' ? 'selected' : ''; ?>>Appointments</option>
                    <option value="patients" <?php echo $report_type == 'patients' ? 'selected' : ''; ?>>Patient Registrations</option>
                    <option value="billing" <?php echo $report_type == 'billing' ? 'selected' : ''; ?>>Billing</option>
                </select>
            </div>
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2" for="start_date">Start Date</label>
                <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                    id="start_date" name="start_date" type="date" value="<?php echo $start_date; ?>" required>
            </div>
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2" for="end_date">End Date</label>
                <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                    id="end_date" name="end_date" type="date" value="<?php echo $end_date; ?>" required>
            </div>
            <div class="flex items-end">
                <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                    Generate
                </button>
            </div>
        </div>
    </form>
</div>

<?php if (isset($report_data)): ?>
<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="p-4 border-b flex justify-between items-center">
        <h3 class="font-semibold"><?php echo $report_title; ?></h3>
        <div class="flex space-x-2">
            <button onclick="window.print()" class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                <i class="fas fa-print"></i> Print
            </button>
            <a href="export_report.php?type=<?php echo $report_type; ?>&start=<?php echo $start_date; ?>&end=<?php echo $end_date; ?>" class="bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                <i class="fas fa-file-export"></i> Export
            </a>
        </div>
    </div>
    
    <div class="p-4">
        <h4 class="text-sm text-gray-600 mb-4">Period: <?php echo formatSystemDate($start_date); ?> to <?php echo formatSystemDate($end_date); ?></h4>
        
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <?php if ($report_type == 'patients'): ?>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Patient</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Gender</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Phone</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Registered</th>
                        <?php elseif ($report_type == 'appointments'): ?>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Patient</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Doctor</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Purpose</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <?php elseif ($report_type == 'billing'): ?>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Invoice #</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Patient</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if ($report_type == 'patients'): ?>
                        <?php while ($row = $report_data->fetch_assoc()): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></div>
                                <div class="text-sm text-gray-500"><?php echo htmlspecialchars($row['email']); ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo ucfirst($row['gender']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo htmlspecialchars($row['phone']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo formatSystemDate($row['registration_date']); ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php elseif ($report_type == 'appointments'): ?>
                        <?php while ($row = $report_data->fetch_assoc()): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900"><?php echo formatSystemDate($row['appointment_date']); ?></div>
                                <div class="text-sm text-gray-500"><?php echo formatSystemTime($row['start_time']); ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($row['patient_first'] . ' ' . $row['patient_last']); ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900">Dr. <?php echo htmlspecialchars($row['doctor_last']); ?></div>
                                <div class="text-sm text-gray-500"><?php echo htmlspecialchars($row['specialization']); ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo htmlspecialchars($row['purpose']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                    <?php 
                                    switch($row['status']) {
                                        case 'scheduled': echo 'bg-blue-100 text-blue-800'; break;
                                        case 'completed': echo 'bg-green-100 text-green-800'; break;
                                        case 'canceled': echo 'bg-red-100 text-red-800'; break;
                                        case 'no_show': echo 'bg-yellow-100 text-yellow-800'; break;
                                    }
                                    ?>
                                ">
                                    <?php echo ucfirst(str_replace('_', ' ', $row['status'])); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php elseif ($report_type == 'billing'): ?>
                        <?php while ($row = $report_data->fetch_assoc()): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                INV-<?php echo str_pad($row['invoice_id'], 5, '0', STR_PAD_LEFT); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($row['patient_first'] . ' ' . $row['patient_last']); ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo formatSystemDate($row['invoice_date']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo formatSystemCurrency($row['total_amount']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                    <?php 
                                    switch($row['status']) {
                                        case 'pending': echo 'bg-yellow-100 text-yellow-800'; break;
                                        case 'partial': echo 'bg-blue-100 text-blue-800'; break;
                                        case 'paid': echo 'bg-green-100 text-green-800'; break;
                                        case 'overdue': echo 'bg-red-100 text-red-800'; break;
                                        case 'canceled': echo 'bg-gray-100 text-gray-800'; break;
                                    }
                                    ?>
                                ">
                                    <?php echo ucfirst($row['status']); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>