<?php
declare(strict_types=1);

const RUN_ACCESS_ADMIN_POLICY = 'all_owned';
const RUN_ACCESS_NOT_FOUND_BODY = 'Not found.';
const GEMINI_API_KEY_REQUIRED_MESSAGE = 'You need an API key. Add your Gemini API key in your profile to use this feature.';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function csrf_token(): string
{
    if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function validate_csrf_token(mixed $submittedToken): bool
{
    $sessionToken = $_SESSION['csrf_token'] ?? null;
    return is_string($sessionToken)
        && is_string($submittedToken)
        && $submittedToken !== ''
        && hash_equals($sessionToken, $submittedToken);
}

function csrf_token_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' .
        htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

function auth_db(): SQLite3
{
    static $db = null;
    if ($db instanceof SQLite3) {
        return $db;
    }

    $db = new SQLite3(__DIR__ . '/curriculum_matching.db');
    $db->enableExceptions(true);
    $db->exec(
        "CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL,
            role TEXT NOT NULL CHECK(role IN ('user', 'admin', 'super_admin')),
            gemini_api_key TEXT NULL,
            email TEXT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )"
    );

    $userColumns = [];
    $userColumnResult = $db->query('PRAGMA table_info(users)');
    while ($column = $userColumnResult->fetchArray(SQLITE3_ASSOC)) {
        $userColumns[] = $column['name'];
    }
    if (!in_array('gemini_api_key', $userColumns, true)) {
        $db->exec('ALTER TABLE users ADD COLUMN gemini_api_key TEXT NULL');
    }
    if (!in_array('email', $userColumns, true)) {
        $db->exec('ALTER TABLE users ADD COLUMN email TEXT NULL');
    }
    $db->exec(
        "CREATE UNIQUE INDEX IF NOT EXISTS users_email_unique
         ON users(email COLLATE NOCASE)
         WHERE email IS NOT NULL AND TRIM(email) <> ''"
    );
    run_access_ensure_indexes($db);

    return $db;
}

function get_user_api_key(int $userId): ?string
{
    if ($userId < 1) {
        return null;
    }

    $stmt = auth_db()->prepare('SELECT gemini_api_key FROM users WHERE id = :id');
    $stmt->bindValue(':id', $userId, SQLITE3_INTEGER);
    $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    if (!is_array($row)) {
        return null;
    }

    $apiKey = trim((string)($row['gemini_api_key'] ?? ''));
    return $apiKey !== '' ? $apiKey : null;
}

function mask_user_api_key(?string $apiKey): string
{
    return $apiKey !== null && trim($apiKey) !== '' ? '********' : 'No Gemini key saved';
}

function gemini_api_key_policy(
    ?array $user,
    ?string $personalKey,
    bool $sharedKeyAvailable,
    bool $guest = false
): array {
    if ($guest) {
        return [
            'allowed' => $sharedKeyAvailable,
            'key_source' => $sharedKeyAvailable ? 'shared' : 'none',
            'needs_personal_key' => false,
        ];
    }

    if ($user === null) {
        return ['allowed' => false, 'key_source' => 'none', 'needs_personal_key' => false];
    }
    if ($personalKey !== null && trim($personalKey) !== '') {
        return ['allowed' => true, 'key_source' => 'personal', 'needs_personal_key' => false];
    }
    if (($user['role'] ?? '') === 'super_admin' && $sharedKeyAvailable) {
        return ['allowed' => true, 'key_source' => 'shared', 'needs_personal_key' => false];
    }

    return [
        'allowed' => false,
        'key_source' => 'none',
        'needs_personal_key' => in_array($user['role'] ?? '', ['user', 'admin'], true),
    ];
}

