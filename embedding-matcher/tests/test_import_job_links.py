from __future__ import annotations

import csv
import tempfile
import unittest
from pathlib import Path

from openpyxl import Workbook

from tools.import_job_links import _classify_url, import_job_links


class ImportJobLinksTests(unittest.TestCase):
    def setUp(self):
        self.temp_dir = tempfile.TemporaryDirectory()
        self.addCleanup(self.temp_dir.cleanup)
        self.root = Path(self.temp_dir.name)
        self.workbook_path = self.root / "source.xlsx"
        self.postings_path = self.root / "job_postings.csv"
        self.mapping_path = self.root / "role_map.csv"
        self._write_role_map()
        self._write_workbook()

    def _write_role_map(self) -> None:
        with self.mapping_path.open("w", encoding="utf-8", newline="") as handle:
            writer = csv.writer(handle)
            writer.writerow(["role", "topic"])
            writer.writerow(["Software/Web Developer", "programming"])
            writer.writerow(["Cybersecurity Analyst", "security"])

    def _write_workbook(self) -> None:
        workbook = Workbook()
        skills = workbook.active
        skills.title = "Industry_Skills_Data"
        skills.append([
            "ID", "Role_Category", "Skill_or_Competency", "Skill_Type", "Entry_Level",
            "Demand_Signal", "Evidence_Count", "Source_Type", "Source_Name", "Source_URL",
            "Posting_or_Report_Date", "Date_Collected", "Notes",
        ])
        skills.append([
            1, "Software/Web Developer", "Java", "Programming", "Y", "High", 2,
            "Job Posting", "Bossjob - Junior Developer", "https://bossjob.com/en-us/job/junior-dev-123",
            "2026", "2026-09-20", "",
        ])
        skills.append([
            2, "Cybersecurity Analyst", "Security", "Practice", "Y", "High", 2,
            "Industry Report", "Security report", "https://example.org/reports/security",
            "2026", "2026-09-20", "",
        ])
        skills.append([
            3, "Unknown Role", "Unknown", "Practice", "Y", "Medium", 1,
            "Job Posting", "Unknown", "https://bossjob.com/en-us/job/unknown-123",
            "2026", "2026-09-20", "",
        ])

        log = workbook.create_sheet("Job_Postings_Log_113")
        log.append([
            "Posting ID", "Role Cluster", "Posting Title / Snippet", "Company (if named)",
            "Location", "Salary (PHP/mo)", "Posted", "Entry-Level Signal", "Notes",
            "Source Search Page (URL)",
        ])
        log.append([
            "JOB-001", "Software/Web Developer", "Developer role", "Example Co", "Manila",
            "", "21d ago", "Y", "", "https://ph.jobstreet.com/Software-Developer-jobs",
        ])
        log.append([
            "JOB-002", "Unmapped Cluster", "Unmapped role", "Example Co", "Manila",
            "", "2026-09-01", "Y", "", "https://bossjob.com/en-us/job/unmapped-123",
        ])
        workbook.save(self.workbook_path)

    @staticmethod
    def _read_rows(path: Path) -> list[dict[str, str]]:
        with path.open("r", encoding="utf-8-sig", newline="") as handle:
            return list(csv.DictReader(handle))

    def test_classifies_direct_posting_listing_and_non_job_urls(self):
        self.assertEqual(
            _classify_url("https://bossjob.com/en-us/job/software-developer-306213"),
            "posting",
        )
        self.assertEqual(
            _classify_url("https://ph.indeed.com/q-python-developer-jobs.html"),
            "listing",
        )
        self.assertIsNone(_classify_url("https://survey.example.org/technology"))
        self.assertIsNone(_classify_url("javascript:alert(1)"))

    def test_import_is_idempotent_preserves_hand_rows_and_skips_unmapped(self):
        hand_row = {
            "topic": "security",
            "posting_url": "https://manual.example.com/job/hand-added",
            "posting_title": "Manual posting",
            "employer": "Manual Employer",
            "date_retrieved": "2026-09-01",
            "notes": "curated by maintainer",
        }
        with self.postings_path.open("w", encoding="utf-8", newline="") as handle:
            writer = csv.DictWriter(handle, fieldnames=list(hand_row))
            writer.writeheader()
            writer.writerow(hand_row)

        first = import_job_links(self.workbook_path, self.postings_path, self.mapping_path)
        first_rows = self._read_rows(self.postings_path)
        second = import_job_links(self.workbook_path, self.postings_path, self.mapping_path)
        second_rows = self._read_rows(self.postings_path)

        self.assertEqual(len(first_rows), 3)
        self.assertEqual(len(second_rows), 3)
        self.assertEqual(first_rows, second_rows)
        self.assertEqual(first_rows[0]["posting_url"], hand_row["posting_url"])
        self.assertEqual(first_rows[0]["link_type"], "posting")
        imported_listing = next(row for row in first_rows if row["link_type"] == "listing")
        self.assertEqual(
            imported_listing["posting_title"],
            "Software/Web Developer job listings (ph.jobstreet.com)",
        )
        self.assertEqual(imported_listing["date_retrieved"], "")
        self.assertEqual(first["kept_posting"], 1)
        self.assertEqual(first["kept_listing"], 1)
        self.assertEqual(second["kept_posting"], 0)
        self.assertEqual(second["kept_listing"], 0)
        self.assertGreater(second["skipped"]["unmapped role"], 0)
        self.assertEqual(second["skipped"]["duplicate URL"], 2)


if __name__ == "__main__":
    unittest.main()
