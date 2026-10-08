<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed.']);
    exit;
}

if (!validate_csrf_token($_POST['csrf_token'] ?? null)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid or expired form token.']);
    exit;
}

if (current_user() !== null || empty($_SESSION['guest_trial_mode'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Guest draft lifecycle is not available.']);
    exit;
}

$tabId = $_POST['tab_id'] ?? null;
if (!is_string($tabId) || !preg_match('/^[a-f0-9]{64}$/', $tabId)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid guest tab identifier.']);
    exit;
}

$draftCleared = claim_guest_draft_tab($tabId);
echo json_encode(['ok' => true, 'draft_cleared' => $draftCleared]);
