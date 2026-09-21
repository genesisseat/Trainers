---
title: "Curriculum Courses - Row-Level Data (10 curricula)"
type: "dataset"
source_file: "curriculum_dataset.csv"
version: "v5-final data"
converted: "2026-09-21"
tags: [curriculum, courses, dataset, rag, academic-signal]
description: "287 major/professional computing courses from 10 curricula (NU Lipa, UST, DLSU, UP, Ateneo, TIP, Adamson, LPU Manila) with year, term, units, classification and stable course IDs."
---

# Curriculum Courses - Row-Level Data

*287 major and professional computing courses from 10 curricula. Course IDs (for example UST-BSCS-04) are stable and can be cited.*

## How to read this file

- **Classification:** Core, Professional, Specialization, Research / Capstone, Internship (definitions in [01_curriculum_benchmarking_dataset](01_curriculum_benchmarking_dataset.md), section 2).
- **Elective/track:** derived from the course name (contains "Elective" or "Professional Track"); it is not from the source documents.
- **Placement confirmed:** yes only when the row has a numeric year and a real term. DLSU and UST BSIT have no term data, and some UST/DLSU rows have no confirmed year.
- **Not included:** general education, PE, NSTP, supporting maths and science, prerequisites, competency tags.
- UST BSIT extraction is incomplete (see section 8.1 of the benchmarking document).

## NU Lipa - BSCS (ML specialization) (2021 (1-2021-BSCS-ML))

*31 courses. Role in study: baseline. Source section: 4.*

| ID | Year | Term | Course | Units | Classification | Elective/track | Placement confirmed |
|---|---|---|---|---|---|---|---|
| NULIPA-BSCS-01 | 1 | 1 | Fundamentals of Programming | 3 | Core | no | yes |
| NULIPA-BSCS-02 | 1 | 1 | Introduction to Computing | 3 | Core | no | yes |
| NULIPA-BSCS-03 | 1 | 2 | Intermediate Programming | 3 | Core | no | yes |
| NULIPA-BSCS-04 | 1 | 2 | Hardware, Software and Peripheral Installation | 1 | Professional | no | yes |
| NULIPA-BSCS-05 | 1 | 3 | Object-Oriented Programming | 3 | Core | no | yes |
| NULIPA-BSCS-06 | 1 | 3 | Discrete Structures 1 | 3 | Core | no | yes |
| NULIPA-BSCS-07 | 2 | 1 | Data Structures and Algorithms | 3 | Core | no | yes |
| NULIPA-BSCS-08 | 2 | 1 | Discrete Structures 2 | 3 | Core | no | yes |
| NULIPA-BSCS-09 | 2 | 2 | Computer Organization and Architecture | 3 | Core | no | yes |
| NULIPA-BSCS-10 | 2 | 2 | Information Management | 3 | Core | no | yes |
| NULIPA-BSCS-11 | 2 | 3 | Basic Networking | 3 | Core | no | yes |
| NULIPA-BSCS-12 | 2 | 3 | Algorithms and Complexity | 3 | Core | no | yes |
| NULIPA-BSCS-13 | 2 | 3 | Advanced Database Systems | 3 | Professional | no | yes |
| NULIPA-BSCS-14 | 3 | 1 | Introduction to Machine Learning | 3 | Specialization | no | yes |
| NULIPA-BSCS-15 | 3 | 1 | Operating Systems | 3 | Core | no | yes |
| NULIPA-BSCS-16 | 3 | 1 | Automata Theory and Formal Languages | 3 | Core | no | yes |
| NULIPA-BSCS-17 | 3 | 1 | Software Engineering 1 | 3 | Core | no | yes |
| NULIPA-BSCS-18 | 3 | 2 | Information Assurance and Security | 3 | Professional | no | yes |
| NULIPA-BSCS-19 | 3 | 2 | Software Engineering 2 | 3 | Core | no | yes |
| NULIPA-BSCS-20 | 3 | 2 | Advanced Machine Learning | 3 | Specialization | no | yes |
| NULIPA-BSCS-21 | 3 | 3 | Methods of Research | 3 | Research / Capstone | no | yes |
| NULIPA-BSCS-22 | 3 | 3 | Deep Learning | 3 | Specialization | no | yes |
| NULIPA-BSCS-23 | 3 | 3 | Applications Development and Emerging Technologies | 3 | Professional | no | yes |
| NULIPA-BSCS-24 | 3 | 3 | Introduction to Human-Computer Interaction | 3 | Professional | no | yes |
| NULIPA-BSCS-25 | 4 | 1 | Reinforcement Learning | 3 | Specialization | no | yes |
| NULIPA-BSCS-26 | 4 | 1 | Programming Languages | 3 | Core | no | yes |
| NULIPA-BSCS-27 | 4 | 1 | Social and Professional Issues | 3 | Professional | no | yes |
| NULIPA-BSCS-28 | 4 | 1 | Thesis 1 | 3 | Research / Capstone | no | yes |
| NULIPA-BSCS-29 | 4 | 2 | Data Science | 3 | Specialization | no | yes |
| NULIPA-BSCS-30 | 4 | 2 | Thesis 2 | 3 | Research / Capstone | no | yes |
| NULIPA-BSCS-31 | 4 | 3 | Internship (400 hours) | 3 | Internship | no | yes |

