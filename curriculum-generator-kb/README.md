---
title: "Curriculum Generator Knowledge Base - Index"
type: index
converted: "2026-09-21"
tags: [curriculum, industry, rag, index]
description: "Start here. What each markdown file contains, the ID conventions, and how to chunk and cite them."
---

# Curriculum Generator Knowledge Base

Markdown versions of the project data, ready to drop into an Obsidian vault or feed to a retrieval (RAG) step. The two Word documents keep their original wording; the spreadsheet and CSV data are turned into tables you can cite by ID.

## Files

| File | What it holds | Signal |
|---|---|---|
| [01_curriculum_benchmarking_dataset.md](01_curriculum_benchmarking_dataset.md) | The approved v5-final document: classification rules, source status, school-by-school tables, comparison tables, limitations, references | Academic |
| [02_industry_skills_synthesis.md](02_industry_skills_synthesis.md) | Final synthesis of 113 entry-level postings: skills, entry-level flag and demand rating per role cluster | Industry |
| [03_industry_skills_data.md](03_industry_skills_data.md) | One record per skill per role cluster with evidence count and source, plus the Core / Add / Keep / Drop framework and sampling method | Industry |
| [04_job_postings_log.md](04_job_postings_log.md) | The 113 postings behind the industry signal | Industry evidence |
| [05_curriculum_courses.md](05_curriculum_courses.md) | 287 courses from 10 curricula, grouped by school, with year, term, units and classification | Academic |
| data/ | Original spreadsheet plus the course CSV with IDs added | Raw |

## ID conventions (use these for citations)

- **Courses:** `<SCHOOL>-<PROGRAM>-<nn>`, for example `UST-BSCS-04`, `ADAMSON-BSIT-12`.
- **Skill records:** `IS-###`, for example `IS-014`.
- **Job postings:** `JOB-PH-###`, as in the original spreadsheet.

## Suggested chunking

- Split on `##` and `###` headings; each table is one chunk per school or per role cluster.
- For row-level retrieval, treat each table row as a record and attach the heading above it (school and program, or role cluster) as metadata.
- Keep the front matter fields (`type`, `tags`, `version`) as metadata on every chunk.

## Things to know before relying on it

- File 01 was written for the earlier "curriculum enhancer" framing and centres on NU Lipa. Its section 11 (first-pass industry scan) and section 12 (early observations) are superseded by files 02 to 04.
- Placement by year and term is confirmed for 234 of 287 course rows. DLSU and UST BSIT have no term data; UST BSIT is also incomplete.
- Course tags (`competency_tag`) and prerequisites are not in the data yet, so they are not in these files.
- Industry "Posted" values are ages shown on listing pages in September 2026, and the source URLs are search pages, not single-posting links.
- Absence from a required-course list is not proof that a school never teaches a topic.
