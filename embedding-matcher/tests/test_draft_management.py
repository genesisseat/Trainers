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
                status, finalized, title = connection.execute(
                    "SELECT status, is_finalized, user_title FROM generated_curriculum_runs WHERE id = 1"
                ).fetchone()
                review = connection.execute(
                    "SELECT status, notes FROM generated_curriculum_reviews WHERE id = 1"
                ).fetchone()
            finally:
                connection.close()

        self.assertEqual((status, finalized, title), ("approved", 0, None))
        self.assertEqual(review, ("approved", "legacy"))

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
