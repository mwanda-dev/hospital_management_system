<div class="sidebar bg-white w-64 md:w-72 shadow-lg fixed h-full overflow-y-auto transform -translate-x-full md:translate-x-0 transition-transform duration-300 ease-in-out z-20">
    <div class="p-4 flex items-center justify-between border-b">
        <div class="flex items-center space-x-3">
            <div class="bg-blue-500 p-2 rounded-lg">
                <i class="fas fa-hospital text-white text-2xl"></i>
            </div>
            <h1 class="text-xl font-bold text-gray-800">MediCare HMS</h1>
        </div>
        <button id="sidebarClose" class="text-gray-500 hover:text-gray-700 md:hidden">
            <i class="fas fa-times"></i>
        </button>
    </div>
    
    <div class="p-4">
        <div class="flex items-center space-x-3 mb-6">
            <img src="https://randomuser.me/api/portraits/lego/5.jpg" alt="User" class="w-10 h-10 rounded-full">
            <div>
                <p class="font-medium"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></p>
                <p class="text-xs text-gray-500"><?php echo ucfirst($user['role']); ?></p>
            </div>
        </div>
        
        <nav>
            <h3 class="text-xs uppercase text-gray-500 font-bold mb-3">Main Menu</h3>
            <ul class="space-y-2">
                <li>
                    <a href="index.php" class="flex items-center space-x-3 p-2 rounded-lg <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active-nav-item' : 'hover:bg-blue-50'; ?>">
                        <i class="fas fa-tachometer-alt w-5 text-center"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li>
                    <a href="patients.php" class="flex items-center space-x-3 p-2 rounded-lg <?php echo basename($_SERVER['PHP_SELF']) == 'patients.php' ? 'active-nav-item' : 'hover:bg-blue-50'; ?>">
                        <i class="fas fa-user-plus w-5 text-center"></i>
                        <span>Patient Management</span>
                    </a>
                </li>
                <li>
                    <a href="appointments.php" class="flex items-center space-x-3 p-2 rounded-lg <?php echo basename($_SERVER['PHP_SELF']) == 'appointments.php' ? 'active-nav-item' : 'hover:bg-blue-50'; ?>">
                        <i class="fas fa-calendar-check w-5 text-center"></i>
                        <span>Appointments</span>
                    </a>
                </li>
                <li>
                    <a href="medical_records.php" class="flex items-center space-x-3 p-2 rounded-lg <?php echo basename($_SERVER['PHP_SELF']) == 'medical_records.php' ? 'active-nav-item' : 'hover:bg-blue-50'; ?>">
                        <i class="fas fa-file-medical w-5 text-center"></i>
                        <span>Patient Records</span>
                    </a>
                </li>
                <li>
                    <a href="inventory.php" class="flex items-center space-x-3 p-2 rounded-lg <?php echo basename($_SERVER['PHP_SELF']) == 'inventory.php' ? 'active-nav-item' : 'hover:bg-blue-50'; ?>">
                        <i class="fas fa-pills w-5 text-center"></i>
                        <span>Medication</span>
                    </a>
                </li>
                <li>
                    <a href="lab_tests.php" class="flex items-center space-x-3 p-2 rounded-lg <?php echo basename($_SERVER['PHP_SELF']) == 'lab_tests.php' ? 'active-nav-item' : 'hover:bg-blue-50'; ?>">
                        <i class="fas fa-flask w-5 text-center"></i>
                        <span>Lab Tests</span>
                    </a>
                </li>
                <li>
                    <a href="wards.php" class="flex items-center space-x-3 p-2 rounded-lg <?php echo basename($_SERVER['PHP_SELF']) == 'wards.php' ? 'active-nav-item' : 'hover:bg-blue-50'; ?>">
                        <i class="fas fa-procedures w-5 text-center"></i>
                        <span>Ward Management</span>
                    </a>
                </li>
                <li>
                    <a href="billing.php" class="flex items-center space-x-3 p-2 rounded-lg <?php echo basename($_SERVER['PHP_SELF']) == 'billing.php' ? 'active-nav-item' : 'hover:bg-blue-50'; ?>">
                        <i class="fas fa-file-invoice-dollar w-5 text-center"></i>
                        <span>Billing & Payments</span>
                    </a>
                </li>
                <li>
                    <a href="reports.php" class="flex items-center space-x-3 p-2 rounded-lg <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active-nav-item' : 'hover:bg-blue-50'; ?>">
                        <i class="fas fa-chart-line w-5 text-center"></i>
                        <span>Reports</span>
                    </a>
                </li>
            </ul>
            
            <h3 class="text-xs uppercase text-gray-500 font-bold mt-8 mb-3">System</h3>
            <ul class="space-y-2">
                <?php if ($user['role'] == 'admin'): ?>
                <li>
                    <a href="users.php" class="flex items-center space-x-3 p-2 rounded-lg <?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active-nav-item' : 'hover:bg-blue-50'; ?>">
                        <i class="fas fa-users-cog w-5 text-center"></i>
                        <span>User Management</span>
                    </a>
                </li>
                <?php endif; ?>
                <li>
                    <a href="settings.php" class="flex items-center space-x-3 p-2 rounded-lg <?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active-nav-item' : 'hover:bg-blue-50'; ?>">
                        <i class="fas fa-cog w-5 text-center"></i>
                        <span>Settings</span>
                    </a>
                </li>
            </ul>
        </nav>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const sidebar = document.querySelector('.sidebar');
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebarClose = document.getElementById('sidebarClose');
        
        // Show sidebar when toggle button is clicked
        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', function() {
                sidebar.classList.remove('-translate-x-full');
                document.body.style.overflow = 'hidden';
            });
        }
        
        // Hide sidebar when close button is clicked
        if (sidebarClose) {
            sidebarClose.addEventListener('click', function() {
                sidebar.classList.add('-translate-x-full');
                document.body.style.overflow = 'auto';
            });
        }
        
        // Hide sidebar when clicking outside of it on mobile
        document.addEventListener('click', function(event) {
            if (window.innerWidth < 768) {
                const isClickInsideSidebar = sidebar.contains(event.target);
                const isClickOnToggle = event.target === sidebarToggle || sidebarToggle.contains(event.target);
                
                if (!isClickInsideSidebar && !isClickOnToggle) {
                    sidebar.classList.add('-translate-x-full');
                    document.body.style.overflow = 'auto';
                }
            }
        });
    });
</script>