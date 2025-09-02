<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

// Redirect if already logged in
if (isAuthenticated()) {
    if (isPatient()) {
        header("Location: ../patients/patientportal.php");
    } else {
        header("Location: ../index.php");
    }
    exit();
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    // Validate credentials
    $user = authenticateUser($username, $password);
    
    if ($user) {
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['user_type'] = $user['user_type'];
        
        if ($user['user_type'] === 'patient') {
            header("Location: ../patients/patientportal.php");
        } else {
            header("Location: ../index.php");
        }
        exit();
    } else {
        $error = "Invalid username or password!";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MediCare HMS - Login</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --secondary: #10b981;
            --secondary-dark: #059669;
            --accent: #8b5cf6;
            --light-bg: #f8fafc;
        }
        
        body {
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
        }
        
        .card:hover {
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.12);
            transform: translateY(-5px);
        }
        
        .input-field {
            transition: all 0.3s;
            border: 2px solid #e2e8f0;
        }
        
        .input-field:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            transition: all 0.3s;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, var(--primary-dark) 0%, #1e40af 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(37, 99, 235, 0.3);
        }
        
        .btn-secondary {
            background: linear-gradient(135deg, var(--secondary) 0%, var(--secondary-dark) 100%);
            transition: all 0.3s;
        }
        
        .btn-secondary:hover {
            background: linear-gradient(135deg, var(--secondary-dark) 0%, #047857 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(16, 185, 129, 0.3);
        }
        
        .floating-label {
            position: relative;
            margin-bottom: 20px;
        }
        
        .floating-input {
            width: 100%;
            padding: 16px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            background: #fff;
            font-size: 16px;
            transition: all 0.3s;
        }
        
        .floating-input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        
        .floating-label-text {
            position: absolute;
            top: 18px;
            left: 16px;
            color: #94a3b8;
            background: #fff;
            padding: 0 5px;
            transition: all 0.3s;
            pointer-events: none;
        }
        
        .floating-input:focus ~ .floating-label-text,
        .floating-input:not(:placeholder-shown) ~ .floating-label-text {
            top: -10px;
            left: 12px;
            font-size: 12px;
            color: var(--primary);
            font-weight: 600;
        }
        
        .toggle-password {
            position: absolute;
            right: 15px;
            top: 16px;
            cursor: pointer;
            color: #94a3b8;
        }
        
        .divider {
            display: flex;
            align-items: center;
            text-align: center;
            color: #94a3b8;
            font-size: 14px;
            margin: 20px 0;
        }
        
        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .divider::before {
            margin-right: 10px;
        }
        
        .divider::after {
            margin-left: 10px;
        }
        
        .feature-card {
            transition: all 0.3s;
            border-left: 4px solid transparent;
        }
        
        .feature-card:hover {
            border-left-color: var(--primary);
            transform: translateX(5px);
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .animate-fade-in {
            animation: fadeIn 0.6s ease-out forwards;
        }
        
        .delay-100 { animation-delay: 0.1s; }
        .delay-200 { animation-delay: 0.2s; }
        .delay-300 { animation-delay: 0.3s; }
        
        .hospital-icon {
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            border-radius: 16px;
            padding: 12px;
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-4xl flex flex-col md:flex-row gap-6">
        <!-- Left Column - Login Form -->
        <div class="w-full md:w-1/2">
            <div class="card p-8 animate-fade-in">
                <div class="flex items-center justify-center mb-8">
                    <div class="hospital-icon mr-4">
                        <i class="fas fa-hospital text-white text-2xl"></i>
                    </div>
                    <h1 class="text-2xl font-bold text-gray-800">MediCare HMS</h1>
                </div>
                
                <h2 class="text-3xl font-bold text-gray-800 mb-2">Welcome back</h2>
                <p class="text-gray-600 mb-8">Sign in to access your account</p>
                
                <?php if (isset($error)): ?>
                <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6 rounded" role="alert">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-exclamation-circle text-red-500"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm text-red-700"><?php echo $error; ?></p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="floating-label mb-6">
                        <input class="floating-input" id="username" name="username" type="text" placeholder=" " 
                               value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" required>
                        <label class="floating-label-text" for="username">Username, Email or Phone</label>
                        <div class="absolute right-3 top-4 text-gray-400">
                            <i class="fas fa-user"></i>
                        </div>
                    </div>
                    
                    <div class="floating-label mb-6">
                        <input class="floating-input" id="password" name="password" type="password" placeholder=" " required>
                        <label class="floating-label-text" for="password">Password</label>
                        <div class="toggle-password" onclick="togglePassword()">
                            <i class="fas fa-eye" id="eye-icon"></i>
                        </div>
                    </div>
                    
                    <div class="flex items-center justify-between mb-6">
                        <label class="flex items-center">
                            <input type="checkbox" class="form-checkbox h-4 w-4 text-blue-600">
                            <span class="ml-2 text-sm text-gray-600">Remember me</span>
                        </label>
                        
                        <a href="#" class="text-sm text-blue-600 hover:text-blue-800 transition-colors">
                            Forgot password?
                        </a>
                    </div>
                    
                    <button class="btn-primary text-white font-bold py-3 px-4 rounded-xl w-full focus:outline-none focus:shadow-outline mb-6" type="submit">
                        Sign In
                    </button>
                </form>

            </div>
            
            <!-- Demo Credentials -->
            <div class="card p-6 mt-6 animate-fade-in delay-200">
                <h3 class="font-bold text-gray-700 mb-3 flex items-center">
                    <i class="fas fa-key mr-2 text-blue-500"></i>Demo Credentials
                </h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="bg-blue-50 p-4 rounded-lg">
                        <h4 class="font-semibold text-blue-800 mb-2">Staff Account</h4>
                        <p class="text-sm text-blue-600">Username: <span class="font-mono">admin</span></p>
                        <p class="text-sm text-blue-600">Password: <span class="font-mono">password</span></p>
                    </div>
                    
                    <div class="bg-green-50 p-4 rounded-lg">
                        <h4 class="font-semibold text-green-800 mb-2">Patient Account</h4>
                        <p class="text-sm text-green-600">Username: Email or Phone</p>
                        <p class="text-sm text-green-600">Password: Phone number</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Right Column - Information -->
        <div class="w-full md:w-1/2">
            <!-- Patient Portal Card -->
            <div class="card p-8 bg-gradient-to-br from-green-50 to-cyan-50 border-l-4 border-green-500 mb-6 animate-fade-in delay-100">
                <div class="flex items-start mb-6">
                    <div class="bg-green-100 p-3 rounded-full mr-4">
                        <i class="fas fa-user-injured text-green-600 text-xl"></i>
                    </div>
                    <div>
                        <h2 class="text-2xl font-bold text-gray-800">Patient Portal</h2>
                        <p class="text-gray-600">Access your medical records and appointments</p>
                    </div>
                </div>
                
                <ul class="space-y-3 mb-6">
                    <li class="flex items-center">
                        <i class="fas fa-check-circle text-green-500 mr-3"></i>
                        <span class="text-gray-700">View test results</span>
                    </li>
                    <li class="flex items-center">
                        <i class="fas fa-check-circle text-green-500 mr-3"></i>
                        <span class="text-gray-700">Schedule appointments</span>
                    </li>
                    <li class="flex items-center">
                        <i class="fas fa-check-circle text-green-500 mr-3"></i>
                        <span class="text-gray-700">Message your doctor</span>
                    </li>
                </ul>
                
                <div class="bg-white p-4 rounded-lg border border-green-200">
                    <p class="text-sm font-semibold text-green-800 mb-2">Login Instructions:</p>
                    <p class="text-sm text-green-700">Use your registered email or phone as username</p>
                    <p class="text-sm text-green-700">Use your phone number as password</p>
                </div>
            </div>
            
            <!-- Staff Portal Card -->
            <div class="card p-8 bg-gradient-to-br from-blue-50 to-indigo-50 border-l-4 border-blue-500 mb-6 animate-fade-in delay-200">
                <div class="flex items-start mb-6">
                    <div class="bg-blue-100 p-3 rounded-full mr-4">
                        <i class="fas fa-user-md text-blue-600 text-xl"></i>
                    </div>
                    <div>
                        <h2 class="text-2xl font-bold text-gray-800">Staff Portal</h2>
                        <p class="text-gray-600">Access the healthcare management system</p>
                    </div>
                </div>
                
                <ul class="space-y-3 mb-6">
                    <li class="flex items-center">
                        <i class="fas fa-check-circle text-blue-500 mr-3"></i>
                        <span class="text-gray-700">Manage patient records</span>
                    </li>
                    <li class="flex items-center">
                        <i class="fas fa-check-circle text-blue-500 mr-3"></i>
                        <span class="text-gray-700">Schedule appointments</span>
                    </li>
                    <li class="flex items-center">
                        <i class="fas fa-check-circle text-blue-500 mr-3"></i>
                        <span class="text-gray-700">Generate reports</span>
                    </li>
                </ul>
                
                <a href="#" class="inline-flex items-center text-blue-600 hover:text-blue-800 font-medium transition-colors">
                    <i class="fas fa-info-circle mr-2"></i>Staff Support Center
                </a>
            </div>
            
            <!-- Support Card -->
            <div class="card p-6 animate-fade-in delay-300">
                <h3 class="font-bold text-gray-700 mb-4 flex items-center">
                    <i class="fas fa-life-ring mr-2 text-purple-500"></i>Need Assistance?
                </h3>
                
                <div class="bg-purple-50 p-4 rounded-lg mb-4">
                    <p class="text-sm text-purple-700">Contact our support team for help with login issues</p>
                    <p class="text-sm font-medium text-purple-800 mt-2">
                        <i class="fas fa-envelope mr-2"></i>support@medicare-hms.com
                    </p>
                    <p class="text-sm font-medium text-purple-800">
                        <i class="fas fa-phone-alt mr-2"></i>(555) 123-HELP
                    </p>
                </div>
                
                <p class="text-xs text-gray-500 text-center">Available Monday - Friday, 8:00 AM - 6:00 PM EST</p>
            </div>
        </div>
    </div>

    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const eyeIcon = document.getElementById('eye-icon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                eyeIcon.classList.remove('fa-eye');
                eyeIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                eyeIcon.classList.remove('fa-eye-slash');
                eyeIcon.classList.add('fa-eye');
            }
        }
        
        // Add animation on load
        document.addEventListener('DOMContentLoaded', function() {
            document.body.classList.add('loaded');
        });
    </script>
</body>
</html>
