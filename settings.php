 
<?php
$page_title = "System Settings";
require_once 'includes/header.php';

// Check if user is admin
if ($user['role'] != 'admin') {
    $_SESSION['error'] = "You don't have permission to access this page.";
    header("Location: index.php");
    exit();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_settings'])) {
        // Update settings
        foreach ($_POST['settings'] as $key => $value) {
            $stmt = $conn->prepare("
                INSERT INTO system_settings (setting_key, setting_value) 
                VALUES (?, ?)
                ON DUPLICATE KEY UPDATE setting_value = ?
            ");
            $stmt->bind_param("sss", $key, $value, $value);
            $stmt->execute();
        }
        
        $_SESSION['message'] = "Settings updated successfully!";
        header("Location: settings.php");
        exit();
    }
}

// Display messages
if (isset($_SESSION['message'])) {
    echo '<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
        <span class="block sm:inline">' . $_SESSION['message'] . '</span>
    </div>';
    unset($_SESSION['message']);
}

if (isset($_SESSION['error'])) {
    echo '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
        <span class="block sm:inline">' . $_SESSION['error'] . '</span>
    </div>';
    unset($_SESSION['error']);
}

// Get current settings
$settings_result = $conn->query("SELECT * FROM system_settings");
$settings = [];
while ($row = $settings_result->fetch_assoc()) {
    $settings[$row['setting_key'] = $row['setting_value']];
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
?>

<div class="bg-white rounded-lg shadow p-6">
    <h3 class="font-semibold text-lg mb-4">System Settings</h3>
    
    <form method="POST" action="">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="md:col-span-2">
                <h4 class="font-medium text-gray-900 mb-3 border-b pb-2">Hospital Information</h4>
            </div>
            
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2" for="hospital_name">Hospital Name</label>
                <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                    id="hospital_name" name="settings[hospital_name]" type="text" 
                    value="<?php echo htmlspecialchars($settings['hospital_name']); ?>" required>
            </div>
            
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2" for="hospital_phone">Hospital Phone</label>
                <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                    id="hospital_phone" name="settings[hospital_phone]" type="text" 
                    value="<?php echo htmlspecialchars($settings['hospital_phone']); ?>" required>
            </div>
            
            <div class="md:col-span-2">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="hospital_address">Hospital Address</label>
                <textarea class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                    id="hospital_address" name="settings[hospital_address]" rows="3"><?php echo htmlspecialchars($settings['hospital_address']); ?></textarea>
            </div>
            
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2" for="hospital_email">Hospital Email</label>
                <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                    id="hospital_email" name="settings[hospital_email]" type="email" 
                    value="<?php echo htmlspecialchars($settings['hospital_email']); ?>" required>
            </div>
            
            <div class="md:col-span-2">
                <h4 class="font-medium text-gray-900 mb-3 border-b pb-2">System Configuration</h4>
            </div>
            
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2" for="currency_symbol">Currency Symbol</label>
                <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                    id="currency_symbol" name="settings[currency_symbol]" type="text" maxlength="3" 
                    value="<?php echo htmlspecialchars($settings['currency_symbol']); ?>" required>
            </div>
            
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2" for="date_format">Date Format</label>
                <select class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                    id="date_format" name="settings[date_format]" required>
                    <option value="Y-m-d" <?php echo $settings['date_format'] == 'Y-m-d' ? 'selected' : ''; ?>>YYYY-MM-DD (2023-12-31)</option>
                    <option value="d/m/Y" <?php echo $settings['date_format'] == 'd/m/Y' ? 'selected' : ''; ?>>DD/MM/YYYY (31/12/2023)</option>
                    <option value="m/d/Y" <?php echo $settings['date_format'] == 'm/d/Y' ? 'selected' : ''; ?>>MM/DD/YYYY (12/31/2023)</option>
                    <option value="d-M-Y" <?php echo $settings['date_format'] == 'd-M-Y' ? 'selected' : ''; ?>>DD-Mon-YYYY (31-Dec-2023)</option>
                </select>
            </div>
            
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2" for="time_format">Time Format</label>
                <select class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                    id="time_format" name="settings[time_format]" required>
                    <option value="H:i" <?php echo $settings['time_format'] == 'H:i' ? 'selected' : ''; ?>>24-hour (14:30)</option>
                    <option value="h:i A" <?php echo $settings['time_format'] == 'h:i A' ? 'selected' : ''; ?>>12-hour (2:30 PM)</option>
                </select>
            </div>
            
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2" for="records_per_page">Records Per Page</label>
                <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                    id="records_per_page" name="settings[records_per_page]" type="number" min="5" max="100" 
                    value="<?php echo htmlspecialchars($settings['records_per_page']); ?>" required>
            </div>
            
            <div class="md:col-span-2">
                <h4 class="font-medium text-gray-900 mb-3 border-b pb-2">Notification Settings</h4>
            </div>
            
            <div>
                <label class="flex items-center space-x-3">
                    <input type="checkbox" name="settings[enable_sms_notifications]" value="1" 
                        class="form-checkbox h-5 w-5 text-blue-600" 
                        <?php echo $settings['enable_sms_notifications'] == '1' ? 'checked' : ''; ?>>
                    <span class="text-gray-700">Enable SMS Notifications</span>
                </label>
            </div>
            
            <div>
                <label class="flex items-center space-x-3">
                    <input type="checkbox" name="settings[enable_email_notifications]" value="1" 
                        class="form-checkbox h-5 w-5 text-blue-600" 
                        <?php echo $settings['enable_email_notifications'] == '1' ? 'checked' : ''; ?>>
                    <span class="text-gray-700">Enable Email Notifications</span>
                </label>
            </div>
        </div>
        
        <div class="mt-6 flex justify-end">
            <button type="submit" name="update_settings" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                Save Settings
            </button>
        </div>
    </form>
</div>

<?php require_once 'includes/footer.php'; ?>