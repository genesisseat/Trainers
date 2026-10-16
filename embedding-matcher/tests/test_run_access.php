<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth.php';

if (!class_exists('SQLite3')) {
    echo "SKIP: PHP SQLite3 extension is unavailable." . PHP_EOL;
    exit(0);
}

$databasePath = tempnam(sys_get_temp_dir(), 'curriculum-run-access-');
if ($databasePath === false) {
    fwrite(STDERR, "Unable to create the temporary test database." . PHP_EOL);
    exit(1);
}

try {
    $db = new SQLite3($databasePath);
    $db->busyTimeout(30000);
    $db->enableExceptions(true);
    $db->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, role TEXT NOT NULL)');
    $db->exec('CREATE TABLE generated_curriculum_runs (id INTEGER PRIMARY KEY, created_by_user_id INTEGER)');
    $db->exec('CREATE TABLE generated_curriculum_subjects (id INTEGER PRIMARY KEY, run_id INTEGER)');
    $db->exec('CREATE TABLE generated_curriculum_chat (id INTEGER PRIMARY KEY, run_id INTEGER)');
    $db->exec('CREATE TABLE generated_curriculum_reviews (id INTEGER PRIMARY KEY, run_id INTEGER, decision TEXT)');
    $db->exec("INSERT INTO generated_curriculum_runs VALUES (1, 101)");
    $db->exec("INSERT INTO generated_curriculum_runs VALUES (2, 202)");
    $db->exec("INSERT INTO generated_curriculum_runs VALUES (3, NULL)");
    $db->exec("INSERT INTO generated_curriculum_subjects VALUES (1, 1)");
    $db->exec("INSERT INTO generated_curriculum_chat VALUES (1, 1)");
    $db->exec("INSERT INTO generated_curriculum_reviews VALUES (1, 1, 'legacy')");

    foreach ([
        [['id' => 101, 'role' => 'user'], [1]],
        [['id' => 202, 'role' => 'user'], [2]],
        [['id' => 303, 'role' => 'admin'], [1, 2]],
        [['id' => 404, 'role' => 'super_admin'], [1, 2, 3]],
    ] as [$viewer, $expectedRunIds]) {
        $scope = run_access_sql_scope($viewer);
        $listStmt = $db->prepare(
            'SELECT id FROM generated_curriculum_runs r WHERE ' . $scope['sql'] . ' ORDER BY id'
        );
        run_access_bind_scope($listStmt, $scope);
        $listResult = $listStmt->execute();
        $actualRunIds = [];
        while ($row = $listResult->fetchArray(SQLITE3_ASSOC)) {
            $actualRunIds[] = (int)$row['id'];
        }
        if ($actualRunIds !== $expectedRunIds) {
            throw new RuntimeException('Run history did not match the viewer ownership scope.');
        }

        $countStmt = $db->prepare(
            'SELECT COUNT(*) FROM generated_curriculum_runs r WHERE ' . $scope['sql']
        );
        run_access_bind_scope($countStmt, $scope);
        if ((int)$countStmt->execute()->fetchArray(SQLITE3_NUM)[0] !== count($expectedRunIds)) {
            throw new RuntimeException('Dashboard counts did not match the viewer ownership scope.');
        }
    }

    if (!run_access_can_access(['id' => 202, 'role' => 'user'], ['created_by_user_id' => 202])
        || run_access_can_access(['id' => 202, 'role' => 'user'], ['created_by_user_id' => 101])) {
        throw new RuntimeException('Run view/export access did not follow the ownership policy.');
    }

    run_access_delete($db, 1, ['id' => 101, 'role' => 'user']);
    if ((int)$db->querySingle('SELECT COUNT(*) FROM generated_curriculum_runs') !== 0
        || (int)$db->querySingle('SELECT COUNT(*) FROM generated_curriculum_subjects') !== 0
        || (int)$db->querySingle('SELECT COUNT(*) FROM generated_curriculum_chat') !== 0
        || (int)$db->querySingle('SELECT COUNT(*) FROM generated_curriculum_reviews') !== 1
        || (int)$db->querySingle('SELECT run_id FROM generated_curriculum_reviews WHERE id = 1') !== 1) {
        throw new RuntimeException('Deleting a run must leave its legacy review row orphaned and intact.');
    }

    echo "Run access deletion integration checks passed." . PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . PHP_EOL);
    exit(1);
} finally {
    if (isset($db) && $db instanceof SQLite3) {
        $db->close();
    }
    unlink($databasePath);
}
