# Changes V1

## Session Summary

This coding session upgraded the curriculum-generation prototype with Gemini-based generation, interactive curriculum chat, curriculum editing, stronger roadmap rules, and improved diagnostics.

## Gemini Curriculum Generation

Updated `curriculum_generator.py` to call Google Gemini through the REST API.

- Uses the saved API key from the existing user settings location.
- Also supports a project-local `settings.json` fallback.
- Current default chat/generation model: `gemini-3.1-flash-lite`.
- Uses the required `:generateContent` endpoint format.
- Sends the user prompt, program, retrieved subject-bank evidence, and weakest skill evidence.
- Uses JSON response formatting and defensive response parsing.
- Preserves the existing template fallback when Gemini is unavailable.

## API-Key Diagnostics

API-key loading now reports clear stderr diagnostics when settings files are missing, unreadable, malformed, or empty. It checks:

1. `%APPDATA%\CurriculumMatcher\settings.json`
2. `$HOME\CurriculumMatcher\settings.json`
3. The project root `settings.json`

Gemini HTTP, network, timeout, JSON, and schema errors are also reported with the actual failure reason and raw response when available.

## Four-Year Curriculum Roadmap

The initial Gemini prompt was expanded to request a complete academic roadmap:

- Four years of curriculum coverage.
- Semester structure by default, with trimester/custom calendar support when requested.
- Major BSIT professional subjects only.
- Excludes GenEd, PE, NSTP, minor, and unrelated elective subjects.
- Realistic progression from foundations in Years 1–2 to advanced systems, security, architecture, internship, and capstone work in Years 3–4.
- Required subject attributes include year, term, course code, title, units, prerequisites, topics, rationale, source colleges, and mapped industry skills.

The validator rejects incomplete roadmaps, missing years, inconsistent term counts, invalid topic/skill lists, and invalid subject density.

### Term Density Rules

- Years 1–2: exactly 2 or 3 major subjects per term.
- Years 3–4: 1 to 3 subjects per term, allowing capstone or internship terms.
- Gemini is explicitly warned that density violations cause automated rejection.

## Interactive Curriculum Chat

Added chat functionality to `curriculum_generator.py` and `user_operations.py`.

CLI example:

```powershell
python user_operations.py --chat --run-id 1 --message "Add stronger cloud security coverage."
```

The chat handler:

- Loads the selected curriculum run from SQLite.
- Includes the saved subjects and weakest skill evidence in the Gemini context.
- Saves both user and assistant messages.
- Supports Explain Mode for questions and conversational requests.
- Supports Modify Mode when Gemini returns a complete replacement curriculum.
- Validates modified subjects before saving them.
- Replaces the existing run subjects only when the complete updated curriculum is valid.
- Treats plain-text or malformed Gemini output as an explanation instead of breaking the database.

## Database Changes

Added the `generated_curriculum_chat` table to `database_setup.py` and `curriculum_generator.py`:

```text
id
run_id
role
message
created_at
```

Existing curriculum, review, and matching tables were preserved.

## PHP Browser Chat Interface

Updated `generated_curriculum.php` with a temporary browser chat interface.

- Displays user and assistant chat history per curriculum run.
- Uses distinct styling for each role.
- Adds a message textarea and `Send Question / Request Edit` button.
- Executes the Python chat CLI with the selected run ID and message.
- Uses a POST-redirect flow so new messages appear after reload.
- Preserves the existing curriculum display and review controls.

## Documentation Added

Added project documentation files:

- `GEMINI_CURRICULUM_GENERATION.md`: Gemini generation behavior and diagnostics.
- `PROJECT_CONTEXT.md`: broad project architecture and AI handoff context.
- `SUMMARY/CHANGES V1.md`: this concise session summary.

## Validation

Completed validation included:

- VS Code diagnostics for edited Python files: no errors.
- Python compilation with `py_compile`: passed.
- CLI help verification for chat options.
- Static checks for the new Gemini model, endpoint, chat table, and density rules.

A full live Gemini request was not used as a test because it requires network access and a valid API key. PHP linting was unavailable in the terminal because PHP was not on the PATH.

## Important Notes for the Next Developer

- Keep generated curriculum rows compatible with `save_generated_curriculum()` and the existing SQLite schema.
- Preserve the retrieval evidence chain from subject bank and skill coverage into Gemini prompts.
- Treat Gemini output as draft content requiring human review.
- Keep the offline template fallback working.
- The PHP interface is intended for local testing, not production exposure.
- Add mocked Gemini tests next for explanation responses, valid modifications, invalid modifications, HTTP failures, and plain-text fallback behavior.
