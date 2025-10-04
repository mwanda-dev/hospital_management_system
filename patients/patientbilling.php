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

        /* Navigation (canonical) */
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
                        <?php
                        // Prefer $patient_id when available, otherwise try to infer from $patient_info
                        $use_id = isset($patient_id) ? $patient_id : (isset($patient_info['patient_id']) ? $patient_info['patient_id'] : null);
                        $avatar_url = $use_id ? 'https://randomuser.me/api/portraits/lego/' . ($use_id % 10) . '.jpg' : 'https://randomuser.me/api/portraits/lego/0.jpg';
                        ?>
                        <img src="<?php echo $avatar_url; ?>" alt="User Profile" id="profileDropdownToggle">
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
                <li><a href="patientmedicalrecords.php"><i class="fas fa-file-medical"></i> Medical Records</a></li>
                <li><a href="patientprescriptions.php"><i class="fas fa-prescription-bottle"></i> Prescriptions</a></li>
                <li><a href="patientbilling.php" class="active"><i class="fas fa-file-invoice-dollar"></i> Billing</a></li>
                <li><a href="patientprofile.php"><i class="fas fa-user"></i> Profile</a></li>
            </ul>
        </div>
    </div>
    
    <div class="container main-content">
        <div class="page-header">
            <h1 class="page-title">Billing & Payments</h1>
            <button id="downloadStatementsBtn" class="btn btn-primary"><i class="fas fa-download"></i> Download Statements</button>
        
                <script>
                    // Expose billing data to JS for the insurance modal and other actions
                    const BILLING_DATA = <?php echo json_encode($billing_data); ?>;
                    const PATIENT_NAME = <?php echo json_encode($patient_name); ?>;
                </script>
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
            <button id="printStatementsBtn" class="btn btn-outline"><i class="fas fa-print"></i> Print Statements</button>
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
                                <!-- Insurance payments moved to Payment Methods card -->
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
            </div>
            
            <div class="payment-methods">
                <div style="margin-bottom:12px; padding:12px; border:1px dashed #e5e7eb; border-radius:8px; background:#fff8f0; color:#92400e;">
                    <strong>Note:</strong> Only the <em>Insurance</em> payment method is implemented right now. Other online payment methods are not available. All cash payments must be made at the hospital cashier.
                </div>
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
                        <?php if ($method === 'insurance'): ?>
                            <button class="btn btn-outline select-insurance">Select</button>
                        <?php else: ?>
                            <button class="btn btn-outline select-method" data-method="<?php echo $method; ?>">Select</button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Insurance Modal -->
    <div id="insuranceModal" style="display:none; position:fixed; left:0; top:0; right:0; bottom:0; background:rgba(0,0,0,0.5); align-items:center; justify-content:center;">
        <div style="background:white; width:90%; max-width:800px; margin:40px auto; border-radius:8px; padding:1rem; max-height:80vh; overflow:auto;">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <h3>Bill to Insurance - Select Invoice</h3>
                <button id="closeInsuranceModal" class="btn">Close</button>
            </div>
            <div id="insuranceInvoiceList" style="margin-top:1rem;">
                <!-- Unpaid invoices will be injected here -->
            </div>
        </div>
    </div>
    </div>

    <!-- View Bill Modal -->
    <div id="billModal" style="display:none; position:fixed; left:0; top:0; right:0; bottom:0; background:rgba(0,0,0,0.5); align-items:center; justify-content:center; z-index:1000;">
        <div id="billModalContent" style="background:white; width:95%; max-width:900px; margin:40px auto; border-radius:8px; padding:1rem; max-height:90vh; overflow:auto; position:relative;">
            <button id="closeBillModal" class="btn" aria-label="Close invoice" title="Close" style="position:absolute; right:12px; top:12px; width:36px; height:36px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:18px; line-height:1; padding:0; border:1px solid #e5e7eb; background:white; box-shadow:0 2px 6px rgba(0,0,0,0.08);">&times;</button>
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
            
            // View bill buttons - fetch invoice via AJAX and show modal
            const viewButtons = document.querySelectorAll('.view-bill');
            const billModal = document.getElementById('billModal');
            const billDetails = document.getElementById('billDetails');
            const closeBillModal = document.getElementById('closeBillModal');

            function renderInvoice(invoice, items) {
                const pending = (Number(invoice.total_amount) - Number(invoice.paid_amount)).toFixed(2);
                let html = `
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                        <div>
                            <h3 style="margin:0">Invoice #INV-${String(invoice.invoice_id).padStart(5,'0')}</h3>
                            <p style="margin:0;color:#666">Date: ${new Date(invoice.invoice_date).toLocaleDateString()} | Due: ${new Date(invoice.due_date).toLocaleDateString()}</p>
                        </div>
                        <div style="display:flex; gap:8px; align-items:center;">
                            <!-- Print & Download buttons removed per request -->
                        </div>
                    </div>
                    <div style="margin-bottom:12px; display:flex; gap:24px; align-items:center;">
                        <div><strong>Amount:</strong> $${Number(invoice.total_amount).toFixed(2)}</div>
                        <div><strong>Paid:</strong> $${Number(invoice.paid_amount).toFixed(2)}</div>
                        <div><strong>Pending:</strong> $${pending}</div>
                        <div><strong>Status:</strong> ${invoice.status}</div>
                    </div>
                    <div style="margin-bottom:12px;"><strong>Notes:</strong><div style="color:#444;">${invoice.notes ? invoice.notes.replace(/\n/g,'<br>') : '—'}</div></div>
                    <h4>Items</h4>
                    <table style="width:100%; border-collapse:collapse; margin-bottom:12px;">
                        <thead><tr><th style="padding:8px;border:1px solid #eee; text-align:left">Description</th><th style="padding:8px;border:1px solid #eee; width:80px">Qty</th><th style="padding:8px;border:1px solid #eee; width:120px">Unit Price</th><th style="padding:8px;border:1px solid #eee; width:120px">Line Total</th></tr></thead>
                        <tbody>
                `;
                if (items && items.length > 0) {
                    items.forEach(it => {
                        const line = (Number(it.quantity) * Number(it.unit_price)).toFixed(2);
                        html += `<tr><td style="padding:8px;border:1px solid #eee">${it.description}</td><td style="padding:8px;border:1px solid #eee">${it.quantity}</td><td style="padding:8px;border:1px solid #eee">$${Number(it.unit_price).toFixed(2)}</td><td style="padding:8px;border:1px solid #eee">$${line}</td></tr>`;
                    });
                } else {
                    // If no items table, try to show notes only
                    html += `<tr><td colspan="4" style="padding:8px;border:1px solid #eee">No itemized entries found.</td></tr>`;
                }
                html += `</tbody></table>`;

                billDetails.innerHTML = html;

                // Print & Download handlers removed per request
            }

            viewButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const billId = this.getAttribute('data-id');
                    // fetch invoice
                    fetch(`../ajax/get_invoice.php?invoice_id=${encodeURIComponent(billId)}`, { credentials: 'same-origin' })
                        .then(r => r.json())
                        .then(data => {
                            if (!data.success) {
                                alert('Error: ' + (data.message || 'Failed to fetch invoice'));
                                return;
                            }
                            renderInvoice(data.invoice, data.items || []);
                            billModal.style.display = 'flex';
                            // scroll modal to top
                            document.getElementById('billModalContent').scrollTop = 0;
                        })
                        .catch(err => { console.error(err); alert('Unexpected error fetching invoice'); });
                });
            });

            if (closeBillModal) {
                const closeModal = function() {
                    billModal.style.display = 'none';
                    // remove escape listener when closed
                    document.removeEventListener('keydown', escHandler);
                };

                closeBillModal.addEventListener('click', closeModal);

                // Close when clicking outside modal content
                billModal.addEventListener('click', function(e) {
                    if (e.target === billModal) {
                        closeModal();
                    }
                });

                // Close on ESC
                const escHandler = function(e) {
                    if (e.key === 'Escape' || e.key === 'Esc') {
                        closeModal();
                    }
                };
                document.addEventListener('keydown', escHandler);
            }
            
            // Pay bill buttons
            const payButtons = document.querySelectorAll('.pay-bill');
            payButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const billId = this.getAttribute('data-id');
                    const amount = this.getAttribute('data-amount');
                    alert('Initiating payment of $' + amount + ' for invoice ID: ' + billId + '\nThis would redirect to a payment processing page.');
                });
            });

            // Use Insurance buttons
            // Insurance modal handlers (opened from Payment Methods card)
            // Expose patient insurance details to JS
            const PATIENT_INSURANCE = {
                provider: <?php echo json_encode($patient['insurance_provider'] ?? ''); ?>,
                policy: <?php echo json_encode($patient['insurance_policy_number'] ?? ''); ?>
            };

            const selectInsuranceBtn = document.querySelector('.select-insurance');
            // Non-insurance methods handler
            const selectMethodBtns = document.querySelectorAll('.select-method');
            selectMethodBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    // Inform user these methods aren't implemented yet and cash at hospital
                    alert('This payment method is not implemented yet. All cash payments must be made at the hospital cashier.');
                });
            });
            const insuranceModal = document.getElementById('insuranceModal');
            const closeInsuranceModal = document.getElementById('closeInsuranceModal');
            const insuranceInvoiceList = document.getElementById('insuranceInvoiceList');

            function renderUnpaidInvoices() {
                insuranceInvoiceList.innerHTML = '';
                const unpaid = BILLING_DATA.filter(b => b.status !== 'paid' && b.status !== 'canceled');
                if (unpaid.length === 0) {
                    insuranceInvoiceList.innerHTML = '<p>No unpaid invoices available to bill to insurance.</p>';
                    return;
                }

                const table = document.createElement('table');
                table.style.width = '100%';
                table.style.borderCollapse = 'collapse';
                table.innerHTML = `
                    <thead>
                        <tr>
                            <th style="text-align:left; padding:8px; border-bottom:1px solid #eee;">Invoice #</th>
                            <th style="text-align:left; padding:8px; border-bottom:1px solid #eee;">Date</th>
                            <th style="text-align:left; padding:8px; border-bottom:1px solid #eee;">Amount</th>
                            <th style="text-align:left; padding:8px; border-bottom:1px solid #eee;">Status</th>
                            <th style="text-align:left; padding:8px; border-bottom:1px solid #eee;">Action</th>
                        </tr>
                    </thead>
                `;
                const tbody = document.createElement('tbody');

                unpaid.forEach(inv => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td style="padding:8px; border-bottom:1px solid #eee;">INV-${String(inv.invoice_id).padStart(5,'0')}</td>
                        <td style="padding:8px; border-bottom:1px solid #eee;">${new Date(inv.invoice_date).toLocaleDateString()}</td>
                        <td style="padding:8px; border-bottom:1px solid #eee;">$${Number(inv.total_amount).toFixed(2)}</td>
                        <td style="padding:8px; border-bottom:1px solid #eee;">${inv.status}</td>
                        <td style="padding:8px; border-bottom:1px solid #eee;"><button class="btn btn-success bill-to-insurance" data-id="${inv.invoice_id}">Bill to Insurance</button></td>
                    `;
                    tbody.appendChild(tr);
                });

                table.appendChild(tbody);
                insuranceInvoiceList.appendChild(table);

                // attach handlers
                const billBtns = insuranceInvoiceList.querySelectorAll('.bill-to-insurance');
                billBtns.forEach(b => {
                    b.addEventListener('click', function() {
                        const invoiceId = this.getAttribute('data-id');
                        if (!confirm('Send this invoice to your insurance provider?')) return;

                        const formData = new FormData();
                        formData.append('invoice_id', invoiceId);

                        fetch('../ajax/process_insurance_payment.php', {
                            method: 'POST',
                            credentials: 'same-origin',
                            body: formData
                        })
                        .then(resp => resp.json())
                        .then(data => {
                            if (data.success) {
                                alert(data.message);
                                window.location.reload();
                            } else {
                                alert('Error: ' + data.message);
                            }
                        })
                        .catch(err => {
                            console.error(err);
                            alert('An unexpected error occurred.');
                        });
                    });
                });
            }

            if (selectInsuranceBtn) {
                selectInsuranceBtn.addEventListener('click', function() {
                    // If patient has no insurance details, show message
                    if (!PATIENT_INSURANCE.provider || !PATIENT_INSURANCE.policy) {
                        alert("You're not insured. Please update your insurance details in your profile before billing to insurance.");
                        return;
                    }

                    renderUnpaidInvoices();
                    insuranceModal.style.display = 'flex';
                });
            }

            if (closeInsuranceModal) {
                closeInsuranceModal.addEventListener('click', function() {
                    insuranceModal.style.display = 'none';
                });
            }

            // Download Statements (CSV)
            const downloadBtn = document.getElementById('downloadStatementsBtn');
            if (downloadBtn) {
                downloadBtn.addEventListener('click', function() {
                    if (!BILLING_DATA || BILLING_DATA.length === 0) {
                        alert('No billing data available to download.');
                        return;
                    }

                    // Build CSV
                    const headers = ['Invoice #', 'Invoice Date', 'Due Date', 'Service', 'Total Amount', 'Paid Amount', 'Status'];
                    const rows = BILLING_DATA.map(b => [
                        `INV-${String(b.invoice_id).padStart(5,'0')}`,
                        new Date(b.invoice_date).toLocaleDateString(),
                        new Date(b.due_date).toLocaleDateString(),
                        (b.notes || 'Medical Services').replace(/\r?\n|,/g, ' '),
                        Number(b.total_amount).toFixed(2),
                        Number(b.paid_amount).toFixed(2),
                        b.status
                    ]);

                    const csvContent = [headers, ...rows].map(r => r.map(field => `"${String(field).replace(/"/g,'""')}"`).join(',')).join('\r\n');
                    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
                    const url = URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    const filename = `billing_statements_${PATIENT_NAME.replace(/\s+/g,'_')}_${new Date().toISOString().slice(0,10)}.csv`;
                    a.download = filename;
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    URL.revokeObjectURL(url);
                });
            }

            // Print Statements - open a printable view in a new window
            const printBtn = document.getElementById('printStatementsBtn');
            if (printBtn) {
                printBtn.addEventListener('click', function() {
                    const bills = BILLING_DATA || [];
                    let printable = `<!doctype html><html><head><meta charset="utf-8"><title>Printable Billing Statements</title><style>body{font-family:Arial,Helvetica,sans-serif;margin:20px;color:#222}table{width:100%;border-collapse:collapse}th,td{padding:8px;border:1px solid #ddd;text-align:left}th{background:#f7f7f7}</style></head><body>`;
                    printable += `<h2>Billing Statements for ${PATIENT_NAME}</h2>`;
                    printable += `<p>Generated: ${new Date().toLocaleString()}</p>`;
                    if (bills.length === 0) {
                        printable += '<p>No billing records found.</p>';
                    } else {
                        printable += '<table><thead><tr><th>Invoice #</th><th>Date</th><th>Due Date</th><th>Service</th><th>Amount</th><th>Paid</th><th>Status</th></tr></thead><tbody>';
                        bills.forEach(b => {
                            printable += `<tr><td>INV-${String(b.invoice_id).padStart(5,'0')}</td><td>${new Date(b.invoice_date).toLocaleDateString()}</td><td>${new Date(b.due_date).toLocaleDateString()}</td><td>${(b.notes||'Medical Services')}</td><td>$${Number(b.total_amount).toFixed(2)}</td><td>$${Number(b.paid_amount).toFixed(2)}</td><td>${b.status}</td></tr>`;
                        });
                        printable += '</tbody></table>';
                    }
                    printable += '</body></html>';

                    const w = window.open('', '_blank');
                    if (!w) { alert('Popup blocked. Please allow popups for this site to use the printable view.'); return; }
                    w.document.open();
                    w.document.write(printable);
                    w.document.close();
                    // Give the new window a moment to render before printing
                    setTimeout(() => { w.print(); }, 500);
                });
            }
        });
    </script>
</body>
</html>