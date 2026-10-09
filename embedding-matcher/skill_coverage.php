<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_login();

$db = new SQLite3(__DIR__ . '/curriculum_matching.db', SQLITE3_OPEN_READONLY);
$db->busyTimeout(30000);
$db->enableExceptions(true);

$skillTypes = [];
$typeResult = $db->query(
    "SELECT DISTINCT skill_type
     FROM skill_coverage
     WHERE skill_type IS NOT NULL AND TRIM(skill_type) <> ''
     ORDER BY skill_type COLLATE NOCASE"
);
while ($typeRow = $typeResult->fetchArray(SQLITE3_ASSOC)) {
    $skillTypes[] = (string)$typeRow['skill_type'];
}

$search = trim((string)($_GET['q'] ?? ''));
$selectedType = trim((string)($_GET['type'] ?? ''));
if ($selectedType !== '' && !in_array($selectedType, $skillTypes, true)) {
    $selectedType = '';
}
$sortColumns = [
    'skill' => 'skill_name',
    'type' => 'skill_type',
    'course' => 'best_course_title',
    'score' => 'score',
];
$sort = (string)($_GET['sort'] ?? 'score');
if (!isset($sortColumns[$sort])) {
    $sort = 'score';
}
$direction = strtolower((string)($_GET['direction'] ?? 'asc'));
if (!in_array($direction, ['asc', 'desc'], true)) {
    $direction = 'asc';
}
$orderSql = $sortColumns[$sort] . ' ' . strtoupper($direction);
$orderSql .= ', skill_name COLLATE NOCASE ASC';

$coverageSql = 'SELECT skill_id, skill_name, skill_type, best_course_title, score
                FROM skill_coverage';
$whereSql = ' WHERE 1 = 1';
$conditions = [];
if ($search !== '') {
    $conditions[] = '(skill_id LIKE :search OR skill_name LIKE :search OR best_course_title LIKE :search)';
}
if ($selectedType !== '') {
    $conditions[] = 'skill_type = :type';
}
if ($conditions) {
    $whereSql = ' WHERE ' . implode(' AND ', $conditions);
}
$coverageStatement = $db->prepare($coverageSql . $whereSql . ' ORDER BY ' . $orderSql);
if ($search !== '') {
    $coverageStatement->bindValue(':search', '%' . $search . '%', SQLITE3_TEXT);
}
if ($selectedType !== '') {
    $coverageStatement->bindValue(':type', $selectedType, SQLITE3_TEXT);
}
$coverageResult = $coverageStatement->execute();
$coverageRows = [];
while ($row = $coverageResult->fetchArray(SQLITE3_ASSOC)) {
    $coverageRows[] = $row;
}
$coverageResult->finalize();
$db->close();

$sortHref = static function (string $column) use ($sort, $direction, $search, $selectedType): string {
    $nextDirection = $sort === $column && $direction === 'asc' ? 'desc' : 'asc';
    return '?' . http_build_query([
        'q' => $search,
        'type' => $selectedType,
        'sort' => $column,
        'direction' => $nextDirection,
    ]);
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Skill Coverage</title>
    <link rel="stylesheet" href="assets/theme.css">
</head>
<?php $activePage = 'coverage'; require __DIR__ . '/partials/dashboard_shell_start.php'; ?>
        <div class="dashboard-heading">
            <p class="eyebrow">Coverage evidence</p>
            <h1>Skill Coverage</h1>
            <p class="summary">Review the best-course match recorded for each industry skill. Similarity scores are evidence, not competency measurements.</p>
        </div>
        <section class="table-panel">
            <div class="coverage-toolbar">
                <h2>Coverage results</h2>
                <p class="summary"><?= count($coverageRows) ?> result<?= count($coverageRows) === 1 ? '' : 's' ?></p>
                <form method="get">
                    <div class="field">
                        <label for="coverage-search">Search skills or courses</label>
                        <input type="search" id="coverage-search" name="q" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>" placeholder="Enter a skill, ID, or course">
                    </div>
                    <div class="field">
                        <label for="coverage-type">Skill type</label>
                        <select id="coverage-type" name="type">
                            <option value="">All types</option>
                            <?php foreach ($skillTypes as $skillType): ?>
                                <option value="<?= htmlspecialchars($skillType, ENT_QUOTES, 'UTF-8') ?>" <?= $selectedType === $skillType ? 'selected' : '' ?>><?= htmlspecialchars($skillType) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit">Filter</button>
                </form>
            </div>
            <?php if ($coverageRows): ?>
                <div class="table-wrap">
                    <table class="coverage-table">
                        <thead>
                            <tr>
                                <th><a href="<?= htmlspecialchars($sortHref('skill'), ENT_QUOTES, 'UTF-8') ?>">Skill<?= $sort === 'skill' ? ($direction === 'asc' ? ' ↑' : ' ↓') : '' ?></a></th>
                                <th><a href="<?= htmlspecialchars($sortHref('type'), ENT_QUOTES, 'UTF-8') ?>">Type<?= $sort === 'type' ? ($direction === 'asc' ? ' ↑' : ' ↓') : '' ?></a></th>
                                <th><a href="<?= htmlspecialchars($sortHref('course'), ENT_QUOTES, 'UTF-8') ?>">Best matching course<?= $sort === 'course' ? ($direction === 'asc' ? ' ↑' : ' ↓') : '' ?></a></th>
                                <th><a href="<?= htmlspecialchars($sortHref('score'), ENT_QUOTES, 'UTF-8') ?>">Similarity<?= $sort === 'score' ? ($direction === 'asc' ? ' ↑' : ' ↓') : '' ?></a></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($coverageRows as $coverageRow): ?>
                                <tr>
                                    <td><span class="skill-id"><?= htmlspecialchars((string)$coverageRow['skill_id']) ?></span><br><?= htmlspecialchars((string)$coverageRow['skill_name']) ?></td>
                                    <td><?= htmlspecialchars((string)($coverageRow['skill_type'] ?? '')) ?></td>
                                    <td><?= htmlspecialchars((string)($coverageRow['best_course_title'] ?? '')) ?></td>
                                    <td class="score"><?= number_format((float)$coverageRow['score'], 3) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="empty"><?= $search !== '' || $selectedType !== '' ? 'No skill-coverage results match these filters.' : 'No skill-coverage results are available.' ?></p>
            <?php endif; ?>
        </section>
<?php require __DIR__ . '/partials/dashboard_shell_end.php'; ?>
</body>
</html>
