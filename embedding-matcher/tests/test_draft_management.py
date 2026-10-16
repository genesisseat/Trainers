import shutil
import sqlite3
import subprocess
import tempfile
import unittest
from pathlib import Path

import curriculum_generator


PROJECT_ROOT = Path(__file__).resolve().parents[1]


class DraftManagementTests(unittest.TestCase):
    def test_additive_migration_preserves_legacy_status_and_review_rows(self):
        with tempfile.TemporaryDirectory() as directory:
            database = Path(directory) / "legacy.db"
            connection = sqlite3.connect(database, timeout=30)
            try:
                connection.execute(
                    """
                    CREATE TABLE generated_curriculum_runs (
                        id INTEGER PRIMARY KEY,
                        program TEXT,
                        status TEXT,
                        is_finalized INTEGER NOT NULL DEFAULT 0,
                        generated_at TEXT DEFAULT CURRENT_TIMESTAMP
                    )
                    """
                )
                connection.execute(
                    """
                    CREATE TABLE generated_curriculum_reviews (
                        id INTEGER PRIMARY KEY,
                        run_id INTEGER,
                        status TEXT,
                        reviewer TEXT,
                        notes TEXT,
                        FOREIGN KEY(run_id) REFERENCES generated_curriculum_runs(id)
                    )
                    """
                )
                connection.execute(
                    "INSERT INTO generated_curriculum_runs (id, program, status) VALUES (1, 'BSIT', 'approved')"
                )
                connection.execute(
                    "INSERT INTO generated_curriculum_reviews (id, run_id, status, notes) VALUES (1, 1, 'approved', 'legacy')"
                )
                curriculum_generator._ensure_draft_management_columns(connection)
                curriculum_generator._ensure_draft_management_columns(connection)
                status, title, generation_mode = connection.execute(
                    "SELECT status, user_title, generation_mode FROM generated_curriculum_runs WHERE id = 1"
                ).fetchone()
                review = connection.execute(
                    "SELECT status, notes FROM generated_curriculum_reviews WHERE id = 1"
                ).fetchone()
                run_columns = {
                    column[1]
                    for column in connection.execute("PRAGMA table_info(generated_curriculum_runs)")
                }
                open_run = connection.execute(
                    "SELECT id, program FROM generated_curriculum_runs WHERE id = 1"
                ).fetchone()
            finally:
                connection.close()

        self.assertEqual((status, title, generation_mode), ("approved", None, None))
        self.assertEqual(review, ("approved", "legacy"))
        self.assertIn("is_finalized", run_columns)
        self.assertEqual(open_run, (1, "BSIT"))

    def test_generation_mode_backfill_requires_explicit_evidence(self):
        with tempfile.TemporaryDirectory() as directory:
            connection = sqlite3.connect(Path(directory) / "legacy_modes.db", timeout=30)
            try:
                connection.executescript(
                    """
                    CREATE TABLE generated_curriculum_runs (
                        id INTEGER PRIMARY KEY,
                        source TEXT,
                        notes TEXT,
                        is_finalized INTEGER NOT NULL DEFAULT 0
                    );
                    CREATE TABLE generated_curriculum_subjects (
                        id INTEGER PRIMARY KEY,
                        run_id INTEGER,
                        rationale TEXT
                    );
                    INSERT INTO generated_curriculum_runs (id, source, notes) VALUES
                        (1, 'generated', ''),
                        (2, 'generated', ''),
                        (3, 'enhanced', '{"fallback":true,"draft_fallback":true}'),
                        (4, 'enhanced', '{"fallback":false,"draft_fallback":true}'),
                        (5, 'enhanced', '{"fallback":true}');
                    INSERT INTO generated_curriculum_subjects (run_id, rationale) VALUES
                            (1, '[offline template fallback] Evidence preserved.'),
                        (2, 'No mode marker.');
                    """
                )
                curriculum_generator._ensure_draft_management_columns(connection)
                curriculum_generator._ensure_draft_management_columns(connection)
                modes = dict(
                    connection.execute(
                        "SELECT id, generation_mode FROM generated_curriculum_runs ORDER BY id"
                    ).fetchall()
                )
            finally:
                connection.close()

        self.assertEqual(modes, {1: "offline", 2: None, 3: "offline", 4: "online", 5: None})

    def test_history_and_run_pages_expose_generation_mode_filters(self):
        history = (PROJECT_ROOT / "draft_history.php").read_text(encoding="utf-8")
        self.assertIn('id="draft-history-source"', history)
        self.assertIn('id="draft-history-mode"', history)
        self.assertIn("item.dataset.historySource !== sourceFilter.value", history)
        self.assertIn("item.dataset.generationMode !== modeFilter.value", history)
        self.assertIn('data-generation-mode=', history)

        for page, filter_id in (
            ("generated_curriculum.php", 'id="draft-mode-filter"'),
            ("enhanced_curriculum_generated.php", 'id="enhancement-mode-filter"'),
        ):
            with self.subTest(page=page):
                source = (PROJECT_ROOT / page).read_text(encoding="utf-8")
                self.assertIn(filter_id, source)
                self.assertIn("dataset.generationMode === selectedMode", source)

    def test_php_update_validation_and_authorization_helpers(self):
        php = shutil.which("php")
        if php is None:
            self.fail("PHP CLI is required to run draft-management helper tests.")
        result = subprocess.run(
            [php, str(PROJECT_ROOT / "tests" / "test_draft_management.php")],
            capture_output=True,
            text=True,
            check=False,
        )
        self.assertEqual(result.returncode, 0, result.stdout + result.stderr)
        self.assertIn("Draft management helper tests passed.", result.stdout)
