# Developer Guide

This guide is for developers working on the curriculum matching and generation system.

## Project architecture

The project has three major layers:

1. Data ingestion and matching
   - reads course and skill sources
   - embeds text with sentence-transformer models
   - computes similarity scores

2. Evidence layer
   - builds skill coverage summaries
   - exports weak skills and gap reports
   - stores results in SQLite

3. Curriculum generation and draft management
   - builds a canonical subject bank
   - clusters subject variants
   - drafts a structured program curriculum
   - stores generated subject records and creator-owned draft metadata

## Key source files

- match_courses_to_skills.py
  - main embedding pipeline
  - similarity scoring and CSV export

- curriculum_generator_foundation.py
  - subject normalization and clustering logic

- curriculum_generator.py
  - structured generation, enhancement assessment, and saved chat
- draft_management.php
  - additive run metadata migration, update validation, status labels, and ownership checks

- database_setup.py
  - database schema and data import

- user_operations.py
  - primary CLI entry point for end users

- index.php
  - Dashboard with draft counts, source-filterable generated/enhanced history, and weakest-skill coverage preview
- draft_history.php
  - source-filterable generated/enhanced run history
- landing.php, login.php, and signup.php
  - public landing, login, first-account setup, and self-service account creation
- auth.php
  - shared SQLite user storage, password hashing, CSRF helpers, authentication, role-safe account creation, and centralized run authorization
- admin_users.php
  - super-admin-only account and role management; admin-created accounts remain supported alongside public sign-up

- generated_curriculum.php and enhanced_curriculum_generated.php
  - shared sidebar shell, ownership-scoped draft histories, creator-owned title/notes/finalized controls, and browser-generated PDF downloads

- skill_coverage.php
  - searchable, type-filterable, sortable view of saved skill-coverage evidence

- dataset_management.php
  - super-admin-only dataset file preview; this page intentionally has no upload or persistence handler

- assets/pdf-export.js
  - creates PDF documents in the browser from the selected run's rendered content
  - uses the locally bundled jsPDF and jsPDF-AutoTable assets; versions and licenses are documented in `assets/PDF_EXPORT_DEPENDENCIES.md`

## Local environment setup

From the project root:

```powershell
cd C:\Trainers\embedding-matcher
python -m venv venv
.\venv\Scripts\Activate.ps1
pip install -r requirements.txt
```

If there is no requirements file yet, install the known working dependencies:

```powershell
pip install torch sentence-transformers pandas notebook jupyter
```

## Important paths

- Project root: C:\Trainers\embedding-matcher
- Curriculum data: C:\Trainers\curriculum-generator-kb\data\curriculum_dataset_with_ids.csv
- Skill list: C:\Trainers\curriculum-generator-kb\03_industry_skills_data.md
- Local DB: C:\Trainers\embedding-matcher\curriculum_matching.db

## Database design

The SQLite schema should store:

- courses
- skills
- model_runs
- course_skill_matches
- skill_coverage
- generated_curriculum_runs, including `source` values of `generated` and `enhanced`
- nullable `created_by_user_id` / `created_by_username` attribution snapshots on curriculum runs
- generated_curriculum_subjects
- legacy generated_curriculum_reviews table/rows (preserved, no longer read or written)
- generated_curriculum_chat, including nullable sender ID/name snapshots for user messages
- generated_curriculum_runs.user_title, user_notes, is_finalized, and updated_at (additive draft-management fields)
- users.gemini_api_key for per-user Gemini key storage and nullable email for sign-up accounts

## Account creation and access

