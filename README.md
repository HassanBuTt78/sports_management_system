# Sports Management System
**Government M.A.O Graduate College Lahore**

Final Year Project — a smart digital platform for managing college sports,
players, coaches, events, teams, and performance analytics.

## Final System Overview (current, authoritative)

This is the up-to-date description of the finished system. Everything below
this section is a **module-by-module development changelog** kept for
history — it documents the project as it was built, one module at a time,
and some of those older entries reference features that existed briefly
during development and have since been **deliberately removed**. Where the
changelog and this section disagree, this section is correct.

**Actors — exactly 3:**
- **Admin** — one account, manages the entire system
- **Coach** — multiple accounts, each assigned to exactly one sport
- **Player** — multiple accounts, each belonging to one sport/coach/team

**Sports — exactly 3:** Cricket, Football, Hockey.

**Explicitly not part of this system:** Principal (role/login/dashboard),
Volleyball, Racing, any AI/automated chatbot, and any "Live"/"Running"/
in-progress match status. Matches only ever move **Scheduled → Completed**
(or Cancelled) — a coach enters the final score, rates participating
players (1–5), and adds a result summary before completing a match; that
completion then recalculates performance and rankings automatically.

**Tech stack:** HTML5, CSS3, JavaScript, Bootstrap, PHP, MySQL, running on
XAMPP with phpMyAdmin for database management, and Chart.js for the
performance charts.

**Core modules:** Authentication (3 roles), Admin dashboard, Player/Coach/
Team management, Event management, Match management (score entry → player
rating → result/summary → completion), Performance analysis & rankings,
Notifications, and Coach↔Player↔Admin messaging/chat.

---

## ✅ Module 1 — Project Setup & Landing Website (this delivery)

Scope: **UI only**. No login logic, no database connection — as requested.

### Stack
- HTML5, CSS3, Bootstrap 5.3, vanilla JavaScript
- Font Awesome 6 (icons)
- Google Fonts — Poppins (headings) + Inter (body)
- AOS — Animate On Scroll library
- Swiper.js — testimonials carousel

All third-party libraries are loaded free via CDN (jsDelivr / cdnjs), so
there is nothing to `npm install`.

### Folder structure
```
SportsManagementSystem/
├── assets/
│   ├── css/style.css        Design system + all section styling
│   ├── js/script.js         AOS init, counters, Swiper, navbar scroll, etc.
│   ├── images/               Placeholder SVG artwork (hero, sport cards,
│   │                         gallery tiles, testimonial avatars, logo)
│   ├── videos/                (empty — for a future hero video)
│   └── icons/                 (empty — Font Awesome covers icons via CDN)
├── includes/
│   ├── header.php            <head> — meta, fonts, CSS libraries
│   ├── navbar.php             Sticky Bootstrap navbar
│   ├── footer.php             Footer + closing JS libraries
│   └── config.php             Site constants (NO database yet)
├── pages/
│   ├── home.php               Full Module 1 landing content
│   ├── about.php               Stub — "coming soon"
│   ├── sports.php               Stub — "coming soon"
│   ├── gallery.php               Stub — "coming soon"
│   ├── events.php                 Stub — "coming soon"
│   └── contact.php                 Stub — "coming soon"
├── login/                      Empty — built in the Authentication module
├── uploads/                    Empty — built in a later module
├── database/                   Empty — schema added in the DB module
├── index.php                   Entry point (assembles header+navbar+home+footer)
└── README.md
```

### Why `about/sports/gallery/events/contact` exist but are stubs
The brief's folder structure lists these under `pages/`, and the navbar/footer
link to them, so they're scaffolded now with a "coming soon" placeholder
(styled, not broken links) — full content for each is a natural follow-up
module once you confirm what each page needs.

### What's placeholder right now (by design, Module 1 only)
- **Images**: hand-built SVG placeholders in brand colors (hero banner, 4
  sport cards, 8 gallery tiles, 3 testimonial avatars, logo). Swap these for
  real photography in `assets/images/` — file names are already wired into
  the markup, so dropping in `.jpg`/`.png` files with the same names is a
  one-line change (or none, if you keep the same filenames as `.svg`… replace
  extension in the `img src` calls).
- **Content**: sports, features, stats, events, gallery captions, and
  testimonials are hardcoded PHP arrays at the top of `pages/home.php` —
  clearly marked, ready to be swapped for database queries later.
