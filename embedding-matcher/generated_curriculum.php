<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/draft_management.php';
if (current_user() === null
    && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET'
    && ($_GET['guest_draft_clear'] ?? '') === '1') {
    clear_guest_trial_draft();
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'
    && !validate_csrf_token($_POST['csrf_token'] ?? null)) {
    http_response_code(400);
    exit('Invalid or expired form token. Please reload the page and try again.');
}
$isGuestMode = current_user() === null && ((isset($_GET['guest']) && $_GET['guest'] === '1') || (!empty($_SESSION['guest_trial_mode'])));
if ($isGuestMode) {
    header('Cache-Control: no-store, no-cache, must-revalidate');
    $_SESSION['guest_trial_mode'] = true;
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
        && is_string($_POST['guest_tab_id'] ?? null)) {
        claim_guest_draft_tab($_POST['guest_tab_id']);
    }
} elseif (current_user() === null) {
    require_login();
}
$isUserChatSubmission = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
    && trim((string)($_POST['chat_action'] ?? '')) === 'send'
    && trim((string)($_POST['delete_action'] ?? '')) === ''
    && trim((string)($_POST['draft_update_action'] ?? '')) === ''
    && trim((string)($_POST['generate_action'] ?? '')) === '';
$isUserGenerateSubmission = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
    && trim((string)($_POST['generate_action'] ?? '')) === 'generate'
    && trim((string)($_POST['chat_action'] ?? '')) === ''
    && trim((string)($_POST['delete_action'] ?? '')) === ''
    && trim((string)($_POST['draft_update_action'] ?? '')) === '';
$isGuestGenerateSubmission = $isGuestMode
    && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
    && trim((string)($_POST['generate_action'] ?? '')) === 'generate'
    && trim((string)($_POST['chat_action'] ?? '')) === ''
    && trim((string)($_POST['delete_action'] ?? '')) === ''
    && trim((string)($_POST['draft_update_action'] ?? '')) === '';
$isDraftUpdateSubmission = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
    && trim((string)($_POST['draft_update_action'] ?? '')) === 'save'
    && trim((string)($_POST['chat_action'] ?? '')) === ''
    && trim((string)($_POST['delete_action'] ?? '')) === ''
    && trim((string)($_POST['generate_action'] ?? '')) === '';
$isUserDeleteSubmission = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
    && trim((string)($_POST['delete_action'] ?? '')) === 'delete'
    && trim((string)($_POST['chat_action'] ?? '')) === ''
    && trim((string)($_POST['draft_update_action'] ?? '')) === ''
    && trim((string)($_POST['generate_action'] ?? '')) === '';
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && !has_role('admin', 'super_admin')
    && !$isUserChatSubmission
    && !$isUserGenerateSubmission
    && !$isGuestGenerateSubmission
    && !$isDraftUpdateSubmission
    && !$isUserDeleteSubmission) {
    http_response_code(403);
    exit('Forbidden: your account does not have permission to perform this action.');
}

$dbPath = __DIR__ . '/curriculum_matching.db';
$deleteAction = trim((string)($_POST['delete_action'] ?? ''));
$deleteRunId = isset($_POST['delete_run_id']) ? (int)$_POST['delete_run_id'] : null;
$draftUpdateAction = trim((string)($_POST['draft_update_action'] ?? ''));
$draftUpdateRunIdInput = $_POST['draft_update_run_id'] ?? null;
$draftUpdateRunId = is_string($draftUpdateRunIdInput) && ctype_digit($draftUpdateRunIdInput)
    ? (int)$draftUpdateRunIdInput
    : null;
$generateAction = trim((string)($_POST['generate_action'] ?? ''));
$generationProgram = trim((string)($_POST['generate_program'] ?? 'BSIT'));
$generationPrompt = trim((string)($_POST['generate_prompt'] ?? ''));
$chatAction = trim((string)($_POST['chat_action'] ?? ''));
$chatRunId = isset($_POST['chat_run_id']) ? (int)$_POST['chat_run_id'] : null;
$chatMessage = trim((string)($_POST['chat_message'] ?? ''));
$currentUser = current_user();
$currentUserApiKey = $currentUser !== null ? get_user_api_key((int)$currentUser['id']) : null;
$apiKeyPolicy = gemini_api_key_policy(
    $currentUser,
    $currentUserApiKey,
    has_shared_api_key(),
    $isGuestMode
);
$hasApiKeyAvailable = $apiKeyPolicy['allowed'];
$requestedRunId = $_GET['run_id'] ?? $_GET['chat_run_id'] ?? null;
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $requestedRunId = match (true) {
        $deleteAction === 'delete' => $_POST['delete_run_id'] ?? null,
        $draftUpdateAction === 'save' => $draftUpdateRunIdInput,
        $chatAction === 'send' => $_POST['chat_run_id'] ?? null,
        default => $requestedRunId,
    };
}
if (
    !$isGuestMode
    && ($requestedRunId !== null || $deleteAction === 'delete' || $draftUpdateAction === 'save' || $chatAction === 'send')
) {
    $accessDb = new SQLite3($dbPath, SQLITE3_OPEN_READONLY);
    $accessDb->busyTimeout(30000);
    $accessDb->enableExceptions(true);
    $requestedRun = run_access_require($accessDb, $requestedRunId, $currentUser);
    if ($deleteAction === 'delete' && !run_access_can_delete($currentUser, $requestedRun)) {
        $accessDb->close();
        run_access_not_found();
    }
    if ($draftUpdateAction === 'save' && !run_access_can_update($currentUser, $requestedRun)) {
        $accessDb->close();
        run_access_not_found();
    }
    $accessDb->close();
}
$guestTrialLimitReached = $isGuestMode && guest_trial_limit_reached();
$generationMessage = '';
$generationError = '';
$apiKeyMessage = '';
$accountMenuOpen = false;
$apiKeyMessageType = 'warning';

