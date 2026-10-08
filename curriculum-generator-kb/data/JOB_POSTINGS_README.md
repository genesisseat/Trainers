# Curated Job Postings

`job_postings.csv` contains hand-maintained and imported job-posting links. The workbook importer is offline and does not visit URLs. It reads the `Industry_Skills_Data` and `Job_Postings_Log_113` sheets in `final_master_industry_skills_dataset.xlsx`; only direct-looking posting URLs and search/listing URLs are imported. Reports, surveys, articles, invalid links, unmapped roles, and duplicate URLs within a topic association are skipped and summarized.

| Column | Required content |
|---|---|
| `topic` | A topic name present in `job_topic_map.csv` |
| `posting_url` | HTTP(S) URL to an individual posting or a labeled listing page |
| `posting_title` | The posting title/source label, or the generated role-listings title |
| `employer` | Employer name when present in the source; otherwise blank |
| `date_retrieved` | Source collection/posted date in `YYYY-MM-DD` format; blank when unknown |
| `notes` | Optional curator notes |
| `link_type` | `posting` for an individual posting, `listing` for a search/results page |

Manual rows may omit `link_type`; they default to `posting`. Search-looking URLs are accepted only when explicitly marked `listing`. Do not label a search/results URL as an individual posting.

Run `python tools/import_job_links.py` from `embedding-matcher/` to import the workbook. The role-to-topic relationships are maintained in `role_cluster_topic_map.csv`. The import is idempotent for each URL/topic association and preserves existing manually maintained rows. A URL associated with more than one mapped topic can therefore have a row for each topic; recommendation selection still displays each URL at most once. Review the summary for imported counts and skipped rows. It never fetches URLs and does not invent titles, employers, or dates.

`job_topic_map.csv` maps recommendation-name keywords to topics. A recommendation may match more than one topic when whole-word/phrase keywords occur in its normalized title. Selection uses only matched topics, de-duplicates URLs, prefers direct postings over listing pages, sorts newest first within each type, and caps at five links per recommendation. Topic-level links are examples of industry demand, not proof that a specific skill is required. Links may change or be taken down after collection.
