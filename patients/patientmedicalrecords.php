<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

// Check if user is logged in and is a patient
requirePatient();

// Get patient ID from session
$patient_id = $_SESSION['user_id'];

// Fetch patient details
$patient_stmt = $conn->prepare("SELECT first_name, last_name, email, phone FROM patients WHERE patient_id = ?");
$patient_stmt->bind_param("i", $patient_id);
$patient_stmt->execute();
$patient_result = $patient_stmt->get_result();
$patient = $patient_result->fetch_assoc();
$patient_name = $patient['first_name'] . ' ' . $patient['last_name'];

// Fetch medical records from database with pagination
$records = [];

// Pagination settings
$records_per_page = 10; // adjust as desired
$page = isset($_GET['page']) && is_numeric($_GET['page']) && (int)$_GET['page'] > 0 ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $records_per_page;

// Get total count for this patient
$count_stmt = $conn->prepare("SELECT COUNT(*) as total FROM medical_records WHERE patient_id = ?");
$count_stmt->bind_param("i", $patient_id);
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_records = 0;
if ($count_row = $count_result->fetch_assoc()) {
    $total_records = (int)$count_row['total'];
}
$count_stmt->close();

$total_pages = ($total_records > 0) ? (int)ceil($total_records / $records_per_page) : 1;

// Ensure current page isn't out of range
if ($page > $total_pages) {
    $page = $total_pages;
    $offset = ($page - 1) * $records_per_page;
}

// Use prepared statement with LIMIT and OFFSET (bind as integers)
$stmt = $conn->prepare(
    "SELECT mr.*, d.first_name, d.last_name, d.specialization 
    FROM medical_records mr 
    INNER JOIN users d ON mr.doctor_id = d.user_id 
    WHERE mr.patient_id = ? 
    ORDER BY mr.record_date DESC 
    LIMIT ? OFFSET ?"
);
// bind_param requires types; 'i' for patient_id, 'i' for limit, 'i' for offset
$stmt->bind_param("iii", $patient_id, $records_per_page, $offset);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $records[] = $row;
    }
}
$stmt->close();

// Handle record download request
if (isset($_GET['download']) && is_numeric($_GET['download'])) {
    $record_id = $_GET['download'];
    
    // Verify the record belongs to the current patient
    $stmt = $conn->prepare("SELECT * FROM medical_records WHERE record_id = ? AND patient_id = ?");
    $stmt->bind_param("ii", $record_id, $patient_id);
    $stmt->execute();
    $record_result = $stmt->get_result();
    
    if ($record_result->num_rows === 1) {
        $record = $record_result->fetch_assoc();
        
        // Generate PDF content (simplified version)
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="medical_record_'.$record_id.'.pdf"');
        
        // In a real implementation, you would use a PDF library like TCPDF or Dompdf
        // This is a simplified example
        echo "Medical Record #" . $record_id . "\n";
        echo "Date: " . $record['record_date'] . "\n";
        echo "Type: " . $record['record_type'] . "\n";
        echo "Title: " . $record['title'] . "\n";
        echo "Description: " . $record['description'] . "\n";
        
        exit();
    }
}

