<?php
$dbPath = __DIR__ . '/curriculum_matching.db';
$db = new SQLite3($dbPath, SQLITE3_OPEN_READONLY);
$db->enableExceptions(true);

$query = "
    SELECT r.id AS run_id, r.program, r.prompt, r.model_name, r.generated_at,
           rev.id AS review_id, rev.status AS review_status, rev.reviewer, rev.notes, rev.reviewed_at
    FROM generated_curriculum_runs r
    LEFT JOIN generated_curriculum_reviews rev ON rev.run_id = r.id
    ORDER BY rev.reviewed_at DESC, r.generated_at DESC
";

$rows = [];
$result = $db->query($query);
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $rows[] = $row;
}
$db->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Approved and Rejected Drafts</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 24px; background: #f5f7fb; color: #1e293b; }
        .container { max-width: 1100px; margin: 0 auto; background: white; border-radius: 12px; padding: 24px; box-shadow: 0 4px 18px rgba(15, 23, 42, 0.08); }
        h1 { margin-top: 0; }
        .nav { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 18px; }
        .nav a { padding: 8px 12px; border-radius: 8px; text-decoration: none; }
        .nav a.primary { background: #0f172a; color: white; }
        .nav a.secondary { background: #e2e8f0; color: #0f172a; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #e2e8f0; padding: 10px 12px; text-align: left; vertical-align: top; }
        th { background: #eff6ff; }
        .badge { display: inline-block; padding: 4px 8px; border-radius: 999px; font-size: 12px; font-weight: 700; }
        .approved { background: #dcfce7; color: #166534; }
        .rejected { background: #fee2e2; color: #991b1b; }
        .needs_revision { background: #fef3c7; color: #92400e; }
        .draft { background: #dbeafe; color: #1d4ed8; }
        .empty { padding: 12px; color: #475569; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Approved / Rejected Drafts</h1>
        <div class="nav">
            <a class="secondary" href="index.php">Skill Coverage</a>
            <a class="secondary" href="generated_curriculum.php">Generated Drafts</a>
            <a class="primary" href="review_status.php">Approved / Rejected</a>
        </div>

        <?php if (empty($rows)): ?>
            <div class="empty">No review decisions saved yet.</div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Run ID</th>
                        <th>Program</th>
                        <th>Generated</th>
                        <th>Status</th>
                        <th>Reviewer</th>
                        <th>Notes</th>
                        <th>Reviewed At</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <?php $status = trim((string)($row['review_status'] ?? 'draft')); ?>
                        <tr>
                            <td><?= (int)($row['run_id'] ?? 0) ?></td>
                            <td><?= htmlspecialchars((string)($row['program'] ?? '')) ?></td>
                            <td><?= htmlspecialchars((string)($row['generated_at'] ?? '')) ?></td>
                            <td><span class="badge <?= htmlspecialchars(str_replace(' ', '_', strtolower($status))) ?>"><?= htmlspecialchars($status) ?></span></td>
                            <td><?= htmlspecialchars((string)($row['reviewer'] ?? '')) ?></td>
                            <td><?= htmlspecialchars((string)($row['notes'] ?? '')) ?></td>
                            <td><?= htmlspecialchars((string)($row['reviewed_at'] ?? '')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</body>
</html>