function has_shared_api_key(): bool
{
    foreach (['GEMINI_API_KEY', 'GOOGLE_API_KEY'] as $environmentName) {
        $environmentKey = getenv($environmentName);
        if ($environmentKey !== false && trim($environmentKey) !== '') {
            return true;
        }
    }

    $settingsPaths = [];
    foreach ([getenv('APPDATA'), getenv('HOME')] as $baseDir) {
        if ($baseDir !== false && trim($baseDir) !== '') {
            $settingsPaths[] = rtrim($baseDir, DIRECTORY_SEPARATOR) .
                DIRECTORY_SEPARATOR . 'CurriculumMatcher' . DIRECTORY_SEPARATOR . 'settings.json';
        }
    }
    $settingsPaths[] = __DIR__ . DIRECTORY_SEPARATOR . 'settings.json';

    foreach (array_unique($settingsPaths) as $settingsPath) {
        if (!is_file($settingsPath)) {
            continue;
        }

        $settingsJson = @file_get_contents($settingsPath);
        if ($settingsJson === false) {
            continue;
        }
        $settings = json_decode($settingsJson, true);
        if (is_array($settings)
            && isset($settings['api_key'])
            && is_scalar($settings['api_key'])
            && trim((string)$settings['api_key']) !== '') {
            return true;
        }
    }

    return false;
}

function run_command_with_api_key(string|array $command, ?string $apiKey = null): string
{
    $environment = getenv();
    if (!is_array($environment)) {
        throw new RuntimeException('Unable to read the process environment.');
    }
    if ($apiKey !== null && trim($apiKey) !== '') {
        $environment['GEMINI_API_KEY'] = $apiKey;
    }

    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['redirect', 1],
    ];
    $process = proc_open($command, $descriptors, $pipes, null, $environment);
    if (!is_resource($process)) {
        throw new RuntimeException('Unable to start the Python subprocess.');
    }

    fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    proc_close($process);

    if ($output === false) {
        throw new RuntimeException('Unable to read the Python subprocess output.');
    }

    return $output;
}

function save_user_api_key(int $userId, string $apiKey): bool
{
    $cleanKey = trim($apiKey);
    if ($userId < 1 || $cleanKey === '') {
        return false;
    }

    $db = auth_db();
    $stmt = $db->prepare('UPDATE users SET gemini_api_key = :gemini_api_key WHERE id = :id');
    $stmt->bindValue(':gemini_api_key', $cleanKey, SQLITE3_TEXT);
    $stmt->bindValue(':id', $userId, SQLITE3_INTEGER);
    try {
        $stmt->execute();
        return $db->changes() > 0;
    } catch (Throwable $error) {
        return false;
    }
}

function clear_user_api_key(int $userId): bool
{
    if ($userId < 1) {
        return false;
    }

    $db = auth_db();
    $stmt = $db->prepare('UPDATE users SET gemini_api_key = NULL WHERE id = :id');
    $stmt->bindValue(':id', $userId, SQLITE3_INTEGER);
    try {
        $stmt->execute();
        return $db->changes() > 0;
    } catch (Throwable $error) {
        return false;
    }
}

function current_user(): ?array
{
    return isset($_SESSION['user']) && is_array($_SESSION['user']) ? $_SESSION['user'] : null;
}

function has_role(string ...$roles): bool
{
    $user = current_user();
    return $user !== null && in_array($user['role'] ?? '', $roles, true);
}

function run_access_can_access(?array $user, ?array $run): bool
{
    if ($user === null || $run === null || !isset($user['id'])) {
        return false;
    }

    $role = $user['role'] ?? '';
    $ownerId = $run['created_by_user_id'] ?? null;
    if ($role === 'super_admin') {
        return true;
    }
    if ($ownerId === null || (int)$ownerId < 1) {
        return false;
    }
    if ($role === 'user') {
        return (int)$user['id'] === (int)$ownerId;
    }
    if ($role === 'admin') {
        return RUN_ACCESS_ADMIN_POLICY === 'all_owned'
            || ((int)$user['id'] === (int)$ownerId && RUN_ACCESS_ADMIN_POLICY === 'own_only');
    }

    return false;
}