- `landing.php` is the public entry page linking to login and sign-up. The public auth pages use the shared dark dashboard color palette and `assets/rene.jpg` background.
- The first account can still be created through the first-run flow in `login.php`; it becomes a `super_admin`.
- `signup.php` uses the same first-account rule. Account creation is serialized with a SQLite `BEGIN IMMEDIATE` transaction so simultaneous first-run requests cannot both claim the first-account role. Once an account exists, public sign-ups are hard-coded to the `user` role; the form accepts no role value.
- Public sign-up requires a unique username matching the same 3-40 character username rule, a unique valid email address, and a password of at least 8 characters used by `admin_users.php` through `create_user()`. Email addresses are stored case-insensitively; existing admin-created accounts without email addresses continue to work with username login. Passwords continue to use `password_hash(..., PASSWORD_DEFAULT)` and authentication uses `password_verify()`.
- Guest trial mode is intentionally limited to unauthenticated access on `generated_curriculum.php?guest=1` and `enhanced_curriculum_generated.php?guest=1`. The combined rule is three attempts total across both pages, stored in session state only, with all guest output kept in session memory until sign-up. Guests must use the shared API key only; if no shared key is configured, the trial is blocked with a clear message.
- Guest draft carry-over is performed only on successful signup through the normal `save_generated_curriculum()` / `save_enhancement_report()` persistence flow. No data is written to SQLite before the user creates an account, and the signup redirect lands on the saved run so the newly created user sees the imported draft immediately.
- Sign-up validates the session CSRF token, includes an off-screen honeypot, and limits a browser session to 8 sign-up attempts per 15 minutes. Honeypot submissions are silently ignored. These are lightweight safeguards, not a full anti-abuse system.
- Successful sign-up logs the new account in and redirects to the Dashboard. Password recovery remains a super-admin responsibility; there is no email verification or reset flow.
- Login accepts either username or the email address collected during sign-up. Email addresses are not used for verification or password recovery.
- `admin_users.php` remains super-admin-only and continues to create and manage accounts, including role assignment. Public sign-up cannot grant `admin` or `super_admin` after initial setup.

Attribution columns are additive migrations. Existing rows remain NULL and display a neutral "Unattributed" / "Unattributed sender" label; do not infer historic ownership. New browser generation and enhancement runs store the authenticated user ID and username snapshot. User chat messages store the authenticated sender ID and username; assistant messages do not represent a human sender.

Use the database as the source of truth for curriculum provenance and self-service draft metadata. Do not display legacy approval/rejection status values in the UI.

Browser generation, enhancement, and AI chat/edit require the logged-in `user` or `admin` to have a personal `users.gemini_api_key`; these roles never fall back to a shared key. Only `super_admin` may use the shared `GEMINI_API_KEY` / `GOOGLE_API_KEY` environment value or existing `settings.json` when no personal key is saved. The role policy is centralized in PHP `gemini_api_key_policy()` in `auth.php` and mirrored by Python `configure_web_actor_api_key()` in `curriculum_generator.py`; PHP handlers and authenticated Python web-actor operations both enforce it before invoking Gemini. A keyless `user` or `admin` is blocked before Gemini and before the offline template fallback, with the same message and a link to the profile key setting. With an allowed key, the deterministic offline template fallback still applies if Gemini fails. Guest trial mode remains session-only, shared-key-only, and capped at three combined attempts. Personal keys are stored in SQLite without encryption at rest; the UI displays a fixed `********` mask and must not log or echo stored keys.

## Development workflow

### Matching workflow

```powershell
python match_courses_to_skills.py --model BAAI/bge-small-en-v1.5 --top-k 5
```

### User-facing workflow

```powershell
python user_operations.py --model BAAI/bge-small-en-v1.5 --top-k 5
```

### Draft generation workflow

```powershell
python user_operations.py --generate --program BSIT --prompt "Generate a BSIT curriculum focused on software development, databases, and networking." --model BAAI/bge-small-en-v1.5 --top-k 5
```

### Self-service draft management

In the browser, the run creator can edit the draft title and personal notes and toggle Finalized. Finalized is personal tracking only; it is not approval or validation. The update handler enforces ownership and CSRF, limits titles to 150 characters and notes to 5000 characters, and records `updated_at`. Super admins can update unattributed drafts.

## Coding principles

- Prefer retrieval and evidence-based generation.
- Keep generated curriculum outputs structured.
- Preserve source provenance for each planned subject or recommendation.
- Do not overfit to one model; compare at least two when possible.
- Keep the system local-first for quick work.

