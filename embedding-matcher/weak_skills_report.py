#!/usr/bin/env python3
"""Export the weakest matched skills from the SQLite database.

This script reads the stored skill coverage table and produces a short report
showing the lowest-scoring skills, which are the most likely curriculum gaps.
"""

from __future__ import annotations

import sqlite3
import csv
from pathlib import Path

DB_PATH = Path(__file__).resolve().parent / "curriculum_matching.db"
OUTPUT_CSV = Path(__file__).resolve().parent / "weakest_skills_report.csv"


def get_connection() -> sqlite3.Connection:
    conn = sqlite3.connect(DB_PATH, timeout=30)
    conn.row_factory = sqlite3.Row
    return conn


def main() -> None:
    conn = get_connection()
    rows = conn.execute(
        """
        SELECT skill_id, skill_name, skill_type, best_course_id, best_course_title, score
        FROM skill_coverage
        ORDER BY score ASC
        LIMIT 20
        """
    ).fetchall()
    conn.close()

    with OUTPUT_CSV.open("w", newline="", encoding="utf-8") as handle:
        writer = csv.writer(handle)
        writer.writerow(["skill_id", "skill_name", "skill_type", "best_course_id", "best_course_title", "score"])
        for row in rows:
            writer.writerow([
                row["skill_id"],
                row["skill_name"],
                row["skill_type"],
                row["best_course_id"],
                row["best_course_title"],
                row["score"],
            ])

    print(f"Database: {DB_PATH}")
    print(f"Weakest skills report written to: {OUTPUT_CSV}")
    print(f"Rows exported: {len(rows)}")
    for row in rows:
        print(f"{row['skill_id']} | {row['skill_name']} | {row['best_course_title']} | {row['score']:.4f}")


if __name__ == "__main__":
    main()
