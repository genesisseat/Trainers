# Changes V2

## Session Summary

This coding session completed a broad frontend polish pass across the local PHP review interface and hardened the curriculum-generation and review pipelines. The project now has a consistent institutional academic visual language, direct browser review persistence, clearer Gemini diagnostics, and a bounded retry strategy for API failures.

## Academic UI Design System

Aligned `index.php`, `generated_curriculum.php`, and `review_status.php` around a shared slate/navy design system.

- Uses pale slate canvas colors, crisp table surfaces, charcoal text, subtle borders, and academic navy accents.
- Uses consistent container widths, navigation tabs, title spacing, form controls, focus states, and responsive layouts.
- Removed gradients, heavy shadows, floating glass effects, oversized pills, and other generic SaaS styling.
- Added responsive horizontal table handling for narrow screens.
- Preserved existing links, filters, forms, database queries, and PHP routing.

## Skill Coverage Table

Updated `index.php` with a structured institutional ledger layout.

- API settings, curriculum generation, filters, and coverage results are organized into clear panels.
- Coverage columns use clean borders and whole-row two-tone striping: white odd rows and `#f8fafc` even rows.
- Skill IDs, course IDs, and scores retain compact technical formatting.
- Existing sort links, skill filters, course filters, limit controls, generation form, and review handler remain intact.

## Generated Curriculum Layout

Updated `generated_curriculum.php` with a readable four-year curriculum presentation.

- Groups subjects by year and term in responsive academic tables.
- Uses compact columns for course code, title, and units.
- Displays course descriptions and compact topic bullet lists beneath each course title.
- Displays prerequisites and mapped skills as flat academic metadata instead of floating badge chips.
- Uses monospace course codes and right-aligned unit values.
- Aligns the top header and navigation structure with `index.php`.

### Curriculum Data Fields

Added persisted subject metadata for richer course rows:

- `description`
- `mapped_industry_skills`

The PHP page includes compatibility migrations for existing SQLite databases, and older rows fall back to the saved rationale when no description is present.

## Review Submission Pipeline

Fixed browser review persistence in `generated_curriculum.php`.

- Ensures `generated_curriculum_reviews` exists with `status`, `reviewer`, `notes`, and `reviewed_at` columns.
- Executes a prepared SQL `UPDATE` by `run_id` when a review already exists.
- Inserts a review row when no existing review is found.
- Updates the parent generated run status and notes.
- Preserves the existing review form field names and run ID routing.

Review status rendering in `review_status.php` now uses plain table text, with muted em-dash placeholders for empty reviewer, notes, and reviewed-at values.

## Gemini Generation and Diagnostics

Improved `curriculum_generator.py` and the PHP generation UI.

- The direct module entry point now calls `generate_program_curriculum()` instead of always calling the offline template generator.
- Supports `GEMINI_API_KEY` and `GOOGLE_API_KEY` environment variables before checking settings files.
- Reports missing or unreadable API-key configuration to stderr.
- Uses the current default model `gemini-3.5-flash-lite` for curriculum generation, chat, and program-generation paths.
- Uses the REST `:generateContent` endpoint with JSON response formatting.
- Prints exact HTTP, timeout, connection, JSON, and unexpected exception details to stderr.
- Prints available API response bodies for HTTP and Gemini response failures.
- Optional traceback diagnostics can be enabled with `CURRICULUM_DEBUG_API=1`.
- Keeps stdout reserved for the final JSON payload when running the module directly.

## Retry and Timeout Handling

Standardized the Gemini request lifecycle:

- 3 total attempts for retryable HTTP, URL, timeout, and connection failures.
- 60-second request timeout per attempt.
- Brief backoff of 2 seconds, then 4 seconds, capped at 6 seconds.
- Retryable HTTP statuses include 408, 429, 500, 502, 503, and 504.
- Non-retryable errors fail through the existing marked template fallback with diagnostics.

`index.php` allows up to 180 seconds for the generation subprocess and releases any active PHP session before starting the long-running command.

## Professional Failure Notices

Removed misleading "High AI traffic detected" messaging.

- PHP now reports precise generation failures when available.
- Offline fallback notices state that the Gemini request was unavailable and that the draft should be reviewed before approval.
- Warning banners use the same understated slate/navy institutional styling as the rest of the interface.

## Validation

Completed validation included:

- VS Code diagnostics for edited PHP and Python files: no errors.
- Python compilation with `py_compile`: passed.
- PHP lint and local page rendering: passed.
- Isolated timeout tests confirmed bounded request termination and retry behavior.
- Isolated review-handler test confirmed SQLite status, reviewer, notes, and reviewed-at persistence.
- Static checks confirmed current model defaults, clean stdout JSON behavior, and removal of misleading traffic messaging.

A live Gemini request was not used as validation because it requires network access and a valid API key.

## Important Notes for the Next Developer

- Treat generated curricula as draft recommendations requiring human review.
- Preserve the evidence chain from the subject bank and skill coverage into Gemini prompts.
- Keep the SQLite schema compatible with existing generated runs and review records.
- Keep API diagnostics on stderr so CLI stdout remains machine-readable JSON.
- The PHP interface and local shell execution are intended for trusted local development, not production exposure.
- Add mocked Gemini tests for successful responses, 401/403 authentication failures, 429/503 responses, timeout handling, malformed JSON, and fallback diagnostics.