if (isset($_SESSION['flash_success'])) {
    $generationMessage = (string)$_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}
if (isset($_SESSION['flash_error'])) {
    $generationError = (string)$_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
}
if (!empty($_SESSION['open_api_key_settings'])) {
    $accountMenuOpen = true;
    $apiKeyMessage = $generationError !== '' ? $generationError : GEMINI_API_KEY_REQUIRED_MESSAGE;
    unset($_SESSION['open_api_key_settings']);
}
if ($isGuestMode && isset($_SESSION['guest_generation_flash'])) {
    $generationMessage = (string)$_SESSION['guest_generation_flash'];
    unset($_SESSION['guest_generation_flash']);
}
if ($apiKeyMessage === '' && $generationError === GEMINI_API_KEY_REQUIRED_MESSAGE) {
    $apiKeyMessage = GEMINI_API_KEY_REQUIRED_MESSAGE;
    $accountMenuOpen = true;
}

if ($chatAction === 'send' && current_user() === null) {
    http_response_code(403);
    exit('Forbidden: sign in to use curriculum chat.');
}

if ($deleteAction === 'delete') {
    try {
        $deleteDb = new SQLite3($dbPath);
        $deleteDb->busyTimeout(30000);
        $deleteDb->enableExceptions(true);
        run_access_delete($deleteDb, $deleteRunId, $currentUser);
        $deleteDb->close();
        $_SESSION['flash_success'] = 'Curriculum draft and all related data have been permanently deleted.';
        header('Location: generated_curriculum.php');
        exit;
    } catch (Throwable $e) {
        if (isset($deleteDb) && $deleteDb instanceof SQLite3) {
            try {
                $deleteDb->exec('ROLLBACK');
            } catch (Throwable $rollbackException) {
                // Ignore rollback errors if already rolled back
            }
            $deleteDb->close();
        }
        $generationError = 'Failed to delete curriculum draft #' . $deleteRunId . ': ' . $e->getMessage();
    }
}

$schemaDb = null;
if (!$isGuestMode) {
    $schemaDb = new SQLite3($dbPath);
    $schemaDb->busyTimeout(30000);
    $schemaDb->enableExceptions(true);
$schemaDb->exec(
    "CREATE TABLE IF NOT EXISTS generated_curriculum_chat (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        run_id INTEGER,
        role TEXT,
        message TEXT,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(run_id) REFERENCES generated_curriculum_runs(id)
    )"
);
$subjectColumns = [];
$subjectColumnResult = $schemaDb->query('PRAGMA table_info(generated_curriculum_subjects)');
while ($subjectColumn = $subjectColumnResult->fetchArray(SQLITE3_ASSOC)) {
    $subjectColumns[] = $subjectColumn['name'];
}
if (!in_array('mapped_industry_skills', $subjectColumns, true)) {
    $schemaDb->exec('ALTER TABLE generated_curriculum_subjects ADD COLUMN mapped_industry_skills TEXT');
}
if (!in_array('description', $subjectColumns, true)) {
    $schemaDb->exec('ALTER TABLE generated_curriculum_subjects ADD COLUMN description TEXT');
}
if (!in_array('source', $subjectColumns, true)) {
    $schemaDb->exec('ALTER TABLE generated_curriculum_subjects ADD COLUMN source TEXT');
}
$runColumns = [];
$runColumnResult = $schemaDb->query('PRAGMA table_info(generated_curriculum_runs)');
while ($runColumn = $runColumnResult->fetchArray(SQLITE3_ASSOC)) {
    $runColumns[] = $runColumn['name'];
}
if (!in_array('created_by_user_id', $runColumns, true)) {
    $schemaDb->exec('ALTER TABLE generated_curriculum_runs ADD COLUMN created_by_user_id INTEGER');
}
if (!in_array('created_by_username', $runColumns, true)) {
    $schemaDb->exec('ALTER TABLE generated_curriculum_runs ADD COLUMN created_by_username TEXT');
}
draft_management_ensure_columns($schemaDb);
$chatColumns = [];
$chatColumnResult = $schemaDb->query('PRAGMA table_info(generated_curriculum_chat)');
while ($chatColumn = $chatColumnResult->fetchArray(SQLITE3_ASSOC)) {
    $chatColumns[] = $chatColumn['name'];
}
if (!in_array('sender_user_id', $chatColumns, true)) {
    $schemaDb->exec('ALTER TABLE generated_curriculum_chat ADD COLUMN sender_user_id INTEGER');
}
if (!in_array('sender_username', $chatColumns, true)) {
    $schemaDb->exec('ALTER TABLE generated_curriculum_chat ADD COLUMN sender_username TEXT');
}
    $schemaDb->close();
}

if ($draftUpdateAction === 'save') {
    if (current_user() === null || $isGuestMode) {
        run_access_not_found();
    }
    $updateDb = new SQLite3($dbPath);
    $updateDb->busyTimeout(30000);
    $updateDb->enableExceptions(true);
    $draftOwner = run_access_require($updateDb, $draftUpdateRunId, $currentUser);
    if (!run_access_can_update($currentUser, $draftOwner)) {
        $updateDb->close();
        run_access_not_found();
    }

    $userTitle = draft_management_clean_text($_POST['user_title'] ?? null, 150);
    $userNotes = draft_management_clean_text($_POST['user_notes'] ?? null, 5000);
    if ($userTitle === null || $userNotes === null) {
        $updateDb->close();
        http_response_code(400);
        exit('Invalid draft update. Check the title and notes.');
    }

    draft_management_update_details(
        $updateDb,
        $draftUpdateRunId,
        $userTitle !== '' ? $userTitle : null,
        $userNotes
    );
    $updateDb->close();

    $_SESSION['flash_success'] = 'Draft details updated.';
    header('Location: generated_curriculum.php?run_id=' . $draftUpdateRunId . '#run-' . $draftUpdateRunId);
    exit;
}

