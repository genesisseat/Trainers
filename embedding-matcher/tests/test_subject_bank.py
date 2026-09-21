import unittest

from curriculum_generator_foundation import (
    build_subject_bank,
    cluster_subject_variants,
    normalize_subject_name,
)


class SubjectBankTests(unittest.TestCase):
    def test_normalize_subject_name(self):
        self.assertEqual(normalize_subject_name("Data Structures & Algorithms"), "data structures and algorithms")
        self.assertEqual(normalize_subject_name("  Operating Systems  "), "operating systems")

    def test_build_subject_bank_groups_equivalent_subjects(self):
        rows = [
            {
                "university": "NU Lipa",
                "program": "BSIT",
                "course": "Data Structures and Algorithms",
                "classification": "Core",
                "year": "1",
                "term": "1",
                "units": "3",
            },
            {
                "university": "UST",
                "program": "BSIT",
                "course": "Data Structures & Algorithms",
                "classification": "Core",
                "year": "1",
                "term": "1",
                "units": "3",
            },
            {
                "university": "DLSU",
                "program": "BSIT",
                "course": "Operating Systems",
                "classification": "Core",
                "year": "2",
                "term": "1",
                "units": "3",
            },
        ]

        bank = build_subject_bank(rows)
        self.assertIn("bsit", {item["program"].lower() for item in bank})
        self.assertEqual(len(bank), 2)
        self.assertTrue(any(item["canonical_subject"].lower() == "data structures and algorithms" for item in bank))
        self.assertTrue(any(item["canonical_subject"].lower() == "operating systems" for item in bank))

    def test_cluster_subject_variants_uses_embedding_similarity(self):
        rows = [
            {
                "university": "NU Lipa",
                "program": "BSIT",
                "course": "Data Structures and Algorithms",
                "classification": "Core",
                "year": "1",
                "term": "1",
                "units": "3",
            },
            {
                "university": "UST",
                "program": "BSIT",
                "course": "Data Structures & Algorithm",
                "classification": "Core",
                "year": "1",
                "term": "1",
                "units": "3",
            },
            {
                "university": "DLSU",
                "program": "BSIT",
                "course": "Computer Programming 1",
                "classification": "Core",
                "year": "1",
                "term": "1",
                "units": "3",
            },
        ]

        clusters = cluster_subject_variants(rows)
        self.assertGreaterEqual(len(clusters), 2)
        self.assertTrue(any(len(cluster["subject_variants"]) >= 2 for cluster in clusters))


if __name__ == "__main__":
    unittest.main()
