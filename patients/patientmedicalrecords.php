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

// Fetch medical records from database
$records = [];
$stmt = $conn->prepare("
    SELECT mr.*, d.first_name, d.last_name, d.specialization 
    FROM medical_records mr 
    INNER JOIN users d ON mr.doctor_id = d.user_id 
    WHERE mr.patient_id = ? 
    ORDER BY mr.record_date DESC
");
$stmt->bind_param("i", $patient_id);
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
            <button class="action-btn" onclick="downloadAllRecords()"><i class="fas fa-download"></i> Download All Records</button>
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
                                <button class="action-btn view-record" data-id="<?php echo $record['record_id']; ?>" style="padding: 0.25rem 0.5rem; font-size: 0.8rem;">
                                    <i class="fas fa-eye"></i> View
                                </button>
                                <a href="?download=<?php echo $record['record_id']; ?>" class="action-btn btn-secondary" style="padding: 0.25rem 0.5rem; font-size: 0.8rem;">
                                    <i class="fas fa-download"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <div class="pagination">
                    <a href="#" class="page-link"><i class="fas fa-chevron-left"></i></a>
                    <a href="#" class="page-link active">1</a>
                    <a href="#" class="page-link">2</a>
                    <a href="#" class="page-link">3</a>
                    <a href="#" class="page-link"><i class="fas fa-chevron-right"></i></a>
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
                                <button class="action-btn view-record" data-id="<?php echo $record['record_id']; ?>" style="padding: 0.25rem 0.5rem; font-size: 0.8rem;">
                                    <i class="fas fa-eye"></i> View
                                </button>
                                <a href="?download=<?php echo $record['record_id']; ?>" class="action-btn btn-secondary" style="padding: 0.25rem 0.5rem; font-size: 0.8rem;">
                                    <i class="fas fa-download"></i>
                                </a>
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
        
        <!-- Additional tabs for diagnosis and prescription would follow the same pattern -->
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
                <button class="action-btn" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
                <a href="#" id="downloadModalBtn" class="action-btn btn-secondary"><i class="fas fa-download"></i> Download PDF</a>
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
        const downloadModalBtn = document.getElementById('downloadModalBtn');
        
        viewButtons.forEach(button => {
            button.addEventListener('click', () => {
                const recordId = button.getAttribute('data-id');
                loadRecordDetails(recordId);
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
            fetch(`get_record_details.php?id=${recordId}`)
                .then(response => response.text())
                .then(data => {
                    modalBody.innerHTML = data;
                    downloadModalBtn.href = `?download=${recordId}`;
                })
                .catch(error => {
                    modalBody.innerHTML = `<div class="alert alert-error">Error loading record details: ${error}</div>`;
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
        
        // Download all records
        function downloadAllRecords() {
            if (confirm('This will download all your medical records as a ZIP file. Continue?')) {
                window.location.href = 'download_all_records.php';
            }
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