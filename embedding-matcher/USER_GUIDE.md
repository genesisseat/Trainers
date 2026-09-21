# User Guide

This guide explains how to use the curriculum matcher and curriculum generator in normal day-to-day work.

## Goal of the project

This tool helps answer practical questions such as:

- Which skills are poorly covered by the current curriculum?
- Which courses best match a target industry skill?
- What is a reasonable draft curriculum for a program such as BSIT?
- Which generated curriculum draft should be approved, revised, or rejected?

## Prerequisites

Before you begin, make sure:

- Python is installed
- the project virtual environment exists in W:\Trainers\embedding-matcher\venv
- the curriculum knowledge base is available at W:\Trainers\curriculum-generator-kb
- PHP 8.3 is available for the browser UI

## Step 1: Open the project terminal

```powershell
cd W:\Trainers\embedding-matcher
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

## Step 6: Review the generated draft

You can submit a review decision from the CLI:

```powershell
python user_operations.py --review --run-id 1 --review-status approved --reviewer admin --review-notes "Approved for editing review."
```

Allowed review statuses:

- draft
- approved
- rejected
- needs_revision

## Step 7: Use the browser UI

Start the PHP interface:

```powershell
cd W:\Trainers\embedding-matcher
& 'C:\Users\genes\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe' -S 127.0.0.1:8000 -t 'W:\Trainers\embedding-matcher'
```

Open:

- http://127.0.0.1:8000/

From there, you can review the matching data and curriculum-generation records in a browser.

## Good operating habits

- start with the coverage file before changing a curriculum
- compare a few models when quality matters
- keep generated curriculum records as drafts until a human approves them
- preserve the evidence trail from skill coverage to subject content

## Typical user questions this project answers

- What skills does our curriculum currently cover well?
- Which skills are the weakest gaps?
- Which course is the strongest match for a skill?
- What does a recommended draft curriculum look like?
- Is this draft acceptable, rejected, or needing revisions?

## Outputs to expect

- course_to_skill_matches.csv
- skill_coverage.csv
- weakest_skills_report.csv
- curriculum_matching.db

These are the main artifacts for review and decision-making.