The generation, enhancement, and AI-edit pages show an inline warning only when no personal, environment, or settings-file key is available. The Dashboard sidebar account menu manages the current user's key with the existing `save_user_api_key()` and `clear_user_api_key()` helpers; it never displays the saved key value.

Every PHP POST handler validates a session-bound CSRF token before processing the submitted action. Every POST form, including login, account-key, draft-update, chat, and user-management forms, must include `csrf_token_field()`.

Browser role matrix: logged-in `user` and `admin` accounts may generate, enhance, and chat only with a personal Gemini key; only `super_admin` may fall back to the shared key. Regular users may view, reopen, export, chat, update, and delete only their own runs. Super admins may access every run, including NULL-owner legacy runs, which remain unattributed. Admins may access and delete attributed runs owned by any account under `RUN_ACCESS_ADMIN_POLICY = 'all_owned'` in `auth.php`; they cannot access NULL-owner runs. Draft updates remain creator-or-super-admin only. User and dataset management remain super-admin-only.

Every page and POST operation that addresses a saved run must enforce access server-side using `run_access_require()` / `run_access_sql_scope()` from `auth.php`. Lists, counts, run chat, and related curriculum rows must be SQL-scoped rather than filtered in PHP. Inaccessible and nonexistent run IDs use the same 404 response. Python saved-run chat also checks the actor's persisted role and run ownership. Keep the admin policy in the central PHP helper; changing it to `own_only` narrows admin reach in one place.

Deletion removes run courses and chat but intentionally leaves legacy `generated_curriculum_reviews` rows orphaned and unchanged. Personal API keys are stored per account, never rendered into the account page or response, and remain unencrypted at rest.

The shared sidebar shell is used by the Dashboard, generated-curriculum, enhancement-results, Skill Coverage, and super-admin user-management pages. Its recent-history links open the corresponding generated or enhanced draft. Skill Coverage is a dedicated searchable/filterable/sortable view; the Dashboard retains only a weakest-skill preview. Dashboard draft history distinguishes and filters generated/enhanced runs and links back to the source run. The retired `review_status.php` URL redirects to generated draft history. User Management is shown only to super admins, and Dataset Management is intentionally absent from the sidebar. The four-stage UI redesign is complete; continue treating Module 9 self-service draft management as ongoing.

## Testing expectations

The project already includes tests for subject clustering and curriculum generation. Run them with:

```powershell
cd C:\Trainers\embedding-matcher
.\venv\Scripts\Activate.ps1
python -m unittest discover -s tests
```

## Browser UI development

The browser app is served through PHP and is suitable for local draft-management operations. Keep the UI simple and evidence-oriented:

- show skill coverage
- show weak skills summary
- show Draft or Finalized only; legacy approval/rejection status values must display as Draft without rewriting storage
- restrict title, personal notes, and Finalized updates to the run creator or super_admin (super_admin only for unattributed runs)
- keep the visible advisory disclaimer on generated pages and exported PDFs
- keep PDF downloads scoped to the selected run and avoid sending curriculum data to external services
- preserve the explicit preview-only boundary on dataset management until a validated backend workflow is implemented

## Guardrails and future direction

The design should remain:

- evidence-first
- human-reviewable
- retrieval-based before generation
- structured rather than freeform output

Avoid making the generator look fully authoritative until review and evidence pipelines that support it are in place.

## When adding features

Before adding a large feature, confirm:

1. what source evidence it uses
2. where it is stored (CSV, SQLite, or both)
3. how a human will review or override it
4. whether the new feature changes the output contract

## Common maintenance tasks

- update the knowledge-base paths if they move
- regenerate CSV outputs after source data changes
- rebuild the SQLite schema when adding new output tables
- confirm the PHP interface still matches current database layout
- keep README.md, USER_GUIDE.md, AI_CONTEXT.md, and DEVELOPER_GUIDE.md in sync
