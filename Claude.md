# Curri'KoToh: AI Handoff

Use this note for current project status. The implementation and detailed AI context live in `embedding-matcher/`; the active workspace is `C:\Trainers`.

## Purpose and principles

Local-first curriculum analysis and draft generation. The system compares courses with industry skills, identifies coverage gaps, builds a canonical course directory, and generates evidence-grounded curriculum recommendations. Generated content is advisory and requires human review. Embeddings are pretrained; this is not a fine-tuned curriculum model.

## Current stack

- Python in `embedding-matcher/venv`, with CUDA-enabled PyTorch where available.
- Hugging Face SentenceTransformers; operational embedding default is `BAAI/bge-small-en-v1.5`.
- Google Gemini API for generation and review assistance when configured, with deterministic offline template fallback.
- SQLite database: `embedding-matcher/curriculum_matching.db`.
- PHP browser interface for matching reports, generated curricula, and review operations.

## Module checklist

| Module | Area | Status |
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

Module 9 is the active workstream. Basic browser forms and persistence exist, but keep its overall status as ongoing until the review workflow is finished.

## Terminology and canonical grouping

Use **Master Course List / Course Directory** for the feature previously called “Subject Bank Management.” `canonical_subject_bank.csv` and internal `subject_bank` names remain implementation artifacts.

Canonical equivalent-course grouping normalizes names (lowercase, `&` to `and`, punctuation removal, whitespace cleanup), deduplicates exact normalized names, and embeds unique titles. Titles are greedily clustered against later unassigned titles at cosine similarity `>= 0.82`; canonical groups are separated by normalized program. Variants, source colleges, classifications, year/term positions, and units are retained. This is heuristic matching, not formal equivalency approval; preserve the original names and provenance.

## Implemented additions

- Structured Gemini curriculum generation, four-year roadmap validation, bounded retries, diagnostics, and marked offline template fallback.
- Skill-gap evidence and source-college provenance in generated curriculum records.
- Enhancement workflow for user-submitted curricula.
- Chat about generated curricula and enhancement reviews, including explanations and validated modification flows.
- SQLite persistence for generated runs, courses, reviews, and chat; PHP views for coverage, generation, review, and reporting.

## Working paths and files

- Course data: `curriculum-generator-kb/data/curriculum_dataset_with_ids.csv`
- Industry skills: `curriculum-generator-kb/03_industry_skills_data.md`
- CLI: `embedding-matcher/user_operations.py`
- Generation, validation, review, and chat: `embedding-matcher/curriculum_generator.py`
- Course directory builder: `embedding-matcher/curriculum_generator_foundation.py`
- Browser entry: `embedding-matcher/index.php`
- Detailed AI context: `embedding-matcher/AI_CONTEXT.md`

## Guardrails and next focus

- Do not fine-tune without a reviewed, labeled gold dataset.
- Treat similarity as evidence, not a competency or quality measurement.
- Keep API credentials out of source and documentation; use environment variables or supported settings files.
- Keep Module 9 marked ongoing; continue browser approval/rejection and reviewer-notes workflow work while preserving SQLite compatibility.
- Run focused tests after code changes. Some existing test database paths are hard-coded to a legacy absolute location, so check paths before running them from a different workspace.