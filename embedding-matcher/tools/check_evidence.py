#!/usr/bin/env python3
"""Report recommendation-level skill evidence without calling Gemini."""

from __future__ import annotations

import argparse
import csv
import statistics
import sys
from pathlib import Path
from unittest.mock import patch


PROJECT_ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(PROJECT_ROOT))

import curriculum_generator


DEFAULT_SUBJECT_BANK = PROJECT_ROOT / "canonical_subject_bank.csv"
DEFAULT_SKILL_COVERAGE = PROJECT_ROOT / "skill_coverage.csv"
DEFAULT_COURSE_SKILL_MATCHES = PROJECT_ROOT / "course_to_skill_matches.csv"
THRESHOLDS = (0.45, 0.50, 0.55, 0.60)


def _read_subject_bank(path: Path) -> list[dict[str, object]]:
    if not path.is_file():
        raise FileNotFoundError(f"Canonical subject bank not found: {path}")

    list_fields = ("year_terms", "units", "subject_variants", "source_colleges")
    with path.open("r", encoding="utf-8-sig", newline="") as handle:
        bank = []
        for row in csv.DictReader(handle):
            subject: dict[str, object] = dict(row)
            for field in list_fields:
                subject[field] = [
                    value.strip()
                    for value in str(row.get(field) or "").split(";")
                    if value.strip()
                ]
            bank.append(subject)
    return bank


def _read_skill_coverage(path: Path) -> list[dict[str, object]]:
    if not path.is_file():
        raise FileNotFoundError(f"Skill coverage data not found: {path}")

    with path.open("r", encoding="utf-8-sig", newline="") as handle:
        rows = []
        for row in csv.DictReader(handle):
            if not str(row.get("skill_name") or "").strip():
                continue
            rows.append(
                {
                    "skill_id": str(row.get("skill_id") or "").strip(),
                    "skill_name": str(row["skill_name"]).strip(),
                    "score": float(row.get("score") or 0.0),
                }
            )
    return rows


def _read_course_skill_matches(path: Path) -> list[dict[str, object]]:
    if not path.is_file():
        raise FileNotFoundError(f"Per-course skill matches not found: {path}")

    with path.open("r", encoding="utf-8-sig", newline="") as handle:
        rows = []
        for row in csv.DictReader(handle):
            if not str(row.get("course_title") or "").strip() or not str(row.get("skill_name") or "").strip():
                continue
            rows.append(
                {
                    "course_id": str(row.get("course_id") or "").strip(),
                    "course_title": str(row["course_title"]).strip(),
                    "skill_id": str(row.get("skill_id") or "").strip(),
                    "skill_name": str(row["skill_name"]).strip(),
                    "skill_type": str(row.get("skill_type") or "").strip(),
                    "score": float(row.get("score") or 0.0),
                    "rank": int(row.get("rank") or 0),
                }
            )
    return rows


def _evidence_for_threshold(
    subject_title: str,
    evidence: list[dict[str, object]],
    threshold: float,
) -> list[dict[str, object]]:
    return curriculum_generator._subject_skill_evidence(
        subject_title,
        evidence,
        minimum_score=threshold,
    )


def run_report(program: str, subject_bank_path: Path, coverage_path: Path, course_matches_path: Path) -> int:
    subject_bank = _read_subject_bank(subject_bank_path)
    coverage = _read_skill_coverage(coverage_path)
    course_matches = _read_course_skill_matches(course_matches_path)

    with patch(
        "curriculum_generator.call_gemini_for_curriculum",
        side_effect=curriculum_generator.GeminiGenerationError(
            "Gemini disabled for deterministic offline evidence audit."
        ),
    ):
        subjects = curriculum_generator.generate_program_curriculum(
            program=program,
            prompt=f"Generate a complete {program} curriculum for evidence verification.",
            subject_bank=subject_bank,
            skill_coverage=coverage,
            course_skill_matches=course_matches,
        )

    if not subjects:
        raise RuntimeError(f"No curriculum subjects were generated for program {program!r}.")

    names_by_subject: dict[str, list[str]] = {}
    all_scores: list[float] = []
    evidence_subject_count = 0
    print(f"Program: {program}")
    print("Generation mode: deterministic offline template; Gemini call patched out")
    print(f"Evidence threshold: 0.55 (cosine similarity)")
    print(f"Per-course match rows considered: {len(course_matches)}")

    for subject in subjects:
        title = str(subject.get("subject_title") or "")
        evidence = list(subject.get("skill_evidence") or [])
        names = [str(item.get("skill_name") or "") for item in evidence]
        names_by_subject[title] = names
        if evidence:
            evidence_subject_count += 1
        scores = [float(item.get("score") or 0.0) for item in evidence]
        all_scores.extend(scores)
        if evidence:
            detail = ", ".join(
                f"{item['skill_name']} ({float(item.get('score') or 0.0):.3f})"
                for item in evidence
            )
            print(f"- {title}: {detail}")
        else:
            print(f"- {title}: No closely matched skill evidence found")

    total = len(subjects)
    none_count = total - evidence_subject_count
    distinct_lists = {tuple(names) for names in names_by_subject.values()}
    print("Summary:")
    print(f"  Total recommendations/subjects: {total}")
    print(f"  With evidence: {evidence_subject_count}")
    print(f"  With no evidence: {none_count}")
    if all_scores:
        print(
            "  Similarity scores shown (not competency scores): "
            f"min={min(all_scores):.3f}, "
            f"median={statistics.median(all_scores):.3f}, "
            f"max={max(all_scores):.3f}"
        )
    else:
        print("  Similarity scores shown: none")
    print(f"  Distinct evidence lists: {len(distinct_lists)}")
    if len(distinct_lists) == 1:
        print("  FAILURE: every recommendation has the same evidence list.")

    print("Threshold check:")
    for threshold in THRESHOLDS:
        no_match_count = sum(
            not _evidence_for_threshold(str(subject.get("subject_title") or ""), course_matches, threshold)
            for subject in subjects
        )
        percentage = no_match_count / total * 100
        print(
            f"  {threshold:.2f}: {no_match_count}/{total} no-match "
            f"({percentage:.1f}%)"
        )

    return 1 if len(distinct_lists) == 1 else 0


def main() -> int:
    parser = argparse.ArgumentParser(
        description="Audit per-subject skill evidence using the offline curriculum fallback."
    )
    parser.add_argument("--program", required=True, help="Program label, e.g. BSIT or BSCS")
    parser.add_argument("--subject-bank", type=Path, default=DEFAULT_SUBJECT_BANK)
    parser.add_argument("--skill-coverage", type=Path, default=DEFAULT_SKILL_COVERAGE)
    parser.add_argument("--course-skill-matches", type=Path, default=DEFAULT_COURSE_SKILL_MATCHES)
    args = parser.parse_args()
    return run_report(args.program, args.subject_bank, args.skill_coverage, args.course_skill_matches)


if __name__ == "__main__":
    raise SystemExit(main())
