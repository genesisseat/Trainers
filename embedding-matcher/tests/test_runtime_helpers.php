<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth.php';

function runtime_expect(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

function runtime_remove_tree(string $path): void
{
    if (!is_dir($path)) {
        return;
    }
    foreach (array_diff(scandir($path) ?: [], ['.', '..']) as $entry) {
        $entryPath = $path . DIRECTORY_SEPARATOR . $entry;
        if (is_dir($entryPath)) {
            runtime_remove_tree($entryPath);
        } else {
            unlink($entryPath);
        }
    }
    rmdir($path);
}

$root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'curriculum-runtime-' . bin2hex(random_bytes(6));
mkdir($root);
$lockPath = $root . DIRECTORY_SEPARATOR . 'job.lock';
try {
    $windowsPython = $root . '/venv/Scripts/python.exe';
    $linuxPython = $root . '/venv/bin/python';
    mkdir(dirname($windowsPython), 0777, true);
    mkdir(dirname($linuxPython), 0777, true);
    file_put_contents($windowsPython, '');
    file_put_contents($linuxPython, '');
    $configuredPython = $root . '/custom-python';
    file_put_contents($configuredPython, '');

    runtime_expect(
        resolve_python_executable('Windows', $root, null) === $windowsPython,
        'Windows venv interpreter resolution failed.'
    );
    runtime_expect(
        resolve_python_executable('Linux', $root, null) === $linuxPython,
        'Linux venv interpreter resolution failed.'
    );
    runtime_expect(
        resolve_python_executable('Linux', $root, $configuredPython) === $configuredPython,
        'Existing PYTHON_BIN must take precedence.'
    );
    runtime_expect(
        resolve_python_executable('Linux', $root, $root . '/missing-python') === $linuxPython,
        'A missing PYTHON_BIN should fall back to the platform venv.'
    );
    runtime_expect(
        resolve_python_executable('Linux', $root . '/missing-root', null) === null,
        'Missing interpreters should resolve to null.'
    );

    $script = __FILE__;
    if (($argv[1] ?? '') === 'hold-lock') {
        run_command_with_api_key([PHP_BINARY, '-r', 'usleep(1000000);'], null, 1, 2, $argv[2]);
        exit(0);
    }

    $holder = proc_open(
        [PHP_BINARY, $script, 'hold-lock', $lockPath],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $holderPipes
    );
    runtime_expect(is_resource($holder), 'Could not start the lock-holder process.');
    fclose($holderPipes[0]);
    fclose($holderPipes[1]);
    fclose($holderPipes[2]);
    $holderHasLock = false;
    $lockProbe = fopen($lockPath, 'c');
    $lockDeadline = microtime(true) + 5;
    while (microtime(true) < $lockDeadline) {
        if (!flock($lockProbe, LOCK_EX | LOCK_NB)) {
            $holderHasLock = true;
            break;
        }
        flock($lockProbe, LOCK_UN);
        usleep(20000);
    }
    runtime_expect($holderHasLock, 'Lock-holder did not acquire the lock in time.');
    $busyMessage = '';
    try {
        run_command_with_api_key([PHP_BINARY, '-r', 'echo "unexpected";'], null, 0.2, 2, $lockPath);
    } catch (RuntimeException $error) {
        $busyMessage = $error->getMessage();
    }
    runtime_expect(
        $busyMessage === 'The system is busy, please try again in a minute.',
        'A second job should receive the bounded busy response.'
    );
    flock($lockProbe, LOCK_UN);
    fclose($lockProbe);
    proc_close($holder);

    $timeoutMessage = '';
    try {
        run_command_with_api_key([PHP_BINARY, '-r', 'usleep(5000000);'], null, 1, 0.2, $lockPath);
    } catch (RuntimeException $error) {
        $timeoutMessage = $error->getMessage();
    }
    runtime_expect(
        $timeoutMessage === 'The request took too long. Please try again.',
        'Process timeout response failed: ' . var_export($timeoutMessage, true)
    );
    $output = run_command_with_api_key([PHP_BINARY, '-r', 'echo "released";'], null, 1, 2, $lockPath);
    runtime_expect($output === 'released', 'The lock was not released after timeout.');

    $startFailure = false;
    try {
        run_command_with_api_key([$root . '/missing-command'], null, 1, 2, $lockPath);
    } catch (RuntimeException $error) {
        $startFailure = true;
    }
    runtime_expect($startFailure, 'An invalid executable should report a start failure.');
    $output = run_command_with_api_key([PHP_BINARY, '-r', 'echo "released";'], null, 1, 2, $lockPath);
    runtime_expect($output === 'released', 'The lock was not released after process start failure.');
    $output = run_command_with_api_key(
        [PHP_BINARY, '-r', 'echo getenv("GEMINI_API_KEY");'],
        'runtime-test-secret',
        1,
        2,
        $lockPath
    );
    runtime_expect($output === '[redacted]', 'API-key output must be redacted before returning.');
} finally {
    runtime_remove_tree($root);
}

echo "Runtime helper tests passed." . PHP_EOL;
