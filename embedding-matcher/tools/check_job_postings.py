#!/usr/bin/env python3
"""Report curated posting coverage by topic and optional curriculum program."""

from __future__ import annotations

import argparse
import csv
import io
import sys
from contextlib import redirect_stderr
from pathlib import Path
from unittest.mock import patch

PROJECT_ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(PROJECT_ROOT))

from runtime_paths import knowledge_base_dir

import curriculum_generator
from job_postings import (
    MAX_POSTINGS_PER_RECOMMENDATION,
    _load_postings,
    load_topic_keywords,
    match_topics,
    select_job_postings,
)

KB_DATA_ROOT = knowledge_base_dir(PROJECT_ROOT) / "data"
POSTINGS_CSV = KB_DATA_ROOT / "job_postings.csv"
TOPIC_MAP_CSV = KB_DATA_ROOT / "job_topic_map.csv"
SUBJECT_BANK_CSV = PROJECT_ROOT / "canonical_subject_bank.csv"


def _load_subject_bank(path: Path) -> list[dict[str, object]]:
    with path.open("r", encoding="utf-8-sig", newline="") as handle:
        bank: list[dict[str, object]] = []
        for row in csv.DictReader(handle):
            item: dict[str, object] = dict(row)
            for key in ("year_terms", "units", "subject_variants", "source_colleges"):
                item[key] = [
                    part.strip()
                    for part in str(row.get(key) or "").split(";")
                    if part.strip()
                ]
            bank.append(item)
    return bank


def check_coverage(program: str | None = None) -> int:
    captured_diagnostics = io.StringIO()
    with redirect_stderr(captured_diagnostics):
        topic_keywords = load_topic_keywords(TOPIC_MAP_CSV)
        if topic_keywords:
            postings = _load_postings(POSTINGS_CSV, set(topic_keywords))
        else:
            postings = []

    diagnostics = captured_diagnostics.getvalue().strip()
    if diagnostics:
        print("Skipped/invalid data diagnostics:")
        print(diagnostics)

    if not topic_keywords:
        print("Topic map unavailable or invalid; no topic coverage can be reported.")
        return 0

    posting_counts = {topic: {"posting": 0, "listing": 0} for topic in topic_keywords}
    for posting in postings:
        link_type = posting.get("link_type", "posting")
        posting_counts[posting["topic"]][link_type] += 1

    print("Valid job-posting links by topic:")
    for topic in topic_keywords:
        counts = posting_counts[topic]
        print(f"- {topic}: {counts['posting']} posting(s), {counts['listing']} listing(s)")

    underfilled = [
        topic for topic, counts in posting_counts.items()
        if sum(counts.values()) < MAX_POSTINGS_PER_RECOMMENDATION
    ]
    print("Topics with fewer than 5 valid postings:")
    for topic in underfilled:
        counts = posting_counts[topic]
        print(f"- {topic}: {counts['posting']} posting(s), {counts['listing']} listing(s)")

    if program:
        if not SUBJECT_BANK_CSV.is_file():
            print(f"Subject bank unavailable: {SUBJECT_BANK_CSV}", file=sys.stderr)
            return 1
        bank = _load_subject_bank(SUBJECT_BANK_CSV)
        with patch(
            "curriculum_generator.call_gemini_for_curriculum",
            side_effect=curriculum_generator.GeminiGenerationError(
                "Gemini disabled for posting-coverage report."
            ),
        ):
            subjects = curriculum_generator.generate_program_curriculum(
                program=program,
                prompt=f"Generate a {program} curriculum for posting coverage analysis.",
                subject_bank=bank,
                limit=6,
            )
        print(f"Offline recommendation topic coverage for {program}:")
        for subject in subjects:
            title = str(subject.get("subject_title") or "")
            matched_topics = match_topics(title, topic_keywords)
            selected = select_job_postings(title, topic_keywords, postings)
            topics_text = ", ".join(matched_topics) if matched_topics else "none"
            print(f"- {title}: topics={topics_text}; postings to show={len(selected['postings'])}")

    return 0


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--program", help="Optionally report topics/posting counts for each offline recommendation.")
    args = parser.parse_args()
    return check_coverage(args.program)


if __name__ == "__main__":
    raise SystemExit(main())
