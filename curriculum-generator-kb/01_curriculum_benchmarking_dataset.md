---
title: "Curriculum Benchmarking Dataset - BSCS and BSIT"
type: "source-document"
source_file: "Integ__Programming_data_gathering_v5-final.docx"
version: "v5-final"
converted: "2026-09-21"
tags: [curriculum, benchmarking, BSCS, BSIT, CHED, academic-signal]
description: "Approved data-gathering document: classification rules, source status, NU Lipa and benchmark-school curricula, comparison tables, early industry scan, limitations, references."
---

# Curriculum Benchmarking Dataset - BSCS and BSIT

*Major and professional computing subjects only.*

> Converted from the approved v5-final Word document. Wording is unchanged. It was written for the earlier "curriculum enhancer" framing, so it centres on NU Lipa; for the generator, treat the school tables as one source among ten. Section 11 is the early first-pass industry scan and is superseded by [02_industry_skills_synthesis](02_industry_skills_synthesis.md) and [03_industry_skills_data](03_industry_skills_data.md). Row-level course data is in [05_curriculum_courses](05_curriculum_courses.md).

## 1. What this document is for

This is the data-gathering file for the curriculum-enhancement project. It records, in one place, the NU Lipa BSCS and BSIT major subjects and the equivalent subjects at other universities, so that coverage can be compared later without going back to the original web pages each time.

It deliberately leaves out general education, PE, NSTP, humanities, and general communication subjects. Those are required for graduation, but they do not tell us anything about the computing content of the program, which is what the study is about.

Two things this document is not:

- It is not a ranking. No university here is being called better or worse than another.

- It is not a finished analysis. It is the evidence layer. The gap analysis and recommendations come after the data is verified.

## 2. How courses are classified

Every course in this document is tagged with one of five labels. Earlier drafts used mixed labels such as “Core”, “CS Core”, “CS/IT Core”, “Core/Professional”, “Common Computing”, “Industry Experience” and “Industry Preparation”, which made the tables hard to total. Those have all been mapped onto the five below.

| **Label**           | **What it covers**                                                   | **Examples**                                                                                                        |
|---------------------|----------------------------------------------------------------------|---------------------------------------------------------------------------------------------------------------------|
| Core                | Foundation computing subjects almost every CS/IT program requires    | Programming 1–3, data structures, discrete structures, architecture, operating systems, databases, basic networking |
| Professional        | Applied computing subjects that build on the core                    | Web and mobile development, security, systems integration, HCI, multimedia, systems administration                  |
| Specialization      | Track subjects or professional electives, not taken by every student | Machine learning, deep learning, data science, data analytics, professional electives                               |
| Research / Capstone | Thesis, capstone and research methods                                | Methods of Research, Thesis 1–2, Capstone 1–2                                                                       |
| Internship          | Supervised industry work                                             | Internship, practicum, OJT                                                                                          |

*Supporting STEM subjects (maths, statistics, physics) are tracked separately and are not in the tables below. General and minor subjects are excluded entirely.*

Two judgement calls worth stating openly, because a panel will ask:

- Discrete Structures is counted as Core, not as a maths subject. It is a computing foundation course even though it looks like maths on a checklist.

- Thesis, capstone and internship are kept in, because they are part of professional computing preparation and they are one of the clearest differences between programs.

These classifications are our analytical decision, not something CHED assigns. Borderline courses should be confirmed with the department before the dataset is frozen.

## 3. Sources and how far each one has been verified

Only CHED and official university pages are used. Nothing here comes from a blog, a review site, or a third-party course listing. The last column is the important one it says how much of each curriculum has been extracted, so nobody reads a blank cell as “this university does not teach it”.

| **Source**         | **Document used**                                                                            | **Version / effectivity**                            | **Extraction status**                                              |
|--------------------|----------------------------------------------------------------------------------------------|------------------------------------------------------|--------------------------------------------------------------------|
| CHED               | CMO No. 25, s. 2015 Revised PSG for BSCS, BSIS and BSIT                                      | 2015, still the governing PSG as far as we know      | Competency areas extracted; full unit tables not yet               |
| NU Lipa            | BSCS-ML and BSIT-MWA curriculum flowcharts supplied by the project team                      | 2021 (codes read 1-2021-BSCS-ML and 1-2021-BSIT-MWA) | Complete and checked course by course against the flowcharts       |
| UST                | BSCS official program page                                                                   | New curriculum, A.Y. 2023–2024                       | Complete for major subjects                                        |
| UST                | BSIT official program page                                                                   | New curriculum, A.Y. 2023–2024                       | Partial, see note in 8.1                                           |
| DLSU               | BSCS major in Software Technology, CCS page                                                  | Not stated to confirm                                | Complete for major subjects                                        |
| UP                 | BSCS system-wide curriculum checklist and study plan (published via UP Cebu)                 | 2018 curriculum, still current                       | Complete for major subjects, including year/term and prerequisites |
| TIP                | 2018 curriculum checklist for BSIT, registrar-published PDF                                  | 2018 curriculum, still current                       | Complete                                                           |
| Adamson University | BSIT curriculum, registrar-hosted page for the 2026 curriculum (see section 15 for the link) | 2026 curriculum                                      | Complete for major subjects, including year/term and units         |
| LPU Manila         | BSIT curriculum PDF, College of Technology (see section 15 for the link)                     | SY 2022–2023 curriculum                              | Complete for major subjects, including year/term and units         |
| Ateneo de Manila   | BS Computer Science, official Program of Study PDF                                           | Current (2023 PDF revision date)                     | Complete for major subjects, including year/term and units         |

*Full URLs are listed in section 15. The NU Lipa rows come from images the team supplied, not from a published NU web page, so they cannot be cited as a public source in the paper without asking the department for the official copy.*

## 4. NU Lipa BSCS - major subjects

**Full program title:** Bachelor of Science in Computer Science with Specialization in Machine Learning. Curriculum code 1-2021-BSCS-ML, so this is the 2021 curriculum.

The specialization is the reason the machine learning sequence is as long as it is. It is a declared track, not an unusual amount of AI bolted onto a general CS program, and the write-up should say so.

| **Yr** | **Term** | **Course**                                         | **Units** | **Classification**  |
|--------|----------|----------------------------------------------------|-----------|---------------------|
| 1      | 1        | Fundamentals of Programming                        | 3         | Core                |
| 1      | 1        | Introduction to Computing                          | 3         | Core                |
| 1      | 2        | Intermediate Programming                           | 3         | Core                |
| 1      | 2        | Hardware, Software and Peripheral Installation     | 1         | Professional        |
| 1      | 3        | Object-Oriented Programming                        | 3         | Core                |
| 1      | 3        | Discrete Structures 1                              | 3         | Core                |
| 2      | 1        | Data Structures and Algorithms                     | 3         | Core                |
| 2      | 1        | Discrete Structures 2                              | 3         | Core                |
| 2      | 2        | Computer Organization and Architecture             | 3         | Core                |
| 2      | 2        | Information Management                             | 3         | Core                |
| 2      | 3        | Basic Networking                                   | 3         | Core                |
| 2      | 3        | Algorithms and Complexity                          | 3         | Core                |
| 2      | 3        | Advanced Database Systems                          | 3         | Professional        |
| 3      | 1        | Introduction to Machine Learning                   | 3         | Specialization      |
| 3      | 1        | Operating Systems                                  | 3         | Core                |
| 3      | 1        | Automata Theory and Formal Languages               | 3         | Core                |
| 3      | 1        | Software Engineering 1                             | 3         | Core                |
| 3      | 2        | Information Assurance and Security                 | 3         | Professional        |
| 3      | 2        | Software Engineering 2                             | 3         | Core                |
| 3      | 2        | Advanced Machine Learning                          | 3         | Specialization      |
| 3      | 3        | Methods of Research                                | 3         | Research / Capstone |
| 3      | 3        | Deep Learning                                      | 3         | Specialization      |
| 3      | 3        | Applications Development and Emerging Technologies | 3         | Professional        |
| 3      | 3        | Introduction to Human-Computer Interaction         | 3         | Professional        |
| 4      | 1        | Reinforcement Learning                             | 3         | Specialization      |
| 4      | 1        | Programming Languages                              | 3         | Core                |
| 4      | 1        | Social and Professional Issues                     | 3         | Professional        |
| 4      | 1        | Thesis 1                                           | 3         | Research / Capstone |
| 4      | 2        | Data Science                                       | 3         | Specialization      |
| 4      | 2        | Thesis 2                                           | 3         | Research / Capstone |
| 4      | 3        | Internship (400 hours)                             | 3         | Internship          |

