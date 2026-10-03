<?php
/**
 * Added comment: This API uses PDO prepared statements for all queries to prevent SQL injection.
 */
/**
 * Users List API (for admin dropdowns)
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit;
}

try {
    $db = getDB();
    if (!$db) {
        throw new Exception('Database connection failed');
    }
    
    $stmt = $db->prepare("SELECT id, name, username FROM users WHERE status = 1 ORDER BY name ASC");
    $stmt->execute();
    $users = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'users' => $users
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
