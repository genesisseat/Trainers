---
title: "Industry Skills Data - Skill-Level Records"
type: "dataset"
source_file: "final_master_industry_skills_dataset.xlsx (sheets Read_Me & Methodology, Postings_Summary_Final, Industry_Skills_Data)"
version: "final"
converted: "2026-09-21"
tags: [industry, skills, dataset, rag, entry-level]
description: "One record per skill per role cluster with entry-level flag, demand signal, evidence count and source. Includes the decision framework (Core / Add / Keep / Drop) and sampling methodology."
---

# Industry Skills Data - Skill-Level Records

*Entry-level IT / CS skill signals in the Philippines, 2025-2026. Record IDs (IS-###) are stable and can be cited.*

## Status and scope

| Item | Value |
|---|---|
| Document Status | FINAL & VERIFIED (Expanded Sampling Completed) |
| Collection Window | 2025–2026 (Final Scan & Synthesis completed Sept 2026) |
| Total Job Postings Logged | 113 Verified Postings across 7 Key Role Clusters |
| Geographic Scope | Philippines (Metro Manila, CALABARZON / Laguna & Batangas, Central Visayas, Remote) |
| Primary Purpose | Feeds the industry signal input for academic IT/CS curriculum logic (Core / Add / Keep / Drop grouping). |

## Curriculum decision framework

| Signal combination | Grouping and action |
|---|---|
| High Industry Signal + High Academic Signal | CORE: Fundamental topics required across all IT/CS curricula. |
| High Industry Signal + Low Academic Signal | ADD: Emerging/In-demand skill to integrate into modern electives or core. |
| Low Industry Signal + High Academic Signal | KEEP (Prerequisite): Foundational theory needed for academic progression. |
| Low Industry Signal + Low Academic Signal | DROP: Obsolete or highly niche topic candidate for removal. |

## Sampling methodology

| Item | Detail |
|---|---|
| Sampling Methodology | 3-Round targeted web scraping and verification across JobStreet PH, Indeed PH, Bossjob PH, and corporate career portals. |
| Role Clusters Covered | Software/Web Developer (17), QA/Software Tester (16), Data Analyst (16), IT Support/Network Admin (16), Cybersecurity Analyst (16), Cloud/DevOps Trainee (16), Mobile App Developer (16). |
| Validation Standards | ACM/IEEE CS2023 & IT2017 Guidelines, IBPAP IT-BPM Industry Roadmap 2028, Stack Overflow 2025 Developer Survey. |

## Postings summary

| Role Cluster | Total Postings Logged | Explicit Fresh-Grad / Entry (Y) | Skews 1-3 Yrs / Mid (N) | Ambiguous / Trainee Track | Entry-Level Signal Ratio (%) |
|---|---|---|---|---|---|
| Software/Web Developer | 17 | 13 | 1 | 3 | 76.5% |
| QA / Software Tester | 16 | 11 | 2 | 3 | 68.8% |
| Data Analyst | 16 | 11 | 2 | 3 | 68.8% |
| IT Support / Network Admin | 16 | 14 | 2 | 0 | 87.5% |
| Cybersecurity Analyst | 16 | 10 | 2 | 4 | 62.5% |
| Cloud / DevOps (Trainee) | 16 | 9 | 3 | 4 | 56.2% |
| Mobile App Developer | 16 | 11 | 3 | 2 | 68.8% |
| TOTAL | 113 | 79 | 15 | 19 | 69.9% |

## Skill records by role cluster

Field meanings: Entry = Y when the posting targets fresh graduates or juniors, N when it skews to 1-3+ years. Demand = High when seen in 3+ independent postings or sources, Medium for 1-2. Evidence = number of supporting postings or sources.

### Software/Web Developer

| ID | Skill or competency | Type | Entry | Demand | Evidence | Source type | Source name | Source URL | Date | Collected | Notes |
|---|---|---|---|---|---|---|---|---|---|---|---|
| IS-001 | Java | Programming Language | Y | High | 4 | Job Posting | Indeed PH - Associate Software Engineer (Fresh Grad) | https://ph.indeed.com/q-entry-level-java-developer-l-philippines-jobs.html | 2025-2026 (rolling) | 2026-09-20 | Repeated across multiple fresh-grad postings (Seven Seven Global, Octal, Accion Labs) |
| IS-002 | C# | Programming Language | Y | Medium | 1 | Job Posting | Bossjob - Software Developer, Thurston Software | https://bossjob.com/en-us/job/software-developer-306213 | 2025-2026 | 2026-09-20 | Paired with MS SQL |
| IS-003 | PHP | Programming Language | Y | High | 3 | Job Posting | Indeed PH - Web/PHP Developer postings | https://ph.indeed.com/q-web-developer-entry-level-jobs.html | 2025-2026 | 2026-09-20 | Aleson Shipping Lines PHP+MySQL role also matched |
| IS-004 | JavaScript | Programming Language | Y | High | 3 | Job Posting | Indeed PH / JobStreet PH web developer postings | https://ph.indeed.com/q-web-developer-entry-level-jobs.html | 2025-2026 | 2026-09-20 | Core front-end requirement across web roles |
| IS-005 | HTML/CSS | Front-end | Y | High | 3 | Job Posting | Indeed PH - Web Developer, Aventus Medical Care | https://ph.indeed.com/q-web-developer-entry-level-jobs.html | 2025-2026 | 2026-09-20 | Listed alongside PHP/JS in most web postings |
| IS-006 | MySQL / MS SQL | Database | Y | High | 3 | Job Posting | Multiple PH postings (Thurston Software, Aleson Shipping) | https://bossjob.com/en-us/job/software-developer-306213 | 2025-2026 | 2026-09-20 | SQL scripting explicitly required |
| IS-007 | Spring Boot / Java EE | Framework | Y | Medium | 2 | Job Posting | Indeed PH - Java Developer (Spring Boot), Questronix | https://ph.indeed.com/q-entry-level,-java-developer-jobs.html | 2025-2026 | 2026-09-20 | Backend framework for Java roles |
| IS-008 | Git / Version Control | Tool | Y | High | 2 | Developer Survey + Job Postings | Stack Overflow Developer Survey 2025 | https://survey.stackoverflow.co/2025/technology/ | 2025-08 | 2026-09-20 | GitHub cited as most desired collaboration tool |
| IS-009 | REST API design/integration | Concept | Y | High | 3 | Job Posting | Multiple PH postings (Lexagle, VGSI, Aleson) | https://ph.indeed.com/q-entry-level,-java-developer-jobs.html | 2025-2026 | 2026-09-20 | 'Build clean, maintainable APIs' recurring phrase |
| IS-010 | Agile/Scrum fundamentals | Methodology | Y | Medium | 2 | Job Posting | JobStreet PH - Actionlabs IT Services listing | https://ph.indeed.com/q-software-developer-entry-level-jobs.html | 2025-2026 | 2026-09-20 | Basic Agile understanding requested for entry roles |
| IS-011 | Debugging & Testing basics | Practice | Y | High | 3 | Job Posting | Indeed PH - multiple junior developer listings | https://ph.indeed.com/q-software-developer-entry-level-jobs.html | 2025-2026 | 2026-09-20 | Consistently paired with 'maintain applications' |
| IS-012 | .NET / C# (MAUI) | Framework | N | Medium | 1 | Job Posting | Bossjob - Full Stack .NET Developer MAUI | https://bossjob.com/job/security-engineer-i-303521 | 2025-2026 | 2026-09-20 | Seen at 1-3 yrs level, borderline entry |
| IS-013 | Python | Programming Language | Y | High | 4 | Developer Survey + PH Postings | Stack Overflow 2025 / JobStreet PH DevOps Trainee | https://survey.stackoverflow.co/2025/technology/ | 2025-08 | 2026-09-20 | Python usage grew 7pp YoY globally; also asked in PH trainee posts |

### Mobile App Developer

| ID | Skill or competency | Type | Entry | Demand | Evidence | Source type | Source name | Source URL | Date | Collected | Notes |
|---|---|---|---|---|---|---|---|---|---|---|---|
| IS-014 | Dart / Flutter | Framework | Y | High | 2 | Job Posting | Indeed PH - Junior Mobile App Developer, Sandman Software | https://ph.indeed.com/q-web-developer-entry-level-jobs.html | 2025-2026 | 2026-09-20 | Cross-platform mobile requirement |
| IS-015 | Swift (iOS) | Programming Language | Y | Medium | 1 | Job Posting | Indeed PH - Junior Mobile App Developer, Sandman Software | https://ph.indeed.com/q-web-developer-entry-level-jobs.html | 2025-2026 | 2026-09-20 |  |
| IS-016 | Kotlin/Java (Android) | Programming Language | Y | Medium | 1 | Developer Survey | Stack Overflow Developer Survey 2025 | https://survey.stackoverflow.co/2025/technology/ | 2025-08 | 2026-09-20 | Kotlin listed among tracked languages |

### QA / Software Tester

| ID | Skill or competency | Type | Entry | Demand | Evidence | Source type | Source name | Source URL | Date | Collected | Notes |
|---|---|---|---|---|---|---|---|---|---|---|---|
| IS-017 | Manual Testing & Test Case Design | Practice | Y | High | 4 | Job Posting | JobStreet PH / Bossjob - QA Tester, Yonghwa; Test Analyst, Eclaro | https://bossjob.ph/en-us/job/qa-tester-cavite-336923 | 2025-2026 | 2026-09-20 | Test plan/case/script design repeated across postings |
| IS-018 | Bug Tracking & Documentation | Practice | Y | High | 3 | Job Posting | Bossjob - Software Tester, Cepat Kredit Financing | https://bossjob.com/job/software-tester-274068 | 2025-02 | 2026-09-20 | Identify, document, track defects |
| IS-019 | Regression & Performance Testing | Practice | Y | Medium | 2 | Job Posting | Bossjob - QA Tester, Yonghwa of Phils | https://bossjob.ph/en-us/job/qa-tester-cavite-336923 | 2025-07 | 2026-09-20 |  |
| IS-020 | SQL (basic) | Database | Y | Medium | 2 | Job Posting | Recooty - QA Analyst/Data Analyst Fresher | https://jobs.recooty.com/rchilli/data-analyst-fresher-rc414 | 2025-07 | 2026-09-20 | 'Familiarity with SQL is a plus' |
| IS-021 | Excel / Google Sheets | Tool | Y | Medium | 2 | Job Posting | Recooty - QA Analyst Fresher | https://jobs.recooty.com/rchilli/data-analyst-fresher-rc414 | 2025-07 | 2026-09-20 |  |
| IS-022 | Selenium / Automated Testing | Tool | N | Medium | 2 | Job Posting | JobStreet PH - Automation QA remote listings | https://ph.jobstreet.com/software-test-engineer-jobs/remote | 2025-2026 | 2026-09-20 | Skews slightly above entry but appears in junior-adjacent remote roles |
| IS-023 | Postman (API testing) | Tool | N | Medium | 1 | Job Posting | JobStreet PH - Automation QA remote listing | https://ph.jobstreet.com/software-test-engineer-jobs/remote | 2025-2026 | 2026-09-20 | Selenium/Postman/Python combo |
| IS-024 | Basic Programming/Scripting knowledge | Concept | Y | Medium | 2 | Job Posting | Bossjob - Software Tester, Cepat Kredit Financing | https://bossjob.com/job/software-tester-274068 | 2025-02 | 2026-09-20 | 'Basic knowledge of programming or scripting is a plus' |

### Data Analyst

| ID | Skill or competency | Type | Entry | Demand | Evidence | Source type | Source name | Source URL | Date | Collected | Notes |
|---|---|---|---|---|---|---|---|---|---|---|---|
| IS-025 | SQL | Database/Query Language | Y | High | 4 | Job Posting | Bossjob - Junior Data Analyst, Nityo Infotech | https://bossjob.com/job/data-analyst-quezon-city-329390 | 2025-06 | 2026-09-20 | Proficient in SQL explicitly listed |
| IS-026 | Python | Programming Language | Y | High | 3 | Job Posting + Survey | Nityo Infotech listing / Stack Overflow 2025 | https://bossjob.com/job/data-analyst-quezon-city-329390 | 2025-06 | 2026-09-20 |  |
| IS-027 | Power BI (incl. DAX) | BI Tool | Y | High | 3 | Job Posting | Nityo Infotech / JobStreet PH Junior Data Analyst listings | https://ph.jobstreet.com/junior-data-qa-jobs/full-time | 2025-2026 | 2026-09-20 | Dashboards & reports using Power BI or Tableau |
| IS-028 | Tableau | BI Tool | Y | Medium | 2 | Job Posting | Nityo Infotech listing | https://bossjob.com/job/data-analyst-quezon-city-329390 | 2025-06 | 2026-09-20 |  |
| IS-029 | Excel (formulas) | Tool | Y | High | 3 | Job Posting | Nityo Infotech / Recooty listings | https://bossjob.com/job/data-analyst-quezon-city-329390 | 2025-06 | 2026-09-20 |  |
| IS-030 | Data Cleaning / Wrangling | Practice | Y | High | 3 | Job Posting | Nityo Infotech listing | https://bossjob.com/job/data-analyst-quezon-city-329390 | 2025-06 | 2026-09-20 |  |
| IS-031 | Statistical Analysis & Data Modeling | Concept | Y | Medium | 2 | Job Posting | Nityo Infotech listing | https://bossjob.com/job/data-analyst-quezon-city-329390 | 2025-06 | 2026-09-20 |  |
| IS-032 | Cloud/DB platforms (Azure, Databricks, SQL Server) | Cloud/Database | N | Medium | 1 | Job Posting | Nityo Infotech listing | https://bossjob.com/job/data-analyst-quezon-city-329390 | 2025-06 | 2026-09-20 | Listed as familiarity plus, not core requirement |
| IS-033 | Data Storytelling/Business Reporting | Soft/Applied Skill | Y | Medium | 2 | Job Posting | Nityo Infotech listing | https://bossjob.com/job/data-analyst-quezon-city-329390 | 2025-06 | 2026-09-20 |  |

### IT Support / Network Admin

| ID | Skill or competency | Type | Entry | Demand | Evidence | Source type | Source name | Source URL | Date | Collected | Notes |
|---|---|---|---|---|---|---|---|---|---|---|---|
| IS-034 | Troubleshooting Hardware/Software | Practice | Y | High | 4 | Job Posting | JobStreet PH - multiple IT Staff (Fresh Grad) listings | https://ph.jobstreet.com/it-developer-jobs/in-Tanay-Rizal | 2025-2026 | 2026-09-20 | Recurring across 5+ postings scanned |
| IS-035 | Networking Fundamentals | Concept | Y | High | 3 | Job Posting | JobStreet PH - Network Support, IT Infrastructure listings | https://ph.jobstreet.com/network-developer-jobs/on-site | 2025-2026 | 2026-09-20 |  |
| IS-036 | Help Desk / Ticketing Systems | Tool | Y | Medium | 2 | Job Posting | JobStreet PH - Help Desk & IT Support category | https://ph.jobstreet.com/it-developer-jobs/in-Tanay-Rizal | 2025-2026 | 2026-09-20 |  |
| IS-037 | System Monitoring & Deployment Support | Practice | Y | Medium | 2 | Job Posting | JobStreet PH - IT Infrastructure Operations listing | https://ph.jobstreet.com/junior-cloud-devops-jobs/on-site | 2025-2026 | 2026-09-20 |  |
| IS-038 | Basic Cloud Awareness (AWS/Azure) | Cloud | N | Medium | 1 | Job Posting | JobStreet PH - OPPO Philippines IT Staff listing | https://ph.jobstreet.com/it-developer-jobs/in-Tanay-Rizal | 2025-2026 | 2026-09-20 | 'support network, systems, cloud services, cybersecurity' |

### Cybersecurity Analyst (Junior)

| ID | Skill or competency | Type | Entry | Demand | Evidence | Source type | Source name | Source URL | Date | Collected | Notes |
|---|---|---|---|---|---|---|---|---|---|---|---|
| IS-039 | Security Monitoring / SOC basics | Practice | Y | Medium | 3 | Job Posting | JobStreet PH - Junior SOC/Incident Response Analyst | https://ph.jobstreet.com/python-jobs-in-information-communication-technology/security/in-Pililla-Rizal | 2025-2026 | 2026-09-20 |  |
| IS-040 | Vulnerability Assessment | Practice | Y | Medium | 2 | Job Posting | Bossjob - Junior Vulnerability Analyst, J-K Network Services | https://bossjob.ph/en-us/job/cyber-security-analyst-pasay-410346 | 2025-12 | 2026-09-20 |  |
| IS-041 | Networking Fundamentals (security context) | Concept | Y | High | 2 | Job Posting + Report | 2025 Entry-Level Jobs overview / PH postings | https://demo.evennia.com/post/2025-entry-level-jobs | 2025 | 2026-09-20 | 'basic understanding of networking crucial' |
| IS-042 | Python (security scripting) | Programming Language | N | Medium | 1 | Job Posting | JobStreet PH - Security automation/pen-testing listing | https://ph.jobstreet.com/python-jobs-in-information-communication-technology/security/in-Pililla-Rizal | 2025-2026 | 2026-09-20 | Automation & penetration testing role, borderline entry |
| IS-043 | Cloud Security Basics | Cloud/Security | N | Medium | 2 | Job Posting | JobStreet PH - Junior Cloud security monitoring listing | https://ph.jobstreet.com/junior-cloud-jobs/in-Rizal-Baras-Rizal | 2025-2026 | 2026-09-20 | 'Monitors cloud security alerts, manages access' |

### Cloud/DevOps (Trainee)

| ID | Skill or competency | Type | Entry | Demand | Evidence | Source type | Source name | Source URL | Date | Collected | Notes |
|---|---|---|---|---|---|---|---|---|---|---|---|
| IS-044 | AWS / Azure fundamentals | Cloud Platform | Y | High | 3 | Job Posting + Report | Inquirer.net Highest-Paying Jobs 2025 / JobStreet trainee listings | https://technology.inquirer.net/139541/the-highest-paying-jobs-in-the-philippines-for-2025/amp | 2025 | 2026-09-20 | Cloud platform knowledge listed for DevOps roles, even entry-track |
| IS-045 | Automation scripting (Python) | Programming Language | Y | High | 2 | Job Posting + Report | Inquirer.net 2025 salary report | https://technology.inquirer.net/139541/the-highest-paying-jobs-in-the-philippines-for-2025/amp | 2025 | 2026-09-20 | 'Most companies only require strong Python skills' |
| IS-046 | CI/CD & Docker | Tool | N | High | 2 | Developer Survey | Stack Overflow Developer Survey 2025 | https://survey.stackoverflow.co/2025/technology/ | 2025-08 | 2026-09-20 | Docker usage jumped +17pp YoY to 71%, largest single-year gain |
| IS-047 | Infrastructure as Code (Terraform) | Tool | N | Medium | 1 | Developer Survey | Stack Overflow Developer Survey 2025 | https://survey.stackoverflow.co/2025/technology/ | 2025-08 | 2026-09-20 | Among most admired cloud/infra tools |

### Cross-role (all IT/CS entry roles)

| ID | Skill or competency | Type | Entry | Demand | Evidence | Source type | Source name | Source URL | Date | Collected | Notes |
|---|---|---|---|---|---|---|---|---|---|---|---|
| IS-048 | Communication Skills | Soft Skill | Y | High | 6 | Job Postings (aggregate) | Recurring across nearly all PH postings reviewed | https://ph.jobstreet.com/it-developer-jobs/in-Tanay-Rizal | 2025-2026 | 2026-09-20 | Appears in QA, dev, IT support, data analyst listings alike |
| IS-049 | Problem-Solving / Analytical Thinking | Soft Skill | Y | High | 6 | Job Postings (aggregate) | Recurring across PH postings reviewed | https://ph.indeed.com/q-software-developer-entry-level-jobs.html | 2025-2026 | 2026-09-20 |  |
| IS-050 | Learning Agility / Adaptability | Soft Skill | Y | High | 4 | Job Postings (aggregate) | Seven Seven Global, Sumidenso, multiple fresh-grad listings | https://ph.indeed.com/q-entry-level-java-developer-l-philippines-jobs.html | 2025-2026 | 2026-09-20 | 'learning potential and adaptability over prior experience' |
| IS-051 | Teamwork / Collaboration | Soft Skill | Y | High | 5 | Job Postings (aggregate) | Recurring across PH postings reviewed | https://ph.indeed.com/q-web-developer-entry-level-jobs.html | 2025-2026 | 2026-09-20 |  |
| IS-052 | AI Tool Fluency (Copilot/LLM-assisted work) | Emerging Skill | Y | High | 2 | Developer Survey + PH IT Staff listing | Stack Overflow 2025 / JobStreet PH IT Staff listing | https://survey.stackoverflow.co/2025/technology/ | 2025-08 | 2026-09-20 | 84% of devs globally use/plan to use AI tools; PH listing explicitly mentions 'exposure to emerging AI tools' |

### Industry-wide (macro signal)

| ID | Skill or competency | Type | Entry | Demand | Evidence | Source type | Source name | Source URL | Date | Collected | Notes |
|---|---|---|---|---|---|---|---|---|---|---|---|
| IS-053 | Cybersecurity specialists | Macro Shortage Signal |  | High | 1 | Industry Report | Edstellar - 7 Most In-Demand Skills in PH 2025 | https://edstellar.com/blog/skills-in-demand-in-philippines | 2025-03 | 2026-09-20 | Cites Philippines Labour Market Profile 2025/2026: demand outpaces supply |
| IS-054 | Data analysts | Macro Shortage Signal |  | High | 1 | Industry Report | Edstellar - 7 Most In-Demand Skills in PH 2025 | https://edstellar.com/blog/skills-in-demand-in-philippines | 2025-03 | 2026-09-20 |  |
| IS-055 | AI specialists | Macro Shortage Signal |  | High | 1 | Industry Report | Edstellar - 7 Most In-Demand Skills in PH 2025 | https://edstellar.com/blog/skills-in-demand-in-philippines | 2025-03 | 2026-09-20 |  |
| IS-056 | Digital/Talent upskilling (IT-BPM) | Macro Shortage Signal |  | High | 1 | Industry Report | IBPAP Roadmap 2028 review (via GMA News) | https://www.gmanetwork.com/news/money/economy/994797/ibpap-cuts-2028-it-bpm-revenue-jobs-forecasts-after-roadmap-review/ | 2026 (roadmap midpoint review) | 2026-09-20 | 1.9M IT-BPM employees as of 2025; shift 'from capacity to capability' — signals need for higher-value, more specialized skills |

