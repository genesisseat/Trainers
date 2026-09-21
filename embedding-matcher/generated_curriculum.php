<?php
$dbPath = __DIR__ . '/curriculum_matching.db';
$reviewAction = trim((string)($_POST['review_action'] ?? ''));
$reviewRunId = isset($_POST['review_run_id']) ? (int)$_POST['review_run_id'] : null;
$reviewStatus = trim((string)($_POST['review_status'] ?? 'approved'));
$reviewer = trim((string)($_POST['reviewer'] ?? 'admin'));
$reviewNotes = trim((string)($_POST['review_notes'] ?? 'Reviewed in browser.'));
$generationMessage = '';

if ($reviewAction !== '' && $reviewRunId !== null) {
    $pythonExe = __DIR__ . '/venv/Scripts/python.exe';
    $script = __DIR__ . '/user_operations.py';
    if (file_exists($pythonExe) && file_exists($script)) {
        $command = escapeshellarg($pythonExe) . ' ' . escapeshellarg($script) .
            ' --review --run-id ' . escapeshellarg((string)$reviewRunId) .
            ' --review-status ' . escapeshellarg($reviewStatus) .
            ' --reviewer ' . escapeshellarg($reviewer) .
            ' --review-notes ' . escapeshellarg($reviewNotes) .
            ' --output-db ' . escapeshellarg($dbPath) . ' 2>&1';
        $reviewOutput = shell_exec($command);
        $generationMessage = 'Review saved: ' . htmlspecialchars($reviewStatus) . '.';
    } else {
        $generationMessage = 'Python environment not found. Cannot save review status.';
    }
}

$db = new SQLite3($dbPath, SQLITE3_OPEN_READONLY);
$db->enableExceptions(true);

$query = "
    SELECT r.id, r.program, r.prompt, r.model_name, r.status, r.generated_at,
           s.id as subject_id, s.year, s.term, s.subject_code, s.subject_title,
           s.units, s.prerequisites, s.topics, s.rationale, s.source_colleges
    FROM generated_curriculum_runs r
    LEFT JOIN generated_curriculum_subjects s ON s.run_id = r.id
    ORDER BY r.generated_at DESC, CAST(s.year AS INTEGER), CAST(s.term AS INTEGER)
";

$runRows = [];
$result = $db->query($query);
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $runRows[] = $row;
}

