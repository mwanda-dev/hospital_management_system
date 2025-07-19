 
<?php
$page_title = "Inventory Management";
require_once 'includes/header.php';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_item'])) {
        // Add new inventory item
        $stmt = $conn->prepare("
            INSERT INTO inventory (
                item_name, item_type, description, quantity_in_stock, 
                unit_of_measure, reorder_level, cost_per_unit, 
                selling_price, supplier, last_restocked
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $last_restocked = !empty($_POST['last_restocked']) ? $_POST['last_restocked'] : null;
        
        $stmt->bind_param(
            "sssisssdds",
            $_POST['item_name'],
            $_POST['item_type'],
            $_POST['description'],
            $_POST['quantity_in_stock'],
            $_POST['unit_of_measure'],
            $_POST['reorder_level'],
            $_POST['cost_per_unit'],
            $_POST['selling_price'],
            $_POST['supplier'],
            $last_restocked
        );
        
        if ($stmt->execute()) {
            $_SESSION['message'] = "Inventory item added successfully!";
            header("Location: inventory.php");
            exit();
        } else {
            $error = "Error adding inventory item: " . $conn->error;
        }
    } elseif (isset($_POST['update_item'])) {
        // Update inventory item
        $stmt = $conn->prepare("
            UPDATE inventory SET 
                item_name = ?,
                item_type = ?,
                description = ?,
                quantity_in_stock = ?,
                unit_of_measure = ?,
                reorder_level = ?,
                cost_per_unit = ?,
                selling_price = ?,
                supplier = ?,
                last_restocked = ?
            WHERE item_id = ?
        ");
        
        $last_restocked = !empty($_POST['last_restocked']) ? $_POST['last_restocked'] : null;
        
        $stmt->bind_param(
            "sssisssddsi",
            $_POST['item_name'],
            $_POST['item_type'],
            $_POST['description'],
            $_POST['quantity_in_stock'],
            $_POST['unit_of_measure'],
            $_POST['reorder_level'],
            $_POST['cost_per_unit'],
            $_POST['selling_price'],
            $_POST['supplier'],
            $last_restocked,
            $_POST['item_id']
        );
        
        if ($stmt->execute()) {
            $_SESSION['message'] = "Inventory item updated successfully!";
            header("Location: inventory.php");
            exit();
        } else {
            $error = "Error updating inventory item: " . $conn->error;
        }
    }
}

// Handle delete action
if (isset($_GET['delete'])) {
    $item_id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM inventory WHERE item_id = ?");
    $stmt->bind_param("i", $item_id);
    
    if ($stmt->execute()) {
        $_SESSION['message'] = "Inventory item deleted successfully!";
        header("Location: inventory.php");
        exit();
    } else {
        $error = "Error deleting inventory item: " . $conn->error;
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

// Check if we're adding or editing an item
$editing = false;
$item = null;

if (isset($_GET['edit'])) {
    $editing = true;
    $item_id = intval($_GET['edit']);
    $stmt = $conn->prepare("SELECT * FROM inventory WHERE item_id = ?");
    $stmt->bind_param("i", $item_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $item = $result->fetch_assoc();
} elseif (isset($_GET['add'])) {
    $editing = true;
}
?>

<?php if ($editing): ?>
<!-- Inventory Item Form -->
<div class="bg-white rounded-lg shadow p-6 mb-6">
    <h3 class="font-semibold text-lg mb-4"><?php echo isset($_GET['edit']) ? 'Edit Inventory Item' : 'Add New Inventory Item'; ?></h3>
    
    <form method="POST" action="">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2" for="item_name">Item Name</label>
                <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                    id="item_name" name="item_name" type="text" placeholder="Item name" 
                    value="<?php echo htmlspecialchars($item['item_name'] ?? ''); ?>" required>
            </div>
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2" for="item_type">Item Type</label>
                <select class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                    id="item_type" name="item_type" required>
                    <option value="medication" <?php echo (isset($item['item_type']) && $item['item_type'] == 'medication') ? 'selected' : ''; ?>>Medication</option>
                    <option value="supply" <?php echo (isset($item['item_type']) && $item['item_type'] == 'supply') ? 'selected' : ''; ?>>Supply</option>
                    <option value="equipment" <?php echo (isset($item['item_type']) && $item['item_type'] == 'equipment') ? 'selected' : ''; ?>>Equipment</option>
                </select>
            </div>
            <div class="md:col-span-2">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="description">Description</label>
                <textarea class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                    id="description" name="description" placeholder="Item description" rows="3"><?php echo htmlspecialchars($item['description'] ?? ''); ?></textarea>
            </div>
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2" for="quantity_in_stock">Quantity in Stock</label>
                <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                    id="quantity_in_stock" name="quantity_in_stock" type="number" min="0" placeholder="Quantity" 
                    value="<?php echo htmlspecialchars($item['quantity_in_stock'] ?? 0); ?>" required>
            </div>
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2" for="unit_of_measure">Unit of Measure</label>
                <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                    id="unit_of_measure" name="unit_of_measure" type="text" placeholder="e.g., tablets, ml, boxes" 
                    value="<?php echo htmlspecialchars($item['unit_of_measure'] ?? ''); ?>">
            </div>
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2" for="reorder_level">Reorder Level</label>
                <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                    id="reorder_level" name="reorder_level" type="number" min="0" placeholder="Reorder level" 
                    value="<?php echo htmlspecialchars($item['reorder_level'] ?? 10); ?>">
            </div>
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2" for="cost_per_unit">Cost per Unit</label>
                <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                    id="cost_per_unit" name="cost_per_unit" type="number" step="0.01" min="0" placeholder="0.00" 
                    value="<?php echo htmlspecialchars($item['cost_per_unit'] ?? ''); ?>">
            </div>
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2" for="selling_price">Selling Price</label>
                <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                    id="selling_price" name="selling_price" type="number" step="0.01" min="0" placeholder="0.00" 
                    value="<?php echo htmlspecialchars($item['selling_price'] ?? ''); ?>">
            </div>
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2" for="supplier">Supplier</label>
                <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                    id="supplier" name="supplier" type="text" placeholder="Supplier name" 
                    value="<?php echo htmlspecialchars($item['supplier'] ?? ''); ?>">
            </div>
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2" for="last_restocked">Last Restocked</label>
                <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                    id="last_restocked" name="last_restocked" type="date" 
                    value="<?php echo htmlspecialchars($item['last_restocked'] ?? ''); ?>">
            </div>
        </div>
        
        <div class="mt-6 flex justify-end space-x-4">
            <a href="inventory.php" class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                Cancel
            </a>
            <?php if (isset($_GET['edit'])): ?>
                <input type="hidden" name="item_id" value="<?php echo $item['item_id']; ?>">
                <button type="submit" name="update_item" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                    Update Item
                </button>
            <?php else: ?>
                <button type="submit" name="add_item" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                    Add Item
                </button>
            <?php endif; ?>
        </div>
    </form>
</div>
<?php else: ?>
<!-- Inventory List -->
<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="p-4 border-b flex justify-between items-center">
        <h3 class="font-semibold">Inventory Items</h3>
        <div class="flex space-x-2">
            <a href="inventory.php?add" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                <i class="fas fa-plus"></i> Add Item
            </a>
        </div>
    </div>
    
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Item</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quantity</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cost</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Price</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php
                $items = $conn->query("
                    SELECT * FROM inventory 
                    ORDER BY item_name
                ");
                
                while ($item = $items->fetch_assoc()):
                    // Determine status
                    $status_class = '';
                    $status_text = '';
                    if ($item['quantity_in_stock'] <= 0) {
                        $status_class = 'bg-red-100 text-red-800';
                        $status_text = 'Out of Stock';
                    } elseif ($item['quantity_in_stock'] <= $item['reorder_level']) {
                        $status_class = 'bg-yellow-100 text-yellow-800';
                        $status_text = 'Low Stock';
                    } else {
                        $status_class = 'bg-green-100 text-green-800';
                        $status_text = 'In Stock';
                    }
                ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($item['item_name']); ?></div>
                        <div class="text-sm text-gray-500"><?php echo htmlspecialchars($item['description']); ?></div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        <?php echo ucfirst($item['item_type']); ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        <?php echo $item['quantity_in_stock']; ?> <?php echo htmlspecialchars($item['unit_of_measure']); ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        $<?php echo number_format($item['cost_per_unit'], 2); ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        $<?php echo number_format($item['selling_price'], 2); ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $status_class; ?>">
                            <?php echo $status_text; ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                        <a href="inventory.php?edit=<?php echo $item['item_id']; ?>" class="text-blue-600 hover:text-blue-900 mr-3">Edit</a>
                        <a href="inventory.php?delete=<?php echo $item['item_id']; ?>" class="text-red-600 hover:text-red-900" onclick="return confirm('Are you sure you want to delete this item?');">Delete</a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>