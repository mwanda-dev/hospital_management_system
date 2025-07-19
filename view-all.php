<?php
$page_title = "All Activities";
require_once 'includes/header.php';

// Define the time_elapsed_string function if not already defined
if (!function_exists('time_elapsed_string')) {
    function time_elapsed_string($datetime, $full = false) {
        $now = new DateTime;
        $ago = new DateTime($datetime);
        $diff = $now->diff($ago);

        $units = [
            'y' => $diff->y,
            'm' => $diff->m,
            'd' => $diff->d,
            'h' => $diff->h,
            'i' => $diff->i,
            's' => $diff->s,
        ];

        if (!$full && $units['d'] >= 7) {
            $weeks = floor($units['d'] / 7);
            $units['w'] = $weeks;
            $units['d'] = $units['d'] % 7;
        }

        $labels = [
            'y' => 'year',
            'm' => 'month',
            'w' => 'week',
            'd' => 'day',
            'h' => 'hour',
            'i' => 'minute',
            's' => 'second',
        ];

        $parts = [];
        foreach ($units as $unit => $value) {
            if ($value > 0 && isset($labels[$unit])) {
                $parts[] = $value . ' ' . $labels[$unit] . ($value > 1 ? 's' : '');
            }
        }

        if (!$full) {
            $parts = array_slice($parts, 0, 1);
        }

        return $parts ? implode(', ', $parts) . ' ago' : 'just now';
    }
}

// Pagination variables
$per_page = 15;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $per_page;

// Get total count of activities
$total_activities = $conn->query("
    SELECT COUNT(*) as total FROM (
        SELECT 'patient' as type, first_name, last_name, registration_date as date 
        FROM patients
        
        UNION ALL
        
        SELECT 'appointment' as type, p.first_name, p.last_name, a.created_at as date
        FROM appointments a
        JOIN patients p ON a.patient_id = p.patient_id
        
        UNION ALL
        
        SELECT 'billing' as type, p.first_name, p.last_name, b.created_at as date
        FROM billing b
        JOIN patients p ON b.patient_id = p.patient_id
        
        UNION ALL
        
        SELECT 'admission' as type, p.first_name, p.last_name, ad.admission_date as date
        FROM admissions ad
        JOIN patients p ON ad.patient_id = p.patient_id
        
        UNION ALL
        
        SELECT 'payment' as type, p.first_name, p.last_name, b.updated_at as date
        FROM billing b
        JOIN patients p ON b.patient_id = p.patient_id
        WHERE b.status = 'paid'
    ) as combined_activities
")->fetch_assoc()['total'];

$total_pages = ceil($total_activities / $per_page);

// Get paginated activities
$activities = $conn->query("
    SELECT * FROM (
        SELECT 'patient' as type, first_name, last_name, registration_date as date, 
               CONCAT('Patient registered') as description
        FROM patients
        
        UNION ALL
        
        SELECT 'appointment' as type, p.first_name, p.last_name, a.created_at as date,
               CONCAT('Appointment scheduled with Dr. ', u.last_name, ' on ', DATE_FORMAT(a.appointment_date, '%M %e, %Y')) as description
        FROM appointments a
        JOIN patients p ON a.patient_id = p.patient_id
        JOIN users u ON a.doctor_id = u.user_id
        
        UNION ALL
        
        SELECT 'billing' as type, p.first_name, p.last_name, b.created_at as date,
               CONCAT('Bill created for $', FORMAT(b.total_amount, 2)) as description
        FROM billing b
        JOIN patients p ON b.patient_id = p.patient_id
        
        UNION ALL
        
        SELECT 'admission' as type, p.first_name, p.last_name, ad.admission_date as date,
               CONCAT('Admitted to bed #', (SELECT bed_number FROM beds WHERE bed_id = ad.bed_id)) as description
        FROM admissions ad
        JOIN patients p ON ad.patient_id = p.patient_id
        
        UNION ALL
        
        SELECT 'payment' as type, p.first_name, p.last_name, b.updated_at as date,
               CONCAT('Payment of $', FORMAT(b.paid_amount, 2), ' received') as description
        FROM billing b
        JOIN patients p ON b.patient_id = p.patient_id
        WHERE b.status = 'paid'
    ) as combined_activities
    ORDER BY date DESC
    LIMIT $per_page OFFSET $offset
");
?>

<div class="container mx-auto px-4 py-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800">All Activities</h1>
        <a href="index.php" class="text-blue-500 hover:text-blue-700">
            <i class="fas fa-arrow-left mr-1"></i> Back to Dashboard
        </a>
    </div>

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="p-4 border-b">
            <div class="flex justify-between items-center">
                <h3 class="font-semibold text-lg">System Activities</h3>
                <div class="text-sm text-gray-500">
                    Showing <?php echo ($offset + 1); ?>-<?php echo min($offset + $per_page, $total_activities); ?> of <?php echo number_format($total_activities); ?> records
                </div>
            </div>
        </div>
        
        <div class="divide-y">
            <?php if ($activities->num_rows > 0): ?>
                <?php while ($activity = $activities->fetch_assoc()): 
                    $time_ago = time_elapsed_string($activity['date']);
                    $icon_class = '';
                    $icon = '';
                    
                    switch($activity['type']) {
                        case 'patient':
                            $icon_class = 'bg-blue-100 text-blue-500';
                            $icon = 'fa-user-plus';
                            break;
                        case 'appointment':
                            $icon_class = 'bg-green-100 text-green-500';
                            $icon = 'fa-calendar-check';
                            break;
                        case 'billing':
                            $icon_class = 'bg-yellow-100 text-yellow-500';
                            $icon = 'fa-file-invoice-dollar';
                            break;
                        case 'admission':
                            $icon_class = 'bg-purple-100 text-purple-500';
                            $icon = 'fa-procedures';
                            break;
                        case 'payment':
                            $icon_class = 'bg-green-100 text-green-500';
                            $icon = 'fa-money-bill-wave';
                            break;
                    }
                ?>
                <div class="p-4 hover:bg-gray-50 transition-colors duration-150">
                    <div class="flex items-start space-x-4">
                        <div class="<?php echo $icon_class; ?> p-3 rounded-full flex-shrink-0">
                            <i class="fas <?php echo $icon; ?>"></i>
                        </div>
                        <div class="flex-1">
                            <p class="font-medium"><?php echo htmlspecialchars($activity['first_name'] . ' ' . $activity['last_name']); ?></p>
                            <p class="text-sm text-gray-600"><?php echo htmlspecialchars($activity['description']); ?></p>
                            <p class="text-xs text-gray-500 mt-1"><?php echo $time_ago; ?></p>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="p-8 text-center text-gray-500">
                    No activities found in the system.
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="p-4 border-t flex items-center justify-between">
            <div>
                <?php if ($page > 1): ?>
                    <a href="view-all.php?page=<?php echo $page - 1; ?>" class="inline-flex items-center px-3 py-1 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">
                        <i class="fas fa-chevron-left mr-1"></i> Previous
                    </a>
                <?php endif; ?>
            </div>
            
            <div class="text-sm text-gray-700">
                Page <?php echo $page; ?> of <?php echo $total_pages; ?>
            </div>
            
            <div>
                <?php if ($page < $total_pages): ?>
                    <a href="view-all.php?page=<?php echo $page + 1; ?>" class="inline-flex items-center px-3 py-1 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">
                        Next <i class="fas fa-chevron-right ml-1"></i>
                    </a>
                <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
require_once 'includes/footer.php';
?>