import sqlite3
import json
import os
import tempfile
from contextlib import closing
from types import SimpleNamespace
import unittest
from pathlib import Path
from unittest.mock import patch

import curriculum_generator
import user_operations
from curriculum_generator import (
    _fallback_enhanced_curriculum,
    _validate_enhanced_curriculum,
    generate_curriculum_draft,
    generate_program_curriculum,
    save_enhancement_report,
    save_generated_curriculum,
)


class CurriculumGeneratorTests(unittest.TestCase):
    def setUp(self):
        self._test_directory = tempfile.TemporaryDirectory()
        self.addCleanup(self._test_directory.cleanup)
        self.test_db_path = Path(self._test_directory.name) / "test_generated_curriculum.db"
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

    def test_gemini_response_parser_accepts_selected_year_only(self):
        subjects = [
            {
                "program": "BSIT",
                "year": "3",
                "term": term,
                "subject_code": f"BSIT-3{term}-01",
                "subject_title": f"Network Security {term}",
                "description": "Security lab work.",
                "units": "3",
                "prerequisites": "Networking Fundamentals",
                "topics": ["Threat modeling", "Packet analysis", "Secure configuration"],
                "rationale": "Builds applied security skills.",
                "source_colleges": [],
                "mapped_industry_skills": [],
            }
            for term in ("1", "2")
        ]
        payload = {"candidates": [{"content": {"parts": [{"text": json.dumps(subjects)}]}}]}

        parsed = curriculum_generator._parse_gemini_response(payload, expected_years=["3"])

        self.assertEqual({subject["year"] for subject in parsed}, {"3"})
        with self.assertRaises(curriculum_generator.GeminiGenerationError):
            curriculum_generator._parse_gemini_response(payload, expected_years=["1", "3"])

    def test_selected_year_enhancement_fallback_excludes_other_years(self):
        submitted = [{"subject_title": "Network Security", "description": "Existing course", "year": "3"}]
        report = {
            "subjects": [
                {
                    "subject_title": "Network Security",
                    "year": "3",
                    "recommended_year": "3",
                    "status": "keep",
                    "recommended_edit": "Add guided packet-analysis labs.",
                }
            ],
            "recommendations": [],
        }
        course_rows = [
            {"id": "BSIT-Y1", "university": "Adamson University", "program": "BSIT", "course": "Programming Fundamentals", "classification": "Core", "year": "1", "term": "1"},
            {"id": "BSIT-Y3", "university": "Adamson University", "program": "BSIT", "course": "Applied Network Security", "classification": "Core", "year": "3", "term": "1"},
        ]

        with patch("curriculum_generator._request_gemini_prompt", side_effect=RuntimeError("offline")):
            completed = curriculum_generator.generate_enhanced_curriculum(
                program="BSIT",
                specialization="",
                prompt="Enhance Year 3 only.",
                user_subjects=submitted,
                enhancement_report=report,
                subject_bank=[],
                course_rows=course_rows,
                selected_years=["3"],
            )

        self.assertTrue(completed)
        self.assertEqual({subject["year"] for subject in completed}, {"3"})
        self.assertTrue(any(subject["subject_title"] == "Network Security" for subject in completed))
        self.assertFalse(any(subject["subject_title"] == "Programming Fundamentals" for subject in completed))
        self.assertIn("Network Security", report["completed_course_tools"])

    def test_practical_guidance_adds_programming_tools_and_sources(self):
        guidance = curriculum_generator._practical_guidance(
            "Fundamentals of Programming",
            "1",
            "BSIT",
            self.subject_bank,
            [{"skill_name": "Programming"}],
        )

        self.assertIn("Python or Java", guidance["tools_and_apps"])
        self.assertEqual(
            {item["tool"] for item in guidance["tools_and_apps_reasons"]},
            set(guidance["tools_and_apps"]),
        )
        self.assertTrue(all(item["reason"].strip() for item in guidance["tools_and_apps_reasons"]))
        self.assertIn("Year 1", guidance["instructional_reason"])
        self.assertTrue(any("curriculum_dataset_with_ids.csv" in source for source in guidance["sources"]))
        self.assertTrue(any("docs.python.org" in source for source in guidance["sources"]))

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

    def test_complete_offline_curriculum_fills_four_years_and_two_terms(self):
        subject_bank = [
            {
                "program": "BSCS",
                "program_key": "bscs",
                "canonical_subject": f"course {year} term {term} number {index}",
                "display_name": f"Course {year}-{term}-{index}",
                "source_colleges": ["Test University"],
                "classification": "Core",
                "year_terms": [f"Y{year}T{term}"],
                "units": ["3"],
            }
            for year in range(1, 5)
            for term in range(1, 3)
            for index in range(1, 3)
        ]

        draft = generate_curriculum_draft(
            program="BSCS",
            prompt="Build a complete curriculum.",
            subject_bank=subject_bank,
            complete_roadmap=True,
        )

        slots = [(subject["year"], subject["term"]) for subject in draft]
        self.assertEqual(len(draft), 16)
        self.assertEqual(set(slots), {(str(year), str(term)) for year in range(1, 5) for term in range(1, 3)})
        self.assertTrue(all(slots.count(slot) == 2 for slot in set(slots)))
        self.assertEqual({subject["subject_title"] for subject in draft}, {subject["display_name"] for subject in subject_bank})

    def test_save_generated_curriculum_persists_to_sqlite(self):
        draft = generate_curriculum_draft(program="BSIT", prompt="Generate a BSIT curriculum.", subject_bank=self.subject_bank)
        db_path = self.test_db_path
        inserted = save_generated_curriculum(draft, program="BSIT", model_name="retrieval-draft", db_path=db_path, prompt="Generate a BSIT curriculum.")

        self.assertTrue(inserted > 0)
        self.assertTrue(db_path.exists())

        with closing(sqlite3.connect(db_path, timeout=30)) as conn, conn:
            table_count = conn.execute(
                "SELECT COUNT(*) FROM generated_curriculum_subjects"
            ).fetchone()[0]
            self.assertGreater(table_count, 0)

    def test_generated_run_mode_is_set_only_from_explicit_generation_outcome(self):
            generated = [{"subject_title": "Gemini-generated course", "year": "1", "term": "1"}]
            with patch("curriculum_generator.call_gemini_for_curriculum", return_value=generated):
                online_draft = generate_program_curriculum("BSIT", "Generate.", self.subject_bank)
            online_run = save_generated_curriculum(
                online_draft,
                "BSIT",
                "BAAI/bge-small-en-v1.5",
                db_path=Path(self._test_directory.name) / "online.db",
                return_run_id=True,
            )
            with closing(sqlite3.connect(Path(self._test_directory.name) / "online.db")) as connection:
                online_mode = connection.execute(
                    "SELECT generation_mode FROM generated_curriculum_runs WHERE id = ?",
                    (online_run,),
                ).fetchone()[0]
            self.assertEqual(online_mode, "online")
            self.assertTrue(all(item["_generation_mode"] == "online" for item in online_draft))

            template = [
                {
                    "subject_title": "Template course",
                    "year": "1",
                    "term": "1",
                    "rationale": "Template rationale.",
                }
            ]
            with patch(
                "curriculum_generator.call_gemini_for_curriculum",
                side_effect=curriculum_generator.GeminiGenerationError("Gemini unavailable"),
            ), patch("curriculum_generator.generate_curriculum_draft", return_value=template):
                offline_draft = generate_program_curriculum("BSIT", "Generate.", self.subject_bank)
            offline_run = save_generated_curriculum(
                offline_draft,
                "BSIT",
                "BAAI/bge-small-en-v1.5",
                db_path=Path(self._test_directory.name) / "offline.db",
                return_run_id=True,
            )
            with closing(sqlite3.connect(Path(self._test_directory.name) / "offline.db")) as connection:
                offline_mode = connection.execute(
                    "SELECT generation_mode FROM generated_curriculum_runs WHERE id = ?",
                    (offline_run,),
                ).fetchone()[0]
            self.assertEqual(offline_mode, "offline")
            self.assertTrue(all(item["_generation_mode"] == "offline" for item in offline_draft))

            unknown_run = save_generated_curriculum(
                [{"subject_title": "Unmarked legacy course", "year": "1", "term": "1"}],
                "BSIT",
                "gemini-3.5-flash-lite",
                db_path=Path(self._test_directory.name) / "unknown.db",
                return_run_id=True,
            )
            with closing(sqlite3.connect(Path(self._test_directory.name) / "unknown.db")) as connection:
                unknown_mode = connection.execute(
                    "SELECT generation_mode FROM generated_curriculum_runs WHERE id = ?",
                    (unknown_run,),
                ).fetchone()[0]
            self.assertIsNone(unknown_mode)

    def test_enhanced_run_mode_uses_explicit_review_and_course_outcomes(self):
            cases = [
                ({"fallback": False, "draft_fallback": True}, "online"),
                ({"fallback": True, "draft_fallback": False}, "online"),
                ({"fallback": True, "draft_fallback": True}, "offline"),
                ({"fallback": True}, None),
            ]
            for index, (outcomes, expected_mode) in enumerate(cases):
                with self.subTest(outcomes=outcomes):
                    db_path = Path(self._test_directory.name) / f"enhanced-{index}.db"
                    run_id = save_enhancement_report(
                        {
                            **outcomes,
                            "summary": "Review",
                            "subjects": [],
                            "recommendations": [],
                        },
                        enhanced_curriculum=[],
                        user_subjects=[],
                        program="BSIT",
                        model_name="gemini-3.5-flash-lite",
                        db_path=db_path,
                    )
                    with closing(sqlite3.connect(db_path)) as connection:
                        mode = connection.execute(
                            "SELECT generation_mode FROM generated_curriculum_runs WHERE id = ?",
                            (run_id,),
                        ).fetchone()[0]
                    self.assertEqual(mode, expected_mode)

    def test_save_generated_curriculum_can_return_run_id(self):
        with tempfile.TemporaryDirectory() as temp_dir:
            db_path = Path(temp_dir) / "guest_draft.db"
            draft = [{"subject_title": "Guest Draft Course", "year": "1", "term": "1"}]

            run_id = save_generated_curriculum(
                draft,
                program="BSIT",
                model_name="gemini-3.5-flash-lite",
                db_path=db_path,
                created_by_user_id=42,
                created_by_username="guest-created-user",
                return_run_id=True,
            )

            conn = sqlite3.connect(db_path, timeout=30)
            try:
                run = conn.execute(
                    "SELECT id, created_by_user_id, created_by_username FROM generated_curriculum_runs"
                ).fetchone()
            finally:
                conn.close()
            self.assertEqual(run_id, run[0])
            self.assertEqual(run[1:], (42, "guest-created-user"))

    def test_run_and_chat_attribution_persists_for_both_sources(self):
        draft = generate_curriculum_draft(
            program="BSIT",
            prompt="Generate a BSIT curriculum.",
            subject_bank=self.subject_bank,
        )
        with tempfile.TemporaryDirectory() as temp_dir:
            db_path = Path(temp_dir) / "attribution.db"
            conn = sqlite3.connect(db_path, timeout=30)
            conn.execute(
                """CREATE TABLE generated_curriculum_runs (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    program TEXT, prompt TEXT, model_name TEXT, status TEXT,
                    generated_at TEXT DEFAULT CURRENT_TIMESTAMP, notes TEXT
                )"""
            )
            conn.execute(
                """CREATE TABLE users (
                    id INTEGER PRIMARY KEY,
                    role TEXT NOT NULL,
                    gemini_api_key TEXT
                )"""
            )
            conn.executemany(
                "INSERT INTO users (id, role, gemini_api_key) VALUES (?, ?, ?)",
                [
                    (21, "user", None),
                    (22, "super_admin", "test-personal-key"),
                    (23, "user", None),
                    (24, "super_admin", "test-personal-key"),
                ],
            )
            conn.execute(
                """INSERT INTO generated_curriculum_runs
                   (program, prompt, model_name, status, notes)
                   VALUES ('BSIT', 'Legacy draft', 'legacy', 'draft', '')"""
            )
            conn.commit()
            conn.close()
            save_generated_curriculum(
                draft,
                program="BSIT",
                model_name="retrieval-draft",
                db_path=db_path,
                prompt="Generate a BSIT curriculum.",
                created_by_user_id=21,
                created_by_username="draft-owner",
            )
            conn = sqlite3.connect(db_path, timeout=30)
            generated_run_id = conn.execute(
                """SELECT id FROM generated_curriculum_runs
                   WHERE created_by_username = 'draft-owner'"""
            ).fetchone()[0]
            legacy_run = conn.execute(
                "SELECT id, created_by_user_id, created_by_username FROM generated_curriculum_runs WHERE id = 1"
            ).fetchone()
            conn.execute(
                """CREATE TABLE skill_coverage (
                    skill_id TEXT, skill_name TEXT, skill_type TEXT, score REAL,
                    best_course_id TEXT, best_course_title TEXT
                )"""
            )
            conn.close()

            with patch.object(
                curriculum_generator,
                "_call_gemini_for_text",
                return_value='{"action":"explain","message":"Answer","updated_curriculum":null}',
            ):
                curriculum_generator.chat_about_curriculum(
                    db_path,
                    generated_run_id,
                    "Explain this curriculum.",
                    sender_user_id=22,
                    sender_username="chat-user",
                )

            enhancement_run_id = save_enhancement_report(
                {"summary": "Review summary", "subjects": [], "recommendations": []},
                enhanced_curriculum=draft,
                user_subjects=[],
                program="BSIT",
                model_name="retrieval-draft",
                db_path=db_path,
                prompt="Enhance this curriculum.",
                created_by_user_id=23,
                created_by_username="enhancement-owner",
            )
            with patch.object(
                curriculum_generator,
                "_call_gemini_for_text",
                return_value="Answer",
            ):
                curriculum_generator.chat_about_enhancement_review(
                    db_path,
                    enhancement_run_id,
                    "Explain this review.",
                    sender_user_id=24,
                    sender_username="enhancement-chat-user",
                )

            conn = sqlite3.connect(db_path, timeout=30)
            runs = conn.execute(
                """SELECT source, created_by_username FROM generated_curriculum_runs
                   WHERE id IN (?, ?) ORDER BY id""",
                (generated_run_id, enhancement_run_id),
            ).fetchall()
            chat_senders = conn.execute(
                """SELECT sender_username FROM generated_curriculum_chat
                   WHERE role = 'user' ORDER BY id"""
            ).fetchall()
            conn.close()

        self.assertEqual(set(runs), {
            # The inserted rows retain the distinct source and authenticated username.
            ("generated", "draft-owner"),
            ("enhanced", "enhancement-owner"),
        })
        self.assertEqual(legacy_run, (1, None, None))
        self.assertEqual(
            [sender[0] for sender in chat_senders],
            ["chat-user", "enhancement-chat-user"],
        )

    def test_run_access_denies_other_users_and_unattributed_runs(self):
        conn = sqlite3.connect(":memory:", timeout=30)
        self.addCleanup(conn.close)
        conn.execute("CREATE TABLE users (id INTEGER PRIMARY KEY, role TEXT NOT NULL)")
        conn.executemany(
            "INSERT INTO users (id, role) VALUES (?, ?)",
            [(1, "user"), (2, "user"), (3, "admin"), (4, "super_admin")],
        )
        conn.execute(
            "CREATE TABLE generated_curriculum_runs (id INTEGER PRIMARY KEY, created_by_user_id INTEGER)"
        )
        conn.executemany(
            "INSERT INTO generated_curriculum_runs (id, created_by_user_id) VALUES (?, ?)",
            [(10, 1), (11, None)],
        )

        curriculum_generator._require_run_access(conn, 10, 1)
        curriculum_generator._require_run_access(conn, 10, 3, "all_owned")
        curriculum_generator._require_run_access(conn, 11, 4)
        for run_id, actor_id in [(10, 2), (11, 1), (11, 3), (999, 1)]:
            with self.subTest(run_id=run_id, actor_id=actor_id):
                with self.assertRaisesRegex(ValueError, "Run not found or inaccessible"):
                    curriculum_generator._require_run_access(conn, run_id, actor_id)

    def test_chat_rejects_cross_user_run_before_read_or_write(self):
        with closing(sqlite3.connect(self.test_db_path, timeout=30)) as conn:
            conn.execute(
                "CREATE TABLE users (id INTEGER PRIMARY KEY, role TEXT NOT NULL, gemini_api_key TEXT)"
            )
            conn.executemany(
                "INSERT INTO users (id, role, gemini_api_key) VALUES (?, ?, ?)",
                [(1, "user", "owner-key"), (2, "user", "other-key")],
            )
            conn.execute(
                "CREATE TABLE generated_curriculum_runs (id INTEGER PRIMARY KEY, created_by_user_id INTEGER)"
            )
            conn.execute("INSERT INTO generated_curriculum_runs (id, created_by_user_id) VALUES (1, 1)")
            conn.commit()

        with patch.object(curriculum_generator, "_call_gemini_for_text") as gemini_call:
            with self.assertRaisesRegex(ValueError, "Run not found or inaccessible"):
                curriculum_generator.chat_about_curriculum(
                    self.test_db_path,
                    1,
                    "Read this other user's draft.",
                    sender_user_id=2,
                )
        gemini_call.assert_not_called()

        with closing(sqlite3.connect(self.test_db_path, timeout=30)) as conn:
            chat_count = conn.execute(
                "SELECT COUNT(*) FROM generated_curriculum_chat"
            ).fetchone()[0]
        self.assertEqual(chat_count, 0)

    def test_web_actor_key_policy_blocks_keyless_user_and_admin_actions_before_gemini(self):
        with tempfile.TemporaryDirectory() as temp_dir:
            db_path = Path(temp_dir) / "api-policy.db"
            with closing(sqlite3.connect(db_path, timeout=30)) as conn:
                conn.execute(
                    """CREATE TABLE users (
                        id INTEGER PRIMARY KEY,
                        role TEXT NOT NULL,
                        gemini_api_key TEXT
                    )"""
                )
                conn.executemany(
                    "INSERT INTO users (id, role, gemini_api_key) VALUES (?, ?, NULL)",
                    [(1, "user"), (2, "admin")],
                )
                conn.commit()

            for actor_id, role in [(1, "user"), (2, "admin")]:
                for action in ("generate", "enhance", "chat"):
                    with self.subTest(role=role, action=action):
                        args = SimpleNamespace(
                            import_guest_draft_json=None,
                            actor_user_id=actor_id,
                            generate=action == "generate",
                            enhance=action == "enhance",
                            chat=action == "chat",
                            enhancement_chat=False,
                            output_db=db_path,
                        )
                        with patch.dict(os.environ, {"GEMINI_API_KEY": "shared-test-key"}):
                            with patch.object(user_operations, "parse_args", return_value=args):
                                with patch.object(
                                    curriculum_generator,
                                    "_call_gemini_for_text",
                                ) as gemini_call:
                                    with self.assertRaisesRegex(
                                        ValueError,
                                        curriculum_generator.GEMINI_API_KEY_REQUIRED_MESSAGE,
                                    ):
                                        user_operations.main()
                        gemini_call.assert_not_called()

    def test_web_actor_personal_key_and_super_admin_shared_fallback(self):
        with tempfile.TemporaryDirectory() as temp_dir:
            db_path = Path(temp_dir) / "api-policy.db"
            with closing(sqlite3.connect(db_path, timeout=30)) as conn:
                conn.execute(
                    """CREATE TABLE users (
                        id INTEGER PRIMARY KEY,
                        role TEXT NOT NULL,
                        gemini_api_key TEXT
                    )"""
                )
                conn.executemany(
                    "INSERT INTO users (id, role, gemini_api_key) VALUES (?, ?, ?)",
                    [
                        (1, "user", "personal-user-key"),
                        (2, "admin", None),
                        (3, "super_admin", None),
                    ],
                )
                conn.commit()

            with patch.dict(os.environ, {"GEMINI_API_KEY": "shared-test-key"}, clear=True):
                self.assertEqual(
                    curriculum_generator.configure_web_actor_api_key(db_path, 1),
                    "personal",
                )
                self.assertEqual(os.environ["GEMINI_API_KEY"], "personal-user-key")
            with patch.dict(os.environ, {"GEMINI_API_KEY": "shared-test-key"}, clear=True):
                with self.assertRaisesRegex(
                    ValueError,
                    curriculum_generator.GEMINI_API_KEY_REQUIRED_MESSAGE,
                ):
                    curriculum_generator.configure_web_actor_api_key(db_path, 2)
            with patch.dict(os.environ, {"GEMINI_API_KEY": "shared-test-key"}, clear=True):
                self.assertEqual(
                    curriculum_generator.configure_web_actor_api_key(db_path, 3),
                    "shared",
                )
                self.assertEqual(os.environ["GEMINI_API_KEY"], "shared-test-key")

    def test_web_actor_generate_accepts_personal_user_and_shared_super_admin_keys(self):
        with tempfile.TemporaryDirectory() as temp_dir:
            temp_path = Path(temp_dir)
            db_path = temp_path / "api-policy.db"
            with closing(sqlite3.connect(db_path, timeout=30)) as conn:
                conn.execute(
                    """CREATE TABLE users (
                        id INTEGER PRIMARY KEY,
                        role TEXT NOT NULL,
                        gemini_api_key TEXT
                    )"""
                )
                conn.executemany(
                    "INSERT INTO users (id, role, gemini_api_key) VALUES (?, ?, ?)",
                    [(1, "user", "personal-user-key"), (2, "super_admin", None)],
                )
                conn.commit()

            for actor_id, expected_key in [(1, "personal-user-key"), (2, "shared-test-key")]:
                with self.subTest(actor_id=actor_id):
                    args = SimpleNamespace(
                        import_guest_draft_json=None,
                        actor_user_id=actor_id,
                        actor_username="actor",
                        generate=True,
                        enhance=False,
                        chat=False,
                        enhancement_chat=False,
                        output_db=db_path,
                        model="BAAI/bge-small-en-v1.5",
                        output_dir=temp_path / "output",
                        courses_csv=temp_path / "courses.csv",
                        skill_coverage=temp_path / "skills.csv",
                        course_skill_matches=temp_path / "matches.csv",
                        program="BSIT",
                        prompt="Generate a draft.",
                        limit=None,
                    )
                    def generate_with_expected_key(**kwargs):
                        self.assertEqual(os.environ["GEMINI_API_KEY"], expected_key)
                        return []

                    with patch.dict(
                        os.environ,
                        {"GEMINI_API_KEY": "shared-test-key"},
                        clear=True,
                    ):
                        with patch.object(user_operations, "parse_args", return_value=args):
                            with patch.object(user_operations, "load_course_rows", return_value=[]):
                                with patch.object(user_operations, "build_subject_bank", return_value=[]):
                                    with patch.object(
                                        user_operations,
                                        "generate_program_curriculum",
                                        side_effect=generate_with_expected_key,
                                    ) as generate:
                                        with patch.object(
                                            user_operations,
                                            "save_generated_curriculum",
                                            return_value=1,
                                        ):
                                            user_operations.main()
                    generate.assert_called_once()
                    self.assertEqual(generate.call_args.kwargs["program"], "BSIT")

    def test_generate_program_curriculum_adds_subject_specific_evidence(self):
        course_skill_matches = [
            {"course_title": "Computer Networking", "skill_id": "IS-001", "skill_name": "Network Routing", "score": 0.72},
            {"course_title": "Programming Fundamentals", "skill_id": "IS-002", "skill_name": "Python Programming", "score": 0.81},
            {"course_title": "Computer Networking", "skill_id": "IS-003", "skill_name": "Database Design", "score": 0.54},
        ]
        generated_subjects = [
            {
                "program": "BSIT",
                "year": "2",
                "term": "1",
                "subject_code": "BSIT-201",
                "subject_title": title,
                "description": "Test subject.",
                "units": "3",
                "prerequisites": "None",
                "topics": ["Topic one", "Topic two", "Topic three"],
                "rationale": "Test rationale.",
                "source_colleges": [],
                "mapped_industry_skills": [],
            }
            for title in ("Computer Networking", "Programming Fundamentals")
        ]

        with patch("curriculum_generator.call_gemini_for_curriculum", return_value=generated_subjects):
            draft = generate_program_curriculum(
                "BSIT",
                "Generate a curriculum.",
                self.subject_bank,
                course_skill_matches=course_skill_matches,
            )

        self.assertEqual([item["skill_name"] for item in draft[0]["skill_evidence"]], ["Network Routing"])
        self.assertEqual([item["skill_name"] for item in draft[1]["skill_evidence"]], ["Python Programming"])
        self.assertNotEqual(draft[0]["skill_evidence"], draft[1]["skill_evidence"])
        self.assertEqual(draft[0]["skill_evidence"][0]["score"], 0.72)
        self.assertEqual(draft[0]["mapped_industry_skills"], ["Network Routing"])
        self.assertTrue(all(item["_generation_mode"] == "online" for item in draft))

    def test_subject_skill_evidence_reports_no_match_honestly(self):
        unrelated_matches = [
            {"course_title": "Deep Learning", "skill_id": "IS-001", "skill_name": "Postman API testing", "score": 0.81},
            {"course_title": "Spring Boot", "skill_id": "IS-002", "skill_name": "Java Enterprise Framework", "score": 0.77},
        ]

        subject_evidence = curriculum_generator._subject_skill_evidence("Quantum Field Theory", unrelated_matches)
        self.assertEqual(subject_evidence, [])

        guidance = curriculum_generator._practical_guidance(
            "Deep Learning",
            "3",
            "BSIT",
            self.subject_bank,
            unrelated_matches,
        )
        self.assertTrue(any("No closely matched skill evidence found." in source for source in guidance["sources"]))

    def test_subject_skill_evidence_uses_course_match_score_threshold(self):
        matches = [
            {"course_title": "Data Structures and Algorithms", "skill_id": "IS-001", "skill_name": "Algorithm Design", "score": 0.549},
            {"course_title": "Data Structures and Algorithms", "skill_id": "IS-002", "skill_name": "Data Structures", "score": 0.55},
            {"course_title": "Deep Learning", "skill_id": "IS-003", "skill_name": "Neural Networks", "score": 0.92},
        ]

        evidence = curriculum_generator._subject_skill_evidence("Data Structure and Algorithm", matches)

        self.assertEqual([item["skill_name"] for item in evidence], ["Data Structures"])
        self.assertEqual(evidence[0]["score"], 0.55)

    def test_global_skill_coverage_is_not_reused_for_subject_evidence(self):
        global_coverage = [
            {"skill_id": "IS-001", "skill_name": "Postman API testing", "score": 0.81},
            {"skill_id": "IS-002", "skill_name": "Spring Boot", "score": 0.77},
        ]
        generated = [
            {
                "program": "BSCS",
                "year": "3",
                "term": "1",
                "subject_code": "BSCS-301",
                "subject_title": "Deep Learning",
                "description": "Neural network models.",
                "units": "3",
                "prerequisites": "Machine Learning",
                "topics": ["Neural networks", "Optimization", "Evaluation"],
                "rationale": "Specialization course.",
                "source_colleges": [],
                "mapped_industry_skills": [],
            }
        ]

        with patch("curriculum_generator.call_gemini_for_curriculum", return_value=generated):
            draft = generate_program_curriculum(
                "BSCS",
                "Generate a curriculum.",
                self.subject_bank,
                skill_coverage=global_coverage,
            )

        self.assertEqual(draft[0]["skill_evidence"], [])
        self.assertEqual(draft[0]["mapped_industry_skills"], [])

    def test_enrich_enhancement_report_rebuilds_sources_for_new_reports(self):
        report_with_untrusted_sources = {
            "summary": "review summary",
            "subjects": [
                {
                    "subject_title": "Programming Fundamentals",
                    "year": "1",
                    "recommended_year": "1",
                    "status": "keep",
                    "recommended_edit": "Keep it.",
                    "sources": ["Unverified source supplied in report"],
                }
            ],
            "recommendations": [
                {
                    "subject_title": "Applied Security",
                    "target_year": "3",
                    "priority": "High",
                    "reason": "Legacy reason",
                    "sources": ["Unverified source supplied in recommendation"],
                }
            ],
        }

        report = curriculum_generator._enrich_enhancement_report(
            report_with_untrusted_sources,
            "BSIT",
            self.subject_bank,
            [],
        )

        self.assertNotIn("Unverified source supplied in report", report["subjects"][0]["sources"])
        self.assertNotIn("Unverified source supplied in recommendation", report["recommendations"][0]["sources"])
        self.assertTrue(any("No closely matched skill evidence found." in source for source in report["recommendations"][0]["sources"]))

    def test_enrich_enhancement_report_builds_recommendation_specific_skill_sources(self):
        report = {
            "summary": "review",
            "subjects": [],
            "recommendations": [
                {
                    "subject_title": "Computer Networking",
                    "target_year": "3",
                    "priority": "High",
                    "reason": "Network coursework.",
                },
                {
                    "subject_title": "Programming Fundamentals",
                    "target_year": "1",
                    "priority": "High",
                    "reason": "Programming coursework.",
                },
            ],
        }
        course_skill_matches = [
            {"course_title": "Computer Networking", "skill_id": "IS-001", "skill_name": "Networking Fundamentals", "score": 0.73},
            {"course_title": "Computer Networking", "skill_id": "IS-002", "skill_name": "Python Programming", "score": 0.81},
            {"course_title": "Programming Fundamentals", "skill_id": "IS-003", "skill_name": "Basic Programming/Scripting knowledge", "score": 0.71},
        ]

        enriched = curriculum_generator._enrich_enhancement_report(
            report,
            "BSIT",
            self.subject_bank,
            course_skill_matches,
        )

        network_sources = enriched["recommendations"][0]["sources"]
        programming_sources = enriched["recommendations"][1]["sources"]
        network_evidence = next(source for source in network_sources if source.startswith("Industry-skill coverage"))
        programming_evidence = next(source for source in programming_sources if source.startswith("Industry-skill coverage"))
        self.assertIn("Networking Fundamentals (cosine similarity 0.730)", network_evidence)
        self.assertNotIn("Python Programming", network_evidence)
        self.assertIn("Basic Programming/Scripting knowledge (cosine similarity 0.710)", programming_evidence)
        self.assertNotIn("Networking Fundamentals", programming_evidence)

    def test_deleting_run_preserves_orphaned_legacy_review_row(self):
        run_id = save_generated_curriculum(
            [{"subject_title": "Legacy review test course", "year": "1", "term": "1"}],
            program="BSIT",
            model_name="test",
            db_path=self.test_db_path,
            return_run_id=True,
        )
        connection = sqlite3.connect(self.test_db_path, timeout=30)
        try:
            connection.execute("PRAGMA foreign_keys = ON")
            connection.execute(
                """
                CREATE TABLE generated_curriculum_reviews (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    run_id INTEGER,
                    status TEXT,
                    reviewer TEXT,
                    notes TEXT,
                    reviewed_at TEXT DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY(run_id) REFERENCES generated_curriculum_runs(id)
                )
                """
            )
            connection.execute(
                "INSERT INTO generated_curriculum_reviews (run_id, status, reviewer, notes) VALUES (?, ?, ?, ?)",
                (run_id, "approved", "legacy reviewer", "Keep this historical record."),
            )
            connection.commit()
            self.assertEqual(connection.execute("PRAGMA foreign_keys").fetchone()[0], 1)
            foreign_keys = connection.execute(
                "PRAGMA foreign_key_list(generated_curriculum_reviews)"
            ).fetchall()
            self.assertTrue(any(row[2] == "generated_curriculum_runs" for row in foreign_keys))

            connection.execute("PRAGMA foreign_keys = OFF")
            self.assertEqual(connection.execute("PRAGMA foreign_keys").fetchone()[0], 0)
            connection.execute("DELETE FROM generated_curriculum_subjects WHERE run_id = ?", (run_id,))
            connection.execute("DELETE FROM generated_curriculum_runs WHERE id = ?", (run_id,))
            preserved_review = connection.execute(
                "SELECT status, notes FROM generated_curriculum_reviews WHERE run_id = ?",
                (run_id,),
            ).fetchone()
            self.assertEqual(preserved_review, ("approved", "Keep this historical record."))
            self.assertIsNone(
                connection.execute(
                    "SELECT id FROM generated_curriculum_runs WHERE id = ?", (run_id,)
                ).fetchone()
            )
        finally:
            connection.close()

    def test_unplaced_benchmark_courses_stay_within_four_years(self):
        subject_bank = [
            {
                "program": "BSIT",
                "program_key": "bsit",
                "canonical_subject": f"subject {index}",
                "display_name": f"Subject {index}",
                "year_terms": ["YTvaries"],
                "source_colleges": ["Test University"],
                "classification": "Core",
                "units": ["3"],
            }
            for index in range(12)
        ]

        draft = generate_curriculum_draft(
            program="BSIT",
            prompt="Complete the roadmap.",
            subject_bank=subject_bank,
            limit=len(subject_bank),
        )

        self.assertEqual({subject["year"] for subject in draft}, {"1", "2", "3", "4"})
        self.assertTrue(all(subject["term"] in {"1", "2"} for subject in draft))

    def test_user_courses_replace_equivalent_baseline_titles_only(self):
        submitted = [
            {"subject_title": "capstone 1", "description": "", "year": "4"},
            {"subject_title": "internship", "description": "", "year": "4"},
            {"subject_title": "advanced database", "description": "", "year": "3"},
            {"subject_title": "networking", "description": "", "year": "3"},
        ]
        report = {
            "subjects": [
                {"subject_title": subject["subject_title"], "year": subject["year"], "recommended_year": subject["year"], "status": "keep", "recommended_edit": "Keep the reviewed subject."}
                for subject in submitted
            ],
            "recommendations": [],
        }
        course_rows = [
            {"id": "ADAMSON-01", "university": "Adamson University", "program": "BSIT", "course": "IT Capstone Project 1", "classification": "Research / Capstone", "year": "4", "term": "1"},
            {"id": "ADAMSON-02", "university": "Adamson University", "program": "BSIT", "course": "On-the-Job Training for Information Technology", "classification": "Internship", "year": "4", "term": "2"},
            {"id": "ADAMSON-03", "university": "Adamson University", "program": "BSIT", "course": "Advanced Database Management System", "classification": "Professional", "year": "3", "term": "1"},
            {"id": "ADAMSON-04", "university": "Adamson University", "program": "BSIT", "course": "Networking 1", "classification": "Core", "year": "3", "term": "1"},
            {"id": "ADAMSON-05", "university": "Adamson University", "program": "BSIT", "course": "Computer Programming 1", "classification": "Core", "year": "1", "term": "1"},
            {"id": "ADAMSON-06", "university": "Adamson University", "program": "BSIT", "course": "Computer Programming 2", "classification": "Core", "year": "1", "term": "2"},
        ]

        draft = _fallback_enhanced_curriculum("BSIT", submitted, report, [], course_rows)
        titles = [subject["subject_title"] for subject in draft]
        self.assertTrue(all(subject["subject_title"] in titles for subject in submitted))
        self.assertNotIn("IT Capstone Project 1", titles)
        self.assertNotIn("On-the-Job Training for Information Technology", titles)
        self.assertNotIn("Advanced Database Management System", titles)
        self.assertNotIn("Networking 1", titles)
        self.assertIn("Computer Programming 1", titles)
        self.assertIn("Computer Programming 2", titles)

    def test_enhancement_fallback_applies_review_and_saves_completed_draft(self):
        submitted_subjects = [
            {"subject_title": "Legacy Application Development", "description": "Existing app course", "year": "1"},
            {"subject_title": "Outdated Office Suite", "description": "", "year": "2"},
        ]
        enhancement_report = {
            "summary": "Move the application course and remove the outdated course.",
            "subjects": [
                {"subject_title": "Legacy Application Development", "year": "1", "recommended_year": "3", "status": "move", "recommended_edit": "Update the course to modern application architecture."},
                {"subject_title": "Outdated Office Suite", "year": "2", "recommended_year": "2", "status": "unnecessary", "recommended_edit": "Remove this course."},
            ],
            "recommendations": [
                {"subject_title": "Cloud Deployment Fundamentals", "target_year": "4", "priority": "high", "reason": "Build deployment and operations skills."},
            ],
        }
        subject_bank = [
            {
                "program": "BSIT",
                "program_key": "bsit",
                "canonical_subject": "programming fundamentals",
                "display_name": "Programming Fundamentals",
                "year_terms": ["Y1T1"],
                "source_colleges": ["Test University"],
                "units": ["3"],
            },
        ]
        course_rows = [
            {"id": "ADAMSON-BSIT-01", "university": "Adamson University", "program": "BSIT", "course": "Programming Fundamentals", "classification": "Core", "year": "1", "term": "1"},
            {"id": "ADAMSON-BSIT-02", "university": "Adamson University", "program": "BSIT", "course": "Software Engineering", "classification": "Professional", "year": "3", "term": "1"},
                        {"id": "ADAMSON-BSIT-03", "university": "Adamson University", "program": "BSIT", "course": "Cloud Deployment Fundamentals", "classification": "Professional", "year": "4", "term": "1"},
            {"id": "ADAMSON-BSIT-GE", "university": "Adamson University", "program": "BSIT", "course": "General Communication", "classification": "General Education", "year": "1", "term": "1"},
            {"id": "ADAMSON-BSIT-BAD", "university": "Adamson University", "program": "BSIT", "course": "Bad Placement Course", "classification": "Professional", "year": "22", "term": "1"},
            {"id": "LPU-BSIT-01", "university": "LPU Manila", "program": "BSIT", "course": "Other University Programming", "classification": "Core", "year": "1", "term": "1"},
        ]

        completed_draft = _fallback_enhanced_curriculum(
            "BSIT", submitted_subjects, enhancement_report, subject_bank, course_rows
        )
        titles = [subject["subject_title"] for subject in completed_draft]
        self.assertIn("Legacy Application Development", titles)
        self.assertNotIn("Outdated Office Suite", titles)
        self.assertIn("Cloud Deployment Fundamentals", titles)
        self.assertIn("Programming Fundamentals", titles)
        self.assertNotIn("General Communication", titles)
        self.assertNotIn("Bad Placement Course", titles)
        self.assertNotIn("Other University Programming", titles)
        self.assertTrue(all(subject["year"] in {"1", "2", "3", "4"} for subject in completed_draft))
        moved_subject = next(subject for subject in completed_draft if subject["subject_title"] == "Legacy Application Development")
        self.assertEqual(moved_subject["year"], "3")
        _validate_enhanced_curriculum(completed_draft, submitted_subjects, enhancement_report)

        with tempfile.TemporaryDirectory() as directory:
            db_path = Path(directory) / "completed_curriculum.db"
            run_id = save_enhancement_report(
                enhancement_report,
                enhanced_curriculum=completed_draft,
                user_subjects=submitted_subjects,
                program="BSIT",
                model_name="test-model",
                db_path=db_path,
            )
            connection = sqlite3.connect(db_path, timeout=30)
            try:
                saved_titles = [
                    row[0]
                    for row in connection.execute(
                        "SELECT subject_title FROM generated_curriculum_subjects WHERE run_id = ?",
                        (run_id,),
                    )
                ]
                notes = json.loads(
                    connection.execute(
                        "SELECT notes FROM generated_curriculum_runs WHERE id = ?",
                        (run_id,),
                    ).fetchone()[0]
                )
            finally:
                connection.close()

            connection = sqlite3.connect(db_path, timeout=30)
            try:
                run_status, preserved_notes = connection.execute(
                    "SELECT status, notes FROM generated_curriculum_runs WHERE id = ?",
                    (run_id,),
                ).fetchone()
            finally:
                connection.close()

        self.assertEqual(set(saved_titles), set(titles))
        self.assertEqual(notes["submitted_subjects"], submitted_subjects)
        self.assertEqual(run_status, "draft")
        self.assertEqual(json.loads(preserved_notes)["summary"], enhancement_report["summary"])

    def test_enhanced_generation_applies_review_decisions(self):
        submitted_subjects = [
            {"subject_title": "Programming Foundations", "description": "Programming basics", "year": "1"},
            {"subject_title": "Application Architecture", "description": "", "year": "1"},
            {"subject_title": "Legacy Suite", "description": "", "year": "2"},
        ]
        review = {
            "subjects": [
                {"subject_title": "Programming Foundations", "year": "1", "recommended_year": "1", "status": "keep"},
                {"subject_title": "Application Architecture", "year": "1", "recommended_year": "3", "status": "move"},
                {"subject_title": "Legacy Suite", "year": "2", "recommended_year": "2", "status": "unnecessary"},
            ],
            "recommendations": [
                {"subject_title": "Cloud Computing", "target_year": "3", "priority": "high", "reason": "Add cloud deployment skills."},
            ],
        }
        term_titles = [
            ("1", "1", ["Programming Foundations", "Software Fundamentals"]),
            ("1", "2", ["Data Structures", "Database Systems"]),
            ("2", "1", ["Network Fundamentals", "Operating Systems"]),
            ("2", "2", ["Web Development", "Information Assurance"]),
            ("3", "1", ["Application Architecture", "Distributed Systems"]),
            ("3", "2", ["Software Engineering", "Cloud Computing"]),
            ("4", "1", ["Capstone I", "Internship Preparation"]),
            ("4", "2", ["Capstone II", "Professional Practice"]),
        ]
        generated_subjects = []
        for year, term, titles in term_titles:
            for title in titles:
                generated_subjects.append(
                    {
                        "program": "BSIT",
                        "year": year,
                        "term": term,
                        "subject_code": f"BSIT-{year}{term}-{len(generated_subjects) + 1:02d}",
                        "subject_title": title,
                        "description": f"{title} curriculum",
                        "units": "3",
                        "prerequisites": "None",
                        "topics": ["Foundations", "Applied practice", "Project evaluation"],
                        "rationale": "Based on the enhancement review.",
                        "source_colleges": [],
                        "mapped_industry_skills": [],
                        "source": "user" if title in {"Programming Foundations", "Application Architecture"} else "ai_added",
                    }
                )
        payload = {"candidates": [{"content": {"parts": [{"text": json.dumps(generated_subjects)}]}}]}
        major_subject_bank = [
            {
                "program": "BSIT",
                "program_key": "bsit",
                "canonical_subject": subject["subject_title"].casefold(),
                "display_name": subject["subject_title"],
                "subject_variants": [subject["subject_title"]],
                "source_colleges": ["Test University"],
                "classification": "Core",
                "year_terms": [f"Y{subject['year']}T{subject['term']}"],
                "units": ["3"],
            }
            for subject in generated_subjects
            if subject["source"] == "ai_added"
        ]

        with patch("curriculum_generator._request_gemini_prompt", return_value=(payload, "")):
            completed = curriculum_generator.generate_enhanced_curriculum(
                program="BSIT",
                specialization="",
                prompt="Complete the roadmap.",
                user_subjects=submitted_subjects,
                enhancement_report=review,
                subject_bank=major_subject_bank,
            )

        self.assertEqual(len(completed), 16)
        self.assertNotIn("Legacy Suite", [subject["subject_title"] for subject in completed])
        self.assertEqual(
            next(subject["year"] for subject in completed if subject["subject_title"] == "Application Architecture"),
            "3",
        )
        self.assertTrue(any(subject["subject_title"] == "Cloud Computing" and subject["source"] == "ai_added" for subject in completed))


if __name__ == "__main__":
    unittest.main()
