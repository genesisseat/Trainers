from __future__ import annotations

from contextlib import closing
import csv
import json
import os
import sqlite3
import sys
import time
import traceback
import urllib.error
import urllib.parse
import urllib.request
from pathlib import Path
from typing import Any, Dict, Iterable, List

from runtime_paths import knowledge_base_dir

from curriculum_generator_foundation import build_subject_bank, load_course_rows, normalize_subject_name
from recommended_tools import recommend_tools


GEMINI_MAX_ATTEMPTS = 3
GEMINI_REQUEST_TIMEOUT_SECONDS = 60
GEMINI_INITIAL_BACKOFF_SECONDS = 2
GEMINI_MAX_BACKOFF_SECONDS = 6
TRANSIENT_GEMINI_STATUS_CODES = {408, 429, 500, 502, 503, 504}
_MAJOR_COURSE_CLASSIFICATIONS = {
    "core",
    "professional",
    "specialization",
    "research / capstone",
    "internship",
}


class GeminiGenerationError(Exception):
    def __init__(self, message: str, raw_response: str | None = None):
        super().__init__(message)
        self.raw_response = raw_response


RUN_ACCESS_DENIED_MESSAGE = "Run not found or inaccessible."
GEMINI_API_KEY_REQUIRED_MESSAGE = "You need an API key. Add your Gemini API key in your profile to use this feature."


def configure_web_actor_api_key(db_path: str | Path, actor_user_id: int | None) -> str:
    if actor_user_id is None or actor_user_id < 1:
        raise ValueError(GEMINI_API_KEY_REQUIRED_MESSAGE)

    with closing(sqlite3.connect(Path(db_path), timeout=30)) as conn:
        try:
            actor = conn.execute(
                "SELECT role, gemini_api_key FROM users WHERE id = ?",
                (actor_user_id,),
            ).fetchone()
        except sqlite3.OperationalError as error:
            raise ValueError(GEMINI_API_KEY_REQUIRED_MESSAGE) from error

    if actor is None:
        raise ValueError(GEMINI_API_KEY_REQUIRED_MESSAGE)

    role, personal_key = actor
    personal_key = personal_key.strip() if isinstance(personal_key, str) else ""
    if personal_key:
        os.environ["GEMINI_API_KEY"] = personal_key
        return "personal"

    if role == "super_admin":
        shared_key = load_saved_api_key()
        if shared_key:
            os.environ["GEMINI_API_KEY"] = shared_key
            return "shared"

    raise ValueError(GEMINI_API_KEY_REQUIRED_MESSAGE)


def _require_run_access(
    conn: sqlite3.Connection,
    run_id: int,
    actor_user_id: int | None,
    admin_access_policy: str = "own_only",
) -> None:
    if actor_user_id is None or actor_user_id < 1:
        raise ValueError(RUN_ACCESS_DENIED_MESSAGE)

    actor = conn.execute(
        "SELECT role FROM users WHERE id = ?",
        (actor_user_id,),
    ).fetchone()
    run_columns = {
        row[1] for row in conn.execute("PRAGMA table_info(generated_curriculum_runs)")
    }
    owner_expression = "created_by_user_id" if "created_by_user_id" in run_columns else "NULL"
    run = conn.execute(
        f"SELECT {owner_expression} FROM generated_curriculum_runs WHERE id = ?",
        (run_id,),
    ).fetchone()
    if actor is None or run is None:
        raise ValueError(RUN_ACCESS_DENIED_MESSAGE)

    role = actor[0]
    owner_id = run[0]
    allowed = role == "super_admin"
    if role == "user":
        allowed = owner_id is not None and int(owner_id) == actor_user_id
    elif role == "admin":
        allowed = owner_id is not None and int(owner_id) > 0 and (
            admin_access_policy == "all_owned"
            or (admin_access_policy == "own_only" and int(owner_id) == actor_user_id)
        )
    if not allowed:
        raise ValueError(RUN_ACCESS_DENIED_MESSAGE)


def _ensure_run_attribution_columns(conn: sqlite3.Connection) -> None:
    columns = {row[1] for row in conn.execute("PRAGMA table_info(generated_curriculum_runs)")}
    if "created_by_user_id" not in columns:
        conn.execute("ALTER TABLE generated_curriculum_runs ADD COLUMN created_by_user_id INTEGER")
    if "created_by_username" not in columns:
        conn.execute("ALTER TABLE generated_curriculum_runs ADD COLUMN created_by_username TEXT")
    conn.execute(
        "CREATE INDEX IF NOT EXISTS idx_generated_curriculum_runs_owner_id "
        "ON generated_curriculum_runs(created_by_user_id)"
    )


def _ensure_draft_management_columns(conn: sqlite3.Connection) -> None:
    columns = {row[1] for row in conn.execute("PRAGMA table_info(generated_curriculum_runs)")}
    definitions = {
        "user_title": "TEXT NULL",
        "user_notes": "TEXT NULL",
        "updated_at": "TEXT NULL",
        "generation_mode": "TEXT NULL",
    }
    for name, definition in definitions.items():
        if name not in columns:
            conn.execute(f"ALTER TABLE generated_curriculum_runs ADD COLUMN {name} {definition}")
            if name == "generation_mode":
                _backfill_generation_modes(conn)


def _backfill_generation_modes(conn: sqlite3.Connection) -> None:
    subject_table = conn.execute(
        "SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'generated_curriculum_subjects'"
    ).fetchone()
    run_columns = {row[1] for row in conn.execute("PRAGMA table_info(generated_curriculum_runs)")}
    if subject_table and "source" in run_columns:
        conn.execute(
            """
            UPDATE generated_curriculum_runs
            SET generation_mode = 'offline'
            WHERE generation_mode IS NULL
              AND COALESCE(source, 'generated') <> 'enhanced'
              AND EXISTS (
                  SELECT 1
                  FROM generated_curriculum_subjects s
                  WHERE s.run_id = generated_curriculum_runs.id
                    AND instr(COALESCE(s.rationale, ''), '[offline template fallback]') = 1
              )
            """
        )
    if "source" not in run_columns:
        return
    rows = conn.execute(
        """
        SELECT id, notes
        FROM generated_curriculum_runs
        WHERE generation_mode IS NULL AND source = 'enhanced'
        """
    )
    for run_id, notes in rows.fetchall():
        try:
            report = json.loads(notes or "")
        except (TypeError, json.JSONDecodeError):
            continue
        if not isinstance(report, dict):
            continue
        review_fallback = report.get("fallback")
        curriculum_fallback = report.get("draft_fallback")
        if review_fallback is False or curriculum_fallback is False:
            mode = "online"
        elif review_fallback is True and curriculum_fallback is True:
            mode = "offline"
        else:
            continue
        conn.execute(
            "UPDATE generated_curriculum_runs SET generation_mode = ? WHERE id = ? AND generation_mode IS NULL",
            (mode, run_id),
        )


def _ensure_chat_attribution_columns(conn: sqlite3.Connection) -> None:
    columns = {row[1] for row in conn.execute("PRAGMA table_info(generated_curriculum_chat)")}
    if "sender_user_id" not in columns:
        conn.execute("ALTER TABLE generated_curriculum_chat ADD COLUMN sender_user_id INTEGER")
    if "sender_username" not in columns:
        conn.execute("ALTER TABLE generated_curriculum_chat ADD COLUMN sender_username TEXT")
    conn.execute(
        "CREATE INDEX IF NOT EXISTS idx_generated_curriculum_chat_run_id "
        "ON generated_curriculum_chat(run_id)"
    )


def _subject_to_year_term(subject: Dict[str, Any], fallback_year: str = "1", fallback_term: str = "1") -> Dict[str, str]:
    valid_years = {"1", "2", "3", "4"}
    valid_terms = {"1", "2", "3"}
    year_terms = subject.get("year_terms") or []
    if isinstance(year_terms, str):
        year_terms = [year_terms]
    if year_terms:
        first = str(year_terms[0]).strip().upper()
        if first.startswith("Y") and "T" in first:
            year, term = first[1:].split("T", 1)
            year = year.strip()
            term = term.strip()
            if year in valid_years and term in valid_terms:
                return {"year": year, "term": term}
    safe_year = str(fallback_year).strip()
    safe_term = str(fallback_term).strip()
    return {
        "year": safe_year if safe_year in valid_years else "1",
        "term": safe_term if safe_term in valid_terms else "1",
    }


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


def _select_offline_major_rows(
    program: str,
    specialization: str,
    course_rows: Iterable[Dict[str, Any]],
) -> List[Dict[str, Any]]:
    program_key = normalize_subject_name(program)
    specialization_key = normalize_subject_name(specialization)
    target_program = program_key
    target_university = "Adamson University" if program_key == "bsit" else "UP"

    if program_key == "bsit" and (
        "mwa" in specialization_key
        or ("mobile" in specialization_key and "web" in specialization_key)
    ):
        target_program = "bsit mwa"
        target_university = "NU Lipa"
    elif program_key == "bscs" and (
        "machine learning" in specialization_key
        or "artificial intelligence" in specialization_key
    ):
        target_program = "bscs ml specialization"
        target_university = "NU Lipa"

    valid_rows = []
    for row in course_rows:
        row_program = normalize_subject_name(str(row.get("program") or ""))
        classification = str(row.get("classification") or "").strip().casefold()
        year = str(row.get("year") or "").strip()
        term = str(row.get("term") or "").strip()
        if (
            row_program != target_program
            or str(row.get("university") or "").strip() != target_university
            or classification not in _MAJOR_COURSE_CLASSIFICATIONS
            or year not in {"1", "2", "3", "4"}
            or term not in {"1", "2", "3"}
            or not str(row.get("course") or "").strip()
        ):
            continue
        valid_rows.append(dict(row))

    return sorted(
        valid_rows,
        key=lambda row: (
            int(str(row["year"]).strip()),
            int(str(row["term"]).strip()),
            str(row.get("course") or "").casefold(),
        ),
    )


def _major_course_title_set(
    program: str,
    course_rows: Iterable[Dict[str, Any]],
    subject_bank: Iterable[Dict[str, Any]],
) -> set[str]:
    program_key = normalize_subject_name(program)
    titles: set[str] = set()
    rows = list(course_rows)
    if rows:
        for row in rows:
            row_program = normalize_subject_name(str(row.get("program") or ""))
            classification = str(row.get("classification") or "").strip().casefold()
            if (
                (row_program == program_key or row_program.startswith(program_key + " "))
                and classification in _MAJOR_COURSE_CLASSIFICATIONS
            ):
                title = str(row.get("course") or "").strip()
                if title:
                    titles.add(normalize_subject_name(title))
        return titles

    for subject in subject_bank:
        subject_program = normalize_subject_name(str(subject.get("program_key") or subject.get("program") or ""))
        classification = str(subject.get("classification") or "").strip().casefold()
        if (
            (subject_program == program_key or subject_program.startswith(program_key + " "))
            and classification in _MAJOR_COURSE_CLASSIFICATIONS
        ):
            for title in [subject.get("display_name"), subject.get("canonical_subject"), *(subject.get("subject_variants") or [])]:
                if title:
                    titles.add(normalize_subject_name(str(title)))
    return titles


