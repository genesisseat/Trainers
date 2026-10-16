# User Guide

This guide explains how to use Curri'KoToh's curriculum matching, generation, and enhancement features in normal day-to-day work.

## Goal of the project

This tool helps answer practical questions such as:

- Which skills are poorly covered by the current curriculum?
- Which courses best match a target industry skill?
- What is a reasonable draft curriculum for a program such as BSIT?
- How can I keep track of my own curriculum drafts and revisions?

## Prerequisites

Before you begin, make sure:

- Python is installed
- the project virtual environment exists in C:\Trainers\embedding-matcher\venv
- the curriculum knowledge base is available at C:\Trainers\curriculum-generator-kb
- PHP 8.3 is available for the browser UI

## Step 1: Open the project terminal

```powershell
cd C:\Trainers\embedding-matcher
.\venv\Scripts\Activate.ps1
```

## Step 2: Run a matching analysis

Use the user-friendly command:

```powershell
python user_operations.py --model BAAI/bge-small-en-v1.5 --top-k 5
```

This will generate:

- course_to_skill_matches.csv
- skill_coverage.csv

## Step 3: Review skill coverage

Inspect the output files in the project folder. The most important file is:

- skill_coverage.csv

This file tells you which skills are strongly or weakly matched by the current curriculum.

## Step 4: Review the weakest skills

```powershell
python weak_skills_report.py
```

This creates:

- weakest_skills_report.csv

Use this to identify curriculum gaps that need attention.

## Step 5: Generate a draft curriculum

```powershell
python user_operations.py --generate --program BSIT --prompt "Generate a BSIT curriculum focused on software development, databases, and networking." --model BAAI/bge-small-en-v1.5 --top-k 5
```

This uses the subject bank and coverage evidence to build a structured draft.

## Step 6: Manage the generated draft

On the draft page, the creator can set a custom title and add personal notes. Each run shows its recorded generation mode: Online Template, Offline template, or Not recorded when legacy provenance cannot be proven. All generated and enhanced content is advisory and must be verified before use.

## Step 7: Use the browser UI

Start the PHP interface:

```powershell
cd C:\Trainers\embedding-matcher
& 'C:\Users\genes\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe' -S 127.0.0.1:8000 -t 'C:\Trainers\embedding-matcher'
```

Open:

- http://127.0.0.1:8000/

From there, you can view matching data, generated drafts, and saved enhancement results.

### Private drafts and API keys

Regular accounts see and manage only drafts they created, including the associated enhancement report, courses, chat, notes, and PDF export. A direct link to another account's run is unavailable. Super admins can access all runs, including older unattributed runs. Admins can access attributed runs but cannot access unattributed legacy runs.

Logged-in `user` and `admin` accounts must save their own Gemini API key in the account menu before using Generate, Enhance, or the chat assistant. They cannot use a shared system key. Only `super_admin` can fall back to the shared key when no personal key is saved. If a required personal key is missing, the page shows the same notice and a link to the key setting; the action is blocked before Gemini is called. With an eligible key, a deterministic offline draft fallback remains available if Gemini fails. Guest trial behavior is unchanged: shared key only, temporary session drafts, and three combined Generate/Enhance attempts.

The account menu shows only whether a personal Gemini key is saved and a fixed `********` mask; it never displays the stored key. Personal keys are stored in SQLite without encryption at rest.

### Export a draft or enhancement results

Open the run you want and select **Export to PDF**. The browser downloads a PDF with a suggested filename. Enhancement exports include the assessment results and the saved completed curriculum. Each PDF includes an advisory disclaimer. The browser decides the download folder; when its “ask where to save” option is disabled, the file goes to the browser's default Downloads folder.

### Preview dataset files (super admins)

Super admins can open **Dataset Management** from the navigation, choose a dataset type, and add a local file to the page's preview queue. This is a UI preview only: files are not uploaded, saved, imported, or activated, and selections are cleared when the page is closed or refreshed. The current active course and skill sources remain the files listed on that page.

## Good operating habits

- start with the coverage file before changing a curriculum
- compare a few models when quality matters
- verify advisory recommendations before use; generation mode reports which generation path was recorded
- preserve the evidence trail from skill coverage to subject content

## Typical user questions this project answers

- What skills does our curriculum currently cover well?
- Which skills are the weakest gaps?
- Which course is the strongest match for a skill?
- What does a recommended draft curriculum look like?
- What should I verify or revise in this draft before using it?

## Outputs to expect

- course_to_skill_matches.csv
- skill_coverage.csv
- weakest_skills_report.csv
- curriculum_matching.db

These are the main artifacts for review and decision-making.
