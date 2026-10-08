<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/draft_management.php';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'
    && !validate_csrf_token($_POST['csrf_token'] ?? null)) {
    http_response_code(400);
    exit('Invalid or expired form token. Please reload the page and try again.');
}
require_login();

function getSettingsPath(): string {
    $appData = getenv('APPDATA');
    $baseDir = $appData !== false && trim($appData) !== ''
        ? $appData
        : (getenv('HOME') !== false && trim((string)getenv('HOME')) !== '' ? (string)getenv('HOME') : __DIR__);

    $settingsDir = rtrim($baseDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'CurriculumMatcher';
    if (!is_dir($settingsDir)) {
        @mkdir($settingsDir, 0700, true);
    }

    return $settingsDir . DIRECTORY_SEPARATOR . 'settings.json';
}

function loadSavedApiKey(): string {
    $settingsPath = getSettingsPath();
    if (!is_file($settingsPath)) {
        return '';
    }

    $raw = @file_get_contents($settingsPath);
    if ($raw === false || trim($raw) === '') {
        return '';
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        return '';
    }

    $key = isset($data['api_key']) ? (string)$data['api_key'] : '';
    return trim($key);
}

function saveApiKey(string $newKey): array {
    $settingsPath = getSettingsPath();
    $cleanKey = trim($newKey);
    $payload = ['api_key' => $cleanKey];

    $dir = dirname($settingsPath);
    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }

    $written = @file_put_contents($settingsPath, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    return [
        'success' => $written !== false,
        'path' => $settingsPath,
        'value_present' => $cleanKey !== '',
    ];
}

$dbPath = __DIR__ . '/curriculum_matching.db';
$apiKeyAction = trim((string)($_POST['api_key_action'] ?? ''));
$apiKeyInput = trim((string)($_POST['api_key_value'] ?? ''));
$apiKeyMessage = '';
$apiKeyMessageType = 'info';
$accountMenuOpen = in_array($apiKeyAction, ['save', 'clear'], true);
$currentUser = current_user();
$currentUserApiKey = $currentUser !== null ? get_user_api_key((int)$currentUser['id']) : null;
$apiKeyPolicy = gemini_api_key_policy($currentUser, $currentUserApiKey, has_shared_api_key());
$hasApiKeyAvailable = $apiKeyPolicy['allowed'];

if ($apiKeyAction === 'save') {
    if ($currentUser !== null) {
        $apiKeySaved = save_user_api_key((int)$currentUser['id'], $apiKeyInput);
        $apiKeyMessage = $apiKeySaved ? 'API key saved to your account.' : 'Unable to save API key.';
        $apiKeyMessageType = $apiKeySaved ? 'success' : 'warning';
        $currentUserApiKey = $apiKeySaved ? $apiKeyInput : get_user_api_key((int)$currentUser['id']);
    }
} elseif ($apiKeyAction === 'clear' && $currentUser !== null) {
    $keyCleared = clear_user_api_key((int)$currentUser['id']);
    $currentUserApiKey = get_user_api_key((int)$currentUser['id']);
    $apiKeyMessage = $keyCleared ? 'API key removed from your account.' : 'Unable to remove API key.';
    $apiKeyMessageType = $keyCleared ? 'success' : 'warning';
}

$db = new SQLite3($dbPath);
$db->enableExceptions(true);
draft_management_ensure_columns($db);
$runScope = run_access_sql_scope($currentUser);
$countStmt = $db->prepare(
    "SELECT COUNT(*) AS total,
            SUM(CASE WHEN is_finalized = 1 THEN 1 ELSE 0 END) AS finalized,
            SUM(CASE WHEN source IS NULL OR source <> 'enhanced' THEN 1 ELSE 0 END) AS generated,
            SUM(CASE WHEN source = 'enhanced' THEN 1 ELSE 0 END) AS enhanced
     FROM generated_curriculum_runs r
     WHERE {$runScope['sql']}"
);
run_access_bind_scope($countStmt, $runScope);
$draftCounts = $countStmt->execute()->fetchArray(SQLITE3_ASSOC) ?: [];
$totalDrafts = (int)($draftCounts['total'] ?? 0);
$finalizedDrafts = (int)($draftCounts['finalized'] ?? 0);
$generatedDrafts = (int)($draftCounts['generated'] ?? 0);
$enhancedDrafts = (int)($draftCounts['enhanced'] ?? 0);

$historyRuns = [];
$historyStmt = $db->prepare(
    "SELECT id, program, prompt, status, generated_at, source, user_title, is_finalized
     FROM generated_curriculum_runs r
     WHERE {$runScope['sql']}
     ORDER BY generated_at DESC, id DESC
     LIMIT 12"
);
run_access_bind_scope($historyStmt, $runScope);
$historyResult = $historyStmt->execute();
while ($historyRow = $historyResult->fetchArray(SQLITE3_ASSOC)) {
    $historyRuns[] = $historyRow;
}

$db->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard · Curriculum Enhancer</title>
    <link rel="stylesheet" href="assets/theme.css">
</head>
    <?php $activePage = 'dashboard'; require __DIR__ . '/partials/dashboard_shell_start.php'; ?>
            <div class="dashboard-heading">
                <p class="eyebrow">Overview</p>
                <h1>Dashboard</h1>
            </div>
            <section class="stat-grid" aria-label="Curriculum overview">
                <article class="stat-card"><span class="stat-label">Total drafts</span><strong><?= $totalDrafts ?></strong><span class="stat-caption">Generated and enhanced runs</span></article>
                <article class="stat-card"><span class="stat-label">Finalized</span><strong><?= $finalizedDrafts ?></strong><span class="stat-caption">Your own tracking only</span></article>
                <article class="stat-card"><span class="stat-label">Generated</span><strong><?= $generatedDrafts ?></strong><span class="stat-caption">Generated drafts</span></article>
                <article class="stat-card"><span class="stat-label">Enhanced</span><strong><?= $enhancedDrafts ?></strong><span class="stat-caption">Enhanced drafts</span></article>
            </section>
            <div class="dashboard-panels">
                <section class="dashboard-panel recent-panel">
                    <div class="dashboard-panel-heading"><div><p class="eyebrow">Latest activity</p><h2>Recent drafts</h2></div><a href="draft_history.php">View draft history</a></div>
                    <label for="history-source-filter">Filter by source</label>
                    <select id="history-source-filter"><option value="">All drafts</option><option value="generated">Generated</option><option value="enhanced">Enhanced</option></select>
                    <?php if ($historyRuns): ?>
                        <ul class="recent-drafts">
                            <?php foreach ($historyRuns as $historyRun): ?>
                                <?php
                                    $historySource = ($historyRun['source'] ?? '') === 'enhanced' ? 'enhanced' : 'generated';
                                    $historyHref = $historySource === 'enhanced'
                                        ? 'enhanced_curriculum_generated.php?run_id=' . (int)$historyRun['id'] . '#run-' . (int)$historyRun['id']
                                        : 'generated_curriculum.php?run_id=' . (int)$historyRun['id'] . '#run-' . (int)$historyRun['id'];
                                    $draftStatus = draft_management_status_label($historyRun['is_finalized'] ?? 0);
                                    $statusClass = strtolower($draftStatus);
                                    $historyTitle = trim((string)($historyRun['user_title'] ?? '')) ?: (string)$historyRun['program'];
                                ?>
                                <li data-history-source="<?= htmlspecialchars($historySource, ENT_QUOTES, 'UTF-8') ?>">
                                    <a href="<?= htmlspecialchars($historyHref, ENT_QUOTES, 'UTF-8') ?>">
                                        <span class="source-dot source-<?= htmlspecialchars($historySource, ENT_QUOTES, 'UTF-8') ?>" title="<?= $historySource === 'enhanced' ? 'Enhanced' : 'Generated' ?>" aria-label="<?= $historySource === 'enhanced' ? 'Enhanced' : 'Generated' ?>"></span>
                                        <span class="recent-draft-copy"><strong><?= htmlspecialchars($historyTitle, ENT_QUOTES, 'UTF-8') ?> · Run #<?= (int)$historyRun['id'] ?></strong><small><?= htmlspecialchars((string)$historyRun['prompt']) ?></small></span>
                                        <span class="draft-status"><span class="status-dot status-dot-<?= htmlspecialchars($statusClass, ENT_QUOTES, 'UTF-8') ?>"></span><?= htmlspecialchars($draftStatus) ?></span>
                                        <time><?= htmlspecialchars((string)$historyRun['generated_at']) ?></time>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="empty">No curriculum drafts have been saved yet.</p>
                    <?php endif; ?>
                </section>
            </div>
    <script>
                var historySourceFilter = document.getElementById('history-source-filter');
                if (historySourceFilter) {
                    historySourceFilter.addEventListener('change', function () {
                        document.querySelectorAll('[data-history-source]').forEach(function (item) {
                            item.hidden = historySourceFilter.value !== '' && item.dataset.historySource !== historySourceFilter.value;
                        });
                    });
                }
    </script>
    <?php require __DIR__ . '/partials/dashboard_shell_end.php'; ?>
</body>
</html>