if (isset($_GET['enhanced']) && $_GET['enhanced'] === '1') {
    $generationMessage = 'Partial curriculum completed and saved as a new draft.';
}

if ($chatAction === 'send' && $chatRunId !== null && $chatMessage !== '') {
    if (!$hasApiKeyAvailable) {
        $_SESSION['flash_error'] = GEMINI_API_KEY_REQUIRED_MESSAGE;
        $_SESSION['open_api_key_settings'] = true;
    } else {
        $script = __DIR__ . '/user_operations.py';
        try {
            $pythonExe = python_executable();
            if (file_exists($script)) {
                $command = [
                    $pythonExe,
                    $script,
                    '--chat', '--run-id', (string)$chatRunId,
                    '--message', $chatMessage,
                    '--actor-user-id', (string)$currentUser['id'],
                    '--actor-username', (string)$currentUser['username'],
                    '--admin-access-policy', RUN_ACCESS_ADMIN_POLICY,
                    '--output-db', $dbPath,
                ];
                run_command_with_api_key($command, $currentUserApiKey);
            } else {
                throw new RuntimeException('The curriculum assistant is not available.');
            }
        } catch (RuntimeException $error) {
            error_log('Curriculum chat subprocess failed to start.');
            $_SESSION['flash_error'] = python_job_failure_message($error, 'The curriculum assistant could not be started.');
        }
    }

    header('Location: generated_curriculum.php?chat_run_id=' . urlencode((string)$chatRunId));
    exit;
}

if ($generateAction === 'generate') {
    $requestedProgram = strtoupper(trim($generationProgram));
    $program = in_array($requestedProgram, ['BSIT', 'BSCS'], true) ? $requestedProgram : 'BSIT';
    $prompt = preg_replace('/\s+/', ' ', $generationPrompt)
        ?: "Generate a {$program} curriculum focused on software development, databases, and networking.";

    if ($isGuestMode && $guestTrialLimitReached) {
        $generationError = 'You have used all 3 guest trial attempts. Create an account to continue.';
    } elseif ($isGuestMode && !has_shared_api_key()) {
        $generationError = 'Guest trial is currently unavailable because the shared API key is not configured.';
    } elseif (!$hasApiKeyAvailable) {
        $generationError = GEMINI_API_KEY_REQUIRED_MESSAGE;
        $apiKeyMessage = GEMINI_API_KEY_REQUIRED_MESSAGE;
        $accountMenuOpen = true;
    } else {
        if ($isGuestMode) {
            $requestedDraft = guest_generate_program($program, $prompt);
            if (is_array($requestedDraft) && $requestedDraft !== []) {
                $_SESSION['guest_trial_draft'] = [
                    'type' => 'generated',
                    'program' => $program,
                    'model_name' => 'gemini-3.5-flash-lite',
                    'generation_mode' => is_array($requestedDraft[0] ?? null)
                        && in_array($requestedDraft[0]['_generation_mode'] ?? null, ['online', 'offline'], true)
                        ? $requestedDraft[0]['_generation_mode']
                        : null,
                    'prompt' => $prompt,
                    'draft' => $requestedDraft,
                ];
                mark_guest_attempt_success();
                $_SESSION['guest_generation_flash'] = 'Guest draft saved for this session. Create an account to keep this draft.';
                header('Location: generated_curriculum.php?guest=1');
                exit;
            } else {
                $generationError = 'The guest trial could not generate a curriculum draft. Please try again.';
            }
        } else {
            $script = __DIR__ . '/user_operations.py';
            $coursesCsv = curriculum_kb_dir() . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'curriculum_dataset_with_ids.csv';
            $coverageCsv = __DIR__ . '/skill_coverage.csv';

            try {
                $pythonExe = python_executable();
                if (!file_exists($script)) {
                    throw new RuntimeException('The curriculum generation runtime is unavailable.');
                }
                $command = [
                    $pythonExe, $script,
                    '--generate', '--program', $program,
                    '--prompt', $prompt,
                    '--courses-csv', $coursesCsv,
                    '--skill-coverage', $coverageCsv,
                    '--output-db', $dbPath,
                    '--actor-user-id', (string)$currentUser['id'],
                    '--actor-username', (string)$currentUser['username'],
                    '--limit', '6',
                ];
                set_time_limit(180);
                $generationOutput = run_command_with_api_key($command, $currentUserApiKey);
            } catch (RuntimeException $error) {
                error_log('Curriculum generation subprocess failed.');
                $generationError = python_job_failure_message($error, 'Draft generation could not be completed. Please try again.');
                $generationOutput = null;
            }
            if ($generationError === '' && $generationOutput !== null && strpos($generationOutput, '[Offline Fallback]') !== false) {
                $failureDetail = 'Gemini API request failed. The offline template was used for this draft.';
                if (preg_match('/Gemini curriculum generation failed:\s*(.+)/', $generationOutput, $failureMatch)) {
                    $failureDetail = 'Generation unavailable: ' . trim($failureMatch[1]);
                }
                $generationMessage = $failureDetail . ' Review the draft below.';
            } elseif ($generationError === '' && $generationOutput !== null && $generationOutput !== '') {
                $generationMessage = 'Draft generated for ' . $program . '. Review the curriculum section below.';
            } elseif ($generationError === '') {
                $generationError = 'Draft generation could not be completed. Please try again.';
            }
        }
    }
}

