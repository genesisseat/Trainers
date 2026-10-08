# AI Context: Curriculum Matcher & Generator

Operating brief for AI sessions and developers. Workspace root is `C:\Trainers`; primary project files are in `embedding-matcher/`, with source data in `curriculum-generator-kb/`.

## Purpose and guardrails

The system analyzes curriculum-to-industry-skill coverage and produces structured curriculum drafts grounded in course and skill evidence. It is not a fine-tuned model or an autonomous authority. Outputs are advisory recommendations; users must verify content before use and source provenance must be retained.

## Current stack

- Python and the local `venv/` environment; PyTorch uses CUDA when available.
- Hugging Face SentenceTransformers, operational default `BAAI/bge-small-en-v1.5`.
- Google Gemini API for structured generation and enhancement assessment/chat when configured; deterministic template fallback is available when Gemini is missing or fails.
- SQLite database: `curriculum_matching.db`.
- PHP browser interface for matching, reports, generated curricula, and self-service draft management.

## Module status

| Module | Name | Status |
| --- | --- | --- |
| 1 | Data Management | Completed |
| 2 | Industry Skills | Completed |
| 3 | Benchmarking | Completed |
| 4 | Master Course List / Course Directory | Completed |
| 5 | Matching | Completed |
| 6 | Gap Analysis | Completed |
| 7 | AI Generation | Completed |
| 8 | Validation | Completed |
| 9 | Self-Service Draft Management | Ongoing |
| 10 | Reports and Dashboard | Completed |

Module 9 remains ongoing while creator-owned draft title, personal notes, and finalization controls are completed and validated. Finalized is a user's tracking status only, not approval or validation.

## Terminology and canonical grouping

Use **Master Course List / Course Directory** as the feature name; “Subject Bank Management” is retired terminology. Internal filenames and data fields still use `subject_bank` / `canonical_subject`.

`curriculum_generator_foundation.py` normalizes titles by lowercasing, expanding `&` to `and`, removing punctuation, and collapsing spaces. It deduplicates normalized names, embeds remaining title variants, and greedily groups unassigned titles when cosine similarity is at least `0.82`. The resulting canonical title is grouped with the normalized program key. Each record retains variants, source colleges, classification, year/term positions, and units. This is a heuristic grouping, not formal equivalency approval; do not discard source names or imply transitive equivalence.

## Main workflow and files

1. `curriculum-generator-kb/data/curriculum_dataset_with_ids.csv` and `03_industry_skills_data.md` provide the source courses and industry skills.
2. `match_courses_to_skills.py` embeds courses and skills, calculates cosine similarity, and writes match/coverage CSVs. Lower coverage similarity indicates a possible gap, not a validated competency score.
3. `curriculum_generator_foundation.py` builds the canonical course directory and `canonical_subject_bank.csv`.
4. `curriculum_generator.py` retrieves course and skill evidence, requests structured Gemini output, validates the curriculum, and falls back to deterministic templates when required.
5. `user_operations.py` provides matching, generation, user-curriculum enhancement, generated-curriculum chat, and enhancement-results chat.
6. `database_setup.py` initializes/imports SQLite data. Generated runs, generated courses, and chat are persisted in SQLite, with nullable creator/sender attribution snapshots on new web-created runs and user chat messages. Legacy `generated_curriculum_reviews` rows and table are retained but are no longer read or written by the application.
7. `index.php`, `draft_history.php`, `generated_curriculum.php`, `enhanced_curriculum_generated.php`, and `dataset_management.php` provide local browser workflows and views. Generated and enhanced drafts use collapsible lists with client-side search/status/program filters, self-service draft updates, and per-run browser PDF downloads. `review_status.php` remains only as a redirect to draft history. `dataset_management.php` is restricted to super admins and is currently a client-side file preview only; it performs no upload, database write, import, or activation.

Public account entry uses `landing.php`, `login.php`, and `signup.php`. The first account remains a `super_admin`, whether created by login's first-run setup or public sign-up. Public sign-ups after that are always `user`; role selection stays exclusive to super-admin account management in `admin_users.php`. Sign-up collects a unique email and username, uses the existing password hashing and CSRF helpers, includes a honeypot, and applies a session-based limit of 8 attempts per 15 minutes. Login accepts username or email. Successful sign-up logs the user in automatically. Email is not verified and is not used for password reset.

Guest trial mode is a narrow, session-only experience for unauthenticated users: the public landing page offers a `Try it out` action, which opens `generated_curriculum.php?guest=1` and supports the same enhancement flow for `enhanced_curriculum_generated.php?guest=1` only. The combined cap is 3 attempts across both pages, the results remain in session storage until signup, and the guest path uses the shared API key only. Other protected pages continue to require login; no global auth bypass is introduced.

