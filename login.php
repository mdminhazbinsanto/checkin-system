<?php
session_start();
// CSRF token check
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('Invalid CSRF token');
    }
}
// Initialize lockout tracking
if (!isset($_SESSION['failed_attempts'])) {
    $_SESSION['failed_attempts'] = 0;
    $_SESSION['lockout_time'] = 0;
}
function is_locked_out() {
    return $_SESSION['failed_attempts'] >= 5 && time() - $_SESSION['lockout_time'] < 900; // 15 min lockout
}
if (is_locked_out()) {
    die('Account locked due to multiple failed login attempts. Try again later.');
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username'], $_POST['password'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];
    // TODO: Replace with real DB lookup
    $stored_hash = password_hash('examplePassword123!', PASSWORD_DEFAULT);
    // Password complexity check
    $has_upper = preg_match('/[A-Z]/', $password);
    $has_lower = preg_match('/[a-z]/', $password);
    $has_digit = preg_match('/[0-9]/', $password);
    $has_special = preg_match('/[\W]/', $password);
    $is_long = strlen($password) >= 12;
    if (!($has_upper && $has_lower && $has_digit && $has_special && $is_long)) {
        die('Password does not meet complexity requirements.');
    }
    if (password_verify($password, $stored_hash)) {
        // Successful login: reset counters, regenerate session ID
        $_SESSION['failed_attempts'] = 0;
        session_regenerate_id(true);
        echo 'Login successful';
    } else {
        $_SESSION['failed_attempts']++;
        if ($_SESSION['failed_attempts'] >= 5) {
            $_SESSION['lockout_time'] = time();
        }
        die('Invalid credentials');
    }
}
// Generate CSRF token for form
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
?>
<!DOCTYPE html>
<html>
<head><title>Login</title></head>
<body>
<form method="POST" action="login.php">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>" />
    Username: <input type="text" name="username" required /><br/>
    Password: <input type="password" name="password" required /><br/>
    <button type="submit">Login</button>
</form>
</body>
</html>