$runs = [];
$chatByRun = [];
if (!$isGuestMode) {
    $db = new SQLite3($dbPath, SQLITE3_OPEN_READONLY);
    $db->busyTimeout(30000);
    $db->enableExceptions(true);

    $runScope = run_access_sql_scope($currentUser);
    $query = "
        SELECT r.id, r.program, r.prompt, r.model_name, r.status, r.generated_at,
               r.created_by_user_id, r.created_by_username, r.user_title, r.user_notes,
               r.generation_mode, r.updated_at,
               s.id as subject_id, s.year, s.term, s.subject_code, s.subject_title,
               s.units, s.prerequisites, s.topics, s.rationale, s.description, s.source_colleges,
               s.mapped_industry_skills, s.source
        FROM generated_curriculum_runs r
        LEFT JOIN generated_curriculum_subjects s ON s.run_id = r.id
        WHERE {$runScope['sql']}
        ORDER BY r.generated_at DESC, CAST(s.year AS INTEGER), CAST(s.term AS INTEGER)
    ";

    $runRows = [];
    $runStmt = $db->prepare($query);
    run_access_bind_scope($runStmt, $runScope);
    $result = $runStmt->execute();
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $runRows[] = $row;
    }

    $chatStmt = $db->prepare(
        "SELECT c.run_id, c.role, c.message, c.created_at, c.sender_username
         FROM generated_curriculum_chat c
         INNER JOIN generated_curriculum_runs r ON r.id = c.run_id
         WHERE {$runScope['sql']}
         ORDER BY c.created_at ASC, c.id ASC"
    );
    run_access_bind_scope($chatStmt, $runScope);
    $chatResult = $chatStmt->execute();
    while ($chatRow = $chatResult->fetchArray(SQLITE3_ASSOC)) {
        $chatByRun[(int)$chatRow['run_id']][] = $chatRow;
    }

    foreach ($runRows as $run) {
        $runId = (int)$run['id'];
        if (!isset($runs[$runId])) {
            $runs[$runId] = [
                'details' => $run,
                'subjects' => [],
            ];
        }
        if ($run['subject_title'] !== null) {
            $runs[$runId]['subjects'][] = $run;
        }
    }

    $db->close();
} elseif (isset($_SESSION['guest_trial_draft']) && is_array($_SESSION['guest_trial_draft'])) {
    $guestDraft = $_SESSION['guest_trial_draft'];
    $guestDraftType = $guestDraft['type'] ?? 'generated';
    $runs = [];
    $guestSubjectRows = [];
    if ($guestDraftType === 'generated' && isset($guestDraft['draft']) && is_array($guestDraft['draft'])) {
        foreach ($guestDraft['draft'] as $index => $subject) {
            if (!is_array($subject)) {
                continue;
            }
            $prerequisiteValue = $subject['prerequisites'] ?? 'None';
            $prerequisites = is_array($prerequisiteValue)
                ? implode(', ', array_filter(array_map(
                    static fn($prerequisite): string => is_scalar($prerequisite)
                        ? trim((string)$prerequisite)
                        : '',
                    $prerequisiteValue
                ), static fn(string $prerequisite): bool => $prerequisite !== ''))
                : (is_scalar($prerequisiteValue) ? (string)$prerequisiteValue : 'None');
            $subjectRow = [
                'id' => $index + 1,
                'program' => $guestDraft['program'] ?? 'BSIT',
                'prompt' => $guestDraft['prompt'] ?? '',
                'model_name' => $guestDraft['model_name'] ?? 'gemini-3.5-flash-lite',
                'status' => 'draft',
                'generated_at' => date('Y-m-d H:i:s'),
                'year' => (string)($subject['year'] ?? '1'),
                'term' => (string)($subject['term'] ?? '1'),
                'subject_code' => (string)($subject['subject_code'] ?? ''),
                'subject_title' => (string)($subject['subject_title'] ?? ''),
                'units' => (string)($subject['units'] ?? ''),
                'prerequisites' => $prerequisites,
                'topics' => $subject['topics'] ?? [],
                'rationale' => (string)($subject['rationale'] ?? ''),
                'description' => (string)($subject['description'] ?? ''),
                'source_colleges' => $subject['source_colleges'] ?? [],
                'mapped_industry_skills' => $subject['mapped_industry_skills'] ?? [],
                'source' => 'generated',
            ];
            $guestSubjectRows[] = $subjectRow;
        }
        $runs[] = [
            'details' => [
                'id' => 'guest',
                'program' => $guestDraft['program'] ?? 'BSIT',
                'prompt' => $guestDraft['prompt'] ?? '',
                'model_name' => $guestDraft['model_name'] ?? 'gemini-3.5-flash-lite',
                'status' => 'draft',
                'generated_at' => date('Y-m-d H:i:s'),
                'created_by_username' => 'Guest trial',
                'generation_mode' => $guestDraft['generation_mode'] ?? null,
            ],
            'subjects' => $guestSubjectRows,
        ];
    }
}

