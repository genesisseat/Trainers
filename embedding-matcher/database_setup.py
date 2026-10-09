#!/usr/bin/env python3
"""Create and populate a local SQLite database for the curriculum matching project.

This script builds a single-file database with:
- courses
- skills
- course_skill_matches
- skill_coverage
- model_runs

It loads the existing curriculum CSV and industry skills markdown, then imports the
match outputs if they already exist.
"""

from __future__ import annotations

import csv
import json
import sqlite3
from datetime import datetime
from pathlib import Path

import pandas as pd

from match_courses_to_skills import build_course_rows, parse_skill_rows
from runtime_paths import knowledge_base_dir

ROOT = Path(__file__).resolve().parent
DB_PATH = ROOT / "curriculum_matching.db"
KB_DIR = knowledge_base_dir(ROOT)
COURSES_CSV = KB_DIR / "data" / "curriculum_dataset_with_ids.csv"
SKILLS_MD = KB_DIR / "03_industry_skills_data.md"
MATCH_CSV = ROOT / "course_to_skill_matches.csv"
COVERAGE_CSV = ROOT / "skill_coverage.csv"


def _backfill_generation_modes(conn: sqlite3.Connection) -> None:
    columns = {row[1] for row in conn.execute("PRAGMA table_info(generated_curriculum_runs)")}
    has_subjects = conn.execute(
        "SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'generated_curriculum_subjects'"
    ).fetchone()
    if has_subjects and "source" in columns:
        conn.execute(
            """
            UPDATE generated_curriculum_runs
            SET generation_mode = 'offline'
            WHERE generation_mode IS NULL
              AND COALESCE(source, 'generated') <> 'enhanced'
              AND EXISTS (
                  SELECT 1 FROM generated_curriculum_subjects s
                  WHERE s.run_id = generated_curriculum_runs.id
                    AND instr(COALESCE(s.rationale, ''), '[offline template fallback]') = 1
              )
            """
        )
    if "source" not in columns:
        return
    for run_id, notes in conn.execute(
        """
        SELECT id, notes FROM generated_curriculum_runs
        WHERE generation_mode IS NULL AND source = 'enhanced'
        """
    ).fetchall():
        try:
            report = json.loads(notes or "")
        except (TypeError, json.JSONDecodeError):
            continue
        if not isinstance(report, dict):
            continue
        review_fallback = report.get("fallback")
        curriculum_fallback = report.get("draft_fallback")
        if review_fallback is False or curriculum_fallback is False:
            mode = "online"
        elif review_fallback is True and curriculum_fallback is True:
            mode = "offline"
        else:
            continue
        conn.execute(
            "UPDATE generated_curriculum_runs SET generation_mode = ? WHERE id = ? AND generation_mode IS NULL",
            (mode, run_id),
        )


def get_connection() -> sqlite3.Connection:
    is_new_database = not DB_PATH.exists()
    conn = sqlite3.connect(DB_PATH, timeout=30)
    if is_new_database:
        conn.execute("PRAGMA journal_mode=WAL")
    conn.row_factory = sqlite3.Row
    return conn


