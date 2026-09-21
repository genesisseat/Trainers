# Curriculum Matcher & Generator

This project is a local-first curriculum intelligence system built for the Trainer workflow. It combines:

- curriculum-to-skill matching using pretrained sentence-transformer embeddings
- subject normalization and canonical clustering
- structured curriculum generation drafts
- SQLite persistence for analysis, matching, and review history
- a PHP browser UI for local review and operation

The project is designed to support curriculum gap analysis and evidence-based curriculum drafting without needing a large production stack.

## Workplace and data locations

- Project root: W:\Trainers\embedding-matcher
- Knowledge base: W:\Trainers\curriculum-generator-kb
- Course dataset: W:\Trainers\curriculum-generator-kb\data\curriculum_dataset_with_ids.csv
- Skill list: W:\Trainers\curriculum-generator-kb\03_industry_skills_data.md
- Local database: W:\Trainers\embedding-matcher\curriculum_matching.db

## Installed stack and services

This project is currently set up for a Windows workstation with:

- Python 3.10+
- virtual environment in W:\Trainers\embedding-matcher\venv
- SQLite for local storage
- PHP 8.3 for browser-based review
- Jupyter Notebook for experimentation and analysis
- Hugging Face model downloads through the local cache

## What the system does

### Matching and coverage analysis

The system reads the course dataset and skill list, embeds both using a model such as MiniLM or BGE-small, and computes similarity scores.

Outputs include:

- course_to_skill_matches.csv
- skill_coverage.csv
- weakest_skills_report.csv
- curriculum_matching.db

### Subject bank and curriculum generation

The project also builds a canonical subject bank from curriculum data, clusters near-equivalent subject names, and generates structured curriculum drafts tied to relevant skill evidence.

This provides a foundation for:

- program-level curriculum recommendations
- year/term structure drafts
- rationale and source college tracking
- review and approval workflow for generated drafts

## Quick start

### 1) Open a terminal in the project folder

```powershell
cd W:\Trainers\embedding-matcher
```

### 2) Activate the environment

```powershell
.\venv\Scripts\Activate.ps1
```

If PowerShell blocks script execution, run:

```powershell
Set-ExecutionPolicy -Scope Process -ExecutionPolicy RemoteSigned
```

### 3) Run the standard skill-matching workflow

```powershell
python user_operations.py --model BAAI/bge-small-en-v1.5 --top-k 5
```

### 4) Generate a draft curriculum

```powershell
python user_operations.py --generate --program BSIT --prompt "Generate a BSIT curriculum focused on software development, databases, and networking." --model BAAI/bge-small-en-v1.5 --top-k 5
```

### 5) Review a generated draft

```powershell
python user_operations.py --review --run-id 1 --review-status approved --reviewer admin --review-notes "Approved for drafting review."
```

## Primary commands

### Run the raw matcher script

```powershell
python match_courses_to_skills.py --model all-MiniLM-L6-v2 --top-k 5
python match_courses_to_skills.py --model BAAI/bge-small-en-v1.5 --top-k 5
```

### Build the SQLite database

```powershell
python database_setup.py
```

### Generate the weakest-skills report

```powershell
python weak_skills_report.py
```

### Open the notebook

```powershell
jupyter notebook
```

Then open the notebook file in the project folder:

- curriculum_skill_matcher.ipynb

## Browser UI

Start the local PHP interface from the project folder:

```powershell
cd W:\Trainers\embedding-matcher
& 'C:\Users\genes\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe' -S 127.0.0.1:8000 -t 'W:\Trainers\embedding-matcher'
```

Open:

- http://127.0.0.1:8000/

This UI is used for local review of:

- matching results
- skill coverage
- weak-skill gaps
- generated curriculum drafts
- review status and notes

## Important notes about model choice

Recommended models for this project:

- all-MiniLM-L6-v2: fast and lightweight
- BAAI/bge-small-en-v1.5: strong practical default
- BAAI/bge-base-en-v1.5: higher quality if needed

Use pretrained embeddings as the base system. This project does not require fine-tuning for the current gap-analysis and draft-generation workflow.

## Project structure

- match_courses_to_skills.py: main semantic matching pipeline
- user_operations.py: user-friendly CLI entry point
- database_setup.py: SQLite schema and import helper
- curriculum_generator_foundation.py: subject clustering and canonical subject bank
- curriculum_generator.py: draft generation and review logic
- weak_skills_report.py: weak skill export
- index.php: PHP browser interface
- tests/: validation tests for generator and subject-bank behavior
- curriculum_matching.db: local SQLite database

## Operating workflow for normal use

1. Activate the virtual environment.
2. Run the matching workflow with the preferred model.
3. Check the generated skill coverage output.
4. Use weak skills and skill gaps to identify curriculum missing areas.
5. Generate a curriculum draft using the subject bank and prompt context.
6. Review the generated draft in SQLite or the PHP browser.
7. Approve, reject, or request revision using the review workflow.

## Guardrails and design principles

- Keep the tool grounded in retrieval evidence.
- Keep the generated curriculum as a draft recommendation, not an authority.
- Prefer structured output and human review over freeform unverified generation.
- Reuse the subject bank and coverage reports as the evidence source.
- Only add more advanced LLM integrations after the subject bank and review process are solid.

## Troubleshooting

### Missing data files

Check that the following paths exist:

- W:\Trainers\curriculum-generator-kb\data\curriculum_dataset_with_ids.csv
- W:\Trainers\curriculum-generator-kb\03_industry_skills_data.md

### Environment issues

Activate the virtual environment before running Python scripts:

```powershell
cd W:\Trainers\embedding-matcher
.\venv\Scripts\Activate.ps1
```

### PHP UI not loading

Use the explicit PHP executable and start the server from the project folder.

### Model downloads fail or are slow

Check internet connectivity and ensure the local Hugging Face cache is accessible.

## Recommended next steps

- maintain the current evidence-first generation workflow
- improve subject normalization and clustering quality
- add richer review and version tracking
- add saved API key support for external LLM generation later
- expand the browser UI with export, filtering, and approval status views

## Related documentation

- USER_GUIDE.md
- DEVELOPER_GUIDE.md
- AI_CONTEXT.md