$toolsByCourseTitle = [];
$toolsRecommendationError = '';
$courseTitles = [];
foreach ($runs as $runData) {
    foreach ($runData['subjects'] as $subject) {
        $title = trim((string)($subject['subject_title'] ?? ''));
        if ($title !== '') {
            $courseTitles[$title] = true;
        }
    }
}
if ($courseTitles) {
    $titlesFile = tempnam(sys_get_temp_dir(), 'curriculum-course-titles-');
    try {
        $pythonExe = python_executable();
        $encodedTitles = json_encode(array_keys($courseTitles), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($titlesFile === false || $encodedTitles === false
            || file_put_contents($titlesFile, $encodedTitles) === false) {
            throw new RuntimeException('Course tool recommendations could not be prepared.');
        }
        $recommendationOutput = run_command_with_api_key(
            [$pythonExe, __DIR__ . '/recommended_tools.py', '--titles-file', $titlesFile],
            null
        );
        $decodedRecommendations = json_decode($recommendationOutput, true);
        if (!is_array($decodedRecommendations)) {
            throw new RuntimeException('Course tool recommendations returned invalid data.');
        }
        foreach (array_keys($courseTitles) as $courseTitle) {
            if (!isset($decodedRecommendations[$courseTitle]) || !is_array($decodedRecommendations[$courseTitle])) {
                throw new RuntimeException('Course tool recommendations returned an incomplete result.');
            }
        }
        $toolsByCourseTitle = $decodedRecommendations;
    } catch (RuntimeException $error) {
        error_log('Generated curriculum tool recommendations failed: ' . $error->getMessage());
        $toolsRecommendationError = 'Recommended tools could not be loaded. Please reload the page to try again.';
    } finally {
        if (is_string($titlesFile) && file_exists($titlesFile)) {
            @unlink($titlesFile);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generated Curriculum Drafts</title>
    <link rel="stylesheet" href="assets/theme.css">
    <?php require __DIR__ . '/partials/guest_draft_lifecycle_head.php'; ?>
</head>
<?php $activePage = 'generate'; require __DIR__ . '/partials/dashboard_shell_start.php'; ?>
        <h1>Generated Curriculum Drafts</h1>
        <?php if (!$hasApiKeyAvailable): ?>
            <div class="notice warning" role="status"><?= htmlspecialchars(GEMINI_API_KEY_REQUIRED_MESSAGE, ENT_QUOTES, 'UTF-8') ?> <a href="#api_key_value">Open key settings</a></div>
        <?php endif; ?>
        <?php if ($generationMessage !== ''): ?>
            <div class="alert success" role="status">
                <?= htmlspecialchars($generationMessage) ?>
            </div>
        <?php endif; ?>
        <?php if ($generationError !== ''): ?>
            <div class="alert error" role="alert">
                <?= htmlspecialchars($generationError) ?>
            </div>
        <?php endif; ?>
        <?php if ($toolsRecommendationError !== ''): ?>
            <div class="alert error" role="alert"><?= htmlspecialchars($toolsRecommendationError, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <section class="card generation-form-panel" id="generate-curriculum">
            <div class="panel-body">
                <form method="post" class="generation-form" data-loading="generate">
                    <?= csrf_token_field() ?>
                    <?php if ($isGuestMode): ?><input type="hidden" name="guest_tab_id" value=""><?php endif; ?>
                    <input type="hidden" name="generate_action" value="generate">
                    <div class="form-row">
                        <div class="field">
                            <label for="generate_program">Program</label>
                            <select id="generate_program" name="generate_program">
                                <option value="BSIT" <?= $generationProgram === 'BSIT' ? 'selected' : '' ?>>BSIT</option>
                                <option value="BSCS" <?= $generationProgram === 'BSCS' ? 'selected' : '' ?>>BSCS</option>
                            </select>
                        </div>
                        <div class="field wide">
                            <label for="generate_prompt">Generation request</label>
                            <textarea id="generate_prompt" name="generate_prompt" placeholder="Describe focus areas, progression, or other curriculum goals."><?= htmlspecialchars($generationPrompt, ENT_QUOTES, 'UTF-8') ?></textarea>
                        </div>
                    </div>
                    <div class="generation-actions"><button type="submit" <?= $isGuestMode && $guestTrialLimitReached ? 'disabled' : '' ?>>Generate draft</button></div>
                </form>
            </div>
        </section>

        <?php if ($isGuestMode): ?>
            <div class="notice info" role="status">Guest trial mode: 3 total attempts across Generate and Enhance. Drafts are temporary and are cleared when this page is reloaded or guest mode is opened in another tab. Create an account to keep a draft.</div>
        <?php endif; ?>
        <div class="draft-toolbar" id="history" aria-label="Filter generated drafts">
                <div class="field draft-search-field">
                    <label for="draft-search">Search drafts</label>
                    <input id="draft-search" type="search" placeholder="Run number, program, or prompt">
                </div>
                <div class="field">
                    <label for="draft-mode-filter">Generation mode</label>
                    <select id="draft-mode-filter">
                        <option value="">All modes</option>
                        <option value="online">Online Template</option>
                        <option value="offline">Offline template</option>
                    </select>
                </div>
                <div class="field">
                    <label for="draft-program-filter">Program</label>
                    <select id="draft-program-filter">
                        <option value="">All programs</option>
                        <?php $draftPrograms = []; foreach ($runs as $programRunData) { $draftProgram = (string)($programRunData['details']['program'] ?? ''); if ($draftProgram !== '' && !in_array($draftProgram, $draftPrograms, true)) { $draftPrograms[] = $draftProgram; } } foreach ($draftPrograms as $draftProgram): ?>
                            <option value="<?= htmlspecialchars($draftProgram, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($draftProgram) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <p class="draft-count" id="draft-count" aria-live="polite">Showing <?= count($runs) ?> of <?= count($runs) ?> drafts</p>
        </div>
        <div class="empty draft-empty" id="draft-empty" <?= $runs ? 'hidden' : '' ?>><?= $runs ? 'No drafts match these filters.' : 'No generated curriculum drafts found yet.' ?></div>
        <?php foreach ($runs as $runData): ?>
                <?php
                    $run = $runData['details'];
                    $subjectsByYear = [];
                    $runOfflineFallback = false;
                    foreach ($runData['subjects'] as $subject) {
                        $subjectsByYear[(string)$subject['year']][(string)$subject['term']][] = $subject;
                        if (strpos(strtolower((string)($subject['rationale'] ?? '')), '[offline template fallback]') !== false) {
                            $runOfflineFallback = true;
                        }
                    }
                    ksort($subjectsByYear, SORT_NUMERIC);
                    foreach ($subjectsByYear as &$yearTerms) {
                        ksort($yearTerms, SORT_NUMERIC);
                    }
                    unset($yearTerms);
                    $runGenerationMode = in_array($run['generation_mode'] ?? null, ['online', 'offline'], true)
                        ? (string)$run['generation_mode']
                        : 'unknown';
                    $runGenerationLabel = draft_management_generation_mode_label($run['generation_mode'] ?? null);
                    $runId = (int)$run['id'];
                    $runTitle = trim((string)($run['user_title'] ?? '')) ?: (string)$run['program'];
                    $chatTargetedRun = isset($_GET['chat_run_id']) && (int)$_GET['chat_run_id'] === $runId;
                    $updateTargetedRun = isset($_GET['run_id']) && (int)$_GET['run_id'] === $runId;
                    $isNewestRun = $runId === (int)array_key_first($runs);
                    $hasRequestedRun = isset($_GET['chat_run_id']) || isset($_GET['run_id']);
                    $runIsOpen = $chatTargetedRun || $updateTargetedRun || (!$hasRequestedRun && $isNewestRun);
                    $runSearchText = 'Run #' . $runId . ' ' . $runTitle . ' ' . (string)$run['program'] . ' ' . (string)$run['prompt'];
                ?>
                <details class="card draft-item" id="run-<?= $runId ?>" data-generation-mode="<?= htmlspecialchars($runGenerationMode, ENT_QUOTES, 'UTF-8') ?>" data-program="<?= htmlspecialchars((string)$run['program'], ENT_QUOTES, 'UTF-8') ?>" data-search="<?= htmlspecialchars($runSearchText, ENT_QUOTES, 'UTF-8') ?>" <?= $runIsOpen ? 'open' : '' ?>>
                    <summary class="draft-summary">
                        <span class="draft-chevron" aria-hidden="true"></span>
                        <span class="draft-summary-main">
                            <span class="draft-program"><?= htmlspecialchars($runTitle, ENT_QUOTES, 'UTF-8') ?></span>
                            <span class="draft-run-number">Run #<?= $runId ?></span>
                            <span class="draft-badges">
                                <span class="generation-mode"><span class="generation-mode-dot generation-mode-<?= htmlspecialchars($runGenerationMode, ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"></span><?= htmlspecialchars($runGenerationLabel, ENT_QUOTES, 'UTF-8') ?></span>
                            </span>
                            <span class="draft-date"><strong>Generated:</strong> <?= htmlspecialchars((string)$run['generated_at']) ?></span>
                            <span class="draft-date draft-created-by"><strong>Created by:</strong> <?= htmlspecialchars(trim((string)($run['created_by_username'] ?? '')) !== '' ? (string)$run['created_by_username'] : 'Unattributed') ?></span>
                            <span class="draft-prompt" title="<?= htmlspecialchars((string)$run['prompt'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string)$run['prompt']) ?></span>
                        </span>
                    </summary>
                    <div class="draft-actions pdf-export-hide">
                        <?php if (current_user() !== null): ?>
                            <div class="draft-actions-primary">
                                <button type="button" class="button-secondary" data-export-pdf>Export to PDF</button>
                            </div>
                        <?php endif; ?>
                        <?php if (run_access_can_delete(current_user(), $run)): ?>
                        <div class="draft-actions-danger">
                            <button type="button" class="btn-danger" data-open-delete-dialog="delete-dialog-<?= $runId ?>">Delete draft</button>
                            <dialog class="confirm-dialog" id="delete-dialog-<?= $runId ?>" aria-label="Confirm deletion">
                                <div class="confirm-dialog-content">
                                    <div class="confirm-dialog-header">
                                        <p class="eyebrow">Confirm deletion</p>
                                    </div>
                                    <div class="confirm-dialog-target">
                                        <div><strong>Run #<?= $runId ?></strong> · <?= htmlspecialchars((string)$run['program']) ?></div>
                                        <div class="meta">Generated: <?= htmlspecialchars((string)$run['generated_at']) ?></div>
                                    </div>
                                    <p class="confirm-dialog-warning">
                                        <strong>Warning:</strong> This permanently removes this draft, including its course subjects and chat messages. Historical records are retained. This action cannot be undone.
                                    </p>
                                    <div class="confirm-dialog-actions">
                                        <button type="button" class="button-secondary" data-close-delete-dialog>Cancel</button>
                                        <form method="post">
                                            <?= csrf_token_field() ?>
                                            <input type="hidden" name="delete_action" value="delete">
                                            <input type="hidden" name="delete_run_id" value="<?= $runId ?>">
                                            <button type="submit" class="button-danger-solid">Delete permanently</button>
                                        </form>
                                    </div>
                                </div>
                            </dialog>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php if ($runOfflineFallback): ?>
                        <div class="alert warning draft-fallback-warning" role="status">This run used an offline template because a Gemini request was unavailable. Review its content before use.</div>
                    <?php endif; ?>
                    <div class="run-header">
                        <div class="run-title">
                            <h2><?= htmlspecialchars($runTitle, ENT_QUOTES, 'UTF-8') ?></h2>
                            <span class="meta">Run #<?= $runId ?></span>
                        </div>
                        <div class="run-meta meta"><span><strong>Program:</strong> <?= htmlspecialchars((string)$run['program']) ?></span><span><strong>Generation:</strong> <?= htmlspecialchars($runGenerationLabel, ENT_QUOTES, 'UTF-8') ?></span><span><strong>Generated:</strong> <?= htmlspecialchars($run['generated_at']) ?></span><?php if (!empty($run['updated_at'])): ?><span><strong>Updated:</strong> <?= htmlspecialchars((string)$run['updated_at']) ?></span><?php endif; ?><span><strong>Created by:</strong> <?= htmlspecialchars(trim((string)($run['created_by_username'] ?? '')) !== '' ? (string)$run['created_by_username'] : 'Unattributed') ?></span><span><strong>Prompt:</strong> <?= htmlspecialchars($run['prompt']) ?></span></div>
                    </div>
                    <p class="notice warning advisory-disclaimer">Advisory recommendations only: verify all content before any use.</p>
                    <?php if (draft_management_can_update(current_user(), $run)): ?>
                    <form method="post" class="draft-update-form pdf-export-hide">
                        <?= csrf_token_field() ?>
                        <input type="hidden" name="draft_update_action" value="save">
                        <input type="hidden" name="draft_update_run_id" value="<?= $runId ?>">
                        <div class="field"><label for="user_title_<?= $runId ?>">Title (optional)</label><input id="user_title_<?= $runId ?>" name="user_title" maxlength="150" value="<?= htmlspecialchars((string)($run['user_title'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"></div>
                        <div class="field notes"><label for="user_notes_<?= $runId ?>">Personal notes</label><textarea id="user_notes_<?= $runId ?>" name="user_notes" maxlength="5000"><?= htmlspecialchars((string)($run['user_notes'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea></div>
                        <button type="submit">Save details</button>
                    </form>
                    <?php endif; ?>
                    <div class="curriculum">
                        <?php foreach ($subjectsByYear as $year => $terms): ?>
                            <section aria-labelledby="year-<?= $runId ?>-<?= htmlspecialchars($year) ?>">
                                <h3 class="year-heading" id="year-<?= $runId ?>-<?= htmlspecialchars($year) ?>">Year <?= htmlspecialchars($year) ?></h3>
                                <div class="terms">
                                    <?php foreach ($terms as $term => $subjects): ?>
                                        <div class="term">
                                            <div class="term-heading"><strong><?= htmlspecialchars($term === '1' ? 'First Term' : ($term === '2' ? 'Second Term' : 'Term ' . $term)) ?></strong><span><?= count($subjects) ?> courses</span></div>
                                            <table class="course-table"><thead><tr><th>Code</th><th>Course title</th><th class="units">Units</th></tr></thead><tbody>
                                                <?php foreach ($subjects as $subject): ?>
                                                    <?php $prerequisites = trim((string)$subject['prerequisites']); $skillValue = $subject['mapped_industry_skills'] ?? []; $skills = is_array($skillValue) ? $skillValue : json_decode((string)$skillValue, true); $topicValue = $subject['topics'] ?? []; $topics = is_array($topicValue) ? $topicValue : json_decode((string)$topicValue, true); if (!is_array($skills)) { $skills = []; } if (!is_array($topics)) { $topics = []; } $description = trim((string)($subject['description'] ?? '')); if ($description === '') { $description = trim((string)$subject['rationale']); } ?>
                                                    <tr><td><span class="course-code"><?= htmlspecialchars((string)$subject['subject_code']) ?></span></td><td><div class="course-title"><?= htmlspecialchars((string)$subject['subject_title']) ?></div><?php if (current_user() !== null): ?><button type="button" class="ask-subject" data-chat-target="chat_message_<?= $runId ?>" data-prompt="<?= htmlspecialchars('Why did you place "' . (string)$subject['subject_title'] . '" in Year ' . (string)$subject['year'] . ' Term ' . (string)$subject['term'] . '?', ENT_QUOTES, 'UTF-8') ?>">Ask about this subject</button><?php endif; ?><div class="course-description"><?= htmlspecialchars($description) ?></div><?php if (!empty($topics)): ?><ul class="course-topics"><?php foreach ($topics as $topic): ?><li><?= htmlspecialchars((string)$topic) ?></li><?php endforeach; ?></ul><?php endif; ?><?php $toolRecommendations = $toolsByCourseTitle[trim((string)$subject['subject_title'])] ?? []; if (is_array($toolRecommendations) && $toolRecommendations): ?><div class="course-description course-description-labeled"><strong>Recommended tools/apps:</strong><span><?php foreach ($toolRecommendations as $toolRecommendation): ?><?= htmlspecialchars((string)($toolRecommendation['tool'] ?? ''), ENT_QUOTES, 'UTF-8') ?> — <?= htmlspecialchars((string)($toolRecommendation['reason'] ?? ''), ENT_QUOTES, 'UTF-8') ?><?php if (!empty($toolRecommendation['documentation']) && is_array($toolRecommendation['documentation'])): ?> (<?php foreach ($toolRecommendation['documentation'] as $documentationIndex => $documentation): ?><?= $documentationIndex > 0 ? '; ' : '' ?><a href="<?= htmlspecialchars((string)($documentation['url'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer"><?= htmlspecialchars((string)($documentation['label'] ?? 'Documentation'), ENT_QUOTES, 'UTF-8') ?></a><?php endforeach; ?>)<?php endif; ?><br><?php endforeach; ?></span></div><?php endif; ?><div class="course-meta"><span><strong>Prerequisite:</strong> <?= $prerequisites !== '' ? htmlspecialchars($prerequisites) : 'None' ?></span><?php if (!empty($skills)): ?><span><strong>Mapped skills:</strong> <?php foreach ($skills as $skillIndex => $skill): ?><?= $skillIndex > 0 ? ', ' : '' ?><?= htmlspecialchars((string)$skill) ?><?php endforeach; ?></span><?php endif; ?></div></td><td class="units"><?= htmlspecialchars((string)$subject['units']) ?></td></tr>
                                                <?php endforeach; ?>
                                            </tbody></table>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </section>
                        <?php endforeach; ?>
                    </div>
                    <?php $currentChat = $chatByRun[$runId] ?? []; ?>
                <?php if (current_user() !== null && $hasApiKeyAvailable): ?>
                <div class="chat-panel pdf-export-hide">
                    <h3>Ask about this curriculum draft</h3>
                    <?php if (!empty($currentChat)): ?>
                        <div class="chat-history">
                            <?php foreach ($currentChat as $chat): ?>
                                <div class="chat-message <?= $chat['role'] === 'assistant' ? 'assistant' : 'user' ?>">
                                    <span class="meta"><strong><?= htmlspecialchars($chat['role'] === 'assistant' ? 'Assistant' : (trim((string)($chat['sender_username'] ?? '')) !== '' ? (string)$chat['sender_username'] : 'Unattributed sender')) ?></strong> · <?= htmlspecialchars((string)$chat['created_at']) ?></span>
                                    <?= htmlspecialchars((string)$chat['message']) ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="meta">Ask a question or request an edit for this draft.</p>
                    <?php endif; ?>
                    <div class="suggested-prompts" aria-label="Suggested prompts">
                        <?php foreach ([
                            'Why was this curriculum structured this way?',
                            'Which weak industry skills does this curriculum still not cover well?',
                            'Suggest one elective that would strengthen this curriculum.',
                            'Are there any prerequisite issues in this sequence?'
                        ] as $suggestedPrompt): ?>
                            <button type="button" class="suggested-prompt" data-chat-target="chat_message_<?= (int)$run['id'] ?>" data-prompt="<?= htmlspecialchars($suggestedPrompt, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($suggestedPrompt) ?></button>
                        <?php endforeach; ?>
                    </div>
                    <form method="post" class="chat-form" data-loading="chat">
                        <?= csrf_token_field() ?>
                        <input type="hidden" name="chat_action" value="send">
                        <input type="hidden" name="chat_run_id" value="<?= $runId ?>">
                        <textarea id="chat_message_<?= $runId ?>" name="chat_message" placeholder="Ask a question or request an edit..." required></textarea>
                        <button type="submit">Send Question / Request Edit</button>
                        <p class="loading-inline" data-loading-status role="status" aria-live="polite" hidden>Working...</p>
                    </form>
                </div>
                <?php elseif (current_user() !== null): ?>
                <div class="notice warning" role="status"><?= htmlspecialchars(GEMINI_API_KEY_REQUIRED_MESSAGE, ENT_QUOTES, 'UTF-8') ?> <a href="#api_key_value">Open key settings</a></div>
                <?php endif; ?>
                </details>
        <?php endforeach; ?>
<?php require __DIR__ . '/partials/loading_overlay.php'; ?>
<?php require __DIR__ . '/partials/dashboard_shell_end.php'; ?>
    <script src="assets/jspdf.umd.min.js"></script>
    <script src="assets/jspdf.plugin.autotable.min.js"></script>
    <script src="assets/pdf-export.js"></script>
    <script src="assets/loading-overlay.js"></script>
    <script>
        var draftItems = Array.prototype.slice.call(document.querySelectorAll('.draft-item'));
        var draftSearch = document.getElementById('draft-search');
        var draftModeFilter = document.getElementById('draft-mode-filter');
        var draftProgramFilter = document.getElementById('draft-program-filter');
        var draftCount = document.getElementById('draft-count');
        var draftEmpty = document.getElementById('draft-empty');

        function filterDrafts() {
            var searchTerm = draftSearch.value.trim().toLowerCase();
            var selectedMode = draftModeFilter.value;
            var selectedProgram = draftProgramFilter.value;
            var visibleCount = 0;

            draftItems.forEach(function (draft) {
                var matches = draft.dataset.search.toLowerCase().indexOf(searchTerm) !== -1
                    && (!selectedMode || draft.dataset.generationMode === selectedMode)
                    && (!selectedProgram || draft.dataset.program === selectedProgram);
                draft.hidden = !matches;
                if (matches) {
                    visibleCount += 1;
                }
            });

            draftCount.textContent = 'Showing ' + visibleCount + ' of ' + draftItems.length + ' drafts';
            draftEmpty.hidden = visibleCount !== 0;
        }

        if (draftSearch && draftModeFilter && draftProgramFilter && draftCount && draftEmpty) {
            draftSearch.addEventListener('input', filterDrafts);
            draftModeFilter.addEventListener('change', filterDrafts);
            draftProgramFilter.addEventListener('change', filterDrafts);

            var deepLinkDraft = window.location.hash ? document.getElementById(window.location.hash.slice(1)) : null;
            var chatRunId = new URLSearchParams(window.location.search).get('chat_run_id');
            var scrollTarget = deepLinkDraft || (chatRunId ? document.getElementById('run-' + chatRunId) : null);
            if (scrollTarget && scrollTarget.classList.contains('draft-item')) {
                scrollTarget.open = true;
                window.requestAnimationFrame(function () {
                    scrollTarget.scrollIntoView({ block: 'start' });
                });
            }
        }

        document.querySelectorAll('[data-chat-target][data-prompt]').forEach(function (button) {
            button.addEventListener('click', function () {
                var textarea = document.getElementById(button.dataset.chatTarget);
                if (textarea) {
                    textarea.value = button.dataset.prompt;
                    textarea.focus();
                }
            });
        });

        document.querySelectorAll('[data-open-delete-dialog]').forEach(function (button) {
            button.addEventListener('click', function () {
                var dialogId = button.dataset.openDeleteDialog;
                var dialog = document.getElementById(dialogId);
                if (dialog && typeof dialog.showModal === 'function') {
                    dialog.showModal();
                }
            });
        });

        document.querySelectorAll('[data-close-delete-dialog]').forEach(function (button) {
            button.addEventListener('click', function () {
                var dialog = button.closest('dialog');
                if (dialog && typeof dialog.close === 'function') {
                    dialog.close();
                }
            });
        });

        document.querySelectorAll('dialog.confirm-dialog').forEach(function (dialog) {
            dialog.addEventListener('click', function (event) {
                var rect = dialog.getBoundingClientRect();
                var isInDialog = (rect.top <= event.clientY && event.clientY <= rect.top + rect.height
                    && rect.left <= event.clientX && event.clientX <= rect.left + rect.width);
                if (!isInDialog) {
                    dialog.close();
                }
            });
        });
    </script>
</body>
</html>
