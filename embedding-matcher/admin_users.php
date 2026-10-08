<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'
    && !validate_csrf_token($_POST['csrf_token'] ?? null)) {
    http_response_code(400);
    exit('Invalid or expired form token. Please reload the page and try again.');
}
require_role('super_admin');

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $role = (string)($_POST['role'] ?? 'user');
    $userId = (int)($_POST['user_id'] ?? 0);

    if ($action === 'create') {
        $success = create_user($username, $password, $role);
        $message = $success ? 'User created.' : 'Could not create user. Check the username, password length, role, super-admin limit, or duplicate username.';
    } elseif ($action === 'update') {
        $success = update_user($userId, $username, $password, $role);
        if ($success && current_user() !== null && (int)current_user()['id'] === $userId) {
            $_SESSION['user']['username'] = $username;
            $_SESSION['user']['role'] = $role;
        }
        $message = $success ? 'User updated.' : 'Could not update user. The last super admin cannot be demoted, and there can be at most four super admins.';
    } elseif ($action === 'delete') {
        $success = delete_user($userId);
        $message = $success ? 'User deleted.' : 'Could not delete user. You cannot delete your own account or the last super admin.';
    }
}

$users = [];
$result = auth_db()->query('SELECT id, username, role, created_at FROM users ORDER BY username');
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $users[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management</title>
    <link rel="stylesheet" href="assets/theme.css">
</head>
<?php $activePage = 'users'; require __DIR__ . '/partials/dashboard_shell_start.php'; ?>
        <div class="admin-container">
        <h1>User Management</h1>

        <?php if ($message !== ''): ?><div class="notice info" role="status"><?= htmlspecialchars($message) ?></div><?php endif; ?>

        <section class="panel">
            <div class="panel-header">
                <h2>Create account</h2>
            </div>
            <div class="panel-body">
                <form method="post" class="admin-form">
                    <?= csrf_token_field() ?>
                    <input type="hidden" name="action" value="create">
                    <div class="field">
                        <label for="create-username">Username</label>
                        <input id="create-username" name="username" required minlength="3" maxlength="40">
                    </div>
                    <div class="field">
                        <label for="create-password">Password</label>
                        <div class="password-control">
                            <input id="create-password" type="password" name="password" required minlength="8">
                            <button type="button" class="toggle-password" data-target="create-password" aria-label="Show password" title="Show password">Show</button>
                        </div>
                    </div>
                    <div class="field">
                        <label for="create-role">Role</label>
                        <select id="create-role" name="role"><option value="user">User</option><option value="admin">Admin</option><option value="super_admin">Super admin</option></select>
                    </div>
                    <button type="submit">Create user</button>
                </form>
            </div>
        </section>

        <section class="panel">
            <div class="panel-header"><h2>Accounts</h2></div>
            <div class="table-wrap">
                <table class="account-table">
                    <thead><tr><th>Username</th><th>Role</th><th>Created / Password</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <?php $userId = (int)$user['id']; $updateFormId = 'update-user-' . $userId; $passwordId = 'password-user-' . $userId; ?>
                            <tr>
                                <td><input form="<?= $updateFormId ?>" name="username" value="<?= htmlspecialchars($user['username']) ?>" required minlength="3" maxlength="40" aria-label="Username for <?= htmlspecialchars($user['username']) ?>"></td>
                                <td><select form="<?= $updateFormId ?>" name="role" aria-label="Role for <?= htmlspecialchars($user['username']) ?>"><option value="user" <?= $user['role'] === 'user' ? 'selected' : '' ?>>User</option><option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Admin</option><option value="super_admin" <?= $user['role'] === 'super_admin' ? 'selected' : '' ?>>Super admin</option></select></td>
                                <td>
                                    <span class="meta"><?= htmlspecialchars($user['created_at']) ?></span>
                                    <div class="password-control">
                                        <input id="<?= $passwordId ?>" form="<?= $updateFormId ?>" type="password" name="password" minlength="8" placeholder="New password (optional)" aria-label="New password for <?= htmlspecialchars($user['username']) ?>">
                                        <button type="button" class="toggle-password" data-target="<?= $passwordId ?>" aria-label="Show password" title="Show password">Show</button>
                                    </div>
                                </td>
                                <td>
                                    <div class="actions">
                                        <form id="<?= $updateFormId ?>" method="post"><?= csrf_token_field() ?><input type="hidden" name="action" value="update"><input type="hidden" name="user_id" value="<?= $userId ?>"><button type="submit" class="button-secondary">Update</button></form>
                                        <form method="post" onsubmit="return confirm('Delete this account?');"><?= csrf_token_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="user_id" value="<?= $userId ?>"><button type="submit" class="button-danger">Delete</button></form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
        </div>
<?php require __DIR__ . '/partials/dashboard_shell_end.php'; ?>
    <script>
        document.querySelectorAll('.toggle-password').forEach(function (button) {
            button.addEventListener('click', function () {
                var input = document.getElementById(button.dataset.target);
                var showing = input.type === 'text';
                input.type = showing ? 'password' : 'text';
                button.textContent = showing ? 'Show' : 'Hide';
                button.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
                button.setAttribute('title', showing ? 'Show password' : 'Hide password');
            });
        });
    </script>
</body>
</html>
