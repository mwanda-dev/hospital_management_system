<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

// Check if user is logged in and is a patient
checkAuth();
if (!isPatient()) {
    header("Location: ../index.php");
    exit();
}

// Get patient ID from session
$patient_id = $_SESSION['user_id'];

// Fetch patient details
$patient_stmt = $conn->prepare("SELECT first_name, last_name, email, phone FROM patients WHERE patient_id = ?");
$patient_stmt->bind_param("i", $patient_id);
$patient_stmt->execute();
$patient_result = $patient_stmt->get_result();
$patient = $patient_result->fetch_assoc();
$patient_name = $patient['first_name'] . ' ' . $patient['last_name'];

// Count upcoming appointments
$appointment_count_stmt = $conn->prepare("
    SELECT COUNT(*) as count 
    FROM appointments 
    WHERE patient_id = ? 
    AND appointment_date >= CURDATE() 
    AND status = 'scheduled'
");
$appointment_count_stmt->bind_param("i", $patient_id);
$appointment_count_stmt->execute();
$appointment_count_result = $appointment_count_stmt->get_result();
$appointment_count = $appointment_count_result->fetch_assoc()['count'];

// Calculate pending payments
$pending_payments_stmt = $conn->prepare("
    SELECT SUM(total_amount - paid_amount) as total_pending 
    FROM billing 
    WHERE patient_id = ? 
    AND status IN ('pending', 'partial', 'overdue')
");
$pending_payments_stmt->bind_param("i", $patient_id);
$pending_payments_stmt->execute();
$pending_payments_result = $pending_payments_stmt->get_result();
$pending_payments = $pending_payments_result->fetch_assoc()['total_pending'] ?? 0;

// Count recent bills
$recent_bills_stmt = $conn->prepare("
    SELECT COUNT(*) as count 
    FROM billing 
    WHERE patient_id = ? 
    AND invoice_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
");
$recent_bills_stmt->bind_param("i", $patient_id);
$recent_bills_stmt->execute();
$recent_bills_result = $recent_bills_stmt->get_result();
$recent_bills_count = $recent_bills_result->fetch_assoc()['count'];

// Count medical records
$medical_records_stmt = $conn->prepare("
    SELECT COUNT(*) as count 
    FROM medical_records 
    WHERE patient_id = ?
");
$medical_records_stmt->bind_param("i", $patient_id);
$medical_records_stmt->execute();
$medical_records_result = $medical_records_stmt->get_result();
$medical_records_count = $medical_records_result->fetch_assoc()['count'];

// Fetch upcoming appointments
$appointments_stmt = $conn->prepare("
    SELECT a.appointment_date, a.start_time, a.purpose, a.status,
           d.first_name, d.last_name
    FROM appointments a
    INNER JOIN users d ON a.doctor_id = d.user_id
    WHERE a.patient_id = ? 
    AND a.appointment_date >= CURDATE()
    ORDER BY a.appointment_date, a.start_time
    LIMIT 3
");
$appointments_stmt->bind_param("i", $patient_id);
$appointments_stmt->execute();
$appointments_result = $appointments_stmt->get_result();

// Fetch recent bills
$bills_stmt = $conn->prepare("
    SELECT invoice_id, invoice_date, total_amount, paid_amount, status
    FROM billing
    WHERE patient_id = ?
    ORDER BY invoice_date DESC
    LIMIT 3
");
$bills_stmt->bind_param("i", $patient_id);
$bills_stmt->execute();
$bills_result = $bills_stmt->get_result();

// Fetch medical records
$records_stmt = $conn->prepare("
    SELECT mr.record_date, mr.record_type, mr.title,
           d.first_name, d.last_name
    FROM medical_records mr
    INNER JOIN users d ON mr.doctor_id = d.user_id
    WHERE mr.patient_id = ?
    ORDER BY mr.record_date DESC
    LIMIT 3
");
$records_stmt->bind_param("i", $patient_id);
$records_stmt->execute();
$records_result = $records_stmt->get_result();

// Fetch current prescriptions
$prescriptions_stmt = $conn->prepare("
    SELECT p.instructions, p.refills_remaining,
           mr.prescribed_medication
    FROM prescriptions p
    INNER JOIN medical_records mr ON p.record_id = mr.record_id
    WHERE p.patient_id = ? 
    AND p.status = 'active'
    ORDER BY p.prescription_date DESC
    LIMIT 3
");
$prescriptions_stmt->bind_param("i", $patient_id);
$prescriptions_stmt->execute();
$prescriptions_result = $prescriptions_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Portal - Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Roboto', sans-serif;
        }
        
        :root {
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --secondary: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --info: #3b82f6;
            --light: #f3f4f6;
            --dark: #1f2937;
            --gray: #6b7280;
        }
        
        body {
            background-color: #f1f5f9;
            color: #333;
            line-height: 1.6;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px;
        }
        
        /* Header Styles */
        header {
            background: linear-gradient(120deg, var(--primary), var(--primary-dark));
            color: white;
            padding: 1rem 0;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            position: relative;
        }
        
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .logo i {
            font-size: 2rem;
        }
        
        .logo h1 {
            font-size: 1.5rem;
            font-weight: 600;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
            position: relative;
        }
        
        .user-info img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid white;
            cursor: pointer;
        }
        
        /* Dropdown Styles */
        .dropdown {
            position: relative;
            display: inline-block;
        }
        
        .dropdown-content {
            display: none;
            position: absolute;
            right: 0;
            top: 50px;
            background-color: white;
            min-width: 160px;
            box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2);
            z-index: 1;
            border-radius: 5px;
            overflow: hidden;
        }
        
        .dropdown-content a {
            color: var(--dark);
            padding: 12px 16px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: background-color 0.3s;
        }
        
        .dropdown-content a i {
            width: 16px;
            color: var(--gray);
        }
        
        .dropdown-content a:hover {
            background-color: #f1f5f9;
        }
        
        .dropdown-content a:hover i {
            color: var(--primary);
        }
        
        .dropdown-content a.logout:hover {
            color: var(--danger);
        }
        
        .dropdown-content a.logout:hover i {
            color: var(--danger);
        }
        
        .show {
            display: block;
        }
        
        /* Main Content */
        .main-content {
            padding: 2rem 0;
        }
        
        .welcome-banner {
            background: linear-gradient(120deg, var(--primary), var(--info));
            color: white;
            padding: 1.5rem;
            border-radius: 10px;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .welcome-banner h2 {
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            display: flex;
            align-items: center;
            gap: 1rem;
            transition: transform 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }
        
        .stat-icon.appointments {
            background: var(--info);
        }
        
        .stat-icon.payments {
            background: var(--secondary);
        }
        
        .stat-icon.bills {
            background: var(--warning);
        }
        
        .stat-icon.records {
            background: var(--primary);
        }
        
        .stat-info h3 {
            font-size: 1.8rem;
            font-weight: 700;
        }
        
        .stat-info p {
            color: var(--gray);
            font-size: 0.9rem;
        }
        
        .dashboard-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 1.5rem;
        }
        
        .card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            margin-bottom: 1.5rem;
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid #eee;
        }
        
        .card-header h3 {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--dark);
        }
        
        .card-header a {
            color: var(--primary);
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        table th, table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        table th {
            font-weight: 500;
            color: var(--gray);
            font-size: 0.9rem;
        }
        
        table td {
            font-size: 0.95rem;
        }
        
        .status {
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .status.scheduled {
            background: #dbeafe;
            color: var(--info);
        }
        
        .status.completed {
            background: #dcfce7;
            color: var(--secondary);
        }
        
        .status.pending {
            background: #fef3c7;
            color: var(--warning);
        }
        
        .status.overdue {
            background: #fee2e2;
            color: var(--danger);
        }
        
        .medication-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem 0;
            border-bottom: 1px solid #eee;
        }
        
        .medication-item:last-child {
            border-bottom: none;
        }
        
        .med-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: #e0f2fe;
            color: var(--info);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .med-info h4 {
            font-size: 1rem;
            margin-bottom: 0.25rem;
        }
        
        .med-info p {
            font-size: 0.85rem;
            color: var(--gray);
        }
        
        .action-btn {
            display: inline-block;
            padding: 0.5rem 1rem;
            background: var(--primary);
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-size: 0.9rem;
            font-weight: 500;
            transition: background 0.3s ease;
        }
        
        .action-btn:hover {
            background: var(--primary-dark);
        }
        
        /* Responsive */
        @media (max-width: 992px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .header-content {
                flex-direction: column;
                gap: 1rem;
            }
            
            .dropdown-content {
                right: -50px;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    <i class="fas fa-hospital"></i>
                    <h1>MediCare Patient Portal</h1>
                </div>
                <div class="user-info">
                    <div class="user-details">
                        <p>Welcome, <strong><?php echo htmlspecialchars($patient_name); ?></strong></p>
                    </div>
                    <div class="dropdown">
                        <img src="https://randomuser.me/api/portraits/men/32.jpg" alt="User Profile" onclick="toggleDropdown()">
                        <div id="userDropdown" class="dropdown-content">
                            <a href="patientprofile.php"><i class="fas fa-user"></i> Profile</a>
                            <a href="../includes/logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>
    
    <div class="container main-content">
        <div class="welcome-banner">
            <h2>Welcome to Your Patient Portal</h2>
            <p>Here you can manage your appointments, view medical records, check bills and prescriptions</p>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon appointments">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $appointment_count; ?></h3>
                    <p>Upcoming Appointments</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon payments">
                    <i class="fas fa-credit-card"></i>
                </div>
                <div class="stat-info">
                    <h3>$<?php echo number_format($pending_payments, 2); ?></h3>
                    <p>Pending Payments</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon bills">
                    <i class="fas fa-file-invoice-dollar"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $recent_bills_count; ?></h3>
                    <p>Recent Bills</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon records">
                    <i class="fas fa-file-medical"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $medical_records_count; ?></h3>
                    <p>Medical Records</p>
                </div>
            </div>
        </div>
        
        <div class="dashboard-grid">
            <div class="left-column">
                <div class="card">
                    <div class="card-header">
                        <h3>Upcoming Appointments</h3>
                        <a href="patientappointments.php">View All</a>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>Date & Time</th>
                                <th>Doctor</th>
                                <th>Purpose</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($appointments_result->num_rows > 0): ?>
                                <?php while($appointment = $appointments_result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo date('M j, Y', strtotime($appointment['appointment_date'])); ?> - <?php echo date('g:i A', strtotime($appointment['start_time'])); ?></td>
                                        <td>Dr. <?php echo htmlspecialchars($appointment['first_name'] . ' ' . $appointment['last_name']); ?></td>
                                        <td><?php echo htmlspecialchars($appointment['purpose']); ?></td>
                                        <td><span class="status <?php echo $appointment['status']; ?>"><?php echo ucfirst(str_replace('_', ' ', $appointment['status'])); ?></span></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" style="text-align: center;">No upcoming appointments</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h3>Recent Bills & Payments</h3>
                        <a href="patientbilling.php">View All</a>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>Invoice #</th>
                                <th>Date</th>
                                <th>Amount</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($bills_result->num_rows > 0): ?>
                                <?php while($bill = $bills_result->fetch_assoc()): ?>
                                    <tr>
                                        <td>INV-<?php echo str_pad($bill['invoice_id'], 5, '0', STR_PAD_LEFT); ?></td>
                                        <td><?php echo date('M j, Y', strtotime($bill['invoice_date'])); ?></td>
                                        <td>$<?php echo number_format($bill['total_amount'], 2); ?></td>
                                        <td>
                                            <span class="status <?php echo $bill['status']; ?>">
                                                <?php 
                                                $status_map = [
                                                    'pending' => 'Pending',
                                                    'partial' => 'Partial',
                                                    'paid' => 'Paid',
                                                    'overdue' => 'Overdue',
                                                    'canceled' => 'Canceled'
                                                ];
                                                echo $status_map[$bill['status']] ?? ucfirst($bill['status']); 
                                                ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" style="text-align: center;">No bills found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h3>Medical Records</h3>
                        <a href="patientmedicalrecords.php">View All</a>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Record Type</th>
                                <th>Doctor</th>
                                <th>Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($records_result->num_rows > 0): ?>
                                <?php while($record = $records_result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo date('M j, Y', strtotime($record['record_date'])); ?></td>
                                        <td><?php echo ucfirst(str_replace('_', ' ', $record['record_type'])); ?></td>
                                        <td>Dr. <?php echo htmlspecialchars($record['first_name'] . ' ' . $record['last_name']); ?></td>
                                        <td><a href="patientmedicalrecords.php" class="action-btn">View</a></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" style="text-align: center;">No medical records found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div class="right-column">
                <div class="card">
                    <div class="card-header">
                        <h3>Current Prescriptions</h3>
                        <a href="patientprescriptions.php">View All</a>
                    </div>
                    <?php if ($prescriptions_result->num_rows > 0): ?>
                        <?php while($prescription = $prescriptions_result->fetch_assoc()): 
                            // Extract medication name from prescribed_medication
                            $medication_data = $prescription['prescribed_medication'];
                            $medication_name = "Medication";
                            
                            if (!empty($medication_data)) {
                                // Try to parse JSON if it's stored as JSON
                                $medication_json = json_decode($medication_data, true);
                                if (json_last_error() === JSON_ERROR_NONE && isset($medication_json[0]['name'])) {
                                    $medication_name = $medication_json[0]['name'];
                                } else {
                                    // If not JSON, try to extract the first medication name
                                    $lines = explode("\n", $medication_data);
                                    foreach ($lines as $line) {
                                        if (preg_match('/([A-Za-z\s]+)\s*\d+mg/i', $line, $matches)) {
                                            $medication_name = trim($matches[1]);
                                            break;
                                        }
                                    }
                                }
                            }
                        ?>
                            <div class="medication-item">
                                <div class="med-icon">
                                    <i class="fas fa-pills"></i>
                                </div>
                                <div class="med-info">
                                    <h4><?php echo htmlspecialchars($medication_name); ?></h4>
                                    <p><?php echo htmlspecialchars($prescription['instructions'] ?? 'Take as directed'); ?> | Refills: <?php echo $prescription['refills_remaining']; ?> remaining</p>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="medication-item">
                            <div class="med-icon">
                                <i class="fas fa-pills"></i>
                            </div>
                            <div class="med-info">
                                <h4>No current prescriptions</h4>
                                <p>You don't have any active prescriptions</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h3>Quick Actions</h3>
                    </div>
                    <div style="display: grid; gap: 0.75rem;">
                        <a href="patientappointments.php" class="action-btn" style="text-align: center;"><i class="fas fa-plus-circle"></i> Schedule Appointment</a>
                        <a href="patientmedicalrecords.php" class="action-btn" style="text-align: center; background: var(--info);"><i class="fas fa-download"></i> Download Records</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Toggle dropdown visibility
        function toggleDropdown() {
            document.getElementById("userDropdown").classList.toggle("show");
        }
        
        // Close the dropdown if the user clicks outside of it
        window.onclick = function(event) {
            if (!event.target.matches('img')) {
                var dropdowns = document.getElementsByClassName("dropdown-content");
                for (var i = 0; i < dropdowns.length; i++) {
                    var openDropdown = dropdowns[i];
                    if (openDropdown.classList.contains('show')) {
                        openDropdown.classList.remove('show');
                    }
                }
            }
        }
        
        // Update the current date and time
        document.addEventListener('DOMContentLoaded', function() {
            function updateDateTime() {
                const now = new Date();
                const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
                const dateString = now.toLocaleDateString('en-US', options);
                const timeString = now.toLocaleTimeString('en-US');
                
                // If you had a datetime element, you could update it here
                // document.getElementById('datetime').innerHTML = `${dateString} - ${timeString}`;
            }
            
            updateDateTime();
            setInterval(updateDateTime, 60000); // Update every minute
        });
    </script>
</body>
</html>