
<?php
/**
 * G-Portal Login Page
 * Handles user authentication and redirects based on login status
 * 
 * Flow:
 * 1. If already logged in → Redirect to dashboard
 * 2. If accessing directly (no POST/GET) → Redirect to public viewer
 * 3. If accessing via login link (?show=login) → Show login form
 * 4. If submitting credentials (POST) → Authenticate and redirect
 */



require_once 'config/session.php';
require_once 'config/database.php';

// ============================================================
// REDIRECT LOGIC
// ============================================================

// Already logged in? Go straight to dashboard
if (isLoggedIn()) {
    header('Location: pages/dashboard.php');
    exit();
}

// Accessing directly without login intent? Send to public viewer
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && !isset($_GET['show'])) {
    header('Location: pages/viewer.php');
    exit();
}

// ============================================================
// AUTHENTICATION LOGIC
// ============================================================

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get and sanitize input
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Validate input
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password';
    } else {
        // Authenticate user
        $conn = getDBConnection();
        $stmt = $conn->prepare("SELECT id, username, password, role FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            // Verify password
            if (password_verify($password, $user['password'])) {
                // Authentication successful - Set session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                
                // Redirect to dashboard
                header('Location: pages/dashboard.php');
                exit();
            } else {
                $error = 'Invalid username or password';
            }
        } else {
            $error = 'Invalid username or password';
        }
        
        // Clean up
        $stmt->close();
        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - G-Portal</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <!-- Header -->
            <div class="login-header">
                <h1>G-Portal</h1>
                <p>System Directory</p>
            </div>
            
            <!-- Error Message -->
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <!-- Login Form -->
            <form method="POST" action="">
                <!-- Username Field -->
                <div class="form-group">
                    <label for="username">Username</label>
                    <input 
                        type="text" 
                        id="username" 
                        name="username" 
                        required 
                        autofocus
                        placeholder="Enter your username"
                    >
                </div>
                
                <!-- Password Field -->
                <div class="form-group">
                    <label for="password">Password</label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        required
                        placeholder="Enter your password"
                    >
                </div>
                
                <!-- Submit Button -->
                <button type="submit" class="btn btn-primary">
                    Sign In
                </button>
            </form>
            
            <!-- Demo Accounts Info -->
            <div class="demo-accounts">
                <h4>Demo Accounts</h4>
                <p><strong>Super Admin:</strong> superadmin / admin123</p>
                <p><strong>Admin:</strong> admin / admin123</p>
            </div>
        </div>
    </div>
</body>
</html>