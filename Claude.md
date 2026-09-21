Project progress brief for Claude
Use this as the handoff note:

I built a working curriculum-to-skill matching prototype in W:\Trainers\embedding-matcher using pretrained sentence-transformer embeddings only, without fine-tuning. The project reads the canonical dataset from W:\Trainers\curriculum-generator-kb and matches each course to industry skills using semantic similarity.

Current status:

- Core pipeline works end-to-end in Python
- Verified models: all-MiniLM-L6-v2 and BAAI/bge-small-en-v1.5
- CUDA is available on this machine, so local embedding generation works well
- SQLite database is populated and usable
- Weak skill export is generated successfully
- Browser PHP viewer exists and reads from SQLite
- README and developer context files were created for continuity
- A local venv is working in W:\Trainers\embedding-matcher

Main files:

- match_courses_to_skills.py
- user_operations.py
- database_setup.py
- weak_skills_report.py
- index.php
- curriculum_matching.db
- skill_coverage.csv
- weakest_skills_report.csv
- README.md
- AI_CONTEXT.md

Data sources:

- W:\Trainers\curriculum-generator-kb\data\curriculum_dataset_with_ids.csv
- W:\Trainers\curriculum-generator-kb\03_industry_skills_data.md

What the pipeline does:

- Loads curriculum course rows from the CSV
- Loads skill rows from the markdown skill list
- Normalizes and embeds both sets using sentence-transformers
- Computes cosine similarity
- Produces top-k matches per course
- Produces skill coverage output showing the strongest and weakest coverage
- Exports a weak-skills report for human review
- Stores everything in SQLite for dashboard usage

Verified outputs:

- course_to_skill_matches.csv created
- skill_coverage.csv created
- weakest_skills_report.csv created
- curriculum_matching.db created and populated
- project working on Windows under a local venv

Known current limitation:

- This is a pretrained embedding baseline, not a trained custom model; no fine-tuning or supervised curriculum label training has been done yet
- The PHP browser page still needs final polish and a clean launch setup if the local runtime issue continues

Recommended next improvements:

- Add “course details” page with individual match breakdowns
- Add filtering by skill type, course category, or confidence threshold
- Add a scoring explanation section: why a skill is weak/strong
- Add comparison mode between models (MiniLM vs BGE-small vs BGE-base)
- Add a recommended action section: suggest new course, elective, or track adjustment
- Add export to Excel or CSV from the browser
- Add admin/supervisor view with role-based access or saved reports
- Add a “manual review” workflow where staff can approve or override match quality
- Add a historical versioning feature so each analysis run is stored with a timestamp
- Add a simple REST API layer so PHP or another front end can query the data cleanly

Strategic direction:

- Keep pretrained embeddings as the operational baseline
- Do not train yet unless a real labeled gold dataset exists
- Use the system as a gap-analysis tool first
- Only consider fine-tuning after there is human-reviewed curriculum-to-skill labels
- This project is already strong as a prototype for analyzing curriculum gaps and preparing decisions for curriculum design

Current product status:

The current embedding-matcher prototype is a matching/gap-analysis tool:
- It embeds existing courses and industry skills
- It computes cosine similarity to find course↔skill matches
- It produces coverage + weak-skill reports into SQLite
- It is browsable via a PHP viewer

It does not generate new curricula. It only evaluates curricula already present in the dataset.

Next Plan — Curriculum Generator Expansion

The goal:
Let a user prompt something like “Generate a curriculum for BSIT” and get back a structured, recommended curriculum — broken down by year/term, with subjects and topics per subject — designed to prepare students for actual industry demand.

Basis for generation:
- Curriculum data from 8 Philippine colleges: NU Lipa, UST, DLSU, TIP, Ateneo, UP, LPU, Adamson
- Industry skills scanned from job postings

Why this needs a new layer, not just more matching:
Similarity scoring tells you how well a curriculum covers skills — it cannot author a curriculum (sequence subjects into years/terms, decide topic lists, resolve prerequisites). That requires a generation step. Per the project’s strategy, the correct approach is retrieval-augmented generation (RAG): use the embedding pipeline to retrieve relevant reference material, then use an LLM to synthesize the curriculum from that retrieved context — not a trained or fine-tuned model.

Phase 1 — Data normalization (foundation)
- Standardize the 8 colleges’ curricula into one schema: program, year, term, subject_code, subject_title, units, prerequisites, description/topics if available
- Tag each subject with its source college, so provenance is traceable in generated output
- Confirm topic-level detail exists per subject (syllabus-level), or only subject titles — this determines how granular Phase 3 generation can be
- Re-run the industry skill scan/cleanup: dedupe, categorize skills (technical/soft/tooling), and tag with frequency/source job postings for weighting

Phase 2 — Cross-college structural analysis
- Cluster equivalent subjects across colleges using the existing embedding pipeline
- Build a canonical subject bank per program: which subjects are common across most colleges (core), which are unique to one or two (electives/differentiators)
- Re-run skill-coverage analysis per college to see which schools already cover which industry skills best — this becomes generation context, not just a report

Phase 3 — Generation layer (the new capability)
- Design a retrieval step: given program = BSIT, pull the canonical subject bank + top weak/strong skill coverage findings
- Design a generation prompt/pipeline (LLM-based) that takes that retrieved context and drafts:
  - Year/term structure
  - Subject list per term
  - Topics per subject (if syllabus-level data supports it)
  - A short rationale per subject tying it to specific industry skill gaps it addresses
- Output as structured data (not free text) so it can be stored in SQLite and rendered by the PHP front end
- Treat generated output as a draft recommendation, always paired with the gap-analysis evidence behind each choice — not a final authoritative curriculum

Phase 4 — Review & validation workflow
- Human review step: subject-matter experts approve/edit/reject generated subjects
- Version each generated curriculum run with a timestamp, so iterations can be compared
- Add a comparison view: generated curriculum vs. each of the 8 source colleges, side by side

Phase 5 — Dashboard integration
- Extend the existing PHP viewer with a “Generate Curriculum” prompt/interface
- Show generated output next to the underlying gap-analysis evidence (skills addressed, weak skills still unaddressed, which colleges informed each subject)
- Carry forward planned improvements (filtering, admin/role-based access, export to Excel/CSV, REST API layer) — these apply equally to generated curricula, not just matching reports

Open questions to resolve before building Phase 3
1. Is topic-level syllabus data available for all 8 colleges, or just subject titles/descriptions? This caps how detailed “topics to learn in each subject” can realistically be.
2. How should industry skill weighting work — recency of job postings, frequency, or role-seniority level?
3. Should generated curricula respect CHED minimum unit/subject requirements for BSIT and other programs? If so, that is another data source to fold in.
4. Where does the generation call happen — server-side script calling an LLM API, or kept as a separate offline step whose output gets loaded into SQLite?

Recommended execution order

1. Finish the current matching and SQLite/PHP dashboard baseline
2. Build a structured subject bank from the college data
3. Add retrieval and generation prompt scaffolding using the subject bank + skill coverage evidence
4. Store generated curriculum drafts in SQLite with review metadata
5. Add the browser workflow for prompt, review, export, and comparison

This project is already strong as a prototype for analyzing curriculum gaps. The next strategic step is to move from a gap-analysis system into a curriculum-generation assistant that is evidence-driven, transparent, and reviewable by humans.

