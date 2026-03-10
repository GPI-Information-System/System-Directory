<?php
/**
 * G-Portal Login Page
 * Handles user authentication and redirects based on login status
 */

require_once 'config/session.php';
require_once 'config/database.php';

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

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password';
    } else {
        $conn = getDBConnection();
        $stmt = $conn->prepare("SELECT id, username, password, role FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id']  = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role']     = $user['role'];
                header('Location: pages/dashboard.php');
                exit();
            } else {
                $error = 'Invalid username or password';
            }
        } else {
            $error = 'Invalid username or password';
        }

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
<div class="login-split">

    <!-- ===============================
         LEFT PANEL
         =============================== -->
    <div class="login-left">

        <!-- Top bar: Brand name only -->
        <div class="brand-top-bar">
            <span class="brand-name">G-Portal</span>
        </div>

        <!-- Centre content -->
        <div class="left-content">           
            <h1 class="left-heading">
                Monitor & <br>
                <span>Manage all Glory's systems</span><br>
                in one place.
            </h1>

            <p class="left-description">
                G-Portal lets you monitor, manage, and track all internal systems in real time.
            </p>

            <div class="feature-list">
                <div class="feature-item">
                    <div class="feature-icon">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                    </div>
                    Real-time system status monitoring
                </div>
                <div class="feature-item">
                    <div class="feature-icon">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                    </div>
                    Maintenance scheduling & alerts
                </div>
                <div class="feature-item">
                    <div class="feature-icon">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg>
                    </div>
                    Analytics & uptime reporting
                </div>
            </div>
        </div>

        <!-- Bottom spacer to keep layout balanced -->
        <div></div>

    </div>

    <!-- ===============================
         RIGHT PANEL
         =============================== -->
    <div class="login-right">
        <div class="login-form-wrapper">

            <a href="pages/viewer.php" class="viewer-link viewer-link--right">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
                View Systems Directory
            </a>

            <div class="form-header">
                <h2>G-Portal Login</h2>
                <div class="form-divider"></div>
            </div>

            <?php if ($error): ?>
            <div class="alert-error">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                <?php echo htmlspecialchars($error); ?>
            </div>
            <?php endif; ?>

            <form method="POST" action="">

                <div class="form-group">
                    <label for="username">Username</label>
                    <div class="input-wrap">
                        <input
                            type="text"
                            id="username"
                            name="username"
                            required
                            autofocus
                            placeholder="Enter your username"
                            value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                        >
                        <svg class="input-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                    </div>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-wrap">
                        <input
                            type="password"
                            id="password"
                            name="password"
                            required
                            placeholder="Enter your password"
                        >
                        <svg class="input-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                    </div>
                </div>

                <button type="submit" class="btn-signin">
                    Sign In
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
                </button>

            </form>

            <div class="form-footer">
                G-Portal &nbsp;·&nbsp; All rights reserved.
            </div>

        </div>
    </div>

</div>
</body>
</html>