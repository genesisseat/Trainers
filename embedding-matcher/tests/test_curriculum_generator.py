import sqlite3
import unittest
from pathlib import Path

from curriculum_generator import (
    generate_curriculum_draft,
    generate_program_curriculum,
    save_generated_curriculum,
    save_review_status,
)


class CurriculumGeneratorTests(unittest.TestCase):
    def setUp(self):
        self.subject_bank = [
            {
                "program": "BSIT",
                "program_key": "bsit",
                "canonical_subject": "programming fundamentals",
                "display_name": "Programming Fundamentals",
                "subject_variants": ["Programming Fundamentals", "Intro to Programming"],
                "source_colleges": ["NU Lipa", "UST"],
                "classification": "Core",
                "year_terms": ["Y1T1"],
                "units": ["3"],
            },
            {
                "program": "BSIT",
                "program_key": "bsit",
                "canonical_subject": "data structures and algorithms",
                "display_name": "Data Structures and Algorithms",
                "subject_variants": ["Data Structures and Algorithms"],
                "source_colleges": ["NU Lipa", "DLSU"],
                "classification": "Core",
                "year_terms": ["Y1T2", "Y2T1"],
                "units": ["3"],
            },
            {
                "program": "BSIT",
                "program_key": "bsit",
                "canonical_subject": "computer networks",
                "display_name": "Computer Networks",
                "subject_variants": ["Computer Networks"],
                "source_colleges": ["TIP", "UP"],
                "classification": "Core",
                "year_terms": ["Y3T1"],
                "units": ["3"],
            },
        ]

    def test_generate_curriculum_draft_returns_structured_subjects(self):
        draft = generate_curriculum_draft(
            program="BSIT",
            prompt="Generate a BSIT curriculum for software development and networking skills.",
            subject_bank=self.subject_bank,
        )

        self.assertGreater(len(draft), 0)
        first = draft[0]
        self.assertIn("program", first)
        self.assertIn("year", first)
        self.assertIn("term", first)
        self.assertIn("subject_title", first)
        self.assertIn("topics", first)
        self.assertIn("rationale", first)
        self.assertIn("source_colleges", first)

    def test_save_generated_curriculum_persists_to_sqlite(self):
        draft = generate_curriculum_draft(program="BSIT", prompt="Generate a BSIT curriculum.", subject_bank=self.subject_bank)
        db_path = Path("W:/Trainers/embedding-matcher/test_generated_curriculum.db")
        inserted = save_generated_curriculum(draft, program="BSIT", model_name="retrieval-draft", db_path=db_path, prompt="Generate a BSIT curriculum.")

        self.assertTrue(inserted > 0)
        self.assertTrue(db_path.exists())

        with sqlite3.connect(db_path) as conn:
            table_count = conn.execute(
                "SELECT COUNT(*) FROM generated_curriculum_subjects"
            ).fetchone()[0]
            self.assertGreater(table_count, 0)

    def test_generate_program_curriculum_adds_retrieval_evidence(self):
        skill_coverage = [
            {"skill_id": "IS-001", "skill_name": "Python Programming", "score": 0.61},
            {"skill_id": "IS-002", "skill_name": "Computer Networking", "score": 0.52},
            {"skill_id": "IS-003", "skill_name": "Database Design", "score": 0.66},
        ]

        draft = generate_program_curriculum(
            program="BSIT",
            prompt="Generate a BSIT curriculum focused on software development and networking.",
            subject_bank=self.subject_bank,
            skill_coverage=skill_coverage,
        )

        self.assertGreater(len(draft), 0)
        self.assertIn("skill_evidence", draft[0])
        self.assertTrue(any(item["skill_name"] == "Computer Networking" for item in draft[0]["skill_evidence"]))

    def test_save_review_status_tracks_approval(self):
        review = save_review_status(
            db_path="W:/Trainers/embedding-matcher/test_generated_curriculum.db",
            run_id=1,
            status="approved",
            reviewer="admin",
            notes="Good draft for review.",
        )

        self.assertEqual(review["status"], "approved")
        self.assertEqual(review["reviewer"], "admin")
        self.assertIn("Good draft", review["notes"])


if __name__ == "__main__":
    unittest.main()
