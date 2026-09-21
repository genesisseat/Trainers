from __future__ import annotations

import csv
import json
import os
import re
from collections import defaultdict
from pathlib import Path
from typing import Any, Dict, Iterable, List

import torch
from sentence_transformers import SentenceTransformer

ROOT = Path(__file__).resolve().parent
DATASET_PATH = Path(r"W:\Trainers\curriculum-generator-kb\data\curriculum_dataset_with_ids.csv")
HF_HOME = Path(os.environ.get("HF_HOME", ROOT / "hf_cache"))
HF_HOME.mkdir(parents=True, exist_ok=True)
os.environ["HF_HOME"] = str(HF_HOME)
DEFAULT_EMBEDDING_MODEL = "BAAI/bge-small-en-v1.5"


def normalize_subject_name(value: str) -> str:
    text = (value or "").strip().lower()
    text = text.replace("&", " and ")
    text = re.sub(r"[^a-z0-9]+", " ", text)
    text = re.sub(r"\s+", " ", text).strip()
    return text


def _load_embedding_model(model_name: str = DEFAULT_EMBEDDING_MODEL) -> SentenceTransformer:
    device = "cuda" if torch.cuda.is_available() else "cpu"
    return SentenceTransformer(model_name, device=device)


def cluster_subject_variants(rows: Iterable[Dict[str, Any]], model_name: str = DEFAULT_EMBEDDING_MODEL, threshold: float = 0.82) -> List[Dict[str, Any]]:
    unique_variants: List[Dict[str, str]] = []
    seen: set[str] = set()

    for row in rows:
        raw_name = str(row.get("course") or "").strip()
        if not raw_name:
            continue
        normalized = normalize_subject_name(raw_name)
        if not normalized or normalized in seen:
            continue
        seen.add(normalized)
        unique_variants.append(
            {
                "normalized": normalized,
                "display_name": raw_name,
                "program": str(row.get("program") or "").strip(),
                "university": str(row.get("university") or "").strip(),
            }
        )

    if not unique_variants:
        return []

    model = _load_embedding_model(model_name)
    names = [item["display_name"] for item in unique_variants]
    embeddings = model.encode(names, convert_to_tensor=True, normalize_embeddings=True, show_progress_bar=False)
    if not isinstance(embeddings, torch.Tensor):
        embeddings = torch.as_tensor(embeddings)
    embeddings = embeddings.cpu()

    assigned = [False] * len(unique_variants)
    clusters: List[Dict[str, Any]] = []

    for index, item in enumerate(unique_variants):
        if assigned[index]:
            continue

        cluster = {
            "canonical_subject": item["normalized"],
            "subject_variants": [item["display_name"]],
            "source_colleges": {item["university"]} if item["university"] else set(),
            "programs": {item["program"]} if item["program"] else set(),
        }
        assigned[index] = True

        for candidate_index in range(index + 1, len(unique_variants)):
            if assigned[candidate_index]:
                continue
            score = float(torch.matmul(embeddings[index], embeddings[candidate_index]).item())
            if score >= threshold:
                candidate = unique_variants[candidate_index]
                cluster["subject_variants"].append(candidate["display_name"])
                cluster["source_colleges"].add(candidate["university"])
                cluster["programs"].add(candidate["program"])
                assigned[candidate_index] = True

        cluster["subject_variants"] = sorted({variant for variant in cluster["subject_variants"]})
        cluster["source_colleges"] = sorted(cluster["source_colleges"])
        cluster["programs"] = sorted(cluster["programs"])
        clusters.append(cluster)

    return clusters


def _clean_course_row(row: Dict[str, Any]) -> Dict[str, Any]:
    program = str(row.get("program", "")).strip()
    course = str(row.get("course", "")).strip()
    classification = str(row.get("classification", "")).strip()
    year = str(row.get("year", "")).strip()
    term = str(row.get("term", "")).strip()
    units = str(row.get("units", "")).strip()
    return {
        "program": program,
        "program_key": normalize_subject_name(program),
        "university": str(row.get("university", "")).strip(),
        "course": course,
        "canonical_subject": normalize_subject_name(course),
        "classification": classification,
        "year": year,
        "term": term,
        "units": units,
    }