*31 courses. Units add up to 91 (30 courses at 3 units, plus the 1-unit installation course).*

*Checked line by line against the 1-2021-BSCS-ML flowchart. Mathematical Analysis 1 and 2, College Physics 1 and 2, and Quantitative Methods appear in the flowchart but are supporting STEM subjects and are excluded from this table under the rule in section 2.*

## 5. NU Lipa BSIT - major subjects

**Full program title:** Bachelor of Science in Information Technology with Specialization in Mobile and Web Applications. Curriculum code 1-2021-BSIT-MWA, so this is also the 2021 curriculum.

This explains the basic-then-advanced pairs in web, mobile and multimedia - they are the declared specialization. It also explains why project management, quality assurance and AI are absent: the track spends those slots on mobile and web instead. Whether that is the right trade is exactly the question the study should be asking.

| **Yr** | **Term** | **Course**                                         | **Units** | **Classification**  |
|--------|----------|----------------------------------------------------|-----------|---------------------|
| 1      | 1        | Fundamentals of Programming                        | 3         | Core                |
| 1      | 1        | Introduction to Computing                          | 3         | Core                |
| 1      | 2        | Intermediate Programming                           | 3         | Core                |
| 1      | 2        | Hardware, Software and Peripheral Installation     | 1         | Professional        |
| 1      | 3        | Object-Oriented Programming                        | 3         | Core                |
| 1      | 3        | Discrete Structures 1                              | 3         | Core                |
| 2      | 1        | Data Structures and Algorithms                     | 3         | Core                |
| 2      | 1        | Platform Technologies                              | 3         | Professional        |
| 2      | 2        | Computer Organization and Architecture             | 3         | Core                |
| 2      | 2        | Information Management                             | 3         | Core                |
| 2      | 2        | Applications Development and Emerging Technologies | 3         | Professional        |
| 2      | 3        | Web Systems and Technologies                       | 3         | Professional        |
| 2      | 3        | Advanced Database Systems                          | 3         | Professional        |
| 2      | 3        | Social and Professional Issues                     | 3         | Professional        |
| 2      | 3        | Basic Networking                                   | 3         | Core                |
| 3      | 1        | Integrative Programming and Technologies           | 3         | Professional        |
| 3      | 1        | Systems Integration and Architecture               | 3         | Professional        |
| 3      | 1        | Advanced Networking                                | 3         | Professional        |
| 3      | 1        | Multimedia Systems                                 | 3         | Professional        |
| 3      | 1        | IT Project Management                              | 3         | Professional        |
| 3      | 2        | Advanced Multimedia Systems                        | 3         | Professional        |
| 3      | 2        | Mobile Programming                                 | 3         | Professional        |
| 3      | 2        | Systems Analysis and Design                        | 3         | Core                |
| 3      | 2        | Information Assurance and Security                 | 3         | Professional        |
| 3      | 3        | Advanced Web Programming                           | 3         | Professional        |
| 3      | 3        | Advanced Information Assurance and Security        | 3         | Professional        |
| 3      | 3        | Introduction to Human-Computer Interaction         | 3         | Professional        |
| 3      | 3        | Capstone Project 1                                 | 3         | Research / Capstone |
| 4      | 1        | Web Commercialization and E-Commerce               | 3         | Professional        |
| 4      | 1        | Advanced Mobile Programming                        | 3         | Professional        |
| 4      | 1        | Systems Administration and Maintenance             | 3         | Professional        |
| 4      | 1        | Capstone Project 2                                 | 3         | Research / Capstone |
| 4      | 2        | Internship 1 (400 hours)                           | 3         | Internship          |
| 4      | 3        | Internship 2 (400 hours)                           | 3         | Internship          |

*34 courses, 100 units, confirmed by a BSIT student against the actual curriculum*

What stands out in this curriculum: web, mobile, networking, multimedia, systems integration and systems administration are each covered twice (a basic course and an advanced one). That is a deliberate depth pattern, and it should be described that way in the analysis, not just as “present”.

*Checked line by line against the 1-2021-BSIT-MWA flowchart. Quantitative Methods appears in the flowchart but is excluded as supporting STEM.*

## 6. CHED baseline - CMO No. 25, s. 2015

CHED is used here as the regulatory floor, not as a model curriculum. The point of comparing against it is to show that a subject area is required, not that the CHED version of it is the best or most current one. Where the industry has moved past the 2015 PSG and in AI, cloud and data engineering it clearly has that gap is itself a finding.

| **Competency area in the PSG**                     | **Where it sits**         | **What we use it for**         |
|----------------------------------------------------|---------------------------|--------------------------------|
| Introduction to Computing                          | Both programs, foundation | Presence and sequencing        |
| Fundamentals of Programming                        | Both programs, foundation | Programming progression        |
| Intermediate Programming                           | Both programs, foundation | Progression toward OOP         |
| Object-Oriented Programming                        | Both programs, core       | Paradigm coverage              |
| Data Structures and Algorithms                     | Both programs, core       | Core competency benchmark      |
| Discrete Structures                                | BSCS core foundation      | Treated as core, not GE math’s |
| Algorithms and Complexity                          | BSCS core                 | Algorithmic depth              |
| Automata Theory / Formal Languages                 | BSCS core                 | Theory depth                   |
| Computer Architecture and Organization             | Both programs, core       | Systems foundation             |
| Operating Systems                                  | BSCS core                 | Systems benchmark              |
| Programming Languages                              | BSCS core                 | Language and paradigm breadth  |
| Information Management                             | Both programs, core       | Database benchmark             |
| Networks and Communications                        | Both programs             | Networking benchmark           |
| Information Assurance and Security                 | Both programs             | Security benchmark             |
| Human-Computer Interaction                         | Both programs             | Human-centered computing       |
| Applications Development and Emerging Technologies | Both programs             | Modern application development |
| Software Engineering                               | Both programs             | Process and lifecycle          |
| Social and Professional Practice                   | Both programs             | Professional practice          |
| Practicum / Industry Experience                    | Both programs             | Industry exposure              |
| Thesis / Capstone                                  | Both programs             | Research and project work      |

**Gap in this document:** the BSIT-specific competency list in CMO 25 differs from the BSCS one (BSIT is heavier on infrastructure, integration and service management, lighter on theory). The table above blends both. If the study makes a compliance claim for BSIT specifically, the BSIT annex of the CMO needs to be extracted separately.

## 7. BSCS benchmark institutions

### 7.1 UST - BSCS

| **Yr** | **Term** | **Course or area**                                                         | **Units** | **Classification**  |
|--------|----------|----------------------------------------------------------------------------|-----------|---------------------|
| 1      | 1        | Introduction to Computing                                                  | 3         | Core                |
| 1      | 1        | Computer Programming I (Fundamentals Imperative)                           | 5         | Core                |
| 1      | 2        | Computer Programming II (Intermediate, Object-Oriented)                    | 4         | Core                |
| 1      | 1        | Discrete Structures                                                        | 3         | Core                |
| 1      | 2        | Data Structures and Algorithms                                             | 4         | Core                |
| 2      | 1        | Design and Analysis of Algorithms                                          | 3         | Core                |
| 2      | 1        | Theory of Automata                                                         | 3         | Core                |
| 2      | 1        | Information Management                                                     | 4         | Core                |
| 2      | 2        | Computer Architecture and Organization                                     | 3         | Core                |
| 3      | 1        | Programming Languages                                                      | 3         | Core                |
| 3      | 1        | Software Engineering I                                                     | 3         | Core                |
| 2      | 2        | Introduction to Intelligent Systems                                        | 3         | Professional        |
| 2      | 2        | Applications Development and Emerging Technologies 2 (Enterprise Back-end) | 3         | Professional        |
| 1      | 2        | Human-Computer Interaction                                                 | 2         | Professional        |
| 4      | 1        | Graphics Computing and Multimedia Technology                               | 3         | Professional        |
| 4      | 1        | Computer Security and Information Assurance                                | 2         | Professional        |
| 4      | 2        | Emerging Technology                                                        | 1         | Professional        |
| 3-4    | varies   | Professional electives / specialization                                    | varies    | Specialization      |
| 4      | 2        | Practicum (250 hours)                                                      | 4         | Internship          |
| 3-4    | varies   | Thesis I and II                                                            | varies    | Research / Capstone |

*At least 56 units of named major subjects, excluding electives and thesis, which the page gives as variable.*

