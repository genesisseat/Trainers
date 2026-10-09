<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/draft_management.php';
if (current_user() === null
    && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET'
    && ($_GET['guest_draft_clear'] ?? '') === '1') {
    clear_guest_trial_draft();
}
$formatReviewSource = static function (string $source): string {
    $normalizedSource = strtolower(str_replace('\\', '/', $source));
    if (strpos($normalizedSource, 'curriculum_dataset_with_ids.csv') !== false) {
        return 'Curriculum benchmark data';
    }
    if (strpos($normalizedSource, '03_industry_skills_data.md') !== false) {
        return 'Industry skill synthesis data';
    }
    return $source;
};
$isSafePostingUrl = static function (string $url): bool {
    if ($url === '' || preg_match('/\s/', $url) === 1 || filter_var($url, FILTER_VALIDATE_URL) === false) {
        return false;
    }
    $parts = parse_url($url);
    return is_array($parts)
        && isset($parts['scheme'], $parts['host'])
        && in_array(strtolower((string)$parts['scheme']), ['http', 'https'], true)
        && trim((string)$parts['host']) !== '';
};
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
$isUserEnhancementSubmission = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
    && trim((string)($_POST['enhance_action'] ?? '')) === 'enhance'
    && trim((string)($_POST['enhance_delete_action'] ?? '')) === ''
    && trim((string)($_POST['enhancement_chat_action'] ?? '')) === ''
    && trim((string)($_POST['draft_update_action'] ?? '')) === '';
$isUserEnhancementChatSubmission = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
    && trim((string)($_POST['enhancement_chat_action'] ?? '')) === 'send'
    && trim((string)($_POST['enhance_delete_action'] ?? '')) === ''
    && trim((string)($_POST['enhance_action'] ?? '')) === ''
    && trim((string)($_POST['draft_update_action'] ?? '')) === '';
$isGuestEnhancementSubmission = $isGuestMode
    && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
    && trim((string)($_POST['enhance_action'] ?? '')) === 'enhance'
    && trim((string)($_POST['enhance_delete_action'] ?? '')) === ''
    && trim((string)($_POST['enhancement_chat_action'] ?? '')) === ''
    && trim((string)($_POST['draft_update_action'] ?? '')) === '';
$isDraftUpdateSubmission = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
    && trim((string)($_POST['draft_update_action'] ?? '')) === 'save'
    && trim((string)($_POST['enhance_action'] ?? '')) === ''
    && trim((string)($_POST['enhance_delete_action'] ?? '')) === ''
    && trim((string)($_POST['enhancement_chat_action'] ?? '')) === '';
$isUserDeleteSubmission = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
    && trim((string)($_POST['enhance_delete_action'] ?? '')) === 'delete'
    && trim((string)($_POST['enhance_action'] ?? '')) === ''
    && trim((string)($_POST['enhancement_chat_action'] ?? '')) === ''
    && trim((string)($_POST['draft_update_action'] ?? '')) === '';
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && !has_role('admin', 'super_admin')
    && !$isUserEnhancementSubmission
    && !$isUserEnhancementChatSubmission
    && !$isGuestEnhancementSubmission
    && !$isDraftUpdateSubmission
    && !$isUserDeleteSubmission
) {
    http_response_code(403);
    exit('Forbidden: your account does not have permission to perform this action.');
}

set_time_limit(300);
$dbPath = __DIR__ . '/curriculum_matching.db';
$enhanceAction = trim((string)($_POST['enhance_action'] ?? ''));
$enhanceProgram = strtoupper(trim((string)($_POST['enhance_program'] ?? 'BSIT')));
if (!in_array($enhanceProgram, ['BSIT', 'BSCS'], true)) {
    $enhanceProgram = 'BSIT';
}
$specializationsByProgram = [
    'BSIT' => [
        'Software Development & Programming',
        'Cybersecurity & Information Assurance',
        'Cloud Computing & Virtualization',
        'Data Analytics & Database Management',
        'Network Administration & Infrastructure',
    ],
    'BSCS' => [
        'Artificial Intelligence & Machine Learning',
        'Cybersecurity',
        'Data Science & Big Data',
        'Software Engineering',
        'Cloud Computing',
    ],
];
$enhanceSpecializationChoice = trim((string)($_POST['enhance_specialization'] ?? ''));
$enhanceSpecialization = trim((string)($_POST['enhance_specialization'] ?? ''));
$specializationError = false;
if ($enhanceSpecialization !== '' && !in_array($enhanceSpecialization, $specializationsByProgram[$enhanceProgram], true)) {
    $relatedSpecializationPattern = '/\\b(?:comput(?:er|ing|ational)|software|programming|coding|\\bit\\b|information|technology|tech|cyber|security|data|database|analytics|network|cloud|virtuali[sz]ation|infrastructure|systems?|web|mobile|application|artificial intelligence|machine learning|deep learning|algorithm|robotics|automation|devops|reliability|architecture|game development|graphics|human[- ]computer|hci|ux|ui|blockchain|distributed|embedded|internet of things|iot|informatics|digital forensics|quality assurance|testing|multimedia|digital media|cryptography|privacy|business intelligence|erp)\\b/i';
    $specializationError = preg_match($relatedSpecializationPattern, $enhanceSpecialization) !== 1;
}
$enhancePrompt = trim((string)($_POST['enhance_prompt'] ?? 'Review this curriculum for program fit, realistic year progression, outdated courses, unnecessary courses, and recommended edits.'));
$enhanceSelectedYears = array_values(array_intersect(
    ['1', '2', '3', '4'],
    array_map('strval', is_array($_POST['enhance_years'] ?? null) ? $_POST['enhance_years'] : [])
));
$enhancementChatAction = trim((string)($_POST['enhancement_chat_action'] ?? ''));
$enhancementChatRunId = isset($_POST['enhancement_chat_run_id']) ? (int)$_POST['enhancement_chat_run_id'] : null;
$enhancementChatMessage = trim((string)($_POST['enhancement_chat_message'] ?? ''));
$enhanceDeleteAction = trim((string)($_POST['enhance_delete_action'] ?? ''));
$enhanceDeleteRunId = isset($_POST['enhance_delete_run_id']) ? (int)$_POST['enhance_delete_run_id'] : null;
$draftUpdateAction = trim((string)($_POST['draft_update_action'] ?? ''));
$draftUpdateRunIdInput = $_POST['draft_update_run_id'] ?? null;
$draftUpdateRunId = is_string($draftUpdateRunIdInput) && ctype_digit($draftUpdateRunIdInput)
    ? (int)$draftUpdateRunIdInput
    : null;