// Handle AJAX request for record details
if (isset($_GET['get_record']) && is_numeric($_GET['get_record'])) {
    $record_id = $_GET['get_record'];
    
    // Verify the record belongs to the current patient
    $stmt = $conn->prepare("
        SELECT mr.*, d.first_name, d.last_name, d.specialization 
        FROM medical_records mr 
        INNER JOIN users d ON mr.doctor_id = d.user_id 
        WHERE mr.record_id = ? AND mr.patient_id = ?
    ");
    $stmt->bind_param("ii", $record_id, $patient_id);
    $stmt->execute();
    $record_result = $stmt->get_result();
    
    if ($record_result->num_rows === 1) {
        $record = $record_result->fetch_assoc();
        
        // Format the response as HTML
        echo '<div class="record-details">';
        echo '<div class="detail-row">';
        echo '<div class="detail-label">Record ID:</div>';
        echo '<div class="detail-value">#' . $record['record_id'] . '</div>';
        echo '</div>';
        
        echo '<div class="detail-row">';
        echo '<div class="detail-label">Date:</div>';
        echo '<div class="detail-value">' . date('F j, Y', strtotime($record['record_date'])) . '</div>';
        echo '</div>';
        
        echo '<div class="detail-row">';
        echo '<div class="detail-label">Type:</div>';
        echo '<div class="detail-value">' . ucfirst(str_replace('_', ' ', $record['record_type'])) . '</div>';
        echo '</div>';
        
        echo '<div class="detail-row">';
        echo '<div class="detail-label">Title:</div>';
        echo '<div class="detail-value">' . htmlspecialchars($record['title']) . '</div>';
        echo '</div>';
        
        echo '<div class="detail-row">';
        echo '<div class="detail-label">Doctor:</div>';
        echo '<div class="detail-value">Dr. ' . $record['first_name'] . ' ' . $record['last_name'] . '</div>';
        echo '</div>';
        
        echo '<div class="detail-row">';
        echo '<div class="detail-label">Specialization:</div>';
        echo '<div class="detail-value">' . htmlspecialchars($record['specialization']) . '</div>';
        echo '</div>';
        
        if (!empty($record['description'])) {
            echo '<div class="detail-row full-width">';
            echo '<div class="detail-label">Description:</div>';
            echo '<div class="detail-value">' . nl2br(htmlspecialchars($record['description'])) . '</div>';
            echo '</div>';
        }
        
        if (!empty($record['notes'])) {
            echo '<div class="detail-row full-width">';
            echo '<div class="detail-label">Doctor\'s Notes:</div>';
            echo '<div class="detail-value">' . nl2br(htmlspecialchars($record['notes'])) . '</div>';
            echo '</div>';
        }
        
        echo '</div>';
        
        exit();
    } else {
        echo '<div class="error-message">Record not found or access denied.</div>';
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MediCare Patient Portal - Medical Records</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <style>
        /* Your existing CSS styles here */
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
            max-width: 700px;
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
        
        /* Tabs */
        .tabs {
            display: flex;
            border-bottom: 1px solid #ddd;
            margin-bottom: 1.5rem;
        }
        
        .tab {
            padding: 0.75rem 1.5rem;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            transition: all 0.3s ease;
        }
        
        .tab.active {
            border-bottom: 3px solid var(--primary);
            color: var(--primary);
            font-weight: 500;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        /* Profile styles */
        .profile-header {
            display: flex;
            align-items: center;
            gap: 2rem;
            margin-bottom: 2rem;
        }
        
        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid white;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .profile-info h2 {
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
        }
        
        .profile-info p {
            color: var(--gray);
        }
        
        .info-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            margin-bottom: 1.5rem;
        }
        
        .info-card h3 {
            font-size: 1.2rem;
            margin-bottom: 1rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid #eee;
            color: var(--dark);
        }
        
        .info-item {
            display: flex;
            margin-bottom: 1rem;
        }
        
        .info-label {
            width: 150px;
            font-weight: 500;
            color: var(--dark);
        }
        
        .info-value {
            flex: 1;
            color: var(--gray);
        }
        
        /* Record Details Styles */
        .record-details {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        
        .detail-row {
            display: flex;
            padding: 0.75rem 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .detail-row.full-width {
            flex-direction: column;
        }
        
        .detail-label {
            width: 150px;
            font-weight: 600;
            color: var(--dark);
        }
        
        .detail-value {
            flex: 1;
            color: var(--gray);
        }
        
        .detail-row.full-width .detail-value {
            margin-top: 0.5rem;
            line-height: 1.6;
        }
        
        .error-message {
            background-color: #fee2e2;
            color: #b91c1c;
            padding: 1rem;
            border-radius: 5px;
            text-align: center;
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
            
            .profile-header {
                flex-direction: column;
                text-align: center;
            }
            
            .info-item {
                flex-direction: column;
            }
            
            .info-label {
                width: 100%;
                margin-bottom: 0.25rem;
            }
            
            .detail-row {
                flex-direction: column;
            }
            
            .detail-label {
                width: 100%;
                margin-bottom: 0.25rem;
            }
        }
        
        .no-records {
            text-align: center;
            padding: 2rem;
            color: var(--gray);
        }
        
        .no-records i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #ddd;
        }
        
        /* Button Group Styles */
        .btn-group {
            display: flex;
            gap: 0.5rem;
        }
        
        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.8rem;
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
                        <?php $avatar_url = isset($patient_id) ? 'https://randomuser.me/api/portraits/lego/' . ($patient_id % 10) . '.jpg' : 'https://randomuser.me/api/portraits/lego/0.jpg'; ?>
                        <img src="<?php echo $avatar_url; ?>" alt="User Profile" onclick="toggleDropdown()">
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
                <li><a href="patientappointments.php"><i class="fas fa-calendar-check"></i> Appointments</a></li>
                <li><a href="patientmedicalrecords.php" class="active"><i class="fas fa-file-medical"></i> Medical Records</a></li>
                <li><a href="patientprescriptions.php"><i class="fas fa-prescription-bottle"></i> Prescriptions</a></li>
                <li><a href="patientbilling.php"><i class="fas fa-file-invoice-dollar"></i> Billing</a></li>
                <li><a href="patientprofile.php"><i class="fas fa-user"></i> Profile</a></li>
            </ul>
        </div>
    </div>
    
    <div class="container main-content">
        <div class="page-header">
            <h2 class="page-title">Medical Records</h2>
            <button class="action-btn" onclick="printAllRecords()"><i class="fas fa-print"></i> Print All Records</button>
        </div>
        
        <div class="tabs">
            <div class="tab active" data-tab="all">All Records</div>
            <div class="tab" data-tab="lab">Lab Results</div>
            <div class="tab" data-tab="diagnosis">Diagnoses</div>
            <div class="tab" data-tab="prescription">Prescriptions</div>
        </div>
        
        <div class="tab-content active" id="all-tab">
            <div class="card">
                <div class="card-header">
                    <h3>Your Medical History</h3>
                    <div>
                        <select class="form-select" id="sortSelect" style="width: auto; display: inline-block;">
                            <option value="newest">Sort by Date (Newest First)</option>
                            <option value="oldest">Sort by Date (Oldest First)</option>
                            <option value="type">Sort by Type</option>
                        </select>
                    </div>
                </div>
                
                <?php if (count($records) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Record Type</th>
                            <th>Doctor</th>
                            <th>Details</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($records as $record): 
                            $doctor_name = $record['first_name'] . ' ' . $record['last_name'];
                            $record_date = date('M j, Y', strtotime($record['record_date']));
                        ?>
                        <tr>
                            <td><?php echo $record_date; ?></td>
                            <td><?php echo ucfirst(str_replace('_', ' ', $record['record_type'])); ?></td>
                            <td>Dr. <?php echo $doctor_name; ?></td>
                            <td><?php echo $record['title']; ?></td>
                            <td>
                                <div class="btn-group">
                                    <button class="action-btn btn-sm view-record" data-id="<?php echo $record['record_id']; ?>">
                                        <i class="fas fa-eye"></i> View
                                    </button>
                                    <button class="action-btn btn-secondary btn-sm print-record" data-id="<?php echo $record['record_id']; ?>">
                                        <i class="fas fa-print"></i> Print
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a class="page-link" href="?page=<?php echo $page - 1; ?>"><i class="fas fa-chevron-left"></i></a>
                    <?php else: ?>
                        <span class="page-link" aria-disabled="true"><i class="fas fa-chevron-left" style="opacity:0.4"></i></span>
                    <?php endif; ?>

                    <?php
                    // Display a window of pages around current page
                    $visible_pages = 5;
                    $start_page = max(1, $page - floor($visible_pages / 2));
                    $end_page = min($total_pages, $start_page + $visible_pages - 1);
                    if ($end_page - $start_page + 1 < $visible_pages) {
                        $start_page = max(1, $end_page - $visible_pages + 1);
                    }

                    for ($p = $start_page; $p <= $end_page; $p++):
                    ?>
                        <?php if ($p == $page): ?>
                            <span class="page-link active"><?php echo $p; ?></span>
                        <?php else: ?>
                            <a class="page-link" href="?page=<?php echo $p; ?>"><?php echo $p; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <?php if ($page < $total_pages): ?>
                        <a class="page-link" href="?page=<?php echo $page + 1; ?>"><i class="fas fa-chevron-right"></i></a>
                    <?php else: ?>
                        <span class="page-link" aria-disabled="true"><i class="fas fa-chevron-right" style="opacity:0.4"></i></span>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <div class="no-records">
                    <i class="fas fa-file-medical-alt"></i>
                    <h3>No Medical Records Found</h3>
                    <p>You don't have any medical records yet.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="tab-content" id="lab-tab">
            <div class="card">
                <div class="card-header">
                    <h3>Laboratory Results</h3>
                </div>
                
                <?php 
                $lab_records = array_filter($records, function($record) {
                    return $record['record_type'] === 'lab_result';
                });
                
                if (count($lab_records) > 0): 
                ?>
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Test Type</th>
                            <th>Ordering Doctor</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lab_records as $record): 
                            $doctor_name = $record['first_name'] . ' ' . $record['last_name'];
                            $record_date = date('M j, Y', strtotime($record['record_date']));
                        ?>
                        <tr>
                            <td><?php echo $record_date; ?></td>
                            <td><?php echo $record['title']; ?></td>
                            <td>Dr. <?php echo $doctor_name; ?></td>
                            <td><span class="status completed">Completed</span></td>
                            <td>
                                <div class="btn-group">
                                    <button class="action-btn btn-sm view-record" data-id="<?php echo $record['record_id']; ?>">
                                        <i class="fas fa-eye"></i> View
                                    </button>
                                    <button class="action-btn btn-secondary btn-sm print-record" data-id="<?php echo $record['record_id']; ?>">
                                        <i class="fas fa-print"></i> Print
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="no-records">
                    <i class="fas fa-vial"></i>
                    <h3>No Lab Results Found</h3>
                    <p>You don't have any lab results yet.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="tab-content" id="diagnosis-tab">
            <div class="card">
                <div class="card-header">
                    <h3>Diagnoses</h3>
                </div>

                <?php
                $diagnosis_records = array_filter($records, function($record) {
                    return $record['record_type'] === 'diagnosis';
                });

                if (count($diagnosis_records) > 0):
                ?>
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>ICD Code</th>
                            <th>Diagnosis</th>
                            <th>Doctor</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($diagnosis_records as $record):
                            $doctor_name = $record['first_name'] . ' ' . $record['last_name'];
                            $record_date = date('M j, Y', strtotime($record['record_date']));
                        ?>
                        <tr>
                            <td><?php echo $record_date; ?></td>
                            <td><?php echo htmlspecialchars($record['diagnosis_code'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($record['title']); ?></td>
                            <td>Dr. <?php echo $doctor_name; ?></td>
                            <td>
                                <div class="btn-group">
                                    <button class="action-btn btn-sm view-record" data-id="<?php echo $record['record_id']; ?>">
                                        <i class="fas fa-eye"></i> View
                                    </button>
                                    <button class="action-btn btn-secondary btn-sm print-record" data-id="<?php echo $record['record_id']; ?>">
                                        <i class="fas fa-print"></i> Print
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="no-records">
                    <i class="fas fa-notes-medical"></i>
                    <h3>No Diagnoses Found</h3>
                    <p>You don't have any recorded diagnoses yet.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="tab-content" id="prescription-tab">
            <div class="card">
                <div class="card-header">
                    <h3>Your Prescriptions</h3>
                </div>

                <?php
                // Fetch prescriptions for the current patient (reuse logic from patientprescriptions.php)
                $prescriptions = [];
                $prescription_items = [];
                try {
                    $pstmt = $conn->prepare("SELECT p.*, d.first_name, d.last_name FROM prescriptions p LEFT JOIN users d ON p.doctor_id = d.user_id WHERE p.patient_id = ? ORDER BY p.prescription_date DESC");
                    $pstmt->bind_param("i", $patient_id);
                    $pstmt->execute();
                    $pres_res = $pstmt->get_result();
                    while ($row = $pres_res->fetch_assoc()) {
                        $prescriptions[$row['prescription_id']] = $row;
                    }

                    if (!empty($prescriptions)) {
                        $prescription_ids = array_keys($prescriptions);
                        $placeholders = implode(',', array_fill(0, count($prescription_ids), '?'));
                        $stmt_items = $conn->prepare("SELECT * FROM prescription_items WHERE prescription_id IN ($placeholders)");
                        $types = str_repeat('i', count($prescription_ids));
                        $stmt_items->bind_param($types, ...$prescription_ids);
                        $stmt_items->execute();
                        $items_res = $stmt_items->get_result();
                        while ($it = $items_res->fetch_assoc()) {
                            $prescription_items[$it['prescription_id']][] = $it;
                        }
                    }
                } catch (Exception $e) {
                    $pres_error = "Error fetching prescriptions: " . $e->getMessage();
                }

                if (!empty($pres_error)) {
                    echo '<div class="error-message">' . htmlspecialchars($pres_error) . '</div>';
                }

                if (!empty($prescriptions)):
                ?>
                <?php foreach ($prescriptions as $prescription_id => $prescription): ?>
                    <div class="prescription-item" style="margin-bottom:1rem; padding:0.75rem; border:1px solid #eee; border-radius:8px; display:flex; flex-direction:column; gap:0.5rem;">
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <div>
                                <strong>Prescription by Dr. <?php echo htmlspecialchars($prescription['first_name'] . ' ' . $prescription['last_name']); ?></strong>
                                <div style="color:var(--gray); font-size:0.9rem;"><?php echo date('M j, Y', strtotime($prescription['prescription_date'])); ?></div>
                            </div>
                            <div style="text-align:right; color:var(--gray);">Status: <?php echo ucfirst($prescription['status']); ?></div>
                        </div>
                        <?php if (!empty($prescription['instructions'])): ?>
                            <div style="color:var(--gray);">Instructions: <?php echo htmlspecialchars($prescription['instructions']); ?></div>
                        <?php endif; ?>
                        <?php if (isset($prescription_items[$prescription_id])): ?>
                            <ul style="margin-left:1rem; color:var(--dark);">
                                <?php foreach ($prescription_items[$prescription_id] as $item): ?>
                                    <li><?php echo htmlspecialchars($item['medication_name']) . ' - ' . htmlspecialchars($item['dosage']); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <div style="color:var(--gray);">No medications listed for this prescription.</div>
                        <?php endif; ?>
                        <div style="display:flex; gap:0.5rem;">
                            <button class="action-btn btn-sm view-prescription" data-id="<?php echo $prescription_id; ?>">Details</button>
                            <a href="patientprescriptions.php" class="action-btn btn-secondary btn-sm">Manage</a>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-records">
                        <i class="fas fa-file-prescription"></i>
                        <h3>No Prescriptions Found</h3>
                        <p>You don't have any prescriptions yet.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Record Detail Modal -->
    <div class="modal" id="recordModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Medical Record Details</h3>
                <button class="modal-close">&times;</button>
            </div>
            <div class="modal-body" id="modalBody">
                <!-- Content will be loaded via AJAX -->
            </div>
            <div class="modal-footer">
                <button class="action-btn btn-secondary" id="closeModalBtn">Close</button>
                <button class="action-btn" id="printModalBtn"><i class="fas fa-print"></i> Print</button>
            </div>
        </div>
    </div>

    <script>
        // Tab functionality
        const tabs = document.querySelectorAll('.tab');
        const tabContents = document.querySelectorAll('.tab-content');
        
        tabs.forEach(tab => {
            tab.addEventListener('click', () => {
                const tabId = tab.getAttribute('data-tab');
                
                // Update active tab
                tabs.forEach(t => t.classList.remove('active'));
                tab.classList.add('active');
                
                // Show active tab content
                tabContents.forEach(content => content.classList.remove('active'));
                document.getElementById(`${tabId}-tab`).classList.add('active');
            });
        });
        
    // Modal functionality
        const modal = document.getElementById('recordModal');
        const viewButtons = document.querySelectorAll('.view-record');
        const closeBtn = document.querySelector('.modal-close');
        const closeModalBtn = document.getElementById('closeModalBtn');
        const modalBody = document.getElementById('modalBody');
    const printModalBtn = document.getElementById('printModalBtn');
    const printRecordButtons = document.querySelectorAll('.print-record');
        
        viewButtons.forEach(button => {
            button.addEventListener('click', () => {
                const recordId = button.getAttribute('data-id');
                loadRecordDetails(recordId);
            });
        });

        // Wire up print buttons for each record in the table
        printRecordButtons.forEach(btn => {
            btn.addEventListener('click', (e) => {
                const id = btn.getAttribute('data-id');
                printRecord(id);
            });
        });

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
        
        function loadRecordDetails(recordId) {
            // Show loading state
            modalBody.innerHTML = '<div style="text-align: center; padding: 2rem;"><i class="fas fa-spinner fa-spin"></i> Loading record details...</div>';
            modal.style.display = 'flex';
            
            // AJAX request to fetch record details
            fetch(`?get_record=${recordId}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.text();
                })
                .then(data => {
                    modalBody.innerHTML = data;
                    // store the current record id on the modal for printing
                    modal.setAttribute('data-current-record', recordId);
                })
                .catch(error => {
                    modalBody.innerHTML = `<div class="error-message">Error loading record details: ${error}</div>`;
                });
        }
        
        const closeModal = () => {
            modal.style.display = 'none';
        };
        
        closeBtn.addEventListener('click', closeModal);
        closeModalBtn.addEventListener('click', closeModal);
        
        // Close modal when clicking outside
        window.addEventListener('click', (e) => {
            if (e.target === modal) {
                closeModal();
            }
        });
        
        // Print an individual record by opening a new window with the record's details and calling print()
        function printRecord(recordId) {
            // Fetch the record details via the same endpoint used for the modal
            fetch(`?get_record=${recordId}`)
                .then(resp => resp.text())
                .then(html => {
                    const printWindow = window.open('', '_blank', 'width=800,height=600');
                    printWindow.document.write(`<!doctype html><html><head><title>Medical Record #${recordId}</title>`);
                    // modern print styling
                    printWindow.document.write(`
                        <style>
                        @import url('https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap');
                        :root{--primary:#2563eb;--muted:#6b7280}
                        html,body{background:#fff;margin:0;padding:0;font-family:Roboto, Arial, Helvetica, sans-serif;color:#111}
                        /* Page settings */
                        @page { size: auto; margin: 12mm; }
                        *{box-sizing:border-box}
                        .print-container{max-width:900px;margin:0 auto;padding:0}
                        .print-header{display:flex;align-items:center;justify-content:space-between;padding:18px;border-radius:8px;background:linear-gradient(90deg,var(--primary),#1d4ed8);color:#fff}
                        .brand{display:flex;align-items:center;gap:14px}
                        .brand .logo{width:56px;height:56px;border-radius:10px;background:rgba(255,255,255,0.15);display:flex;align-items:center;justify-content:center;font-size:22px}
                        .brand h1{font-size:18px;margin:0;font-weight:700}
                        .meta{text-align:right}
                        .meta .patient{font-weight:600}
                        .card{background:#fff;border-radius:10px;padding:18px;margin-top:12px;box-shadow:0 6px 18px rgba(16,24,40,0.06);border:1px solid #f0f3ff}
                        .row{display:flex;gap:12px;flex-wrap:wrap}
                        .detail-label{width:170px;font-weight:700;color:var(--muted)}
                        .detail-value{flex:1;color:#222}
                        .detail-row{padding:10px 0;border-bottom:1px dashed #f1f5f9}
                        .detail-row:last-child{border-bottom:none}
                        h2.section-title{margin:0 0 12px 0;font-size:16px;color:#0f172a}
                        .footer-note{margin-top:14px;color:var(--muted);font-size:12px}
                        /* table styles if record contains tables */
                        table{width:100%;border-collapse:collapse;margin-top:12px}
                        th,td{padding:10px;border:1px solid #eef2ff;text-align:left}
                        th{background:#f8fafc;color:var(--muted);font-weight:600}
                        /* Prevent small elements from creating an extra blank page */
                        .print-header, .card, .detail-row, table, tr, th, td { break-inside: avoid; page-break-inside: avoid; -webkit-column-break-inside: avoid; }
                        /* Allow large tables to break across pages gracefully */
                        table { page-break-inside: auto }
                        tr { page-break-inside: avoid; page-break-after: auto }
                        @media print{*{ -webkit-print-color-adjust:exact; } .print-header{box-shadow:none} body{margin:0} }
                        </style>
                    `);
                    printWindow.document.write('</head><body>');
                    printWindow.document.write('<div class="print-container">');
                    printWindow.document.write('<div class="print-header">');
                    printWindow.document.write('<div class="brand"><div class="logo">📋</div><div><h1>MediCare — Medical Record</h1><div style="font-size:13px;opacity:0.92">Record #' + recordId + '</div></div></div>');
                    printWindow.document.write('<div class="meta"><div class="patient">' + <?php echo json_encode(htmlspecialchars($patient_name)); ?> + '</div><div style="font-size:13px;margin-top:6px">' + new Date().toLocaleDateString() + '</div></div>');
                    printWindow.document.write('</div>');
                    printWindow.document.write('<div class="card">');
                    printWindow.document.write(html);
                    printWindow.document.write('</div>');
                    printWindow.document.write('<div class="footer-note">Printed from MediCare Patient Portal • ' + new Date().toLocaleString() + '</div>');
                    printWindow.document.write('</div>');
                    printWindow.document.write('</body></html>');
                    printWindow.document.close();
                    // Wait for content to load then print
                    printWindow.onload = function() {
                        printWindow.focus();
                        printWindow.print();
                        // Optionally close after printing
                        // printWindow.close();
                    };
                })
                .catch(err => alert('Unable to load record for printing: ' + err));
        }

        // Print all records by creating a printable view of the current table
        function printAllRecords() {
            // You might want to confirm with the user
            const table = document.querySelector('#all-tab table');
            if (!table) {
                alert('No records to print.');
                return;
            }

            const clone = table.cloneNode(true);
            // Remove action column (last column) from the clone for printing
            Array.from(clone.querySelectorAll('tr')).forEach(tr => {
                const cells = tr.children;
                if (cells.length) {
                    tr.removeChild(cells[cells.length - 1]);
                }
            });

            const printWindow = window.open('', '_blank', 'width=1000,height=800');
            printWindow.document.write('<!doctype html><html><head><title>All Medical Records</title>');
            printWindow.document.write(`
                <style>
                @import url('https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap');
                :root{--primary:#2563eb;--muted:#6b7280}
                html,body{background:#fff;margin:0;padding:0;font-family:Roboto, Arial, Helvetica, sans-serif;color:#111}
                @page { size: auto; margin: 12mm; }
                .print-wrap{max-width:1100px;margin:0 auto;padding:10px}
                .print-header{display:flex;justify-content:space-between;align-items:center;padding:14px;border-radius:8px;background:linear-gradient(90deg,var(--primary),#1d4ed8);color:#fff}
                .print-title{font-size:18px;font-weight:700}
                .print-sub{font-size:13px;opacity:0.95}
                table{width:100%;border-collapse:collapse;margin-top:14px}
                th,td{padding:12px;border-bottom:1px solid #eef2ff;text-align:left}
                th{background:#fbfdff;color:var(--muted);font-weight:600}
                tr:hover td{background:#fbfbff}
                /* Prevent tiny elements from forcing extra pages */
                .print-header, .print-title, .print-sub, table, tr, th, td { break-inside: avoid; page-break-inside: avoid; -webkit-column-break-inside: avoid; }
                table { page-break-inside: auto }
                tr { page-break-inside: avoid; page-break-after: auto }
                @media print{*{ -webkit-print-color-adjust:exact; }}
                </style>
            `);
            printWindow.document.write('</head><body>');
            printWindow.document.write('<div class="print-wrap">');
            printWindow.document.write('<div class="print-header"><div><div class="print-title">Medical Records</div><div class="print-sub"><?php echo htmlspecialchars($patient_name); ?></div></div><div class="print-sub">Printed: ' + new Date().toLocaleString() + '</div></div>');
            printWindow.document.write(clone.outerHTML);
            printWindow.document.write('<div style="margin-top:18px;color:var(--muted);font-size:13px">MediCare Patient Portal — Confidential</div>');
            printWindow.document.write('</div>');
            printWindow.document.write('</body></html>');
            printWindow.document.close();
            printWindow.onload = function() {
                printWindow.focus();
                printWindow.print();
            };
        }

        // Modal print button: print the currently loaded record or modal content
        if (printModalBtn) {
            printModalBtn.addEventListener('click', function() {
                const current = modal.getAttribute('data-current-record');
                if (current) {
                    printRecord(current);
                } else {
                    // Print modal HTML content directly
                    const printWindow = window.open('', '_blank', 'width=800,height=600');
                    printWindow.document.write('<!doctype html><html><head><title>Print Preview</title>');
                    printWindow.document.write(`
                        <style>
                        @import url('https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap');
                        :root{--primary:#2563eb;--muted:#6b7280}
                        html,body{background:#fff;margin:0;padding:0;font-family:Roboto, Arial, Helvetica, sans-serif;color:#111}
                        @page { size: auto; margin: 12mm; }
                        .print-container{max-width:900px;margin:0 auto;padding:10px}
                        .print-header{display:flex;align-items:center;justify-content:space-between;padding:14px;border-radius:8px;background:linear-gradient(90deg,var(--primary),#1d4ed8);color:#fff}
                        .brand{display:flex;align-items:center;gap:14px}
                        .brand .logo{width:48px;height:48px;border-radius:8px;background:rgba(255,255,255,0.12);display:flex;align-items:center;justify-content:center;font-size:20px}
                        .brand h1{font-size:16px;margin:0;font-weight:700}
                        .meta{text-align:right}
                        .footer-note{margin-top:12px;color:var(--muted);font-size:12px}
                        .print-header, .card, .detail-row, table, tr, th, td { break-inside: avoid; page-break-inside: avoid; -webkit-column-break-inside: avoid; }
                        @media print{*{ -webkit-print-color-adjust:exact; } body{margin:0} }
                        </style>
                    `);
                    printWindow.document.write('</head><body>');
                    printWindow.document.write('<div class="print-container">');
                    printWindow.document.write('<div class="print-header">');
                    printWindow.document.write('<div class="brand"><div class="logo">📋</div><div><h1>MediCare — Record</h1></div></div>');
                    printWindow.document.write('<div class="meta">' + new Date().toLocaleString() + '</div>');
                    printWindow.document.write('</div>');
                    printWindow.document.write('<div class="card">' + modalBody.innerHTML + '</div>');
                    printWindow.document.write('<div class="footer-note">Printed from MediCare Patient Portal</div>');
                    printWindow.document.write('</div>');
                    printWindow.document.write('</body></html>');
                    printWindow.document.close();
                    printWindow.onload = function() {
                        printWindow.focus();
                        printWindow.print();
                    };
                }
            });
        }
        
        // Sort functionality
        document.getElementById('sortSelect').addEventListener('change', function() {
            // This would typically make an AJAX request to sort the records
            alert('Sorting by: ' + this.value);
            // In a real implementation, you would:
            // 1. Make an AJAX request to the server with the sort parameter
            // 2. Update the table with the sorted results
        });
    </script>
</body>
</html>