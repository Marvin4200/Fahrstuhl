<?php
/**
 * Premium API Handler (AJAX endpoint)
 * Proxies premium operations to the bot API at port 3002
 */

require_once __DIR__ . '/../includes/config.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

header('Content-Type: application/json');

$action = $_GET['action'] ?? null;
$data = json_decode(file_get_contents('php://input'), true) ?? [];
$userId = trim((string)($data['userId'] ?? ''));
$days = max(1, (int)($data['days'] ?? 30));
$tier = trim((string)($data['tier'] ?? 'pro'));
$allowedTiers = ['basic', 'pro'];
if (!in_array($tier, $allowedTiers, true)) $tier = 'pro';

if (!$userId) {
    http_response_code(400);
    echo json_encode(['error' => 'User ID required']);
    exit;
}
if (!preg_match('/^\d{17,20}$/', $userId)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid user ID format']);
    exit;
}

switch ($action) {
    // The API decides the resulting expiry now, so always report the value it
    // sends back rather than recomputing it here — the two used to drift apart.
    case 'extend':
        // mode 'extend': the API adds these days ON TOP of any remaining time.
        // This used to read the current expiry and pre-sum it, which double-counted
        // the remainder once the API started extending by itself (100 days left +
        // 30 bought came out as 230).
        $result = api('/premium/activate', 'POST', [
            'userId' => $userId, 'daysValid' => $days, 'tier' => $tier, 'mode' => 'extend',
        ]);
        $payload   = $result['data']['data'] ?? [];
        $newExpiry = !empty($payload['expiresAt']) ? date('Y-m-d', strtotime($payload['expiresAt'])) : null;
        echo json_encode([
            'success' => $result['data']['success'] ?? false,
            'message' => "Premium um $days Tage verlängert",
            'userId' => $userId,
            'newExpiresAt' => $newExpiry,
            'tier' => $tier,
        ]);
        break;

    case 'renew':
    case 'activate':
        // mode 'set': an absolute term of exactly $days from now, which is what
        // "activate for N days" means for an admin — not a top-up.
        $result = api('/premium/activate', 'POST', [
            'userId' => $userId, 'daysValid' => $days, 'tier' => $tier, 'mode' => 'set',
        ]);
        $payload   = $result['data']['data'] ?? [];
        $newExpiry = !empty($payload['expiresAt']) ? date('Y-m-d', strtotime($payload['expiresAt'])) : null;
        echo json_encode([
            'success' => $result['data']['success'] ?? false,
            'message' => "Premium für $days Tage gesetzt",
            'userId' => $userId,
            'newExpiresAt' => $newExpiry,
            'expiresAt' => $newExpiry,
            'daysValid' => $days,
            'tier' => $tier,
        ]);
        break;
    case 'deactivate':
        $result = api('/premium/deactivate', 'POST', ['userId' => $userId]);
        echo json_encode([
            'success' => $result['data']['success'] ?? false,
            'message' => 'Premium deactivated',
            'userId' => $userId,
        ]);
        break;
    case 'status':
        $result = getAPI('/premium/user/' . urlencode($userId));
        $user = $result['data']['user'] ?? null;
        echo json_encode([
            'success' => $user !== null,
            'userId' => $userId,
            'premium' => $user,
        ]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Unknown action']);
        break;
}