## NU Lipa - BSIT (MWA) (2021 (1-2021-BSIT-MWA))

*34 courses. Role in study: baseline. Source section: 5.*

| ID | Year | Term | Course | Units | Classification | Elective/track | Placement confirmed |
|---|---|---|---|---|---|---|---|
| NULIPA-BSIT-01 | 1 | 1 | Fundamentals of Programming | 3 | Core | no | yes |
| NULIPA-BSIT-02 | 1 | 1 | Introduction to Computing | 3 | Core | no | yes |
| NULIPA-BSIT-03 | 1 | 2 | Intermediate Programming | 3 | Core | no | yes |
| NULIPA-BSIT-04 | 1 | 2 | Hardware, Software and Peripheral Installation | 1 | Professional | no | yes |
| NULIPA-BSIT-05 | 1 | 3 | Object-Oriented Programming | 3 | Core | no | yes |
| NULIPA-BSIT-06 | 1 | 3 | Discrete Structures 1 | 3 | Core | no | yes |
| NULIPA-BSIT-07 | 2 | 1 | Data Structures and Algorithms | 3 | Core | no | yes |
| NULIPA-BSIT-08 | 2 | 1 | Platform Technologies | 3 | Professional | no | yes |
| NULIPA-BSIT-09 | 2 | 2 | Computer Organization and Architecture | 3 | Core | no | yes |
| NULIPA-BSIT-10 | 2 | 2 | Information Management | 3 | Core | no | yes |
| NULIPA-BSIT-11 | 2 | 2 | Applications Development and Emerging Technologies | 3 | Professional | no | yes |
| NULIPA-BSIT-12 | 2 | 3 | Web Systems and Technologies | 3 | Professional | no | yes |
| NULIPA-BSIT-13 | 2 | 3 | Advanced Database Systems | 3 | Professional | no | yes |
| NULIPA-BSIT-14 | 2 | 3 | Social and Professional Issues | 3 | Professional | no | yes |
| NULIPA-BSIT-15 | 2 | 3 | Basic Networking | 3 | Core | no | yes |
| NULIPA-BSIT-16 | 3 | 1 | Integrative Programming and Technologies | 3 | Professional | no | yes |
| NULIPA-BSIT-17 | 3 | 1 | Systems Integration and Architecture | 3 | Professional | no | yes |
| NULIPA-BSIT-18 | 3 | 1 | Advanced Networking | 3 | Professional | no | yes |
| NULIPA-BSIT-19 | 3 | 1 | Multimedia Systems | 3 | Professional | no | yes |
| NULIPA-BSIT-20 | 3 | 1 | IT Project Management | 3 | Professional | no | yes |
| NULIPA-BSIT-21 | 3 | 2 | Advanced Multimedia Systems | 3 | Professional | no | yes |
| NULIPA-BSIT-22 | 3 | 2 | Mobile Programming | 3 | Professional | no | yes |
| NULIPA-BSIT-23 | 3 | 2 | Systems Analysis and Design | 3 | Core | no | yes |
| NULIPA-BSIT-24 | 3 | 2 | Information Assurance and Security | 3 | Professional | no | yes |
| NULIPA-BSIT-25 | 3 | 3 | Advanced Web Programming | 3 | Professional | no | yes |
| NULIPA-BSIT-26 | 3 | 3 | Advanced Information Assurance and Security | 3 | Professional | no | yes |
| NULIPA-BSIT-27 | 3 | 3 | Introduction to Human-Computer Interaction | 3 | Professional | no | yes |
| NULIPA-BSIT-28 | 3 | 3 | Capstone Project 1 | 3 | Research / Capstone | no | yes |
| NULIPA-BSIT-29 | 4 | 1 | Web Commercialization and E-Commerce | 3 | Professional | no | yes |
| NULIPA-BSIT-30 | 4 | 1 | Advanced Mobile Programming | 3 | Professional | no | yes |
| NULIPA-BSIT-31 | 4 | 1 | Systems Administration and Maintenance | 3 | Professional | no | yes |
| NULIPA-BSIT-32 | 4 | 1 | Capstone Project 2 | 3 | Research / Capstone | no | yes |
| NULIPA-BSIT-33 | 4 | 2 | Internship 1 (400 hours) | 3 | Internship | no | yes |
| NULIPA-BSIT-34 | 4 | 3 | Internship 2 (400 hours) | 3 | Internship | no | yes |