def build_subject_bank(rows: Iterable[Dict[str, Any]], model_name: str = DEFAULT_EMBEDDING_MODEL) -> List[Dict[str, Any]]:
    rows = list(rows)
    clusters = cluster_subject_variants(rows, model_name=model_name)
    clustered_name_map: Dict[str, str] = {}
    for cluster in clusters:
        canonical = cluster["canonical_subject"]
        for variant in cluster["subject_variants"]:
            clustered_name_map[normalize_subject_name(variant)] = canonical

    groups: Dict[str, Dict[str, Any]] = {}

    for row in rows:
        cleaned = _clean_course_row(row)
        program_key = cleaned["program_key"]
        normalized_name = normalize_subject_name(cleaned["course"])
        subject_key = clustered_name_map.get(normalized_name, cleaned["canonical_subject"])
        group_key = f"{program_key}:{subject_key}"

        if group_key not in groups:
            groups[group_key] = {
                "program": cleaned["program"],
                "program_key": program_key,
                "canonical_subject": subject_key,
                "display_name": cleaned["course"],
                "subject_variants": set(),
                "source_colleges": set(),
                "classification": cleaned["classification"],
                "year_terms": set(),
                "units": {cleaned["units"]} if cleaned["units"] else set(),
            }

        groups[group_key]["subject_variants"].add(cleaned["course"])
        groups[group_key]["source_colleges"].add(cleaned["university"])
        groups[group_key]["year_terms"].add(f"Y{cleaned['year']}T{cleaned['term']}")
        if cleaned["units"]:
            groups[group_key]["units"].add(cleaned["units"])

    bank: List[Dict[str, Any]] = []
    for item in groups.values():
        bank.append(
            {
                "program": item["program"],
                "program_key": item["program_key"],
                "canonical_subject": item["canonical_subject"],
                "display_name": item["display_name"],
                "subject_variants": sorted(item["subject_variants"]),
                "source_colleges": sorted(item["source_colleges"]),
                "classification": item["classification"],
                "year_terms": sorted(item["year_terms"]),
                "units": sorted(item["units"]),
            }
        )

    return sorted(bank, key=lambda row: (row["program_key"], row["canonical_subject"]))


def load_course_rows(csv_path: Path) -> List[Dict[str, Any]]:
    rows: List[Dict[str, Any]] = []
    with csv_path.open("r", encoding="utf-8", newline="") as handle:
        reader = csv.DictReader(handle)
        for row in reader:
            if not row.get("course"):
                continue
            rows.append(row)
    return rows


def save_subject_bank(bank: List[Dict[str, Any]], output_path: Path) -> None:
    with output_path.open("w", encoding="utf-8", newline="") as handle:
        writer = csv.DictWriter(
            handle,
            fieldnames=[
                "program",
                "program_key",
                "canonical_subject",
                "display_name",
                "classification",
                "year_terms",
                "units",
                "subject_variants",
                "source_colleges",
            ],
        )
        writer.writeheader()
        for row in bank:
            writer.writerow(
                {
                    "program": row["program"],
                    "program_key": row["program_key"],
                    "canonical_subject": row["canonical_subject"],
                    "display_name": row["display_name"],
                    "classification": row["classification"],
                    "year_terms": "; ".join(row["year_terms"]),
                    "units": "; ".join(row["units"]),
                    "subject_variants": "; ".join(row["subject_variants"]),
                    "source_colleges": "; ".join(row["source_colleges"]),
                }
            )


def main() -> None:
    rows = load_course_rows(DATASET_PATH)
    bank = build_subject_bank(rows)
    output_path = ROOT / "canonical_subject_bank.csv"
    save_subject_bank(bank, output_path)
    print(f"Canonical subject bank written to: {output_path}")
    print(f"Rows generated: {len(bank)}")


if __name__ == "__main__":
    main()