def _course_identity_key(title: str) -> str:
    normalized = normalize_subject_name(title)
    if any(term in normalized for term in ("internship", "practicum", "on the job training", "ojt")):
        return "internship"

    tokens = normalized.split()
    if "capstone" in tokens:
        tokens = [token for token in tokens if token not in {"it", "project"}]
    if "database" in tokens:
        tokens = [token for token in tokens if token not in {"management", "system", "systems"}]
    ignored = {"and", "for", "in", "of", "the"}
    aliases = {
        "algorithms": "algorithm",
        "databases": "database",
        "networking": "network",
        "networks": "network",
        "structures": "structure",
    }
    return " ".join(sorted(aliases.get(token, token) for token in tokens if token not in ignored))


def _course_titles_equivalent(left: str, right: str) -> bool:
    left_key = _course_identity_key(left)
    right_key = _course_identity_key(right)
    if left_key == right_key:
        return True
    left_tokens = left_key.split()
    right_tokens = right_key.split()
    if len(left_tokens) == 1 and len(right_tokens) == 2:
        return left_tokens[0] in right_tokens and any(token.isdigit() for token in right_tokens)
    if len(right_tokens) == 1 and len(left_tokens) == 2:
        return right_tokens[0] in left_tokens and any(token.isdigit() for token in left_tokens)
    return False


def load_saved_api_key() -> str:
    """Load a Gemini key from the environment or local settings for CLI use."""
    for environment_name in ("GEMINI_API_KEY", "GOOGLE_API_KEY"):
        environment_key = os.environ.get(environment_name, "").strip()
        if environment_key:
            return environment_key

    # The web app supplies a personal key when present; otherwise the shared
    # settings-file fallback remains available to browser-launched subprocesses.
    app_data = os.environ.get("APPDATA", "").strip()
    home = os.environ.get("HOME", "").strip()
    project_root = Path(__file__).resolve().parent
    settings_paths: List[Path] = []

    for base_dir in (app_data, home):
        if base_dir:
            settings_paths.append(Path(base_dir) / "CurriculumMatcher" / "settings.json")
    settings_paths.append(project_root / "settings.json")

    seen_paths: set[Path] = set()
    for settings_path in settings_paths:
        settings_path = settings_path.resolve()
        if settings_path in seen_paths:
            continue
        seen_paths.add(settings_path)

        if not settings_path.is_file():
            print(f"API key file not found at expected path: {settings_path}", file=sys.stderr)
            continue

        try:
            with settings_path.open("r", encoding="utf-8") as handle:
                data = json.load(handle)
        except (OSError, json.JSONDecodeError) as error:
            print(f"API key file unreadable at {settings_path}: {error}", file=sys.stderr)
            continue

        if not isinstance(data, dict):
            print(f"API key file does not contain a JSON object: {settings_path}", file=sys.stderr)
            continue

        api_key = str(data.get("api_key", "")).strip()
        if api_key:
            return api_key

        print(f"API key field is missing or empty in: {settings_path}", file=sys.stderr)

    print(
        "API key not found in GEMINI_API_KEY or GOOGLE_API_KEY environment variables, "
        "and no usable API key was found at expected settings paths: "
        + ", ".join(str(path) for path in seen_paths),
        file=sys.stderr,
    )
    return ""


def _build_gemini_prompt(
    program: str,
    prompt: str,
    subject_bank: Iterable[Dict[str, Any]],
    skill_evidence: Iterable[Dict[str, Any]],
) -> str:
    subjects = [
        {
            "canonical_subject": item.get("canonical_subject"),
            "source_colleges": item.get("source_colleges") or [],
            "year_terms": item.get("year_terms") or [],
            "classification": item.get("classification") or "",
        }
        for item in subject_bank
    ]
    weak_skills = [
        {"skill_name": item.get("skill_name"), "score": item.get("score")}
        for item in skill_evidence
    ]

    return f"""You are designing a complete, realistic, evidence-based university curriculum roadmap.
Return ONLY valid JSON: a list of subject objects, with no markdown or commentary.

User request: {prompt}
Program: {program}

Retrieved subject evidence:
{json.dumps(subjects, ensure_ascii=False, indent=2)}

Weakest skill-coverage evidence (lower scores indicate larger gaps):
{json.dumps(weak_skills, ensure_ascii=False, indent=2)}

Build a complete four-year academic roadmap. Use a semester calendar with two terms per
year by default; if the user explicitly requests trimesters or another calendar, use
that number of terms consistently in every year. Years 1 and 2 must contain 2 to 3
subjects in every term; Years 3 and 4 may contain 1 to 3 subjects in every term.
If the requested calendar is not explicit, produce 8 populated terms (Years 1-4, Terms
1-2). Do not output a partial curriculum.

CRITICAL DENSITY RULE: Years 1 and 2 MUST contain exactly 2 or 3 major subjects per
term without exception. You are strictly forbidden from placing only 1 subject in any
freshman or sophomore term. Years 3 and 4 may contain 1 to 3 subjects per term to
gracefully accommodate heavy final-year capstones or internships. Failing to meet these
structural term densities will cause automated validation rejection.

Include ONLY professional BSIT major subjects. Exclude General Education, PE, NSTP,
minor, broad liberal-arts, and unrelated elective courses, even if they appear in the
retrieved evidence. Use the retrieved subjects as evidence and adapt titles only when
needed to create a coherent major roadmap.

Enforce strict progression:
- Years 1-2: computing foundations, introductory programming, discrete concepts,
    data structures, basic web development, databases, operating systems, and networking.
- Years 3-4: advanced software engineering, systems integration, architecture, cloud,
    cybersecurity, distributed systems, professional practice, internship, and capstone.
- Never place advanced architecture, security, distributed systems, internship, or
    capstone subjects in Years 1-2. Respect prerequisites and sequence them realistically.

Each object must contain exactly these keys: program, year, term, subject_code,
subject_title, description, units, prerequisites, topics, rationale, source_colleges,
mapped_industry_skills. `mapped_industry_skills` must be a list of specific weak skill
names addressed by the subject. Topics must be a list of 3 to 6 specific, assessable
topics, not generic boilerplate. Rationale must be one sentence naming the mapped weak
skill or skills and why the subject addresses them. Use retrieved year_terms,
classification, units, colleges, and weak-skill scores when they support the roadmap.
Keep source_colleges as a list of strings.
"""


def _parse_gemini_response(
    response: Dict[str, Any],
    raw_response: str = "",
    expected_years: Iterable[str] | None = None,
) -> List[Dict[str, Any]]:
    try:
        text = response["candidates"][0]["content"]["parts"][0]["text"]
    except (KeyError, IndexError, TypeError) as error:
        raise GeminiGenerationError("Gemini response did not contain generated text.", raw_response) from error

    cleaned = str(text).strip()
    if cleaned.startswith("```"):
        cleaned = cleaned.split("\n", 1)[1] if "\n" in cleaned else ""
        if cleaned.endswith("```"):
            cleaned = cleaned[:-3].rstrip()

    try:
        parsed = json.loads(cleaned)
    except json.JSONDecodeError as error:
        raise GeminiGenerationError(f"Gemini returned invalid JSON: {error.msg}", raw_response or cleaned) from error

    if not isinstance(parsed, list) or not all(isinstance(item, dict) for item in parsed):
        raise GeminiGenerationError("Gemini JSON must be a list of objects.", raw_response or cleaned)

    required_keys = {
        "program", "year", "term", "subject_code", "subject_title", "description", "units",
        "prerequisites", "topics", "rationale", "source_colleges", "mapped_industry_skills",
    }
    for index, item in enumerate(parsed):
        missing = required_keys - item.keys()
        if missing:
            raise GeminiGenerationError(
                f"Gemini subject {index} is missing keys: {sorted(missing)}",
                raw_response or cleaned,
            )
        if not isinstance(item["topics"], list) or not 3 <= len(item["topics"]) <= 6:
            raise GeminiGenerationError(
                f"Gemini subject {index} must contain 3 to 6 topics.",
                raw_response or cleaned,
            )
        if not isinstance(item["source_colleges"], list):
            raise GeminiGenerationError(
                f"Gemini subject {index} source_colleges must be a list.",
                raw_response or cleaned,
            )
        if not isinstance(item["mapped_industry_skills"], list):
            raise GeminiGenerationError(
                f"Gemini subject {index} mapped_industry_skills must be a list.",
                raw_response or cleaned,
            )

    years = {str(item["year"]).strip() for item in parsed}
    required_years = {str(year).strip() for year in expected_years} if expected_years is not None else {"1", "2", "3", "4"}
    if years != required_years:
        raise GeminiGenerationError(
            f"Gemini curriculum must include subjects only in these years: {', '.join(sorted(required_years))}.",
            raw_response or cleaned,
        )
    terms_by_year = {}
    for item in parsed:
        year = str(item["year"]).strip()
        term = str(item["term"]).strip()
        terms_by_year.setdefault(year, set()).add(term)
    if any(len(terms) not in {2, 3} for terms in terms_by_year.values()):
        raise GeminiGenerationError(
            "Gemini curriculum must use 2 or 3 populated terms per year.",
            raw_response or cleaned,
        )
    term_counts = {len(terms) for terms in terms_by_year.values()}
    if len(term_counts) != 1:
        raise GeminiGenerationError(
            "Gemini curriculum must use the same number of terms in every year.",
            raw_response or cleaned,
        )
    invalid_density = []
    for year, terms in terms_by_year.items():
        allowed_counts = {2, 3} if year in {"1", "2"} else {1, 2, 3}
        for term in terms:
            subject_count = sum(
                1
                for item in parsed
                if str(item["year"]).strip() == year and str(item["term"]).strip() == term
            )
            if subject_count not in allowed_counts:
                invalid_density.append(f"Year {year} Term {term}: {subject_count} subjects")

    if invalid_density:
        raise GeminiGenerationError(
            "Gemini curriculum violates term density rules: " + "; ".join(invalid_density),
            raw_response or cleaned,
        )

    return parsed