$db->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generated Curriculum Drafts</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 24px; background: #f5f7fb; color: #1e293b; }
        .container { max-width: 1100px; margin: 0 auto; background: white; border-radius: 12px; padding: 24px; box-shadow: 0 4px 18px rgba(15, 23, 42, 0.08); }
        h1 { margin-top: 0; }
        .nav { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 18px; }
        .nav a { padding: 8px 12px; border-radius: 8px; text-decoration: none; }
        .nav a.primary { background: #0f172a; color: white; }
        .nav a.secondary { background: #e2e8f0; color: #0f172a; }
        .card { border: 1px solid #e2e8f0; padding: 16px; border-radius: 10px; background: #f8fafc; margin-bottom: 20px; }
        .field { display: flex; flex-direction: column; gap: 6px; min-width: 180px; }
        form.review-form { display: flex; flex-wrap: wrap; gap: 10px; align-items: end; margin: 12px 0; }
        input, select, button { padding: 10px 12px; border-radius: 8px; border: 1px solid #cbd5e1; font-size: 14px; }
        button { background: #0f172a; color: white; border: none; cursor: pointer; min-width: 120px; }
        .subject { border: 1px solid #dbeafe; border-radius: 8px; padding: 12px; background: white; margin-top: 12px; }
        .empty { padding: 12px; color: #475569; }
        .meta { color: #475569; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Generated Curriculum Drafts</h1>
        <div class="nav">
            <a class="secondary" href="index.php">Skill Coverage</a>
            <a class="primary" href="generated_curriculum.php">Generated Drafts</a>
            <a class="secondary" href="review_status.php">Approved / Rejected</a>
        </div>

        <?php if ($generationMessage !== ''): ?>
            <div style="padding: 10px 12px; background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; border-radius: 8px; margin-bottom: 18px;">
                <?= htmlspecialchars($generationMessage) ?>
            </div>
        <?php endif; ?>

        <?php if (empty($runRows)): ?>
            <div class="empty">No generated curriculum drafts found yet.</div>
        <?php else: ?>
            <?php $currentRun = null; ?>
            <?php foreach ($runRows as $run): ?>
                <?php if ($currentRun === null || (int)$run['id'] !== (int)$currentRun['id']): ?>
                    <?php if ($currentRun !== null): ?>
                        </div>
                        </div>
                    <?php endif; ?>
                    <?php $currentRun = $run; ?>
                    <div class="card">
                        <h2 style="margin-top: 0; margin-bottom: 8px;">
                            <?= htmlspecialchars($run['program']) ?> — <?= htmlspecialchars($run['status'] ?? 'draft') ?>
                        </h2>
                        <p class="meta"><strong>Generated:</strong> <?= htmlspecialchars($run['generated_at']) ?></p>
                        <p class="meta"><strong>Prompt:</strong> <?= htmlspecialchars($run['prompt']) ?></p>

                        <form method="post" class="review-form">
                            <input type="hidden" name="review_run_id" value="<?= (int)$run['id'] ?>">
                            <div class="field">
                                <label for="review_status">Decision</label>
                                <select name="review_status" id="review_status">
                                    <option value="approved">Approved</option>
                                    <option value="rejected">Rejected</option>
                                    <option value="needs_revision">Needs revision</option>
                                    <option value="draft">Draft</option>
                                </select>
                            </div>
                            <div class="field">
                                <label for="reviewer">Reviewer</label>
                                <input type="text" name="reviewer" value="admin" placeholder="Reviewer name">
                            </div>
                            <div class="field" style="min-width: 260px; flex: 1;">
                                <label for="review_notes">Notes</label>
                                <input type="text" name="review_notes" value="Reviewed in browser." placeholder="Review notes">
                            </div>
                            <button type="submit" name="review_action" value="save">Save review</button>
                        </form>
                        <div style="display: grid; gap: 12px;">
                <?php endif; ?>

                <?php if ($run['subject_title'] !== null): ?>
                    <div class="subject">
                        <div style="display: flex; flex-wrap: wrap; justify-content: space-between; gap: 10px; margin-bottom: 10px;">
                            <strong><?= htmlspecialchars($run['subject_title']) ?></strong>
                            <span class="meta">Year <?= htmlspecialchars($run['year']) ?>, Term <?= htmlspecialchars($run['term']) ?></span>
                        </div>
                        <p style="margin: 4px 0;"><strong>Code:</strong> <?= htmlspecialchars($run['subject_code']) ?></p>
                        <p style="margin: 4px 0;"><strong>Units:</strong> <?= htmlspecialchars($run['units']) ?> | <strong>Prereqs:</strong> <?= htmlspecialchars($run['prerequisites']) ?></p>
                        <p style="margin: 6px 0;"><strong>Source colleges:</strong> <?= htmlspecialchars($run['source_colleges']) ?></p>
                        <p style="margin: 6px 0;"><strong>Rationale:</strong> <?= htmlspecialchars($run['rationale']) ?></p>
                        <p style="margin: 6px 0;"><strong>Topics:</strong></p>
                        <ul style="margin-top: 4px; margin-bottom: 0; padding-left: 18px;">
                            <?php $topics = json_decode((string)$run['topics'], true); if (is_array($topics)): foreach ($topics as $topic): ?>
                                <li><?= htmlspecialchars((string)$topic) ?></li>
                            <?php endforeach; endif; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
            <?php if ($currentRun !== null): ?>
                </div>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</body>
</html>
