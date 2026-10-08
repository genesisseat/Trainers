# Project Context: Curriculum Matcher & Generator

This is the architecture handoff for `C:\Trainers\embedding-matcher`. For current module statuses and AI operating rules, see [AI_CONTEXT.md](AI_CONTEXT.md).

## Architecture

1. **Data and matching:** `match_courses_to_skills.py` reads curriculum CSV and industry-skill Markdown, embeds both with SentenceTransformers, computes cosine similarity, and writes course-skill matches and coverage reports.
2. **Master Course List / Course Directory:** `curriculum_generator_foundation.py` normalizes and clusters equivalent course titles, then aggregates course variants and source metadata into `canonical_subject_bank.csv`.
3. **Generation and validation:** `curriculum_generator.py` retrieves course-bank and weak-skill evidence, requests structured Gemini output, validates the result, and uses a deterministic template fallback when the API cannot be used.
4. **Persistence and draft management:** `database_setup.py` and generator helpers store matching data, generated runs/courses, and chat in `curriculum_matching.db`. Creator-owned title, notes, and finalized fields are additive run columns.
5. **Browser and CLI:** `user_operations.py` exposes matching, generation, enhancement, and chat commands; PHP pages provide the dashboard, source-filtered `draft_history.php`, generated-curriculum view, enhancement results, skill-coverage evidence, super-admin user management, and dataset preview. The retired `review_status.php` URL redirects to draft history.
6. **Public account entry:** `landing.php`, `login.php`, and `signup.php` provide the public landing page and authentication flow. The initial account remains a `super_admin` through either login first-run setup or sign-up; later public accounts are always `user`. Sign-up requires unique username and email values, uses `auth.php` password/CSRF helpers, a honeypot, and a session-based attempt limit. Login accepts either username or email; email is not verified and is not used for recovery. Super-admin account creation and role assignment remain in `admin_users.php`.

## Canonical course grouping

Course titles are lowercased, `&` is normalized to `and`, punctuation is removed, and whitespace is collapsed. Exact normalized duplicates are deduplicated before embedding. Remaining unique titles are greedily clustered when cosine similarity is at least `0.82`; clusters are then grouped per normalized program. The canonical name is seeded by the first title in the cluster. The bank retains title variants, source colleges, classifications, year/term placements, and units. This is a similarity heuristic; preserve provenance and do not present it as formal equivalency approval.

## Gemini behavior

- Default content-generation model in the current implementation: `gemini-3.5-flash-lite`.
- Logged-in `user` and `admin` accounts require their own `users.gemini_api_key` for Generate, Enhance, and chat. Only `super_admin` may fall back to the shared `GEMINI_API_KEY` / `GOOGLE_API_KEY` environment value or existing `settings.json`; guests remain shared-key-only with session-only drafts and a three-attempt cap. PHP centralizes the role decision in `gemini_api_key_policy()` in `auth.php`, and authenticated Python web-actor paths enforce the matching rule in `configure_web_actor_api_key()` in `curriculum_generator.py`. Missing personal keys block the action before Gemini or offline fallback. With an allowed key, the deterministic offline template fallback remains available if Gemini fails. The account UI shows only key-present state and a fixed `********` mask; it does not render any stored key value. Personal keys remain unencrypted at rest.
- The responsive dark sidebar shell is shared by the Dashboard, generation, enhancement, Skill Coverage, and super-admin user-management pages. Dashboard counts, sidebar history, and Draft History are SQL-scoped to the viewer's accessible runs; the full coverage table is shared reference data. Draft history links generated/enhanced runs using their persisted `source` value. The four-stage UI redesign is complete; Module 9 self-service draft management remains ongoing.
- Requests use the Google Generative Language API through Python's standard library.
- Transient failures use bounded retries; failures or invalid output are diagnosed and fall back to the template generator.
- Gemini output is structured and validated, including a four-year roadmap and term-density rules. Do not claim a live API call has been verified unless one was actually run.

