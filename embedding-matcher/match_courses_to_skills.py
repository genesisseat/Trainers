#!/usr/bin/env python3
"""Match curriculum courses to industry skills using pretrained sentence embeddings.

This script reads the course dataset and the industry skills markdown, embeds both
sets with a sentence-transformer model, and writes:
    - course_to_skill_matches.csv
    - skill_coverage.csv

Usage examples:
    python match_courses_to_skills.py --model all-MiniLM-L6-v2
    python match_courses_to_skills.py --model BAAI/bge-small-en-v1.5
    python match_courses_to_skills.py --model intfloat/e5-base-v2
    python match_courses_to_skills.py --limit 20 --top-k 5
"""

from __future__ import annotations

import argparse
import csv
import os
import re
from pathlib import Path
from typing import Iterable, List, Sequence

import pandas as pd
import torch
from sentence_transformers import SentenceTransformer


ROOT = Path(__file__).resolve().parent
WORKSPACE = ROOT.parent
COURSES_CSV = WORKSPACE / "curriculum-generator-kb" / "data" / "curriculum_dataset_with_ids.csv"
SKILLS_MD = WORKSPACE / "curriculum-generator-kb" / "03_industry_skills_data.md"
OUTPUT_DIR = ROOT
HF_HOME = Path(os.environ.get("HF_HOME", ROOT / "hf_cache"))
HF_HOME.mkdir(parents=True, exist_ok=True)
os.environ["HF_HOME"] = str(HF_HOME)


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Match curriculum courses to industry skills with sentence embeddings.")
    parser.add_argument("--model", default="sentence-transformers/all-MiniLM-L6-v2", help="SentenceTransformer model name or HF repo.")
    parser.add_argument("--top-k", type=int, default=5, help="Number of skills to keep per course.")
    parser.add_argument("--limit", type=int, default=None, help="Optional limit for faster testing on a subset.")
    parser.add_argument("--courses-csv", type=Path, default=COURSES_CSV, help="Path to the course CSV dataset.")
    parser.add_argument("--skills-md", type=Path, default=SKILLS_MD, help="Path to the markdown skill dataset.")
    parser.add_argument("--output-dir", type=Path, default=OUTPUT_DIR, help="Directory for generated CSV outputs.")
    return parser.parse_args()


def resolve_model_name(raw: str) -> str:
    value = raw.strip()
    lower = value.lower()
    if "bge-small" in lower:
        return "BAAI/bge-small-en-v1.5"
    if "bge-base" in lower:
        return "BAAI/bge-base-en-v1.5"
    if "minilm" in lower:
        return "sentence-transformers/all-MiniLM-L6-v2"
    if "e5" in lower:
        return "intfloat/e5-base-v2"
    return value


def build_course_rows(csv_path: Path) -> List[dict]:
    frame = pd.read_csv(csv_path)
    needed = ["id", "university", "program", "curriculum_year", "course", "classification"]
    missing = [col for col in needed if col not in frame.columns]
    if missing:
        raise ValueError(f"Course CSV is missing required columns: {missing}")

    rows: List[dict] = []
    for _, row in frame.iterrows():
        course_id = str(row["id"]).strip()
        title = str(row["course"]).strip()
        if not course_id or not title:
            continue

        metadata_parts = [
            row.get("university"),
            row.get("program"),
            row.get("curriculum_year"),
            row.get("classification"),
        ]
        metadata = " | ".join(part for part in (str(p).strip() for p in metadata_parts) if part)
        description = f"{title}. {metadata}" if metadata else title
        rows.append(
            {
                "id": course_id,
                "title": title,
                "description": metadata,
                "text": description,
            }
        )
    return rows


def parse_skill_rows(md_path: Path) -> List[dict]:
    if not md_path.exists():
        raise FileNotFoundError(f"Skill markdown not found: {md_path}")

    rows: List[dict] = []
    for line in md_path.read_text(encoding="utf-8", errors="ignore").splitlines():
        l = line.strip()
        if not l.startswith("|") or "IS-" not in l:
            continue
        cells = [cell.strip() for cell in l.strip("|").split("|")]
        if len(cells) < 11:
            continue
        first = cells[0]
        if not re.match(r"^IS-\d+$", first):
            continue

        skill_id = first
        skill_name = cells[1]
        skill_type = cells[2]
        notes = " ".join(cells[10:]).strip()
        rows.append(
            {
                "id": skill_id,
                "name": skill_name,
                "type": skill_type,
                "notes": notes,
                "text": f"{skill_name}. {skill_type}. {notes}" if notes else skill_name,
            }
        )

    if not rows:
        raise ValueError(f"No skill rows could be parsed from {md_path}")

    dedup: dict[str, dict] = {}
    for row in rows:
        dedup.setdefault(row["id"], row)
    return list(dedup.values())


