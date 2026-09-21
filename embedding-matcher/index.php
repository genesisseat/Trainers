<?php
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
$skillFilter = trim((string)($_GET['skill'] ?? ''));
$courseFilter = trim((string)($_GET['course'] ?? ''));
$limit = isset($_GET['limit']) ? max(5, min(100, (int)$_GET['limit'])) : 20;
$sort = (string)($_GET['sort'] ?? 'score_asc');

$generationProgram = trim((string)($_POST['program'] ?? ''));
$generationPrompt = trim((string)($_POST['prompt'] ?? ''));
$reviewAction = trim((string)($_POST['review_action'] ?? ''));
$reviewRunId = isset($_POST['review_run_id']) ? (int)$_POST['review_run_id'] : null;
$reviewStatus = trim((string)($_POST['review_status'] ?? 'approved'));
$reviewer = trim((string)($_POST['reviewer'] ?? 'admin'));
$reviewNotes = trim((string)($_POST['review_notes'] ?? 'Reviewed in browser.'));
$apiKeyAction = trim((string)($_POST['api_key_action'] ?? ''));
$apiKeyInput = trim((string)($_POST['api_key_value'] ?? ''));
$apiKeyMessage = '';
$apiKeySaved = loadSavedApiKey() !== '';

if ($apiKeyAction === 'save') {
    $saveResult = saveApiKey($apiKeyInput);
    $apiKeySaved = $saveResult['value_present'];
    $apiKeyMessage = $saveResult['success'] ? 'API key saved to your user settings.' : 'Unable to save API key.';
}

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

if ($generationProgram !== '' || $generationPrompt !== '') {
    $program = strtoupper(preg_replace('/[^A-Za-z0-9 ]+/', '', $generationProgram) ?: 'BSIT');
    $prompt = preg_replace('/\s+/', ' ', $generationPrompt) ?: "Generate a {$program} curriculum focused on software development, databases, and networking.";

    $pythonExe = __DIR__ . '/venv/Scripts/python.exe';
    $script = __DIR__ . '/user_operations.py';
    $coursesCsv = __DIR__ . '/../curriculum-generator-kb/data/curriculum_dataset_with_ids.csv';
    $coverageCsv = __DIR__ . '/skill_coverage.csv';

    if (file_exists($pythonExe) && file_exists($script)) {
        $command = escapeshellarg($pythonExe) . ' ' . escapeshellarg($script) .
            ' --generate --program ' . escapeshellarg($program) .
            ' --prompt ' . escapeshellarg($prompt) .
            ' --courses-csv ' . escapeshellarg($coursesCsv) .
            ' --skill-coverage ' . escapeshellarg($coverageCsv) .
            ' --output-db ' . escapeshellarg($dbPath) .
            ' --limit 6 2>&1';

        $generationOutput = shell_exec($command);
        $generationMessage = $generationOutput !== null ? 'Draft generated for ' . htmlspecialchars($program) . '. Review the curriculum section below.' : 'Draft generation failed.';
    } else {
        $generationMessage = 'Python environment not found. Please activate the project venv first.';
    }
}

$allowedSorts = [
    'score_asc' => 'score ASC',
    'score_desc' => 'score DESC',
    'skill_name_asc' => 'skill_name ASC',
    'skill_name_desc' => 'skill_name DESC',
    'course_title_asc' => 'best_course_title ASC',
    'course_title_desc' => 'best_course_title DESC',
];
$sortSql = $allowedSorts[$sort] ?? $allowedSorts['score_asc'];

$db = new SQLite3($dbPath, SQLITE3_OPEN_READONLY);
$db->enableExceptions(true);

$sql = "
    SELECT skill_id, skill_name, skill_type, best_course_id, best_course_title, score
    FROM skill_coverage
    WHERE 1 = 1
";
$params = [];

if ($skillFilter !== '') {
    $sql .= " AND (LOWER(skill_name) LIKE LOWER('%' || :skill || '%') OR LOWER(skill_id) LIKE LOWER('%' || :skill || '%')) ";
    $params[':skill'] = $skillFilter;
}

if ($courseFilter !== '') {
    $sql .= " AND (LOWER(best_course_title) LIKE LOWER('%' || :course || '%') OR LOWER(best_course_id) LIKE LOWER('%' || :course || '%')) ";
    $params[':course'] = $courseFilter;
}

