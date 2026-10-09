import contextlib
import csv
import io
import json
import sqlite3
import tempfile
import unittest
from pathlib import Path

from curriculum_generator import save_enhancement_report
from job_postings import (
    attach_job_postings,
    lookup_job_postings,
    match_topics,
    validate_posting_url,
)


class JobPostingTests(unittest.TestCase):
    def setUp(self):
        self.temp_dir = tempfile.TemporaryDirectory()
        self.addCleanup(self.temp_dir.cleanup)
        self.root = Path(self.temp_dir.name)
        self.postings_path = self.root / "job_postings.csv"
        self.topic_map_path = self.root / "job_topic_map.csv"
        self.write_topic_map(
            [
                ("programming", "programming"),
                ("programming", "software"),
                ("databases", "database"),
                ("databases", "data management"),
                ("networking", "network"),
                ("security", "security"),
                ("data and ai", "deep learning"),
            ]
        )

    def write_topic_map(self, rows):
        with self.topic_map_path.open("w", encoding="utf-8-sig", newline="") as handle:
            writer = csv.writer(handle)
            writer.writerow(["topic", "keyword"])
            writer.writerows(rows)

    def write_postings(self, rows):
        with self.postings_path.open("w", encoding="utf-8-sig", newline="") as handle:
            writer = csv.DictWriter(
                handle,
                fieldnames=[
                    "topic",
                    "posting_url",
                    "posting_title",
                    "employer",
                    "date_retrieved",
                    "notes",
                    "link_type",
                ],
            )
            writer.writeheader()
            writer.writerows(rows)

    @staticmethod
    def posting(topic, url, title="Engineer", employer="Example Inc", retrieved="2026-04-02", notes="", link_type="posting"):
        return {
            "topic": topic,
            "posting_url": url,
            "posting_title": title,
            "employer": employer,
            "date_retrieved": retrieved,
            "notes": notes,
            "link_type": link_type,
        }

    def test_url_validation_accepts_direct_http_and_https_postings(self):
        self.assertTrue(validate_posting_url("https://example.com/job/123"))
        self.assertTrue(validate_posting_url("http://example.com/careers/role-123"))

    def test_url_validation_rejects_non_http_malformed_and_search_pages(self):
        invalid_urls = [
            "",
            "javascript:alert(1)",
            "https:///missing-host",
            "https://example.com/search?q=developer",
            "https://example.com/jobs?q=developer",
            "https://example.com/jobs/search/developer",
            "https://example.com/job/abc def",
        ]
        for url in invalid_urls:
            with self.subTest(url=url):
                self.assertFalse(validate_posting_url(url))

    def test_search_looking_url_is_accepted_only_for_explicit_listing_type(self):
        search_url = "https://ph.indeed.com/q-python-jobs.html"
        self.write_postings(
            [
                self.posting("programming", search_url, link_type="listing"),
                self.posting("programming", "https://jobs.example.com/search?q=python", link_type="posting"),
            ]
        )
        with contextlib.redirect_stderr(io.StringIO()) as errors:
            result = lookup_job_postings("Software Programming", self.postings_path, self.topic_map_path)
        self.assertEqual([posting["link_type"] for posting in result["postings"]], ["listing"])
        self.assertIn("invalid or search/result URL", errors.getvalue())

    def test_listing_type_still_requires_a_valid_http_url(self):
        self.write_postings(
            [
                self.posting("programming", "javascript:alert(1)", link_type="listing"),
                self.posting("programming", "https://valid.example.com/jobs?q=python", link_type="listing"),
            ]
        )
        result = lookup_job_postings("Software Programming", self.postings_path, self.topic_map_path)
        self.assertEqual(
            [posting["posting_url"] for posting in result["postings"]],
            ["https://valid.example.com/jobs?q=python"],
        )

    def test_postings_are_sorted_before_listing_pages(self):
        self.write_postings(
            [
                self.posting("programming", "https://jobs.example.com/listing", retrieved="2026-06-01", link_type="listing"),
                self.posting("programming", "https://jobs.example.com/older", retrieved="2025-01-01"),
                self.posting("programming", "https://jobs.example.com/newer", retrieved="2026-01-01"),
            ]
        )
        result = lookup_job_postings("Software Programming", self.postings_path, self.topic_map_path)
        self.assertEqual(
            [posting["posting_url"] for posting in result["postings"]],
            [
                "https://jobs.example.com/newer",
                "https://jobs.example.com/older",
                "https://jobs.example.com/listing",
            ],
        )

    def test_keyword_matching_uses_whole_words_phrases_and_all_matching_topics(self):
        self.assertEqual(
            match_topics("Advanced Database and Network Security", {
                "databases": ["database", "data management"],
                "networking": ["network"],
                "security": ["security"],
            }),
            ["databases", "networking", "security"],
        )
        self.assertEqual(match_topics("Web of Metadata", {"databases": ["data"]}), [])
        self.assertEqual(match_topics("Deep Learning", {"data and ai": ["deep learning"]}), ["data and ai"])
        self.assertEqual(match_topics("Fine Arts", {"programming": ["program"]}), [])

    def test_postings_are_deduplicated_and_sorted_newest_first(self):
        self.write_postings(
            [
                self.posting("programming", "https://jobs.example/old", retrieved="2025-01-01"),
                self.posting("programming", "https://jobs.example/new", retrieved="2026-03-10"),
                self.posting("programming", "https://jobs.example/new", retrieved="2026-02-01"),
            ]
        )
        with contextlib.redirect_stderr(io.StringIO()) as errors:
            result = lookup_job_postings("Programming", self.postings_path, self.topic_map_path)
        self.assertEqual(
            [posting["posting_url"] for posting in result["postings"]],
            ["https://jobs.example/new", "https://jobs.example/old"],
        )
        self.assertIn("duplicate posting URL", errors.getvalue())

    def test_postings_are_limited_to_topics_matched_by_subject(self):
        self.write_postings(
            [
                self.posting("databases", "https://jobs.example/db"),
                self.posting("security", "https://jobs.example/security"),
            ]
        )
        result = lookup_job_postings("Database Systems", self.postings_path, self.topic_map_path)
        self.assertEqual(result["matched_topics"], ["databases"])
        self.assertEqual([posting["topic"] for posting in result["postings"]], ["databases"])

    def test_url_topic_associations_are_kept_but_duplicate_url_shows_once(self):
        shared_url = "https://jobs.example/shared"
        self.write_postings(
            [
                self.posting("databases", shared_url),
                self.posting("data and ai", shared_url),
            ]
        )
        single_topic = lookup_job_postings("Database Systems", self.postings_path, self.topic_map_path)
        both_topics = lookup_job_postings(
            "Database Deep Learning",
            self.postings_path,
            self.topic_map_path,
        )
        self.assertEqual([row["topic"] for row in single_topic["postings"]], ["databases"])
        self.assertEqual(both_topics["matched_topics"], ["databases", "data and ai"])
        self.assertEqual(len(both_topics["postings"]), 1)

    def test_multi_topic_subject_collects_only_matched_topics_and_caps_at_five(self):
        rows = [
            self.posting("databases", f"https://jobs.example/db-{index}", retrieved=f"2026-05-{10 + index:02d}")
            for index in range(1, 4)
        ]
        rows.extend(
            [
                self.posting("networking", "https://jobs.example/network", retrieved="2026-05-20"),
                self.posting("security", "https://jobs.example/security", retrieved="2026-05-19"),
                self.posting("programming", "https://jobs.example/programming", retrieved="2026-05-30"),
            ]
        )
        self.write_postings(rows)

        result = lookup_job_postings("Database Network Security", self.postings_path, self.topic_map_path)

        self.assertEqual(result["matched_topics"], ["databases", "networking", "security"])
        self.assertEqual(len(result["postings"]), 5)
        self.assertNotIn("programming", {posting["topic"] for posting in result["postings"]})

    def test_fewer_than_five_and_zero_posting_cases(self):
        self.write_postings(
            [
                self.posting("networking", "https://jobs.example/network-1"),
                self.posting("networking", "https://jobs.example/network-2"),
            ]
        )
        result = lookup_job_postings("Network Administration", self.postings_path, self.topic_map_path)
        self.assertEqual(len(result["postings"]), 2)

        empty_path = self.root / "empty.csv"
        empty_path.write_text(
            "topic,posting_url,posting_title,employer,date_retrieved,notes\n",
            encoding="utf-8",
        )
        no_postings = lookup_job_postings("Network Administration", empty_path, self.topic_map_path)
        self.assertEqual(no_postings, {"matched_topics": ["networking"], "postings": []})
        no_topic_match = lookup_job_postings("Art History", self.postings_path, self.topic_map_path)
        self.assertEqual(no_topic_match, {"matched_topics": [], "postings": []})

    def test_blank_invalid_search_and_unknown_topic_rows_are_skipped_and_logged(self):
        self.write_postings(
            [
                self.posting("programming", ""),
                self.posting("programming", "https://example.com/search?q=developer"),
                self.posting("unknown", "https://jobs.example/unknown"),
                self.posting("programming", "https://jobs.example/valid"),
            ]
        )
        with self.postings_path.open("a", encoding="utf-8") as handle:
            handle.write("\n")
        with contextlib.redirect_stderr(io.StringIO()) as errors:
            result = lookup_job_postings("Software Programming", self.postings_path, self.topic_map_path)
        self.assertEqual(len(result["postings"]), 1)
        for expected in ("required posting field", "search/result URL", "not in the topic map", "blank row"):
            self.assertIn(expected, errors.getvalue())

    def test_missing_or_malformed_csv_files_fail_safely(self):
        missing = self.root / "missing.csv"
        result = lookup_job_postings("Database Systems", missing, self.topic_map_path)
        self.assertEqual(result, {"matched_topics": ["databases"], "postings": []})

        malformed_topic_map = self.root / "malformed_topics.csv"
        malformed_topic_map.write_text("not_topic,wrong\nx,y\n", encoding="utf-8")
        self.assertEqual(
            lookup_job_postings("Database Systems", self.postings_path, malformed_topic_map),
            {"matched_topics": [], "postings": []},
        )

        malformed_postings = self.root / "malformed_postings.csv"
        malformed_postings.write_text("url,title\nhttps://example.com,job\n", encoding="utf-8")
        self.assertEqual(
            lookup_job_postings("Database Systems", malformed_postings, self.topic_map_path),
            {"matched_topics": ["databases"], "postings": []},
        )

        bad_csv = self.root / "bad.csv"
        bad_csv.write_bytes(b"\x80")
        self.assertEqual(
            lookup_job_postings("Database Systems", bad_csv, self.topic_map_path),
            {"matched_topics": ["databases"], "postings": []},
        )

    def test_older_saved_report_without_posting_field_stays_unchanged(self):
        old_report = {
            "summary": "Previously saved.",
            "subjects": [],
            "recommendations": [{"subject_title": "Database Systems", "sources": ["old source"]}],
        }
        with tempfile.TemporaryDirectory() as directory:
            db_path = Path(directory) / "legacy.db"
            run_id = save_enhancement_report(
                old_report,
                enhanced_curriculum=[],
                user_subjects=[],
                program="BSIT",
                model_name="legacy",
                db_path=db_path,
            )
            with contextlib.closing(sqlite3.connect(db_path, timeout=30)) as connection:
                loaded_report = json.loads(
                    connection.execute(
                        "SELECT notes FROM generated_curriculum_runs WHERE id = ?",
                        (run_id,),
                    ).fetchone()[0]
                )
        self.assertNotIn("job_postings", loaded_report["recommendations"][0])
        self.assertEqual(loaded_report["recommendations"][0]["sources"], ["old source"])

    def test_saved_enhancement_report_round_trips_posting_snapshot(self):
        report = {
            "summary": "Review",
            "subjects": [],
            "recommendations": [
                {"subject_title": "Database Systems", "sources": ["existing source"]},
            ],
        }
        self.write_postings([self.posting("databases", "https://jobs.example/db")])
        attach_job_postings(report, self.postings_path, self.topic_map_path)
        with tempfile.TemporaryDirectory() as directory:
            db_path = Path(directory) / "runs.db"
            run_id = save_enhancement_report(
                report,
                enhanced_curriculum=[],
                user_subjects=[],
                program="BSIT",
                model_name="test",
                db_path=db_path,
            )
            with contextlib.closing(sqlite3.connect(db_path, timeout=30)) as connection:
                saved = json.loads(
                    connection.execute(
                        "SELECT notes FROM generated_curriculum_runs WHERE id = ?",
                        (run_id,),
                    ).fetchone()[0]
                )
            self.assertEqual(
                saved["recommendations"][0]["job_postings"],
                report["recommendations"][0]["job_postings"],
            )
            self.assertEqual(saved["recommendations"][0]["sources"], ["existing source"])
