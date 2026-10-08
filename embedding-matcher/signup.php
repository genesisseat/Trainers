<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';

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

if (current_user() === null) {
    $signupTabId = $_GET['guest_tab_id'] ?? null;
    $activeGuestTabId = $_SESSION['guest_trial_tab_id'] ?? null;
    if (isset($_SESSION['guest_trial_draft'])
        && (!is_string($signupTabId)
            || !preg_match('/^[a-f0-9]{64}$/', $signupTabId)
            || !is_string($activeGuestTabId)
            || !hash_equals($activeGuestTabId, $signupTabId))) {
        clear_guest_trial_draft();
    }
}

if (current_user() !== null) {
    header('Location: index.php');
    exit;
}

$error = '';
$honeypotTriggered = false;
$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($requestMethod === 'POST') {
    $now = time();
    $honeypot = $_POST['company_website'] ?? '';
    $honeypotTriggered = !is_string($honeypot) || trim($honeypot) !== '';
    $attempts = $_SESSION['signup_attempts'] ?? [];
    $attempts = is_array($attempts) ? $attempts : [];
    $attempts = array_values(array_filter(
        $attempts,
        static fn($attempt): bool => is_int($attempt) && $attempt > $now - 900
    ));

    if (count($attempts) >= 8) {
        $_SESSION['signup_attempts'] = $attempts;
        if (!$honeypotTriggered) {
            $error = 'Too many attempts. Please wait a little while before trying again.';
        }
    } else {
        $attempts[] = $now;
        $_SESSION['signup_attempts'] = $attempts;

        if (!$honeypotTriggered) {
            $username = isset($_POST['username']) && is_string($_POST['username'])
                ? trim($_POST['username'])
                : '';
            $email = isset($_POST['email']) && is_string($_POST['email'])
                ? strtolower(trim($_POST['email']))
                : '';
            $password = isset($_POST['password']) && is_string($_POST['password'])
                ? $_POST['password']
                : '';
            $confirmPassword = isset($_POST['confirm_password']) && is_string($_POST['confirm_password'])
                ? $_POST['confirm_password']
                : '';

            if ($password !== $confirmPassword) {
                $error = 'Passwords do not match.';
            } elseif (!preg_match('/^[A-Za-z0-9_.-]{3,40}$/', $username)
                || strlen($email) > 254
                || filter_var($email, FILTER_VALIDATE_EMAIL) === false
                || strlen($password) < 8) {
                $error = 'Enter a valid email, use a username with 3-40 letters, numbers, dots, dashes, or underscores, and use a password of at least 8 characters.';
            } else {
                $signupResult = create_signup_user($username, $email, $password);
                if (!$signupResult['success']) {
                    $error = match ($signupResult['reason']) {
                        'duplicate' => 'That username is already taken. Choose another.',
                        'duplicate_email' => 'That email address is already in use. Use another email address.',
                        'invalid' => 'Enter a valid email, use a username with 3-40 letters, numbers, dots, dashes, or underscores, and use a password of at least 8 characters.',
                        default => 'Your account could not be created right now. Please try again.',
                    };
                } else {
                    $user = authenticate($username, $password);
                    if ($user === null) {
                        $error = 'Your account was created, but automatic sign-in failed. Please log in.';
                    } else {
                        unset($_SESSION['signup_attempts']);
                        $guestDraft = $_SESSION['guest_trial_draft'] ?? null;
                        $guestDraftType = is_array($guestDraft) && isset($guestDraft['type']) ? (string)$guestDraft['type'] : '';

                        if (is_array($guestDraft) && $guestDraftType !== '') {
                            $newUserId = (int)($user['id'] ?? 0);
                            $newUsername = (string)($user['username'] ?? '');
                            $dbPath = __DIR__ . '/curriculum_matching.db';
                            $pythonExe = __DIR__ . '/venv/Scripts/python.exe';
                            $script = __DIR__ . '/user_operations.py';
                            $draftFile = tempnam(sys_get_temp_dir(), 'guest_draft_');
                            $draftJson = json_encode($guestDraft, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

                            if ($draftFile === false
                                || $draftJson === false
                                || file_put_contents($draftFile, $draftJson) === false
                                || !is_file($pythonExe)
                                || !is_file($script)) {
                                if (is_string($draftFile) && file_exists($draftFile)) {
                                    unlink($draftFile);
                                }
                                $error = 'Your account was created, but the guest draft could not be saved. Please log in and try again.';
                            } else {
                                try {
                                    $output = run_command_with_api_key([
                                        $pythonExe,
                                        $script,
                                        '--import-guest-draft-json',
                                        $draftFile,
                                        '--output-db',
                                        $dbPath,
                                        '--actor-user-id',
                                        (string)$newUserId,
                                        '--actor-username',
                                        $newUsername,
                                    ]);
                                    $importResult = json_decode(trim($output), true);
                                    $savedRunId = is_array($importResult) ? (int)($importResult['run_id'] ?? 0) : 0;
                                    $savedType = is_array($importResult) ? (string)($importResult['type'] ?? '') : '';
                                    if ($savedRunId < 1 || $savedType !== $guestDraftType) {
                                        throw new RuntimeException('The guest draft import did not return a valid saved run.');
                                    }
                                } catch (RuntimeException $importError) {
                                    error_log('Guest draft signup import failed: ' . $importError->getMessage());
                                    $error = 'Your account was created, but the guest draft could not be saved. Please log in and try again.';
                                } finally {
                                    if (file_exists($draftFile)) {
                                        unlink($draftFile);
                                    }
                                }
                            }

                            if ($error === '') {
                                unset($_SESSION['guest_trial_draft'], $_SESSION['guest_attempts_used'], $_SESSION['guest_ip_attempts'], $_SESSION['guest_trial_mode'], $_SESSION['guest_trial_tab_id']);
                                session_regenerate_id(true);
                                $_SESSION['user'] = $user;
                                $savedPage = $guestDraftType === 'enhanced'
                                    ? 'enhanced_curriculum_generated.php?run_id=' . $savedRunId
                                    : 'generated_curriculum.php';
                                header('Location: ' . $savedPage . '#run-' . $savedRunId);
                                exit;
                            }
                        }

                        if (!is_array($guestDraft) || $guestDraftType === '') {
                            unset($_SESSION['guest_trial_draft'], $_SESSION['guest_attempts_used'], $_SESSION['guest_ip_attempts'], $_SESSION['guest_trial_mode'], $_SESSION['guest_trial_tab_id']);
                            session_regenerate_id(true);
                            $_SESSION['user'] = $user;
                            header('Location: index.php');
                            exit;
                        }
                    }
                }
            }
        }
    }
}

$firstAccountSetup = user_count() === 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign up</title>
    <link rel="stylesheet" href="assets/theme.css">
    <?php if (isset($_GET['guest_tab_id'])): ?>
        <script>
            (function () {
                var url = new URL(window.location.href);
                var navigation = window.performance && window.performance.getEntriesByType
                    ? window.performance.getEntriesByType('navigation')[0]
                    : null;
                if (navigation && navigation.type === 'reload' && !url.searchParams.has('guest_draft_clear')) {
                    url.searchParams.set('guest_draft_clear', '1');
                    window.location.replace(url.href);
                    return;
                }
                if (url.searchParams.has('guest_draft_clear') && typeof BroadcastChannel === 'function') {
                    var channel = new BroadcastChannel('curriculum-guest-draft');
                    channel.postMessage({
                        type: 'draft-cleared',
                        tabId: url.searchParams.get('guest_tab_id')
                    });
                    channel.close();
                }
            }());
        </script>
    <?php endif; ?>
</head>
<body>
    <main class="login-page">
        <section class="login-card auth-card">
            <a class="auth-back" href="login.php"><span aria-hidden="true">←</span> Back to log in</a>
            <?php if ($firstAccountSetup): ?><p class="setup-note">First account · becomes super admin</p><?php endif; ?>
            <h1><?= $firstAccountSetup ? 'Create first account' : 'Create account' ?></h1>
            <?php if ($error !== '' && !$honeypotTriggered): ?><div class="notice error" role="alert"><?= htmlspecialchars($error) ?></div><?php endif; ?>
            <form method="post">
                <?= csrf_token_field() ?>
                <div class="signup-honeypot" aria-hidden="true">
                    <label for="signup-company-website">Leave this field empty</label>
                    <input id="signup-company-website" type="text" name="company_website" tabindex="-1" autocomplete="off">
                </div>
                <label for="signup-username">Username<input id="signup-username" name="username" required minlength="3" maxlength="40" autocomplete="username" placeholder="Choose a username"></label>
                <label for="signup-email">Email<input id="signup-email" type="email" name="email" required maxlength="254" autocomplete="email" placeholder="Enter your email"></label>
                <label for="signup-password">Password<input id="signup-password" type="password" name="password" required minlength="8" autocomplete="new-password" placeholder="Create a password"></label>
                <label for="signup-confirm-password">Confirm password<input id="signup-confirm-password" type="password" name="confirm_password" required minlength="8" autocomplete="new-password" placeholder="Re-enter password"></label>
                <button class="login-submit" type="submit">Create account</button>
            </form>
            <p class="auth-footer">Already have an account? <a href="login.php">Log in</a></p>
        </section>
    </main>
</body>
</html>
