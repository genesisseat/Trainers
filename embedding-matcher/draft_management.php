<?php
declare(strict_types=1);

function draft_management_ensure_columns(SQLite3 $db): void
{
    $columns = [];
    $result = $db->query('PRAGMA table_info(generated_curriculum_runs)');
    while ($column = $result->fetchArray(SQLITE3_ASSOC)) {
        $columns[] = (string)$column['name'];
    }

    foreach ([
        'created_by_user_id' => 'INTEGER NULL',
        'created_by_username' => 'TEXT NULL',
        'user_title' => 'TEXT NULL',
        'user_notes' => 'TEXT NULL',
        'is_finalized' => 'INTEGER NOT NULL DEFAULT 0',
        'updated_at' => 'TEXT NULL',
    ] as $name => $definition) {
        if (!in_array($name, $columns, true)) {
            $db->exec("ALTER TABLE generated_curriculum_runs ADD COLUMN {$name} {$definition}");
        }
    }
    run_access_ensure_indexes($db);
}

function draft_management_clean_text(mixed $value, int $maxLength): ?string
{
    if (!is_string($value)) {
        return null;
    }

    $cleaned = preg_replace('/\p{Cc}/u', '', $value);
    if (!is_string($cleaned)) {
        return null;
    }

    $length = iconv_strlen($cleaned, 'UTF-8');
    if ($length === false || $length > $maxLength) {
        return null;
    }

    return trim($cleaned);
}

function draft_management_can_update(?array $user, ?array $run): bool
{
    return run_access_can_update($user, $run);
}

function draft_management_status_label(mixed $isFinalized): string
{
    return in_array($isFinalized, [1, '1', true], true) ? 'Finalized' : 'Draft';
}

function draft_management_finalized_value(mixed $value): ?int
{
    return $value === '0' ? 0 : ($value === '1' ? 1 : null);
}
