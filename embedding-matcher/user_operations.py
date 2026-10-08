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

from curriculum_generator import (
    chat_about_curriculum,
    chat_about_enhancement_review,
    configure_web_actor_api_key,
    enhance_user_curriculum,
    generate_enhanced_curriculum,
    generate_program_curriculum,
    save_generated_curriculum,
    save_enhancement_report,
)
from curriculum_generator_foundation import build_subject_bank, load_course_rows
from job_postings import attach_job_postings
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
JOB_POSTINGS_CSV = WORKSPACE / "curriculum-generator-kb" / "data" / "job_postings.csv"
JOB_TOPIC_MAP_CSV = WORKSPACE / "curriculum-generator-kb" / "data" / "job_topic_map.csv"


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Run a user-friendly curriculum skill matching or curriculum generation operation.")
    parser.add_argument("--model", default="BAAI/bge-small-en-v1.5", help="Embedding model: all-MiniLM-L6-v2, BAAI/bge-small-en-v1.5, intfloat/e5-base-v2")
    parser.add_argument("--top-k", type=int, default=5, help="Number of skill matches to keep per course.")
    parser.add_argument("--limit", type=int, default=None, help="Optional subset size for quick testing.")
    parser.add_argument("--courses-csv", type=Path, default=COURSES_CSV, help="Course CSV input file.")
    parser.add_argument("--skills-md", type=Path, default=SKILLS_MD, help="Industry skill markdown input file.")
    parser.add_argument("--skill-coverage", type=Path, default=PROJECT_ROOT / "skill_coverage.csv", help="Existing skill coverage output used as generation evidence.")
    parser.add_argument("--course-skill-matches", type=Path, default=PROJECT_ROOT / "course_to_skill_matches.csv", help="Per-course skill matches used for recommendation-level evidence.")
    parser.add_argument("--output-dir", type=Path, default=PROJECT_ROOT, help="Folder where CSV results are written.")
    parser.add_argument("--output-db", type=Path, default=PROJECT_ROOT / "curriculum_matching.db", help="SQLite database path for generated curriculum drafts.")
    parser.add_argument("--generate", action="store_true", help="Generate a structured curriculum draft from the canonical subject bank and skill evidence.")
    parser.add_argument("--enhance", action="store_true", help="Complete a user-provided partial curriculum.")
    parser.add_argument("--specialization", default="", help="Optional specialization hint for enhancement.")
    parser.add_argument("--years", default="", help="Comma-separated target years for curriculum enhancement.")
    parser.add_argument("--user-subjects-json", type=Path, help="JSON file containing user-provided subjects.")
    parser.add_argument("--chat", action="store_true", help="Ask a follow-up question about an existing generated curriculum run.")
    parser.add_argument("--enhancement-chat", action="store_true", help="Ask a question about a saved enhancement review.")
    parser.add_argument("--run-id", type=int, default=None, help="Existing generated curriculum run ID.")
    parser.add_argument("--actor-user-id", type=int, default=None, help="Authenticated web user ID for persisted attribution.")
    parser.add_argument("--actor-username", default=None, help="Authenticated web username for persisted attribution.")
    parser.add_argument(
        "--admin-access-policy",
        choices=("own_only", "all_owned"),
        default="own_only",
        help="Admin run access policy supplied by the authenticated PHP caller.",
    )
    parser.add_argument("--import-guest-draft-json", type=Path, help="Import a session-only guest draft after signup.")
    parser.add_argument("--message", default="", help="Chat question or edit request for an existing curriculum run.")
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


def _load_course_skill_matches(path: Path):
    if not path.exists():
        return []

    rows = []
    with path.open("r", newline="", encoding="utf-8-sig") as handle:
        reader = csv.DictReader(handle)
        for row in reader:
            if not row.get("course_title") or not row.get("skill_name"):
                continue
            rows.append(
                {
                    "course_id": row.get("course_id", ""),
                    "course_title": row.get("course_title", ""),
                    "skill_id": row.get("skill_id", ""),
                    "skill_name": row.get("skill_name", ""),
                    "skill_type": row.get("skill_type", ""),
                    "score": float(row.get("score", 0) or 0),
                    "rank": int(row.get("rank", 0) or 0),
                }
            )
    return rows