def create_tables(conn: sqlite3.Connection) -> None:
    conn.execute(
        """
        CREATE TABLE IF NOT EXISTS courses (
            id TEXT PRIMARY KEY,
            university TEXT,
            program TEXT,
            curriculum_year TEXT,
            course_name TEXT,
            classification TEXT,
            is_elective_or_track TEXT,
            role TEXT,
            doc_section TEXT,
            year TEXT,
            term TEXT,
            course_units TEXT,
            data_source TEXT
        )
        """
    )

    conn.execute(
        """
        CREATE TABLE IF NOT EXISTS skills (
            id TEXT PRIMARY KEY,
            name TEXT,
            skill_type TEXT,
            notes TEXT,
            source_file TEXT
        )
        """
    )

    conn.execute(
        """
        CREATE TABLE IF NOT EXISTS model_runs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            model_name TEXT,
            top_k INTEGER,
            run_at TEXT DEFAULT CURRENT_TIMESTAMP,
            status TEXT
        )
        """
    )

    conn.execute(
        """
        CREATE TABLE IF NOT EXISTS course_skill_matches (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            course_id TEXT,
            skill_id TEXT,
            skill_name TEXT,
            skill_type TEXT,
            score REAL,
            rank INTEGER,
            model_name TEXT,
            run_at TEXT,
            FOREIGN KEY(course_id) REFERENCES courses(id),
            FOREIGN KEY(skill_id) REFERENCES skills(id)
        )
        """
    )

    conn.execute(
        """
        CREATE TABLE IF NOT EXISTS skill_coverage (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            skill_id TEXT,
            skill_name TEXT,
            skill_type TEXT,
            best_course_id TEXT,
            best_course_title TEXT,
            score REAL,
            model_name TEXT,
            run_at TEXT,
            FOREIGN KEY(skill_id) REFERENCES skills(id),
            FOREIGN KEY(best_course_id) REFERENCES courses(id)
        )
        """
    )

    conn.execute(
        """
        CREATE TABLE IF NOT EXISTS generated_curriculum_runs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            program TEXT,
            prompt TEXT,
            model_name TEXT,
            status TEXT,
            generated_at TEXT DEFAULT CURRENT_TIMESTAMP,
            notes TEXT,
            source TEXT NOT NULL DEFAULT 'generated',
            created_by_user_id INTEGER,
            created_by_username TEXT,
            user_title TEXT,
            user_notes TEXT,
            updated_at TEXT,
            generation_mode TEXT
        )
        """
    )
    run_columns = {row[1] for row in conn.execute("PRAGMA table_info(generated_curriculum_runs)")}
    if "source" not in run_columns:
        conn.execute("ALTER TABLE generated_curriculum_runs ADD COLUMN source TEXT NOT NULL DEFAULT 'generated'")
        conn.execute("UPDATE generated_curriculum_runs SET source = 'generated' WHERE source IS NULL OR source = ''")
    if "created_by_user_id" not in run_columns:
        conn.execute("ALTER TABLE generated_curriculum_runs ADD COLUMN created_by_user_id INTEGER")
    if "created_by_username" not in run_columns:
        conn.execute("ALTER TABLE generated_curriculum_runs ADD COLUMN created_by_username TEXT")
    for column, definition in {
        "user_title": "TEXT",
        "user_notes": "TEXT",
        "updated_at": "TEXT",
        "generation_mode": "TEXT",
    }.items():
        if column not in run_columns:
            conn.execute(f"ALTER TABLE generated_curriculum_runs ADD COLUMN {column} {definition}")
            if column == "generation_mode":
                _backfill_generation_modes(conn)

    conn.execute(
        """
        CREATE TABLE IF NOT EXISTS generated_curriculum_subjects (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            run_id INTEGER,
            program TEXT,
            year TEXT,
            term TEXT,
            subject_code TEXT,
            subject_title TEXT,
            units TEXT,
            prerequisites TEXT,
            topics TEXT,
            rationale TEXT,
            source_colleges TEXT,
            description TEXT,
            mapped_industry_skills TEXT,
            source TEXT,
            FOREIGN KEY(run_id) REFERENCES generated_curriculum_runs(id)
        )
        """
    )
    subject_columns = {
        row[1]
        for row in conn.execute("PRAGMA table_info(generated_curriculum_subjects)")
    }
    if "source" not in subject_columns:
        conn.execute("ALTER TABLE generated_curriculum_subjects ADD COLUMN source TEXT")

    conn.execute(
        """
        CREATE TABLE IF NOT EXISTS generated_curriculum_chat (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            run_id INTEGER,
            role TEXT,
            message TEXT,
            sender_user_id INTEGER,
            sender_username TEXT,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(run_id) REFERENCES generated_curriculum_runs(id)
        )
        """
    )
    chat_columns = {row[1] for row in conn.execute("PRAGMA table_info(generated_curriculum_chat)")}
    if "sender_user_id" not in chat_columns:
        conn.execute("ALTER TABLE generated_curriculum_chat ADD COLUMN sender_user_id INTEGER")
    if "sender_username" not in chat_columns:
        conn.execute("ALTER TABLE generated_curriculum_chat ADD COLUMN sender_username TEXT")
    conn.execute(
        "CREATE INDEX IF NOT EXISTS idx_generated_curriculum_runs_owner_id "
        "ON generated_curriculum_runs(created_by_user_id)"
    )
    conn.execute(
        "CREATE INDEX IF NOT EXISTS idx_generated_curriculum_chat_run_id "
        "ON generated_curriculum_chat(run_id)"
    )

    conn.commit()


def import_courses(conn: sqlite3.Connection) -> int:
    frame = pd.read_csv(COURSES_CSV)
    inserted = 0
    for _, row in frame.iterrows():
        course_id = str(row.get("id", "")).strip()
        if not course_id:
            continue
        values = (
            course_id,
            str(row.get("university", "")).strip(),
            str(row.get("program", "")).strip(),
            str(row.get("curriculum_year", "")).strip(),
            str(row.get("course", "")).strip(),
            str(row.get("classification", "")).strip(),
            str(row.get("is_elective_or_track", "")).strip(),
            str(row.get("role", "")).strip(),
            str(row.get("doc_section", "")).strip(),
            str(row.get("year", "")).strip(),
            str(row.get("term", "")).strip(),
            str(row.get("units", "")).strip(),
            "curriculum_generator_kb",
        )
        conn.execute(
            """
            INSERT OR REPLACE INTO courses (
                id, university, program, curriculum_year, course_name, classification,
                is_elective_or_track, role, doc_section, year, term, course_units, data_source
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            """,
            values,
        )
        inserted += 1
    conn.commit()
    return inserted


def import_skills(conn: sqlite3.Connection) -> int:
    rows = parse_skill_rows(SKILLS_MD)
    inserted = 0
    for row in rows:
        skill_id = str(row["id"]).strip()
        if not skill_id:
            continue
        conn.execute(
            """
            INSERT OR REPLACE INTO skills (id, name, skill_type, notes, source_file)
            VALUES (?, ?, ?, ?, ?)
            """,
            (
                skill_id,
                str(row.get("name", "")).strip(),
                str(row.get("type", "")).strip(),
                str(row.get("notes", "")).strip(),
                str(SKILLS_MD),
            ),
        )
        inserted += 1
    conn.commit()
    return inserted


