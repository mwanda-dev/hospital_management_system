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

// Fetch prescriptions for the current patient
$prescriptions = [];
$prescription_items = [];

try {
    // Get prescriptions
    $stmt = $conn->prepare("
        SELECT p.*, d.first_name, d.last_name 
        FROM prescriptions p 
        LEFT JOIN users d ON p.doctor_id = d.user_id 
        WHERE p.patient_id = ? 
        ORDER BY p.prescription_date DESC
    ");
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $prescriptions[$row['prescription_id']] = $row;
    }
    
    // Get prescription items if we have prescriptions
    if (!empty($prescriptions)) {
        $prescription_ids = array_keys($prescriptions);
        $placeholders = implode(',', array_fill(0, count($prescription_ids), '?'));
        
        $stmt = $conn->prepare("
            SELECT * FROM prescription_items 
            WHERE prescription_id IN ($placeholders)
        ");
        
        // Bind parameters dynamically
        $types = str_repeat('i', count($prescription_ids));
        $stmt->bind_param($types, ...$prescription_ids);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $prescription_items[$row['prescription_id']][] = $row;
        }
    }
} catch (Exception $e) {
    $error = "Error fetching prescriptions: " . $e->getMessage();
}

// Handle logout
if (isset($_GET['logout'])) {
    logout();
    header("Location: ../includes/login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MediCare Patient Portal - Prescriptions</title>
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
        
        .prescription-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1.5rem;
            border: 1px solid #eee;
            border-radius: 8px;
            margin-bottom: 1rem;
            transition: transform 0.3s ease;
        }
        
        .prescription-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        
        .prescription-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            background: #e0f2fe;
            color: var(--info);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }
        
        .prescription-info {
            flex: 1;
        }
        
        .prescription-info h4 {
            font-size: 1.1rem;
            margin-bottom: 0.25rem;
            color: var(--dark);
        }
        
        .prescription-info p {
            font-size: 0.9rem;
            color: var(--gray);
            margin-bottom: 0.25rem;
        }
        
        .prescription-meta {
            display: flex;
            gap: 1rem;
            margin-top: 0.5rem;
            flex-wrap: wrap;
        }
        
        .meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.85rem;
            color: var(--gray);
        }
        
        .prescription-actions {
            display: flex;
            gap: 0.5rem;
        }
        
        .btn {
            padding: 0.5rem 1rem;
            border-radius: 5px;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            border: none;
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
        
        .status {
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .status.active {
            background: #dcfce7;
            color: var(--secondary);
        }
        
        .status.completed {
            background: #dbeafe;
            color: var(--info);
        }
        
        .status.canceled {
            background: #fee2e2;
            color: var(--danger);
        }
        
        .filter-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding: 1rem;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }
        
        .search-box {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border: 1px solid #ddd;
            border-radius: 5px;
            background: #f9fafb;
        }
        
        .search-box input {
            border: none;
            background: transparent;
            outline: none;
            width: 250px;
        }
        
        .filter-options {
            display: flex;
            gap: 1rem;
        }
        
        .filter-select {
            padding: 0.5rem;
            border: 1px solid #ddd;
            border-radius: 5px;
            background: #f9fafb;
            cursor: pointer;
        }
        
        .no-prescriptions {
            text-align: center;
            padding: 2rem;
            color: var(--gray);
        }
        
        .no-prescriptions i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #ddd;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 1rem;
            }
            
            .nav-menu {
                flex-wrap: wrap;
            }
            
            .filter-bar {
                flex-direction: column;
                gap: 1rem;
                align-items: stretch;
            }
            
            .search-box {
                width: 100%;
            }
            
            .search-box input {
                width: 100%;
            }
            
            .filter-options {
                flex-wrap: wrap;
            }
            
            .prescription-item {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .prescription-actions {
                width: 100%;
                justify-content: flex-end;
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
                        <img src="https://randomuser.me/api/portraits/men/32.jpg" alt="User Profile" id="profileImg">
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
                <li><a href="patientprescriptions.php" class="active"><i class="fas fa-prescription"></i> Prescriptions</a></li>
                <li><a href="patientbilling.php"><i class="fas fa-file-invoice-dollar"></i> Billing</a></li>
                <li><a href="patientmedicalrecords.php"><i class="fas fa-file-medical"></i> Medical Records</a></li>
            </ul>
        </div>
    </div>
    
    <div class="container main-content">
        <div class="page-header">
            <h1 class="page-title">My Prescriptions</h1>
            <button class="btn btn-primary"><i class="fas fa-plus"></i> Request Refill</button>
        </div>
        
        <div class="filter-bar">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" placeholder="Search medications..." id="searchInput">
            </div>
            <div class="filter-options">
                <select class="filter-select" id="statusFilter">
                    <option value="all">All Status</option>
                    <option value="active">Active</option>
                    <option value="completed">Completed</option>
                    <option value="canceled">Canceled</option>
                </select>
                <select class="filter-select" id="sortFilter">
                    <option value="newest">Newest First</option>
                    <option value="oldest">Oldest First</option>
                </select>
            </div>
        </div>
        
        <?php if (isset($error)): ?>
        <div class="card">
            <div class="no-prescriptions">
                <i class="fas fa-exclamation-circle"></i>
                <h3>Error Loading Prescriptions</h3>
                <p><?php echo $error; ?></p>
            </div>
        </div>
        <?php elseif (empty($prescriptions)): ?>
        <div class="card">
            <div class="no-prescriptions">
                <i class="fas fa-file-prescription"></i>
                <h3>No Prescriptions Found</h3>
                <p>You don't have any prescriptions yet.</p>
            </div>
        </div>
        <?php else: ?>
        
        <div class="card">
            <div class="card-header">
                <h3>Current Prescriptions</h3>
            </div>
            
            <?php 
            $current_prescriptions = array_filter($prescriptions, function($prescription) {
                return $prescription['status'] === 'active';
            });
            
            if (empty($current_prescriptions)): ?>
            <div class="no-prescriptions">
                <i class="fas fa-check-circle"></i>
                <p>No active prescriptions</p>
            </div>
            <?php else: ?>
                <?php foreach ($current_prescriptions as $prescription_id => $prescription): ?>
                    <?php if (isset($prescription_items[$prescription_id])): ?>
                        <?php foreach ($prescription_items[$prescription_id] as $item): ?>
                        <div class="prescription-item" data-status="active" data-date="<?php echo $prescription['prescription_date']; ?>">
                            <div class="prescription-icon">
                                <i class="fas fa-pills"></i>
                            </div>
                            <div class="prescription-info">
                                <h4><?php echo htmlspecialchars($item['medication_name']); ?></h4>
                                <p><?php echo htmlspecialchars($item['notes'] ?? 'No description'); ?></p>
                                <div class="prescription-meta">
                                    <span class="meta-item"><i class="fas fa-weight"></i> <?php echo htmlspecialchars($item['dosage']); ?></span>
                                    <span class="meta-item"><i class="fas fa-clock"></i> <?php echo htmlspecialchars($item['frequency']); ?></span>
                                    <span class="meta-item"><i class="fas fa-calendar"></i> <?php echo htmlspecialchars($item['duration']); ?></span>
                                    <span class="meta-item"><i class="fas fa-user-md"></i> Dr. <?php echo htmlspecialchars($prescription['first_name'] . ' ' . $prescription['last_name']); ?></span>
                                    <span class="meta-item"><i class="fas fa-calendar-alt"></i> <?php echo date('M j, Y', strtotime($prescription['prescription_date'])); ?></span>
                                    <?php if ($prescription['refills_remaining'] > 0): ?>
                                    <span class="meta-item"><i class="fas fa-sync-alt"></i> <?php echo $prescription['refills_remaining']; ?> refills remaining</span>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($prescription['instructions'])): ?>
                                <div class="prescription-meta">
                                    <span class="meta-item"><i class="fas fa-info-circle"></i> Instructions: <?php echo htmlspecialchars($prescription['instructions']); ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                            <span class="status active">Active</span>
                            <div class="prescription-actions">
                                <button class="btn btn-outline">Details</button>
                                <?php if ($prescription['refills_remaining'] > 0): ?>
                                <button class="btn btn-primary">Request Refill</button>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h3>Past Prescriptions</h3>
            </div>
            
            <?php 
            $past_prescriptions = array_filter($prescriptions, function($prescription) {
                return $prescription['status'] !== 'active';
            });
            
            if (empty($past_prescriptions)): ?>
            <div class="no-prescriptions">
                <i class="fas fa-history"></i>
                <p>No past prescriptions</p>
            </div>
            <?php else: ?>
                <?php foreach ($past_prescriptions as $prescription_id => $prescription): ?>
                    <?php if (isset($prescription_items[$prescription_id])): ?>
                        <?php foreach ($prescription_items[$prescription_id] as $item): ?>
                        <div class="prescription-item" data-status="<?php echo $prescription['status']; ?>" data-date="<?php echo $prescription['prescription_date']; ?>">
                            <div class="prescription-icon">
                                <i class="fas fa-pills"></i>
                            </div>
                            <div class="prescription-info">
                                <h4><?php echo htmlspecialchars($item['medication_name']); ?></h4>
                                <p><?php echo htmlspecialchars($item['notes'] ?? 'No description'); ?></p>
                                <div class="prescription-meta">
                                    <span class="meta-item"><i class="fas fa-weight"></i> <?php echo htmlspecialchars($item['dosage']); ?></span>
                                    <span class="meta-item"><i class="fas fa-clock"></i> <?php echo htmlspecialchars($item['frequency']); ?></span>
                                    <span class="meta-item"><i class="fas fa-calendar"></i> <?php echo htmlspecialchars($item['duration']); ?></span>
                                    <span class="meta-item"><i class="fas fa-user-md"></i> Dr. <?php echo htmlspecialchars($prescription['first_name'] . ' ' . $prescription['last_name']); ?></span>
                                    <span class="meta-item"><i class="fas fa-calendar-alt"></i> <?php echo date('M j, Y', strtotime($prescription['prescription_date'])); ?></span>
                                </div>
                                <?php if (!empty($prescription['instructions'])): ?>
                                <div class="prescription-meta">
                                    <span class="meta-item"><i class="fas fa-info-circle"></i> Instructions: <?php echo htmlspecialchars($prescription['instructions']); ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                            <span class="status <?php echo $prescription['status']; ?>"><?php echo ucfirst($prescription['status']); ?></span>
                            <div class="prescription-actions">
                                <button class="btn btn-outline">Details</button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Dropdown functionality
            const profileImg = document.getElementById('profileImg');
            const userDropdown = document.getElementById('userDropdown');
            
            // Toggle dropdown visibility
            function toggleDropdown() {
                userDropdown.classList.toggle('show');
            }
            
            // Add click event to profile image
            profileImg.addEventListener('click', function(e) {
                e.stopPropagation();
                toggleDropdown();
            });
            
            // Close dropdown when clicking outside
            document.addEventListener('click', function(e) {
                if (!e.target.matches('#profileImg') && !userDropdown.contains(e.target)) {
                    userDropdown.classList.remove('show');
                }
            });
            
            // Filter functionality
            const statusFilter = document.getElementById('statusFilter');
            const sortFilter = document.getElementById('sortFilter');
            const searchInput = document.getElementById('searchInput');
            const prescriptionItems = document.querySelectorAll('.prescription-item');
            
            function filterPrescriptions() {
                const statusValue = statusFilter.value;
                const sortValue = sortFilter.value;
                const searchValue = searchInput.value.toLowerCase();
                
                prescriptionItems.forEach(item => {
                    const status = item.getAttribute('data-status');
                    const medicationName = item.querySelector('h4').textContent.toLowerCase();
                    const description = item.querySelector('p').textContent.toLowerCase();
                    
                    // Status filter
                    const statusMatch = statusValue === 'all' || status === statusValue;
                    
                    // Search filter
                    const searchMatch = medicationName.includes(searchValue) || description.includes(searchValue);
                    
                    // Show/hide based on filters
                    if (statusMatch && searchMatch) {
                        item.style.display = 'flex';
                    } else {
                        item.style.display = 'none';
                    }
                });
                
                // Sorting
                const visibleItems = Array.from(prescriptionItems).filter(item => item.style.display !== 'none');
                
                visibleItems.sort((a, b) => {
                    const dateA = new Date(a.getAttribute('data-date'));
                    const dateB = new Date(b.getAttribute('data-date'));
                    
                    if (sortValue === 'newest') {
                        return dateB - dateA;
                    } else {
                        return dateA - dateB;
                    }
                });
                
                // Reorder items in DOM
                const containers = document.querySelectorAll('.card');
                containers.forEach(container => {
                    const itemsInContainer = Array.from(container.querySelectorAll('.prescription-item'));
                    itemsInContainer.forEach(item => item.remove());
                    
                    visibleItems.forEach(item => {
                        if (container.contains(item)) {
                            container.appendChild(item);
                        }
                    });
                });
            }
            
            // Add event listeners
            statusFilter.addEventListener('change', filterPrescriptions);
            sortFilter.addEventListener('change', filterPrescriptions);
            searchInput.addEventListener('keyup', filterPrescriptions);
            
            // Request refill buttons
            const refillButtons = document.querySelectorAll('.btn-primary');
            refillButtons.forEach(button => {
                button.addEventListener('click', function() {
                    if (this.textContent.includes('Request Refill')) {
                        alert('Refill request submitted!');
                    }
                });
            });
        });
    </script>
</body>
</html>