## SQLite and browser draft management

The SQLite database stores courses, skills, match runs/results, coverage, generated curriculum runs and courses, and generated-curriculum chat. New run records store nullable creator ID/name snapshots, and human chat messages store nullable sender ID/name snapshots; legacy rows and assistant messages remain unattributed. NULL creator/sender values are displayed as unattributed rather than inferred. Run migrations add owner/query indexes and draft fields without rewriting existing rows. Regular users access only their own runs and may update or delete their own; super_admin may access all runs, including NULL-owner legacy runs. Admin may access/delete attributed runs under the single `RUN_ACCESS_ADMIN_POLICY = 'all_owned'` setting in `auth.php`, but cannot access NULL-owner rows. Draft title/notes/finalized updates remain creator-or-super-admin only. Update POSTs validate CSRF, title/notes lengths, remove control characters, and update the timestamp. Finalized means personal tracking only.

The legacy `generated_curriculum_reviews` table and rows are preserved but no longer read or written in the application. Legacy run statuses such as approved/rejected/needs revision remain stored unchanged and display as Draft unless `is_finalized` is set. Deleting a run leaves its historical review rows orphaned; existing SQLite connections use the default `foreign_keys=OFF`, so these rows do not block deletion.

PHP POST forms and handlers use a session-bound CSRF token; keep both validation and hidden form fields in sync when adding actions. Run lists, history, dashboard counts, chat and course reads are scoped in SQL; direct run URLs, updates, deletes and export pages enforce the same policy server-side. Inaccessible and nonexistent IDs return the same 404 status/body. Python saved-run chat verifies the actor's persisted role and run ownership. The application no longer reads or writes legacy review rows; run deletion leaves them orphaned and unchanged.

Public sign-up is available after first-run setup. `create_signup_user()` assigns the first account `super_admin` and all later sign-ups `user` within a SQLite write transaction; the form accepts no role field. Email is stored case-insensitively in a unique nullable column so existing admin-created accounts without an email remain compatible. This preserves the initial super-admin behavior while preventing public role escalation. Admin-created accounts remain available through `admin_users.php`.

PHP pages include `index.php`, `generated_curriculum.php`, `enhanced_curriculum_generated.php`, `skill_coverage.php`, super-admin-only `admin_users.php`, and `dataset_management.php`. The old `review_status.php` route redirects to draft history. Generated and enhanced runs can be downloaded as browser-generated PDFs using local jsPDF assets. Dataset Management currently previews client-selected files only; it does not upload, persist, import, or activate datasets. Module 9 (Self-Service Draft Management — Ongoing) remains ongoing per the project checklist.

Generated curriculum runs include a `source` field (`generated` for standard drafts and `enhanced` for enhancement flows); the shared sidebar uses it to route recent history items to the appropriate run page.

Generated curricula and enhancement reports are recommendations, not authoritative requirements. Keep mapped-skill evidence and college provenance visible so users can verify recommendations. The PHP interface is intended for trusted local use.

## Additional implemented workflows

- Enhance a user-provided partial curriculum with Gemini-supported recommendations.
- Chat about a generated curriculum; support explanations and validated full-curriculum modifications.
- Chat about saved enhancement results; existing chat backend, prompts, and messages remain unchanged.
- Persist generated/enhanced drafts, personal draft notes, assistant chat history, source colleges, and mapped industry skills.

## Current locations

- Workspace: `C:\Trainers`
- Project: `C:\Trainers\embedding-matcher`
- Curriculum source: `C:\Trainers\curriculum-generator-kb\data\curriculum_dataset_with_ids.csv`
- Skill source: `C:\Trainers\curriculum-generator-kb\03_industry_skills_data.md`
- Database: `C:\Trainers\embedding-matcher\curriculum_matching.db`

Older notes may contain `W:\Trainers` paths. Use paths relative to the active `C:\Trainers` workspace when working here.