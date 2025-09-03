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

// Get current patient ID
$patient_id = $_SESSION['user_id'];

// Fetch appointments for the current patient
$appointments = [];
$stmt = $conn->prepare("
    SELECT a.*, d.first_name, d.last_name, d.specialization 
    FROM appointments a 
    JOIN users d ON a.doctor_id = d.user_id 
    WHERE a.patient_id = ? 
    ORDER BY a.appointment_date DESC, a.start_time DESC
");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $appointments[] = $row;
    }
}
$stmt->close();

// Handle new appointment submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['schedule_appointment'])) {
    $doctor_id = $_POST['doctor_id'];
    $appointment_date = $_POST['appointment_date'];
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];
    $purpose = $_POST['purpose'];
    $notes = $_POST['notes'];
    
    // Insert new appointment
    $stmt = $conn->prepare("
        INSERT INTO appointments (patient_id, doctor_id, appointment_date, start_time, end_time, purpose, notes, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("iisssssi", $patient_id, $doctor_id, $appointment_date, $start_time, $end_time, $purpose, $notes, $patient_id);
    
    if ($stmt->execute()) {
        $success = "Appointment scheduled successfully!";
        // Refresh the page to show the new appointment
        header("Location: patientappointments.php");
        exit();
    } else {
        $error = "Error scheduling appointment: " . $conn->error;
    }
    $stmt->close();
}

// Handle appointment cancellation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['cancel_appointment'])) {
    $appointment_id = $_POST['appointment_id'];
    $cancel_reason = $_POST['cancel_reason'];
    
    // Update appointment status to canceled
    $stmt = $conn->prepare("
        UPDATE appointments 
        SET status = 'canceled', cancel_reason = ?, updated_at = NOW() 
        WHERE appointment_id = ? AND patient_id = ?
    ");
    $stmt->bind_param("sii", $cancel_reason, $appointment_id, $patient_id);
    
    if ($stmt->execute()) {
        $success = "Appointment canceled successfully!";
        // Refresh the page to show the updated appointment
        header("Location: patientappointments.php");
        exit();
    } else {
        $error = "Error canceling appointment: " . $conn->error;
    }
    $stmt->close();
}

// Handle appointment update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_appointment'])) {
    $appointment_id = $_POST['appointment_id'];
    $doctor_id = $_POST['doctor_id'];
    $appointment_date = $_POST['appointment_date'];
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];
    $purpose = $_POST['purpose'];
    $notes = $_POST['notes'];
    
    // Update appointment
    $stmt = $conn->prepare("
        UPDATE appointments 
        SET doctor_id = ?, appointment_date = ?, start_time = ?, end_time = ?, purpose = ?, notes = ?, updated_at = NOW() 
        WHERE appointment_id = ? AND patient_id = ?
    ");
    $stmt->bind_param("isssssii", $doctor_id, $appointment_date, $start_time, $end_time, $purpose, $notes, $appointment_id, $patient_id);
    
    if ($stmt->execute()) {
        $success = "Appointment updated successfully!";
        // Refresh the page to show the updated appointment
        header("Location: patientappointments.php");
        exit();
    } else {
        $error = "Error updating appointment: " . $conn->error;
    }
    $stmt->close();
}

// Fetch doctors for the dropdown
$doctors = [];
$doctor_result = $conn->query("SELECT user_id, first_name, last_name, specialization FROM users WHERE status = 'active' ORDER BY first_name, last_name");
if ($doctor_result->num_rows > 0) {
    while ($row = $doctor_result->fetch_assoc()) {
        $doctors[] = $row;
    }
}