function run_access_can_update(?array $user, ?array $run): bool
{
    if ($user === null || $run === null || !isset($user['id'])) {
        return false;
    }
    if (($user['role'] ?? '') === 'super_admin') {
        return true;
    }

    $ownerId = $run['created_by_user_id'] ?? null;
    return $ownerId !== null
        && (int)$ownerId > 0
        && (int)$user['id'] === (int)$ownerId;
}

function run_access_can_delete(?array $user, ?array $run): bool
{
    return in_array($user['role'] ?? '', ['user', 'admin', 'super_admin'], true)
        && run_access_can_access($user, $run);
}

function run_access_sql_scope(?array $user, string $tableAlias = 'r'): array
{
    if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $tableAlias) !== 1) {
        throw new InvalidArgumentException('Invalid table alias for run access scope.');
    }

    if ($user === null || !isset($user['id'])) {
        return ['sql' => '0 = 1', 'params' => []];
    }
    if (($user['role'] ?? '') === 'super_admin') {
        return ['sql' => '1 = 1', 'params' => []];
    }
    if (($user['role'] ?? '') === 'admin' && RUN_ACCESS_ADMIN_POLICY === 'all_owned') {
        return [
            'sql' => $tableAlias . '.created_by_user_id IS NOT NULL AND ' .
                $tableAlias . '.created_by_user_id > 0',
            'params' => [],
        ];
    }
    if (!in_array($user['role'] ?? '', ['user', 'admin'], true)) {
        return ['sql' => '0 = 1', 'params' => []];
    }

    return [
        'sql' => $tableAlias . '.created_by_user_id = :run_access_user_id',
        'params' => [':run_access_user_id' => (int)$user['id']],
    ];
}

function run_access_bind_scope(SQLite3Stmt $stmt, array $scope): void
{
    foreach ($scope['params'] as $name => $value) {
        $stmt->bindValue($name, $value, SQLITE3_INTEGER);
    }
}

function run_access_ensure_indexes(SQLite3 $db): void
{
    foreach ([
        'generated_curriculum_runs' => 'idx_generated_curriculum_runs_owner_id',
        'generated_curriculum_chat' => 'idx_generated_curriculum_chat_run_id',
    ] as $table => $index) {
        $tableExists = $db->prepare(
            "SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = :table"
        );
        $tableExists->bindValue(':table', $table, SQLITE3_TEXT);
        if ($tableExists->execute()->fetchArray(SQLITE3_NUM)) {
            $column = $table === 'generated_curriculum_runs' ? 'created_by_user_id' : 'run_id';
            $columns = [];
            $columnResult = $db->query("PRAGMA table_info({$table})");
            while ($columnRow = $columnResult->fetchArray(SQLITE3_ASSOC)) {
                $columns[] = $columnRow['name'];
            }
            if (in_array($column, $columns, true)) {
                $db->exec("CREATE INDEX IF NOT EXISTS {$index} ON {$table}({$column})");
            }
        }
    }
}

function run_access_not_found(): never
{
    http_response_code(404);
    exit(RUN_ACCESS_NOT_FOUND_BODY);
}

function run_access_require(SQLite3 $db, mixed $runId, ?array $user = null): array
{
    $normalizedRunId = is_int($runId)
        ? $runId
        : (is_string($runId) && ctype_digit($runId) ? (int)$runId : 0);
    if ($normalizedRunId < 1) {
        run_access_not_found();
    }

    $runColumns = [];
    $columnResult = $db->query('PRAGMA table_info(generated_curriculum_runs)');
    while ($column = $columnResult->fetchArray(SQLITE3_ASSOC)) {
        $runColumns[] = (string)$column['name'];
    }
    $ownerSelect = in_array('created_by_user_id', $runColumns, true)
        ? 'created_by_user_id'
        : 'NULL AS created_by_user_id';
    $sourceSelect = in_array('source', $runColumns, true)
        ? 'source'
        : "'generated' AS source";
    $stmt = $db->prepare(
        "SELECT id, {$sourceSelect}, {$ownerSelect}
         FROM generated_curriculum_runs
         WHERE id = :id"
    );
    $stmt->bindValue(':id', $normalizedRunId, SQLITE3_INTEGER);
    $run = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    if (!is_array($run) || !run_access_can_access($user ?? current_user(), $run)) {
        run_access_not_found();
    }

    return $run;
}