def import_model_run(conn: sqlite3.Connection, model_name: str, top_k: int, status: str = "completed") -> int:
    run_at = datetime.utcnow().isoformat(timespec="seconds")
    cursor = conn.execute(
        """
        INSERT INTO model_runs (model_name, top_k, run_at, status)
        VALUES (?, ?, ?, ?)
        """,
        (model_name, top_k, run_at, status),
    )
    conn.commit()
    return int(cursor.lastrowid)


def import_match_csv(conn: sqlite3.Connection, csv_path: Path, model_name: str, run_id: int) -> int:
    if not csv_path.exists():
        return 0

    inserted = 0
    with csv_path.open("r", encoding="utf-8", newline="") as handle:
        reader = csv.DictReader(handle)
        for row in reader:
            course_id = str(row.get("course_id", "")).strip()
            skill_id = str(row.get("skill_id", "")).strip()
            if not course_id or not skill_id:
                continue
            conn.execute(
                """
                INSERT INTO course_skill_matches (
                    course_id, skill_id, skill_name, skill_type, score, rank, model_name, run_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                """,
                (
                    course_id,
                    skill_id,
                    str(row.get("skill_name", "")).strip(),
                    str(row.get("skill_type", "")).strip(),
                    float(row.get("score", 0) or 0),
                    int(row.get("rank", 0) or 0),
                    model_name,
                    datetime.utcnow().isoformat(timespec="seconds"),
                ),
            )
            inserted += 1
    conn.commit()
    return inserted


def import_coverage_csv(conn: sqlite3.Connection, csv_path: Path, model_name: str, run_id: int) -> int:
    if not csv_path.exists():
        return 0

    inserted = 0
    with csv_path.open("r", encoding="utf-8", newline="") as handle:
        reader = csv.DictReader(handle)
        for row in reader:
            skill_id = str(row.get("skill_id", "")).strip()
            if not skill_id:
                continue
            conn.execute(
                """
                INSERT INTO skill_coverage (
                    skill_id, skill_name, skill_type, best_course_id, best_course_title, score, model_name, run_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                """,
                (
                    skill_id,
                    str(row.get("skill_name", "")).strip(),
                    str(row.get("skill_type", "")).strip(),
                    str(row.get("best_course_id", "")).strip(),
                    str(row.get("best_course_title", "")).strip(),
                    float(row.get("score", 0) or 0),
                    model_name,
                    datetime.utcnow().isoformat(timespec="seconds"),
                ),
            )
            inserted += 1
    conn.commit()
    return inserted


def add_generated_curriculum(
    conn: sqlite3.Connection,
    program: str,
    prompt: str,
    model_name: str,
    subjects: list[dict],
    status: str = "draft",
    notes: str = "",
    created_by_user_id: int | None = None,
    created_by_username: str | None = None,
) -> int:
    run_id = conn.execute(
        """
        INSERT INTO generated_curriculum_runs (
            program, prompt, model_name, status, notes, source, created_by_user_id, created_by_username
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        """,
        (program, prompt, model_name, status, notes, "generated", created_by_user_id, created_by_username),
    ).lastrowid

    for subject in subjects:
        conn.execute(
            """
            INSERT INTO generated_curriculum_subjects (
                run_id, program, year, term, subject_code, subject_title, units,
                prerequisites, topics, rationale, source_colleges, source
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            """,
            (
                run_id,
                str(subject.get("program") or program).strip(),
                str(subject.get("year") or "").strip(),
                str(subject.get("term") or "").strip(),
                str(subject.get("subject_code") or "").strip(),
                str(subject.get("subject_title") or "").strip(),
                str(subject.get("units") or "").strip(),
                str(subject.get("prerequisites") or "").strip(),
                str(subject.get("topics") or "").strip(),
                str(subject.get("rationale") or "").strip(),
                str(subject.get("source_colleges") or "").strip(),
                subject.get("source"),
            ),
        )

    conn.commit()
    return int(run_id)


def main() -> None:
    model_name = "BAAI/bge-small-en-v1.5"
    top_k = 5

    conn = get_connection()
    create_tables(conn)
    course_count = import_courses(conn)
    skill_count = import_skills(conn)
    run_id = import_model_run(conn, model_name, top_k)
    match_count = import_match_csv(conn, MATCH_CSV, model_name, run_id)
    coverage_count = import_coverage_csv(conn, COVERAGE_CSV, model_name, run_id)

    print(f"Database created at: {DB_PATH}")
    print(f"Courses imported: {course_count}")
    print(f"Skills imported: {skill_count}")
    print(f"Matches imported: {match_count}")
    print(f"Coverage rows imported: {coverage_count}")
    print("SQLite database is ready for work use.")
    print("Generated curriculum tables available: generated_curriculum_runs, generated_curriculum_subjects")

    conn.close()


if __name__ == "__main__":
    main()