## UST - BSCS (A.Y. 2023-2024)

*20 courses. Role in study: benchmark. Source section: 7.1.*

| ID | Year | Term | Course | Units | Classification | Elective/track | Placement confirmed |
|---|---|---|---|---|---|---|---|
| UST-BSCS-01 | 1 | 1 | Introduction to Computing | 3 | Core | no | yes |
| UST-BSCS-02 | 1 | 1 | Computer Programming I (Fundamentals Imperative) | 5 | Core | no | yes |
| UST-BSCS-03 | 1 | 2 | Computer Programming II (Intermediate, Object-Oriented) | 4 | Core | no | yes |
| UST-BSCS-04 | 1 | 1 | Discrete Structures | 3 | Core | no | yes |
| UST-BSCS-05 | 1 | 2 | Data Structures and Algorithms | 4 | Core | no | yes |
| UST-BSCS-06 | 2 | 1 | Design and Analysis of Algorithms | 3 | Core | no | yes |
| UST-BSCS-07 | 2 | 1 | Theory of Automata | 3 | Core | no | yes |
| UST-BSCS-08 | 2 | 1 | Information Management | 4 | Core | no | yes |
| UST-BSCS-09 | 2 | 2 | Computer Architecture and Organization | 3 | Core | no | yes |
| UST-BSCS-10 | 3 | 1 | Programming Languages | 3 | Core | no | yes |
| UST-BSCS-11 | 3 | 1 | Software Engineering I | 3 | Core | no | yes |
| UST-BSCS-12 | 2 | 2 | Introduction to Intelligent Systems | 3 | Professional | no | yes |
| UST-BSCS-13 | 2 | 2 | Applications Development and Emerging Technologies 2 (Enterprise Back-end) | 3 | Professional | no | yes |
| UST-BSCS-14 | 1 | 2 | Human-Computer Interaction | 2 | Professional | no | yes |
| UST-BSCS-15 | 4 | 1 | Graphics Computing and Multimedia Technology | 3 | Professional | no | yes |
| UST-BSCS-16 | 4 | 1 | Computer Security and Information Assurance | 2 | Professional | no | yes |
| UST-BSCS-17 | 4 | 2 | Emerging Technology | 1 | Professional | no | yes |
| UST-BSCS-18 | 3-4 | varies | Professional electives / specialization | varies | Specialization | yes | no |
| UST-BSCS-19 | 4 | 2 | Practicum (250 hours) | 4 | Internship | no | yes |
| UST-BSCS-20 | 3-4 | varies | Thesis I and II | varies | Research / Capstone | no | no |

## DLSU - BSCS (Software Technology) (not stated)

*28 courses. Role in study: benchmark. Source section: 7.2.*

| ID | Year | Term | Course | Units | Classification | Elective/track | Placement confirmed |
|---|---|---|---|---|---|---|---|
| DLSU-BSCS-01 | 1 | - | Introduction to Computing | 3 | Core | no | no |
| DLSU-BSCS-02 | 1 | - | Logic Formulation and Introductory Programming | 3 | Core | no | no |
| DLSU-BSCS-03 | 1 | - | Programming with Structured Data Types | 3 | Core | no | no |
| DLSU-BSCS-04 | 1 | - | Object-Oriented Programming | 3 | Core | no | no |
| DLSU-BSCS-05 | 1 | - | Discrete Structures | 3 | Core | no | no |
| DLSU-BSCS-06 | 1 | - | Data Structures and Algorithms | 3 | Core | no | no |
| DLSU-BSCS-07 | 2 | - | Information Management | 3 | Core | no | no |
| DLSU-BSCS-08 | Not confirmed | - | Web Application Development | 3 | Core | no | no |
| DLSU-BSCS-09 | 2 | - | Advanced Programming Techniques | 3 | Professional | no | no |
| DLSU-BSCS-10 | Not confirmed | - | Software Engineering | 3 | Professional | no | no |
| DLSU-BSCS-11 | 2 | - | Algorithms and Complexity | 3 | Professional | no | no |
| DLSU-BSCS-12 | Not confirmed | - | Introduction to Computer Networks | 3 | Professional | no | no |
| DLSU-BSCS-13 | Not confirmed | - | Computer Organization and Architecture I | 3 | Professional | no | no |
| DLSU-BSCS-14 | Not confirmed | - | Computer Organization and Architecture II | 4 | Professional | no | no |
| DLSU-BSCS-15 | Not confirmed | - | Operating Systems | 3 | Professional | no | no |
| DLSU-BSCS-16 | 2 | - | Introduction to Artificial Intelligence | 3 | Professional | no | no |
| DLSU-BSCS-17 | Not confirmed | - | Secure Web Development | 3 | Professional | no | no |
| DLSU-BSCS-18 | Not confirmed | - | Advanced Algorithms and Complexities | 3 | Specialization | no | no |
| DLSU-BSCS-19 | Not confirmed | - | Mobile Development | 3 | Specialization | no | no |
| DLSU-BSCS-20 | Not confirmed | - | Advanced Software Engineering | 3 | Specialization | no | no |
| DLSU-BSCS-21 | Not confirmed | - | Advanced Database Systems | 3 | Specialization | no | no |
| DLSU-BSCS-22 | Not confirmed | - | Human Computer Interactions | 3 | Specialization | no | no |
| DLSU-BSCS-23 | Not confirmed | - | Advanced Intelligent Systems | 3 | Specialization | no | no |
| DLSU-BSCS-24 | Not confirmed | - | Distributed Computing | 3 | Specialization | no | no |
| DLSU-BSCS-25 | Not confirmed | - | Software Technology Research Methods | 3 | Research / Capstone | no | no |
| DLSU-BSCS-26 | Not confirmed | - | Professional electives (NLP, advanced ML, 3D graphics, data analytics, complex systems) | varies | Specialization | yes | no |
| DLSU-BSCS-27 | Not confirmed | - | Practicum | 3 | Internship | no | no |
| DLSU-BSCS-28 | Not confirmed | - | Thesis | 6 | Research / Capstone | no | no |