def _request_gemini_payload(request: urllib.request.Request) -> tuple[Dict[str, Any], str]:
    """Request Gemini content, retrying only failures that may resolve shortly."""
    last_error: Exception | None = None
    last_details = ""

    for attempt in range(1, GEMINI_MAX_ATTEMPTS + 1):
        try:
            with urllib.request.urlopen(request, timeout=GEMINI_REQUEST_TIMEOUT_SECONDS) as response:
                raw_response = response.read().decode("utf-8", errors="replace")
            try:
                return json.loads(raw_response), raw_response
            except json.JSONDecodeError as error:
                print(
                    f"[Gemini API] response was not valid JSON: {error.msg}",
                    file=sys.stderr,
                    flush=True,
                )
                raise GeminiGenerationError(
                    f"Gemini returned invalid API JSON: {error.msg}",
                    raw_response,
                ) from error
        except urllib.error.HTTPError as error:
            last_error = error
            last_details = error.read().decode("utf-8", errors="replace")
            retryable = error.code in TRANSIENT_GEMINI_STATUS_CODES
            error_label = f"HTTP {error.code}"
            print(
                f"[Gemini API] attempt {attempt}/{GEMINI_MAX_ATTEMPTS} failed: {error_label}. "
                f"Response: {last_details[:1000]}",
                file=sys.stderr,
                flush=True,
            )
        except (urllib.error.URLError, TimeoutError, ConnectionError) as error:
            last_error = error
            last_details = str(error)
            retryable = True
            error_label = type(error).__name__
            print(
                f"[Gemini API] attempt {attempt}/{GEMINI_MAX_ATTEMPTS} failed: "
                f"{error_label}: {last_details}",
                file=sys.stderr,
                flush=True,
            )

        if not retryable or attempt == GEMINI_MAX_ATTEMPTS:
            break

        wait_seconds = min(
            GEMINI_INITIAL_BACKOFF_SECONDS * (2 ** (attempt - 1)),
            GEMINI_MAX_BACKOFF_SECONDS,
        )
        print(
            f"[Gemini API] Retrying request after {error_label} in {wait_seconds} seconds... "
            f"(attempt {attempt + 1}/{GEMINI_MAX_ATTEMPTS})",
            file=sys.stderr,
            flush=True,
        )
        time.sleep(wait_seconds)

    if isinstance(last_error, urllib.error.HTTPError):
        message = f"Gemini API request failed ({last_error.code})."
    else:
        message = f"Gemini API request failed: {last_error}"
    print(
        f"[Gemini API] giving up after {GEMINI_MAX_ATTEMPTS} attempt(s): {message}",
        file=sys.stderr,
        flush=True,
    )
    raise GeminiGenerationError(message, last_details) from last_error


def _request_gemini_prompt(prompt: str, model: str) -> tuple[Dict[str, Any], str]:
    api_key = load_saved_api_key()
    if not api_key:
        raise GeminiGenerationError("No Gemini API key is configured.")

    request_body = {
        "contents": [{"parts": [{"text": prompt}]}],
        "generationConfig": {"temperature": 0.3, "responseMimeType": "application/json"},
    }
    endpoint = (
        "https://generativelanguage.googleapis.com/v1beta/models/"
        f"{urllib.parse.quote(model, safe='')}:generateContent?key="
        f"{urllib.parse.quote(api_key, safe='')}"
    )
    request = urllib.request.Request(
        endpoint,
        data=json.dumps(request_body).encode("utf-8"),
        headers={"Content-Type": "application/json"},
        method="POST",
    )
    return _request_gemini_payload(request)


def call_gemini_for_curriculum(
    program: str,
    prompt: str,
    subject_bank: Iterable[Dict[str, Any]],
    skill_evidence: Iterable[Dict[str, Any]],
    model: str = "gemini-3.5-flash-lite",
) -> List[Dict[str, Any]]:
    payload, raw_response = _request_gemini_prompt(
        _build_gemini_prompt(program, prompt, subject_bank, skill_evidence),
        model,
    )
    return _parse_gemini_response(payload, raw_response)


def _call_gemini_for_text(prompt: str, model: str = "gemini-3.5-flash-lite") -> str:
    payload, raw_response = _request_gemini_prompt(prompt, model)

    try:
        return str(payload["candidates"][0]["content"]["parts"][0]["text"]).strip()
    except (KeyError, IndexError, TypeError) as error:
        raise GeminiGenerationError("Gemini response did not contain generated text.", raw_response) from error


def _decode_stored_json(value: str | None) -> Any:
    if not value:
        return []
    try:
        parsed = json.loads(value)
    except (TypeError, json.JSONDecodeError):
        return value
    return parsed


_CURRICULUM_SUBJECT_KEYS = {
    "program", "year", "term", "subject_code", "subject_title", "description", "units",
    "prerequisites", "topics", "rationale", "source_colleges", "mapped_industry_skills",
}


def _parse_chat_response(text: str) -> Dict[str, Any]:
    """Parse structured chat output, treating non-JSON output as explanation text."""
    clean_text = text.strip()
    if clean_text.startswith("```"):
        lines = clean_text.splitlines()
        clean_text = "\n".join(lines[1:-1] if lines and lines[-1].strip() == "```" else lines[1:]).strip()

    try:
        parsed = json.loads(clean_text)
    except json.JSONDecodeError:
        return {"action": "explain", "message": text.strip(), "updated_curriculum": None}

    if not isinstance(parsed, dict):
        return {"action": "explain", "message": text.strip(), "updated_curriculum": None}

    action = parsed.get("action")
    if action not in {"explain", "modify"}:
        action = "explain"
    response_message = parsed.get("message")
    if not isinstance(response_message, str) or not response_message.strip():
        response_message = text.strip()

    updated_curriculum = parsed.get("updated_curriculum")
    if action != "modify" or not isinstance(updated_curriculum, list):
        action = "explain"
        updated_curriculum = None

    return {
        "action": action,
        "message": response_message.strip(),
        "updated_curriculum": updated_curriculum,
    }


def _validate_updated_curriculum(value: Any) -> bool:
    if not isinstance(value, list) or not value:
        return False
    for subject in value:
        if not isinstance(subject, dict) or not _CURRICULUM_SUBJECT_KEYS.issubset(subject):
            return False
        if not isinstance(subject["topics"], list) or not 3 <= len(subject["topics"]) <= 6:
            return False
        if not isinstance(subject["source_colleges"], list):
            return False
        if not isinstance(subject["mapped_industry_skills"], list):
            return False
    return True


def chat_about_curriculum(
    db_path: str | Path,
    run_id: int,
    message: str,
    model: str = "gemini-3.5-flash-lite",
    sender_user_id: int | None = None,
    sender_username: str | None = None,
    admin_access_policy: str = "own_only",
) -> str:
    """Answer a follow-up question using a saved curriculum run as context."""
    clean_message = message.strip()
    if not clean_message:
        raise ValueError("Chat message cannot be empty.")
    if sender_user_id is not None:
        configure_web_actor_api_key(db_path, sender_user_id)

    db_file = Path(db_path)
    with closing(sqlite3.connect(db_file, timeout=30)) as conn, conn:
        conn.execute(
            """
            CREATE TABLE IF NOT EXISTS generated_curriculum_chat (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                run_id INTEGER,
                role TEXT,
                message TEXT,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY(run_id) REFERENCES generated_curriculum_runs(id)
            )
            """
        )
        _ensure_chat_attribution_columns(conn)
        _require_run_access(conn, run_id, sender_user_id, admin_access_policy)
        run = conn.execute(
            """
            SELECT id, program, prompt, model_name, status, generated_at
            FROM generated_curriculum_runs
            WHERE id = ?
            """,
            (run_id,),
        ).fetchone()
        if run is None:
            raise ValueError(f"Generated curriculum run not found: {run_id}")

        subjects = conn.execute(
            """
            SELECT year, term, subject_code, subject_title, units, prerequisites,
                   topics, rationale, source_colleges
            FROM generated_curriculum_subjects
            WHERE run_id = ?
            ORDER BY CAST(year AS INTEGER), CAST(term AS INTEGER), id
            """,
            (run_id,),
        ).fetchall()
        skill_rows = conn.execute(
            """
            SELECT skill_id, skill_name, skill_type, score,
                   best_course_id, best_course_title
            FROM skill_coverage
            ORDER BY score ASC
            LIMIT 10
            """
        ).fetchall()

        curriculum_context = {
            "run": {
                "id": run[0],
                "program": run[1],
                "prompt": run[2],
                "model_name": run[3],
                "status": run[4],
                "generated_at": run[5],
            },
            "subjects": [
                {
                    "year": subject[0],
                    "term": subject[1],
                    "subject_code": subject[2],
                    "subject_title": subject[3],
                    "units": subject[4],
                    "prerequisites": subject[5],
                    "topics": _decode_stored_json(subject[6]),
                    "rationale": subject[7],
                    "source_colleges": _decode_stored_json(subject[8]),
                }
                for subject in subjects
            ],
            "weakest_skill_evidence": [
                {
                    "skill_id": skill[0],
                    "skill_name": skill[1],
                    "skill_type": skill[2],
                    "score": skill[3],
                    "best_course_id": skill[4],
                    "best_course_title": skill[5],
                }
                for skill in skill_rows
            ],
        }
        conn.execute(
            """INSERT INTO generated_curriculum_chat
               (run_id, role, message, sender_user_id, sender_username)
               VALUES (?, ?, ?, ?, ?)""",
            (run_id, "user", clean_message, sender_user_id, sender_username),
        )
        conn.commit()

        chat_prompt = f"""You are assisting with an existing curriculum draft.
Use the curriculum and weak-skill evidence below as the source of truth. Detect the
user's intent and return ONLY valid JSON with this shape:
{{
    "action": "explain" or "modify",
    "message": "the concise conversational response",
    "updated_curriculum": null or a complete list of updated subject objects
}}

Use action "explain" for questions, analysis, or requests that do not require a
database change. Use action "modify" when the user requests a curriculum edit. For
modify, updated_curriculum must be the FULL replacement list, and every subject must
contain exactly the standard keys: program, year, term, subject_code, subject_title,
description, units, prerequisites, topics, rationale, source_colleges, mapped_industry_skills.
Topics and mapped_industry_skills must be lists, and topics must contain 3 to 6
specific strings. Do not invent retrieval evidence. If you cannot produce a valid full
replacement, use action "explain".

Existing curriculum context:
{json.dumps(curriculum_context, ensure_ascii=False, indent=2)}

User follow-up:
{clean_message}
"""

    try:
        raw_answer = _call_gemini_for_text(chat_prompt, model=model)
        chat_result = _parse_chat_response(raw_answer)
    except GeminiGenerationError as error:
        print(f"Gemini curriculum chat failed: {error}", file=sys.stderr)
        chat_result = {
            "action": "explain",
            "message": f"Unable to contact Gemini for this chat request: {error}",
            "updated_curriculum": None,
        }

    updated_curriculum = chat_result.get("updated_curriculum")
    if chat_result["action"] == "modify" and _validate_updated_curriculum(updated_curriculum):
        with closing(sqlite3.connect(db_file, timeout=30)) as conn, conn:
            conn.execute("DELETE FROM generated_curriculum_subjects WHERE run_id = ?", (run_id,))
            for subject in updated_curriculum:
                conn.execute(
                    """
                    INSERT INTO generated_curriculum_subjects (
                        run_id, program, year, term, subject_code, subject_title, description, units,
                        prerequisites, topics, rationale, source_colleges, mapped_industry_skills
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    """,
                    (
                        run_id,
                        str(subject["program"]).strip(),
                        str(subject["year"]).strip(),
                        str(subject["term"]).strip(),
                        str(subject["subject_code"]).strip(),
                        str(subject["subject_title"]).strip(),
                        str(subject["description"]).strip(),
                        str(subject["units"]).strip(),
                        str(subject["prerequisites"]).strip(),
                        json.dumps(subject["topics"], ensure_ascii=False),
                        str(subject["rationale"]).strip(),
                        json.dumps(subject["source_colleges"], ensure_ascii=False),
                        json.dumps(subject["mapped_industry_skills"], ensure_ascii=False),
                    ),
                )
            conn.commit()
    elif chat_result["action"] == "modify":
        chat_result["action"] = "explain"
        chat_result["message"] = (
            f"{chat_result['message']}\n\n"
            "I could not save the requested edit because Gemini did not return a valid full curriculum list."
        )

    with closing(sqlite3.connect(db_file, timeout=30)) as conn, conn:
        conn.execute(
            "INSERT INTO generated_curriculum_chat (run_id, role, message) VALUES (?, ?, ?)",
            (run_id, "assistant", chat_result["message"]),
        )
        conn.commit()

    return chat_result["message"]


