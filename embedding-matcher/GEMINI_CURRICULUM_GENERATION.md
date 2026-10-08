# Gemini Curriculum Generation

## What Changed

`curriculum_generator.py` now supports real curriculum content generation through the Google Gemini REST API while preserving the existing subject-bank retrieval, SQLite persistence, and review workflow.

The following capabilities were added:

- API-key loading compatible with `index.php`.
- Gemini curriculum generation through `gemini-3.5-flash-lite` by default.
- Focused prompts containing the user request, program, retrieved subjects, and weakest skill evidence.
- Defensive parsing and validation of Gemini JSON responses.
- Automatic offline fallback to the existing template generator when no key is configured or the API call fails, with the failure reason recorded.
- Diagnostic logging to stderr for Gemini failures, including the raw response body when available.
- Continued `skill_evidence` metadata on generated rows for the existing UI and workflow.

No changes were made to `database_setup.py`, the SQLite schema, or the PHP review workflow.

## API-Key Configuration

The Python generator reads the key from the same location as the PHP application:

```text
%APPDATA%\\CurriculumMatcher\\settings.json
```

The expected JSON structure is:

```json
{
  "api_key": "your-gemini-api-key"
}
```

If `APPDATA` is unavailable, the loader falls back to:

1. `$HOME/CurriculumMatcher/settings.json`
2. `./CurriculumMatcher/settings.json`

The key can be saved through the API settings form in `index.php`.

## Generation Flow

`generate_program_curriculum()` follows this process:

1. Filter the subject bank to subjects relevant to the requested program.
2. Sort supplied skill-coverage rows by ascending score, where lower scores represent weaker coverage.
3. Keep only a focused set of the weakest skill rows.
4. Call `call_gemini_for_curriculum()` with the program, user prompt, retrieved subjects, and weak-skill evidence.
5. Attach the selected skill evidence to every returned subject so existing consumers retain the same metadata.
6. Return the generated list for the existing `save_generated_curriculum()` function.

The Gemini prompt includes these subject fields:

- `canonical_subject`
- `source_colleges`
- `year_terms`
- `classification`

It also includes these skill fields:

- `skill_name`
- `score`

## Gemini Response Contract

Gemini is instructed to return only a JSON list. Every subject object must contain:

- `program`
- `year`
- `term`
- `subject_code`
- `subject_title`
- `units`
- `prerequisites`
- `topics`
- `rationale`
- `source_colleges`

`topics` must contain 3 to 6 specific topic strings. `rationale` must identify the weak skill or skills addressed by the subject and explain why.

The response parser removes Markdown code fences if Gemini adds them, then validates the JSON type, required keys, topic count, and source-college list. Invalid or incomplete responses raise a clear error instead of being silently persisted.

## Offline Fallback

If the API key is missing, the request times out, the API returns an error, or the response cannot be parsed or validated, generation falls back to `generate_curriculum_draft()`.

Fallback rows are marked in the rationale with:

```text
[template fallback — <actual failure reason>]
```

The actual failure is also written to stderr in the form:

```text
Gemini curriculum generation failed: <actual failure reason>
```

When Gemini returned a response body, it is printed after the failure message under a `Raw Gemini response:` label. This covers missing keys, HTTP errors, network and timeout errors, invalid API JSON, malformed generated JSON, and schema validation failures. The fallback keeps the prototype usable without network access while making the failure visible in the UI and terminal output.

## API Request Settings

The implementation uses the standard-library `urllib` client, so no additional Python dependency is required.

- Endpoint: Google Generative Language API `generateContent`
- Default model: `gemini-2.0-flash`
- Temperature: `0.3`
- Request timeout: `45` seconds
- Requested response MIME type: `application/json`

## Diagnostics and Validation

Editor diagnostics reported no errors in the edited Python module. The project environment does not currently include `pytest`; runtime test execution should be performed after the Gemini key and network access are configured.
