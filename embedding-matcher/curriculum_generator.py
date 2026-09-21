from __future__ import annotations

import json
import sqlite3
from pathlib import Path
from typing import Any, Dict, Iterable, List

from curriculum_generator_foundation import build_subject_bank, load_course_rows, normalize_subject_name


def _subject_to_year_term(subject: Dict[str, Any], fallback_year: str = "1", fallback_term: str = "1") -> Dict[str, str]:
    year_terms = subject.get("year_terms") or []
    if isinstance(year_terms, str):
        year_terms = [year_terms]
    if year_terms:
        first = str(year_terms[0]).strip()
        if first.lower().startswith("y") and "t" in first.lower():
            year = first.split("T", 1)[0].replace("Y", "").strip()
            term = first.split("T", 1)[1].strip()
            if year and term:
                return {"year": year, "term": term}
    return {"year": fallback_year, "term": fallback_term}


def _pick_relevant_subjects(program: str, subject_bank: Iterable[Dict[str, Any]], limit: int = 6) -> List[Dict[str, Any]]:
    program_key = normalize_subject_name(program)
    chosen: List[Dict[str, Any]] = []
    for subject in subject_bank:
        if not subject.get("program_key"):
            continue
        if normalize_subject_name(str(subject.get("program"))) == program_key or normalize_subject_name(str(subject.get("program_key"))) == program_key:
            chosen.append(subject)
    if not chosen:
        chosen = list(subject_bank)[:limit]
    return chosen[:limit]