## UP - BSCS (2018 (system-wide))

*28 courses. Role in study: benchmark. Source section: 7.3.*

| ID | Year | Term | Course | Units | Classification | Elective/track | Placement confirmed |
|---|---|---|---|---|---|---|---|
| UP-BSCS-01 | 1 | 1 | Introduction to Computer Science | 3 | Core | no | yes |
| UP-BSCS-02 | 1 | 1 | Discrete Mathematical Structures in CS 1 | 3 | Core | no | yes |
| UP-BSCS-03 | 1 | 2 | Fundamentals of Programming | 3 | Core | no | yes |
| UP-BSCS-04 | 1 | 2 | Discrete Mathematical Structures in CS 2 | 3 | Core | no | yes |
| UP-BSCS-05 | 2 | 1 | Programming Paradigms | 3 | Core | no | yes |
| UP-BSCS-06 | 2 | 1 | Data Structures | 4 | Core | no | yes |
| UP-BSCS-07 | 2 | 1 | Logic Design and Digital Computer Circuits | 3 | Core | no | yes |
| UP-BSCS-08 | 2 | 2 | Research Methods for Computer Science | 3 | Core | no | yes |
| UP-BSCS-09 | 2 | 2 | File Processing and Database Systems | 3 | Core | no | yes |
| UP-BSCS-10 | 2 | 2 | Intro to Computer Organization, Architecture, and Machine-Level Programming | 3 | Core | no | yes |
| UP-BSCS-11 | 2 | 2 | Introduction to the Theory of Computation | 3 | Core | no | yes |
| UP-BSCS-12 | 3 | 1 | Technical Writing for Computer Science | 3 | Core | no | yes |
| UP-BSCS-13 | 3 | 1 | Design and Implementation of Programming Languages | 3 | Core | no | yes |
| UP-BSCS-14 | 3 | 1 | Software Engineering 1 | 3 | Core | no | yes |
| UP-BSCS-15 | 3 | 1 | Design and Analysis of Algorithms | 3 | Core | no | yes |
| UP-BSCS-16 | 3 | 1 | Ethical and Professional Issues in Computing | 1 | Core | no | yes |
| UP-BSCS-17 | 3 | 1 | Research Internship 1 | 1 | Core | no | yes |
| UP-BSCS-18 | 3 | 2 | Operating Systems | 3 | Core | no | yes |
| UP-BSCS-19 | 3 | 2 | Software Engineering 2 | 3 | Core | no | yes |
| UP-BSCS-20 | 3 | 2 | Introduction to Artificial Intelligence | 3 | Core | no | yes |
| UP-BSCS-21 | 3 | 2 | Research Internship 2 | 1 | Core | no | yes |
| UP-BSCS-22 | 3 | Midyear | Practicum | 3 | Core | no | yes |
| UP-BSCS-23 | 4 | 1 | Data Communication and Networking | 3 | Core | no | yes |
| UP-BSCS-24 | 4 | 1 | Machine Learning | 3 | Core | no | yes |
| UP-BSCS-25 | 4 | 1 | Special Problem 1 | 2 | Core | no | yes |
| UP-BSCS-26 | 4 | 2 | Introduction to Computer Security | 3 | Core | no | yes |
| UP-BSCS-27 | 4 | 2 | Technopreneurship | 3 | Core | no | yes |
| UP-BSCS-28 | 4 | 2 | Special Problem 2 | 2 | Core | no | yes |

