# AI Context & Project Handling Guide

This file is the operating brief for the next AI session and any future developer or automation agent working on this repository.

## Project purpose

The project is a curriculum analysis and drafting system built around pretrained sentence embeddings. It matches curriculum courses to industry skills, identifies weak coverage, clusters subject names, and generates structured curriculum drafts that can be reviewed by a human.

This is not a fully autonomous curriculum generator. It is an evidence-first system that retrieves curriculum and skill context and then produces draft recommendations for review.

## Workspace map

- Project folder: W:\Trainers\embedding-matcher
- Knowledge base: W:\Trainers\curriculum-generator-kb
- Curriculum dataset: W:\Trainers\curriculum-generator-kb\data\curriculum_dataset_with_ids.csv
- Industry skills: W:\Trainers\curriculum-generator-kb\03_industry_skills_data.md
- Main app: W:\Trainers\embedding-matcher\index.php
- Local database: W:\Trainers\embedding-matcher\curriculum_matching.db
- Virtual environment: W:\Trainers\embedding-matcher\venv

## Core files to know

- match_courses_to_skills.py: runs the main semantic matching pipeline
- user_operations.py: user-friendly command interface for matching and generation
- database_setup.py: SQLite schema and imports
- curriculum_generator_foundation.py: canonical subject bank and subject clustering
- curriculum_generator.py: generation and review logic
- weak_skills_report.py: exports weak skill gaps
- index.php: browser UI for local review
- tests/: regression checks for subject bank and generator flow

## Operational rules for AI

1. Do not assume this is a fine-tuned curriculum model.
2. Keep the system evidence-first and retrieval-based.
3. Prefer using the subject bank and skill coverage as ground truth for generation.
4. Treat generated curriculum content as draft recommendations, not hard final curriculum.
5. Save generated curriculum drafts into SQLite with provenance and review metadata.
6. Keep the user-facing output structured and readable.
7. Never remove the local knowledge-base dependency path unless there is a clear reason.

## Working commands

### Activate environment

```powershell
cd W:\Trainers\embedding-matcher
.\venv\Scripts\Activate.ps1
```

### Run matching

```powershell
python user_operations.py --model BAAI/bge-small-en-v1.5 --top-k 5
```

### Generate draft curriculum

```powershell
python user_operations.py --generate --program BSIT --prompt "Generate a BSIT curriculum focused on software development, databases, and networking." --model BAAI/bge-small-en-v1.5 --top-k 5
```

### Review a generated draft

```powershell
python user_operations.py --review --run-id 1 --review-status approved --reviewer admin --review-notes "Approved for drafting review."
```

### Start the local browser UI

```powershell
cd W:\Trainers\embedding-matcher
& 'C:\Users\genes\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe' -S 127.0.0.1:8000 -t 'W:\Trainers\embedding-matcher'
```

## Model guidance

- all-MiniLM-L6-v2: fast and stable default for testing
- BAAI/bge-small-en-v1.5: best practical default for project work
- BAAI/bge-base-en-v1.5: stronger but heavier option

## Data workflow

The project reads structured curriculum data and normalized skill content, then performs:

- embedding generation
- cosine similarity scoring
- coverage ranking
- subject clustering
- curriculum draft generation

The strongest outputs to inspect first are:

- skill_coverage.csv
- weakest_skills_report.csv
- generated curriculum subjects in SQLite

## Developer expectations

When editing this project:

- keep output files and database schema consistent with current workflow
- update the documentation when adding new commands or features
- preserve the retrieval-based evidence chain from source data to generated output
- test generation and review behavior before calling it complete

## Project status summary

The current project is at a working prototype stage:

- matching pipeline works
- SQLite is populated and used
- weak skill report generation works
- PHP browser interface is available
- subject bank and draft generation are implemented
- review workflow is supported

This is ready for human review and improvement, but not yet a final autonomous curriculum authoring system.

## How to continue from here

The next best work is one of these:

1. improve the review dashboard and filter controls
2. add saved API-key support and external LLM generation guardrails
3. refine subject clustering quality across more programs
4. add historical versioning and curriculum comparison views

## Documentation references

- README.md
- USER_GUIDE.md
- DEVELOPER_GUIDE.md
