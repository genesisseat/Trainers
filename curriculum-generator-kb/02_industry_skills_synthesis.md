---
title: "Industry Skills Dataset - Final Synthesis Report"
type: "source-document"
source_file: "Final_Industry_Skills_Dataset_Companion.docx"
version: "final"
converted: "2026-09-21"
tags: [industry, skills, job-postings, entry-level, philippines, industry-signal]
description: "Synthesis of 113 entry-level Philippine IT/CS job postings across 7 role clusters: skills, entry-level flag and demand rating per cluster."
---

# Industry Skills Dataset - Final Synthesis Report

*Entry-level IT / CS roles, Philippines, 2025-2026. Data collected and finalized September 2026.*

> Row-level records with sources: [03_industry_skills_data](03_industry_skills_data.md) and [04_job_postings_log](04_job_postings_log.md).

Prepared to feed the curriculum-generation system's industry-signal input companion to the academic curricula dataset.
Data collected & finalized: Sept 2026 \| Total Job Postings Analyzed: 113 Verified Postings across 7 Role Clusters

## Purpose & How to Read This Document

It provides the final, verified 'industry signal' for entry-level IT and CS competencies in the Philippines. Combined with the academic signal (from standard curricula models), this dataset drives the core/add/keep/drop curriculum grouping logic.

Demand is rated High (appeared in 3+ independent postings/sources) or Medium (1–2). Entry-Level is Y when the position explicitly targets fresh graduates/juniors, and N when skills skew toward 1–3+ years of experience.

### Final Sampling Synthesis (113 Postings Logged)

| **Role Cluster**           | **Total Logged** | **Fresh Grad (Y)** | **Mid-Level (N)** | **Entry Signal %** |
|----------------------------|------------------|--------------------|-------------------|--------------------|
| Software/Web Developer     | 17               | 13                 | 1                 | 76.5%              |
| QA / Software Tester       | 16               | 11                 | 2                 | 68.8%              |
| Data Analyst               | 16               | 11                 | 2                 | 68.8%              |
| IT Support / Network Admin | 16               | 14                 | 2                 | 87.5%              |
| Cybersecurity Analyst      | 16               | 10                 | 2                 | 62.5%              |
| Cloud / DevOps (Trainee)   | 16               | 9                  | 3                 | 56.2%              |
| Mobile App Developer       | 16               | 11                 | 3                 | 68.8%              |
| **TOTAL**                  | **113**          | **79**             | **15**            | **69.9%**          |

### Role Cluster: Software / Web Developer

| **Skill / Competency**      | **Type**             | **Entry-Level** | **Demand Signal & Notes**                                                                             |
|-----------------------------|----------------------|-----------------|-------------------------------------------------------------------------------------------------------|
| Java                        | Programming Language | Y               | Demand: High. Repeated across multiple fresh-grad postings (Seven Seven Global, Octal, Accion Labs) |
| C#                          | Programming Language | Y               | Demand: Medium. Paired with MS SQL                                                                  |
| PHP                         | Programming Language | Y               | Demand: High. Aleson Shipping Lines PHP+MySQL role also matched                                     |
| JavaScript                  | Programming Language | Y               | Demand: High. Core front-end requirement across web roles                                           |
| HTML/CSS                    | Front-end            | Y               | Demand: High. Listed alongside PHP/JS in most web postings                                          |
| MySQL / MS SQL              | Database             | Y               | Demand: High. SQL scripting explicitly required                                                     |
| Spring Boot / Java EE       | Framework            | Y               | Demand: Medium. Backend framework for Java roles                                                    |
| Git / Version Control       | Tool                 | Y               | Demand: High. GitHub cited as most desired collaboration tool                                       |
| REST API design/integration | Concept              | Y               | Demand: High. 'Build clean, maintainable APIs' recurring phrase                                     |
| Agile/Scrum fundamentals    | Methodology          | Y               | Demand: Medium. Basic Agile understanding requested for entry roles                                 |
| Debugging & Testing basics  | Practice             | Y               | Demand: High. Consistently paired with 'maintain applications'                                      |
| .NET / C# (MAUI)            | Framework            | N               | Demand: Medium. Seen at 1-3 yrs level, borderline entry                                             |
| Python                      | Programming Language | Y               | Demand: High. Python usage grew 7pp YoY globally; also asked in PH trainee posts                    |

### Role Cluster: QA / Software Tester