*Year and Term above are taken directly from UST's published Program Schedule for A.Y. 2023–2024 and are fully confirmed, not inferred.*

UST's page states the curriculum is effective A.Y. 2023–2024 and that the program runs three tracks: Core Computer Science, Game Development and Data Science. It also notes the curriculum may change when new guidelines are issued.

**Worth noticing** UST's programming courses carry 4–5 units where NU Lipa's carry 3. That is a depth difference that a simple present/absent table hides completely, and it is one of the more defensible findings available from this data.

### 7.2 DLSU - BSCS major in Software Technology

| **Yr**        | **Course or area**                                                                      | **Units** | **Classification**  |
|---------------|-----------------------------------------------------------------------------------------|-----------|---------------------|
| 1             | Introduction to Computing                                                               | 3         | Core                |
| 1             | Logic Formulation and Introductory Programming                                          | 3         | Core                |
| 1             | Programming with Structured Data Types                                                  | 3         | Core                |
| 1             | Object-Oriented Programming                                                             | 3         | Core                |
| 1             | Discrete Structures                                                                     | 3         | Core                |
| 1             | Data Structures and Algorithms                                                          | 3         | Core                |
| 2             | Information Management                                                                  | 3         | Core                |
| Not confirmed | Web Application Development                                                             | 3         | Core                |
| 2             | Advanced Programming Techniques                                                         | 3         | Professional        |
| Not confirmed | Software Engineering                                                                    | 3         | Professional        |
| 2             | Algorithms and Complexity                                                               | 3         | Professional        |
| Not confirmed | Introduction to Computer Networks                                                       | 3         | Professional        |
| Not confirmed | Computer Organization and Architecture I                                                | 3         | Professional        |
| Not confirmed | Computer Organization and Architecture II                                               | 4         | Professional        |
| Not confirmed | Operating Systems                                                                       | 3         | Professional        |
| 2             | Introduction to Artificial Intelligence                                                 | 3         | Professional        |
| Not confirmed | Secure Web Development                                                                  | 3         | Professional        |
| Not confirmed | Advanced Algorithms and Complexities                                                    | 3         | Specialization      |
| Not confirmed | Mobile Development                                                                      | 3         | Specialization      |
| Not confirmed | Advanced Software Engineering                                                           | 3         | Specialization      |
| Not confirmed | Advanced Database Systems                                                               | 3         | Specialization      |
| Not confirmed | Human Computer Interactions                                                             | 3         | Specialization      |
| Not confirmed | Advanced Intelligent Systems                                                            | 3         | Specialization      |
| Not confirmed | Distributed Computing                                                                   | 3         | Specialization      |
| Not confirmed | Software Technology Research Methods                                                    | 3         | Research / Capstone |
| Not confirmed | Professional electives (NLP, advanced ML, 3D graphics, data analytics, complex systems) | varies    | Specialization      |
| Not confirmed | Practicum                                                                               | 3         | Internship          |
| Not confirmed | Thesis                                                                                  | 6         | Research / Capstone |

*At least 85 units of named major subjects, excluding the variable professional electives. DLSU's page gives 173 academic units and 9 non-academic units for the whole program; general education and non-academic requirements are excluded from the table.*

*Year placement: Year 1 and the four Year 2, Term 1 courses shown are confirmed from a current DLSU CS student's public course-by-course account. The remaining rows are real, confirmed courses in the program, but their year placement was not found in an accessible source and is marked “Not confirmed” rather than guessed.*

DLSU is on a trimester system, which is why the course count is high. The unit totals are comparable across schools, but the per-term load is not, and the analysis should say so rather than implying DLSU students take more.

### 7.3 University of the Philippines - BSCS