## Ateneo de Manila - BSCS (Program of Study PDF, 2023)

*26 courses. Role in study: benchmark. Source section: 7.4.*

| ID | Year | Term | Course | Units | Classification | Elective/track | Placement confirmed |
|---|---|---|---|---|---|---|---|
| ATENEO-BSCS-01 | 1 | 1 | Introduction to Computing | 3 | Core | no | yes |
| ATENEO-BSCS-02 | 1 | 1 | Introduction to Programming I | 3 | Core | no | yes |
| ATENEO-BSCS-03 | 1 | 2 | Introduction to Programming II | 3 | Core | no | yes |
| ATENEO-BSCS-04 | 2 | 1 | Data Structures and Algorithms | 3 | Core | no | yes |
| ATENEO-BSCS-05 | 2 | 2 | Software Tools and Development Frameworks | 3 | Professional | no | yes |
| ATENEO-BSCS-06 | 3 | 1 | Information Management | 3 | Core | no | yes |
| ATENEO-BSCS-07 | 3 | 1 | Computer Organization, Lecture | 3 | Core | no | yes |
| ATENEO-BSCS-08 | 3 | 1 | Computer Organization, Laboratory | 3 | Core | no | yes |
| ATENEO-BSCS-09 | 3 | 1 | Guided Studies in DISCS | 1 | Professional | no | yes |
| ATENEO-BSCS-10 | 3 | 1 | CSCI Major Elective | 3 | Specialization | yes | yes |
| ATENEO-BSCS-11 | 3 | 2 | Operating Systems, Lecture | 3 | Core | no | yes |
| ATENEO-BSCS-12 | 3 | 2 | Operating Systems, Laboratory | 3 | Core | no | yes |
| ATENEO-BSCS-13 | 3 | 2 | Introduction to Software Engineering | 3 | Core | no | yes |
| ATENEO-BSCS-14 | 3 | 2 | Guided Studies in DISCS | 1 | Professional | no | yes |
| ATENEO-BSCS-15 | 3 | 2 | Thesis Writing I | 1 | Research / Capstone | no | yes |
| ATENEO-BSCS-16 | 3 | 2 | CSCI Major Elective | 3 | Specialization | yes | yes |
| ATENEO-BSCS-17 | 4 | Intersession | Practicum | 3 | Internship | no | yes |
| ATENEO-BSCS-18 | 4 | 1 | Computer Networks and Data Communications | 3 | Core | no | yes |
| ATENEO-BSCS-19 | 4 | 1 | Structure and Interpretation of Programming Languages | 3 | Core | no | yes |
| ATENEO-BSCS-20 | 4 | 1 | CSCI Major Elective | 3 | Specialization | yes | yes |
| ATENEO-BSCS-21 | 4 | 1 | Guided Studies in DISCS | 1 | Professional | no | yes |
| ATENEO-BSCS-22 | 4 | 1 | Thesis Writing II | 3 | Research / Capstone | no | yes |
| ATENEO-BSCS-23 | 4 | 2 | Information Assurance and Security | 3 | Professional | no | yes |
| ATENEO-BSCS-24 | 4 | 2 | Theory of Computation | 3 | Core | no | yes |
| ATENEO-BSCS-25 | 4 | 2 | Guided Studies in DISCS | 1 | Professional | no | yes |
| ATENEO-BSCS-26 | 4 | 2 | Thesis Writing III | 3 | Research / Capstone | no | yes |

## UST - BSIT (A.Y. 2023-2024)

*23 courses. Role in study: benchmark. Source section: 8.1.*

