<?php
/**
 * Admin Logs API
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/lang.php';

// Set content type
header('Content-Type: application/json');

// Check if user is admin
if (!isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

// Get parameters
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = min(100, max(1, (int)($_GET['per_page'] ?? 20)));
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$userId = $_GET['user_id'] ?? '';
$status = $_GET['status'] ?? '';

$db = getDB();
if (!$db) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => t('error_db')]);
    exit;
}

// Build query
$where = [];
$params = [];

if ($dateFrom) {
    $where[] = "cl.date >= ?";
    $params[] = $dateFrom;
}

if ($dateTo) {
    $where[] = "cl.date <= ?";
    $params[] = $dateTo;
}

if ($userId) {
    $where[] = "cl.user_id = ?";
    $params[] = $userId;
}

if ($status) {
    $where[] = "cl.status = ?";
    $params[] = $status;
}

$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Get total count
$countQuery = "SELECT COUNT(*) as total FROM check_log cl $whereClause";
$stmt = $db->prepare($countQuery);
$stmt->execute($params);
$total = $stmt->fetch()['total'];

// Get logs
$offset = ($page - 1) * $perPage;
$query = "SELECT cl.id, u.name, cl.date,                                       
    TIME(cl.checkin_time) as checkin_time,                                     
    TIME(cl.checkout_time) as checkout_time,                                   
    cl.duration_minutes as duration,                                           
    cl.status, cl.ip_address                                                   
    FROM check_log cl                                                          
    JOIN users u ON cl.user_id = u.id                                          
    $whereClause                                                               
    ORDER BY cl.date DESC, cl.checkin_time DESC                                
    LIMIT :limit OFFSET :offset";

// Clone the $params array so we can add the pagination values without         
// affecting the COUNT query                                                       
$logParams = $params;                                                          
$logParams[':limit']  = (int)$perPage;   // bind as integer                    
$logParams[':offset'] = (int)$offset;    // bind as integer

$stmt = $db->prepare($query);
$stmt->execute($params);
$logs = $stmt->fetchAll();

echo json_encode([
    'success' => true,
    'logs' => $logs,
    'total' => (int)$total,
    'page' => $page,
    'per_page' => $perPage
]);
