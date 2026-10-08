<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'
    && !validate_csrf_token($_POST['csrf_token'] ?? null)) {
    http_response_code(400);
    exit('Invalid or expired form token. Please reload the page and try again.');
}

if (current_user() !== null) {
    header('Location: index.php');
    exit;
}

$setupMode = user_count() === 0;
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim((string)($_POST['login_identifier'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($setupMode) {
        if (!str_contains($identifier, '@')
            && create_initial_super_admin($identifier, $password)) {
            $success = 'Super admin created. You can now log in.';
            $setupMode = false;
        } elseif (user_count() > 0) {
            $error = 'Initial setup has already been completed. Please log in with your account.';
            $setupMode = false;
        } else {
            $error = 'Use a username with 3-40 letters, numbers, dots, dashes, or underscores, and a password of at least 8 characters.';
        }
    } else {
        $user = authenticate($identifier, $password);
        if ($user === null) {
            $error = 'Invalid username or password.';
        } else {
            session_regenerate_id(true);
            $_SESSION['user'] = $user;
            header('Location: index.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $setupMode ? 'Create Super Admin' : 'Log In' ?></title>
    <link rel="stylesheet" href="assets/theme.css">
</head>
<body>
    <main class="login-page">
        <section class="login-card auth-card">
            <a class="auth-back" href="landing.php"><span aria-hidden="true">←</span> Back</a>
            <?php if ($setupMode): ?><p class="setup-note">First account · becomes super admin</p><?php endif; ?>
            <h1><?= $setupMode ? 'Create first account' : 'Log in' ?></h1>
            <?php if ($error !== ''): ?><div class="notice error" role="alert"><?= htmlspecialchars($error) ?></div><?php endif; ?>
            <?php if ($success !== ''): ?><div class="notice success" role="status"><?= htmlspecialchars($success) ?></div><?php endif; ?>
            <form method="post">
                <?= csrf_token_field() ?>
                <label for="login-identifier"><?= $setupMode ? 'Username' : 'Username or email' ?><input id="login-identifier" name="login_identifier" required autocomplete="username" placeholder="<?= $setupMode ? 'Choose a username' : 'Enter username or email' ?>"></label>
                <label for="login-password">Password<input id="login-password" type="password" name="password" required minlength="8" autocomplete="<?= $setupMode ? 'new-password' : 'current-password' ?>" placeholder="Enter password"></label>
                <button class="login-submit" type="submit"><?= $setupMode ? 'Create super admin' : 'Log in' ?></button>
            </form>
            <p class="auth-footer">Don't have an account? <a href="signup.php">Sign up now</a></p>
        </section>
    </main>
</body>
</html>
