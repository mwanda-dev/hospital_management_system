<?php
require_once 'config.php';
checkAuth();

// Get user data
$user_id = $_SESSION['user_id'];
$user_query = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$user_query->bind_param("i", $user_id);
$user_query->execute();
$user_result = $user_query->get_result();
$user = $user_result->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MediCare - Hospital Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .sidebar {
            transition: all 0.3s;
        }
        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }
        .active-nav-item {
            background-color: #3b82f6;
            color: white;
        }
        .active-nav-item:hover {
            background-color: #2563eb;
        }
        @media (max-width: 767px) {
            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body class="bg-gray-100 font-sans">
    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar -->
        <?php include 'includes/sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="flex-1 overflow-y-auto main-content md:ml-64 lg:ml-72">
            <!-- Top Navigation -->
            <header class="bg-white shadow-sm p-4 flex justify-between items-center sticky top-0 z-10">
                <div class="flex items-center space-x-4">
                    <button id="sidebarToggle" class="text-gray-600 focus:outline-none md:hidden">
                        <i class="fas fa-bars"></i>
                    </button>
                    <h2 class="text-xl font-semibold text-gray-800"><?php echo isset($page_title) ? $page_title : 'Dashboard'; ?></h2>
                </div>
                
                <div class="flex items-center space-x-4">
                    <div class="relative">
                        <button class="text-gray-600 focus:outline-none">
                            <i class="fas fa-bell"></i>
                            <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-4 w-4 flex items-center justify-center">3</span>
                        </button>
                    </div>
                    <div class="relative">
                        <button id="userMenuButton" class="flex items-center space-x-2 focus:outline-none">
                            <img src="https://randomuser.me/api/portraits/lego/5.jpg" alt="User" class="w-8 h-8 rounded-full">
                            <span class="hidden md:inline"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></span>
                            <i class="fas fa-chevron-down text-xs"></i>
                        </button>
                        <div id="userMenu" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 z-20">
                            <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-blue-50">Profile</a>
                            <a href="settings.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-blue-50">Settings</a>
                            <a href="includes/logout.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-blue-50">Logout</a>
                        </div>
                    </div>
                </div>
            </header>
            
            <!-- Main Content Area -->
            <main class="p-6">