$enhanceError = '';
$enhanceSuccess = '';
$enhancementChatError = '';
$currentUser = current_user();
$currentUserApiKey = $currentUser !== null ? get_user_api_key((int)$currentUser['id']) : null;
$apiKeyPolicy = gemini_api_key_policy(
    $currentUser,
    $currentUserApiKey,
    has_shared_api_key(),
    $isGuestMode
);
$hasApiKeyAvailable = $apiKeyPolicy['allowed'];
$apiKeyMessage = '';
$accountMenuOpen = false;
$apiKeyMessageType = 'warning';
$requestedRunId = $_GET['run_id'] ?? null;
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $requestedRunId = match (true) {
        $enhanceDeleteAction === 'delete' => $_POST['enhance_delete_run_id'] ?? null,
        $draftUpdateAction === 'save' => $draftUpdateRunIdInput,
        $enhancementChatAction === 'send' => $_POST['enhancement_chat_run_id'] ?? null,
        default => $requestedRunId,
    };
}
if (
    !$isGuestMode
    && ($requestedRunId !== null || $enhanceDeleteAction === 'delete' || $draftUpdateAction === 'save' || $enhancementChatAction === 'send')
) {
    $accessDb = new SQLite3($dbPath, SQLITE3_OPEN_READONLY);
    $accessDb->enableExceptions(true);
    $requestedRun = run_access_require($accessDb, $requestedRunId, $currentUser);
    if (($requestedRun['source'] ?? '') !== 'enhanced') {
        $accessDb->close();
        run_access_not_found();
    }
    if ($enhanceDeleteAction === 'delete' && !run_access_can_delete($currentUser, $requestedRun)) {
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

if (isset($_SESSION['enhance_flash_success'])) {
    $enhanceSuccess = (string)$_SESSION['enhance_flash_success'];
    unset($_SESSION['enhance_flash_success']);
}
if (isset($_SESSION['enhance_flash_error'])) {
    $enhanceError = (string)$_SESSION['enhance_flash_error'];
    unset($_SESSION['enhance_flash_error']);
}
if (!empty($_SESSION['open_api_key_settings'])) {
    $accountMenuOpen = true;
    $apiKeyMessage = $enhanceError !== '' ? $enhanceError : GEMINI_API_KEY_REQUIRED_MESSAGE;
    unset($_SESSION['open_api_key_settings']);
}
if ($isGuestMode && isset($_SESSION['guest_enhancement_flash'])) {
    $enhanceSuccess = (string)$_SESSION['guest_enhancement_flash'];
    unset($_SESSION['guest_enhancement_flash']);
}

if ($enhancementChatAction === 'send' && current_user() === null) {
    http_response_code(403);
    exit('Forbidden: sign in to use enhancement chat.');
}

if ($enhanceDeleteAction === 'delete') {
    try {
        $deleteDb = new SQLite3($dbPath);
        $deleteDb->enableExceptions(true);
        $deleteRun = run_access_require($deleteDb, $enhanceDeleteRunId, $currentUser);
        if (($deleteRun['source'] ?? '') !== 'enhanced') {
            $deleteDb->close();
            run_access_not_found();
        }
        run_access_delete($deleteDb, $enhanceDeleteRunId, $currentUser);
        $deleteDb->close();
        $_SESSION['enhance_flash_success'] = 'Enhanced draft and its related curriculum/chat data have been permanently deleted.';
        header('Location: enhanced_curriculum_generated.php');
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
        $enhanceError = 'Failed to delete enhanced draft #' . $enhanceDeleteRunId . ': ' . $e->getMessage();
    }
}

$enhancedRunId = !$isGuestMode && isset($_GET['run_id']) ? (int)$_GET['run_id'] : null;
$enhancementRuns = [];
$enhancementChatByRun = [];
$enhancementCoursesByRun = [];
$formSubjects = [
    '1' => [['subject_title' => '', 'description' => '']],
    '2' => [['subject_title' => '', 'description' => '']],
    '3' => [['subject_title' => '', 'description' => '']],
    '4' => [['subject_title' => '', 'description' => '']],
];

if (!$isGuestMode) {
    $schemaDb = new SQLite3($dbPath);
    $schemaDb->enableExceptions(true);
$subjectColumns = [];
$subjectColumnResult = $schemaDb->query('PRAGMA table_info(generated_curriculum_subjects)');
while ($subjectColumn = $subjectColumnResult->fetchArray(SQLITE3_ASSOC)) {
    $subjectColumns[] = $subjectColumn['name'];
}
if (!in_array('source', $subjectColumns, true)) {
    $schemaDb->exec('ALTER TABLE generated_curriculum_subjects ADD COLUMN source TEXT');
}
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
    $updateDb->enableExceptions(true);
    $draftOwner = run_access_require($updateDb, $draftUpdateRunId, $currentUser);
    if (($draftOwner['source'] ?? '') !== 'enhanced'
        || !run_access_can_update($currentUser, $draftOwner)) {
        $updateDb->close();
        run_access_not_found();
    }

    $userTitle = draft_management_clean_text($_POST['user_title'] ?? null, 150);
    $userNotes = draft_management_clean_text($_POST['user_notes'] ?? null, 5000);
    $finalizedValue = draft_management_finalized_value($_POST['is_finalized'] ?? null);
    if ($userTitle === null || $userNotes === null || $finalizedValue === null) {
        $updateDb->close();
        http_response_code(400);
        exit('Invalid draft update. Check the title, notes, and finalized value.');
    }

    $updateStmt = $updateDb->prepare(
        'UPDATE generated_curriculum_runs
         SET user_title = :title, user_notes = :notes, is_finalized = :finalized,
             updated_at = CURRENT_TIMESTAMP
         WHERE id = :id'
    );
    $updateStmt->bindValue(':title', $userTitle !== '' ? $userTitle : null, $userTitle !== '' ? SQLITE3_TEXT : SQLITE3_NULL);
    $updateStmt->bindValue(':notes', $userNotes, SQLITE3_TEXT);
    $updateStmt->bindValue(':finalized', $finalizedValue, SQLITE3_INTEGER);
    $updateStmt->bindValue(':id', $draftUpdateRunId, SQLITE3_INTEGER);
    $updateStmt->execute();
    $updateDb->close();

    $_SESSION['enhance_flash_success'] = 'Draft details updated.';
    header('Location: enhanced_curriculum_generated.php?run_id=' . $draftUpdateRunId . '#run-' . $draftUpdateRunId);
    exit;
}

if ($enhanceAction === 'enhance') {
    $titlesByYear = $_POST['subject_title'] ?? [];
    $descriptionsByYear = $_POST['subject_description'] ?? [];
    $formSubjects = ['1' => [], '2' => [], '3' => [], '4' => []];
    $userSubjects = [];

    if (is_array($titlesByYear) && is_array($descriptionsByYear)) {
        foreach (['1', '2', '3', '4'] as $year) {
            $titles = is_array($titlesByYear[$year] ?? null) ? $titlesByYear[$year] : [];
            $descriptions = is_array($descriptionsByYear[$year] ?? null) ? $descriptionsByYear[$year] : [];
            foreach ($titles as $index => $title) {
                $cleanTitle = trim((string)$title);
                $cleanDescription = trim((string)($descriptions[$index] ?? ''));
                $formSubjects[$year][] = [
                    'subject_title' => $cleanTitle,
                    'description' => $cleanDescription,
                ];
                if ($cleanTitle !== '') {
                    $userSubjects[] = [
                        'subject_title' => $cleanTitle,
                        'description' => $cleanDescription,
                        'year' => $year,
                    ];
                }
            }
        }
    }

    $populatedYears = array_values(array_unique(array_column($userSubjects, 'year')));
    $targetYears = $enhanceSelectedYears ?: $populatedYears;
    $enhanceSelectedYears = $targetYears;
    $yearsWithoutSubjects = array_values(array_diff($targetYears, $populatedYears));
    $userSubjects = array_values(array_filter(
        $userSubjects,
        static fn(array $subject): bool => in_array($subject['year'], $targetYears, true)
    ));

    if ($isGuestMode && $guestTrialLimitReached) {
        $enhanceError = 'You have used all 3 guest trial attempts. Create an account to continue.';
    } elseif ($isGuestMode && !has_shared_api_key()) {
        $enhanceError = 'Guest trial is currently unavailable because the shared API key is not configured.';
    } elseif (!$hasApiKeyAvailable) {
        $enhanceError = GEMINI_API_KEY_REQUIRED_MESSAGE;
        $apiKeyMessage = GEMINI_API_KEY_REQUIRED_MESSAGE;
        $accountMenuOpen = true;
    } elseif ($specializationError) {
        $enhanceError = 'Enter a specialization related to computing, IT, or information systems.';
    } elseif ($yearsWithoutSubjects) {
        $enhanceError = 'Add at least one subject to each selected year before enhancing.';
    } elseif (count($userSubjects) < 1 || count($userSubjects) > 30) {
        $enhanceError = 'Add between 1 and 30 subjects before enhancing the curriculum.';
    } elseif ($isGuestMode) {
        set_time_limit(600);
        try {
            $guestEnhancement = guest_enhance_curriculum(
                $enhanceProgram,
                $enhanceSpecialization,
                $enhancePrompt,
                $userSubjects,
                $targetYears
            );
        } catch (RuntimeException $error) {
            error_log('Guest curriculum enhancement subprocess failed: ' . $error->getMessage());
            $guestEnhancement = null;
        }

        if (is_array($guestEnhancement)
            && isset($guestEnhancement['report'])
            && is_array($guestEnhancement['report'])
            && isset($guestEnhancement['enhanced_curriculum'])
            && is_array($guestEnhancement['enhanced_curriculum'])) {
            $_SESSION['guest_trial_draft'] = [
                'type' => 'enhanced',
                'program' => $enhanceProgram,
                'specialization' => $enhanceSpecialization,
                'prompt' => $enhancePrompt,
                'model_name' => 'gemini-3.5-flash-lite',
                'report' => $guestEnhancement['report'],
                'enhanced_curriculum' => $guestEnhancement['enhanced_curriculum'],
                'user_subjects' => $userSubjects,
            ];
            mark_guest_attempt_success();
            $_SESSION['guest_enhancement_flash'] = 'Guest enhancement review saved for this session. Create an account to keep it.';
            header('Location: enhanced_curriculum_generated.php?guest=1');
            exit;
        } else {
            $enhanceError = 'The guest trial could not complete an enhancement review. Please try again.';
        }
    } else {
        $pythonExe = __DIR__ . '/venv/Scripts/python.exe';
        $script = __DIR__ . '/user_operations.py';
        $subjectsFile = tempnam(sys_get_temp_dir(), 'curriculum_subjects_');
        if ($subjectsFile === false || !file_put_contents($subjectsFile, json_encode($userSubjects, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))) {
            $enhanceError = 'The curriculum input could not be prepared for processing.';
        } elseif (!file_exists($pythonExe) || !file_exists($script)) {
            $enhanceError = 'The curriculum enhancement runtime is not available.';
        } else {
            $runDb = new SQLite3($dbPath, SQLITE3_OPEN_READONLY);
            $previousStmt = $runDb->prepare(
                'SELECT COALESCE(MAX(id), 0)
                 FROM generated_curriculum_runs
                 WHERE created_by_user_id = :user_id'
            );
            $previousStmt->bindValue(':user_id', (int)$currentUser['id'], SQLITE3_INTEGER);
            $previousRunId = (int)$previousStmt->execute()->fetchArray(SQLITE3_NUM)[0];
            $runDb->close();

            $command = escapeshellarg($pythonExe) . ' ' . escapeshellarg($script) .
                ' --enhance --program ' . escapeshellarg($enhanceProgram) .
                ' --specialization ' . escapeshellarg($enhanceSpecialization) .
                ' --years ' . escapeshellarg(implode(',', $targetYears)) .
                ' --prompt ' . escapeshellarg($enhancePrompt) .
                ' --user-subjects-json ' . escapeshellarg($subjectsFile) .
                ' --actor-user-id ' . escapeshellarg((string)$currentUser['id']) .
                ' --actor-username ' . escapeshellarg((string)$currentUser['username']) .
                ' --output-db ' . escapeshellarg($dbPath) . ' 2>&1';
            try {
                run_command_with_api_key($command, $currentUserApiKey);
            } catch (RuntimeException $error) {
                error_log('Curriculum enhancement subprocess failed to start: ' . $error->getMessage());
                $enhanceError = 'The curriculum enhancement process could not be started.';
            }

            if ($enhanceError === '') {
                $runDb = new SQLite3($dbPath, SQLITE3_OPEN_READONLY);
                $createdRunStmt = $runDb->prepare(
                    "SELECT id FROM generated_curriculum_runs
                     WHERE id > :previous_run_id
                       AND created_by_user_id = :user_id
                       AND source = 'enhanced'
                     ORDER BY id DESC LIMIT 1"
                );
                $createdRunStmt->bindValue(':previous_run_id', $previousRunId, SQLITE3_INTEGER);
                $createdRunStmt->bindValue(':user_id', (int)$currentUser['id'], SQLITE3_INTEGER);
                $enhancedRunId = (int)$createdRunStmt->execute()->fetchArray(SQLITE3_NUM)[0];
                $runDb->close();
                if ($enhancedRunId > 0) {
                    header('Location: enhanced_curriculum_generated.php?run_id=' . $enhancedRunId);
                    exit;
                }
                $enhanceError = 'The curriculum could not be enhanced. No new results were saved.';
            }
        }
        if (is_string($subjectsFile) && file_exists($subjectsFile)) {
            @unlink($subjectsFile);
        }
    }
}

if ($enhancementChatAction === 'send' && $enhancementChatRunId !== null && $enhancementChatMessage !== '') {
    if (!$hasApiKeyAvailable) {
        $enhancementChatError = GEMINI_API_KEY_REQUIRED_MESSAGE;
        $apiKeyMessage = GEMINI_API_KEY_REQUIRED_MESSAGE;
        $accountMenuOpen = true;
        $_SESSION['enhance_flash_error'] = GEMINI_API_KEY_REQUIRED_MESSAGE;
        $_SESSION['open_api_key_settings'] = true;
    } else {
        $pythonExe = __DIR__ . '/venv/Scripts/python.exe';
        $script = __DIR__ . '/user_operations.py';
        if (file_exists($pythonExe) && file_exists($script)) {
            $command = escapeshellarg($pythonExe) . ' ' . escapeshellarg($script) .
                ' --enhancement-chat --run-id ' . escapeshellarg((string)$enhancementChatRunId) .
                ' --message ' . escapeshellarg($enhancementChatMessage) .
                ' --actor-user-id ' . escapeshellarg((string)$currentUser['id']) .
                ' --actor-username ' . escapeshellarg((string)$currentUser['username']) .
                ' --admin-access-policy ' . escapeshellarg(RUN_ACCESS_ADMIN_POLICY) .
                ' --output-db ' . escapeshellarg($dbPath) . ' 2>&1';
            try {
                run_command_with_api_key($command, $currentUserApiKey);
                header('Location: enhanced_curriculum_generated.php?run_id=' . $enhancementChatRunId);
                exit;
            } catch (RuntimeException $error) {
                error_log('Enhancement chat subprocess failed to start: ' . $error->getMessage());
                $enhancementChatError = 'The enhancement assistant could not be started.';
            }
        } else {
            $enhancementChatError = 'The enhancement results assistant is not available.';
        }
    }
}

if (!$isGuestMode) {
    $historyDb = new SQLite3($dbPath, SQLITE3_OPEN_READONLY);
    $historyDb->enableExceptions(true);
    $runScope = run_access_sql_scope($currentUser);
    $historyStmt = $historyDb->prepare(
        'SELECT id, program, prompt, model_name, status, generated_at, notes, created_by_user_id,
                created_by_username, user_title, user_notes, is_finalized, updated_at
         FROM generated_curriculum_runs r
         WHERE r.source = \'enhanced\' AND ' . $runScope['sql'] . '
         ORDER BY id DESC'
    );
    run_access_bind_scope($historyStmt, $runScope);
    $historyResult = $historyStmt->execute();
    while ($historyRow = $historyResult->fetchArray(SQLITE3_ASSOC)) {
        $report = json_decode((string)($historyRow['notes'] ?? ''), true);
        if (!is_array($report)
            || !isset($report['summary'])
            || !isset($report['subjects'])
            || !isset($report['recommendations'])) {
            continue;
        }
        $historyRow['enhancement_report'] = $report;
        $enhancementRuns[(int)$historyRow['id']] = $historyRow;
    }
    $historyDb->close();
} elseif (isset($_SESSION['guest_trial_draft'])
    && is_array($_SESSION['guest_trial_draft'])
    && ($_SESSION['guest_trial_draft']['type'] ?? '') === 'enhanced') {
    $guestDraft = $_SESSION['guest_trial_draft'];
    if (isset($guestDraft['report'], $guestDraft['enhanced_curriculum'])
        && is_array($guestDraft['report'])
        && is_array($guestDraft['enhanced_curriculum'])) {
        $guestRunId = 0;
        $enhancementRuns[$guestRunId] = [
            'id' => $guestRunId,
            'program' => (string)($guestDraft['program'] ?? 'BSIT'),
            'prompt' => (string)($guestDraft['prompt'] ?? ''),
            'model_name' => (string)($guestDraft['model_name'] ?? 'gemini-3.5-flash-lite'),
            'status' => 'draft',
            'generated_at' => date('Y-m-d H:i:s'),
            'created_by_username' => 'Guest trial',
            'is_finalized' => 0,
            'enhancement_report' => $guestDraft['report'],
        ];
        $enhancementCoursesByRun[$guestRunId] = $guestDraft['enhanced_curriculum'];
        $enhancedRunId = $guestRunId;

        foreach (['1', '2', '3', '4'] as $year) {
            $formSubjects[$year] = [];
        }
        foreach (($guestDraft['user_subjects'] ?? []) as $guestSubject) {
            if (!is_array($guestSubject)) {
                continue;
            }
            $year = (string)($guestSubject['year'] ?? '');
            if (isset($formSubjects[$year])) {
                $formSubjects[$year][] = [
                    'subject_title' => (string)($guestSubject['subject_title'] ?? ''),
                    'description' => (string)($guestSubject['description'] ?? ''),
                ];
            }
        }
    }
}

if (!$isGuestMode
    && isset($_GET['run_id'])
    && !isset($enhancementRuns[(int)$_GET['run_id']])) {
    run_access_not_found();
}

if ($enhancementChatError !== '' && $enhancementChatRunId !== null && isset($enhancementRuns[$enhancementChatRunId])) {
    $enhancedRunId = $enhancementChatRunId;
} elseif ($enhancedRunId === null || !isset($enhancementRuns[$enhancedRunId])) {
    $enhancedRunId = $enhancementRuns ? (int)array_key_first($enhancementRuns) : null;
}

if ($enhancementRuns && !$isGuestMode) {
    $runIds = implode(',', array_map('intval', array_keys($enhancementRuns)));
    $runScope = run_access_sql_scope($currentUser);
    $chatDb = new SQLite3($dbPath, SQLITE3_OPEN_READONLY);
    $chatDb->enableExceptions(true);
    $chatStmt = $chatDb->prepare(
        'SELECT c.run_id, c.role, c.message, c.created_at, c.sender_username
         FROM generated_curriculum_chat c
         INNER JOIN generated_curriculum_runs r ON r.id = c.run_id
         WHERE c.run_id IN (' . $runIds . ') AND ' . $runScope['sql'] . '
         ORDER BY c.created_at ASC, c.id ASC'
    );
    run_access_bind_scope($chatStmt, $runScope);
    $chatResult = $chatStmt->execute();
    while ($chatRow = $chatResult->fetchArray(SQLITE3_ASSOC)) {
        $enhancementChatByRun[(int)$chatRow['run_id']][] = $chatRow;
    }
    $chatDb->close();

    $courseDb = new SQLite3($dbPath, SQLITE3_OPEN_READONLY);
    $courseDb->enableExceptions(true);
    $courseStmt = $courseDb->prepare(
        'SELECT s.run_id, s.year, s.term, s.subject_code, s.subject_title, s.units, s.description, s.prerequisites,
            topics, rationale, source_colleges, mapped_industry_skills
         FROM generated_curriculum_subjects s
         INNER JOIN generated_curriculum_runs r ON r.id = s.run_id
         WHERE s.run_id IN (' . $runIds . ') AND ' . $runScope['sql'] . '
         ORDER BY s.run_id, CAST(s.year AS INTEGER), CAST(s.term AS INTEGER), s.id'
    );
    run_access_bind_scope($courseStmt, $runScope);
    $courseResult = $courseStmt->execute();
    while ($courseRow = $courseResult->fetchArray(SQLITE3_ASSOC)) {
        $enhancementCoursesByRun[(int)$courseRow['run_id']][] = $courseRow;
    }
    $courseDb->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enhance Curriculum</title>
    <link rel="stylesheet" href="assets/theme.css">
    <?php require __DIR__ . '/partials/guest_draft_lifecycle_head.php'; ?>
</head>
<?php $activePage = 'enhance'; require __DIR__ . '/partials/dashboard_shell_start.php'; ?>
        <h1>Enhance Curriculum</h1>
        <?php if ($isGuestMode): ?>
            <div class="notice info" role="status">Guest trial mode: 3 total attempts across Generate and Enhance. Drafts are temporary and are cleared when this page is reloaded or guest mode is opened in another tab. Create an account to keep a draft.</div>
        <?php endif; ?>
        <?php if (!$hasApiKeyAvailable): ?>
            <div class="notice warning" role="status"><?= htmlspecialchars(GEMINI_API_KEY_REQUIRED_MESSAGE, ENT_QUOTES, 'UTF-8') ?> <a href="#api_key_value">Open key settings</a></div>
        <?php endif; ?>
        <?php if ($enhanceSuccess !== ''): ?>
            <div class="alert success" role="status"><?= htmlspecialchars($enhanceSuccess) ?></div>
        <?php endif; ?>
        <?php if ($enhanceError !== ''): ?>
            <div class="alert warning" role="alert"><?= htmlspecialchars($enhanceError) ?></div>
        <?php endif; ?>

        <section class="card enhancement-form-panel" id="enhance-curriculum">
            <div class="panel-body">
                <form method="post" class="enhancement-form" data-loading="enhance">
                    <?= csrf_token_field() ?>
                    <?php if ($isGuestMode): ?><input type="hidden" name="guest_tab_id" value=""><?php endif; ?>
                    <input type="hidden" name="enhance_action" value="enhance">
                    <div class="enhancement-fields">
                        <div class="field"><label for="enhance_program">Program</label><select id="enhance_program" name="enhance_program"><option value="BSIT" <?= $enhanceProgram === 'BSIT' ? 'selected' : '' ?>>BSIT</option><option value="BSCS" <?= $enhanceProgram === 'BSCS' ? 'selected' : '' ?>>BSCS</option></select></div>
                        <div class="field">
                            <label for="enhance_specialization">Specialization <span class="meta">(optional)</span></label>
                            <input type="text" id="enhance_specialization" name="enhance_specialization" list="enhance_specialization_options" value="<?= htmlspecialchars($enhanceSpecialization, ENT_QUOTES, 'UTF-8') ?>" placeholder="Choose or type a specialization">
                            <datalist id="enhance_specialization_options">
                                <?php foreach ($specializationsByProgram[$enhanceProgram] as $specializationOption): ?>
                                    <option value="<?= htmlspecialchars($specializationOption, ENT_QUOTES, 'UTF-8') ?>">
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                        <div class="field prompt"><label for="enhance_prompt">Review request</label><textarea id="enhance_prompt" name="enhance_prompt" placeholder="What should the review focus on?"><?= htmlspecialchars($enhancePrompt, ENT_QUOTES, 'UTF-8') ?></textarea></div>
                    </div>
                    <div class="subject-editor">
                        <?php foreach ($formSubjects as $year => $subjects): ?>
                            <div class="year-editor">
                                <div class="year-editor-header">
                                    <div class="year-editor-title">
                                        <input class="year-editor-checkbox" type="checkbox" name="enhance_years[]" value="<?= htmlspecialchars($year, ENT_QUOTES, 'UTF-8') ?>" aria-label="Include year <?= htmlspecialchars($year, ENT_QUOTES, 'UTF-8') ?>" <?= in_array((string)$year, $enhanceSelectedYears, true) ? 'checked' : '' ?>>
                                        <h3>Year <?= htmlspecialchars($year) ?></h3>
                                    </div>
                                    <div class="subject-rows" id="subject-rows-<?= htmlspecialchars($year, ENT_QUOTES, 'UTF-8') ?>">
                                        <?php foreach ($subjects as $subject): ?>
                                            <div class="subject-row">
                                                <div class="field"><label>Subject title</label><input type="text" name="subject_title[<?= htmlspecialchars($year, ENT_QUOTES, 'UTF-8') ?>][]" value="<?= htmlspecialchars($subject['subject_title'], ENT_QUOTES, 'UTF-8') ?>" placeholder="e.g. Programming Fundamentals"></div>
                                                <div class="field"><label>Description</label><textarea name="subject_description[<?= htmlspecialchars($year, ENT_QUOTES, 'UTF-8') ?>][]" placeholder="What does this subject currently cover?"><?= htmlspecialchars($subject['description'], ENT_QUOTES, 'UTF-8') ?></textarea></div>
                                                <button type="button" class="button-secondary button-remove" aria-label="Remove subject">Remove</button>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <button type="button" class="button-secondary add-subject" data-year="<?= htmlspecialchars($year, ENT_QUOTES, 'UTF-8') ?>">Add subject</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="enhancement-actions"><button type="submit">Enhance curriculum</button></div>
                </form>
            </div>
        </section>

        <?php $enhancementPrograms = []; foreach ($enhancementRuns as $historyRun) { $historyProgram = (string)$historyRun['program']; if (!in_array($historyProgram, $enhancementPrograms, true)) { $enhancementPrograms[] = $historyProgram; } } ?>
            <div class="draft-toolbar" aria-label="Filter enhancement drafts">
                <div class="field draft-search-field">
                    <label for="enhancement-search">Search drafts</label>
                    <input id="enhancement-search" type="search" placeholder="Run number, program, specialization, or prompt">
                </div>
                <div class="field">
                    <label for="enhancement-status-filter">Status</label>
                    <select id="enhancement-status-filter">
                        <option value="">All statuses</option>
                        <option value="draft">Draft</option>
                        <option value="finalized">Finalized</option>
                    </select>
                </div>
                <div class="field">
                    <label for="enhancement-program-filter">Program</label>
                    <select id="enhancement-program-filter">
                        <option value="">All programs</option>
                        <?php foreach ($enhancementPrograms as $historyProgram): ?>
                            <option value="<?= htmlspecialchars($historyProgram, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($historyProgram) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <p class="draft-count" id="enhancement-count" aria-live="polite">Showing <?= count($enhancementRuns) ?> of <?= count($enhancementRuns) ?> drafts</p>
            </div>
            <div class="empty draft-empty" id="enhancement-empty" <?= $enhancementRuns ? 'hidden' : '' ?>><?= $enhancementRuns ? 'No drafts match these filters.' : 'No enhanced drafts found yet.' ?></div>
            <?php foreach ($enhancementRuns as $historyRun): ?>
                <?php
                    $runId = (int)$historyRun['id'];
                    $report = $historyRun['enhancement_report'];
                    $specialization = trim((string)($report['specialization'] ?? ''));
                    $runStatus = draft_management_status_label($historyRun['is_finalized'] ?? 0);
                    $runStatusClass = strtolower($runStatus);
                    $runTitle = trim((string)($historyRun['user_title'] ?? '')) ?: (string)$historyRun['program'];
                    $runIsOpen = $runId === $enhancedRunId;
                    $runSearchText = 'Run #' . $runId . ' ' . $runTitle . ' ' . (string)$historyRun['program'] . ' ' . $specialization . ' ' . (string)$historyRun['prompt'];
                    $currentEnhancementChat = $enhancementChatByRun[$runId] ?? [];
                    $completedSubjects = $enhancementCoursesByRun[$runId] ?? [];
                    $completedSubjectsByYear = [];
                    foreach ($completedSubjects as $completedSubject) {
                        $completedSubjectsByYear[(string)$completedSubject['year']][(string)$completedSubject['term']][] = $completedSubject;
                    }
                ?>
                <details class="card draft-item enhancement-history-item" id="run-<?= $runId ?>" data-status="<?= htmlspecialchars(strtolower($runStatus), ENT_QUOTES, 'UTF-8') ?>" data-program="<?= htmlspecialchars((string)$historyRun['program'], ENT_QUOTES, 'UTF-8') ?>" data-search="<?= htmlspecialchars($runSearchText, ENT_QUOTES, 'UTF-8') ?>" <?= $runIsOpen ? 'open' : '' ?>>
                    <summary class="draft-summary">
                        <span class="draft-chevron" aria-hidden="true"></span>
                        <span class="draft-summary-main">
                            <span class="draft-program"><?= htmlspecialchars($runTitle, ENT_QUOTES, 'UTF-8') ?><?php if ($specialization !== ''): ?> <span class="draft-specialization">· <?= htmlspecialchars($specialization) ?></span><?php endif; ?></span>
                            <span class="draft-run-number">Run #<?= $runId ?></span>
                            <span class="draft-badges"><span class="draft-status"><span class="status-dot status-dot-<?= htmlspecialchars($runStatusClass, ENT_QUOTES, 'UTF-8') ?>"></span><?= htmlspecialchars($runStatus) ?></span></span>
                            <span class="draft-date"><strong>Generated:</strong> <?= htmlspecialchars((string)$historyRun['generated_at']) ?></span>
                            <span class="draft-date draft-created-by"><strong>Created by:</strong> <?= htmlspecialchars(trim((string)($historyRun['created_by_username'] ?? '')) !== '' ? (string)$historyRun['created_by_username'] : 'Unattributed') ?></span>
                            <span class="draft-prompt" title="<?= htmlspecialchars((string)$historyRun['prompt'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string)$historyRun['prompt']) ?></span>
                        </span>
                    </summary>
                    <div class="output-header">
                        <p class="eyebrow">Enhancement results</p>
                        <h2 id="enhanced-output-title-<?= $runId ?>"><?= htmlspecialchars($runTitle, ENT_QUOTES, 'UTF-8') ?><?php if ($specialization !== ''): ?> · <?= htmlspecialchars($specialization) ?><?php endif; ?></h2>
                        <div class="output-meta meta"><span><strong>Program:</strong> <?= htmlspecialchars((string)$historyRun['program']) ?></span><span><strong>Run #<?= $runId ?></strong></span><span><strong>Generated:</strong> <?= htmlspecialchars((string)$historyRun['generated_at']) ?></span><?php if (!empty($historyRun['updated_at'])): ?><span><strong>Updated:</strong> <?= htmlspecialchars((string)$historyRun['updated_at']) ?></span><?php endif; ?><span><strong>Status:</strong> <?= htmlspecialchars($runStatus) ?></span><span><strong>Prompt:</strong> <?= htmlspecialchars((string)$historyRun['prompt']) ?></span></div>
                    </div>
                    <p class="notice warning advisory-disclaimer">Advisory recommendations only: verify all content before any use.</p>
                    <?php if (draft_management_can_update(current_user(), $historyRun)): ?>
                    <form method="post" class="draft-update-form pdf-export-hide">
                        <?= csrf_token_field() ?>
                        <input type="hidden" name="draft_update_action" value="save">
                        <input type="hidden" name="draft_update_run_id" value="<?= $runId ?>">
                        <div class="field"><label for="user_title_<?= $runId ?>">Draft title (optional)</label><input id="user_title_<?= $runId ?>" name="user_title" maxlength="150" value="<?= htmlspecialchars((string)($historyRun['user_title'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"></div>
                        <div class="field notes"><label for="user_notes_<?= $runId ?>">Personal notes</label><textarea id="user_notes_<?= $runId ?>" name="user_notes" maxlength="5000"><?= htmlspecialchars((string)($historyRun['user_notes'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea></div>
                        <input type="hidden" name="is_finalized" value="0">
                        <label class="field checkbox-field"><input type="checkbox" name="is_finalized" value="1" <?= (int)($historyRun['is_finalized'] ?? 0) === 1 ? 'checked' : '' ?>> Finalized (your own tracking only; not approval or validation)</label>
                        <button type="submit">Save draft details</button>
                    </form>
                    <?php endif; ?>
                    <div class="draft-actions pdf-export-hide">
                        <?php if (current_user() !== null): ?>
                            <div class="draft-actions-primary">
                                <button type="button" class="button-secondary" data-export-pdf>Export to PDF</button>
                            </div>
                        <?php endif; ?>
                        <?php if (run_access_can_delete(current_user(), $historyRun)): ?>
                        <div class="draft-actions-danger">
                            <button type="button" class="btn-danger" data-open-delete-dialog="delete-dialog-<?= $runId ?>">Delete draft</button>
                            <dialog class="confirm-dialog" id="delete-dialog-<?= $runId ?>" aria-label="Confirm deletion">
                                <div class="confirm-dialog-content">
                                    <div class="confirm-dialog-header">
                                        <p class="eyebrow">Confirm deletion</p>
                                    </div>
                                    <div class="confirm-dialog-target">
                                        <div><strong>Run #<?= $runId ?></strong> · <?= htmlspecialchars((string)$historyRun['program']) ?><?php if ($specialization !== ''): ?> · <?= htmlspecialchars($specialization) ?><?php endif; ?></div>
                                        <div class="meta">Generated: <?= htmlspecialchars((string)$historyRun['generated_at']) ?></div>
                                    </div>
                                    <p class="confirm-dialog-warning">
                                        <strong>Warning:</strong> This permanently removes this review, including all its course subjects, review history, and chat messages. This action cannot be undone.
                                    </p>
                                    <div class="confirm-dialog-actions">
                                        <button type="button" class="button-secondary" data-close-delete-dialog>Cancel</button>
                                        <form method="post">
                                            <?= csrf_token_field() ?>
                                            <input type="hidden" name="enhance_delete_action" value="delete">
                                            <input type="hidden" name="enhance_delete_run_id" value="<?= $runId ?>">
                                            <button type="submit" class="button-danger-solid">Delete permanently</button>
                                        </form>
                                    </div>
                                </div>
                            </dialog>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="review-section"><h3>Overall assessment</h3><p class="review-summary"><?= htmlspecialchars((string)($report['summary'] ?? '')) ?></p></div>
                    <?php if (!empty($report['subjects'])): ?>
                        <div class="review-section"><h3>Subject-by-subject review</h3><ul class="review-list">
                            <?php foreach ($report['subjects'] as $assessment): ?>
                                <li class="review-item">
                                    <strong><?= htmlspecialchars((string)$assessment['subject_title']) ?></strong>
                                    <span class="status-badge <?= htmlspecialchars((string)$assessment['status'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string)$assessment['status']) ?></span>
                                    <p><strong>Year fit:</strong> <?= htmlspecialchars((string)$assessment['year_fit']) ?></p>
                                    <p><strong>Why teach it here:</strong> <?= htmlspecialchars((string)($assessment['instructional_reason'] ?? '')) ?></p>
                                    <p><strong>Difficulty:</strong> <?= htmlspecialchars((string)$assessment['difficulty_fit']) ?>. <strong>Prerequisites:</strong> <?= htmlspecialchars((string)$assessment['prerequisite_assessment']) ?></p>
                                    <p><strong>Outdated concern:</strong> <?= htmlspecialchars((string)$assessment['outdated_concern']) ?></p>
                                    <p><strong>Overlap concern:</strong> <?= htmlspecialchars((string)$assessment['overlap_concern']) ?></p>
                                    <p><strong>Recommended edit:</strong> <?= htmlspecialchars((string)$assessment['recommended_edit']) ?></p>
                                    <?php if (!empty($assessment['tools_and_apps']) && is_array($assessment['tools_and_apps'])): ?>
                                        <p><strong>Suggested tools and apps:</strong> <?= htmlspecialchars(implode(', ', array_map('strval', $assessment['tools_and_apps']))) ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($assessment['tools_and_apps_reasons']) && is_array($assessment['tools_and_apps_reasons'])): ?>
                                        <?php foreach ($assessment['tools_and_apps_reasons'] as $toolReason): ?>
                                            <?php if (is_array($toolReason) && trim((string)($toolReason['tool'] ?? '')) !== '' && trim((string)($toolReason['reason'] ?? '')) !== ''): ?>
                                                <p><strong><?= htmlspecialchars((string)$toolReason['tool'], ENT_QUOTES, 'UTF-8') ?> recommended because:</strong> <?= htmlspecialchars((string)$toolReason['reason'], ENT_QUOTES, 'UTF-8') ?></p>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                    <?php if (!empty($assessment['sources']) && is_array($assessment['sources'])): ?>
                                        <p><strong>Sources:</strong></p><ul><?php foreach ($assessment['sources'] as $source): ?><li><?= htmlspecialchars($formatReviewSource((string)$source)) ?></li><?php endforeach; ?></ul>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul></div>
                    <?php endif; ?>
                    <?php if (!empty($report['recommendations'])): ?>
                        <div class="review-section"><h3>Recommendations</h3><ul class="recommendation-list">
                            <?php foreach ($report['recommendations'] as $recommendation): ?>
                                <li>
                                    <strong><?= htmlspecialchars((string)$recommendation['subject_title']) ?></strong> for Year <?= htmlspecialchars((string)$recommendation['target_year']) ?> (<?= htmlspecialchars((string)$recommendation['priority']) ?>)
                                    <p><strong>Reason:</strong> <?= htmlspecialchars((string)$recommendation['reason']) ?></p>
                                    <p><strong>Why it fits this year:</strong> <?= htmlspecialchars((string)($recommendation['instructional_reason'] ?? '')) ?></p>
                                    <?php if (!empty($recommendation['tools_and_apps']) && is_array($recommendation['tools_and_apps'])): ?>
                                        <p><strong>Suggested tools and apps:</strong> <?= htmlspecialchars(implode(', ', array_map('strval', $recommendation['tools_and_apps']))) ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($recommendation['tools_and_apps_reasons']) && is_array($recommendation['tools_and_apps_reasons'])): ?>
                                        <?php foreach ($recommendation['tools_and_apps_reasons'] as $toolReason): ?>
                                            <?php if (is_array($toolReason) && trim((string)($toolReason['tool'] ?? '')) !== '' && trim((string)($toolReason['reason'] ?? '')) !== ''): ?>
                                                <p><strong><?= htmlspecialchars((string)$toolReason['tool'], ENT_QUOTES, 'UTF-8') ?> recommended because:</strong> <?= htmlspecialchars((string)$toolReason['reason'], ENT_QUOTES, 'UTF-8') ?></p>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                    <?php
                                        $hasSavedPostingField = array_key_exists('job_postings', $recommendation)
                                            && is_array($recommendation['job_postings']);
                                        $safePostingRows = [];
                                        $matchedPostingTopics = [];
                                        if ($hasSavedPostingField) {
                                            $jobPostings = $recommendation['job_postings'];
                                            $postingRows = is_array($jobPostings['postings'] ?? null) ? $jobPostings['postings'] : [];
                                            $safePostingRows = array_values(array_filter(
                                                $postingRows,
                                                static fn($posting): bool => is_array($posting)
                                                    && is_string($posting['posting_url'] ?? null)
                                                    && $isSafePostingUrl($posting['posting_url'])
                                            ));
                                            $matchedPostingTopics = is_array($jobPostings['matched_topics'] ?? null)
                                                ? array_values(array_filter(array_map('strval', $jobPostings['matched_topics']), static fn(string $topic): bool => trim($topic) !== ''))
                                                : [];
                                        }
                                    ?>
                                    <?php if ((!empty($recommendation['sources']) && is_array($recommendation['sources'])) || $hasSavedPostingField): ?>
                                        <p><strong>Sources:</strong></p>
                                        <ul>
                                            <?php if (!empty($recommendation['sources']) && is_array($recommendation['sources'])): ?>
                                                <?php foreach ($recommendation['sources'] as $source): ?>
                                                    <li><?= htmlspecialchars($formatReviewSource((string)$source)) ?></li>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                            <?php if ($hasSavedPostingField): ?>
                                                <li class="job-postings">
                                                    <strong>Topic-level examples of industry demand</strong> (not proof that a specific skill is required).
                                                    <?php if ($safePostingRows): ?>
                                                        <ul>
                                                            <?php foreach ($safePostingRows as $posting): ?>
                                                                <?php
                                                                    $postingUrl = (string)$posting['posting_url'];
                                                                    $postingTitle = trim((string)($posting['posting_title'] ?? '')) ?: (string)(parse_url($postingUrl, PHP_URL_HOST) ?: $postingUrl);
                                                                    $postingEmployer = trim((string)($posting['employer'] ?? ''));
                                                                    $postingTopic = trim((string)($posting['topic'] ?? '')) ?: 'topic not specified';
                                                                    $postingDate = trim((string)($posting['date_retrieved'] ?? '')) ?: 'date unknown';
                                                                    $postingType = trim((string)($posting['link_type'] ?? 'posting')) ?: 'posting';
                                                                    $postingHost = (string)(parse_url($postingUrl, PHP_URL_HOST) ?: '');
                                                                    $postingLabel = $postingType === 'listing'
                                                                        ? 'Job listings page: ' . $postingTitle . ' (live search results on ' . $postingHost . '; contents change over time)'
                                                                        : 'Job posting: ' . $postingTitle . ($postingEmployer !== '' ? ' at ' . $postingEmployer : '') . ' (topic: ' . $postingTopic . ', checked ' . $postingDate . ')';
                                                                ?>
                                                                <li>
                                                                    <a href="<?= htmlspecialchars($postingUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer"><?= htmlspecialchars($postingLabel, ENT_QUOTES, 'UTF-8') ?></a><br>
                                                                    <span class="posting-url"><?= htmlspecialchars($postingUrl, ENT_QUOTES, 'UTF-8') ?></span>
                                                                </li>
                                                            <?php endforeach; ?>
                                                        </ul>
                                                        <?php
                                                            $postingCount = count(array_filter($safePostingRows, static fn(array $posting): bool => trim((string)($posting['link_type'] ?? 'posting')) !== 'listing'));
                                                            $listingCount = count($safePostingRows) - $postingCount;
                                                        ?>
                                                        <p><?= $postingCount ?> job posting<?= $postingCount === 1 ? '' : 's' ?> and <?= $listingCount ?> job listing page<?= $listingCount === 1 ? '' : 's' ?> on file for <?= count($matchedPostingTopics) === 1 ? 'topic ' . htmlspecialchars($matchedPostingTopics[0], ENT_QUOTES, 'UTF-8') : 'matched topics' ?>.</p>
                                                        <p>Links may have changed or been taken down since they were checked.</p>
                                                    <?php else: ?>
                                                        <p>No job postings on file for this recommendation yet.</p>
                                                    <?php endif; ?>
                                                </li>
                                            <?php endif; ?>
                                        </ul>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul></div>
                    <?php endif; ?>
                    <?php if ($completedSubjectsByYear): ?>
                        <div class="review-section enhanced-curriculum-output">
                            <h3>Completed enhanced curriculum</h3>
                            <?php foreach ($completedSubjectsByYear as $year => $terms): ?>
                                <section class="enhanced-year" aria-labelledby="enhanced-year-<?= $runId ?>-<?= htmlspecialchars($year, ENT_QUOTES, 'UTF-8') ?>">
                                    <h4 id="enhanced-year-<?= $runId ?>-<?= htmlspecialchars($year, ENT_QUOTES, 'UTF-8') ?>">Year <?= htmlspecialchars($year) ?></h4>
                                    <?php foreach ($terms as $term => $termSubjects): ?>
                                        <div class="enhanced-term">
                                            <h5><?= htmlspecialchars($term === '1' ? 'First Term' : ($term === '2' ? 'Second Term' : 'Term ' . $term)) ?></h5>
                                            <div class="table-wrap"><table class="course-table"><thead><tr><th>Code</th><th>Course title</th><th class="units">Units</th></tr></thead><tbody>
                                                <?php foreach ($termSubjects as $subject): ?>
                                                    <?php
                                                        $topicsValue = $subject['topics'] ?? [];
                                                        $subjectTopics = is_array($topicsValue) ? $topicsValue : json_decode((string)$topicsValue, true);
                                                        $skillsValue = $subject['mapped_industry_skills'] ?? [];
                                                        $subjectSkills = is_array($skillsValue) ? $skillsValue : json_decode((string)$skillsValue, true);
                                                        $sourcesValue = $subject['source_colleges'] ?? [];
                                                        $subjectSources = is_array($sourcesValue) ? $sourcesValue : json_decode((string)$sourcesValue, true);
                                                        $subjectTopics = is_array($subjectTopics) ? $subjectTopics : [];
                                                        $subjectSkills = is_array($subjectSkills) ? $subjectSkills : [];
                                                        $subjectSources = is_array($subjectSources) ? $subjectSources : [];
                                                        $prerequisiteValue = $subject['prerequisites'] ?? '';
                                                        $subjectPrerequisites = is_array($prerequisiteValue)
                                                            ? implode(', ', array_filter(array_map('strval', $prerequisiteValue)))
                                                            : (is_scalar($prerequisiteValue) ? (string)$prerequisiteValue : '');
                                                    ?>
                                                    <tr>
                                                        <td><span class="course-code"><?= htmlspecialchars((string)$subject['subject_code']) ?></span></td>
                                                        <td>
                                                            <strong><?= htmlspecialchars((string)$subject['subject_title']) ?></strong>
                                                            <?php if (trim((string)$subject['description']) !== ''): ?><div class="course-description"><?= htmlspecialchars((string)$subject['description']) ?></div><?php endif; ?>
                                                            <?php if (trim($subjectPrerequisites) !== ''): ?><div class="course-description course-description-labeled"><strong>Prerequisite:</strong><span><?= htmlspecialchars($subjectPrerequisites) ?></span></div><?php endif; ?>
                                                            <?php if (trim((string)($subject['rationale'] ?? '')) !== ''): ?><div class="course-description course-description-labeled"><strong>Why it is taught:</strong><span><?= htmlspecialchars((string)$subject['rationale']) ?></span></div><?php endif; ?>
                                                            <?php if ($subjectTopics): ?><div class="course-description course-description-labeled"><strong>Topics and practical work:</strong><span><?= htmlspecialchars(implode('; ', array_map('strval', $subjectTopics))) ?></span></div><?php endif; ?>
                                                            <?php if ($subjectSkills): ?><div class="course-description course-description-labeled"><strong>Industry skills:</strong><span><?= htmlspecialchars(implode(', ', array_map('strval', $subjectSkills))) ?></span></div><?php endif; ?>
                                                            <?php if ($subjectSources): ?><div class="course-description course-description-labeled"><strong>Benchmark institutions:</strong><span><?= htmlspecialchars(implode(', ', array_map('strval', $subjectSources))) ?></span></div><?php endif; ?>
                                                        </td>
                                                        <td class="units"><?= htmlspecialchars((string)$subject['units']) ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody></table></div>
                                        </div>
                                    <?php endforeach; ?>
                                </section>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="review-section"><h3>Completed enhanced curriculum</h3><p class="meta">No saved course list is available for this review.</p></div>
                    <?php endif; ?>
                    <?php if (current_user() !== null): ?>
                    <div class="chat-panel review-chat pdf-export-hide">
                        <h3>Ask about this enhancement</h3>
                        <?php if ($enhancementChatError !== '' && $enhancementChatRunId === $runId): ?>
                            <div class="alert warning chat-error" role="alert"><?= htmlspecialchars($enhancementChatError) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($currentEnhancementChat)): ?>
                            <div class="chat-history review-chat-history">
                                <?php foreach ($currentEnhancementChat as $chat): ?>
                                    <div class="review-chat-message <?= $chat['role'] === 'user' ? 'user' : 'assistant' ?>">
                                        <span class="meta"><strong><?= htmlspecialchars($chat['role'] === 'assistant' ? 'Assistant' : (trim((string)($chat['sender_username'] ?? '')) !== '' ? (string)$chat['sender_username'] : 'Unattributed sender')) ?></strong> · <?= htmlspecialchars((string)$chat['created_at']) ?></span>
                                        <?= htmlspecialchars((string)$chat['message']) ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($hasApiKeyAvailable): ?>
                        <div class="review-chat-prompts" aria-label="Suggested questions about these results">
                            <?php foreach ([
                                'Which subjects should we revise first?',
                                'Why was this curriculum marked as unrealistic for its year?',
                                'Which recommendations are highest priority?',
                                'Are any courses outdated or unnecessarily overlapping?'
                            ] as $reviewPrompt): ?>
                                <button type="button" class="review-chat-prompt" data-chat-target="review_chat_message_<?= $runId ?>" data-review-prompt="<?= htmlspecialchars($reviewPrompt, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($reviewPrompt) ?></button>
                            <?php endforeach; ?>
                        </div>
                        <form method="post" class="review-chat-form" data-loading="chat">
                            <?= csrf_token_field() ?>
                            <input type="hidden" name="enhancement_chat_action" value="send">
                            <input type="hidden" name="enhancement_chat_run_id" value="<?= $runId ?>">
                            <textarea id="review_chat_message_<?= $runId ?>" name="enhancement_chat_message" placeholder="Ask about these results..." required></textarea>
                            <button type="submit">Ask about results</button>
                            <p class="loading-inline" data-loading-status role="status" aria-live="polite" hidden>Working...</p>
                        </form>
                        <?php else: ?>
                            <p class="notice warning" role="status"><?= htmlspecialchars(GEMINI_API_KEY_REQUIRED_MESSAGE, ENT_QUOTES, 'UTF-8') ?> <a href="#api_key_value">Open key settings</a></p>
                        <?php endif; ?>
                    </div>
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
        var enhancementItems = Array.prototype.slice.call(document.querySelectorAll('.enhancement-history-item'));
        var enhancementSearch = document.getElementById('enhancement-search');
        var enhancementStatusFilter = document.getElementById('enhancement-status-filter');
        var enhancementProgramFilter = document.getElementById('enhancement-program-filter');
        var enhancementCount = document.getElementById('enhancement-count');
        var enhancementEmpty = document.getElementById('enhancement-empty');

        function filterEnhancementReviews() {
            var searchTerm = enhancementSearch.value.trim().toLowerCase();
            var selectedStatus = enhancementStatusFilter.value;
            var selectedProgram = enhancementProgramFilter.value;
            var visibleCount = 0;

            enhancementItems.forEach(function (item) {
                var matches = item.dataset.search.toLowerCase().indexOf(searchTerm) !== -1
                    && (!selectedStatus || item.dataset.status === selectedStatus)
                    && (!selectedProgram || item.dataset.program === selectedProgram);
                item.hidden = !matches;
                if (matches) {
                    visibleCount += 1;
                }
            });

            enhancementCount.textContent = 'Showing ' + visibleCount + ' of ' + enhancementItems.length + ' drafts';
            enhancementEmpty.hidden = visibleCount !== 0;
        }

        if (enhancementSearch && enhancementStatusFilter && enhancementProgramFilter && enhancementCount && enhancementEmpty) {
            enhancementSearch.addEventListener('input', filterEnhancementReviews);
            enhancementStatusFilter.addEventListener('change', filterEnhancementReviews);
            enhancementProgramFilter.addEventListener('change', filterEnhancementReviews);

            var linkedReview = window.location.hash ? document.getElementById(window.location.hash.slice(1)) : null;
            var requestedRunId = new URLSearchParams(window.location.search).get('run_id');
            var reviewToScroll = linkedReview || (requestedRunId ? document.getElementById('run-' + requestedRunId) : null);
            if (reviewToScroll && reviewToScroll.classList.contains('enhancement-history-item')) {
                reviewToScroll.open = true;
                window.requestAnimationFrame(function () {
                    reviewToScroll.scrollIntoView({ block: 'start' });
                });
            }
        }

        document.querySelectorAll('.add-subject').forEach(function (button) {
            button.addEventListener('click', function () {
                var year = button.dataset.year;
                var rows = document.getElementById('subject-rows-' + year);
                var row = rows.querySelector('.subject-row').cloneNode(true);
                row.querySelectorAll('input, textarea').forEach(function (field) { field.value = ''; });
                rows.appendChild(row);
                updateRemoveButtons(rows);
            });
        });
        function updateRemoveButtons(rows) {
            var buttons = rows.querySelectorAll('.button-remove');
            buttons.forEach(function (button) { button.disabled = buttons.length === 1; });
        }
        document.querySelectorAll('.subject-rows').forEach(function (rows) {
            updateRemoveButtons(rows);
            rows.addEventListener('click', function (event) {
                if (event.target.classList.contains('button-remove')) {
                    event.target.closest('.subject-row').remove();
                    updateRemoveButtons(rows);
                }
            });
        });
        var programSelect = document.getElementById('enhance_program');
        var specializationDatalist = document.getElementById('enhance_specialization_options');
        var specializationOptions = <?= json_encode($specializationsByProgram, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        if (programSelect && specializationDatalist) {
            programSelect.addEventListener('change', function () {
                var options = specializationOptions[programSelect.value] || [];
                specializationDatalist.replaceChildren();
                options.forEach(function (option) {
                    specializationDatalist.appendChild(new Option(option, option));
                });
            });
        }
        document.querySelectorAll('[data-review-prompt]').forEach(function (button) {
            button.addEventListener('click', function () {
                var reviewChatMessage = document.getElementById(button.dataset.chatTarget);
                if (reviewChatMessage) {
                    reviewChatMessage.value = button.dataset.reviewPrompt;
                    reviewChatMessage.focus();
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