def chat_about_enhancement_review(
    db_path: str | Path,
    run_id: int,
    message: str,
    model: str = "gemini-3.5-flash-lite",
    sender_user_id: int | None = None,
    sender_username: str | None = None,
    admin_access_policy: str = "own_only",
) -> str:
    """Answer a question using one saved enhancement review as context."""
    clean_message = message.strip()
    if not clean_message:
        raise ValueError("Enhancement review message cannot be empty.")
    if sender_user_id is not None:
        configure_web_actor_api_key(db_path, sender_user_id)

    db_file = Path(db_path)
    with closing(sqlite3.connect(db_file, timeout=30)) as conn, conn:
        conn.execute(
            """
            CREATE TABLE IF NOT EXISTS generated_curriculum_chat (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                run_id INTEGER,
                role TEXT,
                message TEXT,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY(run_id) REFERENCES generated_curriculum_runs(id)
            )
            """
        )
        _ensure_chat_attribution_columns(conn)
        _require_run_access(conn, run_id, sender_user_id, admin_access_policy)
        run = conn.execute(
            """
            SELECT id, program, prompt, model_name, status, generated_at, notes
            FROM generated_curriculum_runs
            WHERE id = ?
            """,
            (run_id,),
        ).fetchone()
        if run is None:
            raise ValueError(f"Enhancement review run not found: {run_id}")

        report = _decode_stored_json(run[6])
        if not isinstance(report, dict) or "subjects" not in report:
            raise ValueError(f"Run is not an enhancement review: {run_id}")

        submitted_subjects = report.get("submitted_subjects")
        if not isinstance(submitted_subjects, list):
            subjects = conn.execute(
                """
                SELECT year, subject_title, description
                FROM generated_curriculum_subjects
                WHERE run_id = ? AND source = 'user'
                ORDER BY CAST(year AS INTEGER), id
                """,
                (run_id,),
            ).fetchall()
            submitted_subjects = [
                {"year": subject[0], "subject_title": subject[1], "description": subject[2]}
                for subject in subjects
            ]
        history = conn.execute(
            """
            SELECT role, message
            FROM generated_curriculum_chat
            WHERE run_id = ?
            ORDER BY created_at ASC, id ASC
            LIMIT 20
            """,
            (run_id,),
        ).fetchall()
        context = {
            "run": {
                "id": run[0],
                "program": run[1],
                "prompt": run[2],
                "status": run[4],
                "generated_at": run[5],
            },
            "submitted_subjects": submitted_subjects,
            "enhancement_report": report,
            "conversation": [{"role": item[0], "message": item[1]} for item in history],
        }
        conn.execute(
            """INSERT INTO generated_curriculum_chat
               (run_id, role, message, sender_user_id, sender_username)
               VALUES (?, ?, ?, ?, ?)""",
            (run_id, "user", clean_message, sender_user_id, sender_username),
        )
        conn.commit()

    chat_prompt = f"""You are a curriculum enhancement review assistant.
Answer the editor's question using only the saved review context below. Focus on
program fit, realistic year-level progression, student capability, prerequisites,
outdated content, unnecessary overlap, and actionable curriculum edits.

Do not generate a replacement curriculum. Do not silently change the saved review or
the submitted curriculum. If asked to change a subject, explain the recommended edit
and why. Be concise but specific, and distinguish evidence from your recommendation.
Return plain text, not JSON or markdown tables.

Saved review context:
{json.dumps(context, ensure_ascii=False, indent=2)}

Editor question:
{clean_message}
"""

    try:
        answer = _call_gemini_for_text(chat_prompt, model=model).strip()
    except GeminiGenerationError as error:
        print(f"Gemini enhancement review chat failed: {error}", file=sys.stderr, flush=True)
        answer = f"Unable to contact Gemini for this review question: {error}"

    with closing(sqlite3.connect(db_file, timeout=30)) as conn, conn:
        conn.execute(
            "INSERT INTO generated_curriculum_chat (run_id, role, message) VALUES (?, ?, ?)",
            (run_id, "assistant", answer),
        )
        conn.commit()

    return answer


