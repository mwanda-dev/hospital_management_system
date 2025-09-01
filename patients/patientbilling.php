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

// Fetch billing data from database
$billing_data = [];
$total_pending = 0;
$total_paid = 0;
$total_overdue = 0;
$total_balance = 0;

$sql = "SELECT * FROM billing WHERE patient_id = ? ORDER BY invoice_date DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $billing_data[] = $row;
        
        // Calculate totals
        $total_balance += $row['total_amount'];
        $total_paid += $row['paid_amount'];
        
        $pending_amount = $row['total_amount'] - $row['paid_amount'];
        
        if ($row['status'] == 'pending' || $row['status'] == 'partial') {
            $total_pending += $pending_amount;
        } elseif ($row['status'] == 'overdue') {
            $total_overdue += $pending_amount;
        }
    }
}

// Fetch patient information
$patient_info = [];
$sql_patient = "SELECT first_name, last_name, email, phone FROM patients WHERE patient_id = ?";
$stmt_patient = $conn->prepare($sql_patient);
$stmt_patient->bind_param("i", $patient_id);
$stmt_patient->execute();
$result_patient = $stmt_patient->get_result();

if ($result_patient->num_rows === 1) {
    $patient_info = $result_patient->fetch_assoc();
}

// Define allowed payment methods
$allowed_payment_methods = array('credit_card', 'mobile_money', 'bank_transfer', 'insurance');

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
    <title>MediCare Patient Portal - Billing</title>
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
        
        .stat-icon.pending {
            background: var(--warning);
        }
        
        .stat-icon.paid {
            background: var(--secondary);
        }
        
        .stat-icon.overdue {
            background: var(--danger);
        }
        
        .stat-icon.total {
            background: var(--info);
        }
        
        .stat-info h3 {
            font-size: 1.8rem;
            font-weight: 700;
        }
        
        .stat-info p {
            color: var(--gray);
            font-size: 0.9rem;
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
        
        .status.pending {
            background: #fef3c7;
            color: var(--warning);
        }
        
        .status.partial {
            background: #dbeafe;
            color: var(--info);
        }
        
        .status.paid {
            background: #dcfce7;
            color: var(--secondary);
        }
        
        .status.overdue {
            background: #fee2e2;
            color: var(--danger);
        }
        
        .status.canceled {
            background: #f3f4f6;
            color: var(--gray);
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
        
        .btn-success {
            background: var(--secondary);
            color: white;
        }
        
        .btn-success:hover {
            background: #0da271;
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
        
        .payment-methods {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-top: 1.5rem;
        }
        
        .payment-method {
            border: 1px solid #eee;
            border-radius: 8px;
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        
        .payment-method-header {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .payment-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            background: #e0f2fe;
            color: var(--info);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }
        
        .payment-method-info h4 {
            font-size: 1rem;
            margin-bottom: 0.25rem;
        }
        
        .payment-method-info p {
            font-size: 0.85rem;
            color: var(--gray);
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
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .filter-bar {
                flex-direction: column;
                gap: 1rem;
                align-items: stretch;
            }
            
            .filter-options {
                flex-wrap: wrap;
            }
            
            table {
                display: block;
                overflow-x: auto;
            }
            
            .payment-methods {
                grid-template-columns: 1fr;
            }
        }
        
        .empty-state {
            text-align: center;
            padding: 2rem;
            color: var(--gray);
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #d1d5db;
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
                        <p>Welcome, <strong><?php echo htmlspecialchars($patient_info['first_name'] . ' ' . $patient_info['last_name']); ?></strong></p>
                    </div>
                    <div class="dropdown">
                        <img src="https://randomuser.me/api/portraits/men/32.jpg" alt="User Profile" id="profileDropdownToggle">
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
                <li><a href="patientbilling.php" class="active"><i class="fas fa-file-invoice-dollar"></i> Billing</a></li>
                <li><a href="patientmedicalrecords.php"><i class="fas fa-file-medical"></i> Medical Records</a></li>
            </ul>
        </div>
    </div>
    
    <div class="container main-content">
        <div class="page-header">
            <h1 class="page-title">Billing & Payments</h1>
            <button class="btn btn-primary"><i class="fas fa-download"></i> Download Statements</button>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon pending">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-info">
                    <h3>$<?php echo number_format($total_pending, 2); ?></h3>
                    <p>Pending Payments</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon paid">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-info">
                    <h3>$<?php echo number_format($total_paid, 2); ?></h3>
                    <p>Paid This Year</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon overdue">
                    <i class="fas fa-exclamation-circle"></i>
                </div>
                <div class="stat-info">
                    <h3>$<?php echo number_format($total_overdue, 2); ?></h3>
                    <p>Overdue Payments</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon total">
                    <i class="fas fa-file-invoice-dollar"></i>
                </div>
                <div class="stat-info">
                    <h3>$<?php echo number_format($total_balance, 2); ?></h3>
                    <p>Total Balance</p>
                </div>
            </div>
        </div>
        
        <div class="filter-bar">
            <div class="filter-options">
                <select class="filter-select" id="statusFilter">
                    <option value="all">All Status</option>
                    <option value="pending">Pending</option>
                    <option value="partial">Partial</option>
                    <option value="paid">Paid</option>
                    <option value="overdue">Overdue</option>
                </select>
                <select class="filter-select" id="dateFilter">
                    <option value="30">Last 30 Days</option>
                    <option value="90">Last 90 Days</option>
                    <option value="365">This Year</option>
                    <option value="all">All Time</option>
                </select>
            </div>
            <button class="btn btn-outline"><i class="fas fa-print"></i> Print Statements</button>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h3>Recent Bills</h3>
            </div>
            
            <?php if (count($billing_data) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Invoice #</th>
                        <th>Date</th>
                        <th>Due Date</th>
                        <th>Service</th>
                        <th>Amount</th>
                        <th>Paid</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($billing_data as $bill): 
                        $pending_amount = $bill['total_amount'] - $bill['paid_amount'];
                    ?>
                    <tr>
                        <td>INV-<?php echo str_pad($bill['invoice_id'], 5, '0', STR_PAD_LEFT); ?></td>
                        <td><?php echo date('M j, Y', strtotime($bill['invoice_date'])); ?></td>
                        <td><?php echo date('M j, Y', strtotime($bill['due_date'])); ?></td>
                        <td><?php echo !empty($bill['notes']) ? substr($bill['notes'], 0, 30) . (strlen($bill['notes']) > 30 ? '...' : '') : 'Medical Services'; ?></td>
                        <td>$<?php echo number_format($bill['total_amount'], 2); ?></td>
                        <td>$<?php echo number_format($bill['paid_amount'], 2); ?></td>
                        <td><span class="status <?php echo $bill['status']; ?>"><?php echo ucfirst($bill['status']); ?></span></td>
                        <td>
                            <button class="btn btn-outline view-bill" data-id="<?php echo $bill['invoice_id']; ?>">View</button>
                            <?php if ($bill['status'] !== 'paid' && $bill['status'] !== 'canceled'): ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-receipt"></i>
                <h3>No Bills Found</h3>
                <p>You don't have any bills at this time.</p>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h3>Payment Methods</h3>
                <button class="btn btn-primary"><i class="fas fa-plus"></i> Add Payment Method</button>
            </div>
            
            <div class="payment-methods">
                <?php
                // Display only the allowed payment methods
                foreach ($allowed_payment_methods as $method):
                    $icon = '';
                    $title = '';
                    $description = '';
                    
                    switch($method) {
                        case 'credit_card':
                            $icon = 'fa-credit-card';
                            $title = 'Credit/Debit Card';
                            $description = 'Pay securely with your card';
                            break;
                        case 'mobile_money':
                            $icon = 'fa-mobile-alt';
                            $title = 'Mobile Money';
                            $description = 'Pay using mobile money services';
                            break;
                        case 'bank_transfer':
                            $icon = 'fa-university';
                            $title = 'Bank Transfer';
                            $description = 'Transfer funds directly from your bank';
                            break;
                        case 'insurance':
                            $icon = 'fa-file-medical';
                            $title = 'Insurance';
                            $description = 'Bill directly to your insurance provider';
                            break;
                    }
                ?>
                <div class="payment-method">
                    <div class="payment-method-header">
                        <div class="payment-icon">
                            <i class="fas <?php echo $icon; ?>"></i>
                        </div>
                        <div class="payment-method-info">
                            <h4><?php echo $title; ?></h4>
                            <p><?php echo $description; ?></p>
                        </div>
                    </div>
                    <div class="payment-method-actions">
                        <button class="btn btn-outline">Select</button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- View Bill Modal (to be implemented) -->
    <div id="billModal" style="display: none;">
        <div class="modal-content">
            <h2>Invoice Details</h2>
            <div id="billDetails"></div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Toggle dropdown visibility
            const profileDropdownToggle = document.getElementById('profileDropdownToggle');
            const userDropdown = document.getElementById('userDropdown');
            
            profileDropdownToggle.addEventListener('click', function(e) {
                e.stopPropagation();
                userDropdown.classList.toggle('show');
            });
            
            // Close the dropdown if the user clicks outside of it
            document.addEventListener('click', function(e) {
                if (!e.target.matches('#profileDropdownToggle') && !userDropdown.contains(e.target)) {
                    userDropdown.classList.remove('show');
                }
            });
            
            // Filter functionality
            const statusFilter = document.getElementById('statusFilter');
            const dateFilter = document.getElementById('dateFilter');
            
            statusFilter.addEventListener('change', function() {
                filterBills();
            });
            
            dateFilter.addEventListener('change', function() {
                filterBills();
            });
            
            function filterBills() {
                // In a real application, this would send an AJAX request to filter bills
                console.log('Filtering by status:', statusFilter.value, 'and date:', dateFilter.value);
                alert('Filter functionality would be implemented here. Currently showing all bills.');
            }
            
            // View bill buttons
            const viewButtons = document.querySelectorAll('.view-bill');
            viewButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const billId = this.getAttribute('data-id');
                    alert('Viewing details for invoice ID: ' + billId + '\nThis would show a modal with full invoice details.');
                });
            });
            
            // Pay bill buttons
            const payButtons = document.querySelectorAll('.pay-bill');
            payButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const billId = this.getAttribute('data-id');
                    const amount = this.getAttribute('data-amount');
                    alert('Initiating payment of $' + amount + ' for invoice ID: ' + billId + '\nThis would redirect to a payment processing page.');
                });
            });
        });
    </script>
</body>
</html>