<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../draft_management.php';

function expect_true(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

expect_true(draft_management_clean_text(str_repeat('a', 150), 150) !== null, '150-char title should be accepted.');
expect_true(draft_management_clean_text(str_repeat('a', 151), 150) === null, '151-char title should be rejected.');
expect_true(draft_management_clean_text(str_repeat('n', 5000), 5000) !== null, '5000-char notes should be accepted.');
expect_true(draft_management_clean_text(str_repeat('n', 5001), 5000) === null, '5001-char notes should be rejected.');
expect_true(draft_management_clean_text("safe\x01text", 20) === 'safetext', 'Control characters should be stripped.');
expect_true(draft_management_clean_text("safe\xC2\x85text", 20) === 'safetext', 'Unicode control characters should be stripped.');
expect_true(draft_management_clean_text([], 20) === null, 'Non-string input should be rejected.');
expect_true(draft_management_generation_mode_label('online') === 'Online Template', 'Online runs should have the agreed label.');
expect_true(draft_management_generation_mode_label('offline') === 'Offline template', 'Offline runs should have the fallback label.');
expect_true(draft_management_generation_mode_label(null) === 'Not recorded', 'Unknown legacy modes should remain neutral.');
expect_true(
    draft_management_enhancement_generation_mode(['fallback' => true, 'draft_fallback' => false]) === 'online',
    'A confirmed Gemini stage should mark an enhanced run online.'
);
expect_true(
    draft_management_enhancement_generation_mode(['fallback' => true, 'draft_fallback' => true]) === 'offline',
    'Both confirmed template stages should mark an enhanced run offline.'
);
expect_true(
    draft_management_enhancement_generation_mode(['fallback' => true]) === null,
    'Incomplete enhanced outcome evidence should remain unknown.'
);
expect_true(
    draft_management_can_update(['id' => 7, 'role' => 'user'], ['created_by_user_id' => 7]),
    'The creator should be allowed to update the run.'
);
expect_true(
    !draft_management_can_update(['id' => 8, 'role' => 'user'], ['created_by_user_id' => 7]),
    'A different user should not be allowed to update the run.'
);
expect_true(
    !draft_management_can_update(['id' => 8, 'role' => 'user'], ['created_by_user_id' => null]),
    'Unattributed runs should not be editable by regular users.'
);
expect_true(
    draft_management_can_update(['id' => 8, 'role' => 'super_admin'], ['created_by_user_id' => null]),
    'Super admins should be allowed to update unattributed runs.'
);
expect_true(!draft_management_can_update(['id' => 8, 'role' => 'user'], null), 'Missing runs should be denied.');
expect_true(
    run_access_can_access(['id' => 7, 'role' => 'user'], ['created_by_user_id' => 7]),
    'A regular user should be able to access their own run.'
);
expect_true(
    !run_access_can_access(['id' => 8, 'role' => 'user'], ['created_by_user_id' => 7]),
    'A regular user should not be able to access another user run.'
);
expect_true(
    run_access_can_delete(['id' => 7, 'role' => 'user'], ['created_by_user_id' => 7]),
    'A regular user should be able to delete their own run.'
);
expect_true(
    !run_access_can_delete(['id' => 8, 'role' => 'user'], ['created_by_user_id' => 7]),
    'A regular user should not be able to delete another user run.'
);
expect_true(
    run_access_can_access(['id' => 9, 'role' => 'admin'], ['created_by_user_id' => 7]),
    'An admin should retain access to attributed runs under the central admin policy.'
);
expect_true(
    !run_access_can_access(['id' => 9, 'role' => 'admin'], ['created_by_user_id' => null]),
    'An admin must not access unattributed runs.'
);
expect_true(
    run_access_can_access(['id' => 10, 'role' => 'super_admin'], ['created_by_user_id' => null]),
    'A super admin should be able to access unattributed runs.'
);
expect_true(
    run_access_sql_scope(['id' => 9, 'role' => 'admin'])['sql']
        === 'r.created_by_user_id IS NOT NULL AND r.created_by_user_id > 0',
    'The central admin SQL policy should exclude unattributed runs.'
);
expect_true(RUN_ACCESS_NOT_FOUND_BODY === 'Not found.', 'Run endpoints must share one generic not-found body.');
$storedKey = 'test-private-gemini-key';
expect_true(mask_user_api_key($storedKey) === '********', 'Personal API keys should render only as a fixed mask.');
expect_true(
    strpos(mask_user_api_key($storedKey), $storedKey) === false,
    'The masked account status must not contain the stored API key.'
);
$keyPolicyUser = ['id' => 7, 'role' => 'user'];
$keyPolicyAdmin = ['id' => 8, 'role' => 'admin'];
$keyPolicySuperAdmin = ['id' => 9, 'role' => 'super_admin'];
expect_true(
    !gemini_api_key_policy($keyPolicyUser, null, true)['allowed'],
    'A keyless user must not fall back to a shared key.'
);
expect_true(
    !gemini_api_key_policy($keyPolicyAdmin, null, true)['allowed'],
    'A keyless admin must not fall back to a shared key.'
);
expect_true(
    gemini_api_key_policy($keyPolicyUser, 'personal-key', true)['key_source'] === 'personal',
    'A personal key should be accepted for regular users.'
);
expect_true(
    gemini_api_key_policy($keyPolicyAdmin, 'personal-key', true)['key_source'] === 'personal',
    'A personal key should be accepted for admins.'
);
expect_true(
    gemini_api_key_policy($keyPolicySuperAdmin, null, true)['key_source'] === 'shared',
    'A keyless super admin may fall back to the shared key.'
);
expect_true(
    !gemini_api_key_policy($keyPolicySuperAdmin, null, false)['allowed'],
    'A super admin without either key source should be blocked.'
);
expect_true(
    gemini_api_key_policy(null, null, true, true)['key_source'] === 'shared'
        && !gemini_api_key_policy(null, null, false, true)['allowed'],
    'Guest trial remains shared-key-only.'
);

$_SESSION['csrf_token'] = 'test-session-token';
expect_true(validate_csrf_token('test-session-token'), 'A matching session CSRF token should be accepted.');
expect_true(!validate_csrf_token('wrong-token'), 'A mismatched CSRF token should be rejected.');

$updateDb = new SQLite3(':memory:');
$updateDb->enableExceptions(true);
$updateDb->exec(
    'CREATE TABLE generated_curriculum_runs (
        id INTEGER PRIMARY KEY,
        user_title TEXT,
        user_notes TEXT,
        is_finalized INTEGER NOT NULL DEFAULT 0,
        updated_at TEXT
    )'
);
$updateDb->exec("INSERT INTO generated_curriculum_runs (id) VALUES (1)");
draft_management_update_details($updateDb, 1, 'Renamed run', 'Reviewer notes');
$updatedRun = $updateDb->querySingle(
    'SELECT user_title, user_notes, updated_at FROM generated_curriculum_runs WHERE id = 1',
    true
);
$runColumns = [];
$columnResult = $updateDb->query('PRAGMA table_info(generated_curriculum_runs)');
while ($column = $columnResult->fetchArray(SQLITE3_ASSOC)) {
    $runColumns[] = $column['name'];
}
expect_true(
    $updatedRun['user_title'] === 'Renamed run'
        && $updatedRun['user_notes'] === 'Reviewer notes'
        && $updatedRun['updated_at'] !== null,
    'Rename and Notes updates should work without a finalized form value.'
);
expect_true(in_array('is_finalized', $runColumns, true), 'The legacy column must remain in place.');
$updateDb->close();
echo "Draft management helper tests passed." . PHP_EOL;
