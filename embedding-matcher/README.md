# Curri'KoToh

This project is a local-first curriculum intelligence system built for the Trainer workflow. It combines:

- curriculum-to-skill matching using pretrained sentence-transformer embeddings
- subject normalization and canonical clustering
- structured curriculum generation drafts
- SQLite persistence for analysis, matching, generated/enhanced drafts, and assistant chat
- a PHP browser UI for draft management and operation

The project is designed to support curriculum gap analysis and evidence-based curriculum drafting without needing a large production stack.

## Workplace and data locations

- The project directory is `embedding-matcher`.
- Source data is expected in a sibling `curriculum-generator-kb` directory.
- The local database is `embedding-matcher/curriculum_matching.db`.

## Installed stack and services

The tested project environment uses:

- Python 3.12.10
- packages listed in `requirements.txt` (direct dependencies) and `requirements-lock.txt` (full environment snapshot)
- SQLite for local storage
- PHP 8.3 for the browser interface
- Hugging Face model downloads through the local cache

## Setup on a new machine

Use Python 3.12.10, the version used by the current project virtual environment. Run these commands from the `embedding-matcher` directory. The curriculum dataset and skill source files must be available in the sibling `curriculum-generator-kb` directory before database initialization.

### Windows PowerShell

```powershell
py -3.12 -m venv venv
.\venv\Scripts\Activate.ps1
python -m pip install --upgrade pip
```

