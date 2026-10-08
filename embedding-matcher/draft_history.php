<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/draft_management.php';
require_login();

$db = new SQLite3(__DIR__ . '/curriculum_matching.db');
$db->enableExceptions(true);
draft_management_ensure_columns($db);
$runScope = run_access_sql_scope(current_user());
$historyStmt = $db->prepare(
    "SELECT id, program, prompt, source, generated_at, user_title, is_finalized
     FROM generated_curriculum_runs r
     WHERE {$runScope['sql']}
     ORDER BY generated_at DESC, id DESC"
);
run_access_bind_scope($historyStmt, $runScope);
$result = $historyStmt->execute();
$drafts = [];
while ($draft = $result->fetchArray(SQLITE3_ASSOC)) {
    $drafts[] = $draft;
}
$db->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Draft History · Curriculum Enhancer</title>
    <link rel="stylesheet" href="assets/theme.css">
</head>
<?php $activePage = 'history'; require __DIR__ . '/partials/dashboard_shell_start.php'; ?>
        <div class="dashboard-heading">
            <p class="eyebrow">Generated and enhanced runs</p>
            <h1>Draft History</h1>
        </div>
        <section class="dashboard-panel recent-panel">
            <label for="draft-history-source">Filter by source</label>
            <select id="draft-history-source">
                <option value="">All drafts</option>
                <option value="generated">Generated</option>
                <option value="enhanced">Enhanced</option>
            </select>
            <?php if ($drafts): ?>
                <ul class="recent-drafts" id="draft-history-list">
                    <?php foreach ($drafts as $draft): ?>
                        <?php
                            $source = ($draft['source'] ?? '') === 'enhanced' ? 'enhanced' : 'generated';
                            $runId = (int)$draft['id'];
                            $href = $source === 'enhanced'
                                ? 'enhanced_curriculum_generated.php?run_id=' . $runId . '#run-' . $runId
                                : 'generated_curriculum.php?run_id=' . $runId . '#run-' . $runId;
                            $title = trim((string)($draft['user_title'] ?? '')) ?: (string)$draft['program'];
                            $status = draft_management_status_label($draft['is_finalized'] ?? 0);
                        ?>
                        <li data-history-source="<?= htmlspecialchars($source, ENT_QUOTES, 'UTF-8') ?>">
                            <a href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>">
                                <span class="source-dot source-<?= htmlspecialchars($source, ENT_QUOTES, 'UTF-8') ?>" title="<?= $source === 'enhanced' ? 'Enhanced' : 'Generated' ?>" aria-label="<?= $source === 'enhanced' ? 'Enhanced' : 'Generated' ?>"></span>
                                <span class="recent-draft-copy">
                                    <strong><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?> · Run #<?= $runId ?></strong>
                                    <small><?= htmlspecialchars((string)$draft['prompt'], ENT_QUOTES, 'UTF-8') ?></small>
                                </span>
                                <span class="draft-status"><span class="status-dot status-dot-<?= htmlspecialchars(strtolower($status), ENT_QUOTES, 'UTF-8') ?>"></span><?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?></span>
                                <time><?= htmlspecialchars((string)$draft['generated_at'], ENT_QUOTES, 'UTF-8') ?></time>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="empty">No curriculum drafts have been saved yet.</p>
            <?php endif; ?>
        </section>
<?php require __DIR__ . '/partials/dashboard_shell_end.php'; ?>
<script>
    var sourceFilter = document.getElementById('draft-history-source');
    if (sourceFilter) {
        sourceFilter.addEventListener('change', function () {
            document.querySelectorAll('#draft-history-list [data-history-source]').forEach(function (item) {
                item.hidden = sourceFilter.value !== '' && item.dataset.historySource !== sourceFilter.value;
            });
        });
    }
</script>
</body>
</html>
