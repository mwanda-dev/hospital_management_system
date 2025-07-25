<?php
$page_title = "Billing & Payments";
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
    'hospital_address' => '123 Medical Drive, Lusaka, Zambia',
    'hospital_phone' => '+260 211 123456',
    'hospital_email' => 'info@medicare.com',
    'currency_symbol' => '$',
    'date_format' => 'Y-m-d',
    'time_format' => 'H:i',
    'records_per_page' => '10',
    'enable_sms_notifications' => '1',
    'enable_email_notifications' => '1'
];

// Merge with defaults
$settings = array_merge($default_settings, $settings);

// Format functions
function format_date($date, $format_setting) {
    $formats = [
        'Y-m-d' => 'Y-m-d',
        'd/m/Y' => 'd/m/Y',
        'm/d/Y' => 'm/d/Y',
        'd-M-Y' => 'j-M-Y'
    ];
    $format = $formats[$format_setting] ?? 'Y-m-d';
    return date($format, strtotime($date));
}

function format_currency($amount, $symbol) {
    return $symbol . number_format($amount, 2);
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_invoice'])) {
        // Add new invoice
        $stmt = $conn->prepare("
            INSERT INTO billing (
                patient_id, invoice_date, due_date, total_amount, 
                paid_amount, status, payment_method, payment_details, 
                notes, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $status = 'pending';
        $created_by = $_SESSION['user_id'];
        $paid_amount = 0;
        
        $stmt->bind_param(
            "issddssssi",
            $_POST['patient_id'],
            $_POST['invoice_date'],
            $_POST['due_date'],
            $_POST['total_amount'],
            $paid_amount,
            $status,
            $_POST['payment_method'],
            $_POST['payment_details'],
            $_POST['notes'],
            $created_by
        );
        
        if ($stmt->execute()) {
            $invoice_id = $conn->insert_id;
            
            // Add invoice items
            foreach ($_POST['items'] as $item) {
                if (!empty($item['description'])) {
                    $stmt = $conn->prepare("
                        INSERT INTO billing_items (
                            invoice_id, description, quantity, unit_price
                        ) VALUES (?, ?, ?, ?)
                    ");
                    
                    $stmt->bind_param(
                        "isid",
                        $invoice_id,
                        $item['description'],
                        $item['quantity'],
                        $item['unit_price']
                    );
                    
                    $stmt->execute();
                }
            }
            
            $_SESSION['message'] = "Invoice created successfully!";
            header("Location: billing.php");
            exit();
        } else {
            $error = "Error creating invoice: " . $conn->error;
        }
    } elseif (isset($_POST['update_payment'])) {
        // Update payment status
        $stmt = $conn->prepare("
            UPDATE billing SET 
                paid_amount = ?,
                status = ?,
                payment_method = ?,
                payment_details = ?
            WHERE invoice_id = ?
        ");
        
        // Determine status based on payment
        $new_status = $_POST['paid_amount'] >= $_POST['total_amount'] ? 'paid' : 
                     ($_POST['paid_amount'] > 0 ? 'partial' : 'pending');
        
        $stmt->bind_param(
            "dsssi",
            $_POST['paid_amount'],
            $new_status,
            $_POST['payment_method'],
            $_POST['payment_details'],
            $_POST['invoice_id']
        );
        
        if ($stmt->execute()) {
            $_SESSION['message'] = "Payment updated successfully!";
            header("Location: billing.php");
            exit();
        } else {
            $error = "Error updating payment: " . $conn->error;
        }
    }
}

// Handle delete action
if (isset($_GET['delete'])) {
    $invoice_id = intval($_GET['delete']);
    
    // First delete items
    $conn->query("DELETE FROM billing_items WHERE invoice_id = $invoice_id");
    
    // Then delete invoice
    $stmt = $conn->prepare("DELETE FROM billing WHERE invoice_id = ?");
    $stmt->bind_param("i", $invoice_id);
    
    if ($stmt->execute()) {
        $_SESSION['message'] = "Invoice deleted successfully!";
        header("Location: billing.php");
        exit();
    } else {
        $error = "Error deleting invoice: " . $conn->error;
    }
}

// Display messages
if (isset($_SESSION['message'])) {
    echo '<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
        <span class="block sm:inline">' . $_SESSION['message'] . '</span>
    </div>';
    unset($_SESSION['message']);
}

if (isset($error)) {
    echo '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
        <span class="block sm:inline">' . $error . '</span>
    </div>';
}

// Check if we're adding or viewing an invoice
$adding = isset($_GET['add']);
$viewing = isset($_GET['view']);

$invoice = null;
$invoice_items = [];
$patient = null;

if ($viewing) {
    $invoice_id = intval($_GET['view']);
    
    // Get invoice
    $stmt = $conn->prepare("
        SELECT b.*, p.first_name, p.last_name, p.phone, p.email
        FROM billing b
        JOIN patients p ON b.patient_id = p.patient_id
        WHERE b.invoice_id = ?
    ");
    $stmt->bind_param("i", $invoice_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $invoice = $result->fetch_assoc();
    
    if (!$invoice) {
        $_SESSION['error'] = "Invoice not found!";
        header("Location: billing.php");
        exit();
    }
    
    // Get invoice items
    $invoice_items = $conn->query("
        SELECT * FROM billing_items 
        WHERE invoice_id = $invoice_id
    ");
}
?>

<?php if ($adding): ?>
<!-- Invoice Form -->
<div class="bg-white rounded-lg shadow p-6 mb-6">
    <h3 class="font-semibold text-lg mb-4">Create New Invoice</h3>
    
    <form method="POST" action="">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2" for="patient_id">Patient</label>
                <select class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                    id="patient_id" name="patient_id" required>
                    <option value="">Select Patient</option>
                    <?php
                    $patients = $conn->query("SELECT patient_id, first_name, last_name FROM patients ORDER BY last_name, first_name");
                    while ($patient = $patients->fetch_assoc()):
                    ?>
                    <option value="<?php echo $patient['patient_id']; ?>">
                        <?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?>
                    </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2" for="invoice_date">Invoice Date</label>
                <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                    id="invoice_date" name="invoice_date" type="date" value="<?php echo date('Y-m-d'); ?>" required>
            </div>
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2" for="due_date">Due Date</label>
                <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                    id="due_date" name="due_date" type="date" value="<?php echo date('Y-m-d', strtotime('+7 days')); ?>" required>
            </div>
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2" for="payment_method">Payment Method</label>
                <select class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                    id="payment_method" name="payment_method">
                    <option value="">Select Payment Method</option>
                    <option value="cash">Cash</option>
                    <option value="credit_card">Credit Card</option>
                    <option value="mobile_money">Mobile Money</option>
                    <option value="bank_transfer">Bank Transfer</option>
                    <option value="insurance">Insurance</option>
                </select>
            </div>
            <div class="md:col-span-2">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="notes">Notes</label>
                <textarea class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                    id="notes" name="notes" placeholder="Additional notes" rows="3"></textarea>
            </div>
        </div>
        
        <div class="mt-6">
            <h4 class="font-medium text-gray-900 mb-3">Invoice Items</h4>
            
            <div id="invoice-items">
                <div class="grid grid-cols-12 gap-4 mb-4">
                    <div class="col-span-6 md:col-span-5">
                        <label class="block text-gray-700 text-sm font-bold mb-2">Description</label>
                    </div>
                    <div class="col-span-3 md:col-span-2">
                        <label class="block text-gray-700 text-sm font-bold mb-2">Quantity</label>
                    </div>
                    <div class="col-span-3 md:col-span-2">
                        <label class="block text-gray-700 text-sm font-bold mb-2">Unit Price</label>
                    </div>
                    <div class="col-span-3 md:col-span-2">
                        <label class="block text-gray-700 text-sm font-bold mb-2">Amount</label>
                    </div>
                </div>
                
                <!-- Item template (hidden) -->
                <div class="grid grid-cols-12 gap-4 mb-4 item-template hidden">
                    <div class="col-span-6 md:col-span-5">
                        <input type="text" name="items[0][description]" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline item-description">
                    </div>
                    <div class="col-span-3 md:col-span-2">
                        <input type="number" name="items[0][quantity]" value="1" min="1" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline item-quantity">
                    </div>
                    <div class="col-span-3 md:col-span-2">
                        <input type="number" step="0.01" name="items[0][unit_price]" min="0" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline item-unit-price">
                    </div>
                    <div class="col-span-2 md:col-span-2 flex items-center">
                        <span class="item-amount">0.00</span>
                        <button type="button" class="ml-2 text-red-500 remove-item">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
                
                <!-- Initial item -->
                <div class="grid grid-cols-12 gap-4 mb-4 item-row">
                    <div class="col-span-6 md:col-span-5">
                        <input type="text" name="items[1][description]" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline item-description">
                    </div>
                    <div class="col-span-3 md:col-span-2">
                        <input type="number" name="items[1][quantity]" value="1" min="1" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline item-quantity">
                    </div>
                    <div class="col-span-3 md:col-span-2">
                        <input type="number" step="0.01" name="items[1][unit_price]" min="0" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline item-unit-price">
                    </div>
                    <div class="col-span-2 md:col-span-2 flex items-center">
                        <span class="item-amount">0.00</span>
                        <button type="button" class="ml-2 text-red-500 remove-item">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            </div>
            
            <button type="button" id="add-item" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                <i class="fas fa-plus"></i> Add Item
            </button>
            
            <div class="mt-4 flex justify-end">
                <div class="text-right">
                    <div class="text-gray-700 mb-2">Subtotal: <span id="subtotal">0.00</span></div>
                    <div class="text-xl font-bold">Total: <span id="total">0.00</span></div>
                    <input type="hidden" name="total_amount" id="total_amount" value="0">
                </div>
            </div>
        </div>
        
        <div class="mt-6 flex justify-end space-x-4">
            <a href="billing.php" class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                Cancel
            </a>
            <button type="submit" name="add_invoice" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                Create Invoice
            </button>
        </div>
    </form>
</div>

<script>
    $(document).ready(function() {
        let itemCount = 1;
        
        // Add new item
        $('#add-item').click(function() {
            itemCount++;
            const newItem = $('.item-template').clone();
            newItem.removeClass('item-template hidden').addClass('item-row');
            
            // Update names and IDs
            newItem.find('[name]').each(function() {
                const name = $(this).attr('name').replace('[0]', '[' + itemCount + ']');
                $(this).attr('name', name);
            });
            
            $('#invoice-items').append(newItem);
            initItemEvents(newItem);
        });
        
        // Remove item
        $(document).on('click', '.remove-item', function() {
            if ($('.item-row').length > 1) {
                $(this).closest('.item-row').remove();
                calculateTotal();
            }
        });
        
        // Calculate amount when quantity or price changes
        $(document).on('input', '.item-quantity, .item-unit-price', function() {
            const row = $(this).closest('.item-row');
            const quantity = parseFloat(row.find('.item-quantity').val()) || 0;
            const unitPrice = parseFloat(row.find('.item-unit-price').val()) || 0;
            const amount = (quantity * unitPrice).toFixed(2);
            row.find('.item-amount').text(amount);
            calculateTotal();
        });
        
        // Initialize events for existing items
        $('.item-row').each(function() {
            initItemEvents($(this));
        });
        
        function initItemEvents(row) {
            const quantity = parseFloat(row.find('.item-quantity').val()) || 0;
            const unitPrice = parseFloat(row.find('.item-unit-price').val()) || 0;
            const amount = (quantity * unitPrice).toFixed(2);
            row.find('.item-amount').text(amount);
        }
        
        function calculateTotal() {
            let subtotal = 0;
            
            $('.item-row').each(function() {
                const amount = parseFloat($(this).find('.item-amount').text()) || 0;
                subtotal += amount;
            });
            
            $('#subtotal').text(subtotal.toFixed(2));
            $('#total').text(subtotal.toFixed(2));
            $('#total_amount').val(subtotal.toFixed(2));
        }
    });
</script>
<?php elseif ($viewing): ?>
<!-- Invoice View -->
<div class="bg-white rounded-lg shadow p-6 mb-6">
    <div class="flex justify-between items-start mb-6">
        <div>
            <h3 class="font-semibold text-lg">Invoice #INV-<?php echo str_pad($invoice['invoice_id'], 5, '0', STR_PAD_LEFT); ?></h3>
            <div class="text-sm text-gray-600">
                Date: <?php echo format_date($invoice['invoice_date'], $settings['date_format']); ?><br>
                Due: <?php echo format_date($invoice['due_date'], $settings['date_format']); ?>
            </div>
        </div>
        <div class="text-right">
            <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full 
                <?php 
                switch($invoice['status']) {
                    case 'pending': echo 'bg-yellow-100 text-yellow-800'; break;
                    case 'partial': echo 'bg-blue-100 text-blue-800'; break;
                    case 'paid': echo 'bg-green-100 text-green-800'; break;
                    case 'overdue': echo 'bg-red-100 text-red-800'; break;
                    case 'canceled': echo 'bg-gray-100 text-gray-800'; break;
                }
                ?>
            ">
                <?php echo ucfirst($invoice['status']); ?>
            </span>
        </div>
    </div>
    
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
        <div>
            <h4 class="font-medium text-gray-900 mb-2">Hospital Information</h4>
            <div class="text-sm text-gray-600">
                <?php echo htmlspecialchars($settings['hospital_name']); ?><br>
                <?php echo htmlspecialchars($settings['hospital_address']); ?><br>
                Phone: <?php echo htmlspecialchars($settings['hospital_phone']); ?><br>
                Email: <?php echo htmlspecialchars($settings['hospital_email']); ?>
            </div>
        </div>
        <div>
            <h4 class="font-medium text-gray-900 mb-2">Billed To</h4>
            <div class="text-sm text-gray-600">
                <?php echo htmlspecialchars($invoice['first_name'] . ' ' . $invoice['last_name']); ?><br>
                Phone: <?php echo htmlspecialchars($invoice['phone']); ?><br>
                Email: <?php echo htmlspecialchars($invoice['email']); ?>
            </div>
        </div>
    </div>
    
    <div class="overflow-x-auto mb-6">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quantity</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Unit Price</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php while ($item = $invoice_items->fetch_assoc()): ?>
                <tr>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($item['description']); ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo $item['quantity']; ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo format_currency($item['unit_price'], $settings['currency_symbol']); ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo format_currency($item['unit_price'] * $item['quantity'], $settings['currency_symbol']); ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    
    <div class="flex justify-end">
        <div class="w-full md:w-1/3">
            <div class="flex justify-between py-2 border-b">
                <span class="font-medium">Subtotal:</span>
                <span><?php echo format_currency($invoice['total_amount'], $settings['currency_symbol']); ?></span>
            </div>
            <div class="flex justify-between py-2 border-b">
                <span class="font-medium">Paid Amount:</span>
                <span><?php echo format_currency($invoice['paid_amount'], $settings['currency_symbol']); ?></span>
            </div>
            <div class="flex justify-between py-2 font-bold text-lg">
                <span>Balance Due:</span>
                <span><?php echo format_currency($invoice['total_amount'] - $invoice['paid_amount'], $settings['currency_symbol']); ?></span>
            </div>
        </div>
    </div>
    
    <!-- Payment Form -->
    <div class="mt-8 pt-6 border-t">
        <h4 class="font-medium text-gray-900 mb-4">Record Payment</h4>
        
        <form method="POST" action="">
            <input type="hidden" name="invoice_id" value="<?php echo $invoice['invoice_id']; ?>">
            <input type="hidden" name="total_amount" value="<?php echo $invoice['total_amount']; ?>">
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="paid_amount">Amount Paid</label>
                    <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                        id="paid_amount" name="paid_amount" type="number" step="0.01" min="0" max="<?php echo $invoice['total_amount'] - $invoice['paid_amount']; ?>" 
                        value="<?php echo $invoice['total_amount'] - $invoice['paid_amount']; ?>" required>
                </div>
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="payment_method">Payment Method</label>
                    <select class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                        id="payment_method" name="payment_method" required>
                        <option value="cash" <?php echo $invoice['payment_method'] == 'cash' ? 'selected' : ''; ?>>Cash</option>
                        <option value="credit_card" <?php echo $invoice['payment_method'] == 'credit_card' ? 'selected' : ''; ?>>Credit Card</option>
                        <option value="mobile_money" <?php echo $invoice['payment_method'] == 'mobile_money' ? 'selected' : ''; ?>>Mobile Money</option>
                        <option value="bank_transfer" <?php echo $invoice['payment_method'] == 'bank_transfer' ? 'selected' : ''; ?>>Bank Transfer</option>
                        <option value="insurance" <?php echo $invoice['payment_method'] == 'insurance' ? 'selected' : ''; ?>>Insurance</option>
                    </select>
                </div>
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="payment_details">Payment Details</label>
                    <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                        id="payment_details" name="payment_details" type="text" placeholder="Reference/Details" 
                        value="<?php echo htmlspecialchars($invoice['payment_details']); ?>">
                </div>
            </div>
            
            <div class="mt-6 flex justify-end space-x-4">
                <a href="billing.php" class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                    Back to Invoices
                </a>
                <button type="submit" name="update_payment" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                    Record Payment
                </button>
            </div>
        </form>
    </div>
</div>
<?php else: ?>
<!-- Billing List -->
<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="p-4 border-b flex justify-between items-center">
        <h3 class="font-semibold">Invoices</h3>
        <div class="flex space-x-2">
            <a href="billing.php?add" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                <i class="fas fa-plus"></i> New Invoice
            </a>
        </div>
    </div>
    
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Invoice #</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Patient</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Paid</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php
                $invoices = $conn->query("
                    SELECT b.*, p.first_name, p.last_name
                    FROM billing b
                    JOIN patients p ON b.patient_id = p.patient_id
                    ORDER BY b.invoice_date DESC
                ");
                
                while ($inv = $invoices->fetch_assoc()):
                    $balance = $inv['total_amount'] - $inv['paid_amount'];
                    
                    // Status badge color
                    $status_class = '';
                    switch ($inv['status']) {
                        case 'pending': $status_class = 'bg-yellow-100 text-yellow-800'; break;
                        case 'partial': $status_class = 'bg-blue-100 text-blue-800'; break;
                        case 'paid': $status_class = 'bg-green-100 text-green-800'; break;
                        case 'overdue': $status_class = 'bg-red-100 text-red-800'; break;
                        case 'canceled': $status_class = 'bg-gray-100 text-gray-800'; break;
                    }
                ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                        INV-<?php echo str_pad($inv['invoice_id'], 5, '0', STR_PAD_LEFT); ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($inv['first_name'] . ' ' . $inv['last_name']); ?></div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        <?php echo format_date($inv['invoice_date'], $settings['date_format']); ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        <?php echo format_currency($inv['total_amount'], $settings['currency_symbol']); ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        <?php echo format_currency($inv['paid_amount'], $settings['currency_symbol']); ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $status_class; ?>">
                            <?php echo ucfirst($inv['status']); ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                        <a href="billing.php?view=<?php echo $inv['invoice_id']; ?>" class="text-blue-600 hover:text-blue-900 mr-3">View</a>
                        <a href="billing.php?delete=<?php echo $inv['invoice_id']; ?>" class="text-red-600 hover:text-red-900" onclick="return confirm('Are you sure you want to delete this invoice?');">Delete</a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>