def generate_curriculum_draft(
    program: str,
    prompt: str,
    subject_bank: Iterable[Dict[str, Any]],
    limit: int = 6,
    complete_roadmap: bool = False,
) -> List[Dict[str, Any]]:
    bank = list(subject_bank)
    if complete_roadmap:
        relevant = _pick_relevant_subjects(program, bank, limit=len(bank))
        major_subjects = [
            subject
            for subject in relevant
            if str(subject.get("classification") or "").strip().casefold()
            in _MAJOR_COURSE_CLASSIFICATIONS
        ]
        if major_subjects:
            relevant = major_subjects

        assigned: set[int] = set()
        scheduled: List[tuple[Dict[str, Any], str, str]] = []
        for year in ("1", "2", "3", "4"):
            for term in ("1", "2"):
                matching = [
                    (index, subject)
                    for index, subject in enumerate(relevant)
                    if index not in assigned
                    and f"Y{year}T{term}" in {
                        str(year_term).strip().upper()
                        for year_term in (
                            [subject.get("year_terms")]
                            if isinstance(subject.get("year_terms"), str)
                            else subject.get("year_terms") or []
                        )
                    }
                ]
                for index, subject in matching[:2]:
                    scheduled.append((subject, year, term))
                    assigned.add(index)

        for year in ("1", "2", "3", "4"):
            for term in ("1", "2"):
                current_count = sum(
                    1 for _, scheduled_year, scheduled_term in scheduled
                    if scheduled_year == year and scheduled_term == term
                )
                needed = 2 - current_count
                if needed <= 0:
                    continue

                slot_order = (int(year) - 1) * 2 + int(term) - 1
                remaining = [
                    (index, subject)
                    for index, subject in enumerate(relevant)
                    if index not in assigned
                ]

                def placement_distance(item: tuple[int, Dict[str, Any]]) -> tuple[int, int]:
                    index, subject = item
                    placements = subject.get("year_terms") or []
                    if isinstance(placements, str):
                        placements = [placements]
                    distances = []
                    for placement in placements:
                        normalized = str(placement).strip().upper()
                        if normalized.startswith("Y") and "T" in normalized:
                            try:
                                source_year, source_term = normalized[1:].split("T", 1)
                                distances.append(
                                    abs((int(source_year) - 1) * 2 + int(source_term) - 1 - slot_order)
                                )
                            except ValueError:
                                continue
                    return (min(distances) if distances else 8, index)

                for index, subject in sorted(remaining, key=placement_distance)[:needed]:
                    scheduled.append((subject, year, term))
                    assigned.add(index)

        if len(scheduled) != 16:
            raise ValueError(
                f"Cannot build a complete four-year, two-term fallback for {program}: "
                f"at least 16 distinct major-course records are required, found {len(scheduled)}."
            )
        draft_subjects = scheduled
    else:
        draft_subjects = []
        for index, subject in enumerate(_pick_relevant_subjects(program, bank, limit=limit), start=1):
            year_term = _subject_to_year_term(
                subject,
                fallback_year=str(((index - 1) // 2) % 4 + 1),
                fallback_term=str(((index - 1) % 2) + 1),
            )
            draft_subjects.append((subject, year_term["year"], year_term["term"]))

    draft: List[Dict[str, Any]] = []

    for index, (subject, year, term) in enumerate(draft_subjects, start=1):
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
                "year": year,
                "term": term,
                "subject_code": f"{program.upper()}-{index:02d}",
                "subject_title": title,
                "description": f"An applied study of {canonical}, including its core concepts, methods, and practical use in computing work.",
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
    course_skill_matches: Iterable[Dict[str, Any]] | None = None,
) -> List[Dict[str, Any]]:
    all_subjects = list(subject_bank)
    relevant_subjects = _pick_relevant_subjects(program, all_subjects, limit=limit)
    course_matches = list(course_skill_matches or [])
    evidence = sorted(
        [item for item in (skill_coverage or []) if item.get("skill_name")],
        key=lambda item: float(item.get("score") or 0),
    )[: max(limit, 6)]

    fallback_reason = "unknown Gemini failure"
    generation_mode = "online"
    try:
        draft = call_gemini_for_curriculum(
            program=program,
            prompt=prompt,
            subject_bank=relevant_subjects,
            skill_evidence=evidence,
        )
    except GeminiGenerationError as error:
        generation_mode = "offline"
        fallback_reason = str(error)
        print(f"API Error: {error}", file=sys.stderr, flush=True)
        if error.raw_response:
            print(f"API Response Body:\n{error.raw_response}", file=sys.stderr, flush=True)
        if os.environ.get("CURRICULUM_DEBUG_API", "").lower() in {"1", "true", "yes"}:
            traceback.print_exc(file=sys.stderr)
        print(f"Gemini curriculum generation failed: {fallback_reason}", file=sys.stderr)
        print(
            "[Offline Fallback] Gemini was unavailable after the retry window. "
            "Using the offline template curriculum.",
            file=sys.stderr,
        )
        draft = generate_curriculum_draft(
            program=program,
            prompt=prompt,
            subject_bank=all_subjects,
            limit=limit,
            complete_roadmap=True,
        )
        for subject in draft:
            subject["rationale"] = f"[offline template fallback] {subject['rationale']}"
    except urllib.error.HTTPError as error:
        generation_mode = "offline"
        fallback_reason = f"Gemini HTTP error {error.code}: {error.reason}"
        response_body = error.read().decode("utf-8", errors="replace")
        print(f"API Error: {fallback_reason}", file=sys.stderr, flush=True)
        if response_body:
            print(f"API Response Body:\n{response_body}", file=sys.stderr, flush=True)
        print(f"Gemini curriculum generation failed: {fallback_reason}", file=sys.stderr)
        print("[Offline Fallback] Using the offline template curriculum.", file=sys.stderr)
        draft = generate_curriculum_draft(
            program=program,
            prompt=prompt,
            subject_bank=all_subjects,
            limit=limit,
            complete_roadmap=True,
        )
        for subject in draft:
            subject["rationale"] = f"[offline template fallback] {subject['rationale']}"
    except (urllib.error.URLError, TimeoutError, OSError) as error:
        generation_mode = "offline"
        fallback_reason = f"{type(error).__name__}: {error}"
        print(f"API Error: {fallback_reason}", file=sys.stderr, flush=True)
        print(f"Gemini curriculum generation failed: {fallback_reason}", file=sys.stderr)
        print("[Offline Fallback] Using the offline template curriculum.", file=sys.stderr)
        draft = generate_curriculum_draft(
            program=program,
            prompt=prompt,
            subject_bank=all_subjects,
            limit=limit,
            complete_roadmap=True,
        )
        for subject in draft:
            subject["rationale"] = f"[offline template fallback] {subject['rationale']}"
    except Exception as error:
        generation_mode = "offline"
        fallback_reason = f"{type(error).__name__}: {error}"
        print(f"API Error: {fallback_reason}", file=sys.stderr, flush=True)
        if os.environ.get("CURRICULUM_DEBUG_API", "").lower() in {"1", "true", "yes"}:
            traceback.print_exc(file=sys.stderr)
        print(f"Gemini curriculum generation failed: {fallback_reason}", file=sys.stderr)
        draft = generate_curriculum_draft(
            program=program,
            prompt=prompt,
            subject_bank=all_subjects,
            limit=limit,
            complete_roadmap=True,
        )
        for subject in draft:
            subject["rationale"] = f"[offline template fallback] {subject['rationale']}"

    for subject in draft:
        subject["_generation_mode"] = generation_mode
        subject_title = str(subject.get("subject_title") or subject.get("display_name") or subject.get("canonical_subject") or "")
        subject_evidence = _subject_skill_evidence(subject_title, course_matches)
        subject["skill_evidence"] = subject_evidence
        mapped_industry_skills = [str(item.get("skill_name")).strip() for item in subject_evidence if item.get("skill_name")]
        subject["mapped_industry_skills"] = mapped_industry_skills

    return draft


def _build_enhancement_prompt(
    program: str,
    specialization: str,
    prompt: str,
    user_subjects: List[Dict[str, Any]],
    enhancement_report: Dict[str, Any],
    subject_bank: Iterable[Dict[str, Any]],
    skill_evidence: Iterable[Dict[str, Any]],
    selected_years: Iterable[str] | None = None,
) -> str:
    subjects = [
        {
            "canonical_subject": item.get("canonical_subject"),
            "source_colleges": item.get("source_colleges") or [],
            "year_terms": item.get("year_terms") or [],
            "classification": item.get("classification") or "",
        }
        for item in subject_bank
    ]
    weak_skills = [
        {"skill_name": item.get("skill_name"), "score": item.get("score")}
        for item in skill_evidence
    ]
    year_scope = (
        f"Generate subjects only for selected years {', '.join(selected_years)}. "
        "Complete those year plans without adding courses from unselected years."
        if selected_years is not None
        else "Make a complete four-year roadmap."
    )

    return f"""You are completing a partial university curriculum into a complete, realistic, evidence-based roadmap.
Return ONLY valid JSON: a list of subject objects, with no markdown or commentary.

Program: {program}
Specialization: {specialization}
User request: {prompt}

User-provided subjects:
{json.dumps(user_subjects, ensure_ascii=False, indent=2)}

Enhancement review and recommendations:
{json.dumps(enhancement_report, ensure_ascii=False, indent=2)}

Retrieved subject evidence:
{json.dumps(subjects, ensure_ascii=False, indent=2)}

Weakest skill-coverage evidence:
{json.dumps(weak_skills, ensure_ascii=False, indent=2)}

Apply the enhancement review when building the completed curriculum. Keep every
user-provided subject_title EXACTLY as given, except omit subjects whose review status
is "unnecessary". Do not merge or duplicate subjects. Use each review's
recommended_year for moved subjects. Revise descriptions, prerequisites, topics,
units, rationale, and placement as needed to address the review. Complete year, term,
subject_code, units, prerequisites, topics, rationale, source_colleges, and
mapped_industry_skills for every retained user subject.

Apply useful review recommendations by adding relevant subjects, avoiding titles
already present, and only when their titles match the retrieved major-course evidence.
Every ai_added title must match a retrieved major-course title or an eligible
recommendation. Do not include general education, PE, NSTP, minor courses, or courses
from another program. Use the selected program's single coherent source curriculum;
Do not combine institution-specific plans. {year_scope} Preserve confirmed year/term
placements from evidence, and use realistic prerequisite progression. Early foundational courses must precede advanced databases, networks,
security, systems architecture, capstone, and internship courses.
Include concrete tools, programming languages, platforms, or applications in each
subject's description or topics. Explain in the rationale why each subject belongs in
its year and how it prepares students for later coursework. Ground sources and skill
claims in the retrieved curriculum and industry-skill evidence.

Each object must contain the standard keys: program, year, term, subject_code,
subject_title, description, units, prerequisites, topics, rationale, source_colleges,
mapped_industry_skills, plus a source key. Topics must contain 3 to 6 specific,
assessable items. Set source to exactly "user" for subjects from the user input and
exactly "ai_added" for every subject you introduce. Keep source_colleges and
mapped_industry_skills as lists.
"""


def _fallback_enhanced_curriculum(
    program: str,
    user_subjects: List[Dict[str, Any]],
    enhancement_report: Dict[str, Any],
    subject_bank: Iterable[Dict[str, Any]],
    course_rows: Iterable[Dict[str, Any]] | None = None,
    specialization: str = "",
) -> List[Dict[str, Any]]:
    bank = list(subject_bank)
    raw_rows = list(course_rows or [])
    allowed_titles = _major_course_title_set(program, raw_rows, bank)
    fallback: List[Dict[str, Any]] = []
    assessments = {
        str(assessment.get("subject_title") or ""): assessment
        for assessment in enhancement_report.get("subjects", [])
    }
    retained_titles: set[str] = set()
    protected_course_titles: List[str] = []
    for index, subject in enumerate(user_subjects, start=1):
        title = str(subject["subject_title"])
        assessment = assessments.get(title, {})
        if assessment.get("status") == "unnecessary":
            continue
        description = str(subject.get("description") or "").strip()
        if not description:
            description = f"An applied study of {title}, including core concepts and practical computing work."
        recommended_edit = str(assessment.get("recommended_edit") or "").strip()
        rationale = recommended_edit or f"This user-provided subject supports the requested {program} curriculum."
        if assessment:
            description = f"{description} Enhancement focus: {rationale}"
        year = str(assessment.get("recommended_year") or subject.get("year") or "1")
        fallback.append(
            {
                "program": program,
                "year": year,
                "term": str(((index - 1) % 2) + 1),
                "subject_code": f"{program.upper()}-USER-{index:02d}",
                "subject_title": title,
                "description": description,
                "units": "3",
                "prerequisites": "None",
                "topics": [
                    f"Foundations of {title}",
                    f"Applied practice in {title}",
                    "Industry-aligned problem solving",
                    "Project work and evaluation",
                ],
                "rationale": f"[offline template fallback] {rationale}",
                "source_colleges": [],
                "mapped_industry_skills": [],
                "source": "user",
            }
        )
        retained_titles.add(normalize_subject_name(title))
        protected_course_titles.append(title)

    for index, recommendation in enumerate(enhancement_report.get("recommendations", []), start=1):
        title = str(recommendation.get("subject_title") or "").strip()
        year = str(recommendation.get("target_year") or "").strip()
        normalized_title = normalize_subject_name(title)
        if (
            not title
            or normalized_title in retained_titles
            or any(_course_titles_equivalent(title, existing) for existing in protected_course_titles)
            or normalized_title not in allowed_titles
            or year not in {"1", "2", "3", "4"}
        ):
            continue
        reason = str(recommendation.get("reason") or "Recommended to complete the curriculum.").strip()
        fallback.append(
            {
                "program": program,
                "year": year,
                "term": "1",
                "subject_code": f"{program.upper()}-AI-{index:02d}",
                "subject_title": title,
                "description": reason,
                "units": "3",
                "prerequisites": "None",
                "topics": [
                    f"Foundations of {title}",
                    f"Applied practice in {title}",
                    "Industry-aligned problem solving",
                    "Project work and evaluation",
                ],
                "rationale": f"[offline template fallback] {reason}",
                "source_colleges": [],
                "mapped_industry_skills": [],
                "source": "ai_added",
            }
        )
        retained_titles.add(normalized_title)
        protected_course_titles.append(title)

    major_rows = _select_offline_major_rows(program, specialization, raw_rows)
    if major_rows:
        for index, row in enumerate(major_rows, start=1):
            title = str(row["course"]).strip()
            normalized_title = normalize_subject_name(title)
            if (
                not normalized_title
                or normalized_title in retained_titles
                or any(_course_titles_equivalent(title, existing) for existing in protected_course_titles)
            ):
                continue
            university = str(row.get("university") or "Program benchmark").strip()
            fallback.append(
                {
                    "program": program,
                    "year": str(row["year"]).strip(),
                    "term": str(row["term"]).strip(),
                    "subject_code": str(row.get("id") or f"{program.upper()}-BASE-{index:02d}").strip(),
                    "subject_title": title,
                    "description": f"Major computing course benchmarked from {university}.",
                    "units": str(row.get("units") or "3").strip(),
                    "prerequisites": "None",
                    "topics": [
                        f"Core concepts in {title}",
                        f"Applied methods for {title}",
                        f"Computing practice in {title}",
                        "Project work and evaluation",
                    ],
                    "rationale": f"[offline template fallback] {row.get('classification', 'Major')} course from the {university} benchmark.",
                    "source_colleges": [university],
                    "mapped_industry_skills": [],
                    "source": "ai_added",
                }
            )
            retained_titles.add(normalized_title)
    else:
        major_bank = [
            subject
            for subject in bank
            if str(subject.get("classification") or "").strip().casefold() in _MAJOR_COURSE_CLASSIFICATIONS
        ]
        benchmark_subjects = generate_curriculum_draft(
            program=program,
            prompt="Complete the reviewed curriculum using major-course evidence.",
            subject_bank=major_bank,
            limit=max(1, len(major_bank)),
        )
        for subject in benchmark_subjects:
            normalized_title = _course_identity_key(str(subject.get("subject_title") or ""))
            if not normalized_title or normalized_title in retained_titles:
                continue
            subject["source"] = "ai_added"
            subject["rationale"] = f"[offline template fallback] {subject['rationale']}"
            subject.setdefault("mapped_industry_skills", [])
            fallback.append(subject)
            retained_titles.add(normalized_title)
    for subject in fallback:
        guidance = _practical_guidance(
            str(subject.get("subject_title") or ""),
            str(subject.get("year") or "1"),
            program,
            bank,
            [],
        )
        tools_topic = "Tools and apps: " + ", ".join(guidance["tools_and_apps"])
        topics = list(subject.get("topics") or [])
        if not any(str(topic).startswith("Tools and apps:") for topic in topics):
            if len(topics) < 6:
                topics.append(tools_topic)
            elif topics:
                topics[-1] = tools_topic
        subject["topics"] = topics
        rationale = str(subject.get("rationale") or "").strip()
        subject["rationale"] = (rationale + " " + guidance["instructional_reason"]).strip()
        description = str(subject.get("description") or "").strip()
        subject["description"] = (description + " Practical tools: " + ", ".join(guidance["tools_and_apps"]) + ".").strip()
    return fallback


def _validate_enhanced_curriculum(
    curriculum: List[Dict[str, Any]],
    user_subjects: List[Dict[str, Any]],
    enhancement_report: Dict[str, Any],
    allowed_added_titles: set[str] | None = None,
    allowed_years: Iterable[str] | None = None,
) -> None:
    allowed_year_set = {str(year).strip() for year in allowed_years} if allowed_years is not None else None
    for index, subject in enumerate(curriculum):
        if subject.get("source") not in {"user", "ai_added"}:
            raise GeminiGenerationError(
                f"Enhanced subject {index} must have source='user' or source='ai_added'."
            )
        if (
            subject.get("source") == "ai_added"
            and allowed_added_titles is not None
            and normalize_subject_name(str(subject.get("subject_title") or "")) not in allowed_added_titles
        ):
            raise GeminiGenerationError(
                f"Enhanced subject {index} is not in the selected program's major-course evidence."
            )
        if allowed_year_set is not None and str(subject.get("year") or "").strip() not in allowed_year_set:
            raise GeminiGenerationError(
                f"Enhanced subject {index} is outside the selected year scope."
            )

    assessments = {
        str(assessment.get("subject_title") or ""): assessment
        for assessment in enhancement_report.get("subjects", [])
    }
    expected_titles = [
        str(subject["subject_title"])
        for subject in user_subjects
        if assessments.get(str(subject["subject_title"]), {}).get("status") != "unnecessary"
    ]
    returned_user_subjects = [
        subject
        for subject in curriculum
        if subject.get("source") == "user"
    ]
    returned_user_titles = [
        str(subject["subject_title"])
        for subject in returned_user_subjects
    ]

    if sorted(returned_user_titles) != sorted(expected_titles):
        raise GeminiGenerationError(
            "Gemini enhancement changed, removed, or duplicated retained user-provided subjects."
        )

    for subject in returned_user_subjects:
        title = str(subject["subject_title"])
        assessment = assessments.get(title)
        if assessment is None:
            raise GeminiGenerationError(
                "Gemini marked an unknown subject as user-provided."
            )
        if assessment.get("status") == "move":
            recommended_year = str(assessment.get("recommended_year") or "").strip()
            if recommended_year and str(subject.get("year") or "").strip() != recommended_year:
                raise GeminiGenerationError(
                    f"Gemini did not move {title} to its recommended year {recommended_year}."
                )


def _build_enhancement_review_prompt(
    program: str,
    specialization: str,
    prompt: str,
    user_subjects: List[Dict[str, Any]],
    subject_bank: Iterable[Dict[str, Any]],
    skill_evidence: Iterable[Dict[str, Any]],
    selected_years: Iterable[str] | None = None,
) -> str:
    evidence = [
        {
            "canonical_subject": item.get("canonical_subject"),
            "source_colleges": item.get("source_colleges") or [],
            "year_terms": item.get("year_terms") or [],
            "classification": item.get("classification") or "",
        }
        for item in subject_bank
    ]
    skills = [
        {"skill_name": item.get("skill_name"), "score": item.get("score")}
        for item in skill_evidence
    ]
    return f"""You are reviewing an existing university curriculum. Do not generate a replacement curriculum.
Return ONLY valid JSON with this shape:
{{
  "summary": "overall assessment",
  "subjects": [
    {{
      "subject_title": "exact title from input",
      "year": "1-4",
    "recommended_year": "1-4",
      "status": "keep|revise|outdated|unnecessary|move",
      "year_fit": "why the subject does or does not fit the assigned year",
      "difficulty_fit": "appropriate|too_basic|too_advanced|unclear",
      "prerequisite_assessment": "realistic prerequisite assessment",
      "outdated_concern": "concern or none",
      "overlap_concern": "concern or none",
      "recommended_edit": "specific edit or keep recommendation",
      "skills_addressed": ["skill names"]
    }}
  ],
  "recommendations": [
    {{
      "subject_title": "optional suggested subject",
      "target_year": "1-4",
      "priority": "high|medium|low",
      "reason": "why it would improve the current curriculum"
    }}
  ]
}}

Program: {program}
Specialization: {specialization}
Editor request: {prompt}
Selected year scope: {', '.join(selected_years) if selected_years is not None else 'all years'}.
Only assess submitted subjects and recommend changes within the selected year scope.
Do not move a subject outside that scope.

Existing curriculum subjects, grouped by the editor's assigned year:
{json.dumps(user_subjects, ensure_ascii=False, indent=2)}

Retrieved curriculum evidence:
{json.dumps(evidence, ensure_ascii=False, indent=2)}

Weak industry-skill evidence:
{json.dumps(skills, ensure_ascii=False, indent=2)}

Review the current curriculum as-is. Preserve each input subject_title exactly once
in the review and report its submitted year in "year". Set "recommended_year" to the
best placement after review; use the submitted year when no move is recommended.
Judge whether each subject is realistic for the
capabilities students normally have at that point in a four-year program:
Year 1 should establish foundations; Year 2 should refine and apply core skills;
Year 3 should integrate advanced systems and specialization work; Year 4 should focus
on professional practice, capstone, internship, and advanced specialization.
Flag subjects that are too advanced, too basic, outdated, unnecessary, overlapping,
or missing realistic prerequisites. Recommendations are advisory only and must not
be added to the curriculum automatically.
"""


def _parse_enhancement_review(response: Dict[str, Any], raw_response: str = "") -> Dict[str, Any]:
    try:
        text = response["candidates"][0]["content"]["parts"][0]["text"]
    except (KeyError, IndexError, TypeError) as error:
        raise GeminiGenerationError("Gemini response did not contain review text.", raw_response) from error

    cleaned = str(text).strip()
    if cleaned.startswith("```"):
        cleaned = cleaned.split("\n", 1)[1] if "\n" in cleaned else ""
        if cleaned.endswith("```"):
            cleaned = cleaned[:-3].rstrip()
    try:
        parsed = json.loads(cleaned)
    except json.JSONDecodeError as error:
        raise GeminiGenerationError(f"Gemini returned invalid review JSON: {error.msg}", raw_response or cleaned) from error

    if not isinstance(parsed, dict) or not isinstance(parsed.get("subjects"), list) or not isinstance(parsed.get("recommendations"), list):
        raise GeminiGenerationError("Gemini review must contain subjects and recommendations lists.", raw_response or cleaned)
    allowed_statuses = {"keep", "revise", "outdated", "unnecessary", "move"}
    for index, subject in enumerate(parsed["subjects"]):
        required = {"subject_title", "year", "recommended_year", "status", "year_fit", "difficulty_fit", "prerequisite_assessment", "outdated_concern", "overlap_concern", "recommended_edit", "skills_addressed"}
        if not isinstance(subject, dict) or not required.issubset(subject) or subject["status"] not in allowed_statuses:
            raise GeminiGenerationError(f"Gemini review subject {index} is incomplete or invalid.", raw_response or cleaned)
        if (
            str(subject["year"]).strip() not in {"1", "2", "3", "4"}
            or str(subject["recommended_year"]).strip() not in {"1", "2", "3", "4"}
            or not isinstance(subject["skills_addressed"], list)
        ):
            raise GeminiGenerationError(f"Gemini review subject {index} has invalid year or skills.", raw_response or cleaned)
    for index, recommendation in enumerate(parsed["recommendations"]):
        required = {"subject_title", "target_year", "priority", "reason"}
        if not isinstance(recommendation, dict) or not required.issubset(recommendation):
            raise GeminiGenerationError(f"Gemini recommendation {index} is incomplete.", raw_response or cleaned)
    return parsed


def _fallback_enhancement_review(
    program: str,
    user_subjects: List[Dict[str, Any]],
    reason: str,
) -> Dict[str, Any]:
    return {
        "summary": f"A review could not be completed automatically: {reason}",
        "subjects": [
            {
                "subject_title": subject["subject_title"],
                "year": str(subject["year"]),
                "recommended_year": str(subject["year"]),
                "status": "revise",
                "year_fit": "Manual review required because Gemini was unavailable.",
                "difficulty_fit": "unclear",
                "prerequisite_assessment": "Manual prerequisite review required.",
                "outdated_concern": "Not assessed.",
                "overlap_concern": "Not assessed.",
                "recommended_edit": "Review this subject against the program and specialization.",
                "skills_addressed": [],
            }
            for subject in user_subjects
        ],
        "recommendations": [],
        "fallback": True,
        "program": program,
    }


def _subject_skill_evidence(
    subject_title: str,
    course_skill_matches: Iterable[Dict[str, Any]],
    minimum_score: float = 0.55,
    limit: int = 5,
) -> List[Dict[str, Any]]:
    """Select thresholded skill matches from the matching course-title records."""
    title = str(subject_title or "").strip()
    if not title:
        return []

    ignored_tokens = {
        "and", "of", "the", "for", "in", "to", "with", "fundamentals", "introduction",
        "intro", "advanced", "basic", "applied", "computer", "computing", "data",
        "learning", "technology", "technologies", "system", "systems", "analysis",
    }
    token_aliases = {
        "algorithms": "algorithm",
        "networks": "network",
        "networking": "network",
        "structures": "structure",
    }

    def evidence_tokens(value: str) -> set[str]:
        return {
            token_aliases.get(token, token)
            for token in normalize_subject_name(value).split()
            if token not in ignored_tokens
        }

    title_tokens = evidence_tokens(title)
    if not title_tokens:
        return []

    candidates: List[Dict[str, Any]] = []
    for item in course_skill_matches or []:
        skill_name = str(item.get("skill_name") or "").strip()
        course_title = str(item.get("course_title") or "").strip()
        if not skill_name or not course_title or not _course_titles_equivalent(title, course_title):
            continue
        if not title_tokens.intersection(evidence_tokens(skill_name)):
            continue
        score_value = float(item.get("score") or 0.0)
        if score_value < minimum_score:
            continue

        candidates.append({
            "skill_id": item.get("skill_id"),
            "skill_name": skill_name,
            "score": score_value,
        })

    candidates.sort(
        key=lambda item: (float(item["score"]), str(item["skill_name"]).casefold()),
        reverse=True,
    )

    selected: List[Dict[str, Any]] = []
    seen: set[tuple[str, str]] = set()
    for candidate in candidates[:limit]:
        key = (str(candidate["skill_name"]).casefold(), str(candidate.get("skill_id") or "").casefold())
        if key in seen:
            continue
        seen.add(key)
        selected.append({
            "skill_id": candidate.get("skill_id"),
            "skill_name": candidate["skill_name"],
            "score": float(candidate["score"]),
        })
    return selected


def _practical_guidance(
    subject_title: str,
    year: str,
    program: str,
    subject_bank: Iterable[Dict[str, Any]],
    course_skill_matches: Iterable[Dict[str, Any]],
) -> Dict[str, Any]:
    tool_recommendations = recommend_tools(subject_title)
    tools_and_apps = [item["tool"] for item in tool_recommendations]
    tool_reasons = [
        {"tool": item["tool"], "reason": item["reason"]}
        for item in tool_recommendations
    ]
    tool_sources = [
        f"{documentation['label']}: {documentation['url']}"
        for item in tool_recommendations
        for documentation in item["documentation"]
    ]

    year_focus = {
        "1": "establishes foundational computing and problem-solving skills needed by later core subjects",
        "2": "lets students apply Year 1 foundations to practical core computing work",
        "3": "builds on core skills through advanced systems and specialization work",
        "4": "connects prior learning to professional practice, capstone, and workplace-ready work",
    }
    instruction_reason = (
        f"{subject_title} is appropriate to teach in Year {year} because that stage "
        f"{year_focus.get(year, 'supports the next stage of the program')}"
    )
    sources = [
        "Curriculum benchmark data: curriculum-generator-kb/data/curriculum_dataset_with_ids.csv",
        "Industry skill synthesis: curriculum-generator-kb/03_industry_skills_data.md",
    ]
    relevant_skill_evidence = _subject_skill_evidence(subject_title, course_skill_matches)
    if relevant_skill_evidence:
        skill_details = [
            f"{str(item['skill_name']).strip()} (cosine similarity {float(item['score']):.3f})"
            for item in relevant_skill_evidence
            if str(item.get("skill_name") or "").strip()
        ]
        if skill_details:
            sources.append(
                "Industry-skill coverage evidence reviewed (similarity is evidence, not a competency score): "
                + ", ".join(skill_details)
            )
    else:
        sources.append("No closely matched skill evidence found.")

    program_key = normalize_subject_name(program)
    title_tokens = set(normalize_subject_name(subject_title).split()) - {"fundamentals", "introduction", "advanced"}
    benchmark_matches = []
    for item in subject_bank:
        item_program = normalize_subject_name(str(item.get("program") or item.get("program_key") or ""))
        if item_program and item_program != program_key:
            continue
        year_terms = item.get("year_terms") or []
        if isinstance(year_terms, str):
            year_terms = [year_terms]
        if not any(str(term).strip().upper().startswith(f"Y{year}T") for term in year_terms):
            continue
        benchmark_title = str(item.get("display_name") or item.get("canonical_subject") or "").strip()
        benchmark_tokens = set(normalize_subject_name(benchmark_title).split())
        overlap = len(title_tokens & benchmark_tokens)
        if benchmark_title and overlap:
            benchmark_matches.append((overlap, benchmark_title, item.get("source_colleges") or []))
    for _, benchmark_title, colleges in sorted(benchmark_matches, reverse=True)[:3]:
        college_names = ", ".join(str(college) for college in colleges if str(college).strip())
        citation = f"Benchmark match: {benchmark_title}"
        if college_names:
            citation += f" ({college_names})"
        sources.append(citation)
    sources.extend(tool_sources)
    return {
        "tools_and_apps_recommendations": tool_recommendations,
        "tools_and_apps": tools_and_apps,
        "tools_and_apps_reasons": tool_reasons,
        "instructional_reason": instruction_reason + ".",
        "sources": list(dict.fromkeys(sources)),
    }


def _enrich_enhancement_report(
    report: Dict[str, Any],
    program: str,
    subject_bank: Iterable[Dict[str, Any]],
    course_skill_matches: Iterable[Dict[str, Any]],
) -> Dict[str, Any]:
    bank = list(subject_bank)
    course_matches = list(course_skill_matches)
    report["evidence_sources"] = [
        "curriculum-generator-kb/data/curriculum_dataset_with_ids.csv",
        "curriculum-generator-kb/03_industry_skills_data.md",
    ]
    for assessment in report.get("subjects", []):
        guidance = _practical_guidance(
            str(assessment.get("subject_title") or ""),
            str(assessment.get("recommended_year") or assessment.get("year") or "1"),
            program,
            bank,
            course_matches,
        )
        assessment["tools_and_apps"] = guidance["tools_and_apps"]
        assessment["tools_and_apps_reasons"] = guidance["tools_and_apps_reasons"]
        assessment["tools_and_apps_recommendations"] = guidance["tools_and_apps_recommendations"]
        assessment["instructional_reason"] = guidance["instructional_reason"]
        assessment["sources"] = guidance["sources"]
    for recommendation in report.get("recommendations", []):
        guidance = _practical_guidance(
            str(recommendation.get("subject_title") or ""),
            str(recommendation.get("target_year") or "1"),
            program,
            bank,
            course_matches,
        )
        recommendation["tools_and_apps"] = guidance["tools_and_apps"]
        recommendation["tools_and_apps_reasons"] = guidance["tools_and_apps_reasons"]
        recommendation["tools_and_apps_recommendations"] = guidance["tools_and_apps_recommendations"]
        recommendation["instructional_reason"] = guidance["instructional_reason"]
        recommendation["sources"] = guidance["sources"]
    return report


def enhance_user_curriculum(
    program: str,
    specialization: str,
    prompt: str,
    user_subjects: List[Dict[str, Any]],
    subject_bank: Iterable[Dict[str, Any]],
    skill_coverage: Iterable[Dict[str, Any]] | None = None,
    model: str = "gemini-3.5-flash-lite",
    selected_years: Iterable[str] | None = None,
    course_skill_matches: Iterable[Dict[str, Any]] | None = None,
) -> Dict[str, Any]:
    if not isinstance(user_subjects, list) or not 1 <= len(user_subjects) <= 30:
        raise ValueError("user_subjects must contain between 1 and 30 subjects.")
    if any(
        not isinstance(subject, dict)
        or not str(subject.get("subject_title", "")).strip()
        for subject in user_subjects
    ):
        raise ValueError("Each user subject must contain a non-empty subject_title.")

    normalized_subjects = [
        {
            "subject_title": str(subject["subject_title"]),
            "description": str(subject.get("description") or ""),
            "year": str(subject.get("year") or "1").strip(),
        }
        for subject in user_subjects
    ]
    if any(subject["year"] not in {"1", "2", "3", "4"} for subject in normalized_subjects):
        raise ValueError("Each user subject year must be 1, 2, 3, or 4.")
    requested_years = sorted(
        {str(year).strip() for year in selected_years}
        if selected_years is not None
        else {subject["year"] for subject in normalized_subjects}
    )
    if not requested_years or any(year not in {"1", "2", "3", "4"} for year in requested_years):
        raise ValueError("selected_years must contain one or more years from 1 through 4.")
    if any(subject["year"] not in requested_years for subject in normalized_subjects):
        raise ValueError("Every submitted subject must belong to a selected year.")
    bank = list(subject_bank)
    evidence = sorted(
        [item for item in (skill_coverage or []) if item.get("skill_name")],
        key=lambda item: float(item.get("score") or 0),
    )[:10]
    try:
        payload, raw_response = _request_gemini_prompt(
            _build_enhancement_review_prompt(
                program,
                specialization,
                prompt,
                normalized_subjects,
                bank,
                evidence,
                requested_years,
            ),
            model,
        )
        review = _parse_enhancement_review(payload, raw_response)
        expected = sorted(subject["subject_title"] for subject in normalized_subjects)
        returned = sorted(subject["subject_title"] for subject in review["subjects"])
        if expected != returned:
            raise GeminiGenerationError("Gemini review did not assess every user-provided subject.", raw_response)
        for assessment in review["subjects"]:
            if str(assessment.get("recommended_year") or "").strip() not in requested_years:
                assessment["recommended_year"] = str(assessment["year"]).strip()
                if assessment.get("status") == "move":
                    assessment["status"] = "revise"
        review["recommendations"] = [
            recommendation
            for recommendation in review["recommendations"]
            if str(recommendation.get("target_year") or "").strip() in requested_years
        ]
        review["program"] = program
        review["specialization"] = specialization
        review["selected_years"] = requested_years
        review["fallback"] = False
        return _enrich_enhancement_report(review, program, bank, course_skill_matches or [])
    except Exception as error:
        print(f"Gemini curriculum enhancement failed: {error}", file=sys.stderr, flush=True)
        if isinstance(error, GeminiGenerationError) and error.raw_response:
            print(f"API Response Body:\n{error.raw_response}", file=sys.stderr, flush=True)
        print("[Offline Fallback] Returning the user-provided subjects with defaults.", file=sys.stderr)
        review = _fallback_enhancement_review(program, normalized_subjects, str(error))
        review["selected_years"] = requested_years
        return _enrich_enhancement_report(review, program, bank, course_skill_matches or [])


def _attach_completed_course_tools(
    report: Dict[str, Any],
    curriculum: Iterable[Dict[str, Any]],
) -> None:
    report["completed_course_tools"] = {
        title: recommend_tools(title)
        for subject in curriculum
        if (title := str(subject.get("subject_title") or "").strip())
    }


def generate_enhanced_curriculum(
    program: str,
    specialization: str,
    prompt: str,
    user_subjects: List[Dict[str, Any]],
    enhancement_report: Dict[str, Any],
    subject_bank: Iterable[Dict[str, Any]],
    skill_coverage: Iterable[Dict[str, Any]] | None = None,
    model: str = "gemini-3.5-flash-lite",
    course_rows: Iterable[Dict[str, Any]] | None = None,
    selected_years: Iterable[str] | None = None,
) -> List[Dict[str, Any]]:
    subjects = list(user_subjects)
    bank = list(subject_bank)
    raw_course_rows = list(course_rows or [])
    target_years = sorted(
        {str(year).strip() for year in selected_years}
        if selected_years is not None
        else {"1", "2", "3", "4"}
    )
    if not target_years or any(year not in {"1", "2", "3", "4"} for year in target_years):
        raise ValueError("selected_years must contain one or more years from 1 through 4.")
    if any(str(subject.get("year") or "").strip() not in target_years for subject in subjects):
        raise ValueError("Every submitted subject must belong to a selected year.")
    if selected_years is not None and set(target_years) != {"1", "2", "3", "4"}:
        raw_course_rows = [
            row for row in raw_course_rows
            if str(row.get("year") or "").strip() in target_years
        ]
        scoped_bank = []
        for subject in bank:
            year_terms = subject.get("year_terms") or []
            if isinstance(year_terms, str):
                year_terms = [year_terms]
            if any(
                str(term).strip().upper().startswith(tuple(f"Y{year}T" for year in target_years))
                for term in year_terms
            ):
                scoped_bank.append(subject)
        bank = scoped_bank
    major_rows = _select_offline_major_rows(program, specialization, raw_course_rows)
    if major_rows:
        evidence_bank = [
            {
                "canonical_subject": str(row["course"]),
                "source_colleges": [str(row.get("university") or "")],
                "year_terms": [f"Y{row['year']}T{row['term']}"],
                "classification": str(row.get("classification") or ""),
            }
            for row in major_rows
        ]
    else:
        evidence_bank = [
            subject
            for subject in _pick_relevant_subjects(program, bank, limit=len(bank))
            if str(subject.get("classification") or "").strip().casefold()
            in {"core", "professional", "specialization", "research / capstone", "internship"}
        ]
    evidence = sorted(
        [item for item in (skill_coverage or []) if item.get("skill_name")],
        key=lambda item: float(item.get("score") or 0),
    )[:10]
    supported_program_titles = _major_course_title_set(program, raw_course_rows, evidence_bank)
    generation_report = dict(enhancement_report)
    generation_report["selected_years"] = target_years
    for assessment in generation_report.get("subjects", []):
        if str(assessment.get("recommended_year") or "").strip() not in target_years:
            assessment["recommended_year"] = str(assessment.get("year") or target_years[0]).strip()
            if assessment.get("status") == "move":
                assessment["status"] = "revise"
    generation_report["recommendations"] = [
        recommendation
        for recommendation in enhancement_report.get("recommendations", [])
        if normalize_subject_name(str(recommendation.get("subject_title") or "")) in supported_program_titles
        and str(recommendation.get("target_year") or "").strip() in target_years
    ]
    allowed_added_titles = {
        normalize_subject_name(str(row.get("course") or ""))
        for row in major_rows
        if str(row.get("course") or "").strip()
    }
    if not major_rows:
        for subject in evidence_bank:
            for title in [subject.get("display_name"), subject.get("canonical_subject"), *(subject.get("subject_variants") or [])]:
                if title:
                    allowed_added_titles.add(normalize_subject_name(str(title)))
    allowed_added_titles.update(
        normalize_subject_name(str(recommendation.get("subject_title") or ""))
        for recommendation in generation_report["recommendations"]
        if str(recommendation.get("subject_title") or "").strip()
    )

    try:
        payload, raw_response = _request_gemini_prompt(
            _build_enhancement_prompt(
                program,
                specialization,
                prompt,
                subjects,
                generation_report,
                evidence_bank,
                evidence,
                target_years if selected_years is not None else None,
            ),
            model,
        )
        curriculum = _parse_gemini_response(
            payload,
            raw_response,
            expected_years=target_years if selected_years is not None else None,
        )
        _validate_enhanced_curriculum(
            curriculum,
            subjects,
            generation_report,
            allowed_added_titles=allowed_added_titles,
            allowed_years=target_years if selected_years is not None else None,
        )
        enhancement_report["draft_fallback"] = False
        _attach_completed_course_tools(enhancement_report, curriculum)
        return curriculum
    except Exception as error:
        print(f"Gemini enhanced curriculum generation failed: {error}", file=sys.stderr, flush=True)
        if isinstance(error, GeminiGenerationError) and error.raw_response:
            print(f"API Response Body:\n{error.raw_response}", file=sys.stderr, flush=True)
        print("[Offline Fallback] Saving user subjects and review recommendations as a draft.", file=sys.stderr)
        enhancement_report["draft_fallback"] = True
        enhancement_report["draft_fallback_reason"] = str(error)
        fallback = _fallback_enhanced_curriculum(
            program,
            subjects,
            generation_report,
            evidence_bank,
            course_rows=raw_course_rows,
            specialization=specialization,
        )
        if selected_years is not None:
            fallback = [subject for subject in fallback if str(subject.get("year") or "").strip() in target_years]
        _attach_completed_course_tools(enhancement_report, fallback)
        return fallback


def save_generated_curriculum(
    draft: Iterable[Dict[str, Any]],
    program: str,
    model_name: str,
    db_path: str | Path = "curriculum_matching.db",
    prompt: str = "",
    status: str = "draft",
    created_by_user_id: int | None = None,
    created_by_username: str | None = None,
    return_run_id: bool = False,
) -> int:
    draft_subjects = list(draft)
    subject_modes = [
        item.get("_generation_mode") if isinstance(item, dict) else None
        for item in draft_subjects
    ]
    generation_mode = (
        subject_modes[0]
        if subject_modes
        and subject_modes[0] in {"online", "offline"}
        and all(mode == subject_modes[0] for mode in subject_modes)
        else None
    )
    db_file = Path(db_path)
    db_file.parent.mkdir(parents=True, exist_ok=True)

    with closing(sqlite3.connect(db_file, timeout=30)) as conn, conn:
        conn.execute(
            """
            CREATE TABLE IF NOT EXISTS generated_curriculum_runs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                program TEXT,
                prompt TEXT,
                model_name TEXT,
                status TEXT,
                generated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                notes TEXT,
                source TEXT NOT NULL DEFAULT 'generated',
                generation_mode TEXT NULL
            )
            """
        )
        run_columns = {row[1] for row in conn.execute("PRAGMA table_info(generated_curriculum_runs)")}
        if "source" not in run_columns:
            conn.execute("ALTER TABLE generated_curriculum_runs ADD COLUMN source TEXT NOT NULL DEFAULT 'generated'")
            conn.execute("UPDATE generated_curriculum_runs SET source = 'generated' WHERE source IS NULL OR source = ''")
        _ensure_run_attribution_columns(conn)
        _ensure_draft_management_columns(conn)
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
                description TEXT,
                mapped_industry_skills TEXT,
                source TEXT,
                FOREIGN KEY(run_id) REFERENCES generated_curriculum_runs(id)
            )
            """
        )
        subject_columns = {
            row[1]
            for row in conn.execute("PRAGMA table_info(generated_curriculum_subjects)")
        }
        if "source" not in subject_columns:
            conn.execute("ALTER TABLE generated_curriculum_subjects ADD COLUMN source TEXT")
        conn.execute(
            """
            CREATE TABLE IF NOT EXISTS generated_curriculum_chat (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                run_id INTEGER,
                role TEXT,
                message TEXT,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY(run_id) REFERENCES generated_curriculum_runs(id)
            )
            """
        )
        _ensure_chat_attribution_columns(conn)

        run_id = conn.execute(
            """
            INSERT INTO generated_curriculum_runs (
                program, prompt, model_name, status, notes, source, created_by_user_id, created_by_username,
                generation_mode
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            """,
            (
                program, prompt, model_name, status, "Generated from retrieval-based subject bank", "generated",
                created_by_user_id, created_by_username or None, generation_mode,
            ),
        ).lastrowid

        inserted = 0
        for item in draft_subjects:
            conn.execute(
                """
                INSERT INTO generated_curriculum_subjects (
                    run_id, program, year, term, subject_code, subject_title, description, units,
                    prerequisites, topics, rationale, source_colleges, mapped_industry_skills, source
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                """,
                (
                    run_id,
                    str(item.get("program") or program).strip(),
                    str(item.get("year") or "").strip(),
                    str(item.get("term") or "").strip(),
                    str(item.get("subject_code") or "").strip(),
                    str(item.get("subject_title") or "").strip(),
                    str(item.get("description") or item.get("rationale") or "").strip(),
                    str(item.get("units") or "").strip(),
                    str(item.get("prerequisites") or "None").strip(),
                    json.dumps(item.get("topics") or [], ensure_ascii=False),
                    str(item.get("rationale") or "").strip(),
                    json.dumps(item.get("source_colleges") or [], ensure_ascii=False),
                    json.dumps(item.get("mapped_industry_skills") or [], ensure_ascii=False),
                    item.get("source"),
                ),
            )
            inserted += 1

        conn.commit()
        return int(run_id) if return_run_id else inserted


def save_enhancement_report(
    report: Dict[str, Any],
    enhanced_curriculum: Iterable[Dict[str, Any]],
    user_subjects: Iterable[Dict[str, Any]],
    program: str,
    model_name: str,
    db_path: str | Path = "curriculum_matching.db",
    prompt: str = "",
    created_by_user_id: int | None = None,
    created_by_username: str | None = None,
) -> int:
    db_file = Path(db_path)
    db_file.parent.mkdir(parents=True, exist_ok=True)
    original_subjects = list(user_subjects)
    completed_subjects = list(enhanced_curriculum)
    stored_report = dict(report)
    _attach_completed_course_tools(stored_report, completed_subjects)
    stored_report["submitted_subjects"] = original_subjects
    stored_report["enhanced_subject_count"] = len(completed_subjects)
    review_fallback = stored_report.get("fallback")
    curriculum_fallback = stored_report.get("draft_fallback")
    if review_fallback is False or curriculum_fallback is False:
        generation_mode = "online"
    elif review_fallback is True and curriculum_fallback is True:
        generation_mode = "offline"
    else:
        generation_mode = None

    with closing(sqlite3.connect(db_file, timeout=30)) as conn, conn:
        conn.execute(
            """
            CREATE TABLE IF NOT EXISTS generated_curriculum_runs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                program TEXT,
                prompt TEXT,
                model_name TEXT,
                status TEXT,
                generated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                notes TEXT,
                source TEXT NOT NULL DEFAULT 'generated',
                generation_mode TEXT NULL
            )
            """
        )
        run_columns = {row[1] for row in conn.execute("PRAGMA table_info(generated_curriculum_runs)")}
        if "source" not in run_columns:
            conn.execute("ALTER TABLE generated_curriculum_runs ADD COLUMN source TEXT NOT NULL DEFAULT 'generated'")
            conn.execute("UPDATE generated_curriculum_runs SET source = 'generated' WHERE source IS NULL OR source = ''")
        _ensure_run_attribution_columns(conn)
        _ensure_draft_management_columns(conn)
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
                description TEXT,
                mapped_industry_skills TEXT,
                source TEXT,
                FOREIGN KEY(run_id) REFERENCES generated_curriculum_runs(id)
            )
            """
        )
        columns = {row[1] for row in conn.execute("PRAGMA table_info(generated_curriculum_subjects)")}
        if "source" not in columns:
            conn.execute("ALTER TABLE generated_curriculum_subjects ADD COLUMN source TEXT")

        run_id = conn.execute(
            """
            INSERT INTO generated_curriculum_runs (
                program, prompt, model_name, status, notes, source, created_by_user_id, created_by_username,
                generation_mode
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            """,
            (
                program,
                prompt,
                model_name,
                "draft",
                json.dumps(stored_report, ensure_ascii=False),
                "enhanced",
                created_by_user_id,
                created_by_username or None,
                generation_mode,
            ),
        ).lastrowid

        for subject in completed_subjects:
            conn.execute(
                """
                INSERT INTO generated_curriculum_subjects (
                    run_id, program, year, term, subject_code, subject_title, description, units,
                    prerequisites, topics, rationale, source_colleges, mapped_industry_skills, source
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                """,
                (
                    run_id,
                    str(subject.get("program") or program).strip(),
                    str(subject.get("year") or "").strip(),
                    str(subject.get("term") or "").strip(),
                    str(subject.get("subject_code") or "").strip(),
                    str(subject.get("subject_title") or "").strip(),
                    str(subject.get("description") or subject.get("rationale") or "").strip(),
                    str(subject.get("units") or "").strip(),
                    str(subject.get("prerequisites") or "").strip(),
                    json.dumps(subject.get("topics") or [], ensure_ascii=False),
                    str(subject.get("rationale") or "").strip(),
                    json.dumps(subject.get("source_colleges") or [], ensure_ascii=False),
                    json.dumps(subject.get("mapped_industry_skills") or [], ensure_ascii=False),
                    str(subject.get("source") or "ai_added").strip(),
                ),
            )

        conn.commit()
        return int(run_id)