def generate_curriculum_draft(
    program: str,
    prompt: str,
    subject_bank: Iterable[Dict[str, Any]],
    limit: int = 6,
) -> List[Dict[str, Any]]:
    relevant = _pick_relevant_subjects(program, subject_bank, limit=limit)
    draft: List[Dict[str, Any]] = []

    for index, subject in enumerate(relevant, start=1):
        year_term = _subject_to_year_term(subject, fallback_year=str((index - 1) // 2 + 1), fallback_term=str(((index - 1) % 2) + 1))
        title = str(subject.get("display_name") or subject.get("canonical_subject") or "Subject").strip()
        canonical = str(subject.get("canonical_subject") or title).strip()
        topics = [
            f"Foundations of {canonical}",
            f"Applied practice in {canonical}",
            "Industry-aligned problem solving",
            "Project work and evaluation",
        ]
        rationale = (
            f"This subject is included to address core skills relevant to {program} and aligns with the recurring pattern observed across multiple colleges."
        )

        draft.append(
            {
                "program": program,
                "year": year_term["year"],
                "term": year_term["term"],
                "subject_code": f"{program.upper()}-{index:02d}",
                "subject_title": title,
                "units": str((subject.get("units") or ["3"])[0]) if isinstance(subject.get("units"), list) else str(subject.get("units") or "3"),
                "prerequisites": "None",
                "topics": topics,
                "rationale": rationale,
                "source_colleges": subject.get("source_colleges") or ["Program benchmark"],
            }
        )

    return draft


def generate_program_curriculum(
    program: str,
    prompt: str,
    subject_bank: Iterable[Dict[str, Any]],
    skill_coverage: Iterable[Dict[str, Any]] | None = None,
    limit: int = 6,
) -> List[Dict[str, Any]]:
    draft = generate_curriculum_draft(program=program, prompt=prompt, subject_bank=subject_bank, limit=limit)

    evidence = list(skill_coverage or [])
    evidence_by_skill = {str(item.get("skill_name") or "").lower(): item for item in evidence if item.get("skill_name")}

    for subject in draft:
        title = str(subject.get("subject_title") or "").lower()
        matching_skills = []
        for skill_name, skill in evidence_by_skill.items():
            if any(keyword in title for keyword in [skill_name.split()[0].lower(), skill_name.lower()]):
                matching_skills.append(
                    {
                        "skill_id": skill.get("skill_id"),
                        "skill_name": skill.get("skill_name"),
                        "score": skill.get("score"),
                    }
                )

        if not matching_skills and evidence:
            matching_skills = [
                {
                    "skill_id": item.get("skill_id"),
                    "skill_name": item.get("skill_name"),
                    "score": item.get("score"),
                }
                for item in evidence[:2]
            ]

        subject["skill_evidence"] = matching_skills
        subject["rationale"] = (
            f"{subject['rationale']} Skill evidence used: "
            + ", ".join(skill["skill_name"] for skill in matching_skills) if matching_skills else "No direct skill match available in retrieval evidence."
        )

    return draft


def save_generated_curriculum(
    draft: Iterable[Dict[str, Any]],
    program: str,
    model_name: str,
    db_path: str | Path = "curriculum_matching.db",
    prompt: str = "",
    status: str = "draft",
) -> int:
    db_file = Path(db_path)
    db_file.parent.mkdir(parents=True, exist_ok=True)

    with sqlite3.connect(db_file) as conn:
        conn.execute(
            """
            CREATE TABLE IF NOT EXISTS generated_curriculum_runs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                program TEXT,
                prompt TEXT,
                model_name TEXT,
                status TEXT,
                generated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                notes TEXT
            )
            """
        )
        conn.execute(
            """
            CREATE TABLE IF NOT EXISTS generated_curriculum_subjects (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                run_id INTEGER,
                program TEXT,
                year TEXT,
                term TEXT,
                subject_code TEXT,
                subject_title TEXT,
                units TEXT,
                prerequisites TEXT,
                topics TEXT,
                rationale TEXT,
                source_colleges TEXT,
                FOREIGN KEY(run_id) REFERENCES generated_curriculum_runs(id)
            )
            """
        )
        conn.execute(
            """
            CREATE TABLE IF NOT EXISTS generated_curriculum_reviews (
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

        run_id = conn.execute(
            """
            INSERT INTO generated_curriculum_runs (program, prompt, model_name, status, notes)
            VALUES (?, ?, ?, ?, ?)
            """,
            (program, prompt, model_name, status, "Generated from retrieval-based subject bank"),
        ).lastrowid

        inserted = 0
        for item in draft:
            conn.execute(
                """
                INSERT INTO generated_curriculum_subjects (
                    run_id, program, year, term, subject_code, subject_title, units,
                    prerequisites, topics, rationale, source_colleges
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                """,
                (
                    run_id,
                    str(item.get("program") or program).strip(),
                    str(item.get("year") or "").strip(),
                    str(item.get("term") or "").strip(),
                    str(item.get("subject_code") or "").strip(),
                    str(item.get("subject_title") or "").strip(),
                    str(item.get("units") or "").strip(),
                    str(item.get("prerequisites") or "None").strip(),
                    json.dumps(item.get("topics") or [], ensure_ascii=False),
                    str(item.get("rationale") or "").strip(),
                    json.dumps(item.get("source_colleges") or [], ensure_ascii=False),
                ),
            )
            inserted += 1

        conn.commit()
        return inserted


def save_review_status(
    db_path: str | Path,
    run_id: int,
    status: str,
    reviewer: str,
    notes: str,
) -> Dict[str, Any]:
    db_file = Path(db_path)
    with sqlite3.connect(db_file) as conn:
        conn.execute(
            """
            CREATE TABLE IF NOT EXISTS generated_curriculum_reviews (
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
        conn.execute(
            """
            INSERT INTO generated_curriculum_reviews (run_id, status, reviewer, notes)
            VALUES (?, ?, ?, ?)
            """,
            (run_id, status, reviewer, notes),
        )
        conn.execute(
            """
            UPDATE generated_curriculum_runs
            SET status = ?, notes = ?
            WHERE id = ?
            """,
            (status, notes, run_id),
        )
        conn.commit()

    return {"run_id": run_id, "status": status, "reviewer": reviewer, "notes": notes}


if __name__ == "__main__":
    rows = load_course_rows(Path(r"W:\Trainers\curriculum-generator-kb\data\curriculum_dataset_with_ids.csv"))
    bank = build_subject_bank(rows)
    generated = generate_curriculum_draft(program="BSIT", prompt="Generate a BSIT curriculum.", subject_bank=bank, limit=6)
    print(json.dumps(generated, indent=2, ensure_ascii=False))