function run_access_delete(SQLite3 $db, mixed $runId, ?array $user = null): void
{
    $run = run_access_require($db, $runId, $user);
    if (!run_access_can_delete($user ?? current_user(), $run)) {
        run_access_not_found();
    }

    $normalizedRunId = (int)$run['id'];
    $db->exec('PRAGMA foreign_keys = OFF');
    $db->exec('BEGIN TRANSACTION');
    try {
        foreach ([
            'generated_curriculum_chat',
            'generated_curriculum_subjects',
        ] as $table) {
            $stmt = $db->prepare("DELETE FROM {$table} WHERE run_id = :run_id");
            $stmt->bindValue(':run_id', $normalizedRunId, SQLITE3_INTEGER);
            $stmt->execute();
        }
        $stmt = $db->prepare('DELETE FROM generated_curriculum_runs WHERE id = :id');
        $stmt->bindValue(':id', $normalizedRunId, SQLITE3_INTEGER);
        $stmt->execute();
        $db->exec('COMMIT');
    } catch (Throwable $error) {
        $db->exec('ROLLBACK');
        throw $error;
    }
}

function require_login(): void
{
    if (current_user() === null) {
        header('Location: login.php');
        exit;
    }
}

function guest_trial_state(): array
{
    $now = time();
    $used = isset($_SESSION['guest_attempts_used']) ? max(0, (int)$_SESSION['guest_attempts_used']) : 0;
    $ipAttempts = $_SESSION['guest_ip_attempts'] ?? [];
    if (!is_array($ipAttempts)) {
        $ipAttempts = [];
    }
    $filteredIpAttempts = [];
    foreach ($ipAttempts as $attemptAt) {
        if (!is_numeric($attemptAt)) {
            continue;
        }
        $timestamp = (int)$attemptAt;
        if ($timestamp > $now - 900) {
            $filteredIpAttempts[] = $timestamp;
        }
    }
    $_SESSION['guest_ip_attempts'] = $filteredIpAttempts;

    return [
        'used' => $used,
        'ip_key' => 'guest_ip_' . md5((string)($_SERVER['REMOTE_ADDR'] ?? '') . ':' . (string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '')),
        'ip_count' => count($filteredIpAttempts),
    ];
}

function guest_trial_limit_reached(): bool
{
    $state = guest_trial_state();
    return $state['used'] >= 3 || $state['ip_count'] >= 3;
}

function mark_guest_attempt_success(): void
{
    $state = guest_trial_state();
    $_SESSION['guest_attempts_used'] = min(3, $state['used'] + 1);
    $_SESSION['guest_ip_attempts'] = array_values(array_merge($_SESSION['guest_ip_attempts'] ?? [], [time()]));
}

function guest_trial_route_allowed(string $scriptName): bool
{
    if (current_user() !== null) {
        return false;
    }

    $guestRequested = isset($_GET['guest']) && $_GET['guest'] === '1';
    if (!$guestRequested && !isset($_SESSION['guest_trial_mode'])) {
        return false;
    }

    return $scriptName === 'generated_curriculum.php' || $scriptName === 'enhanced_curriculum_generated.php';
}

function claim_guest_draft_tab(string $tabId): bool
{
    if (!preg_match('/^[a-f0-9]{64}$/', $tabId)) {
        return false;
    }

    $activeTabId = $_SESSION['guest_trial_tab_id'] ?? null;
    if (is_string($activeTabId) && hash_equals($activeTabId, $tabId)) {
        return false;
    }

    $draftCleared = isset($_SESSION['guest_trial_draft']);
    clear_guest_trial_draft();
    $_SESSION['guest_trial_tab_id'] = $tabId;

    return $draftCleared;
}

