# Database Architecture — Module 2
Sports Management System · Government M.A.O Graduate College Lahore

Database name: **`sports_management_system`** · Engine: **InnoDB** · Charset: **utf8mb4**

---

## 1. Text-format Entity Relationship Diagram

```
sports (1) ───────────< coaches (M)
   │                        │
   │                        │ (1)
   │                        ▼
   │                  players (M) ────────< team_players >──────── teams (M)
   │                        │  ▲                                       │
   │                        │  │ captain_id / vice_captain_id (1)      │
   │                        │  └───────────────────────────────────────┘
   │                        │
   │                        ├──────< event_participants >──── events (M)
   │                        │                                    │
   │                        ├──────< player_scores >──── matches (M) ─┐
   │                        │                                    ▲    │
   │                        └──────< player_ratings                │  team_one/two/winner_team
   │                        │                                       │
   │                        └──────< performance_analysis (1:1)     │
   │                                                                │
   └──────────────< teams / events / matches (all carry sport_id) ─┘

admins ────< gallery (uploaded_by)
admins, coaches, players  ─(polymorphic, app-enforced)─>  messages
admins, coaches, players  ─(polymorphic, app-enforced)─>  notifications
admins, coaches, players  ─(polymorphic, app-enforced)─>  activity_logs
coaches / players / admins            ─(polymorphic, app-enforced)─>  documents

Legend:  (1) one side · (M) many side · >──< many-to-many junction
```

---

## 2. How every table connects

**`sports`** is the root of the whole schema — exactly Cricket, Football, and
Hockey. `coaches`, `players`, `teams`, `events`, and `matches` all carry a
`sport_id` foreign key back to it, which is what lets every dashboard later
be filtered "show me only Football" or "only Cricket."

**`coaches` → `sports`**: each coach has exactly one `sport_id` (one coach,
one sport, per the brief). Deleting a sport cascades and removes its
coaches (`ON DELETE CASCADE`) — a sport that no longer exists shouldn't
have orphaned coach accounts.

**`players` → `coaches`, `sports`, `teams`**: a player belongs to one coach
(`coach_id`, nullable — a player can exist before being assigned a coach)
and one sport (`sport_id`, required). `team_id` links a player to their
roster team, but is nullable (a player can be unassigned) and uses
`ON DELETE SET NULL` — if their team is deleted, the player record survives,
just without a team.

**`teams` → `sports`, `coaches`, `players` (captain/vice-captain)**: each
team plays one sport and has one coach. `captain_id` and `vice_captain_id`
both point back into `players`. This creates the schema's one circular
reference (`players.team_id` ↔ `teams.captain_id`), which is why
`players.team_id`'s foreign key is added with a separate `ALTER TABLE`
statement *after* `teams` is created — `teams` can't exist without
`players` existing first, and `players.team_id` can't be constrained
without `teams` existing.

**`team_players`** is the classic many-to-many junction between `teams` and
`players`: a team has many players, and — across seasons — a player could
appear on more than one team's roster history, so this junction (rather
than a single column) is what makes the relationship many-to-many instead
of one-to-many. A `UNIQUE(team_id, player_id)` constraint stops the same
player being added to the same team twice.

**`events` → `sports`, `coaches`**: an event belongs to one sport and is
optionally tied to the coach who created/runs it.

**`event_participants`** is the many-to-many junction between `events` and
`players` — a player can register for many events, an event can have many
registered players — with a `participation_status` (Pending/Approved/
Rejected) tracking the coach's approval per the brief's Participation
Module.

**`matches` → `sports`, `teams` (×3: `team_one`, `team_two`, `winner_team`)**:
a match belongs to one sport and pits two teams against each other.
`team_two` and `winner_team` are nullable for schema flexibility, though
Cricket, Football, and Hockey are all always team-vs-team.

**`player_scores` → `players`, `coaches`, `matches`**: one row per
player-per-match, holding every sport's stat columns (`goals`, `runs`,
`wickets`, `catches`, `assists`, `race_time`, `points`, `custom_score`) so a
single flexible table can serve all four sports rather than one table per
sport. A `UNIQUE(player_id, match_id)` stops duplicate score entries for
the same player in the same match.

**`player_ratings` → `players`, `coaches`**: a coach's 1–5 star rating and
written review of a player, independent of any specific match.

**`performance_analysis` → `players` (1:1)**: a cached, pre-computed summary
row per player — average rating, total score, matches played, rank, and a
derived performance level. This table exists to be **recalculated** (by a
future "AI Performance Analysis" module) rather than queried live from
`player_scores`/`player_ratings` on every page load, which is what makes
leaderboards and reports fast. `UNIQUE(player_id)` enforces the 1:1
relationship.

**`messages`, `notifications`, `activity_logs`, `documents`** are
intentionally **polymorphic**: `sender_role`/`receiver_role`/`user_role`/
`uploader_role` columns say *which* table an ID belongs to (admin, coach,
or player), because a single foreign key column can't reference three
different parent tables at once. Referential integrity for these is
enforced in the PHP application layer (e.g. "does this player_id actually
exist before inserting a message") rather than by the database engine —
this is a standard, deliberate trade-off for polymorphic association
patterns, not an oversight.

**`gallery` → `admins`**: since only admins manage the gallery in this
system, `uploaded_by` gets a real, enforced foreign key (unlike the
polymorphic tables above).

---

## 3. Design decisions worth knowing about

- **`ON DELETE CASCADE` vs `ON DELETE SET NULL`** — used deliberately, not
  uniformly. Cascade is used where a child record is meaningless without
  its parent (e.g. delete a sport → its coaches and players go with it,
  since "a football coach with no Football program" doesn't make sense
  here). `SET NULL` is used where the child should survive its parent's
  deletion (e.g. delete a team → its players remain, just unassigned).
- **`rank` is backtick-quoted** (`` `rank` ``) in `performance_analysis`
  because `RANK` became a reserved word in MySQL 8.0 (window functions).
  Without backticks, `CREATE TABLE` and every later query referencing it
  would throw a syntax error.
- **3NF**: every table's non-key columns depend only on that table's whole
  primary key. Repeating data (like a sport's name) lives in exactly one
  place (`sports`) and everything else references it by ID — nothing is
  duplicated across tables.
- **All foreign key columns are indexed** (`KEY idx_...`), which InnoDB
  requires anyway to enforce the constraint efficiently, and which keeps
  every future `JOIN` (e.g. "all players for this coach") fast.

---

## 4. Files delivered in this module

| File | Purpose |
|---|---|
| `database/schema.sql` | `CREATE DATABASE`, all 18 `CREATE TABLE` statements, the one `ALTER TABLE` (closing the players/teams loop), all foreign keys/indexes/constraints, and sample seed data. |
| `includes/config.php` | Site constants (from Module 1) **plus** a real `mysqli` connection to `sports_management_system`. Connection failures are non-fatal — Module 1's landing pages keep working even before you've imported the schema. |
| `database/ER_DIAGRAM.md` | This file. |

## 5. Import instructions

1. Start MySQL in the XAMPP control panel.
2. phpMyAdmin → **Import** → choose `database/schema.sql` → Go.
   *(Or via terminal: `mysql -u root -p < database/schema.sql`)*
3. Confirm `includes/config.php` has the right `DB_USER` / `DB_PASS` for
   your MySQL install (defaults match a fresh XAMPP install: user `root`,
   no password).
4. That's it — no code changes needed elsewhere in Module 1.

**Next up (Module 3):** Authentication — Admin / Coach / Player login,
forgot/reset password, session guards, and logout, built on top of this
schema and connection.