| ID | Year | Term | Course | Units | Classification | Elective/track | Placement confirmed |
|---|---|---|---|---|---|---|---|
| UST-BSIT-01 | 1 | - | Introduction to Computing | 3 | Core | no | no |
| UST-BSIT-02 | 1 | - | Computer Programming I (Fundamentals, Imperative) | 5 | Core | no | no |
| UST-BSIT-03 | 1 | - | Computer Programming II (Intermediate, Object-Oriented) | 4 | Core | no | no |
| UST-BSIT-04 | 1 | - | Discrete Structures | 3 | Core | no | no |
| UST-BSIT-05 | 1 | - | Information Technology Fundamentals | 3 | Core | no | no |
| UST-BSIT-06 | 1 | - | Human-Computer Interaction | 3 | Professional | no | no |
| UST-BSIT-07 | 2 | - | Computer Architecture, Organization and Logic | 3 | Core | no | no |
| UST-BSIT-08 | 2 | - | Computer Architecture, Organization and Logic Laboratory | 1 | Core | no | no |
| UST-BSIT-09 | 2 | - | Data Communications and Networking II | 3 | Core | no | no |
| UST-BSIT-10 | 2 | - | Data Communications and Networking II Laboratory | 1 | Core | no | no |
| UST-BSIT-11 | 2 | - | Applications Development and Emerging Technologies 2 (Enterprise Back-end) | 3 | Professional | no | no |
| UST-BSIT-12 | 3 | - | Software Engineering 1 | 3 | Professional | no | no |
| UST-BSIT-13 | 3 | - | Applications Development and Emerging Technologies 3 (Mobile Programming) | 3 | Professional | no | no |
| UST-BSIT-14 | 3 | - | Operating Systems | 3 | Core | no | no |
| UST-BSIT-15 | 3 | - | Social and Professional Practice | 3 | Professional | no | no |
| UST-BSIT-16 | 3 | - | Information Technology Capstone Project I | 3 | Research / Capstone | no | no |
| UST-BSIT-17 | 3 | - | Professional Elective 1 | 3 | Specialization | yes | no |
| UST-BSIT-18 | 4 | - | System Integration and Architecture | 3 | Professional | no | no |
| UST-BSIT-19 | 4 | - | Information Technology Capstone Project II | 3 | Research / Capstone | no | no |
| UST-BSIT-20 | 4 | - | Emerging Technologies | 1 | Professional | no | no |
| UST-BSIT-21 | 4 | - | Professional Elective 3 | 3 | Specialization | yes | no |
| UST-BSIT-22 | 4 | - | Professional Elective 4 | 3 | Specialization | yes | no |
| UST-BSIT-23 | 4 | - | Practicum (500 hours) | 6 | Internship | no | no |

## TIP - BSIT (2018)

*33 courses. Role in study: benchmark. Source section: 8.2.*

| ID | Year | Term | Course | Units | Classification | Elective/track | Placement confirmed |
|---|---|---|---|---|---|---|---|
| TIP-BSIT-01 | 1 | 1 | Introduction to Computing | 3 | Core | no | yes |
| TIP-BSIT-02 | 1 | 1 | Computer Programming 1 | 3 | Core | no | yes |
| TIP-BSIT-03 | 1 | 2 | Computer Programming 2 | 3 | Core | no | yes |
| TIP-BSIT-04 | 1 | 2 | Introduction to Human Computer Interaction | 3 | Professional | no | yes |
| TIP-BSIT-05 | 2 | 1 | Data Structures and Algorithms | 3 | Core | no | yes |
| TIP-BSIT-06 | 2 | 1 | Web Systems and Technologies | 3 | Professional | no | yes |
| TIP-BSIT-07 | 2 | 1 | Prof. Elective 1 | 3 | Specialization | yes | yes |
| TIP-BSIT-08 | 2 | 2 | Information Management | 3 | Core | no | yes |
| TIP-BSIT-09 | 2 | 2 | Platform Technologies | 3 | Professional | no | yes |
| TIP-BSIT-10 | 2 | 2 | Prof. Elective 2 | 3 | Specialization | yes | yes |
| TIP-BSIT-11 | 3 | 1 | Integrative Programming and Technologies | 3 | Professional | no | yes |
| TIP-BSIT-12 | 3 | 1 | Networking 1 | 3 | Core | no | yes |
| TIP-BSIT-13 | 3 | 1 | Advanced Database Systems | 3 | Professional | no | yes |
| TIP-BSIT-14 | 3 | 1 | Systems Integration and Architecture 1 | 3 | Professional | no | yes |
| TIP-BSIT-15 | 3 | 1 | IT Elective 1 | 3 | Specialization | yes | yes |
| TIP-BSIT-16 | 3 | 2 | Data Mining and Warehousing | 3 | Professional | no | yes |
| TIP-BSIT-17 | 3 | 2 | Mobile Computing | 3 | Professional | no | yes |
| TIP-BSIT-18 | 3 | 2 | Information Assurance and Security 1 | 3 | Professional | no | yes |
| TIP-BSIT-19 | 3 | 2 | Application Development and Emerging Technologies | 3 | Professional | no | yes |
| TIP-BSIT-20 | 3 | 2 | Networking 2 | 3 | Professional | no | yes |
| TIP-BSIT-21 | 3 | 2 | Prof. Elective 3 | 3 | Specialization | yes | yes |
| TIP-BSIT-22 | 3 | 2 | IT Elective 2 | 3 | Specialization | yes | yes |
| TIP-BSIT-23 | 3 | Summer | Data Analytics | 3 | Specialization | no | yes |
| TIP-BSIT-24 | 3 | Summer | Capstone Project 1 | 3 | Research / Capstone | no | yes |
| TIP-BSIT-25 | 3 | Summer | IT Elective 3 | 3 | Specialization | yes | yes |
| TIP-BSIT-26 | 4 | 1 | Social and Professional Issues | 3 | Professional | no | yes |
| TIP-BSIT-27 | 4 | 1 | Systems Administration and Maintenance | 3 | Professional | no | yes |
| TIP-BSIT-28 | 4 | 1 | Information Assurance and Security 2 | 3 | Professional | no | yes |
| TIP-BSIT-29 | 4 | 1 | Systems Integration and Architecture 2 | 3 | Professional | no | yes |
| TIP-BSIT-30 | 4 | 1 | Prof. Elective 4 | 3 | Specialization | yes | yes |
| TIP-BSIT-31 | 4 | 1 | IT Elective 4 | 3 | Specialization | yes | yes |
| TIP-BSIT-32 | 4 | 2 | Internship in Computing | 6 | Internship | no | yes |
| TIP-BSIT-33 | 4 | 2 | Capstone Project 2 | 3 | Research / Capstone | no | yes |

