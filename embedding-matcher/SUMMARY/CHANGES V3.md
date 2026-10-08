# Changes V3

## 2026-10-06 - Rebrand interface as Curriculum Enhancer

- Updated the landing page, shared navigation and dashboard wordmarks, dashboard document title, copyright, and exported PDF metadata/footers to use "Curriculum Enhancer". The wordmark monogram remains "C".

## 2026-10-06 - Landing page rhythm and evidence statement

- Improved the landing wordmark's contrast consistently in the navigation and footer; both already used the same visible "C" markup.
- Varied section heading scale, added an open full-width navy statement between the feature cards and pipeline, and removed the action-like labels from the evidence card.
- Replaced the pipeline's short dash connectors with arrows on desktop and downward arrows on narrow screens. Functional routes, data metrics, and backend behavior are unchanged.

## 2026-10-06 - Landing page dark data-product redesign

- Reworked the public landing page into a dark, image-backed hero with an overlaid navigation, square CTAs, a dataset metrics strip, bento-style Generate/Enhance/evidence cards, an illustrative workflow pipeline, guest-trial callout, closing CTAs, and a compact footer.
- Reused the existing `assets/nu-lipa.jpg` image, Manrope font, navy/dark theme, and monospace token. The pipeline is explicitly labeled illustrative and not live telemetry.
- Metrics are a dated snapshot calculated from the checked-in `skill_coverage.csv` (56 distinct skill IDs) and `canonical_subject_bank.csv` (192 distinct canonical course titles and 8 distinct source institutions). These are dataset counts, not live database totals.
- Preserved existing landing-page destinations and PHP/session logic; responsive layout changes are scoped to landing-page classes.

## 2026-10-05 - Public landing, login, and self-service sign-up

- Added a minimal public landing page linking to login and sign-up; updated login to link back to the landing page and to sign-up, and sign-up to link back to login.
- Added a minimal self-service sign-up form with username, email, password, and confirmation fields, shared dark dashboard styling, and the existing `assets/rene.jpg` background.
- Public sign-ups reuse the existing password hashing and CSRF helpers, reject duplicate usernames, include a silent honeypot, and apply an eight-attempt-per-15-minute session limit.
- Preserved first-account setup: the first account created through either login or sign-up becomes `super_admin`. Sign-up serializes the first-account check and insertion in SQLite; every subsequent public account is assigned `user` with no role input, while super-admin account creation remains in `admin_users.php`.
- Successful sign-up creates the authenticated session and redirects to the Dashboard. No email verification, recovery email, or password-reset workflow was added.
- Sign-up now requires a unique email address; login accepts either the account username or email. Existing admin-created accounts with no email continue to authenticate by username.

## 2026-10-03 - Stage 1 backend plumbing for per-user keys and run source tracking

This stage added the backend plumbing required for the upcoming sidebar/chat UI redesign without changing browser layout or styling.

- Added `users.gemini_api_key` to the local auth schema and created `save_user_api_key()` / `clear_user_api_key()` helpers in `auth.php` with SQLite prepared statements. The legacy shared `settings.json` key mechanism remains in place as a fallback until the user-key flow is fully rolled out.
- Added a server-side gate before generation and enhancement requests: when the current user has no saved key, the PHP handler stops before launching the Python subprocess and shows an explicit message telling the user to add a key first.
- Passed the active user key to each subprocess using a scoped `GEMINI_API_KEY` environment variable for that process only, while leaving `curriculum_generator.py`'s existing environment/settings lookup intact as the final fallback source.
- Added `generated_curriculum_runs.source` with a safe additive migration, backfilled existing rows to `generated`, and set the generate/enhance insertion paths to `generated` and `enhanced` respectively.
- The "Remove key" button itself and the full sidebar/dashboard redesign remain pending in later stages; this change only adds the backend plumbing and database support needed for those future UI pieces.

## 2026-10-03 - Require personal Gemini keys in browser workflows

- Removed the shared `settings.json` fallback from browser generation, enhancement, and AI chat/edit subprocesses. Each user must have a personal key saved in their account; PHP passes that key through the existing child-process environment helper.
- Enabled regular users to submit curriculum enhancements as well as generated drafts; review and deletion remain admin/super-admin actions, and user management remains super-admin-only.
- Added inline key-missing notices to generation, enhancement, and AI-edit pages, linking users to the API settings form. The notice is page-scoped to avoid coupling it to the future shared-header/sidebar redesign.
- Kept the Python settings-file lookup for direct CLI/manual use and documented that the web app always supplies a personal key in `GEMINI_API_KEY`.

## 2026-10-03 - Stage 2 Dashboard sidebar shell

- Replaced the Dashboard's generation/key forms and coverage table with a data-backed overview showing draft and review counts, recent drafts, and lowest-similarity skill-coverage evidence.
- Added a dark, responsive fixed-width sidebar to the Dashboard only, with role-aware navigation, searchable merged generated/enhanced run history, and a bottom-pinned account menu.
- Wired account key save/remove to the existing per-user auth helpers; the UI shows only whether a key is saved and never displays its value.
- Kept the existing top navigation on all other pages. Stage 3 (shell rollout to the remaining pages) and Stage 4 (merged review view) remain pending.

## 2026-10-03 - Add run/chat attribution and CSRF protection

- Added additive nullable creator ID/username snapshot columns to curriculum runs and sender ID/username snapshot columns to chat messages. Existing rows remain unattributed and display a neutral legacy/unknown label.
- Passed authenticated user identity from browser generation, enhancement, and chat requests through `user_operations.py` into SQLite persistence; browser review attribution now uses the logged-in reviewer rather than a submitted name.
- Added session-bound CSRF token helpers and enforced token validation on all PHP POST handlers; included tokens in every POST form, including login, API-key, curriculum, chat, review, and user-management forms.
- Confirmed browser permissions: users may generate/enhance/chat; admins and super admins may additionally review/delete; only super admins may manage accounts.

## 2026-10-03 - Restore shared settings.json API-key fallback

- Restored Stage 1 key resolution for browser generation, enhancement, and chat: a personal key takes priority, with the existing shared environment/settings.json key as fallback.
- Subprocesses receive a personal `GEMINI_API_KEY` override only when the user has a personal key; proactive warnings and server-side blocks now apply only when neither personal nor shared key is available.
- This supersedes the brief personal-key-required-only behavior; no shared-key database table or admin settings UI was added.

## 2026-10-03 - Stage 3 shared sidebar shell rollout

- Reused the responsive sidebar shell across generation, enhancement, Skill Coverage, review-status, and super-admin user-management pages; role-aware Review and User Management navigation remains restricted, and Dataset Management remains absent from the sidebar.
- Moved generation and enhancement entry forms below their respective filters and run histories. The Dashboard stays focused on overview metrics, recent drafts, and a weakest-skill preview.
- Added `skill_coverage.php` as a dedicated searchable, type-filterable, sortable view of saved coverage evidence; Stage 4's merged review view remains pending.

## 2026-10-03 - Stage 4 merged review history

- Updated review history to distinguish generated and enhanced runs using the sidebar's existing colored source dots, with a client-side source filter and links to each run on its source page.
- Displayed review status as a dot and text label, and showed the stored reviewer name with a neutral "Unattributed" label for legacy entries without reviewer attribution.
- Completed the four-stage UI redesign while preserving review submission, CSRF, and viewer-role behavior.