def main() -> None:
    args = parse_args()
    if args.import_guest_draft_json is not None:
        if args.actor_user_id is None or args.actor_user_id < 1 or not args.actor_username:
            raise ValueError("A valid authenticated user ID and username are required to import a guest draft.")
        with args.import_guest_draft_json.open("r", encoding="utf-8-sig") as handle:
            guest_draft = json.load(handle)
        if not isinstance(guest_draft, dict):
            raise ValueError("The guest draft file must contain a JSON object.")

        draft_type = guest_draft.get("type")
        program = str(guest_draft.get("program") or "BSIT")
        model_name = str(guest_draft.get("model_name") or "gemini-3.5-flash-lite")
        prompt = str(guest_draft.get("prompt") or "")
        attribution = {
            "created_by_user_id": args.actor_user_id,
            "created_by_username": args.actor_username,
        }
        if draft_type == "generated":
            draft = guest_draft.get("draft")
            if not isinstance(draft, list):
                raise ValueError("The generated guest draft must contain a subject list.")
            run_id = save_generated_curriculum(
                draft,
                program=program,
                model_name=model_name,
                db_path=args.output_db,
                prompt=prompt,
                status="draft",
                return_run_id=True,
                **attribution,
            )
        elif draft_type == "enhanced":
            report = guest_draft.get("report")
            enhanced_curriculum = guest_draft.get("enhanced_curriculum")
            user_subjects = guest_draft.get("user_subjects", [])
            if not isinstance(report, dict) or not isinstance(enhanced_curriculum, list) or not isinstance(user_subjects, list):
                raise ValueError("The enhanced guest draft is incomplete.")
            run_id = save_enhancement_report(
                report,
                enhanced_curriculum,
                user_subjects,
                program=program,
                model_name=model_name,
                db_path=args.output_db,
                prompt=prompt,
                **attribution,
            )
        else:
            raise ValueError("The guest draft type is not supported.")

        print(json.dumps({"run_id": run_id, "type": draft_type}))
        return

    if args.actor_user_id is not None and (
        args.generate or args.enhance or args.chat or args.enhancement_chat
    ):
        configure_web_actor_api_key(args.output_db, args.actor_user_id)

    model_name = resolve_model_name(args.model)
    output_dir = args.output_dir
    output_dir.mkdir(parents=True, exist_ok=True)

    if args.chat:
        if args.run_id is None:
            raise ValueError("--run-id is required when --chat is used.")
        if not args.message.strip():
            raise ValueError("--message is required when --chat is used.")
        answer = chat_about_curriculum(
            db_path=args.output_db,
            run_id=args.run_id,
            message=args.message,
            sender_user_id=args.actor_user_id,
            sender_username=args.actor_username,
            admin_access_policy=args.admin_access_policy,
        )
        print("\n=== Curriculum Chat ===")
        print(f"Run ID: {args.run_id}")
        print(answer)
        return

    if args.enhancement_chat:
        if args.run_id is None:
            raise ValueError("--run-id is required when --enhancement-chat is used.")
        if not args.message.strip():
            raise ValueError("--message is required when --enhancement-chat is used.")
        answer = chat_about_enhancement_review(
            db_path=args.output_db,
            run_id=args.run_id,
            message=args.message,
            sender_user_id=args.actor_user_id,
            sender_username=args.actor_username,
            admin_access_policy=args.admin_access_policy,
        )
        print("\n=== Enhancement Review Chat ===")
        print(f"Run ID: {args.run_id}")
        print(answer)
        return

    if args.generate:
        subject_bank = build_subject_bank(load_course_rows(args.courses_csv))
        skill_coverage = _load_skill_coverage_rows(args.skill_coverage)
        course_skill_matches = _load_course_skill_matches(args.course_skill_matches)
        generated = generate_program_curriculum(
            program=args.program,
            prompt=args.prompt,
            subject_bank=subject_bank,
            skill_coverage=skill_coverage,
            limit=args.limit or 6,
            course_skill_matches=course_skill_matches,
        )
        saved_count = save_generated_curriculum(
            generated,
            program=args.program,
            model_name=model_name,
            db_path=args.output_db,
            prompt=args.prompt,
            created_by_user_id=args.actor_user_id,
            created_by_username=args.actor_username,
        )

        print("\n=== Curriculum Generation ===")
        print(f"Program: {args.program}")
        print(f"Prompt: {args.prompt}")
        print(f"Model: {model_name}")
        print(f"Database: {args.output_db}")
        print(f"Saved subjects: {saved_count}")
        print(json.dumps(generated[:3], indent=2, ensure_ascii=False))
        return

    if args.enhance:
        if args.user_subjects_json is None:
            raise ValueError("--user-subjects-json is required when --enhance is used.")
        with args.user_subjects_json.open("r", encoding="utf-8") as handle:
            user_subjects = json.load(handle)

        populated_years = sorted({str(subject.get("year") or "1").strip() for subject in user_subjects})
        selected_years = sorted(set(filter(None, (year.strip() for year in args.years.split(","))))) if args.years else populated_years
        if not selected_years or any(year not in {"1", "2", "3", "4"} for year in selected_years):
            raise ValueError("--years must contain one or more years from 1 through 4.")
        missing_years = sorted(set(selected_years) - set(populated_years))
        if missing_years:
            raise ValueError("Add at least one submitted subject for selected year(s): " + ", ".join(missing_years))
        user_subjects = [
            subject for subject in user_subjects
            if str(subject.get("year") or "1").strip() in selected_years
        ]

        course_rows = load_course_rows(args.courses_csv)
        subject_bank = build_subject_bank(course_rows)
        skill_coverage = _load_skill_coverage_rows(args.skill_coverage)
        course_skill_matches = _load_course_skill_matches(args.course_skill_matches)
        enhancement_report = enhance_user_curriculum(
            program=args.program,
            specialization=args.specialization,
            prompt=args.prompt,
            user_subjects=user_subjects,
            subject_bank=subject_bank,
            skill_coverage=skill_coverage,
            selected_years=selected_years,
            course_skill_matches=course_skill_matches,
        )
        attach_job_postings(
            enhancement_report,
            JOB_POSTINGS_CSV,
            JOB_TOPIC_MAP_CSV,
        )
        enhanced_curriculum = generate_enhanced_curriculum(
            program=args.program,
            specialization=args.specialization,
            prompt=args.prompt,
            user_subjects=user_subjects,
            enhancement_report=enhancement_report,
            subject_bank=subject_bank,
            skill_coverage=skill_coverage,
            course_rows=course_rows,
            selected_years=selected_years,
        )
        run_id = save_enhancement_report(
            enhancement_report,
            enhanced_curriculum=enhanced_curriculum,
            user_subjects=user_subjects,
            program=args.program,
            model_name=model_name,
            db_path=args.output_db,
            prompt=args.prompt,
            created_by_user_id=args.actor_user_id,
            created_by_username=args.actor_username,
        )

        print("\n=== Curriculum Enhancement ===")
        print(f"Program: {args.program}")
        print(f"Specialization: {args.specialization}")
        print(f"Prompt: {args.prompt}")
        print(f"Model: {model_name}")
        print(f"Database: {args.output_db}")
        print(f"Run ID: {run_id}")
        print(f"Reviewed subjects: {len(enhancement_report.get('subjects', []))}")
        print(f"Completed draft subjects: {len(enhanced_curriculum)}")
        print(json.dumps(enhancement_report, indent=2, ensure_ascii=False))
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