## Adamson University - BSIT (2026)

*32 courses. Role in study: benchmark. Source section: 8.3.*

| ID | Year | Term | Course | Units | Classification | Elective/track | Placement confirmed |
|---|---|---|---|---|---|---|---|
| ADAMSON-BSIT-01 | 1 | 1 | Introduction to Computing | 3 | Core | no | yes |
| ADAMSON-BSIT-02 | 1 | 1 | Fundamentals of Programming | 3 | Core | no | yes |
| ADAMSON-BSIT-03 | 1 | 1 | Digital and Logic Circuits (lab) | 1 | Core | no | yes |
| ADAMSON-BSIT-04 | 1 | 2 | Computer Programming 1 | 3 | Core | no | yes |
| ADAMSON-BSIT-05 | 1 | 2 | Data Structure and Algorithms | 3 | Core | no | yes |
| ADAMSON-BSIT-06 | 1 | 2 | Web Design Principles (lab) | 1 | Professional | no | yes |
| ADAMSON-BSIT-07 | 2 | 1 | Database Management System | 3 | Core | no | yes |
| ADAMSON-BSIT-08 | 2 | 1 | Computer Programming 2 | 3 | Core | no | yes |
| ADAMSON-BSIT-09 | 2 | 1 | Multimedia Technology | 3 | Professional | no | yes |
| ADAMSON-BSIT-10 | 2 | 1 | Discrete Math | 3 | Core | no | yes |
| ADAMSON-BSIT-11 | 2 | 2 | Object Oriented Programming | 3 | Core | no | yes |
| ADAMSON-BSIT-12 | 2 | 2 | Advanced Database Management System | 3 | Professional | no | yes |
| ADAMSON-BSIT-13 | 2 | 2 | Networking 1 | 3 | Core | no | yes |
| ADAMSON-BSIT-14 | 3 | 1 | Networking 2 | 3 | Professional | no | yes |
| ADAMSON-BSIT-15 | 3 | 1 | Information Assurance and Security 1 | 3 | Professional | no | yes |
| ADAMSON-BSIT-16 | 3 | 1 | Project Management | 3 | Professional | no | yes |
| ADAMSON-BSIT-17 | 3 | 1 | Applications Development and Emerging Technologies | 3 | Professional | no | yes |
| ADAMSON-BSIT-18 | 3 | 1 | IT Professional Track 1 (Mobile Development or Game Analysis and Design) | 3 | Specialization | yes | yes |
| ADAMSON-BSIT-19 | 3 | 2 | Systems Administration and Maintenance | 3 | Professional | no | yes |
| ADAMSON-BSIT-20 | 3 | 2 | Information Assurance and Security 2 | 3 | Professional | no | yes |
| ADAMSON-BSIT-21 | 3 | 2 | Human Computer Interaction | 3 | Professional | no | yes |
| ADAMSON-BSIT-22 | 3 | 2 | IT Capstone Project 1 | 3 | Research / Capstone | no | yes |
| ADAMSON-BSIT-23 | 3 | 2 | IT Professional Track 2 (Developing ASP.NET Core, Web Frameworks, Advanced Internetwork Devices or Game Programming) | 3 | Specialization | yes | yes |
| ADAMSON-BSIT-24 | 3 | 2 | IT Professional Track 3 (Software Development for Enterprise Systems, Game Assets and Environment Design, or Virtualization and Cloud Services) | 3 | Specialization | yes | yes |
| ADAMSON-BSIT-25 | 3 | 3 | Systems Integration and Architecture | 3 | Professional | no | yes |
| ADAMSON-BSIT-26 | 3 | 3 | Code of Ethics for IT Professionals | 3 | Professional | no | yes |
| ADAMSON-BSIT-27 | 3 | 3 | IT Issues and Seminars | 3 | Professional | no | yes |
| ADAMSON-BSIT-28 | 4 | 1 | IT Capstone Project 2 | 3 | Research / Capstone | no | yes |
| ADAMSON-BSIT-29 | 4 | 1 | PC Repair and Troubleshooting (lab) | 1 | Professional | no | yes |
| ADAMSON-BSIT-30 | 4 | 1 | IT Professional Track 4 (2D Animation or System/Network Administration) | 3 | Specialization | yes | yes |
| ADAMSON-BSIT-31 | 4 | 2 | On-the-Job Training for Information Technology | 6 | Internship | no | yes |
| ADAMSON-BSIT-32 | 4 | 2 | Technopreneurship | 3 | Professional | no | yes |

