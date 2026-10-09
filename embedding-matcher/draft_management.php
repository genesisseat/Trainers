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
        'updated_at' => 'TEXT NULL',
        'generation_mode' => 'TEXT NULL',
    ] as $name => $definition) {
        if (!in_array($name, $columns, true)) {
            $db->exec("ALTER TABLE generated_curriculum_runs ADD COLUMN {$name} {$definition}");
            if ($name === 'generation_mode') {
                draft_management_backfill_generation_modes($db, $columns);
            }
        }
    }
    run_access_ensure_indexes($db);
}

function draft_management_backfill_generation_modes(SQLite3 $db, array $runColumns): void
{
    $subjectTable = $db->querySingle(
        "SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'generated_curriculum_subjects'"
    );
    if ($subjectTable && in_array('source', $runColumns, true)) {
        $db->exec(
            "UPDATE generated_curriculum_runs
             SET generation_mode = 'offline'
             WHERE generation_mode IS NULL
               AND COALESCE(source, 'generated') <> 'enhanced'
               AND EXISTS (
                   SELECT 1
                   FROM generated_curriculum_subjects s
                   WHERE s.run_id = generated_curriculum_runs.id
                     AND instr(COALESCE(s.rationale, ''), '[offline template fallback]') = 1
               )"
        );
    }

    if (!in_array('source', $runColumns, true)) {
        return;
    }
    $result = $db->query(
        "SELECT id, notes
         FROM generated_curriculum_runs
         WHERE generation_mode IS NULL AND source = 'enhanced'"
    );
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $report = json_decode((string)($row['notes'] ?? ''), true);
        if (!is_array($report)) {
            continue;
        }
        $reviewFallback = $report['fallback'] ?? null;
        $curriculumFallback = $report['draft_fallback'] ?? null;
        if ($reviewFallback === false || $curriculumFallback === false) {
            $mode = 'online';
        } elseif ($reviewFallback === true && $curriculumFallback === true) {
            $mode = 'offline';
        } else {
            continue;
        }
        $update = $db->prepare(
            'UPDATE generated_curriculum_runs
             SET generation_mode = :mode
             WHERE id = :id AND generation_mode IS NULL'
        );
        $update->bindValue(':mode', $mode, SQLITE3_TEXT);
        $update->bindValue(':id', (int)$row['id'], SQLITE3_INTEGER);
        $update->execute();
    }
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

function draft_management_generation_mode_label(mixed $generationMode): string
{
    return match ($generationMode) {
        'online' => 'Online Template',
        'offline' => 'Offline template',
        default => 'Not recorded',
    };
}

function draft_management_enhancement_generation_mode(array $report): ?string
{
    $reviewFallback = $report['fallback'] ?? null;
    $curriculumFallback = $report['draft_fallback'] ?? null;
    if ($reviewFallback === false || $curriculumFallback === false) {
        return 'online';
    }
    if ($reviewFallback === true && $curriculumFallback === true) {
        return 'offline';
    }
    return null;
}

function draft_management_update_details(SQLite3 $db, int $runId, ?string $title, string $notes): void
{
    $statement = $db->prepare(
        'UPDATE generated_curriculum_runs
         SET user_title = :title, user_notes = :notes, updated_at = CURRENT_TIMESTAMP
         WHERE id = :id'
    );
    if ($statement === false) {
        throw new RuntimeException('Unable to prepare the draft details update.');
    }
    $statement->bindValue(':title', $title, $title === null ? SQLITE3_NULL : SQLITE3_TEXT);
    $statement->bindValue(':notes', $notes, SQLITE3_TEXT);
    $statement->bindValue(':id', $runId, SQLITE3_INTEGER);
    if ($statement->execute() === false) {
        throw new RuntimeException('Unable to update draft details.');
    }
}