| **Skill / Competency**                | **Type** | **Entry-Level** | **Demand Signal & Notes**                                                                |
|---------------------------------------|----------|-----------------|------------------------------------------------------------------------------------------|
| Manual Testing & Test Case Design     | Practice | Y               | Demand: High. Test plan/case/script design repeated across postings                    |
| Bug Tracking & Documentation          | Practice | Y               | Demand: High. Identify, document, track defects                                        |
| Regression & Performance Testing      | Practice | Y               | Demand: Medium |
| SQL (basic)                           | Database | Y               | Demand: Medium. 'Familiarity with SQL is a plus'                                       |
| Excel / Google Sheets                 | Tool     | Y               | Demand: Medium |
| Selenium / Automated Testing          | Tool     | N               | Demand: Medium. Skews slightly above entry but appears in junior-adjacent remote roles |
| Postman (API testing)                 | Tool     | N               | Demand: Medium. Selenium/Postman/Python combo                                          |
| Basic Programming/Scripting knowledge | Concept  | Y               | Demand: Medium. 'Basic knowledge of programming or scripting is a plus'                |

### Role Cluster: Data Analyst

| **Skill / Competency**                             | **Type**                | **Entry-Level** | **Demand Signal & Notes**                                          |
|----------------------------------------------------|-------------------------|-----------------|--------------------------------------------------------------------|
| SQL                                                | Database/Query Language | Y               | Demand: High. Proficient in SQL explicitly listed                |
| Python                                             | Programming Language    | Y               | Demand: High |
| Power BI (incl. DAX)                               | BI Tool                 | Y               | Demand: High. Dashboards & reports using Power BI or Tableau     |
| Tableau                                            | BI Tool                 | Y               | Demand: Medium |
| Excel (formulas)                                   | Tool                    | Y               | Demand: High |
| Data Cleaning / Wrangling                          | Practice                | Y               | Demand: High |
| Statistical Analysis & Data Modeling               | Concept                 | Y               | Demand: Medium |
| Cloud/DB platforms (Azure, Databricks, SQL Server) | Cloud/Database          | N               | Demand: Medium. Listed as familiarity plus, not core requirement |
| Data Storytelling/Business Reporting               | Soft/Applied Skill      | Y               | Demand: Medium |

### Role Cluster: IT Support / Network Administrator

| **Skill / Competency**                 | **Type** | **Entry-Level** | **Demand Signal & Notes**                                                   |
|----------------------------------------|----------|-----------------|-----------------------------------------------------------------------------|
| Troubleshooting Hardware/Software      | Practice | Y               | Demand: High. Recurring across 5+ postings scanned                        |
| Networking Fundamentals                | Concept  | Y               | Demand: High |
| Help Desk / Ticketing Systems          | Tool     | Y               | Demand: Medium |
| System Monitoring & Deployment Support | Practice | Y               | Demand: Medium |
| Basic Cloud Awareness (AWS/Azure)      | Cloud    | N               | Demand: Medium. 'support network, systems, cloud services, cybersecurity' |

### Role Cluster: Cybersecurity Analyst

| **Skill / Competency**                     | **Type**             | **Entry-Level** | **Demand Signal & Notes**                                                 |
|--------------------------------------------|----------------------|-----------------|---------------------------------------------------------------------------|
| Security Monitoring / SOC basics           | Practice             | Y               | Demand: Medium |
| Vulnerability Assessment                   | Practice             | Y               | Demand: Medium |
| Networking Fundamentals (security context) | Concept              | Y               | Demand: High. 'basic understanding of networking crucial'               |
| Python (security scripting)                | Programming Language | N               | Demand: Medium. Automation & penetration testing role, borderline entry |
| Cloud Security Basics                      | Cloud/Security       | N               | Demand: Medium. 'Monitors cloud security alerts, manages access'        |

### Role Cluster: Cloud / DevOps (Trainee)

| **Skill / Competency**             | **Type**             | **Entry-Level** | **Demand Signal & Notes**                                                          |
|------------------------------------|----------------------|-----------------|------------------------------------------------------------------------------------|
| AWS / Azure fundamentals           | Cloud Platform       | Y               | Demand: High. Cloud platform knowledge listed for DevOps roles, even entry-track |
| Automation scripting (Python)      | Programming Language | Y               | Demand: High. 'Most companies only require strong Python skills'                 |
| CI/CD & Docker                     | Tool                 | N               | Demand: High. Docker usage jumped +17pp YoY to 71%, largest single-year gain     |
| Infrastructure as Code (Terraform) | Tool                 | N               | Demand: Medium. Among most admired cloud/infra tools                             |

### Role Cluster: Mobile App Developer

| **Skill / Competency** | **Type**             | **Entry-Level** | **Demand Signal & Notes**                               |
|------------------------|----------------------|-----------------|---------------------------------------------------------|
| Dart / Flutter         | Framework            | Y               | Demand: High. Cross-platform mobile requirement       |
| Swift (iOS)            | Programming Language | Y               | Demand: Medium |
| Kotlin/Java (Android)  | Programming Language | Y               | Demand: Medium. Kotlin listed among tracked languages |
