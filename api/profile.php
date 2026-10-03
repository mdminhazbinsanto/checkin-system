<?php
/**
 * Profile API — Change password, update preferences (lang, timezone)
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/lang.php';
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => t('session_expired')]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$action = $_POST['action'] ?? '';
$userId = $_SESSION['user_id'];
$db = getDB();

if (!$db) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => t('error_db')]);
    exit;
}

switch ($action) {
    case 'update_password':
        $currentPass = $_POST['current_pass'] ?? '';
        $newPass = $_POST['new_pass'] ?? '';
        $confirmPass = $_POST['confirm_pass'] ?? '';

        if (empty($currentPass) || empty($newPass) || empty($confirmPass)) {
            echo json_encode(['success' => false, 'message' => 'All password fields are required']);
            exit;
        }

        if ($newPass !== $confirmPass) {
            echo json_encode(['success' => false, 'message' => 'New password and confirmation do not match']);
            exit;
        }

        if (strlen($newPass) < 6) {
            echo json_encode(['success' => false, 'message' => 'New password must be at least 6 characters']);
            exit;
        }

        // Verify current password
        // Use a prepared statement with integer cast for user ID to prevent injection
$stmt = $db->prepare("SELECT password FROM users WHERE id = ?");
$stmt->bindValue(1, (int)$userId, PDO::PARAM_INT);
$stmt->execute();
        
        $user = $stmt->fetch();

        if (!$user || !password_verify($currentPass, $user['password'])) {
            echo json_encode(['success' => false, 'message' => 'Current password is incorrect']);
            exit;
        }

        // Update password
        $hashedPassword = password_hash($newPass, PASSWORD_BCRYPT);
        $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$hashedPassword, $userId]);

        echo json_encode(['success' => true, 'message' => 'Password updated successfully']);
        break;

    case 'update_preferences':
        $lang = $_POST['lang'] ?? '';
        $timezone = $_POST['timezone'] ?? '';

        // Validate language
        $allowedLangs = ['en', 'it'];
        if (!in_array($lang, $allowedLangs)) {
            echo json_encode(['success' => false, 'message' => 'Invalid language']);
            exit;
        }

        // Validate timezone
        if (!in_array($timezone, DateTimeZone::listIdentifiers())) {
            echo json_encode(['success' => false, 'message' => 'Invalid timezone']);
            exit;
        }

        // Update database
        $stmt = $db->prepare("UPDATE users SET lang = ?, timezone = ? WHERE id = ?");
        $stmt->execute([$lang, $timezone, $userId]);

        // Update session
        $_SESSION['lang'] = $lang;
        $_SESSION['timezone'] = $timezone;
        date_default_timezone_set($timezone);

        echo json_encode(['success' => true, 'message' => 'Preferences updated successfully']);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
        break;
}