The UP system does not offer a BSIT its computing degree is BSCS only, run under the same 2018 curriculum framework across UP units (course codes below are UP Cebu's published version). Data below is complete: full course list with year, term, units and prerequisites, from an official registrar-published checklist and study plan.

| Yr  | Term    | Course                                                                      | Units | Classification |
|-----|---------|-----------------------------------------------------------------------------|-------|----------------|
| 1   | 1       | Introduction to Computer Science                                            | 3     | Core           |
| 1   | 1       | Discrete Mathematical Structures in CS 1                                    | 3     | Core           |
| 1   | 2       | Fundamentals of Programming                                                 | 3     | Core           |
| 1   | 2       | Discrete Mathematical Structures in CS 2                                    | 3     | Core           |
| 2   | 1       | Programming Paradigms                                                       | 3     | Core           |
| 2   | 1       | Data Structures                                                             | 4     | Core           |
| 2   | 1       | Logic Design and Digital Computer Circuits                                  | 3     | Core           |
| 2   | 2       | Research Methods for Computer Science                                       | 3     | Core           |
| 2   | 2       | File Processing and Database Systems                                        | 3     | Core           |
| 2   | 2       | Intro to Computer Organization, Architecture, and Machine-Level Programming | 3     | Core           |
| 2   | 2       | Introduction to the Theory of Computation                                   | 3     | Core           |
| 3   | 1       | Technical Writing for Computer Science                                      | 3     | Core           |
| 3   | 1       | Design and Implementation of Programming Languages                          | 3     | Core           |
| 3   | 1       | Software Engineering 1                                                      | 3     | Core           |
| 3   | 1       | Design and Analysis of Algorithms                                           | 3     | Core           |
| 3   | 1       | Ethical and Professional Issues in Computing                                | 1     | Core           |
| 3   | 1       | Research Internship 1                                                       | 1     | Core           |
| 3   | 2       | Operating Systems                                                           | 3     | Core           |
| 3   | 2       | Software Engineering 2                                                      | 3     | Core           |
| 3   | 2       | Introduction to Artificial Intelligence                                     | 3     | Core           |
| 3   | 2       | Research Internship 2                                                       | 1     | Core           |
| 3   | Midyear | Practicum                                                                   | 3     | Core           |
| 4   | 1       | Data Communication and Networking                                           | 3     | Core           |
| 4   | 1       | Machine Learning                                                            | 3     | Core           |
| 4   | 1       | Special Problem 1                                                           | 2     | Core           |
| 4   | 2       | Introduction to Computer Security                                           | 3     | Core           |
| 4   | 2       | Technopreneurship                                                           | 3     | Core           |
| 4   | 2       | Special Problem 2                                                           | 2     | Core           |

*28 required courses, 82 units, all classified as Core here since UP's curriculum does not label them Professional/Specialization the way NU Lipa's does — the distinction UP draws instead is between these required courses and a separate open elective pool (not tallied above; students choose a set number of units from a list that includes web engineering, systems analysis and design, project management, computer graphics, and AI-adjacent electives such as expert systems). Year and term are taken directly from the published study plan and are fully confirmed.*

Two things worth noting for the comparison in section 9: UP's list has no dedicated networking, HCI, or security course among the required set (these may live in the untallied elective pool), and UP requires two research-internship courses plus a practicum, in addition to a research-based two-course capstone sequence a heavier and more research-oriented capstone structure than either UST or DLSU.

**7.4 Ateneo de Manila University - BSCS**

Ateneo does not offer a BSIT. Its computing degrees are BS Computer Science, BS Management Information Systems, and a joint BS Computer Science BS Digital Game Design and Development program; it also runs a BS Information Technology Entrepreneurship (BS ITE) program, but that is a business/entrepreneurship degree co-run with the business school, not a standard BSIT, and is not comparable here. Ateneo's CS program is the first and only one in the Philippines accredited Level 4 (the highest level) by PAASCU, and the department is a CHED Center of Excellence. Data below is complete and fully confirmed: pulled directly from Ateneo's official “Program of Study” PDF.

| Yr  | Term         | Course                                                | Units | Classification      |
|-----|--------------|-------------------------------------------------------|-------|---------------------|
| 1   | 1            | Introduction to Computing                             | 3     | Core                |
| 1   | 1            | Introduction to Programming I                         | 3     | Core                |
| 1   | 2            | Introduction to Programming II                        | 3     | Core                |
| 2   | 1            | Data Structures and Algorithms                        | 3     | Core                |
| 2   | 2            | Software Tools and Development Frameworks             | 3     | Professional        |
| 3   | 1            | Information Management                                | 3     | Core                |
| 3   | 1            | Computer Organization, Lecture                        | 3     | Core                |
| 3   | 1            | Computer Organization, Laboratory                     | 3     | Core                |
| 3   | 1            | Guided Studies in DISCS                               | 1     | Professional        |
| 3   | 1            | CSCI Major Elective                                   | 3     | Specialization      |
| 3   | 2            | Operating Systems, Lecture                            | 3     | Core                |
| 3   | 2            | Operating Systems, Laboratory                         | 3     | Core                |
| 3   | 2            | Introduction to Software Engineering                  | 3     | Core                |
| 3   | 2            | Guided Studies in DISCS                               | 1     | Professional        |
| 3   | 2            | Thesis Writing I                                      | 1     | Research / Capstone |
| 3   | 2            | CSCI Major Elective                                   | 3     | Specialization      |
| 4   | Intersession | Practicum                                             | 3     | Internship          |
| 4   | 1            | Computer Networks and Data Communications             | 3     | Core                |
| 4   | 1            | Structure and Interpretation of Programming Languages | 3     | Core                |
| 4   | 1            | CSCI Major Elective                                   | 3     | Specialization      |
| 4   | 1            | Guided Studies in DISCS                               | 1     | Professional        |
| 4   | 1            | Thesis Writing II                                     | 3     | Research / Capstone |
| 4   | 2            | Information Assurance and Security                    | 3     | Professional        |
| 4   | 2            | Theory of Computation                                 | 3     | Core                |
| 4   | 2            | Guided Studies in DISCS                               | 1     | Professional        |
| 4   | 2            | Thesis Writing III                                    | 3     | Research / Capstone |

*26 major/professional courses, 63 units, from the current Program of Study PDF. Year, term and units are fully confirmed. CSCI Major Electives (four slots) draw from areas including multimedia, MIS/software engineering, networks and wireless systems, web-based systems, computer engineering and computational science, but specific elective titles are not published in the Program of Study and are not tallied individually here.*

Notable for the comparison: Ateneo's thesis sequence runs three courses across two years (Thesis Writing I, II, III) plus a separate Practicum, which is a heavier and more sustained research requirement than any other school in this document, including UP's two-course Special Problem sequence. Ateneo’s database coverage is one course (Information Management), its networking depth is one course only, and there is no AI/machine learning course in the required list — these may be covered within the CSCI Major Elective slots, but that cannot be confirmed from the published Program of Study alone.

## 8. BSIT benchmark institutions

### 8.1 UST - BSIT

| **Yr** | **Course**                                                                 | **Units** | **Classification**  |
|--------|----------------------------------------------------------------------------|-----------|---------------------|
| 1      | Introduction to Computing                                                  | 3         | Core                |
| 1      | Computer Programming I (Fundamentals, Imperative)                          | 5         | Core                |
| 1      | Computer Programming II (Intermediate, Object-Oriented)                    | 4         | Core                |
| 1      | Discrete Structures                                                        | 3         | Core                |
| 1      | Information Technology Fundamentals                                        | 3         | Core                |
| 1      | Human-Computer Interaction                                                 | 3         | Professional        |
| 2      | Computer Architecture, Organization and Logic                              | 3         | Core                |
| 2      | Computer Architecture, Organization and Logic Laboratory                   | 1         | Core                |
| 2      | Data Communications and Networking II                                      | 3         | Core                |
| 2      | Data Communications and Networking II Laboratory                           | 1         | Core                |
| 2      | Applications Development and Emerging Technologies 2 (Enterprise Back-end) | 3         | Professional        |
| 3      | Software Engineering 1                                                     | 3         | Professional        |
| 3      | Applications Development and Emerging Technologies 3 (Mobile Programming)  | 3         | Professional        |
| 3      | Operating Systems                                                          | 3         | Core                |
| 3      | Social and Professional Practice                                           | 3         | Professional        |
| 3      | Information Technology Capstone Project I                                  | 3         | Research / Capstone |
| 3      | Professional Elective 1                                                    | 3         | Specialization      |
| 4      | System Integration and Architecture                                        | 3         | Professional        |
| 4      | Information Technology Capstone Project II                                 | 3         | Research / Capstone |
| 4      | Emerging Technologies                                                      | 1         | Professional        |
| 4      | Professional Elective 3                                                    | 3         | Specialization      |
| 4      | Professional Elective 4                                                    | 3         | Specialization      |
| 4      | Practicum (500 hours)                                                      | 6         | Internship          |

*69 units from what has been extracted.*

On the specialization tracks: the UST page describes elective tracks in Network and Security and in Web and Mobile App Development, and elsewhere on the same page mentions an IT Automation track. Because the page contradicts itself, both readings are recorded here as written. Do not pick one without asking the registrar.

### 8.2 TIP – BSIT

| Yr  | Term   | Course                                            | Units | Classification      |
|-----|--------|---------------------------------------------------|-------|---------------------|
| 1   | 1      | Introduction to Computing                         | 3     | Core                |
| 1   | 1      | Computer Programming 1                            | 3     | Core                |
| 1   | 2      | Computer Programming 2                            | 3     | Core                |
| 1   | 2      | Introduction to Human Computer Interaction        | 3     | Professional        |
| 2   | 1      | Data Structures and Algorithms                    | 3     | Core                |
| 2   | 1      | Web Systems and Technologies                      | 3     | Professional        |
| 2   | 1      | Prof. Elective 1                                  | 3     | Specialization      |
| 2   | 2      | Information Management                            | 3     | Core                |
| 2   | 2      | Platform Technologies                             | 3     | Professional        |
| 2   | 2      | Prof. Elective 2                                  | 3     | Specialization      |
| 3   | 1      | Integrative Programming and Technologies          | 3     | Professional        |
| 3   | 1      | Networking 1                                      | 3     | Core                |
| 3   | 1      | Advanced Database Systems                         | 3     | Professional        |
| 3   | 1      | Systems Integration and Architecture 1            | 3     | Professional        |
| 3   | 1      | IT Elective 1                                     | 3     | Specialization      |
| 3   | 2      | Data Mining and Warehousing                       | 3     | Professional        |
| 3   | 2      | Mobile Computing                                  | 3     | Professional        |
| 3   | 2      | Information Assurance and Security 1              | 3     | Professional        |
| 3   | 2      | Application Development and Emerging Technologies | 3     | Professional        |
| 3   | 2      | Networking 2                                      | 3     | Professional        |
| 3   | 2      | Prof. Elective 3                                  | 3     | Specialization      |
| 3   | 2      | IT Elective 2                                     | 3     | Specialization      |
| 3   | Summer | Data Analytics                                    | 3     | Specialization      |
| 3   | Summer | Capstone Project 1                                | 3     | Research / Capstone |
| 3   | Summer | IT Elective 3                                     | 3     | Specialization      |
| 4   | 1      | Social and Professional Issues                    | 3     | Professional        |
| 4   | 1      | Systems Administration and Maintenance            | 3     | Professional        |
| 4   | 1      | Information Assurance and Security 2              | 3     | Professional        |
| 4   | 1      | Systems Integration and Architecture 2            | 3     | Professional        |
| 4   | 1      | Prof. Elective 4                                  | 3     | Specialization      |
| 4   | 1      | IT Elective 4                                     | 3     | Specialization      |
| 4   | 2      | Internship in Computing                           | 6     | Internship          |
| 4   | 2      | Capstone Project 2                                | 3     | Research / Capstone |

*33 major/professional courses, 99 units, from the 2018 curriculum, still in effect. Year, term and units are fully confirmed pulled directly from TIP Quezon City's registrar-published checklist. Prof. Electives and IT Electives are drawn from named tracks (Animation and Mobile App Development, Cyber Security, Digital Arts and Design); specific elective titles vary by track and are not tallied individually here.*

Notable for the comparison: TIP requires two networking courses, two systems integration courses, and a dedicated data mining/data analytics sequence not present in NU Lipa's BSIT. It does not have a dedicated e-commerce course the way NU Lipa does. The internship (Internship in Computing) is 6 units but the checklist does not break out its hours the way NU Lipa's or UST's do.

### 8.3 Adamson University – BSIT

Adamson’s BSIT is the 2026 curriculum. Data below covers major and professional computing subjects only and follows the same rules as the other tables: lecture and laboratory components of the same course are combined into one row and their units added, general education, PE, theology, supporting math’s and science, and free electives are left out. The four IT Professional Tracks are shown as single rows with the elective options named in the curriculum. Term 3 is the Third-Year summer term.

| Yr  | Term | Course                                                                                                                                          | Units | Classification      |
|-----|------|-------------------------------------------------------------------------------------------------------------------------------------------------|-------|---------------------|
| 1   | 1    | Introduction to Computing                                                                                                                       | 3     | Core                |
| 1   | 1    | Fundamentals of Programming                                                                                                                     | 3     | Core                |
| 1   | 1    | Digital and Logic Circuits (lab)                                                                                                                | 1     | Core                |
| 1   | 2    | Computer Programming 1                                                                                                                          | 3     | Core                |
| 1   | 2    | Data Structure and Algorithms                                                                                                                   | 3     | Core                |
| 1   | 2    | Web Design Principles (lab)                                                                                                                     | 1     | Professional        |
| 2   | 1    | Database Management System                                                                                                                      | 3     | Core                |
| 2   | 1    | Computer Programming 2                                                                                                                          | 3     | Core                |
| 2   | 1    | Multimedia Technology                                                                                                                           | 3     | Professional        |
| 2   | 1    | Discrete Math                                                                                                                                   | 3     | Core                |
| 2   | 2    | Object Oriented Programming                                                                                                                     | 3     | Core                |
| 2   | 2    | Advanced Database Management System                                                                                                             | 3     | Professional        |
| 2   | 2    | Networking 1                                                                                                                                    | 3     | Core                |
| 3   | 1    | Networking 2                                                                                                                                    | 3     | Professional        |
| 3   | 1    | Information Assurance and Security 1                                                                                                            | 3     | Professional        |
| 3   | 1    | Project Management                                                                                                                              | 3     | Professional        |
| 3   | 1    | Applications Development and Emerging Technologies                                                                                              | 3     | Professional        |
| 3   | 1    | IT Professional Track 1 (Mobile Development or Game Analysis and Design)                                                                        | 3     | Specialization      |
| 3   | 2    | Systems Administration and Maintenance                                                                                                          | 3     | Professional        |
| 3   | 2    | Information Assurance and Security 2                                                                                                            | 3     | Professional        |
| 3   | 2    | Human Computer Interaction                                                                                                                      | 3     | Professional        |
| 3   | 2    | IT Capstone Project 1                                                                                                                           | 3     | Research / Capstone |
| 3   | 2    | IT Professional Track 2 (Developing ASP.NET Core, Web Frameworks, Advanced Internetwork Devices or Game Programming)                            | 3     | Specialization      |
| 3   | 2    | IT Professional Track 3 (Software Development for Enterprise Systems, Game Assets and Environment Design, or Virtualization and Cloud Services) | 3     | Specialization      |
| 3   | 3    | Systems Integration and Architecture                                                                                                            | 3     | Professional        |
| 3   | 3    | Code of Ethics for IT Professionals                                                                                                             | 3     | Professional        |
| 3   | 3    | IT Issues and Seminars                                                                                                                          | 3     | Professional        |
| 4   | 1    | IT Capstone Project 2                                                                                                                           | 3     | Research / Capstone |
| 4   | 1    | PC Repair and Troubleshooting (lab)                                                                                                             | 1     | Professional        |
| 4   | 1    | IT Professional Track 4 (2D Animation or System/Network Administration)                                                                         | 3     | Specialization      |
| 4   | 2    | On-the-Job Training for Information Technology                                                                                                  | 6     | Internship          |
| 4   | 2    | Technopreneurship                                                                                                                               | 3     | Professional        |

*32 major/professional courses, 93 units, from the 2026 curriculum. Year, term and units are read from Adamson’s registrar-hosted curriculum page; pre-requisites are available in that listing but are not carried into this table. Professional Track courses are counted as Specialization and their specific titles depend on the track chosen. The classification column is our analytical decision, as in section 2.*

Notable for the comparison: Adamson requires two networking courses, two information assurance and security courses, and a systems administration course, and it keeps Systems Integration and Architecture in the summer term. It has Human Computer Interaction and Multimedia Technology as required courses, and a dedicated Technopreneurship course. It has no dedicated data mining or analytics course among the required subjects. The internship (On-the-Job Training) is 6 units, and the listing does not state its hours.

### 8.4 Lyceum of the Philippines University (LPU Manila) – BSIT

LPU Manila’s BSIT is the curriculum effective SY 2022–2023, published by its College of Technology in Intramuros. Data below covers major and professional computing subjects only and follows the same rules as the other tables: lecture and laboratory components of a course are one row, general education, PE, NSTP, calculus, physics, statistics and quantitative methods are left out, and Discrete Mathematics is counted as Core (section 2). Living in the IT Era is counted as Core because it is a computing course rather than a general education one.

| Yr  | Term | Course                                             | Units | Classification      |
|-----|------|----------------------------------------------------|-------|---------------------|
| 1   | 1    | Fundamentals of Programming                        | 3     | Core                |
| 1   | 1    | Living in the IT Era                               | 3     | Core                |
| 1   | 2    | Information Assurance and Security 1               | 3     | Professional        |
| 1   | 2    | Information Management                             | 3     | Core                |
| 1   | 2    | Intermediate Programming                           | 3     | Core                |
| 1   | 2    | Introduction to Computing                          | 3     | Core                |
| 1   | 2    | Platform Technologies                              | 3     | Professional        |
| 2   | 1    | Applications Development and Emerging Technologies | 3     | Professional        |
| 2   | 1    | Data Structures and Algorithms                     | 3     | Core                |
| 2   | 1    | Object Oriented Programming                        | 3     | Core                |
| 2   | 2    | Discrete Mathematics 1                             | 3     | Core                |
| 2   | 2    | Information Assurance and Security 2               | 3     | Professional        |
| 2   | 2    | Integrative Programming and Technologies           | 3     | Professional        |
| 3   | 1    | Discrete Mathematics 2                             | 3     | Core                |
| 3   | 1    | IT Elective 1 (Non-Lab)                            | 3     | Specialization      |
| 3   | 1    | Multimedia Design and Programming                  | 3     | Professional        |
| 3   | 1    | Networks 1                                         | 3     | Core                |
| 3   | 1    | System Integration and Architecture 1              | 3     | Professional        |
| 3   | 1    | Technopreneurship                                  | 3     | Professional        |
| 3   | 1    | Web Systems and Technologies                       | 3     | Professional        |
| 3   | 2    | Advanced Database Management Systems               | 3     | Professional        |
| 3   | 2    | IT Elective 2 (with Lab)                           | 3     | Specialization      |
| 3   | 2    | Networks 2                                         | 3     | Professional        |
| 3   | 2    | Systems Administration and Maintenance             | 3     | Professional        |
| 3   | 2    | System Integration and Architecture 2              | 3     | Professional        |
| 3   | 2    | Social Issues and Professional Ethics              | 3     | Professional        |
| 4   | 1    | Capstone Project 1                                 | 3     | Research / Capstone |
| 4   | 1    | IT Elective 3 (Non-Lab)                            | 3     | Specialization      |
| 4   | 1    | OJT (600 hours)                                    | 6     | Internship          |
| 4   | 2    | Capstone Project 2                                 | 3     | Research / Capstone |
| 4   | 2    | Human Computer Interaction                         | 3     | Professional        |
| 4   | 2    | IT Elective 4 (with Lab)                           | 3     | Specialization      |

*32 major/professional courses, 99 units, from the SY 2022–2023 curriculum. Year, term and units are read directly from the official curriculum PDF; pre-requisites are listed there but are not carried into this table. IT Electives 1–4 are unnamed in the curriculum and are counted as Specialization. The classification column is our analytical decision, as in section 2.*

Notable for the comparison: LPU Manila requires two networking courses, two systems integration courses, two information assurance and security courses, and a dedicated Systems Administration and Maintenance course. It has Multimedia Design and Programming, Human Computer Interaction and Technopreneurship as required courses, and it states its internship hours (600). It has no dedicated data analytics, AI/machine learning or mobile development course in the required list; mobile or other topics may sit inside the four unnamed IT Electives.

### 8.5 Are four BSCS and four BSIT benchmarks enough?

BSCS is now benchmarked against four schools (UST, DLSU, UP, Ateneo) and BSIT against four (UST, TIP, Adamson, LPU Manila). Both are on workable footing, though BSIT has a caveat about UST and includes no state university. Three honest points:

- Four is workable for each program. Four source-backed schools can show real differences without claiming a representative picture of “what Philippine universities generally teach” the write-up should say “compared with UST, DLSU, UP and Ateneo” for BSCS and “compared with UST, TIP, Adamson and LPU Manila” for BSIT. The BSIT set is all private schools, which should be stated.

- Quality over count matters more here than reaching a round number. UST, DLSU, UP and Ateneo (for BSCS) are recent or system-standard, completely or near-completely extracted, and from named official pages. TIP, Adamson and LPU Manila (for BSIT) are fully extracted from official curriculum documents. UST (for BSIT) is the one exception its extraction is confirmed incomplete (section 8.1) so UST’s BSIT cells should be read as provisional. A school added just to inflate the count, using an old or partial curriculum, would weaken the study rather than strengthen it that was the actual problem with keeping PCU and Batangas State in without re-verifying their currency and completeness.

- No state university is among the BSIT benchmarks. PUP, University of San Carlos and MSU-IIT were attempted but returned incomplete or no usable course data and are not included here. A state BSIT benchmark would strengthen the comparison if one can be obtained later, but it is not required.

## 9. Comparison - BSCS

| **Competency**                 | **NU Lipa**                  | **UST**                      | **DLSU (Software Tech)**            | **UP**                                                         | **Ateneo**                                            |
|--------------------------------|------------------------------|------------------------------|-------------------------------------|----------------------------------------------------------------|-------------------------------------------------------|
| Programming foundations        | Yes                          | Yes                          | Yes                                 | Yes                                                            | Yes                                                   |
| Object-oriented programming    | Yes                          | Yes                          | Yes                                 | Yes, within Programming Paradigms                              | Not found under that name                             |
| Data structures and algorithms | Yes                          | Yes                          | Yes                                 | Yes                                                            | Yes                                                   |
| Algorithms and complexity      | Yes                          | Yes                          | Yes, plus an advanced course        | Yes                                                            | Not found in the required list                        |
| Discrete structures            | Yes, two courses             | Yes                          | Yes                                 | Yes, two courses                                               | Not found in the required list                        |
| Automata / formal languages    | Yes                          | Yes                          | Not found on the Software Tech page | Yes                                                            | Yes (Theory of Computation)                           |
| Computer architecture          | Yes                          | Yes                          | Yes, two courses                    | Yes                                                            | Yes, lecture and lab                                  |
| Operating systems              | Yes                          | Yes                          | Yes                                 | Yes                                                            | Yes, lecture and lab                                  |
| Databases                      | Yes, plus advanced           | Yes                          | Yes, plus advanced                  | Yes                                                            | Yes, via Information Management                       |
| Networking                     | Yes                          | Yes                          | Yes                                 | Yes                                                            | Yes, one course                                       |
| Software engineering           | Yes, two courses             | Yes                          | Yes, plus advanced                  | Yes, two courses                                               | Yes, plus Software Tools and Development Frameworks   |
| Security                       | Yes                          | Yes                          | Yes (Secure Web Development)        | Yes                                                            | Yes                                                   |
| Human-computer interaction     | Yes                          | Yes                          | Yes                                 | Not found in the required list                                 | Not found in the required list                        |
| AI / intelligent systems       | Yes, via the ML sequence     | Yes                          | Yes, plus advanced                  | Yes                                                            | Not found in the required list                        |
| Machine learning               | Yes, dedicated course        | Track or elective            | Professional elective               | Yes, dedicated course                                          | Not found in the required list                        |
| Deep learning                  | Yes, dedicated course        | Not found                    | Not found as a required course      | Not found                                                      | Not found                                             |
| Reinforcement learning         | Yes, dedicated course        | Not found                    | Not found                           | Not found                                                      | Not found                                             |
| Data science / analytics       | Yes, dedicated course        | Dedicated track              | Professional elective               | Not found in the required list                                 | Not found in the required list                        |
| Graphics / multimedia          | Not found                    | Yes                          | Elective (3D graphics)              | Not found                                                      | Possible via major elective slots                     |
| Distributed computing          | Not found                    | Track or elective            | Yes                                 | Not found                                                      | Not found                                             |
| Web development                | Not found as a major subject | Not found as a major subject | Yes, plus secure web                | Not found as a major subject                                   | Possible via major elective slots (web-based systems) |
| Mobile development             | Not found                    | Track or elective            | Yes                                 | Not found                                                      | Not found                                             |
| Emerging technologies          | Yes                          | Yes                          | Within advanced courses             | Not found under that name                                      | Not found under that name                             |
| Research / thesis              | Yes                          | Yes                          | Yes                                 | Yes, via Special Problem 1 and 2 plus two research internships | Yes, three thesis courses                             |
| Internship                     | Yes, 400 hours               | Yes, 250 hours               | Yes                                 | Yes, hours not stated in this checklist                        | Yes, Practicum, hours not stated                      |

## 10. Comparison – BSIT

| **Competency**                 | **NU Lipa**            | **UST**         | **TIP**                        | **Adamson (2026)**                                                             | **LPU Manila**                 |
|--------------------------------|------------------------|-----------------|--------------------------------|--------------------------------------------------------------------------------|--------------------------------|
| Programming foundations        | Yes                    | Yes             | Yes                            | Yes                                                                            | Yes                            |
| Object-oriented programming    | Yes                    | Yes             | Not found in the required list | Yes                                                                            | Yes                            |
| Data structures and algorithms | Yes                    | Not in extract  | Yes                            | Yes                                                                            | Yes                            |
| Databases                      | Yes, plus advanced     | Not in extract  | Yes, plus advanced             | Yes, plus advanced                                                             | Yes, plus advanced             |
| Networking                     | Yes, plus advanced     | Yes             | Yes, two courses               | Yes, plus advanced                                                             | Yes, two courses               |
| Systems integration            | Yes                    | Yes             | Yes, two courses               | Yes                                                                            | Yes, two courses               |
| Systems administration         | Yes                    | Not in extract  | Yes                            | Yes                                                                            | Yes                            |
| Security                       | Yes, two courses       | Elective track  | Yes, two courses               | Yes, two courses                                                               | Yes, two courses               |
| Web development                | Yes, plus advanced     | Elective track  | Yes                            | Web design (1 unit) required; web frameworks or ASP.NET Core as track elective | Yes                            |
| Mobile development             | Yes, plus advanced     | Yes (App Dev 3) | Yes                            | Track elective                                                                 | Not found in the required list |
| Multimedia                     | Yes, plus advanced     | Not in extract  | Elective track                 | Yes                                                                            | Yes                            |
| E-commerce                     | Yes                    | Not found       | Not found                      | Not found                                                                      | Not found                      |
| Systems analysis and design    | Yes                    | Not in extract  | Not found                      | Not found                                                                      | Not found                      |
| AI / machine learning          | Not found              | Not in extract  | Not found                      | Not found                                                                      | Not found                      |
| Data analytics                 | Not found              | Not in extract  | Yes, plus data mining          | Not found                                                                      | Not found                      |
| IT project management          | Yes                    | Not in extract  | Not found                      | Yes                                                                            | Not found                      |
| Quality assurance              | Not found              | Not in extract  | Not found                      | Not found                                                                      | Not found                      |
| Technopreneurship              | Not found              | Not in extract  | Not found                      | Yes                                                                            | Yes                            |
| Human-computer interaction     | Yes                    | Yes             | Yes                            | Yes                                                                            | Yes                            |
| Capstone                       | Yes, two terms         | Yes, two terms  | Yes, two courses               | Yes, two terms                                                                 | Yes, two terms                 |
| Internship                     | Yes, 2 × 400 hrs = 800 | 500 hours       | Yes, 6 units, hours not stated | Yes, 6 units, hours not stated                                                 | Yes, 600 hours                 |

## 11. Industry needs - what the evidence outside the academe shows

Sections 4–10 compare NU Lipa against other curricula. That can only show that NU Lipa differs from another school, which is not by itself a reason to change anything. This section brings in evidence from outside the academe published Philippine research on IT/CS employability, and a scan of current Philippine job postings so the eventual recommendations can be justified against what the industry actually asks for, not just against what other universities teach.

### 11.1 What this section is, and is not

This is a web search pass done in one sitting: searches across job boards (JobStreet, Glassdoor Philippines, Built In) and searches for published Philippine studies on IT/CS graduate employability and skills gaps. It found real, citable sources, which the plan in the earlier draft of this document did not yet have. It is not the coded, sampled job-posting study described in section 11.6 below that still needs to be done by the team, with a fixed role list, a fixed collection window, and two coders. Treat what follows as a credible first pass that narrows and justifies that later work, not as a finished dataset.

### 11.2 What published Philippine research says

Several Philippine studies have asked this exact question does the IT/CS curriculum match what employers want using methods close to what this project needs. They are worth reading in full before the paper is written, not just cited from this summary.

| **Study**                                                                                                                                                                   | **Method**                                                                                                                | **What it found**                                                                                                                                                                                                                                                                                                                                                                                                                                                                                               |
|-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------|---------------------------------------------------------------------------------------------------------------------------|-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| Bringula, Balcoba & Basa (2016), “Employable Skills of Information Technology Graduates in the Philippines,” ACM WCCCE                                                      | Survey of 44 IT academics and 44 IT practitioners, ranking 10 skills                                                      | <u>IT/technical skills</u> and <u>soft skills (teamwork, communication, problem-solving, attitude)</u> were both rated essential, with neither ranked clearly above the other. Practitioners rated problem-solving higher than academics did. The paper concluded the curriculum studied was largely responsive, which is a useful reminder that a gap analysis should also be able to say what is already working.                                                                                             |
| Unnamed authors (2025), “Assessing the IT Skills Gap: A Comparative Analysis of Tertiary IT Education in the Philippines and Industry Expectations,” ResearchGate           | Job listings from leading companies compared against BSIT course outlines using NLP semantic similarity and TF-IDF        | BSIT programs were found to cover foundational IT competencies adequately, but to fall short specifically in specialized and emerging technology skills. This is close to the method section 11.6 proposes, just done with an automated text-matching step instead of manual coding.                                                                                                                                                                                                                            |
| Miranda, Tayag & Canlas (2025), “Cybersecurity skills in new graduates: a Philippine perspective,” International Journal of Advances in Applied Sciences                    | JobStreet and Indeed Philippines postings (Sep–Nov 2023) plus a 152-person survey of students, teachers and professionals | Professionals, teachers and students all rated communication, critical thinking, problem-solving, ethical judgement, adaptability and continuous learning as the most necessary entry-level skills ahead of specific tool or certification knowledge. Certifications and incident-handling skills were rated less urgent for entry-level roles than for later-career ones, which is a useful nuance: what a curriculum should prioritize for a fresh graduate is not always what a senior job posting asks for. |
| Toquero & Ulanday (2021), “University Graduates’ Assessment of the Relevance of the Curriculum to the Labor Market in the Philippines,” International Research in Education | Survey of 1,761 graduates, one state university                                                                           | Found curriculum-labour market alignment was generally rated positively by graduates, and that this alignment was strongest when supported by practical, hands-on coursework and licensure or certification. Relevant to NU Lipa’s 400–800-hour internships, which are already a strength by this measure.                                                                                                                                                                                                      |

**The recurring pattern across all four:** soft skills, communication, problem-solving, teamwork, adaptability come up as consistently, or more consistently, important than any single technical tool. NU Lipa’s major-subject list has one course that speaks to this directly (Social and Professional Issues, one term, both programs). That is worth a second look: not necessarily a new course, but a check on whether communication and teamwork are exercised inside the technical courses, since the research above says this is where Philippine curricula most often fall short even when the technical content is sound.

### 11.3 What current job postings show

A scan of live postings on JobStreet, Glassdoor Philippines and Built In (September 2026) for the roles closest to what NU Lipa’s two tracks prepare graduates for:

| **Role**                                  | **What entry-level postings ask for**                                                                                                                                                                                                                                                                                                                                                                                                   |
|-------------------------------------------|-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| Software / web developer (general)        | Writing, testing and debugging code; Git; a modern web or mobile framework; and stated explicitly in more than one posting communicating clearly with both technical and non-technical teams. Entry salaries seen around ₱25,000–₱45,000/month.                                                                                                                                                                                         |
| Mobile developer                          | Cross-platform frameworks, chiefly Flutter and React Native, come up far more often than native-only iOS/Android skills. RESTful API integration and Git are near-universal requirements even at the junior level.                                                                                                                                                                                                                      |
| Web developer                             | React and Node.js dominate the postings sampled, alongside RESTful API design, database work (increasingly NoSQL as well as SQL), and Git. Several postings for combined “mobile app / web developer” roles suggest PH employers, especially smaller companies, do not always separate the two skill sets the way a curriculum track does.                                                                                              |
| Software / cloud-adjacent roles generally | Familiarity with a cloud platform (AWS, Azure or GCP) appears at the junior level more often than it used to one industry guide describes “junior cloud familiarity” as now an expectation rather than a senior-only skill. Agile/Scrum exposure is commonly listed even for entry-level postings.                                                                                                                                      |
| AI-adjacent roles                         | The clearest 2025–2026 trend is AI integration building on top of OpenAI, Anthropic or Google APIs rather than from-scratch model research. This is a different skill from the reinforcement-learning and deep-learning theory NU Lipa’s BSCS teaches, and the distinction matters: the curriculum may be strong on the research side of AI while the entry-level market is currently pulling toward the applied, API-integration side. |

### 11.4 The IT-BPM sector, in brief

The IT and Business Process Association of the Philippines (IBPAP) reported roughly \$40.3 billion in export revenue and 1.9 million workers for the sector in 2025, projecting close to \$42 billion and 1.97 million workers by the end of 2026. IBPAP’s own reporting describes the sector moving beyond traditional call-center work toward analytics, business intelligence, software development, engineering and cybersecurity, driven partly by the growth of global capacity centers. IBPAP has also stated that roughly two-thirds of its member companies have adopted AI in some form, and it is running workforce programs (with DICT and TESDA) aimed specifically at closing AI and digital skills gaps. This is useful context for the study’s introduction it establishes that the sector is real, growing and shifting toward higher-value technical work but it is sector-level and not curriculum-specific, so it should not stand in for the role-level evidence in 11.2 and 11.3.

### 11.5 What this means for NU Lipa’s two curricula

- Quality assurance and testing keep surfacing as worth watching. “Tests and debugs effectively” appear as a baseline expectation in general developer postings (11.3), and it is the kind of practical, hands-on skill the Toquero & Ulanday finding says employers value. Quality assurance is also not found in the required list of TIP, Adamson or LPU Manila (section 10), so the curriculum side now has three BSIT checks. Absence from a required list is not proof that a school never teaches it, so this is a pattern to confirm rather than a finding.

- NU Lipa BSIT’s Mobile Programming and Advanced Mobile Programming courses are confirmed as required, which matches real demand — but the curriculum flowchart does not specify which framework is taught. Postings lean toward Flutter and React Native. Worth checking with the department whether the course content matches current tooling, since “mobile programming” as a course title could mean native Android/iOS or a cross-platform framework, and that is a real difference for a graduate’s first job search.

- NU Lipa BSCS’s AI strength (section 12) may be angled differently from where entry-level market demand currently sits. The curriculum is heavy on ML/DL/RL theory and model-building; the freshest market signal is applied AI integration against existing APIs. Both are legitimate, but they are not the same skill, and the recommendation stage should be explicit about which one the curriculum is optimizing for.

- Cloud platform familiarity (AWS/Azure/GCP) and Agile/Scrum exposure are not visible as named topics anywhere in NU Lipa’s major-subject list, and they show up repeatedly in entry-level postings across roles. This is the kind of finding the job-posting sample in 11.6 should quantify properly right now it is a pattern from a handful of postings, not a percentage.

- Soft skills, communication, problem-solving, adaptability are the most consistent finding across every Philippine study found, more consistent than any single technical skill. NU Lipa has one course addressing this directly per program. Whether that is enough is a fair question for faculty and industry partners, not something this document can answer on its own.

## 12. What the data shows so far

These are observations from the evidence above. They are not yet recommendations every one of them needs a second source before it goes in the paper.

- NU Lipa curricula are dated 2021. The benchmarks span a range of years UST 2023–2024, DLSU not stated, UP’s checklist dated 2018 but still the current system-wide curriculum, Ateneo’s Program of Study PDF revised 2023, TIP 2018 (still in effect), Adamson 2026 and LPU Manila SY 2022–2023. So, the study is comparing a 2021 curriculum against a mix of older and newer ones. That asymmetry must be stated, and it cuts both ways: some apparent gaps may simply be curriculum drift rather than a design choice.

- Both programs are specialization tracks, not general BSCS and BSIT. That reframes most of the comparison. NU Lipa is not a general program that happens to lean toward ML or toward web and mobile it declares those tracks up front, so the fair comparison is against UST’s and DLSU’s track offerings, not against their core-only lists.

- NU Lipa BSCS is strong in AI. It has five dedicated courses (introduction to ML, advanced ML, deep learning, reinforcement learning, data science) where UST and DLSU treat the area as a track or an elective. AI should not be written up as a missing area; if anything, the interesting question is whether that concentration crowds out something else.

- NU Lipa BSCS covers the traditional CS foundations completely programming, data structures, algorithms, discrete structures, architecture, operating systems, programming languages, software engineering and automata.

- The clearest BSCS gap is in graphics/multimedia, distributed computing and web development, which appear at UST or DLSU but not in the NU Lipa BSCS major list.

- NU Lipa BSIT is built around web, mobile, networking and multimedia, each with a basic and an advanced course. That is a genuine specialization pattern, not just broad coverage.

- IT Project Management is confirmed present in NU Lipa BSIT Year 3, Term 1, alongside Integrative Programming and Systems Integration and Architecture. An earlier pass had missed it; it has been added to the curriculum table and this finding is corrected. Quality assurance and AI/machine learning are not found in the required list of TIP, Adamson or LPU Manila either (section 10), and UST's incomplete extraction (section 8.1) does not cover them. That makes it a three-school pattern, but it only shows they are absent from required courses, not that the schools never teach them.

- Depth differs even where coverage matches. UST's programming courses are 4–5 units against NU Lipa's 3. A present/absent table cannot see that, so unit counts need to stay in the analysis.

- Prerequisites are available. The flowcharts carry a pre-requisite column for every course, which the benchmark sources mostly do not. That makes sequencing analysis possible for NU Lipa in a way it is not yet for the other schools useful, but it means a sequencing comparison needs prerequisite data collected for the benchmarks too.

- An absent row is evidence about our sources, not about the university. Every claim of the form “university X does not offer Y” needs the full official curriculum for X before it can be made.

## 13. Turning this into the dataset and the analysis

### 13.1 Fields to record per course

| **Field**                     | **Why it is there**                               |
|-------------------------------|---------------------------------------------------|
| University                    | Identify the institution                          |
| Program                       | BSCS or BSIT                                      |
| Curriculum year / effectivity | Stops old and current curricula being mixed       |
| Course code                   | Precise identification                            |
| Course title                  | Human-readable name                               |
| Units                         | Measures weight, not just presence                |
| Year and term                 | Let’s us analyze sequencing                       |
| Prerequisite                  | Shows the learning progression                    |
| Classification                | One of the five labels in section 2               |
| Competency tag                | Normalizes different course names across schools  |
| Required or elective          | Separates what everyone takes from what some take |
| Practical component           | Lab, project or practicum hours                   |
| Source URL                    | Traceability back to the evidence                 |
| Date accessed                 | Currency of the evidence                          |
| Verification status           | Keeps confirmed data apart from assumptions       |

### 13.2 Order of analysis

The system should not jump from “this course is missing” to “add this course”. The steps in between are what make the recommendation defensible:

1.  Normalize course titles and map each course to a standard competency.

2.  Compare NU Lipa against the CHED baseline first, since that is a compliance question with a clear answer.

3.  Compare NU Lipa against the benchmark institutions, without ranking them.

4.  Measure depth, not just presence: number of courses, units, sequencing, required versus elective, lab and practicum exposure.

5.  Identify gaps, overlaps, and areas of heavy concentration over-concentration is a finding too.

6.  Cross-check against industry interviews, alumni and student feedback, job postings and relevant literature. A difference between two curricula is not by itself a reason to change one.

7.  Produce the recommendation with the evidence trail attached, and state plainly what still needs faculty or industry validation.

Two rules for the dataset itself: flag any curriculum version older than the NU curriculum year so the system does not treat an outdated curriculum as a current industry benchmark and keep required courses separate from electives and tracks throughout.

## 14. Limitations, and what to do next

### 14.1 Limitations to state in the paper

- The NU Lipa course lists come from curriculum flowcharts supplied by the team, not from a published NU web page. They have been checked course by course against those flowcharts, but the official copy should still be requested from the department so the study can cite a source.

- The UST BSIT extraction is incomplete and is missing courses that the program certainly has.

- The NU Lipa curricula are from 2021 and are older than most of the benchmarks they are being compared against.

- NU Lipa programs are specialization tracks. Comparing a track curriculum against another school’s general curriculum will produce apparent gaps that are just differences in what the program set out to do.

- No industry-side evidence has been collected yet. Section 11 sets out how to do it, but until it exists the study can say what universities teach and not what employers want.

- Course classification is our analytical decision, and another researcher could reasonably classify some courses differently.

- BSCS now has usable course-level data from five sources (NU Lipa, UST, DLSU, UP, Ateneo), one of which UP is a state university, though not one near NU Lipa geographically. BSIT also has five (NU Lipa, UST, TIP, Adamson, LPU Manila), all private, and UST’s own extraction is incomplete. BSIT has no state university benchmark, and the write-up should say so.

## 15. References

Commission on Higher Education. CMO No. 25, s. 2015 Revised Policies, Standards and Guidelines for the BSCS, BSIS and BSIT Programs.

https://legacy.ched.gov.ph/wp-content/uploads/2017/10/CMO-no.-25-s.-2015.pdf

University of Santo Tomas. Bachelor of Science in Computer Science.

https://www.ust.edu.ph/academics/programs/bachelor-of-science-in-computer-science/

University of Santo Tomas. Bachelor of Science in Information Technology.

https://www.ust.edu.ph/academics/programs/bachelor-of-science-in-information-technology/

De La Salle University, College of Computer Studies. BSCS major in Software Technology.

https://old.dlsu.edu.ph/colleges/ccs/undergraduate-degree-programs/cs-st/

University of the Philippines. BSCS curriculum checklist and study plan, 2018 curriculum (published via UP Cebu; course codes and sequencing are shared system-wide across UP units).

https://our.upcebu.edu.ph/wp-content/uploads/2025/11/NEW-Bachelor-of-Science-in-Computer-Science-Checklist-2018_rev-STAT-123.pdf

Technological Institute of the Philippines, Quezon City. 2018 Curriculum for Bachelor of Science in Information Technology (BSIT).

https://dru.tip.edu.ph/assets/generic-pages/downloadables/IT-2018-CURRICULUM_ARIS.pdf

Adamson University. Bachelor of Science in Information Technology (BSIT), 2026 curriculum.

https://www.adamson.edu.ph/v1/?page=curriculum&cid=%20%20%20%203r&curryear=2026

Lyceum of the Philippines University, Manila, College of Technology. Bachelor of Science in Information Technology (BSIT), curriculum effective SY 2022–2023.

https://manila.lpu.edu.ph/wp-content/uploads/2023/11/COT-BSIT-Curriculum-effective-AY22-23.pdf

Ateneo de Manila University, Department of Information Systems and Computer Science. Bachelor of Science in Computer Science, Program of Study.

https://www.ateneo.edu/sites/default/files/2023-05/BS%20CS%20Program%20of%20Study.pdf

National University Lipa. Curriculum for the Bachelor of Science in Computer Science with Specialization in Machine Learning (1-2021-BSCS-ML) and the Bachelor of Science in Information Technology with Specialization in Mobile and Web Applications (1-2021-BSIT-MWA). Curriculum flowcharts supplied by the project team; not a published web source.

**Industry-needs sources**

Bringula, R. P., Balcoba, A. C., & Basa, R. S. (2016). Employable Skills of Information Technology Graduates in the Philippines: Do Industry Practitioners and Educators have the Same View? Proceedings of the 21st Western Canadian Conference on Computing Education (ACM). https://dl.acm.org/doi/10.1145/2910925.2910928

Assessing the IT Skills Gap: A Comparative Analysis of Tertiary IT Education in the Philippines and Industry Expectations (2025). ResearchGate. https://www.researchgate.net/publication/391774899

Miranda, J. P. P., Tayag, M. I., & Canlas, J. D. (2025). Cybersecurity skills in new graduates: a Philippine perspective. International Journal of Advances in Applied Sciences, 14(4), 1217–1228. https://arxiv.org/pdf/2512.14778

Toquero, C. M. D., & Ulanday, D. M. P. (2021). University Graduates’ Assessment of the Relevance of the Curriculum to the Labor Market in the Philippines. International Research in Education, 9(1), 19–37. https://www.macrothink.org/journal/index.php/ire/article/view/17421

IT and Business Process Association of the Philippines (IBPAP). IT-BPM Industry Roadmap 2028 and 2026 Industry Overview. https://ibpap.org/knowledge-hub

Job posting scan, September 2026: JobStreet Philippines (ph.jobstreet.com), Glassdoor Philippines (glassdoor.com), Built In Manila (builtin.com), WebGeek Philippines developer guide (webgeek.ph).
