import json
import shutil
import sqlite3
import subprocess
import tempfile
import unittest
from pathlib import Path

from curriculum_generator import save_enhancement_report
from recommended_tools import recommend_tools

PROJECT_ROOT = Path(__file__).resolve().parents[1]


class RecommendedToolsTests(unittest.TestCase):
    def test_real_curriculum_titles_have_expected_recommendations(self):
        cases = {
            "Introduction to Computing": "Python or Java",
            "Fundamentals of Programming": "Python or Java",
            "Networking 1": "Cisco Packet Tracer",
            "Information Management": "PostgreSQL or MySQL",
            "Database Management System": "PostgreSQL or MySQL",
            "Systems Analysis and Design": "PlantUML (UML diagramming)",
            "Information Assurance and Security": "OWASP Juice Shop",
            "Discrete Math": "Python",
            "Internship in Computing": "Placement-approved IDE",
            "Capstone Project 1": "Git and GitHub",
            "Code of Ethics for IT Professionals": "No title-based software recommendation",
            "Technopreneurship": "No title-based software recommendation",
            "Operating Systems": "Ubuntu/Linux shell",
            "Human Computer Interaction": "Figma or paper prototypes",
            "Multimedia Systems": "GIMP",
            "Computer Architecture": "Logisim-evolution",
            "Introduction to Artificial Intelligence": "Jupyter Notebook",
            "Web Application Development": "Browser developer tools",
        }
        for title, expected_tool in cases.items():
            with self.subTest(title=title):
                recommendations = recommend_tools(title)
                self.assertIn(expected_tool, {item["tool"] for item in recommendations})
                self.assertTrue(all(item["reason"].strip() for item in recommendations))
                for item in recommendations:
                    for documentation in item["documentation"]:
                        self.assertIn(documentation["url"].split(":", 1)[0], {"https", "http"})
                        self.assertTrue(documentation["label"].strip())

    def test_normalization_and_no_match_default(self):
        self.assertEqual(
            recommend_tools(" information-management! "),
            recommend_tools("Information Management"),
        )
        self.assertEqual(
            recommend_tools("Code of Ethics for IT Professionals")[0]["tool"],
            "No title-based software recommendation",
        )
        default = recommend_tools("A course with no technical title")
        self.assertEqual(len(default), 1)
        self.assertIn("syllabus", default[0]["reason"])
        self.assertEqual(default[0]["documentation"], [])

    def test_new_enhanced_run_persists_completed_course_tool_snapshot(self):
        with tempfile.TemporaryDirectory() as directory:
            database = Path(directory) / "tools.db"
            run_id = save_enhancement_report(
                {"subjects": [], "recommendations": []},
                [{"subject_title": "Information Management", "year": "1", "term": "1"}],
                [],
                program="BSIT",
                model_name="test-model",
                db_path=database,
            )
            connection = sqlite3.connect(database)
            try:
                report_json = connection.execute(
                    "SELECT notes FROM generated_curriculum_runs WHERE id = ?",
                    (run_id,),
                ).fetchone()[0]
            finally:
                connection.close()

        saved_report = json.loads(report_json)
        saved_tools = saved_report["completed_course_tools"]["Information Management"]
        self.assertIn("PostgreSQL or MySQL", {item["tool"] for item in saved_tools})
        self.assertTrue(all(item["reason"] for item in saved_tools))

    def test_course_rendering_and_pdf_export_include_recommendations(self):
        generated_page = (PROJECT_ROOT / "generated_curriculum.php").read_text(encoding="utf-8")
        enhanced_page = (PROJECT_ROOT / "enhanced_curriculum_generated.php").read_text(encoding="utf-8")
        self.assertIn("Recommended tools/apps:", generated_page)
        self.assertIn("Recommended tools/apps:", enhanced_page)
        self.assertIn("completed_course_tools", enhanced_page)

        node = shutil.which("node")
        if node is None:
            self.skipTest("Node.js is not installed; the browser PDF smoke test could not run.")
        result = subprocess.run(
            [node, str(PROJECT_ROOT / "tests" / "test_pdf_recommended_tools.js")],
            capture_output=True,
            text=True,
            check=False,
        )
        self.assertEqual(result.returncode, 0, result.stdout + result.stderr)
        self.assertIn("PDF recommended-tools export smoke test passed.", result.stdout)


if __name__ == "__main__":
    unittest.main()