$sql .= " ORDER BY " . $sortSql . " LIMIT :limit ";

$stmt = $db->prepare($sql);
if ($skillFilter !== '') {
    $stmt->bindValue(':skill', $skillFilter, SQLITE3_TEXT);
}
if ($courseFilter !== '') {
    $stmt->bindValue(':course', $courseFilter, SQLITE3_TEXT);
}
$stmt->bindValue(':limit', $limit, SQLITE3_INTEGER);
$result = $stmt->execute();

$rows = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $rows[] = $row;
}

$runQuery = "
    SELECT r.id, r.program, r.prompt, r.model_name, r.status, r.generated_at,
           s.id as subject_id, s.year, s.term, s.subject_code, s.subject_title,
           s.units, s.prerequisites, s.topics, s.rationale, s.source_colleges
    FROM generated_curriculum_runs r
    LEFT JOIN generated_curriculum_subjects s ON s.run_id = r.id
    ORDER BY r.generated_at DESC, CAST(s.year AS INTEGER), CAST(s.term AS INTEGER)
    LIMIT 20
";
$runRows = [];
$runResult = $db->query($runQuery);
while ($row = $runResult->fetchArray(SQLITE3_ASSOC)) {
    $runRows[] = $row;
}

$db->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Curriculum Skill Matcher</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 24px;
            background: #f5f7fb;
            color: #1e293b;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 4px 18px rgba(15, 23, 42, 0.08);
        }
        h1 {
            margin-top: 0;
        }
        form {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 24px;
            align-items: end;
        }
        .field {
            display: flex;
            flex-direction: column;
            gap: 6px;
            min-width: 180px;
        }
        label {
            font-weight: 600;
            font-size: 14px;
        }
        input, button {
            padding: 10px 12px;
            border-radius: 8px;
            border: 1px solid #cbd5e1;
            font-size: 14px;
        }
        button {
            background: #0f172a;
            color: white;
            border: none;
            cursor: pointer;
            min-width: 120px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th, td {
            border: 1px solid #e2e8f0;
            padding: 10px 12px;
            text-align: left;
            vertical-align: top;
        }
        th {
            background: #eff6ff;
        }
        .score {
            font-weight: 700;
            color: #0f766e;
        }
        .summary {
            margin-bottom: 18px;
            color: #475569;
        }
        .empty {
            padding: 12px;
            color: #475569;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Curriculum Skill Gap Viewer</h1>
        <p class="summary">Review the weakest matched skills and filter by skill or course.</p>
        <div style="margin-bottom: 20px; display: flex; gap: 10px; flex-wrap: wrap;">
            <a href="index.php" style="padding: 8px 12px; background: #0f172a; color: white; border-radius: 8px; text-decoration: none;">Skill Coverage</a>
            <a href="generated_curriculum.php" style="padding: 8px 12px; background: #e2e8f0; color: #0f172a; border-radius: 8px; text-decoration: none;">Generated Drafts</a>
            <a href="review_status.php" style="padding: 8px 12px; background: #e2e8f0; color: #0f172a; border-radius: 8px; text-decoration: none;">Approved / Rejected</a>
        </div>

        <section style="margin-bottom: 28px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 18px;">
            <h2 style="margin-top: 0;">API settings</h2>
            <?php if ($apiKeyMessage !== ''): ?>
                <div style="padding: 10px 12px; background: #eff6ff; border: 1px solid #bfdbfe; color: #1d4ed8; border-radius: 8px; margin-bottom: 14px;">
                    <?= htmlspecialchars($apiKeyMessage) ?>
                </div>
            <?php endif; ?>
            <form method="post">
                <input type="hidden" name="api_key_action" value="save">
                <div class="field" style="min-width: 420px; flex: 1;">
                    <label for="api_key_value">Gemini / OpenAI API key</label>
                    <input type="password" id="api_key_value" name="api_key_value" value="" placeholder="Paste your API key here" autocomplete="off">
                </div>
                <button type="submit">Save key</button>
            </form>
            <p style="margin: 10px 0 0; color: #475569; font-size: 13px;">
                <?= $apiKeySaved ? 'A key is currently saved in your user profile settings.' : 'No key is saved yet. The key is stored outside the project folder for safety.' ?>
            </p>
        </section>

        <section style="margin-bottom: 28px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 18px;">
            <h2 style="margin-top: 0;">Generate curriculum draft</h2>
            <?php if ($generationMessage !== ''): ?>
                <div style="padding: 10px 12px; background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; border-radius: 8px; margin-bottom: 14px;">
                    <?= $generationMessage ?>
                </div>
            <?php endif; ?>
            <form method="post">
                <div class="field">
                    <label for="program">Program</label>
                    <input type="text" id="program" name="program" value="BSIT" placeholder="BSIT">
                </div>
                <div class="field" style="min-width: 420px; flex: 1;">
                    <label for="prompt">Prompt</label>
                    <input type="text" id="prompt" name="prompt" value="Generate a BSIT curriculum focused on software development, databases, and networking." placeholder="Generate a curriculum for...">
                </div>
                <button type="submit">Generate draft</button>
            </form>
        </section>

        <form method="get">
            <div class="field">
                <label for="skill">Skill filter</label>
                <input type="text" id="skill" name="skill" value="<?= htmlspecialchars($skillFilter) ?>" placeholder="e.g. Postman, Python, Cloud">
            </div>
            <div class="field">
                <label for="course">Course filter</label>
                <input type="text" id="course" name="course" value="<?= htmlspecialchars($courseFilter) ?>" placeholder="e.g. Operating Systems">
            </div>
            <div class="field">
                <label for="limit">Limit</label>
                <input type="number" id="limit" name="limit" min="5" max="100" value="<?= (int)$limit ?>">
            </div>
            <div class="field">
                <label for="sort">Sort by</label>
                <select id="sort" name="sort">
                    <option value="score_asc" <?= $sort === 'score_asc' ? 'selected' : '' ?>>Weakest first</option>
                    <option value="score_desc" <?= $sort === 'score_desc' ? 'selected' : '' ?>>Strongest first</option>
                    <option value="skill_name_asc" <?= $sort === 'skill_name_asc' ? 'selected' : '' ?>>Skill A→Z</option>
                    <option value="skill_name_desc" <?= $sort === 'skill_name_desc' ? 'selected' : '' ?>>Skill Z→A</option>
                    <option value="course_title_asc" <?= $sort === 'course_title_asc' ? 'selected' : '' ?>>Course A→Z</option>
                    <option value="course_title_desc" <?= $sort === 'course_title_desc' ? 'selected' : '' ?>>Course Z→A</option>
                </select>
            </div>
            <button type="submit">Apply filters</button>
        </form>

        <?php if (empty($rows)): ?>
            <div class="empty">No matching rows found for the current filters.</div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Skill ID</th>
                        <th><a href="?<?= http_build_query(array_merge($_GET, ['sort' => $sort === 'skill_name_asc' ? 'skill_name_desc' : 'skill_name_asc'])) ?>">Skill Name</a></th>
                        <th>Type</th>
                        <th>Best Course ID</th>
                        <th><a href="?<?= http_build_query(array_merge($_GET, ['sort' => $sort === 'course_title_asc' ? 'course_title_desc' : 'course_title_asc'])) ?>">Best Course Title</a></th>
                        <th><a href="?<?= http_build_query(array_merge($_GET, ['sort' => $sort === 'score_asc' ? 'score_desc' : 'score_asc'])) ?>">Similarity Score</a></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['skill_id']) ?></td>
                            <td><?= htmlspecialchars($row['skill_name']) ?></td>
                            <td><?= htmlspecialchars($row['skill_type']) ?></td>
                            <td><?= htmlspecialchars($row['best_course_id']) ?></td>
                            <td><?= htmlspecialchars($row['best_course_title']) ?></td>
                            <td class="score"><?= number_format((float)$row['score'], 4) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <section style="margin-top: 32px;">
            <h2>Generated drafts</h2>
            <p class="summary">The generated curriculum review is now shown on a dedicated page for cleaner review and less mixing with the skill-coverage view.</p>
            <p><a href="generated_curriculum.php">Open the generated curriculum page</a></p>
        </section>
    </div>
</body>
</html>
