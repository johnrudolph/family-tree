---
paths:
  - '**'
---

# General

## Add a Change Log entry for every user-facing PR
Every PR that includes a user-facing change (a feature, fix, or visible behavior change) must add a row to `database/seeders/ChangeLogSeeder.php`-style entry in the `change_log_entries` table (via a migration-safe path such as a small seeder addition or a direct `ChangeLogEntry::create()` call in the PR) so it shows up on the Change Log page (`/changelog`).

Rules for entries:
- One short sentence per change, written like a release note.
- Plain, non-technical language. No em dashes. No Claude/AI jargon ("leverage", "robust", "seamless", etc).
- Only user-facing changes get an entry. Skip internal refactors, test-only changes, dependency bumps, and other invisible work.
- Set `merged_at` to the actual merge time (or push time) so the diffForHumans ordering on the page stays accurate.