if __name__ == "__main__":
    rows = load_course_rows(knowledge_base_dir() / "data" / "curriculum_dataset_with_ids.csv")
    bank = build_subject_bank(rows)
    program = os.environ.get("CURRICULUM_PROGRAM", "BSIT").strip() or "BSIT"
    prompt = os.environ.get(
        "CURRICULUM_PROMPT",
        f"Generate a {program} curriculum focused on software development, databases, and networking.",
    ).strip()
    if not load_saved_api_key():
        print(
            "API key not found in environment variables or settings. "
            "Generating an explicitly marked offline template.",
            file=sys.stderr,
        )
    matches_path = Path(__file__).resolve().parent / "course_to_skill_matches.csv"
    course_skill_matches = []
    if matches_path.is_file():
        with matches_path.open("r", encoding="utf-8-sig", newline="") as handle:
            course_skill_matches = [
                {
                    "course_title": row.get("course_title", ""),
                    "skill_id": row.get("skill_id", ""),
                    "skill_name": row.get("skill_name", ""),
                    "score": float(row.get("score", 0) or 0),
                }
                for row in csv.DictReader(handle)
                if row.get("course_title") and row.get("skill_name")
            ]
    generated = generate_program_curriculum(
        program=program,
        prompt=prompt,
        subject_bank=bank,
        limit=6,
        course_skill_matches=course_skill_matches,
    )
    print(json.dumps(generated, indent=2, ensure_ascii=False))
