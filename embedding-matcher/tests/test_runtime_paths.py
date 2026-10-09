import os
import sqlite3
import tempfile
import unittest
from pathlib import Path
from unittest.mock import patch

import database_setup
from runtime_paths import HF_CACHE_VARIABLES, configure_huggingface_cache, knowledge_base_dir


PROJECT_ROOT = Path(__file__).resolve().parents[1]


class RuntimePathTests(unittest.TestCase):
    def test_default_knowledge_base_is_sibling(self):
        self.assertEqual(
            knowledge_base_dir(PROJECT_ROOT),
            PROJECT_ROOT.parent / "curriculum-generator-kb",
        )

    def test_kb_dir_override_anchors_relative_paths_at_workspace(self):
        with patch.dict(os.environ, {"KB_DIR": "custom-kb"}):
            self.assertEqual(
                knowledge_base_dir(PROJECT_ROOT),
                (PROJECT_ROOT.parent / "custom-kb").resolve(),
            )

    def test_external_huggingface_cache_variables_are_preserved(self):
        cleared = {name: "" for name in HF_CACHE_VARIABLES}
        with tempfile.TemporaryDirectory() as directory:
            for variable in HF_CACHE_VARIABLES:
                external_cache = str(Path(directory) / variable.lower())
                with patch.dict(os.environ, {**cleared, variable: external_cache}, clear=False):
                    self.assertEqual(configure_huggingface_cache(PROJECT_ROOT), Path(external_cache))
                    self.assertEqual(os.environ[variable], external_cache)

    def test_local_cache_is_used_only_when_no_cache_variable_is_set(self):
        cleared = {name: "" for name in HF_CACHE_VARIABLES}
        with tempfile.TemporaryDirectory() as directory, patch.dict(os.environ, cleared, clear=False):
            os.environ.pop("HF_HOME", None)
            temporary_root = Path(directory)
            result = configure_huggingface_cache(temporary_root)
            self.assertEqual(result, temporary_root / "hf_cache")
            self.assertEqual(os.environ["HF_HOME"], str(temporary_root / "hf_cache"))

    def test_new_database_uses_wal(self):
        with tempfile.TemporaryDirectory() as directory:
            database = Path(directory) / "new.db"
            with patch.object(database_setup, "DB_PATH", database):
                connection = database_setup.get_connection()
                try:
                    self.assertEqual(connection.execute("PRAGMA journal_mode").fetchone()[0], "wal")
                finally:
                    connection.close()

    def test_existing_database_journal_mode_is_preserved(self):
        with tempfile.TemporaryDirectory() as directory:
            database = Path(directory) / "existing.db"
            connection = sqlite3.connect(database)
            connection.execute("CREATE TABLE existing_table (id INTEGER)")
            connection.close()
            with patch.object(database_setup, "DB_PATH", database):
                connection = database_setup.get_connection()
                try:
                    self.assertEqual(connection.execute("PRAGMA journal_mode").fetchone()[0], "delete")
                finally:
                    connection.close()

    def test_php_runtime_helpers(self):
        import shutil
        import subprocess

        php = shutil.which("php")
        if php is None:
            self.fail("PHP CLI is required to run runtime helper tests.")
        result = subprocess.run(
            [php, str(PROJECT_ROOT / "tests" / "test_runtime_helpers.php")],
            capture_output=True,
            text=True,
            check=False,
        )
        self.assertEqual(result.returncode, 0, result.stdout + result.stderr)
        self.assertIn("Runtime helper tests passed.", result.stdout)


if __name__ == "__main__":
    unittest.main()
