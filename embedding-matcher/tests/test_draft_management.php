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
expect_true(draft_management_finalized_value('0') === 0, 'False form value should be accepted.');
expect_true(draft_management_finalized_value('1') === 1, 'True form value should be accepted.');
expect_true(draft_management_finalized_value('true') === null, 'Non-boolean form value should be rejected.');
expect_true(draft_management_finalized_value(1) === null, 'Non-string form value should be rejected.');
expect_true(draft_management_status_label('approved') === 'Draft', 'Legacy statuses should render as Draft.');
expect_true(draft_management_status_label(0) === 'Draft', 'Unfinalized runs should render as Draft.');
expect_true(draft_management_status_label(1) === 'Finalized', 'Finalized runs should render as Finalized.');
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
echo "Draft management helper tests passed." . PHP_EOL;