def prefix_text(model_name: str, text: str) -> str:
    if "e5" in model_name.lower():
        return f"passage: {text}"
    return text


def embed_texts(model: SentenceTransformer, model_name: str, texts: Sequence[str]) -> torch.Tensor:
    model_name_lower = model_name.lower()
    prepared = [prefix_text(model_name_lower, text) for text in texts]
    embeddings = model.encode(
        prepared,
        convert_to_tensor=True,
        normalize_embeddings=True,
        batch_size=32,
        show_progress_bar=False,
    )
    if not isinstance(embeddings, torch.Tensor):
        embeddings = torch.as_tensor(embeddings)
    return embeddings.cpu()


def compute_matches(course_rows: Sequence[dict], skill_rows: Sequence[dict], model_name: str, top_k: int, limit: int | None = None):
    if limit is not None:
        course_rows = course_rows[:limit]
        skill_rows = skill_rows[:]

    course_texts = [row["text"] for row in course_rows]
    skill_texts = [row["text"] for row in skill_rows]

    device = "cuda" if torch.cuda.is_available() else "cpu"
    model = SentenceTransformer(model_name, device=device)
    course_embeddings = embed_texts(model, model_name, course_texts)
    skill_embeddings = embed_texts(model, model_name, skill_texts)

    # Cosine similarity on normalized embeddings: dot = cosine similarity
    similarities = torch.matmul(course_embeddings, skill_embeddings.T)
    course_scores = similarities.numpy()

    course_matches: List[dict] = []
    for i, course in enumerate(course_rows):
        order = sorted(range(len(skill_rows)), key=lambda j: course_scores[i, j], reverse=True)[:top_k]
        for rank, j in enumerate(order, start=1):
            score = float(course_scores[i, j])
            course_matches.append(
                {
                    "course_id": course["id"],
                    "course_title": course["title"],
                    "skill_id": skill_rows[j]["id"],
                    "skill_name": skill_rows[j]["name"],
                    "skill_type": skill_rows[j]["type"],
                    "score": score,
                    "rank": rank,
                }
            )

    coverage_rows: List[dict] = []
    for j, skill in enumerate(skill_rows):
        best_course_index = int(torch.argmax(similarities[:, j]).item())
        score = float(course_scores[best_course_index, j])
        coverage_rows.append(
            {
                "skill_id": skill["id"],
                "skill_name": skill["name"],
                "skill_type": skill["type"],
                "best_course_id": course_rows[best_course_index]["id"],
                "best_course_title": course_rows[best_course_index]["title"],
                "score": score,
            }
        )

    coverage_rows.sort(key=lambda row: row["score"])
    return course_matches, coverage_rows


def write_csv(path: Path, fieldnames: Sequence[str], rows: Iterable[dict]) -> None:
    with path.open("w", newline="", encoding="utf-8") as handle:
        writer = csv.DictWriter(handle, fieldnames=fieldnames)
        writer.writeheader()
        for row in rows:
            writer.writerow({key: row.get(key, "") for key in fieldnames})


def main() -> None:
    args = parse_args()
    model_name = resolve_model_name(args.model)
    output_dir = args.output_dir
    output_dir.mkdir(parents=True, exist_ok=True)

    course_rows = build_course_rows(args.courses_csv)
    skill_rows = parse_skill_rows(args.skills_md)

    course_matches, coverage_rows = compute_matches(
        course_rows,
        skill_rows,
        model_name=model_name,
        top_k=args.top_k,
        limit=args.limit,
    )

    write_csv(
        output_dir / "course_to_skill_matches.csv",
        ["course_id", "course_title", "skill_id", "skill_name", "skill_type", "score", "rank"],
        course_matches,
    )
    write_csv(
        output_dir / "skill_coverage.csv",
        ["skill_id", "skill_name", "skill_type", "best_course_id", "best_course_title", "score"],
        coverage_rows,
    )

    print(f"Loaded {len(course_rows)} courses and {len(skill_rows)} skills.")
    print(f"Model: {model_name}")
    print(f"Wrote: {output_dir / 'course_to_skill_matches.csv'}")
    print(f"Wrote: {output_dir / 'skill_coverage.csv'}")


if __name__ == "__main__":
    main()