For an NVIDIA GPU, first install the matching CUDA-enabled PyTorch build using the instructions at [pytorch.org](https://pytorch.org/). For a CPU-only machine, including a typical cloud VM, install the CPU build first to avoid downloading the very large CUDA package:

```powershell
python -m pip install torch==2.14.1 --index-url https://download.pytorch.org/whl/cpu
python -m pip install -r requirements.txt
```

Use `requirements.txt` for the human-readable direct dependency list. To reproduce the complete package-version snapshot from the current venv instead, use `python -m pip install -r requirements-lock.txt` in place of the last command above.

### Linux or macOS

```sh
python3.12 -m venv venv
source venv/bin/activate
python -m pip install --upgrade pip
python -m pip install torch==2.14.1 --index-url https://download.pytorch.org/whl/cpu
python -m pip install -r requirements.txt
```

Use `requirements.txt` for the human-readable direct dependency list. To reproduce the complete package-version snapshot from the current venv instead, use `python -m pip install -r requirements-lock.txt` in place of the last command above. On a machine with an NVIDIA GPU, install its matching CUDA-enabled PyTorch build from [pytorch.org](https://pytorch.org/) instead of the CPU-only command, then install the selected requirements file.

### Initialize and run

Initialize the local SQLite database from the project directory:

```sh
python database_setup.py
```

`database_setup.py` creates the database schema and imports the course dataset and skill data. It requires the source files in the sibling `curriculum-generator-kb` directory; it does not create an empty database when those required files are absent.

Install PHP 8.3 for the browser interface, with the `sqlite3` and `pdo_sqlite` extensions enabled. The application uses PHP's `SQLite3` API; enable OpenSSL when serving the site over HTTPS. Start the local development server from `embedding-matcher`:

```sh
php -S 127.0.0.1:8000
```

Open `http://127.0.0.1:8000/`. The first account created becomes `super_admin`. Each user adds their own Gemini API key from the account menu. The embedding model downloads from Hugging Face on first use, so internet access is required for the initial model download.

The virtual environment (`venv/`), SQLite database files (including `curriculum_matching.db`), and `settings.json` are local-only and are not stored in the repository.

### Runtime configuration

- `PYTHON_BIN` optionally selects an existing Python executable. If it is unset or points to a missing file, PHP uses `venv\Scripts\python.exe` on Windows or `venv/bin/python` on Linux/macOS.
- `KB_DIR` optionally selects the knowledge-base directory. Without it, the app uses the sibling `curriculum-generator-kb` directory. A relative override is resolved from the workspace root.
- The app respects an externally set `HF_HOME`, `HF_HUB_CACHE`, `HUGGINGFACE_HUB_CACHE`, `TRANSFORMERS_CACHE`, `SENTENCE_TRANSFORMERS_HOME`, or `HF_DATASETS_CACHE`. If none is set, Python uses `embedding-matcher/hf_cache`.
- Python jobs are serialized across users with an OS file lock in the system temporary directory. A request waits up to 45 seconds for the lock, then returns a friendly busy message. Once started, a job is limited to 240 seconds; lock waiting plus processing is bounded to less than 300 seconds.
- For nginx/PHP-FPM deployments, allow at least 300 seconds for the PHP request and upstream response so the browser can receive the completed result or timeout message.
- On a server shell, run `php tools/check_runtime.php` from `embedding-matcher` to report the resolved interpreter, knowledge-base files, effective Hugging Face cache location, and database writability. The diagnostic does not print API keys.

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
- self-service run titles and personal notes

## Quick start

### 1) Open a terminal in the project folder

See [Setup on a new machine](#setup-on-a-new-machine) to create and activate the environment on Windows, Linux, or macOS.

### 2) Activate the environment

Activate the venv using the command for your operating system in [Setup on a new machine](#setup-on-a-new-machine).

### 3) Run the standard skill-matching workflow

```powershell
python user_operations.py --model BAAI/bge-small-en-v1.5 --top-k 5
```

### 4) Generate a draft curriculum

```powershell
python user_operations.py --generate --program BSIT --prompt "Generate a BSIT curriculum focused on software development, databases, and networking." --model BAAI/bge-small-en-v1.5 --top-k 5
```

### 5) Manage a saved draft

Open the Dashboard or the matching Generated/Enhanced drafts page. The run creator can edit the run title and personal notes. Each run also displays its recorded generation mode: Online Template, Offline template, or Not recorded when legacy provenance is unavailable.

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

Start the local PHP interface from the `embedding-matcher` folder:

```sh
php -S 127.0.0.1:8000
```

Open:

- http://127.0.0.1:8000/

This UI is used locally for:

- matching results
- skill coverage
- weak-skill gaps
- generated curriculum drafts
- saved enhancement results, with collapsible rows, client-side search and filters, and per-run assistant chat
- deterministic per-course recommended tools/apps with reasons and curated documentation links; generated runs calculate these at display time, while new enhanced runs save a recommendation snapshot
- per-run PDF downloads for generated drafts and enhancement results, including the completed enhanced curriculum
- creator-owned run title and personal notes
- super-admin dataset file preview; files are not uploaded, saved, or activated by this UI yet

PDFs are generated in the browser using locally served jsPDF and jsPDF-AutoTable assets; there is no runtime CDN request or server-side PDF service. The suggested filename is downloaded according to the browser's download settings. See `assets/PDF_EXPORT_DEPENDENCIES.md` for bundled library versions and licenses.

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
- curriculum_generator.py: draft generation, enhancement assessment, and chat logic
- recommended_tools.py: shared deterministic course-title tool recommendations
- weak_skills_report.py: weak skill export
- index.php: PHP browser interface
- generated_curriculum.php and enhanced_curriculum_generated.php: collapsible run histories and PDF exports
- dataset_management.php: super-admin-only dataset selection preview (no upload or activation backend)
- assets/pdf-export.js: browser-side PDF generation
- tests/: validation tests for generator and subject-bank behavior
- curriculum_matching.db: local SQLite database

## Operating workflow for normal use

1. Activate the virtual environment.
2. Run the matching workflow with the preferred model.
3. Check the generated skill coverage output.
4. Use weak skills and skill gaps to identify curriculum missing areas.
5. Generate a curriculum draft using the subject bank and prompt context.
6. Inspect the generated draft and evidence in the PHP browser.
7. Optionally set a custom title or add personal notes.

## Guardrails and design principles

- Keep the tool grounded in retrieval evidence.
- Keep the generated curriculum as a draft recommendation, not an authority.
- Prefer structured output and human verification over freeform unverified generation.
- Reuse the subject bank and coverage reports as the evidence source.
- Only add more advanced LLM integrations after the subject bank and evidence/validation practices are solid.

## Troubleshooting

### Missing data files

Check that the sibling `curriculum-generator-kb` directory contains:

- `data/curriculum_dataset_with_ids.csv`
- `03_industry_skills_data.md`

### Environment issues

Activate the virtual environment before running Python scripts:

```powershell
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
- add validated dataset upload, staging, activation, and rollback workflows behind the super-admin interface
- improve self-service draft tracking and export options as workflow needs emerge

## Related documentation

- USER_GUIDE.md
- DEVELOPER_GUIDE.md
- AI_CONTEXT.md