Notable additions: four-year curriculum structure/density validation; Gemini retries and diagnostics; mapped skill evidence and source provenance; enhancement of user-submitted curricula; explain/modify chat with validation; persisted enhancement results and chat; local browser-generated PDF exports. The enhancement page lists saved reports from `generated_curriculum_runs.notes`, opens the requested `run_id` (or newest report), keeps each assistant conversation grouped by run, and includes saved completed courses in its PDF export. The core enhancement assessment and its prompt remain; the separate assistant chat and saved messages remain unchanged.

## Paths and outputs

- Project: `C:\Trainers\embedding-matcher`
- Knowledge base: `C:\Trainers\curriculum-generator-kb`
- Course data: `C:\Trainers\curriculum-generator-kb\data\curriculum_dataset_with_ids.csv`
- Skills data: `C:\Trainers\curriculum-generator-kb\03_industry_skills_data.md`
- Job-posting link data: `C:\Trainers\curriculum-generator-kb\data\job_postings.csv` contains hand-maintained and imported `posting`/`listing` links. `tools/import_job_links.py` imports URL/title/company/date fields from the `Industry_Skills_Data` and `Job_Postings_Log_113` sheets of `final_master_industry_skills_dataset.xlsx`, using `role_cluster_topic_map.csv`; the import is offline, idempotent per URL/topic association, and preserves hand-added rows. The same URL can be associated with multiple explicitly mapped topics, but recommendation selection displays it at most once. Listing pages are explicitly labeled and accepted only with `link_type=listing`; unmatched or unmapped source records are skipped. `job_topic_map.csv` continues to determine whole-word/phrase recommendation topic matches. Per recommendation, direct postings precede listings, newest first within each type, capped at five, without cross-topic borrowing. Existing enhancement report snapshots remain persisted in the existing JSON; old reports without the field remain unchanged.
- Database: `C:\Trainers\embedding-matcher\curriculum_matching.db`
- Main outputs: `course_to_skill_matches.csv`, `skill_coverage.csv`, `weakest_skills_report.csv`, `canonical_subject_bank.csv`
- PDF export helper and bundled dependencies: `embedding-matcher/assets/pdf-export.js`, `jspdf.umd.min.js`, and `jspdf.plugin.autotable.min.js`; see `embedding-matcher/assets/PDF_EXPORT_DEPENDENCIES.md` for versions and licenses.
- API key: browser workflows resolve only the current user's saved `users.gemini_api_key` first, then fall back to the shared environment key (`GEMINI_API_KEY` / `GOOGLE_API_KEY`) or existing `settings.json` via `curriculum_generator.py`'s `load_saved_api_key()`. PHP overrides the child `GEMINI_API_KEY` only when a personal key exists; without one, the subprocess inherits the environment and Python checks the existing settings paths. The account UI displays only a fixed mask and never returns a stored key in page content. Actions are blocked only when neither source has a usable key. Do not commit secrets to source or docs; personal keys remain in SQLite without encryption at rest.
- Users see an inline warning only when there is no personal, environment, or settings-file key available. The API settings form is available to logged-in users; draft deletion remains admin/super-admin-only and user management remains super-admin-only.
- Generated-run tracking: `generated_curriculum_runs.source` stores `generated` vs `enhanced`; dashboard history uses it to route to each run's source page.
- Draft management: additive, idempotent nullable `user_title`, `user_notes`, and `updated_at` columns plus `is_finalized INTEGER NOT NULL DEFAULT 0` are stored on `generated_curriculum_runs`. Creator or super_admin may update a run; only super_admin may update an unattributed run. The update POST requires CSRF, title is limited to 150 characters, notes to 5000 characters, control characters are stripped, and `updated_at` is set on each successful update. Inputs are escaped on output.
- Legacy status compatibility: stored values are never rewritten, but all old or unknown run statuses render as `Draft` unless `is_finalized = 1`, in which case the UI and PDF say `Finalized`. Old approval/rejection decisions are not shown. Deleting a run does not delete legacy review rows; SQLite foreign-key enforcement is off by default for existing app connections, so deletion leaves those rows orphaned.
- Attribution tracking: new browser-created runs store `created_by_user_id` and a `created_by_username` snapshot; new user chat entries store `sender_user_id` and `sender_username`. Assistant entries and pre-migration records remain NULL. The UI labels NULL ownership/senders as unattributed and does not fabricate attribution. Retained legacy review records are not modified.
- Run privacy: PHP run access is centralized in `auth.php`; regular users may access only their own attributed runs. `super_admin` can access every run, including NULL-owner legacy runs (shown as unattributed). `admin` can access all attributed runs under `RUN_ACCESS_ADMIN_POLICY = 'all_owned'`, but never NULL-owner runs; changing that policy to `own_only` scopes admin to their own runs in the shared helper. Only the creator or super_admin may edit draft title/notes/finalized fields; regular users may delete their own runs, and admin/super_admin may delete attributed runs according to the admin policy. Run lists, chats, dashboard counts, courses, direct links, and PDF page rendering are ownership-scoped in SQL or checked server-side; denied and nonexistent IDs share the same 404 body. Python saved-run chat verifies the actor's role and run ownership as defense in depth. Deleting a run removes its chat/course rows but intentionally leaves any legacy review rows orphaned and unchanged.
- CSRF protection: every PHP POST endpoint validates a session-bound token before processing, and every rendered POST form includes the token. Preserve this coverage when adding forms or handlers.
- Role matrix: logged-in users may generate and enhance only with a personal Gemini key; only `super_admin` may fall back to the shared key. Regular users may view, reopen, export, chat, update, and delete only their own runs. Admins may access/delete attributed runs per `RUN_ACCESS_ADMIN_POLICY`, but cannot access unattributed runs; super_admin may access all runs. Draft metadata updates remain creator-or-super_admin only. Only super_admin may manage accounts and assign roles. Public account creation is available after the first-run super-admin setup, and all later public sign-ups are regular users.
- Stage 1 per-user key storage and run-source metadata are in place. The PHP key policy is centralized in `gemini_api_key_policy()` in `auth.php`; matching authenticated web-actor enforcement lives in `configure_web_actor_api_key()` in `curriculum_generator.py`. A missing personal key blocks `user` and `admin` Generate, Enhance, and chat actions before Gemini or offline fallback is attempted. Users with a key retain the deterministic offline template fallback if Gemini fails. Super admins may use a personal key or shared key; guests remain session-only, shared-key-only, and limited to three attempts. Stored keys remain unencrypted at rest and must never be logged or rendered; the account UI uses only a fixed mask. The responsive sidebar shell is shared across the Dashboard, generation, enhancement, Skill Coverage, and super-admin user-management pages. Dashboard draft history filters by generated/enhanced source and links to each run. The four-stage UI redesign is complete; Module 9 self-service draft management remains ongoing.