## LPU Manila - BSIT (SY 2022-2023)

*32 courses. Role in study: benchmark. Source section: 8.4.*

| ID | Year | Term | Course | Units | Classification | Elective/track | Placement confirmed |
|---|---|---|---|---|---|---|---|
| LPU-BSIT-01 | 1 | 1 | Fundamentals of Programming | 3 | Core | no | yes |
| LPU-BSIT-02 | 1 | 1 | Living in the IT Era | 3 | Core | no | yes |
| LPU-BSIT-03 | 1 | 2 | Information Assurance and Security 1 | 3 | Professional | no | yes |
| LPU-BSIT-04 | 1 | 2 | Information Management | 3 | Core | no | yes |
| LPU-BSIT-05 | 1 | 2 | Intermediate Programming | 3 | Core | no | yes |
| LPU-BSIT-06 | 1 | 2 | Introduction to Computing | 3 | Core | no | yes |
| LPU-BSIT-07 | 1 | 2 | Platform Technologies | 3 | Professional | no | yes |
| LPU-BSIT-08 | 2 | 1 | Applications Development and Emerging Technologies | 3 | Professional | no | yes |
| LPU-BSIT-09 | 2 | 1 | Data Structures and Algorithms | 3 | Core | no | yes |
| LPU-BSIT-10 | 2 | 1 | Object Oriented Programming | 3 | Core | no | yes |
| LPU-BSIT-11 | 2 | 2 | Discrete Mathematics 1 | 3 | Core | no | yes |
| LPU-BSIT-12 | 2 | 2 | Information Assurance and Security 2 | 3 | Professional | no | yes |
| LPU-BSIT-13 | 2 | 2 | Integrative Programming and Technologies | 3 | Professional | no | yes |
| LPU-BSIT-14 | 3 | 1 | Discrete Mathematics 2 | 3 | Core | no | yes |
| LPU-BSIT-15 | 3 | 1 | IT Elective 1 (Non-Lab) | 3 | Specialization | yes | yes |
| LPU-BSIT-16 | 3 | 1 | Multimedia Design and Programming | 3 | Professional | no | yes |
| LPU-BSIT-17 | 3 | 1 | Networks 1 | 3 | Core | no | yes |
| LPU-BSIT-18 | 3 | 1 | System Integration and Architecture 1 | 3 | Professional | no | yes |
| LPU-BSIT-19 | 3 | 1 | Technopreneurship | 3 | Professional | no | yes |
| LPU-BSIT-20 | 3 | 1 | Web Systems and Technologies | 3 | Professional | no | yes |
| LPU-BSIT-21 | 3 | 2 | Advanced Database Management Systems | 3 | Professional | no | yes |
| LPU-BSIT-22 | 3 | 2 | IT Elective 2 (with Lab) | 3 | Specialization | yes | yes |
| LPU-BSIT-23 | 3 | 2 | Networks 2 | 3 | Professional | no | yes |
| LPU-BSIT-24 | 3 | 2 | Systems Administration and Maintenance | 3 | Professional | no | yes |
| LPU-BSIT-25 | 3 | 2 | System Integration and Architecture 2 | 3 | Professional | no | yes |
| LPU-BSIT-26 | 3 | 2 | Social Issues and Professional Ethics | 3 | Professional | no | yes |
| LPU-BSIT-27 | 4 | 1 | Capstone Project 1 | 3 | Research / Capstone | no | yes |
| LPU-BSIT-28 | 4 | 1 | IT Elective 3 (Non-Lab) | 3 | Specialization | yes | yes |
| LPU-BSIT-29 | 4 | 1 | OJT (600 hours) | 6 | Internship | no | yes |
| LPU-BSIT-30 | 4 | 2 | Capstone Project 2 | 3 | Research / Capstone | no | yes |
| LPU-BSIT-31 | 4 | 2 | Human Computer Interaction | 3 | Professional | no | yes |
| LPU-BSIT-32 | 4 | 2 | IT Elective 4 (with Lab) | 3 | Specialization | yes | yes |

