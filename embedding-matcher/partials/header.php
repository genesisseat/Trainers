<?php
$activePage = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
$navigation = [
    'index.php' => 'Dashboard',
    'generated_curriculum.php' => 'Generated Drafts',
    'enhanced_curriculum_generated.php' => 'Enhance Curriculum',
    'draft_history.php' => 'Draft History',
];
?>
<header class="app-header">
    <div class="brand-row">
        <a class="wordmark" href="index.php" aria-label="Curriculum Enhancer home">
            <span class="wordmark-mark" aria-hidden="true">C</span>
            <span>Curriculum Enhancer</span>
        </a>
        <span class="brand-context">Academic planning workspace</span>
    </div>
    <?php if (current_user() !== null): ?>
        <nav class="nav" aria-label="Primary navigation">
            <?php foreach ($navigation as $page => $label): ?>
                <a href="<?= htmlspecialchars($page, ENT_QUOTES, 'UTF-8') ?>"<?= $activePage === $page ? ' aria-current="page"' : '' ?>><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></a>
            <?php endforeach; ?>
            <?php if (has_role('super_admin')): ?>
                <a href="dataset_management.php"<?= $activePage === 'dataset_management.php' ? ' aria-current="page"' : '' ?>>Dataset Management</a>
                <a href="admin_users.php"<?= $activePage === 'admin_users.php' ? ' aria-current="page"' : '' ?>>User Management</a>
            <?php endif; ?>
            <a class="nav-account" href="logout.php">Log out (<?= htmlspecialchars((string)current_user()['username'], ENT_QUOTES, 'UTF-8') ?>)</a>
        </nav>
    <?php endif; ?>
</header>
