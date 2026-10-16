<?php
$currentUser = $currentUser ?? current_user();
$currentUserApiKey = $currentUserApiKey ?? (
    $currentUser !== null ? get_user_api_key((int)$currentUser['id']) : null
);
$isGuestShell = $currentUser === null && (!empty($_SESSION['guest_trial_mode']) || (isset($_GET['guest']) && $_GET['guest'] === '1'));
$activePage = $activePage ?? '';
$accountMenuOpen = $accountMenuOpen ?? false;
$apiKeyMessage = $apiKeyMessage ?? '';
$apiKeyMessageType = $apiKeyMessageType ?? 'info';

$shellHistory = [];
if ($currentUser !== null) {
    $shellDb = new SQLite3(__DIR__ . '/../curriculum_matching.db', SQLITE3_OPEN_READONLY);
    $shellDb->busyTimeout(30000);
    $shellDb->enableExceptions(true);
    $shellHistoryScope = run_access_sql_scope($currentUser);
    $shellHistoryStmt = $shellDb->prepare(
        "SELECT id, program, prompt, source
         FROM generated_curriculum_runs r
         WHERE {$shellHistoryScope['sql']}
         ORDER BY generated_at DESC, id DESC
         LIMIT 12"
    );
    run_access_bind_scope($shellHistoryStmt, $shellHistoryScope);
    $shellHistoryResult = $shellHistoryStmt->execute();
    while ($shellHistoryRow = $shellHistoryResult->fetchArray(SQLITE3_ASSOC)) {
        $shellHistory[] = $shellHistoryRow;
    }
    $shellDb->close();
}
?>
<body class="dashboard-body">
    <button class="sidebar-toggle" type="button" aria-label="Open navigation" aria-expanded="false" aria-controls="dashboard-sidebar"><span></span><span></span><span></span></button>
    <div class="dashboard-shell">
        <aside class="dashboard-sidebar" id="dashboard-sidebar" aria-label="Dashboard sidebar">
            <div class="dashboard-brand">
                <a class="dashboard-wordmark" href="<?= $isGuestShell ? 'generated_curriculum.php?guest=1' : 'index.php' ?>"><img class="brand-logo" src="assets/curri-kotoh-logo.png" alt=""><span>Curri'KoToh</span></a>
            </div>
            <nav class="sidebar-nav" aria-label="Primary navigation">
                <?php if (!$isGuestShell): ?>
                    <a class="sidebar-link" href="index.php" <?= $activePage === 'dashboard' ? 'aria-current="page"' : '' ?>>
                        <svg class="sidebar-link-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1h-6v-7h-4v7H4a1 1 0 0 1-1-1z"/></svg>
                        <span>Dashboard</span>
                    </a>
                <?php endif; ?>
                <a class="sidebar-link" href="<?= $isGuestShell ? 'generated_curriculum.php?guest=1' : 'generated_curriculum.php' ?>" <?= $activePage === 'generate' ? 'aria-current="page"' : '' ?>>
                    <svg class="sidebar-link-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                    <span>Generate new</span>
                </a>
                <a class="sidebar-link" href="<?= $isGuestShell ? 'enhanced_curriculum_generated.php?guest=1' : 'enhanced_curriculum_generated.php' ?>" <?= $activePage === 'enhance' ? 'aria-current="page"' : '' ?>>
                    <svg class="sidebar-link-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v3m0 12v3M3 12h3m12 0h3M5.64 5.64l2.12 2.12m8.48 8.48 2.12 2.12m0-12.72-2.12 2.12m-8.48 8.48-2.12 2.12"/><path d="m9 9 6 6m0-6-6 6"/></svg>
                    <span>Enhance new</span>
                </a>
                <?php if (!$isGuestShell): ?>
                    <a class="sidebar-link" href="draft_history.php" <?= $activePage === 'history' ? 'aria-current="page"' : '' ?>>
                        <svg class="sidebar-link-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16M4 12h16M4 19h16"/><circle cx="2" cy="5" r=".5"/><circle cx="2" cy="12" r=".5"/><circle cx="2" cy="19" r=".5"/></svg>
                        <span>Draft History</span>
                    </a>
                <?php endif; ?>
                <?php if ($isGuestShell): ?>
                    <a class="sidebar-link" href="landing.php">
                        <svg class="sidebar-link-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M19 12H5m7 7-7-7 7-7"/></svg>
                        <span>Back to landing page</span>
                    </a>
                    <a class="sidebar-link" href="signup.php">
                        <svg class="sidebar-link-icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="8" r="4"/><path d="M3 21v-2a6 6 0 0 1 12 0v2m4-11v6m-3-3h6"/></svg>
                        <span>Create an account</span>
                    </a>
                <?php endif; ?>
                <?php if (has_role('super_admin')): ?>
                    <a class="sidebar-link" href="admin_users.php" <?= $activePage === 'users' ? 'aria-current="page"' : '' ?>>
                        <svg class="sidebar-link-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="10" cy="7" r="4"/><path d="M20 8v6m-3-3h6"/></svg>
                        <span>User Management</span>
                    </a>
                <?php endif; ?>
            </nav>
            <?php if (!$isGuestShell): ?>
            <section class="sidebar-history" aria-labelledby="history-title">
                <div class="sidebar-section-heading"><h2 id="history-title">Recent history</h2><a href="generated_curriculum.php">View all</a></div>
                <label class="visually-hidden" for="history-search">Search run history</label>
                <input class="history-search" type="search" id="history-search" placeholder="Search programs or prompts">
                <ul class="history-list" id="history-list">
                    <?php foreach ($shellHistory as $historyRun): ?>
                        <?php
                            $historySource = ($historyRun['source'] ?? '') === 'enhanced' ? 'enhanced' : 'generated';
                            $historyHref = $historySource === 'enhanced'
                                ? 'enhanced_curriculum_generated.php?run_id=' . (int)$historyRun['id'] . '#run-' . (int)$historyRun['id']
                                : 'generated_curriculum.php?run_id=' . (int)$historyRun['id'] . '#run-' . (int)$historyRun['id'];
                            $historySearch = strtolower((string)$historyRun['program'] . ' ' . (string)$historyRun['prompt']);
                        ?>
                        <li data-history-search="<?= htmlspecialchars($historySearch, ENT_QUOTES, 'UTF-8') ?>">
                            <a class="history-link" href="<?= htmlspecialchars($historyHref, ENT_QUOTES, 'UTF-8') ?>">
                                <span class="source-dot source-<?= htmlspecialchars($historySource, ENT_QUOTES, 'UTF-8') ?>" title="<?= $historySource === 'enhanced' ? 'Enhanced' : 'Generated' ?>" aria-label="<?= $historySource === 'enhanced' ? 'Enhanced' : 'Generated' ?>"></span>
                                <span class="history-item-text"><span><?= htmlspecialchars((string)$historyRun['program']) ?></span><small>Run #<?= (int)$historyRun['id'] ?></small></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <p class="history-empty" id="history-empty" hidden>No matching runs.</p>
            </section>
            <?php endif; ?>
            <?php if ($currentUser !== null): ?>
            <section class="sidebar-account">
                <button class="account-toggle" type="button" id="account-toggle" aria-expanded="<?= $accountMenuOpen ? 'true' : 'false' ?>" aria-controls="account-menu">
                    <span class="account-avatar" aria-hidden="true"><?= htmlspecialchars(strtoupper(substr((string)$currentUser['username'], 0, 1))) ?></span>
                    <span class="account-name"><?= htmlspecialchars((string)$currentUser['username']) ?></span>
                    <span class="account-chevron" aria-hidden="true">▴</span>
                </button>
                <div class="account-menu" id="account-menu" <?= $accountMenuOpen ? '' : 'hidden' ?>>
                    <p class="account-menu-title">Account settings</p>
                    <?php if ($apiKeyMessage !== ''): ?>
                        <div class="notice <?= htmlspecialchars($apiKeyMessageType, ENT_QUOTES, 'UTF-8') ?>" role="status">
                            <?= htmlspecialchars($apiKeyMessage, ENT_QUOTES, 'UTF-8') ?>
                            <a href="#api_key_value" data-focus-api-key>Open key settings</a>
                        </div>
                    <?php endif; ?>
                    <p class="key-status">
                        <span class="status-dot <?= $currentUserApiKey !== null ? 'status-dot-good' : 'status-dot-missing' ?>" aria-hidden="true"></span>
                        Gemini key: <?= htmlspecialchars(mask_user_api_key($currentUserApiKey), ENT_QUOTES, 'UTF-8') ?>
                    </p>
                    <form method="post" action="index.php" class="account-key-form">
                        <?= csrf_token_field() ?>
                        <input type="hidden" name="api_key_action" value="save">
                        <label for="api_key_value">Add or replace Gemini API key</label>
                        <input type="password" id="api_key_value" name="api_key_value" placeholder="Paste your personal key" autocomplete="new-password" required>
                        <button type="submit">Save key</button>
                    </form>
                    <?php if ($currentUserApiKey !== null): ?>
                        <form method="post" action="index.php" class="account-remove-form">
                            <?= csrf_token_field() ?>
                            <input type="hidden" name="api_key_action" value="clear">
                            <button class="account-remove-button" type="submit">Remove key</button>
                        </form>
                    <?php endif; ?>
                    <?php if (has_role('super_admin')): ?>
                        <a class="account-menu-link" href="admin_users.php">User Management</a>
                    <?php endif; ?>
                    <a class="account-menu-link" href="logout.php">Log out</a>
                </div>
            </section>
            <?php endif; ?>
        </aside>
        <button class="sidebar-backdrop" type="button" aria-label="Close navigation" tabindex="-1"></button>
        <main class="dashboard-main" id="dashboard">
            <header class="dashboard-topbar">
                <a class="topbar-wordmark" href="<?= $isGuestShell ? 'landing.php' : 'index.php' ?>"><img class="brand-logo" src="assets/curri-kotoh-logo.png" alt=""><span>Curri'KoToh</span></a>
                <span class="topbar-context">Curriculum ko to eh!</span>
            </header>
