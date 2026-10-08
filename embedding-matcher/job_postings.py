"""Read-only lookup for curated and imported topic-level job links."""

from __future__ import annotations

import csv
import re
import sys
from datetime import date
from pathlib import Path
from typing import Any
from urllib.parse import unquote, urlsplit

TOPIC_MAP_COLUMNS = {"topic", "keyword"}
POSTING_COLUMNS = {
    "topic",
    "posting_url",
    "posting_title",
    "employer",
    "date_retrieved",
    "notes",
}
MAX_POSTINGS_PER_RECOMMENDATION = 5


def normalize_topic_text(value: str) -> str:
    text = str(value or "").lower().replace("&", " and ")
    text = re.sub(r"[^a-z0-9]+", " ", text)
    return re.sub(r"\s+", " ", text).strip()


def _log_skip(path: Path, row_number: int, reason: str) -> None:
    print(f"Skipping {path} row {row_number}: {reason}", file=sys.stderr)


def validate_http_url(value: str) -> bool:
    raw_url = str(value or "").strip()
    if not raw_url or any(character.isspace() for character in raw_url):
        return False
    try:
        parsed = urlsplit(raw_url)
        if parsed.scheme.lower() not in {"http", "https"} or not parsed.hostname:
            return False
        parsed.port
    except ValueError:
        return False
    return True


def validate_posting_url(value: str) -> bool:
    raw_url = str(value or "").strip()
    if not validate_http_url(raw_url):
        return False
    parsed = urlsplit(raw_url)
    searchable = unquote(raw_url).lower()
    path = unquote(parsed.path).lower()
    query = unquote(parsed.query).lower()
    if "search" in searchable or "/jobs/search" in path:
        return False
    if re.search(r"(?:^|&)q=", query) and "job" in path:
        return False
    return True


def _posting_url_key(url: str) -> tuple[str, str, int | None, str, str, str]:
    parsed = urlsplit(url)
    return (
        parsed.scheme.lower(),
        (parsed.hostname or "").casefold(),
        parsed.port,
        parsed.path,
        parsed.query,
        parsed.fragment,
    )


def _read_csv(path: Path, required_columns: set[str]) -> list[dict[str, str]] | None:
    try:
        with path.open("r", encoding="utf-8-sig", newline="") as handle:
            reader = csv.reader(handle)
            raw_headers = next(reader, None)
            headers = [header.strip() for header in raw_headers] if raw_headers else []
            fieldnames = set(headers)
            if (
                not headers
                or any(not header for header in headers)
                or len(headers) != len(fieldnames)
                or not required_columns.issubset(fieldnames)
            ):
                print(
                    f"Job-posting lookup disabled: {path} has missing or invalid headers.",
                    file=sys.stderr,
                )
                return None
            rows: list[dict[str, str]] = []
            for row in reader:
                row_number = reader.line_num
                if not row or all(not value.strip() for value in row):
                    _log_skip(path, row_number, "blank row")
                    continue
                if len(row) > len(headers):
                    _log_skip(path, row_number, "malformed row has extra columns")
                    continue
                rows.append(
                    {
                        header: row[index].strip() if index < len(row) else ""
                        for index, header in enumerate(headers)
                    }
                )
            return rows
    except (OSError, UnicodeError, csv.Error, ValueError) as error:
        print(f"Job-posting lookup disabled for {path}: {error}", file=sys.stderr)
        return None


def load_topic_keywords(path: Path) -> dict[str, list[str]] | None:
    rows = _read_csv(path, TOPIC_MAP_COLUMNS)
    if not rows:
        if rows is not None:
            print(f"Job-posting lookup disabled: {path} contains no topic keywords.", file=sys.stderr)
        return None

    topics: dict[str, list[str]] = {}
    for row_number, row in enumerate(rows, start=2):
        topic = normalize_topic_text(row.get("topic", ""))
        keyword = normalize_topic_text(row.get("keyword", ""))
        if not topic or not keyword:
            _log_skip(path, row_number, "blank topic or keyword")
            continue
        keywords = topics.setdefault(topic, [])
        if keyword not in keywords:
            keywords.append(keyword)
    return topics or None


