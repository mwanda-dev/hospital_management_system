<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

// Check if user is logged in and is a patient
if (!isLoggedIn() || !isPatient()) {
    header("Location: ../includes/login.php");
    exit();
}

// Get patient data from database
$patient_id = $_SESSION['user_id'];
$sql = "SELECT * FROM patients WHERE patient_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Patient not found.");
}

$patient = $result->fetch_assoc();

// Handle form submission for updating profile
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $address = trim($_POST['address']);
    $emergency_contact_name = trim($_POST['emergency_contact_name']);
    $emergency_contact_phone = trim($_POST['emergency_contact_phone']);
    $insurance_provider = trim($_POST['insurance_provider']);
    $insurance_policy_number = trim($_POST['insurance_policy_number']);
    
    // Update query
    $update_sql = "UPDATE patients SET 
                  first_name = ?, 
                  last_name = ?, 
                  phone = ?, 
                  email = ?, 
                  address = ?, 
                  emergency_contact_name = ?, 
                  emergency_contact_phone = ?, 
                  insurance_provider = ?, 
                  insurance_policy_number = ? 
                  WHERE patient_id = ?";
    
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("sssssssssi", 
        $first_name, $last_name, $phone, $email, $address, 
        $emergency_contact_name, $emergency_contact_phone, 
        $insurance_provider, $insurance_policy_number, $patient_id
    );
    
    if ($update_stmt->execute()) {
        $success = "Profile updated successfully!";
        // Refresh patient data
        $stmt->execute();
        $result = $stmt->get_result();
        $patient = $result->fetch_assoc();
    } else {
        $error = "Error updating profile: " . $conn->error;
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    // For patients, the password is their phone number
    // This is a simple implementation - you might want to enhance security
    $new_phone = trim($_POST['new_phone']);
    
    $update_sql = "UPDATE patients SET phone = ? WHERE patient_id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("si", $new_phone, $patient_id);
    
    if ($update_stmt->execute()) {
        $success = "Phone number (password) updated successfully!";
        // Refresh patient data
        $stmt->execute();
        $result = $stmt->get_result();
        $patient = $result->fetch_assoc();
    } else {
        $error = "Error updating phone number: " . $conn->error;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MediCare Patient Portal - Profile</title>
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
            cursor: pointer;
        }
        
        /* Navigation */
        .nav-container {
            background: white;
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
            text-decoration: none;
            color: var(--dark);
            font-weight: 500;
            transition: all 0.3s ease;
            border-bottom: 3px solid transparent;
        }
        
        .nav-menu a:hover {
            background: #f8fafc;
            color: var(--primary);
        }
        
        .nav-menu a.active {
            color: var(--primary);
            border-bottom: 3px solid var(--primary);
        }
        
        /* Main Content */
        .main-content {
            padding: 0 0 2rem 0;
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }
        
        .page-title {
            font-size: 1.8rem;
            color: var(--dark);
            font-weight: 600;
        }
        
        .profile-container {
            display: grid;
            grid-template-columns: 1fr 2fr;
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
        
        .btn {
            padding: 0.5rem 1rem;
            border-radius: 5px;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            border: none;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
        }
        
        .btn-outline {
            background: transparent;
            border: 1px solid var(--primary);
            color: var(--primary);
        }
        
        .btn-outline:hover {
            background: var(--primary);
            color: white;
        }
        
        .profile-sidebar {
            position: sticky;
            top: 2rem;
            height: fit-content;
        }
        
        .profile-summary {
            text-align: center;
            padding: 2rem 1rem;
        }
        
        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid var(--primary);
            margin: 0 auto 1rem;
            background-color: #e0e7ff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            color: var(--primary);
        }
        
        .profile-name {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .profile-role {
            color: var(--gray);
            margin-bottom: 1.5rem;
        }
        
        .profile-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-top: 1.5rem;
        }
        
        .stat {
            text-align: center;
            padding: 1rem;
            background: #f8fafc;
            border-radius: 8px;
        }
        
        .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
        }
        
        .stat-label {
            font-size: 0.85rem;
            color: var(--gray);
        }
        
        .info-group {
            margin-bottom: 1.5rem;
        }
        
        .info-group:last-child {
            margin-bottom: 0;
        }
        
        .info-label {
            font-size: 0.85rem;
            color: var(--gray);
            margin-bottom: 0.25rem;
        }
        
        .info-value {
            font-size: 1rem;
            font-weight: 500;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
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
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        
        .emergency-contact {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            border: 1px solid #eee;
            border-radius: 8px;
            margin-bottom: 1rem;
        }
        
        .emergency-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: #fee2e2;
            color: var(--danger);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }
        
        .emergency-info h4 {
            font-size: 1.1rem;
            margin-bottom: 0.25rem;
        }
        
        .emergency-info p {
            color: var(--gray);
            margin-bottom: 0.25rem;
        }
        
        .tab-container {
            margin-bottom: 1.5rem;
        }
        
        .tab-menu {
            display: flex;
            list-style: none;
            border-bottom: 1px solid #eee;
            margin-bottom: 1.5rem;
        }
        
        .tab-menu li {
            margin-right: 1rem;
        }
        
        .tab-menu a {
            display: block;
            padding: 0.75rem 1rem;
            text-decoration: none;
            color: var(--gray);
            font-weight: 500;
            border-bottom: 3px solid transparent;
            transition: all 0.3s ease;
        }
        
        .tab-menu a.active {
            color: var(--primary);
            border-bottom: 3px solid var(--primary);
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1rem;
        }
        
        .alert-success {
            background-color: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        
        .alert-error {
            background-color: #fee2e2;
            color: #b91c1c;
            border: 1px solid #fecaca;
        }
        
        /* Responsive */
        @media (max-width: 992px) {
            .profile-container {
                grid-template-columns: 1fr;
            }
            
            .profile-sidebar {
                position: static;
            }
        }
        
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 1rem;
            }
            
            .nav-menu {
                flex-wrap: wrap;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .profile-stats {
                grid-template-columns: 1fr;
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
                        <p>Welcome, <strong><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></strong></p>
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
                <li><a href="patientprescriptions.php"><i class="fas fa-prescription"></i> Prescriptions</a></li>
                <li><a href="patientbilling.php"><i class="fas fa-file-invoice-dollar"></i> Billing</a></li>
                <li><a href="patientmedicalrecords.php"><i class="fas fa-file-medical"></i> Medical Records</a></li>
                <li><a href="patientprofile.php" class="active"><i class="fas fa-user"></i> Profile</a></li>
            </ul>
        </div>
    </div>
    
    <div class="container main-content">
        <div class="page-header">
            <h1 class="page-title">My Profile</h1>
        </div>
        
        <?php if (isset($success)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $success; ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <div class="profile-container">
            <div class="profile-sidebar">
                <div class="card">
                    <div class="profile-summary">
                        <div class="profile-avatar" style="overflow: hidden; width: 120px; height: 120px; border-radius: 50%;">
                            <img src="https://randomuser.me/api/portraits/men/32.jpg" alt="User Avatar" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                        </div>
                        <h2 class="profile-name"><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></h2>
                        <p class="profile-role">Patient since <?php echo date('Y', strtotime($patient['registration_date'])); ?></p>
                    </div>
                    
                    <div class="profile-stats">
                        <div class="stat">
                            <div class="stat-value"><?php echo rand(5, 20); ?></div>
                            <div class="stat-label">Visits</div>
                        </div>
                        <div class="stat">
                            <div class="stat-value"><?php echo rand(3, 10); ?></div>
                            <div class="stat-label">Prescriptions</div>
                        </div>
                    </div>
                </div>
                
                <?php if (!empty($patient['emergency_contact_name'])): ?>
                <div class="card">
                    <div class="card-header">
                        <h3>Emergency Contacts</h3>
                    </div>
                    
                    <div class="emergency-contact">
                        <div class="emergency-icon">
                            <i class="fas fa-user"></i>
                        </div>
                        <div class="emergency-info">
                            <h4><?php echo htmlspecialchars($patient['emergency_contact_name']); ?></h4>
                            <p>Emergency Contact</p>
                            <p><i class="fas fa-phone"></i> <?php echo htmlspecialchars($patient['emergency_contact_phone']); ?></p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="profile-main">
                <div class="card">
                    <div class="tab-container">
                        <ul class="tab-menu">
                            <li><a href="#personal" class="active">Personal Info</a></li>
                            <li><a href="#medical">Medical Info</a></li>
                            <li><a href="#security">Security</a></li>
                        </ul>
                        
                        <div id="personal" class="tab-content active">
                            <form method="POST" action="">
                                <div class="card-header">
                                    <h3>Personal Information</h3>
                                    <button type="button" id="edit-personal" class="btn btn-outline"><i class="fas fa-edit"></i> Edit</button>
                                    <button type="submit" name="update_profile" id="save-personal" class="btn btn-primary" style="display: none;"><i class="fas fa-save"></i> Save</button>
                                </div>
                                
                                <div class="form-grid">
                                    <div class="form-group">
                                        <label class="form-label">First Name</label>
                                        <input type="text" class="form-control" name="first_name" value="<?php echo htmlspecialchars($patient['first_name']); ?>" readonly>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label class="form-label">Last Name</label>
                                        <input type="text" class="form-control" name="last_name" value="<?php echo htmlspecialchars($patient['last_name']); ?>" readonly>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Date of Birth</label>
                                    <input type="text" class="form-control" value="<?php echo date('F j, Y', strtotime($patient['date_of_birth'])); ?>" readonly>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Gender</label>
                                    <input type="text" class="form-control" value="<?php echo ucfirst($patient['gender']); ?>" readonly>
                                </div>
                                
                                <div class="card-header" style="margin-top: 2rem;">
                                    <h3>Contact Information</h3>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Email Address</label>
                                    <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($patient['email']); ?>" readonly>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Phone Number</label>
                                    <input type="tel" class="form-control" name="phone" value="<?php echo htmlspecialchars($patient['phone']); ?>" readonly>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Address</label>
                                    <textarea class="form-control" name="address" readonly><?php echo htmlspecialchars($patient['address']); ?></textarea>
                                </div>
                                
                                <div class="card-header" style="margin-top: 2rem;">
                                    <h3>Emergency Contact</h3>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Emergency Contact Name</label>
                                    <input type="text" class="form-control" name="emergency_contact_name" value="<?php echo htmlspecialchars($patient['emergency_contact_name']); ?>" readonly>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Emergency Contact Phone</label>
                                    <input type="text" class="form-control" name="emergency_contact_phone" value="<?php echo htmlspecialchars($patient['emergency_contact_phone']); ?>" readonly>
                                </div>
                                
                                <div class="card-header" style="margin-top: 2rem;">
                                    <h3>Insurance Information</h3>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Insurance Provider</label>
                                    <input type="text" class="form-control" name="insurance_provider" value="<?php echo htmlspecialchars($patient['insurance_provider']); ?>" readonly>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Insurance Policy Number</label>
                                    <input type="text" class="form-control" name="insurance_policy_number" value="<?php echo htmlspecialchars($patient['insurance_policy_number']); ?>" readonly>
                                </div>
                            </form>
                        </div>
                        
                        <div id="medical" class="tab-content">
                            <div class="card-header">
                                <h3>Medical Information</h3>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Blood Type</label>
                                <input type="text" class="form-control" value="<?php echo $patient['blood_type'] ? htmlspecialchars($patient['blood_type']) : 'Not specified'; ?>" readonly>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Medical Records</label>
                                <p class="info-value">View your medical records in the <a href="patientmedicalrecords.php">Medical Records</a> section.</p>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Prescriptions</label>
                                <p class="info-value">View your prescriptions in the <a href="patientprescriptions.php">Prescriptions</a> section.</p>
                            </div>
                        </div>
                        
                        <div id="security" class="tab-content">
                            <form method="POST" action="">
                                <div class="card-header">
                                    <h3>Security Settings</h3>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Current Phone Number (Password)</label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($patient['phone']); ?>" readonly>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">New Phone Number (Will become your new password)</label>
                                    <input type="text" class="form-control" name="new_phone" placeholder="Enter new phone number">
                                </div>
                                
                                <button type="submit" name="change_password" class="btn btn-primary">Update Phone Number/Password</button>
                            </form>
                        </div>
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
        
        document.addEventListener('DOMContentLoaded', function() {
            // Tab functionality
            const tabLinks = document.querySelectorAll('.tab-menu a');
            const tabContents = document.querySelectorAll('.tab-content');
            
            tabLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    // Remove active class from all tabs
                    tabLinks.forEach(tab => tab.classList.remove('active'));
                    tabContents.forEach(tab => tab.classList.remove('active'));
                    
                    // Add active class to clicked tab
                    this.classList.add('active');
                    const tabId = this.getAttribute('href');
                    document.querySelector(tabId).classList.add('active');
                });
            });
            
            // Edit button functionality for personal info
            const editPersonalBtn = document.getElementById('edit-personal');
            const savePersonalBtn = document.getElementById('save-personal');
            
            if (editPersonalBtn) {
                editPersonalBtn.addEventListener('click', function() {
                    const formControls = document.querySelectorAll('#personal input:not([readonly]), #personal textarea:not([readonly])');
                    const isReadOnly = formControls[0].hasAttribute('readonly');
                    
                    if (isReadOnly) {
                        formControls.forEach(control => control.removeAttribute('readonly'));
                        editPersonalBtn.style.display = 'none';
                        savePersonalBtn.style.display = 'inline-block';
                    }
                });
            }
        });
    </script>
</body>
</html>