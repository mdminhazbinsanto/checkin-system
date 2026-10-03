<?php
/**
 * Messages API
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/lang.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => t('session_expired')]);
    exit;
}

$userId = $_SESSION['user_id'];
$isAdmin = isAdmin();
$db = getDB();

if (!$db) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => t('error_db')]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

// GET requests
if ($method === 'GET') {
    $action = $_GET['action'] ?? '';

    switch ($action) {
        case 'inbox':
            $page = max(1, intval($_GET['page'] ?? 1));
            $limit = 20;
            $offset = ($page - 1) * $limit;

            $stmt = $db->prepare("
                SELECT m.*, u.name as sender_name
                FROM messages m
                JOIN users u ON m.sender_id = u.id
                WHERE m.receiver_id = ?
                ORDER BY m.created_at DESC
                LIMIT ? OFFSET ?
            ");
            $stmt->execute([$userId, $limit, $offset]);
            $messages = $stmt->fetchAll();

            echo json_encode(['success' => true, 'messages' => $messages]);
            break;

        case 'sent':
            $stmt = $db->prepare("
                SELECT m.*, u.name as receiver_name
                FROM messages m
                JOIN users u ON m.receiver_id = u.id
                WHERE m.sender_id = ?
                ORDER BY m.created_at DESC
            ");
            $stmt->execute([$userId]);
            $messages = $stmt->fetchAll();

            echo json_encode(['success' => true, 'messages' => $messages]);
            break;

        case 'message':
            $messageId = intval($_GET['id'] ?? 0);

            $stmt = $db->prepare("
                SELECT m.*,
                    s.name as sender_name,
                    r.name as receiver_name
                FROM messages m
                JOIN users s ON m.sender_id = s.id
                JOIN users r ON m.receiver_id = r.id
                WHERE m.id = ? AND (m.sender_id = ? OR m.receiver_id = ?)
            ");
            $stmt->execute([$messageId, $userId, $userId]);
            $message = $stmt->fetch();

            if (!$message) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Message not found']);
                exit;
            }

            // Mark as read if receiver is viewing
            if ($message['receiver_id'] == $userId && !$message['is_read']) {
                $stmt = $db->prepare("UPDATE messages SET is_read = 1 WHERE id = ?");
                $stmt->execute([$messageId]);
                $message['is_read'] = 1;
            }

            echo json_encode(['success' => true, 'message' => $message]);
            break;

        case 'get_unread_count':
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM messages WHERE receiver_id = ? AND is_read = 0");
            $stmt->execute([$userId]);
            $result = $stmt->fetch();

            echo json_encode(['success' => true, 'count' => intval($result['count'])]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Unknown action']);
    }
    exit;
}

// POST requests
if ($method === 'POST') {
    // CSRF protection: Verify token for all POST actions
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verifyCSRFToken($csrfToken)) {
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        exit;
    }
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'send':
            $receiverId = intval($_POST['receiver_id'] ?? 0);
            $subject = trim($_POST['subject'] ?? '');
            $body = trim($_POST['body'] ?? '');

            // Validate
            if (empty($subject) || empty($body)) {
                echo json_encode(['success' => false, 'message' => 'Subject and body are required']);
                exit;
            }

            if (strlen($subject) > 255) {
                echo json_encode(['success' => false, 'message' => 'Subject too long']);
                exit;
            }

            // If not admin, force receiver to be admin
            if (!$isAdmin) {
                $stmt = $db->prepare("SELECT id FROM users WHERE role = 'admin' AND status = 1 LIMIT 1");
                $stmt->execute();
                $admin = $stmt->fetch();
                if (!$admin) {
                    echo json_encode(['success' => false, 'message' => 'No admin found']);
                    exit;
                }
                $receiverId = $admin['id'];
            } else {
                // Admin sending - validate receiver exists
                if ($receiverId <= 0) {
                    echo json_encode(['success' => false, 'message' => 'Invalid receiver']);
                    exit;
                }

                $stmt = $db->prepare("SELECT id FROM users WHERE id = ? AND status = 1");
                $stmt->execute([$receiverId]);
                if (!$stmt->fetch()) {
                    echo json_encode(['success' => false, 'message' => 'Receiver not found']);
                    exit;
                }
            }

            // Prevent sending to self
            if ($receiverId == $userId) {
                echo json_encode(['success' => false, 'message' => 'Cannot send message to yourself']);
                exit;
            }

            // Insert message
            $stmt = $db->prepare("INSERT INTO messages (sender_id, receiver_id, subject, body) VALUES (?, ?, ?, ?)");
            $stmt->execute([$userId, $receiverId, sanitizeInput($subject), sanitizeInput($body)]);

            echo json_encode(['success' => true, 'message' => t('message_sent')]);
            break;

        case 'mark_read':
            $messageId = intval($_POST['message_id'] ?? 0);

            // Verify receiver is current user
            $stmt = $db->prepare("SELECT id FROM messages WHERE id = ? AND receiver_id = ?");
            $stmt->execute([$messageId, $userId]);
            if (!$stmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Message not found or unauthorized']);
                exit;
            }

            $stmt = $db->prepare("UPDATE messages SET is_read = 1 WHERE id = ?");
            $stmt->execute([$messageId]);

            echo json_encode(['success' => true, 'message' => 'Message marked as read']);
            break;

        case 'delete':
            $messageId = intval($_POST['message_id'] ?? 0);

            // Verify sender or receiver is current user
            $stmt = $db->prepare("SELECT id FROM messages WHERE id = ? AND (sender_id = ? OR receiver_id = ?)");
            $stmt->execute([$messageId, $userId, $userId]);
            if (!$stmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Message not found or unauthorized']);
                exit;
            }

            $stmt = $db->prepare("DELETE FROM messages WHERE id = ?");
            $stmt->execute([$messageId]);

            echo json_encode(['success' => true, 'message' => 'Message deleted']);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Unknown action']);
    }
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed']);