def _load_postings(
    path: Path,
    valid_topics: set[str],
) -> list[dict[str, str]]:
    rows = _read_csv(path, POSTING_COLUMNS)
    if not rows:
        if rows is not None:
            print(f"No job postings found in {path}.", file=sys.stderr)
        return []

    candidates: list[dict[str, str | int]] = []
    for row_number, row in enumerate(rows, start=2):
        topic = normalize_topic_text(row.get("topic", ""))
        url = row.get("posting_url", "").strip()
        title = row.get("posting_title", "").strip()
        employer = row.get("employer", "").strip()
        retrieved = row.get("date_retrieved", "").strip()
        if not any((topic, url, title, employer, retrieved, row.get("notes", "").strip())):
            _log_skip(path, row_number, "blank row")
            continue
        link_type = row.get("link_type", "").strip().lower() or "posting"
        if link_type not in {"posting", "listing"}:
            _log_skip(path, row_number, f"unsupported link_type: {link_type!r}")
            continue
        if not topic or not url:
            _log_skip(path, row_number, "required posting field (topic or URL) is blank")
            continue
        if topic not in valid_topics:
            _log_skip(path, row_number, f"topic {topic!r} is not in the topic map")
            continue
        if not validate_http_url(url) or (link_type != "listing" and not validate_posting_url(url)):
            _log_skip(path, row_number, f"invalid or search/result URL: {url!r}")
            continue
        if retrieved:
            try:
                if re.fullmatch(r"\d{4}-\d{2}-\d{2}", retrieved) is None:
                    raise ValueError("date is not YYYY-MM-DD")
                date.fromisoformat(retrieved)
            except ValueError:
                _log_skip(path, row_number, f"date_retrieved must be YYYY-MM-DD: {retrieved!r}")
                continue

        candidates.append(
            {
                "_row_number": row_number,
                "topic": topic,
                "posting_url": url,
                "posting_title": title,
                "employer": employer,
                "date_retrieved": retrieved,
                "notes": row.get("notes", "").strip(),
                "link_type": link_type,
            }
        )
    candidates.sort(key=lambda posting: str(posting["date_retrieved"]), reverse=True)
    candidates.sort(key=lambda posting: 0 if posting["link_type"] == "posting" else 1)
    postings: list[dict[str, str]] = []
    seen_urls: set[tuple[str, tuple[str, str, int | None, str, str, str]]] = set()
    for candidate in candidates:
        posting_url = str(candidate["posting_url"])
        dedupe_key = (str(candidate["topic"]), _posting_url_key(posting_url))
        if dedupe_key in seen_urls:
            _log_skip(path, int(candidate["_row_number"]), f"duplicate posting URL: {posting_url!r}")
            continue
        seen_urls.add(dedupe_key)
        postings.append({key: str(value) for key, value in candidate.items() if key != "_row_number"})
    return postings


def match_topics(subject_name: str, topic_keywords: dict[str, list[str]]) -> list[str]:
    normalized_name = f" {normalize_topic_text(subject_name)} "
    return [
        topic
        for topic, keywords in topic_keywords.items()
        if any(f" {keyword} " in normalized_name for keyword in keywords)
    ]


def select_job_postings(
    subject_name: str,
    topic_keywords: dict[str, list[str]],
    postings: list[dict[str, str]],
    limit: int = MAX_POSTINGS_PER_RECOMMENDATION,
) -> dict[str, Any]:
    matched_topics = match_topics(subject_name, topic_keywords)
    if limit <= 0:
        return {"matched_topics": matched_topics, "postings": []}
    matched_set = set(matched_topics)
    selected: list[dict[str, str]] = []
    seen_urls: set[tuple[str, str, int | None, str, str, str]] = set()
    matching_postings = [
        posting for posting in postings
        if posting["topic"] in matched_set
    ]
    matching_postings.sort(
        key=lambda posting: str(posting.get("date_retrieved") or ""),
        reverse=True,
    )
    matching_postings.sort(
        key=lambda posting: 0 if posting.get("link_type", "posting") == "posting" else 1
    )
    for posting in matching_postings:
        key = _posting_url_key(posting["posting_url"])
        if key in seen_urls:
            continue
        seen_urls.add(key)
        selected.append(posting)
        if len(selected) >= max(0, limit):
            break
    return {"matched_topics": matched_topics, "postings": selected}


def lookup_job_postings(
    subject_name: str,
    postings_path: Path,
    topic_map_path: Path,
    limit: int = MAX_POSTINGS_PER_RECOMMENDATION,
) -> dict[str, Any]:
    """Return matching topics and postings. Any file/CSV failure returns an empty result."""
    empty = {"matched_topics": [], "postings": []}
    try:
        topic_keywords = load_topic_keywords(topic_map_path)
        if not topic_keywords:
            return empty
        matched_topics = match_topics(subject_name, topic_keywords)
        if not matched_topics:
            return empty
        postings = _load_postings(postings_path, set(topic_keywords))
        return select_job_postings(subject_name, topic_keywords, postings, limit)
    except Exception as error:
        print(f"Job-posting lookup failed safely: {error}", file=sys.stderr)
        return empty


def attach_job_postings(
    report: dict[str, Any],
    postings_path: Path,
    topic_map_path: Path,
) -> dict[str, Any]:
    """Add a snapshot of selected postings to each recommendation, without raising."""
    try:
        recommendations = report.get("recommendations", [])
        if not isinstance(recommendations, list):
            return report
        for recommendation in recommendations:
            if not isinstance(recommendation, dict):
                continue
            title = str(recommendation.get("subject_title") or "")
            recommendation["job_postings"] = lookup_job_postings(
                title,
                postings_path,
                topic_map_path,
            )
    except Exception as error:
        print(f"Job-posting report enrichment failed safely: {error}", file=sys.stderr)
    return report
