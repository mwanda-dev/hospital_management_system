 
<?php
require_once 'includes/config.php';
checkAuth();

// Handle CSV export BEFORE any HTML output
if (isset($_GET['export']) && $_GET['export'] == 'csv') {
    $export_type = $_GET['type'];
    $export_start = $_GET['start'];
    $export_end = $_GET['end'];
    
    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $export_type . '_report_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Get export data based on type
    switch ($export_type) {
        case 'patients':
            fputcsv($output, ['Patient Name', 'Gender', 'Phone', 'Email', 'Registration Date']);
            $export_data = $conn->query("
                SELECT first_name, last_name, gender, phone, email, registration_date 
                FROM patients 
                WHERE registration_date BETWEEN '$export_start' AND '$export_end 23:59:59'
                ORDER BY registration_date DESC
            ");
            while ($row = $export_data->fetch_assoc()) {
                fputcsv($output, [
                    $row['first_name'] . ' ' . $row['last_name'],
                    ucfirst($row['gender']),
                    $row['phone'],
                    $row['email'],
                    date('M j, Y', strtotime($row['registration_date']))
                ]);
            }
            break;
            
        case 'appointments':
            fputcsv($output, ['Date', 'Time', 'Patient', 'Doctor', 'Specialization', 'Purpose', 'Status']);
            $export_data = $conn->query("
                SELECT a.appointment_date, a.start_time, 
                       p.first_name as patient_first, p.last_name as patient_last,
                       u.first_name as doctor_first, u.last_name as doctor_last, u.specialization,
                       a.purpose, a.status
                FROM appointments a
                JOIN patients p ON a.patient_id = p.patient_id
                JOIN users u ON a.doctor_id = u.user_id
                WHERE a.appointment_date BETWEEN '$export_start' AND '$export_end'
                ORDER BY a.appointment_date, a.start_time
            ");
            while ($row = $export_data->fetch_assoc()) {
                fputcsv($output, [
                    date('M j, Y', strtotime($row['appointment_date'])),
                    date('g:i A', strtotime($row['start_time'])),
                    $row['patient_first'] . ' ' . $row['patient_last'],
                    'Dr. ' . $row['doctor_last'],
                    $row['specialization'],
                    $row['purpose'],
                    ucfirst(str_replace('_', ' ', $row['status']))
                ]);
            }
            break;
            
        case 'billing':
            fputcsv($output, ['Invoice #', 'Patient', 'Date', 'Total Amount', 'Paid Amount', 'Status']);
            $export_data = $conn->query("
                SELECT b.invoice_id, b.invoice_date, b.total_amount, b.paid_amount, b.status,
                       p.first_name as patient_first, p.last_name as patient_last
                FROM billing b
                JOIN patients p ON b.patient_id = p.patient_id
                WHERE b.invoice_date BETWEEN '$export_start' AND '$export_end'
                ORDER BY b.invoice_date DESC
            ");
            while ($row = $export_data->fetch_assoc()) {
                fputcsv($output, [
                    'INV-' . str_pad($row['invoice_id'], 5, '0', STR_PAD_LEFT),
                    $row['patient_first'] . ' ' . $row['patient_last'],
                    date('M j, Y', strtotime($row['invoice_date'])),
                    '$' . number_format($row['total_amount'], 2),
                    '$' . number_format($row['paid_amount'], 2),
                    ucfirst($row['status'])
                ]);
            }
            break;
            
        case 'staff':
            fputcsv($output, ['Name', 'Role', 'Specialization', 'Phone', 'Email', 'Hire Date', 'Status']);
            $export_data = $conn->query("
                SELECT first_name, last_name, role, specialization, phone, email, hire_date, status 
                FROM users 
                WHERE hire_date BETWEEN '$export_start' AND '$export_end'
                ORDER BY hire_date DESC
            ");
            while ($row = $export_data->fetch_assoc()) {
                fputcsv($output, [
                    $row['first_name'] . ' ' . $row['last_name'],
                    ucfirst($row['role']),
                    $row['specialization'] ?: 'N/A',
                    $row['phone'],
                    $row['email'],
                    $row['hire_date'] ? date('M j, Y', strtotime($row['hire_date'])) : 'N/A',
                    ucfirst($row['status'])
                ]);
            }
            break;
    }
    
    fclose($output);
    exit();
}

// Set page title and include header for HTML output
$page_title = "Reports";
require_once 'includes/header.php';

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
        
    case 'staff':
        $report_title = "Staff Report";
        $report_data = $conn->query("
            SELECT * FROM users 
            WHERE hire_date BETWEEN '$start_date' AND '$end_date'
            ORDER BY hire_date DESC
        ");
        break;
        
    default:
        $report_title = "Reports";
        break;
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
                    <option value="staff" <?php echo $report_type == 'staff' ? 'selected' : ''; ?>>Staff</option>
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
    <div class="p-4 border-b">
        <div class="flex justify-between items-center mb-4">
            <h3 class="font-semibold" id="report-title"><?php echo $report_title; ?></h3>
            <div class="flex space-x-2">
                <button onclick="printReport()" class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                    <i class="fas fa-print"></i> Print
                </button>
                <a href="?export=csv&type=<?php echo $report_type; ?>&start=<?php echo $start_date; ?>&end=<?php echo $end_date; ?>" class="bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                    <i class="fas fa-file-export"></i> Export CSV
                </a>
            </div>
        </div>
        
        <!-- Search functionality -->
        <div class="mb-4">
            <div class="relative">
                <input type="text" id="searchInput" placeholder="Search in table..." 
                    class="shadow appearance-none border rounded w-full py-2 px-3 pl-10 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <i class="fas fa-search text-gray-400"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="p-4">
        <h4 class="text-sm text-gray-600 mb-4">Period: <?php echo date('M j, Y', strtotime($start_date)); ?> to <?php echo date('M j, Y', strtotime($end_date)); ?></h4>
        
        <div class="overflow-x-auto">
            <table id="reportTable" class="min-w-full divide-y divide-gray-200">
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
                        <?php elseif ($report_type == 'staff'): ?>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Staff Member</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Specialization</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Phone</th>
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
                                <?php echo date('M j, Y', strtotime($row['registration_date'])); ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php elseif ($report_type == 'appointments'): ?>
                        <?php while ($row = $report_data->fetch_assoc()): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900"><?php echo date('M j, Y', strtotime($row['appointment_date'])); ?></div>
                                <div class="text-sm text-gray-500"><?php echo date('g:i A', strtotime($row['start_time'])); ?></div>
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
                                <?php echo date('M j, Y', strtotime($row['invoice_date'])); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                $<?php echo number_format($row['total_amount'], 2); ?>
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
                    <?php elseif ($report_type == 'staff'): ?>
                        <?php while ($row = $report_data->fetch_assoc()): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></div>
                                <div class="text-sm text-gray-500"><?php echo htmlspecialchars($row['email']); ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo ucfirst($row['role']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo htmlspecialchars($row['specialization'] ?: 'N/A'); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo htmlspecialchars($row['phone']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                    <?php 
                                    switch($row['status']) {
                                        case 'active': echo 'bg-green-100 text-green-800'; break;
                                        case 'inactive': echo 'bg-red-100 text-red-800'; break;
                                        case 'on_leave': echo 'bg-yellow-100 text-yellow-800'; break;
                                    }
                                    ?>
                                ">
                                    <?php echo ucfirst(str_replace('_', ' ', $row['status'])); ?>
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

<!-- Print Styles -->
<style id="print-styles">
    @media print {
        body * {
            visibility: hidden;
        }
        
        #printable-area, #printable-area * {
            visibility: visible;
        }
        
        #printable-area {
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
        }
        
        .no-print {
            display: none !important;
        }
        
        table {
            border-collapse: collapse;
            width: 100%;
        }
        
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        
        th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        
        .print-header {
            text-align: center;
            margin-bottom: 20px;
        }
        
        .print-title {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .print-period {
            font-size: 14px;
            color: #666;
            margin-bottom: 20px;
        }
    }
</style>

<script>
// Live search functionality
document.getElementById('searchInput').addEventListener('keyup', function() {
    const searchTerm = this.value.toLowerCase();
    const table = document.getElementById('reportTable');
    const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
    
    for (let i = 0; i < rows.length; i++) {
        const row = rows[i];
        const cells = row.getElementsByTagName('td');
        let found = false;
        
        for (let j = 0; j < cells.length; j++) {
            const cellText = cells[j].textContent || cells[j].innerText;
            if (cellText.toLowerCase().indexOf(searchTerm) > -1) {
                found = true;
                break;
            }
        }
        
        if (found) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    }
});

// Print functionality
function printReport() {
    const reportTitle = document.getElementById('report-title').textContent;
    const periodText = document.querySelector('.text-sm.text-gray-600').textContent;
    const table = document.getElementById('reportTable').outerHTML;
    
    // Create printable content
    const printContent = `
        <div id="printable-area">
            <div class="print-header">
                <div class="print-title">MediCare HMS - ${reportTitle}</div>
                <div class="print-period">${periodText}</div>
            </div>
            ${table}
        </div>
    `;
    
    // Create a new window for printing
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>${reportTitle}</title>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    margin: 20px;
                }
                
                table {
                    border-collapse: collapse;
                    width: 100%;
                    margin-top: 20px;
                }
                
                th, td {
                    border: 1px solid #ddd;
                    padding: 8px;
                    text-align: left;
                }
                
                th {
                    background-color: #f2f2f2;
                    font-weight: bold;
                }
                
                .print-header {
                    text-align: center;
                    margin-bottom: 20px;
                }
                
                .print-title {
                    font-size: 24px;
                    font-weight: bold;
                    margin-bottom: 10px;
                }
                
                .print-period {
                    font-size: 14px;
                    color: #666;
                    margin-bottom: 20px;
                }
                
                .bg-green-100 { background-color: #dcfce7; }
                .bg-blue-100 { background-color: #dbeafe; }
                .bg-yellow-100 { background-color: #fef3c7; }
                .bg-red-100 { background-color: #fee2e2; }
                .bg-gray-100 { background-color: #f3f4f6; }
                
                .text-green-800 { color: #166534; }
                .text-blue-800 { color: #1e40af; }
                .text-yellow-800 { color: #92400e; }
                .text-red-800 { color: #991b1b; }
                .text-gray-800 { color: #1f2937; }
                
                .px-2 {
                    padding-left: 0.5rem;
                    padding-right: 0.5rem;
                }
                
                .inline-flex {
                    display: inline-flex;
                }
                
                .text-xs {
                    font-size: 0.75rem;
                }
                
                .leading-5 {
                    line-height: 1.25rem;
                }
                
                .font-semibold {
                    font-weight: 600;
                }
                
                .rounded-full {
                    border-radius: 9999px;
                }
            </style>
        </head>
        <body>
            ${printContent}
        </body>
        </html>
    `);
    
    printWindow.document.close();
    printWindow.focus();
    
    // Wait for content to load then print
    setTimeout(() => {
        printWindow.print();
        printWindow.close();
    }, 250);
}
</script>

<?php require_once 'includes/footer.php'; ?>