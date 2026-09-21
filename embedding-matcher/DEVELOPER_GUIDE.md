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

3. Curriculum generation and review
   - builds a canonical subject bank
   - clusters subject variants
   - drafts a structured program curriculum
   - stores generated subject records with review metadata

## Key source files

- match_courses_to_skills.py
  - main embedding pipeline
  - similarity scoring and CSV export

- curriculum_generator_foundation.py
  - subject normalization and clustering logic

- curriculum_generator.py
  - structured draft generation and review persistence

- database_setup.py
  - database schema and data import

- user_operations.py
  - primary CLI entry point for end users

- index.php
  - browser-based project interface

## Local environment setup

From the project root:

```powershell
cd W:\Trainers\embedding-matcher
python -m venv venv
.\venv\Scripts\Activate.ps1
pip install -r requirements.txt
```

If there is no requirements file yet, install the known working dependencies:

```powershell
pip install torch sentence-transformers pandas notebook jupyter
```

## Important paths

- Project root: W:\Trainers\embedding-matcher
- Curriculum data: W:\Trainers\curriculum-generator-kb\data\curriculum_dataset_with_ids.csv
- Skill list: W:\Trainers\curriculum-generator-kb\03_industry_skills_data.md
- Local DB: W:\Trainers\embedding-matcher\curriculum_matching.db

## Database design

The SQLite schema should store:

- courses
- skills
- model_runs
- course_skill_matches
- skill_coverage
- generated_curriculum_runs
- generated_curriculum_subjects
- generated_curriculum_reviews

Use the database as the source of truth for generated curriculum review status and provenance.

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

### Review workflow

```powershell
python user_operations.py --review --run-id 1 --review-status approved --reviewer admin --review-notes "Reviewed by developer."
```

## Coding principles

- Prefer retrieval and evidence-based generation.
- Keep generated curriculum outputs structured.
- Preserve source provenance for each planned subject or recommendation.
- Do not overfit to one model; compare at least two when possible.
- Keep the system local-first for quick work.

## Testing expectations

The project already includes tests for subject clustering and curriculum generation. Run them with:

```powershell
cd W:\Trainers\embedding-matcher
.\venv\Scripts\Activate.ps1
python -m unittest discover -s tests
```

## Browser UI development

The browser app is served through PHP and is suitable for local review operations. Keep the UI simple and evidence-oriented:

- show skill coverage
- show weak skills summary
- show generated curriculum review status
- keep notes and approval state visible

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