function clear_guest_trial_draft(): void
{
    unset(
        $_SESSION['guest_trial_draft'],
        $_SESSION['guest_generation_flash'],
        $_SESSION['guest_enhancement_flash']
    );
}

function decode_guest_python_output(string $output): ?array
{
    $lines = preg_split('/\R/', trim($output));
    if (!is_array($lines) || $lines === []) {
        error_log('Guest Python helper returned no output.');
        return null;
    }

    $json = array_pop($lines);
    $decoded = json_decode((string)$json, true);
    if (!is_array($decoded)) {
        error_log('Guest Python helper did not return valid JSON: ' . json_last_error_msg());
        return null;
    }

    return $decoded;
}

function guest_generate_program(string $program, string $prompt): ?array
{
    $pythonExe = __DIR__ . '/venv/Scripts/python.exe';
    $projectRoot = __DIR__;
    $coursesCsv = dirname(__DIR__) . '/curriculum-generator-kb/data/curriculum_dataset_with_ids.csv';
    $skillCoverageCsv = $projectRoot . '/skill_coverage.csv';
    $courseSkillMatchesCsv = $projectRoot . '/course_to_skill_matches.csv';

    if (!file_exists($pythonExe) || !is_file($pythonExe)) {
        return null;
    }

    $pythonProjectRoot = json_encode($projectRoot, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $pythonCoursesCsv = json_encode($coursesCsv, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $pythonSkillCoverageCsv = json_encode($skillCoverageCsv, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $pythonCourseSkillMatchesCsv = json_encode($courseSkillMatchesCsv, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $pythonProgram = json_encode($program, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $pythonPrompt = json_encode($prompt, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($pythonProjectRoot === false || $pythonCoursesCsv === false || $pythonSkillCoverageCsv === false || $pythonCourseSkillMatchesCsv === false
        || $pythonProgram === false || $pythonPrompt === false) {
        error_log('Guest curriculum generation input could not be encoded.');
        return null;
    }

    $code = "import csv, json, sys; from pathlib import Path; sys.path.insert(0, " . $pythonProjectRoot . "); from curriculum_generator import generate_program_curriculum; from curriculum_generator_foundation import build_subject_bank, load_course_rows; subject_bank = build_subject_bank(load_course_rows(Path(" . $pythonCoursesCsv . "))); skill_path = Path(" . $pythonSkillCoverageCsv . "); skill_rows = [{'skill_id': row.get('skill_id', ''), 'skill_name': row.get('skill_name', ''), 'score': float(row.get('score', 0) or 0)} for row in csv.DictReader(skill_path.open('r', encoding='utf-8', newline='')) if row.get('skill_name')] if skill_path.is_file() else []; match_path = Path(" . $pythonCourseSkillMatchesCsv . "); match_rows = [{'course_title': row.get('course_title', ''), 'skill_id': row.get('skill_id', ''), 'skill_name': row.get('skill_name', ''), 'score': float(row.get('score', 0) or 0)} for row in csv.DictReader(match_path.open('r', encoding='utf-8-sig', newline='')) if row.get('course_title') and row.get('skill_name')] if match_path.is_file() else []; print(json.dumps(generate_program_curriculum(program=" . $pythonProgram . ", prompt=" . $pythonPrompt . ", subject_bank=subject_bank, skill_coverage=skill_rows, course_skill_matches=match_rows, limit=6), ensure_ascii=False));";

    set_time_limit(180);
    $output = run_command_with_api_key([$pythonExe, '-c', $code], null);
    return decode_guest_python_output($output);
}

function guest_enhance_curriculum(string $program, string $specialization, string $prompt, array $userSubjects, array $selectedYears): ?array
{
    $pythonExe = __DIR__ . '/venv/Scripts/python.exe';
    $projectRoot = __DIR__;
    $coursesCsv = dirname(__DIR__) . '/curriculum-generator-kb/data/curriculum_dataset_with_ids.csv';
    $skillCoverageCsv = $projectRoot . '/skill_coverage.csv';
    $courseSkillMatchesCsv = $projectRoot . '/course_to_skill_matches.csv';

    if (!file_exists($pythonExe) || !is_file($pythonExe)) {
        return null;
    }

    $pythonProjectRoot = json_encode($projectRoot, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $pythonCoursesCsv = json_encode($coursesCsv, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $pythonSkillCoverageCsv = json_encode($skillCoverageCsv, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $pythonCourseSkillMatchesCsv = json_encode($courseSkillMatchesCsv, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $pythonProgram = json_encode($program, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $pythonSpecialization = json_encode($specialization, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $pythonPrompt = json_encode($prompt, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $subjectsJson = json_encode($userSubjects, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $selectedYearsJson = json_encode($selectedYears, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $pythonSubjectsJson = is_string($subjectsJson) ? json_encode($subjectsJson, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : false;
    $pythonSelectedYearsJson = is_string($selectedYearsJson) ? json_encode($selectedYearsJson, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : false;
    if ($pythonProjectRoot === false || $pythonCoursesCsv === false || $pythonSkillCoverageCsv === false || $pythonCourseSkillMatchesCsv === false
        || $pythonProgram === false || $pythonSpecialization === false || $pythonPrompt === false
        || $pythonSubjectsJson === false || $pythonSelectedYearsJson === false) {
        error_log('Guest curriculum enhancement input could not be encoded.');
        return null;
    }

    $code = "import csv, json, sys; from pathlib import Path; sys.path.insert(0, " . $pythonProjectRoot . "); from curriculum_generator import enhance_user_curriculum, generate_enhanced_curriculum; from curriculum_generator_foundation import build_subject_bank, load_course_rows; course_rows = load_course_rows(Path(" . $pythonCoursesCsv . ")); subject_bank = build_subject_bank(course_rows); skill_path = Path(" . $pythonSkillCoverageCsv . "); skill_rows = [{'skill_id': row.get('skill_id', ''), 'skill_name': row.get('skill_name', ''), 'score': float(row.get('score', 0) or 0)} for row in csv.DictReader(skill_path.open('r', encoding='utf-8', newline='')) if row.get('skill_name')] if skill_path.is_file() else []; match_path = Path(" . $pythonCourseSkillMatchesCsv . "); match_rows = [{'course_title': row.get('course_title', ''), 'skill_id': row.get('skill_id', ''), 'skill_name': row.get('skill_name', ''), 'score': float(row.get('score', 0) or 0)} for row in csv.DictReader(match_path.open('r', encoding='utf-8-sig', newline='')) if row.get('course_title') and row.get('skill_name')] if match_path.is_file() else []; user_subjects = json.loads(" . $pythonSubjectsJson . "); selected_years = json.loads(" . $pythonSelectedYearsJson . "); report = enhance_user_curriculum(program=" . $pythonProgram . ", specialization=" . $pythonSpecialization . ", prompt=" . $pythonPrompt . ", user_subjects=user_subjects, subject_bank=subject_bank, skill_coverage=skill_rows, course_skill_matches=match_rows, selected_years=selected_years); enhanced_curriculum = generate_enhanced_curriculum(program=" . $pythonProgram . ", specialization=" . $pythonSpecialization . ", prompt=" . $pythonPrompt . ", user_subjects=user_subjects, enhancement_report=report, subject_bank=subject_bank, skill_coverage=skill_rows, course_rows=course_rows, selected_years=selected_years); print(json.dumps({'report': report, 'enhanced_curriculum': enhanced_curriculum}, ensure_ascii=False));";

    $output = run_command_with_api_key([$pythonExe, '-c', $code], null);
    return decode_guest_python_output($output);
}

function require_role(string ...$roles): void
{
    require_login();
    if (!has_role(...$roles)) {
        http_response_code(403);
        exit('Forbidden: your account does not have permission to access this page.');
    }
}

function user_count(): int
{
    return (int)auth_db()->querySingle('SELECT COUNT(*) FROM users');
}

function super_admin_count(): int
{
    return (int)auth_db()->querySingle("SELECT COUNT(*) FROM users WHERE role = 'super_admin'");
}

function authenticate(string $identifier, string $password): ?array
{
    $identifier = trim($identifier);
    if (str_contains($identifier, '@')) {
        $stmt = auth_db()->prepare(
            'SELECT id, username, password_hash, role
             FROM users WHERE email = :email COLLATE NOCASE'
        );
        $stmt->bindValue(':email', $identifier, SQLITE3_TEXT);
    } else {
        $stmt = auth_db()->prepare(
            'SELECT id, username, password_hash, role
             FROM users WHERE username = :username'
        );
        $stmt->bindValue(':username', $identifier, SQLITE3_TEXT);
    }
    $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    if (!is_array($row) || !password_verify($password, $row['password_hash'])) {
        return null;
    }

    unset($row['password_hash']);
    return $row;
}

function create_user(string $username, string $password, string $role): bool
{
    if (!preg_match('/^[A-Za-z0-9_.-]{3,40}$/', $username) || strlen($password) < 8) {
        return false;
    }
    if (!in_array($role, ['user', 'admin', 'super_admin'], true)) {
        return false;
    }
    if ($role === 'super_admin' && super_admin_count() >= 4) {
        return false;
    }

    try {
        $stmt = auth_db()->prepare(
            'INSERT INTO users (username, password_hash, role) VALUES (:username, :password_hash, :role)'
        );
        $stmt->bindValue(':username', $username, SQLITE3_TEXT);
        $stmt->bindValue(':password_hash', password_hash($password, PASSWORD_DEFAULT), SQLITE3_TEXT);
        $stmt->bindValue(':role', $role, SQLITE3_TEXT);
        $stmt->execute();
        return true;
    } catch (Throwable $error) {
        return false;
    }
}

function create_initial_super_admin(string $username, string $password): bool
{
    $db = auth_db();
    $transactionStarted = false;
    try {
        $db->exec('BEGIN IMMEDIATE');
        $transactionStarted = true;
        if (user_count() !== 0 || !create_user($username, $password, 'super_admin')) {
            $db->exec('ROLLBACK');
            return false;
        }

        $db->exec('COMMIT');
        $transactionStarted = false;
        return true;
    } catch (Throwable $error) {
        if ($transactionStarted) {
            try {
                $db->exec('ROLLBACK');
            } catch (Throwable $rollbackError) {
                error_log('Initial super-admin transaction rollback failed: ' . $rollbackError->getMessage());
            }
        }
        error_log('Initial super-admin account creation failed: ' . $error->getMessage());
        return false;
    }
}

function create_signup_user(string $username, string $email, string $password): array
{
    $email = strtolower(trim($email));
    if (!preg_match('/^[A-Za-z0-9_.-]{3,40}$/', $username)
        || strlen($email) > 254
        || filter_var($email, FILTER_VALIDATE_EMAIL) === false
        || strlen($password) < 8) {
        return ['success' => false, 'role' => null, 'reason' => 'invalid'];
    }

    $db = auth_db();
    $transactionStarted = false;
    try {
        $db->exec('BEGIN IMMEDIATE');
        $transactionStarted = true;

        $duplicateStmt = $db->prepare('SELECT 1 FROM users WHERE username = :username LIMIT 1');
        $duplicateStmt->bindValue(':username', $username, SQLITE3_TEXT);
        $duplicate = $duplicateStmt->execute()->fetchArray(SQLITE3_ASSOC);
        if (is_array($duplicate)) {
            $db->exec('ROLLBACK');
            return ['success' => false, 'role' => null, 'reason' => 'duplicate'];
        }

        $duplicateEmailStmt = $db->prepare(
            'SELECT 1 FROM users WHERE email = :email COLLATE NOCASE LIMIT 1'
        );
        $duplicateEmailStmt->bindValue(':email', $email, SQLITE3_TEXT);
        $duplicateEmail = $duplicateEmailStmt->execute()->fetchArray(SQLITE3_ASSOC);
        if (is_array($duplicateEmail)) {
            $db->exec('ROLLBACK');
            return ['success' => false, 'role' => null, 'reason' => 'duplicate_email'];
        }

        $role = user_count() === 0 ? 'super_admin' : 'user';
        $insertStmt = $db->prepare(
            'INSERT INTO users (username, email, password_hash, role)
             VALUES (:username, :email, :password_hash, :role)'
        );
        $insertStmt->bindValue(':username', $username, SQLITE3_TEXT);
        $insertStmt->bindValue(':email', $email, SQLITE3_TEXT);
        $insertStmt->bindValue(':password_hash', password_hash($password, PASSWORD_DEFAULT), SQLITE3_TEXT);
        $insertStmt->bindValue(':role', $role, SQLITE3_TEXT);
        $insertStmt->execute();

        $db->exec('COMMIT');
        $transactionStarted = false;
        return ['success' => true, 'role' => $role, 'reason' => ''];
    } catch (Throwable $error) {
        if ($transactionStarted) {
            try {
                $db->exec('ROLLBACK');
            } catch (Throwable $rollbackError) {
                error_log('Public signup transaction rollback failed: ' . $rollbackError->getMessage());
            }
        }
        error_log('Public signup account creation failed: ' . $error->getMessage());
        return ['success' => false, 'role' => null, 'reason' => 'create_failed'];
    }
}

function update_user(int $id, string $username, string $password, string $role): bool
{
    if ($id < 1 || !preg_match('/^[A-Za-z0-9_.-]{3,40}$/', $username)) {
        return false;
    }
    if ($password !== '' && strlen($password) < 8) {
        return false;
    }
    if (!in_array($role, ['user', 'admin', 'super_admin'], true)) {
        return false;
    }

    $db = auth_db();
    $existingRole = $db->querySingle(
        'SELECT role FROM users WHERE id = ' . $id,
        false
    );
    if ($existingRole === false) {
        return false;
    }
    if ($existingRole !== 'super_admin' && $role === 'super_admin' && super_admin_count() >= 4) {
        return false;
    }
    if ($existingRole === 'super_admin' && $role !== 'super_admin' && super_admin_count() <= 1) {
        return false;
    }

    try {
        if ($password === '') {
            $stmt = $db->prepare('UPDATE users SET username = :username, role = :role WHERE id = :id');
        } else {
            $stmt = $db->prepare(
                'UPDATE users SET username = :username, password_hash = :password_hash, role = :role WHERE id = :id'
            );
            $stmt->bindValue(':password_hash', password_hash($password, PASSWORD_DEFAULT), SQLITE3_TEXT);
        }
        $stmt->bindValue(':username', $username, SQLITE3_TEXT);
        $stmt->bindValue(':role', $role, SQLITE3_TEXT);
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        $stmt->execute();
        return $db->changes() > 0;
    } catch (Throwable $error) {
        return false;
    }
}

function delete_user(int $id): bool
{
    $user = current_user();
    if ($id < 1 || ($user !== null && (int)$user['id'] === $id)) {
        return false;
    }

    $db = auth_db();
    $role = $db->querySingle('SELECT role FROM users WHERE id = ' . $id, false);
    if ($role === false || ($role === 'super_admin' && super_admin_count() <= 1)) {
        return false;
    }

    try {
        $stmt = $db->prepare('DELETE FROM users WHERE id = :id');
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        $stmt->execute();
        return $db->changes() > 0;
    } catch (Throwable $error) {
        return false;
    }
}