// Get appointment details for editing
$edit_appointment = null;
if (isset($_GET['edit_id'])) {
    $edit_id = $_GET['edit_id'];
    $stmt = $conn->prepare("
        SELECT a.*, d.first_name, d.last_name, d.specialization 
        FROM appointments a 
        JOIN users d ON a.doctor_id = d.user_id 
        WHERE a.appointment_id = ? AND a.patient_id = ?
    ");
    $stmt->bind_param("ii", $edit_id, $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $edit_appointment = $result->fetch_assoc();
    }
    $stmt->close();
}

// Get appointment details for cancellation
$cancel_appointment = null;
if (isset($_GET['cancel_id'])) {
    $cancel_id = $_GET['cancel_id'];
    $stmt = $conn->prepare("
        SELECT a.*, d.first_name, d.last_name, d.specialization 
        FROM appointments a 
        JOIN users d ON a.doctor_id = d.user_id 
        WHERE a.appointment_id = ? AND a.patient_id = ?
    ");
    $stmt->bind_param("ii", $cancel_id, $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $cancel_appointment = $result->fetch_assoc();
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MediCare Patient Portal - Appointments</title>
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
        }
        
        .user-info img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid white;
        }
        
        /* Navigation */
        .nav-container {
            background-color: white;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            margin-bottom: 2rem;
        }
        
        .nav-menu {
            display: flex;
            list-style: none;
            padding: 0;
        }
        
        .nav-menu li {
            padding: 0;
        }
        
        .nav-menu a {
            display: block;
            padding: 1rem 1.5rem;
            color: var(--dark);
            text-decoration: none;
            font-weight: 500;
            border-bottom: 3px solid transparent;
            transition: all 0.3s ease;
        }
        
        .nav-menu a:hover, .nav-menu a.active {
            color: var(--primary);
            border-bottom: 3px solid var(--primary);
            background-color: #f8fafc;
        }
        
        .nav-menu a i {
            margin-right: 8px;
        }
        
        /* Main Content */
        .main-content {
            padding: 0 0 2rem 0;
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .page-title {
            font-size: 1.8rem;
            color: var(--dark);
            font-weight: 600;
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
        
        .status.canceled {
            background: #f3f4f6;
            color: var(--gray);
        }
        
        .status.no_show {
            background: #e5e7eb;
            color: var(--dark);
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
            border: none;
            cursor: pointer;
        }
        
        .action-btn:hover {
            background: var(--primary-dark);
        }
        
        .btn-secondary {
            background: var(--secondary);
        }
        
        .btn-secondary:hover {
            background: #0da271;
        }
        
        .btn-danger {
            background: var(--danger);
        }
        
        .btn-danger:hover {
            background: #dc2626;
        }
        
        .btn-warning {
            background: var(--warning);
        }
        
        .btn-warning:hover {
            background: #d97706;
        }
        
        /* Forms */
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--dark);
        }
        
        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        
        .form-select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
            background-color: white;
            appearance: none;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='m6 8 4 4 4-4'/%3e%3c/svg%3e");
            background-position: right 0.5rem center;
            background-repeat: no-repeat;
            background-size: 1.5em 1.5em;
        }
        
        .form-select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        
        .form-textarea {
            min-height: 120px;
            resize: vertical;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
        }
        
        /* Alert Messages */
        .alert {
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
        }
        
        .alert-success {
            background-color: #dcfce7;
            color: #166534;
            border-left: 4px solid #22c55e;
        }
        
        .alert-error {
            background-color: #fee2e2;
            color: #b91c1c;
            border-left: 4px solid #ef4444;
        }
        
        .alert i {
            margin-right: 0.75rem;
            font-size: 1.25rem;
        }
        
        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background-color: white;
            border-radius: 10px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
        }
        
        .modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--dark);
        }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--gray);
        }
        
        .modal-body {
            padding: 1.5rem;
        }
        
        .modal-footer {
            padding: 1rem 1.5rem;
            border-top: 1px solid #eee;
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
        }
        
        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 2rem;
            gap: 0.5rem;
        }
        
        .page-link {
            display: inline-block;
            padding: 0.5rem 1rem;
            border: 1px solid #ddd;
            border-radius: 5px;
            color: var(--dark);
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .page-link:hover {
            background-color: var(--light);
        }
        
        .page-link.active {
            background-color: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .nav-menu {
                flex-direction: column;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .header-content {
                flex-direction: column;
                gap: 1rem;
            }
            
            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            
            table {
                display: block;
                overflow-x: auto;
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
    
    <div class="nav-container">
        <div class="container">
            <ul class="nav-menu">
                <li><a href="patientportal.php"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="patientappointments.php" class="active"><i class="fas fa-calendar-check"></i> Appointments</a></li>
                <li><a href="patientmedicalrecords.php"><i class="fas fa-file-medical"></i> Medical Records</a></li>
                <li><a href="patientprescriptions.php"><i class="fas fa-prescription-bottle"></i> Prescriptions</a></li>
                <li><a href="patientbilling.php"><i class="fas fa-file-invoice-dollar"></i> Billing</a></li>
                <li><a href="patientprofile.php"><i class="fas fa-user"></i> Profile</a></li>
            </ul>
        </div>
    </div>
    
    <div class="container main-content">
        <div class="page-header">
            <h2 class="page-title">Appointment Management</h2>
            <button class="action-btn" id="newAppointmentBtn"><i class="fas fa-plus"></i> Schedule New Appointment</button>
        </div>
        
        <?php if (isset($success)): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?php echo $success; ?>
        </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo $error; ?>
        </div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-header">
                <h3>Your Appointments</h3>
                <div>
                    <select class="form-select" style="width: auto; display: inline-block;" id="statusFilter">
                        <option value="all">All Statuses</option>
                        <option value="scheduled">Scheduled</option>
                        <option value="completed">Completed</option>
                        <option value="canceled">Canceled</option>
                        <option value="no_show">No Show</option>
                    </select>
                </div>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Date & Time</th>
                        <th>Doctor</th>
                        <th>Specialization</th>
                        <th>Purpose</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($appointments) > 0): ?>
                        <?php foreach ($appointments as $appointment): 
                            $date = date('M j, Y', strtotime($appointment['appointment_date']));
                            $start_time = date('g:i A', strtotime($appointment['start_time']));
                            $end_time = date('g:i A', strtotime($appointment['end_time']));
                            $status_class = $appointment['status'];
                        ?>
                        <tr data-status="<?php echo $appointment['status']; ?>">
                            <td><?php echo $date . ' - ' . $start_time . ' to ' . $end_time; ?></td>
                            <td>Dr. <?php echo $appointment['first_name'] . ' ' . $appointment['last_name']; ?></td>
                            <td><?php echo $appointment['specialization']; ?></td>
                            <td><?php echo $appointment['purpose']; ?></td>
                            <td><span class="status <?php echo $status_class; ?>"><?php echo ucfirst(str_replace('_', ' ', $appointment['status'])); ?></span></td>
                            <td>
                                <?php if ($appointment['status'] == 'scheduled'): ?>
                                    <button class="action-btn btn-secondary edit-btn" style="padding: 0.25rem 0.5rem; font-size: 0.8rem;" data-id="<?php echo $appointment['appointment_id']; ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="action-btn btn-danger cancel-btn" style="padding: 0.25rem 0.5rem; font-size: 0.8rem;" data-id="<?php echo $appointment['appointment_id']; ?>">
                                        <i class="fas fa-times"></i>
                                    </button>
                                <?php else: ?>
                                    <button class="action-btn view-btn" style="padding: 0.25rem 0.5rem; font-size: 0.8rem;" data-id="<?php echo $appointment['appointment_id']; ?>">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 2rem;">
                                You don't have any appointments yet. <a href="#" id="scheduleFirstAppointment" style="color: var(--primary);">Schedule your first appointment</a>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <?php if (count($appointments) > 5): ?>
            <div class="pagination">
                <a href="#" class="page-link"><i class="fas fa-chevron-left"></i></a>
                <a href="#" class="page-link active">1</a>
                <a href="#" class="page-link">2</a>
                <a href="#" class="page-link">3</a>
                <a href="#" class="page-link"><i class="fas fa-chevron-right"></i></a>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- New Appointment Modal -->
    <div class="modal" id="appointmentModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Schedule New Appointment</h3>
                <button class="modal-close">&times;</button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Select Doctor</label>
                            <select class="form-select" name="doctor_id" required>
                                <option value="">Choose a doctor</option>
                                <?php foreach ($doctors as $doctor): ?>
                                    <option value="<?php echo $doctor['user_id']; ?>">
                                        Dr. <?php echo $doctor['first_name'] . ' ' . $doctor['last_name'] . ' (' . $doctor['specialization'] . ')'; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Appointment Date</label>
                            <input type="date" class="form-control" name="appointment_date" required min="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Start Time</label>
                            <input type="time" class="form-control" name="start_time" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">End Time</label>
                            <input type="time" class="form-control" name="end_time" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Purpose of Visit</label>
                        <input type="text" class="form-control" name="purpose" placeholder="e.g., Routine checkup, Consultation, Follow-up" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Notes (Optional)</label>
                        <textarea class="form-control form-textarea" name="notes" placeholder="Any specific concerns or details you'd like to share with the doctor"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="action-btn btn-secondary" id="cancelBtn">Cancel</button>
                    <button type="submit" name="schedule_appointment" class="action-btn">Schedule Appointment</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Appointment Modal -->
    <div class="modal" id="editAppointmentModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Edit Appointment</h3>
                <button class="modal-close">&times;</button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="appointment_id" id="edit_appointment_id">
                <div class="modal-body">
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Select Doctor</label>
                            <select class="form-select" name="doctor_id" id="edit_doctor_id" required>
                                <option value="">Choose a doctor</option>
                                <?php foreach ($doctors as $doctor): ?>
                                    <option value="<?php echo $doctor['user_id']; ?>">
                                        Dr. <?php echo $doctor['first_name'] . ' ' . $doctor['last_name'] . ' (' . $doctor['specialization'] . ')'; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Appointment Date</label>
                            <input type="date" class="form-control" name="appointment_date" id="edit_appointment_date" required min="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Start Time</label>
                            <input type="time" class="form-control" name="start_time" id="edit_start_time" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">End Time</label>
                            <input type="time" class="form-control" name="end_time" id="edit_end_time" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Purpose of Visit</label>
                        <input type="text" class="form-control" name="purpose" id="edit_purpose" placeholder="e.g., Routine checkup, Consultation, Follow-up" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Notes (Optional)</label>
                        <textarea class="form-control form-textarea" name="notes" id="edit_notes" placeholder="Any specific concerns or details you'd like to share with the doctor"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="action-btn btn-secondary" id="editCancelBtn">Cancel</button>
                    <button type="submit" name="update_appointment" class="action-btn">Update Appointment</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Cancel Appointment Modal -->
    <div class="modal" id="cancelAppointmentModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Cancel Appointment</h3>
                <button class="modal-close">&times;</button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="appointment_id" id="cancel_appointment_id">
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label">Appointment Details</label>
                        <div id="cancel_appointment_details" style="padding: 1rem; background-color: #f8f9fa; border-radius: 5px; margin-bottom: 1rem;">
                            <!-- Appointment details will be inserted here by JavaScript -->
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Reason for Cancellation</label>
                        <textarea class="form-control form-textarea" name="cancel_reason" placeholder="Please provide a reason for cancellation" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="action-btn btn-secondary" id="cancelAppointmentCancelBtn">Go Back</button>
                    <button type="submit" name="cancel_appointment" class="action-btn btn-danger">Confirm Cancellation</button>
                </div>
            </form>
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
        
        // Modal functionality
        const modal = document.getElementById('appointmentModal');
        const editModal = document.getElementById('editAppointmentModal');
        const cancelModal = document.getElementById('cancelAppointmentModal');
        const newAppointmentBtn = document.getElementById('newAppointmentBtn');
        const cancelBtn = document.getElementById('cancelBtn');
        const editCancelBtn = document.getElementById('editCancelBtn');
        const cancelAppointmentCancelBtn = document.getElementById('cancelAppointmentCancelBtn');
        const closeBtns = document.querySelectorAll('.modal-close');
        const scheduleFirstLink = document.getElementById('scheduleFirstAppointment');
        const statusFilter = document.getElementById('statusFilter');
        const editButtons = document.querySelectorAll('.edit-btn');
        const cancelButtons = document.querySelectorAll('.cancel-btn');
        
        // Open modal
        function openModal(modalElement) {
            modalElement.style.display = 'flex';
        }
        
        // Close modal
        function closeModal(modalElement) {
            modalElement.style.display = 'none';
        }
        
        // Event listeners for new appointment modal
        newAppointmentBtn.addEventListener('click', () => openModal(modal));
        
        if (scheduleFirstLink) {
            scheduleFirstLink.addEventListener('click', function(e) {
                e.preventDefault();
                openModal(modal);
            });
        }
        
        cancelBtn.addEventListener('click', () => closeModal(modal));
        
        // Event listeners for edit appointment modal
        editCancelBtn.addEventListener('click', () => closeModal(editModal));
        
        // Event listeners for cancel appointment modal
        cancelAppointmentCancelBtn.addEventListener('click', () => closeModal(cancelModal));
        
        // Close modals when clicking on close buttons
        closeBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                const modal = this.closest('.modal');
                closeModal(modal);
            });
        });
        
        // Close modals when clicking outside
        window.addEventListener('click', (e) => {
            if (e.target === modal) {
                closeModal(modal);
            }
            if (e.target === editModal) {
                closeModal(editModal);
            }
            if (e.target === cancelModal) {
                closeModal(cancelModal);
            }
        });
        
        // Status filter functionality
        if (statusFilter) {
            statusFilter.addEventListener('change', function() {
                const status = this.value;
                const rows = document.querySelectorAll('tbody tr');
                
                rows.forEach(row => {
                    if (status === 'all' || row.getAttribute('data-status') === status) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            });
        }
        
        // Edit button functionality
        editButtons.forEach(button => {
            button.addEventListener('click', function() {
                const appointmentId = this.getAttribute('data-id');
                // In a real application, you would fetch the appointment details via AJAX
                // For this example, we'll redirect to the same page with an edit parameter
                window.location.href = `patientappointments.php?edit_id=${appointmentId}`;
            });
        });
        
        // Cancel button functionality
        cancelButtons.forEach(button => {
            button.addEventListener('click', function() {
                const appointmentId = this.getAttribute('data-id');
                // In a real application, you would fetch the appointment details via AJAX
                // For this example, we'll redirect to the same page with a cancel parameter
                window.location.href = `patientappointments.php?cancel_id=${appointmentId}`;
            });
        });
        
        // Auto-open edit modal if edit_id is set
        <?php if (isset($_GET['edit_id']) && $edit_appointment): ?>
            // Populate the edit form with the appointment data
            document.getElementById('edit_appointment_id').value = '<?php echo $edit_appointment['appointment_id']; ?>';
            document.getElementById('edit_doctor_id').value = '<?php echo $edit_appointment['doctor_id']; ?>';
            document.getElementById('edit_appointment_date').value = '<?php echo $edit_appointment['appointment_date']; ?>';
            document.getElementById('edit_start_time').value = '<?php echo $edit_appointment['start_time']; ?>';
            document.getElementById('edit_end_time').value = '<?php echo $edit_appointment['end_time']; ?>';
            document.getElementById('edit_purpose').value = '<?php echo $edit_appointment['purpose']; ?>';
            document.getElementById('edit_notes').value = '<?php echo $edit_appointment['notes']; ?>';
            
            // Open the edit modal
            openModal(editModal);
        <?php endif; ?>
        
        // Auto-open cancel modal if cancel_id is set
        <?php if (isset($_GET['cancel_id']) && $cancel_appointment): ?>
            // Populate the cancel form with the appointment data
            document.getElementById('cancel_appointment_id').value = '<?php echo $cancel_appointment['appointment_id']; ?>';
            
            // Format the appointment details for display
            const appointmentDate = new Date('<?php echo $cancel_appointment['appointment_date']; ?>');
            const formattedDate = appointmentDate.toLocaleDateString('en-US', { 
                year: 'numeric', 
                month: 'short', 
                day: 'numeric' 
            });
            
            const startTime = new Date('<?php echo $cancel_appointment['appointment_date']; ?> <?php echo $cancel_appointment['start_time']; ?>');
            const formattedStartTime = startTime.toLocaleTimeString('en-US', { 
                hour: '2-digit', 
                minute: '2-digit' 
            });
            
            const endTime = new Date('<?php echo $cancel_appointment['appointment_date']; ?> <?php echo $cancel_appointment['end_time']; ?>');
            const formattedEndTime = endTime.toLocaleTimeString('en-US', { 
                hour: '2-digit', 
                minute: '2-digit' 
            });
            
            const detailsHtml = `
                <strong>Date:</strong> ${formattedDate}<br>
                <strong>Time:</strong> ${formattedStartTime} - ${formattedEndTime}<br>
                <strong>Doctor:</strong> Dr. <?php echo $cancel_appointment['first_name'] . ' ' . $cancel_appointment['last_name']; ?><br>
                <strong>Purpose:</strong> <?php echo $cancel_appointment['purpose']; ?>
            `;
            
            document.getElementById('cancel_appointment_details').innerHTML = detailsHtml;
            
            // Open the cancel modal
            openModal(cancelModal);
        <?php endif; ?>
    </script>
</body>
</html>