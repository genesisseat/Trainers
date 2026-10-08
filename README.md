# Curriculum Matcher & Generator

Local-first curriculum analysis and evidence-based curriculum drafting for the Trainer workflow. The system matches courses to industry skills, reports coverage gaps, creates a canonical course directory, and generates structured curriculum recommendations for human review.

## Current setup

- Workspace: `C:\Trainers`
- App: `C:\Trainers\embedding-matcher`
- Knowledge base: `C:\Trainers\curriculum-generator-kb`
- Inputs: `curriculum-generator-kb/data/curriculum_dataset_with_ids.csv` and `curriculum-generator-kb/03_industry_skills_data.md`
- Runtime: Python virtual environment, Hugging Face SentenceTransformers (`BAAI/bge-small-en-v1.5`), Gemini API with offline template fallback, SQLite (`curriculum_matching.db`), and a PHP browser interface.

## Module status

| # | Module | Status |
| --- | --- | --- |
| 1 | Data Management | Completed |
| 2 | Industry Skills | Completed |
| 3 | Benchmarking | Completed |
| 4 | Master Course List / Course Directory | Completed |
| 5 | Matching | Completed |
| 6 | Gap Analysis | Completed |
| 7 | AI Generation | Completed |
| 8 | Validation | Completed |
| 9 | Browser Review, Approval/Rejection, Notes Tracking | Ongoing |
| 10 | Reports and Dashboard | Completed |

Module 9 is the current workstream. Review forms and database persistence are present; keep the module status ongoing while this workflow is being completed.

## Additional capabilities

- Gemini generation with structured output checks, bounded retries, diagnostics, and deterministic offline fallback.
- User-curriculum enhancement, plus chat for explaining or requesting validated changes to generated curricula and enhancement reports.
- Browser histories for generated drafts and enhancement reviews use collapsible, client-filtered run lists; enhancement reports and assistant chats remain associated with their runs.
- Generated drafts and enhancement reviews can be downloaded as PDFs with a preset filename; the browser controls the download destination.
- Super admins can preview dataset file selections in the browser, but uploads and dataset activation are not implemented yet.
- Traceable course variants/source colleges and mapped industry-skill evidence in generated results.
- SQLite persistence for matches, coverage, generated runs, reviews, and chat history.

## Terminology

Use **Master Course List / Course Directory** instead of “Subject Bank Management.” Internally, exact normalized duplicates are removed, unique course titles are embedding-clustered at cosine similarity `>= 0.82`, and canonical groups are separated by normalized program. Variants and source metadata are retained; similarity grouping is heuristic and should not be treated as formal equivalency approval.

## Run locally

```powershell
cd C:\Trainers\embedding-matcher
.\venv\Scripts\Activate.ps1
python user_operations.py --model BAAI/bge-small-en-v1.5 --top-k 5
python -m unittest discover -s tests
```

Start the local PHP server with the installed PHP executable, then open `http://127.0.0.1:8000/`. Gemini is optional: configure `GEMINI_API_KEY` or `GOOGLE_API_KEY`, or save the key through the local PHP settings interface. Without a usable API, generation uses the marked template fallback.

## Key files

- `embedding-matcher/user_operations.py`: matching, generation, enhancement, chat, and review CLI.
- `embedding-matcher/curriculum_generator.py`: Gemini integration, fallback, validation, and persistence.
- `embedding-matcher/curriculum_generator_foundation.py`: course normalization and canonical grouping.
- `embedding-matcher/index.php`: coverage dashboard and browser operations.
- `embedding-matcher/AI_CONTEXT.md`: detailed AI handoff and current implementation context.

## Guardrails

- Embeddings are pretrained; there is no fine-tuned curriculum model.
- Similarity scores are evidence signals, not competency measurements.
- Generated curricula are drafts, not authoritative requirements; preserve source evidence and human review.
- The PHP interface is for trusted local use, not production exposure.