- **Login button**: intentionally inert (tooltip: "Login module arrives
  next") since auth isn't built yet.

### Local setup (XAMPP)
1. Copy the `SportsManagementSystem` folder into `htdocs/`.
2. Start Apache in the XAMPP control panel (no MySQL needed yet).
3. Visit `http://localhost/SportsManagementSystem/`.
4. If your folder name differs, update `BASE_URL` in `includes/config.php`.

## ✅ Module 2 — Database Architecture & Project Configuration

Scope: **schema + config only**. No login logic, no dashboards, no HTML pages.

- **`database/schema.sql`** — `CREATE DATABASE sports_management_system`, all
  18 tables (`admins`, `principals`, `sports`, `coaches`, `players`, `teams`,
  `team_players`, `events`, `event_participants`, `matches`, `player_scores`,
  `player_ratings`, `performance_analysis`, `messages`, `notifications`,
  `activity_logs`, `gallery`, `documents`), every foreign key/index/
  constraint, the one `ALTER TABLE` needed to close the players↔teams
  circular reference, and sample seed data. InnoDB + utf8mb4 throughout,
  normalized to 3NF.
- **`includes/config.php`** — now also opens a real `mysqli` connection to
  the database (in addition to the Module 1 site constants). The
  connection is **non-fatal** if MySQL isn't running yet or the schema
  hasn't been imported — `$conn` is simply `null`, so the Module 1 landing
  pages keep working regardless.
- **`database/ER_DIAGRAM.md`** — text-format ER diagram plus a full
  explanation of how every table connects to every other table, and the
  reasoning behind each `ON DELETE` choice.

Import instructions live in `database/ER_DIAGRAM.md` (section 5).

## ✅ Module 3 — Authentication System

Scope: **auth only**. No dashboards, no Player/Coach Management yet — those
are later modules. Uses **mysqli** (not PDO), per spec.

### Login pages (`login/`)
- `admin_login.php`, `coach_login.php`, `player_login.php`, `principal_login.php`
  — each a fully separate page (own form, own POST handler) sharing the
  same visual shell (`includes/auth_header.php` / `auth_footer.php`) and
  the same backend engine (`includes/auth.php`). A small role-switcher row
  lets you jump between the four without leaving the page.
- `index.php` — simple role picker; this is what the navbar's **Login**
  button now points to.
- `forgot_password.php` — pick a role + enter email → generates a 6-digit
  OTP (hashed with `password_hash()`, 10-minute expiry, stored in the new
  `password_resets` table). **No SMTP is configured**, so sending is
  simulated: the OTP is shown directly on screen (clearly marked "Demo
  mode") and logged to `uploads/otp_simulation_log.txt`. Swapping in real
  SMTP later only touches the one clearly-marked block in this file.
- `reset_password.php` — verifies the OTP, enforces an 8-character minimum,
  hashes the new password, updates the correct table, and clears any
  active lockout.
- `logout.php` — destroys the session, revokes the Remember Me token +
  cookie, logs the event, redirects home.

### Shared engine (`includes/`)
- `functions.php` — the single **role → table** map every other file reads
  from (`roleConfig()`), plus input cleaning, email/password validation,
  IP/browser detection, OTP generation, and `activity_logs` writes.
- `session.php` — hardened session cookies (`httponly`, `samesite=Lax`),
  session-fixation protection (`session_regenerate_id()` on login), the
  full session payload from the spec (id, name, email, role, profile
  image, sport_id/coach_id/team_id, login time), and teardown.
- `auth.php` — `attemptLogin()` (password verify → status check → lockout
  → session → activity log, all via prepared statements), the 5-attempts/
  15-minute lockout (`login_security` table), and Remember Me using a
  selector/validator cookie pattern (`remember_tokens` table) — safer than
  storing a single raw token, since a stolen DB dump alone can't be
  replayed as a valid cookie.
- `middleware.php` — `requireRole(...roles)` is **Direct URL Protection /
  RBAC**: not logged in → bounced to that role's login page; logged in as
  the wrong role → bounced to *their own* dashboard instead of a 403 wall.
- `config.php` (updated) — now also loads `functions.php`/`session.php`
  and starts the hardened session for every page automatically, so the
  Module 1 landing pages get it for free.

### New database tables (`database/schema_module3.sql`)
Three new tables — **no existing table was altered**:
`login_security` (failed attempts + lockout), `password_resets` (OTP
hashes), `remember_tokens` (Remember Me). All follow the same
polymorphic `user_role` + `user_id` pattern already used by
`activity_logs`/`messages`/`notifications` in Module 2.

### Protected placeholders (`admin/`, `coach/`, `player/`)
Each folder gets one `dashboard.php` guarded by `requireRole(...)` — just
enough to **prove RBAC works end-to-end** (try opening
`/player/dashboard.php` while logged in as a Coach — you'll get bounced to
the Coach dashboard instead). The real dashboards (stats, management
tools) are later modules.

### Import this module's SQL
```
mysql -u root -p sports_management_system < database/schema_module3.sql
```
(or phpMyAdmin → Import → `schema_module3.sql`, same as Module 2 — run
this *after* `schema.sql`.)

## ✅ Module 4 — Admin Dashboard

Scope: **Admin dashboard only**. No Player/Coach Management CRUD yet —
every management link is a working `<a>` to its future module's planned
URL (e.g. `admin/players/add.php`), so nothing here needs to change when
those modules land. Auth/RBAC from Module 3 is untouched — every page
below starts with `requireRole('admin')`.

### Pages (`admin/`)
- **`dashboard.php`** — the full ERP-style dashboard: 8 gradient stat
  cards (animated counters), 5 Chart.js charts, Quick Actions, a merged
  Recent Activity feed, System Health panel, Player/Coach summary tables
  (DataTables — search/sort built in), and Upcoming Events cards.
  **Every number is a live, read-only query** against the existing
  Module 2 schema — nothing on this page is mocked.
- **`profile.php`** — the admin's own account details, pulled from
  `admins`. Edit/Change Password are placeholder buttons (SweetAlert2
  "coming soon") — full editing is Module 14.
- **`settings.php`** — settings UI shell (general/security/notifications/
  appearance panels); Save buttons are placeholders — a real settings
  backend is out of this module's scope.
- **`search.php`** — the topbar's global search, live against
  players/coaches/teams/events (read-only `LIKE` search, filterable by
  type) — this is search, not management, so it's in-scope here.
- **`notifications_action.php`** — small AJAX endpoint behind the
  notification bell for **Mark as Read** / **Delete** (prepared
  statements, admin-only, JSON in/out).

### Shared admin UI (`includes/`)
- **`admin_sidebar.php`** — full menu from the brief, active-state
  highlighting, collapsible on mobile/tablet.
- **`admin_topbar.php`** — profile dropdown, live notification bell
  (unread badge + mark-read/delete), live message badge, global search
  bar, dark/light mode toggle.
- **`admin_footer.php`** — closing scripts (Bootstrap, Chart.js,
  DataTables, SweetAlert2, dashboard.js) + copyright bar.
- **`dashboard.css`** / **`dashboard.js`** — glassmorphism + gradient
  card styling, dark mode (persisted via `localStorage`), animated
  counters, all 5 Chart.js setups, DataTables init, and the notification
  AJAX handler.

### Placeholder dashboards replaced
The Module 3 placeholder `admin/dashboard.php` (which only proved RBAC
worked) is now the real Module 4 dashboard. `coach/`, `player/`, and
`principal/dashboard.php` are untouched — those become real dashboards in
their own later modules, the same way this one just did.

### Try it
- **Dark mode**: click the moon icon top-right — preference is remembered.
- **Mobile sidebar**: shrink the browser or view on a phone — the hamburger
  icon opens an overlay sidebar.
- **Notifications**: mark-as-read / delete update instantly via AJAX
  (SweetAlert2 toast confirms it) — try it against the sample notification
  seeded in Module 2.
- **Global search**: type a seeded name (e.g. "Ahmad") into the topbar
  search bar.

## ✅ Module 5 — Player Management

Scope: **Player Management only**. Admin-only (`requireRole('admin')`), no
Coach/Team Management CRUD yet — those links elsewhere still point to
future modules. No schema changes were needed — the `players` table
already had every field this module needed.

### Pages (`admin/player/`)
- **`index.php`** — stat cards (Total/Active/Inactive/New This Month),
  server-side filters (Sport/Coach/Team/Status/Gender), and a DataTable
  with Excel/PDF/Print export (DataTables Buttons extension) plus a full
  action row per player (Quick View, Full Profile, Edit, Reset Password,
  Deactivate).
- **`create.php`** — Add Player. Auto-generates a secure password
  (never typed by the admin), hashes it with `password_hash()`, validates
  every field server-side (unique email/roll_no/phone, image type/size),
  and shows the Player ID/email/temporary password exactly once with a
  **Print Login Credentials** button.
- **`edit.php`** — same fields, optional image replace (old file deleted
  on replace), uniqueness checks correctly exclude the player's own row.
- **`view.php`** — the full **Player Profile** (Feature 8): picture,
  complete info, assigned coach/team/sport (linked out to their future
  modules), match history (from `player_scores`/`matches`), events joined
  (from `event_participants`/`events`), performance summary (from
  `performance_analysis`), and star rating (avg of `player_ratings`).
- **`delete.php`** — soft delete only, exactly as instructed: sets
  `status = 'inactive'`, never runs `DELETE`. Confirmed via SweetAlert2.
- **`reset_password.php`** — generates + hashes a new password, returns
  the plain value once for the admin to hand to the player.
- **`player_details.php`** — lightweight JSON used by the index table's
  "Quick View" (eye icon) SweetAlert2 popup.
- **`ajax_get_coaches.php` / `ajax_get_teams.php`** — power the
  sport-scoped "Assign Coach" / "Assign Team" dropdowns on create/edit.

### New shared helpers (`includes/functions.php` — additive only)
`generateSecurePassword()`, `isFieldUnique()`, `validateUploadedImage()`,
`storeUploadedImage()`, `deleteUploadedFile()` — written generically so
Coach/Team Management can reuse them as-is later.

### Small, backward-compatible tweaks to existing files
- `includes/admin_footer.php` — pages can now set `$extraFooterScripts`
  to load extra libraries (used here for DataTables Buttons); pages that
  don't set it behave exactly as before.
- `includes/admin_sidebar.php` — Player Management now links to the real
  module, with correct active-state highlighting for nested folders.
- `admin/dashboard.php` / `admin/search.php` — the Add Player quick
  action and the Latest Players table/search results now link to the
  real pages instead of placeholders.

### Uploads
Player profile photos land in `uploads/players/` (JPG/PNG/JPEG, max 2MB,
validated both by extension and by `getimagesize()`).

## ✅ Module 6 — Coach Management

Scope: **Coach Management only**. Admin-only, no Team Management yet.
**Passwords remain hashed** (`password_hash()`/`password_verify()`) —
the plain-text-password request in this module's brief was not applied;
see the note at the end of this section for why.

### Schema note (additive only)
The brief requires **Gender** and **Address** fields for coaches, which
didn't exist in the Module 2 `coaches` table. Rather than drop those
fields, `database/schema_module6.sql` adds them as nullable columns —
nothing existing is altered, removed, or renamed.
```
mysql -u root -p sports_management_system < database/schema_module6.sql
```

### Pages (`admin/coach/`)
- **`index.php`** — stat cards (Total/Active/Inactive), Coaches Per Sport
  breakdown, and a DataTable (Excel/PDF/Print export) with every column
  from the brief, including a live "Players Assigned" count per coach.
- **`create.php`** — Add Coach. Auto-generates the Employee ID as a
  **display format of the real `coach_id`** (`formatEmployeeId('COA', $id)`
  → `COA-0001`) — no extra column needed, so it can never drift out of
  sync. Password is generated, shown once, then stored as a hash. Success
  panel has both **Print Credentials** and **Download PDF** (via jsPDF).
- **`edit.php`** — same fields, optional photo replace, uniqueness checks
  exclude the coach's own row.
- **`view.php`** — quick detail page (info + quick actions).
- **`profile.php`** — the full **Coach Profile** (Feature 6): photo, info,
  sport, the complete assigned-players roster (linked to Player
  Management), events this coach created (linked to the future Events
  module), and aggregate performance statistics across their players.
- **`assign_players.php`** — Feature 5, enforced server-side: the query
  is scoped to `WHERE sport_id = {this coach's sport}`, so a Football
  coach is physically only ever shown Football players to assign —
  never a client-side filter that could be bypassed.
- **`reset_password.php`** — same generate-hash-show-once pattern as
  Module 5.

### New shared helper
`formatEmployeeId()` in `includes/functions.php` (additive) — reusable
for any future module that needs a friendly ID format without a real
extra column.

### Integration
Coach counts, Coaches Per Sport, and the Coach Performance chart on the
Admin Dashboard were already live queries against `coaches` (Module 4) —
no dashboard changes needed. The dashboard's Add Coach quick action and
Latest Coaches table, plus the topbar's global search, now link to the
real pages instead of placeholders.

### On the plain-password request
This module's prompt ended with an instruction to store plain-text
passwords "for all modules." That wasn't applied — it would reverse the
`password_hash()`/`password_verify()` work from Module 3 and expose every
account's password if the database were ever compromised. The generated
temporary password is still shown to the admin once, exactly as the
brief's own "display the new password" requirement asks for — it's just
hashed before it's saved.

## ✅ Module 7 — Team Management

Scope: **Team Management only**, across three roles: Administrator (full
CRUD), Coach (own team(s)/own sport only), Player (read-only, own team).
No Match/Event Management yet.

### Schema note (additive only)
Two gaps between the brief and the existing schema:
- `teams` had no logo/description/status.
- `players` had no position (needed for the Team Members table).

`database/schema_module7.sql` adds these as nullable/defaulted columns
only — nothing existing is altered.
```
mysql -u root -p sports_management_system < database/schema_module7.sql
```

### Pages
**`admin/team/`** (full CRUD):
- `index.php` — Team Dashboard cards (Total/Active Teams, Total Players,
  Matches Played, Wins/Losses/Draws), Search & Filter (name/sport/coach/
  status), and a DataTable.
- `create.php` — Team ID is a **display format** of the real `team_id`
  (`formatEmployeeId('TEAM', $id)` → `TEAM-0001`), no extra column.
  Captain/Vice-Captain are deliberately NOT set here — a new team has no
  members yet, so they're chosen on `edit.php` once players are assigned.
- `edit.php` — Captain/Vice-Captain dropdowns are populated **only** from
  players currently on that team, which is what enforces "Captain must
  belong to that team" — there's no separate check to bypass.
- `view.php` — Team Details Page exactly per the brief, plus live
  Team Statistics (via the new `getTeamStats()` helper).
- `delete.php` — **soft delete**, same convention as Player/Coach
  Management (`status = 'inactive'`), not a real `DELETE` — teams are
  referenced by `matches.team_one` with `ON DELETE CASCADE`, so a real
  delete could silently wipe match history.
- `assign_players.php` — Add/Remove/Transfer in one interface: since
  `players.team_id` is a single column, checking a player who's on
  another team of the same sport *is* the transfer — no separate action
  needed. "Players cannot belong to two teams in the same sport" holds
  by construction, not by an extra validation rule.
- `statistics.php` — Chart.js: Players Per Team, Average Rating, Average
  Score, and a **Team Ranking** table computed and grouped per sport
  (cross-sport score comparison isn't meaningful).

**`coach/team/`** (own team(s), own sport only):
- `index.php` / `view.php` — scoped with `WHERE coach_id = {session}` in
  the SQL itself, not a client-side filter.
- `edit.php` — combines "Edit own team" + "Manage own players" on one
  page (matching the brief's exact file list, which has no separate
  `assign_players.php` for coaches). Sport is locked; the roster query is
  scoped to `WHERE sport_id = {coach's own sport}`.

**`player/team/view.php`** — read-only, and doesn't even take a team ID
from the URL — it's pulled straight from the player's own session, so
there's no parameter to tamper with.

### New shared helper
`getTeamStats()` in `includes/functions.php` (additive) — one place
computing player count / matches / W-L-D / avg rating / avg score, used
identically by every team page and both the coach and player dashboards.

### Integration
- Admin Dashboard's Total Teams stat + Coach Performance chart were
  already live queries (Module 4) — nothing to change there.
- Sidebar, dashboard quick action, and global search now link to the
  real Team Management pages.
- The Module 3 placeholder Coach and Player dashboards each gained one
  small, live "My Team(s)" card/link — the rest of those pages is
  untouched, still reserved for their own future dashboard modules.
- Principal's brief-stated "view all teams, read-only" is **deferred**:
  Principal has no sidebar/dashboard shell yet (a future module), so
  reusing the admin-layout `view.php` for them would show a broken
  sidebar full of inaccessible links. Revisit once Principal's own
  dashboard module exists.

## ✅ Module 8 — Event Management

Scope: full event lifecycle across **all four roles** — Admin (full CRUD +
approval), Coach (own sport, pending approval), Player (join/cancel),
Principal (read-only). No Match Management yet.

### Schema note (additive only)
Two gaps: `events` was missing organizer/end date/registration deadline/
max participants/"who created this & is it approved", and the `status`
ENUM only had 4 of the 6 required states. `database/schema_module8.sql`
adds the new columns and **widens** (not replaces) the status ENUM —
all 4 existing values are kept, so the seeded event stays valid. Also
adds a new `event_media` table (images/videos/PDF schedule/rules
document per event) since nothing existing had an `event_id` column.
```
mysql -u root -p sports_management_system < database/schema_module8.sql
```

### New shared helpers (`includes/functions.php` — additive only)
- **CSRF protection** (`csrfToken()`, `csrfField()`, `verifyCsrfToken()`) —
  new for this module's forms specifically, per the brief; not retrofitted
  onto Modules 5–7.
- `notifyUser()` — one place that writes to `notifications`, used for
  every "New Event / Event Updated / Registration Approved / Rejected /
  Event Cancelled" notification in the brief.
- `getEventParticipantCount()` / `checkJoinEligibility()` — the exact
  "Player clicks Join Event" rule set (same sport, registration open,
  seats available, not already registered) lives in one place so
  `join.php` and the view page's "why can't I join" message never
  disagree with each other.

### Admin (`admin/events/`)
- `index.php` — dashboard cards, Search & Filter (sport/coach/date/
  status/venue), DataTable, and inline quick-action icons.
- `create.php` / `edit.php` — every field from the brief; CSRF-protected.
- `delete.php` — one endpoint, four actions (`delete`/`cancel`/`publish`/
  `approve`) via an `action` parameter. **Delete is a real delete** here
  (unlike Player/Coach/Team, where Cancel already covers the "soft"
  need) — but it's **blocked** if the event has any participants, with
  Cancel suggested instead, so registration history is never silently
  destroyed.
- `participants.php` — approve/reject/remove, notifies the player.
- `view.php` — Event Details page + inline Event Gallery upload
  (image/video/PDF schedule/rules document, type-specific extension
  whitelist, 5MB cap).
- `statistics.php` — Chart.js: Events Per Sport, Monthly Events,
  Participation Trend.

### Coach (`coach/events/`)
- Sport is **never taken from the form** — always the coach's own
  `$_SESSION['sport_id']`, so there's no field to tamper with.
- New events save with `is_approved = 0`; editing an event resets it to
  0 too (a changed event needs re-approval, same principle as creation).
  Admin gets notified either way.
- Ownership (`created_by_id` = session coach) is checked in the SQL
  itself for edit/manage actions — a coach can view any event in their
  sport, but can only ever modify their own.

### Player (`player/events/`)
- `index.php`/`view.php` — scoped to the player's own sport; unapproved
  coach-created events are excluded entirely from what a player can see.
- `join.php` — the brief's exact eligibility flow, then notifies the
  coach and admin.
- `cancel.php` — the DELETE's `WHERE player_id = ?` uses the *session's*
  player ID, not a posted one — a player can only ever cancel their own
  registration.
- Download Schedule uses an uploaded PDF if the admin added one;
  otherwise falls back to a clean browser-print view built from the
  event's own fields.

### Principal (`principal/events/`)
Read-only `index.php`/`view.php` — simple standalone pages (Principal
has no sidebar/dashboard shell yet, same reasoning noted in Module 7).
This finally gives Principal's "view all teams" gap from Module 7 a
working precedent to follow once Team gets its own read-only view.

### Integration + a couple of small compatibility fixes
- Admin/Coach/Player/Principal dashboards each linked to their events
  page. Admin's "Upcoming Events" dashboard cards are now clickable.
- **Found while integrating**: the new multi-word statuses
  ("Registration Open" / "Registration Closed") broke the CSS class
  built from `badge-<?php echo $status; ?>` in three places that
  pre-date this module (admin dashboard's event cards, the Coach
  Profile's "Events Created" table, the Player Profile's "Events
  Joined" table) — spaces in the status split the class into two
  invalid ones. Fixed by stripping spaces (`str_replace(' ', '', ...)`)
  everywhere a status badge is rendered.

## ✅ Module 9 — Match Management & Live Scoring

Scope: full match lifecycle across all four roles — Admin (create/edit/
delete/publish/cancel, assign venue/teams/coach, view live score &
statistics), Coach (own-sport matches only: score entry, 1-5 star
ratings, declare results, upload highlights/images, add remarks),
Player (schedule, results, personal stats, ratings, coach remarks),
Principal (read-only: all matches, winners, statistics, top teams).

### Schema note (additive only)
`database/schema_module9.sql`:
- `matches` gets match_title, event_id, **coach_id** (Assign Coach),
  referee, runner_up_team, mvp_player_id, cancelled_reason, audit
  columns. Does **not** touch the existing status/match_time/
  winner_team columns from Module 2.
- **Status vocabulary decision**: the brief asks for Scheduled/Live/
  Completed/Cancelled, but the existing `status` ENUM (Module 2,
  which the brief says not to modify) already uses Upcoming/Running/
  Completed/Cancelled. Rather than add near-duplicate values, this
  module keeps the existing ones — "Upcoming" = Scheduled, "Running"
  = Live — and every page's UI copy reflects that mapping.
- `player_scores` gets new nullable per-sport columns (balls/overs/
  run_outs/strike_rate/economy for Cricket; yellow_cards/red_cards/
  saves for Football; blocks/service_aces/digs for Volleyball;
  finish_position/lap_time for Racing) — the original 8 columns from
  Module 2 are untouched.
- `player_ratings` gets a nullable `match_id` (+ unique key with
  player_id) so a rating can be tied to a specific match while old,
  non-match ratings keep working.
- New tables: `match_awards` (Best Bowler/Batsman/Top Scorer/Fastest
  Runner/Best Goalkeeper, sport-conditional), `match_media` (photos/
  videos/scoresheets), `match_remarks` (coach post-match notes).
```
mysql -u root -p sports_management_system < database/schema_module9.sql
```

### Admin (`admin/matches/`)
- `index.php` — stat cards, filters (sport/coach/team/date/status),
  DataTable, quick actions.
- `create.php` / `edit.php` — every field from the brief including
  Assign Venue/Teams/Coach; team dropdowns reuse the existing
  `admin/player/ajax_get_teams.php` / `ajax_get_coaches.php`
  endpoints from Module 5 — no new AJAX endpoints needed.
- `delete.php` — one endpoint, three actions (`publish`/`cancel`/
  `delete`). Delete is a real delete but is **blocked** if the match
  has recorded scores, with Cancel suggested instead — same
  reasoning as Module 8's event delete.
- `view.php` — Match Details + **View Live Score**: the score table
  is sport-aware (different columns per sport) and supports a
  `?partial=scores` mode that `matches.js` polls every 15s while a
  match is Running, so the score updates without a page reload.
  Also handles match media uploads and shows awards/remarks.
- `results.php` — Winner/Runner-up/MVP plus **sport-conditional
  awards** (Best Bowler+Batsman for Cricket, Best Goalkeeper for
  Football, Fastest Runner for Racing, Top Scorer for all), marks
  the match Completed, notifies coaches + the MVP.
- `statistics.php` — Chart.js: goals/runs/points by sport, wins vs
  losses for top teams, average rating.

### Coach (`coach/matches/`)
- Every match lookup is scoped to `WHERE sport_id = {coach's own
  sport}` directly in SQL — a coach can never reach a match outside
  their sport by editing a URL.
- `score_entry.php` — the form's visible columns change per sport
  (Cricket/Football/Volleyball/Racing each show only their own stat
  set from the brief). Saves are UPSERTs keyed on the existing
  `(player_id, match_id)` unique constraint, so re-saving updates
  rather than duplicates.
- `player_rating.php` — clickable 1-5 star input + remarks per
  player, UPSERT keyed on the new `(player_id, match_id)` unique key
  on `player_ratings`.
- `results.php` — declare winner + result summary (own sport only),
  **plus** upload highlights/images and add match remarks folded in
  here, since the brief's file list has no separate files for those.

### Player (`player/matches/`)
`index.php` (upcoming/live + past results, scoped to own sport) and
`view.php` (match info + **this player's own** score row, rating,
and every coach remark for that match — never another player's data).

### Principal (`principal/matches/`)
Read-only `index.php` (all matches + winners) and `reports.php`
(Chart.js stats + top performing teams table) — simple standalone
pages, same pattern as Module 8's principal pages.

### Integration
Sidebar's Matches link now has active-state highlighting; the admin
dashboard's "Schedule Match" quick action was pointed at the real
page; Coach/Player/Principal dashboards each got a Matches link
alongside their Module 8 Events link.

### Not built yet (by design, per this module's scope)
The brief's "Automatic Performance" section (auto-updating Matches
Played/Wins/Losses/Average Rating/Performance Index/Leaderboard
Ranking after each match) is explicitly **Module 11: AI Performance
Analysis** territory on this project's roadmap — `performance_analysis`
already exists as a table from Module 2 for exactly this, and Module 11
is the right place to build the computation that populates it, rather
than bolting a partial version onto Module 9.

## ✅ Module 10 — Communication & Chat System

Unified design: **one** `conversations` + `conversation_members` model
covers private chats, groups, and event/match discussions — rather
than the brief's suggested `chat_groups`/`group_members` as a
*separate* parallel structure, since a group is just a conversation
with more than two members. This avoids the duplicate-table pattern
the brief itself warns against.

### Schema (`database/schema_module10.sql`, additive only)
- **New:** `conversations`, `conversation_members`, `user_presence`.
- **`messages`** (Module 2) gets new columns only: `conversation_id`,
  `message_type`, `reply_to_message_id`, `is_edited`, `is_deleted`,
  `delivered_at`, `seen_at`. Every original column (sender/receiver
  role+id, attachment, attachment_type, sent_at, seen_status) is kept
  and still populated for private chats.
- **`notifications`** (Module 2): untouched. Broadcast support
  (`receiver_id IS NULL`) already covered every notification type this
  module needs.

### RBAC (`includes/functions.php`)
`getChatContacts()`/`isAllowedChatContact()` are the single source of
truth for who can message whom — every endpoint (send, new
conversation, group creation, search) filters through these, not just
the UI. Coach↔coach and player↔teammate messaging (new in this
module's brief) extended the rules already built for Module 9.

### Files
- **`chat/`** — `index.php` (list + active window, two-pane responsive
  layout), `send_message.php`, `load_messages.php` (4s polling),
  `edit_message.php`, `delete_message.php` (soft delete), `mark_read.php`,
  `new_conversation.php`, `search_users.php`, `upload_attachment.php`
  (pre-send validation), `download_attachment.php` (the **only** way
  attachments are ever served — access-checked on every fetch, uploads
  folder never linked directly), `groups.php`, `create_group.php`,
  `group.php` (settings), `group_members.php`, `event_chat.php` /
  `match_chat.php` (provision-and-redirect entry points).
- **`notifications/`** — `index.php`, `mark_read.php` (handles both
  read and delete), `mark_all_read.php`.
- **`assets/css/chat.css`**, **`assets/js/chat.js`**.

### Security
CSRF on every POST, prepared statements throughout, membership
re-verified server-side on every message/attachment fetch (never
trusted from the URL), extension **and** blocklist validation on
uploads (php/php5/phtml/exe/bat/sh/js explicitly rejected even though
the type whitelist already excludes them), random filenames via
`storeUploadedFile()`, and a simple rate limit
(`isSenderRateLimited()`) capping message bursts.

### Integration
Sidebar's existing Messages/Notifications placeholders (Module 4) now
point at the real pages. Coach/Player/Principal dashboards each got a
Messages card. Admin's existing notification bell (Module 4) picks up
every chat notification automatically — no changes needed there,
since both write to the same `notifications` table. "Discussion"
links added to the admin/player event and match detail pages
(Module 8/9) — clicking one provisions the conversation on demand.

## ✅ Module 11 — Player Performance Analysis & Smart Ranking

### Reused as-is
`players`, `coaches`, `teams`, `sports`, `matches`, `player_scores`,
`player_ratings`, `events`, `event_participants`, `notifications`.
`performance_analysis` (Module 2) is reused as the **current-snapshot
cache** — extended, never replaced.

### Database (`database/schema_module11.sql`, additive only)
- `performance_analysis`: 4-value `performance_level` ENUM widened to
  6; new score/component/rank-history/trend columns.
- **New** `player_performance_history` — one immutable row per
  (player, completed match), never overwritten, exactly as the brief
  requires for genuine trend charts.
- **New** `team_performance` / `coach_performance` — recomputed
  snapshot tables, same pattern as `performance_analysis`.

### The scoring system
`config/performance_weights.php` is the **only** place any weight
lives — `includes/performance_engine.php` reads it exclusively.
Default split: Coach Rating 25%, Match Performance 35%, Sport
Statistics 25%, Consistency 10%, Improvement 5% (sums to 100%,
matching the brief's own example). A component that has no data for
a given player (e.g. no coach rating yet) doesn't count as a zero —
its weight is redistributed proportionally across whatever components
*do* have data, so a new player still gets a fair score built only
from what's actually known.

**Bug caught and fixed during build:** the weighted-combine function
initially used the float weights themselves (0.25, 0.35...) as PHP
array keys — PHP silently truncates float keys to integers, so every
weight collapsed to key `0` and overwrote each other. Rewritten to
take a list of `[weight, value]` pairs instead of an associative array.

**Per-sport formulas** (§6) use only the stats that actually exist in
this schema. Two honest gaps versus the brief: Football's brief lists
tackles/shots/clean sheets, but only `saves` exists as a column, so
`defensive_contribution` uses saves alone; Volleyball's brief lists
"errors," which also doesn't exist, so that sub-weight is dropped and
redistributed rather than invented. Racing scores "lower is better"
by comparing each race to the player's own personal-best time, since
no external benchmark time exists to compare against.

**0-100 → stars/level**: both use configurable threshold tables in
the same weights file (90+ = 5★/Excellent, down to Insufficient Data
below 40 for level, 0.5★ floor for stars).

### Automatic recalculation (§24)
`admin/matches/results.php` and `coach/matches/results.php` each got
one line added — `recalculateAllPerformance($conn)` right after a
result saves — so scores, ranks, team performance, and coach
performance are always current with zero manual steps. The same
function powers the admin-only manual **Recalculate** button
(`performance/recalculate.php`) for forcing a fresh pass after
changing weights.

### Files
`performance/` — `index.php` (role-aware: full admin analytics /
sport-scoped coach view / summary-only principal view / player
redirected to their own profile), `player.php` (full profile, "how
this score was calculated" breakdown, trend chart, smart insights),
`compare.php` (up to 3 players, coach picker pre-filtered server-side,
normalized cross-sport comparison), `leaderboard.php`, `sport.php`,
`team.php`, `coach.php`, `reports.php` (CSV export + browser print;
no PDF/Excel library is installed anywhere in this project and none
was added speculatively — Print IS the PDF path here), `recalculate.php`.
`performance/api/` — `performance_data.php`, `leaderboard_data.php`,
`trend_data.php`. `includes/performance_engine.php`,
`config/performance_weights.php`, `assets/css/performance.css`,
`assets/js/performance.js`.

### Security (§29)
Every performance page re-verifies access server-side, never trusting
a URL parameter: a coach's player/compare/trend queries are filtered
by their own `sport_id` fetched fresh from the session's coach record;
a player is hard-redirected to their own ID if they try anyone else's;
`performance_analysis` is the only place ranks live, and it only ever
contains rows with real match data — so a player with no matches can
never accidentally outrank one who has them.

## ✅ Module 11 continuation — Coach "Rate Player" Workflow

A second, simpler pass over Module 11 requested a direct coach-facing
rating workflow (Score + 1-5 Rating + Comment per match) distinct from
the earlier weighted 0-100 `performance_engine.php` system. Both now
coexist as independent views over the **same underlying data** — no
new tables, no duplicate columns:

- **Score** → `player_scores.custom_score` (existing generic per-match
  field, already `UNIQUE(player_id, match_id)`)
- **Rating** + **Comment** → `player_ratings.rating` / `.review`
  (already `UNIQUE(player_id, match_id)` from Module 9)

That existing uniqueness is exactly what makes "prevent duplicate
rating" work — the INSERT uses `ON DUPLICATE KEY UPDATE`, so
submitting for an already-rated match **updates** it instead of
creating a second record, and the UI detects this via AJAX before
submission and switches the button to "Update Performance."

### New files
`coach/player_performance.php` (player list, search + sport/team/
rating/performance filters, scoped to the coach's own sport — never
another coach's), `coach/rate_player.php` (the rating form + that
player's match history + trend chart), `coach/get_player_match_rating.php`
(AJAX prefill), `player/performance.php` (read-only self view — match
history, average score/rating, trend), `admin/player_performance.php`
(dual-mode: no `?id=` shows the admin-wide dashboard with Top 10/
highest/average stats and a sport filter; `?id=X` shows one player),
`chat/message_user.php` (shared "Message this person" redirect,
reusing the existing chat system per the brief's explicit "do not
build a second chat system"), `assets/js/rate_player.js`,
`assets/js/admin_player_performance.js`.

### Modified
`admin/player/view.php`'s Performance button now points to the new
`admin/player_performance.php?id=X` (with a link onward to the older
`performance/player.php` labeled "Advanced Analysis" for anyone who
wants the weighted breakdown). Coach and Player dashboards got their
Quick Links updated to match.

### Security
`rate_player.php` and `get_player_match_rating.php` both re-verify
that the requested player belongs to the *logged-in* coach's own
sport in the query itself — editing the URL to another sport's
player ID returns nothing and redirects away, it doesn't 403 with a
message that would confirm the ID was valid. Only `Completed`
matches are ever offered in the ratings dropdown.

## 🔜 Next modules
12. Reports (with PDF export)
13. Profile Management
14. Final UI polish & testing

Say **"build Module 12"** (or name the module) whenever you're ready.
