#!/usr/bin/env python3
"""User-friendly entry point for curriculum-to-skill matching.

This script asks for the model and matching settings, then runs the embedding-based
matching pipeline and writes the results to CSV files in the current project folder.

Example:
    python user_operations.py --model BAAI/bge-small-en-v1.5 --top-k 5
    python user_operations.py --model all-MiniLM-L6-v2 --limit 20 --top-k 3
"""

from __future__ import annotations

import argparse
import csv
import json
from pathlib import Path

from curriculum_generator import generate_program_curriculum, save_generated_curriculum
from curriculum_generator_foundation import build_subject_bank, load_course_rows
from match_courses_to_skills import (
    build_course_rows,
    compute_matches,
    parse_skill_rows,
    resolve_model_name,
    write_csv,
)

# This file exists in the same folder as the main script, so we reuse the constants
# from the working implementation when available.

PROJECT_ROOT = Path(__file__).resolve().parent
WORKSPACE = PROJECT_ROOT.parent
COURSES_CSV = WORKSPACE / "curriculum-generator-kb" / "data" / "curriculum_dataset_with_ids.csv"
SKILLS_MD = WORKSPACE / "curriculum-generator-kb" / "03_industry_skills_data.md"


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Run a user-friendly curriculum skill matching or curriculum generation operation.")
    parser.add_argument("--model", default="BAAI/bge-small-en-v1.5", help="Embedding model: all-MiniLM-L6-v2, BAAI/bge-small-en-v1.5, intfloat/e5-base-v2")
    parser.add_argument("--top-k", type=int, default=5, help="Number of skill matches to keep per course.")
    parser.add_argument("--limit", type=int, default=None, help="Optional subset size for quick testing.")
    parser.add_argument("--courses-csv", type=Path, default=COURSES_CSV, help="Course CSV input file.")
    parser.add_argument("--skills-md", type=Path, default=SKILLS_MD, help="Industry skill markdown input file.")
    parser.add_argument("--skill-coverage", type=Path, default=PROJECT_ROOT / "skill_coverage.csv", help="Existing skill coverage output used as generation evidence.")
    parser.add_argument("--output-dir", type=Path, default=PROJECT_ROOT, help="Folder where CSV results are written.")
    parser.add_argument("--output-db", type=Path, default=PROJECT_ROOT / "curriculum_matching.db", help="SQLite database path for generated curriculum drafts.")
    parser.add_argument("--generate", action="store_true", help="Generate a structured curriculum draft from the canonical subject bank and skill evidence.")
    parser.add_argument("--review", action="store_true", help="Review an existing generated curriculum run and update the review status.")
    parser.add_argument("--run-id", type=int, default=None, help="Generated curriculum run ID to review.")
    parser.add_argument("--review-status", default="approved", choices=["draft", "approved", "rejected", "needs_revision"], help="Review decision for a generated curriculum run.")
    parser.add_argument("--reviewer", default="admin", help="Reviewer name recording the curriculum review.")
    parser.add_argument("--review-notes", default="Reviewed in CLI.", help="Review notes for the curriculum draft.")
    parser.add_argument("--program", default="BSIT", help="Program to generate or analyze, such as BSIT, BSCS, or BSEMC.")
    parser.add_argument("--prompt", default="Generate a BSIT curriculum focused on software development, databases, and networking.", help="User prompt to guide the curriculum-generation draft.")
    return parser.parse_args()


def _load_skill_coverage_rows(path: Path):
    if not path.exists():
        return []

    rows = []
    with path.open("r", newline="", encoding="utf-8") as handle:
        reader = csv.DictReader(handle)
        for row in reader:
            if not row.get("skill_name"):
                continue
            rows.append(
                {
                    "skill_id": row.get("skill_id", ""),
                    "skill_name": row.get("skill_name", ""),
                    "score": float(row.get("score", 0) or 0),
                }
            )
    return rows


def main() -> None:
    args = parse_args()
    model_name = resolve_model_name(args.model)
    output_dir = args.output_dir
    output_dir.mkdir(parents=True, exist_ok=True)

    if args.review:
        if args.run_id is None:
            raise ValueError("--run-id is required when --review is used.")
        review = save_review_status(
            db_path=args.output_db,
            run_id=args.run_id,
            status=args.review_status,
            reviewer=args.reviewer,
            notes=args.review_notes,
        )
        print("\n=== Curriculum Review ===")
        print(json.dumps(review, indent=2, ensure_ascii=False))
        return

    if args.generate:
        subject_bank = build_subject_bank(load_course_rows(args.courses_csv))
        skill_coverage = _load_skill_coverage_rows(args.skill_coverage)
        generated = generate_program_curriculum(
            program=args.program,
            prompt=args.prompt,
            subject_bank=subject_bank,
            skill_coverage=skill_coverage,
            limit=args.limit or 6,
        )
        saved_count = save_generated_curriculum(
            generated,
            program=args.program,
            model_name=model_name,
            db_path=args.output_db,
            prompt=args.prompt,
        )

        print("\n=== Curriculum Generation ===")
        print(f"Program: {args.program}")
        print(f"Prompt: {args.prompt}")
        print(f"Model: {model_name}")
        print(f"Database: {args.output_db}")
        print(f"Saved subjects: {saved_count}")
        print(json.dumps(generated[:3], indent=2, ensure_ascii=False))
        return

    print("\n=== Curriculum Skill Matching ===")
    print(f"Model: {model_name}")
    print(f"Top-K per course: {args.top_k}")
    print(f"Limit: {args.limit}")
    print(f"Courses CSV: {args.courses_csv}")
    print(f"Skills MD: {args.skills_md}")
    print(f"Output folder: {output_dir}")

    course_rows = build_course_rows(args.courses_csv)
    skill_rows = parse_skill_rows(args.skills_md)

    if args.limit is not None:
        course_rows = course_rows[: args.limit]

    course_matches, coverage_rows = compute_matches(
        course_rows,
        skill_rows,
        model_name=model_name,
        top_k=args.top_k,
        limit=args.limit,
    )

    course_out = output_dir / "course_to_skill_matches.csv"
    skill_out = output_dir / "skill_coverage.csv"

    write_csv(
        course_out,
        ["course_id", "course_title", "skill_id", "skill_name", "skill_type", "score", "rank"],
        course_matches,
    )
    write_csv(
        skill_out,
        ["skill_id", "skill_name", "skill_type", "best_course_id", "best_course_title", "score"],
        coverage_rows,
    )

    print("\nInput summary:")
    print(f"- Courses loaded: {len(course_rows)}")
    print(f"- Skills loaded: {len(skill_rows)}")
    print("\nOutput summary:")
    print(f"- Course matches: {course_out}")
    print(f"- Skill coverage: {skill_out}")
    print("\nDone. Review skill_coverage.csv to inspect the weakest curriculum coverage.")


if __name__ == "__main__":
    main()