## Useful commands

```powershell
cd C:\Trainers\embedding-matcher
.\venv\Scripts\Activate.ps1
python user_operations.py --model BAAI/bge-small-en-v1.5 --top-k 5
python user_operations.py --generate --program BSIT --model BAAI/bge-small-en-v1.5
python -m unittest discover -s tests
```

The tests include subject normalization/grouping, draft structure, SQLite persistence, additive draft-management migration, preservation of legacy review rows, and PHP draft-management validation helpers. Live Gemini behavior requires a valid key and network access and is not covered by the current test suite.

## Working rules

- Use the active `C:\Trainers` paths; older docs may refer to `W:\Trainers`.
- Do not fine-tune without a human-reviewed labeled dataset.
- Keep API diagnostics on stderr and preserve structured output contracts.
- Preserve SQLite compatibility and evidence/provenance through generation and enhancement.
- Recommendation-level skill evidence is selected from `course_to_skill_matches.csv` records for a normalized-equivalent course title. Matches must meet the 0.55 minimum similarity threshold and share a specific skill term with the recommendation title; global `skill_coverage.csv` best-course rows are never reused as recommendation evidence. Displayed similarity is evidence, not a competency score.
- Enhanced curriculum recommendations may include a snapshot of manually maintained and workbook-imported HTTP(S) links selected deterministically by whole-word/phrase topic keywords in the normalized recommendation name. Validated direct postings precede explicitly typed listing pages, newest dated links first within each type, deduplicated per recommendation and capped at five; recommendations never borrow from unmatched topics. These are topic-level industry-demand examples, not proof of a specific skill requirement. Selected links are saved in existing enhancement report JSON. Guest runs omit posting entries; old saved reports without the optional field remain unchanged.
- Suggested tools and apps in enhancement reviews include deterministic per-tool reasons based on the subject keyword group that selected them; these explanations do not alter Gemini prompts or schemas.
- Keep Module 9 status as ongoing; validate creator-owned draft updates and finalization behavior when changing it.
- Update this file and `PROJECT_CONTEXT.md` when architecture, paths, or module status changes.
